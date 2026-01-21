<?php
/**
 * Template: Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$form_page = get_option('dgptm_edugrant_form_page', '/veranstaltungen/educational-grant-der-dgptm/educational-grant-abrechnung/');
?>

<div class="wrap">
    <h1>EduGrant Einstellungen</h1>

    <form method="post" action="options.php">
        <?php settings_fields('dgptm_edugrant_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="dgptm_edugrant_form_page">Formular-Seite URL</label>
                </th>
                <td>
                    <input type="text" id="dgptm_edugrant_form_page" name="dgptm_edugrant_form_page"
                           value="<?php echo esc_attr($form_page); ?>" class="regular-text">
                    <p class="description">
                        URL der Seite mit dem Shortcode [edugrant_antragsformular].<br>
                        Wird für Links in der Benutzeransicht verwendet.
                    </p>
                </td>
            </tr>
        </table>

        <h2>Shortcodes</h2>
        <table class="widefat" style="max-width: 800px;">
            <thead>
                <tr>
                    <th>Shortcode</th>
                    <th>Beschreibung</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[edugrant_events]</code></td>
                    <td>Zeigt alle verfügbaren Veranstaltungen mit EduGrant-Budget an.</td>
                </tr>
                <tr>
                    <td><code>[meine_edugrantes]</code></td>
                    <td>
                        Zeigt die EduGrants des angemeldeten Benutzers an.<br>
                        <small>Parameter: <code>show_form_link="true|false"</code></small>
                    </td>
                </tr>
                <tr>
                    <td><code>[edugrant_antragsformular]</code></td>
                    <td>
                        Formular für neue Anträge und Dokumenten-Einreichung.<br>
                        <small>URL-Parameter: <code>event_id</code> für neue Anträge, <code>eduid</code> für Abrechnungen</small>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2>Zoho CRM Konfiguration</h2>
        <p>
            Dieses Modul nutzt die OAuth2-Konfiguration des <strong>CRM Abruf</strong> Moduls.<br>
            Stellen Sie sicher, dass das CRM Abruf Modul aktiviert und korrekt konfiguriert ist.
        </p>

        <h3>Benötigte Zoho CRM Module</h3>
        <ul>
            <li><strong>DGFK_Events</strong> - Veranstaltungen mit EduGrant-Budget</li>
            <li><strong>EduGrant</strong> - EduGrant-Anträge und Abrechnungen</li>
        </ul>

        <h3>Benötigte Felder in DGFK_Events</h3>
        <ul>
            <li>Veranstaltungsbezeichnung (Name)</li>
            <li>Von, Bis (Datum)</li>
            <li>Budget (Ja/Nein oder Zahl)</li>
            <li>Max_Anzahl_TN (Maximum Attendees)</li>
            <li>Genehmigte_EduGrant (Anzahl genehmigter Anträge)</li>
            <li>Ort</li>
            <li>Maximale_Forderung (Max. Förderung pro EduGrant)</li>
        </ul>

        <h3>Benötigte Felder in EduGrant</h3>
        <ul>
            <li>Kontakt (Lookup zu Contacts)</li>
            <li>Veranstaltung (Lookup zu DGFK_Events)</li>
            <li>Status</li>
            <li>Nummer (EduGrant-Nummer)</li>
            <li>Beantragt_am, Genehmigt_am</li>
            <li>Unterkunft, Fahrtkosten, Kilometer, Teilnahmegebuehren</li>
            <li>IBAN, Kontoinhaber</li>
            <li>Summe_Eingaben, Summe</li>
        </ul>

        <?php submit_button(); ?>
    </form>

    <h2>Test API-Verbindung</h2>
    <p>
        <button type="button" id="test-api-connection" class="button">API-Verbindung testen</button>
        <span id="api-test-result" style="margin-left: 10px;"></span>
    </p>

    <script>
    jQuery(document).ready(function($) {
        $('#test-api-connection').on('click', function() {
            var $btn = $(this);
            var $result = $('#api-test-result');

            $btn.prop('disabled', true).text('Teste...');
            $result.text('');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dgptm_edugrant_get_events',
                    nonce: '<?php echo wp_create_nonce('dgptm_edugrant_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">✓ Verbindung erfolgreich! ' + response.data.events.length + ' Events gefunden.</span>');
                    } else {
                        $result.html('<span style="color: red;">✗ Fehler: ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">✗ Verbindungsfehler</span>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('API-Verbindung testen');
                }
            });
        });
    });
    </script>
</div>
