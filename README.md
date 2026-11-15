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
- **Preview** - The builder's Preview link opens the form as a visitor sees it, drawn by the same renderer and running the same page script, in a new tab. Submit checks the answers and says so; nothing is sent or saved (the preview form posts nowhere and carries no form token). Drafts preview too.
- **Sections and columns on the canvas** - Every section and set of columns has its own card with Move up, Move down, Copy (with copies of its questions, under new IDs) and Remove (its questions stay where it was, and keep any condition the section had). A set of columns also has Add column, and each column has Move left, Move right and Remove column (its questions join the column beside it). Add Section and Add Two Columns sit beside Add Field. Every action says what it did, and focus stays with the thing acted on.
- **Accessibility Coach** - Check Accessibility, under the canvas, checks the saved form: labels, help on hard questions, choices, section headings, page titles, and the model's own problems. Each finding names the question it concerns and has a Go to button that moves focus to that question's card.
- **Convert rewrites conditions** - When a question changes type and its answers change kind, the conditions, calculations and rules that compared it with its old answers are rewritten where that is certain (a choice's value becomes its label when the question becomes typed; a typed answer becomes the value of the choice it names) and flagged, with the reason, where it is not. Convert says both in its preview, before anything changes.
- **Import from other builders** - Gravity Forms and WPForms JSON exports, Contact Form 7 form markup, Ninja Forms and Google Forms, mapped to QAF types, with a report of everything that could not be carried. Forms only: entries in an export are left behind, and the report says how many.
- **Conditional Logic** - Show/hide fields based on user input
- **Multi-page Forms** - Create wizard-style multi-step forms
- **Repeating groups** - Each entry of a repeater is a labelled group ("Children Row 2"); Add, Remove, Copy and the move buttons say what they did in the repeater's status region and put focus on the new entry, or after a removal on the entry that took its place. A limit is said, not hidden: the button stays reachable with aria-disabled.
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

### Payments

A form with a payment field takes money through Stripe, PayPal or both.
This is the side of the payment contract (version 1) that QUILL and QUILL
Lite publish to; the full guide for site owners is
[docs/payments-admin-guide.md](docs/payments-admin-guide.md).

- **Keys stay on the site.** You enter them under QAF Forms > Payments, or
  in `wp-config.php`. The screen never shows a secret again, no REST route
  returns one, and the payment page gets only the publishable key or client
  ID.
- **The site works out what is owed.** Fixed amounts, donations with a
  smallest and largest gift, and totals made of products, quantities,
  coupons, shipping and tax are all recomputed on the server from the form
  itself, in whole cents rather than floating point. Nothing the browser
  says about money is believed.
- **Stripe Payment Intents and PayPal Orders v2,** created on the server
  with idempotency keys, so a double click or a resent form cannot charge
  twice.
- **Signed webhooks.** Stripe's signatures are checked with the signing
  secret, PayPal's with PayPal's verification call. Each event counts once,
  must match the payment's environment, amount and currency, and can never
  move a paid or refunded entry backwards.
- **Test mode and live mode** for the whole site, with a check box to
  confirm before going live. A test form never takes money on a live site,
  and a live form is refused on a test site.
- **Entry status** (waiting, authorized, paid, failed, cancelled, partly
  refunded, refunded) in the entries REST reply and a payments report with
  totals per currency.
- **Accessible checkout:** labelled fields, errors tied to their field, a
  heading that takes focus when the payment panel appears, one status
  region and one alert, a keyboard-only flow, and nothing shown by colour
  alone.
- **Fulfilment hook:** `qaf_payment_paid` fires once per paid entry.

### Integrations

- **Stripe** and **PayPal** - see Payments above
- **Google Sheets** - Auto-sync entries to spreadsheets
- **Webhooks** - Custom API integrations
- **Email** - Notification and autoresponder emails
- **PDF Generation** - Generate PDFs from entries

### Security

- **Hold, never drop** - A form any spam check doubts is kept as spam with the reason and score, and the visitor sees the same thank-you; mark it Not Spam to bring it back. Checks that judge how someone used the page can only hold, never refuse. See the [Spam guide](docs/user-guide/spam-guide.md)
- **Quiet spam checks** - Honeypot, time to fill (never under three seconds), link count, code in answers, the site's Discussion word lists, rate limits and optional proof of work (ALTCHA format, self-hosted), all on the site
- **Spam services** - Akismet and OOPSpam when you turn them on, told about Spam and Not Spam so they learn; CleanTalk and Shield Security detected (not yet asked)
- **Text questions** - Asked only when the quiet checks are unsure, made fresh each visit, answered loosely, with Send Anyway after three tries
- **CSRF Protection** - All forms protected against cross-site request forgery
- **CAPTCHA Support** - reCAPTCHA v2/v3, hCaptcha and Turnstile
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

### Upgrading from BITS Accessible Forms

QUILL Accessible Forms is BITS Accessible Forms under its new name. Install
it and activate it; the old plugin can then be deleted. Your forms and
entries come across by migration, once, when QUILL Accessible Forms is
activated, or on the first request after the files are replaced (early,
before anything can make empty new tables, and before a visitor's form can
land in one).

The migration has numbered steps. The last one a site has had is kept in
the `qaf_migration_version` option, so each step runs once, a site that
already had the first step gets only the second, and running it again
changes nothing. What each step did is kept in the `qaf_migrated_from_baf`
option.

Step 1, the names:

- each `baf_` table is renamed to `qaf_`, with its rows. If a `qaf_` table
  already holds rows, neither is touched and the conflict is recorded;
- each `baf_` option is copied to `qaf_`, unless the new one already
  exists. The old options are left in place for one release, so going back
  to the old plugin still works;
- `baf_` and `_baf_` user, post and comment meta keys become `qaf_` and
  `_qaf_`;
- every role that had a `baf_` capability is given the `qaf_` one.

Step 2, what refers to the names:

- scheduled events on a `baf_` hook move to the `qaf_` hook, at the same
  time, with the same arguments;
- in every post (revisions too) and in the widget options, the
  `wp:baf/form` block becomes `wp:qaf/form` and `[baf_form]` and
  `[baf_entries]` become `[qaf_form]` and `[qaf_entries]`;
- a saved form's `extensions.baf_legacy` becomes `extensions.qaf_legacy`;
- a person given a `baf_` capability of their own is given the `qaf_` one.

BAF never had a post type of its own, so there is none to move. For one
release the old names still work, for anything the migration did not
reach:

- `[baf_form]` and `[baf_entries]`, and the `baf/form` block on the page;
- the REST namespace `baf/v1` answers GET requests as `qaf/v1` does, and
  says that it is deprecated. It changes nothing: writes need `qaf/v1`;
- form files saved as `.bfl` or `.baf` still open. New ones are saved as
  `.qaf`.

Uploaded files stay where they are.

#### Going back to BITS Accessible Forms

Take a database backup first. Then deactivate QUILL Accessible Forms, and
with your own table prefix in place of `wp_`:

1. Rename each `wp_qaf_` table back to `wp_baf_` (for example
   `RENAME TABLE wp_qaf_forms TO wp_baf_forms;`), for every table step 1
   renamed (they are listed in `qaf_migrated_from_baf`).
2. Undo step 2's text changes. Each is the same length as what it replaced,
   so this is safe on serialized widget options:

   ```sql
   UPDATE wp_posts SET post_content = REPLACE(REPLACE(REPLACE(post_content, 'wp:qaf/', 'wp:baf/'), '[qaf_', '[baf_'), '[/qaf_', '[/baf_');
   UPDATE wp_options SET option_value = REPLACE(REPLACE(REPLACE(option_value, 'wp:qaf/', 'wp:baf/'), '[qaf_', '[baf_'), '[/qaf_', '[/baf_') WHERE option_name LIKE 'widget\_%';
   UPDATE wp_baf_forms SET config = REPLACE(config, '"qaf_legacy"', '"baf_legacy"'), bfl_source = REPLACE(bfl_source, '"qaf_legacy"', '"baf_legacy"');
   ```

3. Activate BITS Accessible Forms. Its `baf_` options were never removed,
   but a setting changed under QAF is only in the `qaf_` option, so set it
   again. Meta keys moved to `qaf_` and the `qaf_` capabilities do no harm
   left in place. Scheduled events come back when BAF schedules them.
4. Delete the `qaf_migration_version` and `qaf_migrated_from_baf` options
   if you may install QUILL Accessible Forms again, so it migrates afresh.

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
- Spam checks, spam services and text questions (the Spam tab)
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
| `/qaf/v1/entries/{id}/spam` | POST | Mark as spam; tells the spam services and fires `qaf_entry_marked` |
| `/qaf/v1/entries/{id}/not-spam` | POST | Mark as not spam; restores it, tells the spam services, sends the notifications that waited |
| `/qaf/v1/spam` | GET | Each spam check's state, limits, question settings, this week's question numbers, a `revision` and `can_edit`; never a key |
| `/qaf/v1/spam` | POST | Change the spam settings (see below); `manage_options` or `qaf_manage_settings` |
| `/qaf/v1/spam/weeks` | GET | This week's question numbers and the weeks before, newest first (`weeks`, 1 to 13, default 12) |
| `/qaf/v1/spam/questions` | GET, POST | The site's own question bank: list it, or add a question; `manage_options` or `qaf_manage_settings` |
| `/qaf/v1/spam/questions/{id}` | POST, DELETE | Change or remove one question; same permission |
| `/qaf/v1/spam/pow` | GET | A proof-of-work challenge for a form page (when ALTCHA is on) |
| `/qaf/v1/bfl/parse` | POST | Parse QAF form language code |
| `/qaf/v1/bfl/validate` | POST | Validate QAF form language code |
| `/qaf/v1/payments/settings` | GET | Payment readiness (contract version 1); administrators only, never a key |
| `/qaf/v1/payments/start` | POST | Checkout: start or resume a payment (needs the submission's token) |
| `/qaf/v1/payments/finish` | POST | Checkout: capture an approved PayPal order (needs the token) |
| `/qaf/v1/payments/status` | POST | Checkout: the site asks the provider and answers (needs the token) |
| `/qaf/v1/payments/webhook/stripe` | POST | Stripe events, signature checked |
| `/qaf/v1/payments/webhook/paypal` | POST | PayPal events, verified with PayPal |

### Spam settings over REST

What wp-admin's Settings > Spam saves can also be changed through
`qaf/v1`, for QUILL's Workbench and anything else signed in as a person
who may change the site's settings (`manage_options`, or QAF's own
`qaf_manage_settings`). Reading the state needs only `qaf_view_entries`.
Every route checks the person's capability in its permission callback; a
browser session also needs WordPress's REST nonce (`X-WP-Nonce`), and an
application password needs nothing more.

**`POST /qaf/v1/spam`** changes only what it sends, and saves nothing when
any value is wrong: the 400 reply (`rest_invalid_param`) names every wrong
one in `data.params`. A name it does not know is refused
(`qaf_spam_unknown_setting`). It takes:

| Name | Value |
| ---- | ----- |
| `providers` | Object of check names and true or false: `honeypot`, `time`, `links`, `patterns`, `blocklist`, `rate_limit`, `token`, `captcha`, `akismet`, `oopspam`, `cleantalk`, `shield`, `altcha`, `challenge` |
| `min_seconds` | Time to fill, 3 to 120 |
| `max_links` | 0 to 50 |
| `oopspam_threshold` | 1 to 6 |
| `altcha_max_number` | 1000 to 1000000 |
| `use_disallowed`, `use_moderation` | true or false |
| `challenge_mode` | `auto`, `always` or `never` |
| `challenge_kinds` | Object: `sense`, `instruction`, `choice`, `order`, `maths`, each true or false |
| `form_modes` | Object of form ids and a mode; `null` gives the form back to the site's mode |
| `send_anyway_after` | Wrong answers before Send Anyway is offered, 1 to 10. It cannot be turned off: nobody is locked out |
| `oopspam_api_key` | Write-only: sets the key. No reply, error or log ever repeats it; GET says only `oopspam_key_saved` |
| `oopspam_api_key_clear` | true removes the saved key |
| `if_revision` | Optional: the `revision` GET gave. When the settings changed since, nothing is saved and the reply is 409 `qaf_spam_conflict` with the revision now saved |

The reply is the state GET gives, as now saved, with `changed`: the names
of the settings this write changed.

**The question bank.** `GET /qaf/v1/spam/questions` lists the site's own
questions, each `{ "id", "question", "answers": [...] }`, with `max` (200)
and the settings `revision`. `POST` to it with `question` and `answers` (a
list of 1 to 10) adds one (201). `POST /qaf/v1/spam/questions/{id}` with
`question` and/or `answers` changes one; `DELETE` removes it. A question's
id comes from its words, so a change gives it a new id (the reply says the
old one as `replaces`), and an id read before somebody else changed the
question answers 404 (`qaf_spam_question_not_found`). A question or answer
may not contain a bar (`|`), which the bank uses between answers.
Questions are at most 300 characters and answers 100.

**Past weeks.** `GET /qaf/v1/spam/weeks` gives `weeks`, newest first, each
`{ "week": "2026-W46", "asked", "passed", "failed", "sent_anyway" }`, with
no gaps, and `kept_days` (100): each week's numbers are kept that long.

Under `baf/v1`, for one release, the reads answer and the writes do not.

### Field input names

Every field's input posts as `qaf_field_<id>` (with `[]` for several values,
or `[part]` for a part of a composite field such as a name or an address).
The submission handler reads `fields[<id>]`, which the page script builds
from those names; a form posted without the script is read the same way
(`QAF_Field::submitted_values()`). Text, email and textarea fields used to
post `qaf_fields[<id>]`; that name is still read, so a form left open in a
browser while the plugin was updated still submits. Rich text posts its
hidden textarea, which the page script keeps in step with the editing area.

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

