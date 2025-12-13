# DGPTM Mitgliedsantrag Module

Professional membership application form with Zoho CRM integration, guarantor verification, address validation, and student certificate upload.

## Version
1.0.0

## Features

### 🎯 Multi-Step Form Interface
- **5 intuitive steps** with visual progress indicator
- Smooth transitions and animations
- Responsive design for mobile and desktop
- Conditional step display (student certificate only if applicable)

### ✅ Real-Time Validation
- **Address validation**: ZIP code format, required fields
- **Email validation**: Format check and DNS MX record verification
- **Guarantor verification**: Live lookup in Zoho CRM
- **Student certificate validation**: File type, size, and required fields

### 👥 Guarantor Verification
- Search by name or email address
- Fuzzy name matching (handles variations like "Dr. Hans Meier" vs "Hans Maier")
- Real-time status indicators:
  - ✓ Green checkmark: Valid member found
  - ✗ Red X: Not found
  - ⚠ Yellow warning: Found but not a valid member
- Automatic membership type verification
- Only allows members with:
  - Ordentliches Mitglied
  - Außerordentliches Mitglied
  - Korrespondierendes Mitglied

### 📚 Student Workflow
- Automatic detection of student status
- Required fields:
  - Study direction
  - Certificate upload (JPG, PNG, PDF, max 5MB)
  - Certificate validity year (2025-2030)
- Conditional display of student-specific fields

### 🔗 Zoho CRM Integration
- OAuth 2.0 authentication
- Automatic contact creation or update
- **Configurable field mapping** - Customize which form fields map to which CRM fields
- **Configurable blueprint transition** - Set your own Blueprint ID in settings
- Stores guarantor information
- Attaches uploaded certificate URL
- **Phone number normalization** - Automatically converts to international format (+CountryCode)

### 🔒 Security & Privacy
- GDPR compliant with explicit consent
- Nonce verification on all AJAX requests
- File upload validation
- Sanitization of all input data
- Secure OAuth token handling

## Installation

1. The module is part of the DGPTM Plugin Suite
2. Navigate to **DGPTM Suite → Dashboard**
3. Find "DGPTM - Mitgliedsantrag" in the Content category
4. Click **Activate**

## Configuration

### OAuth Setup

1. Go to **WordPress Admin → Mitgliedsantrag**
2. Create a Zoho OAuth app at: https://api-console.zoho.eu
3. Select "Server-based Applications"
4. Add the redirect URI shown in the settings page
5. Enable scopes: `ZohoCRM.modules.ALL`, `ZohoCRM.settings.ALL`, and `ZohoCRM.Files.CREATE`
6. Copy Client ID and Client Secret to the settings page
7. Click "Save Settings"
8. Click "Connect with Zoho CRM" button
9. Authorize the application

**Important:** The `ZohoCRM.Files.CREATE` scope is required for uploading student certificates and qualification documents to CRM file fields.

### Alternative: Use Existing OAuth from crm-abruf Module

If you have the `crm-abruf` module active and configured, this module will automatically use its OAuth tokens. No separate configuration needed!

### Advanced Configuration

#### Phone Number Normalization

Configure the default country code for phone number normalization:

1. Go to **WordPress Admin → Mitgliedsantrag → Settings**
2. Set "Standard Ländervorwahl" (default: `+49` for Germany)
3. All phone numbers without country code will be normalized with this prefix

**Examples:**
- Input: `0151 12345678` → Output: `+4915112345678`
- Input: `+43 664 1234567` → Output: `+436641234567` (already international, unchanged)

#### Blueprint Transition ID

Customize the Blueprint workflow triggered after contact creation:

1. Go to **WordPress Admin → Mitgliedsantrag → Settings**
2. Set "Blueprint Transition ID" (default: `548256000025337019`)
3. Leave empty to disable blueprint triggering

#### Custom Field Mapping

Customize how form fields map to Zoho CRM fields:

1. Go to **WordPress Admin → Mitgliedsantrag → Settings**
2. Scroll to "Feldmapping (Erweitert)"
3. Edit the JSON mapping (format: `{"CRM_Field": "form_field"}`)
4. Click "Standard wiederherstellen" to reset to defaults

