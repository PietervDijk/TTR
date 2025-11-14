<?php
require 'includes/config.php';
require 'includes/header.php';

// admin check
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// klas_id verplicht
if (!isset($_GET['klas_id']) || !ctype_digit($_GET['klas_id'])) {
    header('Location: scholen.php');
    exit;
}
$klas_id = (int)$_GET['klas_id'];

// helper
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ----------------- AJAX endpoints -----------------
// Auto-verdeling (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'auto') {
    header('Content-Type: application/json; charset=utf-8');

    // haal klas_voorkeur: id, naam, max_leerlingen
    $stmt = $conn->prepare("SELECT id, naam, COALESCE(max_leerlingen, 0) AS max_leerlingen FROM klas_voorkeur WHERE klas_id=? AND actief=1 ORDER BY volgorde ASC");
    $stmt->bind_param("i", $klas_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $sectors = [];
    while ($r = $res->fetch_assoc()) {
        $sectors[(int)$r['id']] = [
            'id' => (int)$r['id'],
            'naam' => $r['naam'],
            'max' => (int)$r['max_leerlingen'],
            'assigned' => []
        ];
    }
    $stmt->close();

    // haal leerlingen met voorkeuren (prioriteit: voorkeur1, voorkeur2, voorkeur3)
    $stmt = $conn->prepare("SELECT leerling_id, voornaam, tussenvoegsel, achternaam, voorkeur1, voorkeur2, voorkeur3 FROM leerling WHERE klas_id=? ORDER BY achternaam ASC, voornaam ASC");
    $stmt->bind_param("i", $klas_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $students = [];
    while ($r = $res->fetch_assoc()) {
        $students[] = $r;
    }
    $stmt->close();

    // eenvoudige verdeling algoritme:
    // eerst pass: probeer voorkeur1 voor alle leerlingen
    // daarna voorkeur2 voor zij die nog niet geplaatst
    // daarna voorkeur3 ...
    // we respecteren max capaciteit (max == 0 betekent geen limiet)
    $unassigned = [];
    foreach ($students as $stu) {
        $placed = false;
        for ($p = 1; $p <= 3; $p++) {
            $key = 'voorkeur' . $p;
            $val = trim((string)$stu[$key]);
            if ($val === '') continue;
            if (!ctype_digit($val)) continue;
            $sid = (int)$val;
            if (!isset($sectors[$sid])) continue; // niet geldig voor deze klas
            $max = $sectors[$sid]['max'];
            $count = count($sectors[$sid]['assigned']);
            if ($max === 0 || $count < $max) {
                $sectors[$sid]['assigned'][] = (int)$stu['leerling_id'];
                $placed = true;
                break;
            }
        }
        if (!$placed) $unassigned[] = (int)$stu['leerling_id'];
    }

    // Resultaat teruggeven (we sturen ids en namen)
    $out = [
        'success' => true,
        'sectors' => [],
        'unassigned' => $unassigned
    ];
    // wikkel sectors data
    foreach ($sectors as $sid => $s) {
        $out['sectors'][] = [
            'id' => $sid,
            'naam' => $s['naam'],
            'assigned' => $s['assigned'],
            'max' => $s['max']
        ];
    }
    echo json_encode($out);
    exit;
}

// Opslaan van toewijzingen (POST, action=save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save') {
    header('Content-Type: application/json; charset=utf-8');

    // verwacht payload: assignments => { leerling_id: sector_id or 0/null }
    // we kunnen via form-data ook arrays sturen; hier gebruiken we JSON POST body
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['assignments'])) {
        echo json_encode(['success' => false, 'message' => 'Ongeldige payload']);
        exit;
    }
    $assignments = $data['assignments'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE leerling SET toegewezen_voorkeur = ? WHERE leerling_id = ? AND klas_id = ?");
        foreach ($assignments as $leerling_id => $sector_id) {
            $leerling_id = (int)$leerling_id;
            $sector_id = $sector_id === null || $sector_id === '' ? null : (int)$sector_id;
            // we willen null opslaan als niet toegewezen
            if ($sector_id === null) {
                $stmt->bind_param("sis", $sector_id, $leerling_id, $klas_id);
                // bind fails when param type mismatch for null; use explicit query when null
                $q = $conn->prepare("UPDATE leerling SET toegewezen_voorkeur = NULL WHERE leerling_id=? AND klas_id=?");
                $q->bind_param("ii", $leerling_id, $klas_id);
                $q->execute();
                $q->close();
            } else {
                $stmt->bind_param("iii", $sector_id, $leerling_id, $klas_id);
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

// ----------------- Normale pagina rendering -----------------

// haal klas info
$stmt = $conn->prepare("SELECT k.*, s.schoolnaam FROM klas k JOIN school s ON s.school_id = k.school_id WHERE k.klas_id=?");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$klas = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$klas) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Klas niet gevonden</div></div>";
    require 'includes/footer.php';
    exit;
}

// haal sectoren (klas_voorkeur) - deze behandelen we als 'sectoren'
$stmt = $conn->prepare("SELECT id, naam, COALESCE(max_leerlingen, 0) AS max_leerlingen FROM klas_voorkeur WHERE klas_id = ? AND actief=1 ORDER BY volgorde ASC");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$res = $stmt->get_result();
$sectors = [];
while ($r = $res->fetch_assoc()) {
    $sectors[] = $r;
}
$stmt->close();

// haal leerlingen
$stmt = $conn->prepare("SELECT leerling_id, voornaam, tussenvoegsel, achternaam, voorkeur1, voorkeur2, voorkeur3, toegewezen_voorkeur FROM leerling WHERE klas_id = ? ORDER BY achternaam ASC, voornaam ASC");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$leerlingen = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// maak een map leerling_id -> display name
$stuNames = [];
foreach ($leerlingen as $l) {
    $name = trim($l['voornaam'] . ' ' . ($l['tussenvoegsel'] ?: '') . ' ' . $l['achternaam']);
    $stuNames[(int)$l['leerling_id']] = $name;
}

// HTML output
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Verdeling leerlingen - <?= e($klas['klasaanduiding']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8ff; min-height:100vh;}
        .col-sector { min-height: 260px; border-radius: 12px; background: #fff; padding: 12px; box-shadow: 0 4px 12px rgba(20,30,80,.06);}
        .sector-header { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px;}
        .student-item { padding:8px 10px; border-radius:8px; margin-bottom:8px; background:#f8f9fb; cursor:move; border:1px solid #e6e9f2; }
        .student-item.dragging { opacity:0.5; transform: scale(.98); }
        .col-drop-hover { outline: 3px dashed #4666ff33; }
        .capacity { font-size: .85rem; color:#666; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h3 class="mb-0">Verdeling – klas <?= e($klas['klasaanduiding']) ?></h3>
            <div class="text-muted small"><?= e($klas['schoolnaam']) ?> — Leerjaar <?= e($klas['leerjaar']) ?></div>
        </div>
        <div class="d-flex gap-2">
            <a href="leerlingen.php?klas_id=<?= $klas_id ?>" class="btn btn-outline-secondary">Terug naar leerlingen</a>
            <button id="btnAuto" class="btn btn-primary">Automatisch verdelen</button>
            <button id="btnSave" class="btn btn-success">Opslaan verdeling</button>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($sectors as $s): ?>
                    <div class="col-md-4">
                        <div class="col-sector" data-sector-id="<?= (int)$s['id'] ?>">
                            <div class="sector-header">
                                <strong><?= e($s['naam']) ?></strong>
                                <div class="text-end">
                                    <div class="capacity">Max: <input type="number" class="form-control form-control-sm input-max" style="width:80px; display:inline-block;" data-sector-id="<?= (int)$s['id'] ?>" value="<?= (int)$s['max_leerlingen'] === 0 ? '' : (int)$s['max_leerlingen'] ?>" placeholder="0=geen limiet"></div>
                                </div>
                            </div>
                            <div class="dropzone" style="min-height:120px;" data-sector-id="<?= (int)$s['id'] ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Unassigned column -->
                <div class="col-md-4">
                    <div class="col-sector" data-sector-id="0">
                        <div class="sector-header">
                            <strong>Niet toegewezen</strong>
                            <div class="capacity">—</div>
                        </div>
                        <div class="dropzone" style="min-height:120px;" data-sector-id="0"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- lijst met alle leerlingen (source pool) -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h6>Alle leerlingen (sleep naar kolom) — klik en sleep een naam naar een sector</h6>
            <div class="row g-3 mt-2" id="studentsPool">
                <?php foreach ($leerlingen as $l):
                    $lid = (int)$l['leerling_id'];
                    $assigned = $l['toegewezen_voorkeur'] !== null && trim((string)$l['toegewezen_voorkeur']) !== '' ? (int)$l['toegewezen_voorkeur'] : 0;
                    // If already assigned, we will show them in that sector later; otherwise show in pool
                    ?>
                    <div class="col-md-3 student-wrapper" data-leerling-id="<?= $lid ?>" data-assigned="<?= $assigned ?>">
                        <div class="student-item" draggable="true" data-leerling-id="<?= $lid ?>">
                            <?= e($stuNames[$lid]) ?>
                            <div class="text-muted small">Keuzes: <?= e($l['voorkeur1']) ?>, <?= e($l['voorkeur2']) ?>, <?= e($l['voorkeur3']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>



</body>
</html>

<?php
require 'includes/footer.php';
?>
