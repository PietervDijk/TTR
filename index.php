<?php
require 'includes/config.php';
require 'includes/header.php';


// Controleer of klas_id bestaat
if (!isset($_SESSION['klas_id']) && !isset($_GET['klas_id'])) {
    die("Geen klas geselecteerd.");
}

$klas_id = $_SESSION['klas_id'] ?? (int)$_GET['klas_id'];

// 🧠 Controleer of leerling al ingevuld heeft
if (!isset($_SESSION['admin_id']) && isset($_SESSION['heeft_ingevuld']) && $_SESSION['heeft_ingevuld'] === true) {
    header("Location: klaar.php");
    exit;
}

// 🟢 Haal voorkeuren op uit de database
$stmt = $conn->prepare("SELECT id, naam FROM klas_voorkeur WHERE klas_id = ? AND actief = 1 ORDER BY volgorde ASC");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$result = $stmt->get_result();
$voorkeuren = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$melding = "";

// 🟢 Verwerking formulier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voornaam = trim($_POST['voornaam']);
    $tussenvoegsel = trim($_POST['tussenvoegsel'] ?? '');
    $achternaam = trim($_POST['achternaam']);
    $voorkeur1 = $_POST['voorkeur1'] ?? null;
    $voorkeur2 = $_POST['voorkeur2'] ?? null;
    $voorkeur3 = $_POST['voorkeur3'] ?? null;

    if ($voornaam && $achternaam && $voorkeur1) {
        $stmt = $conn->prepare("
            INSERT INTO leerling (klas_id, voornaam, tussenvoegsel, achternaam, voorkeur1, voorkeur2, voorkeur3)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssiii", $klas_id, $voornaam, $tussenvoegsel, $achternaam, $voorkeur1, $voorkeur2, $voorkeur3);
        $stmt->execute();
        $stmt->close();

        // ✅ Als admin — blijf op pagina
        if (isset($_SESSION['admin_id'])) {
            $melding = "<div class='alert alert-success text-center mb-3'>Leerling succesvol toegevoegd!</div>";
        } else {
            // ✅ Leerling — markeer als ingevuld en stuur door
            $_SESSION['heeft_ingevuld'] = true;
            header("Location: klaar.php");
            exit;
        }
    } else {
        $melding = "<div class='alert alert-danger text-center mb-3'>Vul alle verplichte velden in.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Voer je voorkeuren in</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f0f4ff, #d9e4ff);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2d3e50;
            font-weight: 700;
        }
        .btn-primary {
            background: #4666ff;
            border: none;
            border-radius: 12px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: #324bdf;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="col-lg-6 mx-auto">
        <div class="card p-4">
            <h2 class="text-center mb-4">Voer je voorkeuren in</h2>

            <?= $melding ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Voornaam *</label>
                    <input type="text" name="voornaam" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tussenvoegsel</label>
                    <input type="text" name="tussenvoegsel" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Achternaam *</label>
                    <input type="text" name="achternaam" class="form-control" required>
                </div>

                <hr>

                <?php
                $aantal_keuzes = min(5, count($voorkeuren));
                for ($i = 1; $i <= $aantal_keuzes; $i++):
                    ?>
                    <div class="mb-3">
                        <label class="form-label"><?= $i ?>e keuze</label>
                        <select name="voorkeur<?= $i ?>" id="voorkeur<?= $i ?>" class="form-select voorkeur-select" <?= $i === 1 ? 'required' : '' ?>>
                            <option value="" selected disabled>Maak je keuze</option>
                            <?php foreach ($voorkeuren as $opt): ?>
                                <option value="<?= $opt['id'] ?>"><?= ($opt['naam']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endfor; ?>

                <button type="submit" class="btn btn-primary w-100 mt-3">Opslaan</button>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const selects = document.querySelectorAll(".voorkeur-select");
        selects.forEach(select => select.addEventListener("change", updateOptions));

        function updateOptions() {
            const selectedValues = Array.from(selects).map(s => s.value).filter(v => v !== "");
            selects.forEach(select => {
                Array.from(select.options).forEach(option => {
                    if (option.value === "") return;
                    option.disabled = selectedValues.includes(option.value) && select.value !== option.value;
                });
            });
        }
    });
</script>
</body>
</html>
