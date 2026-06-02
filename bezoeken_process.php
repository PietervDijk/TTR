<?php
// Verwerk het formulier uit bezoek_formulier.php:
// - Valideer invoer van het formulier
// - Sla gegevens transactioneel op in meerdere tabellen
// Bij debug (sessie-flag) tonen we welke queries zijn uitgevoerd, met
// parameters en hoeveel rijen zijn aangepast of toegevoegd.
require_once 'includes/functions.php';
require 'includes/config.php';

// Forceer MySQLi om fouten als Exceptions te gooien (handig tijdens debugging)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Start sessie indien nodig. Sessie bevat o.a. admin-gegevens en debug-flag.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug-instellingen: lees debug flags uit de sessie
// Hier verzamelen we later de uitgevoerde queries en metadata
$debug_ingeschakeld = !empty($_SESSION['__debug_mode']);
// Hier verzamelen we later de uitgevoerde queries en metadata
$debug_log = [];

// ------------- Authenticatie / Autorisatie -------------
// Alleen ingelogde admins mogen bezoeken aanmaken of bewerken.
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Beveiliging: controleer CSRF-token
csrf_validate();

// Dit script moet alleen via POST aangeroepen worden (formulierinzending)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bezoek_formulier.php');
    exit;
}

// Foutmeldingen verzamelen we in deze array. Als deze niet leeg is,
// sturen we de gebruiker terug naar het formulier met de fouten.
$foutmeldingen = [];
// Bepaal of we een update (bewerken) of insert (nieuw bezoek) uitvoeren
$is_bewerken = (($_POST['action'] ?? '') === 'update');
// Bij bewerken: welk bezoek-id wordt aangepast?
$te_bewerken_bezoek_id = (int)($_POST['bezoek_id'] ?? 0);

if ($is_bewerken && $te_bewerken_bezoek_id <= 0) {
    $foutmeldingen[] = 'Ongeldig bezoek om te bewerken.';
}

if ($is_bewerken && $te_bewerken_bezoek_id > 0) {
    $queryTekst = 'SELECT bezoek_id FROM bezoek WHERE bezoek_id = ? LIMIT 1';
    $statementBestaan = $conn->prepare($queryTekst);
    $statementBestaan->bind_param('i', $te_bewerken_bezoek_id);
    if ($debug_ingeschakeld) {
        $debug_log[] = ['sql' => $queryTekst, 'params' => [$te_bewerken_bezoek_id]];
    }
    $statementBestaan->execute();
    if ($debug_ingeschakeld) {
        $i = count($debug_log) - 1;
        if ($i >= 0) {
            $debug_log[$i]['affected_rows'] = $statementBestaan->affected_rows;
            if (!empty($conn->insert_id)) { $debug_log[$i]['insert_id'] = $conn->insert_id; }
        }
    }
    $bestaat = $statementBestaan->get_result()->fetch_assoc();
    $statementBestaan->close();

    if (!$bestaat) {
        $foutmeldingen[] = 'Het bezoek dat je wilt bewerken bestaat niet meer.';
    }
}

// --------------------
// 1) Valideer basisvelden voor bezoek
// --------------------
$bezoekNaam = substr(trim($_POST['bezoek_naam'] ?? ''), 0, 255);
$onderwijsType = trim($_POST['onderwijs_type'] ?? '');
// BELANGRIJK: Pincode ALTIJD als string behandelen om leading zeros (bijv. 01234) te behouden
$bezoekPincode = (string)trim($_POST['bezoek_pincode'] ?? '');
$bezoekSchooljaar = preg_replace('/\s+/', ' ', trim($_POST['bezoek_schooljaar'] ?? ''));

if (!is_geldig_schooljaar($bezoekSchooljaar, 2, 3)) {
    $foutmeldingen[] = 'Selecteer een geldig schooljaar.';
}

