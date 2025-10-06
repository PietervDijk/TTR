<?php

$host = 'localhost';
$user = 'root';
$password =  '';
$databaseName = 'tntrandomizer';

$conn = mysqli_connect($host, $user, $password, $databaseName);

// Controleer de verbinding
if (mysqli_connect_error()) {
    // echo "Connection establishing failed!";
} else {
    // echo "Connection established successfully.";
}
