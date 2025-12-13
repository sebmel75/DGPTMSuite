<?php
/**
 * Template: Raumübersicht
 *
 * Zeigt eine Übersicht aller Räume mit ihren aktuellen Sessions
 */

if (!defined('ABSPATH')) {
    exit;
}

$layout = $atts['layout'] ?? 'grid';
$show_time = filter_var($atts['show_time'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
?>

<div class="dgptm-session-overview layout-<?php echo esc_attr($layout); ?>">

    <div class="overview-header">
        <h2>Sessionübersicht</h2>
        <?php if ($show_time): ?>
            <div class="current-time" id="dgptm-overview-time"></div>
        <?php endif; ?>
    </div>

    <div class="overview-content">

        <?php if (empty($overview_data)): ?>
            <div class="no-data">
                <p>Keine Räume/Sessions gefunden</p>
            </div>
        <?php else: ?>

            <?php foreach ($overview_data as $room_id => $room_info): ?>
                <div class="room-card status-<?php echo esc_attr($room_info['status']); ?>" data-room="<?php echo esc_attr($room_id); ?>">

                    <div class="room-header">
                        <h3 class="room-name"><?php echo esc_html($room_info['room']); ?></h3>
                        <span class="room-status-badge"><?php echo esc_html($room_info['status']); ?></span>
                    </div>

                    <div class="room-content">

                        <?php if ($room_info['current_session']): ?>
                            <?php $session = $room_info['current_session']; ?>
                            <div class="current-session">
                                <div class="session-indicator">Jetzt</div>
                                <div class="session-time">
                                    <?php echo esc_html($session['start_time']); ?> - <?php echo esc_html($session['end_time']); ?>
                                </div>
                                <div class="session-title"><?php echo wp_kses_post($session['title']); ?></div>
                                <?php if (!empty($session['speakers'])): ?>
                                    <div class="session-speakers">
                                        <?php
                                        $speaker_names = array_map(function($s) { return $s['name']; }, $session['speakers']);
                                        echo esc_html(implode(', ', $speaker_names));
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php elseif ($room_info['next_session']): ?>
                            <?php $session = $room_info['next_session']; ?>
                            <div class="next-session">
                                <div class="session-indicator">Demnächst</div>
                                <div class="session-time">
                                    <?php echo esc_html($session['start_time']); ?> - <?php echo esc_html($session['end_time']); ?>
                                </div>
                                <div class="session-title"><?php echo wp_kses_post($session['title']); ?></div>
                            </div>

                        <?php else: ?>
                            <div class="idle-session">
                                <p>Keine geplanten Sessions</p>
                            </div>
                        <?php endif; ?>

                    </div>

                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <div class="overview-footer">
        <div class="last-update">Aktualisiert: <span id="dgptm-overview-update"></span></div>
    </div>

</div>
