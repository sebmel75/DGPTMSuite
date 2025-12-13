<?php
global $ays_quiz_frontend_requests_db_version;
$ays_quiz_frontend_requests_db_version = '1.0.0';
/**
 * Fired during plugin activation
 *
 * @link       https://ays-pro.com/
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Frontend_Requests
 * @subpackage Quiz_Maker_Frontend_Requests/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Quiz_Maker_Frontend_Requests
 * @subpackage Quiz_Maker_Frontend_Requests/includes
 * @author     Quiz Maker team <info@ays-pro.com>
 */
class Quiz_Maker_Frontend_Requests_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;
        global $ays_quiz_frontend_requests_db_version;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		$installed_ver = get_option( "ays_quiz_frontend_requests_db_version" );

		$quiz_requests_table = $wpdb->prefix . 'aysquiz_requests';
		$quiz_settings_table = $wpdb->prefix . 'aysquiz_settings';
        $charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `".$quiz_requests_table."` (
			`id` INT(16) UNSIGNED NOT NULL AUTO_INCREMENT,
			`quiz_id` INT(16) UNSIGNED DEFAULT NULL,
			`category_id` INT(16) UNSIGNED DEFAULT NULL,
			`user_id` INT(16) UNSIGNED DEFAULT NULL,
			`user_ip` TEXT NULL DEFAULT NULL,
			`quiz_title` TEXT NULL DEFAULT NULL,
			`quiz_data` LONGTEXT NULL DEFAULT NULL,
			`request_date` DATETIME DEFAULT NULL,
			`status` TEXT NULL DEFAULT NULL,
			`read` TEXT NULL DEFAULT NULL,
			`approved` TEXT NULL DEFAULT NULL,
			`options` TEXT NULL DEFAULT NULL,
			PRIMARY KEY (`id`)
		)$charset_collate;";

		$sql_schema = "SELECT * FROM INFORMATION_SCHEMA.TABLES
					   WHERE table_schema = '".DB_NAME."' AND table_name = '".$quiz_requests_table."' ";
		$results = $wpdb->get_results($sql_schema);
		
		if(empty($results)){
			$wpdb->query( $sql );
		}else{
			dbDelta( $sql );
		}

		$metas = array(
            "front_requests",
        );
		
		foreach($metas as $meta_key){
            $meta_val = "";
            if($meta_key == "user_roles"){
                $meta_val = json_encode(array('administrator'));
            }
            $sql = "SELECT COUNT(*) FROM `".$quiz_settings_table."` WHERE `meta_key` = '".$meta_key."'";
            $result = $wpdb->get_var($sql);
            if(intval($result) == 0){
                $result = $wpdb->insert(
                    $quiz_settings_table,
                    array(
                        'meta_key'    => $meta_key,
                        'meta_value'  => $meta_val,
                        'note'        => "",
                        'options'     => ""
                    ),
                    array( '%s', '%s', '%s', '%s' )
                );
            }
        }
	}

	public static function ays_quiz_frontend_requests_update_db_check() {
        global $ays_quiz_frontend_requests_db_version;

		if (is_multisite()) {
			global $wpdb;
			$quiz_requests_table = $wpdb->prefix . 'aysquiz_requests';
			$network_id = get_current_network_id();

			if ($wpdb->get_var("SHOW TABLES LIKE '$quiz_requests_table'") != $quiz_requests_table ) {
				delete_network_option($network_id, 'ays_quiz_frontend_requests_db_version');
			}

			if (get_network_option($network_id, 'ays_quiz_frontend_requests_db_version') != $ays_quiz_frontend_requests_db_version) {
				self::activate();
			}
		} else {
			if (get_site_option('ays_quiz_frontend_requests_db_version') != $ays_quiz_frontend_requests_db_version) {
				self::activate();
			}
		}
    }

}
