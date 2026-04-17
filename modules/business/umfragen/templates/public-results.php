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
    "SELECT * FROM {$wpdb->prefix}dgptm_survey_questions
     WHERE survey_id = %d AND is_privacy_sensitive = 0
     ORDER BY sort_order ASC",
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
        ?>
            <div class="dgptm-public-question">
                <h3><span class="q-num"><?php echo esc_html($q->sort_order); ?>.</span> <?php echo esc_html($q->question_text); ?>
                    <span style="color:#999;font-size:12px;font-weight:normal;">(<?php echo count($q_answers); ?>)</span>
                </h3>

                <?php DGPTM_Survey_Admin::render_question_result($q, $q_answers); ?>
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
