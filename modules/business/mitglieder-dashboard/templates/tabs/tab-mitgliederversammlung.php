<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Mitgliederversammlung 2025</h3>

<div class="dgptm-card">
    <p>Auch in diesem Jahr haben alle Mitglieder die Moeglichkeit alternativ online an der Mitgliederversammlung teilzunehmen sofern die Teilnahme in Praesenz nicht moeglich ist. <strong>Eine Anmeldung fuer die Praesenzveranstaltung in Leipzig ist <u>nicht</u> notwendig!</strong></p>
</div>

<?php if (shortcode_exists('online_abstimmen_button')) : ?>
<div class="dgptm-card">
    <h3>Online-Teilnahme registrieren</h3>
    <?php echo do_shortcode('[online_abstimmen_button meeting_number="82770189111" kind="webinar" test="1"]'); ?>
    <p style="margin-top: 8px; font-size: 13px; color: var(--dgptm-text-muted);">
        Bitte den Button nur druecken, wenn Sie online an der Mitgliederversammlung teilnehmen moechten.
    </p>
    <p><span style="color: var(--dgptm-error);">Rot</span> = Nicht online teilnehmen &bull;
       <span style="color: var(--dgptm-success);">Gruen</span> = Online teilnehmen</p>
</div>
<?php endif; ?>

<?php if (shortcode_exists('online_abstimmen_zoom_link')) : ?>
<div class="dgptm-card">
    <h3>Zoom-Link</h3>
    <p>Hier koennen Sie <?php echo do_shortcode('[online_abstimmen_zoom_link]'); ?>. Der Link wurde Ihnen auch per Mail zugesandt.</p>
</div>
<?php endif; ?>

<?php if (shortcode_exists('online_abstimmen_liste') && user_can($user_id, 'manage_options')) : ?>
<details class="dgptm-accordion">
    <summary>Teilnehmerliste (Admin)</summary>
    <div class="dgptm-accordion__content">
        <?php echo do_shortcode('[online_abstimmen_liste]'); ?>
    </div>
</details>
<?php endif; ?>
