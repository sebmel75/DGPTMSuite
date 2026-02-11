<?php
/**
 * Public results template - shown via results_token URL
 *
 * Variables available: $survey, $token
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$questions = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}dgptm_survey_questions WHERE survey_id = %d ORDER BY sort_order ASC",
    $survey->id
));

$total_completed = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_survey_responses WHERE survey_id = %d AND status = 'completed'",
    $survey->id
));

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($survey->title); ?> - Ergebnisse</title>
    <?php wp_head(); ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background: #f0f0f1;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .dgptm-public-results {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 30px 40px;
        }
        h1 { font-size: 24px; margin: 0 0 8px; }
        .dgptm-result-summary { color: #666; font-size: 14px; margin-bottom: 30px; }
        .dgptm-public-question {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .dgptm-public-question:last-child { border-bottom: none; }
        .dgptm-public-question h3 {
            font-size: 15px;
            margin: 0 0 12px;
            color: #1d2327;
        }
        .dgptm-public-question .q-num { color: #2271b1; margin-right: 4px; }
        .dgptm-bar-row { display: flex; align-items: center; margin-bottom: 5px; gap: 8px; }
        .dgptm-bar-label { min-width: 150px; max-width: 200px; font-size: 13px; text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dgptm-bar-track { flex: 1; background: #f0f0f1; border-radius: 3px; height: 20px; overflow: hidden; }
        .dgptm-bar-fill { height: 100%; background: #2271b1; border-radius: 3px; min-width: 2px; }
        .dgptm-bar-count { min-width: 50px; font-size: 12px; color: #666; }
        .dgptm-number-stats { display: flex; gap: 20px; flex-wrap: wrap; }
        .dgptm-number-stat { text-align: center; }
        .dgptm-number-stat strong { display: block; font-size: 20px; color: #2271b1; }
        .dgptm-matrix-result table { border-collapse: collapse; width: 100%; }
        .dgptm-matrix-result th, .dgptm-matrix-result td { border: 1px solid #ddd; padding: 8px; text-align: center; font-size: 13px; }
        .dgptm-matrix-result th { background: #f6f7f7; font-weight: 600; }
        .dgptm-matrix-result th:first-child { text-align: left; }
        .dgptm-footer { text-align: center; margin-top: 30px; color: #999; font-size: 12px; }
        @media (max-width: 600px) {
            .dgptm-public-results { padding: 20px; }
            .dgptm-bar-label { min-width: 80px; max-width: 120px; }
        }
    </style>
</head>
<body>
<div class="dgptm-public-results">
    <h1><?php echo esc_html($survey->title); ?></h1>
    <?php if ($survey->description) : ?>
        <p style="color:#666;"><?php echo esc_html($survey->description); ?></p>
    <?php endif; ?>
    <p class="dgptm-result-summary">
        <?php echo esc_html($total_completed); ?> abgeschlossene Teilnahmen
        <?php if ($survey->closed_at) : ?>
            | Geschlossen am <?php echo esc_html(wp_date('d.m.Y', strtotime($survey->closed_at))); ?>
        <?php endif; ?>
    </p>

    <?php if ($total_completed === 0) : ?>
        <p>Noch keine Ergebnisse vorhanden.</p>
    <?php else : ?>
        <?php foreach ($questions as $q) :
            $q_answers = $wpdb->get_results($wpdb->prepare(
                "SELECT a.* FROM {$wpdb->prefix}dgptm_survey_answers a
                 INNER JOIN {$wpdb->prefix}dgptm_survey_responses r ON a.response_id = r.id
                 WHERE a.question_id = %d AND r.survey_id = %d AND r.status = 'completed'",
                $q->id, $survey->id
            ));
            $choices = $q->choices ? json_decode($q->choices, true) : [];
        ?>
            <div class="dgptm-public-question">
                <h3><span class="q-num"><?php echo esc_html($q->sort_order); ?>.</span> <?php echo esc_html($q->question_text); ?>
                    <span style="color:#999;font-size:12px;font-weight:normal;">(<?php echo count($q_answers); ?>)</span>
                </h3>

                <?php if (in_array($q->question_type, ['radio', 'select'], true)) :
                    $counts = [];
                    if (is_array($choices)) {
                        foreach ($choices as $c) { $counts[$c] = 0; }
                    }
                    foreach ($q_answers as $a) {
                        $v = $a->answer_value;
                        if (isset($counts[$v])) { $counts[$v]++; } else { $counts[$v] = 1; }
                    }
                    $max_count = max(array_merge($counts, [1]));
                ?>
                    <div class="dgptm-bar-chart">
                        <?php foreach ($counts as $label => $count) :
                            $pct = $max_count > 0 ? round(($count / $max_count) * 100) : 0;
                            $pct_total = count($q_answers) > 0 ? round(($count / count($q_answers)) * 100) : 0;
                        ?>
                            <div class="dgptm-bar-row">
                                <span class="dgptm-bar-label"><?php echo esc_html($label); ?></span>
                                <div class="dgptm-bar-track"><div class="dgptm-bar-fill" style="width: <?php echo esc_attr($pct); ?>%"></div></div>
                                <span class="dgptm-bar-count"><?php echo esc_html($count); ?> (<?php echo esc_html($pct_total); ?>%)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($q->question_type === 'checkbox') :
                    $counts = [];
                    if (is_array($choices)) {
                        foreach ($choices as $c) { $counts[$c] = 0; }
                    }
                    foreach ($q_answers as $a) {
                        $vals = json_decode($a->answer_value, true);
                        if (is_array($vals)) {
                            foreach ($vals as $v) {
                                if (isset($counts[$v])) { $counts[$v]++; } else { $counts[$v] = 1; }
                            }
                        }
                    }
                    $max_count = max(array_merge($counts, [1]));
                ?>
                    <div class="dgptm-bar-chart">
                        <?php foreach ($counts as $label => $count) :
                            $pct = $max_count > 0 ? round(($count / $max_count) * 100) : 0;
                            $pct_total = count($q_answers) > 0 ? round(($count / count($q_answers)) * 100) : 0;
                        ?>
                            <div class="dgptm-bar-row">
                                <span class="dgptm-bar-label"><?php echo esc_html($label); ?></span>
                                <div class="dgptm-bar-track"><div class="dgptm-bar-fill" style="width: <?php echo esc_attr($pct); ?>%"></div></div>
                                <span class="dgptm-bar-count"><?php echo esc_html($count); ?> (<?php echo esc_html($pct_total); ?>%)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($q->question_type === 'number') :
                    $values = [];
                    foreach ($q_answers as $a) {
                        if (is_numeric($a->answer_value)) { $values[] = floatval($a->answer_value); }
                    }
                    if (!empty($values)) :
                        sort($values);
                        $cnt = count($values);
                        $avg = array_sum($values) / $cnt;
                        $mid = (int) floor($cnt / 2);
                        $median = $cnt % 2 === 0 ? ($values[$mid - 1] + $values[$mid]) / 2 : $values[$mid];
                    ?>
                    <div class="dgptm-number-stats">
                        <div class="dgptm-number-stat"><strong><?php echo esc_html(number_format_i18n(min($values))); ?></strong><span>Min</span></div>
                        <div class="dgptm-number-stat"><strong><?php echo esc_html(number_format_i18n(max($values))); ?></strong><span>Max</span></div>
                        <div class="dgptm-number-stat"><strong><?php echo esc_html(number_format_i18n($avg, 1)); ?></strong><span>Durchschn.</span></div>
                        <div class="dgptm-number-stat"><strong><?php echo esc_html(number_format_i18n($median, 1)); ?></strong><span>Median</span></div>
                    </div>
                    <?php endif;

                elseif ($q->question_type === 'matrix') :
                    $rows = isset($choices['rows']) ? $choices['rows'] : [];
                    $cols = isset($choices['columns']) ? $choices['columns'] : [];
                    $matrix_counts = [];
                    foreach ($rows as $r) {
                        $rk = sanitize_title($r);
                        foreach ($cols as $c) { $matrix_counts[$rk][$c] = 0; }
                    }
                    foreach ($q_answers as $a) {
                        $vals = json_decode($a->answer_value, true);
                        if (is_array($vals)) {
                            foreach ($vals as $rk => $cv) {
                                if (isset($matrix_counts[$rk][$cv])) { $matrix_counts[$rk][$cv]++; }
                            }
                        }
                    }
                    $mx = 1;
                    foreach ($matrix_counts as $rc) { foreach ($rc as $c) { if ($c > $mx) $mx = $c; } }
                ?>
                    <div class="dgptm-matrix-result">
                        <table>
                            <thead><tr><th></th>
                                <?php foreach ($cols as $c) : ?><th><?php echo esc_html($c); ?></th><?php endforeach; ?>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($rows as $r) : $rk = sanitize_title($r); ?>
                                    <tr><th><?php echo esc_html($r); ?></th>
                                        <?php foreach ($cols as $c) :
                                            $v = isset($matrix_counts[$rk][$c]) ? $matrix_counts[$rk][$c] : 0;
                                            $bg = 'rgba(34,113,177,' . ($mx > 0 ? round(($v / $mx) * 0.4, 2) : 0) . ')';
                                        ?>
                                            <td style="background: <?php echo $bg; ?>"><?php echo esc_html($v); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif (in_array($q->question_type, ['text', 'textarea'], true)) : ?>
                    <p style="color:#999;font-size:13px;"><?php echo count($q_answers); ?> Freitext-Antworten (nicht oeffentlich einsehbar).</p>

                <?php elseif ($q->question_type === 'file') : ?>
                    <p style="color:#999;font-size:13px;">Datei-Uploads (nicht oeffentlich einsehbar).</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="dgptm-footer">
        <?php echo esc_html($survey->title); ?> | <?php echo esc_html(get_bloginfo('name')); ?>
    </div>
</div>
<?php wp_footer(); ?>
</body>
</html>
