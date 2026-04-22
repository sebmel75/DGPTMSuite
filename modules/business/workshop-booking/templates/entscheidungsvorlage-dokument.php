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
                Sie haben freigegeben
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
            <h2>Workshop-Buchung &mdash; Entscheidungsvorlage</h2>
            <p class="dgptm-wsb-evl-subtitle">Design-Zwischenstand zur Abstimmung</p>
            <p class="dgptm-wsb-evl-meta">DGPTM | Stand: 22.04.2026 | Empfaenger: Vorstand, Geschaeftsstelle, Kursleitungen</p>
        </div>

        <!-- ───── Abschnitt 1 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-ziel">
            <h3>1. Ziel des Moduls</h3>
            <p>Neues DGPTMSuite-Modul <strong><code>workshop-booking</code></strong>, das zukuenftige Veranstaltungen (Workshops) aus dem Zoho CRM (<code>DGfK_Events</code>) liest und online buchbar macht. Die Buchung laeuft entweder kostenlos (Freiticket) oder ueber Stripe-Zahlung. Jede Buchung erzeugt einen Eintrag im CRM-Modul <code>Veranstal_X_Contacts</code> mit Status <em>Nicht abgerechnet</em> und Blueprint <em>Angemeldet</em>. Das Modul ist so gebaut, dass spaetere Module (Webinar-Buchung, Kongress-Buchung) die Kernkomponenten wiederverwenden koennen.</p>
            <?php $render_section_comments('section-ziel'); ?>
        </div>

        <!-- ───── Abschnitt 2 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-ausgangslage">
            <h3>2. Ausgangslage</h3>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>Element</th><th>Stand</th></tr></thead>
                <tbody>
                    <tr><td><strong>Edugrant-Modul</strong></td><td>Liest bereits Events aus <code>DGfK_Events</code>, zeigt Karten-UI. Dient als Blaupause fuer das Event-Listing.</td></tr>
                    <tr><td><strong>vimeo-webinare (v2.0.1)</strong></td><td>Live, eigene Webinar-Logik mit PDF-Zertifikat. Existierende Ticket-/Buchungs-Logik: keine.</td></tr>
                    <tr><td><strong>Webinar-CRM-Sync (Spec v. 15.04.26)</strong></td><td>Plant bidirektionale Sync zwischen Zoho Meeting und CRM. <strong>Billing ausdruecklich out of scope</strong> &mdash; genau diese Luecke fuellt das neue Modul.</td></tr>
                    <tr><td><strong>stripe-formidable</strong></td><td>Bestehende Stripe-Integration, jedoch Formidable-Forms-gekoppelt. Wird nicht wiederverwendet.</td></tr>
                </tbody>
            </table>
            <?php $render_section_comments('section-ausgangslage'); ?>
        </div>

        <!-- ───── Abschnitt 3 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-entscheidungen">
            <h3>3. Getroffene Design-Entscheidungen</h3>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>#</th><th>Entscheidungspunkt</th><th>Gewaehlt</th><th>Begruendung</th></tr></thead>
                <tbody>
                    <tr><td>1</td><td><strong>Modul-Scope</strong></td><td>Workshop-Modul jetzt, Komponenten fuer spaetere Verallgemeinerung vorbereitet</td><td>Ueberschaubarer Scope, zukunftssicher ohne Over-Engineering</td></tr>
                    <tr><td>2</td><td><strong>User-Authentifizierung</strong></td><td>Hybrid: Eingeloggte one-click, Gaeste per Formular</td><td>Maximale Reichweite; Vor-/Nachname + E-Mail reichen als Einstieg</td></tr>
                    <tr><td>3</td><td><strong>Bezahl-Integration</strong></td><td>Stripe Checkout Session (hosted); bei Nicht-Zahlung wird Buchung wieder geloescht</td><td>Weniger Code, volle PCI-Compliance durch Stripe, SEPA/Apple Pay umsonst</td></tr>
                    <tr><td>4</td><td><strong>Tickets pro Buchung</strong></td><td>Mehrere Tickets mit Teilnehmer:innen-Daten; pro Person ein Veranstal_X_Contacts-Eintrag</td><td>Korrekte Zertifikats- und Fortbildungspunkte-Vergabe pro Person</td></tr>
                    <tr><td>5</td><td><strong>Erfasste Daten pro Ticket</strong></td><td>Minimal (Vor-/Nachname, E-Mail) + Adresse optional; Smart-Form blendet Felder aus, wenn CRM-Match</td><td>Datensparsam, benutzerfreundlich fuer Bestands-Mitglieder</td></tr>
                    <tr><td>6</td><td><strong>Kontakt-Matching</strong></td><td>4-Felder-E-Mail-Suche (<code>Email</code>, <code>Secondary_Email</code>, <code>Third_Email</code>, <code>Fourth_Email</code> via COQL-Fallback). Kein Treffer &rarr; Contact-Neuanlage</td><td>Kompatibel mit vorhandener Webinar-Sync-Logik, keine Dubletten</td></tr>
                    <tr><td>7</td><td><strong>UI-Integration</strong></td><td>Shortcodes: <code>[dgptm_workshops]</code> (Liste/Detail/Formular) und <code>[dgptm_workshops_success]</code> (Bestaetigung)</td><td>Freie Gestaltung der WP-Seite durch Geschaeftsstelle; etabliertes DGPTMSuite-Muster</td></tr>
                    <tr><td>8</td><td><strong>Kapazitaet</strong></td><td>Hartes Limit pro Event + automatische Warteliste mit 24-h-Nachrueck-Frist</td><td>Faire FIFO-Logik, keine Enttaeuschung durch dauerhafte Sperre</td></tr>
                    <tr><td>9</td><td><strong>Storno</strong></td><td>Hybrid: User kann bis zur Frist selbst stornieren (Stripe-Refund automatisch); danach nur Geschaeftsstelle, kein/teilweiser Refund</td><td>Entlastet Geschaeftsstelle, AGB-konform</td></tr>
                    <tr><td>10</td><td><strong>E-Mails</strong></td><td>Hybrid: Transactional (Bestaetigung, Warteliste, Nachruecker, Storno) ueber <code>wp_mail</code>; Info-/Erinnerungs-Mails ueber Zoho CRM / Marketing Automation</td><td>Zeitkritische Mails sofort, ICS-Anhang moeglich; Marketing-Content flexibel</td></tr>
                    <tr><td>11</td><td><strong>Promo-Codes</strong></td><td>Stripe-nativ: Option <code>allow_promotion_codes: true</code>. <code>PromoCodesCSV</code> aus CRM wird optional zu Stripe-Coupons gespiegelt</td><td>Keine eigene Promo-UI noetig</td></tr>
                    <tr><td>12</td><td><strong>Architektur-Ansatz</strong></td><td>Eigenstaendiges Modul mit Service-Interfaces (<code>EventSource</code>, <code>PaymentGateway</code>, <code>BookingWriter</code>, <code>MailSender</code>, <code>WaitlistStore</code>)</td><td>Spaetere Extraktion in Shared-Modul per Namespace-Umzug trivial</td></tr>
                </tbody>
            </table>
            <?php $render_section_comments('section-entscheidungen'); ?>
        </div>

        <!-- ───── Abschnitt 4 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-architektur">
            <h3>4. Architektur-Kern</h3>
            <pre class="dgptm-wsb-evl-codeblock">modules/business/workshop-booking/
