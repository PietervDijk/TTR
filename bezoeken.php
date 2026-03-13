<!-- Pagina voor bezoeken, waarin je bezoekcombinaties kan invoeren met datum/tijd en ook type onderwijs.
En vervolgens in dat scherm scholen gefilterd op het aangewezen onderwijstype kunt toevoegen en klassen van die school 
kan toevoegen via een selectie van bestaande scholen en klassen. -->
<?php 
include 'includes/header.php';
include 'includes/config.php';
// Check of de gebruiker is ingelogd als admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Hier zou je de logica kunnen toevoegen om bezoeken toe te voegen, bewerken, verwijderen, etc.
?>
<div class="container py-5">
    <h1 class="mb-4">Bezoeken beheren</h1>
    <p>Hier kun je bezoeken toevoegen, bewerken of verwijderen. Je kunt ook scholen en klassen koppelen aan specifieke bezoeken.</p>

    <!-- Voorbeeld van een bezoek toevoegen -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-plus-circle"></i> Nieuw bezoek toevoegen
        </div>
        <div class="card-body">
            <form method="post" action="bezoeken_process.php">
                <!-- bezoek naam -->
                <div class="mb-3">
                    <label for="bezoek_naam" class="form-label">Naam bezoek</label>
                    <input type="text" class="form-control" id="bezoek_naam" name="bezoek_naam" required>
                </div>
                <div class="mb-3">
                    <label for="bezoek_datum" class="form-label">Datum en tijd</label>
                    <input type="datetime-local" class="form-control" id="bezoek_datum" name="bezoek_datum" required>
                </div>
                <div class="mb-3">
                    <label for="onderwijs_type" class="form-label">Type onderwijs</label>
                    <select class="form-select" id="onderwijs_type" name="onderwijs_type" required>
                        <option value="">Selecteer type onderwijs</option>
                        <option value="basisonderwijs">Primair onderwijs (PO)</option>
                        <option value="voortgezet_onderwijs">Voortgezet onderwijs (VO)</option>
                        <option value="middelbaar_beroepsonderwijs">Middelbaar beroepsonderwijs (MBO)</option>
                    </select>
                </div>
                <!-- Logica voor scholen en klassen koppelen -->
                <div class="mb-3">
                    <label for="school" class="form-label">School</label>
                    <select class="form-select" id="school" name="school" required>
                        <option value="">Selecteer school</option>
                        <!-- Opties voor scholen zouden hier kunnen worden gegenereerd -->
                    </select>
                </div>
                <div class="mb-3">
                    <label for="klas" class="form-label">Klas</label>
                    <select class="form-select" id="klas" name="klas" required>
                        <option value="">Selecteer klas</option>
                        <!-- Opties voor klassen zouden hier kunnen worden gegenereerd -->
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Bezoek toevoegen
                </button>
            </form>
        </div>
    </div>
    <!-- Hier zou je een lijst kunnen tonen van bestaande bezoeken met opties om te bewerken of verwijderen -->
</div>
<?php include 'includes/footer.php'; ?>