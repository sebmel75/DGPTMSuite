<?php
/**
 * Plugin Name: DGPTM - Fortbildung Liste und Quiz Importer Plugin
 * Plugin URI:  https://example.com
 * Description: Zeigt eingeloggten Mitgliedern eine Liste ihrer Fortbildungen an, importiert täglich (oder per Button) bestandene Quiz-Reports als Fortbildungseinträge (Typ "Quiz") und ermöglicht per Button einen Fortbildungsnachweis via FPDF zu erstellen (Brief-Layout, PNG‑Logo, optionaler Vorlagen‑Hintergrund, QR‑Code/Short‑Code mit Verifikation & 365‑Tage‑Hinweis). Zusätzlich: Einstellungen & Import für Ärztekammer/EIV (EFN/VNR), Mapping Veranstaltungsarten→EBCP-Punkte, Cron‑Intervall, EFN-Autofill, AJAX‑Statusausgabe, Doubletten‑Bereinigung (auch automatisch im Cron nach Quiz‑Import), Admin-/Frontend‑Übersichten inkl. Delegierten‑Ansicht, tägliches Löschen abgelaufener Zertifikate (>365 Tage), neue Verifizierungsseite /verify/ mit 8‑stelligen Codes, konfigurierbarer E‑Mail‑Versand, Rollensteuerung für den Nachweis‑Button.
 * Version:     1.57
 * Author:      Seb
 * Author URI:  https://example.com
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 * Hilfsfunktionen
 * ============================================================ */

if ( ! function_exists( 'fobi_format_date' ) ) {
    /**
     * Formatiert ein Datum (z.B. aus ACF 'date') robust nach d.m.Y.
     * Akzeptiert 'Y-m-d', 'd.m.Y', 'Ymd' und Unix-Timestamps.
     */
    function fobi_format_date( $raw ) {
        if ( $raw === null ) return '';
        if ( is_numeric( $raw ) ) {
            $ts = (int) $raw;
            if ( $ts > 0 ) return date_i18n( 'd.m.Y', $ts );
        }
        $raw = (string) $raw;
        $raw = trim( $raw );
        if ( $raw === '' ) return '';
        $formats = array( 'Y-m-d', 'd.m.Y', 'Ymd' );
        foreach ( $formats as $fmt ) {
            $dt = DateTime::createFromFormat( $fmt, $raw );
            if ( $dt instanceof DateTime ) {
                return date_i18n( 'd.m.Y', $dt->getTimestamp() );
            }
        }
        // letzter Versuch via strtotime (kann locale-abhängig sein)
        $ts = strtotime( $raw );
        if ( $ts ) return date_i18n( 'd.m.Y', $ts );
        return $raw;
    }
}


// Mehrrollen-Check
if ( ! function_exists('fobi_user_has_any_role') ) {
    function fobi_user_has_any_role( $role_slugs ) {
        if ( empty($role_slugs) ) return false;
        $user  = wp_get_current_user();
        $uroles = (array) $user->roles;
        $need = is_array($role_slugs) ? $role_slugs : array_map('trim', explode(',', (string)$role_slugs));
        $need = array_filter(array_map('sanitize_key', $need));
        foreach ($need as $r) {
            if ( in_array($r, $uroles, true) ) return true;
        }
        return false;
    }
}


if ( ! function_exists( 'fobi_is_freigegeben' ) ) {
    function fobi_is_freigegeben( $val ) {
        if ( $val === true || $val === 1 || $val === '1' ) return true;
        if ( is_string( $val ) ) { $v = strtolower( trim( $val ) ); return in_array( $v, array( 'ja', 'true', '1' ), true ); }
        return false;
    }
}
if ( ! function_exists( 'fobi_display_ja_nein' ) ) {
    function fobi_display_ja_nein( $val ) { return fobi_is_freigegeben( $val ) ? 'Ja' : 'Nein'; }
}
if ( ! function_exists( 'fobi_arr_get' ) ) {
    function fobi_arr_get( $array, $key, $default = '' ) { return ( is_array( $array ) && array_key_exists( $key, $array ) ) ? $array[ $key ] : $default; }
}
/** FPDF‑kompatibler Text (ISO‑8859‑1) */
if ( ! function_exists( 'fobi_pdf_text' ) ) {
    function fobi_pdf_text( $s ) {
        $s = (string) $s;
        if ( function_exists('iconv') ) {
            $t = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
            if ( $t !== false ) return $t;
        }
        return utf8_decode( $s );
    }
}
/** Bildprüfung */
if ( ! function_exists( 'fobi_is_valid_image' ) ) {
    function fobi_is_valid_image( $path, $allowed = array('image/png','image/jpeg') ) {
        if ( ! $path || ! file_exists( $path ) ) return false;
        $info = @getimagesize( $path );
        if ( ! $info || ! isset($info['mime']) ) return false;
        return in_array( $info['mime'], $allowed, true );
    }
}
/** PNG auf 8‑Bit normalisieren */
if ( ! function_exists('fobi_normalize_png_to_8bit') ) {
    function fobi_normalize_png_to_8bit( $src_path, $dest_path = '' ) {
        if ( ! $src_path || ! file_exists($src_path) ) return false;
        if ( $dest_path === '' ) $dest_path = $src_path;

        if ( class_exists('Imagick') ) {
            try {
                $im = new Imagick();
                $im->readImage($src_path);
                $im->setImageColorspace(Imagick::COLORSPACE_RGB);
                $im->setImageBackgroundColor(new ImagickPixel('white'));
                if (method_exists($im,'setImageAlphaChannel')) $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                else $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                if (method_exists($im,'setImageDepth')) $im->setImageDepth(8);
                if (method_exists($im,'setImageType'))  $im->setImageType(Imagick::IMGTYPE_TRUECOLOR);
                $im->setImageFormat('png');
                if (method_exists($im,'quantizeImage')) $im->quantizeImage(256, Imagick::COLORSPACE_RGB, 0, false, false);
                $im->writeImage($dest_path);
                $im->clear(); $im->destroy();
                return file_exists($dest_path);
            } catch (\Exception $e) { /* GD-Fallback unten */ }
        }
        if ( function_exists('imagecreatefrompng') && function_exists('imagecreatetruecolor') ) {
            $src = @imagecreatefrompng($src_path);
            if ( ! $src ) return false;
            $w = imagesx($src); $h = imagesy($src);
            $dst = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $w, $h, $white);
            imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
            $ok = imagepng($dst, $dest_path, 6);
            imagedestroy($src); imagedestroy($dst);
            return $ok && file_exists($dest_path);
        }
        return false;
    }
}
/** QR erzeugen (phpqrcode → Fallback Google Charts) */
if ( ! function_exists( 'fobi_generate_qr_png' ) ) {
    function fobi_generate_qr_png( $text, $dest ) {
        if ( ! $text || ! $dest ) return false;
        $qr_lib = plugin_dir_path( __FILE__ ) . 'lib/phpqrcode/qrlib.php';
        if ( file_exists( $qr_lib ) ) {
            require_once $qr_lib;
            if ( class_exists('QRcode') ) {
                try {
                    \QRcode::png( $text, $dest, QR_ECLEVEL_M, 4, 1 );
                    fobi_normalize_png_to_8bit($dest);
                    return fobi_is_valid_image( $dest, array('image/png') );
                } catch ( \Throwable $e ) {}
            }
        }
        $url = 'https://chart.googleapis.com/chart?cht=qr&chs=180x180&chl=' . rawurlencode( $text );
        $resp = wp_remote_get( $url, array( 'timeout' => 15 ) );
        if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
            @file_put_contents( $dest, wp_remote_retrieve_body( $resp ) );
            fobi_normalize_png_to_8bit($dest);
            return fobi_is_valid_image( $dest, array('image/png') );
        }
        return false;
    }
}
/** String-Helfer */
if ( ! function_exists('fobi_ensure_trailing_slash') ) {
    function fobi_ensure_trailing_slash($url){
        return rtrim($url, "/") . "/";
    }
}
/** 8‑stelligen Verifizierungscode erzeugen (A‑Z, 2‑9 ohne I/O/0/1) */
if ( ! function_exists('fobi_generate_verify_code') ) {
    function fobi_generate_verify_code( $len = 8 ) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max   = strlen($chars) - 1;
        $code  = '';
        for($i=0;$i<$len;$i++){ $code .= $chars[random_int(0, $max)]; }
        return $code;
    }
}
/** Einmalige Code‑Generierung mit Eindeutigkeit über fobi_certificate */
if ( ! function_exists('fobi_generate_unique_verify_code') ) {
    function fobi_generate_unique_verify_code( $len = 8, $max_tries = 10 ) {
        for($t=0;$t<$max_tries;$t++){
            $code = fobi_generate_verify_code($len);
            $q = new WP_Query(array(
                'post_type'=>'fobi_certificate','post_status'=>'any','posts_per_page'=>1,'fields'=>'ids',
                'meta_query'=>array(array('key'=>'verify_code','value'=>$code,'compare'=>'=')),
            ));
            if( ! $q->have_posts() ) return $code;
        }
        return $code; // letzter Versuch
    }
}
/** Benutzer hat Rolle? */
if ( ! function_exists('fobi_user_has_role') ) {
    function fobi_user_has_role( $role_slug ) {
        if ( empty($role_slug) ) return false;
        $u = wp_get_current_user();
        $roles = (array) $u->roles;
        return in_array( $role_slug, $roles, true );
    }
}
/** E‑Mail‑Template‑Ersetzung */
if ( ! function_exists('fobi_mail_tpl') ) {
    function fobi_mail_tpl( $tpl, $tokens ) {
        $repl = array();
        foreach ( $tokens as $k=>$v ) $repl['{'.$k.'}'] = $v;
        return strtr( (string)$tpl, $repl );
    }
}

/* ============================================================
 * Konstanten & Defaults
 * ============================================================ */
define( 'FOBI_AEK_OPTION_KEY', 'fobi_aek_settings' );
define( 'FOBI_AEK_CRON_HOOK',  'fobi_aek_batch_event' );
define( 'FOBI_CERT_CLEANUP_HOOK', 'fobi_certificate_cleanup_daily' );

function fobi_aek_default_settings() {
    return array(
        // API
        'access_token'        => '',
        'extra_header_key'    => '',
        'extra_header_value'  => '',
        'scans_endpoint_tpl'  => 'https://api.example.eiv/veranstalter/v1/scans?efn={EFN}',
        'event_endpoint_tpl'  => 'https://api.example.eiv/veranstalter/v1/events/{VNR}',

        // Mapping
        'mapping_json'        => json_encode(array(
            array('code'=>'A','label'=>'Vortragsveranstaltung','calc'=>'unit','points'=>1,'unit_minutes'=>45),
            array('code'=>'B','label'=>'Kongress','calc'=>'fixed','points'=>3),
            array('code'=>'C','label'=>'Kleingruppe/Workshop','calc'=>'unit','points'=>1,'unit_minutes'=>45),
            array('code'=>'D','label'=>'Print/elektronisch + LEK','calc'=>'unit','points'=>1,'unit_minutes'=>45),
            array('code'=>'G','label'=>'Hospitation','calc'=>'per_hour','points'=>1),
            array('code'=>'H','label'=>'Curricula BÄK','calc'=>'unit','points'=>1,'unit_minutes'=>45),
            array('code'=>'I','label'=>'eLearning (LEK)','calc'=>'unit','points'=>1,'unit_minutes'=>45),
            array('code'=>'K','label'=>'Blended Learning','calc'=>'unit','points'=>1,'unit_minutes'=>45),
        )),

        // Batch
        'batch_enabled'        => '1',
        'batch_interval'       => 'daily',
        'allow_member_refresh' => '0',

        // PDF / Logo / Template / QR / Button
        'pdf_logo_attachment_id'     => 0,   // PNG
        'pdf_template_attachment_id' => 0,   // PDF (optional; Seite 1 als Hintergrund)
        'qr_verify_base'             => '',  // leer => /verify/
        'pdf_sender_name'            => get_bloginfo( 'name' ),
        'pdf_sender_email'           => get_bloginfo( 'admin_email' ),
        'enable_certificate_button'  => '1',
        'certificate_button_roles'   => array('administrator'),

        // E‑Mail
        'email_enabled'      => '1',
        'email_subject_tpl'  => 'Ihr Fortbildungsnachweis {period_label}',
        'email_body_tpl'     => "Guten Tag {name},\n\nim Anhang finden Sie Ihren Fortbildungsnachweis ({period_label}).\nVerifikation: {verify_url} – Code: {verify_code}\n\nMit freundlichen Grüßen\n{site_name}",
        'email_attach_pdf'   => '1',
    );
}

/* ============================================================
 * CPTs: fortbildung & fobi_certificate
 * ============================================================ */
