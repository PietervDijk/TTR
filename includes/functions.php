<?php

/**
 * HTML-escape helper: korte schrijfwijze voor htmlspecialchars
 */
function e(mixed $waarde): string
{
    return htmlspecialchars((string)$waarde, ENT_QUOTES, 'UTF-8');
}

/**
 * Genereer array van schooljaren rond huidige jaar
 * Format: "2025 - 2026"
 */
function get_schooljaren(int $jaarTerug = 1, int $jaarVooruit = 2): array
{
    $huidig_jaar = (int)date('Y');
    $huidige_maand = (int)date('n');
    $start_jaar_huidig = ($huidige_maand >= 8) ? $huidig_jaar : $huidig_jaar - 1;

    $schooljaren = [];
    for ($i = -$jaarTerug; $i <= $jaarVooruit; $i++) {
        $start_jaar = $start_jaar_huidig + $i;
        $schooljaren[] = $start_jaar . ' - ' . ($start_jaar + 1);
    }

    return $schooljaren;
}

/**
 * Geef hudig schooljaar terug als "YYYY - YYYY"
 */
function get_huidig_schooljaar(): string
{
    $huidig_jaar = (int)date('Y');
    $huidige_maand = (int)date('n');
    $start_jaar = ($huidige_maand >= 8) ? $huidig_jaar : $huidig_jaar - 1;

    return $start_jaar . ' - ' . ($start_jaar + 1);
}

/**
 * Controleer of schooljaar geldig formaat is (YYYY - YYYY)
 */
function is_geldig_schooljaar(string $schooljaar, int $jaarTerug = 2, int $jaarVooruit = 3): bool
{
    $schooljaar = preg_replace('/\s+/', ' ', trim($schooljaar));
    if ($schooljaar === '') {
        return false;
    }

    if (in_array($schooljaar, get_schooljaren($jaarTerug, $jaarVooruit), true)) {
        return true;
    }

    if (!preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $schooljaar, $matches)) {
        return false;
    }

    return ((int)$matches[2] === ((int)$matches[1] + 1));
}

// ==========================
// CSRF HELPER FUNCTIES
// ==========================

// Debug mode helper (schakelt aan met ?debug=true en onthoudt in sessie)
function is_debug_mode(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // If explicit GET param provided, update session flag
    if (isset($_GET['debug'])) {
        $val = strtolower((string)$_GET['debug']);
        $flag = in_array($val, ['1', 'true', 'on'], true);
        // allow explicit false with debug=false
        if (in_array($val, ['0', 'false', 'off'], true)) {
            $flag = false;
        }
        $_SESSION['__debug_mode'] = $flag;
        return $flag;
    }

    return !empty($_SESSION['__debug_mode']);
}

function enable_debug_mode(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Calling is_debug_mode will process any ?debug= param and set session accordingly
    if (is_debug_mode()) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
        debug_log('Debug mode enabled (session).');
    } else {
        debug_log('Debug mode not enabled.');
    }
}

// verzamelt korte debug-berichten die in header kunnen worden weergegeven
function debug_log(string $msg): void
{
    if (!is_debug_mode()) {
        return;
    }

    if (!isset($GLOBALS['__debug_messages'])) {
        $GLOBALS['__debug_messages'] = [];
    }

    $GLOBALS['__debug_messages'][] = $msg;
}

// Zorg dat er altijd een token is
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Hidden input veld genereren (voor forms)
function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// Valideer CSRF token bij POST
function csrf_validate(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (is_debug_mode()) {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        debug_log('CSRF validation skipped in debug mode. POST token=' . ($token === '' ? '<empty>' : '[present]') . ' session_token=' . (empty($_SESSION['csrf_token']) ? '<empty>' : '[present]'));
        return;
    }

    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (
        empty($_SESSION['csrf_token']) ||
        empty($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        die('Ongeldige CSRF-token.');
    }
}

// (Optioneel) token vernieuwen na succesvolle actie
function csrf_regenerate(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}