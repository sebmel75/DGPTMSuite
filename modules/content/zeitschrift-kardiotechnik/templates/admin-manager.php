<?php
/**
 * Template: Zeitschrift Verwaltung (Frontend)
 * Komplett AJAX-basiert fuer Benutzer ohne Backend-Zugriff
 *
 * Dieses Template wird von [zeitschrift_verwaltung] genutzt (standalone).
 * Die einzelnen Sektionen sind als Partials verfuegbar fuer Dashboard-Integration.
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
?>

<div class="zk-manager" id="zk-manager">
    <!-- Header -->
    <div class="zk-manager-header">
        <div class="zk-manager-title">
            <h2>Zeitschrift Verwaltung</h2>
            <span class="zk-manager-user">Angemeldet: <?php echo esc_html($current_user->display_name); ?></span>
        </div>
        <div class="zk-manager-actions">
            <button type="button" class="zk-btn zk-btn-primary" id="zk-new-issue-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Neue Ausgabe
            </button>
            <button type="button" class="zk-btn zk-btn-secondary" id="zk-refresh-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                Aktualisieren
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="zk-manager-tabs">
        <button type="button" class="zk-tab zk-tab-active" data-tab="issues">Ausgaben</button>
        <button type="button" class="zk-tab" data-tab="articles">Artikel</button>
        <button type="button" class="zk-tab" data-tab="accepted">Akzeptierte Einreichungen</button>
        <button type="button" class="zk-tab" data-tab="pdf-import">PDF-Import</button>
    </div>

    <!-- Tab-Contents -->
    <div class="zk-tab-content zk-tab-active" id="zk-tab-issues">
        <?php include ZK_PLUGIN_DIR . 'templates/_partial-issues.php'; ?>
    </div>

    <div class="zk-tab-content" id="zk-tab-articles">
        <?php include ZK_PLUGIN_DIR . 'templates/_partial-articles.php'; ?>
    </div>

    <div class="zk-tab-content" id="zk-tab-accepted">
        <?php include ZK_PLUGIN_DIR . 'templates/_partial-accepted.php'; ?>
    </div>

    <div class="zk-tab-content" id="zk-tab-pdf-import">
        <?php include ZK_PLUGIN_DIR . 'templates/_partial-pdf-import.php'; ?>
    </div>

    <!-- Alle Modals -->
    <?php include ZK_PLUGIN_DIR . 'templates/_partial-modals-issues.php'; ?>
    <?php include ZK_PLUGIN_DIR . 'templates/_partial-modals-articles.php'; ?>
    <?php include ZK_PLUGIN_DIR . 'templates/_partial-modals-pdf.php'; ?>

    <!-- Toast Notifications -->
    <div class="zk-toast-container" id="zk-toast-container"></div>
</div>
