<?php if (!defined('ABSPATH')) exit;

// Form-POST: Konfiguration speichern
if (isset($_POST['dgptm_fin_save_config']) && check_admin_referer(DGPTM_Finanzen::NONCE)) {
    $json_raw = wp_unslash($_POST['config_json'] ?? '');
    $data = json_decode($json_raw, true);

    if ($data && is_array($data)) {
        $required = ['zoho', 'gocardless'];
        $has_required = isset($data['zoho']) || isset($data['gocardless']);
        if ($has_required) {
            update_option(DGPTM_Finanzen::OPT_CONFIG, $data, false);
            $config_msg = ['type' => 'success', 'text' => 'Konfiguration erfolgreich gespeichert.'];
            if (class_exists('DGPTM_Logger')) {
                DGPTM_Logger::info('Finanzen-Konfiguration aktualisiert', 'finanzen');
            }
        } else {
            $config_msg = ['type' => 'error', 'text' => 'JSON muss mindestens "zoho" oder "gocardless" Objekte enthalten.'];
        }
    } else {
        $config_msg = ['type' => 'error', 'text' => 'Ungueltiges JSON: ' . json_last_error_msg()];
    }
}

// Form-POST: Datei-Upload fuer Konfiguration
if (!empty($_FILES['config_file']['tmp_name']) && check_admin_referer(DGPTM_Finanzen::NONCE)) {
    $file_content = file_get_contents($_FILES['config_file']['tmp_name']);
    $data = json_decode($file_content, true);

    if ($data && is_array($data) && (isset($data['zoho']) || isset($data['gocardless']))) {
        update_option(DGPTM_Finanzen::OPT_CONFIG, $data, false);
        $config_msg = ['type' => 'success', 'text' => 'config.json erfolgreich importiert.'];
    } else {
        $config_msg = ['type' => 'error', 'text' => 'Datei enthaelt kein gueltiges JSON oder fehlende Pflichtfelder.'];
    }
}

