<?php
/**
 * Mail-Template: Buchungsbestaetigung.
 *
 * Variablen aus class-mail-sender.php:
 *   @var string $name        Vor- + Nachname
 *   @var string $event_name  Workshop-/Webinar-Titel
 *   @var string $event_from  Startdatum (formatiert)
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
    <td style="padding:8px 30px 24px;font-size:15px;line-height:1.6;color:#1a1a1a;">
      <p>vielen Dank für deine Anmeldung zu <strong><?php echo esc_html($event_name); ?></strong>.</p>
      <?php if ($event_from) : ?>
        <p>Termin: <strong><?php echo esc_html($event_from); ?></strong></p>
      <?php endif; ?>
      <p>Im Anhang findest du den Termin als Kalender-Datei (ICS), die du direkt in deinen Kalender importieren kannst.</p>
      <p>Weitere Informationen zur Veranstaltung folgen rechtzeitig vor Beginn.</p>
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
