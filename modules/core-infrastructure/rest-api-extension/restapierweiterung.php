<?php
/*
Plugin Name: DGPTM Restapierweiterung
Description: Erweiterung des DGPTM-Plugins um zusätzliche REST-API-Endpunkte: Benutzerrollen aktualisieren, Sites-Liste abrufen und Benutzer löschen (mit optionaler Beitragsumverteilung).
Version: 1.0
Author: Dein Name
Text Domain: zoho-flow
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Custom_Rest_API' ) ) {
    class Custom_Rest_API {

        /**
         * Registriert die REST-Endpunkte unter dem Namespace dgptm/v1.
         */
        public function register_rest_routes() {
            register_rest_route('dgptm/v1', '/user-sites', [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_user_sites' ],
                'permission_callback' => function () {
                    return current_user_can('manage_sites');
                },
            ]);

            register_rest_route('dgptm/v1', '/sites', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_sites_list' ],
                'permission_callback' => function () {
                    return current_user_can('manage_sites');
                },
            ]);

            register_rest_route('dgptm/v1', '/delete-user', [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_user' ],
                'permission_callback' => function () {
                    return current_user_can('manage_sites');
                },
            ]);
        }

        /**
         * Aktualisiert in allen Sites des Netzwerks die Rolle eines Benutzers.
         * Erwartet die Parameter:
         * - user_id (erforderlich)
         * - role (erforderlich)
         */
        public function update_user_sites( WP_REST_Request $request ) {
            $user_id = $request->get_param('user_id');
            $role    = $request->get_param('role');
            $sites   = get_sites();

            if ( ! $user_id || ! $role ) {
                return new WP_Error(
                    'missing_parameters',
                    __( 'user_id and role are required', 'zoho-flow' ),
                    [ 'status' => 400 ]
                );
            }

            foreach ( $sites as $site ) {
                switch_to_blog( $site->blog_id );
                $user = get_user_by('ID', $user_id);

                if ( $user ) {
                    add_user_to_blog( $site->blog_id, $user_id, $role );
                } else {
                    restore_current_blog();
                    return new WP_Error(
                        'user_not_found',
                        __( 'User not found on site', 'zoho-flow' ),
                        [ 'status' => 404 ]
                    );
                }
                restore_current_blog();
            }

            return rest_ensure_response( [ 'message' => 'User roles updated successfully' ] );
        }

        /**
         * Gibt eine Liste aller Sites im Netzwerk zurück.
         */
        public function get_sites_list() {
            $sites = get_sites();
            $site_list = array_map(function ($site) {
                return [
                    'id'     => $site->blog_id,
                    'domain' => $site->domain,
                    'path'   => $site->path,
                ];
            }, $sites);
            return rest_ensure_response( $site_list );
        }

        /**
         * Löscht einen Benutzer aus dem gesamten Netzwerk und weist optional dessen Beiträge einem Ersatzbenutzer zu.
         * Erwartete Parameter:
         * - user_id (erforderlich)
         * - reassign (optional)
         */
        public function delete_user( WP_REST_Request $request ) {
            try {
                $user_id = $request->get_param('user_id');
                if ( empty( $user_id ) ) {
                    return new WP_Error(
                        'missing_user_id',
                        'Es muss eine User-ID übergeben werden.',
                        [ 'status' => 400 ]
                    );
                }
                $user_id = absint( $user_id );
                $user    = get_userdata( $user_id );
                if ( ! $user ) {
                    return new WP_Error(
                        'invalid_user',
                        'Die angegebene User-ID existiert nicht.',
                        [ 'status' => 404 ]
                    );
                }
                if ( get_current_user_id() === $user_id ) {
                    return new WP_Error(
                        'cannot_delete_self',
                        'Du kannst dich nicht selbst löschen.',
                        [ 'status' => 403 ]
                    );
                }
                if ( is_super_admin( $user_id ) ) {
                    return new WP_Error(
                        'cannot_delete_super_admin',
                        'Super-Administratoren können nicht gelöscht werden.',
                        [ 'status' => 403 ]
                    );
                }
                $reassign = $request->get_param('reassign');
                if ( ! empty( $reassign ) ) {
                    $reassign = absint( $reassign );
                    $reassign_user = get_userdata( $reassign );
                    if ( ! $reassign_user ) {
                        return new WP_Error(
                            'invalid_reassign',
                            'Der angegebene Ersatzbenutzer existiert nicht.',
                            [ 'status' => 400 ]
                        );
                    }
                    if ( $reassign === $user_id ) {
                        return new WP_Error(
                            'invalid_reassign',
                            'Der Ersatzbenutzer muss ein anderer Benutzer sein als der zu löschende.',
                            [ 'status' => 400 ]
                        );
                    }

                    $sites = get_sites( [ 'fields' => 'ids' ] );
                    foreach ( $sites as $blog_id ) {
                        switch_to_blog( $blog_id );
                        $args = [
                            'author'         => $user_id,
                            'posts_per_page' => -1,
                            'post_type'      => 'any',
                            'fields'         => 'ids',
                        ];
                        $query = new WP_Query( $args );
                        if ( $query->have_posts() ) {
                            foreach ( $query->posts as $post_id ) {
                                $update = wp_update_post( [
                                    'ID'          => $post_id,
                                    'post_author' => $reassign,
                                ] );
                                if ( is_wp_error( $update ) ) {
                                    error_log( "Fehler beim Umverteilen von Post ID {$post_id}: " . $update->get_error_message() );
                                }
                            }
                        }
                        wp_reset_postdata();
                        restore_current_blog();
                    }
                }
                if ( ! function_exists( 'wpmu_delete_user' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                }
                wpmu_delete_user( $user_id );
                return rest_ensure_response( [
                    'success' => true,
                    'message' => 'Benutzer wurde erfolgreich gelöscht.' . ( ! empty( $reassign ) ? ' Beiträge wurden an Benutzer ' . $reassign . ' umverteilt.' : '' )
                ]);
            } catch ( Exception $e ) {
                error_log( 'delete_user Error: ' . $e->getMessage() );
                return new WP_Error(
                    'internal_server_error',
                    'Ein unerwarteter Fehler ist aufgetreten: ' . $e->getMessage(),
                    [ 'status' => 500 ]
                );
            }
        }
    }
}

if ( ! class_exists( 'DGPTM_Restapierweiterung' ) ) {
    class DGPTM_Restapierweiterung {
        private $rest_api;
        
        public function __construct() {
            add_action('init', [ $this, 'load_textdomain' ]);
            $this->rest_api = new Custom_Rest_API();
            add_action('rest_api_init', [ $this->rest_api, 'register_rest_routes' ]);
        }
        
        public function load_textdomain() {
            load_plugin_textdomain('zoho-flow', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }
    }
}

new DGPTM_Restapierweiterung();