// Form-POST: Billing-Ergebnisse importieren
if (isset($_POST['dgptm_fin_import_results']) && check_admin_referer(DGPTM_Finanzen::NONCE)) {
    $history = get_option(DGPTM_Finanzen::OPT_HISTORY, []);
    $imported = 0;

    if (!empty($_FILES['billing_results_files']['tmp_name'])) {
        foreach ($_FILES['billing_results_files']['tmp_name'] as $i => $tmp) {
            if (empty($tmp) || $_FILES['billing_results_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $content = file_get_contents($tmp);
            $data = json_decode($content, true);
            if (!$data || !is_array($data)) continue;

            $filename = sanitize_file_name($_FILES['billing_results_files']['name'][$i]);
            $year = '';
            $ts = '';
            if (preg_match('/billing_results_(\d{4})_(\d{8}_\d{6})/', $filename, $m)) {
                $year = $m[1];
                $ts = substr($m[2], 0, 4) . '-' . substr($m[2], 4, 2) . '-' . substr($m[2], 6, 2) . ' ' .
                      substr($m[2], 9, 2) . ':' . substr($m[2], 11, 2) . ':' . substr($m[2], 13, 2);
            }

            $summary = $data['summary'] ?? [];
            $history[] = [
                'filename'  => $filename,
                'year'      => $year ?: ($data['year'] ?? '?'),
                'timestamp' => $ts ?: ($data['timestamp'] ?? date('Y-m-d H:i:s')),
                'dry_run'   => $data['dry_run'] ?? null,
                'total'     => (int) ($summary['total'] ?? (isset($data[0]) ? count($data) : 0)),
                'success'   => (int) ($summary['success'] ?? 0),
                'errors'    => (int) ($summary['errors'] ?? 0),
                'amount'    => (float) ($summary['total_amount'] ?? 0),
            ];
            update_option('dgptm_fin_result_' . sanitize_key($filename), $data, false);
            $imported++;
        }
    }

    if ($imported > 0) {
        update_option(DGPTM_Finanzen::OPT_HISTORY, $history, false);
        $results_msg = ['type' => 'success', 'text' => $imported . ' Ergebnis-Datei(en) importiert.'];
    } else {
        $results_msg = ['type' => 'error', 'text' => 'Keine gueltigen JSON-Dateien gefunden.'];
    }
}

// Form-POST: Historische Finanzdaten importieren
if (isset($_POST['dgptm_fin_import_historical']) && check_admin_referer(DGPTM_Finanzen::NONCE)) {
    $json_raw = wp_unslash($_POST['historical_json'] ?? '');
    $data = json_decode($json_raw, true);

    if ($data && is_array($data) && class_exists('DGPTM_FIN_Historical_Data')) {
        $count = DGPTM_FIN_Historical_Data::import($data);
        $hist_msg = ['type' => 'success', 'text' => $count . ' Datensaetze importiert.'];
    } else {
        $hist_msg = ['type' => 'error', 'text' => 'Ungueltiges JSON oder Historical_Data-Klasse nicht verfuegbar.'];
    }
}

// Daten laden
$config = class_exists('DGPTM_FIN_Config') ? DGPTM_FIN_Config::load() : null;
$history = get_option(DGPTM_Finanzen::OPT_HISTORY, []);
$log_entries = class_exists('DGPTM_FIN_Access_Logger') ? DGPTM_FIN_Access_Logger::get_recent(50) : [];
?>
<div class="wrap">
    <h1>DGPTM Finanzen - Administration</h1>

    <?php if (!empty($config_msg)): ?>
        <div class="notice notice-<?php echo esc_attr($config_msg['type']); ?> is-dismissible"><p><?php echo esc_html($config_msg['text']); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($results_msg)): ?>
        <div class="notice notice-<?php echo esc_attr($results_msg['type']); ?> is-dismissible"><p><?php echo esc_html($results_msg['text']); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($hist_msg)): ?>
        <div class="notice notice-<?php echo esc_attr($hist_msg['type']); ?> is-dismissible"><p><?php echo esc_html($hist_msg['text']); ?></p></div>
    <?php endif; ?>

    <!-- 1. Konfiguration -->
    <div class="postbox" style="max-width:800px;padding:12px 20px;">
        <h2>Konfiguration</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field(DGPTM_Finanzen::NONCE); ?>
            <p><strong>Datei hochladen:</strong></p>
            <p><input type="file" name="config_file" accept=".json,application/json" /></p>
            <hr>
            <p><strong>Oder JSON einfuegen:</strong></p>
            <textarea name="config_json" rows="8" class="large-text code" placeholder='{"zoho":{...},"gocardless":{...}}'></textarea>
            <p><button type="submit" name="dgptm_fin_save_config" value="1" class="button button-primary">Konfiguration speichern</button></p>
        </form>
    </div>

    <!-- 2. Aktive Konfiguration -->
    <div class="postbox" style="max-width:800px;padding:12px 20px;margin-top:15px;">
        <h2>Aktive Konfiguration</h2>
        <?php if ($config && $config->is_valid()): ?>
            <p><span class="dashicons dashicons-yes-alt" style="color:green;"></span> <strong style="color:#00a32a;">Konfiguration aktiv</strong></p>
            <table class="form-table">
                <tr><th>Client-ID</th><td><code>...<?php echo esc_html(substr($config->zoho_client_id(), -8)); ?></code></td></tr>
                <tr><th>Client-Secret</th><td><code>********</code></td></tr>
                <tr><th>Organisation-ID</th><td><?php echo esc_html($config->zoho_org_id()); ?></td></tr>
                <tr><th>API-Domain</th><td><?php echo esc_html($config->zoho_api_domain()); ?></td></tr>
                <tr><th>GoCardless API</th><td><?php echo esc_html($config->gc_api_url()); ?></td></tr>
                <tr><th>Mitgliedstypen</th><td><?php echo count($config->membership_types()); ?> konfiguriert</td></tr>
            </table>
        <?php else: ?>
            <p><span class="dashicons dashicons-warning" style="color:orange;"></span> <strong>Keine gueltige Konfiguration vorhanden.</strong></p>
            <p>Bitte importieren Sie die <code>config.json</code> oben. Benoetigte Felder:<br>
                <code>zoho.client.client_id</code>, <code>zoho.client.client_secret</code>,
                <code>zoho.client.refresh_token</code>, <code>zoho.organization_id</code>,
                <code>gocardless.access_token</code></p>
        <?php endif; ?>
    </div>

    <!-- 3. Billing-History -->
    <div class="postbox" style="max-width:900px;padding:12px 20px;margin-top:15px;">
        <h2>Billing-History</h2>
        <p>Bisherige <code>billing_results_*.json</code> Dateien aus dem Python-Tool hochladen.</p>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field(DGPTM_Finanzen::NONCE); ?>
            <input type="file" name="billing_results_files[]" accept=".json" multiple />
            <p><button type="submit" name="dgptm_fin_import_results" value="1" class="button button-secondary">Ergebnisse importieren</button></p>
        </form>
        <?php if (!empty($history)): ?>
        <table class="widefat striped" style="margin-top:10px;">
            <thead><tr><th>Datum</th><th>Jahr</th><th>Dry-Run</th><th>Total</th><th>Erfolg</th><th>Fehler</th><th>Betrag</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($history) as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['timestamp'] ?? '-'); ?></td>
                    <td><?php echo esc_html($r['year'] ?? '-'); ?></td>
                    <td><?php echo ($r['dry_run'] ?? false) ? 'Ja' : 'Nein'; ?></td>
                    <td><?php echo intval($r['total'] ?? 0); ?></td>
                    <td><?php echo intval($r['success'] ?? 0); ?></td>
                    <td><?php echo intval($r['errors'] ?? 0); ?></td>
                    <td><?php echo number_format((float) ($r['amount'] ?? 0), 2, ',', '.'); ?> EUR</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- 4. Historische Finanzdaten -->
    <div class="postbox" style="max-width:800px;padding:12px 20px;margin-top:15px;">
        <h2>Historische Finanzdaten</h2>
        <p>JSON-Daten fuer den Report-Builder importieren.</p>
        <form method="post">
            <?php wp_nonce_field(DGPTM_Finanzen::NONCE); ?>
            <textarea name="historical_json" rows="6" class="large-text code" placeholder='{"jahrestagung":{"2022":{"title":"...","income":{...},"expenses":{...}}}}'></textarea>
            <p><button type="submit" name="dgptm_fin_import_historical" value="1" class="button button-secondary">Historische Daten importieren</button></p>
        </form>
        <p class="description">Format: <code>{"berichtstyp": {"jahr": {"title":"...", "income":{...}, "expenses":{...}}}}</code></p>
    </div>

    <!-- 5. Zugriffs-Log -->
    <div class="postbox" style="max-width:900px;padding:12px 20px;margin-top:15px;">
        <h2>Zugriffs-Log (letzte 50)</h2>
        <?php if (empty($log_entries)): ?>
            <p>Noch keine Zugriffe protokolliert.</p>
        <?php else: ?>
            <table class="widefat striped" style="margin-top:10px;">
                <thead>
                    <tr><th>Zeitpunkt</th><th>Benutzer</th><th>Bericht</th><th>Jahr</th><th>Ergebnis</th><th>IP</th></tr>
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
