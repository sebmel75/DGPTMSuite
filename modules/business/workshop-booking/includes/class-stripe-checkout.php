<?php
/**
 * Stripe-Checkout-Session-Erzeugung.
 *
 * Direkter HTTP-Call auf api.stripe.com (kein Stripe-PHP-SDK,
 * um keine Composer-Abhaengigkeit zu erzeugen).
 *
 * Konfiguration via WP-Optionen / Filter:
 *   - dgptm_wsb_stripe_secret_key       (filterbar)
 *   - dgptm_wsb_stripe_account_id       (Stripe-Account fuer Connected-Accounts/Unterkonten; leer = Hauptkonto)
 *   - dgptm_wsb_stripe_webhook_secret   (zur Signaturpruefung)
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Stripe_Checkout {

    const API_BASE = 'https://api.stripe.com/v1';

    public static function get_secret_key() {
        $key = get_option('dgptm_wsb_stripe_secret_key', '');
        return apply_filters('dgptm_wsb_stripe_secret_key', $key);
    }

    public static function get_account_id() {
        return apply_filters('dgptm_wsb_stripe_account_id', get_option('dgptm_wsb_stripe_account_id', ''));
    }

    public static function get_webhook_secret() {
        return apply_filters('dgptm_wsb_stripe_webhook_secret', get_option('dgptm_wsb_stripe_webhook_secret', ''));
    }

    /**
     * Erzeugt eine Checkout-Session in Stripe.
     *
     * @param array $event       DGfK_Events-Datensatz (mind. id, Name)
     * @param array $attendees   Liste mit ['first_name','last_name','price_eur',...]
     * @param array $contact_ids Liste der zugehoerigen Veranstal_X_Contacts-Zoho-IDs
     * @return array ['success' => bool, 'session_id' => ?, 'url' => ?, 'expires_at' => ?, 'error' => ?]
     */
    public static function create_session($event, array $attendees, array $contact_ids) {
        $key = self::get_secret_key();
        if (!$key) {
            return ['success' => false, 'error' => 'no_stripe_key'];
        }

        $line_items = [];
        foreach ($attendees as $i => $a) {
            $name  = sprintf(
                '%s — %s %s',
                isset($event['Name']) ? $event['Name'] : 'Workshop',
                isset($a['first_name']) ? $a['first_name'] : '',
                isset($a['last_name'])  ? $a['last_name']  : ''
            );
            $price = isset($a['price_eur']) ? (float) $a['price_eur'] : 0;

            $line_items["line_items[$i][price_data][currency]"]            = 'eur';
            $line_items["line_items[$i][price_data][product_data][name]"] = $name;
            $line_items["line_items[$i][price_data][unit_amount]"]         = (int) round($price * 100);
            $line_items["line_items[$i][quantity]"]                        = 1;
        }

        $params = array_merge([
            'mode'                    => 'payment',
            'payment_method_types[0]' => 'card',
            'payment_method_types[1]' => 'sepa_debit',
            'success_url'             => self::success_url(),
            'cancel_url'              => self::cancel_url(),
            'allow_promotion_codes'   => 'false',
            'metadata[event_id]'      => isset($event['id']) ? $event['id'] : '',
            'metadata[contact_ids]'   => implode(',', $contact_ids),
            'expires_at'              => time() + 30 * 60, // 30 Minuten
        ], $line_items);

        $headers = [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];
        $account = self::get_account_id();
        if ($account) {
            $headers['Stripe-Account'] = $account;
        }

        $resp = wp_remote_post(self::API_BASE . '/checkout/sessions', [
            'timeout' => 20,
            'headers' => $headers,
            'body'    => $params,
        ]);
        if (is_wp_error($resp)) {
            return ['success' => false, 'error' => $resp->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (wp_remote_retrieve_response_code($resp) !== 200) {
            return [
                'success' => false,
                'error'   => isset($body['error']['message']) ? $body['error']['message'] : 'stripe_error',
            ];
        }

        return [
            'success'    => true,
            'session_id' => $body['id'],
            'url'        => $body['url'],
            'expires_at' => date('Y-m-d H:i:s', $body['expires_at']),
        ];
    }

    public static function success_url() {
        $url = apply_filters(
            'dgptm_wsb_success_url',
            home_url('/buchung-bestaetigt/')
        );
        return add_query_arg(['dgptm_wsb' => 'success'], $url);
    }

    public static function cancel_url() {
        $url = apply_filters(
            'dgptm_wsb_cancel_url',
            home_url('/')
        );
        return add_query_arg(['dgptm_wsb' => 'cancelled'], $url);
    }
}
