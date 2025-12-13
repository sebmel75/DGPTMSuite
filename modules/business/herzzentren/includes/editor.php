<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* --------------------------------------------------------
 * 3) Shortcode [herzzentrum_edit_button]
 * -------------------------------------------------------- */
function herzzentrum_edit_button_shortcode( $atts ) {
    $atts          = shortcode_atts( [ 'link' => '' ], $atts );
    $current_user  = wp_get_current_user();
    if ( ! $current_user || $current_user->ID === 0 ) {
        return '';
    }
    $current_post_id = get_the_ID();
    if ( ! $current_post_id ) {
        return '';
    }

    $can_edit_all = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
    $assigned     = get_user_meta( $current_user->ID, 'zugewiesenes_herzzentrum', true );
    // Auf Array normalisieren
    if ( ! is_array( $assigned ) ) {
        $assigned_ids = $assigned ? array( intval( $assigned ) ) : array();
    } else {
        $assigned_ids = array_map( 'intval', $assigned );
    }

    if ( $can_edit_all || in_array( $current_post_id, $assigned_ids, true ) ) {
        // Enqueue styles nur wenn Button angezeigt wird
        herzzentrum_editor_enqueue_styles();
        
        $link  = '#hzbearbeiten';
        $title = get_the_title( $current_post_id );
        return '<a href="' . esc_url( $link ) . '" class="dgptm-herzzentrum-edit-button">'
             . 'Herzzentrum ' . esc_html( $title ) . ' bearbeiten'
             . '</a>';
    }
    return '';
}
add_shortcode( 'herzzentrum_edit_button', 'herzzentrum_edit_button_shortcode' );

/* --------------------------------------------------------
 * 4) Shortcode [assigned_herzzentrum_name]
 * -------------------------------------------------------- */
function assigned_herzzentrum_name_shortcode( $atts ) {
    $atts         = shortcode_atts( [ 'link' => 'yes' ], $atts, 'assigned_herzzentrum_name' );
    $current_user = wp_get_current_user();
    if ( ! $current_user || $current_user->ID === 0 ) {
        return '';
    }

    $can_edit_all = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
    $assigned     = get_user_meta( $current_user->ID, 'zugewiesenes_herzzentrum', true );
    if ( ! is_array( $assigned ) ) {
        $assigned_ids = $assigned ? array( intval( $assigned ) ) : array();
    } else {
        $assigned_ids = array_map( 'intval', $assigned );
    }

    // Enqueue styles wenn Link angezeigt wird
    if ( $atts['link'] === 'yes' && ( $can_edit_all || ! empty( $assigned_ids ) ) ) {
        herzzentrum_editor_enqueue_styles();
    }

    if ( $can_edit_all ) {
        // Verhalten wie bisher
        if ( $assigned_ids ) {
            $title        = get_the_title( $assigned_ids[0] );
            $display_text = 'Herzzentren ' . esc_html( $title );
        } else {
            $display_text = 'Herzzentren';
        }
        if ( $atts['link'] === 'yes' ) {
            return '<a href="/karriere/herzzentren/" class="dgptm-herzzentrum-assigned-link">' . $display_text . '</a>';
        }
        return $display_text;
    }

    if ( empty( $assigned_ids ) ) {
        return '';
    }

    // Bei mehreren zugewiesenen Herzzentren: alle verlinken, getrennt durch Komma
    $links = array();
    foreach ( $assigned_ids as $hz_id ) {
        $perm = get_permalink( $hz_id );
        $ttl  = get_the_title( $hz_id );
        if ( $perm && $ttl ) {
            if ( $atts['link'] === 'yes' ) {
                $links[] = '<a href="' . esc_url( $perm ) . '" class="dgptm-herzzentrum-assigned-link">' . esc_html( $ttl ) . '</a>';
            } else {
                $links[] = esc_html( $ttl );
            }
        }
    }
    return implode( ', ', $links );
}
add_shortcode( 'assigned_herzzentrum_name', 'assigned_herzzentrum_name_shortcode' );

