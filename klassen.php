<?php
require 'includes/header.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
// Controleer of er een school_id is meegegeven
if (!isset($_GET['school_id'])) {
    header("Location: scholen.php");
    exit;
}

$school_id = (int)$_GET['school_id'];

// Haal schoolnaam op voor de titel
$stmt = $conn->prepare("SELECT schoolnaam FROM school WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$school = $result->fetch_assoc();
$stmt->close();

if (!$school) {
    echo "<div class='alert alert-danger m-5'>School niet gevonden.</div>";
    require 'includes/footer.php';
    exit;
}

// CREATE
if (isset($_POST['add'])) {
    $klasaanduiding = $_POST['klasaanduiding'];
    $leerjaar = $_POST['leerjaar'];
    $schooljaar = $_POST['schooljaar'];
    $pincode = !empty($_POST['pincode']) ? (int)$_POST['pincode'] : null;

    $stmt = $conn->prepare("INSERT INTO klas (school_id, klasaanduiding, leerjaar, schooljaar, pincode) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $school_id, $klasaanduiding, $leerjaar, $schooljaar, $pincode);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();

    header("Location: klassen.php?school_id=$school_id&highlight=$new_id");
    exit;
}

// UPDATE
if (isset($_POST['update'])) {
    $klas_id = (int)$_POST['klas_id'];
    $klasaanduiding = $_POST['klasaanduiding'];
    $leerjaar = $_POST['leerjaar'];
    $schooljaar = $_POST['schooljaar'];
    $pincode = !empty($_POST['pincode']) ? (int)$_POST['pincode'] : null;

    $stmt = $conn->prepare("UPDATE klas SET klasaanduiding=?, leerjaar=?, schooljaar=?, pincode=? WHERE klas_id=? AND school_id=?");
    $stmt->bind_param("sssiii", $klasaanduiding, $leerjaar, $schooljaar, $pincode, $klas_id, $school_id);
    $stmt->execute();
    $stmt->close();

    header("Location: klassen.php?school_id=$school_id&highlight=$klas_id");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $klas_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM klas WHERE klas_id=? AND school_id=?");
    $stmt->bind_param("ii", $klas_id, $school_id);
    $stmt->execute();
    $stmt->close();
    header("Location: klassen.php?school_id=$school_id");
    exit;
}

// READ
$stmt = $conn->prepare("SELECT * FROM klas WHERE school_id=? ORDER BY leerjaar, klasaanduiding");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$klassen = $stmt->get_result();
$stmt->close();

// Highlight logic
$highlight_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : null;
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col d-flex align-items-center justify-content-between">
            <h2 class="fw-bold text-primary mb-0">
                Klassen – <?= htmlspecialchars($school['schoolnaam']) ?>
            </h2>
            <a href="scholen.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Terug naar scholen
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white fw-semibold">
                    Overzicht klassen
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Klas</th>
                                <th>Leerjaar</th>
                                <th>Schooljaar</th>
                                <th>Pincode</th>
                                <th class="text-end">Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($klassen->num_rows > 0): ?>
                                <?php while ($row = $klassen->fetch_assoc()): ?>
                                    <tr<?= ($highlight_id === (int)$row['klas_id']) ? ' class="table-warning"' : '' ?>>
                                        <td><?= $row['klas_id'] ?></td>
                                        <td><?= htmlspecialchars($row['klasaanduiding']) ?></td>
                                        <td><?= htmlspecialchars($row['leerjaar']) ?></td>
                                        <td><?= htmlspecialchars($row['schooljaar']) ?></td>
                                        <td><?= htmlspecialchars($row['pincode']) ?></td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <a href="klassen.php?school_id=<?= $school_id ?>&edit=<?= $row['klas_id'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-pencil-square"></i> Bewerken
                                                </a>
                                                <a href="klassen.php?school_id=<?= $school_id ?>&delete=<?= $row['klas_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Weet je zeker dat je deze klas wilt verwijderen?')">
                                                    <i class="bi bi-trash"></i> Verwijderen
                                                </a>
                                            </div>
                                        </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-3 text-muted">
                                            Geen klassen gevonden.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- FORM: toevoegen of bewerken -->
        <div class="col-lg-4">
            <?php if (!isset($_GET['edit'])): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white fw-semibold">
                        Klas toevoegen
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-12 mb-2">
                                <label for="klasaanduiding" class="form-label">Klas</label>
                                <input type="text" name="klasaanduiding" id="klasaanduiding" class="form-control" placeholder="Bijv. 1A, 3B" required>
                            </div>
                            <div class="col-12 mb-2">
                                <label for="leerjaar" class="form-label">Leerjaar</label>
                                <input type="text" name="leerjaar" id="leerjaar" class="form-control" placeholder="Bijv. 1, 2, 3" required>
                            </div>
                            <div class="col-12 mb-2">
                                <label for="schooljaar" class="form-label">Schooljaar</label>
                                <input type="text" name="schooljaar" id="schooljaar" class="form-control" placeholder="Bijv. 2024-2025" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="pincode" class="form-label">Pincode (optioneel)</label>
                                <input type="number" name="pincode" id="pincode" class="form-control" placeholder="Bijv. 1234">
                            </div>
                            <div class="col-12 d-grid mt-2">
                                <button type="submit" name="add" class="btn btn-success w-100">Toevoegen</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else:
                $klas_id = (int)$_GET['edit'];
                $stmt = $conn->prepare("SELECT * FROM klas WHERE klas_id=? AND school_id=?");
                $stmt->bind_param("ii", $klas_id, $school_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $klas = $result->fetch_assoc();
                $stmt->close();
                if ($klas):
                ?>
                    <div class="card shadow-sm">
                        <div class="card-header bg-warning text-dark fw-semibold">
                            Klas bewerken
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <input type="hidden" name="klas_id" value="<?= $klas['klas_id'] ?>">
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
                                <div class="col-12 mb-3">
                                    <label for="edit_pincode" class="form-label">Pincode (optioneel)</label>
                                    <input type="number" name="pincode" id="edit_pincode" class="form-control" value="<?= htmlspecialchars($klas['pincode']) ?>">
                                </div>
                                <div class="col-12 d-flex gap-2 mt-2">
                                    <button type="submit" name="update" class="btn btn-warning text-dark w-50">Opslaan</button>
                                    <a href="klassen.php?school_id=<?= $school_id ?>" class="btn btn-secondary w-50 d-flex align-items-center justify-content-center">Annuleren</a>
                                </div>
                            </form>
                        </div>
                    </div>
            <?php endif;
            endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>