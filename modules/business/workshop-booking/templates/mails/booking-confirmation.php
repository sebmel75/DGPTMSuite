<?php
/**
 * Mail-Template: Buchungsbestaetigung.
 *
 * Variablen aus class-mail-sender.php:
 *   @var string      $name           Vor- + Nachname
 *   @var string      $event_name     Workshop-/Webinar-Titel
 *   @var string      $event_from     Startdatum (formatiert)
 *   @var string      $ticket_number  Ticketnummer (Phase 2; leer wenn nicht vergeben)
 *   @var string|null $token_url      Persoenlicher Link fuer Nicht-Mitglieder (Phase 2; null = WP-User mit Mitgliederbereich)
 */
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
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
        <td align="right" style="color:#8bb8e8;font-size:13px;">Buchungsbestätigung</td>
      </tr></table>
    </td>
  </tr>

  <tr>
    <td style="padding:28px 30px 12px;">
      <h2 style="margin:0;font-size:18px;color:#1a1a1a;">Buchung bestätigt</h2>
      <p style="margin:6px 0 0;font-size:14px;color:#6b7280;">Hallo <?php echo esc_html($name ?: 'liebe:r Teilnehmer:in'); ?>,</p>
    </td>
  </tr>

  <tr>
    <td style="padding:8px 30px 8px;font-size:15px;line-height:1.6;color:#1a1a1a;">
      <p>vielen Dank für deine Anmeldung zu <strong><?php echo esc_html($event_name); ?></strong>.</p>
      <?php if (!empty($event_from)) : ?>
        <p>Termin: <strong><?php echo esc_html($event_from); ?></strong></p>
      <?php endif; ?>
    </td>
  </tr>

  <?php if (!empty($ticket_number)) : ?>
  <tr>
    <td style="padding:8px 30px 8px;">
      <div style="background:#f0f5fa;border-left:4px solid #003366;border-radius:0 8px 8px 0;padding:14px 18px;">
        <div style="font-size:11px;color:#003366;text-transform:uppercase;letter-spacing:0.05em;">Ticketnummer</div>
        <div style="font-size:22px;font-family:Courier,monospace;color:#003366;letter-spacing:0.05em;font-weight:700;">
          <?php echo esc_html($ticket_number); ?>
        </div>
      </div>
    </td>
  </tr>
  <?php endif; ?>

  <tr>
    <td style="padding:8px 30px 24px;font-size:15px;line-height:1.6;color:#1a1a1a;">
      <p>Im Anhang findest du:</p>
      <ul style="margin:6px 0 12px 18px;padding:0;">
        <li>Den Termin als Kalender-Datei (<strong>ICS</strong>) zum Import in dein Kalender-Programm.</li>
        <?php if (!empty($ticket_number)) : ?>
          <li>Dein <strong>Ticket-PDF</strong> mit QR-Code &mdash; bitte beim Einlass auf dem Smartphone zeigen oder ausdrucken.</li>
        <?php endif; ?>
      </ul>
      <?php if (!empty($token_url)) : ?>
        <p style="margin-top:18px;">Du kannst dein Ticket jederzeit über deinen persönlichen Link einsehen:</p>
        <p style="text-align:center;margin:14px 0;">
          <a href="<?php echo esc_url($token_url); ?>" style="display:inline-block;background:#003366;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;">Mein Ticket öffnen</a>
        </p>
        <p style="font-size:12px;color:#6b7280;">Der Link ist persönlich und 30 Tage über das Veranstaltungsende hinaus gültig.</p>
      <?php endif; ?>
      <p>Weitere Informationen folgen rechtzeitig vor Beginn.</p>
      <p style="margin-top:20px;">Bei Fragen wende dich bitte an die <a href="mailto:geschaeftsstelle@dgptm.de" style="color:#003366;">Geschäftsstelle</a>.</p>
    </td>
  </tr>

  <tr>
    <td style="background:#f9fafb;padding:16px 30px;border-top:1px solid #e5e7eb;">
      <p style="margin:0;font-size:12px;color:#9ca3af;text-align:center;">
        Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V.<br>
        Diese Nachricht wurde automatisch erstellt.
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
