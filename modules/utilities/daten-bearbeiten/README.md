# DGPTM Daten bearbeiten

Member data editing form with Zoho CRM synchronization, designed to replace the Formidable Forms implementation.

## Version
1.0.0

## Description

This module allows logged-in members to view and edit their personal data stored in Zoho CRM. The form automatically loads data from the CRM, allows editing, and synchronizes changes back to Zoho CRM upon submission.

## Features

- **Automatic Data Loading**: Fetches member data from Zoho CRM on page load
- **OAuth Integration**: Uses OAuth tokens from `mitgliedsantrag` or `crm-abruf` modules
- **Read-only Fields**: Name and birth date are displayed but cannot be edited
- **Email Sync**: Updates WordPress user email when primary email is changed
- **Employer Management**: Search and select employer from Zoho Accounts or manual entry
- **Payment Integration**: GoCardless direct debit management via `[gcl_formidable]` shortcode
- **Form Validation**: Client-side and server-side validation
- **Auto-redirect**: Redirects to internal area after successful submission
- **Responsive Design**: Mobile-friendly layout
- **Toggle Switches**: Modern UI for journal preferences

## Installation

1. The module is part of the DGPTM Plugin Suite
2. Navigate to **DGPTM Suite → Dashboard**
3. Find "DGPTM - Daten bearbeiten" in the Utilities category
4. Click **Activate**

## Requirements

### Required
- One of the following modules for OAuth authentication:
  - `mitgliedsantrag` module (configured with OAuth)
  - `crm-abruf` module (configured with OAuth)

### Optional
- `gocardless` module - For payment management integration
  - Provides `[gcl_formidable]` shortcode for displaying direct debit information
  - Shows current bank account and mandate details
  - If not active, payment section shows placeholder message

### User Requirements
- User must be logged in
- User meta field `zoho_id` must contain valid Zoho Contact ID
- For payment features: User meta field `GoCardlessID` (provided by `gocardless` module)

## Usage

### Display the Form

Add the shortcode to any page:

```
[dgptm-daten-bearbeiten]
```

### Typical Page Setup

1. Create a new WordPress page (e.g., "Meine Daten")
2. Add the shortcode: `[dgptm-daten-bearbeiten]`
3. Restrict page access to logged-in members only
4. Link from member dashboard

## Form Fields

### Read-only Fields
- **Name**: First name + Last name from CRM
- **Geburtsdatum**: Date of birth from CRM

**Note:** For name changes, members must contact the office (message displayed in form).

### Editable Fields

#### Personal Data
- Ansprache (Greeting/Salutation)
- Akad. Titel (Academic Title)
- Titel nach dem Namen (Title after name, e.g., ECCP, AfK)

#### Email Addresses
- **Private Mailadresse** (Primary Email) - Required, used for login
- Zweite (dienstliche) Mailadresse (Secondary Email)
- Optionale Mailadresse (Third Email)

**Important:** The primary email is synchronized to WordPress user account.

#### Address
- Straße (Street) - Required
- Adresszusatz (Address Supplement)
- Postleitzahl (ZIP Code) - Required
- Ort (City) - Required
- Land (Country) - Dropdown with common countries

#### Phone Numbers
- Telefon (Phone)
- Mobil (Mobile)
- Diensttelefon (Work Phone)

#### Employer
- **Arbeitgeber suchen** - Search field for finding employer in Zoho Accounts
- **Arbeitgeber ist nicht in der Liste** - Checkbox for manual entry
- **Ausgeliehen an Klinik** - Shown only for service providers (WKK, LifeSystems)

#### Payment Information (if GoCardless module active)
- **Current Bank Account** - Shows IBAN ending, account holder, bank name
- **Mandate Reference** - GoCardless mandate details
- **Creditor ID** - Creditor identification

#### Journal Preferences
- DIE PERFUSIOLOGIE per Post (Toggle)
- DIE PERFUSIOLOGIE per Mail (Toggle)

## Technical Details

### Zoho CRM Field Mapping

| Form Field | CRM Field | Type | Notes |
|------------|-----------|------|-------|
| Vorname | First_Name | Text | Read-only |
| Nachname | Last_Name | Text | Read-only |
| Geburtsdatum | Date_of_Birth | Date | Read-only |
| Ansprache | greeting | Picklist | Moin, Servus, Liebe, etc. |
| Akad. Titel | Academic_Title | Text | Dr., Prof., etc. |
| Titel nach Name | Title_After_The_Name | Text | ECCP, AfK, etc. |
| Mail1 | Email | Email | Primary email, synced to WP |
| Mail2 | Secondary_Email | Email | |
| Mail3 | Third_Email | Email | |
| Straße | Mailing_Street | Text | |
| Adresszusatz | Mailing_Street_Additional | Text | |
| PLZ | Mailing_Zip | Text | |
| Ort | Mailing_City | Text | |
| Land | Mailing_Country | Text | |
| Telefon | Phone | Phone | |
| Mobil | Mobile | Phone | |
| Diensttelefon | Work_Phone | Phone | |
| Journal Post | journal_post | Boolean | |
| Journal Mail | journal_mail | Boolean | |
| Status | Contact_Status | Text | Read-only, e.g., "Aktiv" |

