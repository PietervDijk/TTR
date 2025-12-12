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
    <title>Admin Login – TTR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/index.css">
</head>

<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-shield-lock"></i>
                <h3>Admin Login</h3>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-login" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control form-control-login"
                        placeholder="admin@example.com"
                        required
                        autofocus>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Wachtwoord</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control form-control-login"
                        placeholder="Voer uw wachtwoord in"
                        required>
                </div>

                <div class="button-group-login">
                    <button type="submit" name="login" class="btn btn-primary btn-login">
                        <i class="bi bi-box-arrow-in-right"></i> Inloggen
                    </button>
                    <a href="klas_login.php" class="btn btn-secondary btn-login">
                        <i class="bi bi-x-circle"></i> Annuleren
                    </a>
                </div>
            </form>

            <div class="login-footer">
                <p><i class="bi bi-info-circle"></i> Alleen voor administrators</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>