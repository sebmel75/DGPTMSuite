# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Quick Reference

**Stack:** WordPress 5.8+ / PHP 7.4+ | **Version:** 3.0.0 | **Modules:** 50+

**Key Paths:**
- Entry point: `dgptm-master.php`
- Core classes: `core/class-*.php`
- Admin: `admin/class-plugin-manager.php`, `admin/views/`
- Modules: `modules/{category}/{module-id}/module.json`
- Config: `categories.json` (categories & flags definitions)

**Settings Storage:**
- Module activation: `wp_options` → `dgptm_suite_settings`
- Module metadata: Stored in each `module.json` file (file-based, NOT database)

## Project Overview

DGPTM Plugin Suite is a WordPress plugin management system consolidating 50+ individual modules into a unified administration interface. The project provides centralized control over DGPTM plugins with dependency management, individual activation/deactivation, update handling, standalone plugin export functionality, and module metadata management (flags, comments, version switching).

**Version:** 3.0.0
**Platform:** WordPress 5.8+ with PHP 7.4+
**Organization:** DGPTM (Deutsche Gesellschaft für Prävention und Telemedizin e.V.)

## Architecture

### Core Structure

The plugin follows a modular architecture with these main components:

1. **Master Controller** (`dgptm-master.php`)
   - Entry point and initialization
   - Loads core classes and admin interface
   - Manages plugin lifecycle hooks

2. **Core System** (`core/`)
   - `class-module-loader.php` - Dynamic module loading based on activation state
   - `class-dependency-manager.php` - Handles module dependencies
   - `class-safe-loader.php` - Safe loading with error handling (isolate test DISABLED)
   - `class-module-metadata-file.php` - File-based metadata (flags, comments, version links)
   - `class-logger.php` - Logging system with auto-rotation
   - `class-zip-generator.php` - Exports modules as standalone plugins
   - `class-checkout-manager.php` - Module checkout for editing
   - `class-module-settings-manager.php` - Per-module settings management
   - `class-central-settings-registry.php` - Central settings registry
   - `class-version-extractor.php` - Extracts versions from plugin headers

3. **Admin Interface** (`admin/`)
   - `class-plugin-manager.php` - Main admin dashboard controller with AJAX handlers
   - `class-module-upload-handler.php` - Handles module ZIP uploads
   - `views/` - Dashboard, settings, export, updates, logs, and metadata modal views
   - `assets/` - Admin CSS and JavaScript

4. **Module Categories** (`modules/`) - defined in `categories.json`
   - `core-infrastructure/` - Essential services (CRM, API, webhooks, menu control)
   - `business/` - Business logic (training, quiz, heart centers, events, voting)
   - `payment/` - Payment integrations (Stripe, GoCardless)
   - `auth/` - Authentication (OTP login)
   - `media/` - Media management (Vimeo, AI knowledge bot)
   - `content/` - Content management (news, publications, webinars)
   - `acf-tools/` - Advanced Custom Fields utilities
   - `utilities/` - Various tools (kiosk mode, EXIF, shortcodes, role manager, etc.)

### Module System

Each module:
- Lives in its category folder (e.g., `modules/business/fortbildung/`)
- Contains a `module.json` configuration file defining:
  - Module ID, name, description, version, author
  - Main PHP file to load
  - Dependencies (DGPTM modules and WordPress plugins)
  - PHP/WordPress version requirements
  - Category and icon
  - Critical flag (prevents deactivation)
- Can be activated/deactivated independently
- Can have flags, comments, and test version links (metadata)
- Can be exported as a standalone WordPress plugin

**Example module.json:**
```json
{
  "id": "herzzentren",
  "name": "DGPTM - Herzzentrum Editor",
  "description": "Heart center management with maps",
  "version": "4.0.1",
  "author": "Sebastian Melzer",
  "main_file": "dgptm-herzzentrum-editor.php",
  "dependencies": [],
  "optional_dependencies": [],
  "wp_dependencies": {
    "plugins": ["elementor", "advanced-custom-fields"]
  },
  "requires_php": "7.4",
  "requires_wp": "5.8",
  "category": "business",
  "icon": "dashicons-location",
  "active": false,
  "can_export": true,
  "critical": false,
  "flags": ["production"],
  "comment": {
    "text": "Aktiv in Produktion",
    "timestamp": 1700000000,
    "user_id": 1
  },
  "test_version_link": null
}
```

