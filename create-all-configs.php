<?php
/**
 * Create all module.json configuration files
 */

$modules = [
    // Core Infrastructure
    'core-infrastructure/rest-api-extension' => [
        'id' => 'rest-api-extension',
        'name' => 'REST API Extensions',
        'description' => 'Custom REST API endpoints for DGPTM',
        'version' => '1.0.0',
        'main_file' => 'restapierweiterung.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'core-infrastructure',
        'icon' => 'dashicons-rest-api',
    ],
    'core-infrastructure/webhook-trigger' => [
        'id' => 'webhook-trigger',
        'name' => 'Webhook Trigger',
        'description' => 'Webhook management and triggering system',
        'version' => '1.0.0',
        'main_file' => 'webhooktrigger.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'core-infrastructure',
        'icon' => 'dashicons-networking',
    ],
    'core-infrastructure/menu-control' => [
        'id' => 'menu-control',
        'name' => 'Menu Control',
        'description' => 'Advanced menu visibility and control',
        'version' => '1.0.0',
        'main_file' => 'menucontrol.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'core-infrastructure',
        'icon' => 'dashicons-menu',
    ],
    'core-infrastructure/side-restrict' => [
        'id' => 'side-restrict',
        'name' => 'Side Restrict',
        'description' => 'Page and content access restrictions',
        'version' => '1.0.0',
        'main_file' => 'siderestrict.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'core-infrastructure',
        'icon' => 'dashicons-lock',
    ],

    // ACF Tools
    'acf-tools/acf-anzeiger' => [
        'id' => 'acf-anzeiger',
        'name' => 'ACF Field Display',
        'description' => 'Display ACF fields with advanced formatting',
        'version' => '1.0.0',
        'main_file' => 'acf_anzeiger.php',
        'dependencies' => [],
        'wp_dependencies' => ['advanced-custom-fields'],
        'category' => 'acf-tools',
        'icon' => 'dashicons-admin-settings',
    ],
    'acf-tools/acf-toggle' => [
        'id' => 'acf-toggle',
        'name' => 'ACF Toggle Functions',
        'description' => 'Toggle ACF field visibility and functionality',
        'version' => '1.0.0',
        'main_file' => 'einausblendenacf.php',
        'dependencies' => [],
        'wp_dependencies' => ['advanced-custom-fields'],
        'category' => 'acf-tools',
        'icon' => 'dashicons-visibility',
    ],
    'acf-tools/acf-jetsync' => [
        'id' => 'acf-jetsync',
        'name' => 'ACF JetEngine Sync',
        'description' => 'Synchronize ACF fields with JetEngine',
        'version' => '1.0.0',
        'main_file' => 'acfjetsync.php',
        'dependencies' => [],
        'wp_dependencies' => ['advanced-custom-fields', 'jetengine'],
        'category' => 'acf-tools',
        'icon' => 'dashicons-update',
    ],

    // Business Modules
    'business/fortbildung' => [
        'id' => 'fortbildung',
        'name' => 'DGPTM - Fortbildungsverwaltung',
        'description' => 'Training management with quiz import and PDF certificates',
        'version' => '1.57',
        'main_file' => 'fortbildung-liste-plugin.php',
        'dependencies' => ['quiz-manager'],
        'optional_dependencies' => ['crm-abruf'],
        'wp_dependencies' => ['advanced-custom-fields'],
        'category' => 'business',
        'icon' => 'dashicons-welcome-learn-more',
    ],
    'business/quiz-manager' => [
        'id' => 'quiz-manager',
        'name' => 'Quiz Manager',
        'description' => 'Manage quizzes, questions and categories',
        'version' => '2.4.7',
        'main_file' => 'quizmanager.php',
        'dependencies' => [],
        'optional_dependencies' => ['crm-abruf'],
        'wp_dependencies' => ['quiz-maker'],
        'category' => 'business',
        'icon' => 'dashicons-forms',
    ],
    'business/herzzentren' => [
        'id' => 'herzzentren',
        'name' => 'DGPTM - Herzzentrum Editor',
        'description' => 'Heart center management with maps and Elementor widgets',
        'version' => '4.0.1',
        'main_file' => 'dgptm-herzzentrum-editor.php',
        'dependencies' => [],
        'wp_dependencies' => ['elementor', 'advanced-custom-fields'],
        'category' => 'business',
        'icon' => 'dashicons-location',
    ],
    'business/timeline-manager' => [
        'id' => 'timeline-manager',
        'name' => 'Timeline Plus',
        'description' => 'Timeline management with custom post type and frontend editor',
        'version' => '1.6.7',
        'main_file' => 'timeline-plus.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'business',
        'icon' => 'dashicons-clock',
    ],
    'business/event-tracker' => [
        'id' => 'event-tracker',
        'name' => 'Event Tracker',
        'description' => 'Event routing, webhooks and email system',
        'version' => '1.16.2',
        'main_file' => 'eventtracker.php',
        'dependencies' => ['webhook-trigger'],
        'optional_dependencies' => ['crm-abruf'],
        'wp_dependencies' => [],
        'category' => 'business',
        'icon' => 'dashicons-calendar-alt',
    ],
    'business/abstimmen-addon' => [
        'id' => 'abstimmen-addon',
        'name' => 'Online Voting System',
        'description' => 'Voting system with Zoom integration and attendance tracking',
        'version' => '2.0',
        'main_file' => 'abstimmenadon.php',
        'dependencies' => ['webhook-trigger'],
        'optional_dependencies' => ['crm-abruf'],
        'wp_dependencies' => [],
        'category' => 'business',
        'icon' => 'dashicons-yes',
    ],
    'business/microsoft-gruppen' => [
        'id' => 'microsoft-gruppen',
        'name' => 'Microsoft 365 Groups',
        'description' => 'Microsoft 365 group management integration',
        'version' => '1.5.3',
        'main_file' => 'microsoftgruppen.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'business',
        'icon' => 'dashicons-groups',
    ],
    'business/anwesenheitsscanner' => [
        'id' => 'anwesenheitsscanner',
        'name' => 'Attendance Scanner',
        'description' => 'Attendance tracking with PDF and barcode generation',
        'version' => '2.0',
        'main_file' => 'anwesenheitsscanner.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'business',
        'icon' => 'dashicons-id',
    ],
    'business/gehaltsstatistik' => [
        'id' => 'gehaltsstatistik',
        'name' => 'Salary Statistics',
        'description' => 'Salary data analysis and statistics',
        'version' => '1.0.0',
        'main_file' => 'gehaltsstatistik.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'business',
        'icon' => 'dashicons-chart-bar',
    ],

    // Payment
    'payment/stripe-formidable' => [
        'id' => 'stripe-formidable',
        'name' => 'Stripe Formidable Integration',
        'description' => 'Stripe SEPA and card payment integration for Formidable Forms',
        'version' => '3.0',
        'main_file' => 'stripeformidable.php',
        'dependencies' => [],
        'wp_dependencies' => ['formidable'],
        'category' => 'payment',
        'icon' => 'dashicons-money-alt',
    ],
    'payment/gocardless' => [
        'id' => 'gocardless',
        'name' => 'GoCardless Direct Debit',
        'description' => 'GoCardless direct debit mandate management',
        'version' => '1.20',
        'main_file' => 'gocardless-direct-debit-manager.php',
        'dependencies' => [],
        'wp_dependencies' => ['formidable'],
        'category' => 'payment',
        'icon' => 'dashicons-bank',
    ],

    // Auth
    'auth/otp-login' => [
        'id' => 'otp-login',
        'name' => 'OTP Login System',
        'description' => 'Secure OTP-based login with rate limiting and multisite support',
        'version' => '3.4.0',
        'main_file' => 'otp-with-rotating-logo-preloader.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'auth',
        'icon' => 'dashicons-shield',
    ],

    // Media
    'media/vimeo-streams' => [
        'id' => 'vimeo-streams',
        'name' => 'Vimeo Stream Manager',
        'description' => 'Multi-stream Vimeo video management',
        'version' => '3.0.4',
        'main_file' => 'vimeo-stream-manager.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'media',
        'icon' => 'dashicons-video-alt3',
    ],
    'media/wissens-bot' => [
        'id' => 'wissens-bot',
        'name' => 'Wissens-Bot with Claude AI',
        'description' => 'AI-powered knowledge bot with multi-database integration',
        'version' => '1.0.0',
        'main_file' => 'wissens-bot.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'media',
        'icon' => 'dashicons-brain',
    ],

    // Content
    'content/news-management' => [
        'id' => 'news-management',
        'name' => 'News Management',
        'description' => 'News editing and listing system',
        'version' => '1.0.0',
        'main_file' => 'newsedit.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'content',
        'icon' => 'dashicons-megaphone',
    ],
    'content/publication-workflow' => [
        'id' => 'publication-workflow',
        'name' => 'Publication Workflow',
        'description' => 'Publication management and workflow',
        'version' => '1.0.0',
        'main_file' => 'publication_workflow.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'content',
        'icon' => 'dashicons-media-document',
    ],

    // Utilities
    'utilities/kiosk-jahrestagung' => [
        'id' => 'kiosk-jahrestagung',
        'name' => 'Kiosk Mode',
        'description' => 'Kiosk mode for annual conferences',
        'version' => '1.0.0',
        'main_file' => 'kioskjahrestagung.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'utilities',
        'icon' => 'dashicons-desktop',
    ],
    'utilities/exif-data' => [
        'id' => 'exif-data',
        'name' => 'EXIF Data Manager',
        'description' => 'Image EXIF metadata management',
        'version' => '1.0.0',
        'main_file' => 'exifdata.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'utilities',
        'icon' => 'dashicons-camera',
    ],
    'utilities/blaue-seiten' => [
        'id' => 'blaue-seiten',
        'name' => 'Blaue Seiten',
        'description' => 'Directory functionality (Blue Pages)',
        'version' => '1.0.0',
        'main_file' => 'blaueseiten.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'utilities',
        'icon' => 'dashicons-book',
    ],
    'utilities/shortcode-tools' => [
        'id' => 'shortcode-tools',
        'name' => 'Shortcode Tools',
        'description' => 'Shortcode editors and grid layouts',
        'version' => '1.0.0',
        'main_file' => 'shortcodeedit.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'utilities',
        'icon' => 'dashicons-shortcode',
    ],
    'utilities/stellenanzeige' => [
        'id' => 'stellenanzeige',
        'name' => 'Job Postings',
        'description' => 'Job posting management',
        'version' => '1.0.0',
        'main_file' => 'stellenanzeige.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'utilities',
        'icon' => 'dashicons-businessman',
    ],
    'utilities/conditional-logic' => [
        'id' => 'conditional-logic',
        'name' => 'Conditional Logic',
        'description' => 'Conditional content display',
        'version' => '1.0.0',
        'main_file' => 'if.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'utilities',
        'icon' => 'dashicons-randomize',
    ],
    'utilities/installer' => [
        'id' => 'installer',
        'name' => 'Plugin Installer',
        'description' => 'Plugin installation helpers',
        'version' => '1.0.0',
        'main_file' => 'installer.php',
        'dependencies' => [],
        'wp_dependencies' => [],
        'category' => 'utilities',
        'icon' => 'dashicons-download',
    ],
    'utilities/zoho-role-manager' => [
        'id' => 'zoho-role-manager',
        'name' => 'Zoho Role Manager',
        'description' => 'Role management based on Zoho CRM data',
        'version' => '1.0.0',
        'main_file' => 'zoho-role-manager.php',
        'dependencies' => ['crm-abruf'],
        'wp_dependencies' => [],
        'category' => 'utilities',
        'icon' => 'dashicons-admin-users',
    ],
];

$base_dir = __DIR__ . '/modules/';
$created = 0;
$errors = [];

foreach ($modules as $path => $config) {
    $module_json_path = $base_dir . $path . '/module.json';

    $json_data = [
        'id' => $config['id'],
        'name' => $config['name'],
        'description' => $config['description'],
        'version' => $config['version'],
        'author' => 'Sebastian Melzer',
        'main_file' => $config['main_file'],
        'dependencies' => $config['dependencies'] ?? [],
        'optional_dependencies' => $config['optional_dependencies'] ?? [],
        'wp_dependencies' => [
            'plugins' => $config['wp_dependencies'] ?? [],
        ],
        'requires_php' => '7.4',
        'requires_wp' => '5.8',
        'category' => $config['category'],
        'icon' => $config['icon'],
        'active' => false,
        'can_export' => true,
    ];

    $dir = dirname($module_json_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $result = file_put_contents($module_json_path, json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($result !== false) {
        $created++;
        echo "✓ Created: $path/module.json\n";
    } else {
        $errors[] = $path;
        echo "✗ Failed: $path/module.json\n";
    }
}

echo "\n=== Summary ===\n";
echo "Created: $created module.json files\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nFailed modules:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}
