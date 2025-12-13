<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* --------------------------------------------------------
 * 1) ACF-Felder programmgesteuert hinzufügen
 * -------------------------------------------------------- */
function herzzentrum_acf_fields() {
    if ( function_exists( 'acf_add_local_field_group' ) ) {
        acf_add_local_field_group( array(
            'key'    => 'group_herzzentrum_permissions',
            'title'  => 'Herzzentrum-Berechtigungen',
            'fields' => array(
                array(
                    'key'   => 'field_alle_herzzentren_bearbeiten',
                    'label' => 'Alle Herzzentren bearbeiten',
                    'name'  => 'alle_herzzentren_bearbeiten',
                    'type'  => 'true_false',
                    'ui'    => 1,
                ),
                array(
                    'key'           => 'field_zugewiesenes_herzzentrum',
                    'label'         => 'Zugewiesene Herzzentren',
                    'name'          => 'zugewiesenes_herzzentrum',
                    'type'          => 'post_object',
                    'post_type'     => array( 'herzzentrum' ),
                    'multiple'      => 1,
                    'ui'            => 1,
                    'return_format' => 'id',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'user_form',
                        'operator' => '==',
                        'value'    => 'all',
                    ),
                ),
            ),
        ) );
    }
}
add_action( 'acf/init', 'herzzentrum_acf_fields' );

/* --------------------------------------------------------
 * Felder für Nicht-Admins verstecken
 * -------------------------------------------------------- */
add_filter( 'acf/prepare_field', 'hide_herzzentrum_fields_non_admin' );
function hide_herzzentrum_fields_non_admin( $field ) {
    $restricted_fields = array(
        'alle_herzzentren_bearbeiten',
        'zugewiesenes_herzzentrum',
    );
    if ( is_admin()
      && ! current_user_can( 'manage_options' )
      && in_array( $field['name'], $restricted_fields, true ) ) {
        return false;
    }
    return $field;
}

/* --------------------------------------------------------
 * TinyMCE & Quicktags nachladen (Admin)
 * -------------------------------------------------------- */
add_action( 'admin_enqueue_scripts', 'herzzentrum_enqueue_wp_editor' );
function herzzentrum_enqueue_wp_editor() {
    wp_enqueue_editor();
    wp_enqueue_script( 'quicktags' );
}
