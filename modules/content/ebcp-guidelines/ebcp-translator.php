<?php
/**
 * Plugin Name: EBCPDGPTM Guidelines – Translator Addon
 * Description: Übersetzungs-Editor + Auto-Translate (DeepL/LibreTranslate) für den EBCPDGPTM Guidelines Viewer. Nummern-basiertes Matching (Spalte „number“), kein Fallback auf row_key. Zugriffssteuerung: „Übersetzen“ für Editoren+ (nur Editor-Tab/Aktionen) und zusätzlich freigebbare Benutzerliste. Robust gegenüber JSON/Array-Speicherformat des Hauptplugins.
 * Version:     1.5.3
 * Author:      Sebastian Melzer + Logicc AI
 * License:     GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('EBCPDGPTM_Guidelines_Translator_Addon')) {

final class EBCPDGPTM_Guidelines_Translator_Addon {
    /** =====================
     *  Logging & Utilities
     *  ===================== */
    private function _mask_key($key) {
        $key = (string)$key;
        if ($key === '') return '';
        $len = strlen($key);
        if ($len <= 7) return str_repeat('*', $len);
        return substr($key, 0, 3) . '…' . substr($key, -3);
    }
    private function _short($str, $max = 400) {
        if (!is_string($str)) $str = function_exists('wp_json_encode') ? wp_json_encode($str) : json_encode($str);
        if (strlen($str) <= $max) return $str;
        return substr($str, 0, $max) . '… (trimmed, ' . strlen($str) . ' bytes)';
    }
    private function _log_enabled() {
        $s = $this->get_addon_settings();
        if (!empty($s['log_force'])) return true;
        return (defined('WP_DEBUG') && WP_DEBUG);
    }
    private function _log_file_enabled() {
        $s = $this->get_addon_settings();
        return !empty($s['log_plugin_file']);
    }
    private function _log_verbose() {
        $s = $this->get_addon_settings();
        return !empty($s['log_verbose']);
    }
    private function _write_log_line($label, $context = array(), $cid = '') {
        if (!$this->_log_enabled()) return;
        if (!is_array($context)) $context = array('data'=>(string)$context);
        // Secrets maskieren & Response kürzen
        if (isset($context['headers']['Authorization'])) {
            $auth = (string)$context['headers']['Authorization'];
            $auth = preg_replace('~^DeepL-Auth-Key\s+~i', '', $auth);
            $context['headers']['Authorization'] = 'DeepL-Auth-Key ' . $this->_mask_key($auth);
        }
        $verbose = $this->_log_verbose();
        if (isset($context['body'])) {
            if (isset($context['body']['text'])) {
                $t = $context['body']['text'];
                $preview = is_array($t) ? implode("\n", $t) : (string)$t;
                $context['body']['text_preview'] = $this->_short($preview);
                if (!$verbose) unset($context['body']['text']);
            } elseif (isset($context['body']['q'])) {
                $preview = (string)$context['body']['q'];
                $context['body']['q_preview'] = $this->_short($preview);
                if (!$verbose) unset($context['body']['q']);
            }
        }
        if (isset($context['response_body']) && !$verbose) {
            $context['response_body'] = $this->_short((string)$context['response_body']);
        }
        if ($cid) $context['cid'] = $cid;
        $msg = '[EBCP-Translator] ' . $label . ': ' . (function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context));
        @error_log($msg);
        if ($this->_log_file_enabled()) {
            $upload = wp_get_upload_dir();
            $dir = trailingslashit($upload['basedir']) . 'ebcp-logs';
            if (!is_dir($dir)) @wp_mkdir_p($dir);
            $file = $dir . '/translator.log';
            if (file_exists($file) && filesize($file) > 1024*1024) {
                @rename($file, $file . '.' . date('Ymd-His'));
            }
            @file_put_contents($file, $msg . PHP_EOL, FILE_APPEND);
        }
    }
    private function _cid() {
        if (function_exists('wp_generate_uuid4')) return wp_generate_uuid4();
        return uniqid('tx_', true);
    }
    private function http_request($method, $url, $args, $cid = '') {
        $method = strtoupper($method);
        if (!is_array($args)) $args = [];
        $args['method'] = $method;
        $args['timeout'] = isset($args['timeout']) ? (int)$args['timeout'] : 20;
        $headers = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : [];
        if (empty($headers['User-Agent'])) {
            $headers['User-Agent'] = 'EBCPDGPTM-Translator/1.5.3; WordPress/' . get_bloginfo('version');
        }
        $args['headers'] = $headers;

        $this->_write_log_line('http:request', ['url'=>$url, 'method'=>$method, 'headers'=>$headers, 'body'=>($args['body'] ?? [])], $cid);
        $t0 = microtime(true);
        $resp = wp_remote_request($url, $args);
        $code = is_wp_error($resp) ? 0 : (int)wp_remote_retrieve_response_code($resp);
        $body = is_wp_error($resp) ? '' : wp_remote_retrieve_body($resp);
        $hdrs = is_wp_error($resp) ? [] : (array)wp_remote_retrieve_headers($resp);
        $this->_write_log_line('http:response', ['status'=>$code, 'is_wp_error'=>is_wp_error($resp), 'wp_error'=>is_wp_error($resp)?$resp->get_error_message():'', 'headers'=>$hdrs, 'response_body'=>$body, 'duration_ms'=>round((microtime(true)-$t0)*1000)], $cid);

        if (!$resp instanceof \WP_Error && in_array($code, [408,429,500,502,503,504], true)) {
            usleep(200000);
            $this->_write_log_line('http:retry', ['status'=>$code], $cid);
            $t0 = microtime(true);
            $resp = wp_remote_request($url, $args);
            $code = is_wp_error($resp) ? 0 : (int)wp_remote_retrieve_response_code($resp);
            $body = is_wp_error($resp) ? '' : wp_remote_retrieve_body($resp);
            $hdrs = is_wp_error($resp) ? [] : (array)wp_remote_retrieve_headers($resp);
            $this->_write_log_line('http:response', ['status'=>$code, 'is_wp_error'=>is_wp_error($resp), 'wp_error'=>is_wp_error($resp)?$resp->get_error_message():'', 'headers'=>$hdrs, 'response_body'=>$body, 'duration_ms'=>round((microtime(true)-$t0)*1000)], $cid);
        }
        return $resp;
    }

    const PARENT_MENU_SLUG   = 'ebcpdgptm-guidelines';
    const OPT_KEY_DATA       = 'ebcp_guidelines_json';
    const OPT_KEY_META       = 'ebcp_guidelines_meta';
    const OPT_KEY_ADDON      = 'ebcpdgptm_translator_settings';

    // Zugriffsrechte
    const CAP_SETTINGS       = 'manage_options'; // Einstellungen/API-Keys nur Admin
    const CAP_TRANSLATE      = 'edit_pages';     // Ab Editor darf übersetzen

    const REST_NS            = 'ebcpdgptm/v1';
    const REST_ROUTE_TX      = '/translate';
    const REST_ROUTE_INFO    = '/translate/info';

    const NONCE_ACTION       = 'ebcpdgptm_tx_nonce';
    const NONCE_FIELD        = 'ebcpdgptm_tx_field';

    public static function init() {
        $self = new self();
        add_action('admin_menu', [$self, 'add_subtab'], 20);
        add_action('admin_menu', [$self, 'maybe_hide_core_translator'], 100);
        add_action('admin_enqueue_scripts', [$self, 'admin_assets']);

        add_action('rest_api_init', [$self, 'register_rest']);

        add_action('admin_post_ebcpdgptm_generate_missing_rows', [$self, 'handle_generate_missing_rows']);
        add_action('admin_post_ebcpdgptm_save_translator_settings', [$self, 'handle_save_settings']);
        add_action('admin_post_ebcpdgptm_bulk_save_rows', [$self, 'handle_bulk_save_rows']);

        // Integration in Hauptplugin-Router
        add_action('ebcpdgptm_render_translator', [$self, 'render_router_page']);
    }

    /* ===================== Zugriffslogik ===================== */

    private function get_addon_settings() {
        $defaults = [
            'provider'          => 'none',   // none|deepl|libre
            'deepl_api_key'     => '',
            'deepl_endpoint'    => 'https://api.deepl.com/v2/translate',
            'deepl_free'        => 0,        // NEU: bei kostenloser Lizenz erzwinge api-free
            'libre_endpoint'    => 'https://libretranslate.com/translate',
            'timeout'           => 20,
            // Freigaben
            'allowed_users'     => '',       // CSV/Liste: ID, user_login oder E-Mail
            // Logging/Fallback
            'fallback_to_libre' => 0,
            'log_plugin_file'   => 0,
            'log_verbose'       => 0,
            'log_force'         => 0,
        ];
        $opt = get_option(self::OPT_KEY_ADDON, []);
        return wp_parse_args(is_array($opt)?$opt:[], $defaults);
    }

    private function user_is_allowed_translator($user = null) : bool {
        if (!$user) $user = wp_get_current_user();
        if (!$user || 0 === $user->ID) return false;

        if (current_user_can(self::CAP_TRANSLATE) || current_user_can(self::CAP_SETTINGS)) return true;

        $s = $this->get_addon_settings();
        $list = trim((string)($s['allowed_users'] ?? ''));
        if ($list === '') return false;

        $tokens = preg_split('~[\s,;]+~', $list, -1, PREG_SPLIT_NO_EMPTY);
        $uid = (string)$user->ID;
        $ulog = strtolower((string)$user->user_login);
        $uemail = strtolower((string)$user->user_email);

        foreach ($tokens as $t) {
            $t = strtolower(trim($t));
            if ($t === '') continue;
            if ($t === $uid || $t === $ulog || $t === $uemail) return true;
        }
        return false;
    }

    private function ensure_can_translate() {
        if (!$this->user_is_allowed_translator()) wp_die('Keine Berechtigung (Übersetzung).');
    }

    private function ensure_can_settings() {
        if (!current_user_can(self::CAP_SETTINGS)) wp_die('Keine Berechtigung (Einstellungen).');
    }

    /* ===================== Admin UI ===================== */

    public function add_subtab() {
        if ($this->user_is_allowed_translator()) {
            add_submenu_page(
                self::PARENT_MENU_SLUG,
                'Übersetzen',
                'Übersetzen',
                'read',
                self::PARENT_MENU_SLUG.'&tab=translator',
                [$this, 'render_router_page'],
                30
            );
        }
    }

    public function maybe_hide_core_translator() {
        global $submenu;
        if (empty($submenu[self::PARENT_MENU_SLUG])) return;
        foreach ($submenu[self::PARENT_MENU_SLUG] as $i => $item) {
            $slug  = isset($item[2]) ? (string)$item[2] : '';
            $title = isset($item[0]) ? (string)$item[0] : '';
            $menu  = isset($item[3]) ? (string)$item[3] : '';
            if ($slug && $slug !== self::PARENT_MENU_SLUG.'&tab=translator') {
                if (stripos($slug, 'translate') !== false || stripos($slug, 'translator') !== false || stripos($slug, 'uebersetz') !== false || stripos($slug, 'übersetz') !== false) {
                    unset($submenu[self::PARENT_MENU_SLUG][$i]);
                }
                if (stripos($title, 'translate') !== false || stripos($title, 'Übersetz') !== false || stripos($menu, 'translate') !== false || stripos($menu, 'Übersetz') !== false) {
                    unset($submenu[self::PARENT_MENU_SLUG][$i]);
                }
            }
        }
    }

    public function render_router_page() {
        $this->ensure_can_translate();

        $active_tab = isset($_GET['tab2']) ? sanitize_key($_GET['tab2']) : 'editor';
        echo '<div class="wrap"><h1>EBCP Guidelines – Übersetzung</h1>';

        echo '<nav class="nav-tab-wrapper" style="margin-top:1rem;">';
        $url_editor   = add_query_arg(['page'=>self::PARENT_MENU_SLUG,'tab'=>'translator','tab2'=>'editor'], admin_url('admin.php'));
        echo '<a class="nav-tab '.($active_tab==='editor'?'nav-tab-active':'').'" href="'.esc_url($url_editor).'">Übersetzungs-Editor</a>';

        if (current_user_can(self::CAP_SETTINGS)) {
            $url_settings = add_query_arg(['page'=>self::PARENT_MENU_SLUG,'tab'=>'translator','tab2'=>'settings'], admin_url('admin.php'));
            echo '<a class="nav-tab '.($active_tab==='settings'?'nav-tab-active':'').'" href="'.esc_url($url_settings).'">Übersetzungs-Einstellungen</a>';
        }
        echo '</nav>';

        echo '<div style="margin-top:1rem">';
        if ($active_tab === 'settings') {
            $this->ensure_can_settings();
            $this->render_settings_tab();
        } else {
            $this->render_editor_tab();
        }
        echo '</div></div>';
    }

    private function get_dataset() {
        $raw = get_option(self::OPT_KEY_DATA);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            } else {
                $maybe = @unserialize($raw);
                if ($maybe !== false && is_array($maybe)) $raw = $maybe;
            }
        }
        if (!is_array($raw)) return ['data'=>[],'meta'=>[]];
        if (!isset($raw['data']) || !is_array($raw['data'])) $raw['data'] = [];
        if (!isset($raw['meta']) || !is_array($raw['meta'])) $raw['meta'] = [];
        return $raw;
    }

    private function set_dataset($arr) {
        update_option(self::OPT_KEY_DATA, $arr);
    }

    private function norm_lang($l) { return strtolower(trim((string)$l)); }
    private function norm_number($n) {
        $n = trim((string)$n);
        if ($n === '' || strtolower($n) === 'nan' || strtolower($n) === 'null') return '';
        return $n;
    }
    private function compute_row_key(array $row, $idx) {
        if (!empty($row['row_key'])) return (string)$row['row_key'];
        $num = $this->norm_number($row['number'] ?? '');
        if ($num !== '') return 'N:'.$num;
        if (!empty($row['uuid'])) return 'U:'.trim((string)$row['uuid']);
        return 'I:'.(string)$idx;
    }
    private function get_field($row, $wanted) {
        $aliases = [
            'class_2024' => ['class_2024','class24'],
            'loe_2024'   => ['loe_2024','loe24'],
            'class_2019' => ['class_2019','class19'],
            'loe_2019'   => ['loe_2019','loe19'],
            'url'        => ['url'],
            'is_section' => ['is_section','section','isSection','row_type'],
        ];
        if ($wanted === 'is_section') {
            if (isset($row['is_section'])) return (bool)$row['is_section'];
            if (isset($row['row_type'])) return strtolower((string)$row['row_type']) === 'section';
        }
        if (!isset($aliases[$wanted])) return $row[$wanted] ?? '';
        foreach ($aliases[$wanted] as $k) if (array_key_exists($k, $row)) return $row[$k];
        return '';
    }
    private function copy_meta_fields(array $row) {
        $keys = ['url','class_2024','loe_2024','class_2019','loe_2019','is_section','class24','loe24','class19','loe19','row_type'];
        $out = [];
        foreach ($keys as $k) if (array_key_exists($k, $row)) $out[$k] = $row[$k];
        if (array_key_exists('is_section', $out)) $out['is_section'] = (bool)$out['is_section'];
        return $out;
    }
    private function collect_langs(array $rows) {
        $set = [];
        foreach ($rows as $r) {
            $l = $this->norm_lang($r['lang'] ?? '');
            if ($l!=='') $set[$l]=true;
        }
        return array_keys($set);
    }

    /* ===================== Settings Tab ===================== */

    private function render_settings_tab() {
        $s = $this->get_addon_settings();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:900px;">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
            <input type="hidden" name="action" value="ebcpdgptm_save_translator_settings">
            <table class="form-table" role="presentation">
                <tr><th scope="row">Provider</th><td>
                    <select name="provider">
                        <option value="none"  <?php selected($s['provider'],'none');  ?>>Kein Auto-Translate (nur UI)</option>
                        <option value="deepl" <?php selected($s['provider'],'deepl'); ?>>DeepL</option>
                        <option value="libre" <?php selected($s['provider'],'libre'); ?>>LibreTranslate</option>
                    </select>
                </td></tr>
                <tr><th scope="row">DeepL API Key</th><td>
                    <input type="password" name="deepl_api_key" value="<?php echo esc_attr($s['deepl_api_key']); ?>" class="regular-text" autocomplete="off">
                    <p class="description">Bei DeepL Free bitte die Option unten aktivieren – dann wird automatisch <code>https://api-free.deepl.com/v2/translate</code> genutzt.</p>
                </td></tr>
                <tr><th scope="row">DeepL Endpoint</th><td>
                    <input type="text" name="deepl_endpoint" value="<?php echo esc_attr($s['deepl_endpoint']); ?>" class="regular-text">
                    <p class="description">Für Pro-Keys: i. d. R. <code>https://api.deepl.com/v2/translate</code>. Wird bei aktivem „DeepL Free“ ignoriert.</p>
                </td></tr>
                <tr><th scope="row">DeepL Free (kostenlose Lizenz)</th><td>
                    <label><input type="checkbox" name="deepl_free" value="1" <?php checked(!empty($s['deepl_free'])); ?>> Ich nutze die kostenlose DeepL-Variante</label>
                    <p class="description">Erzwingt <code>api-free.deepl.com</code> unabhängig vom eingetragenen Endpoint oder Key-Suffix.</p>
                </td></tr>
                <tr><th scope="row">LibreTranslate Endpoint</th><td>
                    <input type="text" name="libre_endpoint" value="<?php echo esc_attr($s['libre_endpoint']); ?>" class="regular-text">
                </td></tr>
                <tr><th scope="row">HTTP Timeout (s)</th><td>
                    <input type="number" min="5" max="60" name="timeout" value="<?php echo (int)$s['timeout']; ?>">
                </td></tr>
                <tr><th scope="row">Zusätzliche Übersetzer</th><td>
                    <textarea name="allowed_users" class="large-text" rows="4" placeholder="ID, user_login oder E-Mail – getrennt per Komma/Zeile"><?php echo esc_textarea($s['allowed_users']); ?></textarea>
                </td></tr>
                <tr><th scope="row">Fallback & Logging</th><td>
                    <label><input type="checkbox" name="fallback_to_libre" value="1" <?php checked(!empty($s['fallback_to_libre'])); ?>> Fallback zu LibreTranslate, wenn DeepL fehlschlägt</label><br>
                    <label><input type="checkbox" name="log_plugin_file" value="1" <?php checked(!empty($s['log_plugin_file'])); ?>> Zusätzlich in <code>wp-uploads/ebcp-logs/translator.log</code> schreiben</label><br>
                    <label><input type="checkbox" name="log_verbose" value="1" <?php checked(!empty($s['log_verbose'])); ?>> Verbose (voller Text/Response)</label><br>
                    <label><input type="checkbox" name="log_force" value="1" <?php checked(!empty($s['log_force'])); ?>> Logging erzwingen (auch wenn <code>WP_DEBUG</code> aus ist)</label>
                </td></tr>
            </table>
            <?php submit_button('Einstellungen speichern'); ?>
        </form>
        <?php
    }

    public function handle_save_settings() {
        $this->ensure_can_settings();
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $provider   = sanitize_key($_POST['provider'] ?? 'none');
        $timeout    = max(5, min(60, (int)($_POST['timeout'] ?? 20)));
        $deepl_key  = isset($_POST['deepl_api_key']) ? trim(wp_unslash($_POST['deepl_api_key'])) : '';
        $deepl_ep   = isset($_POST['deepl_endpoint']) ? esc_url_raw($_POST['deepl_endpoint']) : '';
        $deepl_free = !empty($_POST['deepl_free']) ? 1 : 0;
        $libre_ep   = isset($_POST['libre_endpoint']) ? esc_url_raw($_POST['libre_endpoint']) : '';
        $allowed_users = isset($_POST['allowed_users']) ? trim(wp_unslash($_POST['allowed_users'])) : '';

        update_option(self::OPT_KEY_ADDON, [
            'provider'          => in_array($provider, ['none','deepl','libre'], true) ? $provider : 'none',
            'deepl_api_key'     => $deepl_key,
            'deepl_endpoint'    => $deepl_ep ?: 'https://api.deepl.com/v2/translate',
            'deepl_free'        => $deepl_free,
            'libre_endpoint'    => $libre_ep ?: 'https://libretranslate.com/translate',
            'timeout'           => $timeout,
            'allowed_users'     => $allowed_users,
            'fallback_to_libre' => !empty($_POST['fallback_to_libre']) ? 1 : 0,
            'log_plugin_file'   => !empty($_POST['log_plugin_file']) ? 1 : 0,
            'log_verbose'       => !empty($_POST['log_verbose']) ? 1 : 0,
            'log_force'         => !empty($_POST['log_force']) ? 1 : 0,
        ]);
        wp_redirect(add_query_arg(['page'=>self::PARENT_MENU_SLUG,'tab'=>'translator','tab2'=>'settings','success'=>'1'], admin_url('admin.php')));
        exit;
    }

    /* ===================== Editor Tab ===================== */

    private function render_editor_tab() {
        $dataset = $this->get_dataset();
        $data    = $dataset['data'] ?? [];

        $langs   = $this->collect_langs($data);
        $src     = isset($_GET['src']) ? sanitize_text_field($_GET['src']) : (reset($langs) ?: 'de');
        $tgt     = isset($_GET['tgt']) ? sanitize_text_field($_GET['tgt']) : 'en';
        $srcL    = $this->norm_lang($src);
        $tgtL    = $this->norm_lang($tgt);

        $has_src = in_array($srcL, array_map([$this,'norm_lang'],$langs), true);
        $cnt_by_lang = [];
        foreach(($dataset['data'] ?? []) as $_r){ $_l = $this->norm_lang($_r['lang'] ?? ''); if($_l!=='') $cnt_by_lang[$_l] = ($cnt_by_lang[$_l] ?? 0) + 1; }

        $s = $this->get_addon_settings();
        $auto_enabled = ($s['provider'] !== 'none');

        // Nummer-basiertes Matching (exklusiv)
        $order_src_numbers = [];
        $srcByNum = []; $tgtByNum = [];

        foreach ($data as $idx=>$row) {
            $lang = $this->norm_lang($row['lang'] ?? '');
            $num  = $this->norm_number($row['number'] ?? '');
            if ($num === '') continue;
            if ($lang === $srcL) {
                $srcByNum[$num] = ['idx'=>$idx,'row'=>$row];
                $order_src_numbers[] = $num;
            } elseif ($lang === $tgtL) {
                $tgtByNum[$num] = ['idx'=>$idx,'row'=>$row];
            }
        }

        natcasesort($order_src_numbers);
        $sequence = array_values(array_unique($order_src_numbers));

        ?>
        <?php if (!empty($cnt_by_lang)) { echo '<div class="notice notice-info"><p><strong>Datensatz geladen:</strong> '; foreach ($cnt_by_lang as $l=>$c) { echo strtoupper(esc_html($l)).': '.intval($c).'&nbsp;&nbsp;'; } echo '</p></div>'; } ?>
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::PARENT_MENU_SLUG); ?>">
            <input type="hidden" name="tab" value="translator">
            <input type="hidden" name="tab2" value="editor">
            <p>
                <label>Quelle (lang):<br>
                    <select name="src">
                        <?php foreach ($langs as $l): ?>
                            <option value="<?php echo esc_attr($l); ?>" <?php selected($src,$l); ?>><?php echo esc_html(strtoupper($l)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </p>
            <p>
                <label>Ziel (lang):<br>
                    <input type="text" name="tgt" value="<?php echo esc_attr($tgt); ?>" class="small-text" maxlength="5">
                    <span class="description">z. B. en, fr, pl, es …</span>
                </label>
            </p>
            <p><?php submit_button('Ansicht aktualisieren', 'secondary', '', false); ?></p>
            <p>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=ebcpdgptm_generate_missing_rows&src='.$src.'&tgt='.$tgt), self::NONCE_ACTION, self::NONCE_FIELD ) ); ?>">
                    Fehlende Ziel-Zeilen anlegen (Nummer-Match)
                </a>
            </p>
        </form>
<?php if ($auto_enabled): ?>
    <p style="margin: 10px 0;">
        <button type="button" class="button button-primary" id="ebcpdgptm-bulk-translate">Alle Zeilen übersetzen</button>
        <span class="description">Übersetzt alle <em>leeren</em> Zieltexte von <?php echo esc_html(strtoupper($srcL)); ?> nach <?php echo esc_html(strtoupper($tgtL)); ?>.</span>
    </p>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const bulkBtn = document.getElementById('ebcpdgptm-bulk-translate');
        if (!bulkBtn) return;
        bulkBtn.addEventListener('click', async function(){
            if (!confirm('Alle leeren Zieltexte automatisch übersetzen?')) return;
            bulkBtn.disabled = true;
            const cards = Array.from(document.querySelectorAll('.ebcpdgptm-card'));
            let done = 0;
            for (const card of cards) {
                const tgtTa = card.querySelector('textarea[name$="[text]"]');
                if (tgtTa && tgtTa.value.trim() === '') {
                    const rowBtn = card.querySelector('.tx-auto');
                    if (rowBtn) {
                        rowBtn.click();
                        await new Promise(r => setTimeout(r, 600)); // throttling
                    }
                }
                done++;
                bulkBtn.textContent = 'Übersetze… ' + done + '/' + cards.length;
            }
            bulkBtn.textContent = 'Fertig ✔';
            setTimeout(function(){ bulkBtn.textContent = 'Alle Zeilen übersetzen'; bulkBtn.disabled = false; }, 1200);
        });
    });
    </script>
