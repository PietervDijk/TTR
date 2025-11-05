<?php
require 'includes/header.php';

// Alleen toegankelijk voor admins
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Controleer of school_id aanwezig is
if (!isset($_GET['school_id'])) {
    header("Location: scholen.php");
    exit;
}

$school_id = (int)$_GET['school_id'];

// Haal schoolnaam op
$stmt = $conn->prepare("SELECT schoolnaam FROM school WHERE school_id=?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$school) {
    echo "<div class='alert alert-danger m-5'>School niet gevonden.</div>";
    require 'includes/footer.php';
    exit;
}

/* ==========================
   ✅ KLAS TOEVOEGEN
========================== */
if (isset($_POST['add'])) {
    $klasaanduiding = trim($_POST['klasaanduiding']);
    $leerjaar = trim($_POST['leerjaar']);
    $schooljaar = trim($_POST['schooljaar']);
    $pincode = !empty($_POST['pincode']) ? trim($_POST['pincode']) : null;

    // Voeg klas toe
    $stmt = $conn->prepare("INSERT INTO klas (school_id, klasaanduiding, leerjaar, schooljaar, pincode) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $school_id, $klasaanduiding, $leerjaar, $schooljaar, $pincode);
    $stmt->execute();
    $klas_id = $conn->insert_id;
    $stmt->close();

    // Voeg voorkeuren toe
    if (!empty($_POST['voorkeuren'])) {
        foreach ($_POST['voorkeuren'] as $index => $naam) {
            if (trim($naam) === "") continue;
            $volgorde = $index + 1;

            // Voeg toe aan klas_voorkeur
            $stmt = $conn->prepare("INSERT INTO klas_voorkeur (klas_id, volgorde, naam, actief) VALUES (?, ?, ?, 1)");
            $stmt->bind_param("iis", $klas_id, $volgorde, $naam);
            $stmt->execute();
            $voorkeur_id = $conn->insert_id;
            $stmt->close();

            // Voeg automatisch 1 optie toe in voorkeur_opties
            $stmt = $conn->prepare("INSERT INTO voorkeur_opties (klas_voorkeur_id, naam) VALUES (?, ?)");
            $stmt->bind_param("is", $voorkeur_id, $naam);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: klassen.php?school_id=$school_id&highlight=$klas_id");
    exit;
}

/* ==========================
   ✅ KLAS UPDATEN
========================== */
if (isset($_POST['update'])) {
    $klas_id = (int)$_POST['klas_id'];
    $klasaanduiding = trim($_POST['klasaanduiding']);
    $leerjaar = trim($_POST['leerjaar']);
    $schooljaar = trim($_POST['schooljaar']);
    $pincode = !empty($_POST['pincode']) ? trim($_POST['pincode']) : null;

    // Update klasgegevens
    $stmt = $conn->prepare("UPDATE klas SET klasaanduiding=?, leerjaar=?, schooljaar=?, pincode=? WHERE klas_id=? AND school_id=?");
    $stmt->bind_param("ssssii", $klasaanduiding, $leerjaar, $schooljaar, $pincode, $klas_id, $school_id);
    $stmt->execute();
    $stmt->close();

    // Bestaande voorkeuren bijwerken
    if (!empty($_POST['voorkeur_id'])) {
        foreach ($_POST['voorkeur_id'] as $i => $v_id) {
            $naam = trim($_POST['voorkeur_naam'][$i]);
            if ($naam === "") continue;

            // Update voorkeur naam
            $stmt = $conn->prepare("UPDATE klas_voorkeur SET naam=? WHERE id=? AND klas_id=?");
            $stmt->bind_param("sii", $naam, $v_id, $klas_id);
            $stmt->execute();
            $stmt->close();

            // Update ook voorkeur_opties naam
            $stmt = $conn->prepare("UPDATE voorkeur_opties SET naam=? WHERE klas_voorkeur_id=?");
            $stmt->bind_param("si", $naam, $v_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Nieuwe voorkeur toevoegen (optioneel)
    if (!empty($_POST['nieuwe_voorkeuren'])) {
        foreach ($_POST['nieuwe_voorkeuren'] as $index => $naam) {
            if (trim($naam) === "") continue;

            $stmt = $conn->prepare("SELECT MAX(volgorde) AS max_volgorde FROM klas_voorkeur WHERE klas_id=?");
            $stmt->bind_param("i", $klas_id);
            $stmt->execute();
            $max = $stmt->get_result()->fetch_assoc()['max_volgorde'] ?? 0;
            $stmt->close();

            $volgorde = $max + 1;

            $stmt = $conn->prepare("INSERT INTO klas_voorkeur (klas_id, volgorde, naam, actief) VALUES (?, ?, ?, 1)");
            $stmt->bind_param("iis", $klas_id, $volgorde, $naam);
            $stmt->execute();
            $voorkeur_id = $conn->insert_id;
            $stmt->close();

            // Voeg direct ook optie toe
            $stmt = $conn->prepare("INSERT INTO voorkeur_opties (klas_voorkeur_id, naam) VALUES (?, ?)");
            $stmt->bind_param("is", $voorkeur_id, $naam);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: klassen.php?school_id=$school_id&highlight=$klas_id");
    exit;
}

/* ==========================
   ✅ KLAS VERWIJDEREN
========================== */
if (isset($_GET['delete'])) {
    $klas_id = (int)$_GET['delete'];

    // Cascade verwijderen
    $conn->query("DELETE FROM leerling WHERE klas_id=$klas_id");
    $conn->query("DELETE FROM klas_voorkeur WHERE klas_id=$klas_id");
    $conn->query("DELETE FROM klas WHERE klas_id=$klas_id");

    header("Location: klassen.php?school_id=$school_id");
    exit;
}


// ==========================
// KLAS OVERZICHT
// ==========================
$stmt = $conn->prepare("SELECT * FROM klas WHERE school_id=? ORDER BY leerjaar, klasaanduiding");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$klassen = $stmt->get_result();
$stmt->close();

$highlight_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : null;
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col d-flex justify-content-between align-items-center">
            <h2 class="fw-bold text-primary mb-0">Klassen – <?= htmlspecialchars($school['schoolnaam']) ?></h2>
            <a href="scholen.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Terug</a>
        </div>
    </div>

    <div class="row">
        <!-- Overzicht klassen -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white fw-semibold">Overzicht klassen</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Klas</th>
                            <th>Leerjaar</th>
                            <th>Schooljaar</th>
                            <th>Pincode</th>
                            <th class="text-end">Acties</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($klassen->num_rows > 0): ?>
                            <?php while($row = $klassen->fetch_assoc()): ?>
                                <tr<?= $highlight_id === (int)$row['klas_id'] ? ' class="table-warning"' : '' ?>>
                                    <td><?= $row['klas_id'] ?></td>
                                    <td><?= htmlspecialchars($row['klasaanduiding']) ?></td>
                                    <td><?= htmlspecialchars($row['leerjaar']) ?></td>
                                    <td><?= htmlspecialchars($row['schooljaar']) ?></td>
                                    <td><?= htmlspecialchars($row['pincode']) ?></td>
                                    <td class="text-end">
                                        <a href="klassen.php?school_id=<?= $school_id ?>&edit=<?= $row['klas_id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-pencil-square"></i> Bewerken
                                        </a>
                                        <a href="klassen.php?school_id=<?= $school_id ?>&delete=<?= $row['klas_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Weet je zeker dat je deze klas wilt verwijderen?')">
                                            <i class="bi bi-trash"></i> Verwijderen
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Geen klassen gevonden.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Toevoegen / Bewerken -->
        <div class="col-lg-4">
            <?php if (!isset($_GET['edit'])): ?>
                <!-- Nieuwe klas -->
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white fw-semibold">Klas toevoegen</div>
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-2">
                                <label class="form-label">Klas</label>
                                <input type="text" name="klasaanduiding" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Leerjaar</label>
                                <input type="text" name="leerjaar" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Schooljaar</label>
                                <input type="text" name="schooljaar" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Pincode (optioneel)</label>
                                <input type="text" name="pincode" class="form-control">
                            </div>

                            <hr>
                            <h6>Voorkeuren toevoegen</h6>
                            <div id="voorkeurenWrapper">
                                <div class="mb-2">
                                    <input type="text" name="voorkeuren[]" class="form-control" placeholder="Naam voorkeur">
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm mb-3" onclick="addVoorkeur()">+ Voeg voorkeur toe</button>

                            <button type="submit" name="add" class="btn btn-success w-100">Toevoegen</button>
                        </form>
                    </div>
                </div>

                <script>
                    function addVoorkeur() {
                        const wrapper = document.getElementById('voorkeurenWrapper');
                        const div = document.createElement('div');
                        div.classList.add('mb-2');
                        div.innerHTML = `<input type="text" name="voorkeuren[]" class="form-control" placeholder="Naam voorkeur">`;
                        wrapper.appendChild(div);
                    }
                </script>

            <?php else:
            // Bewerken
            $klas_id = (int)$_GET['edit'];
            $stmt = $conn->prepare("SELECT * FROM klas WHERE klas_id=? AND school_id=?");
            $stmt->bind_param("ii", $klas_id, $school_id);
            $stmt->execute();
            $klas = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $voorkeuren = $conn->query("SELECT * FROM klas_voorkeur WHERE klas_id=$klas_id ORDER BY volgorde ASC");
            ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark fw-semibold">Klas bewerken</div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="klas_id" value="<?= $klas['klas_id'] ?>">
                            <div class="mb-2">
                                <label class="form-label">Klas</label>
                                <input type="text" name="klasaanduiding" class="form-control" value="<?= htmlspecialchars($klas['klasaanduiding']) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Leerjaar</label>
                                <input type="text" name="leerjaar" class="form-control" value="<?= htmlspecialchars($klas['leerjaar']) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Schooljaar</label>
                                <input type="text" name="schooljaar" class="form-control" value="<?= htmlspecialchars($klas['schooljaar']) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Pincode</label>
                                <input type="text" name="pincode" class="form-control" value="<?= htmlspecialchars($klas['pincode']) ?>">
                            </div>

                            <hr>
                            <h6>Bestaande voorkeuren</h6>
                            <div id="voorkeurenWrapperEdit">
                                <?php while($v = $voorkeuren->fetch_assoc()): ?>
                                    <div class="mb-2">
                                        <input type="hidden" name="voorkeur_id[]" value="<?= $v['id'] ?>">
                                        <input type="text" name="voorkeur_naam[]" class="form-control" value="<?= ($v['naam']) ?>">
                                    </div>
                                <?php endwhile; ?>
                            </div>

                            <h6 class="mt-3">Nieuwe voorkeur toevoegen</h6>
                            <div id="nieuweVoorkeurenWrapper">
                                <div class="mb-2">
                                    <input type="text" name="nieuwe_voorkeuren[]" class="form-control" placeholder="Nieuwe voorkeur">
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm mb-3" onclick="addNieuweVoorkeur()">+ Voeg nog een toe</button>

                            <button type="submit" name="update" class="btn btn-warning w-100 mt-2">Opslaan</button>
                        </form>
                    </div>
                </div>

                <script>
                    function addNieuweVoorkeur() {
                        const wrapper = document.getElementById('nieuweVoorkeurenWrapper');
                        const div = document.createElement('div');
                        div.classList.add('mb-2');
                        div.innerHTML = `<input type="text" name="nieuwe_voorkeuren[]" class="form-control" placeholder="Nieuwe voorkeur">`;
                        wrapper.appendChild(div);
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
