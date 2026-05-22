<?php
// AJAX-endpoint voor scholen en klassen van bezoeken
require 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

csrf_validate();

header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['action']) && $_GET['action'] === 'schools') {
    // Geef scholen terug op basis van onderwijstype
    $onderwijsType = trim((string)($_GET['type'] ?? ''));
    if (!in_array($onderwijsType, ['Primair Onderwijs', 'Voortgezet Onderwijs', 'MBO'], true)) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare('SELECT school_id, schoolnaam, plaats FROM school WHERE type_onderwijs = ? ORDER BY schoolnaam ASC');
    $stmt->bind_param('s', $onderwijsType);
    $stmt->execute();
    $school_resultaat = $stmt->get_result();

    $antwoord = [];
    while ($schoolRij = $school_resultaat->fetch_assoc()) {
        $antwoord[] = [
            'school_id' => (int)$schoolRij['school_id'],
            'label' => $schoolRij['schoolnaam'] . ' (' . $schoolRij['plaats'] . ')',
        ];
    }
    $stmt->close();

    echo json_encode($antwoord);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'klassen') {
    // Geef klassen terug voor de gekozen scholen
    $schoolIdsRuw = trim((string)($_GET['school_ids'] ?? ''));
    $school_ids = [];

    if ($schoolIdsRuw !== '') {
        foreach (explode(',', $schoolIdsRuw) as $idRuw) {
            $school_id = (int)trim($idRuw);
            if ($school_id > 0) {
                $school_ids[] = $school_id;
            }
        }
    }

    $school_ids = array_values(array_unique($school_ids));
    if (empty($school_ids)) {
        echo json_encode([]);
        exit;
    }

    $schooljaar = trim((string)($_GET['schooljaar'] ?? ''));

    // Maak een prepared statement met een dynamisch aantal placeholders.
    $placeholders = implode(',', array_fill(0, count($school_ids), '?'));
    $sql_klassen = "
        SELECT k.klas_id, k.klasaanduiding, k.leerjaar, k.school_id, s.schoolnaam, k.schooljaar
        FROM klas k
        INNER JOIN school s ON s.school_id = k.school_id
        WHERE k.school_id IN ($placeholders)";

    $bind_waarden = $school_ids;
    $bind_types = str_repeat('i', count($school_ids));

    if ($schooljaar !== '') {
        $sql_klassen .= " AND k.schooljaar = ?";
        $bind_types .= 's';
        $bind_waarden[] = $schooljaar;
    }

    $sql_klassen .= "\n        ORDER BY s.schoolnaam ASC, k.leerjaar ASC, k.klasaanduiding ASC\n    ";

    $stmt = $conn->prepare($sql_klassen);
    if ($stmt) {
        // bind_param heeft referenties nodig, dus we bouwen de argumenten expliciet op.
        $bind_argumenten = [];
        $bind_argumenten[] = $bind_types;
        for ($i = 0; $i < count($bind_waarden); $i++) {
            $bind_argumenten[] = &$bind_waarden[$i];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_argumenten);
        $stmt->execute();
        $klas_resultaat = $stmt->get_result();
        $stmt->close();
    } else {
        $klas_resultaat = false;
    }

    $antwoord = [];
    if ($klas_resultaat) {
        while ($klasRij = $klas_resultaat->fetch_assoc()) {
            $label = $klasRij['schoolnaam'] . ' - ' . $klasRij['klasaanduiding'];
            if (!empty($klasRij['leerjaar'])) {
                $label .= ' (leerjaar ' . $klasRij['leerjaar'] . ')';
            }
            $antwoord[] = [
                'klas_id' => (int)$klasRij['klas_id'],
                'school_id' => (int)$klasRij['school_id'],
                'schoolnaam' => (string)$klasRij['schoolnaam'],
                'label' => $label,
            ];
        }
    }

    echo json_encode($antwoord);
    exit;
}

echo json_encode([]);
