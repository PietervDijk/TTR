
<?php

require 'includes/header.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
// Haal alle leerlingen op met gekoppelde klas en wereldnamen
$sql = "
SELECT 
    l.leerling_id, 
    l.voornaam, 
    l.tussenvoegsel, 
    l.achternaam, 
    k.klasaanduiding,
    w1.naam AS voorkeur1,
    w2.naam AS voorkeur2,
    w3.naam AS voorkeur3
FROM leerling l
JOIN klas k ON l.klas_id = k.klas_id
LEFT JOIN wereld_sector w1 ON l.voorkeur1_wereld_sector_id = w1.wereld_sector_id
LEFT JOIN wereld_sector w2 ON l.voorkeur2_wereld_sector_id = w2.wereld_sector_id
LEFT JOIN wereld_sector w3 ON l.voorkeur3_wereld_sector_id = w3.wereld_sector_id
ORDER BY l.leerling_id DESC
";
$leerlingen = $conn->query($sql);
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col d-flex justify-content-between align-items-center">
            <h2 class="fw-bold text-primary mb-0">Overzicht leerlingen</h2>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Terug naar formulier
            </a>
        </div>
    </div>

    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success text-center">
            Je bent aan gevoeged
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white fw-semibold">
            Alle leerlingen
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Naam</th>
                    <th>Klas</th>
                    <th>Voorkeur 1</th>
                    <th>Voorkeur 2</th>
                    <th>Voorkeur 3</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($leerlingen->num_rows === 0): ?>
                    <tr><td colspan="6" class="text-center py-3 text-muted">Nog geen leerlingen toegevoegd.</td></tr>
                <?php else: ?>
                    <?php while ($l = $leerlingen->fetch_assoc()): ?>
                        <tr>
                            <td><?= ($l['voornaam'] . ' ' . ($l['tussenvoegsel'] ? $l['tussenvoegsel'] . ' ' : '') . $l['achternaam']) ?></td>
                            <td><?= ($l['klasaanduiding']) ?></td>
                            <td><?= ($l['voorkeur1']) ?></td>
                            <td><?= ($l['voorkeur2']) ?></td>
                            <td><?= ($l['voorkeur3']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
