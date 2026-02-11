<?php
/**
 * Admin template: Survey results dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

$admin = DGPTM_Survey_Admin::get_instance();
$survey_id = isset($_GET['survey_id']) ? absint($_GET['survey_id']) : 0;
$survey = $survey_id ? $admin->get_survey($survey_id) : null;

if (!$survey) {
    echo '<div class="wrap"><h1>Umfrage nicht gefunden</h1></div>';
    return;
}

$questions = $admin->get_questions($survey_id);
$responses = $admin->get_responses($survey_id, 'completed');
$total_completed = count($responses);
$total_in_progress = $admin->get_response_count($survey_id, 'in_progress');

$list_url = admin_url('admin.php?page=dgptm-umfragen');
$edit_url = admin_url('admin.php?page=dgptm-umfragen&view=edit&survey_id=' . $survey_id);

// View mode: overview or detail
$view_response = isset($_GET['response_id']) ? absint($_GET['response_id']) : 0;
?>
<div class="wrap dgptm-umfragen-wrap">
    <h1>
        <a href="<?php echo esc_url($list_url); ?>">&larr; Zurueck</a> |
        Ergebnisse: <?php echo esc_html($survey->title); ?>
        <a href="<?php echo esc_url($edit_url); ?>" class="page-title-action">Bearbeiten</a>
    </h1>

    <!-- Overview stats -->
    <div class="dgptm-results-overview">
        <div class="dgptm-stat-card">
            <span class="dgptm-stat-value"><?php echo esc_html($total_completed); ?></span>
            <span class="dgptm-stat-label">Abgeschlossen</span>
        </div>
        <div class="dgptm-stat-card">
            <span class="dgptm-stat-value"><?php echo esc_html($total_in_progress); ?></span>
            <span class="dgptm-stat-label">In Bearbeitung</span>
        </div>
        <div class="dgptm-stat-card">
            <span class="dgptm-stat-value"><?php echo $total_completed + $total_in_progress > 0 ? round(($total_completed / ($total_completed + $total_in_progress)) * 100) : 0; ?>%</span>
            <span class="dgptm-stat-label">Abschlussrate</span>
        </div>
        <div class="dgptm-stat-card">
            <?php
            $first_response = !empty($responses) ? end($responses) : null;
            $last_response = !empty($responses) ? reset($responses) : null;
            ?>
            <span class="dgptm-stat-value" style="font-size:16px;">
                <?php echo $first_response ? esc_html(wp_date('d.m.Y', strtotime($first_response->started_at))) : '-'; ?>
                - <?php echo $last_response ? esc_html(wp_date('d.m.Y', strtotime($last_response->completed_at))) : '-'; ?>
            </span>
            <span class="dgptm-stat-label">Zeitraum</span>
        </div>
    </div>

    <!-- Export button -->
    <?php if ($total_completed > 0) : ?>
    <p>
        <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=dgptm_survey_export_csv&survey_id=' . $survey_id . '&nonce=' . wp_create_nonce('dgptm_suite_nonce'))); ?>"
           class="button">
            CSV Export
        </a>
        <?php if ($survey->results_token) : ?>
            <button type="button" class="button dgptm-copy-results-link" data-token="<?php echo esc_attr($survey->results_token); ?>">
                Oeffentlichen Ergebnis-Link kopieren
            </button>
        <?php endif; ?>
    </p>
    <?php endif; ?>

    <?php if ($view_response) : ?>
        <!-- Single response detail -->
        <?php
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_survey_responses WHERE id = %d",
            $view_response
        ));
        $answers = $admin->get_answers($view_response);
        $answers_map = [];
        foreach ($answers as $a) {
            $answers_map[$a->question_id] = $a;
        }
        ?>
        <div class="card" style="max-width: 800px; padding: 20px;">
            <h2>Antwort #<?php echo esc_html($view_response); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dgptm-umfragen&view=results&survey_id=' . $survey_id)); ?>" class="button button-small">Zurueck zur Uebersicht</a>
            </h2>
            <?php if ($response) : ?>
            <table class="form-table dgptm-response-detail">
                <tr>
                    <th>Datum</th>
                    <td><?php echo esc_html(wp_date('d.m.Y H:i', strtotime($response->completed_at ?: $response->started_at))); ?></td>
                </tr>
                <?php if ($response->respondent_name) : ?>
                <tr><th>Name</th><td><?php echo esc_html($response->respondent_name); ?></td></tr>
                <?php endif; ?>
                <?php if ($response->respondent_email) : ?>
                <tr><th>E-Mail</th><td><?php echo esc_html($response->respondent_email); ?></td></tr>
                <?php endif; ?>
                <tr><th>IP</th><td><?php echo esc_html($response->respondent_ip); ?></td></tr>
            </table>

            <hr>

            <?php foreach ($questions as $q) :
                $answer = isset($answers_map[$q->id]) ? $answers_map[$q->id] : null;
                $val = $answer ? $answer->answer_value : '';
                $display_val = $val;

                // Decode JSON values
                if ($val && in_array($q->question_type, ['checkbox', 'matrix'], true)) {
                    $decoded = json_decode($val, true);
                    if (is_array($decoded)) {
                        if ($q->question_type === 'checkbox') {
                            $display_val = implode(', ', $decoded);
                        } elseif ($q->question_type === 'matrix') {
                            $parts = [];
                            foreach ($decoded as $row => $col) {
                                $parts[] = $row . ': ' . $col;
                            }
                            $display_val = implode('; ', $parts);
                        }
                    }
                }

                // File answers
                if ($q->question_type === 'file' && $answer && $answer->file_ids) {
                    $fids = json_decode($answer->file_ids, true);
                    if (is_array($fids)) {
                        $links = [];
                        foreach ($fids as $fid) {
                            $url = wp_get_attachment_url(absint($fid));
                            if ($url) {
                                $links[] = '<a href="' . esc_url($url) . '" target="_blank">' . esc_html(basename($url)) . '</a>';
                            }
                        }
                        $display_val = implode(', ', $links);
                    }
                }
            ?>
                <div style="margin-bottom: 12px;">
                    <strong><?php echo esc_html($q->sort_order . '. ' . $q->question_text); ?></strong><br>
                    <?php if ($q->question_type === 'file') : ?>
                        <?php echo $display_val ?: '<em>Keine Antwort</em>'; ?>
                    <?php else : ?>
                        <?php echo esc_html($display_val ?: '(Keine Antwort)'); ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php else : ?>
        <!-- Aggregated results per question -->
        <?php if ($total_completed > 0) : ?>
            <?php foreach ($questions as $q) :
                $q_answers = $admin->get_question_answers($q->id, $survey_id);
                $choices = $q->choices ? json_decode($q->choices, true) : [];
            ?>
                <div class="dgptm-question-result">
                    <h3>
                        <span class="dgptm-q-number"><?php echo esc_html($q->sort_order); ?>.</span>
                        <?php echo esc_html($q->question_text); ?>
                        <span style="color:#999;font-size:12px;font-weight:normal;">
                            (<?php echo count($q_answers); ?> Antworten)
                        </span>
                    </h3>

                    <?php
                    switch ($q->question_type) :
                        case 'radio':
                        case 'select':
                            // Bar chart
                            $counts = [];
                            if (is_array($choices)) {
                                foreach ($choices as $c) {
                                    $counts[$c] = 0;
                                }
                            }
                            foreach ($q_answers as $a) {
                                $v = $a->answer_value;
                                if (isset($counts[$v])) {
                                    $counts[$v]++;
                                } else {
                                    $counts[$v] = 1;
                                }
                            }
                            $max_count = max(array_merge($counts, [1]));
                            ?>
                            <div class="dgptm-bar-chart">
                                <?php foreach ($counts as $label => $count) :
                                    $pct = $max_count > 0 ? round(($count / $max_count) * 100) : 0;
                                    $pct_of_total = count($q_answers) > 0 ? round(($count / count($q_answers)) * 100) : 0;
                                ?>
                                    <div class="dgptm-bar-row">
                                        <span class="dgptm-bar-label" title="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></span>
                                        <div class="dgptm-bar-track">
                                            <div class="dgptm-bar-fill" style="width: <?php echo esc_attr($pct); ?>%"></div>
                                        </div>
                                        <span class="dgptm-bar-count"><?php echo esc_html($count); ?> (<?php echo esc_html($pct_of_total); ?>%)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php break;

                        case 'checkbox':
                            // Bar chart with multiple selections
                            $counts = [];
                            if (is_array($choices)) {
                                foreach ($choices as $c) {
                                    $counts[$c] = 0;
                                }
                            }
                            foreach ($q_answers as $a) {
                                $vals = json_decode($a->answer_value, true);
                                if (is_array($vals)) {
                                    foreach ($vals as $v) {
                                        if (isset($counts[$v])) {
                                            $counts[$v]++;
                                        } else {
                                            $counts[$v] = 1;
                                        }
                                    }
                                }
                            }
                            $max_count = max(array_merge($counts, [1]));
                            ?>
                            <div class="dgptm-bar-chart">
                                <?php foreach ($counts as $label => $count) :
                                    $pct = $max_count > 0 ? round(($count / $max_count) * 100) : 0;
                                    $pct_of_total = count($q_answers) > 0 ? round(($count / count($q_answers)) * 100) : 0;
                                ?>
                                    <div class="dgptm-bar-row">
                                        <span class="dgptm-bar-label" title="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></span>
                                        <div class="dgptm-bar-track">
                                            <div class="dgptm-bar-fill" style="width: <?php echo esc_attr($pct); ?>%"></div>
                                        </div>
                                        <span class="dgptm-bar-count"><?php echo esc_html($count); ?> (<?php echo esc_html($pct_of_total); ?>%)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php break;

                        case 'number':
                            // Statistics
                            $values = [];
                            foreach ($q_answers as $a) {
                                $v = $a->answer_value;
                                if (is_numeric($v)) {
                                    $values[] = floatval($v);
                                }
                            }
                            if (!empty($values)) :
                                sort($values);
                                $sum = array_sum($values);
                                $cnt = count($values);
                                $avg = $sum / $cnt;
                                $median_idx = (int) floor($cnt / 2);
                                $median = $cnt % 2 === 0 ? ($values[$median_idx - 1] + $values[$median_idx]) / 2 : $values[$median_idx];
                            ?>
                                <div class="dgptm-number-stats">
                                    <div class="dgptm-number-stat">
                                        <strong><?php echo esc_html(number_format_i18n(min($values))); ?></strong>
                                        <span>Minimum</span>
                                    </div>
                                    <div class="dgptm-number-stat">
                                        <strong><?php echo esc_html(number_format_i18n(max($values))); ?></strong>
                                        <span>Maximum</span>
                                    </div>
                                    <div class="dgptm-number-stat">
                                        <strong><?php echo esc_html(number_format_i18n($avg, 1)); ?></strong>
                                        <span>Durchschnitt</span>
                                    </div>
                                    <div class="dgptm-number-stat">
                                        <strong><?php echo esc_html(number_format_i18n($median, 1)); ?></strong>
                                        <span>Median</span>
                                    </div>
                                    <div class="dgptm-number-stat">
                                        <strong><?php echo esc_html(number_format_i18n($sum)); ?></strong>
                                        <span>Summe</span>
                                    </div>
                                </div>
                            <?php else : ?>
                                <p style="color:#999;">Keine numerischen Antworten.</p>
                            <?php endif;
                            break;

                        case 'matrix':
                            // Heatmap table
                            $rows = isset($choices['rows']) ? $choices['rows'] : [];
                            $cols = isset($choices['columns']) ? $choices['columns'] : [];
                            $matrix_counts = [];
                            foreach ($rows as $r) {
                                $rk = sanitize_title($r);
                                foreach ($cols as $c) {
                                    $matrix_counts[$rk][$c] = 0;
                                }
                            }
                            foreach ($q_answers as $a) {
                                $vals = json_decode($a->answer_value, true);
                                if (is_array($vals)) {
                                    foreach ($vals as $rk => $cv) {
                                        if (isset($matrix_counts[$rk][$cv])) {
                                            $matrix_counts[$rk][$cv]++;
                                        }
                                    }
                                }
                            }
                            $matrix_max = 1;
                            foreach ($matrix_counts as $rk => $rcounts) {
                                foreach ($rcounts as $c) {
                                    if ($c > $matrix_max) $matrix_max = $c;
                                }
                            }
                            ?>
                            <div class="dgptm-matrix-result">
                                <table>
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <?php foreach ($cols as $c) : ?>
                                                <th><?php echo esc_html($c); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $r) :
                                            $rk = sanitize_title($r);
                                        ?>
                                            <tr>
                                                <th><?php echo esc_html($r); ?></th>
                                                <?php foreach ($cols as $c) :
                                                    $val = isset($matrix_counts[$rk][$c]) ? $matrix_counts[$rk][$c] : 0;
                                                    $intensity = $matrix_max > 0 ? round(($val / $matrix_max) * 100) : 0;
                                                    $bg = 'rgba(34, 113, 177, ' . ($intensity / 100 * 0.4) . ')';
                                                ?>
                                                    <td class="dgptm-matrix-cell" style="background: <?php echo $bg; ?>">
                                                        <?php echo esc_html($val); ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php break;

                        case 'text':
                        case 'textarea':
                            // List of text responses
                            ?>
                            <div class="dgptm-text-responses">
                                <?php foreach ($q_answers as $a) : ?>
                                    <div class="dgptm-text-response-item"><?php echo esc_html($a->answer_value); ?></div>
                                <?php endforeach; ?>
                                <?php if (empty($q_answers)) : ?>
                                    <p style="color:#999;">Keine Antworten.</p>
                                <?php endif; ?>
                            </div>
                            <?php break;

                        case 'file':
                            // File list
                            $file_count = 0;
                            foreach ($q_answers as $a) {
                                if ($a->file_ids) {
                                    $fids = json_decode($a->file_ids, true);
                                    if (is_array($fids)) $file_count += count($fids);
                                }
                            }
                            ?>
                            <p><?php echo esc_html($file_count); ?> Datei(en) hochgeladen.</p>
                            <?php break;

                    endswitch;
                    ?>
                </div>
            <?php endforeach; ?>

            <!-- Individual responses table -->
            <div class="card" style="max-width: 960px; padding: 20px; margin-top: 20px;">
                <h2>Einzelne Antworten</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Name</th>
                            <th>E-Mail</th>
                            <th style="width: 130px;">Datum</th>
                            <th style="width: 100px;">IP</th>
                            <th style="width: 120px;">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($responses as $r) : ?>
                            <tr data-response-id="<?php echo esc_attr($r->id); ?>">
                                <td><?php echo esc_html($r->id); ?></td>
                                <td><?php echo esc_html($r->respondent_name ?: '-'); ?></td>
                                <td><?php echo esc_html($r->respondent_email ?: '-'); ?></td>
                                <td><?php echo esc_html(wp_date('d.m.Y H:i', strtotime($r->completed_at ?: $r->started_at))); ?></td>
                                <td><?php echo esc_html($r->respondent_ip); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=dgptm-umfragen&view=results&survey_id=' . $survey_id . '&response_id=' . $r->id)); ?>"
                                       class="button button-small" title="Details">
                                        <span class="dashicons dashicons-visibility" style="vertical-align: text-bottom;"></span>
                                    </a>
                                    <button type="button" class="button button-small dgptm-delete-response" data-id="<?php echo esc_attr($r->id); ?>" title="Loeschen">
                                        <span class="dashicons dashicons-trash" style="vertical-align: text-bottom;"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else : ?>
            <div class="card" style="max-width: 600px; padding: 30px; text-align: center;">
                <p>Noch keine abgeschlossenen Antworten vorhanden.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
jQuery(function($) {
    // Delete response
    $(document).on('click', '.dgptm-delete-response', function() {
        if (!confirm('Antwort wirklich loeschen?')) return;
        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $.post(dgptmUmfragen.ajaxUrl, {
            action: 'dgptm_survey_delete_response',
            nonce: dgptmUmfragen.nonce,
            response_id: id
        }, function(resp) {
            if (resp.success) {
                $row.fadeOut(300, function() { $(this).remove(); });
            }
        });
    });
});
</script>
