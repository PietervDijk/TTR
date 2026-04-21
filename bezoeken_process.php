<?php
// Verwerk het formulier uit bezoeken.php: valideer invoer en sla transactioneel op
require_once 'includes/functions.php';
require 'includes/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Controleer adminrechten voor bezoeken opslaan
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bezoeken.php');
    exit;
}

$foutmeldingen = [];
$is_bewerken = (($_POST['action'] ?? '') === 'update');
$te_bewerken_bezoek_id = (int)($_POST['bezoek_id'] ?? 0);

if ($is_bewerken && $te_bewerken_bezoek_id <= 0) {
    $foutmeldingen[] = 'Ongeldig bezoek om te bewerken.';
}

if ($is_bewerken && $te_bewerken_bezoek_id > 0) {
    $stmtBestaand = $conn->prepare('SELECT bezoek_id FROM bezoek WHERE bezoek_id = ? LIMIT 1');
    $stmtBestaand->bind_param('i', $te_bewerken_bezoek_id);
    $stmtBestaand->execute();
    $bestaat = $stmtBestaand->get_result()->fetch_assoc();
    $stmtBestaand->close();

    if (!$bestaat) {
        $foutmeldingen[] = 'Het bezoek dat je wilt bewerken bestaat niet meer.';
    }
}

// 1) Valideer basisvelden voor bezoek
$bezoekNaam = substr(trim($_POST['bezoek_naam'] ?? ''), 0, 255);
$onderwijsType = trim($_POST['onderwijs_type'] ?? '');
$bezoekPincode = trim($_POST['bezoek_pincode'] ?? '');
$bezoekSchooljaar = preg_replace('/\s+/', ' ', trim($_POST['bezoek_schooljaar'] ?? ''));

if (!is_geldig_schooljaar($bezoekSchooljaar, 2, 3)) {
    $foutmeldingen[] = 'Selecteer een geldig schooljaar.';
}

if (!$bezoekPincode) {
    $foutmeldingen[] = 'Vul een pincode in.';
} else {
    // Controleer unieke pincode (bij update: huidige record uitsluiten)
    if ($is_bewerken) {
        $stmtCheck = $conn->prepare('SELECT COUNT(*) as cnt FROM bezoek WHERE pincode = ? AND bezoek_id <> ?');
        $stmtCheck->bind_param('si', $bezoekPincode, $te_bewerken_bezoek_id);
    } else {
        $stmtCheck = $conn->prepare('SELECT COUNT(*) as cnt FROM bezoek WHERE pincode = ?');
        $stmtCheck->bind_param('s', $bezoekPincode);
    }

    $stmtCheck->execute();
    $rowCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if ((int)$rowCheck['cnt'] > 0) {
        $foutmeldingen[] = 'Deze pincode is al in gebruik door een ander bezoek.';
    }
}

$bezoekMaxKeuzesRuw = $_POST['bezoek_max_keuzes'] ?? null;

$bezoekDag1 = trim($_POST['bezoek_dag1'] ?? '');
$bezoekDag2 = trim($_POST['bezoek_dag2'] ?? '');
$bezoekWeekStart = trim($_POST['bezoek_week_start'] ?? '');
$bezoekWeekEind = trim($_POST['bezoek_week_eind'] ?? '');

if (!$bezoekNaam) {
    $foutmeldingen[] = 'Vul de bezoeknaam in.';
}

if (!in_array($onderwijsType, ['Primair Onderwijs', 'Voortgezet Onderwijs', 'MBO'], true)) {
    $foutmeldingen[] = 'Selecteer een geldig onderwijstype.';
}

if ($bezoekMaxKeuzesRuw === null || !in_array((int)$bezoekMaxKeuzesRuw, [2, 3], true)) {
    $foutmeldingen[] = 'Selecteer het aantal keuzes (2 of 3).';
} else {
    $bezoekMaxKeuzes = (int)$bezoekMaxKeuzesRuw;
}

if ($onderwijsType === 'Primair Onderwijs') {
    if (!$bezoekDag1) {
        $foutmeldingen[] = 'Vul dag 1 (datum + tijd) in.';
    }
    if (!$bezoekDag2) {
        $foutmeldingen[] = 'Vul dag 2 (datum + tijd) in.';
    }
    if ($bezoekDag1 && $bezoekDag2 && strtotime($bezoekDag2) < strtotime($bezoekDag1)) {
        $foutmeldingen[] = 'Dag 2 mag niet voor dag 1 liggen.';
    }
}

if ($onderwijsType === 'Voortgezet Onderwijs' || $onderwijsType === 'MBO') {
    if (!$bezoekWeekStart) {
        $foutmeldingen[] = 'Vul week start in.';
    }
    if (!$bezoekWeekEind) {
        $foutmeldingen[] = 'Vul week einde in.';
    }
    if ($bezoekWeekStart && $bezoekWeekEind && strtotime($bezoekWeekEind) < strtotime($bezoekWeekStart)) {
        $foutmeldingen[] = 'Week einde mag niet voor week start liggen.';
    }
}

