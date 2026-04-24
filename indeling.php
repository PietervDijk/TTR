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


<?php require 'includes/footer.php'; ?>
