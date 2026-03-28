<?php
/**
 * Session Manager
 *
 * Verwaltet Sessions, Cache und Zeitberechnungen
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Session_Manager {

    private $api;
    private $cache_duration;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->api = new DGPTM_Zoho_Backstage_API();

        // Cache-Dauer basierend auf Veranstaltungstag
        $this->cache_duration = $this->is_event_day() ? 300 : 3600; // 5 Min. am Event-Tag, sonst 1 Std.
    }

    /**
     * Prüfen ob heute Veranstaltungstag ist
     */
    private function is_event_day() {
        $event_date = get_option('dgptm_session_display_event_date');
        if (!$event_date) {
            return false;
        }

        $today = date('Y-m-d');
        $event_start = date('Y-m-d', strtotime($event_date));
        $event_end_days = get_option('dgptm_session_display_event_duration', 1);
        $event_end = date('Y-m-d', strtotime($event_date . ' +' . ($event_end_days - 1) . ' days'));

        return ($today >= $event_start && $today <= $event_end);
    }

    /**
     * Sessions abrufen und cachen
     */
    public function fetch_and_cache_sessions() {
        $sessions = $this->api->get_sessions();

        if (is_wp_error($sessions)) {
            error_log('DGPTM Session Display: Sessions-Abruf fehlgeschlagen - ' . $sessions->get_error_message());
            return false;
        }

        // Sessions verarbeiten und cachen
        $processed_sessions = $this->process_sessions($sessions);
        set_transient('dgptm_sessions_cache', $processed_sessions, $this->cache_duration);

        // Nach Räumen gruppiert cachen
        $sessions_by_room = $this->group_sessions_by_room($processed_sessions);
        set_transient('dgptm_sessions_by_room_cache', $sessions_by_room, $this->cache_duration);

        // Timestamp des letzten Updates speichern
        update_option('dgptm_sessions_last_update', current_time('mysql'));

        return true;
    }

    /**
     * Sessions aus Cache abrufen
     */
    public function get_cached_sessions() {
        $sessions = get_transient('dgptm_sessions_cache');

        // Wenn Cache leer, neu abrufen
        if (false === $sessions) {
            $this->fetch_and_cache_sessions();
            $sessions = get_transient('dgptm_sessions_cache');
        }

        return $sessions ?: [];
    }

    /**
     * Sessions für einen bestimmten Raum abrufen
     */
    public function get_sessions_for_room($room_id) {
        $sessions_by_room = get_transient('dgptm_sessions_by_room_cache');

        if (false === $sessions_by_room) {
            $this->fetch_and_cache_sessions();
            $sessions_by_room = get_transient('dgptm_sessions_by_room_cache');
        }

        return isset($sessions_by_room[$room_id]) ? $sessions_by_room[$room_id] : [];
    }

    /**
     * Aktuelle Session für einen Raum ermitteln
     */
    public function get_current_session($room_id) {
        $sessions = $this->get_sessions_for_room($room_id);

        if (empty($sessions)) {
            return null;
        }

        $now = current_time('timestamp');

        foreach ($sessions as $session) {
            $start_time = strtotime($session['start_time']);
            $end_time = strtotime($session['end_time']);

            if ($now >= $start_time && $now <= $end_time) {
                return $session;
            }
        }

        return null;
    }

    /**
     * Nächste Session für einen Raum ermitteln
     */
    public function get_next_session($room_id) {
        $sessions = $this->get_sessions_for_room($room_id);

        if (empty($sessions)) {
            return null;
        }

        $now = current_time('timestamp');
        $next_session = null;
        $min_diff = PHP_INT_MAX;

        foreach ($sessions as $session) {
            $start_time = strtotime($session['start_time']);

            if ($start_time > $now) {
                $diff = $start_time - $now;
                if ($diff < $min_diff) {
                    $min_diff = $diff;
                    $next_session = $session;
                }
            }
        }

        return $next_session;
    }

    /**
     * Alle Sessions des Tages abrufen
     */
    public function get_todays_sessions() {
        $all_sessions = $this->get_cached_sessions();
        $today = date('Y-m-d');

        return array_filter($all_sessions, function($session) use ($today) {
            $session_date = date('Y-m-d', strtotime($session['start_time']));
            return $session_date === $today;
        });
    }

    /**
     * Sessions verarbeiten
     */
    private function process_sessions($api_response) {
        $sessions = [];

        if (!isset($api_response['sessions']) || !is_array($api_response['sessions'])) {
            return $sessions;
        }

        foreach ($api_response['sessions'] as $session) {
            $processed = [
                'id' => $session['id'] ?? '',
                'title' => $session['title'] ?? '',
                'description' => $session['description'] ?? '',
                'start_time' => $session['startTime'] ?? '',
                'end_time' => $session['endTime'] ?? '',
                'track_id' => $session['trackId'] ?? '',
                'track_name' => $session['trackName'] ?? '',
                'room' => $this->extract_room_from_track($session),
                'speakers' => $this->extract_speakers($session),
                'tags' => $session['tags'] ?? [],
                'status' => $session['status'] ?? 'scheduled',
                'custom_data' => $session['customData'] ?? []
            ];

            $sessions[] = $processed;
        }

        // Nach Startzeit sortieren
        usort($sessions, function($a, $b) {
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });

        return $sessions;
    }

    /**
     * Raum aus Track extrahieren
     */
    private function extract_room_from_track($session) {
        $track_name = $session['trackName'] ?? '';

        // Automatische Raumerkennung aus Track-Namen
        // z.B. "Raum 1", "Room A", "Saal Berlin"
        if (preg_match('/(raum|room|saal)\s*([a-z0-9]+)/i', $track_name, $matches)) {
            return trim($matches[0]);
        }

        // Manuelle Zuordnung aus Einstellungen
        $room_mapping = get_option('dgptm_session_display_room_mapping', []);
        $track_id = $session['trackId'] ?? '';

        if (isset($room_mapping[$track_id])) {
            return $room_mapping[$track_id];
        }

        return $track_name;
    }

    /**
     * Speaker-Informationen extrahieren
     */
    private function extract_speakers($session) {
        $speakers = [];

        if (isset($session['speakers']) && is_array($session['speakers'])) {
            foreach ($session['speakers'] as $speaker) {
                $speakers[] = [
                    'id' => $speaker['id'] ?? '',
                    'name' => $speaker['name'] ?? '',
                    'title' => $speaker['title'] ?? '',
                    'company' => $speaker['company'] ?? '',
                    'bio' => $speaker['bio'] ?? '',
                    'photo' => $speaker['photoUrl'] ?? ''
                ];
            }
        }

        return $speakers;
    }

    /**
     * Sessions nach Räumen gruppieren
     */
    private function group_sessions_by_room($sessions) {
        $grouped = [];

        foreach ($sessions as $session) {
            $room = $session['room'];
            if (!isset($grouped[$room])) {
                $grouped[$room] = [];
            }
            $grouped[$room][] = $session;
        }

        return $grouped;
    }

    /**
     * Alle Räume abrufen
     */
    public function get_all_rooms() {
        $sessions_by_room = get_transient('dgptm_sessions_by_room_cache');

        if (false === $sessions_by_room) {
            $this->fetch_and_cache_sessions();
            $sessions_by_room = get_transient('dgptm_sessions_by_room_cache');
        }

        return array_keys($sessions_by_room);
    }

    /**
     * Übersicht für alle Räume abrufen
     */
    public function get_rooms_overview() {
        $rooms = $this->get_all_rooms();
        $overview = [];

        foreach ($rooms as $room) {
            $current = $this->get_current_session($room);
            $next = $this->get_next_session($room);

            $overview[$room] = [
                'room' => $room,
                'current_session' => $current,
                'next_session' => $next,
                'status' => $current ? 'active' : ($next ? 'upcoming' : 'idle')
            ];
        }

        return $overview;
    }

    /**
     * Session-Status ermitteln
     */
    public function get_session_status($session) {
        $now = current_time('timestamp');
        $start = strtotime($session['start_time']);
        $end = strtotime($session['end_time']);

        if ($now < $start) {
            $diff = $start - $now;
            if ($diff <= 900) { // 15 Minuten
                return 'starting_soon';
            }
            return 'upcoming';
        } elseif ($now >= $start && $now <= $end) {
            $remaining = $end - $now;
            if ($remaining <= 300) { // 5 Minuten
                return 'ending_soon';
            }
            return 'active';
        } else {
            return 'finished';
        }
    }

    /**
     * Zeit bis zur nächsten Session in Minuten
     */
    public function get_time_until_next($room_id) {
        $next = $this->get_next_session($room_id);

        if (!$next) {
            return null;
        }

        $now = current_time('timestamp');
        $start = strtotime($next['start_time']);

        return round(($start - $now) / 60);
    }
}
