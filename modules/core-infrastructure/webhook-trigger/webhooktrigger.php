<?php
/**
 * Plugin Name: DGPTM - Webhook Trigger und Studinachweis
 * Description: Sendet einen Webhook-Request via Ajax (Shortcode [webhook_ajax_trigger]) und bietet zusätzlich ein Studienbescheinigungs-Upload-Formular (Shortcode [studierendenstatue_beantragen]) inkl. Token-basiertem Löschen ohne Login und automatischem Aufräumen nach 7 Tagen.
 * Version: 2.0.3 (final)
 * Author: Sebastian Melzer
 */

if (!defined('ABSPATH')) {
    exit; // Verhindert direkten Zugriff
}

/* --------------------------------------------------
 *  0) Automatische Bereinigung alter Uploads
 *     Prüft bei jedem Seitenaufruf, ob Dateien älter als 7 Tage sind.
 * -------------------------------------------------- */
add_action('init', 'dgptm_cleanup_old_uploads');
function dgptm_cleanup_old_uploads() {
    $stored_files = get_option('dgptm_files', []);
    if (!is_array($stored_files)) {
        $stored_files = [];
    }

    $now = time();
    $changed = false;

    foreach ($stored_files as $token => $info) {
        if (!isset($info['created_at'])) {
            continue;
        }
        // Älter als 7 Tage?
        if (($now - $info['created_at']) > 7 * DAY_IN_SECONDS) {
            // Datei löschen
            if (!empty($info['file_path']) && file_exists($info['file_path'])) {
                @unlink($info['file_path']);
            }
            unset($stored_files[$token]);
            $changed = true;
        }
    }

    if ($changed) {
        update_option('dgptm_files', $stored_files);
    }
}

/* --------------------------------------------------
 *  1) Shortcode: [webhook_ajax_trigger]
 *     Ursprüngliche Funktion: Sendet Webhook per Ajax.
 * -------------------------------------------------- */
function webhook_ajax_shortcode_handler($atts) {
    $atts = shortcode_atts([
        'url'         => '',
        'method'      => 'POST',
        'status_id'   => '',
        'success_msg' => '',
        'error_msg'   => '',
    ], $atts);

    if (empty($atts['url'])) {
        return '<div class="webhook-message error">Webhook-URL ist erforderlich.</div>';
    }

    // Wenn kein status_id angegeben, erzeugen wir ein Div neben dem Button
    $unique_id = uniqid('webhook_output_');

    ob_start();
    ?>
    <button 
        class="webhook-trigger-btn"
        data-url="<?php echo esc_attr($atts['url']); ?>"
        data-method="<?php echo esc_attr($atts['method']); ?>"
        data-status-id="<?php echo esc_attr($atts['status_id']); ?>"
        data-success-msg="<?php echo esc_attr($atts['success_msg']); ?>"
        data-error-msg="<?php echo esc_attr($atts['error_msg']); ?>"
    >
        Mitgliedsbescheinigung anfordern
    </button>

    <?php if (empty($atts['status_id'])): ?>
        <!-- Nur erzeugen, wenn keine separate ID gewünscht -->
        <div id="<?php echo esc_attr($unique_id); ?>" class="webhook-output"></div>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
add_shortcode('webhook_ajax_trigger', 'webhook_ajax_shortcode_handler');


/* --------------------------------------------------
 *  2) Ajax-Handler für [webhook_ajax_trigger]
 *     Sendet JSON success/error zurück.
 * -------------------------------------------------- */
function webhook_ajax_handler() {
    // URL prüfen
    if (empty($_POST['url'])) {
        wp_send_json_error([
            'message' => '<div class="webhook-message error">Webhook-URL ist erforderlich.</div>'
        ]);
    }

    $url         = sanitize_text_field($_POST['url']);
    $method      = !empty($_POST['method'])      ? strtoupper(sanitize_text_field($_POST['method']))      : 'POST';
    $success_msg = !empty($_POST['success_msg']) ? sanitize_text_field($_POST['success_msg'])            : 'Erfolg!';
    $error_msg   = !empty($_POST['error_msg'])   ? sanitize_text_field($_POST['error_msg'])              : 'Fehler!';

    // Nur eingeloggte
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        wp_send_json_error([
            'message' => '<div class="webhook-message error">Benutzer ist nicht eingeloggt.</div>'
        ]);
    }

    // Payload
    $user_id = get_current_user_id(); 
    $user_meta  = [
        'zoho_id' => get_user_meta($user_id, 'zoho_id', true),
        // Statt nickname => user_id
        'user_id' => $user_id,
    ];

    // Optionales Logging
    error_log('Webhook Ajax Payload: ' . print_r($user_meta, true));

    $args = [
        'method'  => $method,
        'body'    => json_encode($user_meta),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ];

    // Request abfeuern
    $response = wp_remote_request($url, $args);
    if (is_wp_error($response)) {
        wp_send_json_error([
            'message' => '<div class="webhook-message error">' 
                        . esc_html($error_msg . ': ' . $response->get_error_message()) 
                        . '</div>'
        ]);
    }

    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code >= 200 && $status_code < 300) {
        // Erfolg
        wp_send_json_success([
            'message' => '<div class="webhook-message success">' 
                        . esc_html($success_msg) 
                        . '</div>'
        ]);
    } else {
        // HTTP-Fehler
        wp_send_json_error([
            'message' => '<div class="webhook-message error">' 
                        . esc_html($error_msg) 
                        . ' (HTTP Status: ' . (int)$status_code . ')' 
                        . '</div>'
        ]);
    }
}
add_action('wp_ajax_webhook_trigger', 'webhook_ajax_handler');
add_action('wp_ajax_nopriv_webhook_trigger', 'webhook_ajax_handler');


