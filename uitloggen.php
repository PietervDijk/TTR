<?php
/*
 * PAGINA-UITLEG
 * -------------------------------------------------
 * Volledige uitlogflow:
 * - sessievariabelen leegmaken
 * - sessie beëindigen
 * - terugsturen naar startpagina
 */
session_start();
session_unset();
session_destroy();
header('Location: index.php');
exit;
