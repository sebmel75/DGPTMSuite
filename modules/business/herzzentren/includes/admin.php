<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* --------------------------------------------------------
 * 2) Reset-Button in den Benutzereinstellungen
 * -------------------------------------------------------- */
add_action( 'show_user_profile', 'add_reset_button_to_user_profile' );
add_action( 'edit_user_profile', 'add_reset_button_to_user_profile' );
function add_reset_button_to_user_profile( $user ) {
    if ( current_user_can( 'manage_options' ) ) {
        ?>
        <h3>Herzzentrum Berechtigungen</h3>
        <table class="form-table">
            <tr>
                <th><label for="reset_herzzentrum">Zugewiesene Herzzentren zurücksetzen</label></th>
                <td>
                    <button id="reset-herzzentrum-button" class="button">Zurücksetzen</button>
                </td>
            </tr>
        </table>
        <script>
            document.getElementById("reset-herzzentrum-button").addEventListener("click", function(e) {
                e.preventDefault();
                if (confirm("Möchten Sie die zugewiesenen Herzzentren wirklich zurücksetzen?")) {
                    fetch(ajaxurl, {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: new URLSearchParams({
                            action: "reset_herzzentrum",
                            user_id: <?php echo esc_js( $user->ID ); ?>,
                            security: "<?php echo wp_create_nonce( 'reset_herzzentrum_nonce' ); ?>"
                        })
                    }).then(response => response.json()).then(data => {
                        if (data.success) {
                            alert("Die zugewiesenen Herzzentren wurden zurückgesetzt.");
                            location.reload();
                        } else {
                            alert("Fehler: " + data.data);
                        }
                    });
                }
            });
        </script>
        <?php
    }
}

add_action( 'wp_ajax_reset_herzzentrum', 'reset_herzzentrum_field' );
function reset_herzzentrum_field() {
    check_ajax_referer( 'reset_herzzentrum_nonce', 'security' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Nicht berechtigt', 403 );
    }
    $user_id = intval( $_POST['user_id'] );
    delete_user_meta( $user_id, 'zugewiesenes_herzzentrum' );
    wp_send_json_success( 'Die zugewiesenen Herzzentren wurden zurückgesetzt.' );
}
