<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX Callback: lädt Herzzentren + Benutzer
 */
function hbl_ajax_load_data() {
    check_ajax_referer( 'hbl_nonce', 'nonce' );
    echo hbl_generate_output();
    wp_die();
}
add_action( 'wp_ajax_load_herzzentren_data', 'hbl_ajax_load_data' );
add_action( 'wp_ajax_nopriv_load_herzzentren_data', 'hbl_ajax_load_data' );
