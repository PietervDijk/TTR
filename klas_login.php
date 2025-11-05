<?php
require 'includes/header.php';

if (isset($_POST['submit'])) {
    $pincode = $_POST['pincode'];

    $stmt = $conn->prepare("SELECT klas_id FROM klas WHERE pincode = ?");
    $stmt->bind_param("s", $pincode);
    $stmt->execute();
    $result = $stmt->get_result();
    $klas = $result->fetch_assoc();
    $stmt->close();

    if ($klas) {
        $_SESSION['klas_id'] = $klas['klas_id'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Wachtwoord klopt niet!";
    }
}
?>

<div class="container py-5">
    <h2 class="text-center mb-3">Voer het klas-wachtwoord in</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger text-center"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" class="col-lg-4 mx-auto">
        <input type="text" name="pincode" class="form-control mb-3 text-center" placeholder="Klas wachtwoord" required>
        <button type="submit" name="submit" class="btn btn-primary w-100">Doorgaan</button>
    </form>
</div>

<?php require 'includes/footer.php'; ?>
