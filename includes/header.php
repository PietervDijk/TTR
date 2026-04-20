<?php
/*
 * Header-template:
 * - laadt configuratie
 * - bepaalt actieve pagina (voor menuhighlight)
 * - start HTML head/body structuur
 */
require_once 'config.php';

$huidige_pagina = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Randomizer T&T</title>

    <!-- Bootstrap 5.3 CSS: basis voor alle pagina-opmaak -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons: gebruikt voor de pictogrammen in de navigatie en kaarten -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet">

    <!-- Google Fonts: vaste typografie voor het hele portaal -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Hoofd CSS: alle Technolab-specifieke stijlen -->
    <link rel="stylesheet" href="css/index.css">
</head>

<body class="ttr-app">

    <?php require 'nav.php'; ?>

    <!-- Hier komt de pagina-inhoud -->