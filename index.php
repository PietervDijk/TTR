
<?php
require 'includes/home.php';

if (isset($_POST['add_leerling'])) {
    $voornaam = $_POST['voornaam'];
    $tussenvoegsel = $_POST['tussenvoegsel'] ?? null;
    $achternaam = $_POST['achternaam'];
    $pincode = (int)$_POST['pincode'];
    $voorkeur1 = (int)$_POST['voorkeur1'];
    $voorkeur2 = (int)$_POST['voorkeur2'];
    $voorkeur3 = (int)$_POST['voorkeur3'];

    // Zoek klas op basis van pincode
    $stmt = $conn->prepare("SELECT klas_id FROM klas WHERE pincode = ?");
    $stmt->bind_param("i", $pincode);
    $stmt->execute();
    $result = $stmt->get_result();
    $klas = $result->fetch_assoc();
    $stmt->close();

    if (!$klas) {
        echo '<div class="alert alert-danger text-center">Pincode klopt niet! Vraag de docent om hulp.</div>';
    } else {
        $klas_id = $klas['klas_id'];

        $stmt = $conn->prepare("
            INSERT INTO leerling (
                klas_id, voornaam, tussenvoegsel, achternaam, 
                voorkeur1_wereld_sector_id, voorkeur2_wereld_sector_id, voorkeur3_wereld_sector_id,
                toegewezen_wereld_sector_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->bind_param("isssiii", $klas_id, $voornaam, $tussenvoegsel, $achternaam, $voorkeur1, $voorkeur2, $voorkeur3);
        $stmt->execute();
        $stmt->close();

        echo '<div class="alert alert-success text-center">Top! Je bent toegevoegd aan de klas.</div>';
    }
}

// Data ophalen voor dropdowns
$klassen = $conn->query("SELECT klas_id, klasaanduiding FROM klas ORDER BY klasaanduiding ASC");
$werelden = $conn->query("SELECT wereld_sector_id, naam FROM wereld_sector WHERE actief = 1 ORDER BY naam ASC");
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col text-center">
            <h2 class="fw-bold text-primary">Leerling toevoegen</h2>
            <p class="text-muted">Vul hieronder de gegevens van de leerling in.</p>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <div class="col-12">
                            <label for="voornaam" class="form-label">Voornaam</label>
                            <input type="text" name="voornaam" id="voornaam" class="form-control" required>
                        </div>

                        <div class="col-12">
                            <label for="tussenvoegsel" class="form-label">Tussenvoegsel (optioneel)</label>
                            <input type="text" name="tussenvoegsel" id="tussenvoegsel" class="form-control">
                        </div>

                        <div class="col-12">
                            <label for="achternaam" class="form-label">Achternaam</label>
                            <input type="text" name="achternaam" id="achternaam" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label for="pincode" class="form-label">Wachtwoord (van de klas)</label>
                            <input type="number" name="pincode" id="pincode" class="form-control" required placeholder="Bijv. 1234">
                        </div>


                        <hr>

                        <div class="col-12">
                            <label class="form-label">Voorkeuren (wereld/sector)</label>
                        </div>

                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="col-12">
                                <select name="voorkeur<?= $i ?>" class="form-control" required>
                                    <option value="" disabled selected>Voorkeur <?= $i ?></option>
                                    <?php
                                    // Reset pointer van $werelden als nodig
                                    $werelden->data_seek(0);
                                    while ($w = $werelden->fetch_assoc()): ?>
                                        <option value="<?= $w['wereld_sector_id'] ?>"><?= ($w['naam']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        <?php endfor; ?>

                        <div class="col-12 d-grid mt-3">
                            <button type="submit" name="add_leerling" class="btn btn-success">
                                Leerling toevoegen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
