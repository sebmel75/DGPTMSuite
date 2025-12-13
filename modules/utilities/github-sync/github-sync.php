<?php
/**
 * Plugin Name: DGPTM - GitHub Sync
 * Plugin URI:  https://github.com/sebmel75/DGPTMSuite
 * Description: Automatische Synchronisation mit GitHub Repository
 * Version:     1.0.0
 * Author:      Sebastian Melzer
 * Author URI:  https://dgptm.de
 * License:     GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DGPTM_GitHub_Sync')) {

    class DGPTM_GitHub_Sync {

        private static $instance = null;
        private $plugin_path;
        private $plugin_url;
        private $option_name = 'dgptm_github_sync_options';
        private $backup_dir;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);
            $this->backup_dir = WP_CONTENT_DIR . '/dgptm-backups/';

            $this->init_hooks();
        }

        private function init_hooks() {
            // Admin
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);

            // REST API Endpoint für GitHub Webhook
            add_action('rest_api_init', [$this, 'register_webhook_endpoint']);

            // AJAX
            add_action('wp_ajax_dgptm_github_sync_now', [$this, 'ajax_sync_now']);
            add_action('wp_ajax_dgptm_github_create_backup', [$this, 'ajax_create_backup']);
            add_action('wp_ajax_dgptm_github_restore_backup', [$this, 'ajax_restore_backup']);
            add_action('wp_ajax_dgptm_github_delete_backup', [$this, 'ajax_delete_backup']);
        }

        /**
         * Register REST API endpoint for GitHub webhook
         */
        public function register_webhook_endpoint() {
            register_rest_route('dgptm/v1', '/github-webhook', [
                'methods' => 'POST',
                'callback' => [$this, 'handle_webhook'],
                'permission_callback' => [$this, 'verify_webhook_signature']
            ]);
        }

        /**
         * Get option with central settings fallback
         */
        private function get_option_value($key, $default = '') {
            // Check central settings first (new system)
            if (function_exists('dgptm_get_module_setting')) {
                $value = dgptm_get_module_setting('github-sync', $key, null);
                if ($value !== null) {
                    return $value;
                }
            }

            // Fallback: Legacy option
            $options = get_option($this->option_name, []);
            return $options[$key] ?? $default;
        }

        /**
         * Verify GitHub webhook signature
         */
        public function verify_webhook_signature($request) {
            $secret = $this->get_option_value('webhook_secret', '');

            if (empty($secret)) {
                $this->log('Webhook secret not configured');
                return false;
            }

            $signature = $request->get_header('X-Hub-Signature-256');
            if (empty($signature)) {
                $this->log('No signature in webhook request');
                return false;
            }

            $payload = $request->get_body();
            $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

            if (!hash_equals($expected, $signature)) {
                $this->log('Invalid webhook signature');
                return false;
            }

            return true;
        }

        /**
         * Handle GitHub webhook
         */
        public function handle_webhook($request) {
            $event = $request->get_header('X-GitHub-Event');
            $payload = $request->get_json_params();

            $this->log("GitHub webhook received: $event");

            if ($event !== 'push') {
                return new WP_REST_Response(['message' => 'Event ignored'], 200);
            }

            // Check if push is to main branch
            $ref = $payload['ref'] ?? '';
            if ($ref !== 'refs/heads/main') {
                $this->log("Push to non-main branch ignored: $ref");
                return new WP_REST_Response(['message' => 'Non-main branch ignored'], 200);
            }

            // Trigger sync
            $result = $this->sync_from_github();

            if (is_wp_error($result)) {
                $this->log('Sync failed: ' . $result->get_error_message());
                return new WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message()
                ], 500);
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Sync completed successfully'
            ], 200);
        }

        /**
         * Sync from GitHub
         */
        public function sync_from_github() {
            $options = get_option($this->option_name, []);
            $repo = $options['repository'] ?? 'sebmel75/DGPTMSuite';
            $branch = $options['branch'] ?? 'main';
            $token = $options['github_token'] ?? '';

            $this->log("Starting sync from GitHub: $repo ($branch)");

            // 1. Create backup
            $backup_result = $this->create_backup();
            if (is_wp_error($backup_result)) {
                return $backup_result;
            }
            $backup_file = $backup_result;

            // 2. Download new version
            $download_url = "https://api.github.com/repos/$repo/zipball/$branch";

            $args = [
                'timeout' => 60,
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'DGPTM-GitHub-Sync'
                ]
            ];

            if (!empty($token)) {
                $args['headers']['Authorization'] = "Bearer $token";
            }

            $this->log("Downloading from: $download_url");

            $response = wp_remote_get($download_url, $args);

            if (is_wp_error($response)) {
                $this->log('Download failed: ' . $response->get_error_message());
                return $response;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                $this->log("Download failed with HTTP $http_code");
                return new WP_Error('download_failed', "HTTP $http_code");
            }

            // 3. Save and extract
            $temp_file = wp_tempnam('dgptm-sync');
            file_put_contents($temp_file, wp_remote_retrieve_body($response));

            $result = $this->extract_and_update($temp_file);
            unlink($temp_file);

            if (is_wp_error($result)) {
                // Rollback
                $this->log('Update failed, rolling back...');
                $this->restore_backup($backup_file);
                return $result;
            }

            $this->log('Sync completed successfully');

            // Update last sync time
            $options['last_sync'] = current_time('mysql');
            $options['last_sync_commit'] = 'webhook';
            update_option($this->option_name, $options);

            return true;
        }

        /**
         * Extract downloaded ZIP and update plugin
         */
        private function extract_and_update($zip_file) {
            if (!class_exists('ZipArchive')) {
                return new WP_Error('no_zip', 'ZipArchive not available');
            }

            $zip = new ZipArchive();
            if ($zip->open($zip_file) !== true) {
                return new WP_Error('zip_error', 'Could not open ZIP file');
            }

            // Extract to temp directory
            $temp_dir = WP_CONTENT_DIR . '/dgptm-sync-temp-' . uniqid() . '/';
            wp_mkdir_p($temp_dir);
            $zip->extractTo($temp_dir);
            $zip->close();

            // Find extracted directory (GitHub adds prefix)
            $dirs = glob($temp_dir . '*', GLOB_ONLYDIR);
            if (empty($dirs)) {
                $this->delete_directory($temp_dir);
                return new WP_Error('extract_error', 'No directory in ZIP');
            }
            $source_dir = $dirs[0] . '/';

            // Verify it's a valid DGPTM suite
            if (!file_exists($source_dir . 'dgptm-master.php')) {
                $this->delete_directory($temp_dir);
                return new WP_Error('invalid_plugin', 'Not a valid DGPTM Plugin Suite');
            }

            // Get plugin directory
            $plugin_dir = dirname(dirname(dirname($this->plugin_path))) . '/';

            // Copy files (excluding certain directories)
            $this->copy_directory($source_dir, $plugin_dir, [
                'exports',
                '.git',
                '.github'
            ]);

            // Cleanup
            $this->delete_directory($temp_dir);

            return true;
        }

        /**
         * Create backup
         */
        public function create_backup($name = null) {
            if (!class_exists('ZipArchive')) {
                return new WP_Error('no_zip', 'ZipArchive not available');
            }

            wp_mkdir_p($this->backup_dir);

            $plugin_dir = dirname(dirname(dirname($this->plugin_path)));
            $backup_name = $name ?: 'dgptm-backup-' . date('Y-m-d-His') . '.zip';
            $backup_path = $this->backup_dir . $backup_name;

            $zip = new ZipArchive();
            if ($zip->open($backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return new WP_Error('backup_failed', 'Could not create backup file');
            }

            $this->add_directory_to_zip($zip, $plugin_dir, 'dgptm-plugin-suite');
            $zip->close();

            $this->log("Backup created: $backup_name");

            // Cleanup old backups (keep last 5)
            $this->cleanup_old_backups(5);

            return $backup_path;
        }

        /**
         * Add directory to ZIP recursively
         */
        private function add_directory_to_zip($zip, $dir, $base = '') {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                $relativePath = $base . '/' . substr($filePath, strlen($dir) + 1);

                // Skip certain directories
                if (strpos($relativePath, '/exports/') !== false ||
                    strpos($relativePath, '/.git/') !== false ||
                    strpos($relativePath, '/dgptm-backups/') !== false) {
                    continue;
                }

                if ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        /**
         * Restore backup
         */
        public function restore_backup($backup_file) {
            if (!file_exists($backup_file)) {
                return new WP_Error('backup_not_found', 'Backup file not found');
            }

            $zip = new ZipArchive();
            if ($zip->open($backup_file) !== true) {
                return new WP_Error('zip_error', 'Could not open backup');
            }

            $plugin_dir = dirname(dirname(dirname($this->plugin_path))) . '/';

            // Extract to temp
            $temp_dir = WP_CONTENT_DIR . '/dgptm-restore-temp-' . uniqid() . '/';
            wp_mkdir_p($temp_dir);
            $zip->extractTo($temp_dir);
            $zip->close();

            // Copy back
            $source = $temp_dir . 'dgptm-plugin-suite/';
            if (is_dir($source)) {
                $this->copy_directory($source, $plugin_dir);
            }

            $this->delete_directory($temp_dir);

            $this->log("Backup restored: " . basename($backup_file));

            return true;
        }

        /**
         * Cleanup old backups
         */
        private function cleanup_old_backups($keep = 5) {
            $backups = glob($this->backup_dir . 'dgptm-backup-*.zip');
            if (count($backups) > $keep) {
                usort($backups, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });

                $to_delete = array_slice($backups, $keep);
                foreach ($to_delete as $file) {
                    unlink($file);
                    $this->log("Deleted old backup: " . basename($file));
                }
            }
        }

        /**
         * Get available backups
         */
        public function get_backups() {
            $backups = glob($this->backup_dir . 'dgptm-backup-*.zip');
            $result = [];

            foreach ($backups as $backup) {
                $result[] = [
                    'name' => basename($backup),
                    'path' => $backup,
                    'size' => size_format(filesize($backup)),
                    'date' => date('Y-m-d H:i:s', filemtime($backup))
                ];
            }

            usort($result, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            return $result;
        }

        /**
         * Copy directory recursively
         */
        private function copy_directory($source, $dest, $exclude = []) {
            if (!is_dir($dest)) {
                wp_mkdir_p($dest);
            }

            $dir = opendir($source);
            while (($file = readdir($dir)) !== false) {
                if ($file === '.' || $file === '..') continue;
                if (in_array($file, $exclude)) continue;

                $src_path = $source . $file;
                $dst_path = $dest . $file;

                if (is_dir($src_path)) {
                    $this->copy_directory($src_path . '/', $dst_path . '/', $exclude);
                } else {
                    copy($src_path, $dst_path);
                }
            }
            closedir($dir);
        }

        /**
         * Delete directory recursively
         */
        private function delete_directory($dir) {
            if (!is_dir($dir)) return;

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($dir);
        }

        /**
         * Log message
         */
        private function log($message) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[DGPTM GitHub Sync] ' . $message);
            }
        }

        // =============================================
        // Admin Interface
        // =============================================

        public function add_admin_menu() {
            add_submenu_page(
                'dgptm-suite',
                'GitHub Sync',
                'GitHub Sync',
                'manage_options',
                'dgptm-github-sync',
                [$this, 'render_admin_page']
            );
        }

        public function register_settings() {
            register_setting('dgptm_github_sync', $this->option_name, [$this, 'sanitize_options']);
        }

        public function sanitize_options($input) {
            $sanitized = [];
            $sanitized['repository'] = sanitize_text_field($input['repository'] ?? 'sebmel75/DGPTMSuite');
            $sanitized['branch'] = sanitize_text_field($input['branch'] ?? 'main');
            $sanitized['github_token'] = sanitize_text_field($input['github_token'] ?? '');
            $sanitized['webhook_secret'] = sanitize_text_field($input['webhook_secret'] ?? '');
            $sanitized['auto_sync'] = !empty($input['auto_sync']);

            // Preserve last sync info
            $old = get_option($this->option_name, []);
            $sanitized['last_sync'] = $old['last_sync'] ?? '';
            $sanitized['last_sync_commit'] = $old['last_sync_commit'] ?? '';

            return $sanitized;
        }

        public function render_admin_page() {
            if (!current_user_can('manage_options')) {
                return;
            }

            $options = get_option($this->option_name, []);
            $backups = $this->get_backups();
            $webhook_url = rest_url('dgptm/v1/github-webhook');

            ?>
            <div class="wrap">
                <h1>GitHub Sync</h1>

                <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
                    <h2>Status</h2>
                    <table class="form-table">
                        <tr>
                            <th>Repository</th>
                            <td><code><?php echo esc_html($options['repository'] ?? 'sebmel75/DGPTMSuite'); ?></code></td>
                        </tr>
                        <tr>
                            <th>Branch</th>
                            <td><code><?php echo esc_html($options['branch'] ?? 'main'); ?></code></td>
                        </tr>
                        <tr>
                            <th>Letzte Synchronisation</th>
                            <td><?php echo esc_html($options['last_sync'] ?? 'Noch nie'); ?></td>
                        </tr>
                        <tr>
                            <th>Webhook URL</th>
                            <td>
                                <code style="word-break: break-all;"><?php echo esc_html($webhook_url); ?></code>
                                <p class="description">Diese URL in GitHub unter Settings → Webhooks eintragen</p>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <button type="button" class="button button-primary" id="sync-now">
                            Jetzt synchronisieren
                        </button>
                        <button type="button" class="button" id="create-backup">
                            Backup erstellen
                        </button>
                    </p>
                    <div id="sync-status" style="margin-top: 10px;"></div>
                </div>

                <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
                    <h2>Einstellungen</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('dgptm_github_sync'); ?>

                        <table class="form-table">
                            <tr>
                                <th><label for="repository">Repository</label></th>
                                <td>
                                    <input type="text" id="repository"
                                           name="<?php echo $this->option_name; ?>[repository]"
                                           value="<?php echo esc_attr($options['repository'] ?? 'sebmel75/DGPTMSuite'); ?>"
                                           class="regular-text" />
                                    <p class="description">Format: username/repository</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="branch">Branch</label></th>
                                <td>
                                    <input type="text" id="branch"
                                           name="<?php echo $this->option_name; ?>[branch]"
                                           value="<?php echo esc_attr($options['branch'] ?? 'main'); ?>"
                                           class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th><label for="github_token">GitHub Token</label></th>
                                <td>
                                    <input type="password" id="github_token"
                                           name="<?php echo $this->option_name; ?>[github_token]"
                                           value="<?php echo esc_attr($options['github_token'] ?? ''); ?>"
                                           class="regular-text" />
                                    <p class="description">Optional: Für private Repositories</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="webhook_secret">Webhook Secret</label></th>
                                <td>
                                    <input type="password" id="webhook_secret"
                                           name="<?php echo $this->option_name; ?>[webhook_secret]"
                                           value="<?php echo esc_attr($options['webhook_secret'] ?? ''); ?>"
                                           class="regular-text" />
                                    <p class="description">Geheimer Schlüssel für GitHub Webhook-Verifizierung</p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Einstellungen speichern'); ?>
                    </form>
                </div>

                <div class="card" style="max-width: 800px; padding: 20px;">
                    <h2>Backups</h2>
                    <?php if (empty($backups)): ?>
                        <p>Keine Backups vorhanden.</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Dateiname</th>
                                    <th>Größe</th>
                                    <th>Datum</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><?php echo esc_html($backup['name']); ?></td>
                                    <td><?php echo esc_html($backup['size']); ?></td>
                                    <td><?php echo esc_html($backup['date']); ?></td>
                                    <td>
                                        <button type="button" class="button restore-backup"
                                                data-file="<?php echo esc_attr($backup['name']); ?>">
                                            Wiederherstellen
                                        </button>
                                        <button type="button" class="button delete-backup"
                                                data-file="<?php echo esc_attr($backup['name']); ?>">
                                            Löschen
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                var nonce = '<?php echo wp_create_nonce('dgptm_github_sync'); ?>';

                $('#sync-now').on('click', function() {
                    var $btn = $(this);
                    var $status = $('#sync-status');

                    $btn.prop('disabled', true).text('Synchronisiere...');
                    $status.html('<p>Synchronisation läuft...</p>');

                    $.post(ajaxurl, {
                        action: 'dgptm_github_sync_now',
                        nonce: nonce
                    }, function(response) {
                        $btn.prop('disabled', false).text('Jetzt synchronisieren');
                        if (response.success) {
                            $status.html('<p style="color: green;">✓ ' + response.data.message + '</p>');
                            location.reload();
                        } else {
                            $status.html('<p style="color: red;">✗ ' + response.data.message + '</p>');
                        }
                    });
                });

                $('#create-backup').on('click', function() {
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Erstelle Backup...');

                    $.post(ajaxurl, {
                        action: 'dgptm_github_create_backup',
                        nonce: nonce
                    }, function(response) {
                        $btn.prop('disabled', false).text('Backup erstellen');
                        if (response.success) {
                            alert('Backup erstellt: ' + response.data.name);
                            location.reload();
                        } else {
                            alert('Fehler: ' + response.data.message);
                        }
                    });
                });

                $('.restore-backup').on('click', function() {
                    var file = $(this).data('file');
                    if (!confirm('Backup "' + file + '" wiederherstellen? Die aktuelle Version wird überschrieben.')) {
                        return;
                    }

                    $.post(ajaxurl, {
                        action: 'dgptm_github_restore_backup',
                        nonce: nonce,
                        file: file
                    }, function(response) {
                        if (response.success) {
                            alert('Backup wiederhergestellt!');
                            location.reload();
                        } else {
                            alert('Fehler: ' + response.data.message);
                        }
                    });
                });

                $('.delete-backup').on('click', function() {
                    var file = $(this).data('file');
                    if (!confirm('Backup "' + file + '" wirklich löschen?')) {
                        return;
                    }

                    $.post(ajaxurl, {
                        action: 'dgptm_github_delete_backup',
                        nonce: nonce,
                        file: file
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Fehler: ' + response.data.message);
                        }
                    });
                });
            });
            </script>
            <?php
        }

        // =============================================
        // AJAX Handlers
        // =============================================

        public function ajax_sync_now() {
            check_ajax_referer('dgptm_github_sync', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $result = $this->sync_from_github();

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success(['message' => 'Synchronisation erfolgreich abgeschlossen']);
        }

        public function ajax_create_backup() {
            check_ajax_referer('dgptm_github_sync', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $result = $this->create_backup();

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success(['name' => basename($result)]);
        }

        public function ajax_restore_backup() {
            check_ajax_referer('dgptm_github_sync', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $file = sanitize_file_name($_POST['file'] ?? '');
            $backup_path = $this->backup_dir . $file;

            $result = $this->restore_backup($backup_path);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success(['message' => 'Backup wiederhergestellt']);
        }

        public function ajax_delete_backup() {
            check_ajax_referer('dgptm_github_sync', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $file = sanitize_file_name($_POST['file'] ?? '');
            $backup_path = $this->backup_dir . $file;

            if (file_exists($backup_path)) {
                unlink($backup_path);
                wp_send_json_success(['message' => 'Backup gelöscht']);
            } else {
                wp_send_json_error(['message' => 'Backup nicht gefunden']);
            }
        }
    }
}

// Initialize
if (!isset($GLOBALS['dgptm_github_sync_initialized'])) {
    $GLOBALS['dgptm_github_sync_initialized'] = true;
    DGPTM_GitHub_Sync::get_instance();
}