// 2) Valideer geselecteerde scholen
$schoolIdsRuw = $_POST['school_ids'] ?? [];
$schoolIds = [];
if (is_array($schoolIdsRuw)) {
    foreach ($schoolIdsRuw as $idRuw) {
        $id = (int)$idRuw;
        if ($id > 0) {
            $schoolIds[] = $id;
        }
    }
}
$schoolIds = array_values(array_unique($schoolIds));

if (empty($schoolIds)) {
    $foutmeldingen[] = 'Selecteer minimaal 1 school.';
} else {
    $schoolInClause = implode(',', $schoolIds);
    $schoolCheck = $conn->query("SELECT school_id FROM school WHERE school_id IN ($schoolInClause)");
    $geldigeSchoolIds = [];
    while ($schoolRow = $schoolCheck->fetch_assoc()) {
        $geldigeSchoolIds[] = (int)$schoolRow['school_id'];
    }

    if (count($geldigeSchoolIds) !== count($schoolIds)) {
        $foutmeldingen[] = 'Er zijn ongeldige scholen geselecteerd.';
    }
}

// 3) Valideer geselecteerde klassen
$klasIdsRuw = $_POST['klas_ids'] ?? [];
$klasIds = [];
if (is_array($klasIdsRuw)) {
    foreach ($klasIdsRuw as $idRuw) {
        $id = (int)$idRuw;
        if ($id > 0) {
            $klasIds[] = $id;
        }
    }
}
$klasIds = array_values(array_unique($klasIds));

if (empty($klasIds)) {
    $foutmeldingen[] = 'Selecteer minimaal 1 klas.';
} else {
    $klasInClause = implode(',', $klasIds);
    $klasCheck = $conn->query("SELECT klas_id, school_id FROM klas WHERE klas_id IN ($klasInClause)");
    $gevondenKlasIds = [];
    $klasSchoolMap = [];

    while ($klasRow = $klasCheck->fetch_assoc()) {
        $gevondenKlasIds[] = (int)$klasRow['klas_id'];
        $klasSchoolMap[(int)$klasRow['klas_id']] = (int)$klasRow['school_id'];
    }

    if (count($gevondenKlasIds) !== count($klasIds)) {
        $foutmeldingen[] = 'Er zijn ongeldige klassen geselecteerd.';
    }

    foreach ($klasIds as $klasId) {
        if (isset($klasSchoolMap[$klasId]) && !in_array($klasSchoolMap[$klasId], $schoolIds, true)) {
            $foutmeldingen[] = 'Geselecteerde klassen moeten bij de gekozen scholen horen.';
            break;
        }
    }
}

// 4) Valideer voorkeuren/sectoren
$voorkeurNamenRuw = $_POST['voorkeur_naam'] ?? [];
$voorkeurMaxRuw = $_POST['voorkeur_max'] ?? [];
$voorkeurDagdeelRuw = $_POST['voorkeur_dag_deel'] ?? [];
$voorkeurMaxDag1Ruw = $_POST['voorkeur_max_dag1'] ?? [];
$voorkeurMaxDag2Ruw = $_POST['voorkeur_max_dag2'] ?? [];

$bezoek_optie_has_split_limits = false;
$dag1ColCheck = $conn->query("SHOW COLUMNS FROM bezoek_optie LIKE 'max_leerlingen_dag1'");
$dag2ColCheck = $conn->query("SHOW COLUMNS FROM bezoek_optie LIKE 'max_leerlingen_dag2'");
// Controleer of PO-daglimieten in database aanwezig zijn
if ($dag1ColCheck && $dag2ColCheck && $dag1ColCheck->num_rows > 0 && $dag2ColCheck->num_rows > 0) {
    $bezoek_optie_has_split_limits = true;
}