/* --------------------------------------------------
 *  3) Shortcode: [webhook_status_output id="mgb"]
 *     Nur ein DIV mit Ausgabebereich (ID).
 * -------------------------------------------------- */
function webhook_status_output_shortcode($atts) {
    $atts = shortcode_atts(['id' => ''], $atts);

    if (empty($atts['id'])) {
        return '<div class="webhook-message error">ID für Statusausgabe ist erforderlich.</div>';
    }
    return '<div id="' . esc_attr($atts['id']) . '" class="webhook-output"></div>';
}
add_shortcode('webhook_status_output', 'webhook_status_output_shortcode');


/* --------------------------------------------------
 *  4) Script-Einbindung für das Original-Webhook-Feature
 * -------------------------------------------------- */
function webhook_enqueue_scripts() {
    wp_enqueue_script(
        'webhook-ajax-script',
        plugin_dir_url(__FILE__) . 'js/webhook-ajax.js',
        ['jquery'],
        '2.0.3', // Version
        true
    );

    wp_localize_script(
        'webhook-ajax-script',
        'webhookAjax',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]
    );
}
add_action('wp_enqueue_scripts', 'webhook_enqueue_scripts');


/* --------------------------------------------------
 *  5) NEUES Feature:
 *     Shortcode [studierendenstatue_beantragen url="..."]
 *     - Modal-Formular für PDF/Bild-Upload (max. 2MB)
 *     - Sendet user_id, zoho_id, year, file_url, delete_token an "url".
 * -------------------------------------------------- */
function dgptm_student_certificate_form_shortcode($atts) {
    $atts = shortcode_atts([
        'url' => '', // Ziel-API-Endpoint
    ], $atts);

    if (empty($atts['url'])) {
        return '<div class="webhook-message error">Bitte eine Ziel-URL (url-Attribut) angeben.</div>';
    }

    // Einzigartige ID für das Modal
    $modal_id = 'dgptm-studierendenstatue-modal-' . uniqid();

    ob_start();
    ?>
    <!-- Button zum Öffnen des Modals -->
    <button type="button" class="dgptm-open-modal-btn" data-modal-id="<?php echo esc_attr($modal_id); ?>">
        Studierendenstatus beantragen
    </button>

    <!-- Modal-Container (zunächst ausgeblendet) -->
    <div id="<?php echo esc_attr($modal_id); ?>" class="dgptm-modal" style="display: none;">
        <div class="dgptm-modal-content">
            <span class="dgptm-close-modal">&times;</span>
            <h2>Studienbescheinigung einreichen</h2>
            
            <div class="dgptm-modal-body">
                <label for="dgptm-year-input">Gültig bis Beitragsjahr:</label><br/>
                <input type="text" id="dgptm-year-input" name="dgptm-year-input" placeholder="z.B. 2025" /><br/><br/>
                
                <label for="dgptm-file-input">hier Studienbescheinigung hochladen (PDF, JPG, PNG, max. 2MB):</label><br/>
                <input type="file" id="dgptm-file-input" name="dgptm-file-input" accept=".pdf,.jpg,.png,.jpeg" /><br/><br/>
                
                <button type="button" 
                        id="dgptm-upload-btn" 
                        data-upload-url="<?php echo esc_attr($atts['url']); ?>">
                    Hochladen &amp; Senden
                </button>
                
                <div id="dgptm-upload-response" style="margin-top:10px;"></div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('studierendenstatue_beantragen', 'dgptm_student_certificate_form_shortcode');


/* --------------------------------------------------
 *  6) Ajax-Handler für das Upload-Formular
 *     Nur PDF/Bilder, max. 2MB, Datei 7 Tage speichern
 *     Token-basiertes Löschen ohne Login.
 * -------------------------------------------------- */
function dgptm_handle_student_certificate_upload() {
    // Nur eingeloggte dürfen hochladen
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Bitte logge dich ein, um eine Studienbescheinigung hochzuladen.']);
    }

    $year       = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
    $target_url = isset($_POST['target_url']) ? esc_url_raw($_POST['target_url']) : '';

    if (empty($year) || empty($target_url)) {
        wp_send_json_error(['message' => 'Jahr und/oder Ziel-URL fehlen.']);
    }

    if (!isset($_FILES['certificate_file'])) {
        wp_send_json_error(['message' => 'Keine Datei empfangen.']);
    }

    $file = $_FILES['certificate_file'];

    // Max 2MB
    if ($file['size'] > 2 * 1024 * 1024) {
        wp_send_json_error(['message' => 'Die Datei darf maximal 2MB groß sein.']);
    }

    // Erlaubte MIME-Typen
    $allowed_mimes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
    ];
    if (!in_array($file['type'], $allowed_mimes)) {
        wp_send_json_error(['message' => 'Nur PDF, JPG und PNG-Dateien sind erlaubt.']);
    }

    // WP-Upload-Funktion
    $upload_overrides = [
        'test_form' => false,
        'unique_filename_callback' => 'dgptm_random_filename'
    ];

    $movefile = wp_handle_upload($file, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        $file_url  = $movefile['url'];
        $file_path = $movefile['file']; // Serverpfad

        // Token generieren
        $delete_token = md5(uniqid('', true));

        // Eintrag in dgptm_files-Option speichern
        $stored_files = get_option('dgptm_files', []);
        if (!is_array($stored_files)) {
            $stored_files = [];
        }
        $stored_files[$delete_token] = [
            'file_path'  => $file_path,
            'created_at' => time(),
        ];
        update_option('dgptm_files', $stored_files);

        // User-Infos
        $current_user_id = get_current_user_id();
        $zoho_id         = get_user_meta($current_user_id, 'zoho_id', true);

        // Payload an die Ziel-URL
        $payload = [
            'user_id'      => $current_user_id,
            'zoho_id'      => $zoho_id,
            'year'         => $year,
            'file_url'     => $file_url,
            'delete_token' => $delete_token,
        ];

        $args = [
            'method'  => 'POST',
            'body'    => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];

        $response = wp_remote_request($target_url, $args);
        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => 'Datei hochgeladen, aber Fehler beim Senden: ' . $response->get_error_message()
            ]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 200 && $status_code < 300) {
            wp_send_json_success([
                'message'  => 'Datei erfolgreich hochgeladen und gesendet!',
                'file_url' => $file_url
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Datei hochgeladen, aber Senden fehlgeschlagen (HTTP Status: ' . $status_code . ')'
            ]);
        }
    } else {
        // Upload-Fehler
        $error_msg = isset($movefile['error']) ? $movefile['error'] : 'Unbekannter Fehler beim Upload.';
        wp_send_json_error(['message' => $error_msg]);
    }
}
add_action('wp_ajax_student_certificate_upload', 'dgptm_handle_student_certificate_upload');
add_action('wp_ajax_nopriv_student_certificate_upload', 'dgptm_handle_student_certificate_upload');


