<?php if (!defined('ABSPATH')) exit;

// Form-POST verarbeiten (statt AJAX — robuster fuer grosse JSON-Daten)
if (isset($_POST['dgptm_mb_save_config']) && check_admin_referer('dgptm_mb_config_save')) {
    $json_raw = wp_unslash($_POST['config_json'] ?? '');
    $data = json_decode($json_raw, true);

    if ($data && is_array($data)) {
        // Validierung: Muss mindestens zoho oder gocardless enthalten
        if (isset($data['zoho']) || isset($data['gocardless'])) {
            update_option(DGPTM_Mitgliedsbeitrag_Module::OPT_CONFIG, $data, false);
            echo '<div class="notice notice-success is-dismissible"><p>Konfiguration erfolgreich gespeichert.</p></div>';
            // Config neu laden
            $config = DGPTM_MB_Config::load();

            if (class_exists('DGPTM_Logger')) {
                DGPTM_Logger::info('Mitgliedsbeitrag-Konfiguration aktualisiert', 'mitgliedsbeitrag');
            }
        } else {
            echo '<div class="notice notice-error"><p>JSON muss mindestens "zoho" oder "gocardless" Objekte enthalten.</p></div>';
        }
    } else {
        $json_error = json_last_error_msg();
        echo '<div class="notice notice-error"><p>Ungueltiges JSON: ' . esc_html($json_error) . '</p></div>';
    }
}

// Auch Datei-Upload unterstuetzen
if (!empty($_FILES['config_file']['tmp_name']) && check_admin_referer('dgptm_mb_config_save')) {
    $file_content = file_get_contents($_FILES['config_file']['tmp_name']);
    $data = json_decode($file_content, true);

    if ($data && is_array($data) && (isset($data['zoho']) || isset($data['gocardless']))) {
        update_option(DGPTM_Mitgliedsbeitrag_Module::OPT_CONFIG, $data, false);
        echo '<div class="notice notice-success is-dismissible"><p>config.json erfolgreich importiert.</p></div>';
        $config = DGPTM_MB_Config::load();
    } else {
        echo '<div class="notice notice-error"><p>Datei enthaelt kein gueltiges JSON.</p></div>';
    }
}

