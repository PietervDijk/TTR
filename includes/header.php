<?php
// Header-template: config, pagina-bepaling, HTML head/body openen
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$huidige_pagina = basename($_SERVER['PHP_SELF']);
?>
<?php
if (function_exists('is_debug_mode') && is_debug_mode()) {
    echo '<div style="background:#ffe6e6;color:#800; padding:8px; text-align:center; font-weight:600;">DEBUG MODE: CSRF checks are bypassed and PHP errors are shown</div>';

    // show some session/debug info
    $sid = session_id();
    $csrf = $_SESSION['csrf_token'] ?? '<niet gezet>';
    echo '<div style="background:#fffbe6;color:#333;padding:6px;font-size:13px;border-bottom:1px solid #f0e6b6;">Session: ' . htmlspecialchars($sid, ENT_QUOTES, 'UTF-8') . ' &nbsp; | &nbsp; CSRF token: ' . htmlspecialchars(substr((string)$csrf, 0, 12), ENT_QUOTES, 'UTF-8') . (strlen((string)$csrf) > 12 ? '...' : '') . '</div>';

    if (!empty($GLOBALS['__debug_messages'])) {
        echo '<div style="padding:8px;background:#f7f7f7;color:#222;font-size:13px;">';
        foreach ($GLOBALS['__debug_messages'] as $m) {
            echo '<div style="margin-bottom:4px;">' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        echo '</div>';
    }
}
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