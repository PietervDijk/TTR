<?php
require_once 'includes/config.php';

// Start een sessie als die nog niet actief is.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Alleen ingelogde admins met de rol 'superadmin' mogen dit scherm zien.
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_rol'] ?? '') !== 'superadmin') {
    header('Location: index.php');
    exit;
}

// Controleer beveiligingstoken voor POST-verzoeken.
csrf_validate();

/**
 * Zorg dat de kolom 'rol' bestaat in de tabel admin.
 * Dit is een éénmalige controle die veilig meerdere keren kan draaien.
 */
function ensure_admin_rol_column(mysqli $conn): void
{
    $result = $conn->query("SHOW COLUMNS FROM admin LIKE 'rol'");
    if ($result && $result->num_rows > 0) {
        return;
    }

    @$conn->query("ALTER TABLE admin ADD COLUMN rol ENUM('admin','superadmin') NOT NULL DEFAULT 'admin' AFTER naam");
}

ensure_admin_rol_column($conn);

$foutmeldingen = [];
$succesmelding = null;

/**
 * Haal een waarde uit POST op en geef een standaardwaarde terug.
 */
function get_post_value(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $default);
}

/**
 * Valideer admin formuliergegevens.
 */
function valideer_admin_data(string $naam, string $email, string $rol): array
{
    $errors = [];

    if ($naam === '') {
        $errors[] = 'Naam is verplicht.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geldig e-mailadres is verplicht.';
    }

    if (!in_array($rol, ['admin', 'superadmin'], true)) {
        $errors[] = 'Kies een geldige rol.';
    }

    return $errors;
}

// Verwerk formulieracties.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['action'] ?? '';

    if ($actie === 'add') {
        $naam = get_post_value('naam');
        $email = get_post_value('email');
        $password = $_POST['password'] ?? '';
        $rol = get_post_value('rol', 'admin');

        $foutmeldingen = valideer_admin_data($naam, $email, $rol);
        if ($password === '') {
            $foutmeldingen[] = 'Wachtwoord is verplicht.';
        }

        if (empty($foutmeldingen)) {
            $stmt = $conn->prepare('INSERT INTO admin (email, password, naam, rol) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $email, $password, $naam, $rol);
            $stmt->execute();
            $nieuw_id = $conn->insert_id;
            $stmt->close();

            csrf_regenerate();
            $_SESSION['admin_read_success'] = 'Admin toegevoegd.';
            header('Location: admin.php?highlight=' . (int)$nieuw_id);
            exit;
        }

        $_SESSION['admin_read_errors'] = $foutmeldingen;
        $_SESSION['admin_form_data'] = $_POST;
        header('Location: admin.php');
        exit;
    }

    if ($actie === 'update') {
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        $naam = get_post_value('naam');
        $email = get_post_value('email');
        $password = $_POST['password'] ?? '';
        $rol = get_post_value('rol', 'admin');

        if ($admin_id <= 0) {
            $foutmeldingen[] = 'Ongeldige admin geselecteerd.';
        }

        $foutmeldingen = array_merge($foutmeldingen, valideer_admin_data($naam, $email, $rol));

        if (empty($foutmeldingen)) {
            if ($password !== '') {
                $stmt = $conn->prepare('UPDATE admin SET naam=?, email=?, password=?, rol=? WHERE id=?');
                $stmt->bind_param('ssssi', $naam, $email, $password, $rol, $admin_id);
            } else {
                $stmt = $conn->prepare('UPDATE admin SET naam=?, email=?, rol=? WHERE id=?');
                $stmt->bind_param('sssi', $naam, $email, $rol, $admin_id);
            }
            $stmt->execute();
            $stmt->close();

            csrf_regenerate();
            $_SESSION['admin_read_success'] = 'Admin bijgewerkt.';
            header('Location: admin.php?highlight=' . $admin_id);
            exit;
        }

        $_SESSION['admin_read_errors'] = $foutmeldingen;
        $_SESSION['admin_form_data'] = $_POST;
        header('Location: admin.php?edit=' . $admin_id);
        exit;
    }

    if ($actie === 'delete') {
        $admin_id = (int)($_POST['admin_id'] ?? 0);

        if ($admin_id <= 0) {
            $foutmeldingen[] = 'Ongeldige admin geselecteerd.';
        }

        if ($admin_id === (int)($_SESSION['admin_id'] ?? 0)) {
            $foutmeldingen[] = 'Je kunt jezelf niet verwijderen.';
        }

        if (empty($foutmeldingen)) {
            $stmt = $conn->prepare('DELETE FROM admin WHERE id = ?');
            $stmt->bind_param('i', $admin_id);
            $stmt->execute();
            $stmt->close();

            csrf_regenerate();
            $_SESSION['admin_read_success'] = 'Admin verwijderd.';
            header('Location: admin.php');
            exit;
        }

        $_SESSION['admin_read_errors'] = $foutmeldingen;
        header('Location: admin.php');
        exit;
    }
}

// Haal alle admins op voor het overzicht.
$admins = [];
$res = $conn->query('SELECT id, naam, email, rol FROM admin ORDER BY id ASC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $admins[] = $row;
    }
}

