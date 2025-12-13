<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://ays-pro.com/
 * @since             1.0.0
 * @package           Quiz_Maker_Frontend_Requests
 *
 * @wordpress-plugin
 * Plugin Name:       Quiz Maker Add-on - Create quiz from frontend
 * Plugin URI:        https://ays-pro.com/frontend-request-addon
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.6
 * Author:            Quiz Maker team
 * Author URI:        https://ays-pro.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       quiz-maker-frontend-requests
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if( in_array('quiz-maker/quiz-maker.php', apply_filters('active_plugins', get_option('active_plugins'))) || is_multisite() ){

	if ( ! defined( 'PARENT_QUIZ_MAKER_VERSION' ) ) {
		$quiz_db_version = get_site_option('ays_quiz_db_version', null);

		if( is_null( $quiz_db_version ) ){
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_dir ='/quiz-maker';
            $quiz_plugin_data = get_plugins($plugin_dir);
            if ( !is_null( $quiz_plugin_data ) && !empty($quiz_plugin_data) && isset($quiz_plugin_data['quiz-maker.php']) ) {
                $quiz_db_version = (isset( $quiz_plugin_data['quiz-maker.php']['Version'] ) && $quiz_plugin_data['quiz-maker.php']['Version'] != "") ? sanitize_text_field($quiz_plugin_data['quiz-maker.php']['Version']) : null;
            }
        }

		if($quiz_db_version !== null){
			$quiz_version_parts = explode('.', $quiz_db_version);
			if(!empty($quiz_version_parts)){
				$quiz_version = intval($quiz_version_parts[0]);
			}
			if($quiz_version < 7){
				define( 'PARENT_QUIZ_MAKER_VERSION', 'free' );
			}else if($quiz_version >= 7 && $quiz_version < 20){
				define( 'PARENT_QUIZ_MAKER_VERSION', 'pro' );
			}else if($quiz_version >= 20){
				define( 'PARENT_QUIZ_MAKER_VERSION', 'dev' );
			}
		}
	}
	
	if ( is_multisite() ) {
		// First, I define a constant to see if site is network activated
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		    // Makes sure the plugin is defined before trying to use it
		    require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		if (is_plugin_active_for_network('quiz-maker/quiz-maker.php')) {  // path to plugin folder and main file
		    define("QUIZ_MAKER_FRONTEND_REQUESTS_NETWORK_ACTIVATED", true);
		} elseif ( in_array('quiz-maker/quiz-maker.php', apply_filters('active_plugins', get_option('active_plugins'))) ) {
		    define("QUIZ_MAKER_FRONTEND_REQUESTS_NETWORK_ACTIVATED", true);
		}
		else {
		    define("QUIZ_MAKER_FRONTEND_REQUESTS_NETWORK_ACTIVATED", false);
		}
	}

	$ays_quiz_maker_network_flag = true;

	if ( defined( "QUIZ_MAKER_FRONTEND_REQUESTS_NETWORK_ACTIVATED" ) ) {
		$ays_quiz_maker_network_flag = QUIZ_MAKER_FRONTEND_REQUESTS_NETWORK_ACTIVATED;
	}

	if ( PARENT_QUIZ_MAKER_VERSION != 'free' && $ays_quiz_maker_network_flag ) {
		/**
		 * Currently plugin version.
		 * Start at version 1.0.0 and use SemVer - https://semver.org
		 * Rename this for your plugin and update it as you release new versions.
		 */
		define( 'QUIZ_MAKER_FRONTEND_REQUESTS_VERSION', '1.0.6' );

		if( ! defined('PARENT_QUIZ_MAKER_NAME')){
			define( 'PARENT_QUIZ_MAKER_NAME', 'quiz-maker' );
		}

		if( ! defined( 'QUIZ_MAKER_FRONTEND_REQUESTS_DIR' ) )
			define( 'QUIZ_MAKER_FRONTEND_REQUESTS_DIR', plugin_dir_path( __FILE__ ) );

		if( ! defined( 'QUIZ_MAKER_FRONTEND_REQUESTS_BASE_URL' ) ) 
			define( 'QUIZ_MAKER_FRONTEND_REQUESTS_BASE_URL', plugin_dir_url(__FILE__ ) );
			
		if( ! defined( 'QUIZ_MAKER_FRONTEND_REQUESTS_ADMIN_URL' ) )
			define( 'QUIZ_MAKER_FRONTEND_REQUESTS_ADMIN_URL', plugin_dir_url( __FILE__ ) . 'admin' );
			
		if( ! defined( 'QUIZ_MAKER_FRONTEND_REQUESTS_ADMIN_PATH' ) )
			define( 'QUIZ_MAKER_FRONTEND_REQUESTS_ADMIN_PATH', plugin_dir_path( __FILE__ ) . 'admin' );
		
		if( ! defined( 'QUIZ_MAKER_FRONTEND_REQUESTS_PUBLIC_URL' ) )
			define( 'QUIZ_MAKER_FRONTEND_REQUESTS_PUBLIC_URL', plugin_dir_url( __FILE__ ) . 'public' );
			
		if( ! defined( 'QUIZ_MAKER_FRONTEND_REQUESTS_PUBLIC_PATH' ) )
			define( 'QUIZ_MAKER_FRONTEND_REQUESTS_PUBLIC_PATH', plugin_dir_path( __FILE__ ) . 'public' );

		/**
		 * The code that runs during plugin activation.
		 * This action is documented in includes/class-quiz-maker-frontend-requests-activator.php
		 */
		function activate_quiz_maker_frontend_requests() {
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-quiz-maker-frontend-requests-activator.php';
			Quiz_Maker_Frontend_Requests_Activator::ays_quiz_frontend_requests_update_db_check();
		}

		/**
		 * The code that runs during plugin deactivation.
		 * This action is documented in includes/class-quiz-maker-frontend-requests-deactivator.php
		 */
		function deactivate_quiz_maker_frontend_requests() {
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-quiz-maker-frontend-requests-deactivator.php';
			Quiz_Maker_Frontend_Requests_Deactivator::deactivate();
		}

		register_activation_hook( __FILE__, 'activate_quiz_maker_frontend_requests' );
		register_deactivation_hook( __FILE__, 'deactivate_quiz_maker_frontend_requests' );

		add_action('plugins_loaded', 'activate_quiz_maker_frontend_requests');

		/**
		 * The core plugin class that is used to define internationalization,
		 * admin-specific hooks, and public-facing site hooks.
		 */
		require plugin_dir_path( __FILE__ ) . 'includes/class-quiz-maker-frontend-requests.php';

		/**
		 * Begins execution of the plugin.
		 *
		 * Since everything within the plugin is registered via hooks,
		 * then kicking off the plugin from this point in the file does
		 * not affect the page life cycle.
		 *
		 * @since    1.0.0
		 */
		function run_quiz_maker_frontend_requests() {

			$plugin = new Quiz_Maker_Frontend_Requests();
			$plugin->run();

		}
		run_quiz_maker_frontend_requests();
	}
}
