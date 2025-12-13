<?php
/**
 * Plugin Name: DGPTM - Gehaltsbarometer
 * Description: Erfasst und zeigt Gehaltsdaten – automatisches Mapping aller Formidable-Felder (außer „ignore*") auf separate Spalten. Neue Felder können per „Spalten aktualisieren" angelegt werden.
 * Version:     4.0
 * Author:      Sebastian Melzer
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Gehaltsbarometer {

    /** @var self|null */
    private static $instance = null;

    /** @var string */
    private $table_name;

    /** @var int Formidable-Formular-ID */
    private $form_id = 24;

    /**
     * Holt eine Einstellung aus dem zentralen Settings-System
     */
    private function get_setting($key, $default = null) {
        if (function_exists('dgptm_get_module_setting')) {
            return dgptm_get_module_setting('gehaltsstatistik', $key, $default);
        }
        // Fallback auf alte Option-Keys
        $fallback_keys = [
            'form_id' => 24,
            'min_entries_per_region' => 'gb_min_entries_per_bundesland',
            'form_intro' => 'gb_form_intro'
        ];
        if (isset($fallback_keys[$key])) {
            if (is_int($fallback_keys[$key])) {
                return $fallback_keys[$key];
            }
            return get_option($fallback_keys[$key], $default);
        }
        return $default;
    }

    /** @var array Regionale Zuordnung der Bundesländer */
    private $regions = [
        'Nord' => ['Schleswig-Holstein', 'Hamburg', 'Bremen', 'Niedersachsen', 'Mecklenburg-Vorpommern'],
        'Ost'  => ['Brandenburg', 'Berlin', 'Sachsen-Anhalt', 'Sachsen', 'Thüringen'],
        'West' => ['Nordrhein-Westfalen', 'Hessen', 'Rheinland-Pfalz', 'Saarland'],
        'Süd'  => ['Bayern', 'Baden-Württemberg']
    ];

    /* --------------------------------------------------------------------- */
    /* ––– Singleton – öffentliche Schnittstelle –––                         */
    /* --------------------------------------------------------------------- */

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /* --------------------------------------------------------------------- */
    /* ––– Lifecycle / Bootstrap –––                                         */
    /* --------------------------------------------------------------------- */

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'gehaltsbarometer';

        register_activation_hook( __FILE__, [ $this, 'activate' ] );

        // DGPTM Suite: Settings werden zentral ueber Modul-Einstellungen verwaltet
        // add_action( 'admin_menu',            [ $this, 'add_admin_menu' ] );
        // add_action( 'admin_init',            [ $this, 'register_settings' ] );

        // Nur Eintraege-Seite behalten
        add_action( 'admin_menu', [ $this, 'add_entries_menu' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_scripts' ] );

        // Formidable-Integration
        add_action( 'frm_after_create_entry',[ $this, 'process_formidable_submission' ], 30, 2 );

        // Shortcodes
        add_shortcode( 'gehaltsbarometer',             [ $this, 'render_gehaltsbarometer' ] );
        add_shortcode( 'gehaltsbarometer_statistik',   [ $this, 'render_statistik' ] );
        add_shortcode( 'gehaltsbarometer_einladung',   [ $this, 'render_einladung' ] );
        add_shortcode( 'gehaltsbarometer_chart',       [ $this, 'render_gehaltsbarometer_chart' ] );
        add_shortcode( 'gehaltsbarometer_is',          [ $this, 'render_gehaltsbarometer_is' ] );
        add_shortcode( 'gehaltsbarometer_isnot',       [ $this, 'render_gehaltsbarometer_isnot' ] );
        add_shortcode( 'gehaltsbarometer_filled',      [ $this, 'shortcode_filled' ] );
        add_shortcode( 'gehaltsbarometer_popup_guard', [ $this, 'shortcode_popup_guard' ] );

        // AJAX
        add_action( 'wp_ajax_gb_check_filled',                  [ $this, 'ajax_check_filled' ] );
        add_action( 'wp_ajax_nopriv_gb_check_filled',           [ $this, 'ajax_check_filled' ] );
        add_action( 'wp_ajax_gb_delete',                        [ $this, 'handle_delete' ] );
        add_action( 'wp_ajax_gb_region_data',                   [ $this, 'ajax_region_data' ] );
        add_action( 'wp_ajax_nopriv_gb_region_data',            [ $this, 'ajax_region_data' ] );

        // Admin-POST-Hooks für „Alles löschen" & Einzellöschung/Korrektur
        add_action( 'admin_post_gb_purge_data',   [ $this, 'handle_purge_data' ] );
        add_action( 'admin_post_gb_update_entry', [ $this, 'handle_update_entry' ] );
        add_action( 'admin_post_gb_delete_entry', [ $this, 'handle_delete_entry' ] );
    }

    /* --------------------------------------------------------------------- */
    /* ––– Tools: PII-Erkennung (anonymisierte Anzeige) –––                  */
    /* --------------------------------------------------------------------- */

    /** Heuristische PII-Pattern-Liste (lowercase Vergleiche, enthält Umlaute-Varianten). */
    private function pii_patterns(): array {
        return [
            'name', 'vorname', 'nachname', 'fullname', 'full_name',
            'mail', 'e-mail', 'email',
            'telefon', 'phone', 'handy', 'mobile',
            'straße', 'strasse', 'str', 'hausnummer', 'plz', 'postleitzahl', 'ort', 'stadt', 'adresse', 'address',
            'geburt', 'birthday', 'geburtsdatum', 'birth',
            'firma', 'unternehmen', 'company', 'arbeitgeber', 'employer',
            'klinik', 'krankenhaus', 'abteilung',
            'iban', 'bic',
            'username', 'user', 'benutzer',
            'person', 'kontakt',
        ];
    }

    /** Spalte als PII werten? */
    private function is_pii_column( string $col ): bool {
        $lc = mb_strtolower( $col );
        // nie PII: technische/Meta-Spalten
        if ( in_array( $lc, [ 'id', 'created_at', 'salary', 'bundesland', 'options' ], true ) ) {
            return false;
        }
        // Nur echte „col_*"-Nutzdaten untersuchen
        $hay = $lc;
        foreach ( $this->pii_patterns() as $p ) {
            if ( strpos( $hay, $p ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /** Liefert Spaltenliste ohne PII (immer inkl. id/created_at/salary/bundesland). */
    private function get_anonymized_columns(): array {
        $cols = $this->get_existing_columns();
        if ( empty( $cols ) ) {
            return [];
        }
        $keep = [];
        foreach ( $cols as $c ) {
            if ( in_array( $c, [ 'id', 'created_at', 'salary', 'bundesland' ], true ) ) {
                $keep[] = $c;
                continue;
            }
            if ( $this->is_pii_column( $c ) ) {
                continue;
            }
            $keep[] = $c;
        }
        return $keep;
    }

    /* --------------------------------------------------------------------- */
    /* ––– AJAX: Teilnahme-Status –––                                       */
    /* --------------------------------------------------------------------- */

    public function ajax_check_filled(): void {
        $filled = null;

        if ( is_user_logged_in() ) {
            $uid  = get_current_user_id();
            $last = (int) get_user_meta( $uid, 'gb_last_submission', true );
            $filled = ( $last && gmdate( 'Y', (int) $last ) === gmdate( 'Y' ) );
        } else {
            $filled = null;
        }

        wp_send_json_success( [ 'filled' => $filled ] );
    }

    /* --------------------------------------------------------------------- */
    /* ––– Shortcode: Popup-Guard (Marker-DIV) –––                           */
    /* --------------------------------------------------------------------- */

    public function shortcode_popup_guard( $atts = [] ): string {
        // POPUP DEAKTIVIERT - Gibt nichts zurück
        // Um wieder zu aktivieren, entfernen Sie diese Zeile und kommentieren Sie den Code unten ein
        return '';

        /* POPUP-CODE DEAKTIVIERT
        $atts = shortcode_atts( [
            'id'      => '',
            'page_id' => '',
        ], $atts, 'gehaltsbarometer_popup_guard' );

        $popup_id = (int) $atts['id'];
        if ( $popup_id <= 0 ) {
            return '';
        }

        $target_page_id = (int) $atts['page_id'];
        if ( $target_page_id > 0 && get_queried_object_id() !== $target_page_id ) {
            return '';
        }

        $cookie_key = 'gb_popup_' . $popup_id . '_' . gmdate( 'Y-m' );

        $attrs = sprintf(
            'class="gb-popup-guard" data-popup-id="%d" data-cookie-key="%s" data-ajax-url="%s"',
            $popup_id,
            esc_attr( $cookie_key ),
            esc_url( admin_url( 'admin-ajax.php' ) )
        );

        return sprintf( '<div %s style="display:none" aria-hidden="true"></div>', $attrs );
        */
    }

    /* --------------------------------------------------------------------- */
    /* ––– Datenbank – Activation –––                                        */
    /* --------------------------------------------------------------------- */

    public function activate(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE `{$this->table_name}` (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            salary      DECIMAL(12,2)       NOT NULL DEFAULT 0,
            bundesland  VARCHAR(100)        NOT NULL DEFAULT '',
            options     LONGTEXT            NULL,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /* --------------------------------------------------------------------- */
    /* ––– Hilfsfunktionen (Felder/Spalten) –––                              */
    /* --------------------------------------------------------------------- */

    private function get_existing_columns(): array {
        global $wpdb;

        $table = esc_sql( $this->table_name );
        $results = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" );

        $columns = [];
        if ( $results ) {
            foreach ( $results as $col ) {
                if ( isset( $col->Field ) ) {
                    $columns[] = (string) $col->Field;
                }
            }
        }
        return $columns;
    }

    private function get_form_fields(): array {
        if ( ! class_exists( 'FrmField' ) ) {
            return [];
        }
        $fields = \FrmField::get_all_for_form( $this->form_id );
        if ( ! is_array( $fields ) ) {
            return [];
        }

        $out = [];
        foreach ( $fields as $f ) {
            $key = isset( $f->field_key ) ? (string) $f->field_key : '';
            if ( $key === '' ) {
                continue;
            }
            if ( stripos( $key, 'ignore' ) === 0 ) {
                continue;
            }
            $out[] = $key;
        }
        return $out;
    }

    private static function field_key_to_column_name( string $field_key ): string {
        $sanitized = preg_replace( '/[^a-zA-Z0-9_]/', '_', $field_key );
        $sanitized = preg_replace( '/_+/', '_', $sanitized ?? '' );
        $sanitized = trim( (string) $sanitized, '_' );
        return 'col_' . strtolower( $sanitized );
    }

    private function add_missing_columns_for_form_fields(): string {
        global $wpdb;

        $existing_cols = $this->get_existing_columns();
        $form_fields   = $this->get_form_fields();

        if ( empty( $form_fields ) ) {
            return 'Keine geeigneten Formfelder gefunden (Formidable inaktiv?).';
        }

        $alter_parts = [];
        foreach ( $form_fields as $fkey ) {
            $col_name = self::field_key_to_column_name( $fkey );
            if ( $col_name === '' ) {
                continue;
            }
            if ( ! in_array( $col_name, $existing_cols, true ) ) {
                $alter_parts[] = "ADD `{$col_name}` TEXT NULL";
            }
        }

        if ( empty( $alter_parts ) ) {
            return 'Alle Spalten bereits vorhanden.';
        }

        $sql = "ALTER TABLE `{$this->table_name}` " . implode( ', ', $alter_parts );
        $wpdb->query( $sql );

        return 'Spalten ergänzt: ' . implode( ', ', $alter_parts );
    }

    private function migrate_salary_data(): string {
        global $wpdb;

        $columns = $this->get_existing_columns();
        
        if ( ! in_array( 'col_gehaltnetto', $columns, true ) ) {
            return 'Spalte "col_gehaltnetto" nicht gefunden. Bitte zuerst "Spalten aktualisieren" ausführen.';
        }

        $count = $wpdb->query(
            "UPDATE `{$this->table_name}` 
             SET salary = CAST(col_gehaltnetto AS DECIMAL(12,2)),
                 bundesland = COALESCE(NULLIF(bundesland, ''), col_bundesland)
             WHERE (salary = 0 OR salary IS NULL)
               AND col_gehaltnetto IS NOT NULL 
               AND col_gehaltnetto != ''"
        );

        if ( $count === false ) {
            return 'Fehler beim Migrieren der Daten.';
        }

        return sprintf( '%d Einträge wurden aktualisiert. Die Statistik sollte jetzt korrekt angezeigt werden.', $count );
    }

    /* --------------------------------------------------------------------- */
    /* ––– Form-Submission –––                                               */
    /* --------------------------------------------------------------------- */

    public function process_formidable_submission( int $entry_id, int $form_id ): void {

        if ( $form_id !== $this->form_id || ! is_user_logged_in() ) {
            return;
        }

        $uid  = get_current_user_id();
        $last = (int) get_user_meta( $uid, 'gb_last_submission', true );

        if ( $last && gmdate( 'Y', (int) $last ) === gmdate( 'Y' ) ) {
            return;
        }

        if ( ! class_exists( 'FrmEntry' ) || ! class_exists( 'FrmField' ) ) {
            return;
        }

        $entry = \FrmEntry::getOne( $entry_id, true );
        if ( ! $entry || ! isset( $entry->metas ) || ! is_array( $entry->metas ) ) {
            return;
        }

        $raw_data = [];
        foreach ( (array) $entry->metas as $field_id => $val ) {

            $field_object = \FrmField::getOne( (int) $field_id );
            $field_key    = ( $field_object && isset( $field_object->field_key ) )
                ? (string) $field_object->field_key
                : 'field_' . (int) $field_id;

            if ( stripos( $field_key, 'ignore' ) === 0 ) {
                continue;
            }

            $raw_data[ $field_key ] = maybe_serialize( $val );
        }

        $insert = [
            'created_at' => current_time( 'mysql' ),
            'salary'     => 0.0,
            'bundesland' => '',
            'options'    => '',
        ];

        $salary_value = 0.0;
        $possible_salary_fields = [ 'gehaltnetto', 'gehalt_netto', 'gehalt', 'salary', 'brutto', 'netto' ];
        foreach ( $possible_salary_fields as $field ) {
            if ( isset( $raw_data[ $field ] ) ) {
                $temp = (float) $raw_data[ $field ];
                if ( $temp > 0 ) {
                    $salary_value = $temp;
                    break;
                }
            }
        }
        $insert['salary'] = $salary_value;

        $possible_bundesland_fields = [ 'bundesland', 'land', 'state' ];
        foreach ( $possible_bundesland_fields as $field ) {
            if ( isset( $raw_data[ $field ] ) && ! empty( $raw_data[ $field ] ) ) {
                $insert['bundesland'] = sanitize_text_field( (string) $raw_data[ $field ] );
                break;
            }
        }

        $existing_cols = $this->get_existing_columns();
        foreach ( $raw_data as $fkey => $val ) {
            $col_name = self::field_key_to_column_name( (string) $fkey );
            if ( $col_name !== '' && in_array( $col_name, $existing_cols, true ) ) {
                $insert[ $col_name ] = maybe_serialize( $val );
            }
        }

        global $wpdb;
        $wpdb->insert( $this->table_name, $insert );

        update_user_meta( $uid, 'gb_last_submission', time() );
    }

    /* --------------------------------------------------------------------- */
    /* ––– Assets – Enqueue –––                                              */
    /* --------------------------------------------------------------------- */

    public function enqueue_scripts(): void {
        wp_enqueue_script( 'jquery' );

        wp_register_script(
            'gb-map-script',
            false,
            [ 'jquery' ],
            null,
            true
        );

        wp_localize_script( 'gb-map-script', 'gb_ajax_obj', [
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'delete_nonce' => wp_create_nonce( 'gb_delete_nonce' ),
            'region_nonce' => wp_create_nonce( 'gb_region_nonce' ),
            'min_entries'  => (int) $this->get_setting( 'min_entries_per_region', 3 ),
        ] );

        wp_add_inline_script( 'gb-map-script', $this->get_inline_js() );
        wp_enqueue_script( 'gb-map-script' );

        // Popup-Guard Script
        wp_register_script(
            'gb-popup-guard',
            false,
            [ 'jquery' ],
            null,
            true
        );

        $guard_js = <<<JS
jQuery(function($){
  function getCookie(name){
    var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\\[\\]\\\\/+^])/g, '\\\\$1') + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }
  function setCookie(name, value, expires){
    var cookie = name + '=' + encodeURIComponent(value) + '; path=/; SameSite=Lax';
    if (expires) cookie += '; expires=' + expires.toUTCString();
    document.cookie = cookie;
  }
  function endOfCurrentMonthUTC(){
    var now = new Date();
    return new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 1, 0, 23, 59, 59));
  }
  function ensureElementorReady(cb){
    function go(){ try { cb(); } catch(e){} }
    if (window.elementorProFrontend && window.elementorProFrontend.modules && window.elementorProFrontend.modules.popup) {
      go();
    } else {
      jQuery(window).on('elementor/frontend/init', go);
      document.addEventListener('DOMContentLoaded', go);
    }
  }

  $('.gb-popup-guard[data-popup-id]').each(function(){
    var \$el   = $(this);
    var id     = parseInt(\$el.data('popup-id'), 10) || 0;
    var key    = String(\$el.data('cookie-key') || '');
    var ajax   = String(\$el.data('ajax-url') || '');
    if(!id || !key || !ajax){ return; }

    if (getCookie(key)) { return; }

    $.post(ajax, { action: 'gb_check_filled' })
      .done(function(res){
        var filled = (res && res.success) ? res.data.filled : null;

        if (filled === false) {
          ensureElementorReady(function(){
            if (window.elementorProFrontend && window.elementorProFrontend.modules && window.elementorProFrontend.modules.popup) {
              window.elementorProFrontend.modules.popup.showPopup({ id: id });
              setCookie(key, '1', endOfCurrentMonthUTC());
            }
          });
        } else {
          setCookie(key, 'checked', endOfCurrentMonthUTC());
        }
      })
      .fail(function(){
        setCookie(key, 'neterr', endOfCurrentMonthUTC());
      });
  });
});
JS;

        wp_add_inline_script( 'gb-popup-guard', $guard_js );
        wp_enqueue_script( 'gb-popup-guard' );
    }

    private function get_inline_js(): string {
        return <<<JS
jQuery(function($){

    /* --------- Löschen (Frontend-Statistik-Liste) --------- */
    $(document).on('click', '.gb-delete-btn', function(e){
        e.preventDefault();
        if( ! confirm('Diesen Eintrag wirklich löschen?') ) return;

        const btn = $(this), id = parseInt(btn.data('id'), 10) || 0;

        $.post(gb_ajax_obj.ajax_url, {
            action : 'gb_delete',
            nonce  : gb_ajax_obj.delete_nonce,
            id     : id
        }).done(function(res){
            if(res && res.success){
                btn.closest('tr').remove();
            }else{
                alert((res && res.data) ? res.data : 'Fehler');
            }
        }).fail(function(){
            alert('Netzwerkfehler.');
        });
    });

    /* --------- Deutschlandkarte mit Regionen --------- */
    const \$mapContainer = $('#gb-map-container');
    const \$year = $('#gb-year-select');
    const \$range = $('#gb-overall-range');
    const \$avg = $('#gb-yearly-average');

    if(\$mapContainer.length){
        loadRegionData();
        \$year.on('change', loadRegionData);
    }

    function loadRegionData(){
        const yearVal = (\$year.length ? parseInt(\$year.val(), 10) : (new Date().getFullYear())) || (new Date().getFullYear());
        
        $.post(gb_ajax_obj.ajax_url, {
            action : 'gb_region_data',
            nonce  : gb_ajax_obj.region_nonce,
            year   : yearVal
        }).done(function(res){
            if(res && res.success && res.data){
                updateMap(res.data, yearVal);
            }else{
                \$range.text('Keine Daten verfügbar.');
                \$avg.text('');
                $('.gb-region').removeClass('has-data').attr('data-salary', '');
            }
        }).fail(function(){
            \$range.text('Fehler beim Laden der Daten.');
            \$avg.text('');
        });
    }

    function updateMap(data, year){
        // Gesamtstatistik anzeigen
        if(data.overall_min > 0){
            \$range.text('Gehaltsspanne ' + year + ': ' + data.overall_min.toLocaleString('de-DE') + ' € – ' + data.overall_max.toLocaleString('de-DE') + ' €');
        } else {
            \$range.text('');
        }

        if(data.yearly_average > 0){
            \$avg.html('<strong>Durchschnittsgehalt ' + year + ': ' + data.yearly_average.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €</strong>');
        } else {
            \$avg.text('');
        }

        // Regionen aktualisieren
        $('.gb-region').each(function(){
            const \$region = $(this);
            const regionName = \$region.data('region');
            const regionData = data.regions[regionName];

            if(regionData && regionData.avg > 0){
                \$region.addClass('has-data')
                    .attr('data-salary', regionData.avg.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €')
                    .attr('data-count', regionData.count);
            } else {
                \$region.removeClass('has-data')
                    .attr('data-salary', '')
                    .attr('data-count', '0');
            }
        });
    }
});
JS;
    }

    /* --------------------------------------------------------------------- */
    /* ––– AJAX: Regions-Daten –––                                          */
    /* --------------------------------------------------------------------- */

    public function ajax_region_data(): void {
        check_ajax_referer( 'gb_region_nonce', 'nonce' );

        $year = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : (int) gmdate( 'Y' );

        global $wpdb;

        // Gesamtstatistik für das Jahr
        $overall = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    MIN(salary) AS overall_min, 
                    MAX(salary) AS overall_max,
                    AVG(salary) AS yearly_average,
                    COUNT(*) AS total_count
                FROM `{$this->table_name}`
                WHERE YEAR(created_at) = %d
                  AND salary > 0",
                $year
            )
        );

        if ( ! $overall || $overall->total_count == 0 ) {
            wp_send_json_success( [
                'regions'        => [],
                'overall_min'    => 0,
                'overall_max'    => 0,
                'yearly_average' => 0,
            ] );
        }

        // Statistik pro Bundesland abrufen
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    bundesland,
                    AVG(salary) AS avg_salary,
                    COUNT(*) AS cnt
                FROM `{$this->table_name}`
                WHERE YEAR(created_at) = %d
                  AND salary > 0
                  AND bundesland != ''
                GROUP BY bundesland",
                $year
            )
        );

        // Nach Regionen gruppieren
        $region_stats = [];
        foreach ( $this->regions as $region_name => $bundeslaender ) {
            $region_stats[$region_name] = [
                'total_salary' => 0,
                'count' => 0,
            ];
        }

        if ( $rows ) {
            foreach ( $rows as $row ) {
                $bundesland = (string) $row->bundesland;
                
                // Finde die passende Region
                foreach ( $this->regions as $region_name => $bundeslaender ) {
                    if ( in_array( $bundesland, $bundeslaender, true ) ) {
                        $region_stats[$region_name]['total_salary'] += (float) $row->avg_salary * (int) $row->cnt;
                        $region_stats[$region_name]['count'] += (int) $row->cnt;
                        break;
                    }
                }
            }
        }

        // Durchschnitt pro Region berechnen
        $min_entries = (int) $this->get_setting( 'min_entries_per_region', 3 );
        $regions = [];
        
        foreach ( $region_stats as $region_name => $stats ) {
            if ( $stats['count'] >= $min_entries ) {
                $regions[$region_name] = [
                    'avg' => $stats['count'] > 0 ? round( $stats['total_salary'] / $stats['count'], 2 ) : 0,
                    'count' => $stats['count'],
                ];
            }
        }

        wp_send_json_success( [
            'regions'        => $regions,
            'overall_min'    => isset( $overall->overall_min ) ? (int) $overall->overall_min : 0,
            'overall_max'    => isset( $overall->overall_max ) ? (int) $overall->overall_max : 0,
            'yearly_average' => isset( $overall->yearly_average ) ? round( (float) $overall->yearly_average, 2 ) : 0,
        ] );
    }

    /* --------------------------------------------------------------------- */
    /* ––– AJAX: Löschen (Frontend) –––                                      */
    /* --------------------------------------------------------------------- */

    public function handle_delete(): void {

        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
            wp_die( esc_html__( 'Ungültiger Aufruf.', 'default' ) );
        }

        check_ajax_referer( 'gb_delete_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Keine Berechtigung.' );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( $id <= 0 ) {
            wp_send_json_error( 'Ungültige ID.' );
        }

        global $wpdb;
        $deleted = $wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );

        if ( $deleted ) {
            wp_send_json_success();
        }
        wp_send_json_error( 'Löschen fehlgeschlagen.' );
    }

    /* --------------------------------------------------------------------- */
    /* ––– ADMIN: Alles löschen –––                                         */
    /* --------------------------------------------------------------------- */

    public function handle_purge_data(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'default' ) );
        }

        check_admin_referer( 'gb_purge_data' );

        $confirm = isset( $_POST['gb_confirm'] ) ? sanitize_text_field( (string) $_POST['gb_confirm'] ) : '';
        if ( $confirm !== 'JA' ) {
            wp_die( esc_html__( 'Bestätigung fehlgeschlagen.', 'default' ) );
        }

        global $wpdb;

        $wpdb->query( "TRUNCATE TABLE `{$this->table_name}`" );
        $wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'gb_last_submission' ], [ '%s' ] );

        $ref = wp_get_referer();
        $redirect = $ref ? $ref : admin_url( 'options-general.php?page=gehaltsbarometer' );
        $redirect = add_query_arg( 'gb_purge_done', '1', $redirect );
        wp_safe_redirect( $redirect );
        exit;
    }

    /* --------------------------------------------------------------------- */
    /* ––– Admin-Menüs & Settings –––                                       */
    /* --------------------------------------------------------------------- */

    /**
     * Fuegt nur die Eintraege-Seite hinzu (Settings werden zentral verwaltet)
     */
    public function add_entries_menu(): void {
        add_submenu_page(
            'options-general.php',
            'Gehaltsbarometer Einträge',
            'Gehaltsbarometer Einträge',
            'manage_options',
            'gehaltsbarometer_entries',
            [ $this, 'entries_page' ]
        );
    }

    /**
     * @deprecated Settings werden jetzt zentral ueber DGPTM Suite verwaltet
     */
    public function add_admin_menu(): void {
        // Nicht mehr verwendet - Settings in DGPTM Suite -> Modul-Einstellungen -> Gehaltsbarometer
    }

    public function register_settings(): void {
        register_setting( 'gb_settings', 'gb_form_intro', [
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default'           => '',
        ] );
        
        register_setting( 'gb_settings', 'gb_min_entries_per_bundesland', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 3,
        ] );
    }

    public function settings_page(): void {

        $scan_form = isset( $_GET['scan_form'] ) ? sanitize_text_field( (string) $_GET['scan_form'] ) : '';
        if ( $scan_form === '1' ) {
            $msg = $this->add_missing_columns_for_form_fields();
            echo '<div class="updated notice"><p>' . esc_html( $msg ) . '</p></div>';
        }

        $migrate_salary = isset( $_GET['migrate_salary'] ) ? sanitize_text_field( (string) $_GET['migrate_salary'] ) : '';
        if ( $migrate_salary === '1' ) {
            $msg = $this->migrate_salary_data();
            echo '<div class="updated notice"><p>' . esc_html( $msg ) . '</p></div>';
        }

        $purge_done = isset( $_GET['gb_purge_done'] ) ? sanitize_text_field( (string) $_GET['gb_purge_done'] ) : '';
        if ( $purge_done === '1' ) {
            echo '<div class="updated notice"><p>Alle Daten wurden gelöscht und alle Benutzer zurückgesetzt.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>Gehaltsbarometer&nbsp;Einstellungen</h1>

            <form method="post" action="options.php">
                <?php
                    settings_fields( 'gb_settings' );
                    do_settings_sections( 'gb_settings' );
                ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="gb_form_intro">Optionaler Info-Text (HTML)</label></th>
                        <td>
                            <textarea id="gb_form_intro" name="gb_form_intro" rows="6" cols="60"><?php echo esc_textarea( get_option( 'gb_form_intro', '' ) ); ?></textarea>
                            <p class="description">
                                Platzhalter <code>{jahr}</code> wird durch das aktuelle Jahr ersetzt.<br>
                                (Nur relevant im Shortcode <strong>[gehaltsbarometer]</strong>.)
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gb_min_entries_per_bundesland">Mindestanzahl Einträge pro Region</label></th>
                        <td>
                            <input type="number" id="gb_min_entries_per_bundesland" name="gb_min_entries_per_bundesland" 
                                   value="<?php echo esc_attr( (string) get_option( 'gb_min_entries_per_bundesland', 3 ) ); ?>" 
                                   min="1" max="100" step="1" class="small-text">
                            <p class="description">
                                Regionen mit weniger als dieser Anzahl von Einträgen werden auf der Karte nicht angezeigt (Datenschutz).<br>
                                <strong>Standard: 3</strong>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>

            <h2>Struktur aktualisieren</h2>
            <p>
                Alle Felder (außer „ignore…") des Formidable-Formulars (ID&nbsp;<?php echo (int) $this->form_id; ?>)
                werden eingelesen; fehlende Spalten werden automatisch angelegt.
            </p>
            <p>
                <a href="<?php echo esc_url( add_query_arg( 'scan_form', 1 ) ); ?>" class="button button-primary">
                    Spalten aktualisieren
                </a>
            </p>

            <hr>

            <h2>Gehaltsdaten migrieren</h2>
            <p>
                Falls die <code>salary</code>-Spalte leer ist (z.B. bei bestehenden Einträgen), werden die Werte aus 
                <code>col_gehaltnetto</code> in die <code>salary</code>-Spalte übertragen.
                Dies ist notwendig, damit die Statistik korrekt angezeigt wird.
            </p>
            <p>
                <a href="<?php echo esc_url( add_query_arg( 'migrate_salary', 1 ) ); ?>" class="button button-secondary">
                    Gehaltsdaten jetzt migrieren
                </a>
            </p>

            <hr>

            <h2>Datenbank vollständig zurücksetzen</h2>
            <p style="max-width:600px;">
                <strong>Warnung:</strong> Diese Aktion entfernt <em>sämtliche</em> Einträge aus dem
                Gehaltsbarometer <u>und</u> setzt bei allen Benutzern das Recht zur erneuten Teilnahme zurück.
                Dies kann <em>nicht</em> rückgängig gemacht werden.
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                  onsubmit="return confirm('Wirklich alle Daten löschen und Benutzer zurücksetzen?');">

                <?php wp_nonce_field( 'gb_purge_data' ); ?>
                <input type="hidden" name="action"     value="gb_purge_data">
                <input type="hidden" name="gb_confirm" value="JA">

                <?php submit_button(
                    'ALLE DATEN LÖSCHEN',
                    'delete',
                    'submit',
                    false,
                    [ 'style' => 'background:#dc3232;border-color:#a00;color:#fff;' ]
                ); ?>
            </form>
        </div>
        <?php
    }

    /* --------------------------------------------------------------------- */
    /* ––– Admin: Einträge (anonymisiert) –––                                */
    /* --------------------------------------------------------------------- */

    public function entries_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'default' ) );
        }

        $export_entries_csv = isset( $_GET['export_entries_csv'] ) ? sanitize_text_field( (string) $_GET['export_entries_csv'] ) : '';
        if ( $export_entries_csv === '1' ) {
            $this->export_entries_csv();
        }

        $editing_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
        $notice     = isset( $_GET['gb_updated'] ) ? sanitize_text_field( (string) $_GET['gb_updated'] ) : '';
        $deleted    = isset( $_GET['gb_deleted'] ) ? sanitize_text_field( (string) $_GET['gb_deleted'] ) : '';

        if ( $notice === '1' ) {
            echo '<div class="updated notice"><p>Eintrag erfolgreich aktualisiert.</p></div>';
        }
        if ( $deleted === '1' ) {
            echo '<div class="updated notice"><p>Eintrag gelöscht.</p></div>';
        }

        if ( $editing_id > 0 ) {
            $this->render_entry_edit_form( $editing_id );
            return;
        }

        $this->render_entries_list();
    }

    private function render_entries_list(): void {
        global $wpdb;

        $columns = $this->get_anonymized_columns();
        if ( empty( $columns ) ) {
            echo '<div class="wrap"><h1>Gehaltsbarometer Einträge</h1><p>Keine Spalten gefunden.</p></div>';
            return;
        }

        $per_page = 50;
        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $offset   = ( $paged - 1 ) * $per_page;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->table_name}`" );

        $safe_cols = array_map(
            static function( $col ) {
                $col = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $col );
                return "`{$col}`";
            },
            $columns
        );
        $select_cols = implode( ', ', $safe_cols );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT $select_cols FROM `{$this->table_name}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        echo '<div class="wrap">';
        echo '<h1>Gehaltsbarometer Einträge (anonymisiert)</h1>';

        if ( empty( $rows ) ) {
            echo '<p>Keine Datensätze vorhanden.</p></div>';
            return;
        }

        echo '<p>';
        echo '<a href="' . esc_url( add_query_arg( 'export_entries_csv', '1' ) ) . '" class="button button-primary">CSV exportieren (alle Einträge)</a>';
        echo '</p>';

        $total_pages = (int) ceil( $total / $per_page );
        if ( $total_pages > 1 ) {
            echo '<div class="tablenav top"><div class="tablenav-pages">';
            echo paginate_links( [
                'base'      => add_query_arg( 'paged', '%#%' ),
                'format'    => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total'     => $total_pages,
                'current'   => $paged,
            ] );
            echo '</div></div>';
        }

        echo '<table class="widefat striped"><thead><tr>';
        foreach ( $columns as $c ) {
            echo '<th>' . esc_html( $c ) . '</th>';
        }
        echo '<th>Aktion</th></tr></thead><tbody>';

        foreach ( $rows as $r ) {
            echo '<tr>';
            foreach ( $columns as $c ) {
                $val = maybe_unserialize( $r->$c );
                if ( is_array( $val ) || is_object( $val ) ) {
                    $val = wp_json_encode( $val, JSON_UNESCAPED_UNICODE );
                }
                echo '<td>' . esc_html( (string) $val ) . '</td>';
            }

            $id = (int) $r->id;
            $edit_url = add_query_arg(
                [ 'page' => 'gehaltsbarometer_entries', 'edit' => $id ],
                admin_url( 'options-general.php' )
            );

            echo '<td style="white-space:nowrap;">';
            echo '<a class="button button-small" href="' . esc_url( $edit_url ) . '">Bearbeiten</a> ';

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline" onsubmit="return confirm(\'Diesen Eintrag wirklich löschen?\');">';
            wp_nonce_field( 'gb_delete_entry_' . $id );
            echo '<input type="hidden" name="action" value="gb_delete_entry">';
            echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '">';
            echo '<button type="submit" class="button button-small button-link-delete" style="color:#a00;">Löschen</button>';
            echo '</form>';

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        if ( $total_pages > 1 ) {
            echo '<div class="tablenav bottom"><div class="tablenav-pages">';
            echo paginate_links( [
                'base'      => add_query_arg( 'paged', '%#%' ),
                'format'    => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total'     => $total_pages,
                'current'   => $paged,
            ] );
            echo '</div></div>';
        }

        echo '</div>';
    }

    private function render_entry_edit_form( int $id ): void {
        global $wpdb;

        $columns = $this->get_anonymized_columns();
        if ( empty( $columns ) ) {
            echo '<div class="wrap"><h1>Eintrag bearbeiten</h1><p>Keine Spalten vorhanden.</p></div>';
            return;
        }

        $safe_cols = array_map(
            static function( $col ) {
                $col = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $col );
                return "`{$col}`";
            },
            $columns
        );
        $select_cols = implode( ', ', $safe_cols );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT $select_cols FROM `{$this->table_name}` WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        echo '<div class="wrap"><h1>Eintrag bearbeiten (ID ' . esc_html( (string) $id ) . ')</h1>';

        if ( ! $row ) {
            echo '<p>Datensatz nicht gefunden.</p></div>';
            return;
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'gb_update_entry_' . $id );
        echo '<input type="hidden" name="action" value="gb_update_entry">';
        echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '">';

        echo '<table class="form-table"><tbody>';
        foreach ( $columns as $c ) {
            $readonly = ( $c === 'id' || $c === 'created_at' ) ? 'readonly' : '';
            $val = maybe_unserialize( $row[ $c ] );
            if ( is_array( $val ) || is_object( $val ) ) {
                $val = wp_json_encode( $val, JSON_UNESCAPED_UNICODE );
            }
            echo '<tr>';
            echo '<th scope="row"><label>' . esc_html( $c ) . '</label></th>';
            echo '<td><input type="text" class="regular-text" name="fields[' . esc_attr( $c ) . ']" value="' . esc_attr( (string) $val ) . '" ' . $readonly . '></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        submit_button( 'Speichern' );
        echo ' <a class="button button-secondary" href="' . esc_url( admin_url( 'options-general.php?page=gehaltsbarometer_entries' ) ) . '">Zurück</a>';
        echo '</form></div>';
    }

    public function handle_update_entry(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'default' ) );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( $id <= 0 ) {
            wp_die( esc_html__( 'Ungültige ID.', 'default' ) );
        }

        check_admin_referer( 'gb_update_entry_' . $id );

        $fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? $_POST['fields'] : [];
        if ( empty( $fields ) ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'gehaltsbarometer_entries', 'edit' => $id ], admin_url( 'options-general.php' ) ) );
            exit;
        }

        $allowed = array_diff( $this->get_anonymized_columns(), [ 'id', 'created_at' ] );

        $data = [];
        foreach ( $allowed as $col ) {
            if ( array_key_exists( $col, $fields ) ) {
                $val = is_array( $fields[ $col ] ) ? '' : (string) $fields[ $col ];
                $data[ $col ] = wp_unslash( $val );
            }
        }

        if ( empty( $data ) ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'gehaltsbarometer_entries', 'edit' => $id ], admin_url( 'options-general.php' ) ) );
            exit;
        }

        global $wpdb;
        $formats = array_fill( 0, count( $data ), '%s' );
        $wpdb->update( $this->table_name, $data, [ 'id' => $id ], $formats, [ '%d' ] );

        $redirect = add_query_arg(
            [ 'page' => 'gehaltsbarometer_entries', 'gb_updated' => '1' ],
            admin_url( 'options-general.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_delete_entry(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'default' ) );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( $id <= 0 ) {
            wp_die( esc_html__( 'Ungültige ID.', 'default' ) );
        }

        check_admin_referer( 'gb_delete_entry_' . $id );

        global $wpdb;
        $wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );

        $redirect = add_query_arg(
            [ 'page' => 'gehaltsbarometer_entries', 'gb_deleted' => '1' ],
            admin_url( 'options-general.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /* --------------------------------------------------------------------- */
    /* ––– Shortcodes – Frontend –––                                        */
    /* --------------------------------------------------------------------- */

    public function render_gehaltsbarometer(): string {

        $intro = str_replace( '{jahr}', gmdate( 'Y' ), (string) $this->get_setting( 'form_intro', '' ) );

        ob_start();
        echo wp_kses_post( $intro );

        if ( ! is_user_logged_in() ) {
            echo '<p>Bitte loggen Sie sich ein, um an der Gehaltsumfrage teilzunehmen.</p>';
            return ob_get_clean();
        }

        $uid      = get_current_user_id();
        $last     = (int) get_user_meta( $uid, 'gb_last_submission', true );
        $answered = ( $last && gmdate( 'Y', (int) $last ) === gmdate( 'Y' ) );

        if ( $answered ) {
            $year_now  = (int) gmdate( 'Y' );
            $year_next = $year_now + 1;
            printf(
                '<p>%s</p>',
                esc_html(
                    "Vielen Dank für Ihre Teilnahme für das Jahr {$year_now}. "
                  . "Bitte nehmen Sie auch {$year_next} wieder teil! "
                  . "Bis dahin können Sie hier die aktuelle Statistik einsehen:"
                )
            );
        } else {
            if ( class_exists( 'FrmForm' ) ) {
                echo do_shortcode( '[formidable id=24]' );
            } else {
                echo '<p><strong>Fehler:</strong> Formidable Forms ist nicht aktiv.</p>';
            }
        }

        return ob_get_clean();
    }

    public function shortcode_filled( $atts = [] ): string {
        if ( ! is_user_logged_in() ) {
            return 'false';
        }
        $uid  = get_current_user_id();
        $last = (int) get_user_meta( $uid, 'gb_last_submission', true );
        return ( $last && gmdate( 'Y', (int) $last ) === gmdate( 'Y' ) ) ? 'true' : 'false';
    }

    public function render_gehaltsbarometer_chart(): string {

        global $wpdb;
        $year_now = (int) gmdate( 'Y' );

        $yrs = $wpdb->get_col( "SELECT DISTINCT YEAR(created_at) FROM `{$this->table_name}` ORDER BY 1 DESC" );
        if ( ! is_array( $yrs ) ) {
            $yrs = [];
        }
        array_unshift( $yrs, $year_now );
        $yrs = array_values( array_unique( array_map( 'intval', $yrs ) ) );

        ob_start(); ?>
        <style>
        .gb-map-wrapper {
            max-width: 700px;
            margin: 20px auto;
            text-align: center;
        }
        
        #gb-map-container {
            position: relative;
            display: inline-block;
            margin: 0 auto;
        }
        
        #gb-map-container img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        
        .gb-region {
            position: absolute;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }
        
        .gb-region:hover {
            opacity: 0.7;
        }
        
        .gb-region.has-data::after {
            content: attr(data-salary);
            position: absolute;
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }
        
        .gb-region.has-data:hover::after {
            opacity: 1;
        }
        
        /* Regionen-Positionierung */
        .gb-region[data-region="Nord"] {
            top: 5%;
            left: 30%;
            width: 40%;
            height: 25%;
        }
        
        .gb-region[data-region="Ost"] {
            top: 15%;
            right: 15%;
            width: 30%;
            height: 45%;
        }
        
        .gb-region[data-region="West"] {
            top: 30%;
            left: 10%;
            width: 35%;
            height: 35%;
        }
        
        .gb-region[data-region="Süd"] {
            bottom: 10%;
            left: 25%;
            width: 50%;
            height: 30%;
        }
        
        .gb-stats {
            margin-top: 30px;
            text-align: center;
        }
        
        .gb-stats > div {
            margin: 10px 0;
        }
        
        #gb-overall-range {
            color: #666;
            font-size: 14px;
        }
        
        #gb-yearly-average {
            font-size: 18px;
            color: #333;
        }
        </style>
        
        <div class="gb-map-wrapper">
            <?php if ( count( $yrs ) > 1 ) : ?>
                <label for="gb-year-select" style="font-weight:bold;">Jahr: </label>
                <select id="gb-year-select" style="margin-bottom:15px;padding:5px;">
                    <?php foreach ( $yrs as $y ) : ?>
                        <option value="<?php echo esc_attr( (string) $y ); ?>" <?php selected( $y, $year_now ); ?>>
                            <?php echo esc_html( (string) $y ); ?>
                        </option>
                    <?php endforeach; ?>
                </select><br>
            <?php else : ?>
                <input type="hidden" id="gb-year-select" value="<?php echo esc_attr( (string) $year_now ); ?>">
            <?php endif; ?>

            <div id="gb-map-container">
                <img src="<?php echo esc_url( plugins_url( 'assets/deutschland-karte.jpg', __FILE__ ) ); ?>" 
                     alt="Deutschlandkarte" 
                     style="max-width: 100%; height: auto;">
                
                <div class="gb-region" data-region="Nord" data-salary="" data-count="0"></div>
                <div class="gb-region" data-region="Ost" data-salary="" data-count="0"></div>
                <div class="gb-region" data-region="West" data-salary="" data-count="0"></div>
                <div class="gb-region" data-region="Süd" data-salary="" data-count="0"></div>
            </div>
            
            <div class="gb-stats">
                <div id="gb-overall-range"></div>
                <div id="gb-yearly-average"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_gehaltsbarometer_is( $atts, $content = null ): string {
        if ( is_user_logged_in() ) {
            $last = (int) get_user_meta( get_current_user_id(), 'gb_last_submission', true );
            if ( $last && gmdate( 'Y', (int) $last ) === gmdate( 'Y' ) ) {
                return do_shortcode( (string) $content );
            }
        }
        return '';
    }

    public function render_gehaltsbarometer_isnot( $atts, $content = null ): string {
        if ( ! is_user_logged_in() ) {
            return do_shortcode( (string) $content );
        }
        $last = (int) get_user_meta( get_current_user_id(), 'gb_last_submission', true );
        if ( ! $last || gmdate( 'Y', (int) $last ) !== gmdate( 'Y' ) ) {
            return do_shortcode( (string) $content );
        }
        return '';
    }

    public function render_einladung( $atts, $content = null ): string {
        return $this->render_gehaltsbarometer_isnot( $atts, $content );
    }

    /* --------------------------------------------------------------------- */
    /* ––– Statistik-Tabelle & CSV –––                                      */
    /* --------------------------------------------------------------------- */

    public function render_statistik(): string {

        if ( ! current_user_can( 'manage_options' ) ) {
            return '<p>Keine Berechtigung.</p>';
        }

        $export_csv = isset( $_GET['export_csv'] ) ? sanitize_text_field( (string) $_GET['export_csv'] ) : '';
        if ( $export_csv === '1' ) {
            $this->export_csv();
        }

        global $wpdb;
        $columns = $this->get_existing_columns();

        if ( empty( $columns ) ) {
            return '<p>Keine Datensätze.</p>';
        }

        $safe_cols = array_map(
            static function( $col ) {
                $col = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $col );
                return "`{$col}`";
            },
            $columns
        );
        $select_cols = implode( ', ', $safe_cols );

        $rows = $wpdb->get_results( "SELECT $select_cols FROM `{$this->table_name}` ORDER BY created_at DESC" );

        if ( empty( $rows ) ) {
            return '<p>Keine Datensätze.</p>';
        }

        ob_start(); ?>
        <h2>Vollständige Gehaltsbarometer-Statistik</h2>
        <p>
            <a href="<?php echo esc_url( add_query_arg( 'export_csv', 1 ) ); ?>" class="button">CSV exportieren (vollständig)</a>
        </p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <?php foreach ( $columns as $col_name ) : ?>
                        <th><?php echo esc_html( (string) $col_name ); ?></th>
                    <?php endforeach; ?>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $r ) : ?>
                <tr>
                    <?php foreach ( $columns as $col_name ) : ?>
                        <td>
                            <?php
                                $val = maybe_unserialize( $r->$col_name );
                                if ( is_array( $val ) || is_object( $val ) ) {
                                    echo esc_html( wp_json_encode( $val, JSON_UNESCAPED_UNICODE ) );
                                } else {
                                    echo esc_html( (string) $val );
                                }
                            ?>
                        </td>
                    <?php endforeach; ?>
                    <td>
                        <button class="gb-delete-btn button button-small" data-id="<?php echo esc_attr( (string) $r->id ); ?>">
                            Löschen
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    private function export_csv(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'default' ) );
        }

        global $wpdb;
        $columns = $this->get_existing_columns();

        if ( empty( $columns ) ) {
            wp_die( esc_html__( 'Keine Daten.', 'default' ) );
        }

        $safe_cols = array_map(
            static function( $col ) {
                $col = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $col );
                return "`{$col}`";
            },
            $columns
        );
        $select_cols = implode( ', ', $safe_cols );

        $rows = $wpdb->get_results( "SELECT $select_cols FROM `{$this->table_name}` ORDER BY created_at DESC" );

        if ( empty( $rows ) ) {
            wp_die( esc_html__( 'Keine Daten.', 'default' ) );
        }

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=gehaltsbarometer_vollstaendig_' . gmdate('Y-m-d') . '.csv' );

        $output = fopen( 'php://output', 'w' );
        fwrite( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        fputcsv( $output, $columns, ';' );

        foreach ( $rows as $row_obj ) {
            $line = [];
            foreach ( $columns as $col ) {
                $val = maybe_unserialize( $row_obj->$col );
                if ( is_array( $val ) || is_object( $val ) ) {
                    $val = wp_json_encode( $val, JSON_UNESCAPED_UNICODE );
                }
                $line[] = (string) $val;
            }
            fputcsv( $output, $line, ';' );
        }
        fclose( $output );
        exit;
    }

    private function export_entries_csv(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'default' ) );
        }

        global $wpdb;
        $columns = $this->get_anonymized_columns();

        if ( empty( $columns ) ) {
            wp_die( esc_html__( 'Keine Daten.', 'default' ) );
        }

        $safe_cols = array_map(
            static function( $col ) {
                $col = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $col );
                return "`{$col}`";
            },
            $columns
        );
        $select_cols = implode( ', ', $safe_cols );

        $rows = $wpdb->get_results( "SELECT $select_cols FROM `{$this->table_name}` ORDER BY created_at DESC" );

        if ( empty( $rows ) ) {
            wp_die( esc_html__( 'Keine Daten.', 'default' ) );
        }

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=gehaltsbarometer_eintraege_' . gmdate('Y-m-d') . '.csv' );

        $output = fopen( 'php://output', 'w' );
        fwrite( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        fputcsv( $output, $columns, ';' );

        foreach ( $rows as $row_obj ) {
            $line = [];
            foreach ( $columns as $col ) {
                $val = maybe_unserialize( $row_obj->$col );
                if ( is_array( $val ) || is_object( $val ) ) {
                    $val = wp_json_encode( $val, JSON_UNESCAPED_UNICODE );
                }
                $line[] = (string) $val;
            }
            fputcsv( $output, $line, ';' );
        }
        fclose( $output );
        exit;
    }
}

/* ------------------------------------------------------------------------- */
/* ––– Plugin initialisieren –––                                            */
/* ------------------------------------------------------------------------- */
Gehaltsbarometer::get_instance();