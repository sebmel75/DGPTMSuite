<?php
/**
 * Module Guides View
 */

if (!defined('ABSPATH')) {
    exit;
}

$guide_manager = DGPTM_Guide_Manager::get_instance();
$module_loader = dgptm_suite()->get_module_loader();
$available_modules = $module_loader->get_available_modules();

// WICHTIG: Gleiche Kategorien-Definition wie im Dashboard verwenden
// Kategorien aus Datenbank und Standard-Kategorien laden
$stored_categories = get_option('dgptm_suite_categories', []);

// Standard-Kategorien (identisch zum Dashboard)
$default_categories = [
    'core-infrastructure' => ['name' => __('Core Infrastructure', 'dgptm-suite'), 'description' => '', 'color' => '#e74c3c'],
    'business' => ['name' => __('Business Logic', 'dgptm-suite'), 'description' => '', 'color' => '#3498db'],
    'payment' => ['name' => __('Payment', 'dgptm-suite'), 'description' => '', 'color' => '#2ecc71'],
    'auth' => ['name' => __('Authentication', 'dgptm-suite'), 'description' => '', 'color' => '#f39c12'],
    'media' => ['name' => __('Media', 'dgptm-suite'), 'description' => '', 'color' => '#9b59b6'],
    'content' => ['name' => __('Content Management', 'dgptm-suite'), 'description' => '', 'color' => '#1abc9c'],
    'acf-tools' => ['name' => __('ACF Tools', 'dgptm-suite'), 'description' => '', 'color' => '#34495e'],
    'utilities' => ['name' => __('Utilities', 'dgptm-suite'), 'description' => '', 'color' => '#95a5a6'],
    'uncategorized' => ['name' => __('Uncategorized', 'dgptm-suite'), 'description' => '', 'color' => '#7f8c8d'],
];

// Merge mit gespeicherten Kategorien
$categories = array_merge($default_categories, $stored_categories);

