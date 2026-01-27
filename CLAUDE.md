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

**GitHub Repository:** https://github.com/sebmel75/DGPTMSuite

**Global Constants:** (defined in `dgptm-master.php`)
- `DGPTM_SUITE_VERSION` - Current version (3.0.0)
- `DGPTM_SUITE_PATH` - Plugin directory path
- `DGPTM_SUITE_URL` - Plugin URL
- `DGPTM_SUITE_FILE` - Main plugin file path
- `DGPTM_SUITE_BASENAME` - Plugin basename

**Global Accessor:** `dgptm_suite()` returns the main plugin instance (`DGPTM_Plugin_Suite`)

## Commands

**PHP Syntax Check (local):**
```bash
php -l path/to/file.php
```

**Dependency Analysis:**
```bash
php analyze-dependencies.php
```

**Deployment:** Push to `main` branch triggers automatic CI/CD deployment to perfusiologie.de via GitHub Actions

## Project Overview

DGPTM Plugin Suite is a WordPress plugin management system consolidating 50+ individual modules into a unified administration interface. The project provides centralized control over DGPTM plugins with dependency management, individual activation/deactivation, update handling, standalone plugin export functionality, and module metadata management (flags, comments, version switching).

**Organization:** DGPTM (Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V.)

## Architecture

### Core Structure

1. **Master Controller** (`dgptm-master.php`)
   - Entry point and initialization
   - Loads core classes and admin interface
   - Manages plugin lifecycle hooks

2. **Core System** (`core/`)
   - `class-module-loader.php` - Dynamic module loading based on activation state
   - `class-dependency-manager.php` - Handles module dependencies
   - `class-safe-loader.php` - Safe loading with error handling (isolate test DISABLED)
   - `class-module-metadata-file.php` - File-based metadata (flags, comments, version links)
   - `class-module-base.php` - Base class for modules to extend
   - `class-logger.php` - Logging system with database storage
   - `class-logger-installer.php` - Database table creation for logs
   - `class-zip-generator.php` - Exports modules as standalone plugins
   - `class-module-generator.php` - Creates new module scaffolding
   - `class-checkout-manager.php` - Module checkout for editing
   - `class-module-settings-manager.php` - Per-module settings management
   - `class-central-settings-registry.php` - Centralized settings management
   - `class-version-extractor.php` - Extracts versions from plugin headers
   - `class-guide-manager.php` - Module documentation/guides system
   - `class-test-version-manager.php` - Links main modules to test versions
   - `class-dgptm-colors.php` - Color utilities for admin UI

3. **Admin Interface** (`admin/`)
   - `class-plugin-manager.php` - Main admin dashboard controller with AJAX handlers
   - `class-module-upload-handler.php` - Handles module ZIP uploads
   - `views/` - Dashboard, settings, export, updates, logs, and metadata modal views
   - `assets/` - Admin CSS and JavaScript

4. **Module Categories** (`modules/`) - defined in `categories.json`
   - `core-infrastructure/` - CRM, API, webhooks, menu control, side-restrict, otp-login, role-manager
   - `business/` - fortbildung, quiz-manager, event-tracker, session-display, timeline-manager, microsoft-gruppen
   - `payment/` - stripe-formidable, gocardless, daten-bearbeiten
   - `auth/` - otp-login
   - `media/` - vimeo-webinare, vimeo-streams, wissens-bot, kardiotechnik-archiv
   - `content/` - news-management, publication-workflow, herzzentren, ebcp-guidelines, mitgliedsantrag, stellenanzeige
   - `acf-tools/` - acf-anzeiger, acf-jetsync, acf-toggle, acf-permissions-manager
   - `utilities/` - elementor-doctor, elementor-ai-export, frontend-page-editor, shortcode-tools, role-manager

5. **Shared Libraries** (`libraries/`)
   - `fpdf/` - PDF generation (used by `fortbildung`, `anwesenheitsscanner`)
   - `class-code128.php` - Barcode generation

