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
                                <button type="button" class="dgptm-wsb-evl-comment-delete" data-id="<?php echo esc_attr($c['id']); ?>" title="Kommentar löschen">&times;</button>
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
 * Hinweis: Buttons innerhalb der nächsten Closure verwenden
 * die globalen dgptm-fe-btn Klassen aus core/assets/css/dgptm-shared-buttons.css.
 * Modulspezifische Klassen (dgptm-wsb-evl-row-*) bleiben für JS-Selektoren erhalten.
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
                        title="<?php echo $approved ? 'Klicken zum Zurücknehmen' : 'Diesem Vorschlag zustimmen'; ?>">
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
                                    <button type="button" class="dgptm-wsb-evl-comment-delete" data-id="<?php echo esc_attr($c['id']); ?>" title="Kommentar löschen">&times;</button>
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
                    Freigabe zurückziehen
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if (current_user_can('manage_options')) : ?>
        <div class="dgptm-wsb-evl-admin-bar" style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px 16px;margin:12px 0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="font-size:13px;color:#9a3412;">
                <strong>Admin:</strong> Alle Kommentare eingearbeitet? Beteiligte mit einer Mail informieren.
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <label style="font-size:13px;color:#9a3412;display:flex;gap:6px;align-items:center;">
                    <input type="checkbox" id="dgptm-wsb-evl-mark-read-toggle" checked>
                    Kommentare als &bdquo;eingearbeitet&ldquo; markieren
                </label>
                <button type="button" class="dgptm-fe-btn dgptm-fe-btn-primary dgptm-fe-btn-small" id="dgptm-wsb-evl-implemented">
                    Beteiligte benachrichtigen
                </button>
            </div>
        </div>
    <?php endif; ?>

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
            <h2>Workshops und Webinare aus Backstage auslagern und über die Homepage buchbar machen</h2>
            <p class="dgptm-wsb-evl-subtitle">Was wird gebaut? Was muss noch entschieden werden?</p>
            <p class="dgptm-wsb-evl-meta">DGPTM | Stand: 25.04.2026 (erweitert um Tickets, Mitgliederbereich, Teilnahmebescheinigungen) | Empfänger:innen: Vorstand, Geschäftsstelle, Kursleitungen</p>
        </div>

        <div class="dgptm-wsb-evl-highlight blue">
            <strong>Worum dich dieses Dokument bittet:</strong>
            <ol style="margin:6px 0 0 18px;">
                <li>Lies die geplanten Entscheidungen (Abschnitt 3) und die offenen Fragen (Abschnitt 10).</li>
                <li>Hinterlasse pro Abschnitt einen Kommentar, wenn du etwas anders entscheiden möchtest.</li>
                <li>Klicke auf <em>&bdquo;Entscheidungsvorlage freigeben&ldquo;</em>, sobald du einverstanden bist.</li>
            </ol>
        </div>

        <!-- ───── Abschnitt 1 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-ziel">
            <h3>1. Worum geht es?</h3>
            <p>Bisher läuft die Buchung <em>aller</em> Veranstaltungen über <strong>Zoho Backstage</strong>. Für große Formate (Kongresse, Sachkundekurse) ist das passend &mdash; für kleine Workshops und Webinare verursacht es überproportionalen Einrichtungs- und Wartungsaufwand pro Veranstaltung. Wir wollen diese Formate daher direkt über die DGPTM-Webseite buchbar machen.</p>
            <div class="dgptm-wsb-evl-highlight blue">
                <strong>Geltungsbereich V1 (festgelegt 29.04.2026):</strong> Workshops und Webinare. <strong>Kongresse und Sachkundekurse bleiben bis auf Weiteres in Zoho Backstage</strong> &mdash; sie sind nicht Teil dieses Moduls.
            </div>
            <p><strong>Hauptmotivation:</strong></p>
            <ul>
                <li><strong>Entkopplung kleinerer Veranstaltungen von Zoho Backstage</strong> &mdash; reduziert den hohen Einrichtungs- und Wartungsaufwand, der dort pro Workshop aktuell anfällt.</li>
                <li><strong>Eigenverantwortung der Arbeitsgemeinschaften</strong> &mdash; AGs können ihre Workshops selbst anlegen. Die Geschäftsstelle prüft und gibt frei, bevor die Buchung öffentlich wird.</li>
                <li><strong>Einheitliche Sicht für Teilnehmer:innen</strong> &mdash; Buchungen aus dem neuen Tool und aus Backstage erscheinen gemeinsam im Mitgliederbereich (siehe Punkt 13).</li>
            </ul>
            <p>Mit dem neuen Modul soll Folgendes möglich werden:</p>
            <ul>
                <li>Workshops, die bereits intern gepflegt sind, erscheinen automatisch auf der Webseite.</li>
                <li>Mitglieder und Gäste können mit wenigen Klicks anmelden.</li>
                <li>Die Bezahlung läuft sicher über unseren bestehenden Zahlungsanbieter.</li>
                <li>Die Anmeldung wird automatisch ins Zoho CRM eingetragen.</li>
                <li>Der/die Teilnehmer:in erhält sofort eine Bestätigung mit Termin für den Kalender.</li>
                <li>Die Geschäftsstelle erhält automatisch eine Mail, sobald eine AG einen neuen Kurs einträgt &mdash; analog zum Workflow bei Kursen für die Zertifizierung.</li>
                <li>Mitglieder sehen alle ihre Tickets (auch ältere und solche aus Zoho Backstage) im Mitgliederbereich an einer Stelle.</li>
                <li>Jedes Ticket trägt einen QR-Code für die Einlasskontrolle am Workshop-Tag.</li>
                <li>Nicht-Mitglieder bekommen einen persönlichen Link per E-Mail &mdash; ohne Login.</li>
                <li>Nach dem Workshop entsteht automatisch ein Teilnahmebescheinigung als PDF.</li>
            </ul>
            <p><em>Hinweis:</em> Das Modul ist der erste Baustein. Eine Erweiterung auf Kongresse und Sachkundekurse ist <em>nicht</em> Teil dieser Vorlage und würde nach erfolgreichem V1-Betrieb separat geplant.</p>
            <?php $render_section_comments('section-ziel'); ?>
        </div>

        <!-- ───── Abschnitt 2 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-ausgangslage">
            <h3>2. Was haben wir heute?</h3>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>Heute vorhanden</th><th>Was fehlt / belastet</th></tr></thead>
                <tbody>
                    <tr><td>Alle Veranstaltungen (Workshops, Kongresse, Sachkundekurse) werden in <strong>Zoho Backstage</strong> angelegt und buchbar gemacht.</td><td>Pro Workshop fällt der gleiche Einrichtungs- und Wartungsaufwand wie für eine große Veranstaltung an &mdash; unverhältnismäßig für kleine Formate.</td></tr>
                    <tr><td>Auf der Webseite werden Workshops bereits als Liste angezeigt (Edugrant-Modul).</td><td>Direkte Buchung von der Webseite aus ist nicht möglich &mdash; Klick führt nach Backstage.</td></tr>
                    <tr><td>Bezahlung per Kreditkarte/SEPA gibt es bereits an anderer Stelle.</td><td>Diese ist an alte Formulare gekoppelt und nicht für Workshops nutzbar.</td></tr>
                    <tr><td>Webinare laufen über eine eigene, fertige Lösung (vimeo-webinare).</td><td>Sie hat keine Anmelde-/Buchungs-/Ticketlogik &mdash; das war auch nicht ihr Zweck.</td></tr>
                </tbody>
            </table>
            <p><strong>Konsequenz heute:</strong> Jede Arbeitsgemeinschaft, die einen Workshop machen will, ist auf die Geschäftsstelle angewiesen, um in Backstage eine vollständige Veranstaltungs-Konfiguration anzulegen. Das skaliert nicht und bremst kleine Formate aus.</p>
            <?php $render_section_comments('section-ausgangslage'); ?>
        </div>

        <!-- ───── Abschnitt 3 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-entscheidungen">
            <h3>3. Fragestellungen zur Entscheidung</h3>
            <p>Diese Vorschläge liegen dem geplanten Modul zugrunde. Du kannst jedem Vorschlag <em>einzeln zustimmen</em> oder einen <em>eigenen Kommentar</em> hinterlassen. Die Gesamt-Freigabe am Ende der Seite gilt als &bdquo;Ich trage die Vorlage als Ganzes mit&ldquo;.</p>

            <h4 style="margin-top:16px;">Buchungs-Logik</h4>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>#</th><th>Frage</th><th>Vorschlag</th><th>Begründung</th></tr></thead>
                <tbody>
                    <tr><td>1</td><td><strong>Wie gross soll das Modul sein?</strong></td><td>Erst nur Workshops. Bausteine werden so gebaut, dass Webinare/Kongresse später daran andocken können.</td><td>Schritt für Schritt &mdash; kein Mammut-Projekt, aber zukunftsfähig.</td></tr>
                    <?php $render_row_actions('entscheidung-row-1'); ?>
                    <tr><td>2</td><td><strong>Müssen Buchende eingeloggt sein?</strong></td><td>Beides: Mitglieder buchen mit einem Klick. Gäste füllen ein kurzes Formular aus.</td><td>Maximal einfach für Mitglieder, gleichzeitig offen für Externe.</td></tr>
                    <?php $render_row_actions('entscheidung-row-2'); ?>
                    <tr><td>3</td><td><strong>Wie wird bezahlt?</strong></td><td>Über Stripe (gleicher Anbieter wie heute). Die Bezahlseite läuft direkt bei Stripe &mdash; wir berühren keine Kreditkartendaten. Workshops laufen über ein <strong>eigenes Stripe-Unterkonto</strong>, getrennt vom DGPTM-Hauptkonto.</td><td>Sicher (internationaler Sicherheitsstandard), Apple Pay und SEPA-Lastschrift automatisch dabei. Eigenes Unterkonto für Workshops sorgt für klare buchhalterische Trennung.</td></tr>
                    <?php $render_row_actions('entscheidung-row-3'); ?>
                    <tr><td>4</td><td><strong>Was, wenn jemand mehrere Plätze bucht?</strong></td><td>Pro Person ein eigener Eintrag mit eigenen Kontaktdaten.</td><td>Damit jede:r Teilnehmer:in eine eigene Bestätigung und ggf. Fortbildungspunkte erhält.</td></tr>
                    <?php $render_row_actions('entscheidung-row-4'); ?>
                    <tr><td>5</td><td><strong>Welche Daten werden erfasst?</strong></td><td>Pflicht: Vor- und Nachname, E-Mail. Adresse nur, wenn benötigt. Bei bekannten Mitgliedern werden vorhandene Daten automatisch eingeblendet.</td><td>So wenig wie möglich, so viel wie nötig.</td></tr>
                    <?php $render_row_actions('entscheidung-row-5'); ?>
                    <tr><td>6</td><td><strong>Wie erkennt das System Bestandsmitglieder?</strong></td><td>Es prüft alle bekannten E-Mail-Adressen. Findet es niemanden, wird ein neuer Kontakt angelegt.</td><td>Keine Doppel-Einträge, keine Karteileichen.</td></tr>
                    <?php $render_row_actions('entscheidung-row-6'); ?>
                    <tr><td>7</td><td><strong>Wie wird die Buchung in die Webseite eingebunden?</strong></td><td>Über zwei Platzhalter, die die Geschäftsstelle frei auf jeder Seite einbauen kann.</td><td>Volle Gestaltungsfreiheit für die Geschäftsstelle.</td></tr>
                    <?php $render_row_actions('entscheidung-row-7'); ?>
                    <tr><td>8</td><td><strong>Was, wenn ein Workshop voll ist?</strong></td><td>Automatische Warteliste. Wird ein Platz frei, hat die nächste Person 24 Stunden Zeit zum Buchen.</td><td>Faire Reihenfolge ohne dauerhafte Sperre.</td></tr>
                    <?php $render_row_actions('entscheidung-row-8'); ?>
                    <tr><td>9</td><td><strong>Storno durch Teilnehmer:in?</strong></td><td>Bis zu einer Frist (Vorschlag: 14 Tage vor dem Workshop) selbst möglich, Geld kommt automatisch zurück. Danach nur über die Geschäftsstelle.</td><td>Entlastet die Geschäftsstelle, bleibt im Rahmen unserer AGB.</td></tr>
                    <?php $render_row_actions('entscheidung-row-9'); ?>
                    <tr><td>10</td><td><strong>Welche E-Mails verschickt das System?</strong></td><td>Bestätigung, Warteliste-Info, Nachrück-Einladung, Storno-Bestätigung &mdash; sofort und automatisch. Erinnerungen und Werbung weiter über das bestehende Marketing-Tool.</td><td>Wichtige Mails sofort. Marketing-Inhalte bleiben flexibel.</td></tr>
                    <?php $render_row_actions('entscheidung-row-10'); ?>
                    <tr><td>11</td><td><strong>Rabattcodes?</strong></td><td>Direkt über Stripe verwaltet &mdash; keine extra Pflege in unserem System.</td><td>Spart Aufwand. Es gibt nur eine Stelle, an der Codes gepflegt werden.</td></tr>
                    <?php $render_row_actions('entscheidung-row-11'); ?>
                    <tr><td>12</td><td><strong>Wie wird das Modul gebaut?</strong></td><td>Mit austauschbaren Bausteinen (siehe Abschnitt 4), damit Webinar- und Kongress-Buchung später denselben Kern nutzen können.</td><td>Spart bei den nächsten Modulen Zeit und Geld.</td></tr>
                    <?php $render_row_actions('entscheidung-row-12'); ?>
                    <tr><td>12a</td><td><strong>Mehrsprachigkeit (DE/EN)</strong></td><td>Frontend, Buchungsformular, Bestätigungsmails und Teilnahmebescheinigung sind von Anfang an zweisprachig vorbereitet (Deutsch und Englisch). Pro Workshop wird eine Sprache gewählt; bei englischsprachigen Veranstaltungen (z.&thinsp;B. International Webinar) erhalten Teilnehmer:innen automatisch englische Texte.</td><td>Vermeidet späteren Komplett-Umbau. Internationale Webinare und englischsprachige Workshops sind so direkt am Start unterstützt.</td></tr>
                    <?php $render_row_actions('entscheidung-row-12a'); ?>
                </tbody>
            </table>

            <h4 style="margin-top:24px;">Tickets, QR-Code und Mitgliederbereich</h4>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>#</th><th>Frage</th><th>Vorschlag</th><th>Begründung</th></tr></thead>
                <tbody>
                    <tr><td>13</td><td><strong>Verbindung zu Zoho Backstage</strong></td><td>Wir prüfen, ob bereits in Backstage gepflegte Buchungen automatisch ins eigene Zoho CRM gespiegelt werden können. Mitglieder sehen alle Tickets an einer Stelle &mdash; unabhängig von der Quelle (Backstage oder Homepage).</td><td>Keine doppelte Pflege. Eine einheitliche Sicht für Teilnehmer:innen.</td></tr>
                    <?php $render_row_actions('entscheidung-row-13'); ?>
                    <tr><td>14</td><td><strong>Ticketnummern</strong></td><td>Numerische Ticketnummern im gleichen Format wie Zoho Backstage (z.&thinsp;B. 8-stellige Zahlenfolge).</td><td>Damit beide Systeme kompatibel bleiben und Tickets eindeutig sind.</td></tr>
                    <?php $render_row_actions('entscheidung-row-14'); ?>
                    <tr><td>15</td><td><strong>QR-Code auf jedem Ticket</strong></td><td>Jedes Ticket trägt einen QR-Code mit der Ticketnummer. Geschäftsstelle/Kursleitung kann am Workshop-Tag mit dem Smartphone scannen und sofort prüfen, ob das Ticket gültig ist.</td><td>Schneller, ehrlicher Einlass. Kein manuelles Listenabhaken.</td></tr>
                    <?php $render_row_actions('entscheidung-row-15'); ?>
                    <tr><td>16</td><td><strong>Tickets im Mitgliederbereich</strong></td><td>Eingeloggte Mitglieder sehen auf der Seite &bdquo;Meine Tickets&ldquo; alle ihre Buchungen mit Termin, Status, QR-Code, Storno-Möglichkeit und Download der Teilnahmebescheinigung.</td><td>Eine zentrale Anlaufstelle &mdash; entlastet die Geschäftsstelle bei Standard-Anfragen.</td></tr>
                    <?php $render_row_actions('entscheidung-row-16'); ?>
                    <tr><td>17</td><td><strong>Zugang für Nicht-Mitglieder</strong></td><td>Personen ohne WordPress-Konto bekommen einen persönlichen Link per E-Mail. Damit können sie ihr Ticket einsehen, downloaden und ggf. stornieren &mdash; ohne Anmeldung. Der Link läuft nach einer festgelegten Frist ab.</td><td>Kein Zwang zur Registrierung, trotzdem komfortabler Zugriff.</td></tr>
                    <?php $render_row_actions('entscheidung-row-17'); ?>
                    <tr><td>18</td><td><strong>Auto-Zuordnung über E-Mail</strong></td><td>Bucht ein Mitglied ohne sich einzuloggen, erkennt das System die E-Mail-Adresse als bekannt und ordnet die Buchung automatisch dem Mitglieds-Kontakt zu. Es entsteht kein Doppel-Eintrag.</td><td>Keine Karteileichen, sauberer Datenbestand. Mitglied sieht das Ticket beim nächsten Login.</td></tr>
                    <?php $render_row_actions('entscheidung-row-18'); ?>
                </tbody>
            </table>

            <h4 style="margin-top:24px;">Teilnahmezertifikate</h4>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>#</th><th>Frage</th><th>Vorschlag</th><th>Begründung</th></tr></thead>
                <tbody>
                    <tr><td>19</td><td><strong>Automatische Teilnahmebescheinigung</strong></td><td>Sobald Geschäftsstelle oder Kursleitung die Anwesenheit einer Person bestätigt, erzeugt das System automatisch eine Teilnahmebescheinigung als PDF. Es wird per E-Mail verschickt und ist im Mitgliederbereich abrufbar. Engine: Wiederverwendung der bestehenden Webinar-Lösung.</td><td>Spart der Geschäftsstelle das manuelle Erstellen. Klarer Trigger (Anwesenheits-Bestätigung) statt unklarer Zeit-Logik.</td></tr>
                    <?php $render_row_actions('entscheidung-row-19'); ?>
                    <tr><td>20</td><td><strong>Layout konfigurierbar</strong></td><td>Geschäftsstelle kann pro Workshop ein Standard-Layout zuweisen oder ein eigenes hinterlegen.</td><td>Flexibilität für Sonder-Veranstaltungen, ohne dass jedes Mal ein:e Programmierer:in benötigt wird.</td></tr>
                    <?php $render_row_actions('entscheidung-row-20'); ?>
                    <tr><td>21</td><td><strong>Externe Designer:innen</strong></td><td>Wer für die DGPTM Layouts gestaltet (z.&thinsp;B. Grafiker:innen), bekommt einen persönlichen Link per E-Mail und kann das Layout der Teilnahmebescheinigung direkt im Browser bearbeiten &mdash; ohne WordPress-Zugang. Vorschau in Echtzeit.</td><td>Designer:innen brauchen keinen Account, kein VPN, keine Schulung. Geschäftsstelle behält die Kontrolle über die Einladungen.</td></tr>
                    <?php $render_row_actions('entscheidung-row-21'); ?>
                </tbody>
            </table>

            <h4 style="margin-top:24px;">Erweiterte Funktionen</h4>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>#</th><th>Frage</th><th>Vorschlag</th><th>Begründung</th></tr></thead>
                <tbody>
                    <tr><td>22</td><td><strong>Variable Pflichtfelder pro Ticket-Typ</strong></td><td>Pro Ticket-Typ können zusätzliche Pflichtfelder definiert werden (z.&thinsp;B. für Sponsorenkarten Firmenname und USt-ID, für Ehrenamts-Tickets Bestätigung der Funktion). Verwaltung im CRM am Workshop-Datensatz.</td><td>Vermeidet Sammelmails, hebt Sonderbedingungen direkt im Buchungsfluss ab.</td></tr>
                    <?php $render_row_actions('entscheidung-row-22'); ?>
                    <tr><td>23</td><td><strong>Webinar-Modul direkt verbunden</strong></td><td>Das bestehende vimeo-webinare-Modul wird von Anfang an angebunden. Webinar-Anmeldungen laufen über denselben Buchungs-Kern (Tickets, Zahlung, QR, Mitgliederbereich, Teilnahmebescheinigung). Keine spätere Migration nötig.</td><td>Eine Buchungs-Logik für Workshops und Webinare. Spart Doppelbau und reduziert Wartungsaufwand.</td></tr>
                    <?php $render_row_actions('entscheidung-row-23'); ?>
                </tbody>
            </table>
            <?php $render_section_comments('section-entscheidungen'); ?>
        </div>

        <!-- ───── Abschnitt 4 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-architektur">
            <h3>4. Wie ist die Lösung aufgebaut?</h3>
            <p>Das Modul besteht aus klar getrennten Bausteinen. Jeder Baustein hat eine Aufgabe und kann für spätere Module (Webinare, Kongresse) wiederverwendet werden.</p>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>Baustein</th><th>Aufgabe</th></tr></thead>
                <tbody>
                    <tr><td><strong>Workshops lesen</strong></td><td>Holt aktive Workshops aus dem Zoho CRM auf die Webseite.</td></tr>
                    <tr><td><strong>Buchung prüfen</strong></td><td>Prüft, ob noch Plätze frei sind, und legt die Anmeldung an.</td></tr>
                    <tr><td><strong>Bezahlung</strong></td><td>Schickt die Person zur Stripe-Bezahlseite und nimmt das Ergebnis entgegen.</td></tr>
                    <tr><td><strong>Rechnung (Zoho Books)</strong></td><td>Erstellt nach erfolgreicher Zahlung automatisch eine Rechnung in Zoho Books und hängt sie der Bestätigungsmail an. Vorgang und Vorlagen sind noch mit der Geschäftsstelle abzustimmen (siehe offene Frage 14).</td></tr>
                    <tr><td><strong>Zoho CRM schreiben</strong></td><td>Trägt die Anmeldung in unsere Teilnehmer:innen-Liste ein.</td></tr>
                    <tr><td><strong>E-Mails</strong></td><td>Versendet Bestätigung, Warteliste, Storno, Termin-Verlegung &mdash; mit Termin-Anhang für den Kalender.</td></tr>
                    <tr><td><strong>Termin-Verlegung</strong></td><td>Wenn Geschäftsstelle/Kursleitung einen Workshop verlegen muss: Geänderter Termin im CRM ergibt automatisch (1) Update aller Tickets, (2) Mail an alle Teilnehmer:innen mit neuem Termin und neuem Kalender-Anhang, (3) Möglichkeit zum Storno mit voller Erstattung innerhalb einer festgelegten Frist.</td></tr>
                    <tr><td><strong>Warteliste</strong></td><td>Überwacht freie Plätze und benachrichtigt Nachrücker:innen automatisch.</td></tr>
                    <tr><td><strong>Webseite (Frontend)</strong></td><td>Was Nutzer:innen sehen: Workshop-Karten, Buchungsformular, Bestätigungsseite.</td></tr>
                    <tr><td><strong>Mitgliederbereich-Anzeige</strong></td><td>Zeigt eingeloggten Mitgliedern alle ihre Tickets gesammelt &mdash; aus dem eigenen Tool und aus Zoho Backstage.</td></tr>
                    <tr><td><strong>QR-Code-Generator</strong></td><td>Erzeugt für jedes Ticket einen scanbaren Code mit der Ticketnummer.</td></tr>
                    <tr><td><strong>Ticketprüfung (Webtool)</strong></td><td>Mobile-Webseite, mit der Geschäftsstelle/Kursleitung am Workshop-Tag Tickets prüft &mdash; per QR-Scan im Browser oder direkter Eingabe der Ticketnummer. Keine App-Installation nötig.</td></tr>
                    <tr><td><strong>Persönliche-Link-Verwaltung</strong></td><td>Erzeugt und prüft die Zugangs-Links für Nicht-Mitglieder und externe Designer:innen.</td></tr>
                    <tr><td><strong>Bescheinigungs-Generator</strong></td><td>Erzeugt das Teilnahme-PDF nach dem Workshop &mdash; nutzt die bestehende Vimeo-Webinare-Engine.</td></tr>
                    <tr><td><strong>Backstage-Spiegelung</strong></td><td>Holt Buchungen, die direkt in Zoho Backstage entstanden sind, ins eigene Zoho CRM (Konzept &mdash; siehe offene Fragen).</td></tr>
                </tbody>
            </table>
            <p><em>Hintergrund:</em> Diese Trennung ermöglicht es, einzelne Bausteine später auszutauschen oder für Webinare und Kongresse wiederzuverwenden &mdash; ohne das ganze Modul anzufassen.</p>
            <?php $render_section_comments('section-architektur'); ?>
        </div>

        <!-- ───── Abschnitt 5 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-datenfluss">
            <h3>5. So läuft eine Buchung ab</h3>
            <ol class="dgptm-wsb-evl-steps-list">
                <li><strong>Workshop entdecken</strong> &mdash; Auf der Webseite werden alle kommenden Workshops als Karten gezeigt. Die Inhalte kommen direkt aus dem Zoho CRM.</li>
                <li><strong>Ticket auswählen</strong> &mdash; Die Person wählt ein Ticket (z.&thinsp;B. &bdquo;Vollpreis&ldquo;, &bdquo;Mitgliedspreis&ldquo;).</li>
                <li><strong>Daten eintragen / Daten prüfen</strong> &mdash; Bei eingeloggten Mitgliedern werden hinterlegte Stammdaten vorausgefüllt; die Person prüft sie und korrigiert ggf. Gäste tragen Vor-/Nachname und E-Mail neu ein. So bleibt der Datenbestand sauber, ohne dass jemand die Felder leer ausfüllen muss.</li>
                <li><strong>Bezahlen</strong> &mdash; Weiterleitung zur Bezahlseite von Stripe. Wer nicht zahlt, dessen Buchung verfällt automatisch &mdash; der Platz wird wieder freigegeben.</li>
                <li><strong>Bestätigung</strong> &mdash; Sofort nach Zahlungseingang: Bestätigungs-E-Mail mit Kalender-Anhang und Ticket inklusive QR-Code.</li>
                <li><strong>Eintrag im Mitgliederbereich</strong> &mdash; Eingeloggte Mitglieder sehen ihr neues Ticket sofort auf der Seite &bdquo;Meine Tickets&ldquo;. Gäste/Nicht-Mitglieder erhalten einen persönlichen Link per E-Mail zur Ticket-Verwaltung.</li>
                <li><strong>E-Mail-Auto-Zuordnung</strong> &mdash; Wer ohne Login bucht, dessen E-Mail wird mit den bekannten Mitglieder-Adressen abgeglichen. Bei Treffer wird die Buchung automatisch dem Mitglieds-Kontakt zugeordnet &mdash; kein Doppel-Eintrag.</li>
                <li><strong>Falls voll</strong> &mdash; Die Person landet automatisch auf der Warteliste. Wird ein Platz frei, erhält sie eine E-Mail mit 24-Stunden-Zahlungslink.</li>
                <li><strong>Nach dem Workshop</strong> &mdash; Geschäftsstelle/Kursleitung markiert Teilnehmer:innen als anwesend. Das System erstellt automatisch das PDF der Teilnahmebescheinigung und versendet es per E-Mail. Mitglieder finden es zusätzlich im Mitgliederbereich.</li>
            </ol>
            <?php $render_section_comments('section-datenfluss'); ?>
        </div>

        <!-- ───── Abschnitt 6 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-kompatibilitaet">
            <h3>6. Zusammenspiel mit bestehenden Funktionen</h3>
            <ul>
                <li><strong>Zoho Backstage:</strong> Tickets, die direkt in Backstage angelegt wurden, werden ins eigene Zoho CRM gespiegelt. Mitglieder sehen <em>alle</em> ihre Tickets im Mitgliederbereich an einer Stelle &mdash; egal aus welchem Tool. Wie genau die Spiegelung erfolgen soll (Echtzeit, Cron-Lauf, einmaliger Import), ist eine offene Frage (siehe Abschnitt 10).</li>
                <li><strong>Edugrant-Förderung:</strong> Wenn für einen Workshop eine Förderung verfügbar ist, erscheint auf der Buchungs-Karte
                    <ul style="margin-top:6px;">
                        <li>ein <strong>klar erklärender Text</strong>, wofür die Förderung gedacht ist und wer sie nutzen kann (z.&thinsp;B. Auszubildende in der Kardiotechnik, junge Perfusionist:innen),</li>
                        <li>ein Link zum Förderantrag &mdash; <strong>jeder Antrag wird weiterhin durch die Geschäftsstelle/den Vorstand geprüft und bestätigt</strong>, bevor der Förderplatz vergeben wird,</li>
                        <li>ein <strong>limitierbares Förderplatz-Kontingent pro Workshop</strong> (z.&thinsp;B. 3 Edugrant-Plätze von insgesamt 20 Plätzen). Sind die Plätze vergeben, ist der Förderhinweis auf der Buchungs-Karte automatisch ausgeblendet.</li>
                    </ul>
                </li>
                <li><strong>Rechnungserstellung:</strong> Nach erfolgreicher Zahlung erstellt das System automatisch eine Rechnung in Zoho Books und schickt sie der Bestätigungsmail bei. Rechnungs-Vorlage, Nummernkreis und Logik bei Edugrant-Förderung (z.&thinsp;B. Anteilsabrechnung) werden mit der Geschäftsstelle abgestimmt &mdash; siehe offene Fragen, Punkt 14.</li>
                <li><strong>vimeo-webinare:</strong> Wird von Anfang an direkt eingebunden. Webinar-Anmeldungen laufen über denselben Buchungs-Kern (Tickets, Zahlung, QR, Mitgliederbereich, Teilnahmebescheinigung). Die bestehende Engine für die Teilnahmebescheinigung wird gemeinsam genutzt.</li>
                <li><strong>Kongresse und Sachkundekurse:</strong> Laufen unverändert weiter über Zoho Backstage und sind <strong>nicht Bestandteil dieses Moduls</strong>. Tickets daraus werden lediglich über die Backstage-Spiegelung (Punkt 13) im Mitgliederbereich angezeigt.</li>
            </ul>
            <?php $render_section_comments('section-kompatibilitaet'); ?>
        </div>

        <!-- ───── Abschnitt 7 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-crm-erweiterungen">
            <h3>7. Anpassungen im Zoho CRM</h3>
            <p>Damit das Modul funktioniert, sind ein paar kleine Anpassungen im internen Zoho CRM nötig. Diese müssen mit der Geschäftsstelle abgestimmt werden.</p>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Veranstaltungs-Stammdaten</strong> &mdash; neues Feld &bdquo;Storno-Frist (Tage)&ldquo;, Standardwert z.&thinsp;B. 14 Tage. So kann pro Workshop entschieden werden, wie lange Teilnehmer:innen selbst stornieren dürfen.
                <em>&rarr; Abstimmung mit Geschäftsstelle nötig.</em>
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Stornogebühr</strong> &mdash; bei Selbst-Storno innerhalb der Frist wird automatisch eine Stornogebühr einbehalten:
                <ul>
                    <li><strong>10&nbsp;% des Ticketpreises</strong>,</li>
                    <li><strong>maximal 35&nbsp;€ pro Ticket</strong>.</li>
                </ul>
                Beispiel: bei 200&nbsp;€ Ticketpreis = 20&nbsp;€ Gebühr; bei 500&nbsp;€ Ticketpreis = 35&nbsp;€ Gebühr (Deckel). Die Erstattung erfolgt automatisch über Stripe abzüglich dieser Gebühr; die Gebühr erscheint als separater Posten in Zoho Books.
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Anmelde-Status</strong> &mdash; neue Status-Werte für die Teilnehmer:innen-Liste:
                <ul>
                    <li><em>Zahlung ausstehend</em> &mdash; während die Person noch auf der Bezahlseite ist</li>
                    <li><em>Angemeldet</em> &mdash; Zahlung erfolgt, Ticket gültig, Workshop steht noch bevor</li>
                    <li><em>Warteliste</em> &mdash; Workshop ist voll</li>
                    <li><em>Nachrücker:in &ndash; Zahlung ausstehend</em> &mdash; 24-Stunden-Frist läuft</li>
                    <li><em>Storniert</em> &mdash; Geld (abzüglich Stornogebühr) erstattet</li>
                    <li><em>Angemeldet, nicht teilgenommen</em> &mdash; Workshop ist vorbei, Person war nicht da; keine Teilnahmebescheinigung, keine FoBi-Punkte</li>
                    <li><em>Anwesend</em> &mdash; bestätigt teilgenommen, Teilnahmebescheinigung erstellt</li>
                </ul>
                <em>&rarr; Abstimmung mit Verantwortlichen für den Anmelde-Workflow nötig.</em>
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Teilnahmebescheinigung bei mehrtägigen Veranstaltungen</strong> &mdash; offen: Wird pro Tag eine eigene Bescheinigung erzeugt, oder eine Sammel-Bescheinigung für alle besuchten Tage? Wie wird Anwesenheit bei Teilteilnahme (z.&thinsp;B. nur Tag&nbsp;1 von 3) abgebildet? Vorschlag zur Diskussion: <em>eine</em> Bescheinigung mit ausgewiesenen Anwesenheitstagen und entsprechend reduzierter Punktzahl. Klärung mit Geschäftsstelle und Kursleitungen &mdash; siehe offene Fragen, Punkt 15.
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Ticketnummer</strong> &mdash; neues Feld für eine numerische, eindeutige Ticketnummer (Format identisch zu Zoho Backstage, z.&thinsp;B. 8-stellig). Wird beim Anlegen automatisch vergeben.
                <em>&rarr; Format mit Backstage-Verantwortlichen abstimmen.</em>
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>Layout der Teilnahmebescheinigung</strong> &mdash; optionales Feld am Workshop, das auf ein hinterlegtes Layout verweist. Leer = Standard-Layout.
            </div>
            <div class="dgptm-wsb-evl-highlight orange">
                <strong>WordPress-seitig: Tabelle für persönliche Links</strong> &mdash; speichert die Zugangs-Links für Nicht-Mitglieder und externe Designer:innen mit Ablaufdatum. Vorbild: bestehende Lösung im Stipendien-Modul (Gutachter:innen-Token).
                <em>&rarr; rein technisch, keine Abstimmung mit Geschäftsstelle nötig.</em>
            </div>
            <?php $render_section_comments('section-crm-erweiterungen'); ?>
        </div>

        <!-- ───── Abschnitt 8 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-abhaengigkeiten">
            <h3>8. Externe Dienste</h3>
            <table class="dgptm-wsb-evl-table">
                <thead><tr><th>Dienst</th><th>Wofür</th><th>Stand</th></tr></thead>
                <tbody>
                    <tr><td><strong>Stripe</strong> (Zahlungsanbieter)</td><td>Bezahlung und automatische Erstattung. Eigenes Stripe-Unterkonto für Workshops/Webinare. Apple&nbsp;Pay, SEPA-Lastschrift und <strong>PayPal</strong> sind über Stripe direkt mit abgedeckt &mdash; keine zusätzliche PayPal-Anbindung nötig.</td><td>aktiv, Konto vorhanden</td></tr>
                    <tr><td><strong>Zoho CRM</strong></td><td>Workshop-Daten lesen, Anmeldungen schreiben</td><td>aktiv, Zugriffsrechte vorhanden</td></tr>
                    <tr><td><strong>Zoho Books</strong></td><td>Automatische Rechnungserstellung nach Zahlung; Anhang an Bestätigungsmail. Vorlage und Nummernkreis werden mit der Geschäftsstelle abgestimmt.</td><td>aktiv, anzubinden</td></tr>
                    <tr><td><strong>EIV-Fobi</strong> (Fortbildungspunkte)</td><td>Direkter Anschluss zur automatischen Punkte-Meldung nach Workshop-Abschluss. EFN wird aus dem Zoho CRM übergeben.</td><td>in V1 direkt mit angeschlossen</td></tr>
                </tbody>
            </table>
            <?php $render_section_comments('section-abhaengigkeiten'); ?>
        </div>

        <!-- ───── Abschnitt 9 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-out-of-scope">
            <h3>9. Was ist NICHT enthalten?</h3>
            <p>Damit klar ist, was diese Version <em>nicht</em> kann:</p>
            <ul>
                <li><strong>Kongresse und Sachkundekurse:</strong> Laufen unverändert weiter über Zoho Backstage &mdash; keine Buchungslogik in diesem Modul. Tickets erscheinen aber im Mitgliederbereich (Backstage-Spiegelung).</li>
                <li><strong>Fortbildungspunkte-Erfassung im Buchungsformular für Nicht-Mitglieder/Ärzt:innen:</strong> Aktuell nicht geplant. Für DGPTM-Mitglieder ist die EFN über Zoho CRM bereits hinterlegt und wird automatisch genutzt.</li>
            </ul>
            <?php $render_section_comments('section-out-of-scope'); ?>
        </div>

        <!-- ───── Abschnitt 10 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-offene-punkte">
            <h3>10. Offene Fragen für dich</h3>
            <p>Diese Punkte brauchen eine Entscheidung, bevor wir mit der Umsetzung starten. Hinterlass deine Meinung bitte als Kommentar in diesem Abschnitt.</p>
            <div class="dgptm-wsb-evl-highlight blue">
                <table class="dgptm-wsb-evl-table" style="background:transparent;">
                    <thead><tr><th>#</th><th>Frage</th><th>Vorschlag</th></tr></thead>
                    <tbody>
                        <tr><td>1</td><td><strong>Wie lange dürfen Teilnehmer:innen selbst stornieren?</strong></td><td>14 Tage einheitlich, pro Workshop änderbar</td></tr>
                        <?php $render_row_actions('offen-row-1', 3); ?>
                        <tr><td>2</td><td><strong>Was passiert, wenn nach der Frist storniert wird?</strong></td><td>Vorschlag: keine Erstattung. Härtefälle nach Kulanz &mdash; muss mit AGB abgestimmt werden.</td></tr>
                        <?php $render_row_actions('offen-row-2', 3); ?>
                        <tr><td>3</td><td><strong>Wie sollen die neuen Anmelde-Status heissen?</strong></td><td>Vorschlag: &bdquo;Zahlung ausstehend&ldquo;, &bdquo;Warteliste&ldquo;, &bdquo;Nachrücker:in &ndash; Zahlung ausstehend&ldquo;, &bdquo;Storniert&ldquo;</td></tr>
                        <?php $render_row_actions('offen-row-3', 3); ?>
                        <tr><td>4</td><td><strong>Edugrant-Förderung:</strong> Nur Hinweis und Link &mdash; oder integrierter Antrag aus der Buchung heraus?</td><td>Vorschlag: erstmal nur Hinweis und Link.</td></tr>
                        <?php $render_row_actions('offen-row-4', 3); ?>
                        <tr><td>5</td><td><strong>Backstage-Spiegelung &mdash; wie genau?</strong></td><td>Vorschlag: regelmäßiger Abgleich (z.&thinsp;B. alle 15 Min) statt Echtzeit-Synchronisation. Einfacher, robuster, ausreichend.</td></tr>
                        <?php $render_row_actions('offen-row-5', 3); ?>
                        <tr><td>6</td><td><strong>Backstage-Migration</strong></td><td>Sollen alte Backstage-Buchungen einmalig importiert werden, oder nur künftige? Vorschlag: nur künftige und alle <em>noch aktiven</em> Backstage-Tickets.</td></tr>
                        <?php $render_row_actions('offen-row-6', 3); ?>
                        <tr><td>7</td><td><strong>Format der Ticketnummer</strong></td><td>Welches genaue Format hat die Backstage-Ticketnummer? (Stellenanzahl, Bereich) &mdash; muss mit Backstage-Verantwortlichen geklärt werden.</td></tr>
                        <?php $render_row_actions('offen-row-7', 3); ?>
                        <tr><td>8</td><td><strong>Gültigkeit der persönlichen Links</strong> (Nicht-Mitglieder)</td><td>Vorschlag: bis Workshop-Ende plus 30 Tage &mdash; lange genug für Download der Teilnahmebescheinigung, kurz genug für Datensparsamkeit.</td></tr>
                        <?php $render_row_actions('offen-row-8', 3); ?>
                        <tr><td>9</td><td><strong>Wer darf Designer:innen einladen?</strong></td><td>Vorschlag: nur Geschäftsstelle. Kontrolle bleibt zentral.</td></tr>
                        <?php $render_row_actions('offen-row-9', 3); ?>
                        <tr><td>10</td><td><strong>Standard-Layout der Teilnahmebescheinigung</strong></td><td>Welches der vorhandenen Webinar-Layouts (classic, corporate, elegant, minimal) wird Standard? Oder ein neues, eigens für Workshops?</td></tr>
                        <?php $render_row_actions('offen-row-10', 3); ?>
                        <tr><td>11</td><td><strong>Wie wird Anwesenheit erfasst?</strong></td><td>Vorschlag: per QR-Code-Scan am Einlass <em>und</em> manuelle Nachpflege möglich. Bei Online-Workshops: aus Zoom/Vimeo-Anwesenheitsdaten oder manuell.</td></tr>
                        <?php $render_row_actions('offen-row-11', 3); ?>
                        <tr><td>12</td><td><strong>Online-Tool oder eigene App für Anwesenheits-Erfassung?</strong></td><td>Vorschlag: in V1 als Web-Tool (Smartphone-Browser, kein App-Store-Eintrag). Eine native App für iOS/Android nur, wenn sich der Web-Weg im Echtbetrieb als unzureichend erweist.</td></tr>
                        <?php $render_row_actions('offen-row-12', 3); ?>
                        <tr><td>13</td><td><strong>Gemeinsame Buchung mehrerer Personen mit einem Zahler:</strong> notwendig?</td><td>Heute deckt das System ab: eine Person bucht und bezahlt mehrere Tickets in einer Buchung &mdash; jede:r Teilnehmer:in bekommt eigene Daten und Teilnahmebescheinigung. Brauchen wir zusätzlich getrennte Zahler:innen pro Ticket? Vorschlag: nein, ist bisher nie aufgetreten.</td></tr>
                        <?php $render_row_actions('offen-row-13', 3); ?>
                        <tr><td>14</td><td><strong>Rechnungserstellung über Zoho Books</strong></td><td>Welche Rechnungs-Vorlage wird verwendet? Eigener Nummernkreis für Workshop-/Webinar-Rechnungen oder gemeinsamer mit Mitgliedsbeiträgen? Wie werden Edugrant-Förderungen abgebildet (volle Rechnung an Teilnehmer:in &amp; Förder-Buchung intern, oder reduzierte Rechnung)? &mdash; Klärung mit Geschäftsstelle.</td></tr>
                        <?php $render_row_actions('offen-row-14', 3); ?>
                        <tr><td>15</td><td><strong>Teilnahmebescheinigung bei mehrtägigen Veranstaltungen</strong></td><td>Eine Sammel-Bescheinigung mit ausgewiesenen Tagen oder pro Tag eine eigene? Wie werden Tageweise-Anwesenheiten und FoBi-Punkte verrechnet? Vorschlag: eine Bescheinigung mit Tagesübersicht und entsprechend gerechneten Punkten.</td></tr>
                        <?php $render_row_actions('offen-row-15', 3); ?>
                        <tr><td>16</td><td><strong>Termin-Verlegung &mdash; Storno-Recht?</strong></td><td>Wird ein Workshop verlegt: Sollen alle Teilnehmer:innen das Recht auf vollständige Erstattung (ohne Stornogebühr) bekommen, wenn sie zum neuen Termin nicht können? Vorschlag: ja, mit einer Frist von 14 Tagen ab Verlegungs-Mail.</td></tr>
                        <?php $render_row_actions('offen-row-16', 3); ?>
                    </tbody>
                </table>
            </div>
            <?php $render_section_comments('section-offene-punkte'); ?>
        </div>

        <!-- ───── Abschnitt 11 ───── -->
        <div class="dgptm-wsb-evl-section" id="section-zertifikate">
            <h3>11. Teilnahmebescheinigungen</h3>

            <p>Nach jedem Workshop sollen Teilnehmer:innen automatisch eine Teilnahmebescheinigung als PDF erhalten. Das Modul nutzt dafür die bereits vorhandene Engine aus den vimeo-webinaren &mdash; einmal gebaut, an zwei Stellen verwendet.</p>

            <h4>Wann entsteht die Teilnahmebescheinigung?</h4>
            <ol>
                <li>Geschäftsstelle oder Kursleitung markiert nach dem Workshop, wer anwesend war.</li>
                <li>Das System erzeugt für jede:n Anwesende:n automatisch ein PDF der Teilnahmebescheinigung.</li>
                <li>Das PDF wird per E-Mail verschickt.</li>
                <li>Mitglieder finden das PDF zusätzlich im Mitgliederbereich unter &bdquo;Meine Tickets&ldquo;.</li>
                <li>Nicht-Mitglieder rufen es über ihren persönlichen Link ab.</li>
            </ol>

            <h4>Was steht auf der Teilnahmebescheinigung?</h4>
            <ul>
                <li>Name der Teilnehmer:in</li>
                <li>Workshop-Titel und Datum</li>
                <li>Ort/Format (Präsenz oder online)</li>
                <li>Optionale Fortbildungspunkte und VNR-Nummer</li>
                <li>Unterschrift, Logo, Wasserzeichen &mdash; je nach Layout</li>
            </ul>

            <h4>Layout pro Workshop</h4>
            <p>Geschäftsstelle kann pro Workshop entscheiden:</p>
            <ul>
                <li><strong>Standard-Layout</strong> &mdash; vordefiniertes DGPTM-Layout, Empfehlung für die meisten Workshops.</li>
                <li><strong>Eigenes Layout</strong> &mdash; bei Sonder-Veranstaltungen (z.&thinsp;B. Jubiläum, Kooperations-Workshops) kann ein gestaltetes Layout hinterlegt werden.</li>
            </ul>

            <h4>Externe Designer:innen ohne WordPress-Zugang</h4>
            <div class="dgptm-wsb-evl-highlight blue">
                <p><strong>Idee:</strong> Wer für die DGPTM Layouts gestaltet (z.&thinsp;B. eine externe Grafikerin), bekommt einen persönlichen Link per E-Mail. Damit kann er/sie das Layout der Teilnahmebescheinigung im Browser bearbeiten &mdash; ohne sich in WordPress anzumelden.</p>
                <p><strong>Konkret:</strong></p>
                <ol>
                    <li>Geschäftsstelle trägt E-Mail-Adresse der Designerin in eine Einladungs-Maske ein und legt fest, für welchen Workshop das Layout gelten soll.</li>
                    <li>Designerin erhält eine E-Mail mit persönlichem Link.</li>
                    <li>Sie öffnet den Link, sieht den Layout-Editor, kann Hintergrundbild, Logo, Texte, Schriften ändern.</li>
                    <li>Vorschau-PDF in Echtzeit, ohne Speichern.</li>
                    <li>Speichern: das Layout ist sofort für den Workshop aktiv.</li>
                    <li>Der Link läuft nach festgelegter Frist ab (Vorschlag: 14 Tage), kann aber jederzeit von der Geschäftsstelle widerrufen werden.</li>
                </ol>
                <p><strong>Sicherheit:</strong> Der Link ist nur für dieses eine Workshop-Layout gültig. Designerin sieht keine Teilnehmer:innen-Daten, keine Buchungen, kein anderes Modul.</p>
            </div>

            <h4>Vorbild im Repo</h4>
            <p>Die Token-Logik wird vom Stipendien-Modul (Gutachter:innen-Zugang) wiederverwendet. Die PDF-Engine kommt aus den vimeo-webinaren. Beides ist im Einsatz und bewährt &mdash; kein Neubau, sondern Wiederverwendung.</p>

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
