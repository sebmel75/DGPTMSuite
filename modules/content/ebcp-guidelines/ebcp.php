<?php
/**
 * Plugin Name: EBCPDGPTM Guidelines Viewer
 * Description: Zeigt die EBCP-Guidelines (mehrsprachig) als filterbare Tabelle mit Suche, Bearbeitungsfunktion, PDF-Export (mit Abschnittsüberschriften) sowie sprachspezifischen Tabellenköpfen aus den Einstellungen; CSV Ex-/Import ausschließlich im Backend.
 * Version:     1.4.5
 * Author:      Sebastian Melzer
 * License:     GPLv2 or later
 * Text Domain: ebcpdgptm-guidelines-viewer
 */

if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('EBCPDGPTM_Guidelines_Viewer') ) {

    final class EBCPDGPTM_Guidelines_Viewer {
        const OPT_KEY            = 'ebcp_guidelines_json';
        const OPT_META           = 'ebcp_guidelines_meta';
        const REST_NS            = 'ebcpdgptm/v1';
        const REST_ROUTE         = '/guidelines';
        const CAP_MANAGE         = 'manage_options';
        const NONCE_UPLOAD       = 'ebcpdgptm_guidelines_upload';
        const NONCE_SAVE         = 'ebcpdgptm_guidelines_save';
        const NONCE_INSERT       = 'ebcpdgptm_guidelines_insert';
        const NONCE_DELETE       = 'ebcpdgptm_guidelines_delete';
        const NONCE_SAVE_SET     = 'ebcpdgptm_guidelines_save_settings';
        const NONCE_SAVE_HEADERS = 'ebcpdgptm_guidelines_save_headers';
        const NONCE_EXP_CSV      = 'ebcpdgptm_guidelines_export_csv';
        const NONCE_EXP_SET      = 'ebcpdgptm_guidelines_export_settings';
        const NONCE_IMP_SET      = 'ebcpdgptm_guidelines_import_settings';
        const NONCE_REPLACE_CSV  = 'ebcpdgptm_replace_lang_csv';

        /** NEU: Daten-JSON-Export */
        const NONCE_EXP_JSON     = 'ebcpdgptm_guidelines_export_json';

        const MENU_SLUG          = 'ebcpdgptm-guidelines';

        public static function init() {
            $self = new self();
            add_action('init', [$self, 'load_textdomain_and_hooks']);
        }

        public function load_textdomain_and_hooks() {
            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('admin_post_ebcpdgptm_upload',            [$this, 'handle_upload']);
            add_action('admin_post_ebcpdgptm_save',              [$this, 'handle_save']);
            add_action('admin_post_ebcpdgptm_insert_row',        [$this, 'handle_insert_row']);
            add_action('admin_post_ebcpdgptm_delete_row',        [$this, 'handle_delete_row']);
            add_action('admin_post_ebcpdgptm_save_settings',     [$this, 'handle_save_settings']);
            add_action('admin_post_ebcpdgptm_save_headers',      [$this, 'handle_save_headers']);
            add_action('admin_post_ebcpdgptm_export_csv',        [$this, 'handle_export_csv']);
            add_action('admin_post_ebcpdgptm_export_settings',   [$this, 'handle_export_settings']);
            add_action('admin_post_ebcpdgptm_import_settings',   [$this, 'handle_import_settings']);
            add_action('admin_post_ebcpdgptm_replace_lang_csv',  [$this, 'handle_replace_lang_csv']);

            /** NEU: Daten-JSON-Export */
            add_action('admin_post_ebcpdgptm_export_json',       [$this, 'handle_export_json']);

            add_action('rest_api_init',                          [$this, 'register_rest']);
            add_shortcode('ebcpdgptm_guidelines',                [$this, 'shortcode']);
            add_filter('rocket_exclude_rest_api',                [$this, 'exclude_rest_api_from_rocket']);
        }

        public function exclude_rest_api_from_rocket($endpoints) { $endpoints[] = self::REST_NS . '/(.*)'; return $endpoints; }

        private static function read_dataset(): ?array {
            $raw = get_option(self::OPT_KEY);
            if (is_array($raw)) return $raw;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
            }
            return null;
        }

        /** Defaults für tabellenspezifische Sprach-Header */
        private function get_default_table_headers(string $lang): array {
            $defaults = [
                'en' => ['text' => 'Recommendation', 'class' => 'Class',   'loe' => 'LOE'],
                'de' => ['text' => 'Empfehlung',     'class' => 'Klasse',  'loe' => 'LOE'],
                'fr' => ['text' => 'Recommandation', 'class' => 'Classe',  'loe' => 'LOE'],
                'es' => ['text' => 'Recomendación',  'class' => 'Clase',   'loe' => 'LOE'],
                'it' => ['text' => 'Raccomandazione','class' => 'Classe',  'loe' => 'LOE'],
                'pt' => ['text' => 'Recomendação',   'class' => 'Classe',  'loe' => 'LOE'],
                'nl' => ['text' => 'Aanbeveling',    'class' => 'Klasse',  'loe' => 'LOE'],
                'ar' => ['text' => 'توصية',          'class' => 'الفئة',   'loe' => 'LOE'],
            ];
            return $defaults[$lang] ?? $defaults['en'];
        }

        /** Meta laden/sichern **/
        private function get_meta(): array {
            $meta = get_option(self::OPT_META, []);
            if (!isset($meta['years'])) $meta['years'] = ['now' => 2024, 'old' => 2019];

            // Sprachen (vollständige Liste aller bekannten Sprachen)
            if (!isset($meta['languages'])) {
                $ds = self::read_dataset();
                $meta['languages'] = isset($ds['languages']) && is_array($ds['languages']) ? $ds['languages'] : ['en','de','fr','es','it','pt','nl','ar'];
            }

            // NEU: Aktiv-Status pro Sprache (default: alle aktiv)
            if (!isset($meta['languages_enabled']) || !is_array($meta['languages_enabled'])) {
                $meta['languages_enabled'] = [];
            }
            foreach ($meta['languages'] as $l) {
                if (!isset($meta['languages_enabled'][$l])) {
                    $meta['languages_enabled'][$l] = true;
                }
            }

            // Tabellenköpfe nach Sprache
            if (!isset($meta['headers']) || !is_array($meta['headers'])) $meta['headers'] = [];
            foreach ($meta['languages'] as $l) {
                if (empty($meta['headers'][$l]) || !is_array($meta['headers'][$l])) {
                    $meta['headers'][$l] = $this->get_default_table_headers($l);
                } else {
                    $meta['headers'][$l] = array_merge($this->get_default_table_headers($l), $meta['headers'][$l]); // fehlende Keys füllen
                }
            }
            return $meta;
        }
        private function save_meta(array $meta): void { update_option(self::OPT_META, $meta); }

        /** Liste aller bekannten Sprachen (Backend-Operationen, Exporte, Headerpflege) */
        private function get_all_languages(): array {
            $m = $this->get_meta();
            $langs = $m['languages'] ?? [];
            if (!$langs) {
                $ds = self::read_dataset();
                $langs = is_array($ds['languages'] ?? null) ? $ds['languages'] : [];
            }
            $langs = array_values(array_unique(array_map('strval', $langs)));
            return $langs ?: ['en'];
        }

        /** Sichtbare/aktive Sprachen (Frontend & REST) */
        private function get_languages(): array {
            $m = $this->get_meta();
            $all = $m['languages'] ?? [];
            $enabled = [];
            if (is_array($m['languages_enabled'] ?? null)) {
                foreach ($all as $l) {
                    if (!isset($m['languages_enabled'][$l]) || $m['languages_enabled'][$l]) {
                        $enabled[] = $l;
                    }
                }
            }
            if (!$enabled) $enabled = $all; // Fallback: wenn versehentlich alle deaktiviert wurden
            $enabled = array_values(array_unique(array_map('strval', $enabled)));
            return $enabled ?: ['en'];
        }

        /** Jahreszahlen aus Meta */
        private function get_years(): array {
            $m = $this->get_meta();
            $years = $m['years'] ?? ['now' => 2024, 'old' => 2019];
            $years['now'] = intval($years['now'] ?? 2024);
            $years['old'] = intval($years['old'] ?? 2019);
            return $years;
        }

        /** Tabellenköpfe für eine Sprache */
        private function get_headers_for_lang(string $lang): array {
            $m = $this->get_meta();
            $h = $m['headers'][$lang] ?? $this->get_default_table_headers($lang);
            return array_merge($this->get_default_table_headers($lang), $h);
        }

        /** Frontend-Text bereinigen: Trenner ';' und \" aus JSON-Import entfernen */
        private function clean_front_text(?string $s): string {
            if ($s === null) return '';
            $s = str_replace('\"', '"', $s);
            $s = str_replace(';', '', $s);
            return $s;
        }

        /** Link-Wrapper, falls URL vorhanden */
        private function wrap_text_with_url(string $text, ?string $url): string {
            $text = $this->clean_front_text($text);
            $url  = trim((string)$url);
            if ($url !== '') {
                return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($text) . '</a>';
            }
            return esc_html($text);
        }

        /** CSV -> Array von Datensätzen (sprachweise Import/Re-Import) */
        private function parse_csv_rows(string $csv, string $lang): array {
            $years = $this->get_years();
            $rows = [];

            // --- Normalize BOM and line endings ---
            if (substr($csv, 0, 3) === "\xEF\xBB\xBF") {
                $csv = substr($csv, 3);
            }
            $csv = str_replace(["\r\n", "\r"], "\n", $csv);

            $fp = fopen('php://temp', 'r+');
            fwrite($fp, $csv);
            rewind($fp);

            $delim = ';';

            $header = fgetcsv($fp, 0, $delim);
            if (!is_array($header)) { fclose($fp); return []; }

            // Normalize header names and strip possible BOM on first cell
            $norm = array_map(function($h){
                $h = (string)$h;
                // Strip UTF-8 BOM if present on first header cell
                $h = preg_replace('/^\xEF\xBB\xBF/u', '', $h);
                $h = strtolower(trim($h));
                $h = str_replace(['ä','ö','ü','ß'], ['ae','oe','ue','ss'], $h);
                $h = preg_replace('/[^a-z0-9_]+/','_', $h);
                return $h;
            }, $header);

            $idx = function(string $key, array $alts = []) use ($norm) {
                $cands = array_merge([$key], $alts);
                foreach ($cands as $c) {
                    $i = array_search($c, $norm, true);
                    if ($i !== false) return intval($i);
                }
                return -1;
            };

            $i_number = $idx('number', ['nummer','nr','no','num']);
            $i_text   = $idx('text', ['recommendation','empfehlung']);
            $i_url    = $idx('url', ['link','href']);
            $i_c_now  = $idx('class_'.$years['now'], ['class_2024','class_now','klasse_'.$years['now'],'klasse_2024']);
            $i_l_now  = $idx('loe_'.$years['now'],   ['loe_2024','loe_now']);
            $i_c_old  = $idx('class_'.$years['old'], ['class_2019','class_old','klasse_'.$years['old'],'klasse_2019']);
            $i_l_old  = $idx('loe_'.$years['old'],   ['loe_2019','loe_old']);
            $i_is_sec = $idx('is_section', ['section','issection','uberschrift','ueberschrift','heading']);

            $line_no = 0;
            while (($line = fgetcsv($fp, 0, $delim)) !== false) {
                // Skip completely empty lines
                if (count(array_filter($line, fn($v)=>trim((string)$v)!=='')) === 0) continue;

                $line_no++;

                $get = function($i) use ($line) { return ($i >= 0 && isset($line[$i])) ? (string)$line[$i] : ''; };
                $truthy = function($v) {
                    $v = strtolower(trim((string)$v));
                    return in_array($v, ['1','true','yes','y','ja','wahr','oui','si','verdadeiro','waar','on'], true);
                };

                $number = $get($i_number);
                // Fallback: if the column is missing or empty, use the running line number (stable across languages if CSVs share the same order)
                if ($number === '' || $i_number < 0) {
                    $number = (string)$line_no;
                }

                $text   = $get($i_text);
                $url    = $get($i_url);
                $c_now  = $get($i_c_now);
                $l_now  = $get($i_l_now);
                $c_old  = $get($i_c_old);
                $l_old  = $get($i_l_old);
                $is_sec = $truthy($get($i_is_sec));

                $rows[] = [
                    'number'     => sanitize_text_field($number),
                    'row_type'   => $is_sec ? 'section' : 'item',
                    'lang'       => sanitize_text_field($lang),
                    'text'       => sanitize_textarea_field($text),
                    'url'        => esc_url_raw($url),
                    'class_2024' => sanitize_text_field($c_now),
                    'loe_2024'   => sanitize_text_field($l_now),
                    'class_2019' => sanitize_text_field($c_old),
                    'loe_2019'   => sanitize_text_field($l_old),
                ];
            }
            fclose($fp);
            return $rows;
        }
		
		/** Parse HTTP_ACCEPT_LANGUAGE into ordered list ['de-de','de','en-us','en', ...] */
private function parse_accept_language($hdr) : array {
    if (!is_string($hdr) || $hdr === '') return [];
    $langs = [];
    foreach (explode(',', $hdr) as $part) {
        $pieces = explode(';', trim($part));
        $code = trim($pieces[0]);
        $q = 1.0;
        if (isset($pieces[1]) && preg_match('~q=([0-9\.]+)~', $pieces[1], $m)) {
            $q = (float)$m[1];
        }
        if ($code !== '') $langs[] = ['code'=>$code, 'q'=>$q];
    }
    if (!$langs) return [];
    usort($langs, function($a,$b){ return $a['q'] < $b['q'] ? 1 : ($a['q'] > $b['q'] ? -1 : 0); });
    $ordered = [];
    foreach ($langs as $l) {
        $ordered[] = $l['code'];
        if (strlen($l['code']) >= 2) $ordered[] = substr($l['code'], 0, 2);
    }
    $seen = []; $out = [];
    foreach ($ordered as $c) {
        $c = strtolower(trim($c));
        if ($c !== '' && !isset($seen[$c])) { $seen[$c]=true; $out[]=$c; }
    }
    return $out;
}

/** Detect request language: GET 'lang' -> Accept-Language -> site locale; fallback 'en'. */
private function detect_request_lang(array $ui_langs, $atts_lang = 'auto') : string {
    $ui_langs = array_map('strtolower', array_map('trim', $ui_langs));
    $fallback = in_array('en', $ui_langs, true) ? 'en' : ($ui_langs[0] ?? 'en');

    // 1) shortcode-Attribut (explizit)
    if (is_string($atts_lang) && strtolower($atts_lang) !== 'auto') {
        $cand = strtolower(trim($atts_lang));
        if (in_array($cand, $ui_langs, true)) return $cand;
    }
    // 2) URL-Param ?lang=xx
    if (isset($_GET['lang'])) {
        $cand = strtolower(sanitize_text_field($_GET['lang']));
        if (in_array($cand, $ui_langs, true)) return $cand;
    }
    // 3) Browser-Header
    $prefs = $this->parse_accept_language($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    foreach ($prefs as $p) {
        $m = $this->map_locale_to_lang($p);
        if (in_array($m, $ui_langs, true)) return $m;
    }
    // 4) Site-Locale
    $m = $this->map_locale_to_lang(get_locale());
    if (in_array($m, $ui_langs, true)) return $m;

    return $fallback;
}

		
		
		
		
		

        public function admin_menu() { add_menu_page('EBCP Guidelines', 'EBCP Guidelines', self::CAP_MANAGE, self::MENU_SLUG, [$this, 'render_admin_page'], 'dashicons-welcome-learn-more', 58); }

        public function render_admin_page() {
            $active_tab = $_GET['tab'] ?? 'import';
            if ($active_tab === 'translator') {
                if (!current_user_can('edit_pages') && !current_user_can(self::CAP_MANAGE)) wp_die(__('Keine Berechtigung.'));
            } else {
                if (!current_user_can(self::CAP_MANAGE)) wp_die(__('Keine Berechtigung.'));
            }
        
            if (!current_user_can(self::CAP_MANAGE)) wp_die(__('Keine Berechtigung.'));
            $active_tab = $_GET['tab'] ?? 'import';
            ?>
            <div class="wrap"><h1>EBCP Guidelines</h1>
                <nav class="nav-tab-wrapper">
                    <a href="?page=<?php echo self::MENU_SLUG; ?>&tab=import" class="nav-tab <?php if($active_tab == 'import') echo 'nav-tab-active'; ?>">Import & Basis-Einstellungen</a>
                    <a href="?page=<?php echo self::MENU_SLUG; ?>&tab=headers" class="nav-tab <?php if($active_tab == 'headers') echo 'nav-tab-active'; ?>">Tabellenköpfe je Sprache</a>
                    <a href="?page=<?php echo self::MENU_SLUG; ?>&tab=edit" class="nav-tab <?php if($active_tab == 'edit') echo 'nav-tab-active'; ?>">Daten bearbeiten</a>
                    <a href="?page=<?php echo self::MENU_SLUG; ?>&tab=translator" class="nav-tab <?php if ($active_tab == 'translator') echo 'nav-tab-active'; ?>">Übersetzen (Addon)</a>
                </nav>
                <div class="tab-content" style="margin-top: 1em;">
                    <?php if (isset($_GET['success'])) echo '<div class="notice notice-success is-dismissible"><p>Aktion erfolgreich ausgeführt.</p></div>'; ?>
                    <?php
                        if ($active_tab == 'import') $this->render_import_tab();
                        elseif ($active_tab == 'headers') $this->render_headers_tab();
                        elseif ($active_tab == 'translator') {
                            // Delegation an Addon
                            if (has_action('ebcpdgptm_render_translator')) {
                                do_action('ebcpdgptm_render_translator');
                            } elseif (class_exists('EBCPDGPTM_Guidelines_Translator_Addon')) {
                                // Fallback: direktes Rendern, falls Addon aktiv aber ohne Hook
                                $__addon = new \EBCPDGPTM_Guidelines_Translator_Addon();
                                $__addon->render_router_page();
                            } else {
                                echo '<div class="notice notice-error"><p>Translator‑Addon ist nicht aktiv.</p></div>';
                            }
                        } else $this->render_edit_tab();
                    ?>
                </div>
            </div>
            <?php
        }

        private function render_import_tab() {
            $meta = $this->get_meta();
            $last = isset($meta['updated_at']) ? esc_html($meta['updated_at']) : '—';
            $years = $this->get_years();

            /** NEU: Alle vs. aktive Sprachen getrennt */
            $all_langs = $this->get_all_languages();
            $active_langs = $this->get_languages();
            $enabled_map = $meta['languages_enabled'] ?? [];
            $default_lang = $all_langs[0] ?? 'en';
            ?>
            <h2>Daten importieren</h2>
            <p>Lade hier die normalisierte JSON-Datei hoch. Bestehende Daten werden überschrieben.</p>
            <p><strong>Zuletzt aktualisiert:</strong> <?php echo $last; ?></p>
            <div style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE_UPLOAD); ?>
                    <input type="hidden" name="action" value="ebcpdgptm_upload">
                    <input type="file" name="ebcpdgptm_json" accept=".json,application/json" required>
                    <?php submit_button('Import starten'); ?>
                </form>

                <!-- NEU: Daten-JSON exportieren -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE_EXP_JSON); ?>
                    <input type="hidden" name="action" value="ebcpdgptm_export_json">
                    <?php submit_button('Daten als JSON exportieren', 'secondary'); ?>
                </form>
            </div>

            <hr>
            <h3>Basis-Einstellungen (Jahre & Sprachen)</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:1em;">
                <?php wp_nonce_field(self::NONCE_SAVE_SET); ?>
                <input type="hidden" name="action" value="ebcpdgptm_save_settings">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ebcpdgptm_year_now">Aktuelles Jahr (oberhalb Class/LOE)</label></th>
                        <td><input type="number" id="ebcpdgptm_year_now" name="year_now" value="<?php echo esc_attr($years['now']); ?>" min="1900" max="2100"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ebcpdgptm_year_old">Vergleichsjahr (oberhalb Class/LOE)</label></th>
                        <td><input type="number" id="ebcpdgptm_year_old" name="year_old" value="<?php echo esc_attr($years['old']); ?>" min="1900" max="2100"></td>
                    </tr>
                    <tr>
                        <th scope="row">Vorhandene Sprachen</th>
                        <td>
                            <code><?php echo esc_html(implode(', ', $all_langs)); ?></code>
                            <p class="description">Gesamtliste aller Sprachen im System (für Ex-/Import & Headerpflege).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Aktive Sprachen (Frontend & REST)</th>
                        <td>
                            <?php foreach ($all_langs as $l_code): ?>
                                <label style="display:inline-block; min-width:90px; margin-right:12px;">
                                    <input type="checkbox" name="enabled_langs[]" value="<?php echo esc_attr($l_code); ?>" <?php checked($enabled_map[$l_code] ?? true); ?>>
                                    <?php echo esc_html($l_code); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">Deaktivierte Sprachen bleiben in den Daten bestehen, sind aber im Frontend/REST nicht auswählbar.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ebcpdgptm_new_lang">Zusätzliche Sprache anlegen</label></th>
                        <td>
                            <input type="text" id="ebcpdgptm_new_lang" name="new_lang" placeholder="z.B. pl" pattern="[A-Za-z\-]{2,5}">
                            <p class="description">Sprachcode (2–5 Zeichen, z. B. en, de, fr, es, it, pt, nl, ar, pl). Tabellenköpfe werden automatisch mit Defaultwerten vorgefüllt. Neue Sprachen werden automatisch aktiviert.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Einstellungen speichern'); ?>
            </form>

            <h3>Einstellungen exportieren/importieren</h3>
            <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE_EXP_SET); ?>
                    <input type="hidden" name="action" value="ebcpdgptm_export_settings">
                    <?php submit_button('Einstellungen als JSON exportieren', 'secondary'); ?>
                </form>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE_IMP_SET); ?>
                    <input type="hidden" name="action" value="ebcpdgptm_import_settings">
                    <input type="file" name="ebcpdgptm_settings_json" accept=".json,application/json" required>
                    <?php submit_button('Einstellungen importieren', 'secondary'); ?>
                </form>
            </div>

            <hr>
            <h3>CSV-Export / -Re-Import (pro Sprache, ersetzt vollständig)</h3>
            <p><strong>Hinweis:</strong> CSV Ex-/Import ist ausschließlich hier im Backend verfügbar.</p>

            <!-- Sprache nur EINMAL auswählen und dann für beide Formulare verwenden -->
            <p>
                <label for="ebcpdgptm_lang_shared"><strong>Sprache (gilt für Export & Import)</strong></label><br>
                <select id="ebcpdgptm_lang_shared">
                    <?php foreach ($all_langs as $l_code): ?>
                        <option value="<?php echo esc_attr($l_code); ?>"><?php echo esc_html($l_code); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <div style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
                <!-- Export-Formular: verwendet versteckte Sprach-Input -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="ebcpdgptm_export_form">
                    <?php wp_nonce_field(self::NONCE_EXP_CSV); ?>
                    <input type="hidden" name="action" value="ebcpdgptm_export_csv">
                    <input type="hidden" name="lang" class="ebcpdgptm_lang_hidden" value="<?php echo esc_attr($default_lang); ?>">
                    <?php submit_button('CSV exportieren', 'secondary'); ?>
                </form>

                <!-- Import-Formular: verwendet versteckte Sprach-Input -->
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="ebcpdgptm_import_form">
                    <?php wp_nonce_field(self::NONCE_REPLACE_CSV); ?>
                    <input type="hidden" name="action" value="ebcpdgptm_replace_lang_csv">
                    <input type="hidden" name="lang" class="ebcpdgptm_lang_hidden" value="<?php echo esc_attr($default_lang); ?>">
                    <p>
                        <label for="ebcpdgptm_csv_file"><strong>CSV-Datei</strong></label><br>
                        <input id="ebcpdgptm_csv_file" type="file" name="ebcpdgptm_csv" accept=".csv,text/csv" required>
                    </p>
                    <?php submit_button('CSV importieren (Sprache ersetzen)', 'secondary'); ?>
                </form>
            </div>

            <script>
            (function(){
                const sel = document.getElementById('ebcpdgptm_lang_shared');
                if (!sel) return;
                const hiddenInputs = document.querySelectorAll('.ebcpdgptm_lang_hidden');
                const sync = () => hiddenInputs.forEach(i => i.value = sel.value);
                sel.addEventListener('change', sync);
                sync();
            })();
            </script>

            <p class="description">Erwartete Spalten (Trenner „;“): <code>number</code>; <code>text</code>; <code>class_<?php echo esc_html($years['now']); ?></code>; <code>loe_<?php echo esc_html($years['now']); ?></code>; <code>class_<?php echo esc_html($years['old']); ?></code>; <code>loe_<?php echo esc_html($years['old']); ?></code>; <code>url</code>; <code>is_section</code> (true/false). <em>Hinweis:</em> <code>lang</code> ist optional und wird durch die gewählte Sprache ersetzt.</p>

            <hr><h3>Daten-Vorschau</h3>
            <?php
            $decoded = self::read_dataset();
            if ($decoded) {
                echo '<p><strong>Zeilen:</strong> ' . intval($decoded['row_count'] ?? count($decoded['data'])) . '</p>';
                echo '<p><strong>Sprachen (Daten):</strong> ' . implode(', ', array_map('esc_html', (array)($decoded['languages'] ?? []))) . '</p>';
            } else {
                echo '<p>Noch keine Daten importiert.</p>';
            }
        }

        /** Tabellenkopf-Einstellungen je Sprache */
        private function render_headers_tab() {
            $langs   = $this->get_all_languages(); // NEU: alle Sprachen zeigen
            $headers = $this->get_meta()['headers'] ?? [];
            ?>
            <h2>Tabellenköpfe je Sprache</h2>
            <p>Diese Bezeichnungen werden ausschließlich für die Tabellenköpfe im Frontend genutzt.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE_SAVE_HEADERS); ?>
                <input type="hidden" name="action" value="ebcpdgptm_save_headers">
                <table class="widefat striped" style="max-width: 900px;">
                    <thead><tr><th>Sprache</th><th>Spalte „Text“</th><th>Label „Class“</th><th>Label „LOE“</th></tr></thead>
                    <tbody>
                        <?php foreach ($langs as $l): $h = $headers[$l] ?? $this->get_default_table_headers($l); ?>
                        <tr>
                            <td><strong><?php echo esc_html($l); ?></strong></td>
                            <td><input type="text" name="headers[<?php echo esc_attr($l); ?>][text]" value="<?php echo esc_attr($h['text'] ?? ''); ?>" class="regular-text"></td>
                            <td><input type="text" name="headers[<?php echo esc_attr($l); ?>][class]" value="<?php echo esc_attr($h['class'] ?? ''); ?>" class="regular-text"></td>
                            <td><input type="text" name="headers[<?php echo esc_attr($l); ?>][loe]"   value="<?php echo esc_attr($h['loe'] ?? ''); ?>" class="regular-text"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button('Tabellenköpfe speichern'); ?>
            </form>
            <?php
        }
        
        /** NEU: Edit-Tab nach Sprachen separiert + Übersetzungsmodus */
        private function render_edit_tab() {
            $dataset = self::read_dataset(); if (!$dataset || empty($dataset['data'])) { echo '<p>Keine Daten zum Bearbeiten vorhanden.</p>'; return; }

            $all_langs = $this->get_all_languages();
            $edit_lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : ($all_langs[0] ?? 'en');
            if (!in_array($edit_lang, $all_langs, true)) $edit_lang = $all_langs[0] ?? 'en';

            $compare_lang = isset($_GET['compare']) ? sanitize_text_field($_GET['compare']) : '';
            if ($compare_lang === $edit_lang) $compare_lang = '';

            $translation_mode = isset($_GET['tmode']) && ($_GET['tmode'] === '1');

            // Alle Zeilen dieser Sprache mit globalem Index sammeln (keine Pagination)
            $records = [];
            foreach (($dataset['data'] ?? []) as $global_index => $row) {
                if (isset($row['lang']) && strtolower($row['lang']) === strtolower($edit_lang)) {
                    $records[] = ['idx' => $global_index, 'row' => $row];
                }
            }

            // Map für Übersetzungsmodus (nach 'number')
            $compare_map = [];
            if ($translation_mode && $compare_lang) {
                foreach (($dataset['data'] ?? []) as $r) {
                    if (isset($r['lang']) && strtolower($r['lang']) === strtolower($compare_lang)) {
                        $num = (string)($r['number'] ?? '');
                        if ($num !== '') $compare_map[$num] = $r;
                    }
                }
            }

            ?>
            <h2>Daten bearbeiten</h2>
            <p>Änderungen hier werden direkt in der Datenbank gespeichert.</p>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom:1em; display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">
                <input type="hidden" name="tab" value="edit">
                <p>
                    <label><strong>Bearbeitungssprache</strong><br>
                        <select name="lang">
                            <?php foreach ($all_langs as $l): ?>
                                <option value="<?php echo esc_attr($l); ?>" <?php selected($edit_lang, $l); ?>><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </p>
                <p>
                    <label><strong>Übersetzungsmodus</strong><br>
                        <select name="tmode">
                            <option value="0" <?php selected(!$translation_mode); ?>>Aus</option>
                            <option value="1" <?php selected($translation_mode); ?>>An</option>
                        </select>
                    </label>
                </p>
                <p>
                    <label><strong>Vergleichssprache</strong><br>
                        <select name="compare">
                            <option value="">–</option>
                            <?php foreach ($all_langs as $l): if ($l === $edit_lang) continue; ?>
                                <option value="<?php echo esc_attr($l); ?>" <?php selected($compare_lang, $l); ?>><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </p>
                <?php submit_button('Ansicht aktualisieren', 'secondary', '', false); ?>
                <p style="margin:0 1rem;"><strong>Zeilen (<?php echo esc_html($edit_lang); ?>):</strong> <?php echo count($records); ?></p>
                <?php if ($translation_mode && $compare_lang): ?>
                    <p style="margin:0 1rem;"><strong>Vergleich aktiv:</strong> <?php echo esc_html($compare_lang); ?></p>
                <?php endif; ?>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE_SAVE); ?>
                <input type="hidden" name="action" value="ebcpdgptm_save">
                <!-- damit nach dem Speichern zur selben Ansicht zurückgeleitet wird -->
                <input type="hidden" name="edit_lang" value="<?php echo esc_attr($edit_lang); ?>">
                <input type="hidden" name="tmode" value="<?php echo $translation_mode ? '1' : '0'; ?>">
                <input type="hidden" name="compare_lang" value="<?php echo esc_attr($compare_lang); ?>">

                <div class="tablenav top">
                    <div class="tablenav-pages"><span class="displaying-num"><?php echo count($records); ?> Einträge (alle)</span></div>
                    <?php submit_button('Änderungen speichern', 'primary', 'save_top', false); ?>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 6%;">Spr.</th>
                            <th style="width: 10%;">Nummer</th>
                            <th>Text (<?php echo esc_html($edit_lang); ?>)</th>
                            <?php if ($translation_mode && $compare_lang): ?>
                                <th style="width: 30%;">Vergleich (<?php echo esc_html($compare_lang); ?>)</th>
                            <?php endif; ?>
                            <th style="width: 15%;">URL</th>
                            <th style="width: 7%;">Class 24</th>
                            <th style="width: 7%;">LOE 24</th>
                            <th style="width: 7%;">Class 19</th>
                            <th style="width: 7%;">LOE 19</th>
                            <th style="width: 10%;">Überschrift?</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($records as $rec):
                        $global_index = $rec['idx']; $row = $rec['row'];
                        $num = (string)($row['number'] ?? '');
                        $cmp = ($translation_mode && $compare_lang && $num !== '' && isset($compare_map[$num])) ? $compare_map[$num] : null;
                        $cmp_text = $cmp ? $cmp['text'] ?? '' : '';
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($edit_lang); ?></strong>
                                <input type="hidden" name="data[<?php echo $global_index; ?>][lang]" value="<?php echo esc_attr($edit_lang); ?>">
                            </td>
                            <td><input type="text" name="data[<?php echo $global_index; ?>][number]" value="<?php echo esc_attr($row['number'] ?? ''); ?>" placeholder="Darf doppelt vorkommen"></td>
                            <td>
                                <textarea name="data[<?php echo $global_index; ?>][text]" rows="2" style="width: 100%;"><?php echo esc_textarea($row['text'] ?? ''); ?></textarea>
                                <?php if ($translation_mode && $compare_lang && $cmp_text !== ''): ?>
                                    <button type="button" class="button button-small ebcp-copy-compare" data-target="data[<?php echo $global_index; ?>][text]" data-compare="<?php echo esc_attr($cmp_text); ?>" style="margin-top:6px;">Text aus <?php echo esc_html($compare_lang); ?> übernehmen</button>
                                <?php endif; ?>
                            </td>
                            <?php if ($translation_mode && $compare_lang): ?>
                                <td class="ebcp-compare-cell">
                                    <div class="ebcp-compare-text" style="white-space:pre-wrap;"><?php echo esc_html($cmp_text); ?></div>
                                    <?php if (!$cmp_text && $num !== ''): ?>
                                        <em style="color:#777;">(keine passende Nummer in <?php echo esc_html($compare_lang); ?> gefunden)</em>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td><input type="url" name="data[<?php echo $global_index; ?>][url]" value="<?php echo esc_attr($row['url'] ?? ''); ?>" placeholder="https://…"></td>
                            <td><input type="text" name="data[<?php echo $global_index; ?>][class_2024]" value="<?php echo esc_attr($row['class_2024'] ?? ''); ?>"></td>
                            <td><input type="text" name="data[<?php echo $global_index; ?>][loe_2024]" value="<?php echo esc_attr($row['loe_2024'] ?? ''); ?>"></td>
                            <td><input type="text" name="data[<?php echo $global_index; ?>][class_2019]" value="<?php echo esc_attr($row['class_2019'] ?? ''); ?>"></td>
                            <td><input type="text" name="data[<?php echo $global_index; ?>][loe_2019]" value="<?php echo esc_attr($row['loe_2019'] ?? ''); ?>"></td>
                            <td><input type="checkbox" name="data[<?php echo $global_index; ?>][is_section]" value="1" <?php checked($row['row_type'] ?? 'item', 'section'); ?>></td>
                            <td>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ebcpdgptm_insert_row&index=' . $global_index . '&lang=' . rawurlencode($edit_lang)), self::NONCE_INSERT, '_wpnonce')); ?>" class="button button-small" title="Neue Zeile darunter einfügen">[+]</a>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ebcpdgptm_delete_row&index=' . $global_index . '&lang=' . rawurlencode($edit_lang)), self::NONCE_DELETE, '_wpnonce')); ?>" class="button button-small" onclick="return confirm('Diese Zeile wirklich löschen?');" title="Diese Zeile löschen">[–]</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="tablenav bottom">
                    <?php submit_button('Änderungen speichern', 'primary', 'save_bottom', false); ?>
                </div>
            </form>

            <script>
            (function(){
                document.addEventListener('click', function(e){
                    const btn = e.target.closest('.ebcp-copy-compare');
                    if(!btn) return;
                    e.preventDefault();
                    const compare = btn.getAttribute('data-compare') || '';
                    const targetName = btn.getAttribute('data-target');
                    if(!targetName) return;
                    const textarea = document.querySelector('textarea[name="'+CSS.escape(targetName)+'"]');
                    if(textarea){
                        textarea.value = compare;
                        textarea.style.outline = '2px solid #46b450';
                        setTimeout(()=>textarea.style.outline='', 700);
                    }
                });
            })();
            </script>
            <style>
                .ebcp-compare-cell { background:#f8f9fa; }
                .ebcp-compare-cell .ebcp-compare-text { color:#333; }
            </style>
            <?php
        }

        public function handle_upload() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_UPLOAD);
            if (empty($_FILES['ebcpdgptm_json']['tmp_name'])) {
                wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'error'=>'1'], admin_url('admin.php'))); exit;
            }
            $raw = file_get_contents($_FILES['ebcpdgptm_json']['tmp_name']);
            if ($raw === false) {
                wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'error'=>'2'], admin_url('admin.php'))); exit;
            }
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'error'=>'3'], admin_url('admin.php'))); exit;
            }

            update_option(self::OPT_KEY, $decoded);

            // Meta ggf. aus Datei übernehmen (years/languages/headers optional)
            $meta = $this->get_meta();
            if (isset($decoded['meta']['years']) && is_array($decoded['meta']['years'])) {
                $meta['years']['now'] = intval($decoded['meta']['years']['now'] ?? $meta['years']['now']);
                $meta['years']['old'] = intval($decoded['meta']['years']['old'] ?? $meta['years']['old']);
            }
            if (isset($decoded['languages']) && is_array($decoded['languages'])) {
                $meta['languages'] = array_values(array_unique(array_map('strval', $decoded['languages'])));
                // neue Sprachen standardmäßig aktivieren
                foreach ($meta['languages'] as $l) {
                    if (!isset($meta['languages_enabled'][$l])) $meta['languages_enabled'][$l] = true;
                }
            }
            if (isset($decoded['meta']['headers']) && is_array($decoded['meta']['headers'])) {
                $meta['headers'] = array_merge($meta['headers'], $decoded['meta']['headers']);
            }
            $meta['updated_at'] = current_time('mysql');
            $meta['schema_version'] = $decoded['schema_version'] ?? ($meta['schema_version'] ?? 1);
            $this->save_meta($meta);

            wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'success'=>'1'], admin_url('admin.php'))); exit;
        }

        public function handle_save() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_SAVE);
            $dataset = self::read_dataset();
            if (!is_array($dataset)) $dataset = ['data'=>[]];
            $submitted_data = $_POST['data'] ?? [];
            foreach ($submitted_data as $index => $row) {
                if (isset($dataset['data'][$index])) {
                    $dataset['data'][$index] = [
                        'number'     => sanitize_text_field($row['number']),
                        'row_type'   => (isset($row['is_section']) ? 'section' : 'item'),
                        'lang'       => sanitize_text_field($row['lang']),
                        'text'       => sanitize_textarea_field($row['text']),
                        'url'        => esc_url_raw($row['url'] ?? ''),
                        'class_2024' => sanitize_text_field($row['class_2024']),
                        'loe_2024'   => sanitize_text_field($row['loe_2024']),
                        'class_2019' => sanitize_text_field($row['class_2019']),
                        'loe_2019'   => sanitize_text_field($row['loe_2019']),
                    ];
                }
            }
            $dataset['row_count'] = isset($dataset['data']) ? count($dataset['data']) : 0;
            update_option(self::OPT_KEY, $dataset);
            $meta = $this->get_meta(); $meta['updated_at'] = current_time('mysql'); $this->save_meta($meta);

            // Zur selben Ansicht zurückleiten
            $lang = isset($_POST['edit_lang']) ? sanitize_text_field($_POST['edit_lang']) : '';
            $tmode = isset($_POST['tmode']) && $_POST['tmode'] === '1' ? '1' : '0';
            $compare = isset($_POST['compare_lang']) ? sanitize_text_field($_POST['compare_lang']) : '';

            wp_redirect(add_query_arg(['page' => self::MENU_SLUG, 'tab' => 'edit', 'lang' => $lang, 'tmode' => $tmode, 'compare' => $compare, 'success' => '1'], admin_url('admin.php'))); exit;
        }
        
        public function handle_insert_row() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_INSERT, '_wpnonce');
            $index = isset($_GET['index']) ? intval($_GET['index']) : -1;
            if ($index === -1) wp_die('Index fehlt.');
            $dataset = self::read_dataset();
            array_splice($dataset['data'], $index + 1, 0, [[
                'row_type' => 'item',
                'lang'     => $dataset['data'][$index]['lang'] ?? 'en',
                'url'      => '',
            ]]);
            $dataset['row_count'] = count($dataset['data']);
            update_option(self::OPT_KEY, $dataset);
            $lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : '';
            wp_redirect(add_query_arg(['page' => self::MENU_SLUG, 'tab' => 'edit', 'lang' => $lang, 'success' => '1'], admin_url('admin.php'))); exit;
        }
        
        public function handle_delete_row() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_DELETE, '_wpnonce');
            $index = isset($_GET['index']) ? intval($_GET['index']) : -1;
            if ($index === -1) wp_die('Index fehlt.');
            $dataset = self::read_dataset();
            array_splice($dataset['data'], $index, 1);
            $dataset['row_count'] = count($dataset['data']);
            update_option(self::OPT_KEY, $dataset);
            $lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : '';
            wp_redirect(add_query_arg(['page' => self::MENU_SLUG, 'tab' => 'edit', 'lang' => $lang, 'success' => '1'], admin_url('admin.php'))); exit;
        }

        /** Basis-Einstellungen speichern (Jahre & neue Sprache & Aktiv-Status) */
        public function handle_save_settings() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_SAVE_SET);
            $meta = $this->get_meta();
            $now = isset($_POST['year_now']) ? intval($_POST['year_now']) : $meta['years']['now'];
            $old = isset($_POST['year_old']) ? intval($_POST['year_old']) : $meta['years']['old'];
            $meta['years'] = ['now'=>$now, 'old'=>$old];

            // Aktiv-Status der Sprachen
            $enabled_from_post = array_map('strval', (array)($_POST['enabled_langs'] ?? []));
            $enabled_map = [];
            foreach ($meta['languages'] as $l) {
                $enabled_map[$l] = in_array($l, $enabled_from_post, true);
            }
            // Sicherheits-Fallback: mind. eine Sprache aktiv
            if (!in_array(true, $enabled_map, true) && !empty($meta['languages'])) {
                $enabled_map[$meta['languages'][0]] = true;
            }
            $meta['languages_enabled'] = $enabled_map;

            // Neue Sprache
            $new = trim((string)($_POST['new_lang'] ?? ''));
            if ($new !== '') {
                $new = strtolower($new);
                $meta['languages'] = array_values(array_unique(array_merge($meta['languages'] ?? [], [$new])));
                if (empty($meta['headers'][$new])) {
                    $meta['headers'][$new] = $this->get_default_table_headers($new);
                }
                // Neue Sprache standardmäßig aktivieren
                $meta['languages_enabled'][$new] = true;
            }

            $meta['updated_at'] = current_time('mysql');
            $this->save_meta($meta);
            wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'success'=>'1'], admin_url('admin.php'))); exit;
        }

        /** Tabellenkopf-Einstellungen speichern */
        public function handle_save_headers() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_SAVE_HEADERS);
            $meta = $this->get_meta();
            $incoming = $_POST['headers'] ?? [];
            if (is_array($incoming)) {
                foreach ($incoming as $lang => $vals) {
                    $lang = strtolower(sanitize_text_field($lang));
                    $meta['headers'][$lang] = [
                        'text'  => sanitize_text_field($vals['text']  ?? $this->get_default_table_headers($lang)['text']),
                        'class' => sanitize_text_field($vals['class'] ?? $this->get_default_table_headers($lang)['class']),
                        'loe'   => sanitize_text_field($vals['loe']   ?? $this->get_default_table_headers($lang)['loe']),
                    ];
                }
            }
            $meta['updated_at'] = current_time('mysql');
            $this->save_meta($meta);
            wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'headers', 'success'=>'1'], admin_url('admin.php'))); exit;
        }

        /** CSV-Export pro Sprache (Backend-Download) */
        public function handle_export_csv() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_EXP_CSV);
            $lang = sanitize_text_field($_POST['lang'] ?? '');
            $dataset = self::read_dataset();
            $rowsAll = array_values(array_filter($dataset['data'] ?? [], fn($r)=>isset($r['lang']) && strtolower($r['lang'])===strtolower($lang)));
            $years = $this->get_years();

            $filename = 'ebcp-guidelines-' . $lang . '-' . date('Ymd-His') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['number','lang','text','class_'.$years['now'],'loe_'.$years['now'],'class_'.$years['old'],'loe_'.$years['old'],'url','is_section'], ';');
            foreach ($rowsAll as $r) {
                $text = $this->clean_front_text((string)($r['text'] ?? ''));
                $line = [
                    (string)($r['number'] ?? ''),
                    (string)($r['lang'] ?? ''),
                    $text,
                    (string)($r['class_2024'] ?? ''),
                    (string)($r['loe_2024'] ?? ''),
                    (string)($r['class_2019'] ?? ''),
                    (string)($r['loe_2019'] ?? ''),
                    (string)($r['url'] ?? ''),
                    (($r['row_type'] ?? 'item') === 'section') ? 'true' : 'false',
                ];
                fputcsv($out, $line, ';');
            }
            fclose($out);
            exit;
        }

        /** Einstellungen als JSON exportieren */
        public function handle_export_settings() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_EXP_SET);
            $meta = $this->get_meta();
            $payload = [
                'languages'   => $meta['languages'] ?? [],
                'years'       => $meta['years'] ?? ['now'=>2024,'old'=>2019],
                'headers'     => $meta['headers'] ?? [],
                'exported_at' => current_time('mysql'),
            ];
            $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="ebcp-settings-'.date('Ymd-His').'.json"');
            echo $json; exit;
        }

        /** Einstellungen aus JSON importieren */
        public function handle_import_settings() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_IMP_SET);
            if (empty($_FILES['ebcpdgptm_settings_json']['tmp_name'])) {
                wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'error'=>'settings1'], admin_url('admin.php'))); exit;
            }
            $raw = file_get_contents($_FILES['ebcpdgptm_settings_json']['tmp_name']);
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'error'=>'settings2'], admin_url('admin.php'))); exit;
            }
            $meta = $this->get_meta();
            if (isset($decoded['languages']) && is_array($decoded['languages'])) $meta['languages'] = array_values(array_unique(array_map('strval',$decoded['languages'])));
            if (isset($decoded['years']) && is_array($decoded['years'])) {
                $meta['years']['now'] = intval($decoded['years']['now'] ?? $meta['years']['now']);
                $meta['years']['old'] = intval($decoded['years']['old'] ?? $meta['years']['old']);
            }
            if (isset($decoded['headers']) && is_array($decoded['headers'])) {
                $meta['headers'] = array_merge($meta['headers'], $decoded['headers']);
            }
            // neu hinzugekommene Sprachen aktivieren
            foreach ($meta['languages'] as $l) {
                if (!isset($meta['languages_enabled'][$l])) $meta['languages_enabled'][$l] = true;
            }

            $meta['updated_at'] = current_time('mysql');
            $this->save_meta($meta);
            wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'success'=>'1'], admin_url('admin.php'))); exit;
        }

        /** Backend: CSV Re-Import (Sprache vollständig ersetzen) */
        public function handle_replace_lang_csv() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_REPLACE_CSV);
            $lang = sanitize_text_field($_POST['lang'] ?? '');
            if ($lang === '' || empty($_FILES['ebcpdgptm_csv']['tmp_name'])) {
                wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'error'=>'csvimp1'], admin_url('admin.php'))); exit;
            }
            $csv = file_get_contents($_FILES['ebcpdgptm_csv']['tmp_name']);
            if ($csv === false) {
                wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'error'=>'csvimp2'], admin_url('admin.php'))); exit;
            }
            $newRows = $this->parse_csv_rows($csv, $lang);

            $dataset = self::read_dataset();
            if (!is_array($dataset)) $dataset = ['data'=>[]];

            $dataset['data'] = array_values(array_filter($dataset['data'], fn($r)=>strtolower((string)($r['lang']??'')) !== strtolower($lang)));
            $dataset['data'] = array_merge($dataset['data'], $newRows);
            $dataset['row_count'] = count($dataset['data']);

            $meta = $this->get_meta();
            $meta['languages'] = array_values(array_unique(array_merge($meta['languages'] ?? [], [strtolower($lang)])));
            if (!isset($meta['languages_enabled'][$lang])) $meta['languages_enabled'][$lang] = true; // neue Sprache aktivieren
            $meta['updated_at'] = current_time('mysql');

            update_option(self::OPT_KEY, $dataset);
            $this->save_meta($meta);

            wp_redirect(add_query_arg(['page'=>self::MENU_SLUG, 'tab'=>'import', 'success'=>'1'], admin_url('admin.php'))); exit;
        }

        /** NEU: Daten als JSON exportieren (Import-kompatibel) */
        public function handle_export_json() {
            if (!current_user_can(self::CAP_MANAGE)) wp_die('Keine Berechtigung.');
            check_admin_referer(self::NONCE_EXP_JSON);

            $dataset = self::read_dataset();
            if (!is_array($dataset)) $dataset = ['data' => []];

            $meta = $this->get_meta();

            $payload = $dataset;
            // Languages & Schema
            $payload['languages'] = $meta['languages'] ?? ($dataset['languages'] ?? []);
            $payload['schema_version'] = $dataset['schema_version'] ?? ($meta['schema_version'] ?? 1);

            // Meta-Block anreichern (years, headers)
            $payload['meta'] = $payload['meta'] ?? [];
            $payload['meta']['years'] = $meta['years'] ?? ['now'=>2024,'old'=>2019];
            $payload['meta']['headers'] = $meta['headers'] ?? [];
            $payload['meta']['exported_at'] = current_time('mysql');

            $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="ebcp-guidelines-data-'.date('Ymd-His').'.json"');
            echo $json; exit;
        }

        public function register_rest() {
            register_rest_route(self::REST_NS, self::REST_ROUTE, [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => function (WP_REST_Request $req) {
                    $decoded = self::read_dataset(); if (!$decoded) return new WP_REST_Response(['ok' => false, 'error' => 'no_data'], 404);
                    $lang = sanitize_text_field($req->get_param('lang'));
                    $data = $decoded['data'] ?? [];
                    if ($lang) $data = array_values(array_filter($data, fn($r) => isset($r['lang']) && strtolower($r['lang']) === strtolower($lang)));
                    $meta = $this->get_meta();
                    $headers = $this->get_headers_for_lang($lang ?: ($this->get_languages()[0] ?? 'en'));
                    return new WP_REST_Response([
                        'ok'        => true,
                        'data'      => $data,
                        'languages' => $this->get_languages(), // NEU: nur aktive Sprachen
                        'years'     => $meta['years'] ?? ['now'=>2024,'old'=>2019],
                        'headers'   => $headers,
                    ], 200);
                },
            ]);
        }

        private function get_class_color_name($s) { return match (strtolower(str_replace(' ', '', (string)$s))) {'i' => 'class-i', 'iia' => 'class-iia', 'iib' => 'class-iib', 'iii' => 'class-iii', default => ''}; }
        private function map_locale_to_lang($locale) { $m = strtolower(substr((string)$locale, 0, 2)); return in_array($m, ['en', 'de', 'fr', 'es', 'it', 'pt', 'nl', 'ar',  'pl',  'da',  'bs',  'bg',  'hr',  'et',  'el',  'hu',  'no',  'ro',  'ru',  'sk',  'sl',  'sv',  'tr',  'uk',  'lt',  'cs'], true) ? $m : 'en'; }

        /** Nur für UI-Steuertexte (nicht für Tabellenköpfe!) */
        private function get_i18n(string $lang): array {
            $t = [
                'en' => ['language'=>'Language','class'=>'Class','search'=>'Search','search_placeholder'=>'Text…','all'=>'All','toggle_all_collapse'=>'Collapse all','toggle_all_expand'=>'Expand all','show_sections'=>'Headings','pdf'=>'PDF','no_results'=>'No results.','recommendation'=>'Recommendation','select_row'=>'Select row %s','select_all_visible'=>'Select all (visible)','clear_selection'=>'Clear selection'],
                'de' => ['language'=>'Sprache','class'=>'Klasse','search'=>'Suche','search_placeholder'=>'Text…','all'=>'Alle','toggle_all_collapse'=>'Alle zuklappen','toggle_all_expand'=>'Alle aufklappen','show_sections'=>'Überschriften','pdf'=>'PDF','no_results'=>'Keine Treffer.','recommendation'=>'Empfehlung','select_row'=>'Zeile %s auswählen','select_all_visible'=>'Alle Haken setzen (sichtbar)','clear_selection'=>'Haken entfernen'],
                'fr' => ['language'=>'Langue','class'=>'Classe','search'=>'Recherche','search_placeholder'=>'Texte…','all'=>'Tous','toggle_all_collapse'=>'Tout réduire','toggle_all_expand'=>'Tout développer','show_sections'=>'Titres','pdf'=>'PDF','no_results'=>'Aucun résultat.','recommendation'=>'Recommandation','select_row'=>'Sélectionner la ligne %s','select_all_visible'=>'Tout cocher (visible)','clear_selection'=>'Tout décocher'],
                'es' => ['language'=>'Idioma','class'=>'Clase','search'=>'Buscar','search_placeholder'=>'Texto…','all'=>'Todos','toggle_all_collapse'=>'Contraer todo','toggle_all_expand'=>'Desplegar todo','show_sections'=>'Encabezados','pdf'=>'PDF','no_results'=>'Sin resultados.','recommendation'=>'Recomendación','select_row'=>'Seleccionar la fila %s','select_all_visible'=>'Marcar todo (visible)','clear_selection'=>'Desmarcar'],
                'it' => ['language'=>'Lingua','class'=>'Classe','search'=>'Ricerca','search_placeholder'=>'Testo…','all'=>'Tutti','toggle_all_collapse'=>'Comprimi tutto','toggle_all_expand'=>'Espandi tutto','show_sections'=>'Intestazioni','pdf'=>'PDF','no_results'=>'Nessun risultato.','recommendation'=>'Raccomandazione','select_row'=>'Seleziona la riga %s','select_all_visible'=>'Seleziona tutto (visibile)','clear_selection'=>'Deseleziona'],
                'pt' => ['language'=>'Idioma','class'=>'Classe','search'=>'Pesquisar','search_placeholder'=>'Texto…','all'=>'Todos','toggle_all_collapse'=>'Recolher tudo','toggle_all_expand'=>'Expandir tutto','show_sections'=>'Cabeçalhos','pdf'=>'PDF','no_results'=>'Sem resultados.','recommendation'=>'Recomendação','select_row'=>'Selecionar a linha %s','select_all_visible'=>'Selecionar tudo (visível)','clear_selection'=>'Limpar seleção'],
                'nl' => ['language'=>'Taal','class'=>'Klasse','search'=>'Zoeken','search_placeholder'=>'Tekst…','all'=>'Alle','toggle_all_collapse'=>'Alles inklappen','toggle_all_expand'=>'Alles uitklappen','show_sections'=>'Kopteksten','pdf'=>'PDF','no_results'=>'Geen resultaten.','recommendation'=>'Aanbeveling','select_row'=>'Rij %s selecteren','select_all_visible'=>'Alles aanvinken (zichtbaar)','clear_selection'=>'Selectie wissen'],
                'ar' => ['language'=>'اللغة','class'=>'الفئة','search'=>'بحث','search_placeholder'=>'نص…','all'=>'الكل','toggle_all_collapse'=>'طيّ الكل','toggle_all_expand'=>'توسيع الكل','show_sections'=>'عناوين','pdf'=>'PDF','no_results'=>'لا توجد نتائج.','recommendation'=>'توصية','select_row'=>'تحديد الصف %s','select_all_visible'=>'تحديد الكل (الظاهر)','clear_selection'=>'مسح التحديد'],
'pl' => ['language'=>'Język','class'=>'Klasa','search'=>'Szukaj','search_placeholder'=>'Tekst…','all'=>'Wszystkie','toggle_all_collapse'=>'Zwiń wszystko','toggle_all_expand'=>'Rozwiń wszystko','show_sections'=>'Nagłówki','pdf'=>'PDF','no_results'=>'Brak wyników.','recommendation'=>'Rekomendacja','select_row'=>'Wybierz wiersz %s','select_all_visible'=>'Zaznacz wszystkie (widoczne)','clear_selection'=>'Wyczyść zaznaczenie'],
'da' => ['language'=>'Sprog','class'=>'Klasse','search'=>'Søg','search_placeholder'=>'Tekst…','all'=>'Alle','toggle_all_collapse'=>'Fold alle sammen','toggle_all_expand'=>'Fold alle ud','show_sections'=>'Overskrifter','pdf'=>'PDF','no_results'=>'Ingen resultater.','recommendation'=>'Anbefaling','select_row'=>'Vælg række %s','select_all_visible'=>'Vælg alle (synlige)','clear_selection'=>'Ryd markering'],
'bs' => ['language'=>'Jezik','class'=>'Klasa','search'=>'Pretraga','search_placeholder'=>'Tekst…','all'=>'Sve','toggle_all_collapse'=>'Sažmi sve','toggle_all_expand'=>'Proširi sve','show_sections'=>'Naslovi','pdf'=>'PDF','no_results'=>'Nema rezultata.','recommendation'=>'Preporuka','select_row'=>'Odaberi red %s','select_all_visible'=>'Označi sve (vidljivo)','clear_selection'=>'Očisti izbor'],
'bg' => ['language'=>'Език','class'=>'Клас','search'=>'Търсене','search_placeholder'=>'Текст…','all'=>'Всички','toggle_all_collapse'=>'Свий всички','toggle_all_expand'=>'Разгъни всички','show_sections'=>'Заглавия','pdf'=>'PDF','no_results'=>'Няма резултати.','recommendation'=>'Препоръка','select_row'=>'Изберете ред %s','select_all_visible'=>'Избери всички (видими)','clear_selection'=>'Изчисти избора'],
'hr' => ['language'=>'Jezik','class'=>'Klasa','search'=>'Pretraga','search_placeholder'=>'Tekst…','all'=>'Sve','toggle_all_collapse'=>'Sažmi sve','toggle_all_expand'=>'Proširi sve','show_sections'=>'Naslovi','pdf'=>'PDF','no_results'=>'Nema rezultata.','recommendation'=>'Preporuka','select_row'=>'Odaberi redak %s','select_all_visible'=>'Označi sve (vidljivo)','clear_selection'=>'Očisti odabir'],
'et' => ['language'=>'Keel','class'=>'Klass','search'=>'Otsing','search_placeholder'=>'Tekst…','all'=>'Kõik','toggle_all_collapse'=>'Ahenda kõik','toggle_all_expand'=>'Laienda kõik','show_sections'=>'Pealkirjad','pdf'=>'PDF','no_results'=>'Tulemusi pole.','recommendation'=>'Soovitus','select_row'=>'Vali rida %s','select_all_visible'=>'Vali kõik (nähtavad)','clear_selection'=>'Tühjenda valik'],
'el' => ['language'=>'Γλώσσα','class'=>'Κλάση','search'=>'Αναζήτηση','search_placeholder'=>'Κείμενο…','all'=>'Όλα','toggle_all_collapse'=>'Σύμπτυξη όλων','toggle_all_expand'=>'Ανάπτυξη όλων','show_sections'=>'Επικεφαλίδες','pdf'=>'PDF','no_results'=>'Δεν υπάρχουν αποτελέσματα.','recommendation'=>'Σύσταση','select_row'=>'Επιλέξτε τη γραμμή %s','select_all_visible'=>'Επιλογή όλων (ορατά)','clear_selection'=>'Καθαρισμός επιλογής'],
'hu' => ['language'=>'Nyelv','class'=>'Osztály','search'=>'Keresés','search_placeholder'=>'Szöveg…','all'=>'Összes','toggle_all_collapse'=>'Összes összecsukása','toggle_all_expand'=>'Összes kibontása','show_sections'=>'Címsorok','pdf'=>'PDF','no_results'=>'Nincs találat.','recommendation'=>'Ajánlás','select_row'=>'Sor %s kiválasztása','select_all_visible'=>'Összes kijelölése (láthatóak)','clear_selection'=>'Kijelölés törlése'],
'nb' => ['language'=>'Språk','class'=>'Klasse','search'=>'Søk','search_placeholder'=>'Tekst…','all'=>'Alle','toggle_all_collapse'=>'Fold sammen alle','toggle_all_expand'=>'Fold ut alle','show_sections'=>'Overskrifter','pdf'=>'PDF','no_results'=>'Ingen treff.','recommendation'=>'Anbefaling','select_row'=>'Velg rad %s','select_all_visible'=>'Velg alle (synlige)','clear_selection'=>'Fjern markering'],
'ro' => ['language'=>'Limbă','class'=>'Clasă','search'=>'Căutare','search_placeholder'=>'Text…','all'=>'Toate','toggle_all_collapse'=>'Restrânge toate','toggle_all_expand'=>'Extinde toate','show_sections'=>'Titluri','pdf'=>'PDF','no_results'=>'Niciun rezultat.','recommendation'=>'Recomandare','select_row'=>'Selectează rândul %s','select_all_visible'=>'Selectează toate (vizibile)','clear_selection'=>'Șterge selecția'],
'ru' => ['language'=>'Язык','class'=>'Класс','search'=>'Поиск','search_placeholder'=>'Текст…','all'=>'Все','toggle_all_collapse'=>'Свернуть все','toggle_all_expand'=>'Развернуть все','show_sections'=>'Заголовки','pdf'=>'PDF','no_results'=>'Нет результатов.','recommendation'=>'Рекомендация','select_row'=>'Выбрать строку %s','select_all_visible'=>'Выбрать все (видимые)','clear_selection'=>'Снять выделение'],
'sk' => ['language'=>'Jazyk','class'=>'Trieda','search'=>'Hľadať','search_placeholder'=>'Text…','all'=>'Všetky','toggle_all_collapse'=>'Zbaliť všetko','toggle_all_expand'=>'Rozbaliť všetko','show_sections'=>'Nadpisy','pdf'=>'PDF','no_results'=>'Žiadne výsledky.','recommendation'=>'Odporúčanie','select_row'=>'Vybrať riadok %s','select_all_visible'=>'Vybrať všetky (viditeľné)','clear_selection'=>'Zrušiť výber'],
'sl' => ['language'=>'Jezik','class'=>'Razred','search'=>'Iskanje','search_placeholder'=>'Besedilo…','all'=>'Vse','toggle_all_collapse'=>'Strni vse','toggle_all_expand'=>'Razširi vse','show_sections'=>'Naslovi','pdf'=>'PDF','no_results'=>'Ni rezultatov.','recommendation'=>'Priporočilo','select_row'=>'Izberi vrstico %s','select_all_visible'=>'Izberi vse (vidne)','clear_selection'=>'Počisti izbiro'],
'sv' => ['language'=>'Språk','class'=>'Klass','search'=>'Sök','search_placeholder'=>'Text…','all'=>'Alla','toggle_all_collapse'=>'Fäll ihop alla','toggle_all_expand'=>'Fäll ut alla','show_sections'=>'Rubriker','pdf'=>'PDF','no_results'=>'Inga resultat.','recommendation'=>'Rekommendation','select_row'=>'Välj rad %s','select_all_visible'=>'Markera alla (synliga)','clear_selection'=>'Rensa markering'],
'tr' => ['language'=>'Dil','class'=>'Sınıf','search'=>'Ara','search_placeholder'=>'Metin…','all'=>'Tümü','toggle_all_collapse'=>'Tümünü daralt','toggle_all_expand'=>'Tümünü genişlet','show_sections'=>'Başlıklar','pdf'=>'PDF','no_results'=>'Sonuç yok.','recommendation'=>'Öneri','select_row'=>'Satır %s’i seç','select_all_visible'=>'Tümünü seç (görünenler)','clear_selection'=>'Seçimi temizle'],
'uk' => ['language'=>'Мова','class'=>'Клас','search'=>'Пошук','search_placeholder'=>'Текст…','all'=>'Усі','toggle_all_collapse'=>'Згорнути все','toggle_all_expand'=>'Розгорнути все','show_sections'=>'Заголовки','pdf'=>'PDF','no_results'=>'Немає результатів.','recommendation'=>'Рекомендація','select_row'=>'Вибрати рядок %s','select_all_visible'=>'Вибрати все (видимі)','clear_selection'=>'Очистити вибір'],
'lt' => ['language'=>'Kalba','class'=>'Klasė','search'=>'Paieška','search_placeholder'=>'Tekstas…','all'=>'Visi','toggle_all_collapse'=>'Sutraukti viską','toggle_all_expand'=>'Išskleisti viską','show_sections'=>'Antraštės','pdf'=>'PDF','no_results'=>'Rezultatų nėra.','recommendation'=>'Rekomendacija','select_row'=>'Pasirinkti eilutę %s','select_all_visible'=>'Pasirinkti visus (matomus)','clear_selection'=>'Išvalyti pasirinkimą'],
'cs' => ['language'=>'Jazyk','class'=>'Třída','search'=>'Hledat','search_placeholder'=>'Text…','all'=>'Vše','toggle_all_collapse'=>'Sbalit vše','toggle_all_expand'=>'Rozbalit vše','show_sections'=>'Nadpisy','pdf'=>'PDF','no_results'=>'Žádné výsledky.','recommendation'=>'Doporučení','select_row'=>'Vybrat řádek %s','select_all_visible'=>'Vybrat vše (viditelné)','clear_selection'=>'Zrušit výběr'],
				
            ];
            return $t[$lang] ?? $t['en'];
        }
        
        private function render_rows_html(array $rows, $show2019, $showSections, string $lang) {
            $i18n = $this->get_i18n($lang);
            $h = ''; $section_id_counter = 0; $current_section_id = '';
            foreach ($rows as $r) {
                if (($r['row_type'] ?? 'item') === 'section') {
                    if ($showSections) {
                        $section_id_counter++; $current_section_id = 'section-' . $section_id_counter;
                        $label = trim((string)($r['text'] ?? '')) !== '' ? $r['text'] : ($r['number'] ?? '');
                        $label = $this->clean_front_text($label);
                        $h .= '<tr class="ebcpdgptm-section-row" data-section-id="' . $current_section_id . '">'
                            . '<td class="col-toggle"></td>'
                            . '<td colspan="5">' . esc_html($label) . '</td>'
                            . '<td class="col-check"><input type="checkbox" aria-label="' . esc_attr(sprintf($i18n['select_row'] ?? 'Select row %s', $label)) . '"></td>'
                            . '</tr>';
                    }
                    continue;
                }
                $num_raw = (string)($r['number'] ?? '');
                $textCell = $this->wrap_text_with_url((string)($r['text'] ?? ''), $r['url'] ?? '');
                $h .= '<tr data-number="' . esc_attr($num_raw) . '" data-section-parent-id="' . $current_section_id . '"><td class="col-toggle"></td><td class="col-text">' . $textCell . '</td>';
                $h .= '<td class="col-now col-class ' . esc_attr($this->get_class_color_name($r['class_2024'] ?? '')) . '">' . esc_html($r['class_2024'] ?? '') . '</td><td class="col-now col-loe">' . esc_html($r['loe_2024'] ?? '') . '</td>';
                if ($show2019) {
                    $h .= '<td class="col-old th-2019 col-class ' . esc_attr($this->get_class_color_name($r['class_2019'] ?? '')) . '">' . esc_html($r['class_2019'] ?? '') . '</td><td class="col-old th-2019 col-loe">' . esc_html($r['loe_2019'] ?? '') . '</td>';
                } else {
                    $h .= '<td class="col-old th-2019"></td><td class="col-old th-2019"></td>';
                }
                $h .= '<td class="col-check"><input type="checkbox" aria-label="' . esc_attr(sprintf($i18n['select_row'] ?? 'Select row %s', $num_raw)) . '"></td></tr>';
            } return $h;
        }

        public function shortcode($atts) {
            // Tabellenköpfe ausschließlich aus den Einstellungen (pro Sprache). CSV Ex-/Import nur Backend. PDF-Export mit globaler Überschrift und Abschnittstiteln.
            $atts = shortcode_atts(['lang' => 'auto', 'compare' => 'on'], $atts, 'ebcpdgptm_guidelines');
            $decoded = self::read_dataset(); if (!$decoded) return '<div class="ebcpdgptm-wrap"><em>Keine Daten importiert.</em></div>';

            $all = $decoded['data'] ?? [];
            $years = $this->get_years();
            $ui_langs = $this->get_languages(); // NEU: nur aktive Sprachen

         $lang = $this->detect_request_lang($ui_langs, $atts['lang']);

            $initial_rows = array_values(array_filter($all, fn($r) => isset($r['lang']) && strtolower($r['lang']) === $lang));
            $compare_on = ($atts['compare'] === 'on');

            $headers = $this->get_headers_for_lang($lang);
            $embed_json = wp_json_encode([
                'ok'       => true,
                'languages'=> $ui_langs,
                'data'     => array_values($initial_rows),
                'years'    => $years,
                'headers'  => $headers,
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS);
            $rest_url = get_rest_url(null, self::REST_NS . self::REST_ROUTE);
            $i18n = $this->get_i18n($lang);

            // RTL (Arabisch): Wrapper-Markup erhält Klasse/dir
            $is_rtl = (strpos($lang, 'ar') === 0);

            ob_start();
            ?>
            <div class="ebcpdgptm-wrap <?php echo $compare_on ? '' : 'compare-off'; ?> <?php echo $is_rtl ? 'rtl' : ''; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>" data-rest="<?php echo esc_attr($rest_url); ?>" data-initial-lang="<?php echo esc_attr($lang); ?>" data-year-now="<?php echo esc_attr($years['now']); ?>" data-year-old="<?php echo esc_attr($years['old']); ?>">
                <div class="ebcpdgptm-controls"><div class="ebcpdgptm-row">
                    <label><span><?php echo esc_html($i18n['language']); ?></span>
                        <select class="ebcpdgptm-lang">
                            <?php foreach ($ui_langs as $l_code): ?>
                                <option value="<?php echo esc_attr($l_code); ?>" <?php selected($lang, $l_code); ?>>
                                  <?php switch($l_code){case 'en':echo 'English';break;case 'de':echo 'Deutsch';break;case 'fr':echo'Français';break;case 'es':echo'Español';break;case 'it':echo'Italiano';break;case 'pt':echo'Português';break;case 'nl':echo'Nederlands';break;case 'ar':echo'العربية';break;case 'pl':echo'Polski';break;case 'da':echo'Dansk';break;case 'bs':echo'Bosanski';break;case 'bg':echo'Български';break;case 'hr':echo'Hrvatski';break;case 'et':echo'Eesti';break;case 'el':echo'Ελληνικά';break;case 'hu':echo'Magyar';break;case 'nb':echo'Norsk';break;case 'ro':echo'Română';break;case 'ru':echo'Русский';break;case 'sk':echo'Slovenčina';break;case 'sl':echo'Slovenščina';break;case 'sv':echo'Svenska';break;case 'tr':echo'Türkçe';break;case 'fi':echo 'Suomi';break;case 'uk':echo'Українська';break;case 'lt':echo'Lietuvių';break;case 'cs':echo'Čeština';break;default:echo esc_html($l_code);}?>

                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span><?php echo esc_html($i18n['class']); ?></span><select class="ebcpdgptm-class-filter"><option value="all"><?php echo esc_html($i18n['all']); ?></option><option value="I">I</option><option value="IIa">IIa</option><option value="IIb">IIb</option><option value="III">III</option></select></label>
                    <label><span><?php echo esc_html($i18n['search']); ?></span><input type="search" class="ebcpdgptm-search" placeholder="<?php echo esc_attr($i18n['search_placeholder']); ?>"></label>
                    <div class="ebcpdgptm-toggles">
                        <button type="button" class="button button-small ebcpdgptm-toggle-all"><?php echo esc_html($i18n['toggle_all_collapse']); ?></button>
                        <label class="ebcpdgptm-toggle"><input type="checkbox" class="ebcpdgptm-compare" <?php checked($compare_on); ?>> <span><?php echo intval($years['old']); ?></span></label>
                        <label class="ebcpdgptm-toggle"><input type="checkbox" class="ebcpdgptm-showsections" checked> <span><?php echo esc_html($i18n['show_sections']); ?></span></label>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" id="ebcpdgptm-pdf-export-btn" class="ebcpdgptm-pdf-export-btn"><?php echo esc_html($i18n['pdf']); ?></button>
                        <button type="button" id="ebcpdgptm-select-all-btn" class="button button-secondary"><?php echo esc_html($i18n['select_all_visible']); ?></button>
                        <button type="button" id="ebcpdgptm-clear-selection-btn" class="button button-secondary"><?php echo esc_html($i18n['clear_selection']); ?></button>
                    </div>
                </div></div>

                <div class="ebcpdgptm-table-wrap"><table class="ebcpdgptm-table">
                    <thead>
                        <!-- Kopfzeilen ausschließlich aus den Einstellungen -->
                        <tr class="head-years">
                            <th class="col-toggle" rowspan="2"></th>
                            <th class="col-text" rowspan="2"><?php echo esc_html($headers['text'] ?? ''); ?></th>
                            <th class="col-now group-year-now" colspan="2"><?php echo esc_html($years['now']); ?></th>
                            <th class="col-old th-2019 group-year-old" colspan="2"><?php echo esc_html($years['old']); ?></th>
                            <th class="col-check" rowspan="2">✔</th>
                        </tr>
                        <tr class="head-sub">
                            <th class="col-now col-class-head"><?php echo esc_html($headers['class'] ?? ''); ?></th>
                            <th class="col-now col-loe-head"><?php echo esc_html($headers['loe'] ?? ''); ?></th>
                            <th class="col-old th-2019 col-class-head"><?php echo esc_html($headers['class'] ?? ''); ?></th>
                            <th class="col-old th-2019 col-loe-head"><?php echo esc_html($headers['loe'] ?? ''); ?></th>
                        </tr>
                    </thead>
                    <tbody><?php echo $this->render_rows_html(array_values($initial_rows), $compare_on, true, $lang); ?></tbody>
                </table><div class="ebcpdgptm-empty" style="display:none;"><?php echo esc_html($this->get_i18n($lang)['no_results']); ?></div></div>

                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
                <script type="application/json" class="ebcpdgptm-data"><?php echo $embed_json; ?></script>
                <script>
                (function(){
                    const wrap = document.currentScript.closest('.ebcpdgptm-wrap'); if (!wrap) return;

                    // UI-Texte (nur Controls)
                    const I18N = {
                        en:{language:'Language',class:'Class',search:'Search',search_placeholder:'Text…',all:'All',toggle_all_collapse:'Collapse all',toggle_all_expand:'Expand all',show_sections:'Headings',pdf:'PDF',no_results:'No results.',recommendation:'Recommendation',select_row:'Select row %s',select_all_visible:'Select all (visible)',clear_selection:'Clear selection'},
                        de:{language:'Sprache',class:'Klasse',search:'Suche',search_placeholder:'Text…',all:'Alle',toggle_all_collapse:'Alle zuklappen',toggle_all_expand:'Alle aufklappen',show_sections:'Überschriften',pdf:'PDF',no_results:'Keine Treffer.',recommendation:'Empfehlung',select_row:'Zeile %s auswählen',select_all_visible:'Alle Haken setzen (sichtbar)',clear_selection:'Haken entfernen'},
                        fr:{language:'Langue',class:'Classe',search:'Recherche',search_placeholder:'Texte…',all:'Tous',toggle_all_collapse:'Tout réduire',toggle_all_expand:'Tout développer',show_sections:'Titres',pdf:'PDF',no_results:'Aucun résultat.',recommendation:'Recommandation',select_row:'Sélectionner la ligne %s',select_all_visible:'Tout cocher (visible)',clear_selection:'Tout décocher'},
                        es:{language:'Idioma',class:'Clase',search:'Buscar',search_placeholder:'Texto…',all:'Todos',toggle_all_collapse:'Contraer todo',toggle_all_expand:'Desplegar todo',show_sections:'Encabezados',pdf:'PDF',no_results:'Sin resultados.',recommendation:'Recomendación',select_row:'Seleccionar la fila %s',select_all_visible:'Marcar todo (visible)',clear_selection:'Desmarcar'},
                        it:{language:'Lingua',class:'Classe',search:'Ricerca',search_placeholder:'Testo…',all:'Tutti',toggle_all_collapse:'Comprimi tutto',toggle_all_expand:'Espandi tutto',show_sections:'Intestazioni',pdf:'PDF',no_results:'Nessun risultato.',recommendation:'Raccomandazione',select_row:'Seleziona la riga %s',select_all_visible:'Seleziona tutto (visibile)',clear_selection:'Deseleziona'},
                        pt:{language:'Idioma',class:'Classe',search:'Pesquisar',search_placeholder:'Texto…',all:'Todos',toggle_all_collapse:'Recolher tudo',toggle_all_expand:'Expandir tutto',show_sections:'Cabeçalhos',pdf:'PDF',no_results:'Sem resultados.',recommendation:'Recomendação',select_row:'Selecionar a linha %s',select_all_visible:'Selecionar tudo (visível)',clear_selection:'Limpar seleção'},
                        nl:{language:'Taal',class:'Klasse','search':'Zoeken',search_placeholder:'Tekst…','all':'Alle',toggle_all_collapse:'Alles inklappen',toggle_all_expand:'Alles uitklappen',show_sections:'Kopteksten',pdf:'PDF',no_results:'Geen resultaten.',recommendation:'Aanbeveling',select_row:'Rij %s selecteren',select_all_visible:'Alles aanvinken (zichtbaar)',clear_selection:'Selectie wissen'},
                        ar:{language:'اللغة',class:'الفئة',search:'بحث',search_placeholder:'نص…',all:'الكل',toggle_all_collapse:'طيّ الكل',toggle_all_expand:'توسيع الكل',show_sections:'عناوين',pdf:'PDF',no_results:'لا توجد نتائج.',recommendation:'توصية',select_row:'تحديد الصف %s',select_all_visible:'تحديد الكل (الظاهر)',clear_selection:'مسح التحديد'},

pl:{language:'Język',class:'Klasa',search:'Szukaj',search_placeholder:'Tekst…',all:'Wszystkie',toggle_all_collapse:'Zwiń wszystko',toggle_all_expand:'Rozwiń wszystko',show_sections:'Nagłówki',pdf:'PDF',no_results:'Brak wyników.',recommendation:'Rekomendacja',select_row:'Wybierz wiersz %s',select_all_visible:'Zaznacz wszystkie (widoczne)',clear_selection:'Wyczyść zaznaczenie'},
  da:{language:'Sprog',class:'Klasse',search:'Søg',search_placeholder:'Tekst…',all:'Alle',toggle_all_collapse:'Fold alle sammen',toggle_all_expand:'Fold alle ud',show_sections:'Overskrifter',pdf:'PDF',no_results:'Ingen resultater.',recommendation:'Anbefaling',select_row:'Vælg række %s',select_all_visible:'Vælg alle (synlige)',clear_selection:'Ryd markering'},
  bs:{language:'Jezik',class:'Klasa',search:'Pretraga',search_placeholder:'Tekst…',all:'Sve',toggle_all_collapse:'Sažmi sve',toggle_all_expand:'Proširi sve',show_sections:'Naslovi',pdf:'PDF',no_results:'Nema rezultata.',recommendation:'Preporuka',select_row:'Odaberi red %s',select_all_visible:'Označi sve (vidljivo)',clear_selection:'Očisti izbor'},
  bg:{language:'Език',class:'Клас',search:'Търсене',search_placeholder:'Текст…',all:'Всички',toggle_all_collapse:'Свий всички',toggle_all_expand:'Разгъни всички',show_sections:'Заглавия',pdf:'PDF',no_results:'Няма резултати.',recommendation:'Препоръка',select_row:'Изберете ред %s',select_all_visible:'Изберете всички (видими)',clear_selection:'Изчисти избора'},
  hr:{language:'Jezik',class:'Klasa',search:'Pretraga',search_placeholder:'Tekst…',all:'Sve',toggle_all_collapse:'Sažmi sve',toggle_all_expand:'Proširi sve',show_sections:'Naslovi',pdf:'PDF',no_results:'Nema rezultata.',recommendation:'Preporuka',select_row:'Odaberi redak %s',select_all_visible:'Označi sve (vidljivo)',clear_selection:'Očisti odabir'},
  et:{language:'Keel',class:'Klass',search:'Otsing',search_placeholder:'Tekst…',all:'Kõik',toggle_all_collapse:'Ahenda kõik',toggle_all_expand:'Laienda kõik',show_sections:'Pealkirjad',pdf:'PDF',no_results:'Tulemusi pole.',recommendation:'Soovitus',select_row:'Vali rida %s',select_all_visible:'Vali kõik (nähtavad)',clear_selection:'Tühjenda valik'},
  el:{language:'Γλώσσα',class:'Κλάση',search:'Αναζήτηση',search_placeholder:'Κείμενο…',all:'Όλα',toggle_all_collapse:'Σύμπτυξη όλων',toggle_all_expand:'Ανάπτυξη όλων',show_sections:'Επικεφαλίδες',pdf:'PDF',no_results:'Δεν υπάρχουν αποτελέσματα.',recommendation:'Σύσταση',select_row:'Επιλέξτε τη γραμμή %s',select_all_visible:'Επιλογή όλων (ορατά)',clear_selection:'Καθαρισμός επιλογής'},
  hu:{language:'Nyelv',class:'Osztály',search:'Keresés',search_placeholder:'Szöveg…',all:'Összes',toggle_all_collapse:'Összes összecsukása',toggle_all_expand:'Összes kibontása',show_sections:'Címsorok',pdf:'PDF',no_results:'Nincs találat.',recommendation:'Ajánlás',select_row:'Sor %s kiválasztása',select_all_visible:'Összes kijelölése (láthatóak)',clear_selection:'Kijelölés törlése'},
  nb:{language:'Språk',class:'Klasse',search:'Søk',search_placeholder:'Tekst…',all:'Alle',toggle_all_collapse:'Fold sammen alle',toggle_all_expand:'Fold ut alle',show_sections:'Overskrifter',pdf:'PDF',no_results:'Ingen treff.',recommendation:'Anbefaling',select_row:'Velg rad %s',select_all_visible:'Velg alle (synlige)',clear_selection:'Fjern markering'},
  ro:{language:'Limbă',class:'Clasă',search:'Căutare',search_placeholder:'Text…',all:'Toate',toggle_all_collapse:'Restrânge toate',toggle_all_expand:'Extinde toate',show_sections:'Titluri',pdf:'PDF',no_results:'Niciun rezultat.',recommendation:'Recomandare',select_row:'Selectează rândul %s',select_all_visible:'Selectează toate (vizibile)',clear_selection:'Șterge selecția'},
  ru:{language:'Язык',class:'Класс',search:'Поиск',search_placeholder:'Текст…',all:'Все',toggle_all_collapse:'Свернуть все',toggle_all_expand:'Развернуть все',show_sections:'Заголовки',pdf:'PDF',no_results:'Нет результатов.',recommendation:'Рекомендация',select_row:'Выбрать строку %s',select_all_visible:'Выбрать все (видимые)',clear_selection:'Снять выделение'},
  sk:{language:'Jazyk',class:'Trieda',search:'Hľadať',search_placeholder:'Text…',all:'Všetky',toggle_all_collapse:'Zbaliť všetko',toggle_all_expand:'Rozbaliť všetko',show_sections:'Nadpisy',pdf:'PDF',no_results:'Žiadne výsledky.',recommendation:'Odporúčanie',select_row:'Vybrať riadok %s',select_all_visible:'Vybrať všetky (viditeľné)',clear_selection:'Zrušiť výber'},
  sl:{language:'Jezik',class:'Razred',search:'Iskanje',search_placeholder:'Besedilo…',all:'Vse',toggle_all_collapse:'Strni vse',toggle_all_expand:'Razširi vse',show_sections:'Naslovi',pdf:'PDF',no_results:'Ni rezultatov.',recommendation:'Priporočilo',select_row:'Izberi vrstico %s',select_all_visible:'Izberi vse (vidne)',clear_selection:'Počisti izbiro'},
  sv:{language:'Språk',class:'Klass',search:'Sök',search_placeholder:'Text…',all:'Alla',toggle_all_collapse:'Fäll ihop alla',toggle_all_expand:'Fäll ut alla',show_sections:'Rubriker',pdf:'PDF',no_results:'Inga resultat.',recommendation:'Rekommendation',select_row:'Välj rad %s',select_all_visible:'Markera alla (synliga)',clear_selection:'Rensa markering'},
  tr:{language:'Dil',class:'Sınıf',search:'Ara',search_placeholder:'Metin…',all:'Tümü',toggle_all_collapse:'Tümünü daralt',toggle_all_expand:'Tümünü genişlet',show_sections:'Başlıklar',pdf:'PDF',no_results:'Sonuç yok.',recommendation:'Öneri',select_row:'Satır %s’i seç',select_all_visible:'Tümünü seç (görünenler)',clear_selection:'Seçimi temizle'},
  uk:{language:'Мова',class:'Клас',search:'Пошук',search_placeholder:'Текст…',all:'Усі',toggle_all_collapse:'Згорнути все',toggle_all_expand:'Розгорнути все',show_sections:'Заголовки',pdf:'PDF',no_results:'Немає результатів.',recommendation:'Рекомендація',select_row:'Вибрати рядок %s',select_all_visible:'Вибрати все (видимі)',clear_selection:'Очистити вибір'},
						fi: {
  language:'Kieli',
  class:'Luokka',
  search:'Haku',
  search_placeholder:'Teksti…',
  all:'Kaikki',
  toggle_all_collapse:'Sulje kaikki',
  toggle_all_expand:'Laajenna kaikki',
  show_sections:'Otsikot',
  pdf:'PDF',
  no_results:'Ei tuloksia.',
  recommendation:'Suositus',
  select_row:'Valitse rivi %s',
  select_all_visible:'Valitse kaikki (näkyvät)',
  clear_selection:'Tyhjennä valinnat'
},
  lt:{language:'Kalba',class:'Klasė',search:'Paieška',search_placeholder:'Tekstas…',all:'Visi',toggle_all_collapse:'Sutraukti viską',toggle_all_expand:'Išskleisti viską',show_sections:'Antraštės',pdf:'PDF',no_results:'Rezultatų nėra.',recommendation:'Rekomendacija',select_row:'Pasirinkti eilutę %s',select_all_visible:'Pasirinkti visus (matomus)',clear_selection:'Išvalyti pasirinkimą'},
  cs:{language:'Jazyk',class:'Třída',search:'Hledat',search_placeholder:'Text…',all:'Vše',toggle_all_collapse:'Sbalit vše',toggle_all_expand:'Rozbalit vše',show_sections:'Nadpisy',pdf:'PDF',no_results:'Žádné výsledky.',recommendation:'Doporučení',select_row:'Vybrat řádek %s',select_all_visible:'Vybrat vše (viditelné)',clear_selection:'Zrušit výběr'}
						
                    };

                    const tbody = wrap.querySelector('tbody'),
                          thead = wrap.querySelector('thead'),
                          empty = wrap.querySelector('.ebcpdgptm-empty'),
                          selLang = wrap.querySelector('.ebcpdgptm-lang'),
                          inpSearch = wrap.querySelector('.ebcpdgptm-search'),
                          selClass = wrap.querySelector('.ebcpdgptm-class-filter'),
                          chkCompare = wrap.querySelector('.ebcpdgptm-compare'),
                          chkSections = wrap.querySelector('.ebcpdgptm-showsections'),
                          btnToggleAll = wrap.querySelector('.ebcpdgptm-toggle-all'),
                          btnPdfExport = document.getElementById('ebcpdgptm-pdf-export-btn'),
                          btnSelectAll = document.getElementById('ebcpdgptm-select-all-btn'),
                          btnClearSel = document.getElementById('ebcpdgptm-clear-selection-btn');

                    const labLangSpan = selLang.previousElementSibling,
                          labClassSpan = selClass.previousElementSibling,
                          labSearchSpan = inpSearch.previousElementSibling,
                          showSectionsSpan = chkSections.closest('.ebcpdgptm-toggle').querySelector('span');

                    let rows = [], all_rows_data = [], years = { now: parseInt(wrap.dataset.yearNow,10)||2024, old: parseInt(wrap.dataset.yearOld,10)||2019 }, activeLang = wrap.dataset.initialLang, headers = {text:'',class:'',loe:''};

                    function cleanFrontText(s){ if(!s) return ''; return String(s).replace(/\\"/g, '"').replace(/;/g,''); }

                    try {
                        const parsed = JSON.parse(wrap.querySelector('.ebcpdgptm-data').textContent);
                        all_rows_data = parsed.data || [];
                        rows = all_rows_data;
                        years = parsed.years || years;
                        headers = parsed.headers || headers;
                    } catch(e) { rows = []; }

                    function t(){ return I18N[activeLang] || I18N.en; }

                    function applyI18nAndRTL(lang){
                        activeLang = lang || activeLang;
                        const tt = t();
                        if (labLangSpan) labLangSpan.textContent = tt.language;
                        if (labClassSpan) labClassSpan.textContent = tt.class;
                        if (labSearchSpan) labSearchSpan.textContent = tt.search;
                        if (inpSearch) inpSearch.placeholder = tt.search_placeholder;
                        const allOpt = selClass && selClass.querySelector('option[value="all"]'); if (allOpt) allOpt.textContent = tt.all;
                        if (showSectionsSpan) showSectionsSpan.textContent = tt.show_sections;
                        if (btnPdfExport) btnPdfExport.textContent = tt.pdf;
                        if (btnSelectAll) btnSelectAll.textContent = tt.select_all_visible;
                        if (btnClearSel) btnClearSel.textContent = tt.clear_selection;
                        if (empty) empty.textContent = tt.no_results;
                        const anyCollapsed = tbody.querySelector('.ebcpdgptm-section-row.collapsed');
                        btnToggleAll.textContent = anyCollapsed ? tt.toggle_all_expand : tt.toggle_all_collapse;

                        const isRTL = activeLang && activeLang.toLowerCase().startsWith('ar');
                        wrap.classList.toggle('rtl', isRTL);
                        wrap.setAttribute('dir', isRTL ? 'rtl' : 'ltr');
                    }

                    function setHeaderLabels(hdr){
                        const colText = thead.querySelector('.col-text'),
                              colNowClass = thead.querySelector('.col-class-head'),
                              colNowLoe = thead.querySelector('.col-loe-head'),
                              colOldClass = thead.querySelector('.th-2019.col-class-head'),
                              colOldLoe = thead.querySelector('.th-2019.col-loe-head'),
                              yearNowCell = thead.querySelector('.group-year-now'),
                              yearOldCell = thead.querySelector('.group-year-old');
                        if (colText) colText.textContent = (hdr.text||'');
                        if (colNowClass) colNowClass.textContent = (hdr.class||'');
                        if (colNowLoe) colNowLoe.textContent = (hdr.loe||'');
                        if (colOldClass) colOldClass.textContent = (hdr.class||'');
                        if (colOldLoe) colOldLoe.textContent = (hdr.loe||'');
                        if (yearNowCell) yearNowCell.textContent = years.now;
                        if (yearOldCell) yearOldCell.textContent = years.old;
                    }

                    function render(list){
                        empty.style.display = list.filter(r=>r.row_type !=='section').length ? 'none' : '';
                        let html = '', sectionIdCounter = 0, currentSectionId = '';
                        list.forEach(r => {
                            if (r.row_type === 'section'){
                                if (chkSections.checked) {
                                    sectionIdCounter++; currentSectionId = 'section-' + sectionIdCounter;
                                    const labelRaw = String(r.text||'').trim() !== '' ? r.text : (r.number||'');
                                    const label = cleanFrontText(labelRaw);
                                    const aria = (t().select_row || 'Select row %s').replace('%s', label);
                                    html += `<tr class="ebcpdgptm-section-row" data-section-id="${currentSectionId}">
                                        <td class="col-toggle"></td>
                                        <td colspan="5">${label}</td>
                                        <td class="col-check"><input type="checkbox" aria-label="${aria}"></td>
                                    </tr>`;
                                }
                                return;
                            }
                            const c24 = r.class_2024 || '', c19 = r.class_2019 || '';
                            const aria = (t().select_row || 'Select row %s').replace('%s', (r.number||''));
                            const txt = cleanFrontText(r.text||'');
                            const url = (r.url||'').trim();
                            const linked = url ? `<a href="${url}" target="_blank" rel="noopener">${txt}</a>` : txt;
                            html += `<tr data-number="${r.number||''}" data-section-parent-id="${currentSectionId}">
                                <td class="col-toggle"></td>
                                <td class="col-text">${linked}</td>
                                <td class="col-now col-class class-${(c24||'').toLowerCase().replace(/\s+/g,'')}">${c24}</td>
                                <td class="col-now col-loe">${r.loe_2024||''}</td>
                                <td class="col-old th-2019 col-class class-${(c19||'').toLowerCase().replace(/\s+/g,'')}">${chkCompare.checked?c19:''}</td>
                                <td class="col-old th-2019 col-loe">${chkCompare.checked?(r.loe_2019||''):''}</td>
                                <td class="col-check"><input type="checkbox" aria-label="${aria}"></td>
                            </tr>`;
                        });
                        tbody.innerHTML = html;
                    }

                    function applyFilters(){ 
                        const q = inpSearch.value.trim().toLowerCase(), selectedClass = selClass.value; 
                        let sectionsToExpand = new Set();
                        if(!q) { tbody.querySelectorAll('.ebcpdgptm-section-row').forEach((r,i) => sectionsToExpand.add(`section-${i+1}`)); }
                        const filtered = rows.filter(r => { 
                            const isSection = r.row_type === 'section'; 
                            if (!chkSections.checked && isSection) return false; 
                            let isMatch = true; 
                            if (!isSection && selectedClass !== 'all' && (r.class_2024 || '') !== selectedClass) isMatch = false; 
                            if (q) {
                                const hay = String(r.text||r.number||'').toLowerCase();
                                if (!hay.includes(q)) isMatch = false;
                            }
                            if(q && isMatch && !isSection) { 
                                const idx = rows.indexOf(r);
                                const sectionRow = rows.slice(0, idx).reverse().find(p => p.row_type === 'section');
                                if(sectionRow) { 
                                    const sectionIndex = rows.indexOf(sectionRow);
                                    const countBefore = rows.slice(0, sectionIndex).filter(p=>p.row_type==='section').length;
                                    sectionsToExpand.add('section-' + (countBefore + 1)); 
                                } 
                            } 
                            return isMatch; 
                        });
                        render(filtered);
                        tbody.querySelectorAll('.ebcpdgptm-section-row').forEach(row => { row.classList.toggle('collapsed', !sectionsToExpand.has(row.dataset.sectionId)); });
                        tbody.querySelectorAll('tr[data-section-parent-id]').forEach(row => { const parent = tbody.querySelector(`tr[data-section-id="${row.dataset.sectionParentId}"]`); row.classList.toggle('hidden', parent && parent.classList.contains('collapsed')); });
                    }

                    async function fetchLang(lang){
                        wrap.setAttribute('aria-busy', 'true'); 
                        try { 
                            const res = await fetch(`${wrap.dataset.rest}?lang=${encodeURIComponent(lang)}`); 
                            if (!res.ok) throw new Error(res.status); 
                            const json = await res.json();
                            if (json?.ok) {
                                const all = json.data || [];
                                years = json.years || years;
                                headers = json.headers || headers;
                                rows = all;
                                all_rows_data = all;
                                activeLang = lang;
                                setHeaderLabels(headers);
                                applyI18nAndRTL(activeLang);
                                applyFilters();
                            }
                        } catch(e) { console.error(e); } 
                        finally { wrap.setAttribute('aria-busy', 'false'); }
                    }

                    function generatePdf() {
                        const { jsPDF } = window.jspdf; const doc = new jsPDF('l', 'mm', 'a4');

                        // Globale PDF-Überschrift:
                        const pageWidth = doc.internal.pageSize.getWidth();
                        doc.setFontSize(16);
                        try { doc.setFont(undefined, 'bold'); } catch(e){} // fallback safe
                        doc.text('EBCP-Guidelines', pageWidth / 2, 12, { align: 'center' });

                        const cols = [headers.text, `${years.now} ${headers.class}`, `${years.now} ${headers.loe}`];
                        if (chkCompare.checked) { cols.push(`${years.old} ${headers.class}`, `${years.old} ${headers.loe}`); }
                        const head = [cols];

                        // Welche Nummern exportieren? – wenn Checkboxen gesetzt -> diese, sonst alle sichtbaren
                        const checkedRows = Array.from(tbody.querySelectorAll('tr:not(.hidden) td.col-check input:checked')).map(cb => cb.closest('tr'));
                        let numbers = checkedRows.filter(tr => !tr.classList.contains('ebcpdgptm-section-row')).map(tr => tr.dataset.number).filter(Boolean);
                        if (numbers.length === 0) {
                            numbers = Array.from(tbody.querySelectorAll('tr:not(.hidden)')).filter(tr => !tr.classList.contains('ebcpdgptm-section-row')).map(tr => tr.dataset.number).filter(Boolean);
                        }
                        const selectedSet = new Set(numbers);

                        // Body mit Abschnittsüberschriften (aus "Text" wenn vorhanden, sonst "Nummer")
                        const body = [];
                        let currentSectionTitle = '';
                        let sectionHasItems = false;
                        const PUSH_SECTION = (title) => {
                            const safe = cleanFrontText(title||'');
                            body.push([`__SECTION__:${safe}`]); // Markierung für AutoTable
                        };

                        for (const r of all_rows_data) {
                            if (r.row_type === 'section') {
                                const label = (String(r.text||'').trim() !== '' ? r.text : (r.number||''));
                                currentSectionTitle = label;
                                sectionHasItems = false;
                                continue;
                            }
                            if (selectedSet.size > 0 && !selectedSet.has(r.number)) continue;
                            if (!sectionHasItems) { PUSH_SECTION(currentSectionTitle); sectionHasItems = true; }
                            const row = [ cleanFrontText(r.text||''), (r.class_2024||''), (r.loe_2024||'') ];
                            if (chkCompare.checked) { row.push((r.class_2019||''), (r.loe_2019||'')); }
                            body.push(row);
                        }

                        doc.autoTable({
                            head: head,
                            body: body,
                            margin: { top: 18 }, // Platz für die globale Überschrift
                            didParseCell: (data) => {
                                if (data.section === 'body' && data.cell && data.cell.text && typeof data.cell.text[0] === 'string' && data.cell.text[0].startsWith('__SECTION__:')) {
                                    if (data.column.index === 0) {
                                        data.cell.text[0] = data.cell.text[0].replace('__SECTION__:', '');
                                        data.cell.colSpan = head[0].length;
                                        data.cell.styles.fillColor = [233,236,239];
                                        data.cell.styles.fontStyle = 'bold';
                                        data.cell.styles.halign = 'left';
                                    } else {
                                        data.cell.colSpan = 0;
                                    }
                                }
                            },
                            didDrawCell: (data) => {
                                const classNowIndex = 1, loeNowIndex = 2;
                                if (data.section === 'body') {
                                    if (data.column.index === classNowIndex) {
                                        const c = data.cell.text[0];
                                        if(c==='I') data.cell.styles.fillColor = [212, 237, 218];
                                        else if(c==='IIa') data.cell.styles.fillColor = [255, 243, 205];
                                        else if(c==='IIb') data.cell.styles.fillColor = [255, 224, 130];
                                        else if(c==='III') data.cell.styles.fillColor = [248, 215, 218];
                                    }
                                    if (data.column.index === loeNowIndex) {
                                        data.cell.styles.fillColor = [226, 243, 255];
                                    }
                                    if (chkCompare.checked) {
                                        const classOldIndex = 3, loeOldIndex = 4;
                                        if (data.column.index === classOldIndex) {
                                            const c = data.cell.text[0];
                                            if(c==='I') data.cell.styles.fillColor = [212, 237, 218];
                                            else if(c==='IIa') data.cell.styles.fillColor = [255, 243, 205];
                                            else if(c==='IIb') data.cell.styles.fillColor = [255, 224, 130];
                                            else if(c==='III') data.cell.styles.fillColor = [248, 215, 218];
                                        }
                                        if (data.column.index === loeOldIndex) {
                                            data.cell.styles.fillColor = [226, 243, 255];
                                        }
                                    }
                                }
                            }
                        });
                        doc.save(`ebcp-guidelines-${activeLang}-${new Date().toISOString().slice(0,10)}.pdf`);
                    }

                    // Events
                    const applyAllVisible = (checked) => {
                        tbody.querySelectorAll('tr:not(.hidden):not(.ebcpdgptm-section-row) td.col-check input[type="checkbox"]').forEach(cb => cb.checked = checked);
                    };

                    inpSearch.addEventListener('input', applyFilters); 
                    selClass.addEventListener('change', applyFilters); 
                    chkCompare.addEventListener('change', () => { wrap.classList.toggle('compare-off', !chkCompare.checked); applyFilters(); }); 
                    chkSections.addEventListener('change', applyFilters); 
                    selLang.addEventListener('change', () => fetchLang(selLang.value));
                    btnPdfExport.addEventListener('click', generatePdf);
                    btnSelectAll.addEventListener('click', () => applyAllVisible(true));
                    btnClearSel.addEventListener('click', () => applyAllVisible(false));

                    btnToggleAll.addEventListener('click', () => { 
                        const allSections = tbody.querySelectorAll('.ebcpdgptm-section-row'); 
                        const wasAnyCollapsed = !!tbody.querySelector('.ebcpdgptm-section-row.collapsed'); 
                        allSections.forEach(row => { 
                            row.classList.toggle('collapsed', !wasAnyCollapsed); 
                            const id = row.dataset.sectionId; 
                            tbody.querySelectorAll(`[data-section-parent-id="${id}"]`).forEach(el => el.classList.toggle('hidden', !wasAnyCollapsed)); 
                        }); 
                        btnToggleAll.textContent = wasAnyCollapsed ? t().toggle_all_collapse : t().toggle_all_expand; 
                    });

                    // Checkboxen: Wenn Überschrift angehakt wird -> alle Einträge bis zur nächsten Überschrift anhaken
                    tbody.addEventListener('change', (e) => { 
                        if (e.target.type === 'checkbox') { 
                            const row = e.target.closest('tr'); 
                            if (row && row.classList.contains('ebcpdgptm-section-row')) { 
                                const id = row.dataset.sectionId, isChecked = e.target.checked; 
                                tbody.querySelectorAll(`tr[data-section-parent-id="${id}"] td.col-check input[type="checkbox"]`).forEach(cb => { cb.checked = isChecked; });
                            }
                        }
                    });

                    // Klick auf Überschrift klappt Abschnitt ein/aus
                    tbody.addEventListener('click', e => { 
                        const sectionRow = e.target.closest('.ebcpdgptm-section-row'); 
                        const clickedCheckbox = e.target.matches('input[type="checkbox"]');
                        if(sectionRow && !clickedCheckbox) { 
                            const id = sectionRow.dataset.sectionId; 
                            sectionRow.classList.toggle('collapsed'); 
                            tbody.querySelectorAll(`[data-section-parent-id="${id}"]`).forEach(el => el.classList.toggle('hidden')); 
                            btnToggleAll.textContent = tbody.querySelector('.ebcpdgptm-section-row.collapsed') ? t().toggle_all_expand : t().toggle_all_collapse; 
                        } 
                    });

                    // Initial
                    setHeaderLabels(headers);
                    applyI18nAndRTL(activeLang);
                    applyFilters();
                })();
                </script>
                <style>
                    .ebcpdgptm-wrap { --gap: 1rem; font-family: system-ui, sans-serif; }
                    .ebcpdgptm-controls { margin-bottom: var(--gap); }
                    .ebcpdgptm-row { display: grid; grid-template-columns: 1fr 1fr 2fr auto auto; gap: var(--gap); align-items: end; }
                    .ebcpdgptm-row label { display: grid; gap: 6px; font-size: 14px; }
                    .ebcpdgptm-row input, .ebcpdgptm-row select, .ebcpdgptm-row button { padding: 10px 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; }
                    .ebcpdgptm-row button { cursor: pointer; background: #0073aa; color: white; font-weight: 600; white-space: nowrap; }
                    .ebcpdgptm-toggles { display: flex; gap: 1rem; align-items: center; padding-bottom: 10px; }
                    .ebcpdgptm-toggle { display:flex; gap:8px; align-items:center; white-space:nowrap; }
                    .ebcpdgptm-table-wrap { border: 1px solid #ddd; border-radius: 12px; overflow-x: auto; }
                    .ebcpdgptm-table { width: 100%; border-collapse: collapse; }
                    .ebcpdgptm-table thead th { position: sticky; z-index: 2; background: #f0f0f0; }
                    .ebcpdgptm-table thead .head-years th { top: 0; }
                    .ebcpdgptm-table thead .head-sub th { top: 36px; }
                    .ebcpdgptm-table th { font-weight: 600; text-align: left; padding: 12px 14px; border-bottom: 2px solid #ddd; }
                    .ebcpdgptm-table tbody td { padding: 12px 14px; border-top: 1px solid #f0f0f0; vertical-align: top; }
                    .col-toggle { width: 30px; padding-left: 10px !important; }
                    .col-check { width: 50px; text-align: center; }
                    .col-check input { width: 18px; height: 18px; }
                    .col-text { min-width: 400px; }
                    .col-class, .col-loe { width: 90px; text-align: center; font-weight: 700; }
                    .ebcpdgptm-section-row { cursor: pointer; }
                    .ebcpdgptm-section-row td { background: #e9ecef !important; font-weight: 700; color: #343a40; }
                    .col-toggle::before { content: '−'; font-weight: bold; }
                    .ebcpdgptm-section-row.collapsed .col-toggle::before { content: '+'; }
                    tr.hidden { display: none; }
                    .col-class.class-i { background-color: #d4edda; }
                    .col-class.class-iia { background-color: #fff3cd; }
                    .col-class.class-iib { background-color: #ffe082; }
                    .col-class.class-iii { background-color: #f8d7da; }
                    .col-loe { background-color: #e2f3ff; }
                    .ebcpdgptm-empty { padding: 2rem; text-align:center; color:#666; }
                    .ebcpdgptm-wrap.compare-off .th-2019, .ebcpdgptm-wrap.compare-off .col-old { display: none; }

                    /* RTL (Arabisch) */
                    .ebcpdgptm-wrap.rtl { direction: rtl; }
                    .ebcpdgptm-wrap.rtl .ebcpdgptm-table th, 
                    .ebcpdgptm-wrap.rtl .ebcpdgptm-table td { text-align: right; }
                    .ebcpdgptm-wrap.rtl .col-class, 
                    .ebcpdgptm-wrap.rtl .col-loe, 
                    .ebcpdgptm-wrap.rtl .col-check { text-align: center; }
                    .ebcpdgptm-wrap.rtl .col-toggle { padding-right: 10px !important; padding-left: 0 !important; }

                    /* Tablet */
                    @media (max-width: 1024px) {
                        .ebcpdgptm-row { grid-template-columns: 1fr 1fr; }
                        .ebcpdgptm-toggles, #ebcpdgptm-pdf-export-btn, #ebcpdgptm-select-all-btn, #ebcpdgptm-clear-selection-btn { grid-column: 1 / -1; }
                        .ebcpdgptm-table thead .head-sub th { top: 60px; }
                        .col-text { min-width: 340px; }
                    }

                    /* Smartphone */
                    @media (max-width: 600px) {
                        .ebcpdgptm-row { grid-template-columns: 1fr; }
                        .col-text { min-width: 260px; }
                        .ebcpdgptm-table th, .ebcpdgptm-table td { padding: 10px; }
                        .ebcpdgptm-table thead .head-sub th { top: 64px; }
                    }
                </style>
            </div>
            <?php return ob_get_clean();
        }
    }
}

// Translator-Addon laden
$translator_file = __DIR__ . '/ebcp-translator.php';
if (file_exists($translator_file)) {
    require_once $translator_file;
}

// Initialisierung wird vom Modul-Loader durchgeführt
if (!function_exists('ebcp_guidelines_init')) {
    function ebcp_guidelines_init() {
        EBCPDGPTM_Guidelines_Viewer::init();

        // Translator initialisieren falls vorhanden
        if (function_exists('ebcp_translator_init')) {
            ebcp_translator_init();
        }
    }
}

// Wenn direkt als Plugin geladen (nicht als Modul)
if (!function_exists('dgptm_suite')) {
    ebcp_guidelines_init();
}
