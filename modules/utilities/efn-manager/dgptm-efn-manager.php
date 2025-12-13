<?php
/**
 * Plugin Name: DGPTM - EFN Manager
 * Description: Zentrales Management-System für die Einheitliche Fortbildungsnummer (EFN). Umfasst: JsBarcode-basierte Code128-Barcodes, A4-Aufkleberbogen-Generierung (diverse Vorlagen), Self-Service-Kiosk mit Scanner-Integration, PrintNode Silent Printing, Benutzerprofil-Verwaltung, Zoho CRM-Integration, Webhook-Verarbeitung und präzise Druckkalibierung.
 * Version: 1.0.3
 * Author: Sebastian Melzer
 * Text Domain: dgptm-efn-manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper-Funktion fuer EFN Manager Settings
 * Verwendet das zentrale DGPTM Settings-System mit Fallback auf alte Options
 */
if (!function_exists('dgptm_efn_get_setting')) {
    function dgptm_efn_get_setting($key, $default = null) {
        // Mapping von alten Option-Keys auf neue zentrale Keys
        static $key_mapping = [
            'dgptm_efn_autofill_on_init' => 'autofill_on_init',
            'dgptm_default_template' => 'default_template',
            'dgptm_kiosk_webhook' => 'kiosk_webhook',
            'dgptm_kiosk_mode' => 'kiosk_mode',
            'dgptm_kiosk_template' => 'kiosk_template',
            'dgptm_debug_default' => 'debug_default',
            'dgptm_kiosk_top_correction_mm' => 'top_correction_mm',
            'dgptm_kiosk_bottom_correction_mm' => 'bottom_correction_mm',
            'dgptm_kiosk_left_correction_mm' => 'left_correction_mm',
            'dgptm_kiosk_right_correction_mm' => 'right_correction_mm',
            'dgptm_footer_show' => 'footer_show',
            'dgptm_footer_from_bottom_mm' => 'footer_from_bottom_mm',
            'dgptm_printnode_api_key' => 'printnode_api_key',
            'dgptm_printnode_printer_id' => 'printnode_printer_id'
        ];

        // Alten Key auf neuen Key mappen
        $new_key = isset($key_mapping[$key]) ? $key_mapping[$key] : $key;

        // Zuerst im zentralen System suchen
        if (function_exists('dgptm_get_module_setting')) {
            $value = dgptm_get_module_setting('efn-manager', $new_key, null);
            if ($value !== null) {
                return $value;
            }
        }

        // Fallback auf alten Option-Key
        return get_option($key, $default);
    }
}

/* ============================================================
 * EFN Label Sheets & Kiosk System
 * ============================================================ */
final class DGPTM_EFN_Labels {
    private static $instance = null;

    /** A4-Vorlagen (Maße in mm) - Optimierte Werte für präzisen Druck */
    private $templates = array(
        'Avery Zweckform 3667 (48.5×16.9, 4×16)' => array(
            'page_w'  => 210,  'page_h'  => 297,
            'cols'    => 4,    'rows'    => 16,
            'label_w' => 48.5, 'label_h' => 16.9,
            'margin_l'=> 3.5,  'margin_t'=> 12.2,
            'h_space' => 2.8,  'v_space' => 0.0
        ),
        'LabelIdent EBL048X017PP (48,5×16,9, 4×16)' => array(
            'page_w'  => 210,  'page_h'  => 297,
            'cols'    => 4,    'rows'    => 16,
            'label_w' => 48.5, 'label_h' => 16.9,
            'margin_l'=> 3.5,  'margin_t'=> 11.5,
            'h_space' => 3.3,  'v_space' => 0.0
        ),
        'Zweckform L6011 (63.5×33.9, 3×8)' => array(
            'page_w'  => 210,  'page_h'  => 297,
            'cols'    => 3,    'rows'    => 8,
            'label_w' => 63.5, 'label_h' => 33.9,
            'margin_l'=> 7.2,  'margin_t'=> 12.5,
            'h_space' => 2.5,  'v_space' => 0.0
        ),
        'Zweckform L6021 (70×37, 3×8)' => array(
            'page_w'  => 210,  'page_h'  => 297,
            'cols'    => 3,    'rows'    => 8,
            'label_w' => 70.0, 'label_h' => 37.0,
            'margin_l'=> 5.0,  'margin_t'=> 10.5,
            'h_space' => 2.0,  'v_space' => 0.0
        ),
        'Avery L7160 (63.5×38.1, 3×7)' => array(
            'page_w'  => 210,  'page_h'  => 297,
            'cols'    => 3,    'rows'    => 7,
            'label_w' => 63.5, 'label_h' => 38.1,
            'margin_l'=> 7.0,  'margin_t'=> 15.5,
            'h_space' => 2.5,  'v_space' => 0.0
        ),
        'Avery L7563 (99.1×38.1, 2×7)' => array(
            'page_w'  => 210,  'page_h'  => 297,
            'cols'    => 2,    'rows'    => 7,
            'label_w' => 99.1, 'label_h' => 38.1,
            'margin_l'=> 5.8,  'margin_t'=> 15.5,
            'h_space' => 2.5,  'v_space' => 0.0
        ),
        'Zweckform L6021REV-25 (45.7×16.9, 4×16)' => array(
            'page_w'  => 210,  'page_h'  => 297,
            'cols'    => 4,    'rows'    => 16,
            'label_w' => 45.7, 'label_h' => 16.9,
            'margin_l'=> 14.0, 'margin_t'=> 13.5,
            'h_space' => 0.0,  'v_space' => 0.0
        ),
    );

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('efn_label_sheet', array($this, 'shortcode_label_sheet'));

        // Frontend-Endpunkte statt admin-post.php
        add_action('init', array($this, 'handle_frontend_requests'));

        add_shortcode('efn_kiosk', array($this, 'shortcode_kiosk'));

