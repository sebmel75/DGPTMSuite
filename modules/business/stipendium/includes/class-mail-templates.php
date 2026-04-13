<?php
/**
 * DGPTM Stipendium — HTML-Mail-Templates.
 *
 * Baut HTML-Mails im DGPTM-Design (table-based, #003366 Header).
 * Zwei Templates:
 *   1. Einladung zur Begutachtung ("Jetzt begutachten")
 *   2. Abschluss-Benachrichtigung an Vorsitzenden
 *
 * Sendet ueber wp_mail() mit Content-Type text/html.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Mail_Templates {

    /**
     * Einladungs-Mail an Gutachter senden.
     *
     * @param array $args {
     *     @type string $gutachter_name  Name des Gutachters
     *     @type string $gutachter_email E-Mail des Gutachters
     *     @type string $bewerber_name   Name des Bewerbers
     *     @type string $stipendientyp   z.B. "Promotionsstipendium"
     *     @type string $runde           z.B. "Ausschreibung 2026"
     *     @type string $frist           z.B. "30.05.2026"
     *     @type string $gutachten_url   URL mit Token
     * }
     * @return bool Erfolg
     */
    public static function send_einladung($args) {
        $subject = 'DGPTM Stipendium: Einladung zur Begutachtung';
        $body = self::build_einladung_html($args);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: DGPTM Stipendienvergabe <nichtantworten@dgptm.de>',
        ];

        $sent = wp_mail($args['gutachter_email'], $subject, $body, $headers);

        if (!$sent && function_exists('dgptm_log_error')) {
            dgptm_log_error('Stipendium Einladungs-Mail fehlgeschlagen: ' . $args['gutachter_email'], 'stipendium');
        }

        return $sent;
    }

    /**
     * Abschluss-Benachrichtigung an Vorsitzenden senden.
     *
     * @param array $args {
     *     @type string $vorsitz_email   E-Mail des Vorsitzenden
     *     @type string $gutachter_name  Name des Gutachters
     *     @type string $bewerber_name   Name des Bewerbers
     *     @type string $stipendientyp   Stipendientyp
     *     @type float  $gesamtscore     Gesamtscore des Gutachtens
     *     @type string $datum           Abgabedatum formatiert
     * }
     * @return bool Erfolg
     */
    public static function send_abschluss_benachrichtigung($args) {
        $subject = 'DGPTM Stipendium: Gutachten eingegangen von ' . $args['gutachter_name'];
        $body = self::build_abschluss_html($args);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: DGPTM Stipendienvergabe <nichtantworten@dgptm.de>',
        ];

        return wp_mail($args['vorsitz_email'], $subject, $body, $headers);
    }

    /**
     * HTML fuer Einladungs-Mail zusammenbauen.
     */
    private static function build_einladung_html($args) {
        $gutachter_name = esc_html($args['gutachter_name']);
        $bewerber_name  = esc_html($args['bewerber_name']);
        $stipendientyp  = esc_html($args['stipendientyp']);
        $runde          = esc_html($args['runde']);
        $frist          = esc_html($args['frist']);
        $url            = esc_url($args['gutachten_url']);

        return self::wrap_layout(
            'Stipendienvergabe',
            // Titel
            '<h2 style="margin:0;font-size:18px;color:#1a1a1a;">Einladung zur Begutachtung</h2>',
            // Body
            '<p style="font-size:15px;line-height:1.6;color:#333;">
                Sehr geehrte/r ' . $gutachter_name . ',
            </p>
            <p style="font-size:15px;line-height:1.6;color:#333;">
                Sie wurden vom Vorsitzenden des Stipendiumsrats eingeladen,
                eine Bewerbung fuer das ' . $stipendientyp . ' der DGPTM zu begutachten.
            </p>

            <!-- Info-Box -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">
            <tr><td style="background:#f0f5fa;border-left:4px solid #003366;border-radius:0 8px 8px 0;padding:16px 20px;">
                <table width="100%" cellpadding="4" cellspacing="0">
                    <tr>
                        <td style="font-size:13px;color:#6b7280;width:120px;">Bewerber/in:</td>
                        <td style="font-size:14px;color:#1a1a1a;font-weight:600;">' . $bewerber_name . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Stipendium:</td>
                        <td style="font-size:14px;color:#1a1a1a;">' . $stipendientyp . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Runde:</td>
                        <td style="font-size:14px;color:#1a1a1a;">' . $runde . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Frist:</td>
                        <td style="font-size:14px;color:#1a1a1a;font-weight:600;">' . $frist . '</td>
                    </tr>
                </table>
            </td></tr>
            </table>',
            // CTA-Button
            '<a href="' . $url . '" style="display:inline-block;background:#003366;color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:8px;font-size:16px;font-weight:600;">Jetzt begutachten</a>',
            // Footer-Hinweis
            '<p style="font-size:13px;color:#6b7280;line-height:1.5;margin-top:20px;">
                Dieser Link ist persoenlich und vertraulich. Bitte geben Sie ihn nicht an Dritte weiter.
                Der Link ist bis zum ' . $frist . ' gueltig.
            </p>'
        );
    }

    /**
     * HTML fuer Abschluss-Benachrichtigung zusammenbauen.
     */
    private static function build_abschluss_html($args) {
        $gutachter_name = esc_html($args['gutachter_name']);
        $bewerber_name  = esc_html($args['bewerber_name']);
        $stipendientyp  = esc_html($args['stipendientyp']);
        $score          = number_format((float)($args['gesamtscore'] ?? 0), 2, ',', '');
        $punkte         = number_format((float)($args['gesamtscore'] ?? 0) * 10, 1, ',', '');
        $datum          = esc_html($args['datum']);
        $dashboard_url  = esc_url(home_url('/mitgliederbereich/'));

        return self::wrap_layout(
            'Stipendienvergabe',
            // Titel
            '<h2 style="margin:0;font-size:18px;color:#1a1a1a;">Gutachten eingegangen</h2>
             <p style="margin:6px 0 0;font-size:14px;color:#6b7280;">von <strong>' . $gutachter_name . '</strong> am ' . $datum . '</p>',
            // Body
            '<table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;">
            <tr><td style="background:#f0f5fa;border-left:4px solid #003366;border-radius:0 8px 8px 0;padding:16px 20px;">
                <table width="100%" cellpadding="4" cellspacing="0">
                    <tr>
                        <td style="font-size:13px;color:#6b7280;width:120px;">Bewerber/in:</td>
                        <td style="font-size:14px;color:#1a1a1a;font-weight:600;">' . $bewerber_name . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Stipendium:</td>
                        <td style="font-size:14px;color:#1a1a1a;">' . $stipendientyp . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Gesamtscore:</td>
                        <td style="font-size:14px;color:#1a1a1a;font-weight:600;">' . $score . ' / 10 (' . $punkte . ' Punkte)</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Gutachter/in:</td>
                        <td style="font-size:14px;color:#1a1a1a;">' . $gutachter_name . '</td>
                    </tr>
                </table>
            </td></tr>
            </table>',
            // CTA-Button
            '<a href="' . $dashboard_url . '" style="display:inline-block;background:#003366;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;">Im Dashboard ansehen</a>',
            // Footer-Hinweis
            ''
        );
    }

    /**
     * HTML-Layout-Wrapper im DGPTM-Design.
     *
     * Table-based Layout, #003366 Header, responsive 600px.
     * Identisches Design wie class-freigabe.php build_notification_html().
     *
     * @param string $header_right  Text rechts im Header
     * @param string $title_html    Titel-Bereich
     * @param string $body_html     Haupt-Inhalt
     * @param string $cta_html      CTA-Button HTML
     * @param string $footer_note   Zusaetzlicher Hinweistext
     * @return string Komplettes HTML-Dokument
     */
    private static function wrap_layout($header_right, $title_html, $body_html, $cta_html, $footer_note) {
        return '<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

  <!-- Header -->
  <tr>
    <td style="background:#003366;padding:20px 30px;">
      <table width="100%"><tr>
        <td style="color:#ffffff;font-size:20px;font-weight:700;">DGPTM</td>
        <td align="right" style="color:#8bb8e8;font-size:13px;">' . esc_html($header_right) . '</td>
      </tr></table>
    </td>
  </tr>

  <!-- Titel -->
  <tr>
    <td style="padding:28px 30px 12px;">
      ' . $title_html . '
    </td>
  </tr>

  <!-- Inhalt -->
  <tr>
    <td style="padding:4px 30px 16px;">
      ' . $body_html . '
    </td>
  </tr>

  <!-- CTA-Button -->
  <tr>
    <td align="center" style="padding:8px 30px 28px;">
      ' . $cta_html . '
    </td>
  </tr>

  <!-- Zusaetzlicher Hinweis -->
  ' . ($footer_note ? '<tr><td style="padding:0 30px 20px;">' . $footer_note . '</td></tr>' : '') . '

  <!-- Footer -->
  <tr>
    <td style="background:#f9fafb;padding:16px 30px;border-top:1px solid #e5e7eb;">
      <p style="margin:0;font-size:12px;color:#9ca3af;text-align:center;">
        Diese Nachricht wurde automatisch gesendet.<br>
        Deutsche Gesellschaft fuer Perfusiologie und Technische Medizin e.V. | nichtantworten@dgptm.de
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
    }
}
