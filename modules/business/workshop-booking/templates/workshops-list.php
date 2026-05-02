<?php
/**
 * Template: Workshop-Karten + Buchungs-Dialog.
 *
 * @var array $events  Liste der DGfK_Events-Datensaetze
 */
if (!defined('ABSPATH')) exit;
?>
<div class="dgptm-wsb-list">
    <?php if (empty($events)) : ?>
        <p class="dgptm-wsb-empty">Aktuell sind keine Workshops oder Webinare buchbar.</p>
    <?php else : foreach ($events as $event) :
        $name     = isset($event['Name']) ? $event['Name'] : '';
        $event_id = isset($event['id'])   ? $event['id']   : '';
        $from     = isset($event['From_Date']) ? date_i18n('d.m.Y', strtotime($event['From_Date'])) : '';
        $type     = isset($event['Event_Type']) ? $event['Event_Type'] : '';
        if (empty($event_id) || empty($name)) continue;
    ?>
        <article class="dgptm-wsb-card" data-event-id="<?php echo esc_attr($event_id); ?>">
            <header>
                <span class="dgptm-wsb-card-type"><?php echo esc_html($type); ?></span>
                <h3><?php echo esc_html($name); ?></h3>
                <?php if ($from) : ?>
                    <p class="dgptm-wsb-card-date"><?php echo esc_html($from); ?></p>
                <?php endif; ?>
            </header>
            <button type="button"
                    class="dgptm-wsb-card-book"
                    data-event-id="<?php echo esc_attr($event_id); ?>"
                    data-event-name="<?php echo esc_attr($name); ?>">
                Jetzt buchen
            </button>
        </article>
    <?php endforeach; endif; ?>
</div>

<dialog class="dgptm-wsb-dialog" id="dgptm-wsb-booking-dialog">
    <form id="dgptm-wsb-booking-form">
        <input type="hidden" name="event_id" value="">
        <h3>Buchung: <span class="dgptm-wsb-dialog-event-name"></span></h3>
        <div class="dgptm-wsb-attendee">
            <label>Vorname
                <input type="text" name="first_name" required autocomplete="given-name">
            </label>
            <label>Nachname
                <input type="text" name="last_name" required autocomplete="family-name">
            </label>
            <label>E-Mail
                <input type="email" name="email" required autocomplete="email">
            </label>
        </div>
        <div class="dgptm-wsb-actions">
            <button type="button" class="dgptm-wsb-cancel">Abbrechen</button>
            <button type="submit" class="dgptm-wsb-submit">Verbindlich buchen</button>
        </div>
        <p class="dgptm-wsb-feedback" aria-live="polite"></p>
    </form>
</dialog>
