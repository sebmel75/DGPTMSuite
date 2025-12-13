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
$current_sessions = $data['current_sessions'] ?? []; // NEU v1.1.0: Mehrere Sessions
$next = $data['next_session'] ?? null;
$sponsors = $data['sponsors'] ?? [];
$status = $data['status'] ?? 'idle';
$show_sponsors = filter_var($atts['show_sponsors'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
$has_multiple_current = count($current_sessions) > 1; // NEU v1.1.0: Rotation nötig?

// Template-Einstellungen
$template_color = get_option('dgptm_session_display_template_color', '#2563eb');
$template_logo = get_option('dgptm_session_display_template_logo', '');
$sponsor_interval = get_option('dgptm_session_display_sponsor_interval', 10000);

// NEU v1.1.0: Vollbild und Hintergrundbilder
$fullscreen_auto = filter_var($atts['fullscreen'] ?? get_option('dgptm_session_display_fullscreen_auto', true), FILTER_VALIDATE_BOOLEAN);
$bg_gallery_id = $atts['background_gallery'] ?? '';
$bg_image = $atts['background_image'] ?? '';
$bg_gallery_interval = get_option('dgptm_session_display_bg_gallery_interval', 30000);

// NEU v1.1.0: Debug-Zeit und Debug-Datum
$debug_enabled = get_option('dgptm_session_display_debug_enabled', false);
$debug_time = get_option('dgptm_session_display_debug_time', '09:00');
$debug_date_mode = get_option('dgptm_session_display_debug_date_mode', 'off');
$debug_date_custom = get_option('dgptm_session_display_debug_date_custom', date('Y-m-d'));
$debug_event_day = get_option('dgptm_session_display_debug_event_day', 1);

// Debug-Datum berechnen
$debug_date = '';
if ($debug_enabled && $debug_date_mode !== 'off') {
    if ($debug_date_mode === 'event_day') {
        // Veranstaltungstag berechnen
        $event_date = get_option('dgptm_session_display_event_date');
        if ($event_date) {
            $debug_date = date('Y-m-d', strtotime($event_date . ' +' . ($debug_event_day - 1) . ' days'));
        }
    } elseif ($debug_date_mode === 'custom') {
        $debug_date = $debug_date_custom;
    }
}

// Hintergrundbilder aus Galerie holen
$background_images = [];
if (!empty($bg_gallery_id)) {
    $gallery_images = get_post_gallery_images($bg_gallery_id);
    if ($gallery_images) {
        $background_images = $gallery_images;
    }
} elseif (!empty($bg_image)) {
    $background_images[] = $bg_image;
}
?>

<div class="dgptm-session-display"
     data-room="<?php echo esc_attr($room_id); ?>"
     data-type="<?php echo esc_attr($atts['type']); ?>"
     data-sponsor-interval="<?php echo esc_attr($sponsor_interval); ?>"
     data-fullscreen-auto="<?php echo $fullscreen_auto ? '1' : '0'; ?>"
     data-bg-interval="<?php echo esc_attr($bg_gallery_interval); ?>"
     data-debug-enabled="<?php echo $debug_enabled ? '1' : '0'; ?>"
     data-debug-time="<?php echo esc_attr($debug_time); ?>"
     data-debug-date="<?php echo esc_attr($debug_date); ?>"
     data-has-multiple-current="<?php echo $has_multiple_current ? '1' : '0'; ?>"
     data-session-rotation-interval="<?php echo get_option('dgptm_session_display_session_rotation_interval', 15000); ?>"
     style="--primary-color: <?php echo esc_attr($template_color); ?>;">

    <?php if (!empty($background_images)): ?>
        <!-- NEU v1.1.0: Hintergrundbilder-Galerie -->
        <div class="session-display-background" id="dgptm-bg-gallery">
            <?php foreach ($background_images as $index => $image_url): ?>
                <div class="bg-image <?php echo $index === 0 ? 'active' : ''; ?>"
                     style="background-image: url('<?php echo esc_url($image_url); ?>');">
                </div>
            <?php endforeach; ?>
            <div class="bg-overlay"></div>
        </div>
    <?php endif; ?>

    <div class="session-display-header">
        <?php if ($template_logo): ?>
            <div class="event-logo">
                <img src="<?php echo esc_url($template_logo); ?>" alt="Event Logo" />
            </div>
        <?php endif; ?>
        <div class="room-name">
            <?php
            // NEU v1.1.2: "Pause" zum Raumnamen hinzufügen wenn in Pause
            $room_display = esc_html($room_id);
            if ($status === 'pause') {
                $room_display .= ' - Pause';
            }
            echo $room_display;
            ?>
        </div>
        <div class="current-time" id="dgptm-current-time"></div>
    </div>

    <div class="session-display-content">

        <?php if ($current): ?>
            <!-- Aktuelle Session(s) - NEU v1.1.0: Unterstützt Rotation -->
            <div class="current-sessions-container" id="dgptm-current-sessions">
                <?php foreach ($current_sessions as $index => $session): ?>
                    <?php
                    // NEU v1.1.1: Prüfe ob es Vorträge innerhalb dieser Session gibt
                    $has_talks = !empty($session['talks']);
                    $talks = $has_talks ? $session['talks'] : [];
                    ?>
                    <div class="current-session session-active <?php echo $index === 0 ? 'active' : ''; ?>"
                         data-status="<?php echo esc_attr($session['status']); ?>"
                         data-session-index="<?php echo $index; ?>"
                         data-has-talks="<?php echo $has_talks ? '1' : '0'; ?>"
                         data-talks-count="<?php echo count($talks); ?>">
                        <div class="session-header-bar">
                            <div class="session-badge">
                                Jetzt
                                <?php if ($has_multiple_current): ?>
                                    <span class="session-counter">(<?php echo $index + 1; ?>/<?php echo count($current_sessions); ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="session-time">
                                <span class="time-start"><?php echo esc_html($session['start_time']); ?></span>
                                <span class="time-separator">-</span>
                                <span class="time-end"><?php echo esc_html($session['end_time']); ?></span>
                                <span class="session-duration">(<?php echo esc_html($session['duration']); ?>)</span>
                            </div>
                        </div>

                        <?php if ($has_talks): ?>
                            <!-- NEU v1.1.1: Session-Überschrift (weniger prominent wenn Vorträge existieren) -->
                            <h2 class="session-title-header"><?php echo wp_kses_post($session['title']); ?></h2>

                            <!-- NEU v1.1.1: Vorträge-Rotation -->
                            <div class="talks-rotation-container">
                                <?php foreach ($talks as $talk_index => $talk): ?>
                                    <div class="talk-slide <?php echo $talk_index === 0 ? 'active' : ''; ?>"
                                         data-talk-index="<?php echo $talk_index; ?>">

                                        <!-- Vortrag als Hauptinhalt -->
                                        <h1 class="talk-title-main"><?php echo wp_kses_post($talk['title']); ?></h1>

                                        <div class="talk-meta">
                                            <span class="talk-time">
                                                🕒 <?php echo esc_html($talk['start_time']); ?> - <?php echo esc_html($talk['end_time']); ?>
                                                <span class="talk-duration">(<?php echo esc_html($talk['duration']); ?>)</span>
                                            </span>
                                            <?php if (!empty($talk['track_name'])): ?>
                                                <span class="talk-track">📁 <?php echo esc_html($talk['track_name']); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($talk['speakers'])): ?>
                                            <div class="talk-speakers-main">
                                                <div class="speaker-label">Referent<?php echo count($talk['speakers']) > 1 ? 'en' : ''; ?>:</div>
                                                <?php foreach ($talk['speakers'] as $speaker): ?>
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

                                        <?php if (!empty($talk['description'])): ?>
                                            <div class="talk-description">
                                                <?php echo wp_kses_post(wpautop($talk['description'])); ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="talk-progress">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo esc_attr($talk['progress']); ?>%; background-color: var(--primary-color);"></div>
                                            </div>
                                            <div class="progress-text"><?php echo esc_html($talk['progress']); ?>% abgeschlossen</div>
                                        </div>

                                        <?php if (count($talks) > 1): ?>
                                            <div class="talk-counter">
                                                Vortrag <?php echo $talk_index + 1; ?> von <?php echo count($talks); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Keine Vorträge: Session normal anzeigen -->
                            <h1 class="session-title-main"><?php echo wp_kses_post($session['title']); ?></h1>

                        <?php if (!empty($session['speakers'])): ?>
                            <div class="session-speakers-main">
                                <div class="speaker-label">Referent<?php echo count($session['speakers']) > 1 ? 'en' : ''; ?>:</div>
                                <?php foreach ($session['speakers'] as $speaker): ?>
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

                            <?php if (!empty($session['description'])): ?>
                                <div class="session-description">
                                    <?php echo wp_kses_post(wpautop($session['description'])); ?>
                                </div>
                            <?php endif; ?>

                            <div class="session-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo esc_attr($session['progress']); ?>%; background-color: var(--primary-color);"></div>
                                </div>
                                <div class="progress-text"><?php echo esc_html($session['progress']); ?>% abgeschlossen</div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
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

                <h3 class="session-title"><?php echo wp_kses_post($next['title']); ?></h3>

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

        <?php if (!$current && $next): ?>
            <!-- NEU v1.1.2: Pause - im gleichen Stil wie normale Sessions -->
            <div class="session-content pause-mode"
                 data-has-sponsors="<?php echo ($show_sponsors && !empty($sponsors)) ? '1' : '0'; ?>"
                 data-sponsors-count="<?php echo count($sponsors); ?>">

                <!-- NEU v1.1.2: Rotation zwischen Session-Ankündigung und Sponsoren -->
                <div class="pause-rotation-container">

                    <!-- Slide 1: Session-Ankündigung im Session-Stil -->
                    <div class="pause-slide active" data-slide-index="0">

                        <!-- Pause Badge oben -->
                        <div class="pause-status-badge">Pause</div>

                        <!-- Countdown wie Progress-Bar -->
                        <?php if (isset($data['time_until_text'])): ?>
                            <div class="pause-countdown-wrapper">
                                <div class="countdown-label">Nächste Session beginnt in:</div>
                                <div class="countdown-display" id="dgptm-countdown-display">
                                    <?php echo esc_html($data['time_until_text']); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Als Nächstes Label -->
                        <div class="next-session-label">Als Nächstes:</div>

                        <!-- Session Title im gleichen Stil -->
                        <h1 class="session-title-main next-session-title"><?php echo wp_kses_post($next['title']); ?></h1>

                        <!-- Zeit im gleichen Stil wie talk-meta -->
                        <div class="session-meta">
                            <span class="session-time">
                                🕒 <?php echo esc_html($next['start_time']); ?> - <?php echo esc_html($next['end_time']); ?>
                                <span class="session-duration">(<?php echo esc_html($next['duration']); ?>)</span>
                            </span>
                        </div>

                        <!-- Speakers im gleichen Stil -->
                        <?php if (!empty($next['speakers'])): ?>
                            <div class="session-speakers-main">
                                <div class="speaker-label">Referent<?php echo count($next['speakers']) > 1 ? 'en' : ''; ?>:</div>
                                <?php foreach ($next['speakers'] as $speaker): ?>
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

                        <!-- Description im gleichen Stil -->
                        <?php if (!empty($next['description'])): ?>
                            <div class="session-description">
                                <?php echo wp_kses_post(wpautop($next['description'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($show_sponsors && !empty($sponsors)): ?>
                        <!-- Slides 2+: Sponsoren (je ein Sponsor pro Slide, Vollbild) -->
                        <?php foreach ($sponsors as $sponsor_index => $sponsor): ?>
                            <div class="pause-slide sponsor-slide" data-slide-index="<?php echo $sponsor_index + 1; ?>">
                                <div class="sponsor-fullscreen-container">
                                    <div class="sponsor-logo-fullscreen">
                                        <img src="<?php echo esc_url($sponsor['logo']); ?>"
                                             alt="<?php echo esc_attr($sponsor['name']); ?>" />
                                    </div>
                                    <div class="sponsor-name-fullscreen"><?php echo esc_html($sponsor['name']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>
        <?php elseif (!$current && !$next): ?>
            <!-- Keine Sessions / Ende - Sponsoren anzeigen -->
            <div class="no-session pause-screen">
                <?php if ($show_sponsors && !empty($sponsors)): ?>
                    <div class="sponsors-rotation-container">
                        <div class="pause-message">
                            <h2>Pause</h2>
                            <p>Die nächste Session beginnt in Kürze</p>
                        </div>
                        <div class="sponsors-display-wrapper">
                            <div class="sponsors-label">Unsere Partner:</div>
                            <div class="sponsors-display" id="dgptm-sponsors-slider-2">
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

    <?php if ($fullscreen_auto): ?>
        <!-- NEU v1.1.0: Vollbild-Hinweis -->
        <div class="fullscreen-hint" id="dgptm-fullscreen-hint">
            Drücken Sie F11 für Vollbild
        </div>
    <?php endif; ?>

</div>
