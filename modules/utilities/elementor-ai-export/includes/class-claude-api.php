<?php
/**
 * Claude API Integration
 * Connects to Anthropic Claude API for automated page redesign
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Claude_API {

    private $api_key;
    private $api_url = 'https://api.anthropic.com/v1/messages';
    private $model;
    private $max_tokens;

    // Verfügbare Modelle mit ihren Token-Limits (neueste zuerst)
    // WICHTIG: Modellnamen müssen exakt mit Anthropic API übereinstimmen
    private $available_models = [
        // Claude 3.5 Sonnet (korrekte API-Namen)
        'claude-3-5-sonnet-20240620' => 8192,

        // Claude 3 Opus (bestes Modell für komplexe Aufgaben)
        'claude-3-opus-20240229' => 4096,

        // Claude 3 Sonnet (ausgewogen)
        'claude-3-sonnet-20240229' => 4096,

        // Claude 3 Haiku (schnellstes, günstigstes)
        'claude-3-haiku-20240307' => 4096
    ];

    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('elementor_ai_export_claude_api_key', '');
        // Nutzer kann Modell in Settings überschreiben
        $default_model = array_key_first($this->available_models);
        $this->model = get_option('elementor_ai_export_claude_model', $default_model);
        // Set max_tokens basierend auf Modell
        $this->max_tokens = $this->available_models[$this->model] ?? 4096;
    }

    /**
     * Check if API is configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Redesign a page automatically using Claude API
     *
     * @param array $page_data Exported page data (metadata + structure)
     * @param string $prompt User's redesign instructions
     * @return array|WP_Error Modified page data or error
     */
    public function redesign_page($page_data, $prompt) {
        dgptm_log_verbose("redesign_page aufgerufen", 'elementor-ai-export');

        if (!$this->is_configured()) {
            dgptm_log_warning("Nicht konfiguriert!", 'elementor-ai-export');
            return new WP_Error('no_api_key', 'Claude API Key ist nicht konfiguriert. Bitte in den Einstellungen hinterlegen.');
        }

        dgptm_log_verbose("Konfiguriert, baue Prompts", 'elementor-ai-export');

        // Build system prompt (instructions for Claude)
        $system_prompt = $this->get_system_prompt();

        // Build user message with page data
        $user_message = $this->build_user_message($page_data, $prompt);

        dgptm_log_verbose("Prompts erstellt, sende Request an API", 'elementor-ai-export');

        // Call Claude API
        $response = $this->call_api($system_prompt, $user_message);

        dgptm_log_verbose("Response erhalten", 'elementor-ai-export');

        if (is_wp_error($response)) {
            return $response;
        }

        // Extract JSON from Claude's response
        $modified_data = $this->extract_json_from_response($response);

        if (is_wp_error($modified_data)) {
            return $modified_data;
        }

        return $modified_data;
    }

    /**
     * Get system prompt for Claude
     */
    private function get_system_prompt() {
        return 'Du bist ein Experte für Elementor-Seitengestaltung. Deine Aufgabe ist es, Elementor-Seiten basierend auf Nutzeranweisungen umzugestalten.

KRITISCHE REGELN:

1. Du erhältst ein JSON-Dokument mit dieser Struktur:
   {
     "metadata": { ... },
     "structure": [ ... ]
   }

2. Du MUSST das EXAKT gleiche JSON-Format zurückgeben mit:
   - Dem kompletten "metadata" Objekt (meist unverändert)
   - Dem kompletten "structure" Array

3. Jedes Element in "structure" hat diese Felder:
   - "id": Element-ID (NIEMALS ändern!)
   - "type": Element-Typ wie "section", "column", "widget" (NIEMALS ändern!)
   - "level": Hierarchie-Ebene (NIEMALS ändern!)
   - "widget": Widget-Typ bei Widgets (NIEMALS ändern!)
   - "settings": Sichtbare Einstellungen → HIER machst du Änderungen
   - "_elementor_settings": KOMPLETTE Original-Einstellungen (NIEMALS ändern!)
   - "children": Array der Kinder-Elemente

4. Was du ändern darfst (nur in "settings"):
   - Text-Inhalte: title, heading, text, content, editor, description
   - Bilder: image, background_image (URLs)
   - Links: link, url, button_text
   - Farben: color, background_color, text_color
   - Typografie: typography_font_family, typography_font_size

5. Was du NIEMALS ändern darfst:
   - Element-IDs (id)
   - Element-Typen (type, widget)
   - Hierarchie (level)
   - Das gesamte Feld "_elementor_settings" (enthält Dynamic Visibility!)
   - Die Grundstruktur oder Verschachtelung

6. AUSGABEFORMAT:
   - Gib AUSSCHLIESSLICH valides JSON zurück
   - KEIN Markdown-Code-Block (```json ... ```)
   - KEIN erklärender Text davor oder danach
   - NUR das pure JSON-Objekt, das mit { beginnt und mit } endet

7. Bei strukturellen Änderungen:
   - Du kannst neue Elemente HINZUFÜGEN (mit neuen, einzigartigen IDs)
   - Du kannst Elemente ENTFERNEN (ganze Element-Objekte weglassen)
   - Du kannst die REIHENFOLGE ändern
   - Achte darauf, dass "level" korrekt bleibt

BEISPIEL:
Eingabe-Prompt: "Ändere die Überschrift zu \'Willkommen\'"
Du findest das Element mit "heading" in settings und änderst NUR diesen Wert.
Alle anderen Felder bleiben identisch.

Antworte IMMER mit validem JSON, niemals mit Text-Erklärungen!';
    }

    /**
     * Build user message with page data and prompt
     */
    private function build_user_message($page_data, $prompt) {
        $json = json_encode($page_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return "Hier ist eine Elementor-Seite als JSON-Export:\n\n" . $json . "\n\n" .
               "AUFGABE: " . $prompt . "\n\n" .
               "Gib mir das vollständige, bearbeitete JSON zurück. Nur JSON, kein Text!";
    }

    /**
     * Call Claude API with retry logic for rate limits
     */
    private function call_api($system_prompt, $user_message, $retry_count = 0) {
        $body = [
            'model' => $this->model,
            'max_tokens' => $this->max_tokens,
            'system' => $system_prompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $user_message
                ]
            ]
        ];

        // Timeout muss lang genug sein für große Seiten UND mögliche Retries
        // Wir machen den Request selbst, keine Retries hier - Retries nur bei 429
        $timeout = ($retry_count === 0) ? 120 : 30; // Erster Versuch: 2min, Retries: 30s

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ],
            'body' => json_encode($body),
            'timeout' => $timeout,
            'method' => 'POST'
        ];

        $response = wp_remote_request($this->api_url, $args);

        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', 'Claude API Anfrage fehlgeschlagen: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? 'Unbekannter API-Fehler';
            $error_type = $error_data['error']['type'] ?? '';

            // Rate Limit Error - Informiere Benutzer sofort, kein automatisches Retry
            if ($status_code === 429 || $error_type === 'rate_limit_error') {
                dgptm_log_warning("Rate Limit erreicht bei Versuch " . ($retry_count + 1), 'elementor-ai-export');

                return new WP_Error('rate_limit_error',
                    '⏱️ Rate Limit erreicht - Ihre API skaliert gerade hoch!\n\n' .
                    'Anthropic erhöht Ihr Token-Limit gerade von 50.000 auf 120.000 pro Minute. ' .
                    'Das ist beim ersten Request nach längerer Pause normal.\n\n' .
                    '✅ LÖSUNG: Warten Sie einfach 60 Sekunden und klicken Sie dann erneut auf "Automatisch umgestalten".\n\n' .
                    'Beim zweiten Versuch wird es funktionieren, da Ihr Limit dann erhöht ist!'
                );
            }

            return new WP_Error('api_error', 'Claude API Fehler (' . $status_code . '): ' . $error_message);
        }

        $data = json_decode($response_body, true);

        if (!isset($data['content'][0]['text'])) {
            return new WP_Error('invalid_response', 'Ungültige API-Antwort von Claude');
        }

        return $data['content'][0]['text'];
    }

    /**
     * Extract JSON from Claude's response
     */
    private function extract_json_from_response($response) {
        // Remove markdown code blocks if present
        $json_string = preg_replace('/```json\s*/', '', $response);
        $json_string = preg_replace('/```\s*$/', '', $json_string);
        $json_string = trim($json_string);

        // Try to decode
        $data = json_decode($json_string, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to find JSON in the response
            if (preg_match('/\{[\s\S]*"metadata"[\s\S]*"structure"[\s\S]*\}/U', $response, $matches)) {
                $data = json_decode($matches[0], true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error(
                    'invalid_json',
                    'Claude hat kein gültiges JSON zurückgegeben. JSON-Fehler: ' . json_last_error_msg() .
                    "\n\nClaude's Antwort:\n" . substr($response, 0, 500)
                );
            }
        }

        // Validate structure
        if (!isset($data['metadata']) || !isset($data['structure'])) {
            return new WP_Error('incomplete_json', 'Claude hat unvollständiges JSON zurückgegeben (metadata oder structure fehlt)');
        }

        return $data;
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', 'Kein API Key konfiguriert');
        }

        $body = [
            'model' => $this->model,
            'max_tokens' => 100,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Antworte nur mit: "API funktioniert"'
                ]
            ]
        ];

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ],
            'body' => json_encode($body),
            'timeout' => 30,
            'method' => 'POST'
        ];

        $response = wp_remote_request($this->api_url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            return true;
        }

        $body = wp_remote_retrieve_body($response);
        $error_data = json_decode($body, true);
        $error_message = $error_data['error']['message'] ?? 'Unbekannter Fehler';
        $error_type = $error_data['error']['type'] ?? '';

        // Wenn 404 (Modell nicht gefunden), versuche andere Modelle
        if ($status_code === 404) {
            return $this->find_working_model();
        }

        // Wenn 429 (Rate Limit), ist die Verbindung trotzdem OK
        if ($status_code === 429 || $error_type === 'rate_limit_error') {
            return [
                'success' => true,
                'model' => $this->model,
                'max_tokens' => $this->max_tokens,
                'message' => "✅ API-Verbindung funktioniert! Modell: {$this->model} (Rate Limit erreicht, aber das ist normal beim Testen)"
            ];
        }

        return new WP_Error('connection_failed', 'Verbindung fehlgeschlagen: ' . $error_message);
    }

    /**
     * Find a working model by testing each one
     */
    private function find_working_model() {
        $tested_models = [];
        $model_count = 0;

        foreach ($this->available_models as $model => $max_tokens) {
            // Kurze Pause zwischen Tests (außer beim ersten), um Rate Limits zu vermeiden
            if ($model_count > 0) {
                sleep(2); // 2 Sekunden Pause zwischen Tests
            }
            $model_count++;
            $body = [
                'model' => $model,
                'max_tokens' => 100,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Test'
                    ]
                ]
            ];

            $args = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->api_key,
                    'anthropic-version' => '2023-06-01'
                ],
                'body' => json_encode($body),
                'timeout' => 30,
                'method' => 'POST'
            ];

            $response = wp_remote_request($this->api_url, $args);
            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            // Debug-Log mit vollständiger API-Antwort
            dgptm_log_verbose("Test - Modell: {$model}, Status: {$status_code}", 'elementor-ai-export');
            if ($status_code !== 200) {
                dgptm_log_verbose("Fehler-Details für {$model}: " . $response_body, 'elementor-ai-export');
            }

            if ($status_code === 200) {
                // Gefunden! Speichern für zukünftige Nutzung
                update_option('elementor_ai_export_claude_model', $model);
                $this->model = $model;
                $this->max_tokens = $max_tokens;

                dgptm_log_info("Funktionierendes Modell gefunden: {$model}", 'elementor-ai-export');

                return [
                    'success' => true,
                    'model' => $model,
                    'max_tokens' => $max_tokens,
                    'message' => "✅ Funktionierendes Modell gefunden: {$model} (max {$max_tokens} tokens)"
                ];
            } else {
                // Speichere Fehler für Debug
                $error_data = json_decode($response_body, true);
                $error_msg = $error_data['error']['message'] ?? 'Unbekannter Fehler';
                $tested_models[$model] = "Status {$status_code}: {$error_msg}";
                dgptm_log_warning("Modell {$model} fehlgeschlagen: {$error_msg}", 'elementor-ai-export');
            }
        }

        // Erstelle detaillierte Fehlermeldung
        $debug_info = "\n\nGetestete Modelle:\n";
        foreach ($tested_models as $model => $error) {
            $debug_info .= "- {$model}: {$error}\n";
        }

        return new WP_Error(
            'no_model_found',
            'Kein funktionierendes Claude-Modell gefunden. Bitte prüfen Sie Ihren API Key und Ihr Anthropic-Konto.' . $debug_info
        );
    }

    /**
     * Get available models
     */
    public function get_available_models() {
        return $this->available_models;
    }

    /**
     * Get current model
     */
    public function get_current_model() {
        return $this->model;
    }
}
