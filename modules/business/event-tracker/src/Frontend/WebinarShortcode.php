<?php
/**
 * Event Webinar Shortcode
 *
 * [event_webinar] — Zeigt Start/Join/Stop fuer ein Webinar.
 * [event_webinar id="123"] — Fuer ein bestimmtes Event.
 * Ohne id: zeigt das naechste bevorstehende oder aktuell laufende Webinar.
 *
 * Host/Presenter: Start-Button, Co-Host hinzufuegen, Status
 * Teilnehmer: Join-Button (nur waehrend Event-Zeit), Countdown vorher
 * Nach Event: Recording-Link
 *
 * @package EventTracker\Frontend
 * @since 2.3.0
 */

namespace EventTracker\Frontend;

use EventTracker\Core\Constants;
use EventTracker\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WebinarShortcode {

    public function __construct() {
        add_shortcode( 'event_webinar', [ $this, 'render' ] );
    }

    /**
     * Render Webinar Shortcode
     */
    public function render( $atts ) {
        $atts = shortcode_atts( [
            'id' => 0,
        ], $atts, 'event_webinar' );

        $event_id = absint( $atts['id'] );

        // Ohne ID: naechstes bevorstehendes oder laufendes Webinar finden
        if ( ! $event_id ) {
            $event_id = $this->find_current_webinar();
        }

        if ( ! $event_id ) {
            return '<div class="et-webinar-wrap"><p class="et-webinar-empty">Kein aktives Webinar gefunden.</p></div>';
        }

        $post = get_post( $event_id );
        if ( ! $post || $post->post_type !== Constants::CPT ) {
            return '<div class="et-webinar-wrap"><p class="et-webinar-empty">Webinar nicht gefunden.</p></div>';
        }

        // Daten laden
        $title      = $post->post_title;
        $start_ts   = (int) get_post_meta( $event_id, Constants::META_START_TS, true );
        $end_ts     = (int) get_post_meta( $event_id, Constants::META_END_TS, true );
        $zm_key     = get_post_meta( $event_id, Constants::META_ZM_KEY, true );
        $start_url  = get_post_meta( $event_id, Constants::META_ZM_START_URL, true );
        $join_url   = get_post_meta( $event_id, Constants::META_ZM_JOIN_URL, true );
        $rec_url    = get_post_meta( $event_id, Constants::META_ZM_RECORDING_URL, true );
        $zm_status  = get_post_meta( $event_id, Constants::META_ZM_STATUS, true );
        $cohosts    = get_post_meta( $event_id, Constants::META_ZM_COHOSTS, true );

        $now = time();
        $is_host = Helpers::user_has_access();
        $is_before = $now < $start_ts;
        $is_during = $now >= $start_ts && $now <= $end_ts;
        $is_after  = $now > $end_ts;

        // Auch zusaetzliche Daten pruefen (Multi-Day)
        $additional = get_post_meta( $event_id, Constants::META_ADDITIONAL_DATES, true );
        if ( is_array( $additional ) ) {
            foreach ( $additional as $range ) {
                $rs = isset( $range['start'] ) ? (int) $range['start'] : 0;
                $re = isset( $range['end'] ) ? (int) $range['end'] : 0;
                if ( $rs && $re && $now >= $rs && $now <= $re ) {
                    $is_during = true;
                    $is_before = false;
                    $is_after = false;
                    break;
                }
                if ( $rs && $now < $rs ) {
                    $is_before = true;
                    $is_after = false;
                }
            }
        }

        $tz = wp_timezone();

        ob_start();
        ?>
        <div class="et-webinar-wrap" data-event-id="<?php echo esc_attr( $event_id ); ?>">

            <div class="et-webinar-header">
                <h3 class="et-webinar-title"><?php echo esc_html( $title ); ?></h3>
                <div class="et-webinar-meta">
                    <?php
                    $start_dt = ( new \DateTimeImmutable( '@' . $start_ts ) )->setTimezone( $tz );
                    $end_dt   = ( new \DateTimeImmutable( '@' . $end_ts ) )->setTimezone( $tz );
                    echo esc_html( $start_dt->format( 'd.m.Y H:i' ) . ' – ' . $end_dt->format( 'H:i' ) );
                    ?>
                    <?php if ( $zm_key ) : ?>
                        <span class="et-webinar-badge et-webinar-badge--<?php echo esc_attr( $zm_status ?: 'created' ); ?>">
                            <?php echo esc_html( $this->status_label( $zm_status ) ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( ! $zm_key && ! $is_host ) : ?>
                <!-- Kein Webinar erstellt, kein Host -->
                <p class="et-webinar-info">Fuer diese Veranstaltung wurde noch kein Webinar eingerichtet.</p>

            <?php elseif ( $is_before ) : ?>
                <!-- VOR dem Event -->
                <div class="et-webinar-countdown" data-start="<?php echo esc_attr( $start_ts ); ?>">
                    <p>Das Webinar beginnt in <strong class="et-countdown-timer"></strong></p>
                </div>

                <?php if ( $is_host && $zm_key ) : ?>
                    <div class="et-webinar-actions">
                        <a href="<?php echo esc_url( $start_url ); ?>" target="_blank" rel="noopener" class="et-webinar-btn et-webinar-btn--start">
                            Webinar starten (Host)
                        </a>
                    </div>
                <?php endif; ?>

            <?php elseif ( $is_during ) : ?>
                <!-- WAEHREND des Events -->
                <div class="et-webinar-live">
                    <span class="et-webinar-live-dot"></span> Live
                </div>

                <div class="et-webinar-actions">
                    <?php if ( $join_url ) : ?>
                        <a href="<?php echo esc_url( $join_url ); ?>" target="_blank" rel="noopener" class="et-webinar-btn et-webinar-btn--join">
                            Jetzt teilnehmen
                        </a>
                    <?php endif; ?>

                    <?php if ( $is_host && $start_url ) : ?>
                        <a href="<?php echo esc_url( $start_url ); ?>" target="_blank" rel="noopener" class="et-webinar-btn et-webinar-btn--start">
                            Als Host beitreten
                        </a>
                    <?php endif; ?>
                </div>

            <?php elseif ( $is_after ) : ?>
                <!-- NACH dem Event -->
                <?php if ( $rec_url ) : ?>
                    <div class="et-webinar-recording">
                        <a href="<?php echo esc_url( $rec_url ); ?>" target="_blank" rel="noopener" class="et-webinar-btn et-webinar-btn--recording">
                            Aufzeichnung ansehen
                        </a>
                    </div>
                <?php else : ?>
                    <p class="et-webinar-info">Das Webinar ist beendet. Die Aufzeichnung wird in Kuerze verfuegbar sein.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $is_host && $zm_key ) : ?>
                <!-- Host-Bereich: Co-Hosts -->
                <div class="et-webinar-host-panel">
                    <details>
                        <summary>Co-Presenter verwalten</summary>
                        <div class="et-webinar-cohosts">
                            <?php if ( $cohosts && is_array( $cohosts ) ) : ?>
                                <ul class="et-cohost-list">
                                    <?php foreach ( $cohosts as $ch ) : ?>
                                        <li><?php echo esc_html( $ch ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <div class="et-cohost-add">
                                <input type="email" id="et-cohost-email-<?php echo $event_id; ?>" placeholder="E-Mail des Co-Presenters" class="et-cohost-input">
                                <button type="button" class="et-webinar-btn et-webinar-btn--small et-add-cohost-btn" data-event-id="<?php echo esc_attr( $event_id ); ?>">
                                    Hinzufuegen
                                </button>
                            </div>
                        </div>
                    </details>
                </div>
            <?php endif; ?>

        </div>

        <style>
        .et-webinar-wrap { max-width: 600px; }
        .et-webinar-header { margin-bottom: 16px; }
        .et-webinar-title { margin: 0 0 4px; font-size: 18px; font-weight: 600; color: #1d2327; }
        .et-webinar-meta { font-size: 13px; color: #888; display: flex; align-items: center; gap: 8px; }
        .et-webinar-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .et-webinar-badge--created { background: #e8f0f8; color: #005792; }
        .et-webinar-badge--started { background: #e7f5e7; color: #2e7d32; }
        .et-webinar-badge--ended { background: #f0f0f1; color: #666; }

        .et-webinar-countdown { padding: 16px; background: #f8f9fa; border-radius: 4px; text-align: center; margin-bottom: 12px; }
        .et-webinar-live { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; color: #dc3232; margin-bottom: 12px; }
        .et-webinar-live-dot { width: 10px; height: 10px; border-radius: 50%; background: #dc3232; animation: et-pulse 1.5s infinite; }
        @keyframes et-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        .et-webinar-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
        .et-webinar-btn { display: inline-block; padding: 8px 16px; border: none; border-radius: 4px; font-size: 13px; font-weight: 500; text-decoration: none; cursor: pointer; transition: background .15s; }
        .et-webinar-btn--join { background: #0073aa; color: #fff; }
        .et-webinar-btn--join:hover { background: #005d8c; color: #fff; }
        .et-webinar-btn--start { background: #46b450; color: #fff; }
        .et-webinar-btn--start:hover { background: #389e3e; color: #fff; }
        .et-webinar-btn--recording { background: #8b5cf6; color: #fff; }
        .et-webinar-btn--recording:hover { background: #7c3aed; color: #fff; }
        .et-webinar-btn--small { padding: 4px 10px; font-size: 12px; background: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .et-webinar-btn--small:hover { background: #005d8c; }

        .et-webinar-info { color: #888; font-size: 13px; font-style: italic; }
        .et-webinar-empty { color: #888; font-size: 13px; }

        .et-webinar-host-panel { margin-top: 16px; border-top: 1px solid #eee; padding-top: 12px; }
        .et-webinar-host-panel summary { font-size: 13px; font-weight: 500; cursor: pointer; color: #0073aa; }
        .et-cohost-list { margin: 8px 0; padding-left: 20px; font-size: 13px; }
        .et-cohost-add { display: flex; gap: 6px; margin-top: 8px; }
        .et-cohost-input { flex: 1; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; }

        /* Dashboard-filigran */
        .dgptm-dash .et-webinar-wrap { max-width: 100%; }
        .dgptm-dash .et-webinar-title { font-size: 15px; }
        .dgptm-dash .et-webinar-btn { padding: 4px 10px; font-size: 12px; }
        </style>

        <script>
        jQuery(function($){
            // Countdown
            $('.et-webinar-countdown').each(function(){
                var $el = $(this);
                var startTs = parseInt($el.data('start')) * 1000;
                var $timer = $el.find('.et-countdown-timer');

                function update() {
                    var diff = startTs - Date.now();
                    if (diff <= 0) { location.reload(); return; }
                    var h = Math.floor(diff / 3600000);
                    var m = Math.floor((diff % 3600000) / 60000);
                    var s = Math.floor((diff % 60000) / 1000);
                    $timer.text((h > 0 ? h + 'h ' : '') + m + 'min ' + s + 's');
                }
                update();
                setInterval(update, 1000);
            });

            // Co-Host hinzufuegen
            $(document).on('click', '.et-add-cohost-btn', function(){
                var eventId = $(this).data('event-id');
                var $input = $('#et-cohost-email-' + eventId);
                var email = $input.val().trim();
                if (!email) return;

                var $btn = $(this);
                $btn.prop('disabled', true).text('...');

                $.post(typeof eventTrackerData !== 'undefined' ? eventTrackerData.ajaxUrl : ajaxurl, {
                    action: 'et_zm_add_cohosts',
                    nonce: typeof eventTrackerData !== 'undefined' ? eventTrackerData.nonce : '',
                    event_id: eventId,
                    emails: email
                }, function(res){
                    $btn.prop('disabled', false).text('Hinzufuegen');
                    if (res.success) {
                        $input.val('');
                        var $list = $btn.closest('.et-webinar-cohosts').find('.et-cohost-list');
                        if (!$list.length) {
                            $btn.closest('.et-webinar-cohosts').prepend('<ul class="et-cohost-list"></ul>');
                            $list = $btn.closest('.et-webinar-cohosts').find('.et-cohost-list');
                        }
                        $list.append('<li>' + email + '</li>');
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Fehler');
                    }
                });
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Findet das naechste bevorstehende oder laufende Webinar
     */
    private function find_current_webinar() {
        $now = time();

        $events = get_posts( [
            'post_type'      => Constants::CPT,
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            'meta_key'       => Constants::META_START_TS,
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => Constants::META_ZM_KEY,
                    'compare' => 'EXISTS',
                ],
            ],
        ] );

        // Erst laufende suchen
        foreach ( $events as $event ) {
            $start = (int) get_post_meta( $event->ID, Constants::META_START_TS, true );
            $end   = (int) get_post_meta( $event->ID, Constants::META_END_TS, true );
            if ( $now >= $start && $now <= $end ) {
                return $event->ID;
            }
        }

        // Dann naechstes bevorstehendes
        foreach ( $events as $event ) {
            $start = (int) get_post_meta( $event->ID, Constants::META_START_TS, true );
            if ( $start > $now ) {
                return $event->ID;
            }
        }

        // Letztes vergangenes (fuer Recording)
        $past = get_posts( [
            'post_type'      => Constants::CPT,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_key'       => Constants::META_START_TS,
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => Constants::META_ZM_KEY,
                    'compare' => 'EXISTS',
                ],
            ],
        ] );

        return ! empty( $past ) ? $past[0]->ID : 0;
    }

    /**
     * Status-Label fuer Anzeige
     */
    private function status_label( $status ) {
        $labels = [
            'created' => 'Geplant',
            'started' => 'Live',
            'ended'   => 'Beendet',
            'active'  => 'Aktiv',
        ];
        return $labels[ $status ] ?? ucfirst( $status ?: 'Geplant' );
    }
}
