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

        // Validierung - kann false sein wenn API-Abruf fehlschlägt
        if (!is_array($sessions_by_room)) {
            return [];
        }

        return isset($sessions_by_room[$room_id]) ? $sessions_by_room[$room_id] : [];
    }

    /**
     * Aktuelle Session für einen Raum ermitteln
     * NEU v1.1.0: Unterstützt Debug-Datum
     * NEU v1.1.0: Gibt alle aktuell laufenden Sessions zurück (für Rotation)
     */
    public function get_current_session($room_id) {
        $sessions = $this->get_sessions_for_room($room_id);

        if (empty($sessions)) {
            return null;
        }

        $now = $this->get_debug_timestamp();
        $current_sessions = [];

        foreach ($sessions as $session) {
            $start_time = strtotime($session['start_time']);
            $end_time = strtotime($session['end_time']);

            if ($now >= $start_time && $now <= $end_time) {
                $current_sessions[] = $session;
            }
        }

        // Wenn keine laufenden Sessions: null zurückgeben
        if (empty($current_sessions)) {
            return null;
        }

        // Wenn nur eine Session: direkt zurückgeben
        if (count($current_sessions) === 1) {
            return $current_sessions[0];
        }

        // Mehrere Sessions: Array zurückgeben für Rotation
        return $current_sessions;
    }

    /**
     * NEU v1.1.0: Alle aktuell laufenden Sessions für einen Raum
     */
    public function get_all_current_sessions($room_id) {
        $result = $this->get_current_session($room_id);

        // Wenn Ergebnis ein Array von Sessions ist
        if (is_array($result) && isset($result[0]) && is_array($result[0])) {
            return $result;
        }

        // Wenn einzelne Session
        if (is_array($result) && isset($result['id'])) {
            return [$result];
        }

        return [];
    }

    /**
     * Nächste Session für einen Raum ermitteln
     * NEU v1.1.0: Unterstützt Debug-Datum
     */
    public function get_next_session($room_id) {
        $sessions = $this->get_sessions_for_room($room_id);

        if (empty($sessions)) {
            return null;
        }

        $now = $this->get_debug_timestamp();
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

        // NEU v1.1.0: Prüfe ob Venues ohne Raum ausgeblendet werden sollen
        $hide_no_room = get_option('dgptm_session_display_hide_no_room', false);

        foreach ($api_response['sessions'] as $session) {
            $venue_id = $session['venue'] ?? '';

            // NEU v1.1.0: Venue-Name ermitteln (kann leer sein wenn keine Zuordnung)
            $venue_name = $this->get_venue_name($venue_id);

            // NEU v1.1.0: Filtern wenn keine Raum-Zuordnung und Option aktiviert
            if ($hide_no_room && (empty($venue_name) || $venue_name === $venue_id)) {
                continue; // Session überspringen
            }

            $processed = [
                'id' => $session['id'] ?? '',
                'title' => $session['title'] ?? '',
                'description' => $session['description'] ?? '',
                'start_time' => $session['start_time'] ?? '', // Korrekter Feldname
                'end_time' => $this->calculate_end_time($session), // Berechnet aus start_time + duration
                'duration' => $session['duration'] ?? 0, // Dauer in Minuten
                'track_id' => $session['track'] ?? '', // Korrekter Feldname
                'track_name' => $this->get_track_name($session['track'] ?? ''),
                'venue_id' => $venue_id, // Original Venue-ID
                'venue_name' => $venue_name, // Zugeordneter Venue-Name
                'room' => $venue_name, // Venue-Name als Raum verwenden
                'session_type' => $session['session_type'] ?? 'PRESENTATION',
                'featured' => $session['featured'] ?? false,
                'hidden' => $session['hidden'] ?? false,
                'speakers' => $this->extract_speakers($session),
                'created_by' => $session['created_by'] ?? null,
                'language' => $session['language'] ?? 'de'
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
     * Endzeit aus Startzeit und Dauer berechnen
     */
    private function calculate_end_time($session) {
        $start_time = $session['start_time'] ?? '';
        $duration = $session['duration'] ?? 0;

        if (empty($start_time) || $duration == 0) {
            return '';
        }

        $end_timestamp = strtotime($start_time) + ($duration * 60);
        return date('Y-m-d\TH:i:s\Z', $end_timestamp);
    }

    /**
     * Track-Name aus gecachten Tracks abrufen
     */
    private function get_track_name($track_id) {
        if (empty($track_id)) {
            return '';
        }

        // Tracks aus Cache holen
        $tracks = get_transient('dgptm_tracks_cache');

        if (false === $tracks) {
            // Tracks neu abrufen
            $tracks_response = $this->api->get_tracks();
            if (!is_wp_error($tracks_response) && isset($tracks_response['tracks'])) {
                $tracks = $tracks_response['tracks'];
                set_transient('dgptm_tracks_cache', $tracks, $this->cache_duration);
            } else {
                return $track_id; // Fallback auf ID
            }
        }

        // Track-Name finden
        foreach ($tracks as $track) {
            if ($track['track_id'] === $track_id) {
                return $track['name'] ?? $track_id;
            }
        }

        return $track_id; // Fallback auf ID
    }

    /**
     * Venue-Name aus manueller Zuordnung abrufen
     */
    private function get_venue_name($venue_id) {
        if (empty($venue_id)) {
            return '';
        }

        // Manuelle Zuordnung aus Einstellungen
        $venue_mapping = get_option('dgptm_session_display_venue_mapping', []);

        if (isset($venue_mapping[$venue_id])) {
            return $venue_mapping[$venue_id];
        }

        // Fallback: Venue-ID anzeigen
        return $venue_id;
    }

    /**
     * Speaker-Informationen extrahieren
     */
    private function extract_speakers($session) {
        $speakers = [];

        // Speakers sind nicht direkt in der Session, müssen separat abgerufen werden
        // Vorerst nur Speaker-IDs speichern falls vorhanden
        if (isset($session['speakers']) && is_array($session['speakers'])) {
            foreach ($session['speakers'] as $speaker_id) {
                $speaker_info = $this->get_speaker_info($speaker_id);
                if ($speaker_info) {
                    $speakers[] = $speaker_info;
                }
            }
        }

        return $speakers;
    }

    /**
     * Speaker-Details abrufen
     */
    private function get_speaker_info($speaker_id) {
        // Alle Speakers aus Cache holen
        $all_speakers = get_transient('dgptm_speakers_cache');

        if (false === $all_speakers) {
            // Speakers neu abrufen
            $speakers_response = $this->api->get_speakers();
            if (!is_wp_error($speakers_response) && isset($speakers_response['speakers'])) {
                $all_speakers = $speakers_response['speakers'];
                set_transient('dgptm_speakers_cache', $all_speakers, $this->cache_duration);
            } else {
                return null;
            }
        }

        // Speaker finden
        foreach ($all_speakers as $speaker) {
            if ($speaker['id'] === $speaker_id) {
                return [
                    'id' => $speaker['id'] ?? '',
                    'email' => $speaker['email'] ?? '',
                    'first_name' => $speaker['first_name'] ?? '',
                    'last_name' => $speaker['last_name'] ?? '',
                    'name' => trim(($speaker['first_name'] ?? '') . ' ' . ($speaker['last_name'] ?? '')),
                    'designation' => $speaker['designation'] ?? '',
                    'company' => $speaker['company'] ?? '',
                    'description' => $speaker['description'] ?? '',
                    'telephone' => $speaker['telephone'] ?? '',
                    'status' => $speaker['status_string'] ?? 'invited',
                    'featured' => $speaker['featured'] ?? false
                ];
            }
        }

        return null;
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

        // Validierung vor array_keys() - kann immer noch false sein wenn API-Abruf fehlschlägt
        if (!is_array($sessions_by_room) || empty($sessions_by_room)) {
            return [];
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
     * NEU v1.1.0: Unterstützt Debug-Datum
     */
    public function get_session_status($session) {
        $now = $this->get_debug_timestamp();
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
     * NEU v1.1.0: Fortschritt berechnen (öffentliche Methode für Display Controller)
     */
    public function calculate_session_progress($session) {
        $now = $this->get_debug_timestamp();
        $start = strtotime($session['start_time']);
        $end = strtotime($session['end_time']);

        if ($now < $start) {
            return 0;
        } elseif ($now > $end) {
            return 100;
        }

        $total = $end - $start;
        $elapsed = $now - $start;

        return round(($elapsed / $total) * 100);
    }

    /**
     * Zeit bis zur nächsten Session in Minuten
     * NEU v1.1.0: Unterstützt Debug-Datum
     */
    public function get_time_until_next($room_id) {
        $next = $this->get_next_session($room_id);

        if (!$next) {
            return null;
        }

        $now = $this->get_debug_timestamp();
        $start = strtotime($next['start_time']);

        return round(($start - $now) / 60);
    }

    /**
     * NEU v1.1.1: Vorträge finden, die während einer Session im gleichen Raum laufen
     * Diese Methode findet alle Vorträge, die:
     * - Im gleichen Raum (Venue) sind
     * - Zur gleichen Zeit laufen (Zeitüberschneidung)
     * - NICHT aus dem Track "Sessions" sind (das sind die Überschriften)
     */
    public function get_talks_during_session($session, $room_id) {
        $all_sessions = $this->get_cached_sessions();
        $talks = [];

        $session_start = strtotime($session['start_time']);
        $session_end = strtotime($session['end_time']);
        $session_venue = $session['venue_id'];

        // Track "Sessions" identifizieren
        $sessions_track_id = $this->get_sessions_track_id();

        foreach ($all_sessions as $potential_talk) {
            // Überspringen wenn:
            // 1. Es ist die Session selbst
            if ($potential_talk['id'] === $session['id']) {
                continue;
            }

            // 2. Es ist aus dem Track "Sessions" (das sind Überschriften, keine Vorträge)
            if ($potential_talk['track_id'] === $sessions_track_id) {
                continue;
            }

            // 3. Anderes Venue (Raum)
            if ($potential_talk['venue_id'] !== $session_venue) {
                continue;
            }

            // Zeitüberschneidung prüfen
            $talk_start = strtotime($potential_talk['start_time']);
            $talk_end = strtotime($potential_talk['end_time']);

            // Prüfe ob Vortrag während der Session läuft
            $overlaps = ($talk_start < $session_end) && ($talk_end > $session_start);

            if ($overlaps) {
                $talks[] = $potential_talk;
            }
        }

        // Nach Startzeit sortieren
        usort($talks, function($a, $b) {
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });

        return $talks;
    }

    /**
     * NEU v1.1.1: Track-ID von "Sessions" ermitteln
     */
    private function get_sessions_track_id() {
        $tracks = get_transient('dgptm_tracks_cache');

        if (false === $tracks) {
            $tracks_response = $this->api->get_tracks();
            if (!is_wp_error($tracks_response) && isset($tracks_response['tracks'])) {
                $tracks = $tracks_response['tracks'];
                set_transient('dgptm_tracks_cache', $tracks, $this->cache_duration);
            } else {
                return null;
            }
        }

        // Track "Sessions" finden
        foreach ($tracks as $track) {
            if (stripos($track['name'], 'Sessions') !== false) {
                return $track['track_id'];
            }
        }

        return null;
    }

    /**
     * NEU v1.1.0: Debug-Timestamp ermitteln
     * Kombiniert Debug-Datum und Debug-Zeit für Tests
     */
    private function get_debug_timestamp() {
        $debug_enabled = get_option('dgptm_session_display_debug_enabled', false);

        if (!$debug_enabled) {
            return current_time('timestamp');
        }

        // Debug-Zeit
        $debug_time = get_option('dgptm_session_display_debug_time', '09:00');

        // Debug-Datum ermitteln
        $debug_date_mode = get_option('dgptm_session_display_debug_date_mode', 'off');
        $debug_date = '';

        if ($debug_date_mode === 'event_day') {
            // Veranstaltungstag berechnen
            $event_date = get_option('dgptm_session_display_event_date');
            $debug_event_day = get_option('dgptm_session_display_debug_event_day', 1);
            if ($event_date) {
                $debug_date = date('Y-m-d', strtotime($event_date . ' +' . ($debug_event_day - 1) . ' days'));
            }
        } elseif ($debug_date_mode === 'custom') {
            $debug_date = get_option('dgptm_session_display_debug_date_custom', date('Y-m-d'));
        }

        // Wenn kein Debug-Datum: heutiges Datum verwenden
        if (empty($debug_date)) {
            $debug_date = current_time('Y-m-d');
        }

        // Kombiniere Datum und Zeit
        $debug_datetime = $debug_date . ' ' . $debug_time . ':00';

        return strtotime($debug_datetime);
    }
}
