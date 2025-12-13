<?php
/**
 * API Handler für Wissens-Bot
 * Koordiniert Claude AI und externe Datenquellen
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wissens_Bot_API_Handler {
    
    private $claude_api_url = 'https://api.anthropic.com/v1/messages';
    private $api_key;
    private $max_tokens;
    private $system_prompt;
    private $topic_keywords;
    
    public function __construct() {
        $this->api_key = get_option('wissens_bot_claude_api_key');
        $this->max_tokens = get_option('wissens_bot_max_tokens', 4000);
        $this->system_prompt = get_option('wissens_bot_system_prompt');
        $this->topic_keywords = get_option('wissens_bot_topic_keywords');
    }
    
    /**
     * Verarbeitet eine Chat-Nachricht
     */
    public function process_chat_message($message, $conversation_history = array()) {
        // Validierung
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Claude API Key nicht konfiguriert.', 'wissens-bot'));
        }
        
        // Themenrelevanz prüfen
        if (!$this->is_topic_relevant($message)) {
            return array(
                'response' => $this->get_off_topic_message(),
                'sources' => array()
            );
        }
        
        // Externe Datenquellen abfragen
        $context_data = $this->gather_context_data($message);
        
        // System Prompt mit Kontext erweitern
        $enhanced_system_prompt = $this->build_system_prompt($context_data);
        
        // Messages-Array für Claude API aufbauen
        $messages = $this->build_messages_array($message, $conversation_history);
        
        // Claude API aufrufen
        $response = $this->call_claude_api($enhanced_system_prompt, $messages);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return array(
            'response' => $response['content'],
            'sources' => $context_data['sources'],
            'usage' => isset($response['usage']) ? $response['usage'] : array()
        );
    }
    
    /**
     * Prüft ob die Anfrage themenrelevant ist
     */
    private function is_topic_relevant($message) {
        if (empty($this->topic_keywords)) {
            return true; // Keine Einschränkung wenn keine Keywords definiert
        }
        
        $keywords = array_map('trim', explode(',', strtolower($this->topic_keywords)));
        $message_lower = strtolower($message);
        
        foreach ($keywords as $keyword) {
            if (stripos($message_lower, $keyword) !== false) {
                return true;
            }
        }
        
        // Erweiterte Prüfung mit verwandten Begriffen
        $related_terms = array(
            'perfusion', 'kardiotechnik', 'herz', 'herzchirurgie',
            'extrakorporal', 'bypass', 'oxygenator', 'kardiopleg'
        );
        
        foreach ($related_terms as $term) {
            if (stripos($message_lower, $term) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sammelt Kontextdaten aus verschiedenen Quellen
     */
    private function gather_context_data($message) {
        $context_data = array(
            'documents' => array(),
            'sources' => array()
        );
        
        // SharePoint durchsuchen
        if (get_option('wissens_bot_sharepoint_enabled')) {
            $sharepoint = new Wissens_Bot_SharePoint_Connector();
            $sharepoint_results = $sharepoint->search_documents($message);
            
            if (!is_wp_error($sharepoint_results) && !empty($sharepoint_results)) {
                $context_data['documents'] = array_merge($context_data['documents'], $sharepoint_results);
                $context_data['sources'][] = array(
                    'type' => 'sharepoint',
                    'count' => count($sharepoint_results)
                );
            }
        }
        
        // PubMed durchsuchen
        if (get_option('wissens_bot_pubmed_enabled')) {
            $pubmed = new Wissens_Bot_PubMed_Connector();
            $pubmed_results = $pubmed->search($message, 5);
            
            if (!is_wp_error($pubmed_results) && !empty($pubmed_results)) {
                $context_data['documents'] = array_merge($context_data['documents'], $pubmed_results);
                $context_data['sources'][] = array(
                    'type' => 'pubmed',
                    'count' => count($pubmed_results),
                    'articles' => $pubmed_results
                );
            }
        }
        
        // Google Scholar durchsuchen
        if (get_option('wissens_bot_scholar_enabled')) {
            $scholar = new Wissens_Bot_Scholar_Connector();
            $scholar_results = $scholar->search($message, 5);
            
            if (!is_wp_error($scholar_results) && !empty($scholar_results)) {
                $context_data['documents'] = array_merge($context_data['documents'], $scholar_results);
                $context_data['sources'][] = array(
                    'type' => 'scholar',
                    'count' => count($scholar_results),
                    'articles' => $scholar_results
                );
            }
        }
        
        return $context_data;
    }
    
    /**
     * Erstellt erweiterten System Prompt mit Kontext
     */
    private function build_system_prompt($context_data) {
        $prompt = $this->system_prompt . "\n\n";
        $prompt .= "Erlaubte Themenbereiche: " . $this->topic_keywords . "\n\n";
        
        if (!empty($context_data['documents'])) {
            $prompt .= "VERFÜGBARE WISSENSDATENBANK:\n\n";
            
            foreach ($context_data['documents'] as $index => $doc) {
                $prompt .= "---\n";
                $prompt .= "Dokument " . ($index + 1) . ":\n";
                $prompt .= "Quelle: " . ($doc['source'] ?? 'Unbekannt') . "\n";
                
                if (isset($doc['title'])) {
                    $prompt .= "Titel: " . $doc['title'] . "\n";
                }
                
                if (isset($doc['authors'])) {
                    $prompt .= "Autoren: " . $doc['authors'] . "\n";
                }
                
                if (isset($doc['date'])) {
                    $prompt .= "Datum: " . $doc['date'] . "\n";
                }
                
                if (isset($doc['abstract'])) {
                    $prompt .= "Abstract: " . $doc['abstract'] . "\n";
                }
                
                if (isset($doc['content'])) {
                    $prompt .= "Inhalt: " . substr($doc['content'], 0, 2000) . "...\n";
                }
                
                if (isset($doc['url'])) {
                    $prompt .= "URL: " . $doc['url'] . "\n";
                }
                
                $prompt .= "---\n\n";
            }
            
            $prompt .= "\nNutze diese Dokumente als Wissensgrundlage für deine Antwort. ";
            $prompt .= "Zitiere relevante Quellen und gib an, aus welchem Dokument die Information stammt.\n\n";
        }
        
        return $prompt;
    }
    
    /**
     * Baut Messages-Array für Claude API
     */
    private function build_messages_array($current_message, $history) {
        $messages = array();
        
        // Conversation History hinzufügen
        if (!empty($history)) {
            foreach ($history as $msg) {
                $messages[] = array(
                    'role' => $msg['role'],
                    'content' => $msg['content']
                );
            }
        }
        
        // Aktuelle Nachricht hinzufügen
        $messages[] = array(
            'role' => 'user',
            'content' => $current_message
        );
        
        return $messages;
    }
    
    /**
     * Ruft Claude API auf
     */
    private function call_claude_api($system_prompt, $messages) {
        $body = array(
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => intval($this->max_tokens),
            'system' => $system_prompt,
            'messages' => $messages
        );
        
        $response = wp_remote_post($this->claude_api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode($body),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code !== 200) {
            $error_message = isset($data['error']['message']) 
                ? $data['error']['message'] 
                : __('API Fehler', 'wissens-bot');
            
            return new WP_Error('api_error', $error_message);
        }
        
        // Extrahiere Text-Content aus der Antwort
        $content = '';
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
        }
        
        return array(
            'content' => $content,
            'usage' => isset($data['usage']) ? $data['usage'] : array()
        );
    }
    
    /**
     * Gibt Nachricht zurück wenn Thema nicht relevant ist
     */
    private function get_off_topic_message() {
        return sprintf(
            __('Entschuldigung, ich kann nur Fragen zu folgenden Themenbereichen beantworten: %s. Bitte stellen Sie eine Frage zu einem dieser Bereiche.', 'wissens-bot'),
            $this->topic_keywords
        );
    }
}
