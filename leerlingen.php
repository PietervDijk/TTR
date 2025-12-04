<?php
// leerlingen.php
require 'includes/config.php';
require 'includes/header.php';

// Alleen admins
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// klas_id check
if (!isset($_GET['klas_id']) || !ctype_digit($_GET['klas_id'])) {
    header('Location: scholen.php');
    exit;
}
$klas_id = (int)$_GET['klas_id'];

// helper
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ----------------------
// POST acties: update / delete
// ----------------------
$errors = [];
$success = null;

// Update leerling (via modal form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $leerling_id    = (int)($_POST['leerling_id'] ?? 0);
    $voornaam       = trim($_POST['voornaam'] ?? '');
    $tussenvoegsel  = trim($_POST['tussenvoegsel'] ?? '');
    $achternaam     = trim($_POST['achternaam'] ?? '');
    // voorkeuren (1..5)
    $v1 = isset($_POST['voorkeur1']) && ctype_digit((string)$_POST['voorkeur1']) ? (int)$_POST['voorkeur1'] : null;
    $v2 = isset($_POST['voorkeur2']) && ctype_digit((string)$_POST['voorkeur2']) ? (int)$_POST['voorkeur2'] : null;
    $v3 = isset($_POST['voorkeur3']) && ctype_digit((string)$_POST['voorkeur3']) ? (int)$_POST['voorkeur3'] : null;
    $v4 = isset($_POST['voorkeur4']) && ctype_digit((string)$_POST['voorkeur4']) ? (int)$_POST['voorkeur4'] : null;
    $v5 = isset($_POST['voorkeur5']) && ctype_digit((string)$_POST['voorkeur5']) ? (int)$_POST['voorkeur5'] : null;

    if ($leerling_id <= 0) $errors[] = "Ongeldige leerling.";
    if ($voornaam === '') $errors[] = "Voornaam is verplicht.";
    if ($achternaam === '') $errors[] = "Achternaam is verplicht.";

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE leerling
            SET voornaam = ?, tussenvoegsel = ?, achternaam = ?,
                voorkeur1 = ?, voorkeur2 = ?, voorkeur3 = ?, voorkeur4 = ?, voorkeur5 = ?
            WHERE leerling_id = ? AND klas_id = ?
        ");
        // bind ints as i (use null -> bind_param requires types; convert null to 0 and then use NULLIF? Simpler: use prepared statement with explicit types and handle null via variables)
        // We'll bind as strings for simplicity and let DB convert empty strings to NULL where appropriate using NULLIF in query.
        $stmt->bind_param(
            "sssiiiiiii",
            $voornaam,
            $tussenvoegsel,
            $achternaam,
            $v1,
            $v2,
            $v3,
            $v4,
            $v5,
            $leerling_id,
            $klas_id
        );
        if ($stmt->execute()) {
            $success = "Leerling bijgewerkt.";
        } else {
            $errors[] = "Fout bij bijwerken leerling.";
        }
        $stmt->close();
    }
}

// Delete leerling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $del_id = (int)($_POST['leerling_id'] ?? 0);
    if ($del_id > 0) {
        $stmt = $conn->prepare("DELETE FROM leerling WHERE leerling_id = ? AND klas_id = ?");
        $stmt->bind_param("ii", $del_id, $klas_id);
        if ($stmt->execute()) {
            $success = "Leerling verwijderd.";
        } else {
            $errors[] = "Fout bij verwijderen leerling.";
        }
        $stmt->close();
    } else {
        $errors[] = "Ongeldige leerling om te verwijderen.";
    }
}

// ----------------------
// Haal klas info
// ----------------------
$stmt = $conn->prepare("SELECT k.klas_id, k.school_id, k.klasaanduiding, k.leerjaar, k.schooljaar, k.max_keuzes, s.schoolnaam FROM klas k JOIN school s ON s.school_id = k.school_id WHERE k.klas_id = ?");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$klas = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$klas) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Klas niet gevonden.</div></div>";
    require 'includes/footer.php';
    exit;
}

