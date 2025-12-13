<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function dgptm_admin_preloader_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Keine Berechtigung.', 'dgptm' ) ); }
    if ( isset( $_POST['dgptm_preloader_save'] ) && check_admin_referer( 'dgptm_preloader_save' ) ) {
        dgptm_update_option( 'dgptm_preloader_enabled', isset( $_POST['dgptm_preloader_enabled'] ) ? 1 : 0 );
        dgptm_update_option( 'dgptm_preloader_logo', esc_url_raw( wp_unslash( $_POST['dgptm_preloader_logo'] ?? '' ) ) );
        echo '<div class="updated"><p>' . esc_html__( 'Gespeichert.', 'dgptm' ) . '</p></div>';
    }
    $enabled = (int) dgptm_get_option( 'dgptm_preloader_enabled', 1 );
    $logo    = (string) dgptm_get_option( 'dgptm_preloader_logo', '' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Preloader', 'dgptm' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'dgptm_preloader_save' ); ?>
            <table class="form-table">
                <tr><th scope="row"><?php esc_html_e( 'Aktivieren', 'dgptm' ); ?></th>
                    <td><label><input type="checkbox" name="dgptm_preloader_enabled" <?php checked( $enabled, 1 ); ?>> <?php esc_html_e( 'Preloader anzeigen', 'dgptm' ); ?></label></td></tr>
                <tr><th scope="row"><label for="dgptm_preloader_logo"><?php esc_html_e( 'Logo-URL', 'dgptm' ); ?></label></th>
                    <td><input type="url" class="regular-text" id="dgptm_preloader_logo" name="dgptm_preloader_logo" value="<?php echo esc_attr( $logo ); ?>" placeholder="https://…/logo.png">
                        <p class="description"><?php esc_html_e( 'Optional. Wenn leer, wird eine Minimal-Animation angezeigt.', 'dgptm' ); ?></p></td></tr>
            </table>
            <p class="submit"><button class="button button-primary" name="dgptm_preloader_save" value="1"><?php esc_html_e( 'Speichern', 'dgptm' ); ?></button></p>
        </form>
    </div>
    <?php
}
