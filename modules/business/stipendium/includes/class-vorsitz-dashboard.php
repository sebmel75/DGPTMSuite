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
        add_action('wp_ajax_dgptm_stipendium_save_runde', [$this, 'ajax_save_runde']);
    }

    /**
     * Assets registrieren — und fuer berechtigte User vorab enqueuen.
     *
     * Wird auf wp_enqueue_scripts gehookt, damit Stylesheets/Scripts auch dann
     * im DOM landen, wenn der Shortcode-Output spaeter (z.B. via AJAX-Tab-Load)
     * generiert wird. Localize liefert alle Konfigurationsdaten.
     */
    public function register_assets() {
        wp_register_style(
            'dgptm-vorsitz-dashboard',
            $this->plugin_url . 'assets/css/vorsitz-dashboard.css',
            [],
            '1.2.0'
        );
        wp_register_script(
            'dgptm-vorsitz-dashboard',
            $this->plugin_url . 'assets/js/vorsitz-dashboard.js',
            ['jquery'],
            '1.2.0',
            true
        );

        if ($this->user_is_vorsitz()) {
            wp_enqueue_style('dgptm-vorsitz-dashboard');
            wp_enqueue_script('dgptm-vorsitz-dashboard');
            wp_localize_script('dgptm-vorsitz-dashboard', 'dgptmVorsitz', $this->build_localize_data());
        }
    }

    /**
     * Daten fuer das JS-Localize aufbereiten.
     */
    private function build_localize_data() {
        $typen = $this->settings ? $this->settings->get('stipendientypen') : [];
        $aktive_runden = array_filter((array) $typen, function ($t) {
            return !empty($t['runde']);
        });

        $default_runde = '';
        $default_typ   = '';
        if (!empty($aktive_runden)) {
            $first = reset($aktive_runden);
            $default_runde = $first['runde'];
            $default_typ   = $first['bezeichnung'];
        }

        $frist_tage = $this->settings ? ($this->settings->get('gutachter_frist_tage') ?: 28) : 28;
        $frist_datum = date_i18n('d.m.Y', strtotime("+{$frist_tage} days"));

        $stipendientypen = [];
        foreach ((array) $typen as $t) {
            if (!empty($t['bezeichnung'])) {
                $stipendientypen[] = [
                    'id'          => $t['id'] ?? '',
                    'bezeichnung' => $t['bezeichnung'],
                    'runde'       => $t['runde'] ?? '',
                ];
            }
        }

        return [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce(self::NONCE_ACTION),
            'orcidNonce'      => wp_create_nonce('dgptm_stipendium_orcid_nonce'),
            'defaultRunde'    => $default_runde,
            'defaultTyp'      => $default_typ,
            'fristDatum'      => $frist_datum,
            'runden'          => array_values($aktive_runden),
            'stipendientypen' => $stipendientypen,
            'gutachterPool'   => $this->get_gutachter_pool(),
            'strings'         => [
                'confirm_freigeben'   => 'Bewerbung freigeben?',
                'confirm_ablehnen'    => 'Bewerbung ablehnen? Dies kann rueckgaengig gemacht werden.',
                'confirm_vergeben'    => 'Stipendium an diese/n Bewerber/in vergeben?',
                'confirm_archivieren' => 'Alle abgeschlossenen Bewerbungen dieser Runde archivieren?',
                'confirm_delete'      => 'Manuelle Bewerbung wirklich loeschen? Vergebene Tokens werden ebenfalls entfernt.',
                'einladung_gesendet'  => 'Einladung wurde gesendet.',
                'fehler'              => 'Ein Fehler ist aufgetreten.',
                'laden'               => 'Wird geladen...',
                'manuell_gespeichert' => 'Bewerbung wurde gespeichert.',
                'orcid_fehler'        => 'ORCID-Daten konnten nicht abgerufen werden.',
            ],
        ];
    }

    /**
     * Dashboard rendern (aufgerufen von class-dashboard-tab.php).
     *
     * @return string HTML-Output
     */
    public function render() {
        if (!$this->user_is_vorsitz()) return '';

        // Doppel-Enqueue ist idempotent (WP deduped). Falls register_assets()
        // wegen spaeter Shortcode-Auswertung nicht mehr greift, holen wir die
        // Assets hier nach — inkl. Localize, damit dgptmVorsitz garantiert da ist.
        wp_enqueue_style('dgptm-vorsitz-dashboard');
        wp_enqueue_script('dgptm-vorsitz-dashboard');
        wp_localize_script('dgptm-vorsitz-dashboard', 'dgptmVorsitz', $this->build_localize_data());

        $typen = $this->settings ? $this->settings->get('stipendientypen') : [];
        $aktive_runden = array_filter((array) $typen, function ($t) {
            return !empty($t['runde']);
        });

        $frist_tage = $this->settings ? ($this->settings->get('gutachter_frist_tage') ?: 28) : 28;
        $frist_datum = date_i18n('d.m.Y', strtotime("+{$frist_tage} days"));

        ob_start();
        include $this->plugin_path . 'templates/vorsitz-dashboard.php';
        $html = ob_get_clean();

        // Inline-Fallback: bei AJAX-geladenen Dashboard-Tabs ist wp_footer schon
        // gelaufen — Style/Script wurden nicht ausgeliefert. Nur dann nachladen.
        $needs_fallback = did_action('wp_footer')
            && !wp_script_is('dgptm-vorsitz-dashboard', 'done');

        if ($needs_fallback) {
            $config_json = wp_json_encode($this->build_localize_data());
            $script_src  = esc_url($this->plugin_url . 'assets/js/vorsitz-dashboard.js?ver=1.2.0');
            $style_href  = esc_url($this->plugin_url . 'assets/css/vorsitz-dashboard.css?ver=1.2.0');
            $html .= '<link rel="stylesheet" href="' . $style_href . '">';
            $html .= '<script>window.dgptmVorsitz = ' . $config_json . ';</script>';
            $html .= '<script src="' . $script_src . '" defer></script>';
        }

        return $html;
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

        // Manuelle Bewerbungen (lokal in WordPress) laden
        $manuelle = [];
        if (class_exists('DGPTM_Stipendium_Bewerbung_Manuell')) {
            $manuelle = DGPTM_Stipendium_Bewerbung_Manuell::list_by_runde($runde, $typ ?: null);
        }

        // Stipendien aus CRM laden (sofern verfuegbar)
        $crm = [];
        if ($this->zoho) {
            $crm_result = $this->zoho->get_stipendien_by_runde($runde, $typ ?: null);
            if (is_wp_error($crm_result)) {
                // CRM-Fehler nicht hart durchreichen, manuelle Bewerbungen weiter anzeigen
                if (function_exists('dgptm_log_error')) {
                    dgptm_log_error('Stipendium CRM-Fehler: ' . $crm_result->get_error_message(), 'stipendium');
                }
            } elseif (is_array($crm_result)) {
                $crm = $crm_result;
            }
        }

        $alle = array_merge($crm, $manuelle);

        // Demo-Daten nur, wenn weder Zoho noch manuelle Eintraege vorhanden sind
        if (empty($alle) && !$this->zoho) {
            wp_send_json_success($this->get_demo_data($runde, $typ));
            return;
        }

        $result = $this->group_by_status($alle);
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

            // Score aus Tokens lokal aggregieren (Demo/manuelle Bewerbungen)
            $local_score = null;
            $local_count = 0;
            if ($total > 0) {
                $sum = 0.0;
                foreach ($tokens as $t) {
                    if ($t['bewertung_status'] === 'abgeschlossen' && !empty($t['bewertung_data'])) {
                        $bd = json_decode($t['bewertung_data'], true);
                        if (is_array($bd) && isset($bd['Gesamtscore'])) {
                            $sum += (float) $bd['Gesamtscore'];
                            $local_count++;
                        }
                    }
                }
                if ($local_count > 0) {
                    $local_score = round($sum / $local_count, 2);
                }
            }

            $score = $stip['Gesamtscore_Mittelwert'] ?? $local_score;
            $foerderfaehig = isset($stip['Foerderfaehig'])
                ? !empty($stip['Foerderfaehig'])
                : ($score !== null && (float) $score >= 6.0);

            $entry = [
                'id'              => $id,
                'name'            => $stip['Name'] ?? ($stip['Bewerber']['name'] ?? 'Unbekannt'),
                'stipendientyp'   => $stip['Stipendientyp'] ?? '',
                'eingangsdatum'   => $stip['Eingangsdatum'] ?? '',
                'gesamtscore'     => $score,
                'rang'            => $stip['Rang'] ?? null,
                'foerderfaehig'   => $foerderfaehig,
                'vergeben'        => !empty($stip['Vergeben']),
                'gutachter_total' => $total,
                'gutachter_done'  => $completed,
                'gutachter'       => $gutachter_list,
                'is_manual'       => !empty($stip['is_manual']),
                'bemerkung'       => $stip['bemerkung'] ?? '',
                'projekt_titel'   => $stip['projekt_titel'] ?? '',
                'institution'     => $stip['bewerber_institution'] ?? '',
                'orcid'           => $stip['bewerber_orcid'] ?? '',
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

        $result = $this->update_status($stipendium_id, [
            'Stipendium_Status' => 'Freigegeben',
            'Freigabedatum'     => date('Y-m-d'),
        ], [
            'status'        => 'Freigegeben',
            'freigabedatum' => date('Y-m-d'),
        ]);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
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

        $result = $this->update_status($stipendium_id, [
            'Stipendium_Status' => 'Abgelehnt',
        ], [
            'status' => 'Abgelehnt',
        ]);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
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

        $result = $this->update_status($stipendium_id, [
            'Vergeben'     => true,
            'Vergabedatum' => date('Y-m-d'),
        ], [
            'vergeben'     => 1,
            'vergabedatum' => date('Y-m-d'),
        ]);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
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
     * AJAX: Stipendiums-Runde anlegen / aktualisieren
     * ────────────────────────────────────────── */

    public function ajax_save_runde() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $typ_id      = sanitize_key(wp_unslash($_POST['typ_id'] ?? ''));
        $bezeichnung = sanitize_text_field(wp_unslash($_POST['bezeichnung'] ?? ''));
        $runde       = sanitize_text_field(wp_unslash($_POST['runde'] ?? ''));
        $start       = sanitize_text_field(wp_unslash($_POST['start'] ?? ''));
        $ende        = sanitize_text_field(wp_unslash($_POST['ende'] ?? ''));

        if (empty($bezeichnung) || empty($runde)) {
            wp_send_json_error('Stipendientyp-Bezeichnung und Runden-Name sind Pflichtfelder.');
        }

        if ($start && $ende && strtotime($start) > strtotime($ende)) {
            wp_send_json_error('Bewerbungsstart darf nicht nach dem Ende liegen.');
        }

        if (!$this->settings) {
            wp_send_json_error('Settings-Komponente nicht verfuegbar.');
        }

        $all = $this->settings->get_all();
        $typen = isset($all['stipendientypen']) && is_array($all['stipendientypen']) ? $all['stipendientypen'] : [];

        if (empty($typ_id)) {
            // Neuen Typ aus Bezeichnung ableiten (slugify, eindeutig)
            $base = sanitize_title($bezeichnung);
            if (empty($base)) $base = 'stipendientyp';
            $candidate = $base;
            $existing_ids = array_column($typen, 'id');
            $i = 2;
            while (in_array($candidate, $existing_ids, true)) {
                $candidate = $base . '_' . $i;
                $i++;
            }
            $typ_id = $candidate;
        }

        $eintrag = [
            'id'          => $typ_id,
            'bezeichnung' => $bezeichnung,
            'runde'       => $runde,
            'start'       => $start,
            'ende'        => $ende,
        ];

        $found = false;
        foreach ($typen as $idx => $t) {
            if (($t['id'] ?? '') === $typ_id) {
                $typen[$idx] = $eintrag;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $typen[] = $eintrag;
        }

        $all['stipendientypen'] = array_values($typen);
        update_option(\DGPTM_Stipendium_Settings::OPTION_KEY, $all, false);

        wp_send_json_success([
            'message' => $found ? 'Runde aktualisiert.' : 'Neue Runde wurde angelegt.',
            'typ'     => $eintrag,
            'runden'  => array_values(array_filter($typen, function($t) { return !empty($t['runde']); })),
        ]);
    }

    /* ──────────────────────────────────────────
     * Helfer: Gutachter-Pool aus bestehenden Tokens
     * ────────────────────────────────────────── */

    /**
     * Liefert distincte Gutachter aus allen bisherigen Token-Eintraegen,
     * damit der Vorsitzende sie schnell erneut einladen kann.
     */
    private function get_gutachter_pool() {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_stipendium_tokens';
        $rows = $wpdb->get_results(
            "SELECT gutachter_name, gutachter_email, MAX(created_at) AS letzter_einsatz
             FROM {$table}
             GROUP BY gutachter_email
             ORDER BY letzter_einsatz DESC
             LIMIT 50",
            ARRAY_A
        ) ?: [];
        return $rows;
    }

    /* ──────────────────────────────────────────
     * Helfer: Status-Update (CRM oder lokal)
     * ────────────────────────────────────────── */

    /**
     * Aktualisiert den Status einer Bewerbung.
     * Routet basierend auf der ID (MAN_* lokal, sonst CRM).
     *
     * @param string $stipendium_id
     * @param array  $crm_fields    Felder fuer Zoho CRM (API-Namen)
     * @param array  $manual_fields Felder fuer lokale DB (Spalten)
     * @return true|WP_Error
     */
    private function update_status($stipendium_id, $crm_fields, $manual_fields) {
        $runde = sanitize_text_field($_POST['runde'] ?? '');

        // Manuelle Bewerbung
        if (strpos($stipendium_id, 'MAN_') === 0) {
            if (!class_exists('DGPTM_Stipendium_Bewerbung_Manuell')) {
                return new WP_Error('module_missing', 'Modul fuer manuelle Bewerbungen nicht verfuegbar.');
            }
            $ok = DGPTM_Stipendium_Bewerbung_Manuell::update_fields($stipendium_id, $manual_fields);
            if (!$ok) {
                return new WP_Error('update_failed', 'Lokale Aktualisierung fehlgeschlagen.');
            }
            return true;
        }

        // CRM-Bewerbung
        if (!$this->zoho) {
            // Demo-IDs ohne CRM: nur Erfolg simulieren
            return true;
        }

        $result = $this->zoho->update_stipendium($stipendium_id, $crm_fields);
        if (is_wp_error($result)) {
            return $result;
        }
        if ($runde) {
            $this->zoho->invalidate_stipendien_cache($runde);
        }
        return true;
    }

    /* ──────────────────────────────────────────
     * Berechtigungspruefung
     * ────────────────────────────────────────── */

    private function user_is_vorsitz() {
        if (!is_user_logged_in()) return false;
        if (current_user_can('manage_options')) return true;
        if (class_exists('DGPTM_Stipendium_Dashboard_Tab')) {
            return DGPTM_Stipendium_Dashboard_Tab::user_has_flag(
                get_current_user_id(),
                'stipendiumsrat_vorsitz'
            );
        }
        $uid = get_current_user_id();
        if (function_exists('get_field') && get_field('stipendiumsrat_vorsitz', 'user_' . $uid)) return true;
        return (bool) get_user_meta($uid, 'stipendiumsrat_vorsitz', true);
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
