<?php
/*
 * PAGINA-UITLEG (voor studenten)
 * -------------------------------------------------
 * Bezoekenbeheer bestaat uit 3 delen in dit bestand:
 * 1. AJAX-endpoints voor dynamische school/klas-lijsten
 * 2. Serverflow voor laden/verwijderen/bewerken van bezoeken
 * 3. Formulier + frontendlogica voor selectie en validatie
 */
require 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'schools') {
    // AJAX: geef scholen terug op basis van onderwijstype.
    $onderwijsType = trim((string)($_GET['type'] ?? ''));
    if (!in_array($onderwijsType, ['Primair Onderwijs', 'Voortgezet Onderwijs', 'MBO'], true)) {
        header('Content-Type: application/json; charset=utf-8');
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

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($antwoord);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'klassen') {
    // AJAX: geef klassen terug voor de geselecteerde scholen.
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
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit;
    }

    $inClause = implode(',', $school_ids);
    $sql = "
        SELECT k.klas_id, k.klasaanduiding, k.leerjaar, k.school_id, s.schoolnaam
        FROM klas k
        INNER JOIN school s ON s.school_id = k.school_id
        WHERE k.school_id IN ($inClause)
        ORDER BY s.schoolnaam ASC, k.leerjaar ASC, k.klasaanduiding ASC
    ";
    $klas_resultaat = $conn->query($sql);

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

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($antwoord);
    exit;
}

require 'includes/header.php';

$foutmeldingen = [];
$succesmelding = null;
$ingevuldeGegevens = [];

// Haal foutmeldingen uit sessie
if (isset($_SESSION['bezoeken_errors'])) {
    $foutmeldingen = $_SESSION['bezoeken_errors'];
    unset($_SESSION['bezoeken_errors']);
}

// Haal POST data uit sessie voor herweergave
if (isset($_SESSION['bezoeken_post'])) {
    $ingevuldeGegevens = $_SESSION['bezoeken_post'];
    unset($_SESSION['bezoeken_post']);
}

// Haal succesmeldingen uit sessie
if (isset($_SESSION['bezoeken_success'])) {
    $succesmelding = $_SESSION['bezoeken_success'];
    unset($_SESSION['bezoeken_success']);
}

