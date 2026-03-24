<?php
/**
 * Mitglieder-Liste fuer den Finanzen-Tab.
 *
 * Laedt Mitglieder aus Zoho CRM und reichert sie mit
 * Billing-Fehlern aus dem letzten Beitragslauf an.
 *
 * @package DGPTM_Finanzen
 */

if (!defined('ABSPATH')) exit;

class DGPTM_FIN_Member_List {

    private DGPTM_FIN_Config $config;

    public function __construct(DGPTM_FIN_Config $config) {
        $this->config = $config;
    }

    /**
     * Mitglieder fuer ein Beitragsjahr laden und anreichern.
     *
     * @param int   $year    Beitragsjahr
     * @param array $filters Optional: type, status, billing_status
     * @return array Angereicherte Mitglieder-Liste
     */
    public function get_members(int $year, array $filters = []): array {
        $crm = new DGPTM_FIN_Zoho_CRM($this->config);
        $members = $crm->get_member_list($filters);

        if (empty($members)) {
            return [];
        }

        $errors = $this->get_billing_errors($year);
        $fees   = $crm->get_all_fees();

        foreach ($members as &$member) {
            $contact_id = $member['id'] ?? '';
            $type       = $member['Membership_Type'] ?? '';

            // Beitragsstatus anreichern
            $last_billed = $member['letztesBeitragsjahr'] ?? null;
            $member['_billing_status'] = 'pending';
            if ($last_billed !== null && (int) $last_billed >= $year) {
                $member['_billing_status'] = 'billed';
            } elseif ($last_billed === null) {
                $member['_billing_status'] = 'never';
            }

            // Gebuehr anreichern
            $member['_fee'] = $fees[$type] ?? 0.0;

            // Guthaben
            $member['_credit'] = (float) ($member['Guthaben2'] ?? 0);

            // Billing-Fehler anreichern
            $member['_errors'] = $errors[$contact_id] ?? [];

            // GoCardless-Status
            $member['_has_mandate'] = !empty($member['MandatID']);
        }
        unset($member);

        return $members;
    }

    /**
     * Billing-Fehler aus dem letzten Beitragslauf extrahieren.
     *
     * Laedt die gespeicherten Ergebnisse und baut eine Map:
     * contact_id => array von Fehler-Strings.
     *
     * @param int $year Beitragsjahr
     * @return array Map: contact_id => [error1, error2, ...]
     */
    public function get_billing_errors(int $year): array {
        $results = get_option('dgptm_fin_last_results', []);
        $error_map = [];

        if (empty($results) || !is_array($results)) {
            return $error_map;
        }

        foreach ($results as $entry) {
            if (empty($entry['errors']) || !is_array($entry['errors'])) {
                continue;
            }

            $contact_id = $entry['contact_id'] ?? '';
            if (empty($contact_id)) {
                continue;
            }

            $error_map[$contact_id] = $entry['errors'];
        }

        return $error_map;
    }
}
