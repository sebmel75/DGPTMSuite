# DGPTM Plugin Suite - Master Controller

**Version:** 1.0.0
**Author:** Sebastian Melzer / DGPTM
**License:** GPL v2 or later

## 📋 Overview

DGPTM Plugin Suite is a comprehensive WordPress plugin management system that consolidates **33 individual modules** into a single, unified administration interface. It provides centralized control over all DGPTM plugins with features like individual activation/deactivation, dependency management, update handling, and standalone plugin export.

## ✨ Features

- **Centralized Dashboard** - Manage all 33 modules from one interface
- **Dependency Management** - Automatic dependency checking and resolution
- **Smart Activation** - Enable/disable modules individually with dependency warnings
- **Module Export** - Export any module as a standalone WordPress plugin (ZIP)
- **Update Management** - Centralized update system for all modules
- **Category Organization** - Modules grouped by function (Core, Business, Payment, etc.)
- **Search & Filter** - Quickly find modules by name, category, or status
- **Visual Status** - Clear indication of active, loaded, and dependency status

## 📦 Included Modules (33 total)

### Core Infrastructure (5)
- **crm-abruf** - Zoho CRM & API Endpoints (v1.8.0)
- **rest-api-extension** - Custom REST API Endpoints
- **webhook-trigger** - Webhook Management
- **menu-control** - Menu Visibility Control
- **side-restrict** - Page Access Restrictions

### Business Logic (9)
- **fortbildung** - Training Management (v1.57)
- **quiz-manager** - Quiz Management (v2.4.7)
- **herzzentren** - Heart Center Editor (v4.0.1)
- **timeline-manager** - Timeline Management (v1.6.7)
- **event-tracker** - Event Routing & Webhooks (v1.16.2)
- **abstimmen-addon** - Online Voting System (v2.0)
- **microsoft-gruppen** - MS365 Group Management (v1.5.3)
- **anwesenheitsscanner** - Attendance Scanner (v2.0)
- **gehaltsstatistik** - Salary Statistics

### Payment (2)
- **stripe-formidable** - Stripe Payment Integration (v3.0)
- **gocardless** - GoCardless Direct Debit (v1.20)

### Authentication (1)
- **otp-login** - OTP Login System (v3.4.0)

### Media & Content (4)
- **vimeo-streams** - Vimeo Stream Manager (v3.0.4)
- **wissens-bot** - Claude AI Knowledge Bot (v1.0.0)
- **news-management** - News Editing System
- **publication-workflow** - Publication Management

### ACF Tools (3)
- **acf-anzeiger** - ACF Field Display
- **acf-toggle** - ACF Toggle Functions
- **acf-jetsync** - ACF JetEngine Sync

### Utilities (8)
- **kiosk-jahrestagung** - Kiosk Mode
- **exif-data** - EXIF Data Manager
- **blaue-seiten** - Directory (Blue Pages)
- **shortcode-tools** - Shortcode Editors
- **stellenanzeige** - Job Postings
- **conditional-logic** - Conditional Content
- **installer** - Plugin Installer
- **zoho-role-manager** - Zoho Role Manager

## 🚀 Installation

1. Upload `dgptm-plugin-suite` folder to `/wp-content/plugins/`
2. Activate "DGPTM Plugin Suite - Master Controller" in WordPress
3. Navigate to **DGPTM Suite** in the admin menu
4. Enable desired modules from the dashboard

## 📖 Usage

### Activating Modules

1. Go to **DGPTM Suite → Dashboard**
2. Browse modules by category
3. Click **Activate** on any module
4. Dependencies are automatically checked

### Exporting Modules

1. Go to **DGPTM Suite → Dashboard**
2. Click the **Export** button next to any module
3. Download the generated ZIP file
4. Install on any WordPress site as a standalone plugin

### Bulk Operations

1. Select multiple modules using checkboxes
2. Choose action from dropdown (Activate, Deactivate, Export)
3. Click **Apply**

### Search & Filter

- **Search:** Type module name or ID
- **Category Filter:** Show only specific category
- **Status Filter:** Show only active or inactive modules

## ⚙️ Requirements

- **PHP:** 7.4 or higher
- **WordPress:** 5.8 or higher
- **Extensions:** ZipArchive (for export functionality)

### Module-Specific Requirements

Some modules require additional WordPress plugins:
- **ACF Modules:** Advanced Custom Fields
- **Herzzentren:** Elementor + ACF
- **Payment Modules:** Formidable Forms
- **Quiz Manager:** Quiz Maker plugin

## 📂 Directory Structure

```
dgptm-plugin-suite/
├── dgptm-master.php                    # Main plugin file
├── README.md
├── LICENSE
│
├── admin/                              # Admin interface
│   ├── class-plugin-manager.php
│   ├── views/
│   │   ├── dashboard.php
│   │   ├── module-settings.php
│   │   ├── updates.php
│   │   └── export.php
│   └── assets/
│       ├── css/admin.css
│       └── js/admin.js
│
├── core/                               # Core functionality
│   ├── class-module-base.php
│   ├── class-dependency-manager.php
│   ├── class-module-loader.php
│   ├── class-update-manager.php
│   └── class-zip-generator.php
│
├── modules/                            # All modules
│   ├── core-infrastructure/
│   ├── business/
│   ├── payment/
│   ├── auth/
│   ├── media/
│   ├── content/
│   ├── acf-tools/
│   └── utilities/
│
├── libraries/                          # Shared libraries
│   ├── fpdf/
│   └── class-code128.php
│
└── exports/                            # Generated exports
```

## 🔧 Development

### Adding New Modules

1. Create module directory in appropriate category folder
2. Add your plugin file(s)
3. Create `module.json` with configuration:

```json
{
  "id": "my-module",
  "name": "My Module",
  "description": "Module description",
  "version": "1.0.0",
  "author": "Your Name",
  "main_file": "my-module.php",
  "dependencies": [],
  "optional_dependencies": [],
  "wp_dependencies": {"plugins": []},
  "requires_php": "7.4",
  "requires_wp": "5.8",
  "category": "utilities",
  "icon": "dashicons-admin-plugins",
  "active": false,
  "can_export": true
}
```

### Module Configuration

- **id:** Unique module identifier (slug)
- **dependencies:** Required DGPTM modules
- **optional_dependencies:** Optional DGPTM modules
- **wp_dependencies:** Required WordPress plugins
- **category:** Module category (see structure above)
- **icon:** Dashicons class name

## 🔒 Security

- All modules require `manage_options` capability
- Nonce verification on all AJAX calls
- Input sanitization and validation
- Dependency checks before activation
- ABSPATH checks in all files

## 📝 Changelog

### Version 1.0.0 (2025-11-19)
- Initial release
- 33 modules migrated to unified structure
- Complete dependency management system
- Module export functionality
- Centralized admin interface
- Update management system

## 🐛 Troubleshooting

### Module Won't Activate
- Check dependencies in module info
- Ensure required WordPress plugins are active
- Check PHP version requirements

### Export Fails
- Ensure ZipArchive extension is installed
- Check write permissions on exports folder
- Verify module files exist

### Missing Dependencies Warning
- Install required WordPress plugins
- Activate dependent DGPTM modules first
- Check module.json configuration

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## 👥 Credits

Developed by Sebastian Melzer for DGPTM (Deutsche Gesellschaft für Prävention und Telemedizin e.V.)

## 🔗 Links

- **Website:** https://www.dgptm.de/
- **Support:** https://github.com/dgptm/plugin-suite/issues

---

**Made with ❤️ for DGPTM**
