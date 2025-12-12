<?php
require 'includes/header.php';

// Als reset=1 parameter aanwezig, maak de klas sessie schoon
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    unset($_SESSION['klas_id']);
    unset($_SESSION['heeft_ingevuld']);
    unset($_SESSION['leerling_id']);
    unset($_SESSION['mag_wijzigen']);
}

$error = '';

if (isset($_POST['submit'])) {
    $pincode = trim($_POST['pincode']);

    if (empty($pincode)) {
        $error = "Voer alstublieft een wachtwoord in.";
    } else {
        $stmt = $conn->prepare("SELECT klas_id FROM klas WHERE pincode = ?");
        $stmt->bind_param("s", $pincode);
        $stmt->execute();
        $result = $stmt->get_result();
        $klas = $result->fetch_assoc();
        $stmt->close();

        if ($klas) {
            $_SESSION['klas_id'] = $klas['klas_id'];
            header("Location: index.php?klas_id=" . (int)$klas['klas_id']);
            exit;
        } else {
            $error = "Wachtwoord klopt niet!";
        }
    }
}
?>

<div class="ttr-app">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white fw-semibold text-center py-4">
                        <i class="bi bi-door-open"></i> Klas Inloggen
                    </div>

                    <div class="card-body p-4">
                        <p class="text-muted text-center mb-4">
                            <small>Voer het klas-wachtwoord in om uw voorkeuren in te vullen</small>
                        </p>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" autocomplete="off">
                            <div class="mb-4">
                                <label for="pincode" class="form-label fw-semibold">
                                    <i class="bi bi-key"></i> Wachtwoord
                                </label>
                                <input
                                    type="password"
                                    id="pincode"
                                    name="pincode"
                                    class="form-control form-control-lg"
                                    placeholder="Voer wachtwoord in"
                                    required
                                    autofocus>
                            </div>

                            <div class="button-group-klas">
                                <button type="submit" name="submit" class="btn btn-primary btn-klas">
                                    <i class="bi bi-check-circle"></i> Doorgaan
                                </button>
                            </div>
                        </form>

                        <div class="klas-footer mt-4">
                            <p class="text-center text-muted small">
                                <i class="bi bi-info-circle"></i> Weet je het wachtwoord niet? Vraag dit aan je docent.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>