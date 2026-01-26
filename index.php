<?php
require 'includes/config.php';
require 'includes/header.php';

// -------------------------------------------------
// GEEN KLAS GESELECTEERD → INFO-PAGINA TONEN
// -------------------------------------------------
if (!isset($_SESSION['klas_id']) && !isset($_GET['klas_id'])) {
?>
    <div class="ttr-app">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-md-10">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-primary text-white text-center py-4">
                            <i class="bi bi-info-circle"></i>
                            <strong>Welkom bij het Technolab keuzeportaal</strong>
                        </div>

                        <div class="card-body p-4">
                            <p>
                                Op deze site kunnen leerlingen hun voorkeur aangeven voor een
                                <strong>wereld of sector</strong> die zij een week lang bij
                                <strong>Technolab</strong> willen volgen.
                            </p>

                            <p>
                                De gemaakte keuzes worden vervolgens verwerkt door een
                                <strong>indelingsalgoritme</strong>. Daarna controleert een
                                administrator of alle leerlingen correct zijn ingedeeld en
                                of de indeling definitief kan worden verklaard. Zodra de
                                indeling definitief is, wordt deze aangehouden.
                            </p>

                            <p>
                                Om het keuzeformulier te kunnen invullen is eerst aanvullende
                                informatie nodig, zoals gegevens over de school en de klas.
                                Deze gegevens worden ingevoerd door een beheerder van deze site.
                            </p>

                            <p>
                                Het keuzeformulier is beveiligd met een wachtwoord. Zo zorgen
                                we ervoor dat leerlingen alleen toegang hebben tot de keuzes
                                van hun eigen klas en school.
                            </p>

                            <div class="text-center mt-4">
                                <a href="klas_login.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i>
                                    Ga naar klaslogin
                                </a>
                            </div>
                        </div>

                        <div class="card-footer text-center text-muted small">
                            <i class="bi bi-shield-lock"></i>
                            Alleen toegankelijk voor geautoriseerde klassen
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
    require 'includes/footer.php';
    exit;
}

// -------------------------------------------------
// KLAS WÉL GESELECTEERD → NORMALE FLOW
// -------------------------------------------------
$klas_id = isset($_SESSION['klas_id']) ? (int)$_SESSION['klas_id'] : (int)$_GET['klas_id'];
$_SESSION['klas_id'] = $klas_id;

// ------------------------------
// Edit-modus
// ------------------------------
$isEdit = (
    isset($_GET['edit']) &&
    $_GET['edit'] === '1' &&
    !isset($_SESSION['admin_id']) &&
    !empty($_SESSION['mag_wijzigen'])
);

// ------------------------------
// Al ingevuld → klaar
// ------------------------------
if (!$isEdit && !isset($_SESSION['admin_id']) && !empty($_SESSION['heeft_ingevuld'])) {
    header("Location: klaar.php");
    exit;
}

// ------------------------------
// Klasinstellingen
// ------------------------------
$stmt = $conn->prepare("SELECT max_keuzes, klasaanduiding FROM klas WHERE klas_id = ?");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$klasRes = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$klasRes) {
    die("Klas niet gevonden.");
}

$klasNaam   = $klasRes['klasaanduiding'] ?? "Onbekende klas";
$max_keuzes = (int)($klasRes['max_keuzes'] ?? 2);
if (!in_array($max_keuzes, [2, 3], true)) {
    $max_keuzes = 2;
}

