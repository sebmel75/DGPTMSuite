<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function dgptm_admin_instructions_page() { ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'OTP Login – Anleitung', 'dgptm' ); ?></h1>
        <h2><?php esc_html_e( 'Shortcodes', 'dgptm' ); ?></h2>
        <ul>
            <li><code>[dgptm_otp_login redirect="/mein-bereich/"]</code></li>
            <li><code>[dgptm_logout_link text="Abmelden" redirect="/"]</code></li>
            <li><code>[dgptm_logout_url redirect="/"]</code> – URL nur für Button-Link-Felder</li>
        </ul>
        <h2><?php esc_html_e( 'Statische Logout-Route (ohne Shortcode)', 'dgptm' ); ?></h2>
        <p><code>/dgptm-logout?redirect=/</code></p>
        <h2><?php esc_html_e( 'WP-Login sperren', 'dgptm' ); ?></h2>
        <p><?php esc_html_e( 'In den E-Mail/Sicherheits-Einstellungen aktivieren. Notfall-Bypass: ?otp_bypass=1', 'dgptm' ); ?></p>
        <h2><?php esc_html_e( 'Multisite', 'dgptm' ); ?></h2>
        <p><?php esc_html_e( 'Einstellungen sind sowohl als eigener Menüpunkt als auch unter „Einstellungen“ (sowie im Netzwerk-Admin) erreichbar.', 'dgptm' ); ?></p>
    </div>
<?php }
