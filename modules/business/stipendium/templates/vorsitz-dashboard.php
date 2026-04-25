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
        <div class="dgptm-vorsitz-toolbar">
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
            <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--outline" id="dgptm-vorsitz-btn-runde-add">
                + Neue Runde
            </button>
            <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--primary" id="dgptm-vorsitz-btn-manuell-add">
                + Antrag manuell anlegen
            </button>
        </div>
    </div>

    <!-- Hinweis: manuelle Anlage -->
    <div class="dgptm-vorsitz-info-banner">
        Anträge, die per E-Mail oder Papier eingegangen sind, können hier direkt
        eingepflegt und in den Bewertungslauf übernommen werden. Gutachten erfolgen
        weiterhin token-basiert per persönlichem Einladungslink.
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

                <div class="dgptm-vorsitz-field" id="dgptm-einladung-pool-wrap" style="display:none;">
                    <label for="dgptm-einladung-pool">Aus bestehendem Gutachter-Pool wählen (optional):</label>
                    <select id="dgptm-einladung-pool">
                        <option value="">– neue Person eingeben –</option>
                    </select>
                </div>

                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-einladung-name">Name des/der Gutachter:in:</label>
                    <input type="text" id="dgptm-einladung-name" placeholder="z.B. Prof. Dr. Müller">
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

    <!-- Runden-Modal (versteckt) -->
    <div class="dgptm-vorsitz-modal-overlay" id="dgptm-runde-modal" style="display:none;">
        <div class="dgptm-vorsitz-modal">
            <div class="dgptm-vorsitz-modal-header">
                <h4>Stipendiums-Runde anlegen</h4>
                <button type="button" class="dgptm-vorsitz-modal-close" id="dgptm-runde-close">&times;</button>
            </div>
            <div class="dgptm-vorsitz-modal-body">
                <p class="dgptm-vorsitz-hint-text" style="margin:0 0 12px;">
                    Bestehenden Stipendientyp auswählen oder neuen anlegen, dann den
                    Bewerbungszeitraum für diese Runde festlegen.
                </p>

                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-runde-typ-id">Stipendientyp:</label>
                    <select id="dgptm-runde-typ-id">
                        <option value="">— neuen Typ anlegen —</option>
                    </select>
                </div>

                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-runde-bezeichnung">Bezeichnung <span class="req">*</span></label>
                    <input type="text" id="dgptm-runde-bezeichnung" placeholder="z.B. Promotionsstipendium">
                </div>

                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-runde-name">Runden-Bezeichnung <span class="req">*</span></label>
                    <input type="text" id="dgptm-runde-name" placeholder="z.B. Ausschreibung 2026">
                </div>

                <div class="dgptm-vorsitz-grid-2">
                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-runde-start">Bewerbung Start:</label>
                        <input type="date" id="dgptm-runde-start">
                    </div>
                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-runde-ende">Bewerbung Ende:</label>
                        <input type="date" id="dgptm-runde-ende">
                    </div>
                </div>
            </div>
            <div class="dgptm-vorsitz-modal-footer">
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--outline" id="dgptm-runde-cancel">Abbrechen</button>
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--primary" id="dgptm-runde-save">Runde speichern</button>
            </div>
        </div>
    </div>

    <!-- Manuell-Modal (versteckt) -->
    <div class="dgptm-vorsitz-modal-overlay" id="dgptm-manuell-modal" style="display:none;">
        <div class="dgptm-vorsitz-modal dgptm-vorsitz-modal--wide">
            <div class="dgptm-vorsitz-modal-header">
                <h4 id="dgptm-manuell-title">Antrag manuell einpflegen</h4>
                <button type="button" class="dgptm-vorsitz-modal-close" id="dgptm-manuell-close">&times;</button>
            </div>
            <div class="dgptm-vorsitz-modal-body">
                <input type="hidden" id="dgptm-manuell-id" value="">

                <div class="dgptm-vorsitz-grid-2">
                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-manuell-typ">Stipendientyp <span class="req">*</span></label>
                        <select id="dgptm-manuell-typ"></select>
                    </div>
                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-manuell-runde">Runde <span class="req">*</span></label>
                        <input type="text" id="dgptm-manuell-runde" placeholder="z.B. Ausschreibung 2026">
                    </div>
                </div>

                <fieldset class="dgptm-vorsitz-fieldset">
                    <legend>Bewerber:in</legend>

                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-manuell-orcid">ORCID-ID (optional, aber empfohlen):</label>
                        <div class="dgptm-vorsitz-orcid-row">
                            <input type="text" id="dgptm-manuell-orcid" placeholder="0000-0000-0000-0000">
                            <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--outline dgptm-vorsitz-btn--sm" id="dgptm-manuell-orcid-btn">Daten abrufen</button>
                        </div>
                        <small id="dgptm-manuell-orcid-status" class="dgptm-vorsitz-hint-text"></small>
                    </div>

                    <div class="dgptm-vorsitz-grid-2">
                        <div class="dgptm-vorsitz-field">
                            <label for="dgptm-manuell-name">Name <span class="req">*</span></label>
                            <input type="text" id="dgptm-manuell-name" placeholder="Vorname Nachname">
                        </div>
                        <div class="dgptm-vorsitz-field">
                            <label for="dgptm-manuell-email">E-Mail:</label>
                            <input type="email" id="dgptm-manuell-email">
                        </div>
                    </div>

                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-manuell-institution">Institution:</label>
                        <input type="text" id="dgptm-manuell-institution" placeholder="z.B. Universitätsklinikum ...">
                    </div>
                </fieldset>

                <fieldset class="dgptm-vorsitz-fieldset">
                    <legend>Projekt</legend>
                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-manuell-projekt-titel">Projekttitel:</label>
                        <input type="text" id="dgptm-manuell-projekt-titel">
                    </div>
                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-manuell-projekt-zus">Zusammenfassung / Exposé:</label>
                        <textarea id="dgptm-manuell-projekt-zus" rows="4"
                                  placeholder="Kurzfassung des Vorhabens, ca. 5–10 Sätze..."></textarea>
                    </div>
                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-manuell-projekt-meth">Methodik:</label>
                        <textarea id="dgptm-manuell-projekt-meth" rows="3"
                                  placeholder="Studiendesign, Fallzahl, Verfahren..."></textarea>
                    </div>
                </fieldset>

                <fieldset class="dgptm-vorsitz-fieldset">
                    <legend>Eingereichte Unterlagen (Links zu PDFs in WorkDrive/SharePoint)</legend>
                    <p class="dgptm-vorsitz-hint-text" style="margin:0 0 8px;">
                        Tipp: PDFs in WorkDrive ablegen, Freigabelink erzeugen und hier einfügen.
                        Die Gutachter:innen sehen die Links direkt im Bewertungsbogen.
                    </p>
                    <div class="dgptm-vorsitz-grid-2">
                        <div class="dgptm-vorsitz-field">
                            <label for="dgptm-manuell-doc-lebenslauf">Lebenslauf:</label>
                            <input type="url" id="dgptm-manuell-doc-lebenslauf" placeholder="https://...">
                        </div>
                        <div class="dgptm-vorsitz-field">
                            <label for="dgptm-manuell-doc-motivation">Motivationsschreiben:</label>
                            <input type="url" id="dgptm-manuell-doc-motivation" placeholder="https://...">
                        </div>
                        <div class="dgptm-vorsitz-field">
                            <label for="dgptm-manuell-doc-expose">Exposé / Projektbeschreibung:</label>
                            <input type="url" id="dgptm-manuell-doc-expose" placeholder="https://...">
                        </div>
                        <div class="dgptm-vorsitz-field">
                            <label for="dgptm-manuell-doc-empfehlung">Empfehlungsschreiben:</label>
                            <input type="url" id="dgptm-manuell-doc-empfehlung" placeholder="https://...">
                        </div>
                        <div class="dgptm-vorsitz-field">
                            <label for="dgptm-manuell-doc-studien">Studienleistungen:</label>
                            <input type="url" id="dgptm-manuell-doc-studien" placeholder="https://...">
                        </div>
                        <div class="dgptm-vorsitz-field">
                            <label for="dgptm-manuell-doc-publikationen">Publikationen (optional):</label>
                            <input type="url" id="dgptm-manuell-doc-publikationen" placeholder="https://...">
                        </div>
                        <div class="dgptm-vorsitz-field">
                            <label for="dgptm-manuell-doc-zusatz">Ehrenamt / Zusatz (optional):</label>
                            <input type="url" id="dgptm-manuell-doc-zusatz" placeholder="https://...">
                        </div>
                    </div>
                </fieldset>

                <div class="dgptm-vorsitz-grid-2">
                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-manuell-eingang">Eingangsdatum:</label>
                        <input type="date" id="dgptm-manuell-eingang"
                               value="<?php echo esc_attr(date('Y-m-d')); ?>">
                    </div>
                    <div class="dgptm-vorsitz-field">
                        <label for="dgptm-manuell-status">Status nach Anlage:</label>
                        <select id="dgptm-manuell-status">
                            <option value="Freigegeben">Freigegeben (zur Bewertung freigeben)</option>
                            <option value="Geprueft">Geprüft (Vorprüfung erfolgt, Vorsitz prüft Befangenheit)</option>
                        </select>
                    </div>
                </div>

                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-manuell-bemerkung">Interne Bemerkung (nur intern sichtbar):</label>
                    <textarea id="dgptm-manuell-bemerkung" rows="2"
                              placeholder="z.B. eingegangen per E-Mail vom 12.04.2026..."></textarea>
                </div>
            </div>
            <div class="dgptm-vorsitz-modal-footer">
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--outline" id="dgptm-manuell-cancel">Abbrechen</button>
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--primary" id="dgptm-manuell-save">Bewerbung speichern</button>
            </div>
        </div>
    </div>
</div>
