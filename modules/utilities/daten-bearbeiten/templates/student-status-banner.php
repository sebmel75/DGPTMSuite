<?php
/**
 * Template for Student Status Warning Banner
 *
 * Displays in Q4 when student status is about to expire
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_year = date('Y');
?>

<div class="dgptm-student-status-banner">
    <div class="banner-content">
        <div class="banner-icon">⚠️</div>
        <div class="banner-message">
            <strong>Studierendenstatus endet Ende <?php echo esc_html($current_year); ?></strong>
            <p>Bitte reichen Sie Ihre aktuelle Studienbescheinigung ein, um den Studierendenstatus zu erneuern.</p>
        </div>
        <div class="banner-action">
            <button type="button" class="dgptm-banner-btn" id="dgptm-open-studistatus-accordion">
                Jetzt erneuern
            </button>
        </div>
    </div>
</div>
