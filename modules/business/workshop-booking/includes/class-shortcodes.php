<?php
/**
 * Shortcodes:
 *   [dgptm_workshops]         — Karten-Liste + Buchungs-Dialog
 *   [dgptm_workshops_success] — Bestaetigungsseite nach Stripe-Redirect
 *
 * AJAX:
 *   action=dgptm_wsb_book — POST-Endpoint fuer Buchungs-Submit
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Shortcodes {

    private static $instance = null;
    private $plugin_path;
    private $plugin_url;

    public static function get_instance($path = null, $url = null) {
        if (null === self::$instance) self::$instance = new self($path, $url);
        return self::$instance;
    }

    private function __construct($path, $url) {
        $this->plugin_path = $path;
        $this->plugin_url  = $url;

        add_shortcode('dgptm_workshops',         [$this, 'render_list']);
        add_shortcode('dgptm_workshops_success', [$this, 'render_success']);

        add_action('wp_enqueue_scripts',          [$this, 'register_assets']);
        add_action('wp_ajax_dgptm_wsb_book',        [$this, 'ajax_book']);
        add_action('wp_ajax_nopriv_dgptm_wsb_book', [$this, 'ajax_book']);
    }

    public function register_assets() {
        wp_register_style(
            'dgptm-wsb-frontend',
            $this->plugin_url . 'assets/css/frontend.css',
            [],
            '0.3.0'
        );
        wp_register_script(
            'dgptm-wsb-booking-form',
            $this->plugin_url . 'assets/js/booking-form.js',
            ['jquery'],
            '0.3.0',
            true
        );
    }

    public function render_list($atts) {
        wp_enqueue_style('dgptm-wsb-frontend');
        wp_enqueue_script('dgptm-wsb-booking-form');
        wp_localize_script('dgptm-wsb-booking-form', 'dgptmWsb', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('dgptm_wsb_book'),
        ]);

        $events = DGPTM_WSB_Event_Source::fetch_upcoming();
        ob_start();
        include $this->plugin_path . 'templates/workshops-list.php';
        return ob_get_clean();
    }

    public function render_success($atts) {
        wp_enqueue_style('dgptm-wsb-frontend');
        ob_start();
        include $this->plugin_path . 'templates/booking-success.php';
        return ob_get_clean();
    }

    public function ajax_book() {
        check_ajax_referer('dgptm_wsb_book', 'nonce');

        $event_id      = sanitize_text_field(wp_unslash($_POST['event_id'] ?? ''));
        $raw_attendees = json_decode(wp_unslash($_POST['attendees'] ?? '[]'), true);

        if (empty($event_id) || !is_array($raw_attendees) || empty($raw_attendees)) {
            wp_send_json_error('invalid_input');
        }

        $attendees = [];
        foreach ($raw_attendees as $a) {
            if (empty($a['first_name']) || empty($a['last_name'])
                || empty($a['email']) || !is_email($a['email'])) {
                wp_send_json_error('invalid_attendee');
            }
            $attendees[] = [
                'first_name'  => sanitize_text_field($a['first_name']),
                'last_name'   => sanitize_text_field($a['last_name']),
                'email'       => sanitize_email($a['email']),
                'ticket_type' => isset($a['ticket_type']) ? sanitize_text_field($a['ticket_type']) : '',
                'price_eur'   => isset($a['price_eur']) ? (float) $a['price_eur'] : 0,
            ];
        }

        $result = DGPTM_WSB_Booking_Service::book($event_id, $attendees);

        if ($result['result'] === DGPTM_WSB_Booking_Service::RESULT_CHECKOUT) {
            wp_send_json_success(['redirect_url' => $result['checkout_url']]);
        }
        if ($result['result'] === DGPTM_WSB_Booking_Service::RESULT_FREE) {
            $success_url = apply_filters(
                'dgptm_wsb_success_url',
                home_url('/buchung-bestaetigt/')
            );
            wp_send_json_success([
                'redirect_url' => add_query_arg(['dgptm_wsb' => 'success'], $success_url),
            ]);
        }
        wp_send_json_error(isset($result['error']) ? $result['error'] : $result['result']);
    }
}