// A form was held as spam when it arrived (instead of qaf_form_submitted)
do_action( 'qaf_entry_held', $entry, $form, $decision );

// Someone marked an entry Spam ('spam') or Not Spam ('ham')
do_action( 'qaf_entry_marked', $entry_id, $as, $submission, $entry );
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

// Hold a form: add a verdict (it can hold, never refuse)
add_filter( 'qaf_spam_check', function( $verdicts, $submission ) {
    $verdicts[] = array( 'provider' => 'my_plugin', 'verdict' => 'unsure', 'reason' => 'Why.' );
    return $verdicts;
}, 10, 2 );

// Add a spam provider (an object implementing QAF_Spam_Provider)
add_filter( 'qaf_spam_providers', function( $providers, $settings ) {
    return $providers;
}, 10, 2 );

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
composer install
./vendor/bin/phpunit
```

The suite needs PHP 8.0 or later with the `mbstring` extension (PHPUnit
9.6 requires it). It runs without WordPress or a database: WordPress's
functions are stood in for by `tests/mocks/`, and the migration and REST
tests use in-memory stores.

The payment tests (`tests/unit/Payments/`) run without WordPress or a
network: every Stripe and PayPal reply in them is written by hand from the
providers' documented shapes and served by a fake transport, and the keys
are placeholders. They never charge anything. Live sandbox checks are a
separate, manual step (see the admin guide).

### Run JavaScript Tests

```bash
npm test
```

### Code Coverage

```bash
./vendor/bin/phpunit --coverage-html coverage/
```

`phpunit.xml` uses PHPUnit 9.6's `<coverage><include>` form (the old
`<filter><whitelist>` is gone, and with it the "deprecated schema" warning).

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
