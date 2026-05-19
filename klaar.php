<?php
// Bevestigingspagina na opslaan van voorkeuren (met optie om wijzigingen aan te brengen)
session_start();

// Controleer of leerling al ingevuld heeft
if (!isset($_SESSION['heeft_ingevuld']) || $_SESSION['heeft_ingevuld'] !== true) {
    header("Location: index.php");
    exit;
}

// Bepaal of leerling mag wijzigen en of dit een update is
$magWijzigen = !empty($_SESSION['leerling_id'])
    && !empty($_SESSION['klas_id'])
    && !empty($_SESSION['mag_wijzigen']);

$isBijgewerkt = (isset($_GET['updated']) && $_GET['updated'] === '1');
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <title>Klaar!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
</head>

<body class="ttr-app" style="display: flex; flex-direction: column;">
    <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div class="card p-5 text-center" style="max-width: 560px;">
            <h2 class="mb-3 text-success">Bedankt!</h2>

            <?php if ($isBijgewerkt): ?>
                <p>Je wijzigingen zijn succesvol opgeslagen.<br>Je kunt deze pagina nu sluiten.</p>
            <?php else: ?>
                <p>Je voorkeuren zijn succesvol opgeslagen.<br>Je kunt deze pagina nu sluiten.</p>
            <?php endif; ?>

            <?php if ($magWijzigen): ?>
                <hr class="my-4">
                <p class="mb-2">Toch nog iets aanpassen?</p>
                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    <a href="index.php?edit=1" class="btn btn-primary">
                        Mijn keuzes wijzigen
                    </a>
                    <!-- <a href="klas_login.php?reset=1" class="btn btn-success">
                        Nog een leerling invoeren
                    </a> -->
                </div>
                <p class="mt-2 small text-muted">
                    Je kunt je keuzes één keer wijzigen zolang deze browsersessie actief is op dit apparaat.
                    <!-- Met de tweede knop kun je terug naar het begin om een nieuwe leerling in te voeren. -->
                </p>
            <?php endif; ?>
        </div>
    </div>

    <footer class="technolab-footer mt-auto">
        <div class="technolab-footer__purple"></div>
        <div class="technolab-footer__white">
            <div class="container py-3">
                <div class="text-center small">
                    &copy; <?= date('Y') ?> Technolab. Alle rechten voorbehouden.
                </div>
            </div>
        </div>
    </footer>
</body>

</html>