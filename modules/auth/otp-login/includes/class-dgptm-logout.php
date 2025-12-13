<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Shortcode: Link (<a>)
add_shortcode( 'dgptm_logout_link', function( $atts ){
    $atts = shortcode_atts( array(
        'text'     => __( 'Abmelden', 'dgptm' ),
        'redirect' => home_url( '/' ),
        'class'    => '',
    ), $atts );
    $url = wp_logout_url( $atts['redirect'] );
    $class_attr = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
    return '<a href="' . esc_url( $url ) . '"' . $class_attr . '>' . esc_html( $atts['text'] ) . '</a>';
});

// Shortcode: nur URL (für Elementor-Link-Feld)
add_shortcode( 'dgptm_logout_url', function( $atts ){
    $atts = shortcode_atts( array( 'redirect' => home_url( '/' ) ), $atts );
    return esc_url( wp_logout_url( $atts['redirect'] ) );
});

// Route /dgptm-logout oder ?dgptm_logout=1 → sichere Weiterleitung zum Core-Logout
add_action( 'template_redirect', function () {
    $is_qs = isset( $_GET['dgptm_logout'] ) && $_GET['dgptm_logout'] == '1';
    $req_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $path = trim( parse_url( $req_uri, PHP_URL_PATH ), '/' );
    $is_path = ( $path === 'dgptm-logout' );
    if ( ! $is_qs && ! $is_path ) return;
    $redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : home_url( '/' );
    wp_safe_redirect( wp_logout_url( $redirect ) );
    exit;
});
