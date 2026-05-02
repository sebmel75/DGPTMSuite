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
        add_shortcode('dgptm_workshop_ticket',   [$this, 'render_token_ticket']); // Phase 2: Token-Zugang fuer Externe

        add_action('wp_enqueue_scripts',          [$this, 'register_assets']);
        add_action('wp_ajax_dgptm_wsb_book',        [$this, 'ajax_book']);
        add_action('wp_ajax_nopriv_dgptm_wsb_book', [$this, 'ajax_book']);
        add_action('init',                          [$this, 'maybe_handle_pdf_download']); // Phase 2: ?dgptm_wsb_pdf=<token>
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

    /**
     * [dgptm_workshop_ticket] — Token-basierter Ticket-Zugang fuer Nicht-Mitglieder.
     * URL: /mein-ticket/?dgptm_wsb_token=<token>
     */
    public function render_token_ticket($atts) {
        wp_enqueue_style('dgptm-wsb-frontend');

        $token = isset($_GET['dgptm_wsb_token']) ? sanitize_text_field(wp_unslash($_GET['dgptm_wsb_token'])) : '';
        if (empty($token)) {
            return '<div class="dgptm-wsb-token-error">Ungültiger oder fehlender Zugangslink.</div>';
        }

        $row = DGPTM_WSB_Token_Store::find_valid($token, DGPTM_WSB_Token_Store::SCOPE_BOOKING);
        if (!$row) {
            return '<div class="dgptm-wsb-token-error">Dieser Link ist abgelaufen oder wurde widerrufen. Bitte wende dich an die <a href="mailto:geschaeftsstelle@dgptm.de">Geschäftsstelle</a>.</div>';
        }

        DGPTM_WSB_Token_Store::record_usage($token);

        $contact = DGPTM_WSB_Veranstal_X_Contacts::fetch($row['veranstal_x_contact_id']);
        if (!$contact) {
            return '<div class="dgptm-wsb-token-error">Buchung konnte nicht geladen werden.</div>';
        }

        $ticket_number = isset($contact[DGPTM_WSB_Ticket_Number::FIELD_NAME]) ? $contact[DGPTM_WSB_Ticket_Number::FIELD_NAME] : '';
        $status        = isset($contact[DGPTM_WSB_Veranstal_X_Contacts::FIELD_BLUEPRINT]) ? $contact[DGPTM_WSB_Veranstal_X_Contacts::FIELD_BLUEPRINT] : '';
        $event_id      = isset($contact['Event_Name']['id']) ? $contact['Event_Name']['id'] : '';
        $event         = $event_id ? DGPTM_WSB_Event_Source::fetch_one($event_id) : null;
        $event_name    = is_array($event) && isset($event['Name']) ? $event['Name'] : '—';
        $event_from    = is_array($event) && isset($event['From_Date']) ? date_i18n('d.m.Y', strtotime($event['From_Date'])) : '';

        $pdf_url = add_query_arg(['dgptm_wsb_pdf' => $token], home_url('/mein-ticket/'));

        ob_start();
        ?>
        <div class="dgptm-wsb-token-ticket">
            <h2>Dein Ticket</h2>
            <p class="dgptm-wsb-token-event"><strong><?php echo esc_html($event_name); ?></strong>
                <?php if ($event_from) : ?> &mdash; <?php echo esc_html($event_from); endif; ?>
            </p>
            <?php if ($ticket_number) : ?>
                <div class="dgptm-wsb-token-number">
                    <span class="label">Ticketnummer</span>
                    <span class="value"><?php echo esc_html($ticket_number); ?></span>
                </div>
            <?php endif; ?>
            <p class="dgptm-wsb-token-status">Status: <strong><?php echo esc_html($status ?: 'unbekannt'); ?></strong></p>
            <?php if ($ticket_number) : ?>
                <p><a class="dgptm-wsb-token-pdf-btn" href="<?php echo esc_url($pdf_url); ?>">Ticket-PDF herunterladen</a></p>
            <?php endif; ?>
            <p class="dgptm-wsb-token-hint">Bei Fragen wende dich bitte an die <a href="mailto:geschaeftsstelle@dgptm.de">Geschäftsstelle</a>.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Behandelt Ticket-PDF-Download via Token-Link: ?dgptm_wsb_pdf=<token>.
     * Sendet PDF direkt aus, beendet Request.
     */
    public function maybe_handle_pdf_download() {
        if (empty($_GET['dgptm_wsb_pdf'])) return;

        $token = sanitize_text_field(wp_unslash($_GET['dgptm_wsb_pdf']));
        $row   = DGPTM_WSB_Token_Store::find_valid($token, DGPTM_WSB_Token_Store::SCOPE_BOOKING);
        if (!$row) {
            wp_die('Ungültiger oder abgelaufener Zugangslink.', 'Zugriff verweigert', ['response' => 403]);
        }
        DGPTM_WSB_Token_Store::record_usage($token);

        $contact = DGPTM_WSB_Veranstal_X_Contacts::fetch($row['veranstal_x_contact_id']);
        if (!$contact) {
            wp_die('Buchung nicht gefunden.', 'Fehler', ['response' => 404]);
        }
        $ticket_number = isset($contact[DGPTM_WSB_Ticket_Number::FIELD_NAME]) ? $contact[DGPTM_WSB_Ticket_Number::FIELD_NAME] : '';
        if (empty($ticket_number)) {
            wp_die('Für diese Buchung wurde noch keine Ticketnummer vergeben.', 'Ticket nicht verfügbar', ['response' => 404]);
        }

        $event_id = isset($contact['Event_Name']['id']) ? $contact['Event_Name']['id'] : '';
        $event    = $event_id ? DGPTM_WSB_Event_Source::fetch_one($event_id) : null;

        $pdf = DGPTM_WSB_Ticket_PDF::render([
            'ticket_number'  => $ticket_number,
            'first_name'     => isset($contact['Contact_Name']['First_Name']) ? $contact['Contact_Name']['First_Name'] : '',
            'last_name'      => isset($contact['Contact_Name']['Last_Name'])  ? $contact['Contact_Name']['Last_Name']  : '',
            'event_name'     => is_array($event) && isset($event['Name'])      ? $event['Name']      : 'Workshop',
            'event_from'     => is_array($event) && isset($event['From_Date']) ? date_i18n('d.m.Y', strtotime($event['From_Date'])) : '',
            'event_location' => is_array($event) && isset($event['Location'])  ? $event['Location']  : '',
            'event_type'     => is_array($event) && isset($event['Event_Type']) ? $event['Event_Type'] : 'Workshop',
        ]);

        if (!$pdf) {
            wp_die('PDF konnte nicht erzeugt werden. Bitte wende dich an die Geschäftsstelle.', 'Fehler', ['response' => 500]);
        }

        $filename = 'DGPTM-Ticket-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $ticket_number) . '.pdf';
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
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
