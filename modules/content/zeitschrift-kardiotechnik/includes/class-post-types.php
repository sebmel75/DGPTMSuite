<?php
/**
 * Custom Post Type Registration for Zeitschrift Kardiotechnik
 *
 * Registriert die CPTs zeitschkardiotechnik und publikation,
 * die bisher von JetEngine verwaltet wurden.
 *
 * @package Zeitschrift_Kardiotechnik
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZK_Post_Types')) {

    class ZK_Post_Types {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            // CPTs auf init registrieren - wichtig: frühe Priorität
            add_action('init', [$this, 'register_post_types'], 5);

            // Taxonomien registrieren
            add_action('init', [$this, 'register_taxonomies'], 5);

            // Flush Rewrite Rules nur bei Bedarf
            add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);
        }

        /**
         * Registriert die Custom Post Types
         */
        public function register_post_types() {
            // CPT: Zeitschrift Kardiotechnik
            $this->register_zeitschrift_cpt();

            // CPT: Publikation
            $this->register_publikation_cpt();
        }

        /**
         * Registriert den CPT zeitschkardiotechnik
         */
        private function register_zeitschrift_cpt() {
            // Prüfen ob bereits von JetEngine registriert
            if (post_type_exists(ZK_POST_TYPE)) {
                return;
            }

            $labels = [
                'name'                  => 'Zeitschriften',
                'singular_name'         => 'Zeitschrift',
                'menu_name'             => 'Kardiotechnik',
                'name_admin_bar'        => 'Zeitschrift',
                'add_new'               => 'Neu erstellen',
                'add_new_item'          => 'Neue Zeitschrift erstellen',
                'new_item'              => 'Neue Zeitschrift',
                'edit_item'             => 'Zeitschrift bearbeiten',
                'view_item'             => 'Zeitschrift ansehen',
                'all_items'             => 'Alle Zeitschriften',
                'search_items'          => 'Zeitschriften durchsuchen',
                'parent_item_colon'     => 'Übergeordnete Zeitschrift:',
                'not_found'             => 'Keine Zeitschriften gefunden.',
                'not_found_in_trash'    => 'Keine Zeitschriften im Papierkorb.',
                'featured_image'        => 'Titelseite',
                'set_featured_image'    => 'Titelseite festlegen',
                'remove_featured_image' => 'Titelseite entfernen',
                'use_featured_image'    => 'Als Titelseite verwenden',
            ];

            $args = [
                'labels'             => $labels,
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'query_var'          => true,
                'rewrite'            => ['slug' => 'kardiotechnik', 'with_front' => false],
                'capability_type'    => 'post',
                'has_archive'        => true,
                'hierarchical'       => false,
                'menu_position'      => 25,
                'menu_icon'          => 'dashicons-book-alt',
                'supports'           => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions'],
                'show_in_rest'       => true,
                'rest_base'          => 'zeitschriften',
            ];

            register_post_type(ZK_POST_TYPE, $args);
        }

        /**
         * Registriert den CPT publikation
         */
        private function register_publikation_cpt() {
            // Prüfen ob bereits von JetEngine registriert
            if (post_type_exists(ZK_PUBLIKATION_TYPE)) {
                return;
            }

            $labels = [
                'name'                  => 'Publikationen',
                'singular_name'         => 'Publikation',
                'menu_name'             => 'Publikationen',
                'name_admin_bar'        => 'Publikation',
                'add_new'               => 'Neu erstellen',
                'add_new_item'          => 'Neue Publikation erstellen',
                'new_item'              => 'Neue Publikation',
                'edit_item'             => 'Publikation bearbeiten',
                'view_item'             => 'Publikation ansehen',
                'all_items'             => 'Alle Publikationen',
                'search_items'          => 'Publikationen durchsuchen',
                'parent_item_colon'     => 'Übergeordnete Publikation:',
                'not_found'             => 'Keine Publikationen gefunden.',
                'not_found_in_trash'    => 'Keine Publikationen im Papierkorb.',
                'featured_image'        => 'Beitragsbild',
                'set_featured_image'    => 'Beitragsbild festlegen',
                'remove_featured_image' => 'Beitragsbild entfernen',
                'use_featured_image'    => 'Als Beitragsbild verwenden',
            ];

            $args = [
                'labels'             => $labels,
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'query_var'          => true,
                'rewrite'            => ['slug' => 'publikation', 'with_front' => false],
                'capability_type'    => 'post',
                'has_archive'        => true,
                'hierarchical'       => false,
                'menu_position'      => 26,
                'menu_icon'          => 'dashicons-media-document',
                'supports'           => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions', 'excerpt'],
                'show_in_rest'       => true,
                'rest_base'          => 'publikationen',
                'taxonomies'         => ['pub-kategorien'],
            ];

            register_post_type(ZK_PUBLIKATION_TYPE, $args);
        }

        /**
         * Registriert die Taxonomien
         */
        public function register_taxonomies() {
            // Taxonomie: Publikations-Kategorien
            $this->register_pub_kategorien();

            // Taxonomie: Zeitschriften-Kategorien
            $this->register_zeitschriften_kategorien();
        }

        /**
         * Registriert die Taxonomie pub-kategorien
         */
        private function register_pub_kategorien() {
            // Prüfen ob bereits von JetEngine registriert
            if (taxonomy_exists('pub-kategorien')) {
                return;
            }

            $labels = [
                'name'                       => 'Publikationskategorien',
                'singular_name'              => 'Publikationskategorie',
                'menu_name'                  => 'Kategorien',
                'all_items'                  => 'Alle Kategorien',
                'parent_item'                => 'Übergeordnete Kategorie',
                'parent_item_colon'          => 'Übergeordnete Kategorie:',
                'new_item_name'              => 'Neue Kategorie',
                'add_new_item'               => 'Neue Kategorie hinzufügen',
                'edit_item'                  => 'Kategorie bearbeiten',
                'update_item'                => 'Kategorie aktualisieren',
                'view_item'                  => 'Kategorie ansehen',
                'separate_items_with_commas' => 'Kategorien mit Komma trennen',
                'add_or_remove_items'        => 'Kategorien hinzufügen oder entfernen',
                'choose_from_most_used'      => 'Aus häufig verwendeten wählen',
                'popular_items'              => 'Häufig verwendet',
                'search_items'               => 'Kategorien durchsuchen',
                'not_found'                  => 'Keine Kategorien gefunden',
                'no_terms'                   => 'Keine Kategorien',
            ];

            $args = [
                'labels'            => $labels,
                'hierarchical'      => true,
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_nav_menus' => true,
                'show_tagcloud'     => false,
                'show_in_rest'      => true,
                'rewrite'           => ['slug' => 'publikation-kategorie', 'with_front' => false],
            ];

            register_taxonomy('pub-kategorien', [ZK_PUBLIKATION_TYPE], $args);
        }

        /**
         * Registriert die Taxonomie zeitschriften-kategorien
         */
        private function register_zeitschriften_kategorien() {
            // Prüfen ob bereits von JetEngine registriert
            if (taxonomy_exists('zeitschriften-kategorien')) {
                return;
            }

            $labels = [
                'name'                       => 'Zeitschriftenkategorien',
                'singular_name'              => 'Zeitschriftenkategorie',
                'menu_name'                  => 'Kategorien',
                'all_items'                  => 'Alle Kategorien',
                'parent_item'                => 'Übergeordnete Kategorie',
                'parent_item_colon'          => 'Übergeordnete Kategorie:',
                'new_item_name'              => 'Neue Kategorie',
                'add_new_item'               => 'Neue Kategorie hinzufügen',
                'edit_item'                  => 'Kategorie bearbeiten',
                'update_item'                => 'Kategorie aktualisieren',
                'view_item'                  => 'Kategorie ansehen',
                'separate_items_with_commas' => 'Kategorien mit Komma trennen',
                'add_or_remove_items'        => 'Kategorien hinzufügen oder entfernen',
                'choose_from_most_used'      => 'Aus häufig verwendeten wählen',
                'popular_items'              => 'Häufig verwendet',
                'search_items'               => 'Kategorien durchsuchen',
                'not_found'                  => 'Keine Kategorien gefunden',
                'no_terms'                   => 'Keine Kategorien',
            ];

            $args = [
                'labels'            => $labels,
                'hierarchical'      => true,
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_nav_menus' => true,
                'show_tagcloud'     => false,
                'show_in_rest'      => true,
                'rewrite'           => ['slug' => 'zeitschrift-kategorie', 'with_front' => false],
            ];

            register_taxonomy('zeitschriften-kategorien', [ZK_POST_TYPE], $args);
        }

        /**
         * Flush Rewrite Rules wenn CPTs neu registriert wurden
         */
        public function maybe_flush_rewrite_rules() {
            $flush_key = 'zk_cpt_flush_done_170';

            if (get_option($flush_key) !== 'yes') {
                flush_rewrite_rules();
                update_option($flush_key, 'yes');
            }
        }

        /**
         * Statische Methode zum Prüfen, ob CPTs bereits registriert sind
         */
        public static function are_cpts_registered() {
            return post_type_exists(ZK_POST_TYPE) && post_type_exists(ZK_PUBLIKATION_TYPE);
        }
    }
}