if (!$bezoekPincode) {
    $foutmeldingen[] = 'Vul een pincode in.';
} else {
    // Controleer unieke pincode (bij update: huidige record uitsluiten)
    if ($is_bewerken) {
        $queryTekst = 'SELECT COUNT(*) as cnt FROM bezoek WHERE pincode = ? AND bezoek_id <> ?';
        $statementControle = $conn->prepare($queryTekst);
        $statementControle->bind_param('si', $bezoekPincode, $te_bewerken_bezoek_id);
        if ($debug_ingeschakeld) {
            $debug_log[] = ['sql' => $queryTekst, 'params' => [$bezoekPincode, $te_bewerken_bezoek_id]];
        }
    } else {
        $queryTekst = 'SELECT COUNT(*) as cnt FROM bezoek WHERE pincode = ?';
        $statementControle = $conn->prepare($queryTekst);
        $statementControle->bind_param('s', $bezoekPincode);
        if ($debug_ingeschakeld) {
            $debug_log[] = ['sql' => $queryTekst, 'params' => [$bezoekPincode]];
        }
    }

    $statementControle->execute();
    if ($debug_ingeschakeld) {
        $i = count($debug_log) - 1;
        if ($i >= 0) {
            $debug_log[$i]['affected_rows'] = $statementControle->affected_rows;
            if (!empty($conn->insert_id)) { $debug_log[$i]['insert_id'] = $conn->insert_id; }
        }
    }
    $rijControle = $statementControle->get_result()->fetch_assoc();
    $statementControle->close();

    if ((int)$rijControle['cnt'] > 0) {
        $foutmeldingen[] = 'Deze pincode is al in gebruik door een ander bezoek.';
    }
}

$bezoekMaxKeuzesRuw = $_POST['bezoek_max_keuzes'] ?? null;

$bezoekDag1 = trim($_POST['bezoek_dag1'] ?? '');
$bezoekDag2 = trim($_POST['bezoek_dag2'] ?? '');
$bezoekWeekStart = trim($_POST['bezoek_week_start'] ?? '');
$bezoekWeekEind = trim($_POST['bezoek_week_eind'] ?? '');
$bezoekMaxKeuzes = 2;

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

// --------------------
// 2) Valideer geselecteerde scholen
// - Maak een veilige lijst van school-id's
// - Controleer of deze id's daadwerkelijk in de database bestaan
// --------------------
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
    $queryTekst = "SELECT school_id FROM school WHERE school_id IN ($schoolInClause)";
    $schoolControle = $conn->query($queryTekst);
    if ($debug_ingeschakeld) {
        $debug_log[] = ['sql' => $queryTekst, 'params' => []];
        $i = count($debug_log) - 1;
        $debug_log[$i]['num_rows'] = $schoolControle ? $schoolControle->num_rows : null;
    }
    $geldigeSchoolIds = [];
    while ($schoolRij = $schoolControle->fetch_assoc()) {
        $geldigeSchoolIds[] = (int)$schoolRij['school_id'];
    }

    if (count($geldigeSchoolIds) !== count($schoolIds)) {
        $foutmeldingen[] = 'Er zijn ongeldige scholen geselecteerd.';
    }
}

