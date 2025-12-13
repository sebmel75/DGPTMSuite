<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function dgptm_admin_email_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Keine Berechtigung.', 'dgptm' ) ); }
    if ( isset( $_POST['dgptm_email_save'] ) && check_admin_referer( 'dgptm_email_save' ) ) {
        dgptm_update_option( 'dgptm_email_subject', sanitize_text_field( wp_unslash( $_POST['dgptm_email_subject'] ?? '' ) ) );
        dgptm_update_option( 'dgptm_email_body', wp_kses_post( wp_unslash( $_POST['dgptm_email_body'] ?? '' ) ) );
        dgptm_update_option( 'dgptm_otp_rate_limit', max( 1, intval( $_POST['dgptm_otp_rate_limit'] ?? 3 ) ) );
        dgptm_update_option( 'dgptm_disable_wp_login', isset( $_POST['dgptm_disable_wp_login'] ) ? 1 : 0 );
        dgptm_update_option( 'dgptm_webhook_enable', isset( $_POST['dgptm_webhook_enable'] ) ? 1 : 0 );
        dgptm_update_option( 'dgptm_webhook_url', esc_url_raw( wp_unslash( $_POST['dgptm_webhook_url'] ?? '' ) ) );
        echo '<div class="updated"><p>' . esc_html__( 'Gespeichert.', 'dgptm' ) . '</p></div>';
    }
    $subject = (string) dgptm_get_option( 'dgptm_email_subject', '' );
    $body    = (string) dgptm_get_option( 'dgptm_email_body', '' );
    $limit   = (int) dgptm_get_option( 'dgptm_otp_rate_limit', 3 );
    $disable = (int) dgptm_get_option( 'dgptm_disable_wp_login', 0 );
    $wh_on   = (int) dgptm_get_option( 'dgptm_webhook_enable', 0 );
    $wh_url  = (string) dgptm_get_option( 'dgptm_webhook_url', '' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'OTP Login – E-Mail & Sicherheit', 'dgptm' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'dgptm_email_save' ); ?>
            <table class="form-table">
                <tr><th scope="row"><label for="dgptm_email_subject"><?php esc_html_e( 'Betreff', 'dgptm' ); ?></label></th>
                    <td><input name="dgptm_email_subject" id="dgptm_email_subject" type="text" class="regular-text" value="<?php echo esc_attr( $subject ); ?>"></td></tr>
                <tr><th scope="row"><label for="dgptm_email_body"><?php esc_html_e( 'E-Mail Text (Platzhalter siehe unten)', 'dgptm' ); ?></label></th>
                    <td><textarea name="dgptm_email_body" id="dgptm_email_body" rows="10" class="large-text code"><?php echo esc_textarea( $body ); ?></textarea></td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Rate-Limit (Sendeversuche / 10 Minuten)', 'dgptm' ); ?></th>
                    <td><input name="dgptm_otp_rate_limit" type="number" min="1" step="1" value="<?php echo esc_attr( $limit ); ?>"></td></tr>
                <tr><th scope="row"><?php esc_html_e( 'WP-Login deaktivieren', 'dgptm' ); ?></th>
                    <td><label><input type="checkbox" name="dgptm_disable_wp_login" <?php checked( $disable, 1 ); ?>> <?php esc_html_e( 'wp-login.php für Nicht‑Admins sperren (Bypass: ?otp_bypass=1)', 'dgptm' ); ?></label></td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Webhook bei Versand', 'dgptm' ); ?></th>
                    <td><label style="display:flex;gap:.5rem;align-items:center;"><input type="checkbox" name="dgptm_webhook_enable" <?php checked( $wh_on, 1 ); ?>><span><?php esc_html_e( 'Aktivieren', 'dgptm' ); ?></span></label>
                        <input name="dgptm_webhook_url" type="url" class="regular-text" placeholder="https://…" value="<?php echo esc_attr( $wh_url ); ?>">
                        <p class="description"><?php esc_html_e( 'Es werden keine OTPs übertragen. Ereignis: otp_sent', 'dgptm' ); ?></p></td></tr>
            </table>
            <p class="submit"><button class="button button-primary" name="dgptm_email_save" value="1"><?php esc_html_e( 'Speichern', 'dgptm' ); ?></button></p>
        </form>
        <h2><?php esc_html_e( 'Platzhalter', 'dgptm' ); ?></h2>
        <ul>
            <li><code>{site_name}</code> - Name der Website</li>
            <li><code>{user_login}</code> - Benutzername</li>
            <li><code>{user_email}</code> - E-Mail-Adresse</li>
            <li><code>{display_name}</code> - Anzeigename</li>
            <li><code>{otp}</code> oder <code>{code}</code> - Der Einmal-Code</li>
            <li><code>{otp_valid_minutes}</code> - Gültigkeit in Minuten</li>
        </ul>
    </div>
    <?php
}