// Alphabetisch nach Namen sortieren (wie im Dashboard)
uasort($categories, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Guides-Verzeichnis prüfen
$guides_dir = DGPTM_SUITE_PATH . 'guides/';
$existing_guides = [];
if (is_dir($guides_dir)) {
    $guide_files = glob($guides_dir . '*.json');
    foreach ($guide_files as $file) {
        $module_id = basename($file, '.json');
        $existing_guides[$module_id] = true;
    }
}

$modules_by_category = [];
$modules_with_guides_count = 0;
$modules_without_guides_count = 0;

foreach ($available_modules as $module_id => $module_info) {
    $config = $module_info['config'];
    $category = $config['category'] ?? 'uncategorized';

    // Prüfen ob Guide existiert
    $has_guide = isset($existing_guides[$module_id]);

    if ($has_guide) {
        $modules_with_guides_count++;
    } else {
        $modules_without_guides_count++;
    }

    // ALLE Module anzeigen (mit und ohne Guide)
    if (!isset($modules_by_category[$category])) {
        $modules_by_category[$category] = [];
    }

    $modules_by_category[$category][$module_id] = [
        'info' => $module_info,
        'has_guide' => $has_guide
    ];
}

// Module innerhalb jeder Kategorie alphabetisch sortieren (wie im Dashboard)
foreach ($modules_by_category as $cat_id => $modules) {
    uasort($modules_by_category[$cat_id], function($a, $b) {
        $name_a = $a['info']['config']['name'] ?? '';
        $name_b = $b['info']['config']['name'] ?? '';
        return strcmp($name_a, $name_b);
    });
}
?>

<div class="wrap dgptm-guides-page">
    <div class="dgptm-guides-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="dgptm-guides-actions">
            <button type="button" class="button button-primary" id="dgptm-generate-guides">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Alle Anleitungen generieren', 'dgptm-suite'); ?>
            </button>
            <div id="dgptm-generate-status" style="display: none; margin-left: 15px;">
                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                <span class="status-text"><?php _e('Generiere Anleitungen...', 'dgptm-suite'); ?></span>
            </div>
        </div>
    </div>

    <!-- Statistik-Übersicht -->
    <div class="dgptm-guides-stats">
        <div class="stat-box">
            <span class="stat-number"><?php echo $modules_with_guides_count; ?></span>
            <span class="stat-label"><?php _e('Anleitungen verfügbar', 'dgptm-suite'); ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-number"><?php echo count($available_modules); ?></span>
            <span class="stat-label"><?php _e('Module gesamt', 'dgptm-suite'); ?></span>
        </div>
        <div class="stat-box stat-box-warning">
            <span class="stat-number"><?php echo $modules_without_guides_count; ?></span>
            <span class="stat-label"><?php _e('Ohne Anleitung', 'dgptm-suite'); ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-number"><?php echo count($categories); ?></span>
            <span class="stat-label"><?php _e('Kategorien', 'dgptm-suite'); ?></span>
        </div>
    </div>

    <!-- Hinweis wenn keine Guides vorhanden -->
    <?php if ($modules_with_guides_count === 0): ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php _e('Keine Anleitungen gefunden!', 'dgptm-suite'); ?></strong><br>
            <?php _e('Klicken Sie auf "Alle Anleitungen generieren" um die Modul-Anleitungen zu erstellen.', 'dgptm-suite'); ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Suchbereich -->
    <div class="dgptm-guides-search">
        <div class="search-box">
            <input type="text"
                   id="dgptm-guide-search"
                   class="regular-text"
                   placeholder="<?php esc_attr_e('Anleitungen durchsuchen...', 'dgptm-suite'); ?>" />
            <button type="button" class="button button-primary" id="dgptm-search-guides">
                <span class="dashicons dashicons-search"></span>
                <?php _e('Suchen', 'dgptm-suite'); ?>
            </button>
            <button type="button" class="button" id="dgptm-clear-search">
                <?php _e('Zurücksetzen', 'dgptm-suite'); ?>
            </button>
        </div>
        <div id="dgptm-search-results" style="display: none;">
            <h2><?php _e('Suchergebnisse', 'dgptm-suite'); ?></h2>
            <div id="dgptm-search-results-content"></div>
        </div>
    </div>

    <!-- Module nach Kategorien -->
    <div id="dgptm-guides-list">
        <?php foreach ($categories as $cat_id => $cat_data): ?>
            <?php if (isset($modules_by_category[$cat_id]) && !empty($modules_by_category[$cat_id])): ?>
                <div class="dgptm-guide-category" data-category="<?php echo esc_attr($cat_id); ?>">
                    <h2 class="category-title" style="border-left-color: <?php echo esc_attr($cat_data['color'] ?? '#2271b1'); ?>;">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php echo esc_html($cat_data['name']); ?>
                        <span class="category-count">(<?php echo count($modules_by_category[$cat_id]); ?>)</span>
                    </h2>

                    <div class="guides-grid">
                        <?php foreach ($modules_by_category[$cat_id] as $module_id => $module_data): ?>
                            <?php
                            $module_info = $module_data['info'];
                            $has_guide = $module_data['has_guide'];
                            $config = $module_info['config'];

                            // Guide nur laden wenn vorhanden
                            $guide = $has_guide ? $guide_manager->get_guide($module_id) : null;

                            // WICHTIG: Version aus Hauptdatei extrahieren
                            $version = DGPTM_Version_Extractor::get_module_version($module_id);

                            // CSS-Klasse für Karten ohne Anleitung
                            $card_class = $has_guide ? 'guide-card' : 'guide-card guide-card-no-guide';
                            ?>
                            <div class="<?php echo esc_attr($card_class); ?>" data-module-id="<?php echo esc_attr($module_id); ?>">
                                <div class="guide-card-header">
                                    <h3>
                                        <?php echo esc_html($config['name']); ?>
                                        <?php if (!$has_guide): ?>
                                            <span class="no-guide-badge" title="<?php esc_attr_e('Keine Anleitung verfügbar', 'dgptm-suite'); ?>">
                                                <span class="dashicons dashicons-warning"></span>
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                    <span class="guide-version">v<?php echo esc_html($version); ?></span>
                                </div>
                                <div class="guide-card-body">
                                    <p class="guide-description"><?php echo esc_html($guide['description'] ?? $config['description'] ?? ''); ?></p>
                                    <?php if (!$has_guide): ?>
                                        <p class="no-guide-message">
                                            <em><?php _e('Für dieses Modul ist noch keine Anleitung verfügbar.', 'dgptm-suite'); ?></em>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="guide-card-footer">
                                    <?php if ($has_guide): ?>
                                        <button type="button"
                                                class="button button-primary dgptm-view-guide"
                                                data-module-id="<?php echo esc_attr($module_id); ?>">
                                            <span class="dashicons dashicons-book"></span>
                                            <?php _e('Anleitung öffnen', 'dgptm-suite'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button"
                                                class="button button-secondary"
                                                disabled>
                                            <span class="dashicons dashicons-book-alt"></span>
                                            <?php _e('Keine Anleitung', 'dgptm-suite'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal für Anleitung -->
<div id="dgptm-guide-modal" class="dgptm-modal" style="display: none;">
    <div class="dgptm-modal-overlay"></div>
    <div class="dgptm-modal-content-guide">
        <div class="dgptm-modal-header">
            <h2 id="dgptm-guide-title"></h2>
            <button type="button" class="dgptm-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="dgptm-modal-body">
            <div id="dgptm-guide-content"></div>
        </div>
    </div>
</div>

<style>
.dgptm-guides-page {
    max-width: 1400px;
}

.dgptm-guides-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.dgptm-guides-header h1 {
    margin: 0;
}

.dgptm-guides-actions {
    display: flex;
    align-items: center;
}

/* Statistik-Boxen */
.dgptm-guides-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-box {
    background: white;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    text-align: center;
    border-left: 4px solid #2271b1;
}

.stat-number {
    display: block;
    font-size: 2.5em;
    font-weight: bold;
    color: #2271b1;
    line-height: 1;
    margin-bottom: 10px;
}

.stat-label {
    display: block;
    color: #646970;
    font-size: 0.95em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-box-warning {
    border-left-color: #f0b429;
}

.stat-box-warning .stat-number {
    color: #f0b429;
}

/* Module ohne Anleitungen */
.dgptm-missing-guides-notice {
    background: #fff8e5;
    border-left: 4px solid #f0b429;
    padding: 15px 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.missing-guides-toggle {
    margin: 0;
    padding: 5px 0;
    color: #1d2327;
}

.missing-guides-toggle:hover {
    color: #2271b1;
}

.missing-guides-toggle .dashicons {
    transition: transform 0.3s;
}

.missing-guides-toggle.expanded .dashicons {
    transform: rotate(90deg);
}

.missing-guides-list {
    margin-top: 15px;
}

.missing-guides-list ul {
    list-style: none;
    padding: 0;
    margin: 15px 0 0 0;
}

.missing-guides-list li {
    padding: 10px;
    background: white;
    margin-bottom: 8px;
    border-radius: 3px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.module-id-badge {
    background: #f0f0f1;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 0.85em;
    font-family: monospace;
    color: #646970;
}

.category-badge {
    background: #2271b1;
    color: white;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 0.85em;
    margin-left: auto;
}

#dgptm-generate-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.dgptm-guides-search {
    background: white;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.search-box {
    display: flex;
    gap: 10px;
    align-items: center;
}

#dgptm-guide-search {
    flex: 1;
    max-width: 500px;
}

#dgptm-search-results {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.dgptm-guide-category {
    margin-bottom: 30px;
}

.category-title {
    background: #f0f0f1;
    padding: 15px 20px;
    margin: 0 0 15px 0;
    border-left: 4px solid #2271b1;
    cursor: pointer;
    user-select: none;
}

.category-title:hover {
    background: #e8e8e8;
}

.category-title .dashicons {
    transition: transform 0.3s;
}

.category-title.collapsed .dashicons {
    transform: rotate(-90deg);
}

.category-count {
    color: #646970;
    font-weight: normal;
    font-size: 0.9em;
}

.guides-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    padding: 0 20px;
}

.guide-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    transition: box-shadow 0.3s, transform 0.3s;
}

.guide-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

/* Karten ohne Anleitung */
.guide-card-no-guide {
    background: #fff8e5;
    border-left: 4px solid #f0b429;
    opacity: 0.85;
}

.guide-card-no-guide:hover {
    opacity: 1;
    box-shadow: 0 4px 12px rgba(240, 180, 41, 0.2);
}

.no-guide-badge {
    display: inline-flex;
    align-items: center;
    background: #f0b429;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.7em;
    margin-left: 8px;
    vertical-align: middle;
}

.no-guide-badge .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.no-guide-message {
    color: #996800;
    font-size: 0.9em;
    margin-top: 10px;
    padding: 8px;
    background: rgba(240, 180, 41, 0.1);
    border-radius: 3px;
}

.guide-card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f1;
}

.guide-card-header h3 {
    margin: 0;
    font-size: 1.1em;
    color: #1d2327;
}

.guide-version {
    background: #2271b1;
    color: white;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 0.85em;
}

.guide-card-body {
    margin-bottom: 15px;
    min-height: 60px;
}

.guide-description {
    color: #646970;
    font-size: 0.95em;
    line-height: 1.5;
    margin: 0;
}

.guide-card-footer {
    display: flex;
    gap: 10px;
}

.guide-card-footer .button {
    flex: 1;
}

.dgptm-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100000;
}

