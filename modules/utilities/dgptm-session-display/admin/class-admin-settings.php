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
     * Einstellungen-Seite rendern
     */
    public function render_settings_page() {
        // OAuth-Callback verarbeiten
        if (isset($_GET['action']) && $_GET['action'] === 'oauth_callback' && isset($_GET['code'])) {
            $this->handle_oauth_callback();
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
     * OAuth-Callback verarbeiten
     */
    private function handle_oauth_callback() {
        if (!isset($_GET['code'])) {
            add_settings_error(
                'dgptm_session_display',
                'oauth_error',
                'Keine Autorisierung erhalten',
                'error'
            );
            return;
        }

        $code = sanitize_text_field($_GET['code']);
        $result = DGPTM_Zoho_Backstage_API::exchange_code_for_token($code);

        if ($result['success']) {
            add_settings_error(
                'dgptm_session_display',
                'oauth_success',
                'Erfolgreich mit Zoho Backstage verbunden!',
                'success'
            );
        } else {
            add_settings_error(
                'dgptm_session_display',
                'oauth_error',
                'OAuth-Fehler: ' . $result['message'],
                'error'
            );
        }
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

        ?>
        <div class="wrap">
            <h1>Session Display - Einstellungen</h1>

            <?php settings_errors('dgptm_session_display'); ?>

            <form method="post" action="">
                <?php wp_nonce_field('dgptm_session_display_settings'); ?>

                <h2>API-Konfiguration</h2>
                <table class="form-table">
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
}