/* --------------------------------------------------------
 * 5) AJAX-Endpunkt: get_assigned_herzzentrum_name
 * -------------------------------------------------------- */
add_action( 'wp_ajax_get_assigned_herzzentrum_name', 'ajax_get_assigned_herzzentrum_name' );
add_action( 'wp_ajax_nopriv_get_assigned_herzzentrum_name', 'ajax_get_assigned_herzzentrum_name' );
function ajax_get_assigned_herzzentrum_name() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_success( [ 'html' => '' ] );
    }
    $current_user  = wp_get_current_user();
    $can_edit_all  = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
    $assigned      = get_user_meta( $current_user->ID, 'zugewiesenes_herzzentrum', true );
    if ( ! is_array( $assigned ) ) {
        $assigned_ids = $assigned ? array( intval( $assigned ) ) : array();
    } else {
        $assigned_ids = array_map( 'intval', $assigned );
    }

    if ( $can_edit_all ) {
        if ( $assigned_ids ) {
            $title = get_the_title( $assigned_ids[0] );
            $html  = '<a href="/karriere/herzzentren/" class="dgptm-herzzentrum-assigned-link">Jetzt ' . esc_html( $title ) . ' bearbeiten</a>';
        } else {
            $html = '<a href="/karriere/herzzentren/" class="dgptm-herzzentrum-assigned-link">Jetzt alle Herzzentren bearbeiten</a>';
        }
        wp_send_json_success( [ 'html' => $html ] );
    }

    if ( empty( $assigned_ids ) ) {
        wp_send_json_success( [ 'html' => '' ] );
    }

    // Bei mehreren: alle Buttons hintereinander
    $html = '';
    foreach ( $assigned_ids as $hz_id ) {
        $perm = get_permalink( $hz_id );
        $ttl  = get_the_title( $hz_id );
        if ( $perm && $ttl ) {
            $html .= '<a href="' . esc_url( $perm ) . '" class="dgptm-herzzentrum-assigned-link">Jetzt ' . esc_html( $ttl ) . ' bearbeiten</a>';
        }
    }
    wp_send_json_success( [ 'html' => $html ] );
}

/* --------------------------------------------------------
 * 6) Shortcode [assigned_herzzentrum_name_ajax]
 * -------------------------------------------------------- */
function assigned_herzzentrum_name_ajax_shortcode( $atts ) {
    // Enqueue JavaScript nur wenn Shortcode verwendet wird
    wp_enqueue_script(
        'dgptm-herzzentrum-ajax',
        DGPTM_HZ_URL . 'assets/js/herzzentrum-ajax.js',
        array(),
        DGPTM_HZ_VERSION,
        true
    );
    
    wp_localize_script( 'dgptm-herzzentrum-ajax', 'dgptmEditorConfig', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'dgptm_editor_nonce' ),
    ) );
    
    // Enqueue styles
    herzzentrum_editor_enqueue_styles();
    
    return '<div id="dgptm-assigned-herzzentrum-name-output"></div>';
}
add_shortcode( 'assigned_herzzentrum_name_ajax', 'assigned_herzzentrum_name_ajax_shortcode' );

/* --------------------------------------------------------
 * 7) Shortcode [herzzentrum_editors]
 * -------------------------------------------------------- */
function herzzentrum_editors_shortcode( $atts ) {
    $current_post_id = get_the_ID();
    if ( ! $current_post_id ) {
        return 'Beitrag ID konnte nicht ermittelt werden.';
    }
    $users   = get_users();
    $editors = array();

    foreach ( $users as $user ) {
        $assigned = get_user_meta( $user->ID, 'zugewiesenes_herzzentrum', true );
        if ( ! is_array( $assigned ) ) {
            $assigned_ids = $assigned ? array( intval( $assigned ) ) : array();
        } else {
            $assigned_ids = array_map( 'intval', $assigned );
        }
        $can_edit_all = get_user_meta( $user->ID, 'alle_herzzentren_bearbeiten', true );
        if ( $can_edit_all ) {
            continue;
        }
        if ( in_array( $current_post_id, $assigned_ids, true ) ) {
            $editors[] = trim( $user->first_name . ' ' . $user->last_name );
        }
    }

    if ( empty( $editors ) ) {
        return 'Dieses Herzzentrum wird von keinem Benutzer betreut. Bitte wenden Sie sich an die Geschäftsstelle.';
    }
    return 'Dieses Herzzentrum wird betreut von ' . implode( ', ', $editors ) . '.';
}
add_shortcode( 'herzzentrum_editors', 'herzzentrum_editors_shortcode' );

