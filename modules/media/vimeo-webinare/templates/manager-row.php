<?php
/**
 * Template: Eine Zeile in der Manager-Tabelle.
 * Variablen: $w (Webinar-Array), $s (Stats-Array)
 */
if (!defined('ABSPATH')) exit;

$player_url = home_url('/wissen/webinar/' . $w['id']);
$date_raw = $w['webinar_date'] ?? '';
$date_display = '';
if ($date_raw !== '') {
    $ts = strtotime($date_raw);
    if ($ts) $date_display = date_i18n('d.m.Y', $ts);
}
?>
<tr class="dgptm-vw-mgr-row"
    data-id="<?php echo esc_attr($w['id']); ?>"
    data-title="<?php echo esc_attr(strtolower($w['title'])); ?>"
    data-description="<?php echo esc_attr($w['description']); ?>"
    data-vnr="<?php echo esc_attr($w['vnr']); ?>"
    data-webinar-date="<?php echo esc_attr($date_raw); ?>">
    <td class="dgptm-vw-cell-title"><strong><?php echo esc_html($w['title']); ?></strong></td>
    <td data-label="Datum"><?php echo $date_display !== '' ? esc_html($date_display) : '<span style="color:var(--dd-muted);">–</span>'; ?></td>
    <td data-label="Vimeo-ID"><?php echo esc_html($w['vimeo_id']); ?></td>
    <td data-label="EBCP-Punkte"><?php echo esc_html(number_format_i18n($w['ebcp_points'], 1)); ?></td>
    <td data-label="Erforderlich"><?php echo esc_html($w['completion_percentage']); ?>%</td>
    <td data-label="Abgeschlossen"><?php echo esc_html($s['completed']); ?></td>
    <td class="dgptm-vw-cell-actions">
        <button type="button" class="dgptm-vw-btn-icon dgptm-vw-edit" data-id="<?php echo esc_attr($w['id']); ?>" title="Bearbeiten">
            <span class="dashicons dashicons-edit"></span>
        </button>
        <a href="<?php echo esc_url($player_url); ?>" class="dgptm-vw-btn-icon" title="Ansehen" target="_blank" rel="noopener">
            <span class="dashicons dashicons-visibility"></span>
        </a>
        <button type="button" class="dgptm-vw-btn-icon dgptm-vw-delete" data-id="<?php echo esc_attr($w['id']); ?>" title="In Papierkorb">
            <span class="dashicons dashicons-trash"></span>
        </button>
    </td>
</tr>