**Note:** `flags`, `comment`, and `test_version_link` are optional fields managed by the metadata system.

### Module Metadata System (File-Based)

**IMPORTANT CHANGE:** Metadata is now stored in `module.json` files, NOT in `wp_options`. The system automatically migrates old data from the database to files.

**Central Configuration:** `categories.json` defines all available categories and flags.

**Module metadata in module.json:**
- `category` - Module category (references categories.json)
- `flags` - Array of flag IDs (e.g., `["testing", "production"]`)
- `comment` - Optional comment object with text, timestamp, user_id
- `test_version_link` - ID of linked test version module

**Available Flags (defined in categories.json):**
- `testing` - Module is being tested (blue)
- `deprecated` - Module is outdated (red)
- `important` - Critical module (yellow)
- `development` - In development (green)
- `production` - Production-ready (purple)
- `beta` - Beta version (light blue)

**Accessing metadata:**
```php
$metadata = DGPTM_Module_Metadata_File::get_instance();
$metadata->add_flag('module-id', 'testing');
$metadata->set_comment('module-id', 'Needs update');
$metadata->link_test_version('main-module', 'test-module');
$metadata->switch_version('module-id');
```

**Category definitions in categories.json:**
- Each category has: name, description, icon (dashicon), color, sort order
- Directory structure is irrelevant for categorization
- Modules can be in any folder but category is set in module.json

### Dependency Management

The system handles three types of dependencies:

1. **DGPTM Module Dependencies** - Internal module requirements
   - Example: `fortbildung` requires `quiz-manager`
   - Defined in `module.json` under `dependencies` array

2. **WordPress Plugin Dependencies** - External plugin requirements
   - Example: `herzzentren` requires Elementor and ACF
   - Defined in `module.json` under `wp_dependencies.plugins` array

3. **Dependency Chain Resolution**
   - Core modules: `crm-abruf`, `rest-api-extension`, `webhook-trigger` (foundation layer)
   - Many business modules depend on `crm-abruf` for Zoho CRM integration
   - Payment modules require Formidable Forms
   - Loader sorts modules by dependencies before loading

**Key Dependency Chains:**
- Training: Quiz Maker plugin → `quiz-manager` → `fortbildung` → FPDF library
- Voting: `crm-abruf` + `webhook-trigger` → `abstimmen-addon` → Zoom API
- Heart Centers: ACF plugin → `herzzentren` → Elementor widgets
- Events: `webhook-trigger` + `crm-abruf` → `event-tracker`

### Safe Loading System

**IMPORTANT:** The `class-safe-loader.php` has isolated test mode DISABLED (as of recent updates) because it was causing false positives. The isolated test tried to load modules without WordPress context, causing all WordPress functions to be undefined and triggering mass auto-deactivation.

**Current Safety Mechanisms:**
1. PHP syntax check (via `php -l`)
2. Try-catch during actual loading
3. Shutdown handler for fatal errors
4. Auto-deactivation on real errors only

**Do NOT re-enable isolated testing** without first ensuring it runs in WordPress context.

### Shared Libraries

Located in `dgptm-plugin-suite/libraries/`:
- `fpdf/` - PDF generation (used by `fortbildung`, `anwesenheitsscanner`)
- `class-code128.php` - Barcode generation (used by attendance and training modules)

## Working with Modules

### Reading module configuration
```php
// Module config is in modules/{category}/{module-id}/module.json
$config = json_decode(file_get_contents($module_path . '/module.json'), true);
```

### Module activation state
```php
// Settings stored in wp_options table as 'dgptm_suite_settings'
$settings = get_option('dgptm_suite_settings', []);
$active_modules = $settings['active_modules'] ?? [];
// Format: ['module-id' => true/false, ...]
```

