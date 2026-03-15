<?php
/**
 * Tab: Mein Profil - Lightweight welcome + CRM data + sub-tabs for heavy content
 * Variables: $user_id, $crm_data, $permissions, $config
 */
if (!defined('ABSPATH')) exit;

$ansprache  = esc_html($crm_data['Ansprache'] ?? '');
$vorname    = esc_html($crm_data['Vorname'] ?? '');
$nachname   = esc_html($crm_data['Nachname'] ?? '');
$mitgl_art  = esc_html($crm_data['Mitgliedsart'] ?? '');
$mitgl_nr   = esc_html($crm_data['MitgliedsNr'] ?? '');
$status     = esc_html($crm_data['Status'] ?? '');
$efn        = esc_html($crm_data['EFN'] ?? '');
$is_fallback = !empty($crm_data['_source']) && $crm_data['_source'] === 'wordpress_fallback';

$display_name = trim("{$ansprache} {$vorname} {$nachname}");
if (empty($display_name)) {
    $wp_user = get_userdata($user_id);
    $display_name = $wp_user ? $wp_user->display_name : '';
}
?>

<?php // Welcome Header ?>
<div class="dgptm-welcome">
    <h2>Willkommen, <?php echo $display_name; ?></h2>
    <p>Mitgliederbereich der DGPTM</p>
    <div class="dgptm-welcome__actions">
        <button class="dgptm-btn dgptm-btn--outline dgptm-btn--sm" id="dgptm-crm-refresh" title="CRM-Daten neu laden">
            <span class="dashicons dashicons-update"></span> Daten aktualisieren
        </button>
    </div>
</div>

<?php // Banners (lightweight - these shortcodes are fast) ?>
<?php if (shortcode_exists('zoho_books_outstanding_banner')) : ?>
    <?php echo do_shortcode('[zoho_books_outstanding_banner]'); ?>
<?php endif; ?>

<?php if (shortcode_exists('dgptm-studistatus-banner')) : ?>
    <?php echo do_shortcode('[dgptm-studistatus-banner]'); ?>
<?php endif; ?>

<?php if ($is_fallback) : ?>
    <div class="dgptm-banner dgptm-banner--warning">
        <span class="dashicons dashicons-info"></span>
        CRM-Daten konnten nicht geladen werden. Es werden WordPress-Profildaten angezeigt.
    </div>
<?php endif; ?>

<?php // Member Info Badges ?>
<?php if ($mitgl_art || $mitgl_nr || $status || $efn) : ?>
<div class="dgptm-profile-meta">
    <?php if ($mitgl_art) : ?>
        <span class="dgptm-badge dgptm-badge--primary"><?php echo $mitgl_art; ?></span>
    <?php endif; ?>
    <?php if ($mitgl_nr) : ?>
        <span class="dgptm-badge dgptm-badge--primary">Nr. <?php echo $mitgl_nr; ?></span>
    <?php endif; ?>
    <?php if ($status) : ?>
        <span class="dgptm-badge dgptm-badge--success"><?php echo $status; ?></span>
    <?php endif; ?>
    <?php if ($efn) : ?>
        <span class="dgptm-badge dgptm-badge--accent">EFN: <?php echo $efn; ?></span>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php // Contact Data Card (rendered from cache, no shortcodes = fast) ?>
<div class="dgptm-card">
    <h3>Kontaktdaten</h3>
    <dl class="dgptm-data-list">
        <?php if (!empty($crm_data['Strasse'])) : ?>
            <dt>Strasse</dt><dd><?php echo esc_html($crm_data['Strasse']); ?><?php if (!empty($crm_data['Zusatz'])) echo ', ' . esc_html($crm_data['Zusatz']); ?></dd>
        <?php endif; ?>
        <?php if (!empty($crm_data['PLZ']) || !empty($crm_data['Ort'])) : ?>
            <dt>Ort</dt><dd><?php echo esc_html(trim(($crm_data['PLZ'] ?? '') . ' ' . ($crm_data['Ort'] ?? ''))); ?></dd>
        <?php endif; ?>
        <?php if (!empty($crm_data['TelDienst'])) : ?>
            <dt>Telefon</dt><dd><?php echo esc_html($crm_data['TelDienst']); ?></dd>
        <?php endif; ?>
        <?php if (!empty($crm_data['TelMobil'])) : ?>
            <dt>Mobil</dt><dd><?php echo esc_html($crm_data['TelMobil']); ?></dd>
        <?php endif; ?>
        <?php
        $mails = array_filter([
            $crm_data['Mail1'] ?? '',
            $crm_data['Mail2'] ?? '',
            $crm_data['Mail3'] ?? '',
        ]);
        if ($mails) : ?>
            <dt>E-Mail</dt><dd><?php echo esc_html(implode(' | ', $mails)); ?></dd>
        <?php endif; ?>
    </dl>
</div>

<?php // Sub-Tab Navigation for heavy content ?>
<nav class="dgptm-subtab-nav" data-subtab-group="profil">
    <button class="dgptm-subtab-nav__item dgptm-subtab-nav__item--active" data-subtab="stammdaten">Stammdaten bearbeiten</button>
    <button class="dgptm-subtab-nav__item" data-subtab="transaktionen">Rechnungen</button>
    <button class="dgptm-subtab-nav__item" data-subtab="lastschrift">Lastschrift & Bescheinigung</button>
    <?php if ($efn) : ?>
    <button class="dgptm-subtab-nav__item" data-subtab="efn">EFN & Barcode</button>
    <?php endif; ?>
    <button class="dgptm-subtab-nav__item" data-subtab="fortbildung">Fortbildung</button>
</nav>

<?php // Sub-Tab Panels (lazy-loaded via AJAX) ?>
<div class="dgptm-subtab-panel dgptm-subtab-panel--active" data-subtab-panel="stammdaten" data-subtab-loaded="false" data-subtab-action="profil_stammdaten">
    <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
</div>

<div class="dgptm-subtab-panel" data-subtab-panel="transaktionen" data-subtab-loaded="false" data-subtab-action="profil_transaktionen">
    <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
</div>

<div class="dgptm-subtab-panel" data-subtab-panel="lastschrift" data-subtab-loaded="false" data-subtab-action="profil_lastschrift">
    <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
</div>

<?php if ($efn) : ?>
<div class="dgptm-subtab-panel" data-subtab-panel="efn" data-subtab-loaded="false" data-subtab-action="profil_efn">
    <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
</div>
<?php endif; ?>

<div class="dgptm-subtab-panel" data-subtab-panel="fortbildung" data-subtab-loaded="false" data-subtab-action="profil_fortbildung">
    <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
</div>
