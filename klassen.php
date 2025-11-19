<?php
require 'includes/header.php';
require 'includes/config.php';

// Alleen toegankelijk voor admins
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Controleer of school_id aanwezig is
if (!isset($_GET['school_id'])) {
    header("Location: scholen.php");
    exit;
}

$school_id = (int)$_GET['school_id'];

// Haal schoolnaam op
$stmt = $conn->prepare("SELECT schoolnaam FROM school WHERE school_id=?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$school) {
    echo "<div class='alert alert-danger m-5'>School niet gevonden.</div>";
    require 'includes/footer.php';
    exit;
}

$errors = [];
$success = null;

/* ==========================
   ✅ KLAS TOEVOEGEN
========================== */
if (isset($_POST['add'])) {
    $klasaanduiding = substr(trim($_POST['klasaanduiding'] ?? ''), 0, 255);
    $leerjaar       = substr(trim($_POST['leerjaar'] ?? ''), 0, 100);
    $schooljaar     = substr(trim($_POST['schooljaar'] ?? ''), 0, 50);
    $pincode        = trim($_POST['pincode'] ?? '');
    $max_keuzes_raw = $_POST['max_keuzes'] ?? null;

    if ($max_keuzes_raw === null || !in_array((int)$max_keuzes_raw, [2, 3], true)) {
        $errors[] = "Vul het aantal keuzes in (2 of 3).";
    } else {
        $max_keuzes = (int)$max_keuzes_raw;
    }

    $postVoorkeuren = is_array($_POST['voorkeuren'] ?? null) ? $_POST['voorkeuren'] : [];
    $postMax       = is_array($_POST['max_studenten'] ?? null) ? $_POST['max_studenten'] : [];
    $voorkeuren_clean = [];
    $max_studenten_clean = [];

    foreach ($postVoorkeuren as $i => $naamRaw) {
        $naam = substr(trim((string)$naamRaw), 0, 255);
        $maxAantal = isset($postMax[$i]) ? max(1, (int)$postMax[$i]) : 1;
        if ($naam !== '') {
            $voorkeuren_clean[] = $naam;
            $max_studenten_clean[] = $maxAantal;
        }
    }

    if (count($voorkeuren_clean) < 3) $errors[] = "Voeg minimaal 3 voorkeuren toe.";
    if (!$klasaanduiding) $errors[] = "Vul de klasaanduiding in.";
    if (!$leerjaar) $errors[] = "Vul het leerjaar in.";
    if (!$schooljaar) $errors[] = "Vul het schooljaar in.";

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO klas (school_id, klasaanduiding, leerjaar, schooljaar, pincode, max_keuzes)
                VALUES (?, ?, ?, ?, NULLIF(?,''), ?)
            ");
            $stmt->bind_param("issssi", $school_id, $klasaanduiding, $leerjaar, $schooljaar, $pincode, $max_keuzes);
            $stmt->execute();
            $klas_id = $conn->insert_id;
            $stmt->close();

            $insertVoorkeurStmt = $conn->prepare("
                INSERT INTO klas_voorkeur (klas_id, volgorde, naam, max_leerlingen, actief)
                VALUES (?, ?, ?, ?, 1)
            ");
            foreach ($voorkeuren_clean as $index => $naam) {
                $volgorde = $index + 1;
                $maxAantal = $max_studenten_clean[$index] ?? 1;
                $insertVoorkeurStmt->bind_param("iisi", $klas_id, $volgorde, $naam, $maxAantal);
                $insertVoorkeurStmt->execute();
            }
            $insertVoorkeurStmt->close();

            $conn->commit();
            header("Location: klassen.php?school_id=$school_id&highlight=$klas_id");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Fout bij toevoegen klas: " . $e->getMessage());
            $errors[] = "Er is iets misgegaan bij het toevoegen van de klas.";
        }
    }
}