<?php endif; ?>


        <?php if (!$has_src): ?>
            <div class="notice notice-warning"><p>Keine Quelldaten für Sprache <strong><?php echo esc_html($src); ?></strong> gefunden.</p></div>
            <?php return; endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="ebcpdgptm-bulk-save-form">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
            <input type="hidden" name="action" value="ebcpdgptm_bulk_save_rows">
            <input type="hidden" name="src" value="<?php echo esc_attr($src); ?>">
            <input type="hidden" name="tgt" value="<?php echo esc_attr($tgt); ?>">

            <div class="ebcpdgptm-tx-toolbar" style="margin: 0.5rem 0 1rem; display:flex; gap:0.5rem; align-items:center;">
                <span class="dashicons dashicons-translation"></span>
                <strong>Übersetzungs-Modus</strong>
                <?php if ($auto_enabled): ?>
                    <span class="tag">Auto: <?php echo esc_html(strtoupper($s['provider'])); ?></span>
                <?php else: ?>
                    <span class="tag">Auto aus</span>
                <?php endif; ?>
                <?php submit_button('Alle sichtbaren Zieltexte speichern', 'primary', '', false); ?>
            </div>

            <style>
                .ebcpdgptm-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
                .ebcpdgptm-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px;}
                .ebcpdgptm-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
                .ebcpdgptm-head .meta{opacity:.85;font-size:12px}
                .ebcpdgptm-col{display:flex;gap:8px;flex-direction:column}
                .ebcpdgptm-rows{display:flex;flex-direction:column;gap:16px}
                .ebcpdgptm-btns{display:flex;gap:8px;flex-wrap:wrap}
                .tag{padding:2px 6px;border:1px solid #ccd0d4;border-radius:999px;background:#f6f7f7;font-size:11px}
                .tag-sec{background:#eef6ff;border-color:#cce1ff;}
                .note-muted{opacity:.75}
                textarea.ebcpdgptm-text{width:100%;min-height:140px;white-space:pre-wrap;}
                .copy-ok{color:#198754;font-weight:600}
            </style>

            <div class="ebcpdgptm-rows">
                <?php
                foreach ($sequence as $num):
                    $srcRowPack = $srcByNum[$num] ?? null;
                    if (!$srcRowPack) continue;
                    $srcRow  = $srcRowPack['row'];
                    $srcText = (string)($srcRow['text'] ?? '');

                    $tgtRowPack = $tgtByNum[$num] ?? null;
                    $tgtIdx  = $tgtRowPack ? $tgtRowPack['idx'] : null;
                    $tgtRow  = $tgtRowPack ? $tgtRowPack['row'] : null;
                    $tgtText = (string)($tgtRow['text'] ?? '');

                    $isSec   = (bool)$this->get_field($srcRow, 'is_section');
                    $key     = 'N:'.$num;
                    $row_id  = 'r_'.md5($src.$tgt.$key);
                ?>
                <div class="ebcpdgptm-card" data-num="<?php echo esc_attr($num); ?>">
                    <div class="ebcpdgptm-head">
                        <div class="meta">
                            <strong>Nr. <?php echo esc_html($num); ?></strong>
                            <span class="tag"><?php echo esc_html(strtoupper($src)); ?> → <?php echo esc_html(strtoupper($tgt)); ?></span>
                            <?php if ($isSec): ?><span class="tag tag-sec">SECTION</span><?php endif; ?>
                            <?php if ($tgtIdx===null): ?><span class="tag">neu</span><?php endif; ?>
                            <span class="tag">Key: <?php echo esc_html($key); ?></span>
                        </div>
                        <div class="ebcpdgptm-btns">
                            <button type="button" class="button copy-src" data-target="<?php echo esc_attr($row_id); ?>">Aus Quelle kopieren</button>
                            <button type="button" class="button tx-auto" data-row="<?php echo esc_attr($row_id); ?>">Auto-Übersetzen</button>
                        </div>
                    </div>
                    <div class="ebcpdgptm-grid">
                        <div class="ebcpdgptm-col">
                            <label><span class="note-muted">Quelle (<?php echo esc_html($src); ?>)</span></label>
                            <textarea class="ebcpdgptm-text" readonly><?php echo esc_textarea($srcText); ?></textarea>
                        </div>
                        <div class="ebcpdgptm-col">
                            <label>Zieltext (<?php echo esc_html($tgt); ?>)</label>
                            <textarea class="ebcpdgptm-text" name="items[<?php echo esc_attr('N:'.$num); ?>][text]" id="<?php echo esc_attr($row_id); ?>"><?php echo esc_textarea($tgtText); ?></textarea>
                            <input type="hidden" name="items[<?php echo esc_attr('N:'.$num); ?>][number]" value="<?php echo esc_attr($num); ?>">
                            <input type="hidden" name="items[<?php echo esc_attr('N:'.$num); ?>][is_section]" value="<?php echo $isSec ? '1':'0'; ?>">
                            <input type="hidden" name="items[<?php echo esc_attr('N:'.$num); ?>][row_key]" value="<?php echo esc_attr($key); ?>">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:16px;"><?php submit_button('Alle sichtbaren Zieltexte speichern', 'primary'); ?></div>
        </form>

        <script>
        (function(){
            const nonce     = '<?php echo esc_js( wp_create_nonce(self::NONCE_ACTION) ); ?>';
            const restNonce = '<?php echo esc_js( wp_create_nonce('wp_rest') ); ?>';
            const rest      = '<?php echo esc_js( rest_url( self::REST_NS . self::REST_ROUTE_TX ) ); ?>';
            const src       = '<?php echo esc_js($srcL); ?>';
            const tgt       = '<?php echo esc_js($tgtL); ?>';

            document.querySelectorAll('.copy-src').forEach(btn=>{
                btn.addEventListener('click', e=>{
                    const target = document.getElementById(btn.dataset.target);
                    const card   = btn.closest('.ebcpdgptm-card');
                    const srcTa  = card.querySelector('.ebcpdgptm-col textarea[readonly]');
                    if (target && srcTa) {
                        target.value = srcTa.value;
                        btn.classList.add('copy-ok');
                        btn.textContent = 'Kopiert ✔';
                        setTimeout(()=>{btn.classList.remove('copy-ok'); btn.textContent='Aus Quelle kopieren';}, 1200);
                    }
                });
            });

            document.querySelectorAll('.tx-auto').forEach(btn=>{
                btn.addEventListener('click', async e=>{
                    const rowId = btn.dataset.row;
                    const card  = btn.closest('.ebcpdgptm-card');
                    const srcTa = card.querySelector('.ebcpdgptm-col textarea[readonly]');
                    const tgtTa = document.getElementById(rowId);
                    if (!srcTa || !tgtTa) return;
                    btn.disabled = true; const old = btn.textContent; btn.textContent = 'Übersetze…';
                    let j = null;
                    try {
                        const res = await fetch(rest, {
                            method: 'POST',
                            headers: {
                                'Content-Type':'application/json',
                                'X-WP-Nonce': restNonce
                            },
                            body: JSON.stringify({ _ajax_nonce:nonce, text:srcTa.value, source:src, target:tgt }),
                            credentials: 'same-origin'
                        });
                        j = await res.json();
                        if (j && j.ok && typeof j.text === 'string') {
                            tgtTa.value = j.text;
                            btn.textContent = 'Fertig ✔';
                        } else {
                            btn.textContent = 'Fehler'; try{ if(j && j.cid){ btn.title = 'CID: '+j.cid + (j.error ? ' – '+j.error : ''); } }catch(e){}
                        }
                    } catch(err){
                        btn.textContent = 'Fehler'; try{ if(j && j.cid){ btn.title = 'CID: '+j.cid + (j.error ? ' – '+j.error : ''); } }catch(e){}
                    } finally {
                        setTimeout(()=>{btn.textContent = old; btn.disabled = false;}, 1200);
                    }
                });
            });
        })();
        </script>
        <?php
    }

    /* ===================== Actions ===================== */

    public function handle_generate_missing_rows() {
        $this->ensure_can_translate();
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $src = $this->norm_lang(sanitize_text_field($_GET['src'] ?? ''));
        $tgt = $this->norm_lang(sanitize_text_field($_GET['tgt'] ?? ''));
        if ($src==='' || $tgt==='') wp_die('Fehlende Parameter.');

        $dataset = $this->get_dataset();
        $data    = $dataset['data'] ?? [];

        $srcByNum = []; $tgtByNum = [];

        foreach ($data as $idx=>$row) {
            $lang = $this->norm_lang($row['lang'] ?? '');
            $num  = $this->norm_number($row['number'] ?? '');
            if ($num === '') continue;
            if ($lang === $src) {
                $srcByNum[$num] = $row;
            } elseif ($lang === $tgt) {
                $tgtByNum[$num] = $row;
            }
        }

        foreach ($srcByNum as $num=>$row) {
            if (!isset($tgtByNum[$num])) {
                $meta = $this->copy_meta_fields($row);
                $data[] = array_merge($meta, [
                    'lang'    => $tgt,
                    'number'  => $num,
                    'row_key' => 'N:'.$num,
                    'text'    => '',
                ]);
            }
        }

        $dataset['data'] = array_values($data);
        $this->set_dataset($dataset);

        wp_redirect(add_query_arg([
            'page'=>self::PARENT_MENU_SLUG,
            'tab'=>'translator',
            'tab2'=>'editor',
            'src'=>$src,
            'tgt'=>$tgt,
            'success'=>'1'
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_bulk_save_rows() {
        $this->ensure_can_translate();
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $src   = $this->norm_lang(sanitize_text_field($_POST['src'] ?? ''));
        $tgt   = $this->norm_lang(sanitize_text_field($_POST['tgt'] ?? ''));
        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

        $dataset = $this->get_dataset();
        $data    = $dataset['data'] ?? [];

        $indexByNum = []; $srcMetaByNum = [];

        foreach ($data as $i=>$row) {
            $lang = $this->norm_lang($row['lang'] ?? '');
            $num  = $this->norm_number($row['number'] ?? '');

            if ($num !== '' && $lang === $tgt) {
                $indexByNum[$num] = $i;
            }
            if ($lang === $src && $num !== '') {
                $srcMetaByNum[$num] = $this->copy_meta_fields($row);
            }
        }

        foreach ($items as $k=>$payload) {
            $text   = isset($payload['text']) ? wp_kses_post(wp_unslash($payload['text'])) : '';
            $num    = $this->norm_number($payload['number'] ?? '');
            $is_sec = !empty($payload['is_section']);
            if ($num === '') continue;

            if (isset($indexByNum[$num])) {
                $i = $indexByNum[$num];
                $data[$i]['text'] = $text;
                $data[$i]['lang'] = $tgt;
                $data[$i]['number'] = $num;
                $data[$i]['row_key'] = 'N:'.$num;
                if (array_key_exists('is_section', $data[$i])) $data[$i]['is_section'] = (bool)$is_sec;
            } else {
                $meta = $srcMetaByNum[$num] ?? ['url'=>'','class_2024'=>'','loe_2024'=>'','class_2019'=>'','loe_2019'=>'','is_section'=>$is_sec];
                $data[] = array_merge($meta, [
                    'lang'    => $tgt,
                    'number'  => $num,
                    'row_key' => 'N:'.$num,
                    'text'    => $text,
                ]);
            }
        }

        $dataset['data'] = array_values($data);
        $this->set_dataset($dataset);

        wp_redirect(add_query_arg([
            'page'=>self::PARENT_MENU_SLUG,
            'tab'=>'translator',
            'tab2'=>'editor',
            'src'=>$src,
            'tgt'=>$tgt,
            'success'=>'1'
        ], admin_url('admin.php')));
        exit;
    }

    /* ===================== REST: /translate (+ /translate/info) ===================== */

    public function register_rest() {
        $this->_write_log_line('rest:register', ['ns'=>self::REST_NS,'route'=>self::REST_ROUTE_TX]);

        register_rest_route(self::REST_NS, self::REST_ROUTE_TX, [
            'methods'  => 'POST',
            'callback' => [$this, 'rest_translate'],
            'permission_callback' => [$this, 'rest_permission_ok'],
        ]);

        // Kleiner Info-Endpoint zum Debuggen der Konfiguration/Route
        register_rest_route(self::REST_NS, self::REST_ROUTE_INFO, [
            'methods'  => 'GET',
            'callback' => function(\WP_REST_Request $req) {
                $s = $this->get_addon_settings();
                $endpoint = $this->resolve_deepl_endpoint($s['deepl_api_key'], $s['deepl_endpoint'], !empty($s['deepl_free']));
                return new \WP_REST_Response([
                    'ok'            => true,
                    'provider'      => $s['provider'],
                    'deepl_free'    => (bool)$s['deepl_free'],
                    'deepl_endpoint_resolved' => $endpoint,
                    'libre_endpoint'=> $s['libre_endpoint'],
                ], 200);
            },
            'permission_callback' => [$this, 'rest_permission_ok'],
        ]);
    }

    public function rest_permission_ok() {
        return $this->user_is_allowed_translator();
    }

    public function rest_translate(\WP_REST_Request $req) {
        $cid = $this->_cid();
        $nonce = $req->get_param('_ajax_nonce');
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            $this->_write_log_line('rest:denied', ['reason'=>'bad_nonce'], $cid);
            return new \WP_REST_Response(['ok'=>false,'error'=>'bad_nonce','cid'=>$cid], 403);
        }

        $text   = (string)$req->get_param('text');
        $source = $this->norm_lang($req->get_param('source') ?: '');
        $target = $this->norm_lang($req->get_param('target') ?: '');
        $force_free = (bool)$req->get_param('force_free'); // optional: REST-seitiger Zwang

        if ($text === '' || $target === '') {
            $this->_write_log_line('rest:invalid', ['reason'=>'missing_params'], $cid);
            return new \WP_REST_Response(['ok'=>false,'error'=>'missing_params','cid'=>$cid], 400);
        }

        $s = $this->get_addon_settings();
        if ($force_free) $s['deepl_free'] = 1; // „kostenlos“ via REST überschreibbar
        $this->_write_log_line('rest:start', [
            'provider'=>$s['provider'],
            'source'=>$source,
            'target'=>$target,
            'deepl_free'=>(bool)$s['deepl_free'],
            'text_preview'=>$this->_short($text)
        ], $cid);

        $resp = ['ok'=>true,'text'=>$text,'cid'=>$cid];
        $provider_used = $s['provider'];

        if ($s['provider'] === 'deepl') {
            $resp = $this->translate_deepl($text, $source, $target, $s);
            $fallback_allowed = !empty($s['fallback_to_libre']);
            $libre_ready = !empty($s['libre_endpoint']);
            if (!$resp['ok'] && $fallback_allowed && $libre_ready) {
                $this->_write_log_line('rest:fallback', ['from'=>'deepl','to'=>'libre','reason'=>$resp['error'] ?? 'unknown'], $cid);
                $resp2 = $this->translate_libre($text, $source, $target, $s);
                if ($resp2['ok']) {
                    $resp = $resp2; $provider_used = 'libre';
                    $resp['fallback_from'] = 'deepl';
                } else {
                    $resp = [
                        'ok'=>false,
                        'error'=>'both_failed',
                        'cid'=>$cid,
                        'message'=>'DeepL: '.($resp['error']??'error').' | Libre: '.($resp2['error']??'error')
                    ];
                }
            }
        } elseif ($s['provider'] === 'libre') {
            $resp = $this->translate_libre($text, $source, $target, $s);
        } else {
            $resp = ['ok'=>false,'cid'=>$cid,'error'=>'no_provider'];
        }

        $resp['provider_used'] = $provider_used;
        $code = !empty($resp['ok']) ? 200 : 500;
        $this->_write_log_line('rest:done', ['ok'=>$resp['ok'], 'error'=>$resp['error'] ?? '', 'provider_used'=>$provider_used], $cid);
        return new \WP_REST_Response($resp, $code);
    }

    private function resolve_deepl_endpoint($api_key, $endpoint, $force_free = false) {
        // Wenn ausdrücklich „DeepL Free“ gesetzt ist, IMMER api-free verwenden
        if ($force_free) {
            return 'https://api-free.deepl.com/v2/translate';
        }

        $endpoint = trim((string)$endpoint);

        // Automatische Erkennung via Key-Suffix „:fx“ (DeepL Free)
        $is_free_key = (bool)preg_match('~:fx$~i', trim((string)$api_key));

        // Wenn Key nach „Free“ aussieht, ebenfalls api-free erzwingen
        if ($is_free_key) {
            return 'https://api-free.deepl.com/v2/translate';
        }

        // Sonst: Pro-Endpoint respektieren/normalisieren
        $scheme = 'https';
        $host   = 'api.deepl.com';
        $path   = '/v2/translate';

        if ($endpoint !== '') {
            $parts = wp_parse_url($endpoint);
            if (!empty($parts['scheme'])) $scheme = $parts['scheme'];
            if (!empty($parts['host'])) {
                $inputHost = strtolower($parts['host']);
                if (strpos($inputHost, 'deepl.com') !== false) {
                    $host = 'api.deepl.com';
                    $path = '/v2/translate';
                } else {
                    $host = $parts['host'];
                    $path = !empty($parts['path']) ? $parts['path'] : $path;
                }
            }
            if (strpos($host, 'deepl.com') !== false && !empty($parts['path']) && stripos($parts['path'], '/v2/translate') !== false) {
                $path = $parts['path'];
            }
        }

        return $scheme . '://' . $host . $path;
    }

    private function translate_deepl($text, $source, $target, $s) {
        $cid = $this->_cid();
        $endpoint = $this->resolve_deepl_endpoint($s['deepl_api_key'], $s['deepl_endpoint'], !empty($s['deepl_free']));

        $body = [
            'text'                => $text,
            'target_lang'         => strtoupper($target),
            'preserve_formatting' => 1,
            'split_sentences'     => 'nonewlines',
        ];
        if ($source) $body['source_lang'] = strtoupper($source);

        $args = [
            'timeout' => (int)($s['timeout'] ?? 20),
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key ' . $s['deepl_api_key'],
                'Content-Type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
            ],
            'body'    => $body,
        ];

        $resp = $this->http_request('POST', $endpoint, $args, $cid);
        if (is_wp_error($resp)) {
            return ['ok'=>false, 'provider'=>'deepl', 'error'=>'wp_error', 'message'=>$resp->get_error_message(), 'text'=>$text, 'cid'=>$cid];
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $data = json_decode($raw, true);

        if ($code===200 && is_array($data) && isset($data['translations'][0]['text'])) {
            return ['ok'=>true, 'provider'=>'deepl', 'text'=>(string)$data['translations'][0]['text'], 'cid'=>$cid];
        }

        $err = 'deepl_http_' . $code;
        $msg = '';
        if (is_array($data)) {
            if (isset($data['message'])) $msg = (string)$data['message'];
            elseif (isset($data['error']['message'])) $msg = (string)$data['error']['message'];
        }
        if ($code===456) $err = 'deepl_quota_exceeded';
        if ($code===429) $err = 'deepl_rate_limited';
        if ($code===401) $err = 'deepl_unauthorized';
        if ($code===403) $err = 'deepl_forbidden';
        if ($code===400) $err = 'deepl_bad_request';

        return ['ok'=>false, 'provider'=>'deepl', 'error'=>$err, 'message'=>$msg, 'text'=>$text, 'cid'=>$cid, 'details'=>$this->_short($raw)];
    }

    private function translate_libre($text, $source, $target, $s) {
        $cid = $this->_cid();
        $payload = [
            'q'      => $text,
            'source' => $source ?: 'auto',
            'target' => $target,
            'format' => 'text'
        ];
        $args = [
            'timeout' => (int)($s['timeout'] ?? 20),
            'headers' => ['Content-Type'=>'application/json'],
            'body'    => wp_json_encode($payload),
        ];

        $resp = $this->http_request('POST', $s['libre_endpoint'], $args, $cid);
        if (is_wp_error($resp)) {
            return ['ok'=>false, 'provider'=>'libre', 'error'=>'wp_error', 'message'=>$resp->get_error_message(), 'text'=>$text, 'cid'=>$cid];
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $body = json_decode($raw, true);
        if ($code===200 && isset($body['translatedText'])) {
            return ['ok'=>true, 'provider'=>'libre', 'text'=>(string)$body['translatedText'], 'cid'=>$cid];
        }
        $msg = is_array($body) ? ($body['error'] ?? $raw) : $raw;
        return ['ok'=>false, 'provider'=>'libre', 'error'=>'libre_http_'.$code, 'text'=>$text, 'cid'=>$cid, 'details'=>$this->_short($msg)];
    }

    public function admin_assets($hook) {
        // PHP 8: native str_starts_with
        $page = isset($_GET['page']) ? (string)$_GET['page'] : '';
        if ($page !== '' && strpos($page, self::PARENT_MENU_SLUG) === 0) {
            // keine externen Assets – Styles/JS inline
        }
    }
}

// Initialisierung wird vom Modul-Loader durchgeführt
if (!function_exists('ebcp_translator_init')) {
    function ebcp_translator_init() {
        EBCPDGPTM_Guidelines_Translator_Addon::init();
    }
}
}
