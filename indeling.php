<?php
// Verdeling: leerlingen direct indelen via tabelvelden per bezoek
require 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Vanaf hier: normale pagina-rendering (geen AJAX)
require 'includes/header.php';

$paginaTitel = 'Verdeling - ' . ($bezoekGegevens['naam'] ?? 'Bezoek');
$paginaSubtitel = ((int)($bezoekStatistiek['scholen_count'] ?? 0)) . ' scholen • ' . ((int)($bezoekStatistiek['klassen_count'] ?? 0)) . ' klassen';
?>

<div class="ttr-app">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1"><?= e($paginaTitel) ?></h2>
                <div class="text-muted"><?= e($paginaSubtitel) ?></div>
            </div>
            <div class="d-flex gap-2">
                <a href="bezoeken.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Terug naar bezoeken
                </a>
                <button id="btnAuto" class="btn btn-primary">
                    <i class="bi bi-lightning"></i> Verdelen
                </button>
                <button id="btnSave" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Opslaan
                </button>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Leerlingen indelen</span>
                <span class="badge bg-light text-primary"><?= count($leerlingen_lijst) ?> leerling(en)</span>
            </div>
            <div class="card-body p-0">
                <div style="overflow-x: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Naam</th>
                                <th>School</th>
                                <th>Klas</th>
                                <th>Voorkeur 1</th>
                                <th>Voorkeur 2</th>
                                <?php if ($maxKeuzes >= 3): ?><th>Voorkeur 3</th><?php endif; ?>
                                <?php if ($is_po_bezoek): ?>
                                    <th>Toegewezen Dag 1</th>
                                    <th>Toegewezen Dag 2</th>
                                <?php else: ?>
                                    <th>Toegewezen week</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 small text-muted">
            <strong>Legenda:</strong> <span class="text-danger">*</span> = keuze/ID staat niet (meer) in de optielijst van dit bezoek.
        </div>
    </div>
</div>


<?php require 'includes/footer.php'; ?>
