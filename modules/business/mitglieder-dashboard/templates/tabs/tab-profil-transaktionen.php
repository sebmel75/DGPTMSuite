<?php
if (!defined('ABSPATH')) exit;
?>
<?php if (shortcode_exists('zoho_books_transactions')) : ?>
    <?php echo do_shortcode('[zoho_books_transactions]'); ?>
<?php else : ?>
    <p style="color:var(--dgptm-text-muted)">Transaktionsmodul nicht verfuegbar.</p>
<?php endif; ?>
