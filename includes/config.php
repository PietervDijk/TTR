<?php
// Laad centraal databaseconfiguratie en verbinding
require_once __DIR__ . '/functions.php';

$servernaam = 'localhost';
$gebruikersnaam = 'root';
$wachtwoord = '';
$database_naam = 'tntrandomizer';

$conn = mysqli_connect($servernaam, $gebruikersnaam, $wachtwoord, $database_naam);

// Verbinding silently geopend; foutafhandeling gebeurt elders
if (mysqli_connect_error()) {
    // Geen output van technische details
} else {
    // Verbinding OK
}
