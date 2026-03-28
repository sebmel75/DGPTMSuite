<?php
/**
 * Template: Vimeo Webinar Player
 * Variables: $post_id, $vimeo_id, $completion_percentage, $progress, $watched_time, $is_completed, $user_id
 */

if (!defined('ABSPATH')) exit;

$title = get_the_title($post_id);
$points = get_field('ebcp_points', $post_id) ?: 1;
$vnr = get_field('vnr', $post_id) ?: '';
$instance = DGPTM_Vimeo_Webinare::get_instance();
$duration = $instance->get_video_duration($post_id); // wird vom JS gesetzt
?>

<div class="vw-player-container"
     data-webinar-id="<?php echo esc_attr($post_id); ?>"
     data-completion="<?php echo esc_attr($completion_percentage); ?>"
     data-watched-time="<?php echo esc_attr($watched_time); ?>"
     data-user-logged-in="<?php echo $user_id ? 'true' : 'false'; ?>">

    <div class="vw-player-header">
        <h2><?php echo esc_html($title); ?></h2>
        <div class="vw-player-meta">
            <span class="vw-points">⭐ <?php echo esc_html($points); ?> EBCP Punkte</span>
            <?php if ($vnr): ?>
                <span class="vw-vnr">VNR: <?php echo esc_html($vnr); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$user_id): ?>
        <div class="vw-login-notice">
            <span class="dashicons dashicons-info"></span>
            <strong>Hinweis:</strong> Zum Eintrag in den Fortbildungsnachweis bitte <a href="<?php echo wp_login_url(home_url('/webinar/' . $post_id)); ?>">einloggen</a>.
        </div>
    <?php endif; ?>

    <?php if ($is_completed): ?>
        <div class="vw-completed-banner">
            <span class="dashicons dashicons-yes-alt"></span>
            <strong>Webinar abgeschlossen!</strong>
            <p>Sie haben dieses Webinar bereits erfolgreich abgeschlossen.</p>
            <button class="vw-btn vw-btn-primary vw-generate-certificate" data-webinar-id="<?php echo esc_attr($post_id); ?>">
                📄 Zertifikat herunterladen
            </button>
        </div>
    <?php endif; ?>

    <div class="vw-player-wrapper">
        <div class="vw-vimeo-player" id="vimeo-player-<?php echo esc_attr($post_id); ?>" data-vimeo-id="<?php echo esc_attr($vimeo_id); ?>">
            <iframe
                src="https://player.vimeo.com/video/<?php echo esc_attr($vimeo_id); ?>?title=0&byline=0&portrait=0&badge=0&autopause=0&player_id=0&app_id=58479&controls=1"
                frameborder="0"
                allow="autoplay; fullscreen; picture-in-picture; clipboard-write"
                style="width:100%;height:100%;"
                title="<?php echo esc_attr($title); ?>">
            </iframe>
        </div>
    </div>

    <?php if ($user_id): ?>
        <div class="vw-progress-section">
            <div class="vw-progress-bar">
                <div class="vw-progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
            </div>
            <div class="vw-progress-text">
                Angesehene Zeit: <strong class="vw-watched-time-display"><?php echo gmdate('i:s', $watched_time); ?></strong> Min
                <span class="vw-separator">•</span>
                Fortschritt: <strong class="vw-progress-value"><?php echo esc_html(number_format($progress, 1)); ?>%</strong>
                <span class="vw-progress-required">(<?php echo esc_html($completion_percentage); ?>% erforderlich)</span>
            </div>
        </div>

        <?php if (!$is_completed): ?>
            <div class="vw-info-box">
                <p><strong>💡 Hinweis:</strong> Sehen Sie mindestens <?php echo esc_html($completion_percentage); ?>% des Videos tatsächlich an (nicht vorspulen!), um <?php echo esc_html($points); ?> Fortbildungspunkte zu erhalten. Die tatsächliche Wiedergabezeit wird gemessen.</p>
            </div>

            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <div class="vw-debug-box" style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px;">
                    <h4 style="margin-top: 0; color: #856404;">🔧 Debug-Modus</h4>
                    <p style="margin-bottom: 10px;">Test-Buttons (nur im Debug-Modus sichtbar):</p>
                    <button class="vw-btn vw-btn-secondary" onclick="if (typeof window.vwCompleteWebinar === 'function') { window.vwCompleteWebinar(<?php echo esc_js($post_id); ?>); } else { console.error('vwCompleteWebinar function not found!'); alert('Fehler: vwCompleteWebinar Funktion nicht verfügbar. Öffnen Sie die Browser Console (F12) für Details.'); }" style="margin-right: 10px;">
                        🧪 Completion manuell testen
                    </button>
                    <button class="vw-btn vw-btn-secondary" onclick="console.log('vwData:', typeof vwData !== 'undefined' ? vwData : 'NOT DEFINED'); console.log('jQuery:', typeof jQuery !== 'undefined' ? 'OK' : 'NOT LOADED'); console.log('Vimeo SDK:', typeof Vimeo !== 'undefined' ? 'OK' : 'NOT LOADED');">
                        📊 JavaScript-Status prüfen
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <div class="vw-content">
        <?php echo wpautop(get_post_field('post_content', $post_id)); ?>
    </div>

</div>

<div class="vw-loading-overlay" style="display: none;">
    <div class="vw-spinner"></div>
    <p>Wird verarbeitet...</p>
</div>
