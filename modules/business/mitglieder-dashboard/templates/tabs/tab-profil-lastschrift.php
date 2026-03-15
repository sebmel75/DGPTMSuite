<?php
if (!defined('ABSPATH')) exit;
?>
<?php if (shortcode_exists('gcl_formidable')) : ?>
    <h4>Lastschriftmandat</h4>
    <?php echo do_shortcode('[gcl_formidable]'); ?>
<?php endif; ?>

<?php if (shortcode_exists('webhook_ajax_trigger')) : ?>
    <h4 style="margin-top:20px">Mitgliedsbescheinigung</h4>
    <?php echo do_shortcode('[webhook_ajax_trigger url="https://flow.zoho.eu/20086283718/flow/webhook/incoming?zapikey=1001.61e55251780c1730ee213bfe02d8a192.eb83171de88e8e99371cf264aa47e96c&isdebug=false" method="POST" user_field="zoho_id" cooldown="6" status_id="mgb" cooldown_message="Du hast heute schon eine Bescheinigung angefordert."]'); ?>
    <?php echo do_shortcode('[webhook_status_output id="mgb"]'); ?>
<?php endif; ?>

<?php if (shortcode_exists('dgptm-studistatus')) : ?>
    <h4 style="margin-top:20px">Studierendenstatus</h4>
    <?php echo do_shortcode('[dgptm-studistatus]'); ?>
<?php endif; ?>
