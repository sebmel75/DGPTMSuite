<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Top-level menu (single-site)
function dgptm_admin_register_top_menu() {
    add_menu_page(
        __( 'OTP Login', 'dgptm' ),
        __( 'OTP Login', 'dgptm' ),
        'manage_options',
        'dgptm-otp-root',
        'dgptm_admin_email_page',
        'dashicons-shield',
        58
    );
    add_submenu_page( 'dgptm-otp-root', __( 'E-Mail & Sicherheit', 'dgptm' ), __( 'E-Mail & Sicherheit', 'dgptm' ), 'manage_options', 'dgptm-otp-email', 'dgptm_admin_email_page' );
    add_submenu_page( 'dgptm-otp-root', __( 'Microsoft OAuth', 'dgptm' ), __( 'Microsoft OAuth', 'dgptm' ), 'manage_options', 'dgptm-oauth', 'dgptm_admin_oauth_page' );
    add_submenu_page( 'dgptm-otp-root', __( 'Preloader', 'dgptm' ), __( 'Preloader', 'dgptm' ), 'manage_options', 'dgptm-preloader', 'dgptm_admin_preloader_page' );
    add_submenu_page( 'dgptm-otp-root', __( 'Anleitung', 'dgptm' ), __( 'Anleitung', 'dgptm' ), 'read', 'dgptm-instructions', 'dgptm_admin_instructions_page' );
}
add_action( 'admin_menu', 'dgptm_admin_register_top_menu', 9 );

// Also register under Settings (single + network) to keep old paths working
function dgptm_admin_register_menus() {
    $parent = dgptm_is_network_activated() ? 'settings.php' : 'options-general.php';
    add_submenu_page( $parent, __( 'OTP Login', 'dgptm' ), __( 'OTP Login', 'dgptm' ), 'manage_options', 'dgptm-otp-email', 'dgptm_admin_email_page' );
    add_submenu_page( $parent, __( 'Preloader', 'dgptm' ), __( 'Preloader', 'dgptm' ), 'manage_options', 'dgptm-preloader', 'dgptm_admin_preloader_page' );
    add_submenu_page( $parent, __( 'Anleitung', 'dgptm' ), __( 'Anleitung', 'dgptm' ), 'read', 'dgptm-instructions', 'dgptm_admin_instructions_page' );
}
add_action( 'admin_menu', 'dgptm_admin_register_menus' );
add_action( 'network_admin_menu', 'dgptm_admin_register_menus' );

// Settings link in plugin list
add_filter( 'plugin_action_links_' . DGPTM_PLUGIN_BASENAME, function( $links ){
    $url = admin_url( 'admin.php?page=dgptm-otp-root' );
    if ( dgptm_is_network_activated() ) { $url = network_admin_url( 'admin.php?page=dgptm-otp-root' ); }
    $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Einstellungen', 'dgptm' ) . '</a>';
    return $links;
});