/* ==========================
   ✅ KLAS UPDATEN
========================== */
if (isset($_POST['update'])) {
    $klas_id        = (int)($_POST['klas_id'] ?? 0);
    $klasaanduiding = substr(trim($_POST['klasaanduiding'] ?? ''), 0, 255);
    $leerjaar       = substr(trim($_POST['leerjaar'] ?? ''), 0, 100);
    $schooljaar     = substr(trim($_POST['schooljaar'] ?? ''), 0, 50);
    $pincode        = trim($_POST['pincode'] ?? '');
    $max_keuzes_raw = $_POST['max_keuzes'] ?? null;

    if ($max_keuzes_raw === null || !in_array((int)$max_keuzes_raw, [2, 3], true)) {
        $errors[] = "Vul het aantal keuzes in (2 of 3).";
    } else {
        $max_keuzes = (int)$max_keuzes_raw;
    }
    if (!$klasaanduiding) $errors[] = "Vul de klasaanduiding in.";
    if (!$leerjaar)       $errors[] = "Vul het leerjaar in.";
    if (!$schooljaar)     $errors[] = "Vul het schooljaar in.";

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                UPDATE klas
                SET klasaanduiding=?, leerjaar=?, schooljaar=?, pincode=NULLIF(?, ''), max_keuzes=?
                WHERE klas_id=? AND school_id=?
            ");
            $stmt->bind_param("ssssiii", $klasaanduiding, $leerjaar, $schooljaar, $pincode, $max_keuzes, $klas_id, $school_id);
            $stmt->execute();
            $stmt->close();

            // Update bestaande voorkeuren
            if (!empty($_POST['voorkeur_id']) && is_array($_POST['voorkeur_id'])) {
                $updateVoorkeurStmt = $conn->prepare("
                    UPDATE klas_voorkeur SET naam=?, max_leerlingen=? WHERE id=? AND klas_id=?
                ");
                foreach ($_POST['voorkeur_id'] as $i => $v_id_raw) {
                    $v_id = (int)$v_id_raw;
                    $naam = substr(trim($_POST['voorkeur_naam'][$i] ?? ''), 0, 255);
                    if ($naam === "") continue;
                    $maxStudents = max(1, (int)($_POST['voorkeur_max'][$i] ?? 1));
                    $updateVoorkeurStmt->bind_param("siii", $naam, $maxStudents, $v_id, $klas_id);
                    $updateVoorkeurStmt->execute();
                }
                $updateVoorkeurStmt->close();
            }

            // Verwijderen voorkeuren
            if (!empty($_POST['delete_voorkeur']) && is_array($_POST['delete_voorkeur'])) {
                $deleteStmt = $conn->prepare("DELETE FROM klas_voorkeur WHERE id=? AND klas_id=?");
                foreach ($_POST['delete_voorkeur'] as $v_id_raw) {
                    $v_id = (int)$v_id_raw;
                    $deleteStmt->bind_param("ii", $v_id, $klas_id);
                    $deleteStmt->execute();
                }
                $deleteStmt->close();
            }

            // Nieuwe voorkeur toevoegen
            if (!empty($_POST['nieuwe_voorkeuren']) && is_array($_POST['nieuwe_voorkeuren'])) {
                $maxStmt = $conn->prepare("SELECT COALESCE(MAX(volgorde),0) AS max_volgorde FROM klas_voorkeur WHERE klas_id=?");
                $insertVoorkeurStmt = $conn->prepare("INSERT INTO klas_voorkeur (klas_id, volgorde, naam, max_leerlingen, actief) VALUES (?, ?, ?, ?, 1)");
                foreach ($_POST['nieuwe_voorkeuren'] as $i => $naamRaw) {
                    $naam = substr(trim($naamRaw), 0, 255);
                    if ($naam === "") continue;
                    $maxStmt->bind_param("i", $klas_id);
                    $maxStmt->execute();
                    $max = $maxStmt->get_result()->fetch_assoc()['max_volgorde'] ?? 0;
                    $volgorde = $max + 1;
                    $maxNieuw = max(1, (int)($_POST['nieuwe_voorkeuren_max'][$i] ?? 1));
                    $insertVoorkeurStmt->bind_param("iisi", $klas_id, $volgorde, $naam, $maxNieuw);
                    $insertVoorkeurStmt->execute();
                }
                $maxStmt->close();
                $insertVoorkeurStmt->close();
            }

            $conn->commit();
            header("Location: klassen.php?school_id=$school_id&highlight=$klas_id");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Fout bij updaten klas: " . $e->getMessage());
            $errors[] = "Er is iets misgegaan bij het bijwerken van de klas.";
        }
    }
}

