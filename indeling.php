<?php
// Verdeling: leerlingen direct indelen via tabelvelden per bezoek
require 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if (!isset($_GET['bezoek_id']) || !ctype_digit((string)$_GET['bezoek_id'])) {
    header('Location: bezoeken.php');
    exit;
}
$bezoekId = (int)$_GET['bezoek_id'];

// Helper: lees een opgeslagen toewijzing terug.
// Voorbeeld: '12' = wereld 12, '12|dag1' = wereld 12 op dag 1.
function parse_toegewezen_voorkeur($opslagwaarde)
{
    $opslagwaarde = trim((string)$opslagwaarde);
    if ($opslagwaarde === '') {
        return [0, null];
    }

    if (strpos($opslagwaarde, '|') !== false) {
        [$wereldIdRuw, $dagVariantRuw] = explode('|', $opslagwaarde, 2);
        $wereldId = (int)$wereldIdRuw;
        $dagVariant = trim($dagVariantRuw);
        if (!in_array($dagVariant, ['week', 'dag1', 'dag2', 'beide'], true)) {
            $dagVariant = null;
        }
        return [$wereldId, $dagVariant];
    }

    if (ctype_digit($opslagwaarde)) {
        return [(int)$opslagwaarde, null];
    }

    return [0, null];
}

// Helper: maak de opslagtekst voor een toewijzing.
// De variant wordt alleen gebruikt voor PO-dagkolommen.
function maak_toegewezen_voorkeur($wereldId, $dagVariant = null)
{
    $wereldId = (int)$wereldId;
    $dagVariant = trim((string)$dagVariant);

    if ($wereldId <= 0) {
        return '';
    }

    if ($dagVariant !== '' && in_array($dagVariant, ['dag1', 'dag2'], true)) {
        return $wereldId . '|' . $dagVariant;
    }

    return (string)$wereldId;
}

// Legacy helper: kiest bij PO de minst volle variant van een wereld.
// Deze blijft staan zodat de oude logica nog herkenbaar is.
function kies_po_variant(array $wereld, array $aantallenPerDag)
{
    $maxDag1 = (int)($wereld['max_leerlingen_dag1'] ?? 0);
    $maxDag2 = (int)($wereld['max_leerlingen_dag2'] ?? 0);
    $aantalDag1 = (int)($aantallenPerDag['dag1'] ?? 0);
    $aantalDag2 = (int)($aantallenPerDag['dag2'] ?? 0);

    $beschikbaarDag1 = ($maxDag1 <= 0) || ($aantalDag1 < $maxDag1);
    $beschikbaarDag2 = ($maxDag2 <= 0) || ($aantalDag2 < $maxDag2);

    if ($beschikbaarDag1 && !$beschikbaarDag2) {
        return 'dag1';
    }
    if ($beschikbaarDag2 && !$beschikbaarDag1) {
        return 'dag2';
    }
    if (!$beschikbaarDag1 && !$beschikbaarDag2) {
        return null;
    }

    $ratioDag1 = $maxDag1 > 0 ? ($aantalDag1 / $maxDag1) : $aantalDag1;
    $ratioDag2 = $maxDag2 > 0 ? ($aantalDag2 / $maxDag2) : $aantalDag2;

    if ($ratioDag1 < $ratioDag2) {
        return 'dag1';
    }
    if ($ratioDag2 < $ratioDag1) {
        return 'dag2';
    }

    return 'dag1';
}

// Controleer of de database al dagkolommen heeft voor PO-toewijzingen.
// Zonder deze kolommen gebruiken we een fallback-pad.
$kolomCheckStmt = $conn->prepare(" 
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'leerling'
      AND COLUMN_NAME IN ('toegewezen_dag1', 'toegewezen_dag2')
");
$kolomCheckStmt->execute();
$gevondenKolommen = [];
$kolomCheckResult = $kolomCheckStmt->get_result();
while ($kolomRij = $kolomCheckResult->fetch_assoc()) {
    $gevondenKolommen[] = $kolomRij['COLUMN_NAME'];
}
$kolomCheckStmt->close();
$heeftDag1Kolom = in_array('toegewezen_dag1', $gevondenKolommen, true);
$heeftDag2Kolom = in_array('toegewezen_dag2', $gevondenKolommen, true);

// Alleen POST-verzoeken met een geldige actie mogen de verdeellogica starten.
$is_ajax_verzoek = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && in_array($_GET['action'], ['auto', 'save'], true)
);

if ($is_ajax_verzoek) {
    csrf_validate();
}

