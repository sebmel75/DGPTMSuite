# Artikel-Einreichung Dashboard + Reviewer/Autoren-Management — Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Artikel-Einreichung Dashboard-kompatibel machen, Reviewer-Gruppen mit CRM-Anbindung und automatisches Rollen-Management fuer Reviewer und Autoren.

**Architecture:** Dashboard-CSS-Overrides fuer alle 5 Shortcodes, neue `class-crm-reviewer.php` fuer Zoho CRM Contact-Suche/Anlage, Reviewer-Pool-Datenstruktur erweitern (aktiv/inaktiv + zoho_id), automatische WP-Rollen-Vergabe (`reviewer`, `zeitschrift_autor`), User-Anlage bei Nicht-Mitgliedern.

**Tech Stack:** WordPress/PHP 7.4+, jQuery, ACF, Zoho CRM v7 API (OAuth2)

**Spec:** `docs/superpowers/specs/2026-03-28-artikel-einreichung-dashboard-design.md`

---

## File Structure

### New Files
- `modules/content/artikel-einreichung/includes/class-crm-reviewer.php` — Zoho CRM Contact-Suche/Anlage/Verknuepfung
- `modules/content/artikel-einreichung/includes/class-role-manager.php` — WP-Rollen reviewer + zeitschrift_autor verwalten

### Modified Files
- `modules/content/artikel-einreichung/artikel-einreichung.php` — AJAX-Handler erweitern, Pool-Migration, Rollen-Hooks
- `modules/content/artikel-einreichung/assets/css/frontend.css` — Dashboard-Overrides
- `modules/content/artikel-einreichung/assets/js/admin.js` — Reviewer-Suche + CRM, aktiv/inaktiv Toggle
- `modules/content/artikel-einreichung/templates/submission-form.php` — Login-Hinweis
- `modules/content/artikel-einreichung/templates/admin/reviewers.php` — Aktiv/Inaktiv UI, CRM-Badge

---

## Task 1: Dashboard-CSS Forum-Overrides

**Files:**
- Modify: `modules/content/artikel-einreichung/assets/css/frontend.css` (append after line 753)

- [ ] **Step 1: Dashboard-Override-Block anfuegen**

Am Ende von `frontend.css` (nach dem letzten `}` auf Zeile 753) einfuegen:

