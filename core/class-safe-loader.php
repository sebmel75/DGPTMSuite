<?php
/**
 * Safe Loader für DGPTM Plugin Suite
 * Lädt Module sicher mit Fehlerabfang und Rollback
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Safe_Loader {

    private static $instance = null;
    private $error_log = [];
    private $loaded_files = [];

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
     * Modul sicher laden mit Fehlerabfang
     *
     * @param string $module_id Module ID
     * @param string $file_path Pfad zur Hauptdatei
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function safe_load_module($module_id, $file_path) {
        dgptm_log("Safe Loader: Starte sicheres Laden von '$module_id' aus $file_path", 'verbose');

        // Pre-load checks
        if (!file_exists($file_path)) {
            dgptm_log("Safe Loader: FEHLER - Datei nicht gefunden: $file_path", 'error');
            return [
                'success' => false,
                'error' => sprintf(__('File not found: %s', 'dgptm-suite'), $file_path),
                'error_type' => 'file_not_found'
            ];
        }

        // Syntax-Check vor dem Laden
        $syntax_check = $this->check_php_syntax($file_path);
        if (!$syntax_check['valid']) {
            dgptm_log("Safe Loader: FEHLER - Syntax Error in '$module_id': " . $syntax_check['error'], 'error');
            return [
                'success' => false,
                'error' => sprintf(__('PHP Syntax Error: %s', 'dgptm-suite'), $syntax_check['error']),
                'error_type' => 'syntax_error',
                'details' => $syntax_check
            ];
        }

        dgptm_log("Safe Loader: Syntax-Check für '$module_id' erfolgreich", 'verbose');

        // ISOLIERTER TEST DEAKTIVIERT
        // Grund: Der isolierte Test lädt Module ohne WordPress-Kontext, wodurch alle WordPress-Funktionen
        // (add_action, is_user_logged_in, etc.) undefined sind. Dies führt zu Fehlalarmen.
        // Stattdessen verlassen wir uns auf:
        // 1. Syntax-Check (funktioniert korrekt)
        // 2. Try-Catch beim eigentlichen Laden (funktioniert korrekt)
        // 3. Shutdown-Handler für fatale Fehler (funktioniert korrekt)
        dgptm_log("Safe Loader: Isolierter Test übersprungen für '$module_id' (deaktiviert)", 'verbose');

        // Error Handler temporär überschreiben
        $old_error_handler = set_error_handler([$this, 'error_handler']);
        $old_exception_handler = set_exception_handler([$this, 'exception_handler']);

        // Output Buffering für unerwartete Ausgaben
        ob_start();

        try {
            // Versuche Datei zu laden
            $this->loaded_files[$module_id] = $file_path;

            // WordPress-spezifische Fehler abfangen
            $this->setup_shutdown_handler($module_id);

            // KRITISCH: Mit @ operator um ALLE Fehler zu unterdrücken, dann manuell prüfen
            @require_once $file_path;

            // Prüfe ob kritische Fehler aufgetreten sind
            if (!empty($this->error_log[$module_id])) {
                $critical_errors = array_filter($this->error_log[$module_id], function($err) {
                    return $err['severity'] === 'critical';
                });

                if (!empty($critical_errors)) {
                    throw new Exception(
                        implode("\n", array_column($critical_errors, 'message'))
                    );
                }
            }

            // Hole eventuelle Ausgaben
            $output = ob_get_clean();

            // Restore handlers
            restore_error_handler();
            restore_exception_handler();

            // Warnung bei unerwarteter Ausgabe
            if (!empty(trim($output))) {
                $this->log_warning($module_id, 'Unexpected output during load', ['output' => $output]);
            }

            dgptm_log("Safe Loader: Modul '$module_id' erfolgreich geladen ✓", 'verbose');

            return [
                'success' => true,
                'error' => null,
                'output' => $output,
                'warnings' => $this->error_log[$module_id] ?? []
            ];

        } catch (Throwable $e) {
            // PHP 7+ Throwable fängt alles ab (Errors + Exceptions)

            @ob_end_clean();
            @restore_error_handler();
            @restore_exception_handler();

            $error_info = [
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'module_id' => $module_id
            ];

            dgptm_log("Safe Loader: KRITISCHER FEHLER beim Laden von '$module_id': " . $e->getMessage(), 'critical');
            dgptm_log("Safe Loader: Fehler in " . $e->getFile() . ":" . $e->getLine(), 'critical');

            // Log für Admin
            $this->log_critical_error($module_id, $error_info);

            // Automatisches Deaktivieren des Moduls
            $this->auto_deactivate_module($module_id, $error_info);

            return $error_info;
        }
    }

    /**
     * Isolierter Test-Load: Lädt das Modul in einem separaten Prozess
     * Falls der Test fehlschlägt, crasht nur der Test-Prozess, nicht die ganze Site
     */
    private function isolated_test_load($file_path, $module_id) {
        // Versuche include in einem geschützten Kontext
        $test_code = sprintf(
            '<?php
            error_reporting(E_ALL);
            set_error_handler(function($errno, $errstr, $errfile, $errline) {
                echo "ERROR:" . $errno . "|" . $errstr . "|" . $errfile . "|" . $errline;
                return true;
            });

            try {
                require_once %s;
                echo "SUCCESS";
            } catch (Throwable $e) {
                echo "EXCEPTION:" . $e->getMessage() . "|" . $e->getFile() . "|" . $e->getLine();
            }
            ?>',
            var_export($file_path, true)
        );

        // Schreibe Test-Code in temporäre Datei
        $temp_file = sys_get_temp_dir() . '/dgptm_test_' . md5($module_id) . '.php';
        file_put_contents($temp_file, $test_code);

        // Führe Test aus
        $output = [];
        $return_var = 0;

        // Versuche mit PHP_BINARY
        if (!empty(PHP_BINARY) && @is_executable(PHP_BINARY)) {
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($temp_file) . ' 2>&1', $output, $return_var);
        }

        // Cleanup
        @unlink($temp_file);

        $result_string = implode("\n", $output);

        // Analysiere Ergebnis
        if (strpos($result_string, 'SUCCESS') !== false) {
            return ['success' => true];
        }

        // Parse Fehler
        if (preg_match('/ERROR:(\d+)\|(.+?)\|(.+?)\|(\d+)/', $result_string, $matches)) {
            return [
                'success' => false,
                'error' => $matches[2],
                'error_type' => 'PHP Error ' . $matches[1],
                'file' => $matches[3],
                'line' => (int)$matches[4]
            ];
        }

        if (preg_match('/EXCEPTION:(.+?)\|(.+?)\|(\d+)/', $result_string, $matches)) {
            return [
                'success' => false,
                'error' => $matches[1],
                'error_type' => 'Exception',
                'file' => $matches[2],
                'line' => (int)$matches[3]
            ];
        }

        // Wenn PHP_BINARY nicht verfügbar, überspringe isolierten Test
        if (empty(PHP_BINARY) || !@is_executable(PHP_BINARY)) {
            dgptm_log("Safe Loader: PHP_BINARY nicht verfügbar, überspringe isolierten Test", 'verbose');
            return ['success' => true, 'skipped' => true];
        }

        // Unbekannter Fehler
        return [
            'success' => false,
            'error' => 'Unknown error during isolated test',
            'details' => $result_string
        ];
    }

    /**
     * PHP Syntax-Check ohne Ausführung
     */
    private function check_php_syntax($file_path) {
        // Versuche verschiedene PHP-Pfade
        $php_paths = [
            PHP_BINARY,                    // Das PHP, das gerade läuft
            '/usr/bin/php',               // Standard Linux
            '/usr/local/bin/php',         // Alternative Linux
            'C:\\php\\php.exe',           // Windows XAMPP
            'C:\\xampp\\php\\php.exe',    // Windows XAMPP alt
            'php',                         // Im PATH
        ];

        $php_cmd = null;

        // Finde verfügbares PHP
        foreach ($php_paths as $path) {
            if (!empty($path) && @is_executable($path)) {
                $php_cmd = $path;
                break;
            }
        }

        // Wenn kein PHP gefunden wurde, versuche "php" im PATH
        if ($php_cmd === null) {
            // Teste ob "php" verfügbar ist
            exec("which php 2>/dev/null", $which_output, $which_return);
            if ($which_return === 0 && !empty($which_output[0])) {
                $php_cmd = trim($which_output[0]);
            }
        }

        // Wenn immer noch kein PHP gefunden: Syntax-Check überspringen
        if ($php_cmd === null || empty($php_cmd)) {
            dgptm_log("Safe Loader: Warnung - PHP Binary nicht gefunden, überspringe Syntax-Check", 'verbose');
            return [
                'valid' => true,
                'skipped' => true,
                'reason' => 'PHP binary not found in system'
            ];
        }

        dgptm_log("Safe Loader: Verwende PHP: $php_cmd", 'verbose');

        $output = [];
        $return_var = 0;

        // PHP lint check
        exec(escapeshellarg($php_cmd) . " -l " . escapeshellarg($file_path) . " 2>&1", $output, $return_var);

        if ($return_var !== 0) {
            return [
                'valid' => false,
                'error' => implode("\n", $output)
            ];
        }

        return ['valid' => true];
    }

    /**
     * Custom Error Handler
     */
    public function error_handler($errno, $errstr, $errfile, $errline) {
        // Bestimme Modul aus Dateipfad
        $module_id = $this->get_module_from_file($errfile);

        // Wenn Fehler nicht von einem unserer Module kommt, ignorieren
        if ($module_id === 'unknown') {
            return false; // Lass PHP's Error Handler übernehmen
        }

        // Kritische Fehler
        $critical_errors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

        $error_info = [
            'type' => $this->get_error_type_name($errno),
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'severity' => in_array($errno, $critical_errors) ? 'critical' : 'warning'
        ];

        if (!isset($this->error_log[$module_id])) {
            $this->error_log[$module_id] = [];
        }

        $this->error_log[$module_id][] = $error_info;

        // Bei kritischen Fehlern Exception werfen
        if (in_array($errno, $critical_errors)) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        // Nicht-kritische Fehler loggen aber weiterlaufen lassen
        return true; // Verhindert PHP's eigenen Error Handler
    }

    /**
     * Custom Exception Handler
     */
    public function exception_handler($exception) {
        $module_id = $this->get_module_from_file($exception->getFile());

        // Wenn Exception nicht von einem unserer Module kommt, ignorieren
        if ($module_id === 'unknown') {
            return; // Lass WordPress's Exception Handler übernehmen
        }

        $this->log_critical_error($module_id, [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Shutdown Handler für Fatal Errors
     */
    private function setup_shutdown_handler($module_id) {
        $module_file_path = $this->loaded_files[$module_id] ?? '';

        register_shutdown_function(function() use ($module_id, $module_file_path) {
            $error = error_get_last();

            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {

                // WICHTIG: Prüfe ob der Fehler wirklich vom Modul kommt!
                $error_file = $error['file'] ?? '';
                $module_dir = dirname($module_file_path);

                // Nur wenn der Fehler aus dem Modul-Verzeichnis stammt
                $is_module_error = (strpos($error_file, $module_dir) !== false);

                if ($is_module_error) {
                    dgptm_log("Safe Loader: Fatal Error im Modul '$module_id' erkannt", 'critical');

                    // ZUSÄTZLICHE PRÜFUNG: Ist das Modul bereits erfolgreich geladen?
                    $loaded_modules = dgptm_suite()->get_module_loader()->get_loaded_modules();

                    if (isset($loaded_modules[$module_id])) {
                        dgptm_log("Safe Loader: SCHUTZ - Modul '$module_id' war bereits geladen, wird NICHT deaktiviert", 'warning');
                        dgptm_log("Safe Loader: Dies könnte ein externer Fehler sein der fälschlicherweise dem Modul zugeordnet wurde", 'verbose');
                    } else {
                        dgptm_log("Safe Loader: Modul war noch nicht geladen - Deaktivierung möglich", 'warning');

                        $this->log_critical_error($module_id, [
                            'type' => 'Fatal Error',
                            'message' => $error['message'],
                            'file' => $error['file'],
                            'line' => $error['line']
                        ]);

                        $this->auto_deactivate_module($module_id, $error);
                    }
                } else {
                    dgptm_log("Safe Loader: Fatal Error erkannt, aber NICHT vom Modul '$module_id' (Fehler in: $error_file)", 'verbose');
                    dgptm_log("Safe Loader: Modul-Verzeichnis: $module_dir", 'verbose');
                    // Fehler kommt von woanders (z.B. WP Rocket, Caching Plugins) - Modul NICHT deaktivieren!
                }
            }
        });
    }

    /**
     * Modul automatisch deaktivieren bei kritischem Fehler
     *
     * WICHTIG: Deaktiviert Module NUR wenn sie gerade aktiviert werden.
     * Module die bereits erfolgreich geladen sind, werden NIEMALS deaktiviert!
     * KRITISCHE Module werden NIEMALS automatisch deaktiviert!
     */
    private function auto_deactivate_module($module_id, $error_info) {
        // SCHUTZ 1: Prüfe ob Modul als kritisch markiert ist
        $metadata = DGPTM_Module_Metadata_File::get_instance();
        if ($metadata->is_module_critical($module_id)) {
            dgptm_log("Safe Loader: CRITICAL-SCHUTZ - Modul '$module_id' ist als CRITICAL markiert und wird NIEMALS deaktiviert!", 'error');
            dgptm_log("Safe Loader: ADMIN-AKTION ERFORDERLICH - Bitte Fehler manuell beheben!", 'critical');

            // Fehler loggen mit hoher Priorität
            $failed_activations = get_option('dgptm_suite_failed_activations', []);
            $failed_activations[$module_id . '_critical'] = [
                'timestamp' => time(),
                'error' => $error_info,
                'auto_deactivated' => false,
                'is_critical_module' => true,
                'protection_triggered' => true,
                'message' => 'KRITISCHES MODUL - Automatische Deaktivierung verhindert! Admin-Eingriff erforderlich.',
                'severity' => 'CRITICAL'
            ];
            update_option('dgptm_suite_failed_activations', $failed_activations);

            // Admin per E-Mail warnen
            $this->send_critical_module_error_email($module_id, $error_info);

            return; // WICHTIG: NIEMALS kritische Module deaktivieren!
        }

        // SCHUTZ 2: Prüfe ob das Modul bereits erfolgreich geladen ist
        $loaded_modules = dgptm_suite()->get_module_loader()->get_loaded_modules();

        if (isset($loaded_modules[$module_id])) {
            dgptm_log("Safe Loader: SCHUTZ AKTIV - Modul '$module_id' ist bereits geladen und wird NICHT deaktiviert!", 'warning');
            dgptm_log("Safe Loader: Fehler wird geloggt, aber Modul bleibt aktiv", 'verbose');

            // Fehler loggen aber Modul NICHT deaktivieren
            $failed_activations = get_option('dgptm_suite_failed_activations', []);
            $failed_activations[$module_id . '_warning'] = [
                'timestamp' => time(),
                'error' => $error_info,
                'auto_deactivated' => false,
                'protection_triggered' => true,
                'message' => 'Modul war bereits geladen - wurde NICHT deaktiviert'
            ];
            update_option('dgptm_suite_failed_activations', $failed_activations);

            return; // WICHTIG: Nicht deaktivieren!
        }

        // ZUSÄTZLICHER SCHUTZ: Verhindere Massen-Deaktivierung
        $settings = get_option('dgptm_suite_settings', []);
        $active_count = count(array_filter($settings['active_modules'] ?? []));

        // Wenn mehr als 50% der Module aktiv sind, prüfe ob nicht zu viele deaktiviert werden
        if ($active_count > 5) {
            $recent_deactivations = get_transient('dgptm_recent_auto_deactivations') ?: [];
            $recent_count = count(array_filter($recent_deactivations, function($time) {
                return $time > (time() - 3600); // Letzte Stunde
            }));

            // Wenn in der letzten Stunde mehr als 5 Module deaktiviert wurden, STOPP!
            if ($recent_count >= 5) {
                dgptm_log("Safe Loader: MASSEN-DEAKTIVIERUNGS-SCHUTZ - Zu viele Module wurden kürzlich deaktiviert!", 'critical');
                dgptm_log("Safe Loader: Modul '$module_id' wird NICHT automatisch deaktiviert", 'warning');

                // Admin warnen
                $admin_email = get_option('admin_email');
                wp_mail(
                    $admin_email,
                    'DGPTM Suite - Massen-Deaktivierungs-Schutz aktiviert',
                    "Warnung: Mehr als 5 Module wurden in der letzten Stunde automatisch deaktiviert.\n\n" .
                    "Modul '$module_id' sollte deaktiviert werden, wurde aber geschützt.\n\n" .
                    "Bitte überprüfen Sie die Fehler-Logs."
                );

                return; // NICHT deaktivieren!
            }

            // Deaktivierung registrieren
            $recent_deactivations[$module_id] = time();
            set_transient('dgptm_recent_auto_deactivations', $recent_deactivations, 3600);
        }

        // Nur deaktivieren wenn das Modul gerade aktiviert wird (nicht bereits geladen)
        if (isset($settings['active_modules'][$module_id])) {
            dgptm_log("Safe Loader: Deaktiviere Modul '$module_id' aufgrund von Fehler beim Laden", 'error');

            $settings['active_modules'][$module_id] = false;
            update_option('dgptm_suite_settings', $settings);

            // Fehler-Info für Admin speichern
            $failed_activations = get_option('dgptm_suite_failed_activations', []);
            $failed_activations[$module_id] = [
                'timestamp' => time(),
                'error' => $error_info,
                'auto_deactivated' => true,
                'can_retry' => true
            ];
            update_option('dgptm_suite_failed_activations', $failed_activations);

            // Admin-Notice setzen
            set_transient('dgptm_suite_activation_error_' . get_current_user_id(), [
                'module_id' => $module_id,
                'error' => $error_info
            ], 60);
        }
    }

    /**
     * Kritischen Fehler loggen
     */
    private function log_critical_error($module_id, $error_info) {
        $log_entry = sprintf(
            "[%s] DGPTM Suite - Critical Error in module '%s': %s in %s:%s",
            date('Y-m-d H:i:s'),
            $module_id,
            $error_info['message'] ?? 'Unknown error',
            $error_info['file'] ?? 'Unknown file',
            $error_info['line'] ?? '?'
        );

        error_log($log_entry);

        // Zusätzlich in WordPress-Debug-Log
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($log_entry);
        }
    }

    /**
     * Warnung loggen
     */
    private function log_warning($module_id, $message, $context = []) {
        if (!isset($this->error_log[$module_id])) {
            $this->error_log[$module_id] = [];
        }

        $this->error_log[$module_id][] = [
            'type' => 'warning',
            'message' => $message,
            'context' => $context,
            'severity' => 'warning'
        ];
    }

    /**
     * Modul-ID aus Dateipfad ermitteln
     */
    private function get_module_from_file($file_path) {
        foreach ($this->loaded_files as $module_id => $path) {
            if (strpos($file_path, dirname($path)) !== false) {
                return $module_id;
            }
        }
        return 'unknown';
    }

    /**
     * Fehlertyp-Namen
     */
    private function get_error_type_name($type) {
        $types = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return $types[$type] ?? 'UNKNOWN';
    }

    /**
     * Fehlgeschlagene Aktivierungen abrufen
     */
    public function get_failed_activations() {
        return get_option('dgptm_suite_failed_activations', []);
    }

    /**
     * Fehler für ein Modul löschen
     */
    public function clear_module_error($module_id) {
        $failed = $this->get_failed_activations();
        if (isset($failed[$module_id])) {
            unset($failed[$module_id]);
            update_option('dgptm_suite_failed_activations', $failed);
        }
    }

    /**
     * Alle Fehler löschen
     */
    public function clear_all_errors() {
        delete_option('dgptm_suite_failed_activations');
    }

    /**
     * Test-Modus: Modul laden ohne zu aktivieren
     */
    public function test_load_module($module_id, $file_path) {
        $result = $this->safe_load_module($module_id, $file_path);

        // Bei Test immer wieder deaktivieren
        $settings = get_option('dgptm_suite_settings', []);
        if (isset($settings['active_modules'][$module_id])) {
            $settings['active_modules'][$module_id] = false;
            update_option('dgptm_suite_settings', $settings);
        }

        return $result;
    }

    /**
     * Sende E-Mail-Warnung bei Fehler in kritischem Modul
     */
    private function send_critical_module_error_email($module_id, $error_info) {
        $admin_email = get_option('admin_email');

        if (empty($admin_email)) {
            return;
        }

        $site_name = get_option('blogname');
        $subject = sprintf('[%s] KRITISCHER FEHLER in DGPTM Modul: %s', $site_name, $module_id);

        $message = "KRITISCHER FEHLER in DGPTM Plugin Suite\n";
        $message .= "=========================================\n\n";
        $message .= "Ein kritisches Modul hat einen Fehler verursacht!\n\n";
        $message .= "Modul: {$module_id}\n";
        $message .= "Status: CRITICAL - Automatische Deaktivierung verhindert\n";
        $message .= "Zeit: " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "FEHLERDETAILS:\n";
        $message .= "-------------\n";
        $message .= "Fehler: " . ($error_info['error'] ?? $error_info['message'] ?? 'Unbekannt') . "\n";
        $message .= "Datei: " . ($error_info['file'] ?? 'Unbekannt') . "\n";
        $message .= "Zeile: " . ($error_info['line'] ?? '?') . "\n\n";
        $message .= "MASSNAHMEN:\n";
        $message .= "-----------\n";
        $message .= "1. Prüfen Sie das WordPress Debug-Log\n";
        $message .= "2. Prüfen Sie DGPTM Suite → System Logs\n";
        $message .= "3. Das Modul wurde NICHT automatisch deaktiviert\n";
        $message .= "4. Beheben Sie den Fehler schnellstmöglich\n";
        $message .= "5. Bei Bedarf: Modul manuell deaktivieren\n\n";
        $message .= "Dashboard: " . admin_url('admin.php?page=dgptm-suite') . "\n";
        $message .= "System Logs: " . admin_url('admin.php?page=dgptm-suite-logs') . "\n";

        @wp_mail($admin_email, $subject, $message);
    }
}
