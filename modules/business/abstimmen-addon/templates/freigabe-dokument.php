<?php
/**
 * Template: Interaktives Freigabe-Dokument fuer das Abstimmungstool.
 *
 * Variablen aus class-abstimmen-freigabe.php:
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
        <button type="button" class="dgptm-freigabe-comments-toggle">
            <span class="dgptm-freigabe-comments-toggle-icon">&#128172;</span>
            <span>Kommentare &amp; Anmerkungen</span>
            <span class="dgptm-freigabe-comments-count"><?php echo count($section_comments); ?></span>
            <span class="dgptm-freigabe-comments-arrow">&#9660;</span>
        </button>
        <div class="dgptm-freigabe-comments-panel" style="display:none;">
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
    <div class="dgptm-freigabe-countdown" id="dgptm-freigabe-countdown" data-deadline="2026-05-31T23:59:59">
        <span class="countdown-icon">&#9200;</span>
        <div>
            <div class="countdown-label">Frist zur Rueckmeldung</div>
            <div class="countdown-value">wird berechnet...</div>
            <div class="countdown-deadline" style="font-size:12px;color:#888;">Deadline: 31. Mai 2026</div>
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
            <h2>DGPTM Abstimmungstool</h2>
            <p class="dgptm-freigabe-subtitle">Funktionsbeschreibung des digitalen Abstimmungs- und Versammlungssystems</p>
            <p class="dgptm-freigabe-meta">DGPTM | Stand: April 2026 | Version 4.3.0 | Zur Freigabe durch den Vorstand</p>
        </div>

        <!-- Abschnitt 1 -->
        <div class="dgptm-freigabe-section" id="section-ueberblick">
            <h3>1. Was ist das Abstimmungstool?</h3>

            <p>Das <strong>DGPTM Abstimmungstool</strong> ist ein umfassendes System fuer Online-Abstimmungen, Teilnehmerverwaltung und Anwesenheitserfassung. Es wurde speziell fuer die Anforderungen der DGPTM entwickelt und deckt den gesamten Ablauf einer Mitgliederversammlung oder eines Webinars digital ab.</p>

            <p>Das System vereint sechs Funktionsbereiche in einem Modul:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Bereich</th><th>Beschreibung</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Voting-System</strong></td><td>Umfragen mit Single-/Multi-Choice-Fragen, Timer, anonymer Stimmabgabe und Mehrheitsberechnung</td></tr>
                    <tr><td><strong>Beamer-Ansicht</strong></td><td>Live-Projektion von Ergebnissen mit Diagrammen (Balken, Kreis, Donut) in Echtzeit</td></tr>
                    <tr><td><strong>Zoom-Integration</strong></td><td>Automatische Meeting-/Webinar-Registrierung, persoenliche Join-URLs, Status-Synchronisation</td></tr>
                    <tr><td><strong>Anwesenheitserfassung</strong></td><td>Live-Tracking von Teilnehmern via Zoom-Webhook mit Join-/Leave-Zeiten</td></tr>
                    <tr><td><strong>Praesenz-Scanner</strong></td><td>QR-Code-/Badge-Scanner fuer physische Anwesenheit bei Praesenzveranstaltungen</td></tr>
                    <tr><td><strong>Export</strong></td><td>Ergebnisse und Teilnehmerlisten als CSV oder PDF exportierbar</td></tr>
                </tbody>
            </table>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Typische Einsatzszenarien:</strong>
                <ul>
                    <li>Ordentliche und ausserordentliche Mitgliederversammlungen (hybrid: Praesenz + Online)</li>
                    <li>Vorstandswahlen und Satzungsaenderungen</li>
                    <li>Webinare mit automatischer Registrierung und Teilnehmernachweis</li>
                    <li>Konferenzen mit QR-Code-basierter Einlasskontrolle</li>
                </ul>
            </div>

            <?php $render_section_comments('section-ueberblick'); ?>
        </div>

        <!-- Abschnitt 2 -->
        <div class="dgptm-freigabe-section" id="section-satzung">
            <h3>2. Bezug zur Satzung</h3>

            <p>Das Abstimmungstool setzt die Vorgaben der DGPTM-Satzung technisch um. Die folgenden Satzungsparagraphen sind fuer den Betrieb des Systems massgeblich:</p>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">&#167; 4 &mdash; Mitgliedschaft und Stimmrecht</div>
                <p>Nur <strong>ordentliche Mitglieder</strong> sind bei Abstimmungen stimmberechtigt. Das System prueft beim Login automatisch die Mitgliedsart (ueber die WordPress-Rolle und Zoho-CRM-Daten) und vergibt das Stimmrecht entsprechend. Foerdernde Mitglieder, Ehrenmitglieder und Gastmitglieder koennen an Versammlungen teilnehmen, aber nicht abstimmen.</p>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">&#167; 7 Abs. 1 &mdash; Jedes ordentliche Mitglied hat eine Stimme</div>
                <p>Das System stellt technisch sicher, dass jedes Mitglied pro Frage <strong>genau eine Stimme</strong> abgeben kann (bei Sachthemen). Eine erneute Stimmabgabe ueberschreibt die vorherige &mdash; oder wird bei anonymen Abstimmungen vollstaendig gesperrt.</p>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">&#167; 7 Abs. 7 &mdash; Wahlgrundsaetze</div>
                <p>Abstimmungen muessen <strong>frei, geheim, gleich, persoenlich und unmittelbar</strong> sein. Das System unterstuetzt:</p>
                <ol>
                    <li><strong>Frei:</strong> Keine Einflussnahme &mdash; Teilnehmer sehen keine Zwischenergebnisse waehrend der Abstimmung</li>
                    <li><strong>Geheim:</strong> Anonyme Abstimmung &mdash; bei aktivierter Anonymitaet wird nur gespeichert, <em>dass</em> jemand gestimmt hat, aber nicht <em>wie</em></li>
                    <li><strong>Gleich:</strong> Jede Stimme zaehlt gleichwertig</li>
                    <li><strong>Persoenlich:</strong> Identifikation ueber Login oder persoenlichen Token-Link</li>
                    <li><strong>Unmittelbar:</strong> Direkte Stimmabgabe ohne Zwischenschritte</li>
                </ol>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">&#167; 7 Abs. 8 &mdash; Mehrheitsberechnung</div>
                <p>Beschluesse werden mit <strong>einfacher Mehrheit der abgegebenen gueltigen Stimmen</strong> gefasst. Stimmenthaltungen gelten als ungueltige Stimmen und werden bei der Berechnung nicht mitgezaehlt. Das System unterstuetzt drei Mehrheitstypen:</p>
                <table class="dgptm-freigabe-table">
                    <thead>
                        <tr><th>Mehrheitstyp</th><th>Schwelle</th><th>Anwendungsfall</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Einfache Mehrheit</td><td>&gt; 50 % der gueltigen Stimmen</td><td>Regulaere Beschluesse</td></tr>
                        <tr><td>Absolute Mehrheit</td><td>&gt; 50 % aller Anwesenden</td><td>Vorstandswahlen (&#167; 7 Abs. 8)</td></tr>
                        <tr><td>Dreiviertelmehrheit</td><td>&ge; 75 % der gueltigen Stimmen</td><td>Satzungsaenderungen (&#167; 14)</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">&#167; 14 &mdash; Satzungsaenderungen</div>
                <p>Satzungsaenderungen erfordern eine <strong>Dreiviertelmehrheit der anwesenden stimmberechtigten Mitglieder</strong>. Das System erkennt den Abstimmungstyp &bdquo;Satzungsaenderung&ldquo; und wendet automatisch die korrekte Mehrheitsregel an.</p>
            </div>

            <div class="dgptm-freigabe-highlight green">
                <strong>Satzungskonformitaet:</strong> Das System berechnet Mehrheiten automatisch nach dem konfigurierten Typ. Bei Personenwahlen mit mehr als zwei Kandidaten wird eine Stichwahl vorgeschlagen, wenn kein Kandidat die absolute Mehrheit erreicht.
            </div>

            <?php $render_section_comments('section-satzung'); ?>
        </div>

        <!-- Abschnitt 3 -->
        <div class="dgptm-freigabe-section" id="section-voting">
            <h3>3. Das Voting-System</h3>

            <p>Das Herzstu&#776;ck des Moduls ist das Voting-System, bestehend aus <strong>Manager-Ansicht</strong> (Steuerung) und <strong>Member-Ansicht</strong> (Teilnahme).</p>

            <h4>Manager-Ansicht: [manage_poll]</h4>

            <p>Die Manager-Ansicht bietet die vollstaendige Kontrolle ueber Umfragen:</p>

            <div class="dgptm-freigabe-flow">
                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Schritt 1: Umfrage anlegen</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">1</span>
                            <div>Umfrage erstellen mit Name und optionalem Logo. <span class="step-actor">Manager</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">2</span>
                            <div>Gaeste-Abstimmung erlauben oder nur fuer eingeloggte Mitglieder. <span class="step-actor">Manager</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Schritt 2: Fragen hinzufuegen</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">3</span>
                            <div>Frage formulieren und <strong>Antwortoptionen</strong> definieren (eine pro Zeile). <span class="step-actor">Manager</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">4</span>
                            <div>Konfiguration: Single-/Multi-Choice, Timer, Anonymitaet, Mehrheitstyp, Quorum, Diagrammtyp. <span class="step-actor">Manager</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Schritt 3: Abstimmung durchfuehren</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">5</span>
                            <div>Frage <strong>aktivieren</strong> &mdash; wird sofort auf der Teilnehmerseite angezeigt. <span class="step-actor">Manager</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">6</span>
                            <div>Optional: Timer starten (Auto-Close nach Ablauf). <span class="step-actor">Manager</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">7</span>
                            <div>Frage <strong>beenden</strong> und Ergebnisse auf dem Beamer freigeben. <span class="step-actor">Manager</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <h4>Abstimmungsseite: [member_vote]</h4>

            <p>Die Teilnehmerseite zeigt automatisch die aktive Frage an und aktualisiert sich laufend:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Funktion</th><th>Beschreibung</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Auto-Refresh</strong></td><td>Neue Fragen erscheinen automatisch (Polling alle 900 ms bei aktiver Frage, 3,5 s bei inaktiver)</td></tr>
                    <tr><td><strong>Auswahl persistent</strong></td><td>Bereits angekreuzte Antworten bleiben bei Seitenaktualisierung erhalten (localStorage)</td></tr>
                    <tr><td><strong>Zugangsarten</strong></td><td>Login, Token-Link oder Namenseingabe (Guest-Modus, wenn aktiviert)</td></tr>
                    <tr><td><strong>Feedback</strong></td><td>Sofortige Rueckmeldung nach Stimmabgabe (Erfolg oder Fehlermeldung)</td></tr>
                    <tr><td><strong>Timer-Anzeige</strong></td><td>Countdown sichtbar, wenn die Frage ein Zeitlimit hat</td></tr>
                </tbody>
            </table>

            <h4>Abstimmungstypen</h4>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Typ</th><th>Beschreibung</th><th>Satzungsbezug</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Sachthema</strong></td><td>Abstimmung ueber einen Antrag (Ja/Nein/Enthaltung oder eigene Optionen)</td><td>&#167; 7 Abs. 8</td></tr>
                    <tr><td><strong>Personenwahl</strong></td><td>Wahl von Personen mit automatischer Stichwahl-Erkennung</td><td>&#167; 7 Abs. 8, &#167; 8</td></tr>
                    <tr><td><strong>Anonyme Abstimmung</strong></td><td>Stimme wird gezaehlt, aber nicht der Person zugeordnet</td><td>&#167; 7 Abs. 7</td></tr>
                </tbody>
            </table>

            <?php $render_section_comments('section-voting'); ?>
        </div>

        <!-- Abschnitt 4 -->
        <div class="dgptm-freigabe-section" id="section-teilnehmer">
            <h3>4. Teilnehmer-Verwaltung</h3>

            <p>Das System identifiziert Teilnehmer ueber drei Wege und speichert sie in einer zentralen Teilnehmertabelle:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Zugangsart</th><th>Identifikation</th><th>Stimmrecht</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>WordPress-Login</strong></td>
                        <td>User-ID, Name, E-Mail aus dem Benutzerkonto</td>
                        <td>Automatisch ueber Mitgliedsart im CRM</td>
                    </tr>
                    <tr>
                        <td><strong>Token-Link</strong></td>
                        <td>Persoenlicher Einladungslink mit eindeutigem Token</td>
                        <td>Durch den Token vorab zugewiesen</td>
                    </tr>
                    <tr>
                        <td><strong>Namenseingabe (Guest)</strong></td>
                        <td>Cookie-basiert, Name wird gespeichert</td>
                        <td>Nur wenn Guest-Voting aktiviert ist</td>
                    </tr>
                </tbody>
            </table>

            <div class="dgptm-freigabe-highlight orange">
                <strong>Hinweis:</strong> Bei satzungsrelevanten Abstimmungen (Mitgliederversammlung) sollte der Guest-Modus <strong>deaktiviert</strong> werden, damit nur identifizierte ordentliche Mitglieder abstimmen koennen (&#167; 4).
            </div>

            <p><strong>Teilnehmer-Tokens:</strong> Der Manager kann im Vorfeld Token generieren und per E-Mail oder QR-Code versenden. Jeder Token ist an eine Umfrage gebunden und identifiziert den Teilnehmer eindeutig.</p>

            <?php $render_section_comments('section-teilnehmer'); ?>
        </div>

        <!-- Abschnitt 5 -->
        <div class="dgptm-freigabe-section" id="section-beamer">
            <h3>5. Beamer-Ansicht (Live-Projektion)</h3>

            <p>Die Beamer-Ansicht (<strong>[beamer_view]</strong>) ist fuer die Projektion bei Versammlungen optimiert und zeigt Abstimmungsergebnisse in Echtzeit:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Modus</th><th>Beschreibung</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>auto</strong></td><td>Zeigt automatisch die aktuell aktive Frage mit Live-Ergebnissen</td></tr>
                    <tr><td><strong>manual</strong></td><td>Zeigt eine bestimmte Frage (per Question-ID gesteuert)</td></tr>
                    <tr><td><strong>results_all</strong></td><td>Zeigt alle freigegebenen Ergebnisse einer Umfrage als Uebersicht</td></tr>
                </tbody>
            </table>

            <p><strong>Diagrammtypen:</strong> Balkendiagramm, Kreisdiagramm und Donut-Diagramm. Der Diagrammtyp ist pro Frage konfigurierbar.</p>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Steuerung:</strong> Der Beamer-Modus wird zentral ueber die Manager-Ansicht gesteuert. Der Manager kann waehrend der Versammlung zwischen den Modi wechseln, ohne den Beamer-Bildschirm zu beruehren.
            </div>

            <div class="dgptm-freigabe-highlight green">
                <strong>Zusatzinhalt:</strong> Der Manager kann eigenen HTML-Inhalt auf dem Beamer einblenden (z.B. Tagesordnung, Pausenhinweis, Willkommensfolie) &mdash; unabhaengig von der Abstimmung.
            </div>

            <?php $render_section_comments('section-beamer'); ?>
        </div>

        <!-- Abschnitt 6 -->
        <div class="dgptm-freigabe-section" id="section-zoom">
            <h3>6. Zoom-Integration</h3>

            <p>Fuer hybride Versammlungen bindet das System <strong>Zoom Meetings oder Webinare</strong> ueber die Server-to-Server OAuth-Schnittstelle an.</p>

            <div class="dgptm-freigabe-flow">
                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Automatische Registrierung</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">1</span>
                            <div>Mitglied klickt den <strong>ON-Button</strong> auf der Website. <span class="step-actor">Mitglied</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">2</span>
                            <div>System registriert automatisch bei Zoom (API-Call). <span class="step-actor">Automatisch</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">3</span>
                            <div>Mitglied erhaelt E-Mail mit <strong>persoenlichem Zoom-Link</strong>. <span class="step-actor">Automatisch</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Synchronisation</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">4</span>
                            <div><strong>Massenabgleich:</strong> Manager kann fehlende Registrierungen nachholen oder ueberschuessige stornieren. <span class="step-actor">Manager</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">5</span>
                            <div><strong>Stornierung:</strong> Klickt ein Mitglied auf OFF, wird die Zoom-Registrierung automatisch zurueckgezogen. <span class="step-actor">Automatisch</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Shortcodes fuer die Zoom-Integration:</strong>
                <ul>
                    <li><strong>[online_abstimmen_button]</strong> &mdash; ON/OFF Toggle fuer die Zoom-Teilnahme</li>
                    <li><strong>[zoom_register_and_join]</strong> &mdash; Registrieren + sofort beitreten</li>
                    <li><strong>[online_abstimmen_zoom_link]</strong> &mdash; Persoenlicher Zoom-Link des Mitglieds</li>
                    <li><strong>[zoom_live_state]</strong> &mdash; Zeigt ob das Meeting gerade laeuft</li>
                </ul>
            </div>

            <?php $render_section_comments('section-zoom'); ?>
        </div>

        <!-- Abschnitt 7 -->
        <div class="dgptm-freigabe-section" id="section-anwesenheit">
            <h3>7. Anwesenheitserfassung</h3>

            <p>Die Anwesenheit wird ueber zwei Kanaele erfasst, die in einer gemeinsamen Datenstruktur zusammenlaufen:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Kanal</th><th>Funktionsweise</th><th>Daten</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Zoom-Webhook</strong></td>
                        <td>Automatisch: Join/Leave-Events in Echtzeit</td>
                        <td>Name, E-Mail, Join-/Leave-Zeit, Sessiondauer</td>
                    </tr>
                    <tr>
                        <td><strong>Praesenz-Scanner</strong></td>
                        <td>Manuell: QR-Code-Scan oder Namenssuche</td>
                        <td>Name, Mitgliedsart, Mitgliedsnummer, manuell-Flag</td>
                    </tr>
                </tbody>
            </table>

            <p>Die Live-Anwesenheitsliste (<strong>[dgptm_presence_table]</strong>) aktualisiert sich automatisch alle 10 Sekunden und zeigt den aktuellen Stand aller Teilnehmer &mdash; unabhaengig davon, ob sie vor Ort oder online teilnehmen.</p>

            <div class="dgptm-freigabe-highlight green">
                <strong>Satzungsrelevanz:</strong> Die Anwesenheitsliste dient als Nachweis der Beschlussfaehigkeit (Quorum). Sie dokumentiert, welche Mitglieder zu welchem Zeitpunkt anwesend waren.
            </div>

            <?php $render_section_comments('section-anwesenheit'); ?>
        </div>

        <!-- Abschnitt 8 -->
        <div class="dgptm-freigabe-section" id="section-scanner">
            <h3>8. Praesenz-Scanner</h3>

            <p>Fuer Praesenzveranstaltungen bietet der Scanner (<strong>[dgptm_presence_scanner]</strong>) zwei Erfassungsmethoden:</p>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">QR-Code-/Badge-Scan</div>
                <ol>
                    <li>Scanner-Seite oeffnen (z.B. auf Tablet am Eingang)</li>
                    <li>Teilnehmer-Badge scannen (Input-Feld empfaengt den Code)</li>
                    <li>Enter druecken &mdash; Teilnehmer wird automatisch erfasst</li>
                    <li>Status aus dem Zoho CRM wird automatisch uebernommen (Mitgliedsart)</li>
                </ol>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">Manuelle Namenssuche</div>
                <ol>
                    <li>Button &bdquo;Manuelle Abfrage&ldquo; klicken</li>
                    <li>Modal oeffnet sich: Name eingeben (automatisch Titlecase)</li>
                    <li>Suchergebnis aus Zoho CRM wird angezeigt</li>
                    <li>Doppelklick auf das Ergebnis uebernimmt den Eintrag sofort</li>
                    <li>Eintrag wird mit &bdquo;Manuell: X&ldquo; markiert</li>
                </ol>
            </div>

            <?php $render_section_comments('section-scanner'); ?>
        </div>

        <!-- Abschnitt 9 -->
        <div class="dgptm-freigabe-section" id="section-export">
            <h3>9. Export und Auswertung</h3>

            <p>Alle Ergebnisse und Teilnehmerlisten koennen exportiert werden:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Export</th><th>Format</th><th>Inhalt</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Abstimmungsergebnisse</strong></td><td>CSV, PDF</td><td>Frage, Antworten, Stimmenzahl, Mehrheitsergebnis</td></tr>
                    <tr><td><strong>Teilnehmerliste</strong></td><td>CSV, PDF</td><td>Name, E-Mail, Code, Zeitstempel</td></tr>
                    <tr><td><strong>Anwesenheitsliste</strong></td><td>CSV, PDF</td><td>Name, E-Mail, Status, Join-/Leave-Zeit, Dauer</td></tr>
                </tbody>
            </table>

            <div class="dgptm-freigabe-highlight blue">
                <strong>PDF-Protokoll:</strong> Die PDF-Exporte enthalten Meeting-Informationen, die vollstaendige Teilnehmerliste mit Zeitstempeln und eine Zusammenfassung &mdash; geeignet als Anlage zum Versammlungsprotokoll.
            </div>

            <?php $render_section_comments('section-export'); ?>
        </div>

        <!-- Abschnitt 10 -->
        <div class="dgptm-freigabe-section" id="section-sicherheit">
            <h3>10. Sicherheit und Berechtigungen</h3>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Bereich</th><th>Massnahme</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Manager-Berechtigung</strong></td><td>Nur Administratoren oder Benutzer mit dem Usermeta-Flag &bdquo;toggle_abstimmungsmanager&ldquo; koennen Umfragen verwalten</td></tr>
                    <tr><td><strong>AJAX-Sicherheit</strong></td><td>Alle Aktionen sind mit WordPress-Nonces geschuetzt</td></tr>
                    <tr><td><strong>Duplikatschutz</strong></td><td>Doppelte Stimmabgaben werden durch User-ID oder Cookie-ID verhindert</td></tr>
                    <tr><td><strong>Zoom-OAuth</strong></td><td>Server-to-Server OAuth 2.0 &mdash; kein Benutzerzugriff auf Zoom-Daten erforderlich</td></tr>
                    <tr><td><strong>Token-Zugang</strong></td><td>Persoenliche Einladungslinks mit kryptographischen Tokens</td></tr>
                    <tr><td><strong>Anonymitaet</strong></td><td>Bei anonymen Abstimmungen wird die IP durch &bdquo;anonymous&ldquo; ersetzt, User-ID auf 0 gesetzt</td></tr>
                </tbody>
            </table>

            <div class="dgptm-freigabe-highlight green">
                <strong>Datenschutz:</strong> Alle Daten werden auf dem eigenen Server (perfusiologie.de) gespeichert. Es werden keine Abstimmungsdaten an externe Dienste uebermittelt. Die Zoom-Integration uebertraegt nur Registrierungsdaten (Name, E-Mail) an Zoom.
            </div>

            <?php $render_section_comments('section-sicherheit'); ?>
        </div>

        <!-- Abschnitt 11 -->
        <div class="dgptm-freigabe-section" id="section-naechste">
            <h3>11. Naechste Schritte</h3>

            <h4>Shortcode-Uebersicht</h4>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Shortcode</th><th>Zweck</th><th>Berechtigung</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>[manage_poll]</strong></td><td>Umfragen verwalten (Erstellen, Fragen, Ergebnisse)</td><td>Manager</td></tr>
                    <tr><td><strong>[member_vote]</strong></td><td>Abstimmungsseite fuer Teilnehmer</td><td>Alle / Login</td></tr>
                    <tr><td><strong>[beamer_view]</strong></td><td>Live-Projektion auf dem Beamer</td><td>Manager</td></tr>
                    <tr><td><strong>[online_abstimmen_button]</strong></td><td>ON/OFF Toggle fuer Zoom-Teilnahme</td><td>Eingeloggt</td></tr>
                    <tr><td><strong>[dgptm_presence_table]</strong></td><td>Live-Anwesenheitsliste</td><td>Manager</td></tr>
                    <tr><td><strong>[dgptm_presence_scanner]</strong></td><td>QR-/Badge-Scanner</td><td>Manager</td></tr>
                    <tr><td><strong>[dgptm_registration_monitor]</strong></td><td>Live-Monitor fuer Zoom-Registrierungen</td><td>Manager</td></tr>
                </tbody>
            </table>

            <h4>Vor dem naechsten Einsatz zu klaeren</h4>

            <div class="dgptm-freigabe-highlight orange">
                <strong>Konfigurationsbedarf:</strong>
                <ul>
                    <li>Zoom-Zugangsdaten (S2S OAuth: Account ID, Client ID, Client Secret) eintragen</li>
                    <li>Webhook-Endpunkt in Zoom konfigurieren fuer Anwesenheitstracking</li>
                    <li>Manager-Rechte an die zustaendigen Vorstandsmitglieder vergeben</li>
                    <li>E-Mail-Templates fuer Einladungen und Bestaetigungen anpassen</li>
                    <li>Entscheidung: Guest-Voting fuer MV deaktivieren (satzungskonform)?</li>
                    <li>Praesenz-Scanner mit Zoho-CRM-Webhook verbinden</li>
                </ul>
            </div>

            <?php $render_section_comments('section-naechste'); ?>
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
