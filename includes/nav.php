<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<link rel="stylesheet" href="css/index.css">

<nav class="navbar navbar-expand-lg navbar-custom py-3">
    <div class="container d-flex align-items-center justify-content-between">

        <!-- LOGO LINKS -->
        <a href="klas_login.php" class="navbar-brand">
            <img src="images/logo_technolab.svg" class="logo-technolab" alt="Technolab">
        </a>

        <!-- TOGGLER VOOR MOBIEL -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- MENU ITEMS -->
        <div class="collapse navbar-collapse" id="mainNav">

            <!-- MIDDELSTE ITEMS -->
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">

                <?php if (isset($_SESSION['admin_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'index.php') echo 'active'; ?>" href="index.php">
                            <i class="bi bi-house-door"></i> Home
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'scholen.php') echo 'active'; ?>" href="scholen.php">
                            <i class="bi bi-building"></i> Scholen
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'klas_login.php') echo 'active'; ?>" href="klas_login.php">
                            <i class="bi bi-key"></i> Klas wachtwoord
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'klas_login.php') echo 'active'; ?>" href="klas_login.php">
                            <i class="bi bi-door-open"></i> Klas inloggen
                        </a>
                    </li>
                <?php endif; ?>

            </ul>

            <!-- RECHTERKANT IN/UITLOGGEN -->
            <div class="d-flex gap-2 mt-3 mt-lg-0">
                <?php if (isset($_SESSION['admin_id'])): ?>
                    <a href="uitloggen.php" class="btn btn-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Uitloggen
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-success btn-sm">
                        <i class="bi bi-box-arrow-in-right"></i> Inloggen voor Administrators
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>