<?php
/**
 * Template: Events List with EduGrant Budget
 * Shortcode: [edugrant_events]
 */

if (!defined('ABSPATH')) {
    exit;
}

$today = date('Y-m-d');
?>

<div class="edugrant-events-container">
    <h3 class="edugrant-section-title">Veranstaltungen mit EduGrant-Förderung</h3>

    <p class="edugrant-intro">
        Die folgenden Veranstaltungen bieten eine EduGrant-Förderung für Mitglieder an.
        Bitte beachten Sie die Anmeldefrist (bis 3 Tage vor Veranstaltungsbeginn).
    </p>

    <div class="edugrant-events-grid">
        <?php foreach ($events as $event):
            // API Field Names from Modules.json:
            // Name = Veranstaltungsbezeichnung, From_Date = Von, To_Date = Bis
            // Maximum_Promotion = Maximale Förderung, External_Event = Externe Veranstaltung
            // Location = Ort (lookup), Maximum_Attendees = Max Anzahl TN
            $event_name = $event['Name'] ?? 'Unbenannte Veranstaltung';
            $event_id = $event['id'] ?? '';
            $location = $event['Location']['name'] ?? $event['City'] ?? 'Ort nicht angegeben';
            $start_date = !empty($event['From_Date']) ? date_i18n('d.m.Y', strtotime($event['From_Date'])) : '';
            $end_date = !empty($event['To_Date']) ? date_i18n('d.m.Y', strtotime($event['To_Date'])) : '';
            $max_funding = $event['Maximum_Promotion'] ?? '';
            $spots_available = $event['spots_available'] ?? 0;
            $can_apply = $event['can_apply'] ?? false;
            $has_spots = $event['has_spots'] ?? false;
            $deadline = !empty($event['application_deadline']) ? date_i18n('d.m.Y', strtotime($event['application_deadline'])) : '';
            $is_external = $event['External_Event'] ?? false;
            $is_external = ($is_external === true || $is_external === 'true');
        ?>
            <div class="edugrant-event-card <?php echo (!$can_apply || !$has_spots) ? 'disabled' : ''; ?>">
                <div class="event-header">
                    <h4 class="event-title"><?php echo esc_html($event_name); ?></h4>
                    <div class="event-badges">
                        <span class="event-type-badge <?php echo $is_external ? 'external' : 'internal'; ?>">
                            <?php echo $is_external ? 'Extern' : 'Intern'; ?>
                        </span>
                    </div>
                </div>

                <div class="event-details">
                    <div class="event-detail">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span>
                            <?php if ($start_date && $end_date && $start_date !== $end_date): ?>
                                <?php echo esc_html($start_date); ?> - <?php echo esc_html($end_date); ?>
                            <?php elseif ($start_date): ?>
                                <?php echo esc_html($start_date); ?>
                            <?php else: ?>
                                Datum nicht angegeben
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="event-detail">
                        <span class="dashicons dashicons-location"></span>
                        <span><?php echo esc_html($location); ?></span>
                    </div>

                    <?php if ($max_funding): ?>
                        <div class="event-detail highlight">
                            <span class="dashicons dashicons-awards"></span>
                            <span>Max. Förderung: <strong><?php echo esc_html(number_format((float)$max_funding, 0, ',', '.')); ?> &euro;</strong></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($deadline && $can_apply): ?>
                        <div class="event-detail">
                            <span class="dashicons dashicons-clock"></span>
                            <span>Antragsfrist: <?php echo esc_html($deadline); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($spots_available < 999): ?>
                        <div class="event-detail <?php echo $spots_available <= 3 ? 'warning' : ''; ?>">
                            <span class="dashicons dashicons-groups"></span>
                            <span>
                                <?php if ($has_spots): ?>
                                    Noch <?php echo esc_html($spots_available); ?> Plätze verfügbar
                                <?php else: ?>
                                    <strong>Ausgebucht</strong>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$is_external): ?>
                        <div class="event-detail ticket-required">
                            <span class="dashicons dashicons-tickets-alt"></span>
                            <span>Ticket erforderlich</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="event-actions">
                    <?php if ($can_apply && $has_spots): ?>
                        <?php if (is_user_logged_in()): ?>
                            <a href="<?php echo esc_url(add_query_arg('event_id', $event_id, get_option('dgptm_edugrant_form_page', '/veranstaltungen/educational-grant-der-dgptm/educational-grant-abrechnung/'))); ?>"
                               class="button edugrant-apply-btn">
                                <span class="dashicons dashicons-yes"></span> EduGrant beantragen
                            </a>
                        <?php else: ?>
                            <a href="<?php echo wp_login_url(add_query_arg('event_id', $event_id, get_option('dgptm_edugrant_form_page', '/veranstaltungen/educational-grant-der-dgptm/educational-grant-abrechnung/'))); ?>"
                               class="button edugrant-login-btn">
                                Anmelden um zu beantragen
                            </a>
                        <?php endif; ?>
                    <?php elseif (!$has_spots): ?>
                        <span class="edugrant-unavailable">
                            <span class="dashicons dashicons-no"></span> Ausgebucht
                        </span>
                    <?php else: ?>
                        <span class="edugrant-unavailable">
                            <span class="dashicons dashicons-clock"></span> Antragsfrist abgelaufen
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($events)): ?>
        <div class="edugrant-no-events">
            <span class="dashicons dashicons-info"></span>
            <p>Aktuell sind keine Veranstaltungen mit EduGrant-Förderung verfügbar.</p>
        </div>
    <?php endif; ?>
</div>
