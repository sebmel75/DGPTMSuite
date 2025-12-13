<?php
/*
Plugin Name: DGPTM Abstimmungstool (DEPRECATED - Use abstimmen-addon.php)
Description: ⚠️ DEPRECATED: Diese Datei wird nur noch für Rückwärtskompatibilität geladen. Die gesamte Funktionalität wurde in abstimmen-addon.php v4.0.0 integriert.
Version: 3.7.0 (Legacy)
Author: Sebastian Melzer (überarbeitet)

DEPRECATED NOTICE:
==================
This file is DEPRECATED and should not be used directly.
It is only loaded by abstimmen-addon.php for backwards compatibility.

All functionality has been consolidated into:
- abstimmen-addon.php (Main entry point v4.0.0)

Please use abstimmen-addon.php instead of loading this file directly.
*/

if (!defined('ABSPATH')) exit;

// WARNING: Do not activate this plugin directly!
// It should only be loaded via abstimmen-addon.php
if (!defined('DGPTM_ABSTIMMEN_VERSION')) {
	wp_die(
		'<h1>DGPTM Abstimmungstool - Deprecated</h1>' .
		'<p><strong>Diese Datei ist veraltet und darf nicht direkt aktiviert werden.</strong></p>' .
		'<p>Bitte verwenden Sie stattdessen <code>abstimmen-addon.php</code> (Version 4.0.0).</p>' .
		'<p><a href="' . admin_url('plugins.php') . '">Zurück zu Plugins</a></p>'
	);
}

if (!defined('DGPTMVOTE_VERSION')) define('DGPTMVOTE_VERSION', '3.7.0');
if (!defined('DGPTMVOTE_COOKIE'))  define('DGPTMVOTE_COOKIE',  'DGPTMVOTE_voteid');

// Includes
require_once __DIR__ . '/includes/common/helpers.php';
require_once __DIR__ . '/includes/common/install.php';
require_once __DIR__ . '/includes/common/enqueue.php';

// Admin + AJAX
require_once __DIR__ . '/includes/admin/manage-poll.php';
require_once __DIR__ . '/includes/admin/admin-ajax.php';

// Public + AJAX
require_once __DIR__ . '/includes/public/member-vote.php';
require_once __DIR__ . '/includes/ajax/vote.php';

// Beamer
require_once __DIR__ . '/includes/beamer/payload.php';
require_once __DIR__ . '/includes/beamer/view.php';

// Registration
require_once __DIR__ . '/includes/registration/monitor.php';
require_once __DIR__ . '/includes/registration/registration-helpers.php';
require_once __DIR__ . '/includes/registration/registration-ajax.php';

// Export
require_once __DIR__ . '/includes/export/export.php';

// Activation / Upgrade
register_activation_hook(__FILE__, 'dgptm_activate_plugin');
add_action('admin_init', 'dgptm_maybe_upgrade_db');

// Query vars (member view)
add_filter('query_vars','dgptm_add_query_var');
add_action('template_redirect','dgptm_template_redirect');

// Usermeta UI
add_action('show_user_profile','dgptm_user_field_abstimmungsmanager');
add_action('edit_user_profile','dgptm_user_field_abstimmungsmanager');
add_action('personal_options_update','dgptm_save_user_field_abstimmungsmanager');
add_action('edit_user_profile_update','dgptm_save_user_field_abstimmungsmanager');

// Shortcodes registration (failsafe)
add_action('init', function(){
    if (!shortcode_exists('manage_poll')) add_shortcode('manage_poll','dgptm_manage_poll');
    if (!shortcode_exists('beamer_view')) add_shortcode('beamer_view','dgptm_beamer_view');
    if (!shortcode_exists('member_vote')) add_shortcode('member_vote','dgptm_member_vote');
    if (!shortcode_exists('abstimmungsmanager_toggle')) add_shortcode('abstimmungsmanager_toggle','dgptm_shortcode_manager_toggle');
    if (!shortcode_exists('dgptm_registration_monitor')) add_shortcode('dgptm_registration_monitor','dgptm_registration_monitor_fn');
}, 5);