```css
/* ========================================
   Dashboard-Integration (Forum-Vorbild)
   ======================================== */

/* Container-Reset */
.dgptm-dash .dgptm-artikel-container { max-width: 100%; padding: 0; }
.dgptm-dash .dgptm-artikel-form { padding: 0; background: none; border: none; box-shadow: none; border-radius: 0; }

/* Headings */
.dgptm-dash .dgptm-artikel-container h2 { font-size: 15px; margin: 0 0 12px; color: #1d2327; }
.dgptm-dash .dgptm-artikel-container h3 { font-size: 14px; margin: 16px 0 8px; color: #1d2327; }

/* Buttons: Forum-Stil */
.dgptm-dash .dgptm-artikel-container .btn,
.dgptm-dash .dgptm-artikel-container button[type="submit"],
.dgptm-dash .dgptm-artikel-container button,
.dgptm-dash .dgptm-artikel-container .btn-primary {
    display: inline-block !important;
    padding: 4px 10px !important;
    border: 1px solid #0073aa !important;
    border-radius: 4px !important;
    background: #0073aa !important;
    color: #fff !important;
    font-size: 12px !important;
    font-weight: 400 !important;
    line-height: 1.4 !important;
    text-decoration: none !important;
    min-height: 0 !important;
    height: auto !important;
    box-shadow: none !important;
    transition: background .15s;
}
.dgptm-dash .dgptm-artikel-container .btn:hover,
.dgptm-dash .dgptm-artikel-container button:hover { background: #005d8c !important; }

.dgptm-dash .dgptm-artikel-container .btn-secondary { background: #f0f0f0 !important; border-color: #ccc !important; color: #555 !important; }
.dgptm-dash .dgptm-artikel-container .btn-secondary:hover { background: #e0e0e0 !important; }
.dgptm-dash .dgptm-artikel-container .btn-success { background: #46b450 !important; border-color: #46b450 !important; }
.dgptm-dash .dgptm-artikel-container .btn-danger { background: #dc3232 !important; border-color: #dc3232 !important; }

/* Inputs */
.dgptm-dash .dgptm-artikel-container input[type="text"],
.dgptm-dash .dgptm-artikel-container input[type="email"],
.dgptm-dash .dgptm-artikel-container input[type="url"],
.dgptm-dash .dgptm-artikel-container input[type="date"],
.dgptm-dash .dgptm-artikel-container textarea,
.dgptm-dash .dgptm-artikel-container select {
    padding: 8px !important;
    border: 1px solid #ccc !important;
    border-radius: 4px !important;
    font-size: 14px !important;
    box-shadow: none !important;
}

/* Labels */
.dgptm-dash .dgptm-artikel-container label { font-weight: 500; font-size: 13px; margin-bottom: 4px; }

/* Tables */
.dgptm-dash .dgptm-artikel-table { box-shadow: none; border-radius: 0; }
.dgptm-dash .dgptm-artikel-table thead { background: none; }
.dgptm-dash .dgptm-artikel-table th { color: #1d2327; padding: 8px 12px; font-size: 12px; font-weight: 600; text-transform: none; border-bottom: 2px solid #eee; background: none; }
.dgptm-dash .dgptm-artikel-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
.dgptm-dash .dgptm-artikel-table tbody tr:hover { background: #f8f9fa; }

/* Status Badges */
.dgptm-dash .dgptm-artikel-container .status-badge { padding: 2px 8px; border-radius: 10px; font-size: 0.75em; font-weight: 600; }

/* Cards */
.dgptm-dash .dgptm-artikel-container .article-card,
.dgptm-dash .dgptm-artikel-container .detail-card { box-shadow: none; border: 1px solid #eee; border-radius: 4px; padding: 12px; }

/* Modals bleiben eigenstaendig */

/* Progress Steps kompakter */
.dgptm-dash .progress-steps { gap: 8px; }
.dgptm-dash .progress-step .step-number { width: 24px; height: 24px; font-size: 12px; line-height: 24px; }
.dgptm-dash .progress-step .step-label { font-size: 12px; }

/* Tabs */
.dgptm-dash .dgptm-artikel-container .tab-nav a { padding: 6px 12px; font-size: 12px; }

/* File Upload */
.dgptm-dash .dgptm-artikel-container .file-upload-zone { padding: 20px; border-radius: 4px; }

/* Timeline */
.dgptm-dash .dgptm-artikel-container .timeline-item { padding: 10px 0; }

/* Loading */
.dgptm-dash .dgptm-artikel-container .loading { text-align: center; padding: 30px; color: #888; }

/* Responsive */
@media (max-width: 768px) {
    .dgptm-dash .dgptm-artikel-container button { width: auto !important; }
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/content/artikel-einreichung/assets/css/frontend.css
git commit -m "style(artikel-einreichung): Dashboard-CSS Forum-Overrides"
```

---

## Task 2: CRM-Reviewer Klasse erstellen

**Files:**
- Create: `modules/content/artikel-einreichung/includes/class-crm-reviewer.php`

- [ ] **Step 1: Klasse erstellen**

