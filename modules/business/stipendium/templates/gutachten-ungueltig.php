<?php
/**
 * Template: Token ungueltig oder abgelaufen.
 *
 * Variablen:
 * @var string $error_message  Fehlermeldung (optional, von WP_Error)
 */
if (!defined('ABSPATH')) exit;
?>

<div class="dgptm-gutachten-wrap">
    <div class="dgptm-gutachten-header">
        <div class="dgptm-gutachten-header-bar">
            <span class="dgptm-gutachten-logo">DGPTM Stipendium</span>
            <span class="dgptm-gutachten-header-sub">Begutachtung</span>
        </div>
    </div>

    <div class="dgptm-gutachten-ungueltig">
        <div class="dgptm-gutachten-ungueltig-icon">&#9888;</div>
        <h2>Link nicht gueltig</h2>

        <p>
            <?php if (!empty($error_message)) : ?>
                <?php echo esc_html($error_message); ?>
            <?php else : ?>
                Dieser Link ist nicht mehr gueltig oder abgelaufen.
            <?php endif; ?>
        </p>

        <p>
            Bitte wenden Sie sich an die Geschaeftsstelle der DGPTM:
            <a href="mailto:geschaeftsstelle@dgptm.de">geschaeftsstelle@dgptm.de</a>
        </p>
    </div>
</div>
