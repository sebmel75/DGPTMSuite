<?php
/*
Plugin Name: Stellenanzeigen Manager
Description: Ermöglicht das Erstellen, Bearbeiten und Löschen von Stellenanzeigen sowie deren Einbindung über Shortcodes (ohne AJAX).
Version: 2.0
Author: Sebastian Melzer
Text Domain: staz-manager
*/

// Verhindert direkten Zugriff
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper-Funktion fuer Stellenanzeigen Settings
 * Verwendet das zentrale DGPTM Settings-System mit Fallback auf alte Options
 */
if (!function_exists('dgptm_staz_get_setting')) {
    function dgptm_staz_get_setting($key, $default = '') {
        // Mapping von alten Option-Keys auf neue zentrale Keys
        $key_mapping = [
            'dgptm_staz_editor_page_id' => 'editor_page_id',
            'dgptm_staz_bearbeiten_page_id' => 'bearbeiten_page_id'
        ];

        // Neuen Key bestimmen
        $new_key = isset($key_mapping[$key]) ? $key_mapping[$key] : $key;

        // Zuerst im zentralen System suchen
        if (function_exists('dgptm_get_module_setting')) {
            $value = dgptm_get_module_setting('stellenanzeige', $new_key, null);
            if ($value !== null) {
                return $value;
            }
        }

        // Fallback auf alten Option-Key
        return get_option($key, $default);
    }
}

/**
 * ACF JSON Load Point (lädt Feldgruppen automatisch)
 */
add_filter('acf/settings/load_json', 'dgptm_staz_acf_json_load_point');
function dgptm_staz_acf_json_load_point( $paths ) {
    $paths[] = plugin_dir_path( __FILE__ ) . 'acf-json';
    return $paths;
}

/**
 * Hilfsfunktion: Prüft ob User Berechtigung hat Stellenanzeigen zu verwalten
 */
function dgptm_staz_user_can_manage() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $user_id = get_current_user_id();

    // Admin hat immer Zugriff
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }

    // Prüfe ACF User-Meta-Feld
    if ( function_exists( 'get_field' ) ) {
        $can_manage = get_field( 'stellenanzeigen_anlegen', 'user_' . $user_id );
        return (bool) $can_manage;
    }

    return false;
}

/**
 * 1) Custom Post Type "stellenanzeige" registrieren
 */
function dgptm_staz_register_post_type() {
    $labels = array(
        'name'               => 'Stellenanzeigen',
        'singular_name'      => 'Stellenanzeige',
        'menu_name'          => 'Stellenanzeigen',
        'name_admin_bar'     => 'Stellenanzeige',
        'add_new'            => 'Neue Stellenanzeige',
        'add_new_item'       => 'Neue Stellenanzeige hinzufügen',
        'edit_item'          => 'Stellenanzeige bearbeiten',
        'view_item'          => 'Stellenanzeige ansehen',
        'all_items'          => 'Alle Stellenanzeigen',
        'search_items'       => 'Stellenanzeigen suchen',
        'not_found'          => 'Keine Stellenanzeigen gefunden.',
        'not_found_in_trash' => 'Keine Stellenanzeigen im Papierkorb gefunden.',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => false,
        'show_in_rest'       => true,
        'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
        'menu_icon'          => 'dashicons-businessman',
        'capability_type'    => 'stellenanzeige',
        'capabilities'       => array(
            'edit_post'          => 'edit_stellenanzeige',
            'read_post'          => 'read_stellenanzeige',
            'delete_post'        => 'delete_stellenanzeige',
            'edit_posts'         => 'edit_stellenanzeigen',
            'edit_others_posts'  => 'edit_others_stellenanzeigen',
            'publish_posts'      => 'publish_stellenanzeigen',
            'read_private_posts' => 'read_private_stellenanzeigen',
        ),
        'map_meta_cap'       => true,
    );

    register_post_type( 'stellenanzeige', $args );
}
add_action( 'init', 'dgptm_staz_register_post_type' );

/**
 * 2) Einstellungen für dieses Plugin registrieren (Seite mit Shortcodes auswählen)
 */
