<?php
/**
 * Plugin Name: DGPTM - Abstimmungstool Freigabe
 * Description: Interaktives Freigabe-Dokument fuer das Abstimmungstool mit Kommentarfunktion und Vorstandsfreigabe
 * Version: 1.0.0
 * Author: Sebastian Melzer
 * Text Domain: dgptm-abstimmen-freigabe
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_Abstimmen_Freigabe')) {
    require_once __DIR__ . '/includes/class-abstimmen-freigabe.php';
}

add_action('init', function () {
    new DGPTM_Abstimmen_Freigabe(plugin_dir_path(__FILE__), plugin_dir_url(__FILE__));
});
