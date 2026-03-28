<?php
/**
 * Display Controller
 *
 * Steuert die Anzeige von Sessions auf den Displays
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Session_Display_Controller {

    private $session_manager;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->session_manager = new DGPTM_Session_Manager();
    }

    /**
     * Session-Daten für Display abrufen
     */
    public function get_session_for_display($room_id, $display_type = 'current') {
        $data = [
            'room' => $room_id,
            'current_session' => null,
            'next_session' => null,
            'sponsors' => $this->get_sponsors(),
            'timestamp' => current_time('mysql'),
            'status' => 'idle'
        ];

        if ($display_type === 'current' || $display_type === 'both') {
            $current = $this->session_manager->get_current_session($room_id);
            if ($current) {
                $data['current_session'] = $this->format_session_for_display($current);
                $data['status'] = 'active';
            }
        }

        if ($display_type === 'next' || $display_type === 'both') {
            $next = $this->session_manager->get_next_session($room_id);
            if ($next) {
                $data['next_session'] = $this->format_session_for_display($next);
                if ($data['status'] === 'idle') {
                    $data['status'] = 'upcoming';
                }
            }
        }

        // Wenn keine aktuelle Session, aber nächste Session existiert
        if (!$data['current_session'] && $data['next_session']) {
            $time_until = $this->session_manager->get_time_until_next($room_id);
            $data['time_until_next'] = $time_until;
            $data['time_until_text'] = $this->format_time_until($time_until);
        }

        return $data;
    }

    /**
     * Session für Anzeige formatieren
     */
    private function format_session_for_display($session) {
        $formatted = [
            'id' => $session['id'],
            'title' => $session['title'],
            'description' => $this->format_description($session['description']),
            'start_time' => date_i18n('H:i', strtotime($session['start_time'])),
            'end_time' => date_i18n('H:i', strtotime($session['end_time'])),
            'duration' => $this->calculate_duration($session['start_time'], $session['end_time']),
            'speakers' => $this->format_speakers($session['speakers']),
            'room' => $session['room'],
            'status' => $this->session_manager->get_session_status($session),
            'progress' => $this->calculate_progress($session)
        ];

        return $formatted;
    }

    /**
     * Beschreibung formatieren
     */
    private function format_description($description, $max_length = 200) {
        if (strlen($description) <= $max_length) {
            return $description;
        }

        return substr($description, 0, $max_length) . '...';
    }

    /**
     * Speaker formatieren
     */
    private function format_speakers($speakers) {
        if (empty($speakers)) {
            return [];
        }

        $formatted = [];
        foreach ($speakers as $speaker) {
            $formatted[] = [
                'name' => $speaker['name'],
                'title' => $speaker['title'],
                'company' => $speaker['company'],
                'display_name' => $this->get_speaker_display_name($speaker)
            ];
        }

        return $formatted;
    }

    /**
     * Speaker-Anzeigename erstellen
     */
    private function get_speaker_display_name($speaker) {
        $parts = [];

        if (!empty($speaker['name'])) {
            $parts[] = $speaker['name'];
        }

        if (!empty($speaker['title']) && !empty($speaker['company'])) {
            $parts[] = $speaker['title'] . ', ' . $speaker['company'];
        } elseif (!empty($speaker['title'])) {
            $parts[] = $speaker['title'];
        } elseif (!empty($speaker['company'])) {
            $parts[] = $speaker['company'];
        }

        return implode(' – ', $parts);
    }

    /**
     * Dauer berechnen
     */
    private function calculate_duration($start_time, $end_time) {
        $start = strtotime($start_time);
        $end = strtotime($end_time);
        $minutes = ($end - $start) / 60;

        return $minutes . ' Min.';
    }

    /**
     * Fortschritt berechnen (0-100%)
     */
    private function calculate_progress($session) {
        $now = current_time('timestamp');
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
     * Zeit bis zur nächsten Session formatieren
     */
    private function format_time_until($minutes) {
        if ($minutes === null) {
            return '';
        }

        if ($minutes < 1) {
            return 'Startet gleich';
        } elseif ($minutes < 60) {
            return 'Startet in ' . $minutes . ' Min.';
        } else {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            if ($mins > 0) {
                return 'Startet in ' . $hours . 'h ' . $mins . ' Min.';
            } else {
                return 'Startet in ' . $hours . ' Stunde' . ($hours > 1 ? 'n' : '');
            }
        }
    }

    /**
     * Sponsoren abrufen
     */
    private function get_sponsors() {
        $show_sponsors = get_option('dgptm_session_display_show_sponsors', true);

        if (!$show_sponsors) {
            return [];
        }

        $sponsors = get_option('dgptm_session_display_sponsors', []);

        if (empty($sponsors)) {
            return [];
        }

        // Zufällig mischen für Rotation
        shuffle($sponsors);

        return array_slice($sponsors, 0, 5); // Max. 5 Sponsoren gleichzeitig
    }

    /**
     * Display rendern (für Shortcode/Widget)
     */
    public function render_display($atts) {
        $room = $atts['room'] ?? '';
        $type = $atts['type'] ?? 'current';
        $show_sponsors = filter_var($atts['show_sponsors'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        $refresh = $atts['refresh'] ?? 'auto';

        if (empty($room)) {
            echo '<div class="dgptm-session-display-error">Kein Raum angegeben</div>';
            return;
        }

        $data = $this->get_session_for_display($room, $type);

        include DGPTM_SESSION_DISPLAY_PATH . 'templates/display-single.php';
    }

    /**
     * Übersicht rendern
     */
    public function render_overview($atts) {
        $floor = $atts['floor'] ?? '';
        $rooms = $atts['rooms'] ?? '';
        $layout = $atts['layout'] ?? 'grid';
        $show_time = filter_var($atts['show_time'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

        $overview_data = $this->get_overview_data($floor, $rooms);

        include DGPTM_SESSION_DISPLAY_PATH . 'templates/display-overview.php';
    }

    /**
     * Übersichtsdaten abrufen
     */
    private function get_overview_data($floor, $rooms_filter) {
        $all_rooms = $this->session_manager->get_rooms_overview();

        // Nach Etage filtern
        if (!empty($floor)) {
            $floor_rooms = get_option('dgptm_session_display_floors', []);
            if (isset($floor_rooms[$floor])) {
                $allowed_rooms = $floor_rooms[$floor];
                $all_rooms = array_filter($all_rooms, function($room_id) use ($allowed_rooms) {
                    return in_array($room_id, $allowed_rooms);
                }, ARRAY_FILTER_USE_KEY);
            }
        }

        // Nach Raum-Liste filtern
        if (!empty($rooms_filter)) {
            $room_list = array_map('trim', explode(',', $rooms_filter));
            $all_rooms = array_filter($all_rooms, function($room_id) use ($room_list) {
                return in_array($room_id, $room_list);
            }, ARRAY_FILTER_USE_KEY);
        }

        // Sessions formatieren
        foreach ($all_rooms as $room_id => &$room_data) {
            if ($room_data['current_session']) {
                $room_data['current_session'] = $this->format_session_for_display($room_data['current_session']);
            }
            if ($room_data['next_session']) {
                $room_data['next_session'] = $this->format_session_for_display($room_data['next_session']);
            }
        }

        return $all_rooms;
    }
}
