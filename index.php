<?php
require 'includes/config.php';
require 'includes/header.php';
// ------------------------------
// Vereiste: klas_id aanwezig
// ------------------------------
if (!isset($_SESSION['klas_id']) && !isset($_GET['klas_id'])) {
    die("Geen klas geselecteerd.");
}
$klas_id = isset($_SESSION['klas_id']) ? (int)$_SESSION['klas_id'] : (int)$_GET['klas_id'];

// ------------------------------
// Als leerling al heeft ingevuld -> klaar
// (admins mogen blijven op de pagina)
// ------------------------------
if (!isset($_SESSION['admin_id']) && isset($_SESSION['heeft_ingevuld']) && $_SESSION['heeft_ingevuld'] === true) {
    header("Location: klaar.php");
    exit;
}

// ------------------------------
// Klasinstellingen ophalen: max_keuzes (2 of 3)
// ------------------------------
$stmt = $conn->prepare("SELECT max_keuzes FROM klas WHERE klas_id = ?");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$klasRes = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$klasRes) {
    die("Klas niet gevonden.");
}
$max_keuzes = (int)$klasRes['max_keuzes'];
if (!in_array($max_keuzes, [2, 3], true)) {
    // fallback, maar dit zou niet mogen gebeuren als klassen.php goed staat
    $max_keuzes = 2;
}

// ------------------------------
// Actieve voorkeuren (sectoren) van deze klas
// ------------------------------
$stmt = $conn->prepare("SELECT id, naam FROM klas_voorkeur WHERE klas_id = ? AND actief = 1 ORDER BY volgorde ASC");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$result = $stmt->get_result();
$voorkeuren = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// aantal selectvelden = min(max_keuzes, aantal beschikbare opties)
$aantal_keuzes = min($max_keuzes, count($voorkeuren));

// Mappen voor snelle validatie
$allowed_ids = array_map(fn($r) => (int)$r['id'], $voorkeuren);
$allowed_set = array_fill_keys($allowed_ids, true);

$melding = "";

