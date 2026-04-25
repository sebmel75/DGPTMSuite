<?php
/**
 * Entscheidungsvorlage fuer das Workshop-Buchung-Modul.
 *
 * Stellt zwei Shortcodes bereit:
 *   [dgptm_workshop_entscheidungsvorlage]        — Review + Kommentare + Freigabe
 *   [dgptm_workshop_entscheidungsvorlage_export] — Admin-only Export
 *
 * Muster analog DGPTM_Stipendium_Freigabe.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Workshop_Entscheidungsvorlage {

    private $plugin_path;
    private $plugin_url;

    const OPT_APPROVALS     = 'dgptm_wsb_evl_approvals';
    const OPT_COMMENTS      = 'dgptm_wsb_evl_comments';
    const OPT_ROW_APPROVALS = 'dgptm_wsb_evl_row_approvals';
    const NONCE_ACTION      = 'dgptm_wsb_evl_nonce';

    public function __construct($plugin_path, $plugin_url) {
        $this->plugin_path = $plugin_path;
        $this->plugin_url  = $plugin_url;

        add_shortcode('dgptm_workshop_entscheidungsvorlage',        [$this, 'render_shortcode']);
        add_shortcode('dgptm_workshop_entscheidungsvorlage_export', [$this, 'render_export_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        add_action('wp_ajax_dgptm_wsb_evl_comment',        [$this, 'ajax_add_comment']);
        add_action('wp_ajax_dgptm_wsb_evl_delete_comment', [$this, 'ajax_delete_comment']);
        add_action('wp_ajax_dgptm_wsb_evl_approve',        [$this, 'ajax_approve']);
        add_action('wp_ajax_dgptm_wsb_evl_revoke',         [$this, 'ajax_revoke']);
        add_action('wp_ajax_dgptm_wsb_evl_mark_read',      [$this, 'ajax_mark_comments_read']);
        add_action('wp_ajax_dgptm_wsb_evl_export',         [$this, 'ajax_export_comments']);
        add_action('wp_ajax_dgptm_wsb_evl_toggle_row',     [$this, 'ajax_toggle_row_approval']);
    }

    public function register_assets() {
        wp_register_style(
            'dgptm-wsb-evl',
            $this->plugin_url . 'assets/css/entscheidungsvorlage.css',
            [],
            '0.1.0'
        );
        wp_register_script(
            'dgptm-wsb-evl',
            $this->plugin_url . 'assets/js/entscheidungsvorlage.js',
            ['jquery'],
            '0.1.0',
            true
        );
    }

    /**
     * Abschnitts-Katalog — identisch zum Markdown-Dokument.
     */
    public function get_sections() {
        return [
            'section-ziel'              => '1. Worum geht es?',
            'section-ausgangslage'      => '2. Was haben wir heute?',
            'section-entscheidungen'    => '3. Fragestellungen zur Entscheidung',
            'section-architektur'       => '4. Wie ist die Loesung aufgebaut?',
            'section-datenfluss'        => '5. So laeuft eine Buchung ab',
            'section-kompatibilitaet'   => '6. Zusammenspiel mit bestehenden Funktionen',
            'section-crm-erweiterungen' => '7. Anpassungen im Zoho CRM',
            'section-abhaengigkeiten'   => '8. Externe Dienste',
            'section-out-of-scope'      => '9. Was ist NICHT enthalten?',
            'section-offene-punkte'     => '10. Offene Fragen fuer dich',
            'section-zertifikate'       => '11. Teilnahmezertifikate',
        ];
    }

    /* ──────────────────────────────────────────────
     * Haupt-Shortcode
     * ────────────────────────────────────────────── */

    public function render_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="dgptm-wsb-evl-hinweis">Bitte melde dich an, um dieses Dokument einzusehen.</p>';
        }

        $user = wp_get_current_user();
        if (!$this->user_can_interact()) {
            return '<p class="dgptm-wsb-evl-hinweis">Dieses Dokument ist nur fuer Mitglieder zugaenglich.</p>';
        }

        wp_enqueue_style('dgptm-wsb-evl');
        wp_enqueue_script('dgptm-wsb-evl');
        wp_localize_script('dgptm-wsb-evl', 'dgptmWsbEvl', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
            'userId'  => $user->ID,
        ]);

        $approvals      = $this->get_approvals();
        $comments       = $this->get_comments();
        $row_approvals  = $this->get_row_approvals();
        $user_approved  = $this->has_user_approved($user->ID);
        $sections       = $this->get_sections();

        ob_start();
        include $this->plugin_path . 'templates/entscheidungsvorlage-dokument.php';
        return ob_get_clean();
    }

    /* ──────────────────────────────────────────────
     * Export-Shortcode (Admin-only, Klartext)
     * ────────────────────────────────────────────── */

    public function render_export_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '';
        }

        $comments  = $this->get_comments();
        $approvals = $this->get_approvals();
        $sections  = $this->get_sections();

        ob_start();
        $export_id = 'dgptm-wsb-evl-export-' . wp_rand();
        ?>
        <div style="margin:16px 0;">
            <button type="button"
                    onclick="(function(){var t=document.getElementById('<?php echo $export_id; ?>');var r=document.createRange();r.selectNodeContents(t);var s=window.getSelection();s.removeAllRanges();s.addRange(r);document.execCommand('copy');s.removeAllRanges();this.textContent='Kopiert!';var b=this;setTimeout(function(){b.textContent='Alle Kommentare kopieren';},2000);}).call(this)"
                    style="background:#003366;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                Alle Kommentare kopieren
            </button>
        </div>
        <div id="<?php echo $export_id; ?>"
             style="font-family:monospace;font-size:13px;background:#f5f5f5;padding:20px;border-radius:8px;white-space:pre-wrap;"><?php

        echo esc_html('=== FREIGABEN (' . count($approvals) . ') ===') . "\n";
        foreach ($approvals as $a) {
            echo '  ' . esc_html($a['user_name']) . ' — ' . esc_html($a['timestamp']) . "\n";
        }

        echo "\n" . esc_html('=== KOMMENTARE (' . count($comments) . ') ===') . "\n";
        foreach ($sections as $sid => $label) {
            $sc = array_filter($comments, function ($c) use ($sid) { return $c['section'] === $sid; });
            if (empty($sc)) continue;
            echo "\n--- " . esc_html($label) . " ---\n";
            foreach ($sc as $c) {
                $status = !empty($c['status']) ? ' [' . $c['status'] . ']' : '';
                echo '  [' . esc_html($c['timestamp']) . '] ' . esc_html($c['user_name']) . $status . ":\n";
                echo '  ' . esc_html($c['text']) . "\n\n";
            }
        }

        ?></div>
        <?php
        return ob_get_clean();
    }

    /* ──────────────────────────────────────────────
     * AJAX — Kommentar hinzufuegen
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

        $this->notify_participants($user, $comment, 'Workshop-Buchung Entscheidungsvorlage');

        wp_send_json_success([
            'comment' => $comment,
            'html'    => $this->render_comment_html($comment, $user->ID),
        ]);
    }

    /* ──────────────────────────────────────────────
     * AJAX — Kommentar loeschen
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
     * AJAX — Freigabe erteilen
     * ────────────────────────────────────────────── */

    public function ajax_approve() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!$this->user_can_interact()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $user = wp_get_current_user();
        $approvals = $this->get_approvals();

        foreach ($approvals as $a) {
            if ((int) $a['user_id'] === $user->ID) {
                wp_send_json_error('Du hast bereits freigegeben.');
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
            'approval' => $approval,
            'total'    => count($approvals),
        ]);
    }

    /* ──────────────────────────────────────────────
     * AJAX — Freigabe zurueckziehen
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

        wp_send_json_success(['total' => count($approvals)]);
    }

    /* ──────────────────────────────────────────────
     * AJAX — Kommentare als eingearbeitet markieren (Admin)
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
            if (in_array($c['id'], $comment_ids, true)) {
                $c['status']  = 'eingearbeitet';
                $c['read_at'] = current_time('mysql');
                $marked++;
            }
        }
        unset($c);

        update_option(self::OPT_COMMENTS, $comments, false);
        wp_send_json_success(['marked' => $marked]);
    }

    /* ──────────────────────────────────────────────
     * AJAX — JSON-Export (Admin)
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
     * E-Mail — Beteiligte benachrichtigen
     * ────────────────────────────────────────────── */

    private function notify_participants($author, $comment, $dokument_name) {
        $participant_ids = [];
        foreach ($this->get_comments() as $c) {
            $participant_ids[(int) $c['user_id']] = true;
        }
        foreach ($this->get_approvals() as $a) {
            $participant_ids[(int) $a['user_id']] = true;
        }

        unset($participant_ids[$author->ID]);
        if (empty($participant_ids)) return;

        $recipients = [];
        foreach (array_keys($participant_ids) as $uid) {
            $u = get_userdata($uid);
            if ($u && !empty($u->user_email)) {
                $recipients[] = $u->user_email;
            }
        }
        if (empty($recipients)) return;

        $subject = 'DGPTM ' . $dokument_name . ': Neuer Kommentar von ' . $author->display_name;
        $body    = $this->build_notification_html($author, $comment, $dokument_name);

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        foreach ($recipients as $email) {
            $headers[] = 'Bcc: ' . $email;
        }

        wp_mail('nichtantworten@dgptm.de', $subject, $body, $headers);
    }

    private function get_section_label($section_id) {
        $sections = $this->get_sections();
        return $sections[$section_id] ?? $section_id;
    }

    private function build_notification_html($author, $comment, $dokument_name) {
        $date          = date_i18n('d.m.Y, H:i', strtotime($comment['timestamp']));
        $text          = nl2br(esc_html($comment['text']));
        $author_name   = esc_html($author->display_name);
        $doc_name      = esc_html($dokument_name);
        $section_label = esc_html($this->get_section_label($comment['section'] ?? ''));

        return '<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

  <tr>
    <td style="background:#003366;padding:20px 30px;">
      <table width="100%"><tr>
        <td style="color:#ffffff;font-size:20px;font-weight:700;">DGPTM</td>
        <td align="right" style="color:#8bb8e8;font-size:13px;">' . $doc_name . '</td>
      </tr></table>
    </td>
  </tr>

  <tr>
    <td style="padding:28px 30px 12px;">
      <h2 style="margin:0;font-size:18px;color:#1a1a1a;">Neuer Kommentar</h2>
      <p style="margin:6px 0 0;font-size:14px;color:#6b7280;">von <strong>' . $author_name . '</strong> am ' . $date . '</p>
    </td>
  </tr>

  <tr>
    <td style="padding:4px 30px 8px;">
      <div style="display:inline-block;background:#e8eaf6;color:#283593;font-size:12px;font-weight:600;padding:4px 12px;border-radius:12px;">
        Abschnitt: ' . $section_label . '
      </div>
    </td>
  </tr>

  <tr>
    <td style="padding:8px 30px 24px;">
      <div style="background:#f0f5fa;border-left:4px solid #003366;border-radius:0 8px 8px 0;padding:16px 20px;font-size:15px;line-height:1.6;color:#1a1a1a;">
        ' . $text . '
      </div>
    </td>
  </tr>

  <tr>
    <td align="center" style="padding:0 30px 28px;">
      <a href="' . esc_url(home_url('/mitgliederbereich/')) . '" style="display:inline-block;background:#003366;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;">Im Mitgliederbereich ansehen</a>
    </td>
  </tr>

  <tr>
    <td style="background:#f9fafb;padding:16px 30px;border-top:1px solid #e5e7eb;">
      <p style="margin:0;font-size:12px;color:#9ca3af;text-align:center;">
        Diese Nachricht wurde automatisch gesendet, weil du am Freigabe-Prozess &bdquo;' . $doc_name . '&ldquo; beteiligt bist.<br>
        Deutsche Gesellschaft fuer Perfusiologie und Technische Medizin e.V.
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
    }

    /* ──────────────────────────────────────────────
     * Helfer
     * ────────────────────────────────────────────── */

    private function user_can_interact() {
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        return in_array('mitglied', (array) $user->roles, true)
            || in_array('administrator', (array) $user->roles, true);
    }

    private function get_approvals()     { return get_option(self::OPT_APPROVALS, []); }
    private function get_comments()      { return get_option(self::OPT_COMMENTS,  []); }
    public  function get_row_approvals() { return get_option(self::OPT_ROW_APPROVALS, []); }

    public function has_user_approved_row($user_id, $row_id) {
        $rows = $this->get_row_approvals();
        return isset($rows[$row_id]) && isset($rows[$row_id][(int) $user_id]);
    }

    public function get_row_approval_count($row_id) {
        $rows = $this->get_row_approvals();
        return isset($rows[$row_id]) ? count($rows[$row_id]) : 0;
    }

    public function get_row_approvers($row_id) {
        $rows = $this->get_row_approvals();
        return isset($rows[$row_id]) ? $rows[$row_id] : [];
    }

    /* ──────────────────────────────────────────────
     * AJAX — Zustimmung pro Zeile (toggle)
     * ────────────────────────────────────────────── */

    public function ajax_toggle_row_approval() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!$this->user_can_interact()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $row_id = sanitize_text_field(wp_unslash($_POST['row_id'] ?? ''));
        if (empty($row_id) || !preg_match('/^[a-z0-9_\-]+$/i', $row_id)) {
            wp_send_json_error('Ungueltige Zeilen-ID.');
        }

        $user = wp_get_current_user();
        $rows = $this->get_row_approvals();

        if (!isset($rows[$row_id])) {
            $rows[$row_id] = [];
        }

        $now      = current_time('mysql');
        $approved = isset($rows[$row_id][$user->ID]);

        if ($approved) {
            unset($rows[$row_id][$user->ID]);
            $action = 'revoked';
        } else {
            $rows[$row_id][$user->ID] = [
                'user_name' => $user->display_name,
                'timestamp' => $now,
            ];
            $action = 'approved';
        }

        update_option(self::OPT_ROW_APPROVALS, $rows, false);

        wp_send_json_success([
            'action'    => $action,
            'row_id'    => $row_id,
            'count'     => count($rows[$row_id]),
            'approvers' => array_values(array_map(function ($a) { return $a['user_name']; }, $rows[$row_id])),
        ]);
    }

    private function has_user_approved($user_id) {
        foreach ($this->get_approvals() as $a) {
            if ((int) $a['user_id'] === (int) $user_id) {
                return true;
            }
        }
        return false;
    }

    public function render_comment_html($comment, $current_user_id) {
        $can_delete = ((int) $comment['user_id'] === (int) $current_user_id)
                      || current_user_can('manage_options');
        $ts = date_i18n('d.m.Y, H:i', strtotime($comment['timestamp']));

        $html  = '<div class="dgptm-wsb-evl-comment" data-comment-id="' . esc_attr($comment['id']) . '">';
        $html .= '  <div class="dgptm-wsb-evl-comment-meta">';
        $html .= '    <strong>' . esc_html($comment['user_name']) . '</strong>';
        $html .= '    <span class="dgptm-wsb-evl-comment-date">' . esc_html($ts) . '</span>';
        if ($can_delete) {
            $html .= '    <button type="button" class="dgptm-wsb-evl-comment-delete" data-id="' . esc_attr($comment['id']) . '" title="Kommentar loeschen">&times;</button>';
        }
        $html .= '  </div>';
        $html .= '  <div class="dgptm-wsb-evl-comment-text">' . nl2br(esc_html($comment['text'])) . '</div>';
        $html .= '</div>';

        return $html;
    }
}
