<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<link rel="stylesheet" href="css/index.css">

<nav class="navbar navbar-expand-lg bg-white shadow-sm py-3">
    <div class="container d-flex align-items-center">

        <!-- LOGO LINKS -->
        <a href="klas_login.php" class="navbar-brand d-flex align-items-center">
            <img src="images/technolablogo.png" alt="Technolab Logo" style="height:45px;">
        </a>

        <!-- TOGGLER VOOR MOBIEL -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- MENU ITEMS -->
        <div class="collapse navbar-collapse" id="mainNav">

            <!-- MIDDELSTE ITEMS -->
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 text-center">

                <?php if (isset($_SESSION['admin_id'])): ?>
                    <li class="nav-item mx-2">
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF'])=='index.php') echo 'active'; ?>" href="/TTR/index.php">Home</a>
                    </li>

                    <li class="nav-item mx-2">
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF'])=='scholen.php') echo 'active'; ?>" href="/TTR/scholen.php">Scholen</a>
                    </li>

                    <li class="nav-item mx-2">
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF'])=='klas_login.php') echo 'active'; ?>" href="/TTR/klas_login.php">Klas wachtwoord</a>
                    </li>
                <?php endif; ?>

            </ul>

            <!-- RECHTERKANT IN/UITLOGGEN -->
            <div class="d-flex">
                <?php if (isset($_SESSION['admin_id'])): ?>
                    <a href="uitloggen.php" class="btn btn-danger">Uitloggen</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-success">Inloggen</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
