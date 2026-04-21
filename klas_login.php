<?php
// Leerling-login in 2 stappen: code ingeven, dan school/klas kiezen
require 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    unset($_SESSION['klas_id'], $_SESSION['heeft_ingevuld'], $_SESSION['leerling_id'], $_SESSION['mag_wijzigen']);
    header('Location: klas_login.php');
    exit;
}

$foutmelding = '';
$stap = 1;
$pincode = '';
$geselecteerde_school_id = 0;
$geselecteerde_klas_id = 0;
$bezoek_id = 0;
$klassen = [];
$schools = [];

function haal_bezoek_op_via_pincode(mysqli $database, string $pincode): ?array {
    // Query bezoek op pincode
    $stmt = $database->prepare('SELECT bezoek_id FROM bezoek WHERE pincode = ? AND actief = 1 LIMIT 1');
    $stmt->bind_param('s', $pincode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function haal_klassen_op_voor_bezoek(mysqli $database, int $bezoek_id): array {
    // Laad alle klassen voor dit bezoek
    $stmt = $database->prepare('
        SELECT k.klas_id, k.klasaanduiding, k.leerjaar, s.school_id, s.schoolnaam
        FROM bezoek_klas bk
        INNER JOIN klas k ON k.klas_id = bk.klas_id
        INNER JOIN school s ON s.school_id = k.school_id
        WHERE bk.bezoek_id = ?
        ORDER BY s.schoolnaam ASC, k.klasaanduiding ASC
    ');
    $stmt->bind_param('i', $bezoek_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function bouw_scholenlijst(array $klassen): array {
    // Bouw unieke scholenlijst vanuit klassesen
    $scholen = [];
    foreach ($klassen as $row) {
        $school_id = (int)$row['school_id'];
        if (!isset($scholen[$school_id])) {
            $scholen[$school_id] = $row['schoolnaam'];
        }
    }
    return $scholen;
}

if (isset($_POST['submit_code'])) {
    $pincode = trim($_POST['pincode'] ?? '');

    if ($pincode === '') {
        $foutmelding = 'Voer alstublieft de bezoekcode in.';
    } else {
        $bezoek = haal_bezoek_op_via_pincode($conn, $pincode);
        if (!$bezoek) {
            $foutmelding = 'Wachtwoord klopt niet!';
        } else {
            $bezoek_id = (int)$bezoek['bezoek_id'];
            $klassen = haal_klassen_op_voor_bezoek($conn, $bezoek_id);

            if (empty($klassen)) {
                $foutmelding = 'Er zijn geen klassen gekoppeld aan deze bezoekcode.';
            } else {
                $schools = bouw_scholenlijst($klassen);
                $stap = 2;
            }
        }
    }
}

if (isset($_POST['submit_login'])) {
    $pincode = trim($_POST['pincode'] ?? '');
    $geselecteerde_school_id = (int)($_POST['school_id'] ?? 0);
    $geselecteerde_klas_id = (int)($_POST['klas_id'] ?? 0);

    if ($pincode === '') {
        $foutmelding = 'Bezoekcode ontbreekt. Probeer opnieuw.';
        $stap = 1;
    } else {
        $bezoek = haal_bezoek_op_via_pincode($conn, $pincode);
        if (!$bezoek) {
            $foutmelding = 'Ongeldige code. Voer de bezoekcode opnieuw in.';
            $stap = 1;
        } else {
            $bezoek_id = (int)$bezoek['bezoek_id'];
            $klassen = haal_klassen_op_voor_bezoek($conn, $bezoek_id);
            $schools = bouw_scholenlijst($klassen);
            $stap = 2;

            if (empty($klassen)) {
                $foutmelding = 'Er zijn geen klassen gekoppeld aan deze bezoekcode.';
            } elseif ($geselecteerde_school_id <= 0) {
                $foutmelding = 'Selecteer een school.';
            } elseif ($geselecteerde_klas_id <= 0) {
                $foutmelding = 'Selecteer een klas.';
            } else {
                $stmt = $conn->prepare('
                    SELECT 1
                    FROM bezoek_klas bk
                    INNER JOIN klas k ON k.klas_id = bk.klas_id
                    WHERE bk.bezoek_id = ? AND k.klas_id = ? AND k.school_id = ?
                    LIMIT 1
                ');
                $stmt->bind_param('iii', $bezoek_id, $geselecteerde_klas_id, $geselecteerde_school_id);
                $stmt->execute();
                $valid = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$valid) {
                    $foutmelding = 'Ongeldige school/klas combinatie voor deze bezoekcode.';
                } else {
                    $_SESSION['klas_id'] = $geselecteerde_klas_id;
                    header('Location: index.php?klas_id=' . $geselecteerde_klas_id);
                    exit;
                }
            }
        }
    }
}

require 'includes/header.php';
?>

<div class="ttr-app">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white fw-semibold text-center py-4">
                        <i class="bi bi-door-open"></i> Inloggen
                    </div>

                    <div class="card-body p-4">
                        <p class="text-muted text-center mb-4">
                            <small>Voer de bezoekcode in en kies daarna je school en klas.</small>
                        </p>

                        <?php if ($foutmelding !== ''): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle"></i> <?= e($foutmelding) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($stap === 1): ?>
                            <form method="post" action="klas_login.php" autocomplete="off">
                                <div class="mb-4">
                                    <label for="pincode" class="form-label fw-semibold">
                                        <i class="bi bi-key"></i> Bezoekcode
                                    </label>
                                    <input
                                        type="password"
                                        id="pincode"
                                        name="pincode"
                                        class="form-control form-control-lg"
                                        placeholder="Voer bezoekcode in"
                                        required
                                        autofocus>
                                </div>

                                <div class="button-group-klas">
                                    <button type="submit" name="submit_code" class="btn btn-primary btn-klas">
                                        <i class="bi bi-check-circle"></i> Doorgaan
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="post" action="klas_login.php" autocomplete="off">
                                <input type="hidden" name="pincode" value="<?= htmlspecialchars($pincode) ?>">

                                <div class="mb-3">
                                    <label for="school_id" class="form-label fw-semibold">
                                        <i class="bi bi-building"></i> School
                                    </label>
                                    <select id="school_id" name="school_id" class="form-select form-select-lg" required>
                                        <option value="" disabled <?= $geselecteerde_school_id <= 0 ? 'selected' : '' ?>>-- Kies school --</option>
                                        <?php foreach ($schools as $sid => $snaam): ?>
                                            <option value="<?= (int)$sid ?>" <?= $geselecteerde_school_id === (int)$sid ? 'selected' : '' ?>>
                                                <?= e($snaam) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="klas_id" class="form-label fw-semibold">
                                        <i class="bi bi-people"></i> Klas
                                    </label>
                                    <select id="klas_id" name="klas_id" class="form-select form-select-lg" required>
                                        <option value="" disabled <?= $geselecteerde_klas_id <= 0 ? 'selected' : '' ?>>-- Kies klas --</option>
                                        <?php foreach ($klassen as $k): ?>
                                            <option value="<?= (int)$k['klas_id'] ?>" <?= $geselecteerde_klas_id === (int)$k['klas_id'] ? 'selected' : '' ?>>
                                                <?= e($k['schoolnaam']) ?> - <?= e($k['klasaanduiding']) ?>
                                                <?php if (!empty($k['leerjaar'])): ?>
                                                    (leerjaar <?= e($k['leerjaar']) ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="submit_login" class="btn btn-success w-100">
                                        <i class="bi bi-box-arrow-in-right"></i> Inloggen
                                    </button>
                                    <a href="klas_login.php?reset=1" class="btn btn-outline-secondary w-100">
                                        Andere code
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="klas-footer mt-4">
                            <p class="text-center text-muted small mb-0">
                                <i class="bi bi-info-circle"></i> Vraag je docent om de bezoekcode.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