function dgptm_staz_register_settings() {
    register_setting( 'dgptm_staz_options', 'dgptm_staz_editor_page_id' );
    register_setting( 'dgptm_staz_options', 'dgptm_staz_bearbeiten_page_id' );

    add_settings_section(
        'dgptm_staz_settings_section',
        'Stellenanzeigen Manager Einstellungen',
        'dgptm_staz_settings_section_cb',
        'dgptm_staz_settings'
    );

    add_settings_field(
        'dgptm_staz_editor_page_id',
        'Seite mit Shortcode [stellenanzeigen_editor]',
        'dgptm_staz_editor_page_id_cb',
        'dgptm_staz_settings',
        'dgptm_staz_settings_section'
    );

    add_settings_field(
        'dgptm_staz_bearbeiten_page_id',
        'Seite mit Shortcode [stellenanzeige_bearbeiten]',
        'dgptm_staz_bearbeiten_page_id_cb',
        'dgptm_staz_settings',
        'dgptm_staz_settings_section'
    );
}
add_action( 'admin_init', 'dgptm_staz_register_settings' );

/**
 * Callback für den Beschreibungstext in den Einstellungen
 */
function dgptm_staz_settings_section_cb() {
    echo '<p>Bitte wähle die Seiten aus, auf denen du die Shortcodes eingebunden hast.</p>';
}

/**
 * Felder für die Seitenauswahl
 */
function dgptm_staz_editor_page_id_cb() {
    $value = dgptm_staz_get_setting( 'dgptm_staz_editor_page_id', '' );
    wp_dropdown_pages( array(
        'name'             => 'dgptm_staz_editor_page_id',
        'selected'         => $value,
        'show_option_none' => '— Bitte auswählen —'
    ) );
}

function dgptm_staz_bearbeiten_page_id_cb() {
    $value = dgptm_staz_get_setting( 'dgptm_staz_bearbeiten_page_id', '' );
    wp_dropdown_pages( array(
        'name'             => 'dgptm_staz_bearbeiten_page_id',
        'selected'         => $value,
        'show_option_none' => '— Bitte auswählen —'
    ) );
}

/**
 * Options-Seite ins Admin-Menü einhängen
 * @deprecated Settings werden zentral ueber DGPTM Suite verwaltet
 */
function dgptm_staz_add_admin_menu() {
    // DGPTM Suite: Settings werden zentral ueber Modul-Einstellungen verwaltet
    // add_options_page(...) entfernt - Settings unter DGPTM Suite -> Modul-Einstellungen -> Stellenanzeigen
}
// DGPTM Suite: Deaktiviert - Settings werden zentral verwaltet
// add_action( 'admin_menu', 'dgptm_staz_add_admin_menu' );

/**
 * Ausgabe der Options-Seite
 */
