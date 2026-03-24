<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>Finanzbericht - Einstellungen</h1>

    <!-- Credentials Upload -->
    <div class="card" style="max-width:700px;">
        <h2>Zoho Books Zugangsdaten</h2>
        <p>
            <?php if ($has_credentials): ?>
                <span class="dashicons dashicons-yes-alt" style="color:green;"></span>
                Zugangsdaten hinterlegt.
            <?php else: ?>
                <span class="dashicons dashicons-warning" style="color:orange;"></span>
                Keine Zugangsdaten. Dynamische Berichte (ab 2025) nicht verfuegbar.
            <?php endif; ?>
        </p>
        <p>Transfer-JSON hier einfuegen (wird in der Datenbank gespeichert, nicht in Git):</p>
        <textarea id="dgptm-fb-creds-json" rows="6" style="width:100%;font-family:monospace;font-size:12px;"
                  placeholder='{"zoho":{"accounts_domain":"...","client_id":"...","client_secret":"...","refresh_token":"...","organization_id":"..."}}'></textarea>
        <p>
            <button id="dgptm-fb-save-creds" class="button button-primary">Zugangsdaten speichern</button>
            <span id="dgptm-fb-creds-status"></span>
        </p>
    </div>

    <!-- Berechtigungen -->
    <div class="card" style="max-width:700px;margin-top:20px;">
        <h2>Berechtigungen</h2>
        <p>
            Zugriff wird pro Benutzer vergeben unter <strong>Benutzer &rarr; Bearbeiten &rarr; Finanzbericht-Zugriff</strong>.<br>
            Admins haben automatisch vollen Zugriff.
        </p>
        <p>User-Meta-Key: <code><?php echo DGPTM_Finanzbericht::USER_META_KEY; ?></code></p>
        <p>Shortcode: <code>[dgptm_finanzbericht]</code></p>
    </div>

    <!-- Access-Log -->
    <div class="card" style="max-width:900px;margin-top:20px;">
        <h2>Zugriffs-Log (letzte 50)</h2>
        <?php if (empty($log_entries)): ?>
            <p>Noch keine Zugriffe protokolliert.</p>
        <?php else: ?>
            <table class="widefat striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th>Zeitpunkt</th>
                        <th>Benutzer</th>
                        <th>Bericht</th>
                        <th>Jahr</th>
                        <th>Ergebnis</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log_entries as $entry): ?>
                        <tr>
                            <td><?php echo esc_html($entry['accessed_at']); ?></td>
                            <td><?php echo esc_html($entry['display_name'] ?? "User #{$entry['user_id']}"); ?></td>
                            <td><?php echo esc_html($entry['report_type']); ?></td>
                            <td><?php echo intval($entry['report_year']); ?></td>
                            <td>
                                <?php if ($entry['access_result'] === 'granted'): ?>
                                    <span style="color:green;">&#10003;</span>
                                <?php else: ?>
                                    <span style="color:red;">&#10007; <?php echo esc_html($entry['access_result']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($entry['ip_address']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(function($) {
    $('#dgptm-fb-save-creds').on('click', function() {
        var btn = $(this);
        var status = $('#dgptm-fb-creds-status');
        var json = $('#dgptm-fb-creds-json').val().trim();
        if (!json) { status.text('Bitte JSON eingeben.'); return; }
        btn.prop('disabled', true);
        status.text('Speichern...');
        $.post(ajaxurl, {
            action: 'dgptm_fb_upload_credentials',
            nonce: '<?php echo wp_create_nonce(DGPTM_Finanzbericht::NONCE_ACTION); ?>',
            credentials_json: json
        }, function(r) {
            btn.prop('disabled', false);
            if (r.success) {
                status.html('<span style="color:green;">' + r.data.message + '</span>');
                $('#dgptm-fb-creds-json').val('');
                setTimeout(function(){ location.reload(); }, 1000);
            } else {
                status.html('<span style="color:red;">' + r.data.message + '</span>');
            }
        });
    });
});
</script>
