<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>Mitgliedsbeitrag - Konfiguration</h1>

    <div class="card" style="max-width:800px;padding:20px;">
        <h2>Konfiguration importieren</h2>
        <p>Fuegen Sie den Inhalt der <code>config.json</code> aus dem Mitgliedsbeitrag-Tool ein:</p>
        <textarea id="dgptm-mb-config-json" rows="10" class="large-text code" placeholder='{"zoho": {...}, "gocardless": {...}, ...}'><?php
            $existing = get_option(DGPTM_Mitgliedsbeitrag_Module::OPT_CONFIG, []);
            if (!empty($existing)) echo esc_textarea(wp_json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        ?></textarea>
        <p>
            <button type="button" class="button button-primary" id="dgptm-mb-save-config">Konfiguration speichern</button>
            <span id="dgptm-mb-config-status" style="margin-left:10px;"></span>
        </p>
        <p class="description">
            <strong>Hinweis:</strong> Die Konfiguration enthaelt sensible Zugangsdaten (OAuth Tokens, GoCardless Key).
            Sie wird verschluesselt in der Datenbank gespeichert, NICHT im Git.
        </p>
    </div>

    <?php if ($config->is_valid()): ?>
    <div class="card" style="max-width:800px;padding:20px;margin-top:20px;border-left:4px solid #00a32a;">
        <h2 style="color:#00a32a;">Konfiguration aktiv</h2>
        <ul>
            <li><strong>Zoho API:</strong> <?php echo esc_html($config->zoho_api_domain()); ?></li>
            <li><strong>Organisation:</strong> <?php echo esc_html($config->zoho_org_id()); ?></li>
            <li><strong>GoCardless:</strong> Konfiguriert</li>
            <li><strong>Mitgliedstypen:</strong> <?php echo count($config->membership_types()); ?></li>
        </ul>
    </div>
    <?php else: ?>
    <div class="card" style="max-width:800px;padding:20px;margin-top:20px;border-left:4px solid #d63638;">
        <h2 style="color:#d63638;">Konfiguration fehlt</h2>
        <p>Bitte importieren Sie die <code>config.json</code> aus dem Mitgliedsbeitrag-Tool.</p>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(function($) {
    $('#dgptm-mb-save-config').on('click', function() {
        var $btn = $(this);
        var $status = $('#dgptm-mb-config-status');
        $btn.prop('disabled', true).text('Speichere...');

        $.post(ajaxurl, {
            action: 'dgptm_mb_save_config',
            nonce: '<?php echo wp_create_nonce(DGPTM_Mitgliedsbeitrag_Module::NONCE); ?>',
            config_json: $('#dgptm-mb-config-json').val()
        }, function(res) {
            $btn.prop('disabled', false).text('Konfiguration speichern');
            if (res.success) {
                $status.html('<span style="color:#00a32a;">&#10003; ' + res.data.message + '</span>');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                $status.html('<span style="color:#d63638;">&#10007; ' + (res.data?.message || 'Fehler') + '</span>');
            }
        });
    });
});
</script>
