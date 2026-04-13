<?php
require 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Controleer of admin is ingelogd
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bezoeken.php');
    exit;
}

$errors = [];
$is_update = (($_POST['action'] ?? '') === 'update');
$bezoek_id = (int)($_POST['bezoek_id'] ?? 0);

if ($is_update && $bezoek_id <= 0) {
    $errors[] = 'Ongeldig bezoek om te bewerken.';
}

if ($is_update && $bezoek_id > 0) {
    $stmtExisting = $conn->prepare('SELECT bezoek_id FROM bezoek WHERE bezoek_id = ? LIMIT 1');
    $stmtExisting->bind_param('i', $bezoek_id);
    $stmtExisting->execute();
    $exists = $stmtExisting->get_result()->fetch_assoc();
    $stmtExisting->close();

    if (!$exists) {
        $errors[] = 'Het bezoek dat je wilt bewerken bestaat niet meer.';
    }
}

// 1. VALIDEER BASISVELDEN
$bezoek_naam      = substr(trim($_POST['bezoek_naam'] ?? ''), 0, 255);
$onderwijs_type   = trim($_POST['onderwijs_type'] ?? '');
$bezoek_pincode   = trim($_POST['bezoek_pincode'] ?? '');
$bezoek_schooljaar = preg_replace('/\s+/', ' ', trim($_POST['bezoek_schooljaar'] ?? ''));

if (!is_geldig_schooljaar($bezoek_schooljaar)) {
    $errors[] = 'Selecteer een geldig schooljaar.';
}

if (!$bezoek_pincode) {
    $errors[] = 'Vul een pincode in.';
} else {
    // Unieke pincode check (bij bewerken: huidige record uitsluiten)
    if ($is_update) {
        $stmtCheck = $conn->prepare('SELECT COUNT(*) as cnt FROM bezoek WHERE pincode = ? AND bezoek_id <> ?');
        $stmtCheck->bind_param('si', $bezoek_pincode, $bezoek_id);
    } else {
        $stmtCheck = $conn->prepare('SELECT COUNT(*) as cnt FROM bezoek WHERE pincode = ?');
        $stmtCheck->bind_param('s', $bezoek_pincode);
    }

    $stmtCheck->execute();
    $rowCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if ((int)$rowCheck['cnt'] > 0) {
        $errors[] = 'Deze pincode is al in gebruik door een ander bezoek.';
    }
}

$bezoek_max_keuzes_raw = $_POST['bezoek_max_keuzes'] ?? null;

$bezoek_dag1 = trim($_POST['bezoek_dag1'] ?? '');
$bezoek_dag2 = trim($_POST['bezoek_dag2'] ?? '');
$bezoek_week_start = trim($_POST['bezoek_week_start'] ?? '');
$bezoek_week_eind = trim($_POST['bezoek_week_eind'] ?? '');

if (!$bezoek_naam) {
    $errors[] = 'Vul de bezoeknaam in.';
}

if (!in_array($onderwijs_type, ['Primair Onderwijs', 'Voortgezet Onderwijs', 'MBO'], true)) {
    $errors[] = 'Selecteer een geldig onderwijstype.';
}

if ($bezoek_max_keuzes_raw === null || !in_array((int)$bezoek_max_keuzes_raw, [2, 3], true)) {
    $errors[] = 'Selecteer het aantal keuzes (2 of 3).';
} else {
    $bezoek_max_keuzes = (int)$bezoek_max_keuzes_raw;
}

if ($onderwijs_type === 'Primair Onderwijs') {
    if (!$bezoek_dag1) {
        $errors[] = 'Vul dag 1 (datum + tijd) in.';
    }
    if (!$bezoek_dag2) {
        $errors[] = 'Vul dag 2 (datum + tijd) in.';
    }
    if ($bezoek_dag1 && $bezoek_dag2 && strtotime($bezoek_dag2) < strtotime($bezoek_dag1)) {
        $errors[] = 'Dag 2 mag niet voor dag 1 liggen.';
    }
}

if ($onderwijs_type === 'Voortgezet Onderwijs' || $onderwijs_type === 'MBO') {
    if (!$bezoek_week_start) {
        $errors[] = 'Vul week start in.';
    }
    if (!$bezoek_week_eind) {
        $errors[] = 'Vul week einde in.';
    }
    if ($bezoek_week_start && $bezoek_week_eind && strtotime($bezoek_week_eind) < strtotime($bezoek_week_start)) {
        $errors[] = 'Week einde mag niet voor week start liggen.';
    }
}

