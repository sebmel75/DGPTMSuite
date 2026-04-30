# Zoho Desk Cockpit — Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Neues DGPTMSuite-Modul `zoho-desk-cockpit`, das eingeloggten autorisierten Mitgliedern Zoho-Desk-Tickets mit Blueprint-Status und Kommentaren via Shortcode `[dgptm_desk_cockpit]` anzeigt. Whitelist/Blacklist-Filter und Mitglieder-Berechtigung im Admin.

**Architecture:** Singleton-WordPress-Modul mit eigenständigem Self-Client gegen Zoho-Desk-API v1, Transient-Caching (Listing 5 Min, Detail 60 s), Filter-Pipeline (Whitelist Domains/Mails + Blacklist Domains/Local-Part-Pattern, plus immer eigene User-Mail). Frontend in Umfragen-Designsprache, Admin als Submenu unter `dgptm-suite` mit drei Tabs (OAuth, Berechtigte Mitglieder, Filter).

**Tech Stack:** PHP 7.4+, WordPress 5.8+, Zoho-Desk-REST-API v1, OAuth2, WP-Transients, WP-Cron. Kein Test-Framework — Tests sind manuelle WP-Admin-Smoke-Tests + `php -l` Syntax-Checks (CI macht das ohnehin).

**Spec:** `docs/superpowers/specs/2026-04-30-zoho-desk-cockpit-design.md`

---

## File Structure

```
modules/business/zoho-desk-cockpit/
├── module.json                                  Task 1
├── dgptm-zoho-desk-cockpit.php                  Task 1   Bootstrap, Singleton, Hooks, Shortcode-Registrierung
├── includes/
│   ├── class-desk-logger.php                    Task 1   Wrapper auf DGPTM_Logger
│   ├── class-desk-admin.php                     Task 2   Submenu, Tabs, Settings-API-Registrierung
│   ├── class-desk-oauth.php                     Task 3-4 OAuth-Authorize/Callback/Refresh
│   ├── class-desk-api.php                       Task 5   REST-Client gegen Desk, SSRF-Schutz
│   ├── class-desk-filter.php                    Task 6   Whitelist/Blacklist + Mail-Match
│   ├── class-desk-cache.php                     Task 7   Transient-Wrapper, Pro-User-Keys
│   └── class-desk-shortcode.php                 Task 11-12 Render + AJAX-Endpoints
├── templates/
│   ├── frontend-cockpit.php                     Task 11  Listen-Tabs
│   └── frontend-detail.php                      Task 12  Detail-Modal-Snippet
├── assets/
│   ├── css/frontend.css                         Task 10
│   ├── css/admin.css                            Task 2
│   ├── js/frontend.js                           Task 10
│   └── js/admin.js                              Task 2
```

**Manuelle Test-Konvention:** Nach jedem Task wird auf dem lokalen WP-Setup das Modul reaktiviert (Modul-Toggle in DGPTM-Suite), `debug.log` und Browser-Konsole beobachtet, optisch geprüft. PHP-Syntax-Check über CI (deploy.yml). Mocking: Nicht möglich ohne Test-Framework — wir testen direkt gegen die Zoho-Desk-Sandbox des DGPTM-Accounts (Geschäftsstelle).

---

### Task 1: Modul-Skelett, Bootstrap, Logger

**Files:**
- Create: `modules/business/zoho-desk-cockpit/module.json`
- Create: `modules/business/zoho-desk-cockpit/dgptm-zoho-desk-cockpit.php`
- Create: `modules/business/zoho-desk-cockpit/includes/class-desk-logger.php`

- [ ] **Step 1: `module.json` anlegen**

```json
{
    "id": "zoho-desk-cockpit",
    "name": "DGPTM - Zoho Desk Cockpit",
    "description": "Zeigt eingeloggten Mitgliedern Zoho-Desk-Tickets mit Blueprint-Status und Kommentaren. Whitelist/Blacklist-Filter im Admin, eigene Tickets via User-Mail-Match immer sichtbar.",
    "version": "1.0.0",
    "author": "Sebastian Melzer",
    "main_file": "dgptm-zoho-desk-cockpit.php",
    "dependencies": [],
    "optional_dependencies": ["crm-abruf"],
    "wp_dependencies": { "plugins": [] },
    "requires_php": "7.4",
    "requires_wp": "5.8",
    "category": "business",
    "icon": "dashicons-format-chat",
    "active": false,
    "can_export": true,
    "critical": false
}
```

- [ ] **Step 2: Bootstrap `dgptm-zoho-desk-cockpit.php`**

```php
<?php
/**
 * Plugin Name: DGPTM - Zoho Desk Cockpit
 * Description: Frontend-Cockpit für Zoho-Desk-Tickets mit Blueprint-Status, Kommentaren, Whitelist/Blacklist.
 * Version: 1.0.0
 * Author: Sebastian Melzer
 * Text Domain: dgptm-suite
 */
if (!defined('ABSPATH')) exit;

define('DGPTM_DESK_VERSION', '1.0.0');
define('DGPTM_DESK_PATH', plugin_dir_path(__FILE__));
define('DGPTM_DESK_URL', plugin_dir_url(__FILE__));
define('DGPTM_DESK_OPTION', 'dgptm_desk_cockpit_settings');
define('DGPTM_DESK_CAPABILITY', 'dgptm_desk_cockpit_view');
define('DGPTM_DESK_USER_META', 'dgptm_desk_cockpit_authorized');

require_once DGPTM_DESK_PATH . 'includes/class-desk-logger.php';
require_once DGPTM_DESK_PATH . 'includes/class-desk-cache.php';
require_once DGPTM_DESK_PATH . 'includes/class-desk-filter.php';
require_once DGPTM_DESK_PATH . 'includes/class-desk-oauth.php';
require_once DGPTM_DESK_PATH . 'includes/class-desk-api.php';
require_once DGPTM_DESK_PATH . 'includes/class-desk-shortcode.php';
if (is_admin()) {
    require_once DGPTM_DESK_PATH . 'includes/class-desk-admin.php';
}

if (!class_exists('DGPTM_Desk_Cockpit')) {
    class DGPTM_Desk_Cockpit {
        private static $instance = null;
        public static function get_instance() {
            if (self::$instance === null) self::$instance = new self();
            return self::$instance;
        }
        private function __construct() {
            // Capability dynamisch ergänzen
            add_filter('user_has_cap', [$this, 'grant_capability'], 10, 4);

            // Frontend
            DGPTM_Desk_Shortcode::get_instance();

            // Admin
            if (is_admin()) {
                DGPTM_Desk_Admin::get_instance();
            }

            // OAuth-Hooks (Callback, Cron)
            DGPTM_Desk_OAuth::get_instance();

            // Cache-Invalidierung bei Profil-Update
            add_action('profile_update', ['DGPTM_Desk_Cache', 'flush_user'], 10, 1);
        }

        public function grant_capability($allcaps, $caps, $args, $user) {
            if (!$user || !$user->ID) return $allcaps;
            $authorized = (int) get_user_meta($user->ID, DGPTM_DESK_USER_META, true) === 1;
            if ($authorized) {
                $allcaps[DGPTM_DESK_CAPABILITY] = true;
            }
            return $allcaps;
        }
    }
}

if (!isset($GLOBALS['dgptm_desk_cockpit_initialized'])) {
    $GLOBALS['dgptm_desk_cockpit_initialized'] = true;
    DGPTM_Desk_Cockpit::get_instance();
}
```

