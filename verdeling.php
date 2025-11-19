we hebben net bijde klassen pagina dat je bij de max leerlingen kan toevoegen kan je nu dit pagina verbeteren eerst wat ik wil
dat je max leerlingen hoeft niet meer testaan wat hij staat all in de klas pagina kan je  bij de deze pagina de automaties verdelen fixen en laten werken
en de  atuomatiesch verdelen bedoel ik daar mee dat de kinderen worden verdellen in verschlinde sectoren legt er aan wat ze in de eeste voorkeur
van hun gekozen en dan wordt gekijken als de sector vul dan worden ze gestuurt naar de tweede kuzen van hun

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

<script>
    (function(){
        // build a map of students that are already assigned -> move them into dropzones
        const students = document.querySelectorAll('.student-wrapper');
        const pool = document.getElementById('studentsPool');
        // dropzone elements
        const dropzones = Array.from(document.querySelectorAll('.dropzone'));

        // helper: create item element
        function makeItem(node) {
            return node.querySelector('.student-item');
        }

        // place initially assigned students into correct dropzones
        students.forEach(sw => {
            const assigned = sw.getAttribute('data-assigned') || '0';
            const lid = sw.getAttribute('data-leerling-id');
            const item = makeItem(sw);
            if (assigned && assigned !== '0') {
                const dz = document.querySelector('.dropzone[data-sector-id="'+assigned+'"]');
                if (dz) {
                    dz.appendChild(sw);
                } else {
                    pool.appendChild(sw);
                }
            } else {
                pool.appendChild(sw);
            }
        });

        // Drag and drop logic
        let dragged = null;
        document.addEventListener('dragstart', function(e){
            const it = e.target.closest('.student-wrapper');
            if (!it) return;
            dragged = it;
            it.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', it.getAttribute('data-leerling-id')); } catch(e){}
        });
        document.addEventListener('dragend', function(e){
            if (dragged) dragged.classList.remove('dragging');
            dragged = null;
            dropzones.forEach(dz => dz.parentElement.classList.remove('col-drop-hover'));
        });

        dropzones.forEach(dz => {
            dz.addEventListener('dragover', function(e){
                e.preventDefault();
                dz.parentElement.classList.add('col-drop-hover');
                e.dataTransfer.dropEffect = 'move';
            });
            dz.addEventListener('dragleave', function(e){
                dz.parentElement.classList.remove('col-drop-hover');
            });
            dz.addEventListener('drop', function(e){
                e.preventDefault();
                dz.parentElement.classList.remove('col-drop-hover');
                if (!dragged) return;
                dz.appendChild(dragged);
            });
        });

        // Auto-distribute click
        document.getElementById('btnAuto').addEventListener('click', function(){
            if (!confirm('Weet je zeker dat je automatisch wilt verdelen op basis van voorkeuren? Bestaande handmatige toewijzingen worden overschreven.')) return;
            fetch(window.location.pathname + '?klas_id=<?= $klas_id ?>&action=auto', {
                method: 'POST',
                headers: { 'Accept': 'application/json' }
            }).then(r=>r.json()).then(json=>{
                if (!json.success) { alert('Fout bij automatisch verdelen'); return; }
                // clear dropzones
                document.querySelectorAll('.dropzone').forEach(dz=>dz.innerHTML='');
                // move assigned
                json.sectors.forEach(s=>{
                    const dz = document.querySelector('.dropzone[data-sector-id="'+s.id+'"]');
                    if (!dz) return;
                    s.assigned.forEach(lid=>{
                        const el = document.querySelector('.student-wrapper[data-leerling-id="'+lid+'"]');
                        if (el) dz.appendChild(el);
                    });
                });
                // unassigned -> dropzone 0
                const dz0 = document.querySelector('.dropzone[data-sector-id="0"]');
                if (dz0) {
                    json.unassigned.forEach(lid=>{
                        const el = document.querySelector('.student-wrapper[data-leerling-id="'+lid+'"]');
                        if (el) dz0.appendChild(el);
                    });
                }
            }).catch(err=>{
                console.error(err);
                alert('Er is iets misgegaan bij automatisch verdelen.');
            });
        });

        // Save button: collect assignments and send JSON
        document.getElementById('btnSave').addEventListener('click', function(){
            if (!confirm('Opslaan houdt in dat de huidige verdeling naar de database wordt geschreven. Ga je akkoord?')) return;

            // update max_leerlingen values first (we save them via a form post)
            // but to keep it simple: we'll post max values inline via a small POST call
            const maxInputs = document.querySelectorAll('.input-max');
            const maxPayload = {};
            maxInputs.forEach(inp => {
                const sid = inp.getAttribute('data-sector-id');
                const val = inp.value === '' ? 0 : parseInt(inp.value,10) || 0;
                maxPayload[sid] = val;
            });

            // assignments
            const assignments = {};
            document.querySelectorAll('.dropzone').forEach(dz=>{
                const sid = dz.getAttribute('data-sector-id');
                Array.from(dz.querySelectorAll('.student-wrapper')).forEach(sw=>{
                    const lid = sw.getAttribute('data-leerling-id');
                    assignments[lid] = sid === '0' ? null : parseInt(sid,10);
                });
            });

            // step1: save max_leerlingen via synchronous POST form (not JSON)
            // build form data
            const formData = new FormData();
            formData.append('save_max','1');
            for (const k in maxPayload) {
                formData.append('max['+k+']', maxPayload[k]);
            }

            fetch(window.location.pathname + '?klas_id=<?= $klas_id ?>', {
                method: 'POST',
                body: formData
            }).then(resp => resp.text()).then(() => {
                // step2: save assignments (JSON endpoint)
                fetch(window.location.pathname + '?klas_id=<?= $klas_id ?>&action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ assignments: assignments })
                }).then(r=>r.json()).then(j=>{
                    if (j.success) {
                        alert('Verdeling succesvol opgeslagen.');
                        // reload page to reflect updated assigned values
                        location.reload();
                    } else {
                        alert('Fout bij opslaan: ' + (j.message || ''));
                    }
                }).catch(err=>{
                    console.error(err);
                    alert('Er is iets misgegaan bij opslaan.');
                });
            }).catch(err=>{
                console.error(err);
                alert('Fout bij opslaan van capaciteiten.');
            });

        });

    })();
</script>

</body>
</html>

<?php
require 'includes/footer.php';
?>