function dgptm_staz_settings_page() {
    ?>
    <div class="wrap">
        <h1>Stellenanzeigen Manager Einstellungen</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'dgptm_staz_options' );
            do_settings_sections( 'dgptm_staz_settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Shortcode zur Anzeige der Stellenanzeigen
 */
function dgptm_staz_render_shortcode( $atts ) {
    ob_start();

    $today = date( 'Y-m-d' );

    $args = array(
        'post_type'      => 'stellenanzeige',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => array(
            array(
                'key'     => 'gultig_bis',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATE',
            ),
        ),
        'orderby'        => 'post_date',
        'order'          => 'DESC',
    );

    $stellenanzeigen = new WP_Query( $args );

    if ( $stellenanzeigen->have_posts() ) {
        echo '<div class="dgptm-staz-stellenanzeigen-list">';
        while ( $stellenanzeigen->have_posts() ) {
            $stellenanzeigen->the_post();
            $url             = get_field( 'link_zur_stellenanzeige' );
            $job_description = get_field( 'stellenbeschreibung' );
            $valid_until     = get_field( 'gultig_bis' );
            $arbeitgeber     = get_field( 'arbeitgeber' );
            $bild            = get_field( 'bild_zur_stellenanzeige' );
            $thumbnail       = '';
            if ( $bild ) {
                if ( is_array( $bild ) && isset( $bild['ID'] ) ) {
                    $thumbnail = wp_get_attachment_image( $bild['ID'], 'large' );
                } else {
                    $thumbnail = wp_get_attachment_image( $bild, 'large' );
                }
            }
            $publication_date = get_the_date( 'd.m.Y' );

            echo '<div class="dgptm-staz-stellenanzeige-item">';
            echo '<div class="dgptm-staz-content">';
            echo '<h3 class="dgptm-staz-title">' . get_the_title() . '</h3>';
            if ( $thumbnail ) {
                echo '<div class="dgptm-staz-thumbnail">' . $thumbnail . '</div>';
            }
            if ( ! empty( $job_description ) ) {
                echo '<div class="dgptm-staz-job-description">' . wp_kses_post( $job_description ) . '</div>';
            }
            if ( ! empty( $arbeitgeber ) ) {
                echo '<div class="dgptm-staz-arbeitgeber">Arbeitgeber: ' . esc_html( $arbeitgeber ) . '</div>';
            }
            if ( $url ) {
                echo '<a href="' . esc_url( $url ) . '" class="dgptm-staz-button" target="_blank" rel="noopener noreferrer">Jetzt bewerben</a>';
            }
            echo '<div class="dgptm-staz-footer">Veröffentlicht am ' . esc_html( $publication_date );
            if ( $valid_until ) {
                echo ' | Gültig bis: ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $valid_until ) ) );
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';

            if ( $stellenanzeigen->current_post + 1 < $stellenanzeigen->post_count ) {
                echo '<hr class="dgptm-staz-separator">';
            }
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<p>Keine Stellenanzeigen verfügbar.</p>';
    }

    return ob_get_clean();
}
add_shortcode( 'stellenanzeigen', 'dgptm_staz_render_shortcode' );
/**
 * 4) Shortcode [stellenanzeigen_editor]
 *    - Listet alle Stellenanzeigen in einer Tabelle auf
 *    - Bietet Formular zum Erstellen einer neuen Stellenanzeige
 *    - Leitet nach dem Erstellen direkt auf [stellenanzeige_bearbeiten] um
 */
function dgptm_staz_render_editor_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>Bitte melde dich an, um Stellenanzeigen zu verwalten.</p>';
    }

    if ( ! dgptm_staz_user_can_manage() ) {
        return '<p>Du hast keine Berechtigung, um Stellenanzeigen zu verwalten. Bitte kontaktiere einen Administrator.</p>';
    }

    // Beim Absenden einer neuen Stellenanzeige:
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['dgptm_staz_action'] ) && $_POST['dgptm_staz_action'] === 'create' ) {
        check_admin_referer( 'dgptm_staz_editor_action', 'dgptm_staz_editor_nonce' );
        $new_id = dgptm_staz_handle_create(); // Gibt die ID der neu erstellten Stellenanzeige zurück (oder 0 bei Fehler)

        if ( $new_id ) {
            // Nach dem Erstellen zur Bearbeitungsseite weiterleiten:
            $bearbeiten_page_id = dgptm_staz_get_setting( 'dgptm_staz_bearbeiten_page_id' );
            if ( $bearbeiten_page_id ) {
                $edit_url = add_query_arg(
                    array( 'staz_id' => $new_id ),
                    get_permalink( $bearbeiten_page_id )
                );
                wp_safe_redirect( $edit_url );
                exit;
            }
        }
    }

    // Ausgabe Start
    ob_start();
    ?>
    <div class="dgptm-staz-editor-container">
        <h2>Stellenanzeigen verwalten</h2>

        <!-- Formular: Neue Stellenanzeige -->
        <h3>Neue Stellenanzeige erstellen</h3>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'dgptm_staz_editor_action', 'dgptm_staz_editor_nonce' ); ?>
            <input type="hidden" name="dgptm_staz_action" value="create">

            <p>
                <label for="dgptm_staz_title">Titel:</label><br>
                <input type="text" id="dgptm_staz_title" name="dgptm_staz_title" value="" required style="width:100%;">
            </p>
            
            <p>
                <label for="dgptm_staz_arbeitgeber">Arbeitgeber:</label><br>
                <input type="text" id="dgptm_staz_arbeitgeber" name="arbeitgeber" value="" style="width:100%;">
            </p>

            <p>
                <label for="dgptm_staz_job_description">Stellenbeschreibung:</label><br>
                <?php
                wp_editor(
                    '', // kein Inhalt, da neu
                    'stellenbeschreibung_frontend',
                    array(
                        'textarea_name' => 'stellenbeschreibung',
                        'media_buttons' => false,
                        'textarea_rows' => 5,
                        'teeny'         => true,
                        'quicktags'     => true,
                    )
                );
                ?>
            </p>

            <p>
                <label for="dgptm_staz_url">Link zur Stellenanzeige:</label><br>
                <input type="url" id="dgptm_staz_url" name="link_zur_stellenanzeige" value="" required style="width:100%;">
            </p>

            <p>
                <label for="dgptm_staz_valid_until">Gültig bis:</label><br>
                <input type="date" id="dgptm_staz_valid_until" name="gultig_bis" value="" required style="width:100%;">
            </p>

            <p>
                <label for="dgptm_staz_publication_date">Veröffentlichungsdatum:</label><br>
                <input type="date" id="dgptm_staz_publication_date" name="dgptm_staz_publication_date" value="" required style="width:100%;">
            </p>

            <p>
                <label for="dgptm_staz_thumbnail">Bild zur Stellenanzeige hochladen:</label><br>
                <input type="file" id="dgptm_staz_thumbnail" name="bild_zur_stellenanzeige">
            </p>

            <p>
                <button type="submit">Stellenanzeige erstellen</button>
            </p>
        </form>

        <hr>

        <!-- Tabelle aller (aktuellen) Stellenanzeigen -->
        <h3>Alle Stellenanzeigen</h3>
        <?php
        // Tabelle ausgeben:
        echo dgptm_staz_render_table_of_ads();
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'stellenanzeigen_editor', 'dgptm_staz_render_editor_shortcode' );