// ------------------------------
// Actieve voorkeuren
// ------------------------------
$stmt = $conn->prepare("
    SELECT id, naam 
    FROM klas_voorkeur 
    WHERE klas_id = ? AND actief = 1 
    ORDER BY volgorde ASC
");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$voorkeuren = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$aantal_keuzes = min($max_keuzes, count($voorkeuren));

$allowed_ids = array_map(fn($r) => (int)$r['id'], $voorkeuren);
$allowed_set = array_fill_keys($allowed_ids, true);

$melding = "";

// ------------------------------
// PRG succesmelding admin
// ------------------------------
if (isset($_SESSION['admin_id']) && isset($_GET['added']) && $_GET['added'] === '1') {
    $melding = "<div class='alert alert-success text-center mb-3'>
        <i class='bi bi-check-circle'></i> Leerling succesvol toegevoegd!
    </div>";
}

// ------------------------------
// In edit-modus: bestaande leerling ophalen en pre-fill bij GET
// ------------------------------
$leerling_id = null;
if ($isEdit) {
    $leerling_id = (int)($_SESSION['leerling_id'] ?? 0);
    if ($leerling_id <= 0) {
        header("Location: klaar.php");
        exit;
    }

    $stmt = $conn->prepare("
        SELECT voornaam, tussenvoegsel, achternaam,
               voorkeur1, voorkeur2, voorkeur3, voorkeur4, voorkeur5
        FROM leerling
        WHERE leerling_id = ? AND klas_id = ?
    ");
    $stmt->bind_param("ii", $leerling_id, $klas_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        header("Location: klaar.php");
        exit;
    }

    // Alleen bij eerste keer laden (GET) formulier vooraf vullen
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_POST['voornaam']      = $existing['voornaam'];
        $_POST['tussenvoegsel'] = $existing['tussenvoegsel'];
        $_POST['achternaam']    = $existing['achternaam'];

        for ($i = 1; $i <= $aantal_keuzes; $i++) {
            $col = 'voorkeur' . $i;
            $_POST[$col] = $existing[$col] ?? '';
        }
    }
}

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
            $gekozen[$i] = null;
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

    // Dubbele leerling in dezelfde klas voorkomen
    if (empty($errors)) {
        if ($isEdit && !isset($_SESSION['admin_id'])) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS cnt
                FROM leerling
                WHERE klas_id = ?
                  AND voornaam = ?
                  AND tussenvoegsel = ?
                  AND achternaam = ?
                  AND leerling_id <> ?
            ");
            $stmt->bind_param("isssi", $klas_id, $voornaam, $tussenvoegsel, $achternaam, $leerling_id);
        } else {
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS cnt
                FROM leerling
                WHERE klas_id = ?
                  AND voornaam = ?
                  AND tussenvoegsel = ?
                  AND achternaam = ?
            ");
            $stmt->bind_param("isss", $klas_id, $voornaam, $tussenvoegsel, $achternaam);
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($row['cnt']) && (int)$row['cnt'] > 0) {
            $errors[] = "Er is al een leerling met deze naam in deze klas geregistreerd.";
        }
    }

    if (empty($errors)) {
        // Kolommen voor opslag
        $v1 = $gekozen[1] ?? null;
        $v2 = $gekozen[2] ?? null;
        $v3 = $gekozen[3] ?? null;
        $v4 = null;
        $v5 = null;

        if ($isEdit && !isset($_SESSION['admin_id'])) {
            // UPDATE bestaande leerling (alleen leerling zelf)
            $stmt = $conn->prepare("
                UPDATE leerling
                SET voornaam = ?, tussenvoegsel = ?, achternaam = ?,
                    voorkeur1 = ?, voorkeur2 = ?, voorkeur3 = ?, voorkeur4 = ?, voorkeur5 = ?
                WHERE leerling_id = ? AND klas_id = ?
            ");
            $stmt->bind_param(
                "sssiiiiiii",
                $voornaam,
                $tussenvoegsel,
                $achternaam,
                $v1,
                $v2,
                $v3,
                $v4,
                $v5,
                $leerling_id,
                $klas_id
            );
            $stmt->execute();
            $stmt->close();

            $_SESSION['mag_wijzigen'] = false;
            header("Location: klaar.php?updated=1");
            exit;
        } else {
            // INSERT nieuwe leerling
            $stmt = $conn->prepare("
                INSERT INTO leerling (klas_id, voornaam, tussenvoegsel, achternaam, voorkeur1, voorkeur2, voorkeur3, voorkeur4, voorkeur5)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
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
            $newId = $stmt->insert_id;
            $stmt->close();

            if (isset($_SESSION['admin_id'])) {
                // PRG: redirect zodat refresh geen dubbele insert doet + formulier leeg is
                header("Location: index.php?klas_id={$klas_id}&added=1");
                exit;
            } else {
                $_SESSION['heeft_ingevuld'] = true;
                $_SESSION['leerling_id']    = $newId;
                $_SESSION['mag_wijzigen']   = true;
                header("Location: klaar.php");
                exit;
            }
        }
    } else {
        $melding = "<div class='alert alert-danger mb-3'><strong><i class='bi bi-exclamation-circle'></i> Controleer je invoer:</strong><ul class='mb-0'>"
            . implode('', array_map(fn($e) => "<li>" . htmlspecialchars($e) . "</li>", $errors))
            . "</ul></div>";
    }
}
?>

<div class="ttr-app">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white fw-semibold text-center py-4">
                        <i class="bi bi-pencil-square"></i>
                        <?= $isEdit ? 'Wijzig je voorkeuren' : 'Voer je voorkeuren in' ?>
                    </div>

                    <div class="card-body p-4">
                        <h5 class="text-center text-primary fw-bold mb-1">
                            Klas: <?= htmlspecialchars($klasNaam) ?>
                        </h5>

                        <p class="text-center text-muted small mb-4">
                            Je mag <strong><?= htmlspecialchars((string)$aantal_keuzes) ?></strong>
                            <?= $aantal_keuzes === 1 ? 'keuze' : 'keuzes' ?> maken voor deze klas.
                        </p>

                        <?= $melding ?>

                        <form method="post" id="leerlingForm" autocomplete="off">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label for="voornaam" class="form-label fw-semibold">
                                        <i class="bi bi-person"></i> Voornaam *
                                    </label>
                                    <input
                                        type="text"
                                        id="voornaam"
                                        name="voornaam"
                                        class="form-control form-control-lg"
                                        placeholder="bijv: Jan"
                                        required
                                        value="<?= htmlspecialchars($_POST['voornaam'] ?? '') ?>"
                                        style="font-size: 0.95rem;">
                                </div>

                                <div class="col-md-3">
                                    <label for="tussenvoegsel" class="form-label fw-semibold">
                                        <i class="bi bi-person-vcard"></i> Tussenvoegsel
                                    </label>
                                    <input
                                        type="text"
                                        id="tussenvoegsel"
                                        name="tussenvoegsel"
                                        class="form-control form-control-lg"
                                        placeholder="bijv: van"
                                        value="<?= htmlspecialchars($_POST['tussenvoegsel'] ?? '') ?>"
                                        style="font-size: 0.95rem;">
                                </div>

                                <div class="col-md-5">
                                    <label for="achternaam" class="form-label fw-semibold">
                                        <i class="bi bi-person-badge"></i> Achternaam *
                                    </label>
                                    <input
                                        type="text"
                                        id="achternaam"
                                        name="achternaam"
                                        class="form-control form-control-lg"
                                        placeholder="bijv: Rijsbergen"
                                        required
                                        value="<?= htmlspecialchars($_POST['achternaam'] ?? '') ?>"
                                        style="font-size: 0.95rem;">
                                </div>
                            </div>

                            <hr class="my-4">

                            <?php if ($aantal_keuzes < 1): ?>
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Geen keuzes beschikbaar</strong><br>
                                    Er zijn nog geen sectoren/werelden actief voor deze klas. Neem contact op met de docent.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php else: ?>
                                <div class="preferences-section">
                                    <?php for ($i = 1; $i <= $aantal_keuzes; $i++): ?>
                                        <div class="mb-3">
                                            <label for="voorkeur<?= $i ?>" class="form-label fw-semibold">
                                                <i class="bi bi-<?= $i === 1 ? 'star-fill' : ($i === 2 ? 'star-half' : 'star') ?>"></i>
                                                <?= $i ?>e keuze *
                                            </label>
                                            <select
                                                id="voorkeur<?= $i ?>"
                                                name="voorkeur<?= $i ?>"
                                                class="form-select form-select-lg voorkeur-select"
                                                required>
                                                <option value="" <?= empty($_POST['voorkeur' . $i]) ? 'selected' : '' ?>>
                                                    Kies een sector...
                                                </option>
                                                <?php foreach ($voorkeuren as $opt): ?>
                                                    <?php
                                                    $sel = (isset($_POST['voorkeur' . $i]) &&
                                                        $_POST['voorkeur' . $i] !== '' &&
                                                        (int)$_POST['voorkeur' . $i] === (int)$opt['id'])
                                                        ? 'selected'
                                                        : '';
                                                    ?>
                                                    <option value="<?= (int)$opt['id'] ?>" <?= $sel ?>>
                                                        <?= htmlspecialchars($opt['naam']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endfor; ?>
                                </div>

                                <div class="button-group-index mt-4">
                                    <button type="submit" class="btn btn-primary btn-index">
                                        <i class="bi bi-check-circle"></i>
                                        <?= $isEdit ? 'Wijzigingen opslaan' : 'Opslaan' ?>
                                    </button>
                                    <a href="klas_login.php?reset=1" class="btn btn-secondary btn-index">
                                        <i class="bi bi-arrow-left"></i> Terug
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>

                        <div class="index-footer mt-4">
                            <p class="text-center text-muted small">
                                <i class="bi bi-info-circle"></i> Alle velden met * zijn verplicht in te vullen.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Disable dubbele keuzes in de UI (client-side hulp; server-side blijft leidend)
    document.addEventListener("DOMContentLoaded", function() {
        const selects = document.querySelectorAll(".voorkeur-select");

        const updateOptions = () => {
            const selectedValues = Array.from(selects).map(s => s.value).filter(v => v !== "");
            selects.forEach(select => {
                Array.from(select.options).forEach(option => {
                    if (option.value === "") return;
                    option.disabled = selectedValues.includes(option.value) && select.value !== option.value;
                });
            });
        };

        selects.forEach(select => select.addEventListener("change", updateOptions));
        updateOptions();

        // bij admin na redirect focus op voornaam
        <?php if (isset($_SESSION['admin_id']) && isset($_GET['added']) && $_GET['added'] === '1'): ?>
            setTimeout(() => {
                document.getElementById('voornaam')?.focus();
            }, 50);
        <?php endif; ?>
    });
</script>

<?php require 'includes/footer.php'; ?>