/**
 * Zufällige Dateinamen erzeugen
 */
function dgptm_random_filename($dir, $name, $ext) {
    return md5(uniqid('', true)) . $ext;
}


/* --------------------------------------------------
 *  7) REST-Route zum Token-basierten Löschen (ohne Login).
 *     /wp-json/dgptm/v1/delete_file (POST mit Param 'token').
 * -------------------------------------------------- */
add_action('rest_api_init', function () {
    register_rest_route('dgptm/v1', '/delete_file', [
        'methods'             => 'POST',
        'callback'            => 'dgptm_delete_uploaded_file',
        // Kein Login erforderlich
        'permission_callback' => '__return_true'
    ]);
});

/**
 * Löscht Datei anhand des Token-Eintrags in dgptm_files.
 * Danach entfernt sie den Eintrag aus der Option.
 */
function dgptm_delete_uploaded_file(WP_REST_Request $request) {
    $token = $request->get_param('token');
    if (!$token) {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'Kein Token angegeben.'
        ], 400);
    }

    $stored_files = get_option('dgptm_files', []);
    if (!is_array($stored_files)) {
        $stored_files = [];
    }

    // Token vorhanden?
    if (!isset($stored_files[$token])) {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'Ungültiges oder abgelaufenes Token.'
        ], 403);
    }

    $info      = $stored_files[$token];
    $file_path = isset($info['file_path']) ? $info['file_path'] : '';

    // Datei löschen
    if ($file_path && file_exists($file_path)) {
        @unlink($file_path);
    }

    // Eintrag entfernen
    unset($stored_files[$token]);
    update_option('dgptm_files', $stored_files);

    return new WP_REST_Response([
        'status'  => 'success',
        'message' => 'Datei erfolgreich gelöscht.'
    ], 200);
}


/* --------------------------------------------------
 *  8) JavaScript für das Modal und den Upload
 *     (Dateiname: student-certificate.js)
 * -------------------------------------------------- */
function dgptm_student_certificate_scripts() {
    // Inkl. jQuery
    wp_enqueue_script(
        'dgptm-student-certificate-js',
        plugin_dir_url(__FILE__) . 'js/student-certificate.js',
        ['jquery'],
        '2.0.3',
        true
    );

    wp_localize_script(
        'dgptm-student-certificate-js',
        'dgptmUpload',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]
    );
}
add_action('wp_enqueue_scripts', 'dgptm_student_certificate_scripts');