// 2. VALIDEER SCHOLEN
$school_ids_raw = $_POST['school_ids'] ?? [];
$school_ids = [];
if (is_array($school_ids_raw)) {
    foreach ($school_ids_raw as $id_raw) {
        $id = (int)$id_raw;
        if ($id > 0) {
            $school_ids[] = $id;
        }
    }
}
$school_ids = array_values(array_unique($school_ids));

if (empty($school_ids)) {
    $errors[] = 'Selecteer minimaal 1 school.';
}

// 3. VALIDEER KLASSEN
$klas_ids_raw = $_POST['klas_ids'] ?? [];
$klas_ids = [];
if (is_array($klas_ids_raw)) {
    foreach ($klas_ids_raw as $id_raw) {
        $id = (int)$id_raw;
        if ($id > 0) {
            $klas_ids[] = $id;
        }
    }
}
$klas_ids = array_values(array_unique($klas_ids));

if (empty($klas_ids)) {
    $errors[] = 'Selecteer minimaal 1 klas.';
}

// 4. VALIDEER VOORKEUREN
$voorkeur_namen_raw = $_POST['voorkeur_naam'] ?? [];
$voorkeur_max_raw = $_POST['voorkeur_max'] ?? [];

$voorkeuren = [];
if (is_array($voorkeur_namen_raw)) {
    foreach ($voorkeur_namen_raw as $i => $naam_raw) {
        $naam = substr(trim($naam_raw), 0, 255);
        $max_leerlingen = isset($voorkeur_max_raw[$i]) ? max(1, (int)$voorkeur_max_raw[$i]) : 1;
        if ($naam !== '') {
            $voorkeuren[] = [
                'naam' => $naam,
                'max_leerlingen' => $max_leerlingen,
            ];
        }
    }
}

if (count($voorkeuren) < 3) {
    $errors[] = 'Voeg minimaal 3 voorkeuren toe.';
}

// 5. CONTROLEER SCHOOL COVERAGE (minimaal 1 klas per geselecteerde school)
if (!empty($school_ids) && !empty($klas_ids)) {
    $inClause = implode(',', $school_ids);
    $klasInClause = implode(',', $klas_ids);
    $sql = "
        SELECT DISTINCT k.school_id FROM klas k
        WHERE k.klas_id IN ($klasInClause) AND k.school_id IN ($inClause)
    ";
    $result = $conn->query($sql);
    $covered_schools = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $covered_schools[] = (int)$row['school_id'];
        }
    }

    $missing = array_diff($school_ids, $covered_schools);
    if (!empty($missing)) {
        $errors[] = 'Selecteer minimaal 1 klas voor iedere gekozen school.';
    }
}

// Als er fouten zijn, teruggaan naar formulier met foutmelding
if (!empty($errors)) {
    $_SESSION['bezoeken_errors'] = $errors;
    $_SESSION['bezoeken_post'] = $_POST;
    if ($is_update && $bezoek_id > 0) {
        header('Location: bezoeken.php?edit=' . $bezoek_id);
    } else {
        header('Location: bezoeken.php');
    }
    exit;
}