/* --------------------------------------------------------
 * 8) CSS-Stile für Buttons & Links (OPTIMIERT)
 * Inline CSS wurde in separate Datei ausgelagert
 * -------------------------------------------------------- */
function herzzentrum_editor_enqueue_styles() {
    static $enqueued = false;
    if ( $enqueued ) {
        return;
    }
    
    wp_enqueue_style(
        'dgptm-herzzentrum-editor-buttons',
        DGPTM_HZ_URL . 'assets/css/editor-buttons.css',
        array(),
        DGPTM_HZ_VERSION
    );
    
    $enqueued = true;
}

/* --------------------------------------------------------
 * 9) Shortcode [hzberechtigung]...[/hzberechtigung]
 * -------------------------------------------------------- */
function herzzentrum_berechtigung_shortcode( $atts, $content = null ) {
    if ( is_null( $content ) || ! is_user_logged_in() ) {
        return '';
    }
    $current_user    = wp_get_current_user();
    $can_edit_all    = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
    $assigned        = get_user_meta( $current_user->ID, 'zugewiesenes_herzzentrum', true );
    if ( ! is_array( $assigned ) ) {
        $assigned_ids = $assigned ? array( intval( $assigned ) ) : array();
    } else {
        $assigned_ids = array_map( 'intval', $assigned );
    }
    $current_post_id = get_the_ID();
    if ( ! $current_post_id ) {
        return '';
    }
    if ( $can_edit_all || in_array( $current_post_id, $assigned_ids, true ) ) {
        return do_shortcode( $content );
    }
    return '';
}
add_shortcode( 'hzberechtigung', 'herzzentrum_berechtigung_shortcode' );

/* ========================================================
 * NEUE / ERWEITERTE SHORTCODES (hzb_...)
 * ======================================================== */

/**
 * [hzb_assigned_id]
 */
function hzb_assigned_id_shortcode( $atts ) {
    if ( ! is_user_logged_in() ) {
        return '0';
    }
    $current_user = wp_get_current_user();
    if ( ! $current_user || $current_user->ID === 0 ) {
        return '0';
    }
    $can_edit_all = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
    $assigned     = get_user_meta( $current_user->ID, 'zugewiesenes_herzzentrum', true );
    if ( ! is_array( $assigned ) ) {
        $assigned_ids = $assigned ? array( intval( $assigned ) ) : array();
    } else {
        $assigned_ids = array_map( 'intval', $assigned );
    }
    if ( $can_edit_all || empty( $assigned_ids ) ) {
        return '0';
    }
    return (string) $assigned_ids[0];
}
add_shortcode( 'hzb_assigned_id', 'hzb_assigned_id_shortcode' );

/**
 * [hzb_can_edit_all]
 */
function hzb_can_edit_all_shortcode( $atts ) {
    if ( ! is_user_logged_in() ) {
        return 'false';
    }
    $current_user  = wp_get_current_user();
    if ( ! $current_user || $current_user->ID === 0 ) {
        return 'false';
    }
    $can_edit_all = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
    return $can_edit_all ? 'true' : 'false';
}
add_shortcode( 'hzb_can_edit_all', 'hzb_can_edit_all_shortcode' );

/**
 * [hzb_has_permissions]
 */
