# DGPTM Plugin Suite - Dependency Matrix

## Analyzed: 2025-11-19

## Module Dependencies

### Core Infrastructure Modules (Required by others)

#### **crm-abruf** (Zoho CRM & API Endpoints)
- **Provides**:
  - Functions: `dgptm_log()`, `dgptm_redact_array()`, `dgptm_is_debug_enabled()`
  - Shortcodes: `[zoho_api_data]`, `[zoho_api_data_ajax]`, `[ifcrmfield]`, `[api-abfrage]`, `[efn_barcode]`
  - API Endpoints: Custom REST endpoints
  - Zoho OAuth2 integration
- **Required by**: event-tracker, abstimmen-addon, quiz-manager, fortbildung
- **WordPress Dependencies**: None
- **External APIs**: Zoho CRM API

#### **rest-api-extension** (REST API Extensions)
- **Provides**: Custom REST API endpoints
- **Required by**: Multiple modules using custom endpoints
- **WordPress Dependencies**: WP REST API (core)

#### **webhook-trigger** (Webhook Manager)
- **Provides**: Webhook triggering functionality
- **Required by**: event-tracker, abstimmen-addon
- **WordPress Dependencies**: None

#### **acf-tools** (ACF Utilities)
- **Provides**:
  - `acf-anzeiger.php`: ACF field display
  - `acf-toggle.php` (einausblendenacf.php): ACF toggle functions
  - `acfjetsync.php`: ACF JetEngine sync
- **Required by**: herzzentren, fortbildung, multiple modules
- **WordPress Dependencies**: Advanced Custom Fields (ACF)

---

### Business Logic Modules

#### **fortbildung** (DGPTM-Fortbildung/)
- **Dependencies**:
  - INTERNAL: quiz-manager (Quiz-Import)
  - INTERNAL: crm-abruf (optional, EFN-Integration)
  - LIBRARY: FPDF (PDF generation)
  - WP PLUGIN: Advanced Custom Fields
- **Provides**:
  - Shortcodes: Training list display
  - PDF certificate generation
  - Custom post type: fortbildung
- **Sub-modules**:
  - FortbildungStatistikAdon.php
  - fortbildungsupload.php
  - dgptm-efn-labels.php
  - fortbildung-csv-import.php
  - doublettencheck.php
  - erweiterte-suche.php

#### **quiz-manager**
- **Dependencies**:
  - DATABASE: `aysquiz_quizes`, `aysquiz_quizcategories` (Quiz Maker plugin tables)
  - INTERNAL: crm-abruf (Zoho webhook for certificates)
- **Provides**:
  - Shortcodes: `[quiz_manager]`, quiz display
  - User meta: `quizz_verwalten`
- **WordPress Dependencies**: Quiz Maker plugin
- **Required by**: fortbildung

#### **herzzentren** (dgptm-herzzentren-unified/)
- **Dependencies**:
  - WP PLUGIN: Elementor
  - WP PLUGIN: Advanced Custom Fields
- **Provides**:
  - Elementor Widgets: Multi-Map, Single-Map
  - Custom post type: herzzentrum
  - Shortcodes for heart centers
- **Sub-modules**:
  - acf.php, admin.php, editor.php, frontend.php, ajax.php
  - ausbildungs-karte.php, permissions.php
  - hzl-* files (Herzzentren Liste)

#### **timeline-manager** (dgptm-timeline-manager/)
- **Dependencies**: None
- **Provides**:
  - Custom post type: `timeline_entry`
  - Frontend timeline manager
  - REST API integration
- **WordPress Dependencies**: None

#### **event-tracker**
- **Dependencies**:
  - INTERNAL: webhook-trigger
  - INTERNAL: crm-abruf (optional)
- **Provides**:
  - Custom post types: `et_event`, `et_mail`, `et_mail_tpl`
  - Event routing: /eventtracker
  - Email system with templates
  - Zoom meeting integration
- **WordPress Dependencies**: None

#### **abstimmen-addon** (Online-Abstimmung)
- **Dependencies**:
  - INTERNAL: crm-abruf (Zoho snapshot)
  - INTERNAL: webhook-trigger
  - EXTERNAL API: Zoom API (S2S OAuth)
