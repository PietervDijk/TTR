<?php
session_start();
if (!isset($_SESSION['heeft_ingevuld'])) {
    header("Location: index.php");
    exit;
}
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
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="card p-5 text-center">
    <h2 class="mb-3 text-success">Bedankt!</h2>
    <p>Je voorkeuren zijn succesvol opgeslagen.<br>Je kunt deze pagina nu sluiten.</p>
</div>
</body>
</html>
