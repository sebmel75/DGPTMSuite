<?php
/**
 * Template: Entscheidungsvorlage Workshop-Buchung-Modul.
 *
 * Variablen aus class-entscheidungsvorlage.php:
 *   $approvals      — Array aller Freigaben
 *   $comments       — Array aller Kommentare
 *   $user_approved  — bool
 *   $user           — WP_User
 *   $sections       — array<section_id => Label>
 */
if (!defined('ABSPATH')) exit;

$current_user_id = $user->ID;
$approval_count  = count($approvals);

$render_section_comments = function ($section_id) use ($comments, $current_user_id) {
    $section_comments = array_filter($comments, function ($c) use ($section_id) {
        return $c['section'] === $section_id;
    });
    ?>
    <div class="dgptm-wsb-evl-comments-block" data-section="<?php echo esc_attr($section_id); ?>">
        <button type="button" class="dgptm-wsb-evl-comments-toggle">
            <span class="dgptm-wsb-evl-comments-toggle-icon">&#128172;</span>
            <span>Kommentare &amp; Anmerkungen</span>
            <span class="dgptm-wsb-evl-comments-count"><?php echo count($section_comments); ?></span>
            <span class="dgptm-wsb-evl-comments-arrow">&#9660;</span>
        </button>
        <div class="dgptm-wsb-evl-comments-panel" style="display:none;">
            <div class="dgptm-wsb-evl-comments-list">
                <?php foreach ($section_comments as $c) :
                    $can_delete = ((int) $c['user_id'] === $current_user_id) || current_user_can('manage_options');
                    $is_read    = !empty($c['status']) && $c['status'] === 'eingearbeitet';
                    $ts         = date_i18n('d.m.Y, H:i', strtotime($c['timestamp']));
                    $comment_class = 'dgptm-wsb-evl-comment' . ($is_read ? ' dgptm-wsb-evl-comment-read' : '');
                ?>
                    <div class="<?php echo $comment_class; ?>" data-comment-id="<?php echo esc_attr($c['id']); ?>">
                        <div class="dgptm-wsb-evl-comment-meta">
                            <strong><?php echo esc_html($c['user_name']); ?></strong>
                            <span class="dgptm-wsb-evl-comment-date"><?php echo esc_html($ts); ?></span>
                            <?php if ($is_read) : ?>
                                <span class="dgptm-wsb-evl-badge-eingearbeitet">eingearbeitet</span>
                            <?php endif; ?>
                            <?php if ($can_delete && !$is_read) : ?>
                                <button type="button" class="dgptm-wsb-evl-comment-delete" data-id="<?php echo esc_attr($c['id']); ?>" title="Kommentar loeschen">&times;</button>
                            <?php endif; ?>
                            <?php if (current_user_can('manage_options') && !$is_read) : ?>
                                <button type="button" class="dgptm-wsb-evl-comment-mark-read" data-id="<?php echo esc_attr($c['id']); ?>" title="Als eingearbeitet markieren">&#10003; eingearbeitet</button>
                            <?php endif; ?>
                        </div>
                        <div class="dgptm-wsb-evl-comment-text"><?php echo nl2br(esc_html($c['text'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="dgptm-wsb-evl-comment-form">
                <textarea class="dgptm-wsb-evl-comment-input" rows="2" placeholder="Kommentar zu diesem Abschnitt..."></textarea>
                <button type="button" class="dgptm-wsb-evl-comment-submit">Kommentar senden</button>
            </div>
        </div>
    </div>
    <?php
};
?>

<div class="dgptm-wsb-evl-wrapper">

    <!-- Status-Banner -->
    <div class="dgptm-wsb-evl-status-banner">
        <div class="dgptm-wsb-evl-status-info">
            <span class="dgptm-wsb-evl-status-icon"><?php echo $approval_count > 0 ? '&#9989;' : '&#9898;'; ?></span>
            <strong><?php echo $approval_count; ?> Freigabe<?php echo $approval_count !== 1 ? 'n' : ''; ?></strong> erteilt
        </div>
        <?php if (!$user_approved) : ?>
            <button type="button" class="dgptm-wsb-evl-approve-btn" id="dgptm-wsb-evl-approve">
                Entscheidungsvorlage freigeben
            </button>
        <?php else : ?>
            <div class="dgptm-wsb-evl-approved-info">
                Du hast freigegeben
                <button type="button" class="dgptm-wsb-evl-revoke-btn" id="dgptm-wsb-evl-revoke">
                    Freigabe zurueckziehen
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($approvals)) : ?>
    <div class="dgptm-wsb-evl-approvals-list">
        <?php foreach ($approvals as $a) : ?>
            <div class="dgptm-wsb-evl-approval-item">
                <span class="dgptm-wsb-evl-approval-check">&#10003;</span>
                <strong><?php echo esc_html($a['user_name']); ?></strong>
                <span class="dgptm-wsb-evl-approval-date"><?php echo date_i18n('d.m.Y, H:i', strtotime($a['timestamp'])); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════ -->
    <div class="dgptm-wsb-evl-dokument">

        <div class="dgptm-wsb-evl-header">
            <h2>Workshops online buchbar machen &mdash; Entscheidungsvorlage</h2>
            <p class="dgptm-wsb-evl-subtitle">Was wird gebaut? Was muss noch entschieden werden?</p>
            <p class="dgptm-wsb-evl-meta">DGPTM | Stand: 22.04.2026 | Empfaenger:innen: Vorstand, Geschaeftsstelle, Kursleitungen</p>
        </div>

        <div class="dgptm-wsb-evl-highlight blue">
            <strong>Worum dich dieses Dokument bittet:</strong>
            <ol style="margin:6px 0 0 18px;">
                <li>Lies die geplanten Entscheidungen (Abschnitt 3) und die offenen Fragen (Abschnitt 10).</li>
                <li>Hinterlasse pro Abschnitt einen Kommentar, wenn du etwas anders entscheiden moechtest.</li>
                <li>Klicke auf <em>&bdquo;Entscheidungsvorlage freigeben&ldquo;</em>, sobald du einverstanden bist.</li>
            </ol>
        </div>

        <!-- ───── Abschnitt 1 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-ziel">
            <h3>1. Worum geht es?</h3>
            <p>Wir wollen unsere <strong>Workshops online buchbar</strong> machen &mdash; inklusive direkter Bezahlung. Bisher laufen Anmeldung und Bezahlung ueber E-Mail an die Geschaeftsstelle und manuelle Rechnungen.</p>
            <p>Mit dem neuen Modul soll Folgendes moeglich werden:</p>
            <ul>
                <li>Workshops, die bereits intern gepflegt sind, erscheinen automatisch auf der Webseite.</li>
                <li>Mitglieder und Gaeste koennen mit wenigen Klicks anmelden.</li>
                <li>Die Bezahlung laeuft sicher ueber unseren bestehenden Zahlungsanbieter.</li>
                <li>Die Anmeldung wird automatisch ins Mitglieder-System eingetragen.</li>
                <li>Der/die Teilnehmer:in erhaelt sofort eine Bestaetigung mit Termin fuer den Kalender.</li>
            </ul>
            <p><em>Hinweis:</em> Das Modul ist der erste Baustein. Spaeter sollen Webinare und Kongresse mit derselben Mechanik buchbar werden.</p>
            <?php $render_section_comments('section-ziel'); ?>
        </div>

        <!-- ───── Abschnitt 2 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-ausgangslage">
            <h3>2. Was haben wir heute?</h3>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>Heute vorhanden</th><th>Was fehlt</th></tr></thead>
                <tbody>
                    <tr><td>Workshops sind im internen Mitglieder-System gepflegt.</td><td>Sie sind nicht online buchbar.</td></tr>
                    <tr><td>Auf der Webseite werden Workshops bereits als Liste angezeigt (Edugrant-Modul).</td><td>Eine Buchung von dort aus ist nicht moeglich.</td></tr>
                    <tr><td>Bezahlung per Kreditkarte/SEPA gibt es bereits an anderer Stelle.</td><td>Diese ist an alte Formulare gekoppelt und nicht fuer Workshops nutzbar.</td></tr>
                    <tr><td>Webinare laufen ueber eine eigene, fertige Loesung.</td><td>Sie hat keine Anmelde- und Buchungslogik &mdash; das war auch nicht ihr Zweck.</td></tr>
                </tbody>
            </table>
            <p><strong>Konsequenz heute:</strong> Wer einen Workshop buchen moechte, schreibt eine E-Mail an die Geschaeftsstelle. Bezahlung folgt per Rechnung. Das ist viel manueller Aufwand und unbequem fuer Teilnehmer:innen.</p>
            <?php $render_section_comments('section-ausgangslage'); ?>
        </div>

        <!-- ───── Abschnitt 3 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-entscheidungen">
            <h3>3. Was wurde wie entschieden?</h3>
            <p>Diese Entscheidungen liegen dem geplanten Modul zugrunde. Stimmst du nicht zu, hinterlass bitte einen Kommentar im jeweiligen Abschnitt.</p>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>#</th><th>Frage</th><th>Entscheidung</th><th>Warum</th></tr></thead>
                <tbody>
                    <tr><td>1</td><td><strong>Wie gross soll das Modul sein?</strong></td><td>Erst nur Workshops. Bausteine werden so gebaut, dass Webinare/Kongresse spaeter daran andocken koennen.</td><td>Schritt fuer Schritt &mdash; kein Mammut-Projekt, aber zukunftsfaehig.</td></tr>
                    <tr><td>2</td><td><strong>Muessen Buchende eingeloggt sein?</strong></td><td>Beides: Mitglieder buchen mit einem Klick. Gaeste fuellen ein kurzes Formular aus.</td><td>Maximal einfach fuer Mitglieder, gleichzeitig offen fuer Externe.</td></tr>
                    <tr><td>3</td><td><strong>Wie wird bezahlt?</strong></td><td>Ueber Stripe (gleicher Anbieter wie heute). Die Bezahlseite laeuft direkt bei Stripe &mdash; wir beruehren keine Kreditkartendaten.</td><td>Sicher (internationaler Sicherheitsstandard), Apple Pay und SEPA-Lastschrift sind automatisch dabei.</td></tr>
                    <tr><td>4</td><td><strong>Was, wenn jemand mehrere Plaetze bucht?</strong></td><td>Pro Person ein eigener Eintrag mit eigenen Kontaktdaten.</td><td>Damit jede:r Teilnehmer:in eine eigene Bestaetigung und ggf. Fortbildungspunkte erhaelt.</td></tr>
                    <tr><td>5</td><td><strong>Welche Daten werden erfasst?</strong></td><td>Pflicht: Vor- und Nachname, E-Mail. Adresse nur, wenn benoetigt. Bei bekannten Mitgliedern werden vorhandene Daten automatisch eingeblendet.</td><td>So wenig wie moeglich, so viel wie noetig.</td></tr>
                    <tr><td>6</td><td><strong>Wie erkennt das System Bestandsmitglieder?</strong></td><td>Es prueft alle bekannten E-Mail-Adressen. Findet es niemanden, wird ein neuer Kontakt angelegt.</td><td>Keine Doppel-Eintraege, keine Karteileichen.</td></tr>
                    <tr><td>7</td><td><strong>Wie wird die Buchung in die Webseite eingebunden?</strong></td><td>Ueber zwei Platzhalter, die die Geschaeftsstelle frei auf jeder Seite einbauen kann.</td><td>Volle Gestaltungsfreiheit fuer die Geschaeftsstelle.</td></tr>
                    <tr><td>8</td><td><strong>Was, wenn ein Workshop voll ist?</strong></td><td>Automatische Warteliste. Wird ein Platz frei, hat die naechste Person 24 Stunden Zeit zum Buchen.</td><td>Faire Reihenfolge ohne dauerhafte Sperre.</td></tr>
                    <tr><td>9</td><td><strong>Storno durch Teilnehmer:in?</strong></td><td>Bis zu einer Frist (Vorschlag: 14 Tage vor dem Workshop) selbst moeglich, Geld kommt automatisch zurueck. Danach nur ueber die Geschaeftsstelle.</td><td>Entlastet die Geschaeftsstelle, bleibt im Rahmen unserer AGB.</td></tr>
                    <tr><td>10</td><td><strong>Welche E-Mails verschickt das System?</strong></td><td>Bestaetigung, Warteliste-Info, Nachrueck-Einladung, Storno-Bestaetigung &mdash; sofort und automatisch. Erinnerungen und Werbung weiter ueber das bestehende Marketing-Tool.</td><td>Wichtige Mails sofort. Marketing-Inhalte bleiben flexibel.</td></tr>
                    <tr><td>11</td><td><strong>Rabattcodes?</strong></td><td>Direkt ueber Stripe verwaltet &mdash; keine extra Pflege in unserem System.</td><td>Spart Aufwand. Es gibt nur eine Stelle, an der Codes gepflegt werden.</td></tr>
                    <tr><td>12</td><td><strong>Wie wird das Modul gebaut?</strong></td><td>Mit austauschbaren Bausteinen (siehe Abschnitt 4), damit Webinar- und Kongress-Buchung spaeter denselben Kern nutzen koennen.</td><td>Spart bei den naechsten Modulen Zeit und Geld.</td></tr>
                </tbody>
            </table>
            <?php $render_section_comments('section-entscheidungen'); ?>
        </div>

        <!-- ───── Abschnitt 4 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-architektur">
            <h3>4. Wie ist die Loesung aufgebaut?</h3>
            <p>Das Modul besteht aus klar getrennten Bausteinen. Jeder Baustein hat eine Aufgabe und kann fuer spaetere Module (Webinare, Kongresse) wiederverwendet werden.</p>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>Baustein</th><th>Aufgabe</th></tr></thead>
                <tbody>
                    <tr><td><strong>Workshops lesen</strong></td><td>Holt aktive Workshops aus dem Mitglieder-System auf die Webseite.</td></tr>
                    <tr><td><strong>Buchung pruefen</strong></td><td>Prueft, ob noch Plaetze frei sind, und legt die Anmeldung an.</td></tr>
                    <tr><td><strong>Bezahlung</strong></td><td>Schickt die Person zur Stripe-Bezahlseite und nimmt das Ergebnis entgegen.</td></tr>
                    <tr><td><strong>Mitglieder-System schreiben</strong></td><td>Traegt die Anmeldung in unsere Teilnehmer:innen-Liste ein.</td></tr>
                    <tr><td><strong>E-Mails</strong></td><td>Versendet Bestaetigung, Warteliste, Storno &mdash; mit Termin-Anhang fuer den Kalender.</td></tr>
                    <tr><td><strong>Warteliste</strong></td><td>Ueberwacht freie Plaetze und benachrichtigt Nachruecker:innen automatisch.</td></tr>
                    <tr><td><strong>Webseite (Frontend)</strong></td><td>Was Nutzer:innen sehen: Workshop-Karten, Buchungsformular, Bestaetigungsseite.</td></tr>
                </tbody>
            </table>
            <p><em>Hintergrund:</em> Diese Trennung ermoeglicht es, einzelne Bausteine spaeter auszutauschen oder fuer Webinare und Kongresse wiederzuverwenden &mdash; ohne das ganze Modul anzufassen.</p>
            <?php $render_section_comments('section-architektur'); ?>
        </div>

        <!-- ───── Abschnitt 5 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-datenfluss">
            <h3>5. So laeuft eine Buchung ab</h3>
            <ol class="dgptm-wsb-evl-steps-list">
                <li><strong>Workshop entdecken</strong> &mdash; Auf der Webseite werden alle kommenden Workshops als Karten gezeigt. Die Inhalte kommen direkt aus dem Mitglieder-System.</li>
                <li><strong>Ticket auswaehlen</strong> &mdash; Die Person waehlt ein Ticket (z.&thinsp;B. &bdquo;Vollpreis&ldquo;, &bdquo;Mitgliedspreis&ldquo;).</li>
                <li><strong>Daten eintragen</strong> &mdash; Bei eingeloggten Mitgliedern automatisch vorausgefuellt. Gaeste tragen Vor-/Nachname und E-Mail ein.</li>
                <li><strong>Bezahlen</strong> &mdash; Weiterleitung zur Bezahlseite von Stripe. Wer nicht zahlt, dessen Buchung verfaellt automatisch &mdash; der Platz wird wieder freigegeben.</li>
                <li><strong>Bestaetigung</strong> &mdash; Sofort nach Zahlungseingang: Bestaetigungs-E-Mail mit Kalender-Anhang.</li>
                <li><strong>Falls voll</strong> &mdash; Die Person landet automatisch auf der Warteliste. Wird ein Platz frei, erhaelt sie eine E-Mail mit 24-Stunden-Zahlungslink.</li>
            </ol>
            <?php $render_section_comments('section-datenfluss'); ?>
        </div>

        <!-- ───── Abschnitt 6 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-kompatibilitaet">
            <h3>6. Zusammenspiel mit bestehenden Funktionen</h3>
            <ul>
                <li><strong>Edugrant-Foerderung:</strong> Wenn fuer einen Workshop eine Foerderung verfuegbar ist, erscheint auf der Buchungs-Karte ein Hinweis und ein Link zum Foerderantrag. Das vermeidet doppelte Erfassung.</li>
                <li><strong>Webinar-Buchung (in Vorbereitung):</strong> Die Bausteine fuer Mitglieder-Erkennung und Anmelde-Datensaetze werden so gebaut, dass die Webinar-Buchung sie spaeter direkt mitnutzen kann &mdash; ohne Doppelarbeit.</li>
                <li><strong>vimeo-webinare (live):</strong> Bleibt unveraendert. Das Workshop-Modul greift nicht in die laufende Webinar-Loesung ein.</li>
            </ul>
            <?php $render_section_comments('section-kompatibilitaet'); ?>
        </div>

        <!-- ───── Abschnitt 7 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-crm-erweiterungen">
            <h3>7. Anpassungen im Mitglieder-System</h3>
            <p>Damit das Modul funktioniert, sind ein paar kleine Anpassungen im internen Mitglieder-System noetig. Diese muessen mit der Geschaeftsstelle abgestimmt werden.</p>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Veranstaltungs-Stammdaten</strong> &mdash; neues Feld &bdquo;Storno-Frist (Tage)&ldquo;, Standardwert z.&thinsp;B. 14 Tage. So kann pro Workshop entschieden werden, wie lange Teilnehmer:innen selbst stornieren duerfen.
                <em>&rarr; Abstimmung mit Geschaeftsstelle noetig.</em>
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Anmelde-Status</strong> &mdash; neue Status-Werte fuer die Teilnehmer:innen-Liste:
                <ul>
                    <li><em>Zahlung ausstehend</em> &mdash; waehrend die Person noch auf der Bezahlseite ist</li>
                    <li><em>Warteliste</em> &mdash; Workshop ist voll</li>
                    <li><em>Nachruecker:in &ndash; Zahlung ausstehend</em> &mdash; 24-Stunden-Frist laeuft</li>
                    <li><em>Storniert</em> &mdash; Geld erstattet</li>
                </ul>
                <em>&rarr; Abstimmung mit Verantwortlichen fuer den Anmelde-Workflow noetig.</em>
            </div>
            <?php $render_section_comments('section-crm-erweiterungen'); ?>
        </div>

        <!-- ───── Abschnitt 8 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-abhaengigkeiten">
            <h3>8. Externe Dienste</h3>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>Dienst</th><th>Wofuer</th><th>Stand</th></tr></thead>
                <tbody>
                    <tr><td><strong>Stripe</strong> (Zahlungsanbieter)</td><td>Bezahlung und automatische Erstattung</td><td>aktiv, Konto vorhanden</td></tr>
                    <tr><td><strong>Mitglieder-System</strong> (Zoho)</td><td>Workshop-Daten lesen, Anmeldungen schreiben</td><td>aktiv, Zugriffsrechte vorhanden</td></tr>
                    <tr><td><strong>EIV-Fobi</strong> (Fortbildungspunkte)</td><td>Aktuell kein direkter Anschluss &mdash; Fortbildungspunkte werden weiter manuell vergeben.</td><td>folgt in Version 2</td></tr>
                </tbody>
            </table>
            <?php $render_section_comments('section-abhaengigkeiten'); ?>
        </div>

        <!-- ───── Abschnitt 9 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-out-of-scope">
            <h3>9. Was ist NICHT enthalten?</h3>
            <p>Damit klar ist, was diese Version <em>nicht</em> kann:</p>
            <ul>
                <li>Erfassung der Fortbildungspunkte-Nummer im Buchungsformular &mdash; folgt in Version 2.</li>
                <li>Gemeinsame Buchung mehrerer Personen mit nur einem Zahler &mdash; geht aktuell nur, wenn eine Person mehrere Tickets in <em>einer</em> Buchung kauft.</li>
                <li>Variable Pflichtfelder pro Ticket-Typ (z.&thinsp;B. fuer manche Tickets zusaetzliche Angaben).</li>
                <li>Webinar- und Kongress-Buchung &mdash; folgen als eigene Module, die diesen Kern wiederverwenden.</li>
                <li>Automatische Uebernahme bestehender Buchungen aus alten Systemen.</li>
                <li>Automatische Erstellung von Teilnahme-Zertifikaten &mdash; laeuft weiter ueber die bestehende Fortbildungs-Funktion.</li>
            </ul>
            <?php $render_section_comments('section-out-of-scope'); ?>
        </div>

        <!-- ───── Abschnitt 10 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-offene-punkte">
            <h3>10. Offene Fragen fuer dich</h3>
            <p>Diese Punkte brauchen eine Entscheidung, bevor wir mit der Umsetzung starten. Hinterlass deine Meinung bitte als Kommentar in diesem Abschnitt.</p>
            <div class="dgptm-wsb-evl-highlight blue">
                <table class="dgptm-wsb-evl-table" style="background:transparent;">
                    <thead><tr><th>#</th><th>Frage</th><th>Vorschlag</th></tr></thead>
                    <tbody>
                        <tr><td>1</td><td><strong>Wie lange duerfen Teilnehmer:innen selbst stornieren?</strong></td><td>14 Tage einheitlich, pro Workshop aenderbar</td></tr>
                        <tr><td>2</td><td><strong>Was passiert, wenn nach der Frist storniert wird?</strong></td><td>Vorschlag: keine Erstattung. Haertefaelle nach Kulanz &mdash; muss mit AGB abgestimmt werden.</td></tr>
                        <tr><td>3</td><td><strong>Wie sollen die neuen Anmelde-Status heissen?</strong></td><td>Vorschlag: &bdquo;Zahlung ausstehend&ldquo;, &bdquo;Warteliste&ldquo;, &bdquo;Nachruecker:in &ndash; Zahlung ausstehend&ldquo;, &bdquo;Storniert&ldquo;</td></tr>
                        <tr><td>4</td><td><strong>Edugrant-Foerderung:</strong> Nur Hinweis und Link &mdash; oder integrierter Antrag aus der Buchung heraus?</td><td>Vorschlag: erstmal nur Hinweis und Link.</td></tr>
                        <tr><td>5</td><td><strong>Welches Stripe-Konto wird verwendet?</strong></td><td>Vorhandenes DGPTM-Konto oder ein eigenes Sub-Konto fuer Workshops? &mdash; Buchhaltungs-Entscheidung.</td></tr>
                        <tr><td>6</td><td><strong>Teilnahme-Zertifikat nach Workshop direkt aus diesem Modul?</strong></td><td>Vorschlag: nein, weiterhin ueber die bestehende Fortbildungs-Funktion.</td></tr>
                    </tbody>
                </table>
            </div>
            <?php $render_section_comments('section-offene-punkte'); ?>
        </div>

    </div><!-- /.dgptm-wsb-evl-dokument -->

    <!-- Sticky Footer -->
    <div class="dgptm-wsb-evl-footer">
        <div class="dgptm-wsb-evl-footer-inner">
            <div class="dgptm-wsb-evl-footer-left">
                <?php echo $approval_count; ?> Freigabe<?php echo $approval_count !== 1 ? 'n' : ''; ?> | <?php echo count($comments); ?> Kommentar<?php echo count($comments) !== 1 ? 'e' : ''; ?>
            </div>
            <?php if (!$user_approved) : ?>
                <button type="button" class="dgptm-wsb-evl-approve-btn" id="dgptm-wsb-evl-approve-footer">
                    Entscheidungsvorlage freigeben
                </button>
            <?php else : ?>
                <span class="dgptm-wsb-evl-approved-badge">&#10003; Freigegeben</span>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.dgptm-wsb-evl-wrapper -->
