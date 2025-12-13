<?php
/**
 * Presence Scanner Class Wrapper
 *
 * Loads the DGPTM_Presence_Manual_Addon class from abstimmenadon.php
 * This file serves as a bridge between the new modular structure
 * and the existing presence scanner code.
 *
 * @package DGPTM_Abstimmen
 * @version 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the complete Presence Scanner addon class
require_once DGPTM_ABSTIMMEN_PATH . 'abstimmenadon.php';

/**
 * Initialize Presence Scanner
 */
function dgptm_abstimmen_init_presence_scanner() {
	// Initialize the addon
	if ( class_exists( 'DGPTM_Presence_Manual_Addon' ) ) {
		DGPTM_Presence_Manual_Addon::init();
	}
}

// Initialize on init with high priority
add_action( 'init', 'dgptm_abstimmen_init_presence_scanner', 1 );