/**
 * 5) Shortcode [stellenanzeige_bearbeiten]
 *    Zeigt das Bearbeitungsformular für eine Stellenanzeige (abhängig von GET-Parameter "staz_id")
 */
function dgptm_staz_render_bearbeiten_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>Bitte melde dich an, um Stellenanzeigen zu verwalten.</p>';
    }
    if ( ! dgptm_staz_user_can_manage() ) {
        return '<p>Du hast keine Berechtigung, um Stellenanzeigen zu verwalten. Bitte kontaktiere einen Administrator.</p>';
    }

    if ( ! isset( $_GET['staz_id'] ) ) {
        return '<p>Keine Stellenanzeige-ID übergeben.</p>';
    }

    $post_id = intval( $_GET['staz_id'] );
    $post    = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'stellenanzeige' ) {
        return '<p>Ungültige Stellenanzeige.</p>';
    }

    // Wurde das Formular gesendet?
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['dgptm_staz_action'] ) ) {
        check_admin_referer( 'dgptm_staz_editor_action', 'dgptm_staz_editor_nonce' );

        switch ( sanitize_text_field( $_POST['dgptm_staz_action'] ) ) {
            case 'edit':
                dgptm_staz_handle_edit( $post_id );
                // Nach dem Bearbeiten ggf. erneut laden, um Aktualisierung zu zeigen (kein Redirect, falls gewünscht)
                break;
            
            case 'delete':
                dgptm_staz_handle_delete( $post_id );
                // Nach dem Löschen könnte man weiterleiten auf die Editor-Seite
                // Hier als Beispiel:
                $editor_page_id = dgptm_staz_get_setting( 'dgptm_staz_editor_page_id' );
                if ( $editor_page_id ) {
                    wp_safe_redirect( get_permalink( $editor_page_id ) );
                    exit;
                }
                return '<p>Stellenanzeige gelöscht.</p>';

            case 'push_up':
                dgptm_staz_handle_push_up( $post_id );
                break;

            case 'toggle_status':
                dgptm_staz_handle_toggle_status( $post_id );
                break;
        }
    }

    // Felder laden
    $url             = get_field( 'link_zur_stellenanzeige', $post_id );
    $job_description = get_field( 'stellenbeschreibung', $post_id );
    $valid_until     = get_field( 'gultig_bis', $post_id );
    $arbeitgeber     = get_field( 'arbeitgeber', $post_id );
    $publication_date = substr( $post->post_date, 0, 10 ); // YYYY-MM-DD

    ob_start();
    ?>
    <h2>Stellenanzeige bearbeiten</h2>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'dgptm_staz_editor_action', 'dgptm_staz_editor_nonce' ); ?>
        <input type="hidden" name="dgptm_staz_action" value="edit">
        
        <p>
            <label for="dgptm_staz_title_edit">Titel:</label><br>
            <input type="text" id="dgptm_staz_title_edit" name="dgptm_staz_title" 
                   value="<?php echo esc_attr( $post->post_title ); ?>" required style="width:100%;">
        </p>

        <p>
            <label for="dgptm_staz_arbeitgeber_edit">Arbeitgeber:</label><br>
            <input type="text" id="dgptm_staz_arbeitgeber_edit" name="arbeitgeber" 
                   value="<?php echo esc_attr( $arbeitgeber ); ?>" style="width:100%;">
        </p>

        <p>
            <label for="dgptm_staz_job_description_edit">Stellenbeschreibung:</label><br>
            <?php
            wp_editor(
                $job_description,
                'stellenbeschreibung_frontend_edit',
                array(
                    'textarea_name' => 'stellenbeschreibung',
                    'media_buttons' => false,
                    'textarea_rows' => 5,
                    'teeny'         => true,
                    'quicktags'     => true,
                )
            );
            ?>
        </p>

        <p>
            <label for="dgptm_staz_url_edit">Link zur Stellenanzeige:</label><br>
            <input type="url" id="dgptm_staz_url_edit" name="link_zur_stellenanzeige" 
                   value="<?php echo esc_url( $url ); ?>" required style="width:100%;">
        </p>

        <p>
            <label for="dgptm_staz_valid_until_edit">Gültig bis:</label><br>
            <input type="date" id="dgptm_staz_valid_until_edit" name="gultig_bis" 
                   value="<?php echo esc_attr( $valid_until ); ?>" required style="width:100%;">
        </p>

        <p>
            <label for="dgptm_staz_publication_date_edit">Veröffentlichungsdatum:</label><br>
            <input type="date" id="dgptm_staz_publication_date_edit" name="dgptm_staz_publication_date" 
                   value="<?php echo esc_attr( $publication_date ); ?>" required style="width:100%;">
        </p>

        <p>
            <label for="dgptm_staz_thumbnail_edit">Neues Bild hochladen (optional):</label><br>
            <input type="file" id="dgptm_staz_thumbnail_edit" name="bild_zur_stellenanzeige">
        </p>

        <p>
            <button type="submit">Änderungen speichern</button>
        </p>
    </form>

    <hr>

    <!-- Löschen -->
    <form method="post" style="display:inline;">
        <?php wp_nonce_field( 'dgptm_staz_editor_action', 'dgptm_staz_editor_nonce' ); ?>
        <input type="hidden" name="dgptm_staz_action" value="delete">
        <button type="submit" style="color:red;">Stellenanzeige löschen</button>
    </form>

    <!-- Hochschieben -->
    <form method="post" style="display:inline; margin-left:20px;">
        <?php wp_nonce_field( 'dgptm_staz_editor_action', 'dgptm_staz_editor_nonce' ); ?>
        <input type="hidden" name="dgptm_staz_action" value="push_up">
        <button type="submit">Hochschieben</button>
    </form>

    <!-- Status umschalten -->
    <?php
    $is_published = ( $post->post_status === 'publish' );
    ?>
    <form method="post" style="display:inline; margin-left:20px;">
        <?php wp_nonce_field( 'dgptm_staz_editor_action', 'dgptm_staz_editor_nonce' ); ?>
        <input type="hidden" name="dgptm_staz_action" value="toggle_status">
        <button type="submit">
            <?php echo $is_published ? 'Als Entwurf setzen' : 'Veröffentlichen'; ?>
        </button>
    </form>
    <?php

    return ob_get_clean();
}
add_shortcode( 'stellenanzeige_bearbeiten', 'dgptm_staz_render_bearbeiten_shortcode' );

