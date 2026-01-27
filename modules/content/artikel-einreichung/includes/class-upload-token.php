<?php
/**
 * DGPTM Artikel-Einreichung - Upload Token
 * Manages one-time upload links for reviewers and authors
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Artikel_Upload_Token {

    /**
     * Token types
     */
    const TYPE_GUTACHTEN = 'gutachten';
    const TYPE_REVISION = 'revision';
    const TYPE_AUTOR = 'autor';

    /**
     * Generate a new upload token
     *
     * @param int    $artikel_id Article post ID
     * @param string $type       Token type: gutachten, revision, autor
     * @param array  $options    Optional: reviewer_name, reviewer_email, expires_in (days), description
     * @return array|WP_Error    Token data including upload_url or error
     */
    public function generate($artikel_id, $type = self::TYPE_GUTACHTEN, $options = array()) {
        global $wpdb;

        $artikel = get_post($artikel_id);
        if (!$artikel || $artikel->post_type !== DGPTM_Artikel_Einreichung::POST_TYPE) {
            return new WP_Error('invalid_artikel', __('Ungültiger Artikel.', 'dgptm-artikel-einreichung'));
        }

        // Generate secure random token
        $token = bin2hex(random_bytes(32)); // 64 character hex string

        // Default expiration: 28 days
        $expires_in = isset($options['expires_in']) ? intval($options['expires_in']) : 28;
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_in} days"));

        $table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';

        $insert_data = array(
            'token' => $token,
            'artikel_id' => $artikel_id,
            'token_type' => $type,
            'reviewer_email' => isset($options['reviewer_email']) ? sanitize_email($options['reviewer_email']) : null,
            'reviewer_name' => isset($options['reviewer_name']) ? sanitize_text_field($options['reviewer_name']) : null,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'expires_at' => $expires_at,
            'is_one_time' => isset($options['is_one_time']) ? (int) $options['is_one_time'] : 1,
            'description' => isset($options['description']) ? sanitize_text_field($options['description']) : null,
        );

        $result = $wpdb->insert($table, $insert_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Token konnte nicht erstellt werden.', 'dgptm-artikel-einreichung'));
        }

        $token_id = $wpdb->insert_id;

        return array(
            'id' => $token_id,
            'token' => $token,
            'artikel_id' => $artikel_id,
            'type' => $type,
            'expires_at' => $expires_at,
            'upload_url' => $this->get_upload_url($token),
        );
    }

    /**
     * Get upload URL for token
     */
    public function get_upload_url($token) {
        // Use the configured upload page or home URL with query param
        $upload_page_id = get_option('dgptm_artikel_upload_page_id');

        if ($upload_page_id) {
            $base_url = get_permalink($upload_page_id);
        } else {
            $base_url = home_url('/gutachten-upload/');
        }

        return add_query_arg('token', $token, $base_url);
    }

    /**
     * Validate a token
     *
     * @param string $token Token string
     * @return array|WP_Error Token data or error
     */
    public function validate($token) {
        global $wpdb;

        if (empty($token) || strlen($token) !== 64) {
            return new WP_Error('invalid_token', __('Ungültiger Token.', 'dgptm-artikel-einreichung'));
        }

        $table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';

        $token_data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE token = %s", $token),
            ARRAY_A
        );

        if (!$token_data) {
            return new WP_Error('token_not_found', __('Dieser Upload-Link ist ungültig.', 'dgptm-artikel-einreichung'));
        }

        // Check expiration
        if (strtotime($token_data['expires_at']) < time()) {
            return new WP_Error('token_expired', __('Dieser Upload-Link ist abgelaufen.', 'dgptm-artikel-einreichung'));
        }

        // Check if already used (for one-time tokens)
        if ($token_data['is_one_time'] && !empty($token_data['used_at'])) {
            return new WP_Error('token_used', __('Dieser Upload-Link wurde bereits verwendet.', 'dgptm-artikel-einreichung'));
        }

        // Verify article exists
        $artikel = get_post($token_data['artikel_id']);
        if (!$artikel) {
            return new WP_Error('artikel_not_found', __('Der zugehörige Artikel wurde nicht gefunden.', 'dgptm-artikel-einreichung'));
        }

        return $token_data;
    }

    /**
     * Mark token as used
     *
     * @param string $token Token string
     * @return bool|WP_Error
     */
    public function mark_used($token) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';

        $result = $wpdb->update(
            $table,
            array('used_at' => current_time('mysql')),
            array('token' => $token),
            array('%s'),
            array('%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Token konnte nicht aktualisiert werden.', 'dgptm-artikel-einreichung'));
        }

        return true;
    }

    /**
     * Revoke a token by ID
     *
     * @param int $token_id Token ID
     * @return bool|WP_Error
     */
    public function revoke($token_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';

        // Set expiration to now
        $result = $wpdb->update(
            $table,
            array('expires_at' => current_time('mysql')),
            array('id' => $token_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Token konnte nicht widerrufen werden.', 'dgptm-artikel-einreichung'));
        }

        return true;
    }

    /**
     * Delete a token by ID
     *
     * @param int $token_id Token ID
     * @return bool|WP_Error
     */
    public function delete($token_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';

        $result = $wpdb->delete($table, array('id' => $token_id), array('%d'));

        if ($result === false) {
            return new WP_Error('db_error', __('Token konnte nicht gelöscht werden.', 'dgptm-artikel-einreichung'));
        }

        return true;
    }

    /**
     * Get all tokens for an article
     *
     * @param int $artikel_id Article post ID
     * @return array
     */
    public function get_tokens_for_artikel($artikel_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE artikel_id = %d ORDER BY created_at DESC",
                $artikel_id
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Get a token by ID
     *
     * @param int $token_id Token ID
     * @return array|null
     */
    public function get_token($token_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $token_id),
            ARRAY_A
        );
    }

    /**
     * Cleanup expired tokens
     *
     * @param int $days_old Delete tokens older than this many days past expiration
     * @return int Number of deleted tokens
     */
    public function cleanup_expired($days_old = 30) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));

        $count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE expires_at < %s",
                $cutoff
            )
        );

        return $count ?: 0;
    }

    /**
     * Schedule automatic token cleanup
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('dgptm_artikel_token_cleanup')) {
            wp_schedule_event(time(), 'daily', 'dgptm_artikel_token_cleanup');
        }
    }

    /**
     * Unschedule cleanup
     */
    public static function unschedule_cleanup() {
        $timestamp = wp_next_scheduled('dgptm_artikel_token_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dgptm_artikel_token_cleanup');
        }
    }

    /**
     * Get type label
     */
    public static function get_type_label($type) {
        $labels = array(
            self::TYPE_GUTACHTEN => __('Gutachten', 'dgptm-artikel-einreichung'),
            self::TYPE_REVISION => __('Revision', 'dgptm-artikel-einreichung'),
            self::TYPE_AUTOR => __('Autor-Upload', 'dgptm-artikel-einreichung'),
        );
        return $labels[$type] ?? $type;
    }
}
