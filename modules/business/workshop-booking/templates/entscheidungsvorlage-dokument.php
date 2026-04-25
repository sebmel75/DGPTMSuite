<?php
/**
 * Template: Entscheidungsvorlage Workshop-Buchung-Modul.
 *
 * Variablen aus class-entscheidungsvorlage.php:
 *   $approvals      — Array aller Gesamt-Freigaben
 *   $comments       — Array aller Kommentare (pro Sektion ODER pro Zeile)
 *   $row_approvals  — Array [row_id => [user_id => {user_name,timestamp}]]
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
                <button type="button" class="dgptm-fe-btn dgptm-fe-btn-primary dgptm-fe-btn-small dgptm-wsb-evl-comment-submit">Kommentar senden</button>
            </div>
        </div>
    </div>
    <?php
};

/* ──────────────────────────────────────────────
 * Hinweis: Buttons innerhalb der naechsten Closure verwenden
 * die globalen dgptm-fe-btn Klassen aus core/assets/css/dgptm-shared-buttons.css.
 * Modulspezifische Klassen (dgptm-wsb-evl-row-*) bleiben fuer JS-Selektoren erhalten.
 * ────────────────────────────────────────────── */

$render_row_actions = function ($row_id, $colspan = 4) use ($comments, $row_approvals, $current_user_id) {
    $approvers   = isset($row_approvals[$row_id]) ? $row_approvals[$row_id] : [];
    $approved    = isset($approvers[$current_user_id]);
    $count       = count($approvers);
    $row_comments = array_filter($comments, function ($c) use ($row_id) {
        return isset($c['section']) && $c['section'] === $row_id;
    });
    $comment_count = count($row_comments);
    ?>
    <tr class="dgptm-wsb-evl-row-actions" data-row-id="<?php echo esc_attr($row_id); ?>">
        <td colspan="<?php echo (int) $colspan; ?>">
            <div class="dgptm-wsb-evl-row-actions-bar">
                <button type="button"
                        class="dgptm-fe-btn dgptm-fe-btn-small<?php echo $approved ? ' dgptm-fe-btn-primary' : ''; ?> dgptm-wsb-evl-row-approve-btn<?php echo $approved ? ' is-approved' : ''; ?>"
                        data-row="<?php echo esc_attr($row_id); ?>"
                        title="<?php echo $approved ? 'Klicken zum Zuruecknehmen' : 'Diesem Vorschlag zustimmen'; ?>">
                    <span class="dgptm-wsb-evl-row-approve-icon"><?php echo $approved ? '&#10003;' : '&#9744;'; ?></span>
                    <span class="dgptm-wsb-evl-row-approve-label"><?php echo $approved ? 'Zugestimmt' : 'Vorschlag mittragen'; ?></span>
                    <span class="dgptm-wsb-evl-row-approve-count" data-count="<?php echo (int) $count; ?>"><?php echo $count > 0 ? '(' . $count . ')' : ''; ?></span>
                </button>
                <?php if ($count > 0) : ?>
                    <span class="dgptm-wsb-evl-row-approvers" title="<?php echo esc_attr(implode(', ', array_map(function ($a) { return $a['user_name']; }, $approvers))); ?>">
                        <?php
                        $names = array_map(function ($a) { return $a['user_name']; }, $approvers);
                        $first = array_slice($names, 0, 3);
                        echo esc_html(implode(', ', $first));
                        if (count($names) > 3) echo ' &hellip;';
                        ?>
                    </span>
                <?php endif; ?>
                <button type="button"
                        class="dgptm-fe-btn dgptm-fe-btn-small dgptm-wsb-evl-row-comments-toggle"
                        data-row="<?php echo esc_attr($row_id); ?>">
                    &#128172; Kommentare <span class="dgptm-wsb-evl-row-comments-count">(<?php echo (int) $comment_count; ?>)</span>
                    <span class="dgptm-wsb-evl-row-comments-arrow">&#9660;</span>
                </button>
            </div>
            <div class="dgptm-wsb-evl-comments-block dgptm-wsb-evl-row-comments-block" data-section="<?php echo esc_attr($row_id); ?>" style="display:none;">
                <div class="dgptm-wsb-evl-comments-list">
                    <?php foreach ($row_comments as $c) :
                        $can_delete = ((int) $c['user_id'] === $current_user_id) || current_user_can('manage_options');
                        $is_read    = !empty($c['status']) && $c['status'] === 'eingearbeitet';
                        $ts         = date_i18n('d.m.Y, H:i', strtotime($c['timestamp']));
                        $comment_class = 'dgptm-wsb-evl-comment' . ($is_read ? ' dgptm-wsb-evl-comment-read' : '');
                    ?>
                        <div class="<?php echo $comment_class; ?>" data-comment-id="<?php echo esc_attr($c['id']); ?>">
                            <div class="dgptm-wsb-evl-comment-meta">
                                <strong><?php echo esc_html($c['user_name']); ?></strong>
                                <span class="dgptm-wsb-evl-comment-date"><?php echo esc_html($ts); ?></span>
                                <?php if ($is_read) : ?><span class="dgptm-wsb-evl-badge-eingearbeitet">eingearbeitet</span><?php endif; ?>
                                <?php if ($can_delete && !$is_read) : ?>
                                    <button type="button" class="dgptm-wsb-evl-comment-delete" data-id="<?php echo esc_attr($c['id']); ?>" title="Kommentar loeschen">&times;</button>
                                <?php endif; ?>
                                <?php if (current_user_can('manage_options') && !$is_read) : ?>
                                    <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-wsb-evl-comment-mark-read" data-id="<?php echo esc_attr($c['id']); ?>" title="Als eingearbeitet markieren">&#10003; eingearbeitet</button>
                                <?php endif; ?>
                            </div>
                            <div class="dgptm-wsb-evl-comment-text"><?php echo nl2br(esc_html($c['text'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="dgptm-wsb-evl-comment-form">
                    <textarea class="dgptm-wsb-evl-comment-input" rows="2" placeholder="Kommentar zu dieser Zeile..."></textarea>
                    <button type="button" class="dgptm-fe-btn dgptm-fe-btn-primary dgptm-fe-btn-small dgptm-wsb-evl-comment-submit">Senden</button>
                </div>
            </div>
        </td>
    </tr>
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
            <button type="button" class="dgptm-fe-btn dgptm-fe-btn-primary" id="dgptm-wsb-evl-approve">
                Entscheidungsvorlage freigeben
            </button>
        <?php else : ?>
            <div class="dgptm-wsb-evl-approved-info">
                Du hast freigegeben
                <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small" id="dgptm-wsb-evl-revoke">
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
            <p class="dgptm-wsb-evl-meta">DGPTM | Stand: 25.04.2026 (erweitert um Tickets, Mitgliederbereich, Zertifikate) | Empfaenger:innen: Vorstand, Geschaeftsstelle, Kursleitungen</p>
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
                <li>Die Anmeldung wird automatisch ins Zoho CRM eingetragen.</li>
                <li>Der/die Teilnehmer:in erhaelt sofort eine Bestaetigung mit Termin fuer den Kalender.</li>
                <li>Mitglieder sehen alle ihre Tickets (auch aeltere und solche aus Zoho Backstage) im Mitgliederbereich an einer Stelle.</li>
                <li>Jedes Ticket traegt einen QR-Code fuer die Einlasskontrolle am Workshop-Tag.</li>
                <li>Nicht-Mitglieder bekommen einen persoenlichen Link per E-Mail &mdash; ohne Login.</li>
                <li>Nach dem Workshop entsteht automatisch ein Teilnahme-Zertifikat als PDF.</li>
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
                    <tr><td>Workshops sind im internen Zoho CRM gepflegt.</td><td>Sie sind nicht online buchbar.</td></tr>
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
            <h3>3. Fragestellungen zur Entscheidung</h3>
            <p>Diese Vorschlaege liegen dem geplanten Modul zugrunde. Du kannst jedem Vorschlag <em>einzeln zustimmen</em> oder einen <em>eigenen Kommentar</em> hinterlassen. Die Gesamt-Freigabe am Ende der Seite gilt als &bdquo;Ich trage die Vorlage als Ganzes mit&ldquo;.</p>

            <h4 style="margin-top:16px;">Buchungs-Logik</h4>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>#</th><th>Frage</th><th>Vorschlag</th><th>Begruendung</th></tr></thead>
                <tbody>
                    <tr><td>1</td><td><strong>Wie gross soll das Modul sein?</strong></td><td>Erst nur Workshops. Bausteine werden so gebaut, dass Webinare/Kongresse spaeter daran andocken koennen.</td><td>Schritt fuer Schritt &mdash; kein Mammut-Projekt, aber zukunftsfaehig.</td></tr>
                    <?php $render_row_actions('entscheidung-row-1'); ?>
                    <tr><td>2</td><td><strong>Muessen Buchende eingeloggt sein?</strong></td><td>Beides: Mitglieder buchen mit einem Klick. Gaeste fuellen ein kurzes Formular aus.</td><td>Maximal einfach fuer Mitglieder, gleichzeitig offen fuer Externe.</td></tr>
                    <?php $render_row_actions('entscheidung-row-2'); ?>
                    <tr><td>3</td><td><strong>Wie wird bezahlt?</strong></td><td>Ueber Stripe (gleicher Anbieter wie heute). Die Bezahlseite laeuft direkt bei Stripe &mdash; wir beruehren keine Kreditkartendaten. Workshops laufen ueber ein <strong>eigenes Stripe-Unterkonto</strong>, getrennt vom DGPTM-Hauptkonto.</td><td>Sicher (internationaler Sicherheitsstandard), Apple Pay und SEPA-Lastschrift automatisch dabei. Eigenes Unterkonto fuer Workshops sorgt fuer klare buchhalterische Trennung.</td></tr>
                    <?php $render_row_actions('entscheidung-row-3'); ?>
                    <tr><td>4</td><td><strong>Was, wenn jemand mehrere Plaetze bucht?</strong></td><td>Pro Person ein eigener Eintrag mit eigenen Kontaktdaten.</td><td>Damit jede:r Teilnehmer:in eine eigene Bestaetigung und ggf. Fortbildungspunkte erhaelt.</td></tr>
                    <?php $render_row_actions('entscheidung-row-4'); ?>
                    <tr><td>5</td><td><strong>Welche Daten werden erfasst?</strong></td><td>Pflicht: Vor- und Nachname, E-Mail. Adresse nur, wenn benoetigt. Bei bekannten Mitgliedern werden vorhandene Daten automatisch eingeblendet.</td><td>So wenig wie moeglich, so viel wie noetig.</td></tr>
                    <?php $render_row_actions('entscheidung-row-5'); ?>
                    <tr><td>6</td><td><strong>Wie erkennt das System Bestandsmitglieder?</strong></td><td>Es prueft alle bekannten E-Mail-Adressen. Findet es niemanden, wird ein neuer Kontakt angelegt.</td><td>Keine Doppel-Eintraege, keine Karteileichen.</td></tr>
                    <?php $render_row_actions('entscheidung-row-6'); ?>
                    <tr><td>7</td><td><strong>Wie wird die Buchung in die Webseite eingebunden?</strong></td><td>Ueber zwei Platzhalter, die die Geschaeftsstelle frei auf jeder Seite einbauen kann.</td><td>Volle Gestaltungsfreiheit fuer die Geschaeftsstelle.</td></tr>
                    <?php $render_row_actions('entscheidung-row-7'); ?>
                    <tr><td>8</td><td><strong>Was, wenn ein Workshop voll ist?</strong></td><td>Automatische Warteliste. Wird ein Platz frei, hat die naechste Person 24 Stunden Zeit zum Buchen.</td><td>Faire Reihenfolge ohne dauerhafte Sperre.</td></tr>
                    <?php $render_row_actions('entscheidung-row-8'); ?>
                    <tr><td>9</td><td><strong>Storno durch Teilnehmer:in?</strong></td><td>Bis zu einer Frist (Vorschlag: 14 Tage vor dem Workshop) selbst moeglich, Geld kommt automatisch zurueck. Danach nur ueber die Geschaeftsstelle.</td><td>Entlastet die Geschaeftsstelle, bleibt im Rahmen unserer AGB.</td></tr>
                    <?php $render_row_actions('entscheidung-row-9'); ?>
                    <tr><td>10</td><td><strong>Welche E-Mails verschickt das System?</strong></td><td>Bestaetigung, Warteliste-Info, Nachrueck-Einladung, Storno-Bestaetigung &mdash; sofort und automatisch. Erinnerungen und Werbung weiter ueber das bestehende Marketing-Tool.</td><td>Wichtige Mails sofort. Marketing-Inhalte bleiben flexibel.</td></tr>
                    <?php $render_row_actions('entscheidung-row-10'); ?>
                    <tr><td>11</td><td><strong>Rabattcodes?</strong></td><td>Direkt ueber Stripe verwaltet &mdash; keine extra Pflege in unserem System.</td><td>Spart Aufwand. Es gibt nur eine Stelle, an der Codes gepflegt werden.</td></tr>
                    <?php $render_row_actions('entscheidung-row-11'); ?>
                    <tr><td>12</td><td><strong>Wie wird das Modul gebaut?</strong></td><td>Mit austauschbaren Bausteinen (siehe Abschnitt 4), damit Webinar- und Kongress-Buchung spaeter denselben Kern nutzen koennen.</td><td>Spart bei den naechsten Modulen Zeit und Geld.</td></tr>
                    <?php $render_row_actions('entscheidung-row-12'); ?>
                </tbody>
            </table>

            <h4 style="margin-top:24px;">Tickets, QR-Code und Mitgliederbereich</h4>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>#</th><th>Frage</th><th>Vorschlag</th><th>Begruendung</th></tr></thead>
                <tbody>
                    <tr><td>13</td><td><strong>Verbindung zu Zoho Backstage</strong></td><td>Wir pruefen, ob bereits in Backstage gepflegte Buchungen automatisch ins eigene Zoho CRM gespiegelt werden koennen. Mitglieder sehen alle Tickets an einer Stelle &mdash; unabhaengig von der Quelle (Backstage oder Homepage).</td><td>Keine doppelte Pflege. Eine einheitliche Sicht fuer Teilnehmer:innen.</td></tr>
                    <?php $render_row_actions('entscheidung-row-13'); ?>
                    <tr><td>14</td><td><strong>Ticketnummern</strong></td><td>Numerische Ticketnummern im gleichen Format wie Zoho Backstage (z.&thinsp;B. 8-stellige Zahlenfolge).</td><td>Damit beide Systeme kompatibel bleiben und Tickets eindeutig sind.</td></tr>
                    <?php $render_row_actions('entscheidung-row-14'); ?>
                    <tr><td>15</td><td><strong>QR-Code auf jedem Ticket</strong></td><td>Jedes Ticket traegt einen QR-Code mit der Ticketnummer. Geschaeftsstelle/Kursleitung kann am Workshop-Tag mit dem Smartphone scannen und sofort pruefen, ob das Ticket gueltig ist.</td><td>Schneller, ehrlicher Einlass. Kein manuelles Listenabhaken.</td></tr>
                    <?php $render_row_actions('entscheidung-row-15'); ?>
                    <tr><td>16</td><td><strong>Tickets im Mitgliederbereich</strong></td><td>Eingeloggte Mitglieder sehen auf der Seite &bdquo;Meine Tickets&ldquo; alle ihre Buchungen mit Termin, Status, QR-Code, Storno-Moeglichkeit und Zertifikat-Download.</td><td>Eine zentrale Anlaufstelle &mdash; entlastet die Geschaeftsstelle bei Standard-Anfragen.</td></tr>
                    <?php $render_row_actions('entscheidung-row-16'); ?>
                    <tr><td>17</td><td><strong>Zugang fuer Nicht-Mitglieder</strong></td><td>Personen ohne WordPress-Konto bekommen einen persoenlichen Link per E-Mail. Damit koennen sie ihr Ticket einsehen, downloaden und ggf. stornieren &mdash; ohne Anmeldung. Der Link laeuft nach einer festgelegten Frist ab.</td><td>Kein Zwang zur Registrierung, trotzdem komfortabler Zugriff.</td></tr>
                    <?php $render_row_actions('entscheidung-row-17'); ?>
                    <tr><td>18</td><td><strong>Auto-Zuordnung ueber E-Mail</strong></td><td>Bucht ein Mitglied ohne sich einzuloggen, erkennt das System die E-Mail-Adresse als bekannt und ordnet die Buchung automatisch dem Mitglieds-Kontakt zu. Es entsteht kein Doppel-Eintrag.</td><td>Keine Karteileichen, sauberer Datenbestand. Mitglied sieht das Ticket beim naechsten Login.</td></tr>
                    <?php $render_row_actions('entscheidung-row-18'); ?>
                </tbody>
            </table>

            <h4 style="margin-top:24px;">Teilnahmezertifikate</h4>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>#</th><th>Frage</th><th>Vorschlag</th><th>Begruendung</th></tr></thead>
                <tbody>
                    <tr><td>19</td><td><strong>Automatisches Zertifikat</strong></td><td>Sobald Geschaeftsstelle oder Kursleitung die Anwesenheit einer Person bestaetigt, erzeugt das System automatisch ein Teilnahme-Zertifikat als PDF. Es wird per E-Mail verschickt und ist im Mitgliederbereich abrufbar. Engine: Wiederverwendung der bestehenden Webinar-Loesung.</td><td>Spart der Geschaeftsstelle das manuelle Erstellen. Klarer Trigger (Anwesenheits-Bestaetigung) statt unklarer Zeit-Logik.</td></tr>
                    <?php $render_row_actions('entscheidung-row-19'); ?>
                    <tr><td>20</td><td><strong>Layout konfigurierbar</strong></td><td>Geschaeftsstelle kann pro Workshop ein Standard-Layout zuweisen oder ein eigenes hinterlegen.</td><td>Flexibilitaet fuer Sonder-Veranstaltungen, ohne dass jedes Mal ein:e Programmierer:in benoetigt wird.</td></tr>
                    <?php $render_row_actions('entscheidung-row-20'); ?>
                    <tr><td>21</td><td><strong>Externe Designer:innen</strong></td><td>Wer fuer die DGPTM Layouts gestaltet (z.&thinsp;B. Grafiker:innen), bekommt einen persoenlichen Link per E-Mail und kann das Zertifikat-Layout direkt im Browser bearbeiten &mdash; ohne WordPress-Zugang. Vorschau in Echtzeit.</td><td>Designer:innen brauchen keinen Account, kein VPN, keine Schulung. Geschaeftsstelle behaelt die Kontrolle ueber die Einladungen.</td></tr>
                    <?php $render_row_actions('entscheidung-row-21'); ?>
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
                    <tr><td><strong>Workshops lesen</strong></td><td>Holt aktive Workshops aus dem Zoho CRM auf die Webseite.</td></tr>
                    <tr><td><strong>Buchung pruefen</strong></td><td>Prueft, ob noch Plaetze frei sind, und legt die Anmeldung an.</td></tr>
                    <tr><td><strong>Bezahlung</strong></td><td>Schickt die Person zur Stripe-Bezahlseite und nimmt das Ergebnis entgegen.</td></tr>
                    <tr><td><strong>Zoho CRM schreiben</strong></td><td>Traegt die Anmeldung in unsere Teilnehmer:innen-Liste ein.</td></tr>
                    <tr><td><strong>E-Mails</strong></td><td>Versendet Bestaetigung, Warteliste, Storno &mdash; mit Termin-Anhang fuer den Kalender.</td></tr>
                    <tr><td><strong>Warteliste</strong></td><td>Ueberwacht freie Plaetze und benachrichtigt Nachruecker:innen automatisch.</td></tr>
                    <tr><td><strong>Webseite (Frontend)</strong></td><td>Was Nutzer:innen sehen: Workshop-Karten, Buchungsformular, Bestaetigungsseite.</td></tr>
                    <tr><td><strong>Mitgliederbereich-Anzeige</strong></td><td>Zeigt eingeloggten Mitgliedern alle ihre Tickets gesammelt &mdash; aus dem eigenen Tool und aus Zoho Backstage.</td></tr>
                    <tr><td><strong>QR-Code-Generator</strong></td><td>Erzeugt fuer jedes Ticket einen scanbaren Code mit der Ticketnummer.</td></tr>
                    <tr><td><strong>Ticketpruefung (Webtool)</strong></td><td>Mobile-Webseite, mit der Geschaeftsstelle/Kursleitung am Workshop-Tag Tickets prueft &mdash; per QR-Scan im Browser oder direkter Eingabe der Ticketnummer. Keine App-Installation noetig.</td></tr>
                    <tr><td><strong>Persoenliche-Link-Verwaltung</strong></td><td>Erzeugt und prueft die Zugangs-Links fuer Nicht-Mitglieder und externe Designer:innen.</td></tr>
                    <tr><td><strong>Zertifikat-Generator</strong></td><td>Erzeugt das Teilnahme-PDF nach dem Workshop &mdash; nutzt die bestehende Vimeo-Webinare-Engine.</td></tr>
                    <tr><td><strong>Backstage-Spiegelung</strong></td><td>Holt Buchungen, die direkt in Zoho Backstage entstanden sind, ins eigene Zoho CRM (Konzept &mdash; siehe offene Fragen).</td></tr>
                </tbody>
            </table>
            <p><em>Hintergrund:</em> Diese Trennung ermoeglicht es, einzelne Bausteine spaeter auszutauschen oder fuer Webinare und Kongresse wiederzuverwenden &mdash; ohne das ganze Modul anzufassen.</p>
            <?php $render_section_comments('section-architektur'); ?>
        </div>

        <!-- ───── Abschnitt 5 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-datenfluss">
            <h3>5. So laeuft eine Buchung ab</h3>
            <ol class="dgptm-wsb-evl-steps-list">
                <li><strong>Workshop entdecken</strong> &mdash; Auf der Webseite werden alle kommenden Workshops als Karten gezeigt. Die Inhalte kommen direkt aus dem Zoho CRM.</li>
                <li><strong>Ticket auswaehlen</strong> &mdash; Die Person waehlt ein Ticket (z.&thinsp;B. &bdquo;Vollpreis&ldquo;, &bdquo;Mitgliedspreis&ldquo;).</li>
                <li><strong>Daten eintragen</strong> &mdash; Bei eingeloggten Mitgliedern automatisch vorausgefuellt. Gaeste tragen Vor-/Nachname und E-Mail ein.</li>
                <li><strong>Bezahlen</strong> &mdash; Weiterleitung zur Bezahlseite von Stripe. Wer nicht zahlt, dessen Buchung verfaellt automatisch &mdash; der Platz wird wieder freigegeben.</li>
                <li><strong>Bestaetigung</strong> &mdash; Sofort nach Zahlungseingang: Bestaetigungs-E-Mail mit Kalender-Anhang und Ticket inklusive QR-Code.</li>
                <li><strong>Eintrag im Mitgliederbereich</strong> &mdash; Eingeloggte Mitglieder sehen ihr neues Ticket sofort auf der Seite &bdquo;Meine Tickets&ldquo;. Gaeste/Nicht-Mitglieder erhalten einen persoenlichen Link per E-Mail zur Ticket-Verwaltung.</li>
                <li><strong>E-Mail-Auto-Zuordnung</strong> &mdash; Wer ohne Login bucht, dessen E-Mail wird mit den bekannten Mitglieder-Adressen abgeglichen. Bei Treffer wird die Buchung automatisch dem Mitglieds-Kontakt zugeordnet &mdash; kein Doppel-Eintrag.</li>
                <li><strong>Falls voll</strong> &mdash; Die Person landet automatisch auf der Warteliste. Wird ein Platz frei, erhaelt sie eine E-Mail mit 24-Stunden-Zahlungslink.</li>
                <li><strong>Nach dem Workshop</strong> &mdash; Geschaeftsstelle/Kursleitung markiert Teilnehmer:innen als anwesend. Das System erstellt automatisch das Zertifikat-PDF und versendet es per E-Mail. Mitglieder finden es zusaetzlich im Mitgliederbereich.</li>
            </ol>
            <?php $render_section_comments('section-datenfluss'); ?>
        </div>

        <!-- ───── Abschnitt 6 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-kompatibilitaet">
            <h3>6. Zusammenspiel mit bestehenden Funktionen</h3>
            <ul>
                <li><strong>Zoho Backstage:</strong> Tickets, die direkt in Backstage angelegt wurden, werden ins eigene Zoho CRM gespiegelt. Mitglieder sehen <em>alle</em> ihre Tickets im Mitgliederbereich an einer Stelle &mdash; egal aus welchem Tool. Wie genau die Spiegelung erfolgen soll (Echtzeit, Cron-Lauf, einmaliger Import), ist eine offene Frage (siehe Abschnitt 10).</li>
                <li><strong>Edugrant-Foerderung:</strong> Wenn fuer einen Workshop eine Foerderung verfuegbar ist, erscheint auf der Buchungs-Karte ein Hinweis und ein Link zum Foerderantrag. Das vermeidet doppelte Erfassung.</li>
                <li><strong>Webinar-Buchung (in Vorbereitung):</strong> Die Bausteine fuer Mitglieder-Erkennung und Anmelde-Datensaetze werden so gebaut, dass die Webinar-Buchung sie spaeter direkt mitnutzen kann &mdash; ohne Doppelarbeit.</li>
                <li><strong>vimeo-webinare (live):</strong> Bleibt unveraendert. Wir nutzen aber dessen bestehende Zertifikat-Engine fuer das neue Workshop-Zertifikat &mdash; einmal gebaut, an zwei Stellen verwendet.</li>
            </ul>
            <?php $render_section_comments('section-kompatibilitaet'); ?>
        </div>

        <!-- ───── Abschnitt 7 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-crm-erweiterungen">
            <h3>7. Anpassungen im Zoho CRM</h3>
            <p>Damit das Modul funktioniert, sind ein paar kleine Anpassungen im internen Zoho CRM noetig. Diese muessen mit der Geschaeftsstelle abgestimmt werden.</p>
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
                    <li><em>Anwesend</em> &mdash; bestaetigt teilgenommen, Zertifikat erstellt</li>
                </ul>
                <em>&rarr; Abstimmung mit Verantwortlichen fuer den Anmelde-Workflow noetig.</em>
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Ticketnummer</strong> &mdash; neues Feld fuer eine numerische, eindeutige Ticketnummer (Format identisch zu Zoho Backstage, z.&thinsp;B. 8-stellig). Wird beim Anlegen automatisch vergeben.
                <em>&rarr; Format mit Backstage-Verantwortlichen abstimmen.</em>
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Zertifikat-Layout</strong> &mdash; optionales Feld am Workshop, das auf ein hinterlegtes Layout verweist. Leer = Standard-Layout.
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>WordPress-seitig: Tabelle fuer persoenliche Links</strong> &mdash; speichert die Zugangs-Links fuer Nicht-Mitglieder und externe Designer:innen mit Ablaufdatum. Vorbild: bestehende Loesung im Stipendien-Modul (Gutachter:innen-Token).
                <em>&rarr; rein technisch, keine Abstimmung mit Geschaeftsstelle noetig.</em>
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
                    <tr><td><strong>Zoho CRM</strong></td><td>Workshop-Daten lesen, Anmeldungen schreiben</td><td>aktiv, Zugriffsrechte vorhanden</td></tr>
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
                <li>Vollstaendige Migration alter Buchungen aus dem alten System &mdash; nur die kuenftigen und ggf. die noch aktiven Backstage-Buchungen werden abgebildet.</li>
                <li>Anwesenheits-Erfassung per QR-Scanner-App: in V1 erfolgt das Scannen mit dem Smartphone-Browser auf einer einfachen Kontroll-Seite. Eine eigene App ist nicht geplant.</li>
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
                        <tr><td>5</td><td><strong>Backstage-Spiegelung &mdash; wie genau?</strong></td><td>Vorschlag: regelmaessiger Abgleich (z.&thinsp;B. alle 15 Min) statt Echtzeit-Synchronisation. Einfacher, robuster, ausreichend.</td></tr>
                        <tr><td>6</td><td><strong>Backstage-Migration</strong></td><td>Sollen alte Backstage-Buchungen einmalig importiert werden, oder nur kuenftige? Vorschlag: nur kuenftige und alle <em>noch aktiven</em> Backstage-Tickets.</td></tr>
                        <tr><td>7</td><td><strong>Format der Ticketnummer</strong></td><td>Welches genaue Format hat die Backstage-Ticketnummer? (Stellenanzahl, Bereich) &mdash; muss mit Backstage-Verantwortlichen geklaert werden.</td></tr>
                        <tr><td>8</td><td><strong>Gueltigkeit der persoenlichen Links</strong> (Nicht-Mitglieder)</td><td>Vorschlag: bis Workshop-Ende plus 30 Tage &mdash; lange genug fuer Zertifikat-Download, kurz genug fuer Datensparsamkeit.</td></tr>
                        <tr><td>9</td><td><strong>Wer darf Designer:innen einladen?</strong></td><td>Vorschlag: nur Geschaeftsstelle. Kontrolle bleibt zentral.</td></tr>
                        <tr><td>10</td><td><strong>Standard-Layout des Zertifikats</strong></td><td>Welches der vorhandenen Webinar-Layouts (classic, corporate, elegant, minimal) wird Standard? Oder ein neues, eigens fuer Workshops?</td></tr>
                        <tr><td>11</td><td><strong>Wie wird Anwesenheit erfasst?</strong></td><td>Vorschlag: per QR-Code-Scan am Einlass <em>und</em> manuelle Nachpflege moeglich. Bei Online-Workshops: aus Zoom/Vimeo-Anwesenheitsdaten oder manuell.</td></tr>
                    </tbody>
                </table>
            </div>
            <?php $render_section_comments('section-offene-punkte'); ?>
        </div>

        <!-- ───── Abschnitt 11 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-zertifikate">
            <h3>11. Teilnahmezertifikate</h3>

            <p>Nach jedem Workshop sollen Teilnehmer:innen automatisch ein Zertifikat als PDF erhalten. Das Modul nutzt dafuer die bereits vorhandene Engine aus den vimeo-webinaren &mdash; einmal gebaut, an zwei Stellen verwendet.</p>

            <h4>Wann entsteht das Zertifikat?</h4>
            <ol>
                <li>Geschaeftsstelle oder Kursleitung markiert nach dem Workshop, wer anwesend war.</li>
                <li>Das System erzeugt fuer jede:n Anwesende:n automatisch ein Zertifikat-PDF.</li>
                <li>Das PDF wird per E-Mail verschickt.</li>
                <li>Mitglieder finden das PDF zusaetzlich im Mitgliederbereich unter &bdquo;Meine Tickets&ldquo;.</li>
                <li>Nicht-Mitglieder rufen es ueber ihren persoenlichen Link ab.</li>
            </ol>

            <h4>Was steht auf dem Zertifikat?</h4>
            <ul>
                <li>Name der Teilnehmer:in</li>
                <li>Workshop-Titel und Datum</li>
                <li>Ort/Format (Praesenz oder online)</li>
                <li>Optionale Fortbildungspunkte und VNR-Nummer</li>
                <li>Unterschrift, Logo, Wasserzeichen &mdash; je nach Layout</li>
            </ul>

            <h4>Layout pro Workshop</h4>
            <p>Geschaeftsstelle kann pro Workshop entscheiden:</p>
            <ul>
                <li><strong>Standard-Layout</strong> &mdash; vordefiniertes DGPTM-Layout, Empfehlung fuer die meisten Workshops.</li>
                <li><strong>Eigenes Layout</strong> &mdash; bei Sonder-Veranstaltungen (z.&thinsp;B. Jubilaeum, Kooperations-Workshops) kann ein gestaltetes Layout hinterlegt werden.</li>
            </ul>

            <h4>Externe Designer:innen ohne WordPress-Zugang</h4>
            <div class="dgptm-wsb-evl-highlight blue">
                <p><strong>Idee:</strong> Wer fuer die DGPTM Layouts gestaltet (z.&thinsp;B. eine externe Grafikerin), bekommt einen persoenlichen Link per E-Mail. Damit kann er/sie das Zertifikat-Layout im Browser bearbeiten &mdash; ohne sich in WordPress anzumelden.</p>
                <p><strong>Konkret:</strong></p>
                <ol>
                    <li>Geschaeftsstelle traegt E-Mail-Adresse der Designerin in eine Einladungs-Maske ein und legt fest, fuer welchen Workshop das Layout gelten soll.</li>
                    <li>Designerin erhaelt eine E-Mail mit persoenlichem Link.</li>
                    <li>Sie oeffnet den Link, sieht den Layout-Editor, kann Hintergrundbild, Logo, Texte, Schriften aendern.</li>
                    <li>Vorschau-PDF in Echtzeit, ohne Speichern.</li>
                    <li>Speichern: das Layout ist sofort fuer den Workshop aktiv.</li>
                    <li>Der Link laeuft nach festgelegter Frist ab (Vorschlag: 14 Tage), kann aber jederzeit von der Geschaeftsstelle widerrufen werden.</li>
                </ol>
                <p><strong>Sicherheit:</strong> Der Link ist nur fuer dieses eine Workshop-Layout gueltig. Designerin sieht keine Teilnehmer:innen-Daten, keine Buchungen, kein anderes Modul.</p>
            </div>

            <h4>Vorbild im Repo</h4>
            <p>Die Token-Logik wird vom Stipendien-Modul (Gutachter:innen-Zugang) wiederverwendet. Die PDF-Engine kommt aus den vimeo-webinaren. Beides ist im Einsatz und bewaehrt &mdash; kein Neubau, sondern Wiederverwendung.</p>

            <?php $render_section_comments('section-zertifikate'); ?>
        </div>

    </div><!-- /.dgptm-wsb-evl-dokument -->

    <!-- Sticky Footer -->
    <div class="dgptm-wsb-evl-footer">
        <div class="dgptm-wsb-evl-footer-inner">
            <div class="dgptm-wsb-evl-footer-left">
                <?php echo $approval_count; ?> Freigabe<?php echo $approval_count !== 1 ? 'n' : ''; ?> | <?php echo count($comments); ?> Kommentar<?php echo count($comments) !== 1 ? 'e' : ''; ?>
            </div>
            <?php if (!$user_approved) : ?>
                <button type="button" class="dgptm-fe-btn dgptm-fe-btn-primary" id="dgptm-wsb-evl-approve-footer">
                    Entscheidungsvorlage freigeben
                </button>
            <?php else : ?>
                <span class="dgptm-wsb-evl-approved-badge">&#10003; Freigegeben</span>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.dgptm-wsb-evl-wrapper -->
