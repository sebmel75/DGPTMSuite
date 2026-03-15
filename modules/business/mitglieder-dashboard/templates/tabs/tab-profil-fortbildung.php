<?php
if (!defined('ABSPATH')) exit;
?>
<a href="<?php echo esc_url(home_url('/mitgliedschaft/interner-bereich/fortbildungsnachweis/')); ?>"
   class="dgptm-btn dgptm-btn--primary">
    <span class="dashicons dashicons-welcome-learn-more"></span>
    Fortbildungsnachweis (inkl. Quiz)
</a>

<?php if (shortcode_exists('fobi_nachweis_pruefliste')) : ?>
    <div style="margin-top: var(--dgptm-gap);">
        <?php echo do_shortcode('[fobi_nachweis_pruefliste]'); ?>
    </div>
<?php endif; ?>
