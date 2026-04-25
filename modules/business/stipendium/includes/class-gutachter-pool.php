<?php
/**
 * DGPTM Stipendium — Gutachter-Stammdaten (Pool).
 *
 * Eigene Stammdaten-Verwaltung der Gutachter:innen mit Fachgebiet,
 * Mitgliedsstatus und internen Notizen. Ergaenzt die Token-Tabelle:
 * Tokens haben weiterhin Name/E-Mail; der Pool dient zum Vor-Auswaehlen
 * und Pflegen der Personen ohne sofortige Einladung.
 *
 * Berechtigung: Vorsitz oder manage_options.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Gutachter_Pool {

    const NONCE_ACTION = 'dgptm_stipendium_vorsitz_nonce';

    public function __construct() {
        add_action('wp_ajax_dgptm_stipendium_pool_list',   [$this, 'ajax_list']);
        add_action('wp_ajax_dgptm_stipendium_pool_save',   [$this, 'ajax_save']);
        add_action('wp_ajax_dgptm_stipendium_pool_delete', [$this, 'ajax_delete']);
    }

    /* ──────────────────────────────────────────
     * Public Helfer
     * ────────────────────────────────────────── */

    public static function table() {
        return DGPTM_Stipendium_Token_Installer::gutachter_table_name();
    }

    /**
     * Alle aktiven Gutachter:innen liefern (fuer Pool-Dropdown).
     */
    public static function list_active() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT id, name, email, fachgebiet, mitglied
             FROM " . self::table() . "
             WHERE aktiv = 1
             ORDER BY name ASC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Person nach E-Mail suchen.
     */
    public static function find_by_email($email) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . self::table() . " WHERE email = %s LIMIT 1", $email),
            ARRAY_A
        );
    }

    /**
     * Person anlegen oder aktualisieren (idempotent ueber E-Mail).
     */
    public static function upsert($data) {
        global $wpdb;
        $clean = self::sanitize($data);
        if (empty($clean['name']) || empty($clean['email'])) {
            return new WP_Error('missing', 'Name und E-Mail sind Pflicht.');
        }

        $existing = self::find_by_email($clean['email']);
        if ($existing) {
            $clean['updated_at'] = current_time('mysql');
            // Nur fehlende Felder ergaenzen, vorhandene nicht ueberschreiben
            $merge = [];
            if (empty($existing['name']) && !empty($clean['name'])) $merge['name'] = $clean['name'];
            if (empty($existing['fachgebiet']) && !empty($clean['fachgebiet'])) $merge['fachgebiet'] = $clean['fachgebiet'];
            if (!empty($merge)) {
                $merge['updated_at'] = current_time('mysql');
                $wpdb->update(self::table(), $merge, ['id' => $existing['id']]);
            }
            return (int) $existing['id'];
        }

        $clean['created_by'] = get_current_user_id();
        $clean['created_at'] = current_time('mysql');
        $clean['aktiv']      = 1;
        $ok = $wpdb->insert(self::table(), $clean);
        if ($ok === false) {
            return new WP_Error('db_error', 'Speichern fehlgeschlagen.');
        }
        return (int) $wpdb->insert_id;
    }

    /* ──────────────────────────────────────────
     * AJAX
     * ────────────────────────────────────────── */

    public function ajax_list() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!self::can_manage()) wp_send_json_error('Keine Berechtigung.', 403);

        global $wpdb;
        $search = sanitize_text_field($_POST['search'] ?? '');
        $sql = "SELECT * FROM " . self::table() . " WHERE 1=1";
        $args = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= " AND (name LIKE %s OR email LIKE %s OR fachgebiet LIKE %s)";
            array_push($args, $like, $like, $like);
        }
        $sql .= " ORDER BY name ASC LIMIT 200";

        $rows = $args
            ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        wp_send_json_success(['items' => $rows ?: []]);
    }

    public function ajax_save() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!self::can_manage()) wp_send_json_error('Keine Berechtigung.', 403);

        $id   = (int) ($_POST['id'] ?? 0);
        $data = self::sanitize([
            'name'       => $_POST['name']       ?? '',
            'email'      => $_POST['email']      ?? '',
            'fachgebiet' => $_POST['fachgebiet'] ?? '',
            'mitglied'   => $_POST['mitglied']   ?? 0,
            'notizen'    => $_POST['notizen']    ?? '',
            'aktiv'      => $_POST['aktiv']      ?? 1,
        ]);

        if (empty($data['name']) || empty($data['email'])) {
            wp_send_json_error('Name und E-Mail sind Pflichtfelder.');
        }
        if (!is_email($data['email'])) {
            wp_send_json_error('Ungueltige E-Mail-Adresse.');
        }

        global $wpdb;
        if ($id > 0) {
            $data['updated_at'] = current_time('mysql');
            $ok = $wpdb->update(self::table(), $data, ['id' => $id]);
            if ($ok === false) wp_send_json_error('Aktualisieren fehlgeschlagen.');
            wp_send_json_success(['id' => $id, 'message' => 'Gespeichert.']);
        }

        // Konflikt: existierende E-Mail
        $existing = self::find_by_email($data['email']);
        if ($existing) {
            wp_send_json_error('Diese E-Mail existiert bereits — bitte den Eintrag bearbeiten.');
        }

        $data['created_by'] = get_current_user_id();
        $data['created_at'] = current_time('mysql');
        $ok = $wpdb->insert(self::table(), $data);
        if ($ok === false) wp_send_json_error('Anlegen fehlgeschlagen.');
        wp_send_json_success(['id' => (int) $wpdb->insert_id, 'message' => 'Angelegt.']);
    }

    public function ajax_delete() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!self::can_manage()) wp_send_json_error('Keine Berechtigung.', 403);

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) wp_send_json_error('Ungueltige ID.');

        global $wpdb;
        // Soft-Delete: aktiv=0, statt komplett zu loeschen — Tokens behalten Bezug
        $ok = $wpdb->update(self::table(), ['aktiv' => 0, 'updated_at' => current_time('mysql')], ['id' => $id]);
        if ($ok === false) wp_send_json_error('Loeschen fehlgeschlagen.');
        wp_send_json_success(['message' => 'Eintrag deaktiviert.']);
    }

    /* ──────────────────────────────────────────
     * Helfer
     * ────────────────────────────────────────── */

    private static function sanitize($d) {
        return [
            'name'       => sanitize_text_field($d['name'] ?? ''),
            'email'      => sanitize_email($d['email'] ?? ''),
            'fachgebiet' => sanitize_text_field($d['fachgebiet'] ?? ''),
            'mitglied'   => !empty($d['mitglied']) ? 1 : 0,
            'notizen'    => sanitize_textarea_field($d['notizen'] ?? ''),
            'aktiv'      => isset($d['aktiv']) ? (!empty($d['aktiv']) ? 1 : 0) : 1,
        ];
    }

    private static function can_manage() {
        if (!is_user_logged_in()) return false;
        if (current_user_can('manage_options')) return true;
        if (class_exists('DGPTM_Stipendium_Dashboard_Tab')) {
            return DGPTM_Stipendium_Dashboard_Tab::user_has_flag(
                get_current_user_id(),
                'stipendiumsrat_vorsitz'
            );
        }
        return false;
    }
}
