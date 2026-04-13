<?php
/**
 * Template: Gutachter-Bewertungsbogen
 *
 * Variablen (von class-gutachter-form.php bereitgestellt):
 * @var array  $token_data    Token-Daten aus DB
 * @var array  $draft_data    Gespeicherte Entwurfsdaten (oder leer)
 * @var string $bewerber_name Name des Bewerbers
 * @var string $stipendientyp Stipendientyp
 * @var string $runde         Runden-Bezeichnung
 * @var array  $dokumente     Dokument-Links [{label, url}]
 */
if (!defined('ABSPATH')) exit;

$gutachter_name = esc_html($token_data['gutachter_name']);

// Hilfsfunktion: Dropdown-Wert aus Draft laden
$get_note = function($feld) use ($draft_data) {
    return isset($draft_data[$feld]) ? (int) $draft_data[$feld] : 0;
};
$get_text = function($feld) use ($draft_data) {
    return esc_textarea($draft_data[$feld] ?? '');
};

// Rubriken-Definition
$rubriken = [
    'A' => [
        'titel'    => 'Wissenschaftlicher Wert',
        'gewicht'  => '30%',
        'fragen'   => [
            'A1' => 'Ist die Fragestellung fuer das Fachgebiet relevant?',
            'A2' => 'Ist die Forschungsfrage klar formuliert?',
            'A3' => 'Ist ein Erkenntnisfortschritt zu erwarten?',
        ],
    ],
    'B' => [
        'titel'    => 'Relevanz fuer die Perfusiologie',
        'gewicht'  => '30%',
        'fragen'   => [
            'B1' => 'Leistet das Vorhaben einen Beitrag zum Fach?',
            'B2' => 'Sind praxisrelevante Impulse zu erwarten?',
            'B3' => 'Besteht ein klarer Bezug zum Berufsfeld?',
        ],
    ],
    'C' => [
        'titel'    => 'Projektbeschreibung und Methodik',
        'gewicht'  => '25%',
        'fragen'   => [
            'C1' => 'Ist die Methodik angemessen und nachvollziehbar?',
            'C2' => 'Ist das Vorhaben realisierbar (Zeitplan, Ressourcen)?',
            'C3' => 'Sind Aufbau und Planung schluessig?',
        ],
    ],
    'D' => [
        'titel'    => 'Leistungsnachweise des/der Bewerber/in',
        'gewicht'  => '15%',
        'fragen'   => [
            'D1' => 'Sind die akademischen Leistungen ueberzeugend?',
            'D2' => 'Sind relevante fachliche Kompetenzen erkennbar?',
            'D3' => 'Ergibt sich ein stimmiges Profil?',
        ],
    ],
];
?>

