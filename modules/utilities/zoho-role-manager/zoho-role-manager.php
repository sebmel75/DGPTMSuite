<?php
/**
 * Plugin Name: Zoho Role Manager
 * Description: Verwaltet die "mitglied" Rolle basierend auf Zoho CRM Daten (einmal pro Login)
 * Version: 1.0.0
 * Author: Sebastian
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zoho_Role_Manager {
    
    private $session_key = 'zoho_role_sync_done';
    
    public function __construct() {
        add_shortcode('sync_mitglied_rolle', array($this, 'sync_role_shortcode'));
    }
    
    /**
     * Shortcode Handler: [sync_mitglied_rolle]
     */
    public function sync_role_shortcode($atts) {
        // Nur für eingeloggte Nutzer
        if (!is_user_logged_in()) {
            return '<div class="zoho-role-message info">Bitte loggen Sie sich ein.</div>';
        }
        
        // Prüfen, ob bereits in dieser Session synchronisiert wurde
        if ($this->is_already_synced()) {
            return '<div class="zoho-role-message info">Rollensynchronisation bereits durchgeführt.</div>';
        }
        
        // Zoho Daten abrufen
        $aktives_mitglied = do_shortcode('[zoho_api_data field="aktives_mitglied"]');
        
        // Rolle synchronisieren
        $result = $this->sync_user_role($aktives_mitglied);
        
        // Session-Flag setzen
        $this->mark_as_synced();
        
        // Rückmeldung ausgeben
        return $this->get_feedback_message($result);
    }
    
    /**
     * Prüft, ob die Synchronisation in dieser Session bereits erfolgt ist
     */
    private function is_already_synced() {
        if (!session_id()) {
            session_start();
        }
        return isset($_SESSION[$this->session_key]) && $_SESSION[$this->session_key] === true;
    }
    
    /**
     * Markiert die Synchronisation als durchgeführt
     */
    private function mark_as_synced() {
        if (!session_id()) {
            session_start();
        }
        $_SESSION[$this->session_key] = true;
    }
    
    /**
     * Synchronisiert die Benutzerrolle basierend auf dem Zoho-Wert
     */
    private function sync_user_role($zoho_value) {
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return array('status' => 'error', 'message' => 'Benutzer nicht gefunden');
        }
        
        // Aktuellen Wert normalisieren
        $zoho_value = trim($zoho_value);
        $is_active = $this->parse_boolean($zoho_value);
        
        // Aktuelle Rollen des Benutzers
        $current_roles = $user->roles;
        $has_mitglied = in_array('mitglied', $current_roles);
        
        $action_taken = 'none';
        
        if ($is_active && !$has_mitglied) {
            // Rolle "mitglied" hinzufügen
            $user->add_role('mitglied');
            $action_taken = 'added';
            
        } elseif (!$is_active && $has_mitglied) {
            // Rolle "mitglied" entfernen
            $user->remove_role('mitglied');
            $action_taken = 'removed';
            
            // Fallback: Wenn keine Rolle mehr vorhanden, subscriber zuweisen
            $updated_user = get_user_by('id', $user_id);
            if (empty($updated_user->roles)) {
                $user->add_role('subscriber');
                $action_taken = 'removed_fallback';
            }
        }
        
        return array(
            'status' => 'success',
            'action' => $action_taken,
            'is_active' => $is_active,
            'zoho_value' => $zoho_value
        );
    }
    
    /**
     * Konvertiert verschiedene Boolean-Formate in true/false
     */
    private function parse_boolean($value) {
        // String in Kleinbuchstaben konvertieren
        $value_lower = strtolower(trim($value));
        
        // True-Werte
        if (in_array($value_lower, array('true', '1', 'yes', 'ja', 'aktiv'))) {
            return true;
        }
        
        // False-Werte
        if (in_array($value_lower, array('false', '0', 'no', 'nein', 'inaktiv', ''))) {
            return false;
        }
        
        // Numerische Prüfung
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        
        // Standard: false
        return false;
    }
    
    /**
     * Generiert die Feedback-Nachricht für den Benutzer

    private function get_feedback_message($result) {
        if ($result['status'] === 'error') {
            return '<div class="zoho-role-message error">' . esc_html($result['message']) . '</div>';
        }
        
        $messages = array(
            'added' => 'Ihre Mitgliedschaft wurde erfolgreich aktiviert.',
            'removed' => 'Ihre Mitgliedschaft wurde deaktiviert.',
            'removed_fallback' => 'Ihre Mitgliedschaft wurde deaktiviert. Sie haben jetzt Standard-Zugriffsrechte.',
            'none' => 'Ihre Mitgliedschaftsdaten sind bereits aktuell.'
        );
        
        $message = isset($messages[$result['action']]) ? $messages[$result['action']] : 'Synchronisation abgeschlossen.';
        
        return '<div class="zoho-role-message success">' . esc_html($message) . '</div>';
    }*/
}     

// Plugin initialisieren
new Zoho_Role_Manager();

/**
 * CSS für Feedback-Nachrichten
  
add_action('wp_head', function() {
    ?>
    <style>
        .zoho-role-message {
            padding: 12px 15px;
            margin: 15px 0;
            border-radius: 4px;
            font-size: 14px;
        }
        .zoho-role-message.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .zoho-role-message.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .zoho-role-message.info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
    </style>
    <?php
}
		  
		  );*/
