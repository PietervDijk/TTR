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
    const voorkeurValidationMessage = document.getElementById('voorkeur_validation_message');
    const planningPrerequisiteMessage = document.getElementById('planning_prerequisite_message');
    const bezoekDag1 = document.getElementById('bezoek_dag1');
    const bezoekDag2 = document.getElementById('bezoek_dag2');
    const bezoekWeekStart = document.getElementById('bezoek_week_start');
    const bezoekWeekEind = document.getElementById('bezoek_week_eind');
    const bezoekSchooljaar = document.getElementById('bezoek_schooljaar');
    const tabDoorKnoppen = document.querySelectorAll('.js-tab-door');
    const bezoekTabs = document.querySelectorAll('.js-bezoek-tab');
    const tabVolgorde = ['#tab-basisgegevens', '#tab-selectie', '#tab-planning', '#tab-voorkeuren'];

    // Zet de gewenste tab actief via Bootstrap
    function gaNaarTab(tabSelector) {
        if (!tabSelector || !window.bootstrap || !window.bootstrap.Tab) {
            return;
        }

        const tabKnop = document.querySelector('[data-bs-target="' + tabSelector + '"]');
        if (!tabKnop) {
            return;
        }

        const tab = window.bootstrap.Tab.getOrCreateInstance(tabKnop);
        tab.show();
        tabKnop.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Bepaal of een stap al open mag
    function magTabOpenen(tabSelector) {
        if (tabSelector === '#tab-basisgegevens') {
            return true;
        }

        const basisGeldig = valideerTab('#tab-basisgegevens').geldig;
        if (!basisGeldig) {
            return false;
        }

        if (tabSelector === '#tab-selectie') {
            return valideerSelectieTab(false).geldig;
        }

        const selectieGeldig = valideerSelectieTab(false).geldig;
        if (!selectieGeldig) {
            return false;
        }

        if (tabSelector === '#tab-planning') {
            return true;
        }

        const planningGeldig = valideerTab('#tab-planning').geldig;
        if (!planningGeldig) {
            return false;
        }

        if (tabSelector === '#tab-voorkeuren') {
            return true;
        }

        return true;
    }

    // Toon de eerste benodigde stap als een tab nog dicht zit
    function gaNaarEersteBenodigdeStap(tabSelector) {
        if (tabSelector === '#tab-selectie' && !valideerTab('#tab-basisgegevens').geldig) {
            gaNaarTab('#tab-basisgegevens');
            return;
        }

        if (tabSelector === '#tab-planning') {
            if (!valideerTab('#tab-basisgegevens').geldig) {
                gaNaarTab('#tab-basisgegevens');
                return;
            }
            if (!valideerTab('#tab-selectie').geldig) {
                gaNaarTab('#tab-selectie');
                return;
            }
        }

        if (tabSelector === '#tab-voorkeuren') {
            if (!valideerTab('#tab-basisgegevens').geldig) {
                gaNaarTab('#tab-basisgegevens');
                return;
            }
            if (!valideerTab('#tab-selectie').geldig) {
                gaNaarTab('#tab-selectie');
                return;
            }
            if (!valideerTab('#tab-planning').geldig) {
                gaNaarTab('#tab-planning');
                return;
            }
        }
    }

    // Zet tab-states visueel op basis van validatie
    function updateTabBeschikbaarheid() {
        bezoekTabs.forEach(function(tabKnop) {
            const tabSelector = tabKnop.dataset.bsTarget;
            const toegestaan = magTabOpenen(tabSelector);
            tabKnop.classList.toggle('is-locked', !toegestaan);
            tabKnop.setAttribute('aria-disabled', toegestaan ? 'false' : 'true');
        });
    }

    // Controleer selectie zonder de lijsten opnieuw te laden
    function valideerSelectieTab(werkLijstenBij = true) {
        if (werkLijstenBij) {
            updateSchoolState();
            updateKlasState();
        } else {
            validateKlasCoverage();
            updatePlanningPrerquisites();
        }

        const eersteFoutveld = document.getElementById('school_required_marker') && !schoolRequiredMarker.value
            ? schoolFilter
            : (document.getElementById('klas_required_marker') && !klasRequiredMarker.value ? klasFilter : null);

        const heeftSchoolFout = !schoolRequiredMarker.value;
        const heeftKlasFout = !klasRequiredMarker.value;
        const heeftDekkingFout = klasValidationMessage && !klasValidationMessage.classList.contains('d-none');

        return {
            geldig: !(heeftSchoolFout || heeftKlasFout || heeftDekkingFout),
            eersteFoutveld: eersteFoutveld
        };
    }

    // Lees de actieve tab uit het formulier
    function haalActieveTabOp() {
        const actievePane = document.querySelector('#bezoekForm .tab-pane.active.show');
        return actievePane ? ('#' + actievePane.id) : tabVolgorde[0];
    }

    // Haal het eerste zichtbare invoerveld met een fout op
    function haalEersteOngeldigeInvoerOp(tabPane) {
        const invoervelden = Array.from(tabPane.querySelectorAll('input, select, textarea')).filter(function(invoer) {
            return !invoer.disabled && invoer.offsetParent !== null;
        });

        return invoervelden.find(function(invoer) {
            return typeof invoer.checkValidity === 'function' && !invoer.checkValidity();
        }) || null;
    }

    // Geef het foutvak voor voorkeuren netjes weer of leeg het
    function zetVoorkeurenFoutmelding(tekst) {
        if (!voorkeurValidationMessage) {
            return;
        }

        voorkeurValidationMessage.textContent = tekst || '';
        voorkeurValidationMessage.classList.toggle('d-none', !tekst);
    }

    // Controleer of de planning al zinvol is om te tonen
    function basisVoorPlanningIsKlaar() {
        return Boolean(onderwijsType && onderwijsType.value) && getSelectedSchoolIds().length > 0 && getSelectedKlasCheckboxes().some(function(cb) {
            return cb.checked;
        });
    }

    // Toon of verberg de melding in de planning-tab
    function updatePlanningPrerquisites() {
        if (!planningPrerequisiteMessage) {
            return;
        }

        planningPrerequisiteMessage.classList.toggle('d-none', basisVoorPlanningIsKlaar());
    }

    // Controleer de inhoud van een tabblad
    function valideerTab(tabSelector) {
        const tabPane = document.querySelector(tabSelector);
        if (!tabPane) {
            return { geldig: true, eersteFoutveld: null };
        }

        if (tabSelector === '#tab-basisgegevens') {
            const verplichteVelden = [
                document.getElementById('bezoek_naam'),
                document.getElementById('bezoek_schooljaar'),
                document.getElementById('onderwijs_type'),
                document.getElementById('bezoek_pincode'),
                document.getElementById('bezoek_max_keuzes')
            ].filter(Boolean);

            const eersteFoutveld = verplichteVelden.find(function(invoer) {
                return !invoer.checkValidity();
            }) || null;

            return { geldig: !eersteFoutveld, eersteFoutveld: eersteFoutveld };
        }

        if (tabSelector === '#tab-selectie') {
            return valideerSelectieTab(false);
        }

        if (tabSelector === '#tab-planning') {
            const type = onderwijsType.value;
            const eersteFoutveld = type === 'Primair Onderwijs'
                ? [bezoekDag1, bezoekDag2].find(function(invoer) { return invoer && !invoer.checkValidity(); })
                : [bezoekWeekStart, bezoekWeekEind].find(function(invoer) { return invoer && !invoer.checkValidity(); });

            if (eersteFoutveld) {
                return { geldig: false, eersteFoutveld: eersteFoutveld };
            }

            if (type === 'Primair Onderwijs' && bezoekDag1.value && bezoekDag2.value && bezoekDag2.value < bezoekDag1.value) {
                bezoekDag2.setCustomValidity('Dag 2 mag niet voor dag 1 liggen.');
                bezoekDag2.reportValidity();
                bezoekDag2.setCustomValidity('');
                return { geldig: false, eersteFoutveld: bezoekDag2 };
            }

            if ((type === 'Voortgezet Onderwijs' || type === 'MBO') && bezoekWeekStart.value && bezoekWeekEind.value && bezoekWeekEind.value < bezoekWeekStart.value) {
                bezoekWeekEind.setCustomValidity('Week einde mag niet voor week start liggen.');
                bezoekWeekEind.reportValidity();
                bezoekWeekEind.setCustomValidity('');
                return { geldig: false, eersteFoutveld: bezoekWeekEind };
            }

            return { geldig: true, eersteFoutveld: null };
        }

        if (tabSelector === '#tab-voorkeuren') {
            const voorkeurRijen = Array.from(document.querySelectorAll('.voorkeur-row-bezoek'));
            const ingevuldeVoorkeuren = voorkeurRijen.filter(function(rij) {
                const naamVeld = rij.querySelector('input[name="voorkeur_naam[]"]');
                return naamVeld && naamVeld.value.trim() !== '';
            });

            if (ingevuldeVoorkeuren.length < 3) {
                zetVoorkeurenFoutmelding('Vul minimaal 3 voorkeuren in.');
                return { geldig: false, eersteFoutveld: voorkeurRijen[0] ? voorkeurRijen[0].querySelector('input[name="voorkeur_naam[]"]') : null };
            }

            zetVoorkeurenFoutmelding('');
            return { geldig: true, eersteFoutveld: null };
        }

        return { geldig: true, eersteFoutveld: null };
    }

    // Controleer alle tabbladen en geef de eerste fouttab terug
    function valideerAlleTabs() {
        for (const tabSelector of tabVolgorde) {
            const resultaat = valideerTab(tabSelector);
            if (!resultaat.geldig) {
                return { geldig: false, foutTab: tabSelector, eersteFoutveld: resultaat.eersteFoutveld };
            }
        }

        return { geldig: true, foutTab: null, eersteFoutveld: null };
    }

    // Valideer een stap en ga alleen verder als die compleet is
    function gaNaarTabNaValidatie(tabSelector) {
        const huidigeTabSelector = haalActieveTabOp();
        const huidigeIndex = tabVolgorde.indexOf(huidigeTabSelector);
        const doelIndex = tabVolgorde.indexOf(tabSelector);

        if (doelIndex > huidigeIndex) {
            const resultaat = valideerTab(huidigeTabSelector);
            if (!resultaat.geldig) {
                if (resultaat.eersteFoutveld && typeof resultaat.eersteFoutveld.reportValidity === 'function') {
                    resultaat.eersteFoutveld.reportValidity();
                    resultaat.eersteFoutveld.focus();
                }
                return;
            }
        }

        gaNaarTab(tabSelector);
    }

    // Event: klik op tabkoppen
    bezoekTabs.forEach(function(tabKnop) {
        tabKnop.addEventListener('click', function(event) {
            const tabSelector = tabKnop.dataset.bsTarget;
            if (magTabOpenen(tabSelector)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            gaNaarEersteBenodigdeStap(tabSelector);
        });
    });

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
        const eerderGeselecteerdeKlasIds = getCheckedValues('klas_ids');

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
        if (eerderGeselecteerdeKlasIds.length > 0) {
            document.querySelectorAll('.js-klas-checkbox').forEach(function(cb) {
                if (eerderGeselecteerdeKlasIds.includes(Number(cb.value))) {
                    cb.checked = true;
                }
            });
        }
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
        updatePlanningPrerquisites();
        updateTabBeschikbaarheid();
    }

    // Update klas-selectie state: badge, required-marker en valideer dekking
    function updateKlasState() {
        const checkedKlassen = getCheckedValues('klas_ids');
        setRequiredMarker(klasRequiredMarker, checkedKlassen.length > 0);
        updateCountBadge('klas_ids', klasCountBadge, 'klas');
        validateKlasCoverage();
        updatePlanningPrerquisites();
        updateTabBeschikbaarheid();
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
        const response = await fetch('bezoeken_ajax.php?action=schools&type=' + encodeURIComponent(type), {
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
        const geselecteerdSchooljaar = bezoekSchooljaar ? bezoekSchooljaar.value : '';
        const klassenUrl = 'bezoeken_ajax.php?action=klassen&school_ids=' + encodeURIComponent(schoolIds.join(',')) + (geselecteerdSchooljaar ? '&schooljaar=' + encodeURIComponent(geselecteerdSchooljaar) : '');
        const response = await fetch(klassenUrl, {
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
        updatePlanningPrerquisites();
        updateTabBeschikbaarheid();
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

    // Event: schooljaar gewijzigd -> herlaad klassen voor geselecteerde scholen
    if (bezoekSchooljaar) {
        bezoekSchooljaar.addEventListener('change', function() {
            updateSchoolState();
        });
    }

    // Event: vorige/volgende stap knoppen
    tabDoorKnoppen.forEach(function(knop) {
        knop.addEventListener('click', function() {
            gaNaarTabNaValidatie(knop.dataset.tabDoel);
        });
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
    form.addEventListener('submit', function(event) {
        const resultaat = valideerAlleTabs();
        if (!resultaat.geldig) {
            event.preventDefault();
            gaNaarTab(resultaat.foutTab);
            if (resultaat.eersteFoutveld && typeof resultaat.eersteFoutveld.reportValidity === 'function') {
                resultaat.eersteFoutveld.reportValidity();
                resultaat.eersteFoutveld.focus();
            }
            return;
        }

        validateKlasCoverage();
    });

    // Init state
    updateCountBadge('school_ids', schoolCountBadge, 'school');
    updateCountBadge('klas_ids', klasCountBadge, 'klas');
    toggleDatumVeldenByOnderwijsType();
    updatePlanningPrerquisites();
    updateTabBeschikbaarheid();

    // Laad scholen als onderwijstype al ingevuld is (bij bewerking)
    if (onderwijsType.value) {
        onderwijsType.dispatchEvent(new Event('change'));
    }
});
