<?php
require 'includes/header.php';

// Controleer of er een school_id is meegegeven
if (!isset($_GET['school_id'])) {
    header("Location: scholen.php");
    exit;
}

$school_id = (int)$_GET['school_id'];

// Haal schoolnaam op voor de titel
$stmt = $conn->prepare("SELECT schoolnaam FROM school WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$school = $result->fetch_assoc();
$stmt->close();

if (!$school) {
    echo "<div class='alert alert-danger m-5'>School niet gevonden.</div>";
    require 'includes/footer.php';
    exit;
}

// CREATE
if (isset($_POST['add'])) {
    $klasaanduiding = $_POST['klasaanduiding'];
    $leerjaar = $_POST['leerjaar'];
    $schooljaar = $_POST['schooljaar'];
    $pincode = !empty($_POST['pincode']) ? (int)$_POST['pincode'] : null;

    $stmt = $conn->prepare("INSERT INTO klas (school_id, klasaanduiding, leerjaar, schooljaar, pincode) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $school_id, $klasaanduiding, $leerjaar, $schooljaar, $pincode);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();

    header("Location: klassen.php?school_id=$school_id&highlight=$new_id");
    exit;
}

// UPDATE
if (isset($_POST['update'])) {
    $klas_id = (int)$_POST['klas_id'];
    $klasaanduiding = $_POST['klasaanduiding'];
    $leerjaar = $_POST['leerjaar'];
    $schooljaar = $_POST['schooljaar'];
    $pincode = !empty($_POST['pincode']) ? (int)$_POST['pincode'] : null;

    $stmt = $conn->prepare("UPDATE klas SET klasaanduiding=?, leerjaar=?, schooljaar=?, pincode=? WHERE klas_id=? AND school_id=?");
    $stmt->bind_param("sssiii", $klasaanduiding, $leerjaar, $schooljaar, $pincode, $klas_id, $school_id);
    $stmt->execute();
    $stmt->close();

    header("Location: klassen.php?school_id=$school_id&highlight=$klas_id");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $klas_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM klas WHERE klas_id=? AND school_id=?");
    $stmt->bind_param("ii", $klas_id, $school_id);
    $stmt->execute();
    $stmt->close();
    header("Location: klassen.php?school_id=$school_id");
    exit;
}

// READ
$stmt = $conn->prepare("SELECT * FROM klas WHERE school_id=? ORDER BY leerjaar, klasaanduiding");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$klassen = $stmt->get_result();
$stmt->close();

// Highlight logic
$highlight_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : null;
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col d-flex align-items-center justify-content-between">
            <h2 class="fw-bold text-primary mb-0">
                Klassen – <?= htmlspecialchars($school['schoolnaam']) ?>
            </h2>
            <a href="scholen.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Terug naar scholen
            </a>
        </div>
    </div>

    

<?php require 'includes/footer.php'; ?>