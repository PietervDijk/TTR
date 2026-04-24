<?php
require 'includes/header.php';

// Admin-pagina: beheer klassen binnen een school (CRUD)

// Alleen toegankelijk voor admins
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

csrf_validate();

// Controleer school_id
if (!isset($_GET['school_id'])) {
    header("Location: scholen.php");
    exit;
}

$school_id = (int)$_GET['school_id'];

// Haal de schoolinformatie op die bij deze pagina hoort
$stmt = $conn->prepare("SELECT schoolnaam FROM school WHERE school_id=?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$school_info) {
    echo "<div class='alert alert-danger m-5'>School niet gevonden</div>";
    require 'includes/footer.php';
    exit;
}

$errors = [];
$success = null;

// Voeg een nieuwe klas toe
if (isset($_POST['add'])) {
    $klasaanduiding = substr(trim($_POST['klasaanduiding'] ?? ''), 0, 255);
    $leerjaar       = substr(trim($_POST['leerjaar'] ?? ''), 0, 100);
    $schooljaar     = substr(trim($_POST['schooljaar'] ?? ''), 0, 50);

    if (!$klasaanduiding)            $errors[] = "Vul de klasaanduiding in.";
    if (!$leerjaar)                  $errors[] = "Vul het leerjaar in.";
    if (!$schooljaar)                $errors[] = "Vul het schooljaar in.";

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO klas (school_id, klasaanduiding, leerjaar, schooljaar)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $school_id, $klasaanduiding, $leerjaar, $schooljaar);
            $stmt->execute();
            $klas_id = $conn->insert_id;
            $stmt->close();

            csrf_regenerate();
            header("Location: klassen.php?school_id=$school_id&highlight=$klas_id");
            exit;
        } catch (Exception $e) {
            error_log("Fout bij toevoegen klas: " . $e->getMessage());
            $errors[] = "Er is iets misgegaan bij het toevoegen van de klas.";
        }
    }
}

// Update bestaande klas
if (isset($_POST['update'])) {
    $klas_id        = (int)($_POST['klas_id'] ?? 0);
    $klasaanduiding = substr(trim($_POST['klasaanduiding'] ?? ''), 0, 255);
    $leerjaar       = substr(trim($_POST['leerjaar'] ?? ''), 0, 100);
    $schooljaar     = substr(trim($_POST['schooljaar'] ?? ''), 0, 50);

    if (!$klasaanduiding) $errors[] = "Vul de klasaanduiding in.";
    if (!$leerjaar)       $errors[] = "Vul het leerjaar in.";
    if (!$schooljaar)     $errors[] = "Vul het schooljaar in.";

    if ($klas_id <= 0) {
        $errors[] = "Ongeldige klas om te bewerken.";
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT klas_id FROM klas WHERE klas_id = ? AND school_id = ? LIMIT 1");
            $stmt->bind_param("ii", $klas_id, $school_id);
            $stmt->execute();
            $bestaat_klas = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$bestaat_klas) {
                $errors[] = "De geselecteerde klas hoort niet bij deze school of bestaat niet meer.";
            } else {
            $stmt = $conn->prepare("
                UPDATE klas
                SET klasaanduiding=?, leerjaar=?, schooljaar=?
                WHERE klas_id=? AND school_id=?
            ");
            $stmt->bind_param("sssii", $klasaanduiding, $leerjaar, $schooljaar, $klas_id, $school_id);
            $stmt->execute();
            $stmt->close();

            csrf_regenerate();
            header("Location: klassen.php?school_id=$school_id&highlight=$klas_id");
            exit;
            }
        } catch (Exception $e) {
            error_log("Fout bij updaten klas: " . $e->getMessage());
            $errors[] = "Er is iets misgegaan bij het bijwerken van de klas.";
        }
    }
}

