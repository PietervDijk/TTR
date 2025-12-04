<?php
require_once 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Randomizer T&T</title>

    <!-- Bootstrap CSS (v4.4) -->
    <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css"
        integrity="sha384-Vkoo...Ifjh"
        crossorigin="anonymous">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Hoofd CSS -->
    <link rel="stylesheet" href="css/index.css">
    <!-- Bootstrap 5 bundle (Popper + Bootstrap JS). Voeg toe in includes/footer.php of vlak boven </body> -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>
    <?php require 'nav.php'; ?>

    <!-- hier komt je page content -->