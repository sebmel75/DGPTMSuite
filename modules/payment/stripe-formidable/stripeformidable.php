<?php
/**
 * Plugin Name: Stripe Formidable Integration
 * Description: Integriert Stripe-SEPA-Mandate und Karten-Zahlungen in Formidable-Form ID 12, speichert Stripe Customer ID.
 * Version:     3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Stripe_Formidable_Integration {

    private string $opt_name = 'stripe_formidable_settings';
    private int    $form_id  = 12;

    public function __construct() {
        add_action( 'admin_menu',         [ $this, 'admin_page'        ] );
        add_action( 'admin_init',         [ $this, 'register_settings' ] );
        add_action( 'init',               [ $this, 'init_hooks'       ] );
    }

    /* Admin UI */
    public function admin_page() {
        add_options_page(
            'Stripe Integration',
            'Stripe Formidable',
            'manage_options',
            'stripe-formidable-settings',
            [ $this, 'settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'stripe_formidable_group', $this->opt_name );
        add_settings_section( 'sec_stripe', 'Stripe Einstellungen', '__return_false', 'stripe-formidable-settings' );
        add_settings_field( 'stripe_secret', 'Stripe Secret Key',
            [ $this, 'input_field' ], 'stripe-formidable-settings', 'sec_stripe', [ 'key'=>'stripe_secret' ] );
        add_settings_field( 'stripe_pub', 'Stripe Publishable Key',
            [ $this, 'input_field' ], 'stripe-formidable-settings', 'sec_stripe', [ 'key'=>'stripe_pub' ] );
    }

    public function settings_page() {
        ?>
        <div class="wrap"><h1>Stripe Formidable Integration</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'stripe_formidable_group' );
            do_settings_sections( 'stripe-formidable-settings' );
            submit_button();
            ?>
        </form>
        </div>
        <?php
    }

    public function input_field( array $args ) {
        $opts = get_option( $this->opt_name, [] );
        $val  = $opts[ $args['key'] ] ?? '';
        printf(
            '<input type="text" name="%1$s[%2$s]" value="%3$s" style="width:60%%">',
            esc_attr( $this->opt_name ),
            esc_attr( $args['key'] ),
            esc_attr( $val )
        );
    }

    /* Hooks */
    public function init_hooks() {
        add_shortcode(   'stripe_formidable',        [ $this, 'render_section'    ] );
        add_action(      'frm_after_create_entry',   [ $this, 'handle_submit'    ], 20, 2 );
        add_filter(      'frm_confirmation_message', [ $this, 'insert_response'  ], 20, 3 );
    }

    /* Logging helper */
    private function log( string $msg ): void {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log("[Stripe-Formidable] $msg");
        }
    }

    /* Stripe API helper */
    private function stripe_api( string $method, string $endpoint, array $data = [] ) {
        $opts = get_option( $this->opt_name, [] );
        $sk   = trim( $opts['stripe_secret'] ?? '' );
        if ( ! $sk ) {
            $this->log("Missing Stripe secret key");
            return null;
        }
        $url  = "https://api.stripe.com/v1/$endpoint";
        $args = [ 'method'=>$method, 'headers'=>[ 'Authorization'=>"Bearer $sk" ], 'timeout'=>20 ];
        if ( $method === 'GET' && $data ) {
            $url .= '?' . http_build_query( $data );
        } elseif ( $method !== 'GET' ) {
            $args['body'] = $data;
        }
        $this->log("Stripe $method $endpoint " . json_encode($data));
        $res = wp_remote_request( $url, $args );
        if ( is_wp_error($res) ) {
            $this->log("Stripe API error: " . $res->get_error_message());
            return null;
        }
        return $res;
    }

    /* Render payment info */
    public function render_section(): string {
        $stripe_id = trim( do_shortcode('[zoho_api_data_ajax field="StripeID"]') );
        if ( ! $stripe_id ) {
            return '<p><strong>Keine Stripe-Zahlungsdaten vorhanden.</strong></p>';
        }
        // Fetch customer with default payment method
        $res = $this->stripe_api( 'GET', "customers/$stripe_id", [ 'expand[]'=>'invoice_settings.default_payment_method' ] );
        if ( ! $res ) {
            return '<p><strong>Fehler beim Laden der Stripe-Daten.</strong></p>';
        }
        $code = wp_remote_retrieve_response_code($res);
        $body = json_decode( wp_remote_retrieve_body($res), true );
        if ( $code !== 200 || empty($body) ) {
            return '<p><strong>Keine gültige Stripe-Antwort.</strong></p>';
        }
        $cust = $body;
        $pm   = $cust['invoice_settings']['default_payment_method'] ?? null;
        // fallback to first SEPA if none
        if ( ! $pm ) {
            $list = $this->stripe_api( 'GET', 'payment_methods', [ 'customer'=>$stripe_id, 'type'=>'sepa_debit', 'limit'=>1 ] );
            if ( $list ) {
                $data = json_decode( wp_remote_retrieve_body($list), true )['data'] ?? [];
                $pm   = $data[0] ?? null;
            }
        }
        if ( ! $pm ) {
            return '<p><strong>Keine Default-Zahlungsmethode gesetzt.</strong></p>';
        }

        // Determine display values
        $holder = $cust['name'] ?? $cust['email'];
        $type   = $pm['type'];
        if ( $type === 'sepa_debit' ) {
            $last4 = $pm['sepa_debit']['last4'];
            $mand  = $pm['sepa_debit']['mandate'] ?? '';
            // creditor = account id
            $acct = $this->stripe_api('GET','account');
            $cred = '';
            if ( $acct ) {
                $cred = json_decode( wp_remote_retrieve_body($acct), true )['id'] ?? '';
            }
            return $this->wrap_display( $holder, "…$last4", "Stripe SEPA", $mand, $cred, '#0a0' );
        }
        if ( $type === 'card' ) {
            $brand = strtoupper($pm['card']['brand']);
            $last4 = $pm['card']['last4'];
            $info  = "Karte $brand ****$last4";
            return $this->wrap_display( $holder, $info, "Stripe Karte", '', '', '#00a' );
        }

        return '<p><strong>Unbekannte Zahlungsmethode.</strong></p>';
    }

    private function wrap_display( string $holder, string $last4, string $bank, string $mand, string $cred, string $border ): string {
        return
          '<div style="font-size:.9em;padding:8px;border:1px solid '.esc_attr($border).';border-radius:4px;">'
          ."<div><strong>Inhaber:</strong> ".esc_html($holder)."</div>"
          ."<div><strong>Detail:</strong> ".esc_html($last4)."</div>"
          ."<div><strong>Typ:</strong> ".esc_html($bank)."</div>"
          .( $mand
             ? '<div style="margin-top:6px;"><small>'
               ."<strong>Mandats-Ref:</strong> ".esc_html($mand)
               ." | <strong>Gläubiger-ID:</strong> ".esc_html($cred)
               .'</small></div>'
             : ''
           )
          .'</div>';
    }

    /* Handle form submit */
    public function handle_submit( $entry_id, $form_id ) {
        if ( $form_id !== $this->form_id ) return;
        $fields = FrmField::getAll(['form_id'=>$form_id]);
        $map    = array_column( $fields, 'id', 'field_key' );
        $meta   = $_POST['item_meta'] ?? [];

        $get = function( $key ) use ( $map, $meta ) {
            return isset( $map[$key], $meta[$map[$key]] )
                ? sanitize_text_field( wp_unslash( $meta[$map[$key]] ) )
                : '';
        };

        $email            = $get('mail');
        $holder           = $get('account_holder');
        $memberNo         = trim( do_shortcode('[zoho_api_data_ajax field="MitgliedsNr"]') );
        $stripe_id_field  = $get('stripe_customer_id');
        $stripe_id_zoo    = trim( do_shortcode('[zoho_api_data_ajax field="StripeID"]') );
        $stripe_id        = $stripe_id_field ?: $stripe_id_zoo;

        $stripe_type = $get('stripe_type'); // 'sepa' or 'card'
        $iban4       = $get('iban4');
        $card_no     = $get('card_number');
        $card_mm     = $get('card_exp_month');
        $card_yy     = $get('card_exp_year');
        $card_cvc    = $get('card_cvc');
        $card_name   = $get('card_holder_name');

        $msg = '';

        // 1) Customer create/update
        if ( $email ) {
            $payload = [ 'email'=>$email, 'name'=>$holder, 'description'=>$memberNo ];
            if ( ! $stripe_id ) {
                // search existing
                $sr = $this->stripe_api('GET','customers',['email'=>$email,'limit'=>1]);
                if ( $sr ) {
                    $data = json_decode(wp_remote_retrieve_body($sr),true)['data'] ?? [];
                    $stripe_id = $data[0]['id'] ?? '';
                }
            }
            if ( ! $stripe_id ) {
                $r = $this->stripe_api('POST','customers',$payload);
                if ( $r && wp_remote_retrieve_response_code($r)===200 ) {
                    $stripe_id = json_decode(wp_remote_retrieve_body($r),true)['id'] ?? '';
                    $msg .= "Stripe Customer created: $stripe_id\n";
                }
            } else {
                $this->stripe_api('POST',"customers/$stripe_id",$payload);
                $msg .= "Stripe Customer updated: $stripe_id\n";
            }
            // store in hidden field
            if ( $stripe_id && isset( $map['stripe_customer_id'] ) ) {
                FrmEntryMeta::update_entry_meta( $entry_id, $map['stripe_customer_id'], '', $stripe_id );
            }
        }

        // 2) Payment method
        if ( $stripe_id && $stripe_type === 'sepa' && $iban4 ) {
            $pm = $this->stripe_api('POST','payment_methods',[
                'type'=>'sepa_debit',
                'sepa_debit[iban]'=>$iban4,
                'billing_details[name]'=>$holder,
                'billing_details[email]'=>$email,
            ]);
            if ( $pm && wp_remote_retrieve_response_code($pm)===200 ) {
                $b = json_decode(wp_remote_retrieve_body($pm),true);
                $pmid = $b['id'] ?? '';
                if ( $pmid ) {
                    $this->stripe_api('POST',"payment_methods/$pmid/attach",['customer'=>$stripe_id]);
                    $this->stripe_api('POST',"customers/$stripe_id",[
                        'invoice_settings[default_payment_method]'=>$pmid
                    ]);
                    $msg .= "Stripe SEPA attached: $pmid\n";
                }
            }
        }
        if ( $stripe_id && $stripe_type === 'card'
             && $card_no && $card_mm && $card_yy && $card_cvc ) {
            $pm = $this->stripe_api('POST','payment_methods',[
                'type'=>'card',
                'card[number]'=>$card_no,
                'card[exp_month]'=>$card_mm,
                'card[exp_year]'=>$card_yy,
                'card[cvc]'=>$card_cvc,
                'billing_details[name]'=>$card_name,
                'billing_details[email]'=>$email,
            ]);
            if ( $pm && wp_remote_retrieve_response_code($pm)===200 ) {
                $b = json_decode(wp_remote_retrieve_body($pm),true);
                $pmid = $b['id'] ?? '';
                if ( $pmid ) {
                    $this->stripe_api('POST',"payment_methods/$pmid/attach",['customer'=>$stripe_id]);
                    $this->stripe_api('POST',"customers/$stripe_id",[
                        'invoice_settings[default_payment_method]'=>$pmid
                    ]);
                    $msg .= "Stripe Card attached: $pmid\n";
                }
            }
        }

        // store response
        set_transient( "stripe_resp_$entry_id", $msg, 60 );
        if ( isset( $map['stripe-response'] ) ) {
            FrmEntryMeta::update_entry_meta( $entry_id, $map['stripe-response'], '', $msg );
        }
    }

    /* Show confirmation */
    public function insert_response( string $message, $form, $args ): string {
        if ( $form->id !== $this->form_id ) return $message;
        $eid = $args['entry_id'] ?? 0;
        $r   = $eid ? get_transient("stripe_resp_$eid") : '';
        if ( $r ) {
            delete_transient("stripe_resp_$eid");
            return '<div class="stripe-response" style="white-space:pre-wrap;margin-bottom:1em;">'
                 . esc_html($r) . '</div>' . $message;
        }
        return $message;
    }
}

new Stripe_Formidable_Integration();
