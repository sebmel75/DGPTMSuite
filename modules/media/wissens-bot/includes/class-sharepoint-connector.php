<?php
/**
 * SharePoint Connector für Wissens-Bot
 * Greift auf SharePoint-Dokumente zu
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wissens_Bot_SharePoint_Connector {
    
    private $tenant_id;
    private $client_id;
    private $client_secret;
    private $site_url;
    private $folders;
    private $access_token;
    
    public function __construct() {
        $this->tenant_id = get_option('wissens_bot_sharepoint_tenant_id');
        $this->client_id = get_option('wissens_bot_sharepoint_client_id');
        $this->client_secret = get_option('wissens_bot_sharepoint_client_secret');
        $this->site_url = get_option('wissens_bot_sharepoint_site_url');
        $this->folders = get_option('wissens_bot_sharepoint_folders');
    }
    
    /**
     * Durchsucht SharePoint-Dokumente
     */
    public function search_documents($query, $max_results = 5) {
        if (!$this->validate_config()) {
            return new WP_Error('invalid_config', __('SharePoint nicht korrekt konfiguriert.', 'wissens-bot'));
        }
        
        // Access Token holen
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }
        
        $this->access_token = $token;
        
        // Dokumente durchsuchen
        $results = array();
        $folders = array_filter(array_map('trim', explode("\n", $this->folders)));
        
        foreach ($folders as $folder) {
            $folder_results = $this->search_folder($folder, $query, $max_results);
            if (!is_wp_error($folder_results)) {
                $results = array_merge($results, $folder_results);
            }
        }
        
        return array_slice($results, 0, $max_results);
    }
    
    /**
     * Validiert die Konfiguration
     */
    private function validate_config() {
        return !empty($this->tenant_id) 
            && !empty($this->client_id) 
            && !empty($this->client_secret)
            && !empty($this->site_url);
    }
    
    /**
     * Holt OAuth Access Token
     */
    private function get_access_token() {
        // Prüfe ob Token im Transient gecacht ist
        $cached_token = get_transient('wissens_bot_sharepoint_token');
        if ($cached_token !== false) {
            return $cached_token;
        }
        
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $response = wp_remote_post($token_url, array(
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['access_token'])) {
            return new WP_Error('token_error', __('Konnte Access Token nicht erhalten.', 'wissens-bot'));
        }
        
        // Token für 50 Minuten cachen (läuft nach 60 ab)
        set_transient('wissens_bot_sharepoint_token', $body['access_token'], 50 * MINUTE_IN_SECONDS);
        
        return $body['access_token'];
    }
    
    /**
     * Durchsucht einen SharePoint-Ordner
     */
    private function search_folder($folder_path, $query, $max_results) {
        // Parse Site URL
        $site_parts = parse_url($this->site_url);
        $host = $site_parts['host'];
        $site_path = isset($site_parts['path']) ? trim($site_parts['path'], '/') : '';
        
        // Konstruiere Graph API URL für Suche
        $encoded_folder = urlencode($folder_path);
        $search_url = "https://graph.microsoft.com/v1.0/sites/{$host}:/{$site_path}:/drive/root:/{$encoded_folder}:/children";
        
        $response = wp_remote_get($search_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['value'])) {
            return array();
        }
        
        $results = array();
        
        foreach ($body['value'] as $item) {
            // Nur PDFs verarbeiten
            if (!isset($item['file']) || !$this->is_pdf($item['name'])) {
                continue;
            }
            
            // Prüfe ob Dateiname zum Query passt
            if (!$this->matches_query($item['name'], $query)) {
                continue;
            }
            
            // PDF-Inhalt extrahieren
            $content = $this->extract_pdf_content($item['@microsoft.graph.downloadUrl']);
            
            if (!empty($content)) {
                $results[] = array(
                    'source' => 'SharePoint',
                    'title' => $item['name'],
                    'content' => $content,
                    'url' => $item['webUrl'],
                    'modified' => $item['lastModifiedDateTime']
                );
            }
            
            if (count($results) >= $max_results) {
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Prüft ob Datei eine PDF ist
     */
    private function is_pdf($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'pdf';
    }
    
    /**
     * Prüft ob Dateiname zum Query passt
     */
    private function matches_query($filename, $query) {
        $query_words = explode(' ', strtolower($query));
        $filename_lower = strtolower($filename);
        
        foreach ($query_words as $word) {
            if (strlen($word) > 3 && stripos($filename_lower, $word) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extrahiert Text aus PDF
     */
    private function extract_pdf_content($download_url) {
        // PDF herunterladen
        $response = wp_remote_get($download_url, array(
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $pdf_data = wp_remote_retrieve_body($response);
        
        // Temporäre Datei erstellen
        $temp_file = wp_tempnam('sharepoint_pdf');
        file_put_contents($temp_file, $pdf_data);
        
        // Text extrahieren (verschiedene Methoden)
        $text = $this->extract_text_from_pdf($temp_file);
        
        // Temp-Datei löschen
        @unlink($temp_file);
        
        // Text kürzen auf max. 3000 Zeichen
        return substr($text, 0, 3000);
    }
    
    /**
     * Extrahiert Text aus PDF-Datei
     */
    private function extract_text_from_pdf($filepath) {
        // Methode 1: pdftotext (wenn verfügbar)
        if (function_exists('shell_exec') && $this->command_exists('pdftotext')) {
            $output = shell_exec('pdftotext ' . escapeshellarg($filepath) . ' -');
            if (!empty($output)) {
                return $output;
            }
        }
        
        // Methode 2: Basic PDF-Parsing (einfache Implementierung)
        $content = file_get_contents($filepath);
        
        // Extrahiere Text zwischen 'stream' und 'endstream'
        if (preg_match_all('/stream\s*(.+?)\s*endstream/s', $content, $matches)) {
            $text = '';
            foreach ($matches[1] as $match) {
                // Dekodiere wenn möglich
                $decoded = @gzuncompress($match);
                if ($decoded !== false) {
                    $text .= $decoded . ' ';
                } else {
                    $text .= $match . ' ';
                }
            }
            
            // Bereinige Text
            $text = preg_replace('/[^\x20-\x7E\x0A\x0D\xC0-\xFF]/u', '', $text);
            return $text;
        }
        
        return '';
    }
    
    /**
     * Prüft ob Kommandozeilen-Tool verfügbar ist
     */
    private function command_exists($command) {
        $return = shell_exec(sprintf("which %s", escapeshellarg($command)));
        return !empty($return);
    }
}
