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
                    <label class="form-label">Klassen</label>

                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <input type="text" id="klas_filter" class="form-control" placeholder="Zoek klas..." style="max-width: 260px;">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="klas_select_all">Alles selecteren</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="klas_clear">Leegmaken</button>
                        <span class="badge bg-light text-dark border align-self-center" id="klas_count_badge">0 geselecteerd</span>
                    </div>

                    <div id="klas_list" class="border rounded p-2" style="max-height: 260px; overflow: auto; background: #fff;"></div>
                    <input type="text" id="klas_required_marker" class="d-none" required>
                    <div id="klas_validation_message" class="small text-danger mt-2 d-none"></div>
                    <div class="form-text">Per gekozen school moet je minimaal 1 klas selecteren.</div>
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
        const klasFilter = document.getElementById('klas_filter');
        const klasList = document.getElementById('klas_list');
        const klasSelectAllBtn = document.getElementById('klas_select_all');
        const klasClearBtn = document.getElementById('klas_clear');
        const klasCountBadge = document.getElementById('klas_count_badge');
        const klasRequiredMarker = document.getElementById('klas_required_marker');
        const klasValidationMessage = document.getElementById('klas_validation_message');

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

        function selectedClassRows() {
            return Array.from(klasList.querySelectorAll('.js-klas-checkbox:checked'));
        }

        function updateClassCount() {
            const count = selectedClassRows().length;
            klasCountBadge.textContent = count + ' geselecteerd';
            klasRequiredMarker.value = count > 0 ? 'ok' : '';
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

        function renderKlassen(items, selectedClassIds) {
            klasList.innerHTML = '';

            if (!items.length) {
                klasList.innerHTML = '<div class="text-muted small p-1">Geen klassen gevonden voor de gekozen scholen.</div>';
                updateClassCount();
                return;
            }

            items.forEach(function(item) {
                const id = Number(item.klas_id);
                const schoolId = Number(item.school_id);
                const checked = selectedClassIds.includes(id) ? 'checked' : '';

                const row = document.createElement('div');
                row.className = 'form-check mb-1 js-klas-row';
                row.dataset.label = String(item.label || '').toLowerCase();
                row.dataset.schoolId = String(schoolId);
                row.innerHTML = `
                    <input class="form-check-input js-klas-checkbox" type="checkbox" name="klas_ids[]" id="klas_${id}" value="${id}" data-school-id="${schoolId}" ${checked}>
                    <label class="form-check-label" for="klas_${id}">${escapeHtml(item.label)}</label>
                `;
                klasList.appendChild(row);
            });

            applyClassFilter();
            updateClassCount();
        }

        function applyClassFilter() {
            const needle = (klasFilter.value || '').trim().toLowerCase();
            Array.from(klasList.querySelectorAll('.js-klas-row')).forEach(function(row) {
                const label = row.dataset.label || '';
                const visible = needle === '' || label.includes(needle);
                row.classList.toggle('d-none', !visible);
            });
        }

        function selectAllVisibleClasses() {
            Array.from(klasList.querySelectorAll('.js-klas-row')).forEach(function(row) {
                if (row.classList.contains('d-none')) return;
                const input = row.querySelector('.js-klas-checkbox');
                if (input) {
                    input.checked = true;
                }
            });
            updateClassCount();
            validateSchoolCoverage();
        }

        function clearAllClasses() {
            Array.from(klasList.querySelectorAll('.js-klas-checkbox')).forEach(function(input) {
                input.checked = false;
            });
            updateClassCount();
            validateSchoolCoverage();
        }

        function loadKlassen() {
            const schoolIds = selectedSchoolValues();
            const previousClassIds = selectedClassRows().map(function(input) {
                return Number(input.value);
            });

            if (!schoolIds.length) {
                klasList.innerHTML = '<div class="text-muted small p-1">Kies eerst minimaal 1 school.</div>';
                updateClassCount();
                validateSchoolCoverage();
                return;
            }

            fetch('bezoeken.php?action=klassen&school_ids=' + encodeURIComponent(schoolIds.join(',')))
                .then(function(response) {
                    return response.json();
                })
                .then(function(items) {
                    renderKlassen(items, previousClassIds);
                    validateSchoolCoverage();
                })
                .catch(function() {
                    klasList.innerHTML = '<div class="text-danger small p-1">Kon klassen niet laden.</div>';
                    updateClassCount();
                    validateSchoolCoverage();
                });
        }

        function validateSchoolCoverage() {
            const selectedSchools = selectedSchoolValues();
            const selectedClassSchoolIds = new Set(selectedClassRows().map(function(input) {
                return Number(input.dataset.schoolId);
            }));

            const missingSchoolIds = selectedSchools.filter(function(schoolId) {
                return !selectedClassSchoolIds.has(schoolId);
            });

            if (missingSchoolIds.length > 0) {
                klasRequiredMarker.value = '';
                if (klasValidationMessage) {
                    klasValidationMessage.textContent = 'Selecteer minimaal 1 klas voor iedere gekozen school.';
                    klasValidationMessage.classList.remove('d-none');
                }
                return false;
            }

            if (selectedSchools.length > 0 && selectedClassRows().length > 0) {
                klasRequiredMarker.value = 'ok';
            }
            if (klasValidationMessage) {
                klasValidationMessage.textContent = '';
                klasValidationMessage.classList.add('d-none');
            }
            return true;
        }

        function loadSchools() {
            const type = (onderwijsType.value || '').trim();
            const previous = selectedSchoolValues();

            if (!type) {
                schoolList.innerHTML = '<div class="text-muted small p-1">Kies eerst een onderwijstype.</div>';
                updateSchoolCount();
                loadKlassen();
                return;
            }

            fetch('bezoeken.php?action=schools&type=' + encodeURIComponent(type))
                .then(function(response) {
                    return response.json();
                })
                .then(function(items) {
                    renderSchools(items, previous);
                    loadKlassen();
                })
                .catch(function() {
                    schoolList.innerHTML = '<div class="text-danger small p-1">Kon scholen niet laden.</div>';
                    updateSchoolCount();
                    loadKlassen();
                });
        }

        onderwijsType.addEventListener('change', loadSchools);

        schoolFilter.addEventListener('input', applySchoolFilter);

        schoolSelectAllBtn.addEventListener('click', function() {
            selectAllVisibleSchools();
            loadKlassen();
        });

        schoolClearBtn.addEventListener('click', function() {
            clearAllSchools();
            loadKlassen();
        });

        schoolList.addEventListener('change', function(event) {
            if (!event.target.classList.contains('js-school-checkbox')) return;
            updateSchoolCount();
            loadKlassen();
        });

        klasFilter.addEventListener('input', applyClassFilter);

        klasSelectAllBtn.addEventListener('click', selectAllVisibleClasses);

        klasClearBtn.addEventListener('click', clearAllClasses);

        klasList.addEventListener('change', function(event) {
            if (!event.target.classList.contains('js-klas-checkbox')) return;
            updateClassCount();
            validateSchoolCoverage();
        });

        form.addEventListener('submit', function(event) {
            updateSchoolCount();
            updateClassCount();
            if (!validateSchoolCoverage()) {
                event.preventDefault();
            }
        });

        loadSchools();
    });
</script>

<?php require 'includes/footer.php'; ?>