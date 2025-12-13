<?php
/**
 * Admin Settings
 *
 * Verwaltung der Einstellungen im WordPress-Backend
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Session_Display_Admin {

    /**
     * Übersicht / Dashboard Seite rendern
     */
    public function render_overview_page() {
        // Sicherstellen dass alle Klassen geladen sind
        if (!class_exists('DGPTM_Session_Manager')) {
            $includes_path = DGPTM_SESSION_DISPLAY_PATH . 'includes/';
            if (file_exists($includes_path . 'class-session-manager.php')) {
                require_once $includes_path . 'class-session-manager.php';
            }
            if (file_exists($includes_path . 'class-zoho-backstage-api.php')) {
                require_once $includes_path . 'class-zoho-backstage-api.php';
            }
        }

        ?>
        <div class="wrap">
            <h1>Session Display - Übersicht</h1>

            <div class="dgptm-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">

                <!-- Status-Card -->
                <div class="card">
                    <h2>System-Status</h2>
                    <?php
                    $has_token = get_option('dgptm_session_display_refresh_token') ? true : false;
                    $event_id = get_option('dgptm_session_display_event_id', '');
                    $last_update = get_option('dgptm_sessions_last_update', 'Noch nie');
                    $next_cron = wp_next_scheduled('dgptm_session_display_update');
                    ?>
                    <table class="widefat">
                        <tr>
                            <td><strong>API-Verbindung:</strong></td>
                            <td>
                                <?php if ($has_token): ?>
                                    <span style="color: green;">✓ Verbunden</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ Nicht verbunden</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Event-ID:</strong></td>
                            <td><?php echo $event_id ? esc_html($event_id) : '<em>Nicht konfiguriert</em>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Letztes Update:</strong></td>
                            <td><?php echo esc_html($last_update); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Nächster Abruf:</strong></td>
                            <td><?php echo $next_cron ? esc_html(date('Y-m-d H:i:s', $next_cron)) : '<em>Nicht geplant</em>'; ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Sessions-Card -->
                <div class="card">
                    <h2>Sessions</h2>
                    <?php
                    try {
                        if (class_exists('DGPTM_Session_Manager')) {
                            $session_manager = new DGPTM_Session_Manager();
                            $all_sessions = $session_manager->get_cached_sessions();
                            $todays_sessions = $session_manager->get_todays_sessions();
                            $all_rooms = $session_manager->get_all_rooms();
                        } else {
                            $all_sessions = [];
                            $todays_sessions = [];
                            $all_rooms = [];
                        }
                    } catch (Exception $e) {
                        $all_sessions = [];
                        $todays_sessions = [];
                        $all_rooms = [];
                        error_log('Session Display Overview Error: ' . $e->getMessage());
                    }
                    ?>
                    <table class="widefat">
                        <tr>
                            <td><strong>Gesamt:</strong></td>
                            <td><?php echo count($all_sessions); ?> Sessions</td>
                        </tr>
                        <tr>
                            <td><strong>Heute:</strong></td>
                            <td><?php echo count($todays_sessions); ?> Sessions</td>
                        </tr>
                        <tr>
                            <td><strong>Räume:</strong></td>
                            <td><?php echo count($all_rooms); ?> Räume</td>
                        </tr>
                    </table>
                    <p style="margin-top: 15px;">
                        <a href="<?php echo admin_url('admin.php?page=dgptm-session-display-settings'); ?>" class="button button-primary">
                            Sessions aktualisieren
                        </a>
                    </p>
                </div>

                <!-- Sponsoren-Card -->
                <div class="card">
                    <h2>Sponsoren</h2>
                    <?php
                    $sponsors = get_option('dgptm_session_display_sponsors', []);
                    $show_sponsors = get_option('dgptm_session_display_show_sponsors', true);
                    ?>
                    <table class="widefat">
                        <tr>
                            <td><strong>Anzahl:</strong></td>
                            <td><?php echo count($sponsors); ?> Sponsoren</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><?php echo $show_sponsors ? '<span style="color: green;">Aktiv</span>' : '<span style="color: gray;">Inaktiv</span>'; ?></td>
                        </tr>
                    </table>
                    <p style="margin-top: 15px;">
                        <a href="<?php echo admin_url('admin.php?page=dgptm-session-display-sponsors'); ?>" class="button">
                            Sponsoren verwalten
                        </a>
                    </p>
                </div>

            </div>

            <h2 style="margin-top: 30px;">Schnellzugriff</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?php echo admin_url('admin.php?page=dgptm-session-display-settings'); ?>" class="button button-large">
                    <span class="dashicons dashicons-admin-settings" style="margin-top: 3px;"></span> Einstellungen
                </a>
                <a href="<?php echo admin_url('admin.php?page=dgptm-session-display-rooms'); ?>" class="button button-large">
                    <span class="dashicons dashicons-admin-home" style="margin-top: 3px;"></span> Raumzuordnung
                </a>
                <a href="<?php echo admin_url('admin.php?page=dgptm-session-display-sponsors'); ?>" class="button button-large">
                    <span class="dashicons dashicons-awards" style="margin-top: 3px;"></span> Sponsoren
                </a>
            </div>

            <h2 style="margin-top: 30px;">Shortcode-Verwendung</h2>
            <div class="card" style="max-width: 800px;">
                <h3>Einzelraum-Display</h3>
                <code>[session_display room="Raum 1" type="current" show_sponsors="true"]</code>
                <p><strong>Parameter:</strong></p>
                <ul>
                    <li><code>room</code> - Raumname (erforderlich)</li>
                    <li><code>type</code> - current, next, oder both (Standard: current)</li>
                    <li><code>show_sponsors</code> - true/false (Standard: true)</li>
                </ul>

                <h3 style="margin-top: 20px;">Raumübersicht</h3>
                <code>[session_overview floor="EG" layout="grid"]</code>
                <p><strong>Parameter:</strong></p>
                <ul>
                    <li><code>floor</code> - Etage filtern (optional)</li>
                    <li><code>rooms</code> - Komma-getrennte Raumliste (optional)</li>
                    <li><code>layout</code> - grid, list, oder timeline (Standard: grid)</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * API-Tester-Seite rendern
     */
    public function render_api_test_page() {
        // API-Klasse laden
        if (!class_exists('DGPTM_Zoho_Backstage_API')) {
            require_once DGPTM_SESSION_DISPLAY_PATH . 'includes/class-zoho-backstage-api.php';
        }

        // API-Request verarbeiten wenn gesendet
        $api_response = null;
        $api_error = null;
        $request_url = '';

        if (isset($_POST['dgptm_api_test_submit'])) {
            check_admin_referer('dgptm_api_test');

            $endpoint = sanitize_text_field($_POST['api_endpoint'] ?? '');
            $portal_id = get_option('dgptm_session_display_portal_id', '20086233464');
            $event_id = get_option('dgptm_session_display_event_id', '');

            // Platzhalter ersetzen
            $endpoint = str_replace('{portal_id}', $portal_id, $endpoint);
            $endpoint = str_replace('{event_id}', $event_id, $endpoint);

            // URL zusammenbauen
            $base_url = 'https://www.zohoapis.eu/backstage/v3';
            $request_url = $base_url . $endpoint;

            // Access Token holen
            $api = new DGPTM_Zoho_Backstage_API();
            $access_token_property = new ReflectionProperty($api, 'access_token');
            $access_token_property->setAccessible(true);
            $access_token = $access_token_property->getValue($api);

            if (!$access_token) {
                $api_error = 'Kein Access Token verfügbar. Bitte zuerst OAuth-Authentifizierung durchführen.';
            } else {
                // API-Request durchführen
                $response = wp_remote_get($request_url, [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                        'Content-Type' => 'application/json'
                    ],
                    'timeout' => 30
                ]);

                if (is_wp_error($response)) {
                    $api_error = $response->get_error_message();
                } else {
                    $status_code = wp_remote_retrieve_response_code($response);
                    $body = wp_remote_retrieve_body($response);

                    $api_response = [
                        'status_code' => $status_code,
                        'body' => $body,
                        'json' => json_decode($body, true)
                    ];
                }
            }
        }

        $portal_id = get_option('dgptm_session_display_portal_id', '20086233464');
        $event_id = get_option('dgptm_session_display_event_id', '');

        ?>
        <div class="wrap">
            <h1>API-Tester</h1>
            <p>Testen Sie die Zoho Backstage API-Endpunkte und sehen Sie die rohen JSON-Antworten.</p>

            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2>API-Konfiguration</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>Base URL:</strong></td>
                        <td><code>https://www.zohoapis.eu/backstage/v3</code></td>
                    </tr>
                    <tr>
                        <td><strong>Portal ID:</strong></td>
                        <td><code><?php echo esc_html($portal_id); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Event ID:</strong></td>
                        <td><code><?php echo esc_html($event_id ?: 'Nicht konfiguriert'); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Access Token:</strong></td>
                        <td>
                            <?php
                            $has_token = get_option('dgptm_session_display_refresh_token') ? true : false;
                            echo $has_token ? '<span style="color: green;">✓ Vorhanden</span>' : '<span style="color: red;">✗ Nicht verfügbar</span>';
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2>API-Request senden</h2>

                <form method="post" action="">
                    <?php wp_nonce_field('dgptm_api_test'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_endpoint">API-Endpoint</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="api_endpoint"
                                       name="api_endpoint"
                                       value="<?php echo esc_attr($_POST['api_endpoint'] ?? "/portals/{$portal_id}/events/"); ?>"
                                       class="large-text"
                                       placeholder="/portals/20086233464/events/">
                                <p class="description">
                                    Geben Sie den Endpoint-Pfad ein (mit oder ohne führenden Slash).<br>
                                    Verwenden Sie <code>{portal_id}</code> und <code>{event_id}</code> als Platzhalter.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h3>Beispiel-Endpoints:</h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><code>/portals/{portal_id}/events/</code> - Alle Events</li>
                        <li><code>/portals/{portal_id}/events/{event_id}/</code> - Event-Details</li>
                        <li><code>/portals/{portal_id}/events/{event_id}/sessions/</code> - Alle Sessions</li>
                        <li><code>/portals/{portal_id}/events/{event_id}/tracks/</code> - Alle Tracks</li>
                        <li><code>/portals/{portal_id}/events/{event_id}/speakers/</code> - Alle Speakers</li>
                    </ul>

                    <p>
                        <button type="submit" name="dgptm_api_test_submit" class="button button-primary">
                            API-Request senden
                        </button>
                    </p>
                </form>
            </div>

            <?php if ($api_error): ?>
                <div class="notice notice-error" style="padding: 20px; margin: 20px 0;">
                    <h3>Fehler</h3>
                    <pre style="background: #f0f0f0; padding: 15px; overflow-x: auto;"><?php echo esc_html($api_error); ?></pre>
                </div>
            <?php endif; ?>

            <?php if ($api_response): ?>
                <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2>API-Antwort</h2>

                    <h3>Request URL:</h3>
                    <pre style="background: #f0f0f0; padding: 15px; overflow-x: auto;"><?php echo esc_html($request_url); ?></pre>

                    <h3>Status Code:</h3>
                    <pre style="background: #f0f0f0; padding: 15px; overflow-x: auto;"><?php echo esc_html($api_response['status_code']); ?></pre>

                    <h3>Rohe Antwort:</h3>
                    <pre style="background: #f0f0f0; padding: 15px; overflow-x: auto; max-height: 400px; overflow-y: auto;"><?php echo esc_html($api_response['body']); ?></pre>

                    <h3>Formatiertes JSON:</h3>
                    <pre style="background: #f0f0f0; padding: 15px; overflow-x: auto; max-height: 600px; overflow-y: auto;"><?php
                    echo esc_html(json_encode($api_response['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    ?></pre>

                    <h3>JSON-Struktur:</h3>
                    <pre style="background: #f0f0f0; padding: 15px; overflow-x: auto; max-height: 400px; overflow-y: auto;"><?php
                    print_r($api_response['json']);
                    ?></pre>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Einstellungen-Seite rendern
     */
    public function render_settings_page() {
        // Sicherstellen dass API-Klasse geladen ist
        if (!class_exists('DGPTM_Zoho_Backstage_API')) {
            $api_file = DGPTM_SESSION_DISPLAY_PATH . 'includes/class-zoho-backstage-api.php';
            if (file_exists($api_file)) {
                require_once $api_file;
            }
        }

        // OAuth-Callback wird jetzt in session-display.php via admin_init verarbeitet
        // Transient-Nachricht anzeigen falls vorhanden
        $oauth_message = get_transient('dgptm_session_display_oauth_message');
        if ($oauth_message) {
            delete_transient('dgptm_session_display_oauth_message');
            add_settings_error(
                'dgptm_session_display',
                'oauth_message',
                $oauth_message['message'],
                $oauth_message['type']
            );
        }

        // Einstellungen speichern
        if (isset($_POST['dgptm_session_display_save_settings'])) {
            $this->save_settings();
        }

        // Verbindung testen
        if (isset($_POST['dgptm_session_display_test_connection'])) {
            $this->test_api_connection();
        }

        // Sessions manuell aktualisieren
        if (isset($_POST['dgptm_session_display_refresh_sessions'])) {
            $this->manual_refresh_sessions();
        }

        $this->display_settings_form();
    }

    /**
     * Einstellungen speichern
     */
    private function save_settings() {
        check_admin_referer('dgptm_session_display_settings');

        // API-Einstellungen
        update_option('dgptm_session_display_client_id', sanitize_text_field($_POST['client_id'] ?? ''));
        update_option('dgptm_session_display_client_secret', sanitize_text_field($_POST['client_secret'] ?? ''));
        update_option('dgptm_session_display_portal_id', sanitize_text_field($_POST['portal_id'] ?? ''));
        update_option('dgptm_session_display_event_id', sanitize_text_field($_POST['event_id'] ?? ''));

        // OAuth-Scopes
        $selected_scopes = isset($_POST['oauth_scopes']) ? (array) $_POST['oauth_scopes'] : [];
        $selected_scopes = array_map('sanitize_text_field', $selected_scopes);
        update_option('dgptm_session_display_oauth_scopes', $selected_scopes);

        // Event-Einstellungen
        update_option('dgptm_session_display_event_date', sanitize_text_field($_POST['event_date'] ?? ''));
        update_option('dgptm_session_display_event_duration', intval($_POST['event_duration'] ?? 1));

        // Display-Einstellungen
        update_option('dgptm_session_display_refresh_interval', intval($_POST['refresh_interval'] ?? 60) * 1000);
        update_option('dgptm_session_display_auto_update_interval', intval($_POST['auto_update_interval'] ?? 300));
        update_option('dgptm_session_display_auto_refresh', isset($_POST['auto_refresh']));
        update_option('dgptm_session_display_show_sponsors', isset($_POST['show_sponsors']));
        update_option('dgptm_session_display_sponsor_interval', intval($_POST['sponsor_interval'] ?? 10) * 1000);

        // Template-Einstellungen
        update_option('dgptm_session_display_template_color', sanitize_hex_color($_POST['template_color'] ?? '#2563eb'));
        update_option('dgptm_session_display_template_logo', esc_url_raw($_POST['template_logo'] ?? ''));

        // NEU: Display-Einstellungen (Vollbild, Hintergründe, Venue-Filter)
        update_option('dgptm_session_display_fullscreen_auto', isset($_POST['fullscreen_auto']));
        update_option('dgptm_session_display_bg_gallery_interval', intval($_POST['bg_gallery_interval'] ?? 30) * 1000);
        update_option('dgptm_session_display_session_rotation_interval', intval($_POST['session_rotation_interval'] ?? 15) * 1000);
        update_option('dgptm_session_display_hide_no_room', isset($_POST['hide_no_room']));

        // NEU v1.1.0: Debug-Einstellungen (Zeit und Datum)
        update_option('dgptm_session_display_debug_enabled', isset($_POST['debug_enabled']));
        update_option('dgptm_session_display_debug_time', sanitize_text_field($_POST['debug_time'] ?? '09:00'));
        update_option('dgptm_session_display_debug_date_mode', sanitize_text_field($_POST['debug_date_mode'] ?? 'off'));
        update_option('dgptm_session_display_debug_date_custom', sanitize_text_field($_POST['debug_date_custom'] ?? date('Y-m-d')));
        update_option('dgptm_session_display_debug_event_day', intval($_POST['debug_event_day'] ?? 1));

        add_settings_error(
            'dgptm_session_display',
            'settings_saved',
            'Einstellungen gespeichert',
            'success'
        );
    }

    /**
     * API-Verbindung testen
     */
    private function test_api_connection() {
        check_admin_referer('dgptm_session_display_settings');

        $api = new DGPTM_Zoho_Backstage_API();
        $result = $api->test_connection();

        if ($result['success']) {
            add_settings_error(
                'dgptm_session_display',
                'connection_success',
                'Verbindung erfolgreich! ' . count($result['data']['events'] ?? []) . ' Events gefunden.',
                'success'
            );
        } else {
            add_settings_error(
                'dgptm_session_display',
                'connection_error',
                'Verbindungsfehler: ' . $result['message'],
                'error'
            );
        }
    }

    /**
     * Sessions manuell aktualisieren
     */
    private function manual_refresh_sessions() {
        check_admin_referer('dgptm_session_display_settings');

        $session_manager = new DGPTM_Session_Manager();
        $result = $session_manager->fetch_and_cache_sessions();

        if ($result) {
            $last_update = get_option('dgptm_sessions_last_update');
            add_settings_error(
                'dgptm_session_display',
                'refresh_success',
                'Sessions erfolgreich aktualisiert! Letztes Update: ' . $last_update,
                'success'
            );
        } else {
            add_settings_error(
                'dgptm_session_display',
                'refresh_error',
                'Fehler beim Aktualisieren der Sessions',
                'error'
            );
        }
    }

    /**
     * Einstellungsformular anzeigen
     */
    private function display_settings_form() {
        $client_id = get_option('dgptm_session_display_client_id', '');
        $client_secret = get_option('dgptm_session_display_client_secret', '');
        $portal_id = get_option('dgptm_session_display_portal_id', '20086233464');
        $event_id = get_option('dgptm_session_display_event_id', '');
        $event_date = get_option('dgptm_session_display_event_date', '');
        $event_duration = get_option('dgptm_session_display_event_duration', 1);
        $refresh_interval = get_option('dgptm_session_display_refresh_interval', 60000) / 1000;
        $auto_update_interval = get_option('dgptm_session_display_auto_update_interval', 300);
        $auto_refresh = get_option('dgptm_session_display_auto_refresh', true);
        $show_sponsors = get_option('dgptm_session_display_show_sponsors', true);
        $sponsor_interval = get_option('dgptm_session_display_sponsor_interval', 10000) / 1000;
        $template_color = get_option('dgptm_session_display_template_color', '#2563eb');
        $template_logo = get_option('dgptm_session_display_template_logo', '');
        $last_update = get_option('dgptm_sessions_last_update', 'Noch nie');
        $has_token = get_option('dgptm_session_display_refresh_token') ? true : false;
        $next_cron = wp_next_scheduled('dgptm_session_display_update');
        $next_cron_time = $next_cron ? date('Y-m-d H:i:s', $next_cron) : 'Nicht geplant';

        // OAuth-Nachricht aus Transient anzeigen
        $oauth_message = get_transient('dgptm_session_display_oauth_message');
        if ($oauth_message) {
            delete_transient('dgptm_session_display_oauth_message');
            add_settings_error(
                'dgptm_session_display',
                'oauth_message',
                $oauth_message['message'],
                $oauth_message['type']
            );
        }

        ?>
        <div class="wrap">
            <h1>Session Display - Einstellungen</h1>

            <?php settings_errors('dgptm_session_display'); ?>

            <form method="post" action="">
                <?php wp_nonce_field('dgptm_session_display_settings'); ?>

                <h2>API-Konfiguration</h2>

                <div class="notice notice-info" style="padding: 10px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">📋 Zoho OAuth Konfiguration</h3>
                    <p><strong>Tragen Sie diese Redirect URI bei Zoho ein (accounts.zoho.eu):</strong></p>
                    <p style="background: #f0f0f0; padding: 10px; font-family: monospace; word-break: break-all;">
                        <?php echo esc_html(admin_url('admin.php?page=dgptm-session-display&action=oauth_callback')); ?>
                    </p>
                    <p>
                        <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js(admin_url('admin.php?page=dgptm-session-display&action=oauth_callback')); ?>'); alert('Redirect URI in Zwischenablage kopiert!');">
                            📋 URI kopieren
                        </button>
                    </p>
                    <p style="color: #d63638;"><strong>⚠️ Wichtig:</strong> Scopes müssen exakt so geschrieben werden (kleingeschrieben)!</p>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label>OAuth-Scopes</label>
                        </th>
                        <td>
                            <?php
                            $selected_scopes = get_option('dgptm_session_display_oauth_scopes', ['zohobackstage.agenda.READ']);

                            $available_scopes = [
                                'Portal' => [
                                    'zohobackstage.portal.READ' => 'Portal lesen',
                                ],
                                'Events' => [
                                    'zohobackstage.event.READ' => 'Events lesen',
                                    'zohobackstage.event.CREATE' => 'Events erstellen',
                                    'zohobackstage.event.UPDATE' => 'Events aktualisieren',
                                    'zohobackstage.event.DELETE' => 'Events löschen',
                                ],
                                'Agenda (Sessions)' => [
                                    'zohobackstage.agenda.READ' => 'Sessions lesen',
                                    'zohobackstage.agenda.CREATE' => 'Sessions erstellen',
                                    'zohobackstage.agenda.UPDATE' => 'Sessions aktualisieren',
                                    'zohobackstage.agenda.DELETE' => 'Sessions löschen',
                                ],
                                'Speaker' => [
                                    'zohobackstage.speaker.READ' => 'Speaker lesen',
                                    'zohobackstage.speaker.CREATE' => 'Speaker erstellen',
                                    'zohobackstage.speaker.UPDATE' => 'Speaker aktualisieren',
                                    'zohobackstage.speaker.DELETE' => 'Speaker löschen',
                                ],
                                'Sponsoren' => [
                                    'zohobackstage.sponsor.READ' => 'Sponsoren lesen',
                                    'zohobackstage.sponsor.CREATE' => 'Sponsoren erstellen',
                                    'zohobackstage.sponsor.UPDATE' => 'Sponsoren aktualisieren',
                                    'zohobackstage.sponsor.DELETE' => 'Sponsoren löschen',
                                ],
                                'Tickets' => [
                                    'zohobackstage.eventticket.READ' => 'Tickets lesen',
                                    'zohobackstage.eventticket.CREATE' => 'Tickets erstellen',
                                    'zohobackstage.eventticket.UPDATE' => 'Tickets aktualisieren',
                                    'zohobackstage.eventticket.DELETE' => 'Tickets löschen',
                                ],
                                'Bestellungen' => [
                                    'zohobackstage.order.READ' => 'Bestellungen lesen',
                                    'zohobackstage.order.CREATE' => 'Bestellungen erstellen',
                                    'zohobackstage.order.UPDATE' => 'Bestellungen aktualisieren',
                                    'zohobackstage.order.DELETE' => 'Bestellungen löschen',
                                ],
                                'Teilnehmer' => [
                                    'zohobackstage.attendee.READ' => 'Teilnehmer lesen',
                                    'zohobackstage.attendee.UPDATE' => 'Teilnehmer aktualisieren',
                                    'zohobackstage.attendee.DELETE' => 'Teilnehmer löschen',
                                ],
                                'Webhooks' => [
                                    'zohobackstage.webhook.READ' => 'Webhooks lesen',
                                    'zohobackstage.webhook.CREATE' => 'Webhooks erstellen',
                                    'zohobackstage.webhook.UPDATE' => 'Webhooks aktualisieren',
                                    'zohobackstage.webhook.DELETE' => 'Webhooks löschen',
                                ],
                            ];
                            ?>
                            <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
                                <?php foreach ($available_scopes as $category => $scopes): ?>
                                    <h4 style="margin-top: 10px; margin-bottom: 5px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">
                                        <?php echo esc_html($category); ?>
                                    </h4>
                                    <?php foreach ($scopes as $scope => $label): ?>
                                        <label style="display: block; margin: 5px 0;">
                                            <input type="checkbox"
                                                   name="oauth_scopes[]"
                                                   value="<?php echo esc_attr($scope); ?>"
                                                   <?php checked(in_array($scope, $selected_scopes)); ?>>
                                            <code style="font-size: 11px;"><?php echo esc_html($scope); ?></code>
                                            <span style="color: #666;">- <?php echo esc_html($label); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                            <p class="description">
                                <strong>Standard für Session-Anzeige:</strong> <code>zohobackstage.agenda.READ</code><br>
                                Wählen Sie nur die Berechtigungen aus, die Sie wirklich benötigen.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client ID</th>
                        <td>
                            <input type="text" name="client_id" value="<?php echo esc_attr($client_id); ?>" class="regular-text" />
                            <p class="description">Zoho OAuth Client ID</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client Secret</th>
                        <td>
                            <input type="password" name="client_secret" value="<?php echo esc_attr($client_secret); ?>" class="regular-text" />
                            <p class="description">Zoho OAuth Client Secret</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Portal ID</th>
                        <td>
                            <input type="text" name="portal_id" value="<?php echo esc_attr($portal_id); ?>" class="regular-text" />
                            <p class="description">Zoho Backstage Portal ID (Standard: 20086233464)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Event ID</th>
                        <td>
                            <input type="text" name="event_id" value="<?php echo esc_attr($event_id); ?>" class="regular-text" />
                            <p class="description">ID des aktuellen Events</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">OAuth-Status</th>
                        <td>
                            <?php if ($has_token): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                                <strong>Verbunden</strong>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: orange;"></span>
                                <strong>Nicht verbunden</strong>
                            <?php endif; ?>
                            <br><br>
                            <?php if ($client_id && $client_secret): ?>
                                <a href="<?php echo esc_url(DGPTM_Zoho_Backstage_API::get_authorization_url()); ?>" class="button button-secondary">
                                    Mit Zoho Backstage verbinden
                                </a>
                            <?php else: ?>
                                <p class="description" style="color: red;">Bitte zuerst Client ID und Secret eingeben</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2>Event-Einstellungen</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Veranstaltungsdatum</th>
                        <td>
                            <input type="date" name="event_date" value="<?php echo esc_attr($event_date); ?>" />
                            <p class="description">Erster Tag der Veranstaltung</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Dauer (Tage)</th>
                        <td>
                            <input type="number" name="event_duration" value="<?php echo esc_attr($event_duration); ?>" min="1" max="7" />
                            <p class="description">Anzahl der Veranstaltungstage</p>
                        </td>
                    </tr>
                </table>

                <h2>Display-Einstellungen</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Frontend-Aktualisierung</th>
                        <td>
                            <input type="number" name="refresh_interval" value="<?php echo esc_attr($refresh_interval); ?>" min="10" max="300" />
                            Sekunden
                            <p class="description">Wie oft sollen die Frontend-Displays aktualisiert werden? (Standard: 60 Sekunden)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Backend API-Abruf</th>
                        <td>
                            <input type="number" name="auto_update_interval" value="<?php echo esc_attr($auto_update_interval); ?>" min="60" max="3600" />
                            Sekunden
                            <p class="description">
                                Wie oft sollen Sessions automatisch von Zoho Backstage abgerufen werden? (Standard: 300 Sekunden = 5 Minuten)<br>
                                <strong>Nächster automatischer Abruf:</strong> <?php echo esc_html($next_cron_time); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto-Refresh</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_refresh" value="1" <?php checked($auto_refresh); ?> />
                                Automatische Frontend-Aktualisierung aktivieren
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sponsoren anzeigen</th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_sponsors" value="1" <?php checked($show_sponsors); ?> />
                                Sponsoren-Logos in Pausen einblenden
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sponsoren-Rotationsintervall</th>
                        <td>
                            <input type="number" name="sponsor_interval" value="<?php echo esc_attr($sponsor_interval); ?>" min="5" max="60" />
                            Sekunden
                            <p class="description">Wie oft sollen Sponsoren-Logos wechseln? (Standard: 10 Sekunden)</p>
                        </td>
                    </tr>
                </table>

                <h2>Template-Anpassung</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Primärfarbe</th>
                        <td>
                            <input type="color" name="template_color" value="<?php echo esc_attr($template_color); ?>" />
                            <input type="text" value="<?php echo esc_attr($template_color); ?>" readonly style="width: 100px; margin-left: 10px;" />
                            <p class="description">Hauptfarbe für Akzente, Fortschrittsbalken und Hervorhebungen</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Event-Logo URL</th>
                        <td>
                            <input type="url" name="template_logo" value="<?php echo esc_attr($template_logo); ?>" class="large-text" placeholder="https://..." />
                            <p class="description">Logo für Header und Footer der Displays (optional)</p>
                            <?php if ($template_logo): ?>
                                <br><br>
                                <strong>Vorschau:</strong><br>
                                <img src="<?php echo esc_url($template_logo); ?>" alt="Logo Vorschau" style="max-width: 200px; max-height: 100px; margin-top: 10px;" />
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2>Session-Verwaltung</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Letztes Update</th>
                        <td>
                            <strong><?php echo esc_html($last_update); ?></strong>
                            <br><br>
                            <button type="submit" name="dgptm_session_display_refresh_sessions" class="button button-secondary">
                                <span class="dashicons dashicons-update"></span> Sessions jetzt aktualisieren
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Verbindung testen</th>
                        <td>
                            <button type="submit" name="dgptm_session_display_test_connection" class="button button-secondary">
                                <span class="dashicons dashicons-admin-plugins"></span> API-Verbindung testen
                            </button>
                        </td>
                    </tr>
                </table>

                <h2>Display-Einstellungen</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Vollbild-Modus</th>
                        <td>
                            <?php $fullscreen_auto = get_option('dgptm_session_display_fullscreen_auto', true); ?>
                            <label>
                                <input type="checkbox" name="fullscreen_auto" value="1" <?php checked($fullscreen_auto); ?> />
                                Displays automatisch im Vollbild-Modus starten
                            </label>
                            <p class="description">Empfohlen für Kiosk-Displays. Benutzer kann mit F11 Vollbild beenden.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Hintergrundbilder</th>
                        <td>
                            <?php $bg_gallery_interval = get_option('dgptm_session_display_bg_gallery_interval', 30000) / 1000; ?>
                            <label>
                                Rotations-Intervall (Sekunden):
                                <input type="number" name="bg_gallery_interval" value="<?php echo esc_attr($bg_gallery_interval); ?>" min="5" max="300" step="5" style="width: 80px;" />
                            </label>
                            <p class="description">Intervall für Hintergrundbilder-Galerie (wenn per Shortcode aktiviert)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Session-Rotation</th>
                        <td>
                            <?php $session_rotation_interval = get_option('dgptm_session_display_session_rotation_interval', 15000) / 1000; ?>
                            <label>
                                Rotations-Intervall (Sekunden):
                                <input type="number" name="session_rotation_interval" value="<?php echo esc_attr($session_rotation_interval); ?>" min="5" max="120" step="5" style="width: 80px;" />
                            </label>
                            <p class="description">Intervall für Rotation von parallelen Sessions (z.B. mehrere Vorträge gleichzeitig im Raum)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Venues ohne Raum</th>
                        <td>
                            <?php $hide_no_room = get_option('dgptm_session_display_hide_no_room', true); ?>
                            <label>
                                <input type="checkbox" name="hide_no_room" value="1" <?php checked($hide_no_room); ?> />
                                Venues ohne zugeordneten Raum ausblenden
                            </label>
                            <p class="description">Sessions ohne Raum-Zuordnung werden nicht angezeigt</p>
                        </td>
                    </tr>
                </table>

                <h2>Debug-Einstellungen</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Debug-Zeitsteuerung aktivieren</th>
                        <td>
                            <?php $debug_enabled = get_option('dgptm_session_display_debug_enabled', false); ?>
                            <label>
                                <input type="checkbox" name="debug_enabled" value="1" <?php checked($debug_enabled); ?> id="debug-enabled-toggle" />
                                Debug-Zeitsteuerung einschalten
                            </label>
                            <p class="description" style="color: #d63638;"><strong>⚠️ Nur für Tests!</strong> Setzt alle Displays auf eine fixe Zeit und/oder ein fixes Datum.</p>
                        </td>
                    </tr>
                    <tr id="debug-date-row" style="<?php echo $debug_enabled ? '' : 'display:none;'; ?>">
                        <th scope="row">Debug-Datum</th>
                        <td>
                            <?php
                            $debug_date_mode = get_option('dgptm_session_display_debug_date_mode', 'off');
                            $debug_date_custom = get_option('dgptm_session_display_debug_date_custom', date('Y-m-d'));
                            $debug_event_day = get_option('dgptm_session_display_debug_event_day', 1);
                            ?>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="radio" name="debug_date_mode" value="off" <?php checked($debug_date_mode, 'off'); ?> />
                                Kein Debug-Datum (heutiges Datum verwenden)
                            </label>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="radio" name="debug_date_mode" value="event_day" <?php checked($debug_date_mode, 'event_day'); ?> />
                                Veranstaltungstag simulieren:
                                <select name="debug_event_day" style="margin-left: 10px;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php selected($debug_event_day, $i); ?>>
                                            Tag <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="radio" name="debug_date_mode" value="custom" <?php checked($debug_date_mode, 'custom'); ?> />
                                Benutzerdefiniertes Datum:
                                <input type="date" name="debug_date_custom" value="<?php echo esc_attr($debug_date_custom); ?>" style="margin-left: 10px;" />
                            </label>
                            <p class="description">
                                <strong>Veranstaltungstag:</strong> Berechnet das Datum basierend auf dem Event-Datum + gewähltem Tag<br>
                                <strong>Benutzerdefiniert:</strong> Festes Datum für alle Displays
                            </p>
                        </td>
                    </tr>
                    <tr id="debug-time-row" style="<?php echo $debug_enabled ? '' : 'display:none;'; ?>">
                        <th scope="row">Debug-Zeit</th>
                        <td>
                            <?php $debug_time = get_option('dgptm_session_display_debug_time', '09:00'); ?>
                            <input type="time" name="debug_time" value="<?php echo esc_attr($debug_time); ?>" />
                            <p class="description">Alle Displays werden so tun, als wäre es diese Uhrzeit (nützlich zum Testen verschiedener Sessions)</p>
                        </td>
                    </tr>
                </table>

                <script>
                document.getElementById('debug-enabled-toggle').addEventListener('change', function() {
                    var isEnabled = this.checked;
                    document.getElementById('debug-time-row').style.display = isEnabled ? '' : 'none';
                    document.getElementById('debug-date-row').style.display = isEnabled ? '' : 'none';
                });
                </script>

                <p class="submit">
                    <button type="submit" name="dgptm_session_display_save_settings" class="button button-primary">
                        Einstellungen speichern
                    </button>
                </p>
            </form>

            <hr>

            <h2>Kurzanleitung</h2>
            <ol>
                <li>Erstellen Sie eine OAuth-App in Zoho (accounts.zoho.eu)</li>
                <li>Geben Sie Client ID und Secret ein</li>
                <li>Klicken Sie auf "Mit Zoho Backstage verbinden"</li>
                <li>Autorisieren Sie die Anwendung</li>
                <li>Geben Sie die Event-ID ein</li>
                <li>Testen Sie die Verbindung</li>
                <li>Aktualisieren Sie die Sessions</li>
            </ol>

            <h3>Shortcode-Verwendung</h3>
            <code>[session_display room="Raum 1" type="current" show_sponsors="true"]</code>
            <br><br>
            <code>[session_overview floor="EG" layout="grid"]</code>
        </div>
        <?php
    }

    /**
     * Raumzuordnung-Seite rendern
     */
    public function render_rooms_page() {
        // Automatische Zuordnung
        if (isset($_POST['dgptm_auto_map_rooms'])) {
            $this->auto_map_rooms();
        }

        // Manuelle Zuordnung speichern
        if (isset($_POST['dgptm_save_room_mapping'])) {
            $this->save_room_mapping();
        }

        // Etagen-Zuordnung speichern
        if (isset($_POST['dgptm_save_floor_mapping'])) {
            $this->save_floor_mapping();
        }

        $this->display_rooms_form();
    }

    /**
     * Automatische Raumzuordnung
     */
    private function auto_map_rooms() {
        check_admin_referer('dgptm_room_mapping');

        $api = new DGPTM_Zoho_Backstage_API();
        $event_id = get_option('dgptm_session_display_event_id');

        if (!$event_id) {
            add_settings_error('dgptm_room_mapping', 'no_event', 'Keine Event-ID konfiguriert', 'error');
            return;
        }

        $tracks_response = $api->get_tracks($event_id);

        if (is_wp_error($tracks_response)) {
            add_settings_error('dgptm_room_mapping', 'api_error', 'Fehler beim Abrufen der Tracks', 'error');
            return;
        }

        $tracks = $tracks_response['tracks'] ?? [];
        $mapped = DGPTM_Room_Mapper::auto_map_from_track_names($tracks);

        add_settings_error(
            'dgptm_room_mapping',
            'auto_map_success',
            count($mapped) . ' Räume automatisch zugeordnet',
            'success'
        );
    }

    /**
     * Raumzuordnung speichern
     */
    private function save_room_mapping() {
        check_admin_referer('dgptm_room_mapping');

        if (isset($_POST['room_mapping']) && is_array($_POST['room_mapping'])) {
            $mapping = [];
            foreach ($_POST['room_mapping'] as $track_id => $room_name) {
                $mapping[sanitize_text_field($track_id)] = sanitize_text_field($room_name);
            }
            update_option('dgptm_session_display_room_mapping', $mapping);

            add_settings_error('dgptm_room_mapping', 'mapping_saved', 'Raumzuordnung gespeichert', 'success');
        }
    }

    /**
     * Etagen-Zuordnung speichern
     */
    private function save_floor_mapping() {
        check_admin_referer('dgptm_floor_mapping');

        if (isset($_POST['floor_mapping']) && is_array($_POST['floor_mapping'])) {
            $floors = [];
            foreach ($_POST['floor_mapping'] as $floor => $rooms_string) {
                $room_list = array_map('trim', explode(',', $rooms_string));
                $floors[sanitize_text_field($floor)] = array_filter($room_list);
            }
            update_option('dgptm_session_display_floors', $floors);

            add_settings_error('dgptm_floor_mapping', 'floor_saved', 'Etagen-Zuordnung gespeichert', 'success');
        }
    }

    /**
     * Raumzuordnungsformular anzeigen
     */
    private function display_rooms_form() {
        $mapping = DGPTM_Room_Mapper::get_all_mappings();
        $floors = DGPTM_Room_Mapper::get_all_floors();

        ?>
        <div class="wrap">
            <h1>Raumzuordnung</h1>

            <?php settings_errors('dgptm_room_mapping'); ?>
            <?php settings_errors('dgptm_floor_mapping'); ?>

            <form method="post">
                <?php wp_nonce_field('dgptm_room_mapping'); ?>

                <h2>Track → Raum Zuordnung</h2>
                <p>Ordnen Sie Zoho Backstage Tracks zu physischen Räumen zu.</p>

                <button type="submit" name="dgptm_auto_map_rooms" class="button button-secondary">
                    <span class="dashicons dashicons-admin-site-alt3"></span> Automatisch zuordnen
                </button>

                <br><br>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Track ID</th>
                            <th>Raumname</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mapping)): ?>
                            <tr>
                                <td colspan="2">Keine Zuordnungen vorhanden. Klicken Sie auf "Automatisch zuordnen".</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mapping as $track_id => $room_name): ?>
                                <tr>
                                    <td><?php echo esc_html($track_id); ?></td>
                                    <td>
                                        <input type="text" name="room_mapping[<?php echo esc_attr($track_id); ?>]" value="<?php echo esc_attr($room_name); ?>" class="regular-text" />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!empty($mapping)): ?>
                    <p class="submit">
                        <button type="submit" name="dgptm_save_room_mapping" class="button button-primary">
                            Zuordnung speichern
                        </button>
                    </p>
                <?php endif; ?>
            </form>

            <hr>

            <form method="post">
                <?php wp_nonce_field('dgptm_floor_mapping'); ?>

                <h2>Etagen-Gruppierung</h2>
                <p>Gruppieren Sie Räume nach Etagen/Bereichen für Übersichtsanzeigen.</p>

                <table class="form-table">
                    <?php
                    $default_floors = ['EG' => '', 'OG1' => '', 'OG2' => ''];
                    $all_floors = array_merge($default_floors, $floors);
                    ?>
                    <?php foreach ($all_floors as $floor => $rooms): ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($floor); ?></th>
                            <td>
                                <input type="text" name="floor_mapping[<?php echo esc_attr($floor); ?>]" value="<?php echo esc_attr(is_array($rooms) ? implode(', ', $rooms) : $rooms); ?>" class="large-text" placeholder="Raum 1, Raum 2, Raum 3" />
                                <p class="description">Räume kommagetrennt eingeben</p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <p class="submit">
                    <button type="submit" name="dgptm_save_floor_mapping" class="button button-primary">
                        Etagen speichern
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Sponsoren-Seite rendern
     */
    public function render_sponsors_page() {
        // Sponsor hinzufügen/speichern
        if (isset($_POST['dgptm_save_sponsors'])) {
            $this->save_sponsors();
        }

        $this->display_sponsors_form();
    }

    /**
     * Sponsoren speichern
     */
    private function save_sponsors() {
        check_admin_referer('dgptm_sponsors');

        $sponsors = [];

        if (isset($_POST['sponsor_name']) && is_array($_POST['sponsor_name'])) {
            for ($i = 0; $i < count($_POST['sponsor_name']); $i++) {
                if (!empty($_POST['sponsor_name'][$i])) {
                    $sponsors[] = [
                        'name' => sanitize_text_field($_POST['sponsor_name'][$i]),
                        'logo' => esc_url_raw($_POST['sponsor_logo'][$i]),
                        'level' => sanitize_text_field($_POST['sponsor_level'][$i] ?? 'default')
                    ];
                }
            }
        }

        update_option('dgptm_session_display_sponsors', $sponsors);

        add_settings_error('dgptm_sponsors', 'sponsors_saved', 'Sponsoren gespeichert', 'success');
    }

    /**
     * Sponsoren-Formular anzeigen
     */
    private function display_sponsors_form() {
        $sponsors = get_option('dgptm_session_display_sponsors', []);

        ?>
        <div class="wrap">
            <h1>Sponsoren-Verwaltung</h1>

            <?php settings_errors('dgptm_sponsors'); ?>

            <form method="post">
                <?php wp_nonce_field('dgptm_sponsors'); ?>

                <h2>Sponsoren</h2>
                <p>Sponsoren-Logos werden in Pausen auf den Displays rotierend angezeigt.</p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Name</th>
                            <th style="width: 50%;">Logo-URL</th>
                            <th style="width: 20%;">Level</th>
                        </tr>
                    </thead>
                    <tbody id="sponsors-table">
                        <?php if (empty($sponsors)): ?>
                            <tr>
                                <td><input type="text" name="sponsor_name[]" class="regular-text" placeholder="Sponsor-Name" /></td>
                                <td><input type="url" name="sponsor_logo[]" class="large-text" placeholder="https://..." /></td>
                                <td>
                                    <select name="sponsor_level[]">
                                        <option value="platinum">Platinum</option>
                                        <option value="gold">Gold</option>
                                        <option value="silver">Silver</option>
                                        <option value="bronze">Bronze</option>
                                    </select>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sponsors as $sponsor): ?>
                                <tr>
                                    <td><input type="text" name="sponsor_name[]" value="<?php echo esc_attr($sponsor['name']); ?>" class="regular-text" /></td>
                                    <td><input type="url" name="sponsor_logo[]" value="<?php echo esc_attr($sponsor['logo']); ?>" class="large-text" /></td>
                                    <td>
                                        <select name="sponsor_level[]">
                                            <option value="platinum" <?php selected($sponsor['level'], 'platinum'); ?>>Platinum</option>
                                            <option value="gold" <?php selected($sponsor['level'], 'gold'); ?>>Gold</option>
                                            <option value="silver" <?php selected($sponsor['level'], 'silver'); ?>>Silver</option>
                                            <option value="bronze" <?php selected($sponsor['level'], 'bronze'); ?>>Bronze</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <br>
                <button type="button" id="add-sponsor" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span> Sponsor hinzufügen
                </button>

                <p class="submit">
                    <button type="submit" name="dgptm_save_sponsors" class="button button-primary">
                        Sponsoren speichern
                    </button>
                </p>
            </form>

            <script>
            jQuery(document).ready(function($) {
                $('#add-sponsor').on('click', function() {
                    var row = '<tr>' +
                        '<td><input type="text" name="sponsor_name[]" class="regular-text" placeholder="Sponsor-Name" /></td>' +
                        '<td><input type="url" name="sponsor_logo[]" class="large-text" placeholder="https://..." /></td>' +
                        '<td><select name="sponsor_level[]">' +
                        '<option value="platinum">Platinum</option>' +
                        '<option value="gold">Gold</option>' +
                        '<option value="silver">Silver</option>' +
                        '<option value="bronze">Bronze</option>' +
                        '</select></td>' +
                        '</tr>';
                    $('#sponsors-table').append(row);
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Venue-Zuordnung rendern
     */
    public function render_venues_page() {
        // API und Manager laden
        if (!class_exists('DGPTM_Session_Manager')) {
            require_once DGPTM_SESSION_DISPLAY_PATH . 'includes/class-session-manager.php';
        }

        $manager = new DGPTM_Session_Manager();

        // Einstellungen speichern
        if (isset($_POST['dgptm_save_venue_mapping'])) {
            check_admin_referer('dgptm_venue_mapping');

            $venue_mapping = [];
            if (isset($_POST['venue_id']) && isset($_POST['venue_name'])) {
                foreach ($_POST['venue_id'] as $index => $venue_id) {
                    $venue_name = sanitize_text_field($_POST['venue_name'][$index]);
                    if (!empty($venue_id) && !empty($venue_name)) {
                        $venue_mapping[$venue_id] = $venue_name;
                    }
                }
            }

            update_option('dgptm_session_display_venue_mapping', $venue_mapping);
            echo '<div class="notice notice-success"><p>Venue-Zuordnung gespeichert!</p></div>';
        }

        // Alle Sessions abrufen
        $all_sessions = $manager->get_cached_sessions();

        // Alle verwendeten Venue-IDs extrahieren
        $venue_ids = [];
        foreach ($all_sessions as $session) {
            if (!empty($session['venue'])) {
                $venue_id = $session['venue'];
                if (!isset($venue_ids[$venue_id])) {
                    $venue_ids[$venue_id] = [
                        'id' => $venue_id,
                        'count' => 0,
                        'sessions' => []
                    ];
                }
                $venue_ids[$venue_id]['count']++;
                $venue_ids[$venue_id]['sessions'][] = $session['title'];
            }
        }

        // Gespeicherte Zuordnung abrufen
        $venue_mapping = get_option('dgptm_session_display_venue_mapping', []);

        ?>
        <div class="wrap">
            <h1>Venue-Zuordnung</h1>
            <p class="description">
                Die Zoho Backstage API liefert Venue-IDs, aber keine Namen. Hier können Sie den IDs
                aussagekräftige Raumnamen zuordnen.
            </p>

            <div style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <strong>ℹ️ Hinweis:</strong> Die Venue-IDs werden automatisch aus den heruntergeladenen Sessions extrahiert.
                Wenn keine Venues angezeigt werden, bitte zuerst Sessions über "Sessions Übersicht" aktualisieren.
            </div>

            <h2>Gefundene Venues</h2>
            <div style="margin-bottom: 20px;">
                <strong>Gesamt:</strong> <?php echo count($venue_ids); ?> verschiedene Venue(s)
            </div>

            <?php if (empty($venue_ids)): ?>
                <div class="notice notice-warning">
                    <p>Keine Venues gefunden. Bitte laden Sie zuerst Sessions über die "Sessions Übersicht" Seite.</p>
                </div>
            <?php else: ?>
                <form method="post">
                    <?php wp_nonce_field('dgptm_venue_mapping'); ?>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Venue-ID</th>
                                <th style="width: 40%;">Raumname (zugeordnet)</th>
                                <th style="width: 10%;">Anzahl Sessions</th>
                                <th style="width: 20%;">Beispiel-Sessions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($venue_ids as $venue_id => $data): ?>
                                <tr>
                                    <td>
                                        <code><?php echo esc_html($venue_id); ?></code>
                                        <input type="hidden" name="venue_id[]" value="<?php echo esc_attr($venue_id); ?>" />
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="venue_name[]"
                                               value="<?php echo esc_attr($venue_mapping[$venue_id] ?? ''); ?>"
                                               class="regular-text"
                                               placeholder="z.B. Hörsaal A, Raum 101, etc." />
                                    </td>
                                    <td>
                                        <strong><?php echo $data['count']; ?></strong> Session(s)
                                    </td>
                                    <td>
                                        <details style="cursor: pointer;">
                                            <summary style="color: #2271b1;">Anzeigen (<?php echo min(3, count($data['sessions'])); ?>)</summary>
                                            <ul style="margin: 5px 0; padding-left: 20px; font-size: 12px;">
                                                <?php
                                                $sample_sessions = array_slice($data['sessions'], 0, 3);
                                                foreach ($sample_sessions as $session_title):
                                                ?>
                                                    <li><?php echo esc_html(mb_substr($session_title, 0, 60)) . (mb_strlen($session_title) > 60 ? '...' : ''); ?></li>
                                                <?php endforeach; ?>
                                                <?php if (count($data['sessions']) > 3): ?>
                                                    <li><em>... und <?php echo count($data['sessions']) - 3; ?> weitere</em></li>
                                                <?php endif; ?>
                                            </ul>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" name="dgptm_save_venue_mapping" class="button button-primary">
                            Venue-Zuordnung speichern
                        </button>
                    </p>
                </form>

                <h2 style="margin-top: 40px;">Zuordnungs-Vorschau</h2>
                <p class="description">So werden die Räume in der Anzeige erscheinen:</p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Venue-ID</th>
                            <th>Angezeigter Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($venue_ids as $venue_id => $data): ?>
                            <tr>
                                <td><code><?php echo esc_html($venue_id); ?></code></td>
                                <td>
                                    <?php if (!empty($venue_mapping[$venue_id])): ?>
                                        <strong><?php echo esc_html($venue_mapping[$venue_id]); ?></strong>
                                    <?php else: ?>
                                        <em style="color: #999;"><?php echo esc_html($venue_id); ?> (ID wird angezeigt)</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($venue_mapping[$venue_id])): ?>
                                        <span style="color: green;">✓ Zugeordnet</span>
                                    <?php else: ?>
                                        <span style="color: orange;">⚠ Nicht zugeordnet</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Sessions Übersicht rendern
     */
    public function render_sessions_overview_page() {
        // API und Manager laden
        if (!class_exists('DGPTM_Zoho_Backstage_API')) {
            require_once DGPTM_SESSION_DISPLAY_PATH . 'includes/class-zoho-backstage-api.php';
        }
        if (!class_exists('DGPTM_Session_Manager')) {
            require_once DGPTM_SESSION_DISPLAY_PATH . 'includes/class-session-manager.php';
        }

        $api = new DGPTM_Zoho_Backstage_API();
        $manager = new DGPTM_Session_Manager();

        // Manuelle Aktualisierung?
        if (isset($_POST['refresh_sessions'])) {
            check_admin_referer('dgptm_refresh_sessions');
            $manager->fetch_and_cache_sessions();
            echo '<div class="notice notice-success"><p>Sessions wurden aktualisiert!</p></div>';
        }

        // Sessions abrufen
        $all_sessions = $manager->get_cached_sessions();

        // Tracks abrufen
        $tracks = get_transient('dgptm_tracks_cache');
        if (false === $tracks) {
            $tracks_response = $api->get_tracks();
            if (!is_wp_error($tracks_response) && isset($tracks_response['tracks'])) {
                $tracks = $tracks_response['tracks'];
                set_transient('dgptm_tracks_cache', $tracks, 3600);
            } else {
                $tracks = [];
            }
        }

        // Track "Sessions" finden (kann auch "Sessions - Stream" heißen)
        $sessions_track = null;
        foreach ($tracks as $track) {
            if (stripos($track['name'], 'Sessions') !== false) {
                $sessions_track = $track;
                break;
            }
        }

        // Sessions nach Track "Sessions" filtern und gruppieren
        $grouped_sessions = [];

        foreach ($all_sessions as $session) {
            // Nur Sessions aus dem Track "Sessions"
            if ($sessions_track && $session['track_id'] === $sessions_track['track_id']) {
                // Session ist die Überschrift/Gruppe
                $session_title = $session['title'];

                if (!isset($grouped_sessions[$session_title])) {
                    $grouped_sessions[$session_title] = [
                        'session' => $session,
                        'vortraege' => []
                    ];
                }
            } else {
                // Alle anderen sind Vorträge - versuche sie einer Session zuzuordnen
                // Zunächst sammeln wir sie separat
                $grouped_sessions['_einzelvortraege'][] = $session;
            }
        }

        ?>
        <div class="wrap">
            <h1>Sessions Übersicht</h1>
            <p class="description">
                Hierarchie: <strong>Track "Sessions"</strong> → <strong>Session (Überschrift)</strong> → <strong>Vorträge</strong>
            </p>

            <form method="post" style="margin: 20px 0;">
                <?php wp_nonce_field('dgptm_refresh_sessions'); ?>
                <button type="submit" name="refresh_sessions" class="button button-primary">
                    <span class="dashicons dashicons-update"></span> Sessions aktualisieren
                </button>
            </form>

            <div style="margin-top: 20px;">
                <h2>Statistiken</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                    <div class="card" style="padding: 15px;">
                        <h3 style="margin-top: 0;">Gesamt Sessions</h3>
                        <p style="font-size: 32px; font-weight: bold; margin: 10px 0;">
                            <?php echo count($all_sessions); ?>
                        </p>
                    </div>
                    <div class="card" style="padding: 15px;">
                        <h3 style="margin-top: 0;">Track "Sessions"</h3>
                        <p style="font-size: 32px; font-weight: bold; margin: 10px 0;">
                            <?php echo $sessions_track ? count(array_filter($all_sessions, function($s) use ($sessions_track) {
                                return $s['track_id'] === $sessions_track['track_id'];
                            })) : 0; ?>
                        </p>
                    </div>
                    <div class="card" style="padding: 15px;">
                        <h3 style="margin-top: 0;">Alle Tracks</h3>
                        <p style="font-size: 32px; font-weight: bold; margin: 10px 0;">
                            <?php echo count($tracks); ?>
                        </p>
                    </div>
                </div>

                <?php if ($sessions_track): ?>
                    <div class="card" style="padding: 20px; margin-bottom: 20px; background: #f0f9ff;">
                        <h3 style="margin-top: 0;">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            Track: <?php echo esc_html($sessions_track['name']); ?>
                        </h3>
                        <p><strong>Track-ID:</strong> <?php echo esc_html($sessions_track['track_id']); ?></p>
                        <p><strong>Sprache:</strong> <?php echo esc_html($sessions_track['language']); ?></p>
                        <p><strong>Index:</strong> <?php echo esc_html($sessions_track['index']); ?></p>
                    </div>
                <?php endif; ?>

                <h2>Sessions im Track "<?php echo $sessions_track ? esc_html($sessions_track['name']) : 'Sessions'; ?>"</h2>

                <?php if (empty($grouped_sessions)): ?>
                    <div class="notice notice-warning">
                        <p>Keine Sessions gefunden. Bitte aktualisieren Sie die Daten über den Button oben.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_sessions as $session_title => $data): ?>
                        <?php if ($session_title === '_einzelvortraege') continue; // Erstmal überspringen ?>

                        <div class="card" style="margin-bottom: 20px; border-left: 4px solid #2271b1;">
                            <h3 style="margin: 0; padding: 15px; background: #f6f7f7; border-bottom: 1px solid #ddd;">
                                <span class="dashicons dashicons-megaphone"></span>
                                <?php echo esc_html($session_title); ?>
                            </h3>

                            <div style="padding: 15px;">
                                <?php $session = $data['session']; ?>

                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 15px;">
                                    <div>
                                        <strong>Start:</strong>
                                        <?php echo esc_html(date('d.m.Y H:i', strtotime($session['start_time']))); ?>
                                    </div>
                                    <div>
                                        <strong>Ende:</strong>
                                        <?php echo esc_html(date('H:i', strtotime($session['end_time']))); ?>
                                    </div>
                                    <div>
                                        <strong>Dauer:</strong>
                                        <?php echo esc_html($session['duration']); ?> Minuten
                                    </div>
                                    <div>
                                        <strong>Track:</strong>
                                        <?php echo esc_html($session['track_name']); ?>
                                    </div>
                                </div>

                                <?php if (!empty($session['venue_name']) || !empty($session['venue_id'])): ?>
                                    <div style="padding: 10px; background: #e8f4f8; border-radius: 4px; margin-bottom: 10px;">
                                        <strong>📍 Raum/Venue:</strong>
                                        <?php echo esc_html($session['venue_name'] ?? $session['venue_id']); ?>
                                        <?php if (!empty($session['venue_id']) && $session['venue_name'] !== $session['venue_id']): ?>
                                            <small style="color: #666;">(Venue-ID: <?php echo esc_html($session['venue_id']); ?>)</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($session['description'])): ?>
                                    <div style="padding: 10px; background: #f9f9f9; border-radius: 4px; margin-top: 10px;">
                                        <strong>Beschreibung:</strong><br>
                                        <?php echo wp_kses_post($session['description']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($session['speakers'])): ?>
                                    <div style="margin-top: 15px;">
                                        <strong>Speaker:</strong>
                                        <ul style="margin: 5px 0; padding-left: 20px;">
                                            <?php foreach ($session['speakers'] as $speaker): ?>
                                                <li>
                                                    <?php echo esc_html($speaker['name']); ?>
                                                    <?php if (!empty($speaker['company'])): ?>
                                                        (<?php echo esc_html($speaker['company']); ?>)
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <!-- Hier würden die zugeordneten Vorträge kommen -->
                                <?php if (!empty($data['vortraege'])): ?>
                                    <div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #ddd;">
                                        <h4 style="margin-top: 0;">Vorträge in dieser Session:</h4>

                                        <?php foreach ($data['vortraege'] as $vortrag): ?>
                                            <div style="padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
                                                <strong><?php echo esc_html($vortrag['title']); ?></strong><br>
                                                <small>
                                                    <?php echo esc_html(date('H:i', strtotime($vortrag['start_time']))); ?> -
                                                    <?php echo esc_html(date('H:i', strtotime($vortrag['end_time']))); ?>
                                                    (<?php echo esc_html($vortrag['duration']); ?> Min.)
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Alle Tracks anzeigen -->
                <h2 style="margin-top: 40px;">Alle verfügbaren Tracks</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Index</th>
                            <th>Track-Name</th>
                            <th>Track-ID</th>
                            <th>Sprache</th>
                            <th>Anzahl Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tracks as $track): ?>
                            <tr>
                                <td><?php echo esc_html($track['index']); ?></td>
                                <td>
                                    <strong><?php echo esc_html($track['name']); ?></strong>
                                    <?php if ($sessions_track && $track['track_id'] === $sessions_track['track_id']): ?>
                                        <span class="dashicons dashicons-star-filled" style="color: gold;" title="Haupt-Track für Sessions"></span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html($track['track_id']); ?></code></td>
                                <td><?php echo esc_html($track['language']); ?></td>
                                <td>
                                    <?php
                                    $track_session_count = count(array_filter($all_sessions, function($s) use ($track) {
                                        return $s['track_id'] === $track['track_id'];
                                    }));
                                    echo $track_session_count;
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
