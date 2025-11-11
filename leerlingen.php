<?php
require 'includes/header.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// ==========================
// Validatie: klas_id vereist
// ==========================
if (!isset($_GET['klas_id']) || !ctype_digit($_GET['klas_id'])) {
    // Als geen klas is gekozen: terug naar scholenoverzicht
    header('Location: scholen.php');
    exit;
}

$klas_id = (int)$_GET['klas_id'];

// ==========================
// Klas + schoolinfo ophalen
// ==========================
$stmt = $conn->prepare("
    SELECT k.klas_id, k.school_id, k.klasaanduiding, k.leerjaar, k.schooljaar, s.schoolnaam
    FROM klas k
    JOIN school s ON s.school_id = k.school_id
    WHERE k.klas_id = ?
");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$klas = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$klas) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Klas niet gevonden.</div></div>";
    require 'includes/footer.php';
    exit;
}

// ==========================
// Leerlingen uit deze klas
// ==========================
$stmt = $conn->prepare("
    SELECT leerling_id, voornaam, tussenvoegsel, achternaam,
           voorkeur1, voorkeur2, voorkeur3, voorkeur4, voorkeur5, toegewezen_voorkeur
    FROM leerling
    WHERE klas_id = ?
    ORDER BY achternaam ASC, voornaam ASC
");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$leerlingen = $stmt->get_result();
$stmt->close();
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold text-primary mb-1">
                    Leerlingen – <?= htmlspecialchars($klas['klasaanduiding']) ?>
                </h2>
                <div class="text-muted">
                    <?= htmlspecialchars($klas['schoolnaam']) ?> • Leerjaar <?= htmlspecialchars($klas['leerjaar']) ?> • Schooljaar <?= htmlspecialchars($klas['schooljaar']) ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="klassen.php?school_id=<?= (int)$klas['school_id'] ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Terug naar klassen
                </a>
                <a href="scholen.php" class="btn btn-outline-secondary">
                    <i class="bi bi-building"></i> Scholen
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success text-center">
            Leerling toegevoegd.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Leerlingen in klas <?= htmlspecialchars($klas['klasaanduiding']) ?></span>
            <span class="badge bg-light text-primary"><?= (int)$leerlingen->num_rows ?> leerling(en)</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:220px;">Naam</th>
                        <th>Voorkeur 1</th>
                        <th>Voorkeur 2</th>
                        <th>Voorkeur 3</th>
                        <th>Voorkeur 4</th>
                        <th>Voorkeur 5</th>
                        <th>Toegewezen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($leerlingen->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center py-3 text-muted">
                                Nog geen leerlingen in deze klas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($l = $leerlingen->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($l['voornaam']) ?>
                                    <?= $l['tussenvoegsel'] ? htmlspecialchars(' ' . $l['tussenvoegsel']) : '' ?>
                                    <?= htmlspecialchars(' ' . $l['achternaam']) ?>
                                </td>
                                <td><?= htmlspecialchars($l['voorkeur1'] ?? '') ?></td>
                                <td><?= htmlspecialchars($l['voorkeur2'] ?? '') ?></td>
                                <td><?= htmlspecialchars($l['voorkeur3'] ?? '') ?></td>
                                <td><?= htmlspecialchars($l['voorkeur4'] ?? '') ?></td>
                                <td><?= htmlspecialchars($l['voorkeur5'] ?? '') ?></td>
                                <td class="<?= !empty($l['toegewezen_voorkeur']) ? 'fw-semibold text-success' : 'text-muted' ?>">
                                    <?= htmlspecialchars($l['toegewezen_voorkeur'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>