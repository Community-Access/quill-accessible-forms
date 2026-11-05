# QUILL Accessible Forms (QAF) - Product Requirements Document

**Version:** 1.0  
**Last Updated:** January 27, 2026  
**Author:** BITS - Blind Information Technology Solutions  
**Status:** Development

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Product Vision](#product-vision)
3. [Target Audience](#target-audience)
4. [Core Differentiators](#core-differentiators)
5. [Accessibility Requirements (WCAG 2.2 AA)](#accessibility-requirements-wcag-22-aa)
6. [Feature Specifications](#feature-specifications)
7. [Technical Architecture](#technical-architecture)
8. [The QAF form language specification](#the-qaf-form-language-specification)
9. [Field Types](#field-types)
10. [Advanced Features](#advanced-features)
11. [Entry Views](#entry-views)
12. [Integrations](#integrations)
13. [Security](#security)
14. [User Interface Requirements](#user-interface-requirements)
15. [REST API](#rest-api)
16. [Testing Requirements](#testing-requirements)
17. [Deployment & Release Strategy](#deployment--release-strategy)

---

## Executive Summary

QUILL Accessible Forms (QAF) is a revolutionary WordPress form builder plugin that places accessibility at its core, not as an afterthought. Unlike existing solutions that treat accessibility as optional or premium features, QAF ensures every form created is automatically WCAG 2.2 AA compliant while providing all advanced features included without paid add-ons.

### Key Statistics

| Metric | Value |
| ------ | ----- |
| Field Types | 50 |
| Advanced Features | 59 |
| Choice Libraries | 125+ |
| Global Settings | 24 |
| Entry View Layouts | 5 |
| WCAG 2.2 AA Criteria Met | 15/15 |
| Paid Add-ons Required | 0 |

---

## Product Vision

### Mission Statement

"Technology should work for everyone. Not as an afterthought. Not as a checkbox on a compliance form. But as a fundamental promise built into every line of code."

### Goals

1. **Accessibility First**: Every form automatically meets WCAG 2.2 AA standards
2. **Feature Complete**: All features included without add-on fatigue
3. **Multiple Input Methods**: Full keyboard, mouse, touch, and voice support
4. **Forms as Code**: QAF form language for text-based form building and version control
5. **Structure Mode**: Revolutionary keyboard-first tree navigation
6. **Universal Design**: Works for all users regardless of ability

---

## Target Audience

### Primary Users

1. **Accessibility-Focused Organizations**: Government agencies, educational institutions, healthcare providers
2. **Blind/Low-Vision Developers**: Users who rely on screen readers and keyboard navigation
3. **Enterprise Teams**: Organizations requiring compliance
4. **Small Business Owners**: Non-technical users needing accessible forms
5. **Teachers/Educators**: Quiz and survey creators
6. **Developers**: Those wanting to integrate forms into applications

### User Personas

#### Persona 1: Sarah (Screen Reader User)

- **Role**: HR Manager at a government agency
- **Needs**: Create job application forms fully accessible to all applicants
- **Pain Points**: Current form builders require extensive configuration for accessibility
- **Goals**: Build forms quickly without accessibility expertise

#### Persona 2: Marcus (Web Developer)

- **Role**: Freelance developer
- **Needs**: Version control for forms, code-based editing
- **Pain Points**: GUI-only builders don't fit development workflows
- **Goals**: Use QAF form language to manage forms in Git, review changes in PRs

#### Persona 3: Elena (Small Business Owner)

- **Role**: Bakery owner
- **Needs**: Order forms with payment processing
- **Pain Points**: Accessibility add-ons cost extra, complex to configure
- **Goals**: Simple setup with all features included

---

## Core Differentiators

### 1. Structure Mode (Industry First)

A keyboard-first tree view for organizing forms:

- Navigate using only arrow keys
- Move, duplicate, and delete with keyboard shortcuts
- Convert field types while preserving properties
- Real-time warnings when changes could break conditional logic
- Full screen reader support with ARIA live regions

### 2. The QAF form language

Human-readable form definitions stored as text:

```bfl
form "Contact Us"
  page "Your Information"
    section "Contact Details"
      field text "Name" required
        placeholder "Enter your full name"
        autocomplete "name"
      field email "Email" required
        validation "email"
      field textarea "Message" required
        minLength 10
        maxLength 500
```

### 3. Accessibility by Default

- Every field automatically includes proper labels
- ARIA attributes applied without configuration
- Error messages announced to screen readers
- Keyboard navigation built-in
- Focus management automatic
- Color contrast guaranteed

### 4. CSV Auto-Form Generation

Import CSV files to automatically generate forms:

- Detect column types (text, number, date, etc.)
- Identify dropdown candidates (limited unique values)
- Create radio buttons for boolean/yes-no columns
- Generate checkboxes for multi-value columns
- Smart field naming from headers

---

## Accessibility Requirements (WCAG 2.2 AA)

### Principle 1: Perceivable

#### 1.1 Text Alternatives

- [x] All non-text content has text alternatives (1.1.1)
- [x] Images in forms use proper alt text
- [x] Icons have accessible labels

#### 1.2 Time-based Media

- [x] Not applicable (no time-based media in forms)

#### 1.3 Adaptable

- [x] Information and relationships programmatically determinable (1.3.1)
- [x] Meaningful sequence preserved (1.3.2)
- [x] Instructions don't rely solely on sensory characteristics (1.3.3)
- [x] Content works in all orientations (1.3.4)
- [x] Input purpose programmatically determinable (1.3.5)

#### 1.4 Distinguishable

- [x] Color not sole means of conveying information (1.4.1)
- [x] Audio control provided (1.4.2)
- [x] Contrast ratio minimum 4.5:1 for text (1.4.3)
- [x] Text resizable to 200% (1.4.4)
- [x] No images of text (1.4.5)
- [x] Reflow at 400% zoom (1.4.10)
- [x] Non-text contrast 3:1 (1.4.11)
- [x] Text spacing adjustable (1.4.12)
- [x] Hover/focus content dismissible (1.4.13)

### Principle 2: Operable

#### 2.1 Keyboard Accessible

- [x] All functionality keyboard accessible (2.1.1)
- [x] No keyboard traps (2.1.2)
- [x] Character key shortcuts configurable (2.1.4)

#### 2.2 Enough Time

- [x] Timing adjustable (2.2.1)
- [x] Pause/stop/hide for moving content (2.2.2)

#### 2.3 Seizures

- [x] No content flashes more than 3 times/second (2.3.1)

#### 2.4 Navigable

- [x] Bypass blocks mechanism (2.4.1)
- [x] Pages have titles (2.4.2)
- [x] Focus order logical (2.4.3)
- [x] Link purpose determinable (2.4.4)
- [x] Multiple ways to find pages (2.4.5)
- [x] Headings and labels descriptive (2.4.6)
- [x] Focus visible (2.4.7)
- [x] Focus not obscured (2.4.11)

#### 2.5 Input Modalities

- [x] Pointer gestures have alternatives (2.5.1)
- [x] Pointer cancellation supported (2.5.2)
- [x] Label in name (2.5.3)
- [x] Motion actuation alternatives (2.5.4)
- [x] Dragging alternatives provided (2.5.7) **CRITICAL FOR QAF**
- [x] Target size minimum 24x24px (2.5.8)

### Principle 3: Understandable

#### 3.1 Readable

- [x] Language of page set (3.1.1)
- [x] Language of parts can be set (3.1.2)

#### 3.2 Predictable

- [x] No change of context on focus (3.2.1)
- [x] No change of context on input unless warned (3.2.2)
- [x] Consistent navigation (3.2.3)
- [x] Consistent identification (3.2.4)
- [x] Consistent help location (3.2.6)

#### 3.3 Input Assistance

- [x] Error identification (3.3.1)
- [x] Labels or instructions provided (3.3.2)
- [x] Error suggestions provided (3.3.3)
- [x] Error prevention for important submissions (3.3.4)
- [x] Redundant entry avoided (3.3.7)
- [x] Accessible authentication (3.3.8)

### Principle 4: Robust

#### 4.1 Compatible

- [x] Name, role, value programmatically determinable (4.1.2)
- [x] Status messages announced (4.1.3)

### Accessibility Coach

QAF includes a built-in accessibility coach that analyzes forms in real-time:

**Features:**
- **Accessibility Score** - 0-100 rating based on issue severity
- **Issue Detection** - Scans for missing labels, placeholder-as-label, generic labels, missing help text, duplicate IDs, and more
- **Category Grouping** - Issues organized by Labels, Help Text, Structure, Validation
- **Fix Suggestions** - Actionable guidance for each issue
- **Click-to-Navigate** - Jump directly to problem fields
- **Auto-Analyze Mode** - Optionally re-scan on every form change

**Keyboard Shortcut:** Ctrl+Shift+A (Windows) or Cmd+Shift+A (Mac)

**Checks Performed:**
| Check | Severity | WCAG Criteria |
|-------|----------|---------------|
| Missing field label | Error | 3.3.2, 4.1.2 |
| Placeholder as label | Error | 3.3.2 |
| Generic/unclear label | Warning | 2.4.6 |
| Missing help text (complex fields) | Info | 3.3.2 |
| Missing validation message | Warning | 3.3.1 |
| Duplicate field IDs | Error | 4.1.1 |
| Hidden legend on grouped controls | Error | 1.3.1 |
| Missing page title (multi-page) | Warning | 2.4.2 |

See the [Accessibility Coach Guide](user-guide/accessibility-coach-guide.md) for complete documentation.

---

## Feature Specifications

### Form Builder Interface

#### Visual Builder Mode

```text
┌─────────────────────────────────────────────────────────────────────┐
│ [Skip to Content] [Skip to Fields] [Skip to Settings]               │
├─────────────────────────────────────────────────────────────────────┤
│ QUILL Accessible Forms - Edit: Contact Form                          │
├──────────────────┬──────────────────────────────────────────────────┤
│                  │                                                  │
│  FIELD PALETTE   │              FORM CANVAS                         │
│  ┌────────────┐  │  ┌──────────────────────────────────────────┐   │
│  │ Text       │  │  │ Page 1: Contact Info                      │   │
│  │ Email      │  │  │ ┌────────────────────────────────────┐   │   │
│  │ Textarea   │  │  │ │ Section: Personal Details           │   │   │
│  │ Number     │  │  │ │ ┌──────────────────────────────┐   │   │   │
│  │ Select     │  │  │ │ │ Field: Name (Text)            │   │   │   │
│  │ Radio      │  │  │ │ │ [↑] [↓] [Edit] [Copy] [Del]   │   │   │   │
│  │ Checkbox   │  │  │ │ └──────────────────────────────┘   │   │   │
│  │ ...        │  │  │ │ ┌──────────────────────────────┐   │   │   │
│  └────────────┘  │  │ │ │ Field: Email (Email)          │   │   │   │
│                  │  │ │ │ [↑] [↓] [Edit] [Copy] [Del]   │   │   │   │
│  [Add Field]     │  │ │ └──────────────────────────────┘   │   │   │
│  Keyboard: F     │  │ └────────────────────────────────────┘   │   │
│                  │  └──────────────────────────────────────────┘   │
│                  │                                                  │
└──────────────────┴──────────────────────────────────────────────────┘
│  Mode: [Visual] [Structure] [QAF Form Language]  │  [Preview] [Save] [Publish]
└─────────────────────────────────────────────────────────────────────┘
```

#### Structure Mode (Tree View)

```text
Form: Contact Form
├── Page 1: Contact Info
│   ├── Section: Personal Details
│   │   ├── Field: Name (text) [required]
│   │   ├── Field: Email (email) [required]
│   │   └── Field: Phone (phone)
│   └── Section: Message
│       ├── Field: Subject (select)
│       └── Field: Message (textarea) [required]
└── Page 2: Confirmation
    └── Section: Review
        └── Field: Terms (checkbox) [required]

Keyboard Shortcuts:
↑/↓    Navigate items
←/→    Collapse/Expand
Enter  Edit item
Space  Select item
Ctrl+↑ Move item up
Ctrl+↓ Move item down
Ctrl+C Copy item
Ctrl+V Paste item
Delete Remove item
Tab    Next element
```

#### QAF Form Language Mode

```text
┌─────────────────────────────────────────────────────────────────────┐
│ the QAF form language Editor                                          [Sync] [Format] │
├─────────────────────────────────────────────────────────────────────┤
│  1  form "Contact Form"                                             │
│  2    settings                                                      │
│  3      honeypot true                                               │
│  4      csrf true                                                   │
│  5      rateLimit 10 per minute                                     │
│  6                                                                  │
│  7    page "Contact Info"                                           │
│  8      section "Personal Details"                                  │
│  9        field text "Name" required                                │
│ 10          placeholder "Your full name"                            │
│ 11          autocomplete "name"                                     │
│ 12          minLength 2                                             │
│ 13                                                                  │
│ 14        field email "Email" required                              │
│ 15          placeholder "your@email.com"                            │
│ 16          autocomplete "email"                                    │
│ 17          validation                                              │
│ 18            noDisposable true                                     │
│ 19                                                                  │
│ 20        field phone "Phone"                                       │
│ 21          format "(###) ###-####"                                 │
│ 22                                                                  │
│ 23      section "Message"                                           │
│ 24        field select "Subject"                                    │
│ 25          options                                                 │
│ 26            "General Inquiry"                                     │
│ 27            "Support Request"                                     │
│ 28            "Sales Question"                                      │
│ 29                                                                  │
│ 30        field textarea "Message" required                         │
│ 31          minLength 10                                            │
│ 32          maxLength 2000                                          │
│ 33          counter true                                            │
└─────────────────────────────────────────────────────────────────────┘
│  Line 14, Col 8  │  UTF-8  │  QAF form language v1.0  │  Errors: 0  Warnings: 0  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## The QAF form language specification

### Grammar Definition

```ebnf
form           = "form" STRING settings? page+
settings       = "settings" setting*
setting        = IDENTIFIER value
page           = "page" STRING section+
section        = "section" STRING field+
field          = "field" field_type STRING modifier* property*
field_type     = "text" | "textarea" | "email" | "url" | "password" 
               | "phone" | "number" | "currency" | "range" | "calculated"
               | "select" | "radio" | "checkbox" | "multiselect" | "combobox"
               | "toggle" | "rating" | "likert" | "ranking" | "nps"
               | "name" | "address" | "repeater" | "nested"
               | "file" | "image" | "color"
               | "product" | "quantity" | "coupon" | "total"
               | "date" | "time" | "datetime"
               | "hidden" | "html" | "section_break" | "page_break"
modifier       = "required" | "readonly" | "hidden" | "disabled"
property       = IDENTIFIER value | IDENTIFIER block
block          = INDENT (property | option)* DEDENT
option         = STRING (":" value)?
value          = STRING | NUMBER | BOOLEAN | list
list           = "[" (value ("," value)*)? "]"

STRING         = '"' [^"]* '"' | "'" [^']* "'"
NUMBER         = [0-9]+ ("." [0-9]+)?
BOOLEAN        = "true" | "false"
IDENTIFIER     = [a-zA-Z_][a-zA-Z0-9_]*
```

### Complete the QAF form language Example

```bfl
# QUILL Accessible Forms - the QAF form language Example
# Job Application Form

form "Job Application"
  settings
    title "Apply for a Position"
    description "Complete this form to apply for a job at our company"
    honeypot true
    csrf true
    rateLimit 5 per hour
    saveAndContinue true
    confirmationPage "thank-you"
    notification
      to "{admin_email}"
      subject "New Job Application: {Name}"
      replyTo "{Email}"
    
  page "Personal Information"
    progress true
    
    section "Basic Details"
      description "Please provide your contact information"
      
      field name "Full Name" required
        prefix "Name"
        first
          label "First Name"
          autocomplete "given-name"
        last
          label "Last Name"
          autocomplete "family-name"
          
      field email "Email Address" required
        placeholder "your.email@example.com"
        autocomplete "email"
        validation
          noDisposable true
          
      field phone "Phone Number" required
        format "international"
        autocomplete "tel"
        
      field address "Address" required
        autocomplete true
        components
          street true
          city true
          state true
          zip true
          country true
          
    section "Professional Links"
      field url "LinkedIn Profile"
        placeholder "https://linkedin.com/in/yourprofile"
        
      field url "Portfolio Website"
        placeholder "https://yourportfolio.com"
        
  page "Experience"
    section "Work History"
      field repeater "Previous Positions"
        minRows 1
        maxRows 10
        addLabel "Add Another Position"
        fields
          field text "Company Name" required
          field text "Job Title" required
          field date "Start Date" required
          field date "End Date"
            conditionalLogic
              show when "Currently Employed" is false
          field checkbox "Currently Employed"
          field textarea "Responsibilities"
            maxLength 500
            
    section "Education"
      field repeater "Education History"
        minRows 1
        maxRows 5
        fields
          field text "Institution" required
          field select "Degree Type" required
            choiceLibrary "education"
          field text "Field of Study"
          field number "Graduation Year"
            min 1950
            max 2030
            
  page "Position Details"
    section "Job Preferences"
      field select "Position Applying For" required
        options
          "Software Developer"
          "UX Designer"
          "Project Manager"
          "Quality Assurance"
          "DevOps Engineer"
          
      field radio "Employment Type" required
        options
          "Full-time"
          "Part-time"
          "Contract"
          
      field checkbox "Work Arrangements"
        options
          "Remote"
          "Hybrid"
          "On-site"
          
      field currency "Salary Expectation"
        currency "USD"
        min 30000
        max 500000
        
      field date "Available Start Date" required
        minDate "today"
        
    section "Documents"
      field file "Resume/CV" required
        accept ".pdf,.doc,.docx"
        maxSize 5MB
        description "Upload your resume in PDF or Word format"
        
      field file "Cover Letter"
        accept ".pdf,.doc,.docx"
        maxSize 5MB
        
  page "Review & Submit"
    section "Preview"
      field html "Application Preview"
        content "<p>Please review your application before submitting.</p>"
        
      field previewSubmission
        
    section "Agreement"
      field checkbox "Terms" required
        label "I certify that all information provided is accurate"
        
      field checkbox "Privacy" required
        label "I agree to the privacy policy"
        link "/privacy-policy"
        
    submit "Submit Application"
      loadingText "Submitting..."
      successMessage "Thank you for your application!"
```

### the QAF form language Import/Export

```php
// Import QAF form language to form definition
$form = QAF_BFL_Parser::parse($bfl_string);

// Export form to the QAF form language
$bfl = QAF_BFL_Compiler::compile($form);

// Validate QAF form language syntax
$errors = QAF_BFL_Validator::validate($bfl_string);
```

---

## Field Types

### Category 1: Text & Numeric (11 types)

| Field Type | Description | HTML Element | ARIA Role |
| ---------- | ----------- | ------------ | --------- |
| Text | Single line text | `<input type="text">` | textbox |
| Textarea | Multi-line text | `<textarea>` | textbox |
| Rich Text | WYSIWYG editor | Custom (Accessible) | application |
| Email | Email address | `<input type="email">` | textbox |
| URL | Web address | `<input type="url">` | textbox |
| Password | Secure input | `<input type="password">` | textbox |
| Phone | Phone number | `<input type="tel">` | textbox |
| Number | Numeric input | `<input type="number">` | spinbutton |
| Currency | Money values | `<input type="text">` | textbox |
| Range/Slider | Value range | `<input type="range">` | slider |
| Calculated | Formula-based | Display only | status |

### Category 2: Choice Fields (7 types)

| Field Type | Description | HTML Element | ARIA Role |
| ---------- | ----------- | ------------ | --------- |
| Select | Dropdown | `<select>` | listbox |
| Radio | Single choice | `<input type="radio">` | radiogroup |
| Checkbox | Multiple choice | `<input type="checkbox">` | group |
| Multi-Select | Multiple dropdown | `<select multiple>` | listbox |
| Combobox | Searchable select | Custom | combobox |
| Toggle | On/Off switch | `<input type="checkbox">` | switch |
| Advanced Select | Tags/search | Custom | combobox |

### Category 3: Survey & Quiz (10 types)

| Field Type | Description | Accessibility Features |
| ---------- | ----------- | ---------------------- |
| Rating | Star/number scale | Arrow key navigation, value announcements |
| Likert | Agreement scale | Radio group with matrix layout |
| Ranking | Drag to reorder | **Keyboard reordering with button alternatives** |
| NPS | Net Promoter Score | 0-10 scale with keyboard nav |
| Image Choice | Select images | Images with proper alt text, keyboard selection |
| Slider Survey | Visual scale | Accessible slider with tick marks |
| Quiz Question | Multiple choice | Immediate feedback option |
| Quiz True/False | Boolean quiz | Radio group |
| Quiz Fill-Blank | Text completion | Input within text context |
| Quiz Matching | Match pairs | **Accessible drag-drop with keyboard** |

### Category 4: Composite Fields (6 types)

| Field Type | Components | Accessibility |
| ---------- | ---------- | ------------- |
| Name | First, Middle, Last, Prefix, Suffix | Fieldset with legend |
| Address | Street, City, State, Zip, Country | Fieldset with autocomplete |
| Address Autocomplete | Google Places integration | Combobox with suggestions |
| Phone with Country | Country code + number | Grouped inputs |
| Repeater | Add/remove rows | **Keyboard add/remove/reorder** |
| Nested Forms | Subform embedding | Proper heading hierarchy |

### Category 5: Files & Media (3 types)

| Field Type | Features | Accessibility |
| ---------- | -------- | ------------- |
| File Upload | Drag-drop, chunked | **Keyboard upload button always visible** |
| Image Upload | Preview, crop | Alt text input, preview description |
| Color Picker | Visual selection | **Hex input alternative, presets keyboard-accessible** |

### Category 6: Date & Time (4 types)

| Field Type | HTML/Component | Accessibility |
| ---------- | -------------- | ------------- |
| Date | `<input type="date">` or picker | Native + custom accessible picker |
| Time | `<input type="time">` or picker | Native + custom accessible picker |
| DateTime | Combined | Proper grouping |
| Date Range | Start/End | **Clear start/end relationship** |

### Category 7: Payment Fields (7 types)

| Field Type | Purpose | Security |
| ---------- | ------- | -------- |
| Product | Item selection | PCI scope minimized |
| Quantity | Amount selector | Number validation |
| Coupon | Discount codes | Server validation |
| Shipping | Methods/rates | Conditional logic |
| Total | Calculated sum | Read-only display |
| User Price | Name-your-price | Range validation |
| Payment | Gateway integration | PCI compliant iframes |

### Category 8: Advanced Fields (9 types)

| Field Type | Purpose |
| ---------- | ------- |
| Unique ID | Auto-generated identifiers |
| QR Code | Generated QR display |
| Quiz Score | Calculated quiz result |
| Quiz Results | Detailed breakdown |
| Survey Summary | Response analytics |
| Entry Link | Link to submission |
| Post Field | WordPress post data |
| Hidden | Non-visible data |
| HTML | Static content |

### Category 9: Layout Elements (6 types)

| Element | Purpose | Accessibility |
| ------- | ------- | ------------- |
| Section | Group fields | `<fieldset>` with `<legend>` |
| Page | Multi-page form | Progress announcements |
| Column | Layout grid | Logical reading order |
| HTML Block | Custom content | Semantic HTML required |
| Section Break | Visual separator | Hidden from AT unless descriptive |
| Page Break | New page trigger | Navigation instructions |

---

## Advanced Features

### Smart Population (6 Features)

#### Smart Defaults

`smart_default` populates a field when the user hasn’t entered a value.

- `geo` (via filter `qaf_smart_default_geo`)
- `today+N`
- `user:property`

#### Unique Value Validation

Enforce unique values with configurable scope:
- `unique_scope: "form"` (default)
- `unique_scope: "global"`

Optional `unique_message` overrides the default error text.

#### Input Masking

Text-like fields can accept `mask` / `@mask` to guide input formatting (progressive enhancement).

#### Autocomplete Provider Model

QAF uses an extensible provider model for autocomplete functionality:

**Built-in Providers:**
- `static` - Pre-loaded datasets (countries, states, provinces, timezones, languages, currencies)
- `browser` - HTML5 autocomplete tokens (personal, contact, address, payment info)
- `google_places` - Google Places API for address autocomplete
- `fallback` - Country/state dropdown cascade when external APIs unavailable

**Graceful Degradation:**
- If Google Places API key is not configured, address fields automatically fall back to static country/state dropdowns
- All providers are availability-checked before use
- Custom providers can be registered via `qaf_register_autocomplete_providers` action

**Usage in the QAF form language:**
```bfl
field text "Country" 
  autocomplete "country"          // Static dataset
  
field text "Address"
  autocomplete "street-address"   // Browser token
  
field address "Full Address"
  provider "google_places"        // API-based
```

See the [Autocomplete Providers Guide](user-guide/autocomplete-providers-guide.md) for complete documentation.


#### 1. Dynamic Population

```php
// Populate from various sources
[qaf_field name="user_name" populate="user:display_name"]
[qaf_field name="post_title" populate="post:post_title"]
[qaf_field name="entry_value" populate="entry:15:field_3"]
[qaf_field name="api_data" populate="api:https://api.example.com/data"]
```

#### 2. Live Merge Tags

```html
<p>Hello, {field:name}! Your total is {field:total:currency}.</p>
```

#### 3. Chained Selects

```bfl
field select "Country" required
  choiceLibrary "countries"
  
field select "State/Province" required
  chainedTo "Country"
  dataSource "states-by-country"
```

#### 4. URL Parameters

```php
// Auto-populate from URL: ?name=John&email=john@example.com
[qaf_field name="name" urlParam="name"]
[qaf_field name="email" urlParam="email"]
```

#### 5. Easy Passthrough

```bfl
// Pass values between forms
redirect "form-2"
  passthrough ["name", "email", "reference_id"]
```

#### 6. Field Value Objects

```bfl
field select "Product"
  options
    "Widget A": { price: 10.00, sku: "WA001" }
    "Widget B": { price: 15.00, sku: "WB001" }
    
field calculated "Price"
  formula "{Product.price}"
```

### Calculations & Pricing (3 Features)

#### 1. Advanced Calculations

```bfl
field calculated "Total"
  formula "({quantity} * {price}) - {discount}"
  
field calculated "Average"
  formula "avg({score_1}, {score_2}, {score_3})"
  
field calculated "Conditional"
  formula "if({type} == 'premium', {base} * 1.5, {base})"
```

#### 2. Date/Time Calculator

```bfl
field calculated "Age"
  formula "yearsBetween({birth_date}, today())"
  
field calculated "Duration"
  formula "businessDays({start_date}, {end_date})"
```

#### 3. Conditional Pricing

```bfl
field product "Service"
  basePrice 100
  conditionalPricing
    when {membership} is "gold" then multiply 0.8
    when {quantity} > 10 then multiply 0.9
```

### Field Behaviors (6 Features)

#### Field Synchronization

```bfl
field text "Billing Name"

field text "Shipping Name"
  syncWith "Billing Name"
  syncTrigger "checkbox:same_as_billing"
```

#### Word/Character Counters

```bfl
field textarea "Essay"
  counter
    type "words"
    min 100
    max 500
    display "remaining"
```

### Validation & Security (6 Features)

#### Email Validation

```bfl
field email "Email" required
  validation
    noDisposable true
    blockedDomains ["tempmail.com", "guerrillamail.com"]
    requireMX true
```

#### Blocklist

```bfl
settings
  blocklist
    values ["spam", "test"]
    patterns ["\\d{10,}"] # Block long numbers
    profanity true
```

### User Experience (11 Features)

#### Save & Continue

```bfl
settings
  saveAndContinue
    autoSave true
    autoSaveInterval 30 # seconds
    multipleDrafts true
    resumeLink "email" # or "token"
```

#### Preview Before Submit

```bfl
page "Review"
  section "Preview"
    field previewSubmission
      template "detailed"
      editLinks true
```

#### Multi-Page Navigation

```bfl
settings
  pagination
    progressBar true
    progressType "steps" # or "percentage"
    backButton true
    savePerPage true
    
  transition "slide" # respects prefers-reduced-motion
```

---

## Entry Views

### View Types

#### 1. Table Layout

```bfl
view table "All Entries"
  source "contact-form"
  columns
    "Name" sortable searchable
    "Email" sortable searchable
    "Date" sortable
    "Status" filterable
  pagination 25
  search true
  export ["csv", "xlsx"]
```

**Accessibility Features:**

- Proper `<th>` headers with `scope`
- Sortable columns with `aria-sort`
- Keyboard-navigable rows
- Screen reader announcements for updates

#### 2. Cards Layout

```bfl
view cards "Directory"
  source "member-form"
  card
    image "{photo}"
    title "{name}"
    subtitle "{title}"
    fields ["email", "phone", "department"]
  columns 3
  cardSize "medium"
```

**Accessibility Features:**

- Cards as `<article>` elements
- Proper heading hierarchy
- Focus indicators on cards
- Keyboard navigation between cards

#### 3. List Layout

```bfl
view list "Submissions"
  source "feedback-form"
  template
    primary "{subject}"
    secondary "{date} - {status}"
    detail "{message:truncate:100}"
```

#### 4. Map Layout

```bfl
view map "Locations"
  source "location-form"
  mapProvider "leaflet" # Open source, accessible
  addressField "address"
  markerTitle "{name}"
  markerContent "{description}"
  clustering true
```

**Accessibility Features:**

- List view alongside map
- Keyboard-navigable markers
- Screen reader marker descriptions
- Skip link to bypass map

#### 5. Calendar Layout

```bfl
view calendar "Events"
  source "event-form"
  dateField "event_date"
  titleField "event_name"
  modes ["month", "week", "agenda"]
  defaultMode "month"
```

**Accessibility Features:**

- Proper date table structure
- Agenda view for screen readers
- Keyboard navigation (arrows for dates)
- Current date announcements

---

## Integrations

### Payment Gateways

#### Stripe Integration

```php
class QAF_Stripe_Integration {
    public function process_payment($entry, $form) {
        // PCI-compliant Stripe Elements
        // Accessible card input
        // Proper error handling with announcements
    }
}
```

#### PayPal Integration

```php
class QAF_PayPal_Integration {
    public function render_button($form) {
        // PayPal Smart Button with fallback
        // Keyboard-accessible checkout
    }
}
```

### External Services

#### Google Sheets

```bfl
integration googleSheets
  spreadsheetId "abc123"
  sheetName "Responses"
  mapping
    "A": "{name}"
    "B": "{email}"
    "C": "{date}"
  sync "bidirectional"
```

#### Webhooks

```bfl
integration webhook
  url "https://api.example.com/webhook"
  method "POST"
  headers
    "Authorization": "Bearer {api_key}"
  events ["submission", "update", "delete"]
  retries 3
```

#### REST API

Endpoints:

- `GET /wp-json/qaf/v1/forms` - List forms
- `GET /wp-json/qaf/v1/forms/{id}` - Get form
- `POST /wp-json/qaf/v1/forms` - Create form
- `PUT /wp-json/qaf/v1/forms/{id}` - Update form
- `DELETE /wp-json/qaf/v1/forms/{id}` - Delete form
- `GET /wp-json/qaf/v1/entries` - List entries
- `POST /wp-json/qaf/v1/entries` - Create entry
- `GET /wp-json/qaf/v1/entries/{id}` - Get entry

---

## Security

### Built-in Security Features

#### 1. Honeypot Protection

```php
// Invisible field for bots
<div class="qaf-hp" aria-hidden="true" style="position:absolute;left:-9999px;">
    <label for="qaf_hp_field">Leave empty</label>
    <input type="text" id="qaf_hp_field" name="qaf_hp_field" tabindex="-1" autocomplete="off">
</div>
```

#### 2. CSRF Protection

```php
// Nonce verification
wp_nonce_field('qaf_submit_form_' . $form_id, 'qaf_nonce');
```

#### 3. Rate Limiting

```php
class QAF_Rate_Limiter {
    public function check($form_id, $ip) {
        // Per-IP rate limiting
        // Per-form rate limiting
        // Configurable thresholds
    }
}
```

#### 4. CAPTCHA Support

- reCAPTCHA v2 (checkbox)
- reCAPTCHA v3 (invisible)
- hCaptcha
- Cloudflare Turnstile

**Accessibility Note:** All CAPTCHA implementations include accessible alternatives.

#### 5. File Upload Security

```php
class QAF_File_Security {
    private $allowed_types = ['jpg', 'png', 'pdf', 'doc', 'docx'];
    
    public function validate($file) {
        // MIME type checking
        // Extension validation
        // Malware scanning hook
        // Size limits
    }
}
```

#### 6. Input Sanitization

```php
class QAF_Sanitizer {
    public function sanitize($value, $field_type) {
        // Type-specific sanitization
        // XSS prevention
        // SQL injection prevention
    }
}
```

---

## User Interface Requirements

### Keyboard Navigation

#### Global Shortcuts

| Shortcut | Action |
| -------- | ------ |
| `Ctrl+S` | Save form |
| `Ctrl+P` | Preview form |
| `Ctrl+Z` | Undo |
| `Ctrl+Y` | Redo |
| `Ctrl+F` | Find field |
| `F1` | Help |
| `F6` | Cycle panels |
| `Escape` | Close modal/cancel |

#### Structure Mode Shortcuts

| Shortcut | Action |
| -------- | ------ |
| `↑/↓` | Navigate items |
| `←/→` | Collapse/Expand |
| `Enter` | Edit item |
| `Space` | Select item |
| `Ctrl+↑` | Move up |
| `Ctrl+↓` | Move down |
| `Ctrl+C` | Copy |
| `Ctrl+V` | Paste |
| `Ctrl+D` | Duplicate |
| `Delete` | Remove |
| `F2` | Rename |
| `Ctrl+Enter` | Add field after |

#### Drag-Drop Alternatives

Every drag-drop operation MUST have a button alternative:

```html
<div class="qaf-field" role="listitem">
    <span class="qaf-field-name">Name Field</span>
    <div class="qaf-field-actions">
        <button aria-label="Move Name Field up" class="qaf-move-up">
            <span aria-hidden="true">↑</span>
        </button>
        <button aria-label="Move Name Field down" class="qaf-move-down">
            <span aria-hidden="true">↓</span>
        </button>
        <button aria-label="Edit Name Field" class="qaf-edit">
            Edit
        </button>
        <button aria-label="Duplicate Name Field" class="qaf-duplicate">
            Copy
        </button>
        <button aria-label="Delete Name Field" class="qaf-delete">
            Delete
        </button>
    </div>
</div>
```

### Focus Management

```javascript
class QAFFocusManager {
    // Trap focus in modals
    trapFocus(container) { }
    
    // Return focus after modal close
    restoreFocus() { }
    
    // Announce dynamic content
    announce(message, priority = 'polite') { }
    
    // Skip link targets
    registerSkipTarget(id, label) { }
}
```

### Color Scheme Support

```css
/* Light mode (default) */
:root {
    --qaf-bg-primary: #ffffff;
    --qaf-text-primary: #1a1a1a;
    --qaf-accent: #0066cc;
    --qaf-error: #cc0000;
    --qaf-success: #008800;
}

/* Dark mode */
@media (prefers-color-scheme: dark) {
    :root {
        --qaf-bg-primary: #1a1a1a;
        --qaf-text-primary: #ffffff;
        --qaf-accent: #66b3ff;
        --qaf-error: #ff6666;
        --qaf-success: #66ff66;
    }
}

/* High contrast mode */
@media (prefers-contrast: more) {
    :root {
        --qaf-bg-primary: #000000;
        --qaf-text-primary: #ffffff;
        --qaf-accent: #ffff00;
        --qaf-error: #ff0000;
        --qaf-success: #00ff00;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
```

---

## Technical Architecture

### Plugin Structure

```text
quill-accessible-forms/
├── quill-accessible-forms.php          # Main plugin file
├── README.md
├── LICENSE
├── composer.json
├── package.json
├── webpack.config.js
│
├── includes/
│   ├── class-qaf-loader.php           # Dependency loader
│   ├── class-qaf-activator.php        # Activation hooks
│   ├── class-qaf-deactivator.php      # Deactivation hooks
│   │
│   ├── core/
│   │   ├── class-qaf-form.php         # Form model
│   │   ├── class-qaf-field.php        # Field base class
│   │   ├── class-qaf-entry.php        # Entry model
│   │   ├── class-qaf-view.php         # View model
│   │   └── class-qaf-settings.php     # Settings management
│   │
│   ├── fields/
│   │   ├── class-qaf-field-text.php
│   │   ├── class-qaf-field-email.php
│   │   ├── class-qaf-field-textarea.php
│   │   ├── ... (all 63 field types)
│   │   └── class-qaf-field-registry.php
│   │
│   ├── bfl/
│   │   ├── class-qaf-bfl-lexer.php    # Tokenizer
│   │   ├── class-qaf-bfl-parser.php   # Parser
│   │   ├── class-qaf-bfl-compiler.php # Compiler
│   │   ├── class-qaf-bfl-validator.php
│   │   └── class-qaf-bfl-formatter.php
│   │
│   ├── accessibility/
│   │   ├── class-qaf-a11y-validator.php
│   │   ├── class-qaf-aria-generator.php
│   │   ├── class-qaf-focus-manager.php
│   │   └── class-qaf-announcer.php
│   │
│   ├── security/
│   │   ├── class-qaf-honeypot.php
│   │   ├── class-qaf-rate-limiter.php
│   │   ├── class-qaf-captcha.php
│   │   └── class-qaf-sanitizer.php
│   │
│   ├── integrations/
│   │   ├── class-qaf-stripe.php
│   │   ├── class-qaf-paypal.php
│   │   ├── class-qaf-google-sheets.php
│   │   ├── class-qaf-webhooks.php
│   │   └── class-qaf-gutenberg.php
│   │
│   ├── views/
│   │   ├── class-qaf-view-table.php
│   │   ├── class-qaf-view-cards.php
│   │   ├── class-qaf-view-list.php
│   │   ├── class-qaf-view-map.php
│   │   └── class-qaf-view-calendar.php
│   │
│   ├── import/
│   │   ├── class-qaf-csv-importer.php
│   │   ├── class-qaf-form-importer.php
│   │   └── class-qaf-auto-form-generator.php
│   │
│   └── api/
│       ├── class-qaf-rest-forms.php
│       ├── class-qaf-rest-entries.php
│       └── class-qaf-rest-views.php
│
├── admin/
│   ├── class-qaf-admin.php
│   ├── partials/
│   │   ├── form-builder.php
│   │   ├── structure-mode.php
│   │   ├── bfl-editor.php
│   │   ├── entry-list.php
│   │   └── settings-page.php
│   ├── css/
│   │   └── qaf-admin.css
│   └── js/
│       ├── qaf-admin.js
│       ├── qaf-form-builder.js
│       ├── qaf-structure-mode.js
│       └── qaf-bfl-editor.js
│
├── public/
│   ├── class-qaf-public.php
│   ├── partials/
│   │   ├── form-render.php
│   │   └── view-render.php
│   ├── css/
│   │   └── qaf-public.css
│   └── js/
│       ├── qaf-public.js
│       ├── qaf-validation.js
│       └── qaf-accessibility.js
│
├── templates/
│   ├── form/
│   │   ├── form-wrapper.php
│   │   ├── field-wrapper.php
│   │   ├── error-message.php
│   │   └── success-message.php
│   └── views/
│       ├── table.php
│       ├── cards.php
│       ├── list.php
│       ├── map.php
│       └── calendar.php
│
├── assets/
│   ├── images/
│   └── fonts/
│
├── languages/
│   └── quill-accessible-forms.pot
│
├── data/
│   └── choice-libraries/
│       ├── countries.json
│       ├── us-states.json
│       ├── currencies.json
│       └── ... (65 libraries)
│
└── tests/
    ├── phpunit/
    ├── jest/
    └── accessibility/
        ├── axe/
        └── manual-testing-checklist.md
```

### Database Schema

```sql
-- Forms table
CREATE TABLE {prefix}qaf_forms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    config LONGTEXT NOT NULL, -- JSON
    bfl_source LONGTEXT, -- QAF form language code
    status ENUM('draft', 'active', 'inactive') DEFAULT 'draft',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by BIGINT UNSIGNED,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY status (status)
);

-- Entries table
CREATE TABLE {prefix}qaf_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED,
    status ENUM('active', 'trash', 'spam', 'pending', 'approved', 'rejected') DEFAULT 'active',
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    source_url VARCHAR(2000),
    payment_status VARCHAR(50),
    payment_amount DECIMAL(10,2),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY form_id (form_id),
    KEY status (status),
    KEY created_at (created_at)
);

-- Entry meta table
CREATE TABLE {prefix}qaf_entry_meta (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_id BIGINT UNSIGNED NOT NULL,
    field_id VARCHAR(50) NOT NULL,
    field_value LONGTEXT,
    PRIMARY KEY (id),
    KEY entry_id (entry_id),
    KEY field_id (field_id)
);

-- Views table
CREATE TABLE {prefix}qaf_views (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    form_id BIGINT UNSIGNED NOT NULL,
    view_type ENUM('table', 'cards', 'list', 'map', 'calendar') NOT NULL,
    config LONGTEXT NOT NULL, -- JSON
    status ENUM('draft', 'active', 'inactive') DEFAULT 'draft',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
);

-- Form drafts (Save & Continue)
CREATE TABLE {prefix}qaf_drafts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id BIGINT UNSIGNED NOT NULL,
    resume_token VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED,
    email VARCHAR(255),
    data LONGTEXT NOT NULL, -- JSON
    current_page INT DEFAULT 1,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY resume_token (resume_token),
    KEY form_id (form_id),
    KEY expires_at (expires_at)
);

-- Rate limiting
CREATE TABLE {prefix}qaf_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempts INT DEFAULT 1,
    window_start DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY form_ip (form_id, ip_address),
    KEY window_start (window_start)
);
```

---

## Testing Requirements

### Automated Testing

#### Unit Tests (PHPUnit)

```php
class QAF_Field_Text_Test extends WP_UnitTestCase {
    public function test_render_includes_label() { }
    public function test_render_includes_aria_required() { }
    public function test_validation_respects_min_length() { }
    public function test_sanitization_removes_html() { }
}
```

#### JavaScript Tests (Jest)

```javascript
describe('QAF Form Builder', () => {
    test('adds field to canvas', () => { });
    test('keyboard navigation works', () => { });
    test('drag-drop has button alternative', () => { });
});
```

#### Accessibility Tests (Axe-core)

```javascript
describe('Accessibility', () => {
    test('form has no axe violations', async () => {
        const results = await axe(document.querySelector('.qaf-form'));
        expect(results.violations).toHaveLength(0);
    });
});
```

### Manual Testing Checklist

#### Screen Reader Testing

- [ ] NVDA on Windows (Firefox, Chrome)
- [ ] JAWS on Windows (Firefox, Chrome)
- [ ] VoiceOver on macOS (Safari)
- [ ] VoiceOver on iOS (Safari)
- [ ] TalkBack on Android (Chrome)

#### Keyboard Testing

- [ ] Tab navigation through all elements
- [ ] Arrow key navigation where applicable
- [ ] Enter/Space activation
- [ ] Escape for cancel/close
- [ ] No keyboard traps
- [ ] Visible focus indicators

#### Visual Testing

- [ ] 400% zoom without horizontal scroll
- [ ] High contrast mode
- [ ] Dark mode
- [ ] Reflow at 320px width

---

## Deployment & Release Strategy

### Version 1.0 Release Plan

#### Phase 1: Preview (February 2026)

- Core form builder
- 30 essential field types
- Structure Mode
- QAF form language basic support
- Basic accessibility features

#### Phase 2: Beta (March 2026)

- All 63 field types
- Full QAF form language support
- Entry views (table, list)
- Payment integrations
- Advanced accessibility testing

#### Phase 3: Release Candidate (April 2026)

- All features complete
- Performance optimization
- Security audit
- Beta tester feedback incorporated

#### Phase 4: General Availability (May 2026)

- Full documentation
- WordPress.org submission
- Community support launch

---

## Keyboard Accessibility Technical Specification

### Design Philosophy

QUILL Accessible Forms (QAF) treats keyboard navigation as a **first-class citizen**, not an accessibility afterthought. Every feature is designed keyboard-first, with mouse support added as an enhancement. This section documents the technical implementation.

### Structure Mode Implementation

Structure Mode is an industry-first keyboard-native tree view for form organization. It provides complete form building capabilities without requiring a mouse.

#### Tree Navigation Pattern

The structure tree implements the WAI-ARIA Treeview pattern with full keyboard support:

```javascript
// Navigation state management
const QAFStructureMode = {
    currentItem: null,
    
    // Roving tabindex - only current item is tabbable
    initRovingTabindex: function() {
        const items = this.tree.querySelectorAll('[role="treeitem"]');
        items.forEach((item, index) => {
            item.setAttribute('tabindex', index === 0 ? '0' : '-1');
        });
    },
    
    // Focus management
    setFocus: function(item) {
        // Remove tabindex from all items
        this.tree.querySelectorAll('[role="treeitem"]')
            .forEach(i => i.setAttribute('tabindex', '-1'));
        
        // Set focus on new item
        item.setAttribute('tabindex', '0');
        item.focus();
        this.currentItem = item;
    }
};
```

#### Complete Keyboard Shortcut Implementation

| Key | Action | Implementation |
| --- | ------ | -------------- |
| ↓ | Next visible item | Walks tree respecting expanded/collapsed state |
| ↑ | Previous visible item | Walks tree in reverse |
| → | Expand or move to first child | If expandable and collapsed: expand. If expanded: focus first child |
| ← | Collapse or move to parent | If expanded: collapse. If collapsed: focus parent |
| Home | First item | Focus first treeitem in tree |
| End | Last visible item | Focus last visible treeitem |
| Enter | Edit selected item | Opens field settings panel |
| Space | Toggle selection | Toggles aria-selected |
| Delete | Remove item | Confirms then removes; focus moves to next/previous |
| F2 | Rename item | Inline label editing |
| Ctrl+↑ | Move item up | Reorders in DOM, saves to server, announces change |
| Ctrl+↓ | Move item down | Reorders in DOM, saves to server, announces change |
| Ctrl+← | Outdent | Moves item out of parent section |
| Ctrl+→ | Indent | Moves item into previous sibling section |
| Ctrl+C | Copy | Copies item to clipboard (with dialog for sections) |
| Ctrl+X | Cut | Cuts item to clipboard |
| Ctrl+V | Paste | Pastes after current item |
| Ctrl+Shift+V | Paste as child | Pastes inside current section |
| Ctrl+D | Duplicate | Creates copy immediately after item |
| Ctrl+T | Convert Type | Opens field type conversion modal |

#### Field Type Conversion

Both Structure Mode and Visual Builder support converting fields between types while preserving compatible properties:

**Conversion Categories:**
- **Lossless** - All data preserved (e.g., text → textarea)
- **Lossy** - Some data may be lost (e.g., textarea → text truncates content)
- **Incompatible** - Conversion not supported

**Preserved Property Groups:**
- Universal: label, description, required, readonly, css_class, conditional_logic
- Validation: min, max, minlength, maxlength, pattern, step
- Choices: options, default_value, allow_other
- Text: autocomplete, spellcheck, inputmode
- DateTime: date_format, time_format, min_date, max_date
- File: accept, multiple, max_file_size, capture

**Conditional Logic Safety:**
When converting a field that is referenced by conditional logic rules, QAF:
1. Analyzes all conditional rules for compatibility
2. Shows warnings for potentially broken rules
3. Offers auto-repair when possible (e.g., updating operators)
4. Prevents conversion if critical dependencies would break

See the [Field Type Conversion Guide](user-guide/field-type-conversion-guide.md) for complete documentation.

#### Copy Section Behavior

When copying a section (fieldset, columns, page), special handling is required:

```javascript
copyItem: function(item) {
    const hasChildren = item.querySelector('[role="group"]');
    
    if (hasChildren) {
        // Show accessible dialog asking about children
        this.showCopyDialog(item, (includeChildren) => {
            this.performCopy(item, includeChildren);
        });
    } else {
        this.performCopy(item, false);
    }
}
```

**Keyboard shortcuts for explicit behavior:**

- **Ctrl+Shift+C**: Always copy with children (no dialog)
- **Ctrl+Alt+C**: Always copy without children (no dialog)

#### Screen Reader Announcements

All actions trigger ARIA live region announcements:

```javascript
// Announcement queue to prevent overlap
announceQueue: [],

announce: function(message, priority = 'polite') {
    const region = priority === 'assertive' 
        ? this.assertiveRegion 
        : this.politeRegion;
    
    // Clear region, wait, set message (required for re-announcement)
    region.textContent = '';
    setTimeout(() => {
        region.textContent = message;
    }, 100);
}

// Example announcements:
// "Email Address field, position 3 of 12"
// "Contact Information section expanded, 4 items inside"
// "Full Name moved up, now position 2 of 12"
// "Item copied. Press Ctrl+V to paste"
```

### Visual Builder Keyboard Support

The Visual Builder provides equivalent keyboard functionality:

#### Field Card Actions

Each field card includes visible action buttons that serve as drag-drop alternatives:

```html
<div class="qaf-field-card" 
     role="listitem" 
     data-field-id="name_1"
     tabindex="0">
    <span class="qaf-field-label">Full Name</span>
    <span class="qaf-field-type">(name)</span>
    
    <div class="qaf-field-actions" role="toolbar" 
         aria-label="Actions for Full Name">
        <button class="qaf-field-move-up" 
                aria-label="Move Full Name up (position 3 of 12)"
                disabled="false">↑</button>
        <button class="qaf-field-move-down" 
                aria-label="Move Full Name down (position 3 of 12)">↓</button>
        <button class="qaf-field-edit" 
                aria-label="Edit Full Name settings">Edit</button>
        <button class="qaf-field-duplicate" 
                aria-label="Duplicate Full Name">Copy</button>
        <button class="qaf-field-delete" 
                aria-label="Delete Full Name">Delete</button>
    </div>
</div>
```

**ARIA labels are dynamic**, updating to reflect current position.

#### Move Button State Management

```javascript
updateMoveButtonStates: function() {
    const cards = this.canvas.querySelectorAll('.qaf-field-card');
    const total = cards.length;
    
    cards.forEach((card, index) => {
        const moveUp = card.querySelector('.qaf-field-move-up');
        const moveDown = card.querySelector('.qaf-field-move-down');
        const label = card.querySelector('.qaf-field-label').textContent;
        
        // Disable at boundaries
        moveUp.disabled = (index === 0);
        moveDown.disabled = (index === total - 1);
        
        // Update ARIA labels with position
        moveUp.setAttribute('aria-label', 
            `Move ${label} up (position ${index + 1} of ${total})`);
        moveDown.setAttribute('aria-label', 
            `Move ${label} down (position ${index + 1} of ${total})`);
    });
}
```

### Focus Management

#### Focus Trapping in Panels

When settings panels or dialogs open, focus is trapped inside:

```javascript
class QAFFocusManager {
    trapFocus(container) {
        this.previousFocus = document.activeElement;
        
        const focusable = this.getFocusableElements(container);
        focusable[0].focus();
        
        container.addEventListener('keydown', (e) => {
            if (e.key !== 'Tab') return;
            
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    }
    
    releaseTrap() {
        // Return focus to trigger element
        if (this.previousFocus) {
            this.previousFocus.focus();
        }
    }
}
```

#### Focus Restoration After Actions

After destructive actions (delete, move), focus is managed predictably:

```javascript
deleteField: function(item) {
    const next = item.nextElementSibling;
    const prev = item.previousElementSibling;
    
    item.remove();
    
    // Focus next, or previous, or canvas
    if (next) {
        this.setFocus(next);
    } else if (prev) {
        this.setFocus(prev);
    } else {
        this.canvas.focus();
    }
    
    this.announce(`${label} deleted`);
}
```

### Property Editing

#### Field Settings Panel

All field properties can be edited via keyboard in the settings panel:

1. **Standard form controls**: All inputs, selects, checkboxes are natively keyboard accessible
2. **Tab sections**: Use Ctrl+Tab / Ctrl+Shift+Tab to switch between tabs
3. **Save/Cancel**: Ctrl+Enter saves, Escape cancels

#### Inline Editing

Field labels can be edited inline with F2:

```javascript
renameItem: function(item) {
    const label = item.querySelector('.qaf-tree-label');
    const original = label.textContent;
    
    // Replace with contenteditable
    label.contentEditable = true;
    label.focus();
    
    // Select all text
    const range = document.createRange();
    range.selectNodeContents(label);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    
    label.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.saveRename(item, label.textContent);
        } else if (e.key === 'Escape') {
            label.textContent = original;
            label.contentEditable = false;
            item.focus();
        }
    }, { once: true });
}
```

### WCAG 2.2 AA Compliance Matrix

| Success Criterion | Requirement | Implementation |
| ----------------- | ----------- | -------------- |
| 2.1.1 Keyboard | All functionality keyboard accessible | Every action has keyboard shortcut |
| 2.1.2 No Keyboard Trap | User can always navigate away | Escape always cancels, no traps |
| 2.4.3 Focus Order | Logical, meaningful sequence | Tab order follows visual order |
| 2.4.7 Focus Visible | Focus indicator always visible | Custom high-contrast focus rings |
| 2.5.7 Dragging | Alternative to dragging movements | Move Up/Down buttons for all drag operations |
| 4.1.2 Name, Role, Value | Components have accessible names | ARIA labels, roles, and states throughout |
| 4.1.3 Status Messages | Status presented without focus change | ARIA live regions for all announcements |

---

## Appendix

### A. Choice Libraries (125+ Total)

#### Geographic (35+)

1. Countries - All 250+ (ISO 3166-1)
2. Continents (7)
3. World Regions (8 geographic regions)
4. US States (50 + DC + territories)
5. Canadian Provinces (13)
6. UK Counties (~45 regions)
7. Australian States (8)
8. German States - Bundesländer (16)
9. French Regions (13)
10. Italian Regions (20)
11. Spanish Autonomous Communities (19)
12. Dutch Provinces (12)
13. Belgian Provinces (11)
14. Swiss Cantons (26)
15. Austrian States (9)
16. Mexican States (32)
17. Brazilian States (27)
18. Argentine Provinces (24)
19. Indian States (36)
20. Chinese Provinces (34)
21. Japanese Prefectures (47)
22. South Korean Provinces (17)
23. New Zealand Regions (16)
24. South African Provinces (9)
25. Irish Counties (26)
26. Polish Voivodeships (16)
27. Russian Regions (20 major)
28. Major US Cities (40)
29. Major World Cities (48)

#### Time & Date (15+)

30. Timezones (All IANA)
31. Timezones by Region (cascading)
32. US Timezones
33. Days of Week (7)
34. Months (12)
35. Years - Past 100
36. Years - Future 10
37. Years - Birth years (1920-present)
38. Time Slots - Hourly
39. Time Slots - 30 minute
40. Time Slots - 15 minute

#### Financial (10+)

41. Currencies (58 ISO 4217)
42. Major Currencies (8)
43. Cryptocurrencies (12)
44. Budget Ranges
45. Annual Revenue Ranges
46. Payment Methods (14)
47. Shipping Methods (11)

#### Languages & Contact (10+)

48. Languages (50)
49. Major Languages (12)
50. Phone Country Codes (51)
51. Contact Preferences (8)
52. Contact Times (6)
53. Notification Preferences (7)

#### Demographics (15+)

54. Age Ranges
55. Gender Options
56. Marital Status
57. Education Levels
58. Employment Status
59. Income Ranges
60. Household Sizes
61. Religions (17)
62. Ethnicities (10)
63. Political Affiliations (10)
64. Relationship Status (9)

#### Professional (15+)

65. Industries (60+)
66. Job Functions/Departments (33)
67. Experience Levels (9)
68. Years of Experience (8)
69. Company Sizes (9)
70. Fields of Study (43)
71. Lead Sources (23)
72. Project Types (15)
73. Timelines (10)
74. Service Categories (28)

#### E-commerce & Product (20+)

75. T-Shirt Sizes (9)
76. Shoe Sizes US (21)
77. Shoe Sizes EU (14)
78. Product Conditions
79. Colors
80. Quantities (1-100)
81. Subscription Frequencies (8)
82. Property Types (14)
83. Bedrooms (7)
84. Bathrooms (8)
85. Vehicle Types (17)
86. Fuel Types (9)

#### Rating & Feedback (15+)

87. 5-Star Rating
88. 10-Point Scale
89. Satisfaction Scale (5)
90. Agreement Scale (5 & 7)
91. Likelihood Scale (5)
92. Frequency Scale (5)
93. Quality Scale (5)
94. Importance Scale (5)
95. Priority Levels (5)
96. Status Options (7)
97. NPS Scale (0-10)

#### Health & Medical (10+)

98. Blood Types (8)
99. Medical Specialties (31)
100. Dietary Restrictions (15)
101. Allergens (17)
102. Meal Preferences (6)
103. Accessibility Needs (13)
104. Appointment Types (16)
105. Pet Types (13)

#### Technology (10+)

106. Platforms/OS (9)
107. Device Types (11)
108. Browsers (10)
109. Skill Levels (7)

#### Boolean & Simple (10+)

110. Yes/No
111. Yes/No/Unsure
112. True/False
113. Agree/Disagree
114. Salutations/Titles

#### Events & Support (10+)

115. Event Types (23)
116. Ticket Types (14)
117. Support Issue Types (14)

#### Units of Measurement (4)

118. Length Units (8)
119. Weight Units (7)
120. Volume Units (9)
121. Temperature Units (3)

### B. Keyboard Shortcut Reference

[Full shortcut reference available in help documentation]

### C. ARIA Reference

[Complete ARIA pattern implementations documented in developer guide]

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 27, 2026 | BITS Team | Initial PRD |

---

**End of PRD**