- **Provides**:
  - Voting system with codes
  - Zoom meeting registration
  - Attendance tracking
  - User meta: `dgptm_vote_*`
- **WordPress Dependencies**: None

---

### Payment Modules

#### **stripe-formidable**
- **Dependencies**:
  - WP PLUGIN: Formidable Forms (Form ID 12)
  - EXTERNAL API: Stripe API
- **Provides**:
  - Stripe SEPA mandate integration
  - Card payment integration
  - Customer ID storage
- **WordPress Dependencies**: Formidable Forms

#### **gocardless**
- **Dependencies**:
  - WP PLUGIN: Formidable Forms (Form ID 12)
  - EXTERNAL API: GoCardless API
- **Provides**:
  - Direct debit mandate management
  - Account management
- **WordPress Dependencies**: Formidable Forms

---

### Authentication & Access Control

#### **otp-login** (otp-login-improved/)
- **Dependencies**: None
- **Provides**:
  - OTP login system
  - Rate limiting
  - 30-day login option
  - Multisite support
  - Shortcodes: Login/Logout
- **WordPress Dependencies**: None (can replace wp-login)

#### **side-restrict**
- **Dependencies**: None
- **Provides**: Page access restrictions
- **WordPress Dependencies**: None

#### **menu-control**
- **Dependencies**: None
- **Provides**: Menu visibility control
- **WordPress Dependencies**: None

---

### Media & Content Modules

#### **vimeo-streams** (vimeo-stream-manager-multi/)
- **Dependencies**: None
- **Provides**:
  - Shortcode: `[vimeo_streams]`
  - Multi-stream layout
  - Password protection
- **WordPress Dependencies**: None
- **External APIs**: Vimeo (embed only)

#### **wissens-bot**
- **Dependencies**:
  - EXTERNAL API: Claude AI (Anthropic)
  - EXTERNAL API: Microsoft Graph (SharePoint)
  - EXTERNAL API: PubMed
  - EXTERNAL API: Google Scholar
- **Provides**:
  - Shortcode: `[wissens_bot]`
  - AI-powered knowledge search
- **WordPress Dependencies**: None

#### **news-management**
- **Files**: newsedit.php, shortcodenewslist.php
- **Includes**: news_shortcodes.php, news_basis_shortcodes.php
- **Dependencies**: None
- **Provides**: News editing and listing
- **WordPress Dependencies**: None

#### **publication-workflow**
- **Dependencies**: None
- **Provides**: Publication management
- **WordPress Dependencies**: None

---

### Utility Modules

#### **microsoft-gruppen**
- **Dependencies**:
  - EXTERNAL API: Microsoft Graph API (365 Groups)
- **Provides**:
  - Microsoft 365 group management
  - User display with job titles
- **WordPress Dependencies**: None

#### **anwesenheitsscanner**
- **Dependencies**:
  - LIBRARY: FPDF
  - LIBRARY: Code128 barcode (class-code128.php)
- **Provides**:
  - Attendance scanning
  - PDF generation with barcodes
- **WordPress Dependencies**: None

#### **gehaltsstatistik**
- **Dependencies**: None
- **Provides**: Salary statistics
- **WordPress Dependencies**: None

#### **kiosk-jahrestagung**
- **Dependencies**: None
- **Provides**: Kiosk mode for annual conferences
- **WordPress Dependencies**: None

#### **exif-data**
- **Dependencies**: None
- **Provides**: Image EXIF data management
- **WordPress Dependencies**: None

#### **blaue-seiten**
- **Dependencies**: None
- **Provides**: Directory functionality ("Blue Pages")
- **WordPress Dependencies**: None

#### **shortcode-tools**
- **Files**: shortcodeedit.php, shortcode-grid.php, dgptm_grid_shortcode.php
- **Dependencies**: None
- **Provides**: Shortcode editors and grid layouts
- **WordPress Dependencies**: None

#### **stellenanzeige**
- **Dependencies**: None
- **Provides**: Job posting management
- **WordPress Dependencies**: Currently empty file

#### **conditional-logic** (if.php)
- **Dependencies**: None
- **Provides**: Conditional content display
- **WordPress Dependencies**: None

