<?php
/**
 * Plugin Name: OTP with Rotating Logo Preloader
 * Description: Sicheres OTP-Login (AJAX) per E-Mail oder Benutzername, mit Rate-Limit, Preloader (rotierendes Logo), optional 30‑Tage-Login ("Angemeldet bleiben"), Logout‑Shortcodes, und WP‑Login‑Deaktivierung. Multisite‑kompatibel.
 * Version: 3.4.0
 * Author: Sebastian Melzer
 * Text Domain: dgptm
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Network: true
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'DGPTM_PLUGIN_FILE', __FILE__ );
define( 'DGPTM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DGPTM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DGPTM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'DGPTM_OTP_TTL' ) ) { define( 'DGPTM_OTP_TTL', 600 ); } // 10 Minuten fix

// Network-aware options
if ( ! function_exists( 'dgptm_is_network_activated' ) ) {
    function dgptm_is_network_activated() {
        if ( ! is_multisite() ) return false;
        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return function_exists( 'is_plugin_active_for_network' )
            ? is_plugin_active_for_network( DGPTM_PLUGIN_BASENAME )
            : false;
    }
}
if ( ! function_exists( 'dgptm_get_option' ) ) {
    function dgptm_get_option( $key, $default = '' ) {
        // Mapping von alten Option-Keys auf neue zentrale Keys (DGPTM Suite)
        static $key_mapping = [
            'dgptm_otp_rate_limit' => 'rate_limit',
            'dgptm_email_subject' => 'email_subject',
            'dgptm_email_body' => 'email_body',
            'dgptm_preloader_enabled' => 'preloader_enabled',
            'dgptm_disable_wp_login' => 'disable_wp_login',
            'dgptm_webhook_enable' => 'webhook_enable',
            'dgptm_webhook_url' => 'webhook_url'
        ];

        // Zuerst im zentralen DGPTM Suite Settings-System suchen
        if (isset($key_mapping[$key]) && function_exists('dgptm_get_module_setting')) {
            $value = dgptm_get_module_setting('otp-login', $key_mapping[$key], null);
            if ($value !== null) {
                return $value;
            }
        }

        // Fallback: Multisite-aware option getting
        if ( dgptm_is_network_activated() ) {
            $v = get_site_option( $key, null );
            return ( null === $v ) ? get_option( $key, $default ) : $v;
        }
        return get_option( $key, $default );
    }
}
if ( ! function_exists( 'dgptm_update_option' ) ) {
    function dgptm_update_option( $key, $value ) {
        if ( dgptm_is_network_activated() ) {
            update_site_option( $key, $value );
        } else {
            update_option( $key, $value );
        }
    }
}

// Load parts
require_once DGPTM_PLUGIN_DIR . 'admin/class-dgptm-admin.php';
require_once DGPTM_PLUGIN_DIR . 'admin/class-dgptm-email-settings.php';
require_once DGPTM_PLUGIN_DIR . 'admin/class-dgptm-preloader-settings.php';
require_once DGPTM_PLUGIN_DIR . 'admin/class-dgptm-instructions.php';
require_once DGPTM_PLUGIN_DIR . 'includes/class-dgptm-otp.php';
require_once DGPTM_PLUGIN_DIR . 'includes/class-dgptm-preloader.php';
require_once DGPTM_PLUGIN_DIR . 'includes/class-dgptm-logout.php';

// Defaults
register_activation_hook( __FILE__, function () {
    if ( ! dgptm_get_option( 'dgptm_email_subject', null ) ) {
        dgptm_update_option( 'dgptm_email_subject', __( 'Ihr Login-Code für {site_name}', 'dgptm' ) );
    }
    if ( ! dgptm_get_option( 'dgptm_email_body', null ) ) {
        dgptm_update_option( 'dgptm_email_body', __( "Hallo {user_login},\n\nIhr Einmal-Code lautet: {otp}\nEr ist {otp_valid_minutes} Minuten gültig.\n\nViele Grüße\n{site_name}", 'dgptm' ) );
    }
    if ( ! dgptm_get_option( 'dgptm_otp_rate_limit', null ) ) {
        dgptm_update_option( 'dgptm_otp_rate_limit', 3 );
    }
    if ( ! dgptm_get_option( 'dgptm_preloader_enabled', null ) ) {
        dgptm_update_option( 'dgptm_preloader_enabled', 1 );
    }
    if ( ! dgptm_get_option( 'dgptm_disable_wp_login', null ) ) {
        dgptm_update_option( 'dgptm_disable_wp_login', 0 );
    }
    if ( ! dgptm_get_option( 'dgptm_webhook_enable', null ) ) {
        dgptm_update_option( 'dgptm_webhook_enable', 0 );
    }
    if ( ! dgptm_get_option( 'dgptm_webhook_url', null ) ) {
        dgptm_update_option( 'dgptm_webhook_url', '' );
    }
});

// Allow core logout when wp-login is disabled
add_action( 'login_init', function () {
    if ( ! dgptm_get_option( 'dgptm_disable_wp_login', 0 ) ) return;
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    if ( $action === 'logout' ) return; // allow
    if ( isset( $_GET['otp_bypass'] ) && $_GET['otp_bypass'] == '1' ) return;
    if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) return;
    wp_safe_redirect( home_url() );
    exit;
} );
