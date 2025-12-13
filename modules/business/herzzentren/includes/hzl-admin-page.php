<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin-Menü-Eintrag
 */
function hbl_register_admin_page() {
    add_menu_page(
        'Herzzentren Benutzer Liste',
        'Herzzentren Benutzer',
        'manage_options',
        'herzzentren-benutzer-liste',
        'hbl_display_admin_page',
        'dashicons-groups',
        6
    );
}
add_action( 'admin_menu', 'hbl_register_admin_page' );

/**
 * Admin-Seite anzeigen
 */
function hbl_display_admin_page() {
    ?>
    <div class="wrap">
        <h1>Herzzentren Benutzer Liste</h1>
        <?php echo do_shortcode( '[herzzentren_benutzer_liste]' ); ?>
    </div>
    <?php
}
