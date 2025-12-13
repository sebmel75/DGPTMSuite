<?php
/**
 * Admin Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$oauth_success = isset($_GET['oauth_success']) && $_GET['oauth_success'] === '1';
$client_id = $options['client_id'] ?? '';
$redirect_uri = $options['redirect_uri'] ?? admin_url('admin.php?page=dgptm-mitgliedsantrag');
$has_token = !empty($options['access_token'] ?? '');
?>

<div class="wrap">
    <h1>Mitgliedsantrag - Einstellungen</h1>

    <?php if ($oauth_success): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>OAuth-Verbindung erfolgreich!</strong> Die Zoho CRM Verbindung wurde hergestellt.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>Einstellungen gespeichert.</strong></p>
        </div>
    <?php endif; ?>

    <div class="dgptm-admin-container">
        <div class="dgptm-admin-section">
            <h2>Zoho CRM OAuth-Konfiguration</h2>

            <?php if (class_exists('DGPTM_Zoho_CRM_Hardened')): ?>
                <div class="notice notice-info">
                    <p><strong>Info:</strong> Das Modul "crm-abruf" ist aktiv. Die OAuth-Verbindung von crm-abruf wird automatisch verwendet, falls dort konfiguriert.</p>
                </div>
            <?php endif; ?>

            <p>Um die Bürgen-Verifizierung und Kontakterstellung in Zoho CRM zu nutzen, müssen Sie eine OAuth-Verbindung einrichten:</p>

            <ol>
                <li>Erstellen Sie eine OAuth-App in der Zoho API Console: <a href="https://api-console.zoho.eu" target="_blank">https://api-console.zoho.eu</a></li>
                <li>Wählen Sie "Server-based Applications"</li>
                <li>Tragen Sie folgende Redirect URI ein: <code><?php echo esc_html($redirect_uri); ?></code></li>
                <li>Aktivieren Sie die Scopes: <code>ZohoCRM.modules.ALL</code>, <code>ZohoCRM.settings.ALL</code>, <code>ZohoCRM.Files.CREATE</code></li>
                <li>Kopieren Sie Client ID und Client Secret hierher</li>
            </ol>

            <form method="post" action="options.php">
                <?php settings_fields('dgptm_mitgliedsantrag_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="disable_formidable_conflicts">Formidable Konflikte verhindern</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="disable_formidable_conflicts"
                                       name="dgptm_mitgliedsantrag_options[disable_formidable_conflicts]"
                                       value="1"
                                       <?php checked(!empty($options['disable_formidable_conflicts'])); ?>>
                                <strong>Aktivieren</strong> um zu verhindern, dass Formidable Forms den [mitgliedsantrag] Shortcode überschreibt
                            </label>
                            <p class="description">
                                Empfohlen: Aktivieren Sie diese Option, wenn Formidable Forms den Shortcode kapern will.
                                Verwenden Sie trotzdem vorzugsweise <code>[dgptm-mitgliedsantrag]</code> um Konflikte zu vermeiden.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="google_maps_api_key">Google Maps API Key</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="google_maps_api_key"
                                   name="dgptm_mitgliedsantrag_options[google_maps_api_key]"
                                   value="<?php echo esc_attr($options['google_maps_api_key'] ?? ''); ?>"
                                   class="regular-text">
                            <p class="description">
                                Erforderlich für die Adressvalidierung mit Google Maps Geocoding API.
                                <a href="https://console.cloud.google.com/apis/credentials" target="_blank">API Key erstellen</a>
                                <br>Aktivieren Sie die "Geocoding API" in Ihrer Google Cloud Console.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_country_code">Standard Ländervorwahl</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="default_country_code"
                                   name="dgptm_mitgliedsantrag_options[default_country_code]"
                                   value="<?php echo esc_attr($options['default_country_code'] ?? '+49'); ?>"
                                   class="regular-text"
                                   placeholder="+49">
                            <p class="description">
                                Standard-Ländervorwahl für Telefonnummern ohne Länderkennung (z.B. +49 für Deutschland, +43 für Österreich).
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="webhook_url">Webhook URL</label>
                        </th>
                        <td>
                            <input type="url"
                                   id="webhook_url"
                                   name="dgptm_mitgliedsantrag_options[webhook_url]"
                                   value="<?php echo esc_attr($options['webhook_url'] ?? ''); ?>"
                                   class="regular-text"
                                   placeholder="https://example.com/webhook">
                            <p class="description">
                                URL des Webhooks, der nach erfolgreicher Formular-Übermittlung aufgerufen wird.
                                <br>Erhält alle Formulardaten als JSON POST-Request.
                                <br>Leer lassen, um keinen Webhook zu triggern.
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox"
                                           id="webhook_test_mode"
                                           name="dgptm_mitgliedsantrag_options[webhook_test_mode]"
                                           value="1"
                                           <?php checked(!empty($options['webhook_test_mode'])); ?>>
                                    <strong>Test-Modus aktivieren</strong> - Sendet Header <code>X-DGPTM-Test-Mode: 1</code> mit jedem Webhook
                                </label>
                            </p>
                            <?php if (!empty($options['webhook_url'])): ?>
                            <div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                                <h4 style="margin-top: 0;">Webhook testen:</h4>
                                <p style="margin-bottom: 10px;">
                                    <button type="button" class="test-webhook-btn button button-secondary" data-test-type="simple">
                                        🔗 Verbindungstest
                                    </button>
                                    <span style="color: #666; margin-left: 10px;">Sendet minimale Testdaten zur Verbindungsprüfung</span>
                                </p>
                                <p style="margin-bottom: 0;">
                                    <button type="button" class="test-webhook-btn button button-primary" data-test-type="full">
                                        📋 Vollständiger Feldtest
                                    </button>
                                    <span style="color: #666; margin-left: 10px;">Sendet alle Formularfelder mit realistischen Beispieldaten</span>
                                </p>
                            </div>
                            <div id="webhook-test-result" style="margin-top: 15px;"></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h3>Feldmapping (Erweitert)</h3>
                <p>Hier können Sie das Standard-Feldmapping zwischen Formular und Zoho CRM anpassen. JSON-Format erforderlich.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="field_mapping">CRM Feldmapping</label>
                        </th>
                        <td>
                            <?php
                            $default_mapping = [
                                'First_Name' => 'vorname',
                                'Last_Name' => 'nachname',
                                'Academic_Title' => 'akad_titel',
                                'greeting' => 'ansprache',
                                'Date_of_Birth' => 'geburtsdatum',
                                'Other_Street' => 'strasse',
                                'Other_City' => 'stadt',
                                'Other_State' => 'bundesland',
                                'Other_Zip' => 'plz',
                                'Other_Country' => 'land',
                                'Email' => 'email1',
                                'Secondary_Email' => 'email2',
                                'Third_Email' => 'email3',
                                'Phone' => 'telefon1',
                                'Work_Phone' => 'telefon2',
                                'employer_name' => 'arbeitgeber',
                                'Guarantor_Name_1' => 'buerge1_name',
                                'Guarantor_Mail_1' => 'buerge1_email',
                                'Guarantor_Status_1' => 'buerge1_id',
                                'Guarantor_Name_2' => 'buerge2_name',
                                'Guarantor_Mail_2' => 'buerge2_email',
                                'Guarantor_Status_2' => 'buerge2_id',
                                'profession' => 'studienrichtung',
                                'Freigestellt_bis' => 'studienbescheinigung_gueltig_bis',
                                'Membership_Type' => 'mitgliedsart'
                            ];
                            $current_mapping = !empty($options['field_mapping']) ? $options['field_mapping'] : json_encode($default_mapping, JSON_PRETTY_PRINT);
                            ?>
                            <textarea
                                id="field_mapping"
                                name="dgptm_mitgliedsantrag_options[field_mapping]"
                                rows="15"
                                class="large-text code"
                                style="font-family: monospace;"><?php echo esc_textarea($current_mapping); ?></textarea>
                            <p class="description">
                                <strong>JSON-Format:</strong> <code>{"CRM_Feldname": "formular_feldname"}</code><br>
                                <strong>Verfügbare Formularfelder:</strong><br>
                                <code>vorname, nachname, akad_titel, ansprache, geburtsdatum, strasse, stadt, bundesland, plz, land,<br>
                                email1, email2, email3, telefon1, telefon2, arbeitgeber,<br>
                                buerge1_name, buerge1_email, buerge1_id, buerge2_name, buerge2_email, buerge2_id,<br>
                                studienrichtung, studienbescheinigung_gueltig_bis, mitgliedsart</code>
                            </p>
                            <button type="button" class="button" onclick="document.getElementById('field_mapping').value = <?php echo esc_js(json_encode(json_encode($default_mapping, JSON_PRETTY_PRINT))); ?>;">
                                Standard wiederherstellen
                            </button>
                        </td>
                    </tr>
                </table>

                <h3>OAuth-Konfiguration</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="client_id">Zoho Client ID</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="client_id"
                                   name="dgptm_mitgliedsantrag_options[client_id]"
                                   value="<?php echo esc_attr($client_id); ?>"
                                   class="regular-text">
                            <p class="description">Für die Bürgen-Verifizierung über Zoho CRM.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="client_secret">Client Secret</label>
                        </th>
                        <td>
                            <input type="password"
                                   id="client_secret"
                                   name="dgptm_mitgliedsantrag_options[client_secret]"
                                   value="<?php echo esc_attr($options['client_secret'] ?? ''); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="redirect_uri">Redirect URI</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="redirect_uri"
                                   name="dgptm_mitgliedsantrag_options[redirect_uri]"
                                   value="<?php echo esc_attr($redirect_uri); ?>"
                                   class="regular-text"
                                   readonly>
                            <p class="description">Diese URI muss in Ihrer Zoho OAuth-App eingetragen werden.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">OAuth Status</th>
                        <td>
                            <?php if ($has_token): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <strong style="color: #46b450;">Verbunden</strong>
                                <?php if (isset($options['token_expiry'])): ?>
                                    <br>
                                    <small>Token gültig bis: <?php echo date('d.m.Y H:i', $options['token_expiry']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                                <strong style="color: #dc3232;">Nicht verbunden</strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Einstellungen speichern'); ?>
            </form>

            <?php if (!empty($client_id) && !empty($options['client_secret'])): ?>
                <hr>
                <h3>OAuth-Autorisierung</h3>
                <p>Klicken Sie auf den Button unten, um die Verbindung mit Zoho CRM zu autorisieren:</p>
                <?php
                $auth_url = 'https://accounts.zoho.eu/oauth/v2/auth?' . http_build_query([
                    'scope' => 'ZohoCRM.modules.ALL,ZohoCRM.settings.ALL,ZohoCRM.Files.CREATE',
                    'client_id' => $client_id,
                    'response_type' => 'code',
                    'access_type' => 'offline',
                    'redirect_uri' => $redirect_uri
                ]);
                ?>
                <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-admin-network" style="vertical-align: middle;"></span>
                    Mit Zoho CRM verbinden
                </a>
            <?php endif; ?>
        </div>

        <div class="dgptm-admin-section">
            <h2>Verwendung</h2>
            <p>Um das Mitgliedsantragsformular auf einer Seite anzuzeigen, verwenden Sie folgenden Shortcode:</p>
            <code>[dgptm-mitgliedsantrag]</code>
            <p style="margin-top: 10px;"><small>Alternativ: <code>[mitgliedsantrag]</code> (kann von Formidable Forms überschrieben werden)</small></p>

            <h3 style="margin-top: 30px;">🔍 Shortcode-Diagnose</h3>
            <?php
            global $shortcode_tags;
            $dgptm_shortcode_ok = isset($shortcode_tags['dgptm-mitgliedsantrag']);
            $mitgliedsantrag_exists = isset($shortcode_tags['mitgliedsantrag']);

            if ($mitgliedsantrag_exists) {
                $callback = $shortcode_tags['mitgliedsantrag'];
                $is_ours = is_array($callback) && isset($callback[0]) && get_class($callback[0]) === 'DGPTM_Mitgliedsantrag';
            } else {
                $is_ours = false;
            }
            ?>
            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Shortcode</th>
                        <th>Status</th>
                        <th>Besitzer</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[dgptm-mitgliedsantrag]</code></td>
                        <td>
                            <?php if ($dgptm_shortcode_ok): ?>
                                <span style="color: #46b450;">✓ Registriert</span>
                            <?php else: ?>
                                <span style="color: #dc3232;">✗ Nicht registriert</span>
                            <?php endif; ?>
                        </td>
                        <td>DGPTM Mitgliedsantrag</td>
                    </tr>
                    <tr>
                        <td><code>[mitgliedsantrag]</code></td>
                        <td>
                            <?php if ($mitgliedsantrag_exists): ?>
                                <?php if ($is_ours): ?>
                                    <span style="color: #46b450;">✓ Registriert (Unseres)</span>
                                <?php else: ?>
                                    <span style="color: #f0ad4e;">⚠ Registriert (Überschrieben)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #dc3232;">✗ Nicht registriert</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($mitgliedsantrag_exists && !$is_ours) {
                                if (is_array($callback) && is_object($callback[0])) {
                                    echo esc_html(get_class($callback[0]));
                                } elseif (is_string($callback)) {
                                    echo esc_html($callback);
                                } else {
                                    echo 'Unbekannt';
                                }
                            } else {
                                echo 'DGPTM Mitgliedsantrag';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php if (shortcode_exists('formidable')): ?>
                    <tr>
                        <td><code>[formidable]</code></td>
                        <td><span style="color: #46b450;">✓ Registriert</span></td>
                        <td>Formidable Forms</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($mitgliedsantrag_exists && !$is_ours): ?>
                <div class="notice notice-warning inline" style="margin-top: 15px;">
                    <p><strong>Warnung:</strong> Der Shortcode <code>[mitgliedsantrag]</code> wurde von einem anderen Plugin überschrieben!</p>
                    <p><strong>Lösung:</strong></p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>Aktivieren Sie oben die Option "Formidable Konflikte verhindern"</li>
                        <li>ODER verwenden Sie <code>[dgptm-mitgliedsantrag]</code> stattdessen (empfohlen)</li>
                    </ul>
                </div>
            <?php endif; ?>

            <h3 style="margin-top: 30px;">Funktionen</h3>
            <ul>
                <li><strong>Multi-Step-Formular:</strong> Übersichtliche Aufteilung in 5 Schritte</li>
                <li><strong>Bürgen-Verifizierung:</strong> Automatische Überprüfung der Bürgen über Zoho CRM</li>
                <li><strong>Adressvalidierung:</strong> Plausibilitätsprüfung der eingegebenen Adresse mit Google Maps</li>
                <li><strong>E-Mail-Validierung:</strong> Überprüfung der E-Mail-Adressen (Format und Domain)</li>
                <li><strong>Studenten-Workflow:</strong> Automatische Anforderung der Studienbescheinigung</li>
                <li><strong>CRM-Integration:</strong> Automatische Anlage oder Aktualisierung des Kontakts in Zoho CRM</li>
                <li><strong>Webhook-Integration:</strong> Alle Formulardaten werden per POST an Ihren Webhook gesendet</li>
                <li><strong>Test-Modus:</strong> Webhooks können im Test-Modus ausgelöst werden</li>
            </ul>

            <h3 style="margin-top: 30px;">🔗 Webhook-Integration</h3>
            <p>Nach erfolgreicher Formular-Übermittlung werden alle Daten an Ihren konfigurierten Webhook gesendet.</p>

            <h4>Webhook-Payload Format:</h4>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 3px; overflow-x: auto;">{
  "event": "mitgliedsantrag_submitted",
  "timestamp": "2025-12-06 15:30:45",
  "test_mode": false,
  "contact_id": "123456789",
  "form_data": {
    "vorname": "Max",
    "nachname": "Mustermann",
    "email1": "max@example.com",
    "telefon1": "+491234567890",
    "telefon2": "+491234567891",
    "strasse": "Musterstraße 123",
    "plz": "12345",
    "stadt": "Musterstadt",
    ... (alle weiteren Formularfelder)
  },
  "studienbescheinigung_url": "https://example.com/wp-content/uploads/..."
}</pre>

            <h4>HTTP Headers:</h4>
            <ul>
                <li><code>Content-Type: application/json</code></li>
                <li><code>X-DGPTM-Event: mitgliedsantrag_submitted</code> oder <code>test_webhook</code></li>
                <li><code>X-DGPTM-Test-Mode: 0</code> oder <code>1</code> (wenn Test-Modus aktiv)</li>
            </ul>

            <h4>Webhook-Tests:</h4>
            <p><strong>🔗 Verbindungstest:</strong> Sendet minimale Testdaten zur Überprüfung, ob Ihr Webhook-Endpunkt erreichbar ist.</p>
            <p><strong>📋 Vollständiger Feldtest:</strong> Sendet ALLE Formularfelder mit realistischen Beispieldaten (wie "Dr. med. Maximilian Mustermann").
            Perfekt zum Testen Ihrer Feldzuordnungen und Datenverarbeitung.</p>

            <h4>Test-Modus in Produktivumgebung:</h4>
            <p>Wenn Sie die Checkbox "Test-Modus aktivieren" aktivieren, werden ALLE Webhooks (auch echte Formulareinsendungen)
            mit dem Header <code>X-DGPTM-Test-Mode: 1</code> gesendet.
            So können Sie auf Ihrer Webhook-Empfängerseite zwischen Produktivdaten und Testdaten unterscheiden.</p>

            <h3 style="margin-top: 30px;">Zoho CRM Felder</h3>
            <p>Folgende Felder werden standardmäßig in Zoho CRM befüllt (anpassbar über Feldmapping):</p>
            <ul style="columns: 2;">
                <li>First_Name / Last_Name</li>
                <li>Academic_Title</li>
                <li>greeting (Ansprache) - <strong>Pflichtfeld</strong></li>
                <li>Date_of_Birth - <strong>Pflichtfeld</strong></li>
                <li>Other_Street / Other_City / Other_Zip / Other_State / Other_Country</li>
                <li>Email / Secondary_Email / Third_Email</li>
                <li>Phone / Work_Phone - <strong>Normalisiert mit Länderkennung</strong></li>
                <li>employer_name</li>
                <li>Guarantor_Name_1 / Guarantor_Mail_1 / Guarantor_Status_1</li>
                <li>Guarantor_Name_2 / Guarantor_Mail_2 / Guarantor_Status_2</li>
                <li>profession (Studienrichtung)</li>
                <li>Freigestellt_bis (Studienbescheinigung gültig bis)</li>
                <li>Membership_Type</li>
                <li>Bemerkung</li>
            </ul>

            <h3 style="margin-top: 30px;">📱 Telefonnummern-Normalisierung</h3>
            <p>Alle eingegebenen Telefonnummern werden automatisch in das internationale Format konvertiert:</p>
            <ul>
                <li><strong>Eingabe:</strong> <code>0151 12345678</code> → <strong>Ausgabe:</strong> <code>+4915112345678</code></li>
                <li><strong>Eingabe:</strong> <code>+43 664 1234567</code> → <strong>Ausgabe:</strong> <code>+436641234567</code> (bereits international)</li>
                <li>Die Standard-Ländervorwahl kann in den Einstellungen angepasst werden (Standard: +49)</li>
            </ul>
        </div>
    </div>
</div>

<script>
// Inline debug script to check if page loads correctly
console.log('Admin settings page loaded');
console.log('jQuery available:', typeof jQuery !== 'undefined');
console.log('dgptmMitgliedsantragAdmin available:', typeof dgptmMitgliedsantragAdmin !== 'undefined');
jQuery(document).ready(function($) {
    console.log('Document ready');
    console.log('Test buttons found:', $('.test-webhook-btn').length);
});
</script>

<style>
.dgptm-admin-container {
    max-width: 1200px;
}

.dgptm-admin-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.dgptm-admin-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.dgptm-admin-section code {
    background: #f0f0f1;
    padding: 3px 8px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
}

.dgptm-admin-section ul {
    line-height: 1.8;
}
</style>
