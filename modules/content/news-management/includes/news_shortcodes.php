<?php
/**
 * Shortcode: [list_creation_edit_modal]
 * Dashboard-kompatibel: Alle Operationen via AJAX, kein Page-Reload.
 */

if ( ! function_exists( 'cnp_get_req' ) ) {
    function cnp_get_req( $key, $default = '' ) {
        return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( $_REQUEST[ $key ] ) : $default;
    }
}

if ( ! function_exists( 'cnp_category_checklist' ) ) {
    function cnp_category_checklist( $cats, $depth = 0, $selected = [] ) {
        foreach ( $cats as $c ) {
            if ( (int) $c->parent !== $depth ) continue;
            $chk = in_array( (int) $c->term_id, $selected ) ? 'checked' : '';
            echo '<label style="display:block;margin-left:' . ( $depth ? 15 : 0 ) . 'px">';
            echo '<input type="checkbox" name="cnp_news_category[]" value="' . intval( $c->term_id ) . '" ' . $chk . '> ';
            echo esc_html( $c->name ) . '</label>';
            cnp_category_checklist( $cats, $c->term_id, $selected );
        }
    }
}

/**
 * Rendert die News-Liste + Edit-Modals (wird vom Shortcode UND vom AJAX-Handler genutzt).
 */
