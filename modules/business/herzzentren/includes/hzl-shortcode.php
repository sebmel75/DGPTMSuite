<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode [herzzentren_benutzer_liste]
 * Button + AJAX-Container
 */
function hbl_herzzentren_benutzer_liste_shortcode( $atts ) {
    $ajax_url = admin_url( 'admin-ajax.php' );
    $nonce    = wp_create_nonce( 'hbl_nonce' );

    ob_start();
    ?>
    <div id="hbl-data-container"></div>
    <button id="hbl-load-button">Herzzentren und Benutzer laden</button>
    <script>
    (function($){
        var ajax = {
            url: "<?php echo esc_js( $ajax_url ); ?>",
            nonce: "<?php echo esc_js( $nonce ); ?>"
        };
        $(document).ready(function(){
            $("#hbl-load-button").on("click", function(e){
                e.preventDefault();
                $.post( ajax.url, {
                    action: "load_herzzentren_data",
                    nonce: ajax.nonce
                }, function(response){
                    $("#hbl-data-container").html(response);
                    if ( $("#hbl-herzzentren-table").length ) {
                        $("#hbl-herzzentren-table").DataTable({
                            paging: true,
                            lengthChange: true,
                            searching: true,
                            ordering: true,
                            info: true,
                            autoWidth: false,
                            language: {
                                url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/de-DE.json"
                            }
                        });
                    }
                });
            });
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'herzzentren_benutzer_liste', 'hbl_herzzentren_benutzer_liste_shortcode' );