$maxKeuzes = in_array((int)($klas['max_keuzes'] ?? 2), [2,3], true) ? (int)$klas['max_keuzes'] : 2;

// ----------------------
// Haal beschikbare voorkeuren (voor select in modal)
// ----------------------
$stmt = $conn->prepare("SELECT id, naam FROM klas_voorkeur WHERE klas_id = ? AND actief = 1 ORDER BY volgorde ASC");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$res = $stmt->get_result();
$allowedById = [];
$allowedList = [];
while ($r = $res->fetch_assoc()) {
    $allowedById[(int)$r['id']] = $r['naam'];
    $allowedList[] = $r['naam'];
}
$stmt->close();

// ----------------------
// Haal leerlingen (tabel)
// ----------------------
$stmt = $conn->prepare("
    SELECT leerling_id, voornaam, tussenvoegsel, achternaam,
           voorkeur1, voorkeur2, voorkeur3, voorkeur4, voorkeur5, toegewezen_voorkeur
    FROM leerling
    WHERE klas_id = ?
    ORDER BY achternaam ASC, voornaam ASC
");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$leerlingen = $stmt->get_result();
$stmt->close();

?>
<div class="container py-5">
    <div class="row mb-4">
        <div class="col d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold text-primary mb-1">Leerlingen – <?= e($klas['klasaanduiding']) ?></h2>
                <div class="text-muted"><?= e($klas['schoolnaam']) ?> • Leerjaar <?= e($klas['leerjaar']) ?></div>
                <?php if (!empty($allowedList)): ?>
                    <div class="mt-2 small"><strong>Beschikbare sectoren:</strong> <?= e(implode(', ', $allowedList)) ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <a href="klassen.php?school_id=<?= (int)$klas['school_id'] ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Terug naar klassen
                </a>
                <a href="scholen.php" class="btn btn-outline-secondary"><i class="bi bi-building"></i> Scholen</a>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err) echo '<li>'.e($err).'</li>'; ?></ul></div>
    <?php endif; ?>

    <a href="verdeling.php?klas_id=<?= $klas_id ?>" class="btn btn-primary mb-3"><i class="bi bi-kanban"></i> Ga naar verdeling</a>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Leerling voorkeuren – <?= e($klas['klasaanduiding']) ?></span>
            <span class="badge bg-light text-primary"><?= (int)$leerlingen->num_rows ?> leerling(en)</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="min-width:220px;">Naam</th>
                        <?php for ($i = 1; $i <= $maxKeuzes; $i++): ?><th>Voorkeur <?= $i ?></th><?php endfor; ?>
                        <th>Toegewezen</th>
                        <th style="width:170px">Actie</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if ($leerlingen->num_rows === 0): ?>
                        <tr><td colspan="<?= 3 + $maxKeuzes ?>" class="text-center py-3 text-muted">Nog geen leerlingen in deze klas.</td></tr>
                    <?php else: while ($l = $leerlingen->fetch_assoc()): ?>
                        <tr>
                            <td><?= e($l['voornaam']) ?><?= $l['tussenvoegsel'] ? ' ' . e($l['tussenvoegsel']) : '' ?> <?= e($l['achternaam']) ?></td>

                            <?php for ($i = 1; $i <= $maxKeuzes; $i++):
                                $val = $l['voorkeur'.$i] ?? '';
                                // render label if id exists in allowedById
                                if (ctype_digit((string)$val) && isset($allowedById[(int)$val])) {
                                    $label = e($allowedById[(int)$val]);
                                } elseif ($val === '' || $val === null) {
                                    $label = '<span class="text-muted">—</span>';
                                } else {
                                    $label = '<span class="text-danger">'.e($val).' *</span>';
                                }
                                ?>
                                <td><?= $label ?></td>
                            <?php endfor; ?>

                            <td>
                                <?php
                                $t = $l['toegewezen_voorkeur'];
                                if (ctype_digit((string)$t) && isset($allowedById[(int)$t])) {
                                    echo '<span class="fw-semibold text-success">'.e($allowedById[(int)$t]).'</span>';
                                } elseif ($t === '' || $t === null) {
                                    echo '<span class="text-muted">—</span>';
                                } else {
                                    echo '<span class="text-danger">'.e($t).' *</span>';
                                }
                                ?>
                            </td>

                            <td>
                                <!-- BUTTONS: Bewerken (modal) & Verwijderen (POST) -->
                                <button
                                        type="button"
                                        class="btn btn-sm btn-warning me-1 updateStudentBtn"
                                        data-id="<?= (int)$l['leerling_id'] ?>"
                                        data-voornaam="<?= e($l['voornaam']) ?>"
                                        data-tussenvoegsel="<?= e($l['tussenvoegsel']) ?>"
                                        data-achternaam="<?= e($l['achternaam']) ?>"
                                        data-v1="<?= e($l['voorkeur1']) ?>"
                                        data-v2="<?= e($l['voorkeur2']) ?>"
                                        data-v3="<?= e($l['voorkeur3']) ?>"
                                        data-v4="<?= e($l['voorkeur4']) ?>"
                                        data-v5="<?= e($l['voorkeur5']) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#updateStudentModal"
                                >
                                    <i class="bi bi-pencil-square"></i> Bewerken
                                </button>

                                <!-- Delete via small form (POST) -->
                                <form method="post" class="d-inline" onsubmit="return confirm('Weet je zeker dat je deze leerling wilt verwijderen?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="leerling_id" value="<?= (int)$l['leerling_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i> Verwijderen
                                    </button>
                                </form>
                            </td>

                        </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 small text-muted">
        <strong>Legenda:</strong> <span class="text-danger">*</span> = keuze/ID staat niet (meer) in de lijst voor deze klas.
    </div>
</div>

<!-- ==========================
     UPDATE MODAL
     ========================== -->
<div class="modal fade" id="updateStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="leerling_id" id="edit_leerling_id" value="">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Leerling bewerken</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Voornaam</label>
                        <input type="text" name="voornaam" id="edit_voornaam" class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tussenvoegsel</label>
                        <input type="text" name="tussenvoegsel" id="edit_tussenvoegsel" class="form-control">
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Achternaam</label>
                        <input type="text" name="achternaam" id="edit_achternaam" class="form-control" required>
                    </div>

                    <hr class="my-3">

                    <?php for ($i = 1; $i <= 5; $i++): // toon altijd 5 velden; gebruikers kiezen maximaal $maxKeuzes maar admin kan vullen ?>
                        <div class="col-md-4">
                            <label class="form-label">Voorkeur <?= $i ?></label>
                            <select name="voorkeur<?= $i ?>" id="edit_v<?= $i ?>" class="form-select">
                                <option value="">— geen keuze —</option>
                                <?php foreach ($allowedById as $id => $naam): ?>
                                    <option value="<?= (int)$id ?>"><?= e($naam) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endfor; ?>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button type="submit" class="btn btn-warning">Opslaan</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function(){
        // vul modal met data uit knop
        const updateBtns = document.querySelectorAll('.updateStudentBtn');
        updateBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id || '';
                document.getElementById('edit_leerling_id').value = id;
                document.getElementById('edit_voornaam').value = btn.dataset.voornaam || '';
                document.getElementById('edit_tussenvoegsel').value = btn.dataset.tussenvoegsel || '';
                document.getElementById('edit_achternaam').value = btn.dataset.achternaam || '';

                // voorkeuren
                for (let i=1;i<=5;i++) {
                    const el = document.getElementById('edit_v'+i);
                    if (!el) continue;
                    el.value = btn.dataset['v'+i] || '';
                }
            });
        });
    })();
</script>

<?php require 'includes/footer.php'; ?>
