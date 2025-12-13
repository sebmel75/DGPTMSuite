<?php
/**
 * Asset Enqueue Handler
 * Updated version with external CSS/JS files
 *
 * @package DGPTM_Abstimmen
 * @version 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue Admin Assets
 */
function dgptm_abstimmen_enqueue_admin_assets( $hook ) {
	// Only on our admin pages
	$allowed_hooks = array(
		'toplevel_page_dgptm-voting',
		'voting_page_dgptm-voting-settings',
		'edit.php',
		'post.php',
		'post-new.php',
	);

	if ( ! in_array( $hook, $allowed_hooks, true ) && strpos( $hook, 'dgptm' ) === false ) {
		return;
	}

	$version = DGPTM_ABSTIMMEN_VERSION;

	// Admin CSS
	wp_enqueue_style(
		'dgptm-abstimmen-admin',
		DGPTM_ABSTIMMEN_URL . 'assets/css/admin.css',
		array(),
		$version
	);

	// Admin JavaScript
	wp_enqueue_script(
		'dgptm-abstimmen-admin',
		DGPTM_ABSTIMMEN_URL . 'assets/js/admin.js',
		array( 'jquery' ),
		$version,
		true
	);

	// Localize script
	wp_localize_script(
		'dgptm-abstimmen-admin',
		'dgptm_ajax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'dgptm_admin_nonce' ),
		)
	);

	// QR Code library
	wp_enqueue_script(
		'qrcode-lib',
		'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js',
		array(),
		'1.5.3',
		true
	);
}
add_action( 'admin_enqueue_scripts', 'dgptm_abstimmen_enqueue_admin_assets' );

/**
 * Enqueue Frontend Assets
 */
function dgptm_abstimmen_enqueue_frontend_assets() {
	$version = DGPTM_ABSTIMMEN_VERSION;

	// Frontend CSS
	wp_enqueue_style(
		'dgptm-abstimmen-frontend',
		DGPTM_ABSTIMMEN_URL . 'assets/css/frontend.css',
		array(),
		$version
	);

	// Frontend JavaScript
	wp_enqueue_script(
		'dgptm-abstimmen-frontend',
		DGPTM_ABSTIMMEN_URL . 'assets/js/frontend.js',
		array( 'jquery' ),
		$version,
		true
	);

	// Localize script for AJAX
	$rest_nonce = '';
	if ( is_user_logged_in() ) {
		$rest_nonce = wp_create_nonce( 'wp_rest' );
	}

	wp_localize_script(
		'dgptm-abstimmen-frontend',
		'dgptm_vote',
		array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'rest_url'      => rest_url(),
			'rest_nonce'    => $rest_nonce,
			'rest_presence' => rest_url( 'dgptm-zoom/v1/presence' ),
			'nonce'         => wp_create_nonce( 'dgptm_vote_nonce' ),
		)
	);

	// Chart.js for beamer view
	if ( is_page() && has_shortcode( get_post()->post_content, 'beamer_view' ) ) {
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'dgptm_abstimmen_enqueue_frontend_assets' );

/**
 * Conditional Asset Loading based on Shortcode
 */
function dgptm_abstimmen_conditional_assets() {
	global $post;

	if ( ! is_a( $post, 'WP_Post' ) ) {
		return;
	}

	$content = $post->post_content;

	// Load QR Code library if manage_poll shortcode present
	if ( has_shortcode( $content, 'manage_poll' ) || has_shortcode( $content, 'dgptm_registration_monitor' ) ) {
		wp_enqueue_script(
			'qrcode-lib',
			'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js',
			array(),
			'1.5.3',
			true
		);
	}

	// Load Chart.js if beamer_view shortcode present
	if ( has_shortcode( $content, 'beamer_view' ) ) {
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'dgptm_abstimmen_conditional_assets', 20 );

/**
 * Inline Styles for Dynamic Colors (Settings-based)
 */
function dgptm_abstimmen_inline_styles() {
	$options = get_option( 'dgptm_vote_settings', array() );

	// Allow customization via settings
	$primary_color   = isset( $options['primary_color'] ) ? $options['primary_color'] : '#2563eb';
	$success_color   = isset( $options['success_color'] ) ? $options['success_color'] : '#10b981';
	$danger_color    = isset( $options['danger_color'] ) ? $options['danger_color'] : '#ef4444';
	$beamer_gradient = isset( $options['beamer_gradient'] ) ? $options['beamer_gradient'] : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';

	$custom_css = "
		:root {
			--dgptm-primary: {$primary_color};
			--dgptm-success: {$success_color};
			--dgptm-danger: {$danger_color};
		}
		.dgptm-beamer-view {
			background: {$beamer_gradient};
		}
		.btn.primary,
		.dgptm-toggle-button.on {
			background-color: var(--dgptm-success);
		}
		.btn.danger,
		.dgptm-toggle-button.off {
			background-color: var(--dgptm-danger);
		}
	";

	wp_add_inline_style( 'dgptm-abstimmen-frontend', $custom_css );
}
add_action( 'wp_enqueue_scripts', 'dgptm_abstimmen_inline_styles', 30 );

/**
 * Preload Critical Assets
 */
function dgptm_abstimmen_preload_assets() {
	// Preload critical CSS
	echo '<link rel="preload" href="' . esc_url( DGPTM_ABSTIMMEN_URL . 'assets/css/frontend.css' ) . '" as="style">' . "\n";

	// Preload critical JS
	echo '<link rel="preload" href="' . esc_url( DGPTM_ABSTIMMEN_URL . 'assets/js/frontend.js' ) . '" as="script">' . "\n";
}
add_action( 'wp_head', 'dgptm_abstimmen_preload_assets', 1 );

/**
 * Remove Inline Scripts (Deprecated - kept for backwards compatibility)
 */
function dgptm_abstimmen_remove_inline_scripts() {
	// This function is now empty as all scripts are externalized
	// Kept for potential plugin hooks that might reference it
}
