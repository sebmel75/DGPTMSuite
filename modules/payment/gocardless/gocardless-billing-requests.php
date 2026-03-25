<?php
/**
 * GoCardless Billing Requests — Bankdaten ändern via Hosted Flow
 *
 * Shortcode [gcl_formidable_new]: Mandat-Status, Bankverbindung ändern,
 * neues Mandat einrichten, Mandat kündigen — alles via Billing Requests API.
 *
 * @package DGPTM\GoCardless
 * @since   2.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'DGPTM_GCL_BR_SETTINGS', 'dgptm_gcl_br_settings' );

function dgptm_gcl_br_default_settings() {
    return [
        'redirect_url' => 'https://perfusiologie.de/mitgliedschaft/interner-bereich/#tab-profil',
        'exit_url'     => 'https://perfusiologie.de/mitgliedschaft/interner-bereich/',
    ];
}

function dgptm_gcl_br_get_settings() {
    return wp_parse_args(
        get_option( DGPTM_GCL_BR_SETTINGS, [] ),
        dgptm_gcl_br_default_settings()
    );
}

/**
 * Liest den GoCardless API Token aus den bestehenden Settings.
 */
function dgptm_gcl_br_get_token() {
    $opts = get_option( 'gocardless_settings', [] );
    return trim( $opts['gocardless_api_token'] ?? '' );
}

/**
 * GoCardless API Helper mit Fehlerbehandlung.
 *
 * @param string      $method   HTTP-Methode (GET, POST, PUT)
 * @param string      $endpoint API-Endpunkt (ohne Base-URL)
 * @param array|null  $body     Request-Body (wird JSON-encoded)
 * @return array|WP_Error  Parsed JSON oder WP_Error
 */
