<?php
/**
 * Custom Post Type Registration for Herzzentrum
 *
 * Registriert den CPT herzzentrum, der bisher von JetEngine verwaltet wurde.
 * ACF-Felder werden weiterhin von ACF Pro verwaltet.
 *
 * @package DGPTM_Herzzentren
 * @since 4.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registriert den CPT herzzentrum
 */
function dgptm_hz_register_post_type() {
    // Prüfen ob bereits von JetEngine registriert
    if (post_type_exists('herzzentrum')) {
        return;
    }

    $labels = [
        'name'                  => 'Herzzentren',
        'singular_name'         => 'Herzzentrum',
        'menu_name'             => 'Herzzentren',
        'name_admin_bar'        => 'Herzzentrum',
        'add_new'               => 'Neu erstellen',
        'add_new_item'          => 'Neues Herzzentrum erstellen',
        'new_item'              => 'Neues Herzzentrum',
        'edit_item'             => 'Herzzentrum bearbeiten',
        'view_item'             => 'Herzzentrum ansehen',
        'all_items'             => 'Alle Herzzentren',
        'search_items'          => 'Herzzentren durchsuchen',
        'parent_item_colon'     => 'Übergeordnetes Herzzentrum:',
        'not_found'             => 'Keine Herzzentren gefunden.',
        'not_found_in_trash'    => 'Keine Herzzentren im Papierkorb.',
        'featured_image'        => 'Beitragsbild',
        'set_featured_image'    => 'Beitragsbild festlegen',
        'remove_featured_image' => 'Beitragsbild entfernen',
        'use_featured_image'    => 'Als Beitragsbild verwenden',
        'archives'              => 'Herzzentren-Archiv',
        'insert_into_item'      => 'In Herzzentrum einfügen',
        'uploaded_to_this_item' => 'Zu diesem Herzzentrum hochgeladen',
        'filter_items_list'     => 'Herzzentren-Liste filtern',
        'items_list_navigation' => 'Herzzentren-Navigation',
        'items_list'            => 'Herzzentren-Liste',
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => ['slug' => 'herzzentrum', 'with_front' => false],
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 27,
        'menu_icon'          => 'dashicons-heart',
        'supports'           => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions', 'excerpt'],
        'show_in_rest'       => true,
        'rest_base'          => 'herzzentren',
    ];

    register_post_type('herzzentrum', $args);
}
add_action('init', 'dgptm_hz_register_post_type', 5);

/**
 * Flush Rewrite Rules bei Aktivierung
 */
function dgptm_hz_flush_rewrite_rules() {
    $flush_key = 'dgptm_hz_cpt_flush_done_410';

    if (get_option($flush_key) !== 'yes') {
        dgptm_hz_register_post_type();
        flush_rewrite_rules();
        update_option($flush_key, 'yes');
    }
}
add_action('admin_init', 'dgptm_hz_flush_rewrite_rules');
