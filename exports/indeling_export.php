<?php
/**
 * CSV-export voor de indeling.
 *
 * PO gebruikt twee toegewezen dagkolommen.
 * VO/MBO gebruikt één toegewezen sector-kolom voor de hele week.
 */

require_once __DIR__ . '/../includes/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function haal_id_uit_waarde(string $waarde): int
{
    // Haal alleen een numeriek ID uit een opgeslagen waarde.
    $waarde = trim($waarde);
    if ($waarde === '') {
        return 0;
    }

    if (preg_match('/^(\d+)/', $waarde, $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

function vertaal_waarde_naar_naam(string $waarde, array $namen_per_id): string
{
    // Zet een ID om naar de bijbehorende naam.
    $waarde = trim($waarde);
    if ($waarde === '') {
        return '';
    }

    $id = haal_id_uit_waarde($waarde);
    if ($id > 0) {
        return $namen_per_id[$id] ?? '';
    }

    return $waarde;
}

function formatteer_naam(array $rij): string
{
    // Bouw een nette voor- en achternaam samen.
    return trim(
        ($rij['voornaam'] ?? '') . ' ' .
        ($rij['tussenvoegsel'] ?? '') . ' ' .
        ($rij['achternaam'] ?? '')
    );
}

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Toegang geweigerd.');
}

// Zonder bezoek_id weten we niet welke indeling we moeten exporteren.
$bezoek_id = isset($_GET['bezoek_id']) ? (int)$_GET['bezoek_id'] : 0;
if ($bezoek_id <= 0) {
    http_response_code(400);
    exit('Parameter bezoek_id ontbreekt.');
}

// Lees het bezoek terug: naam voor de bestandsnaam, type voor de juiste kolommen
// en max_keuzes voor het wel of niet tonen van voorkeur 3.
$stmt = $conn->prepare('SELECT naam, type_onderwijs, max_keuzes FROM bezoek WHERE bezoek_id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    exit('Databasefout.');
}
$stmt->bind_param('i', $bezoek_id);
$stmt->execute();
$bezoek_gegevens = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bezoek_gegevens) {
    http_response_code(404);
    exit('Bezoek niet gevonden.');
}

// PO krijgt dag 1 en dag 2. VO/MBO krijgt één toegewezen sector-kolom voor de hele week.
$is_po_bezoek = (($bezoek_gegevens['type_onderwijs'] ?? '') === 'PO');
$heeft_derde_voorkeur = ((int)($bezoek_gegevens['max_keuzes'] ?? 2) >= 3);

// Haal alle leerlingen van dit bezoek op, samen met school en klas.
$sql_leerlingen = "
    SELECT
        l.voornaam,
        l.tussenvoegsel,
        l.achternaam,
        l.voorkeur1,
        l.voorkeur2,
        l.voorkeur3,
";

if ($is_po_bezoek) {
    $sql_leerlingen .= "
        l.toegewezen_dag1,
        l.toegewezen_dag2,
";
} else {
    $sql_leerlingen .= "
        l.toegewezen_week,
";
}

$sql_leerlingen .= "
        s.schoolnaam,
        k.klasaanduiding
    FROM leerling l
    INNER JOIN klas k ON k.klas_id = l.klas_id
    INNER JOIN school s ON s.school_id = k.school_id
    INNER JOIN bezoek_klas bk ON bk.klas_id = l.klas_id
    WHERE bk.bezoek_id = ?
    ORDER BY s.schoolnaam ASC, k.klasaanduiding ASC, l.achternaam ASC, l.voornaam ASC
";

$stmt = $conn->prepare($sql_leerlingen);
if (!$stmt) {
    http_response_code(500);
    exit('Databasefout bij ophalen leerlingen.');
}
$stmt->bind_param('i', $bezoek_id);
$stmt->execute();
$leerlingen_resultaat = $stmt->get_result();

$leerlingen = [];
$gebruikte_optie_ids = [];

while ($leerling_rij = $leerlingen_resultaat->fetch_assoc()) {
    $leerlingen[] = $leerling_rij;

    // Verzamel alle gebruikte voorkeur- en toewijzings-ID's.
    if ($is_po_bezoek) {
        // PO: voorkeuren + twee dagen
        $velden_voor_ids = ['voorkeur1', 'voorkeur2', 'toegewezen_dag1', 'toegewezen_dag2'];
    } else {
        // VO/MBO: voorkeuren + toegewezen_week
        $velden_voor_ids = ['voorkeur1', 'voorkeur2', 'toegewezen_week'];
    }

    if ($heeft_derde_voorkeur) {
        $velden_voor_ids[] = 'voorkeur3';
    }

    foreach ($velden_voor_ids as $veld) {
        $id = haal_id_uit_waarde((string)($leerling_rij[$veld] ?? ''));
        if ($id > 0) {
            $gebruikte_optie_ids[$id] = true;
        }
    }
}
$stmt->close();

$naam_per_optie_id = [];

// Zet gebruikte IDs om naar leesbare namen.
if (!empty($gebruikte_optie_ids)) {
    $ids_sql = implode(',', array_map('intval', array_keys($gebruikte_optie_ids)));
    $sql_opties = "SELECT optie_id, naam FROM bezoek_optie WHERE bezoek_id = $bezoek_id AND optie_id IN ($ids_sql) AND actief = 1";
    $opties_resultaat = $conn->query($sql_opties);

    if ($opties_resultaat) {
        while ($optie_rij = $opties_resultaat->fetch_assoc()) {
            $naam_per_optie_id[(int)$optie_rij['optie_id']] = $optie_rij['naam'];
        }
    }
}

// De browser krijgt een download, geen losse pagina.
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="indeling_' . preg_replace('/[^a-z0-9\-_]+/i', '_', (string)$bezoek_gegevens['naam']) . '.csv"');

$uitvoer = fopen('php://output', 'w');
$scheidingsteken = ';';

// BOM helpt Excel om UTF-8 goed te lezen.
echo "\xEF\xBB\xBF";

// Maak eerst de kolomkoppen. Die verschillen licht per type bezoek.
$kolomkoppen = ['Naam', 'School', 'Klas', 'Voorkeur 1', 'Voorkeur 2'];
if ($heeft_derde_voorkeur) {
    $kolomkoppen[] = 'Voorkeur 3';
}

if ($is_po_bezoek) {
    $kolomkoppen[] = 'Toegewezen Dag 1';
    $kolomkoppen[] = 'Toegewezen Dag 2';
} else {
    $kolomkoppen[] = 'Toegewezen Sector';
}

fputcsv($uitvoer, $kolomkoppen, $scheidingsteken);

foreach ($leerlingen as $leerling_rij) {
    // Zet de opgeslagen voorkeuren om naar leesbare namen.
    $voorkeur1 = vertaal_waarde_naar_naam((string)($leerling_rij['voorkeur1'] ?? ''), $naam_per_optie_id);
    $voorkeur2 = vertaal_waarde_naar_naam((string)($leerling_rij['voorkeur2'] ?? ''), $naam_per_optie_id);
    $voorkeur3 = $heeft_derde_voorkeur
        ? vertaal_waarde_naar_naam((string)($leerling_rij['voorkeur3'] ?? ''), $naam_per_optie_id)
        : null;

    if ($is_po_bezoek) {
        // PO: laat beide dagen apart zien.
        $dag1Naam = vertaal_waarde_naar_naam((string)($leerling_rij['toegewezen_dag1'] ?? ''), $naam_per_optie_id);
        $dag2Naam = vertaal_waarde_naar_naam((string)($leerling_rij['toegewezen_dag2'] ?? ''), $naam_per_optie_id);

        $exportrij = [
            formatteer_naam($leerling_rij),
            $leerling_rij['schoolnaam'] ?? '',
            $leerling_rij['klasaanduiding'] ?? '',
            $voorkeur1,
            $voorkeur2,
        ];

        if ($heeft_derde_voorkeur) {
            $exportrij[] = $voorkeur3;
        }

        $exportrij[] = $dag1Naam;
        $exportrij[] = $dag2Naam;

        fputcsv($uitvoer, $exportrij, $scheidingsteken);
    } else {
        // VO/MBO: één sector-toewijzing in de weekkolom.
        $sectorNaam = vertaal_waarde_naar_naam((string)($leerling_rij['toegewezen_week'] ?? ''), $naam_per_optie_id);

        $exportrij = [
            formatteer_naam($leerling_rij),
            $leerling_rij['schoolnaam'] ?? '',
            $leerling_rij['klasaanduiding'] ?? '',
            $voorkeur1,
            $voorkeur2,
        ];

        if ($heeft_derde_voorkeur) {
            $exportrij[] = $voorkeur3;
        }

        $exportrij[] = $sectorNaam;

        fputcsv($uitvoer, $exportrij, $scheidingsteken);
    }
}

fclose($uitvoer);
exit;