```php
<?php
/**
 * Zoho CRM Integration fuer Reviewer-Management
 * Sucht/erstellt Contacts im CRM, verknuepft mit WP-Usern
 */
if (!defined('ABSPATH')) exit;

class DGPTM_CRM_Reviewer {

    /**
     * Prueft ob CRM-Modul verfuegbar ist
     */
    public static function is_available() {
        return class_exists('DGPTM_Zoho_Plugin') && method_exists('DGPTM_Zoho_Plugin', 'get_instance');
    }

    /**
     * Holt OAuth-Token vom CRM-Modul
     */
    private static function get_token() {
        if (!self::is_available()) return null;
        $plugin = DGPTM_Zoho_Plugin::get_instance();
        $token = $plugin->get_oauth_token();
        if (is_wp_error($token) || empty($token)) return null;
        return $token;
    }

    /**
     * Sucht Contact per E-Mail im Zoho CRM
     * @return array|null Contact-Daten oder null
     */
    public static function search_by_email($email) {
        $token = self::get_token();
        if (!$token) return null;

        $url = 'https://www.zohoapis.eu/crm/v7/Contacts/search?email=' . urlencode($email);
        $response = dgptm_safe_remote('GET', $url, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Accept' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) return null;
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['data'][0])) {
            $c = $body['data'][0];
            return [
                'zoho_id' => $c['id'],
                'first_name' => $c['First_Name'] ?? '',
                'last_name' => $c['Last_Name'] ?? '',
                'email' => $c['Email'] ?? '',
                'institution' => $c['Department'] ?? '',
            ];
        }

        return null;
    }

    /**
     * Sucht Contact per Name im Zoho CRM
     * @return array Liste von Treffern
     */
    public static function search_by_name($name) {
        $token = self::get_token();
        if (!$token) return [];

        $url = 'https://www.zohoapis.eu/crm/v7/Contacts/search?word=' . urlencode($name);
        $response = dgptm_safe_remote('GET', $url, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Accept' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) return [];
        $body = json_decode(wp_remote_retrieve_body($response), true);

        $results = [];
        if (!empty($body['data']) && is_array($body['data'])) {
            foreach (array_slice($body['data'], 0, 10) as $c) {
                $results[] = [
                    'zoho_id' => $c['id'],
                    'first_name' => $c['First_Name'] ?? '',
                    'last_name' => $c['Last_Name'] ?? '',
                    'email' => $c['Email'] ?? '',
                    'institution' => $c['Department'] ?? '',
                ];
            }
        }

        return $results;
    }

    /**
     * Erstellt neuen Contact im Zoho CRM
     * @return string|null Zoho-ID oder null bei Fehler
     */
    public static function create_contact($first_name, $last_name, $email, $institution = '') {
        $token = self::get_token();
        if (!$token) return null;

        $data = [
            'data' => [[
                'First_Name' => $first_name,
                'Last_Name' => $last_name,
                'Email' => $email,
                'Department' => $institution,
                'Tag' => [['name' => 'Reviewer']],
            ]]
        ];

        $response = dgptm_safe_remote('POST', 'https://www.zohoapis.eu/crm/v7/Contacts', [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) return null;
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['data'][0]['details']['id'])) {
            return $body['data'][0]['details']['id'];
        }

        return null;
    }

    /**
     * Verknuepft WP-User mit Zoho Contact
     */
    public static function link_user($user_id, $zoho_id) {
        update_user_meta($user_id, 'zoho_id', $zoho_id);
    }
}
```

- [ ] **Step 2: In Hauptdatei einbinden**

In `artikel-einreichung.php`, in der `load_dependencies()` Methode (ca. Zeile 80-86, wo `class-db-installer.php` etc. geladen werden), hinzufuegen:

```php
require_once plugin_dir_path(__FILE__) . 'includes/class-crm-reviewer.php';
```

Falls keine `load_dependencies()` Methode existiert, im Konstruktor nach den anderen `require_once` Aufrufen einfuegen.

- [ ] **Step 3: Commit**

```bash
git add modules/content/artikel-einreichung/includes/class-crm-reviewer.php
git add modules/content/artikel-einreichung/artikel-einreichung.php
git commit -m "feat(artikel-einreichung): CRM-Reviewer Klasse fuer Zoho Contact-Integration"
```

---

## Task 3: Role-Manager Klasse erstellen

**Files:**
- Create: `modules/content/artikel-einreichung/includes/class-role-manager.php`

- [ ] **Step 1: Klasse erstellen**