/* -------------------------------------------------------------
   HILFSFUNKTIONEN (Tabellen-Ausgabe, Create/Edit/Delete, Upload)
   ------------------------------------------------------------- */

/**
 * Tabelle mit allen aktiven Stellenanzeigen ohne AJAX.
 */
function dgptm_staz_render_table_of_ads() {
    // Im Editor ALLE Stellenanzeigen anzeigen (auch abgelaufene)
    $args = array(
        'post_type'      => 'stellenanzeige',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'future', 'draft' ),
        'meta_key'       => 'gultig_bis',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
    );

    $stellenanzeigen = new WP_Query( $args );

    ob_start();
    ?>
    <table style="width:100%; border-collapse:collapse; margin-top: 10px;">
        <thead>
            <tr>
                <th style="border:1px solid #ddd; padding:8px;">Bild</th>
                <th style="border:1px solid #ddd; padding:8px;">Titel</th>
                <th style="border:1px solid #ddd; padding:8px;">Beschreibung?</th>
                <th style="border:1px solid #ddd; padding:8px;">Gültig bis</th>
                <th style="border:1px solid #ddd; padding:8px;">Veröffentlicht</th>
                <th style="border:1px solid #ddd; padding:8px;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php
        if ( $stellenanzeigen->have_posts() ) :
            $today = date( 'Y-m-d' );
            while ( $stellenanzeigen->have_posts() ) :
                $stellenanzeigen->the_post();
                $job_description  = get_field( 'stellenbeschreibung' );
                $valid_until      = get_field( 'gultig_bis' );
                $bild             = get_field( 'bild_zur_stellenanzeige' );
                $publication_date = get_the_date( 'd.m.Y' );

                // Prüfe ob abgelaufen
                $is_expired = ( $valid_until && $valid_until < $today );
                $row_style = $is_expired ? ' style="opacity: 0.6; background: #fff3cd;"' : '';

                $thumbnail       = '';
                if ( $bild ) {
                    if ( is_array( $bild ) && isset( $bild['ID'] ) ) {
                        $thumbnail = wp_get_attachment_image( $bild['ID'], 'thumbnail' );
                    } else {
                        $thumbnail = wp_get_attachment_image( $bild, 'thumbnail' );
                    }
                }
                ?>
                <tr<?php echo $row_style; ?>>
                    <td style="border:1px solid #ddd; padding:8px; text-align:center;">
                        <?php echo $thumbnail ? $thumbnail : 'Kein Bild'; ?>
                    </td>
                    <td style="border:1px solid #ddd; padding:8px;">
                        <?php echo esc_html( get_the_title() ); ?>
                    </td>
                    <td style="border:1px solid #ddd; padding:8px; text-align:center;">
                        <?php echo ! empty( $job_description ) ? '<span>Ja</span>' : '<span>Nein</span>'; ?>
                    </td>
                    <td style="border:1px solid #ddd; padding:8px;">
                        <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $valid_until ) ) ); ?>
                        <?php if ( $is_expired ): ?>
                            <br><span style="color: #856404; font-weight: bold; font-size: 11px;">⚠️ ABGELAUFEN</span>
                        <?php endif; ?>
                    </td>
                    <td style="border:1px solid #ddd; padding:8px;">
                        <?php echo esc_html( $publication_date ); ?>
                    </td>
                    <td style="border:1px solid #ddd; padding:8px; text-align:center;">
                        <?php
                        // Link zur Bearbeitungsseite
                        $bearbeiten_page_id = dgptm_staz_get_setting( 'dgptm_staz_bearbeiten_page_id' );
                        if ( $bearbeiten_page_id ) {
                            $edit_url = add_query_arg(
                                array( 'staz_id' => get_the_ID() ),
                                get_permalink( $bearbeiten_page_id )
                            );
                            echo '<a href="' . esc_url( $edit_url ) . '">Bearbeiten</a>';
                        } else {
                            echo 'Keine Bearbeitungsseite definiert';
                        }
                        ?>
                    </td>
                </tr>
                <?php
            endwhile;
            wp_reset_postdata();
        else :
            ?>
            <tr>
                <td colspan="6" style="border:1px solid #ddd; padding:8px; text-align:center;">
                    Keine Stellenanzeigen gefunden.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

