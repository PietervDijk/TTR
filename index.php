<?php
require 'includes/header.php';

// Zet header, infokaart of formulier neer afhankelijk van klasseselectie
// Valideert en slaat gegevens op als formulier wordt ingediend

// Toon infokaart als geen klas is geselecteerd
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
                            <!-- <p>
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
                            </p> -->

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

// Laad klasgegevens en formulier als klas geselecteerd is
$klas_id = isset($_SESSION['klas_id']) ? (int)$_SESSION['klas_id'] : (int)$_GET['klas_id'];
$_SESSION['klas_id'] = $klas_id;

// Controleer of dit een bewerk-aanvraag is
$is_bewerken = (
    isset($_GET['edit']) &&
    $_GET['edit'] === '1' &&
    !isset($_SESSION['admin_id']) &&
    !empty($_SESSION['mag_wijzigen'])
);

// Redirect naar afgeronde pagina als leerling al heeft ingevuld
if (!$is_bewerken && !isset($_SESSION['admin_id']) && !empty($_SESSION['heeft_ingevuld'])) {
    header("Location: klaar.php");
    exit;
}

// Haal klasnaam en bijbehorende bezoek op
$stmt = $conn->prepare("SELECT klasaanduiding FROM klas WHERE klas_id = ?");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$klas_gegevens = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$klas_gegevens) {
    die("Klas niet gevonden.");
}

$klas_naam = $klas_gegevens['klasaanduiding'] ?? "Onbekende klas";

// Zoek bezoek_id en bepaal hoeveel keuzes verplicht zijn
$stmt = $conn->prepare("SELECT bezoek_id FROM bezoek_klas WHERE klas_id = ? LIMIT 1");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$bezoek_resultaat = $stmt->get_result()->fetch_assoc();
$stmt->close();

$bezoek_id = null;
$max_keuzes = 2;

if ($bezoek_resultaat) {
    $bezoek_id = (int)$bezoek_resultaat['bezoek_id'];
    // Lees max_keuzes uit bezoektabel
    $stmt = $conn->prepare("SELECT max_keuzes FROM bezoek WHERE bezoek_id = ?");
    $stmt->bind_param("i", $bezoek_id);
    $stmt->execute();
    $bezoekData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($bezoekData) {
        $max_keuzes = (int)($bezoekData['max_keuzes'] ?? 2);
        if (!in_array($max_keuzes, [2, 3], true)) {
            $max_keuzes = 2;
        }
    }
}

// Haal alle actieve sectoren/opties voor dit bezoek op
if ($bezoek_id) {
    $stmt = $conn->prepare("
        SELECT optie_id AS id, naam 
        FROM bezoek_optie 
        WHERE bezoek_id = ? AND actief = 1 
        ORDER BY volgorde ASC
    ");
    $stmt->bind_param("i", $bezoek_id);
    $stmt->execute();
    $beschikbare_voorkeuren = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $beschikbare_voorkeuren = [];
}

$aantal_keuzes = min($max_keuzes, count($beschikbare_voorkeuren));

$toegestane_ids = array_map(fn($r) => (int)$r['id'], $beschikbare_voorkeuren);
$toegestane_set = array_fill_keys($toegestane_ids, true);

// Houdt foutmeldingen vast voor weergave
$melding_html = "";

// Toon succesmeldingvoor admin na toevoegen leerling
if (isset($_SESSION['admin_id']) && isset($_GET['added']) && $_GET['added'] === '1') {
    $melding_html = "<div class='alert alert-success text-center mb-3'>
        <i class='bi bi-check-circle'></i> Leerling succesvol toegevoegd!
    </div>";
}

// Haal bestaande leerlinggegevens op voor bewerking
$leerling_id = null;
if ($is_bewerken) {
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

    // Vul formulier in op eerste keer laden (niet na POST)
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

// Verwerk inzending: valideer en sla leerling op
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voornaam      = trim($_POST['voornaam'] ?? '');
    $tussenvoegsel = trim($_POST['tussenvoegsel'] ?? '');
    $achternaam    = trim($_POST['achternaam'] ?? '');

    // Parse keuzes als getallen
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

    // Valideer alle velden server-side
    $errors = [];
    if ($voornaam === '')       $errors[] = "Vul je voornaam in.";
    if ($achternaam === '')     $errors[] = "Vul je achternaam in.";
    if ($aantal_keuzes < 1)     $errors[] = "Er zijn (nog) geen keuzes beschikbaar voor deze klas.";

    // Check of alle keuzes ingevuld zijn
    for ($i = 1; $i <= $aantal_keuzes; $i++) {
        if ($gekozen[$i] === null) {
            $errors[] = "Vul je {$i}e keuze in.";
        }
    }

    // Zorg voor unieke keuzes en controleer of ze geldig zijn
    $vals = array_values(array_filter($gekozen, fn($v) => $v !== null));
    if (count($vals) !== count(array_unique($vals))) {
        $errors[] = "Kies per voorkeur een andere sector (geen dubbele keuzes).";
    }
    foreach ($vals as $id) {
        if (!isset($toegestane_set[$id])) {
            $errors[] = "Onjuiste keuze gedetecteerd. Vernieuw de pagina en probeer opnieuw.";
            break;
        }
    }

    // Controleer of leerling al bestaat in klas
    if (empty($errors)) {
        if ($is_bewerken && !isset($_SESSION['admin_id'])) {
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
        // Zet keuzes voor database-opslag
        $v1 = $gekozen[1] ?? null;
        $v2 = $gekozen[2] ?? null;
        $v3 = $gekozen[3] ?? null;
        $v4 = null;
        $v5 = null;

        if ($is_bewerken && !isset($_SESSION['admin_id'])) {
            // Update bestaande leerling
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
            // Voeg nieuwe leerling toe
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
                // Redirect na insert om dubbele POST-inzending te voorkomen
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
        $melding_html = "<div class='alert alert-danger mb-3'><strong><i class='bi bi-exclamation-circle'></i> Controleer je invoer:</strong><ul class='mb-0'>"
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
                        <?= $is_bewerken ? 'Wijzig je voorkeuren' : 'Voer je voorkeuren in' ?>
                    </div>

                    <div class="card-body p-4">
                        <h5 class="text-center text-primary fw-bold mb-1">
                            Klas: <?= htmlspecialchars($klas_naam) ?>
                        </h5>

                        <p class="text-center text-muted small mb-4">
                            Je mag <strong><?= htmlspecialchars((string)$aantal_keuzes) ?></strong>
                            <?= $aantal_keuzes === 1 ? 'keuze' : 'keuzes' ?> maken voor deze klas.
                        </p>

                        <?= $melding_html ?>

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
                                                <?php foreach ($beschikbare_voorkeuren as $opt): ?>
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
                                        <?= $is_bewerken ? 'Wijzigingen opslaan' : 'Opslaan' ?>
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
    // Zet dubbele keuzes grijs voor betere UX
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

        // Focus op voornaamveld na succesvolle toevoeging door admin
        <?php if (isset($_SESSION['admin_id']) && isset($_GET['added']) && $_GET['added'] === '1'): ?>
            setTimeout(() => {
                document.getElementById('voornaam')?.focus();
            }, 50);
        <?php endif; ?>
    });
</script>

<?php require 'includes/footer.php'; ?>