function cnp_render_news_list() {
    $all_cats = get_categories( [ 'taxonomy' => 'category', 'hide_empty' => false ] );
    if ( ! is_array( $all_cats ) ) $all_cats = [];

    $args = [
        'post_type'      => 'newsbereich',
        'post_status'    => [ 'publish', 'draft', 'future' ],
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    if ( ! current_user_can( 'edit_others_newsbereiche' ) ) {
        $args['author'] = get_current_user_id();
    }
    $q = new WP_Query( $args );

    ob_start();

    if ( ! $q->have_posts() ) {
        echo '<p>Keine Eintr&auml;ge gefunden.</p>';
    } else {
        echo '<table class="widefat fixed striped"><thead><tr>';
        echo '<th>Bild</th><th>Titel</th><th>Datum</th><th>Status</th><th>Publiziert?</th><th>Aktionen</th>';
        echo '</tr></thead><tbody>';

        while ( $q->have_posts() ) {
            $q->the_post();
            $nid = get_the_ID();
            $pd  = get_post_meta( $nid, '_cnp_publish_date', true );
            $pd_str = $pd ? date_i18n( 'd.m.Y', strtotime( $pd ) ) : '-';
            $thumb  = has_post_thumbnail( $nid ) ? get_the_post_thumbnail( $nid, 'cnp_news_thumbnail' ) : 'Kein Bild';
            $is_pub = ( get_post_status( $nid ) === 'publish' );
            $st_obj = get_post_status_object( get_post_status( $nid ) );
            $st_lbl = $st_obj ? $st_obj->label : '-';
            ?>
            <tr>
                <td><?php echo $thumb; ?></td>
                <td><?php echo esc_html( get_the_title() ); ?></td>
                <td><?php echo esc_html( $pd_str ); ?></td>
                <td class="cnp-status-label"><?php echo esc_html( $st_lbl ); ?></td>
                <td>
                    <label class="cnp-switch">
                        <input type="checkbox" class="cnp-toggle-publish" data-postid="<?php echo intval( $nid ); ?>" <?php checked( $is_pub ); ?>>
                        <span class="cnp-slider"></span>
                    </label>
                </td>
                <td>
                    <div style="display:flex;gap:10px;align-items:center">
                        <button type="button" class="button cnp-open-edit-modal" data-postid="<?php echo intval( $nid ); ?>">Bearbeiten</button>
                        <button type="button" class="button button-secondary cnp-delete-btn" data-postid="<?php echo intval( $nid ); ?>">L&ouml;schen</button>
                    </div>
                </td>
            </tr>
            <?php
        }
        echo '</tbody></table>';
        wp_reset_postdata();
    }

    // Edit-Modals
    $q->rewind_posts();
    while ( $q->have_posts() ) {
        $q->the_post();
        $nid = get_the_ID();
        $title_val = get_the_title();
        $cont_val  = get_the_content();
        $pd  = get_post_meta( $nid, '_cnp_publish_date', true );
        $pd_val = $pd ? date_i18n( 'd.m.Y', strtotime( $pd ) ) : '';
        $es  = get_post_meta( $nid, '_cnp_event_start', true );
        $es_val = $es ? date_i18n( 'd.m.Y', strtotime( $es ) ) : '';
        $du  = get_post_meta( $nid, '_cnp_display_until', true );
        $du_val = $du ? date_i18n( 'd.m.Y', strtotime( $du ) ) : '';
        $loc_val = get_post_meta( $nid, '_cnp_event_location', true );
        $url_val = get_post_meta( $nid, '_cnp_news_url', true );
        $cats_sel = wp_get_post_terms( $nid, 'category', [ 'fields' => 'ids' ] );
        $edugrant_val = get_post_meta( $nid, '_cnp_edugrant', true );
        $is_event = ( ! empty( $es_val ) || ! empty( $loc_val ) || $edugrant_val == 1 );
        $modal_id = 'cnp-modal-edit-' . $nid;
        ?>
        <div id="<?php echo esc_attr( $modal_id ); ?>" class="cnp-modal-overlay">
          <div class="cnp-modal-content">
            <button type="button" class="cnp-close-modal" data-close="#<?php echo esc_attr( $modal_id ); ?>">&times;</button>
            <h2>Bearbeiten (ID: <?php echo intval( $nid ); ?>)</h2>
            <div style="margin-bottom:10px">
              <strong>Typ:</strong>
              <label class="cnp-switch">
                <input type="checkbox" class="cnp-type-switch-edit" id="cnp-type-switch-edit-<?php echo intval( $nid ); ?>" <?php checked( $is_event ); ?>>
                <span class="cnp-slider"></span>
              </label>
              <span id="cnp-type-label-edit-<?php echo intval( $nid ); ?>"><?php echo $is_event ? 'Veranstaltung' : 'News'; ?></span>
            </div>
            <form class="cnp-ajax-form" data-action="cnp_news_edit" enctype="multipart/form-data">
              <input type="hidden" name="post_id" value="<?php echo intval( $nid ); ?>">
              <div style="margin-bottom:10px"><label>Titel*:</label><br>
                <input type="text" name="cnp_news_title" style="width:100%" value="<?php echo esc_attr( $title_val ); ?>"></div>
              <div style="margin-bottom:10px"><label>Inhalt*:</label><br>
                <textarea name="cnp_news_content" rows="12" style="width:100%"><?php echo esc_textarea( $cont_val ); ?></textarea></div>
              <div style="margin-bottom:10px"><label>Ver&ouml;ffentlichungsdatum (dd.mm.yyyy):</label><br>
                <input type="text" name="cnp_publish_date" style="width:100%" value="<?php echo esc_attr( $pd_val ); ?>"></div>
              <div id="cnp-event-fields-edit-<?php echo intval( $nid ); ?>" style="display:<?php echo $is_event ? 'block' : 'none'; ?>">
                <div style="margin-bottom:10px"><label>Veranstaltungsstart (dd.mm.yyyy):</label><br>
                  <input type="text" name="cnp_event_start" style="width:100%" value="<?php echo esc_attr( $es_val ); ?>"></div>
              </div>
              <div style="margin-bottom:10px">
                <label id="cnp_display_until_label_edit-<?php echo intval( $nid ); ?>"><?php echo $is_event ? 'Veranstaltungsende (dd.mm.yyyy):' : 'Anzeigen bis (dd.mm.yyyy):'; ?></label><br>
                <input type="text" name="cnp_display_until" style="width:100%" value="<?php echo esc_attr( $du_val ); ?>"></div>
              <div id="cnp-event-fields-edit-<?php echo intval( $nid ); ?>-2" style="display:<?php echo $is_event ? 'block' : 'none'; ?>">
                <div style="margin-bottom:10px"><label>Ort:</label><br>
                  <input type="text" name="cnp_event_location" style="width:100%" value="<?php echo esc_attr( $loc_val ); ?>"></div>
                <div style="margin-bottom:10px"><label>Edugrant:</label>
                  <label class="cnp-switch"><input type="checkbox" name="cnp_edugrant" value="1" <?php checked( $edugrant_val, 1 ); ?>><span class="cnp-slider"></span></label></div>
              </div>
              <div style="margin-bottom:10px"><label>Kategorien:</label><br>
                <?php cnp_category_checklist( $all_cats, 0, $cats_sel ); ?></div>
              <div style="margin-bottom:10px"><label>Neue Kategorie:</label><br>
                <input type="text" name="cnp_new_category" style="width:100%"></div>
              <div style="margin-bottom:10px"><label>Beitragsbild:</label><br>
                <input type="file" name="cnp_featured_image" accept="image/*"></div>
              <div style="margin-bottom:10px"><label>Externe URL:</label><br>
                <input type="url" name="cnp_news_url" style="width:100%" value="<?php echo esc_attr( $url_val ); ?>"></div>
              <button type="submit" class="button button-primary">Speichern</button>
            </form>
          </div>
        </div>
        <?php
    }
    wp_reset_postdata();

    return ob_get_clean();
}


/* ============================================================
 * Shortcode
 * ============================================================ */
function cnp_list_creation_edit_modal_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>Bitte logge dich ein, um News verwalten zu k&ouml;nnen.</p>';
    }
    if ( ! current_user_can( 'edit_newsbereiche' ) ) {
        return '<p>Keine Berechtigung.</p>';
    }

    $all_cats = get_categories( [ 'taxonomy' => 'category', 'hide_empty' => false ] );
    if ( ! is_array( $all_cats ) ) $all_cats = [];

    ob_start();
    ?>
    <style>
    .cnp-switch{position:relative;display:inline-block;width:50px;height:24px}
    .cnp-switch input{opacity:0;width:0;height:0}
    .cnp-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;transition:.4s;border-radius:24px}
    .cnp-slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;transition:.4s;border-radius:50%}
    input:checked+.cnp-slider{background:#2196F3}
    input:checked+.cnp-slider:before{transform:translateX(26px)}
    </style>

    <button type="button" class="button" id="cnp-open-create-modal">+ Neuer Eintrag</button>

    <!-- Create Modal -->
    <div id="cnp-modal-create" class="cnp-modal-overlay">
      <div class="cnp-modal-content">
        <button type="button" class="cnp-close-modal" data-close="#cnp-modal-create">&times;</button>
        <h2>Neuen Eintrag erstellen</h2>
        <div style="margin-bottom:10px">
          <strong>Typ:</strong>
          <label class="cnp-switch"><input type="checkbox" id="cnp-type-switch-create"><span class="cnp-slider"></span></label>
          <span id="cnp-type-label-create">News</span>
        </div>
        <form class="cnp-ajax-form" data-action="cnp_news_create" enctype="multipart/form-data">
          <div style="margin-bottom:10px"><label>Titel*:</label><br>
            <input type="text" name="cnp_news_title" style="width:100%"></div>
          <div style="margin-bottom:10px"><label>Inhalt*:</label><br>
            <textarea name="cnp_news_content" rows="12" style="width:100%"></textarea></div>
          <div style="margin-bottom:10px"><label>Ver&ouml;ffentlichungsdatum (dd.mm.yyyy):</label><br>
            <input type="text" name="cnp_publish_date" style="width:100%"></div>
          <div style="margin-bottom:10px">
            <label id="cnp_display_until_label_create">Anzeigen bis (dd.mm.yyyy):</label><br>
            <input type="text" name="cnp_display_until" style="width:100%"></div>
          <div id="cnp-event-fields-create" style="display:none">
            <div style="margin-bottom:10px"><label>Veranstaltungsstart (dd.mm.yyyy):</label><br>
              <input type="text" name="cnp_event_start" style="width:100%"></div>
            <div style="margin-bottom:10px"><label>Ort:</label><br>
              <input type="text" name="cnp_event_location" style="width:100%"></div>
            <div style="margin-bottom:10px"><label>Edugrant:</label>
              <label class="cnp-switch"><input type="checkbox" name="cnp_edugrant" value="1"><span class="cnp-slider"></span></label></div>
          </div>
          <div style="margin-bottom:10px"><label>Kategorien:</label><br>
            <?php cnp_category_checklist( $all_cats ); ?></div>
          <div style="margin-bottom:10px"><label>Neue Kategorie:</label><br>
            <input type="text" name="cnp_new_category" style="width:100%"></div>
          <div style="margin-bottom:10px"><label>Beitragsbild:</label><br>
            <input type="file" name="cnp_featured_image" accept="image/*"></div>
          <div style="margin-bottom:10px"><label>Externe URL:</label><br>
            <input type="url" name="cnp_news_url" style="width:100%" placeholder="https://..."></div>
          <button type="submit" class="button button-primary">Erstellen</button>
        </form>
      </div>
    </div>

    <hr style="margin:1em 0">
    <h2>Liste aller Eintr&auml;ge</h2>

    <div id="cnp-news-content-area">
        <?php echo cnp_render_news_list(); ?>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode( 'list_creation_edit_modal', 'cnp_list_creation_edit_modal_shortcode' );

// Synchronisation des Veröffentlichungsdatums mit dem WordPress-Beitragsdatum
add_filter( 'wp_insert_post_data', function( $data, $postarr ) {
    if ( isset( $postarr['meta_input']['_cnp_publish_date'] ) && $data['post_type'] === 'newsbereich' ) {
        $pd = $postarr['meta_input']['_cnp_publish_date'];
        if ( ! empty( $pd ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $pd ) ) {
            $data['post_date']     = $pd . ' 00:00:00';
            $data['post_date_gmt'] = get_gmt_from_date( $pd . ' 00:00:00' );
        }
    }
    return $data;
}, 10, 2 );
