<?php
require 'header.php';

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

<h2>Scholen</h2>
<table border="1">
    <tr>
        <th>ID</th>
        <th>School</th>
        <th>Plaats</th>
        <th>Acties</th>
    </tr>
    <?php while ($row = $scholen->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['school']) ?></td>
            <td><?= htmlspecialchars($row['plaats']) ?></td>
            <td>
                <a href="scholen.php?edit=<?= $row['id'] ?>">Bewerken</a>
                <a href="scholen.php?delete=<?= $row['id'] ?>" onclick="return confirm('Weet je het zeker?')">Verwijderen</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

<h2>School toevoegen</h2>
<form method="post">
    <input type="text" name="school" placeholder="School" required>
    <input type="text" name="plaats" placeholder="Plaats" required>
    <button type="submit" name="add">Toevoegen</button>
</form>

<?php
// EDIT FORM
if (isset($_GET['edit'])):
    $id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM scholen WHERE id=$id");
    $school = $result->fetch_assoc();
?>
    <h2>School bewerken</h2>
    <form method="post">
        <input type="hidden" name="id" value="<?= $school['id'] ?>">
        <input type="text" name="school" value="<?= htmlspecialchars($school['school']) ?>" required>
        <input type="text" name="plaats" value="<?= htmlspecialchars($school['plaats']) ?>" required>
        <button type="submit" name="update">Opslaan</button>
    </form>
<?php endif; ?>