```php
<?php
/**
 * Rollen-Manager fuer Reviewer und Autoren
 * Vergibt/entzieht WP-Rollen automatisch
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Artikel_Role_Manager {

    /**
     * Reviewer-Rolle vergeben
     */
    public static function grant_reviewer_role($user_id) {
        $user = get_userdata($user_id);
        if ($user && !in_array('reviewer', $user->roles)) {
            $user->add_role('reviewer');
        }
    }

    /**
     * Reviewer-Rolle entziehen
     */
    public static function revoke_reviewer_role($user_id) {
        $user = get_userdata($user_id);
        if ($user && in_array('reviewer', $user->roles)) {
            $user->remove_role('reviewer');
        }
    }

    /**
     * zeitschrift_autor Rolle vergeben
     */
    public static function grant_autor_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;

        // Rolle registrieren falls nicht vorhanden
        if (!wp_roles()->is_role('zeitschrift_autor')) {
            add_role('zeitschrift_autor', 'Zeitschrift-Autor', ['read' => true]);
        }

        if (!in_array('zeitschrift_autor', $user->roles)) {
            $user->add_role('zeitschrift_autor');
        }
    }

    /**
     * zeitschrift_autor Rolle entziehen (wenn keine aktiven Einreichungen)
     */
    public static function maybe_revoke_autor_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user || !in_array('zeitschrift_autor', $user->roles)) return;

        // Pruefe ob noch aktive Einreichungen vorhanden
        $terminal_statuses = [
            DGPTM_Artikel_Einreichung::STATUS_REJECTED,
            DGPTM_Artikel_Einreichung::STATUS_PUBLISHED,
        ];

        $active = get_posts([
            'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
            'author' => $user_id,
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'artikel_status',
                    'value' => $terminal_statuses,
                    'compare' => 'NOT IN'
                ]
            ]
        ]);

        if (empty($active)) {
            $user->remove_role('zeitschrift_autor');
        }
    }

    /**
     * WP-User anlegen fuer externen Reviewer/Autor
     * @return int|WP_Error User-ID oder Fehler
     */
    public static function create_user($email, $first_name, $last_name, $role = 'reviewer') {
        $username = sanitize_user(strstr($email, '@', true), true);

        // Username-Kollision vermeiden
        $base = $username;
        $i = 1;
        while (username_exists($username)) {
            $username = $base . $i;
            $i++;
        }

        $password = wp_generate_password(16, true);

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name),
            'role' => $role,
        ]);

        if (!is_wp_error($user_id)) {
            // Einladungs-E-Mail mit Passwort-Reset-Link senden
            wp_new_user_notification($user_id, null, 'user');
        }

        return $user_id;
    }

    /**
     * Reviewer-Pool synchronisieren — Rollen an aktiv/inaktiv anpassen
     */
    public static function sync_reviewer_roles($pool) {
        foreach ($pool as $reviewer) {
            $user_id = intval($reviewer['user_id'] ?? 0);
            if (!$user_id) continue;

            if (!empty($reviewer['active'])) {
                self::grant_reviewer_role($user_id);
            } else {
                self::revoke_reviewer_role($user_id);
            }
        }
    }
}
```

- [ ] **Step 2: In Hauptdatei einbinden und Login-Hook registrieren**

In `artikel-einreichung.php`:

1. Require nach class-crm-reviewer:
```php
require_once plugin_dir_path(__FILE__) . 'includes/class-role-manager.php';
```

2. Im Konstruktor (nach den bestehenden `add_action` Aufrufen) Login-Hook:
```php
add_action('wp_login', [$this, 'on_user_login'], 10, 2);
```

3. Neue Methode in der Klasse:
```php
public function on_user_login($user_login, $user) {
    DGPTM_Artikel_Role_Manager::maybe_revoke_autor_role($user->ID);
}
```

- [ ] **Step 3: Commit**

```bash
git add modules/content/artikel-einreichung/includes/class-role-manager.php
git add modules/content/artikel-einreichung/artikel-einreichung.php
git commit -m "feat(artikel-einreichung): Role-Manager fuer Reviewer und Autoren"
```

---

## Task 4: Reviewer-Pool Datenstruktur migrieren + AJAX-Handler erweitern

**Files:**
- Modify: `modules/content/artikel-einreichung/artikel-einreichung.php`

- [ ] **Step 1: Pool-Migrations-Methode hinzufuegen**

Neue Methode in `DGPTM_Artikel_Einreichung`:

```php
/**
 * Migriert alte Reviewer-Pool-Struktur [12, 45] -> [{user_id, active, zoho_id, added_at}]
 */
public function get_reviewer_pool() {
    $pool = get_option(self::OPT_REVIEWERS, []);

    // Migration: alte Struktur erkennen (flaches Array von IDs)
    if (!empty($pool) && isset($pool[0]) && is_int($pool[0])) {
        $migrated = [];
        foreach ($pool as $uid) {
            $zoho_id = get_user_meta($uid, 'zoho_id', true);
            $migrated[] = [
                'user_id' => $uid,
                'active' => true,
                'zoho_id' => $zoho_id ?: '',
                'added_at' => date('Y-m-d'),
            ];
        }
        update_option(self::OPT_REVIEWERS, $migrated);
        // Rollen synchronisieren
        DGPTM_Artikel_Role_Manager::sync_reviewer_roles($migrated);
        return $migrated;
    }

    return $pool;
}

/**
 * Speichert Reviewer-Pool und synchronisiert Rollen
 */
public function save_reviewer_pool($pool) {
    update_option(self::OPT_REVIEWERS, $pool);
    DGPTM_Artikel_Role_Manager::sync_reviewer_roles($pool);
}
```

