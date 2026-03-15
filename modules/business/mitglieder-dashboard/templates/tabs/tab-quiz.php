<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Quizzmaster Area</h3>

<details class="dgptm-accordion" open>
    <summary>Frontend-Anfragen</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('ays_quiz_frontend_requests')) : ?>
            <?php echo do_shortcode('[ays_quiz_frontend_requests]'); ?>
        <?php else : ?>
            <p>Quiz-Frontend-Modul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>Quiz-Manager</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('quiz_manager')) : ?>
            <?php echo do_shortcode('[quiz_manager]'); ?>
        <?php else : ?>
            <p>Quiz-Manager-Modul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>
</details>
