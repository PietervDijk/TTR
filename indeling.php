<?php
// Verdeling: leerlingen direct indelen via tabelvelden per bezoek
require 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}


<?php require 'includes/footer.php'; ?>
