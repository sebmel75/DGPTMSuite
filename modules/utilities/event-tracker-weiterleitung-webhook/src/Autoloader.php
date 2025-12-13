<?php
/**
 * Event Tracker Autoloader
 *
 * PSR-4 kompatibles Autoloading
 *
 * @package EventTracker
 * @since 2.0.0
 */

namespace EventTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader Class
 */
class Autoloader {

	/**
	 * Namespace Prefix
	 *
	 * @var string
	 */
	private static $prefix = 'EventTracker\\';

	/**
	 * Base Directory
	 *
	 * @var string
	 */
	private static $base_dir;

	/**
	 * Register Autoloader
	 *
	 * @param string $base_dir Plugin base directory.
	 */
	public static function register( $base_dir ) {
		self::$base_dir = trailingslashit( $base_dir ) . 'src/';
		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Autoload Classes
	 *
	 * @param string $class Fully qualified class name.
	 */
	private static function autoload( $class ) {
		// Prüfe ob Klasse zu unserem Namespace gehört
		if ( strpos( $class, self::$prefix ) !== 0 ) {
			return;
		}

		// Entferne Namespace-Prefix
		$relative_class = substr( $class, strlen( self::$prefix ) );

		// Konvertiere Namespace zu Pfad
		$file = self::$base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		// Lade Datei wenn existent
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