### Adding a new module
1. Create directory: `modules/{category}/{module-id}/`
2. Add main PHP file with proper WordPress plugin header
3. Create `module.json` with proper configuration
4. Add class_exists check: `if (!class_exists('My_Class')) { class My_Class { ... } }`
5. Add initialization guard to prevent double-loading
6. Module will auto-appear in dashboard on next load

**Example module structure:**
```php
<?php
/**
 * Plugin Name: My Module
 * Description: Module description
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent class redeclaration
if (!class_exists('My_Module_Class')) {
    class My_Module_Class {
        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            // Initialize
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['my_module_initialized'])) {
    $GLOBALS['my_module_initialized'] = true;
    My_Module_Class::get_instance();
}
```

### Module Loading Process

The loader (`class-module-loader.php`):
1. Scans all module directories for `module.json` files
2. Reads active module list from WordPress options
3. Sorts modules by dependency order (topological sort)
4. Loads each module's main file via Safe Loader
5. Fires `dgptm_suite_modules_loaded` action hook
6. Auto-deactivates modules with errors
7. Logs all operations with context (admin vs frontend)

**Load order:** Core infrastructure → Dependencies → Business logic → Utilities

**Critical modules** (with `"critical": true` in module.json):
- Cannot be deactivated via dashboard
- Are auto-reactivated if accidentally deactivated
- Examples: `crm-abruf`, `menu-control`, `otp-login`

### Export System

The `class-zip-generator.php` creates standalone plugins:
1. Copies module files to temporary directory
2. Generates standalone plugin header
3. Includes required dependencies (libraries, core functions)
4. Creates ZIP file in `exports/` directory
5. Serves download to admin user

### Admin Dashboard

Located at WordPress Admin → DGPTM Suite:
- **Dashboard** (`views/dashboard.php`) - Module grid with status indicators, info buttons
- **Module Settings** (`views/module-settings.php`) - Individual module configuration
- **Updates** (`views/updates.php`) - Update management interface
- **Export** (`views/export.php`) - Bulk export functionality
- **System Logs** (`views/logs.php`) - Debug log viewer with filtering
- **Module Guides** (`views/guides.php`) - Module documentation

**Module Actions:**
- Info button (ℹ️) opens metadata modal (flags, comments, version switching)
- Toggle button activates/deactivates modules
- Export button generates standalone ZIP
- Checkout button (for development) locks module for editing

## Important Files & Their Purposes

- `dgptm-master.php` - Main plugin file, WordPress plugin header, initialization
- `categories.json` - Central definitions for all categories and flags (file-based metadata system)
- `DEPENDENCIES.md` - Comprehensive dependency matrix for all modules
- `INSTALLATION.md` - Step-by-step installation guide
- `README.md` - Project overview, features, and quick start
- `CLAUDE.md` - This file - guidance for Claude Code
- `analyze-dependencies.php` - Standalone script to analyze module dependencies
- `debug.log` - WordPress debug log (auto-rotated when over 50MB)

## External Integrations

Several modules integrate with external services:

- **Zoho CRM** - `crm-abruf` provides OAuth2 integration and API endpoints
- **Stripe** - `stripe-formidable` handles payment processing
- **GoCardless** - `gocardless` manages SEPA direct debits
- **Microsoft 365** - `microsoft-gruppen` uses Graph API for group management
- **Claude AI** - `wissens-bot` provides AI-powered knowledge search
- **Zoom** - `abstimmen-addon` and `event-tracker` integrate meeting APIs
- **Vimeo** - `vimeo-streams` manages video embeds
- **Elementor** - `herzzentren`, `frontend-page-editor` integrate with Elementor

API credentials and configuration are stored in WordPress options (wp_options table) or individual module settings.

## Development Environment

**Requirements:**
- PHP 7.4+
- WordPress 5.8+
- ZipArchive PHP extension for export functionality
- Advanced Custom Fields (ACF) plugin - used extensively

**Environment Notes:**
- Platform: Windows (use Windows path separators)
- PHP may not be in system PATH - use WordPress admin interface for scripts
- German language strings common (DGPTM is a German organization)

**GitHub Repository:** https://github.com/sebmel75/DGPTMSuite

## Testing Modules

