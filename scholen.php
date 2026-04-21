<?php
// Admin-pagina: CRUD voor scholen (Create, Read, Update, Delete)
require 'includes/header.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$foutmeldingen = [];

// Voeg school toe
if (isset($_POST['add'])) {
    $nieuwe_schoolnaam = trim($_POST['schoolnaam'] ?? '');
    $nieuwe_plaats = trim($_POST['plaats'] ?? '');
    $nieuw_type_onderwijs = trim($_POST['type_onderwijs'] ?? '');

    $stmt = $conn->prepare("INSERT INTO school (schoolnaam, plaats, type_onderwijs) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nieuwe_schoolnaam, $nieuwe_plaats, $nieuw_type_onderwijs);
    $stmt->execute();
    $nieuwe_school_id = $conn->insert_id;
    $stmt->close();

    header("Location: scholen.php?highlight=$nieuwe_school_id");
    exit;
}

// Update bestaande school
if (isset($_POST['update'])) {
    $te_bewerken_school_id = (int)$_POST['school_id'];
    $gewijzigde_schoolnaam = trim($_POST['schoolnaam'] ?? '');
    $gewijzigde_plaats = trim($_POST['plaats'] ?? '');
    $gewijzigd_type_onderwijs = trim($_POST['type_onderwijs'] ?? '');

    if ($te_bewerken_school_id <= 0) {
        $foutmeldingen[] = 'Ongeldige school om te bewerken.';
    } else {
        $stmt = $conn->prepare("SELECT school_id FROM school WHERE school_id = ? LIMIT 1");
        $stmt->bind_param("i", $te_bewerken_school_id);
        $stmt->execute();
        $bestaat_school = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bestaat_school) {
            $foutmeldingen[] = 'De geselecteerde school bestaat niet (meer).';
        } else {
            $stmt = $conn->prepare("UPDATE school SET schoolnaam=?, plaats=?, type_onderwijs=? WHERE school_id=?");
            $stmt->bind_param("sssi", $gewijzigde_schoolnaam, $gewijzigde_plaats, $gewijzigd_type_onderwijs, $te_bewerken_school_id);
            $stmt->execute();
            $stmt->close();

            header("Location: scholen.php?highlight=$te_bewerken_school_id");
            exit;
        }
    }
}

// Verwijder school (alleen als geen klassen gekoppeld zijn)
if (isset($_GET['delete'])) {
    $te_verwijderen_school_id = (int)$_GET['delete'];

    try {
        $stmt = $conn->prepare("SELECT COUNT(*) AS aantal FROM klas WHERE school_id = ?");
        $stmt->bind_param("i", $te_verwijderen_school_id);
        $stmt->execute();
        $aantal_klassen = (int)($stmt->get_result()->fetch_assoc()['aantal'] ?? 0);
        $stmt->close();

        if ($aantal_klassen > 0) {
            $foutmeldingen[] = 'Deze school kan niet verwijderd worden zolang er nog klassen aan gekoppeld zijn.';
        } else {
            $stmt = $conn->prepare("DELETE FROM school WHERE school_id=?");
            $stmt->bind_param("i", $te_verwijderen_school_id);
            $stmt->execute();
            $stmt->close();

            header("Location: scholen.php");
            exit;
        }
    } catch (Exception $e) {
        error_log('Fout bij verwijderen school: ' . $e->getMessage());
        $foutmeldingen[] = 'Er is iets misgegaan bij het verwijderen van de school.';
    }
}

// Laad alle scholen voor overzichtstabel
$school_resultaat = $conn->query("SELECT * FROM school");

// Bepaal te markeren school (visuele feedback na bewerking)
$gemarkeerde_school_id = null;
if (isset($_GET['highlight'])) {
    $gemarkeerde_school_id = (int)$_GET['highlight'];
}
?>