- [ ] **Step 3: Logger-Wrapper `includes/class-desk-logger.php`**

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Desk_Logger {
    public static function log($message, $level = 'info', $context = null) {
        if (!class_exists('DGPTM_Logger')) return;
        $ctx = null;
        if (!empty($context) && is_array($context)) {
            $ctx = function_exists('dgptm_redact_array')
                ? dgptm_redact_array($context)
                : self::redact($context);
        }
        DGPTM_Logger::log($message, $level, 'zoho-desk-cockpit', $ctx);
    }

    private static function redact($arr) {
        $keys = ['authorization','Authorization','access_token','refresh_token','client_secret','code'];
        $out = [];
        foreach ((array) $arr as $k => $v) {
            if (in_array($k, $keys, true)) {
                $out[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $out[$k] = self::redact($v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
```

- [ ] **Step 4: Stub-Klassen anlegen, damit Bootstrap nicht fatal failt**

In jedem Stub: `<?php if (!defined('ABSPATH')) exit; class DGPTM_Desk_Cache { public static function flush_user($id){} public static function get_instance(){} } ` — analog für `DGPTM_Desk_Filter`, `DGPTM_Desk_OAuth`, `DGPTM_Desk_API`, `DGPTM_Desk_Shortcode`, `DGPTM_Desk_Admin`. Jede Klasse hat `private static $instance` + `public static function get_instance()`.

Beispiel `includes/class-desk-cache.php`:

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Desk_Cache {
    private static $instance = null;
    public static function get_instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }
    public static function flush_user($user_id) {
        // wird in Task 7 implementiert
    }
}
```

Analog `class-desk-filter.php`, `class-desk-oauth.php`, `class-desk-api.php`, `class-desk-shortcode.php`, `class-desk-admin.php`.

- [ ] **Step 5: Manueller Test**

  1. Datei `D:/.../DGPTMSuite/modules/business/zoho-desk-cockpit/` ist im Plugin-Manager der Suite sichtbar (Refresh `wp-admin → DGPTM-Suite`).
  2. Modul aktivieren — keine Fatal Errors, `wp-content/debug.log` zeigt keine PHP-Warnungen.
  3. Modul deaktivieren — sauber.

- [ ] **Step 6: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Modul-Skelett, Bootstrap, Logger"
```

---

### Task 2: Admin-Submenu mit drei leeren Tabs

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-admin.php`
- Create: `modules/business/zoho-desk-cockpit/assets/css/admin.css`
- Create: `modules/business/zoho-desk-cockpit/assets/js/admin.js`

- [ ] **Step 1: `class-desk-admin.php` ausschreiben**

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Desk_Admin {
    private static $instance = null;
    const PAGE_SLUG = 'dgptm-desk-cockpit';

    public static function get_instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu() {
        add_submenu_page(
            'dgptm-suite',
            __('Zoho Desk Cockpit', 'dgptm-suite'),
            __('Desk Cockpit', 'dgptm-suite'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function enqueue_assets($hook) {
        if (strpos((string) $hook, self::PAGE_SLUG) === false) return;
        wp_enqueue_style(
            'dgptm-desk-admin',
            DGPTM_DESK_URL . 'assets/css/admin.css',
            [],
            DGPTM_DESK_VERSION
        );
        wp_enqueue_script(
            'dgptm-desk-admin',
            DGPTM_DESK_URL . 'assets/js/admin.js',
            ['jquery'],
            DGPTM_DESK_VERSION,
            true
        );
        wp_localize_script('dgptm-desk-admin', 'DGPTM_DESK_ADMIN', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('dgptm_desk_admin'),
        ]);
    }

    public function register_settings() {
        register_setting('dgptm_desk_cockpit', DGPTM_DESK_OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default'           => self::defaults(),
        ]);
    }

    public static function defaults() {
        return [
            'region'                  => 'eu',
            'org_id'                  => '',
            'client_id'               => '',
            'client_secret_enc'       => '',
            'whitelist_domains'       => [],
            'whitelist_emails'        => [],
            'blacklist_mail_patterns' => ['noreply','no-reply','mailer-daemon','bounce','do-not-reply','postmaster','notifications','automated'],
            'blacklist_domains'       => [],
        ];
    }

    public function sanitize_settings($input) {
        // Detail-Sanitization in Task 8 (Filter-Tab) und Task 3 (OAuth-Tab)
        $current = get_option(DGPTM_DESK_OPTION, self::defaults());
        return is_array($input) ? array_merge($current, $input) : $current;
    }

    public function render_page() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'oauth';
        $tabs = [
            'oauth'   => __('OAuth & Verbindung', 'dgptm-suite'),
            'members' => __('Berechtigte Mitglieder', 'dgptm-suite'),
            'filter'  => __('Filter', 'dgptm-suite'),
        ];
        echo '<div class="wrap dgptm-desk-admin">';
        echo '<h1>' . esc_html__('Zoho Desk Cockpit', 'dgptm-suite') . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $url = add_query_arg(['page' => self::PAGE_SLUG, 'tab' => $key], admin_url('admin.php'));
            $cls = 'nav-tab' . ($tab === $key ? ' nav-tab-active' : '');
            printf('<a class="%s" href="%s">%s</a>', esc_attr($cls), esc_url($url), esc_html($label));
        }
        echo '</h2>';
        switch ($tab) {
            case 'members': $this->render_members_tab(); break;
            case 'filter':  $this->render_filter_tab();  break;
            default:        $this->render_oauth_tab();   break;
        }
        echo '</div>';
    }

    private function render_oauth_tab()   { echo '<p>' . esc_html__('OAuth-Konfiguration folgt.', 'dgptm-suite') . '</p>'; }
    private function render_members_tab() { echo '<p>' . esc_html__('Mitgliederverwaltung folgt.', 'dgptm-suite') . '</p>'; }
    private function render_filter_tab()  { echo '<p>' . esc_html__('Filter folgen.', 'dgptm-suite') . '</p>'; }
}
```

- [ ] **Step 2: Minimales `assets/css/admin.css`**

```css
.dgptm-desk-admin .nav-tab-wrapper { margin-bottom: 16px; }
.dgptm-desk-admin .dgptm-desk-card {
    background:#fff; border:1px solid #ddd; padding:16px 20px; margin:12px 0;
    border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,0.04);
}
.dgptm-desk-admin .dgptm-desk-status-ok { color:#10b981; }
.dgptm-desk-admin .dgptm-desk-status-warn { color:#f59e0b; }
.dgptm-desk-admin .dgptm-desk-status-err { color:#ef4444; }
.dgptm-desk-admin textarea { width:100%; min-height:120px; font-family: ui-monospace, monospace; }
```

- [ ] **Step 3: Leerer `assets/js/admin.js`**

```js
(function($){ /* Admin-JS folgt in späteren Tasks */ })(jQuery);
```

- [ ] **Step 4: Manueller Test**

  1. Modul aktivieren.
  2. WP-Admin → DGPTM-Suite → Submenu „Desk Cockpit" sichtbar.
  3. Klick öffnet Seite mit drei Tabs, Default-Tab „OAuth & Verbindung".
  4. Tabs schalten via Query-Param `&tab=members|filter|oauth`.

- [ ] **Step 5: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Admin-Submenu mit Tab-Skelett"
```

---

### Task 3: OAuth-Tab + Connect-Flow + Token-Speicherung (verschlüsselt)

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-admin.php` (`render_oauth_tab`, Sanitization)
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-oauth.php`

**Hinweise:**
- Region-Mapping: `eu→accounts.zoho.eu`, `com→accounts.zoho.com`, `in→accounts.zoho.in`, `au→accounts.zoho.com.au`, `jp→accounts.zoho.jp`.
- Redirect-URI = `admin-url('admin-ajax.php?action=dgptm_desk_oauth_callback')`. Diese URL muss der Admin im Zoho-Self-Client als „Authorized Redirect URI" eintragen.
- Verschlüsselung mit `AUTH_KEY` als Material — `openssl_encrypt` mit `AES-256-CBC`.

- [ ] **Step 1: Verschlüsselungs-Helfer in `class-desk-oauth.php`**

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Desk_OAuth {
    private static $instance = null;
    const SCOPES = 'Desk.tickets.READ,Desk.contacts.READ,Desk.basic.READ,Desk.search.READ';
    const TOKENS_OPTION = 'dgptm_desk_oauth_tokens';

    public static function get_instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_dgptm_desk_oauth_callback', [$this, 'handle_callback']);
        add_action('wp_ajax_dgptm_desk_oauth_start',    [$this, 'handle_start']);
        add_action('wp_ajax_dgptm_desk_oauth_test',     [$this, 'handle_test']);
        add_action('dgptm_desk_token_refresh_cron',     [$this, 'cron_refresh']);
        if (!wp_next_scheduled('dgptm_desk_token_refresh_cron')) {
            wp_schedule_event(time() + 60, 'hourly', 'dgptm_desk_token_refresh_cron');
        }
    }

    public static function encrypt($plain) {
        if ($plain === '' || $plain === null) return '';
        $key = substr(hash('sha256', AUTH_KEY, true), 0, 32);
        $iv  = openssl_random_pseudo_bytes(16);
        $enc = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    public static function decrypt($enc) {
        if ($enc === '' || $enc === null) return '';
        $raw = base64_decode($enc, true);
        if ($raw === false || strlen($raw) < 17) return '';
        $key = substr(hash('sha256', AUTH_KEY, true), 0, 32);
        $iv  = substr($raw, 0, 16);
        $ct  = substr($raw, 16);
        $dec = openssl_decrypt($ct, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $dec === false ? '' : $dec;
    }

    public static function settings() {
        return get_option(DGPTM_DESK_OPTION, DGPTM_Desk_Admin::defaults());
    }

    public static function accounts_host($region) {
        $map = [
            'eu' => 'accounts.zoho.eu',
            'com'=> 'accounts.zoho.com',
            'in' => 'accounts.zoho.in',
            'au' => 'accounts.zoho.com.au',
            'jp' => 'accounts.zoho.jp',
        ];
        return $map[$region] ?? 'accounts.zoho.eu';
    }

    public static function api_host($region) {
        $map = [
            'eu' => 'desk.zoho.eu',
            'com'=> 'desk.zoho.com',
            'in' => 'desk.zoho.in',
            'au' => 'desk.zoho.com.au',
            'jp' => 'desk.zoho.jp',
        ];
        return $map[$region] ?? 'desk.zoho.eu';
    }

    public static function redirect_uri() {
        return admin_url('admin-ajax.php?action=dgptm_desk_oauth_callback');
    }

    public function handle_start() {
        if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
        check_admin_referer('dgptm_desk_admin', 'nonce');
        $s = self::settings();
        if (empty($s['client_id']) || empty($s['client_secret_enc'])) {
            wp_die(__('Bitte zuerst Client-ID und Client-Secret speichern.', 'dgptm-suite'));
        }
        $url = 'https://' . self::accounts_host($s['region']) . '/oauth/v2/auth?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $s['client_id'],
            'scope'         => self::SCOPES,
            'redirect_uri'  => self::redirect_uri(),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => wp_create_nonce('dgptm_desk_oauth_state'),
        ]);
        wp_redirect($url);
        exit;
    }

    public function handle_callback() {
        if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
        $code  = isset($_GET['code'])  ? sanitize_text_field(wp_unslash($_GET['code']))  : '';
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        if (!wp_verify_nonce($state, 'dgptm_desk_oauth_state')) wp_die('State mismatch', 400);
        if (!$code) wp_die('No code', 400);

        $s = self::settings();
        $resp = wp_remote_post('https://' . self::accounts_host($s['region']) . '/oauth/v2/token', [
            'timeout' => 20,
            'body'    => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $s['client_id'],
                'client_secret' => self::decrypt($s['client_secret_enc']),
                'redirect_uri'  => self::redirect_uri(),
                'code'          => $code,
            ],
        ]);
        if (is_wp_error($resp)) {
            DGPTM_Desk_Logger::log('OAuth-Token-Exchange fehlgeschlagen', 'error', ['err' => $resp->get_error_message()]);
            wp_die(esc_html($resp->get_error_message()));
        }
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['access_token']) || empty($data['refresh_token'])) {
            DGPTM_Desk_Logger::log('OAuth-Token-Response unvollständig', 'error', ['data' => $data]);
            wp_die('Token-Response unvollständig: ' . esc_html(wp_remote_retrieve_body($resp)));
        }

        update_option('dgptm_desk_oauth_tokens', [
            'access_token_enc'  => self::encrypt($data['access_token']),
            'refresh_token_enc' => self::encrypt($data['refresh_token']),
            'expires_at'        => time() + (int) ($data['expires_in'] ?? 3600) - 60,
            'created_at'        => time(),
        ], false);

        DGPTM_Desk_Logger::log('OAuth verbunden', 'info');
        wp_safe_redirect(add_query_arg(['page' => 'dgptm-desk-cockpit', 'tab' => 'oauth', 'connected' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function tokens() {
        $t = get_option('dgptm_desk_oauth_tokens', []);
        return is_array($t) ? $t : [];
    }

    public static function status() {
        $t = self::tokens();
        if (empty($t['refresh_token_enc'])) return ['state' => 'missing', 'label' => 'nicht verbunden'];
        if (empty($t['access_token_enc']) || (int) ($t['expires_at'] ?? 0) <= time()) {
            return ['state' => 'stale', 'label' => 'Access-Token abgelaufen, wird beim nächsten Aufruf erneuert'];
        }
        return ['state' => 'ok', 'label' => 'verbunden, gültig bis ' . wp_date('Y-m-d H:i', (int) $t['expires_at'])];
    }

    public function cron_refresh() {
        // Implementierung in Task 4
    }
    public function handle_test() {
        // Implementierung in Task 5
    }
    public function refresh_now() {
        // Implementierung in Task 4
    }
}
```

- [ ] **Step 2: `render_oauth_tab` in `class-desk-admin.php` ausbauen**

Im selben File `class-desk-admin.php`, Methode `render_oauth_tab` ersetzen:

```php
private function render_oauth_tab() {
    $s = get_option(DGPTM_DESK_OPTION, self::defaults());
    $status = DGPTM_Desk_OAuth::status();

    if (isset($_GET['connected'])) {
        echo '<div class="notice notice-success"><p>' . esc_html__('Mit Zoho Desk verbunden.', 'dgptm-suite') . '</p></div>';
    }

    echo '<form method="post" action="options.php">';
    settings_fields('dgptm_desk_cockpit');
    echo '<div class="dgptm-desk-card">';
    echo '<h3>' . esc_html__('Zoho Self-Client', 'dgptm-suite') . '</h3>';
    echo '<p><label>' . esc_html__('Region', 'dgptm-suite') . '<br/>';
    echo '<select name="' . esc_attr(DGPTM_DESK_OPTION) . '[region]">';
    foreach (['eu' => 'EU (.zoho.eu)', 'com' => 'COM (.zoho.com)', 'in' => 'IN (.zoho.in)', 'au' => 'AU (.zoho.com.au)', 'jp' => 'JP (.zoho.jp)'] as $k => $l) {
        printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($s['region'], $k, false), esc_html($l));
    }
    echo '</select></label></p>';
    echo '<p><label>Org-ID<br/><input type="text" name="' . esc_attr(DGPTM_DESK_OPTION) . '[org_id]" value="' . esc_attr($s['org_id']) . '" class="regular-text"></label></p>';
    echo '<p><label>Client-ID<br/><input type="text" name="' . esc_attr(DGPTM_DESK_OPTION) . '[client_id]" value="' . esc_attr($s['client_id']) . '" class="regular-text"></label></p>';
    echo '<p><label>Client-Secret<br/><input type="password" name="' . esc_attr(DGPTM_DESK_OPTION) . '[client_secret_plain]" value="" class="regular-text" placeholder="' . esc_attr__('leer lassen, um vorhandenes Secret zu behalten', 'dgptm-suite') . '"></label></p>';
    echo '<p class="description">' . esc_html__('Redirect-URI im Self-Client eintragen:', 'dgptm-suite') . ' <code>' . esc_html(DGPTM_Desk_OAuth::redirect_uri()) . '</code></p>';
    submit_button(__('Speichern', 'dgptm-suite'));
    echo '</div>';
    echo '</form>';

    echo '<div class="dgptm-desk-card">';
    echo '<h3>' . esc_html__('Verbindung', 'dgptm-suite') . '</h3>';
    $cls = $status['state'] === 'ok' ? 'dgptm-desk-status-ok' : ($status['state'] === 'stale' ? 'dgptm-desk-status-warn' : 'dgptm-desk-status-err');
    echo '<p>Status: <strong class="' . esc_attr($cls) . '">' . esc_html($status['label']) . '</strong></p>';

    $start_url = wp_nonce_url(admin_url('admin-ajax.php?action=dgptm_desk_oauth_start'), 'dgptm_desk_admin', 'nonce');
    echo '<a href="' . esc_url($start_url) . '" class="button button-primary">' . esc_html__('Mit Zoho verbinden', 'dgptm-suite') . '</a> ';
    if ($status['state'] !== 'missing') {
        $test_url = wp_nonce_url(admin_url('admin-ajax.php?action=dgptm_desk_oauth_test'), 'dgptm_desk_admin', 'nonce');
        echo '<a href="' . esc_url($test_url) . '" class="button">' . esc_html__('Verbindung testen', 'dgptm-suite') . '</a>';
    }
    echo '</div>';
}
```

- [ ] **Step 3: Sanitize-Hook erweitern**

In `class-desk-admin.php`, `sanitize_settings`-Methode ersetzen:

```php
public function sanitize_settings($input) {
    $current = get_option(DGPTM_DESK_OPTION, self::defaults());
    if (!is_array($input)) return $current;

    $out = $current;
    if (isset($input['region']))    $out['region']    = sanitize_key($input['region']);
    if (isset($input['org_id']))    $out['org_id']    = sanitize_text_field($input['org_id']);
    if (isset($input['client_id'])) $out['client_id'] = sanitize_text_field($input['client_id']);

    if (!empty($input['client_secret_plain'])) {
        $out['client_secret_enc'] = DGPTM_Desk_OAuth::encrypt(sanitize_text_field($input['client_secret_plain']));
    }

    // Filter-Keys werden in Task 8 hier ergänzt
    return $out;
}
```

- [ ] **Step 4: Manueller Test**

  1. Modul ist aktiv. WP-Admin → Desk Cockpit → Tab „OAuth & Verbindung".
  2. Region/Org-ID/Client-ID/Secret eintragen → Speichern. Reload zeigt: Region/Org-ID/Client-ID gefüllt, Secret-Feld wieder leer (Hinweis aktiv).
  3. Im Zoho-Self-Client (separat) Redirect-URI eintragen.
  4. „Mit Zoho verbinden" klicken → Zoho-Login → Consent → zurück nach WP mit Notice „Mit Zoho Desk verbunden".
  5. Status-Zeile zeigt grün „verbunden, gültig bis …".
  6. `wp_options` enthält `dgptm_desk_oauth_tokens` mit `access_token_enc`/`refresh_token_enc` (base64-Strings, nicht im Klartext).

- [ ] **Step 5: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): OAuth-Connect-Flow und verschluesselte Token-Speicherung"
```

---

### Task 4: Token-Refresh (on-demand + Cron)

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-oauth.php`

- [ ] **Step 1: `refresh_now()` und `cron_refresh()` implementieren**

In `class-desk-oauth.php` die Stub-Methoden ersetzen:

```php
public function refresh_now() {
    $t = self::tokens();
    if (empty($t['refresh_token_enc'])) {
        return new WP_Error('no_refresh_token', 'Kein Refresh-Token gespeichert.');
    }
    $s = self::settings();
    $resp = wp_remote_post('https://' . self::accounts_host($s['region']) . '/oauth/v2/token', [
        'timeout' => 20,
        'body'    => [
            'grant_type'    => 'refresh_token',
            'refresh_token' => self::decrypt($t['refresh_token_enc']),
            'client_id'     => $s['client_id'],
            'client_secret' => self::decrypt($s['client_secret_enc']),
        ],
    ]);
    if (is_wp_error($resp)) {
        DGPTM_Desk_Logger::log('Refresh-Request fehlgeschlagen', 'error', ['err' => $resp->get_error_message()]);
        return $resp;
    }
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($data['access_token'])) {
        DGPTM_Desk_Logger::log('Refresh-Response ohne access_token', 'error', ['data' => $data]);
        return new WP_Error('refresh_failed', 'Kein access_token in Refresh-Response.');
    }
    $t['access_token_enc'] = self::encrypt($data['access_token']);
    $t['expires_at']       = time() + (int) ($data['expires_in'] ?? 3600) - 60;
    update_option('dgptm_desk_oauth_tokens', $t, false);
    DGPTM_Desk_Logger::log('Access-Token erneuert', 'info');
    return $data['access_token'];
}

public function cron_refresh() {
    $t = self::tokens();
    if (empty($t['refresh_token_enc'])) return;
    if ((int) ($t['expires_at'] ?? 0) > time() + 600) return; // noch >10 Min gültig
    $r = $this->refresh_now();
    if (is_wp_error($r)) {
        DGPTM_Desk_Logger::log('Cron-Refresh fehlgeschlagen', 'error', ['err' => $r->get_error_message()]);
        set_transient('dgptm_desk_admin_notice', $r->get_error_message(), DAY_IN_SECONDS);
    }
}

public static function get_access_token() {
    $t = self::tokens();
    if (empty($t['access_token_enc'])) {
        $self = self::get_instance();
        $r = $self->refresh_now();
        if (is_wp_error($r)) return $r;
        $t = self::tokens();
    } elseif ((int) ($t['expires_at'] ?? 0) <= time()) {
        $self = self::get_instance();
        $r = $self->refresh_now();
        if (is_wp_error($r)) return $r;
        $t = self::tokens();
    }
    return self::decrypt($t['access_token_enc']);
}
```

- [ ] **Step 2: Admin-Notice für gescheiterten Refresh anzeigen**

In `class-desk-admin.php`, Konstruktor ergänzen:

```php
add_action('admin_notices', [$this, 'maybe_show_notices']);
```

Methode ergänzen:

```php
public function maybe_show_notices() {
    $msg = get_transient('dgptm_desk_admin_notice');
    if ($msg) {
        echo '<div class="notice notice-error"><p>Zoho Desk: ' . esc_html($msg) . '</p></div>';
        delete_transient('dgptm_desk_admin_notice');
    }
}
```

- [ ] **Step 3: Manueller Test**

  1. Setze in DB temporär `expires_at` auf `time() - 1` via phpMyAdmin oder `wp option update`. (`wp option get dgptm_desk_oauth_tokens --format=json | jq` zur Inspektion.)
  2. Im Frontend irgendeine Cockpit-Seite aufrufen ODER via WP-CLI: `wp eval 'var_dump(DGPTM_Desk_OAuth::get_access_token());'` — Refresh wird ausgelöst.
  3. `expires_at` ist neu (jetzt + ~3540).
  4. `debug.log`: Eintrag „Access-Token erneuert".

- [ ] **Step 4: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Token-Refresh-Logik und stuendlicher Cron"
```

---

### Task 5: Desk-API-Client mit SSRF-Schutz

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-api.php`
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-oauth.php` (`handle_test`)

- [ ] **Step 1: API-Client implementieren**

`class-desk-api.php` komplett:

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Desk_API {
    private static $instance = null;
    public static function get_instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private static function host_allowed($host) {
        return (bool) preg_match('/^desk\.zoho\.(eu|com|in|com\.au|jp)$/', $host);
    }

    private function request($method, $path, $query = [], $body = null) {
        $token = DGPTM_Desk_OAuth::get_access_token();
        if (is_wp_error($token)) return $token;

        $s    = DGPTM_Desk_OAuth::settings();
        $host = DGPTM_Desk_OAuth::api_host($s['region']);
        if (!self::host_allowed($host)) return new WP_Error('host_blocked', 'API-Host nicht erlaubt');

        $url = 'https://' . $host . $path;
        if (!empty($query)) $url .= '?' . http_build_query($query);

        $headers = [
            'Authorization' => 'Zoho-oauthtoken ' . $token,
            'orgId'         => $s['org_id'],
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];

        $args = [
            'method'  => $method,
            'timeout' => 20,
            'headers' => $headers,
        ];
        if ($body !== null) $args['body'] = wp_json_encode($body);

        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) {
            DGPTM_Desk_Logger::log('Desk-API-Request-Fehler', 'error', ['url' => $url, 'err' => $resp->get_error_message()]);
            return $resp;
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);

        if ($code === 401) {
            // Token abgelaufen → einmal forcieren
            $oauth = DGPTM_Desk_OAuth::get_instance();
            $r = $oauth->refresh_now();
            if (is_wp_error($r)) return $r;
            $headers['Authorization'] = 'Zoho-oauthtoken ' . $r;
            $args['headers'] = $headers;
            $resp = wp_remote_request($url, $args);
            $code = (int) wp_remote_retrieve_response_code($resp);
            $raw  = wp_remote_retrieve_body($resp);
        }

        if ($code === 429) {
            DGPTM_Desk_Logger::log('Desk-Rate-Limit 429', 'warning', ['url' => $url]);
            return new WP_Error('rate_limit', 'Zoho Desk Rate-Limit erreicht');
        }
        if ($code < 200 || $code >= 300) {
            DGPTM_Desk_Logger::log('Desk-API-HTTP-Fehler', 'error', ['url' => $url, 'code' => $code, 'body' => substr($raw, 0, 500)]);
            return new WP_Error('http_' . $code, 'Desk-API HTTP ' . $code);
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public function list_open() {
        return $this->request('GET', '/api/v1/tickets/search', [
            'status'  => 'Open,On Hold,Escalated',
            'sortBy'  => '-modifiedTime',
            'limit'   => 100,
            'include' => 'contacts,assignee',
        ]);
    }

    public function list_closed_last_7_days() {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $from = gmdate('Y-m-d\TH:i:s\Z', time() - 7 * DAY_IN_SECONDS);
        return $this->request('GET', '/api/v1/tickets/search', [
            'status'        => 'Closed',
            'modifiedTimeRange' => $from . ',' . $now,
            'sortBy'        => '-modifiedTime',
            'limit'         => 100,
            'include'       => 'contacts,assignee',
        ]);
    }

    public function get_ticket($id) {
        $id = (int) $id;
        if ($id <= 0) return new WP_Error('bad_id', 'Ungültige Ticket-ID');
        return $this->request('GET', '/api/v1/tickets/' . $id, [
            'include' => 'contacts,assignee',
        ]);
    }

    public function get_comments($id) {
        $id = (int) $id;
        if ($id <= 0) return new WP_Error('bad_id', 'Ungültige Ticket-ID');
        $r = $this->request('GET', '/api/v1/tickets/' . $id . '/comments', [
            'sortBy' => '-commentedTime',
            'limit'  => 100,
        ]);
        return $r;
    }

    public function get_blueprint($id) {
        $id = (int) $id;
        if ($id <= 0) return new WP_Error('bad_id', 'Ungültige Ticket-ID');
        return $this->request('GET', '/api/v1/tickets/' . $id . '/getBlueprint', []);
    }
}
```

- [ ] **Step 2: `handle_test` in `class-desk-oauth.php`**

```php
public function handle_test() {
    if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
    if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'dgptm_desk_admin')) {
        wp_die('Bad nonce', 403);
    }
    $api = DGPTM_Desk_API::get_instance();
    $r = $api->list_open();
    if (is_wp_error($r)) {
        $msg = $r->get_error_message();
        DGPTM_Desk_Logger::log('Verbindungstest fehlgeschlagen', 'warning', ['err' => $msg]);
        wp_safe_redirect(add_query_arg(['page' => 'dgptm-desk-cockpit', 'tab' => 'oauth', 'test_err' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }
    $count = isset($r['data']) ? count($r['data']) : 0;
    DGPTM_Desk_Logger::log('Verbindungstest erfolgreich', 'info', ['tickets' => $count]);
    wp_safe_redirect(add_query_arg(['page' => 'dgptm-desk-cockpit', 'tab' => 'oauth', 'test_ok' => $count], admin_url('admin.php')));
    exit;
}
```

- [ ] **Step 3: Anzeige des Test-Ergebnisses in `render_oauth_tab`**

In `class-desk-admin.php` direkt nach dem `connected`-Block ergänzen:

```php
if (isset($_GET['test_ok'])) {
    echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Verbindungstest OK — %d offene Tickets gepullt.', 'dgptm-suite'), (int) $_GET['test_ok']) . '</p></div>';
}
if (isset($_GET['test_err'])) {
    echo '<div class="notice notice-error"><p>' . esc_html__('Verbindungstest fehlgeschlagen: ', 'dgptm-suite') . esc_html(rawurldecode((string) $_GET['test_err'])) . '</p></div>';
}
```

- [ ] **Step 4: Manueller Test**

  1. Tab „OAuth & Verbindung" → „Verbindung testen".
  2. Erwartet: grüne Notice „Verbindungstest OK — N offene Tickets gepullt.".
  3. `debug.log`: Eintrag „Verbindungstest erfolgreich".
  4. Negativ-Test: Im DB temporär falsches `client_id` setzen → Fehler-Notice mit Fehlertext.

- [ ] **Step 5: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Desk-API-Client mit SSRF-Schutz und Verbindungstest"
```

---

### Task 6: Filter-Klasse (Whitelist/Blacklist)

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-filter.php`

- [ ] **Step 1: `class-desk-filter.php` ausschreiben**

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Desk_Filter {
    public static function get_instance() { return null; } // statische Klasse, Bootstrap braucht den Stub

    /**
     * Filtert eine Liste von Desk-Tickets gemäß Whitelist/Blacklist.
     *
     * @param array  $tickets    Roh-Ticket-Liste aus Desk-API (Array mit 'data' oder direkter Liste)
     * @param string $user_email Mail des aktuellen WP-Users (eigene Tickets immer sichtbar)
     * @param array  $settings   Plugin-Settings (DGPTM_DESK_OPTION)
     * @return array Liste sichtbarer Tickets (gleiche Struktur wie Eingabe)
     */
    public static function filter_list($tickets, $user_email, $settings) {
        $list = isset($tickets['data']) && is_array($tickets['data']) ? $tickets['data'] : (is_array($tickets) ? $tickets : []);
        $out = [];
        foreach ($list as $t) {
            if (self::is_visible($t, $user_email, $settings)) $out[] = $t;
        }
        return $out;
    }

    public static function is_visible($ticket, $user_email, $settings) {
        $email = self::contact_email($ticket);
        if (!$email) return false;

        $local  = self::local_part($email);
        $domain = self::domain_part($email);

        $hit_self   = $user_email && strcasecmp($email, $user_email) === 0;
        $hit_domain = self::in_array_ci($domain, $settings['whitelist_domains'] ?? []);
        $hit_mail   = self::in_array_ci($email,  $settings['whitelist_emails']  ?? []);
        $whitelist  = $hit_self || $hit_domain || $hit_mail;

        if (!$whitelist) return false;
        if ($hit_self) return true; // eigene Mail überschreibt Blacklist

        $patterns = $settings['blacklist_mail_patterns'] ?? [];
        foreach ($patterns as $pat) {
            if ($pat === '') continue;
            if (stripos($local, $pat) !== false) return false;
        }
        if (self::in_array_ci($domain, $settings['blacklist_domains'] ?? [])) return false;

        return true;
    }

    public static function contact_email($ticket) {
        if (isset($ticket['contact']['email']))  return strtolower(trim($ticket['contact']['email']));
        if (isset($ticket['email']))             return strtolower(trim($ticket['email']));
        return '';
    }
    public static function local_part($email)  { $p = strrpos($email, '@'); return $p === false ? $email : substr($email, 0, $p); }
    public static function domain_part($email) { $p = strrpos($email, '@'); return $p === false ? '' : strtolower(substr($email, $p + 1)); }

    public static function in_array_ci($needle, $haystack) {
        $needle = strtolower((string) $needle);
        foreach ((array) $haystack as $h) {
            if (strtolower(trim((string) $h)) === $needle) return true;
        }
        return false;
    }

    public static function filter_hash($settings, $user_email) {
        return md5(wp_json_encode([
            'wd' => array_map('strtolower', $settings['whitelist_domains']       ?? []),
            'we' => array_map('strtolower', $settings['whitelist_emails']        ?? []),
            'bp' => array_map('strtolower', $settings['blacklist_mail_patterns'] ?? []),
            'bd' => array_map('strtolower', $settings['blacklist_domains']       ?? []),
            'me' => strtolower($user_email),
        ]));
    }
}
```

- [ ] **Step 2: Manueller Test (Smoke)**

Ad-hoc per WP-CLI im Projekt-Root:

```bash
wp eval '
$s = ["whitelist_domains"=>["dgptm.de"],"whitelist_emails"=>[],"blacklist_mail_patterns"=>["noreply"],"blacklist_domains"=>[]];
$t = ["contact"=>["email"=>"info@dgptm.de"]];
var_dump(DGPTM_Desk_Filter::is_visible($t,"member@example.com",$s));   // expect: true
$t2 = ["contact"=>["email"=>"noreply@dgptm.de"]];
var_dump(DGPTM_Desk_Filter::is_visible($t2,"member@example.com",$s));  // expect: false
$t3 = ["contact"=>["email"=>"member@example.com"]];
var_dump(DGPTM_Desk_Filter::is_visible($t3,"member@example.com",$s));  // expect: true (eigene)
'
```

- [ ] **Step 3: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Whitelist/Blacklist-Filter-Klasse"
```

---

### Task 7: Cache-Wrapper

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-cache.php`

- [ ] **Step 1: `class-desk-cache.php` implementieren**

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Desk_Cache {
    const LISTING_TTL = 5 * MINUTE_IN_SECONDS;
    const DETAIL_TTL  = 60;
    const STALE_FACTOR = 2; // bei API-Fehler bis 2× TTL alte Daten ausliefern

    public static function get_instance() { return null; }

    private static function listing_key($user_id, $filter_hash) {
        return 'dgptm_desk_list_' . $user_id . '_' . $filter_hash;
    }
    private static function listing_stale_key($user_id, $filter_hash) {
        return 'dgptm_desk_list_stale_' . $user_id . '_' . $filter_hash;
    }
    private static function detail_key($ticket_id) {
        return 'dgptm_desk_detail_' . (int) $ticket_id;
    }

    public static function get_listing($user_id, $filter_hash) {
        $v = get_transient(self::listing_key($user_id, $filter_hash));
        return is_array($v) ? $v : null;
    }
    public static function set_listing($user_id, $filter_hash, $data) {
        set_transient(self::listing_key($user_id, $filter_hash), $data, self::LISTING_TTL);
        set_transient(self::listing_stale_key($user_id, $filter_hash), $data, self::LISTING_TTL * self::STALE_FACTOR);
    }
    public static function get_listing_stale($user_id, $filter_hash) {
        $v = get_transient(self::listing_stale_key($user_id, $filter_hash));
        return is_array($v) ? $v : null;
    }

    public static function get_detail($ticket_id) {
        $v = get_transient(self::detail_key($ticket_id));
        return is_array($v) ? $v : null;
    }
    public static function set_detail($ticket_id, $data) {
        set_transient(self::detail_key($ticket_id), $data, self::DETAIL_TTL);
    }

    public static function flush_user($user_id) {
        global $wpdb;
        $like = $wpdb->esc_like('_transient_dgptm_desk_list_' . $user_id . '_') . '%';
        $like_t = $wpdb->esc_like('_transient_timeout_dgptm_desk_list_' . $user_id . '_') . '%';
        $like_s = $wpdb->esc_like('_transient_dgptm_desk_list_stale_' . $user_id . '_') . '%';
        $like_st= $wpdb->esc_like('_transient_timeout_dgptm_desk_list_stale_' . $user_id . '_') . '%';
        foreach ([$like, $like_t, $like_s, $like_st] as $l) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $l));
        }
    }

    public static function flush_all_listings() {
        global $wpdb;
        foreach ([
            '_transient_dgptm_desk_list_%',
            '_transient_timeout_dgptm_desk_list_%',
        ] as $l) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $l));
        }
    }
}
```

- [ ] **Step 2: Manueller Test**

```bash
wp eval '
DGPTM_Desk_Cache::set_listing(1, "abc", ["foo"=>"bar"]);
var_dump(DGPTM_Desk_Cache::get_listing(1, "abc"));
DGPTM_Desk_Cache::flush_user(1);
var_dump(DGPTM_Desk_Cache::get_listing(1, "abc"));   // expect: NULL
var_dump(DGPTM_Desk_Cache::get_listing_stale(1, "abc")); // expect: NULL
'
```

- [ ] **Step 3: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Transient-Cache-Wrapper mit Stale-Backup"
```

---

### Task 8: Filter-Tab (Whitelist/Blacklist verwalten)

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-admin.php` (`render_filter_tab`, `sanitize_settings`, AJAX-Filter-Test)

- [ ] **Step 1: `render_filter_tab` ausbauen**

```php
private function render_filter_tab() {
    $s = get_option(DGPTM_DESK_OPTION, self::defaults());
    $textarea = function ($key, $label, $values, $hint = '') {
        $val = is_array($values) ? implode("\n", $values) : '';
        echo '<p><label><strong>' . esc_html($label) . '</strong><br/>';
        echo '<textarea name="' . esc_attr(DGPTM_DESK_OPTION) . '[' . esc_attr($key) . ']">' . esc_textarea($val) . '</textarea></label>';
        if ($hint) echo '<br/><span class="description">' . esc_html($hint) . '</span>';
        echo '</p>';
    };

    echo '<form method="post" action="options.php">';
    settings_fields('dgptm_desk_cockpit');

    echo '<div class="dgptm-desk-card">';
    echo '<h3>' . esc_html__('Whitelist (zusätzlich zu eigenen Tickets)', 'dgptm-suite') . '</h3>';
    $textarea('whitelist_domains', __('Domains', 'dgptm-suite'), $s['whitelist_domains'], __('eine Domain pro Zeile, ohne @, z. B. dgptm.de', 'dgptm-suite'));
    $textarea('whitelist_emails',  __('E-Mails', 'dgptm-suite'),  $s['whitelist_emails'],  __('eine Mail pro Zeile, z. B. info@dgptm.de', 'dgptm-suite'));
    echo '</div>';

    echo '<div class="dgptm-desk-card">';
    echo '<h3>' . esc_html__('Blacklist (automatisierte Mails ausblenden)', 'dgptm-suite') . '</h3>';
    $textarea('blacklist_mail_patterns', __('Mail-Pattern (Local-Part)', 'dgptm-suite'), $s['blacklist_mail_patterns'], __('Substring-Match auf den Teil vor @, z. B. noreply, mailer-daemon', 'dgptm-suite'));
    $textarea('blacklist_domains',       __('Domains', 'dgptm-suite'),                    $s['blacklist_domains'],       __('eine Domain pro Zeile, z. B. stripe.com', 'dgptm-suite'));
    echo '</div>';

    submit_button(__('Filter speichern', 'dgptm-suite'));
    echo '</form>';

    echo '<div class="dgptm-desk-card">';
    echo '<h3>' . esc_html__('Filter testen', 'dgptm-suite') . '</h3>';
    $url = wp_nonce_url(admin_url('admin-ajax.php?action=dgptm_desk_filter_test'), 'dgptm_desk_admin', 'nonce');
    echo '<a href="' . esc_url($url) . '" class="button">' . esc_html__('Aktuelle Sichtbarkeit prüfen', 'dgptm-suite') . '</a>';
    echo '</div>';

    if (isset($_GET['filter_result'])) {
        $r = json_decode(rawurldecode((string) $_GET['filter_result']), true);
        if (is_array($r)) {
            echo '<div class="notice notice-info"><p>';
            printf(esc_html__('Sichtbar: %d, Gefiltert: %d. Beispiele für ausgeblendete Mails: %s', 'dgptm-suite'),
                (int) ($r['visible'] ?? 0),
                (int) ($r['hidden']  ?? 0),
                esc_html(implode(', ', array_slice((array) ($r['hidden_samples'] ?? []), 0, 5)))
            );
            echo '</p></div>';
        }
    }
}
```

- [ ] **Step 2: Sanitization-Erweiterung**

In `sanitize_settings` ergänzen, **vor `return $out;`**:

```php
$linelist = function ($v) {
    if (is_array($v)) $v = implode("\n", $v);
    $lines = preg_split('/\r\n|\r|\n/', (string) $v);
    $clean = [];
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l === '') continue;
        $clean[] = sanitize_text_field($l);
    }
    return array_values(array_unique($clean));
};
foreach (['whitelist_domains','whitelist_emails','blacklist_mail_patterns','blacklist_domains'] as $k) {
    if (array_key_exists($k, $input)) {
        $out[$k] = $linelist($input[$k]);
    }
}