$voorkeuren = [];
if (is_array($voorkeurNamenRuw)) {
    foreach ($voorkeurNamenRuw as $i => $naamRuw) {
        $naam = substr(trim($naamRuw), 0, 255);
        $max_leerlingen = isset($voorkeurMaxRuw[$i]) ? max(1, (int)$voorkeurMaxRuw[$i]) : 1;
        $dag_deel = 'week';
        $max_leerlingen_dag1 = null;
        $max_leerlingen_dag2 = null;

        if ($onderwijsType === 'Primair Onderwijs') {
            $dag_deel = trim((string)($voorkeurDagdeelRuw[$i] ?? 'beide'));
            if (!in_array($dag_deel, ['dag1', 'dag2', 'beide'], true)) {
                $foutmeldingen[] = 'Kies per wereld een geldige PO-daginstelling.';
                $dag_deel = 'beide';
            }

            if ($dag_deel === 'beide') {
                $rawDag1 = trim((string)($voorkeurMaxDag1Ruw[$i] ?? ''));
                $rawDag2 = trim((string)($voorkeurMaxDag2Ruw[$i] ?? ''));

                $max_leerlingen_dag1 = ($rawDag1 === '') ? $max_leerlingen : max(1, (int)$rawDag1);
                $max_leerlingen_dag2 = ($rawDag2 === '') ? $max_leerlingen : max(1, (int)$rawDag2);

                if (!$bezoek_optie_has_split_limits && $max_leerlingen_dag1 !== $max_leerlingen_dag2) {
                    $foutmeldingen[] = 'Verschillende limieten voor dag 1 en dag 2 vereisen een database-update (kolommen max_leerlingen_dag1/max_leerlingen_dag2).';
                }

                if (!$bezoek_optie_has_split_limits) {
                    $max_leerlingen = max($max_leerlingen_dag1, $max_leerlingen_dag2);
                } else {
                    $max_leerlingen = null;
                }
            } elseif ($dag_deel === 'dag1') {
                if ($bezoek_optie_has_split_limits) {
                    $max_leerlingen_dag1 = $max_leerlingen;
                    $max_leerlingen_dag2 = null;
                    $max_leerlingen = null;
                }
            } elseif ($dag_deel === 'dag2') {
                if ($bezoek_optie_has_split_limits) {
                    $max_leerlingen_dag1 = null;
                    $max_leerlingen_dag2 = $max_leerlingen;
                    $max_leerlingen = null;
                }
            }
        }

        if ($naam !== '') {
            $voorkeuren[] = [
                'naam' => $naam,
                'max_leerlingen' => $max_leerlingen,
                'dag_deel' => $dag_deel,
                'max_leerlingen_dag1' => $max_leerlingen_dag1,
                'max_leerlingen_dag2' => $max_leerlingen_dag2,
            ];
        }
    }
}

if (count($voorkeuren) < 3) {
    $foutmeldingen[] = 'Voeg minimaal 3 voorkeuren toe.';
}

// 5) Controleer school-dekking (per school min 1 klas)
if (!empty($schoolIds) && !empty($klasIds)) {
    $inClause = implode(',', $schoolIds);
    $klasInClause = implode(',', $klasIds);
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

    $missing = array_diff($schoolIds, $covered_schools);
    if (!empty($missing)) {
        $foutmeldingen[] = 'Selecteer minimaal 1 klas voor iedere gekozen school.';
    }
}

// Bij fouten: terug met foutmeldingen in sessie
if (!empty($foutmeldingen)) {
    $_SESSION['bezoeken_errors'] = $foutmeldingen;
    $_SESSION['bezoeken_post'] = $_POST;
    if ($is_bewerken && $te_bewerken_bezoek_id > 0) {
        header('Location: bezoeken.php?edit=' . $te_bewerken_bezoek_id);
    } else {
        header('Location: bezoeken.php');
    }
    exit;
}

