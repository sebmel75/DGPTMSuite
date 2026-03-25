<?php
/**
 * AJAX-Handler für News-Verwaltung (Dashboard-kompatibel).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 * Toggle Publish / Draft
 * ============================================================ */
add_action( 'wp_ajax_cnp_news_toggle_publish', function() {
    check_ajax_referer( 'cnp_news_nonce', 'nonce' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    }

    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'newsbereich' ) {
        wp_send_json_error( [ 'message' => 'Beitrag nicht gefunden.' ] );
    }

    $new_status = ( $post->post_status === 'publish' ) ? 'draft' : 'publish';
    wp_update_post( [ 'ID' => $post_id, 'post_status' => $new_status ] );

    $label = ( $new_status === 'publish' ) ? 'Veröffentlicht' : 'Entwurf';
    wp_send_json_success( [ 'status' => $new_status, 'status_label' => $label ] );
});

/* ============================================================
 * Create
 * ============================================================ */
add_action( 'wp_ajax_cnp_news_create', function() {
    check_ajax_referer( 'cnp_news_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_newsbereiche' ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    }

    $title   = wp_kses_post( $_POST['cnp_news_title'] ?? '' );
    $content = $_POST['cnp_news_content'] ?? '';
    if ( empty( $title ) || empty( $content ) ) {
        wp_send_json_error( [ 'message' => 'Titel und Inhalt sind erforderlich.' ] );
    }

    $pd  = trim( $_POST['cnp_publish_date'] ?? '' );
    $es  = trim( $_POST['cnp_event_start'] ?? '' );
    $du  = trim( $_POST['cnp_display_until'] ?? '' );
    $loc = sanitize_text_field( $_POST['cnp_event_location'] ?? '' );
    $url = esc_url_raw( $_POST['cnp_news_url'] ?? '' );
    $cats = isset( $_POST['cnp_news_category'] ) ? array_map( 'intval', (array) $_POST['cnp_news_category'] ) : [];
    $new_cat = sanitize_text_field( $_POST['cnp_new_category'] ?? '' );
    $edugrant = isset( $_POST['cnp_edugrant'] ) ? 1 : 0;

    if ( ! empty( $new_cat ) ) {
        $term = get_term_by( 'name', $new_cat, 'category' );
        if ( ! $term ) {
            $nt = wp_insert_term( $new_cat, 'category' );
            if ( ! is_wp_error( $nt ) ) $cats[] = (int) $nt['term_id'];
        } else {
            $cats[] = (int) $term->term_id;
        }
    }

    $pd_ymd = cnp_convert_date_or_fallback_today( $pd );
    $meta = [ '_cnp_publish_date' => $pd_ymd, '_cnp_edugrant' => $edugrant ];
    if ( ! empty( $es ) ) $meta['_cnp_event_start']   = cnp_convert_date_or_fallback_today( $es );
    if ( ! empty( $du ) ) $meta['_cnp_display_until']  = cnp_convert_date_or_fallback_today( $du );
    if ( ! empty( $loc ) ) $meta['_cnp_event_location'] = $loc;

    $pid = wp_insert_post( [
        'post_title'    => $title,
        'post_content'  => $content,
        'post_status'   => 'publish',
        'post_type'     => 'newsbereich',
        'post_author'   => get_current_user_id(),
        'meta_input'    => $meta,
        'post_category' => $cats,
    ] );

    if ( is_wp_error( $pid ) ) {
        wp_send_json_error( [ 'message' => 'Fehler: ' . $pid->get_error_message() ] );
    }

    // Beitragsbild
    if ( ! empty( $_FILES['cnp_featured_image']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $att_id = media_handle_upload( 'cnp_featured_image', $pid );
        if ( ! is_wp_error( $att_id ) ) set_post_thumbnail( $pid, $att_id );
    }

    if ( $url ) update_post_meta( $pid, '_cnp_news_url', $url );

    wp_send_json_success( [ 'message' => 'Neuer Eintrag erstellt.' ] );
});

/* ============================================================
 * Edit
 * ============================================================ */
add_action( 'wp_ajax_cnp_news_edit', function() {
    check_ajax_referer( 'cnp_news_nonce', 'nonce' );

    $edit_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $edit_id || ! current_user_can( 'edit_post', $edit_id ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    }

    $title   = wp_kses_post( $_POST['cnp_news_title'] ?? '' );
    $content = $_POST['cnp_news_content'] ?? '';
    if ( empty( $title ) || empty( $content ) ) {
        wp_send_json_error( [ 'message' => 'Titel und Inhalt sind erforderlich.' ] );
    }

    $pd  = trim( $_POST['cnp_publish_date'] ?? '' );
    $es  = trim( $_POST['cnp_event_start'] ?? '' );
    $du  = trim( $_POST['cnp_display_until'] ?? '' );
    $loc = sanitize_text_field( $_POST['cnp_event_location'] ?? '' );
    $url = esc_url_raw( $_POST['cnp_news_url'] ?? '' );
    $cats = isset( $_POST['cnp_news_category'] ) ? array_map( 'intval', (array) $_POST['cnp_news_category'] ) : [];
    $new_cat = sanitize_text_field( $_POST['cnp_new_category'] ?? '' );
    $edugrant = isset( $_POST['cnp_edugrant'] ) ? 1 : 0;

    if ( ! empty( $new_cat ) ) {
        $term = get_term_by( 'name', $new_cat, 'category' );
        if ( ! $term ) {
            $nt = wp_insert_term( $new_cat, 'category' );
            if ( ! is_wp_error( $nt ) ) $cats[] = (int) $nt['term_id'];
        } else {
            $cats[] = (int) $term->term_id;
        }
    }
    cnp_ensure_category_allgemein( $cats );

    $pd_ymd = cnp_convert_date_or_fallback_today( $pd );
    $meta = [ '_cnp_publish_date' => $pd_ymd, '_cnp_edugrant' => $edugrant ];

    if ( ! empty( $es ) ) $meta['_cnp_event_start'] = cnp_convert_date_or_fallback_today( $es );
    else delete_post_meta( $edit_id, '_cnp_event_start' );

    if ( ! empty( $du ) ) $meta['_cnp_display_until'] = cnp_convert_date_or_fallback_today( $du );
    else delete_post_meta( $edit_id, '_cnp_display_until' );

    if ( ! empty( $loc ) ) $meta['_cnp_event_location'] = $loc;
    else delete_post_meta( $edit_id, '_cnp_event_location' );

    $res = wp_update_post( [
        'ID'            => $edit_id,
        'post_title'    => $title,
        'post_content'  => $content,
        'meta_input'    => $meta,
        'post_category' => $cats,
    ], true );

    if ( is_wp_error( $res ) ) {
        wp_send_json_error( [ 'message' => 'Fehler: ' . $res->get_error_message() ] );
    }

    // Beitragsbild
    if ( ! empty( $_FILES['cnp_featured_image']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $att_id = media_handle_upload( 'cnp_featured_image', $edit_id );
        if ( ! is_wp_error( $att_id ) ) set_post_thumbnail( $edit_id, $att_id );
    }

    update_post_meta( $edit_id, '_cnp_news_url', $url );

    wp_send_json_success( [ 'message' => 'Eintrag aktualisiert.' ] );
});

/* ============================================================
 * Delete
 * ============================================================ */
add_action( 'wp_ajax_cnp_news_delete', function() {
    check_ajax_referer( 'cnp_news_nonce', 'nonce' );

    $del_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $del_id || ! current_user_can( 'delete_post', $del_id ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    }

    wp_delete_post( $del_id, true );
    wp_send_json_success( [ 'message' => 'Eintrag gelöscht.' ] );
});

/* ============================================================
 * Load List (Refresh nach CRUD)
 * ============================================================ */
add_action( 'wp_ajax_cnp_news_load_list', function() {
    check_ajax_referer( 'cnp_news_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_newsbereiche' ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    }

    $html = cnp_render_news_list();
    wp_send_json_success( [ 'html' => $html ] );
});
