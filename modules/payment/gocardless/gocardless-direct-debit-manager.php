<?php
/**
 * Plugin Name: GoCardless Formidable Integration
 * Description: Integriert GoCardless-Lastschriftmandate in Formidable Form ID 12.  
 * Version:     1.20 – Filtert nur aktive Konten für old_account_id und überspringt Disable, wenn bereits deaktiviert.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GCL_Formidable_Integration {
    private $opt_name = 'gocardless_settings';
    private $form_id  = 12;

    public function __construct() {
        add_action( 'admin_menu',         [ $this, 'admin_page'        ] );
        add_action( 'admin_init',         [ $this, 'register_settings' ] );
        add_action( 'init',               [ $this, 'init_hooks'        ] );
    }

    /***** Admin Settings *****/
    public function admin_page() {
        add_options_page( 'GoCardless', 'GoCardless', 'manage_options', 'gcl-settings', [ $this, 'page_html' ] );
    }

    public function page_html() {
        ?>
        <div class="wrap">
          <h1>GoCardless Einstellungen</h1>
          <form method="post" action="options.php">
            <?php
            settings_fields( 'gcl_settings_group' );
            do_settings_sections( 'gcl-settings' );
            submit_button();
            ?>
          </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting( 'gcl_settings_group', $this->opt_name );
        add_settings_section( 'gcl_api', 'API-Einstellungen', null, 'gcl-settings' );
        add_settings_field( 'gc_token', 'API Access Token', [ $this, 'field_token' ], 'gcl-settings', 'gcl_api' );
    }

    public function field_token() {
        $opts = get_option( $this->opt_name, [] );
        $val  = esc_attr( trim( $opts['gocardless_api_token'] ?? '' ) );
        echo "<input type='text' name='{$this->opt_name}[gocardless_api_token]' value='$val' style='width:60%' />";
    }

    /***** Init Hooks *****/
    public function init_hooks() {
        add_shortcode(      'gcl_formidable',          [ $this, 'render_section'     ] );
        add_filter(         'frm_get_default_value',   [ $this, 'set_default_value' ], 10, 3 );
        add_action(         'frm_after_create_entry',  [ $this, 'handle_submission'  ], 20, 2 );
        add_filter(         'frm_show_success_message',[ $this, 'show_api_response'  ], 20, 2 );
    }

    /** Debug-Logger */
    private function log( $msg ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[GCL] " . print_r( $msg, true ) );
        }
    }

    /***** GoCardless API Helper *****/
    private function gcl_api( $method, $endpoint, $token, $body = null ) {
        $this->log( "GC API REQUEST → $method $endpoint • " . json_encode( $body ) );
        $args = [
            'method'    => $method,
            'headers'   => [
                'Authorization'      => "Bearer $token",
                'GoCardless-Version' => '2015-07-06',
                'Accept'             => 'application/json',
            ],
            'timeout'   => 20,
        ];
        if ( $body !== null ) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body']                    = is_array( $body ) ? wp_json_encode( $body ) : $body;
        }

        $res = wp_remote_request( "https://api.gocardless.com/$endpoint", $args );

        if ( is_wp_error( $res ) ) {
            $this->log( "GC API ERROR → " . $res->get_error_message() );
        } else {
            $code = wp_remote_retrieve_response_code( $res );
            $body = wp_remote_retrieve_body( $res );
            $this->log( "GC API RESPONSE → HTTP $code • $body" );
        }

        return $res;
    }

    /***** Anzeige bestehendes Mandat (kompakt) *****/
    public function render_section() {
        $opts  = get_option( $this->opt_name, [] );
        $token = trim( $opts['gocardless_api_token'] ?? '' );
        $html  = '<div class="gcl-section">';
        if ( ! $token ) {
            $html .= '<p><strong>Fehler:</strong> API-Token fehlt.</p>';
        } else {
            $cust = trim( do_shortcode( '[zoho_api_data field="GoCardlessID"]' ) );
            if ( $cust ) {
                $r = $this->gcl_api( 'GET', "customer_bank_accounts?customer=$cust", $token );
                if ( ! is_wp_error( $r ) ) {
                    $accs   = json_decode( wp_remote_retrieve_body( $r ), true )['customer_bank_accounts'] ?? [];
                    $active = array_filter( $accs, fn( $a ) => ! empty( $a['enabled'] ) );
                    if ( $active ) {
                        $a     = array_shift( $active );
                        $html .= "<div class='gcl-bank-info' style='font-size:0.9em;padding:8px;border:1px solid #ddd;border-radius:4px;'>"
                               . "<div><strong>Inhaber:</strong> "      . esc_html( $a['account_holder_name'] ) . "</div>"
                               . "<div><strong>IBAN endet:</strong> …"    . esc_html( $a['account_number_ending'] ) . "</div>"
                               . "<div><strong>Bank:</strong> "         . esc_html( $a['bank_name'] ) . "</div>";

                        // Mandats-Details
                        $rm = $this->gcl_api( 'GET', "mandates?customer_bank_account={$a['id']}", $token );
                        if ( ! is_wp_error( $rm ) ) {
                            $mands = json_decode( wp_remote_retrieve_body( $rm ), true )['mandates'] ?? [];
                            if ( $mands ) {
                                $m          = reset( $mands );
                                $ref        = esc_html( $m['reference'] ?? '' );
                                $creditorId = esc_html( $m['links']['creditor'] ?? '' );
                                $html      .= "<div style='margin-top:6px;'><small>"
                                            . "<strong>Mandats-Ref:</strong> $ref | "
                                            . "<strong>Gläubiger-ID:</strong> $creditorId"
                                            . "</small></div>";
                            }
                        }

                        $html .= '</div>';
                    } else {
                        $html .= '<p><strong>Kein Lastschriftmandat hinterlegt.</strong></p>';
                    }
                }
            } else {
                $html .= '<p><strong>Kein Lastschriftmandat hinterlegt.</strong></p>';
            }
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Default-Werte für Felder: old_account_id, gcl_action, gcl_todo, gcl_new
     */
    public function set_default_value( $default, $field, $args ) {
        if ( $field->form_id != $this->form_id ) {
            return $default;
        }
        if ( ! in_array( $field->field_key, [ 'old_account_id','gcl_action','gcl_todo','gcl_new' ], true ) ) {
            return $default;
        }

        $opts   = get_option( $this->opt_name, [] );
        $token  = trim( $opts['gocardless_api_token'] ?? '' );
        $cust   = trim( do_shortcode( '[zoho_api_data field="GoCardlessID"]' ) );
        $accs   = [];
        $exists = false;

        if ( $token && $cust ) {
            $r = $this->gcl_api( 'GET', "customer_bank_accounts?customer=$cust", $token );
            if ( ! is_wp_error( $r ) ) {
                $accs   = json_decode( wp_remote_retrieve_body( $r ), true )['customer_bank_accounts'] ?? [];
                $exists = (bool) array_filter( $accs, fn( $a ) => ! empty( $a['enabled'] ) );
            }
        }

        switch ( $field->field_key ) {
            case 'old_account_id':
                $active = array_filter( $accs, fn( $a ) => ! empty( $a['enabled'] ) );
                return ! empty( $active ) ? reset( $active )['id'] : '';
            case 'gcl_action':
                return $exists ? 'update' : 'new';
            case 'gcl_todo':
                return 'nothing';
            case 'gcl_new':
                return 'Nein';
        }

        return $default;
    }

    /**
     * Nach Formular-Submit: E-Mail-Sync + GoCardless-Flows
     */
    public function handle_submission( $entry_id, $form_id ) {
        if ( $form_id !== $this->form_id ) {
            return;
        }
        $this->log( "=== handle_submission entry $entry_id ===" );

        // Feld-Key → ID
        $fields = FrmField::getAll( [ 'form_id' => $form_id ] );
        $map    = array_column( $fields, 'id', 'field_key' );
        $meta   = $_POST['item_meta'] ?? [];

        // Werte extrahieren
        $keys = [ 'old_account_id','gcl_action','gcl_todo','gcl_new','iban','account_holder','mail' ];
        $v    = [];
        foreach ( $keys as $k ) {
            $i      = $map[$k] ?? 0;
            $v[$k]  = $i && isset( $meta[$i] ) ? sanitize_text_field( wp_unslash( $meta[$i] ) ) : '';
        }
        $this->log( 'Values: ' . print_r( $v, true ) );

        $old_id  = $v['old_account_id'];
        $action  = $v['gcl_action'];
        $todo    = $v['gcl_todo'];
        $gcl_new = $v['gcl_new'];
        $iban    = $v['iban'];
        $holder  = $v['account_holder'];
        $email   = $v['mail'];

        $opts  = get_option( $this->opt_name, [] );
        $token = trim( $opts['gocardless_api_token'] ?? '' );
        $zcid  = trim( do_shortcode( '[zoho_api_data field="GoCardlessID"]' ) );

        if ( ! $token ) {
            $this->log( 'Abbruch: kein API-Token' );
            return;
        }

        $msg = '';

        // 1) E-Mail-Sync
        if ( $zcid && $email ) {
            $r0 = $this->gcl_api( 'GET', "customers/$zcid", $token );
            if ( ! is_wp_error( $r0 ) ) {
                $cur = json_decode( wp_remote_retrieve_body( $r0 ), true )['customers']['email'] ?? '';
                if ( strcasecmp( $cur, $email ) !== 0 ) {
                    $r1   = $this->gcl_api( 'PUT', "customers/$zcid", $token, [ 'customers'=>[ 'email'=> $email ] ] );
                    $msg .= "Customer email updated: " . wp_remote_retrieve_body( $r1 ) . "\n";
                }
            }
        }

        // 2) Neuer-Mandat-Flow
        if ( $action === 'new' && $gcl_new === 'Ja' ) {
            $this->log( '→ Neuer-Mandat-Flow' );
            $rCust = $this->gcl_api( 'POST', 'customers', $token, [
                'customers'=>[ 'email'=> $email, 'given_name'=> $holder, 'family_name'=> '' ]
            ] );
            $msg  .= "create customer: " . wp_remote_retrieve_body( $rCust ) . "\n";
            $cid   = json_decode( wp_remote_retrieve_body( $rCust ), true )['customers']['id'] ?? '';

            $rAcc  = $this->gcl_api( 'POST', 'customer_bank_accounts', $token, [
                'customer_bank_accounts'=>[
                    'iban'                => $iban,
                    'account_holder_name' => $holder,
                    'country_code'        => substr( $iban, 0, 2 ),
                    'links'               => [ 'customer'=> $cid ],
                ],
            ] );
            $msg  .= "create account: " . wp_remote_retrieve_body( $rAcc ) . "\n";
            $aid   = json_decode( wp_remote_retrieve_body( $rAcc ), true )['customer_bank_accounts']['id'] ?? '';

            if ( $aid ) {
                $rMand = $this->gcl_api( 'POST', 'mandates', $token, [
                    'mandates'=>[ 'scheme'=>'sepa_core', 'links'=>[ 'customer_bank_account'=> $aid ] ]
                ] );
                $msg  .= "create mandate: " . wp_remote_retrieve_body( $rMand ) . "\n";
            }
        }
        // 3) Update-Flow
        elseif ( $action === 'update' && $todo === 'update' ) {
            $this->log( '→ Update-Flow' );
            if ( $old_id ) {
                // Nur deaktivieren, wenn noch aktiv
                $rAccInfo = $this->gcl_api( 'GET', "customer_bank_accounts/$old_id", $token );
                if ( ! is_wp_error( $rAccInfo ) ) {
                    $accInfo = json_decode( wp_remote_retrieve_body( $rAccInfo ), true )['customer_bank_accounts'] ?? null;
                    if ( $accInfo && ! empty( $accInfo['enabled'] ) ) {
                        $this->gcl_api( 'POST', "customer_bank_accounts/$old_id/actions/disable", $token, '{}' );
                        $msg .= "Disabled old account $old_id\n";
                    } else {
                        $msg .= "Old account $old_id already disabled, skipping\n";
                    }
                }
            }
            $rAcc = $this->gcl_api( 'POST', 'customer_bank_accounts', $token, [
                'customer_bank_accounts'=>[
                    'iban'                => $iban,
                    'account_holder_name' => $holder,
                    'country_code'        => substr( $iban, 0, 2 ),
                    'links'               => [ 'customer'=> $zcid ],
                ],
            ] );
            $msg  .= "create new account: " . wp_remote_retrieve_body( $rAcc ) . "\n";
            $aid   = json_decode( wp_remote_retrieve_body( $rAcc ), true )['customer_bank_accounts']['id'] ?? '';
            if ( $aid ) {
                $rMand = $this->gcl_api( 'POST', 'mandates', $token, [
                    'mandates'=>[ 'scheme'=>'sepa_core', 'links'=>[ 'customer_bank_account'=> $aid ] ]
                ] );
                $msg  .= "create new mandate: " . wp_remote_retrieve_body( $rMand ) . "\n";
            }
        }
        // 4) Delete-Flow
        elseif ( $action === 'update' && $todo === 'delete' ) {
            $this->log( '→ Delete-Flow' );
            if ( $old_id ) {
                $rM = $this->gcl_api( 'GET', "mandates?customer_bank_account=$old_id", $token );
                foreach ( json_decode( wp_remote_retrieve_body( $rM ), true )['mandates'] ?? [] as $m ) {
                    if ( $m['status'] !== 'cancelled' ) {
                        $this->gcl_api( 'POST', "mandates/{$m['id']}/actions/cancel", $token, '{}' );
                    }
                }
                // Nur deaktivieren, wenn noch aktiv
                $rAccInfo = $this->gcl_api( 'GET', "customer_bank_accounts/$old_id", $token );
                if ( ! is_wp_error( $rAccInfo ) ) {
                    $accInfo = json_decode( wp_remote_retrieve_body( $rAccInfo ), true )['customer_bank_accounts'] ?? null;
                    if ( $accInfo && ! empty( $accInfo['enabled'] ) ) {
                        $this->gcl_api( 'POST', "customer_bank_accounts/$old_id/actions/disable", $token, '{}' );
                        $msg .= "Disabled old account $old_id\n";
                    } else {
                        $msg .= "Old account $old_id already disabled, skipping\n";
                    }
                }
            }
        }

        set_transient( 'gcl_resp_' . get_current_user_id(), $msg, 60 );
        $this->log( "=== handle_submission complete ===\n$msg" );
    }

    /***** Success Message *****/
    public function show_api_response( $message, $form ) {
        if ( $form->id !== $this->form_id ) {
            return $message;
        }
        $key  = 'gcl_resp_' . get_current_user_id();
        $resp = get_transient( $key );
        if ( $resp ) {
            delete_transient( $key );
            return '<div class="gcl-response" style="white-space:pre-wrap;margin-bottom:1em;">'
                   . esc_html( $resp )
                   . '</div>' . $message;
        }
        return $message;
    }
}

new GCL_Formidable_Integration();