To test a module:
1. Navigate to DGPTM Suite dashboard in WordPress admin
2. Check dependency warnings before activation
3. Activate the module
4. Check WordPress debug.log for errors: `dgptm-plugin-suite/../debug.log`
5. Verify module functionality in WordPress frontend/admin
6. Check browser console for JavaScript errors

**Using the System Logs:**
1. Go to DGPTM Suite → System Logs
2. Filter by level (Critical, Error, Warning, Info, Verbose)
3. Filter by module or time range
4. Logs auto-rotate when exceeding 50MB (keeps last 3 backups)

## Key WordPress Hooks

Modules can use these WordPress hooks:
- `plugins_loaded` - Module loading happens here (priority 1)
- `dgptm_suite_modules_loaded` - Fired after all modules loaded
- `dgptm_suite_module_loaded` - Fired after individual module loads (receives $module_id, $config)
- `dgptm_suite_module_metadata_updated` - Fired when module metadata changes
- `init` - WordPress initialization (use for registering post types, taxonomies)
- `admin_menu` - Adding admin menu items
- `wp_enqueue_scripts` - Enqueuing frontend assets
- `admin_enqueue_scripts` - Enqueuing admin assets

## Security Considerations

- All admin operations require `manage_options` capability
- Nonce verification on all AJAX calls (`dgptm_suite_nonce`)
- Input sanitization using WordPress functions
- ABSPATH checks in all PHP files prevent direct access
- File upload handling with type validation
- API credentials stored in WordPress options (should be encrypted for production)
- Class redeclaration checks prevent conflicts
- Initialization guards prevent double-loading

## Module Export Format

Exported plugins include:
- Original module files
- Standalone plugin header
- Required shared libraries
- Dependency declarations
- Installation instructions
- Can be installed on any WordPress site independently

## Common Issues and Solutions

### "Class already declared" errors
**Cause:** Module loaded multiple times or class name collision

**Solution:**
```php
if (!class_exists('My_Class')) {
    class My_Class { ... }
}

if (!isset($GLOBALS['my_module_initialized'])) {
    $GLOBALS['my_module_initialized'] = true;
    My_Class::get_instance();
}
```

### Modules auto-deactivated at night
**Cause:** Safe Loader's isolated test (now disabled) ran without WordPress context

**Solution:** Already fixed - isolated test is disabled in `class-safe-loader.php:61-68`

### Module won't activate
**Possible causes:**
1. Missing dependencies - check module info
2. Required WordPress plugins not active
3. PHP version mismatch
4. Syntax errors in module code

**Debug:**
1. Check DGPTM Suite → System Logs
2. Filter for the module ID
3. Look for error messages
4. Check dependency warnings in dashboard

### Frontend Page Editor issues
**Module:** `frontend-page-editor` allows non-admin users to edit pages with Elementor

**Key concept:** Temporarily changes user role to "editor" during session (not just capabilities)

**Common issue:** Elementor requires actual role, not just capabilities

**Solution implemented:** Role switching instead of capability granting

## Notes for Development

- Module loading is logged to error_log with context (admin vs frontend)
- Settings are stored in `dgptm_suite_settings` option (not cached by Object Cache)
- Module metadata stored in individual `module.json` files (file-based system)
- Dashboard uses AJAX for module activation/deactivation
- CSS/JS assets are in `admin/assets/` directory
- German language strings used in many modules (DGPTM is German organization)
- Version numbers extracted from plugin headers, not module.json
- Logs are automatically rotated when exceeding 50MB (keeps last 3 backups)
- Critical modules have auto-reactivation protection

## AJAX Handlers in class-plugin-manager.php

**Module Operations:**
- `dgptm_toggle_module` - Activate/deactivate module
- `dgptm_export_module` - Generate standalone ZIP
- `dgptm_get_module_info` - Get module config, dependencies, and metadata
- `dgptm_create_module` - Create new module via generator
- `dgptm_test_module` - Test module loading
- `dgptm_delete_module` - Delete module files

**Module Metadata:**
- `dgptm_add_flag` - Add flag to module
- `dgptm_remove_flag` - Remove flag from module
- `dgptm_set_comment` - Save module comment
- `dgptm_switch_version` - Switch between main/test version
- `dgptm_link_test_version` - Link main and test modules

