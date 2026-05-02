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
     * Demo-Token (read-only): zeigt Beispiel-Ticket ohne CRM-Lookup.
     * Wird zu Demonstrations-Zwecken auf Production verwendet.
     * Kein DB-Eintrag, kein record_usage(), keine PII.
     */
    const DEMO_TOKEN = 'dgptm-demo-ticket-2026';

    /**
     * Liefert Demo-Ticket-Daten (Erika Mustermann, ECMO-Refresher).
     * Identisch zum Beispiel im Musterticket der Entscheidungsvorlage.
     */
    private static function demo_ticket_data() {
        return [
            'is_demo'        => true,
            'ticket_number'  => '999990000000000042',
            'first_name'     => 'Erika',
            'last_name'      => 'Mustermann',
            'event_name'     => 'Workshop ECMO/ECLS Refresher',
            'event_from'     => '15.10.2026',
            'event_location' => 'DGPTM-Geschäftsstelle, Hannover',
            'status'         => 'Angemeldet',
        ];
    }

    /**
     * [dgptm_workshop_ticket] — Token-basierter Ticket-Zugang fuer Nicht-Mitglieder.
     * URL: /veranstaltungen/ticket/?dgptm_wsb_token=<token>
     *
     * Demo-Modus: Token = self::DEMO_TOKEN → zeigt Beispiel-Daten ohne CRM-Lookup.
     */
    public function render_token_ticket($atts) {
        wp_enqueue_style('dgptm-wsb-frontend');

        $token = isset($_GET['dgptm_wsb_token']) ? sanitize_text_field(wp_unslash($_GET['dgptm_wsb_token'])) : '';
        if (empty($token)) {
            return '<div class="dgptm-wsb-token-error">Ungültiger oder fehlender Zugangslink.</div>';
        }

        // Demo-Modus
        if ($token === self::DEMO_TOKEN) {
            $demo = self::demo_ticket_data();
            $ticket_number = $demo['ticket_number'];
            $status        = $demo['status'];
            $event_name    = $demo['event_name'];
            $event_from    = $demo['event_from'];
            $is_demo       = true;
        } else {
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
            $is_demo       = false;
        }

        $pdf_url = add_query_arg(['dgptm_wsb_pdf' => $token], DGPTM_WSB_Token_Store::build_booking_url($token));
        // Token-Param doppelt vermeiden: build_booking_url setzt schon dgptm_wsb_token,
        // wir ueberschreiben es bewusst mit dgptm_wsb_pdf — beide Pfade erwartet die Seite.
        $pdf_url = remove_query_arg('dgptm_wsb_token', $pdf_url);

        // Echter QR-Code wenn endroid verfuegbar; sonst SVG-Pseudo-QR (Musterticket-Stil)
        $qr_data_uri = $ticket_number ? DGPTM_WSB_QR_Generator::as_data_uri($ticket_number, 320) : null;

        // Zusatzfelder fuer Demo-Modus (werden im Layout angezeigt)
        $first_name = !empty($is_demo) ? 'Erika' : '';
        $last_name  = !empty($is_demo) ? 'Mustermann' : '';
        $event_loc  = !empty($is_demo) ? 'DGPTM-Geschäftsstelle, Hannover' : '';
        $logo_url   = apply_filters('dgptm_wsb_ticket_logo_url', DGPTM_WSB_Ticket_PDF::LOGO_URL);

        ob_start();
        ?>
        <?php if (!empty($is_demo)) : ?>
            <div class="dgptm-wsb-demo-banner">
                <strong>Demo-Vorschau:</strong> Dieses Ticket dient nur zur Anschauung. Die Daten sind frei erfunden.
            </div>
        <?php endif; ?>

        <div class="dgptm-wsb-ticket-card">
            <div class="dgptm-wsb-ticket-header">
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="vertical-align:middle;">
                            <div class="dgptm-wsb-ticket-title">Veranstaltungsticket</div>
                            <div class="dgptm-wsb-ticket-subtitle">Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V.</div>
                        </td>
                        <td style="vertical-align:middle;text-align:right;width:160px;">
                            <img src="<?php echo esc_url($logo_url); ?>" alt="DGPTM" class="dgptm-wsb-ticket-logo">
                        </td>
                    </tr>
                </table>
            </div>

            <div class="dgptm-wsb-ticket-body">
                <div class="dgptm-wsb-ticket-data">
                    <?php if ($event_name) : ?>
                        <div class="dgptm-wsb-ticket-field">
                            <div class="dgptm-wsb-ticket-field-label">Veranstaltung</div>
                            <div class="dgptm-wsb-ticket-field-value"><?php echo esc_html($event_name); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($event_from) : ?>
                        <div class="dgptm-wsb-ticket-field">
                            <div class="dgptm-wsb-ticket-field-label">Termin</div>
                            <div class="dgptm-wsb-ticket-field-value"><?php echo esc_html($event_from); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($event_loc) : ?>
                        <div class="dgptm-wsb-ticket-field">
                            <div class="dgptm-wsb-ticket-field-label">Ort</div>
                            <div class="dgptm-wsb-ticket-field-value"><?php echo esc_html($event_loc); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($first_name || $last_name) : ?>
                        <div class="dgptm-wsb-ticket-field">
                            <div class="dgptm-wsb-ticket-field-label">Teilnehmer:in</div>
                            <div class="dgptm-wsb-ticket-field-value"><?php echo esc_html(trim($first_name . ' ' . $last_name)); ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="dgptm-wsb-ticket-field">
                        <div class="dgptm-wsb-ticket-field-label">Status</div>
                        <div class="dgptm-wsb-ticket-field-value"><?php echo esc_html($status ?: 'unbekannt'); ?></div>
                    </div>
                    <?php if ($ticket_number) : ?>
                        <div class="dgptm-wsb-ticket-number-box">
                            <div class="dgptm-wsb-ticket-number-label">Ticketnummer</div>
                            <div class="dgptm-wsb-ticket-number-value"><?php echo esc_html($ticket_number); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="dgptm-wsb-ticket-qr">
                    <?php if ($qr_data_uri) : ?>
                        <img src="<?php echo esc_attr($qr_data_uri); ?>" alt="QR-Code Ticket <?php echo esc_attr($ticket_number); ?>" class="dgptm-wsb-ticket-qr-img">
                    <?php else : ?>
                        <?php // Fallback: dekoratives SVG-Muster wenn endroid noch nicht installiert ?>
                        <svg width="160" height="160" viewBox="0 0 25 25" xmlns="http://www.w3.org/2000/svg" class="dgptm-wsb-ticket-qr-img" style="background:#ffffff;">
                            <rect width="25" height="25" fill="#ffffff"/>
                            <g fill="#000000">
                                <rect x="0" y="0" width="7" height="7"/><rect x="1" y="1" width="5" height="5" fill="#ffffff"/><rect x="2" y="2" width="3" height="3"/>
                                <rect x="18" y="0" width="7" height="7"/><rect x="19" y="1" width="5" height="5" fill="#ffffff"/><rect x="20" y="2" width="3" height="3"/>
                                <rect x="0" y="18" width="7" height="7"/><rect x="1" y="19" width="5" height="5" fill="#ffffff"/><rect x="2" y="20" width="3" height="3"/>
                                <rect x="8" y="0" width="1" height="1"/><rect x="10" y="0" width="1" height="1"/><rect x="12" y="0" width="1" height="1"/><rect x="14" y="0" width="1" height="1"/><rect x="16" y="0" width="1" height="1"/>
                                <rect x="8" y="2" width="1" height="1"/><rect x="11" y="2" width="1" height="1"/><rect x="13" y="2" width="1" height="1"/><rect x="15" y="2" width="1" height="1"/>
                                <rect x="9" y="4" width="1" height="1"/><rect x="11" y="4" width="1" height="1"/><rect x="14" y="4" width="1" height="1"/><rect x="16" y="4" width="1" height="1"/>
                                <rect x="8" y="6" width="1" height="1"/><rect x="10" y="6" width="1" height="1"/><rect x="12" y="6" width="1" height="1"/><rect x="13" y="6" width="1" height="1"/><rect x="15" y="6" width="1" height="1"/><rect x="17" y="6" width="1" height="1"/>
                                <rect x="0" y="8" width="1" height="1"/><rect x="2" y="8" width="1" height="1"/><rect x="4" y="8" width="1" height="1"/><rect x="9" y="8" width="1" height="1"/><rect x="12" y="8" width="1" height="1"/><rect x="14" y="8" width="1" height="1"/><rect x="16" y="8" width="1" height="1"/><rect x="19" y="8" width="1" height="1"/><rect x="21" y="8" width="1" height="1"/><rect x="23" y="8" width="1" height="1"/>
                                <rect x="1" y="10" width="1" height="1"/><rect x="3" y="10" width="1" height="1"/><rect x="6" y="10" width="1" height="1"/><rect x="8" y="10" width="1" height="1"/><rect x="11" y="10" width="1" height="1"/><rect x="13" y="10" width="1" height="1"/><rect x="15" y="10" width="1" height="1"/><rect x="18" y="10" width="1" height="1"/><rect x="20" y="10" width="1" height="1"/><rect x="22" y="10" width="1" height="1"/>
                                <rect x="0" y="12" width="1" height="1"/><rect x="2" y="12" width="1" height="1"/><rect x="5" y="12" width="1" height="1"/><rect x="9" y="12" width="1" height="1"/><rect x="12" y="12" width="1" height="1"/><rect x="14" y="12" width="1" height="1"/><rect x="17" y="12" width="1" height="1"/><rect x="19" y="12" width="1" height="1"/><rect x="21" y="12" width="1" height="1"/><rect x="24" y="12" width="1" height="1"/>
                                <rect x="1" y="14" width="1" height="1"/><rect x="4" y="14" width="1" height="1"/><rect x="7" y="14" width="1" height="1"/><rect x="10" y="14" width="1" height="1"/><rect x="13" y="14" width="1" height="1"/><rect x="16" y="14" width="1" height="1"/><rect x="18" y="14" width="1" height="1"/><rect x="20" y="14" width="1" height="1"/><rect x="23" y="14" width="1" height="1"/>
                                <rect x="2" y="16" width="1" height="1"/><rect x="5" y="16" width="1" height="1"/><rect x="8" y="16" width="1" height="1"/><rect x="11" y="16" width="1" height="1"/><rect x="14" y="16" width="1" height="1"/><rect x="15" y="16" width="1" height="1"/><rect x="17" y="16" width="1" height="1"/><rect x="19" y="16" width="1" height="1"/><rect x="22" y="16" width="1" height="1"/>
                                <rect x="8" y="18" width="1" height="1"/><rect x="10" y="18" width="1" height="1"/><rect x="12" y="18" width="1" height="1"/><rect x="14" y="18" width="1" height="1"/><rect x="16" y="18" width="1" height="1"/>
                                <rect x="9" y="20" width="1" height="1"/><rect x="11" y="20" width="1" height="1"/><rect x="13" y="20" width="1" height="1"/><rect x="15" y="20" width="1" height="1"/><rect x="17" y="20" width="1" height="1"/>
                                <rect x="8" y="22" width="1" height="1"/><rect x="11" y="22" width="1" height="1"/><rect x="14" y="22" width="1" height="1"/><rect x="16" y="22" width="1" height="1"/>
                                <rect x="10" y="24" width="1" height="1"/><rect x="12" y="24" width="1" height="1"/><rect x="15" y="24" width="1" height="1"/>
                            </g>
                        </svg>
                        <p style="font-size:11px;color:#9ca3af;margin-top:4px;font-style:italic;">QR-Engine noch nicht installiert</p>
                    <?php endif; ?>
                    <div class="dgptm-wsb-ticket-qr-caption">QR-Code für Einlass-Scan</div>
                </div>
            </div>

            <div class="dgptm-wsb-ticket-footer">
                Bitte beim Einlass auf dem Smartphone zeigen oder ausgedruckt vorlegen
            </div>
        </div>

        <?php if ($ticket_number) : ?>
            <p class="dgptm-wsb-ticket-actions">
                <a class="dgptm-wsb-token-pdf-btn" href="<?php echo esc_url($pdf_url); ?>">Ticket-PDF herunterladen</a>
            </p>
        <?php endif; ?>
        <p class="dgptm-wsb-token-hint">Bei Fragen wende dich bitte an die <a href="mailto:geschaeftsstelle@dgptm.de">Geschäftsstelle</a>.</p>
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

        // Demo-Modus: rendert Beispiel-PDF ohne CRM-Lookup
        if ($token === self::DEMO_TOKEN) {
            $demo = self::demo_ticket_data();
            $pdf = DGPTM_WSB_Ticket_PDF::render([
                'ticket_number'  => $demo['ticket_number'],
                'first_name'     => $demo['first_name'],
                'last_name'      => $demo['last_name'],
                'event_name'     => $demo['event_name'],
                'event_from'     => $demo['event_from'],
                'event_location' => $demo['event_location'],
                'event_type'     => 'Workshop',
            ]);
            if (!$pdf) {
                wp_die('PDF-Engine nicht verfuegbar (composer install fehlt?).', 'Fehler', ['response' => 500]);
            }
            $filename = 'DGPTM-Veranstaltungsticket-Demo.pdf';
            nocache_headers();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            exit;
        }

        $row = DGPTM_WSB_Token_Store::find_valid($token, DGPTM_WSB_Token_Store::SCOPE_BOOKING);
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