- [ ] **Step 2: ajax_add_reviewer() erweitern**

Ersetze die bestehende `ajax_add_reviewer()` Methode (Zeile 1803-1823):

```php
public function ajax_add_reviewer() {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');

    if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Keine Berechtigung.']);
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    $email = sanitize_email($_POST['email'] ?? '');
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['last_name'] ?? '');
    $zoho_id = sanitize_text_field($_POST['zoho_id'] ?? '');

    // Fall 1: Bestehender WP-User
    if ($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(['message' => 'Benutzer nicht gefunden.']);
        }
    }
    // Fall 2: Neuen User anlegen (E-Mail angegeben, kein WP-User)
    elseif ($email) {
        // Pruefen ob E-Mail schon existiert
        $existing = get_user_by('email', $email);
        if ($existing) {
            $user_id = $existing->ID;
        } else {
            if (empty($last_name)) {
                wp_send_json_error(['message' => 'Nachname ist erforderlich.']);
            }
            $user_id = DGPTM_Artikel_Role_Manager::create_user($email, $first_name, $last_name, 'reviewer');
            if (is_wp_error($user_id)) {
                wp_send_json_error(['message' => 'Fehler beim Anlegen: ' . $user_id->get_error_message()]);
            }
        }
    } else {
        wp_send_json_error(['message' => 'User-ID oder E-Mail erforderlich.']);
    }

    // CRM-Verknuepfung
    if (empty($zoho_id) && $email && DGPTM_CRM_Reviewer::is_available()) {
        $crm_contact = DGPTM_CRM_Reviewer::search_by_email($email);
        if ($crm_contact) {
            $zoho_id = $crm_contact['zoho_id'];
        } else {
            $user = get_userdata($user_id);
            $zoho_id = DGPTM_CRM_Reviewer::create_contact(
                $user->first_name ?: $first_name,
                $user->last_name ?: $last_name,
                $user->user_email ?: $email
            ) ?: '';
        }
    }

    if ($zoho_id) {
        DGPTM_CRM_Reviewer::link_user($user_id, $zoho_id);
    }

    // Zum Pool hinzufuegen
    $pool = $this->get_reviewer_pool();
    $exists = false;
    foreach ($pool as &$r) {
        if (intval($r['user_id']) === $user_id) {
            $r['active'] = true;
            $r['zoho_id'] = $zoho_id ?: $r['zoho_id'];
            $exists = true;
            break;
        }
    }
    unset($r);

    if (!$exists) {
        $pool[] = [
            'user_id' => $user_id,
            'active' => true,
            'zoho_id' => $zoho_id,
            'added_at' => date('Y-m-d'),
        ];
    }

    $this->save_reviewer_pool($pool);

    $user = get_userdata($user_id);
    wp_send_json_success([
        'message' => 'Reviewer hinzugefuegt.',
        'reviewer' => [
            'user_id' => $user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'active' => true,
            'zoho_id' => $zoho_id,
        ]
    ]);
}
```

- [ ] **Step 3: ajax_remove_reviewer() erweitern — inaktiv setzen statt loeschen**

Ersetze die bestehende `ajax_remove_reviewer()` Methode (Zeile 1828-1847):

```php
public function ajax_remove_reviewer() {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');

    if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Keine Berechtigung.']);
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    $permanent = !empty($_POST['permanent']);

    if (!$user_id) {
        wp_send_json_error(['message' => 'Ungueltige Benutzer-ID.']);
    }

    $pool = $this->get_reviewer_pool();

    if ($permanent) {
        // Komplett entfernen
        $pool = array_values(array_filter($pool, function($r) use ($user_id) {
            return intval($r['user_id']) !== $user_id;
        }));
    } else {
        // Nur inaktiv setzen
        foreach ($pool as &$r) {
            if (intval($r['user_id']) === $user_id) {
                $r['active'] = false;
                break;
            }
        }
        unset($r);
    }

    $this->save_reviewer_pool($pool);

    wp_send_json_success(['message' => $permanent ? 'Reviewer entfernt.' : 'Reviewer deaktiviert.']);
}
```

- [ ] **Step 4: Neuen AJAX-Handler: ajax_toggle_reviewer_active()**

Neue Methode in der Klasse + Registrierung im Konstruktor:

Konstruktor (nach bestehenden `add_action('wp_ajax_...')` Aufrufen):
```php
add_action('wp_ajax_dgptm_toggle_reviewer_active', [$this, 'ajax_toggle_reviewer_active']);
```

