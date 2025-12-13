<?php
/**
 * Event Tracker - Cleanup Old Module ID
 *
 * Dieses Script entfernt die alte Modul-ID "event-tracker-weiterleitung-webhook"
 * aus den DGPTM Suite Settings und aktiviert das neue Modul "event-tracker".
 *
 * ANLEITUNG:
 * 1. Diese Datei in den Browser aufrufen: https://ihre-domain.de/wp-content/plugins/dgptm-plugin-suite/modules/business/event-tracker/cleanup-old-module.php
 * 2. Script wird automatisch ausgeführt
 * 3. Seite neu laden
 * 4. WICHTIG: Diese Datei danach löschen!
 */

// WordPress laden
require_once dirname( dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) ) . '/wp-load.php';

// Nur für Admins
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Keine Berechtigung.' );
}

echo '<h1>Event Tracker - Cleanup Script</h1>';
echo '<pre>';

// DGPTM Suite Settings laden
$settings = get_option( 'dgptm_suite_settings', [] );

echo "=== DGPTM Suite Settings ===\n";
echo "Aktive Module vorher:\n";
print_r( isset( $settings['active_modules'] ) ? $settings['active_modules'] : 'Keine' );
echo "\n\n";

// Alte ID entfernen
$old_id = 'event-tracker-weiterleitung-webhook';
$new_id = 'event-tracker';

$changes_made = false;

if ( isset( $settings['active_modules'][ $old_id ] ) ) {
	echo "✓ Alte Modul-ID '$old_id' gefunden - wird entfernt\n";
	unset( $settings['active_modules'][ $old_id ] );
	$changes_made = true;
}

// Neue ID aktivieren (falls noch nicht)
if ( ! isset( $settings['active_modules'][ $new_id ] ) ) {
	echo "✓ Neue Modul-ID '$new_id' wird aktiviert\n";
	$settings['active_modules'][ $new_id ] = true;
	$changes_made = true;
}

if ( $changes_made ) {
	update_option( 'dgptm_suite_settings', $settings );
	echo "\n✅ Settings aktualisiert!\n\n";
} else {
	echo "\nℹ️  Keine Änderungen nötig.\n\n";
}

echo "Aktive Module nachher:\n";
$updated = get_option( 'dgptm_suite_settings', [] );
print_r( isset( $updated['active_modules'] ) ? $updated['active_modules'] : 'Keine' );

echo "\n\n=== WICHTIG ===\n";
echo "1. Permalinks neu speichern: WordPress Admin → Einstellungen → Permalinks → Speichern\n";
echo "2. Diese Datei LÖSCHEN für Sicherheit!\n";
echo "3. Browser-Cache leeren\n";
echo "4. Seite neu laden\n";

echo '</pre>';
echo '<p><a href="' . admin_url() . '">Zurück zum WordPress Dashboard</a></p>';
