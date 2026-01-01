<?php
/**
 * Erweiterter WYSIWYG-Editor mit Medienverwaltung
 *
 * Verwendet TinyMCE mit erweiterten Funktionen für Bilder,
 * Grafiken und Dateiverwaltung.
 *
 * @package DGPTM_Zeitschrift_Kardiotechnik
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZK_Editor')) {

    class ZK_Editor {

        private static $instance = null;
        private static $editor_id = 0;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            // AJAX Handler für Medien
            add_action('wp_ajax_zk_upload_media', [$this, 'ajax_upload_media']);
            add_action('wp_ajax_zk_get_media_library', [$this, 'ajax_get_media_library']);
            add_action('wp_ajax_zk_delete_media', [$this, 'ajax_delete_media']);
        }

        /**
         * Rendert einen erweiterten Editor
         *
         * @param string $content Initialer Inhalt
         * @param string $name Input-Name
         * @param array $options Editor-Optionen
         * @return string HTML
         */
        public static function render($content = '', $name = 'content', $options = []) {
            self::$editor_id++;
            $editor_id = 'zk-editor-' . self::$editor_id;

            $defaults = [
                'height' => 400,
                'placeholder' => 'Inhalt eingeben...',
                'media_buttons' => true,
                'toolbar' => 'full', // full, basic, minimal
                'allow_images' => true,
                'allow_files' => true,
                'post_id' => 0,
            ];

            $options = wp_parse_args($options, $defaults);

            // Editor-Assets laden
            self::enqueue_assets();

            ob_start();
            ?>
            <div class="zk-editor-wrapper" id="<?php echo esc_attr($editor_id); ?>-wrapper" data-editor-id="<?php echo esc_attr($editor_id); ?>">
                <?php if ($options['media_buttons']) : ?>
                    <div class="zk-editor-toolbar-extra">
                        <?php if ($options['allow_images']) : ?>
                            <button type="button" class="zk-editor-btn zk-btn-add-image" title="Bild einfügen">
                                <span class="dashicons dashicons-format-image"></span>
                                <span class="zk-btn-label">Bild</span>
                            </button>
                        <?php endif; ?>

                        <?php if ($options['allow_files']) : ?>
                            <button type="button" class="zk-editor-btn zk-btn-add-file" title="Datei einfügen">
                                <span class="dashicons dashicons-media-default"></span>
                                <span class="zk-btn-label">Datei</span>
                            </button>
                        <?php endif; ?>

                        <button type="button" class="zk-editor-btn zk-btn-media-library" title="Mediathek öffnen">
                            <span class="dashicons dashicons-admin-media"></span>
                            <span class="zk-btn-label">Mediathek</span>
                        </button>

                        <span class="zk-editor-separator"></span>

                        <button type="button" class="zk-editor-btn zk-btn-fullscreen" title="Vollbild">
                            <span class="dashicons dashicons-fullscreen-alt"></span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="zk-editor-container">
                    <textarea
                        id="<?php echo esc_attr($editor_id); ?>"
                        name="<?php echo esc_attr($name); ?>"
                        class="zk-editor-textarea"
                        data-options='<?php echo esc_attr(json_encode($options)); ?>'
                        placeholder="<?php echo esc_attr($options['placeholder']); ?>"
                    ><?php echo esc_textarea($content); ?></textarea>
                </div>

                <!-- Drop-Zone für Drag & Drop -->
                <div class="zk-editor-dropzone" style="display: none;">
                    <div class="zk-dropzone-content">
                        <span class="dashicons dashicons-upload"></span>
                        <p>Dateien hier ablegen</p>
                    </div>
                </div>

                <!-- Mediathek-Modal -->
                <div class="zk-media-modal" style="display: none;">
                    <div class="zk-media-modal-content">
                        <div class="zk-media-modal-header">
                            <h3>Mediathek</h3>
                            <button type="button" class="zk-media-modal-close">&times;</button>
                        </div>
                        <div class="zk-media-modal-body">
                            <div class="zk-media-tabs">
                                <button type="button" class="zk-media-tab active" data-tab="upload">Hochladen</button>
                                <button type="button" class="zk-media-tab" data-tab="library">Mediathek</button>
                            </div>

                            <div class="zk-media-tab-content" data-tab="upload">
                                <div class="zk-upload-area">
                                    <input type="file" class="zk-file-input" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                                    <div class="zk-upload-placeholder">
                                        <span class="dashicons dashicons-cloud-upload"></span>
                                        <p>Dateien hierher ziehen oder klicken zum Auswählen</p>
                                        <p class="zk-upload-hint">Bilder, PDFs, Office-Dokumente</p>
                                    </div>
                                    <div class="zk-upload-progress" style="display: none;">
                                        <div class="zk-progress-bar"><div class="zk-progress-fill"></div></div>
                                        <p class="zk-progress-text">Hochladen...</p>
                                    </div>
                                </div>
                            </div>

                            <div class="zk-media-tab-content" data-tab="library" style="display: none;">
                                <div class="zk-media-filter">
                                    <select class="zk-media-type-filter">
                                        <option value="">Alle Medien</option>
                                        <option value="image">Bilder</option>
                                        <option value="application/pdf">PDFs</option>
                                        <option value="application">Dokumente</option>
                                    </select>
                                    <input type="search" class="zk-media-search" placeholder="Suchen...">
                                </div>
                                <div class="zk-media-grid"></div>
                                <div class="zk-media-loading" style="display: none;">Laden...</div>
                            </div>
                        </div>
                        <div class="zk-media-modal-footer">
                            <div class="zk-media-selection-info"></div>
                            <button type="button" class="zk-btn-insert" disabled>Einfügen</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Editor-Assets laden
         */
        public static function enqueue_assets() {
            static $enqueued = false;

            if ($enqueued) {
                return;
            }

            // WordPress-Editor laden
            wp_enqueue_editor();
            wp_enqueue_media();

            // Custom Editor CSS
            wp_enqueue_style(
                'zk-editor',
                ZK_PLUGIN_URL . 'assets/css/editor.css',
                [],
                ZK_VERSION
            );

            // Custom Editor JS
            wp_enqueue_script(
                'zk-editor',
                ZK_PLUGIN_URL . 'assets/js/editor.js',
                ['jquery', 'editor', 'quicktags'],
                ZK_VERSION,
                true
            );

            wp_localize_script('zk-editor', 'zkEditor', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('zk_admin_nonce'),
                'strings' => [
                    'uploadError' => __('Fehler beim Hochladen', 'zeitschrift-kardiotechnik'),
                    'selectFile' => __('Datei auswählen', 'zeitschrift-kardiotechnik'),
                    'insertImage' => __('Bild einfügen', 'zeitschrift-kardiotechnik'),
                    'insertFile' => __('Datei einfügen', 'zeitschrift-kardiotechnik'),
                    'dragDropHint' => __('Dateien hier ablegen', 'zeitschrift-kardiotechnik'),
                    'uploading' => __('Hochladen...', 'zeitschrift-kardiotechnik'),
                    'processing' => __('Verarbeite...', 'zeitschrift-kardiotechnik'),
                ],
                'allowedTypes' => [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
                'maxFileSize' => wp_max_upload_size(),
            ]);

            $enqueued = true;
        }

        /**
         * Medien-Upload Handler
         */
        public function ajax_upload_media() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('upload_files')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            if (!isset($_FILES['file'])) {
                wp_send_json_error(['message' => 'Keine Datei']);
            }

            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $post_id = intval($_POST['post_id'] ?? 0);

            $attachment_id = media_handle_upload('file', $post_id);

            if (is_wp_error($attachment_id)) {
                wp_send_json_error(['message' => $attachment_id->get_error_message()]);
            }

            $attachment = get_post($attachment_id);
            $url = wp_get_attachment_url($attachment_id);
            $thumb = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            $medium = wp_get_attachment_image_src($attachment_id, 'medium');
            $large = wp_get_attachment_image_src($attachment_id, 'large');
            $full = wp_get_attachment_image_src($attachment_id, 'full');

            wp_send_json_success([
                'id' => $attachment_id,
                'url' => $url,
                'title' => $attachment->post_title,
                'filename' => basename($url),
                'mime' => $attachment->post_mime_type,
                'sizes' => [
                    'thumbnail' => $thumb ? $thumb[0] : null,
                    'medium' => $medium ? $medium[0] : null,
                    'large' => $large ? $large[0] : null,
                    'full' => $full ? $full[0] : null,
                ],
                'is_image' => strpos($attachment->post_mime_type, 'image') === 0,
            ]);
        }

        /**
         * Mediathek laden
         */
        public function ajax_get_media_library() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('upload_files')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $post_id = intval($_POST['post_id'] ?? 0);
            $type = sanitize_text_field($_POST['type'] ?? '');
            $search = sanitize_text_field($_POST['search'] ?? '');
            $page = intval($_POST['page'] ?? 1);
            $per_page = 24;

            $args = [
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => 'date',
                'order' => 'DESC',
            ];

            if (!empty($type)) {
                if ($type === 'image') {
                    $args['post_mime_type'] = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                } else {
                    $args['post_mime_type'] = $type;
                }
            }

            if (!empty($search)) {
                $args['s'] = $search;
            }

            // Wenn Post-ID gegeben, zuerst dessen Attachments
            if ($post_id > 0) {
                $args['post_parent'] = $post_id;
            }

            $query = new WP_Query($args);
            $items = [];

            foreach ($query->posts as $attachment) {
                $url = wp_get_attachment_url($attachment->ID);
                $thumb = wp_get_attachment_image_src($attachment->ID, 'thumbnail');

                $items[] = [
                    'id' => $attachment->ID,
                    'url' => $url,
                    'title' => $attachment->post_title,
                    'filename' => basename($url),
                    'mime' => $attachment->post_mime_type,
                    'thumbnail' => $thumb ? $thumb[0] : null,
                    'is_image' => strpos($attachment->post_mime_type, 'image') === 0,
                    'date' => get_the_date('d.m.Y', $attachment->ID),
                ];
            }

            wp_send_json_success([
                'items' => $items,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages,
                'page' => $page,
            ]);
        }

        /**
         * Medium löschen
         */
        public function ajax_delete_media() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('delete_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $attachment_id = intval($_POST['attachment_id'] ?? 0);

            if ($attachment_id <= 0) {
                wp_send_json_error(['message' => 'Ungültige ID']);
            }

            $result = wp_delete_attachment($attachment_id, true);

            if (!$result) {
                wp_send_json_error(['message' => 'Fehler beim Löschen']);
            }

            wp_send_json_success(['message' => 'Gelöscht']);
        }

        /**
         * TinyMCE-Konfiguration für den Editor
         */
        public static function get_tinymce_settings($toolbar = 'full') {
            $toolbars = [
                'full' => [
                    'toolbar1' => 'formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | blockquote hr | link unlink | table | removeformat | fullscreen',
                    'toolbar2' => 'subscript superscript | charmap | pastetext | undo redo | help',
                ],
                'basic' => [
                    'toolbar1' => 'bold italic | bullist numlist | link unlink | removeformat',
                    'toolbar2' => '',
                ],
                'minimal' => [
                    'toolbar1' => 'bold italic link',
                    'toolbar2' => '',
                ],
            ];

            return $toolbars[$toolbar] ?? $toolbars['full'];
        }
    }
}
