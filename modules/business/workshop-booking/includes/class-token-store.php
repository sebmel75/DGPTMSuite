<?php
/**
 * Token-Store: Erzeugung, Validierung, Widerruf von persoenlichen Zugangslinks.
 *
 * Scopes:
 *   - booking: Nicht-Mitglieder rufen Ticket ueber Token-Link auf;
 *              gueltig bis Workshop-Ende + 30 Tage (Spec offene Frage 8)
 *   - layout:  externe Designer:innen bearbeiten Bescheinigungs-Layout (Phase 4);
 *              gueltig 14 Tage, jederzeit widerrufbar (Spec EVL Vorschlag 21 + offene Frage 9)
 *
 * Token-Format: 48-Bit-Hex (cryptographically secure).
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Token_Store {

    const SCOPE_BOOKING = 'booking';
    const SCOPE_LAYOUT  = 'layout';

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_workshop_tokens';
    }

    /**
     * Erzeugt einen Booking-Token fuer einen Nicht-Mitglied-Zugang.
     *
     * @param string  $contact_id Veranstal_X_Contacts-Zoho-ID
     * @param string  $email
     * @param string  $event_end_date z.B. '2026-08-15' — Token gilt bis end + 30 Tage
     * @return string|null Token oder null bei Fehler
     */
    public static function create_booking_token($contact_id, $email, $event_end_date) {
        $expires_ts = strtotime($event_end_date . ' +30 days');
        if ($expires_ts === false) {
            $expires_ts = time() + 90 * 86400;
        }
        return self::create([
            'scope'                  => self::SCOPE_BOOKING,
            'veranstal_x_contact_id' => $contact_id,
            'email'                  => $email,
            'expires_at'             => date('Y-m-d H:i:s', $expires_ts),
        ]);
    }

    /**
     * Erzeugt einen Layout-Token fuer externe Designer:innen.
     *
     * @param string $email
     * @param string $event_id  optional: Token nur fuer ein bestimmtes Event-Layout
     * @param int    $invited_by WordPress-User-ID (Geschaeftsstelle)
     * @return string|null
     */
    public static function create_layout_token($email, $event_id, $invited_by) {
        return self::create([
            'scope'      => self::SCOPE_LAYOUT,
            'event_id'   => $event_id,
            'email'      => $email,
            'invited_by' => $invited_by,
            'expires_at' => date('Y-m-d H:i:s', time() + 14 * 86400),
        ]);
    }

    /**
     * Generischer Insert.
     */
    private static function create(array $data) {
        global $wpdb;
        $token = bin2hex(random_bytes(24)); // 48 Hex-Zeichen
        $row = array_merge([
            'token'      => $token,
            'created_at' => current_time('mysql'),
            'use_count'  => 0,
        ], $data);

        $ok = $wpdb->insert(self::table(), $row);
        return $ok ? $token : null;
    }

    /**
     * Liefert einen Token-Eintrag, wenn er gueltig ist (nicht widerrufen, nicht abgelaufen).
     *
     * @return array|null Zeile oder null
     */
    public static function find_valid($token, $scope = null) {
        if (empty($token)) return null;

        global $wpdb;
        $sql = "SELECT * FROM " . self::table() . " WHERE token = %s AND revoked_at IS NULL AND expires_at > %s";
        $params = [$token, current_time('mysql')];
        if ($scope) {
            $sql .= " AND scope = %s";
            $params[] = $scope;
        }
        $sql .= " LIMIT 1";

        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Markiert eine Token-Nutzung (use_count++, last_used_at).
     */
    public static function record_usage($token) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table() . "
             SET use_count = use_count + 1, last_used_at = %s
             WHERE token = %s",
            current_time('mysql'),
            $token
        ));
    }

    /**
     * Widerruft einen Token (sofortiger Zugriffs-Stopp).
     */
    public static function revoke($token) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            ['revoked_at' => current_time('mysql')],
            ['token' => $token]
        );
    }

    /**
     * Baut die oeffentliche URL fuer einen Booking-Token.
     *
     * Standard: /veranstaltungen/ticket/. Ueberschreibbar via Filter
     * 'dgptm_wsb_ticket_page_url' fuer abweichende Permalink-Strukturen.
     */
    public static function build_booking_url($token) {
        $base = apply_filters('dgptm_wsb_ticket_page_url', home_url('/veranstaltungen/ticket/'));
        return add_query_arg(['dgptm_wsb_token' => $token], $base);
    }

    public static function build_layout_url($token) {
        $base = apply_filters('dgptm_wsb_layout_page_url', home_url('/veranstaltungen/bescheinigungs-layout/'));
        return add_query_arg(['dgptm_wsb_layout_token' => $token], $base);
    }
}
