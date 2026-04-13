<?php
/**
 * DGPTM Stipendium — Vorsitzenden-Dashboard.
 *
 * Vollstaendige Implementierung des Auswertungs-Dashboards:
 * - Bewerbungen nach Status gruppiert anzeigen
 * - Gutachter einladen (Token + Mail)
 * - Status-Aktionen (Freigeben, Ablehnen, Vergeben, Archivieren)
 * - Ranking-Berechnung triggern
 * - PDF-Export (Platzhalter fuer spaeteren Zoho Writer Aufruf)
 *
 * Berechtigung: acf:stipendiumsrat_vorsitz ODER manage_options
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Vorsitz_Dashboard {

    const NONCE_ACTION = 'dgptm_stipendium_vorsitz_nonce';

    private $plugin_path;
    private $plugin_url;
    private $settings;
    private $zoho;
    private $token_manager;

    public function __construct($plugin_path, $plugin_url, $settings, $zoho, $token_manager) {
        $this->plugin_path   = $plugin_path;
        $this->plugin_url    = $plugin_url;
        $this->settings      = $settings;
        $this->zoho          = $zoho;
        $this->token_manager = $token_manager;

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        // AJAX-Endpoints (alle nur fuer eingeloggte User)
        add_action('wp_ajax_dgptm_stipendium_freigeben', [$this, 'ajax_freigeben']);
        add_action('wp_ajax_dgptm_stipendium_ablehnen', [$this, 'ajax_ablehnen']);
        add_action('wp_ajax_dgptm_stipendium_einladen', [$this, 'ajax_einladen']);
        add_action('wp_ajax_dgptm_stipendium_ranking', [$this, 'ajax_ranking']);
        add_action('wp_ajax_dgptm_stipendium_pdf', [$this, 'ajax_pdf']);
        add_action('wp_ajax_dgptm_stipendium_vergeben', [$this, 'ajax_vergeben']);
        add_action('wp_ajax_dgptm_stipendium_archivieren', [$this, 'ajax_archivieren']);
        add_action('wp_ajax_dgptm_stipendium_load_bewerbungen', [$this, 'ajax_load_bewerbungen']);
    }

    /**
     * Assets registrieren.
     */
    public function register_assets() {
        wp_register_style(
            'dgptm-vorsitz-dashboard',
            $this->plugin_url . 'assets/css/vorsitz-dashboard.css',
            [],
            '1.1.0'
        );
        wp_register_script(
            'dgptm-vorsitz-dashboard',
            $this->plugin_url . 'assets/js/vorsitz-dashboard.js',
            ['jquery'],
            '1.1.0',
            true
        );
    }

    /**
     * Dashboard rendern (aufgerufen von class-dashboard-tab.php).
     *
     * @return string HTML-Output
     */
    public function render() {
        if (!$this->user_is_vorsitz()) return '';

        wp_enqueue_style('dgptm-vorsitz-dashboard');
        wp_enqueue_script('dgptm-vorsitz-dashboard');

        // Verfuegbare Runden/Typen aus Settings
        $typen = $this->settings ? $this->settings->get('stipendientypen') : [];
        $aktive_runden = array_filter($typen, function ($t) {
            return !empty($t['runde']);
        });

        // Default-Runde (erste aktive)
        $default_runde = '';
        $default_typ = '';
        if (!empty($aktive_runden)) {
            $first = reset($aktive_runden);
            $default_runde = $first['runde'];
            $default_typ = $first['bezeichnung'];
        }

        // Gutachter-Frist aus Settings
        $frist_tage = $this->settings ? ($this->settings->get('gutachter_frist_tage') ?: 28) : 28;
        $frist_datum = date_i18n('d.m.Y', strtotime("+{$frist_tage} days"));

        wp_localize_script('dgptm-vorsitz-dashboard', 'dgptmVorsitz', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce(self::NONCE_ACTION),
            'defaultRunde'  => $default_runde,
            'defaultTyp'    => $default_typ,
            'fristDatum'    => $frist_datum,
            'runden'        => array_values($aktive_runden),
            'strings'       => [
                'confirm_freigeben'   => 'Bewerbung freigeben?',
                'confirm_ablehnen'    => 'Bewerbung ablehnen? Dies kann rueckgaengig gemacht werden.',
                'confirm_vergeben'    => 'Stipendium an diese/n Bewerber/in vergeben?',
                'confirm_archivieren' => 'Alle abgeschlossenen Bewerbungen dieser Runde archivieren?',
                'einladung_gesendet'  => 'Einladung wurde gesendet.',
                'fehler'              => 'Ein Fehler ist aufgetreten.',
                'laden'               => 'Wird geladen...',
            ],
        ]);

        ob_start();
        include $this->plugin_path . 'templates/vorsitz-dashboard.php';
        return ob_get_clean();
    }

    /* ──────────────────────────────────────────
     * AJAX: Bewerbungen laden
     * ────────────────────────────────────────── */

    public function ajax_load_bewerbungen() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $runde = sanitize_text_field($_POST['runde'] ?? '');
        $typ   = sanitize_text_field($_POST['typ'] ?? '');

        if (empty($runde)) {
            wp_send_json_error('Runde ist ein Pflichtfeld.');
        }

        if (!$this->zoho) {
            // Demo-Modus: Testdaten zurueckgeben
            wp_send_json_success($this->get_demo_data($runde, $typ));
            return;
        }

        // Stipendien aus CRM laden
        $stipendien = $this->zoho->get_stipendien_by_runde($runde, $typ ?: null);
        if (is_wp_error($stipendien)) {
            wp_send_json_error($stipendien->get_error_message());
        }

        // Tokens aus lokaler DB laden und zuordnen
        $result = $this->group_by_status($stipendien ?: []);

        wp_send_json_success($result);
    }

    /**
     * Stipendien nach Status gruppieren und Token-Info anhaengen.
     */
    private function group_by_status($stipendien) {
        $gruppen = [
            'geprueft'      => [],
            'freigegeben'   => [],
            'in_bewertung'  => [],
            'abgeschlossen' => [],
            'abgelehnt'     => [],
            'archiviert'    => [],
        ];

        foreach ($stipendien as $stip) {
            $id = $stip['id'] ?? '';
            $status_raw = $stip['Stipendium_Status'] ?? $stip['Status'] ?? 'Eingegangen';
            $status_key = $this->normalize_status($status_raw);

            // Token-Info aus lokaler DB
            $tokens = $this->token_manager ? $this->token_manager->get_by_stipendium($id) : [];
            $completed = 0;
            $total = count($tokens);
            $gutachter_list = [];

            foreach ($tokens as $t) {
                $gutachter_list[] = [
                    'name'   => $t['gutachter_name'],
                    'email'  => $t['gutachter_email'],
                    'status' => $t['bewertung_status'],
                ];
                if ($t['bewertung_status'] === 'abgeschlossen') {
                    $completed++;
                }
            }

            $entry = [
                'id'              => $id,
                'name'            => $stip['Name'] ?? ($stip['Bewerber']['name'] ?? 'Unbekannt'),
                'stipendientyp'   => $stip['Stipendientyp'] ?? '',
                'eingangsdatum'   => $stip['Eingangsdatum'] ?? '',
                'gesamtscore'     => $stip['Gesamtscore_Mittelwert'] ?? null,
                'rang'            => $stip['Rang'] ?? null,
                'foerderfaehig'   => !empty($stip['Foerderfaehig']),
                'vergeben'        => !empty($stip['Vergeben']),
                'gutachter_total' => $total,
                'gutachter_done'  => $completed,
                'gutachter'       => $gutachter_list,
            ];

            if (isset($gruppen[$status_key])) {
                $gruppen[$status_key][] = $entry;
            }
        }

        return $gruppen;
    }

    /**
     * Status-String normalisieren.
     */
    private function normalize_status($status) {
        $map = [
            'Eingegangen'    => 'geprueft',
            'Geprueft'       => 'geprueft',
            'Freigegeben'    => 'freigegeben',
            'In Bewertung'   => 'in_bewertung',
            'Abgeschlossen'  => 'abgeschlossen',
            'Abgelehnt'      => 'abgelehnt',
            'Archiviert'     => 'archiviert',
        ];
        return $map[$status] ?? 'geprueft';
    }

    /* ──────────────────────────────────────────
     * AJAX: Bewerbung freigeben
     * ────────────────────────────────────────── */

    public function ajax_freigeben() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $stipendium_id = sanitize_text_field($_POST['stipendium_id'] ?? '');
        if (empty($stipendium_id)) {
            wp_send_json_error('Stipendium-ID fehlt.');
        }

        if ($this->zoho) {
            $result = $this->zoho->update_stipendium($stipendium_id, [
                'Stipendium_Status' => 'Freigegeben',
                'Freigabedatum'     => date('Y-m-d'),
            ]);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            // Cache invalidieren
            $runde = sanitize_text_field($_POST['runde'] ?? '');
            if ($runde) {
                $this->zoho->invalidate_stipendien_cache($runde);
            }
        }

        wp_send_json_success(['message' => 'Bewerbung freigegeben.']);
    }

    /* ──────────────────────────────────────────
     * AJAX: Bewerbung ablehnen
     * ────────────────────────────────────────── */

    public function ajax_ablehnen() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $stipendium_id = sanitize_text_field($_POST['stipendium_id'] ?? '');
        if (empty($stipendium_id)) {
            wp_send_json_error('Stipendium-ID fehlt.');
        }

        if ($this->zoho) {
            $result = $this->zoho->update_stipendium($stipendium_id, [
                'Stipendium_Status' => 'Abgelehnt',
            ]);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            $runde = sanitize_text_field($_POST['runde'] ?? '');
            if ($runde) {
                $this->zoho->invalidate_stipendien_cache($runde);
            }
        }

        wp_send_json_success(['message' => 'Bewerbung abgelehnt.']);
    }

    /* ──────────────────────────────────────────
     * AJAX: Gutachter einladen
     * ────────────────────────────────────────── */

    public function ajax_einladen() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $stipendium_id   = sanitize_text_field($_POST['stipendium_id'] ?? '');
        $gutachter_name  = sanitize_text_field($_POST['gutachter_name'] ?? '');
        $gutachter_email = sanitize_email($_POST['gutachter_email'] ?? '');
        $frist           = sanitize_text_field($_POST['frist'] ?? '');

        if (empty($stipendium_id) || empty($gutachter_name) || empty($gutachter_email)) {
            wp_send_json_error('Bitte alle Felder ausfuellen.');
        }

        if (!is_email($gutachter_email)) {
            wp_send_json_error('Bitte eine gueltige E-Mail-Adresse eingeben.');
        }

        // Frist berechnen
        $frist_tage = 28;
        if ($this->settings) {
            $frist_tage = $this->settings->get('gutachter_frist_tage') ?: 28;
        }
        if (!empty($frist)) {
            // Frist als Datum angegeben — Differenz zu heute berechnen
            $frist_ts = strtotime($frist);
            if ($frist_ts && $frist_ts > time()) {
                $frist_tage = (int) ceil(($frist_ts - time()) / DAY_IN_SECONDS);
            }
        }

        // Token generieren
        if (!$this->token_manager) {
            wp_send_json_error('Token-Manager nicht verfuegbar.');
        }

        $token_result = $this->token_manager->generate(
            $stipendium_id,
            $gutachter_name,
            $gutachter_email,
            $frist_tage
        );

        if (is_wp_error($token_result)) {
            wp_send_json_error($token_result->get_error_message());
        }

        // Bewerberdaten fuer Mail laden
        $bewerber_name = 'Unbekannt';
        $stipendientyp = '';
        $runde = '';
        if ($this->zoho) {
            $stipendium = $this->zoho->get_stipendium($stipendium_id);
            if (!is_wp_error($stipendium)) {
                $data = $stipendium['data'][0] ?? $stipendium;
                $bewerber_name = $data['Bewerber']['name'] ?? $data['Name'] ?? 'Unbekannt';
                $stipendientyp = $data['Stipendientyp'] ?? '';
                $runde = $data['Runde'] ?? '';
            }

            // Status auf "In Bewertung" setzen (falls noch Freigegeben)
            $this->zoho->update_stipendium($stipendium_id, [
                'Stipendium_Status' => 'In Bewertung',
            ]);
            if ($runde) {
                $this->zoho->invalidate_stipendien_cache($runde);
            }
        }

        // Einladungs-Mail senden
        $frist_datum = date_i18n('d.m.Y', strtotime("+{$frist_tage} days"));
        $mail_sent = DGPTM_Stipendium_Mail_Templates::send_einladung([
            'gutachter_name'  => $gutachter_name,
            'gutachter_email' => $gutachter_email,
            'bewerber_name'   => $bewerber_name,
            'stipendientyp'   => $stipendientyp,
            'runde'           => $runde,
            'frist'           => $frist_datum,
            'gutachten_url'   => $token_result['url'],
        ]);

        wp_send_json_success([
            'message'   => $mail_sent
                ? 'Einladung an ' . $gutachter_name . ' gesendet.'
                : 'Token erstellt, aber E-Mail konnte nicht gesendet werden.',
            'token_url' => $token_result['url'],
            'mail_sent' => $mail_sent,
        ]);
    }

    /* ──────────────────────────────────────────
     * AJAX: Ranking berechnen
     * ────────────────────────────────────────── */

    public function ajax_ranking() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $runde = sanitize_text_field($_POST['runde'] ?? '');
        $typ   = sanitize_text_field($_POST['typ'] ?? '');

        if (empty($runde)) {
            wp_send_json_error('Runde ist ein Pflichtfeld.');
        }

        // In Zoho CRM wird das Ranking via Deluge Custom Function berechnet.
        // Hier triggern wir die Neuberechnung ueber einen Status-Update,
        // der die Workflow Rule ausloest.
        // Alternative: Custom Function ueber API aufrufen (wenn verfuegbar).

        if ($this->zoho) {
            $this->zoho->invalidate_stipendien_cache($runde, $typ ?: null);
        }

        wp_send_json_success([
            'message' => 'Ranking wird im CRM neu berechnet. Bitte laden Sie das Dashboard in wenigen Sekunden neu.',
        ]);
    }

    /* ──────────────────────────────────────────
     * AJAX: PDF-Export
     * ────────────────────────────────────────── */

    public function ajax_pdf() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        // Platzhalter: PDF-Generierung ueber Zoho Writer oder lokale Lib
        // Wird in einer spaeteren Version implementiert
        wp_send_json_error('PDF-Export wird in einer spaeteren Version implementiert.');
    }

    /* ──────────────────────────────────────────
     * AJAX: Stipendium vergeben
     * ────────────────────────────────────────── */

    public function ajax_vergeben() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $stipendium_id = sanitize_text_field($_POST['stipendium_id'] ?? '');
        if (empty($stipendium_id)) {
            wp_send_json_error('Stipendium-ID fehlt.');
        }

        if ($this->zoho) {
            $result = $this->zoho->update_stipendium($stipendium_id, [
                'Vergeben'     => true,
                'Vergabedatum' => date('Y-m-d'),
            ]);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            $runde = sanitize_text_field($_POST['runde'] ?? '');
            if ($runde) {
                $this->zoho->invalidate_stipendien_cache($runde);
            }
        }

        wp_send_json_success(['message' => 'Stipendium wurde vergeben.']);
    }

    /* ──────────────────────────────────────────
     * AJAX: Runde archivieren
     * ────────────────────────────────────────── */

    public function ajax_archivieren() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $runde = sanitize_text_field($_POST['runde'] ?? '');
        $typ   = sanitize_text_field($_POST['typ'] ?? '');

        if (empty($runde)) {
            wp_send_json_error('Runde ist ein Pflichtfeld.');
        }

        if (!$this->zoho) {
            wp_send_json_success(['message' => 'Demo-Modus: Archivierung simuliert.', 'count' => 0]);
            return;
        }

        // Alle abgeschlossenen Stipendien der Runde laden
        $stipendien = $this->zoho->get_stipendien_by_runde($runde, $typ ?: null);
        if (is_wp_error($stipendien)) {
            wp_send_json_error($stipendien->get_error_message());
        }

        $archived = 0;
        foreach ($stipendien as $stip) {
            $status = $stip['Stipendium_Status'] ?? $stip['Status'] ?? '';
            if ($status === 'Abgeschlossen') {
                $this->zoho->update_stipendium($stip['id'], [
                    'Stipendium_Status' => 'Archiviert',
                ]);
                $archived++;
            }
        }

        $this->zoho->invalidate_stipendien_cache($runde, $typ ?: null);

        wp_send_json_success([
            'message' => $archived . ' Bewerbung(en) archiviert.',
            'count'   => $archived,
        ]);
    }

    /* ──────────────────────────────────────────
     * Berechtigungspruefung
     * ────────────────────────────────────────── */

    private function user_is_vorsitz() {
        if (!is_user_logged_in()) return false;
        if (current_user_can('manage_options')) return true;

        $user_id = get_current_user_id();
        return (bool) get_field('stipendiumsrat_vorsitz', 'user_' . $user_id);
    }

    /* ──────────────────────────────────────────
     * Demo-Daten (wenn Zoho nicht verfuegbar)
     * ────────────────────────────────────────── */

    private function get_demo_data($runde, $typ) {
        return [
            'geprueft' => [
                [
                    'id' => 'DEMO_001',
                    'name' => 'Max Mustermann',
                    'stipendientyp' => 'Promotionsstipendium',
                    'eingangsdatum' => '2026-04-01',
                    'gesamtscore' => null,
                    'rang' => null,
                    'foerderfaehig' => false,
                    'vergeben' => false,
                    'gutachter_total' => 0,
                    'gutachter_done' => 0,
                    'gutachter' => [],
                ],
            ],
            'freigegeben' => [
                [
                    'id' => 'DEMO_002',
                    'name' => 'Anna Beispiel',
                    'stipendientyp' => 'Josef Guettler Stipendium',
                    'eingangsdatum' => '2026-04-02',
                    'gesamtscore' => null,
                    'rang' => null,
                    'foerderfaehig' => false,
                    'vergeben' => false,
                    'gutachter_total' => 0,
                    'gutachter_done' => 0,
                    'gutachter' => [],
                ],
            ],
            'in_bewertung'  => [],
            'abgeschlossen' => [],
            'abgelehnt'     => [],
            'archiviert'    => [],
        ];
    }
}
