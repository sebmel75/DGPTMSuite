<?php
/**
 * Ticket-PDF-Generator.
 *
 * Erzeugt das Ticket-PDF (mit QR-Code, Workshop-Daten, Teilnehmer:in)
 * via dompdf/dompdf (Composer-Dependency).
 *
 * Bei fehlender Library: graceful Fallback (liefert null + Log-Eintrag).
 *
 * Spec EVL Abschnitt 4 Baustein "Ticket-PDF".
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Ticket_PDF {

    /**
     * Erzeugt ein Ticket-PDF als binaeren String.
     *
     * @param array $ticket
     *   Erwartete Felder:
     *     - ticket_number  (string)  z.B. "99999001"
     *     - first_name     (string)
     *     - last_name      (string)
     *     - event_name     (string)
     *     - event_from     (string)  bereits formatiert
     *     - event_location (string)
     *     - event_type     (string)  "Workshop" / "Webinar"
     * @return string|null  PDF-Inhalt oder null bei Fehler
     */
    public static function render(array $ticket) {
        if (!class_exists('Dompdf\\Dompdf')) {
            error_log('[DGPTM_WSB_Ticket_PDF] dompdf nicht verfuegbar — composer install fehlt?');
            return null;
        }

        try {
            $html = self::build_html($ticket);

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return $dompdf->output();
        } catch (\Throwable $e) {
            error_log('[DGPTM_WSB_Ticket_PDF] render_failed: ' . $e->getMessage());
            return null;
        }
    }

    const LOGO_URL = 'https://perfusiologie.de/nichtwordpress/DGPTM_Logo_kurz_horizontal_rgb_weiss_300_250106.png';
    const AGB_URL  = 'https://perfusiologie.de/agb';

    /**
     * Baut das HTML fuer das Ticket-PDF.
     */
    private static function build_html(array $ticket) {
        $ticket_number = isset($ticket['ticket_number']) ? $ticket['ticket_number'] : '';
        $ticket_type   = isset($ticket['ticket_type'])   ? $ticket['ticket_type']   : '';
        $first_name    = isset($ticket['first_name'])    ? $ticket['first_name']    : '';
        $last_name     = isset($ticket['last_name'])     ? $ticket['last_name']     : '';
        $full_name     = trim($first_name . ' ' . $last_name);
        $event_name    = isset($ticket['event_name'])    ? $ticket['event_name']    : '';
        $event_from    = isset($ticket['event_from'])    ? $ticket['event_from']    : '';
        $event_loc     = isset($ticket['event_location']) ? $ticket['event_location'] : '';

        $qr_uri = DGPTM_WSB_QR_Generator::as_data_uri($ticket_number, 320);
        $qr_img = $qr_uri
                ? '<img src="' . esc_attr($qr_uri) . '" alt="QR-Code Ticket ' . esc_attr($ticket_number) . '" style="width:180px;height:180px;">'
                : '<div style="width:180px;height:180px;border:2px dashed #999;display:flex;align-items:center;justify-content:center;color:#999;font-size:11px;">QR nicht verfügbar</div>';

        $logo    = apply_filters('dgptm_wsb_ticket_logo_url', self::LOGO_URL);
        $agb_url = apply_filters('dgptm_wsb_agb_url',         self::AGB_URL);

        return '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><title>DGPTM Veranstaltungsticket ' . esc_html($ticket_number) . '</title>
<style>
  @page { margin: 30mm 20mm 20mm 20mm; }
  body { font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; font-size: 12pt; }
  .header { background: #003366; color: #ffffff; padding: 20px 25px; border-radius: 8px; margin-bottom: 24px; }
  .header table { width: 100%; }
  .header td { vertical-align: middle; }
  .header .title-cell h1 { margin: 0; font-size: 20pt; }
  .header .logo-cell { text-align: right; width: 200px; }
  .header .logo-cell img { max-width: 180px; height: auto; }
  .ticket-box { display: table; width: 100%; }
  .ticket-data, .ticket-qr { display: table-cell; vertical-align: top; }
  .ticket-data { padding-right: 20px; }
  .ticket-qr { width: 200px; text-align: center; }
  .field { margin-bottom: 14px; }
  .field-label { font-size: 9pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
  .field-value { font-size: 13pt; color: #111827; font-weight: 600; }
  .ticket-number-box { background: #f0f5fa; border-left: 4px solid #003366; padding: 14px 18px; margin: 18px 0; border-radius: 0 6px 6px 0; }
  .ticket-number-box .label { font-size: 10pt; color: #003366; }
  .ticket-number-box .value { font-size: 16pt; font-family: Courier, monospace; color: #003366; letter-spacing: 0.04em; word-break: break-all; }
  .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 9pt; color: #6b7280; text-align: center; line-height: 1.6; }
  .footer .org { font-weight: 600; color: #1a1a1a; }
  .footer a { color: #003366; }
</style></head>
<body>
  <div class="header">
    <table>
      <tr>
        <td class="title-cell">
          <h1>Veranstaltungsticket</h1>
        </td>
        <td class="logo-cell">
          <img src="' . esc_url($logo) . '" alt="DGPTM">
        </td>
      </tr>
    </table>
  </div>

  <div class="ticket-box">
    <div class="ticket-data">
      <div class="field">
        <div class="field-label">Veranstaltung</div>
        <div class="field-value">' . esc_html($event_name) . '</div>
      </div>
      <div class="field">
        <div class="field-label">Termin</div>
        <div class="field-value">' . esc_html($event_from) . '</div>
      </div>
      ' . ($event_loc ? '<div class="field"><div class="field-label">Ort</div><div class="field-value">' . esc_html($event_loc) . '</div></div>' : '') . '
      <div class="field">
        <div class="field-label">Teilnehmer:in</div>
        <div class="field-value">' . esc_html($full_name) . '</div>
      </div>
      ' . ($ticket_type ? '<div class="field"><div class="field-label">Ticketart</div><div class="field-value">' . esc_html($ticket_type) . '</div></div>' : '') . '
      <div class="ticket-number-box">
        <div class="label">Ticketnummer</div>
        <div class="value">' . esc_html($ticket_number) . '</div>
      </div>
    </div>
    <div class="ticket-qr">
      ' . $qr_img . '
      <div style="margin-top:10px;font-size:9pt;color:#6b7280;">QR-Code für Einlass-Scan</div>
    </div>
  </div>

  <div class="footer">
    Bitte zeige dieses Ticket beim Einlass auf deinem Smartphone oder als Ausdruck vor.<br>
    Bei Fragen: <a href="mailto:geschaeftsstelle@dgptm.de">geschaeftsstelle@dgptm.de</a> &middot; Es gelten die <a href="' . esc_url($agb_url) . '">AGB der DGPTM</a>.<br>
    <span class="org">Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V.</span>
  </div>
</body></html>';
    }
}