### File Structure
```
daten-bearbeiten/
├── dgptm-daten-bearbeiten.php    # Main plugin file
├── module.json                    # Module configuration
├── README.md                      # This file
├── templates/
│   └── edit-form.php              # Form template
└── assets/
    ├── css/
    │   └── style.css              # Form styles
    └── js/
        └── script.js              # AJAX logic
```

### AJAX Endpoints

- `dgptm_load_member_data` - Load member data from Zoho CRM
- `dgptm_update_member_data` - Update member data in Zoho CRM

### OAuth Token Resolution

The module attempts to get OAuth token in this order:
1. From `mitgliedsantrag` module via `get_access_token()` method (automatically refreshes expired tokens)
2. From `crm-abruf` module via `get_access_token()` method

**Note:** The `mitgliedsantrag` module's `get_access_token()` method automatically refreshes the token if it's expired, so there's no manual intervention needed.

### WordPress User Email Sync

When the primary email (`mail1`) is changed:
1. WordPress user email is updated via `wp_update_user()`
2. User meta `billing_email` is updated
3. Change is synchronized to Zoho CRM

### Redirect After Submission

After successful form submission, the user is redirected to:
```
https://perfusiologie.de/mitgliedschaft/interner-bereich/
```

To change this, modify the redirect URL in `dgptm-daten-bearbeiten.php`:
```php
'redirectUrl' => 'https://perfusiologie.de/mitgliedschaft/interner-bereich/',
```

## User Experience Flow

1. **Page Load**
   - Loading spinner appears
   - AJAX request fetches data from Zoho CRM
   - Form fields are populated
   - Form fades in

2. **Editing**
   - User can edit all non-read-only fields
   - Real-time validation on required fields

3. **Submission**
   - Submit button disabled with loading text
   - AJAX request updates Zoho CRM
   - Success message displays
   - Auto-redirect after 2 seconds

## Error Handling

### Common Issues

**"Keine Zoho ID gefunden"**
- **Cause:** User meta field `zoho_id` is empty
- **Solution:** Ensure user account has been synchronized with Zoho CRM

**"OAuth-Token nicht verfügbar"**
- **Cause:** Neither `mitgliedsantrag` nor `crm-abruf` module has valid OAuth token
- **Solution:** Configure OAuth in one of the required modules

**"Kontakt nicht gefunden"**
- **Cause:** Zoho Contact ID doesn't exist in CRM or access denied
- **Solution:** Verify `zoho_id` is correct and OAuth has proper permissions

**"Fehler beim Aktualisieren der Daten"**
- **Cause:** CRM update request failed
- **Solution:** Check WordPress debug log for detailed error message

### Debugging

Enable WordPress debug logging:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Look for entries in `debug.log` with prefix: `[DGPTM Daten bearbeiten]`

## Comparison with Formidable Forms

This module replaces the Formidable Forms implementation with these advantages:

| Feature | Formidable Forms | This Module |
|---------|------------------|-------------|
| Dependencies | Requires Formidable Pro | No external plugins |
| Performance | Heavy plugin overhead | Lightweight, purpose-built |
| Customization | Limited by Formidable | Full code control |
| OAuth Integration | Custom addon required | Built-in, reuses existing |
| Maintenance | Formidable updates can break | Stable, controlled updates |

## Security

- Nonce verification on all AJAX requests
- `is_user_logged_in()` check on all operations
- Input sanitization (`sanitize_text_field`, `sanitize_email`)
- OAuth token validation
- Read-only enforcement on sensitive fields

## Changelog

### Version 1.0.0 (2025-12-06)
- Initial release
- OAuth integration with mitgliedsantrag/crm-abruf modules
- Complete form field mapping from Formidable Forms
- Read-only name and birth date fields
- WordPress email synchronization
- Responsive design with toggle switches
- Auto-redirect after successful submission
- AJAX-based data loading and saving

## Support

For issues or questions:
1. Check the WordPress debug log
2. Verify `zoho_id` user meta field exists
3. Ensure OAuth is configured in mitgliedsantrag or crm-abruf module
4. Test with browser console open for JavaScript errors

## Credits

**Author**: Sebastian Melzer
**Organization**: DGPTM (Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V.)
**License**: Proprietary - Part of DGPTM Plugin Suite

## Notes

- Module designed as drop-in replacement for Formidable Forms implementation
- Maintains same redirect URL: `https://perfusiologie.de/mitgliedschaft/interner-bereich/`
- Compatible with existing Zoho CRM field structure
- Uses modern ES5 JavaScript for maximum browser compatibility
- CSS follows Formidable Forms class naming convention for easy migration
