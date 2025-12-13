<?php
/**
 * Publication Frontend Manager - Analytics & Reporting
 * Statistiken und Berichte für das Publikationssystem
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Analytics {

    /**
     * Hole Submission-Statistiken
     */
    public static function get_submission_stats($date_from = null, $date_to = null) {
        $args = array(
            'post_type' => 'publikation',
            'posts_per_page' => -1,
            'post_status' => 'any',
        );

        if ($date_from || $date_to) {
            $args['date_query'] = array();
            if ($date_from) {
                $args['date_query']['after'] = $date_from;
            }
            if ($date_to) {
                $args['date_query']['before'] = $date_to;
            }
        }

        $query = new WP_Query($args);
        $posts = $query->posts;

        $stats = array(
            'total_submissions' => count($posts),
            'by_status' => array(),
            'by_month' => array(),
            'by_author' => array(),
            'average_time_to_decision' => 0,
        );

        foreach ($posts as $post) {
            $status = get_post_meta($post->ID, 'review_status', true) ?: 'submitted';

            // Count by status
            if (!isset($stats['by_status'][$status])) {
                $stats['by_status'][$status] = 0;
            }
            $stats['by_status'][$status]++;

            // Count by month
            $month = date('Y-m', strtotime($post->post_date));
            if (!isset($stats['by_month'][$month])) {
                $stats['by_month'][$month] = 0;
            }
            $stats['by_month'][$month]++;

            // Count by author
            $author_id = $post->post_author;
            if (!isset($stats['by_author'][$author_id])) {
                $stats['by_author'][$author_id] = array(
                    'name' => get_the_author_meta('display_name', $author_id),
                    'count' => 0,
                );
            }
            $stats['by_author'][$author_id]['count']++;
        }

        return $stats;
    }

    /**
     * Hole Review-Performance-Statistiken
     */
    public static function get_review_performance($date_from = null, $date_to = null) {
        $reviews = get_comments(array(
            'type' => 'pfm_review',
            'status' => 'approve',
            'date_query' => array(
                'after' => $date_from ?: '2020-01-01',
                'before' => $date_to ?: date('Y-m-d'),
            ),
        ));

        $stats = array(
            'total_reviews' => count($reviews),
            'by_reviewer' => array(),
            'by_recommendation' => array(
                'accept' => 0,
                'minor' => 0,
                'major' => 0,
                'reject' => 0,
            ),
            'average_score' => 0,
            'average_time' => 0,
        );

        $total_score = 0;
        $score_count = 0;
        $total_time = 0;
        $time_count = 0;

        foreach ($reviews as $review) {
            // By reviewer
            $reviewer_id = $review->user_id;
            if ($reviewer_id) {
                if (!isset($stats['by_reviewer'][$reviewer_id])) {
                    $stats['by_reviewer'][$reviewer_id] = array(
                        'name' => $review->comment_author,
                        'count' => 0,
                        'avg_score' => 0,
                    );
                }
                $stats['by_reviewer'][$reviewer_id]['count']++;
            }

            // By recommendation
            $rec = get_comment_meta($review->comment_ID, 'pfm_recommendation', true);
            if (isset($stats['by_recommendation'][$rec])) {
                $stats['by_recommendation'][$rec]++;
            }

            // Average score
            $score = get_comment_meta($review->comment_ID, 'pfm_review_weighted_score', true);
            if ($score) {
                $total_score += floatval($score);
                $score_count++;

                if ($reviewer_id && isset($stats['by_reviewer'][$reviewer_id])) {
                    $stats['by_reviewer'][$reviewer_id]['scores'][] = floatval($score);
                }
            }

            // Time to review
            $post_id = $review->comment_post_ID;
            $assigned_date = get_post_meta($post_id, 'pfm_review_assigned_date', true);
            if ($assigned_date) {
                $days = round((strtotime($review->comment_date) - strtotime($assigned_date)) / DAY_IN_SECONDS);
                $total_time += $days;
                $time_count++;
            }
        }

        $stats['average_score'] = $score_count > 0 ? round($total_score / $score_count, 2) : 0;
        $stats['average_time'] = $time_count > 0 ? round($total_time / $time_count, 1) : 0;

        // Calculate reviewer averages
        foreach ($stats['by_reviewer'] as $reviewer_id => &$data) {
            if (!empty($data['scores'])) {
                $data['avg_score'] = round(array_sum($data['scores']) / count($data['scores']), 2);
            }
        }

        return $stats;
    }

    /**
     * Hole Acceptance Rate
     */
    public static function get_acceptance_rate($date_from = null, $date_to = null) {
        $stats = self::get_submission_stats($date_from, $date_to);

        $total = $stats['total_submissions'];
        $accepted = isset($stats['by_status']['accepted']) ? $stats['by_status']['accepted'] : 0;
        $published = isset($stats['by_status']['published']) ? $stats['by_status']['published'] : 0;
        $rejected = isset($stats['by_status']['rejected']) ? $stats['by_status']['rejected'] : 0;

        $completed = $accepted + $published + $rejected;

        return array(
            'total' => $total,
            'completed' => $completed,
            'accepted' => $accepted + $published,
            'rejected' => $rejected,
            'acceptance_rate' => $completed > 0 ? round((($accepted + $published) / $completed) * 100, 1) : 0,
            'rejection_rate' => $completed > 0 ? round(($rejected / $completed) * 100, 1) : 0,
        );
    }

    /**
     * Hole Time-to-Decision Statistiken
     */
    public static function get_time_to_decision_stats() {
        $decisions = array();

        $query = new WP_Query(array(
            'post_type' => 'publikation',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'pfm_editorial_decisions',
                    'compare' => 'EXISTS',
                ),
            ),
        ));

        foreach ($query->posts as $post) {
            $submission_date = strtotime($post->post_date);
            $decision_data = get_post_meta($post->ID, 'pfm_editorial_decisions', true);

            if (is_array($decision_data) && !empty($decision_data)) {
                $first_decision = $decision_data[0];
                $decision_date = strtotime($first_decision['timestamp']);
                $days = round(($decision_date - $submission_date) / DAY_IN_SECONDS);

                $decisions[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'days' => $days,
                    'decision' => $first_decision['decision'],
                );
            }
        }

        $times = array_column($decisions, 'days');

        return array(
            'decisions' => $decisions,
            'count' => count($decisions),
            'average' => count($times) > 0 ? round(array_sum($times) / count($times), 1) : 0,
            'median' => count($times) > 0 ? self::calculate_median($times) : 0,
            'min' => count($times) > 0 ? min($times) : 0,
            'max' => count($times) > 0 ? max($times) : 0,
        );
    }

    /**
     * Berechne Median
     */
    private static function calculate_median($arr) {
        sort($arr);
        $count = count($arr);
        $middle = floor(($count - 1) / 2);

        if ($count % 2) {
            return $arr[$middle];
        } else {
            return ($arr[$middle] + $arr[$middle + 1]) / 2;
        }
    }

    /**
     * Render Analytics Dashboard
     */
    public static function render_dashboard() {
        if (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
            return '<p>' . __('Keine Berechtigung.', PFM_TD) . '</p>';
        }

        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-12 months'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

        $submission_stats = self::get_submission_stats($date_from, $date_to);
        $review_stats = self::get_review_performance($date_from, $date_to);
        $acceptance = self::get_acceptance_rate($date_from, $date_to);
        $time_to_decision = self::get_time_to_decision_stats();

        ob_start();
        ?>
        <div class="pfm-analytics-dashboard">
            <h2><?php _e('Publikations-Statistiken', PFM_TD); ?></h2>

            <!-- Filter -->
            <div class="analytics-filters">
                <form method="get" style="margin-bottom: 20px;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
                    <label><?php _e('Von:', PFM_TD); ?> <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>"></label>
                    <label><?php _e('Bis:', PFM_TD); ?> <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>"></label>
                    <button type="submit" class="button"><?php _e('Filter anwenden', PFM_TD); ?></button>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="pfm-metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon"><span class="dashicons dashicons-media-document"></span></div>
                    <div class="metric-content">
                        <h3><?php echo esc_html($submission_stats['total_submissions']); ?></h3>
                        <p><?php _e('Einreichungen', PFM_TD); ?></p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon"><span class="dashicons dashicons-visibility"></span></div>
                    <div class="metric-content">
                        <h3><?php echo esc_html($review_stats['total_reviews']); ?></h3>
                        <p><?php _e('Reviews', PFM_TD); ?></p>
                    </div>
                </div>

                <div class="metric-card success">
                    <div class="metric-icon"><span class="dashicons dashicons-yes"></span></div>
                    <div class="metric-content">
                        <h3><?php echo esc_html($acceptance['acceptance_rate']); ?>%</h3>
                        <p><?php _e('Akzeptanzrate', PFM_TD); ?></p>
                    </div>
                </div>

                <div class="metric-card info">
                    <div class="metric-icon"><span class="dashicons dashicons-clock"></span></div>
                    <div class="metric-content">
                        <h3><?php echo esc_html($time_to_decision['average']); ?></h3>
                        <p><?php _e('Tage bis Entscheidung', PFM_TD); ?></p>
                    </div>
                </div>
            </div>

            <!-- Status Distribution -->
            <div class="analytics-section">
                <h3><?php _e('Verteilung nach Status', PFM_TD); ?></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Status', PFM_TD); ?></th>
                            <th><?php _e('Anzahl', PFM_TD); ?></th>
                            <th><?php _e('Anteil', PFM_TD); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submission_stats['by_status'] as $status => $count): ?>
                            <tr>
                                <td><?php echo PFM_Workflow_Tracker::render_status_badge($status); ?></td>
                                <td><?php echo esc_html($count); ?></td>
                                <td><?php echo esc_html(round(($count / $submission_stats['total_submissions']) * 100, 1)); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Review Performance -->
            <div class="analytics-section">
                <h3><?php _e('Review-Performance', PFM_TD); ?></h3>
                <div class="performance-stats">
                    <p><strong><?php _e('Durchschnittliche Bewertung:', PFM_TD); ?></strong> <?php echo esc_html($review_stats['average_score']); ?> / 5.0</p>
                    <p><strong><?php _e('Durchschnittliche Review-Zeit:', PFM_TD); ?></strong> <?php echo esc_html($review_stats['average_time']); ?> <?php _e('Tage', PFM_TD); ?></p>
                </div>

                <h4><?php _e('Reviewer-Aktivität', PFM_TD); ?></h4>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Reviewer', PFM_TD); ?></th>
                            <th><?php _e('Anzahl Reviews', PFM_TD); ?></th>
                            <th><?php _e('Durchschnittsscore', PFM_TD); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        uasort($review_stats['by_reviewer'], function($a, $b) {
                            return $b['count'] - $a['count'];
                        });
                        foreach (array_slice($review_stats['by_reviewer'], 0, 10) as $reviewer):
                        ?>
                            <tr>
                                <td><?php echo esc_html($reviewer['name']); ?></td>
                                <td><?php echo esc_html($reviewer['count']); ?></td>
                                <td><?php echo esc_html($reviewer['avg_score']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Time to Decision -->
            <div class="analytics-section">
                <h3><?php _e('Zeit bis zur Entscheidung', PFM_TD); ?></h3>
                <div class="time-stats">
                    <p><strong><?php _e('Durchschnitt:', PFM_TD); ?></strong> <?php echo esc_html($time_to_decision['average']); ?> <?php _e('Tage', PFM_TD); ?></p>
                    <p><strong><?php _e('Median:', PFM_TD); ?></strong> <?php echo esc_html($time_to_decision['median']); ?> <?php _e('Tage', PFM_TD); ?></p>
                    <p><strong><?php _e('Min - Max:', PFM_TD); ?></strong> <?php echo esc_html($time_to_decision['min']); ?> - <?php echo esc_html($time_to_decision['max']); ?> <?php _e('Tage', PFM_TD); ?></p>
                </div>
            </div>

            <!-- Monthly Submissions Chart -->
            <div class="analytics-section">
                <h3><?php _e('Einreichungen pro Monat', PFM_TD); ?></h3>
                <div class="chart-container">
                    <?php self::render_monthly_chart($submission_stats['by_month']); ?>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render einfaches Bar-Chart
     */
    private static function render_monthly_chart($data) {
        if (empty($data)) {
            echo '<p>' . __('Keine Daten verfügbar.', PFM_TD) . '</p>';
            return;
        }

        ksort($data);
        $max = max($data);

        echo '<div class="pfm-bar-chart">';
        foreach ($data as $month => $count) {
            $percentage = $max > 0 ? ($count / $max) * 100 : 0;
            echo '<div class="chart-bar">';
            echo '<div class="bar-label">' . esc_html(date('M Y', strtotime($month . '-01'))) . '</div>';
            echo '<div class="bar-container">';
            echo '<div class="bar-fill" style="width:' . esc_attr($percentage) . '%"></div>';
            echo '<span class="bar-value">' . esc_html($count) . '</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Export Daten als CSV
     */
    public static function export_to_csv($type = 'submissions') {
        if (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
            wp_die(__('Keine Berechtigung.', PFM_TD));
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=pfm_export_' . $type . '_' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        if ($type === 'submissions') {
            fputcsv($output, array('ID', 'Titel', 'Autor', 'Status', 'DOI', 'Eingereicht am', 'Letzte Änderung'));

            $query = new WP_Query(array(
                'post_type' => 'publikation',
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
            ));

            foreach ($query->posts as $post) {
                fputcsv($output, array(
                    $post->ID,
                    $post->post_title,
                    get_the_author_meta('display_name', $post->post_author),
                    get_post_meta($post->ID, 'review_status', true),
                    get_post_meta($post->ID, 'doi', true),
                    get_the_date('Y-m-d', $post),
                    get_the_modified_date('Y-m-d', $post),
                ));
            }
        }

        fclose($output);
        exit;
    }
}
