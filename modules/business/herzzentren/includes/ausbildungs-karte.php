<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode: [herzzentren_ausbildungs_karte height="520px" zoom="6"]
 * Zeigt nur Herzzentren mit freien Ausbildungsplätzen (ACF: freieausbildung > 0).
 * Hover = Tooltip mit Bemerkung (ACF: ausbildungbemerkung).
 */
function hzb_shortcode_ausbildungs_karte( $atts ) {
    $atts = shortcode_atts( array(
        'height' => '520px',
        'zoom'   => 6,
    ), $atts, 'herzzentren_ausbildungs_karte' );

    // Assets laden (Leaflet liegt bereits im Plugin /assets)
    wp_enqueue_style( 'leaflet', plugins_url( '../assets/leaflet.css', __FILE__ ), array(), '1.9.4' );
    wp_enqueue_script( 'leaflet', plugins_url( '../assets/leaflet.js', __FILE__ ), array(), '1.9.4', true );

    // Query: nur Herzzentren mit freieausbildung > 0
    $q = new WP_Query( array(
        'post_type'      => 'herzzentrum',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => 'freieausbildung',
                'value'   => 0,
                'type'    => 'NUMERIC',
                'compare' => '>',
            ),
        ),
        'no_found_rows'  => true,
        'fields'         => 'ids',
    ) );

    $items = array();
    if ( $q->have_posts() ) {
        foreach ( $q->posts as $hz_id ) {
            $lat  = get_field( 'map-marker-latitude',  $hz_id );
            $lng  = get_field( 'map-marker-longitude', $hz_id );
            if ( $lat === '' || $lng === '' || $lat === null || $lng === null ) {
                // Fallback: ein kombiniertes Feld, falls vorhanden
                $latlng = get_field( 'latitudelongitude', $hz_id );
                if ( is_string( $latlng ) && strpos( $latlng, ',' ) !== false ) {
                    list( $lat, $lng ) = array_map( 'trim', explode( ',', $latlng ) );
                }
            }
            $lat = is_numeric( $lat ) ? floatval( $lat ) : null;
            $lng = is_numeric( $lng ) ? floatval( $lng ) : null;
            if ( $lat === null || $lng === null ) {
                continue; // Ohne Koordinaten kein Marker
            }

            $title     = get_the_title( $hz_id );
            $anzahl    = intval( get_field( 'freieausbildung', $hz_id ) );
            $bemerkung = (string) get_field( 'ausbildungbemerkung', $hz_id );
            $url       = get_permalink( $hz_id );

            $items[] = array(
                'id'        => $hz_id,
                'title'     => $title,
                'anzahl'    => $anzahl,
                'bemerkung' => $bemerkung,
                'lat'       => $lat,
                'lng'       => $lng,
                'url'       => $url,
            );
        }
    }

    $map_id  = 'hzb-ausbildungs-karte-' . wp_generate_uuid4();
    $height  = esc_attr( $atts['height'] );
    $zoom    = intval( $atts['zoom'] );
    $markers = wp_json_encode( $items );

    ob_start();
    ?>
    <div id="<?php echo esc_attr( $map_id ); ?>" style="width:100%;height:<?php echo $height; ?>;border-radius:10px;overflow:hidden;"></div>
    <script>
    (function(){
        function initMap(){
            if (typeof L === 'undefined') { setTimeout(initMap, 100); return; }

            var map = L.map('<?php echo esc_js( $map_id ); ?>');
            var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            var markers = <?php echo $markers ? $markers : '[]'; ?>;
            var group = [];
            markers.forEach(function(m){
                var popupHtml = '<div style="min-width:220px;">'
                              + '<strong>' + (m.title||'') + '</strong><br>'
                              + 'Freie Ausbildungsplätze: ' + (m.anzahl||0) + '<br>'
                              + (m.bemerkung ? '<em>' + m.bemerkung.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</em><br>' : '')
                              + (m.url ? '<a href="'+m.url+'">Zum Herzzentrum</a>' : '')
                              + '</div>';

                var marker = L.marker([m.lat, m.lng]).addTo(map);
                marker.bindTooltip((m.bemerkung||'').length ? m.bemerkung : 'Freie Plätze: ' + (m.anzahl||0), {direction:'top'});
                marker.bindPopup(popupHtml, {maxWidth: 320});
                group.push(marker);
            });

            if (group.length){
                var g = L.featureGroup(group);
                map.fitBounds(g.getBounds().pad(0.2));
                if (!map._loaded) map.setZoom(<?php echo $zoom; ?>);
            } else {
                map.setView([51.163, 10.447], <?php echo $zoom; ?>); // Deutschland
            }
        }
        initMap();
    }());
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'herzzentren_ausbildungs_karte', 'hzb_shortcode_ausbildungs_karte' );
