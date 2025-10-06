<?php
require 'includes/header.php';

// CREATE
if (isset($_POST['add'])) {
    $school = $conn->real_escape_string($_POST['school']);
    $plaats = $conn->real_escape_string($_POST['plaats']);
    $conn->query("INSERT INTO scholen (school, plaats) VALUES ('$school', '$plaats')");
    header("Location: scholen.php");
    exit;
}

// UPDATE
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $school = $conn->real_escape_string($_POST['school']);
    $plaats = $conn->real_escape_string($_POST['plaats']);
    $conn->query("UPDATE scholen SET school='$school', plaats='$plaats' WHERE id=$id");
    header("Location: scholen.php");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM scholen WHERE id=$id");
    header("Location: scholen.php");
    exit;
}

// READ
$scholen = $conn->query("SELECT * FROM scholen");
?>

<div class="container mt-5">
    <h2 class="mb-4">Scholen</h2>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>School</th>
                <th>Plaats</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $scholen->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['school']) ?></td>
                    <td><?= htmlspecialchars($row['plaats']) ?></td>
                    <td>
                        <a href="scholen.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Bewerken</a>
                        <a href="scholen.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Weet je het zeker?')">Verwijderen</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="card mt-4">
        <div class="card-header">
            School toevoegen
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="school" class="form-control" placeholder="School" required>
                </div>
                <div class="col-md-5">
                    <input type="text" name="plaats" class="form-control" placeholder="Plaats" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add" class="btn btn-success w-100">Toevoegen</button>
                </div>
            </form>
        </div>
    </div>

    <?php
    // EDIT FORM
    if (isset($_GET['edit'])):
        $id = (int)$_GET['edit'];
        $result = $conn->query("SELECT * FROM scholen WHERE id=$id");
        $school = $result->fetch_assoc();
    ?>
        <div class="card mt-4">
            <div class="card-header">
                School bewerken
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="id" value="<?= $school['id'] ?>">
                    <div class="col-md-5">
                        <input type="text" name="school" class="form-control" value="<?= htmlspecialchars($school['school']) ?>" required>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="plaats" class="form-control" value="<?= htmlspecialchars($school['plaats']) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="update" class="btn btn-primary w-100">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require 'includes/footer.php'; ?>