function dgptm_gcl_br_api( $method, $endpoint, $body = null ) {
    $token = dgptm_gcl_br_get_token();
    if ( ! $token ) {
        return new WP_Error( 'gcl_no_token', 'GoCardless API-Token nicht konfiguriert.' );
    }

    $args = [
        'method'  => $method,
        'headers' => [
            'Authorization'      => 'Bearer ' . $token,
            'GoCardless-Version' => '2015-07-06',
            'Accept'             => 'application/json',
        ],
        'timeout' => 30,
    ];

    if ( $body !== null ) {
        $args['headers']['Content-Type'] = 'application/json';
        $args['body'] = is_array( $body ) ? wp_json_encode( $body ) : $body;
    }

    $url = 'https://api.gocardless.com/' . ltrim( $endpoint, '/' );
    $res = wp_remote_request( $url, $args );

    if ( is_wp_error( $res ) ) {
        return new WP_Error( 'gcl_http_error', 'GoCardless HTTP-Fehler: ' . $res->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $res );
    $data = json_decode( wp_remote_retrieve_body( $res ), true );

    if ( $code < 200 || $code >= 300 ) {
        $msg = $data['error']['message'] ?? "HTTP {$code}";
        return new WP_Error( 'gcl_api_error', $msg );
    }

    return $data;
}

/**
 * Ermittelt die GoCardless Customer-ID für den aktuellen User (serverseitig).
 */
function dgptm_gcl_br_get_customer_id() {
    // Primär: Zoho CRM Shortcode (scoped auf aktuellen User)
    $cid = trim( do_shortcode( '[zoho_api_data field="GoCardlessID"]' ) );
    if ( $cid ) return $cid;

    // Fallback: User-Meta
    $cid = get_user_meta( get_current_user_id(), 'goCardlessPayment', true );
    return trim( (string) $cid );
}

/**
 * Ermittelt das aktive Mandat + Bankkonto für einen Customer (serverseitig).
 *
 * @param string $customer_id GoCardless Customer-ID
 * @return array|null  ['mandate_id', 'mandate_ref', 'bank_account_id', 'account_ending', 'bank_name', 'holder_name', 'status']
 */
function dgptm_gcl_br_get_active_mandate( $customer_id ) {
    if ( ! $customer_id ) return null;

    // Aktive und pending Mandate laden
    $data = dgptm_gcl_br_api( 'GET', "mandates?customer={$customer_id}&status=active" );
    if ( is_wp_error( $data ) ) return null;

    $mandates = $data['mandates'] ?? [];

    // Auch pending_submission prüfen
    if ( empty( $mandates ) ) {
        $data2 = dgptm_gcl_br_api( 'GET', "mandates?customer={$customer_id}&status=pending_submission" );
        if ( ! is_wp_error( $data2 ) ) {
            $mandates = $data2['mandates'] ?? [];
        }
    }

    if ( empty( $mandates ) ) return null;

    // Neuestes Mandat nehmen
    usort( $mandates, function( $a, $b ) {
        return strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' );
    });
    $m = $mandates[0];

    $bank_account_id = $m['links']['customer_bank_account'] ?? '';
    $account_ending = '';
    $bank_name = '';
    $holder_name = '';

    if ( $bank_account_id ) {
        $ba = dgptm_gcl_br_api( 'GET', "customer_bank_accounts/{$bank_account_id}" );
        if ( ! is_wp_error( $ba ) ) {
            $acc = $ba['customer_bank_accounts'] ?? [];
            $account_ending = $acc['account_number_ending'] ?? '';
            $bank_name      = $acc['bank_name'] ?? '';
            $holder_name    = $acc['account_holder_name'] ?? '';
        }
    }

    return [
        'mandate_id'      => $m['id'],
        'mandate_ref'     => $m['reference'] ?? '',
        'bank_account_id' => $bank_account_id,
        'account_ending'  => $account_ending,
        'bank_name'       => $bank_name,
        'holder_name'     => $holder_name,
        'status'          => $m['status'] ?? 'unknown',
    ];
}

/* ============================================================
 * AJAX-Handler
 * ============================================================ */

// --- Status laden ---
add_action( 'wp_ajax_dgptm_gcl_load_status', function() {
    check_ajax_referer( 'dgptm_gcl_nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
    nocache_headers();

    $customer_id = dgptm_gcl_br_get_customer_id();
    if ( ! $customer_id ) {
        wp_send_json_success( [ 'status' => 'no_customer', 'html' => '<p>Kein GoCardless-Kundenkonto vorhanden. Bitte kontaktieren Sie die Geschäftsstelle.</p>' ] );
    }

    $mandate = dgptm_gcl_br_get_active_mandate( $customer_id );

    // CRM-Sync: Mandat-ID prüfen und ggf. aktualisieren
    if ( $mandate ) {
        $zoho_id = get_user_meta( get_current_user_id(), 'zoho_id', true );
        if ( $zoho_id && class_exists( 'DGPTM_Zoho_Plugin' ) ) {
            $stored_mandate = get_user_meta( get_current_user_id(), '_gcl_mandate_id', true );
            if ( $stored_mandate !== $mandate['mandate_id'] ) {
                $zoho = DGPTM_Zoho_Plugin::get_instance();
                $oauth = $zoho->get_oauth_token();
                if ( ! is_wp_error( $oauth ) ) {
                    wp_remote_request( "https://www.zohoapis.eu/crm/v7/Contacts/{$zoho_id}", [
                        'method'  => 'PUT',
                        'timeout' => 15,
                        'headers' => [
                            'Authorization' => 'Zoho-oauthtoken ' . $oauth,
                            'Content-Type'  => 'application/json',
                        ],
                        'body' => wp_json_encode( [ 'data' => [[ 'MandatID' => $mandate['mandate_id'] ]] ] ),
                    ]);
                }
                update_user_meta( get_current_user_id(), '_gcl_mandate_id', $mandate['mandate_id'] );
            }
        }
    }

    if ( ! $mandate ) {
        wp_send_json_success( [
            'status' => 'no_mandate',
            'html'   => '<p><strong>Kein aktives Mandat vorhanden.</strong></p>'
                      . '<p><button class="button button-primary" id="dgptm-gcl-new-btn">Neues Mandat einrichten</button></p>',
        ]);
    }

    $status_label = $mandate['status'] === 'active' ? '● Aktiv' : '◐ Ausstehend';
    $html = '<div class="dgptm-gcl-info">'
          . '<div><strong>Status:</strong> ' . esc_html( $status_label ) . '</div>'
          . '<div><strong>Konto:</strong> ••••' . esc_html( $mandate['account_ending'] ) . '</div>'
          . '<div><strong>Inhaber:</strong> ' . esc_html( $mandate['holder_name'] ) . '</div>'
          . '<div><strong>Bank:</strong> ' . esc_html( $mandate['bank_name'] ) . '</div>'
          . '<div><strong>Mandat:</strong> ' . esc_html( $mandate['mandate_ref'] ) . '</div>'
          . '</div>'
          . '<p style="margin-top:12px;">'
          . '<button class="button button-secondary" id="dgptm-gcl-change-btn">Bankverbindung ändern</button> '
          . '<button class="button" id="dgptm-gcl-cancel-btn" style="color:#d63638;">Mandat kündigen</button>'
          . '</p>';

    wp_send_json_success( [ 'status' => 'active', 'html' => $html ] );
});

// --- Bankverbindung ändern ---
add_action( 'wp_ajax_dgptm_gcl_change_bank', function() {
    check_ajax_referer( 'dgptm_gcl_nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
    nocache_headers();

    $customer_id = dgptm_gcl_br_get_customer_id();
    if ( ! $customer_id ) wp_send_json_error( [ 'message' => 'Kein GoCardless-Kundenkonto.' ] );

    $mandate = dgptm_gcl_br_get_active_mandate( $customer_id );

    // Schritt 1-2: Altes Mandat kündigen + Konto deaktivieren (idempotent)
    if ( $mandate ) {
        // Cancel (ignoriert Fehler bei bereits cancelled)
        dgptm_gcl_br_api( 'POST', "mandates/{$mandate['mandate_id']}/actions/cancel", '{}' );

        // Disable nur wenn noch aktiv
        if ( $mandate['bank_account_id'] ) {
            $ba = dgptm_gcl_br_api( 'GET', "customer_bank_accounts/{$mandate['bank_account_id']}" );
            if ( ! is_wp_error( $ba ) && ! empty( $ba['customer_bank_accounts']['enabled'] ) ) {
                dgptm_gcl_br_api( 'POST', "customer_bank_accounts/{$mandate['bank_account_id']}/actions/disable", '{}' );
            }
        }
    }

    // Schritt 3: Billing Request erstellen
    $br = dgptm_gcl_br_api( 'POST', 'billing_requests', [
        'billing_requests' => [
            'mandate_request' => [ 'scheme' => 'sepa_core' ],
            'links' => [ 'customer' => $customer_id ],
        ],
    ]);

    if ( is_wp_error( $br ) ) {
        wp_send_json_error( [ 'message' => 'Billing Request fehlgeschlagen: ' . $br->get_error_message() ] );
    }

    $br_id = $br['billing_requests']['id'] ?? '';
    if ( ! $br_id ) wp_send_json_error( [ 'message' => 'Billing Request ID fehlt.' ] );

    // Schritt 4: Billing Request Flow
    $settings = dgptm_gcl_br_get_settings();
    $flow = dgptm_gcl_br_api( 'POST', 'billing_request_flows', [
        'billing_request_flows' => [
            'redirect_uri'          => $settings['redirect_url'],
            'exit_uri'              => $settings['exit_url'],
            'lock_customer_details' => true,
            'links' => [ 'billing_request' => $br_id ],
        ],
    ]);

    if ( is_wp_error( $flow ) ) {
        wp_send_json_error( [ 'message' => 'Billing Request Flow fehlgeschlagen: ' . $flow->get_error_message() ] );
    }

    $auth_url = $flow['billing_request_flows']['authorisation_url'] ?? '';
    if ( ! $auth_url ) wp_send_json_error( [ 'message' => 'Keine Autorisierungs-URL erhalten.' ] );

    // Lokalen Mandat-Cache leeren
    delete_user_meta( get_current_user_id(), '_gcl_mandate_id' );

    wp_send_json_success( [ 'redirect' => $auth_url ] );
});

// --- Neues Mandat einrichten ---
add_action( 'wp_ajax_dgptm_gcl_new_mandate', function() {
    check_ajax_referer( 'dgptm_gcl_nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
    nocache_headers();

    $customer_id = dgptm_gcl_br_get_customer_id();
    if ( ! $customer_id ) wp_send_json_error( [ 'message' => 'Kein GoCardless-Kundenkonto. Bitte Geschäftsstelle kontaktieren.' ] );

    // Direkt Billing Request (kein Cancel nötig)
    $br = dgptm_gcl_br_api( 'POST', 'billing_requests', [
        'billing_requests' => [
            'mandate_request' => [ 'scheme' => 'sepa_core' ],
            'links' => [ 'customer' => $customer_id ],
        ],
    ]);

    if ( is_wp_error( $br ) ) {
        wp_send_json_error( [ 'message' => 'Billing Request fehlgeschlagen: ' . $br->get_error_message() ] );
    }

    $br_id = $br['billing_requests']['id'] ?? '';
    if ( ! $br_id ) wp_send_json_error( [ 'message' => 'Billing Request ID fehlt.' ] );

    $settings = dgptm_gcl_br_get_settings();
    $flow = dgptm_gcl_br_api( 'POST', 'billing_request_flows', [
        'billing_request_flows' => [
            'redirect_uri'          => $settings['redirect_url'],
            'exit_uri'              => $settings['exit_url'],
            'lock_customer_details' => true,
            'links' => [ 'billing_request' => $br_id ],
        ],
    ]);

    if ( is_wp_error( $flow ) ) {
        wp_send_json_error( [ 'message' => 'Flow fehlgeschlagen: ' . $flow->get_error_message() ] );
    }

    $auth_url = $flow['billing_request_flows']['authorisation_url'] ?? '';
    if ( ! $auth_url ) wp_send_json_error( [ 'message' => 'Keine Autorisierungs-URL erhalten.' ] );

    wp_send_json_success( [ 'redirect' => $auth_url ] );
});

// --- Mandat kündigen ---
add_action( 'wp_ajax_dgptm_gcl_cancel_mandate', function() {
    check_ajax_referer( 'dgptm_gcl_nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
    nocache_headers();

    $customer_id = dgptm_gcl_br_get_customer_id();
    if ( ! $customer_id ) wp_send_json_error( [ 'message' => 'Kein GoCardless-Kundenkonto.' ] );

    $mandate = dgptm_gcl_br_get_active_mandate( $customer_id );
    if ( ! $mandate ) wp_send_json_error( [ 'message' => 'Kein aktives Mandat gefunden.' ] );

    // Cancel Mandat (idempotent)
    $cancel = dgptm_gcl_br_api( 'POST', "mandates/{$mandate['mandate_id']}/actions/cancel", '{}' );
    if ( is_wp_error( $cancel ) && strpos( $cancel->get_error_message(), 'already cancelled' ) === false ) {
        wp_send_json_error( [ 'message' => 'Kündigung fehlgeschlagen: ' . $cancel->get_error_message() ] );
    }

    // Disable Bankkonto
    if ( $mandate['bank_account_id'] ) {
        $ba = dgptm_gcl_br_api( 'GET', "customer_bank_accounts/{$mandate['bank_account_id']}" );
        if ( ! is_wp_error( $ba ) && ! empty( $ba['customer_bank_accounts']['enabled'] ) ) {
            dgptm_gcl_br_api( 'POST', "customer_bank_accounts/{$mandate['bank_account_id']}/actions/disable", '{}' );
        }
    }

    delete_user_meta( get_current_user_id(), '_gcl_mandate_id' );

    wp_send_json_success( [ 'message' => 'Mandat gekündigt.' ] );
});

/* ============================================================
 * Shortcode [gcl_formidable_new]
 * ============================================================ */

add_action( 'init', function() {
    add_shortcode( 'gcl_formidable_new', 'dgptm_gcl_br_render_shortcode' );
});

function dgptm_gcl_br_render_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>Bitte melden Sie sich an.</p>';
    }

    $token = dgptm_gcl_br_get_token();
    if ( ! $token ) {
        return '<div class="dgptm-gcl-widget"><p>GoCardless nicht konfiguriert.</p></div>';
    }

    ob_start();
    ?>
    <div class="dgptm-gcl-widget" id="dgptm-gcl-widget">
        <h4>SEPA-Lastschriftmandat</h4>
        <div id="dgptm-gcl-status">
            <p class="dgptm-gcl-loading">Mandat-Status wird geladen…</p>
        </div>
        <div id="dgptm-gcl-message" style="display:none;margin-top:10px;padding:10px;border-radius:4px;"></div>
    </div>
    <style>
    .dgptm-gcl-widget { max-width:500px; }
    .dgptm-gcl-info { padding:10px; border:1px solid #ddd; border-radius:6px; background:#fafafa; font-size:14px; line-height:1.8; }
    .dgptm-gcl-info div { margin-bottom:2px; }
    .dgptm-gcl-loading { color:#666; font-style:italic; }
    .dgptm-gcl-msg-success { background:#e7f5e7; border:1px solid #46b450; color:#1a7a1a; }
    .dgptm-gcl-msg-error { background:#fef3e7; border:1px solid #d63638; color:#d63638; }
    </style>
    <script>
    jQuery(function($){
        var gcl = {
            ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
            nonce: <?php echo wp_json_encode( wp_create_nonce( 'dgptm_gcl_nonce' ) ); ?>
        };

        function loadStatus() {
            var $s = $('#dgptm-gcl-status');
            if (!$s.length) return;
            $s.html('<p class="dgptm-gcl-loading">Mandat-Status wird geladen…</p>');
            $.post(gcl.ajaxUrl, { action:'dgptm_gcl_load_status', _wpnonce:gcl.nonce }, function(r){
                if (r.success) { $s.html(r.data.html); }
                else { $s.html('<p style="color:red;">'+(r.data?.message||'Fehler')+'</p>'); }
            }).fail(function(){ $s.html('<p style="color:red;">Verbindungsfehler.</p>'); });
        }

        function showMsg(text, type) {
            var $m = $('#dgptm-gcl-message');
            $m.html(text).removeClass('dgptm-gcl-msg-success dgptm-gcl-msg-error')
              .addClass(type === 'success' ? 'dgptm-gcl-msg-success' : 'dgptm-gcl-msg-error').show();
            setTimeout(function(){ $m.fadeOut(); }, 6000);
        }

        // Bankverbindung ändern
        $(document).on('click', '#dgptm-gcl-change-btn', function(e){
            e.preventDefault();
            if (!confirm('Bankverbindung ändern? Das aktuelle Mandat wird gekündigt und Sie werden zu GoCardless weitergeleitet.')) return;
            var $btn = $(this).prop('disabled',true).text('Bitte warten…');
            $.post(gcl.ajaxUrl, { action:'dgptm_gcl_change_bank', _wpnonce:gcl.nonce }, function(r){
                if (r.success && r.data.redirect) { window.location.href = r.data.redirect; }
                else { showMsg(r.data?.message||'Fehler', 'error'); $btn.prop('disabled',false).text('Bankverbindung ändern'); }
            }).fail(function(){ showMsg('Verbindungsfehler.', 'error'); $btn.prop('disabled',false).text('Bankverbindung ändern'); });
        });

        // Neues Mandat
        $(document).on('click', '#dgptm-gcl-new-btn', function(e){
            e.preventDefault();
            var $btn = $(this).prop('disabled',true).text('Bitte warten…');
            $.post(gcl.ajaxUrl, { action:'dgptm_gcl_new_mandate', _wpnonce:gcl.nonce }, function(r){
                if (r.success && r.data.redirect) { window.location.href = r.data.redirect; }
                else { showMsg(r.data?.message||'Fehler', 'error'); $btn.prop('disabled',false).text('Neues Mandat einrichten'); }
            }).fail(function(){ showMsg('Verbindungsfehler.', 'error'); $btn.prop('disabled',false).text('Neues Mandat einrichten'); });
        });

        // Mandat kündigen
        $(document).on('click', '#dgptm-gcl-cancel-btn', function(e){
            e.preventDefault();
            if (!confirm('Mandat wirklich kündigen? Zukünftige Lastschriften sind dann nicht mehr möglich.')) return;
            var $btn = $(this).prop('disabled',true).text('Wird gekündigt…');
            $.post(gcl.ajaxUrl, { action:'dgptm_gcl_cancel_mandate', _wpnonce:gcl.nonce }, function(r){
                if (r.success) { showMsg('Mandat erfolgreich gekündigt.', 'success'); loadStatus(); }
                else { showMsg(r.data?.message||'Fehler', 'error'); $btn.prop('disabled',false).text('Mandat kündigen'); }
            }).fail(function(){ showMsg('Verbindungsfehler.', 'error'); $btn.prop('disabled',false).text('Mandat kündigen'); });
        });

        // Init + Dashboard Re-Init
        loadStatus();
        $(document).on('dgptm_tab_loaded', function(){ loadStatus(); });
    });
    </script>
    <?php
    return ob_get_clean();
}

/* ============================================================
 * Admin-Settings (eigene Sektion unter GoCardless)
 * ============================================================ */

add_action( 'admin_init', function() {
    register_setting( 'gcl_settings_group', DGPTM_GCL_BR_SETTINGS, [
        'sanitize_callback' => function( $input ) {
            return [
                'redirect_url' => esc_url_raw( $input['redirect_url'] ?? '' ),
                'exit_url'     => esc_url_raw( $input['exit_url'] ?? '' ),
            ];
        },
    ]);

    add_settings_section(
        'gcl_br_section',
        'Billing Requests (Bankdaten ändern)',
        function() { echo '<p>Einstellungen für den Hosted Flow zur Bankdaten-Änderung.</p>'; },
        'gcl-settings'
    );

    $s = dgptm_gcl_br_get_settings();

    add_settings_field( 'gcl_br_redirect', 'Redirect URL', function() use ($s) {
        echo '<input type="url" name="' . DGPTM_GCL_BR_SETTINGS . '[redirect_url]" value="' . esc_attr($s['redirect_url']) . '" style="width:60%">';
        echo '<p class="description">Wohin der Benutzer nach erfolgreichem GoCardless-Flow zurückkehrt.</p>';
    }, 'gcl-settings', 'gcl_br_section' );

    add_settings_field( 'gcl_br_exit', 'Exit URL', function() use ($s) {
        echo '<input type="url" name="' . DGPTM_GCL_BR_SETTINGS . '[exit_url]" value="' . esc_attr($s['exit_url']) . '" style="width:60%">';
        echo '<p class="description">Wohin der Benutzer geht wenn er den Flow abbricht.</p>';
    }, 'gcl-settings', 'gcl_br_section' );
});