/* ------------ CREATE / EDIT / DELETE / PUSH UP / TOGGLE STATUS ---------------- */

/**
 * (A) Neue Stellenanzeige erstellen
 *     Gibt die neue Post-ID zurück oder 0 bei Fehler.
 */
function dgptm_staz_handle_create() {
    if ( ! isset( $_POST['dgptm_staz_title'], $_POST['link_zur_stellenanzeige'], $_POST['gultig_bis'], $_POST['dgptm_staz_publication_date'] ) ) {
        return 0;
    }

    $title            = sanitize_text_field( $_POST['dgptm_staz_title'] );
    $arbeitgeber      = isset( $_POST['arbeitgeber'] ) ? sanitize_text_field( $_POST['arbeitgeber'] ) : '';
    $job_description  = isset( $_POST['stellenbeschreibung'] ) ? wp_kses_post( $_POST['stellenbeschreibung'] ) : '';
    $url              = esc_url_raw( $_POST['link_zur_stellenanzeige'] );
    $valid_until      = sanitize_text_field( $_POST['gultig_bis'] );
    $publication_date = sanitize_text_field( $_POST['dgptm_staz_publication_date'] );

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valid_until ) ) {
        return 0;
    }
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $publication_date ) ) {
        return 0;
    }

    $new_post = array(
        'post_title'    => $title,
        'post_content'  => $job_description,
        'post_status'   => 'publish',
        'post_type'     => 'stellenanzeige',
        'post_date'     => $publication_date . ' 00:00:00',
        'post_date_gmt' => get_gmt_from_date( $publication_date . ' 00:00:00' ),
    );

    $post_id = wp_insert_post( $new_post );

    if ( $post_id ) {
        update_field( 'link_zur_stellenanzeige', $url, $post_id );
        update_field( 'stellenbeschreibung', $job_description, $post_id );
        update_field( 'gultig_bis', $valid_until, $post_id );
        update_field( 'arbeitgeber', $arbeitgeber, $post_id );

        if ( isset( $_FILES['bild_zur_stellenanzeige'] ) && ! empty( $_FILES['bild_zur_stellenanzeige']['name'] ) ) {
            $uploaded = dgptm_staz_handle_file_upload( $_FILES['bild_zur_stellenanzeige'], $post_id );
            if ( ! is_wp_error( $uploaded ) ) {
                update_field( 'bild_zur_stellenanzeige', $uploaded, $post_id );
            }
        }
    }

    return $post_id;
}

