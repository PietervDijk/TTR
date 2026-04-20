<?php
/*
 * Navigatie-template:
 * toont andere menu-items voor admin en leerling.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$huidige_pagina = $huidige_pagina ?? basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="css/index.css">

<nav class="navbar navbar-expand-lg navbar-custom py-3">
    <div class="container d-flex align-items-center justify-content-between">

        <!-- Logo links -->
        <a href="index.php" class="navbar-brand">
            <img src="images/logo_technolab.svg" class="logo-technolab" alt="Technolab">
        </a>

        <!-- Navigatieknop voor kleine schermen -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Hoofdmenu -->
        <div class="collapse navbar-collapse" id="mainNav">

            <!-- Centrale navigatie-items -->
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">

                <?php $is_home = $huidige_pagina === 'index.php'; ?>

                <li class="nav-item">
                    <a class="nav-link <?php if ($is_home) echo 'active'; ?>" href="index.php">
                        <i class="bi bi-house-door"></i> Home
                    </a>
                </li>

                <?php if (isset($_SESSION['admin_id'])): ?>

                    <?php $is_scholen = $huidige_pagina === 'scholen.php'; ?>
                    <?php $is_bezoeken = $huidige_pagina === 'bezoeken.php'; ?>
                    <?php $is_klas_login = $huidige_pagina === 'klas_login.php'; ?>

                    <li class="nav-item">
                        <a class="nav-link <?php if ($is_scholen) echo 'active'; ?>" href="scholen.php">
                            <i class="bi bi-building"></i> Scholen
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php if ($is_bezoeken) echo 'active'; ?>" href="bezoeken.php">
                            <i class="bi bi-calendar-check"></i> Bezoeken
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php if ($is_klas_login) echo 'active'; ?>" href="klas_login.php">
                            <i class="bi bi-key"></i> Klas wachtwoord
                        </a>
                    </li>
                <?php else: ?>

                    <?php $is_klas_login = $huidige_pagina === 'klas_login.php'; ?>

                    <li class="nav-item">
                        <a class="nav-link <?php if ($is_klas_login) echo 'active'; ?>" href="klas_login.php">
                            <i class="bi bi-door-open"></i> Klas inloggen
                        </a>
                    </li>
                <?php endif; ?>

            </ul>

            <!-- Rechterzijde: in- en uitloggen -->
            <div class="d-flex gap-2 mt-3 mt-lg-0">
                <?php if (isset($_SESSION['admin_id'])): ?>
                    <a href="uitloggen.php" class="btn btn-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Uitloggen
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-success btn-sm">
                        <i class="bi bi-box-arrow-in-right"></i> Admin Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>