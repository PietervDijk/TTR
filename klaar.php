<?php
session_start();

// Alleen toegankelijk als een leerling al ingevuld heeft
if (!isset($_SESSION['heeft_ingevuld']) || $_SESSION['heeft_ingevuld'] !== true) {
    header("Location: index.php");
    exit;
}

// Is er een leerling gekoppeld aan deze sessie én mag hij/zij nog wijzigen?
$kanWijzigen = !empty($_SESSION['leerling_id'])
    && !empty($_SESSION['klas_id'])
    && !empty($_SESSION['mag_wijzigen']);

// Kwam je hier na een update?
$isUpdated = (isset($_GET['updated']) && $_GET['updated'] === '1');
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <title>Klaar!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e0f7ff, #f4faff);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="card p-5 text-center">
        <h2 class="mb-3 text-success">Bedankt!</h2>

        <?php if ($isUpdated): ?>
            <p>Je wijzigingen zijn succesvol opgeslagen.<br>Je kunt deze pagina nu sluiten.</p>
        <?php else: ?>
            <p>Je voorkeuren zijn succesvol opgeslagen.<br>Je kunt deze pagina nu sluiten.</p>
        <?php endif; ?>

        <?php if ($kanWijzigen): ?>
            <hr class="my-4">
            <p class="mb-2">Toch nog iets aanpassen?</p>
            <a href="index.php?edit=1" class="btn btn-outline-primary">
                Mijn keuzes wijzigen
            </a>
            <p class="mt-2 small text-muted">
                Je kunt je keuzes één keer wijzigen zolang deze browsersessie actief is op dit apparaat.
            </p>
        <?php endif; ?>
    </div>
</body>

</html>