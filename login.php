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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login – TTR</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
</head>

<body class="ttr-app login-page">

    <div class="login-wrap container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5">

                <div class="login-card">
                    <div class="login-header">
                        <div class="login-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <div>
                            <h3 class="mb-0">Admin Login</h3>
                            <small class="text-muted">Alleen voor administrators</small>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-login" role="alert">
                            <i class="bi bi-exclamation-circle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" autocomplete="off">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group input-group-login">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    placeholder="admin@example.com"
                                    required
                                    autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Wachtwoord</label>
                            <div class="input-group input-group-login">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Voer uw wachtwoord in"
                                    required>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="login" class="btn btn-primary btn-login">
                                <i class="bi bi-box-arrow-in-right"></i> Inloggen
                            </button>
                            <a href="klas_login.php" class="btn btn-outline-secondary btn-login">
                                <i class="bi bi-x-circle"></i> Terug
                            </a>
                        </div>

                        <div class="login-hint mt-4">
                            <i class="bi bi-info-circle"></i>
                            Tip: gebruik je admin account gegevens.
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>