// Cache invalidieren bei Filter-Änderungen
$changed = false;
foreach (['whitelist_domains','whitelist_emails','blacklist_mail_patterns','blacklist_domains'] as $k) {
    if (($current[$k] ?? null) !== ($out[$k] ?? null)) { $changed = true; break; }
}
if ($changed) {
    DGPTM_Desk_Cache::flush_all_listings();
}
```

- [ ] **Step 3: AJAX-Filter-Test-Endpoint**

In `class-desk-admin.php`, Konstruktor ergänzen:

```php
add_action('wp_ajax_dgptm_desk_filter_test', [$this, 'ajax_filter_test']);
```

Methode:

```php
public function ajax_filter_test() {
    if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
    if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'dgptm_desk_admin')) {
        wp_die('Bad nonce', 403);
    }
    $api = DGPTM_Desk_API::get_instance();
    $open = $api->list_open();
    if (is_wp_error($open)) {
        wp_safe_redirect(add_query_arg(['page' => 'dgptm-desk-cockpit', 'tab' => 'filter', 'test_err' => rawurlencode($open->get_error_message())], admin_url('admin.php')));
        exit;
    }
    $list = isset($open['data']) ? (array) $open['data'] : [];
    $s = get_option(DGPTM_DESK_OPTION, self::defaults());
    $visible = 0; $hidden = 0; $hidden_samples = [];
    foreach ($list as $t) {
        if (DGPTM_Desk_Filter::is_visible($t, '', $s)) {
            $visible++;
        } else {
            $hidden++;
            $em = DGPTM_Desk_Filter::contact_email($t);
            if ($em) $hidden_samples[] = $em;
        }
    }
    $payload = rawurlencode(wp_json_encode([
        'visible' => $visible,
        'hidden'  => $hidden,
        'hidden_samples' => array_values(array_unique($hidden_samples)),
    ]));
    wp_safe_redirect(add_query_arg(['page' => 'dgptm-desk-cockpit', 'tab' => 'filter', 'filter_result' => $payload], admin_url('admin.php')));
    exit;
}
```

- [ ] **Step 4: Manueller Test**

  1. Tab „Filter" → Domain `dgptm.de` in Whitelist eintragen, Speichern.
  2. „Aktuelle Sichtbarkeit prüfen" → Notice zeigt N sichtbar, M gefiltert + bis zu 5 Beispiel-Mails.
  3. `noreply` aus Blacklist entfernen, neu prüfen → mehr sichtbar.

- [ ] **Step 5: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Admin-Tab Filter mit Live-Test und Cache-Invalidierung"
```

