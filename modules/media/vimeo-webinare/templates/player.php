<?php
/**
 * Template: Vimeo Webinar Player
 * Version: 1.3.0
 * Variables: $post_id, $vimeo_id, $completion_percentage, $progress, $watched_time, $is_completed, $user_id
 */

if (!defined('ABSPATH')) exit;

$title = get_the_title($post_id);
$points = get_field('ebcp_points', $post_id) ?: 1;
$vnr = get_field('vnr', $post_id) ?: '';
$instance = DGPTM_Vimeo_Webinare::get_instance();
$duration = $instance->get_video_duration($post_id);

// Cookie-Daten für Übernahme nach Login
$has_cookie_progress = false;
if (!$user_id) {
    $cookie_name = 'vw_webinar_' . $post_id;
    if (isset($_COOKIE[$cookie_name])) {
        $cookie_data = json_decode(stripslashes($_COOKIE[$cookie_name]), true);
        if (is_array($cookie_data) && floatval($cookie_data['watched_time'] ?? 0) > 0) {
            $has_cookie_progress = true;
        }
    }
}
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
        <div class="vw-login-notice vw-notice-warning">
            <span class="dashicons dashicons-warning"></span>
            <div class="vw-notice-content">
                <strong>Wichtiger Hinweis:</strong>
                <p>Sie sind nicht angemeldet. Ihr Fortschritt wird nur lokal auf diesem Gerät gespeichert und die Teilnahme wird <strong>nicht automatisch</strong> in Ihrer Fortbildungsliste eingetragen.</p>
                <p>Um <?php echo esc_html($points); ?> EBCP-Punkte zu erhalten, bitte 
                    <a href="<?php echo wp_login_url(home_url('/wissen/webinar/' . $post_id)); ?>" class="vw-login-link">
                        <strong>jetzt anmelden</strong>
                    </a>
                    <?php if ($has_cookie_progress): ?>
                        - Ihr bisheriger Fortschritt wird übernommen!
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($is_completed): ?>
        <div class="vw-completed-banner">
            <span class="dashicons dashicons-yes-alt"></span>
            <strong>Webinar abgeschlossen!</strong>
            <p>Sie haben dieses Webinar bereits erfolgreich abgeschlossen und <?php echo esc_html($points); ?> EBCP-Punkte erhalten.</p>
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

    <div class="vw-progress-section">
        <div class="vw-progress-bar">
            <div class="vw-progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
            <div class="vw-progress-threshold" style="left: <?php echo esc_attr($completion_percentage); ?>%"></div>
        </div>
        <div class="vw-progress-text">
            Angesehene Zeit: <strong class="vw-watched-time-display"><?php echo gmdate('i:s', $watched_time); ?></strong> Min
            <span class="vw-separator">•</span>
            Fortschritt: <strong class="vw-progress-value"><?php echo esc_html(number_format($progress, 1)); ?>%</strong>
            <span class="vw-progress-required">(<?php echo esc_html($completion_percentage); ?>% erforderlich)</span>
            <?php if (!$user_id): ?>
                <span class="vw-local-badge" title="Nur lokal gespeichert">📱 Lokal</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$is_completed): ?>
        <div class="vw-info-box">
            <p>
                <strong>💡 Hinweis:</strong> 
                Sehen Sie mindestens <?php echo esc_html($completion_percentage); ?>% des Videos tatsächlich an (nicht vorspulen!), um <?php echo esc_html($points); ?> Fortbildungspunkte zu erhalten. 
                Die tatsächliche Wiedergabezeit wird gemessen.
                <?php if (!$user_id): ?>
                    <strong>Sie müssen angemeldet sein, um Punkte zu erhalten.</strong>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="vw-content">
        <?php echo wpautop(get_post_field('post_content', $post_id)); ?>
    </div>

</div>

<div class="vw-loading-overlay" style="display: none;">
    <div class="vw-spinner"></div>
    <p>Wird verarbeitet...</p>
</div>

<!-- Erfolgs-Modal -->
<div class="vw-modal vw-success-modal" id="vw-completion-modal" style="display: none;">
    <div class="vw-modal-content">
        <div class="vw-modal-header">
            <h3>🎉 Herzlichen Glückwunsch!</h3>
        </div>
        <div class="vw-modal-body">
            <p>Sie haben das Webinar "<strong><?php echo esc_html($title); ?></strong>" erfolgreich abgeschlossen!</p>
            <p class="vw-points-earned">
                <span class="vw-points-number"><?php echo esc_html($points); ?></span>
                <span class="vw-points-label">EBCP-Punkte erhalten</span>
            </p>
            <p>Ihr Zertifikat wurde erstellt und an Ihre E-Mail-Adresse gesendet.</p>
        </div>
        <div class="vw-modal-footer">
            <button class="vw-btn vw-btn-primary vw-download-cert">📄 Zertifikat herunterladen</button>
            <button class="vw-btn vw-btn-secondary vw-close-modal">Schließen</button>
        </div>
    </div>
</div>
