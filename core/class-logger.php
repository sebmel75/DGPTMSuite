<?php
/**
 * DGPTM Suite Logger
 * Zentrale Logging-Funktionalität mit Hybrid-System (Datenbank + File)
 *
 * Features:
 * - Datenbank-Logging mit strukturierten Daten
 * - File-Logging als Backup/Fallback
 * - Per-Modul Debug-Level Konfiguration
 * - Rückwärtskompatibilität mit bestehenden Aufrufen
 *
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Logger {

    private static $instance = null;

    /**
     * Log-Level Hierarchie (niedrigerer Wert = mehr Output)
     */
    const LEVELS = [
        'verbose'  => 0,
        'info'     => 1,
        'warning'  => 2,
        'error'    => 3,
        'critical' => 4
    ];

    /**
     * Singleton Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private Konstruktor für Singleton
     */
    private function __construct() {
        // Stündliche Cleanup-Routine
        if (!wp_next_scheduled('dgptm_logs_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'dgptm_logs_cleanup');
        }
        add_action('dgptm_logs_cleanup', [$this, 'scheduled_cleanup']);
    }

    /**
     * Holt die Logging-Einstellungen
     */
    private static function get_logging_settings() {
        $settings = get_option('dgptm_suite_settings', []);

        return [
            'global_level' => isset($settings['logging']['global_level']) ? $settings['logging']['global_level'] : 'warning',
            'db_enabled' => isset($settings['logging']['db_enabled']) ? (bool) $settings['logging']['db_enabled'] : true,
            'file_enabled' => isset($settings['logging']['file_enabled']) ? (bool) $settings['logging']['file_enabled'] : true,
            'max_db_entries' => isset($settings['logging']['max_db_entries']) ? (int) $settings['logging']['max_db_entries'] : 100000,
            'module_levels' => isset($settings['logging']['module_levels']) ? $settings['logging']['module_levels'] : [],
            // Legacy-Settings für Rückwärtskompatibilität
            'enable_logging' => isset($settings['enable_logging']) ? (bool) $settings['enable_logging'] : false,
            'enable_verbose_logging' => isset($settings['enable_verbose_logging']) ? (bool) $settings['enable_verbose_logging'] : false,
            'log_cleanup_age' => isset($settings['log_cleanup_age']) ? (int) $settings['log_cleanup_age'] : 24
        ];
    }

    /**
     * Holt das effektive Log-Level für ein Modul
     *
     * @param string|null $module_id Modul-ID
     * @return string Log-Level
     */
    public static function get_effective_level($module_id = null) {
        $settings = self::get_logging_settings();

        // Prüfe Modul-spezifisches Level
        if ($module_id && isset($settings['module_levels'][$module_id])) {
            $module_level = $settings['module_levels'][$module_id];
            if ($module_level !== 'global' && isset(self::LEVELS[$module_level])) {
                return $module_level;
            }
        }

        // Legacy-Kompatibilität: Wenn alte Settings aktiv sind
        if ($settings['enable_verbose_logging']) {
            return 'verbose';
        }
        if ($settings['enable_logging']) {
            return 'info';
        }

        return $settings['global_level'];
    }

    /**
     * Prüft ob ein bestimmtes Level geloggt werden soll
     *
     * @param string $level Das zu prüfende Level
     * @param string|null $module_id Modul-ID
     * @return bool
     */
    public static function should_log($level, $module_id = null) {
        // Critical und Error werden IMMER geloggt
        if ($level === 'critical' || $level === 'error') {
            return true;
        }

        $effective_level = self::get_effective_level($module_id);
        $level_value = isset(self::LEVELS[$level]) ? self::LEVELS[$level] : 1;
        $effective_value = isset(self::LEVELS[$effective_level]) ? self::LEVELS[$effective_level] : 2;

        return $level_value >= $effective_value;
    }

    /**
     * Prüft ob Info-Level Logging aktiviert ist (Legacy)
     */
    public static function is_enabled() {
        $settings = self::get_logging_settings();
        return $settings['enable_logging'] || self::LEVELS[self::get_effective_level()] <= self::LEVELS['info'];
    }

    /**
     * Prüft ob detailliertes Verbose-Logging aktiviert ist (Legacy)
     */
    public static function is_verbose_enabled() {
        $settings = self::get_logging_settings();
        return $settings['enable_verbose_logging'] || self::get_effective_level() === 'verbose';
    }

    /**
     * Hauptmethode für Logging
     *
     * @param string $message Die Log-Nachricht
     * @param string $level Log-Level: 'verbose', 'info', 'warning', 'error', 'critical'
     * @param string|null $module_id Modul-Identifikation (optional)
     * @param array|null $context Zusätzliche Kontextdaten (optional)
     * @param bool $force Logging erzwingen (ignoriert Level-Einstellung)
     */
    public static function log($message, $level = 'info', $module_id = null, $context = null, $force = false) {
        // Normalisiere Level
        $level = strtolower($level);
        if (!isset(self::LEVELS[$level])) {
            $level = 'info';
        }

        // Prüfe ob geloggt werden soll
        if (!$force && !self::should_log($level, $module_id)) {
            return;
        }

        $settings = self::get_logging_settings();

        // Erstelle Log-Entry
        $entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'module_id' => $module_id,
            'message' => $message,
            'context' => $context ? wp_json_encode($context) : null,
            'user_id' => get_current_user_id() ?: null,
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? substr(sanitize_text_field($_SERVER['REQUEST_URI']), 0, 512) : null,
            'ip_address' => self::get_client_ip()
        ];

        // In Datenbank schreiben
        if ($settings['db_enabled']) {
            self::write_to_db($entry);
        }

        // In File schreiben (immer als Backup bei critical/error, sonst nach Setting)
        if ($settings['file_enabled'] || $level === 'critical' || $level === 'error') {
            self::write_to_file($entry);
        }
    }

    /**
     * Schreibt Log-Entry in die Datenbank
     */
    private static function write_to_db($entry) {
        global $wpdb;

        // Prüfe ob Installer-Klasse geladen ist
        if (!class_exists('DGPTM_Logger_Installer')) {
            return false;
        }

        if (!DGPTM_Logger_Installer::table_exists()) {
            return false;
        }

        $table_name = DGPTM_Logger_Installer::get_table_name();

        $result = $wpdb->insert(
            $table_name,
            [
                'timestamp' => $entry['timestamp'],
                'level' => $entry['level'],
                'module_id' => $entry['module_id'],
                'message' => $entry['message'],
                'context' => $entry['context'],
                'user_id' => $entry['user_id'],
                'request_uri' => $entry['request_uri'],
                'ip_address' => $entry['ip_address']
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Schreibt Log-Entry in die Datei (error_log)
     */
    private static function write_to_file($entry) {
        // Präfix basierend auf Level
        $prefix_map = [
            'verbose'  => 'DGPTM Suite [VERBOSE]',
            'info'     => 'DGPTM Suite',
            'warning'  => 'DGPTM Suite WARNING',
            'error'    => 'DGPTM Suite ERROR',
            'critical' => 'DGPTM Suite KRITISCH'
        ];

        $prefix = isset($prefix_map[$entry['level']]) ? $prefix_map[$entry['level']] : 'DGPTM Suite';

        // Modul-ID hinzufügen wenn vorhanden
        if (!empty($entry['module_id'])) {
            $prefix .= ' [' . $entry['module_id'] . ']';
        }

        $log_message = $prefix . ': ' . $entry['message'];

        // Context als JSON anhängen bei verbose
        if (!empty($entry['context']) && ($entry['level'] === 'verbose' || $entry['level'] === 'error' || $entry['level'] === 'critical')) {
            $log_message .= ' | Context: ' . $entry['context'];
        }

        error_log($log_message);
    }

    /**
     * Verbose Log (nur wenn verbose_logging aktiviert)
     */
    public static function verbose($message, $module_id = null, $context = null) {
        self::log($message, 'verbose', $module_id, $context);
    }

    /**
     * Info Log
     */
    public static function info($message, $module_id = null, $context = null) {
        self::log($message, 'info', $module_id, $context);
    }

    /**
     * Warning Log (wird immer geloggt wenn >= warning level)
     */
    public static function warning($message, $module_id = null, $context = null) {
        self::log($message, 'warning', $module_id, $context);
    }

    /**
     * Error Log (wird immer geloggt)
     */
    public static function error($message, $module_id = null, $context = null) {
        self::log($message, 'error', $module_id, $context, true);
    }

    /**
     * Critical Log (wird immer geloggt)
     */
    public static function critical($message, $module_id = null, $context = null) {
        self::log($message, 'critical', $module_id, $context, true);
    }

    /**
     * Setzt das Debug-Level für ein Modul
     *
     * @param string $module_id Modul-ID
     * @param string $level Log-Level oder 'global'
     */
    public static function set_module_level($module_id, $level) {
        $settings = get_option('dgptm_suite_settings', []);

        if (!isset($settings['logging'])) {
            $settings['logging'] = [];
        }
        if (!isset($settings['logging']['module_levels'])) {
            $settings['logging']['module_levels'] = [];
        }

        if ($level === 'global') {
            unset($settings['logging']['module_levels'][$module_id]);
        } else {
            $settings['logging']['module_levels'][$module_id] = $level;
        }

        update_option('dgptm_suite_settings', $settings);
    }

    /**
     * Holt das Debug-Level für ein Modul
     *
     * @param string $module_id Modul-ID
     * @return string Level oder 'global'
     */
    public static function get_module_level($module_id) {
        $settings = self::get_logging_settings();

        if (isset($settings['module_levels'][$module_id])) {
            return $settings['module_levels'][$module_id];
        }

        return 'global';
    }

    /**
     * Abfrage von Logs aus der Datenbank
     *
     * @param array $filters Filter-Optionen
     * @return array
     */
    public static function query_logs($filters = []) {
        global $wpdb;

        if (!class_exists('DGPTM_Logger_Installer') || !DGPTM_Logger_Installer::table_exists()) {
            return ['logs' => [], 'total' => 0];
        }

        $table_name = DGPTM_Logger_Installer::get_table_name();

        $defaults = [
            'level' => null,
            'module_id' => null,
            'search' => null,
            'date_from' => null,
            'date_to' => null,
            'user_id' => null,
            'per_page' => 50,
            'page' => 1,
            'orderby' => 'timestamp',
            'order' => 'DESC'
        ];

        $filters = wp_parse_args($filters, $defaults);

        // WHERE-Bedingungen aufbauen
        $where = ['1=1'];
        $values = [];

        if (!empty($filters['level'])) {
            if (is_array($filters['level'])) {
                $placeholders = implode(',', array_fill(0, count($filters['level']), '%s'));
                $where[] = "level IN ($placeholders)";
                $values = array_merge($values, $filters['level']);
            } else {
                $where[] = 'level = %s';
                $values[] = $filters['level'];
            }
        }

        if (!empty($filters['module_id'])) {
            $where[] = 'module_id = %s';
            $values[] = $filters['module_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = 'message LIKE %s';
            $values[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'timestamp >= %s';
            $values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'timestamp <= %s';
            $values[] = $filters['date_to'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = $filters['user_id'];
        }

        $where_sql = implode(' AND ', $where);

        // Gesamtanzahl
        $count_sql = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        if (!empty($values)) {
            $count_sql = $wpdb->prepare($count_sql, $values);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Sortierung
        $orderby = in_array($filters['orderby'], ['id', 'timestamp', 'level', 'module_id']) ? $filters['orderby'] : 'timestamp';
        $order = strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Pagination
        $per_page = max(1, min(500, (int) $filters['per_page']));
        $page = max(1, (int) $filters['page']);
        $offset = ($page - 1) * $per_page;

        // Logs abrufen
        $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $logs = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        // Context JSON dekodieren
        foreach ($logs as &$log) {
            if (!empty($log['context'])) {
                $log['context'] = json_decode($log['context'], true);
            }
        }

        return [
            'logs' => $logs ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }

    /**
     * Holt alle Module die Logs haben
     */
    public static function get_logged_modules() {
        global $wpdb;

        if (!class_exists('DGPTM_Logger_Installer') || !DGPTM_Logger_Installer::table_exists()) {
            return [];
        }

        $table_name = DGPTM_Logger_Installer::get_table_name();

        return $wpdb->get_col(
            "SELECT DISTINCT module_id FROM $table_name WHERE module_id IS NOT NULL ORDER BY module_id"
        );
    }

    /**
     * Geplante Cleanup-Routine
     */
    public function scheduled_cleanup() {
        if (class_exists('DGPTM_Logger_Installer')) {
            DGPTM_Logger_Installer::cleanup_old_logs();

            $settings = self::get_logging_settings();
            DGPTM_Logger_Installer::cleanup_by_count($settings['max_db_entries']);
        }
    }

    /**
     * Holt die Client-IP-Adresse
     */
    private static function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}

/**
 * Globale Hilfsfunktionen für einfache Verwendung in Modulen
 */

/**
 * Einfache Log-Funktion (rückwärtskompatibel + erweitert)
 *
 * @param string $message Die Log-Nachricht
 * @param string $level Log-Level: 'info', 'warning', 'error', 'critical', 'verbose'
 * @param string|null $module_id Modul-ID (optional)
 * @param array|null $context Zusätzlicher Kontext (optional)
 */
function dgptm_log($message, $level = 'info', $module_id = null, $context = null) {
    // Rückwärtskompatibilität: alte Aufrufe hatten nur 2 Parameter
    if (is_string($module_id) && in_array($module_id, ['info', 'warning', 'error', 'critical', 'verbose'])) {
        // Altes Format: dgptm_log($message, $level) - module_id war eigentlich level
        $level = $module_id;
        $module_id = null;
        $context = null;
    }

    switch ($level) {
        case 'verbose':
            DGPTM_Logger::verbose($message, $module_id, $context);
            break;
        case 'critical':
            DGPTM_Logger::critical($message, $module_id, $context);
            break;
        case 'error':
            DGPTM_Logger::error($message, $module_id, $context);
            break;
        case 'warning':
            DGPTM_Logger::warning($message, $module_id, $context);
            break;
        case 'info':
        default:
            DGPTM_Logger::info($message, $module_id, $context);
            break;
    }
}

/**
 * Convenience-Funktionen für Module
 */
function dgptm_log_verbose($message, $module_id = null, $context = null) {
    DGPTM_Logger::verbose($message, $module_id, $context);
}

function dgptm_log_info($message, $module_id = null, $context = null) {
    DGPTM_Logger::info($message, $module_id, $context);
}

function dgptm_log_warning($message, $module_id = null, $context = null) {
    DGPTM_Logger::warning($message, $module_id, $context);
}

function dgptm_log_error($message, $module_id = null, $context = null) {
    DGPTM_Logger::error($message, $module_id, $context);
}

function dgptm_log_critical($message, $module_id = null, $context = null) {
    DGPTM_Logger::critical($message, $module_id, $context);
}

/**
 * Prüfe ob Logging aktiviert ist (Legacy)
 */
function dgptm_is_logging_enabled() {
    return DGPTM_Logger::is_enabled();
}

/**
 * Prüfe ob Verbose Logging aktiviert ist (Legacy)
 */
function dgptm_is_verbose_logging_enabled() {
    return DGPTM_Logger::is_verbose_enabled();
}

/**
 * Setze Modul-spezifisches Debug-Level
 *
 * @param string $module_id Modul-ID
 * @param string $level Log-Level oder 'global'
 */
function dgptm_set_module_log_level($module_id, $level) {
    DGPTM_Logger::set_module_level($module_id, $level);
}

/**
 * Hole Modul-spezifisches Debug-Level
 *
 * @param string $module_id Modul-ID
 * @return string
 */
function dgptm_get_module_log_level($module_id) {
    return DGPTM_Logger::get_module_level($module_id);
}