**Checkout System:**
- `dgptm_checkout_module` - Lock module for editing
- `dgptm_checkin_module` - Upload updated module
- `dgptm_cancel_checkout` - Cancel checkout

All handlers require `manage_options` capability and nonce verification.

## Common Module Patterns

### ACF Field Registration
Modules that use ACF often register fields programmatically:
```php
public function register_acf_fields() {
    if (!function_exists('acf_add_local_field_group')) {
        return; // ACF not active
    }

    acf_add_local_field_group([
        'key' => 'group_unique_key',
        'title' => 'Field Group Title',
        'fields' => [ /* field definitions */ ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'custom_post_type'
                ]
            ]
        ]
    ]);
}
```

### Custom Post Type Registration
Standard pattern for registering custom post types:
```php
public function register_post_types() {
    // IMPORTANT: Register on 'init' hook, NOT in constructor
    add_action('init', [$this, 'do_register_post_types']);
}

public function do_register_post_types() {
    if (post_type_exists('custom_type')) {
        return; // Prevent double registration
    }

    register_post_type('custom_type', [
        'labels' => [ /* labels */ ],
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'custom-fields'],
        'rewrite' => ['slug' => 'custom-slug'],
    ]);
}
```

**Critical:** Always register post types on `init` hook, never in constructor, to avoid `Call to a member function add_rewrite_tag() on null` errors.

### AJAX Handler Pattern
Standard AJAX implementation:
```php
// Register AJAX handlers in constructor
add_action('wp_ajax_custom_action', [$this, 'ajax_handler']);
add_action('wp_ajax_nopriv_custom_action', [$this, 'ajax_handler']); // For non-logged users

// Enqueue script with localized data
public function enqueue_scripts() {
    wp_enqueue_script('custom-script',
        $this->plugin_url . 'assets/js/script.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('custom-script', 'customData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('custom_nonce')
    ]);
}

// AJAX handler
public function ajax_handler() {
    check_ajax_referer('custom_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    // Process request
    $result = /* ... */;

    wp_send_json_success(['data' => $result]);
}
```

### Dynamic ACF Field Detection
For modules that need to adapt to ACF field changes:
```php
public function get_dynamic_fields() {
    if (!function_exists('acf_get_field_group')) {
        return [];
    }

    $group = acf_get_field_group('group_key');
    if (!$group) {
        return [];
    }

    $fields = acf_get_fields('group_key');
    $dynamic_fields = [];

    foreach ($fields as $field) {
        if ($field['type'] === 'true_false') {
            $dynamic_fields[] = [
                'key' => $field['key'],
                'name' => $field['name'],
                'label' => $field['label']
            ];
        }
    }

    return $dynamic_fields;
}
```

This pattern is used in `acf-permissions-manager` to automatically detect permission fields.

## Recent Module Examples

### ACF Permissions Manager (`modules/utilities/acf-permissions-manager/`)
**Purpose:** Manage ACF user permissions from a specific field group

**Key Features:**
- Dynamic permission detection from ACF group
- Matrix view: all users × all permissions
- Batch assignment/revocation
- CSV export

**Architecture:**
- Main class reads ACF fields dynamically at runtime
- AJAX handlers for toggle, batch operations
- 4-tab interface: Overview, By Permission, By User, Batch Operations
- Custom toggle switches styled in CSS

**Code Pattern:**
```php
// Dynamic permission reading
public function get_all_permissions() {
    $fields = acf_get_fields($this->permissions_group_key);
    foreach ($fields as $field) {
        if ($field['type'] === 'true_false') {
            $permissions[] = [
                'key' => $field['key'],
                'name' => $field['name'],
                'label' => $field['label']
            ];
        }
    }
    return $permissions;
}
```

### Vimeo Webinar Manager (`modules/media/vimeo-webinare/`)
**Purpose:** Manage Vimeo webinars with progress tracking and certificates

**Key Features:**
- Vimeo API integration for batch import
- Time-based progress tracking (prevents skip-ahead)
- Automatic certificate generation via FPDF
- Integration with `fortbildung` module
- Email notifications on completion

