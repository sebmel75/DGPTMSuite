<?php
/**
 * Template: Vorsitzenden-Dashboard
 *
 * Variablen (von class-vorsitz-dashboard.php bereitgestellt):
 * @var array $aktive_runden  Runden-Konfigurationen aus Settings
 * @var string $frist_datum   Default-Frist formatiert
 */
if (!defined('ABSPATH')) exit;
?>

<div class="dgptm-vorsitz-wrap" id="dgptm-vorsitz-dashboard">

    <!-- Header mit Filtern -->
    <div class="dgptm-vorsitz-header">
        <h3>Stipendien &mdash; Vorsitzenden-Dashboard</h3>
        <div class="dgptm-vorsitz-filter">
            <label for="dgptm-vorsitz-runde">Runde:</label>
            <select id="dgptm-vorsitz-runde">
                <?php foreach ($aktive_runden as $typ) : ?>
                    <option value="<?php echo esc_attr($typ['runde']); ?>"
                            data-typ="<?php echo esc_attr($typ['bezeichnung']); ?>">
                        <?php echo esc_html($typ['bezeichnung']); ?> &mdash; <?php echo esc_html($typ['runde']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Lade-Indikator -->
    <div class="dgptm-vorsitz-loading" id="dgptm-vorsitz-loading" style="display:none;">
        <div class="dgptm-vorsitz-spinner"></div>
        Bewerbungen werden geladen...
    </div>

    <!-- Status-Gruppen -->
    <div id="dgptm-vorsitz-content">

        <!-- Geprueft (bereit zur Freigabe) -->
        <section class="dgptm-vorsitz-section" id="dgptm-section-geprueft" style="display:none;">
            <h4 class="dgptm-vorsitz-section-title dgptm-vorsitz-section-title--yellow">
                Geprueft <span class="dgptm-vorsitz-badge" id="dgptm-count-geprueft">0</span>
            </h4>
            <div class="dgptm-vorsitz-cards" id="dgptm-cards-geprueft"></div>
        </section>

        <!-- Freigegeben (Gutachter einladen) -->
        <section class="dgptm-vorsitz-section" id="dgptm-section-freigegeben" style="display:none;">
            <h4 class="dgptm-vorsitz-section-title dgptm-vorsitz-section-title--blue">
                Freigegeben <span class="dgptm-vorsitz-badge" id="dgptm-count-freigegeben">0</span>
            </h4>
            <div class="dgptm-vorsitz-cards" id="dgptm-cards-freigegeben"></div>
        </section>

        <!-- In Bewertung -->
        <section class="dgptm-vorsitz-section" id="dgptm-section-in_bewertung" style="display:none;">
            <h4 class="dgptm-vorsitz-section-title dgptm-vorsitz-section-title--orange">
                In Bewertung <span class="dgptm-vorsitz-badge" id="dgptm-count-in_bewertung">0</span>
            </h4>
            <div class="dgptm-vorsitz-cards" id="dgptm-cards-in_bewertung"></div>
        </section>

        <!-- Abgeschlossen (Ranking) -->
        <section class="dgptm-vorsitz-section" id="dgptm-section-abgeschlossen" style="display:none;">
            <h4 class="dgptm-vorsitz-section-title dgptm-vorsitz-section-title--green">
                Abgeschlossen <span class="dgptm-vorsitz-badge" id="dgptm-count-abgeschlossen">0</span>
            </h4>
            <div class="dgptm-vorsitz-ranking" id="dgptm-ranking-table"></div>
            <div class="dgptm-vorsitz-bulk-actions" id="dgptm-bulk-actions" style="display:none;">
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--outline" data-action="pdf">
                    PDF-Export
                </button>
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--primary" data-action="archivieren">
                    Runde archivieren
                </button>
            </div>
        </section>

        <!-- Leer-Hinweis -->
        <div class="dgptm-vorsitz-empty" id="dgptm-vorsitz-empty" style="display:none;">
            <p>Keine Bewerbungen in dieser Runde vorhanden.</p>
        </div>
    </div>

    <!-- Einladungs-Modal (versteckt) -->
    <div class="dgptm-vorsitz-modal-overlay" id="dgptm-einladung-modal" style="display:none;">
        <div class="dgptm-vorsitz-modal">
            <div class="dgptm-vorsitz-modal-header">
                <h4>Gutachter/in einladen</h4>
                <button type="button" class="dgptm-vorsitz-modal-close" id="dgptm-einladung-close">&times;</button>
            </div>
            <div class="dgptm-vorsitz-modal-body">
                <input type="hidden" id="dgptm-einladung-stipendium-id">
                <p class="dgptm-vorsitz-modal-info" id="dgptm-einladung-bewerber-info"></p>
                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-einladung-name">Name des/der Gutachter/in:</label>
                    <input type="text" id="dgptm-einladung-name" placeholder="z.B. Prof. Dr. Mueller">
                </div>
                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-einladung-email">E-Mail:</label>
                    <input type="email" id="dgptm-einladung-email" placeholder="gutachter@example.de">
                </div>
                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-einladung-frist">Frist bis:</label>
                    <input type="date" id="dgptm-einladung-frist"
                           value="<?php echo esc_attr(date('Y-m-d', strtotime('+28 days'))); ?>">
                </div>
            </div>
            <div class="dgptm-vorsitz-modal-footer">
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--outline" id="dgptm-einladung-cancel">Abbrechen</button>
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--primary" id="dgptm-einladung-send">Einladung senden</button>
            </div>
        </div>
    </div>
</div>
