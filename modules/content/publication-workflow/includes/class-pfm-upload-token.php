<?php
/**
 * Publication Frontend Manager - Upload Token Management
 * Handles secure token generation and validation for external uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Upload_Token {

    /**
     * Token types
     */
    const TYPE_GUTACHTEN = 'gutachten';
    const TYPE_REVISION = 'revision';
    const TYPE_AUTOR = 'autor';

    /**
     * Default expiration in days
     */
    const DEFAULT_EXPIRY_DAYS = 21;

    /**
     * Token length
     */
    const TOKEN_LENGTH = 64;

    /**
     * Generate a new upload token
     *
     * @param int    $publication_id Publication post ID
     * @param string $type           Token type (gutachten, revision, autor)
     * @param array  $options        Additional options:
     *                               - expires_in: Days until expiration (default 21)
     *                               - is_one_time: Whether token can only be used once (default true)
     *                               - reviewer_email: Email of the reviewer
     *                               - reviewer_name: Name of the reviewer
     *                               - description: Description/notes for the token
     * @return array|WP_Error Token data or error
     */
    public function generate($publication_id, $type = self::TYPE_GUTACHTEN, $options = array()) {
        global $wpdb;

        // Validate publication
        $publication = get_post($publication_id);
        if (!$publication || $publication->post_type !== 'publikation') {
            return new WP_Error('invalid_publication', __('Ungültige Publikation.', PFM_TD));
        }

        // Validate type
        $valid_types = array(self::TYPE_GUTACHTEN, self::TYPE_REVISION, self::TYPE_AUTOR);
        if (!in_array($type, $valid_types, true)) {
            return new WP_Error('invalid_type', __('Ungültiger Token-Typ.', PFM_TD));
        }

        // Generate secure token
        $token = $this->generate_secure_token();

        // Calculate expiration
        $expires_in_days = isset($options['expires_in']) ? intval($options['expires_in']) : self::DEFAULT_EXPIRY_DAYS;
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_in_days} days"));

        // Prepare data
        $data = array(
            'token' => $token,
            'publication_id' => $publication_id,
            'token_type' => $type,
            'reviewer_email' => isset($options['reviewer_email']) ? sanitize_email($options['reviewer_email']) : null,
            'reviewer_name' => isset($options['reviewer_name']) ? sanitize_text_field($options['reviewer_name']) : null,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'expires_at' => $expires_at,
            'is_one_time' => isset($options['is_one_time']) ? (int) $options['is_one_time'] : 1,
            'description' => isset($options['description']) ? sanitize_text_field($options['description']) : null,
        );

        $table = $wpdb->prefix . 'pfm_upload_tokens';

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            return new WP_Error('db_error', __('Token konnte nicht erstellt werden.', PFM_TD));
        }

        $data['id'] = $wpdb->insert_id;
        $data['upload_url'] = $this->get_upload_url($token);

        return $data;
    }

    /**
     * Generate a cryptographically secure token
     *
     * @return string
     */
    private function generate_secure_token() {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
        }
        return wp_generate_password(self::TOKEN_LENGTH, false, false);
    }

    /**
     * Validate a token and return its data
     *
     * @param string $token The token to validate
     * @return array|WP_Error Token data or error with code
     */
    public function validate($token) {
        global $wpdb;

        if (empty($token) || strlen($token) !== self::TOKEN_LENGTH) {
            return new WP_Error('invalid_token', __('Ungültiger Token.', PFM_TD), array('code' => 'invalid'));
        }

        $table = $wpdb->prefix . 'pfm_upload_tokens';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE token = %s",
            $token
        ), ARRAY_A);

        if (!$row) {
            return new WP_Error('token_not_found', __('Token nicht gefunden.', PFM_TD), array('code' => 'not_found'));
        }

        // Check expiration
        if (strtotime($row['expires_at']) < time()) {
            return new WP_Error('token_expired', __('Dieser Upload-Link ist abgelaufen.', PFM_TD), array('code' => 'expired'));
        }

        // Check if already used (for one-time tokens)
        if ($row['is_one_time'] && !empty($row['used_at'])) {
            return new WP_Error('token_used', __('Dieser Upload-Link wurde bereits verwendet.', PFM_TD), array('code' => 'used'));
        }

        // Check if publication still exists
        $publication = get_post($row['publication_id']);
        if (!$publication) {
            return new WP_Error('publication_not_found', __('Publikation nicht gefunden.', PFM_TD), array('code' => 'not_found'));
        }

        $row['publication'] = $publication;

        return $row;
    }

    /**
     * Mark a token as used
     *
     * @param string $token The token to mark
     * @return bool|WP_Error True on success or error
     */
    public function mark_used($token) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_upload_tokens';

        $result = $wpdb->update(
            $table,
            array('used_at' => current_time('mysql')),
            array('token' => $token),
            array('%s'),
            array('%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Token konnte nicht aktualisiert werden.', PFM_TD));
        }

        return true;
    }

    /**
     * Get the upload URL for a token
     *
     * @param string $token
     * @return string
     */
    public function get_upload_url($token) {
        // Try to find the upload page
        $upload_page_id = get_option('pfm_upload_page_id');

        if ($upload_page_id) {
            $base_url = get_permalink($upload_page_id);
        } else {
            // Fallback to site URL with slug
            $base_url = home_url('/gutachten-upload/');
        }

        return add_query_arg('token', $token, $base_url);
    }

    /**
     * Get all tokens for a publication
     *
     * @param int  $publication_id
     * @param bool $include_expired Include expired tokens
     * @return array
     */
    public function get_tokens_for_publication($publication_id, $include_expired = false) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_upload_tokens';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE publication_id = %d",
            $publication_id
        );

        if (!$include_expired) {
            $sql .= $wpdb->prepare(" AND expires_at > %s", current_time('mysql'));
        }

        $sql .= " ORDER BY created_at DESC";

        $tokens = $wpdb->get_results($sql, ARRAY_A) ?: array();

        // Add upload URLs
        foreach ($tokens as &$token) {
            $token['upload_url'] = $this->get_upload_url($token['token']);
            $token['is_expired'] = strtotime($token['expires_at']) < time();
            $token['is_used'] = !empty($token['used_at']);
            $token['is_active'] = !$token['is_expired'] && !$token['is_used'];
        }

        return $tokens;
    }

    /**
     * Revoke a token by ID
     *
     * @param int $token_id
     * @return bool|WP_Error True on success or error
     */
    public function revoke($token_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_upload_tokens';

        // Set expires_at to now to effectively revoke
        $result = $wpdb->update(
            $table,
            array('expires_at' => current_time('mysql')),
            array('id' => $token_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Token konnte nicht widerrufen werden.', PFM_TD));
        }

        return true;
    }

    /**
     * Delete a token by ID
     *
     * @param int $token_id
     * @return bool|WP_Error True on success or error
     */
    public function delete($token_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_upload_tokens';

        $result = $wpdb->delete($table, array('id' => $token_id), array('%d'));

        if ($result === false) {
            return new WP_Error('db_error', __('Token konnte nicht gelöscht werden.', PFM_TD));
        }

        return true;
    }

    /**
     * Cleanup expired tokens
     *
     * @param int $older_than_days Delete tokens expired more than X days ago
     * @return int Number of deleted tokens
     */
    public function cleanup_expired($older_than_days = 30) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_upload_tokens';
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$older_than_days} days"));

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE expires_at < %s",
            $cutoff
        ));

        return $result !== false ? $result : 0;
    }

    /**
     * Get token statistics
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_upload_tokens';
        $now = current_time('mysql');

        return array(
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'active' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE expires_at > %s AND used_at IS NULL",
                $now
            )),
            'used' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE used_at IS NOT NULL"),
            'expired' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE expires_at <= %s AND used_at IS NULL",
                $now
            )),
            'by_type' => array(
                self::TYPE_GUTACHTEN => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE token_type = %s",
                    self::TYPE_GUTACHTEN
                )),
                self::TYPE_REVISION => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE token_type = %s",
                    self::TYPE_REVISION
                )),
                self::TYPE_AUTOR => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE token_type = %s",
                    self::TYPE_AUTOR
                )),
            ),
        );
    }

    /**
     * Render token management UI for a publication
     *
     * @param int $publication_id
     * @return string HTML
     */
    public function render_token_management($publication_id) {
        $tokens = $this->get_tokens_for_publication($publication_id, true);

        $type_labels = array(
            self::TYPE_GUTACHTEN => __('Gutachten', PFM_TD),
            self::TYPE_REVISION => __('Revision', PFM_TD),
            self::TYPE_AUTOR => __('Autor', PFM_TD),
        );

        ob_start();
        ?>
        <div class="pfm-token-management">
            <h4><?php _e('Upload-Links', PFM_TD); ?></h4>

            <div class="pfm-generate-token">
                <form class="pfm-generate-token-form" data-publication-id="<?php echo esc_attr($publication_id); ?>">
                    <label for="pfm_token_type"><?php _e('Neuen Link erstellen:', PFM_TD); ?></label>
                    <select name="token_type" id="pfm_token_type">
                        <?php foreach ($type_labels as $type => $label): ?>
                            <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="reviewer_name" placeholder="<?php esc_attr_e('Reviewer-Name (optional)', PFM_TD); ?>" />
                    <input type="email" name="reviewer_email" placeholder="<?php esc_attr_e('Reviewer-E-Mail (optional)', PFM_TD); ?>" />
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php _e('Link erstellen', PFM_TD); ?>
                    </button>
                </form>
            </div>

            <?php if (!empty($tokens)): ?>
                <table class="widefat striped pfm-tokens-table">
                    <thead>
                        <tr>
                            <th><?php _e('Typ', PFM_TD); ?></th>
                            <th><?php _e('Für', PFM_TD); ?></th>
                            <th><?php _e('Erstellt', PFM_TD); ?></th>
                            <th><?php _e('Gültig bis', PFM_TD); ?></th>
                            <th><?php _e('Status', PFM_TD); ?></th>
                            <th><?php _e('Aktionen', PFM_TD); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $token): ?>
                            <tr class="<?php echo $token['is_active'] ? 'active' : ($token['is_used'] ? 'used' : 'expired'); ?>">
                                <td>
                                    <span class="token-type-badge <?php echo esc_attr($token['token_type']); ?>">
                                        <?php echo esc_html($type_labels[$token['token_type']] ?? $token['token_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    if ($token['reviewer_name']) {
                                        echo esc_html($token['reviewer_name']);
                                        if ($token['reviewer_email']) {
                                            echo '<br><small>' . esc_html($token['reviewer_email']) . '</small>';
                                        }
                                    } elseif ($token['reviewer_email']) {
                                        echo esc_html($token['reviewer_email']);
                                    } else {
                                        echo '<em>' . __('Nicht angegeben', PFM_TD) . '</em>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html(mysql2date('d.m.Y H:i', $token['created_at'])); ?></td>
                                <td><?php echo esc_html(mysql2date('d.m.Y H:i', $token['expires_at'])); ?></td>
                                <td>
                                    <?php if ($token['is_used']): ?>
                                        <span class="status-badge used">
                                            <?php _e('Verwendet', PFM_TD); ?>
                                            <br><small><?php echo esc_html(mysql2date('d.m.Y H:i', $token['used_at'])); ?></small>
                                        </span>
                                    <?php elseif ($token['is_expired']): ?>
                                        <span class="status-badge expired"><?php _e('Abgelaufen', PFM_TD); ?></span>
                                    <?php else: ?>
                                        <span class="status-badge active"><?php _e('Aktiv', PFM_TD); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($token['is_active']): ?>
                                        <button class="button button-small pfm-copy-token" data-url="<?php echo esc_attr($token['upload_url']); ?>" title="<?php esc_attr_e('Link kopieren', PFM_TD); ?>">
                                            <span class="dashicons dashicons-admin-page"></span>
                                        </button>
                                        <button class="button button-small pfm-revoke-token" data-token-id="<?php echo esc_attr($token['id']); ?>" title="<?php esc_attr_e('Widerrufen', PFM_TD); ?>">
                                            <span class="dashicons dashicons-dismiss"></span>
                                        </button>
                                    <?php else: ?>
                                        <button class="button button-small pfm-delete-token" data-token-id="<?php echo esc_attr($token['id']); ?>" title="<?php esc_attr_e('Löschen', PFM_TD); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="description"><?php _e('Noch keine Upload-Links erstellt.', PFM_TD); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Schedule cron job for token cleanup
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('pfm_token_cleanup')) {
            wp_schedule_event(time(), 'daily', 'pfm_token_cleanup');
        }
    }

    /**
     * Unschedule cron job
     */
    public static function unschedule_cleanup() {
        wp_clear_scheduled_hook('pfm_token_cleanup');
    }
}
