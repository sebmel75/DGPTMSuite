<?php
/**
 * Tab: Mein Profil - Welcome, CRM data, contact info
 * This is now a normal tab. Sub-tabs are separate templates, rendered by the parent/child system.
 */
if (!defined('ABSPATH')) exit;
?>
<!-- DEBUG: tab-profil.php loaded, crm_data keys: <?php echo implode(',', array_keys($crm_data ?? [])); ?>, user_id: <?php echo $user_id ?? 'NULL'; ?> -->
<?php
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

<div class="dgptm-welcome">
    <h2>Willkommen, <?php echo esc_html($display_name); ?></h2>
    <p>Mitgliederbereich der DGPTM</p>
</div>

<?php if (shortcode_exists('zoho_books_outstanding_banner')) echo do_shortcode('[zoho_books_outstanding_banner]'); ?>
<?php if (shortcode_exists('dgptm-studistatus-banner')) echo do_shortcode('[dgptm-studistatus-banner]'); ?>
<?php if ($is_fallback) : ?>
    <div class="dgptm-banner dgptm-banner--warning">
        <span class="dashicons dashicons-info"></span>
        CRM-Daten konnten nicht geladen werden.
    </div>
<?php endif; ?>

<div class="dgptm-profile-meta">
    <?php if (shortcode_exists('zoho_api_data_ajax')) : ?>
        <span class="dgptm-badge dgptm-badge--primary"><?php echo do_shortcode('[zoho_api_data_ajax field="Mitgliedsart"]'); ?></span>
        <span class="dgptm-badge dgptm-badge--primary">Nr. <?php echo do_shortcode('[zoho_api_data_ajax field="MitgliedsNr"]'); ?></span>
        <span class="dgptm-badge dgptm-badge--success"><?php echo do_shortcode('[zoho_api_data_ajax field="Status"]'); ?></span>
        <span class="dgptm-badge dgptm-badge--accent">EFN: <?php echo do_shortcode('[zoho_api_data_ajax field="EFN"]'); ?></span>
    <?php endif; ?>
</div>

<?php if (shortcode_exists('efn_barcode_js')) : ?>
    <div class="dgptm-efn-barcode-mobile">
        <?php echo do_shortcode('[efn_barcode_js]'); ?>
    </div>
<?php endif; ?>

<div class="dgptm-card">
    <h3>Kontaktdaten</h3>
    <?php if (shortcode_exists('zoho_api_data_ajax')) : ?>
        <dl class="dgptm-data-list">
            <dt>Adresse</dt>
            <dd>
                <?php echo do_shortcode('[zoho_api_data_ajax field="Strasse"]'); ?>
                <?php if (shortcode_exists('ifcrmfield')) echo do_shortcode('[ifcrmfield field="Zusatz" value=""][else], [zoho_api_data_ajax field="Zusatz"][/ifcrmfield]'); ?>
                <br><?php echo do_shortcode('[zoho_api_data_ajax field="PLZ"]'); ?> <?php echo do_shortcode('[zoho_api_data_ajax field="Ort"]'); ?>
            </dd>
            <dt>Telefon</dt>
            <dd><?php echo do_shortcode('[zoho_api_data_ajax field="TelDienst"]'); ?></dd>
            <dt>Mobil</dt>
            <dd><?php echo do_shortcode('[zoho_api_data_ajax field="TelMobil"]'); ?></dd>
            <dt>E-Mail</dt>
            <dd><?php echo do_shortcode('[zoho_api_data_ajax field="Mail1"]'); ?></dd>
        </dl>
    <?php else : ?>
        <p style="color:var(--dgptm-text-muted)">Keine Kontaktdaten verfuegbar.</p>
    <?php endif; ?>
</div>

<a href="<?php echo esc_url(home_url('/mitgliedschaft/interner-bereich/fortbildungsnachweis/')); ?>"
   class="dgptm-btn dgptm-btn--primary">
    <span class="dashicons dashicons-welcome-learn-more"></span>
    Fortbildungsnachweis (inkl. Quiz)
</a>
