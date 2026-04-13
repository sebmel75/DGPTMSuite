<?php
/**
 * DGPTM Stipendium — ORCID Public API Lookup.
 *
 * Fragt die oeffentliche ORCID API v3.0 ab, um Bewerberdaten
 * (Name, Institution, E-Mail) automatisch auszufuellen.
 * Kein API-Key erforderlich.
 *
 * Referenz: DGPTM_Artikel_Einreichung::ajax_lookup_orcid()
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_ORCID_Lookup {

    const NONCE_ACTION = 'dgptm_stipendium_orcid_nonce';
    const ORCID_API_BASE = 'https://pub.orcid.org/v3.0/';

    public function __construct() {
        // Fuer eingeloggte und nicht-eingeloggte Benutzer
        add_action('wp_ajax_dgptm_stipendium_lookup_orcid', [$this, 'ajax_lookup']);
        add_action('wp_ajax_nopriv_dgptm_stipendium_lookup_orcid', [$this, 'ajax_lookup']);
    }

    /**
     * AJAX: ORCID-Daten von der oeffentlichen API abrufen.
     *
     * Erwartet POST-Parameter:
     * - nonce: dgptm_stipendium_orcid_nonce
     * - orcid: ORCID-ID im Format 0000-0000-0000-0000
     *
     * Gibt zurueck: Vorname, Nachname, Institution, E-Mail
     */
    public function ajax_lookup() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $orcid = sanitize_text_field($_POST['orcid'] ?? '');

        // ORCID-Format validieren (4 Gruppen a 4 Ziffern, letzte kann X sein)
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
            wp_send_json_error([
                'message' => 'Ungueltiges ORCID-Format. Bitte verwenden Sie das Format: 0000-0000-0000-0000',
            ]);
        }

        // 1. Personendaten abrufen
        $person_url = self::ORCID_API_BASE . $orcid . '/person';
        $person_response = wp_remote_get($person_url, [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 10,
        ]);

        if (is_wp_error($person_response)) {
            wp_send_json_error([
                'message' => 'Verbindungsfehler zur ORCID-API. Bitte versuchen Sie es spaeter erneut.',
            ]);
        }

        $status_code = wp_remote_retrieve_response_code($person_response);

        if ($status_code === 404) {
            wp_send_json_error([
                'message' => 'Diese ORCID-ID wurde nicht gefunden. Bitte ueberpruefen Sie die Eingabe.',
            ]);
        }

        if ($status_code !== 200) {
            wp_send_json_error([
                'message' => 'Fehler beim Abrufen der ORCID-Daten (Status: ' . $status_code . ').',
            ]);
        }

        $person_data = json_decode(wp_remote_retrieve_body($person_response), true);

        if (!$person_data) {
            wp_send_json_error([
                'message' => 'Ungueltige Antwort von der ORCID-API.',
            ]);
        }

        // Name extrahieren
        $given_name  = '';
        $family_name = '';
        if (isset($person_data['name'])) {
            $given_name  = $person_data['name']['given-names']['value'] ?? '';
            $family_name = $person_data['name']['family-name']['value'] ?? '';
        }

        // E-Mail extrahieren (falls oeffentlich)
        $email = '';
        if (isset($person_data['emails']['email']) && !empty($person_data['emails']['email'])) {
            foreach ($person_data['emails']['email'] as $email_entry) {
                if (!empty($email_entry['email'])) {
                    $email = $email_entry['email'];
                    break;
                }
            }
        }

        // 2. Beschaeftigung/Institution abrufen
        $institution = '';
        $emp_url = self::ORCID_API_BASE . $orcid . '/employments';
        $emp_response = wp_remote_get($emp_url, [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 10,
        ]);

        if (!is_wp_error($emp_response) && wp_remote_retrieve_response_code($emp_response) === 200) {
            $emp_data = json_decode(wp_remote_retrieve_body($emp_response), true);

            if (isset($emp_data['affiliation-group']) && !empty($emp_data['affiliation-group'])) {
                foreach ($emp_data['affiliation-group'] as $group) {
                    if (isset($group['summaries'][0]['employment-summary'])) {
                        $emp = $group['summaries'][0]['employment-summary'];
                        $institution = $emp['organization']['name'] ?? '';
                        if ($institution) break;
                    }
                }
            }
        }

        // Ergebnis pruefen — mindestens Name sollte vorhanden sein
        $full_name = trim($given_name . ' ' . $family_name);

        if (empty($full_name)) {
            wp_send_json_error([
                'message' => 'Der Name ist bei diesem ORCID-Profil nicht oeffentlich sichtbar. Bitte geben Sie Ihren Namen manuell ein.',
                'partial' => true,
                'data'    => [
                    'orcid'       => $orcid,
                    'email'       => $email,
                    'institution' => $institution,
                ],
            ]);
        }

        wp_send_json_success([
            'message' => 'ORCID-Daten erfolgreich abgerufen.',
            'data'    => [
                'orcid'       => $orcid,
                'vorname'     => $given_name,
                'nachname'    => $family_name,
                'name'        => $full_name,
                'email'       => $email,
                'institution' => $institution,
            ],
        ]);
    }

    /**
     * Nonce fuer Frontend-Nutzung erzeugen.
     *
     * @return string Nonce-Wert
     */
    public static function create_nonce() {
        return wp_create_nonce(self::NONCE_ACTION);
    }
}
