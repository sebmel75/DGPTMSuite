<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Übersetzungsdateien laden
 */
function hbl_load_textdomain() {
    load_plugin_textdomain(
        'herzzentren-benutzer-liste',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/../languages/'
    );
}
add_action( 'plugins_loaded', 'hbl_load_textdomain' );
