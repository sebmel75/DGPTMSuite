<?php
/**
 * Template: Webinar-Statistiken.
 * Variablen: $webinars, $stats, $total_webinars, $total_completed,
 *            $total_in_progress, $total_views, $avg_rate
 */
if (!defined('ABSPATH')) exit;
?>
<div class="dgptm-vw dgptm-vw-stats">

    <div class="dgptm-vw-kpi-grid">
        <div class="dgptm-card dgptm-vw-kpi">
            <div class="dgptm-vw-kpi-number"><?php echo esc_html($total_webinars); ?></div>
            <div class="dgptm-vw-kpi-label">Gesamt Webinare</div>
        </div>
        <div class="dgptm-card dgptm-vw-kpi">
            <div class="dgptm-vw-kpi-number"><?php echo esc_html($total_completed); ?></div>
            <div class="dgptm-vw-kpi-label">Gesamt Abschlüsse</div>
        </div>
        <div class="dgptm-card dgptm-vw-kpi">
            <div class="dgptm-vw-kpi-number"><?php echo esc_html($total_in_progress); ?></div>
            <div class="dgptm-vw-kpi-label">In Bearbeitung</div>
        </div>
        <div class="dgptm-card dgptm-vw-kpi">
            <div class="dgptm-vw-kpi-number"><?php echo esc_html(number_format_i18n($avg_rate, 1)); ?>%</div>
            <div class="dgptm-vw-kpi-label">Abschlussrate Ø (gewichtet)</div>
        </div>
    </div>

    <?php if (!empty($webinars)): ?>
    <div class="dgptm-card dgptm-vw-performance">
        <h3>Performance</h3>
        <table class="dgptm-vw-stats-table">
            <thead>
                <tr>
                    <th data-sort="title">Webinar</th>
                    <th data-sort="completed">Abgeschlossen</th>
                    <th data-sort="in_progress">In Bearbeitung</th>
                    <th data-sort="views">Gesamt Ansichten</th>
                    <th data-sort="rate">Completion-Rate</th>
                    <th>Verlauf</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($webinars as $w):
                    $s = $stats[$w['id']] ?? ['completed' => 0, 'in_progress' => 0, 'total_views' => 0];
                    $rate = $s['total_views'] > 0
                        ? round($s['completed'] / $s['total_views'] * 100, 1)
                        : 0.0;
                ?>
                <tr data-rate="<?php echo esc_attr($rate); ?>"
                    data-completed="<?php echo esc_attr($s['completed']); ?>"
                    data-in_progress="<?php echo esc_attr($s['in_progress']); ?>"
                    data-views="<?php echo esc_attr($s['total_views']); ?>"
                    data-title="<?php echo esc_attr(strtolower($w['title'])); ?>">
                    <td data-label="Webinar"><?php echo esc_html($w['title']); ?></td>
                    <td data-label="Abgeschlossen"><?php echo esc_html($s['completed']); ?></td>
                    <td data-label="In Bearbeitung"><?php echo esc_html($s['in_progress']); ?></td>
                    <td data-label="Ansichten"><?php echo esc_html($s['total_views']); ?></td>
                    <td data-label="Rate"><?php echo esc_html(number_format_i18n($rate, 1)); ?>%</td>
                    <td data-label="Verlauf">
                        <div class="dgptm-vw-sparkline" style="--rate: <?php echo esc_attr($rate); ?>"></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
