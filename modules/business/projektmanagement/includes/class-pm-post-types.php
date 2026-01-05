<?php
/**
 * Post Types Registration for Projektmanagement
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PM_Post_Types')) {

    class PM_Post_Types {

        /**
         * Register all custom post types
         */
        public static function register() {
            self::register_project_cpt();
            self::register_task_cpt();
            self::register_project_template_cpt();
            self::register_task_template_cpt();
        }

        /**
         * Register Project CPT
         */
        private static function register_project_cpt() {
            if (post_type_exists('dgptm_project')) {
                return;
            }

            register_post_type('dgptm_project', [
                'labels' => [
                    'name'               => 'Projekte',
                    'singular_name'      => 'Projekt',
                    'add_new'            => 'Neues Projekt',
                    'add_new_item'       => 'Neues Projekt hinzufuegen',
                    'edit_item'          => 'Projekt bearbeiten',
                    'new_item'           => 'Neues Projekt',
                    'view_item'          => 'Projekt ansehen',
                    'search_items'       => 'Projekte suchen',
                    'not_found'          => 'Keine Projekte gefunden',
                    'not_found_in_trash' => 'Keine Projekte im Papierkorb',
                ],
                'public'             => false,
                'show_ui'            => false,
                'show_in_menu'       => false,
                'show_in_rest'       => false,
                'supports'           => ['title', 'editor', 'author'],
                'capability_type'    => 'post',
                'has_archive'        => false,
                'rewrite'            => false,
            ]);
        }

        /**
         * Register Task CPT
         */
        private static function register_task_cpt() {
            if (post_type_exists('dgptm_task')) {
                return;
            }

            register_post_type('dgptm_task', [
                'labels' => [
                    'name'               => 'Aufgaben',
                    'singular_name'      => 'Aufgabe',
                    'add_new'            => 'Neue Aufgabe',
                    'add_new_item'       => 'Neue Aufgabe hinzufuegen',
                    'edit_item'          => 'Aufgabe bearbeiten',
                    'new_item'           => 'Neue Aufgabe',
                    'view_item'          => 'Aufgabe ansehen',
                    'search_items'       => 'Aufgaben suchen',
                    'not_found'          => 'Keine Aufgaben gefunden',
                    'not_found_in_trash' => 'Keine Aufgaben im Papierkorb',
                ],
                'public'             => false,
                'show_ui'            => false,
                'show_in_menu'       => false,
                'show_in_rest'       => false,
                'supports'           => ['title', 'editor', 'author', 'comments'],
                'capability_type'    => 'post',
                'has_archive'        => false,
                'rewrite'            => false,
            ]);
        }

        /**
         * Register Project Template CPT
         */
        private static function register_project_template_cpt() {
            if (post_type_exists('dgptm_proj_template')) {
                return;
            }

            register_post_type('dgptm_proj_template', [
                'labels' => [
                    'name'               => 'Projekt-Vorlagen',
                    'singular_name'      => 'Projekt-Vorlage',
                    'add_new'            => 'Neue Vorlage',
                    'add_new_item'       => 'Neue Vorlage hinzufuegen',
                    'edit_item'          => 'Vorlage bearbeiten',
                    'new_item'           => 'Neue Vorlage',
                    'view_item'          => 'Vorlage ansehen',
                    'search_items'       => 'Vorlagen suchen',
                    'not_found'          => 'Keine Vorlagen gefunden',
                    'not_found_in_trash' => 'Keine Vorlagen im Papierkorb',
                ],
                'public'             => false,
                'show_ui'            => false,
                'show_in_menu'       => false,
                'show_in_rest'       => false,
                'supports'           => ['title', 'editor', 'author'],
                'capability_type'    => 'post',
                'has_archive'        => false,
                'rewrite'            => false,
            ]);
        }

        /**
         * Register Task Template CPT
         */
        private static function register_task_template_cpt() {
            if (post_type_exists('dgptm_task_template')) {
                return;
            }

            register_post_type('dgptm_task_template', [
                'labels' => [
                    'name'               => 'Aufgaben-Vorlagen',
                    'singular_name'      => 'Aufgaben-Vorlage',
                    'add_new'            => 'Neue Aufgaben-Vorlage',
                    'add_new_item'       => 'Neue Aufgaben-Vorlage hinzufuegen',
                    'edit_item'          => 'Aufgaben-Vorlage bearbeiten',
                    'new_item'           => 'Neue Aufgaben-Vorlage',
                    'view_item'          => 'Aufgaben-Vorlage ansehen',
                    'search_items'       => 'Aufgaben-Vorlagen suchen',
                    'not_found'          => 'Keine Aufgaben-Vorlagen gefunden',
                    'not_found_in_trash' => 'Keine Aufgaben-Vorlagen im Papierkorb',
                ],
                'public'             => false,
                'show_ui'            => false,
                'show_in_menu'       => false,
                'show_in_rest'       => false,
                'supports'           => ['title', 'editor'],
                'capability_type'    => 'post',
                'has_archive'        => false,
                'rewrite'            => false,
            ]);
        }
    }
}
