<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_WorkDrive {

    private $settings;
    private $api_base = 'https://www.zohoapis.eu/workdrive/api/v1/';

    public function __construct($settings) {
        $this->settings = $settings;
    }

    private function get_token() {
        if (function_exists('dgptm_get_zoho_token')) {
            return dgptm_get_zoho_token();
        }
        if (class_exists('DGPTM_CRM_Abruf')) {
            $crm = DGPTM_CRM_Abruf::get_instance();
            if (method_exists($crm, 'get_valid_access_token')) {
                return $crm->get_valid_access_token();
            }
        }
        return get_option('dgptm_zoho_access_token', '');
    }

    private function headers($content_type = 'application/json') {
        return [
            'Authorization' => 'Zoho-oauthtoken ' . $this->get_token(),
            'Content-Type'  => $content_type,
        ];
    }

    /**
     * Ordner fuer eine Bewerbung erstellen.
     *
     * Struktur: Team-Folder / Stipendientyp / Runde / Bewerbung_NNN_Nachname_Vorname
     *
     * @param string $stipendientyp z.B. "Promotionsstipendium"
     * @param string $runde         z.B. "2026 - Ausschreibung 2026"
     * @param string $nachname      Nachname des Bewerbers
     * @param string $vorname       Vorname des Bewerbers
     * @param int    $nummer        Laufende Nummer der Bewerbung
     * @return string|WP_Error Folder-ID oder Fehler
     */
    public function create_bewerbung_ordner($stipendientyp, $runde, $nachname, $vorname, $nummer) {
        $root_folder_id = $this->settings->get('workdrive_team_folder_id');
        if (empty($root_folder_id)) {
            return new WP_Error('workdrive_config', 'WorkDrive Team-Folder ID nicht konfiguriert.');
        }

        // 1. Stipendientyp-Ordner sicherstellen
        $typ_folder = $this->ensure_subfolder($root_folder_id, $stipendientyp);
        if (is_wp_error($typ_folder)) return $typ_folder;

        // 2. Runden-Ordner sicherstellen
        $runde_folder = $this->ensure_subfolder($typ_folder, $runde);
        if (is_wp_error($runde_folder)) return $runde_folder;

        // 3. Bewerbungs-Ordner erstellen
        $folder_name = sprintf('Bewerbung_%03d_%s_%s',
            $nummer,
            $this->sanitize_filename($nachname),
            $this->sanitize_filename($vorname)
        );
        return $this->create_folder($runde_folder, $folder_name);
    }

    /**
     * Datei in einen Ordner hochladen.
     *
     * @param string $folder_id   WorkDrive Folder-ID
     * @param string $file_path   Lokaler Dateipfad
     * @param string $file_name   Gewuenschter Dateiname in WorkDrive
     * @return array|WP_Error     Upload-Ergebnis mit 'id' und 'url'
     */
    public function upload_file($folder_id, $file_path, $file_name) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Datei nicht gefunden: ' . $file_path);
        }

        $url = $this->api_base . 'upload?parent_id=' . $folder_id . '&override-name-exist=true';

        $boundary = wp_generate_password(24, false);
        $body  = '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="content"; filename="' . $file_name . '"' . "\r\n";
        $body .= 'Content-Type: ' . wp_check_filetype($file_path)['type'] . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= '--' . $boundary . '--';

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $this->get_token(),
                'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body'    => $body,
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            $this->log_error('WorkDrive Upload fehlgeschlagen: ' . $response->get_error_message());
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $file_id = $data['data'][0]['attributes']['resource_id'] ?? null;

        if (!$file_id) {
            return new WP_Error('workdrive_upload', 'Upload-Antwort ohne resource_id.');
        }

        return [
            'id'  => $file_id,
            'url' => $this->get_share_link($file_id),
        ];
    }

    /**
     * Share-Link fuer eine Datei erstellen.
     */
    public function get_share_link($resource_id) {
        $cache_key = 'dgptm_wd_share_' . $resource_id;
        $cached = get_transient($cache_key);
        if ($cached) return $cached;

        $url = $this->api_base . 'files/' . $resource_id . '/links';
        $body = json_encode([
            'data' => [
                'attributes' => [
                    'link_type'   => 'view',
                    'request_type' => 'externallink',
                    'allow_download' => true,
                ],
                'type' => 'links',
            ],
        ]);

        $response = wp_remote_post($url, [
            'headers' => $this->headers(),
            'body'    => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) return '';

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $link = $data['data']['attributes']['link'] ?? '';

        if ($link) {
            set_transient($cache_key, $link, DAY_IN_SECONDS);
        }
        return $link;
    }

    /**
     * Ordner loeschen (fuer DSGVO-Cleanup).
     */
    public function delete_folder($folder_id) {
        $url = $this->api_base . 'files/' . $folder_id;
        $response = wp_remote_request($url, [
            'method'  => 'PATCH',
            'headers' => $this->headers(),
            'body'    => json_encode([
                'data' => [
                    'attributes' => ['status' => '61'], // Trash
                    'type' => 'files',
                ],
            ]),
            'timeout' => 15,
        ]);
        return !is_wp_error($response);
    }

    /* ── Hilfsfunktionen ─────────────────────── */

    private function ensure_subfolder($parent_id, $name) {
        // Existierenden Ordner suchen
        $url = $this->api_base . 'files/' . $parent_id . '/files?filter[type]=folder';
        $response = wp_remote_get($url, [
            'headers' => $this->headers(),
            'timeout' => 15,
        ]);

        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            foreach (($data['data'] ?? []) as $item) {
                if (($item['attributes']['name'] ?? '') === $name) {
                    return $item['id'];
                }
            }
        }

        // Nicht gefunden — neu erstellen
        return $this->create_folder($parent_id, $name);
    }

    private function create_folder($parent_id, $name) {
        $url = $this->api_base . 'files';
        $body = json_encode([
            'data' => [
                'attributes' => [
                    'name'      => $name,
                    'parent_id' => $parent_id,
                ],
                'type' => 'files',
            ],
        ]);

        $response = wp_remote_post($url, [
            'headers' => $this->headers(),
            'body'    => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->log_error('WorkDrive Ordner-Erstellung fehlgeschlagen: ' . $response->get_error_message());
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['data']['id'] ?? new WP_Error('workdrive_create', 'Ordner-ID nicht in Antwort.');
    }

    private function sanitize_filename($str) {
        $str = remove_accents($str);
        $str = preg_replace('/[^a-zA-Z0-9_-]/', '_', $str);
        return $str;
    }

    private function log_error($message) {
        if (function_exists('dgptm_log_error')) {
            dgptm_log_error($message, 'stipendium');
        }
    }
}
