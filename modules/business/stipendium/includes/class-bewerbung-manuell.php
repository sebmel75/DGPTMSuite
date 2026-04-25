<?php
/**
 * DGPTM Stipendium — Manuell eingepflegte Bewerbungen.
 *
 * Erlaubt Vorsitzendem/Geschaeftsstelle, Bewerbungen, die per E-Mail oder
 * auf Papier eingegangen sind, fuer den digitalen Bewertungslauf
 * direkt im Frontend einzupflegen — ohne Online-Bewerbungsformular.
 *
 * Speicherung: lokal in wp_dgptm_stipendium_manual.
 * IDs im Format MAN_<8hex>; sind kompatibel mit dem Token-Manager
 * und dem Bewertungsbogen.
 *
 * Berechtigung: Stipendiumsrat-Vorsitz oder manage_options.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Bewerbung_Manuell {

    const NONCE_ACTION = 'dgptm_stipendium_vorsitz_nonce';

    public function __construct() {
        add_action('wp_ajax_dgptm_stipendium_manual_create', [$this, 'ajax_create']);
        add_action('wp_ajax_dgptm_stipendium_manual_update', [$this, 'ajax_update']);
        add_action('wp_ajax_dgptm_stipendium_manual_delete', [$this, 'ajax_delete']);
        add_action('wp_ajax_dgptm_stipendium_manual_get',    [$this, 'ajax_get']);
    }

    /* ──────────────────────────────────────────
     * Public CRUD-Helfer (auch fuer Vorsitz-Dashboard)
     * ────────────────────────────────────────── */

    /**
     * Manuelle Bewerbungen einer Runde laden.
     *
     * @param string      $runde Runden-Bezeichnung (z.B. "Ausschreibung 2026")
     * @param string|null $typ   Stipendientyp oder null fuer alle
     * @return array Liste der Bewerbungen (im Stipendien-Format fuer das Dashboard)
     */
    public static function list_by_runde($runde, $typ = null) {
        global $wpdb;
        $table = DGPTM_Stipendium_Token_Installer::manual_table_name();

        $sql = "SELECT * FROM {$table} WHERE runde = %s";
        $args = [$runde];

        if (!empty($typ)) {
            $sql .= " AND stipendientyp = %s";
            $args[] = $typ;
        }

        $sql .= " ORDER BY created_at DESC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];

        $result = [];
        foreach ($rows as $row) {
            $result[] = self::row_to_stipendium($row);
        }
        return $result;
    }

    /**
     * Einzelne manuelle Bewerbung im Stipendien-Format laden.
     *
     * @param string $id MAN_*-ID
     * @return array|null Stipendien-Daten oder null
     */
    public static function get_by_id($id) {
        $row = self::get_row($id);
        if (!$row) return null;
        return self::row_to_stipendium($row);
    }

    /**
     * Status einer manuellen Bewerbung aktualisieren.
     *
     * @param string $id     MAN_*-ID
     * @param array  $fields Zu aktualisierende Felder
     * @return bool
     */
    public static function update_fields($id, $fields) {
        global $wpdb;
        $table = DGPTM_Stipendium_Token_Installer::manual_table_name();

        $clean = self::sanitize_fields($fields, false);
        if (empty($clean)) return false;

        $clean['updated_at'] = current_time('mysql');

        $result = $wpdb->update($table, $clean, ['id' => $id]);
        return $result !== false;
    }

    /* ──────────────────────────────────────────
     * AJAX: Bewerbung anlegen
     * ────────────────────────────────────────── */

    public function ajax_create() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!self::current_user_can_manage()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $raw = wp_unslash($_POST['data'] ?? '');
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            wp_send_json_error('Ungueltige Daten.');
        }

        $clean = self::sanitize_fields($data, true);

        if (empty($clean['runde']) || empty($clean['stipendientyp']) || empty($clean['bewerber_name'])) {
            wp_send_json_error('Runde, Stipendientyp und Bewerber-Name sind Pflichtfelder.');
        }

        $clean['id']            = self::generate_id();
        $clean['created_by']    = get_current_user_id();
        $clean['created_at']    = current_time('mysql');
        $clean['eingangsdatum'] = $clean['eingangsdatum'] ?: current_time('Y-m-d');
        if (empty($clean['status'])) {
            $clean['status'] = 'Freigegeben';
        }

        global $wpdb;
        $table = DGPTM_Stipendium_Token_Installer::manual_table_name();

        $inserted = $wpdb->insert($table, $clean);
        if ($inserted === false) {
            wp_send_json_error('Bewerbung konnte nicht gespeichert werden.');
        }

        wp_send_json_success([
            'message' => 'Bewerbung wurde angelegt.',
            'id'      => $clean['id'],
            'data'    => self::row_to_stipendium(self::get_row($clean['id'])),
        ]);
    }

    /* ──────────────────────────────────────────
     * AJAX: Bewerbung aktualisieren
     * ────────────────────────────────────────── */

    public function ajax_update() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!self::current_user_can_manage()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $id  = sanitize_text_field($_POST['id'] ?? '');
        $raw = wp_unslash($_POST['data'] ?? '');
        $data = is_string($raw) ? json_decode($raw, true) : $raw;

        if (empty($id) || strpos($id, 'MAN_') !== 0) {
            wp_send_json_error('Ungueltige Bewerbungs-ID.');
        }
        if (!is_array($data)) {
            wp_send_json_error('Ungueltige Daten.');
        }

        $existing = self::get_row($id);
        if (!$existing) {
            wp_send_json_error('Bewerbung nicht gefunden.');
        }

        $ok = self::update_fields($id, $data);
        if (!$ok) {
            wp_send_json_error('Aktualisierung fehlgeschlagen.');
        }

        wp_send_json_success([
            'message' => 'Bewerbung wurde aktualisiert.',
            'data'    => self::row_to_stipendium(self::get_row($id)),
        ]);
    }

    /* ──────────────────────────────────────────
     * AJAX: Bewerbung loeschen
     * ────────────────────────────────────────── */

    public function ajax_delete() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!self::current_user_can_manage()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $id = sanitize_text_field($_POST['id'] ?? '');
        if (empty($id) || strpos($id, 'MAN_') !== 0) {
            wp_send_json_error('Ungueltige Bewerbungs-ID.');
        }

        global $wpdb;
        $table = DGPTM_Stipendium_Token_Installer::manual_table_name();

        // Zugehoerige Tokens mit loeschen (kaskadiert)
        $token_table = DGPTM_Stipendium_Token_Installer::table_name();
        $wpdb->delete($token_table, ['stipendium_id' => $id]);

        $deleted = $wpdb->delete($table, ['id' => $id]);
        if ($deleted === false) {
            wp_send_json_error('Loeschen fehlgeschlagen.');
        }

        wp_send_json_success(['message' => 'Bewerbung geloescht.']);
    }

    /* ──────────────────────────────────────────
     * AJAX: Einzelne Bewerbung laden (zum Editieren)
     * ────────────────────────────────────────── */

    public function ajax_get() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!self::current_user_can_manage()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $id = sanitize_text_field($_POST['id'] ?? '');
        $row = self::get_row($id);
        if (!$row) {
            wp_send_json_error('Bewerbung nicht gefunden.');
        }

        // Rohdaten + Stipendien-Format zurueck
        $row['dokument_urls_decoded'] = self::decode_dokumente($row['dokument_urls'] ?? '');
        wp_send_json_success(['raw' => $row, 'data' => self::row_to_stipendium($row)]);
    }

    /* ──────────────────────────────────────────
     * Interne Helfer
     * ────────────────────────────────────────── */

    /**
     * MAN_-ID generieren (Kollisions-sicher).
     */
    private static function generate_id() {
        global $wpdb;
        $table = DGPTM_Stipendium_Token_Installer::manual_table_name();
        $attempt = 0;
        do {
            $candidate = 'MAN_' . strtoupper(bin2hex(random_bytes(4)));
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %s", $candidate));
            $attempt++;
        } while ($exists && $attempt < 5);
        return $candidate;
    }

    /**
     * Roh-Datensatz aus DB laden.
     */
    private static function get_row($id) {
        global $wpdb;
        $table = DGPTM_Stipendium_Token_Installer::manual_table_name();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %s", $id),
            ARRAY_A
        );
    }

    /**
     * Felder fuer DB sanitizen.
     *
     * @param array $data    Rohdaten aus AJAX
     * @param bool  $is_create true beim Insert (alle Felder), false beim Update (Whitelist)
     */
    private static function sanitize_fields($data, $is_create = true) {
        $clean = [];

        if (isset($data['runde']))             $clean['runde']             = sanitize_text_field($data['runde']);
        if (isset($data['stipendientyp']))     $clean['stipendientyp']     = sanitize_text_field($data['stipendientyp']);
        if (isset($data['status'])) {
            $erlaubt = ['Geprueft', 'Freigegeben', 'In Bewertung', 'Abgeschlossen', 'Abgelehnt', 'Archiviert'];
            $clean['status'] = in_array($data['status'], $erlaubt, true) ? $data['status'] : 'Freigegeben';
        }
        if (isset($data['bewerber_name']))        $clean['bewerber_name']        = sanitize_text_field($data['bewerber_name']);
        if (isset($data['bewerber_email']))       $clean['bewerber_email']       = sanitize_email($data['bewerber_email']);
        if (isset($data['bewerber_orcid'])) {
            $orcid = preg_replace('/[^0-9X-]/', '', strtoupper($data['bewerber_orcid']));
            $clean['bewerber_orcid'] = preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid) ? $orcid : '';
        }
        if (isset($data['bewerber_institution'])) $clean['bewerber_institution'] = sanitize_text_field($data['bewerber_institution']);
        if (isset($data['projekt_titel']))        $clean['projekt_titel']        = sanitize_textarea_field($data['projekt_titel']);
        if (isset($data['projekt_zusammenfassung'])) $clean['projekt_zusammenfassung'] = sanitize_textarea_field($data['projekt_zusammenfassung']);
        if (isset($data['projekt_methodik']))     $clean['projekt_methodik']     = sanitize_textarea_field($data['projekt_methodik']);

        // Dokument-URLs als JSON ablegen
        if (isset($data['dokument_urls'])) {
            $dokumente = is_array($data['dokument_urls']) ? $data['dokument_urls'] : [];
            $sauber = [];
            foreach ($dokumente as $key => $url) {
                $url = esc_url_raw(trim((string)$url));
                if (!empty($url)) {
                    $sauber[sanitize_key($key)] = $url;
                }
            }
            $clean['dokument_urls'] = wp_json_encode($sauber);
        }

        if (isset($data['eingangsdatum'])) {
            $clean['eingangsdatum'] = self::sanitize_date($data['eingangsdatum']);
        }
        if (isset($data['freigabedatum'])) {
            $clean['freigabedatum'] = self::sanitize_date($data['freigabedatum']);
        }
        if (isset($data['vergeben'])) {
            $clean['vergeben'] = !empty($data['vergeben']) ? 1 : 0;
        }
        if (isset($data['vergabedatum'])) {
            $clean['vergabedatum'] = self::sanitize_date($data['vergabedatum']);
        }
        if (isset($data['bemerkung'])) {
            $clean['bemerkung'] = sanitize_textarea_field($data['bemerkung']);
        }

        return $clean;
    }

    private static function sanitize_date($value) {
        $value = sanitize_text_field($value);
        if (empty($value)) return null;
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * Datenbank-Zeile in das von Vorsitz-Dashboard und Gutachter-Form
     * erwartete Stipendien-Format umwandeln.
     */
    public static function row_to_stipendium($row) {
        if (!$row) return null;

        $dokumente = self::decode_dokumente($row['dokument_urls'] ?? '');

        $stipendium = [
            'id'                       => $row['id'],
            'Name'                     => $row['bewerber_name'],
            'Bewerber'                 => ['name' => $row['bewerber_name']],
            'Stipendientyp'            => $row['stipendientyp'],
            'Runde'                    => $row['runde'],
            'Stipendium_Status'        => $row['status'],
            'Status'                   => $row['status'],
            'Eingangsdatum'            => $row['eingangsdatum'],
            'Freigabedatum'            => $row['freigabedatum'],
            'Vergeben'                 => !empty($row['vergeben']),
            'Vergabedatum'             => $row['vergabedatum'],
            'bewerber_email'           => $row['bewerber_email'],
            'bewerber_orcid'           => $row['bewerber_orcid'],
            'bewerber_institution'     => $row['bewerber_institution'],
            'projekt_titel'            => $row['projekt_titel'],
            'projekt_zusammenfassung'  => $row['projekt_zusammenfassung'],
            'projekt_methodik'         => $row['projekt_methodik'],
            'bemerkung'                => $row['bemerkung'] ?? '',
            'is_manual'                => true,
        ];

        $feld_map = [
            'lebenslauf'           => 'Lebenslauf_URL',
            'motivationsschreiben' => 'Motivationsschreiben_URL',
            'expose'               => 'Expose_URL',
            'empfehlungsschreiben' => 'Empfehlungsschreiben_URL',
            'studienleistungen'    => 'Studienleistungen_URL',
            'publikationen'        => 'Publikationen_URL',
            'zusatzqualifikationen'=> 'Zusatzqualifikationen_URL',
        ];
        foreach ($feld_map as $key => $api_key) {
            if (!empty($dokumente[$key])) {
                $stipendium[$api_key] = $dokumente[$key];
            }
        }

        return $stipendium;
    }

    private static function decode_dokumente($json) {
        if (empty($json)) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Berechtigungspruefung: Vorsitz oder manage_options.
     * Geschaeftsstelle kann mit acf:stipendiumsrat_mitglied freigegeben werden,
     * wenn das gewuenscht ist — aktuell konservativ auf Vorsitz beschraenkt.
     */
    private static function current_user_can_manage() {
        if (!is_user_logged_in()) return false;
        if (current_user_can('manage_options')) return true;
        if (class_exists('DGPTM_Stipendium_Dashboard_Tab')) {
            $uid = get_current_user_id();
            return DGPTM_Stipendium_Dashboard_Tab::user_has_flag($uid, 'stipendiumsrat_vorsitz')
                || DGPTM_Stipendium_Dashboard_Tab::user_has_flag($uid, 'stipendiumsrat_mitglied');
        }
        return false;
    }
}