<div class="dgptm-gutachten-wrap">

    <!-- Header -->
    <div class="dgptm-gutachten-header">
        <div class="dgptm-gutachten-header-bar">
            <span class="dgptm-gutachten-logo">DGPTM Stipendium</span>
            <span class="dgptm-gutachten-header-sub">Begutachtung</span>
        </div>
        <div class="dgptm-gutachten-meta">
            <span class="dgptm-gutachten-meta-tag"><?php echo esc_html($stipendientyp); ?></span>
            <?php if ($runde) : ?>
                <span class="dgptm-gutachten-meta-tag dgptm-gutachten-meta-tag--light"><?php echo esc_html($runde); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Begruessung -->
    <div class="dgptm-gutachten-intro">
        <p>
            Guten Tag, <strong><?php echo $gutachter_name; ?></strong>,
        </p>
        <p>
            Sie wurden eingeladen, die folgende Bewerbung fuer das
            <?php echo esc_html($stipendientyp); ?> der DGPTM zu begutachten.
        </p>
        <div class="dgptm-gutachten-bewerber">
            <span class="dgptm-gutachten-bewerber-label">Bewerber/in:</span>
            <span class="dgptm-gutachten-bewerber-name"><?php echo esc_html($bewerber_name); ?></span>
        </div>
    </div>

    <!-- Dokumente -->
    <?php if (!empty($dokumente)) : ?>
    <div class="dgptm-gutachten-dokumente">
        <h4>Eingereichte Unterlagen</h4>
        <div class="dgptm-gutachten-dokumente-list">
            <?php foreach ($dokumente as $dok) : ?>
                <a href="<?php echo esc_url($dok['url']); ?>" target="_blank" rel="noopener" class="dgptm-gutachten-dok-link">
                    <span class="dgptm-gutachten-dok-icon">&#128196;</span>
                    <?php echo esc_html($dok['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bewertungsbogen -->
    <form id="dgptm-gutachten-form" class="dgptm-gutachten-form" novalidate>
        <input type="hidden" name="token" value="<?php echo esc_attr($token_data['token']); ?>">

        <?php foreach ($rubriken as $prefix => $rubrik) : ?>
        <fieldset class="dgptm-gutachten-rubrik" data-rubrik="<?php echo $prefix; ?>">
            <legend>
                <?php echo $prefix; ?>. <?php echo esc_html($rubrik['titel']); ?>
                <span class="dgptm-gutachten-gewicht">(<?php echo $rubrik['gewicht']; ?>)</span>
            </legend>

            <?php foreach ($rubrik['fragen'] as $frage_id => $frage_text) : ?>
            <div class="dgptm-gutachten-frage">
                <label for="<?php echo $frage_id; ?>_Note">
                    <?php echo substr($frage_id, 1); ?>. <?php echo esc_html($frage_text); ?>
                </label>
                <select id="<?php echo $frage_id; ?>_Note"
                        name="<?php echo $frage_id; ?>_Note"
                        class="dgptm-gutachten-note"
                        data-rubrik="<?php echo $prefix; ?>"
                        required>
                    <option value="">-- Note --</option>
                    <?php for ($i = 1; $i <= 10; $i++) : ?>
                        <option value="<?php echo $i; ?>" <?php selected($get_note($frage_id . '_Note'), $i); ?>>
                            <?php echo $i; ?><?php echo $i === 1 ? ' (ungenuegend)' : ($i === 10 ? ' (hervorragend)' : ''); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endforeach; ?>

            <div class="dgptm-gutachten-kommentar">
                <label for="<?php echo $prefix; ?>_Kommentar">Kommentar zu Rubrik <?php echo $prefix; ?> (optional):</label>
                <textarea id="<?php echo $prefix; ?>_Kommentar"
                          name="<?php echo $prefix; ?>_Kommentar"
                          rows="3"
                          placeholder="Optionale Anmerkungen zu dieser Rubrik..."><?php echo $get_text($prefix . '_Kommentar'); ?></textarea>
            </div>

            <div class="dgptm-gutachten-rubrik-score" data-rubrik-score="<?php echo $prefix; ?>">
                Rubrik <?php echo $prefix; ?>: <span class="score-value">--</span> / 10
            </div>
        </fieldset>
        <?php endforeach; ?>

        <!-- Gesamtanmerkungen -->
        <fieldset class="dgptm-gutachten-rubrik dgptm-gutachten-gesamt-anmerkungen">
            <legend>Gesamtanmerkungen</legend>
            <textarea id="Gesamtanmerkungen"
                      name="Gesamtanmerkungen"
                      rows="5"
                      placeholder="Zusammenfassende Beurteilung, Empfehlung..."><?php echo $get_text('Gesamtanmerkungen'); ?></textarea>
        </fieldset>

        <!-- Score-Vorschau -->
        <div class="dgptm-gutachten-score-vorschau" id="dgptm-score-vorschau">
            <div class="dgptm-gutachten-score-label">Vorschau: Gesamtscore</div>
            <div class="dgptm-gutachten-score-value">
                <span id="dgptm-score-gesamt">--</span> / 10
                <span class="dgptm-gutachten-score-punkte">(<span id="dgptm-score-punkte">--</span> Punkte)</span>
            </div>
            <div class="dgptm-gutachten-score-detail" id="dgptm-score-detail">
                A: <span id="score-a">--</span> |
                B: <span id="score-b">--</span> |
                C: <span id="score-c">--</span> |
                D: <span id="score-d">--</span>
            </div>
        </div>

        <!-- Aktionen -->
        <div class="dgptm-gutachten-actions">
            <div class="dgptm-gutachten-autosave-status" id="dgptm-autosave-status">
                <!-- Wird per JS aktualisiert -->
            </div>
            <button type="submit" class="dgptm-gutachten-submit" id="dgptm-gutachten-submit">
                Gutachten abschliessen
            </button>
        </div>

        <p class="dgptm-gutachten-hinweis">
            <strong>Hinweis:</strong> Nach dem Abschluss kann die Bewertung nicht mehr geaendert werden.
            Ihr Entwurf wird automatisch alle 30 Sekunden gespeichert.
        </p>
    </form>
</div>
