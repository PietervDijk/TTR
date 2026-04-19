<?php
require 'includes/config.php';

// Zorg dat de sessie actief is (ook voor AJAX)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// admin check
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// bezoek_id verplicht
if (!isset($_GET['bezoek_id']) || !ctype_digit((string)$_GET['bezoek_id'])) {
    header('Location: bezoeken.php');
    exit;
}
$bezoek_id = (int)$_GET['bezoek_id'];

// helper
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function parse_toegewezen_voorkeur($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return [0, null];
    }

    if (strpos($value, '|') !== false) {
        [$sectorIdRaw, $variantRaw] = explode('|', $value, 2);
        $sectorId = (int)$sectorIdRaw;
        $variant = trim($variantRaw);
        if (!in_array($variant, ['week', 'dag1', 'dag2', 'beide'], true)) {
            $variant = null;
        }

        return [$sectorId, $variant];
    }

    if (ctype_digit($value)) {
        return [(int)$value, null];
    }

    return [0, null];
}

function maak_toegewezen_voorkeur($sectorId, $variant = null)
{
    $sectorId = (int)$sectorId;
    $variant = trim((string)$variant);

    if ($sectorId <= 0) {
        return '';
    }

    if ($variant !== '' && in_array($variant, ['dag1', 'dag2'], true)) {
        return $sectorId . '|' . $variant;
    }

    return (string)$sectorId;
}

function kies_po_variant(array $sector, array $variantCounts)
{
    $capDag1 = (int)($sector['max_leerlingen_dag1'] ?? 0);
    $capDag2 = (int)($sector['max_leerlingen_dag2'] ?? 0);
    $countDag1 = (int)($variantCounts['dag1'] ?? 0);
    $countDag2 = (int)($variantCounts['dag2'] ?? 0);

    $beschikbaarDag1 = ($capDag1 <= 0) || ($countDag1 < $capDag1);
    $beschikbaarDag2 = ($capDag2 <= 0) || ($countDag2 < $capDag2);

    if ($beschikbaarDag1 && !$beschikbaarDag2) {
        return 'dag1';
    }
    if ($beschikbaarDag2 && !$beschikbaarDag1) {
        return 'dag2';
    }
    if (!$beschikbaarDag1 && !$beschikbaarDag2) {
        return null;
    }

    $ratioDag1 = $capDag1 > 0 ? ($countDag1 / $capDag1) : $countDag1;
    $ratioDag2 = $capDag2 > 0 ? ($countDag2 / $capDag2) : $countDag2;

    if ($ratioDag1 < $ratioDag2) {
        return 'dag1';
    }
    if ($ratioDag2 < $ratioDag1) {
        return 'dag2';
    }

    return 'dag1';
}

// ----------------- AJAX endpoints -----------------
$isAjax = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && in_array($_GET['action'], ['auto', 'save'], true)
);

