<?php
/**
 * DGPTM Stipendium — Gutachter-Bewertungsbogen.
 *
 * Shortcode [dgptm_stipendium_gutachten] fuer den Token-basierten
 * Gutachter-Zugang. Kein Login erforderlich — Token validiert Identitaet.
 *
 * Zustaende:
 *   1. Token gueltig, Status ausstehend/entwurf → Bewertungsbogen
 *   2. Token gueltig, Status abgeschlossen → Danke-Seite
 *   3. Token ungueltig/abgelaufen → Fehler-Seite
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Gutachter_Form {

    const NONCE_ACTION = 'dgptm_stipendium_gutachten_nonce';

    private $plugin_path;
    private $plugin_url;
    private $token_manager;
    private $zoho;

    public function __construct($plugin_path, $plugin_url, $token_manager, $zoho = null) {
        $this->plugin_path   = $plugin_path;
        $this->plugin_url    = $plugin_url;
        $this->token_manager = $token_manager;
        $this->zoho          = $zoho;

        // Shortcode
        add_shortcode('dgptm_stipendium_gutachten', [$this, 'render_shortcode']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        // AJAX-Endpoints (kein Login noetig — Token validiert)
        add_action('wp_ajax_dgptm_stipendium_autosave', [$this, 'ajax_autosave']);
        add_action('wp_ajax_nopriv_dgptm_stipendium_autosave', [$this, 'ajax_autosave']);
        add_action('wp_ajax_dgptm_stipendium_submit_gutachten', [$this, 'ajax_submit']);
        add_action('wp_ajax_nopriv_dgptm_stipendium_submit_gutachten', [$this, 'ajax_submit']);
    }

    /**
     * Assets registrieren (noch nicht einreihen).
     */
    public function register_assets() {
        wp_register_style(
            'dgptm-gutachten',
            $this->plugin_url . 'assets/css/gutachten.css',
            [],
            '1.1.0'
        );
        wp_register_script(
            'dgptm-gutachten',
            $this->plugin_url . 'assets/js/gutachten.js',
            ['jquery'],
            '1.1.0',
            true
        );
    }

    /**
     * Shortcode [dgptm_stipendium_gutachten] rendern.
     */
    public function render_shortcode($atts) {
        $token_string = sanitize_text_field($_GET['token'] ?? '');

        if (empty($token_string)) {
            ob_start();
            include $this->plugin_path . 'templates/gutachten-ungueltig.php';
            return ob_get_clean();
        }

        $token_data = $this->token_manager->validate($token_string);

        // Token ungueltig oder abgelaufen
        if (is_wp_error($token_data)) {
            $error_message = $token_data->get_error_message();
            ob_start();
            include $this->plugin_path . 'templates/gutachten-ungueltig.php';
            return ob_get_clean();
        }

        // Bereits abgeschlossen → Danke-Seite
        if ($token_data['bewertung_status'] === 'abgeschlossen') {
            $bewertung_data = json_decode($token_data['bewertung_data'] ?? '{}', true);
            $gesamtscore = $this->calculate_score($bewertung_data);
            $completed_at = $token_data['completed_at'];

            // Bewerberdaten aus CRM laden (gecacht)
            $stipendium = $this->get_stipendium_data($token_data['stipendium_id']);
            $bewerber_name = $stipendium['Bewerber']['name'] ?? $stipendium['Name'] ?? 'Unbekannt';

            ob_start();
            include $this->plugin_path . 'templates/gutachten-danke.php';
            return ob_get_clean();
        }

        // Bewertungsbogen anzeigen
        wp_enqueue_style('dgptm-gutachten');
        wp_enqueue_script('dgptm-gutachten');

        // Bestehende Entwurfsdaten laden
        $draft_data = [];
        if ($token_data['bewertung_status'] === 'entwurf' && !empty($token_data['bewertung_data'])) {
            $draft_data = json_decode($token_data['bewertung_data'], true) ?: [];
        }

        // Bewerberdaten aus CRM laden
        $stipendium = $this->get_stipendium_data($token_data['stipendium_id']);
        $bewerber_name = $stipendium['Bewerber']['name'] ?? $stipendium['Name'] ?? 'Unbekannt';
        $stipendientyp = $stipendium['Stipendientyp'] ?? '';
        $runde = $stipendium['Runde'] ?? '';

        // Dokument-URLs
        $dokumente = $this->extract_document_urls($stipendium);

        // JS-Variablen
        wp_localize_script('dgptm-gutachten', 'dgptmGutachten', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce(self::NONCE_ACTION),
            'token'      => $token_string,
            'draftData'  => $draft_data,
            'strings'    => [
                'saving'        => 'Speichere...',
                'saved'         => 'Entwurf gespeichert um',
                'save_error'    => 'Fehler beim Speichern.',
                'confirm_submit'=> 'Moechten Sie Ihr Gutachten jetzt abschliessen? Nach dem Abschluss kann die Bewertung nicht mehr geaendert werden.',
                'submitting'    => 'Wird uebermittelt...',
                'submit_error'  => 'Fehler beim Uebermitteln. Bitte versuchen Sie es erneut.',
            ],
        ]);

        ob_start();
        include $this->plugin_path . 'templates/gutachten-form.php';
        return ob_get_clean();
    }

    /**
     * AJAX: Auto-Save (alle 30 Sekunden).
     *
     * Erwartet POST: token, data (JSON-encoded), nonce
     * Kein Login noetig — Token validiert.
     */
    public function ajax_autosave() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $token = sanitize_text_field($_POST['token'] ?? '');
        $raw_data = wp_unslash($_POST['data'] ?? '');
        $data = is_string($raw_data) ? json_decode($raw_data, true) : $raw_data;

        if (!is_array($data)) {
            wp_send_json_error(['message' => 'Ungueltige Daten.']);
        }

        // Daten sanitizen
        $clean = $this->sanitize_bewertung_data($data);

        $result = $this->token_manager->save_draft($token, $clean);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'saved_at' => current_time('H:i'),
        ]);
    }

    /**
     * AJAX: Gutachten abschliessen.
     *
     * 1. Daten validieren (alle Pflichtfelder ausgefuellt)
     * 2. Score berechnen
     * 3. Bewertung in Zoho CRM erstellen
     * 4. Token als abgeschlossen markieren
     * 5. Benachrichtigungs-Mail an Vorsitzenden
     */
    public function ajax_submit() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $token = sanitize_text_field($_POST['token'] ?? '');
        $raw_data = wp_unslash($_POST['data'] ?? '');
        $data = is_string($raw_data) ? json_decode($raw_data, true) : $raw_data;

        if (!is_array($data)) {
            wp_send_json_error(['message' => 'Ungueltige Daten.']);
        }

        // Token validieren
        $token_data = $this->token_manager->validate($token);
        if (is_wp_error($token_data)) {
            wp_send_json_error(['message' => $token_data->get_error_message()]);
        }

        if ($token_data['bewertung_status'] === 'abgeschlossen') {
            wp_send_json_error(['message' => 'Diese Bewertung wurde bereits abgeschlossen.']);
        }

        // Daten sanitizen
        $clean = $this->sanitize_bewertung_data($data);

        // Pflichtfelder pruefen (alle 12 Noten muessen 1-10 sein)
        $noten_felder = ['A1_Note','A2_Note','A3_Note','B1_Note','B2_Note','B3_Note',
                         'C1_Note','C2_Note','C3_Note','D1_Note','D2_Note','D3_Note'];
        foreach ($noten_felder as $feld) {
            $wert = (int)($clean[$feld] ?? 0);
            if ($wert < 1 || $wert > 10) {
                wp_send_json_error([
                    'message' => 'Bitte vergeben Sie fuer alle Leitfragen eine Note zwischen 1 und 10.',
                    'field'   => $feld,
                ]);
            }
        }

        // Score berechnen
        $score = $this->calculate_score($clean);
        $clean['Gesamtscore'] = $score['gesamt'];
        $clean['A_Gewichtet'] = $score['a_gewichtet'];
        $clean['B_Gewichtet'] = $score['b_gewichtet'];
        $clean['C_Gewichtet'] = $score['c_gewichtet'];
        $clean['D_Gewichtet'] = $score['d_gewichtet'];

        // Bewertung in Zoho CRM erstellen
        $crm_id = '';
        if ($this->zoho) {
            $crm_data = [
                'Stipendium'        => $token_data['stipendium_id'],
                'Gutachter_Name'    => $token_data['gutachter_name'],
                'Gutachter_Email'   => $token_data['gutachter_email'],
                'Status'            => 'Abgeschlossen',
                'Bewertungsdatum'   => date('Y-m-d\TH:i:sP'),
            ];
            // Noten und Kommentare uebernehmen
            foreach ($clean as $key => $val) {
                if (preg_match('/^[ABCD]\d?_/', $key) || $key === 'Gesamtanmerkungen') {
                    $crm_data[$key] = $val;
                }
            }
            // Gewichtete Scores
            $crm_data['A_Gewichtet'] = $score['a_gewichtet'];
            $crm_data['B_Gewichtet'] = $score['b_gewichtet'];
            $crm_data['C_Gewichtet'] = $score['c_gewichtet'];
            $crm_data['D_Gewichtet'] = $score['d_gewichtet'];
            $crm_data['Gesamtscore'] = $score['gesamt'];

            $crm_result = $this->zoho->create_bewertung($crm_data);
            if (!is_wp_error($crm_result) && isset($crm_result['data'][0]['details']['id'])) {
                $crm_id = $crm_result['data'][0]['details']['id'];
            }
        }

        // Token als abgeschlossen markieren
        $result = $this->token_manager->mark_complete($token, $clean, $crm_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Benachrichtigungs-Mail an Vorsitzenden
        $this->notify_vorsitz($token_data, $clean, $score);

        wp_send_json_success([
            'message'     => 'Vielen Dank! Ihr Gutachten wurde erfolgreich uebermittelt.',
            'gesamtscore' => $score['gesamt'],
            'punkte'      => round($score['gesamt'] * 10, 1),
        ]);
    }

    /**
     * Bewertungsdaten sanitizen.
     *
     * @param array $data Rohdaten aus Formular
     * @return array Bereinigte Daten
     */
    private function sanitize_bewertung_data($data) {
        $clean = [];
        $noten_felder = ['A1_Note','A2_Note','A3_Note','B1_Note','B2_Note','B3_Note',
                         'C1_Note','C2_Note','C3_Note','D1_Note','D2_Note','D3_Note'];
        $kommentar_felder = ['A_Kommentar','B_Kommentar','C_Kommentar','D_Kommentar','Gesamtanmerkungen'];

        foreach ($noten_felder as $feld) {
            $wert = isset($data[$feld]) ? (int) $data[$feld] : 0;
            $clean[$feld] = max(0, min(10, $wert));
        }

        foreach ($kommentar_felder as $feld) {
            $clean[$feld] = sanitize_textarea_field($data[$feld] ?? '');
        }

        return $clean;
    }

    /**
     * Gesamtscore berechnen.
     *
     * Gewichtung: A=30%, B=30%, C=25%, D=15%
     *
     * @param array $data Bewertungsdaten
     * @return array Score-Details
     */
    private function calculate_score($data) {
        $a_avg = (($data['A1_Note'] ?? 0) + ($data['A2_Note'] ?? 0) + ($data['A3_Note'] ?? 0)) / 3.0;
        $b_avg = (($data['B1_Note'] ?? 0) + ($data['B2_Note'] ?? 0) + ($data['B3_Note'] ?? 0)) / 3.0;
        $c_avg = (($data['C1_Note'] ?? 0) + ($data['C2_Note'] ?? 0) + ($data['C3_Note'] ?? 0)) / 3.0;
        $d_avg = (($data['D1_Note'] ?? 0) + ($data['D2_Note'] ?? 0) + ($data['D3_Note'] ?? 0)) / 3.0;

        $a_gew = round($a_avg * 0.30, 4);
        $b_gew = round($b_avg * 0.30, 4);
        $c_gew = round($c_avg * 0.25, 4);
        $d_gew = round($d_avg * 0.15, 4);

        $gesamt = round($a_gew + $b_gew + $c_gew + $d_gew, 2);

        return [
            'a_avg'       => round($a_avg, 2),
            'b_avg'       => round($b_avg, 2),
            'c_avg'       => round($c_avg, 2),
            'd_avg'       => round($d_avg, 2),
            'a_gewichtet' => $a_gew,
            'b_gewichtet' => $b_gew,
            'c_gewichtet' => $c_gew,
            'd_gewichtet' => $d_gew,
            'gesamt'      => $gesamt,
        ];
    }

    /**
     * Stipendium-Daten aus CRM abrufen (gecacht).
     *
     * @param string $stipendium_id Zoho Record-ID
     * @return array Stipendium-Daten
     */
    private function get_stipendium_data($stipendium_id) {
        // Demo-Modus: Vollstaendige Testdaten liefern
        if (!$this->zoho || strpos($stipendium_id, 'DEMO') === 0) {
            return [
                'Name'                      => 'Demo-Bewerbung',
                'Bewerber'                  => ['name' => 'Dr. Max Mustermann'],
                'Stipendientyp'             => 'Promotionsstipendium',
                'Runde'                     => 'Ausschreibung 2026 (Demo)',
                'Eingangsdatum'             => '2026-04-01',
                'bewerber_institution'      => 'Universitaetsklinikum Beispielstadt',
                'bewerber_orcid'            => '0000-0002-1234-5678',
                'bewerber_email'            => 'max.mustermann@example.de',
                'projekt_titel'             => 'Einfluss der minimalinvasiven extrakorporalen Zirkulation auf die postoperative kognitive Funktion bei aelteren Patienten',
                'projekt_zusammenfassung'   => 'Diese Promotionsarbeit untersucht den Einfluss verschiedener Perfusionsstrategien auf die postoperative kognitive Funktion bei Patienten ueber 70 Jahre. Im Fokus steht der Vergleich zwischen konventioneller und minimalinvasiver extrakorporaler Zirkulation (MiECC) hinsichtlich inflammatorischer Marker, Mikroembolisation und neurokognitiver Outcomes. Die Studie wird als prospektive, randomisierte Untersuchung an 120 Patienten durchgefuehrt.',
                'projekt_methodik'          => 'Prospektiv, randomisiert, monozentrisch. 120 Patienten (60 MiECC vs. 60 konventionell). Neurokognitive Testbatterie praeoperativ, 7 Tage und 3 Monate postoperativ. Inflammationsmarker (IL-6, TNF-alpha, S100B) intraoperativ und bis 48h postoperativ. Transkranielle Dopplersonographie zur Detektion von Mikroembolien.',
                'Lebenslauf_URL'            => 'https://perfusiologie.de/wp-content/uploads/demo/lebenslauf-mustermann.pdf',
                'Motivationsschreiben_URL'  => 'https://perfusiologie.de/wp-content/uploads/demo/motivationsschreiben-mustermann.pdf',
                'Empfehlungsschreiben_URL'  => 'https://perfusiologie.de/wp-content/uploads/demo/empfehlungsschreiben-mustermann.pdf',
                'Studienleistungen_URL'     => 'https://perfusiologie.de/wp-content/uploads/demo/studienleistungen-mustermann.pdf',
                'Publikationen_URL'         => 'https://perfusiologie.de/wp-content/uploads/demo/publikationen-mustermann.pdf',
            ];
        }

        $cache_key = 'dgptm_stip_detail_' . $stipendium_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $result = $this->zoho->get_stipendium($stipendium_id);
        if (is_wp_error($result)) {
            return ['Name' => 'Fehler beim Laden', 'Stipendientyp' => '', 'Runde' => ''];
        }

        $data = $result['data'][0] ?? $result;
        set_transient($cache_key, $data, 10 * MINUTE_IN_SECONDS);
        return $data;
    }

    /**
     * Dokument-URLs aus Stipendium-Record extrahieren.
     *
     * @param array $stipendium Stipendium-Daten
     * @return array Dokument-Liste mit Label + URL
     */
    private function extract_document_urls($stipendium) {
        $felder = [
            'Lebenslauf_URL'            => 'Lebenslauf',
            'Motivationsschreiben_URL'  => 'Motivationsschreiben',
            'Empfehlungsschreiben_URL'  => 'Empfehlungsschreiben',
            'Studienleistungen_URL'     => 'Studienleistungen',
            'Publikationen_URL'         => 'Publikationen',
            'Zusatzqualifikationen_URL' => 'Ehrenamt/Zusatzqualifikationen',
        ];

        $dokumente = [];
        foreach ($felder as $key => $label) {
            if (!empty($stipendium[$key])) {
                $dokumente[] = [
                    'label' => $label,
                    'url'   => $stipendium[$key],
                ];
            }
        }
        return $dokumente;
    }

    /**
     * Vorsitzenden per E-Mail benachrichtigen.
     */
    private function notify_vorsitz($token_data, $bewertung_data, $score) {
        // Vorsitz-E-Mail aus Settings
        $stipendium_instance = DGPTM_Stipendium::get_instance();
        $settings = $stipendium_instance->get_settings();
        $vorsitz_email = $settings ? $settings->get('benachrichtigung_vorsitz_email') : '';

        if (empty($vorsitz_email)) return;

        $stipendium = $this->get_stipendium_data($token_data['stipendium_id']);

        DGPTM_Stipendium_Mail_Templates::send_abschluss_benachrichtigung([
            'vorsitz_email'  => $vorsitz_email,
            'gutachter_name' => $token_data['gutachter_name'],
            'bewerber_name'  => $stipendium['Bewerber']['name'] ?? $stipendium['Name'] ?? 'Unbekannt',
            'stipendientyp'  => $stipendium['Stipendientyp'] ?? '',
            'gesamtscore'    => $score['gesamt'],
            'datum'          => current_time('d.m.Y, H:i'),
        ]);
    }
}
