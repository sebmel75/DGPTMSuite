<?php
/**
 * Tab: Mein Profil - CRM data, member status, accordion sections
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
?>

<div class="dgptm-profile-header">
    <h2><?php echo trim("{$ansprache} {$vorname} {$nachname}"); ?></h2>
    <button class="dgptm-btn dgptm-btn--outline" id="dgptm-crm-refresh" title="CRM-Daten neu laden">
        <span class="dashicons dashicons-update"></span> Aktualisieren
    </button>
</div>

<?php // Banners ?>
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

<?php // Member Info ?>
<?php if ($mitgl_art || $mitgl_nr || $status) : ?>
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

<?php // EFN Barcode ?>
<?php if ($efn && shortcode_exists('efn_barcode_js')) : ?>
    <div class="dgptm-card">
        <h3>EFN-Barcode</h3>
        <?php echo do_shortcode('[efn_barcode_js]'); ?>
    </div>
<?php endif; ?>

<?php // Fortbildungsnachweis Button ?>
<div style="margin-bottom: var(--dgptm-gap);">
    <a href="<?php echo esc_url(home_url('/mitgliedschaft/interner-bereich/fortbildungsnachweis/')); ?>"
       class="dgptm-btn dgptm-btn--primary">
        <span class="dashicons dashicons-welcome-learn-more"></span>
        Fortbildungsnachweis (inkl. Quiz)
    </a>
</div>

<?php // Accordion Sections ?>
<details class="dgptm-accordion" open>
    <summary>Kontaktdaten & Stammdaten</summary>
    <div class="dgptm-accordion__content">
        <dl class="dgptm-data-list">
            <?php if (!empty($crm_data['Strasse'])) : ?>
                <dt>Strasse</dt><dd><?php echo esc_html($crm_data['Strasse']); ?></dd>
            <?php endif; ?>
            <?php if (!empty($crm_data['Zusatz'])) : ?>
                <dt>Zusatz</dt><dd><?php echo esc_html($crm_data['Zusatz']); ?></dd>
            <?php endif; ?>
            <?php if (!empty($crm_data['PLZ']) || !empty($crm_data['Ort'])) : ?>
                <dt>Ort</dt><dd><?php echo esc_html(($crm_data['PLZ'] ?? '') . ' ' . ($crm_data['Ort'] ?? '')); ?></dd>
            <?php endif; ?>
            <?php if (!empty($crm_data['TelDienst'])) : ?>
                <dt>Telefon</dt><dd><?php echo esc_html($crm_data['TelDienst']); ?></dd>
            <?php endif; ?>
            <?php if (!empty($crm_data['TelMobil'])) : ?>
                <dt>Mobil</dt><dd><?php echo esc_html($crm_data['TelMobil']); ?></dd>
            <?php endif; ?>
            <?php if (!empty($crm_data['TelZus'])) : ?>
                <dt>Weiteres Telefon</dt><dd><?php echo esc_html($crm_data['TelZus']); ?></dd>
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

        <?php if (shortcode_exists('dgptm-daten-bearbeiten')) : ?>
            <div style="margin-top: var(--dgptm-gap);">
                <?php echo do_shortcode('[dgptm-daten-bearbeiten]'); ?>
            </div>
        <?php else : ?>
            <a href="<?php echo esc_url(home_url('/mitgliedschaft/interner-bereich/daten-bearbeiten/')); ?>"
               class="dgptm-btn dgptm-btn--outline" style="margin-top: var(--dgptm-gap);">
                Daten bearbeiten
            </a>
        <?php endif; ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>Rechnungen & Transaktionen</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('zoho_books_transactions')) : ?>
            <?php echo do_shortcode('[zoho_books_transactions]'); ?>
        <?php else : ?>
            <p class="dgptm-text-muted">Transaktionsmodul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>Lastschriftmandat & Mitgliedsbescheinigung</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('gcl_formidable')) : ?>
            <h4>Lastschriftmandat</h4>
            <?php echo do_shortcode('[gcl_formidable]'); ?>
        <?php endif; ?>

        <?php if (shortcode_exists('webhook_ajax_trigger')) : ?>
            <h4 style="margin-top: 20px;">Mitgliedsbescheinigung</h4>
            <?php echo do_shortcode('[webhook_ajax_trigger url="https://flow.zoho.eu/20086283718/flow/webhook/incoming?zapikey=1001.61e55251780c1730ee213bfe02d8a192.eb83171de88e8e99371cf264aa47e96c&isdebug=false" method="POST" user_field="zoho_id" cooldown="6" status_id="mgb" cooldown_message="Du hast heute schon eine Bescheinigung angefordert."]'); ?>
            <?php echo do_shortcode('[webhook_status_output id="mgb"]'); ?>
        <?php endif; ?>

        <?php if (shortcode_exists('dgptm-studistatus')) : ?>
            <h4 style="margin-top: 20px;">Studierendenstatus</h4>
            <?php echo do_shortcode('[dgptm-studistatus]'); ?>
        <?php endif; ?>
    </div>
</details>

<details class="dgptm-accordion">
    <summary>EFN-Etiketten</summary>
    <div class="dgptm-accordion__content">
        <?php if (shortcode_exists('efn_label_sheet')) : ?>
            <?php echo do_shortcode('[efn_label_sheet]'); ?>
        <?php else : ?>
            <p class="dgptm-text-muted">EFN-Modul nicht verfuegbar.</p>
        <?php endif; ?>
    </div>
</details>
