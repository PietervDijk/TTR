<?php
require 'includes/header.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
// CREATE
if (isset($_POST['add'])) {
    $schoolnaam = $_POST['schoolnaam'];
    $plaats = $_POST['plaats'];
    $type_onderwijs = $_POST['type_onderwijs'];
    $stmt = $conn->prepare("INSERT INTO school (schoolnaam, plaats, type_onderwijs) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $schoolnaam, $plaats, $type_onderwijs);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();
    header("Location: scholen.php?highlight=$new_id");
    exit;
}

// UPDATE
if (isset($_POST['update'])) {
    $school_id = (int)$_POST['school_id'];
    $schoolnaam = $_POST['schoolnaam'];
    $plaats = $_POST['plaats'];
    $type_onderwijs = $_POST['type_onderwijs'];
    $stmt = $conn->prepare("UPDATE school SET schoolnaam=?, plaats=?, type_onderwijs=? WHERE school_id=?");
    $stmt->bind_param("sssi", $schoolnaam, $plaats, $type_onderwijs, $school_id);
    $stmt->execute();
    $stmt->close();
    header("Location: scholen.php?highlight=$school_id");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $school_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM school WHERE school_id=?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $stmt->close();
    header("Location: scholen.php");
    exit;
}

// READ
$scholen = $conn->query("SELECT * FROM school");

// Highlight na bewerken of toevoegen
$highlight_id = null;
if (isset($_GET['highlight'])) {
    $highlight_id = (int)$_GET['highlight'];
}
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col">
            <h2 class="fw-bold text-primary">
                Scholen
            </h2>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white fw-semibold">
                    Overzicht scholen
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Schoolnaam</th>
                                <th>Plaats</th>
                                <th>Type onderwijs</th>
                                <th class="text-end">Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $scholen->fetch_assoc()): ?>
                                <tr<?= ($highlight_id === (int)$row['school_id']) ? ' class="table-warning"' : '' ?>>
                                    <td><?= $row['school_id'] ?></td>
                                    <td><?= htmlspecialchars($row['schoolnaam']) ?></td>
                                    <td><?= htmlspecialchars($row['plaats']) ?></td>
                                    <td>
                                        <span><?= htmlspecialchars($row['type_onderwijs']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group" aria-label="Acties">
                                            <a href="klassen.php?school_id=<?= $row['school_id'] ?>" class="btn btn-dark btn-sm">
                                                <i class="bi bi-houses"></i> Klassen
                                            </a>
                                            <a href="scholen.php?edit=<?= $row['school_id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-pencil-square"></i> Bewerken
                                            </a>
                                            <a href="scholen.php?delete=<?= $row['school_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Weet je het zeker?')">
                                                <i class="bi bi-trash"></i> Verwijderen
                                            </a>
                                        </div>
                                    </td>
                                    </tr>
                                <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <?php if (!isset($_GET['edit'])): // Toon het hele blok alleen als er niet wordt bewerkt 
            ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white fw-semibold">
                        School toevoegen
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-12 mb-2">
                                <label for="schoolnaam" class="form-label">Schoolnaam</label>
                                <input type="text" name="schoolnaam" id="schoolnaam" class="form-control" placeholder="Schoolnaam" required style="min-height: 48px;">
                            </div>
                            <div class="col-12 mb-2">
                                <label for="plaats" class="form-label">Plaats</label>
                                <input type="text" name="plaats" id="plaats" class="form-control" placeholder="Plaats" required style="min-height: 48px;">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="type_onderwijs" class="form-label">Type onderwijs</label>
                                <select name="type_onderwijs" id="type_onderwijs" class="form-control" required style="min-height: 48px;">
                                    <option value="" disabled selected>Kies type onderwijs</option>
                                    <option value="Primair Onderwijs">PO</option>
                                    <option value="Voortgezet Onderwijs">VO</option>
                                    <option value="MBO">MBO</option>
                                </select>
                            </div>
                            <div class="col-12 d-grid mt-2">
                                <button type="submit" name="add" class="btn btn-success w-100">
                                    Toevoegen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            // EDIT FORM
            if (isset($_GET['edit'])):
                $school_id = (int)$_GET['edit'];
                $stmt = $conn->prepare("SELECT * FROM school WHERE school_id=?");
                $stmt->bind_param("i", $school_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $school = $result->fetch_assoc();
                $stmt->close();
            ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark fw-semibold">
                        School bewerken
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="school_id" value="<?= $school['school_id'] ?>">
                            <div class="col-12 mb-2">
                                <label for="edit_schoolnaam" class="form-label">Schoolnaam</label>
                                <input type="text" name="schoolnaam" id="edit_schoolnaam" class="form-control" value="<?= htmlspecialchars($school['schoolnaam']) ?>" required style="min-height: 48px;">
                            </div>
                            <div class="col-12 mb-2">
                                <label for="edit_plaats" class="form-label">Plaats</label>
                                <input type="text" name="plaats" id="edit_plaats" class="form-control" value="<?= htmlspecialchars($school['plaats']) ?>" required style="min-height: 48px;">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="edit_type_onderwijs" class="form-label">Type onderwijs</label>
                                <select name="type_onderwijs" id="edit_type_onderwijs" class="form-control" required style="min-height: 48px;">
                                    <option value="" disabled <?= empty($school['type_onderwijs']) ? 'selected' : '' ?>>Kies type onderwijs</option>
                                    <option value="Primair Onderwijs" <?= $school['type_onderwijs'] == 'Primair Onderwijs' ? 'selected' : '' ?>>PO</option>
                                    <option value="Voortgezet Onderwijs" <?= $school['type_onderwijs'] == 'Voortgezet Onderwijs' ? 'selected' : '' ?>>VO</option>
                                    <option value="MBO" <?= $school['type_onderwijs'] == 'MBO' ? 'selected' : '' ?>>MBO</option>
                                </select>
                            </div>
                            <div class="col-12 d-flex gap-2 mt-2">
                                <button type="submit" name="update" class="btn btn-warning text-dark w-50">
                                    Opslaan
                                </button>
                                <a href="scholen.php" class="btn btn-secondary w-50 d-flex align-items-center justify-content-center">
                                    Annuleren
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require 'includes/footer.php'; ?>