// AJAX endpoint: automatische verdeling uitvoeren
if ($is_ajax_verzoek && $_GET['action'] === 'auto') {
    header('Content-Type: application/json; charset=utf-8');

    // Bepaal eerst welk type bezoek dit is, omdat PO en VO/MBO anders werken.
    $bezoek_type_stmt = $conn->prepare('SELECT type_onderwijs FROM bezoek WHERE bezoek_id = ?');
    $bezoek_type_stmt->bind_param('i', $bezoekId);
    $bezoek_type_stmt->execute();
    $bezoekGegevens = $bezoek_type_stmt->get_result()->fetch_assoc();
    $bezoek_type_stmt->close();

    $is_po_bezoek = (($bezoekGegevens['type_onderwijs'] ?? '') === 'PO');

    // Lijst van alle actieve werelden/sectoren van dit bezoek.
    // Hier bewaren we naam, dagdeel en maximale aantallen.
    $wereld_stmt = $conn->prepare(" 
        SELECT bo.optie_id AS id, bo.naam, bo.dag_deel, bo.max_leerlingen_dag1, bo.max_leerlingen_dag2,
               COALESCE(
                   bo.max_leerlingen,
                   CASE bo.dag_deel
                       WHEN 'dag1' THEN bo.max_leerlingen_dag1
                       WHEN 'dag2' THEN bo.max_leerlingen_dag2
                       WHEN 'beide' THEN CASE
                           WHEN COALESCE(bo.max_leerlingen_dag1, 0) = 0 OR COALESCE(bo.max_leerlingen_dag2, 0) = 0 THEN 0
                           ELSE (COALESCE(bo.max_leerlingen_dag1, 0) + COALESCE(bo.max_leerlingen_dag2, 0))
                       END
                       ELSE 0
                   END,
                   0
               ) AS max_leerlingen
        FROM bezoek_optie bo
        WHERE bo.bezoek_id = ? AND bo.actief = 1
        ORDER BY bo.volgorde ASC
    ");
    $wereld_stmt->bind_param('i', $bezoekId);
    $wereld_stmt->execute();
    $wereld_result = $wereld_stmt->get_result();

    $wereldenPerId = [];
    while ($wereldRij = $wereld_result->fetch_assoc()) {
        $wereldId = (int)$wereldRij['id'];
        $wereldenPerId[$wereldId] = [
            'id' => $wereldId,
            'naam' => $wereldRij['naam'],
            'dag_deel' => (string)($wereldRij['dag_deel'] ?? 'week'),
            'max_leerlingen_dag1' => isset($wereldRij['max_leerlingen_dag1']) ? (int)$wereldRij['max_leerlingen_dag1'] : 0,
            'max_leerlingen_dag2' => isset($wereldRij['max_leerlingen_dag2']) ? (int)$wereldRij['max_leerlingen_dag2'] : 0,
            'max' => (int)$wereldRij['max_leerlingen'],
        ];
    }
    $wereld_stmt->close();

    // Alle leerlingen van het bezoek met hun drie voorkeuren.
    $leerling_stmt = $conn->prepare(" 
        SELECT l.leerling_id, l.voornaam, l.tussenvoegsel, l.achternaam,
               l.voorkeur1, l.voorkeur2, l.voorkeur3
        FROM leerling l
        INNER JOIN bezoek_klas bk ON bk.klas_id = l.klas_id
        WHERE bk.bezoek_id = ?
    ");
    $leerling_stmt->bind_param('i', $bezoekId);
    $leerling_stmt->execute();
    $leerlingResult = $leerling_stmt->get_result();

    $leerlingen_voor_automatisch = [];
    while ($leerlingRij = $leerlingResult->fetch_assoc()) {
        $leerlingen_voor_automatisch[(int)$leerlingRij['leerling_id']] = $leerlingRij;
    }
    $leerling_stmt->close();

    $leerling_ids = array_keys($leerlingen_voor_automatisch);
    shuffle($leerling_ids);

    // Tellers per wereld en per dag.
    // Deze bepalen hoe vol iets al zit en sturen de verdeling bij.
    $maxDag1PerWereld = [];
    $maxDag2PerWereld = [];
    foreach ($wereldenPerId as $wereldId => $wereldGegeven) {
        $maxDag1PerWereld[$wereldId] = ($wereldGegeven['dag_deel'] === 'dag2') ? 0 : ((int)$wereldGegeven['max_leerlingen_dag1'] > 0 ? (int)$wereldGegeven['max_leerlingen_dag1'] : (int)$wereldGegeven['max']);
        $maxDag2PerWereld[$wereldId] = ($wereldGegeven['dag_deel'] === 'dag1') ? 0 : ((int)$wereldGegeven['max_leerlingen_dag2'] > 0 ? (int)$wereldGegeven['max_leerlingen_dag2'] : (int)$wereldGegeven['max']);
    }

    $aantalDag1PerWereld = [];
    $aantalDag2PerWereld = [];
    foreach ($wereldenPerId as $wereldId => $_wereld) {
        $aantalDag1PerWereld[$wereldId] = 0;
        $aantalDag2PerWereld[$wereldId] = 0;
    }

    $toewijzingen_per_leerling = [];
    $niet_ingedeeld_aantal = 0;

    // Controleer of een wereld op een bepaalde dag nog gebruikt mag worden.
    $mag_op_dag = function (int $wereld_id, string $dag) use (&$wereldenPerId, &$maxDag1PerWereld, &$maxDag2PerWereld, &$aantalDag1PerWereld, &$aantalDag2PerWereld): bool {
        if (!isset($wereldenPerId[$wereld_id])) {
            return false;
        }

        $wereld = $wereldenPerId[$wereld_id];
        $dag_deel = (string)($wereld['dag_deel'] ?? 'week');

        if ($dag === 'dag1' && $dag_deel === 'dag2') return false;
        if ($dag === 'dag2' && $dag_deel === 'dag1') return false;

        $capaciteit = ($dag === 'dag1')
            ? ($maxDag1PerWereld[$wereld_id] ?? 0)
            : ($maxDag2PerWereld[$wereld_id] ?? 0);

        $aantal = ($dag === 'dag1')
            ? ($aantalDag1PerWereld[$wereld_id] ?? 0)
            : ($aantalDag2PerWereld[$wereld_id] ?? 0);

        return ($capaciteit <= 0) || ($aantal < $capaciteit);
    };

    // Hoe voller een wereld is, hoe hoger de score.
    // De auto-verdeling kiest liever een minder volle wereld.
    $wereld_waarde = function (int $wereld_id, string $dag) use (&$maxDag1PerWereld, &$maxDag2PerWereld, &$aantalDag1PerWereld, &$aantalDag2PerWereld): float {
        $capaciteit = ($dag === 'dag1')
            ? ($maxDag1PerWereld[$wereld_id] ?? 0)
            : ($maxDag2PerWereld[$wereld_id] ?? 0);

        $aantal = ($dag === 'dag1')
            ? ($aantalDag1PerWereld[$wereld_id] ?? 0)
            : ($aantalDag2PerWereld[$wereld_id] ?? 0);

        return ($capaciteit > 0)
            ? ($aantal / $capaciteit)
            : (float)$aantal;
    };

    // Zoek de beste passende wereld uit een lijst kandidaten.
    $zoek_beste_wereld = function (array $kandidaten, string $dag, ?int $uitgesloten_wereld_id = null) use (&$wereldenPerId, $mag_op_dag, $wereld_waarde): ?int {
        $beste_wereld_id = null;
        $beste_score = null;

        foreach ($kandidaten as $wereld_id) {
            $wereld_id = (int)$wereld_id;

            if (
                $wereld_id <= 0 ||
                $wereld_id === $uitgesloten_wereld_id ||
                !isset($wereldenPerId[$wereld_id])
            ) {
                continue;
            }

            if (!$mag_op_dag($wereld_id, $dag)) {
                continue;
            }

            $score = $wereld_waarde($wereld_id, $dag);

            if ($beste_score === null || $score < $beste_score) {
                $beste_score = $score;
                $beste_wereld_id = $wereld_id;
            }
        }

        return $beste_wereld_id;
    };

    if ($is_po_bezoek) {
        // PO: elke leerling krijgt twee verschillende werelden.
        // Eerst proberen we een voorkeur vast te leggen, daarna vullen we de tweede dag.
        foreach ($leerling_ids as $leerlingId) {
            // Verzamel voorkeuren 1 t/m 3 uit de leerlingkaart.
            $voorkeursWerelden = [];
            for ($i = 1; $i <= 3; $i++) {
                $voorkeurWereldId = (int)($leerlingen_voor_automatisch[$leerlingId]['voorkeur' . $i] ?? 0);
                if ($voorkeurWereldId > 0 && isset($wereldenPerId[$voorkeurWereldId])) {
                    $voorkeursWerelden[] = $voorkeurWereldId;
                }
            }
            $voorkeursWerelden = array_values(array_unique($voorkeursWerelden));

            // Dit is de eerste wereld die we vastzetten.
            $ankerDag = null;
            $ankerWereldId = null;

            foreach ($voorkeursWerelden as $voorkeurWereldId) {
                // Bepaal op welke dagen deze voorkeur überhaupt mag voorkomen.
                $toegestaneDagen = [];
                $dagDeel = (string)($wereldenPerId[$voorkeurWereldId]['dag_deel'] ?? 'week');
                if ($dagDeel !== 'dag2') {
                    $toegestaneDagen[] = 'dag1';
                }
                if ($dagDeel !== 'dag1') {
                    $toegestaneDagen[] = 'dag2';
                }

                foreach ($toegestaneDagen as $dag) {
                    if ($mag_op_dag($voorkeurWereldId, $dag)) {
                        if ($ankerWereldId === null || $wereld_waarde($voorkeurWereldId, $dag) < $wereld_waarde((int)$ankerWereldId, (string)$ankerDag)) {
                            $ankerDag = $dag;
                            $ankerWereldId = $voorkeurWereldId;
                        }
                    }
                }
            }

            // Geen voorkeur bruikbaar? Kies dan de eerste vrije wereld.
            if ($ankerWereldId === null || $ankerDag === null) {
                foreach (array_keys($wereldenPerId) as $wereldId) {
                    foreach (['dag1', 'dag2'] as $dag) {
                        if ($mag_op_dag((int)$wereldId, $dag)) {
                            $ankerWereldId = (int)$wereldId;
                            $ankerDag = $dag;
                            break 2;
                        }
                    }
                }
            }

            // Zoek voor de tweede dag een andere wereld dan de eerste.
            $andereDag = ($ankerDag === 'dag1') ? 'dag2' : 'dag1';
            $andereWereldId = $zoek_beste_wereld(array_values(array_diff($voorkeursWerelden, [$ankerWereldId])), $andereDag, $ankerWereldId);
            if ($andereWereldId === null) {
                $andereWereldId = $zoek_beste_wereld(array_keys($wereldenPerId), $andereDag, $ankerWereldId);
            }

            // Als er geen nette combinatie is, slaan we deze leerling over.
            if ($ankerWereldId === null || $andereWereldId === null || $ankerWereldId === $andereWereldId) {
                $toewijzingen_per_leerling[$leerlingId] = ['week' => null, 'dag1' => null, 'dag2' => null];
                $niet_ingedeeld_aantal++;
                continue;
            }

            // Werk de tellingen bij zodat volgende leerlingen rekening houden met deze keuze.
            if ($ankerDag === 'dag1') {
                $aantalDag1PerWereld[$ankerWereldId]++;
            } else {
                $aantalDag2PerWereld[$ankerWereldId]++;
            }

            if ($andereDag === 'dag1') {
                $aantalDag1PerWereld[$andereWereldId]++;
            } else {
                $aantalDag2PerWereld[$andereWereldId]++;
            }

            $toewijzingen_per_leerling[$leerlingId] = [
                'week' => null,
                'dag1' => $ankerDag === 'dag1' ? $ankerWereldId : $andereWereldId,
                'dag2' => $ankerDag === 'dag2' ? $ankerWereldId : $andereWereldId,
            ];
        }
    } else {
        // VO/MBO: één toewijzing per leerling.
        // Eerst de voorkeuren, daarna een vrije wereld.
        foreach ($leerling_ids as $leerlingId) {
            // Verzamel voorkeuren 1 t/m 3, zonder dubbele IDs.
            $voorkeursWerelden = [];
            for ($i = 1; $i <= 3; $i++) {
                $voorkeurWereldId = (int)($leerlingen_voor_automatisch[$leerlingId]['voorkeur' . $i] ?? 0);
                if ($voorkeurWereldId > 0 && isset($wereldenPerId[$voorkeurWereldId])) {
                    $voorkeursWerelden[] = $voorkeurWereldId;
                }
            }
            $voorkeursWerelden = array_values(array_unique($voorkeursWerelden));

            // Kies eerst een voorkeurswereld die nog plek heeft.
            $gekozenWereldId = null;
            foreach ($voorkeursWerelden as $voorkeurWereldId) {
                if ($mag_op_dag($voorkeurWereldId, 'dag1')) {
                    $gekozenWereldId = $voorkeurWereldId;
                    break;
                }
            }
            if ($gekozenWereldId === null) {
                // Geen voorkeur beschikbaar? Pak dan de eerste vrije wereld.
                foreach (array_keys($wereldenPerId) as $wereldId) {
                    if ($mag_op_dag((int)$wereldId, 'dag1')) {
                        $gekozenWereldId = (int)$wereldId;
                        break;
                    }
                }
            }

            if ($gekozenWereldId === null) {
                $toewijzingen_per_leerling[$leerlingId] = ['week' => null, 'dag1' => null, 'dag2' => null];
                $niet_ingedeeld_aantal++;
                continue;
            }

            $toewijzingen_per_leerling[$leerlingId] = ['week' => $gekozenWereldId, 'dag1' => null, 'dag2' => null];
        }
    }

    csrf_regenerate();
    echo json_encode([
        'success' => true,
        'toewijzingen' => $toewijzingen_per_leerling,
        'niet_ingedeeld_aantal' => $niet_ingedeeld_aantal,
        'csrf_token' => csrf_token(),
    ]);
    exit;
}

// AJAX endpoint: huidige tabelindeling opslaan naar DB
if ($is_ajax_verzoek && $_GET['action'] === 'save') {
    header('Content-Type: application/json; charset=utf-8');

    // Lees de tabelgegevens uit de JSON-body van de browser.
    $jsonInput = file_get_contents('php://input');
    $verzoek_data = json_decode($jsonInput, true);
    $toewijzingen = $verzoek_data['toewijzingen'] ?? null;

    if (!is_array($toewijzingen)) {
        echo json_encode(['success' => false, 'message' => 'Ongeldige data.']);
        exit;
    }

    $bezoek_type_stmt = $conn->prepare('SELECT type_onderwijs FROM bezoek WHERE bezoek_id = ?');
    $bezoek_type_stmt->bind_param('i', $bezoekId);
    $bezoek_type_stmt->execute();
    $bezoekGegevens = $bezoek_type_stmt->get_result()->fetch_assoc();
    $bezoek_type_stmt->close();
    $is_po_bezoek = (($bezoekGegevens['type_onderwijs'] ?? '') === 'PO');

    // Wijzigingen worden in één transactie opgeslagen.
    $conn->begin_transaction();
    try {
        if ($is_po_bezoek) {
            // PO schrijft naar twee dagkolommen.
            // De weekkolom wordt expliciet leeggemaakt om geen oude waarde te laten staan.
            if ($heeftDag1Kolom && $heeftDag2Kolom) {
                $poOpslagStmt = $conn->prepare(" 
                    UPDATE leerling l
                    INNER JOIN bezoek_klas bk ON bk.klas_id = l.klas_id
                    SET l.toegewezen_dag1 = ?,
                        l.toegewezen_dag2 = ?,
                        l.toegewezen_week = NULL
                    WHERE l.leerling_id = ? AND bk.bezoek_id = ?
                ");

                foreach ($toewijzingen as $leerlingIdRuw => $gegevens) {
                    $leerlingId = (int)$leerlingIdRuw;
                    if ($leerlingId <= 0 || !is_array($gegevens)) {
                        continue;
                    }

                    // Alleen geldige numerieke ID's opslaan.
                    $dag1WereldId = isset($gegevens['dag1']) && ctype_digit((string)$gegevens['dag1']) ? (string)(int)$gegevens['dag1'] : null;
                    $dag2WereldId = isset($gegevens['dag2']) && ctype_digit((string)$gegevens['dag2']) ? (string)(int)$gegevens['dag2'] : null;

                    $poOpslagStmt->bind_param('ssii', $dag1WereldId, $dag2WereldId, $leerlingId, $bezoekId);
                    $poOpslagStmt->execute();
                }
                $poOpslagStmt->close();
            } else {
                // Fallback zonder dagkolommen: bewaar alsnog één bruikbare waarde.
                $fallbackOpslagStmt = $conn->prepare(" 
                    UPDATE leerling l
                    INNER JOIN bezoek_klas bk ON bk.klas_id = l.klas_id
                    SET l.toegewezen_week = ?
                    WHERE l.leerling_id = ? AND bk.bezoek_id = ?
                ");

                foreach ($toewijzingen as $leerlingIdRuw => $gegevens) {
                    $leerlingId = (int)$leerlingIdRuw;
                    if ($leerlingId <= 0 || !is_array($gegevens)) {
                        continue;
                    }

                    // Neem dag1 als die bestaat, anders dag2, anders leeg.
                    $dag1WereldId = isset($gegevens['dag1']) && ctype_digit((string)$gegevens['dag1']) ? (int)$gegevens['dag1'] : 0;
                    $dag2WereldId = isset($gegevens['dag2']) && ctype_digit((string)$gegevens['dag2']) ? (int)$gegevens['dag2'] : 0;

                    $opslag = '';
                    if ($dag1WereldId > 0) {
                        $opslag = maak_toegewezen_voorkeur($dag1WereldId, 'dag1');
                    } elseif ($dag2WereldId > 0) {
                        $opslag = maak_toegewezen_voorkeur($dag2WereldId, 'dag2');
                    }

                    $opslagOrNull = ($opslag === '') ? null : $opslag;
                    $fallbackOpslagStmt->bind_param('sii', $opslagOrNull, $leerlingId, $bezoekId);
                    $fallbackOpslagStmt->execute();
                }
                $fallbackOpslagStmt->close();
            }
        } else {
            // VO/MBO schrijft naar toegewezen_week.
            $voOpslagStmt = $conn->prepare(" 
                UPDATE leerling l
                INNER JOIN bezoek_klas bk ON bk.klas_id = l.klas_id
                SET l.toegewezen_week = ?
                WHERE l.leerling_id = ? AND bk.bezoek_id = ?
            ");

            foreach ($toewijzingen as $leerlingIdRuw => $gegevens) {
                $leerlingId = (int)$leerlingIdRuw;
                if ($leerlingId <= 0 || !is_array($gegevens)) {
                    continue;
                }

                // Eén week-ID per leerling, geen dagverdeling.
                $weekWereldId = isset($gegevens['week']) && ctype_digit((string)$gegevens['week']) ? (string)(int)$gegevens['week'] : null;
                $voOpslagStmt->bind_param('sii', $weekWereldId, $leerlingId, $bezoekId);
                $voOpslagStmt->execute();
            }
            $voOpslagStmt->close();
        }

        $conn->commit();
        csrf_regenerate();
        echo json_encode(['success' => true, 'csrf_token' => csrf_token()]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Fout opslaan verdeling: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Fout bij opslaan.']);
    }
    exit;
}

// Vanaf hier: normale pagina-rendering (geen AJAX)
require 'includes/header.php';

$stmt = $conn->prepare('SELECT bezoek_id, naam, type_onderwijs, max_keuzes FROM bezoek WHERE bezoek_id = ?');
$stmt->bind_param('i', $bezoekId);
$stmt->execute();
$bezoekGegevens = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bezoekGegevens) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Bezoek niet gevonden.</div></div>";
    require 'includes/footer.php';
    exit;
}

// PO toont twee dagvelden, VO/MBO toont één weekveld.
$is_po_bezoek = (($bezoekGegevens['type_onderwijs'] ?? '') === 'PO');
$maxKeuzes = (int)($bezoekGegevens['max_keuzes'] ?? 2);
if (!in_array($maxKeuzes, [2, 3], true)) {
    $maxKeuzes = 2;
}

$stmt = $conn->prepare(" 
    SELECT COUNT(*) AS klassen_count, COUNT(DISTINCT k.school_id) AS scholen_count
    FROM bezoek_klas bk
    INNER JOIN klas k ON k.klas_id = bk.klas_id
    WHERE bk.bezoek_id = ?
");
$stmt->bind_param('i', $bezoekId);
$stmt->execute();
$bezoekStatistiek = $stmt->get_result()->fetch_assoc();
$stmt->close();

$paginaTitel = 'Verdeling - ' . ($bezoekGegevens['naam'] ?? 'Bezoek');
$paginaSubtitel = ((int)($bezoekStatistiek['scholen_count'] ?? 0)) . ' scholen • ' . ((int)($bezoekStatistiek['klassen_count'] ?? 0)) . ' klassen';

// Opties die in de tabel en in de selects getoond worden.
$stmt = $conn->prepare(" 
    SELECT bo.optie_id AS id, bo.naam, bo.dag_deel
    FROM bezoek_optie bo
    WHERE bo.bezoek_id = ? AND bo.actief = 1
    ORDER BY bo.volgorde ASC
");
$stmt->bind_param('i', $bezoekId);
$stmt->execute();
$optie_result = $stmt->get_result();

$wereldenVoorTabel = [];
$wereldNaamPerId = [];
$wereldDagdeelPerId = [];
while ($optieRij = $optie_result->fetch_assoc()) {
    $wereldId = (int)$optieRij['id'];
    $wereldenVoorTabel[] = $optieRij;
    $wereldNaamPerId[$wereldId] = $optieRij['naam'];
    $wereldDagdeelPerId[$wereldId] = (string)($optieRij['dag_deel'] ?? 'week');
}
$stmt->close();

// Leerlingenlijst voor de tabel, inclusief huidige toewijzingen.
$leerlingenSql = "
    SELECT l.leerling_id, l.voornaam, l.tussenvoegsel, l.achternaam,
           l.voorkeur1, l.voorkeur2, l.voorkeur3, l.toegewezen_week,
           k.klasaanduiding, s.schoolnaam";
if ($heeftDag1Kolom) {
    $leerlingenSql .= ', l.toegewezen_dag1';
} else {
    $leerlingenSql .= ', NULL AS toegewezen_dag1';
}
if ($heeftDag2Kolom) {
    $leerlingenSql .= ', l.toegewezen_dag2';
} else {
    $leerlingenSql .= ', NULL AS toegewezen_dag2';
}
$leerlingenSql .= "
    FROM leerling l
    INNER JOIN bezoek_klas bk ON bk.klas_id = l.klas_id
    INNER JOIN klas k ON k.klas_id = l.klas_id
    INNER JOIN school s ON s.school_id = k.school_id
    WHERE bk.bezoek_id = ?
    ORDER BY s.schoolnaam ASC, k.klasaanduiding ASC, l.achternaam ASC, l.voornaam ASC
";

$stmt = $conn->prepare($leerlingenSql);
$stmt->bind_param('i', $bezoekId);
$stmt->execute();
$leerlingen_lijst = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="ttr-app">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1"><?= e($paginaTitel) ?></h2>
                <div class="text-muted"><?= e($paginaSubtitel) ?></div>
            </div>
            <div class="d-flex gap-2">
                <a href="bezoeken.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Terug naar bezoeken
                </a>
                <button id="btnAuto" class="btn btn-primary">
                    <i class="bi bi-lightning"></i> Verdelen
                </button>
                <button id="btnSave" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Opslaan
                </button>
            </div>
        </div>

        <div id="autoMessages" class="mb-3"></div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Leerlingen indelen</span>
                <span class="badge bg-light text-primary"><?= count($leerlingen_lijst) ?> leerling(en)</span>
            </div>
            <div class="card-body p-0">
                <div style="overflow-x: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Naam</th>
                                <th>School</th>
                                <th>Klas</th>
                                <th>Voorkeur 1</th>
                                <th>Voorkeur 2</th>
                                <?php if ($maxKeuzes >= 3): ?><th>Voorkeur 3</th><?php endif; ?>
                                <?php if ($is_po_bezoek): ?>
                                    <th>Toegewezen Dag 1</th>
                                    <th>Toegewezen Dag 2</th>
                                <?php else: ?>
                                    <th>Toegewezen week</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leerlingen_lijst)): ?>
                                <tr>
                                    <td colspan="<?= $is_po_bezoek ? ($maxKeuzes >= 3 ? 8 : 7) : ($maxKeuzes >= 3 ? 7 : 6) ?>" class="text-center py-3 text-muted">Nog geen leerlingen.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leerlingen_lijst as $leerling): ?>
                                    <?php
                                    // Huidige waarden uit de database omzetten naar geselecteerde opties.
                                    $leerlingId = (int)$leerling['leerling_id'];
                                    [$toegewezenWereldId] = parse_toegewezen_voorkeur($leerling['toegewezen_week'] ?? '');

                                    $geselecteerdeWeek = '';
                                    $geselecteerdeDag1 = '';
                                    $geselecteerdeDag2 = '';

                                    if ($is_po_bezoek) {
                                        if ($heeftDag1Kolom && !empty($leerling['toegewezen_dag1']) && ctype_digit((string)$leerling['toegewezen_dag1'])) {
                                            $geselecteerdeDag1 = (string)(int)$leerling['toegewezen_dag1'];
                                        }
                                        if ($heeftDag2Kolom && !empty($leerling['toegewezen_dag2']) && ctype_digit((string)$leerling['toegewezen_dag2'])) {
                                            $geselecteerdeDag2 = (string)(int)$leerling['toegewezen_dag2'];
                                        }
                                    } else {
                                        if ($toegewezenWereldId > 0) {
                                            $geselecteerdeWeek = (string)$toegewezenWereldId;
                                        }
                                    }
                                    ?>
                                    <tr data-student-row="<?= $leerlingId ?>">
                                        <td><strong><?= e(trim($leerling['voornaam'] . ' ' . ($leerling['tussenvoegsel'] ?: '') . ' ' . $leerling['achternaam'])) ?></strong></td>
                                        <td><?= e($leerling['schoolnaam']) ?></td>
                                        <td><?= e($leerling['klasaanduiding']) ?></td>

                                        <?php for ($i = 1; $i <= 2; $i++): ?>
                                            <td>
                                                <?php
                                                $voorkeurWaarde = $leerling['voorkeur' . $i] ?? '';
                                                if (ctype_digit((string)$voorkeurWaarde) && isset($wereldNaamPerId[(int)$voorkeurWaarde])) {
                                                    echo e($wereldNaamPerId[(int)$voorkeurWaarde]);
                                                } elseif ($voorkeurWaarde === '' || $voorkeurWaarde === null) {
                                                    echo '<span class="text-muted">-</span>';
                                                } else {
                                                    echo '<span class="text-danger">' . e($voorkeurWaarde) . ' *</span>';
                                                }
                                                ?>
                                            </td>
                                        <?php endfor; ?>

                                        <?php if ($maxKeuzes >= 3): ?>
                                            <td>
                                                <?php
                                                $voorkeurWaarde = $leerling['voorkeur3'] ?? '';
                                                if (ctype_digit((string)$voorkeurWaarde) && isset($wereldNaamPerId[(int)$voorkeurWaarde])) {
                                                    echo e($wereldNaamPerId[(int)$voorkeurWaarde]);
                                                } elseif ($voorkeurWaarde === '' || $voorkeurWaarde === null) {
                                                    echo '<span class="text-muted">-</span>';
                                                } else {
                                                    echo '<span class="text-danger">' . e($voorkeurWaarde) . ' *</span>';
                                                }
                                                ?>
                                            </td>
                                        <?php endif; ?>

                                        <?php if ($is_po_bezoek): ?>
                                            <td>
                                                <!-- Dag 1: alleen werelden die op dag 1 of beide dagen mogen -->
                                                <select class="form-select form-select-sm assign-dag1" data-leerling-id="<?= $leerlingId ?>">
                                                    <option value="">-</option>
                                                    <?php foreach ($wereldenVoorTabel as $wereld):
                                                        $wereldId = (int)$wereld['id'];
                                                        $dagdeel = (string)($wereld['dag_deel'] ?? 'week');
                                                        if (!in_array($dagdeel, ['dag1', 'beide', 'week'], true)) {
                                                            continue;
                                                        }
                                                    ?>
                                                        <option value="<?= $wereldId ?>" <?= ((string)$wereldId === $geselecteerdeDag1 ? 'selected' : '') ?>><?= e($wereld['naam']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <!-- Dag 2: alleen werelden die op dag 2 of beide dagen mogen -->
                                                <select class="form-select form-select-sm assign-dag2" data-leerling-id="<?= $leerlingId ?>">
                                                    <option value="">-</option>
                                                    <?php foreach ($wereldenVoorTabel as $wereld):
                                                        $wereldId = (int)$wereld['id'];
                                                        $dagdeel = (string)($wereld['dag_deel'] ?? 'week');
                                                        if (!in_array($dagdeel, ['dag2', 'beide', 'week'], true)) {
                                                            continue;
                                                        }
                                                    ?>
                                                        <option value="<?= $wereldId ?>" <?= ((string)$wereldId === $geselecteerdeDag2 ? 'selected' : '') ?>><?= e($wereld['naam']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        <?php else: ?>
                                            <td>
                                                <!-- VO/MBO: één weektoewijzing per leerling -->
                                                <select class="form-select form-select-sm assign-week" data-leerling-id="<?= $leerlingId ?>">
                                                    <option value="">-</option>
                                                    <?php foreach ($wereldenVoorTabel as $wereld): ?>
                                                        <option value="<?= (int)$wereld['id'] ?>" <?= ((string)((int)$wereld['id']) === $geselecteerdeWeek ? 'selected' : '') ?>><?= e($wereld['naam']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 small text-muted">
            <strong>Legenda:</strong> <span class="text-danger">*</span> = keuze/ID staat niet (meer) in de optielijst van dit bezoek.
        </div>
    </div>
</div>

<script>
(function() {
    // PO gebruikt twee selects per leerling, VO/MBO gebruikt er één.
    const is_po_bezoek = <?= json_encode($is_po_bezoek) ?>;
    let csrfToken = <?= json_encode(csrf_token()) ?>;
    const berichtVak = document.getElementById('autoMessages');

    // Toon feedback boven de tabel
    function toonMelding(htmlInhoud, soort) {
        if (!berichtVak) return;
        berichtVak.innerHTML = '<div class="alert alert-' + (soort || 'info') + '" role="alert">' + htmlInhoud + '</div>';
    }

    // Lees de huidige waarden uit de selectvelden in de tabel
    function verzamelToewijzingen() {
        const rijen = document.querySelectorAll('tr[data-student-row]');
        const toewijzingen = {};

        rijen.forEach(function(rij) {
            const leerlingId = rij.getAttribute('data-student-row');
            if (!leerlingId) return;

            if (is_po_bezoek) {
                // PO: verzamel dag 1 en dag 2 apart.
                const dag1Select = rij.querySelector('.assign-dag1');
                const dag2Select = rij.querySelector('.assign-dag2');
                toewijzingen[leerlingId] = {
                    week: null,
                    dag1: dag1Select && dag1Select.value ? parseInt(dag1Select.value, 10) : null,
                    dag2: dag2Select && dag2Select.value ? parseInt(dag2Select.value, 10) : null,
                };
            } else {
                // VO/MBO: verzamel één weekkeuze.
                const weekSelect = rij.querySelector('.assign-week');
                toewijzingen[leerlingId] = {
                    week: weekSelect && weekSelect.value ? parseInt(weekSelect.value, 10) : null,
                    dag1: null,
                    dag2: null,
                };
            }
        });

        return toewijzingen;
    }

    // Zet auto-verdeelresultaat terug in de juiste selectvelden
    function pasToewijzingenToe(toewijzingen) {
        if (!toewijzingen || typeof toewijzingen !== 'object') return;

        Object.keys(toewijzingen).forEach(function(leerlingId) {
            const rij = document.querySelector('tr[data-student-row="' + leerlingId + '"]');
            if (!rij) return;

            const gegevens = toewijzingen[leerlingId] || {};
            if (is_po_bezoek) {
                // Zet de twee PO-selects apart terug.
                const dag1Select = rij.querySelector('.assign-dag1');
                const dag2Select = rij.querySelector('.assign-dag2');
                if (dag1Select) dag1Select.value = gegevens.dag1 ? String(gegevens.dag1) : '';
                if (dag2Select) dag2Select.value = gegevens.dag2 ? String(gegevens.dag2) : '';
            } else {
                // Zet de weekselect terug.
                const weekSelect = rij.querySelector('.assign-week');
                if (weekSelect) weekSelect.value = gegevens.week ? String(gegevens.week) : '';
            }
        });
    }

    const knopAutomatisch = document.getElementById('btnAuto');
    if (knopAutomatisch) {
        // Start auto-verdeling via AJAX en vul de tabel met het resultaat.
        knopAutomatisch.addEventListener('click', function() {
            if (!confirm('Weet je zeker dat je automatisch wilt verdelen?')) return;

            fetch('indeling.php?bezoek_id=<?= (int)$bezoekId ?>&action=auto', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            })
            .then(function(antwoord) { return antwoord.json(); })
            .then(function(resultaat) {
                if (!resultaat.success) {
                    toonMelding('Er is een fout opgetreden bij automatisch verdelen.', 'danger');
                    return;
                }

                if (resultaat.csrf_token) {
                    csrfToken = String(resultaat.csrf_token);
                }

                pasToewijzingenToe(resultaat.toewijzingen || {});

                if ((resultaat.niet_ingedeeld_aantal || 0) > 0) {
                    toonMelding('Automatisch verdeeld. ' + resultaat.niet_ingedeeld_aantal + ' leerling(en) konden niet geplaatst worden.', 'warning');
                } else {
                    toonMelding('Alle leerlingen zijn automatisch ingedeeld.', 'success');
                }
            })
            .catch(function() {
                toonMelding('Er is een fout opgetreden bij automatisch verdelen.', 'danger');
            });
        });
    }

    const knopOpslaan = document.getElementById('btnSave');
    if (knopOpslaan) {
        // Sla de huidige tabelindeling op via AJAX.
        knopOpslaan.addEventListener('click', function() {
            if (!confirm('Opslaan schrijft de huidige indeling naar de database. Ga je akkoord?')) return;

            const toewijzingen = verzamelToewijzingen();

            fetch('indeling.php?bezoek_id=<?= (int)$bezoekId ?>&action=save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ toewijzingen: toewijzingen })
            })
            .then(function(antwoord) { return antwoord.json(); })
            .then(function(resultaat) {
                if (resultaat.success) {
                    if (resultaat.csrf_token) {
                        csrfToken = String(resultaat.csrf_token);
                    }
                    toonMelding('Indeling succesvol opgeslagen.', 'success');
                } else {
                    toonMelding('Fout bij opslaan: ' + (resultaat.message || ''), 'danger');
                }
            })
            .catch(function() {
                toonMelding('Er is een fout opgetreden bij opslaan.', 'danger');
            });
        });
    }
})();
</script>

<?php require 'includes/footer.php'; ?>
