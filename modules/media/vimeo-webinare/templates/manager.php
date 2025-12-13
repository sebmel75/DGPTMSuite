<?php
/**
 * Template: Webinar Frontend Manager
 * Variables: $user_id, $is_manager, $is_admin
 */

if (!defined('ABSPATH')) exit;

$all_webinars = get_posts([
    'post_type' => 'vimeo_webinar',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'orderby' => 'title',
    'order' => 'ASC',
]);
?>

<div class="vw-manager-container">

    <div class="vw-manager-header">
        <h2>🎬 Webinar Manager</h2>
        <button class="vw-btn vw-btn-primary" id="vw-create-new">
            <span class="dashicons dashicons-plus-alt"></span> Neues Webinar erstellen
        </button>
    </div>

    <div class="vw-manager-tabs">
        <button class="vw-tab-btn active" data-tab="list">Liste</button>
        <button class="vw-tab-btn" data-tab="stats">Statistiken</button>
    </div>

    <!-- Tab: Liste -->
    <div class="vw-tab-content vw-tab-list active">

        <div class="vw-manager-search">
            <input type="text" class="vw-manager-search-input" placeholder="Webinare durchsuchen..." />
        </div>

        <table class="vw-manager-table">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Vimeo ID</th>
                    <th>EBCP Punkte</th>
                    <th>Erforderlich %</th>
                    <th>Abgeschlossen</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="vw-manager-tbody">
                <?php foreach ($all_webinars as $webinar):
                    $webinar_id = $webinar->ID;
                    $vimeo_id = get_field('vimeo_id', $webinar_id);
                    $points = get_field('ebcp_points', $webinar_id) ?: 1;
                    $completion = get_field('completion_percentage', $webinar_id) ?: 90;

                    $instance = DGPTM_Vimeo_Webinare::get_instance();
                    $stats_method = new ReflectionMethod($instance, 'get_webinar_stats');
                    $stats_method->setAccessible(true);
                    $stats = $stats_method->invoke($instance, $webinar_id);
                ?>
                <tr data-id="<?php echo esc_attr($webinar_id); ?>" data-title="<?php echo esc_attr(strtolower($webinar->post_title)); ?>">
                    <td><strong><?php echo esc_html($webinar->post_title); ?></strong></td>
                    <td><?php echo esc_html($vimeo_id); ?></td>
                    <td><?php echo esc_html($points); ?></td>
                    <td><?php echo esc_html($completion); ?>%</td>
                    <td><?php echo esc_html($stats['completed']); ?></td>
                    <td class="vw-actions">
                        <button class="vw-btn-icon vw-edit" data-id="<?php echo esc_attr($webinar_id); ?>" title="Bearbeiten">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button class="vw-btn-icon vw-view-stats" data-id="<?php echo esc_attr($webinar_id); ?>" title="Statistiken">
                            <span class="dashicons dashicons-chart-bar"></span>
                        </button>
                        <a href="<?php echo home_url('/wissen/webinar/' . $webinar_id); ?>" class="vw-btn-icon" title="Ansehen" target="_blank">
                            <span class="dashicons dashicons-visibility"></span>
                        </a>
                        <button class="vw-btn-icon vw-delete" data-id="<?php echo esc_attr($webinar_id); ?>" title="Löschen">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($all_webinars)): ?>
            <p class="vw-no-data">Keine Webinare vorhanden. Erstellen Sie Ihr erstes Webinar!</p>
        <?php endif; ?>

    </div>

    <!-- Tab: Statistiken -->
    <div class="vw-tab-content vw-tab-stats">
        <div class="vw-stats-grid">
            <div class="vw-stat-card">
                <div class="vw-stat-number"><?php echo count($all_webinars); ?></div>
                <div class="vw-stat-label">Gesamt Webinare</div>
            </div>

            <?php
            $total_completed = 0;
            $total_in_progress = 0;
            foreach ($all_webinars as $webinar) {
                $instance = DGPTM_Vimeo_Webinare::get_instance();
                $stats_method = new ReflectionMethod($instance, 'get_webinar_stats');
                $stats_method->setAccessible(true);
                $stats = $stats_method->invoke($instance, $webinar->ID);
                $total_completed += $stats['completed'];
                $total_in_progress += $stats['in_progress'];
            }
            ?>

            <div class="vw-stat-card">
                <div class="vw-stat-number"><?php echo $total_completed; ?></div>
                <div class="vw-stat-label">Gesamt Abschlüsse</div>
            </div>

            <div class="vw-stat-card">
                <div class="vw-stat-number"><?php echo $total_in_progress; ?></div>
                <div class="vw-stat-label">In Bearbeitung</div>
            </div>
        </div>

        <div class="vw-stats-table-container">
            <h3>Webinar Performance</h3>
            <table class="vw-stats-table">
                <thead>
                    <tr>
                        <th>Webinar</th>
                        <th>Abgeschlossen</th>
                        <th>In Bearbeitung</th>
                        <th>Gesamt Ansichten</th>
                        <th>Completion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_webinars as $webinar):
                        $instance = DGPTM_Vimeo_Webinare::get_instance();
                        $stats_method = new ReflectionMethod($instance, 'get_webinar_stats');
                        $stats_method->setAccessible(true);
                        $stats = $stats_method->invoke($instance, $webinar->ID);
                        $rate = $stats['total_views'] > 0 ? ($stats['completed'] / $stats['total_views'] * 100) : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html($webinar->post_title); ?></td>
                        <td><?php echo $stats['completed']; ?></td>
                        <td><?php echo $stats['in_progress']; ?></td>
                        <td><?php echo $stats['total_views']; ?></td>
                        <td><?php echo number_format($rate, 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal: Create/Edit Webinar -->
<div id="vw-modal" class="vw-modal" style="display: none;">
    <div class="vw-modal-content">
        <div class="vw-modal-header">
            <h3 id="vw-modal-title">Webinar erstellen</h3>
            <button class="vw-modal-close">&times;</button>
        </div>
        <div class="vw-modal-body">
            <form id="vw-webinar-form">
                <input type="hidden" name="post_id" id="vw-post-id" value="" />

                <div class="vw-form-group">
                    <label for="vw-title">Titel *</label>
                    <input type="text" id="vw-title" name="title" required />
                </div>

                <div class="vw-form-group">
                    <label for="vw-description">Beschreibung</label>
                    <textarea id="vw-description" name="description" rows="4"></textarea>
                </div>

                <div class="vw-form-row">
                    <div class="vw-form-group">
                        <label for="vw-vimeo-id">Vimeo Video ID *</label>
                        <input type="text" id="vw-vimeo-id" name="vimeo_id" required />
                        <small>Nur die Zahlen-ID (z.B. 123456789)</small>
                    </div>

                    <div class="vw-form-group">
                        <label for="vw-completion">Erforderlicher Fortschritt (%) *</label>
                        <input type="number" id="vw-completion" name="completion_percentage" min="1" max="100" value="90" required />
                    </div>
                </div>

                <div class="vw-form-row">
                    <div class="vw-form-group">
                        <label for="vw-points">EBCP Punkte *</label>
                        <input type="number" id="vw-points" name="points" step="0.5" min="0" value="1" required />
                    </div>

                    <div class="vw-form-group">
                        <label for="vw-vnr">VNR</label>
                        <input type="text" id="vw-vnr" name="vnr" />
                    </div>
                </div>

                <div class="vw-form-actions">
                    <button type="button" class="vw-btn vw-btn-secondary vw-modal-close">Abbrechen</button>
                    <button type="submit" class="vw-btn vw-btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Statistiken Details -->
<div id="vw-stats-modal" class="vw-modal" style="display: none;">
    <div class="vw-modal-content">
        <div class="vw-modal-header">
            <h3 id="vw-stats-modal-title">Webinar Statistiken</h3>
            <button class="vw-modal-close">&times;</button>
        </div>
        <div class="vw-modal-body" id="vw-stats-modal-body">
            <!-- Loaded via AJAX -->
        </div>
    </div>
</div>