/**
 * (B) Vorhandene Stellenanzeige bearbeiten
 */
function dgptm_staz_handle_edit( $post_id ) {
    if ( ! isset( $_POST['dgptm_staz_title'], $_POST['gultig_bis'], $_POST['dgptm_staz_publication_date'] ) ) {
        echo '<p>Fehler: Bitte fülle alle erforderlichen Felder aus.</p>';
        return;
    }

    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'stellenanzeige' ) {
        echo '<p>Fehler: Ungültige Stellenanzeige.</p>';
        return;
    }

    $title            = sanitize_text_field( $_POST['dgptm_staz_title'] );
    $arbeitgeber      = isset( $_POST['arbeitgeber'] ) ? sanitize_text_field( $_POST['arbeitgeber'] ) : '';
    $job_description  = isset( $_POST['stellenbeschreibung'] ) ? wp_kses_post( $_POST['stellenbeschreibung'] ) : '';
    $valid_until      = sanitize_text_field( $_POST['gultig_bis'] );
    $publication_date = sanitize_text_field( $_POST['dgptm_staz_publication_date'] );
    $url              = isset( $_POST['link_zur_stellenanzeige'] ) ? esc_url_raw( $_POST['link_zur_stellenanzeige'] ) : '';

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valid_until ) ) {
        echo '<p>Fehler: ungültiges "Gültig bis"-Datum.</p>';
        return;
    }
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $publication_date ) ) {
        echo '<p>Fehler: ungültiges Veröffentlichungsdatum.</p>';
        return;
    }

    $updated_post = array(
        'ID'             => $post_id,
        'post_title'     => $title,
        'post_content'   => $job_description,
        'post_date'      => $publication_date . ' 00:00:00',
        'post_date_gmt'  => get_gmt_from_date( $publication_date . ' 00:00:00' ),
    );

    $result = wp_update_post( $updated_post, true );
    if ( is_wp_error( $result ) ) {
        echo '<p>Fehler beim Aktualisieren: ' . esc_html( $result->get_error_message() ) . '</p>';
        return;
    }

    update_field( 'gultig_bis', $valid_until, $post_id );
    update_field( 'link_zur_stellenanzeige', $url, $post_id );
    update_field( 'stellenbeschreibung', $job_description, $post_id );
    update_field( 'arbeitgeber', $arbeitgeber, $post_id );

    // Bild-Upload
    if ( isset( $_FILES['bild_zur_stellenanzeige'] ) && ! empty( $_FILES['bild_zur_stellenanzeige']['name'] ) ) {
        $uploaded = dgptm_staz_handle_file_upload( $_FILES['bild_zur_stellenanzeige'], $post_id );
        if ( ! is_wp_error( $uploaded ) ) {
            update_field( 'bild_zur_stellenanzeige', $uploaded, $post_id );
        } else {
            echo '<p>Fehler beim Bild-Upload: ' . esc_html( $uploaded->get_error_message() ) . '</p>';
        }
    }

    echo '<p style="color: green;">Stellenanzeige wurde erfolgreich aktualisiert.</p>';
}

/**
 * (C) Stellenanzeige löschen
 */
function dgptm_staz_handle_delete( $post_id ) {
    $post = get_post( $post_id );
    if ( $post && $post->post_type === 'stellenanzeige' ) {
        wp_delete_post( $post_id, true );
        echo '<p style="color: green;">Stellenanzeige erfolgreich gelöscht.</p>';
    } else {
        echo '<p>Fehler: Stellenanzeige nicht gefunden oder ungültig.</p>';
    }
}

/**
 * (D) Stellenanzeige "hochschieben" (Datum aktualisieren)
 */
