<?php
// File: includes/registration/registration-ajax.php

if (!defined('ABSPATH')) exit;

/**
 * AJAX: Scan & Registrieren (Kiosk/Scanner) + Settings speichern + Liste ausgeben
 */
if (!function_exists('dgptm_scan_register_fn')) {
    function dgptm_scan_register_fn(){
        $enabled = (int)get_option('dgptm_registration_enabled', 0);
        $pid     = (int)get_option('dgptm_registration_poll_id', 0);
        if(!$enabled || !$pid){
            wp_send_json_error('Registrierung ist nicht aktiv.');
        }

        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        if($code===''){
            wp_send_json_error('Kein Code.');
        }

        if (strtolower($code) === 'demo') {
            $info = array(
                'ok'            => true,
                'member_no'     => '123456',
                'first_name'    => 'Testi',
                'last_name'     => 'Tester',
                'member_status' => 'Tester',
                'email'         => '',
            );
        } else {
            if (!function_exists('dgptm_lookup_member_by_code')) {
                require_once __DIR__ . '/registration-helpers.php';
            }
            $info = dgptm_lookup_member_by_code($code);
            if(empty($info['ok'])) {
                wp_send_json_success(array('ok'=>false,'msg'=>isset($info['msg'])?$info['msg']:'Ungültig'));
            }
        }

        $fields = array(
            'first_name'    => isset($info['first_name']) ? $info['first_name'] : '',
            'last_name'     => isset($info['last_name']) ? $info['last_name'] : '',
            'member_status' => isset($info['member_status']) ? $info['member_status'] : '',
            'email'         => isset($info['email']) ? $info['email'] : '',
        );
        $member_no = isset($info['member_no']) ? $info['member_no'] : (string)$code;

        if (!function_exists('dgptm_get_or_create_member_token')) {
            require_once __DIR__ . '/registration-helpers.php';
        }
        $token = dgptm_get_or_create_member_token($pid, $member_no, $fields);

        if (!function_exists('dgptm_build_member_link')) {
            require_once __DIR__ . '/registration-helpers.php';
        }
        $link = dgptm_build_member_link($pid, $token);

        if(!empty($fields['email']) && is_email($fields['email'])){
            global $wpdb;
            $poll = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE id=%d", $pid));
            $pname = $poll ? $poll->name : 'Abstimmung';

            if (!function_exists('dgptm_mail_send_registration')) {
                require_once __DIR__ . '/registration-helpers.php';
            }
            dgptm_mail_send_registration($fields['email'], $pname, $link, $token, array(
                'first_name'=>$fields['first_name'],'last_name'=>$fields['last_name'],
                'member_no'=>$member_no,'member_status'=>$fields['member_status']
            ));
        }

        $display = trim(($fields['first_name'] ?? '').' '.($fields['last_name'] ?? ''));
        if($display==='') $display='Mitglied';

        wp_send_json_success(array(
            'ok'        => true,
            'qr_url'    => $link,
            'token'     => $token,
            'display'   => $display,
            'member_no' => $member_no,
        ));
    }
}
add_action('wp_ajax_dgptm_scan_register','dgptm_scan_register_fn');
add_action('wp_ajax_nopriv_dgptm_scan_register','dgptm_scan_register_fn');

add_action('wp_ajax_dgptm_save_registration_settings','dgptm_save_registration_settings_fn');
function dgptm_save_registration_settings_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    update_option('dgptm_registration_enabled', !empty($_POST['enabled'])?1:0);
    update_option('dgptm_registration_poll_id', isset($_POST['poll_id'])?intval($_POST['poll_id']):0);
    update_option('dgptm_member_lookup_endpoint', isset($_POST['endpoint'])?esc_url_raw($_POST['endpoint']):'');
    update_option('dgptm_registration_email_subject', isset($_POST['mail_subject'])?sanitize_text_field($_POST['mail_subject']):'');
    update_option('dgptm_registration_email_body', isset($_POST['mail_body'])?wp_kses_post($_POST['mail_body']):'');
    wp_send_json_success();
}

add_action('wp_ajax_dgptm_get_registered_members','dgptm_get_registered_members_fn');
function dgptm_get_registered_members_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    global $wpdb;
    $pid=(int)get_option('dgptm_registration_poll_id',0);
    if(!$pid){
        $poll=$wpdb->get_row("SELECT id FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE status='active' LIMIT 1");
        if($poll) $pid=(int)$poll->id;
    }
    if(!$pid) wp_send_json_error('Keine aktive/gewählte Umfrage.');
    $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE poll_id=%d ORDER BY joined_time ASC",$pid),ARRAY_A);
    if(!$rows){ wp_send_json_success(array('html'=>'<p>Keine Registrierungen.</p>')); }
    $out='<div style="overflow:auto;"><table border="1" cellpadding="5" style="border-collapse:collapse;min-width:880px;"><tr><th>Vollname</th><th>Vorname</th><th>Nachname</th><th>MitgliedsNr</th><th>Status</th><th>E-Mail</th><th>Zeit</th></tr>';
    foreach($rows as $r){
        $nm = trim($r['first_name'].' '.$r['last_name']); if($nm==='') $nm=$r['fullname'];
        $out.='<tr><td>'.esc_html($nm).'</td><td>'.esc_html($r['first_name']).'</td><td>'.esc_html($r['last_name']).'</td><td>'.esc_html($r['member_no']).'</td><td>'.esc_html($r['member_status']).'</td><td>'.esc_html($r['email']).'</td><td>'.$r['joined_time'].'</td></tr>';
    }
    $out.='</table></div>';
    wp_send_json_success(array('html'=>$out));
}
