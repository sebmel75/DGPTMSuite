<?php
/**
 * Template: Webinar Liste
 * Variables: $webinars, $user_id
 */

if (!defined('ABSPATH')) exit;
?>

<div class="vw-liste-container">

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

                $instance = DGPTM_Vimeo_Webinare::get_instance();
                $progress = get_user_meta($user_id, '_vw_progress_' . $webinar_id, true) ?: 0;
                $is_completed = get_user_meta($user_id, '_vw_completed_' . $webinar_id, true);

                // Status
                $status = 'not-started';
                $status_label = 'Nicht begonnen';
                $status_class = 'status-new';

                if ($is_completed) {
                    $status = 'completed';
                    $status_label = 'Abgeschlossen';
                    $status_class = 'status-completed';
                } elseif ($progress > 0) {
                    $status = 'in-progress';
                    $status_label = 'In Bearbeitung';
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
                                <div class="vw-card-progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
                            </div>
                            <span class="vw-card-progress-text"><?php echo esc_html(number_format($progress, 0)); ?>%</span>
                        </div>
                    <?php endif; ?>

                    <div class="vw-card-excerpt">
                        <?php echo wp_trim_words($webinar->post_content, 20); ?>
                    </div>

                    <div class="vw-card-actions">
                        <a href="<?php echo home_url('/webinar/' . $webinar_id); ?>" class="vw-btn vw-btn-primary">
                            <?php echo $is_completed ? 'Erneut ansehen' : ($progress > 0 ? 'Fortsetzen' : 'Jetzt ansehen'); ?>
                        </a>

                        <?php if ($is_completed): ?>
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
