<?php
require 'includes/header.php';

// CREATE
if (isset($_POST['add'])) {
    $schoolnaam = $conn->real_escape_string($_POST['schoolnaam']);
    $plaats = $conn->real_escape_string($_POST['plaats']);
    $type_onderwijs = $conn->real_escape_string($_POST['type_onderwijs']);
    $conn->query("INSERT INTO school (schoolnaam, plaats, type_onderwijs) VALUES ('$schoolnaam', '$plaats', '$type_onderwijs')");
    $new_id = $conn->insert_id; // Haal het ID van de nieuw toegevoegde school op
    header("Location: scholen.php?highlight=$new_id");
    exit;
}

// UPDATE
if (isset($_POST['update'])) {
    $school_id = (int)$_POST['school_id'];
    $schoolnaam = $conn->real_escape_string($_POST['schoolnaam']);
    $plaats = $conn->real_escape_string($_POST['plaats']);
    $type_onderwijs = $conn->real_escape_string($_POST['type_onderwijs']);
    $conn->query("UPDATE school SET schoolnaam='$schoolnaam', plaats='$plaats', type_onderwijs='$type_onderwijs' WHERE school_id=$school_id");
    header("Location: scholen.php?highlight=$school_id");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $school_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM school WHERE school_id=$school_id");
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
                <i class="bi bi-building me-2"></i>Scholen
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
                                <tr<?php if ($highlight_id === (int)$row['school_id']) echo ' class="table-warning"'; ?>>
                                    <td><?= $row['school_id'] ?></td>
                                    <td><?= htmlspecialchars($row['schoolnaam']) ?></td>
                                    <td><?= htmlspecialchars($row['plaats']) ?></td>
                                    <td>
                                        <span><?= htmlspecialchars($row['type_onderwijs']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="scholen.php?edit=<?= $row['school_id'] ?>" class="btn btn-outline-primary btn-sm me-1">
                                            <i class="bi bi-pencil-square"></i> Bewerken
                                        </a>
                                        <a href="scholen.php?delete=<?= $row['school_id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Weet je het zeker?')">
                                            <i class="bi bi-trash"></i> Verwijderen
                                        </a>
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
                                    <i class="bi bi-plus-circle me-1"></i>Toevoegen
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
                $result = $conn->query("SELECT * FROM school WHERE school_id=$school_id");
                $school = $result->fetch_assoc();
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
                                    <i class="bi bi-save me-1"></i>Opslaan
                                </button>
                                <a href="scholen.php" class="btn btn-secondary w-50 d-flex align-items-center justify-content-center">
                                    <i class="bi bi-x-circle me-1"></i>Annuleren
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