// DELETE: verwijder bezoek inclusief gekoppelde records in één transactie.
if (isset($_GET['delete'])) {
    $te_verwijderen_bezoek_id = (int)$_GET['delete'];
    $conn->begin_transaction();
    try {
        foreach ([
            'DELETE FROM bezoek_optie WHERE bezoek_id=?',
            'DELETE FROM bezoek_klas WHERE bezoek_id=?',
            'DELETE FROM bezoek_school WHERE bezoek_id=?',
            'DELETE FROM bezoek WHERE bezoek_id=?',
        ] as $del_sql) {
            $stmt = $conn->prepare($del_sql);
            $stmt->bind_param('i', $te_verwijderen_bezoek_id);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
        header('Location: bezoeken.php');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $foutmeldingen[] = 'Er is iets misgegaan bij het verwijderen van het bezoek.';
    }
}

// Bewerkmodus: laad huidig bezoek + gekoppelde scholen/klassen/opties.
$te_bewerken_bezoek = null;
$geselecteerde_school_ids = [];
$geselecteerde_klas_ids = [];
$geselecteerde_opties = [];
$typeNaarLabel = ['PO' => 'Primair Onderwijs', 'VO' => 'Voortgezet Onderwijs', 'MBO' => 'MBO'];

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
        while ($r = $res->fetch_assoc()) {
            $geselecteerde_school_ids[] = (int)$r['school_id'];
        }
        $stmt->close();

        $stmt = $conn->prepare('SELECT klas_id FROM bezoek_klas WHERE bezoek_id=?');
        $stmt->bind_param('i', $te_bewerken_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $geselecteerde_klas_ids[] = (int)$r['klas_id'];
        }
        $stmt->close();

        $stmt = $conn->prepare('SELECT * FROM bezoek_optie WHERE bezoek_id=? AND actief=1 ORDER BY volgorde ASC');
        $stmt->bind_param('i', $te_bewerken_id);
        $stmt->execute();
        $geselecteerde_opties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Overzichtstabel: haal alle bezoeken op.
$bezoek_resultaat = $conn->query('SELECT * FROM bezoek ORDER BY created_at DESC');
$gemarkeerde_bezoek_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : null;

// Formulierdata bepalen (bewerkmodus of post-back na validatiefouten).
if ($te_bewerken_bezoek) {
    $form_data = [
        'bezoek_naam'       => $te_bewerken_bezoek['naam'],
        'onderwijs_type'    => $typeNaarLabel[$te_bewerken_bezoek['type_onderwijs']] ?? '',
        'bezoek_pincode'    => $te_bewerken_bezoek['pincode'],
        'bezoek_max_keuzes' => (string)$te_bewerken_bezoek['max_keuzes'],
        'bezoek_schooljaar' => $te_bewerken_bezoek['schooljaar'] ?? '',
        'bezoek_dag1'       => $te_bewerken_bezoek['po_dag1'] ? date('Y-m-d\TH:i', strtotime($te_bewerken_bezoek['po_dag1'])) : '',
        'bezoek_dag2'       => $te_bewerken_bezoek['po_dag2'] ? date('Y-m-d\TH:i', strtotime($te_bewerken_bezoek['po_dag2'])) : '',
        'bezoek_week_start' => $te_bewerken_bezoek['vo_week_start'] ?? '',
        'bezoek_week_eind'  => $te_bewerken_bezoek['vo_week_eind'] ?? '',
    ];
    $formulier_voorkeur_namen = array_column($geselecteerde_opties, 'naam');
    $formulier_voorkeur_max = [];
    $formulier_voorkeur_dagdelen = array_column($geselecteerde_opties, 'dag_deel');
    $formulier_voorkeur_max_dag1 = [];
    $formulier_voorkeur_max_dag2 = [];
    foreach ($geselecteerde_opties as $optie) {
        $optieMax = $optie['max_leerlingen'] ?? null;
        $optieDagdeel = $optie['dag_deel'] ?? 'week';
        if (($optieMax === null || $optieMax === '') && $form_data['onderwijs_type'] === 'Primair Onderwijs') {
            if ($optieDagdeel === 'dag1') {
                $optieMax = $optie['max_leerlingen_dag1'] ?? null;
            } elseif ($optieDagdeel === 'dag2') {
                $optieMax = $optie['max_leerlingen_dag2'] ?? null;
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

?>
<div class="ttr-app">
    <div class="container py-5">
        <h2 class="fw-bold text-primary mb-0">Bezoeken beheren</h2>
        <p>Op deze pagina kun je nieuwe bezoeken toevoegen en bestaande bezoeken beheren.</p>

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

        <!-- Overzicht bezoeken -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white fw-semibold">
                Overzicht bezoeken
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Naam</th>
                                <th>Type</th>
                                <th>Pincode</th>
                                <th>Datum / Week</th>
                                <th>Status</th>
                                <th class="text-end">Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bezoek_resultaat && $bezoek_resultaat->num_rows > 0): ?>
                                <?php while ($bezoekRij = $bezoek_resultaat->fetch_assoc()): ?>
                                    <?php
                                        $type_labels = ['PO' => 'Primair Onderwijs', 'VO' => 'Voortgezet Onderwijs', 'MBO' => 'MBO'];
                                        $type_label = $type_labels[$bezoekRij['type_onderwijs']] ?? $bezoekRij['type_onderwijs'];
                                        if ($bezoekRij['type_onderwijs'] === 'PO') {
                                            $datum_ton = $bezoekRij['po_dag1'] ? date('d-m-Y', strtotime($bezoekRij['po_dag1'])) : '—';
                                            if ($bezoekRij['po_dag2']) $datum_ton .= ' / ' . date('d-m-Y', strtotime($bezoekRij['po_dag2']));
                                        } else {
                                            $datum_ton = ($bezoekRij['vo_week_start'] ? date('d-m-Y', strtotime($bezoekRij['vo_week_start'])) : '—');
                                            if ($bezoekRij['vo_week_eind']) $datum_ton .= ' t/m ' . date('d-m-Y', strtotime($bezoekRij['vo_week_eind']));
                                        }
                                    ?>
                                    <tr<?= ($gemarkeerde_bezoek_id === (int)$bezoekRij['bezoek_id']) ? ' class="table-warning"' : '' ?>>
                                        <td><?= e($bezoekRij['naam']) ?></td>
                                        <td><?= e($type_label) ?></td>
                                        <td><code><?= e($bezoekRij['pincode']) ?></code></td>
                                        <td><?= e($datum_ton) ?></td>
                                        <td>
                                            <?php if ($bezoekRij['actief']): ?>
                                                <span class="badge bg-success">Actief</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactief</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group" role="group">
                                                <a href="verdeling.php?bezoek_id=<?= (int)$brow['bezoek_id'] ?>" class="btn btn-dark btn-sm" title="Open verdeling voor alle gekoppelde klassen">
                                                    <i class="bi bi-diagram-3"></i> Verdeling
                                                </a>
                                                <a href="bezoeken.php?edit=<?= (int)$brow['bezoek_id'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-pencil-square"></i> Bewerken
                                                </a>
                                                <a href="bezoeken.php?delete=<?= (int)$brow['bezoek_id'] ?>" class="btn btn-danger btn-sm js-confirm" data-confirm="Weet je zeker dat je dit bezoek wilt verwijderen?">
                                                    <i class="bi bi-trash"></i> Verwijderen
                                                </a>
                                            </div>
                                                <a href="verdeling.php?bezoek_id=<?= (int)$bezoekRij['bezoek_id'] ?>" class="btn btn-dark btn-sm" title="Open verdeling voor alle gekoppelde klassen">
                                    </tr>
                                <?php endwhile; ?>
                                                <a href="bezoeken.php?edit=<?= (int)$bezoekRij['bezoek_id'] ?>" class="btn btn-primary btn-sm">
                                <tr>
                                    <td colspan="6" class="text-muted text-center py-3">Nog geen bezoeken aangemaakt.</td>
                                                <a href="bezoeken.php?delete=<?= (int)$bezoekRij['bezoek_id'] ?>" class="btn btn-danger btn-sm js-confirm" data-confirm="Weet je zeker dat je dit bezoek wilt verwijderen?">
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Toevoegen / Bewerken formulier -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header <?= $te_bewerken_bezoek ? 'bg-warning text-dark' : 'bg-success text-white' ?>">
                <?php if ($te_bewerken_bezoek): ?>
                    <i class="bi bi-pencil-square"></i> Bezoek bewerken: <?= e($te_bewerken_bezoek['naam']) ?>
                <?php else: ?>
                    <i class="bi bi-plus-circle"></i> Nieuw bezoek toevoegen
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="post" action="bezoeken_process.php" id="bezoekForm" novalidate>
                    <?php if ($te_bewerken_bezoek): ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="bezoek_id" value="<?= (int)$te_bewerken_bezoek['bezoek_id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="bezoek_naam" class="form-label">Bezoeknaam</label>
                        <input type="text" class="form-control" id="bezoek_naam" name="bezoek_naam" placeholder="Naam van bezoek" value="<?= e($form_data['bezoek_naam'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="onderwijs_type" class="form-label">Type onderwijs</label>
                        <select class="form-select" id="onderwijs_type" name="onderwijs_type" required>
                            <option value="" disabled <?= empty($form_data['onderwijs_type']) ? 'selected' : '' ?>>Selecteer type onderwijs</option>
                            <option value="Primair Onderwijs" <?= ($form_data['onderwijs_type'] ?? '') === 'Primair Onderwijs' ? 'selected' : '' ?>>Primair onderwijs (PO)</option>
                            <option value="Voortgezet Onderwijs" <?= ($form_data['onderwijs_type'] ?? '') === 'Voortgezet Onderwijs' ? 'selected' : '' ?>>Voortgezet onderwijs (VO)</option>
                            <option value="MBO" <?= ($form_data['onderwijs_type'] ?? '') === 'MBO' ? 'selected' : '' ?>>Middelbaar beroepsonderwijs (MBO)</option>
                        </select>
                    </div>

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

                    <div class="mb-3" id="po_datums_wrapper">
                        <label class="form-label">PO datums en tijden</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="bezoek_dag1" class="form-label">Dag 1 (datum + tijd)</label>
                                <input type="datetime-local" class="form-control" id="bezoek_dag1" name="bezoek_dag1"
                                    value="<?= e($form_data['bezoek_dag1'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="bezoek_dag2" class="form-label">Dag 2 (datum + tijd)</label>
                                <input type="datetime-local" class="form-control" id="bezoek_dag2" name="bezoek_dag2"
                                    value="<?= e($form_data['bezoek_dag2'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 d-none" id="vo_mbo_datums_wrapper">
                        <label class="form-label">VO/MBO week</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="bezoek_week_start" class="form-label">Week start (datum)</label>
                                <input type="date" class="form-control" id="bezoek_week_start" name="bezoek_week_start"
                                    value="<?= e($form_data['bezoek_week_start'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="bezoek_week_eind" class="form-label">Week einde (datum)</label>
                                <input type="date" class="form-control" id="bezoek_week_eind" name="bezoek_week_eind"
                                    value="<?= e($form_data['bezoek_week_eind'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
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
                                <option value="<?= htmlspecialchars($bsj) ?>" <?= $gekozen_bsj === $bsj ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bsj) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="bezoek_pincode" class="form-label">Pincode</label>
                        <input type="text" class="form-control" id="bezoek_pincode" name="bezoek_pincode"
                            placeholder="Bijv: 1234" value="<?= htmlspecialchars($form_data['bezoek_pincode'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="bezoek_max_keuzes" class="form-label">Aantal keuzes</label>
                        <select class="form-select" id="bezoek_max_keuzes" name="bezoek_max_keuzes" required>
                            <option value="" disabled <?= empty($form_data['bezoek_max_keuzes']) ? 'selected' : '' ?>>Selecteer aantal keuzes</option>
                            <option value="2" <?= ($form_data['bezoek_max_keuzes'] ?? '') === '2' ? 'selected' : '' ?>>2</option>
                            <option value="3" <?= ($form_data['bezoek_max_keuzes'] ?? '') === '3' ? 'selected' : '' ?>>3</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Voorkeuren (minimaal 3)</label>
                        <div id="po_voorkeur_dagdeel_hint" class="alert alert-info py-2 px-3 small mb-3 d-none">
                            Kies per wereld of deze beschikbaar is op dag 1, dag 2 of op beide dagen.
                        </div>
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
                                        <input type="number" name="voorkeur_max_dag1[]" class="form-control js-po-split-max-input" placeholder="Limiet dag 1" min="1" value="<?= e($opgeslagen_voorkeur_max_dag1[$i] ?? '') ?>" <?= $split_groep_verstopt ? 'disabled' : '' ?>>
                                        <input type="number" name="voorkeur_max_dag2[]" class="form-control js-po-split-max-input" placeholder="Limiet dag 2" min="1" value="<?= e($opgeslagen_voorkeur_max_dag2[$i] ?? '') ?>" <?= $split_groep_verstopt ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="js-add-bezoek-voorkeur">
                            + Nieuwe voorkeur
                        </button>
                    </div>

                    <?php if ($te_bewerken_bezoek): ?>
                        <div class="d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-warning text-dark">
                                <i class="bi bi-check-circle"></i> Bezoek opslaan
                            </button>
                            <a href="bezoeken.php" class="btn btn-secondary">Annuleren</a>
                        </div>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Bezoek toevoegen
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const voorafGeselecteerdeSchoolIds = <?= json_encode($vooraf_geselecteerde_school_ids) ?>;
    const voorafGeselecteerdeKlasIds = <?= json_encode($vooraf_geselecteerde_klas_ids) ?>;

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

        const poDatumsWrapper = document.getElementById('po_datums_wrapper');
        const voMboDatumsWrapper = document.getElementById('vo_mbo_datums_wrapper');
        const poVoorkeurDagdeelHint = document.getElementById('po_voorkeur_dagdeel_hint');
        const bezoekDag1 = document.getElementById('bezoek_dag1');
        const bezoekDag2 = document.getElementById('bezoek_dag2');
        const bezoekWeekStart = document.getElementById('bezoek_week_start');
        const bezoekWeekEind = document.getElementById('bezoek_week_eind');

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

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getCheckedValues(name) {
            return Array.from(document.querySelectorAll('input[name="' + name + '[]"]:checked')).map((el) => Number(el.value));
        }

        function updateCountBadge(name, badgeEl, singular) {
            const count = getCheckedValues(name).length;
            badgeEl.textContent = count + ' geselecteerd';
            if (count === 1) {
                badgeEl.textContent = count + ' ' + singular + ' geselecteerd';
            }
        }

        function setRequiredMarker(markerEl, hasSelection) {
            markerEl.value = hasSelection ? 'ok' : '';
        }

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

        function getSelectedSchoolIds() {
            return getCheckedValues('school_ids');
        }

        function getSelectedKlasCheckboxes() {
            return Array.from(document.querySelectorAll('.js-klas-checkbox'));
        }

        function updateSchoolState() {
            const selectedSchoolIds = getSelectedSchoolIds();
            setRequiredMarker(schoolRequiredMarker, selectedSchoolIds.length > 0);
            updateCountBadge('school_ids', schoolCountBadge, 'school');
            fetchKlassenForSchools(selectedSchoolIds);
        }

        function updateKlasState() {
            const checkedKlassen = getCheckedValues('klas_ids');
            setRequiredMarker(klasRequiredMarker, checkedKlassen.length > 0);
            updateCountBadge('klas_ids', klasCountBadge, 'klas');
            validateKlasCoverage();
        }

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

        function applySchoolFilter() {
            const q = (schoolFilter.value || '').trim().toLowerCase();
            const rows = Array.from(schoolList.querySelectorAll('label'));
            rows.forEach((row) => {
                const txt = row.textContent.toLowerCase();
                row.style.display = q === '' || txt.includes(q) ? '' : 'none';
            });
        }

        function applyKlasFilter() {
            const q = (klasFilter.value || '').trim().toLowerCase();
            const rows = Array.from(klasList.querySelectorAll('.js-klas-row'));
            rows.forEach((row) => {
                const txt = row.textContent.toLowerCase();
                row.style.display = q === '' || txt.includes(q) ? '' : 'none';
            });
        }

        async function fetchSchoolsByType(type) {
            const response = await fetch('bezoeken.php?action=schools&type=' + encodeURIComponent(type), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                throw new Error('Kon scholen niet laden');
            }
            return response.json();
        }

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

        function clearSchoolAndKlasLists() {
            schoolList.innerHTML = '';
            klasList.innerHTML = '';
            setRequiredMarker(schoolRequiredMarker, false);
            setRequiredMarker(klasRequiredMarker, false);
            updateCountBadge('school_ids', schoolCountBadge, 'school');
            updateCountBadge('klas_ids', klasCountBadge, 'klas');
            validateKlasCoverage();
        }

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

        onderwijsType.addEventListener('change', async function() {
            const type = onderwijsType.value;
            clearSchoolAndKlasLists();
            toggleDatumVeldenByOnderwijsType();
            if (!type) return;
            try {
                const schools = await fetchSchoolsByType(type);
                renderSchoolList(schools);
            } catch (err) {
                schoolList.innerHTML = '<div class="text-danger small">Fout bij laden van scholen.</div>';
            }
        });

        schoolFilter.addEventListener('input', applySchoolFilter);
        klasFilter.addEventListener('input', applyKlasFilter);

        schoolSelectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('.js-school-checkbox').forEach((cb) => { cb.checked = true; });
            updateSchoolState();
        });

        schoolClearBtn.addEventListener('click', function() {
            document.querySelectorAll('.js-school-checkbox').forEach((cb) => { cb.checked = false; });
            updateSchoolState();
        });

        klasSelectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('.js-klas-checkbox').forEach((cb) => { cb.checked = true; });
            updateKlasState();
        });

        klasClearBtn.addEventListener('click', function() {
            document.querySelectorAll('.js-klas-checkbox').forEach((cb) => { cb.checked = false; });
            updateKlasState();
        });

        schoolList.addEventListener('change', function(event) {
            if (event.target && event.target.classList.contains('js-school-checkbox')) {
                updateSchoolState();
            }
        });

        klasList.addEventListener('change', function(event) {
            if (event.target && event.target.classList.contains('js-klas-checkbox')) {
                updateKlasState();
            }
        });

        // Bevestiging bij verwijderen
        document.querySelectorAll('.js-confirm').forEach(function(link) {
            link.addEventListener('click', function(event) {
                const message = link.dataset.confirm || 'Weet je het zeker?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        // Nieuwe voorkeur toevoegen
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

            voorkeurenWrapper.addEventListener('change', function(event) {
                if (event.target && event.target.classList.contains('js-po-dagdeel-select')) {
                    const row = event.target.closest('.voorkeur-row-bezoek');
                    if (row) {
                        updatePoRowDagdeelState(row, onderwijsType.value === 'Primair Onderwijs');
                    }
                }
            });

        form.addEventListener('submit', function() {
            validateKlasCoverage();
        });

        // Init state
        updateCountBadge('school_ids', schoolCountBadge, 'school');
        updateCountBadge('klas_ids', klasCountBadge, 'klas');
        toggleDatumVeldenByOnderwijsType();

        // Als er al een type geselecteerd was (POST-herweergave of bewerkmode), scholen laden
        if (onderwijsType.value) {
            onderwijsType.dispatchEvent(new Event('change'));
        }
    });
</script>

<?php require 'includes/footer.php'; ?>