// Verwijder een klas met cascading deletes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $klas_id = (int)($_POST['klas_id'] ?? 0);
    $transactie_gestart = false;
    try {
        $stmt = $conn->prepare("SELECT klas_id FROM klas WHERE klas_id = ? AND school_id = ? LIMIT 1");
        $stmt->bind_param("ii", $klas_id, $school_id);
        $stmt->execute();
        $gevonden_klas = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$gevonden_klas) {
            $errors[] = "De geselecteerde klas hoort niet bij deze school of bestaat niet meer.";
        } else {
            $conn->begin_transaction();
            $transactie_gestart = true;

        $stmt = $conn->prepare("DELETE FROM leerling WHERE klas_id = ?");
        $stmt->bind_param("i", $klas_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM bezoek_klas WHERE klas_id = ?");
        $stmt->bind_param("i", $klas_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM klas WHERE klas_id = ?");
        $stmt->bind_param("i", $klas_id);
        $stmt->execute();
        $stmt->close();

            $conn->commit();

        csrf_regenerate();
        header("Location: klassen.php?school_id=$school_id");
        exit;
        }
    } catch (Exception $e) {
        if ($transactie_gestart) {
            $conn->rollback();
        }
        error_log("Fout bij verwijderen klas: " . $e->getMessage());
        $errors[] = "Er is iets misgegaan bij het verwijderen van de klas.";
    }
}

// Laad alle klassen voor dit school-overzicht
$stmt = $conn->prepare("SELECT * FROM klas WHERE school_id=? ORDER BY leerjaar, klasaanduiding");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$klas_resultaat = $stmt->get_result();
$stmt->close();

$gemarkeerde_klas_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : null;
?>

