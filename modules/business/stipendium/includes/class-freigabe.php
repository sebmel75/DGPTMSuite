<?php
/**
 * Digitale Freigabe-Komponente fuer das Stipendium-Konzeptdokument.
 *
 * Stellt einen Shortcode bereit, der das Konzeptdokument mit
 * Kommentierungsfunktion je Abschnitt und digitaler Freigabe anzeigt.
 * Sichtbar nur fuer Benutzer mit der WordPress-Rolle "mitglied".
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Freigabe {

    private $plugin_path;
    private $plugin_url;

    /** wp_options Keys */
    const OPT_APPROVALS = 'dgptm_stipendium_freigabe_approvals';
    const OPT_COMMENTS  = 'dgptm_stipendium_freigabe_comments';

    /** Nonce Action */
    const NONCE_ACTION = 'dgptm_stipendium_freigabe_nonce';

    public function __construct($plugin_path, $plugin_url) {
        $this->plugin_path = $plugin_path;
        $this->plugin_url  = $plugin_url;

        add_shortcode('dgptm_stipendium_freigabe', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        // AJAX-Endpoints (nur fuer eingeloggte User)
        add_action('wp_ajax_dgptm_freigabe_comment',    [$this, 'ajax_add_comment']);
        add_action('wp_ajax_dgptm_freigabe_delete_comment', [$this, 'ajax_delete_comment']);
        add_action('wp_ajax_dgptm_freigabe_approve',    [$this, 'ajax_approve']);
        add_action('wp_ajax_dgptm_freigabe_revoke',     [$this, 'ajax_revoke']);

        // Admin-Endpoints (nur manage_options)
        add_action('wp_ajax_dgptm_freigabe_export',     [$this, 'ajax_export_comments']);
        add_action('wp_ajax_dgptm_freigabe_mark_read',  [$this, 'ajax_mark_comments_read']);
    }

    /* ──────────────────────────────────────────────
     * Assets
     * ────────────────────────────────────────────── */

    public function register_assets() {
        wp_register_style(
            'dgptm-freigabe',
            $this->plugin_url . 'assets/css/freigabe.css',
            [],
            '0.1.0'
        );
        wp_register_script(
            'dgptm-freigabe',
            $this->plugin_url . 'assets/js/freigabe.js',
            ['jquery'],
            '0.1.0',
            true
        );
    }

    /* ──────────────────────────────────────────────
     * Shortcode
     * ────────────────────────────────────────────── */

    public function render_shortcode($atts) {
        // Rollencheck: nur "mitglied"
        if (!is_user_logged_in()) {
            return '<p class="dgptm-freigabe-hinweis">Bitte melden Sie sich an, um dieses Dokument einzusehen.</p>';
        }

        $user = wp_get_current_user();
        if (!in_array('mitglied', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            return '<p class="dgptm-freigabe-hinweis">Dieses Dokument ist nur fuer Mitglieder zugaenglich.</p>';
        }

        wp_enqueue_style('dgptm-freigabe');
        wp_enqueue_script('dgptm-freigabe');
        wp_localize_script('dgptm-freigabe', 'dgptmFreigabe', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
            'userId'  => $user->ID,
        ]);

        $approvals = $this->get_approvals();
        $comments  = $this->get_comments();
        $user_approved = $this->has_user_approved($user->ID);

        ob_start();
        include $this->plugin_path . 'templates/freigabe-dokument.php';
        return ob_get_clean();
    }

    /* ──────────────────────────────────────────────
     * AJAX: Kommentar hinzufuegen
     * ────────────────────────────────────────────── */

    public function ajax_add_comment() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!$this->user_can_interact()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $section = sanitize_text_field(wp_unslash($_POST['section'] ?? ''));
        $text    = sanitize_textarea_field(wp_unslash($_POST['comment'] ?? ''));

        if (empty($section) || empty($text)) {
            wp_send_json_error('Abschnitt und Kommentar sind Pflichtfelder.');
        }

        $user = wp_get_current_user();
        $comments = $this->get_comments();

        $comment = [
            'id'        => wp_generate_uuid4(),
            'section'   => $section,
            'user_id'   => $user->ID,
            'user_name' => $user->display_name,
            'text'      => $text,
            'timestamp' => current_time('mysql'),
        ];

        $comments[] = $comment;
        update_option(self::OPT_COMMENTS, $comments, false);

        wp_send_json_success([
            'comment' => $comment,
            'html'    => $this->render_comment_html($comment, $user->ID),
        ]);
    }

    /* ──────────────────────────────────────────────
     * AJAX: Kommentar loeschen
     * ────────────────────────────────────────────── */

    public function ajax_delete_comment() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!$this->user_can_interact()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $comment_id = sanitize_text_field(wp_unslash($_POST['comment_id'] ?? ''));
        $user = wp_get_current_user();
        $comments = $this->get_comments();

        $found = false;
        $comments = array_values(array_filter($comments, function ($c) use ($comment_id, $user, &$found) {
            if ($c['id'] === $comment_id) {
                // Nur eigene Kommentare loeschen (oder Admin)
                if ((int) $c['user_id'] === $user->ID || current_user_can('manage_options')) {
                    $found = true;
                    return false;
                }
            }
            return true;
        }));

        if (!$found) {
            wp_send_json_error('Kommentar nicht gefunden oder keine Berechtigung.');
        }

        update_option(self::OPT_COMMENTS, $comments, false);
        wp_send_json_success();
    }

    /* ──────────────────────────────────────────────
     * AJAX: Freigabe erteilen
     * ────────────────────────────────────────────── */

    public function ajax_approve() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!$this->user_can_interact()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $user = wp_get_current_user();
        $approvals = $this->get_approvals();

        // Doppelte Freigabe verhindern
        foreach ($approvals as $a) {
            if ((int) $a['user_id'] === $user->ID) {
                wp_send_json_error('Sie haben bereits freigegeben.');
            }
        }

        $approval = [
            'user_id'   => $user->ID,
            'user_name' => $user->display_name,
            'timestamp' => current_time('mysql'),
        ];

        $approvals[] = $approval;
        update_option(self::OPT_APPROVALS, $approvals, false);

        wp_send_json_success([
            'approval'   => $approval,
            'total'      => count($approvals),
        ]);
    }

    /* ──────────────────────────────────────────────
     * AJAX: Freigabe zurueckziehen
     * ────────────────────────────────────────────── */

    public function ajax_revoke() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!$this->user_can_interact()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $user = wp_get_current_user();
        $approvals = $this->get_approvals();

        $approvals = array_values(array_filter($approvals, function ($a) use ($user) {
            return (int) $a['user_id'] !== $user->ID;
        }));

        update_option(self::OPT_APPROVALS, $approvals, false);

        wp_send_json_success([
            'total' => count($approvals),
        ]);
    }

    /* ──────────────────────────────────────────────
     * Admin: Kommentare exportieren (JSON)
     * ────────────────────────────────────────────── */

    public function ajax_export_comments() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nur Administratoren.', 403);
        }

        wp_send_json_success([
            'comments'  => $this->get_comments(),
            'approvals' => $this->get_approvals(),
        ]);
    }

    /* ──────────────────────────────────────────────
     * Admin: Kommentare als "eingelesen" markieren
     * ────────────────────────────────────────────── */

    public function ajax_mark_comments_read() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Nur Administratoren.', 403);
        }

        $comment_ids = json_decode(wp_unslash($_POST['comment_ids'] ?? '[]'), true);
        if (!is_array($comment_ids)) {
            wp_send_json_error('Ungueltige IDs.');
        }

        $comments = $this->get_comments();
        $marked = 0;

        foreach ($comments as &$c) {
            if (in_array($c['id'], $comment_ids)) {
                $c['status'] = 'eingelesen';
                $c['read_at'] = current_time('mysql');
                $marked++;
            }
        }
        unset($c);

        update_option(self::OPT_COMMENTS, $comments, false);

        wp_send_json_success(['marked' => $marked]);
    }

    /* ──────────────────────────────────────────────
     * Helfer
     * ────────────────────────────────────────────── */

    private function user_can_interact() {
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        return in_array('mitglied', (array) $user->roles)
            || in_array('administrator', (array) $user->roles);
    }

    private function get_approvals() {
        return get_option(self::OPT_APPROVALS, []);
    }

    private function get_comments() {
        return get_option(self::OPT_COMMENTS, []);
    }

    private function has_user_approved($user_id) {
        foreach ($this->get_approvals() as $a) {
            if ((int) $a['user_id'] === (int) $user_id) {
                return true;
            }
        }
        return false;
    }

    private function get_section_comments($section_id, $comments) {
        return array_filter($comments, function ($c) use ($section_id) {
            return $c['section'] === $section_id;
        });
    }

    /**
     * Erzeugt HTML fuer einen einzelnen Kommentar.
     */
    public function render_comment_html($comment, $current_user_id) {
        $can_delete = ((int) $comment['user_id'] === (int) $current_user_id)
                      || current_user_can('manage_options');
        $ts = date_i18n('d.m.Y, H:i', strtotime($comment['timestamp']));

        $html  = '<div class="dgptm-freigabe-comment" data-comment-id="' . esc_attr($comment['id']) . '">';
        $html .= '  <div class="dgptm-freigabe-comment-meta">';
        $html .= '    <strong>' . esc_html($comment['user_name']) . '</strong>';
        $html .= '    <span class="dgptm-freigabe-comment-date">' . esc_html($ts) . '</span>';
        if ($can_delete) {
            $html .= '    <button type="button" class="dgptm-freigabe-comment-delete" data-id="' . esc_attr($comment['id']) . '" title="Kommentar loeschen">&times;</button>';
        }
        $html .= '  </div>';
        $html .= '  <div class="dgptm-freigabe-comment-text">' . nl2br(esc_html($comment['text'])) . '</div>';
        $html .= '</div>';

        return $html;
    }
}
