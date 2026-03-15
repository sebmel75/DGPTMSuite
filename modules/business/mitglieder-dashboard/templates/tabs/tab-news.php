<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">News & Veranstaltungen</h3>

<div class="dgptm-grid-2">
    <div class="dgptm-card">
        <h3>Nachrichten fuer Mitglieder</h3>
        <?php if (shortcode_exists('news-modal')) : ?>
            <?php echo do_shortcode('[news-modal category="intern" layout="list" title_length="100" show_pubdate=true]'); ?>
        <?php else : ?>
            <p class="dgptm-text-muted">News-Modul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>

    <div class="dgptm-card">
        <h3>Veranstaltungen</h3>
        <?php if (shortcode_exists('news-modal')) : ?>
            <?php echo do_shortcode('[news-modal category="events" layout="list" sort_field="event_start" sort_dir="ASC" title_length="50"]'); ?>
        <?php else : ?>
            <p class="dgptm-text-muted">Veranstaltungsmodul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($permissions->check_acf_permission($user_id, 'testbereich')) : ?>
<div style="margin-top: var(--dgptm-gap);">
    <a href="<?php echo esc_url(home_url('/mitgliedschaft/interner-bereich/blaue-seiten/')); ?>"
       class="dgptm-btn dgptm-btn--outline">
        Zu den Blauen Seiten (Testversion)
    </a>
</div>
<?php endif; ?>
