<?php
session_start();

// Als geen klas → geen toegang
if (!isset($_SESSION['klas_id'])) {
    header("Location: klas_login.php");
    exit;
}

// Leerling mag niet terug naar formulier → sessie verbreken
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Klaar!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light d-flex justify-content-center align-items-center vh-100">

<div class="card text-center p-4 shadow-lg border-0" style="max-width: 450px;">
    <h3 class="text-success fw-bold mb-3">🎉 Je bent klaar!</h3>
    <p class="mb-4">Goed gedaan! Je hebt alles ingevuld.</p>

    <a href="https://www.google.com" class="btn btn-primary" target="_blank">
        Sluit scherm
    </a>
</div>

</body>
</html>
