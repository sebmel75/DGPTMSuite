<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Admin & Testbereich</h3>

<details class="dgptm-accordion">
    <summary>Timeline-Manager</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('timeline_manager')) : ?>
            <?php echo do_shortcode('[timeline_manager]'); ?>
        <?php else : ?>
            <p>Modul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>Gehaltsbarometer Statistik</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('gehaltsbarometer_statistik')) : ?>
            <?php echo do_shortcode('[gehaltsbarometer_statistik]'); ?>
        <?php else : ?>
            <p>Modul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>Wissens-Bot</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('wissens_bot')) : ?>
            <?php echo do_shortcode('[wissens_bot]'); ?>
        <?php else : ?>
            <p>Modul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>Herzzentren-Benutzerliste</summary>
    <div class="dgptm-accordion__content">
        <?php echo do_shortcode('[herzzentren_benutzer_liste]'); ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>DOI-Liste</summary>
    <div class="dgptm-accordion__content">
        <?php echo do_shortcode('[doi_liste]'); ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>Publikationen</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('publikation_list_frontend')) : ?>
            <?php echo do_shortcode('[publikation_list_frontend count="5"]'); ?>
        <?php endif; ?>
        <?php if (shortcode_exists('publikation_edit_frontend')) : ?>
            <?php echo do_shortcode('[publikation_edit_frontend]'); ?>
        <?php endif; ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>Blaue Seiten</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('list_blaue_seiten')) : ?>
            <?php echo do_shortcode('[list_blaue_seiten]'); ?>
        <?php else : ?>
            <p>Modul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>EFN-Etiketten (erweitert)</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('efn_label_sheet')) : ?>
            <?php echo do_shortcode('[efn_label_sheet default="Avery L7160 (63.5x38.1, 3x8)" show_text="yes"]'); ?>
        <?php else : ?>
            <p>Modul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>
</details>

<?php if (shortcode_exists('fortbildung_bestaetigung')) : ?>
<div class="dgptm-card">
    <h3>Fortbildungsbestaetigung</h3>
    <?php echo do_shortcode('[fortbildung_bestaetigung]'); ?>
</div>
<?php endif; ?>

<?php if (shortcode_exists('events_by_month')) : ?>
<div class="dgptm-card">
    <h3>Events nach Monat</h3>
    <?php echo do_shortcode('[events_by_month]'); ?>
</div>
<?php endif; ?>
