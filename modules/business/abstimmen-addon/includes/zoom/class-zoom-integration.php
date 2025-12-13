<?php
/**
 * Zoom Integration Class Wrapper
 *
 * Loads the DGPTM_Online_Abstimmen class from onlineabstimmung.php
 * This file serves as a bridge between the new modular structure
 * and the existing Zoom integration code.
 *
 * @package DGPTM_Abstimmen
 * @version 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the complete Zoom integration class
require_once DGPTM_ABSTIMMEN_PATH . 'onlineabstimmung.php';

/**
 * Initialize Zoom Integration
 */
function dgptm_abstimmen_init_zoom_integration() {
	// Initialize the singleton instance
	if ( class_exists( 'DGPTM_Online_Abstimmen' ) ) {
		DGPTM_Online_Abstimmen::instance();
	}
}

// Initialize on plugins_loaded with higher priority
add_action( 'plugins_loaded', 'dgptm_abstimmen_init_zoom_integration', 5 );
