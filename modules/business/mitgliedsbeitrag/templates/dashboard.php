<?php if (!defined('ABSPATH')) exit;
$mb = DGPTM_Mitgliedsbeitrag_Module::get_instance();
$is_schatzmeister = $mb->user_has_access();
?>
<div id="dgptm-mb-app" class="dgptm-mb-wrap">
    <div class="dgptm-mb-header">
        <h2>Mitgliedsbeitrag</h2>
        <span class="dgptm-mb-badge"><?php echo $is_schatzmeister ? 'Schatzmeister' : 'Ansicht'; ?></span>
    </div>

    <!-- Status-Bereich (fuer alle sichtbar) -->
    <div id="dgptm-mb-status" class="dgptm-mb-section">
        <h3>Mitglieder-Uebersicht</h3>
        <div id="dgptm-mb-stats-loading"><span class="spinner is-active"></span> Lade Statistiken...</div>
        <div id="dgptm-mb-stats-content" style="display:none;"></div>
    </div>

    <?php if ($is_schatzmeister): ?>
    <!-- Billing-Tools (nur Schatzmeister) -->
    <div class="dgptm-mb-section">
        <h3>Beitragsabrechnungslauf</h3>
        <div class="dgptm-mb-controls">
            <label>Jahr:
                <select id="dgptm-mb-year">
                    <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>Kontakt-IDs (optional):
                <input type="text" id="dgptm-mb-contacts" placeholder="Komma-getrennt oder leer fuer alle" style="width:250px;">
            </label>
        </div>
        <div class="dgptm-mb-actions">
            <button type="button" class="dgptm-mb-btn" id="dgptm-mb-dry-run">Dry-Run (Simulation)</button>
            <button type="button" class="dgptm-mb-btn dgptm-mb-btn-primary" id="dgptm-mb-live-run">Live-Run (Abrechnen)</button>
            <label style="margin-left:12px;"><input type="checkbox" id="dgptm-mb-send"> Rechnungen per Mail versenden</label>
        </div>
    </div>

    <div class="dgptm-mb-section">
        <h3>Weitere Aktionen</h3>
        <div class="dgptm-mb-actions">
            <button type="button" class="dgptm-mb-btn" id="dgptm-mb-process-payments">Zahlungen verarbeiten (Dry-Run)</button>
            <button type="button" class="dgptm-mb-btn" id="dgptm-mb-sync-mandates">Mandate synchronisieren (Dry-Run)</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Ergebnisse -->
    <div id="dgptm-mb-results" class="dgptm-mb-section" style="display:none;">
        <h3>Ergebnisse</h3>
        <div id="dgptm-mb-results-content"></div>
    </div>
</div>
