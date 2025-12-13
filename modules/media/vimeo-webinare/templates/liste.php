<?php
/**
 * Template: Webinar Liste
 * Version: 1.3.0
 * Variables: $webinars, $user_id, $is_logged_in
 */

if (!defined('ABSPATH')) exit;

$instance = DGPTM_Vimeo_Webinare::get_instance();

// Hole Cookie-Fortschritt für nicht eingeloggte Benutzer
$cookie_progress = [];
if (!$is_logged_in) {
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'vw_webinar_') === 0) {
            $webinar_id = intval(str_replace('vw_webinar_', '', $name));
            if ($webinar_id) {
                $data = json_decode(stripslashes($value), true);
                if (is_array($data)) {
                    $cookie_progress[$webinar_id] = [
                        'watched_time' => floatval($data['watched_time'] ?? 0),
                        'progress' => floatval($data['progress'] ?? 0),
                    ];
                }
            }
        }
    }
}

// Hole Verlauf für eingeloggte Benutzer
$history = [];
if ($is_logged_in && $user_id) {
    $history = $instance->get_user_webinar_history($user_id, 5);
}
?>

<div class="vw-liste-container">

    <?php if (!$is_logged_in): ?>
        <div class="vw-login-banner">
            <span class="dashicons dashicons-info-outline"></span>
            <div class="vw-banner-content">
                <strong>Hinweis:</strong> Sie sind nicht angemeldet. Fortschritte werden nur lokal gespeichert und nicht in Ihrer Fortbildungsliste eingetragen.
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="vw-btn vw-btn-small">Jetzt anmelden</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($is_logged_in && !empty($history)): ?>
        <div class="vw-history-section">
            <h3>📜 Zuletzt angesehen</h3>
            <div class="vw-history-list">
                <?php foreach ($history as $item): ?>
                    <div class="vw-history-item <?php echo $item['completed'] ? 'completed' : ''; ?>">
                        <div class="vw-history-info">
                            <a href="<?php echo home_url('/wissen/webinar/' . $item['webinar_id']); ?>" class="vw-history-title">
                                <?php echo esc_html($item['title']); ?>
                            </a>
                            <span class="vw-history-date">
                                <?php 
                                $date = new DateTime($item['last_access']);
                                $now = new DateTime();
                                $diff = $now->diff($date);
                                
                                if ($diff->days == 0) {
                                    echo 'Heute';
                                } elseif ($diff->days == 1) {
                                    echo 'Gestern';
                                } elseif ($diff->days < 7) {
                                    echo 'vor ' . $diff->days . ' Tagen';
                                } else {
                                    echo $date->format('d.m.Y');
                                }
                                ?>
                            </span>
                        </div>
                        <div class="vw-history-progress">
                            <?php if ($item['completed']): ?>
                                <span class="vw-status-badge completed">✓ Abgeschlossen</span>
                            <?php else: ?>
                                <div class="vw-mini-progress">
                                    <div class="vw-mini-progress-fill" style="width: <?php echo esc_attr($item['progress']); ?>%"></div>
                                </div>
                                <span class="vw-progress-percent"><?php echo number_format($item['progress'], 0); ?>%</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <h2>📚 Verfügbare Webinare</h2>

    <?php if (empty($webinars)): ?>
        <p class="vw-no-webinars">Derzeit sind keine Webinare verfügbar.</p>
    <?php else: ?>

        <div class="vw-filter-section">
            <input type="text" class="vw-search-input" placeholder="Webinare durchsuchen..." />
            <select class="vw-status-filter">
                <option value="all">Alle anzeigen</option>
                <option value="not-started">Noch nicht begonnen</option>
                <option value="in-progress">In Bearbeitung</option>
                <option value="completed">Abgeschlossen</option>
            </select>
        </div>

        <div class="vw-webinar-grid">
            <?php foreach ($webinars as $webinar):
                $webinar_id = $webinar->ID;
                $vimeo_id = get_field('vimeo_id', $webinar_id);
                $points = get_field('ebcp_points', $webinar_id) ?: 1;
                $completion_req = get_field('completion_percentage', $webinar_id) ?: 90;

                // Fortschritt ermitteln
                $progress = 0;
                $is_completed = false;
                $is_local_progress = false;

                if ($is_logged_in && $user_id) {
                    // Eingeloggter Benutzer - aus DB
                    $progress = floatval(get_user_meta($user_id, '_vw_progress_' . $webinar_id, true));
                    $is_completed = (bool) get_user_meta($user_id, '_vw_completed_' . $webinar_id, true);
                } elseif (isset($cookie_progress[$webinar_id])) {
                    // Nicht eingeloggt - aus Cookie
                    $progress = $cookie_progress[$webinar_id]['progress'];
                    $is_local_progress = true;
                }

                // Status bestimmen
                $status = 'not-started';
                $status_label = 'Nicht begonnen';
                $status_class = 'status-new';

                if ($is_completed) {
                    $status = 'completed';
                    $status_label = 'Abgeschlossen';
                    $status_class = 'status-completed';
                } elseif ($progress > 0) {
                    $status = 'in-progress';
                    $status_label = $is_local_progress ? 'In Bearbeitung (lokal)' : 'In Bearbeitung';
                    $status_class = 'status-progress';
                }

                // Thumbnail from Vimeo
                $thumbnail = '';
                if ($vimeo_id) {
                    $thumbnail = "https://vumbnail.com/{$vimeo_id}.jpg";
                }
            ?>

            <div class="vw-webinar-card" data-status="<?php echo esc_attr($status); ?>" data-title="<?php echo esc_attr(strtolower($webinar->post_title)); ?>">

                <div class="vw-card-thumbnail" style="background-image: url('<?php echo esc_url($thumbnail); ?>');">
                    <div class="vw-card-status <?php echo esc_attr($status_class); ?>">
                        <?php echo esc_html($status_label); ?>
                        <?php if ($is_local_progress): ?>
                            <span class="vw-local-indicator" title="Nur lokal gespeichert">📱</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_completed): ?>
                        <div class="vw-card-badge">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="vw-card-content">
                    <h3><?php echo esc_html($webinar->post_title); ?></h3>

                    <div class="vw-card-meta">
                        <span class="vw-meta-item">⭐ <?php echo esc_html($points); ?> EBCP</span>
                        <span class="vw-meta-item">⏱ <?php echo esc_html($completion_req); ?>% erforderlich</span>
                    </div>

                    <?php if ($progress > 0): ?>
                        <div class="vw-card-progress">
                            <div class="vw-card-progress-bar">
                                <div class="vw-card-progress-fill <?php echo $is_local_progress ? 'local' : ''; ?>" style="width: <?php echo esc_attr($progress); ?>%"></div>
                            </div>
                            <span class="vw-card-progress-text"><?php echo esc_html(number_format($progress, 0)); ?>%</span>
                        </div>
                    <?php endif; ?>

                    <div class="vw-card-excerpt">
                        <?php echo wp_trim_words($webinar->post_content, 20); ?>
                    </div>

                    <div class="vw-card-actions">
                        <a href="<?php echo home_url('/wissen/webinar/' . $webinar_id); ?>" class="vw-btn vw-btn-primary">
                            <?php 
                            if ($is_completed) {
                                echo 'Erneut ansehen';
                            } elseif ($progress > 0) {
                                echo 'Fortsetzen';
                            } else {
                                echo 'Jetzt ansehen';
                            }
                            ?>
                        </a>

                        <?php if ($is_completed && $is_logged_in): ?>
                            <button class="vw-btn vw-btn-secondary vw-generate-certificate" data-webinar-id="<?php echo esc_attr($webinar_id); ?>">
                                📄 Zertifikat
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>