// Houd ingevulde form waarden vast als er een fout optreedt.
$ingevuldeGegevens = $_SESSION['admin_form_data'] ?? [];
unset($_SESSION['admin_form_data']);

// Flash messages ophalen en daarna verwijderen.
$foutmeldingen = $_SESSION['admin_read_errors'] ?? [];
unset($_SESSION['admin_read_errors']);
$succesmelding = $_SESSION['admin_read_success'] ?? null;
unset($_SESSION['admin_read_success']);

require 'includes/header.php';
?>

<div class="ttr-app">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="fw-bold text-primary mb-0">Admins</h2>
                    <a href="admin.php" class="btn btn-outline-secondary">Verversen</a>
                </div>

                <?php if (!empty($foutmeldingen)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($foutmeldingen as $f): ?>
                                <li><?= e($f) ?></li>
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
                    <div class="card-header bg-primary text-white fw-semibold">Overzicht admins</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Naam</th>
                                        <th>E-mail</th>
                                        <th>Rol</th>
                                        <th class="text-end">Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($admins)): ?>
                                        <tr><td colspan="4" class="text-center small text-muted">Geen admins gevonden.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($admins as $a): ?>
                                            <tr>
                                                <td><?= e($a['naam']) ?></td>
                                                <td><?= e($a['email']) ?></td>
                                                <td>
                                                    <span class="badge <?= ($a['rol'] ?? 'admin') === 'superadmin' ? 'bg-dark' : 'bg-secondary' ?>">
                                                        <?= e($a['rol'] ?? 'admin') ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group" role="group">
                                                        <a href="admin.php?edit=<?= (int)$a['id'] ?>" class="btn btn-primary btn-sm">
                                                            <i class="bi bi-pencil-square"></i> Bewerken
                                                        </a>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Weet je het zeker?');">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="bi bi-trash"></i> Verwijderen
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <?php
                $te_bewerken = null;
                if (isset($_GET['edit'])) {
                    $id = (int)$_GET['edit'];
                    $stmt = $conn->prepare('SELECT id, naam, email, rol FROM admin WHERE id=?');
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $te_bewerken = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }
                ?>

                <?php if (!$te_bewerken): ?>
                    <div class="card shadow-sm mb-4 mb-lg-0">
                        <div class="card-header bg-success text-white fw-semibold">Admin toevoegen</div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="add">
                                <div class="col-12 mb-2">
                                    <label for="naam" class="form-label">Naam</label>
                                    <input type="text" name="naam" id="naam" class="form-control form-input" placeholder="Naam" value="<?= e($ingevuldeGegevens['naam'] ?? '') ?>" required>
                                </div>
                                <div class="col-12 mb-2">
                                    <label for="rol" class="form-label">Rol</label>
                                    <select name="rol" id="rol" class="form-control form-select" required>
                                        <option value="admin" <?= (($ingevuldeGegevens['rol'] ?? 'admin') === 'admin') ? 'selected' : '' ?>>Admin</option>
                                        <option value="superadmin" <?= (($ingevuldeGegevens['rol'] ?? 'admin') === 'superadmin') ? 'selected' : '' ?>>Superadmin</option>
                                    </select>
                                </div>
                                <div class="col-12 mb-2">
                                    <label for="email" class="form-label">E-mail</label>
                                    <input type="email" name="email" id="email" class="form-control form-input" placeholder="E-mail" value="<?= e($ingevuldeGegevens['email'] ?? '') ?>" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="password" class="form-label">Wachtwoord</label>
                                    <input type="password" name="password" id="password" class="form-control form-input" required>
                                </div>
                                <div class="col-12 d-grid mt-2">
                                    <button type="submit" class="btn btn-success">Toevoegen</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card shadow-sm mb-4 mb-lg-0">
                        <div class="card-header bg-warning text-dark fw-semibold">Admin bewerken</div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="admin_id" value="<?= (int)$te_bewerken['id'] ?>">
                                <div class="col-12 mb-2">
                                    <label for="edit_naam" class="form-label">Naam</label>
                                    <input type="text" name="naam" id="edit_naam" class="form-control form-input" value="<?= e($te_bewerken['naam']) ?>" required>
                                </div>
                                <div class="col-12 mb-2">
                                    <label for="edit_rol" class="form-label">Rol</label>
                                    <select name="rol" id="edit_rol" class="form-control form-select" required>
                                        <option value="admin" <?= ($te_bewerken['rol'] ?? 'admin') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="superadmin" <?= ($te_bewerken['rol'] ?? 'admin') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                                    </select>
                                </div>
                                <div class="col-12 mb-2">
                                    <label for="edit_email" class="form-label">E-mail</label>
                                    <input type="email" name="email" id="edit_email" class="form-control form-input" value="<?= e($te_bewerken['email']) ?>" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="edit_password" class="form-label">Wachtwoord (leeg = ongewijzigd)</label>
                                    <input type="password" name="password" id="edit_password" class="form-control form-input">
                                </div>
                                <div class="col-12 d-flex gap-2 mt-2">
                                    <button type="submit" class="btn btn-warning text-dark w-50">Opslaan</button>
                                    <a href="admin.php" class="btn btn-secondary w-50 d-flex align-items-center justify-content-center">Annuleren</a>
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
