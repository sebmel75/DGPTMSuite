<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Abstimmungsmanager</h3>

<div class="dgptm-grid-2">
    <a href="<?php echo esc_url(home_url('/mitgliedschaft/interner-bereich/abstimmungsmanager/')); ?>" class="dgptm-link-card">
        <span class="dashicons dashicons-yes-alt"></span>
        <span class="dgptm-link-card__text">Abstimmungsmanager</span>
    </a>
    <a href="<?php echo esc_url(home_url('/mitgliedschaft/interner-bereich/abstimmungsmanager/abstimmungstool-beamer/')); ?>" class="dgptm-link-card" target="_blank">
        <span class="dashicons dashicons-desktop"></span>
        <span class="dgptm-link-card__text">Beameranzeige (Vollbild)</span>
    </a>
</div>

<?php if (shortcode_exists('member_vote')) : ?>
<div class="dgptm-card" style="margin-top: var(--dgptm-gap);">
    <h3>Abstimmen</h3>
    <?php echo do_shortcode('[member_vote]'); ?>
</div>
<?php endif; ?>
