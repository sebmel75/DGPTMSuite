<?php
/**
 * CSV-Import Add-on für Fortbildungen
 * Importiert Teilnehmerlisten und ordnet sie WordPress-Benutzern zu
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Fortbildung_CSV_Import {
    
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
        add_action( 'wp_ajax_fobi_process_csv_import', array( $this, 'process_csv_import' ) );
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=fortbildung',
            'Teilnehmerliste importieren',
            'CSV-Import',
            'manage_options',
            'fobi-csv-import',
            array( $this, 'render_import_page' )
        );
    }
    
    /**
     * Import-Seite rendern
     */
    public function render_import_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }
        
        wp_enqueue_media();
        $nonce = wp_create_nonce( 'fobi_csv_import' );
        ?>
        <div class="wrap">
            <h1>Teilnehmerliste importieren</h1>
            
            <div class="fobi-import-container" style="max-width: 900px;">
                
                <div class="card" style="margin-top: 20px;">
                    <h2>CSV-Datei hochladen</h2>
                    <p class="description">
                        Laden Sie eine CSV-Datei mit folgenden Spalten hoch:<br>
                        <strong>Format 1 (empfohlen):</strong> <code>FIRST_NAME</code>, <code>LAST_NAME</code>, <code>EMAIL</code>, <code>SESSION_TITLE</code><br>
                        <strong>Format 2:</strong> <code>NAME</code>, <code>EMAIL</code>, <code>SESSION_TITLE</code> (NAME wird automatisch in Vor- und Nachname aufgeteilt)
                    </p>
                    
                    <form id="fobi-csv-import-form" enctype="multipart/form-data">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="csv_files">CSV-Dateien *</label>
                                </th>
                                <td>
                                    <input type="file" 
                                           id="csv_files" 
                                           name="csv_files[]" 
                                           accept=".csv,.txt" 
                                           multiple
                                           required>
                                    <p class="description">
                                        Wählen Sie eine oder mehrere CSV-Dateien aus (Strg/Cmd gedrückt halten für Mehrfachauswahl)
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="is_speaker_mode">Datei-Typ</label>
                                </th>
                                <td>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" 
                                               id="is_speaker_mode" 
                                               name="is_speaker_mode" 
                                               value="1"
                                               class="mode-checkbox">
                                        <strong>Datei enthält Referenten/Sprecher</strong>
                                    </label>
                                    <p class="description" style="margin-left: 25px; margin-bottom: 15px;">
                                        Titel wird als "Aktive Teilnahme an der Veranstaltung: {Name}" gesetzt
                                    </p>
                                    
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" 
                                               id="is_attendee_mode" 
                                               name="is_attendee_mode" 
                                               value="1"
                                               class="mode-checkbox">
                                        <strong>Datei enthält Teilnehmer</strong>
                                    </label>
                                    <p class="description" style="margin-left: 25px;">
                                        Veranstaltungstitel wird ohne Zusatz verwendet
                                    </p>
                                </td>
                            </tr>
                            
                            <tr id="event_name_row" style="display: none;">
                                <th scope="row">
                                    <label for="event_name">Veranstaltungsname *</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="event_name" 
                                           name="event_name" 
                                           class="regular-text"
                                           placeholder="z.B. DGPTM-Jahreskongress 2025">
                                    <p class="description" id="event_name_description">
                                        <!-- Beschreibung wird per JavaScript angepasst -->
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="event_date">Datum der Veranstaltung *</label>
                                </th>
                                <td>
                                    <input type="date" 
                                           id="event_date" 
                                           name="event_date" 
                                           class="regular-text"
                                           value="<?php echo esc_attr( date('Y-m-d') ); ?>"
                                           required>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="event_location">Veranstaltungsort *</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="event_location" 
                                           name="event_location" 
                                           class="regular-text"
                                           placeholder="z.B. Hamburg"
                                           required>
                                    <p class="description">Ort der Veranstaltung</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="event_points">EBCP-Punkte *</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="event_points" 
                                           name="event_points" 
                                           class="small-text"
                                           step="0.1"
                                           min="0"
                                           value="1.0"
                                           required>
                                    <p class="description">Anzahl der EBCP-Punkte für diese Veranstaltung</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="event_type">Veranstaltungsart</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="event_type" 
                                           name="event_type" 
                                           class="regular-text"
                                           value="Präsenzveranstaltung"
                                           placeholder="z.B. Kongress, Workshop">
                                    <p class="description">Optional: Art der Veranstaltung</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="auto_approve">Automatisch freigeben</label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               id="auto_approve" 
                                               name="auto_approve" 
                                               value="1">
                                        Alle importierten Einträge automatisch freigeben
                                    </label>
                                    <p class="description">
                                        Wenn deaktiviert, müssen die Einträge manuell freigegeben werden
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="csv_encoding">Zeichenkodierung</label>
                                </th>
                                <td>
                                    <select id="csv_encoding" name="csv_encoding">
                                        <option value="UTF-8">UTF-8</option>
                                        <option value="ISO-8859-1">ISO-8859-1 (Latin-1)</option>
                                        <option value="Windows-1252">Windows-1252</option>
                                    </select>
                                    <p class="description">
                                        Wählen Sie die Kodierung der CSV-Datei
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary" id="fobi-import-btn">
                                Import starten
                            </button>
                            <span class="spinner" id="fobi-import-spinner" style="float: none; display: none;"></span>
                        </p>
                    </form>
                </div>
                
                <div class="card" style="margin-top: 20px; display: none;" id="fobi-import-results">
                    <h2>Import-Ergebnis</h2>
                    <div id="fobi-import-log" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; line-height: 1.6;">
                    </div>
                </div>
                
                <div class="card" style="margin-top: 20px;">
                    <h2>Hinweise</h2>
                    <ul style="line-height: 1.8;">
                        <li><strong>Multi-File-Upload:</strong> Sie können mehrere CSV-Dateien gleichzeitig hochladen (Strg/Cmd gedrückt halten).</li>
                        
                        <li><strong>Drei Import-Modi:</strong>
                            <ul style="margin-top: 5px;">
                                <li><strong>Normal-Modus:</strong> SESSION_TITLE wird aus CSV übernommen</li>
                                <li><strong>Referenten-Modus:</strong> Titel wird als "Aktive Teilnahme an der Veranstaltung: {Name}" generiert</li>
                                <li><strong>Teilnehmer-Modus:</strong> Veranstaltungstitel wird ohne Zusatz übernommen</li>
                            </ul>
                        </li>
                        
                        <li><strong>Vier CSV-Formate werden unterstützt:</strong>
                            <ul style="margin-top: 5px;">
                                <li>Format 1: <code>FIRST_NAME</code>, <code>LAST_NAME</code>, <code>EMAIL</code>, <code>SESSION_TITLE</code></li>
                                <li>Format 2: <code>NAME</code>, <code>EMAIL</code>, <code>SESSION_TITLE</code> (vollständiger Name wird automatisch aufgeteilt)</li>
                                <li>Format 3: <code>FIRST_NAME</code>, <code>LAST_NAME</code>, <code>EMAIL_ADDRESS</code> (für Referenten/Sprecher-Listen)</li>
                                <li>Format 4: <code>NAME</code>, <code>EMAIL</code>, <code>CHECK_IN_TIME</code>, <code>CHECK_OUT_TIME</code> (für Teilnehmer-Listen)</li>
                            </ul>
                        </li>
                        
                        <li><strong>Benutzer-Zuordnung:</strong> Das System verwendet Fuzzy-Matching mit Scoring (0-100+ Punkte) für intelligente Benutzer-Erkennung.</li>
                        <li><strong>Teilnamen-Erkennung:</strong> Auch Teilnamen werden erkannt (z.B. "Robert Dzieciol" findet "Robert Marek Dzieciol").</li>
                        <li><strong>E-Mail-Fallback:</strong> Bei fehlender Namensübereinstimmung erfolgt Abgleich über E-Mail-Adresse.</li>
                        <li><strong>Duplikat-Prüfung:</strong> Bereits existierende Einträge (gleicher Benutzer, ähnlicher Titel, gleiches Datum) werden übersprungen.</li>
                        <li><strong>Zusätzliche Spalten:</strong> Weitere Spalten wie TICKET_ID, TICKET_CLASS, COMPANY, DESIGNATION, CHECK_IN_TIME etc. werden ignoriert.</li>
                        <li><strong>Nicht gefundene Benutzer:</strong> Werden im Protokoll aufgelistet und müssen zuerst in WordPress angelegt werden.</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
            .fobi-import-container .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .fobi-import-container h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
            }
            #fobi-import-log .log-success {
                color: #46b450;
            }
            #fobi-import-log .log-warning {
                color: #f0b849;
            }
            #fobi-import-log .log-error {
                color: #dc3232;
            }
            #fobi-import-log .log-info {
                color: #00a0d2;
            }
        </style>
        
        <script>
        jQuery(function($) {
            var nonce = '<?php echo esc_js( $nonce ); ?>';
            
            // Mode-Checkboxen: Gegenseitige Ausschließung
            $('.mode-checkbox').on('change', function() {
                if ($(this).is(':checked')) {
                    // Andere Checkbox deaktivieren
                    $('.mode-checkbox').not(this).prop('checked', false);
                    
                    // Event-Name-Feld anzeigen
                    $('#event_name_row').show();
                    $('#event_name').prop('required', true);
                    
                    // Beschreibung anpassen
                    if ($(this).attr('id') === 'is_speaker_mode') {
                        $('#event_name_description').html('Wird verwendet für: "Aktive Teilnahme an der Veranstaltung: <strong>{Ihr Eingabe}</strong>"');
                        $('#event_name').attr('placeholder', 'z.B. DGPTM-Jahreskongress 2025');
                    } else if ($(this).attr('id') === 'is_attendee_mode') {
                        $('#event_name_description').html('Wird als Fortbildungstitel verwendet: "<strong>{Ihre Eingabe}</strong>" (ohne Zusatz)');
                        $('#event_name').attr('placeholder', 'z.B. Grundlagen der Perfusionstechnik');
                    }
                } else {
                    // Wenn keine Checkbox aktiv ist, Event-Name-Feld verstecken
                    if ($('.mode-checkbox:checked').length === 0) {
                        $('#event_name_row').hide();
                        $('#event_name').prop('required', false);
                    }
                }
            });
            
            function log(message, type) {
                type = type || 'info';
                var timestamp = new Date().toLocaleTimeString('de-DE');
                var className = 'log-' + type;
                $('#fobi-import-log').append(
                    '<div class="' + className + '">[' + timestamp + '] ' + message + '</div>'
                );
                $('#fobi-import-log').scrollTop($('#fobi-import-log')[0].scrollHeight);
            }
            
            $('#fobi-csv-import-form').on('submit', function(e) {
                e.preventDefault();
                
                var fileInput = $('#csv_files')[0];
                if (!fileInput.files || fileInput.files.length === 0) {
                    alert('Bitte wählen Sie mindestens eine CSV-Datei aus.');
                    return;
                }
                
                // Prüfe ob einer der Modi aktiviert ist und Event-Name ausgefüllt
                var isSpeakerMode = $('#is_speaker_mode').is(':checked');
                var isAttendeeMode = $('#is_attendee_mode').is(':checked');
                
                if ((isSpeakerMode || isAttendeeMode) && !$('#event_name').val().trim()) {
                    alert('Bitte geben Sie einen Veranstaltungsnamen ein.');
                    return;
                }
                
                $('#fobi-import-btn').prop('disabled', true);
                $('#fobi-import-spinner').show();
                $('#fobi-import-results').show();
                $('#fobi-import-log').html('');
                
                var modeText = '';
                if (isSpeakerMode) {
                    modeText = ' im Referenten-Modus';
                } else if (isAttendeeMode) {
                    modeText = ' im Teilnehmer-Modus';
                }
                
                log('Import von ' + fileInput.files.length + ' Datei(en)' + modeText + ' wird gestartet...', 'info');
                
                var formData = new FormData(this);
                formData.append('action', 'fobi_process_csv_import');
                formData.append('_wpnonce', nonce);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#fobi-import-btn').prop('disabled', false);
                        $('#fobi-import-spinner').hide();
                        
                        if (response.success && response.data) {
                            var data = response.data;
                            
                            log('═══════════════════════════════════════', 'info');
                            log('Import abgeschlossen!', 'success');
                            log('═══════════════════════════════════════', 'info');
                            log('', 'info');
                            log('Statistik:', 'info');
                            log('• Verarbeitete Dateien: ' + data.files_processed, 'info');
                            log('• Gesamt verarbeitet: ' + data.total, 'info');
                            log('• Erfolgreich importiert: ' + data.imported, 'success');
                            log('• Übersprungen (Duplikate): ' + data.skipped, 'warning');
                            log('• Fehler: ' + data.errors, 'error');
                            log('', 'info');
                            
                            if (data.logs && data.logs.length > 0) {
                                log('Detailliertes Protokoll:', 'info');
                                log('───────────────────────────────────────', 'info');
                                data.logs.forEach(function(entry) {
                                    log(entry.message, entry.type);
                                });
                            }
                            
                            if (data.not_found && data.not_found.length > 0) {
                                log('', 'info');
                                log('═══════════════════════════════════════', 'warning');
                                log('Nicht gefundene Benutzer (' + data.not_found.length + '):', 'warning');
                                log('═══════════════════════════════════════', 'warning');
                                data.not_found.forEach(function(user) {
                                    log('• ' + user.name + ' (' + user.email + ')', 'warning');
                                });
                                log('', 'warning');
                                log('Hinweis: Diese Benutzer müssen zuerst in WordPress angelegt werden.', 'warning');
                            }
                            
                        } else {
                            log('Fehler beim Import: ' + (response.data && response.data.message ? response.data.message : 'Unbekannter Fehler'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#fobi-import-btn').prop('disabled', false);
                        $('#fobi-import-spinner').hide();
                        log('HTTP-Fehler: ' + error + ' (Status: ' + xhr.status + ')', 'error');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * CSV-Import verarbeiten (Multi-File + Referenten-Modus)
     */
    public function process_csv_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        }
        
        check_ajax_referer( 'fobi_csv_import' );
        
        // Parameter validieren
        if ( empty( $_FILES['csv_files'] ) || ! is_array( $_FILES['csv_files']['error'] ) ) {
            wp_send_json_error( array( 'message' => 'Keine Dateien hochgeladen.' ) );
        }
        
        $event_date = sanitize_text_field( $_POST['event_date'] ?? '' );
        $event_location = sanitize_text_field( $_POST['event_location'] ?? '' );
        $event_points = floatval( $_POST['event_points'] ?? 0 );
        $event_type = sanitize_text_field( $_POST['event_type'] ?? 'Präsenzveranstaltung' );
        $auto_approve = isset( $_POST['auto_approve'] ) && $_POST['auto_approve'] === '1';
        $encoding = sanitize_text_field( $_POST['csv_encoding'] ?? 'UTF-8' );
        
        // Modi: Referenten oder Teilnehmer
        $is_speaker_mode = isset( $_POST['is_speaker_mode'] ) && $_POST['is_speaker_mode'] === '1';
        $is_attendee_mode = isset( $_POST['is_attendee_mode'] ) && $_POST['is_attendee_mode'] === '1';
        $event_name = '';
        
        if ( $is_speaker_mode || $is_attendee_mode ) {
            $event_name = sanitize_text_field( $_POST['event_name'] ?? '' );
            if ( empty( $event_name ) ) {
                wp_send_json_error( array( 'message' => 'Veranstaltungsname ist erforderlich.' ) );
            }
        }
        
        if ( empty( $event_date ) || empty( $event_location ) || $event_points <= 0 ) {
            wp_send_json_error( array( 'message' => 'Bitte füllen Sie alle Pflichtfelder aus.' ) );
        }
        
        // Mehrere Dateien verarbeiten
        $files = $_FILES['csv_files'];
        $file_count = count( $files['name'] );
        
        $total_imported = 0;
        $total_skipped = 0;
        $total_errors = 0;
        $total_processed = 0;
        $all_logs = array();
        $all_not_found = array();
        
        for ( $i = 0; $i < $file_count; $i++ ) {
            // Fehler bei dieser Datei?
            if ( $files['error'][$i] !== UPLOAD_ERR_OK ) {
                $all_logs[] = array(
                    'message' => 'Datei ' . ($i + 1) . ' (' . $files['name'][$i] . '): Upload-Fehler',
                    'type' => 'error'
                );
                $total_errors++;
                continue;
            }
            
            $file_path = $files['tmp_name'][$i];
            $file_name = $files['name'][$i];
            
            $all_logs[] = array(
                'message' => '═══════════════════════════════════════',
                'type' => 'info'
            );
            $all_logs[] = array(
                'message' => 'Verarbeite Datei: ' . $file_name,
                'type' => 'info'
            );
            $all_logs[] = array(
                'message' => '═══════════════════════════════════════',
                'type' => 'info'
            );
            
            // CSV-Datei einlesen
            $csv_data = $this->parse_csv_file( $file_path, $encoding );
            
            if ( empty( $csv_data ) ) {
                $all_logs[] = array(
                    'message' => 'Datei ' . $file_name . ': Konnte nicht gelesen werden oder ist leer.',
                    'type' => 'error'
                );
                $total_errors++;
                continue;
            }
            
            // Daten verarbeiten
            $result = $this->process_csv_data( $csv_data, array(
                'date' => $event_date,
                'location' => $event_location,
                'points' => $event_points,
                'type' => $event_type,
                'auto_approve' => $auto_approve,
                'is_speaker_mode' => $is_speaker_mode,
                'is_attendee_mode' => $is_attendee_mode,
                'event_name' => $event_name,
                'file_name' => $file_name,
            ) );
            
            $total_imported += $result['imported'];
            $total_skipped += $result['skipped'];
            $total_errors += $result['errors'];
            $total_processed += $result['total'];
            
            $all_logs = array_merge( $all_logs, $result['logs'] );
            $all_not_found = array_merge( $all_not_found, $result['not_found'] );
        }
        
        // Dedupliziere nicht gefundene Benutzer
        $unique_not_found = array();
        $seen = array();
        foreach ( $all_not_found as $user ) {
            $key = $user['email'];
            if ( ! isset( $seen[$key] ) ) {
                $seen[$key] = true;
                $unique_not_found[] = $user;
            }
        }
        
        wp_send_json_success( array(
            'files_processed' => $file_count,
            'total' => $total_processed,
            'imported' => $total_imported,
            'skipped' => $total_skipped,
            'errors' => $total_errors,
            'logs' => $all_logs,
            'not_found' => $unique_not_found,
        ) );
    }
    
    /**
     * Vollständigen Namen in Vor- und Nachname aufteilen
     */
    private function split_full_name( $full_name ) {
        $full_name = trim( $full_name );
        
        if ( empty( $full_name ) ) {
            return array(
                'first_name' => '',
                'last_name' => ''
            );
        }
        
        // Bei Komma-Trennung (Nachname, Vorname)
        if ( strpos( $full_name, ',' ) !== false ) {
            $parts = array_map( 'trim', explode( ',', $full_name, 2 ) );
            return array(
                'first_name' => isset( $parts[1] ) ? $parts[1] : '',
                'last_name' => isset( $parts[0] ) ? $parts[0] : ''
            );
        }
        
        // Standardfall: Leerzeichen-Trennung
        $parts = preg_split( '/\s+/', $full_name );
        
        if ( count( $parts ) === 1 ) {
            // Nur ein Wort → als Nachname behandeln
            return array(
                'first_name' => '',
                'last_name' => $parts[0]
            );
        }
        
        // Erster Teil = Vorname, Rest = Nachname
        $first_name = array_shift( $parts );
        $last_name = implode( ' ', $parts );
        
        return array(
            'first_name' => $first_name,
            'last_name' => $last_name
        );
    }
    
    /**
     * CSV-Datei parsen
     */
    private function parse_csv_file( $file_path, $encoding = 'UTF-8' ) {
        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            return array();
        }
        
        $content = file_get_contents( $file_path );
        
        // Encoding konvertieren
        if ( $encoding !== 'UTF-8' ) {
            if ( function_exists( 'iconv' ) ) {
                $content = iconv( $encoding, 'UTF-8//TRANSLIT', $content );
            } elseif ( function_exists( 'mb_convert_encoding' ) ) {
                $content = mb_convert_encoding( $content, 'UTF-8', $encoding );
            }
        }
        
        // Zeilen aufteilen
        $lines = preg_split( '/\r\n|\r|\n/', $content );
        $lines = array_filter( $lines, function( $line ) {
            return trim( $line ) !== '';
        } );
        
        if ( empty( $lines ) ) {
            return array();
        }
        
        // Trennzeichen erkennen (Komma oder Semikolon)
        $first_line = $lines[0];
        $delimiter = strpos( $first_line, ';' ) !== false ? ';' : ',';
        
        // Header parsen
        $header_raw = str_getcsv( array_shift( $lines ), $delimiter );
        $header = array_map( 'trim', $header_raw );
        
        // Erforderliche Spalten prüfen - zwei Formate unterstützen
        // Format 1: FIRST_NAME, LAST_NAME, EMAIL, SESSION_TITLE
        // Format 2: NAME, EMAIL, SESSION_TITLE (NAME wird aufgespalten)
        // Format 3: FIRST_NAME, LAST_NAME, EMAIL_ADDRESS (Speakers)
        
        $has_split_name = in_array( 'FIRST_NAME', $header, true ) && in_array( 'LAST_NAME', $header, true );
        $has_full_name = in_array( 'NAME', $header, true );
        $has_email = in_array( 'EMAIL', $header, true );
        $has_email_address = in_array( 'EMAIL_ADDRESS', $header, true );
        $has_session = in_array( 'SESSION_TITLE', $header, true );
        
        // E-Mail: entweder EMAIL oder EMAIL_ADDRESS
        if ( ! $has_email && ! $has_email_address ) {
            return array(); // Mindestens eine E-Mail-Spalte erforderlich
        }
        
        // Name: entweder NAME oder FIRST_NAME+LAST_NAME
        if ( ! $has_split_name && ! $has_full_name ) {
            return array(); // Entweder NAME oder FIRST_NAME+LAST_NAME muss vorhanden sein
        }
        
        // SESSION_TITLE ist optional (wird für Referenten generiert)
        
        // Datenzeilen parsen
        $data = array();
        $has_split_name = in_array( 'FIRST_NAME', $header, true ) && in_array( 'LAST_NAME', $header, true );
        $has_full_name = in_array( 'NAME', $header, true );
        $has_email = in_array( 'EMAIL', $header, true );
        $has_email_address = in_array( 'EMAIL_ADDRESS', $header, true );
        
        foreach ( $lines as $line ) {
            $row_raw = str_getcsv( $line, $delimiter );
            if ( count( $row_raw ) !== count( $header ) ) {
                continue; // Zeile überspringen, wenn Anzahl der Spalten nicht passt
            }
            $row = array_combine( $header, array_map( 'trim', $row_raw ) );
            
            // E-Mail normalisieren: EMAIL_ADDRESS → EMAIL
            if ( ! $has_email && $has_email_address && isset( $row['EMAIL_ADDRESS'] ) ) {
                $row['EMAIL'] = $row['EMAIL_ADDRESS'];
            }
            
            // NAME-Spalte aufsplitten, falls vorhanden
            if ( $has_full_name && ! empty( $row['NAME'] ) ) {
                $name_parts = $this->split_full_name( $row['NAME'] );
                $row['FIRST_NAME'] = $name_parts['first_name'];
                $row['LAST_NAME'] = $name_parts['last_name'];
            }
            
            // Leere Zeilen überspringen
            if ( empty( $row['FIRST_NAME'] ) && empty( $row['LAST_NAME'] ) && empty( $row['EMAIL'] ) ) {
                continue;
            }
            
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * CSV-Daten verarbeiten
     */
    private function process_csv_data( $csv_data, $event_data ) {
        $total = count( $csv_data );
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $logs = array();
        $not_found_users = array();
        
        $is_speaker_mode = isset( $event_data['is_speaker_mode'] ) && $event_data['is_speaker_mode'];
        $is_attendee_mode = isset( $event_data['is_attendee_mode'] ) && $event_data['is_attendee_mode'];
        $event_name = isset( $event_data['event_name'] ) ? $event_data['event_name'] : '';
        
        foreach ( $csv_data as $index => $row ) {
            $first_name = sanitize_text_field( $row['FIRST_NAME'] ?? '' );
            $last_name = sanitize_text_field( $row['LAST_NAME'] ?? '' );
            $email = sanitize_email( $row['EMAIL'] ?? '' );
            
            // Session-Titel: Abhängig vom Modus
            if ( $is_speaker_mode && ! empty( $event_name ) ) {
                // Referenten-Modus: "Aktive Teilnahme an der Veranstaltung: {Name}"
                $session_title = 'Aktive Teilnahme an der Veranstaltung: ' . $event_name;
            } elseif ( $is_attendee_mode && ! empty( $event_name ) ) {
                // Teilnehmer-Modus: {Name} ohne Zusatz
                $session_title = $event_name;
            } else {
                // Normal-Modus: SESSION_TITLE aus CSV
                $session_title = sanitize_text_field( $row['SESSION_TITLE'] ?? '' );
            }
            
            // Daten validieren
            if ( empty( $first_name ) || empty( $last_name ) || empty( $session_title ) ) {
                $logs[] = array(
                    'message' => 'Zeile ' . ($index + 2) . ': Unvollständige Daten - übersprungen',
                    'type' => 'warning'
                );
                $skipped++;
                continue;
            }
            
            // Benutzer finden (mit Scoring)
            $match_result = $this->find_user_with_details( $first_name, $last_name, $email );
            
            if ( ! $match_result['user_id'] ) {
                $not_found_users[] = array(
                    'name' => $first_name . ' ' . $last_name,
                    'email' => $email
                );
                $logs[] = array(
                    'message' => 'Zeile ' . ($index + 2) . ': Benutzer "' . $first_name . ' ' . $last_name . '" (' . $email . ') nicht gefunden',
                    'type' => 'error'
                );
                $errors++;
                continue;
            }
            
            $user_id = $match_result['user_id'];
            $match_quality = $match_result['match_quality'];
            $match_details = $match_result['details'];
            
            // Duplikat prüfen
            if ( $this->is_duplicate( $user_id, $session_title, $event_data['date'] ) ) {
                $logs[] = array(
                    'message' => 'Zeile ' . ($index + 2) . ': Duplikat für "' . $first_name . ' ' . $last_name . '" → ' . $match_details . ' - übersprungen',
                    'type' => 'warning'
                );
                $skipped++;
                continue;
            }
            
            // Fortbildungseintrag erstellen
            $post_id = wp_insert_post( array(
                'post_title' => $session_title,
                'post_type' => 'fortbildung',
                'post_status' => 'publish',
            ) );
            
            if ( is_wp_error( $post_id ) || ! $post_id ) {
                $logs[] = array(
                    'message' => 'Zeile ' . ($index + 2) . ': Fehler beim Erstellen des Eintrags für "' . $first_name . ' ' . $last_name . '"',
                    'type' => 'error'
                );
                $errors++;
                continue;
            }
            
            // ACF-Felder aktualisieren
            update_field( 'date', $event_data['date'], $post_id );
            update_field( 'location', $event_data['location'], $post_id );
            update_field( 'points', $event_data['points'], $post_id );
            update_field( 'type', $event_data['type'], $post_id );
            update_field( 'user', $user_id, $post_id );
            update_field( 'freigegeben', $event_data['auto_approve'], $post_id );
            
            // Meta-Daten für Nachverfolgung
            update_post_meta( $post_id, 'csv_import_source', 'csv_import_' . date( 'Y-m-d_H-i-s' ) );
            update_post_meta( $post_id, 'csv_import_email', $email );
            update_post_meta( $post_id, 'csv_import_match_quality', $match_quality );
            
            if ( $is_speaker_mode ) {
                update_post_meta( $post_id, 'csv_import_speaker_mode', true );
                update_post_meta( $post_id, 'csv_import_event_name', $event_name );
            } elseif ( $is_attendee_mode ) {
                update_post_meta( $post_id, 'csv_import_attendee_mode', true );
                update_post_meta( $post_id, 'csv_import_event_name', $event_name );
            }
            
            // Log mit Match-Details
            $type_label = '';
            if ( $is_speaker_mode ) {
                $type_label = ' [Referent]';
            } elseif ( $is_attendee_mode ) {
                $type_label = ' [Teilnehmer]';
            }
            
            $logs[] = array(
                'message' => 'Zeile ' . ($index + 2) . ': ✓ "' . $first_name . ' ' . $last_name . '" → ' . $match_details . ' (' . $match_quality . ')' . $type_label,
                'type' => 'success'
            );
            $imported++;
        }
        
        return array(
            'total' => $total,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'logs' => $logs,
            'not_found' => $not_found_users,
        );
    }
    
    /**
     * Benutzer finden mit Details für Logging
     */
    private function find_user_with_details( $first_name, $last_name, $email ) {
        // Alle Benutzer mit Scoring durchsuchen
        $all_users = get_users( array(
            'fields' => array( 'ID', 'display_name', 'user_email' ),
        ) );
        
        $matches = array();
        
        foreach ( $all_users as $user ) {
            $score = $this->calculate_user_match_score( 
                $first_name, 
                $last_name, 
                $email, 
                $user 
            );
            
            if ( $score > 0 ) {
                $matches[] = array(
                    'user_id' => $user->ID,
                    'score' => $score,
                    'user' => $user
                );
            }
        }
        
        // Keine Matches gefunden
        if ( empty( $matches ) ) {
            return array(
                'user_id' => false,
                'match_quality' => 'Nicht gefunden',
                'details' => ''
            );
        }
        
        // Sortiere nach Score (höchster zuerst)
        usort( $matches, function( $a, $b ) {
            return $b['score'] - $a['score'];
        });
        
        $best_match = $matches[0];
        
        // Prüfe auf Mehrdeutigkeit
        if ( count( $matches ) > 1 ) {
            $second_best = $matches[1];
            
            if ( $best_match['score'] < 100 && 
                 ( $best_match['score'] - $second_best['score'] ) < 20 ) {
                return array(
                    'user_id' => false,
                    'match_quality' => 'Mehrdeutig',
                    'details' => 'Mehrere ähnliche Treffer'
                );
            }
        }
        
        // Mindest-Score erforderlich
        if ( $best_match['score'] < 50 ) {
            return array(
                'user_id' => false,
                'match_quality' => 'Score zu niedrig',
                'details' => 'Score: ' . $best_match['score']
            );
        }
        
        // Match-Qualität bestimmen
        $quality = 'Gut';
        if ( $best_match['score'] >= 100 ) {
            $quality = 'Perfekt';
        } elseif ( $best_match['score'] >= 80 ) {
            $quality = 'Sehr gut';
        } elseif ( $best_match['score'] >= 70 ) {
            $quality = 'Gut';
        } else {
            $quality = 'Ausreichend';
        }
        
        // Details zum Match
        $wp_user = $best_match['user'];
        $wp_first = get_user_meta( $wp_user->ID, 'first_name', true );
        $wp_last = get_user_meta( $wp_user->ID, 'last_name', true );
        $wp_full = trim( $wp_first . ' ' . $wp_last );
        
        return array(
            'user_id' => $best_match['user_id'],
            'match_quality' => $quality,
            'details' => $wp_full . ' (Score: ' . $best_match['score'] . ')'
        );
    }
    
    /**
     * Berechne Match-Score für einen Benutzer
     * Höherer Score = besserer Match
     */
    private function calculate_user_match_score( $csv_first, $csv_last, $csv_email, $wp_user ) {
        $score = 0;
        
        $wp_first = get_user_meta( $wp_user->ID, 'first_name', true );
        $wp_last = get_user_meta( $wp_user->ID, 'last_name', true );
        $wp_email = $wp_user->user_email;
        $wp_display = $wp_user->display_name;
        
        // Normalisiere für Vergleich
        $csv_first_norm = $this->normalize_name( $csv_first );
        $csv_last_norm = $this->normalize_name( $csv_last );
        $wp_first_norm = $this->normalize_name( $wp_first );
        $wp_last_norm = $this->normalize_name( $wp_last );
        
        // === EXAKTE MATCHES (höchster Score) ===
        
        // 1. Exakter Vorname + Exakter Nachname = 100 Punkte (perfekt)
        if ( $csv_first_norm === $wp_first_norm && $csv_last_norm === $wp_last_norm ) {
            $score += 100;
        }
        
        // 2. Exakte E-Mail = 100 Punkte (perfekt)
        if ( ! empty( $csv_email ) && ! empty( $wp_email ) ) {
            if ( strcasecmp( $csv_email, $wp_email ) === 0 ) {
                $score += 100;
            }
        }
        
        // === PARTIAL MATCHES (mittlerer Score) ===
        
        // 3. Vorname exakt + Nachname ist enthalten = 80 Punkte
        if ( $csv_first_norm === $wp_first_norm && ! empty( $csv_last_norm ) && ! empty( $wp_last_norm ) ) {
            if ( $this->name_contains( $wp_last_norm, $csv_last_norm ) ) {
                $score += 80;
            }
        }
        
        // 4. Nachname exakt + Vorname ist enthalten = 75 Punkte
        if ( $csv_last_norm === $wp_last_norm && ! empty( $csv_first_norm ) && ! empty( $wp_first_norm ) ) {
            if ( $this->name_contains( $wp_first_norm, $csv_first_norm ) ) {
                $score += 75;
            }
        }
        
        // 5. Beide Namen sind enthalten (z.B. "Robert Dzieciol" in "Robert Marek Dzieciol") = 70 Punkte
        if ( ! empty( $csv_first_norm ) && ! empty( $csv_last_norm ) && 
             ! empty( $wp_first_norm ) && ! empty( $wp_last_norm ) ) {
            
            $wp_full = $wp_first_norm . ' ' . $wp_last_norm;
            $csv_full = $csv_first_norm . ' ' . $csv_last_norm;
            
            if ( $this->name_contains( $wp_full, $csv_first_norm ) && 
                 $this->name_contains( $wp_full, $csv_last_norm ) ) {
                $score += 70;
            }
        }
        
        // 6. Display Name enthält beide Namen = 60 Punkte
        if ( ! empty( $wp_display ) && ! empty( $csv_first_norm ) && ! empty( $csv_last_norm ) ) {
            $wp_display_norm = $this->normalize_name( $wp_display );
            if ( $this->name_contains( $wp_display_norm, $csv_first_norm ) && 
                 $this->name_contains( $wp_display_norm, $csv_last_norm ) ) {
                $score += 60;
            }
        }
        
        // === ZUSATZ-PUNKTE ===
        
        // 7. E-Mail-Domain übereinstimmung = +10 Punkte
        if ( ! empty( $csv_email ) && ! empty( $wp_email ) ) {
            $csv_domain = substr( strrchr( $csv_email, '@' ), 1 );
            $wp_domain = substr( strrchr( $wp_email, '@' ), 1 );
            
            if ( ! empty( $csv_domain ) && ! empty( $wp_domain ) ) {
                if ( strcasecmp( $csv_domain, $wp_domain ) === 0 ) {
                    $score += 10;
                }
            }
        }
        
        // 8. Initialen-Match als zusätzlicher Indikator = +5 Punkte
        if ( ! empty( $csv_first_norm ) && ! empty( $wp_first_norm ) ) {
            if ( substr( $csv_first_norm, 0, 1 ) === substr( $wp_first_norm, 0, 1 ) ) {
                $score += 5;
            }
        }
        
        return $score;
    }
    
    /**
     * Prüfe ob ein Name einen anderen enthält (als ganzes Wort)
     */
    private function name_contains( $haystack, $needle ) {
        if ( empty( $haystack ) || empty( $needle ) ) {
            return false;
        }
        
        // Als ganzes Wort suchen (mit Wortgrenzen)
        $pattern = '/\b' . preg_quote( $needle, '/' ) . '\b/i';
        return preg_match( $pattern, $haystack ) === 1;
    }
    
    /**
     * Normalisiere Namen für Vergleich (lowercase, Leerzeichen bereinigen)
     */
    private function normalize_name( $name ) {
        $name = trim( $name );
        $name = mb_strtolower( $name, 'UTF-8' );
        $name = preg_replace( '/\s+/', ' ', $name ); // Multiple Leerzeichen zu einem
        return $name;
    }
    
    /**
     * Duplikat-Prüfung (verbessert - auch bei ähnlichen Namen)
     */
    private function is_duplicate( $user_id, $title, $date ) {
        // Suche nach existierenden Einträgen für diesen Benutzer mit gleichem Datum
        $existing = get_posts( array(
            'post_type' => 'fortbildung',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'user',
                    'value' => $user_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'date',
                    'value' => $date,
                    'compare' => '='
                ),
            ),
        ) );
        
        if ( empty( $existing ) ) {
            return false;
        }
        
        // Prüfe ob der Titel ähnlich ist (nicht nur exakt)
        $title_norm = $this->normalize_title( $title );
        
        foreach ( $existing as $post_id ) {
            $existing_title = get_the_title( $post_id );
            $existing_title_norm = $this->normalize_title( $existing_title );
            
            // Exakter Titel-Match
            if ( $title_norm === $existing_title_norm ) {
                return true;
            }
            
            // Ähnlicher Titel (>80% Übereinstimmung)
            $similarity = 0;
            similar_text( $title_norm, $existing_title_norm, $similarity );
            if ( $similarity > 80 ) {
                return true;
            }
            
            // Einer ist im anderen enthalten (und beide > 20 Zeichen)
            if ( strlen( $title_norm ) > 20 && strlen( $existing_title_norm ) > 20 ) {
                if ( strpos( $existing_title_norm, $title_norm ) !== false || 
                     strpos( $title_norm, $existing_title_norm ) !== false ) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Normalisiere Titel für Vergleich
     */
    private function normalize_title( $title ) {
        $title = trim( $title );
        $title = mb_strtolower( $title, 'UTF-8' );
        // Sonderzeichen entfernen
        $title = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $title );
        // Multiple Leerzeichen zu einem
        $title = preg_replace( '/\s+/', ' ', $title );
        return trim( $title );
    }
}

// Instanz erstellen
new Fortbildung_CSV_Import();