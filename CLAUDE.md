# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Quick Reference

**Stack:** WordPress 5.8+ / PHP 7.4+ | **Version:** 3.0.0 | **Modules:** 67 across 8 categories

**Key Paths:**
- Entry point: `dgptm-master.php`
- Core classes: `core/class-*.php` (18 classes)
- Admin controller: `admin/class-plugin-manager.php` (AJAX handlers, ~1900 lines)
- Admin views: `admin/views/*.php` (9 templates)
- Module definitions: `modules/{category}/{module-id}/module.json`
- Category & flag definitions: `categories.json`
- CI/CD: `.github/workflows/deploy.yml`

**Global Constants:** `DGPTM_SUITE_VERSION`, `DGPTM_SUITE_PATH`, `DGPTM_SUITE_URL`, `DGPTM_SUITE_FILE`, `DGPTM_SUITE_BASENAME`

**Global Accessor:** `dgptm_suite()` returns the singleton `DGPTM_Plugin_Suite` instance

**GitHub:** https://github.com/sebmel75/DGPTMSuite
**Production:** perfusiologie.de (Linux, deployed via GitHub Actions rsync over SSH)

## Project Overview

DGPTM Plugin Suite is a WordPress meta-plugin that consolidates 67 individual modules into a unified administration interface for DGPTM (Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V.). It provides centralized activation/deactivation, dependency management, module metadata (flags/comments), version switching, standalone export, and logging.

German language strings are common throughout the codebase.

## Architecture

### Initialization Flow

1. `dgptm-master.php` defines constants, loads 18 core classes + admin classes (if `is_admin()`)
2. `DGPTM_Plugin_Suite` singleton initializes `DGPTM_Dependency_Manager` and `DGPTM_Module_Loader`
3. On `plugins_loaded` (priority 1): `module_loader->load_modules()` scans `modules/` for `module.json` files, resolves dependency order via topological sort, loads each active module through `DGPTM_Safe_Loader`
4. Hook `dgptm_suite_modules_loaded` fires when all modules are loaded

**Load order:** Core infrastructure → dependency-sorted modules → business logic → utilities

### Data Storage

| Data | Storage | Key |
|------|---------|-----|
| Module activation state | `wp_options` | `dgptm_suite_settings` → `active_modules` |
| Module metadata (flags, comments, version links) | **File-based** in each `module.json` | NOT in `wp_options` |
| Logs | Custom DB table | Created by `DGPTM_Logger_Installer` |
| Per-module settings | `wp_options` | Via `DGPTM_Module_Settings_Manager` |

**Important:** Module metadata migrated from database (`class-module-metadata.php`) to file-based (`class-module-metadata-file.php`). The legacy class still exists but the file-based version is the current standard.

### Module System

Each module lives in `modules/{category}/{module-id}/` with a `module.json` and a main PHP file. Modules follow the singleton pattern with `class_exists` guard and `$GLOBALS` initialization guard (see boilerplate below).

**Categories** (defined in `categories.json`): core-infrastructure, business, payment, auth, media, content, acf-tools, utilities

**Flags** (defined in `categories.json`): testing (blue), deprecated (red), important (yellow), development (green), production (purple), beta (light blue)

**Critical modules** (`"critical": true` in `module.json`): Cannot be deactivated, auto-reactivated. Core infrastructure modules like `crm-abruf`, `menu-control` are critical.

### Key Dependency Chains

- Training: Quiz Maker plugin → `quiz-manager` → `fortbildung` → FPDF library
- Voting: `crm-abruf` + `webhook-trigger` → `abstimmen-addon` → Zoom API
- Heart Centers: ACF + Elementor → `herzzentren`
- Events: `webhook-trigger` + `crm-abruf` → `event-tracker`

### Safe Loader Warning

`class-safe-loader.php` has isolated test mode **DISABLED** (lines 61-68). It was causing mass auto-deactivation by loading modules without WordPress context. **Do NOT re-enable** without ensuring WordPress context is available during testing.