// --------------------
// 3) Valideer geselecteerde klassen
// - Zelfde principe als bij scholen: maak unieke id-lijst en controleer
//   of de klassen bestaan en bij de gekozen scholen horen.
// --------------------
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
    $queryTekst = "SELECT klas_id, school_id FROM klas WHERE klas_id IN ($klasInClause)";
    $klasControle = $conn->query($queryTekst);
    if ($debug_ingeschakeld) {
        $debug_log[] = ['sql' => $queryTekst, 'params' => []];
        $i = count($debug_log) - 1;
        $debug_log[$i]['num_rows'] = $klasControle ? $klasControle->num_rows : null;
    }
    $gevondenKlasIds = [];
    $klasSchoolMap = [];

    while ($klasRij = $klasControle->fetch_assoc()) {
        $gevondenKlasIds[] = (int)$klasRij['klas_id'];
        $klasSchoolMap[(int)$klasRij['klas_id']] = (int)$klasRij['school_id'];
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

// --------------------
// 4) Valideer voorkeuren/sectoren
// - Verwerkt de ingevulde voorkeuren (naam, limieten, dag-deel)
// - Controleert of database kolommen bestaan voor PO-splits
// --------------------
$voorkeurNamenRuw = $_POST['voorkeur_naam'] ?? [];
$voorkeurMaxRuw = $_POST['voorkeur_max'] ?? [];
$voorkeurDagdeelRuw = $_POST['voorkeur_dag_deel'] ?? [];
$voorkeurMaxDag1Ruw = $_POST['voorkeur_max_dag1'] ?? [];
$voorkeurMaxDag2Ruw = $_POST['voorkeur_max_dag2'] ?? [];

$bezoek_optie_heeft_split_limieten = false;
$queryTekst = "SHOW COLUMNS FROM bezoek_optie LIKE 'max_leerlingen_dag1'";
$dag1KolomControle = $conn->query($queryTekst);
$i = null; if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => []]; $i = count($debug_log)-1; $debug_log[$i]['num_rows'] = $dag1KolomControle ? $dag1KolomControle->num_rows : null; }
$queryTekst = "SHOW COLUMNS FROM bezoek_optie LIKE 'max_leerlingen_dag2'";
$dag2KolomControle = $conn->query($queryTekst);
if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => []]; $i = count($debug_log)-1; $debug_log[$i]['num_rows'] = $dag2KolomControle ? $dag2KolomControle->num_rows : null; }
// Controleer of PO-daglimieten in database aanwezig zijn
if ($dag1KolomControle && $dag2KolomControle && $dag1KolomControle->num_rows > 0 && $dag2KolomControle->num_rows > 0) {
    $bezoek_optie_heeft_split_limieten = true;
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

                if (!$bezoek_optie_heeft_split_limieten && $max_leerlingen_dag1 !== $max_leerlingen_dag2) {
                    $foutmeldingen[] = 'Verschillende limieten voor dag 1 en dag 2 vereisen een database-update (kolommen max_leerlingen_dag1/max_leerlingen_dag2).';
                }

                if (!$bezoek_optie_heeft_split_limieten) {
                    $max_leerlingen = max($max_leerlingen_dag1, $max_leerlingen_dag2);
                } else {
                    $max_leerlingen = max($max_leerlingen_dag1, $max_leerlingen_dag2);
                }
            } elseif ($dag_deel === 'dag1') {
                if ($bezoek_optie_heeft_split_limieten) {
                    $max_leerlingen_dag1 = $max_leerlingen;
                    $max_leerlingen_dag2 = null;
                }
            } elseif ($dag_deel === 'dag2') {
                if ($bezoek_optie_heeft_split_limieten) {
                    $max_leerlingen_dag1 = null;
                    $max_leerlingen_dag2 = $max_leerlingen;
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

// --------------------
// 5) Controleer school-dekking (per school min 1 klas)
// - Zorg dat voor elke gekozen school minstens één klas geselecteerd is
// --------------------
if (!empty($schoolIds) && !empty($klasIds)) {
    $inClause = implode(',', $schoolIds);
    $klasInClause = implode(',', $klasIds);
    $queryTekst = "
        SELECT DISTINCT k.school_id FROM klas k
        WHERE k.klas_id IN ($klasInClause) AND k.school_id IN ($inClause)
    ";
    if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => []]; }
    $resultaat = $conn->query($queryTekst);
    if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['num_rows'] = $resultaat ? $resultaat->num_rows : null; } }
    $gedekteScholen = [];
    if ($resultaat) {
        while ($rij = $resultaat->fetch_assoc()) {
            $gedekteScholen[] = (int)$rij['school_id'];
        }
    }

    $ontbrekende_scholen = array_diff($schoolIds, $gedekteScholen);
    if (!empty($ontbrekende_scholen)) {
        $foutmeldingen[] = 'Selecteer minimaal 1 klas voor iedere gekozen school.';
    }
}

// Als validatie faalt: zet foutmeldingen in de sessie en stuur gebruiker
// terug naar het formulier (met oude POST-waarden). Hierdoor kan de
// gebruiker correcties maken zonder alles opnieuw in te vullen.
// Bij succes gaan we verder naar de transactionele save.
// Bij fouten: terug met foutmeldingen in sessie
if (!empty($foutmeldingen)) {
    $_SESSION['bezoeken_errors'] = $foutmeldingen;
    $_SESSION['bezoeken_post'] = $_POST;
    if ($is_bewerken && $te_bewerken_bezoek_id > 0) {
        header('Location: bezoek_formulier.php?edit=' . $te_bewerken_bezoek_id);
    } else {
        header('Location: bezoek_formulier.php');
    }
    exit;
}

