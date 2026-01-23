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

// ----------------- AUTO VERDELING (EERLIJK) -----------------
if ($isAjax && $_GET['action'] === 'auto') {
    header('Content-Type: application/json; charset=utf-8');

    // Sectoren
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
    $capacity = [];
    while ($r = $res->fetch_assoc()) {
        $sid = (int)$r['id'];
        $sectors[$sid] = [
            'id'       => $sid,
            'naam'     => $r['naam'],
            'max'      => (int)$r['max_leerlingen'],
            'assigned' => []
        ];
        $capacity[$sid] = (int)$r['max_leerlingen'];
    }
    $stmt->close();

    // Leerlingen
    $stmt = $conn->prepare("
        SELECT leerling_id, voornaam, tussenvoegsel, achternaam,
               voorkeur1, voorkeur2, voorkeur3
        FROM leerling
        WHERE klas_id=?
    ");
    $stmt->bind_param("i", $klas_id);
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
                    $sectors[$sid]['assigned'][] = $lid;
                    $assigned[$lid] = $sid;
                    unset($unassigned[$lid]);
                }
            }
        }
    }

    // --------- RESTVERDELING ---------
    foreach ($unassigned as $lid => $_) {
        foreach ($sectors as $sid => $sector) {
            if ($capacity[$sid] === 0 || count($sector['assigned']) < $capacity[$sid]) {
                $sectors[$sid]['assigned'][] = $lid;
                $assigned[$lid] = $sid;
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
            'assigned' => $s['assigned'],
            'max'      => $s['max']
        ];
    }

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

// ----------------- PAGINA -----------------

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
$sectorNaamMap = [];
while ($r = $res->fetch_assoc()) {
    $sectors[] = $r;
    $sectorNaamMap[(int)$r['id']] = $r['naam'];
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

<div class="ttr-app">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1">Verdeling – <?= e($klas['klasaanduiding']) ?></h2>
                <div class="text-muted"><?= e($klas['schoolnaam']) ?> • Leerjaar <?= e($klas['leerjaar']) ?></div>
            </div>
            <div class="d-flex gap-2">
                <a href="leerlingen.php?klas_id=<?= $klas_id ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Terug naar leerlingen
                </a>
                <button id="btnAuto" class="btn btn-primary">
                    <i class="bi bi-lightning"></i> Automatisch verdelen
                </button>
                <button id="btnSave" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Opslaan verdeling
                </button>
            </div>
        </div>

        <!-- Hier komen meldingen (Bootstrap alerts) -->
        <div id="autoMessages" class="mb-3"></div>

        <!-- Sectoren / werelden -->
        <div class="row g-3 mb-4">
            <?php foreach ($sectors as $s): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card verdeling-card shadow-sm h-100">
                        <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
                            <span><?= e($s['naam']) ?></span>
                            <!-- Toon aantal toegewezen leerlingen -->
                            <span class="badge bg-light text-primary" id="sector-<?= $s['id'] ?>-count" data-max-leerlingen="<?= (int)$s['max_leerlingen'] ?>">
                                Aantal: 0
                            </span>
                            <span class="badge bg-light text-primary">
                                Max: <?= (int)$s['max_leerlingen'] === 0 ? '∞' : (int)$s['max_leerlingen'] ?>
                            </span>
                        </div>
                        <div class="card-body p-2">
                            <div class="dropzone" data-sector-id="<?= (int)$s['id'] ?>" style="min-height:180px;"></div>
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
                        $assigned = $l['toegewezen_voorkeur'] !== null && trim((string)$l['toegewezen_voorkeur']) !== ''
                            ? (int)$l['toegewezen_voorkeur']
                            : 0;
                    ?>
                        <div class="student-wrapper"
                            data-leerling-id="<?= $lid ?>"
                            data-assigned="<?= $assigned ?>">
                            <div class="student-item" draggable="true" data-leerling-id="<?= $lid ?>">
                                <div class="student-name">
                                    <?= e($stuNames[$lid]) ?>
                                </div>

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
        const dropzones = document.querySelectorAll('.dropzone');
        const students = document.querySelectorAll('.student-wrapper');
        const msgBox = document.getElementById('autoMessages');

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
            const assigned = sw.getAttribute('data-assigned') || '0';
            const dz = document.querySelector('.dropzone[data-sector-id="' + assigned + '"]');
            if (dz) dz.appendChild(sw);
        });

        // Update counts voor alle sectoren
        function updateAllSectorCounts() {
            document.querySelectorAll('.dropzone').forEach(dz => {
                const sectorId = dz.getAttribute('data-sector-id');
                if (sectorId !== '0') { // skip de pool
                    updateSectorCount(sectorId);
                }
            });
        }

        // Functie om het aantal leerlingen in een sector bij te werken
        function updateSectorCount(sectorId) {
            const sector = document.querySelector('.dropzone[data-sector-id="' + sectorId + '"]');
            const studentCount = sector ? sector.querySelectorAll('.student-wrapper').length : 0;
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
                dz.appendChild(dragged);
                dragged.setAttribute('data-assigned', newSectorId);

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

                        // Update het aantal leerlingen in de sector na automatische verdeling
                        updateSectorCount(s.id);
                    });

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