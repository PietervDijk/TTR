<?php
require 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'schools') {
    $type = trim((string)($_GET['type'] ?? ''));
    if (!in_array($type, ['Primair Onderwijs', 'Voortgezet Onderwijs', 'MBO'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare('SELECT school_id, schoolnaam, plaats FROM school WHERE type_onderwijs = ? ORDER BY schoolnaam ASC');
    $stmt->bind_param('s', $type);
    $stmt->execute();
    $result = $stmt->get_result();

    $payload = [];
    while ($row = $result->fetch_assoc()) {
        $payload[] = [
            'school_id' => (int)$row['school_id'],
            'label' => $row['schoolnaam'] . ' (' . $row['plaats'] . ')',
        ];
    }
    $stmt->close();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

require 'includes/header.php';

?>

<div class="container py-5">
    <h1 class="mb-4">Bezoeken beheren</h1>
    <p>Op deze pagina kun je nieuwe bezoeken toevoegen en bestaande bezoeken beheren.</p>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-plus-circle"></i> Nieuw bezoek toevoegen
        </div>
        <div class="card-body">
            <form method="post" action="bezoeken_process.php" id="bezoekForm" novalidate>
                <div class="mb-3">
                    <label for="bezoek_naam" class="form-label">Bezoeknaam</label>
                    <input type="text" class="form-control" id="bezoek_naam" name="bezoek_naam" required>
                </div>

                <div class="mb-3">
                    <label for="onderwijs_type" class="form-label">Type onderwijs</label>
                    <select class="form-select" id="onderwijs_type" name="onderwijs_type" required>
                        <option value="" disabled selected>Selecteer type onderwijs</option>
                        <option value="Primair Onderwijs">Primair onderwijs (PO)</option>
                        <option value="Voortgezet Onderwijs">Voortgezet onderwijs (VO)</option>
                        <option value="MBO">Middelbaar beroepsonderwijs (MBO)</option>
                    </select>
                </div>
                
                
                <div class="mb-3">
                    <label class="form-label">Scholen</label>
                    
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <input type="text" id="school_filter" class="form-control" placeholder="Zoek school..." style="max-width: 260px;">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="school_select_all">Alles selecteren</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="school_clear">Leegmaken</button>
                        <span class="badge bg-light text-dark border align-self-center" id="school_count_badge">0 geselecteerd</span>
                    </div>
                    
                    <div id="school_list" class="border rounded p-2" style="max-height: 260px; overflow: auto; background: #fff;"></div>
                    <input type="text" id="school_required_marker" class="d-none" required>
                    <div class="form-text">Kies een of meerdere scholen met de checkboxen.</div>
                </div>
                
                <div class="mb-3">
                    <label for="klas" class="form-label">Klas</label>
                    <select class="form-select" id="klas" name="klas">
                        <option value="">Selecteer klas</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="bezoek_datum" class="form-label">Datum en tijd</label>
                    <input type="datetime-local" class="form-control" id="bezoek_datum" name="bezoek_datum" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Bezoek toevoegen
                </button>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>