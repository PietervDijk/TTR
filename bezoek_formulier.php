<?php
// Formulier voor nieuw bezoek of bewerking van een bestaand bezoek
require 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

csrf_validate();

$foutmeldingen = [];
$succesmelding = null;
$ingevuldeGegevens = [];

if (isset($_SESSION['bezoeken_errors'])) {
    $foutmeldingen = $_SESSION['bezoeken_errors'];
    unset($_SESSION['bezoeken_errors']);
}

if (isset($_SESSION['bezoeken_post'])) {
    $ingevuldeGegevens = $_SESSION['bezoeken_post'];
    unset($_SESSION['bezoeken_post']);
}

if (isset($_SESSION['bezoeken_success'])) {
    $succesmelding = $_SESSION['bezoeken_success'];
    unset($_SESSION['bezoeken_success']);
}

$te_bewerken_bezoek = null;
$geselecteerde_school_ids = [];
$geselecteerde_klas_ids = [];
$geselecteerde_opties = [];
$typeNaarLabel = [
    'PO' => 'Primair Onderwijs',
    'VO' => 'Voortgezet Onderwijs',
    'MBO' => 'MBO',
];

if (isset($_GET['edit'])) {
    $te_bewerken_id = (int)$_GET['edit'];
    $stmt = $conn->prepare('SELECT * FROM bezoek WHERE bezoek_id=?');
    $stmt->bind_param('i', $te_bewerken_id);
    $stmt->execute();
    $te_bewerken_bezoek = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($te_bewerken_bezoek) {
        $stmt = $conn->prepare('SELECT school_id FROM bezoek_school WHERE bezoek_id=?');
        $stmt->bind_param('i', $te_bewerken_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($rij = $res->fetch_assoc()) {
            $geselecteerde_school_ids[] = (int)$rij['school_id'];
        }
        $stmt->close();

        $stmt = $conn->prepare('SELECT klas_id FROM bezoek_klas WHERE bezoek_id=?');
        $stmt->bind_param('i', $te_bewerken_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($rij = $res->fetch_assoc()) {
            $geselecteerde_klas_ids[] = (int)$rij['klas_id'];
        }
        $stmt->close();

        $stmt = $conn->prepare('SELECT * FROM bezoek_optie WHERE bezoek_id=? AND actief=1 ORDER BY volgorde ASC');
        $stmt->bind_param('i', $te_bewerken_id);
        $stmt->execute();
        $geselecteerde_opties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

if ($te_bewerken_bezoek) {
    $form_data = [
        'bezoek_naam' => $te_bewerken_bezoek['naam'],
        'onderwijs_type' => $typeNaarLabel[$te_bewerken_bezoek['type_onderwijs']] ?? '',
        'bezoek_pincode' => $te_bewerken_bezoek['pincode'],
        'bezoek_max_keuzes' => (string)$te_bewerken_bezoek['max_keuzes'],
        'bezoek_schooljaar' => $te_bewerken_bezoek['schooljaar'] ?? '',
        'bezoek_dag1' => $te_bewerken_bezoek['po_dag1'] ? date('Y-m-d\TH:i', strtotime($te_bewerken_bezoek['po_dag1'])) : '',
        'bezoek_dag2' => $te_bewerken_bezoek['po_dag2'] ? date('Y-m-d\TH:i', strtotime($te_bewerken_bezoek['po_dag2'])) : '',
        'bezoek_week_start' => $te_bewerken_bezoek['vo_week_start'] ?? '',
        'bezoek_week_eind' => $te_bewerken_bezoek['vo_week_eind'] ?? '',
    ];

    $formulier_voorkeur_namen = array_column($geselecteerde_opties, 'naam');
    $formulier_voorkeur_max = [];
    $formulier_voorkeur_dagdelen = array_column($geselecteerde_opties, 'dag_deel');
    $formulier_voorkeur_max_dag1 = [];
    $formulier_voorkeur_max_dag2 = [];

    // Zet opgeslagen voorkeuren om naar formulierwaarden
    foreach ($geselecteerde_opties as $optie) {
        $optieMax = $optie['max_leerlingen'] ?? null;
        $optieDagdeel = $optie['dag_deel'] ?? 'week';
        $optieMaxDag1 = $optie['max_leerlingen_dag1'] ?? null;
        $optieMaxDag2 = $optie['max_leerlingen_dag2'] ?? null;

        $heeftOptieMaxDag1 = ($optieMaxDag1 !== null && $optieMaxDag1 !== '');
        $heeftOptieMaxDag2 = ($optieMaxDag2 !== null && $optieMaxDag2 !== '');

        if ($form_data['onderwijs_type'] === 'Primair Onderwijs') {
            if ($optieDagdeel === 'dag1') {
                if ($heeftOptieMaxDag1) {
                    $optieMax = $optieMaxDag1;
                }
            } elseif ($optieDagdeel === 'dag2') {
                if ($heeftOptieMaxDag2) {
                    $optieMax = $optieMaxDag2;
                }
            } elseif ($optieDagdeel === 'beide') {
                // Geef splitwaarden voorrang, zodat een opgeslagen PO-variant
                // niet terugvalt op een oude max_leerlingen-waarde.
                if ($heeftOptieMaxDag1 || $heeftOptieMaxDag2) {
                    $optieMax = $heeftOptieMaxDag1 ? $optieMaxDag1 : ($heeftOptieMaxDag2 ? $optieMaxDag2 : $optieMax);
                }
            }
        }

        $formulier_voorkeur_max[] = ($optieMax === null || $optieMax === '') ? '' : (string)$optieMax;
        $formulier_voorkeur_max_dag1[] = isset($optie['max_leerlingen_dag1']) ? (string)$optie['max_leerlingen_dag1'] : '';
        $formulier_voorkeur_max_dag2[] = isset($optie['max_leerlingen_dag2']) ? (string)$optie['max_leerlingen_dag2'] : '';
    }

    $vooraf_geselecteerde_school_ids = $geselecteerde_school_ids;
    $vooraf_geselecteerde_klas_ids = $geselecteerde_klas_ids;
} else {
    $form_data = $ingevuldeGegevens;
    $formulier_voorkeur_namen = $ingevuldeGegevens['voorkeur_naam'] ?? [];
    $formulier_voorkeur_max = $ingevuldeGegevens['voorkeur_max'] ?? [];
    $formulier_voorkeur_dagdelen = $ingevuldeGegevens['voorkeur_dag_deel'] ?? [];
    $formulier_voorkeur_max_dag1 = $ingevuldeGegevens['voorkeur_max_dag1'] ?? [];
    $formulier_voorkeur_max_dag2 = $ingevuldeGegevens['voorkeur_max_dag2'] ?? [];
    $vooraf_geselecteerde_school_ids = array_values(array_unique(array_map('intval', $ingevuldeGegevens['school_ids'] ?? [])));
    $vooraf_geselecteerde_klas_ids = array_values(array_unique(array_map('intval', $ingevuldeGegevens['klas_ids'] ?? [])));
}

require 'includes/header.php';
?>
<div class="ttr-app">
    <div class="container py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <?php if ($te_bewerken_bezoek): ?>
                    <h2 class="fw-bold text-primary mb-0">Bezoek bewerken</h2>
                    <p class="mb-0">Pas de gegevens van dit bezoek aan.</p>
                <?php else: ?>
                    <h2 class="fw-bold text-primary mb-0">Nieuw bezoek toevoegen</h2>
                    <p class="mb-0">Maak hier een nieuw bezoek aan.</p>
                <?php endif; ?>
            </div>
            <a href="bezoeken.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Terug naar overzicht
            </a>
        </div>

        <?php if (!empty($foutmeldingen)): ?>
            <div class="alert alert-danger">
                <strong>Controleer je invoer:</strong>
                <ul class="mb-0">
                    <?php foreach ($foutmeldingen as $foutmelding): ?>
                        <li><?= e($foutmelding) ?></li>
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
            <div class="card-header <?= $te_bewerken_bezoek ? 'bg-warning text-dark' : 'bg-success text-white' ?>">
                <?php if ($te_bewerken_bezoek): ?>
                    <i class="bi bi-pencil-square"></i> Bezoek bewerken: <?= e($te_bewerken_bezoek['naam']) ?>
                <?php else: ?>
                    <i class="bi bi-plus-circle"></i> Nieuw bezoek toevoegen
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="alert alert-light border small mb-4">
                    Het formulier is opgesplitst in tabs zodat je per onderdeel kunt werken.
                </div>

                <form
                    method="post"
                    action="bezoeken_process.php"
                    id="bezoekForm"
                    novalidate
                    data-vooraf-scholen='<?= e(json_encode($vooraf_geselecteerde_school_ids)) ?>'
                    data-vooraf-klassen='<?= e(json_encode($vooraf_geselecteerde_klas_ids)) ?>'
                >
                    <?= csrf_input() ?>
                    <?php if ($te_bewerken_bezoek): ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="bezoek_id" value="<?= (int)$te_bewerken_bezoek['bezoek_id'] ?>">
                    <?php endif; ?>

                    <ul class="nav nav-tabs bezoek-form-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active text-start bezoek-form-tabknop" id="tab-basisgegevens-tab" data-bs-toggle="tab" data-bs-target="#tab-basisgegevens" type="button" role="tab" aria-controls="tab-basisgegevens" aria-selected="true">
                                <span class="bezoek-form-tabtitel">1. Basisgegevens</span>
                                <span class="bezoek-form-tabomschrijving">Naam, type, schooljaar, pincode en keuzes</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-start bezoek-form-tabknop" id="tab-selectie-tab" data-bs-toggle="tab" data-bs-target="#tab-selectie" type="button" role="tab" aria-controls="tab-selectie" aria-selected="false">
                                <span class="bezoek-form-tabtitel">2. Scholen en klassen</span>
                                <span class="bezoek-form-tabomschrijving">Pas nadat de basis klopt</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-start bezoek-form-tabknop" id="tab-planning-tab" data-bs-toggle="tab" data-bs-target="#tab-planning" type="button" role="tab" aria-controls="tab-planning" aria-selected="false">
                                <span class="bezoek-form-tabtitel">3. Planning</span>
                                <span class="bezoek-form-tabomschrijving">Na onderwijs type en schoolselectie</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-start bezoek-form-tabknop" id="tab-voorkeuren-tab" data-bs-toggle="tab" data-bs-target="#tab-voorkeuren" type="button" role="tab" aria-controls="tab-voorkeuren" aria-selected="false">
                                <span class="bezoek-form-tabtitel">4. Voorkeuren</span>
                                <span class="bezoek-form-tabomschrijving">Als scholen, klassen en planning klaar zijn</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-basisgegevens" role="tabpanel" aria-labelledby="tab-basisgegevens-tab" tabindex="0">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <label for="bezoek_naam" class="form-label">Bezoeknaam</label>
                                    <input type="text" class="form-control" id="bezoek_naam" name="bezoek_naam" placeholder="Naam van bezoek" value="<?= e($form_data['bezoek_naam'] ?? '') ?>" required>
                                </div>

                                <div class="col-lg-6">
                                    <label for="bezoek_schooljaar" class="form-label">Schooljaar</label>
                                    <select class="form-select" id="bezoek_schooljaar" name="bezoek_schooljaar" required>
                                        <?php
                                        $gekozen_bsj = $form_data['bezoek_schooljaar'] ?? get_huidig_schooljaar();
                                        if ($gekozen_bsj === '') {
                                            $gekozen_bsj = get_huidig_schooljaar();
                                        }
                                        $bsj_lijst = get_schooljaren();
                                        if (!in_array($gekozen_bsj, $bsj_lijst, true)) {
                                            array_unshift($bsj_lijst, $gekozen_bsj);
                                        }
                                        foreach ($bsj_lijst as $bsj):
                                        ?>
                                            <option value="<?= e($bsj) ?>" <?= $gekozen_bsj === $bsj ? 'selected' : '' ?>>
                                                <?= e($bsj) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-lg-6">
                                    <label for="onderwijs_type" class="form-label">Type onderwijs</label>
                                    <select class="form-select" id="onderwijs_type" name="onderwijs_type" required>
                                        <option value="" disabled <?= empty($form_data['onderwijs_type']) ? 'selected' : '' ?>>Selecteer type onderwijs</option>
                                        <option value="Primair Onderwijs" <?= ($form_data['onderwijs_type'] ?? '') === 'Primair Onderwijs' ? 'selected' : '' ?>>Primair onderwijs (PO)</option>
                                        <option value="Voortgezet Onderwijs" <?= ($form_data['onderwijs_type'] ?? '') === 'Voortgezet Onderwijs' ? 'selected' : '' ?>>Voortgezet onderwijs (VO)</option>
                                        <option value="MBO" <?= ($form_data['onderwijs_type'] ?? '') === 'MBO' ? 'selected' : '' ?>>Middelbaar beroepsonderwijs (MBO)</option>
                                    </select>
                                </div>

                                <div class="col-lg-3">
                                    <label for="bezoek_pincode" class="form-label">Pincode</label>
                                    <input type="text" class="form-control" id="bezoek_pincode" name="bezoek_pincode" placeholder="Bijv: 1234" value="<?= e($form_data['bezoek_pincode'] ?? '') ?>" required>
                                </div>

                                <div class="col-lg-3">
                                    <label for="bezoek_max_keuzes" class="form-label">Aantal keuzes</label>
                                    <select class="form-select" id="bezoek_max_keuzes" name="bezoek_max_keuzes" required>
                                        <option value="" disabled <?= empty($form_data['bezoek_max_keuzes']) ? 'selected' : '' ?>>Selecteer aantal keuzes</option>
                                        <option value="2" <?= ($form_data['bezoek_max_keuzes'] ?? '') === '2' ? 'selected' : '' ?>>2</option>
                                        <option value="3" <?= ($form_data['bezoek_max_keuzes'] ?? '') === '3' ? 'selected' : '' ?>>3</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn btn-primary js-tab-door" data-tab-doel="#tab-selectie">
                                    Volgende stap
                                </button>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-selectie" role="tabpanel" aria-labelledby="tab-selectie-tab" tabindex="0">
                            <div class="mb-3">
                                <label class="form-label">Scholen</label>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <input type="text" id="school_filter" class="form-control" placeholder="Zoek school...">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="school_select_all">Alles selecteren</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="school_clear">Leegmaken</button>
                                    <span class="badge bg-light text-dark border align-self-center" id="school_count_badge">0 geselecteerd</span>
                                </div>
                                <div id="school_list" class="border rounded p-2"></div>
                                <input type="text" id="school_required_marker" class="d-none" required>
                                <div class="form-text">Kies een of meerdere scholen met de checkboxen.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Klassen</label>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <input type="text" id="klas_filter" class="form-control" placeholder="Zoek klas...">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="klas_select_all">Alles selecteren</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="klas_clear">Leegmaken</button>
                                    <span class="badge bg-light text-dark border align-self-center" id="klas_count_badge">0 geselecteerd</span>
                                </div>
                                <div id="klas_list" class="border rounded p-2"></div>
                                <input type="text" id="klas_required_marker" class="d-none" required>
                                <div id="klas_validation_message" class="small text-danger mt-2 d-none"></div>
                                <div class="form-text">Per gekozen school moet je minimaal 1 klas selecteren.</div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary js-tab-door" data-tab-doel="#tab-basisgegevens">
                                    Vorige stap
                                </button>
                                <button type="button" class="btn btn-primary js-tab-door" data-tab-doel="#tab-planning">
                                    Volgende stap
                                </button>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-planning" role="tabpanel" aria-labelledby="tab-planning-tab" tabindex="0">
                            <div id="planning_prerequisite_message" class="bezoek-tab-melding small mb-3 d-none">
                                Vul eerst de basisgegevens in en selecteer scholen en klassen. Daarna kun je de planning invullen.
                            </div>
                            <div class="mb-3" id="po_datums_wrapper">
                                <label class="form-label">PO datums en tijden</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label for="bezoek_dag1" class="form-label">Dag 1 (datum + tijd)</label>
                                        <input type="datetime-local" class="form-control" id="bezoek_dag1" name="bezoek_dag1" value="<?= e($form_data['bezoek_dag1'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="bezoek_dag2" class="form-label">Dag 2 (datum + tijd)</label>
                                        <input type="datetime-local" class="form-control" id="bezoek_dag2" name="bezoek_dag2" value="<?= e($form_data['bezoek_dag2'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 d-none" id="vo_mbo_datums_wrapper">
                                <label class="form-label">VO/MBO week</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label for="bezoek_week_start" class="form-label">Week start (datum)</label>
                                        <input type="date" class="form-control" id="bezoek_week_start" name="bezoek_week_start" value="<?= e($form_data['bezoek_week_start'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="bezoek_week_eind" class="form-label">Week einde (datum)</label>
                                        <input type="date" class="form-control" id="bezoek_week_eind" name="bezoek_week_eind" value="<?= e($form_data['bezoek_week_eind'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary js-tab-door" data-tab-doel="#tab-selectie">
                                    Vorige stap
                                </button>
                                <button type="button" class="btn btn-primary js-tab-door" data-tab-doel="#tab-voorkeuren">
                                    Volgende stap
                                </button>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-voorkeuren" role="tabpanel" aria-labelledby="tab-voorkeuren-tab" tabindex="0">
                            <div class="mb-3">
                                <label class="form-label">Voorkeuren (minimaal 3)</label>
                                <div id="po_voorkeur_dagdeel_hint" class="alert alert-info py-2 px-3 small mb-3 d-none">
                                    Kies per wereld of deze beschikbaar is op dag 1, dag 2 of op beide dagen.
                                </div>
                                <div id="voorkeur_validation_message" class="small text-danger mt-2 d-none"></div>
                                <div id="voorkeurenWrapperBezoek">
                                    <?php
                                    $opgeslagen_voorkeur_namen = $formulier_voorkeur_namen;
                                    $opgeslagen_voorkeur_max = $formulier_voorkeur_max;
                                    $opgeslagen_voorkeur_dagdelen = $formulier_voorkeur_dagdelen;
                                    $opgeslagen_voorkeur_max_dag1 = $formulier_voorkeur_max_dag1;
                                    $opgeslagen_voorkeur_max_dag2 = $formulier_voorkeur_max_dag2;
                                    $min_rows = max(3, count($opgeslagen_voorkeur_namen));

                                    for ($i = 0; $i < $min_rows; $i++):
                                        $geselecteerd_dagdeel = $opgeslagen_voorkeur_dagdelen[$i] ?? 'beide';
                                        if (!in_array($geselecteerd_dagdeel, ['dag1', 'dag2', 'beide', 'week'], true)) {
                                            $geselecteerd_dagdeel = 'beide';
                                        }
                                        $toon_split_limieten = (($form_data['onderwijs_type'] ?? '') === 'Primair Onderwijs' && $geselecteerd_dagdeel === 'beide');
                                        $split_groep_verstopt = (($form_data['onderwijs_type'] ?? '') !== 'Primair Onderwijs' || $geselecteerd_dagdeel !== 'beide');
                                    ?>
                                        <div class="mb-2 d-flex gap-2 flex-wrap voorkeur-row-bezoek">
                                            <input type="text" name="voorkeur_naam[]" class="form-control" placeholder="Bijv: Electrotechniek" value="<?= e($opgeslagen_voorkeur_namen[$i] ?? '') ?>">
                                            <div class="js-base-max-group<?= $toon_split_limieten ? ' d-none' : '' ?>">
                                                <input
                                                    type="number"
                                                    name="voorkeur_max[]"
                                                    class="form-control js-base-max-input"
                                                    placeholder="<?= $geselecteerd_dagdeel === 'dag1' ? 'Limiet dag 1' : ($geselecteerd_dagdeel === 'dag2' ? 'Limiet dag 2' : 'Max leerlingen') ?>"
                                                    min="1"
                                                    value="<?= e($opgeslagen_voorkeur_max[$i] ?? '') ?>"
                                                >
                                            </div>
                                            <div class="js-po-dagdeel-group<?= ($form_data['onderwijs_type'] ?? '') === 'Primair Onderwijs' ? '' : ' d-none' ?>">
                                                <select name="voorkeur_dag_deel[]" class="form-select js-po-dagdeel-select">
                                                    <option value="beide" <?= $geselecteerd_dagdeel === 'beide' ? 'selected' : '' ?>>Beide dagen</option>
                                                    <option value="dag1" <?= $geselecteerd_dagdeel === 'dag1' ? 'selected' : '' ?>>Alleen dag 1</option>
                                                    <option value="dag2" <?= $geselecteerd_dagdeel === 'dag2' ? 'selected' : '' ?>>Alleen dag 2</option>
                                                </select>
                                            </div>
                                            <div class="js-po-split-max-group d-flex gap-2<?= $split_groep_verstopt ? ' d-none' : '' ?>">
                                                <input type="number" name="voorkeur_max_dag1[]" class="form-control js-po-split-max-input" placeholder="Limiet dag 1" min="1" value="<?= e($opgeslagen_voorkeur_max_dag1[$i] ?? '') ?>">
                                                <input type="number" name="voorkeur_max_dag2[]" class="form-control js-po-split-max-input" placeholder="Limiet dag 2" min="1" value="<?= e($opgeslagen_voorkeur_max_dag2[$i] ?? '') ?>">
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="js-add-bezoek-voorkeur">
                                    + Nieuwe voorkeur
                                </button>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary js-tab-door" data-tab-doel="#tab-planning">
                                    Vorige stap
                                </button>

                                <?php if ($te_bewerken_bezoek): ?>
                                    <button type="submit" class="btn btn-warning text-dark">
                                        <i class="bi bi-check-circle"></i> Bezoek opslaan
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Bezoek toevoegen
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>