<div class="ttr-app">
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col d-flex justify-content-between align-items-center">
                <h2 class="fw-bold text-primary mb-0">Klassen – <?= e($school_info['schoolnaam']) ?></h2>
                <a href="scholen.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Terug naar scholen</a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Controleer je invoer:</strong>
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Overzicht klassen (LINKS) -->
            <div class="col-12 col-lg-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white fw-semibold">Overzicht klassen</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Klas</th>
                                        <th>Leerjaar</th>
                                        <th>Schooljaar</th>
                                        <th class="text-end">Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($klas_resultaat->num_rows > 0): ?>
                                        <?php while ($klas = $klas_resultaat->fetch_assoc()): ?>
                                            <tr<?= $gemarkeerde_klas_id === (int)$klas['klas_id'] ? ' class="table-warning"' : '' ?>>
                                                <td><?= e($klas['klasaanduiding']) ?></td>
                                                <td><?= e($klas['leerjaar']) ?></td>
                                                <td><?= e($klas['schooljaar']) ?></td>
                                                <td class="text-end">
                                                    <div class="btn-group" role="group">
                                                        <a href="leerlingen.php?klas_id=<?= (int)$klas['klas_id'] ?>" class="btn btn-dark btn-sm">
                                                            <i class="bi bi-houses"></i> Leerlingen
                                                        </a>
                                                        <a href="klassen.php?school_id=<?= $school_id ?>&edit=<?= (int)$klas['klas_id'] ?>" class="btn btn-primary btn-sm">
                                                            <i class="bi bi-pencil-square"></i> Bewerken
                                                        </a>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Weet je zeker dat je deze klas wilt verwijderen?');">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="delete" value="1">
                                                            <input type="hidden" name="klas_id" value="<?= (int)$klas['klas_id'] ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="bi bi-trash"></i> Verwijderen
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">Geen klassen gevonden.</td>
                                            </tr>
                                        <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toevoegen / Bewerken (RECHTS) -->
            <div class="col-12 col-lg-4">
                <?php if (!isset($_GET['edit'])): ?>
                    <!-- Klas toevoegen -->
                    <div class="card shadow-sm mb-4 mb-lg-0">
                        <div class="card-header bg-success text-white fw-semibold">
                            Klas toevoegen
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <?= csrf_input() ?>
                                <div class="col-12 mb-2">
                                    <label for="klas_naam" class="form-label">Klasnaam</label>
                                    <input type="text" name="klasaanduiding" id="klas_naam" class="form-control form-input" placeholder="Bijv: 3A" value="<?= htmlspecialchars($_POST['klasaanduiding'] ?? '') ?>" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <label for="klas_leerjaar" class="form-label">Leerjaar</label>
                                    <input type="text" name="leerjaar" id="klas_leerjaar" class="form-control form-input" placeholder="Bijv: 3" value="<?= htmlspecialchars($_POST['leerjaar'] ?? '') ?>" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <label for="klas_schooljaar" class="form-label">Schooljaar</label>
                                    <select name="schooljaar" id="klas_schooljaar" class="form-select form-input" required>
                                        <?php
                                        $gekozen_sj = $_POST['schooljaar'] ?? get_huidig_schooljaar();
                                        $sj_lijst   = get_schooljaren();
                                        if (!in_array($gekozen_sj, $sj_lijst, true) && $gekozen_sj !== '') {
                                            array_unshift($sj_lijst, $gekozen_sj);
                                        }
                                        foreach ($sj_lijst as $sj):
                                        ?>
                                            <option value="<?= htmlspecialchars($sj) ?>" <?= $gekozen_sj === $sj ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sj) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 d-grid mt-3">
                                    <button type="submit" name="add" class="btn btn-success w-100">
                                        Opslaan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    $te_bewerken_klas_id = (int)$_GET['edit'];
                    $stmt = $conn->prepare("SELECT * FROM klas WHERE klas_id=? AND school_id=?");
                    $stmt->bind_param("ii", $te_bewerken_klas_id, $school_id);
                    $stmt->execute();
                    $klas = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $stmt = $conn->prepare("SELECT * FROM klas_voorkeur WHERE klas_id=? ORDER BY volgorde ASC");
                    $stmt->bind_param("i", $te_bewerken_klas_id);
                    $stmt->execute();
                    $voorkeuren = $stmt->get_result();
                    $stmt->close();

                    //$huidig_max = in_array((int)($klas['max_keuzes'] ?? 2), [2, 3], true) ? (int)$klas['max_keuzes'] : 2;
                    ?>
                    <div class="card shadow-sm mb-4 mb-lg-0">
                        <div class="card-header bg-warning text-dark fw-semibold">
                            Klas bewerken
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <?= csrf_input() ?>
                                <input type="hidden" name="klas_id" value="<?= (int)$klas['klas_id'] ?>">

                                <div class="col-12 mb-2">
                                    <label for="edit_klas_naam" class="form-label">Klasnaam</label>
                                    <input type="text" name="klasaanduiding" id="edit_klas_naam" class="form-control form-input"
                                        value="<?= e($klas['klasaanduiding']) ?>" placeholder="Bijv: 3A" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <label for="edit_klas_leerjaar" class="form-label">Leerjaar</label>
                                    <input type="text" name="leerjaar" id="edit_klas_leerjaar" class="form-control form-input"
                                        value="<?= e($klas['leerjaar']) ?>" placeholder="Bijv: 3" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <label for="edit_klas_schooljaar" class="form-label">Schooljaar</label>
                                    <select name="schooljaar" id="edit_klas_schooljaar" class="form-select form-input" required>
                                        <?php
                                        $gekozen_schooljaar = $klas['schooljaar'];
                                        $schooljaar_lijst = get_schooljaren();
                                        if (!in_array($gekozen_schooljaar, $schooljaar_lijst, true) && $gekozen_schooljaar !== '') {
                                            array_unshift($schooljaar_lijst, $gekozen_schooljaar);
                                        }
                                        foreach ($schooljaar_lijst as $schooljaar):
                                        ?>
                                            <option value="<?= e($schooljaar) ?>" <?= $gekozen_schooljaar === $schooljaar ? 'selected' : '' ?>>
                                                <?= e($schooljaar) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 d-flex gap-2 mt-3">
                                    <button type="submit" name="update" class="btn btn-warning text-dark w-50">
                                        Opslaan
                                    </button>
                                    <a href="klassen.php?school_id=<?= $school_id ?>" class="btn btn-secondary w-50 d-flex align-items-center justify-content-center">
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