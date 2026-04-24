<?php
// Logout-flow: wis sessie en redirect naar index
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: index.php');
	exit;
}

csrf_validate();

session_unset();
session_destroy();
header('Location: index.php');
exit;