**Architecture:**
- `class-vimeo-api.php` - REST API client for Vimeo
- Progress tracking via JavaScript tracking watched duration
- Completion triggers AJAX → creates fortbildung entry → generates PDF → sends email
- Batch import from Vimeo folders with duplicate detection

**Code Pattern:**
```php
// Vimeo API pagination
public function get_folder_videos($folder_id) {
    $all_videos = [];
    $page = 1;
    do {
        $response = $this->request("/me/projects/{$folder_id}/videos", 'GET', [
            'per_page' => 100,
            'page' => $page
        ]);
        if (isset($response['data'])) {
            $all_videos = array_merge($all_videos, $response['data']);
        }
        $has_more = ($page * 100) < $response['total'];
        $page++;
    } while ($has_more);
    return $all_videos;
}
```

### Elementor Doctor (`modules/utilities/elementor-doctor/`)
**Purpose:** Scan and repair Elementor pages with errors

**Key Features:**
- 10+ error detection types (JSON, structure, IDs, orphaned settings, CSS)
- Automatic backup before all repairs
- Batch processing with progress tracking
- Custom post type for backups with restore functionality

**Architecture:**
- `class-elementor-scanner.php` - Validates Elementor page data
- `class-elementor-repair.php` - Fixes errors, regenerates CSS
- Backup system stores all metadata for full restoration
- Processes pages in batches to avoid timeouts

**Critical Fix Applied:**
```php
// WRONG - causes error
public function __construct() {
    $this->register_backup_post_type(); // Too early!
}

// CORRECT - register on init hook
public function __construct() {
    add_action('init', [$this, 'register_backup_post_type']);
}
```

## Module Development Checklist

When creating a new module:

1. **Structure:**
   - [ ] Create directory in appropriate category folder
   - [ ] Add main PHP file with WordPress plugin header
   - [ ] Create `module.json` with all required fields
   - [ ] Add `class_exists()` check around class definition
   - [ ] Add initialization guard (`$GLOBALS` check)

2. **Initialization:**
   - [ ] Use singleton pattern with private constructor
   - [ ] Register post types/taxonomies on `init` hook, never in constructor
   - [ ] Register AJAX handlers in constructor
   - [ ] Use `add_action` for WordPress hooks

3. **Security:**
   - [ ] Add `ABSPATH` check at top of all PHP files
   - [ ] Verify nonces on all AJAX calls
   - [ ] Check `manage_options` capability for admin operations
   - [ ] Sanitize all input, escape all output

4. **Dependencies:**
   - [ ] List DGPTM module dependencies in module.json
   - [ ] List WordPress plugin dependencies in wp_dependencies
   - [ ] Check for required functions before using them
   - [ ] Provide graceful degradation when dependencies missing

5. **Assets:**
   - [ ] Enqueue scripts/styles with version numbers
   - [ ] Use `wp_localize_script()` for AJAX URL and nonce
   - [ ] Check if assets are needed before enqueuing
   - [ ] Use proper asset paths (plugin_url)

6. **Documentation:**
   - [ ] Add clear comments for complex logic
   - [ ] Document all public methods
   - [ ] Update DEPENDENCIES.md if adding dependencies
   - [ ] Consider adding module-specific README

## Known Issues and Solutions

### Post Type Registration Error
**Error:** `Call to a member function add_rewrite_tag() on null`
**Cause:** Calling `register_post_type()` too early (in constructor)
**Solution:** Always register on `init` hook

### Class Already Declared
**Error:** `Cannot redeclare class`
**Cause:** Module loaded multiple times
**Solution:** Wrap class in `if (!class_exists('Class_Name'))` and add initialization guard

### Modules Auto-Deactivated
**Cause:** Safe Loader's isolated test ran without WordPress context (now disabled)
**Status:** Fixed - isolated testing is disabled in `class-safe-loader.php`

### Missing Metadata Methods
**Error:** `Call to undefined method DGPTM_Module_Metadata_File::method_name()`
**Cause:** Using old metadata class name or missing migration
**Solution:** Use `DGPTM_Module_Metadata_File::get_instance()` and ensure all methods exist
