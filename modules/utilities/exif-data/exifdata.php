<?php
/**
 * Plugin Name: DGPTM - EXIF Editor for Media Library
 * Description: Zeigt und bearbeitet EXIF-Daten in der WordPress-Medienbibliothek – inklusive einer Übersicht aller Bilder zur Kontrolle urheberrechtsrelevanter Daten.
 * Version: 1.1
 * Author: Dein Name
 */

// Sicherstellen, dass das Plugin nicht direkt aufgerufen wird
if (!defined('ABSPATH')) {
    exit;
}

class EXIFEditorPlugin {

    /**
     * Hier definieren wir die EXIF‑Felder, die im Editor und in der Übersicht verfügbar sein sollen.
     */
    private $available_exif_fields = [
        'Copyright',
        'Artist',
        'Make',
        'Model',
        'DateTime',
        'GPSLatitude',
        'GPSLongitude'
    ];

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('add_meta_boxes', [$this, 'add_exif_meta_box']);
        add_action('wp_ajax_update_exif_data', [$this, 'update_exif_data']);
        add_action('admin_menu', [$this, 'register_admin_page']);
    }

    /**
     * Lädt die nötigen Skripte (und erzeugt dabei einen Nonce für die AJAX‑Aufrufe).
     */
    public function enqueue_scripts($hook) {
        // Für den Anhang‑Editor, Upload-Seite oder unsere EXIF Manager Seite laden wir das Script
        if ($hook === 'post.php' || $hook === 'upload.php' || (is_string($hook) && strpos($hook, 'exif-manager') !== false)) {
            wp_enqueue_script('exif-editor-script', plugin_dir_url(__FILE__) . 'js/exif-editor.js', ['jquery'], '1.1', true);
            wp_localize_script('exif-editor-script', 'exifEditorAjax', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('update_exif_nonce')
            ]);
        }
    }

    /**
     * Fügt in der Detailansicht eines Anhangs (im Medienbereich) eine Meta-Box hinzu,
     * über die die EXIF‑Daten angezeigt und bearbeitet werden können.
     */
    public function add_exif_meta_box() {
        add_meta_box(
            'exif_editor_meta_box',
            __('EXIF-Daten bearbeiten', 'exif-editor'),
            [$this, 'render_exif_meta_box'],
            'attachment',
            'side',
            'default'
        );
    }

    /**
     * Rendert die Meta-Box im Anhang‑Editor.
     */
    public function render_exif_meta_box($post) {
        $file_path = get_attached_file($post->ID);
        $exif_data = $this->get_exif_data($file_path);

        echo '<div id="exif_editor_meta_box">';
        echo '<table class="form-table">';
        if ($exif_data) {
            foreach ($exif_data as $key => $value) {
                // Einige Systemfelder überspringen
                if (strpos($key, 'FILE.') === 0 || strpos($key, 'COMPUTED.') === 0) {
                    continue;
                }
                echo '<tr>';
                echo '<th><label for="' . esc_attr($key) . '">' . esc_html($key) . '</label></th>';
                echo '<td><input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" /></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td>' . __('Keine EXIF-Daten gefunden.', 'exif-editor') . '</td></tr>';
        }
        echo '</table>';

        // Dropdown zum Hinzufügen neuer EXIF-Felder (optional)
        echo '<h4>' . __('Neues EXIF-Feld hinzufügen', 'exif-editor') . '</h4>';
        echo '<select id="new-exif-field">';
        echo '<option value="">' . __('Wählen Sie ein Feld aus', 'exif-editor') . '</option>';
        foreach ($this->available_exif_fields as $field) {
            echo '<option value="' . esc_attr($field) . '">' . esc_html($field) . '</option>';
        }
        echo '</select> ';
        echo '<button type="button" class="button" id="add-exif-field">' . __('Hinzufügen', 'exif-editor') . '</button><br /><br />';

        // Speichern-Button: Der Attachment-ID wird als data-Attribut mitgegeben
        echo '<button type="button" class="button button-primary" id="save-exif-data" data-attachment-id="' . esc_attr($post->ID) . '">' . __('Speichern', 'exif-editor') . '</button>';
        echo '</div>';
    }

    /**
     * Liest die EXIF-Daten der angegebenen Datei aus.
     *
     * @param string $file_path
     * @return array|false
     */
    private function get_exif_data($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        $exif_data = @exif_read_data($file_path, 0, true);
        if (!$exif_data) {
            return false;
        }

        $flattened_exif = [];
        foreach ($exif_data as $section => $data) {
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $key => $value) {
                $flattened_exif[$section . '.' . $key] = $value;
            }
        }

        return $flattened_exif;
    }

    /**
     * Liefert den Wert eines bestimmten EXIF-Feldes zurück (sucht anhand des Feldnamens am Ende des Schlüssels).
     *
     * @param array|false $exif_data
     * @param string      $field
     * @return string
     */
    private function get_exif_field_value($exif_data, $field) {
        if (!$exif_data) {
            return '';
        }
        foreach ($exif_data as $key => $value) {
            // Prüft, ob der Schlüssel mit dem gewünschten Feld endet (z.B. ".Copyright")
            if (substr($key, -strlen($field)) === $field) {
                return $value;
            }
        }
        return '';
    }

    /**
     * Registriert eine neue Admin-Seite (im Menü "Medien") zur Verwaltung aller EXIF-Daten.
     */
    public function register_admin_page() {
        add_media_page(
            __('EXIF Manager', 'exif-editor'),
            __('EXIF Manager', 'exif-editor'),
            'manage_options',
            'exif-manager',
            [$this, 'render_exif_manager_page']
        );
    }

    /**
     * Rendert die Admin-Seite, in der alle Bilder samt ausgewählten EXIF-Daten in einer Tabelle angezeigt werden.
     */
    public function render_exif_manager_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('EXIF Manager', 'exif-editor'); ?></h1>
            <p><?php _e('Hier können Sie die EXIF-Daten aller Bilder verwalten und bearbeiten.', 'exif-editor'); ?></p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Vorschaubild', 'exif-editor'); ?></th>
                        <th><?php _e('Titel', 'exif-editor'); ?></th>
                        <?php foreach ($this->available_exif_fields as $field): ?>
                            <th><?php echo esc_html($field); ?></th>
                        <?php endforeach; ?>
                        <th><?php _e('Aktionen', 'exif-editor'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Hole alle Bild-Anhänge
                    $args = [
                        'post_type'      => 'attachment',
                        'post_mime_type' => 'image',
                        'post_status'    => 'inherit',
                        'posts_per_page' => -1,
                    ];
                    $attachments = get_posts($args);
                    if ($attachments) {
                        foreach ($attachments as $attachment) {
                            $file_path = get_attached_file($attachment->ID);
                            $exif_data = $this->get_exif_data($file_path);
                            ?>
                            <tr data-attachment-id="<?php echo esc_attr($attachment->ID); ?>">
                                <td><?php echo wp_get_attachment_image($attachment->ID, [80, 80]); ?></td>
                                <td><?php echo esc_html(get_the_title($attachment->ID)); ?></td>
                                <?php
                                // Für jedes definierte EXIF-Feld wird ein Input-Feld ausgegeben.
                                foreach ($this->available_exif_fields as $field): 
                                    $value = $this->get_exif_field_value($exif_data, $field);
                                ?>
                                    <td>
                                        <input type="text" class="exif-field" data-field="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($value); ?>" />
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <button type="button" class="button save-exif-row" data-attachment-id="<?php echo esc_attr($attachment->ID); ?>">
                                        <?php _e('Speichern', 'exif-editor'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="<?php echo count($this->available_exif_fields) + 3; ?>"><?php _e('Keine Bilder gefunden.', 'exif-editor'); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * AJAX-Handler zum Aktualisieren der EXIF-Daten.
     */
    public function update_exif_data() {
        // Sicherheitsprüfung: Berechtigung und Nonce
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung.', 'exif-editor'));
        }
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_exif_nonce')) {
            wp_send_json_error(__('Ungültiger Request.', 'exif-editor'));
        }

        $attachment_id = intval($_POST['attachment_id']);
        $new_exif_data = isset($_POST['exif_data']) ? $_POST['exif_data'] : [];

        $file_path = get_attached_file($attachment_id);
        if (!file_exists($file_path)) {
            wp_send_json_error(__('Datei nicht gefunden.', 'exif-editor'));
        }

        // Versuchen, die Bilddatei mit Imagick zu öffnen
        try {
            $imagick = new Imagick($file_path);
        } catch (Exception $e) {
            wp_send_json_error(__('Fehler beim Öffnen der Datei.', 'exif-editor'));
        }

        // Aktualisieren der EXIF-Daten – es wird nur für die definierten Felder aktualisiert
        foreach ($new_exif_data as $field => $value) {
            if (in_array($field, $this->available_exif_fields)) {
                // Hier wird der "exif:" Präfix verwendet.
                $imagick->setImageProperty('exif:' . $field, $value);
            }
        }

        // Speichert die Änderungen in der Bilddatei
        if (!$imagick->writeImage($file_path)) {
            wp_send_json_error(__('Fehler beim Speichern der EXIF-Daten.', 'exif-editor'));
        }

        wp_send_json_success(__('EXIF-Daten erfolgreich aktualisiert.', 'exif-editor'));
    }
}

new EXIFEditorPlugin();
