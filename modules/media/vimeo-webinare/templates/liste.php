<?php
/**
 * Template: Webinar-Liste (öffentlicher Frontend-Katalog).
 * Design an Mitglieder-Dashboard angeglichen.
 *
 * Variablen (vom Shortcode-Liste bereitgestellt):
 *   $webinars_raw : WP_Post[]
 *   $user_id      : int
 *   $is_logged_in : bool
 *   $cookie_progress : array — [webinar_id => ['progress'=>float, 'watched_time'=>float]]
 *   $history      : array — eingeloggt: letzte 5 Einträge
 */
if (!defined('ABSPATH')) exit;
?>
<div class="dgptm-vw dgptm-vw-liste">

    <?php if (!$is_logged_in): ?>
        <div class="dgptm-card dgptm-vw-login-banner">
            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
            <div class="dgptm-vw-login-text">
                <strong>Hinweis:</strong> Sie sind nicht angemeldet. Fortschritte werden nur lokal gespeichert und nicht in Ihrer Fortbildungsliste eingetragen.
            </div>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="dgptm-btn--primary">Jetzt anmelden</a>
        </div>
    <?php endif; ?>

    <?php if ($is_logged_in && !empty($history)): ?>
        <div class="dgptm-card dgptm-vw-history">
            <h3>
                <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                Zuletzt angesehen
            </h3>
            <ul class="dgptm-vw-history-list">
                <?php foreach ($history as $item):
                    $date_str = '';
                    try {
                        $date = new DateTime($item['last_access']);
                        $now = new DateTime();
                        $diff = $now->diff($date);
                        if ($diff->days == 0)      $date_str = 'Heute';
                        elseif ($diff->days == 1)  $date_str = 'Gestern';
                        elseif ($diff->days < 7)   $date_str = 'vor ' . $diff->days . ' Tagen';
                        else                       $date_str = $date->format('d.m.Y');
                    } catch (\Exception $e) { /* ignore */ }
                    $url = home_url('/wissen/webinar/' . $item['webinar_id']);
                ?>
                <li class="dgptm-vw-history-item">
                    <a href="<?php echo esc_url($url); ?>" class="dgptm-vw-history-title"><?php echo esc_html($item['title']); ?></a>
                    <span class="dgptm-vw-history-date"><?php echo esc_html($date_str); ?></span>
                    <?php if (!empty($item['completed'])): ?>
                        <span class="dgptm-badge dgptm-badge--success">
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            Abgeschlossen
                        </span>
                    <?php else: ?>
                        <div class="dgptm-progress" aria-label="Fortschritt">
                            <div class="dgptm-progress-fill" style="width: <?php echo esc_attr($item['progress']); ?>%"></div>
                        </div>
                        <span class="dgptm-vw-history-percent"><?php echo esc_html(number_format($item['progress'], 0)); ?>%</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2 class="dgptm-vw-heading">
        <span class="dashicons dashicons-format-video" aria-hidden="true"></span>
        Verfügbare Webinare
    </h2>

    <?php if (empty($webinars_raw)): ?>
        <p class="dgptm-vw-empty">Derzeit sind keine Webinare verfügbar.</p>
    <?php else: ?>

        <div class="dgptm-vw-filter">
            <label class="dgptm-vw-search">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <input type="text" class="dgptm-vw-liste-search" placeholder="Webinare durchsuchen..." />
            </label>
            <select class="dgptm-vw-liste-status">
                <option value="all">Alle anzeigen</option>
                <option value="not-started">Noch nicht begonnen</option>
                <option value="in-progress">In Bearbeitung</option>
                <option value="completed">Abgeschlossen</option>
            </select>
        </div>

        <div class="dgptm-vw-grid">
            <?php foreach ($webinars_raw as $webinar):
                $webinar_id = $webinar->ID;
                $vimeo_id = get_field('vimeo_id', $webinar_id);
                $points = get_field('ebcp_points', $webinar_id) ?: 1;
                $completion_req = get_field('completion_percentage', $webinar_id) ?: 90;

                $progress = 0;
                $is_completed = false;
                $is_local_progress = false;

                if ($is_logged_in && $user_id) {
                    $progress = floatval(get_user_meta($user_id, '_vw_progress_' . $webinar_id, true));
                    $is_completed = (bool) get_user_meta($user_id, '_vw_completed_' . $webinar_id, true);
                } elseif (isset($cookie_progress[$webinar_id])) {
                    $progress = $cookie_progress[$webinar_id]['progress'] ?? 0;
                    $is_local_progress = true;
                }

                $status = 'not-started';
                $badge_class = 'dgptm-badge--muted';
                $status_label = 'Noch nicht begonnen';
                if ($is_completed) {
                    $status = 'completed';
                    $badge_class = 'dgptm-badge--success';
                    $status_label = 'Abgeschlossen';
                } elseif ($progress > 0) {
                    $status = 'in-progress';
                    $badge_class = 'dgptm-badge--accent';
                    $status_label = 'In Bearbeitung';
                }

                $thumbnail = $vimeo_id ? "https://vumbnail.com/{$vimeo_id}.jpg" : '';
            ?>
            <article class="dgptm-card dgptm-vw-webinar-card"
                     data-status="<?php echo esc_attr($status); ?>"
                     data-title="<?php echo esc_attr(strtolower($webinar->post_title)); ?>">

                <div class="dgptm-vw-thumb"<?php if ($thumbnail): ?> style="background-image:url('<?php echo esc_url($thumbnail); ?>');"<?php endif; ?>>
                    <?php if (!$thumbnail): ?>
                        <span class="dashicons dashicons-format-video dgptm-vw-thumb-fallback" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="dgptm-badge <?php echo esc_attr($badge_class); ?> dgptm-vw-thumb-badge">
                        <?php if ($is_completed): ?>
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <?php endif; ?>
                        <?php echo esc_html($status_label); ?>
                    </span>
                    <?php if ($is_local_progress): ?>
                        <span class="dgptm-vw-local-indicator" title="Nur lokal gespeichert" aria-label="Nur lokal gespeichert">
                            <span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="dgptm-vw-card-body">
                    <h3 class="dgptm-vw-card-title"><?php echo esc_html($webinar->post_title); ?></h3>

                    <div class="dgptm-vw-card-meta">
                        <span><span class="dashicons dashicons-star-filled"></span> <?php echo esc_html(number_format_i18n($points, 1)); ?> EBCP</span>
                        <span><span class="dashicons dashicons-clock"></span> <?php echo esc_html($completion_req); ?>% erf.</span>
                    </div>

                    <?php if ($progress > 0): ?>
                        <div class="dgptm-progress">
                            <div class="dgptm-progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
                        </div>
                        <div class="dgptm-vw-card-progress-text"><?php echo esc_html(number_format($progress, 0)); ?>%</div>
                    <?php endif; ?>

                    <p class="dgptm-vw-card-excerpt"><?php echo esc_html(wp_trim_words($webinar->post_content, 20)); ?></p>

                    <div class="dgptm-vw-card-actions">
                        <a href="<?php echo esc_url(home_url('/wissen/webinar/' . $webinar_id)); ?>" class="dgptm-btn--primary">
                            <?php
                            if ($is_completed) echo 'Erneut ansehen';
                            elseif ($progress > 0) echo 'Fortsetzen';
                            else echo 'Jetzt ansehen';
                            ?>
                        </a>
                        <?php if ($is_completed && $is_logged_in): ?>
                            <button type="button" class="dgptm-btn--ghost dgptm-vw-certificate" data-webinar-id="<?php echo esc_attr($webinar_id); ?>">
                                <span class="dashicons dashicons-awards" aria-hidden="true"></span>
                                Zertifikat
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>
