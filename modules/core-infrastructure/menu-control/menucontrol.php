<?php
/**
 * Plugin Name: Menu Role Control
 * Description: Ermöglicht das Ein- und Ausblenden von Menüpunkten basierend auf Benutzerrollen.
 * Version: 1.2.2
 * Author: Dein Name
 * Text Domain: menu-role-control
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MenuRoleControl {

    /**
     * Konstruktor: Initialisiert die Hooks.
     */
    public function __construct() {
        // Initialisierung der Einstellungen
        add_action('init', [$this, 'register_settings']);

        // Filter für die Menüelemente
        add_filter('wp_get_nav_menu_items', [$this, 'filter_menu_items_by_role'], 10, 2);

        // Hinzufügen der benutzerdefinierten Felder zu den Menüelementen im Admin
        add_action('wp_nav_menu_item_custom_fields', [$this, 'add_role_fields_to_menu_item'], 10, 4);

        // Speichern der benutzerdefinierten Felder
        add_action('wp_update_nav_menu_item', [$this, 'save_menu_item_roles'], 10, 3);

        // Enqueue Admin Styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
    }

    /**
     * Registriert die Plugin-Einstellungen.
     */
    public function register_settings() {
        // Derzeit keine Einstellungen, aber vorbereitet für zukünftige Erweiterungen
        register_setting('menu_role_control_settings_group', 'menu_role_control_settings');
    }

    /**
     * Enqueue Admin CSS für bessere Darstellung der benutzerdefinierten Felder.
     */
    public function enqueue_admin_styles($hook) {
        // Nur im Menü-Editor laden
        if ($hook !== 'nav-menus.php') {
            return;
        }

        wp_enqueue_style(
            'menu-role-control-admin',
            plugin_dir_url(__FILE__) . 'css/admin-style.css',
            [],
            '1.0.0'
        );
    }

    /**
     * Fügt benutzerdefinierte Felder zu den Menüelementen hinzu.
     *
     * @param int     $item_id Menüelement-ID.
     * @param WP_Post $item Menüelement-Objekt.
     * @param int     $depth Tiefe des Menüelements.
     * @param stdClass $args Argumente des Menüelements.
     */
    public function add_role_fields_to_menu_item($item_id, $item, $depth, $args) {
        // Nonce-Feld für Sicherheit
        wp_nonce_field('menu_role_control_save', 'menu_role_control_nonce_' . $item_id);

        $roles = wp_roles()->roles;
        $saved_roles = get_post_meta($item_id, '_menu_item_roles', true);
        $hide_logged_out = get_post_meta($item_id, '_menu_item_hide_logged_out', true);
        $hide_logged_in = get_post_meta($item_id, '_menu_item_hide_logged_in', true);
        ?>

        <p class="menu-role-control description description-wide">
            <label for="edit-menu-item-roles-<?php echo esc_attr($item_id); ?>">
                <?php esc_html_e('Beschränken auf Rollen', 'menu-role-control'); ?><br />
                <select multiple="multiple" id="edit-menu-item-roles-<?php echo esc_attr($item_id); ?>" class="widefat edit-menu-item-roles" name="menu-item-roles[<?php echo esc_attr($item_id); ?>][]">
                    <?php foreach ($roles as $role_slug => $role) : ?>
                        <option value="<?php echo esc_attr($role_slug); ?>" <?php selected(is_array($saved_roles) && in_array($role_slug, $saved_roles, true)); ?>>
                            <?php echo esc_html($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="description"><?php esc_html_e('Wähle die Rollen aus, die dieses Menüelement sehen dürfen.', 'menu-role-control'); ?></span>
            </label>
        </p>

        <p class="menu-role-control description description-wide">
            <label for="edit-menu-item-hide-logged-out-<?php echo esc_attr($item_id); ?>">
                <input type="checkbox" id="edit-menu-item-hide-logged-out-<?php echo esc_attr($item_id); ?>" name="menu-item-hide-logged-out[<?php echo esc_attr($item_id); ?>]" value="1" <?php checked($hide_logged_out, '1'); ?> />
                <?php esc_html_e('Nur für **nicht** eingeloggte Benutzer ausblenden', 'menu-role-control'); ?>
            </label>
        </p>

        <p class="menu-role-control description description-wide">
            <label for="edit-menu-item-hide-logged-in-<?php echo esc_attr($item_id); ?>">
                <input type="checkbox" id="edit-menu-item-hide-logged-in-<?php echo esc_attr($item_id); ?>" name="menu-item-hide-logged-in[<?php echo esc_attr($item_id); ?>]" value="1" <?php checked($hide_logged_in, '1'); ?> />
                <?php esc_html_e('Nur für eingeloggte Benutzer ausblenden', 'menu-role-control'); ?>
            </label>
        </p>

        <?php
    }

    /**
     * Speichert die benutzerdefinierten Felder der Menüelemente.
     *
     * @param int   $menu_id Menü-ID.
     * @param int   $menu_item_db_id Menüelement-Datenbank-ID.
     * @param array $args Argumente des Menüelements.
     */
    public function save_menu_item_roles($menu_id, $menu_item_db_id, $args) {
        // Überprüfen des Nonce-Feldes
        if (!isset($_POST['menu_role_control_nonce_' . $menu_item_db_id]) || 
            !wp_verify_nonce($_POST['menu_role_control_nonce_' . $menu_item_db_id], 'menu_role_control_save')) {
            return;
        }

        // Speichern der Rollen
        if (isset($_POST['menu-item-roles'][$menu_item_db_id]) && is_array($_POST['menu-item-roles'][$menu_item_db_id])) {
            $roles = array_map('sanitize_text_field', $_POST['menu-item-roles'][$menu_item_db_id]);
            $roles = array_filter($roles); // Entfernt leere Werte
            if (!empty($roles)) {
                update_post_meta($menu_item_db_id, '_menu_item_roles', $roles);
            } else {
                delete_post_meta($menu_item_db_id, '_menu_item_roles');
            }
        } else {
            delete_post_meta($menu_item_db_id, '_menu_item_roles');
        }

        // Speichern der "Hide for logged out"
        if (isset($_POST['menu-item-hide-logged-out'][$menu_item_db_id])) {
            update_post_meta($menu_item_db_id, '_menu_item_hide_logged_out', '1');
        } else {
            delete_post_meta($menu_item_db_id, '_menu_item_hide_logged_out');
        }

        // Speichern der "Hide for logged in"
        if (isset($_POST['menu-item-hide-logged-in'][$menu_item_db_id])) {
            update_post_meta($menu_item_db_id, '_menu_item_hide_logged_in', '1');
        } else {
            delete_post_meta($menu_item_db_id, '_menu_item_hide_logged_in');
        }
    }

    /**
     * Filtert die Menüelemente basierend auf den Benutzerrollen und Sichtbarkeitseinstellungen.
     *
     * @param array    $items Menüelemente.
     * @param stdClass $args  Menüargumente.
     * @return array Gefilterte Menüelemente.
     */
    public function filter_menu_items_by_role($items, $args) {
        if (is_admin()) {
            return $items;
        }

        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;
        $is_logged_in = is_user_logged_in();

        foreach ($items as $key => $item) {
            $item_roles = get_post_meta($item->ID, '_menu_item_roles', true);
            $hide_logged_out = get_post_meta($item->ID, '_menu_item_hide_logged_out', true);
            $hide_logged_in = get_post_meta($item->ID, '_menu_item_hide_logged_in', true);

            // Debugging: Log the meta values and user status
            // error_log("Menu Item ID: {$item->ID}");
            // error_log("Hide Logged Out: " . ($hide_logged_out ? 'Yes' : 'No'));
            // error_log("Hide Logged In: " . ($hide_logged_in ? 'Yes' : 'No'));
            // error_log("User Logged In: " . ($is_logged_in ? 'Yes' : 'No'));
            // error_log("User Roles: " . implode(', ', $user_roles));

            // Ausblenden für nicht eingeloggte Benutzer
            if ($hide_logged_out && !$is_logged_in) {
                unset($items[$key]);
                continue;
            }

            // Ausblenden für eingeloggte Benutzer
            if ($hide_logged_in && $is_logged_in) {
                unset($items[$key]);
                continue;
            }

            // Rollenbasierte Anzeige
            if (!empty($item_roles)) {
                if (!$is_logged_in) {
                    // Benutzer ist nicht eingeloggt, keine Rollen vorhanden
                    unset($items[$key]);
                    continue;
                }

                if (empty(array_intersect($user_roles, $item_roles))) {
                    unset($items[$key]);
                }
            }
        }

        return $items;
    }
}

// Initialisiert das Plugin
new MenuRoleControl();

/**
 * Aktiviert das Plugin und erstellt die erforderlichen Daten.
 */
function menu_role_control_activate() {
    // Aktionen bei der Aktivierung, falls erforderlich
}
register_activation_hook(__FILE__, 'menu_role_control_activate');

/**
 * Deaktiviert das Plugin und bereinigt die Daten.
 */
function menu_role_control_deactivate() {
    // Aktionen bei der Deaktivierung, falls erforderlich
}
register_deactivation_hook(__FILE__, 'menu_role_control_deactivate');
