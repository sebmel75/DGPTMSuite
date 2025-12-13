<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Prüft, ob der Benutzer alle Herzzentren bearbeiten darf.
 * Quellen: Capability 'hzb_edit_all_herzzentren', Admin (manage_options) oder ACF-User-Meta 'alle_herzzentren_bearbeiten'.
 */
function hzb_user_can_edit_all( $user_id = 0 ) {
	$user_id = $user_id ? intval($user_id) : get_current_user_id();
	if ( ! $user_id ) return false;
	if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'hzb_edit_all_herzzentren' ) ) {
		return true;
	}
	$meta = get_user_meta( $user_id, 'alle_herzzentren_bearbeiten', true );
	if ( is_array($meta) ) {
		$meta = reset($meta);
	}
	$truthy = array('1','true','yes','on',1,true);
	return in_array( $meta, $truthy, true );
}

/**
 * Liefert eine Liste der zugewiesenen Herzzentrum-IDs für den Nutzer.
 * Erwartet User-Meta 'zugewiesenes_herzzentrum' (ID, Liste, CSV oder Array).
 */
function hzb_get_assigned_herzzentren_ids( $user_id = 0 ) {
	$user_id = $user_id ? intval($user_id) : get_current_user_id();
	if ( ! $user_id ) return array();

	$raw = get_user_meta( $user_id, 'zugewiesenes_herzzentrum', true );
	$ids = array();

	if ( empty($raw) ) return array();

	if ( is_array($raw) ) {
		$ids = $raw;
	} else {
		$ids = preg_split('/[,;]+/', (string) $raw);
	}

	$out = array();
	foreach ( $ids as $v ) {
		$v = intval( trim((string)$v) );
		if ( $v > 0 ) $out[] = $v;
	}
	$out = array_values( array_unique($out) );
	return $out;
}

/**
 * Darf der User ein konkretes Herzzentrum bearbeiten?
 */
function hzb_user_can_edit_herzzentrum( $user_id, $post_id ) {
	$user_id = $user_id ? intval($user_id) : get_current_user_id();
	$post_id = intval($post_id);
	if ( ! $user_id || ! $post_id ) return false;

	if ( hzb_user_can_edit_all( $user_id ) ) return true;
	$assigned = hzb_get_assigned_herzzentren_ids( $user_id );
	return in_array( $post_id, $assigned, true );
}

/**
 * Liefert alle bearbeitbaren Herzzentrums-IDs des Nutzers.
 */
function hzb_get_user_editable_herzzentren( $user_id = 0 ) {
	$user_id = $user_id ? intval($user_id) : get_current_user_id();
	if ( ! $user_id ) return array();

	if ( hzb_user_can_edit_all( $user_id ) ) {
		$q = new WP_Query( array(
			'post_type'      => 'herzzentrum',
			'posts_per_page' => -1,
			'post_status'    => array('publish','draft','pending','private'),
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		return $q->posts ? array_map('intval', $q->posts) : array();
	}

	return hzb_get_assigned_herzzentren_ids( $user_id );
}
