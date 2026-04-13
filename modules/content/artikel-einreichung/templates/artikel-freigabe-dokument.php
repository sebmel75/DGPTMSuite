<?php
/**
 * Template: Interaktives Freigabe-Dokument fuer das Artikel-Einreichungssystem.
 *
 * Variablen aus class-artikel-freigabe.php:
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
    <div class="dgptm-freigabe-countdown" id="dgptm-freigabe-countdown" data-deadline="2026-05-12T23:59:59">
        <span class="countdown-icon">&#9200;</span>
        <div>
            <div class="countdown-label">Frist zur Rueckmeldung</div>
            <div class="countdown-value">wird berechnet...</div>
            <div class="countdown-deadline" style="font-size:12px;color:#888;">Deadline: 12. Mai 2026</div>
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
            <h2>Digitales Artikel-Einreichungssystem</h2>
            <p class="dgptm-freigabe-subtitle">Konzept zur Digitalisierung des Einreichungs- und Begutachtungsverfahrens der Fachzeitschrift &bdquo;Die Perfusiologie&ldquo;</p>
            <p class="dgptm-freigabe-meta">DGPTM | Stand: April 2026 | Zur Freigabe durch die Redaktion</p>
        </div>

        <!-- Abschnitt 1 -->
        <div class="dgptm-freigabe-section" id="section-was-ist">
            <h3>1. Was ist das Einreichungssystem?</h3>

            <p>Die DGPTM gibt die Fachzeitschrift <strong>&bdquo;Die Perfusiologie&ldquo;</strong> heraus. Bisher wurden Artikel per E-Mail eingereicht und manuell bearbeitet &mdash; ein aufwendiges und fehleranfaelliges Verfahren.</p>

            <p>Das neue System <strong>digitalisiert den gesamten Prozess</strong>: von der Einreichung ueber die Begutachtung bis zur endgueltigen Entscheidung und Publikation. Alles findet auf einer zentralen Plattform statt, jeder Schritt ist nachvollziehbar dokumentiert.</p>

            <p><strong>Unterstuetzte Artikeltypen:</strong></p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Artikeltyp</th><th>Beschreibung</th></tr>
                </thead>
                <tbody>
                    <tr><td>Originalarbeit</td><td>Eigene Forschungsergebnisse mit Methodik und Auswertung</td></tr>
                    <tr><td>Uebersichtsarbeit</td><td>Zusammenfassung und Bewertung bestehender Literatur</td></tr>
                    <tr><td>Fallbericht</td><td>Beschreibung eines besonderen klinischen Falls</td></tr>
                    <tr><td>Kurzmitteilung</td><td>Kurzgefasste Beobachtung oder vorlaeufiges Ergebnis</td></tr>
                    <tr><td>Kommentar</td><td>Fachliche Stellungnahme zu einem veroeffentlichten Beitrag</td></tr>
                    <tr><td>Editorial</td><td>Einfuehrender Beitrag eines Redaktionsmitglieds</td></tr>
                    <tr><td>Tutorial</td><td>Schritt-fuer-Schritt-Anleitung zu einer Methode oder Technik</td></tr>
                </tbody>
            </table>

            <?php $render_section_comments('section-was-ist'); ?>
        </div>

        <!-- Abschnitt 2 -->
        <div class="dgptm-freigabe-section" id="section-rollen">
            <h3>2. Wer ist beteiligt?</h3>

            <p>Im Einreichungssystem gibt es vier klar definierte Rollen:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Rolle</th><th>Aufgabe</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Autor/in</strong></td>
                        <td>Reicht den Artikel ein, verfolgt den Bearbeitungsstatus, laed Ueberarbeitungen hoch</td>
                    </tr>
                    <tr>
                        <td><strong>Gutachter/in</strong></td>
                        <td>Bewertet den Artikel anonym und gibt eine fachliche Empfehlung ab (Annehmen / Ueberarbeiten / Ablehnen)</td>
                    </tr>
                    <tr>
                        <td><strong>Redaktion</strong></td>
                        <td>Prueft die formale Vollstaendigkeit der Einreichung, steuert den Workflow, uebernimmt das Lektorat</td>
                    </tr>
                    <tr>
                        <td><strong>Chefredakteur/in</strong></td>
                        <td>Weist Gutachter zu, trifft die redaktionelle Entscheidung, veroeffentlicht den Artikel</td>
                    </tr>
                </tbody>
            </table>

            <?php $render_section_comments('section-rollen'); ?>
        </div>

        <!-- Abschnitt 3 -->
        <div class="dgptm-freigabe-section" id="section-ablauf">
            <h3>3. Der Ablauf im Ueberblick</h3>

            <p>Der gesamte Prozess gliedert sich in sieben Phasen &mdash; von der Einreichung bis zur Veroeffentlichung:</p>

            <div class="dgptm-freigabe-flow">

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 1: Einreichung</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">1</span>
                            <div>Autor oeffnet das <strong>Einreichungsformular</strong> auf perfusiologie.de. <span class="step-actor">Autor/in</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">2</span>
                            <div>Vierstufiges Formular: <strong>Titel &amp; Artikeltyp</strong> &rarr; <strong>Autorendaten</strong> (mit ORCID-Lookup) &rarr; <strong>Zusammenfassungen &amp; Schluesselwoerter</strong> &rarr; <strong>Dokument-Upload</strong>. <span class="step-actor">Autor/in</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">3</span>
                            <div>Erklaerungen zu Interessenkonflikten, Foerderung und Ethikvotum werden im Formular bestaetigt. <span class="step-actor">Autor/in</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">4</span>
                            <div>System sendet automatisch eine <strong>Eingangsbestaetigung</strong> mit Einreichungsnummer an den Autor. <span class="step-actor">Automatisch</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 2: Formale Pruefung</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">5</span>
                            <div>Redaktion prueft die <strong>Vollstaendigkeit</strong>: Pflichtfelder, Dokumentformate, abgegebene Erklaerungen. <span class="step-actor">Redaktion</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">6</span>
                            <div>Bei Maengeln: <strong>Rueckfrage an den Autor</strong> per E-Mail. Bei Bestehen: Weiterleitung zur Begutachtung. <span class="step-actor">Redaktion</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 3: Gutachter-Zuweisung</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">7</span>
                            <div>Chefredakteur weist <strong>1&ndash;2 Gutachter</strong> aus dem vorhandenen Pool oder dem Zoho CRM zu. <span class="step-actor">Chefredakteur/in</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">8</span>
                            <div>Gutachter erhalten eine <strong>E-Mail mit sicherem Zugangslink</strong> (Token). Ein eigenes Benutzerkonto ist <em>nicht</em> erforderlich. <span class="step-actor">Automatisch</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 4: Peer Review (Begutachtung)</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">9</span>
                            <div>Gutachter lesen <strong>Manuskript und Zusammenfassungen</strong> und laden alle Anhaenge herunter. <span class="step-actor">Gutachter/in</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">10</span>
                            <div>Strukturiertes <strong>Gutachten</strong>: Freitext mit Leitfragen plus Empfehlung (4 Optionen: Annehmen / Kleinere Ueberarbeitung / Groessere Ueberarbeitung / Ablehnen). <span class="step-actor">Gutachter/in</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">11</span>
                            <div><strong>Single-Blind-Verfahren:</strong> Die Identitaet der Gutachter bleibt dem Autor verborgen. Beide Gutachten werden dem Chefredakteur vorgelegt. <span class="step-actor">Automatisch</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 5: Redaktionelle Entscheidung</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">12</span>
                            <div>Chefredakteur prueft die Gutachten und trifft eine <strong>Entscheidung</strong>: Annehmen, Ueberarbeitung erforderlich oder Ablehnen. <span class="step-actor">Chefredakteur/in</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">13</span>
                            <div>Der Autor erhaelt einen <strong>Entscheidungsbrief</strong> mit den Gutachter-Kommentaren (aber ohne Namen der Gutachter). <span class="step-actor">Automatisch</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 6: Ueberarbeitung (falls erforderlich)</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">14</span>
                            <div>Autor laed das <strong>ueberarbeitete Manuskript</strong> mit Aenderungsmarkierungen hoch. <span class="step-actor">Autor/in</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">15</span>
                            <div>Autor verfasst eine <strong>&bdquo;Antwort an die Gutachter&ldquo;</strong> mit Punkt-fuer-Punkt-Erklaerung der vorgenommenen Aenderungen. <span class="step-actor">Autor/in</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">16</span>
                            <div>Chefredakteur bewertet die Revision und entscheidet ueber Annahme oder weitere Runde. <span class="step-actor">Chefredakteur/in</span></div>
                        </div>
                    </div>
                </div>

                <div class="dgptm-freigabe-flow-arrow">&darr;</div>

                <div class="dgptm-freigabe-phase">
                    <div class="dgptm-freigabe-phase-header">Phase 7: Publikation</div>
                    <div class="dgptm-freigabe-phase-body">
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">17</span>
                            <div>Nach Annahme: <strong>Lektorat und Satz</strong> durch die Redaktion. <span class="step-actor">Redaktion</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">18</span>
                            <div>Artikel wird in der Fachzeitschrift <strong>veroeffentlicht</strong>. <span class="step-actor">Chefredakteur/in</span></div>
                        </div>
                        <div class="dgptm-freigabe-step">
                            <span class="step-num">19</span>
                            <div>Autor erhaelt eine <strong>Bestaetigung</strong> mit Link zur Publikation. <span class="step-actor">Automatisch</span></div>
                        </div>
                    </div>
                </div>

            </div>

            <?php $render_section_comments('section-ablauf'); ?>
        </div>

        <!-- Abschnitt 4 -->
        <div class="dgptm-freigabe-section" id="section-formular">
            <h3>4. Das Einreichungsformular</h3>

            <p>Das Formular fuehrt Autoren in vier uebersichtlichen Schritten durch die Einreichung:</p>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">Schritt 1: Titel und Artikeltyp</div>
                <ol>
                    <li>Vollstaendiger Titel des Artikels eingeben</li>
                    <li>Optionalen Untertitel ergaenzen</li>
                    <li>Artikeltyp aus der Liste auswaehlen</li>
                </ol>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">Schritt 2: Autorendaten</div>
                <ol>
                    <li>ORCID-ID eingeben &mdash; Name und Institution werden <strong>automatisch ergaenzt</strong></li>
                    <li>Koautoren mit ihren ORCID-IDs hinzufuegen</li>
                    <li>Korrespondenzautor festlegen</li>
                </ol>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">Schritt 3: Zusammenfassungen und Metadaten</div>
                <ol>
                    <li>Deutsche Zusammenfassung (Abstract) verfassen</li>
                    <li>Englische Zusammenfassung (Abstract) verfassen</li>
                    <li>Schluesselwoerter in Deutsch und Englisch eingeben</li>
                    <li>Drei Kernaussagen als &bdquo;Highlights&ldquo; formulieren</li>
                    <li>Literaturverzeichnis einfuegen</li>
                </ol>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">Schritt 4: Dokument-Upload</div>
                <ol>
                    <li>Manuskript als Word- oder PDF-Datei hochladen</li>
                    <li>Abbildungen und Tabellen als separate Dateien hochladen</li>
                    <li>Optionales Zusatzmaterial anhaengen</li>
                </ol>
            </div>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Erklaerungen:</strong> Am Ende des Formulars werden drei Erklaerungen abgegeben:
                <ul>
                    <li>Interessenkonflikt-Erklaerung</li>
                    <li>Foerderhinweise (Drittmittel, Sponsoren)</li>
                    <li>Ethikvotum (falls zutreffend)</li>
                </ul>
            </div>

            <?php $render_section_comments('section-formular'); ?>
        </div>

        <!-- Abschnitt 5 -->
        <div class="dgptm-freigabe-section" id="section-gutachtenbogen">
            <h3>5. Der Begutachtungsbogen</h3>

            <p>Gutachter erhalten ueber einen sicheren Zugangslink Einblick in die Einreichung und fuellen anschliessend einen strukturierten Bogen aus.</p>

            <h4>Was Gutachter sehen</h4>

            <div class="dgptm-freigabe-highlight blue">
                <ul>
                    <li>Artikeltyp, Titel, deutsche und englische Zusammenfassung, Schluesselwoerter</li>
                    <li>Download-Links fuer Manuskript und alle Anhaenge</li>
                    <li><em>Keine</em> Autorenangaben &mdash; Single-Blind-Verfahren</li>
                </ul>
            </div>

            <h4>Was Gutachter bewerten</h4>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">Freitext-Gutachten</div>
                <ol>
                    <li>Wissenschaftliche Qualitaet und Originalitaet</li>
                    <li>Neuheit und Relevanz fuer das Fachgebiet</li>
                    <li>Methodik und Auswertung</li>
                    <li>Klarheit von Struktur und Darstellung</li>
                    <li>Klinische oder praktische Relevanz</li>
                    <li>Angemessenheit der Literatur</li>
                    <li>Qualitaet von Abbildungen und Tabellen</li>
                </ol>
            </div>

            <div class="dgptm-freigabe-rubrik">
                <div class="rubrik-title">Empfehlung (Pflichtauswahl)</div>
                <ol>
                    <li>Annehmen ohne Aenderungen</li>
                    <li>Annehmen nach kleineren Ueberarbeitungen</li>
                    <li>Neueinreichung nach groesseren Ueberarbeitungen</li>
                    <li>Ablehnen</li>
                </ol>
            </div>

            <?php $render_section_comments('section-gutachtenbogen'); ?>
        </div>

        <!-- Abschnitt 6 -->
        <div class="dgptm-freigabe-section" id="section-datenschutz">
            <h3>6. Datenschutz und Sicherheit</h3>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Token-basierter Zugang:</strong> Externe Gutachter brauchen kein eigenes Benutzerkonto. Sie erhalten einen persoenlichen Einmal-Link, der nach Nutzung verfaellt.
            </div>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Datenspeicherung:</strong> Alle Daten liegen auf dem eigenen Server (perfusiologie.de) in Deutschland. Es werden keine Daten an Drittanbieter weitergegeben.
            </div>

            <div class="dgptm-freigabe-highlight blue">
                <strong>Single-Blind-Verfahren:</strong> Die Identitaet der Gutachter bleibt dem Autor vollstaendig verborgen. Nur der Chefredakteur kennt alle Beteiligten.
            </div>

            <div class="dgptm-freigabe-highlight green">
                <strong>Automatische Bereinigung:</strong> Abgelaufene Zugangs-Tokens werden regelmaessig automatisch geloescht. Eingeladene Gutachter, die nicht reagieren, verlieren ihren Zugang nach einer konfigurierbaren Frist.
            </div>

            <?php $render_section_comments('section-datenschutz'); ?>
        </div>

        <!-- Abschnitt 7 -->
        <div class="dgptm-freigabe-section" id="section-status">
            <h3>7. Status-Uebersicht</h3>

            <p>Jeder Artikel durchlaeuft verschiedene Status-Stufen. Die folgende Tabelle erklaert, was jeder Status bedeutet:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Status</th><th>Was bedeutet das?</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Eingereicht</strong></td><td>Der Autor hat den Artikel abgeschickt. Die Redaktion hat ihn noch nicht gesehen.</td></tr>
                    <tr><td><strong>Formale Pruefung</strong></td><td>Die Redaktion prueft, ob alle Unterlagen vollstaendig und korrekt sind.</td></tr>
                    <tr><td><strong>In Begutachtung</strong></td><td>Gutachter wurden zugewiesen und bearbeiten den Artikel gerade.</td></tr>
                    <tr><td><strong>Ueberarbeitung erforderlich</strong></td><td>Die Gutachter haben Aenderungswuensche. Der Autor muss den Artikel ueberarbeiten.</td></tr>
                    <tr><td><strong>Angenommen</strong></td><td>Der Artikel wurde zur Veroeffentlichung angenommen.</td></tr>
                    <tr><td><strong>Abgelehnt</strong></td><td>Der Artikel wird nicht veroeffentlicht. Der Autor erhaelt eine Begruendung.</td></tr>
                    <tr><td><strong>Revision eingereicht</strong></td><td>Der Autor hat das ueberarbeitete Manuskript hochgeladen.</td></tr>
                    <tr><td><strong>Exportiert</strong></td><td>Der angenommene Artikel wurde fuer das Lektorat vorbereitet und weitergeleitet.</td></tr>
                    <tr><td><strong>Lektorat</strong></td><td>Die Redaktion prueft Sprache und Stil des Artikels.</td></tr>
                    <tr><td><strong>Gesetzt</strong></td><td>Der Artikel wurde typografisch fuer den Druck aufbereitet.</td></tr>
                    <tr><td><strong>Veroeffentlicht</strong></td><td>Der Artikel ist erschienen und in der Zeitschrift zu lesen.</td></tr>
                </tbody>
            </table>

            <?php $render_section_comments('section-status'); ?>
        </div>

        <!-- Abschnitt 8 -->
        <div class="dgptm-freigabe-section" id="section-einstellungen">
            <h3>8. Konfigurierbare Einstellungen</h3>

            <p>Folgende Bereiche koennen ohne Programmieraenderungen angepasst werden:</p>

            <table class="dgptm-freigabe-table">
                <thead>
                    <tr><th>Bereich</th><th>Was ist anpassbar?</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Gutachter-Pool</strong></td>
                        <td>Gutachter koennen im Backend verwaltet werden: Hinzufuegen, Bearbeiten, Fachgebiete hinterlegen</td>
                    </tr>
                    <tr>
                        <td><strong>E-Mail-Vorlagen</strong></td>
                        <td>Alle automatischen E-Mails (Eingangsbestaetigung, Gutachtereinladung, Entscheidungsbrief) koennen im Wortlaut angepasst werden</td>
                    </tr>
                    <tr>
                        <td><strong>SharePoint-Anbindung</strong></td>
                        <td>Optionaler Export der Manuskripte in einen SharePoint-Ordner fuer die Redaktionsarbeit</td>
                    </tr>
                    <tr>
                        <td><strong>ORCID-Integration</strong></td>
                        <td>Automatisches Nachschlagen von Autorenname und Institution ueber die ORCID-Datenbank</td>
                    </tr>
                </tbody>
            </table>

            <?php $render_section_comments('section-einstellungen'); ?>
        </div>

        <!-- Abschnitt 9 -->
        <div class="dgptm-freigabe-section" id="section-naechste-schritte">
            <h3>9. Naechste Schritte</h3>

            <h4>Testseiten zum Ausprobieren</h4>

            <div class="dgptm-freigabe-highlight green">
                <strong>Die folgenden Links fuehren zu den Testseiten des Systems.</strong> Sie koennen sich dort selbst einen Eindruck vom aktuellen Stand verschaffen:
                <ul style="margin:10px 0 0 20px;list-style:none;padding:0;">
                    <li style="margin-bottom:8px;">&#128221; <a href="https://perfusiologie.de/fachzeitschrift/artikel-einreichen" target="_blank" style="color:#1565c0;font-weight:600;">Einreichungsformular</a> &mdash; So sieht das Formular fuer Autoren aus</li>
                    <li style="margin-bottom:8px;">&#128188; <a href="https://perfusiologie.de/fachzeitschrift/editor-in-chief/" target="_blank" style="color:#1565c0;font-weight:600;">Editor-in-Chief Dashboard</a> &mdash; Sicht des Chefredakteurs (Gutachter zuweisen, Entscheidungen treffen)</li>
                    <li style="margin-bottom:8px;">&#128100; <a href="https://perfusiologie.de/fachzeitschrift/artikel-dashboard/?autor_token=a41e933936a5105aff2ba210e0da252c72d85f2e65c04f9bdff9d5da0f36294a" target="_blank" style="color:#1565c0;font-weight:600;">Autoren-Dashboard</a> &mdash; So sieht der Autor den Status seiner Einreichung</li>
                    <li style="margin-bottom:8px;">&#128240; <a href="https://perfusiologie.de/fachzeitschrift/redaktion/" target="_blank" style="color:#1565c0;font-weight:600;">Redaktions-Dashboard</a> &mdash; Sicht der Redaktion (formale Pruefung, Workflow)</li>
                </ul>
            </div>

            <h4>Weitere Schritte</h4>

            <ol class="dgptm-freigabe-steps-list">
                <li><strong>Freigabe durch die Redaktion</strong> &mdash; Dieses Konzeptdokument pruefen und freigeben</li>
                <li><strong>E-Mail-Vorlagen konfigurieren</strong> &mdash; Texte fuer alle automatischen Benachrichtigungen festlegen</li>
                <li><strong>Gutachter-Pool aufbauen</strong> &mdash; Externe Gutachter im System hinterlegen und Fachgebiete zuordnen</li>
                <li><strong>Testphase mit einem Probelauf</strong> &mdash; Einen Musterartikel durch den gesamten Prozess fuehren</li>
                <li><strong>Go-Live fuer die naechste Ausgabe</strong> &mdash; Aktivierung des Systems fuer echte Einreichungen</li>
            </ol>

            <div class="dgptm-freigabe-highlight orange">
                <strong>Vor Go-Live zu klaeren:</strong>
                <ul>
                    <li>Datenschutzerklaerung auf der Homepage um den Abschnitt zur Artikel-Einreichung ergaenzen</li>
                    <li>Redaktionelle Zustaendigkeiten fuer die formale Pruefung festlegen</li>
                    <li>Gutachter-Pool mit mindestens 10 Personen besetzen</li>
                    <li>Chefredakteur im System als Benutzer einrichten</li>
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
