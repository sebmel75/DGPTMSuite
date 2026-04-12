<?php
/**
 * Template: Digitales Freigabe-Dokument fuer die Stipendienvergabe.
 *
 * Variablen aus class-freigabe.php:
 *   $approvals      — Array aller Freigaben
 *   $comments       — Array aller Kommentare
 *   $user_approved  — bool, hat aktueller User bereits freigegeben
 *   $user           — WP_User Objekt des aktuellen Benutzers
 */
if (!defined('ABSPATH')) exit;

$current_user_id = $user->ID;
$approval_count  = count($approvals);

/**
 * Hilfsfunktion: Kommentarblock fuer einen Abschnitt rendern.
 */
$render_section_comments = function ($section_id) use ($comments, $current_user_id) {
    $section_comments = array_filter($comments, function ($c) use ($section_id) {
        return $c['section'] === $section_id;
    });
    ?>
    <div class="dgptm-freigabe-comments-block" data-section="<?php echo esc_attr($section_id); ?>">
        <div class="dgptm-freigabe-comments-cta">
            <span class="dgptm-freigabe-comments-cta-icon">&#128172;</span>
            <span class="dgptm-freigabe-comments-cta-text">Haben Sie Anmerkungen oder Aenderungswuensche zu diesem Abschnitt? Bitte kommentieren Sie hier:</span>
            <span class="dgptm-freigabe-comments-count"><?php echo count($section_comments); ?></span>
        </div>
        <div class="dgptm-freigabe-comments-panel">
            <div class="dgptm-freigabe-comments-list">
                <?php foreach ($section_comments as $c) : ?>
                    <?php
                    $can_delete = ((int) $c['user_id'] === $current_user_id) || current_user_can('manage_options');
                    $ts = date_i18n('d.m.Y, H:i', strtotime($c['timestamp']));
                    ?>
                    <div class="dgptm-freigabe-comment" data-comment-id="<?php echo esc_attr($c['id']); ?>">
                        <div class="dgptm-freigabe-comment-meta">
                            <strong><?php echo esc_html($c['user_name']); ?></strong>
                            <span class="dgptm-freigabe-comment-date"><?php echo esc_html($ts); ?></span>
                            <?php if ($can_delete) : ?>
                                <button type="button" class="dgptm-freigabe-comment-delete" data-id="<?php echo esc_attr($c['id']); ?>" title="Kommentar loeschen">&times;</button>
                            <?php endif; ?>
                        </div>
                        <div class="dgptm-freigabe-comment-text"><?php echo nl2br(esc_html($c['text'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="dgptm-freigabe-comment-form">
                <textarea class="dgptm-freigabe-comment-input" rows="2" placeholder="Kommentar zu diesem Abschnitt..."></textarea>
                <button type="button" class="dgptm-freigabe-comment-submit">Kommentar senden</button>
            </div>
        </div>
    </div>
    <?php
};
?>

<div class="dgptm-freigabe-wrapper">

    <!-- Countdown -->
    <div class="dgptm-freigabe-countdown" id="dgptm-freigabe-countdown" data-deadline="2026-04-19T23:59:59">
        <span class="countdown-icon">&#9200;</span>
        <div>
            <div class="countdown-label">Frist zur Rueckmeldung</div>
            <div class="countdown-value">wird berechnet...</div>
            <div class="countdown-deadline" style="font-size:12px;color:#888;">Deadline: 19. April 2026</div>
        </div>
    </div>

    <!-- Umgesetzte Rueckmeldungen Banner -->
    <div class="dgptm-freigabe-feedback-banner">
        <div class="dgptm-freigabe-feedback-banner-icon">&#9989;</div>
        <div>
            <div class="dgptm-freigabe-feedback-banner-title">Rueckmeldungen eingearbeitet (Stand: 12. April 2026)</div>
            <div class="dgptm-freigabe-feedback-banner-text">
                Folgende Punkte aus der ersten Feedbackrunde wurden in das Konzept uebernommen:
            </div>
            <ul class="dgptm-freigabe-feedback-list">
                <li><strong>Gleichstandsregelung definiert</strong> &mdash; Stufenweise Aufloesung ueber Rubrik A &rarr; B &rarr; C &rarr; Gremiumsentscheidung <span class="dgptm-badge-umgesetzt">umgesetzt</span></li>
                <li><strong>Anonymisierung nicht erforderlich</strong> &mdash; Bewusste Entscheidung: Person und perfusiologisches Engagement sollen in die Bewertung einfliessen. Vollstaendige Anonymisierung waere bei Empfehlungsschreiben kaum durchsetzbar. <span class="dgptm-badge-umgesetzt">umgesetzt</span></li>
                <li><strong>Ausschlusskriterium &lt; 60 Punkte</strong> &mdash; Bewerbungen unter 60 Gesamtpunkten werden automatisch ausgeschlossen, auch wenn keine andere Bewerbung vorliegt. <span class="dgptm-badge-umgesetzt">umgesetzt</span></li>
                <li><strong>Bewertungsmatrix mit variabler Gutachterzahl</strong> &mdash; Das System bildet die Excel-Bewertungsmatrix digital ab. Die Anzahl der Gutachter ist nicht auf 2 festgelegt, sondern variabel (z.B. 3 oder mehr). <span class="dgptm-badge-umgesetzt">umgesetzt</span></li>
            </ul>
        </div>
    </div>

    <!-- Freigabe-Status Banner -->
    <div class="dgptm-freigabe-status-banner">
        <div class="dgptm-freigabe-status-info">
            <span class="dgptm-freigabe-status-icon"><?php echo $approval_count > 0 ? '&#9989;' : '&#9898;'; ?></span>
            <strong><?php echo $approval_count; ?> Freigabe<?php echo $approval_count !== 1 ? 'n' : ''; ?></strong> erteilt
        </div>
        <?php if (!$user_approved) : ?>
            <button type="button" class="dgptm-freigabe-approve-btn" id="dgptm-freigabe-approve">
                Dokument freigeben
            </button>
        <?php else : ?>
            <div class="dgptm-freigabe-approved-info">
                Sie haben freigegeben
                <button type="button" class="dgptm-freigabe-revoke-btn" id="dgptm-freigabe-revoke">
                    Freigabe zurueckziehen
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Freigabe-Liste -->
    <?php if (!empty($approvals)) : ?>
    <div class="dgptm-freigabe-approvals-list" id="dgptm-freigabe-approvals">
        <?php foreach ($approvals as $a) : ?>
            <div class="dgptm-freigabe-approval-item">
                <span class="dgptm-freigabe-approval-check">&#10003;</span>
                <strong><?php echo esc_html($a['user_name']); ?></strong>
                <span class="dgptm-freigabe-approval-date"><?php echo date_i18n('d.m.Y, H:i', strtotime($a['timestamp'])); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         DOKUMENT-INHALT
         ═══════════════════════════════════════════════════ -->

    <div class="dgptm-freigabe-dokument">

        <div class="dgptm-freigabe-header">
            <h2>Digitale Stipendienvergabe</h2>
            <p class="dgptm-freigabe-subtitle">Konzept zur Digitalisierung des Bewerbungs- und Bewertungsverfahrens</p>
            <p class="dgptm-freigabe-meta">DGPTM | Stand: April 2026 | Zur Freigabe durch den Stipendiumsrat</p>
        </div>

        <!-- Abschnitt 1 -->
        <div class="dgptm-freigabe-section" id="section-aenderungen">
            <h3>1. Was aendert sich?</h3>

            <p>Bisher laeuft das Bewerbungsverfahren per E-Mail: Bewerbende senden ihre Unterlagen an den Vorsitzenden, der sie manuell weiterleitet. Feedback wird per E-Mail gesammelt, ein standardisiertes Ranking gibt es nicht.</p>

            <p><strong>Kuenftig</strong> wird das Verfahren vollstaendig digital abgebildet:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Bisher (per E-Mail)</th><th>Kuenftig (digital)</th></tr>
                </thead>
                <tbody>
                    <tr><td>Bewerbung per E-Mail an den Vorsitzenden</td><td>Strukturiertes Upload-Formular auf perfusiologie.de</td></tr>
                    <tr><td>Manuelle Weiterleitung der Unterlagen</td><td>Automatischer Zugang im Mitgliederbereich</td></tr>
                    <tr><td>Keine Eingangsbestaetigung</td><td>Automatische Bestaetigungs-E-Mail</td></tr>
                    <tr><td>Subjektives Feedback per E-Mail</td><td>Standardisierter digitaler Bewertungsbogen</td></tr>
                    <tr><td>Kein automatisches Ranking</td><td>Automatisches Ranking mit Mittelwert aller Gutachter</td></tr>
                    <tr><td>Kein Export</td><td>PDF-Dokument als Vorlage fuer den Vorstand</td></tr>
                </tbody>
            </table>

            <div class="dgptm-freigabe-highlight green">
                <strong>Mehrere Stipendientypen:</strong> Das System unterstuetzt verschiedene Stipendien (aktuell: Promotionsstipendium und Josef Guettler Stipendium). Jedes Stipendium hat einen eigenen Bewerbungszeitraum. Das Bewertungsverfahren ist fuer alle Typen identisch.
            </div>

            <?php $render_section_comments('section-aenderungen'); ?>
        </div>

        <!-- Abschnitt 2 -->
        <div class="dgptm-freigabe-section" id="section-rollen">
            <h3>2. Wer ist beteiligt?</h3>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Rolle</th><th>Was kann diese Person?</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Bewerbende/r</strong></td>
                        <td>Bewerbungsformular auf der Homepage ausfuellen und Unterlagen hochladen</td>
                    </tr>
                    <tr>
                        <td><strong>Ratsmitglied</strong> (Gutachter/in)</td>
                        <td>Bewerbungen im Mitgliederbereich einsehen und ueber den digitalen Bewertungsbogen bewerten</td>
                    </tr>
                    <tr>
                        <td><strong>Vorsitzende/r</strong> des Stipendiumsrats</td>
                        <td>Alles was Ratsmitglieder koennen, plus: Bewerbungen freigeben, Gesamtauswertung einsehen, PDF-Export erstellen, Stipendium vergeben, Runde archivieren</td>
                    </tr>
                </tbody>
            </table>

            <p>Die Zugehoerigkeit zum Stipendiumsrat wird im Benutzerprofil hinterlegt. Der Stipendien-Reiter im Mitgliederbereich ist <strong>nur fuer Ratsmitglieder sichtbar</strong>.</p>

            <?php $render_section_comments('section-rollen'); ?>
        </div>

        <!-- Abschnitt 3 -->
        <div class="dgptm-freigabe-section" id="section-ablauf">
            <h3>3. Ablauf im Ueberblick</h3>

            <p>Das Verfahren gliedert sich in vier Phasen:</p>

            <div class="dgptm-freigabe-flow">

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 1: Bewerbungseingang</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">1</span>
                            <div>Bewerbende/r oeffnet die Stipendien-Seite und klickt <strong>&bdquo;Jetzt bewerben&ldquo;</strong> <span class="step-actor">Bewerbende/r</span><br>
                            <em class="step-note">Der Button ist nur waehrend des aktiven Bewerbungszeitraums sichtbar.</em></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">2</span>
                            <div>Strukturiertes Formular in 4 Schritten: <strong>Persoenliche Daten</strong> &rarr; <strong>Dokumente hochladen</strong> &rarr; <strong>Datenschutz-Einwilligung</strong> &rarr; <strong>Einreichen</strong> <span class="step-actor">Bewerbende/r</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">3</span>
                            <div>System sendet automatisch eine <strong>Eingangsbestaetigung per E-Mail</strong> und benachrichtigt den Vorsitzenden. <span class="step-actor">Automatisch</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 2: Freigabe &amp; Zugang</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">4</span>
                            <div>Bewerbungsunterlagen werden sicher in der Cloud gespeichert (Zoho WorkDrive, EU-Rechenzentrum). <span class="step-actor">Automatisch</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">5</span>
                            <div>Der Vorsitzende <strong>gibt die Bewerbung fuer alle Ratsmitglieder frei</strong> (oder: alle sehen sie sofort &mdash; konfigurierbar). <span class="step-actor">Vorsitzende/r</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">6</span>
                            <div>Alle Ratsmitglieder erhalten eine <strong>E-Mail-Benachrichtigung</strong>. <span class="step-actor">Automatisch</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 3: Bewertung durch Gutachter</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">7</span>
                            <div>Jedes Ratsmitglied liest die Unterlagen und fuellt den <strong>digitalen Bewertungsbogen</strong> aus. <span class="step-actor">Ratsmitglied</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">8</span>
                            <div>Bewertungsbogen: <strong>4 Rubriken</strong> (A&ndash;D) mit je 3 Leitfragen, Noten 1&ndash;10, optionale Kommentare. Kann als <strong>Entwurf gespeichert</strong> werden. <span class="step-actor">Ratsmitglied</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">9</span>
                            <div>System berechnet automatisch den <strong>gewichteten Gesamtscore</strong>. Vorsitzender wird informiert. <span class="step-actor">Automatisch</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 4: Auswertung &amp; Vergabe</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">10</span>
                            <div>Sobald alle Gutachter bewertet haben, erscheint die <strong>Gesamtauswertung mit Ranking</strong>. <span class="step-actor">Automatisch</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">11</span>
                            <div>Vorsitzender sieht Einzelbewertungen, erstellt <strong>PDF-Export</strong> (Vorlage fuer den Vorstand) und vergibt das Stipendium. <span class="step-actor">Vorsitzende/r</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">12</span>
                            <div>Runde wird <strong>archiviert</strong>. Nicht vergebene Bewerbungen werden nach Loeschfrist geloescht. <span class="step-actor">Vorsitzende/r</span></div>
                        </div>
                    </div>
                </div>

            </div>

            <?php $render_section_comments('section-ablauf'); ?>
        </div>

        <!-- Abschnitt 4 -->
        <div class="dgptm-freigabe-section" id="section-bewertungsbogen">
            <h3>4. Der digitale Bewertungsbogen</h3>

            <p>Der Bewertungsbogen bildet den bestehenden Gutachterleitfaden exakt ab. Jede Bewerbung wird anhand von vier Rubriken bewertet:</p>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">A. Wissenschaftlicher Wert der Fragestellung <span class="rubrik-weight">30 %</span></div>
                <ol>
                    <li>Ist die Fragestellung wissenschaftlich relevant?</li>
                    <li>Ist sie klar formuliert und forschungslogisch hergeleitet?</li>
                    <li>Ist ein echter Erkenntnisfortschritt zu erwarten?</li>
                </ol>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">B. Relevanz fuer die Weiterentwicklung der Perfusiologie <span class="rubrik-weight">30 %</span></div>
                <ol>
                    <li>Leistet das Projekt einen erkennbaren Beitrag zur Weiterentwicklung des Fachs?</li>
                    <li>Hat das Vorhaben Potenzial, wissenschaftliche oder praxisrelevante Impulse zu setzen?</li>
                    <li>Ist der Bezug zum Berufsfeld klar und substanziell?</li>
                </ol>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">C. Projektbeschreibung <span class="rubrik-weight">25 %</span></div>
                <ol>
                    <li>Ist das Projekt methodisch ueberzeugend?</li>
                    <li>Ist die Umsetzung im Promotionszeitraum realistisch?</li>
                    <li>Sind Aufbau, Argumentation und Planung nachvollziehbar?</li>
                </ol>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">D. Eingereichte Leistungsnachweise <span class="rubrik-weight">15 %</span></div>
                <ol>
                    <li>Lassen die bisherigen Leistungen eine erfolgreiche Promotion erwarten?</li>
                    <li>Liegen relevante fachliche und wissenschaftliche Kompetenzen vor?</li>
                    <li>Ist ein erkennbares Profil fuer das beantragte Thema vorhanden?</li>
                </ol>
            </div>

            <h4>Berechnung der Gesamtpunktzahl</h4>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Schritt 1 &mdash; Einzelbewertung:</strong> Jeder Gutachter vergibt pro Leitfrage eine Note von 1 bis 10. Pro Rubrik wird der Durchschnitt der 3 Noten gebildet und mit der Gewichtung multipliziert. Die Summe aller gewichteten Rubriken ergibt den <strong>Gesamtscore des Gutachters</strong> (maximal 10,0 Punkte bzw. 100 Gesamtpunkte).
            </div>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Schritt 2 &mdash; Gesamtbewertung der Bewerbung:</strong> Die Gesamtpunktzahl einer Bewerbung ergibt sich aus dem <strong>arithmetischen Mittel aller Gutachterbewertungen</strong>:<br>
                <em>Gesamtpunktzahl = (Summe aller Gutachterbewertungen) / Anzahl der vorliegenden Bewertungen</em><br>
                Die Anzahl der Gutachter ist <strong>variabel</strong> (nicht auf 2 festgelegt).
            </div>

            <div class="dgptm-freigabe-highlight orange" style="border-left-color:#e53935;background:#fce4ec;">
                <strong>Ausschlusskriterium:</strong> Bewerbungen mit einer Gesamtpunktzahl <strong>unter 60 Punkten</strong> (entspricht einem Score unter 6,0) werden <strong>automatisch ausgeschlossen</strong> &mdash; auch wenn in einer Runde keine andere Bewerbung die Schwelle erreicht. In diesem Fall wird das Stipendium in dieser Runde nicht vergeben.
            </div>

            <h4>Gleichstandsregelung</h4>

            <div class="dgptm-freigabe-highlight green">
                <strong>Bei identischer Gesamtpunktzahl</strong> erfolgt die Rangbildung in folgender Reihenfolge:
                <ol style="margin:8px 0 0 20px;">
                    <li>Hoehere Punktzahl in <strong>Rubrik A</strong> (Wissenschaftlicher Wert)</li>
                    <li>Falls weiterhin gleich: Hoehere Punktzahl in <strong>Rubrik B</strong> (Relevanz fuer die Perfusiologie)</li>
                    <li>Falls weiterhin gleich: Hoehere Punktzahl in <strong>Rubrik C</strong> (Projektbeschreibung)</li>
                    <li>Danach: <strong>Entscheidung im Gremium</strong></li>
                </ol>
            </div>

            <h4>Bewertungsmatrix</h4>

            <p>Das System bildet die bekannte Excel-Bewertungsmatrix vollstaendig digital ab:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr>
                        <th>Bewerber/in</th>
                        <th>Gutachter/in</th>
                        <th>A (30%)</th>
                        <th>B (30%)</th>
                        <th>C (25%)</th>
                        <th>D (15%)</th>
                        <th>Gesamt</th>
                        <th>Punkte</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td rowspan="3">Beispiel</td>
                        <td>Gutachter 1</td>
                        <td>8,0</td><td>7,0</td><td>6,0</td><td>9,0</td>
                        <td>7,45</td><td>74,5</td>
                    </tr>
                    <tr>
                        <td>Gutachter 2</td>
                        <td>9,0</td><td>8,0</td><td>7,0</td><td>8,0</td>
                        <td>8,15</td><td>81,5</td>
                    </tr>
                    <tr>
                        <td>Gutachter 3</td>
                        <td>7,0</td><td>8,0</td><td>8,0</td><td>7,0</td>
                        <td>7,55</td><td>75,5</td>
                    </tr>
                    <tr style="background:#e3f2fd;font-weight:600;">
                        <td colspan="2">Mittelwert</td>
                        <td>8,0</td><td>7,7</td><td>7,0</td><td>8,0</td>
                        <td>7,72</td><td>77,2</td>
                    </tr>
                </tbody>
            </table>

            <p style="font-size:13px;color:#666;">Die Anzahl der Gutachter kann pro Runde variieren. Das System berechnet den Mittelwert automatisch, unabhaengig davon ob 2, 3 oder mehr Gutachten vorliegen.</p>

            <h4>Anonymisierung</h4>

            <div class="dgptm-freigabe-highlight green">
                <strong>Bewusste Entscheidung: Keine Anonymisierung.</strong> Die Bewerbungen werden den Gutachtern <strong>nicht anonymisiert</strong> vorgelegt. Begruendung:
                <ul style="margin:6px 0 0 20px;">
                    <li>Eine vollstaendige Anonymisierung ist bei Empfehlungsschreiben und Lebenslaeufen kaum durchsetzbar &mdash; Hinweise auf Herkunft, Klinik oder betreuende Hochschule lassen sich nicht vollstaendig eliminieren.</li>
                    <li>Die Person und ihr bisheriges Engagement im perfusiologischen Umfeld (z.B. durch Vortraege) sollen bewusst in die Bewertung einfliessen koennen.</li>
                    <li>Die standardisierte Bewertung ueber den Gutachterleitfaden und das gewichtete Punktesystem stellt dennoch eine nachvollziehbare, kriteriengeleitete Entscheidung sicher.</li>
                </ul>
            </div>

            <?php $render_section_comments('section-bewertungsbogen'); ?>
        </div>

        <!-- Abschnitt 5 -->
        <div class="dgptm-freigabe-section" id="section-dokumente">
            <h3>5. Welche Dokumente werden hochgeladen?</h3>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Dokument</th><th>Pflicht?</th></tr>
                </thead>
                <tbody>
                    <tr><td>Tabellarischer Lebenslauf</td><td>Ja</td></tr>
                    <tr><td>Motivationsschreiben / Bewerbungsschreiben</td><td>Ja</td></tr>
                    <tr><td>Empfehlungsschreiben</td><td>Ja</td></tr>
                    <tr><td>Nachweise ueber Studienleistungen / Abschluss</td><td>Ja</td></tr>
                    <tr><td>Publikationen oder geplante wissenschaftliche Aktivitaeten</td><td>Nein (optional)</td></tr>
                    <tr><td>Ehrenamtliche Taetigkeiten oder Zusatzqualifikationen</td><td>Nein (optional)</td></tr>
                </tbody>
            </table>

            <?php $render_section_comments('section-dokumente'); ?>
        </div>

        <!-- Abschnitt 6 -->
        <div class="dgptm-freigabe-section" id="section-datenschutz">
            <h3>6. Datenschutz (DSGVO)</h3>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Einwilligung:</strong> Bewerbende muessen im Formular aktiv eine Datenschutz-Checkbox ankreuzen. Der Text verweist auf die Datenschutzerklaerung der DGPTM.
            </div>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Speicherort:</strong> Alle Daten werden in Zoho CRM und Zoho WorkDrive gespeichert &mdash; beides in EU-Rechenzentren. Auf dem Webserver werden <strong>keine</strong> Bewerbungsunterlagen dauerhaft gespeichert.
            </div>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Zugriff:</strong> Nur Mitglieder des Stipendiumsrats koennen Bewerbungen und Bewertungen einsehen.
            </div>

            <h4>Loeschfristen</h4>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Situation</th><th>Loeschfrist</th><th>Begruendung</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Bewerbung <strong>nicht erfolgreich</strong></td>
                        <td>12 Monate nach Rundenende</td>
                        <td>Zweck erfuellt</td>
                    </tr>
                    <tr>
                        <td>Bewerbung <strong>zurueckgezogen</strong></td>
                        <td>Sofort</td>
                        <td>Widerruf der Einwilligung</td>
                    </tr>
                    <tr>
                        <td>Stipendium <strong>laufend</strong></td>
                        <td>Keine Loeschung</td>
                        <td>Laufendes Vertragsverhaeltnis</td>
                    </tr>
                    <tr>
                        <td>Stipendium <strong>abgeschlossen</strong></td>
                        <td><strong>10 Jahre</strong> nach Abschluss</td>
                        <td>Dokumentationspflicht</td>
                    </tr>
                </tbody>
            </table>

            <p>30 Tage vor Ablauf einer Loeschfrist erhaelt der Vorsitzende eine Erinnerung per E-Mail.</p>

            <?php $render_section_comments('section-datenschutz'); ?>
        </div>

        <!-- Abschnitt 7 -->
        <div class="dgptm-freigabe-section" id="section-einstellungen">
            <h3>7. Konfigurierbare Einstellungen</h3>

            <p>Folgende Einstellungen koennen im Backend angepasst werden, ohne dass Programmieraenderungen noetig sind:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Einstellung</th><th>Wert</th><th>Bedeutung</th></tr>
                </thead>
                <tbody>
                    <tr><td>Bewerbungszeitraum</td><td>Pro Typ einstellbar</td><td>Start- und Enddatum pro Stipendientyp</td></tr>
                    <tr><td>Freigabe-Modus</td><td>Freigabe durch Vorsitzenden</td><td>Ob Bewerbungen erst vom Vorsitzenden freigegeben werden muessen</td></tr>
                    <tr><td>Ausschlusskriterium</td><td>&lt; 60 Punkte</td><td>Bewerbungen unter 60 Gesamtpunkten werden ausgeschlossen <span class="dgptm-badge-umgesetzt">fest</span></td></tr>
                    <tr><td>Gleichstandsregelung</td><td>A &rarr; B &rarr; C &rarr; Gremium</td><td>Stufenweise Aufloesung bei identischer Punktzahl <span class="dgptm-badge-umgesetzt">fest</span></td></tr>
                    <tr><td>Anzahl Gutachter</td><td>Variabel</td><td>Pro Runde frei waehlbar (2, 3 oder mehr)</td></tr>
                    <tr><td>Anonymisierung</td><td>Nein</td><td>Bewerbungen werden nicht anonymisiert vorgelegt <span class="dgptm-badge-umgesetzt">fest</span></td></tr>
                    <tr><td>Loeschfrist (nicht vergeben)</td><td>12 Monate</td><td>Aufbewahrung nach Rundenende</td></tr>
                    <tr><td>Loeschfrist (vergeben)</td><td>10 Jahre</td><td>Aufbewahrung nach Stipendiums-Abschluss</td></tr>
                    <tr><td>Automatische Loeschung</td><td>Nein (nur Erinnerung)</td><td>Ob Daten automatisch geloescht werden</td></tr>
                </tbody>
            </table>

            <?php $render_section_comments('section-einstellungen'); ?>
        </div>

        <!-- Abschnitt 8 -->
        <div class="dgptm-freigabe-section" id="section-benachrichtigungen">
            <h3>8. E-Mail-Benachrichtigungen</h3>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Ereignis</th><th>Empfaenger</th></tr>
                </thead>
                <tbody>
                    <tr><td>Bewerbung eingereicht</td><td>Bewerbende/r (Bestaetigung) + Vorsitzende/r</td></tr>
                    <tr><td>Bewerbung freigegeben</td><td>Alle Ratsmitglieder</td></tr>
                    <tr><td>Bewertung abgeschlossen</td><td>Vorsitzende/r</td></tr>
                    <tr><td>Alle Bewertungen komplett</td><td>Vorsitzende/r (Auswertung bereit)</td></tr>
                    <tr><td>Loeschfrist naht</td><td>Vorsitzende/r (30-Tage-Vorwarnung)</td></tr>
                </tbody>
            </table>

            <?php $render_section_comments('section-benachrichtigungen'); ?>
        </div>

        <!-- Abschnitt 9 -->
        <div class="dgptm-freigabe-section" id="section-naechste-schritte">
            <h3>9. Naechste Schritte</h3>

            <ol class="dgptm-freigabe-steps-list">
                <li><strong>Freigabe durch den Stipendiumsrat</strong> &mdash; Dieses Dokument pruefen und freigeben</li>
                <li><strong>Datenschutzerklaerung ergaenzen</strong> &mdash; Abschnitt zur Stipendienverarbeitung hinzufuegen</li>
                <li><strong>Technische Umsetzung</strong> &mdash; WordPress-Modul + Zoho CRM-Konfiguration</li>
                <li><strong>Testphase</strong> &mdash; Probelauf mit dem Stipendiumsrat</li>
                <li><strong>Go-Live</strong> &mdash; Aktivierung fuer die naechste Bewerbungsrunde</li>
            </ol>

            <div class="dgptm-freigabe-highlight orange">
                <strong>Vor Go-Live zu klaeren:</strong>
                <ul>
                    <li>Datenschutzerklaerung auf der Homepage anpassen</li>
                    <li>Bewerbungszeitraum fuer die erste Runde festlegen</li>
                    <li>Ratsmitglieder im System hinterlegen</li>
                    <li>WorkDrive-Ordnerstruktur einmalig einrichten</li>
                </ul>
            </div>

            <?php $render_section_comments('section-naechste-schritte'); ?>
        </div>

    </div><!-- .dgptm-freigabe-dokument -->

    <!-- ═══════════════════════════════════════════════════
         FREIGABE-BEREICH (unten, sticky)
         ═══════════════════════════════════════════════════ -->

    <div class="dgptm-freigabe-footer" id="dgptm-freigabe-footer">
        <div class="dgptm-freigabe-footer-inner">
            <div class="dgptm-freigabe-footer-left">
                <strong><?php echo $approval_count; ?></strong> Freigabe<?php echo $approval_count !== 1 ? 'n' : ''; ?> erteilt
                <?php if (!empty($approvals)) : ?>
                    &mdash;
                    <?php echo implode(', ', array_map(function ($a) { return esc_html($a['user_name']); }, $approvals)); ?>
                <?php endif; ?>
            </div>
            <div class="dgptm-freigabe-footer-right">
                <?php if (!$user_approved) : ?>
                    <button type="button" class="dgptm-freigabe-approve-btn" id="dgptm-freigabe-approve-footer">
                        Ich gebe dieses Konzept frei
                    </button>
                <?php else : ?>
                    <span class="dgptm-freigabe-approved-badge">Freigegeben</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- .dgptm-freigabe-wrapper -->
