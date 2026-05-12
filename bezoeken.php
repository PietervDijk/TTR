<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Overzicht van bezoeken en verwijderactie
if (isset($_GET['action']) && in_array($_GET['action'], ['schools', 'klassen'], true)) {
    require 'bezoeken_ajax.php';
    exit;
}

if (isset($_GET['edit'])) {
    header('Location: bezoek_formulier.php?edit=' . (int)$_GET['edit']);
    exit;
}

require 'includes/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

csrf_validate();

$foutmeldingen = [];
$succesmelding = null;
$gemarkeerde_bezoek_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : null;

if (isset($_SESSION['bezoeken_errors'])) {
    $foutmeldingen = $_SESSION['bezoeken_errors'];
    unset($_SESSION['bezoeken_errors']);
}

if (isset($_SESSION['bezoeken_success'])) {
    $succesmelding = $_SESSION['bezoeken_success'];
    unset($_SESSION['bezoeken_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $te_verwijderen_bezoek_id = (int)($_POST['bezoek_id'] ?? 0);

    if ($te_verwijderen_bezoek_id <= 0) {
        $foutmeldingen[] = 'Ongeldig bezoek om te verwijderen.';
    } else {
        $stmt = $conn->prepare('SELECT bezoek_id FROM bezoek WHERE bezoek_id = ? LIMIT 1');
        $stmt->bind_param('i', $te_verwijderen_bezoek_id);
        $stmt->execute();
        $bestaat_bezoek = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bestaat_bezoek) {
            $foutmeldingen[] = 'Het geselecteerde bezoek bestaat niet (meer).';
        } else {
            $conn->begin_transaction();
            try {
                // Verwijder eerst de gekoppelde records en daarna het bezoek zelf
                foreach ([
                    'DELETE FROM bezoek_optie WHERE bezoek_id=?',
                    'DELETE FROM bezoek_klas WHERE bezoek_id=?',
                    'DELETE FROM bezoek_school WHERE bezoek_id=?',
                    'DELETE FROM bezoek WHERE bezoek_id=?',
                ] as $verwijder_sql) {
                    $stmt = $conn->prepare($verwijder_sql);
                    $stmt->bind_param('i', $te_verwijderen_bezoek_id);
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();
                csrf_regenerate();
                header('Location: bezoeken.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $foutmeldingen[] = 'Er is iets misgegaan bij het verwijderen van het bezoek.';
            }
        }
    }
}

$bezoek_resultaat = $conn->query('SELECT * FROM bezoek ORDER BY created_at DESC');
$type_labels = [
    'PO' => 'Primair Onderwijs',
    'VO' => 'Voortgezet Onderwijs',
    'MBO' => 'MBO',
];

require 'includes/header.php';
?>
<div class="ttr-app">
    <div class="container py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="fw-bold text-primary mb-0">Bezoeken beheren</h2>
                <p class="mb-0">Gebruik dit overzicht voor beheer en ga voor invoer naar het losse formulier.</p>
            </div>
            <a href="bezoek_formulier.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Nieuw bezoek
            </a>
        </div>

        <?php if (!empty($foutmeldingen)): ?>
            <div class="alert alert-danger">
                <strong>Controleer je invoer:</strong>
                <ul class="mb-0">
                    <?php foreach ($foutmeldingen as $foutmelding): ?>
                        <li><?= e($foutmelding) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($succesmelding): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?= e($succesmelding) ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white fw-semibold">
                Overzicht bezoeken
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Naam</th>
                                <th>Type</th>
                                <th>Pincode</th>
                                <th>Datum / Week</th>
                                <th>Status</th>
                                <th class="text-end">Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bezoek_resultaat && $bezoek_resultaat->num_rows > 0): ?>
                                <?php while ($bezoekRij = $bezoek_resultaat->fetch_assoc()): ?>
                                    <?php
                                        $type_label = $type_labels[$bezoekRij['type_onderwijs']] ?? $bezoekRij['type_onderwijs'];
                                        if ($bezoekRij['type_onderwijs'] === 'PO') {
                                            $datum_tekst = $bezoekRij['po_dag1'] ? date('d-m-Y', strtotime($bezoekRij['po_dag1'])) : '—';
                                            if ($bezoekRij['po_dag2']) {
                                                $datum_tekst .= ' / ' . date('d-m-Y', strtotime($bezoekRij['po_dag2']));
                                            }
                                        } else {
                                            $datum_tekst = $bezoekRij['vo_week_start'] ? date('d-m-Y', strtotime($bezoekRij['vo_week_start'])) : '—';
                                            if ($bezoekRij['vo_week_eind']) {
                                                $datum_tekst .= ' t/m ' . date('d-m-Y', strtotime($bezoekRij['vo_week_eind']));
                                            }
                                        }
                                    ?>
                                    <tr<?= $gemarkeerde_bezoek_id === (int)$bezoekRij['bezoek_id'] ? ' class="table-warning"' : '' ?>>
                                        <td><?= e($bezoekRij['naam']) ?></td>
                                        <td><?= e($type_label) ?></td>
                                        <td><code><?= e($bezoekRij['pincode']) ?></code></td>
                                        <td><?= e($datum_tekst) ?></td>
                                        <td>
                                            <?php if ($bezoekRij['actief']): ?>
                                                <span class="badge bg-success">Actief</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactief</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group" role="group">
                                                <a href="indeling.php?bezoek_id=<?= (int)$bezoekRij['bezoek_id'] ?>" class="btn btn-dark btn-sm" title="Open verdeling voor alle gekoppelde klassen">
                                                    <i class="bi bi-diagram-3"></i> Verdeling
                                                </a>
                                                <a href="bezoek_formulier.php?edit=<?= (int)$bezoekRij['bezoek_id'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-pencil-square"></i> Bewerken
                                                </a>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Weet je zeker dat je dit bezoek wilt verwijderen?');">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="delete" value="1">
                                                    <input type="hidden" name="bezoek_id" value="<?= (int)$bezoekRij['bezoek_id'] ?>">
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
                                    <td colspan="6" class="text-muted text-center py-3">Nog geen bezoeken aangemaakt.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>