<?php
require 'includes/header.php';

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

$errors = [];  // verzamel validatiefouten
$success = null;

/* ==========================
   ✅ KLAS TOEVOEGEN (met validatie)
========================== */
if (isset($_POST['add'])) {
    // Ingelezen waarden (bewaren voor her-render)
    $klasaanduiding = substr(trim($_POST['klasaanduiding'] ?? ''), 0, 255);
    $leerjaar       = substr(trim($_POST['leerjaar'] ?? ''), 0, 100);
    $schooljaar     = substr(trim($_POST['schooljaar'] ?? ''), 0, 50);
    $pincode        = trim($_POST['pincode'] ?? '');
    $max_keuzes_raw = $_POST['max_keuzes'] ?? null;

    // Validatie: max_keuzes verplicht en alleen 2 of 3
    if ($max_keuzes_raw === null || !in_array((int)$max_keuzes_raw, [2, 3], true)) {
        $errors[] = "Vul het aantal keuzes in (alleen 2 of 3).";
    } else {
        $max_keuzes = (int)$max_keuzes_raw;
    }

    // Validatie: minimaal 3 voorkeuren (niet-lege namen)
    $postVoorkeuren = is_array($_POST['voorkeuren'] ?? null) ? $_POST['voorkeuren'] : [];
    // trim & filter leeg
    $voorkeuren_clean = [];
    foreach ($postVoorkeuren as $naamRaw) {
        $naam = substr(trim((string)$naamRaw), 0, 255);
        if ($naam !== '') $voorkeuren_clean[] = $naam;
    }
    if (count($voorkeuren_clean) < 3) {
        $errors[] = "Voeg minimaal 3 voorkeuren toe.";
    }

    if (!$klasaanduiding) $errors[] = "Vul de klasaanduiding in.";
    if (!$leerjaar)       $errors[] = "Vul het leerjaar in.";
    if (!$schooljaar)     $errors[] = "Vul het schooljaar in (bijv. 2025/2026).";

    if (empty($errors)) {
        // transaction starten
        $conn->begin_transaction();
        try {
            // Gebruik NULLIF zodat lege pincode als SQL NULL wordt opgeslagen
            $stmt = $conn->prepare("
                INSERT INTO klas (school_id, klasaanduiding, leerjaar, schooljaar, pincode, max_keuzes)
                VALUES (?, ?, ?, ?, NULLIF(?,''), ?)
            ");
            $stmt->bind_param("issssi", $school_id, $klasaanduiding, $leerjaar, $schooljaar, $pincode, $max_keuzes);
            $stmt->execute();
            $klas_id = $conn->insert_id;
            $stmt->close();

            // Voeg voorkeuren toe in opgegeven volgorde
            $insertVoorkeurStmt = $conn->prepare("INSERT INTO klas_voorkeur (klas_id, volgorde, naam, actief) VALUES (?, ?, ?, 1)");
            $insertOptieStmt    = $conn->prepare("INSERT INTO voorkeur_opties (klas_voorkeur_id, naam) VALUES (?, ?)");

            foreach ($voorkeuren_clean as $index => $naam) {
                $volgorde = $index + 1;
                $insertVoorkeurStmt->bind_param("iis", $klas_id, $volgorde, $naam);
                $insertVoorkeurStmt->execute();
                $voorkeur_id = $conn->insert_id;

                // desnoods zelfde naam als optie
                $insertOptieStmt->bind_param("is", $voorkeur_id, $naam);
                $insertOptieStmt->execute();
            }
            $insertVoorkeurStmt->close();
            $insertOptieStmt->close();

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
   ✅ KLAS UPDATEN (max_keuzes alleen 2 of 3)
========================== */
if (isset($_POST['update'])) {
    $klas_id        = (int)($_POST['klas_id'] ?? 0);
    $klasaanduiding = substr(trim($_POST['klasaanduiding'] ?? ''), 0, 255);
    $leerjaar       = substr(trim($_POST['leerjaar'] ?? ''), 0, 100);
    $schooljaar     = substr(trim($_POST['schooljaar'] ?? ''), 0, 50);
    $pincode        = trim($_POST['pincode'] ?? '');
    $max_keuzes_raw = $_POST['max_keuzes'] ?? null;

    if ($max_keuzes_raw === null || !in_array((int)$max_keuzes_raw, [2, 3], true)) {
        $errors[] = "Vul het aantal keuzes in (alleen 2 of 3).";
    } else {
        $max_keuzes = (int)$max_keuzes_raw;
    }
    if (!$klasaanduiding) $errors[] = "Vul de klasaanduiding in.";
    if (!$leerjaar)       $errors[] = "Vul het leerjaar in.";
    if (!$schooljaar)     $errors[] = "Vul het schooljaar in (bijv. 2025/2026).";

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Update klasgegevens (lege pincode -> NULL)
            $stmt = $conn->prepare("
                UPDATE klas
                SET klasaanduiding=?, leerjaar=?, schooljaar=?, pincode=NULLIF(?, ''), max_keuzes=?
                WHERE klas_id=? AND school_id=?
            ");
            $stmt->bind_param("ssssiii", $klasaanduiding, $leerjaar, $schooljaar, $pincode, $max_keuzes, $klas_id, $school_id);
            $stmt->execute();
            $stmt->close();

            // Bestaande voorkeuren bijwerken
            if (!empty($_POST['voorkeur_id']) && is_array($_POST['voorkeur_id'])) {
                $updateVoorkeurStmt = $conn->prepare("UPDATE klas_voorkeur SET naam=? WHERE id=? AND klas_id=?");
                $updateOptieStmt    = $conn->prepare("UPDATE voorkeur_opties SET naam=? WHERE klas_voorkeur_id=?");

                foreach ($_POST['voorkeur_id'] as $i => $v_id_raw) {
                    $v_id = (int)$v_id_raw;
                    $naam = substr(trim($_POST['voorkeur_naam'][$i] ?? ''), 0, 255);
                    if ($naam === "") continue;

                    $updateVoorkeurStmt->bind_param("sii", $naam, $v_id, $klas_id);
                    $updateVoorkeurStmt->execute();

                    $updateOptieStmt->bind_param("si", $naam, $v_id);
                    $updateOptieStmt->execute();
                }

                $updateVoorkeurStmt->close();
                $updateOptieStmt->close();
            }

            // Nieuwe voorkeur toevoegen (optioneel)
            if (!empty($_POST['nieuwe_voorkeuren']) && is_array($_POST['nieuwe_voorkeuren'])) {
                $maxStmt            = $conn->prepare("SELECT COALESCE(MAX(volgorde), 0) AS max_volgorde FROM klas_voorkeur WHERE klas_id=?");
                $insertVoorkeurStmt = $conn->prepare("INSERT INTO klas_voorkeur (klas_id, volgorde, naam, actief) VALUES (?, ?, ?, 1)");
                $insertOptieStmt    = $conn->prepare("INSERT INTO voorkeur_opties (klas_voorkeur_id, naam) VALUES (?, ?)");

                foreach ($_POST['nieuwe_voorkeuren'] as $naamRaw) {
                    $naam = substr(trim($naamRaw), 0, 255);
                    if ($naam === "") continue;

                    $maxStmt->bind_param("i", $klas_id);
                    $maxStmt->execute();
                    $max = $maxStmt->get_result()->fetch_assoc()['max_volgorde'] ?? 0;

                    $volgorde = $max + 1;

                    $insertVoorkeurStmt->bind_param("iis", $klas_id, $volgorde, $naam);
                    $insertVoorkeurStmt->execute();
                    $voorkeur_id = $conn->insert_id;

                    $insertOptieStmt->bind_param("is", $voorkeur_id, $naam);
                    $insertOptieStmt->execute();
                }

                $maxStmt->close();
                $insertVoorkeurStmt->close();
                $insertOptieStmt->close();
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
        // Verwijder eerst alle gekoppelde voorkeur_opties, klas_voorkeur en leerlingen, daarna klas
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
            <a href="scholen.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Terug</a>
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
                                            <div class="btn-group" role="group" aria-label="Acties">
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
                <!-- Nieuwe klas -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white fw-semibold">
                        Klas toevoegen
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3" id="formAddKlas">
                            <div class="col-12 mb-2">
                                <label for="klasaanduiding" class="form-label">Klas</label>
                                <input type="text" name="klasaanduiding" id="klasaanduiding" class="form-control" placeholder="Bv. 1A" required
                                    value="<?= htmlspecialchars($_POST['klasaanduiding'] ?? '') ?>">
                            </div>

                            <div class="col-12 mb-2">
                                <label for="leerjaar" class="form-label">Leerjaar</label>
                                <input type="text" name="leerjaar" id="leerjaar" class="form-control" placeholder="Bv. 1" required
                                    value="<?= htmlspecialchars($_POST['leerjaar'] ?? '') ?>">
                            </div>

                            <div class="col-12 mb-2">
                                <label for="schooljaar" class="form-label">Schooljaar</label>
                                <input type="text" name="schooljaar" id="schooljaar" class="form-control" placeholder="Bv. 2025/2026" required
                                    value="<?= htmlspecialchars($_POST['schooljaar'] ?? '') ?>">
                            </div>

                            <div class="col-12 mb-2">
                                <label for="pincode" class="form-label">Pincode (optioneel)</label>
                                <input type="text" name="pincode" id="pincode" class="form-control" placeholder="Vier cijfers"
                                    value="<?= htmlspecialchars($_POST['pincode'] ?? '') ?>">
                            </div>

                            <div class="col-12 mb-2">
                                <label for="max_keuzes" class="form-label">Aantal keuzes toegestaan</label>
                                <select name="max_keuzes" id="max_keuzes" class="form-control" required>
                                    <option value="" <?= !isset($_POST['max_keuzes']) ? 'selected' : '' ?> disabled>Kies aantal keuzes</option>
                                    <option value="2" <?= (($_POST['max_keuzes'] ?? '') === '2') ? 'selected' : '' ?>>2</option>
                                    <option value="3" <?= (($_POST['max_keuzes'] ?? '') === '3') ? 'selected' : '' ?>>3</option>
                                </select>
                                <small class="text-muted">Dit bepaalt hoeveel sectoren/werelden een leerling mag kiezen in het formulier.</small>
                            </div>

                            <hr class="col-10">

                            <div class="col-12">
                                <h6 class="mb-2">Voorkeuren toevoegen (minimaal 3)</h6>
                                <div id="voorkeurenWrapper">
                                    <?php
                                    // Herbouw invoer bij fout; zorg dat er minimaal 3 velden staan
                                    $prefPosted = is_array($_POST['voorkeuren'] ?? null) ? $_POST['voorkeuren'] : ['', '', ''];
                                    if (count($prefPosted) < 3) {
                                        $prefPosted = array_merge($prefPosted, array_fill(0, 3 - count($prefPosted), ''));
                                    }
                                    foreach ($prefPosted as $val): ?>
                                        <div class="mb-2">
                                            <input type="text" name="voorkeuren[]" class="form-control" placeholder="Naam voorkeur"
                                                value="<?= htmlspecialchars($val) ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-secondary btn-sm mt-2 mb-3" onclick="addVoorkeur()">+ Voeg voorkeur toe</button>
                            </div>

                            <div class="col-12 d-grid mt-2">
                                <button type="submit" name="add" class="btn btn-success w-100">Toevoegen</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function addVoorkeur() {
                        const wrapper = document.getElementById('voorkeurenWrapper');
                        const div = document.createElement('div');
                        div.classList.add('mb-2');
                        div.innerHTML = `<input type="text" name="voorkeuren[]" class="form-control" placeholder="Naam voorkeur">`;
                        wrapper.appendChild(div);
                    }

                    // Extra client-side check (handig, maar server-side is leidend)
                    document.getElementById('formAddKlas').addEventListener('submit', function(e) {
                        const maxSel = document.getElementById('max_keuzes');
                        if (!maxSel.value || (maxSel.value !== '2' && maxSel.value !== '3')) {
                            e.preventDefault();
                            alert('Kies het aantal keuzes (alleen 2 of 3).');
                            return;
                        }
                        const inputs = Array.from(document.querySelectorAll('#voorkeurenWrapper input[name="voorkeuren[]"]'));
                        const filled = inputs.map(i => i.value.trim()).filter(v => v !== '');
                        if (filled.length < 3) {
                            e.preventDefault();
                            alert('Voeg minimaal 3 voorkeuren toe.');
                        }
                    });
                </script>

            <?php else:
                // Bewerken
                $klas_id = (int)$_GET['edit'];
                $stmt = $conn->prepare("SELECT * FROM klas WHERE klas_id=? AND school_id=?");
                $stmt->bind_param("ii", $klas_id, $school_id);
                $stmt->execute();
                $klas = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // veilige prepared statement voor voorkeuren
                $stmt = $conn->prepare("SELECT * FROM klas_voorkeur WHERE klas_id=? ORDER BY volgorde ASC");
                $stmt->bind_param("i", $klas_id);
                $stmt->execute();
                $voorkeuren = $stmt->get_result();
                $stmt->close();

                $huidig_max = in_array((int)($klas['max_keuzes'] ?? 2), [2, 3], true) ? (int)$klas['max_keuzes'] : 2;
            ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark fw-semibold">
                        Klas bewerken
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="klas_id" value="<?= (int)$klas['klas_id'] ?>">

                            <div class="col-12 mb-2">
                                <label for="edit_klasaanduiding" class="form-label">Klas</label>
                                <input type="text" name="klasaanduiding" id="edit_klasaanduiding" class="form-control" value="<?= htmlspecialchars($klas['klasaanduiding']) ?>" required>
                            </div>

                            <div class="col-12 mb-2">
                                <label for="edit_leerjaar" class="form-label">Leerjaar</label>
                                <input type="text" name="leerjaar" id="edit_leerjaar" class="form-control" value="<?= htmlspecialchars($klas['leerjaar']) ?>" required>
                            </div>

                            <div class="col-12 mb-2">
                                <label for="edit_schooljaar" class="form-label">Schooljaar</label>
                                <input type="text" name="schooljaar" id="edit_schooljaar" class="form-control" value="<?= htmlspecialchars($klas['schooljaar']) ?>" required>
                            </div>

                            <div class="col-12 mb-2">
                                <label for="edit_pincode" class="form-label">Pincode</label>
                                <input type="text" name="pincode" id="edit_pincode" class="form-control" value="<?= htmlspecialchars($klas['pincode']) ?>">
                            </div>

                            <div class="col-12 mb-2">
                                <label for="edit_max_keuzes" class="form-label">Aantal keuzes toegestaan</label>
                                <select name="max_keuzes" id="edit_max_keuzes" class="form-select" required>
                                    <option value="" <?= !isset($_POST['max_keuzes']) ? 'selected' : '' ?> disabled>— Aantal Keuzes —</option>
                                    <option value="2" <?= $huidig_max === 2 ? 'selected' : '' ?>>2</option>
                                    <option value="3" <?= $huidig_max === 3 ? 'selected' : '' ?>>3</option>
                                </select>
                            </div>

                            <hr class="col-10">

                            <div class="col-12">
                                <h6 class="mb-2">Bestaande voorkeuren</h6>
                                <div id="voorkeurenWrapperEdit">
                                    <?php while ($v = $voorkeuren->fetch_assoc()): ?>
                                        <div class="mb-2">
                                            <input type="hidden" name="voorkeur_id[]" value="<?= (int)$v['id'] ?>">
                                            <input type="text" name="voorkeur_naam[]" class="form-control" value="<?= htmlspecialchars($v['naam']) ?>">
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <div class="col-12 mt-3">
                                <h6 class="mb-2">Nieuwe voorkeur toevoegen</h6>
                                <div id="nieuweVoorkeurenWrapper">
                                    <div class="mb-2">
                                        <input type="text" name="nieuwe_voorkeuren[]" class="form-control" placeholder="Nieuwe voorkeur">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary btn-sm mt-2 mb-3" onclick="addNieuweVoorkeur()">+ Voeg nog een toe</button>
                            </div>

                            <div class="col-12 d-flex gap-2 mt-2">
                                <button type="submit" name="update" class="btn btn-warning text-dark w-50">
                                    Opslaan
                                </button>
                                <a href="klassen.php?school_id=<?= $school_id ?>" class="btn btn-secondary w-50 d-flex align-items-center justify-content-center">
                                    Annuleren
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function addNieuweVoorkeur() {
                        const wrapper = document.getElementById('nieuweVoorkeurenWrapper');
                        const div = document.createElement('div');
                        div.classList.add('mb-2');
                        div.innerHTML = `<input type="text" name="nieuwe_voorkeuren[]" class="form-control" placeholder="Nieuwe voorkeur">`;
                        wrapper.appendChild(div);
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>