<?php
/**
 * Tab: Mein Profil - Welcome + CRM data via shortcodes (like original) + folder sub-tabs
 * Variables: $user_id, $crm_data, $permissions, $config
 */
if (!defined('ABSPATH')) exit;

// Use CRM cache for display name (fast, no API call)
$ansprache = $crm_data['Ansprache'] ?? '';
$vorname   = $crm_data['Vorname'] ?? '';
$nachname  = $crm_data['Nachname'] ?? '';
$display_name = trim("{$ansprache} {$vorname} {$nachname}");
if (empty($display_name)) {
    $wp_user = get_userdata($user_id);
    $display_name = $wp_user ? $wp_user->display_name : '';
}
$is_fallback = !empty($crm_data['_source']) && $crm_data['_source'] === 'wordpress_fallback';
?>

<?php // Welcome ?>
<div class="dgptm-welcome">
    <h2>Willkommen, <?php echo esc_html($display_name); ?></h2>
    <p>Mitgliederbereich der DGPTM</p>
    <div class="dgptm-welcome__actions">
        <button class="dgptm-btn dgptm-btn--outline dgptm-btn--sm" id="dgptm-crm-refresh">
            <span class="dashicons dashicons-update"></span> Daten aktualisieren
        </button>
    </div>
</div>

<?php // Banners ?>
<?php if (shortcode_exists('zoho_books_outstanding_banner')) echo do_shortcode('[zoho_books_outstanding_banner]'); ?>
<?php if (shortcode_exists('dgptm-studistatus-banner')) echo do_shortcode('[dgptm-studistatus-banner]'); ?>
<?php if ($is_fallback) : ?>
    <div class="dgptm-banner dgptm-banner--warning">
        <span class="dashicons dashicons-info"></span>
        CRM-Daten konnten nicht geladen werden. Es werden WordPress-Profildaten angezeigt.
    </div>
<?php endif; ?>

<?php // Member status line (like original: shortcode-based for live CRM data) ?>
<div class="dgptm-profile-meta">
    <?php if (shortcode_exists('zoho_api_data_ajax')) : ?>
        <span class="dgptm-badge dgptm-badge--primary"><?php echo do_shortcode('[zoho_api_data_ajax field="Mitgliedsart"]'); ?></span>
        <span class="dgptm-badge dgptm-badge--primary">Nr. <?php echo do_shortcode('[zoho_api_data_ajax field="MitgliedsNr"]'); ?></span>
        <span class="dgptm-badge dgptm-badge--success"><?php echo do_shortcode('[zoho_api_data_ajax field="Status"]'); ?></span>
        <span class="dgptm-badge dgptm-badge--accent">EFN: <?php echo do_shortcode('[zoho_api_data_ajax field="EFN"]'); ?></span>
    <?php elseif (!empty($crm_data['Mitgliedsart'])) : ?>
        <span class="dgptm-badge dgptm-badge--primary"><?php echo esc_html($crm_data['Mitgliedsart']); ?></span>
        <?php if (!empty($crm_data['MitgliedsNr'])) : ?><span class="dgptm-badge dgptm-badge--primary">Nr. <?php echo esc_html($crm_data['MitgliedsNr']); ?></span><?php endif; ?>
        <?php if (!empty($crm_data['Status'])) : ?><span class="dgptm-badge dgptm-badge--success"><?php echo esc_html($crm_data['Status']); ?></span><?php endif; ?>
        <?php if (!empty($crm_data['EFN'])) : ?><span class="dgptm-badge dgptm-badge--accent">EFN: <?php echo esc_html($crm_data['EFN']); ?></span><?php endif; ?>
    <?php endif; ?>
</div>

<?php // EFN Barcode (lightweight, inline) ?>
<?php if (shortcode_exists('efn_barcode_js')) echo do_shortcode('[efn_barcode_js]'); ?>