**Available form fields:**
- `vorname`, `nachname`, `akad_titel`, `ansprache`, `geburtsdatum`
- `strasse`, `stadt`, `bundesland`, `plz`, `land`
- `email1`, `email2`, `email3`, `telefon1`, `telefon2`
- `arbeitgeber`, `mitgliedsart`
- `buerge1_name`, `buerge1_email`, `buerge1_id`
- `buerge2_name`, `buerge2_email`, `buerge2_id`
- `studienrichtung`, `studienbescheinigung_gueltig_bis`

## Usage

### Display the Form

Add the shortcode to any page or post:

```
[dgptm-mitgliedsantrag]
```

**Alternative shortcode** (may be overridden by Formidable Forms):
```
[mitgliedsantrag]
```

**Recommendation:** Use `[dgptm-mitgliedsantrag]` to avoid conflicts with other plugins.

### Form Steps

1. **Basisdaten** (Basic Data)
   - Greeting/Salutation (required)
   - Date of birth (required)
   - Academic title
   - First name, last name
   - Additional titles
   - Membership type

2. **Adresse** (Address)
   - Private address (required - no business address!)
   - Street, city, ZIP, state, country
   - Up to 3 email addresses (validated)
   - Phone numbers
   - Employer/University
   - Student toggle

3. **Studienbescheinigung** (Student Certificate - conditional)
   - Study direction
   - Certificate upload
   - Certificate validity year
   - Only shown if "Student" is checked

4. **Bürgen** (Guarantors)
   - Two guarantors required
   - Enter name OR email address
   - Real-time verification
   - Must be valid members

5. **Bestätigung** (Confirmation)
   - GDPR information
   - Explicit consent checkbox
   - Final submission

## Zoho CRM Field Mapping

| Form Field | CRM Field | Type |
|------------|-----------|------|
| Vorname | First_Name | Text |
| Nachname | Last_Name | Text |
| Akad. Titel | Academic_Title | Text |
| Ansprache | greeting | Picklist (required) |
| Geburtsdatum | Date_of_Birth | Date (required) |
| Straße | Other_Street | Text |
| Stadt | Other_City | Text |
| PLZ | Other_Zip | Text |
| Bundesland | Other_State | Text |
| Land | Other_Country | Text |
| E-Mail 1 | Email | Email (unique) |
| E-Mail 2 | Secondary_Email | Email |
| E-Mail 3 | Third_Email | Email |
| Telefon 1 | Phone | Phone |
| Telefon 2 | Work_Phone | Phone |
| Arbeitgeber | employer_name | Text |
| Studienrichtung | profession | Text |
| Zertifikat gültig bis | Freigestellt_bis | Date |
| Mitgliedsart | Membership_Type | Picklist |
| Bürge 1 Name | Guarantor_Name_1 | Text |
| Bürge 1 E-Mail | Guarantor_Mail_1 | Email |
| Bürge 1 Status | Guarantor_Status_1 | Boolean |
| Bürge 2 Name | Guarantor_Name_2 | Text |
| Bürge 2 E-Mail | Guarantor_Mail_2 | Email |
| Bürge 2 Status | Guarantor_Status_2 | Boolean |

## Technical Details

### Dependencies
- **crm-abruf module**: For Zoho CRM OAuth (recommended but optional)
- **WordPress 5.8+**
- **PHP 7.4+**
- **jQuery** (included with WordPress)

### File Structure
```
mitgliedsantrag/
├── dgptm-mitgliedsantrag.php    # Main plugin file
├── module.json                   # Module configuration
├── README.md                     # This file
├── templates/
│   ├── form.php                  # Frontend form template
│   └── admin-settings.php        # Admin settings page
└── assets/
    ├── css/
    │   ├── style.css             # Frontend styles
    │   └── admin-style.css       # Admin styles
    └── js/
        └── script.js             # Frontend JavaScript
```

### AJAX Endpoints

- `dgptm_verify_guarantor` - Verify guarantor by name or email
- `dgptm_validate_address` - Validate address format
- `dgptm_validate_email` - Validate email format and domain
- `dgptm_submit_application` - Submit complete application

### Guarantor Search Algorithm

1. **Email Search**: If input is valid email format
   - Search primary Email field
   - Search Secondary_Email field
   - Search Third_Email field

2. **Name Search**: If input is text
   - Try exact match: First_Name + Last_Name
   - Try fuzzy match: Contains Last_Name
   - Calculate similarity score (70% threshold)
   - Return best match

3. **Membership Verification**
   - Check Membership_Type field
   - Must be one of: Ordentliches, Außerordentliches, Korrespondierendes Mitglied

