<?php
/**
 * Microsoft OAuth Admin-Einstellungen
 *
 * @package DGPTM_OTP_Login
 * @since 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registriert die OAuth-Einstellungen
 */
function dgptm_oauth_register_settings() {
    // Microsoft OAuth Einstellungen
    register_setting( 'dgptm_oauth_settings', 'dgptm_oauth_microsoft_enabled', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 0,
    ] );

    register_setting( 'dgptm_oauth_settings', 'dgptm_oauth_microsoft_client_id', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );

    register_setting( 'dgptm_oauth_settings', 'dgptm_oauth_microsoft_client_secret', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );

    register_setting( 'dgptm_oauth_settings', 'dgptm_oauth_login_page', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_url',
        'default'           => '',
    ] );
}
add_action( 'admin_init', 'dgptm_oauth_register_settings' );

/**
 * Rendert die OAuth-Einstellungsseite
 */
function dgptm_admin_oauth_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Zugriff verweigert.', 'dgptm' ) );
    }

    // Einstellungen speichern
    if ( isset( $_POST['dgptm_oauth_save'] ) && check_admin_referer( 'dgptm_oauth_settings_nonce' ) ) {
        dgptm_update_option( 'dgptm_oauth_microsoft_enabled', isset( $_POST['dgptm_oauth_microsoft_enabled'] ) ? 1 : 0 );
        dgptm_update_option( 'dgptm_oauth_microsoft_client_id', sanitize_text_field( wp_unslash( $_POST['dgptm_oauth_microsoft_client_id'] ?? '' ) ) );

        // Client Secret nur aktualisieren wenn ausgefüllt (nicht überschreiben mit leer)
        $new_secret = sanitize_text_field( wp_unslash( $_POST['dgptm_oauth_microsoft_client_secret'] ?? '' ) );
        if ( ! empty( $new_secret ) ) {
            dgptm_update_option( 'dgptm_oauth_microsoft_client_secret', $new_secret );
        }

        dgptm_update_option( 'dgptm_oauth_login_page', sanitize_url( wp_unslash( $_POST['dgptm_oauth_login_page'] ?? '' ) ) );
        dgptm_update_option( 'dgptm_oauth_popup_id', absint( $_POST['dgptm_oauth_popup_id'] ?? 0 ) );

        echo '<div class="notice notice-success"><p>' . esc_html__( 'Einstellungen gespeichert.', 'dgptm' ) . '</p></div>';
    }

    // Aktuelle Werte laden
    $enabled      = (int) dgptm_get_option( 'dgptm_oauth_microsoft_enabled', 0 );
    $client_id    = trim( (string) dgptm_get_option( 'dgptm_oauth_microsoft_client_id', '' ) );
    $client_secret = trim( (string) dgptm_get_option( 'dgptm_oauth_microsoft_client_secret', '' ) );
    $login_page   = trim( (string) dgptm_get_option( 'dgptm_oauth_login_page', '' ) );
    $popup_id     = (int) dgptm_get_option( 'dgptm_oauth_popup_id', 32160 );

    // Callback URL für Azure-Konfiguration
    $callback_url = rest_url( 'dgptm/v1/oauth/microsoft/callback' );

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Microsoft OAuth Einstellungen', 'dgptm' ); ?></h1>

        <div class="dgptm-oauth-info" style="background: #f0f6fc; border-left: 4px solid #0078d4; padding: 15px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #0078d4;">
                <span class="dashicons dashicons-info" style="margin-right: 5px;"></span>
                <?php esc_html_e( 'Azure App-Registrierung', 'dgptm' ); ?>
            </h3>
            <p><?php esc_html_e( 'Um Microsoft Login zu aktivieren, müssen Sie eine App im Azure Portal registrieren:', 'dgptm' ); ?></p>
            <ol style="margin-left: 20px;">
                <li><?php esc_html_e( 'Gehen Sie zu', 'dgptm' ); ?> <a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank">Azure Portal → App-Registrierungen</a></li>
                <li><?php esc_html_e( 'Klicken Sie auf "Neue Registrierung"', 'dgptm' ); ?></li>
                <li><?php esc_html_e( 'Wählen Sie bei "Unterstützte Kontotypen":', 'dgptm' ); ?> <strong><?php esc_html_e( 'Konten in einem beliebigen Organisationsverzeichnis und persönliche Microsoft-Konten', 'dgptm' ); ?></strong></li>
                <li><?php esc_html_e( 'Fügen Sie folgende Umleitungs-URI hinzu (Typ: Web):', 'dgptm' ); ?>
                    <code style="background: #fff; padding: 5px 10px; display: block; margin: 10px 0; border: 1px solid #ddd; user-select: all;"><?php echo esc_html( $callback_url ); ?></code>
                </li>
                <li><?php esc_html_e( 'Nach der Registrierung: Kopieren Sie die "Anwendungs-ID (Client-ID)"', 'dgptm' ); ?></li>
                <li><?php esc_html_e( 'Gehen Sie zu "Zertifikate & Geheimnisse" → "Neuer geheimer Clientschlüssel"', 'dgptm' ); ?></li>
                <li><?php esc_html_e( 'Kopieren Sie den Wert des Geheimnisses (nur einmalig sichtbar!)', 'dgptm' ); ?></li>
            </ol>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field( 'dgptm_oauth_settings_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Microsoft Login aktivieren', 'dgptm' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dgptm_oauth_microsoft_enabled" value="1" <?php checked( $enabled, 1 ); ?> />
                            <?php esc_html_e( 'Microsoft-Button im Login-Formular anzeigen', 'dgptm' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Wenn aktiviert, erscheint ein "Mit Microsoft anmelden"-Button unter dem OTP-Formular.', 'dgptm' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dgptm_oauth_microsoft_client_id"><?php esc_html_e( 'Client-ID (Application ID)', 'dgptm' ); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="dgptm_oauth_microsoft_client_id"
                               name="dgptm_oauth_microsoft_client_id"
                               value="<?php echo esc_attr( $client_id ); ?>"
                               class="regular-text"
                               placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                        />
                        <p class="description">
                            <?php esc_html_e( 'Die Anwendungs-ID aus dem Azure Portal.', 'dgptm' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dgptm_oauth_microsoft_client_secret"><?php esc_html_e( 'Client Secret', 'dgptm' ); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               id="dgptm_oauth_microsoft_client_secret"
                               name="dgptm_oauth_microsoft_client_secret"
                               value=""
                               class="regular-text"
                               placeholder="<?php echo $client_secret ? esc_attr( '••••••••••••••••' ) : ''; ?>"
                        />
                        <p class="description">
                            <?php if ( $client_secret ) : ?>
                                <span style="color: green;">✓ <?php esc_html_e( 'Secret ist gespeichert. Leer lassen um beizubehalten.', 'dgptm' ); ?></span>
                            <?php else : ?>
                                <?php esc_html_e( 'Der geheime Clientschlüssel aus dem Azure Portal.', 'dgptm' ); ?>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dgptm_oauth_login_page"><?php esc_html_e( 'Login-Seiten-URL', 'dgptm' ); ?></label>
                    </th>
                    <td>
                        <input type="url"
                               id="dgptm_oauth_login_page"
                               name="dgptm_oauth_login_page"
                               value="<?php echo esc_attr( $login_page ); ?>"
                               class="regular-text"
                               placeholder="<?php echo esc_attr( home_url( '/login/' ) ); ?>"
                        />
                        <p class="description">
                            <?php esc_html_e( 'URL der Seite mit dem [dgptm_otp_login] Shortcode. Wird für Fehler-Redirects verwendet.', 'dgptm' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dgptm_oauth_popup_id"><?php esc_html_e( 'Elementor Popup ID', 'dgptm' ); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="dgptm_oauth_popup_id"
                               name="dgptm_oauth_popup_id"
                               value="<?php echo esc_attr( $popup_id ); ?>"
                               class="small-text"
                               min="0"
                        />
                        <p class="description">
                            <?php esc_html_e( 'Post-ID des Elementor Popups mit dem Login-Formular. Bei OAuth-Fehlern wird dieses Popup automatisch geöffnet.', 'dgptm' ); ?>
                            <br>
                            <?php esc_html_e( 'Leer lassen oder 0 = Popup nicht automatisch öffnen.', 'dgptm' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php if ( $enabled && $client_id && $client_secret ) : ?>
                <div class="dgptm-oauth-status" style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #28a745;">
                        <span class="dashicons dashicons-yes-alt" style="margin-right: 5px;"></span>
                        <?php esc_html_e( 'Microsoft Login ist aktiv', 'dgptm' ); ?>
                    </h3>
                    <p><?php esc_html_e( 'Der Microsoft-Login-Button wird im OTP-Formular angezeigt.', 'dgptm' ); ?></p>
                    <p>
                        <strong><?php esc_html_e( 'Callback-URL:', 'dgptm' ); ?></strong><br>
                        <code style="user-select: all;"><?php echo esc_html( $callback_url ); ?></code>
                    </p>
                </div>
            <?php elseif ( $enabled && ( empty( $client_id ) || empty( $client_secret ) ) ) : ?>
                <div class="dgptm-oauth-status" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #856404;">
                        <span class="dashicons dashicons-warning" style="margin-right: 5px;"></span>
                        <?php esc_html_e( 'Konfiguration unvollständig', 'dgptm' ); ?>
                    </h3>
                    <p><?php esc_html_e( 'Microsoft Login ist aktiviert, aber Client-ID oder Client Secret fehlen.', 'dgptm' ); ?></p>
                </div>
            <?php endif; ?>

            <p class="submit">
                <input type="submit" name="dgptm_oauth_save" class="button button-primary" value="<?php esc_attr_e( 'Einstellungen speichern', 'dgptm' ); ?>" />
            </p>
        </form>

        <hr style="margin: 40px 0;">

        <h2><?php esc_html_e( 'Funktionsweise', 'dgptm' ); ?></h2>
        <table class="widefat" style="max-width: 800px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Schritt', 'dgptm' ); ?></th>
                    <th><?php esc_html_e( 'Beschreibung', 'dgptm' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>1</strong></td>
                    <td><?php esc_html_e( 'Benutzer klickt auf "Mit Microsoft anmelden"', 'dgptm' ); ?></td>
                </tr>
                <tr>
                    <td><strong>2</strong></td>
                    <td><?php esc_html_e( 'Weiterleitung zu Microsoft Login (Account-Auswahl)', 'dgptm' ); ?></td>
                </tr>
                <tr>
                    <td><strong>3</strong></td>
                    <td><?php esc_html_e( 'Benutzer meldet sich bei Microsoft an', 'dgptm' ); ?></td>
                </tr>
                <tr>
                    <td><strong>4</strong></td>
                    <td><?php esc_html_e( 'Microsoft sendet E-Mail-Adresse zurück', 'dgptm' ); ?></td>
                </tr>
                <tr>
                    <td><strong>5</strong></td>
                    <td><?php esc_html_e( 'System sucht WordPress-Benutzer mit dieser E-Mail', 'dgptm' ); ?></td>
                </tr>
                <tr>
                    <td><strong>6a</strong></td>
                    <td style="color: green;"><?php esc_html_e( '✓ Benutzer gefunden → Automatischer Login', 'dgptm' ); ?></td>
                </tr>
                <tr>
                    <td><strong>6b</strong></td>
                    <td style="color: red;"><?php esc_html_e( '✗ Kein Benutzer gefunden → Fehlermeldung', 'dgptm' ); ?></td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin-top: 30px;"><?php esc_html_e( 'Sicherheitsmerkmale', 'dgptm' ); ?></h3>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><?php esc_html_e( 'OAuth 2.0 mit PKCE (Proof Key for Code Exchange)', 'dgptm' ); ?></li>
            <li><?php esc_html_e( 'State-Parameter zum CSRF-Schutz', 'dgptm' ); ?></li>
            <li><?php esc_html_e( 'Tokens sind nur 10 Minuten gültig', 'dgptm' ); ?></li>
            <li><?php esc_html_e( 'Einmalverwendung aller Sicherheitstokens', 'dgptm' ); ?></li>
            <li><?php esc_html_e( 'Nur verifizierte E-Mails von Microsoft werden akzeptiert', 'dgptm' ); ?></li>
            <li><?php esc_html_e( 'Keine automatische Kontoerstellung', 'dgptm' ); ?></li>
        </ul>
    </div>
    <?php
}
