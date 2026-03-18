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

if (isset($_GET['action']) && $_GET['action'] === 'klassen') {
    $schoolIdsRaw = trim((string)($_GET['school_ids'] ?? ''));
    $ids = [];

    if ($schoolIdsRaw !== '') {
        foreach (explode(',', $schoolIdsRaw) as $idRaw) {
            $id = (int)trim($idRaw);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
    }

    $ids = array_values(array_unique($ids));
    if (empty($ids)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit;
    }

    $inClause = implode(',', $ids);
    $sql = "
        SELECT k.klas_id, k.klasaanduiding, k.leerjaar, k.school_id, s.schoolnaam
        FROM klas k
        INNER JOIN school s ON s.school_id = k.school_id
        WHERE k.school_id IN ($inClause)
        ORDER BY s.schoolnaam ASC, k.leerjaar ASC, k.klasaanduiding ASC
    ";
    $result = $conn->query($sql);

    $payload = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $label = $row['schoolnaam'] . ' - ' . $row['klasaanduiding'];
            if (!empty($row['leerjaar'])) {
                $label .= ' (leerjaar ' . $row['leerjaar'] . ')';
            }
            $payload[] = [
                'klas_id' => (int)$row['klas_id'],
                'school_id' => (int)$row['school_id'],
                'schoolnaam' => (string)$row['schoolnaam'],
                'label' => $label,
            ];
        }
    }

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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('bezoekForm');
        const onderwijsType = document.getElementById('onderwijs_type');
        const schoolFilter = document.getElementById('school_filter');
        const schoolList = document.getElementById('school_list');
        const schoolSelectAllBtn = document.getElementById('school_select_all');
        const schoolClearBtn = document.getElementById('school_clear');
        const schoolCountBadge = document.getElementById('school_count_badge');
        const schoolRequiredMarker = document.getElementById('school_required_marker');

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function selectedSchoolValues() {
            return Array.from(schoolList.querySelectorAll('.js-school-checkbox:checked')).map(function(input) {
                return Number(input.value);
            }).filter(function(v) {
                return Number.isInteger(v) && v > 0;
            });
        }

        function updateSchoolCount() {
            const count = selectedSchoolValues().length;
            schoolCountBadge.textContent = count + ' geselecteerd';
            schoolRequiredMarker.value = count > 0 ? 'ok' : '';
        }

        function renderSchools(items, selectedIds) {
            schoolList.innerHTML = '';

            if (!items.length) {
                schoolList.innerHTML = '<div class="text-muted small p-1">Geen scholen gevonden voor dit onderwijstype.</div>';
                updateSchoolCount();
                return;
            }

            items.forEach(function(item) {
                const id = Number(item.school_id);
                const checked = selectedIds.includes(id) ? 'checked' : '';

                const row = document.createElement('div');
                row.className = 'form-check mb-1 js-school-row';
                row.dataset.label = String(item.label || '').toLowerCase();
                row.innerHTML = `
                    <input class="form-check-input js-school-checkbox" type="checkbox" name="school_ids[]" id="school_${id}" value="${id}" ${checked}>
                    <label class="form-check-label" for="school_${id}">${escapeHtml(item.label)}</label>
                `;
                schoolList.appendChild(row);
            });

            applySchoolFilter();
            updateSchoolCount();
        }

        function applySchoolFilter() {
            const needle = (schoolFilter.value || '').trim().toLowerCase();
            Array.from(schoolList.querySelectorAll('.js-school-row')).forEach(function(row) {
                const label = row.dataset.label || '';
                const visible = needle === '' || label.includes(needle);
                row.classList.toggle('d-none', !visible);
            });
        }

        function selectAllVisibleSchools() {
            Array.from(schoolList.querySelectorAll('.js-school-row')).forEach(function(row) {
                if (row.classList.contains('d-none')) return;
                const input = row.querySelector('.js-school-checkbox');
                if (input) {
                    input.checked = true;
                }
            });
            updateSchoolCount();
        }

        function clearAllSchools() {
            Array.from(schoolList.querySelectorAll('.js-school-checkbox')).forEach(function(input) {
                input.checked = false;
            });
            updateSchoolCount();
        }

        function loadSchools() {
            const type = (onderwijsType.value || '').trim();
            const previous = selectedSchoolValues();

            if (!type) {
                schoolList.innerHTML = '<div class="text-muted small p-1">Kies eerst een onderwijstype.</div>';
                updateSchoolCount();
                return;
            }

            fetch('bezoeken.php?action=schools&type=' + encodeURIComponent(type))
                .then(function(response) {
                    return response.json();
                })
                .then(function(items) {
                    renderSchools(items, previous);
                })
                .catch(function() {
                    schoolList.innerHTML = '<div class="text-danger small p-1">Kon scholen niet laden.</div>';
                    updateSchoolCount();
                });
        }

        onderwijsType.addEventListener('change', loadSchools);

        schoolFilter.addEventListener('input', applySchoolFilter);

        schoolSelectAllBtn.addEventListener('click', selectAllVisibleSchools);

        schoolClearBtn.addEventListener('click', clearAllSchools);

        schoolList.addEventListener('change', function(event) {
            if (!event.target.classList.contains('js-school-checkbox')) return;
            updateSchoolCount();
        });

        form.addEventListener('submit', function() {
            updateSchoolCount();
        });

        loadSchools();
    });
</script>

<?php require 'includes/footer.php'; ?>