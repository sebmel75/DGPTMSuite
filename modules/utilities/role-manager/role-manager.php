<?php
/**
 * Plugin Name: DGPTM Role Manager
 * Description: Backend-Zugriffskontrolle, Multiple Rollen und Toolbar-Management
 * Version: 1.0.0
 * Author: Sebastian Melzer
 * Text Domain: dgptm-role-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hauptklasse für Role Manager
 */
if (!class_exists('DGPTM_Suite_Role_Manager')) {
class DGPTM_Suite_Role_Manager {

    private static $instance = null;

    // Option-Namen
    const OPTION_BACKEND_ROLES = 'dgptm_role_manager_backend_roles';
    const OPTION_TOOLBAR_ROLES = 'dgptm_role_manager_toolbar_roles';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        try {
            error_log('DGPTM Role Manager: Konstruktor gestartet');

            // Backend-Zugriffssperre (spät laden, nach allen anderen Plugins)
            add_action('admin_init', [$this, 'restrict_backend_access'], 999);

            // Toolbar-Kontrolle
            add_action('after_setup_theme', [$this, 'control_toolbar']);

            // User-Profil erweitern für Multiple Rollen
            add_action('show_user_profile', [$this, 'display_role_selector'], 5);
            add_action('edit_user_profile', [$this, 'display_role_selector'], 5);

            // WICHTIG: Entferne Standard-Role aus POST BEVOR WordPress es verarbeitet
            add_action('personal_options_update', [$this, 'remove_standard_role_from_post'], 1);
            add_action('edit_user_profile_update', [$this, 'remove_standard_role_from_post'], 1);

            // Dann speichere unsere Rollen
            add_action('personal_options_update', [$this, 'save_user_roles'], 999);
            add_action('edit_user_profile_update', [$this, 'save_user_roles'], 999);

            // Verstecke Standard WordPress Role-Selector
            add_action('admin_footer-user-edit.php', [$this, 'hide_default_role_selector']);
            add_action('admin_footer-profile.php', [$this, 'hide_default_role_selector']);

            // Settings Page
            add_action('admin_menu', [$this, 'add_settings_page'], 99);
            add_action('admin_init', [$this, 'register_settings']);

            // AJAX Handlers
            add_action('wp_ajax_dgptm_create_role', [$this, 'ajax_create_role']);
            add_action('wp_ajax_dgptm_delete_role', [$this, 'ajax_delete_role']);
            add_action('wp_ajax_dgptm_update_role_capabilities', [$this, 'ajax_update_role_capabilities']);
            add_action('wp_ajax_dgptm_get_role_capabilities', [$this, 'ajax_get_role_capabilities']);

            error_log('DGPTM Role Manager: Konstruktor erfolgreich abgeschlossen');
        } catch (Exception $e) {
            error_log('DGPTM Role Manager: Fehler im Konstruktor - ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Hole erlaubte Backend-Rollen aus Einstellungen
     */
    private function get_backend_allowed_roles() {
        $roles = get_option(self::OPTION_BACKEND_ROLES, ['administrator', 'editor', 'author']);
        return is_array($roles) ? $roles : ['administrator', 'editor', 'author'];
    }

    /**
     * Hole erlaubte Toolbar-Rollen aus Einstellungen
     */
    private function get_toolbar_allowed_roles() {
        $roles = get_option(self::OPTION_TOOLBAR_ROLES, ['administrator', 'editor']);
        return is_array($roles) ? $roles : ['administrator', 'editor'];
    }

    /**
     * Backend-Zugriff beschränken
     */
    public function restrict_backend_access() {
        // Skip für AJAX Requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Skip während Plugin-Aktivierung/Deaktivierung
        if (isset($_GET['action']) && in_array($_GET['action'], ['activate', 'deactivate'])) {
            return;
        }

        // Skip auf DGPTM Suite eigenen Seiten (für Module-Aktivierung)
        if (isset($_GET['page']) && strpos($_GET['page'], 'dgptm-suite') !== false) {
            return;
        }

        // Skip für Elementor-Editor (Frontend Page Editor)
        if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
            return;
        }

        // Skip für erlaubte Dateien
        $allowed_files = ['admin-ajax.php', 'admin-post.php'];
        $current_file = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
        if (in_array($current_file, $allowed_files)) {
            return;
        }

        // Prüfe ob User eingeloggt ist
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return;
        }

        // Sicherheit: Immer Admins durchlassen (doppelter Check)
        if (current_user_can('manage_options')) {
            return;
        }

        $user_roles = (array) $user->roles;

        // Leere Rollen = durchlassen (Sicherheit)
        if (empty($user_roles)) {
            return;
        }

        // Prüfe ob User mindestens eine erlaubte Rolle hat
        $has_access = false;
        $backend_allowed_roles = $this->get_backend_allowed_roles();
        foreach ($backend_allowed_roles as $allowed_role) {
            if (in_array($allowed_role, $user_roles)) {
                $has_access = true;
                break;
            }
        }

        // ZUSÄTZLICH: Prüfe ob User eine aktive Frontend-Editor Session hat
        if (!$has_access && class_exists('DGPTM_Frontend_Page_Editor')) {
            $fpe = DGPTM_Frontend_Page_Editor::get_instance();
            if (method_exists($fpe, 'user_has_page_access')) {
                if ($fpe->user_has_page_access($user->ID)) {
                    // User hat Seiten zugewiesen = Zugriff erlauben für Elementor
                    $has_access = true;
                }
            }
        }

        // Kein Zugriff = Redirect zum Frontend
        if (!$has_access) {
            error_log('DGPTM Role Manager: Backend-Zugriff verweigert für User ' . $user->ID . ' mit Rollen: ' . implode(', ', $user_roles));
            wp_safe_redirect(home_url());
            exit;
        }
    }

    /**
     * Toolbar-Sichtbarkeit kontrollieren
     */
    public function control_toolbar() {
        // Prüfe ob User eingeloggt ist
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return;
        }

        $user_roles = (array) $user->roles;

        // Prüfe ob User Toolbar sehen darf
        $can_see_toolbar = false;
        $toolbar_allowed_roles = $this->get_toolbar_allowed_roles();
        foreach ($toolbar_allowed_roles as $allowed_role) {
            if (in_array($allowed_role, $user_roles)) {
                $can_see_toolbar = true;
                break;
            }
        }

        // Toolbar ausblenden wenn nicht erlaubt
        if (!$can_see_toolbar) {
            show_admin_bar(false);
        }
    }

    /**
     * Entferne Standard-Role Feld aus POST damit WordPress es nicht überschreibt
     */
    public function remove_standard_role_from_post($user_id) {
        error_log('DGPTM Role Manager: remove_standard_role_from_post aufgerufen für User ' . $user_id);

        if (isset($_POST['role'])) {
            error_log('DGPTM Role Manager: Standard-Role Feld gefunden im POST: ' . $_POST['role']);
            error_log('DGPTM Role Manager: Entferne Standard-Role Feld aus POST');
            unset($_POST['role']);
            error_log('DGPTM Role Manager: Standard-Role Feld entfernt - POST Keys jetzt: ' . print_r(array_keys($_POST), true));
        } else {
            error_log('DGPTM Role Manager: Kein Standard-Role Feld in POST gefunden');
        }
    }

    /**
     * Verstecke Standard WordPress Role-Selector
     */
    public function hide_default_role_selector() {
        if (!current_user_can('promote_users')) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Verstecke das Standard-Rollen-Feld von WordPress
            $('select#role').closest('tr').hide();
        });
        </script>
        <?php
    }

    /**
     * Multiple Rollen Selector im User-Profil anzeigen
     */
    public function display_role_selector($user) {
        // Nur Admins dürfen Rollen ändern
        if (!current_user_can('promote_users')) {
            return;
        }

        // Hole alle verfügbaren Rollen (sicherer Zugriff)
        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        $all_roles = $wp_roles->get_names();
        $user_roles = (array) $user->roles;

        ?>
        <h3><?php _e('Benutzer-Rollen', 'dgptm-role-manager'); ?></h3>
        <table class="form-table" role="presentation">
            <tr>
                <th><label><?php _e('Rollen (Mehrfachauswahl)', 'dgptm-role-manager'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Rollen', 'dgptm-role-manager'); ?></span></legend>
                        <?php foreach ($all_roles as $role_slug => $role_name): ?>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox"
                                       name="dgptm_user_roles[]"
                                       value="<?php echo esc_attr($role_slug); ?>"
                                       <?php checked(in_array($role_slug, $user_roles)); ?>>
                                <?php echo esc_html($role_name); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description">
                            <?php _e('Wählen Sie eine oder mehrere Rollen aus. Der Benutzer erhält alle Capabilities der ausgewählten Rollen.', 'dgptm-role-manager'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * User-Rollen speichern
     */
    public function save_user_roles($user_id) {
        // Debug-Logging
        error_log('=== DGPTM Role Manager: save_user_roles START für User ' . $user_id . ' ===');
        error_log('POST Keys: ' . print_r(array_keys($_POST), true));

        // Capability Check
        if (!current_user_can('edit_user', $user_id)) {
            error_log('DGPTM Role Manager: Keine Berechtigung - User kann edit_user nicht');
            return;
        }

        // Prüfe ob unser Feld im POST ist
        if (!isset($_POST['dgptm_user_roles'])) {
            error_log('DGPTM Role Manager: dgptm_user_roles NICHT in POST - Form nicht abgeschickt oder keine Checkboxen aktiviert');
            // Wenn das Feld nicht vorhanden ist, setze Subscriber als Default
            $user = new WP_User($user_id);
            foreach ($user->roles as $role) {
                $user->remove_role($role);
            }
            $user->add_role('subscriber');
            error_log('DGPTM Role Manager: Subscriber als Default gesetzt');
            error_log('=== DGPTM Role Manager: save_user_roles END (subscriber) ===');
            return;
        }

        $selected_roles = $_POST['dgptm_user_roles'];
        if (!is_array($selected_roles)) {
            $selected_roles = [];
        }

        error_log('DGPTM Role Manager: Ausgewählte Rollen: ' . print_r($selected_roles, true));

        // Verhindere dass User sich selbst die Admin-Rolle entfernt
        $editing_user = wp_get_current_user();
        if ($editing_user->ID == $user_id && in_array('administrator', $editing_user->roles)) {
            if (!in_array('administrator', $selected_roles)) {
                error_log('DGPTM Role Manager: Admin darf sich nicht selbst Admin-Rolle entfernen');
                error_log('=== DGPTM Role Manager: save_user_roles END (verhindert) ===');
                return;
            }
        }

        // Hole User
        $user = new WP_User($user_id);
        $current_roles = (array) $user->roles;

        error_log('DGPTM Role Manager: Aktuelle Rollen vor Änderung: ' . print_r($current_roles, true));

        // Entferne alle aktuellen Rollen
        foreach ($current_roles as $role) {
            $user->remove_role($role);
            error_log('DGPTM Role Manager: Rolle entfernt - ' . $role);
        }

        // Füge ausgewählte Rollen hinzu
        foreach ($selected_roles as $role) {
            $role = sanitize_text_field($role);
            $user->add_role($role);
            error_log('DGPTM Role Manager: Rolle hinzugefügt - ' . $role);
        }

        // Wenn keine Rollen ausgewählt wurden, setze Subscriber
        if (empty($selected_roles)) {
            $user->add_role('subscriber');
            error_log('DGPTM Role Manager: Subscriber als Fallback gesetzt');
        }

        // Finale Rollen loggen
        $user = new WP_User($user_id); // Neu laden um sicher zu sein
        error_log('DGPTM Role Manager: Finale Rollen: ' . print_r($user->roles, true));
        error_log('=== DGPTM Role Manager: save_user_roles END ===');
    }

    /**
     * Füge Settings-Seite hinzu
     */
    public function add_settings_page() {
        add_submenu_page(
            'dgptm-suite',
            __('Role Manager', 'dgptm-role-manager'),
            __('Role Manager', 'dgptm-role-manager'),
            'manage_options',
            'dgptm-role-manager',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Registriere Settings
     */
    public function register_settings() {
        register_setting('dgptm_role_manager_settings', self::OPTION_BACKEND_ROLES);
        register_setting('dgptm_role_manager_settings', self::OPTION_TOOLBAR_ROLES);
    }

    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle form submission
        if (isset($_POST['dgptm_save_role_settings']) && check_admin_referer('dgptm_role_manager_settings')) {
            $backend_roles = isset($_POST['backend_roles']) ? array_map('sanitize_text_field', $_POST['backend_roles']) : [];
            $toolbar_roles = isset($_POST['toolbar_roles']) ? array_map('sanitize_text_field', $_POST['toolbar_roles']) : [];

            // Stelle sicher dass Administrator immer dabei ist
            if (!in_array('administrator', $backend_roles)) {
                $backend_roles[] = 'administrator';
            }
            if (!in_array('administrator', $toolbar_roles)) {
                $toolbar_roles[] = 'administrator';
            }

            update_option(self::OPTION_BACKEND_ROLES, $backend_roles);
            update_option(self::OPTION_TOOLBAR_ROLES, $toolbar_roles);

            echo '<div class="notice notice-success"><p>' . __('Einstellungen gespeichert.', 'dgptm-role-manager') . '</p></div>';
        }

        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        $all_roles = $wp_roles->get_names();
        $backend_roles = $this->get_backend_allowed_roles();
        $toolbar_roles = $this->get_toolbar_allowed_roles();

        ?>
        <div class="wrap">
            <h1><?php _e('Role Manager Einstellungen', 'dgptm-role-manager'); ?></h1>

            <!-- Access Control Settings -->
            <form method="post" action="">
                <?php wp_nonce_field('dgptm_role_manager_settings'); ?>

                <h2><?php _e('Zugriffskontrolle', 'dgptm-role-manager'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Backend-Zugriff erlaubt für:', 'dgptm-role-manager'); ?></th>
                        <td>
                            <?php foreach ($all_roles as $role_slug => $role_name): ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="backend_roles[]" value="<?php echo esc_attr($role_slug); ?>"
                                           <?php checked(in_array($role_slug, $backend_roles)); ?>
                                           <?php disabled($role_slug, 'administrator'); ?>>
                                    <?php echo esc_html($role_name); ?>
                                    <?php if ($role_slug === 'administrator'): ?>
                                        <em>(<?php _e('immer aktiv', 'dgptm-role-manager'); ?>)</em>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">
                                <?php _e('Wähle die Rollen aus, die Zugriff auf das WordPress-Backend haben sollen.', 'dgptm-role-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Toolbar sichtbar für:', 'dgptm-role-manager'); ?></th>
                        <td>
                            <?php foreach ($all_roles as $role_slug => $role_name): ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="toolbar_roles[]" value="<?php echo esc_attr($role_slug); ?>"
                                           <?php checked(in_array($role_slug, $toolbar_roles)); ?>
                                           <?php disabled($role_slug, 'administrator'); ?>>
                                    <?php echo esc_html($role_name); ?>
                                    <?php if ($role_slug === 'administrator'): ?>
                                        <em>(<?php _e('immer aktiv', 'dgptm-role-manager'); ?>)</em>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">
                                <?php _e('Wähle die Rollen aus, die die WordPress-Toolbar sehen können.', 'dgptm-role-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Einstellungen speichern', 'dgptm-role-manager'), 'primary', 'dgptm_save_role_settings'); ?>
            </form>

            <hr>

            <!-- Role Management -->
            <h2><?php _e('Rollen verwalten', 'dgptm-role-manager'); ?></h2>

            <!-- Create New Role -->
            <div class="card" style="max-width: 800px;">
                <h3><?php _e('Neue Rolle erstellen', 'dgptm-role-manager'); ?></h3>
                <form id="dgptm-create-role-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new_role_slug"><?php _e('Rollen-ID (Slug)', 'dgptm-role-manager'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="new_role_slug" name="role_slug" class="regular-text" required
                                       pattern="[a-z0-9_-]+" placeholder="z.B. custom_role">
                                <p class="description">
                                    <?php _e('Nur Kleinbuchstaben, Zahlen, Unterstriche und Bindestriche erlaubt.', 'dgptm-role-manager'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="new_role_name"><?php _e('Anzeigename', 'dgptm-role-manager'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="new_role_name" name="role_name" class="regular-text" required
                                       placeholder="z.B. Benutzerdefinierte Rolle">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="copy_from_role"><?php _e('Capabilities kopieren von', 'dgptm-role-manager'); ?></label>
                            </th>
                            <td>
                                <select id="copy_from_role" name="copy_from_role">
                                    <option value=""><?php _e('Keine (leere Rolle)', 'dgptm-role-manager'); ?></option>
                                    <?php foreach ($all_roles as $role_slug => $role_name): ?>
                                        <option value="<?php echo esc_attr($role_slug); ?>">
                                            <?php echo esc_html($role_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php _e('Optional: Kopiere alle Fähigkeiten einer existierenden Rolle.', 'dgptm-role-manager'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="submit" class="button button-primary">
                            <?php _e('Rolle erstellen', 'dgptm-role-manager'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <!-- Existing Roles -->
            <div class="card" style="max-width: none; margin-top: 20px;">
                <h3><?php _e('Existierende Rollen', 'dgptm-role-manager'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Rolle', 'dgptm-role-manager'); ?></th>
                            <th><?php _e('Slug', 'dgptm-role-manager'); ?></th>
                            <th><?php _e('Capabilities', 'dgptm-role-manager'); ?></th>
                            <th><?php _e('Aktionen', 'dgptm-role-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_roles as $role_slug => $role_name): ?>
                            <?php
                            $role = get_role($role_slug);
                            $capabilities = $role ? array_keys($role->capabilities) : [];
                            $is_default = in_array($role_slug, ['administrator', 'editor', 'author', 'contributor', 'subscriber']);
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($role_name); ?></strong></td>
                                <td><code><?php echo esc_html($role_slug); ?></code></td>
                                <td>
                                    <details>
                                        <summary><?php echo count($capabilities); ?> <?php _e('Capabilities', 'dgptm-role-manager'); ?></summary>
                                        <div style="margin-top: 10px; max-height: 200px; overflow-y: auto;">
                                            <?php if (!empty($capabilities)): ?>
                                                <ul style="margin: 0; padding-left: 20px;">
                                                    <?php foreach ($capabilities as $cap): ?>
                                                        <li><code style="font-size: 11px;"><?php echo esc_html($cap); ?></code></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <em><?php _e('Keine Capabilities', 'dgptm-role-manager'); ?></em>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </td>
                                <td>
                                    <button type="button" class="button button-small dgptm-edit-role"
                                            data-role="<?php echo esc_attr($role_slug); ?>"
                                            data-name="<?php echo esc_attr($role_name); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php _e('Bearbeiten', 'dgptm-role-manager'); ?>
                                    </button>
                                    <?php if (!$is_default): ?>
                                        <button type="button" class="button button-small button-link-delete dgptm-delete-role"
                                                data-role="<?php echo esc_attr($role_slug); ?>"
                                                data-name="<?php echo esc_attr($role_name); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                            <?php _e('Löschen', 'dgptm-role-manager'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Role Modal -->
        <div id="dgptm-edit-role-modal" style="display: none;">
            <div class="dgptm-modal-overlay"></div>
            <div class="dgptm-modal-content" style="max-width: 700px;">
                <div class="dgptm-modal-header">
                    <h2><?php _e('Rolle bearbeiten', 'dgptm-role-manager'); ?></h2>
                    <button class="dgptm-modal-close" type="button">&times;</button>
                </div>
                <div class="dgptm-modal-body" style="max-height: 60vh; overflow-y: auto;">
                    <form id="dgptm-edit-role-form">
                        <input type="hidden" id="edit_role_slug" name="role_slug">
                        <p>
                            <strong><?php _e('Rolle:', 'dgptm-role-manager'); ?></strong>
                            <span id="edit_role_name_display"></span>
                            (<code id="edit_role_slug_display"></code>)
                        </p>
                        <h3><?php _e('Capabilities', 'dgptm-role-manager'); ?></h3>
                        <p class="description">
                            <?php _e('Wähle die Fähigkeiten aus, die diese Rolle haben soll.', 'dgptm-role-manager'); ?>
                        </p>
                        <div id="dgptm-capabilities-list" style="column-count: 2; column-gap: 20px;">
                            <!-- Wird per JavaScript gefüllt -->
                        </div>
                    </form>
                </div>
                <div class="dgptm-modal-footer">
                    <button type="button" class="button button-primary" id="dgptm-save-role-caps">
                        <?php _e('Speichern', 'dgptm-role-manager'); ?>
                    </button>
                    <button type="button" class="button dgptm-modal-close">
                        <?php _e('Abbrechen', 'dgptm-role-manager'); ?>
                    </button>
                </div>
            </div>
        </div>

        <style>
        .dgptm-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 100000;
        }
        .dgptm-modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 100001;
            max-width: 90%;
            width: 600px;
        }
        .dgptm-modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dgptm-modal-header h2 {
            margin: 0;
        }
        .dgptm-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }
        .dgptm-modal-close:hover {
            color: #000;
        }
        .dgptm-modal-body {
            padding: 20px;
        }
        .dgptm-modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            text-align: right;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Create Role
            $('#dgptm-create-role-form').on('submit', function(e) {
                e.preventDefault();

                const formData = {
                    action: 'dgptm_create_role',
                    nonce: '<?php echo wp_create_nonce('dgptm_role_manager'); ?>',
                    role_slug: $('#new_role_slug').val(),
                    role_name: $('#new_role_name').val(),
                    copy_from_role: $('#copy_from_role').val()
                };

                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        alert('<?php _e('Rolle erfolgreich erstellt!', 'dgptm-role-manager'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Fehler:', 'dgptm-role-manager'); ?> ' + response.data.message);
                    }
                });
            });

            // Delete Role
            $('.dgptm-delete-role').on('click', function() {
                const roleSlug = $(this).data('role');
                const roleName = $(this).data('name');

                if (!confirm('<?php _e('Möchten Sie die Rolle wirklich löschen?', 'dgptm-role-manager'); ?>\n\n' + roleName + ' (' + roleSlug + ')')) {
                    return;
                }

                $.post(ajaxurl, {
                    action: 'dgptm_delete_role',
                    nonce: '<?php echo wp_create_nonce('dgptm_role_manager'); ?>',
                    role_slug: roleSlug
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Rolle gelöscht!', 'dgptm-role-manager'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Fehler:', 'dgptm-role-manager'); ?> ' + response.data.message);
                    }
                });
            });

            // Edit Role - Get all capabilities
            const allCapabilities = <?php
                $all_caps = [];
                foreach ($all_roles as $role_slug => $role_name) {
                    $role = get_role($role_slug);
                    if ($role && $role->capabilities) {
                        $all_caps = array_merge($all_caps, array_keys($role->capabilities));
                    }
                }
                $all_caps = array_unique($all_caps);
                sort($all_caps);
                echo json_encode($all_caps);
            ?>;

            // Edit Role
            $('.dgptm-edit-role').on('click', function() {
                const roleSlug = $(this).data('role');
                const roleName = $(this).data('name');

                $('#edit_role_slug').val(roleSlug);
                $('#edit_role_name_display').text(roleName);
                $('#edit_role_slug_display').text(roleSlug);

                // Load current capabilities
                $.post(ajaxurl, {
                    action: 'dgptm_get_role_capabilities',
                    nonce: '<?php echo wp_create_nonce('dgptm_role_manager'); ?>',
                    role_slug: roleSlug
                }, function(response) {
                    if (response.success) {
                        const currentCaps = response.data.capabilities;
                        const $capsList = $('#dgptm-capabilities-list');
                        $capsList.empty();

                        allCapabilities.forEach(function(cap) {
                            const checked = currentCaps.includes(cap) ? 'checked' : '';
                            $capsList.append(`
                                <label style="display: block; margin-bottom: 8px; break-inside: avoid;">
                                    <input type="checkbox" name="capabilities[]" value="${cap}" ${checked}>
                                    <code style="font-size: 11px;">${cap}</code>
                                </label>
                            `);
                        });

                        $('#dgptm-edit-role-modal').show();
                    }
                });
            });

            // Close Modal
            $('.dgptm-modal-close').on('click', function() {
                $('#dgptm-edit-role-modal').hide();
            });

            // Save Role Capabilities
            $('#dgptm-save-role-caps').on('click', function() {
                const roleSlug = $('#edit_role_slug').val();
                const capabilities = [];
                $('#dgptm-capabilities-list input:checked').each(function() {
                    capabilities.push($(this).val());
                });

                $.post(ajaxurl, {
                    action: 'dgptm_update_role_capabilities',
                    nonce: '<?php echo wp_create_nonce('dgptm_role_manager'); ?>',
                    role_slug: roleSlug,
                    capabilities: capabilities
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Capabilities gespeichert!', 'dgptm-role-manager'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Fehler:', 'dgptm-role-manager'); ?> ' + response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Create Role
     */
    public function ajax_create_role() {
        check_ajax_referer('dgptm_role_manager', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-role-manager')]);
        }

        $role_slug = sanitize_key($_POST['role_slug']);
        $role_name = sanitize_text_field($_POST['role_name']);
        $copy_from_role = sanitize_text_field($_POST['copy_from_role']);

        if (empty($role_slug) || empty($role_name)) {
            wp_send_json_error(['message' => __('Rolle-ID und Name sind erforderlich', 'dgptm-role-manager')]);
        }

        // Prüfe ob Rolle bereits existiert
        if (get_role($role_slug)) {
            wp_send_json_error(['message' => __('Eine Rolle mit dieser ID existiert bereits', 'dgptm-role-manager')]);
        }

        // Capabilities kopieren?
        $capabilities = [];
        if (!empty($copy_from_role)) {
            $source_role = get_role($copy_from_role);
            if ($source_role) {
                $capabilities = $source_role->capabilities;
            }
        }

        // Rolle erstellen
        $result = add_role($role_slug, $role_name, $capabilities);

        if ($result) {
            wp_send_json_success(['message' => __('Rolle erstellt', 'dgptm-role-manager')]);
        } else {
            wp_send_json_error(['message' => __('Fehler beim Erstellen der Rolle', 'dgptm-role-manager')]);
        }
    }

    /**
     * AJAX: Delete Role
     */
    public function ajax_delete_role() {
        check_ajax_referer('dgptm_role_manager', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-role-manager')]);
        }

        $role_slug = sanitize_key($_POST['role_slug']);

        // Verhindere Löschen von Standard-Rollen
        $default_roles = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];
        if (in_array($role_slug, $default_roles)) {
            wp_send_json_error(['message' => __('Standard-Rollen können nicht gelöscht werden', 'dgptm-role-manager')]);
        }

        remove_role($role_slug);
        wp_send_json_success(['message' => __('Rolle gelöscht', 'dgptm-role-manager')]);
    }

    /**
     * AJAX: Update Role Capabilities
     */
    public function ajax_update_role_capabilities() {
        check_ajax_referer('dgptm_role_manager', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-role-manager')]);
        }

        $role_slug = sanitize_key($_POST['role_slug']);
        $capabilities = isset($_POST['capabilities']) ? array_map('sanitize_text_field', $_POST['capabilities']) : [];

        $role = get_role($role_slug);
        if (!$role) {
            wp_send_json_error(['message' => __('Rolle nicht gefunden', 'dgptm-role-manager')]);
        }

        // Entferne alle aktuellen Capabilities
        foreach ($role->capabilities as $cap => $granted) {
            $role->remove_cap($cap);
        }

        // Füge neue Capabilities hinzu
        foreach ($capabilities as $cap) {
            $role->add_cap($cap);
        }

        wp_send_json_success(['message' => __('Capabilities aktualisiert', 'dgptm-role-manager')]);
    }

    /**
     * AJAX: Get Role Capabilities (helper for edit modal)
     */
    public function ajax_get_role_capabilities() {
        check_ajax_referer('dgptm_role_manager', 'nonce');

        $role_slug = sanitize_key($_POST['role_slug']);
        $role = get_role($role_slug);

        if (!$role) {
            wp_send_json_error(['message' => __('Rolle nicht gefunden', 'dgptm-role-manager')]);
        }

        $capabilities = array_keys($role->capabilities);
        wp_send_json_success(['capabilities' => $capabilities]);
    }
}
} // End class_exists check

// Initialisiere Plugin
if (!isset($GLOBALS['dgptm_suite_role_manager_initialized'])) {
    $GLOBALS['dgptm_suite_role_manager_initialized'] = true;
    DGPTM_Suite_Role_Manager::get_instance();
}