function hzb_has_permissions_shortcode( $atts ) {
    if ( ! is_user_logged_in() ) {
        return 'false';
    }
    $current_user  = wp_get_current_user();
    if ( ! $current_user || $current_user->ID === 0 ) {
        return 'false';
    }
    $can_edit_all = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
    $assigned     = get_user_meta( $current_user->ID, 'zugewiesenes_herzzentrum', true );
    if ( ! is_array( $assigned ) ) {
        $has_assigned = (bool) $assigned;
    } else {
        $has_assigned = ! empty( $assigned );
    }
    return ( $can_edit_all || $has_assigned ) ? 'true' : 'false';
}
add_shortcode( 'hzb_has_permissions', 'hzb_has_permissions_shortcode' );

/**
 * [hzb_can_edit_this_post]
 */
function hzb_can_edit_this_post_shortcode( $atts ) {
    if ( ! is_user_logged_in() ) {
        return 'false';
    }
    $current_user    = wp_get_current_user();
    if ( ! $current_user || $current_user->ID === 0 ) {
        return 'false';
    }
    $current_post_id = get_the_ID();
    if ( ! $current_post_id ) {
        return 'false';
    }
    $can_edit_all = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
    $assigned     = get_user_meta( $current_user->ID, 'zugewiesenes_herzzentrum', true );
    if ( ! is_array( $assigned ) ) {
        $assigned_ids = $assigned ? array( intval( $assigned ) ) : array();
    } else {
        $assigned_ids = array_map( 'intval', $assigned );
    }
    return ( $can_edit_all || in_array( $current_post_id, $assigned_ids, true ) ) ? 'true' : 'false';
}
add_shortcode( 'hzb_can_edit_this_post', 'hzb_can_edit_this_post_shortcode' );

/**
 * [hzb_can_edit_any_permission]
 */
function hzb_can_edit_any_permission_shortcode( $atts ) {
    if ( ! is_user_logged_in() ) {
        return 'false';
    }
    $current_user  = wp_get_current_user();
    if ( ! $current_user || $current_user->ID === 0 ) {
        return 'false';
    }
    $can_edit_all = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
    $assigned     = get_user_meta( $current_user->ID, 'zugewiesenes_herzzentrum', true );
    if ( ! is_array( $assigned ) ) {
        $has_assigned = (bool) $assigned;
    } else {
        $has_assigned = ! empty( $assigned );
    }
    return ( $can_edit_all || $has_assigned ) ? 'true' : 'false';
}
add_shortcode( 'hzb_can_edit_any_permission', 'hzb_can_edit_any_permission_shortcode' );

/**
 * [hzb_can_edit_permission type="all|specific"]
 */
function hzb_can_edit_permission_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'type' => '' ], $atts );
    $type = strtolower( $atts['type'] );
    if ( ! is_user_logged_in() ) {
        return 'false';
    }
    $current_user = wp_get_current_user();
    if ( ! $current_user || $current_user->ID === 0 ) {
        return 'false';
    }
    if ( $type === 'all' ) {
        $can_edit_all = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
        return $can_edit_all ? 'true' : 'false';
    } elseif ( $type === 'specific' ) {
        $assigned = get_user_meta( $current_user->ID, 'zugewiesenes_herzzentrum', true );
        if ( ! is_array( $assigned ) ) {
            $assigned_ids = $assigned ? array( intval( $assigned ) ) : array();
        } else {
            $assigned_ids = array_map( 'intval', $assigned );
        }
        return ! empty( $assigned_ids ) ? 'true' : 'false';
    } elseif ( empty( $type ) ) {
        // wie hzb_has_permissions
        $can_edit_all = get_user_meta( $current_user->ID, 'alle_herzzentren_bearbeiten', true );
        $assigned     = get_user_meta( $current_user->ID, 'zugewiesenes_herzzentrum', true );
        if ( ! is_array( $assigned ) ) {
            $has_assigned = (bool) $assigned;
        } else {
            $has_assigned = ! empty( $assigned );
        }
        return ( $can_edit_all || $has_assigned ) ? 'true' : 'false';
    }
    return 'false';
}
add_shortcode( 'hzb_can_edit_permission', 'hzb_can_edit_permission_shortcode' );