### Module System

Each module:
- Lives in its category folder (e.g., `modules/business/fortbildung/`)
- Contains a `module.json` configuration file
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
  "wp_dependencies": {
    "plugins": ["elementor", "advanced-custom-fields"]
  },
  "requires_php": "7.4",
  "requires_wp": "5.8",
  "category": "business",
  "icon": "dashicons-location",
  "active": false,
  "critical": false,
  "flags": ["production"],
  "comment": {"text": "Aktiv in Produktion", "timestamp": 1700000000, "user_id": 1}
}
```

### Module Metadata System (File-Based)

**IMPORTANT:** Metadata is stored in `module.json` files, NOT in `wp_options`.

**Available Flags (defined in categories.json):**
- `testing` (blue), `deprecated` (red), `important` (yellow)
- `development` (green), `production` (purple), `beta` (light blue)

**Accessing metadata:**
```php
$metadata = DGPTM_Module_Metadata_File::get_instance();
$metadata->add_flag('module-id', 'testing');
$metadata->set_comment('module-id', 'Needs update');
$metadata->link_test_version('main-module', 'test-module');
```

### Dependency Management

**Key Dependency Chains:**
- Training: Quiz Maker plugin → `quiz-manager` → `fortbildung` → FPDF library
- Voting: `crm-abruf` + `webhook-trigger` → `abstimmen-addon` → Zoom API
- Heart Centers: ACF plugin → `herzzentren` → Elementor widgets
- Events: `webhook-trigger` + `crm-abruf` → `event-tracker`

### Safe Loading System

**IMPORTANT:** `class-safe-loader.php` has isolated test mode DISABLED because it was causing false positives (loading modules without WordPress context).

**Do NOT re-enable isolated testing** without first ensuring it runs in WordPress context.

## Working with Modules

### Adding a new module
1. Create directory: `modules/{category}/{module-id}/`
2. Add main PHP file with proper WordPress plugin header
3. Create `module.json` with proper configuration
4. Add class_exists check and initialization guard
5. Module will auto-appear in dashboard on next load

**Required module structure:**
```php
<?php
/**
 * Plugin Name: My Module
 * Description: Module description
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

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
            // Initialize - register post types on 'init' hook, NOT here!
            add_action('init', [$this, 'register_post_types']);
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

**Load order:** Core infrastructure → Dependencies → Business logic → Utilities

**Critical modules** (with `"critical": true`): Cannot be deactivated, auto-reactivated if needed.

## External Integrations

- **Zoho CRM** - `crm-abruf` provides OAuth2 integration and API endpoints
- **Stripe** - `stripe-formidable` handles payment processing
- **GoCardless** - `gocardless` manages SEPA direct debits
- **Microsoft 365** - `microsoft-gruppen` uses Graph API
- **Claude AI** - `wissens-bot` provides AI-powered knowledge search
- **Zoom** - `abstimmen-addon` and `event-tracker` integrate meeting APIs
- **Vimeo** - `vimeo-streams` and `vimeo-webinare` manage video embeds
- **Elementor** - `herzzentren`, `frontend-page-editor` integrate with Elementor

## Key WordPress Hooks

- `plugins_loaded` - Module loading happens here (priority 1)
- `dgptm_suite_modules_loaded` - Fired after all modules loaded
- `dgptm_suite_module_loaded` - Fired after individual module loads
- `init` - Register post types, taxonomies HERE, never in constructor
- `admin_menu` - Adding admin menu items
- `wp_enqueue_scripts` / `admin_enqueue_scripts` - Enqueuing assets

## AJAX Handlers in class-plugin-manager.php

**Module Operations:**
- `dgptm_toggle_module`, `dgptm_export_module`, `dgptm_get_module_info`
- `dgptm_create_module`, `dgptm_test_module`, `dgptm_delete_module`

**Module Metadata:**
- `dgptm_add_flag`, `dgptm_remove_flag`, `dgptm_set_comment`
- `dgptm_switch_version`, `dgptm_link_test_version`

**Checkout System:**
- `dgptm_checkout_module`, `dgptm_checkin_module`, `dgptm_cancel_checkout`

All handlers require `manage_options` capability and nonce verification (`dgptm_suite_nonce`).

## Common Module Patterns

### Custom Post Type Registration
```php
// CRITICAL: Always register on 'init' hook, never in constructor
public function __construct() {
    add_action('init', [$this, 'register_post_types']);
}

public function register_post_types() {
    if (post_type_exists('custom_type')) return;
    register_post_type('custom_type', [...]);
}
```

### AJAX Handler Pattern
```php
// Register in constructor
add_action('wp_ajax_custom_action', [$this, 'ajax_handler']);

// Enqueue with localization
wp_localize_script('custom-script', 'customData', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('custom_nonce')
]);

// Handler
public function ajax_handler() {
    check_ajax_referer('custom_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    wp_send_json_success(['data' => $result]);
}
```

### ACF Field Registration
```php
public function register_acf_fields() {
    if (!function_exists('acf_add_local_field_group')) return;
    acf_add_local_field_group([...]);
}
```

## Common Issues and Solutions

### Post Type Registration Error
**Error:** `Call to a member function add_rewrite_tag() on null`
**Solution:** Always register post types on `init` hook, never in constructor.

### Class Already Declared
**Error:** `Cannot redeclare class`
**Solution:** Wrap class in `if (!class_exists('Class_Name'))` and add `$GLOBALS` initialization guard.

### Modules Auto-Deactivated
**Cause:** Safe Loader's isolated test ran without WordPress context (now disabled)
**Status:** Fixed - isolated testing is disabled in `class-safe-loader.php:61-68`

### Module won't activate
1. Check DGPTM Suite → System Logs
2. Filter for the module ID
3. Look for dependency warnings in dashboard
4. Check WordPress debug.log for PHP errors

## Testing Modules

1. Navigate to DGPTM Suite dashboard in WordPress admin
2. Check dependency warnings before activation
3. Activate the module
4. Check WordPress debug.log for errors
5. Verify functionality in frontend/admin
6. Check browser console for JavaScript errors

**System Logs:** DGPTM Suite → System Logs (filter by level, module, time range)

## Deployment

**CI/CD:** GitHub Actions workflow (`.github/workflows/deploy.yml`)
- Triggers on push to `main` or manual dispatch
- Runs PHP syntax check on first 100 PHP files
- Verifies critical files exist
- Creates backup on server before deployment
- Deploys via rsync over SSH
- Target: perfusiologie.de

**Required GitHub Secrets:**
- `SSH_HOST`, `SSH_USER`, `SSH_PASSWORD` - Server credentials
- `WP_PATH` - WordPress installation path on server

**Manual Deployment:**
1. Push to `main` branch triggers automatic deployment
2. Or use GitHub Actions → "Run workflow" for manual trigger

## Development Notes

- German language strings are common (DGPTM is a German medical organization)
- Version numbers extracted from plugin headers, not module.json (see `class-version-extractor.php`)
- Logs stored in database with auto-cleanup (configurable max entries, default 100000)
- CSS/JS assets are in `admin/assets/` directory
- Platform: Windows development, Production: Linux server (perfusiologie.de)
- Default active modules on fresh install: `crm-abruf`, `rest-api-extension`, `webhook-trigger`, `menu-control`, `side-restrict`

## Important Files

- `dgptm-master.php` - Main plugin file, initialization, defines constants and loads core classes
- `categories.json` - Category and flag definitions (8 categories, 6 flags)
- `DEPENDENCIES.md` - Comprehensive dependency matrix with all external API integrations
- `README.md` - Project overview and module list (German)
- `analyze-dependencies.php` - Standalone dependency analysis script
- `.github/workflows/deploy.yml` - CI/CD pipeline (rsync over SSH, auto-backup before deploy)
