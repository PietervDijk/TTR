<?php
if (session_status() == PHP_SESSION_NONE) { 
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3 navbar-custom">
    <div class="container">
        <!-- Merknaam -->
        <a class="navbar-brand fw-bold text-primary" href="/TTR/index.php">
            <i class="bi bi-mortarboard-fill me-2 fs-3"></i>Technolab
        </a>

        <!-- Hamburger menu voor mobiel -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (isset($_SESSION['admin_id'])): ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item mx-1">
                        <a class="nav-link px-3 rounded-pill <?php if (basename($_SERVER['PHP_SELF']) == 'index.php') echo ' active'; ?>" href="/TTR/index.php">Home</a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link px-3 rounded-pill <?php if (basename($_SERVER['PHP_SELF']) == 'scholen.php') echo ' active'; ?>" href="/TTR/scholen.php">Scholen</a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link px-3 rounded-pill <?php if (basename($_SERVER['PHP_SELF']) == 'klas_login.php') echo ' active'; ?>" href="/TTR/klas_login.php">klas wachtwoord</a>
                    </li>
                </ul>

                <!-- Admin knoppen -->
                <div class="d-flex ms-auto">
                    <a href="uitloggen.php" class="btn btn-danger">Uitloggen</a>
                </div>
            <?php else: ?>
                <!-- Alleen inloggen knop voor niet ingelogde gebruikers -->
                <div class="d-flex ms-auto">
                    <a href="login.php" class="btn btn-success">Inloggen</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>