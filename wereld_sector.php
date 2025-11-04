<?php require 'includes/header.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!-- Formulier maken om werelden/sectoren toe te voegen en te beheren.
Houd rekening met actief status, en het type van de school/klas (po/vo/vmo en dergelijke). -->
<?php require 'includes/footer.php'; ?>