// ------------------------------
// Verwerking formulier (POST)
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voornaam      = trim($_POST['voornaam'] ?? '');
    $tussenvoegsel = trim($_POST['tussenvoegsel'] ?? '');
    $achternaam    = trim($_POST['achternaam'] ?? '');

    // Lees de N keuzes in (als integers of lege string)
    $gekozen = [];
    for ($i = 1; $i <= $aantal_keuzes; $i++) {
        $key = 'voorkeur' . $i;
        $val = $_POST[$key] ?? '';
        $val = trim((string)$val);
        if ($val === '') {
            $gekozen[$i] = null;
        } elseif (ctype_digit($val)) {
            $gekozen[$i] = (int)$val;
        } else {
            $gekozen[$i] = null; // ongeldig, zal door validatie vallen
        }
    }

    // Server-side validatie
    $errors = [];
    if ($voornaam === '')       $errors[] = "Vul je voornaam in.";
    if ($achternaam === '')     $errors[] = "Vul je achternaam in.";
    if ($aantal_keuzes < 1)     $errors[] = "Er zijn (nog) geen keuzes beschikbaar voor deze klas.";
    // Alle N keuzes verplicht invullen
    for ($i = 1; $i <= $aantal_keuzes; $i++) {
        if ($gekozen[$i] === null) {
            $errors[] = "Vul je {$i}e keuze in.";
        }
    }
    // Moeten uniek zijn en geldig binnen deze klas
    $vals = array_values(array_filter($gekozen, fn($v) => $v !== null));
    if (count($vals) !== count(array_unique($vals))) {
        $errors[] = "Kies per voorkeur een andere sector (geen dubbele keuzes).";
    }
    foreach ($vals as $id) {
        if (!isset($allowed_set[$id])) {
            $errors[] = "Onjuiste keuze gedetecteerd. Vernieuw de pagina en probeer opnieuw.";
            break;
        }
    }

    if (empty($errors)) {
        // Bouw kolommen voor opslag (we vullen alleen de eerste 5 posities zoals DB heeft)
        $v1 = $gekozen[1] ?? null;
        $v2 = $gekozen[2] ?? null;
        $v3 = $gekozen[3] ?? null;
        $v4 = null;
        $v5 = null;

        // Insert
        $stmt = $conn->prepare("
            INSERT INTO leerling (klas_id, voornaam, tussenvoegsel, achternaam, voorkeur1, voorkeur2, voorkeur3, voorkeur4, voorkeur5)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // voorkeur1..5 zijn integers of null; bind als i of null -> gebruik 'i' en geef null als NULL via bind_param (mysqli zet dat goed met null)
        $stmt->bind_param(
            "isssiiiii",
            $klas_id,
            $voornaam,
            $tussenvoegsel,
            $achternaam,
            $v1,
            $v2,
            $v3,
            $v4,
            $v5
        );
        $stmt->execute();
        $stmt->close();

        if (isset($_SESSION['admin_id'])) {
            $melding = "<div class='alert alert-success text-center mb-3'>Leerling succesvol toegevoegd!</div>";
            // leeg alleen de keuzes; namen mag je laten staan voor sneller invoeren
            for ($i = 1; $i <= $aantal_keuzes; $i++) {
                unset($_POST['voorkeur' . $i]);
            }
        } else {
            $_SESSION['heeft_ingevuld'] = true;
            header("Location: klaar.php");
            exit;
        }
    } else {
        // Toon nette foutmelding
        $melding = "<div class='alert alert-danger mb-3'><strong>Controleer je invoer:</strong><ul class='mb-0'>"
            . implode('', array_map(fn($e) => "<li>" . htmlspecialchars($e) . "</li>", $errors))
            . "</ul></div>";
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
            box-shadow: 0 8px 20px rgba(0, 0, 0, .1);
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
                <h2 class="text-center mb-1">Voer je voorkeuren in</h2>
                <p class="text-center text-muted mb-4">Je mag <strong><?= htmlspecialchars((string)$aantal_keuzes) ?></strong> keuzes maken voor deze klas.</p>

                <?= $melding ?>

                <form method="post" id="leerlingForm">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Voornaam *</label>
                            <input type="text" name="voornaam" class="form-control" required
                                value="<?= htmlspecialchars($_POST['voornaam'] ?? '') ?>">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Tussenvoegsel</label>
                            <input type="text" name="tussenvoegsel" class="form-control"
                                value="<?= htmlspecialchars($_POST['tussenvoegsel'] ?? '') ?>">
                        </div>

                        <div class="col-md-5 mb-3">
                            <label class="form-label">Achternaam *</label>
                            <input type="text" name="achternaam" class="form-control" required
                                value="<?= htmlspecialchars($_POST['achternaam'] ?? '') ?>">
                        </div>
                    </div>


                    <hr>

                    <?php if ($aantal_keuzes < 1): ?>
                        <div class="alert alert-warning">Er zijn nog geen sectoren/werelden actief voor deze klas. Neem contact op met de docent.</div>
                    <?php else: ?>
                        <?php for ($i = 1; $i <= $aantal_keuzes; $i++): ?>
                            <div class="mb-3">
                                <label class="form-label"><?= $i ?>e keuze *</label>
                                <select
                                    name="voorkeur<?= $i ?>"
                                    id="voorkeur<?= $i ?>"
                                    class="form-select voorkeur-select"
                                    required>
                                    <option value="" disabled <?= empty($_POST['voorkeur' . $i]) ? 'selected' : '' ?>>Maak je keuze</option>
                                    <?php foreach ($voorkeuren as $opt): ?>
                                        <?php $sel = (isset($_POST['voorkeur' . $i]) && $_POST['voorkeur' . $i] !== '' && (int)$_POST['voorkeur' . $i] === (int)$opt['id']) ? 'selected' : ''; ?>
                                        <option value="<?= (int)$opt['id'] ?>" <?= $sel ?>><?= htmlspecialchars($opt['naam']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endfor; ?>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary w-100 mt-3">Opslaan</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Disable dubbele keuzes in de UI (client-side hulp; server-side blijft leidend)
        document.addEventListener("DOMContentLoaded", function() {
            const selects = document.querySelectorAll(".voorkeur-select");
            const updateOptions = () => {
                const selectedValues = Array.from(selects).map(s => s.value).filter(v => v !== "");
                selects.forEach(select => {
                    Array.from(select.options).forEach(option => {
                        if (option.value === "") return;
                        // disable als in andere select gekozen
                        option.disabled = selectedValues.includes(option.value) && select.value !== option.value;
                    });
                });
            };
            selects.forEach(select => select.addEventListener("change", updateOptions));
            updateOptions();
        });
    </script>
</body>

</html>