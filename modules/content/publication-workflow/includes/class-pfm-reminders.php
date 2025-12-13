<?php
/**
 * Publication Frontend Manager - Deadline Reminder System
 * Automatische Erinnerungen für Review-Deadlines und andere Fristen
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Reminders {

    /**
     * Initialisiere Reminder-System
     */
    public static function init() {
        // Cron-Job für tägliche Reminder-Prüfung
        if (!wp_next_scheduled('pfm_daily_reminder_check')) {
            wp_schedule_event(time(), 'daily', 'pfm_daily_reminder_check');
        }

        add_action('pfm_daily_reminder_check', array(__CLASS__, 'check_and_send_reminders'));
    }

    /**
     * Prüfe und sende Erinnerungen
     */
    public static function check_and_send_reminders() {
        self::check_review_deadlines();
        self::check_pending_decisions();
        self::check_revision_deadlines();
    }

    /**
     * Prüfe Review-Deadlines
     */
    public static function check_review_deadlines() {
        $query = new WP_Query(array(
            'post_type' => 'publikation',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'review_status',
                    'value' => 'under_review',
                ),
                array(
                    'key' => 'pfm_review_deadline',
                    'value' => '',
                    'compare' => '!=',
                ),
            ),
        ));

        foreach ($query->posts as $post) {
            $deadline = get_post_meta($post->ID, 'pfm_review_deadline', true);
            $assigned_reviewers = get_post_meta($post->ID, 'pfm_assigned_reviewers', true);

            if (!$deadline || !is_array($assigned_reviewers)) {
                continue;
            }

            $deadline_timestamp = strtotime($deadline);
            $today = time();
            $days_until_deadline = round(($deadline_timestamp - $today) / DAY_IN_SECONDS);

            // Hole bereits erledigte Reviews
            $submitted_reviews = get_comments(array(
                'post_id' => $post->ID,
                'type' => 'pfm_review',
                'status' => 'approve',
            ));

            $submitted_reviewer_ids = array();
            foreach ($submitted_reviews as $review) {
                $submitted_reviewer_ids[] = $review->user_id;
            }

            // Finde Reviewer, die noch nicht eingereicht haben
            $pending_reviewers = array_diff($assigned_reviewers, $submitted_reviewer_ids);

            if (empty($pending_reviewers)) {
                continue;
            }

            // Sende Erinnerungen
            if ($days_until_deadline == 3 || $days_until_deadline == 1 || $days_until_deadline == 0) {
                self::send_review_reminder($post->ID, $pending_reviewers, $days_until_deadline, $deadline);
            }

            // Warnmeldung bei überfälliger Deadline
            if ($days_until_deadline < 0) {
                self::send_overdue_notification($post->ID, $pending_reviewers, abs($days_until_deadline));
            }
        }
    }

    /**
     * Sende Review-Erinnerung
     */
    private static function send_review_reminder($post_id, $reviewer_ids, $days_remaining, $deadline) {
        $last_reminder = get_post_meta($post_id, 'pfm_last_reminder_sent', true);

        // Verhindere mehrfache Erinnerungen am selben Tag
        if ($last_reminder && date('Y-m-d', strtotime($last_reminder)) === date('Y-m-d')) {
            return;
        }

        $data = PFM_Email_Templates::get_email_data($post_id, array(
            'review_deadline' => date('d.m.Y', strtotime($deadline)),
            'days_remaining' => $days_remaining,
        ));

        foreach ($reviewer_ids as $reviewer_id) {
            $reviewer = get_userdata($reviewer_id);
            if ($reviewer) {
                $reviewer_data = array_merge($data, array(
                    'reviewer_name' => $reviewer->display_name,
                ));

                PFM_Email_Templates::send_email(
                    $reviewer->user_email,
                    'review_reminder',
                    $reviewer_data
                );
            }
        }

        update_post_meta($post_id, 'pfm_last_reminder_sent', current_time('mysql'));

        // Log Reminder
        self::log_reminder($post_id, 'review_reminder', array(
            'reviewer_ids' => $reviewer_ids,
            'days_remaining' => $days_remaining,
        ));
    }

    /**
     * Sende Überfälligkeitsbenachrichtigung
     */
    private static function send_overdue_notification($post_id, $reviewer_ids, $days_overdue) {
        // Benachrichtige Redaktion
        $editors = pfm_get_editors();

        $data = PFM_Email_Templates::get_email_data($post_id, array(
            'days_overdue' => $days_overdue,
            'pending_reviewers' => implode(', ', array_map(function($id) {
                return get_userdata($id)->display_name;
            }, $reviewer_ids)),
        ));

        foreach ($editors as $editor) {
            $subject = sprintf(__('Überfällige Reviews: %s', PFM_TD), get_the_title($post_id));
            $message = sprintf(
                __("Die Review-Deadline für '%s' ist seit %d Tagen überschritten.\n\nAusstehende Reviewer: %s\n\nBitte prüfen Sie: %s", PFM_TD),
                get_the_title($post_id),
                $days_overdue,
                $data['pending_reviewers'],
                $data['publication_url']
            );

            wp_mail($editor->user_email, $subject, $message);
        }

        self::log_reminder($post_id, 'overdue_notification', array(
            'days_overdue' => $days_overdue,
            'reviewer_ids' => $reviewer_ids,
        ));
    }

    /**
     * Prüfe ausstehende Redaktionsentscheidungen
     */
    public static function check_pending_decisions() {
        $query = new WP_Query(array(
            'post_type' => 'publikation',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'review_status',
                    'value' => 'under_review',
                ),
            ),
        ));

        foreach ($query->posts as $post) {
            // Prüfe ob Reviews vollständig
            $assigned_reviewers = get_post_meta($post->ID, 'pfm_assigned_reviewers', true);

            if (!is_array($assigned_reviewers) || empty($assigned_reviewers)) {
                continue;
            }

            $submitted_reviews = get_comments(array(
                'post_id' => $post->ID,
                'type' => 'pfm_review',
                'status' => 'approve',
            ));

            // Alle Reviews eingereicht?
            if (count($submitted_reviews) >= count($assigned_reviewers)) {
                $last_notification = get_post_meta($post->ID, 'pfm_decision_pending_notification', true);

                // Benachrichtige nur einmal pro Woche
                if (!$last_notification || strtotime($last_notification) < strtotime('-7 days')) {
                    self::send_decision_pending_notification($post->ID);
                    update_post_meta($post->ID, 'pfm_decision_pending_notification', current_time('mysql'));
                }
            }
        }
    }

    /**
     * Sende Benachrichtigung über ausstehende Entscheidung
     */
    private static function send_decision_pending_notification($post_id) {
        $editors = pfm_get_editors();

        $data = PFM_Email_Templates::get_email_data($post_id);

        $subject = sprintf(__('Entscheidung ausstehend: %s', PFM_TD), get_the_title($post_id));
        $message = sprintf(
            __("Alle Reviews für '%s' sind eingegangen. Eine redaktionelle Entscheidung steht noch aus.\n\nBitte treffen Sie eine Entscheidung: %s", PFM_TD),
            get_the_title($post_id),
            $data['publication_url']
        );

        foreach ($editors as $editor) {
            wp_mail($editor->user_email, $subject, $message);
        }

        self::log_reminder($post_id, 'decision_pending', array());
    }

    /**
     * Prüfe Revisions-Deadlines
     */
    public static function check_revision_deadlines() {
        $query = new WP_Query(array(
            'post_type' => 'publikation',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'review_status',
                    'value' => 'revision_needed',
                ),
                array(
                    'key' => 'pfm_revision_deadline',
                    'value' => '',
                    'compare' => '!=',
                ),
            ),
        ));

        foreach ($query->posts as $post) {
            $deadline = get_post_meta($post->ID, 'pfm_revision_deadline', true);

            if (!$deadline) {
                continue;
            }

            $deadline_timestamp = strtotime($deadline);
            $today = time();
            $days_until_deadline = round(($deadline_timestamp - $today) / DAY_IN_SECONDS);

            // Erinnerung 7 Tage vor Ablauf und am Tag selbst
            if ($days_until_deadline == 7 || $days_until_deadline == 1) {
                self::send_revision_reminder($post->ID, $days_until_deadline);
            }
        }
    }

    /**
     * Sende Revisions-Erinnerung
     */
    private static function send_revision_reminder($post_id, $days_remaining) {
        $post = get_post($post_id);
        $author = get_userdata($post->post_author);

        if (!$author) {
            return;
        }

        $data = PFM_Email_Templates::get_email_data($post_id, array(
            'days_remaining' => $days_remaining,
        ));

        $subject = sprintf(__('Erinnerung: Revision-Deadline für %s', PFM_TD), get_the_title($post_id));
        $message = sprintf(
            __("Dies ist eine Erinnerung, dass die Deadline für die Einreichung Ihrer Revision in %d Tag(en) abläuft.\n\nBitte laden Sie Ihre überarbeitete Version hier hoch: %s", PFM_TD),
            $days_remaining,
            $data['publication_url']
        );

        wp_mail($author->user_email, $subject, $message);

        self::log_reminder($post_id, 'revision_reminder', array(
            'days_remaining' => $days_remaining,
        ));
    }

    /**
     * Logge Reminder
     */
    private static function log_reminder($post_id, $type, $details) {
        $log = get_post_meta($post_id, 'pfm_reminder_log', true);

        if (!is_array($log)) {
            $log = array();
        }

        $log[] = array(
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'details' => $details,
        );

        update_post_meta($post_id, 'pfm_reminder_log', $log);
    }

    /**
     * Hole Reminder-Log
     */
    public static function get_reminder_log($post_id) {
        return get_post_meta($post_id, 'pfm_reminder_log', true) ?: array();
    }

    /**
     * Render Reminder-Log für Redaktion
     */
    public static function render_reminder_log($post_id) {
        $log = self::get_reminder_log($post_id);

        if (empty($log)) {
            return '<p>' . __('Keine Erinnerungen gesendet.', PFM_TD) . '</p>';
        }

        $output = '<div class="pfm-reminder-log">';
        $output .= '<h4>' . __('Erinnerungs-Historie', PFM_TD) . '</h4>';
        $output .= '<table class="widefat striped">';
        $output .= '<thead><tr>';
        $output .= '<th>' . __('Datum', PFM_TD) . '</th>';
        $output .= '<th>' . __('Typ', PFM_TD) . '</th>';
        $output .= '<th>' . __('Details', PFM_TD) . '</th>';
        $output .= '</tr></thead>';
        $output .= '<tbody>';

        foreach (array_reverse($log) as $entry) {
            $output .= '<tr>';
            $output .= '<td>' . esc_html(mysql2date('d.m.Y H:i', $entry['timestamp'])) . '</td>';
            $output .= '<td>' . esc_html($entry['type']) . '</td>';
            $output .= '<td>' . esc_html(json_encode($entry['details'])) . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Setze Review-Deadline
     */
    public static function set_review_deadline($post_id, $deadline) {
        update_post_meta($post_id, 'pfm_review_deadline', $deadline);
        update_post_meta($post_id, 'pfm_review_assigned_date', current_time('mysql'));
    }

    /**
     * Setze Revisions-Deadline
     */
    public static function set_revision_deadline($post_id, $deadline) {
        update_post_meta($post_id, 'pfm_revision_deadline', $deadline);
    }

    /**
     * Hole bevorstehende Deadlines (Dashboard-Widget)
     */
    public static function get_upcoming_deadlines($days = 7) {
        $deadlines = array();

        // Review Deadlines
        $query = new WP_Query(array(
            'post_type' => 'publikation',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'pfm_review_deadline',
                    'value' => '',
                    'compare' => '!=',
                ),
            ),
        ));

        foreach ($query->posts as $post) {
            $deadline = get_post_meta($post->ID, 'pfm_review_deadline', true);
            $deadline_timestamp = strtotime($deadline);

            if ($deadline_timestamp > time() && $deadline_timestamp < strtotime("+{$days} days")) {
                $deadlines[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'type' => 'review',
                    'deadline' => $deadline,
                    'days_remaining' => round(($deadline_timestamp - time()) / DAY_IN_SECONDS),
                );
            }
        }

        return $deadlines;
    }

    /**
     * Render Upcoming Deadlines Widget
     */
    public static function render_upcoming_deadlines_widget() {
        $deadlines = self::get_upcoming_deadlines(7);

        if (empty($deadlines)) {
            return '<p>' . __('Keine bevorstehenden Deadlines.', PFM_TD) . '</p>';
        }

        $output = '<div class="pfm-upcoming-deadlines">';
        $output .= '<ul>';

        foreach ($deadlines as $deadline) {
            $urgency_class = $deadline['days_remaining'] <= 2 ? 'urgent' : 'normal';

            $output .= '<li class="deadline-item ' . $urgency_class . '">';
            $output .= '<strong>' . esc_html($deadline['title']) . '</strong><br>';
            $output .= '<small>' . sprintf(__('%d Tage verbleibend', PFM_TD), $deadline['days_remaining']) . '</small>';
            $output .= ' - <a href="' . esc_url(add_query_arg('pfm_id', $deadline['post_id'], get_permalink($deadline['post_id']))) . '">' . __('Öffnen', PFM_TD) . '</a>';
            $output .= '</li>';
        }

        $output .= '</ul>';
        $output .= '</div>';

        return $output;
    }
}

// Initialisiere Reminder-System
PFM_Reminders::init();
