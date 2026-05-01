<?php
/**
 * Books_Status_Reader — Read-only-Anbindung an Zoho Books fuer Drift-Reconciliation.
 *
 * Phase 1 nutzt nur is_invoice_paid(). Schreibzugriff (Rechnungs-Erstellung,
 * Zahlungseingang verbuchen) folgt in Phase 7.
 *
 * Konfiguration via WP-Option / Filter:
 *   - dgptm_wsb_books_org_id (Zoho-Books-Organization-ID)
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Books_Status_Reader {

    /**
     * Prueft, ob eine Zoho-Books-Rechnung als bezahlt gilt.
     *
     * @param string $invoice_id
     * @return bool|null  true=bezahlt, false=offen, null=unbekannt/Books nicht erreichbar
     */
    public static function is_invoice_paid($invoice_id) {
        if (empty($invoice_id)) return null;

        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return null;

        $org_id = apply_filters('dgptm_wsb_books_org_id', get_option('dgptm_wsb_books_org_id', ''));
        if (!$org_id) return null;

        $url = 'https://www.zohoapis.eu/books/v3/invoices/'
             . rawurlencode($invoice_id)
             . '?organization_id=' . rawurlencode($org_id);

        $resp = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $status = isset($body['invoice']['status']) ? strtolower($body['invoice']['status']) : null;

        if ($status === 'paid') return true;
        if (in_array($status, ['sent', 'partially_paid', 'overdue', 'unpaid', 'draft'], true)) return false;

        return null;
    }
}