.dgptm-modal-content-guide {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 4px;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.3);
    z-index: 100001;
    max-width: 900px;
    width: 90%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
}

.dgptm-modal-header {
    padding: 20px 30px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dgptm-modal-header h2 {
    margin: 0;
    font-size: 1.4em;
}

.dgptm-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    color: #646970;
    font-size: 1.5em;
}

.dgptm-modal-close:hover {
    color: #d63638;
}

.dgptm-modal-body {
    padding: 30px;
    overflow-y: auto;
    flex: 1;
}

#dgptm-guide-content {
    line-height: 1.8;
}

#dgptm-guide-content h1,
#dgptm-guide-content h2,
#dgptm-guide-content h3 {
    color: #1d2327;
    margin-top: 1.5em;
    margin-bottom: 0.5em;
}

#dgptm-guide-content h1 {
    font-size: 1.8em;
    border-bottom: 2px solid #2271b1;
    padding-bottom: 10px;
}

#dgptm-guide-content h2 {
    font-size: 1.4em;
    border-bottom: 1px solid #ddd;
    padding-bottom: 8px;
}

#dgptm-guide-content h3 {
    font-size: 1.2em;
}

#dgptm-guide-content p {
    margin: 1em 0;
}

#dgptm-guide-content code {
    background: #f6f7f7;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    color: #d63638;
}