Methode:
```php
public function ajax_toggle_reviewer_active() {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');

    if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Keine Berechtigung.']);
    }

    $user_id = intval($_POST['user_id'] ?? 0);

    if (!$user_id) {
        wp_send_json_error(['message' => 'Ungueltige Benutzer-ID.']);
    }

    $pool = $this->get_reviewer_pool();
    $new_state = false;

    foreach ($pool as &$r) {
        if (intval($r['user_id']) === $user_id) {
            $r['active'] = !$r['active'];
            $new_state = $r['active'];
            break;
        }
    }
    unset($r);

    $this->save_reviewer_pool($pool);

    wp_send_json_success([
        'message' => $new_state ? 'Reviewer aktiviert.' : 'Reviewer deaktiviert.',
        'active' => $new_state,
    ]);
}
```

- [ ] **Step 5: ajax_search_users() erweitern — CRM-Suche hinzufuegen**

Ersetze die bestehende `ajax_search_users()` Methode (Zeile 1768-1798):

```php
public function ajax_search_users() {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');

    if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Keine Berechtigung.']);
    }

    $search = sanitize_text_field($_POST['search'] ?? '');

    if (strlen($search) < 2) {
        wp_send_json_success(['users' => []]);
    }

    // 1. WP-User suchen
    $users = get_users([
        'search' => '*' . $search . '*',
        'search_columns' => ['display_name', 'user_email', 'user_login'],
        'number' => 10,
        'orderby' => 'display_name'
    ]);

    $results = [];
    $found_emails = [];

    foreach ($users as $user) {
        $zoho_id = get_user_meta($user->ID, 'zoho_id', true);
        $results[] = [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'source' => 'wp',
            'zoho_id' => $zoho_id ?: '',
        ];
        $found_emails[] = strtolower($user->user_email);
    }

    // 2. Zoho CRM durchsuchen (falls verfuegbar)
    if (DGPTM_CRM_Reviewer::is_available()) {
        $crm_results = [];

        // E-Mail-Suche
        if (filter_var($search, FILTER_VALIDATE_EMAIL)) {
            $contact = DGPTM_CRM_Reviewer::search_by_email($search);
            if ($contact) $crm_results[] = $contact;
        } else {
            $crm_results = DGPTM_CRM_Reviewer::search_by_name($search);
        }

        foreach ($crm_results as $c) {
            // Duplikate vermeiden (gleiche E-Mail)
            if (!empty($c['email']) && in_array(strtolower($c['email']), $found_emails)) continue;

            $results[] = [
                'id' => 0,
                'name' => trim($c['first_name'] . ' ' . $c['last_name']),
                'email' => $c['email'],
                'source' => 'crm',
                'zoho_id' => $c['zoho_id'],
                'first_name' => $c['first_name'],
                'last_name' => $c['last_name'],
            ];
            if (!empty($c['email'])) $found_emails[] = strtolower($c['email']);
        }
    }

    wp_send_json_success(['users' => $results]);
}
```

- [ ] **Step 6: Commit**

```bash
git add modules/content/artikel-einreichung/artikel-einreichung.php
git commit -m "feat(artikel-einreichung): Reviewer-Pool Migration + erweiterte AJAX-Handler"
```

---

## Task 5: Autoren-Rollen bei Einreichung

**Files:**
- Modify: `modules/content/artikel-einreichung/artikel-einreichung.php` (ajax_submit_artikel, ca. Zeile 1213)
- Modify: `modules/content/artikel-einreichung/templates/submission-form.php`

- [ ] **Step 1: Login-Hinweis im Template**

In `templates/submission-form.php`, nach Zeile 11 (`<div class="dgptm-artikel-container">`), vor dem `<form>`:

```php
<?php if (!is_user_logged_in()) : ?>
    <div class="dgptm-artikel-login-hint" style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
        <strong>Haben Sie bereits ein Konto?</strong>
        <a href="<?php echo wp_login_url(get_permalink()); ?>">Bitte melden Sie sich an</a>, um Ihre Einreichung im Mitgliederbereich verfolgen zu koennen.
        <br><small>Sie koennen auch ohne Anmeldung einreichen — Sie erhalten dann einen Zugangslink per E-Mail.</small>
    </div>
<?php endif; ?>
```

- [ ] **Step 2: ajax_submit_artikel() erweitern — User-Zuordnung + Rolle**