---

### Task 9: Berechtigte-Mitglieder-Tab

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-admin.php` (`render_members_tab`, AJAX-Handler)

- [ ] **Step 1: `render_members_tab` ausschreiben**

```php
private function render_members_tab() {
    if (isset($_GET['saved'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Berechtigungen aktualisiert.', 'dgptm-suite') . '</p></div>';
    }
    $authorized = get_users([
        'meta_key'   => DGPTM_DESK_USER_META,
        'meta_value' => 1,
        'fields'     => ['ID', 'display_name', 'user_email'],
        'orderby'    => 'display_name',
        'order'      => 'ASC',
        'number'     => 500,
    ]);

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('dgptm_desk_members', 'dgptm_desk_members_nonce');
    echo '<input type="hidden" name="action" value="dgptm_desk_members_save"/>';

    echo '<div class="dgptm-desk-card">';
    echo '<h3>' . esc_html__('Mitglied hinzufügen', 'dgptm-suite') . '</h3>';
    echo '<p><input type="email" name="add_email" class="regular-text" placeholder="' . esc_attr__('E-Mail eines bestehenden WP-Users', 'dgptm-suite') . '"/> ';
    submit_button(__('Hinzufügen', 'dgptm-suite'), 'secondary', 'submit_add', false);
    echo '</p></div>';

    echo '<div class="dgptm-desk-card">';
    echo '<h3>' . esc_html__('Aktuell autorisiert', 'dgptm-suite') . '</h3>';
    if (!$authorized) {
        echo '<p>' . esc_html__('Noch niemand autorisiert.', 'dgptm-suite') . '</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>E-Mail</th><th></th></tr></thead><tbody>';
        foreach ($authorized as $u) {
            $remove = wp_nonce_url(admin_url('admin-post.php?action=dgptm_desk_members_save&remove_user=' . (int) $u->ID), 'dgptm_desk_members', 'dgptm_desk_members_nonce');
            printf(
                '<tr><td>%s</td><td>%s</td><td><a class="button-link-delete" href="%s">%s</a></td></tr>',
                esc_html($u->display_name),
                esc_html($u->user_email),
                esc_url($remove),
                esc_html__('Entfernen', 'dgptm-suite')
            );
        }
        echo '</tbody></table>';
    }
    echo '</div>';
    echo '</form>';
}
```

- [ ] **Step 2: Save-Handler im Konstruktor registrieren und implementieren**

Konstruktor ergänzen:

```php
add_action('admin_post_dgptm_desk_members_save', [$this, 'save_members']);
```

Methode:

```php
public function save_members() {
    if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
    check_admin_referer('dgptm_desk_members', 'dgptm_desk_members_nonce');

    $redirect = add_query_arg(['page' => 'dgptm-desk-cockpit', 'tab' => 'members', 'saved' => '1'], admin_url('admin.php'));

    if (!empty($_GET['remove_user'])) {
        $uid = (int) $_GET['remove_user'];
        if ($uid > 0) {
            delete_user_meta($uid, DGPTM_DESK_USER_META);
            DGPTM_Desk_Cache::flush_user($uid);
            DGPTM_Desk_Logger::log('Mitglied entzogen', 'info', ['user_id' => $uid]);
        }
        wp_safe_redirect($redirect);
        exit;
    }

    if (!empty($_POST['add_email'])) {
        $email = sanitize_email(wp_unslash($_POST['add_email']));
        $u = $email ? get_user_by('email', $email) : false;
        if ($u) {
            update_user_meta($u->ID, DGPTM_DESK_USER_META, 1);
            DGPTM_Desk_Cache::flush_user($u->ID);
            DGPTM_Desk_Logger::log('Mitglied autorisiert', 'info', ['user_id' => $u->ID]);
        } else {
            $redirect = add_query_arg(['page' => 'dgptm-desk-cockpit', 'tab' => 'members', 'err' => rawurlencode('User nicht gefunden')], admin_url('admin.php'));
        }
    }

    wp_safe_redirect($redirect);
    exit;
}
```

- [ ] **Step 3: Fehler-Notice für „User nicht gefunden"**

In `render_members_tab` direkt nach dem `saved`-Block:

```php
if (isset($_GET['err'])) {
    echo '<div class="notice notice-error"><p>' . esc_html(rawurldecode((string) $_GET['err'])) . '</p></div>';
}
```

- [ ] **Step 4: Manueller Test**

  1. Tab „Berechtigte Mitglieder" → eigene WP-User-Mail eintragen → Hinzufügen → erscheint in Tabelle.
  2. Per WP-CLI: `wp user meta get <ID> dgptm_desk_cockpit_authorized` → 1.
  3. „Entfernen"-Klick → Tabelle leer, User-Meta weg.
  4. Cap-Check: `wp user list --field=ID,user_email,roles` und dann `wp eval 'var_dump(user_can(<ID>, "dgptm_desk_cockpit_view"));'` → bei autorisiertem User `true`, sonst `false`.

- [ ] **Step 5: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Berechtigte-Mitglieder-Tab mit Hinzufuegen/Entfernen"
```

---

### Task 10: Frontend-CSS und JS-Bundles (Designsprache Umfragen)

**Files:**
- Create: `modules/business/zoho-desk-cockpit/assets/css/frontend.css`
- Create: `modules/business/zoho-desk-cockpit/assets/js/frontend.js`

**Designvorgabe:** Tokens analog Umfragen-Modul, Buttons als `dgptm-fe-btn`, Karten/Modals folgen `dgptm-fe-*`-Konvention.

- [ ] **Step 1: `frontend.css` anlegen**

```css
:root {
    --dgptm-primary: #4f46e5;
    --dgptm-primary-hover: #4338ca;
    --dgptm-primary-light: #eef2ff;
    --dgptm-success: #10b981;
    --dgptm-error:   #ef4444;
    --dgptm-warning: #f59e0b;
    --dgptm-gray-50:  #f9fafb;
    --dgptm-gray-100: #f3f4f6;
    --dgptm-gray-200: #e5e7eb;
    --dgptm-gray-500: #6b7280;
    --dgptm-gray-700: #374151;
    --dgptm-gray-900: #111827;
    --dgptm-radius: 12px;
    --dgptm-radius-sm: 8px;
    --dgptm-shadow: 0 1px 2px rgba(0,0,0,0.05);
    --dgptm-shadow-md: 0 4px 6px rgba(0,0,0,0.07);
    --dgptm-shadow-lg: 0 10px 25px rgba(0,0,0,0.10);
    --dgptm-transition: 0.2s ease;
}

.dgptm-desk { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--dgptm-gray-900); }
.dgptm-desk-header {
    display:flex; align-items:center; justify-content:space-between; gap:16px;
    background:#fff; border:1px solid var(--dgptm-gray-200); border-radius:var(--dgptm-radius);
    padding:14px 18px; box-shadow:var(--dgptm-shadow); margin-bottom:14px;
}
.dgptm-desk-header .meta { color:var(--dgptm-gray-500); font-size:13px; }

.dgptm-fe-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:10px 16px; border-radius:var(--dgptm-radius-sm); font-weight:600; cursor:pointer;
    border:1px solid transparent; transition: var(--dgptm-transition);
    background:var(--dgptm-gray-100); color:var(--dgptm-gray-900);
}
.dgptm-fe-btn:hover { background:var(--dgptm-gray-200); }
.dgptm-fe-btn--primary { background:var(--dgptm-primary); color:#fff; box-shadow:var(--dgptm-shadow); }
.dgptm-fe-btn--primary:hover { background:var(--dgptm-primary-hover); }

.dgptm-fe-tabs { display:flex; gap:4px; border-bottom:2px solid var(--dgptm-gray-200); margin-bottom:14px; }
.dgptm-fe-tab {
    padding:10px 16px; cursor:pointer; border:none; background:transparent;
    color:var(--dgptm-gray-500); font-weight:600; border-bottom:2px solid transparent; margin-bottom:-2px;
}
.dgptm-fe-tab.is-active { color:var(--dgptm-primary); border-bottom-color:var(--dgptm-primary); }

.dgptm-desk-list { background:#fff; border:1px solid var(--dgptm-gray-200); border-radius:var(--dgptm-radius); box-shadow:var(--dgptm-shadow); overflow:hidden; }
.dgptm-desk-row {
    display:grid; grid-template-columns: 90px 1fr 1.2fr 110px 140px 110px;
    gap:12px; padding:12px 16px; align-items:center; border-bottom:1px solid var(--dgptm-gray-100);
    cursor:pointer; transition: background var(--dgptm-transition);
}
.dgptm-desk-row:hover { background:var(--dgptm-gray-50); }
.dgptm-desk-row:last-child { border-bottom:none; }
.dgptm-desk-row .num { color:var(--dgptm-gray-500); font-variant-numeric: tabular-nums; }
.dgptm-desk-row .subject { font-weight:600; }
.dgptm-desk-row .contact { color:var(--dgptm-gray-700); font-size:14px; }
.dgptm-desk-row .badge {
    display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600;
    background:var(--dgptm-gray-100); color:var(--dgptm-gray-700);
}
.dgptm-desk-row .badge.open    { background:#e0e7ff; color:#3730a3; }
.dgptm-desk-row .badge.hold    { background:#fef3c7; color:#92400e; }
.dgptm-desk-row .badge.escalated { background:#fee2e2; color:#991b1b; }
.dgptm-desk-row .badge.closed  { background:#d1fae5; color:#065f46; }

.dgptm-desk-empty {
    background:#fff; border:1px dashed var(--dgptm-gray-200); border-radius:var(--dgptm-radius);
    padding:30px; text-align:center; color:var(--dgptm-gray-500);
}

.dgptm-desk-banner-stale {
    background:#fffbeb; border:1px solid #fde68a; color:#92400e;
    padding:10px 14px; border-radius:var(--dgptm-radius-sm); margin-bottom:12px;
}

.dgptm-fe-modal-backdrop {
    position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9998;
    display:none; align-items:flex-start; justify-content:center; padding:40px 16px; overflow:auto;
}
.dgptm-fe-modal-backdrop.is-open { display:flex; }
.dgptm-fe-modal {
    background:#fff; border-radius:var(--dgptm-radius); box-shadow:var(--dgptm-shadow-lg);
    max-width:780px; width:100%; padding:24px;
}
.dgptm-fe-modal h2 { margin:0 0 12px; }
.dgptm-fe-modal .blueprint { background:var(--dgptm-primary-light); color:var(--dgptm-primary); padding:10px 14px; border-radius:var(--dgptm-radius-sm); margin:10px 0; }
.dgptm-fe-modal .comment { border-top:1px solid var(--dgptm-gray-100); padding:12px 0; }
.dgptm-fe-modal .comment .author { font-weight:600; }
.dgptm-fe-modal .comment .meta { color:var(--dgptm-gray-500); font-size:12px; margin-bottom:6px; }
.dgptm-fe-modal .comment.private .meta::before { content:"Privat · "; color:var(--dgptm-warning); }

@media (max-width: 720px) {
    .dgptm-desk-row { grid-template-columns: 1fr; gap:4px; }
    .dgptm-desk-row .num { font-size:12px; }
}
```

- [ ] **Step 2: `frontend.js` anlegen (Skelett)**

```js
(function ($) {
    'use strict';
    if (typeof DGPTM_DESK === 'undefined') return;

    function showTab(root, name) {
        root.find('.dgptm-fe-tab').removeClass('is-active');
        root.find('[data-tab="' + name + '"]').addClass('is-active');
        root.find('.dgptm-desk-tab-pane').hide();
        root.find('.dgptm-desk-tab-pane[data-pane="' + name + '"]').show();
    }

    $(function () {
        $('.dgptm-desk').each(function () {
            const root = $(this);
            showTab(root, 'open');

            root.on('click', '.dgptm-fe-tab', function () {
                showTab(root, $(this).data('tab'));
            });

            root.on('click', '.dgptm-desk-row', function () {
                openDetail(root, $(this).data('id'));
            });

            root.on('click', '.dgptm-desk-refresh', function (e) {
                e.preventDefault();
                $.post(DGPTM_DESK.ajaxurl, {
                    action: 'dgptm_desk_refresh',
                    nonce: DGPTM_DESK.nonce
                }, function () { window.location.reload(); });
            });

            $(document).on('click', '.dgptm-fe-modal-backdrop, .dgptm-fe-modal-close', function (e) {
                if (e.target === this) $('.dgptm-fe-modal-backdrop').removeClass('is-open');
            });
        });
    });

    function openDetail(root, ticketId) {
        const backdrop = $('<div class="dgptm-fe-modal-backdrop is-open"><div class="dgptm-fe-modal"><p>Lädt…</p></div></div>');
        $('body').append(backdrop);
        $.post(DGPTM_DESK.ajaxurl, {
            action: 'dgptm_desk_get_ticket',
            nonce: DGPTM_DESK.nonce,
            id: ticketId
        }, function (resp) {
            if (resp && resp.success) {
                backdrop.find('.dgptm-fe-modal').html(resp.data.html);
            } else {
                backdrop.find('.dgptm-fe-modal').html('<p>Fehler beim Laden.</p>');
            }
        }).fail(function () {
            backdrop.find('.dgptm-fe-modal').html('<p>Fehler beim Laden.</p>');
        });
    }
})(jQuery);
```

- [ ] **Step 3: Manueller Test**

Noch keine sichtbare Wirkung — wird in Task 11 + 12 verbunden. Smoke-Test: WP-Admin → System Logs → keine Fehler. `php -l` auf alle PHP-Files lokal: `php -l modules/business/zoho-desk-cockpit/**/*.php` (PowerShell: foreach-Loop nötig).

- [ ] **Step 4: Commit**

```bash
git add modules/business/zoho-desk-cockpit/assets/
git commit -m "feat(zoho-desk-cockpit): Frontend-CSS und JS-Bundle in Umfragen-Designsprache"
```

---

### Task 11: Shortcode-Listing mit Tabs

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-shortcode.php`
- Create: `modules/business/zoho-desk-cockpit/templates/frontend-cockpit.php`

- [ ] **Step 1: `class-desk-shortcode.php` ausbauen**

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Desk_Shortcode {
    private static $instance = null;
    public static function get_instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('dgptm_desk_cockpit', [$this, 'render']);
        add_action('wp_ajax_dgptm_desk_get_ticket', [$this, 'ajax_get_ticket']);
        add_action('wp_ajax_dgptm_desk_refresh',    [$this, 'ajax_refresh']);
    }

    private function enqueue() {
        wp_enqueue_style(
            'dgptm-desk-cockpit-frontend',
            DGPTM_DESK_URL . 'assets/css/frontend.css',
            [],
            DGPTM_DESK_VERSION
        );
        wp_enqueue_script(
            'dgptm-desk-cockpit-frontend',
            DGPTM_DESK_URL . 'assets/js/frontend.js',
            ['jquery'],
            DGPTM_DESK_VERSION,
            true
        );
        wp_localize_script('dgptm-desk-cockpit-frontend', 'DGPTM_DESK', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('dgptm_desk_front'),
        ]);
    }

    public function render($atts) {
        $this->enqueue();

        if (!is_user_logged_in()) {
            return '<div class="dgptm-desk dgptm-desk-empty">' . esc_html__('Bitte einloggen, um deine Anfragen zu sehen.', 'dgptm-suite') . '</div>';
        }
        if (!current_user_can(DGPTM_DESK_CAPABILITY)) {
            return '<div class="dgptm-desk dgptm-desk-empty">' . esc_html__('Dieser Bereich ist nur für autorisierte Mitglieder. Bei Fragen wende dich an die Geschäftsstelle.', 'dgptm-suite') . '</div>';
        }

        $user        = wp_get_current_user();
        $user_email  = strtolower($user->user_email);
        $settings    = get_option(DGPTM_DESK_OPTION, DGPTM_Desk_Admin::defaults());
        $filter_hash = DGPTM_Desk_Filter::filter_hash($settings, $user_email);

        $cached = DGPTM_Desk_Cache::get_listing($user->ID, $filter_hash);
        $stale  = false;

        if (!$cached) {
            $api    = DGPTM_Desk_API::get_instance();
            $open   = $api->list_open();
            $closed = $api->list_closed_last_7_days();

            if (is_wp_error($open) || is_wp_error($closed)) {
                $cached = DGPTM_Desk_Cache::get_listing_stale($user->ID, $filter_hash);
                $stale  = true;
                if (!$cached) {
                    return '<div class="dgptm-desk dgptm-desk-empty">' . esc_html__('Verbindung zu Zoho Desk unterbrochen, bitte später erneut versuchen.', 'dgptm-suite') . '</div>';
                }
            } else {
                $open_filtered   = DGPTM_Desk_Filter::filter_list($open,   $user_email, $settings);
                $closed_filtered = DGPTM_Desk_Filter::filter_list($closed, $user_email, $settings);
                $cached = [
                    'open'      => $open_filtered,
                    'closed'    => $closed_filtered,
                    'fetched_at' => time(),
                ];
                DGPTM_Desk_Cache::set_listing($user->ID, $filter_hash, $cached);
            }
        }

        ob_start();
        $tickets_open   = $cached['open']   ?? [];
        $tickets_closed = $cached['closed'] ?? [];
        $fetched_at     = (int) ($cached['fetched_at'] ?? time());
        include DGPTM_DESK_PATH . 'templates/frontend-cockpit.php';
        return ob_get_clean();
    }

    public function ajax_refresh() {
        if (!is_user_logged_in() || !current_user_can(DGPTM_DESK_CAPABILITY)) wp_send_json_error('forbidden', 403);
        if (!check_ajax_referer('dgptm_desk_front', 'nonce', false)) wp_send_json_error('bad_nonce', 403);
        DGPTM_Desk_Cache::flush_user(get_current_user_id());
        wp_send_json_success();
    }

    public function ajax_get_ticket() {
        // Implementierung in Task 12
        wp_send_json_error('not_implemented', 501);
    }

    /**
     * Hilfsfunktionen für Templates.
     */
    public static function status_class($status) {
        $s = strtolower((string) $status);
        if (strpos($s, 'closed')   !== false) return 'closed';
        if (strpos($s, 'hold')     !== false) return 'hold';
        if (strpos($s, 'escalat')  !== false) return 'escalated';
        return 'open';
    }
    public static function relative_time($iso) {
        if (!$iso) return '—';
        $ts = strtotime($iso);
        if (!$ts) return esc_html($iso);
        return human_time_diff($ts, time()) . ' ' . __('zurück', 'dgptm-suite');
    }
    public static function blueprint_label($ticket) {
        if (isset($ticket['blueprint']['name']) && $ticket['blueprint']['name'] !== '') return (string) $ticket['blueprint']['name'];
        if (isset($ticket['blueprintCurrentStage']) && $ticket['blueprintCurrentStage'] !== '') return (string) $ticket['blueprintCurrentStage'];
        return '—';
    }
    public static function contact_label($ticket) {
        $em = DGPTM_Desk_Filter::contact_email($ticket);
        $first = $ticket['contact']['firstName'] ?? '';
        $last  = $ticket['contact']['lastName']  ?? '';
        $name = trim($first . ' ' . $last);
        return $name !== '' ? ($name . ($em ? ' <' . $em . '>' : '')) : $em;
    }
}
```

- [ ] **Step 2: Template `templates/frontend-cockpit.php`**

```php
<?php
if (!defined('ABSPATH')) exit;
/** @var array $tickets_open */
/** @var array $tickets_closed */
/** @var int   $fetched_at */
/** @var bool  $stale */
?>
<div class="dgptm-desk">
    <div class="dgptm-desk-header">
        <div>
            <strong><?php echo (int) count($tickets_open); ?></strong>
            <?php esc_html_e('offene Anfragen', 'dgptm-suite'); ?>
            <span class="meta">·
                <?php
                printf(
                    esc_html__('Stand %s', 'dgptm-suite'),
                    esc_html(wp_date('H:i', $fetched_at))
                );
                ?>
            </span>
        </div>
        <button class="dgptm-fe-btn dgptm-desk-refresh"><?php esc_html_e('Aktualisieren', 'dgptm-suite'); ?></button>
    </div>

    <?php if (!empty($stale)): ?>
        <div class="dgptm-desk-banner-stale">
            <?php esc_html_e('Daten könnten veraltet sein — Verbindung zu Zoho Desk hat zuletzt nicht funktioniert.', 'dgptm-suite'); ?>
        </div>
    <?php endif; ?>

    <div class="dgptm-fe-tabs">
        <button class="dgptm-fe-tab is-active" data-tab="open"><?php esc_html_e('Offen', 'dgptm-suite'); ?></button>
        <button class="dgptm-fe-tab" data-tab="closed"><?php esc_html_e('Erledigt (7 Tage)', 'dgptm-suite'); ?></button>
    </div>

    <div class="dgptm-desk-tab-pane" data-pane="open">
        <?php if (!$tickets_open): ?>
            <div class="dgptm-desk-empty"><?php esc_html_e('Aktuell keine offenen Anfragen sichtbar.', 'dgptm-suite'); ?></div>
        <?php else: ?>
            <div class="dgptm-desk-list">
                <?php foreach ($tickets_open as $t): ?>
                    <div class="dgptm-desk-row" data-id="<?php echo esc_attr($t['id'] ?? ''); ?>">
                        <span class="num">#<?php echo esc_html($t['ticketNumber'] ?? ''); ?></span>
                        <span class="subject"><?php echo esc_html($t['subject'] ?? '—'); ?></span>
                        <span class="contact"><?php echo esc_html(DGPTM_Desk_Shortcode::contact_label($t)); ?></span>
                        <span class="badge <?php echo esc_attr(DGPTM_Desk_Shortcode::status_class($t['status'] ?? '')); ?>"><?php echo esc_html($t['status'] ?? ''); ?></span>
                        <span class="blueprint-stage"><?php echo esc_html(DGPTM_Desk_Shortcode::blueprint_label($t)); ?></span>
                        <span class="time"><?php echo esc_html(DGPTM_Desk_Shortcode::relative_time($t['modifiedTime'] ?? '')); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="dgptm-desk-tab-pane" data-pane="closed" style="display:none;">
        <?php if (!$tickets_closed): ?>
            <div class="dgptm-desk-empty"><?php esc_html_e('Keine erledigten Anfragen in den letzten 7 Tagen.', 'dgptm-suite'); ?></div>
        <?php else: ?>
            <div class="dgptm-desk-list">
                <?php foreach ($tickets_closed as $t): ?>
                    <div class="dgptm-desk-row" data-id="<?php echo esc_attr($t['id'] ?? ''); ?>">
                        <span class="num">#<?php echo esc_html($t['ticketNumber'] ?? ''); ?></span>
                        <span class="subject"><?php echo esc_html($t['subject'] ?? '—'); ?></span>
                        <span class="contact"><?php echo esc_html(DGPTM_Desk_Shortcode::contact_label($t)); ?></span>
                        <span class="badge closed"><?php echo esc_html($t['status'] ?? 'Closed'); ?></span>
                        <span class="blueprint-stage"><?php echo esc_html(DGPTM_Desk_Shortcode::blueprint_label($t)); ?></span>
                        <span class="time"><?php echo esc_html(DGPTM_Desk_Shortcode::relative_time($t['closedTime'] ?? ($t['modifiedTime'] ?? ''))); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
```

- [ ] **Step 3: Manueller Test**

  1. Eigener User ist autorisiert (Task 9 erledigt).
  2. Eine WP-Seite mit Shortcode `[dgptm_desk_cockpit]` anlegen (private/Mitglieder-Page).
  3. Aufrufen → Header mit Anzahl, Tabs „Offen"/„Erledigt (7 Tage)", Liste oder Empty-State.
  4. Tab-Switch funktioniert (JS).
  5. „Aktualisieren"-Button → Reload, Daten frisch.
  6. Logout-Test: ohne Login → Hinweis-Box.
  7. Capability-Test: User entziehen → „Nur für autorisierte Mitglieder"-Box.

- [ ] **Step 4: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Shortcode-Listing mit Offen/Erledigt-Tabs"
```

---

### Task 12: Detail-Modal (Kommentare + Blueprint)

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-shortcode.php` (`ajax_get_ticket`)
- Create: `modules/business/zoho-desk-cockpit/templates/frontend-detail.php`

- [ ] **Step 1: `ajax_get_ticket` ausschreiben**

In `class-desk-shortcode.php` die Stub-Methode ersetzen:

```php
public function ajax_get_ticket() {
    if (!is_user_logged_in() || !current_user_can(DGPTM_DESK_CAPABILITY)) wp_send_json_error('forbidden', 403);
    if (!check_ajax_referer('dgptm_desk_front', 'nonce', false)) wp_send_json_error('bad_nonce', 403);

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) wp_send_json_error('bad_id', 400);

    $cached = DGPTM_Desk_Cache::get_detail($id);
    if (!$cached) {
        $api      = DGPTM_Desk_API::get_instance();
        $ticket   = $api->get_ticket($id);
        if (is_wp_error($ticket)) wp_send_json_error($ticket->get_error_message(), 502);

        // IDOR-Schutz: Sichtbarkeit gegen Filter prüfen
        $user     = wp_get_current_user();
        $settings = get_option(DGPTM_DESK_OPTION, DGPTM_Desk_Admin::defaults());
        if (!DGPTM_Desk_Filter::is_visible($ticket, strtolower($user->user_email), $settings)) {
            wp_send_json_error('forbidden', 403);
        }

        $comments  = $api->get_comments($id);
        $blueprint = $api->get_blueprint($id);

        $cached = [
            'ticket'    => $ticket,
            'comments'  => is_wp_error($comments)  ? ['data' => []] : $comments,
            'blueprint' => is_wp_error($blueprint) ? null            : $blueprint,
        ];
        DGPTM_Desk_Cache::set_detail($id, $cached);
    }

    ob_start();
    $ticket    = $cached['ticket'];
    $comments  = $cached['comments']['data']  ?? [];
    $blueprint = $cached['blueprint'];
    include DGPTM_DESK_PATH . 'templates/frontend-detail.php';
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}
```

- [ ] **Step 2: `templates/frontend-detail.php`**

```php
<?php
if (!defined('ABSPATH')) exit;
/** @var array $ticket */
/** @var array $comments */
/** @var array|null $blueprint */
$web_url = $ticket['webUrl'] ?? '';
$descr   = isset($ticket['description']) ? wp_kses_post($ticket['description']) : '';
$bp_stage = '';
if (is_array($blueprint)) {
    $bp_stage = $blueprint['data']['currentStage']['name']
        ?? $blueprint['currentStage']['name']
        ?? $blueprint['blueprintCurrentStage']
        ?? '';
}
?>
<button class="dgptm-fe-btn dgptm-fe-modal-close" style="float:right">×</button>
<h2>#<?php echo esc_html($ticket['ticketNumber'] ?? ''); ?> · <?php echo esc_html($ticket['subject'] ?? ''); ?></h2>
<p class="meta">
    <?php echo esc_html(DGPTM_Desk_Shortcode::contact_label($ticket)); ?>
    · <span class="badge <?php echo esc_attr(DGPTM_Desk_Shortcode::status_class($ticket['status'] ?? '')); ?>"><?php echo esc_html($ticket['status'] ?? ''); ?></span>
</p>

<?php if ($bp_stage): ?>
    <div class="blueprint">
        <strong><?php esc_html_e('Blueprint-Stand:', 'dgptm-suite'); ?></strong>
        <?php echo esc_html($bp_stage); ?>
    </div>
<?php endif; ?>

<?php if ($descr): ?>
    <div class="description"><?php echo $descr; // bereits via wp_kses_post sanitized ?></div>
<?php endif; ?>

<h3><?php esc_html_e('Kommentare', 'dgptm-suite'); ?></h3>
<?php if (!$comments): ?>
    <p class="meta"><?php esc_html_e('Keine Kommentare.', 'dgptm-suite'); ?></p>
<?php else: ?>
    <?php foreach ($comments as $c):
        $is_private = !empty($c['isPublic']) ? false : true;
        $author = $c['commenter']['name'] ?? ($c['commenter']['firstName'] ?? '—');
        $when   = $c['commentedTime'] ?? '';
        $body   = isset($c['content']) ? wp_kses_post($c['content']) : '';
    ?>
        <div class="comment <?php echo $is_private ? 'private' : 'public'; ?>">
            <div class="author"><?php echo esc_html($author); ?></div>
            <div class="meta"><?php echo esc_html($when); ?></div>
            <div class="body"><?php echo $body; ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($web_url): ?>
    <p style="margin-top:14px;">
        <a class="dgptm-fe-btn dgptm-fe-btn--primary" href="<?php echo esc_url($web_url); ?>" target="_blank" rel="noopener">
            <?php esc_html_e('In Zoho Desk öffnen', 'dgptm-suite'); ?>
        </a>
    </p>
<?php endif; ?>
```

- [ ] **Step 3: Manueller Test**

  1. Cockpit-Seite, eine Ticket-Zeile anklicken → Modal öffnet, zeigt Subject, Status-Badge, Blueprint-Stand (falls vorhanden), Description und Kommentare.
  2. „×" oder Klick auf Backdrop schließt Modal.
  3. Negativ-Test (IDOR): in Browser-Devtools Ticket-ID manipulieren, die nicht in Whitelist und nicht eigene ist → 403/Fehlermeldung im Modal.
  4. „In Zoho Desk öffnen" öffnet Desk-Web-UI in neuem Tab.

- [ ] **Step 4: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Detail-Modal mit Kommentaren und Blueprint-Stand"
```

---

### Task 13: Stale-Cache-Banner und Refresh-Härtung

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/includes/class-desk-shortcode.php` (Stale-Variable an Template)
- Modify: `modules/business/zoho-desk-cockpit/templates/frontend-cockpit.php` (bereits prepared)

- [ ] **Step 1: `$stale`-Flag an Template übergeben**

In `class-desk-shortcode.php`, Methode `render`, vor `include DGPTM_DESK_PATH . 'templates/frontend-cockpit.php';` ergänzen:

```php
$stale = !empty($stale); // war evtl. bereits in der Schleife oben gesetzt; explizit boolen
```

(Das Template prüft `if (!empty($stale))` bereits — kein weiterer Code nötig, nur Sicherstellen dass die Variable im Scope ist.)

- [ ] **Step 2: Manueller Test (Stale-Pfad)**

  1. Lokale Internet-Verbindung blockieren oder im Modul `class-desk-api.php`: `request()` temporär `return new WP_Error('test', 'simulated outage');` ergänzen.
  2. Cockpit-Seite mit eingelogtem autorisiertem User → bei vorhandenem Cache: Stale-Banner sichtbar.
  3. Banner verschwindet nach erfolgreichem Pull (Test-Mod entfernen, Refresh).

- [ ] **Step 3: Commit**

```bash
git add modules/business/zoho-desk-cockpit/
git commit -m "feat(zoho-desk-cockpit): Stale-Banner aktiv bei Desk-Ausfall"
```

---

### Task 14: Manuelle End-to-End-Test-Checkliste + README-Eintrag

**Files:**
- Modify: `modules/business/zoho-desk-cockpit/dgptm-zoho-desk-cockpit.php` (Header-Description final)
- Create: `modules/business/zoho-desk-cockpit/README.md`

- [ ] **Step 1: README mit Test-Checkliste**

```markdown
# Zoho Desk Cockpit

Mitglieder-Cockpit für Zoho-Desk-Tickets in der DGPTM-Suite.

## Setup

1. **Zoho Self-Client** anlegen (Zoho API Console → Self-Client → "Server-based Apps"):
   - Scopes: `Desk.tickets.READ,Desk.contacts.READ,Desk.basic.READ,Desk.search.READ`
   - Redirect-URI: `https://<host>/wp-admin/admin-ajax.php?action=dgptm_desk_oauth_callback`
2. **Modul aktivieren** in DGPTM-Suite.
3. **Tab "OAuth & Verbindung"**: Region, Org-ID, Client-ID, Client-Secret eintragen → Speichern → "Mit Zoho verbinden".
4. **Tab "Berechtigte Mitglieder"**: Mitglieder-Mails autorisieren.
5. **Tab "Filter"**: Whitelist Domains/Mails + Blacklist nach Bedarf pflegen.
6. **Shortcode** `[dgptm_desk_cockpit]` auf einer geschützten Mitgliederseite einfügen.

## Test-Checkliste (manuell)

- [ ] Modul aktivieren/deaktivieren ohne Fatal Errors
- [ ] OAuth-Connect erfolgreich, Tokens verschlüsselt in `wp_options`
- [ ] Verbindungstest pullt Tickets
- [ ] Cron `dgptm_desk_token_refresh_cron` ist registriert (`wp cron event list`)
- [ ] Autorisiertes Mitglied sieht Cockpit, nicht-autorisiertes nicht
- [ ] Filter blendet konfigurierte Mails aus, eigene Mail bleibt sichtbar
- [ ] Tab "Erledigt (7 Tage)" zeigt nur Closed-Tickets der letzten 7 Tage
- [ ] Detail-Modal zeigt Kommentare und Blueprint-Stand
- [ ] IDOR-Schutz: Direktaufruf einer nicht erlaubten Ticket-ID via AJAX → 403
- [ ] Stale-Banner bei simuliertem Desk-Ausfall
- [ ] "Aktualisieren"-Button leert User-Cache und reloaded
- [ ] Filter-Save invalidiert alle Listing-Caches
- [ ] Profile-Update (E-Mail-Wechsel) leert User-Cache
- [ ] Designsprache: Tokens `--dgptm-primary` etc. sichtbar (DevTools), Buttons `dgptm-fe-btn`, keine Elementor-Vererbung
```

- [ ] **Step 2: Test-Checkliste manuell durchgehen**

Jeden Punkt der README-Checkliste ankreuzen / Fehler beheben. Dabei besonders auf:
  - Anzeige bei leerer Liste (Empty-State)
  - Verhalten bei abgelaufenem Refresh-Token (Refresh-Token in DB temporär verfälschen → Admin-Notice)
  - Mobile-Ansicht (Browser-Devtools, ≤720px → Karten-Layout)

- [ ] **Step 3: PHP-Syntax-Check vor Commit**

```bash
# Aus Projekt-Root:
find modules/business/zoho-desk-cockpit -name '*.php' -exec /d/php/php -l {} \;
```

Alle Files müssen „No syntax errors detected" zurückgeben.

- [ ] **Step 4: Commit**

```bash
git add modules/business/zoho-desk-cockpit/README.md
git commit -m "docs(zoho-desk-cockpit): README mit Setup und Test-Checkliste"
```

---

### Task 15: Deploy & Abnahme auf Produktion

**Files:** keine

- [ ] **Step 1: Push auf `main`**

```bash
git push origin main
```

GitHub Actions führt aus: PHP-Syntax-Check → rsync → Cache-Flush.

- [ ] **Step 2: Deploy-Status prüfen**

```bash
gh run list --workflow=deploy.yml --limit 1
gh run watch
```

Erwartet: `success`.

- [ ] **Step 3: Live-Smoke-Test auf perfusiologie.de**

  1. WP-Admin → DGPTM-Suite → Modul `zoho-desk-cockpit` aktivieren (falls nicht).
  2. OAuth-Connect mit Live-Self-Client-Daten der DGPTM-Geschäftsstelle.
  3. Mindestens einen Test-User autorisieren (z. B. eigene Mail).
  4. Test-Seite mit Shortcode aufrufen, Funktionen prüfen.

- [ ] **Step 4: Logger-Smoke**

WP-Admin → DGPTM-Suite → System Logs → Modul `zoho-desk-cockpit`: keine Fehler in den letzten 30 Min, OAuth-/Refresh-Events sichtbar.

---

## Self-Review-Notizen

**Spec-Coverage geprüft:**
- ✅ Architektur (Singleton, Klassen-Layout) → Task 1
- ✅ Datenfluss Listen-Pull mit 5-Min-Transient → Task 5, 7, 11
- ✅ Datenfluss Detail-Pull mit 60-Sek-Transient + IDOR-Schutz → Task 12
- ✅ Token-Refresh-Cron → Task 4
- ✅ Filter-Logik (Whitelist/Blacklist, eigene Mail bevorzugt) → Task 6
- ✅ Frontend-Tabs „Offen" / „Erledigt 7 Tage" → Task 11
- ✅ Detail-Modal Kommentare + Blueprint → Task 12
- ✅ Designsprache Umfragen → Task 10
- ✅ Admin-Tabs OAuth, Mitglieder, Filter → Tasks 3, 9, 8
- ✅ OAuth Self-Client EU/COM/IN/AU/JP → Task 3
- ✅ AES-256-CBC mit AUTH_KEY → Task 3
- ✅ Stale-Cache + Banner → Task 7, 13
- ✅ Capability-Filter via `user_has_cap` → Task 1
- ✅ Cache-Invalidierung bei Filter-Save / Profile-Update / Mitglieder-Änderung → Tasks 1, 7, 8, 9
- ✅ SSRF-Schutz Host-Whitelist → Task 5

**Bekannte offene Detail-Verifikationen während Implementation:**
- Genaue Desk-API-Parameter `modifiedTimeRange` vs `closedTimeRange` — Doku-Lookup beim Bauen, ggf. Anpassung in Task 5.
- Blueprint-Response-Schema (Pfad zu `currentStage.name`) — Live-Antwort beim ersten Test prüfen, ggf. `blueprint_label` anpassen.
- Status-Werte der DGPTM-Desk-Instanz (Open / On Hold / Escalated / Closed sind Default-Werte; Custom-Status ggf. ergänzen) — beim Verbindungstest in Task 5 sichtbar.
