<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Microsoft 365 Gruppen</h3>

<div class="dgptm-banner dgptm-banner--warning">
    <span class="dashicons dashicons-warning"></span>
    <div>
        <strong>Achtung:</strong> Hier koennen Mitglieder zu Gruppen hinzugefuegt werden, die in Microsoft 365 definiert sind und ggf. Rechte auf
        <a href="https://dgptm.sharepoint.com" target="_blank">dgptm.sharepoint.com</a> in Teams oder in Outlook verleihen.
        Bitte sorgfaeltig verwenden.
    </div>
</div>

<?php if (shortcode_exists('ms365_group_manager')) : ?>
    <?php echo do_shortcode('[ms365_group_manager]'); ?>
<?php else : ?>
    <div class="dgptm-tab-unavailable">
        <span class="dashicons dashicons-cloud"></span>
        <p>Microsoft-365-Modul ist derzeit nicht verfuegbar.</p>
    </div>
<?php endif; ?>
