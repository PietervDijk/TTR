<?php
require 'includes/header.php';

// Zorg dat er een session actief is (includes/header.php start meestal al session_start())
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Alleen toegang via klas-login
if (!isset($_SESSION['klas_id'])) {
    header("Location: klas_login.php");
    exit;
}

$klas_id = $_SESSION['klas_id'];

// Bepaal of de huidige bezoeker een ingelogde admin is
// (gebruik admin_id zoals ingesteld bij admin-login)
$is_admin = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);

// Wereld/sector ophalen
$werelden = $conn->query("SELECT wereld_sector_id, naam FROM wereld_sector WHERE actief = 1 ORDER BY naam ASC");

// Leerlingen ophalen
$leerlingen = $conn->query("
    SELECT l.voornaam, l.tussenvoegsel, l.achternaam,
           w1.naam AS v1, w2.naam AS v2, w3.naam AS v3,
           k.klasaanduiding
    FROM leerling l
    LEFT JOIN wereld_sector w1 ON l.voorkeur1_wereld_sector_id = w1.wereld_sector_id
    LEFT JOIN wereld_sector w2 ON l.voorkeur2_wereld_sector_id = w2.wereld_sector_id
    LEFT JOIN wereld_sector w3 ON l.voorkeur3_wereld_sector_id = w3.wereld_sector_id
    LEFT JOIN klas k ON l.klas_id = k.klas_id
    WHERE l.klas_id = $klas_id
    ORDER BY l.achternaam ASC
");

// Leerling opslaan
if (isset($_POST['add_leerling'])) {
    $voornaam = $_POST['voornaam'];
    $tussenvoegsel = $_POST['tussenvoegsel'] ?? null;
    $achternaam = $_POST['achternaam'];
    $v1 = (int)$_POST['voorkeur1'];
    $v2 = (int)$_POST['voorkeur2'];
    $v3 = (int)$_POST['voorkeur3'];

    $stmt = $conn->prepare("
        INSERT INTO leerling (
            klas_id, voornaam, tussenvoegsel, achternaam,
            voorkeur1_wereld_sector_id, voorkeur2_wereld_sector_id, voorkeur3_wereld_sector_id,
            toegewezen_wereld_sector_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->bind_param("isssiii", $klas_id, $voornaam, $tussenvoegsel, $achternaam, $v1, $v2, $v3);
    $stmt->execute();
    $stmt->close();

    // Als het géén admin is, redirect naar klaar.php en beëindig script
    if (!$is_admin) {
        header("Location: klaar.php");
        exit;
    } else {
        // Als admin: blijf op dezelfde pagina en toon een succesmelding
        $success = true;
    }
}
?>
<div class="container py-5">
    <?php if (!empty($success)): ?>
        <div class="alert alert-success text-center mb-3">✅ Leerling succesvol toegevoegd (admin view).</div>
    <?php endif; ?>

    <h2 class="fw-bold text-center mb-4 text-primary">
        Welkom in klas <?php
        // Haal klasaanduiding veilig op (als er minstens 1 leerling is)
        $knaam = '';
        if ($leerlingen && $leerlingen->num_rows > 0) {
            $row = $leerlingen->fetch_assoc();
            $knaam = $row['klasaanduiding'] ?? '';
            $leerlingen->data_seek(0);
        } else {
            // fallback: haal klasaanduiding rechtstreeks uit klas tabel
            $kq = $conn->prepare("SELECT klasaanduiding FROM klas WHERE klas_id = ?");
            $kq->bind_param("i", $klas_id);
            $kq->execute();
            $kr = $kq->get_result()->fetch_assoc();
            $knaam = $kr['klasaanduiding'] ?? '';
            $kq->close();
        }
        echo ($knaam);
        ?>
    </h2>
    <div class="container py-5">

        <?php $leerlingen->data_seek(0); ?>

        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success text-center mb-3">
                ✅ Je bent succesvol toegevoegd aan de klas!
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-sm mb-5">
                    <div class="card-body">
                        <form method="post" class="row g-3">

                            <div class="col-12">
                                <label class="form-label">Voornaam</label>
                                <input type="text" name="voornaam" class="form-control" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Tussenvoegsel (optioneel)</label>
                                <input type="text" name="tussenvoegsel" class="form-control">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Achternaam</label>
                                <input type="text" name="achternaam" class="form-control" required>
                            </div>

                            <hr>

                            <div class="col-12">
                                <label class="form-label">Kies 3 voorkeuren</label>
                            </div>

                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <div class="col-12">
                                    <select name="voorkeur<?= $i ?>" id="voorkeur<?= $i ?>" class="form-control voorkeur" required>
                                        <option disabled selected>Voorkeur <?= $i ?></option>
                                        <?php
                                        $werelden->data_seek(0);
                                        while ($w = $werelden->fetch_assoc()): ?>
                                            <option value="<?= $w['wereld_sector_id'] ?>">
                                                <?= htmlspecialchars($w['naam']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            <?php endfor; ?>

                            <div class="col-12 d-grid mt-3">
                                <button type="submit" name="add_leerling" class="btn btn-success">
                                    Opslaan ✅
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ✅ JavaScript die dubbele keuzes voorkomt -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selects = document.querySelectorAll('.voorkeur');

            function updateOptions() {
                // verzamel alle gekozen waarden
                const gekozen = Array.from(selects).map(s => s.value).filter(v => v && v !== "Voorkeur");

                // reset alle opties
                selects.forEach(select => {
                    Array.from(select.options).forEach(opt => {
                        if (opt.value && opt.value !== "Voorkeur") {
                            opt.disabled = gekozen.includes(opt.value) && select.value !== opt.value;
                        }
                    });
                });
            }

            selects.forEach(select => {
                select.addEventListener('change', updateOptions);
            });
        });
    </script>

    <?php require 'includes/footer.php'; ?>
