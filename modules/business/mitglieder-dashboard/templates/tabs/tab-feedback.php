<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Anregungen und Wuensche</h3>
<p style="color: var(--dgptm-text-muted); margin-bottom: var(--dgptm-gap);">
    Wir sind fuer alle Wuensche und Hinweise offen.
</p>
<?php if (shortcode_exists('formidable')) : ?>
    <?php echo do_shortcode('[formidable id=13]'); ?>
<?php else : ?>
    <p>Feedback-Formular ist derzeit nicht verfuegbar.</p>
<?php endif; ?>