// 6) Sla alles op in database (transactioneel)
$bezoek_id = 0;
$conn->begin_transaction();
try {
    // Bepaal databasecode voor onderwijstype
    $onderwijsTypeCode = '';
    if ($onderwijsType === 'Primair Onderwijs') {
        $onderwijsTypeCode = 'PO';
    } elseif ($onderwijsType === 'Voortgezet Onderwijs') {
        $onderwijsTypeCode = 'VO';
    } elseif ($onderwijsType === 'MBO') {
        $onderwijsTypeCode = 'MBO';
    }

    if ($is_bewerken) {
        $bezoek_id = $te_bewerken_bezoek_id;

        // Bij update: NULL datumvelden die niet relevant zijn
        $poDag1Db = ($onderwijsTypeCode === 'PO') ? $bezoekDag1 : null;
        $poDag2Db = ($onderwijsTypeCode === 'PO') ? $bezoekDag2 : null;
        $voWeekStartDb = ($onderwijsTypeCode === 'PO') ? null : $bezoekWeekStart;
        $voWeekEindDb = ($onderwijsTypeCode === 'PO') ? null : $bezoekWeekEind;

        $stmt = $conn->prepare('
            UPDATE bezoek
            SET naam=?, type_onderwijs=?, schooljaar=?, pincode=?, max_keuzes=?,
                po_dag1=?, po_dag2=?, vo_week_start=?, vo_week_eind=?
            WHERE bezoek_id=?
        ');
        $stmt->bind_param(
            'ssssissssi',
            $bezoekNaam,
            $onderwijsTypeCode,
            $bezoekSchooljaar,
            $bezoekPincode,
            $bezoekMaxKeuzes,
            $poDag1Db,
            $poDag2Db,
            $voWeekStartDb,
            $voWeekEindDb,
            $te_bewerken_bezoek_id
        );
        $stmt->execute();
        $stmt->close();

        // Verwijder oude koppelingen voor vervanging
        foreach ([
            'DELETE FROM bezoek_school WHERE bezoek_id=?',
            'DELETE FROM bezoek_klas WHERE bezoek_id=?',
            'DELETE FROM bezoek_optie WHERE bezoek_id=?',
        ] as $deleteSql) {
            $stmtDelete = $conn->prepare($deleteSql);
            $stmtDelete->bind_param('i', $te_bewerken_bezoek_id);
            $stmtDelete->execute();
            $stmtDelete->close();
        }
    } else {
        // Insert nieuw bezoek met juiste datumtype
        if ($onderwijsTypeCode === 'PO') {
            $stmt = $conn->prepare('
                INSERT INTO bezoek (naam, type_onderwijs, schooljaar, pincode, max_keuzes, po_dag1, po_dag2, actief)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ');
            $stmt->bind_param(
                'sssisss',
                $bezoekNaam,
                $onderwijsTypeCode,
                $bezoekSchooljaar,
                $bezoekPincode,
                $bezoekMaxKeuzes,
                $bezoekDag1,
                $bezoekDag2
            );
        } else {
            $stmt = $conn->prepare('
                INSERT INTO bezoek (naam, type_onderwijs, schooljaar, pincode, max_keuzes, vo_week_start, vo_week_eind, actief)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ');
            $stmt->bind_param(
                'sssisss',
                $bezoekNaam,
                $onderwijsTypeCode,
                $bezoekSchooljaar,
                $bezoekPincode,
                $bezoekMaxKeuzes,
                $bezoekWeekStart,
                $bezoekWeekEind
            );
        }

        $stmt->execute();
        $bezoek_id = $conn->insert_id;
        $stmt->close();
    }

    // Voeg scholen toe aan bezoek
    $stmt_school = $conn->prepare('INSERT INTO bezoek_school (bezoek_id, school_id) VALUES (?, ?)');
    foreach ($schoolIds as $schoolId) {
        $stmt_school->bind_param('ii', $bezoek_id, $schoolId);
        $stmt_school->execute();
    }
    $stmt_school->close();

    // Voeg klassen toe aan bezoek
    $stmt_klas = $conn->prepare('INSERT INTO bezoek_klas (bezoek_id, klas_id) VALUES (?, ?)');
    foreach ($klasIds as $klasId) {
        $stmt_klas->bind_param('ii', $bezoek_id, $klasId);
        $stmt_klas->execute();
    }
    $stmt_klas->close();

    // Sla alle voorkeuropties op
    if ($bezoek_optie_has_split_limits) {
        $stmt_optie = $conn->prepare('
            INSERT INTO bezoek_optie (bezoek_id, volgorde, naam, max_leerlingen, dag_deel, max_leerlingen_dag1, max_leerlingen_dag2, actief)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ');
    } else {
        $stmt_optie = $conn->prepare('
            INSERT INTO bezoek_optie (bezoek_id, volgorde, naam, max_leerlingen, dag_deel, actief)
            VALUES (?, ?, ?, ?, ?, 1)
        ');
    }
    foreach ($voorkeuren as $volgordeIndex => $voorkeur) {
        $volgorde = $volgordeIndex + 1;
        $naam = $voorkeur['naam'];
        $max_leerlingen = $voorkeur['max_leerlingen'];
        $dag_deel = $voorkeur['dag_deel'];
        $max_leerlingen_dag1 = $voorkeur['max_leerlingen_dag1'];
        $max_leerlingen_dag2 = $voorkeur['max_leerlingen_dag2'];

        if ($bezoek_optie_has_split_limits) {
            $stmt_optie->bind_param('iisisii', $bezoek_id, $volgorde, $naam, $max_leerlingen, $dag_deel, $max_leerlingen_dag1, $max_leerlingen_dag2);
        } else {
            $stmt_optie->bind_param('iisis', $bezoek_id, $volgorde, $naam, $max_leerlingen, $dag_deel);
        }
        $stmt_optie->execute();
    }
    $stmt_optie->close();

    $conn->commit();

    $_SESSION['bezoeken_success'] = $is_bewerken ? 'Bezoek succesvol bijgewerkt!' : 'Bezoek succesvol toegevoegd!';
    header('Location: bezoeken.php?highlight=' . $bezoek_id);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log('Fout bij opslaan bezoek: ' . $e->getMessage());
    $_SESSION['bezoeken_errors'] = ['Er is iets misgegaan bij het opslaan van het bezoek.'];
    $_SESSION['bezoeken_post'] = $_POST;
    if ($is_bewerken && $bezoek_id > 0) {
        header('Location: bezoeken.php?edit=' . $bezoek_id);
    } else {
        header('Location: bezoeken.php');
    }
    exit;
}
