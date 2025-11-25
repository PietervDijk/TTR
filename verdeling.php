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

// klas_id verplicht
if (!isset($_GET['klas_id']) || !ctype_digit($_GET['klas_id'])) {
    header('Location: scholen.php');
    exit;
}
$klas_id = (int)$_GET['klas_id'];

// helper
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ----------------- AJAX endpoints -----------------
$isAjax = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && in_array($_GET['action'], ['auto', 'save'], true)
);

// Auto-verdeling (POST)
if ($isAjax && $_GET['action'] === 'auto') {
    header('Content-Type: application/json; charset=utf-8');

    // haal klas_voorkeur: id, naam, max_leerlingen
    $stmt = $conn->prepare("
        SELECT id, naam, COALESCE(max_leerlingen,0) AS max_leerlingen
        FROM klas_voorkeur
        WHERE klas_id=? AND actief=1
        ORDER BY volgorde ASC
    ");
    $stmt->bind_param("i", $klas_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $sectors = [];
    while ($r = $res->fetch_assoc()) {
        $sectors[(int)$r['id']] = [
            'id'       => (int)$r['id'],
            'naam'     => $r['naam'],
            'max'      => (int)$r['max_leerlingen'],
            'assigned' => []
        ];
    }
    $stmt->close();

    // haal leerlingen met voorkeuren
    $stmt = $conn->prepare("
        SELECT leerling_id, voornaam, tussenvoegsel, achternaam,
               voorkeur1, voorkeur2, voorkeur3
        FROM leerling
        WHERE klas_id=?
        ORDER BY achternaam ASC, voornaam ASC
    ");
    $stmt->bind_param("i", $klas_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $students = [];
    while ($r = $res->fetch_assoc()) {
        $students[] = $r;
    }
    $stmt->close();

    // eenvoudige verdeling algoritme + reden bij mislukken
    $unassigned = [];
    foreach ($students as $stu) {
        $placed  = false;
        $reasons = [];

        $fullName = trim(
            $stu['voornaam'] . ' ' .
                ($stu['tussenvoegsel'] ?: '') . ' ' .
                $stu['achternaam']
        );

        for ($p = 1; $p <= 3; $p++) {
            $key = 'voorkeur' . $p;
            $val = trim((string)$stu[$key]);

            if ($val === '') {
                $reasons[] = "Voorkeur {$p} is niet ingevuld.";
                continue;
            }
            if (!ctype_digit($val)) {
                $reasons[] = "Voorkeur {$p} bevat een ongeldige sector (‘{$val}’).";
                continue;
            }

            $sid = (int)$val;

            if (!isset($sectors[$sid])) {
                $reasons[] = "Voorkeur {$p} verwijst naar een sector die niet (meer) bestaat.";
                continue;
            }

            $max   = $sectors[$sid]['max'];
            $count = count($sectors[$sid]['assigned']);

            if ($max !== 0 && $count >= $max) {
                $reasons[] = "Sector {$sectors[$sid]['naam']} (voorkeur {$p}) zit vol.";
                continue;
            }

            // plek gevonden: toewijzen en klaar
            $sectors[$sid]['assigned'][] = (int)$stu['leerling_id'];
            $placed = true;
            break;
        }

        if (!$placed) {
            if (empty($reasons)) {
                $reasons[] = "Er zijn geen geldige voorkeuren ingevuld.";
            }

            $unassigned[] = [
                'id'    => (int)$stu['leerling_id'],
                'naam'  => $fullName,
                'reden' => implode(' ', $reasons),
            ];
        }
    }

    $out = ['success' => true, 'sectors' => [], 'unassigned' => $unassigned];
    foreach ($sectors as $sid => $s) {
        $out['sectors'][] = [
            'id'       => $sid,
            'naam'     => $s['naam'],
            'assigned' => $s['assigned'],
            'max'      => $s['max']
        ];
    }

    echo json_encode($out);
    exit;
}

// Opslaan toewijzingen (POST, action=save)
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
            UPDATE leerling
            SET toegewezen_voorkeur=?
            WHERE leerling_id=? AND klas_id=?
        ");

        foreach ($assignments as $leerling_id => $sector_id) {
            $leerling_id = (int)$leerling_id;

            if ($sector_id === null || $sector_id === '') {
                $q = $conn->prepare("
                    UPDATE leerling
                    SET toegewezen_voorkeur=NULL
                    WHERE leerling_id=? AND klas_id=?
                ");
                $q->bind_param("ii", $leerling_id, $klas_id);
                $q->execute();
                $q->close();
            } else {
                $sector_id = (int)$sector_id;
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

// ----------------- Pagina rendering -----------------

require 'includes/header.php';

// klas info
$stmt = $conn->prepare("
    SELECT k.*, s.schoolnaam
    FROM klas k
    JOIN school s ON s.school_id=k.school_id
    WHERE k.klas_id=?
");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$klas = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$klas) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Klas niet gevonden</div></div>";
    require 'includes/footer.php';
    exit;
}

// sectoren
$stmt = $conn->prepare("
    SELECT id, naam, COALESCE(max_leerlingen,0) AS max_leerlingen
    FROM klas_voorkeur
    WHERE klas_id=? AND actief=1
    ORDER BY volgorde ASC
");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$res   = $stmt->get_result();
$sectors = [];
while ($r = $res->fetch_assoc()) {
    $sectors[] = $r;
}
$stmt->close();

// leerlingen
$stmt = $conn->prepare("
    SELECT leerling_id, voornaam, tussenvoegsel, achternaam,
           voorkeur1, voorkeur2, voorkeur3, toegewezen_voorkeur
    FROM leerling
    WHERE klas_id=?
    ORDER BY achternaam ASC, voornaam ASC
");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$leerlingen = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stuNames = [];
foreach ($leerlingen as $l) {
    $stuNames[(int)$l['leerling_id']] =
        trim($l['voornaam'] . ' ' . ($l['tussenvoegsel'] ?: '') . ' ' . $l['achternaam']);
}
?>

<style>
    body {
        background: #f6f8ff;
        min-height: 100vh;
    }

    .col-sector {
        min-height: 260px;
        border-radius: 12px;
        background: #fff;
        padding: 12px;
        box-shadow: 0 4px 12px rgba(20, 30, 80, .06);
    }

    .sector-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }

    .student-item {
        padding: 8px 10px;
        border-radius: 8px;
        margin-bottom: 8px;
        background: #f8f9fb;
        cursor: move;
        border: 1px solid #e6e9f2;
    }

    .student-item.dragging {
        opacity: 0.5;
        transform: scale(.98);
    }

    .col-drop-hover {
        outline: 3px dashed #4666ff33;
    }

    .capacity {
        font-size: .85rem;
        color: #666;
    }

    .student-wrapper {
        width: 100%;
    }
</style>

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

    <!-- Hier komen meldingen (Bootstrap alerts) -->
    <div id="autoMessages" class="mb-3"></div>

    <!-- Sectoren / werelden -->
    <div class="row g-3 mb-4">
        <?php foreach ($sectors as $s): ?>
            <div class="col-md-4 mt-3">
                <div class="col-sector" data-sector-id="<?= (int)$s['id'] ?>">
                    <div class="sector-header">
                        <strong><?= e($s['naam']) ?></strong>
                        <div class="capacity">
                            Max: <?= (int)$s['max_leerlingen'] === 0 ? '∞' : (int)$s['max_leerlingen'] ?>
                        </div>
                    </div>
                    <div class="dropzone" data-sector-id="<?= (int)$s['id'] ?>" style="min-height:120px"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Alle leerlingen (pool) -->
    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <h6>Alle leerlingen (sleep naar een sector)</h6>
            <div class="mt-2 dropzone" id="studentsPool" data-sector-id="0">
                <?php foreach ($leerlingen as $l):
                    $lid      = (int)$l['leerling_id'];
                    $assigned = $l['toegewezen_voorkeur'] !== null && trim((string)$l['toegewezen_voorkeur']) !== ''
                        ? (int)$l['toegewezen_voorkeur']
                        : 0;
                ?>
                    <div class="student-wrapper w-100 mb-2"
                        data-leerling-id="<?= $lid ?>"
                        data-assigned="<?= $assigned ?>">
                        <div class="student-item text-truncate"
                            draggable="true"
                            data-leerling-id="<?= $lid ?>">
                            <?= e($stuNames[$lid]) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const dropzones = document.querySelectorAll('.dropzone');
        const students = document.querySelectorAll('.student-wrapper');
        const msgBox = document.getElementById('autoMessages');

        // aangepaste showMessage: GEEN close-knop meer
        function showMessage(html, type = 'info') {
            if (!msgBox) return;
            msgBox.innerHTML = `
                <div class="alert alert-${type}" role="alert">
                    ${html}
                </div>`;
        }

        // Plaats leerlingen met toegewezen sector direct in de juiste kolom.
        students.forEach(sw => {
            const assigned = sw.getAttribute('data-assigned') || '0';
            const dz = document.querySelector('.dropzone[data-sector-id="' + assigned + '"]');
            if (dz) dz.appendChild(sw);
        });

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
            dropzones.forEach(dz => dz.parentElement.classList.remove('col-drop-hover'));
        });

        dropzones.forEach(dz => {
            dz.addEventListener('dragover', e => {
                e.preventDefault();
                if (dz.closest('.col-sector')) {
                    dz.parentElement.classList.add('col-drop-hover');
                }
                e.dataTransfer.dropEffect = 'move';
            });
            dz.addEventListener('dragenter', e => {
                e.preventDefault();
                if (dz.closest('.col-sector')) {
                    dz.parentElement.classList.add('col-drop-hover');
                }
            });
            dz.addEventListener('dragleave', () => {
                if (dz.closest('.col-sector')) {
                    dz.parentElement.classList.remove('col-drop-hover');
                }
            });
            dz.addEventListener('drop', e => {
                e.preventDefault();
                if (dz.closest('.col-sector')) {
                    dz.parentElement.classList.remove('col-drop-hover');
                }
                if (!dragged) return;
                dz.appendChild(dragged);
                dragged.setAttribute('data-assigned', dz.getAttribute('data-sector-id'));
            });
        });

        // ---------- Automatisch verdelen ----------
        document.getElementById('btnAuto').addEventListener('click', function() {
            if (!confirm('Weet je zeker dat je automatisch wilt verdelen?')) return;

            fetch('verdeling.php?klas_id=<?= $klas_id ?>&action=auto', {
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
                            sw.setAttribute('data-assigned', 0);
                        });
                    }

                    // Sectoren vullen volgens JSON
                    json.sectors.forEach(s => {
                        const dz = document.querySelector('.dropzone[data-sector-id="' + s.id + '"]');
                        if (!dz) return;
                        s.assigned.forEach(lid => {
                            const el = map[lid];
                            if (el) {
                                dz.appendChild(el);
                                el.setAttribute('data-assigned', s.id);
                            }
                        });
                    });

                    // Meld leerlingen die niet geplaatst konden worden
                    if (json.unassigned && json.unassigned.length > 0) {
                        let html = '<strong>Niet automatisch ingedeeld:</strong><br><ul class="mb-0">';
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
                    assignments[lid] = sid === '0' ? null : parseInt(sid, 10);
                });
            });

            fetch('verdeling.php?klas_id=<?= $klas_id ?>&action=save', {
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