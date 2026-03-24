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
</div>