In `ajax_submit_artikel()`, nach Zeile 1217 (`$user_id = get_current_user_id();`), folgenden Block einfuegen:

```php
// Nicht eingeloggt: User ueber E-Mail zuordnen oder anlegen
if (!$user_id) {
    $author_email = sanitize_email($_POST['hauptautor_email'] ?? '');
    if (!empty($author_email)) {
        $existing_user = get_user_by('email', $author_email);
        if ($existing_user) {
            $user_id = $existing_user->ID;
        } else {
            $first_name = sanitize_text_field($_POST['hauptautor'] ?? '');
            // Name aufsplitten
            $name_parts = explode(' ', $first_name, 2);
            $fn = $name_parts[0] ?? '';
            $ln = $name_parts[1] ?? $name_parts[0];

            $user_id = DGPTM_Artikel_Role_Manager::create_user($author_email, $fn, $ln, 'zeitschrift_autor');
            if (is_wp_error($user_id)) {
                $user_id = 0; // Fallback: ohne User erstellen (Token-Zugriff)
            } else {
                // CRM-Verknuepfung
                if (DGPTM_CRM_Reviewer::is_available()) {
                    $crm = DGPTM_CRM_Reviewer::search_by_email($author_email);
                    if ($crm) {
                        DGPTM_CRM_Reviewer::link_user($user_id, $crm['zoho_id']);
                    } else {
                        $zoho_id = DGPTM_CRM_Reviewer::create_contact($fn, $ln, $author_email);
                        if ($zoho_id) DGPTM_CRM_Reviewer::link_user($user_id, $zoho_id);
                    }
                }
            }
        }
    }
}

// Autor-Rolle vergeben (fuer eingeloggte und neu angelegte User)
if ($user_id) {
    DGPTM_Artikel_Role_Manager::grant_autor_role($user_id);
}
```

- [ ] **Step 3: Commit**

```bash
git add modules/content/artikel-einreichung/artikel-einreichung.php
git add modules/content/artikel-einreichung/templates/submission-form.php
git commit -m "feat(artikel-einreichung): Autoren-Rolle + User-Anlage bei Einreichung"
```

---

## Task 6: Admin-JS Reviewer-Suche erweitern

**Files:**
- Modify: `modules/content/artikel-einreichung/assets/js/admin.js`

- [ ] **Step 1: Suchergebnis-Rendering erweitern**

Die bestehende User-Suche in `admin.js` (ca. Zeile 28-100) zeigt Suchergebnisse an. Diese muss um CRM-Quelle und aktiv/inaktiv-Toggle erweitert werden.

Finde den Block der Suchergebnis-Anzeige (wo `result.id`, `result.name`, `result.email` genutzt werden) und erweitere die Anzeige:

```javascript
// In der success-Callback der User-Suche, ersetze die Ergebnis-Rendering-Logik:
response.data.users.forEach(function(user) {
    var sourceLabel = user.source === 'crm' ? '<span class="ae-source-badge ae-source-crm">CRM</span>' : '<span class="ae-source-badge ae-source-wp">WP</span>';
    var html = '<div class="search-result-item" data-user-id="' + user.id + '" data-email="' + (user.email || '') + '" data-first-name="' + (user.first_name || '') + '" data-last-name="' + (user.last_name || '') + '" data-zoho-id="' + (user.zoho_id || '') + '">';
    html += '<div class="search-result-info">';
    html += '<strong>' + user.name + '</strong> ' + sourceLabel;
    html += '<br><small>' + (user.email || 'Keine E-Mail') + '</small>';
    html += '</div>';
    html += '<button type="button" class="btn-add-reviewer">Hinzufuegen</button>';
    html += '</div>';
    $results.append(html);
});
```

Erweitere den Click-Handler fuer "Hinzufuegen" um die neuen Felder:
```javascript
$(document).on('click', '.btn-add-reviewer', function() {
    var $item = $(this).closest('.search-result-item');
    var data = {
        action: 'dgptm_add_reviewer',
        nonce: artikelAdmin.nonce,
        user_id: $item.data('user-id'),
        email: $item.data('email'),
        first_name: $item.data('first-name'),
        last_name: $item.data('last-name'),
        zoho_id: $item.data('zoho-id')
    };
    // ... AJAX call
});
```

- [ ] **Step 2: Aktiv/Inaktiv Toggle hinzufuegen**

