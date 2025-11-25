<?php
require 'includes/header.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if (!isset($_GET['klas_id']) || !ctype_digit($_GET['klas_id'])) {
    header('Location: scholen.php');
    exit;
}
$klas_id = (int)$_GET['klas_id'];

// --- Klas + school ---
$stmt = $conn->prepare("
    SELECT k.klas_id, k.school_id, k.klasaanduiding, k.leerjaar, k.schooljaar,
           k.max_keuzes,
           s.schoolnaam
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

// Bepaal hoeveel voorkeuren we tonen
$maxKeuzes = in_array((int)($klas['max_keuzes'] ?? 2), [2, 3], true)
    ? (int)$klas['max_keuzes']
    : 2;

// --- Toegestane opties voor deze klas ---
$stmt = $conn->prepare("
    SELECT id, naam
    FROM klas_voorkeur
    WHERE klas_id = ? AND actief = 1
    ORDER BY volgorde ASC
");
$stmt->bind_param("i", $klas_id);
$stmt->execute();
$res = $stmt->get_result();

$allowedById = [];
$allowedNames = [];
$allowedList = [];
while ($r = $res->fetch_assoc()) {
    $id   = (int)$r['id'];
    $naam = (string)$r['naam'];
    $allowedById[$id]                       = $naam;
    $allowedNames[mb_strtolower(trim($naam))] = $naam;
    $allowedList[]                          = $naam;
}
$stmt->close();

// --- Leerlingen ---
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

// --- Helpers ---
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function dash()
{
    return '<span class="text-muted">—</span>';
}

function renderChoice(?string $raw, array $allowedById, array $allowedNames): string
{
    $v = trim((string)$raw);
    if ($v === '') return dash();
    if (ctype_digit($v)) {
        $id = (int)$v;
        if (isset($allowedById[$id])) return e($allowedById[$id]);
        return '<span class="text-danger" title="ID niet (meer) geldig voor deze klas">' . e($v) . ' *</span>';
    }
    $norm = mb_strtolower($v);
    if (isset($allowedNames[$norm])) return e($allowedNames[$norm]);
    return '<span class="text-danger" title="Keuze staat niet (meer) in de lijst voor deze klas">' . e($v) . ' *</span>';
}

function renderAssignedChoice(?string $raw, array $allowedById, array $allowedNames): string
{
    $v = trim((string)$raw);
    if ($v === '') return dash();
    if (ctype_digit($v)) {
        $id = (int)$v;
        if (isset($allowedById[$id])) return '<span class="fw-semibold text-success">' . e($allowedById[$id]) . '</span>';
        return '<span class="text-danger" title="Toegewezen ID niet (meer) geldig voor deze klas">' . e($v) . ' *</span>';
    }
    $norm = mb_strtolower($v);
    if (isset($allowedNames[$norm])) return '<span class="fw-semibold text-success">' . e($allowedNames[$norm]) . '</span>';
    return '<span class="text-danger" title="Toegewezen keuze staat niet (meer) in de lijst voor deze klas">' . e($v) . ' *</span>';
}
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold text-primary mb-1">
                    Leerlingen – <?= e($klas['klasaanduiding']) ?>
                </h2>
                <div class="text-muted">
                    <?= e($klas['schoolnaam']) ?> • Leerjaar <?= e($klas['leerjaar']) ?> • Schooljaar <?= e($klas['schooljaar']) ?>
                </div>
                <?php if (!empty($allowedList)): ?>
                    <div class="mt-2 small">
                        <strong>Beschikbare sectoren:</strong>
                        <?= e(implode(', ', $allowedList)) ?>
                    </div>
                <?php endif; ?>
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

    <a href="verdeling.php?klas_id=<?= (int)$klas['klas_id'] ?>" class="btn btn-primary mb-3">
        <i class="bi bi-kanban"></i> Ga naar verdeling
    </a>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Leerling voorkeuren – <?= e($klas['klasaanduiding']) ?></span>
            <span class="badge bg-light text-primary"><?= (int)$leerlingen->num_rows ?> leerling(en)</span>
        </div>
        <div class="card-body p-0">
            <!-- Kleine extra: responsieve tabel -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:220px;">Naam</th>
                            <?php for ($i = 1; $i <= $maxKeuzes; $i++): ?>
                                <th>Voorkeur <?= $i ?></th>
                            <?php endfor; ?>
                            <th>Toegewezen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($leerlingen->num_rows === 0): ?>
                            <tr>
                                <td colspan="<?= 2 + $maxKeuzes ?>" class="text-center py-3 text-muted">
                                    Nog geen leerlingen in deze klas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($l = $leerlingen->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?= e($l['voornaam']) ?><?= $l['tussenvoegsel'] ? ' ' . e($l['tussenvoegsel']) : '' ?> <?= e($l['achternaam']) ?>
                                    </td>
                                    <?php for ($i = 1; $i <= $maxKeuzes; $i++): ?>
                                        <td><?= renderChoice($l['voorkeur' . $i] ?? '', $allowedById, $allowedNames) ?></td>
                                    <?php endfor; ?>
                                    <td><?= renderAssignedChoice($l['toegewezen_voorkeur'] ?? '', $allowedById, $allowedNames) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 small text-muted">
        <strong>Legenda:</strong> per “Voorkeur 1..<?= (int)$maxKeuzes ?>” tonen we de gekozen sector.
        <span class="text-danger">*</span> = keuze/ID staat niet (meer) in de lijst voor deze klas (geldt ook voor "Toegewezen").
    </div>
</div>

<?php require 'includes/footer.php'; ?>