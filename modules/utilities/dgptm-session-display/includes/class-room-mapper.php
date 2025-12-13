<?php
/**
 * Room Mapper
 *
 * Verwaltet die Zuordnung von Tracks zu Räumen
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Room_Mapper {

    /**
     * Track zu Raum zuordnen
     */
    public static function map_track_to_room($track_id, $room_name) {
        $mapping = get_option('dgptm_session_display_room_mapping', []);
        $mapping[$track_id] = sanitize_text_field($room_name);
        update_option('dgptm_session_display_room_mapping', $mapping);
    }

    /**
     * Raum für Track abrufen
     */
    public static function get_room_for_track($track_id) {
        $mapping = get_option('dgptm_session_display_room_mapping', []);
        return isset($mapping[$track_id]) ? $mapping[$track_id] : null;
    }

    /**
     * Alle Zuordnungen abrufen
     */
    public static function get_all_mappings() {
        return get_option('dgptm_session_display_room_mapping', []);
    }

    /**
     * Zuordnung entfernen
     */
    public static function remove_mapping($track_id) {
        $mapping = get_option('dgptm_session_display_room_mapping', []);
        if (isset($mapping[$track_id])) {
            unset($mapping[$track_id]);
            update_option('dgptm_session_display_room_mapping', $mapping);
        }
    }

    /**
     * Alle Zuordnungen zurücksetzen
     */
    public static function reset_all_mappings() {
        delete_option('dgptm_session_display_room_mapping');
    }

    /**
     * Automatische Zuordnung basierend auf Track-Namen
     */
    public static function auto_map_from_track_names($tracks) {
        $mapping = [];

        foreach ($tracks as $track) {
            $track_id = $track['id'] ?? '';
            $track_name = $track['name'] ?? '';

            if (empty($track_id) || empty($track_name)) {
                continue;
            }

            // Raum aus Namen extrahieren
            $room = self::extract_room_from_name($track_name);

            if ($room) {
                $mapping[$track_id] = $room;
            }
        }

        if (!empty($mapping)) {
            update_option('dgptm_session_display_room_mapping', $mapping);
        }

        return $mapping;
    }

    /**
     * Raum aus Track-Namen extrahieren
     */
    private static function extract_room_from_name($track_name) {
        // Verschiedene Muster für Raumnamen
        $patterns = [
            '/raum\s*([a-z0-9]+)/i',
            '/room\s*([a-z0-9]+)/i',
            '/saal\s*([a-z0-9]+)/i',
            '/hall\s*([a-z0-9]+)/i',
            '/auditorium\s*([a-z0-9]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $track_name, $matches)) {
                return 'Raum ' . trim($matches[1]);
            }
        }

        // Wenn kein Muster passt, kompletten Track-Namen verwenden
        return $track_name;
    }

    /**
     * Räume nach Etagen gruppieren
     */
    public static function assign_room_to_floor($room_name, $floor) {
        $floors = get_option('dgptm_session_display_floors', []);

        if (!isset($floors[$floor])) {
            $floors[$floor] = [];
        }

        if (!in_array($room_name, $floors[$floor])) {
            $floors[$floor][] = $room_name;
        }

        update_option('dgptm_session_display_floors', $floors);
    }

    /**
     * Raum von Etage entfernen
     */
    public static function remove_room_from_floor($room_name, $floor) {
        $floors = get_option('dgptm_session_display_floors', []);

        if (isset($floors[$floor])) {
            $floors[$floor] = array_diff($floors[$floor], [$room_name]);
            if (empty($floors[$floor])) {
                unset($floors[$floor]);
            }
        }

        update_option('dgptm_session_display_floors', $floors);
    }

    /**
     * Alle Etagen abrufen
     */
    public static function get_all_floors() {
        return get_option('dgptm_session_display_floors', []);
    }

    /**
     * Räume einer Etage abrufen
     */
    public static function get_rooms_on_floor($floor) {
        $floors = get_option('dgptm_session_display_floors', []);
        return isset($floors[$floor]) ? $floors[$floor] : [];
    }
}
