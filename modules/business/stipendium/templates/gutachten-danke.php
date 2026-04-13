<?php
/**
 * Template: Danke-Seite nach abgeschlossenem Gutachten.
 *
 * Variablen:
 * @var array  $token_data     Token-Daten
 * @var array  $bewertung_data Bewertungsdaten
 * @var array  $gesamtscore    Score-Details
 * @var string $completed_at   Abschluss-Zeitpunkt
 * @var string $bewerber_name  Name des Bewerbers
 */
if (!defined('ABSPATH')) exit;

$score_wert  = is_array($gesamtscore) ? ($gesamtscore['gesamt'] ?? 0) : (float) $gesamtscore;
$score_fmt   = number_format($score_wert, 2, ',', '');
$punkte_fmt  = number_format($score_wert * 10, 1, ',', '');
$datum_fmt   = $completed_at ? date_i18n('d.m.Y, H:i', strtotime($completed_at)) : '';
?>

<div class="dgptm-gutachten-wrap">
    <div class="dgptm-gutachten-header">
        <div class="dgptm-gutachten-header-bar">
            <span class="dgptm-gutachten-logo">DGPTM Stipendium</span>
            <span class="dgptm-gutachten-header-sub">Begutachtung</span>
        </div>
    </div>

    <div class="dgptm-gutachten-danke">
        <div class="dgptm-gutachten-danke-icon">&#10003;</div>
        <h2>Vielen Dank fuer Ihr Gutachten!</h2>

        <p>
            Ihre Bewertung fuer <strong><?php echo esc_html($bewerber_name); ?></strong>
            wurde erfolgreich uebermittelt.
        </p>

        <div class="dgptm-gutachten-danke-details">
            <table>
                <tr>
                    <td>Ihr Gesamtscore:</td>
                    <td><strong><?php echo $score_fmt; ?> / 10</strong> (<?php echo $punkte_fmt; ?> Punkte)</td>
                </tr>
                <?php if ($datum_fmt) : ?>
                <tr>
                    <td>Abgegeben am:</td>
                    <td><?php echo esc_html($datum_fmt); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <p>
            Der Vorsitzende des Stipendiumsrats wurde automatisch benachrichtigt.
        </p>
        <p class="dgptm-gutachten-danke-kontakt">
            Bei Rueckfragen wenden Sie sich bitte an die Geschaeftsstelle:
            <a href="mailto:geschaeftsstelle@dgptm.de">geschaeftsstelle@dgptm.de</a>
        </p>
    </div>
</div>