<div class="ttr-app">
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col d-flex justify-content-between align-items-center">
                <h2 class="fw-bold text-primary mb-0">Scholen</h2>
            </div>
        </div>

        <?php if (!empty($foutmeldingen)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($foutmeldingen as $foutmelding): ?>
                        <li><?= e($foutmelding) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Overzicht scholen (LINKS) -->
            <div class="col-12 col-lg-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white fw-semibold">
                        Overzicht scholen
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Schoolnaam</th>
                                        <th>Plaats</th>
                                        <th>Type onderwijs</th>
                                        <th class="text-end">Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($school = $school_resultaat->fetch_assoc()): ?>
                                        <tr<?= ($gemarkeerde_school_id === (int)$school['school_id']) ? ' class="table-warning"' : '' ?>>
                                            <td><?= e($school['schoolnaam']) ?></td>
                                            <td><?= e($school['plaats']) ?></td>
                                            <td>
                                                <span><?= e($school['type_onderwijs']) ?></span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group" role="group" aria-label="Acties">
                                                    <a href="klassen.php?school_id=<?= (int)$school['school_id'] ?>"
                                                        class="btn btn-dark btn-sm">
                                                        <i class="bi bi-houses"></i> Klassen
                                                    </a>
                                                    <a href="scholen.php?edit=<?= (int)$school['school_id'] ?>"
                                                        class="btn btn-primary btn-sm">
                                                        <i class="bi bi-pencil-square"></i> Bewerken
                                                    </a>
                                                    <a href="scholen.php?delete=<?= (int)$school['school_id'] ?>"
                                                        class="btn btn-danger btn-sm js-confirm"
                                                        data-confirm="Weet je het zeker?">
                                                        <i class="bi bi-trash"></i> Verwijderen
                                                    </a>
                                                </div>
                                            </td>
                                            </tr>
                                        <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div> <!-- /.table-responsive -->
                    </div>
                </div>
            </div>

            <!-- Toevoegen / Bewerken (RECHTS) -->
            <div class="col-12 col-lg-4">
                <?php if (!isset($_GET['edit'])): ?>
                    <!-- School toevoegen -->
                    <div class="card shadow-sm mb-4 mb-lg-0">
                        <div class="card-header bg-success text-white fw-semibold">
                            School toevoegen
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <div class="col-12 mb-2">
                                    <label for="schoolnaam" class="form-label">Schoolnaam</label>
                                    <input
                                        type="text"
                                        name="schoolnaam"
                                        id="schoolnaam"
                                        class="form-control form-input"
                                        placeholder="Schoolnaam"
                                        required>
                                </div>
                                <div class="col-12 mb-2">
                                    <label for="plaats" class="form-label">Plaats</label>
                                    <input
                                        type="text"
                                        name="plaats"
                                        id="plaats"
                                        class="form-control form-input"
                                        placeholder="Plaats"
                                        required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="type_onderwijs" class="form-label">Type onderwijs</label>
                                    <select
                                        name="type_onderwijs"
                                        id="type_onderwijs"
                                        class="form-control form-select"
                                        required>
                                        <option value="" disabled selected>Kies type onderwijs</option>
                                        <option value="Primair Onderwijs">Primair Onderwijs (PO)</option>
                                        <option value="Voortgezet Onderwijs">Voortgezet Onderwijs (VO)</option>
                                        <option value="MBO">Middelbaar Beroepsonderwijs (MBO)</option>
                                    </select>
                                </div>
                                <div class="col-12 d-grid mt-2">
                                    <button type="submit" name="add" class="btn btn-success w-100">
                                        Toevoegen
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- School bewerken -->
                    <?php
                    $te_bewerken_school_id = (int)$_GET['edit'];
                    $stmt = $conn->prepare("SELECT * FROM school WHERE school_id=?");
                    $stmt->bind_param("i", $te_bewerken_school_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $school = $result->fetch_assoc();
                    $stmt->close();
                    ?>
                    <div class="card shadow-sm mb-4 mb-lg-0">
                        <div class="card-header bg-warning text-dark fw-semibold">
                            School bewerken
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <input type="hidden" name="school_id" value="<?= (int)$school['school_id'] ?>">
                                <div class="col-12 mb-2">
                                    <label for="edit_schoolnaam" class="form-label">Schoolnaam</label>
                                    <input
                                        type="text"
                                        name="schoolnaam"
                                        id="edit_schoolnaam"
                                        class="form-control form-input"
                                        value="<?= e($school['schoolnaam']) ?>"
                                        required>
                                </div>
                                <div class="col-12 mb-2">
                                    <label for="edit_plaats" class="form-label">Plaats</label>
                                    <input
                                        type="text"
                                        name="plaats"
                                        id="edit_plaats"
                                        class="form-control form-input"
                                        value="<?= e($school['plaats']) ?>"
                                        required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="edit_type_onderwijs" class="form-label">Type onderwijs</label>
                                    <select
                                        name="type_onderwijs"
                                        id="edit_type_onderwijs"
                                        class="form-control form-select"
                                        required>
                                        <option value="" disabled <?= empty($school['type_onderwijs']) ? 'selected' : '' ?>>
                                            Kies type onderwijs
                                        </option>
                                        <option value="Primair Onderwijs" <?= $school['type_onderwijs'] == 'Primair Onderwijs' ? 'selected' : '' ?>>PO</option>
                                        <option value="Voortgezet Onderwijs" <?= $school['type_onderwijs'] == 'Voortgezet Onderwijs' ? 'selected' : '' ?>>VO</option>
                                        <option value="MBO" <?= $school['type_onderwijs'] == 'MBO' ? 'selected' : '' ?>>MBO</option>
                                    </select>
                                </div>
                                <div class="col-12 d-flex gap-2 mt-2">
                                    <button type="submit" name="update" class="btn btn-warning text-dark w-50">
                                        Opslaan
                                    </button>
                                    <a href="scholen.php" class="btn btn-secondary w-50 d-flex align-items-center justify-content-center">
                                        Annuleren
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>