class Fortbildung_Liste_Plugin {
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_shortcode( 'fortbildung_liste', array( $this, 'display_fortbildung_list' ) );
    }
    public function register_post_types() {
        if ( ! post_type_exists( 'fortbildung' ) ) {
            register_post_type( 'fortbildung', array(
                'labels' => array(
                    'name'=>'Fortbildungen','singular_name'=>'Fortbildung','menu_name'=>'Fortbildungen',
                    'add_new'=>'Neu hinzufügen','add_new_item'=>'Neue Fortbildung hinzufügen','new_item'=>'Neue Fortbildung',
                    'edit_item'=>'Fortbildung bearbeiten','view_item'=>'Fortbildung anzeigen','all_items'=>'Alle Fortbildungen',
                    'search_items'=>'Fortbildungen suchen','not_found'=>'Keine Fortbildungen gefunden','not_found_in_trash'=>'Keine Fortbildungen im Papierkorb gefunden',
                ),
                'public'=>false,'publicly_queryable'=>false,'show_ui'=>true,'show_in_menu'=>true,'query_var'=>false,'rewrite'=>false,'capability_type'=>'post','has_archive'=>false,'hierarchical'=>false,'supports'=>array('title','custom-fields'),'show_in_rest'=>true,
            ));
        }
        if ( ! post_type_exists( 'fobi_certificate' ) ) {
            register_post_type( 'fobi_certificate', array(
                'labels'=>array('name'=>'Nachweise','singular_name'=>'Nachweis','menu_name'=>'Nachweise','add_new_item'=>'Neuen Nachweis anlegen','edit_item'=>'Nachweis bearbeiten','all_items'=>'Alle Nachweise'),
                'public'=>false,'publicly_queryable'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=fortbildung','supports'=>array('title','custom-fields'),'show_in_rest'=>false,
            ));
        }
    }


	public function display_fortbildung_list( $atts ) {
    if ( ! is_user_logged_in() ) return '<p>Bitte loggen Sie sich ein, um Ihre Fortbildungen zu sehen.</p>';

    $settings       = wp_parse_args( get_option( FOBI_AEK_OPTION_KEY, array() ), fobi_aek_default_settings() );
    $btn_enabled    = ($settings['enable_certificate_button'] === '1');
    $required_roles = (array)($settings['certificate_button_roles'] ?? array('administrator'));
    $user_can_btn   = fobi_user_has_any_role( $required_roles );

    $uid          = get_current_user_id();
    $current_year = date('Y');

    // Letzten Nachweis anzeigen
    $last_download_html = '';
    $btn_label = 'Fortbildungsnachweis erstellen';
    $last = new WP_Query(array(
        'post_type'=>'fobi_certificate','post_status'=>'publish','meta_key'=>'user_id','meta_value'=>$uid,
        'orderby'=>'date','order'=>'DESC','posts_per_page'=>1,'fields'=>'ids'
    ));
    if ( $last->have_posts() ) {
        $cid = (int) $last->posts[0];
        $label = get_post_meta($cid,'period_label',true);
        $created = get_post_meta($cid,'created_at',true);
        $sig = get_post_meta($cid,'sig',true);
        $download_url = add_query_arg(array('cid'=>$cid,'sig'=>$sig), home_url('/dgptm-download/'));
        $btn_label = 'Nachweis neu erstellen';
        $last_download_html = '<div class="notice notice-success" style="padding:10px;margin-top:10px;"><strong>Letzter Nachweis:</strong> '.esc_html($label).' — erstellt am '.esc_html(date_i18n('d.m.Y H:i', strtotime($created))).' &nbsp; <a class="button" href="'.esc_url($download_url).'" target="_blank" rel="noopener">Download</a></div>';
    }

    ob_start(); ?>
    <script>(function(){ if(typeof window.ajaxurl==='undefined'){ window.ajaxurl='<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>'; }})();</script>

    <div style="margin-bottom:12px;">
        <label>Zeitraum:</label>
        <select id="fobi-year-from" style="margin-left:6px;">
            <?php for($y=$current_year-6;$y<=$current_year;$y++): ?>
                <option value="<?php echo esc_attr($y); ?>" <?php selected($y,$current_year); ?>><?php echo esc_html($y); ?></option>
            <?php endfor; ?>
        </select>
        <span>bis</span>
        <select id="fobi-year-to" style="margin-left:6px;">
            <?php for($y=$current_year-6;$y<=$current_year;$y++): ?>
                <option value="<?php echo esc_attr($y); ?>" <?php selected($y,$current_year); ?>><?php echo esc_html($y); ?></option>
            <?php endfor; ?>
        </select>
        <small style="margin-left:8px;color:#666;">Max. 3 Jahre</small>
    </div>

    <div id="fobi-list-container"><?php echo self::get_fortbildungen_table( $uid, $current_year, $current_year ); ?></div>

    <?php
    // Button-Attributes: nur Einstellungen/Rollen; Live-Sperre erfolgt per AJAX unten
    $disabled_attr = $title_attr = $style_attr = '';
    $hint_html = '<div style="margin-top:6px;color:#666;font-size:12px;line-height:1.3;"><em>Jeder Zeitraum kann nur einmal pro Kalendertag generiert werden.</em></div>';

    if ( ! $btn_enabled || ! $user_can_btn ) {
        $disabled_attr = ' disabled="disabled"';
        if ( ! $btn_enabled ) {
            $title_attr  = ' title="Funktion ist in den Einstellungen deaktiviert."';
        } else {
            $title_attr  = ' title="Sie haben nicht die erforderliche Rolle(n): ' . esc_attr( implode(', ', $required_roles) ) . '."';
        }
        $style_attr    = ' style="opacity:.5;cursor:not-allowed"';
    } else {
        $title_attr = ' title="Jeder Zeitraum kann nur einmal pro Kalendertag generiert werden."';
    }
    ?>

    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-top:16px;gap:12px;flex-wrap:wrap;">
        <div style="min-width:260px;">
            <button id="create-fobi-button" class="button button-primary"<?php echo $disabled_attr.$title_attr.$style_attr; ?>>
                <?php echo esc_html($btn_label); ?>
            </button>
            <div id="fobi-result" style="margin-top:8px;"><?php echo $last_download_html; ?></div>
            <?php echo $hint_html; ?>
        </div>
        <div style="max-width:560px;color:#555;">
            <em>Hinweis:</em> Der Nachweis kann ohne Unterschrift/Stempel bei der der Re-Zertifizierung eingereicht werden. Der EBCP-Delegierte kann zur Überprüfung alle Fortbildungsnachweise einsehen.
        </div>
    </div>

    <style>
    /* Mobile Karten + Desktop Tabelle */
    .fobi-list { display:grid; grid-template-columns:1fr; gap:12px; margin:0; padding:0; list-style:none; }
    .fobi-card { border:1px solid #ddd; border-radius:8px; padding:10px; background:#fff; }
    .fobi-row { display:grid; grid-template-columns:110px 1fr; row-gap:6px; column-gap:8px; font-size:14px; }
    .fobi-label { font-weight:600; color:#444; }
    .fobi-dim    { color:#666; }
    .fobi-points { text-align:right; font-weight:700; }
    .fobi-grey   { opacity:.7; }

    @media (min-width:768px){
        .fobi-desktop { display:block; }
        .fobi-mobile  { display:none !important; }
        .fortbildung-liste { width:100%; border-collapse:collapse; }
        .fortbildung-liste th, .fortbildung-liste td { border:1px solid #ccc; padding:8px; text-align:left; vertical-align:top; }
        .fortbildung-liste thead th { background:#f6f7f7; }
    }
    @media (max-width:767px){
        .fobi-desktop { display:none !important; }
        .fobi-mobile  { display:block; }
    }
    </style>

    <script>
    (function($){
        function clampRange(){
            var f=parseInt($('#fobi-year-from').val(),10), t=parseInt($('#fobi-year-to').val(),10);
            if(t<f){ t=f; $('#fobi-year-to').val(t); }
            if((t-f)>2){ t=f+2; $('#fobi-year-to').val(t); }
            return {from:f,to:t};
        }
        function reloadList(){
            var r=clampRange(); $('#fobi-list-container').html('Lade…');
            $.post(window.ajaxurl,{action:'fobi_filter_fortbildungen',from:r.from,to:r.to},function(resp){ $('#fobi-list-container').html(resp); });
        }

        // NEU: Live-Check, ob für den gewählten Zeitraum heute bereits ein Nachweis erzeugt wurde
        function updateCreateButtonState(){
            var $btn = $('#create-fobi-button');
            // Falls der Button ohnehin durch Rolle/Einstellung deaktiviert ist — nicht prüfen
            if ($btn.is(':disabled') && $btn.css('cursor') === 'not-allowed' && <?php echo ($btn_enabled && $user_can_btn) ? 'false' : 'true'; ?>) {
                return;
            }
            var r = clampRange();
            $.post(window.ajaxurl,{
                action:'fobi_can_create_today',
                from_year:r.from,
                to_year:r.to
            }, function(resp){
                if(resp && resp.success && resp.data){
                    if(resp.data.allowed){
                        $btn.prop('disabled', false).css({opacity:'',cursor:''})
                            .attr('title','Jeder Zeitraum kann nur einmal pro Kalendertag generiert werden.');
                    }else{
                        $btn.prop('disabled', true).css({opacity:.5,cursor:'not-allowed'})
                            .attr('title','Heute wurde für diesen Zeitraum bereits ein Nachweis erzeugt.');
                    }
                }
            });
        }

        $('#fobi-year-from,#fobi-year-to').on('change', function(){
            reloadList();
            updateCreateButtonState();
        });

        // Initial prüfen
        updateCreateButtonState();

        $('#create-fobi-button').on('click',function(e){
            if($(this).is(':disabled')){ return; }
            e.preventDefault(); var r=clampRange();
            $('#fobi-result').html('Fortbildungsnachweis wird erstellt…');
            $.ajax({url:window.ajaxurl,method:'POST',dataType:'json',data:{action:'create_fortbildungsnachweis',from_year:r.from,to_year:r.to}})
            .done(function(resp){
                if(resp && resp.success && resp.data){
                    $('#fobi-result').html('<strong>Nachweis erstellt.</strong>');
                    setTimeout(function(){ window.location.reload(); }, 800);
                } else {
                    var msg=(resp && resp.data && resp.data.message)?resp.data.message:'Fehler beim Erstellen des Nachweises.';
                    $('#fobi-result').html('<span style="color:#b00">'+msg+'</span>');
                    // Nach Fehler erneut Buttonzustand prüfen (z. B. Limit erreicht)
                    updateCreateButtonState();
                }
            }).fail(function(xhr){
                $('#fobi-result').html('Fehler ('+xhr.status+').');
                updateCreateButtonState();
            });
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
	
	
	

    public static function get_fortbildungen_table( $user_id, $from_year, $to_year ) {
        $args = array(
            'post_type'=>'fortbildung','post_status'=>'publish','posts_per_page'=>-1,
            'meta_query'=>array('relation'=>'AND',array('key'=>'user','value'=>$user_id,'compare'=>'=')),
        );
        $from_year=intval($from_year); $to_year=intval($to_year);
        if($from_year>0 && $to_year>0){
            $args['meta_query'][]=array('key'=>'date','value'=>array($from_year.'-01-01',$to_year.'-12-31'),'compare'=>'BETWEEN','type'=>'DATE');
        }
        $q=new WP_Query($args);
        if(!$q->have_posts()) return '<p>Keine Fortbildungen gefunden.</p>';

        // Mobile + Desktop
        ob_start(); ?>
        <!-- Mobile -->
        <div class="fobi-mobile">
            <ul class="fobi-list">
            <?php
            $total_points = 0;
            while ( $q->have_posts() ) {
                $q->the_post();
                $pid        = get_the_ID();
                $raw_date = (string) get_field('date', $pid);
$display_date = '';
if ($raw_date !== '') {
    $fmts = array('Y-m-d','d.m.Y','Ymd');
    foreach ($fmts as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw_date);
        if ($dt instanceof DateTime) { $ts = $dt->getTimestamp(); $display_date = date_i18n('d.m.Y', $ts); break; }
    }
}

                $title      = get_the_title( $pid );
                $location   = (string) get_field( 'location', $pid );
                $points     = floatval( get_field( 'points', $pid ) );
                $type       = (string) get_field( 'type', $pid );
                $free       = get_field( 'freigegeben', $pid );
                if ( fobi_is_freigegeben( $free ) ) $total_points += $points; ?>
                <li class="fobi-card<?php echo fobi_is_freigegeben($free) ? '' : ' fobi-grey'; ?>">
                    <div class="fobi-row"><div class="fobi-label">Datum</div><div><?php echo esc_html( $display_date ); ?></div></div>
                    <div class="fobi-row"><div class="fobi-label">Titel</div><div><?php echo esc_html( $title ); ?></div></div>
                    <div class="fobi-row"><div class="fobi-label">Ort</div><div class="fobi-dim"><?php echo esc_html( $location ); ?></div></div>
                    <div class="fobi-row"><div class="fobi-label">Punkte</div><div class="fobi-points"><?php echo esc_html( number_format($points,1,',','.') ); ?></div></div>
                    <div class="fobi-row"><div class="fobi-label">Art</div><div class="fobi-dim"><?php echo esc_html( $type ); ?></div></div>
                </li>
            <?php } ?>
            </ul>
            <p style="text-align:right; margin-top:10px;">
               EBCP Gesamtpunkte im Zeitraum: <strong><?php echo esc_html( number_format($total_points,1,',','.') ); ?></strong>
            </p>
        </div>

        <!-- Desktop -->
        <div class="fobi-desktop">
            <table class="fortbildung-liste">
                <thead><tr><th>Datum</th><th>Titel</th><th>Ort</th><th style="width:110px; text-align:right;">Punkte</th><th>Art</th></tr></thead>
                <tbody>
                <?php
                $q->rewind_posts();
                $total_points = 0;
                while ( $q->have_posts() ) {
                    $q->the_post();
                    $pid        = get_the_ID();
                    $raw_date = (string) get_field('date', $pid);
$display_date = '';
if ($raw_date !== '') {
    $fmts = array('Y-m-d','d.m.Y','Ymd');
    foreach ($fmts as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw_date);
        if ($dt instanceof DateTime) { $ts = $dt->getTimestamp(); $display_date = date_i18n('d.m.Y', $ts); break; }
    }
}

                    $title      = get_the_title( $pid );
                    $location   = (string) get_field( 'location', $pid );
                    $points     = floatval( get_field( 'points', $pid ) );
                    $type       = (string) get_field( 'type', $pid );
                    $free       = get_field( 'freigegeben', $pid );
                    if ( fobi_is_freigegeben( $free ) ) $total_points += $points; ?>
                    <tr<?php echo fobi_is_freigegeben($free) ? '' : ' style="color:#9aa"'; ?>>
                        <td><?php echo esc_html( $display_date ); ?></td>
                        <td><?php echo esc_html( $title ); ?></td>
                        <td class="fobi-dim"><?php echo esc_html( $location ); ?></td>
                        <td style="text-align:right;"><?php echo esc_html( number_format($points,1,',','.') ); ?></td>
                        <td class="fobi-dim"><?php echo esc_html( $type ); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            <p style="text-align:right; margin-top:10px;">
               EBCP Gesamtpunkte im Zeitraum: <strong><?php echo esc_html( number_format($total_points,1,',','.') ); ?></strong>
            </p>
        </div>
        <?php wp_reset_postdata();
        return ob_get_clean();
    }
}
new Fortbildung_Liste_Plugin();

/* ============================================================
 * AJAX: Filter Fortbildungen
 * ============================================================ */

// ============================================================
// AJAX: Live-Prüfung, ob für Zeitraum heute schon ein Nachweis existiert
// ============================================================
add_action('wp_ajax_fobi_can_create_today', 'fobi_can_create_today_cb');
function fobi_can_create_today_cb(){
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(array('message'=>'Nicht eingeloggt.'));
    }

    $uid  = get_current_user_id();
    $from = isset($_POST['from_year']) ? intval($_POST['from_year']) : 0;
    $to   = isset($_POST['to_year'])   ? intval($_POST['to_year'])   : 0;

    // Defaults & Klammerung wie im UI
    if ( ! $from ) $from = intval( date('Y') );
    if ( ! $to )   $to   = $from;
    if ( $to < $from ) $to = $from;
    if ( ($to - $from) > 2 ) $to = $from + 2;

    $period_from = $from . '-01-01';
    $period_to   = $to   . '-12-31';

    // Heute (Site-Zeitzone)
    $now_ts  = current_time('timestamp');
    // Grenzen als Strings für Meta-Query vom Typ DATETIME
    $startDay = date_i18n('Y-m-d 00:00:00', $now_ts);
    $endDay   = date_i18n('Y-m-d 23:59:59', $now_ts);

    // Suche Nachweise des Nutzers für genau diesen Zeitraum, die HEUTE erstellt wurden
    $q = new WP_Query(array(
        'post_type'      => 'fobi_certificate',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            'relation' => 'AND',
            array('key' => 'user_id',     'value' => $uid,          'compare' => '='),
            array('key' => 'period_from', 'value' => $period_from,  'compare' => '='),
            array('key' => 'period_to',   'value' => $period_to,    'compare' => '='),
            array('key' => 'created_at',  'value' => array($startDay, $endDay), 'compare' => 'BETWEEN', 'type' => 'DATETIME'),
        ),
    ));

    $allowed = ! $q->have_posts();
    wp_reset_postdata();

    wp_send_json_success(array('allowed' => $allowed));
}





add_action('wp_ajax_fobi_filter_fortbildungen', function(){
    if(!is_user_logged_in()){ echo "Sie sind nicht eingeloggt."; wp_die(); }
    $uid=get_current_user_id(); $from=isset($_POST['from'])?intval($_POST['from']):0; $to=isset($_POST['to'])?intval($_POST['to']):0;
    if($from && $to && ($to-$from)>2){ $to=$from+2; }
    if(!$from){ $from=date('Y'); } if(!$to){ $to=$from; }
    echo Fortbildung_Liste_Plugin::get_fortbildungen_table($uid,$from,$to); wp_die();
});

/* ============================================================
 * QUIZ-Importer & Nachweis-Erstellung
 * ============================================================ */
class Quiz_Report_Importer {
    public function __construct() {
        add_action('wp_ajax_process_quiz_reports', array($this,'process_quiz_reports'));
        add_action('wp_ajax_create_fortbildungsnachweis', array($this,'create_fortbildungsnachweis'));
        if( !wp_next_scheduled('qr_import_daily_event') ){ wp_schedule_event(time(),'daily','qr_import_daily_event'); }
        add_action('qr_import_daily_event', array($this,'process_quiz_reports')); // Cron
    }

    public function process_quiz_reports() {
        global $wpdb;
        $table = $wpdb->prefix.'aysquiz_reports';
        $reports = $wpdb->get_results("SELECT * FROM $table WHERE status='finished'");
        if(empty($reports)){ echo "Keine fertigen Quiz-Reports gefunden."; wp_die(); }

        $processed=0;$skipped=0;$errors=0;
        foreach($reports as $report){
            if(intval($report->user_id)<=0){ $skipped++; }
            else{
                $existing=get_posts(array('post_type'=>'fortbildung','meta_key'=>'quiz_report_id','meta_value'=>$report->unique_code,'posts_per_page'=>1,'fields'=>'ids'));
                if(!empty($existing)){ $skipped++; }
                else{
                    $data_arr=@json_decode($report->data,true);
                    $is_passed=null;
                    if($is_passed===null && isset($report->passed)){ $v=strtolower(trim((string)$report->passed)); $is_passed=in_array($v,array('1','true','yes','passed','bestanden'),true); }
                    if($is_passed===null && isset($report->result)){ $res=strtolower(trim((string)$report->result)); if($res!==''){ $is_passed=(strpos($res,'pass')!==false || strpos($res,'bestanden')!==false); } }
                    if($is_passed===null && is_array($data_arr)){
                        foreach(array('passed','is_passed','quiz_is_passed','quizPassed','bestanden') as $k){
                            if(array_key_exists($k,$data_arr)){ $v=strtolower(trim((string)$data_arr[$k])); $is_passed=in_array($v,array('1','true','yes','passed','bestanden'),true); break; }
                        }
                        if($is_passed===null){
                            $threshold=null;
                            foreach(array('passing_score','min_pass_score','pass_score','passing_percent','min_percent') as $k){ if(isset($data_arr[$k]) && is_numeric($data_arr[$k])){ $threshold=floatval($data_arr[$k]); break; } }
                            if($threshold!==null){ $score_num=floatval(trim((string)$report->score)); $is_passed=($score_num>=$threshold); }
                        }
                    }
                    if($is_passed===null){ $score_num=floatval(trim((string)$report->score)); $is_passed=($score_num>=60); }

                    if(!$is_passed){ $skipped++; }
                    else{
                        $quiz_title='Unbekanntes Quiz';
                        if(is_array($data_arr) && !empty($data_arr['quiz_name'])){ $quiz_title=$data_arr['quiz_name']; }
                        $current_date=current_time('Y-m-d');
                        $pid=wp_insert_post(array('post_title'=>$quiz_title,'post_type'=>'fortbildung','post_status'=>'publish'));
                        if(is_wp_error($pid)){ $errors++; }
                        else{
                            update_field('type','Quiz',$pid);
                            update_field('location','Online',$pid);
                            update_field('user',intval($report->user_id),$pid);
                            update_field('date',$current_date,$pid);
                            $this_year=date('Y');
                            $q=new WP_Query(array('post_type'=>'fortbildung','fields'=>'ids','meta_query'=>array(
                                array('key'=>'type','value'=>'Quiz'),
                                array('key'=>'user','value'=>intval($report->user_id)),
                                array('key'=>'date','value'=>$this_year.'-','compare'=>'LIKE'),
                            )));
                            $quiz_count=intval($q->found_posts); $already_counted=max(0,$quiz_count-1);
                            $points=($already_counted<12)?0.5:0;
                            update_field('points',$points,$pid);
                            update_post_meta($pid,'quiz_report_id',$report->unique_code);
                            update_field('freigegeben',true,$pid);
                            $processed++;
                        }
                    }
                }
            }
            // Report löschen
            $wpdb->delete($table,array('id'=>$report->id),array('%d'));
        }

        // Nach Cron-Import: Doublettenbereinigung (einmal)
        if ( defined('DOING_CRON') && DOING_CRON ) {
            fobi_run_dedupe_once();
        }

        echo "Import abgeschlossen. Verarbeitete: $processed, Übersprungen: $skipped, Fehler: $errors."; wp_die();
    }

    /**
     * Nachweis erstellen – mit Short‑Code‑Verifikation (/verify/{CODE})
     * Logo breiter (70 mm), robuster QR + Text‑Fallback, Footer fix (eine Zeile).
     */
    public function create_fortbildungsnachweis() {
    if ( ! is_user_logged_in() ) { wp_send_json_error(array('message'=>'Nicht eingeloggt.')); }

    $settings = wp_parse_args( get_option( FOBI_AEK_OPTION_KEY, array() ), fobi_aek_default_settings() );

    // Rollenberechtigung (aus den Einstellungen)
    $required_roles = (array)($settings['certificate_button_roles'] ?? array('administrator'));
    if ( ! fobi_user_has_any_role( $required_roles ) ) {
        wp_send_json_error(array('message'=>'Keine Berechtigung (erforderliche Rolle(n): '.implode(', ',$required_roles).').'));
    }
    if ( $settings['enable_certificate_button'] !== '1' ) {
        wp_send_json_error(array('message'=>'Funktion ist in den Einstellungen deaktiviert.'));
    }

    $uid = get_current_user_id();
    $from_year = isset($_POST['from_year']) ? intval($_POST['from_year']) : 0;
    $to_year   = isset($_POST['to_year'])   ? intval($_POST['to_year'])   : 0;
    if(!$from_year) $from_year=date('Y'); if(!$to_year) $to_year=$from_year;
    if($to_year<$from_year) $to_year=$from_year;
    if(($to_year-$from_year)>2) $to_year=$from_year+2;

    $period_from  = $from_year.'-01-01';
    $period_to    = $to_year.'-12-31';
    $period_label = '01.01.'.$from_year.' – 31.12.'.$to_year;
    $subject_line = 'Fortbildungsnachweis '.$from_year.' - '.$to_year;

    // --- Tageslimit je Zeitraum (pro Benutzer) ---
    $today = current_time('Y-m-d');
    $existing = new WP_Query(array(
        'post_type'      => 'fobi_certificate',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            'relation' => 'AND',
            array('key' => 'user_id',     'value' => $uid,          'compare' => '='),
            array('key' => 'period_from', 'value' => $period_from,  'compare' => '='),
            array('key' => 'period_to',   'value' => $period_to,    'compare' => '='),
        ),
    ));
    $blocked = false;
    if ( $existing->have_posts() ) {
        foreach ( $existing->posts as $cid_existing ) {
            $created_meta = (string) get_post_meta( $cid_existing, 'created_at', true );
            if ( $created_meta ) {
                $created_day = date_i18n( 'Y-m-d', strtotime( $created_meta ) );
                if ( $created_day === $today ) { $blocked = true; break; }
            }
        }
    }
    wp_reset_postdata();
    if ( $blocked ) {
        wp_send_json_error(array(
            'message' => 'Für den Zeitraum ' . $period_label . ' wurde heute bereits ein Nachweis erzeugt. Jeder Zeitraum kann nur einmal pro Kalendertag generiert werden.'
        ));
    }

    // Daten sammeln (nur freigegeben) — Datum robust parsen und fertig formatiert bereitstellen
    $q = new WP_Query(array(
        'post_type'=>'fortbildung','post_status'=>'publish','posts_per_page'=>-1,
        'meta_query'=>array('relation'=>'AND',
            array('key'=>'user','value'=>$uid,'compare'=>'='),
            array('key'=>'date','value'=>array($period_from,$period_to),'compare'=>'BETWEEN','type'=>'DATE'),
        ),
    ));
    $rows=array(); $sum=0.0;
    if($q->have_posts()){
        while($q->have_posts()){ $q->the_post();
            $pid=get_the_ID();
            if(!fobi_is_freigegeben(get_field('freigegeben',$pid))) continue;

            $raw_date = (string) get_field('date', $pid);
            $date_ts  = false;
            $date_disp = '';
            if ($raw_date !== '') {
                $fmts = array('Y-m-d','d.m.Y','Ymd');
                foreach ($fmts as $fmt) {
                    $dt = DateTime::createFromFormat($fmt, $raw_date);
                    if ($dt instanceof DateTime) { $date_ts = $dt->getTimestamp(); break; }
                }
                $date_disp = ($date_ts !== false) ? date_i18n('d.m.Y', $date_ts) : $raw_date;
            }

            $r = array(
                'date_raw'  => $raw_date,
                'date_disp' => $date_disp,
                'title'     => get_the_title($pid),
                'loc'       => (string) get_field('location', $pid),
                'points'    => floatval( get_field('points', $pid) ),
            );

            $sum += $r['points'];
            $rows[] = $r;
        }
        wp_reset_postdata();
    }
    if(empty($rows)){ wp_send_json_error(array('message'=>'Keine freigegebenen Fortbildungseinträge im Zeitraum gefunden.')); }

    // Nutzer / Adresse
    $user = get_userdata($uid);
    $full_name = trim(($user->first_name ?: '').' '.($user->last_name ?: '')); if($full_name==='') $full_name = $user->display_name ?: $user->user_login;

    $addr_street = trim( do_shortcode('[zoho_api_data field="Strasse"]') );
    $addr_extra  = trim( do_shortcode('[zoho_api_data field="Zusatz"]') );
    $addr_plz    = trim( do_shortcode('[zoho_api_data field="PLZ"]') );
    $addr_city   = trim( do_shortcode('[zoho_api_data field="Ort"]') );
    $address_lines = array_filter(array($full_name,$addr_street,$addr_extra, trim($addr_plz.' '.$addr_city)));

    // FPDF laden
    if ( ! class_exists('FPDF') ) {
        $fpdf_path = DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php';
        if(file_exists($fpdf_path)) require_once $fpdf_path;
    }
    if ( ! class_exists('FPDF') ) { wp_send_json_error(array('message'=>'FPDF-Bibliothek nicht gefunden (Ordner <code>fpdf</code>).')); }

    // Optional: FPDI (PDF-Vorlage)
    $fpdi_loaded = false;
    foreach ( array('vendor/autoload.php','fpdi/autoload.php','fpdi/src/autoload.php','fpdi/fpdi.php') as $rel ) {
        $p = plugin_dir_path(__FILE__).$rel;
        if ( file_exists($p) ) { @require_once $p; $fpdi_loaded = (class_exists('\\setasign\\Fpdi\\Fpdi') || class_exists('FPDI')); if($fpdi_loaded) break; }
    }

    // PNG-Logo
    $logo_png_path = '';
    $logo_id = absint($settings['pdf_logo_attachment_id']);
    if ( $logo_id ) {
        $p = get_attached_file($logo_id);
        if ( $p && file_exists($p) ) {
            fobi_normalize_png_to_8bit($p);
            if ( fobi_is_valid_image($p, array('image/png')) ) $logo_png_path = $p;
        }
    }

    // Nachweis-CPT
    $cert_id = wp_insert_post(array('post_title'=>'Nachweis '.$full_name.' ('.$period_label.')','post_type'=>'fobi_certificate','post_status'=>'publish'));
    if(is_wp_error($cert_id) || ! $cert_id){ wp_send_json_error(array('message'=>'Fehler beim Anlegen des Nachweis-Datensatzes.')); }
    $created_at = current_time('mysql');
    update_post_meta($cert_id,'user_id',$uid);
    update_post_meta($cert_id,'period_from',$period_from);
    update_post_meta($cert_id,'period_to',$period_to);
    update_post_meta($cert_id,'period_label',$period_label);
    update_post_meta($cert_id,'sum_points',$sum);
    update_post_meta($cert_id,'created_at',$created_at);
    $sum_signature = number_format($sum,1,'.','');
    $sig = hash_hmac('sha256', $cert_id.'|'.$uid.'|'.$period_from.'|'.$period_to.'|'.$sum_signature, wp_salt('auth'));
    update_post_meta($cert_id,'sig',$sig);

    // Short-Code für Verifikation generieren (8 Stellen)
    $verify_code = fobi_generate_unique_verify_code(8);
    update_post_meta($cert_id,'verify_code',$verify_code);

    // Verifikations-Base
    $verify_base = trim((string)$settings['qr_verify_base']);
    if (empty($verify_base)) $verify_base = home_url('/verify/');
    $verify_base = fobi_ensure_trailing_slash($verify_base);
    $verify_short_url = $verify_base . rawurlencode($verify_code);
    update_post_meta($cert_id,'verify_short_url',$verify_short_url);

    // PDF erstellen (ggf. mit Vorlage)
    $pdf = null; $used_template=false;
    $tpl_id = absint($settings['pdf_template_attachment_id']);
    $tpl_path = $tpl_id ? get_attached_file($tpl_id) : '';
    $tpl_ok = ($tpl_path && file_exists($tpl_path) && preg_match('/\.pdf$/i',$tpl_path));

    if($tpl_ok && $fpdi_loaded && (class_exists('\\setasign\\Fpdi\\Fpdi') || class_exists('FPDI'))){
        if(class_exists('\\setasign\\Fpdi\\Fpdi')){ $pdf = new \setasign\Fpdi\Fpdi('P','mm','A4'); }
        else { $pdf = new \FPDI('P','mm','A4'); }
        $pdf->SetTitle(fobi_pdf_text($subject_line));
        $pdf->SetAuthor(fobi_pdf_text($settings['pdf_sender_name']));
        $pdf->SetMargins(20,20,20);
        $pdf->SetAutoPageBreak(true, 20);
        try{
            $pdf->setSourceFile($tpl_path);
            $tplId = $pdf->importPage(1);
            $pdf->AddPage();
            if(method_exists($pdf,'useTemplate')){
                if(class_exists('\\setasign\\Fpdi\\Fpdi')){ $pdf->useTemplate($tplId,0,0,210,297,false); }
                else { $pdf->useTemplate($tplId,0,0,210,297); }
            }
            $used_template=true;
        }catch(\Exception $e){
            $pdf = null;
        }
    }
    if(!$pdf){
        $pdf = new FPDF('P','mm','A4');
        $pdf->SetTitle(fobi_pdf_text($subject_line));
        $pdf->SetAuthor(fobi_pdf_text($settings['pdf_sender_name']));
        $pdf->SetMargins(20,20,20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Fallback: PDF-Vorlage als PNG-Hintergrund
        if($tpl_ok && class_exists('Imagick')){
            try{
                $u=wp_upload_dir();
                $bg_png = trailingslashit($u['basedir']).'dgptm_tpl_'.md5($tpl_path).'.png';
                if(!file_exists($bg_png)){
                    $im = new Imagick();
                    $im->setResolution(300,300);
                    $im->readImage($tpl_path.'[0]');
                    $im->setImageFormat('png');
                    $im->writeImage($bg_png);
                    $im->clear(); $im->destroy();
                }
                if(file_exists($bg_png)){
                    fobi_normalize_png_to_8bit($bg_png);
                    if(fobi_is_valid_image($bg_png)) {
                        $pdf->Image($bg_png, 0, 0, 210, 297);
                        $used_template=true;
                    }
                }
            }catch(\Exception $e){}
        }
    }

    /* ======= Brief-Layout ======= */
    if($logo_png_path && fobi_is_valid_image($logo_png_path)){ $pdf->Image($logo_png_path, 120, 20, 70); }

    // Datum rechts / Adresse links
    $pdf->SetXY(20, 50);
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0, 6, fobi_pdf_text( date_i18n('d.m.Y') ), 0, 1, 'R');
    $pdf->SetXY(20, 50);
    $pdf->SetFont('Arial','',11);
    foreach ( $address_lines as $line ) {
        $pdf->Cell(0, 6, fobi_pdf_text($line), 0, 1, 'L');
    }
    $pdf->Ln(8);

    // Betreff
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0, 7, fobi_pdf_text('Betreff: '.$subject_line), 0, 1, 'L');
    $pdf->Ln(3);

    // Name
    $pdf->SetFont('Arial','',11);
    $pdf->Cell(0, 6, fobi_pdf_text('Name: '.$full_name), 0, 1, 'L');
    $pdf->Ln(4);

    // Tabelle
    $wDate=30; $wTitle=80; $wLoc=45; $wPts=15;
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell($wDate,8,fobi_pdf_text('Datum'),1,0,'L');
    $pdf->Cell($wTitle,8,fobi_pdf_text('Titel'),1,0,'L');
    $pdf->Cell($wLoc,8,fobi_pdf_text('Ort'),1,0,'L');
    $pdf->Cell($wPts,8,fobi_pdf_text('Pkt'),1,1,'R');

    $pdf->SetFont('Arial','',10);
    foreach ($rows as $r) {
        // Datum: bereits formatiert (nie „heute“ erzwingen)
        $pdf->Cell($wDate, 7, fobi_pdf_text( $r['date_disp'] ), 1, 0, 'L');

        $x = $pdf->GetX(); $y = $pdf->GetY();

        // Titel
        $pdf->MultiCell($wTitle, 7, fobi_pdf_text($r['title']), 1, 'L');
        $hTitle = $pdf->GetY() - $y;
        $pdf->SetXY($x + $wTitle, $y);

        // Ort
        $pdf->MultiCell($wLoc, 7, fobi_pdf_text($r['loc']), 1, 'L');
        $hLoc = $pdf->GetY() - $y;
        $pdf->SetXY($x + $wTitle + $wLoc, $y);

        // Punkte
        $rowh = max(7, $hTitle, $hLoc);
        $pdf->Cell($wPts, $rowh, fobi_pdf_text(number_format($r['points'],1,',','.')), 1, 1, 'R');
    }

    // Summe – Platz prüfen
    $pageH   = method_exists($pdf,'GetPageHeight') ? $pdf->GetPageHeight() : 297.0;
    $bMargin = 20.0;
    $trigger = $pageH - $bMargin;
    if ( $pdf->GetY() + 12 > $trigger ) {
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',11);
        $pdf->Cell($wDate,8,fobi_pdf_text('Datum'),1,0,'L');
        $pdf->Cell($wTitle,8,fobi_pdf_text('Titel'),1,0,'L');
        $pdf->Cell($wLoc,8,fobi_pdf_text('Ort'),1,0,'L');
        $pdf->Cell($wPts,8,fobi_pdf_text('Pkt'),1,1,'R');
    }
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell($wDate+$wTitle+$wLoc,8, fobi_pdf_text('Gesamtpunkte'), 1, 0, 'R');
    $pdf->Cell($wPts,8, fobi_pdf_text(number_format($sum,1,',','.')), 1, 1, 'R');

    // QR-Code + Hinweis
    try{
        $left=20; $qrSize=38; $footerSpace=25; $noteLineH=5;
        $note_lines = 4;
        $needed = $qrSize + 8 + ($noteLineH * $note_lines) + 6 + $footerSpace;
        $pageH   = method_exists($pdf,'GetPageHeight') ? $pdf->GetPageHeight() : 297.0;
        $bMargin = 20.0;
        $trigger = $pageH - $bMargin;

        if ( $pdf->GetY() + $needed > $trigger ) {
            $pdf->AddPage();
        }

        $u = wp_upload_dir();
        $qr_tmp = trailingslashit($u['basedir']).'dgptm_qr_'.md5($verify_short_url).'.png';
        $qr_ok = false;
        if(!file_exists($qr_tmp) || !fobi_is_valid_image($qr_tmp)){
            $qr_ok = fobi_generate_qr_png( $verify_short_url, $qr_tmp );
        } else {
            $qr_ok = true;
        }
        if($qr_ok){ fobi_normalize_png_to_8bit($qr_tmp); $qr_ok = fobi_is_valid_image($qr_tmp); }

        $yNow = $pdf->GetY() + 6;
        if ( $qr_ok ) {
            $pdf->Image($qr_tmp, $left, $yNow, $qrSize, $qrSize);
        } else {
            // Fallback: Kasten mit Host + Code
            $pdf->Rect($left, $yNow, $qrSize, $qrSize);
            $pdf->SetFont('Arial','B',10);
            $pdf->SetXY($left, $yNow + 12);
            $pdf->Cell($qrSize,6, fobi_pdf_text('Verify'), 0, 2, 'C');
            $pdf->SetFont('Arial','',9);
            $host = preg_replace('#^https?://#','', rtrim(home_url('/verify/'),'/'));
            $pdf->Cell($qrSize,5, fobi_pdf_text($host), 0, 2, 'C');
            $pdf->SetFont('Arial','B',11);
            $pdf->Cell($qrSize,7, fobi_pdf_text($verify_code), 0, 2, 'C');
        }

        // Hinweistext kompakt
        $pdf->SetXY($left + $qrSize + 6, $yNow + 2);
        $pdf->SetFont('Arial','',9);
        $host = preg_replace('#^https?://#','', rtrim(home_url('/verify/'),'/'));
        $note  = "Zur Verifikation bitte oder Code auf ".$host." eingeben.\n";
        $note .= "Code: ".$verify_code."\n";
        $note .= "Gültig für 365 Tage ab Erstellungsdatum.";
        $pdf->MultiCell(170 - $qrSize - 6, $noteLineH, fobi_pdf_text($note), 0, 'L');
        $pdf->SetY($yNow + $qrSize + 10);
    }catch(\Throwable $e){ /* QR optional – PDF dennoch ausgeben */ }

    // Footer – eine Zeile
    $pdf->SetY(-25);
    $pdf->SetFont('Arial','I',8);
    $footer_line = $settings['pdf_sender_name'].' — '.$settings['pdf_sender_email'].' — '.site_url().' — Erstellt am '.date_i18n('d.m.Y H:i');
    $pdf->Cell(0,5, fobi_pdf_text($footer_line), 0, 0, 'C');

    // speichern
    $uploads = wp_upload_dir();
    $target_dir = trailingslashit($uploads['basedir']).'dgptm';
    if( ! file_exists($target_dir) ){ @wp_mkdir_p($target_dir); }
    if( ! is_dir($target_dir) || ! is_writable($target_dir) ){
        wp_send_json_error(array('message'=>'Kein beschreibbares Verzeichnis für den PDF-Export gefunden (uploads/dgptm).'));
    }
    $tmp_out = trailingslashit($target_dir).'dgptm_tmp_'.$cert_id.'.pdf';
    $pdf->Output('F', $tmp_out);
    if( ! file_exists($tmp_out) || filesize($tmp_out) < 1000 ){
        @unlink($tmp_out);
        wp_send_json_error(array('message'=>'PDF konnte nicht erzeugt werden.'));
    }

    $filename = 'Fortbildungsnachweis_'.sanitize_title($full_name).'_'.$from_year.( $to_year!==$from_year ? '-'.$to_year : '' ).'_'.$verify_code.'.pdf';
    $final_path = trailingslashit($target_dir).$filename;
    if(!@rename($tmp_out,$final_path)){ @copy($tmp_out,$final_path); @unlink($tmp_out); }

    $fileurl = '';
    if( strpos($final_path, trailingslashit($uploads['basedir'])) === 0 ){
        $rel = substr($final_path, strlen(trailingslashit($uploads['basedir'])));
        $fileurl = trailingslashit($uploads['baseurl']).str_replace(DIRECTORY_SEPARATOR,'/',$rel);
    }

    update_post_meta($cert_id,'file_url',$fileurl);
    update_post_meta($cert_id,'file_path',$final_path);

    $download_url = add_query_arg(array('cid'=>$cert_id,'sig'=>$sig), home_url('/dgptm-download/'));

    // Optional: E-Mail
    $to=$user->user_email;
    if( $settings['email_enabled'] === '1' && is_email($to) ){
        $tokens = array(
            'name'         => $full_name,
            'period_label' => $period_label,
            'from_year'    => (string)$from_year,
            'to_year'      => (string)$to_year,
            'verify_url'   => $verify_short_url,
            'verify_code'  => $verify_code,
            'sum_points'   => number_format($sum,1,',','.'),
            'site_name'    => get_bloginfo('name'),
            'site_url'     => site_url(),
        );
        $subject = fobi_mail_tpl( $settings['email_subject_tpl'], $tokens );
        $body    = fobi_mail_tpl( $settings['email_body_tpl'], $tokens );
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $attachments = array();
        if ( $settings['email_attach_pdf'] === '1' && file_exists($final_path) ) $attachments[] = $final_path;
        @wp_mail($to, $subject, $body, $headers, $attachments);
    }

    wp_send_json_success(array(
        'message'=>'Nachweis erstellt.',
        'period_label'=>$period_label,
        'fileurl'=>$fileurl,
        'download_url'=>$download_url,
        'cid'=>$cert_id,
        'verify_url'=>$verify_short_url,
        'verify_code'=>$verify_code
    ));
}
}
new Quiz_Report_Importer();

/* ============================================================
 * Admin-Menü „Quiz Reports“
 * ============================================================ */
add_action('admin_menu','frp_add_admin_menu');
function frp_add_admin_menu(){
    add_submenu_page('edit.php?post_type=fortbildung','Quiz Reports verarbeiten','Quiz Reports','manage_options','quiz-reports-import','frp_admin_page_callback');
}
function frp_admin_page_callback(){ ?>
    <div class="wrap"><h1>Quiz Reports verarbeiten</h1>
        <p>Klicke auf den Button, um alle fertigen (bestanden) Quiz Reports zu importieren. Dabei werden die entsprechenden Datensätze anschließend aus der Quiz-Datenbank gelöscht.</p>
        <button id="process-quiz-reports-button" class="button button-primary">Quiz Reports verarbeiten</button>
        <div id="process-quiz-reports-result" style="margin-top:20px;"></div>
    </div>
    <script>
    jQuery(function($){
        if(typeof window.ajaxurl==='undefined'){ window.ajaxurl='<?php echo esc_js(admin_url('admin-ajax.php')); ?>'; }
        $('#process-quiz-reports-button').on('click',function(e){
            e.preventDefault();
            $('#process-quiz-reports-result').html('Quiz Reports werden verarbeitet…');
            $.post(window.ajaxurl,{action:'process_quiz_reports'},function(r){ $('#process-quiz-reports-result').html(r); });
        });
    });
    </script>
<?php }


/* ============================================================
 * Einstellungen (Tabs)
 * ============================================================ */
add_action('admin_menu','fobi_aek_add_settings_menu');
function fobi_aek_add_settings_menu(){
    add_submenu_page('edit.php?post_type=fortbildung','Einstellungen (Fortbildungen)','Einstellungen','manage_options','fobi-aek-settings','fobi_aek_settings_page_render');
}
function fobi_aek_settings_page_render() {
    if(function_exists('wp_enqueue_media')) wp_enqueue_media();
    $is_admin = current_user_can('manage_options');
    $settings = wp_parse_args( get_option( FOBI_AEK_OPTION_KEY, array() ), fobi_aek_default_settings() );

    // Speichern
    if( isset($_POST['fobi_aek_save']) && $is_admin && check_admin_referer('fobi_aek_settings_save') ){
        $settings['access_token']        = sanitize_text_field( wp_unslash($_POST['access_token'] ?? '') );
        $settings['extra_header_key']    = sanitize_text_field( wp_unslash($_POST['extra_header_key'] ?? '') );
        $settings['extra_header_value']  = sanitize_text_field( wp_unslash($_POST['extra_header_value'] ?? '') );
        $settings['scans_endpoint_tpl']  = esc_url_raw( wp_unslash($_POST['scans_endpoint_tpl'] ?? '') );
        $settings['event_endpoint_tpl']  = esc_url_raw( wp_unslash($_POST['event_endpoint_tpl'] ?? '') );
        $settings['efn_autofill_on_init']= isset($_POST['efn_autofill_on_init']) ? '1' : '0';
        $settings['batch_enabled']       = isset($_POST['batch_enabled']) ? '1' : '0';
        $allowed = array('hourly','twicedaily','daily','weekly');
        $settings['batch_interval']      = in_array( ($_POST['batch_interval'] ?? 'daily'), $allowed, true ) ? $_POST['batch_interval'] : 'daily';
        $settings['allow_member_refresh']= isset($_POST['allow_member_refresh']) ? '1' : '0';

        $settings['pdf_sender_name']     = sanitize_text_field( wp_unslash($_POST['pdf_sender_name'] ?? '') );
        $settings['pdf_sender_email']    = sanitize_email( wp_unslash($_POST['pdf_sender_email'] ?? '') );
        $settings['qr_verify_base']      = esc_url_raw( wp_unslash($_POST['qr_verify_base'] ?? '') );
        $settings['pdf_template_attachment_id'] = absint( $_POST['pdf_template_attachment_id'] ?? 0 );
        $settings['pdf_logo_attachment_id']     = absint( $_POST['pdf_logo_attachment_id'] ?? 0 );
        $settings['enable_certificate_button']  = isset($_POST['enable_certificate_button']) ? '1' : '0';
     $roles_in = $_POST['certificate_button_roles'] ?? array('administrator');
$roles_in = is_array($roles_in) ? $roles_in : array($roles_in);
$roles_in = array_values(array_unique(array_map('sanitize_key', $roles_in)));
$settings['certificate_button_roles'] = !empty($roles_in) ? $roles_in : array('administrator');


        // E‑Mail
        $settings['email_enabled']     = isset($_POST['email_enabled']) ? '1' : '0';
        $settings['email_subject_tpl'] = sanitize_text_field( wp_unslash($_POST['email_subject_tpl'] ?? '') );
        $settings['email_body_tpl']    = wp_kses_post( wp_unslash($_POST['email_body_tpl'] ?? '') );
        $settings['email_attach_pdf']  = isset($_POST['email_attach_pdf']) ? '1' : '0';

        $mapping_json = wp_unslash($_POST['mapping_json'] ?? '');
        json_decode($mapping_json); if(json_last_error()===JSON_ERROR_NONE){ $settings['mapping_json']=$mapping_json; }

        update_option( FOBI_AEK_OPTION_KEY, $settings );
        echo '<div class="notice notice-success"><p>Einstellungen gespeichert.</p></div>';
        fobi_aek_reschedule_cron($settings);
    }

    $nonce_import = wp_create_nonce('fobi_aek_import_run');

    $tpl_id = absint($settings['pdf_template_attachment_id']);
    $tpl_url = $tpl_id ? wp_get_attachment_url($tpl_id) : '';
    $tpl_name = $tpl_id ? basename( get_attached_file($tpl_id) ) : '';

    $logo_id = absint($settings['pdf_logo_attachment_id']);
    $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
    $logo_name = $logo_id ? basename( get_attached_file($logo_id) ) : '';

    $disabled_attr = current_user_can('manage_options') ? '' : ' disabled="disabled"';
    $title_attr    = current_user_can('manage_options') ? '' : ' title="Nur Administratoren dürfen den Abruf starten."';
    $style_attr    = current_user_can('manage_options') ? '' : ' style="opacity:.5;cursor:not-allowed"';

    // Rollenliste
	
	

// --- BEGIN REPLACE: Rollen-Multiselect (PDF & Button Tab) ---

// Rollenliste sicherstellen
// --- BEGIN REPLACE: Rollen-Multiselect (PDF & Button Tab) ---

// Rollen zuverlässig laden
$all_roles = array();

// 1) Sicherstellen, dass get_editable_roles verfügbar ist
if ( ! function_exists('get_editable_roles') && file_exists( ABSPATH . 'wp-admin/includes/user.php' ) ) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}
if ( function_exists('get_editable_roles') ) {
    $all_roles = get_editable_roles();
}

// 2) Fallback: wp_roles
if ( empty($all_roles) ) {
    global $wp_roles;
    if ( ! isset($wp_roles) || ! is_a($wp_roles, 'WP_Roles') ) {
        $wp_roles = function_exists('wp_roles') ? wp_roles() : new WP_Roles();
    }
    if ( is_object($wp_roles) && ! empty($wp_roles->roles) ) {
        $all_roles = $wp_roles->roles;
    }
}

// 3) Letzter Fallback
if ( empty($all_roles) ) {
    $opt = get_option('wp_user_roles');
    if ( is_array($opt) ) $all_roles = $opt;
}

// Abwärtskompatibel: altes Feld
$selected_roles = $settings['certificate_button_roles'] ?? ( $settings['certificate_button_role'] ?? array('administrator') );
if ( ! is_array( $selected_roles ) ) {
    $selected_roles = array( sanitize_key( $selected_roles ) );
}
$selected_roles = array_values( array_unique( array_map( 'sanitize_key', $selected_roles ) ) );
?>
<p style="margin-top:6px;">
    <label for="certificate_button_roles">Erlaubte Rollen (Mehrfachauswahl mit Strg/Cmd):&nbsp;</label>
    <select id="certificate_button_roles" name="certificate_button_roles[]" multiple size="6" style="min-width:260px;">
        <?php
        if ( ! empty($all_roles) ) {
            foreach ( $all_roles as $slug => $role ) {
                $slug_s = sanitize_key( $slug );
                $name   = is_array($role) && isset($role['name']) ? $role['name'] : ( is_object($role) && isset($role->name) ? $role->name : $slug_s );
                $sel    = in_array( $slug_s, $selected_roles, true ) ? ' selected' : '';
                echo '<option value="'.esc_attr($slug_s).'"'.$sel.'>'.esc_html($name).'</option>';
            }
        } else {
            echo '<option value="administrator" selected>Administrator</option>';
        }
        ?>
    </select>
</p>
<?php
// --- END REPLACE ---
// --- END REPLACE ---

	
	
 
    ?>



    <div class="wrap">
        <h1>Fortbildungen – Einstellungen & Import</h1>

        <h2 class="nav-tab-wrapper">
            <a href="#tab-aek" class="nav-tab">Zugang &amp; Endpunkte</a>
            <a href="#tab-efn" class="nav-tab">EFN &amp; Abruf</a>
            <a href="#tab-mapping" class="nav-tab">Mapping</a>
            <a href="#tab-pdf" class="nav-tab">PDF &amp; Button</a>
            <a href="#tab-email" class="nav-tab">E‑Mail</a>
            <a href="#tab-import" class="nav-tab">Import</a>
        </h2>

        <form method="post" id="fobi-settings-form">
            <?php wp_nonce_field('fobi_aek_settings_save'); ?>

            <style>
            .fobi-tab-panel{display:none}
            .fobi-tab-panel.active{display:block}
            .fobi-sticky-save{position:sticky;bottom:0;background:#f6f7f7;border-top:1px solid #dcdcde;padding:10px;margin-top:10px}
            </style>

            <!-- Tab: Zugang & Endpunkte -->
            <div id="tab-aek" class="fobi-tab-panel">
                <h2 class="title">Zugangsdaten & Endpunkte</h2>
                <table class="form-table">
                    <tr><th><label for="access_token">Access-Token (Authorization)</label></th><td><input type="text" class="regular-text" id="access_token" name="access_token" value="<?php echo esc_attr($settings['access_token']); ?>" placeholder="z. B. Bearer abcdef..."></td></tr>
                    <tr>
                        <th><label for="extra_header_key">Zusätzlicher Header (optional)</label></th>
                        <td>
                            <input type="text" class="regular-text" id="extra_header_key" name="extra_header_key" value="<?php echo esc_attr($settings['extra_header_key']); ?>" placeholder="z. B. X-API-KEY">
                            <input type="text" class="regular-text" id="extra_header_value" name="extra_header_value" value="<?php echo esc_attr($settings['extra_header_value']); ?>" placeholder="Wert">
                        </td>
                    </tr>
                    <tr><th><label for="scans_endpoint_tpl">Scans/Teilnahmen Endpoint</label></th><td><input type="url" class="regular-text code" id="scans_endpoint_tpl" name="scans_endpoint_tpl" value="<?php echo esc_attr($settings['scans_endpoint_tpl']); ?>"></td></tr>
                    <tr><th><label for="event_endpoint_tpl">Event‑Details Endpoint</label></th><td><input type="url" class="regular-text code" id="event_endpoint_tpl" name="event_endpoint_tpl" value="<?php echo esc_attr($settings['event_endpoint_tpl']); ?>"></td></tr>
                </table>
            </div>

            <!-- Tab: EFN & Abruf -->
            <div id="tab-efn" class="fobi-tab-panel">
                <h2 class="title">EFN & Abruf‑Modus</h2>
                <table class="form-table">
                    <tr><th>EFN automatisch füllen</th><td><label><input type="checkbox" name="efn_autofill_on_init" <?php checked($settings['efn_autofill_on_init'],'1'); ?>> Beim Login/Init EFN via <code>[zoho_api_data field="EFN"]</code> übernehmen (falls leer)</label></td></tr>
                    <tr><th>Batch‑Abruf aktiv</th><td><label><input type="checkbox" name="batch_enabled" <?php checked($settings['batch_enabled'],'1'); ?>> Nächtlicher Abruf (nur Benutzer mit EFN)</label></td></tr>
                    <tr><th><label for="batch_interval">Batch‑Intervall</label></th><td>
                        <select id="batch_interval" name="batch_interval">
                            <option value="hourly" <?php selected($settings['batch_interval'],'hourly'); ?>>Stündlich</option>
                            <option value="twicedaily" <?php selected($settings['batch_interval'],'twicedaily'); ?>>Zweimal täglich</option>
                            <option value="daily" <?php selected($settings['batch_interval'],'daily'); ?>>Täglich</option>
                            <option value="weekly" <?php selected($settings['batch_interval'],'weekly'); ?>>Wöchentlich</option>
                        </select>
                    </td></tr>
                    <tr><th>On‑Demand für Mitglieder</th><td><label><input type="checkbox" name="allow_member_refresh" <?php checked($settings['allow_member_refresh'],'1'); ?>> Mitglieder dürfen eigene AEK‑Daten abrufen</label></td></tr>
                </table>
            </div>

            <!-- Tab: Mapping -->
            <div id="tab-mapping" class="fobi-tab-panel">
                <h2 class="title">Mapping‑Matrix (JSON)</h2>
                <p class="description">AEK‑Veranstaltungsarten (Typcodes) → EBCP‑Punkte. Struktur: <code>[{ "code":"A", "label":"Vortrag", "calc":"unit|fixed|per_hour", "points":1, "unit_minutes":45 }]</code></p>
                <textarea name="mapping_json" rows="10" class="large-text code"><?php echo esc_textarea($settings['mapping_json']); ?></textarea>
            </div>

            <!-- Tab: PDF & Button -->
            <div id="tab-pdf" class="fobi-tab-panel">
                <h2 class="title">PDF‑Einstellungen & Button‑Berechtigung</h2>
                <table class="form-table">
                    <tr><th><label for="pdf_logo_attachment_id">Logo (PNG)</label></th>
                        <td>
                            <input type="hidden" id="pdf_logo_attachment_id" name="pdf_logo_attachment_id" value="<?php echo esc_attr($logo_id); ?>">
                            <button type="button" class="button" id="choose_png_logo">PNG wählen</button>
                            <button type="button" class="button" id="remove_png_logo"<?php echo $logo_id? '':' style="display:none"'; ?>>Entfernen</button>
                            <span id="png_logo_filename" style="margin-left:8px;">
                                <?php if($logo_id && $logo_url): ?>
                                    <a href="<?php echo esc_url($logo_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($logo_name); ?></a>
                                <?php else: ?>
                                    <em>Kein Logo ausgewählt</em>
                                <?php endif; ?>
                            </span>
                            <p class="description">Es werden ausschließlich PNG‑Dateien akzeptiert.</p>
                        </td>
                    </tr>
                    <tr><th><label for="qr_verify_base">QR‑Verifikations‑URL</label></th><td><input type="url" class="regular-text" id="qr_verify_base" name="qr_verify_base" value="<?php echo esc_attr($settings['qr_verify_base']); ?>" placeholder="leer = /verify/"></td></tr>
                    <tr><th><label for="pdf_sender_name">PDF Absendername</label></th><td><input type="text" class="regular-text" id="pdf_sender_name" name="pdf_sender_name" value="<?php echo esc_attr($settings['pdf_sender_name']); ?>"></td></tr>
                    <tr><th><label for="pdf_sender_email">PDF Absender‑E‑Mail</label></th><td><input type="email" class="regular-text" id="pdf_sender_email" name="pdf_sender_email" value="<?php echo esc_attr($settings['pdf_sender_email']); ?>"></td></tr>
                    <tr>
                        <th><label for="pdf_template_attachment_id">PDF‑Vorlage (optional)</label></th>
                        <td>
                            <input type="hidden" id="pdf_template_attachment_id" name="pdf_template_attachment_id" value="<?php echo esc_attr($tpl_id); ?>">
                            <button type="button" class="button" id="choose_pdf_template">Vorlage wählen</button>
                            <button type="button" class="button" id="remove_pdf_template"<?php echo $tpl_id? '':' style="display:none"'; ?>>Vorlage entfernen</button>
                            <span id="pdf_template_filename" style="margin-left:8px;">
                                <?php if($tpl_id && $tpl_url): ?>
                                    <a href="<?php echo esc_url($tpl_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($tpl_name); ?></a>
                                <?php else: ?>
                                    <em>Keine Vorlage ausgewählt</em>
                                <?php endif; ?>
                            </span>
                            <p class="description">Wenn FPDI verfügbar ist, wird die 1. Seite dieser PDF als Hintergrund verwendet. Ohne FPDI erfolgt ein Best‑Effort‑Bildhintergrund (Imagick).</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Nachweis‑Button</th>
                        <td>
                            <label><input type="checkbox" name="enable_certificate_button" <?php checked($settings['enable_certificate_button'],'1'); ?>> Nachweis‑Button im Shortcode aktivieren</label>
                            <?php
// Rollen zuverlässig laden
$all_roles = array();
if ( ! function_exists('get_editable_roles') && file_exists( ABSPATH . 'wp-admin/includes/user.php' ) ) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}
if ( function_exists('get_editable_roles') ) {
    $all_roles = get_editable_roles();
}
if ( empty($all_roles) ) {
    global $wp_roles;
    if ( ! isset($wp_roles) || ! is_a($wp_roles, 'WP_Roles') ) {
        $wp_roles = function_exists('wp_roles') ? wp_roles() : new WP_Roles();
    }
    if ( is_object($wp_roles) && ! empty($wp_roles->roles) ) {
        $all_roles = $wp_roles->roles;
    }
}
if ( empty($all_roles) ) {
    $opt = get_option('wp_user_roles');
    if ( is_array($opt) ) $all_roles = $opt;
}
$selected_roles = $settings['certificate_button_roles'] ?? array('administrator');
if ( ! is_array($selected_roles) ) { $selected_roles = array( sanitize_key($selected_roles) ); }
$selected_roles = array_values( array_unique( array_map( 'sanitize_key', $selected_roles ) ) );
?>
<p style="margin-top:6px;">
    <label for="certificate_button_roles">Erlaubte Rollen (Mehrfachauswahl mit Strg/Cmd):&nbsp;</label>
    <select id="certificate_button_roles" name="certificate_button_roles[]" multiple size="6" style="min-width:260px;">
        <?php
        if ( ! empty($all_roles) ) {
            foreach ( $all_roles as $slug => $role_def ) {
                $slug_s = sanitize_key( $slug );
                $name   = is_array($role_def) && isset($role_def['name']) ? $role_def['name'] : ( is_object($role_def) && isset($role_def->name) ? $role_def->name : $slug_s );
                $sel    = in_array( $slug_s, $selected_roles, true ) ? ' selected' : '';
                echo '<option value="'.esc_attr($slug_s).'"'.$sel.'>'.esc_html($name).'</option>';
            }
        } else {
            echo '<option value="administrator" selected>Administrator</option>';
        }
        ?>
    </select>
</p>

                        </td>
                    </tr>
                </table>
            </div>

            <!-- Tab: E‑Mail -->
            <div id="tab-email" class="fobi-tab-panel">
                <h2 class="title">E‑Mail‑Versand</h2>
                <table class="form-table">
                    <tr>
                        <th>E‑Mail versenden</th>
                        <td><label><input type="checkbox" name="email_enabled" <?php checked($settings['email_enabled'],'1'); ?>> Nach Erstellung automatisch E‑Mail an den Benutzer senden</label></td>
                    </tr>
                    <tr>
                        <th><label for="email_subject_tpl">Betreff</label></th>
                        <td><input type="text" class="regular-text" id="email_subject_tpl" name="email_subject_tpl" value="<?php echo esc_attr($settings['email_subject_tpl']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="email_body_tpl">Text</label></th>
                        <td>
                            <textarea class="large-text code" rows="8" id="email_body_tpl" name="email_body_tpl"><?php echo esc_textarea($settings['email_body_tpl']); ?></textarea>
                            <p class="description">Verfügbare Platzhalter: <code>{name}</code>, <code>{period_label}</code>, <code>{from_year}</code>, <code>{to_year}</code>, <code>{sum_points}</code>, <code>{verify_url}</code>, <code>{verify_code}</code>, <code>{site_name}</code>, <code>{site_url}</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th>PDF anhängen</th>
                        <td><label><input type="checkbox" name="email_attach_pdf" <?php checked($settings['email_attach_pdf'],'1'); ?>> PDF an E‑Mail anhängen</label></td>
                    </tr>
                </table>
            </div>

            <!-- Tab: Import -->
            <div id="tab-import" class="fobi-tab-panel">
                <h2>Import gescannter Teilnahmen (pro EFN des eingeloggten Benutzers)</h2>
                <p>Es werden nur Benutzer berücksichtigt, die eine EFN hinterlegt haben.</p>
                <p>
                    <button id="fobi-aek-import-btn" class="button button-secondary"<?php echo $disabled_attr.$title_attr.$style_attr; ?>>Jetzt Daten von der Ärztekammer abrufen</button>
                    <span id="fobi-aek-import-spinner" class="spinner" style="float:none;display:none;"></span>
                </p>
                <div id="fobi-aek-status" style="margin-top:10px;max-width:900px;padding:10px;background:#fff;border:1px solid #ccd0d4;line-height:1.5;"></div>
            </div>

            <div class="fobi-sticky-save">
                <button type="submit" name="fobi_aek_save" class="button button-primary">Einstellungen speichern</button>
            </div>
        </form>
    </div>

    <script>
    jQuery(function($){
        // Tabs
        var key='fobiSettingsTab';
        function switchTab(id){
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[href="#'+id+'"]').addClass('nav-tab-active');
            $('.fobi-tab-panel').removeClass('active').hide();
            $('#'+id).addClass('active').show();
            localStorage.setItem(key,id);
            if(history.replaceState){ history.replaceState(null,null,'#'+id); }
        }
        $('.nav-tab-wrapper').on('click','.nav-tab',function(e){ e.preventDefault(); switchTab($(this).attr('href').replace('#','')); });
        var init=(location.hash?location.hash.replace('#',''):localStorage.getItem(key))||'tab-aek';
        if(!$('#'+init).length){ init='tab-aek'; }
        switchTab(init);

        // Media Picker: PNG Logo
        var frameLogo; $('#choose_png_logo').on('click', function(e){
            e.preventDefault();
            if(frameLogo){ frameLogo.open(); return; }
            frameLogo = wp.media({ title:'PNG‑Logo wählen', library:{ type:'image' }, multiple:false });
            frameLogo.on('select', function(){
                var att = frameLogo.state().get('selection').first().toJSON();
                if(att && att.subtype && att.subtype.toLowerCase() !== 'png'){
                    alert('Bitte eine PNG‑Datei wählen.');
                    return;
                }
                $('#pdf_logo_attachment_id').val(att.id || 0);
                $('#png_logo_filename').html(att && att.url ? '<a href="'+att.url+'" target="_blank" rel="noopener">'+att.filename+'</a>' : '<em>Kein Logo ausgewählt</em>');
                $('#remove_png_logo').show();
            });
            frameLogo.open();
        });
        $('#remove_png_logo').on('click', function(e){
            e.preventDefault();
            $('#pdf_logo_attachment_id').val('0');
            $('#png_logo_filename').html('<em>Kein Logo ausgewählt</em>');
            $(this).hide();
        });

        // Media Picker: PDF‑Vorlage
        var frameTpl; $('#choose_pdf_template').on('click', function(e){
            e.preventDefault();
            if(frameTpl){ frameTpl.open(); return; }
            frameTpl = wp.media({ title:'PDF‑Vorlage wählen', library:{ type:'application/pdf' }, multiple:false });
            frameTpl.on('select', function(){
                var att = frameTpl.state().get('selection').first().toJSON();
                $('#pdf_template_attachment_id').val(att.id);
                $('#pdf_template_filename').html('<a href="'+att.url+'" target="_blank" rel="noopener">'+att.filename+'</a>');
                $('#remove_pdf_template').show();
            });
            frameTpl.open();
        });
        $('#remove_pdf_template').on('click', function(e){
            e.preventDefault();
            $('#pdf_template_attachment_id').val('0');
            $('#pdf_template_filename').html('<em>Keine Vorlage ausgewählt</em>');
            $(this).hide();
        });

        // Import (AJAX)
        var isAdmin = <?php echo current_user_can('manage_options') ? 'true':'false'; ?>;
        var nonce   = '<?php echo esc_js($nonce_import); ?>';
        function log(line){ var box=$('#fobi-aek-status'); var t=new Date().toLocaleTimeString(); box.append('<div>['+t+'] '+line+'</div>'); box.scrollTop(box.prop("scrollHeight")); }
        $('#fobi-aek-import-btn').on('click',function(e){
            e.preventDefault(); if(!isAdmin) return;
            $('#fobi-aek-import-spinner').show(); $('#fobi-aek-status').html(''); log('Starte Abruf…');
            $.post(ajaxurl,{action:'fobi_aek_import',_wpnonce:nonce},function(resp){
                $('#fobi-aek-import-spinner').hide();
                if(resp && resp.success){ (resp.data.logs||[]).forEach(function(l){ log(l); }); log('Fertig.'); }
                else { log('Fehler: '+(resp && resp.data && resp.data.message?resp.data.message:'Unbekannter Fehler')); }
            }).fail(function(xhr){ $('#fobi-aek-import-spinner').hide(); log('HTTP-Fehler beim Abruf ('+xhr.status+').'); });
        });
    });
    </script>
    <?php
}
function fobi_aek_reschedule_cron($settings=null){
    if(!$settings) $settings=wp_parse_args(get_option(FOBI_AEK_OPTION_KEY,array()),fobi_aek_default_settings());
    $ts=wp_next_scheduled(FOBI_AEK_CRON_HOOK); if($ts) wp_unschedule_event($ts,FOBI_AEK_CRON_HOOK);
    if($settings['batch_enabled']!=='1') return;
    wp_schedule_event(time()+60, $settings['batch_interval']?:'daily', FOBI_AEK_CRON_HOOK);
}
add_action(FOBI_AEK_CRON_HOOK,'fobi_aek_run_batch_for_all_with_efn');
function fobi_aek_run_batch_for_all_with_efn(){
    $s=wp_parse_args(get_option(FOBI_AEK_OPTION_KEY,array()),fobi_aek_default_settings());
    if($s['batch_enabled']!=='1') return;
    $users=get_users(array('meta_key'=>'EFN','meta_compare'=>'EXISTS','fields'=>array('ID'),'number'=>5000));
    foreach($users as $u){ fobi_aek_import_for_user_id($u->ID,$s,true); }
}
add_action('wp_ajax_fobi_aek_import','fobi_aek_import_ajax');
function fobi_aek_import_ajax(){
    if(!current_user_can('manage_options')) wp_send_json_error(array('message'=>'Keine Berechtigung.'));
    check_ajax_referer('fobi_aek_import_run');
    $s=wp_parse_args(get_option(FOBI_AEK_OPTION_KEY,array()),fobi_aek_default_settings());
    $logs=fobi_aek_import_for_user_id(get_current_user_id(),$s,false);
    wp_send_json_success(array('logs'=>$logs));
}
function fobi_aek_import_for_user_id($user_id,$settings,$silent=false){
    $logs=array(); $efn=preg_replace('/\D+/','',(string)get_user_meta($user_id,'EFN',true));
    if(!$efn){ $msg='Kein EFN für Benutzer-ID '.intval($user_id).' – übersprungen.'; return $silent?array():array($msg); }
    $logs[]="EFN erkannt: ".substr($efn,0,4).'…'.substr($efn,-3);
    $scans_tpl=$settings['scans_endpoint_tpl']; $event_tpl=$settings['event_endpoint_tpl'];
    if(empty($scans_tpl)||empty($event_tpl)){ $logs[]='Endpunkte in den Einstellungen unvollständig.'; return $logs; }
    $scans_url=str_replace('{EFN}',rawurlencode($efn),$scans_tpl);
    $headers=array('Accept'=>'application/json');
    if(!empty($settings['access_token'])) $headers['Authorization']=$settings['access_token'];
    if(!empty($settings['extra_header_key']) && !empty($settings['extra_header_value'])) $headers[$settings['extra_header_key']]=$settings['extra_header_value'];

    $logs[]="Rufe gescannte Teilnahmen ab…";
    $resp=wp_remote_get($scans_url,array('timeout'=>30,'headers'=>$headers));
    if(is_wp_error($resp)){ $logs[]='HTTP-Fehler beim Scans‑Abruf: '.$resp->get_error_message(); return $logs; }
    $code=wp_remote_retrieve_response_code($resp);
    if($code<200||$code>=300){ $logs[]='Scans‑Abruf fehlgeschlagen. HTTP‑Code: '.$code; return $logs; }
    $data=json_decode(wp_remote_retrieve_body($resp),true);
    if(!is_array($data)){ $logs[]='Antwort konnte nicht geparst werden.'; return $logs; }

    $mapping=json_decode($settings['mapping_json'],true); if(!is_array($mapping)) $mapping=array();
    $map=array(); foreach($mapping as $row){ $c=strtoupper(trim(fobi_arr_get($row,'code',''))); if($c!=='') $map[$c]=$row; }

    $imported=0; $skipped=0;
    foreach($data as $row){
        $vnr=trim((string)fobi_arr_get($row,'vnr','')); if($vnr===''){ $skipped++; $logs[]="Übersprungen: Eintrag ohne VNR."; continue; }
        $exists=get_posts(array('post_type'=>'fortbildung','posts_per_page'=>1,'fields'=>'ids','meta_query'=>array('relation'=>'AND',array('key'=>'aek_vnr','value'=>$vnr,'compare'=>'='),array('key'=>'user','value'=>$user_id,'compare'=>'='))));
        if(!empty($exists)){ $skipped++; $logs[]="VNR $vnr bereits vorhanden – übersprungen."; continue; }

        $type_code=strtoupper(trim((string)fobi_arr_get($row,'typeCode','')));
        $date_raw=(string)fobi_arr_get($row,'date','');
        $title=(string)fobi_arr_get($row,'title','');
        $dur=(int)fobi_arr_get($row,'durationMinutes',0);
        $points_api=fobi_arr_get($row,'points',null);

        $event_url=str_replace('{VNR}',rawurlencode($vnr),$event_tpl);
        $detail_resp=wp_remote_get($event_url,array('timeout'=>30,'headers'=>$headers));
        $detail=array();
        if(!is_wp_error($detail_resp) && wp_remote_retrieve_response_code($detail_resp)>=200 && wp_remote_retrieve_response_code($detail_resp)<300){
            $d=json_decode(wp_remote_retrieve_body($detail_resp),true); if(is_array($d)) $detail=$d;
        }
        $location=(string)fobi_arr_get($detail,'location',fobi_arr_get($row,'location',''));
        if(empty($title))     $title=(string)fobi_arr_get($detail,'title','');
        if(empty($type_code)) $type_code=strtoupper((string)fobi_arr_get($detail,'typeCode',''));
        if(empty($dur))       $dur=(int)fobi_arr_get($detail,'durationMinutes',0);
        if($date_raw==='')    $date_raw=(string)fobi_arr_get($detail,'date','');

        $points=0.0;
        if($points_api!==null && $points_api!==''){ $points=floatval($points_api); }
        else{
            $m=isset($map[$type_code])?$map[$type_code]:null;
            if($m){
                $calc=strtolower((string)fobi_arr_get($m,'calc','fixed'));
                $base=floatval(fobi_arr_get($m,'points',0));
                if($calc==='unit'){
                    $unit=max(1,intval(fobi_arr_get($m,'unit_minutes',45)));
                    $units=$dur>0?ceil($dur/$unit):1; $points=$base*$units;
                }elseif($calc==='per_hour'){
                    $hours=$dur>0?($dur/60.0):1.0; $points=$base*$hours;
                }else{ $points=$base; }
            }else{ $logs[]="Hinweis: Kein Mapping für Typcode '{$type_code}' gefunden – Punkte = 0. Bitte Mapping pflegen."; }
        }

        $pid=wp_insert_post(array('post_title'=>$title?$title:'AEK-Veranstaltung '.$vnr,'post_type'=>'fortbildung','post_status'=>'publish'));
        if(is_wp_error($pid) || ! $pid){ $logs[]="Fehler: Eintrag zu VNR $vnr konnte nicht angelegt werden."; $skipped++; continue; }

        $date_store=$date_raw?date('Y-m-d',strtotime($date_raw)):current_time('Y-m-d');
        update_field('date',$date_store,$pid);
        update_field('location',$location,$pid);
        update_field('points',floatval($points),$pid);
        update_field('type','AEK',$pid);
        update_field('user',$user_id,$pid);
        update_field('freigegeben',true,$pid);
        update_post_meta($pid,'aek_vnr',$vnr);
        update_post_meta($pid,'aek_type_code',$type_code);

        $imported++; $logs[]="Importiert: {$title} (VNR {$vnr}) – Punkte: ".number_format($points,1,',','.').($type_code?" [Typ {$type_code}]":'');
    }
    $logs[]="Fazit: Importiert {$imported}, übersprungen {$skipped}.";
    return $logs;
}

/* ============================================================
 * Offene Freigaben (Admin/Frontend)
 * ============================================================ */
function fobi_query_unapproved_fortbildungen(){
    return new WP_Query(array(
        'post_type'=>'fortbildung','post_status'=>'publish','posts_per_page'=>-1,
        'meta_query'=>array('relation'=>'OR',array('key'=>'freigegeben','compare'=>'NOT EXISTS'),array('key'=>'freigegeben','value'=>'1','compare'=>'!=')),
        'orderby'=>'date','order'=>'DESC',
    ));
}
function fobi_render_unapproved_table($context='admin'){
    $q=fobi_query_unapproved_fortbildungen(); ob_start(); $nonce=wp_create_nonce('fobi_unapproved_actions'); ?>
    <style>.fobi-open-table{width:100%;border-collapse:collapse;margin-top:10px}.fobi-open-table th,.fobi-open-table td{border:1px solid #ccc;padding:8px;text-align:left}.fobi-bulkbar{margin:10px 0;padding:8px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px}</style>
    <?php if(!$q->have_posts()){ echo '<p>Keine offenen (nicht freigegebenen) Fortbildungen vorhanden.</p>'; return ob_get_clean(); } ?>
    <div class="fobi-bulkbar">
        <label><input type="checkbox" id="fobi-bulk-select-all"> Alle auswählen</label>
        <button class="button button-primary" id="fobi-bulk-approve-selected">Auswahl freigeben</button>
        <button class="button" id="fobi-bulk-approve-all-visible">Alle (angezeigten) freigeben</button>
        <span id="fobi-bulk-msg" style="margin-left:10px;"></span>
    </div>
    <table class="fobi-open-table" id="fobi-open-table">
        <thead><tr><th style="width:36px;"><input type="checkbox" id="fobi-head-select-all"></th><th>Datum</th><th>Titel</th><th>Benutzer</th><th>Ort</th><th>Punkte</th><th>Art</th><th>Aktion</th></tr></thead>
        <tbody>
        <?php while($q->have_posts()){ $q->the_post();
            $pid=get_the_ID(); $date=(string)get_field('date',$pid); $loc=(string)get_field('location',$pid);
            $pts=get_field('points',$pid); $type=(string)get_field('type',$pid);
            $uid=get_post_meta($pid,'user',true); $uname='(Unbekannt)';
            if($uid){ $u=get_userdata($uid); if($u){ $uname=trim(($u->first_name?:'').' '.($u->last_name?:'')); if($uname==='') $uname=$u->display_name?:$u->user_login; } }
            echo '<tr id="fobi-row-'.esc_attr($pid).'" data-pid="'.esc_attr($pid).'">
                <td><input type="checkbox" class="fobi-row-check" value="'.esc_attr($pid).'"></td>
                <td>'.esc_html( fobi_format_date($date) ).'</td>
                <td><a href="'.esc_url(admin_url('post.php?post='.$pid.'&action=edit')).'" target="_blank" rel="noopener">'.esc_html(get_the_title()).'</a></td>
                <td>'.esc_html($uname).'</td>
                <td>'.esc_html($loc).'</td>
                <td>'.esc_html($pts!==''?$pts:'0').'</td>
                <td>'.esc_html($type).'</td>
                <td><button class="button fobi-approve" data-pid="'.esc_attr($pid).'">Freigeben</button> <button class="button fobi-delete" data-pid="'.esc_attr($pid).'" style="margin-left:6px;">Löschen</button></td>
            </tr>';
        } wp_reset_postdata(); ?>
        </tbody>
    </table>
    <script>
    (function($){
        if(typeof window.ajaxurl==='undefined'){ window.ajaxurl='<?php echo esc_js(admin_url('admin-ajax.php')); ?>'; }
        var nonce='<?php echo esc_js($nonce); ?>';
        function rowGone(pid){ $('#fobi-row-'+pid).fadeOut(200,function(){ $(this).remove(); }); }
        $(document).on('click','.fobi-approve',function(e){
            e.preventDefault(); var pid=$(this).data('pid'); var btn=$(this); btn.prop('disabled',true).text('Wird freigegeben…');
            $.post(window.ajaxurl,{action:'fobi_mark_fortbildung_approved',post_id:pid,_wpnonce:nonce},function(resp){
                if(resp && resp.success){ rowGone(pid); } else { btn.prop('disabled',false).text('Freigeben'); alert('Fehler: '+(resp && resp.data && resp.data.message?resp.data.message:'Unbekannt')); }
            }).fail(function(){ btn.prop('disabled',false).text('Freigeben'); alert('HTTP-Fehler'); });
        });
        $(document).on('click','.fobi-delete',function(e){
            e.preventDefault(); var pid=$(this).data('pid'); if(!confirm('Eintrag wirklich löschen?')) return;
            var btn=$(this); btn.prop('disabled',true).text('Lösche…');
            $.post(window.ajaxurl,{action:'fobi_delete_fortbildung_admin',post_id:pid,_wpnonce:nonce},function(resp){
                if(resp && resp.success){ rowGone(pid); } else { btn.prop('disabled',false).text('Löschen'); alert('Fehler: '+(resp && resp.data && resp.data.message?resp.data.message:'Unbekannt')); }
            }).fail(function(){ btn.prop('disabled',false).text('Löschen'); alert('HTTP-Fehler'); });
        });

        $('#fobi-head-select-all,#fobi-bulk-select-all').on('change',function(){ var s=$(this).is(':checked'); $('.fobi-row-check').prop('checked',s); $('#fobi-head-select-all,#fobi-bulk-select-all').prop('checked',s); });
        function collectVisibleIds(){ var ids=[]; $('#fobi-open-table tbody tr').each(function(){ var pid=$(this).data('pid'); if(pid){ ids.push(pid); } }); return ids; }
        function collectSelectedIds(){ var ids=[]; $('.fobi-row-check:checked').each(function(){ ids.push($(this).val()); }); return ids; }
        function bulkApprove(ids){
            if(!ids||!ids.length){ $('#fobi-bulk-msg').text('Keine Einträge ausgewählt.'); return; }
            $('#fobi-bulk-msg').text('Gebe '+ids.length+' Einträge frei …');
            $.post(window.ajaxurl,{action:'fobi_bulk_mark_fortbildung_approved',post_ids:ids,_wpnonce:nonce},function(resp){
                if(resp && resp.success){ (resp.data.updated_ids||[]).forEach(function(pid){ $('#fobi-row-'+pid).remove(); }); $('#fobi-bulk-msg').text('Freigegeben: '+(resp.data.count||0)); }
                else { $('#fobi-bulk-msg').text('Fehler bei der Massenfreigabe.'); }
            }).fail(function(){ $('#fobi-bulk-msg').text('HTTP-Fehler bei der Massenfreigabe.'); });
        }
        $('#fobi-bulk-approve-selected').on('click',function(e){ e.preventDefault(); bulkApprove(collectSelectedIds()); });
        $('#fobi-bulk-approve-all-visible').on('click',function(e){ e.preventDefault(); if(!confirm('Alle angezeigten Einträge freigeben?')) return; bulkApprove(collectVisibleIds()); });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
add_action('admin_menu', function(){
    add_submenu_page('edit.php?post_type=fortbildung','Offene Freigaben','Offene Freigaben','manage_options','fobi-unapproved',function(){ if(!current_user_can('manage_options')) wp_die('Keine Berechtigung.'); echo '<div class="wrap"><h1>Offene Freigaben</h1>'.fobi_render_unapproved_table('admin').'</div>'; });
});
add_shortcode('offene_fortbildungen', function(){
    if(!is_user_logged_in()) return '<p>Bitte loggen Sie sich ein.</p>';
    $is_delegate = get_user_meta(get_current_user_id(),'delegate',true)==='1';
    if(!current_user_can('manage_options') && ! $is_delegate) return '<p>Keine Berechtigung.</p>';
    ob_start(); ?>
    <script>(function(){ if(typeof window.ajaxurl==='undefined'){ window.ajaxurl='<?php echo esc_js(admin_url('admin-ajax.php')); ?>'; }})();</script>
    <h3>Offene Freigaben</h3>
    <?php echo fobi_render_unapproved_table('frontend'); return ob_get_clean();
});
add_action('wp_ajax_fobi_mark_fortbildung_approved', function(){
    check_ajax_referer('fobi_unapproved_actions');
    if(!is_user_logged_in()) wp_send_json_error(array('message'=>'Nicht eingeloggt.'));
    $is_delegate=get_user_meta(get_current_user_id(),'delegate',true)==='1';
    if(!current_user_can('manage_options') && ! $is_delegate) wp_send_json_error(array('message'=>'Keine Berechtigung.'));
    $pid=isset($_POST['post_id'])?intval($_POST['post_id']):0;
    if(!$pid || get_post_type($pid)!=='fortbildung') wp_send_json_error(array('message'=>'Ungültige Post-ID.'));
    update_field('freigegeben',true,$pid); wp_send_json_success(array('message'=>'Freigeben.'));
});
add_action('wp_ajax_fobi_delete_fortbildung_admin', function(){
    check_ajax_referer('fobi_unapproved_actions');
    if(!is_user_logged_in()) wp_send_json_error(array('message'=>'Nicht eingeloggt.'));
    $is_delegate=get_user_meta(get_current_user_id(),'delegate',true)==='1';
    if(!current_user_can('manage_options') && ! $is_delegate) wp_send_json_error(array('message'=>'Keine Berechtigung.'));
    $pid=isset($_POST['post_id'])?intval($_POST['post_id']):0;
    if(!$pid || get_post_type($pid)!=='fortbildung') wp_send_json_error(array('message'=>'Ungültige Post-ID.'));
    $deleted=wp_delete_post($pid,true); if(!$deleted) wp_send_json_error(array('message'=>'Löschen fehlgeschlagen.')); wp_send_json_success(array('message'=>'Fortbildung gelöscht.'));
});
add_action('wp_ajax_fobi_bulk_mark_fortbildung_approved', function(){
    check_ajax_referer('fobi_unapproved_actions');
    if(!current_user_can('manage_options')) wp_send_json_error(array('message'=>'Keine Berechtigung.'));
    $ids=isset($_POST['post_ids'])?(array)$_POST['post_ids']:array();
    $ids=array_map('intval',$ids); $ids=array_filter($ids);
    if(empty($ids)) wp_send_json_error(array('message'=>'Keine IDs übertragen.'));
    $updated=array(); foreach($ids as $pid){ if(get_post_type($pid)==='fortbildung'){ update_field('freigegeben',true,$pid); $updated[]=$pid; } }
    wp_send_json_success(array('count'=>count($updated),'updated_ids'=>$updated));
});

/* ============================================================
 * Delegierten-Ansicht
 * ============================================================ */
add_shortcode('delegierte_liste','delegierte_liste_shortcode');
function delegierte_liste_shortcode(){
    if(!is_user_logged_in()) return '<p>Bitte loggen Sie sich ein.</p>';
    $delegate=get_user_meta(get_current_user_id(),'delegate',true);
    if($delegate!='1' && ! current_user_can('manage_options')) return '<p>Keine Berechtigung.</p>';
    ob_start(); ?>
    <div class="wrap">
        <h2>Delegierte Übersicht</h2>
        <p>
            <label for="ebcp-user-search">Benutzer suchen:</label>
            <input type="text" id="ebcp-user-search" placeholder="Name eingeben">
            <button id="ebcp-load-delegierte" class="button button-primary">Nutzer mit Nachweisen laden</button>
        </p>
        <div id="ebcp-delegierte-liste-container"></div>
        <p style="margin-top:6px;color:#555;"><em>Hinweis:</em> Der EBCP‑Delegierte kann Fortbildungsnachweise einsehen. Zu jedem Nachweis werden die Fortbildungen des jeweiligen Zeitraums inkl. Erstellungsdatum angezeigt.</p>
    </div>
    <script>
    (function($){
        if(typeof window.ajaxurl==='undefined'){ window.ajaxurl='<?php echo esc_js(admin_url('admin-ajax.php')); ?>'; }
        $('#ebcp-load-delegierte').on('click',function(e){
            e.preventDefault(); var term=$('#ebcp-user-search').val();
            $('#ebcp-delegierte-liste-container').html('Lade Liste…');
            $.post(window.ajaxurl,{action:'ebcp_load_delegierte_liste',user_search:term},function(resp){ $('#ebcp-delegierte-liste-container').html(resp); });
        });
        jQuery(function(){ $('#ebcp-load-delegierte').trigger('click'); });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
add_action('wp_ajax_ebcp_load_delegierte_liste','ebcp_load_delegierte_liste_ajax');
function ebcp_load_delegierte_liste_ajax(){
    if(!is_user_logged_in()){ echo 'Bitte loggen Sie sich ein.'; wp_die(); }
    $cur=get_current_user_id(); $delegate=get_user_meta($cur,'delegate',true);
    if($delegate!='1' && ! current_user_can('manage_options')){ echo 'Keine Berechtigung.'; wp_die(); }
    $term=isset($_POST['user_search'])?sanitize_text_field($_POST['user_search']):'';
    $certs=new WP_Query(array('post_type'=>'fobi_certificate','post_status'=>'publish','fields'=>'ids','posts_per_page'=>-1));
    $uids=array(); if($certs->have_posts()){ foreach($certs->posts as $cid){ $u=intval(get_post_meta($cid,'user_id',true)); if($u) $uids[$u]=true; } }
    $uids=array_keys($uids);
    if(empty($uids)){ echo '<p>Keine Nutzer mit Nachweisen gefunden.</p>'; wp_die(); }

    $args=array('include'=>$uids,'fields'=>array('ID','user_login','display_name'));
    if($term!==''){ $args['search']='*'.$term.'*'; $args['search_columns']=array('user_login','display_name','first_name','last_name'); }
    $users=get_users($args);
    if(empty($users)){ echo '<p>Keine passenden Nutzer gefunden.</p>'; wp_die(); }

    echo '<table class="widefat" style="max-width:800px;"><thead><tr><th>Benutzer</th><th>Nachweise</th><th>Aktion</th></tr></thead><tbody>';
    foreach($users as $u){
        $name=trim(($u->first_name??'').' '.($u->last_name??'')); if(!$name) $name=$u->display_name?:$u->user_login;
        $count=new WP_Query(array('post_type'=>'fobi_certificate','post_status'=>'publish','meta_key'=>'user_id','meta_value'=>$u->ID,'fields'=>'ids','posts_per_page'=>-1));
        $num=intval($count->found_posts);
        echo '<tr><td>'.esc_html($name).'</td><td>'.esc_html($num).'</td><td><button class="delegate-show-certs button" data-userid="'.esc_attr($u->ID).'">Nachweise anzeigen</button></td></tr>';
        echo '<tr id="delegate-certs-wrapper-'.esc_attr($u->ID).'"><td colspan="3"></td></tr>';
    }
    echo '</tbody></table>'; ?>
    <script>
    (function($){
        if(typeof window.ajaxurl==='undefined'){ window.ajaxurl='<?php echo esc_js(admin_url('admin-ajax.php')); ?>'; }
        $('.delegate-show-certs').off('click').on('click',function(e){
            e.preventDefault();
            var uid=$(this).data('userid'); var row=$('#delegate-certs-wrapper-'+uid).find('td');
            row.html('Lade Nachweise…');
            $.post(window.ajaxurl,{action:'delegate_get_user_certs',user_id:uid},function(resp){ row.html(resp); });
        });
    })(jQuery);
    </script>
    <?php
    wp_die();
}
add_action('wp_ajax_delegate_get_user_certs',function(){
    if(!is_user_logged_in()){ echo 'Nicht eingeloggt.'; wp_die(); }
    $cur=get_current_user_id(); $delegate=get_user_meta($cur,'delegate',true);
    if($delegate!='1' && ! current_user_can('manage_options')){ echo 'Keine Berechtigung.'; wp_die(); }
    $uid=isset($_POST['user_id'])?intval($_POST['user_id']):0; if(!$uid){ echo 'Ungültige Benutzer-ID.'; wp_die(); }

    $certs=new WP_Query(array('post_type'=>'fobi_certificate','post_status'=>'publish','meta_key'=>'user_id','meta_value'=>$uid,'posts_per_page'=>-1,'orderby'=>'date','order'=>'DESC'));
    if(!$certs->have_posts()){ echo '<p>Keine Nachweise vorhanden.</p>'; wp_die(); }

    ob_start(); ?>
    <div class="delegate-certs-list">
        <?php while($certs->have_posts()){ $certs->the_post();
            $cid=get_the_ID(); $label=get_post_meta($cid,'period_label',true);
            $sum=get_post_meta($cid,'sum_points',true); $created=get_post_meta($cid,'created_at',true);
            $download=add_query_arg(array('cid'=>$cid,'sig'=>get_post_meta($cid,'sig',true)), home_url('/dgptm-download/')); ?>
            <div style="border:1px solid #ccd0d4;padding:10px;margin:10px 0;background:#fff;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                    <div><strong>Zeitraum:</strong> <?php echo esc_html($label); ?> &nbsp; <strong>Erstellt am:</strong> <?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($created))); ?> &nbsp; <strong>Summe:</strong> <?php echo esc_html(number_format((float)$sum,1,',','.')); ?></div>
                    <div><a class="button" href="<?php echo esc_url($download); ?>" target="_blank" rel="noopener">Nachweis herunterladen</a></div>
                </div>
                <div class="delegate-certs-fobis" id="cert-fobis-<?php echo esc_attr($cid); ?>" style="margin-top:8px;">Lade Fortbildungen…</div>
            </div>
            <script>
            jQuery(function($){ $.post(ajaxurl,{action:'delegate_get_fortbildung_list_for_cert',user_id:<?php echo json_encode($uid); ?>,cid:<?php echo json_encode($cid); ?>},function(resp){ $('#cert-fobis-<?php echo esc_js($cid); ?>').html(resp); }); });
            </script>
        <?php } wp_reset_postdata(); ?>
    </div>
    <?php
    echo ob_get_clean(); wp_die();
});
add_action('wp_ajax_delegate_get_fortbildung_list_for_cert', function(){
    if(!is_user_logged_in()){ echo 'Nicht eingeloggt.'; wp_die(); }
    $cur=get_current_user_id(); $delegate=get_user_meta($cur,'delegate',true);
    if($delegate!='1' && ! current_user_can('manage_options')){ echo 'Keine Berechtigung.'; wp_die(); }
    $uid=isset($_POST['user_id'])?intval($_POST['user_id']):0; $cid=isset($_POST['cid'])?intval($_POST['cid']):0;
    if(!$uid || !$cid){ echo 'Parameter fehlen.'; wp_die(); }
    $from=get_post_meta($cid,'period_from',true); $to=get_post_meta($cid,'period_to',true);
    echo get_fortbildungen_table_delegate($uid,$from,$to); wp_die();
});
function get_fortbildungen_table_delegate($user_id,$date_from='',$date_to=''){
    $args=array('post_type'=>'fortbildung','post_status'=>'publish','posts_per_page'=>-1,'meta_query'=>array(array('key'=>'user','value'=>$user_id,'compare'=>'=')));
    if($date_from && $date_to){ $args['meta_query'][]=array('key'=>'date','value'=>array($date_from,$date_to),'compare'=>'BETWEEN','type'=>'DATE'); }
    $q=new WP_Query($args); if(!$q->have_posts()) return '<p>Keine Fortbildungen im Zeitraum gefunden.</p>';
    ob_start(); ?>
    <style>.fortbildung-liste-delegate{width:100%;border-collapse:collapse}.fortbildung-liste-delegate th,.fortbildung-liste-delegate td{border:1px solid #ccc;padding:8px;text-align:left}.greyed-out{color:#999}</style>
    <table class="fortbildung-liste-delegate"><thead><tr><th>Datum</th><th>Titel</th><th>Ort</th><th>Punkte</th><th>Art</th><th>Freigegeben</th><th>Aktion</th></tr></thead><tbody>
    <?php while($q->have_posts()){ $q->the_post();
        $pid=get_the_ID(); $d=(string)get_field('date',$pid); $title=get_the_title($pid); $loc=get_field('location',$pid);
        $pts=get_field('points',$pid); $type=get_field('type',$pid); $free=get_field('freigegeben',$pid);
        $is_free=fobi_is_freigegeben($free); $row=$is_free?'':'greyed-out'; ?>
        <tr class="<?php echo esc_attr($row); ?>">
            <td><?php echo esc_html($d); ?></td><td><?php echo esc_html($title); ?></td><td><?php echo esc_html($loc); ?></td><td><?php echo esc_html($pts); ?></td><td><?php echo esc_html($type); ?></td><td><?php echo esc_html(fobi_display_ja_nein($free)); ?></td>
            <td>
                <button class="delegate-delete-fortbildung button" data-postid="<?php echo esc_attr($pid); ?>"><span class="dashicons dashicons-trash"></span></button>
                <?php if(!$is_free): ?><button class="delegate-freigeben-fortbildung button" data-postid="<?php echo esc_attr($pid); ?>">Freigeben</button><?php endif; ?>
            </td>
        </tr>
    <?php } wp_reset_postdata(); ?>
    </tbody></table>
    <script>
    (function($){
        if(typeof window.ajaxurl==='undefined'){ window.ajaxurl='<?php echo esc_js(admin_url('admin-ajax.php')); ?>'; }
        $('.delegate-delete-fortbildung').off('click').on('click',function(e){
            e.preventDefault(); if(!confirm('Fortbildung wirklich löschen?')) return;
            var id=$(this).data('postid'),td=$(this).closest('td'); td.html('Lösche…');
            $.post(window.ajaxurl,{action:'delegate_delete_fortbildung',post_id:id},function(r){ if(r.success){ td.closest('tr').remove(); } else { td.html('Fehler: '+(r.data && r.data.message?r.data.message:'unbekannt')); } });
        });
        $('.delegate-freigeben-fortbildung').off('click').on('click',function(e){
            e.preventDefault(); var id=$(this).data('postid'),td=$(this).closest('td'); td.html('Wird freigegeben…');
            $.post(window.ajaxurl,{action:'delegate_freigeben_fortbildung',post_id:id},function(r){ if(r.success){ var tr=td.closest('tr'); tr.find('td:nth-child(6)').text('Ja'); tr.removeClass('greyed-out'); td.html('Freigegeben'); } else { td.html('Fehler: '+(r.data && r.data.message?r.data.message:'unbekannt')); } });
        });
    })(jQuery);
    </script>
    <?php return ob_get_clean();
}
add_action('wp_ajax_delegate_load_form','delegate_load_form_ajax');
function delegate_load_form_ajax(){
    if(!is_user_logged_in()){ echo 'Nicht eingeloggt.'; wp_die(); }
    $cur=get_current_user_id(); $delegate=get_user_meta($cur,'delegate',true);
    if($delegate!='1' && ! current_user_can('manage_options')){ echo 'Keine Berechtigung.'; wp_die(); }
    $uid=isset($_POST['user_id'])?intval($_POST['user_id']):0; if(!$uid){ echo 'Ungültige Benutzer-ID.'; wp_die(); } ?>
    <form id="delegate-fortbildung-form-<?php echo esc_attr($uid); ?>">
        <p><label for="df_title_<?php echo esc_attr($uid); ?>">Titel:</label> <input type="text" id="df_title_<?php echo esc_attr($uid); ?>" name="title" required></p>
        <p><label for="df_date_<?php echo esc_attr($uid); ?>">Datum:</label> <input type="date" id="df_date_<?php echo esc_attr($uid); ?>" name="date" required></p>
        <p><label for="df_location_<?php echo esc_attr($uid); ?>">Ort:</label> <input type="text" id="df_location_<?php echo esc_attr($uid); ?>" name="location"></p>
        <p><label for="df_points_<?php echo esc_attr($uid); ?>">Punkte:</label> <input type="number" step="0.1" id="df_points_<?php echo esc_attr($uid); ?>" name="points"></p>
        <p><label for="df_type_<?php echo esc_attr($uid); ?>">Art:</label> <input type="text" id="df_type_<?php echo esc_attr($uid); ?>" name="type"></p>
        <p><button type="submit" class="button">Fortbildung hinzufügen</button></p>
        <div class="delegate-fortbildung-form-message"></div>
        <input type="hidden" name="user_id" value="<?php echo esc_attr($uid); ?>">
    </form>
    <script>
    (function($){
        if(typeof window.ajaxurl==='undefined'){ window.ajaxurl='<?php echo esc_js(admin_url('admin-ajax.php')); ?>'; }
        $('#delegate-fortbildung-form-<?php echo esc_attr($uid); ?>').on('submit',function(e){
            e.preventDefault(); var f=$(this), data=f.serialize();
            $.post(window.ajaxurl, data+'&action=delegate_add_fortbildung', function(r){
                if(r.success){ f.find('.delegate-fortbildung-form-message').html('<p style="color:green">'+r.data.message+'</p>'); f[0].reset(); }
                else{ f.find('.delegate-fortbildung-form-message').html('<p style="color:red">'+(r.data && r.data.message?r.data.message:'Fehler')+'</p>'); }
            });
        });
    })(jQuery);
    </script>
    <?php wp_die();
}
add_action('wp_ajax_delegate_add_fortbildung', function(){
    if(!is_user_logged_in()) wp_send_json_error(array('message'=>'Nicht eingeloggt.'));
    $cur=get_current_user_id(); $delegate=get_user_meta($cur,'delegate',true);
    if($delegate!='1' && ! current_user_can('manage_options')) wp_send_json_error(array('message'=>'Keine Berechtigung.'));
    $uid=isset($_POST['user_id'])?intval($_POST['user_id']):0; if(!$uid) wp_send_json_error(array('message'=>'Ungültige Ziel-Benutzer-ID.'));
    $title=sanitize_text_field($_POST['title'] ?? ''); $date=sanitize_text_field($_POST['date'] ?? ''); $loc=sanitize_text_field($_POST['location'] ?? ''); $points=floatval($_POST['points'] ?? 0); $type=sanitize_text_field($_POST['type'] ?? '');
    if(empty($title)||empty($date)) wp_send_json_error(array('message'=>'Titel und Datum sind erforderlich.'));
    $pid=wp_insert_post(array('post_title'=>$title,'post_type'=>'fortbildung','post_status'=>'publish'));
    if(is_wp_error($pid)) wp_send_json_error(array('message'=>'Fehler beim Erstellen des Eintrags.'));
    update_field('date',$date,$pid); update_field('location',$loc,$pid); update_field('points',$points,$pid); update_field('type',$type,$pid); update_field('user',$uid,$pid); update_field('freigegeben',false,$pid);
    wp_send_json_success(array('message'=>'Fortbildung hinzugefügt.'));
});
add_action('wp_ajax_delegate_delete_fortbildung', function(){
    if(!is_user_logged_in()) wp_send_json_error(array('message'=>'Nicht eingeloggt.'));
    $cur=get_current_user_id(); $delegate=get_user_meta($cur,'delegate',true);
    if($delegate!='1' && ! current_user_can('manage_options')) wp_send_json_error(array('message'=>'Keine Berechtigung.'));
    $pid=isset($_POST['post_id'])?intval($_POST['post_id']):0; if(!$pid) wp_send_json_error(array('message'=>'Ungültige Post-ID.'));
    $del=wp_delete_post($pid,true); if(!$del) wp_send_json_error(array('message'=>'Löschen fehlgeschlagen.')); wp_send_json_success(array('message'=>'Fortbildung gelöscht.'));
});
add_action('wp_ajax_delegate_freigeben_fortbildung', function(){
    if(!is_user_logged_in()) wp_send_json_error(array('message'=>'Nicht eingeloggt.'));
    $cur=get_current_user_id(); $delegate=get_user_meta($cur,'delegate',true);
    if($delegate!='1' && ! current_user_can('manage_options')) wp_send_json_error(array('message'=>'Keine Berechtigung.'));
    $pid=isset($_POST['post_id'])?intval($_POST['post_id']):0; if(!$pid) wp_send_json_error(array('message'=>'Ungültige Post-ID.'));
    update_field('freigegeben',true,$pid); wp_send_json_success(array('message'=>'Freigegeben.'));
});

/* ============================================================
 * Verifikation & Download – Endpoints
 *   - Neu: /verify/ (Form) & /verify/{CODE}
 *   - Alt bleibt: /dgptm-verify?cid=...&sig=...
 * ============================================================ */
add_action('init', function(){
    // Neue, kurze Verifikation
    add_rewrite_rule('^verify/([A-Za-z0-9]{1,8})/?$', 'index.php?dgptm_verify_code=$matches[1]', 'top');
    add_rewrite_rule('^verify/?$', 'index.php?dgptm_verify_page=1', 'top');

    // Kompatibel: alte Routen
    add_rewrite_rule('^dgptm-verify/?','index.php?dgptm_verify=1','top');
    add_rewrite_rule('^dgptm-download/?','index.php?dgptm_download=1','top');

    // täglicher Zertifikats-Cleanup planen (falls noch nicht)
    if ( ! wp_next_scheduled( FOBI_CERT_CLEANUP_HOOK ) ) {
        wp_schedule_event( time() + 120, 'daily', FOBI_CERT_CLEANUP_HOOK );
    }
});
add_filter('query_vars', function($vars){
    $vars[]='dgptm_verify'; $vars[]='dgptm_download';
    $vars[]='dgptm_verify_page'; $vars[]='dgptm_verify_code';
    return $vars;
});
add_action('template_redirect', function(){

    /* ---------- Neue Verifikation: /verify/{CODE} ---------- */
    if ( get_query_var('dgptm_verify_code') ) {
        $code = preg_replace('/[^A-Za-z0-9]/','', get_query_var('dgptm_verify_code') );
        status_header(200); header('Content-Type:text/html; charset='.get_bloginfo('charset'));
        echo '<!doctype html><html><head><meta charset="'.esc_attr(get_bloginfo('charset')).'"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nachweis-Verifikation</title><style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;padding:20px;background:#f5f5f5} .card{max-width:720px;margin:0 auto;border:1px solid #ccc;border-radius:8px;padding:16px;background:#fff} .ok{color:#0a7} .bad{color:#b00} .muted{color:#666}</style></head><body><div class="card">';
        if(!$code){ echo '<h2 class="bad">Ungültiger Code</h2><p>Bitte geben Sie einen gültigen Code ein.</p></div></body></html>'; exit; }

        $q = new WP_Query(array(
            'post_type'=>'fobi_certificate','post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids',
            'meta_query'=>array(array('key'=>'verify_code','value'=>$code,'compare'=>'=')),
        ));
        if ( ! $q->have_posts() ) {
            echo '<h2 class="bad">Nachweis nicht gefunden</h2><p>Der eingegebene Code <strong>'.esc_html($code).'</strong> ist nicht bekannt. Bitte prüfen Sie Ihre Eingabe.</p><p class="muted">Tipp: Groß-/Kleinschreibung egal. Code max. 8 Zeichen.</p>';
            echo '<p><a href="'.esc_url( home_url('/verify/') ).'">Zur Code-Eingabe</a></p></div></body></html>'; exit;
        }
        $cid = (int) $q->posts[0];
        $uid=intval(get_post_meta($cid,'user_id',true));
        $from=(string)get_post_meta($cid,'period_from',true); $to=(string)get_post_meta($cid,'period_to',true);
        $sum=(float)get_post_meta($cid,'sum_points',true);
        $user=$uid?get_userdata($uid):null;
        $name=$user?( trim(($user->first_name?:'').' '.($user->last_name?:'')) ?: ($user->display_name?:$user->user_login) ):'(Unbekannt)';
        $label=get_post_meta($cid,'period_label',true); $created=get_post_meta($cid,'created_at',true);
        $url=(string)get_post_meta($cid,'file_url',true);

        echo '<h2 class="ok">Nachweis gültig</h2>';
        echo '<p><strong>Inhaber:</strong> '.esc_html($name).'<br><strong>Zeitraum:</strong> '.esc_html($label).'<br><strong>Gesamtpunkte:</strong> '.esc_html(number_format($sum,1,',','.')).'<br><strong>Nachweis erstellt am:</strong> '.esc_html(date_i18n('d.m.Y H:i',strtotime($created))).'</p>';
        if($url) echo '<p><a href="'.esc_url($url).'" target="_blank" rel="noopener">PDF ansehen</a></p>';
        echo '<p class="muted">Hinweis: Mit diesem Code ist der Nachweis für <strong>365 Tage</strong> ab Erstellungsdatum nachprüfbar.</p>';
        echo '</div></body></html>'; exit;
    }

    /* ---------- Neue Verifikation: /verify/ (Form) ---------- */
    if ( intval(get_query_var('dgptm_verify_page')) === 1 ) {
        $code = '';
        if ( isset($_POST['verify_code']) ) { $code = preg_replace('/[^A-Za-z0-9]/','', (string)$_POST['verify_code'] ); }
        status_header(200); header('Content-Type:text/html; charset='.get_bloginfo('charset'));
        echo '<!doctype html><html><head><meta charset="'.esc_attr(get_bloginfo('charset')).'"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nachweis verifizieren</title><style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;padding:20px;background:#f5f5f5} .card{max-width:720px;margin:0 auto;border:1px solid #ccc;border-radius:8px;padding:16px;background:#fff} input[type=text]{padding:8px;font-size:16px;width:220px} button{padding:8px 14px;font-size:16px}</style></head><body><div class="card">';
        echo '<h2>Nachweis verifizieren</h2>';
        echo '<form method="post" action="'.esc_url( home_url('/verify/') ).'"><p><label for="verify_code">Code:</label> <input type="text" id="verify_code" name="verify_code" maxlength="8" value="'.esc_attr($code).'" placeholder="z. B. ABCD2345"> <button type="submit">Prüfen</button></p></form>';
        if($code!==''){
            echo '<p>Weiter zur Prüfung: <a href="'.esc_url( home_url('/verify/'.rawurlencode($code).'/') ).'">'.esc_html( home_url('/verify/'.rawurlencode($code).'/') ).'</a></p>';
        }
        echo '</div></body></html>'; exit;
    }

    /* ---------- Kompatibel: alte Verifikation per cid+sig ---------- */
    if( intval(get_query_var('dgptm_verify')) === 1 ){
        $cid=absint($_GET['cid'] ?? 0); $sig=sanitize_text_field($_GET['sig'] ?? '');
        status_header(200); header('Content-Type:text/html; charset='.get_bloginfo('charset'));
        echo '<!doctype html><html><head><meta charset="'.esc_attr(get_bloginfo('charset')).'"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nachweis-Prüfung</title><style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;padding:20px} .card{max-width:680px;margin:0 auto;border:1px solid #ccc;border-radius:8px;padding:16px;background:#fff} .ok{color:#0a7} .bad{color:#b00}</style></head><body><div class="card">';
        if(!$cid || !$sig){ echo '<h2 class="bad">Ungültige Parameter</h2><p>Es fehlen Angaben.</p></div></body></html>'; exit; }
        $uid=intval(get_post_meta($cid,'user_id',true));
        $from=(string)get_post_meta($cid,'period_from',true); $to=(string)get_post_meta($cid,'period_to',true);
        $sum=number_format(floatval(get_post_meta($cid,'sum_points',true)),1,'.','');
        $exp=hash_hmac('sha256', $cid.'|'.$uid.'|'.$from.'|'.$to.'|'.$sum, wp_salt('auth'));
        if(!hash_equals($exp,$sig)){ echo '<h2 class="bad">Nachweis ungültig</h2><p>Signaturprüfung fehlgeschlagen.</p></div></body></html>'; exit; }
        $user=$uid?get_userdata($uid):null;
        $name=$user?( trim(($user->first_name?:'').' '.($user->last_name?:'')) ?: ($user->display_name?:$user->user_login) ):'(Unbekannt)';
        $label=get_post_meta($cid,'period_label',true); $created=get_post_meta($cid,'created_at',true);
        echo '<h2 class="ok">Nachweis gültig</h2><p><strong>Inhaber:</strong> '.esc_html($name).'<br><strong>Zeitraum:</strong> '.esc_html($label).'<br><strong>Gesamtpunkte:</strong> '.esc_html(number_format((float)$sum,1,',','.')).'<br><strong>Nachweis erstellt am:</strong> '.esc_html(date_i18n('d.m.Y H:i',strtotime($created))).'</p>';
        $url=(string)get_post_meta($cid,'file_url',true); if($url) echo '<p><a href="'.esc_url($url).'" target="_blank" rel="noopener">PDF ansehen</a></p>';
        echo '<p><em>Hinweis:</em> Mit dem QR‑Code ist dieser Nachweis für <strong>365 Tage</strong> ab Erstellungsdatum nachprüfbar.</p>';
        echo '</div></body></html>'; exit;
    }

    /* ---------- Download ---------- */
    if( intval(get_query_var('dgptm_download')) === 1 ){
        $cid=absint($_GET['cid'] ?? 0); $sig=sanitize_text_field($_GET['sig'] ?? '');
        if(!$cid || !$sig){ status_header(400); echo 'Parameter fehlen.'; exit; }
        $uid=intval(get_post_meta($cid,'user_id',true));
        $from=(string)get_post_meta($cid,'period_from',true); $to=(string)get_post_meta($cid,'period_to',true);
        $sum=number_format(floatval(get_post_meta($cid,'sum_points',true)),1,'.','');
        $exp=hash_hmac('sha256',$cid.'|'.$uid.'|'.$from.'|'.$to.'|'.$sum, wp_salt('auth'));
        if(!hash_equals($exp,$sig)){ status_header(403); echo 'Ungültige Signatur.'; exit; }

        $path=(string)get_post_meta($cid,'file_path',true);
        if($path && file_exists($path)){
            $filename = wp_basename($path);
            $fallback = preg_replace('/[^A-Za-z0-9._-]/','_', $filename);
            nocache_headers();
            header('Content-Type: application/pdf');
            header('Content-Length: '.filesize($path));
            header('Content-Disposition: attachment; filename="'.$fallback.'"; filename*=UTF-8\'\'' . rawurlencode($filename));
            header('X-Content-Type-Options: nosniff');
            readfile($path);
            exit;
        }
        $url=(string)get_post_meta($cid,'file_url',true); if($url){ wp_redirect($url); exit; }
        status_header(404); echo 'Datei nicht gefunden.'; exit;
    }
});
register_activation_hook(__FILE__, function(){
    // Rewrites setzen
    add_rewrite_rule('^verify/([A-Za-z0-9]{1,8})/?$', 'index.php?dgptm_verify_code=$matches[1]', 'top');
    add_rewrite_rule('^verify/?$', 'index.php?dgptm_verify_page=1', 'top');
    add_rewrite_rule('^dgptm-verify/?','index.php?dgptm_verify=1','top');
    add_rewrite_rule('^dgptm-download/?','index.php?dgptm_download=1','top');
    flush_rewrite_rules();
    if ( ! wp_next_scheduled( FOBI_CERT_CLEANUP_HOOK ) ) {
        wp_schedule_event( time() + 120, 'daily', FOBI_CERT_CLEANUP_HOOK );
    }
});
register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
    $ts = wp_next_scheduled( FOBI_CERT_CLEANUP_HOOK );
    if ( $ts ) wp_unschedule_event( $ts, FOBI_CERT_CLEANUP_HOOK );
});

/* ============================================================
 * Zertifikats-Cleanup (>365 Tage) + Admin-Spalten für Nachweise
 * ============================================================ */
add_action( FOBI_CERT_CLEANUP_HOOK, 'fobi_cleanup_old_certificates' );
function fobi_cleanup_old_certificates(){
    $threshold = strtotime('-365 days', current_time('timestamp'));
    $q = new WP_Query(array(
        'post_type'=>'fobi_certificate','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids',
        'date_query'=>array(array('before'=>date('Y-m-d H:i:s',$threshold),'inclusive'=>true)),
    ));
    if ( ! $q->have_posts() ) return;
    foreach ( $q->posts as $cid ){
        $path = (string) get_post_meta($cid,'file_path',true);
        if ( $path && file_exists($path) ) @unlink($path);
        wp_delete_post($cid, true);
    }
}
add_filter( 'manage_fobi_certificate_posts_columns', function($cols){
    $cols['period']  = 'Zeitraum';
    $cols['created'] = 'Erstellt am';
    $cols['sumpts']  = 'Punkte';
    $cols['code']    = 'Verifizierungs‑Code';
    $cols['file']    = 'Datei';
    return $cols;
});
add_action( 'manage_fobi_certificate_posts_custom_column', function($col,$post_id){
    if($col==='period'){ echo esc_html( get_post_meta($post_id,'period_label',true) ); }
    if($col==='created'){ $c=get_post_meta($post_id,'created_at',true); echo $c? esc_html( date_i18n('d.m.Y H:i', strtotime($c)) ) : ''; }
    if($col==='sumpts'){ $s=get_post_meta($post_id,'sum_points',true); echo esc_html( number_format((float)$s,1,',','.') ); }
    if($col==='code'){ echo esc_html( get_post_meta($post_id,'verify_code',true) ); }
    if($col==='file'){ $u=get_post_meta($post_id,'file_url',true); if($u) echo '<a href="'.esc_url($u).'" target="_blank" rel="noopener">Öffnen</a>'; }
},10,2);

/* ============================================================
 * Bestätigungs‑Shortcode (Token‑Workflow)
 * ============================================================ */
add_shortcode('fortbildung_bestaetigung','fortbildung_bestaetigung_shortcode');
function fortbildung_bestaetigung_shortcode() {
    if(isset($_GET['token'])){ $token=sanitize_text_field($_GET['token']); }
    elseif(isset($_POST['token'])){ $token=sanitize_text_field($_POST['token']); }
    else{ return '<p>Kein Token gefunden.</p>'; }

    $q=new WP_Query(array('post_type'=>'fortbildung','posts_per_page'=>1,'meta_query'=>array(array('key'=>'token','value'=>$token,'compare'=>'='))));
    if(!$q->have_posts()) return '<p>Kein passender Fortbildungseintrag gefunden.</p>';
    $q->the_post(); $pid=get_the_ID(); wp_reset_postdata();

    if(isset($_POST['confirmation'])){
        $c=sanitize_text_field($_POST['confirmation']);
        if($c==='yes'){ update_field('freigegeben',true,$pid); update_field('token','',$pid); return '<p>Fortbildungseintrag wurde erfolgreich freigegeben.</p>'; }
        elseif($c==='no'){ update_field('token','',$pid); return '<p>Fortbildungseintrag wurde nicht freigegeben.</p>'; }
        else return '<p>Ungültige Bestätigung.</p>';
    }

    $out='<form method="post">';
    $out.='<input type="hidden" name="token" value="'.esc_attr($token).'">';
    $out.='<p>Möchten Sie diesen Fortbildungseintrag freigeben?</p>';
    $out.='<p><button type="submit" name="confirmation" value="yes">Ja, freigeben</button> <button type="submit" name="confirmation" value="no">Nein</button></p>';
    $out.='</form>';
    return $out;
}
require_once plugin_dir_path(__FILE__) . 'fortbildungsupload.php';
require_once plugin_dir_path(__FILE__) . 'fortbildung-csv-import.php';
require_once plugin_dir_path(__FILE__) . 'doublettencheck.php';
require_once plugin_dir_path(__FILE__) . 'erweiterte-suche.php';
require_once plugin_dir_path(__FILE__) . 'FortbildungStatistikAdon.php';

