<?php
// File: includes/registration/registration-helpers.php

if (!defined('ABSPATH')) exit;

/**
 * Token anlegen/holen (pro poll_id + member_no, unique)
 */
if (!function_exists('dgptm_get_or_create_member_token')) {
    function dgptm_get_or_create_member_token($poll_id, $member_no, $fields=array()){
        global $wpdb;
        $tbl=$wpdb->prefix.'dgptm_abstimmung_participants';

        $row=$wpdb->get_row($wpdb->prepare("SELECT token FROM $tbl WHERE poll_id=%d AND member_no=%s LIMIT 1",$poll_id,$member_no));
        if($row && !empty($row->token)) return $row->token;

        $token = wp_generate_password(24, false, false);
        $fullname = trim( ( $fields['first_name'] ?? '' ) . ' ' . ( $fields['last_name'] ?? '' ) );
        $data=array(
            'poll_id'       => (int)$poll_id,
            'user_id'       => 0,
            'fullname'      => $fullname ? $fullname : 'Mitglied '.$member_no,
            'cookie_id'     => '',
            'joined_time'   => current_time('mysql'),
            'first_name'    => $fields['first_name'] ?? '',
            'last_name'     => $fields['last_name'] ?? '',
            'member_no'     => $member_no,
            'member_status' => $fields['member_status'] ?? '',
            'email'         => $fields['email'] ?? '',
            'token'         => $token,
            'source'        => 'kiosk'
        );

        $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE poll_id=%d AND member_no=%s",$poll_id,$member_no));
        if($exists){
            $wpdb->update($tbl,$data,array('poll_id'=>$poll_id,'member_no'=>$member_no));
        } else {
            $wpdb->insert($tbl,$data);
        }

        return $token;
    }
}

/**
 * Teilnahme-Link bauen
 */
if (!function_exists('dgptm_build_member_link')) {
    function dgptm_build_member_link($poll_id, $token){
        $url = add_query_arg(array('dgptm_member'=>1,'poll_id'=>$poll_id,'token'=>$token), home_url('/'));
        return $url;
    }
}

/**
 * Registrierungs-Mail versenden (Platzhalter: {first_name} {last_name} {member_no} {status} {poll_name} {link} {token})
 */
if (!function_exists('dgptm_mail_send_registration')) {
    function dgptm_mail_send_registration($email, $poll_name, $link, $token, $vars=array()){
        $sub = get_option('dgptm_registration_email_subject','Ihr Abstimmungslink');
        $body= get_option('dgptm_registration_email_body',"Hallo {first_name} {last_name},\n\nhier ist Ihr persönlicher Abstimmungslink für {poll_name}:\n{link}\n\nMitgliedsnummer: {member_no}\nStatus: {status}\n\nViele Grüße");
        $repl = array(
            '{first_name}' => $vars['first_name']    ?? '',
            '{last_name}'  => $vars['last_name']     ?? '',
            '{member_no}'  => $vars['member_no']     ?? '',
            '{status}'     => $vars['member_status'] ?? '',
            '{poll_name}'  => $poll_name,
            '{link}'       => $link,
            '{token}'      => $token
        );
        $subj = strtr($sub,$repl);
        $text = strtr($body,$repl);
        $headers = array('Content-Type: text/plain; charset='.get_option('blog_charset'));
        @wp_mail($email, $subj, $text, $headers);
    }
}

/**
 * Mitgliedsdaten über externen Endpoint abrufen
 * Erwartete Antwort: JSON { ok, member_no, first_name, last_name, member_status, email, msg? }
 */
if (!function_exists('dgptm_lookup_member_by_code')) {
    function dgptm_lookup_member_by_code($code){
        $endpoint = get_option('dgptm_member_lookup_endpoint','');
        if(!$endpoint) return array('ok'=>false,'msg'=>'Endpoint fehlt.');
        $url = add_query_arg(array('code'=>$code), $endpoint);
        $args = array('timeout'=>8,'sslverify'=>false);
        $res = wp_remote_get($url, $args);
        if(is_wp_error($res)) return array('ok'=>false,'msg'=>'Lookup-Fehler');
        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);
        if(!is_array($data)) return array('ok'=>false,'msg'=>'Ungültige Antwort');
        if(empty($data['ok'])) return array('ok'=>false,'msg'=>isset($data['msg'])?$data['msg']:'Nicht gefunden');

        return array(
            'ok'            => true,
            'member_no'     => isset($data['member_no'])     ? $data['member_no']     : '',
            'first_name'    => isset($data['first_name'])    ? $data['first_name']    : '',
            'last_name'     => isset($data['last_name'])     ? $data['last_name']     : '',
            'member_status' => isset($data['member_status']) ? $data['member_status'] : '',
            'email'         => isset($data['email'])         ? $data['email']         : ''
        );
    }
}