// ----------------- AUTO VERDELING (EERLIJK) -----------------
if ($isAjax && $_GET['action'] === 'auto') {
    header('Content-Type: application/json; charset=utf-8');

    $stmt = $conn->prepare("SELECT type_onderwijs FROM bezoek WHERE bezoek_id=?");
    $stmt->bind_param('i', $bezoek_id);
    $stmt->execute();
    $bezoekRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $isPoBezoek = (($bezoekRow['type_onderwijs'] ?? '') === 'PO');

    $stmt = $conn->prepare(" 
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
        WHERE bo.bezoek_id=? AND bo.actief=1
        ORDER BY bo.volgorde ASC
    ");
    $stmt->bind_param("i", $bezoek_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $sectors = [];
    $capacity = [];
    while ($r = $res->fetch_assoc()) {
        $sid = (int)$r['id'];
        $sectors[$sid] = [
            'id'       => $sid,
            'naam'     => $r['naam'],
            'dag_deel' => (string)($r['dag_deel'] ?? 'week'),
            'max_leerlingen_dag1' => isset($r['max_leerlingen_dag1']) ? (int)$r['max_leerlingen_dag1'] : 0,
            'max_leerlingen_dag2' => isset($r['max_leerlingen_dag2']) ? (int)$r['max_leerlingen_dag2'] : 0,
            'max'      => (int)$r['max_leerlingen'],
            'assigned' => [],
            'variant_counts' => ['dag1' => 0, 'dag2' => 0],
        ];
        $capacity[$sid] = (int)$r['max_leerlingen'];
    }
    $stmt->close();

    $stmt = $conn->prepare(" 
        SELECT l.leerling_id, l.voornaam, l.tussenvoegsel, l.achternaam,
               l.voorkeur1, l.voorkeur2, l.voorkeur3
        FROM leerling l
        INNER JOIN bezoek_klas bk ON bk.klas_id = l.klas_id
        WHERE bk.bezoek_id=?
    ");
    $stmt->bind_param("i", $bezoek_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $students = [];
    while ($r = $res->fetch_assoc()) {
        $students[(int)$r['leerling_id']] = $r;
    }
    $stmt->close();

    // Shuffle = geen volgorde-voordeel
    $studentIds = array_keys($students);
    shuffle($studentIds);

    $unassigned = array_fill_keys($studentIds, true);
    $assigned   = [];

    // --------- VERDELING PER VOORKEURLAAG ---------
    for ($p = 1; $p <= 3; $p++) {

        // sector_id => [leerling_id, leerling_id...]
        $buckets = [];

        foreach ($unassigned as $lid => $_) {
            $sid = (int)$students[$lid]["voorkeur$p"];
            if ($sid > 0 && isset($sectors[$sid])) {
                if ($capacity[$sid] === 0 || count($sectors[$sid]['assigned']) < $capacity[$sid]) {
                    $buckets[$sid][] = $lid;
                }
            }
        }

        // Per sector eerlijk loten
        foreach ($buckets as $sid => $leerlingen) {
            shuffle($leerlingen);

            foreach ($leerlingen as $lid) {
                if (!isset($unassigned[$lid])) continue;

                if ($capacity[$sid] === 0 || count($sectors[$sid]['assigned']) < $capacity[$sid]) {
                    $variant = null;
                    if ($isPoBezoek && ($sectors[$sid]['dag_deel'] ?? 'week') === 'beide') {
                        $variant = kies_po_variant($sectors[$sid], $sectors[$sid]['variant_counts']);
                        if ($variant === null) {
                            continue;
                        }
                        $sectors[$sid]['variant_counts'][$variant]++;
                    }

                    $assignmentValue = maak_toegewezen_voorkeur($sid, $variant);
                    $sectors[$sid]['assigned'][] = [
                        'student_id' => $lid,
                        'assignment' => $assignmentValue,
                        'variant' => $variant,
                    ];
                    $assigned[$lid] = $assignmentValue;
                    unset($unassigned[$lid]);
                }
            }
        }
    }

    // --------- RESTVERDELING ---------
    foreach ($unassigned as $lid => $_) {
        foreach ($sectors as $sid => $sector) {
            if ($capacity[$sid] === 0 || count($sector['assigned']) < $capacity[$sid]) {
                $variant = null;
                if ($isPoBezoek && ($sector['dag_deel'] ?? 'week') === 'beide') {
                    $variant = kies_po_variant($sector, $sectors[$sid]['variant_counts']);
                    if ($variant === null) {
                        continue;
                    }
                    $sectors[$sid]['variant_counts'][$variant]++;
                }

                $assignmentValue = maak_toegewezen_voorkeur($sid, $variant);
                $sectors[$sid]['assigned'][] = [
                    'student_id' => $lid,
                    'assignment' => $assignmentValue,
                    'variant' => $variant,
                ];
                $assigned[$lid] = $assignmentValue;
                unset($unassigned[$lid]);
                break;
            }
        }
    }

    // --------- NIET GEPLAATST (alle sectoren vol) ---------
    $notPlaced = [];
    foreach ($unassigned as $lid => $_) {
        $stu = $students[$lid];
        $notPlaced[] = [
            'id'    => $lid,
            'naam'  => trim($stu['voornaam'] . ' ' . ($stu['tussenvoegsel'] ?: '') . ' ' . $stu['achternaam']),
            'reden' => 'Alle sectoren zijn vol.'
        ];
    }

    // Output exact zoals frontend verwacht
    $out = ['success' => true, 'sectors' => [], 'unassigned' => $notPlaced];
    foreach ($sectors as $s) {
        $out['sectors'][] = [
            'id'       => $s['id'],
            'naam'     => $s['naam'],
            'dag_deel' => $s['dag_deel'],
            'assigned' => $s['assigned'],
            'max'      => $s['max']
        ];
    }

    $out['assignments'] = $assigned;

    echo json_encode($out);
    exit;
}

// ----------------- OPSLAAN -----------------
if ($isAjax && $_GET['action'] === 'save') {
    header('Content-Type: application/json; charset=utf-8');

    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['assignments'])) {
        echo json_encode(['success' => false, 'message' => 'Ongeldige payload']);
        exit;
    }
    $assignments = $data['assignments'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(" 
            UPDATE leerling l
            INNER JOIN bezoek_klas bk ON bk.klas_id = l.klas_id
            SET l.toegewezen_voorkeur=?
            WHERE l.leerling_id=? AND bk.bezoek_id=?
        ");

        foreach ($assignments as $leerling_id => $sector_id) {
            $leerling_id = (int)$leerling_id;

            if ($sector_id === null || $sector_id === '') {
                $q = $conn->prepare(" 
                    UPDATE leerling l
                    INNER JOIN bezoek_klas bk ON bk.klas_id = l.klas_id
                    SET l.toegewezen_voorkeur=NULL
                    WHERE l.leerling_id=? AND bk.bezoek_id=?
                ");
                $q->bind_param("ii", $leerling_id, $bezoek_id);
                $q->execute();
                $q->close();
            } else {
                $sector_id = (int)$sector_id;
                $stmt->bind_param("iii", $sector_id, $leerling_id, $bezoek_id);
                $stmt->execute();
            }
        }

        $stmt->close();
        $conn->commit();
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Fout opslaan verdeling: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Fout bij opslaan.']);
        exit;
    }
}

// ----------------- PAGINA -----------------

require 'includes/header.php';

$page_title = '';
$page_subtitle = '';
$back_url = 'bezoeken.php';
$back_label = 'Terug naar bezoeken';

$stmt = $conn->prepare("SELECT bezoek_id, naam, type_onderwijs FROM bezoek WHERE bezoek_id=?");
$stmt->bind_param("i", $bezoek_id);
$stmt->execute();
$bezoek = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bezoek) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Bezoek niet gevonden</div></div>";
    require 'includes/footer.php';
    exit;
}

$isPoBezoek = (($bezoek['type_onderwijs'] ?? '') === 'PO');

$stmt = $conn->prepare(" 
    SELECT COUNT(*) AS klassen_count, COUNT(DISTINCT k.school_id) AS scholen_count
    FROM bezoek_klas bk
    INNER JOIN klas k ON k.klas_id = bk.klas_id
    WHERE bk.bezoek_id=?
");
$stmt->bind_param("i", $bezoek_id);
$stmt->execute();
$bezoek_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = 'Verdeling - ' . ($bezoek['naam'] ?? 'Bezoek');
$page_subtitle = ((int)($bezoek_stats['scholen_count'] ?? 0)) . ' scholen • ' . ((int)($bezoek_stats['klassen_count'] ?? 0)) . ' klassen';

// sectoren (van bezoek_optie)
$stmt = $conn->prepare(" 
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
    WHERE bo.bezoek_id=? AND bo.actief=1
    ORDER BY bo.volgorde ASC
");
$stmt->bind_param("i", $bezoek_id);
$stmt->execute();
$res   = $stmt->get_result();
$sectors = [];
$sectorNaamMap = [];
$sectorMetaMap = [];
while ($r = $res->fetch_assoc()) {
    $sectors[] = $r;
    $sectorNaamMap[(int)$r['id']] = $r['naam'];
    $sectorMetaMap[(int)$r['id']] = [
        'dag_deel' => (string)($r['dag_deel'] ?? 'week'),
        'max_leerlingen_dag1' => isset($r['max_leerlingen_dag1']) ? (int)$r['max_leerlingen_dag1'] : 0,
        'max_leerlingen_dag2' => isset($r['max_leerlingen_dag2']) ? (int)$r['max_leerlingen_dag2'] : 0,
    ];
}
$stmt->close();

// leerlingen
$stmt = $conn->prepare(" 
    SELECT l.leerling_id, l.voornaam, l.tussenvoegsel, l.achternaam,
           l.voorkeur1, l.voorkeur2, l.voorkeur3, l.toegewezen_voorkeur,
           k.klasaanduiding, s.schoolnaam
    FROM leerling l
    INNER JOIN bezoek_klas bk ON bk.klas_id = l.klas_id
    INNER JOIN klas k ON k.klas_id = l.klas_id
    INNER JOIN school s ON s.school_id = k.school_id
    WHERE bk.bezoek_id=?
    ORDER BY s.schoolnaam ASC, k.klasaanduiding ASC, l.achternaam ASC, l.voornaam ASC
");
$stmt->bind_param("i", $bezoek_id);
$stmt->execute();
$leerlingen = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stuNames = [];
foreach ($leerlingen as $l) {
    $stuNames[(int)$l['leerling_id']] =
        trim($l['voornaam'] . ' ' . ($l['tussenvoegsel'] ?: '') . ' ' . $l['achternaam']);
}
?>

<div class="ttr-app">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1"><?= e($page_title) ?></h2>
                <div class="text-muted"><?= e($page_subtitle) ?></div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e($back_url) ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> <?= e($back_label) ?>
                </a>
                <button id="btnAuto" class="btn btn-primary">
                    <i class="bi bi-lightning"></i> Verdelen
                </button>
                <button id="btnSave" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Opslaan
                </button>
            </div>
        </div>

        <!-- Hier komen meldingen (Bootstrap alerts) -->
        <div id="autoMessages" class="mb-3"></div>

        <!-- Sectoren / werelden -->
        <div class="row g-3 mb-4">
            <?php foreach ($sectors as $s): ?>
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card verdeling-card shadow-sm h-100">
                        <div
                            class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center"
                            data-sector-id="<?= (int)$s['id'] ?>"
                            data-dag-deel="<?= e((string)($s['dag_deel'] ?? 'week')) ?>"
                            data-max-dag1="<?= (int)($s['max_leerlingen_dag1'] ?? 0) ?>"
                            data-max-dag2="<?= (int)($s['max_leerlingen_dag2'] ?? 0) ?>"
                        >
                            <span><?= e($s['naam']) ?></span>
                            <div class="d-flex flex-column align-items-end gap-1">
                                <?php if ($isPoBezoek && (($s['dag_deel'] ?? 'week') === 'beide')): ?>
                                    <span class="badge bg-warning text-dark">Beide dagen</span>
                                    <span class="badge bg-light text-primary" id="sector-<?= $s['id'] ?>-count" data-max-leerlingen="<?= (int)$s['max_leerlingen'] ?>">
                                        Aantal: 0
                                    </span>
                                    <span class="badge bg-light text-primary" id="sector-<?= $s['id'] ?>-dag1-count">
                                        Dag 1: 0 / <?= (int)($s['max_leerlingen_dag1'] ?? 0) === 0 ? '∞' : (int)($s['max_leerlingen_dag1'] ?? 0) ?>
                                    </span>
                                    <span class="badge bg-light text-primary" id="sector-<?= $s['id'] ?>-dag2-count">
                                        Dag 2: 0 / <?= (int)($s['max_leerlingen_dag2'] ?? 0) === 0 ? '∞' : (int)($s['max_leerlingen_dag2'] ?? 0) ?>
                                    </span>
                                <?php elseif ($isPoBezoek && (($s['dag_deel'] ?? 'week') === 'dag1' || ($s['dag_deel'] ?? 'week') === 'dag2')): ?>
                                    <span class="badge bg-warning text-dark"><?= e(($s['dag_deel'] === 'dag1') ? 'Dag 1' : 'Dag 2') ?></span>
                                    <span class="badge bg-light text-primary" id="sector-<?= $s['id'] ?>-count" data-max-leerlingen="<?= (int)$s['max_leerlingen'] ?>">
                                        Aantal: 0
                                    </span>
                                    <span class="badge bg-light text-primary">
                                        Max: <?= (int)$s['max_leerlingen'] === 0 ? '∞' : (int)$s['max_leerlingen'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-primary" id="sector-<?= $s['id'] ?>-count" data-max-leerlingen="<?= (int)$s['max_leerlingen'] ?>">
                                        Aantal: 0
                                    </span>
                                    <span class="badge bg-light text-primary">
                                        Max: <?= (int)$s['max_leerlingen'] === 0 ? '∞' : (int)$s['max_leerlingen'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body p-2">
                            <?php if ($isPoBezoek && (($s['dag_deel'] ?? 'week') === 'beide')): ?>
                                <div class="small text-muted mb-1 fw-semibold">Dag 1</div>
                                <div class="dropzone mb-2" data-sector-id="<?= (int)$s['id'] ?>" data-variant="dag1" style="min-height:120px;"></div>
                                <div class="small text-muted mb-1 fw-semibold">Dag 2</div>
                                <div class="dropzone" data-sector-id="<?= (int)$s['id'] ?>" data-variant="dag2" style="min-height:120px;"></div>
                            <?php else: ?>
                                <div class="dropzone" data-sector-id="<?= (int)$s['id'] ?>" data-variant="" style="min-height:180px;"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Alle leerlingen (pool) -->
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white fw-semibold">
                <i class="bi bi-diagram-3"></i> Alle leerlingen (sleep naar een sector)
            </div>
            <div class="card-body p-2">
                <div class="dropzone verdeling-pool" id="studentsPool" data-sector-id="0">
                    <?php foreach ($leerlingen as $l):
                        $lid      = (int)$l['leerling_id'];
                        [$assignedSectorId, $assignedVariant] = parse_toegewezen_voorkeur($l['toegewezen_voorkeur'] ?? '');
                        $assignedRaw = trim((string)($l['toegewezen_voorkeur'] ?? ''));
                    ?>
                        <div class="student-wrapper"
                            data-leerling-id="<?= $lid ?>"
                            data-assigned="<?= e($assignedRaw) ?>"
                            data-assigned-sector-id="<?= (int)$assignedSectorId ?>"
                            data-assigned-variant="<?= e((string)($assignedVariant ?? '')) ?>">
                            <div class="student-item" draggable="true" data-leerling-id="<?= $lid ?>">
                                <div class="student-name">
                                    <?= e($stuNames[$lid]) ?>
                                </div>
                                <div class="student-origin text-muted small"><?= e($l['schoolnaam']) ?> - <?= e($l['klasaanduiding']) ?></div>

                                <?php if ($isPoBezoek && $assignedSectorId > 0 && (($sectorMetaMap[$assignedSectorId]['dag_deel'] ?? 'week') === 'beide')): ?>
                                    <div class="mt-1">
                                        <span class="badge bg-warning text-dark js-assigned-variant-badge" data-sector-id="<?= (int)$assignedSectorId ?>">
                                            <?= e($assignedVariant === 'dag2' ? 'Dag 2 variant' : 'Dag 1 variant') ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php
                                // Haal voorkeuren op
                                $v1 = (int)$l['voorkeur1'];
                                $v2 = (int)$l['voorkeur2'];
                                $v3 = (int)$l['voorkeur3'];

                                // Zet om naar namen
                                $vTxt = [];

                                if ($v1 && isset($sectorNaamMap[$v1])) $vTxt[] = $sectorNaamMap[$v1];
                                if ($v2 && isset($sectorNaamMap[$v2])) $vTxt[] = $sectorNaamMap[$v2];
                                if ($v3 && isset($sectorNaamMap[$v3])) $vTxt[] = $sectorNaamMap[$v3];

                                if (!empty($vTxt)) {
                                    echo '<div class="student-preferences text-muted small">(' . e(implode(', ', $vTxt)) . ')</div>';
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const sectorMeta = <?= json_encode($sectorMetaMap) ?>;
        const isPoBezoek = <?= json_encode($isPoBezoek) ?>;
        const dropzones = document.querySelectorAll('.dropzone');
        const students = document.querySelectorAll('.student-wrapper');
        const msgBox = document.getElementById('autoMessages');

        function parseAssignedValue(value) {
            const raw = String(value || '').trim();
            if (!raw) {
                return { sectorId: 0, variant: '' };
            }

            const parts = raw.split('|');
            if (parts.length > 1) {
                return {
                    sectorId: parseInt(parts[0], 10) || 0,
                    variant: (parts[1] || '').trim()
                };
            }

            return {
                sectorId: parseInt(raw, 10) || 0,
                variant: ''
            };
        }

        function formatAssignedValue(sectorId, variant) {
            const id = parseInt(sectorId, 10) || 0;
            const normalizedVariant = String(variant || '').trim();
            if (id <= 0) {
                return '';
            }
            if (normalizedVariant === 'dag1' || normalizedVariant === 'dag2') {
                return id + '|' + normalizedVariant;
            }
            return String(id);
        }

        function getSectorMeta(sectorId) {
            return sectorMeta[String(sectorId)] || sectorMeta[parseInt(sectorId, 10)] || null;
        }

        function isPoBothDaySector(sectorId) {
            const meta = getSectorMeta(sectorId);
            return !!(isPoBezoek && meta && meta.dag_deel === 'beide');
        }

        function getSectorDropzone(sectorId, variant) {
            const parsedSectorId = parseInt(sectorId, 10) || 0;
            if (parsedSectorId <= 0) {
                return document.querySelector('.dropzone[data-sector-id="0"]');
            }

            const normalizedVariant = String(variant || '').trim();
            if (normalizedVariant) {
                const specific = document.querySelector('.dropzone[data-sector-id="' + parsedSectorId + '"][data-variant="' + normalizedVariant + '"]');
                if (specific) {
                    return specific;
                }
            }

            return document.querySelector('.dropzone[data-sector-id="' + parsedSectorId + '"]');
        }

        function getSectorDropzones(sectorId) {
            return Array.from(document.querySelectorAll('.dropzone[data-sector-id="' + sectorId + '"]'));
        }

        function getSectorCounts(sectorId, ignoreEl) {
            const counts = { dag1: 0, dag2: 0, total: 0 };
            const dzList = getSectorDropzones(sectorId);
            dzList.forEach(function(dz) {
                const zoneVariant = String(dz.getAttribute('data-variant') || '').trim();
                dz.querySelectorAll('.student-wrapper').forEach(function(sw) {
                    if (ignoreEl && sw === ignoreEl) {
                        return;
                    }
                    counts.total++;

                    if (zoneVariant === 'dag1') {
                        counts.dag1++;
                    } else if (zoneVariant === 'dag2') {
                        counts.dag2++;
                    } else {
                        const parsed = parseAssignedValue(sw.getAttribute('data-assigned'));
                        if (parsed.variant === 'dag2') {
                            counts.dag2++;
                        } else if (parsed.variant === 'dag1') {
                            counts.dag1++;
                        }
                    }
                });
            });

            return counts;
        }

        function choosePoVariantForSector(sectorId, ignoreEl) {
            const meta = getSectorMeta(sectorId);
            if (!meta || meta.dag_deel !== 'beide') {
                return '';
            }

            const counts = getSectorCounts(sectorId, ignoreEl);
            const capDag1 = parseInt(meta.max_leerlingen_dag1 || 0, 10) || 0;
            const capDag2 = parseInt(meta.max_leerlingen_dag2 || 0, 10) || 0;
            const canUseDag1 = capDag1 <= 0 || counts.dag1 < capDag1;
            const canUseDag2 = capDag2 <= 0 || counts.dag2 < capDag2;

            if (canUseDag1 && !canUseDag2) return 'dag1';
            if (canUseDag2 && !canUseDag1) return 'dag2';
            if (!canUseDag1 && !canUseDag2) return '';

            const ratioDag1 = capDag1 > 0 ? (counts.dag1 / capDag1) : counts.dag1;
            const ratioDag2 = capDag2 > 0 ? (counts.dag2 / capDag2) : counts.dag2;
            if (ratioDag1 < ratioDag2) return 'dag1';
            if (ratioDag2 < ratioDag1) return 'dag2';
            return 'dag1';
        }

        function ensureVariantBadge(sw, sectorId, variant) {
            const item = sw.querySelector('.student-item');
            if (!item) return;

            const existingBadge = item.querySelector('.js-assigned-variant-badge');
            const showBadge = isPoBothDaySector(sectorId) && (variant === 'dag1' || variant === 'dag2');

            if (!showBadge) {
                if (existingBadge) existingBadge.remove();
                return;
            }

            const label = variant === 'dag2' ? 'Dag 2 variant' : 'Dag 1 variant';
            if (existingBadge) {
                existingBadge.textContent = label;
                return;
            }

            const wrap = document.createElement('div');
            wrap.className = 'mt-1';
            wrap.innerHTML = '<span class="badge bg-warning text-dark js-assigned-variant-badge">' + label + '</span>';
            item.appendChild(wrap);
        }

        function normalizeVariantForSector(sectorId, variant, ignoreEl) {
            const parsedSectorId = parseInt(sectorId, 10) || 0;
            if (parsedSectorId <= 0) {
                return '';
            }

            const normalizedVariant = String(variant || '').trim();
            if (!isPoBothDaySector(parsedSectorId)) {
                return '';
            }

            if (normalizedVariant === 'dag1' || normalizedVariant === 'dag2') {
                return normalizedVariant;
            }

            return choosePoVariantForSector(parsedSectorId, ignoreEl);
        }

        function placeStudent(sw, sectorId, variant, ignoreEl) {
            const parsedSectorId = parseInt(sectorId, 10) || 0;
            const finalVariant = normalizeVariantForSector(parsedSectorId, variant, ignoreEl);
            const target = getSectorDropzone(parsedSectorId, finalVariant);
            if (!target) {
                return;
            }

            target.appendChild(sw);
            assignStudentToSectorElement(sw, parsedSectorId, finalVariant);
        }

        function assignStudentToSectorElement(sw, sectorId, variant) {
            const parsedSectorId = parseInt(sectorId, 10) || 0;
            const normalizedVariant = normalizeVariantForSector(parsedSectorId, variant, sw);
            sw.setAttribute('data-assigned-sector-id', parsedSectorId);
            sw.setAttribute('data-assigned-variant', normalizedVariant);
            sw.setAttribute('data-assigned', formatAssignedValue(parsedSectorId, normalizedVariant));
            ensureVariantBadge(sw, parsedSectorId, normalizedVariant);
        }

        function updateStudentCardPlacement(sw) {
            const parsed = parseAssignedValue(sw.getAttribute('data-assigned'));
            const assignedSectorId = parsed.sectorId;
            const assignedVariant = parsed.variant;
            placeStudent(sw, assignedSectorId, assignedVariant, sw);
        }

        // aangepaste showMessage
        function showMessage(html, type = 'info') {
            if (!msgBox) return;
            msgBox.innerHTML = `
                <div class="alert alert-${type}" role="alert">
                    ${html}
                </div>`;
        }

        // Plaats leerlingen met toegewezen sector direct in de juiste kolom.
        students.forEach(sw => {
            updateStudentCardPlacement(sw);
        });

        // Update counts voor alle sectoren
        function updateAllSectorCounts() {
            Object.keys(sectorMeta).forEach(function(sectorId) {
                updateSectorCount(sectorId);
            });
        }

        // Functie om het aantal leerlingen in een sector bij te werken
        function updateSectorCount(sectorId) {
            const counts = getSectorCounts(sectorId);
            const studentCount = counts.total;
            const countBadge = document.getElementById('sector-' + sectorId + '-count');
            if (countBadge) {
                countBadge.textContent = 'Aantal: ' + studentCount;

                // Check of het aantal groter is dan het maximum
                const maxLeerlingen = parseInt(countBadge.getAttribute('data-max-leerlingen'), 10);
                if (maxLeerlingen > 0 && studentCount > maxLeerlingen) {
                    countBadge.classList.add('text-danger');
                } else {
                    countBadge.classList.remove('text-danger');
                }
            }

            const meta = getSectorMeta(sectorId);
            if (meta && meta.dag_deel === 'beide') {
                const dag1Badge = document.getElementById('sector-' + sectorId + '-dag1-count');
                const dag2Badge = document.getElementById('sector-' + sectorId + '-dag2-count');
                const capDag1 = parseInt(meta.max_leerlingen_dag1, 10) || 0;
                const capDag2 = parseInt(meta.max_leerlingen_dag2, 10) || 0;
                if (dag1Badge) {
                    dag1Badge.textContent = 'Dag 1: ' + counts.dag1 + ' / ' + (capDag1 > 0 ? capDag1 : '∞');
                    dag1Badge.classList.toggle('text-danger', capDag1 > 0 && counts.dag1 > capDag1);
                }
                if (dag2Badge) {
                    dag2Badge.textContent = 'Dag 2: ' + counts.dag2 + ' / ' + (capDag2 > 0 ? capDag2 : '∞');
                    dag2Badge.classList.toggle('text-danger', capDag2 > 0 && counts.dag2 > capDag2);
                }
            }
        }

        // Eerste keer alle counts updaten
        updateAllSectorCounts();

        let dragged = null;

        document.addEventListener('dragstart', e => {
            const sw = e.target.closest('.student-wrapper');
            if (!sw) return;
            dragged = sw;
            sw.querySelector('.student-item')?.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            try {
                e.dataTransfer.setData('text/plain', sw.getAttribute('data-leerling-id'));
            } catch {}
        });

        document.addEventListener('dragend', () => {
            if (dragged) {
                dragged.querySelector('.student-item')?.classList.remove('dragging');
            }
            dragged = null;
            dropzones.forEach(dz => {
                const card = dz.closest('.card');
                if (card) card.classList.remove('verdeling-drop-hover');
            });
        });

        // Dropzone events
        dropzones.forEach(dz => {
            dz.addEventListener('dragover', e => {
                e.preventDefault();
                const card = dz.closest('.card');
                if (card) card.classList.add('verdeling-drop-hover');
                e.dataTransfer.dropEffect = 'move';
            });
            dz.addEventListener('dragenter', e => {
                e.preventDefault();
                const card = dz.closest('.card');
                if (card) card.classList.add('verdeling-drop-hover');
            });
            dz.addEventListener('dragleave', () => {
                const card = dz.closest('.card');
                if (card) card.classList.remove('verdeling-drop-hover');
            });
            dz.addEventListener('drop', e => {
                e.preventDefault();
                const card = dz.closest('.card');
                if (card) card.classList.remove('verdeling-drop-hover');
                if (!dragged) return;

                const oldAssignment = parseAssignedValue(dragged.getAttribute('data-assigned'));
                const oldSectorId = oldAssignment.sectorId;
                const newSectorId = dz.getAttribute('data-sector-id');
                const zoneVariant = String(dz.getAttribute('data-variant') || '').trim();
                const newVariant = newSectorId !== '0' ? zoneVariant : '';

                placeStudent(dragged, newSectorId, newVariant, dragged);

                // Update the tellers van beide sectoren
                if (oldSectorId && oldSectorId !== '0') {
                    updateSectorCount(oldSectorId);
                }
                if (newSectorId && newSectorId !== '0') {
                    updateSectorCount(newSectorId);
                }
            });
        });

        // ---------- Automatisch verdelen ----------
        document.getElementById('btnAuto').addEventListener('click', function() {
            if (!confirm('Weet je zeker dat je automatisch wilt verdelen?')) return;

            fetch('verdeling.php?bezoek_id=<?= (int)$bezoek_id ?>&action=auto', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(json => {
                    if (!json.success) {
                        showMessage('Er is een fout opgetreden bij het automatisch verdelen.', 'danger');
                        return;
                    }

                    // Map leerling_id -> DOM-element
                    const map = {};
                    document.querySelectorAll('.student-wrapper').forEach(sw => {
                        map[sw.getAttribute('data-leerling-id')] = sw;
                    });

                    // Alles eerst naar de pool
                    const pool = document.querySelector('.dropzone[data-sector-id="0"]');
                    if (pool) {
                        Object.values(map).forEach(sw => {
                            pool.appendChild(sw);
                            assignStudentToSectorElement(sw, 0, '');
                        });
                    }

                    const assignmentMap = json.assignments || {};

                    Object.keys(assignmentMap).forEach(function(lid) {
                        const el = map[lid];
                        if (!el) return;

                        const parsed = parseAssignedValue(assignmentMap[lid]);
                        placeStudent(el, parsed.sectorId, parsed.variant, el);
                    });

                    if (!json.assignments && Array.isArray(json.sectors)) {
                        json.sectors.forEach(s => {
                            const dz = getSectorDropzone(s.id);
                            if (!dz) return;
                            s.assigned.forEach(item => {
                                const studentId = typeof item === 'object' ? item.student_id : item;
                                const assignmentValue = typeof item === 'object' && item.assignment ? item.assignment : String(s.id);
                                const el = map[studentId];
                                if (el) {
                                    const parsed = parseAssignedValue(assignmentValue);
                                    placeStudent(el, parsed.sectorId, parsed.variant, el);
                                }
                            });
                            updateSectorCount(s.id);
                        });
                    }

                    updateAllSectorCounts();

                    // Meld leerlingen die niet geplaatst konden worden
                    if (json.unassigned && json.unassigned.length > 0) {
                        let html = '<strong>Niet automatisch ingedeeld:</strong><ul class="mb-0 mt-2">';
                        json.unassigned.forEach(u => {
                            html += `<li><strong>${u.naam}</strong>: ${u.reden}</li>`;
                        });
                        html += '</ul>';
                        showMessage(html, 'warning');
                    } else {
                        showMessage('Alle leerlingen zijn succesvol automatisch ingedeeld.', 'success');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showMessage('Er is een fout opgetreden bij het automatisch verdelen.', 'danger');
                });
        });

        // ---------- Opslaan ----------
        document.getElementById('btnSave').addEventListener('click', function() {
            if (!confirm('Opslaan schrijft de huidige verdeling naar de database. Ga je akkoord?')) return;

            const assignments = {};
            document.querySelectorAll('.dropzone').forEach(dz => {
                const sid = dz.getAttribute('data-sector-id');
                dz.querySelectorAll('.student-wrapper').forEach(sw => {
                    const lid = sw.getAttribute('data-leerling-id');
                    const assignedValue = (sw.getAttribute('data-assigned') || '').trim();
                    assignments[lid] = sid === '0' ? null : (assignedValue || String(sid));
                });
            });

            fetch('verdeling.php?bezoek_id=<?= (int)$bezoek_id ?>&action=save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        assignments
                    })
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        showMessage('Verdeling succesvol opgeslagen.', 'success');
                    } else {
                        showMessage('Fout bij opslaan: ' + (j.message || ''), 'danger');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showMessage('Er is een fout opgetreden bij het opslaan van de verdeling.', 'danger');
                });
        });

    })();
</script>

<?php require 'includes/footer.php'; ?>