        add_shortcode('efn_barcode_js', array($this, 'shortcode_barcode'));
    }

    /* ---------- Frontend Request Handler ---------- */

    public function handle_frontend_requests() {
        // PDF-Download Handler
        if (isset($_REQUEST['dgptm_efn_action']) && $_REQUEST['dgptm_efn_action'] === 'download_labels') {
            $this->handle_download();
        }

        // Kiosk-Print Handler (AJAX)
        if (isset($_REQUEST['dgptm_efn_action']) && $_REQUEST['dgptm_efn_action'] === 'kiosk_print') {
            $this->ajax_kiosk_print();
        }
    }

    /* ---------- Helpers ---------- */

    private function get_efn_from_shortcode() {
        if ( ! function_exists( 'do_shortcode' ) ) return '';
        $raw = do_shortcode('[zoho_api_data field="EFN"]');
        $raw = trim(wp_strip_all_tags($raw));
        $digits = preg_replace('/\D+/', '', $raw);
        return (strlen($digits) === 15) ? $digits : '';
    }

    private function get_name_from_shortcodes() {
        if ( ! function_exists( 'do_shortcode' ) ) return '';
        $vor  = trim( wp_strip_all_tags( do_shortcode('[zoho_api_data field="Vorname"]') ) );
        $nach = trim( wp_strip_all_tags( do_shortcode('[zoho_api_data field="Nachname"]') ) );
        return trim($vor.' '.$nach);
    }

    private function require_fpdf() {
        $fpdf_path = DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php';
        if ( ! file_exists($fpdf_path) ) {
            return new WP_Error('fpdf_missing', 'FPDF nicht gefunden. Bitte fpdf.php nach /fpdf/ legen.');
        }
        require_once $fpdf_path;

        // Code128 Barcode-Klasse aus der Library laden
        $code128_path = DGPTM_SUITE_PATH . 'libraries/class-code128.php';
        if ( ! file_exists($code128_path) ) {
            return new WP_Error('code128_missing', 'Code128 Klasse nicht gefunden.');
        }
        require_once $code128_path;

        return true;
    }

    /**
     * PDF erzeugen – akzeptiert ENTWEDER einen Template-Key (string) ODER ein komplettes Template-Array
     */
    private function render_labels_pdf($efn, $template_or_key, $sheet_name = '', $args = array()) {
        // Template bestimmen
        if (is_string($template_or_key)) {
            if (!isset($this->templates[$template_or_key])) {
                return new WP_Error('tpl_unknown', 'Unbekannte Vorlage.');
            }
            $tpl = $this->templates[$template_or_key];
        } elseif (is_array($template_or_key)) {
            $tpl = $template_or_key;
        } else {
            return new WP_Error('tpl_invalid', 'Ungültige Vorlage.');
        }

        // Zusätzliche Argumente (Feinjustage) - Optimierte Standardwerte
        $y_offset_mm        = isset($args['y_offset_mm']) ? (float)$args['y_offset_mm'] : 0.0;
        $y_drift_mm         = isset($args['y_drift_mm'])  ? (float)$args['y_drift_mm']  : 0.0;
        $h_left_corr_mm     = isset($args['h_left_corr_mm'])  ? (float)$args['h_left_corr_mm']  : 0.0;
        $h_right_corr_mm    = isset($args['h_right_corr_mm']) ? (float)$args['h_right_corr_mm'] : 0.0;
        $footer_from_bottom = isset($args['footer_from_bottom_mm']) ? max(0.0, (float)$args['footer_from_bottom_mm']) : 7.0;
        $show_footer        = array_key_exists('show_footer', $args) ? (bool)$args['show_footer'] : ($sheet_name !== '');
        $footer_cell_h      = 5.0;

        // Pflichtfelder prüfen
        $need = array('page_w','page_h','cols','rows','label_w','label_h','margin_l','margin_t','h_space','v_space');
        foreach ($need as $k) {
            if (!isset($tpl[$k])) return new WP_Error('tpl_missing', 'Template-Feld fehlt: '.$k);
        }

        // Grundvalidierung
        $tpl['page_w']  = max(10,  (float)$tpl['page_w']);
        $tpl['page_h']  = max(10,  (float)$tpl['page_h']);
        $tpl['cols']    = max(1,   (int)$tpl['cols']);
        $tpl['rows']    = max(1,   (int)$tpl['rows']);
        $tpl['label_w'] = max(1.0, (float)$tpl['label_w']);
        $tpl['label_h'] = max(1.0, (float)$tpl['label_h']);
        $tpl['margin_l']= max(0.0, (float)$tpl['margin_l']);
        $tpl['margin_t']= max(0.0, (float)$tpl['margin_t']);
        $tpl['h_space'] = max(0.0, (float)$tpl['h_space']);
        $tpl['v_space'] = max(0.0, (float)$tpl['v_space']);

        $ok = $this->require_fpdf();
        if ( is_wp_error($ok) ) return $ok;

        $pdf = new FPDF('P', 'mm', array($tpl['page_w'], $tpl['page_h']));
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->SetFont('Helvetica','',8);

        $cols     = (int)$tpl['cols'];
        $rows     = (int)$tpl['rows'];
        $label_w  = (float)$tpl['label_w'];
        $label_h  = (float)$tpl['label_h'];
        $margin_l = (float)$tpl['margin_l'];
        $margin_t = (float)$tpl['margin_t'];
        $h_space  = (float)$tpl['h_space'];
        $v_space  = (float)$tpl['v_space'];

        // Breite der gesamten Labelfläche
        $grid_w = ($cols * $label_w) + (($cols - 1) * $h_space);

        // Überschrift oben
        if ($sheet_name !== '') {
            $header_text = 'DGPTM-EFN-Bogen von: '.$sheet_name;
            $hdr_size = 10;
            $pdf->SetFont('Helvetica','B', $hdr_size);
            $pdf->SetXY($margin_l, 3);
            $pdf->Cell($grid_w, 5, $header_text, 0, 0, 'C');
            $pdf->SetFont('Helvetica','',8);
        }

        // Dynamische Berechnung der optimalen Barcode-Dimensionen
        // Kleine Labels (≤50mm): 60% Breite, mittlere (51-70mm): 70%, große (>70mm): 75%
        if ($label_w <= 50) {
            $barcode_w_percent = 0.60;
            $barcode_h = 6.0;
            $text_size = 7;
        } elseif ($label_w <= 70) {
            $barcode_w_percent = 0.70;
            $barcode_h = 7.0;
            $text_size = 8;
        } else {
            $barcode_w_percent = 0.75;
            $barcode_h = 8.0;
            $text_size = 9;
        }

        $barcode_w = $label_w * $barcode_w_percent;
        $text_gap  = 0.8;

        // Berechne die Gesamthöhe des Blocks (Barcode + Abstand + Text)
        $text_height = $text_size * 0.35; // mm (Approximation: 1pt ≈ 0.35mm)
        $block_h = $barcode_h + $text_gap + $text_height;

        // Labels: vertikale 2-Punkt-Drift + horizontale Rand-Drift (links↔rechts)
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                // Horizontal drift: Interpolation von linker zu rechter Spalte
                $h_drift = 0.0;
                if ($cols > 1) {
                    $t = $c / ($cols - 1);
                    $h_drift = (1.0 - $t) * $h_left_corr_mm + $t * $h_right_corr_mm;
                }
                $x = $margin_l + $c * ($label_w + $h_space) + $h_drift;

                // Vertikal drift: 0 für oberste Reihe → y_drift_mm für unterste Reihe
                $v_drift = ($rows > 1) ? ($r / ($rows - 1)) * $y_drift_mm : 0.0;
                $y = ($margin_t + $y_offset_mm + $v_drift) + $r * ($label_h + $v_space);

                // Vertikale Zentrierung des gesamten Blocks innerhalb des Labels
                $by = $y + ($label_h - $block_h) / 2.0;
                $bx = $x + ($label_w - $barcode_w) / 2.0;

                // Zeichne Barcode mit optimaler Quiet Zone
                // Kleine Labels: 1.5mm, mittlere: 2.0mm, große: 2.5mm
                $quiet_zone = ($label_w <= 50) ? 1.5 : (($label_w <= 70) ? 2.0 : 2.5);
                DGPTM_Code128::draw($pdf, $bx, $by, $barcode_w, $barcode_h, $efn, $quiet_zone);

                // Text zentriert unter dem Barcode
                $text_y = $by + $barcode_h + $text_gap;
                $pdf->SetFont('Helvetica', '', $text_size);
                $pdf->SetXY($x, $text_y);
                $pdf->Cell($label_w, $text_height, 'EFN: '.$efn, 0, 0, 'C');
                $pdf->SetFont('Helvetica', '', 8); // Reset auf Standard
            }
        }

        // Footer unten (optional)
        if ($show_footer) {
            $footer_text = 'Erstellt von DGPTM';
            $pdf->SetFont('Helvetica','I', 8);
            $footer_y = max(0.0, $tpl['page_h'] - ($footer_from_bottom + $footer_cell_h));
            $pdf->SetXY($margin_l, $footer_y);
            $pdf->Cell($grid_w, $footer_cell_h, $footer_text, 0, 0, 'C');
        }

        return $pdf;
    }

    private function stream_pdf_inline($pdf, $filename) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        while (ob_get_level()) { ob_end_clean(); }
        $pdf->Output('I', $filename);
        exit;
    }

    private function save_pdf_tmp($pdf, $filename='print.pdf') {
        $upload = wp_upload_dir();
        $path = trailingslashit($upload['basedir']).$filename;
        $pdf->Output('F', $path);
        return trailingslashit($upload['baseurl']).$filename;
    }

    /** Strikter POST (JSON) zu Zoho Functions ohne Authorization-Header */
    private function strict_post_no_auth($url, array $args_json) {
        $body = wp_json_encode(array('arguments' => $args_json));

        $rm = function($handle){
            curl_setopt($handle, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Accept' => 'application/json',
            ));
            curl_setopt($handle, CURLOPT_HTTPAUTH, 0);
        };
        add_action('http_api_curl', $rm, 10, 1);

        $resp = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'    => $body,
            'timeout' => 20,
        ));

        remove_action('http_api_curl', $rm, 10);
        return $resp;
    }

    /* ---------- Shortcode: Label-Sheet ---------- */

    public function shortcode_label_sheet($atts = array()) {
        $atts = shortcode_atts(array(
            'default' => 'LabelIdent EBL048X017PP (48,5×16,9, 4×16)',
        ), $atts, 'efn_label_sheet');

        $efn = $this->get_efn_from_shortcode();
        $default_tpl   = $atts['default'];
        $detected_name = $this->get_name_from_shortcodes();

        // Frontend-URL statt admin-post.php
        $action_url = esc_url( home_url('/') );
        $nonce = wp_create_nonce('dgptm_efn_pdf');

        ob_start(); ?>
        <div class="dgptm-efn-sheet" style="border:1px solid #ddd;padding:12px;border-radius:8px;">
            <h3 style="margin-top:0">EFN-Aufkleberbogen zum Selberdrucken</h3>
            <h6 style="margin-top:0">Fehlende Vorlagen bitte an geschaeftsstelle@dgptm.de melden.</h6>
            <p><b>EFN:</b> <code><?php echo esc_html($efn ?: '—'); ?></code></p>
            

            <?php if (!$efn): ?>
                <p style="color:#b00">Die EFN konnte nicht ermittelt werden (erwartet 15 Ziffern). Bitte sicherstellen, dass <code>[zoho_api_data field="EFN"]</code> korrekt auflöst.</p>
            <?php endif; ?>

            <form id="dgptm-efn-form" method="get" action="<?php echo $action_url; ?>" target="_blank">
                <input type="hidden" name="dgptm_efn_action" value="download_labels">
                <input type="hidden" name="dgptm_efn_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="sheet_name" value="<?php echo esc_attr($detected_name); ?>">

                <label><b>Vorlage/Format (A4)</b></label><br>
                <select name="template" id="dgptm_tpl_select" style="min-width:320px;padding:6px;margin:6px 0">
                    <?php foreach ($this->templates as $name=>$tpl): ?>
                        <option value="<?php echo esc_attr($name); ?>" <?php selected($name, $default_tpl); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                    <option value="__custom__">Benutzerdefiniert (A4)</option>
                </select>

                <!-- Benutzerdefinierte Maße -->
                <div id="dgptm_custom_box" style="display:none; padding:10px; border:1px dashed #ccc; border-radius:6px; margin:8px 0;">
                    <div style="display:grid; grid-template-columns: repeat(5, minmax(120px, 1fr)); gap:10px;">
                        <label>Seite B × H (mm)
                            <input type="number" step="0.1" name="c_page_w" value="210" style="width:100%">
                            <input type="number" step="0.1" name="c_page_h" value="297" style="width:100%">
                        </label>
                        <label>Spalten × Zeilen
                            <input type="number" step="1" name="c_cols" value="3" style="width:100%">
                            <input type="number" step="1" name="c_rows" value="8" style="width:100%">
                        </label>
                        <label>Etikett B × H (mm)
                            <input type="number" step="0.1" name="c_label_w" value="63.5" style="width:100%">
                            <input type="number" step="0.1" name="c_label_h" value="33.9" style="width:100%">
                        </label>
                        <label>Rand links/oben (mm)
                            <input type="number" step="0.1" name="c_margin_l" value="7.0" style="width:100%">
                            <input type="number" step="0.1" name="c_margin_t" value="12.0" style="width:100%">
                        </label>
                        <label>Abstand h / v (mm)
                            <input type="number" step="0.1" name="c_h_space" value="2.5" style="width:100%">
                            <input type="number" step="0.1" name="c_v_space" value="0.0" style="width:100%">
                        </label>
                    </div>
                    <small style="color:#555;display:block;margin-top:6px;">
                        Hinweis: Das Raster darf die Seitenbreite/-höhe nicht überschreiten. Bei Bedarf Ränder/Abstände leicht anpassen.
                    </small>
                </div>

                <button type="submit" style="background:#111;color:#fff;padding:8px 14px;border-radius:6px;border:0;cursor:pointer" <?php echo $efn ? '' : 'disabled'; ?>>
                    PDF jetzt herunterladen
                </button>
            </form>

            <script>
            (function(){
                const sel = document.getElementById('dgptm_tpl_select');
                const box = document.getElementById('dgptm_custom_box');
                function toggle(){ if(box && sel) box.style.display = (sel.value === '__custom__') ? 'block' : 'none'; }
                if (sel){ sel.addEventListener('change', toggle); toggle(); }
            })();
            </script>
        </div>
        <?php return ob_get_clean();
    }

    public function handle_download() {
        $nonce = $_REQUEST['dgptm_efn_nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'dgptm_efn_pdf' ) ) { status_header(400); wp_die('Ungültiger Aufruf.'); }

        $efn = $this->get_efn_from_shortcode();
        if (!$efn) { status_header(400); wp_die('EFN fehlt oder ist nicht 15-stellig.'); }

        $template_key = sanitize_text_field($_REQUEST['template'] ?? '');
        $sheet_name   = sanitize_text_field($_REQUEST['sheet_name'] ?? '');
        if ($sheet_name === '') { $sheet_name = $this->get_name_from_shortcodes(); }

        // Footer-Settings
        $f_off  = (float) dgptm_efn_get_setting('dgptm_footer_from_bottom_mm', 7.0);
        $f_show = (dgptm_efn_get_setting('dgptm_footer_show','yes') === 'yes');

        if ($template_key === '__custom__') {
            $tpl = array(
                'page_w'  => isset($_REQUEST['c_page_w'])  ? floatval($_REQUEST['c_page_w'])  : 210.0,
                'page_h'  => isset($_REQUEST['c_page_h'])  ? floatval($_REQUEST['c_page_h'])  : 297.0,
                'cols'    => isset($_REQUEST['c_cols'])    ? intval($_REQUEST['c_cols'])      : 3,
                'rows'    => isset($_REQUEST['c_rows'])    ? intval($_REQUEST['c_rows'])      : 8,
                'label_w' => isset($_REQUEST['c_label_w']) ? floatval($_REQUEST['c_label_w']) : 63.5,
                'label_h' => isset($_REQUEST['c_label_h']) ? floatval($_REQUEST['c_label_h']) : 33.9,
                'margin_l'=> isset($_REQUEST['c_margin_l'])? floatval($_REQUEST['c_margin_l']): 7.0,
                'margin_t'=> isset($_REQUEST['c_margin_t'])? floatval($_REQUEST['c_margin_t']): 12.0,
                'h_space' => isset($_REQUEST['c_h_space']) ? floatval($_REQUEST['c_h_space']) : 2.5,
                'v_space' => isset($_REQUEST['c_v_space']) ? floatval($_REQUEST['c_v_space']) : 0.0,
            );
            $pdf = $this->render_labels_pdf($efn, $tpl, $sheet_name, array(
                'y_offset_mm'           => 0.0,
                'y_drift_mm'            => 0.0,
                'h_left_corr_mm'        => 0.0,
                'h_right_corr_mm'       => 0.0,
                'footer_from_bottom_mm' => $f_off,
                'show_footer'           => $f_show,
            ));
        } else {
            $pdf = $this->render_labels_pdf($efn, $template_key, $sheet_name, array(
                'y_offset_mm'           => 0.0,
                'y_drift_mm'            => 0.0,
                'h_left_corr_mm'        => 0.0,
                'h_right_corr_mm'       => 0.0,
                'footer_from_bottom_mm' => $f_off,
                'show_footer'           => $f_show,
            ));
        }

        if ( is_wp_error($pdf) ) { status_header(400); wp_die( $pdf->get_error_message() ); }

        $filename = 'EFN_Labels_'.$efn.'.pdf';
        $this->stream_pdf_inline($pdf, $filename);
    }

    /* ---------- Shortcode: Kiosk ---------- */
    public function shortcode_kiosk($atts = array()) {
        // Defaults aus Optionen
        $def_webhook  = esc_url_raw( dgptm_efn_get_setting('dgptm_kiosk_webhook', '') );
        $def_mode     = in_array(dgptm_efn_get_setting('dgptm_kiosk_mode','browser'), array('browser','printnode'), true) ? dgptm_efn_get_setting('dgptm_kiosk_mode','browser') : 'browser';
        $def_debug    = (dgptm_efn_get_setting('dgptm_debug_default', 'no') === 'yes') ? 'yes' : 'no';
        $def_tpl      = dgptm_efn_get_setting('dgptm_kiosk_template', dgptm_efn_get_setting('dgptm_default_template','LabelIdent EBL048X017PP (48,5×16,9, 4×16)'));

        $atts = shortcode_atts(array(
            'webhook'  => $def_webhook,
            'mode'     => $def_mode,
            'debug'    => $def_debug,
            'template' => $def_tpl,
        ), $atts, 'efn_kiosk');

        // Frontend-URL statt admin-ajax.php
        $ajax_url = esc_url( home_url('/') );
        $nonce    = wp_create_nonce('dgptm_kiosk');

        ob_start(); ?>
        <div class="dgptm-kiosk" style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#111;color:#fff;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;">
            <div style="max-width:900px;width:100%;padding:24px;">
                <h1 style="margin:0 0 12px 0;font-size:28px;">EFN Bogen selber drucken</h1>
                <p style="margin:0;color:#bbb">Code vom Kongressausweis scannen → Ausdruck startet automatisch.</p>

                <form id="dgptm-kiosk-form" autocomplete="off" onsubmit="return false;" style="display:flex;gap:12px;">
                    <input id="dgptm-kiosk-code" type="text" inputmode="numeric" pattern="[0-9A-Za-z\-]{1,}" placeholder="Code scannen …"
                           style="flex:1;padding:14px 16px;border-radius:8px;border:0;outline:0;font-size:22px;">
                    <button id="dgptm-kiosk-send" style="padding:14px 18px;border-radius:8px;border:0;background:#06c;color:#fff;font-size:18px;cursor:pointer">
                        Senden
                    </button>
                </form>

                <div id="dgptm-kiosk-status" style="margin-top:14px;color:#9fd;"></div>

                <div id="dgptm-kiosk-debug" style="margin-top:12px;display:none;">
                    <details open style="background:#222;border-radius:8px;padding:12px;border:1px solid #333;">
                        <summary style="cursor:pointer;color:#ccc">Debug</summary>
                        <pre id="dgptm-kiosk-debug-pre" style="white-space:pre-wrap;color:#9fe;overflow:auto;max-height:40vh;margin-top:10px;"></pre>
                    </details>
                </div>

                <iframe id="dgptm-kiosk-printframe"
                        style="position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;"
                        aria-hidden="true"></iframe>
            </div>
        </div>

        <script>
        (function(){
            const ajaxUrl = <?php echo json_encode($ajax_url); ?>;
            const nonce   = <?php echo json_encode($nonce); ?>;
            const webhook = <?php echo json_encode($atts['webhook']); ?>;
            const mode    = <?php echo json_encode($atts['mode']); ?>;
            const tpl     = <?php echo json_encode($atts['template']); ?>;
            const debug   = <?php echo json_encode($atts['debug']); ?> === 'yes';

            const $code   = document.getElementById('dgptm-kiosk-code');
            const $btn    = document.getElementById('dgptm-kiosk-send');
            const $status = document.getElementById('dgptm-kiosk-status');
            const $frame  = document.getElementById('dgptm-kiosk-printframe');
            const $dbgBox = document.getElementById('dgptm-kiosk-debug');
            const $dbgPre = document.getElementById('dgptm-kiosk-debug-pre');

            function setStatus(msg, ok=true){ $status.textContent = msg||''; $status.style.color = ok ? '#9fd' : '#f99'; }
            function showDebug(obj){
                if(!debug) return;
                $dbgBox.style.display = 'block';
                try { $dbgPre.textContent = (typeof obj === 'string') ? obj : JSON.stringify(obj, null, 2); }
                catch(e){ $dbgPre.textContent = String(obj); }
            }
            function parseResponse(r){
                return r.text().then(txt=>{
                    try { return { ok:true, data: JSON.parse(txt) }; }
                    catch(e){ return { ok:false, text: txt, err: e }; }
                });
            }

            function send() {
                const v = ($code.value || '').trim();
                if(!v){ setStatus('Bitte Code scannen …', false); return; }
                if(!webhook){ setStatus('Hinweis: Keine Webhook-URL im Client – Server-Default wird verwendet.', true); }

                setStatus('Webhook → EFN prüfen …');

                const fd = new FormData();
                fd.append('dgptm_efn_action','kiosk_print');
                fd.append('nonce', nonce);
                fd.append('code', v);
                fd.append('webhook', webhook);
                fd.append('mode', mode);
                fd.append('template', tpl);
                fd.append('debug', debug ? '1' : '0');

                fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
                  .then(parseResponse)
                  .then(pack => {
                      if (!pack.ok) {
                          setStatus('Server lieferte keine gültige JSON-Antwort.', false);
                          if (debug) showDebug(pack.text || '(leer)');
                          return;
                      }
                      const data = pack.data;

                      if(!data || !data.ok){
                          const msg = (data && (data.error || data.message)) ? (data.error || data.message) : 'Fehler.';
                          setStatus(msg, false);
                          if (debug) showDebug(data);
                          return;
                      }

                      setStatus(data.message || 'Druck gestartet.', true);

                      if (data.mode === 'browser' && data.url){
                          $frame.onload = function(){
                              try {
                                  $frame.contentWindow.focus();
                                  $frame.contentWindow.print();
                                  setTimeout(()=>{ try{ $frame.contentWindow.print(); }catch(_){} }, 800);
                              } catch(e) { if (debug) showDebug({ iframe_print_error: String(e) }); }
                          };
                          setTimeout(()=>{ $frame.src = data.url + '#toolbar=0&navpanes=0&scrollbar=0'; }, 60);
                      }
                      $code.value = '';
                  })
                  .catch(err => {
                      if (debug) showDebug({ fetch_error:String(err) });
                      setStatus('Netzwerk-/Serverfehler: '+err, false);
                  });
            }

            $btn.addEventListener('click', send);
            $code.addEventListener('keydown', e => { if(e.key === 'Enter'){ e.preventDefault(); send(); } });
            window.addEventListener('load', ()=> { $code.focus(); });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /** Frontend: Kiosk-Print */
    public function ajax_kiosk_print() {
        nocache_headers();
        header('Content-Type: application/json; charset=UTF-8');

        // Nonce-Prüfung (Frontend statt AJAX)
        $nonce = $_POST['nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'dgptm_kiosk' ) ) {
            wp_send_json(array('ok'=>false, 'message'=>'Ungültige Sicherheitsprüfung.'));
        }

        $code      = sanitize_text_field($_POST['code'] ?? '');

        // Webhook: zuerst POST, wenn leer → Option
        $webhook_post = isset($_POST['webhook']) ? trim((string)$_POST['webhook']) : '';
        $webhook      = esc_url_raw($webhook_post);
        if (empty($webhook)) {
            $webhook_opt = dgptm_efn_get_setting('dgptm_kiosk_webhook', '');
            $webhook     = esc_url_raw($webhook_opt);
        }

        $mode_post = sanitize_text_field($_POST['mode'] ?? '');
        $mode_opt  = dgptm_efn_get_setting('dgptm_kiosk_mode','browser');
        $debug     = !empty($_POST['debug']);

        $template_post = sanitize_text_field($_POST['template'] ?? '');
        $template_opt  = dgptm_efn_get_setting('dgptm_kiosk_template', dgptm_efn_get_setting('dgptm_default_template','LabelIdent EBL048X017PP (48,5×16,9, 4×16)'));
        $template      = $template_post ?: $template_opt;

        if (!$code) {
            wp_send_json(array(
                'ok'=>false,
                'message'=>'Kein Code übermittelt.',
                '_debug'=> $debug ? array('note'=>'missing code') : null
            ));
        }

        if (empty($webhook)) {
            wp_send_json(array(
                'ok'=>false,
                'message'=>'Webhook-URL fehlt (weder im Request noch in den Einstellungen gesetzt).',
                '_debug'=> $debug ? array('note'=>'missing webhook after fallback','webhook_post_raw'=>$webhook_post) : null
            ));
        }

        if (!isset($this->templates[$template])) {
            $template = 'LabelIdent EBL048X017PP (48,5×16,9, 4×16)';
        }

        $api_key    = trim(dgptm_efn_get_setting('dgptm_printnode_api_key',''));
        $printer_id = intval(dgptm_efn_get_setting('dgptm_printnode_printer_id', 0));

        $mode_selected = $mode_post ?: $mode_opt;
        if ($mode_opt === 'printnode' && $api_key && $printer_id > 0) {
            $mode_selected = 'printnode';
        }

        // 1) Webhook POST JSON
        $resp = $this->strict_post_no_auth($webhook, array('code' => $code));
        if ( is_wp_error($resp) ) {
            wp_send_json(array('ok'=>false, 'message'=>'Webhook-Fehler: '.$resp->get_error_message(), '_debug'=> $debug ? array('wp_error'=>$resp->get_error_message()) : null));
        }

        $http_code = wp_remote_retrieve_response_code($resp);
        $headers_o = wp_remote_retrieve_headers($resp);
        $headers   = is_object($headers_o) ? $headers_o->getAll() : (array)$headers_o;
        $body      = wp_remote_retrieve_body($resp);
        $json      = json_decode($body, true);

        // Payload extrahieren
        $payload = array();
        if (is_array($json)) {
            if (isset($json['details']['output'])) {
                $out = $json['details']['output'];
                if (is_array($out)) { $payload = $out; }
                elseif (is_string($out)) {
                    $inner = json_decode($out, true);
                    if (is_array($inner)) $payload = $inner;
                }
            }
            if (!$payload && isset($json['details']['userMessage'][2])) {
                $cand = $json['details']['userMessage'][2];
                if (is_array($cand)) { $payload = $cand; }
                elseif (is_string($cand)) {
                    $inner2 = json_decode($cand, true);
                    if (is_array($inner2)) $payload = $inner2;
                }
            }
        }

        $status_efn  = isset($payload['statusefn'])  ? strtolower(trim((string)$payload['statusefn'])) : '';
        $message_efn = isset($payload['messageefn']) ? (string)$payload['messageefn'] : '';
        $name_efn    = isset($payload['name'])       ? (string)$payload['name']      : '';
        $efn_val     = isset($payload['efn'])        ? (string)$payload['efn']       : '';

        $status_raw  = ($status_efn === ''  && isset($json['status']))  ? strtolower(trim((string)$json['status'])) : '';
        $message_raw = ($message_efn === '' && isset($json['message'])) ? (string)$json['message']                  : '';
        if ($message_raw === 'function executed successfully' && $message_efn !== '') { $message_raw = ''; }

        $eff_status  = $status_efn  !== '' ? $status_efn  : $status_raw;
        $eff_message = $message_efn !== '' ? $message_efn : $message_raw;

        $sheet_name = sanitize_text_field($name_efn);
        $efn        = preg_replace('/\D+/', '', $efn_val);

        if ($eff_status === '') {
            $eff_status = (strlen($efn) === 15) ? 'found' : 'notfound';
            if ($eff_status === 'notfound' && $eff_message === '') $eff_message = 'Keine EFN gefunden.';
        }

        if ($eff_status !== 'found' || strlen($efn)!==15) {
            wp_send_json(array(
                'ok'=>false, 'status'=>$eff_status, 'message'=>$eff_message ?: 'EFN nicht gefunden.',
                '_debug'=> $debug ? array('http_code'=>$http_code,'headers'=>$headers,'body'=>$body,'json'=>$json,'payload'=>$payload) : null
            ));
        }

        // 2) PDF erzeugen
        $top_corr_mm    = (float) dgptm_efn_get_setting('dgptm_kiosk_top_correction_mm', -5.0);
        $bottom_corr_mm = (float) dgptm_efn_get_setting('dgptm_kiosk_bottom_correction_mm',  5.0);
        $y_off   = $top_corr_mm;
        $y_drift = $bottom_corr_mm - $top_corr_mm;

        $h_left  = (float) dgptm_efn_get_setting('dgptm_kiosk_left_correction_mm',  -5.0);
        $h_right = (float) dgptm_efn_get_setting('dgptm_kiosk_right_correction_mm',  5.0);

        $f_off  = (float) dgptm_efn_get_setting('dgptm_footer_from_bottom_mm', 7.0);
        $f_show = (dgptm_efn_get_setting('dgptm_footer_show','yes') === 'yes');

        $pdf = $this->render_labels_pdf($efn, $template, $sheet_name, array(
            'y_offset_mm'            => $y_off,
            'y_drift_mm'             => $y_drift,
            'h_left_corr_mm'         => $h_left,
            'h_right_corr_mm'        => $h_right,
            'footer_from_bottom_mm'  => $f_off,
            'show_footer'            => $f_show,
        ));
        if ( is_wp_error($pdf) ) {
            wp_send_json(array('ok'=>false, 'message'=>$pdf->get_error_message(),
                '_debug'=> $debug ? array('http_code'=>$http_code,'headers'=>$headers,'body'=>$body,'json'=>$json,'payload'=>$payload) : null
            ));
        }

        // 3) Drucken
        if ($mode_opt === 'printnode' && $api_key && $printer_id > 0) {
            $mode_selected = 'printnode';
        }

        if ($mode_selected === 'printnode') {
            // PrintNode – silent serverseitig
            $upload = wp_upload_dir();
            $tmpFile = 'efn_kiosk_'.time().'.pdf';
            $path = trailingslashit($upload['basedir']).$tmpFile;
            $pdf->Output('F', $path);
            $data = file_get_contents($path);
            @unlink($path);

            if ($data===false) {
                wp_send_json(array('ok'=>false, 'message'=>'PDF konnte nicht gelesen werden.','_debug'=> $debug ? array('http_code'=>$http_code) : null));
            }

            if (!$api_key || !$printer_id) {
                wp_send_json(array('ok'=>false, 'message'=>'PrintNode API-Key/Printer-ID fehlen.','_debug'=> $debug ? array('http_code'=>$http_code) : null));
            }

            $payload_print = array(
                "printerId"   => $printer_id,
                "title"       => "EFN Labels ".$efn,
                "contentType" => "pdf_base64",
                "content"     => base64_encode($data),
                "source"      => "DGPTM-Labels-Kiosk"
            );

            $r2   = wp_remote_post('https://api.printnode.com/printjobs', array(
                'headers' => array(
                    'Authorization' => 'Basic '.base64_encode($api_key.':'),
                    'Content-Type'  => 'application/json'
                ),
                'body'    => wp_json_encode($payload_print),
                'timeout' => 20,
            ));
            if ( is_wp_error($r2) ) {
                wp_send_json(array('ok'=>false, 'message'=>'PrintNode-Fehler: '.$r2->get_error_message(),'_debug'=> $debug ? array('printnode_error'=>$r2->get_error_message()) : null));
            }
            $codeHttp = wp_remote_retrieve_response_code($r2);
            $body2    = wp_remote_retrieve_body($r2);
            $job_id   = null; $dec2 = json_decode($body2, true); if (is_array($dec2) && isset($dec2['id'])) $job_id = $dec2['id'];
            if ($codeHttp < 200 || $codeHttp >= 300) {
                wp_send_json(array('ok'=>false, 'message'=>'PrintNode HTTP '.$codeHttp.': '.$body2,'_debug'=> $debug ? array('printnode_http'=>$codeHttp, 'printnode_body'=>$body2) : null));
            }

            wp_send_json(array(
                'ok'=>true, 'mode'=>'printnode',
                'message'=>$eff_message ?: 'Druckauftrag gesendet.',
                '_debug'=> $debug ? array('printnode_job_id'=>$job_id) : null
            ));
        }

        // Browser-Kiosk
        $url = $this->save_pdf_tmp($pdf, 'efn_kiosk_'.time().'.pdf');
        wp_send_json(array(
            'ok'=>true, 'mode'=>'browser', 'url'=>$url,
            'message'=>$eff_message ?: 'Druck gestartet.',
            '_debug'=> $debug ? array('http_code'=>$http_code,'headers'=>$headers,'body'=>$body,'json'=>$json,'payload'=>$payload) : null
        ));
    }

    /* ---------- Shortcode: EFN Barcode (JavaScript-basiert) ---------- */
    public function shortcode_barcode($atts = array()) {
        $atts = shortcode_atts(array(
            'width'  => '280',
            'height' => '70',
        ), $atts, 'efn_barcode_js');

        $efn = $this->get_efn_from_shortcode();
        $width  = max(120, intval($atts['width']));
        $height = max(40,  intval($atts['height']));
        $uid = 'dgptm-efn-barcode-svg-'.uniqid();

        ob_start(); ?>
        <div class="dgptm-efn-barcode" data-efn="<?php echo esc_attr($efn); ?>" data-width="<?php echo esc_attr($width); ?>" data-height="<?php echo esc_attr($height); ?>" style="text-align:center;margin:12px 0;">
            <div class="dgptm-efn-barcode-inner" style="display:none;">
                <svg id="<?php echo esc_attr($uid); ?>"></svg>
                <div class="dgptm-efn-barcode-text" style="font:600 14px/1.3 system-ui,Segoe UI,Roboto,Arial,sans-serif;margin-top:6px;"></div>
            </div>
            <?php if (!$efn): ?>
                <small style="color:#b00;">EFN nicht verfügbar.</small>
            <?php endif; ?>
        </div>
        <script>
        (function(){
            const wrap   = document.currentScript.previousElementSibling;
            if(!wrap) return;
            const efn    = (wrap.getAttribute('data-efn') || '').trim();
            const width  = parseInt(wrap.getAttribute('data-width')  || '280', 10);
            const height = parseInt(wrap.getAttribute('data-height') || '70',  10);
            if (!/^[0-9]{15}$/.test(efn)) return;

            const inner  = wrap.querySelector('.dgptm-efn-barcode-inner');
            const svg    = inner && inner.querySelector('svg');
            const textEl = inner && inner.querySelector('.dgptm-efn-barcode-text');
            if (!inner || !svg || !textEl) return;

            function render(){
                try {
                    window.JsBarcode(svg, efn, {
                        format: "CODE128",
                        width: 2,
                        height: height,
                        displayValue: false,
                        margin: 0
                    });
                    svg.setAttribute('width',  width);
                    svg.setAttribute('height', height);
                    svg.style.width  = width + 'px';
                    svg.style.height = height + 'px';

                    textEl.textContent = 'EFN: ' + efn;
                    inner.style.display = 'inline-block';
                } catch(e) {
                    console.error('Barcode render error', e);
                }
            }

            function ensureLib(cb){
                if (window.JsBarcode) { cb(); return; }
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js';
                s.async = true;
                s.onload = cb;
                s.onerror = function(){ console.error('JsBarcode laden fehlgeschlagen'); };
                document.head.appendChild(s);
            }

            ensureLib(render);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

/* ============================================================
 * EFN-Feld im Benutzerprofil
 * ============================================================ */
add_action( 'show_user_profile', 'dgptm_efn_user_profile_field' );
add_action( 'edit_user_profile', 'dgptm_efn_user_profile_field' );
function dgptm_efn_user_profile_field( $user ) {
    if ( ! current_user_can( 'edit_user', $user->ID ) ) return;
    $efn = get_user_meta( $user->ID, 'EFN', true ); ?>
    <h2>EFN (Einheitliche Fortbildungsnummer)</h2>
    <table class="form-table">
        <tr>
            <th><label for="dgptm_efn">EFN</label></th>
            <td>
                <input type="text" name="dgptm_efn" id="dgptm_efn" class="regular-text" value="<?php echo esc_attr( $efn ); ?>" />
                <p class="description">15-stellige Einheitliche Fortbildungsnummer. Wird für Zoho CRM-Abfrage und Zertifikate verwendet.</p>
                <?php if ( get_current_user_id() === (int)$user->ID ) : ?>
                    <p><button type="button" class="button" id="dgptm-efn-fetch-btn">EFN aus Zoho übernehmen</button>
                    <span id="dgptm-efn-fetch-msg" style="margin-left:10px;"></span></p>
                    <script>
                    jQuery(function($){
                        $('#dgptm-efn-fetch-btn').on('click', function(e){
                            e.preventDefault();
                            $('#dgptm-efn-fetch-msg').text('Lade…');
                            $.post(ajaxurl, { action: 'dgptm_efn_fetch_from_zoho' }, function(resp){
                                if(resp && resp.success){
                                    $('#dgptm_efn').val(resp.data.efn || '');
                                    $('#dgptm-efn-fetch-msg').text('Übernommen.');
                                } else {
                                    $('#dgptm-efn-fetch-msg').text('Keine EFN gefunden.');
                                }
                            }).fail(function(){ $('#dgptm-efn-fetch-msg').text('Fehler beim Abruf.'); });
                        });
                    });
                    </script>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'personal_options_update', 'dgptm_efn_user_profile_save' );
add_action( 'edit_user_profile_update', 'dgptm_efn_user_profile_save' );
function dgptm_efn_user_profile_save( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) return;
    if ( isset( $_POST['dgptm_efn'] ) ) {
        $val = preg_replace('/\D+/', '', (string) wp_unslash( $_POST['dgptm_efn'] ) );
        update_user_meta( $user_id, 'EFN', $val );
    }
}

add_action( 'wp_ajax_dgptm_efn_fetch_from_zoho', function(){
    if ( ! is_user_logged_in() ) wp_send_json_error();
    $efn_raw = do_shortcode('[zoho_api_data field="EFN"]');
    $efn     = preg_replace('/\D+/', '', (string) $efn_raw);
    if ( $efn ) { update_user_meta( get_current_user_id(), 'EFN', $efn ); wp_send_json_success( array( 'efn' => $efn ) ); }
    wp_send_json_error();
});

/* ============================================================
 * EFN Autofill beim Login (einmalig)
 * ============================================================ */
add_action('init', function(){
    if ( ! is_user_logged_in() ) return;
    $autofill = dgptm_efn_get_setting('dgptm_efn_autofill_on_init', '1');
    if ( $autofill === '1' ) {
        $current = (string) get_user_meta( get_current_user_id(), 'EFN', true );
        if ( $current === '' ) {
            $efn_raw = do_shortcode('[zoho_api_data field="EFN"]');
            $efn     = preg_replace('/\D+/', '', (string) $efn_raw);
            if ( $efn ) update_user_meta( get_current_user_id(), 'EFN', $efn );
        }
    }
});

/* ============================================================
 * Admin-Einstellungen: EFN Manager
 * ============================================================ */
add_action('admin_menu', function () {
    add_options_page(
        'EFN Manager',
        'EFN Manager',
        'manage_options',
        'dgptm-efn-manager',
        'dgptm_render_efn_manager_settings_page'
    );
});

add_action('admin_init', function () {
    /* Allgemein/Kiosk/Offsets/Footer */
    $general_group = 'dgptm_efn_manager_general';

    register_setting($general_group, 'dgptm_kiosk_webhook', array('type'=>'string','sanitize_callback'=>'esc_url_raw'));
    register_setting($general_group, 'dgptm_kiosk_mode', array(
        'type'=>'string',
        'sanitize_callback'=>function($v){ return in_array($v,array('browser','printnode'),true)?$v:'browser'; }
    ));
    register_setting($general_group, 'dgptm_default_template', array('type'=>'string','sanitize_callback'=>'sanitize_text_field'));
    register_setting($general_group, 'dgptm_kiosk_template', array('type'=>'string','sanitize_callback'=>'sanitize_text_field'));
    register_setting($general_group, 'dgptm_debug_default', array(
        'type'=>'string',
        'sanitize_callback'=>function($v){ return ($v==='yes')?'yes':'no'; }
    ));
    register_setting($general_group, 'dgptm_efn_autofill_on_init', array(
        'type'=>'string',
        'sanitize_callback'=>function($v){ return ($v==='1')?'1':'0'; }
    ));

    // Vertikale 2-Punkt-Kalibrierung
    register_setting($general_group, 'dgptm_kiosk_top_correction_mm', array('type'=>'number','sanitize_callback'=>function($v){ return (float)$v; }));
    register_setting($general_group, 'dgptm_kiosk_bottom_correction_mm', array('type'=>'number','sanitize_callback'=>function($v){ return (float)$v; }));

    // Horizontale Rand-Kalibrierung
    register_setting($general_group, 'dgptm_kiosk_left_correction_mm',  array('type'=>'number','sanitize_callback'=>function($v){ return (float)$v; }));
    register_setting($general_group, 'dgptm_kiosk_right_correction_mm', array('type'=>'number','sanitize_callback'=>function($v){ return (float)$v; }));

    // Footer
    register_setting($general_group, 'dgptm_footer_from_bottom_mm', array('type'=>'number','sanitize_callback'=>function($v){ return (float)$v; }));
    register_setting($general_group, 'dgptm_footer_show', array(
        'type'=>'string',
        'sanitize_callback'=>function($v){ return ($v==='no')?'no':'yes'; }
    ));

    /* PrintNode */
    $printnode_group = 'dgptm_efn_manager_printnode';
    register_setting($printnode_group, 'dgptm_printnode_api_key', array('type'=>'string','sanitize_callback'=>'sanitize_text_field'));
    register_setting($printnode_group, 'dgptm_printnode_printer_id', array('type'=>'integer','sanitize_callback'=>'intval'));
});

function dgptm_render_efn_manager_settings_page() {
    if (!current_user_can('manage_options')) return;

    $templates = array(
        'Zweckform L6011 (63.5×33.9, 3×8)',
        'Avery Zweckform 3667 (48.5×16.9, 4×16)',
        'LabelIdent EBL048X017PP (48,5×16,9, 4×16)',
        'Zweckform L6021 (70×37, 3×8)',
        'Avery L7160 (63.5×38.1, 3×7)',
        'Avery L7563 (99.1×38.1, 2×7)',
        'Zweckform L6021REV-25 (45.7×16.9, 4×16)',
    );

    $api = esc_attr( dgptm_efn_get_setting('dgptm_printnode_api_key','') );
    $pid = intval( dgptm_efn_get_setting('dgptm_printnode_printer_id',0) );
    $wh  = esc_url( dgptm_efn_get_setting('dgptm_kiosk_webhook','') );
    $md  = dgptm_efn_get_setting('dgptm_kiosk_mode','browser');
    $tpl = dgptm_efn_get_setting('dgptm_default_template','LabelIdent EBL048X017PP (48,5×16,9, 4×16)');
    $dbg = dgptm_efn_get_setting('dgptm_debug_default','no');
    $autofill = dgptm_efn_get_setting('dgptm_efn_autofill_on_init','1');

    $tplKiosk   = dgptm_efn_get_setting('dgptm_kiosk_template', $tpl);

    $topCorr    = dgptm_efn_get_setting('dgptm_kiosk_top_correction_mm', -5.0);
    $botCorr    = dgptm_efn_get_setting('dgptm_kiosk_bottom_correction_mm',  5.0);
    $leftCorr   = dgptm_efn_get_setting('dgptm_kiosk_left_correction_mm',  -5.0);
    $rightCorr  = dgptm_efn_get_setting('dgptm_kiosk_right_correction_mm',  5.0);

    $footerDist = dgptm_efn_get_setting('dgptm_footer_from_bottom_mm', 7.0);
    $footerShow = dgptm_efn_get_setting('dgptm_footer_show', 'yes');

    // Notices für Testdruck
    if (!empty($_GET['dgptm_printnode_test'])) {
        if ($_GET['dgptm_printnode_test']==='ok') {
            echo '<div class="updated"><p>PrintNode-Testdruck wurde an Printer ID '.intval($pid).' gesendet.</p></div>';
        } else {
            $err = esc_html($_GET['dgptm_printnode_test']);
            echo '<div class="error"><p>PrintNode-Test fehlgeschlagen: '.$err.'</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>EFN Manager – Einstellungen</h1>
        <p>Zentrale Konfiguration für alle EFN-Funktionalitäten (Barcodes, Label-Sheets, Kiosk, PrintNode, Benutzerprofil).</p>

        <!-- Allgemein/Kiosk/Offsets/Footer -->
        <form method="post" action="options.php">
            <?php settings_fields('dgptm_efn_manager_general'); ?>

            <h2 class="title">Allgemeine Einstellungen</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">EFN Autofill beim Login</th>
                    <td>
                        <label><input type="radio" name="dgptm_efn_autofill_on_init" value="1" <?php checked($autofill,'1'); ?> /> Aktiviert</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="dgptm_efn_autofill_on_init" value="0" <?php checked($autofill,'0'); ?> /> Deaktiviert</label>
                        <p class="description">Wenn aktiviert, wird die EFN beim ersten Login automatisch aus Zoho übernommen (falls leer).</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Kiosk-System</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="dgptm_kiosk_webhook">Kiosk Webhook URL (Zoho Functions)</label></th>
                    <td>
                        <input type="url" name="dgptm_kiosk_webhook" id="dgptm_kiosk_webhook" class="regular-text code" value="<?php echo $wh; ?>" placeholder="https://www.zohoapis.eu/crm/v7/functions/.../actions/execute?auth_type=apikey&amp;zapikey=..." />
                        <p class="description">POST JSON <code>{"arguments":{"code":"..."}}</code>. Antwort: <code>details.output</code> mit <code>statusefn</code>, <code>messageefn</code>, <code>name</code>, <code>efn</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Kiosk Modus (Servervorgabe)</th>
                    <td>
                        <label><input type="radio" name="dgptm_kiosk_mode" value="browser" <?php checked($md,'browser'); ?> /> Browser (Chrome <code>--kiosk-printing</code>)</label><br>
                        <label><input type="radio" name="dgptm_kiosk_mode" value="printnode" <?php checked($md,'printnode'); ?> /> PrintNode (Silent Printing)</label>
                        <p class="description">PrintNode-Modus wird serverseitig erzwungen, wenn API-Key & Printer-ID gesetzt sind.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dgptm_kiosk_template">Kiosk Vorlage</label></th>
                    <td>
                        <select name="dgptm_kiosk_template" id="dgptm_kiosk_template">
                            <?php foreach($templates as $t): ?>
                                <option value="<?php echo esc_attr($t); ?>" <?php selected($tplKiosk,$t); ?>><?php echo esc_html($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Standard-Vorlage für <code>[efn_kiosk]</code> (Shortcode-Attribut übersteuert).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="dgptm_default_template">Standard-Vorlage (Download)</label></th>
                    <td>
                        <select name="dgptm_default_template" id="dgptm_default_template">
                            <?php foreach($templates as $t): ?>
                                <option value="<?php echo esc_attr($t); ?>" <?php selected($tpl,$t); ?>><?php echo esc_html($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Default für <code>[efn_label_sheet]</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Debug (Standard)</th>
                    <td>
                        <label><input type="radio" name="dgptm_debug_default" value="no"  <?php checked($dbg,'no');  ?> /> Nein</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="dgptm_debug_default" value="yes" <?php checked($dbg,'yes'); ?> /> Ja</label>
                    </td>
                </tr>
            </table>

            <h2 class="title">Druckkalibierung</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="dgptm_kiosk_top_correction_mm">Oberste Reihe (mm)</label></th>
                    <td>
                        <input type="number" step="0.1" name="dgptm_kiosk_top_correction_mm" id="dgptm_kiosk_top_correction_mm" class="small-text" value="<?php echo esc_attr($topCorr); ?>" />
                        <p class="description">Negativ = nach oben, positiv = nach unten. Standard: −5,0 mm</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dgptm_kiosk_bottom_correction_mm">Unterste Reihe (mm)</label></th>
                    <td>
                        <input type="number" step="0.1" name="dgptm_kiosk_bottom_correction_mm" id="dgptm_kiosk_bottom_correction_mm" class="small-text" value="<?php echo esc_attr($botCorr); ?>" />
                        <p class="description">Positiv = nach unten, negativ = nach oben. Standard: +5,0 mm</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dgptm_kiosk_left_correction_mm">Linke Spalte (mm)</label></th>
                    <td>
                        <input type="number" step="0.1" name="dgptm_kiosk_left_correction_mm" id="dgptm_kiosk_left_correction_mm" class="small-text" value="<?php echo esc_attr($leftCorr); ?>" />
                        <p class="description">Negativ = nach links, positiv = nach rechts. Standard: −5,0 mm</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dgptm_kiosk_right_correction_mm">Rechte Spalte (mm)</label></th>
                    <td>
                        <input type="number" step="0.1" name="dgptm_kiosk_right_correction_mm" id="dgptm_kiosk_right_correction_mm" class="small-text" value="<?php echo esc_attr($rightCorr); ?>" />
                        <p class="description">Negativ = nach links, positiv = nach rechts. Standard: +5,0 mm</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Footer-Einstellungen</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Footer anzeigen</th>
                    <td>
                        <label><input type="radio" name="dgptm_footer_show" value="yes" <?php checked($footerShow,'yes'); ?> /> Ja</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="dgptm_footer_show" value="no"  <?php checked($footerShow,'no');  ?> /> Nein</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dgptm_footer_from_bottom_mm">Abstand vom unteren Rand (mm)</label></th>
                    <td>
                        <input type="number" step="0.1" name="dgptm_footer_from_bottom_mm" id="dgptm_footer_from_bottom_mm" class="small-text" value="<?php echo esc_attr($footerDist); ?>" />
                        <p class="description">Unterkante Footer-Text zum Papierrand. Standard: 7,0 mm</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Einstellungen speichern'); ?>
        </form>

        <!-- PrintNode -->
        <h2 class="title">PrintNode Silent Printing</h2>
        <form method="post" action="options.php">
            <?php settings_fields('dgptm_efn_manager_printnode'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="dgptm_printnode_api_key">PrintNode API Key</label></th>
                    <td>
                        <input type="text" name="dgptm_printnode_api_key" id="dgptm_printnode_api_key" class="regular-text" value="<?php echo $api; ?>" placeholder="PN-XXXXXXXX:YYYYYYYYYYYYYYYYYYYY" />
                        <p class="description">Von <a href="https://app.printnode.com/" target="_blank">PrintNode Dashboard</a> (Account → API Keys).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dgptm_printnode_printer_id">Printer ID</label></th>
                    <td>
                        <input type="number" name="dgptm_printnode_printer_id" id="dgptm_printnode_printer_id" class="small-text" value="<?php echo $pid; ?>" />
                        <p class="description">Drucker-ID aus PrintNode (API: <code>/printers</code>).</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('PrintNode-Einstellungen speichern'); ?>
        </form>

        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin-top:20px;">
            <?php wp_nonce_field('dgptm_printnode_test'); ?>
            <input type="hidden" name="action" value="dgptm_printnode_test">
            <button type="submit" class="button button-secondary">PrintNode-Testdruck senden</button>
            <p class="description">Sendet eine Test-PDF an die konfigurierte Printer ID – unabhängig vom Browser/Kiosk.</p>
        </form>
    </div>
    <?php
}

/** Admin-Handler: PrintNode Selbsttest */
add_action('admin_post_dgptm_printnode_test', function(){
    if (!current_user_can('manage_options')) wp_die('Not allowed');
    check_admin_referer('dgptm_printnode_test');

    $api_key   = trim(dgptm_efn_get_setting('dgptm_printnode_api_key',''));
    $printer_id= intval(dgptm_efn_get_setting('dgptm_printnode_printer_id', 0));
    if (!$api_key || !$printer_id) {
        wp_safe_redirect( add_query_arg('dgptm_printnode_test', rawurlencode('API-Key/Printer-ID fehlen'), admin_url('options-general.php?page=dgptm-efn-manager')) ); exit;
    }

    $fpdf_path = DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php';
    if (!file_exists($fpdf_path)) {
        wp_safe_redirect( add_query_arg('dgptm_printnode_test', rawurlencode('FPDF nicht gefunden'), admin_url('options-general.php?page=dgptm-efn-manager')) ); exit;
    }
    require_once $fpdf_path;
    $pdf = new FPDF('P','mm','A4');
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $pdf->SetFont('Helvetica','B',16);
    $pdf->Cell(0,10,'DGPTM PrintNode – Testdruck',0,1,'C');
    $pdf->SetFont('Helvetica','',12);
    $pdf->Ln(4);
    $pdf->MultiCell(0,7,"Wenn dieses Blatt gedruckt wird, ist PrintNode korrekt konfiguriert.\nDatum: ".date('Y-m-d H:i:s'));
    $upload = wp_upload_dir();
    $path = trailingslashit($upload['basedir']).'dgptm_printnode_test.pdf';
    $pdf->Output('F', $path);
    $data = @file_get_contents($path);
    @unlink($path);

    if ($data===false) {
        wp_safe_redirect( add_query_arg('dgptm_printnode_test', rawurlencode('PDF konnte nicht gelesen werden'), admin_url('options-general.php?page=dgptm-efn-manager')) ); exit;
    }

    $payload = array(
        "printerId"   => $printer_id,
        "title"       => "DGPTM PrintNode Test",
        "contentType" => "pdf_base64",
        "content"     => base64_encode($data),
        "source"      => "DGPTM-EFN-Manager-Settings"
    );

    $r = wp_remote_post('https://api.printnode.com/printjobs', array(
        'headers' => array(
            'Authorization' => 'Basic '.base64_encode($api_key.':'),
            'Content-Type'  => 'application/json'
        ),
        'body'    => wp_json_encode($payload),
        'timeout' => 20,
    ));

    if (is_wp_error($r)) {
        wp_safe_redirect( add_query_arg('dgptm_printnode_test', rawurlencode('HTTP-Fehler: '.$r->get_error_message()), admin_url('options-general.php?page=dgptm-efn-manager')) ); exit;
    }
    $codeHttp = wp_remote_retrieve_response_code($r);
    if ($codeHttp < 200 || $codeHttp >= 300) {
        wp_safe_redirect( add_query_arg('dgptm_printnode_test', rawurlencode('PrintNode HTTP '.$codeHttp.': '.wp_remote_retrieve_body($r)), admin_url('options-general.php?page=dgptm-efn-manager')) ); exit;
    }
    wp_safe_redirect( add_query_arg('dgptm_printnode_test', 'ok', admin_url('options-general.php?page=dgptm-efn-manager')) ); exit;
});

/* ============================================================
 * Initialisierung
 * ============================================================ */
DGPTM_EFN_Labels::instance();