#dgptm-guide-content pre {
    background: #f6f7f7;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    border-left: 3px solid #2271b1;
}

#dgptm-guide-content ul,
#dgptm-guide-content ol {
    margin: 1em 0;
    padding-left: 2em;
}

#dgptm-guide-content li {
    margin: 0.5em 0;
}

#dgptm-guide-content a {
    color: #2271b1;
    text-decoration: none;
}

#dgptm-guide-content a:hover {
    text-decoration: underline;
}

.search-result-item {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: background 0.3s;
}

.search-result-item:hover {
    background: #f0f0f1;
}

.search-result-item h4 {
    margin: 0 0 10px 0;
    color: #2271b1;
}

.search-result-item .module-id {
    font-size: 0.85em;
    color: #646970;
}

.search-result-item .match-score {
    float: right;
    background: #f0f0f1;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 0.85em;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Anleitungen generieren
    $('#dgptm-generate-guides').on('click', function() {
        const $button = $(this);
        const $status = $('#dgptm-generate-status');

        if (confirm('<?php _e('Möchten Sie alle Modul-Anleitungen neu generieren? Dieser Vorgang kann einen Moment dauern.', 'dgptm-suite'); ?>')) {
            $button.prop('disabled', true);
            $status.show();

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_generate_all_guides',
                    nonce: dgptmSuite.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    $status.hide();

                    if (response.success) {
                        alert(response.data.message);
                        // Seite neu laden um die neuen Anleitungen anzuzeigen
                        location.reload();
                    } else {
                        alert('<?php _e('Fehler: ', 'dgptm-suite'); ?>' + response.data.message);
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    $status.hide();
                    alert('<?php _e('AJAX-Fehler beim Generieren der Anleitungen', 'dgptm-suite'); ?>');
                }
            });
        }
    });

    // Kategorie-Toggle
    $('.category-title').on('click', function() {
        $(this).toggleClass('collapsed');
        $(this).next('.guides-grid').slideToggle(300);
    });

    // Anleitung anzeigen
    $('.dgptm-view-guide').on('click', function() {
        const moduleId = $(this).data('module-id');
        loadGuide(moduleId);
    });

    // Modal schließen
    $('.dgptm-modal-close, .dgptm-modal-overlay').on('click', function() {
        $('#dgptm-guide-modal').hide();
    });

    // Suche
    $('#dgptm-search-guides').on('click', function() {
        const searchTerm = $('#dgptm-guide-search').val().trim();

        if (searchTerm.length < 2) {
            alert('<?php _e('Bitte mindestens 2 Zeichen eingeben.', 'dgptm-suite'); ?>');
            return;
        }

        searchGuides(searchTerm);
    });

    // Enter-Taste für Suche
    $('#dgptm-guide-search').on('keypress', function(e) {
        if (e.which === 13) {
            $('#dgptm-search-guides').click();
        }
    });

    // Suche zurücksetzen
    $('#dgptm-clear-search').on('click', function() {
        $('#dgptm-guide-search').val('');
        $('#dgptm-search-results').hide();
        $('#dgptm-guides-list').show();
    });

    // Anleitung laden
    function loadGuide(moduleId) {
        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_get_guide',
                nonce: dgptmSuite.nonce,
                module_id: moduleId
            },
            success: function(response) {
                if (response.success) {
                    const guide = response.data.guide;

                    $('#dgptm-guide-title').text(guide.title);

                    // Markdown zu HTML konvertieren (einfach)
                    let content = guide.content;

                    // Features hinzufügen wenn vorhanden
                    if (guide.features && guide.features.length > 0) {
                        content += '\n\n## Hauptfunktionen\n\n';
                        guide.features.forEach(feature => {
                            content += '- ' + feature + '\n';
                        });
                    }

                    $('#dgptm-guide-content').html(convertMarkdown(content));
                    $('#dgptm-guide-modal').show();
                } else {
                    alert('<?php _e('Fehler beim Laden der Anleitung.', 'dgptm-suite'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('AJAX-Fehler', 'dgptm-suite'); ?>');
            }
        });
    }

    // Anleitungen durchsuchen
    function searchGuides(searchTerm) {
        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_search_guides',
                nonce: dgptmSuite.nonce,
                search_term: searchTerm
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data.results);
                } else {
                    alert('<?php _e('Fehler bei der Suche.', 'dgptm-suite'); ?>');
                }
            }
        });
    }

    // Suchergebnisse anzeigen
    function displaySearchResults(results) {
        const $container = $('#dgptm-search-results-content');
        $container.empty();

        if (results.length === 0) {
            $container.html('<p><?php _e('Keine Ergebnisse gefunden.', 'dgptm-suite'); ?></p>');
        } else {
            results.forEach(result => {
                const $item = $('<div class="search-result-item">');
                $item.html(
                    '<h4>' + result.guide.title +
                    '<span class="match-score">Score: ' + result.score + '</span></h4>' +
                    '<p class="guide-description">' + (result.guide.description || '') + '</p>' +
                    '<p class="module-id">Modul-ID: ' + result.module_id + '</p>'
                );

                $item.on('click', function() {
                    loadGuide(result.module_id);
                });

                $container.append($item);
            });
        }

        $('#dgptm-guides-list').hide();
        $('#dgptm-search-results').show();
    }

    // Einfacher Markdown-zu-HTML Konverter
    function convertMarkdown(markdown) {
        let html = markdown;

        // Headers
        html = html.replace(/^### (.*?)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.*?)$/gm, '<h2>$1</h2>');
        html = html.replace(/^# (.*?)$/gm, '<h1>$1</h1>');

        // Bold
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Italic
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Code
        html = html.replace(/`(.*?)`/g, '<code>$1</code>');

        // Links
        html = html.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank">$1</a>');

        // Listen
        const lines = html.split('\n');
        let inList = false;
        let result = [];

        lines.forEach(line => {
            if (line.trim().startsWith('- ')) {
                if (!inList) {
                    result.push('<ul>');
                    inList = true;
                }
                result.push('<li>' + line.trim().substring(2) + '</li>');
            } else {
                if (inList) {
                    result.push('</ul>');
                    inList = false;
                }
                result.push(line);
            }
        });

        if (inList) {
            result.push('</ul>');
        }

        html = result.join('\n');

        // Absätze
        html = html.replace(/\n\n/g, '</p><p>');
        html = '<p>' + html + '</p>';

        // Leere Absätze entfernen
        html = html.replace(/<p>\s*<\/p>/g, '');
        html = html.replace(/<p>(<h[1-6]>.*?<\/h[1-6]>)<\/p>/g, '$1');
        html = html.replace(/<p>(<ul>.*?<\/ul>)<\/p>/gs, '$1');

        return html;
    }
});
</script>