// Billing-Ergebnisse hochladen
if (isset($_POST['dgptm_mb_import_results']) && check_admin_referer('dgptm_mb_config_save')) {
    $history = get_option('dgptm_mb_billing_history', []);
    $imported = 0;

    if (!empty($_FILES['billing_results_files']['tmp_name'])) {
        foreach ($_FILES['billing_results_files']['tmp_name'] as $i => $tmp) {
            if (empty($tmp) || $_FILES['billing_results_files']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $content = file_get_contents($tmp);
            $data = json_decode($content, true);
            if (!$data || !is_array($data)) continue;

            $filename = sanitize_file_name($_FILES['billing_results_files']['name'][$i]);

            // Jahr und Timestamp aus Dateiname extrahieren: billing_results_2026_20260323_060103.json
            $year = '';
            $ts = '';
            if (preg_match('/billing_results_(\d{4})_(\d{8}_\d{6})/', $filename, $m)) {
                $year = $m[1];
                $ts = substr($m[2], 0, 4) . '-' . substr($m[2], 4, 2) . '-' . substr($m[2], 6, 2) . ' ' .
                      substr($m[2], 9, 2) . ':' . substr($m[2], 11, 2) . ':' . substr($m[2], 13, 2);
            }

            // Zusammenfassung extrahieren
            $total = 0;
            if (isset($data['summary']['total'])) {
                $total = (int) $data['summary']['total'];
            } elseif (is_array($data) && isset($data[0])) {
                $total = count($data);
            }

            $history[] = [
                'filename'  => $filename,
                'year'      => $year ?: ($data['year'] ?? '?'),
                'timestamp' => $ts ?: ($data['timestamp'] ?? date('Y-m-d H:i:s')),
                'total'     => $total,
                'dry_run'   => $data['dry_run'] ?? null,
                'summary'   => $data['summary'] ?? null,
            ];

            // Vollstaendige Daten in separater Option speichern
            update_option('dgptm_mb_result_' . sanitize_key($filename), $data, false);
            $imported++;
        }
    }

    if ($imported > 0) {
        update_option('dgptm_mb_billing_history', $history, false);
        echo '<div class="notice notice-success"><p>' . $imported . ' Ergebnis-Datei(en) importiert.</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Keine gueltigen JSON-Dateien gefunden.</p></div>';
    }
}
?>
<div class="wrap">
    <h1>Mitgliedsbeitrag - Konfiguration</h1>

    <div class="card" style="max-width:800px;padding:20px;">
        <h2>config.json importieren</h2>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('dgptm_mb_config_save'); ?>

            <h3>Option 1: Datei hochladen</h3>
            <p>
                <input type="file" name="config_file" accept=".json,application/json" />
                <button type="submit" class="button button-secondary">Datei importieren</button>
            </p>

            <hr style="margin:20px 0;">

            <h3>Option 2: JSON einfuegen</h3>
            <textarea name="config_json" rows="12" class="large-text code" placeholder='Inhalt der config.json hier einfuegen...'><?php
                $existing = get_option(DGPTM_Mitgliedsbeitrag_Module::OPT_CONFIG, []);
                if (!empty($existing)) echo esc_textarea(wp_json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            ?></textarea>
            <p>
                <button type="submit" name="dgptm_mb_save_config" value="1" class="button button-primary">Konfiguration speichern</button>
            </p>
        </form>
    </div>

    <?php if ($config->is_valid()): ?>
    <div class="card" style="max-width:800px;padding:20px;margin-top:20px;border-left:4px solid #00a32a;">
        <h2 style="color:#00a32a;">Konfiguration aktiv</h2>
        <table class="form-table">
            <tr><th>Zoho API</th><td><?php echo esc_html($config->zoho_api_domain()); ?></td></tr>
            <tr><th>Organisation</th><td><?php echo esc_html($config->zoho_org_id()); ?></td></tr>
            <tr><th>CRM Version</th><td><?php echo esc_html($config->zoho_crm_version()); ?></td></tr>
            <tr><th>Books Version</th><td><?php echo esc_html($config->zoho_books_version()); ?></td></tr>
            <tr><th>GoCardless</th><td>Konfiguriert (Token: ...<?php echo esc_html(substr($config->gc_token(), -8)); ?>)</td></tr>
            <tr><th>Mitgliedstypen</th><td><?php echo count($config->membership_types()); ?> konfiguriert</td></tr>
            <tr><th>Rechnungsvarianten</th><td><?php echo count($config->invoice_variants()); ?> konfiguriert</td></tr>
            <tr><th>Studenten-Beitrag</th><td><?php echo number_format($config->student_fee(), 2, ',', '.'); ?> EUR</td></tr>
        </table>
    </div>
    <?php else: ?>
    <div class="card" style="max-width:800px;padding:20px;margin-top:20px;border-left:4px solid #d63638;">
        <h2 style="color:#d63638;">Konfiguration fehlt oder unvollstaendig</h2>
        <p>Bitte importieren Sie die <code>config.json</code> aus dem Mitgliedsbeitrag-Tool (Option 1 oder 2 oben).</p>
        <p>Benoetigte Felder: <code>zoho.client.client_id</code>, <code>zoho.client.refresh_token</code>, <code>zoho.organization_id</code>, <code>gocardless.access_token</code></p>
    </div>
    <?php endif; ?>

    <!-- Bisherige Billing-Ergebnisse hochladen -->
    <div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
        <h2>Bisherige Abrechnungsergebnisse importieren</h2>
        <p>Laden Sie die <code>billing_results_*.json</code> Dateien aus dem bisherigen Python-Tool hoch.</p>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('dgptm_mb_config_save'); ?>
            <input type="file" name="billing_results_files[]" accept=".json" multiple />
            <p>
                <button type="submit" name="dgptm_mb_import_results" value="1" class="button button-secondary">Ergebnisse importieren</button>
            </p>
        </form>
        <?php
        $existing_results = get_option('dgptm_mb_billing_history', []);
        if (!empty($existing_results)):
        ?>
        <h3>Importierte Ergebnisse</h3>
        <table class="widefat striped" style="margin-top:10px;">
            <thead><tr><th>Datei</th><th>Jahr</th><th>Datum</th><th>Mitglieder</th></tr></thead>
            <tbody>
            <?php foreach ($existing_results as $r): ?>
                <tr>
                    <td><code><?php echo esc_html($r['filename'] ?? ''); ?></code></td>
                    <td><?php echo esc_html($r['year'] ?? '-'); ?></td>
                    <td><?php echo esc_html($r['timestamp'] ?? '-'); ?></td>
                    <td><?php echo esc_html($r['total'] ?? '-'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