<?php // Contact data (using shortcodes like the original Elementor page) ?>
<div class="dgptm-card">
    <h3>Kontaktdaten</h3>
    <?php if (shortcode_exists('zoho_api_data_ajax')) : ?>
        <dl class="dgptm-data-list">
            <dt>Adresse</dt>
            <dd>
                <?php echo do_shortcode('[zoho_api_data_ajax field="Strasse"]'); ?>
                <?php if (shortcode_exists('ifcrmfield')) : ?>
                    <?php echo do_shortcode('[ifcrmfield field="Zusatz" value=""][else], [zoho_api_data_ajax field="Zusatz"][/ifcrmfield]'); ?>
                <?php endif; ?>
                <br><?php echo do_shortcode('[zoho_api_data_ajax field="PLZ"]'); ?> <?php echo do_shortcode('[zoho_api_data_ajax field="Ort"]'); ?>
            </dd>
            <dt>Telefon</dt>
            <dd><?php echo do_shortcode('[zoho_api_data_ajax field="TelDienst"]'); ?></dd>
            <dt>Mobil</dt>
            <dd><?php echo do_shortcode('[zoho_api_data_ajax field="TelMobil"]'); ?></dd>
            <dt>E-Mail</dt>
            <dd><?php echo do_shortcode('[zoho_api_data_ajax field="Mail1"]'); ?></dd>
        </dl>
    <?php elseif (!empty($crm_data['Strasse'])) : ?>
        <dl class="dgptm-data-list">
            <dt>Adresse</dt>
            <dd><?php echo esc_html($crm_data['Strasse'] ?? ''); ?><?php if (!empty($crm_data['Zusatz'])) echo ', ' . esc_html($crm_data['Zusatz']); ?>
                <br><?php echo esc_html(trim(($crm_data['PLZ'] ?? '') . ' ' . ($crm_data['Ort'] ?? ''))); ?></dd>
            <?php if (!empty($crm_data['TelDienst'])) : ?><dt>Telefon</dt><dd><?php echo esc_html($crm_data['TelDienst']); ?></dd><?php endif; ?>
            <?php if (!empty($crm_data['TelMobil'])) : ?><dt>Mobil</dt><dd><?php echo esc_html($crm_data['TelMobil']); ?></dd><?php endif; ?>
            <?php $mails = array_filter([$crm_data['Mail1'] ?? '', $crm_data['Mail2'] ?? '', $crm_data['Mail3'] ?? '']);
            if ($mails) : ?><dt>E-Mail</dt><dd><?php echo esc_html(implode(' | ', $mails)); ?></dd><?php endif; ?>
        </dl>
    <?php else : ?>
        <p style="color: var(--dgptm-text-muted)">Keine Kontaktdaten verfuegbar.</p>
    <?php endif; ?>
</div>

<?php // Folder Sub-Tabs ?>
<div class="dgptm-folder-tabs" data-subtab-group="profil">
    <div class="dgptm-folder-nav">
        <a href="#" class="dgptm-folder-tab dgptm-folder-tab--active" data-subtab="stammdaten">Stammdaten bearbeiten</a>
        <a href="#" class="dgptm-folder-tab" data-subtab="transaktionen">Rechnungen</a>
        <a href="#" class="dgptm-folder-tab" data-subtab="lastschrift">Lastschrift & Bescheinigung</a>
        <a href="#" class="dgptm-folder-tab" data-subtab="efn">EFN-Etiketten</a>
        <a href="#" class="dgptm-folder-tab" data-subtab="fortbildung">Fortbildung</a>
    </div>
    <div class="dgptm-folder-content">
        <div class="dgptm-subtab-panel dgptm-subtab-panel--active" data-subtab-panel="stammdaten" data-subtab-loaded="false" data-subtab-action="profil_stammdaten">
            <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
        </div>
        <div class="dgptm-subtab-panel" data-subtab-panel="transaktionen" data-subtab-loaded="false" data-subtab-action="profil_transaktionen">
            <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
        </div>
        <div class="dgptm-subtab-panel" data-subtab-panel="lastschrift" data-subtab-loaded="false" data-subtab-action="profil_lastschrift">
            <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
        </div>
        <div class="dgptm-subtab-panel" data-subtab-panel="efn" data-subtab-loaded="false" data-subtab-action="profil_efn">
            <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
        </div>
        <div class="dgptm-subtab-panel" data-subtab-panel="fortbildung" data-subtab-loaded="false" data-subtab-action="profil_fortbildung">
            <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
        </div>
    </div>
</div>
