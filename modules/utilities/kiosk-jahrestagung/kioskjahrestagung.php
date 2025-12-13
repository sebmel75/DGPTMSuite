<?php
/**
 * Plugin Name: Bummern Code Scanner (Single File, Inline CSS/JS + Rave + Banner Topbar + Logs)
 * Description: Shortcode-basierter Kiosk-Scanner. Webhook-Validierung, Event-Plausibilisierung, Party-Rave. Inline CSS/JS nur auf Shortcode-Seiten. Admin-Einstellungen, Log-Viewer (inkl. Löschen).
 * Version:     2.2.1
 * Author:      Seb
 * License:     GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

final class Bummern_Code_Scanner {
    const OPT_KEY        = 'bcs_options';
    const LOG_KEY        = 'bcs_logs';
    const NONCE_AJAX     = 'bcs_ajax_nonce';
    const NONCE_SETTINGS = 'bcs_settings_nonce';
    const NONCE_LOGS     = 'bcs_logs_nonce';
    const SHORTCODE      = 'bummern_scanner';
    const MENU_SLUG      = 'bcs_settings';

    private static $instance = null;
    private $shortcode_on_page = false;

    /** Singleton */
    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this,'maybe_set_defaults']);

        add_shortcode(self::SHORTCODE, [$this,'render_shortcode']);
        add_action('wp_footer', [$this,'print_inline_assets']);

        add_action('wp_ajax_bcs_check_code',        [$this,'ajax_check_code']);
        add_action('wp_ajax_nopriv_bcs_check_code',[$this,'ajax_check_code']);
        add_action('wp_ajax_bcs_kiosk_login',       [$this,'ajax_kiosk_login']);
        add_action('wp_ajax_nopriv_bcs_kiosk_login',[$this,'ajax_kiosk_login']);
        add_action('wp_ajax_bcs_admin_test_webhook',[$this,'ajax_admin_test_webhook']);

        add_action('admin_menu', [$this,'admin_menu']);
        add_action('admin_init', [$this,'register_settings']);
        add_action('admin_post_bcs_clear_logs', [$this,'handle_clear_logs']);
    }

    /** Defaults */
    public function maybe_set_defaults() {
        $opt = get_option(self::OPT_KEY, []);
        $defaults = [
            'webhook_url'       => '',
            'success_message'   => 'Willkommen, {{name}}! Zugang OK.',
            'error_message'     => 'Leider ungültig. Bitte prüfen.',
            'expected_event'    => '',
            'error_wrong_event' => 'Falsches Event (erwartet: {{expected}} / erhalten: {{eid}}).',
            'flash_duration'    => 800,
            'success_color'     => '#66ff99',
            'error_color'       => '#ff3366',
            'banner_url'        => '',
            // NEU: Topbar-Optionen
            'banner_width_pct'  => 100,   // 10..100 (% der Seitenbreite)
            'banner_height_px'  => 120,   // 40..600 px
            'custom_heading'    => 'Code-Check',
            'custom_subheading' => 'Bitte Code scannen oder eingeben',
            'party_code'        => '',
            'party_text'        => 'Party-Modus! 🎉',
            'party_duration'    => 4000,
            'kiosk_password'    => '',
            'log_enabled'       => 1,
            'log_max'           => 500,
            'timeout'           => 8,
        ];
        $merged = wp_parse_args($opt, $defaults);
        if ($merged !== $opt) update_option(self::OPT_KEY, $merged, false);

        if (get_option(self::LOG_KEY) === false) {
            add_option(self::LOG_KEY, [], false);
        }
    }

    /** Settings */
    public function register_settings() {
        register_setting(self::MENU_SLUG, self::OPT_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this,'sanitize_options'],
        ]);
    }

    public function sanitize_options($input) {
        $clean = [];
        $clean['webhook_url']       = isset($input['webhook_url']) ? esc_url_raw($input['webhook_url']) : '';
        $clean['success_message']   = isset($input['success_message']) ? wp_kses_post($input['success_message']) : '';
        $clean['error_message']     = isset($input['error_message']) ? wp_kses_post($input['error_message']) : '';
        $clean['expected_event']    = isset($input['expected_event']) ? sanitize_text_field($input['expected_event']) : '';
        $clean['error_wrong_event'] = isset($input['error_wrong_event']) ? wp_kses_post($input['error_wrong_event']) : '';
        $clean['flash_duration']    = isset($input['flash_duration']) ? max(0, intval($input['flash_duration'])) : 800;
        $clean['success_color']     = isset($input['success_color']) ? sanitize_hex_color($input['success_color']) : '#66ff99';
        $clean['error_color']       = isset($input['error_color']) ? sanitize_hex_color($input['error_color']) : '#ff3366';
        $clean['banner_url']        = isset($input['banner_url']) ? esc_url_raw($input['banner_url']) : '';
        $clean['custom_heading']    = isset($input['custom_heading']) ? sanitize_text_field($input['custom_heading']) : '';
        $clean['custom_subheading'] = isset($input['custom_subheading']) ? sanitize_text_field($input['custom_subheading']) : '';

        // NEU: Banner-Größen
        $bw = isset($input['banner_width_pct'])  ? intval($input['banner_width_pct']) : 100;
        $bh = isset($input['banner_height_px'])  ? intval($input['banner_height_px']) : 120;
        $clean['banner_width_pct']  = min(100, max(10, $bw));
        $clean['banner_height_px']  = min(600, max(40,  $bh));

        $clean['party_code']        = isset($input['party_code']) ? sanitize_text_field($input['party_code']) : '';
        $clean['party_text']        = isset($input['party_text']) ? sanitize_text_field($input['party_text']) : '';
        $clean['party_duration']    = isset($input['party_duration']) ? max(0, intval($input['party_duration'])) : 4000;
        $clean['kiosk_password']    = isset($input['kiosk_password']) ? trim($input['kiosk_password']) : '';
        $clean['log_enabled']       = !empty($input['log_enabled']) ? 1 : 0;
        $clean['log_max']           = isset($input['log_max']) ? max(10, intval($input['log_max'])) : 500;
        $clean['timeout']           = isset($input['timeout']) ? max(3, intval($input['timeout'])) : 8;
        return $clean;
    }

    /** Admin menu */
    public function admin_menu() {
        add_options_page(
            'Bummern Code Scanner',
            'Bummern Code Scanner',
            'manage_options',
            self::MENU_SLUG,
            [$this,'render_admin_page']
        );
    }

    /** Admin page renderer */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;
        $opt = get_option(self::OPT_KEY, []);
        $logs = get_option(self::LOG_KEY, []);
        ?>
        <div class="wrap">
            <h1>Bummern Code Scanner</h1>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg(['tab'=>'settings'])); ?>" class="nav-tab <?php echo (!isset($_GET['tab']) || $_GET['tab']==='settings')?'nav-tab-active':''; ?>">Einstellungen</a>
                <a href="<?php echo esc_url(add_query_arg(['tab'=>'logs'])); ?>" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab']==='logs')?'nav-tab-active':''; ?>">Logs</a>
                <a href="<?php echo esc_url(add_query_arg(['tab'=>'test'])); ?>" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab']==='test')?'nav-tab-active':''; ?>">Webhook-Test</a>
            </h2>
            <?php
            $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
            if ($tab === 'logs') {
                $this->render_logs_tab($logs);
            } elseif ($tab === 'test') {
                $this->render_test_tab();
            } else {
                $this->render_settings_tab($opt);
            }
            ?>
        </div>
        <?php
    }

    private function render_settings_tab($opt) {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields(self::MENU_SLUG); ?>
            <?php wp_nonce_field(self::NONCE_SETTINGS, self::NONCE_SETTINGS); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><label for="webhook_url">Webhook-URL</label></th>
                    <td><input name="<?php echo self::OPT_KEY; ?>[webhook_url]" type="url" id="webhook_url" class="regular-text" value="<?php echo esc_attr($opt['webhook_url']??''); ?>" placeholder="https://example.org/check" />
                    <p class="description">Es wird <code>?code=XYZ</code> (bzw. <code>&code=</code>) angehängt.</p></td></tr>

                <tr><th scope="row"><label for="expected_event">Erwartete Event-ID</label></th>
                    <td><input name="<?php echo self::OPT_KEY; ?>[expected_event]" type="text" id="expected_event" class="regular-text" value="<?php echo esc_attr($opt['expected_event']??''); ?>" /></td></tr>

                <tr><th scope="row"><label for="success_message">Erfolgsmeldung</label></th>
                    <td><textarea name="<?php echo self::OPT_KEY; ?>[success_message]" id="success_message" class="large-text" rows="3"><?php echo esc_textarea($opt['success_message']??''); ?></textarea>
                    <p class="description">Platzhalter wie <code>{{name}}</code>, <code>{{email}}</code> und alle geflachten Felder aus dem Webhook-JSON (z. B. <code>{{Teilnehmer_name}}</code>, <code>{{Veranstaltungen_name}}</code>).</p></td></tr>

                <tr><th scope="row"><label for="error_message">Fehlermeldung</label></th>
                    <td><textarea name="<?php echo self::OPT_KEY; ?>[error_message]" id="error_message" class="large-text" rows="2"><?php echo esc_textarea($opt['error_message']??''); ?></textarea></td></tr>

                <tr><th scope="row"><label for="error_wrong_event">Fehlertext (falsches Event)</label></th>
                    <td><textarea name="<?php echo self::OPT_KEY; ?>[error_wrong_event]" id="error_wrong_event" class="large-text" rows="2"><?php echo esc_textarea($opt['error_wrong_event']??''); ?></textarea></td></tr>

                <tr><th scope="row">Farben & Dauer</th>
                    <td>
                        Erfolgsfarbe: <input type="text" name="<?php echo self::OPT_KEY; ?>[success_color]" value="<?php echo esc_attr($opt['success_color']??'#66ff99'); ?>" class="regular-text" style="max-width:120px" />
                        &nbsp;Fehlerfarbe: <input type="text" name="<?php echo self::OPT_KEY; ?>[error_color]" value="<?php echo esc_attr($opt['error_color']??'#ff3366'); ?>" class="regular-text" style="max-width:120px" />
                        &nbsp;Dauer (ms): <input type="number" name="<?php echo self::OPT_KEY; ?>[flash_duration]" value="<?php echo esc_attr($opt['flash_duration']??800); ?>" min="0" step="100" style="max-width:120px" />
                    </td></tr>

                <tr><th scope="row">Texte & Banner</th>
                    <td>
                        H2: <input type="text" name="<?php echo self::OPT_KEY; ?>[custom_heading]" value="<?php echo esc_attr($opt['custom_heading']??''); ?>" class="regular-text" />
                        <br/>H4: <input type="text" name="<?php echo self::OPT_KEY; ?>[custom_subheading]" value="<?php echo esc_attr($opt['custom_subheading']??''); ?>" class="regular-text" />
                        <br/>Banner-URL: <input type="url" name="<?php echo self::OPT_KEY; ?>[banner_url]" value="<?php echo esc_attr($opt['banner_url']??''); ?>" class="regular-text" />
                        <br/>Banner-Breite (%): <input type="number" name="<?php echo self::OPT_KEY; ?>[banner_width_pct]" value="<?php echo esc_attr($opt['banner_width_pct']??100); ?>" min="10" max="100" style="max-width:120px" />
                        &nbsp;Banner-Höhe (px): <input type="number" name="<?php echo self::OPT_KEY; ?>[banner_height_px]" value="<?php echo esc_attr($opt['banner_height_px']??120); ?>" min="40" max="600" style="max-width:120px" />
                        <p class="description">Banner wird als Top-Leiste oben fix angezeigt. Standard: 100 % Breite / 120 px Höhe.</p>
                    </td></tr>

                <tr><th scope="row">Party-Modus</th>
                    <td>
                        Party-Code: <input type="text" name="<?php echo self::OPT_KEY; ?>[party_code]" value="<?php echo esc_attr($opt['party_code']??''); ?>" class="regular-text" />
                        &nbsp;Text: <input type="text" name="<?php echo self::OPT_KEY; ?>[party_text]" value="<?php echo esc_attr($opt['party_text']??''); ?>" class="regular-text" />
                        &nbsp;Dauer (ms): <input type="number" name="<?php echo self::OPT_KEY; ?>[party_duration]" value="<?php echo esc_attr($opt['party_duration']??4000); ?>" min="0" step="100" style="max-width:120px" />
                        <p class="description">Beim Party-Code startet wildes Farbgeflacker (Rave) und der Webhook wird trotzdem abgefragt.</p>
                    </td></tr>

                <tr><th scope="row">Kiosk</th>
                    <td>
                        Kiosk-Passwort (optional): <input type="text" name="<?php echo self::OPT_KEY; ?>[kiosk_password]" value="<?php echo esc_attr($opt['kiosk_password']??''); ?>" class="regular-text" />
                    </td></tr>

                <tr><th scope="row">Webhook</th>
                    <td>
                        Timeout (Sek.): <input type="number" name="<?php echo self::OPT_KEY; ?>[timeout]" value="<?php echo esc_attr($opt['timeout']??8); ?>" min="3" max="60" style="max-width:100px" />
                    </td></tr>

                <tr><th scope="row">Logging</th>
                    <td>
                        <label><input type="checkbox" name="<?php echo self::OPT_KEY; ?>[log_enabled]" value="1" <?php checked(!empty($opt['log_enabled'])); ?> /> Logging aktivieren</label>
                        &nbsp;Max. Einträge: <input type="number" name="<?php echo self::OPT_KEY; ?>[log_max]" value="<?php echo esc_attr($opt['log_max']??500); ?>" min="10" max="5000" style="max-width:120px" />
                        <p class="description">Speichert Hash des Codes, Webhook-Status, Event-ID, Meldungen, HTTP-Status und Client-IP.</p>
                    </td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <p>Shortcode: <code>[<?php echo esc_html(self::SHORTCODE); ?>]</code> oder <code>[<?php echo esc_html(self::SHORTCODE); ?> main_id="EVENT123"]</code></p>
        <?php
    }

    private function render_logs_tab($logs) {
        ?>
        <h2>Logs</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:12px;">
            <?php wp_nonce_field(self::NONCE_LOGS, self::NONCE_LOGS); ?>
            <input type="hidden" name="action" value="bcs_clear_logs">
            <?php submit_button('Logs löschen', 'delete', 'submit', false); ?>
        </form>
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Zeit</th>
                <th>IP</th>
                <th>Code (hash)</th>
                <th>Status</th>
                <th>EID (exp / got)</th>
                <th>HTTP</th>
                <th>Message</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7">Keine Einträge</td></tr>
            <?php else:
                $logs = array_reverse($logs);
                foreach ($logs as $row):
                    ?>
                    <tr>
                        <td><?php echo esc_html($row['time'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['ip'] ?? ''); ?></td>
                        <td><code><?php echo esc_html($row['code_hash'] ?? ''); ?></code></td>
                        <td><?php echo esc_html($row['status'] ?? ''); ?></td>
                        <td><?php echo esc_html(($row['expected']??'').' / '.($row['eid']??'')); ?></td>
                        <td><?php echo esc_html($row['http'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['msg'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_test_tab() {
        $ajax_nonce = wp_create_nonce(self::NONCE_AJAX);
        ?>
        <h2>Webhook-Test</h2>
        <p>Gibt den rohen JSON-Output (ggf. normalisiert) zurück.</p>
        <input type="text" id="bcs-test-code" class="regular-text" placeholder="Testcode">
        <button class="button" id="bcs-test-btn">Testen</button>
        <pre id="bcs-test-out" style="background:#111;color:#0f0;padding:12px;white-space:pre-wrap;max-width:900px;overflow:auto;"></pre>
        <script>
        (function(){
          const btn=document.getElementById('bcs-test-btn');
          const out=document.getElementById('bcs-test-out');
          const code=document.getElementById('bcs-test-code');
          btn.addEventListener('click', function(){
            out.textContent='...';
            const fd=new FormData();
            fd.append('action','bcs_admin_test_webhook');
            fd.append('security','<?php echo esc_js($ajax_nonce); ?>');
            fd.append('code', code.value||'');
            fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'})
              .then(r=>r.json()).then(j=>{
                out.textContent = JSON.stringify(j,null,2);
              }).catch(e=>{
                out.textContent = 'Fehler: '+(e&&e.message?e.message:e);
              });
          });
        })();
        </script>
        <?php
    }

    /** Logs löschen */
    public function handle_clear_logs() {
        if (!current_user_can('manage_options')) wp_die('No perms', 403);
        check_admin_referer(self::NONCE_LOGS, self::NONCE_LOGS);
        update_option(self::LOG_KEY, [], false);
        wp_redirect(add_query_arg(['page'=>self::MENU_SLUG,'tab'=>'logs','cleared'=>'1'], admin_url('options-general.php')));
        exit;
    }

    /** Logging Helper */
    private function add_log($entry) {
        $opt = get_option(self::OPT_KEY, []);
        if (empty($opt['log_enabled'])) return;
        $logs = get_option(self::LOG_KEY, []);
        $logs[] = $entry;
        $max = max(10, intval($opt['log_max'] ?? 500));
        if (count($logs) > $max) {
            $logs = array_slice($logs, -$max);
        }
        update_option(self::LOG_KEY, $logs, false);
    }

    /** Shortcode */
    public function render_shortcode($atts) {
    $this->shortcode_on_page = true;

    $opt = get_option(self::OPT_KEY, []);
    $atts = shortcode_atts([
        'main_id' => $opt['expected_event'] ?? '',
    ], $atts, self::SHORTCODE);
    $expected_event = sanitize_text_field($atts['main_id']);

    // Kiosk: Login nötig?
    $kiosk_required = !empty($opt['kiosk_password']);
    $kiosk_valid = false;
    if ($kiosk_required) {
        $cookie = isset($_COOKIE['bcs_kiosk_token']) ? sanitize_text_field($_COOKIE['bcs_kiosk_token']) : '';
        $kiosk_valid = (!empty($cookie) && $cookie === md5($opt['kiosk_password']));
    }

    ob_start();

    // Inline CSS (mit Top-Offset ~100px)
    ?>
    <style>
    :root{
      --bcs-top-offset: 100px; /* Banner ~100px vom oberen Bildschirmrand abrücken */
      --bcs-banner-h: <?php echo intval($opt['banner_height_px'] ?? 120); ?>px;
      --bcs-banner-w: <?php echo intval($opt['banner_width_pct'] ?? 100); ?>%;
    }
    .bcs-topbar{position:fixed; top:var(--bcs-top-offset); left:0; right:0; z-index:9999; background:#fff; border-bottom:1px solid rgba(0,0,0,.08)}
    .bcs-topbar-inner{width:var(--bcs-banner-w); margin:0 auto}
    .bcs-topbar img{display:block; width:100%; height:var(--bcs-banner-h); object-fit:contain}

    .bcs-wrap{
      min-height:70vh; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:12px;
      padding:24px; box-sizing:border-box;
      /* Standard ohne Banner: kein extra Padding oben */
    }
    /* Nur wenn Banner vorhanden: zusätzlichen Platz einrechnen */
    .bcs-has-banner .bcs-wrap{
      padding-top: calc(var(--bcs-banner-h) + 12px + var(--bcs-top-offset));
    }

    .bcs-card{max-width:760px; width:100%; text-align:center; border-radius:16px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,.08); background:#fff}
    .bcs-h2{font-size:1.8rem; margin:.2rem 0 .4rem 0}
    .bcs-h4{font-size:1rem; margin:0 0 1rem 0; color:#555}
    .bcs-input{font-size:2rem; letter-spacing:.08em; text-align:center; width:100%; padding:.6em 1em; border-radius:12px; border:1px solid #ccc; outline:none}
    .bcs-input:focus{border-color:#888; box-shadow:0 0 0 4px rgba(0,0,0,.06)}
    .bcs-msg{min-height:2.2em; margin-top:12px; font-size:1.2rem}
    .bcs-kiosk{max-width:420px; margin:24px auto; text-align:center}
    .bcs-kiosk input{font-size:1.2rem; padding:.6em 1em; border-radius:10px; border:1px solid #ccc; width:100%}
    .bcs-kiosk button{margin-top:10px}
    .bcs-party{animation:bcsPulse .8s infinite alternate}
    @keyframes bcsPulse{from{transform:scale(1)}to{transform:scale(1.01)}}
    </style>
    <?php

    // Top-Banner (fix, mit ~100px Abstand nach oben)
    $has_banner = !empty($opt['banner_url']);
    if ($has_banner) {
        printf(
            '<div class="bcs-topbar"><div class="bcs-topbar-inner"><img src="%s" alt="Banner" id="bcs-top-banner"/></div></div>',
            esc_url($opt['banner_url'])
        );
    }

    echo '<div class="bcs-wrap" id="bcs-root" data-expected="'.esc_attr($expected_event).'">';
    echo '<div class="bcs-card" id="bcs-card">';

    if (!empty($opt['custom_heading'])) {
        printf('<h2 class="bcs-h2" id="bcs-heading-main">%s</h2>', esc_html($opt['custom_heading']));
    }
    if (!empty($opt['custom_subheading'])) {
        printf('<h4 class="bcs-h4" id="bcs-subheading">%s</h4>', esc_html($opt['custom_subheading']));
    }

    // Kiosk-Login?
    if ($kiosk_required && !$kiosk_valid) {
        $nonce = wp_create_nonce(self::NONCE_AJAX);
        ?>
        <div class="bcs-kiosk">
            <input type="password" id="bcs-kiosk-pass" placeholder="Kiosk-Passwort">
            <button class="button button-primary" id="bcs-kiosk-btn">Anmelden</button>
            <div class="bcs-msg" id="bcs-kiosk-msg"></div>
        </div>
        <script>
        (function(){
          const btn = document.getElementById('bcs-kiosk-btn');
          const pass= document.getElementById('bcs-kiosk-pass');
          const msg = document.getElementById('bcs-kiosk-msg');
          btn.addEventListener('click', function(){
            const fd=new FormData();
            fd.append('action','bcs_kiosk_login');
            fd.append('security','<?php echo esc_js($nonce); ?>');
            fd.append('password', pass.value||'');
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {method:'POST', body:fd, credentials:'same-origin'})
              .then(r=>r.json()).then(j=>{
                if(j && j.ok){ location.reload(); }
                else{ msg.textContent = (j && j.msg) ? j.msg : 'Fehler'; }
              }).catch(e=>{ msg.textContent='Netzwerkfehler';});
          });
        })();
        </script>
        <?php
        echo '</div></div>'; // card/wrap
        return ob_get_clean();
    }

    // Eingabe UI
    printf('<input type="text" id="bcs-input" class="bcs-input" maxlength="120" autocomplete="off" placeholder="%s" />',
        esc_attr('Code scannen…')
    );
    echo '<div class="bcs-msg" id="bcs-msg" aria-live="polite"></div>';
    echo '</div></div>'; // card/wrap

    // Inline JS
    $ajax_nonce = wp_create_nonce(self::NONCE_AJAX);
    $cfg = [
        'ajax'          => admin_url('admin-ajax.php'),
        'nonce'         => $ajax_nonce,
        'flashDuration' => intval($opt['flash_duration'] ?? 800),
        'successColor'  => $opt['success_color'] ?? '#66ff99',
        'errorColor'    => $opt['error_color'] ?? '#ff3366',
        'expected'      => $expected_event,
        'partyCode'     => $opt['party_code'] ?? '',
        'partyText'     => $opt['party_text'] ?? 'Party!',
        'partyDuration' => intval($opt['party_duration'] ?? 4000),
        'bannerH'       => intval($opt['banner_height_px'] ?? 120),
        'bannerW'       => intval($opt['banner_width_pct'] ?? 100),
        'hasBanner'     => $has_banner ? 1 : 0
    ];
    ?>
    <script>
    (function(){
      const cfg = <?php echo wp_json_encode($cfg); ?>;
      const root = document.getElementById('bcs-root');
      const card = document.getElementById('bcs-card');
      const input= document.getElementById('bcs-input');
      const msg  = document.getElementById('bcs-msg');
      const topImg = document.getElementById('bcs-top-banner');

      // Banner-Variablen setzen + Body-Klasse nur wenn Banner vorhanden
      (function(){
        if (cfg.hasBanner) {
          document.body.classList.add('bcs-has-banner');
        }
        document.documentElement.style.setProperty('--bcs-banner-h', (cfg.bannerH||120) + 'px');
        document.documentElement.style.setProperty('--bcs-banner-w', (cfg.bannerW||100) + '%');
        document.documentElement.style.setProperty('--bcs-top-offset', '100px'); // ~100px Abstand zum oberen Rand
        if (topImg) {
          const upd = ()=> {
            // Höhe ggf. an tatsächliche Bildhöhe anpassen (nur nach oben begrenzen, damit Layout stabil)
            const hh = Math.max(60, cfg.bannerH||topImg.clientHeight||120);
            document.documentElement.style.setProperty('--bcs-banner-h', hh + 'px');
          };
          if (topImg.complete) upd(); else topImg.addEventListener('load', upd);
          window.addEventListener('resize', upd);
        }
      })();

      // RAVE: wildes Farbgeflacker (auch bei Party-Code, Webhook läuft trotzdem)
      let raveTimer = null, raveInterval = null, raveActive = false;
      const raveColors = ['#ff0040','#00e5ff','#ffe600','#7cff00','#ff7b00','#9c00ff','#ff2fd9','#00ff9f','#ffd1dc','#00ff3c','#caff00','#ff006e','#00f5d4'];
      function startRave(duration){
        if (raveActive) return;
        raveActive = true;
        card.classList.add('bcs-party');
        const origBg = document.body.style.backgroundColor;
        const origShadow = card.style.boxShadow;
        let i = 0;
        raveInterval = setInterval(()=>{
          const col = raveColors[i++ % raveColors.length];
          document.body.style.backgroundColor = col;
          card.style.boxShadow = '0 0 0 6px '+col+'22, 0 8px 40px '+col+'55';
        }, 70);
        raveTimer = setTimeout(()=>{ stopRave(origBg, origShadow); }, Math.max(500, duration||1500));
      }
      function stopRave(origBg, origShadow){
        clearInterval(raveInterval); raveInterval=null;
        clearTimeout(raveTimer); raveTimer=null;
        document.body.style.backgroundColor = origBg || '';
        card.style.boxShadow = origShadow || '';
        card.classList.remove('bcs-party');
        raveActive = false;
      }

      function flash(bg, duration){
        if (raveActive) return; // während Rave keine Standard-Blitze
        const orig = document.body.style.backgroundColor;
        document.body.style.transition='background-color 120ms ease';
        document.body.style.backgroundColor = bg;
        setTimeout(()=>{ document.body.style.backgroundColor = ''; }, Math.max(120, duration));
      }

      function handleEnter(){
        const code = (input.value||'').trim();
        if(!code) return;

        // Party-Code: Rave starten, aber trotzdem Webhook abfragen
        const party = (cfg.partyCode && code === cfg.partyCode);
        if (party) {
          msg.textContent = cfg.partyText||'Party!';
          startRave(cfg.partyDuration||1500);
        } else {
          msg.textContent = 'Prüfe…';
        }

        const fd = new FormData();
        fd.append('action','bcs_check_code');
        fd.append('security', cfg.nonce);
        fd.append('code', code);
        fd.append('expected', cfg.expected);

        fetch(cfg.ajax, { method:'POST', body:fd, credentials:'same-origin' })
          .then(r=>r.json()).then(j=>{
            if(!j){ msg.textContent='Fehler'; return; }
            if(!raveActive){
              if(j.ok){ flash(cfg.successColor, cfg.flashDuration); }
              else{     flash(cfg.errorColor, cfg.flashDuration); }
            }
            msg.innerHTML = j.html || (j.msg ? j.msg : '');
          })
          .catch(e=>{
            if(!raveActive) flash(cfg.errorColor, cfg.flashDuration);
            msg.textContent = 'Netzwerkfehler';
          })
          .finally(()=>{ input.value=''; input.focus(); });
      }

      input.addEventListener('keydown', function(ev){
        if(ev.key === 'Enter'){ handleEnter(); }
      });
      setTimeout(()=>{ input && input.focus && input.focus(); }, 0);
    })();
    </script>
    <?php

    return ob_get_clean();
}

    /** Inline Assets nur falls Shortcode auf Seite (CSS/JS direkt in render_shortcode()) */
    public function print_inline_assets() {}

    /** AJAX: Kiosk Login */
    public function ajax_kiosk_login() {
        check_ajax_referer(self::NONCE_AJAX, 'security');
        $opt = get_option(self::OPT_KEY, []);
        $pass = isset($_POST['password']) ? (string) $_POST['password'] : '';
        $ok = (!empty($opt['kiosk_password']) && hash_equals($opt['kiosk_password'], $pass));
        if ($ok) {
            $expires = time() + 60*60*24*30;
            $path = '/';
            $domain = '';
            $secure = is_ssl();
            $httponly = true;
            
            // Für moderne PHP-Versionen (7.3+) mit SameSite-Support
            if (PHP_VERSION_ID >= 70300) {
                setcookie('bcs_kiosk_token', md5($opt['kiosk_password']), [
                    'expires'  => $expires,
                    'path'     => $path,
                    'domain'   => $domain,
                    'secure'   => $secure,
                    'httponly' => $httponly,
                    'samesite' => 'Lax'  // 'Lax' ist kompatibler als 'None'
                ]);
            } else {
                // Fallback für ältere PHP-Versionen
                setcookie('bcs_kiosk_token', md5($opt['kiosk_password']), $expires, $path, $domain, $secure, $httponly);
            }
        }
        wp_send_json(['ok'=>$ok, 'msg'=>$ok?'OK':'Passwort falsch']);
    }

    /** AJAX: Webhook Test im Backend */
    public function ajax_admin_test_webhook() {
        if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'No perms'], 403);
        check_ajax_referer(self::NONCE_AJAX, 'security');
        $opt = get_option(self::OPT_KEY, []);
        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $res = $this->call_webhook($opt['webhook_url'] ?? '', $code, intval($opt['timeout'] ?? 8));
        // return raw result (for debug)
        wp_send_json($res);
    }

    /** AJAX: Code prüfen */
    public function ajax_check_code() {
        check_ajax_referer(self::NONCE_AJAX, 'security');
        $opt = get_option(self::OPT_KEY, []);

        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $expected = isset($_POST['expected']) ? sanitize_text_field(wp_unslash($_POST['expected'])) : ($opt['expected_event']??'');

        $resp = $this->call_webhook($opt['webhook_url'] ?? '', $code, intval($opt['timeout'] ?? 8));
        $http = $resp['http'] ?? null;

        // Payload normalisieren (unterstützt Zoho-formatiertes JSON)
        $data = $this->normalize_webhook_payload($resp['json'] ?? []);

        $eid = isset($data['eid']) ? (string) $data['eid'] : '';
        $crm = isset($data['crm_message']) ? (string)$data['crm_message'] : '';
        $m   = isset($data['message']) ? (string)$data['message'] : '';
        $stat= isset($data['status']) ? (string)$data['status'] : '';

        $template_success = $opt['success_message'] ?? 'OK';
        $template_error   = $opt['error_message'] ?? 'Fehler';
        $tpl_wrong_event  = $opt['error_wrong_event'] ?? 'Falsches Event';

        $status_txt = 'error';
        if (!empty($expected) && $eid !== '' && strval($eid) !== strval($expected)) {
            $msg = $this->fill_template($tpl_wrong_event, array_merge($data, ['expected'=>$expected, 'eid'=>$eid]));
            $status_txt = 'error';
        } else {
            if ($stat === 'ok') {
                $msg = $this->fill_template($template_success, $data);
                $status_txt = 'success';
            } else {
                $msg = $crm !== '' ? $crm : ($m !== '' ? $m : $this->fill_template($template_error, $data));
                $status_txt = 'error';
            }
        }

        $this->add_log([
            'time'      => current_time('mysql'),
            'ip'        => $this->get_client_ip(),
            'code_hash' => wp_hash($code),
            'status'    => $status_txt,
            'eid'       => $eid,
            'expected'  => $expected,
            'http'      => $http,
            'msg'       => wp_strip_all_tags($msg),
        ]);

        wp_send_json([
            'ok'   => ($status_txt === 'success'),
            'html' => wp_kses_post($msg),
        ]);
    }

    /** Webhook-Aufruf (GET ?code=) */
    private function call_webhook($base_url, $code, $timeout=8) {
        $out = ['http'=>null,'json'=>null,'raw'=>null];
        $base_url = trim((string)$base_url);
        if (empty($base_url)) {
            $out['json'] = ['status'=>'error','message'=>'Webhook nicht konfiguriert'];
            return $out;
        }
        $sep = (strpos($base_url,'?')!==false) ? '&' : '?';
        $url = $base_url . $sep . 'code=' . rawurlencode($code);

        $args = [
            'timeout' => max(3, intval($timeout)),
            'headers' => [ 'Accept' => 'application/json' ],
        ];
        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            $out['http'] = 0;
            $out['json'] = ['status'=>'error','message'=>$res->get_error_message()];
            return $out;
        }
        $code_http = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);

        $out['http'] = $code_http;
        $out['raw']  = $body;

        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $out['json'] = $json;
        } else {
            $out['json'] = ['status'=>'error','message'=>'Ungültiges JSON vom Webhook'];
        }
        return $out;
    }

    /** JSON normalisieren: unterstützt {status,eid,...} oder Zoho {details:{output:{...}, userMessage:[...]}} */
    private function normalize_webhook_payload($json) {
        $base = is_array($json) ? $json : [];
        $payload = [];

        // Fall 1: Flache Struktur
        if (isset($base['status']) || isset($base['eid']) || isset($base['crm_message']) || isset($base['message'])) {
            $payload = $base;
        }

        // Fall 2: Zoho-Fn-Output: details.output + details.userMessage[0]
        if (isset($base['details']) && is_array($base['details'])) {
            if (isset($base['details']['output']) && is_array($base['details']['output'])) {
                $payload = array_merge($payload, $base['details']['output']);
            }
            if (isset($base['details']['userMessage'][0]) && is_array($base['details']['userMessage'][0])) {
                $payload = array_merge($this->flatten_array($base['details']['userMessage'][0]), $payload);
            }
        }

        // EID als String normalisieren (Array -> erstes Element)
        if (isset($payload['eid'])) {
            $eid = $payload['eid'];
            if (is_array($eid)) {
                $eid = reset($eid);
            }
            $payload['eid'] = is_scalar($eid) ? (string)$eid : '';
        } elseif (isset($payload['Veranstaltungen_id'])) {
            // Fallback aus geflachter Struktur
            $payload['eid'] = (string)$payload['Veranstaltungen_id'];
        }

        // Komfortfelder name/email ergänzen
        if (!isset($payload['name'])) {
            if (isset($payload['Teilnehmer_name']))       $payload['name'] = $payload['Teilnehmer_name'];
            elseif (isset($payload['customerprivat_name']))$payload['name'] = $payload['customerprivat_name'];
        }
        if (!isset($payload['email'])) {
            if (isset($payload['Email'])) $payload['email'] = $payload['Email'];
        }

        // status: wenn nicht vorhanden, evtl. aus "code"
        if (!isset($payload['status']) && isset($base['code']) && $base['code'] === 'success') {
            // nur Prozess-Erfolg, NICHT Zugangs-Erfolg – daher nicht automatisch "ok" setzen
        }

        return $payload;
    }

    /** Array flach machen: Nested keys -> key_subkey */
    private function flatten_array($arr, $prefix = '', $sep = '_') {
        $out = [];
        foreach ($arr as $k=>$v) {
            $key = $prefix ? ($prefix.$sep.$k) : $k;
            if (is_array($v)) {
                // einfache Arrays (z.B. ["id1","id2"]) als Komma-String
                $is_assoc = array_keys($v) !== range(0, count($v)-1);
                if ($is_assoc) {
                    $out += $this->flatten_array($v, $key, $sep);
                } else {
                    $out[$key] = implode(',', array_map(function($x){ return is_scalar($x)?(string)$x:json_encode($x); }, $v));
                }
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    }

    /** Platzhalter-Template */
    private function fill_template($tpl, $arr) {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function($m) use ($arr){
            $k = trim($m[1]);
            if ($k === '') return '';
            if (isset($arr[$k]) && $arr[$k] !== null) return esc_html((string)$arr[$k]);
            return '';
        }, (string)$tpl);
    }

    private function get_client_ip() {
        $keys = ['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$k]));
                if ($k==='HTTP_X_FORWARDED_FOR' && strpos($ip,',')!==false) {
                    $parts = array_map('trim', explode(',', $ip));
                    $ip = $parts[0];
                }
                return $ip;
            }
        }
        return '';
    }
}

Bummern_Code_Scanner::instance();