├── src/
│   ├── Contracts/           Interfaces
│   ├── Events/              CRM-Event-Abruf + Ticket-Parsing
│   ├── Booking/             Orchestrator + Value Objects
│   ├── Payment/             Stripe-Checkout + Webhook
│   ├── Crm/                 Veranstal_X_Contacts + 4-Felder-Lookup
│   ├── Mail/                wp_mail + ICS-Builder
│   ├── Waitlist/            Nachrueck-Logik, Cron-Watcher
│   ├── Ajax/                Contact-Lookup + Booking-Submit
│   └── Shortcodes/          [dgptm_workshops], [dgptm_workshops_success]
├── templates/               Frontend + E-Mail-Templates
├── assets/                  CSS + JS
└── cron/                    Warteliste-Watcher (15 min)</pre>
            <p><strong>Oeffentlicher Einstiegspunkt:</strong> <code>BookingService::get_instance()-&gt;book($event_id, $attendees)</code> gibt ein <code>BookingResult</code>-Objekt mit entweder <code>checkout_url</code>, <code>confirmation</code> oder <code>waitlist_position</code>.</p>
            <?php $render_section_comments('section-architektur'); ?>
        </div>

        <!-- ───── Abschnitt 5 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-datenfluss">
            <h3>5. Datenfluss</h3>
            <ol class="dgptm-wsb-evl-steps-list">
                <li><strong>Event-Anzeige</strong> &mdash; Cron-unabhaengig: Live-Abruf aus CRM ueber bestehendes <code>crm-abruf</code>-Modul, Filter <code>Event_Type = "Workshop"</code> + <code>From_Date &gt;= heute</code>.</li>
                <li><strong>Ticket-Auswahl</strong> &mdash; Tickets kommen aus dem Event-Record (<code>Tickets</code>-Array von Zoho Backstage).</li>
                <li><strong>Buchungs-Submit</strong> &mdash; Capacity-Check &rarr; Veranstal_X_Contacts-Eintrag mit Status <em>Zahlung ausstehend</em> (oder <em>Warteliste</em>) &rarr; bei bezahlten Tickets: Stripe Checkout Session erzeugen.</li>
                <li><strong>Stripe-Webhook</strong>
                    <ul>
                        <li><code>checkout.session.completed</code> &rarr; Status auf <em>Nicht abgerechnet</em>, Blueprint auf <em>Angemeldet</em>, Bestaetigungs-Mail mit ICS.</li>
                        <li><code>checkout.session.expired</code> &rarr; Veranstal_X_Contacts-Eintrag wird geloescht, Platz frei.</li>
                    </ul>
                </li>
                <li><strong>Warteliste-Watcher</strong> (15 min) &mdash; Prueft Luecken zwischen belegten Plaetzen und <code>Maximum_Attendees</code>; bei Luecke: aeltester Wartelisten-Eintrag wird per E-Mail mit 24-h-Zahlungslink benachrichtigt.</li>
            </ol>
            <?php $render_section_comments('section-datenfluss'); ?>
        </div>

        <!-- ───── Abschnitt 6 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-kompatibilitaet">
            <h3>6. Kompatibilitaet zu bestehenden Modulen</h3>
            <ul>
                <li><strong>Edugrant</strong> &mdash; Workshop-Buchungsmodul prueft, ob Event eine Edugrant-Foerderung hat (<code>Maximum_Promotion</code> gesetzt). Falls ja: Hinweis auf Karte (&bdquo;Fuer diese Veranstaltung ist EduGrant moeglich&ldquo;) + Link zum Edugrant-Antrag. Keine Funktions-Duplizierung.</li>
                <li><strong>Webinar-CRM-Sync</strong> &mdash; Shared <code>Crm\VeranstalXContactsWriter</code> und <code>ContactLookup</code> werden so gestaltet, dass die Webinar-Sync-Spec sie spaeter referenzieren kann. Die 4-Felder-Mail-Logik wird einmalig implementiert und beiden Modulen zur Verfuegung gestellt (nach Verschiebung in Shared-Modul).</li>
                <li><strong>vimeo-webinare</strong> &mdash; Kein direkter Konflikt; spaetere Verallgemeinerung des Moduls auf Webinar-Buchung ist vorbereitet.</li>
            </ul>
            <?php $render_section_comments('section-kompatibilitaet'); ?>
        </div>

        <!-- ───── Abschnitt 7 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-crm-erweiterungen">
            <h3>7. Neue Felder / Erweiterungen im CRM</h3>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>DGfK_Events</strong> &mdash; Vorschlag fuer neues Feld <code>Storno_Frist_Tage</code> (Zahl, Standard 14). Steuert Self-Service-Storno-Frist.
                <em>&rarr; Abstimmung mit Geschaeftsstelle noetig.</em>
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Veranstal_X_Contacts</strong> &mdash; Neue Status-Werte ggf. erforderlich:
                <ul>
                    <li><em>Zahlung ausstehend</em> (waehrend Stripe-Session offen)</li>
                    <li><em>Warteliste</em> (Capacity-Ueberlauf)</li>
                    <li><em>Nachruecker &ndash; Zahlung ausstehend</em> (24-h-Frist aktiv)</li>
                    <li><em>Storniert</em> (Refund erfolgt)</li>
                </ul>
                <em>&rarr; Abstimmung mit Blueprint-Verantwortlichen noetig.</em>
            </div>
            <?php $render_section_comments('section-crm-erweiterungen'); ?>
        </div>

        <!-- ───── Abschnitt 8 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-abhaengigkeiten">
            <h3>8. Externe Abhaengigkeiten</h3>
            <ul>
                <li><strong>Stripe-Konto</strong> &mdash; Aktiv. Webhook-Secret muss konfiguriert werden (Endpoint: <code>/wp-json/dgptm-workshop/v1/stripe-webhook</code>).</li>
                <li><strong>Zoho CRM</strong> &mdash; Schreibzugriff auf <code>Veranstal_X_Contacts</code> + <code>Contacts</code>; Lese-/Blueprint-Transition-Rechte.</li>
                <li><strong>EIV-Fobi</strong> &mdash; Aktuell kein direkter Touchpoint (VNR-Erfassung erst in v2).</li>
            </ul>
            <?php $render_section_comments('section-abhaengigkeiten'); ?>
        </div>

        <!-- ───── Abschnitt 9 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-out-of-scope">
            <h3>9. Nicht im Scope (v1)</h3>
            <ul>
                <li>VNR/EIV-Fobi-Erfassung im Buchungsformular &rarr; v2</li>
                <li>Gruppenanmeldung mit einem gemeinsamen Zahler fuer mehrere Personen (geht bereits indirekt)</li>
                <li>Conditional-Field-Logik pro Ticket-Typ (Pflichtfelder variabel)</li>
                <li>Webinar- und Kongress-Buchung &rarr; spaetere Module, die den Core wiederverwenden</li>
                <li>Automatische Migration bestehender Backstage-Buchungen</li>
            </ul>
            <?php $render_section_comments('section-out-of-scope'); ?>
        </div>

        <!-- ───── Abschnitt 10 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-offene-punkte">
            <h3>10. Offene Punkte zur Entscheidung</h3>
            <div class="dgptm-wsb-evl-highlight blue">
                <ol>
                    <li><strong>Storno-Frist:</strong> Einheitlich (z.B. 14 Tage) oder pro Event konfigurierbar? &rarr; Vorschlag: neues Feld <code>Storno_Frist_Tage</code> am Event.</li>
                    <li><strong>Refund-Politik nach Frist:</strong> Gar kein Refund, 50&thinsp;%, nach Kulanz? &rarr; braucht AGB-Abstimmung.</li>
                    <li><strong>Blueprint-Status-Wording:</strong> Wie sollen die neuen Status heissen? &rarr; Vorschlag: &bdquo;Zahlung ausstehend&ldquo;, &bdquo;Warteliste&ldquo;, &bdquo;Nachruecker &ndash; Zahlung ausstehend&ldquo;, &bdquo;Storniert&ldquo;.</li>
                    <li><strong>EduGrant-Verknuepfung:</strong> Nur Hinweis/Link auf der Karte, oder integrierter Flow &bdquo;Ich beantrage EduGrant zu dieser Buchung&ldquo;? v1-Vorschlag: nur Hinweis.</li>
                    <li><strong>Stripe-Konto:</strong> Das Konto der Gesellschaft wird verwendet? Oder separates Sub-Konto? &rarr; Finanz-/Buchhaltungs-Entscheidung.</li>
                    <li><strong>Teilnahme-Zertifikat</strong> nach Workshop: Bereits in diesem Modul, oder weiterhin durch <code>fortbildung</code>-Post-Type (manuell)? &rarr; v1-Vorschlag: out of scope, kommt via bestehendem <code>fortbildung</code>-Flow.</li>
                </ol>
            </div>
            <?php $render_section_comments('section-offene-punkte'); ?>
        </div>

        <!-- ───── Abschnitt 11 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-zeitplan">
            <h3>11. Zeitplan (Schaetzung)</h3>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>Phase</th><th>Aufwand</th></tr></thead>
                <tbody>
                    <tr><td>Implementierung Core (Events, Booking, Stripe, CRM, Mail)</td><td>~3 Personentage</td></tr>
                    <tr><td>Frontend (Shortcodes, Templates, JS-Progressive-Form)</td><td>~1,5 Personentage</td></tr>
                    <tr><td>Warteliste, Storno, Webhook-Edge-Cases</td><td>~1,5 Personentage</td></tr>
                    <tr><td>Test auf Staging + Anpassungen</td><td>~1 Personentag</td></tr>
                    <tr><td><strong>Summe</strong></td><td><strong>~7 Personentage</strong></td></tr>
                </tbody>
            </table>
            <?php $render_section_comments('section-zeitplan'); ?>
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