#### **installer**
- **Dependencies**: None
- **Provides**: Plugin installation helpers
- **WordPress Dependencies**: None

#### **zoho-role-manager**
- **Dependencies**:
  - INTERNAL: crm-abruf (Zoho integration)
- **Provides**: Role management based on Zoho data
- **WordPress Dependencies**: None

---

## Shared Libraries

### **FPDF**
- **Location**: dgptm/fpdf/
- **Required by**: fortbildung, anwesenheitsscanner
- **Type**: PDF generation library

### **Code128**
- **Location**: dgptm/includes/class-code128.php
- **Required by**: anwesenheitsscanner, fortbildung (barcodes)
- **Type**: Barcode generation class

---

## External WordPress Plugin Dependencies

1. **Advanced Custom Fields (ACF)** - Required by:
   - herzzentren
   - fortbildung
   - acf-tools (and modules using it)

2. **Elementor** - Required by:
   - herzzentren (Widgets)

3. **Formidable Forms** - Required by:
   - stripe-formidable
   - gocardless

4. **Quiz Maker** - Required by:
   - quiz-manager (uses DB tables)

---

## External API Dependencies

1. **Zoho CRM API** - Used by:
   - crm-abruf
   - quiz-manager
   - fortbildung
   - abstimmen-addon

2. **Stripe API** - Used by:
   - stripe-formidable

3. **GoCardless API** - Used by:
   - gocardless

4. **Zoom API** - Used by:
   - abstimmen-addon
   - event-tracker (optional)

5. **Microsoft Graph API** - Used by:
   - microsoft-gruppen
   - wissens-bot (SharePoint)

6. **Claude AI API** - Used by:
   - wissens-bot

7. **PubMed API** - Used by:
   - wissens-bot

8. **Google Scholar** - Used by:
   - wissens-bot

---

## Database Tables (Custom)

1. **aysquiz_quizes** - Quiz Maker plugin (used by quiz-manager)
2. **aysquiz_quizcategories** - Quiz Maker plugin (used by quiz-manager)
3. Various meta tables for custom post types

---

## Critical Dependency Chains

1. **Fortbildung Flow**:
   ```
   Quiz Maker Plugin → quiz-manager → fortbildung → FPDF → PDF Certificate
   ```

2. **Payment Flow**:
   ```
   Formidable Forms → stripe-formidable/gocardless → External API → Payment
   ```

3. **Event Flow**:
   ```
   event-tracker → webhook-trigger → External Webhook
                → crm-abruf → Zoho CRM
   ```

4. **Voting Flow**:
   ```
   abstimmen-addon → Zoom API → Registration
                  → crm-abruf → Zoho Snapshot
                  → webhook-trigger → Notifications
   ```

5. **Heart Centers Flow**:
   ```
   ACF → herzzentren → Elementor Widgets → Map Display
   ```

---

## Recommendations for Module Organization

### Core Layer (Always Active)
- crm-abruf
- rest-api-extension
- webhook-trigger
- menu-control
- side-restrict

### Business Layer (Selectively Active)
- fortbildung
- quiz-manager
- herzzentren
- event-tracker
- abstimmen-addon

### Payment Layer (Optional)
- stripe-formidable
- gocardless

### Content Layer (Optional)
- vimeo-streams
- wissens-bot
- news-management
- publication-workflow

### Utility Layer (Optional)
- All other modules

---

## Module Categories

```
CORE (5):          crm-abruf, rest-api-extension, webhook-trigger, menu-control, side-restrict
BUSINESS (9):      fortbildung, quiz-manager, herzzentren, timeline-manager, event-tracker,
                   abstimmen-addon, microsoft-gruppen, anwesenheitsscanner, gehaltsstatistik
PAYMENT (2):       stripe-formidable, gocardless
AUTH (1):          otp-login
MEDIA (2):         vimeo-streams, wissens-bot
CONTENT (2):       news-management, publication-workflow
ACF (3):           acf-anzeiger, acf-toggle, acf-jetsync
UTILITIES (8):     kiosk-jahrestagung, exif-data, blaue-seiten, shortcode-tools,
                   stellenanzeige, conditional-logic, installer, zoho-role-manager
```
