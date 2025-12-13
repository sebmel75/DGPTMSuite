<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generiert die Ausgabe der Herzzentren und zugeordneten Benutzer.
 * Unterstützt jetzt mehrere Herzzentren pro Benutzer.
 */
function hbl_generate_output() {
    $post_type = 'herzzentrum';

    if ( ! post_type_exists( $post_type ) ) {
        return '<p>Der Custom Post Type "herzzentrum" ist nicht registriert.</p>';
    }

    $output = '';

    // 1) Benutzer mit "alle_herzzentren_bearbeiten"
    $berechtigte = get_users( array(
        'meta_key'   => 'alle_herzzentren_bearbeiten',
        'meta_value' => '1',
        'orderby'    => 'display_name',
        'order'      => 'ASC',
        'number'     => -1,
        'fields'     => array( 'ID', 'first_name', 'last_name' ),
    ) );

    $output .= '<div class="hbl-berechtigte-benutzer">';
    $output .= '<h2>Benutzer, die alle Herzzentren bearbeiten dürfen:</h2>';
    if ( ! empty( $berechtigte ) ) {
        $output .= '<ul>';
        foreach ( $berechtigte as $user ) {
            $fname = get_user_meta( $user->ID, 'first_name', true );
            $lname = get_user_meta( $user->ID, 'last_name', true );
            $name  = trim( $fname . ' ' . $lname );
            if ( empty( $name ) ) {
                $name = get_userdata( $user->ID )->display_name;
            }
            $link = admin_url( 'user-edit.php?user_id=' . $user->ID );
            $output .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a></li>';
        }
        $output .= '</ul>';
    } else {
        $output .= '<p>Kein Benutzer hat diese Berechtigung.</p>';
    }
    $output .= '</div>';

    // 2) Alle Herzzentren
    $herzzentren = get_posts( array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    if ( empty( $herzzentren ) ) {
        $output .= '<p>Keine Herzzentren gefunden.</p>';
        return $output;
    }

    // 3) Tabelle starten
    $output .= '<table id="hbl-herzzentren-table" class="display hbl-herzzentren-benutzer-tabelle">';
    $output .= '<thead><tr><th>Herzzentrum</th><th>Zugeordnete Benutzer</th></tr></thead><tbody>';

    foreach ( $herzzentren as $hz ) {
        $hz_id    = $hz->ID;
        $hz_title = get_the_title( $hz_id );
        $hz_link  = get_permalink( $hz_id );

        // Mehrfachzuweisung: Benutzer, deren Meta-Array den aktuellen ID-String enthält
        $zugew = get_users( array(
            'meta_query' => array(
                array(
                    'key'     => 'zugewiesenes_herzzentrum',
                    'value'   => '"' . $hz_id . '"',
                    'compare' => 'LIKE',
                ),
            ),
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => -1,
            'fields'  => array( 'ID', 'first_name', 'last_name' ),
        ) );

        if ( ! empty( $zugew ) ) {
            $list = '<ul>';
            foreach ( $zugew as $user ) {
                $fname = get_user_meta( $user->ID, 'first_name', true );
                $lname = get_user_meta( $user->ID, 'last_name', true );
                $name  = trim( $fname . ' ' . $lname );
                if ( empty( $name ) ) {
                    $name = get_userdata( $user->ID )->display_name;
                }
                $link = admin_url( 'user-edit.php?user_id=' . $user->ID );
                $list .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a></li>';
            }
            $list .= '</ul>';
        } else {
            $list = 'n.B.';
        }

        $output .= '<tr>';
        $output .= '<td><a href="' . esc_url( $hz_link ) . '">' . esc_html( $hz_title ) . '</a></td>';
        $output .= '<td>' . $list . '</td>';
        $output .= '</tr>';
    }

    $output .= '</tbody></table>';

    // 4) Styling
    $output .= '<style>
        .hbl-herzzentren-benutzer-tabelle { width:100%; margin-bottom:20px }
        .hbl-herzzentren-benutzer-tabelle th,
        .hbl-herzzentren-benutzer-tabelle td { padding:8px; vertical-align:top }
        .hbl-herzzentren-benutzer-tabelle a { color:#0073aa; text-decoration:none }
        .hbl-herzzentren-benutzer-tabelle a:hover { text-decoration:underline }
        .hbl-berechtigte-benutzer { margin-bottom:20px }
        .hbl-berechtigte-benutzer ul { list-style-type:disc; margin-left:20px }
    </style>';

    return $output;
}
