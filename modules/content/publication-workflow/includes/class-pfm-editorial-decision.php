<?php
/**
 * Publication Frontend Manager - Editorial Decision Interface
 * Interface für redaktionelle Entscheidungen mit Zusammenfassung aller Reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Editorial_Decision {

    /**
     * Render Editorial Decision Dashboard
     */
    public static function render_decision_interface($post_id) {
        if (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
            return '<p>' . __('Keine Berechtigung.', PFM_TD) . '</p>';
        }

        $post = get_post($post_id);
        $current_status = get_post_meta($post_id, 'review_status', true);
        $reviews = self::get_all_reviews($post_id);
        $aggregated_scores = PFM_Review_Criteria::get_aggregated_scores($post_id);

        ob_start();
        ?>
        <div class="pfm-editorial-decision">
            <h3><?php _e('Redaktionelle Entscheidung', PFM_TD); ?></h3>

            <?php if (empty($reviews)): ?>
                <div class="notice notice-warning">
                    <p><?php _e('Noch keine Reviews vorhanden. Bitte weisen Sie zunächst Reviewer zu.', PFM_TD); ?></p>
                </div>
            <?php else: ?>

                <!-- Review-Zusammenfassung -->
                <div class="review-summary">
                    <h4><?php _e('Review-Übersicht', PFM_TD); ?></h4>

                    <?php if ($aggregated_scores): ?>
                        <div class="aggregated-scores">
                            <?php echo PFM_Review_Criteria::render_aggregated_scores($post_id); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Empfehlungen der Reviewer -->
                    <div class="reviewer-recommendations">
                        <h5><?php _e('Reviewer-Empfehlungen', PFM_TD); ?></h5>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Reviewer', PFM_TD); ?></th>
                                    <th><?php _e('Empfehlung', PFM_TD); ?></th>
                                    <th><?php _e('Score', PFM_TD); ?></th>
                                    <th><?php _e('Datum', PFM_TD); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                    <?php
                                    $rec = get_comment_meta($review->comment_ID, 'pfm_recommendation', true);
                                    $score = get_comment_meta($review->comment_ID, 'pfm_review_weighted_score', true);
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($review->comment_author); ?></td>
                                        <td><?php echo self::render_recommendation_badge($rec); ?></td>
                                        <td><?php echo $score ? esc_html($score) . ' / 5' : '—'; ?></td>
                                        <td><?php echo esc_html(mysql2date('d.m.Y', $review->comment_date)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Detaillierte Reviews -->
                    <div class="detailed-reviews">
                        <h5><?php _e('Detaillierte Reviews', PFM_TD); ?></h5>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-detail">
                                <h6><?php echo esc_html($review->comment_author); ?> - <?php echo esc_html(mysql2date('d.m.Y H:i', $review->comment_date)); ?></h6>

                                <?php
                                $rec = get_comment_meta($review->comment_ID, 'pfm_recommendation', true);
                                $to_author = get_comment_meta($review->comment_ID, 'pfm_comments_to_author', true);
                                $confidential = get_comment_meta($review->comment_ID, 'pfm_confidential_to_editor', true);
                                ?>

                                <p><strong><?php _e('Empfehlung:', PFM_TD); ?></strong> <?php echo self::render_recommendation_badge($rec); ?></p>

                                <?php echo PFM_Review_Criteria::render_scores_display($review->comment_ID); ?>

                                <?php if ($to_author): ?>
                                    <div class="review-comments">
                                        <strong><?php _e('Kommentare für Autor:', PFM_TD); ?></strong>
                                        <div class="comment-text"><?php echo wp_kses_post(nl2br($to_author)); ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($confidential): ?>
                                    <div class="review-confidential">
                                        <strong><?php _e('Vertrauliche Kommentare (nur für Redaktion):', PFM_TD); ?></strong>
                                        <div class="comment-text confidential"><?php echo wp_kses_post(nl2br($confidential)); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Entscheidungsformular -->
                <div class="decision-form">
                    <h4><?php _e('Entscheidung treffen', PFM_TD); ?></h4>

                    <?php
                    $recommendation = self::suggest_decision($reviews, $aggregated_scores);
                    if ($recommendation):
                    ?>
                        <div class="notice notice-info">
                            <p>
                                <strong><?php _e('Vorgeschlagene Entscheidung:', PFM_TD); ?></strong>
                                <?php echo self::render_recommendation_badge($recommendation['decision']); ?>
                                <br>
                                <small><?php echo esc_html($recommendation['reason']); ?></small>
                            </p>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pfm-decision-form">
                        <?php wp_nonce_field('pfm_make_decision', 'pfm_decision_nonce'); ?>
                        <input type="hidden" name="action" value="pfm_make_decision">
                        <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">

                        <table class="form-table">
                            <tr>
                                <th><label for="decision"><?php _e('Entscheidung', PFM_TD); ?></label></th>
                                <td>
                                    <select name="decision" id="decision" required>
                                        <option value=""><?php _e('-- Bitte wählen --', PFM_TD); ?></option>
                                        <option value="accepted"><?php _e('Akzeptiert', PFM_TD); ?></option>
                                        <option value="revision_needed"><?php _e('Revision erforderlich', PFM_TD); ?></option>
                                        <option value="rejected"><?php _e('Abgelehnt', PFM_TD); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="decision_comments"><?php _e('Feedback an Autor', PFM_TD); ?></label></th>
                                <td>
                                    <textarea name="decision_comments" id="decision_comments" rows="8" class="large-text" required></textarea>
                                    <p class="description"><?php _e('Dieser Text wird dem Autor per E-Mail zugesendet.', PFM_TD); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="send_email"><?php _e('E-Mail senden', PFM_TD); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="send_email" id="send_email" value="1" checked>
                                        <?php _e('Automatische E-Mail-Benachrichtigung an Autor', PFM_TD); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary button-large">
                                <?php _e('Entscheidung treffen und speichern', PFM_TD); ?>
                            </button>
                        </p>
                    </form>

                    <!-- Quick Decision Buttons -->
                    <div class="quick-decisions">
                        <h5><?php _e('Schnellentscheidungen', PFM_TD); ?></h5>
                        <p class="description"><?php _e('Verwenden Sie vordefinierte Entscheidungsvorlagen', PFM_TD); ?></p>
                        <div class="quick-decision-buttons">
                            <button type="button" class="button quick-accept" data-decision="accepted">
                                <span class="dashicons dashicons-yes"></span> <?php _e('Schnell-Akzeptanz', PFM_TD); ?>
                            </button>
                            <button type="button" class="button quick-revision" data-decision="revision_needed">
                                <span class="dashicons dashicons-edit"></span> <?php _e('Minor Revision', PFM_TD); ?>
                            </button>
                            <button type="button" class="button quick-major-revision" data-decision="revision_needed">
                                <span class="dashicons dashicons-warning"></span> <?php _e('Major Revision', PFM_TD); ?>
                            </button>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Quick Decision Templates
            var templates = {
                'accepted': '<?php echo esc_js(__("Sehr geehrte/r Autor/in,\n\nwir freuen uns, Ihnen mitteilen zu können, dass Ihre Publikation zur Veröffentlichung akzeptiert wurde.\n\nDie Reviews waren überwiegend positiv und wir sehen keinen weiteren Änderungsbedarf.\n\nMit freundlichen Grüßen", PFM_TD)); ?>',
                'revision_needed': '<?php echo esc_js(__("Sehr geehrte/r Autor/in,\n\nvielen Dank für Ihre Einreichung. Nach sorgfältiger Prüfung durch unsere Reviewer benötigt Ihre Publikation Überarbeitungen.\n\nBitte beachten Sie die Kommentare der Reviewer und reichen Sie eine überarbeitete Version ein.\n\nMit freundlichen Grüßen", PFM_TD)); ?>'
            };

            $('.quick-decision-buttons button').on('click', function() {
                var decision = $(this).data('decision');
                $('#decision').val(decision);
                if (templates[decision]) {
                    $('#decision_comments').val(templates[decision]);
                }
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Hole alle Reviews für eine Publikation
     */
    private static function get_all_reviews($post_id) {
        return get_comments(array(
            'post_id' => $post_id,
            'type' => 'pfm_review',
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'DESC',
        ));
    }

    /**
     * Render Empfehlungs-Badge
     */
    private static function render_recommendation_badge($recommendation) {
        $badges = array(
            'accept' => '<span class="pfm-rec-badge accept"><span class="dashicons dashicons-yes"></span> ' . __('Accept', PFM_TD) . '</span>',
            'minor' => '<span class="pfm-rec-badge minor"><span class="dashicons dashicons-edit"></span> ' . __('Minor Revision', PFM_TD) . '</span>',
            'major' => '<span class="pfm-rec-badge major"><span class="dashicons dashicons-warning"></span> ' . __('Major Revision', PFM_TD) . '</span>',
            'reject' => '<span class="pfm-rec-badge reject"><span class="dashicons dashicons-no"></span> ' . __('Reject', PFM_TD) . '</span>',
            'accepted' => '<span class="pfm-rec-badge accept"><span class="dashicons dashicons-yes"></span> ' . __('Akzeptiert', PFM_TD) . '</span>',
            'revision_needed' => '<span class="pfm-rec-badge minor"><span class="dashicons dashicons-edit"></span> ' . __('Revision', PFM_TD) . '</span>',
            'rejected' => '<span class="pfm-rec-badge reject"><span class="dashicons dashicons-no"></span> ' . __('Abgelehnt', PFM_TD) . '</span>',
        );

        return isset($badges[$recommendation]) ? $badges[$recommendation] : esc_html($recommendation);
    }

    /**
     * Schlage Entscheidung basierend auf Reviews vor
     */
    private static function suggest_decision($reviews, $aggregated_scores) {
        if (empty($reviews)) {
            return null;
        }

        $recommendations = array();
        foreach ($reviews as $review) {
            $rec = get_comment_meta($review->comment_ID, 'pfm_recommendation', true);
            $recommendations[] = $rec;
        }

        $accept_count = count(array_filter($recommendations, function($r) { return $r === 'accept'; }));
        $minor_count = count(array_filter($recommendations, function($r) { return $r === 'minor'; }));
        $major_count = count(array_filter($recommendations, function($r) { return $r === 'major'; }));
        $reject_count = count(array_filter($recommendations, function($r) { return $r === 'reject'; }));
        $total = count($recommendations);

        $avg_score = $aggregated_scores ? $aggregated_scores['average'] : 0;

        // Entscheidungslogik
        if ($reject_count > $total / 2) {
            return array(
                'decision' => 'rejected',
                'reason' => sprintf(__('Mehrheit der Reviewer (%d von %d) empfiehlt Ablehnung', PFM_TD), $reject_count, $total),
            );
        }

        if ($accept_count > $total / 2 && $avg_score >= 4.0) {
            return array(
                'decision' => 'accepted',
                'reason' => sprintf(__('Mehrheit der Reviewer (%d von %d) empfiehlt Akzeptanz. Durchschnittsscore: %.2f', PFM_TD), $accept_count, $total, $avg_score),
            );
        }

        if ($major_count > 0 || $avg_score < 3.0) {
            return array(
                'decision' => 'revision_needed',
                'reason' => __('Major Revision empfohlen aufgrund kritischer Anmerkungen oder niedrigem Score', PFM_TD),
            );
        }

        if ($minor_count > 0 || $avg_score < 4.0) {
            return array(
                'decision' => 'revision_needed',
                'reason' => __('Minor Revision empfohlen zur Verbesserung der Publikation', PFM_TD),
            );
        }

        return array(
            'decision' => 'accepted',
            'reason' => __('Positive Reviews und gute Bewertungen', PFM_TD),
        );
    }

    /**
     * Speichere redaktionelle Entscheidung
     */
    public static function save_decision($post_id, $decision, $comments, $send_email = true) {
        $old_status = get_post_meta($post_id, 'review_status', true);

        // Status aktualisieren
        update_post_meta($post_id, 'review_status', $decision);

        // Entscheidung speichern
        $decision_data = array(
            'timestamp' => current_time('mysql'),
            'decision' => $decision,
            'comments' => $comments,
            'editor_id' => get_current_user_id(),
            'editor_name' => wp_get_current_user()->display_name,
        );

        $decisions = get_post_meta($post_id, 'pfm_editorial_decisions', true);
        if (!is_array($decisions)) {
            $decisions = array();
        }
        $decisions[] = $decision_data;
        update_post_meta($post_id, 'pfm_editorial_decisions', $decisions);

        // Status-Historie
        PFM_Workflow_Tracker::log_status_change($post_id, $old_status, $decision);

        // E-Mail senden
        if ($send_email) {
            PFM_Email_Templates::send_decision_email($post_id, $decision, $comments);
        }

        return true;
    }

    /**
     * Hole Editorial Decisions History
     */
    public static function get_decisions_history($post_id) {
        return get_post_meta($post_id, 'pfm_editorial_decisions', true) ?: array();
    }

    /**
     * Render Decisions History
     */
    public static function render_decisions_history($post_id) {
        $decisions = self::get_decisions_history($post_id);

        if (empty($decisions)) {
            return '<p>' . __('Noch keine redaktionellen Entscheidungen getroffen.', PFM_TD) . '</p>';
        }

        $output = '<div class="pfm-decisions-history">';
        $output .= '<h4>' . __('Entscheidungshistorie', PFM_TD) . '</h4>';
        $output .= '<div class="decisions-timeline">';

        foreach (array_reverse($decisions) as $decision) {
            $output .= '<div class="decision-entry">';
            $output .= '<div class="decision-header">';
            $output .= '<strong>' . esc_html(mysql2date('d.m.Y H:i', $decision['timestamp'])) . '</strong>';
            $output .= ' - ' . self::render_recommendation_badge($decision['decision']);
            $output .= '<br><small>' . sprintf(__('von %s', PFM_TD), esc_html($decision['editor_name'])) . '</small>';
            $output .= '</div>';
            $output .= '<div class="decision-comments">' . wp_kses_post(nl2br($decision['comments'])) . '</div>';
            $output .= '</div>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }
}
