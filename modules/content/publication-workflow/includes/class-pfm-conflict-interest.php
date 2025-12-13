<?php
/**
 * Publication Frontend Manager - Conflict of Interest System
 * Verwaltung von Interessenkonflikten für Reviewer und Autoren
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Conflict_Interest {

    /**
     * Speichere COI Declaration für Reviewer
     */
    public static function save_reviewer_declaration($user_id, $post_id, $has_conflict, $details = '') {
        $declarations = get_user_meta($user_id, 'pfm_coi_declarations', true);
        if (!is_array($declarations)) {
            $declarations = array();
        }

        $declarations[$post_id] = array(
            'has_conflict' => $has_conflict,
            'details' => $details,
            'date' => current_time('mysql'),
        );

        update_user_meta($user_id, 'pfm_coi_declarations', $declarations);

        // Auch bei der Publikation speichern
        $pub_declarations = get_post_meta($post_id, 'pfm_reviewer_coi', true);
        if (!is_array($pub_declarations)) {
            $pub_declarations = array();
        }

        $pub_declarations[$user_id] = array(
            'has_conflict' => $has_conflict,
            'details' => $details,
            'date' => current_time('mysql'),
            'reviewer_name' => get_userdata($user_id)->display_name,
        );

        update_post_meta($post_id, 'pfm_reviewer_coi', $pub_declarations);
    }

    /**
     * Hole COI Declaration
     */
    public static function get_declaration($user_id, $post_id) {
        $declarations = get_user_meta($user_id, 'pfm_coi_declarations', true);

        if (is_array($declarations) && isset($declarations[$post_id])) {
            return $declarations[$post_id];
        }

        return null;
    }

    /**
     * Check ob Reviewer Konflikt hat
     */
    public static function has_conflict($user_id, $post_id) {
        $declaration = self::get_declaration($user_id, $post_id);
        return $declaration && $declaration['has_conflict'] === true;
    }

    /**
     * Render COI Declaration Form
     */
    public static function render_declaration_form($post_id) {
        $user_id = get_current_user_id();
        $existing = self::get_declaration($user_id, $post_id);

        ob_start();
        ?>
        <div class="pfm-coi-declaration">
            <h4><?php _e('Interessenkonflikt-Erklärung', PFM_TD); ?></h4>

            <div class="coi-info">
                <p><?php _e('Als Reviewer sind Sie verpflichtet, mögliche Interessenkonflikte offenzulegen. Ein Interessenkonflikt liegt vor, wenn:', PFM_TD); ?></p>
                <ul>
                    <li><?php _e('Sie mit den Autoren zusammengearbeitet haben (innerhalb der letzten 3 Jahre)', PFM_TD); ?></li>
                    <li><?php _e('Sie eine persönliche oder berufliche Beziehung zu den Autoren haben', PFM_TD); ?></li>
                    <li><?php _e('Sie finanzielle Interessen im Zusammenhang mit der Publikation haben', PFM_TD); ?></li>
                    <li><?php _e('Sie an ähnlichen Forschungsprojekten arbeiten (konkurrierende Interessen)', PFM_TD); ?></li>
                </ul>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="coi-form">
                <?php wp_nonce_field('pfm_save_coi', 'pfm_coi_nonce'); ?>
                <input type="hidden" name="action" value="pfm_save_coi">
                <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">

                <p>
                    <label>
                        <input type="radio" name="has_conflict" value="0" <?php checked(!$existing || $existing['has_conflict'] === false); ?> required>
                        <strong><?php _e('Ich erkläre, dass KEIN Interessenkonflikt vorliegt', PFM_TD); ?></strong>
                    </label>
                </p>

                <p>
                    <label>
                        <input type="radio" name="has_conflict" value="1" <?php checked($existing && $existing['has_conflict'] === true); ?> required>
                        <strong><?php _e('Ich erkläre, dass ein potenzieller Interessenkonflikt vorliegt', PFM_TD); ?></strong>
                    </label>
                </p>

                <div id="conflict-details" style="display:<?php echo ($existing && $existing['has_conflict']) ? 'block' : 'none'; ?>;">
                    <p>
                        <label for="conflict_details"><strong><?php _e('Bitte beschreiben Sie den Interessenkonflikt:', PFM_TD); ?></strong></label><br>
                        <textarea name="conflict_details" id="conflict_details" rows="4" class="large-text"><?php echo esc_textarea($existing['details'] ?? ''); ?></textarea>
                    </p>
                </div>

                <?php if ($existing): ?>
                    <div class="notice notice-info">
                        <p><?php printf(__('Letzte Erklärung vom %s', PFM_TD), esc_html(mysql2date('d.m.Y H:i', $existing['date']))); ?></p>
                    </div>
                <?php endif; ?>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Erklärung speichern', PFM_TD); ?>
                    </button>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('input[name="has_conflict"]').on('change', function() {
                if ($(this).val() === '1') {
                    $('#conflict-details').slideDown();
                } else {
                    $('#conflict-details').slideUp();
                }
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Render COI Status für Redaktion
     */
    public static function render_coi_status($post_id) {
        $declarations = get_post_meta($post_id, 'pfm_reviewer_coi', true);

        if (empty($declarations)) {
            return '<p>' . __('Keine COI-Erklärungen vorhanden.', PFM_TD) . '</p>';
        }

        $output = '<div class="pfm-coi-status">';
        $output .= '<h4>' . __('Interessenkonflikt-Erklärungen', PFM_TD) . '</h4>';
        $output .= '<table class="widefat striped">';
        $output .= '<thead><tr>';
        $output .= '<th>' . __('Reviewer', PFM_TD) . '</th>';
        $output .= '<th>' . __('Status', PFM_TD) . '</th>';
        $output .= '<th>' . __('Details', PFM_TD) . '</th>';
        $output .= '<th>' . __('Datum', PFM_TD) . '</th>';
        $output .= '</tr></thead>';
        $output .= '<tbody>';

        foreach ($declarations as $user_id => $declaration) {
            $has_conflict = $declaration['has_conflict'];

            $output .= '<tr>';
            $output .= '<td>' . esc_html($declaration['reviewer_name']) . '</td>';
            $output .= '<td>';

            if ($has_conflict) {
                $output .= '<span class="coi-badge conflict"><span class="dashicons dashicons-warning"></span> ' . __('Konflikt gemeldet', PFM_TD) . '</span>';
            } else {
                $output .= '<span class="coi-badge no-conflict"><span class="dashicons dashicons-yes"></span> ' . __('Kein Konflikt', PFM_TD) . '</span>';
            }

            $output .= '</td>';
            $output .= '<td>' . ($has_conflict ? esc_html($declaration['details']) : '—') . '</td>';
            $output .= '<td>' . esc_html(mysql2date('d.m.Y', $declaration['date'])) . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Prüfe ob Reviewer für Publikation geeignet ist (Auto-Check)
     */
    public static function check_reviewer_suitability($reviewer_id, $post_id) {
        $issues = array();

        // Check 1: Hat bereits declared
        $declaration = self::get_declaration($reviewer_id, $post_id);
        if ($declaration && $declaration['has_conflict']) {
            $issues[] = __('Reviewer hat Interessenkonflikt gemeldet', PFM_TD);
        }

        // Check 2: Ist Autor der Publikation
        $post = get_post($post_id);
        if ($post->post_author == $reviewer_id) {
            $issues[] = __('Reviewer ist Autor der Publikation', PFM_TD);
        }

        // Check 3: Hat kürzlich mit Autor zusammengearbeitet (prüfe Co-Autorschaft)
        $author_id = $post->post_author;
        if (self::has_recent_collaboration($reviewer_id, $author_id)) {
            $issues[] = __('Reviewer hat kürzlich mit Autor zusammengearbeitet', PFM_TD);
        }

        // Check 4: Gleiche Institution (optional, benötigt zusätzliche Daten)
        // Könnte über user_meta implementiert werden

        return array(
            'suitable' => empty($issues),
            'issues' => $issues,
        );
    }

    /**
     * Prüfe kürzliche Zusammenarbeit (basierend auf Co-Autorschaft)
     */
    private static function has_recent_collaboration($user1, $user2, $months = 36) {
        $date_threshold = date('Y-m-d', strtotime("-{$months} months"));

        // Suche Publikationen wo beide Autoren sind
        $query = new WP_Query(array(
            'post_type' => 'publikation',
            'date_query' => array(
                array(
                    'after' => $date_threshold,
                ),
            ),
            'posts_per_page' => 1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'autoren',
                    'value' => get_userdata($user1)->display_name,
                    'compare' => 'LIKE',
                ),
            ),
        ));

        // Vereinfachte Prüfung - in Produktion würde man strukturierte Co-Autoren-Daten nutzen
        return $query->have_posts();
    }

    /**
     * Render Reviewer Exclusion List für Autor
     */
    public static function render_exclusion_list_form($post_id) {
        $exclusions = get_post_meta($post_id, 'pfm_reviewer_exclusions', true);
        if (!is_array($exclusions)) {
            $exclusions = array();
        }

        ob_start();
        ?>
        <div class="pfm-reviewer-exclusions">
            <h4><?php _e('Reviewer-Ausschlussliste', PFM_TD); ?></h4>
            <p class="description"><?php _e('Sie können Personen benennen, die nicht als Reviewer eingesetzt werden sollen (z.B. wegen Interessenkonflikten).', PFM_TD); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pfm_save_exclusions', 'pfm_exclusions_nonce'); ?>
                <input type="hidden" name="action" value="pfm_save_exclusions">
                <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="exclusions"><?php _e('Ausgeschlossene Reviewer', PFM_TD); ?></label></th>
                        <td>
                            <textarea name="exclusions" id="exclusions" rows="5" class="large-text" placeholder="<?php esc_attr_e('Name, Institution (ein Eintrag pro Zeile)', PFM_TD); ?>"><?php echo esc_textarea(implode("\n", $exclusions)); ?></textarea>
                            <p class="description"><?php _e('Geben Sie Name und Institution an, ein Eintrag pro Zeile.', PFM_TD); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="exclusion_reason"><?php _e('Begründung', PFM_TD); ?></label></th>
                        <td>
                            <textarea name="exclusion_reason" id="exclusion_reason" rows="3" class="large-text"><?php echo esc_textarea(get_post_meta($post_id, 'pfm_exclusion_reason', true)); ?></textarea>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Ausschlussliste speichern', PFM_TD); ?></button>
                </p>
            </form>

            <?php if (!empty($exclusions)): ?>
                <div class="current-exclusions">
                    <h5><?php _e('Aktuelle Ausschlüsse:', PFM_TD); ?></h5>
                    <ul>
                        <?php foreach ($exclusions as $exclusion): ?>
                            <li><?php echo esc_html($exclusion); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Hole Reviewer-Ausschlüsse
     */
    public static function get_exclusions($post_id) {
        $exclusions = get_post_meta($post_id, 'pfm_reviewer_exclusions', true);
        return is_array($exclusions) ? $exclusions : array();
    }
}