// --------------------
// 6) Sla alles op in database (transactioneel)
// We openen een transaction zodat alle inserts/updates als één operatie
// worden uitgevoerd. Bij een fout rollen we terug (rollback).
$bezoek_id = 0;
$conn->begin_transaction();
try {
    $bestaandeOptieIds = [];

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

        // Werk het bestaande `bezoek` record bij met de nieuwe waarden.
        // We gebruiken een prepared statement om SQL-injectie te voorkomen.
        $queryTekst = '
            UPDATE bezoek
            SET naam=?, type_onderwijs=?, schooljaar=?, pincode=?, max_keuzes=?,
                po_dag1=?, po_dag2=?, vo_week_start=?, vo_week_eind=?
            WHERE bezoek_id=?
        ';
        $statementBezoek = $conn->prepare($queryTekst);
        $statementBezoek->bind_param(
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
        if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$bezoekNaam, $onderwijsTypeCode, $bezoekSchooljaar, $bezoekPincode, $bezoekMaxKeuzes, $poDag1Db, $poDag2Db, $voWeekStartDb, $voWeekEindDb, $te_bewerken_bezoek_id]]; }
        $statementBezoek->execute();
        if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['affected_rows'] = $statementBezoek->affected_rows; if (!empty($conn->insert_id)) { $debug_log[$i]['insert_id'] = $conn->insert_id; } } }
        $statementBezoek->close();

        // Haal bestaande actieve opties op zodat we bestaande optie_id's kunnen hergebruiken
        $queryTekst = 'SELECT optie_id FROM bezoek_optie WHERE bezoek_id=? AND actief=1 ORDER BY volgorde ASC, optie_id ASC';
        $statementBestaandeOpties = $conn->prepare($queryTekst);
        $statementBestaandeOpties->bind_param('i', $te_bewerken_bezoek_id);
        if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$te_bewerken_bezoek_id]]; }
        $statementBestaandeOpties->execute();
        if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['affected_rows'] = $statementBestaandeOpties->affected_rows; if (!empty($conn->insert_id)) { $debug_log[$i]['insert_id'] = $conn->insert_id; } } }
        $resultaatBestaandeOpties = $statementBestaandeOpties->get_result();
        while ($rijOptie = $resultaatBestaandeOpties->fetch_assoc()) {
            $bestaandeOptieIds[] = (int)$rijOptie['optie_id'];
        }
        $statementBestaandeOpties->close();
        // Verwijder bestaande school/klas-koppelingen zodat we niet opnieuw dubbele
        // (bezoek_id, school_id) of (bezoek_id, klas_id) proberen in te voegen.
        $queryTekst = 'DELETE FROM bezoek_school WHERE bezoek_id=?';
        $statementVerwijder = $conn->prepare($queryTekst);
        $statementVerwijder->bind_param('i', $te_bewerken_bezoek_id);
        if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$te_bewerken_bezoek_id]]; }
        $statementVerwijder->execute();
        if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['affected_rows'] = $statementVerwijder->affected_rows; } }
        $statementVerwijder->close();

        $queryTekst = 'DELETE FROM bezoek_klas WHERE bezoek_id=?';
        $statementVerwijder = $conn->prepare($queryTekst);
        $statementVerwijder->bind_param('i', $te_bewerken_bezoek_id);
        if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$te_bewerken_bezoek_id]]; }
        $statementVerwijder->execute();
        if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['affected_rows'] = $statementVerwijder->affected_rows; } }
        $statementVerwijder->close();
    } else {
        // Nieuwe bezoek-insert: afhankelijk van `type_onderwijs` vullen we
        // ofwel PO-datums (po_dag1/po_dag2) of VO/MBO weekvelden in.
        // Ook hier gebruiken we prepared statements.
        // Insert nieuw bezoek met juiste datumtype
        if ($onderwijsTypeCode === 'PO') {
            $queryTekst = '
                INSERT INTO bezoek (naam, type_onderwijs, schooljaar, pincode, max_keuzes, po_dag1, po_dag2, actief)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ';
            $statementBezoek = $conn->prepare($queryTekst);
            $statementBezoek->bind_param(
                'ssssiss',
                $bezoekNaam,
                $onderwijsTypeCode,
                $bezoekSchooljaar,
                $bezoekPincode,
                $bezoekMaxKeuzes,
                $bezoekDag1,
                $bezoekDag2
            );
                if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$bezoekNaam, $onderwijsTypeCode, $bezoekSchooljaar, $bezoekPincode, $bezoekMaxKeuzes, $bezoekDag1, $bezoekDag2]]; }
        } else {
            $queryTekst = '
                INSERT INTO bezoek (naam, type_onderwijs, schooljaar, pincode, max_keuzes, vo_week_start, vo_week_eind, actief)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ';
            $statementBezoek = $conn->prepare($queryTekst);
            $statementBezoek->bind_param(
                'ssssiss',
                $bezoekNaam,
                $onderwijsTypeCode,
                $bezoekSchooljaar,
                $bezoekPincode,
                $bezoekMaxKeuzes,
                $bezoekWeekStart,
                $bezoekWeekEind
            );
            if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$bezoekNaam, $onderwijsTypeCode, $bezoekSchooljaar, $bezoekPincode, $bezoekMaxKeuzes, $bezoekWeekStart, $bezoekWeekEind]]; }
        }

        $statementBezoek->execute();
        $bezoek_id = $conn->insert_id;
        if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['affected_rows'] = $statementBezoek->affected_rows; if (!empty($bezoek_id)) { $debug_log[$i]['insert_id'] = $bezoek_id; } } }
        $statementBezoek->close();
    }

    // Voeg scholen toe aan bezoek (bezoek_school): voor elke geselecteerde
    // school wordt een rij toegevoegd met het bezoek_id en school_id.
    // We gebruiken een prepared statement en voeren het binnen dezelfde
    // database-transactie uit.
    // Voeg scholen toe aan bezoek
    $queryTekst = 'INSERT INTO bezoek_school (bezoek_id, school_id) VALUES (?, ?)';
    $statementSchool = $conn->prepare($queryTekst);
    foreach ($schoolIds as $schoolId) {
        $statementSchool->bind_param('ii', $bezoek_id, $schoolId);
        if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$bezoek_id, $schoolId]]; }
        $statementSchool->execute();
        if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['affected_rows'] = $statementSchool->affected_rows; } }
    }
    $statementSchool->close();

    // Voeg klassen toe aan bezoek (bezoek_klas): vergelijkbaar met scholen.
    // Elke geselecteerde klas krijgt een record gekoppeld aan dit bezoek.
    // Voeg klassen toe aan bezoek
    $queryTekst = 'INSERT INTO bezoek_klas (bezoek_id, klas_id) VALUES (?, ?)';
    $statementKlas = $conn->prepare($queryTekst);
    foreach ($klasIds as $klasId) {
        $statementKlas->bind_param('ii', $bezoek_id, $klasId);
        if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$bezoek_id, $klasId]]; }
        $statementKlas->execute();
        if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['affected_rows'] = $statementKlas->affected_rows; } }
    }
    $statementKlas->close();

    // Sla voorkeuropties op zonder bestaande optie-id's te verliezen.
    // We behandelen twee gevallen:
    // - bewerken: probeer bestaande optie_id's te updaten, voeg nieuwe opties in
    // - nieuw: voeg alle opties opnieuw toe
    // Er is ook support voor PO-splits (verschil dag1/dag2) als die kolommen
    // in de database aanwezig zijn.
    if ($is_bewerken) {
        if ($bezoek_optie_heeft_split_limieten) {
            $queryTekst = '
                UPDATE bezoek_optie
                SET volgorde=?, naam=?, max_leerlingen=?, dag_deel=?, max_leerlingen_dag1=?, max_leerlingen_dag2=?, actief=1
                WHERE optie_id=?
            ';
            $statementOptieBijwerken = $conn->prepare($queryTekst);
            $queryInvoegen = '
                INSERT INTO bezoek_optie (bezoek_id, volgorde, naam, max_leerlingen, dag_deel, max_leerlingen_dag1, max_leerlingen_dag2, actief)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ';
            $statementOptieInvoegen = $conn->prepare($queryInvoegen);
        } else {
            $queryTekst = '
                UPDATE bezoek_optie
                SET volgorde=?, naam=?, max_leerlingen=?, dag_deel=?, actief=1
                WHERE optie_id=?
            ';
            $statementOptieBijwerken = $conn->prepare($queryTekst);
            $queryInvoegen = '
                INSERT INTO bezoek_optie (bezoek_id, volgorde, naam, max_leerlingen, dag_deel, actief)
                VALUES (?, ?, ?, ?, ?, 1)
            ';
            $statementOptieInvoegen = $conn->prepare($queryInvoegen);
        }
    } else {
        if ($bezoek_optie_heeft_split_limieten) {
            $queryInvoegen = '
                INSERT INTO bezoek_optie (bezoek_id, volgorde, naam, max_leerlingen, dag_deel, max_leerlingen_dag1, max_leerlingen_dag2, actief)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ';
            $statementOptieInvoegen = $conn->prepare($queryInvoegen);
        } else {
            $queryInvoegen = '
                INSERT INTO bezoek_optie (bezoek_id, volgorde, naam, max_leerlingen, dag_deel, actief)
                VALUES (?, ?, ?, ?, ?, 1)
            ';
            $statementOptieInvoegen = $conn->prepare($queryInvoegen);
        }
    }

    foreach ($voorkeuren as $volgordeIndex => $voorkeur) {
        $volgorde = $volgordeIndex + 1;
        $naam = $voorkeur['naam'];
        $max_leerlingen = $voorkeur['max_leerlingen'];
        $dag_deel = $voorkeur['dag_deel'];
        $max_leerlingen_dag1 = $voorkeur['max_leerlingen_dag1'];
        $max_leerlingen_dag2 = $voorkeur['max_leerlingen_dag2'];

        if ($is_bewerken && isset($bestaandeOptieIds[$volgordeIndex])) {
            $optie_id = $bestaandeOptieIds[$volgordeIndex];

            if ($bezoek_optie_heeft_split_limieten) {
                $statementOptieBijwerken->bind_param('isisiii', $volgorde, $naam, $max_leerlingen, $dag_deel, $max_leerlingen_dag1, $max_leerlingen_dag2, $optie_id);
                    if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$volgorde, $naam, $max_leerlingen, $dag_deel, $max_leerlingen_dag1, $max_leerlingen_dag2, $optie_id]]; }
            } else {
                $statementOptieBijwerken->bind_param('isisi', $volgorde, $naam, $max_leerlingen, $dag_deel, $optie_id);
                    if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$volgorde, $naam, $max_leerlingen, $dag_deel, $optie_id]]; }
            }
            $statementOptieBijwerken->execute();
                if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['affected_rows'] = $statementOptieBijwerken->affected_rows; } }
        } else {
            if ($bezoek_optie_heeft_split_limieten) {
                $statementOptieInvoegen->bind_param('iisisii', $bezoek_id, $volgorde, $naam, $max_leerlingen, $dag_deel, $max_leerlingen_dag1, $max_leerlingen_dag2);
                if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryInvoegen, 'params' => [$bezoek_id, $volgorde, $naam, $max_leerlingen, $dag_deel, $max_leerlingen_dag1, $max_leerlingen_dag2]]; }
            } else {
                $statementOptieInvoegen->bind_param('iisis', $bezoek_id, $volgorde, $naam, $max_leerlingen, $dag_deel);
                if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryInvoegen, 'params' => [$bezoek_id, $volgorde, $naam, $max_leerlingen, $dag_deel]]; }
            }
            $statementOptieInvoegen->execute();
            if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['affected_rows'] = $statementOptieInvoegen->affected_rows; if (!empty($conn->insert_id)) { $debug_log[$i]['insert_id'] = $conn->insert_id; } } }
        }
    }

    if ($is_bewerken && count($bestaandeOptieIds) > count($voorkeuren)) {
        $queryTekst = 'UPDATE bezoek_optie SET actief=0 WHERE optie_id=?';
        $statementOptieDeactiveren = $conn->prepare($queryTekst);
        for ($i = count($voorkeuren); $i < count($bestaandeOptieIds); $i++) {
            $optie_id = $bestaandeOptieIds[$i];
            $statementOptieDeactiveren->bind_param('i', $optie_id);
            if ($debug_ingeschakeld) { $debug_log[] = ['sql' => $queryTekst, 'params' => [$optie_id]]; }
            $statementOptieDeactiveren->execute();
            if ($debug_ingeschakeld) { $i = count($debug_log)-1; if ($i >= 0) { $debug_log[$i]['affected_rows'] = $statementOptieDeactiveren->affected_rows; } }
        }
        $statementOptieDeactiveren->close();
    }

    if (isset($statementOptieBijwerken)) {
        $statementOptieBijwerken->close();
    }
    $statementOptieInvoegen->close();

    // Alle wijzigingen definitief maken
    $conn->commit();

    // Zet een succesmelding in de sessie zodat het overzicht deze kan tonen
    $_SESSION['bezoeken_success'] = $is_bewerken ? 'Bezoek succesvol bijgewerkt!' : 'Bezoek succesvol toegevoegd!';
    csrf_regenerate();

    // Wanneer debug aan staat tonen we de verzamelde queries en wachten we
    // op de gebruiker om door te klikken. Dit maakt het veilig om de
    // uitvoer te inspecteren zonder direct verder te navigeren.
    if ($debug_ingeschakeld) {
        $doorgaan_url = 'bezoeken.php?highlight=' . $bezoek_id;
        echo '<h2>Debug: uitgevoerde queries</h2>';
        echo '<pre>' . htmlspecialchars(print_r($debug_log, true)) . '</pre>';
        echo '<p>Bezoek ID: ' . htmlspecialchars((string)$bezoek_id) . '</p>';
        echo '<p><a href="' . htmlspecialchars($doorgaan_url) . '">Doorgaan naar overzicht</a></p>';
        exit;
    }

    // Standaardgedrag: redirect naar het overzicht met highlight
    header('Location: bezoeken.php?highlight=' . $bezoek_id);
    exit;
} catch (Exception $e) {
    // Bij iedere fout: rol de transaction terug zodat de DB geen halve
    // wijzigingen bevat. Log de fout naar de server-log.
    $conn->rollback();
    error_log('Fout bij opslaan bezoek: ' . $e->getMessage());

    // Als debug aanstaat tonen we technische details op het scherm zodat
    // een ontwikkelaar direct kan zien welke queries en POST-data er waren.
    if ($debug_ingeschakeld) {
        // Bewaar POST data en foutmelding in sessie zodat gebruiker bij
        // terugkeer het formulier met de eerder ingevulde waarden ziet.
        $_SESSION['bezoeken_post'] = $_POST;
        $_SESSION['bezoeken_errors'] = ['Er is iets misgegaan bij het opslaan van het bezoek.'];
        $doorgaan_url = ($is_bewerken && $bezoek_id > 0) ? 'bezoek_formulier.php?edit=' . $bezoek_id : 'bezoek_formulier.php';

        echo '<h2>Debug: fout bij opslaan bezoek</h2>';
        echo '<p><strong>Exception:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<h3>Uitgevoerde queries</h3>';
        echo '<pre>' . htmlspecialchars(print_r($debug_log, true)) . '</pre>';
        echo '<h3>POST data</h3>';
        echo '<pre>' . htmlspecialchars(print_r($_POST, true)) . '</pre>';
        echo '<p><a href="' . htmlspecialchars($doorgaan_url) . '">Terug naar formulier</a></p>';
        exit;
    }

    // Zonder debug tonen we alleen een generieke foutmelding aan de gebruiker
    // en sturen we terug naar het formulier met de oude POST-waarden.
    $_SESSION['bezoeken_errors'] = ['Er is iets misgegaan bij het opslaan van het bezoek.'];
    $_SESSION['bezoeken_post'] = $_POST;
    if ($is_bewerken && $bezoek_id > 0) {
        header('Location: bezoek_formulier.php?edit=' . $bezoek_id);
    } else {
        header('Location: bezoek_formulier.php');
    }
    exit;
}
