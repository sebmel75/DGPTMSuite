<?php
/**
 * Publication Frontend Manager - Review Criteria System
 * Erweiterte Bewertungskriterien für wissenschaftliche Reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Review_Criteria {

    /**
     * Standard-Bewertungskriterien
     */
    public static function get_default_criteria() {
        return array(
            'methodology' => array(
                'label' => __('Methodik & Forschungsdesign', PFM_TD),
                'description' => __('Qualität der wissenschaftlichen Methodik und des Forschungsdesigns', PFM_TD),
                'weight' => 25,
            ),
            'relevance' => array(
                'label' => __('Relevanz & Originalität', PFM_TD),
                'description' => __('Bedeutung und Neuheit der Forschungsergebnisse', PFM_TD),
                'weight' => 20,
            ),
            'clarity' => array(
                'label' => __('Klarheit & Struktur', PFM_TD),
                'description' => __('Verständlichkeit und logischer Aufbau der Arbeit', PFM_TD),
                'weight' => 15,
            ),
            'literature' => array(
                'label' => __('Literatur & Referenzen', PFM_TD),
                'description' => __('Vollständigkeit und Angemessenheit der zitierten Literatur', PFM_TD),
                'weight' => 15,
            ),
            'results' => array(
                'label' => __('Ergebnisse & Diskussion', PFM_TD),
                'description' => __('Qualität der Darstellung und Interpretation der Ergebnisse', PFM_TD),
                'weight' => 15,
            ),
            'presentation' => array(
                'label' => __('Darstellung & Sprache', PFM_TD),
                'description' => __('Sprachliche Qualität und grafische Aufbereitung', PFM_TD),
                'weight' => 10,
            ),
        );
    }

    /**
     * Bewertungsskala (1-5)
     */
    public static function get_rating_scale() {
        return array(
            5 => __('Ausgezeichnet', PFM_TD),
            4 => __('Gut', PFM_TD),
            3 => __('Befriedigend', PFM_TD),
            2 => __('Ausreichend', PFM_TD),
            1 => __('Unzureichend', PFM_TD),
        );
    }

    /**
     * Berechne gewichteten Gesamtscore
     */
    public static function calculate_weighted_score($ratings) {
        $criteria = self::get_default_criteria();
        $total_weight = 0;
        $weighted_sum = 0;

        foreach ($ratings as $key => $rating) {
            if (isset($criteria[$key]) && is_numeric($rating)) {
                $weight = $criteria[$key]['weight'];
                $weighted_sum += $rating * $weight;
                $total_weight += $weight;
            }
        }

        return $total_weight > 0 ? round($weighted_sum / $total_weight, 2) : 0;
    }

    /**
     * Speichere Review mit Kriterien
     */
    public static function save_review_scores($comment_id, $scores) {
        update_comment_meta($comment_id, 'pfm_review_scores', $scores);
        $weighted_score = self::calculate_weighted_score($scores);
        update_comment_meta($comment_id, 'pfm_review_weighted_score', $weighted_score);
    }

    /**
     * Hole Review-Scores
     */
    public static function get_review_scores($comment_id) {
        return get_comment_meta($comment_id, 'pfm_review_scores', true);
    }

    /**
     * Hole aggregierte Scores für eine Publikation
     */
    public static function get_aggregated_scores($post_id) {
        $reviews = get_comments(array(
            'post_id' => $post_id,
            'type' => 'pfm_review',
            'status' => 'approve',
        ));

        if (empty($reviews)) {
            return null;
        }

        $criteria = self::get_default_criteria();
        $aggregated = array();
        $count = 0;

        foreach ($reviews as $review) {
            $scores = self::get_review_scores($review->comment_ID);
            if (!empty($scores)) {
                $count++;
                foreach ($scores as $key => $score) {
                    if (!isset($aggregated[$key])) {
                        $aggregated[$key] = 0;
                    }
                    $aggregated[$key] += floatval($score);
                }
            }
        }

        if ($count > 0) {
            foreach ($aggregated as $key => $sum) {
                $aggregated[$key] = round($sum / $count, 2);
            }
        }

        return array(
            'scores' => $aggregated,
            'count' => $count,
            'average' => $count > 0 ? self::calculate_weighted_score($aggregated) : 0,
        );
    }

    /**
     * Render Bewertungsformular
     */
    public static function render_criteria_form() {
        $criteria = self::get_default_criteria();
        $scale = self::get_rating_scale();

        echo '<div class="pfm-review-criteria">';
        echo '<h4>' . __('Bewertungskriterien', PFM_TD) . '</h4>';
        echo '<p class="description">' . __('Bitte bewerten Sie die Publikation anhand der folgenden Kriterien (1 = Unzureichend, 5 = Ausgezeichnet)', PFM_TD) . '</p>';

        foreach ($criteria as $key => $criterion) {
            echo '<div class="pfm-criterion">';
            echo '<label><strong>' . esc_html($criterion['label']) . '</strong></label>';
            echo '<p class="description" style="margin: 5px 0 10px 0;">' . esc_html($criterion['description']) . '</p>';
            echo '<div class="pfm-rating-buttons" data-criterion="' . esc_attr($key) . '">';

            foreach ($scale as $value => $label) {
                echo '<label class="pfm-rating-option">';
                echo '<input type="radio" name="pfm_scores[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" required>';
                echo '<span class="rating-label">' . esc_html($value) . ' - ' . esc_html($label) . '</span>';
                echo '</label>';
            }

            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render Score-Anzeige
     */
    public static function render_scores_display($comment_id) {
        $scores = self::get_review_scores($comment_id);
        if (empty($scores)) {
            return '';
        }

        $criteria = self::get_default_criteria();
        $weighted_score = get_comment_meta($comment_id, 'pfm_review_weighted_score', true);

        $output = '<div class="pfm-review-scores">';
        $output .= '<h5>' . __('Bewertung', PFM_TD) . '</h5>';
        $output .= '<table class="pfm-scores-table">';

        foreach ($scores as $key => $score) {
            if (isset($criteria[$key])) {
                $output .= '<tr>';
                $output .= '<td>' . esc_html($criteria[$key]['label']) . '</td>';
                $output .= '<td class="score-value">' . esc_html($score) . ' / 5</td>';
                $output .= '<td class="score-bar"><div class="bar" style="width:' . ($score * 20) . '%"></div></td>';
                $output .= '</tr>';
            }
        }

        $output .= '<tr class="total-score">';
        $output .= '<td><strong>' . __('Gesamtbewertung (gewichtet)', PFM_TD) . '</strong></td>';
        $output .= '<td class="score-value"><strong>' . esc_html($weighted_score) . ' / 5</strong></td>';
        $output .= '<td class="score-bar"><div class="bar total" style="width:' . ($weighted_score * 20) . '%"></div></td>';
        $output .= '</tr>';

        $output .= '</table>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render aggregierte Scores
     */
    public static function render_aggregated_scores($post_id) {
        $data = self::get_aggregated_scores($post_id);
        if (!$data) {
            return '<p>' . __('Noch keine Bewertungen vorhanden.', PFM_TD) . '</p>';
        }

        $criteria = self::get_default_criteria();

        $output = '<div class="pfm-aggregated-scores">';
        $output .= '<h4>' . sprintf(__('Durchschnittliche Bewertung (%d Reviews)', PFM_TD), $data['count']) . '</h4>';
        $output .= '<div class="overall-score">';
        $output .= '<span class="score-number">' . esc_html($data['average']) . '</span>';
        $output .= '<span class="score-max">/ 5.0</span>';
        $output .= '</div>';

        $output .= '<table class="pfm-scores-table">';
        foreach ($data['scores'] as $key => $score) {
            if (isset($criteria[$key])) {
                $output .= '<tr>';
                $output .= '<td>' . esc_html($criteria[$key]['label']) . '</td>';
                $output .= '<td class="score-value">' . esc_html($score) . ' / 5</td>';
                $output .= '<td class="score-bar"><div class="bar" style="width:' . ($score * 20) . '%"></div></td>';
                $output .= '</tr>';
            }
        }
        $output .= '</table>';
        $output .= '</div>';

        return $output;
    }
}
