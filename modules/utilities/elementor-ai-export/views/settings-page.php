<?php
if (!defined('ABSPATH')) {
    exit;
}

$api_key = get_option('elementor_ai_export_claude_api_key', '');
?>
<div class="wrap elementor-ai-export-settings-wrap">
    <h1>Elementor AI Export - Einstellungen</h1>

    <p class="description">
        Konfigurieren Sie die Claude API-Integration für automatische Seitengestaltung.
    </p>

    <div class="settings-container">
        <div class="card">
            <h2>Claude API Konfiguration</h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="claude-api-key">API Key</label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="claude-api-key"
                            class="regular-text"
                            value="<?php echo esc_attr($api_key); ?>"
                            placeholder="sk-ant-..."
                        >
                        <p class="description">
                            Ihr Claude API Key von <a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic Console</a>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button id="save-settings-btn" class="button button-primary">
                    Einstellungen speichern
                </button>
                <button id="test-api-btn" class="button" <?php echo empty($api_key) ? 'disabled' : ''; ?>>
                    Verbindung testen
                </button>
            </p>

            <div id="settings-message" class="settings-message" style="display:none;"></div>
        </div>

        <div class="card">
            <h2>So erhalten Sie einen API Key</h2>

            <ol>
                <li>Gehen Sie zu <a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic Console</a></li>
                <li>Melden Sie sich an oder erstellen Sie ein Konto</li>
                <li>Navigieren Sie zu "API Keys"</li>
                <li>Klicken Sie auf "Create Key"</li>
                <li>Kopieren Sie den generierten Key</li>
                <li>Fügen Sie ihn oben ein</li>
            </ol>

            <h3>Kosten</h3>
            <p>
                Die Claude API wird nach Nutzung abgerechnet:
            </p>
            <ul>
                <li><strong>Claude 3.5 Sonnet</strong> (empfohlen): ~$3 pro 1M Input-Tokens, ~$15 pro 1M Output-Tokens</li>
                <li>Eine durchschnittliche Elementor-Seite: ca. 5.000-20.000 Tokens</li>
                <li>Geschätzte Kosten pro Redesign: $0.05 - $0.30</li>
            </ul>

            <p>
                <a href="https://www.anthropic.com/pricing" target="_blank">Aktuelle Preise ansehen</a>
            </p>
        </div>

        <div class="card">
            <h2>Automatisches Redesign nutzen</h2>

            <p>
                Mit konfiguriertem API Key können Sie Seiten vollautomatisch umgestalten:
            </p>

            <ol>
                <li>Gehen Sie zu <strong>DGPTM Suite → AI Export</strong></li>
                <li>Wählen Sie den Tab <strong>"Automatisches Redesign"</strong></li>
                <li>Wählen Sie eine Seite aus</li>
                <li>Geben Sie Ihre Umgestaltungs-Anweisungen ein</li>
                <li>Klicken Sie auf <strong>"Automatisch umgestalten"</strong></li>
            </ol>

            <p>
                Das System wird:
            </p>
            <ul>
                <li>✅ Die Seite exportieren</li>
                <li>✅ An Claude API senden mit Ihren Anweisungen</li>
                <li>✅ Das Redesign als Staging-Seite importieren</li>
                <li>✅ Ihnen einen Link zum Testen geben</li>
            </ul>

            <p>
                <strong>Vorteil:</strong> Kein manuelles Copy-Paste mehr! Alles vollautomatisch in einem Klick.
            </p>
        </div>
    </div>
</div>

<style>
.elementor-ai-export-settings-wrap {
    max-width: 1200px;
}

.settings-container {
    margin-top: 20px;
}

.settings-container .card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    margin-bottom: 20px;
}

.settings-container h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #2271b1;
}

.settings-message {
    margin-top: 15px;
    padding: 12px;
    border-radius: 4px;
}

.settings-message.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.settings-message.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#save-settings-btn').on('click', function() {
        const $btn = $(this);
        const apiKey = $('#claude-api-key').val().trim();

        $btn.prop('disabled', true).text('Speichern...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'elementor_ai_save_settings',
                nonce: '<?php echo wp_create_nonce('elementor_ai_export_nonce'); ?>',
                api_key: apiKey
            },
            success: function(response) {
                const $msg = $('#settings-message');

                if (response.success) {
                    $msg.removeClass('error').addClass('success')
                        .html('<strong>Erfolg!</strong> ' + response.data.message)
                        .fadeIn();

                    $('#test-api-btn').prop('disabled', !apiKey);
                } else {
                    $msg.removeClass('success').addClass('error')
                        .html('<strong>Fehler!</strong> ' + response.data.message)
                        .fadeIn();
                }
            },
            error: function() {
                $('#settings-message').removeClass('success').addClass('error')
                    .html('<strong>Fehler!</strong> Netzwerkfehler beim Speichern')
                    .fadeIn();
            },
            complete: function() {
                $btn.prop('disabled', false).text('Einstellungen speichern');
            }
        });
    });

    $('#test-api-btn').on('click', function() {
        const $btn = $(this);

        $btn.prop('disabled', true).text('Teste...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'elementor_ai_test_api',
                nonce: '<?php echo wp_create_nonce('elementor_ai_export_nonce'); ?>'
            },
            success: function(response) {
                const $msg = $('#settings-message');

                if (response.success) {
                    $msg.removeClass('error').addClass('success')
                        .html('<strong>Erfolg!</strong> ' + response.data.message)
                        .fadeIn();
                } else {
                    $msg.removeClass('success').addClass('error')
                        .html('<strong>Fehler!</strong> ' + response.data.message)
                        .fadeIn();
                }
            },
            error: function() {
                $('#settings-message').removeClass('success').addClass('error')
                    .html('<strong>Fehler!</strong> Netzwerkfehler beim Testen')
                    .fadeIn();
            },
            complete: function() {
                $btn.prop('disabled', false).text('Verbindung testen');
            }
        });
    });
});
</script>
