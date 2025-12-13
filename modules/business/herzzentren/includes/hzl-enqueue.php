<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue DataTables CSS und JS nur wenn Shortcode vorhanden ist.
 */
function hbl_enqueue_datatables() {
    if ( is_admin() ) {
        return;
    }
    global $post;
    if ( ! isset( $post->post_content ) || ! has_shortcode( $post->post_content, 'herzzentren_benutzer_liste' ) ) {
        return;
    }

    wp_enqueue_style(
        'hbl-datatables-css',
        'https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css',
        array(),
        '1.13.4'
    );
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script(
        'hbl-datatables-js',
        'https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js',
        array( 'jquery' ),
        '1.13.4',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'hbl_enqueue_datatables' );
