<?php
/**
 * Publication Frontend Manager - Workflow Tracker
 * Visueller Workflow-Status-Tracker mit Timeline
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Workflow_Tracker {

    /**
     * Workflow-Stages Definition
     */
    public static function get_workflow_stages() {
        return array(
            'submitted' => array(
                'label' => __('Eingereicht', PFM_TD),
                'icon' => 'dashicons-upload',
                'color' => '#0073aa',
                'description' => __('Manuskript wurde eingereicht', PFM_TD),
            ),
            'under_review' => array(
                'label' => __('Im Review', PFM_TD),
                'icon' => 'dashicons-visibility',
                'color' => '#f0b849',
                'description' => __('Review läuft', PFM_TD),
            ),
            'revision_needed' => array(
                'label' => __('Nachbesserung', PFM_TD),
                'icon' => 'dashicons-edit',
                'color' => '#d54e21',
                'description' => __('Überarbeitung erforderlich', PFM_TD),
            ),
            'accepted' => array(
                'label' => __('Akzeptiert', PFM_TD),
                'icon' => 'dashicons-yes',
                'color' => '#46b450',
                'description' => __('Zur Veröffentlichung freigegeben', PFM_TD),
            ),
            'rejected' => array(
                'label' => __('Abgelehnt', PFM_TD),
                'icon' => 'dashicons-no',
                'color' => '#dc3232',
                'description' => __('Publikation abgelehnt', PFM_TD),
            ),
            'published' => array(
                'label' => __('Veröffentlicht', PFM_TD),
                'icon' => 'dashicons-megaphone',
                'color' => '#00a32a',
                'description' => __('Öffentlich verfügbar', PFM_TD),
            ),
        );
    }

    /**
     * Log Status-Änderung
     */
    public static function log_status_change($post_id, $old_status, $new_status, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $history = get_post_meta($post_id, 'pfm_status_history', true);
        if (!is_array($history)) {
            $history = array();
        }

        $history[] = array(
            'timestamp' => current_time('mysql'),
            'old_status' => $old_status,
            'new_status' => $new_status,
            'user_id' => $user_id,
            'user_name' => get_userdata($user_id)->display_name ?? __('Unbekannt', PFM_TD),
        );

        update_post_meta($post_id, 'pfm_status_history', $history);
    }

    /**
     * Hole Status-Historie
     */
    public static function get_status_history($post_id) {
        return get_post_meta($post_id, 'pfm_status_history', true) ?: array();
    }

    /**
     * Render Workflow Timeline
     */
    public static function render_timeline($post_id) {
        $current_status = get_post_meta($post_id, 'review_status', true);
        $stages = self::get_workflow_stages();
        $history = self::get_status_history($post_id);

        // Finde erreichte Stages
        $reached_stages = array();
        foreach ($history as $entry) {
            $reached_stages[$entry['new_status']] = $entry;
        }

        // Immer submitted als ersten Schritt
        if (!isset($reached_stages['submitted'])) {
            $reached_stages['submitted'] = array(
                'timestamp' => get_the_date('Y-m-d H:i:s', $post_id),
                'user_name' => get_the_author_meta('display_name', get_post_field('post_author', $post_id)),
            );
        }

        $output = '<div class="pfm-workflow-timeline">';
        $output .= '<h4>' . __('Publikations-Workflow', PFM_TD) . '</h4>';
        $output .= '<div class="timeline-container">';

        $stage_order = array('submitted', 'under_review', 'revision_needed', 'accepted', 'published');

        foreach ($stage_order as $stage_key) {
            if (!isset($stages[$stage_key])) continue;

            $stage = $stages[$stage_key];
            $is_current = ($stage_key === $current_status);
            $is_reached = isset($reached_stages[$stage_key]);

            $classes = array('timeline-stage');
            if ($is_current) $classes[] = 'current';
            if ($is_reached) $classes[] = 'reached';

            $output .= '<div class="' . implode(' ', $classes) . '" data-stage="' . esc_attr($stage_key) . '">';
            $output .= '<div class="stage-icon" style="background-color:' . esc_attr($stage['color']) . '">';
            $output .= '<span class="dashicons ' . esc_attr($stage['icon']) . '"></span>';
            $output .= '</div>';
            $output .= '<div class="stage-content">';
            $output .= '<h5>' . esc_html($stage['label']) . '</h5>';

            if ($is_reached && isset($reached_stages[$stage_key]['timestamp'])) {
                $output .= '<p class="stage-date">' . esc_html(mysql2date('d.m.Y H:i', $reached_stages[$stage_key]['timestamp'])) . '</p>';
                if (!empty($reached_stages[$stage_key]['user_name'])) {
                    $output .= '<p class="stage-user">' . esc_html($reached_stages[$stage_key]['user_name']) . '</p>';
                }
            }

            $output .= '</div>';
            $output .= '</div>';
        }

        // Rejected als alternativer Endpunkt
        if ($current_status === 'rejected') {
            $stage = $stages['rejected'];
            $output .= '<div class="timeline-stage current reached" data-stage="rejected">';
            $output .= '<div class="stage-icon" style="background-color:' . esc_attr($stage['color']) . '">';
            $output .= '<span class="dashicons ' . esc_attr($stage['icon']) . '"></span>';
            $output .= '</div>';
            $output .= '<div class="stage-content">';
            $output .= '<h5>' . esc_html($stage['label']) . '</h5>';
            if (isset($reached_stages['rejected']['timestamp'])) {
                $output .= '<p class="stage-date">' . esc_html(mysql2date('d.m.Y H:i', $reached_stages['rejected']['timestamp'])) . '</p>';
            }
            $output .= '</div>';
            $output .= '</div>';
        }

        $output .= '</div>'; // .timeline-container
        $output .= '</div>'; // .pfm-workflow-timeline

        return $output;
    }

    /**
     * Render kompakte Status-Badge
     */
    public static function render_status_badge($status) {
        $stages = self::get_workflow_stages();

        if (!isset($stages[$status])) {
            return '<span class="pfm-status-badge unknown">' . esc_html($status) . '</span>';
        }

        $stage = $stages[$status];

        return sprintf(
            '<span class="pfm-status-badge" style="background-color:%s; color:#fff;"><span class="dashicons %s"></span> %s</span>',
            esc_attr($stage['color']),
            esc_attr($stage['icon']),
            esc_html($stage['label'])
        );
    }

    /**
     * Render Status-Historie-Tabelle
     */
    public static function render_history_table($post_id) {
        $history = self::get_status_history($post_id);
        $stages = self::get_workflow_stages();

        if (empty($history)) {
            return '<p>' . __('Keine Statusänderungen aufgezeichnet.', PFM_TD) . '</p>';
        }

        $output = '<div class="pfm-status-history">';
        $output .= '<h4>' . __('Status-Historie', PFM_TD) . '</h4>';
        $output .= '<table class="widefat striped">';
        $output .= '<thead><tr>';
        $output .= '<th>' . __('Datum', PFM_TD) . '</th>';
        $output .= '<th>' . __('Von', PFM_TD) . '</th>';
        $output .= '<th>' . __('Nach', PFM_TD) . '</th>';
        $output .= '<th>' . __('Durch', PFM_TD) . '</th>';
        $output .= '</tr></thead>';
        $output .= '<tbody>';

        foreach (array_reverse($history) as $entry) {
            $output .= '<tr>';
            $output .= '<td>' . esc_html(mysql2date('d.m.Y H:i', $entry['timestamp'])) . '</td>';

            $old_label = isset($stages[$entry['old_status']]) ? $stages[$entry['old_status']]['label'] : $entry['old_status'];
            $new_label = isset($stages[$entry['new_status']]) ? $stages[$entry['new_status']]['label'] : $entry['new_status'];

            $output .= '<td>' . esc_html($old_label) . '</td>';
            $output .= '<td>' . esc_html($new_label) . '</td>';
            $output .= '<td>' . esc_html($entry['user_name']) . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render Progress-Indicator
     */
    public static function render_progress_indicator($post_id) {
        $current_status = get_post_meta($post_id, 'review_status', true);
        $stages = array('submitted', 'under_review', 'accepted', 'published');

        $current_index = array_search($current_status, $stages);
        if ($current_index === false) {
            $current_index = 0;
        }

        $progress = (($current_index + 1) / count($stages)) * 100;

        $output = '<div class="pfm-progress-indicator">';
        $output .= '<div class="progress-bar-container">';
        $output .= '<div class="progress-bar" style="width:' . esc_attr($progress) . '%"></div>';
        $output .= '</div>';
        $output .= '<p class="progress-text">' . sprintf(__('Schritt %d von %d', PFM_TD), $current_index + 1, count($stages)) . '</p>';
        $output .= '</div>';

        return $output;
    }
}
