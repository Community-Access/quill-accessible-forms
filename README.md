# QUILL Accessible Forms (QAF)

A fully accessible WordPress form builder plugin with WCAG 2.2 AA compliance, custom form language (the QAF form language), and advanced entry views.

![WordPress Version](https://img.shields.io/badge/WordPress-6.0%2B-blue)
![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-purple)
![License](https://img.shields.io/badge/License-MIT-green)

## Features

### Accessibility First

- **WCAG 2.2 AA Compliant** - All forms meet accessibility standards out of the box
- **Screen Reader Support** - Proper ARIA labels, live regions, and announcements
- **Keyboard Navigation** - Full keyboard support for all interactions
- **Focus Management** - Intelligent focus trapping and restoration
- **High Contrast Mode** - Automatic support for high contrast preferences
- **Reduced Motion** - Respects `prefers-reduced-motion` settings

### Visual Form Builder

- **Drag & Drop Interface** - Build forms visually with 30+ field types
- **Structure Mode** - Code-first approach using the QAF form language
- **Real-time Preview** - See changes as you make them
- **Conditional Logic** - Show/hide fields based on user input
- **Multi-page Forms** - Create wizard-style multi-step forms
- **Input Masking** - Guide user input formats (e.g. phone, SSN)
- **Smart Defaults** - Auto-fill fields (`geo`, `today+N`, `user:*`)
- **Unique Validation** - Enforce uniqueness with per-form or global scope
- **Address Autocomplete** - Optional Google Places integration

### Field Types

| Basic | Advanced | Payment |
| ----- | -------- | ------- |
| Text | File Upload | Stripe |
| Email | Signature | PayPal |
| Number | Rating | Calculation |
| Textarea | Likert Scale | |
| Select | NPS Score | |
| Radio | Ranking | |
| Checkbox | Repeater | |
| Date/Time | Address | |
| Phone | Name | |
| URL | Hidden | |
| Password | HTML | |
| Color | Section | |
| Range | Page Break | |

### The QAF form language

Define forms as code with our custom domain-specific language.

```bfl
form "Contact Us"
@submit_label "Send Message"
@success_message "Thank you for contacting us!"

text "Your Name"
@id "name"
@required
@placeholder "John Doe"

email "Email Address"
@id "email"
@required

textarea "Your Message"
@id "message"
@required
@min 20
@max 1000
@rows 6

submit "Send Message"
```

**Phase 5 Enhancements (implemented):**

```bfl
text "Phone"
@id "phone"
@mask "(999) 999-9999"

address "Shipping Address"
@id "ship_address"
@autocomplete
@autocomplete_provider "google_places"
@autocomplete_countries ["US","CA"]
@autocomplete_types ["address"]

text "Name"
@id "display_name"
@smart_default "user:display_name"

email "Email"
@id "email_unique"
@unique
@unique_scope "form"   # form|global
@unique_message "This email is already registered"
```

Address autocomplete requires a Google Maps API key configured under **QAF Forms → Settings → Integrations**.


### Choice Libraries

Use predefined choice lists instead of typing options manually. QAF includes **125+ built-in libraries** with full cascading support:

```bfl
select country {
    label "Country"
    library countries
}

select state {
    label "State/Province"
    cascade_from country
    cascade_library states  // Auto-populates states based on country
    empty_message "Please select a country first"
}
```

**Geographic Libraries (35+):**

| Library | Description |
| ------- | ----------- |
| `countries` | All 250+ countries (ISO 3166-1) |
| `continents` | 7 continents |
| `world_regions` | Geographic regions (Americas, Europe, Asia, etc.) |
| `us_states` | US states + DC + territories |
| `ca_provinces` | Canadian provinces and territories |
| `uk_counties` | UK ceremonial counties (~45 regions) |
| `au_states` | Australian states and territories |
| `de_states` | German Bundesländer (16) |
| `fr_regions` | French regions (13) |
| `it_regions` | Italian regions (20) |
| `es_regions` | Spanish autonomous communities (19) |
| `nl_provinces` | Dutch provinces (12) |
| `be_provinces` | Belgian provinces (11) |
| `ch_cantons` | Swiss cantons (26) |
| `at_states` | Austrian states (9) |
| `mx_states` | Mexican states (32) |
| `br_states` | Brazilian states (27) |
| `ar_provinces` | Argentine provinces (24) |
| `in_states` | Indian states and territories (36) |
| `cn_provinces` | Chinese provinces (34) |
| `jp_prefectures` | Japanese prefectures (47) |
| `kr_provinces` | South Korean provinces (17) |
| `nz_regions` | New Zealand regions (16) |
| `za_provinces` | South African provinces (9) |
| `ie_counties` | Irish counties (26) |
| `pl_voivodeships` | Polish voivodeships (16) |
| `ru_regions` | Russian major regions (20) |
| `cities_us` | Major US cities (40) |
| `cities_world` | Major world cities (48) |

**Time & Date Libraries:**

| Library | Description |
| ------- | ----------- |
| `timezones` | All IANA timezones |
| `timezones_us` | US timezones only |
| `timezones_*` | By region (europe, asia, america, etc.) |
| `days` | Days of the week |
| `months` | Months of the year |
| `years_past` | Past 100 years |
| `years_future` | Next 10 years |
| `years_birth` | Birth years (1920-present) |
| `time_slots_hourly` | Hourly time slots |
| `time_slots_30min` | 30-minute intervals |
| `time_slots_15min` | 15-minute intervals |

**Financial Libraries:**

| Library | Description |
| ------- | ----------- |
| `currencies` | 58 world currencies (ISO 4217) |
| `currencies_major` | 8 major currencies (USD, EUR, GBP, etc.) |
| `cryptocurrencies` | 12 popular cryptocurrencies |
| `budget_ranges` | Budget brackets ($1K - $1M+) |
| `annual_revenue` | Company revenue ranges |
| `payment_methods` | Credit card, PayPal, etc. |

**Language & Contact Libraries:**

| Library | Description |
| ------- | ----------- |
| `languages` | 50 world languages (ISO 639-1) |
| `languages_major` | 12 major languages |
| `phone_codes` | 51 country dialing codes |
| `contact_preferences` | Email, phone, SMS, etc. |
| `contact_times` | Morning, afternoon, evening |

**Demographic Libraries:**

| Library | Description |
| ------- | ----------- |
| `age_ranges` | Age brackets |
| `gender` | Gender options (inclusive) |
| `marital_status` | Marital status options |
| `education_levels` | Education attainment |
| `employment_status` | Employment types |
| `income_ranges` | Income brackets |
| `household_sizes` | 1-7+ people |
| `religions` | World religions |
| `ethnicities` | Ethnicity options |
| `relationship_statuses` | Relationship status |

**Professional Libraries:**

| Library | Description |
| ------- | ----------- |
| `industries` | 60+ industry sectors |
| `job_functions` | Job departments/functions |
| `experience_levels` | Entry to C-Level |
| `years_experience` | Experience ranges |
| `company_sizes` | Company employee counts |
| `fields_of_study` | Academic majors |
| `lead_sources` | How did you hear about us |
| `project_types` | Project categories |
| `timelines` | Timeline options |

**E-commerce Libraries:**

| Library | Description |
| ------- | ----------- |
| `tshirt_sizes` | XS to 5XL |
| `shoe_sizes_us` | US shoe sizes |
| `shoe_sizes_eu` | EU shoe sizes |
| `product_conditions` | New, used, refurbished |
| `colors` | Common color options |
| `quantities` | 1-100 quantity selector |
| `shipping_methods` | Standard, express, overnight |
| `subscription_frequencies` | Weekly to annually |

**Rating & Feedback Libraries:**

| Library | Description |
| ------- | ----------- |
| `rating_5_star` | 1-5 star rating |
| `satisfaction` | Very unsatisfied to very satisfied |
| `agreement` | Strongly disagree to strongly agree |
| `likelihood` | Very unlikely to very likely |
| `frequency` | Never to always |
| `quality` | Poor to excellent |
| `priority_levels` | Low to critical |

**Health & Medical Libraries:**

| Library | Description |
| ------- | ----------- |
| `blood_types` | A+, A-, B+, etc. |
| `medical_specialties` | 30+ medical specialties |
| `dietary_restrictions` | Vegetarian, vegan, gluten-free, etc. |
| `allergens` | Common food allergens |
| `accessibility_needs` | Wheelchair, hearing, visual, etc. |
| `appointment_types` | Consultation, follow-up, etc. |

**Technology Libraries:**

| Library | Description |
| ------- | ----------- |
| `platforms` | Windows, macOS, Linux, iOS, Android |
| `device_types` | Desktop, laptop, tablet, smartphone |
| `browsers` | Chrome, Firefox, Safari, Edge |
| `skill_levels` | Beginner to expert |

**Boolean & Simple Libraries:**

| Library | Description |
| ------- | ----------- |
| `yes_no` | Yes/No |
| `yes_no_unsure` | Yes/No/Not sure |
| `true_false` | True/False |
| `agree_disagree` | Agree/Disagree |

**Cascading Support:**

Libraries automatically cascade when related:
- `continent` → `countries` (filter countries by continent)
- `country` → `states` (auto-detect correct state/province list)
- `timezone_region` → `timezones` (filter by region)

**Register Custom Libraries:**

```php
add_action('qaf_register_choice_libraries', function() {
    QAF_Choice_Libraries::register('departments', array(
        'label' => 'Departments',
        'choices' => array(
            array('value' => 'sales', 'label' => 'Sales'),
            array('value' => 'support', 'label' => 'Support'),
            array('value' => 'engineering', 'label' => 'Engineering'),
        ),
    ));
});
```

### Entry Views

Display form submissions in multiple layouts:

- **Table View** - Sortable, filterable data table with bulk actions
- **Cards View** - Visual card layout with images and badges
- **List View** - Compact list format for quick scanning
- **Map View** - Geographic visualization for location data
- **Calendar View** - Date-based entry display

### Integrations

- **Stripe** - Accept payments with 3D Secure/SCA support
- **PayPal** - PayPal checkout integration
- **Google Sheets** - Auto-sync entries to spreadsheets
- **Webhooks** - Custom API integrations
- **Email** - Notification and autoresponder emails
- **PDF Generation** - Generate PDFs from entries

### Security

- **CSRF Protection** - All forms protected against cross-site request forgery
- **Honeypot Fields** - Invisible bot detection
- **Rate Limiting** - Prevent form spam and abuse
- **CAPTCHA Support** - reCAPTCHA v2/v3 and hCaptcha
- **Input Sanitization** - Comprehensive XSS and injection protection
- **File Validation** - Secure file upload handling

## Installation

### From WordPress Admin

1. Go to **Plugins → Add New**
2. Search for "QUILL Accessible Forms"
3. Click **Install Now** and then **Activate**

### Manual Installation

1. Download the plugin ZIP file
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin

### Via Composer

```bash
composer require bits/accessible-forms
```

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+

## Quick Start

### Create Your First Form

1. Go to **QAF Forms → Add New**
2. Enter a form title
3. Drag fields from the sidebar to build your form
4. Configure field settings in the right panel
5. Click **Save Form**

### Display a Form

Use the shortcode:

```text
[qaf_form id="1"]
```

Or the Gutenberg block:

1. Add a new block
2. Search for "QAF Form"
3. Select your form from the dropdown

### View Entries

1. Go to **QAF Forms → Entries**
2. Select a form to view its submissions
3. Use filters and search to find specific entries
4. Export data as CSV, Excel, or PDF

## Configuration

### Global Settings

Navigate to **QAF Forms → Settings** to configure:

- Default form styles
- Email notifications
- Payment gateway credentials
- reCAPTCHA keys
- Integration API keys

### Form-Specific Settings

Each form has its own settings panel:

- **General** - Title, description, status
- **Submissions** - Entry handling, limits, scheduling
- **Notifications** - Admin and user emails
- **Confirmations** - Success messages and redirects
- **Payments** - Payment field configuration
- **Integrations** - Third-party connections

## API Reference

### REST API Endpoints

| Endpoint | Method | Description |
| -------- | ------ | ----------- |
| `/qaf/v1/forms` | GET | List all forms |
| `/qaf/v1/forms/{id}` | GET | Get single form |
| `/qaf/v1/forms` | POST | Create form |
| `/qaf/v1/forms/{id}` | PUT | Update form |
| `/qaf/v1/forms/{id}` | DELETE | Delete form |
| `/qaf/v1/entries` | GET | List entries |
| `/qaf/v1/entries/{id}` | GET | Get single entry |
| `/qaf/v1/entries` | POST | Create entry |
| `/qaf/v1/bfl/parse` | POST | Parse QAF form language code |
| `/qaf/v1/bfl/validate` | POST | Validate QAF form language code |

### PHP Hooks

#### Actions

```php
// Before form renders
do_action( 'qaf_before_form_render', $form_id, $form );

// After successful submission
do_action( 'qaf_after_submission', $entry_id, $form_id, $data );

// Before entry is saved
do_action( 'qaf_before_entry_save', $data, $form_id );

// After payment processed
do_action( 'qaf_payment_completed', $entry_id, $payment_data );
```

#### Filters

```php
// Modify form HTML
add_filter( 'qaf_form_html', function( $html, $form_id ) {
    return $html;
}, 10, 2 );

// Validate submission data
add_filter( 'qaf_validate_submission', function( $errors, $data, $form_id ) {
    return $errors;
}, 10, 3 );

// Modify field options dynamically
add_filter( 'qaf_field_options', function( $options, $field, $form_id ) {
    return $options;
}, 10, 3 );

// Customize email content
add_filter( 'qaf_notification_email', function( $email, $entry, $form ) {
    return $email;
}, 10, 3 );
```

### JavaScript API

```javascript
// Get form instance
const form = QAF.getForm('form-id');

// Submit programmatically
form.submit();

// Validate all fields
const isValid = form.validate();

// Get/set field values
form.getField('email').getValue();
form.getField('email').setValue('test@example.com');

// Listen to events
form.on('submit', (data) => console.log(data));
form.on('validation:error', (errors) => console.log(errors));
form.on('field:change', (field, value) => console.log(field, value));
```

## Testing

### Run PHPUnit Tests

```bash
./vendor/bin/phpunit
```

### Run JavaScript Tests

```bash
npm test
```

### Code Coverage

```bash
./vendor/bin/phpunit --coverage-html coverage/
```

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Setup

1. Clone the repository
2. Run `composer install`
3. Run `npm install`
4. Set up local WordPress environment
5. Symlink or copy plugin to `wp-content/plugins/`

### Coding Standards

- Follow WordPress Coding Standards
- Use PHP 8.0+ features appropriately
- Ensure WCAG 2.2 AA compliance for all UI changes
- Write tests for new functionality

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

This plugin is licensed under the MIT License; see [LICENSE](LICENSE).
MIT is compatible with the GPL, so the plugin can be distributed through
WordPress.org and used alongside GPL themes and plugins.

## Support

- **Documentation**: [docs.bitsforms.com](https://docs.bitsforms.com)
- **GitHub Issues**: [Report bugs](https://github.com/bits/accessible-forms/issues)
- **Community Forum**: [WordPress.org Support](https://wordpress.org/support/plugin/quill-accessible-forms)

## Credits

Built by Jeff Bishop.

Special thanks to:

- WordPress community
- WAI-ARIA working group
- All our contributors and testers
