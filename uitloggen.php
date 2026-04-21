<?php
// Logout-flow: wis sessie en redirect naar index
session_start();
session_unset();
session_destroy();
header('Location: index.php');
exit;
