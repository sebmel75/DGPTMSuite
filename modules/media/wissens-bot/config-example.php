<?php
/**
 * Wissens-Bot - Beispiel-Konfiguration
 * 
 * Fügen Sie diese Zeilen zu Ihrer wp-config.php hinzu, um zusätzliche Optionen zu konfigurieren.
 * Diese Datei sollte NICHT direkt verwendet werden.
 */

// ===== CLAUDE API KONFIGURATION =====

// Alternativ: API Key über Environment Variable setzen (empfohlen für Produktion)
// define('WISSENS_BOT_CLAUDE_API_KEY', getenv('CLAUDE_API_KEY'));

// ===== SERPAPI FÜR GOOGLE SCHOLAR (OPTIONAL) =====

// Für bessere Google Scholar Ergebnisse
// Registrierung: https://serpapi.com
// define('WISSENS_BOT_SERPAPI_KEY', 'ihr-serpapi-key');

// ===== PERFORMANCE & CACHING =====

// SharePoint Token-Cache Dauer (in Sekunden)
// define('WISSENS_BOT_CACHE_DURATION', 3600); // Standard: 3600 (1 Stunde)

// Maximale Anzahl von Dokumenten aus SharePoint pro Anfrage
// define('WISSENS_BOT_MAX_DOCUMENTS', 5); // Standard: 5

// ===== RATE LIMITING =====

// Maximale Anzahl Anfragen pro Benutzer pro Stunde
// define('WISSENS_BOT_RATE_LIMIT', 50); // Standard: nicht begrenzt

// Rate Limit für nicht-eingeloggte Benutzer
// define('WISSENS_BOT_GUEST_RATE_LIMIT', 10);

// ===== ENTWICKLUNG & DEBUGGING =====

// Aktiviert ausführliches Logging
// define('WISSENS_BOT_DEBUG', true);

// Speichert alle API-Anfragen und Antworten
// define('WISSENS_BOT_LOG_API_CALLS', true);

// Test-Modus: Keine echten API-Aufrufe (für Entwicklung)
// define('WISSENS_BOT_TEST_MODE', true);

// ===== SICHERHEIT =====

// Beschränkt Plugin auf bestimmte User Roles
// define('WISSENS_BOT_ALLOWED_ROLES', ['administrator', 'editor']);

// Maximale Nachrichtenlänge (Zeichen)
// define('WISSENS_BOT_MAX_MESSAGE_LENGTH', 1000);

// Blockiert verdächtige Keywords in Anfragen
// define('WISSENS_BOT_BLOCKED_KEYWORDS', ['malicious', 'exploit']);

// ===== ERWEITERTE OPTIONEN =====

// Verwendet andere Claude-Modelle
// Verfügbare Modelle: 'claude-sonnet-4-20250514', 'claude-opus-4-20250514'
// define('WISSENS_BOT_MODEL', 'claude-sonnet-4-20250514');

// Temperatur für Claude-Antworten (0.0 - 1.0)
// Höhere Werte = kreativer, niedrigere Werte = präziser
// define('WISSENS_BOT_TEMPERATURE', 0.7);

// Custom User Agent für externe API-Aufrufe
// define('WISSENS_BOT_USER_AGENT', 'Wissens-Bot/1.0 (your-organization.com)');

// ===== SHAREPOINT ERWEITERT =====

// SharePoint-Region (für Multi-Geo Tenants)
// define('WISSENS_BOT_SHAREPOINT_REGION', 'EUR'); // EUR, NAM, APC

// Maximale PDF-Größe zum Verarbeiten (in MB)
// define('WISSENS_BOT_MAX_PDF_SIZE', 10);

// ===== PUBMED ERWEITERT =====

// Email für NCBI E-utilities (empfohlen für bessere Rate Limits)
// define('WISSENS_BOT_NCBI_EMAIL', 'ihre-email@example.com');

// NCBI API Key (optional, für höhere Rate Limits)
// define('WISSENS_BOT_NCBI_API_KEY', 'ihr-ncbi-api-key');

// ===== CUSTOM HOOKS =====

// Diese Konstanten können Sie verwenden, um das Verhalten über Hooks zu steuern

/*
// Beispiel: Custom Logging
add_action('wissens_bot_after_response', function($message, $response) {
    if (defined('WISSENS_BOT_LOG_API_CALLS') && WISSENS_BOT_LOG_API_CALLS) {
        error_log('Wissens-Bot Query: ' . $message);
        error_log('Wissens-Bot Response: ' . substr($response, 0, 200) . '...');
    }
}, 10, 2);

// Beispiel: Custom Rate Limiting
add_filter('wissens_bot_check_rate_limit', function($allowed, $user_id) {
    if (defined('WISSENS_BOT_RATE_LIMIT')) {
        $count = get_transient('wissens_bot_count_' . $user_id) ?: 0;
        return $count < WISSENS_BOT_RATE_LIMIT;
    }
    return $allowed;
}, 10, 2);
*/

// ===== BEISPIEL: VOLLSTÄNDIGE PRODUKTIONS-KONFIGURATION =====

/*
// Sichere Konfiguration für Produktionsumgebung

// API Keys aus Environment Variables
define('WISSENS_BOT_CLAUDE_API_KEY', getenv('CLAUDE_API_KEY'));
define('WISSENS_BOT_SERPAPI_KEY', getenv('SERPAPI_KEY'));

// Performance
define('WISSENS_BOT_CACHE_DURATION', 7200); // 2 Stunden
define('WISSENS_BOT_MAX_DOCUMENTS', 3);

// Rate Limiting
define('WISSENS_BOT_RATE_LIMIT', 30);
define('WISSENS_BOT_GUEST_RATE_LIMIT', 5);

// Sicherheit
define('WISSENS_BOT_ALLOWED_ROLES', ['administrator', 'editor', 'author']);
define('WISSENS_BOT_MAX_MESSAGE_LENGTH', 500);

// Debugging aus in Produktion
define('WISSENS_BOT_DEBUG', false);
define('WISSENS_BOT_LOG_API_CALLS', false);

// Email für NCBI
define('WISSENS_BOT_NCBI_EMAIL', 'support@ihre-domain.de');
*/
