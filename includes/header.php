<?php
// Header-template: config, pagina-bepaling, HTML head/body openen
require_once 'config.php';

$huidige_pagina = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Randomizer T&T</title>

    <!-- Bootstrap 5.3: basis-opmaak -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons: pictogrammen -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet">

    <!-- Google Fonts: typografie -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/index.css">
</head>

<body class="ttr-app">

    <?php require 'nav.php'; ?>

    <!-- Hier komt de pagina-inhoud -->