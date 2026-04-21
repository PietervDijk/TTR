<?php
// Placeholder: toekomstig beheer van werelden/sectoren per bezoek
require 'includes/header.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!--
    Deze pagina is nog een startpunt voor het beheer van werelden en sectoren.
    De bedoeling is later een formulier te tonen waarmee actieve sectoren per
    bezoektype beheerd kunnen worden.
-->
<?php require 'includes/footer.php'; ?>