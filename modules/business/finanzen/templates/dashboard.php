<?php
if (!defined('ABSPATH')) exit;
$fin = DGPTM_Finanzen::get_instance();
$user_id = get_current_user_id();
$role = $fin->get_user_role($user_id);
$tabs = $fin->get_visible_tabs($user_id);

if (empty($role)) {
    echo '<div class="dgptm-fin-error">Kein Zugriff.</div>';
    return;
}

$role_labels = [
    'admin'            => 'Administrator',
    'schatzmeister'    => 'Schatzmeister',
    'praesident'       => 'Praesident',
    'geschaeftsstelle' => 'Geschaeftsstelle',
];

$tab_labels = [
    'dashboard' => 'Dashboard',
    'billing'   => 'Abrechnung',
    'members'   => 'Mitglieder',
    'results'   => 'Ergebnisse',
    'payments'  => 'Zahlungen',
    'invoices'  => 'Rechnungen',
    'reports'   => 'Finanzberichte',
    'treasurer' => 'Schatzmeister',
    'settings'  => 'Einstellungen',
];

$current_year = (int) date('Y');
?>
<div id="dgptm-fin-app" class="dgptm-fin-wrap">

    <!-- Header -->
    <div class="dgptm-fin-header">
        <h2>DGPTM Finanzen</h2>
        <span class="dgptm-fin-role-badge"><?php echo esc_html($role_labels[$role] ?? $role); ?></span>
    </div>

    <!-- Tab Navigation -->
    <div class="dgptm-fin-tabs">
        <?php foreach ($tabs as $tab):
            if (!isset($tab_labels[$tab])) continue;
        ?>
            <button class="dgptm-fin-tab" data-tab="<?php echo esc_attr($tab); ?>">
                <?php echo esc_html($tab_labels[$tab]); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Panel: Dashboard -->
    <div class="dgptm-fin-panel" id="panel-dashboard" style="display:none">
        <div class="dgptm-fin-loading"><span class="spinner is-active"></span> Lade Dashboard...</div>
        <div id="dgptm-fin-kpis" class="dgptm-fin-kpis" style="display:none"></div>
        <div id="dgptm-fin-last-run" class="dgptm-fin-section" style="display:none">
            <h3>Letzter Abrechnungslauf</h3>
            <div id="dgptm-fin-last-run-content"></div>
        </div>
    </div>

    <!-- Panel: Billing -->
    <div class="dgptm-fin-panel" id="panel-billing" style="display:none">
        <h3>Beitragsabrechnungslauf</h3>
        <div class="dgptm-fin-controls">
            <label>Jahr:
                <select id="dgptm-fin-billing-year">
                    <?php for ($y = $current_year; $y >= 2024; $y--): ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>Kontakt-IDs (optional):
                <textarea id="dgptm-fin-billing-contacts" rows="2" placeholder="Komma-getrennt oder leer fuer alle"></textarea>
            </label>
        </div>
        <div class="dgptm-fin-controls">
            <button type="button" class="dgptm-fin-btn" id="dgptm-fin-dry-run">Dry-Run (Simulation)</button>
            <button type="button" class="dgptm-fin-btn dgptm-fin-btn-danger" id="dgptm-fin-live-run"
                onclick="return confirm('Live-Run starten? Dieser Vorgang kann nicht rueckgaengig gemacht werden.');">
                Live-Run (Abrechnen)
            </button>
            <label class="dgptm-fin-checkbox-label">
                <input type="checkbox" id="dgptm-fin-send-invoices"> Rechnungen per Mail versenden
            </label>
        </div>
        <div id="dgptm-fin-billing-progress" class="dgptm-fin-progress" style="display:none">
            <div class="dgptm-fin-progress-bar"></div>
        </div>
        <div id="dgptm-fin-billing-results" class="dgptm-fin-section" style="display:none">
            <h3>Ergebnisse</h3>
            <div id="dgptm-fin-billing-results-content"></div>
        </div>
    </div>

    <!-- Panel: Members -->
    <div class="dgptm-fin-panel" id="panel-members" style="display:none">
        <h3>Mitglieder-Uebersicht</h3>
        <div class="dgptm-fin-filter-bar">
            <label>Jahr:
                <select id="dgptm-fin-members-year">
                    <?php for ($y = $current_year; $y >= 2024; $y--): ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>Typ:
                <select id="dgptm-fin-members-type">
                    <option value="">Alle</option>
                </select>
            </label>
            <label>Status:
                <select id="dgptm-fin-members-status">
                    <option value="">Alle</option>
                </select>
            </label>
            <label>Abrechnung:
                <select id="dgptm-fin-members-billing">
                    <option value="">Alle</option>
                </select>
            </label>
            <button type="button" class="dgptm-fin-btn dgptm-fin-btn-primary" id="dgptm-fin-members-load">Laden</button>
        </div>
        <div id="dgptm-fin-members-table" class="dgptm-fin-section"></div>
        <div class="dgptm-fin-controls">
            <button type="button" class="dgptm-fin-btn dgptm-fin-btn-primary" id="dgptm-fin-members-to-billing" style="display:none">
                Zur Abrechnung
            </button>
        </div>
    </div>

    <!-- Panel: Results -->
    <div class="dgptm-fin-panel" id="panel-results" style="display:none">
        <h3>Abrechnungsergebnisse</h3>
        <div id="dgptm-fin-results-list" class="dgptm-fin-section">
            <div class="dgptm-fin-loading"><span class="spinner is-active"></span> Lade Ergebnisse...</div>
        </div>
    </div>

    <!-- Panel: Payments -->
    <div class="dgptm-fin-panel" id="panel-payments" style="display:none">
        <h3>Zahlungen</h3>
        <div class="dgptm-fin-controls">
            <button type="button" class="dgptm-fin-btn" id="dgptm-fin-process-payments">Zahlungen verarbeiten (Dry-Run)</button>
            <button type="button" class="dgptm-fin-btn" id="dgptm-fin-sync-mandates">Mandate synchronisieren (Dry-Run)</button>
        </div>
        <div id="dgptm-fin-payments-results" class="dgptm-fin-section" style="display:none">
            <h3>Ergebnisse</h3>
            <div id="dgptm-fin-payments-results-content"></div>
        </div>
    </div>

    <!-- Panel: Invoices -->
    <div class="dgptm-fin-panel" id="panel-invoices" style="display:none">
        <h3>Rechnungen</h3>
        <div class="dgptm-fin-controls">
            <button type="button" class="dgptm-fin-btn dgptm-fin-btn-primary" id="dgptm-fin-invoices-refresh">Aktualisieren</button>
        </div>
        <div id="dgptm-fin-invoices-table" class="dgptm-fin-section">
            <div class="dgptm-fin-loading"><span class="spinner is-active"></span> Lade Rechnungen...</div>
        </div>
    </div>

    <!-- Panel: Reports -->
    <div class="dgptm-fin-panel" id="panel-reports" style="display:none">
        <h3>Finanzberichte</h3>
        <div class="dgptm-fin-sub-tabs">
            <button class="dgptm-fin-sub-tab" data-report="jahrestagung">Jahrestagung</button>
            <button class="dgptm-fin-sub-tab" data-report="sachkundekurs">Sachkundekurs</button>
            <button class="dgptm-fin-sub-tab" data-report="zeitschrift">Zeitschrift</button>
            <button class="dgptm-fin-sub-tab" data-report="mitgliederzahl">Mitgliederzahl</button>
        </div>
        <div class="dgptm-fin-controls">
            <label>Jahr:
                <select id="dgptm-fin-reports-year">
                    <?php for ($y = $current_year; $y >= 2024; $y--): ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <button type="button" class="dgptm-fin-btn" id="dgptm-fin-reports-reload">Aktualisieren</button>
        </div>
        <div id="dgptm-fin-reports-content" class="dgptm-fin-section">
            <p class="dgptm-fin-placeholder">Bitte Bericht auswaehlen.</p>
        </div>
    </div>

    <!-- Panel: Treasurer -->
    <div class="dgptm-fin-panel" id="panel-treasurer" style="display:none">
        <h3>Schatzmeister-Eintraege</h3>
        <div id="dgptm-fin-treasurer-list" class="dgptm-fin-section">
            <div class="dgptm-fin-loading"><span class="spinner is-active"></span> Lade Eintraege...</div>
        </div>
    </div>

    <!-- Panel: Settings -->
    <div class="dgptm-fin-panel" id="panel-settings" style="display:none">
        <h3>Einstellungen</h3>
        <div id="dgptm-fin-settings-content" class="dgptm-fin-section">
            <div class="dgptm-fin-loading"><span class="spinner is-active"></span> Lade Konfiguration...</div>
        </div>
    </div>

</div>