function dgptm_staz_handle_push_up( $post_id ) {
    $post = get_post( $post_id );
    if ( $post && $post->post_type === 'stellenanzeige' ) {
        $args = array(
            'ID'            => $post_id,
            'post_date'     => current_time( 'mysql' ),
            'post_date_gmt' => get_gmt_from_date( current_time( 'mysql' ) ),
        );
        wp_update_post( $args );
        echo '<p style="color: green;">Stellenanzeige erfolgreich hochgeschoben.</p>';
    } else {
        echo '<p>Fehler: Stellenanzeige nicht gefunden oder ungültig.</p>';
    }
}

/**
 * (E) Status umschalten (publish <-> draft)
 */
function dgptm_staz_handle_toggle_status( $post_id ) {
    $post = get_post( $post_id );
    if ( $post && $post->post_type === 'stellenanzeige' ) {
        $new_status = ( $post->post_status === 'publish' ) ? 'draft' : 'publish';
        $args = array(
            'ID'          => $post_id,
            'post_status' => $new_status,
        );
        wp_update_post( $args );
        echo '<p style="color: green;">Status erfolgreich geändert ('.esc_html( $new_status ).').</p>';
    } else {
        echo '<p>Fehler: Stellenanzeige nicht gefunden oder ungültig.</p>';
    }
}

/**
 * (F) Upload-Handler für Bilder
 */
function dgptm_staz_handle_file_upload( $file, $post_id ) {
    $upload_dir = wp_upload_dir();
    if ( ! wp_is_writable( $upload_dir['path'] ) ) {
        return new WP_Error( 'upload_error', 'Upload-Verzeichnis ist nicht beschreibbar.' );
    }

    $filetype      = wp_check_filetype( basename( $file['name'] ), null );
    $allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
    if ( ! in_array( strtolower( $filetype['ext'] ), $allowed_types ) ) {
        return new WP_Error( 'invalid_file', 'Ungültiger Dateityp (nur JPG, JPEG, PNG, GIF, WEBP erlaubt).' );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload( $file, array( 'test_form' => false ) );

    if ( isset( $uploaded['error'] ) ) {
        return new WP_Error( 'upload_error', $uploaded['error'] );
    }

    // Attachment anlegen
    $attachment = array(
        'post_mime_type' => $uploaded['type'],
        'post_title'     => sanitize_file_name( $uploaded['file'] ),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $uploaded['file'], $post_id );

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
    wp_update_attachment_metadata( $attach_id, $attach_data );

    // Optional: als Beitragsbild setzen
    set_post_thumbnail( $post_id, $attach_id );

    return $attach_id;
}

/**
 * 6) Custom Post Type Capabilities für Admins
 *
 * HINWEIS: Die Berechtigung für normale Benutzer wird über das ACF-User-Meta-Feld
 * "stellenanzeigen_anlegen" gesteuert, nicht über WordPress Capabilities.
 */
function dgptm_staz_admin_caps() {
    // Nur Administratoren bekommen die Post Type Capabilities
    $admin_role = get_role( 'administrator' );
    if ( $admin_role ) {
        $admin_role->add_cap( 'edit_stellenanzeige' );
        $admin_role->add_cap( 'read_stellenanzeige' );
        $admin_role->add_cap( 'delete_stellenanzeige' );
        $admin_role->add_cap( 'edit_stellenanzeigen' );
        $admin_role->add_cap( 'edit_others_stellenanzeigen' );
        $admin_role->add_cap( 'publish_stellenanzeigen' );
        $admin_role->add_cap( 'read_private_stellenanzeigen' );
    }
}
// Wird beim Modulstart ausgeführt (nicht nur bei Aktivierung)
add_action( 'init', 'dgptm_staz_admin_caps', 11 );

/**
 * 7) Styles im Frontend laden
 */
function dgptm_staz_enqueue_styles() {
    wp_enqueue_style( 'dgptm-staz-styles', plugin_dir_url( __FILE__ ) . 'css/dgptm-staz-styles.css', array(), '2.0' );
}
add_action( 'wp_enqueue_scripts', 'dgptm_staz_enqueue_styles' );

/**
 * 8) Admin-Notice: Benutzer über ACF-Feld informieren
 */
function dgptm_staz_admin_notice() {
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'users' ) {
        ?>
        <div class="notice notice-info">
            <p><strong>Stellenanzeigen-Berechtigung:</strong> Aktivieren Sie das Feld "Stellenanzeigen anlegen" im Benutzerprofil, um Benutzern die Verwaltung von Stellenanzeigen zu erlauben.</p>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'dgptm_staz_admin_notice' );
