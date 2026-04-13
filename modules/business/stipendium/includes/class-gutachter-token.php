<?php
/**
 * DGPTM Stipendium — Gutachter-Token Manager.
 *
 * Verwaltet Token-Lifecycle: Generierung, Validierung, Entwurf-Speicherung,
 * Abschluss-Markierung und Aufraeum-Cron.
 *
 * Sicherheit: random_bytes(32) fuer Token-Generierung, hash_equals() fuer
 * timing-attack-resistente Validierung.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Gutachter_Token {

    const CLEANUP_HOOK = 'dgptm_stipendium_token_cleanup';

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dgptm_stipendium_tokens';

        // Cron fuer abgelaufene Tokens
        add_action(self::CLEANUP_HOOK, [$this, 'cleanup_expired']);

        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CLEANUP_HOOK);
        }
    }

    /**
     * Neuen Token generieren und in DB speichern.
     *
     * @param string $stipendium_id  Zoho CRM Record-ID der Bewerbung
     * @param string $gutachter_name Name des Gutachters
     * @param string $gutachter_email E-Mail des Gutachters
     * @param int    $frist_tage     Gueltigkeitsdauer in Tagen (Default 28)
     * @return array|WP_Error        Token-Daten oder Fehler
     */
    public function generate($stipendium_id, $gutachter_name, $gutachter_email, $frist_tage = 28) {
        global $wpdb;

        if (empty($stipendium_id) || empty($gutachter_name) || empty($gutachter_email)) {
            return new WP_Error('missing_data', 'Stipendium-ID, Name und E-Mail sind Pflichtfelder.');
        }

        // Pruefen ob bereits ein aktiver Token fuer diese Kombination existiert
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, token FROM {$this->table}
             WHERE stipendium_id = %s AND gutachter_email = %s
             AND bewertung_status != 'abgeschlossen' AND expires_at > NOW()",
            $stipendium_id,
            $gutachter_email
        ), ARRAY_A);

        if ($existing) {
            return new WP_Error(
                'token_exists',
                'Fuer diese/n Gutachter/in existiert bereits ein aktiver Token.',
                ['token_id' => $existing['id']]
            );
        }

        // Kryptographisch sicheren Token generieren
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$frist_tage} days"));

        $result = $wpdb->insert($this->table, [
            'token'           => $token,
            'stipendium_id'   => sanitize_text_field($stipendium_id),
            'gutachter_name'  => sanitize_text_field($gutachter_name),
            'gutachter_email' => sanitize_email($gutachter_email),
            'bewertung_status' => 'ausstehend',
            'created_by'      => get_current_user_id(),
            'created_at'      => current_time('mysql'),
            'expires_at'      => $expires_at,
        ], ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);

        if ($result === false) {
            return new WP_Error('db_error', 'Token konnte nicht erstellt werden.');
        }

        return [
            'id'         => $wpdb->insert_id,
            'token'      => $token,
            'expires_at' => $expires_at,
            'url'        => $this->get_gutachten_url($token),
        ];
    }

    /**
     * Token validieren (timing-attack-sicher).
     *
     * @param string $token Token-String aus URL
     * @return array|WP_Error Token-Daten oder Fehler
     */
    public function validate($token) {
        global $wpdb;

        // Laenge pruefen (64 Hex-Zeichen = 32 Bytes)
        if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
            return new WP_Error('invalid_token', 'Ungueltiges Token-Format.');
        }

        // Token aus DB laden
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE token = %s LIMIT 1",
            $token
        ), ARRAY_A);

        if (!$row) {
            return new WP_Error('token_not_found', 'Dieser Link ist nicht gueltig.');
        }

        // Timing-attack-sichere Vergleich
        if (!hash_equals($row['token'], $token)) {
            return new WP_Error('token_mismatch', 'Dieser Link ist nicht gueltig.');
        }

        // Ablauf pruefen
        if (strtotime($row['expires_at']) < time()) {
            return new WP_Error('token_expired', 'Dieser Link ist abgelaufen.');
        }

        return $row;
    }

    /**
     * Token anhand des Token-Strings abrufen (ohne Validierung).
     *
     * @param string $token Token-String
     * @return array|null Token-Daten oder null
     */
    public function get_by_token($token) {
        global $wpdb;

        if (empty($token) || strlen($token) !== 64) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE token = %s LIMIT 1",
            $token
        ), ARRAY_A);
    }

    /**
     * Entwurf speichern (Auto-Save).
     *
     * @param string $token Token-String
     * @param array  $data  Bewertungsdaten (Noten + Kommentare)
     * @return true|WP_Error
     */
    public function save_draft($token, $data) {
        global $wpdb;

        $row = $this->validate($token);
        if (is_wp_error($row)) {
            return $row;
        }

        if ($row['bewertung_status'] === 'abgeschlossen') {
            return new WP_Error('already_completed', 'Diese Bewertung wurde bereits abgeschlossen.');
        }

        $result = $wpdb->update(
            $this->table,
            [
                'bewertung_data'   => wp_json_encode($data),
                'bewertung_status' => 'entwurf',
            ],
            ['id' => $row['id']],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Entwurf konnte nicht gespeichert werden.');
        }

        return true;
    }

    /**
     * Bewertung als abgeschlossen markieren.
     *
     * @param string $token  Token-String
     * @param array  $data   Finale Bewertungsdaten
     * @param string $crm_id Zoho CRM Bewertungs-Record ID (nach Erstellung)
     * @return true|WP_Error
     */
    public function mark_complete($token, $data, $crm_id = '') {
        global $wpdb;

        $row = $this->validate($token);
        if (is_wp_error($row)) {
            return $row;
        }

        if ($row['bewertung_status'] === 'abgeschlossen') {
            return new WP_Error('already_completed', 'Diese Bewertung wurde bereits abgeschlossen.');
        }

        $result = $wpdb->update(
            $this->table,
            [
                'bewertung_data'   => wp_json_encode($data),
                'bewertung_status' => 'abgeschlossen',
                'bewertung_crm_id' => sanitize_text_field($crm_id),
                'completed_at'     => current_time('mysql'),
            ],
            ['id' => $row['id']],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Bewertung konnte nicht abgeschlossen werden.');
        }

        return true;
    }

    /**
     * Alle Tokens fuer ein Stipendium abrufen.
     *
     * @param string $stipendium_id Zoho CRM Record-ID
     * @return array Token-Zeilen
     */
    public function get_by_stipendium($stipendium_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE stipendium_id = %s ORDER BY created_at DESC",
            $stipendium_id
        ), ARRAY_A) ?: [];
    }

    /**
     * Anzahl abgeschlossener Bewertungen fuer ein Stipendium.
     *
     * @param string $stipendium_id Zoho CRM Record-ID
     * @return int
     */
    public function count_completed($stipendium_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE stipendium_id = %s AND bewertung_status = 'abgeschlossen'",
            $stipendium_id
        ));
    }

    /**
     * Anzahl aller Tokens (gesamt) fuer ein Stipendium.
     *
     * @param string $stipendium_id Zoho CRM Record-ID
     * @return int
     */
    public function count_total($stipendium_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE stipendium_id = %s",
            $stipendium_id
        ));
    }

    /**
     * Gutachten-URL zusammenbauen.
     *
     * @param string $token Token-String
     * @return string Vollstaendige URL
     */
    public function get_gutachten_url($token) {
        // Seite mit Shortcode [dgptm_stipendium_gutachten] suchen
        $page_id = $this->find_gutachten_page();
        if ($page_id) {
            return add_query_arg('token', $token, get_permalink($page_id));
        }
        // Fallback: statischer Pfad
        return home_url('/karriere/stipendien/gutachten/?token=' . $token);
    }

    /**
     * WordPress-Seite mit dem Gutachten-Shortcode finden.
     *
     * @return int|null Post-ID oder null
     */
    private function find_gutachten_page() {
        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page' AND post_status = 'publish'
             AND post_content LIKE '%[dgptm_stipendium_gutachten%'
             LIMIT 1"
        );
        return $page_id ? (int) $page_id : null;
    }

    /**
     * Abgelaufene Tokens aufraumen (Cron).
     *
     * Loescht Tokens die seit 30 Tagen abgelaufen sind
     * und nicht abgeschlossen wurden.
     */
    public function cleanup_expired() {
        global $wpdb;

        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table}
             WHERE expires_at < %s AND bewertung_status != 'abgeschlossen'",
            $cutoff
        ));

        if ($deleted > 0 && function_exists('dgptm_log')) {
            dgptm_log("Stipendium Token Cleanup: {$deleted} abgelaufene Tokens geloescht.", 'stipendium');
        }

        return $deleted;
    }

    /**
     * Cron-Event deregistrieren (bei Deaktivierung).
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled(self::CLEANUP_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CLEANUP_HOOK);
        }
    }
}
