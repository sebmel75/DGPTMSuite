<?php
/**
 * Template: Einzelraum-Display
 *
 * Zeigt die aktuelle/nächste Session für einen Raum an
 */

if (!defined('ABSPATH')) {
    exit;
}

$room_id = $data['room'] ?? '';
$current = $data['current_session'] ?? null;
$next = $data['next_session'] ?? null;
$sponsors = $data['sponsors'] ?? [];
$status = $data['status'] ?? 'idle';
$show_sponsors = filter_var($atts['show_sponsors'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

// Template-Einstellungen
$template_color = get_option('dgptm_session_display_template_color', '#2563eb');
$template_logo = get_option('dgptm_session_display_template_logo', '');
$sponsor_interval = get_option('dgptm_session_display_sponsor_interval', 10000);
?>

<div class="dgptm-session-display"
     data-room="<?php echo esc_attr($room_id); ?>"
     data-type="<?php echo esc_attr($atts['type']); ?>"
     data-sponsor-interval="<?php echo esc_attr($sponsor_interval); ?>"
     style="--primary-color: <?php echo esc_attr($template_color); ?>;">

    <div class="session-display-header">
        <?php if ($template_logo): ?>
            <div class="event-logo">
                <img src="<?php echo esc_url($template_logo); ?>" alt="Event Logo" />
            </div>
        <?php endif; ?>
        <div class="room-name"><?php echo esc_html($room_id); ?></div>
        <div class="current-time" id="dgptm-current-time"></div>
    </div>

    <div class="session-display-content">

        <?php if ($current): ?>
            <!-- Aktuelle Session -->
            <div class="current-session session-active" data-status="<?php echo esc_attr($current['status']); ?>">
                <div class="session-header-bar">
                    <div class="session-badge">Jetzt</div>
                    <div class="session-time">
                        <span class="time-start"><?php echo esc_html($current['start_time']); ?></span>
                        <span class="time-separator">-</span>
                        <span class="time-end"><?php echo esc_html($current['end_time']); ?></span>
                        <span class="session-duration">(<?php echo esc_html($current['duration']); ?>)</span>
                    </div>
                </div>

                <!-- Session als prominente Überschrift -->
                <h1 class="session-title-main"><?php echo esc_html($current['title']); ?></h1>

                <?php if (!empty($current['speakers'])): ?>
                    <div class="session-speakers-main">
                        <div class="speaker-label">Referent<?php echo count($current['speakers']) > 1 ? 'en' : ''; ?>:</div>
                        <?php foreach ($current['speakers'] as $speaker): ?>
                            <div class="speaker-block">
                                <div class="speaker-name-large"><?php echo esc_html($speaker['name']); ?></div>
                                <?php if (!empty($speaker['title']) || !empty($speaker['company'])): ?>
                                    <div class="speaker-details">
                                        <?php if (!empty($speaker['title'])): ?>
                                            <span class="speaker-title"><?php echo esc_html($speaker['title']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($speaker['company'])): ?>
                                            <span class="speaker-company"><?php echo esc_html($speaker['company']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($current['description'])): ?>
                    <div class="session-description">
                        <?php echo nl2br(esc_html($current['description'])); ?>
                    </div>
                <?php endif; ?>

                <div class="session-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo esc_attr($current['progress']); ?>%; background-color: var(--primary-color);"></div>
                    </div>
                    <div class="progress-text"><?php echo esc_html($current['progress']); ?>% abgeschlossen</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($next && ($atts['type'] === 'next' || $atts['type'] === 'both')): ?>
            <!-- Nächste Session -->
            <div class="next-session">
                <div class="session-badge">Als Nächstes</div>

                <?php if (isset($data['time_until_text'])): ?>
                    <div class="time-until">
                        <?php echo esc_html($data['time_until_text']); ?>
                    </div>
                <?php endif; ?>

                <div class="session-time">
                    <span class="time-start"><?php echo esc_html($next['start_time']); ?></span>
                    <span class="time-separator">-</span>
                    <span class="time-end"><?php echo esc_html($next['end_time']); ?></span>
                </div>

                <h3 class="session-title"><?php echo esc_html($next['title']); ?></h3>

                <?php if (!empty($next['speakers'])): ?>
                    <div class="session-speakers">
                        <?php foreach ($next['speakers'] as $speaker): ?>
                            <div class="speaker">
                                <span class="speaker-name"><?php echo esc_html($speaker['name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$current && !$next): ?>
            <!-- Keine Sessions / Pause - Sponsoren anzeigen -->
            <div class="no-session pause-screen">
                <?php if ($show_sponsors && !empty($sponsors)): ?>
                    <div class="sponsors-rotation-container">
                        <div class="pause-message">
                            <h2>Pause</h2>
                            <p>Die nächste Session beginnt in Kürze</p>
                        </div>
                        <div class="sponsors-display-wrapper">
                            <div class="sponsors-label">Unsere Partner:</div>
                            <div class="sponsors-display" id="dgptm-sponsors-slider">
                                <?php foreach ($sponsors as $index => $sponsor): ?>
                                    <div class="sponsor-slide <?php echo $index === 0 ? 'active' : ''; ?>"
                                         data-index="<?php echo esc_attr($index); ?>"
                                         data-level="<?php echo esc_attr($sponsor['level'] ?? 'default'); ?>">
                                        <div class="sponsor-logo-container">
                                            <img src="<?php echo esc_url($sponsor['logo']); ?>"
                                                 alt="<?php echo esc_attr($sponsor['name']); ?>"
                                                 class="sponsor-logo" />
                                        </div>
                                        <div class="sponsor-name"><?php echo esc_html($sponsor['name']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="sponsors-navigation">
                                <?php foreach ($sponsors as $index => $sponsor): ?>
                                    <span class="sponsor-dot <?php echo $index === 0 ? 'active' : ''; ?>"
                                          data-index="<?php echo esc_attr($index); ?>"></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="pause-message">
                        <h2>Pause</h2>
                        <p>Derzeit keine Sessions geplant</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="session-display-footer">
        <?php if ($template_logo): ?>
            <div class="footer-logo">
                <img src="<?php echo esc_url($template_logo); ?>" alt="Event Logo" />
            </div>
        <?php else: ?>
            <div class="event-title">DGPTM Jahrestagung</div>
        <?php endif; ?>
        <div class="last-update">
            Aktualisiert: <span id="dgptm-last-update"><?php echo esc_html(date_i18n('H:i:s', strtotime($data['timestamp']))); ?></span>
        </div>
    </div>

</div>