In der Reviewer-Liste (wo Reviewer angezeigt werden), Toggle-Button hinzufuegen:

```javascript
$(document).on('click', '.btn-toggle-reviewer', function() {
    var $btn = $(this);
    var userId = $btn.data('user-id');

    $.post(artikelAdmin.ajaxUrl, {
        action: 'dgptm_toggle_reviewer_active',
        nonce: artikelAdmin.nonce,
        user_id: userId
    }, function(res) {
        if (res.success) {
            var $row = $btn.closest('.reviewer-item');
            if (res.data.active) {
                $row.removeClass('reviewer-inactive');
                $btn.text('Deaktivieren');
            } else {
                $row.addClass('reviewer-inactive');
                $btn.text('Aktivieren');
            }
        }
    });
});
```

- [ ] **Step 3: CSS fuer Quell-Badges und Inaktiv-Status**

Am Ende von `admin.css` hinzufuegen:

```css
/* Source Badges */
.ae-source-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; margin-left: 4px; }
.ae-source-wp { background: #e8f0f8; color: #005792; }
.ae-source-crm { background: #fff3e0; color: #e65100; }

/* Inactive Reviewers */
.reviewer-item.reviewer-inactive { opacity: 0.5; }
.reviewer-item.reviewer-inactive .reviewer-name { text-decoration: line-through; }
```

- [ ] **Step 4: Commit**

```bash
git add modules/content/artikel-einreichung/assets/js/admin.js
git add modules/content/artikel-einreichung/assets/css/admin.css
git commit -m "feat(artikel-einreichung): Admin-JS Reviewer-Suche mit CRM + Toggle"
```

---

## Task 7: Dashboard-Tabs konfigurieren (manuell)

**Files:** Keine Code-Aenderungen — WP-Admin Konfiguration.

- [ ] **Step 1: Haupt-Tab "Zeitschrift/Perfusiologie" anlegen (falls nicht vorhanden)**

Oder die Shortcodes in bestehende Tabs integrieren. Empfohlene Konfiguration:

| ID | Label | Parent | Order | Permission | Content |
|----|-------|--------|-------|------------|---------|
| `artikel` | Artikel-Einreichung | _(leer)_ | 85 | `role:zeitschrift_autor,reviewer,administrator` | _(leer)_ |
| `artikel-einreichen` | Einreichen | `artikel` | 86 | `always` | `[artikel_einreichung]` |
| `artikel-status` | Meine Einreichungen | `artikel` | 87 | `always` | `[artikel_dashboard]` |
| `artikel-review` | Reviews | `artikel` | 88 | `role:reviewer,administrator` | `[artikel_review]` |
| `artikel-redaktion` | Redaktion | `artikel` | 89 | `acf:editor_in_chief` | `[artikel_redaktion]` |
| `artikel-editor` | Editor Dashboard | `artikel` | 90 | `acf:editor_in_chief` | `[artikel_editor_dashboard]` |

---

## Task 8: Integration testen

- [ ] **Step 1: Dashboard-Modus testen**

- Alle 5 Shortcodes im Dashboard sichtbar (je nach Rolle)
- Forum-Stil CSS angewendet (keine Shadows, kleine Buttons)
- Token-Zugriff funktioniert weiterhin (URL mit Token)

- [ ] **Step 2: Reviewer-Management testen**

- Bestehender Reviewer-Pool wird migriert (alte → neue Struktur)
- Reviewer hinzufuegen: WP-User + CRM-Suche
- Neuen externen Reviewer anlegen (nicht in WP): User wird erstellt, Einladungs-E-Mail
- Reviewer deaktivieren: `reviewer` Rolle entzogen
- Reviewer aktivieren: `reviewer` Rolle zurueck

- [ ] **Step 3: Autoren-Rolle testen**

- Eingeloggt einreichen: `zeitschrift_autor` Rolle vergeben
- Nicht eingeloggt einreichen (mit E-Mail): User angelegt, Rolle vergeben
- Nach Abschluss/Ablehnung aller Artikel: Rolle entzogen (beim naechsten Login)

- [ ] **Step 4: CRM-Integration testen**

- Reviewer mit bekannter CRM-E-Mail suchen: CRM-Treffer angezeigt
- Neuen Reviewer anlegen: Contact im CRM erstellt, `zoho_id` verknuepft
- Falls CRM nicht erreichbar: nur WP-Suche, keine Fehler