### Blueprint Transition

After successful contact creation/update, the module triggers a blueprint transition with:
- **Transition ID**: `548256000025337019`
- **Module**: Contacts
- **API Endpoint**: `/crm/v2/Contacts/{id}/actions/blueprint`

This initiates the membership approval workflow in Zoho CRM.

## Error Handling

### Common Issues

**OAuth not configured**
- Error: "OAuth-Verbindung nicht konfiguriert"
- Solution: Complete OAuth setup in admin settings OR activate crm-abruf module

**Guarantor not found**
- Error: Red X next to guarantor input
- Possible causes:
  - Typo in name or email
  - Person not in CRM
  - Person is not a member
- Solution: Verify spelling, check CRM, or use different guarantor

**File upload failed**
- Error: "Fehler beim Hochladen der Studienbescheinigung"
- Possible causes:
  - File too large (>5MB)
  - Invalid file type (not JPG/PNG/PDF)
  - PHP upload limits
- Solution: Reduce file size, convert to PDF, or check server limits

**Blueprint transition failed**
- Note: Application is still created, only workflow trigger fails
- Check Zoho CRM logs
- Verify blueprint ID in settings (default: `548256000025337019`)
- Ensure user has blueprint permissions

**CRM transmission not working**
- Error: Contact not created/updated in Zoho CRM
- **Debugging steps:**
  1. Enable WordPress debug logging:
     ```php
     define('WP_DEBUG', true);
     define('WP_DEBUG_LOG', true);
     ```
  2. Check `wp-content/debug.log` for entries starting with `[DGPTM Mitgliedsantrag]`
  3. Look for specific error messages:
     - `ERROR: Contact creation/update failed:` - Network or API error
     - `Zoho CRM HTTP Code:` - Check HTTP response code (200 = OK, 401 = Unauthorized, etc.)
     - `Zoho CRM response:` - Full API response with error details
  4. Common causes:
     - OAuth token expired (check OAuth Status in settings)
     - Invalid field mapping (check JSON syntax in Feldmapping)
     - Missing required CRM fields (greeting, Date_of_Birth must be set)
     - CRM API rate limits exceeded
  5. Test OAuth connection:
     - Go to Mitgliedsantrag settings
     - Check if "OAuth Status" shows "Verbunden" (Connected)
     - Re-authorize if needed

**Phone numbers not normalized**
- Check "Standard Ländervorwahl" in settings (default: `+49`)
- Verify debug log shows: `Normalized phones - Phone1: +49..., Phone2: +49...`
- Phone normalization happens before CRM submission

## Development

### Extending the Module

**Add custom validation:**
```php
add_filter('dgptm_mitgliedsantrag_validate_step', function($isValid, $step, $data) {
    if ($step === 1) {
        // Custom validation logic
    }
    return $isValid;
}, 10, 3);
```

**Modify CRM data before submission:**
```php
add_filter('dgptm_mitgliedsantrag_crm_data', function($contact_data, $form_data) {
    // Modify $contact_data
    return $contact_data;
}, 10, 2);
```

**Hook into successful submission:**
```php
add_action('dgptm_mitgliedsantrag_submitted', function($contact_id, $form_data) {
    // Custom logic after successful submission
}, 10, 2);
```

### Debugging

Enable WordPress debug mode to see detailed logs:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Look for entries in `debug.log` with prefix: `[DGPTM Mitgliedsantrag]`

## Changelog

### Version 1.0.0 (2025-12-05)
- Initial release
- Multi-step form with 5 steps
- Zoho CRM OAuth integration
- Real-time guarantor verification with fuzzy name matching
- Address and email validation
- Student certificate upload
- Automatic contact creation/update in Zoho CRM
- Blueprint transition trigger
- GDPR compliance
- Responsive design
- Comprehensive error handling

## Support

For issues or questions:
1. Check the WordPress debug log
2. Verify OAuth configuration
3. Test with crm-abruf module active
4. Check Zoho CRM API limits and permissions

## Credits

**Author**: Sebastian Melzer
**Organization**: DGPTM (Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V.)
**License**: Proprietary - Part of DGPTM Plugin Suite

## Notes

- Always test in a staging environment first
- Ensure adequate Zoho API rate limits for production use
- Regular backups recommended before module updates
- Certificate files are stored in WordPress media library
- OAuth tokens are refreshed automatically when expired
