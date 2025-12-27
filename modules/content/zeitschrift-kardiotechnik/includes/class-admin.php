<?php
/**
 * Zeitschrift Kardiotechnik - Admin Funktionen
 *
 * @package DGPTM_Zeitschrift_Kardiotechnik
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZK_Admin')) {

    class ZK_Admin {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            // User Profile Feld für Zeitschrift-Manager
            add_action('show_user_profile', [$this, 'add_user_fields']);
            add_action('edit_user_profile', [$this, 'add_user_fields']);
            add_action('personal_options_update', [$this, 'save_user_fields']);
            add_action('edit_user_profile_update', [$this, 'save_user_fields']);

            // Custom Columns für CPT
            add_filter('manage_' . ZK_POST_TYPE . '_posts_columns', [$this, 'add_columns']);
            add_action('manage_' . ZK_POST_TYPE . '_posts_custom_column', [$this, 'column_content'], 10, 2);
        }

        /**
         * Fügt Zeitschrift-Manager Felder zum Benutzerprofil hinzu
         */
        public function add_user_fields($user) {
            // Nur Admins können diese Felder sehen/bearbeiten
            if (!current_user_can('manage_options')) {
                return;
            }

            $is_manager = get_user_meta($user->ID, 'zeitschriftmanager', true);
            $is_editor = get_user_meta($user->ID, 'editor_in_chief', true);
            ?>
            <h3>Zeitschrift Kardiotechnik</h3>
            <table class="form-table">
                <tr>
                    <th><label for="zeitschriftmanager">Zeitschrift-Manager</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="zeitschriftmanager" id="zeitschriftmanager"
                                   value="1" <?php checked($is_manager, '1'); ?> />
                            Zugriff auf die Zeitschrift-Verwaltung erlauben
                        </label>
                        <p class="description">
                            Ermöglicht dem Benutzer, Zeitschriften zu verwalten und Veröffentlichungsdaten zu ändern.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="editor_in_chief">Chefredakteur</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="editor_in_chief" id="editor_in_chief"
                                   value="1" <?php checked($is_editor, '1'); ?> />
                            Chefredakteur-Berechtigung für Zeitschrift-Verwaltung
                        </label>
                        <p class="description">
                            Chefredakteure haben vollständigen Zugriff auf die Zeitschrift-Verwaltung.
                        </p>
                    </td>
                </tr>
            </table>
            <?php
        }

        /**
         * Speichert die Zeitschrift-Manager Felder
         */
        public function save_user_fields($user_id) {
            if (!current_user_can('manage_options')) {
                return;
            }

            // zeitschriftmanager
            $manager_value = isset($_POST['zeitschriftmanager']) ? '1' : '0';
            update_user_meta($user_id, 'zeitschriftmanager', $manager_value);

            // editor_in_chief
            $editor_value = isset($_POST['editor_in_chief']) ? '1' : '0';
            update_user_meta($user_id, 'editor_in_chief', $editor_value);
        }

        /**
         * Fügt Custom Columns zur Post-Liste hinzu
         */
        public function add_columns($columns) {
            $new_columns = [];

            foreach ($columns as $key => $value) {
                $new_columns[$key] = $value;

                if ($key === 'title') {
                    $new_columns['zk_ausgabe'] = 'Ausgabe';
                    $new_columns['zk_status'] = 'Status';
                    $new_columns['zk_articles'] = 'Artikel';
                }
            }

            return $new_columns;
        }

        /**
         * Content für Custom Columns
         */
        public function column_content($column, $post_id) {
            switch ($column) {
                case 'zk_ausgabe':
                    $jahr = get_field('jahr', $post_id);
                    $ausgabe = get_field('ausgabe', $post_id);
                    echo esc_html($jahr . '/' . $ausgabe);
                    break;

                case 'zk_status':
                    $is_visible = DGPTM_Zeitschrift_Kardiotechnik::is_issue_visible($post_id);
                    $available = get_field('verfuegbar_ab', $post_id);

                    if ($is_visible) {
                        echo '<span style="color: #46b450;">● Online</span>';
                    } else {
                        echo '<span style="color: #f0ad4e;">● Geplant: ' . esc_html($available) . '</span>';
                    }
                    break;

                case 'zk_articles':
                    $articles = DGPTM_Zeitschrift_Kardiotechnik::get_issue_articles($post_id);
                    echo count($articles) . ' Artikel';
                    break;
            }
        }

        /**
         * Holt Status-Info für eine Ausgabe
         */
        public static function get_issue_status($post_id) {
            $available = get_field('verfuegbar_ab', $post_id);
            $is_visible = DGPTM_Zeitschrift_Kardiotechnik::is_issue_visible($post_id);

            if (!$available) {
                return [
                    'status' => 'online',
                    'label' => 'Online',
                    'class' => 'zk-status-online',
                    'date' => null
                ];
            }

            $date = DateTime::createFromFormat('d/m/Y', $available);
            $today = new DateTime();
            $today->setTime(0, 0, 0);

            if ($is_visible) {
                return [
                    'status' => 'online',
                    'label' => 'Online seit ' . $date->format('d.m.Y'),
                    'class' => 'zk-status-online',
                    'date' => $available
                ];
            }

            // Prüfen ob in der Vergangenheit oder Zukunft
            $diff = $today->diff($date);
            $days_until = $diff->invert ? -$diff->days : $diff->days;

            if ($days_until <= 7 && $days_until > 0) {
                return [
                    'status' => 'soon',
                    'label' => 'In ' . $days_until . ' Tagen',
                    'class' => 'zk-status-soon',
                    'date' => $available
                ];
            }

            return [
                'status' => 'scheduled',
                'label' => 'Geplant: ' . $date->format('d.m.Y'),
                'class' => 'zk-status-scheduled',
                'date' => $available
            ];
        }

        /**
         * Konvertiert Datum von d/m/Y zu Y-m-d für Input-Felder
         */
        public static function date_to_input($date_string) {
            if (!$date_string) {
                return '';
            }

            $date = DateTime::createFromFormat('d/m/Y', $date_string);
            if (!$date) {
                return '';
            }

            return $date->format('Y-m-d');
        }

        /**
         * Konvertiert Datum von Y-m-d zu d/m/Y für ACF
         */
        public static function input_to_date($date_string) {
            if (!$date_string) {
                return '';
            }

            $date = DateTime::createFromFormat('Y-m-d', $date_string);
            if (!$date) {
                return '';
            }

            return $date->format('d/m/Y');
        }
    }
}
