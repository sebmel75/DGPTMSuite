<?php
/**
 * Finanzen Konfiguration
 *
 * Laedt Konfiguration aus wp_options (importiert aus config.json).
 * Enthaelt Zoho CRM/Books Credentials, GoCardless Token,
 * Mitgliedstypen, Rechnungsvarianten, CRM-Feldnamen, Blueprints,
 * Bank-Kontodaten und Books-Einstellungen.
 */

if (!defined('ABSPATH')) exit;

class DGPTM_FIN_Config {

    private array $data;

    private function __construct(array $data) {
        $this->data = $data;
    }

    public static function load(): self {
        $data = get_option(DGPTM_Finanzen::OPT_CONFIG, []);
        return new self(is_array($data) ? $data : []);
    }

    public function is_valid(): bool {
        return !empty($this->get('zoho.client.client_id'))
            && !empty($this->get('zoho.client.client_secret'))
            && !empty($this->get('zoho.client.refresh_token'))
            && !empty($this->get('zoho.organization_id'))
            && !empty($this->get('gocardless.access_token'));
    }

    /**
     * Dot-Notation getter: $config->get('zoho.client.client_id')
     */
    public function get(string $key, $default = null) {
        $parts = explode('.', $key);
        $val = $this->data;
        foreach ($parts as $part) {
            if (!is_array($val) || !isset($val[$part])) {
                return $default;
            }
            $val = $val[$part];
        }
        return $val;
    }

    public function all(): array {
        return $this->data;
    }

    /* ============================================================ */
    /* Convenience Accessors                                         */
    /* ============================================================ */

    public function zoho_accounts_domain(): string {
        return $this->get('zoho.accounts_domain', 'https://accounts.zoho.eu');
    }

    public function zoho_api_domain(): string {
        return $this->get('zoho.api_domain', 'https://www.zohoapis.eu');
    }

    public function zoho_client_id(): string {
        return $this->get('zoho.client.client_id', '');
    }

    public function zoho_client_secret(): string {
        return $this->get('zoho.client.client_secret', '');
    }

    public function zoho_refresh_token(): string {
        return $this->get('zoho.client.refresh_token', '');
    }

    public function zoho_org_id(): string {
        return $this->get('zoho.organization_id', '');
    }

    public function zoho_crm_version(): string {
        return $this->get('zoho.crm_api_version', 'v8');
    }

    public function zoho_books_version(): string {
        return $this->get('zoho.books_api_version', 'v3');
    }

    public function gc_token(): string {
        return $this->get('gocardless.access_token', '');
    }

    public function gc_api_url(): string {
        return $this->get('gocardless.api_url', 'https://api.gocardless.com');
    }

    public function student_fee(): float {
        return (float) $this->get('zoho.student_fee', 10.0);
    }

    public function membership_types(): array {
        return $this->get('membership_types', []);
    }

    public function invoice_variants(): array {
        return $this->get('invoice_variants', []);
    }

    public function crm_field(string $group, string $field, string $default = ''): string {
        return $this->get("crm_fields.{$group}.{$field}", $default);
    }

    public function books_setting(string $key, $default = '') {
        return $this->get("books.{$key}", $default);
    }

    public function allowed_statuses(): array {
        return $this->get('zoho.allowed_contact_statuses', ['Aktiv', 'Freigestellt']);
    }

    /* ============================================================ */
    /* Additional Accessors (Finanzen)                               */
    /* ============================================================ */

    public function books_credentials(): array {
        return $this->get('zoho.books', []);
    }

    public function blueprint(string $name): string {
        return $this->get('zoho.blueprints.' . $name, '');
    }

    public function chargeback_fee(): float {
        return (float) $this->get('books.chargeback_fee', 5.0);
    }

    public function bank_account(): array {
        return $this->get('bank_account', []);
    }
}