/* ==========================
   ✅ KLAS VERWIJDEREN
========================== */
if (isset($_GET['delete'])) {
    $klas_id = (int)$_GET['delete'];
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE v FROM voorkeur_opties v JOIN klas_voorkeur k ON v.klas_voorkeur_id = k.id WHERE k.klas_id = ?");
        $stmt->bind_param("i", $klas_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM klas_voorkeur WHERE klas_id = ?");
        $stmt->bind_param("i", $klas_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM leerling WHERE klas_id = ?");
        $stmt->bind_param("i", $klas_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM klas WHERE klas_id = ?");
        $stmt->bind_param("i", $klas_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        header("Location: klassen.php?school_id=$school_id");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Fout bij verwijderen klas: " . $e->getMessage());
        $errors[] = "Er is iets misgegaan bij het verwijderen van de klas.";
    }
}

// ==========================
// KLAS OVERZICHT
// ==========================
$stmt = $conn->prepare("SELECT * FROM klas WHERE school_id=? ORDER BY leerjaar, klasaanduiding");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$klassen = $stmt->get_result();
$stmt->close();

$highlight_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : null;
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col d-flex justify-content-between align-items-center">
            <h2 class="fw-bold text-primary mb-0">Klassen – <?= htmlspecialchars($school['schoolnaam']) ?></h2>
            <a href="scholen.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Terug naar scholen</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Controleer je invoer:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Overzicht klassen -->
        <div class="col-lg-8.5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white fw-semibold">Overzicht klassen</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Klas</th>
                            <th>Leerjaar</th>
                            <th>Schooljaar</th>
                            <th>Pincode</th>
                            <th>Keuzes</th>
                            <th class="text-end">Acties</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($klassen->num_rows > 0): ?>
                            <?php while ($row = $klassen->fetch_assoc()): ?>
                                <tr<?= $highlight_id === (int)$row['klas_id'] ? ' class="table-warning"' : '' ?>>
                                    <td><?= (int)$row['klas_id'] ?></td>
                                    <td><?= htmlspecialchars($row['klasaanduiding']) ?></td>
                                    <td><?= htmlspecialchars($row['leerjaar']) ?></td>
                                    <td><?= htmlspecialchars($row['schooljaar']) ?></td>
                                    <td><?= htmlspecialchars($row['pincode']) ?></td>
                                    <td><?= htmlspecialchars((string)($row['max_keuzes'] ?? '2')) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <a href="leerlingen.php?klas_id=<?= (int)$row['klas_id'] ?>" class="btn btn-dark btn-sm">
                                                <i class="bi bi-houses"></i> Leerlingen
                                            </a>
                                            <a href="klassen.php?school_id=<?= $school_id ?>&edit=<?= (int)$row['klas_id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-pencil-square"></i> Bewerken
                                            </a>
                                            <a href="klassen.php?school_id=<?= $school_id ?>&delete=<?= (int)$row['klas_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Weet je zeker dat je deze klas wilt verwijderen?')">
                                                <i class="bi bi-trash"></i> Verwijderen
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">Geen klassen gevonden.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Toevoegen / Bewerken -->
        <div class="col-lg-4">
            <?php if (!isset($_GET['edit'])): ?>
                <!-- Nieuwe klas formulier -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white fw-semibold">Klas toevoegen</div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Klasnaam</label>
                                <input type="text" name="klasaanduiding" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Leerjaar</label>
                                <input type="text" name="leerjaar" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Schooljaar</label>
                                <input type="text" name="schooljaar" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Pincode</label>
                                <input type="text" name="pincode" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Aantal keuzes</label>
                                <select name="max_keuzes" class="form-select" required>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                </select>
                            </div>

                            <!-- Voorkeuren toevoegen -->
                            <div class="col-12">
                                <label class="form-label">Voorkeuren (minimaal 3)</label>
                                <div id="voorkeurenWrapper">
                                    <?php for($i=0;$i<3;$i++): ?>
                                        <div class="mb-2 d-flex gap-2">
                                            <input type="text" name="voorkeuren[]" class="form-control" placeholder="Voorkeur naam">
                                            <input type="number" name="max_studenten[]" class="form-control" placeholder="Max leerlingen" min="1" style="max-width:120px">
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addVoorkeur()">+ Nieuwe voorkeur</button>
                            </div>

                            <div class="col-12 text-end mt-3">
                                <button type="submit" name="add" class="btn btn-success">Opslaan</button>
                                <a href="scholen.php" class="btn btn-secondary">Annuleren</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Bewerken -->
                <?php
                $klas_id = (int)$_GET['edit'];
                $stmt = $conn->prepare("SELECT * FROM klas WHERE klas_id=? AND school_id=?");
                $stmt->bind_param("ii", $klas_id, $school_id);
                $stmt->execute();
                $klas = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $stmt = $conn->prepare("SELECT * FROM klas_voorkeur WHERE klas_id=? ORDER BY volgorde ASC");
                $stmt->bind_param("i", $klas_id);
                $stmt->execute();
                $voorkeuren = $stmt->get_result();
                $stmt->close();

                $huidig_max = in_array((int)($klas['max_keuzes'] ?? 2), [2,3], true) ? (int)$klas['max_keuzes'] : 2;
                ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark fw-semibold">Klas bewerken</div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="klas_id" value="<?= (int)$klas['klas_id'] ?>">

                            <div class="col-12">
                                <label class="form-label">Klasnaam</label>
                                <input type="text" name="klasaanduiding" class="form-control" value="<?= htmlspecialchars($klas['klasaanduiding']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Leerjaar</label>
                                <input type="text" name="leerjaar" class="form-control" value="<?= htmlspecialchars($klas['leerjaar']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Schooljaar</label>
                                <input type="text" name="schooljaar" class="form-control" value="<?= htmlspecialchars($klas['schooljaar']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Pincode</label>
                                <input type="text" name="pincode" class="form-control" value="<?= htmlspecialchars($klas['pincode']) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Aantal keuzes</label>
                                <select name="max_keuzes" class="form-select" required>
                                    <option value="2" <?= $huidig_max===2?'selected':'' ?>>2</option>
                                    <option value="3" <?= $huidig_max===3?'selected':'' ?>>3</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <h6 class="mb-2">Bestaande voorkeuren</h6>
                                <div id="voorkeurenWrapperEdit">
                                    <?php while($v = $voorkeuren->fetch_assoc()): ?>
                                        <div class="mb-2 d-flex gap-2 align-items-center">
                                            <input type="hidden" name="voorkeur_id[]" value="<?= (int)$v['id'] ?>">
                                            <input type="text" name="voorkeur_naam[]" class="form-control" value="<?= htmlspecialchars($v['naam']) ?>" required>
                                            <input type="number" name="voorkeur_max[]" class="form-control" min="1" style="max-width:120px" value="<?= (int)$v['max_leerlingen'] ?>">
                                            <input type="checkbox" name="delete_voorkeur[]" value="<?= (int)$v['id'] ?>" title="Verwijderen">
                                        </div>
                                    <?php endwhile; ?>
                                </div>

                                <h6 class="mt-3">Nieuwe voorkeur toevoegen</h6>
                                <div id="nieuweVoorkeurenWrapper">
                                    <div class="mb-2 d-flex gap-2">
                                        <input type="text" name="nieuwe_voorkeuren[]" class="form-control" placeholder="Naam">
                                        <input type="number" name="nieuwe_voorkeuren_max[]" class="form-control" min="1" style="max-width:120px" placeholder="Max leerlingen">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addNieuweVoorkeur()">+ Nieuwe voorkeur</button>
                            </div>

                            <div class="col-12 text-end mt-3">
                                <button type="submit" name="update" class="btn btn-warning">Opslaan</button>
                                <a href="klassen.php?school_id=<?= $school_id ?>" class="btn btn-secondary">Annuleren</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function addVoorkeur() {
        const wrapper = document.getElementById('voorkeurenWrapper');
        const div = document.createElement('div');
        div.classList.add('mb-2','d-flex','gap-2');
        div.innerHTML = `<input type="text" name="voorkeuren[]" class="form-control" placeholder="Voorkeur naam">
                     <input type="number" name="max_studenten[]" class="form-control" placeholder="Max leerlingen" min="1" style="max-width:120px">`;
        wrapper.appendChild(div);
    }

    function addNieuweVoorkeur() {
        const wrapper = document.getElementById('nieuweVoorkeurenWrapper');
        const div = document.createElement('div');
        div.classList.add('mb-2','d-flex','gap-2');
        div.innerHTML = `<input type="text" name="nieuwe_voorkeuren[]" class="form-control" placeholder="Naam">
                     <input type="number" name="nieuwe_voorkeuren_max[]" class="form-control" min="1" style="max-width:120px" placeholder="Max leerlingen">`;
        wrapper.appendChild(div);
    }
</script>

<?php require 'includes/footer.php'; ?>
