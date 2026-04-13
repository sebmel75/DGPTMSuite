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
        add_shortcode('dgptm_stipendium_freigabe_export', [$this, 'render_export_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        // AJAX-Endpoints (nur fuer eingeloggte User)
        add_action('wp_ajax_dgptm_freigabe_comment',    [$this, 'ajax_add_comment']);
        add_action('wp_ajax_dgptm_freigabe_delete_comment', [$this, 'ajax_delete_comment']);
        add_action('wp_ajax_dgptm_freigabe_approve',    [$this, 'ajax_approve']);
        add_action('wp_ajax_dgptm_freigabe_revoke',     [$this, 'ajax_revoke']);

        // Admin-Endpoints (nur manage_options)
        add_action('wp_ajax_dgptm_freigabe_export',     [$this, 'ajax_export_comments']);
        add_action('wp_ajax_dgptm_freigabe_mark_read',  [$this, 'ajax_mark_comments_read']);

        // Demo-Bewertung
        add_action('wp_ajax_dgptm_freigabe_demo_bewertung', [$this, 'ajax_demo_bewertung']);
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
     * Export-Shortcode (Admin-only, Klartext)
     * ────────────────────────────────────────────── */

    public function render_export_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '';
        }

        $comments  = $this->get_comments();
        $approvals = $this->get_approvals();

        $sections = [
            'section-aenderungen'       => '1. Was aendert sich?',
            'section-rollen'            => '2. Wer ist beteiligt?',
            'section-ablauf'            => '3. Ablauf im Ueberblick',
            'section-bewertungsbogen'   => '4. Der digitale Bewertungsbogen',
            'section-dokumente'         => '5. Welche Dokumente werden hochgeladen?',
            'section-datenschutz'       => '6. Datenschutz (DSGVO)',
            'section-einstellungen'     => '7. Konfigurierbare Einstellungen',
            'section-benachrichtigungen'=> '8. E-Mail-Benachrichtigungen',
            'section-naechste-schritte' => '9. Naechste Schritte',
        ];

        ob_start();
        $export_id = 'dgptm-freigabe-export-' . wp_rand();
        ?>
        <div style="margin:16px 0;">
            <button type="button" onclick="(function(){var t=document.getElementById('<?php echo $export_id; ?>');var r=document.createRange();r.selectNodeContents(t);var s=window.getSelection();s.removeAllRanges();s.addRange(r);document.execCommand('copy');s.removeAllRanges();this.textContent='Kopiert!';var b=this;setTimeout(function(){b.textContent='Alle Kommentare kopieren';},2000);}).call(this)" style="background:#003366;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Alle Kommentare kopieren</button>
        </div>
        <div id="<?php echo $export_id; ?>" style="font-family:monospace;font-size:13px;background:#f5f5f5;padding:20px;border-radius:8px;white-space:pre-wrap;"><?php

        echo esc_html("=== FREIGABEN (" . count($approvals) . ") ===") . "\n";
        foreach ($approvals as $a) {
            echo "  " . esc_html($a['user_name']) . " — " . esc_html($a['timestamp']) . "\n";
        }

        echo "\n" . esc_html("=== KOMMENTARE (" . count($comments) . ") ===") . "\n";
        foreach ($sections as $sid => $label) {
            $sc = array_filter($comments, function($c) use ($sid) { return $c['section'] === $sid; });
            if (empty($sc)) continue;
            echo "\n--- " . esc_html($label) . " ---\n";
            foreach ($sc as $c) {
                $status = !empty($c['status']) ? ' [' . $c['status'] . ']' : '';
                echo "  [" . esc_html($c['timestamp']) . "] " . esc_html($c['user_name']) . $status . ":\n";
                echo "  " . esc_html($c['text']) . "\n\n";
            }
        }

        ?></div>
        <?php
        return ob_get_clean();
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

        // Alle Beteiligten per E-Mail benachrichtigen
        $this->notify_participants($user, $comment, 'Stipendium-Freigabe');

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
                $c['status'] = 'eingearbeitet';
                $c['read_at'] = current_time('mysql');
                $marked++;
            }
        }
        unset($c);

        update_option(self::OPT_COMMENTS, $comments, false);

        wp_send_json_success(['marked' => $marked]);
    }

    /* ──────────────────────────────────────────────
     * AJAX: Demo-Bewertung starten
     * ────────────────────────────────────────────── */

    public function ajax_demo_bewertung() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!$this->user_can_interact()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $user = wp_get_current_user();

        // Token-Manager pruefen
        if (!class_exists('DGPTM_Stipendium_Gutachter_Token')) {
            wp_send_json_error('Token-System noch nicht verfuegbar. Bitte Modul-Update abwarten.');
        }

        // Sicherstellen dass Token-Tabelle existiert
        if (class_exists('DGPTM_Stipendium_Token_Installer')) {
            DGPTM_Stipendium_Token_Installer::install();
        }

        $token_manager = new DGPTM_Stipendium_Gutachter_Token();

        // Demo-Token fuer fiktive Bewerbung generieren
        $token_data = $token_manager->generate(
            'DEMO-2026-001',
            $user->display_name,
            $user->user_email,
            28
        );

        if (is_wp_error($token_data)) {
            // Bei "token_exists" den bestehenden Token verwenden
            if ($token_data->get_error_code() === 'token_exists') {
                global $wpdb;
                $table = $wpdb->prefix . 'dgptm_stipendium_tokens';
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT token FROM {$table} WHERE stipendium_id = %s AND gutachter_email = %s AND bewertung_status != 'abgeschlossen' ORDER BY created_at DESC LIMIT 1",
                    'DEMO-2026-001', $user->user_email
                ), ARRAY_A);
                if ($existing) {
                    $token_data = ['token' => $existing['token']];
                } else {
                    wp_send_json_error('Token konnte nicht erstellt werden: ' . $token_data->get_error_message());
                }
            } else {
                wp_send_json_error('Token konnte nicht erstellt werden: ' . $token_data->get_error_message());
            }
        }

        // Einladungsmail senden
        if (class_exists('DGPTM_Stipendium_Mail_Templates')) {
            $gutachten_url = home_url('/karriere/stipendien/gutachten/?token=' . $token_data['token']);

            DGPTM_Stipendium_Mail_Templates::send_einladung([
                'gutachter_name'  => $user->display_name,
                'gutachter_email' => $user->user_email,
                'bewerber_name'   => 'Dr. Max Mustermann (Demo-Bewerbung)',
                'stipendientyp'   => 'Promotionsstipendium',
                'runde'           => 'Ausschreibung 2026 (Demo)',
                'gutachten_url'   => $gutachten_url,
                'frist'           => date_i18n('d.m.Y', strtotime('+28 days')),
            ]);
        }

        wp_send_json_success([
            'message' => 'Demo-Einladung wurde an ' . $user->user_email . ' gesendet.',
        ]);
    }

    /* ──────────────────────────────────────────────
     * E-Mail: Beteiligte benachrichtigen
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
        $body = $this->build_notification_html($author, $comment, $dokument_name);

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        foreach ($recipients as $email) {
            $headers[] = 'Bcc: ' . $email;
        }

        wp_mail('nichtantworten@dgptm.de', $subject, $body, $headers);
    }

    private function get_section_label($section_id) {
        $sections = [
            'section-aenderungen'       => '1. Was aendert sich?',
            'section-rollen'            => '2. Wer ist beteiligt?',
            'section-ablauf'            => '3. Ablauf im Ueberblick',
            'section-bewertungsbogen'   => '4. Der digitale Bewertungsbogen',
            'section-dokumente'         => '5. Welche Dokumente werden hochgeladen?',
            'section-datenschutz'       => '6. Datenschutz (DSGVO)',
            'section-einstellungen'     => '7. Konfigurierbare Einstellungen',
            'section-benachrichtigungen'=> '8. E-Mail-Benachrichtigungen',
            'section-naechste-schritte' => '9. Naechste Schritte',
        ];
        return $sections[$section_id] ?? $section_id;
    }

    private function build_notification_html($author, $comment, $dokument_name) {
        $date = date_i18n('d.m.Y, H:i', strtotime($comment['timestamp']));
        $text = nl2br(esc_html($comment['text']));
        $author_name = esc_html($author->display_name);
        $doc_name = esc_html($dokument_name);
        $section_label = esc_html($this->get_section_label($comment['section'] ?? ''));

        return '<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

  <!-- Header -->
  <tr>
    <td style="background:#003366;padding:20px 30px;">
      <table width="100%"><tr>
        <td style="color:#ffffff;font-size:20px;font-weight:700;">DGPTM</td>
        <td align="right" style="color:#8bb8e8;font-size:13px;">' . $doc_name . '</td>
      </tr></table>
    </td>
  </tr>

  <!-- Titel -->
  <tr>
    <td style="padding:28px 30px 12px;">
      <h2 style="margin:0;font-size:18px;color:#1a1a1a;">Neuer Kommentar</h2>
      <p style="margin:6px 0 0;font-size:14px;color:#6b7280;">von <strong>' . $author_name . '</strong> am ' . $date . '</p>
    </td>
  </tr>

  <!-- Abschnitt -->
  <tr>
    <td style="padding:4px 30px 8px;">
      <div style="display:inline-block;background:#e8eaf6;color:#283593;font-size:12px;font-weight:600;padding:4px 12px;border-radius:12px;">
        Abschnitt: ' . $section_label . '
      </div>
    </td>
  </tr>

  <!-- Kommentar -->
  <tr>
    <td style="padding:8px 30px 24px;">
      <div style="background:#f0f5fa;border-left:4px solid #003366;border-radius:0 8px 8px 0;padding:16px 20px;font-size:15px;line-height:1.6;color:#1a1a1a;">
        ' . $text . '
      </div>
    </td>
  </tr>

  <!-- CTA -->
  <tr>
    <td align="center" style="padding:0 30px 28px;">
      <a href="' . esc_url(home_url('/mitgliederbereich/')) . '" style="display:inline-block;background:#003366;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;">Im Mitgliederbereich ansehen</a>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#f9fafb;padding:16px 30px;border-top:1px solid #e5e7eb;">
      <p style="margin:0;font-size:12px;color:#9ca3af;text-align:center;">
        Diese Nachricht wurde automatisch gesendet, weil Sie am Freigabe-Prozess &bdquo;' . $doc_name . '&ldquo; beteiligt sind.<br>
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
