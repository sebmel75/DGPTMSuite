<?php
/**
 * Admin template: Survey list
 */

if (!defined('ABSPATH')) {
    exit;
}

$admin = DGPTM_Survey_Admin::get_instance();
$surveys = $admin->get_surveys();

$status_labels = [
    'draft'    => ['label' => 'Entwurf', 'color' => '#999'],
    'active'   => ['label' => 'Aktiv', 'color' => '#46b450'],
    'closed'   => ['label' => 'Geschlossen', 'color' => '#dc3232'],
    'archived' => ['label' => 'Archiviert', 'color' => '#666'],
];

$edit_url = admin_url('admin.php?page=dgptm-umfragen&view=edit');
$results_url = admin_url('admin.php?page=dgptm-umfragen&view=results');
?>
<div class="wrap dgptm-umfragen-wrap">
    <h1 class="wp-heading-inline">Umfragen</h1>
    <a href="<?php echo esc_url($edit_url); ?>" class="page-title-action">Neue Umfrage</a>
    <button type="button" class="page-title-action" id="dgptm-seed-ecls">ECLS-Zentren anlegen</button>
    <hr class="wp-header-end">

    <?php if (empty($surveys)) : ?>
        <div class="dgptm-umfragen-empty">
            <p>Keine Umfragen vorhanden. Erstellen Sie eine neue Umfrage oder legen Sie die ECLS-Zentren Musterumfrage an.</p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-title">Titel</th>
                    <th class="column-status" style="width: 100px;">Status</th>
                    <th class="column-responses" style="width: 100px;">Antworten</th>
                    <th class="column-shortcode" style="width: 220px;">Shortcode</th>
                    <th class="column-date" style="width: 140px;">Erstellt</th>
                    <th class="column-actions" style="width: 200px;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($surveys as $survey) :
                    $s = isset($status_labels[$survey->status]) ? $status_labels[$survey->status] : $status_labels['draft'];
                ?>
                    <tr data-survey-id="<?php echo esc_attr($survey->id); ?>">
                        <td class="column-title">
                            <strong>
                                <a href="<?php echo esc_url($edit_url . '&survey_id=' . $survey->id); ?>">
                                    <?php echo esc_html($survey->title); ?>
                                </a>
                            </strong>
                            <?php if ($survey->description) : ?>
                                <br><span class="description"><?php echo esc_html(wp_trim_words($survey->description, 15)); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-status">
                            <span class="dgptm-status-badge" style="background: <?php echo esc_attr($s['color']); ?>">
                                <?php echo esc_html($s['label']); ?>
                            </span>
                        </td>
                        <td class="column-responses">
                            <a href="<?php echo esc_url($results_url . '&survey_id=' . $survey->id); ?>">
                                <?php echo esc_html($survey->response_count); ?>
                            </a>
                        </td>
                        <td class="column-shortcode">
                            <code class="dgptm-shortcode-copy" title="Klicken zum Kopieren">[dgptm_umfrage slug="<?php echo esc_attr($survey->slug); ?>"]</code>
                        </td>
                        <td class="column-date">
                            <?php echo esc_html(wp_date('d.m.Y H:i', strtotime($survey->created_at))); ?>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo esc_url($edit_url . '&survey_id=' . $survey->id); ?>" class="button button-small" title="Bearbeiten">
                                <span class="dashicons dashicons-edit" style="vertical-align: text-bottom;"></span>
                            </a>
                            <a href="<?php echo esc_url($results_url . '&survey_id=' . $survey->id); ?>" class="button button-small" title="Ergebnisse">
                                <span class="dashicons dashicons-chart-bar" style="vertical-align: text-bottom;"></span>
                            </a>
                            <button type="button" class="button button-small dgptm-duplicate-survey" data-id="<?php echo esc_attr($survey->id); ?>" title="Duplizieren">
                                <span class="dashicons dashicons-admin-page" style="vertical-align: text-bottom;"></span>
                            </button>
                            <?php if ($survey->results_token) : ?>
                                <button type="button" class="button button-small dgptm-copy-results-link" data-token="<?php echo esc_attr($survey->results_token); ?>" title="Ergebnis-Link kopieren">
                                    <span class="dashicons dashicons-share" style="vertical-align: text-bottom;"></span>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="button button-small dgptm-delete-survey" data-id="<?php echo esc_attr($survey->id); ?>" title="Archivieren">
                                <span class="dashicons dashicons-trash" style="vertical-align: text-bottom;"></span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
