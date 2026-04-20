<?php

/*
 * Centrale databaseconfiguratie.
 * Elke pagina die met MySQL werkt, include dit bestand.
 */

require_once __DIR__ . '/functions.php';

$servernaam = 'localhost';
$gebruikersnaam = 'root';
$wachtwoord = '';
$database_naam = 'tntrandomizer';

$conn = mysqli_connect($servernaam, $gebruikersnaam, $wachtwoord, $database_naam);

// De verbinding wordt bewust stil geopend; foutafhandeling gebeurt elders.
if (mysqli_connect_error()) {
    // Intentioneel leeg: we willen hier geen technische details tonen.
} else {
    // Intentioneel leeg: succes wordt niet op scherm gemeld.
}
