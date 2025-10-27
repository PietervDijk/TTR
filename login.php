
<?php
session_start();
require('includes/config.php');

$error = '';

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $wachtwoord = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, email, password, naam FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($admin = $result->fetch_assoc()) {
        // PLAIN TEXT vergelijking (onveilig)
        if ($wachtwoord === $admin['password']) {
            // Login succesvol
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_naam'] = $admin['naam'];
            header('Location: index.php');
            exit;
        } else {
            $error = "Ongeldig wachtwoord";
        }
    } else {
        $error = "Geen admin gevonden met dit emailadres";
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
<div class="card p-4 shadow" style="width: 350px;">
    <h3 class="text-center mb-3">Admin Login</h3>
    <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Wachtwoord</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" name="login" class="btn btn-primary w-100">Inloggen</button>
    </form>
</div>
</body>
</html>
