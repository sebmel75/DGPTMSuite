# DGPTM Plugin Suite - Installation Guide

## 🎯 Quick Start

1. **Backup Your Site** (Already done: `../BACKUP_2025-11-19_07-49-25/`)
2. **Upload Plugin**
3. **Activate**
4. **Configure Modules**

## 📋 Pre-Installation Checklist

- [ ] PHP 7.4+ installed
- [ ] WordPress 5.8+ running
- [ ] ZipArchive PHP extension enabled
- [ ] Site backup completed
- [ ] Required plugins identified

## 🚀 Step-by-Step Installation

### Step 1: Upload Plugin

**Option A: Via WordPress Admin**
1. Go to **Plugins → Add New**
2. Click **Upload Plugin**
3. Choose `dgptm-plugin-suite.zip`
4. Click **Install Now**
5. Click **Activate Plugin**

**Option B: Via FTP/File Manager**
1. Upload the `dgptm-plugin-suite` folder to `/wp-content/plugins/`
2. Go to **Plugins** in WordPress admin
3. Find "DGPTM Plugin Suite - Master Controller"
4. Click **Activate**

### Step 2: First-Time Configuration

After activation, you'll see **DGPTM Suite** in the admin menu.

1. **Navigate to Dashboard**
   - Click **DGPTM Suite** in admin sidebar
   - You'll see all 33 modules organized by category

2. **Review Module Status**
   - Core modules are pre-activated
   - All other modules start inactive

### Step 3: Activate Core Modules

**Recommended first activation order:**

1. **Core Infrastructure** (Essential - Pre-activated)
   - crm-abruf
   - rest-api-extension
   - webhook-trigger
   - menu-control
   - side-restrict

2. **Install Dependencies** (If needed)
   - Advanced Custom Fields (for ACF modules)
   - Elementor (for Herzzentren)
   - Formidable Forms (for Payment modules)
   - Quiz Maker (for Quiz Manager)

3. **Activate Business Modules** (As needed)
   - Start with modules you actively use
   - Dependencies are automatically checked

## ⚙️ Configuration

### Module Settings

Each module may have its own settings:

1. Go to **DGPTM Suite → Module Settings**
2. Select a module
3. Configure module-specific options
4. Save settings

### External API Configurations

Some modules require API keys:

**Zoho CRM (crm-abruf)**
- Configure OAuth2 credentials
- Set up API endpoints

**Stripe (stripe-formidable)**
- Add Stripe API keys
- Configure webhook URLs

**GoCardless (gocardless)**
- Set up GoCardless tokens
- Configure mandate settings

**Microsoft 365 (microsoft-gruppen)**
- Azure AD app registration
- Graph API permissions

**Claude AI (wissens-bot)**
- Anthropic API key
- Configure data sources

## 🔍 Verification

### Test Basic Functionality

```bash
# 1. Check if plugin is active
# WordPress Admin → Plugins → Ensure "DGPTM Plugin Suite" is active

# 2. Verify dashboard loads
# WordPress Admin → DGPTM Suite → Should show all 33 modules

# 3. Test module activation
# Click "Activate" on a simple module (e.g., conditional-logic)
# Should activate without errors

# 4. Test module deactivation
# Click "Deactivate" on the same module
# Should deactivate successfully

# 5. Test export functionality
# Click "Export" on any module
# Should download a ZIP file
```

### Verify File Permissions

```bash
# Exports folder must be writable
chmod 755 wp-content/plugins/dgptm-plugin-suite/exports/

# Check library access
ls -la wp-content/plugins/dgptm-plugin-suite/libraries/
```

## 🐛 Common Installation Issues

### Issue: Plugin doesn't appear in list

**Solution:**
- Verify folder is named exactly `dgptm-plugin-suite`
- Check that `dgptm-master.php` exists in plugin root
- Ensure PHP version is 7.4+

### Issue: "Missing dependencies" error

**Solution:**
- Install required WordPress plugins first
- Check **DEPENDENCIES.md** for requirements
- Activate prerequisite DGPTM modules

### Issue: Export fails

**Solution:**
- Install ZipArchive extension: `php -m | grep zip`
- Check exports folder permissions
- Ensure disk space available

### Issue: Modules don't load

**Solution:**
- Check PHP error log: `wp-content/debug.log`
- Verify module.json files exist
- Confirm main_file paths are correct

## 📊 Module Activation Order

For optimal setup, activate in this order:

1. **Core Infrastructure** (Essential)
   ```
   ✓ crm-abruf
   ✓ rest-api-extension
   ✓ webhook-trigger
   ✓ menu-control
   ✓ side-restrict
   ```

2. **ACF Tools** (If using ACF)
   ```
   - acf-anzeiger
   - acf-toggle
   - acf-jetsync
   ```

3. **Business Modules** (As needed)
   ```
   - quiz-manager (required by fortbildung)
   - fortbildung
   - event-tracker (requires webhook-trigger)
   - abstimmen-addon (requires webhook-trigger)
   - others as needed
   ```

4. **Specialized Modules**
   ```
   - Payment: stripe-formidable, gocardless
   - Auth: otp-login
   - Media: vimeo-streams, wissens-bot
   - Content: news-management, publication-workflow
   - Utilities: as needed
   ```

## 🔐 Security Hardening

### Recommended Settings

1. **Restrict Access**
   - Only administrators should access DGPTM Suite
   - Consider adding extra authentication

2. **File Permissions**
   ```bash
   chmod 644 dgptm-plugin-suite/dgptm-master.php
   chmod 755 dgptm-plugin-suite/exports/
   ```

3. **Disable Unused Modules**
   - Only activate modules you need
   - Reduces attack surface

4. **Regular Updates**
   - Check **DGPTM Suite → Updates** regularly
   - Keep all modules current

## 📱 Multisite Installation

For WordPress Multisite:

1. **Network Activate**
   - Go to **Network Admin → Plugins**
   - Click **Network Activate** on DGPTM Suite

2. **Per-Site Configuration**
   - Each site can activate different modules
   - Settings are site-specific

3. **Shared Modules**
   - Core modules should be active network-wide
   - Business modules can vary per site

## ✅ Post-Installation Checklist

- [ ] Plugin activated successfully
- [ ] Dashboard accessible
- [ ] Core modules active
- [ ] Required WP plugins installed
- [ ] API keys configured (if needed)
- [ ] Test module activation/deactivation
- [ ] Test export functionality
- [ ] Error log checked (no errors)
- [ ] Backup verified
- [ ] Documentation reviewed

## 🎓 Next Steps

1. **Review Module Docs**
   - Read `DEPENDENCIES.md` for module relationships
   - Understand which modules you need

2. **Configure APIs**
   - Set up external service credentials
   - Test API connections

3. **Test Exports**
   - Export a sample module
   - Install on test site
   - Verify functionality

4. **Plan Activation**
   - Decide which modules to enable
   - Consider dependencies
   - Enable incrementally

## 📞 Support

If you encounter issues:

1. **Check Documentation**
   - README.md
   - DEPENDENCIES.md
   - This guide

2. **Review Logs**
   - WordPress debug.log
   - PHP error log
   - Browser console

3. **Get Help**
   - Contact DGPTM support
   - Check GitHub issues
   - Review module-specific docs

---

**Installation complete!** 🎉

Go to **DGPTM Suite → Dashboard** to start managing your modules.
