// Initialiseer form-logica na DOM-load
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('bezoekForm');
    if (!form) {
        return;
    }

    const voorafGeselecteerdeSchoolIds = JSON.parse(form.dataset.voorafScholen || '[]');
    const voorafGeselecteerdeKlasIds = JSON.parse(form.dataset.voorafKlassen || '[]');

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

    const poDatumsWrapper = document.getElementById('po_datums_wrapper');
    const voMboDatumsWrapper = document.getElementById('vo_mbo_datums_wrapper');
    const poVoorkeurDagdeelHint = document.getElementById('po_voorkeur_dagdeel_hint');
    const bezoekDag1 = document.getElementById('bezoek_dag1');
    const bezoekDag2 = document.getElementById('bezoek_dag2');
    const bezoekWeekStart = document.getElementById('bezoek_week_start');
    const bezoekWeekEind = document.getElementById('bezoek_week_eind');

    // Update zichtbaarheid PO-dagkeuze fields op basis van geselecteerde optie
    function updatePoRowDagdeelState(row, isPO) {
        const dagdeelSelect = row.querySelector('.js-po-dagdeel-select');
        const baseMaxGroup = row.querySelector('.js-base-max-group');
        const baseMaxInput = row.querySelector('.js-base-max-input');
        const splitGroup = row.querySelector('.js-po-split-max-group');
        const splitInputs = row.querySelectorAll('.js-po-split-max-input');
        if (!dagdeelSelect || !splitGroup) {
            return;
        }

        const showSplit = isPO && dagdeelSelect.value === 'beide';
        if (baseMaxGroup) {
            baseMaxGroup.classList.toggle('d-none', showSplit);
        }

        if (baseMaxInput) {
            if (!isPO) {
                baseMaxInput.placeholder = 'Max leerlingen';
            } else if (dagdeelSelect.value === 'dag1') {
                baseMaxInput.placeholder = 'Limiet dag 1';
            } else if (dagdeelSelect.value === 'dag2') {
                baseMaxInput.placeholder = 'Limiet dag 2';
            } else {
                baseMaxInput.placeholder = 'Max leerlingen';
            }
        }

        splitGroup.classList.toggle('d-none', !showSplit);
        splitInputs.forEach(function(input) {
            input.disabled = !showSplit;
        });
    }

    // Zet PO-dagdeel velden zichtbaar/onzichtbaar naargelang onderwijstype
    function updatePoDagdeelVisibility() {
        const isPO = onderwijsType.value === 'Primair Onderwijs';

        document.querySelectorAll('.js-po-dagdeel-group').forEach(function(group) {
            group.classList.toggle('d-none', !isPO);
        });

        document.querySelectorAll('.js-po-dagdeel-select').forEach(function(select) {
            select.disabled = !isPO;
            if (!isPO) {
                select.value = 'beide';
            }
        });

        document.querySelectorAll('.voorkeur-row-bezoek').forEach(function(row) {
            updatePoRowDagdeelState(row, isPO);
        });

        if (poVoorkeurDagdeelHint) {
            poVoorkeurDagdeelHint.classList.toggle('d-none', !isPO);
        }
    }

    // Escape HTML-speciale karakters
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Haal geselecteerde checkbox-waardes op als array van nummers
    function getCheckedValues(name) {
        return Array.from(document.querySelectorAll('input[name="' + name + '[]"]:checked')).map((el) => Number(el.value));
    }

    // Update aantal-badge voor aangekruiste items
    function updateCountBadge(name, badgeEl, singular) {
        const count = getCheckedValues(name).length;
        badgeEl.textContent = count + ' geselecteerd';
        if (count === 1) {
            badgeEl.textContent = count + ' ' + singular + ' geselecteerd';
        }
    }

    // Zet required-marker (hidden input) op 'ok' of lege string
    function setRequiredMarker(markerEl, hasSelection) {
        markerEl.value = hasSelection ? 'ok' : '';
    }

    // Render schoollijst met checkboxes
    function renderSchoolList(items) {
        if (!items.length) {
            schoolList.innerHTML = '<div class="text-muted small">Geen scholen gevonden voor dit onderwijstype.</div>';
            setRequiredMarker(schoolRequiredMarker, false);
            updateCountBadge('school_ids', schoolCountBadge, 'school');
            return;
        }

        const html = items.map((item) => {
            return `
                <label class="d-flex align-items-center gap-2 p-1 border-bottom">
                    <input type="checkbox" name="school_ids[]" value="${item.school_id}" class="form-check-input js-school-checkbox">
                    <span>${escapeHtml(item.label)}</span>
                </label>
            `;
        }).join('');
        schoolList.innerHTML = html;
        if (voorafGeselecteerdeSchoolIds.length > 0) {
            document.querySelectorAll('.js-school-checkbox').forEach(function(cb) {
                if (voorafGeselecteerdeSchoolIds.includes(Number(cb.value))) {
                    cb.checked = true;
                }
            });
        }
        applySchoolFilter();
        updateSchoolState();
    }

    // Render klassenlijst met checkboxes (gegroepeerd per school)
    function renderKlasList(items) {
        if (!items.length) {
            klasList.innerHTML = '<div class="text-muted small">Geen klassen gevonden voor de geselecteerde scholen.</div>';
            setRequiredMarker(klasRequiredMarker, false);
            updateCountBadge('klas_ids', klasCountBadge, 'klas');
            validateKlasCoverage();
            return;
        }

        const html = items.map((item) => {
            return `
                <label class="d-flex align-items-center gap-2 p-1 border-bottom js-klas-row" data-school-id="${item.school_id}">
                    <input type="checkbox" name="klas_ids[]" value="${item.klas_id}" class="form-check-input js-klas-checkbox" data-school-id="${item.school_id}">
                    <span>${escapeHtml(item.label)}</span>
                </label>
            `;
        }).join('');
        klasList.innerHTML = html;
        if (voorafGeselecteerdeKlasIds.length > 0) {
            document.querySelectorAll('.js-klas-checkbox').forEach(function(cb) {
                if (voorafGeselecteerdeKlasIds.includes(Number(cb.value))) {
                    cb.checked = true;
                }
            });
        }
        applyKlasFilter();
        updateKlasState();
    }

    // Haal IDs van geselecteerde scholen op
    function getSelectedSchoolIds() {
        return getCheckedValues('school_ids');
    }

    // Haal alle klas-checkboxes op
    function getSelectedKlasCheckboxes() {
        return Array.from(document.querySelectorAll('.js-klas-checkbox'));
    }

    // Update school-selectie state: badge, required-marker en laad klassen
    function updateSchoolState() {
        const selectedSchoolIds = getSelectedSchoolIds();
        setRequiredMarker(schoolRequiredMarker, selectedSchoolIds.length > 0);
        updateCountBadge('school_ids', schoolCountBadge, 'school');
        fetchKlassenForSchools(selectedSchoolIds);
    }

    // Update klas-selectie state: badge, required-marker en valideer dekking
    function updateKlasState() {
        const checkedKlassen = getCheckedValues('klas_ids');
        setRequiredMarker(klasRequiredMarker, checkedKlassen.length > 0);
        updateCountBadge('klas_ids', klasCountBadge, 'klas');
        validateKlasCoverage();
    }

    // Valideer dat alle gekozen scholen min 1 klas hebben
    function validateKlasCoverage() {
        const selectedSchoolIds = getSelectedSchoolIds();
        const checkedKlas = getSelectedKlasCheckboxes().filter((cb) => cb.checked);
        const covered = new Set(checkedKlas.map((cb) => Number(cb.dataset.schoolId)));

        const missing = selectedSchoolIds.filter((id) => !covered.has(id));

        if (selectedSchoolIds.length > 0 && missing.length > 0) {
            klasValidationMessage.textContent = 'Selecteer minimaal 1 klas per gekozen school.';
            klasValidationMessage.classList.remove('d-none');
            klasRequiredMarker.value = '';
        } else {
            klasValidationMessage.textContent = '';
            klasValidationMessage.classList.add('d-none');
            setRequiredMarker(klasRequiredMarker, checkedKlas.length > 0);
        }
    }

    // Filter schoollijst op invoerwaarde
    function applySchoolFilter() {
        const q = (schoolFilter.value || '').trim().toLowerCase();
        const rows = Array.from(schoolList.querySelectorAll('label'));
        rows.forEach((row) => {
            const txt = row.textContent.toLowerCase();
            row.style.display = q === '' || txt.includes(q) ? '' : 'none';
        });
    }

    // Filter klaslijst op invoerwaarde
    function applyKlasFilter() {
        const q = (klasFilter.value || '').trim().toLowerCase();
        const rows = Array.from(klasList.querySelectorAll('.js-klas-row'));
        rows.forEach((row) => {
            const txt = row.textContent.toLowerCase();
            row.style.display = q === '' || txt.includes(q) ? '' : 'none';
        });
    }

    // Fetch scholen via AJAX op onderwijstype
    async function fetchSchoolsByType(type) {
        const response = await fetch('bezoeken.php?action=schools&type=' + encodeURIComponent(type), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) {
            throw new Error('Kon scholen niet laden');
        }
        return response.json();
    }

    // Fetch klassen via AJAX op geselecteerde schoolID's
    async function fetchKlassenForSchools(schoolIds) {
        if (!schoolIds.length) {
            renderKlasList([]);
            return;
        }
        const response = await fetch('bezoeken.php?action=klassen&school_ids=' + encodeURIComponent(schoolIds.join(',')), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) {
            throw new Error('Kon klassen niet laden');
        }
        const payload = await response.json();
        renderKlasList(payload);
    }

    // Wis school- en klaslijsten
    function clearSchoolAndKlasLists() {
        schoolList.innerHTML = '';
        klasList.innerHTML = '';
        setRequiredMarker(schoolRequiredMarker, false);
        setRequiredMarker(klasRequiredMarker, false);
        updateCountBadge('school_ids', schoolCountBadge, 'school');
        updateCountBadge('klas_ids', klasCountBadge, 'klas');
        validateKlasCoverage();
    }

    // Zet datumvelden zichtbaar/onzichtbaar naargelang onderwijstype
    function toggleDatumVeldenByOnderwijsType() {
        const type = onderwijsType.value;
        const isPO = type === 'Primair Onderwijs';
        const isVoMbo = type === 'Voortgezet Onderwijs' || type === 'MBO';

        poDatumsWrapper.classList.toggle('d-none', !isPO);
        voMboDatumsWrapper.classList.toggle('d-none', !isVoMbo);

        bezoekDag1.required = isPO;
        bezoekDag2.required = isPO;
        bezoekWeekStart.required = isVoMbo;
        bezoekWeekEind.required = isVoMbo;

        if (!isPO) {
            bezoekDag1.value = '';
            bezoekDag2.value = '';
        }
        if (!isVoMbo) {
            bezoekWeekStart.value = '';
            bezoekWeekEind.value = '';
        }

        updatePoDagdeelVisibility();
    }

    // Event: onderwijstype gewijzigd -> laad scholen en zet velden
    onderwijsType.addEventListener('change', async function() {
        const type = onderwijsType.value;
        clearSchoolAndKlasLists();
        toggleDatumVeldenByOnderwijsType();
        if (!type) {
            return;
        }
        try {
            const schools = await fetchSchoolsByType(type);
            renderSchoolList(schools);
        } catch (err) {
            schoolList.innerHTML = '<div class="text-danger small">Fout bij laden van scholen.</div>';
        }
    });

    // Event: school filter invoer
    schoolFilter.addEventListener('input', applySchoolFilter);
    // Event: klas filter invoer
    klasFilter.addEventListener('input', applyKlasFilter);

    // Event: alles selecteren button voor scholen
    schoolSelectAllBtn.addEventListener('click', function() {
        document.querySelectorAll('.js-school-checkbox').forEach((cb) => { cb.checked = true; });
        updateSchoolState();
    });

    // Event: alles wissen button voor scholen
    schoolClearBtn.addEventListener('click', function() {
        document.querySelectorAll('.js-school-checkbox').forEach((cb) => { cb.checked = false; });
        updateSchoolState();
    });

    // Event: alles selecteren button voor klassen
    klasSelectAllBtn.addEventListener('click', function() {
        document.querySelectorAll('.js-klas-checkbox').forEach((cb) => { cb.checked = true; });
        updateKlasState();
    });

    // Event: alles wissen button voor klassen
    klasClearBtn.addEventListener('click', function() {
        document.querySelectorAll('.js-klas-checkbox').forEach((cb) => { cb.checked = false; });
        updateKlasState();
    });

    // Event: school checkbox veranderd
    schoolList.addEventListener('change', function(event) {
        if (event.target && event.target.classList.contains('js-school-checkbox')) {
            updateSchoolState();
        }
    });

    // Event: klas checkbox veranderd
    klasList.addEventListener('change', function(event) {
        if (event.target && event.target.classList.contains('js-klas-checkbox')) {
            updateKlasState();
        }
    });

    // Event: delete links met bevestiging-dialog
    document.querySelectorAll('.js-confirm').forEach(function(link) {
        link.addEventListener('click', function(event) {
            const message = link.dataset.confirm || 'Weet je het zeker?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // Event: voorkeur toevoegen button (maak nieuwe voorkeur-rij)
    const voorkeurenWrapper = document.getElementById('voorkeurenWrapperBezoek');
    const addVoorkeurBtn = document.getElementById('js-add-bezoek-voorkeur');
    addVoorkeurBtn.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'mb-2 d-flex gap-2 flex-wrap voorkeur-row-bezoek';
        row.innerHTML = `
            <input type="text" name="voorkeur_naam[]" class="form-control" placeholder="Bijv: Electrotechniek">
            <div class="js-base-max-group">
                <input type="number" name="voorkeur_max[]" class="form-control js-base-max-input" placeholder="Max leerlingen" min="1">
            </div>
            <div class="js-po-dagdeel-group d-none">
                <select name="voorkeur_dag_deel[]" class="form-select js-po-dagdeel-select" disabled>
                    <option value="beide" selected>Beide dagen</option>
                    <option value="dag1">Alleen dag 1</option>
                    <option value="dag2">Alleen dag 2</option>
                </select>
            </div>
            <div class="js-po-split-max-group d-flex gap-2 d-none">
                <input type="number" name="voorkeur_max_dag1[]" class="form-control js-po-split-max-input" placeholder="Limiet dag 1" min="1" disabled>
                <input type="number" name="voorkeur_max_dag2[]" class="form-control js-po-split-max-input" placeholder="Limiet dag 2" min="1" disabled>
            </div>
        `;
        voorkeurenWrapper.appendChild(row);
        updatePoDagdeelVisibility();
    });

    // Event: PO-dagdeel select gewijzigd
    voorkeurenWrapper.addEventListener('change', function(event) {
        if (event.target && event.target.classList.contains('js-po-dagdeel-select')) {
            const row = event.target.closest('.voorkeur-row-bezoek');
            if (row) {
                updatePoRowDagdeelState(row, onderwijsType.value === 'Primair Onderwijs');
            }
        }
    });

    // Event: formulier submit
    form.addEventListener('submit', function() {
        validateKlasCoverage();
    });

    // Init state
    updateCountBadge('school_ids', schoolCountBadge, 'school');
    updateCountBadge('klas_ids', klasCountBadge, 'klas');
    toggleDatumVeldenByOnderwijsType();

    // Laad scholen als onderwijstype al ingevuld is (bij bewerking)
    if (onderwijsType.value) {
        onderwijsType.dispatchEvent(new Event('change'));
    }
});
