<?php
/*
Plugin Name: DGPTM - ACF Toggle Shortcodes
Description: Erstellt Shortcodes, um Inhalte basierend auf einem ACF-Ja/Nein-Feld im Benutzerprofil oder basierend auf Benutzerrollen ein- oder auszublenden, sowie einen Shortcode, der den Status des Feldes (true/false) zurückgibt.
Version: 1.4
Author: Dein Name
*/

if (!defined('ABSPATH')) {
    exit; // Sicherheitsmaßnahme
}

// Funktion für den Ein-/Ausblende-Shortcode
function acf_toggle_content_shortcode($atts, $content = null) {
    $atts = shortcode_atts([
        'field' => '', // Name des ACF-Feldes
        'user_id' => '' // Benutzer-ID (optional, standardmäßig aktueller Benutzer)
    ], $atts);

    if (empty($atts['field'])) {
        return ''; // Kein Feld angegeben, nichts zurückgeben
    }

    // Benutzer-ID festlegen (aktueller Benutzer, falls nicht angegeben)
    $user_id = !empty($atts['user_id']) ? intval($atts['user_id']) : get_current_user_id();

    if (!$user_id) {
        return ''; // Kein Benutzer gefunden, nichts zurückgeben
    }

    // Wert des ACF-Feldes abrufen
    $field_value = get_field($atts['field'], 'user_' . $user_id);

    if ($field_value) {
        return do_shortcode($content); // Inhalt anzeigen, wenn Feld "true" ist
    }

    return ''; // Inhalt nicht anzeigen
}
add_shortcode('acf_toggle_show', 'acf_toggle_content_shortcode');

// Funktion für den Ausblende-Shortcode
function acf_toggle_hide_shortcode($atts, $content = null) {
    $atts = shortcode_atts([
        'field' => '', // Name des ACF-Feldes
        'user_id' => '' // Benutzer-ID (optional, standardmäßig aktueller Benutzer)
    ], $atts);

    if (empty($atts['field'])) {
        return ''; // Kein Feld angegeben, nichts zurückgeben
    }

    // Benutzer-ID festlegen (aktueller Benutzer, falls nicht angegeben)
    $user_id = !empty($atts['user_id']) ? intval($atts['user_id']) : get_current_user_id();

    if (!$user_id) {
        return ''; // Kein Benutzer gefunden, nichts zurückgeben
    }

    // Wert des ACF-Feldes abrufen
    $field_value = get_field($atts['field'], 'user_' . $user_id);

    if (!$field_value) {
        return do_shortcode($content); // Inhalt anzeigen, wenn Feld "false" ist
    }

    return ''; // Inhalt nicht anzeigen
}
add_shortcode('acf_toggle_hide', 'acf_toggle_hide_shortcode');

// Funktion für den True/False-Shortcode
function acf_toggle_status_shortcode($atts) {
    $atts = shortcode_atts([
        'field' => '', // Name des ACF-Feldes
        'user_id' => '' // Benutzer-ID (optional, standardmäßig aktueller Benutzer)
    ], $atts);

    if (empty($atts['field'])) {
        return ''; // Kein Feld angegeben, nichts zurückgeben
    }

    // Benutzer-ID festlegen (aktueller Benutzer, falls nicht angegeben)
    $user_id = !empty($atts['user_id']) ? intval($atts['user_id']) : get_current_user_id();

    if (!$user_id) {
        return ''; // Kein Benutzer gefunden, nichts zurückgeben
    }

    // Wert des ACF-Feldes abrufen
    $field_value = get_field($atts['field'], 'user_' . $user_id);

    return $field_value ? 'true' : 'false'; // True oder False zurückgeben
}
add_shortcode('acf_toggle_status', 'acf_toggle_status_shortcode');

// Funktion für den Rollenbasierten Shortcode
function acf_toggle_role_shortcode($atts, $content = null) {
    $atts = shortcode_atts([
        'role' => '', // Rollen, die überprüft werden sollen (Komma-separiert)
    ], $atts);

    if (empty($atts['role'])) {
        return ''; // Keine Rollen angegeben, nichts zurückgeben
    }

    // Aktuellen Benutzer abrufen
    $current_user = wp_get_current_user();

    // Rollen in ein Array umwandeln
    $roles = array_map('trim', explode(',', $atts['role']));

    // Prüfen, ob der Benutzer eine der angegebenen Rollen hat
    if (array_intersect($roles, $current_user->roles)) {
        return do_shortcode($content); // Inhalt anzeigen, wenn Benutzer mindestens eine Rolle hat
    }

    return ''; // Inhalt nicht anzeigen
}
add_shortcode('acf_toggle_role', 'acf_toggle_role_shortcode');

// Funktion für Rollen-Status-Shortcode
function acf_toggle_role_status_shortcode($atts) {
    $atts = shortcode_atts([
        'role' => '', // Rollen, die überprüft werden sollen (Komma-separiert)
    ], $atts);

    if (empty($atts['role'])) {
        return ''; // Keine Rollen angegeben, nichts zurückgeben
    }

    // Aktuellen Benutzer abrufen
    $current_user = wp_get_current_user();

    // Rollen in ein Array umwandeln
    $roles = array_map('trim', explode(',', $atts['role']));

    // Prüfen, ob der Benutzer eine der angegebenen Rollen hat
    return array_intersect($roles, $current_user->roles) ? 'true' : 'false'; // True oder False zurückgeben
}
add_shortcode('acf_toggle_role_status', 'acf_toggle_role_status_shortcode');