## Module Boilerplate

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
            // CRITICAL: Register post types on 'init', never in constructor
            add_action('init', [$this, 'register_post_types']);
        }
    }
}

if (!isset($GLOBALS['my_module_initialized'])) {
    $GLOBALS['my_module_initialized'] = true;
    My_Module_Class::get_instance();
}
```

**Common mistake:** Registering post types in the constructor causes `Call to a member function add_rewrite_tag() on null`. Always use the `init` hook.

## AJAX Handlers

All AJAX handlers in `admin/class-plugin-manager.php` require `manage_options` capability and nonce verification (`dgptm_suite_nonce`).

**Module ops:** `dgptm_toggle_module`, `dgptm_export_module`, `dgptm_get_module_info`, `dgptm_create_module`, `dgptm_test_module`, `dgptm_delete_module`

**Metadata:** `dgptm_add_flag`, `dgptm_remove_flag`, `dgptm_set_comment`, `dgptm_switch_version`, `dgptm_link_test_version`

**Checkout:** `dgptm_checkout_module`, `dgptm_checkin_module`, `dgptm_cancel_checkout`

## External Integrations

- **Zoho CRM** (`crm-abruf`) - OAuth2, central API layer used by many business modules
- **Stripe** (`stripe-formidable`) - Payment via Formidable Forms
- **GoCardless** (`gocardless`) - SEPA direct debits
- **Microsoft 365** (`microsoft-gruppen`) - Graph API for group management
- **Claude AI** (`wissens-bot`) - Knowledge search
- **Zoom** (`abstimmen-addon`, `event-tracker`) - Meeting API integration
- **Vimeo** (`vimeo-streams`, `vimeo-webinare`) - Video embeds, webinar player with anti-skip progress tracking
- **Elementor** (`herzzentren`, `frontend-page-editor`) - Page builder widgets and frontend editing

## Deployment

Push to `main` triggers GitHub Actions: PHP syntax check → rsync deploy to perfusiologie.de → cache flush.

**Required GitHub Secrets:** `SSH_HOST`, `SSH_USER`, `SSH_PASSWORD`, `WP_PATH`

The deploy excludes `.git`, `.github`, `*.md`, `.claude`, `exports/`, `*.log` from the package.

## Development Notes

- **Platform:** Windows development, Linux production. Use appropriate path separators.
- **PHP:** Not in PATH on dev machine. Locate executable manually if needed.
- **No test framework:** Testing is done via WordPress admin dashboard (activate module, check System Logs, check debug.log, check browser console).
- **Version numbers** are extracted from PHP plugin headers, not from `module.json`.
- **Logs** are stored in a custom database table with configurable max entries and auto-cleanup cron.
- **ACF (Advanced Custom Fields)** is a dependency for many modules. Always guard with `function_exists('acf_add_local_field_group')`.
- **Rewrite rules:** After adding/changing rewrite rules, flush permalinks (Settings → Permalinks → Save, or `flush_rewrite_rules()`).

## Key Custom Hooks

- `dgptm_suite_modules_loaded` - All modules finished loading
- `dgptm_suite_module_loaded` - Individual module loaded (receives `$module_id`, `$config`)

## Notable Module Details

- **vimeo-webinare:** Time-based progress tracking (not position-based) to prevent skip-forward cheating. Dynamic URL routing via `/wissen/webinar/{id}`. Completion creates a `fortbildung` post, generates PDF certificate via FPDF, sends email.
- **fortbildung:** Central training/certification post type used by vimeo-webinare, quiz-manager, and event-tracker. ACF fields: user, date, location, type, points, vnr, token, freigegeben.
- **frontend-page-editor:** Temporarily switches user role to "editor" (not just capabilities) because Elementor requires an actual role.
- **otp-login:** Microsoft OAuth integration with OTP fallback, rotating logo preloader.
