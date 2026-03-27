<?php
/**
 * Plugin Name: DGPTM - Forum
 * Description: Diskussionsforum mit Arbeitsgemeinschaften, Themen und verschachtelten Antworten
 * Version: 2.2.0
 * Author: Sebastian Melzer
 */
if (!defined('ABSPATH')) exit;

define('DGPTM_FORUM_VERSION', '2.2.0');
define('DGPTM_FORUM_PATH', plugin_dir_path(__FILE__));
define('DGPTM_FORUM_URL', plugin_dir_url(__FILE__));

if ( ! function_exists( 'dgptm_forum_fullname' ) ) {
    /**
     * Gibt "Vorname Nachname" zurück. Fallback: display_name → user_login.
     * @param WP_User|object|null $user  User-Objekt oder null
     * @return string
     */
    function dgptm_forum_fullname( $user ) {
        if ( ! $user ) return 'Unbekannt';
        $first = $user->first_name ?? '';
        $last  = $user->last_name ?? '';
        $full  = trim( $first . ' ' . $last );
        return $full ?: ( $user->display_name ?: ( $user->user_login ?? 'Unbekannt' ) );
    }
}

if (!class_exists('DGPTM_Forum')) {

    class DGPTM_Forum {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->load_dependencies();
            $this->init_hooks();
        }

        private function load_dependencies() {
            require_once DGPTM_FORUM_PATH . 'includes/class-forum-installer.php';
            require_once DGPTM_FORUM_PATH . 'includes/class-forum-permissions.php';
            require_once DGPTM_FORUM_PATH . 'includes/class-forum-ag-manager.php';

            if (file_exists(DGPTM_FORUM_PATH . 'includes/class-forum-notifications.php')) {
                require_once DGPTM_FORUM_PATH . 'includes/class-forum-notifications.php';
            }
            if (file_exists(DGPTM_FORUM_PATH . 'includes/class-forum-renderer.php')) {
                require_once DGPTM_FORUM_PATH . 'includes/class-forum-renderer.php';
            }
            if (file_exists(DGPTM_FORUM_PATH . 'includes/class-forum-admin-renderer.php')) {
                require_once DGPTM_FORUM_PATH . 'includes/class-forum-admin-renderer.php';
            }
            if (file_exists(DGPTM_FORUM_PATH . 'includes/class-forum-ajax.php')) {
                require_once DGPTM_FORUM_PATH . 'includes/class-forum-ajax.php';
            }
        }

        private function init_hooks() {
            add_action('init', [$this, 'ensure_tables'], 1);
            add_action('init', [$this, 'register_shortcodes']);

            // Forum view AJAX actions
            $forum_actions = [
                'dgptm_forum_load_view',
                'dgptm_forum_load_thread',
                'dgptm_forum_create_thread',
                'dgptm_forum_create_reply',
                'dgptm_forum_edit_post',
                'dgptm_forum_delete_post',
                'dgptm_forum_upload_file',
                'dgptm_forum_subscribe',
                'dgptm_forum_unsubscribe',
            ];

            // Admin AJAX actions
            $admin_actions = [
                'dgptm_forum_admin_save_ag',
                'dgptm_forum_admin_delete_ag',
                'dgptm_forum_admin_add_member',
                'dgptm_forum_admin_remove_member',
                'dgptm_forum_admin_save_topic',
                'dgptm_forum_admin_delete_topic',
                'dgptm_forum_admin_grant_access',
                'dgptm_forum_admin_revoke_access',
                'dgptm_forum_admin_search_users',
                'dgptm_forum_admin_toggle_pin',
                'dgptm_forum_admin_close_thread',
                'dgptm_forum_admin_set_forum_admin',
                'dgptm_forum_admin_save_mail_templates',
                'dgptm_forum_admin_load_tab',
                'dgptm_forum_admin_bulk_subscribe_ag',
                'dgptm_forum_admin_unblacklist_user',
                'dgptm_forum_toggle_blacklist',
            ];

            foreach (array_merge($forum_actions, $admin_actions) as $action) {
                add_action('wp_ajax_' . $action, [$this, 'handle_ajax']);
            }

            add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);

            // Unsubscribe link handler (Feature 4: Blacklist)
            add_action('init', function() {
                if (isset($_GET['dgptm_forum_unsubscribe']) && isset($_GET['user']) && isset($_GET['token'])) {
                    $uid   = absint($_GET['user']);
                    $token = sanitize_text_field($_GET['token']);
                    if (wp_hash($uid . 'forum_unsub') === $token) {
                        update_user_meta($uid, 'dgptm_forum_blacklisted', 1);
                        wp_die(
                            'Sie erhalten keine Forum-Benachrichtigungen mehr. Diese Einstellung kann im Mitgliederbereich r&uuml;ckg&auml;ngig gemacht werden.',
                            'Forum-Benachrichtigungen deaktiviert'
                        );
                    }
                }
            });
        }

        public function ensure_tables() {
            $db_version = get_option('dgptm_forum_db_version', '');
            if ($db_version !== DGPTM_FORUM_VERSION) {
                DGPTM_Forum_Installer::install();
                update_option('dgptm_forum_db_version', DGPTM_FORUM_VERSION);
            }
        }

        public function register_shortcodes() {
            add_shortcode('dgptm-forum', [$this, 'shortcode_forum']);
            add_shortcode('dgptm-forum-admin', [$this, 'shortcode_forum_admin']);
            add_shortcode('is-forum-admin', [$this, 'shortcode_is_forum_admin']);
        }

        public function shortcode_forum($atts = []) {
            if (!is_user_logged_in()) {
                return '<p>Bitte anmelden.</p>';
            }

            $this->enqueue_assets();
            $ajax_url     = admin_url('admin-ajax.php');
            $nonce        = wp_create_nonce('dgptm_forum');
            $is_admin     = DGPTM_Forum_Permissions::is_forum_admin() ? 1 : 0;
            $deep_thread  = isset($_GET['thread']) ? absint($_GET['thread']) : 0;

            ob_start();
            ?>
            <div class="dgptm-forum-wrap" data-deep-thread="<?php echo $deep_thread; ?>">
                <div class="dgptm-forum-breadcrumb"></div>
                <div class="dgptm-forum-content"><p>Forum wird geladen…</p></div>
            </div>
            <script>
            (function(){
                window.dgptmForum = {
                    ajaxUrl: <?php echo wp_json_encode($ajax_url); ?>,
                    nonce: <?php echo wp_json_encode($nonce); ?>,
                    isAdmin: <?php echo $is_admin; ?>
                };
                var $w = jQuery('.dgptm-forum-wrap');
                if (!$w.length) return;
                var deepThread = parseInt($w.data('deep-thread') || 0);
                if (deepThread > 0) {
                    jQuery.post(dgptmForum.ajaxUrl, {
                        action: 'dgptm_forum_load_view',
                        nonce: dgptmForum.nonce,
                        view: 'thread',
                        id: deepThread
                    }).done(function(r) {
                        if (r && r.success) {
                            $w.find('.dgptm-forum-content').html(r.data.html);
                        } else {
                            $w.find('.dgptm-forum-content').html('<p style="color:red">' + ((r&&r.data&&r.data.message)||'Fehler') + '</p>');
                        }
                    }).fail(function() {
                        $w.find('.dgptm-forum-content').html('<p style="color:red">Verbindungsfehler</p>');
                    });
                } else {
                    jQuery.post(dgptmForum.ajaxUrl, {
                        action: 'dgptm_forum_load_view',
                        nonce: dgptmForum.nonce,
                        view: 'ags',
                        id: 0
                    }).done(function(r) {
                        if (r && r.success) {
                            $w.find('.dgptm-forum-content').html(r.data.html);
                            if (r.data.breadcrumb) {
                                var bc = '', crumbs = r.data.breadcrumb;
                                for (var i = 0; i < crumbs.length; i++) {
                                    if (i > 0) bc += '<span class="sep">&rsaquo;</span>';
                                    if (crumbs[i].link) bc += '<a href="#" data-view="' + crumbs[i].view + '" data-id="' + (crumbs[i].id||0) + '">' + crumbs[i].label + '</a>';
                                    else bc += '<span>' + crumbs[i].label + '</span>';
                                }
                                $w.find('.dgptm-forum-breadcrumb').html(bc);
                            }
                        } else {
                            $w.find('.dgptm-forum-content').html('<p style="color:red">' + ((r&&r.data&&r.data.message)||'Fehler') + '</p>');
                        }
                    }).fail(function() {
                        $w.find('.dgptm-forum-content').html('<p style="color:red">Verbindungsfehler</p>');
                    });
                }
            })();
            </script>
            <?php
            return ob_get_clean();
        }

        public function shortcode_forum_admin($atts = []) {
            if (!is_user_logged_in()) {
                return '';
            }

            if (!DGPTM_Forum_Permissions::is_forum_admin()) {
                return '<p>Keine Berechtigung.</p>';
            }

            $this->enqueue_assets();
            $ajax_url = admin_url('admin-ajax.php');
            $nonce    = wp_create_nonce('dgptm_forum');

            ob_start();
            ?>
            <div class="dgptm-forum-admin-wrap">
                <nav class="dgptm-forum-admin-tabs">
                    <a href="#" class="active" data-tab="ags">Hauptgruppen</a>
                    <a href="#" data-tab="admins">Forum-Admins</a>
                    <a href="#" data-tab="mails">E-Mail-Vorlagen</a>
                </nav>
                <div class="dgptm-forum-admin-content"><p>Wird geladen…</p></div>
            </div>
            <script>
            (function(){
                window.dgptmForum = {
                    ajaxUrl: <?php echo wp_json_encode($ajax_url); ?>,
                    nonce: <?php echo wp_json_encode($nonce); ?>,
                    isAdmin: 1
                };
                function loadAdminTab(tab) {
                    var $c = jQuery('.dgptm-forum-admin-content');
                    if (!$c.length) return;
                    $c.html('<p>Wird geladen\u2026</p>');
                    jQuery.post(dgptmForum.ajaxUrl, {
                        action: 'dgptm_forum_admin_load_tab',
                        nonce: dgptmForum.nonce,
                        tab: tab
                    }).done(function(r) {
                        if (r && r.success) $c.html(r.data.html);
                        else $c.html('<p style="color:red">' + ((r && r.data && r.data.message) || 'Fehler') + '</p>');
                    }).fail(function() {
                        $c.html('<p style="color:red">Verbindungsfehler</p>');
                    });
                }
                var $ = jQuery;

                // Tab-Klick
                $(document).off('click.forumadmin').on('click.forumadmin', '.dgptm-forum-admin-tabs a', function(e) {
                    e.preventDefault();
                    $('.dgptm-forum-admin-tabs a').removeClass('active');
                    $(this).addClass('active');
                    loadAdminTab($(this).data('tab'));
                });

                // Toggle eingeklappte Formulare
                $(document).off('click.forumtoggle').on('click.forumtoggle', '.dgptm-forum-admin-toggle', function(e) {
                    e.preventDefault();
                    var target = $(this).data('target');
                    $('#' + target).slideToggle(200);
                });

                // Hauptgruppe erstellen/bearbeiten
                $(document).off('submit.forumag').on('submit.forumag', '.dgptm-forum-admin-ag-form', function(e) {
                    e.preventDefault();
                    var $f = $(this), $btn = $f.find('button[type="submit"]').prop('disabled', true);
                    $.ajax({
                        url: dgptmForum.ajaxUrl,
                        type: 'POST',
                        data: $f.serialize() + '&action=dgptm_forum_admin_save_ag&nonce=' + dgptmForum.nonce,
                        dataType: 'json'
                    }).done(function(r) {
                        if (r && r.success) { loadAdminTab('ags'); }
                        else { alert((r && r.data && r.data.message) ? r.data.message : 'Fehler'); $btn.prop('disabled',false); }
                    }).fail(function(xhr) {
                        alert('Fehler: ' + (xhr.responseText || 'Verbindungsfehler').substring(0, 300));
                        $btn.prop('disabled',false);
                    });
                });

                // Hauptgruppe löschen
                $(document).off('click.forumagdel').on('click.forumagdel', '.dgptm-forum-admin-delete-ag', function(e) {
                    e.preventDefault();
                    if (!confirm('Hauptgruppe wirklich löschen?')) return;
                    $.post(dgptmForum.ajaxUrl, { action:'dgptm_forum_admin_delete_ag', nonce:dgptmForum.nonce, ag_id:$(this).data('ag-id') })
                    .done(function(r) { if (r&&r.success) loadAdminTab('ags'); else alert((r&&r.data&&r.data.message)||'Fehler'); });
                });

                // Thema erstellen/bearbeiten
                $(document).off('submit.forumtopic').on('submit.forumtopic', '.dgptm-forum-admin-topic-form', function(e) {
                    e.preventDefault();
                    var $f = $(this), $btn = $f.find('button[type="submit"]').prop('disabled', true);
                    $.ajax({
                        url: dgptmForum.ajaxUrl,
                        type: 'POST',
                        data: $f.serialize() + '&action=dgptm_forum_admin_save_topic&nonce=' + dgptmForum.nonce,
                        dataType: 'json'
                    }).done(function(r) {
                        if (r && r.success) { loadAdminTab('topics'); }
                        else { alert((r && r.data && r.data.message) ? r.data.message : 'Fehler'); $btn.prop('disabled',false); }
                    }).fail(function(xhr) {
                        alert('Fehler: ' + (xhr.responseText || 'Verbindungsfehler').substring(0, 300));
                        $btn.prop('disabled',false);
                    });
                });

                // User-Suche (Debounced)
                var searchTimer;
                $(document).off('input.forumsearch').on('input.forumsearch', '.dgptm-forum-user-search', function() {
                    var $input = $(this), term = $input.val();
                    var $results = $input.closest('.dgptm-forum-user-search-wrap').find('.dgptm-forum-user-results');
                    clearTimeout(searchTimer);
                    if (term.length < 2) { $results.hide(); return; }
                    searchTimer = setTimeout(function() {
                        $.post(dgptmForum.ajaxUrl, { action:'dgptm_forum_admin_search_users', nonce:dgptmForum.nonce, term:term })
                        .done(function(r) {
                            if (r.success && r.data.users && r.data.users.length) {
                                var html = '';
                                r.data.users.forEach(function(u) {
                                    html += '<div class="user-item" data-user-id="'+u.id+'">'+u.name+' ('+u.email+')</div>';
                                });
                                $results.html(html).show();
                            } else { $results.html('<div class="user-item">Keine Ergebnisse</div>').show(); }
                        });
                    }, 300);
                });

                // User aus Suchergebnis auswählen
                $(document).off('click.forumusersel').on('click.forumusersel', '.dgptm-forum-user-results .user-item', function() {
                    var userId = $(this).data('user-id');
                    if (!userId) return;
                    var $wrap = $(this).closest('.dgptm-forum-user-search-wrap');
                    var ctx = $wrap.data('context'), targetId = $wrap.data('target-id');
                    if (ctx === 'set-moderator') {
                        // Moderator in das hidden-Feld des Edit-Formulars schreiben
                        $wrap.find('input[name="moderator_id"]').val(userId);
                        $wrap.find('.dgptm-forum-user-search').val($(this).text().split('(')[0].trim());
                        $wrap.find('.dgptm-forum-user-results').hide();
                        return;
                    } else if (ctx === 'ag-member') {
                        $.post(dgptmForum.ajaxUrl, { action:'dgptm_forum_admin_add_member', nonce:dgptmForum.nonce, ag_id:targetId, user_id:userId })
                        .done(function(r) { if (r.success) loadAdminTab('ags'); });
                    } else if (ctx === 'forum-admin') {
                        $.post(dgptmForum.ajaxUrl, { action:'dgptm_forum_admin_set_forum_admin', nonce:dgptmForum.nonce, user_id:userId, is_admin:1 })
                        .done(function(r) { if (r.success) loadAdminTab('admins'); });
                    } else if (ctx === 'topic-access') {
                        $.post(dgptmForum.ajaxUrl, { action:'dgptm_forum_admin_grant_access', nonce:dgptmForum.nonce, topic_id:targetId, user_id:userId })
                        .done(function(r) { if (r.success) loadAdminTab('topics'); });
                    }
                    $wrap.find('.dgptm-forum-user-results').hide();
                    $wrap.find('.dgptm-forum-user-search').val('');
                });

                // Mitglied entfernen
                $(document).off('click.forummemrem').on('click.forummemrem', '.dgptm-forum-admin-remove-member', function(e) {
                    e.preventDefault();
                    $.post(dgptmForum.ajaxUrl, { action:'dgptm_forum_admin_remove_member', nonce:dgptmForum.nonce, ag_id:$(this).data('ag-id'), user_id:$(this).data('user-id') })
                    .done(function(r) { if (r.success) loadAdminTab('ags'); });
                });

                // Forum-Admin entfernen
                $(document).off('click.forumadmrem').on('click.forumadmrem', '.dgptm-forum-admin-remove-admin', function(e) {
                    e.preventDefault();
                    $.post(dgptmForum.ajaxUrl, { action:'dgptm_forum_admin_set_forum_admin', nonce:dgptmForum.nonce, user_id:$(this).data('user-id'), is_admin:0 })
                    .done(function(r) { if (r.success) loadAdminTab('admins'); });
                });

                // Mail-Vorlagen speichern
                $(document).off('submit.forummail').on('submit.forummail', '.dgptm-forum-admin-mail-form', function(e) {
                    e.preventDefault();
                    var $f = $(this), $btn = $f.find('button[type="submit"]').prop('disabled', true);
                    $.ajax({
                        url: dgptmForum.ajaxUrl, type: 'POST', dataType: 'json',
                        data: $f.serialize() + '&action=dgptm_forum_admin_save_mail_templates&nonce=' + dgptmForum.nonce
                    }).done(function(r) {
                        if (r && r.success) { $btn.text('Gespeichert!'); setTimeout(function(){ $btn.text('Vorlagen speichern').prop('disabled',false); }, 1500); }
                        else { alert((r&&r.data&&r.data.message)||'Fehler'); $btn.prop('disabled',false); }
                    }).fail(function(xhr) { alert('Fehler: '+(xhr.responseText||'').substring(0,200)); $btn.prop('disabled',false); });
                });

                // Bulk subscribe all AG members (Feature 2)
                $(document).off('click.forumbulksub').on('click.forumbulksub', '.dgptm-forum-admin-bulk-subscribe', function(e) {
                    e.preventDefault();
                    var $btn = $(this).prop('disabled', true);
                    var agId = $btn.data('ag-id');
                    $.post(dgptmForum.ajaxUrl, {
                        action: 'dgptm_forum_admin_bulk_subscribe_ag',
                        nonce: dgptmForum.nonce,
                        ag_id: agId
                    }).done(function(r) {
                        if (r && r.success) {
                            $btn.text(r.data.count + ' Mitglieder abonniert!');
                            setTimeout(function(){ $btn.text('Alle Mitglieder abonnieren').prop('disabled', false); }, 2000);
                        } else {
                            alert((r && r.data && r.data.message) || 'Fehler');
                            $btn.prop('disabled', false);
                        }
                    }).fail(function() { alert('Verbindungsfehler'); $btn.prop('disabled', false); });
                });

                // Unblacklist user (Feature 4)
                $(document).off('click.forumunbl').on('click.forumunbl', '.dgptm-forum-admin-unblacklist', function(e) {
                    e.preventDefault();
                    var userId = $(this).data('user-id');
                    $.post(dgptmForum.ajaxUrl, {
                        action: 'dgptm_forum_admin_unblacklist_user',
                        nonce: dgptmForum.nonce,
                        user_id: userId
                    }).done(function(r) {
                        if (r && r.success) loadAdminTab('admins');
                        else alert((r && r.data && r.data.message) || 'Fehler');
                    });
                });

                // Initial load
                loadAdminTab('ags');
            })();
            </script>
            <?php
            return ob_get_clean();
        }

        public function shortcode_is_forum_admin($atts = []) {
            return DGPTM_Forum_Permissions::is_forum_admin() ? '1' : '0';
        }

        public function handle_ajax() {
            if (class_exists('DGPTM_Forum_Ajax')) {
                DGPTM_Forum_Ajax::get_instance()->dispatch();
            } else {
                wp_send_json_error('AJAX-Handler nicht verfügbar.');
            }
        }

        public function enqueue_assets() {
            wp_enqueue_style(
                'dgptm-forum',
                DGPTM_FORUM_URL . 'assets/css/forum.css',
                [],
                DGPTM_FORUM_VERSION
            );

            wp_enqueue_script(
                'dgptm-forum',
                DGPTM_FORUM_URL . 'assets/js/forum.js',
                ['jquery'],
                DGPTM_FORUM_VERSION,
                true
            );

            wp_localize_script('dgptm-forum', 'dgptmForum', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('dgptm_forum'),
                'isAdmin' => DGPTM_Forum_Permissions::is_forum_admin() ? 1 : 0,
            ]);
        }

        /**
         * Registriert Forum-Tabs im Mitglieder-Dashboard (einmalig).
         */
        public function ensure_dashboard_tabs() {
            if ( get_option( 'dgptm_forum_tabs_registered' ) ) return;

            $tabs = get_option( 'dgptm_dash_tabs_v3', [] );
            if ( ! is_array( $tabs ) ) return;

            $has_forum = false;
            $has_admin = false;
            foreach ( $tabs as $t ) {
                if ( ( $t['id'] ?? '' ) === 'forum' ) $has_forum = true;
                if ( ( $t['id'] ?? '' ) === 'forum-admin' ) $has_admin = true;
            }

            if ( ! $has_forum ) {
                $tabs[] = [
                    'id'         => 'forum',
                    'label'      => 'Forum',
                    'parent'     => '',
                    'active'     => true,
                    'order'      => 50,
                    'permission' => 'always',
                    'content'    => '[dgptm-forum]',
                ];
            }

            if ( ! $has_admin ) {
                $tabs[] = [
                    'id'         => 'forum-admin',
                    'label'      => 'Forum-Verwaltung',
                    'parent'     => '',
                    'active'     => true,
                    'order'      => 51,
                    'permission' => 'sc:is-forum-admin',
                    'content'    => '[dgptm-forum-admin]',
                ];
            }

            update_option( 'dgptm_dash_tabs_v3', $tabs, false );
            update_option( 'dgptm_forum_tabs_registered', 1 );
        }

        public function maybe_enqueue_assets() {
            global $post;
            if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dgptm_dashboard')) {
                $this->enqueue_assets();
            }
        }
    }
}

if (!isset($GLOBALS['dgptm_forum_initialized'])) {
    $GLOBALS['dgptm_forum_initialized'] = true;
    DGPTM_Forum::get_instance();
}

// Shortcode sofort registrieren (nicht auf init warten) — Dashboard-Permission braucht ihn früh
if ( ! shortcode_exists( 'is-forum-admin' ) ) {
    add_shortcode( 'is-forum-admin', function() {
        if ( ! is_user_logged_in() ) return '0';
        if ( current_user_can( 'manage_options' ) ) return '1';
        return (string) get_user_meta( get_current_user_id(), 'dgptm_forum_admin', true ) === '1' ? '1' : '0';
    });
}
