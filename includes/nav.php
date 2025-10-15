<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow">
    <div class="container">
        <!-- <a class="navbar-brand fw-bold d-flex align-items-center" href="/TTR/index.php">
            <i class="bi bi-mortarboard-fill me-2 fs-3 text-primary"></i>TTR
        </a> -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item mx-1">
                    <a class="nav-link d-flex align-items-center px-3 rounded-pill<?php if (basename($_SERVER['PHP_SELF']) == 'index.php') echo ' active bg-primary text-white'; ?>" href="/TTR/index.php">
                        Home
                    </a>
                </li>
                <li class="nav-item mx-1">
                    <a class="nav-link d-flex align-items-center px-3 rounded-pill<?php if (basename($_SERVER['PHP_SELF']) == 'scholen.php') echo ' active bg-primary text-white'; ?>" href="/TTR/scholen.php">
                        Scholen
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>