// 6. OPSLAAN IN DATABASE
$conn->begin_transaction();
try {
    // Mappeer onderwijs_type naar enum waarde
    $type_enum = '';
    if ($onderwijs_type === 'Primair Onderwijs') {
        $type_enum = 'PO';
    } elseif ($onderwijs_type === 'Voortgezet Onderwijs') {
        $type_enum = 'VO';
    } elseif ($onderwijs_type === 'MBO') {
        $type_enum = 'MBO';
    }

    if ($is_update) {
        // Bij update worden ongebruikte datumvelden op NULL gezet.
        $po_dag1_db = ($type_enum === 'PO') ? $bezoek_dag1 : null;
        $po_dag2_db = ($type_enum === 'PO') ? $bezoek_dag2 : null;
        $vo_week_start_db = ($type_enum === 'PO') ? null : $bezoek_week_start;
        $vo_week_eind_db = ($type_enum === 'PO') ? null : $bezoek_week_eind;

        $stmt = $conn->prepare('
            UPDATE bezoek
            SET naam=?, type_onderwijs=?, schooljaar=?, pincode=?, max_keuzes=?,
                po_dag1=?, po_dag2=?, vo_week_start=?, vo_week_eind=?
            WHERE bezoek_id=?
        ');
        $stmt->bind_param(
            'ssssissssi',
            $bezoek_naam,
            $type_enum,
            $bezoek_schooljaar,
            $bezoek_pincode,
            $bezoek_max_keuzes,
            $po_dag1_db,
            $po_dag2_db,
            $vo_week_start_db,
            $vo_week_eind_db,
            $bezoek_id
        );
        $stmt->execute();
        $stmt->close();

        // Koppelingen vervangen door de nieuwe selectie.
        foreach ([
            'DELETE FROM bezoek_school WHERE bezoek_id=?',
            'DELETE FROM bezoek_klas WHERE bezoek_id=?',
            'DELETE FROM bezoek_optie WHERE bezoek_id=?',
        ] as $deleteSql) {
            $stmtDelete = $conn->prepare($deleteSql);
            $stmtDelete->bind_param('i', $bezoek_id);
            $stmtDelete->execute();
            $stmtDelete->close();
        }
    } else {
        // Insert bezoek: PO gebruikt dag1/dag2, VO/MBO gebruikt week start/eind
        if ($type_enum === 'PO') {
            $stmt = $conn->prepare('
                INSERT INTO bezoek (naam, type_onderwijs, schooljaar, pincode, max_keuzes, po_dag1, po_dag2, actief)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ');
            $stmt->bind_param(
                'sssisss',
                $bezoek_naam,
                $type_enum,
                $bezoek_schooljaar,
                $bezoek_pincode,
                $bezoek_max_keuzes,
                $bezoek_dag1,
                $bezoek_dag2
            );
        } else {
            $stmt = $conn->prepare('
                INSERT INTO bezoek (naam, type_onderwijs, schooljaar, pincode, max_keuzes, vo_week_start, vo_week_eind, actief)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ');
            $stmt->bind_param(
                'sssisss',
                $bezoek_naam,
                $type_enum,
                $bezoek_schooljaar,
                $bezoek_pincode,
                $bezoek_max_keuzes,
                $bezoek_week_start,
                $bezoek_week_eind
            );
        }

        $stmt->execute();
        $bezoek_id = $conn->insert_id;
        $stmt->close();
    }

    // Insert scholen in bezoek_school
    $stmt_school = $conn->prepare('INSERT INTO bezoek_school (bezoek_id, school_id) VALUES (?, ?)');
    foreach ($school_ids as $school_id) {
        $stmt_school->bind_param('ii', $bezoek_id, $school_id);
        $stmt_school->execute();
    }
    $stmt_school->close();

    // Insert klassen in bezoek_klas
    $stmt_klas = $conn->prepare('INSERT INTO bezoek_klas (bezoek_id, klas_id) VALUES (?, ?)');
    foreach ($klas_ids as $klas_id) {
        $stmt_klas->bind_param('ii', $bezoek_id, $klas_id);
        $stmt_klas->execute();
    }
    $stmt_klas->close();

    // Insert voorkeuren in bezoek_optie
    $stmt_optie = $conn->prepare('
        INSERT INTO bezoek_optie (bezoek_id, volgorde, naam, max_leerlingen, actief)
        VALUES (?, ?, ?, ?, 1)
    ');
    foreach ($voorkeuren as $index => $voorkeur) {
        $volgorde = $index + 1;
        $naam = $voorkeur['naam'];
        $max_leerlingen = $voorkeur['max_leerlingen'];
        $stmt_optie->bind_param('iisi', $bezoek_id, $volgorde, $naam, $max_leerlingen);
        $stmt_optie->execute();
    }
    $stmt_optie->close();

    $conn->commit();

    $_SESSION['bezoeken_success'] = $is_update ? 'Bezoek succesvol bijgewerkt!' : 'Bezoek succesvol toegevoegd!';
    header('Location: bezoeken.php?highlight=' . $bezoek_id);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log('Fout bij opslaan bezoek: ' . $e->getMessage());
    $_SESSION['bezoeken_errors'] = ['Er is iets misgegaan bij het opslaan van het bezoek.'];
    $_SESSION['bezoeken_post'] = $_POST;
    if ($is_update && $bezoek_id > 0) {
        header('Location: bezoeken.php?edit=' . $bezoek_id);
    } else {
        header('Location: bezoeken.php');
    }
    exit;
}
