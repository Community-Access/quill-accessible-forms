# QUILL Accessible Forms (QAF) Complete User Guide

Welcome to QUILL Accessible Forms (QAF), an accessible WordPress form builder. This comprehensive guide will walk you through every feature, from your first form to advanced workflows. Whether you prefer using a mouse, keyboard, or assistive technology, QAF treats every user as a first-class citizen.

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [The Three Ways to Build Forms](#the-three-ways-to-build-forms)
4. [Keyboard Navigation &amp; Accessibility](#keyboard-navigation--accessibility)
5. [Field Types Reference](#field-types-reference) (60+ field types)
6. [Predefined Choice Templates](#predefined-choice-templates)
7. [QAF form language Reference](#bfl-language-reference)
8. [Conditional Logic](#conditional-logic)
9. [Calculations &amp; Formulas](#calculations--formulas)
10. [Merge Tags](#merge-tags)
11. [Multi-Page Forms](#multi-page-forms)
12. [Save &amp; Continue](#save--continue)
13. [Nested Forms &amp; Repeaters](#nested-forms--repeaters)
14. [Entry Views](#entry-views)
15. [Entry Management](#entry-management)
16. [Notifications &amp; Confirmations](#notifications--confirmations)
17. [Payment Processing](#payment-processing)
18. [Security &amp; Spam Prevention](#security--spam-prevention)
19. [REST API](#rest-api)
20. [Hooks &amp; Filters for Developers](#hooks--filters-for-developers)
21. [Additional Feature Guides](#additional-feature-guides)
22. [Troubleshooting](#troubleshooting)

---

## Introduction

### What Makes QAF Different?

QUILL Accessible Forms (QAF) was built with one core belief: **form building should be accessible to everyone**. This isn't an afterthought—accessibility is woven into every feature, every interaction, and every line of code.

**For Users Who Prefer Keyboards**: Our Structure Mode provides a tree-view interface where you can navigate, reorder, edit, copy, and delete fields entirely with your keyboard. No mouse required, ever.

**For Screen Reader Users**: Every control has proper ARIA labels. Every action triggers a screen reader announcement. Focus management guides you naturally through workflows.

**For Visual Users**: The Visual Builder provides familiar drag-and-drop interactions with beautiful, high-contrast visuals that work in any color scheme.

**For Developers**: Write forms as code using the QAF form language (Barrier-Free Language), version control them, and automate creation.

### System Requirements

Before installing, ensure your environment meets these requirements:

| Requirement | Minimum Version |
| ----------- | --------------- |
| WordPress | 6.0 or higher |
| PHP | 8.0 or higher |
| MySQL | 5.7 or higher (or MariaDB 10.3+) |

### Browser Support

QAF works beautifully in all modern browsers:

- Chrome 90+
- Firefox 90+
- Safari 14+
- Edge 90+
- Mobile Safari (iOS)
- Chrome for Android

---

## Getting Started

### Installation

#### Method 1: WordPress Admin (Recommended)

The easiest way to install QUILL Accessible Forms (QAF):

1. Log into your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Search for "QUILL Accessible Forms" (or "QAF")
4. Click **Install Now**
5. Once installed, click **Activate**

That's it! QAF is now ready to use.

#### Method 2: Manual Upload

If you have a ZIP file:

1. Go to **Plugins → Add New**
2. Click **Upload Plugin** at the top
3. Choose your ZIP file
4. Click **Install Now**
5. Click **Activate**

#### Method 3: Composer (For Developers)

```bash
composer require quill-accessible-forms/qaf
```

### What Happens After Activation

When you activate QAF, several things happen automatically:

1. **Database tables are created** for storing forms, entries, and settings
2. **Default templates** are added to help you get started quickly
3. **Accessibility settings** are configured to optimal defaults
4. **REST API endpoints** are registered for advanced integrations

### Initial Configuration

Navigate to **QAF Forms → Settings** to configure your installation:

#### General Settings

- **Default Success Message**: What users see after submitting a form
- **Default Email Address**: Where admin notifications are sent
- **Date Format**: How dates are displayed throughout the plugin
- **Currency**: Default currency for payment fields

#### Accessibility Settings

These settings control how QAF communicates with assistive technology:

- **Screen Reader Verbosity**: Choose between "Concise" (essential announcements only) or "Verbose" (detailed announcements for every action)
- **Focus Indicator Style**: "Default" uses browser focus styles; "Enhanced" adds high-visibility focus rings
- **Announce Field Positions**: When enabled, screen readers announce "Position 3 of 7" when navigating fields
- **Reduced Motion**: Respects the user's system preference for reduced animations

#### Security Settings

- **Enable Honeypot**: Invisible spam trap (recommended)
- **Enable Rate Limiting**: Prevent submission flooding
- **reCAPTCHA**: Optional Google reCAPTCHA integration
- **hCaptcha**: Privacy-focused CAPTCHA alternative

#### Payment Settings

- **Stripe API Keys**: For credit card processing
- **PayPal Client ID**: For PayPal checkout integration
- **Test Mode**: Process payments in sandbox mode during development

---

## The Three Ways to Build Forms

QAF offers three distinct interfaces for building forms. Each is fully accessible and equally capable—choose the one that matches how you prefer to work.

### Visual Builder

The Visual Builder is the fastest way to build forms when you want to *see* the form take shape as you work.

If this is your first time opening it, the three-panel layout can feel like a lot at once. That’s normal—QAF is powerful. The easiest way to start is to **ignore the Settings panel** for a minute, add a few fields to the canvas, then come back and refine.

Think of it as three panels working together: a list of building blocks, a live preview, and a settings editor.

#### Getting There

1. In WordPress admin, go to **QAF Forms → Add New**.
2. You’ll land in the **Visual Builder** automatically.

If you opened an existing form, use the **Visual Builder** tab to return to this view.

#### What You’re Looking At (Left → Center → Right)

- **Field Palette (left)**: The “toolbox” of field types (Text, Email, Address, Section, etc.).
- **Form Canvas (center)**: The live form preview (your “draft”).
- **Field Settings (right)**: The configuration panel for the currently selected field.

A good mental model: **palette → canvas → settings**.

Example: if you’re building a contact form, you might add a **Text** field, click it on the canvas, then rename the label to “Full Name” in settings.

#### Step-by-Step: Build Your First Form

1. **Start with structure**
   - Add a **Section** for each topic (e.g. “Contact”, “Shipping”, “Payment”).
   - If your form is long, consider a **Page Break** early so users don’t scroll forever.

2. **Add your required fields first**
   - Add the core fields your form can’t work without (Name, Email, Phone, etc.).
   - In **Field Settings**, enable **Required**.

3. **Add helpful fields next**
   - Use **Description** text to explain tricky questions.
   - Use **Placeholder** text for examples (but don’t rely on placeholders as labels).

4. **Finish with a clear submit**
   - Confirm the **Submit** label is meaningful (e.g. “Request Quote” vs “Submit”).

#### Adding Fields

**With a mouse (drag-and-drop):**

1. Find the field type in the **Field Palette**.
2. Drag it onto the **Form Canvas**.
3. Drop it where you want it to appear.

**With a keyboard (no drag required):**

1. Press **Tab** until focus reaches the **Field Palette**.
2. Use **Arrow Keys** to choose a field type.
3. Press **Enter** to add it to the form.
4. After it’s added, continue with **Tab** into the field or its settings.

#### Selecting a Field (so you can edit it)

- Click the field on the canvas, **or**
- Use **Tab** to move onto the field, then press **Enter**.

When a field is selected, the **Field Settings** panel updates to match that field.

#### Reordering Fields (when things are “in the wrong place”)

**Mouse:** drag the field card on the canvas to a new position.

**Keyboard:**
- Select a field and press **Ctrl+↑** to move it up.
- Select a field and press **Ctrl+↓** to move it down.

**Move buttons (best for many users):**

Every field card includes visible **Move Up** / **Move Down** buttons. These are often faster than drag-and-drop and work well with assistive technology.

#### Editing Fields (the settings that matter most)

In **Field Settings**, you’ll commonly configure:

- **Label**: What the user sees as the question.
- **Required**: Whether the form can be submitted without it.
- **Description**: Extra instructions (great for accessibility).
- **Placeholder**: Example input (never a replacement for a label).
- **Validation**: Constraints like min/max, patterns, or unique checks.

#### Visual Builder Tips (common workflows)

**Sections vs Pages (when to use which):**
- Use a **Section** when you want visual grouping on the same screen (e.g. “Contact Info”, “Shipping”).
- Use a **Page Break** when the form feels like a long wall of questions and you want a “Next” / “Back” flow.

**Common beginner mistakes (and how to recover):**
- Added a field in the wrong spot? Use **Move Up / Move Down** on the field card (often easier than drag-and-drop).
- Confusing **Description** vs **Placeholder**? Description is visible guidance *outside* the input; placeholder is hint text *inside* the input and disappears as the user types.
- Made something required by accident? Select the field and toggle **Required** off in the settings panel.

**Preview mindset:**
- The center canvas is a live preview of what users will fill out. Your WordPress theme may affect styling, but labels, required indicators, and validation behavior remain the same.

**When to jump to Structure Mode:**
- If reordering feels fiddly or the canvas feels busy, Structure Mode is the fastest way to reorganize large forms, then return here to fine-tune settings.

**Conditional Logic tip:**
- Add conditional rules after your “controlling” fields exist (it’s easier to select them and test the behavior).

### Structure Mode (Keyboard-First Tree View)

Structure Mode is QAF’s industry-first keyboard-native form builder. It presents your form as a tree structure, similar to a file browser, and supports complete keyboard navigation.

#### Getting There

1. Open any form in the editor
2. Click the **Structure Mode** tab (or press **Alt+S**)

#### Why Structure Mode?

For users who navigate primarily with keyboards—whether by preference, necessity, or because they use assistive technology—Structure Mode provides:

- **Complete keyboard control**: Every action has a keyboard shortcut
- **Clear hierarchy**: See sections, pages, and nested fields at a glance
- **Efficient navigation**: Jump directly to any field with arrow keys
- **Predictable focus**: Focus never gets lost; you always know where you are

#### Navigation

| Key | Action |
| --- | ------ |
| ↓ (Down Arrow) | Move to next item |
| ↑ (Up Arrow) | Move to previous item |
| → (Right Arrow) | Expand section (if collapsed) or move to first child |
| ← (Left Arrow) | Collapse section (if expanded) or move to parent |
| Home | Jump to first item |
| End | Jump to last item |
| Enter | Edit selected item |
| Space | Select/deselect item |

#### Moving Items

| Key | Action |
| --- | ------ |
| Ctrl+↑ | Move item up |
| Ctrl+↓ | Move item down |
| Ctrl+← | Outdent (move out of section) |
| Ctrl+→ | Indent (move into previous section) |

#### Clipboard Operations

| Key | Action |
| --- | ------ |
| Ctrl+C | Copy selected item |
| Ctrl+X | Cut selected item |
| Ctrl+V | Paste item |
| Ctrl+D | Duplicate item |

#### Other Actions

| Key | Action |
| --- | ------ |
| Delete | Delete selected item |
| F2 | Rename item |
| Escape | Cancel current action |

#### Copying Sections with Children

When you copy a section (fieldset, columns, or page), you have the option to:

1. **Copy with children**: The section and all fields inside it are copied
2. **Copy structure only**: Just the section is copied, without its contents

A dialog appears asking which you prefer. You can also:

- Press **Ctrl+Shift+C** to always copy with children
- Press **Ctrl+Alt+C** to always copy structure only

#### Screen Reader Announcements

Every action in Structure Mode triggers a screen reader announcement:

- "Email Address field, position 3 of 12"
- "Contact Information section expanded, 4 items inside"
- "Full Name moved up, now position 2 of 12"
- "Address section collapsed"

### QAF Form Language Mode

the QAF form language (Barrier-Free Language) is QAF’s domain-specific language for defining forms as code. It's perfect for developers, for version control, and for creating forms programmatically.

#### Getting There

1. Open any form in the editor
2. Click the **the QAF form language** tab (or press **Alt+B**)

#### Why Use the QAF form language?

- **Version control**: Track form changes with Git
- **Portability**: Copy forms between sites as text
- **Automation**: Generate forms from scripts or APIs
- **Precision**: Define exact configurations without clicking around

#### Basic Syntax

QAF form language uses clean, indentation-based syntax:

```bfl
form "Contact Us"
  description: "Get in touch with our team"
  success_message: "Thank you! We'll respond within 24 hours."
  email_notifications: ["support@example.com"]

  text "Your Name"
    required
    autocomplete: "name"

  email "Your Email"
    required
    autocomplete: "email"

  textarea "Your Message"
    required
    rows: 5
    min: 20
    max: 2000

  submit "Send Message"
```

#### Validation and Formatting

The QAF form language editor includes:

- **Syntax highlighting**: Keywords, strings, and values are color-coded
- **Real-time validation**: Errors are highlighted as you type
- **Auto-formatting**: Press **Ctrl+Shift+F** to format your code
- **Autocomplete**: Press **Ctrl+Space** for suggestions

---

## Product Philosophy: What Makes QAF Different

QAF is built on a simple promise: forms should work for everyone out of the box. That means accessibility is not an add-on, and keyboard and screen-reader support are treated as first-class requirements.

At a practical level, this shows up in a few core ideas:

- **Accessibility-first defaults**: sensible labels, predictable focus order, and clear error messaging.
- **Multiple ways to build**: Visual Builder, Structure Mode, and QAF form language each produce the same form.
- **Progressive enhancement**: the form still works if a browser feature or optional integration is unavailable.

## Accessibility Compliance & Standards (WCAG 2.2 AA)

QAF is designed to meet WCAG 2.2 AA requirements by default. In addition to the keyboard patterns documented below, the product requirements include explicit WCAG coverage across perceivable, operable, understandable, and robust criteria.

Here’s the high-level checklist QAF is designed to satisfy:

- **Perceivable**: text alternatives, meaningful structure, instructions not based on shape/color, strong contrast, reflow at high zoom.
- **Operable**: fully keyboard accessible, no traps, visible focus, predictable navigation.
- **Understandable**: clear labels, consistent patterns, errors explained.
- **Robust**: semantic HTML and ARIA patterns that work across assistive technologies.

For the full criterion-by-criterion list, see `docs/PRD.md` → **Accessibility Requirements (WCAG 2.2 AA)**.

## Keyboard Navigation & Accessibility

Accessibility isn't a feature in QAF—it's the foundation. This section documents every keyboard shortcut, screen reader behavior, and accessible pattern in detail.

### Universal Keyboard Shortcuts

These shortcuts work throughout QAF:

| Shortcut | Action |
| -------- | ------ |
| Tab | Move to next focusable element |
| Shift+Tab | Move to previous focusable element |
| Enter | Activate buttons, open items |
| Space | Toggle checkboxes, select items |
| Escape | Close panels, cancel dialogs |
| Ctrl+S | Save current form |

### Form Builder Shortcuts

| Shortcut | Action |
| -------- | ------ |
| Alt+V | Switch to Visual Builder |
| Alt+S | Switch to Structure Mode |
| Alt+B | Switch to QAF Form Language Mode |
| Ctrl+Z | Undo last action |
| Ctrl+Shift+Z | Redo |
| Ctrl+F | Find field by name |

### Structure Mode Shortcuts (Complete Reference)

#### Navigation

| Shortcut | Action |
| -------- | ------ |
| ↓ | Next item (including children of expanded nodes) |
| ↑ | Previous item |
| → | If collapsed: expand. If expanded: move to first child |
| ← | If expanded: collapse. If collapsed: move to parent |
| Home | First item in tree |
| End | Last visible item in tree |
| Page Down | Move down 10 items |
| Page Up | Move up 10 items |

#### Selection

| Shortcut | Action |
| -------- | ------ |
| Space | Toggle selection on current item |
| Ctrl+A | Select all items |
| Ctrl+Click | Add to selection (mouse) |
| Shift+Click | Select range (mouse) |
| Shift+↑/↓ | Extend selection up/down |

#### Editing

| Shortcut | Action |
| -------- | ------ |
| Enter | Open field settings panel |
| F2 | Rename field (edit label inline) |
| Delete | Delete selected item(s) |
| Backspace | Delete selected item(s) |

#### Moving

| Shortcut | Action |
| -------- | ------ |
| Ctrl+↑ | Move selected item up |
| Ctrl+↓ | Move selected item down |
| Ctrl+← | Outdent (move to parent level) |
| Ctrl+→ | Indent (make child of previous item) |

#### Clipboard

| Shortcut | Action |
| -------- | ------ |
| Ctrl+C | Copy selected item(s) |
| Ctrl+X | Cut selected item(s) |
| Ctrl+V | Paste after current item |
| Ctrl+Shift+V | Paste as child of current item |
| Ctrl+D | Duplicate selected item(s) |

#### Copying Sections

| Shortcut | Action |
| -------- | ------ |
| Ctrl+C | Copy (shows dialog if section has children) |
| Ctrl+Shift+C | Copy with children (no dialog) |
| Ctrl+Alt+C | Copy without children (no dialog) |

### Field Settings Panel Shortcuts

When the settings panel is open:

| Shortcut | Action |
| -------- | ------ |
| Ctrl+Enter | Save settings |
| Escape | Cancel and close panel |
| Ctrl+Tab | Next settings tab |
| Ctrl+Shift+Tab | Previous settings tab |
| Tab | Move between form controls |

### ARIA Live Regions

QAF uses ARIA live regions to announce important changes:

- **Polite announcements** (aria-live="polite"): Used for non-urgent updates like "Field moved to position 3"
- **Assertive announcements** (aria-live="assertive"): Used for errors and urgent feedback

### Focus Management

Focus is carefully managed throughout QAF:

- **Focus trapping**: When dialogs or panels open, focus is trapped inside until closed
- **Focus restoration**: When panels close, focus returns to the trigger element
- **Focus indicators**: All focusable elements have visible focus indicators that meet WCAG 2.2 contrast requirements
- **Roving tabindex**: In lists and trees, only one item is in the tab order at a time; use arrow keys to move between items

### High Contrast Support

QAF automatically adapts to Windows High Contrast Mode:

- All text meets minimum contrast ratios
- Focus indicators use system colors
- Icons have sufficient contrast or text alternatives

### Reduced Motion

When users have "prefers-reduced-motion" enabled:

- Animations are disabled or reduced to simple fades
- Drag-and-drop shows position indicators without animation
- Field moves happen instantly

---

## Field Types Reference

QAF includes a wide set of field types. Each field is built for accessibility with proper labeling, keyboard support, and screen reader compatibility.

If you want the **fully comprehensive, beginner-first walkthrough of every field and its real settings**, use:
- `docs/user-guide/field-types-guide.md`

This section remains a lighter reference + examples, while the dedicated guide goes field-by-field.

### Text Input Fields

#### Text

Single-line text input for names, short answers, and general text.

```bfl
text "Company Name"
  required
  min: 2
  max: 100
  placeholder: "Enter company name"
  autocomplete: "organization"
```

**Attributes:**

| Attribute | Type | Description |
| --------- | ---- | ----------- |
| required | boolean | Field must be filled |
| min | number | Minimum character length |
| max | number | Maximum character length |
| pattern | string | Regex validation pattern |
| placeholder | string | Hint text (use sparingly—not a label replacement) |
| default | string | Pre-filled value |
| autocomplete | string | Browser autocomplete hint |
| prefix | string | Text shown before input (like "$") |
| suffix | string | Text shown after input (like "USD") |
| readonly | boolean | Display value but prevent editing |

#### Email

Email address input with built-in validation.

```bfl
email "Your Email"
  required
  confirm: true
  autocomplete: "email"
```

**Attributes:**

| Attribute | Type | Description |
| --------- | ---- | ----------- |
| confirm | boolean | Add confirmation field |
| blocklist | array | Disallow these domains |
| allowlist | array | Only allow these domains |
| validate_dns | boolean | Check domain has valid MX records |

#### Textarea

Multi-line text input for longer responses.

```bfl
textarea "Project Description"
  required
  rows: 5
  min: 50
  max: 2000
  character_count: true
```

**Attributes:**

| Attribute | Type | Description |
| --------- | ---- | ----------- |
| rows | number | Visible rows (default: 4) |
| min | number | Minimum characters |
| max | number | Maximum characters |
| word_count | boolean | Show word counter |
| character_count | boolean | Show character counter |
| auto_expand | boolean | Grow as user types |

#### URL

Website address input with protocol validation.

```bfl
url "Your Website"
  required
  protocols: ["https", "http"]
  placeholder: "https://example.com"
```

#### Phone

Phone number input with international format support.

```bfl
phone "Phone Number"
  required
  format: "international"
  country: "US"
  autocomplete: "tel"
```

**Formats:**

- `us`: US format (555) 123-4567
- `international`: +1 555 123 4567
- `any`: Accept any format

#### Password

Password input with strength requirements and visibility toggle.

```bfl
password "Password"
  required
  min: 8
  strength: "strong"
  confirm: true
  show_strength_meter: true
```

**Strength Levels:**

- `weak`: 6+ characters
- `medium`: 8+ characters with uppercase and number
- `strong`: 8+ characters with uppercase, number, and symbol
- `very_strong`: 12+ characters with all character types

### Number Fields

#### Number

Numeric input with range validation.

```bfl
number "Quantity"
  required
  min: 1
  max: 100
  step: 1
  default: 1
```

#### Range

Slider input for selecting values within a range.

```bfl
range "Budget"
  min: 0
  max: 10000
  step: 100
  default: 5000
  show_value: true
  prefix: "$"
```

#### Rating

Star rating input for feedback and reviews.

```bfl
rating "Product Rating"
  required
  max: 5
  precision: "half"
  icons: "stars"
```

#### Calculation

Computed field based on other field values. See [Calculations & Formulas](#calculations--formulas) for details.

```bfl
calculation "Total"
  formula: "{quantity} * {unit_price}"
  format: "currency"
  decimal_places: 2
```

### Selection Fields

#### Select (Dropdown)

Single-selection dropdown menu with optional cascading functionality.

```bfl
select "Country"
  required
  placeholder: "Choose a country"
  searchable: true
  options:
    - "United States": "US"
    - "Canada": "CA"
    - "United Kingdom": "UK"
```

**Cascading Dropdowns:**

Create dependent dropdowns where the second field's options filter based on the first field's selection:

```bfl
select "Country"
  id: country_field
  required
  options:
    - "United States": "US"
    - "Canada": "CA"

select "State/Province"
  required
  cascade_from: country_field
  cascade_mapping:
    US:
      - "California": "CA"
      - "New York": "NY"
      - "Texas": "TX"
    CA:
      - "Ontario": "ON"
      - "Quebec": "QC"
      - "British Columbia": "BC"
  empty_message: "Please select a country first"
```

**Dynamic Population with Cascading:**

Populate cascading dropdowns dynamically from WordPress data:

```bfl
select "Category"
  id: category_field
  populate:
    source: taxonomy
    config:
      taxonomy: product_cat
    value: term_id
    label: name

select "Subcategory"
  cascade_from: category_field
  populate:
    source: taxonomy
    config:
      taxonomy: product_cat
    filters:
      - field: parent
        value: "{parent}"
    value: term_id
    label: name
```

**Cascading Options:**

| Option | Type | Description |
| ------ | ---- | ----------- |
| `cascade_from` | string | ID of the parent field to depend on |
| `cascade_mapping` | object | Static mapping of parent values to child options |
| `empty_message` | string | Message when no parent is selected |
| `loading_message` | string | Message while loading options (AJAX) |
| `populate` | object | Dynamic population config (see Populate Anything) |

**Accessibility Features:**
- Child field disabled until parent has value
- `aria-busy="true"` during AJAX loading
- Screen readers announce option count after load
- Focus remains on field after options update

#### Multi-Select

Allow selecting multiple options.

**QAF form language note:** choice-field options are written as `+` lines, then modifiers (like `@required`).

```bfl
multiselect "Skills"
  + JavaScript
  + Python
  + PHP
  + Go
  + Rust

  @required
```

#### Radio

Single-selection button group.

Accessibility note: QAF renders radio groups inside a `<fieldset>` with a `<legend>` (your field label) so screen readers announce the group question correctly.

```bfl
radio "Payment Method"
  + card|Credit Card
  + paypal|PayPal
  + bank|Bank Transfer

  @required
```

#### Checkbox

Multiple-selection checkbox group.

Accessibility note: like radios, checkbox groups render as `<fieldset>` + `<legend>`.

```bfl
checkbox "Interests"
  + Sports
  + Music
  + Movies
  + Gaming
  + Reading

  @required
```

### Date & Time Fields

#### Date

Date picker with calendar popup.

```bfl
date "Birth Date"
  required
  max: "today"
  format: "YYYY-MM-DD"
  disabled_days: [0, 6]
```

**Special Values:**

- `min: "today"` - Today or later
- `max: "today"` - Today or earlier
- `min: "-30 days"` - 30 days ago or later
- `max: "+1 year"` - Within the next year

#### Time

Time picker input.

```bfl
time "Appointment Time"
  required
  format: "12h"
  min: "09:00"
  max: "17:00"
  step: 30
```

#### Date Range

Select a start and end date.

```bfl
daterange "Trip Dates"
  required
  min_days: 1
  max_days: 30
```

### File Fields

#### File Upload

File upload with type and size validation.

```bfl
fileupload "Documents"
  required
  multiple: true
  max_files: 5
  max_size: "10MB"
  accept: [".pdf", ".doc", ".docx"]
  preview: true
```

**Accept Presets:**

- `images`: .jpg, .jpeg, .png, .gif, .webp
- `documents`: .pdf, .doc, .docx, .xls, .xlsx
- `audio`: .mp3, .wav, .ogg
- `video`: .mp4, .webm, .mov

#### Signature

Digital signature capture with touch and mouse support.

```bfl
signature "Your Signature"
  required
  width: 500
  height: 200
  pen_color: "#000000"
```

### Smart Defaults

Smart defaults let you populate a field automatically when the user hasn’t entered a value.

```bfl
select "Country"
  smart_default: "geo"

date "Event Date"
  smart_default: "today+7"

text "Name"
  smart_default: "user:display_name"
```

Supported tokens:
- `geo` (resolved via the `qaf_smart_default_geo` filter)
- `today+N` (relative date)
- `user:property` (e.g. `user:display_name`, `user:user_email`)

### Unique Value Validation

Enforce unique values per-form or globally.

```bfl
email "Email"
  unique: true
  unique_scope: "form"   # form|global
  unique_message: "This email is already registered"
```

### Input Masking

Input masks guide users to enter values in a specific format.

```bfl
text "Phone"
  mask: "(999) 999-9999"

text "SSN"
  mask: "999-99-9999"
```

Mask symbols:
- `9` digit
- `A` letter
- `*` alphanumeric

### Layout Fields

#### Section

Group related fields with a heading. Sections can be collapsible for better organization of long forms.

```bfl
section "Contact Information"
  description: "How can we reach you?"
  collapsible: true
  collapsed_default: false
  heading_level: h2
  collapse_icon: chevron
  animation: smooth
  
  email "Email"
  phone "Phone"
```

**Section Options:**

| Option | Type | Default | Description |
| ------ | ---- | ------- | ----------- |
| `collapsible` | boolean | false | Enable expand/collapse functionality |
| `collapsed_default` | boolean | false | Start section collapsed |
| `heading_level` | string | "h2" | Heading level (h1-h6) |
| `collapse_icon` | string | "chevron" | Icon style: chevron, plus_minus, caret, arrow |
| `animation` | string | "smooth" | Animation: smooth, fade, instant |

**Accessibility Features:**
- Uses `<button>` element for toggle control (keyboard accessible)
- `aria-expanded` indicates current state
- `aria-controls` links button to content panel
- `Space` and `Enter` keys toggle the section
- Screen readers announce expanded/collapsed state
- `prefers-reduced-motion` respected for animations

#### Columns

Arrange fields in multiple columns.

```bfl
columns 2
  column
    text "First Name"
  column
    text "Last Name"
```

#### HTML

Insert custom HTML content.

```bfl
html "Important Notice"
  content: |
    <div class="notice">
      <strong>Note:</strong> Fields marked with * are required.
    </div>
```

### Composite Fields

#### Name

Structured name input with multiple parts.

```bfl
name "Full Name"
  required
  format: "extended"
  fields:
    prefix: true
    first: true
    middle: true
    last: true
    suffix: false
```

#### Address

Complete address input with optional autocomplete.

```bfl
address "Shipping Address"
  required
  format: "international"
  autocomplete: true
  autocomplete_provider: "google_places"
  autocomplete_countries: ["US", "CA"]
  autocomplete_types: ["address"]
  default_country: "US"
```

**Notes:**
- Address autocomplete is progressive-enhancement: if a Google Maps API key is not configured, the address field works normally.
- Configure the API key under **QAF Forms → Settings → Integrations → Google Maps API Key**.

### Special Fields

#### Consent

GDPR-compliant consent checkbox.

```bfl
consent "Privacy Agreement"
  required
  text: "I agree to the [Terms of Service](/terms) and [Privacy Policy](/privacy)."
  purpose: "marketing"
```

#### Captcha

Human verification field.

```bfl
captcha "Verification"
  type: "recaptcha_v3"
  threshold: 0.5
```

#### Hidden

Pass data without user input.

```bfl
hidden "utm_source"
  dynamic: "query_string"
  key: "utm_source"
```

### Survey & Feedback Fields

#### Likert Scale

Matrix-style scale for surveys with multiple statements rated on the same scale.

```bfl
likert "Service Feedback"
  required
  scale_type: "agreement"
  rows:
    - "Staff was friendly and helpful"
    - "Wait time was acceptable"
    - "Facility was clean"
    - "I would recommend this service"
```

**Scale Types:**

| Type | Options |
| ---- | ------- |
| `agreement` | Strongly Disagree → Strongly Agree |
| `frequency` | Never → Always |
| `satisfaction` | Very Unsatisfied → Very Satisfied |
| `importance` | Not Important → Very Important |
| `likelihood` | Very Unlikely → Very Likely |
| `custom` | Define your own columns |

**Custom Scale Example:**

```bfl
likert "Quality Assessment"
  scale_type: "custom"
  columns:
    - "1": "Poor"
    - "2": "Fair"
    - "3": "Good"
    - "4": "Excellent"
  rows:
    - "Product quality"
    - "Packaging"
    - "Delivery speed"
```

#### NPS (Net Promoter Score)

Industry-standard customer loyalty measurement (0-10 scale).

```bfl
nps "Recommendation"
  required
  label: "How likely are you to recommend us to a friend or colleague?"
  low_label: "Not at all likely"
  high_label: "Extremely likely"
  followup_enabled: true
  followup_detractor: "What could we do better?"
  followup_passive: "How can we improve?"
  followup_promoter: "What did you like most?"
```

**Score Categories:**
- **Detractors (0-6)**: Unhappy customers
- **Passives (7-8)**: Satisfied but unenthusiastic
- **Promoters (9-10)**: Loyal enthusiasts

#### Ranking

Drag-and-drop ranking with full keyboard support.

```bfl
ranking "Priority"
  required
  randomize: true
  options:
    - "Price"
    - "Quality"
    - "Speed"
    - "Service"
    - "Convenience"
```

**Keyboard Controls:**
- **Arrow keys**: Navigate items
- **Enter**: Activate move mode
- **Arrow keys (in move mode)**: Reorder item
- **Escape**: Exit move mode
- **Move Up/Down buttons**: Click-based alternative

### UI Enhancement Fields

#### Toggle

On/off switch with accessible switch role.

```bfl
toggle "Email Notifications"
  default: true
  on_label: "Enabled"
  off_label: "Disabled"
  style: "switch"
```

**Styles:**
- `switch`: iOS-style toggle switch
- `checkbox`: Traditional checkbox appearance
- `button`: Toggle button appearance

#### Color

Color picker with presets and optional alpha channel.

```bfl
color "Brand Color"
  required
  default_color: "#3498db"
  allow_alpha: true
  show_input: true
  preset_colors:
    - "#e74c3c"
    - "#3498db"
    - "#2ecc71"
    - "#f39c12"
    - "#9b59b6"
```

### Pricing & E-Commerce Fields

#### Product

Product selection with pricing for order forms.

```bfl
product "Select Plan"
  required
  display: "cards"
  options:
    - label: "Basic"
      price: 9.99
      description: "For individuals"
    - label: "Pro"
      price: 29.99
      description: "For small teams"
      featured: true
    - label: "Enterprise"
      price: 99.99
      description: "For organizations"
```

**Display Options:**
- `dropdown`: Standard select dropdown
- `radio`: Radio button list
- `cards`: Visual product cards

#### Quantity

Numeric quantity selector with increment/decrement buttons.

```bfl
quantity "Number of Licenses"
  required
  min: 1
  max: 100
  default: 1
  step: 1
```

#### Price

Formatted currency input.

```bfl
price "Custom Amount"
  required
  currency: "USD"
  min: 1
  max: 10000
  decimal_places: 2
```

#### Total

Auto-calculated order total (read-only).

```bfl
total "Order Total"
  format: "currency"
  currency: "USD"
  include_tax: true
  tax_rate: 0.08
  show_breakdown: true
```

#### Coupon

Discount code input with validation.

```bfl
coupon "Discount Code"
  placeholder: "Enter coupon code"
  valid_codes:
    - code: "SAVE10"
      type: "percent"
      value: 10
    - code: "FLAT20"
      type: "fixed"
      value: 20
```

### Post Creation Fields

These fields map to WordPress post fields when creating posts from form submissions.

#### Post Title

```bfl
post_title "Article Title"
  required
  max: 200
```

#### Post Content

```bfl
post_content "Article Body"
  required
  editor: "rich"
  media_upload: true
```

#### Post Excerpt

```bfl
post_excerpt "Summary"
  max: 300
  rows: 3
```

#### Post Category

```bfl
post_category "Category"
  required
  taxonomy: "category"
  multiple: false
  display: "dropdown"
```

#### Post Tags

```bfl
post_tags "Tags"
  taxonomy: "post_tag"
  max_tags: 10
  suggestions: true
```

#### Featured Image

```bfl
post_image "Featured Image"
  required
  max_size: "2MB"
  dimensions:
    min_width: 1200
    min_height: 630
```

#### Custom Field

```bfl
post_custom "Event Date"
  meta_key: "_event_date"
  type: "date"
```

### User Registration Fields

Create user accounts from form submissions.

#### Username

```bfl
user_login "Username"
  required
  min: 3
  max: 60
  validate_available: true
```

#### User Email

```bfl
user_email "Email Address"
  required
  validate_available: true
  send_confirmation: true
```

#### User Password

```bfl
user_password "Password"
  required
  min: 8
  strength: "strong"
  confirm: true
  show_strength_meter: true
```

### Form Navigation Fields

#### Page Break

Create multi-page forms with page breaks.

```bfl
page "Personal Information"
  # Fields for this page
  name "Full Name"
  email "Email"

page "Address"
  # Fields for next page
  address "Shipping Address"

page "Review"
  show_summary: true
```

**Page Attributes:**

| Attribute | Description |
| --------- | ----------- |
| `show_if` | Conditional page display |
| `title` | Page title (shown in progress) |
| `description` | Page description |
| `show_summary` | Show previous answers |
| `next_label` | Custom "Next" button text |
| `prev_label` | Custom "Previous" button text |

#### Submit Button

```bfl
submit "Complete Registration"
  style: "primary"
  icon: "check"
  loading_text: "Processing..."
  position: "center"
```

#### Save Progress Button

```bfl
save "Save for Later"
  style: "secondary"
  position: "left"
  confirmation: "Your progress has been saved. Check your email for a link to continue."
```

### Repeater Fields

Create repeatable field groups for dynamic data entry.

```bfl
repeater "Work Experience"
  min: 1
  max: 10
  add_label: "Add Another Job"
  remove_label: "Remove"
  
  text "Job Title"
    required
  
  text "Company"
    required
  
  daterange "Employment Period"
  
  textarea "Responsibilities"
```

---

## Predefined Choice Templates

QAF includes **125+ ready-to-use choice templates** for common form fields. These presets help you build forms faster, keep wording consistent, and reduce data-entry mistakes.

In practice, presets are most useful when:
- You want standardized lists (countries, states/provinces, timezones).
- You want consistent scales (satisfaction, agreement, NPS).
- You want to reduce admin maintenance (no hand-typing huge option lists).

> **📚 Full reference:** `docs/CHOICE-LIBRARIES.md` contains the complete catalog, mappings, and the developer API.

### Geographic Templates

This is where presets shine: you get standardized data without typing hundreds of options.

#### Countries (All 250+)

```bfl
select "Country"
  preset: "countries"
  searchable: true
```

All ISO 3166-1 countries with proper names and 2-letter codes.

#### States & Provinces (25+ Countries)

QAF includes state/province libraries for 25+ countries with automatic cascading:

| Preset | Country | Count |
|--------|---------|-------|
| `us_states` | United States | 51+ |
| `ca_provinces` | Canada | 13 |
| `uk_counties` | United Kingdom | ~45 |
| `au_states` | Australia | 8 |
| `de_states` | Germany | 16 |
| `fr_regions` | France | 13 |
| `it_regions` | Italy | 20 |
| `es_regions` | Spain | 19 |
| `nl_provinces` | Netherlands | 12 |
| `be_provinces` | Belgium | 11 |
| `ch_cantons` | Switzerland | 26 |
| `at_states` | Austria | 9 |
| `mx_states` | Mexico | 32 |
| `br_states` | Brazil | 27 |
| `ar_provinces` | Argentina | 24 |
| `in_states` | India | 36 |
| `cn_provinces` | China | 34 |
| `jp_prefectures` | Japan | 47 |
| `kr_provinces` | South Korea | 17 |
| `nz_regions` | New Zealand | 16 |
| `za_provinces` | South Africa | 9 |
| `ie_counties` | Ireland | 26 |
| `pl_voivodeships` | Poland | 16 |
| `ru_regions` | Russia | 20 |

```bfl
select "State"
  preset: "us_states"
```

#### Cascading Country → State

When you want “Country” and “State/Province” to work together, use a cascade. This gives users a shorter list and prevents mistakes (for example: you won’t show US states when the user picked Canada).

```bfl
select "Country"
  preset: "countries"
  id: country_field

select "State/Province"
  cascade_from: country_field
  preset: "states"
  empty_message: "Please select a country first"
```

The `states` preset automatically detects which state/province library to use based on the selected country.

**Built-in cascades you can use:**
- `continents` → `countries`
- `countries` → `states`
- `timezone_regions` → `timezones`

If you need a full mapping table (e.g. which country code selects which state/province preset), see `docs/CHOICE-LIBRARIES.md` → **Cascading Relationships**.

#### Continents & World Regions

```bfl
select "Continent"
  preset: "continents"
```

**Values:** Africa, Antarctica, Asia, Europe, North America, Oceania, South America

```bfl
select "Region"
  preset: "world_regions"
```

**Values:** Africa, Asia, Caribbean, Central America, Europe, Middle East, North America, Oceania, South America

#### Major Cities

```bfl
select "City"
  preset: "cities_us"
  searchable: true
```

40 major US cities including New York, Los Angeles, Chicago, Houston, Phoenix, etc.

```bfl
select "City"
  preset: "cities_world"
  searchable: true
```

48 major world cities including Tokyo, Delhi, Shanghai, São Paulo, Paris, London, etc.

### Time & Date Templates

#### Time Zones

```bfl
select "Timezone"
  preset: "timezones"
  searchable: true
```

All IANA time zones (400+). Also available filtered by region:
- `timezones_us` - US timezones only
- `timezones_europe` - European timezones
- `timezones_asia` - Asian timezones
- `timezones_america` - Americas timezones

#### Days & Months

```bfl
checkbox "Available Days"
  preset: "days"
```

**Values:** Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, Saturday

```bfl
select "Birth Month"
  preset: "months"
```

**Values:** January through December

#### Years

```bfl
select "Year"
  preset: "years_birth"
```

Birth years from 1920 to present (descending order).

Also available:
- `years_past` - Past 100 years
- `years_future` - Next 10 years

#### Time Slots

```bfl
select "Appointment Time"
  preset: "time_slots_hourly"
```

Hourly time slots (12:00 AM through 11:00 PM).

Also available:
- `time_slots_30min` - 30-minute intervals (48 options)
- `time_slots_15min` - 15-minute intervals (96 options)

### Financial Templates

Financial presets are useful when you want consistent reporting across submissions (so “USD” doesn’t become “US Dollars”, “$”, and “United States Dollar” in different entries).

#### Currencies

```bfl
select "Currency"
  preset: "currencies"
```

58 world currencies (ISO 4217) including USD, EUR, GBP, JPY, etc.

```bfl
select "Currency"
  preset: "currencies_major"
```

8 major currencies: USD, EUR, GBP, JPY, AUD, CAD, CHF, CNY

```bfl
select "Cryptocurrency"
  preset: "cryptocurrencies"
```

12 popular cryptocurrencies: Bitcoin, Ethereum, Tether, etc.

#### Budget Ranges

```bfl
select "Budget"
  preset: "budget_ranges"
```

**Values:** Under $1,000, $1,000 - $5,000, ... Over $1,000,000

#### Payment Methods

```bfl
checkbox "Payment Methods"
  preset: "payment_methods"
```

**Values:** Credit card, Debit card, PayPal, Apple Pay, Google Pay, Venmo, Bank transfer, Wire transfer, Check, Cash, Cryptocurrency, Invoice/Net terms, Financing, Other

### Language Templates

Language and contact presets are helpful for user profiles and intake forms. They also reduce spelling inconsistencies (important when you export data).

#### Languages

```bfl
select "Language"
  preset: "languages"
```

50 world languages with ISO 639-1 codes.

```bfl
select "Language"
  preset: "languages_major"
```

12 major languages: English, Spanish, French, German, Chinese, Japanese, Portuguese, Russian, Arabic, Hindi, Italian, Korean

#### Phone Country Codes

```bfl
select "Country Code"
  preset: "phone_codes"
```

51 international dialing codes: +1 (US/Canada), +44 (UK), +49 (Germany), etc.

### Personal Information Templates

#### Age Ranges

```bfl
select "Age Group"
  preset: "age_ranges"
```

**Values:** Under 18, 18-24, 25-34, 35-44, 45-54, 55-64, 65-74, 75+, Prefer not to say

#### Gender Options

```bfl
select "Gender"
  preset: "gender"
```

**Values:** Female, Male, Non-binary, Genderqueer, Prefer to self-describe, Prefer not to say

#### Marital Status

```bfl
select "Marital Status"
  preset: "marital_status"
```

**Values:** Single, Married, Domestic Partnership, Divorced, Separated, Widowed, Other, Prefer not to say

#### Relationship Status

```bfl
select "Relationship Status"
  preset: "relationship_statuses"
```

**Values:** Single, In a relationship, Engaged, Married, Domestic partnership, Separated, Divorced, Widowed, Prefer not to say

#### Education Level

```bfl
select "Education"
  preset: "education_levels"
```

**Values:** Some high school, High school diploma/GED, Some college, Associate degree, Bachelor's degree, Master's degree, Professional degree (JD, MD), Doctorate degree, Trade/Vocational certificate, Prefer not to say

#### Income Ranges

```bfl
select "Household Income"
  preset: "income_ranges"
```

**Values:** Under $25,000, $25,000 - $49,999, ... $300,000+, Prefer not to say

#### Household Size

```bfl
select "Household Size"
  preset: "household_sizes"
```

**Values:** 1 person, 2 people, 3 people, 4 people, 5 people, 6 people, 7+ people, Prefer not to say

### Employment Templates

#### Employment Status

```bfl
select "Employment Status"
  preset: "employment_status"
```

**Values:** Employed full-time, Employed part-time, Self-employed, Freelancer/Contractor, Unemployed - seeking, Unemployed - not seeking, Student, Retired, Homemaker, Unable to work, Prefer not to say

#### Industries (60+)

```bfl
select "Industry"
  preset: "industries"
  searchable: true
```

60+ industry sectors including Accounting & Finance, Advertising & Marketing, Aerospace & Defense, Agriculture, Architecture, Automotive, Banking, Biotechnology, Construction, Consulting, Education, Healthcare, Information Technology, Legal, Manufacturing, Real Estate, Retail, Software & SaaS, and many more.

#### Job Functions

```bfl
select "Department"
  preset: "job_functions"
```

33 job functions/departments including Accounting/Finance, Administrative, Customer Service, Engineering, Executive/Management, Human Resources, Information Technology, Marketing, Operations, Product Management, Sales, etc.

#### Experience Levels

```bfl
select "Experience Level"
  preset: "experience_levels"
```

**Values:** Entry level, Junior, Mid-level, Senior, Lead, Manager, Director, VP, C-Level

#### Years of Experience

```bfl
select "Experience"
  preset: "years_experience"
```

**Values:** No experience, Less than 1 year, 1-2 years, 3-5 years, 6-10 years, 11-15 years, 16-20 years, More than 20 years

#### Company Size

```bfl
select "Company Size"
  preset: "company_sizes"
```

**Values:** Self-employed, 2-10 employees, 11-50 employees, 51-200 employees, 201-500 employees, 501-1,000 employees, 1,001-5,000 employees, 5,001-10,000 employees, 10,001+ employees

#### Fields of Study

```bfl
select "Major"
  preset: "fields_of_study"
  searchable: true
```

43 academic majors including Accounting, Biology, Business Administration, Computer Science, Economics, Engineering, Finance, Law, Marketing, Medicine, Psychology, etc.

### Lead Generation Templates

#### Lead Sources

```bfl
select "How did you hear about us?"
  preset: "lead_sources"
```

**Values:** Google Search, Bing Search, Facebook, Instagram, Twitter/X, LinkedIn, YouTube, TikTok, Pinterest, Reddit, Referral/Word of mouth, Email marketing, Blog/Article, Podcast, Webinar, Conference/Event, Print advertisement, TV/Radio, Billboard/Outdoor, Direct mail, Cold call, Partner/Affiliate, Other

#### Project Timelines

```bfl
select "Timeline"
  preset: "timelines"
```

**Values:** As soon as possible, Within 1 week, Within 2 weeks, Within 1 month, 2-3 months, 3-6 months, 6-12 months, More than 1 year, Flexible, Not sure yet

#### Service Categories

```bfl
select "Service Needed"
  preset: "service_categories"
```

29 service categories including Automotive, Beauty & Spa, Cleaning, Consulting, Education, Financial, Healthcare, Home Improvement, Legal, Marketing, Real Estate, Technology, etc.

### Agreement & Opinion Templates

These presets are ideal for surveys and feedback forms. They’re intentionally standardized so results are easy to analyze and compare over time.

#### Yes/No Options

```bfl
radio "Agree to Terms"
  preset: "yes_no"
```

**Values:** Yes, No

```bfl
radio "Interested?"
  preset: "yes_no_unsure"
```

**Values:** Yes, No, Not sure

#### Agreement Scales

```bfl
radio "Agreement"
  preset: "agreement"
```

**5-Point:** Strongly Disagree, Disagree, Neutral, Agree, Strongly Agree

```bfl
radio "Agreement"
  preset: "agreement_7"
```

**7-Point:** Strongly Disagree, Disagree, Somewhat Disagree, Neutral, Somewhat Agree, Agree, Strongly Agree

#### Satisfaction Scale

```bfl
radio "Satisfaction"
  preset: "satisfaction"
```

**Values:** Very Unsatisfied, Unsatisfied, Neutral, Satisfied, Very Satisfied

#### Frequency Scale

```bfl
radio "How often?"
  preset: "frequency"
```

**Values:** Never, Rarely, Sometimes, Often, Always

#### Likelihood Scale

```bfl
radio "Likelihood"
  preset: "likelihood"
```

**Values:** Very Unlikely, Unlikely, Neutral, Likely, Very Likely

#### Quality Scale

```bfl
radio "Quality"
  preset: "quality"
```

**Values:** Very Poor, Poor, Average, Good, Excellent

#### Importance Scale

```bfl
radio "Importance"
  preset: "importance"
```

**Values:** Not Important, Slightly Important, Moderately Important, Important, Very Important

#### Priority Levels

```bfl
select "Priority"
  preset: "priority_levels"
```

**Values:** Low, Medium, High, Urgent, Critical

### Product & Size Templates

#### T-Shirt Sizes

```bfl
select "Size"
  preset: "tshirt_sizes"
```

**Values:** XS, S, M, L, XL, 2XL, 3XL, 4XL, 5XL

#### Shoe Sizes

```bfl
select "Shoe Size (US)"
  preset: "shoe_sizes_us"
```

**US Values:** 4, 4.5, 5, 5.5, 6, 6.5, 7, 7.5, 8, 8.5, 9, 9.5, 10, 10.5, 11, 11.5, 12, 12.5, 13, 14, 15

```bfl
select "Shoe Size (EU)"
  preset: "shoe_sizes_eu"
```

**EU Values:** 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48

#### Product Conditions

```bfl
radio "Condition"
  preset: "product_conditions"
```

**Values:** New, Like New, Very Good, Good, Acceptable, For Parts

#### Quantities

```bfl
select "Quantity"
  preset: "quantities"
```

Numbers 1-100 for quantity selection.

### Contact & Communication Templates

#### Contact Preferences

```bfl
checkbox "Preferred Contact Method"
  preset: "contact_preferences"
```

**Values:** Email, Phone call, Text message (SMS), WhatsApp, Video call, Postal mail, In person, No preference

#### Contact Times

```bfl
checkbox "Best Time to Contact"
  preset: "contact_times"
```

**Values:** Morning (8am - 12pm), Afternoon (12pm - 5pm), Evening (5pm - 8pm), Weekdays only, Weekends only, Anytime

#### Notification Preferences

```bfl
checkbox "Notifications"
  preset: "notification_preferences"
```

**Values:** All notifications, Important only, Marketing and promotions, Product updates, Newsletter, Account alerts only, No notifications

### Health & Medical Templates

Use these when you need structured healthcare or dietary information. If a question is sensitive, consider adding a short description explaining why you’re asking and how the data will be used.

#### Blood Types

```bfl
select "Blood Type"
  preset: "blood_types"
```

**Values:** A+, A-, B+, B-, AB+, AB-, O+, O-, Unknown

#### Medical Specialties

```bfl
select "Specialty"
  preset: "medical_specialties"
  searchable: true
```

31 medical specialties including Cardiology, Dermatology, Emergency Medicine, Family Medicine, Internal Medicine, Neurology, Oncology, Orthopedics, Pediatrics, Psychiatry, Surgery, etc.

#### Dietary Restrictions

```bfl
checkbox "Dietary Restrictions"
  preset: "dietary_restrictions"
```

**Values:** None, Vegetarian, Vegan, Pescatarian, Gluten-free, Dairy-free, Nut-free, Kosher, Halal, Keto, Paleo, Low sodium, Low sugar/Diabetic, Raw food, Other

#### Allergens

```bfl
checkbox "Allergies"
  preset: "allergens"
```

**Values:** None, Peanuts, Tree nuts, Milk/Dairy, Eggs, Wheat, Soy, Fish, Shellfish, Sesame, Gluten, Sulfites, Mustard, Celery, Lupin, Mollusks, Other

#### Accessibility Needs

```bfl
checkbox "Accessibility Requirements"
  preset: "accessibility_needs"
```

**Values:** None, Wheelchair access, Mobility assistance, Visual impairment, Hearing impairment, Sign language interpreter, Service animal, Large print materials, Braille materials, Captioning/Subtitles, Audio description, Quiet/Low-stimulation space, Other

#### Appointment Types

```bfl
select "Appointment Type"
  preset: "appointment_types"
```

**Values:** Consultation, Follow-up, Initial Visit, Check-up, Emergency, Procedure, Screening, Vaccination, Lab Work, Imaging/X-Ray, Telehealth/Virtual, Therapy Session, Dental, Eye Exam, Physical Exam, Other

### Technology Templates

#### Platforms

```bfl
checkbox "Supported Platforms"
  preset: "platforms"
```

**Values:** Windows, macOS, Linux, iOS, Android, Web/Browser, ChromeOS, Cross-platform, Other

#### Device Types

```bfl
checkbox "Device"
  preset: "device_types"
```

**Values:** Desktop computer, Laptop, Tablet, Smartphone, Smartwatch, Smart TV, Gaming console, Smart speaker, E-reader, VR headset, Other

#### Browsers

```bfl
checkbox "Supported Browsers"
  preset: "browsers"
```

**Values:** Google Chrome, Mozilla Firefox, Safari, Microsoft Edge, Opera, Brave, Vivaldi, Samsung Internet, Internet Explorer, Other

#### Skill Levels

```bfl
select "Skill Level"
  preset: "skill_levels"
```

**Values:** Beginner, Elementary, Intermediate, Upper Intermediate, Advanced, Expert, Native/Fluent

### Real Estate Templates

#### Property Types

```bfl
select "Property Type"
  preset: "property_types"
```

**Values:** House, Apartment, Condo, Townhouse, Duplex, Multi-family, Mobile/Manufactured home, Land/Lot, Commercial, Industrial, Office, Retail, Farm/Ranch, Other

#### Bedrooms & Bathrooms

```bfl
select "Bedrooms"
  preset: "bedrooms"
```

**Values:** Studio, 1 bedroom, 2 bedrooms, 3 bedrooms, 4 bedrooms, 5 bedrooms, 6+ bedrooms

```bfl
select "Bathrooms"
  preset: "bathrooms"
```

**Values:** 1, 1.5, 2, 2.5, 3, 3.5, 4, 4+

### Vehicle Templates

#### Vehicle Types

```bfl
select "Vehicle Type"
  preset: "vehicle_types"
```

**Values:** Sedan, SUV, Crossover, Truck, Van/Minivan, Coupe, Convertible, Hatchback, Station wagon, Sports car, Luxury, Electric, Hybrid, Motorcycle, RV/Camper, Boat, Other

#### Fuel Types

```bfl
select "Fuel Type"
  preset: "fuel_types"
```

**Values:** Gasoline, Diesel, Electric, Hybrid, Plug-in hybrid, Flex fuel, Hydrogen, Natural gas, Propane

### Event Templates

#### Event Types

```bfl
select "Event Type"
  preset: "event_types"
```

**Values:** Conference, Seminar, Workshop, Webinar, Meeting, Networking event, Training, Presentation, Product launch, Trade show, Exhibition, Fundraiser, Gala, Concert, Festival, Party/Celebration, Wedding, Corporate event, Sports event, Community event, Virtual event, Hybrid event, Other

#### Ticket Types

```bfl
select "Ticket Type"
  preset: "ticket_types"
```

**Values:** Free, General admission, VIP, Early bird, Student, Senior, Military, Group, Member, Press/Media, Sponsor, Exhibitor, Speaker, Other

### Support Templates

#### Issue Types

```bfl
select "Issue Type"
  preset: "support_issue_types"
```

**Values:** Bug/Error, Feature request, Question, Account issue, Billing/Payment, Order issue, Shipping/Delivery, Return/Refund, Technical support, Feedback, Complaint, Partnership inquiry, Press/Media inquiry, Other

#### Status Options

```bfl
select "Status"
  preset: "statuses"
```

**Values:** Pending, Open, In Progress, On Hold, Resolved, Closed, Cancelled

### Units of Measurement

```bfl
select "Length Unit"
  preset: "units_length"
```

**Values:** mm, cm, m, km, in, ft, yd, mi

```bfl
select "Weight Unit"
  preset: "units_weight"
```

**Values:** mg, g, kg, oz, lb, st, ton

```bfl
select "Temperature"
  preset: "units_temperature"
```

**Values:** Celsius (°C), Fahrenheit (°F), Kelvin (K)

### Using Custom Values with Presets

Override or extend preset values:

```bfl
select "Country"
  preset: "countries"
  prepend:
    - "": "Select a country..."
  append:
    - "OTHER": "Other (specify below)"
  remove:
    - "KP"  # Remove specific option
```

### Creating Conditional Cascading Dropdowns

```bfl
select "Country"
  preset: "countries"
  id: country_field

select "State/Province"
  cascade_from: country_field
  preset: "states"
  empty_message: "Please select a country first"
```

---

## Custom Libraries & Advanced Integration (Choice Libraries)

Most teams can rely on the built-in choice templates. When you can’t (for example: departments, locations, user roles, product catalogs), you can register your own libraries in PHP.

QAF supports:
- **Static libraries** (a fixed list of choices)
- **Callback libraries** (choices generated dynamically)
- **Custom cascades** (parent choice filters child choice)

For the complete PHP examples and API surface, see `docs/CHOICE-LIBRARIES.md` → **Custom Libraries** and **API Reference**.

## QAF Form Language Guide (Beginner)

If you’re new to building forms, start here:

- `docs/user-guide/qaf-form-language-guide.md`

That guide is written as an “instructor in your pocket” tutorial and includes:
- your first copy/paste form
- indentation rules and common mistakes
- field IDs and how conditional logic finds fields
- choice fields (select/radio/checkbox)
- accessibility semantics like **fieldset/legend** for grouped options

---

## Conditional Logic

Show, hide, require, or validate fields based on other field values.

### Basic Conditions

```bfl
select "Has Car"
  options: ["Yes", "No"]

text "Car Make"
  show_if: "has_car" = "Yes"
  required_if: "has_car" = "Yes"
```

### Operators

| Operator | Description | Example |
| -------- | ----------- | ------- |
| `=` | Equals | `"field" = "value"` |
| `!=` | Not equals | `"field" != "value"` |
| `>` | Greater than | `"age" > 18` |
| `>=` | Greater than or equal | `"quantity" >= 1` |
| `<` | Less than | `"price" < 100` |
| `<=` | Less than or equal | `"count" <= 10` |
| `contains` | Contains substring | `"name" contains "Jr"` |
| `starts_with` | Starts with | `"code" starts_with "ABC"` |
| `ends_with` | Ends with | `"email" ends_with ".edu"` |
| `is_empty` | Has no value | `"field" is_empty` |
| `is_not_empty` | Has any value | `"field" is_not_empty` |
| `in` | In list | `"country" in ["US", "CA"]` |
| `not_in` | Not in list | `"status" not_in ["banned"]` |

### Combining Conditions

```bfl
# AND - both must be true
text "Notes"
  show_if: "category" = "Other" AND "priority" = "High"

# OR - either can be true
email "Work Email"
  show_if: "type" = "Business" OR "type" = "Enterprise"

# Parentheses for grouping
textarea "Details"
  show_if: ("urgent" = true AND "type" = "Support") OR "vip" = true
```

### Conditional Pages

Show or skip entire pages based on conditions:

```bfl
form "Survey"
  page "Basic Info"
    select "Employment"
      options: ["Employed", "Self-Employed", "Not Working"]
    
  page "Employment Details"
    show_if: "employment" = "Employed" OR "employment" = "Self-Employed"
    
    text "Employer"
    text "Job Title"
    
  page "Contact"
    # This page shows for everyone
```

---

## Calculations & Formulas

Create dynamic calculated fields for totals, scores, pricing, and more.

### Basic Math

```bfl
number "Quantity"
  min: 1

number "Unit Price"
  min: 0

calculation "Total"
  formula: "{quantity} * {unit_price}"
```

### Operators

| Operator | Description |
| -------- | ----------- |
| `+` | Addition |
| `-` | Subtraction |
| `*` | Multiplication |
| `/` | Division |
| `%` | Modulo (remainder) |
| `^` | Exponentiation |

### Functions

| Function | Description | Example |
| -------- | ----------- | ------- |
| `SUM(...)` | Sum values | `SUM({a}, {b}, {c})` |
| `AVG(...)` | Average | `AVG({scores})` |
| `MIN(...)` | Minimum | `MIN({bid1}, {bid2})` |
| `MAX(...)` | Maximum | `MAX({bid1}, {bid2})` |
| `COUNT(...)` | Count items | `COUNT({items})` |
| `ROUND(x, d)` | Round to d decimals | `ROUND({total}, 2)` |
| `FLOOR(x)` | Round down | `FLOOR({price})` |
| `CEIL(x)` | Round up | `CEIL({price})` |
| `ABS(x)` | Absolute value | `ABS({difference})` |

### Conditional Calculations

```bfl
calculation "Shipping"
  formula: |
    IF({subtotal} >= 100,
      0,
      IF({shipping_method} = "Express",
        15.99,
        5.99
      )
    )
```

### Date Calculations

```bfl
calculation "Age"
  formula: "YEARS_BETWEEN({birth_date}, TODAY())"

calculation "Days Until Event"
  formula: "DAYS_BETWEEN(TODAY(), {event_date})"
```

| Function | Description |
| -------- | ----------- |
| `TODAY()` | Current date |
| `NOW()` | Current date and time |
| `YEARS_BETWEEN(d1, d2)` | Years between two dates |
| `MONTHS_BETWEEN(d1, d2)` | Months between two dates |
| `DAYS_BETWEEN(d1, d2)` | Days between two dates |
| `ADD_DAYS(date, n)` | Add n days to date |

### Pricing Example

```bfl
form "Order"
  repeater "Items"
    text "Product"
    number "Quantity"
      min: 1
    number "Price"
      min: 0
    calculation "Line Total"
      formula: "{quantity} * {price}"
  
  calculation "Subtotal"
    formula: "SUM({items:line_total})"
  
  calculation "Tax"
    formula: "{subtotal} * 0.08"
  
  calculation "Total"
    formula: "{subtotal} + {tax}"
    format: "currency"
    prefix: "$"
```

### Quiz Scoring

```bfl
form "Quiz"
  radio "Q1: What is 2+2?"
    options:
      - "3": 0
      - "4": 1
      - "5": 0
      
  radio "Q2: Capital of France?"
    options:
      - "London": 0
      - "Paris": 1
      - "Berlin": 0
  
  calculation "Score"
    formula: "{q1} + {q2}"
  
  calculation "Percentage"
    formula: "ROUND(({score} / 2) * 100, 0)"
    suffix: "%"
  
  calculation "Grade"
    formula: |
      IF({percentage} >= 70, "Pass", "Fail")
```

---

## Merge Tags

Merge tags are placeholders that get replaced with actual values. Use them in notifications, confirmations, calculations, and more.

### Syntax

```text
{tag_name}
{tag_name:modifier}
{category:tag_name}
```

### Field Values

Reference any field by its ID:

```text
{field:first_name}     → "John"
{field:email}          → "john@example.com"
{field:total}          → "99.00"
```

### Entry Information

```text
{entry:id}             → "1234"
{entry:date}           → "January 15, 2025"
{entry:time}           → "2:30 PM"
{entry:ip}             → "192.168.1.1"
{entry:source_url}     → "https://example.com/contact"
```

### User Information

```text
{user:id}              → "5"
{user:email}           → "user@example.com"
{user:display_name}    → "John Doe"
{user:first_name}      → "John"
{user:last_name}       → "Doe"
```

### Site Information

```text
{site:name}            → "My WordPress Site"
{site:url}             → "https://example.com"
{site:admin_email}     → "admin@example.com"
{site:date}            → "January 15, 2025"
```

### Modifiers

```text
{field:total:currency}         → "$99.00"
{field:date:format:M j, Y}     → "Jan 15, 2025"
{field:name:uppercase}         → "JOHN DOE"
{field:name:lowercase}         → "john doe"
{field:text:truncate:50}       → "Lorem ipsum dolor sit..."
{field:email:urlencode}        → "john%40example.com"
{field:company:default:N/A}    → "N/A" (if empty)
```

### Conditional Tags

```text
{if field:country = "US"}
  Free shipping!
{else}
  International shipping rates apply.
{/if}
```

### All Fields Summary

Display all submitted values:

```text
{all_fields}                      → Full table of all fields
{all_fields:exclude:hidden,ip}    → Exclude certain fields
{all_fields:format:list}          → Simple list format
```

### Using in Notifications

```bfl
form "Order"
  confirmation_email: true
  confirmation_subject: "Order #{entry:id} Confirmation"
  confirmation_message: |
    Hello {field:name},
    
    Thank you for your order!
    
    Order Details:
    {all_fields}
    
    Total: {field:total:currency}
```

---

## Multi-Page Forms

Break long forms into manageable pages with progress indicators.

### Basic Multi-Page Form

```bfl
form "Application"
  progress_bar: true
  
  page "Personal Information"
    name "Full Name"
      required
    email "Email"
      required
    phone "Phone"
  
  page "Experience"
    textarea "Work History"
    fileupload "Resume"
      accept: [".pdf", ".doc", ".docx"]
  
  page "Review"
    html "Review"
      content: "Please review your information before submitting."
  
  submit "Submit Application"
```

### Progress Options

```bfl
form "Registration"
  progress_bar: true
  progress_type: "steps"
  navigation: "buttons"
  validate_on_next: true
```

**Progress Types:**

- `bar` - Progress bar filling left to right
- `steps` - Step indicators (Step 1, Step 2, etc.)
- `percentage` - Percentage complete
- `none` - No progress indicator

**Navigation:**

- `buttons` - Next/Previous buttons
- `tabs` - Clickable page tabs
- `both` - Both buttons and tabs

### Page Attributes

```bfl
page "Optional Section"
  title: "Step 2: Additional Info"
  description: "This section is optional"
  show_if: "wants_details" = "Yes"
  next_button: "Continue"
  prev_button: "Go Back"
```

---

## Save & Continue

Let users save their progress and return later to complete the form.

### Enabling Save & Continue

```bfl
form "Long Application"
  save_continue: true
  save_auto: true
  save_interval: 30
  resume_method: "email"
  expiration: 30
```

### Configuration Options

| Option | Description | Default |
| ------ | ----------- | ------- |
| save_continue | Enable the feature | false |
| save_auto | Auto-save periodically | true |
| save_interval | Seconds between auto-saves | 30 |
| resume_method | How to send link: email, token, both | email |
| expiration | Days until draft expires | 30 |
| multiple_drafts | Allow multiple drafts per user | true |

### How It Works

**Saving:**

1. User clicks "Save Progress" (or auto-save triggers)
2. All entered data is stored securely
3. A unique, secure token is generated
4. User receives an email with their resume link

**Resuming:**

1. User clicks the resume link
2. Their saved data is restored
3. They continue where they left off
4. On multi-page forms, they return to their last page

### Email Customization

```php
add_filter( 'qaf_save_continue_email', function( $email, $draft, $form ) {
    $email['subject'] = 'Continue your ' . $form->title;
    $email['message'] = sprintf(
        'You have a saved draft. Click here to continue: %s',
        $draft->resume_url
    );
    return $email;
}, 10, 3 );
```

---

## Nested Forms & Repeaters

Collect multiple sets of related data, like line items, attendees, or work history.

### Basic Repeater

```bfl
form "Order"
  nested "Items"
    min_rows: 1
    max_rows: 20
    add_label: "Add Item"
    
    text "Product"
      required
    number "Quantity"
      min: 1
    number "Price"
      min: 0
```

### Configuration

| Option | Description | Default |
| ------ | ----------- | ------- |
| min_rows | Minimum entries required | 0 |
| max_rows | Maximum entries allowed | 10 |
| default_rows | Initial rows shown | 1 |
| add_label | Add button text | "Add Row" |
| remove_label | Remove button text | "Remove" |
| layout | table, cards, or modal | table |
| sortable | Allow reordering rows | true |
| collapsible | Collapse rows to save space | false |

### Layouts

**Table Layout** - Best for forms with few fields per row:

```bfl
nested "Line Items"
  layout: "table"
```

**Cards Layout** - Better for complex entries:

```bfl
nested "Attendees"
  layout: "cards"
  collapsible: true
  row_label: "Attendee {n}"
```

**Modal Layout** - Opens each entry in a dialog:

```bfl
nested "Properties"
  layout: "modal"
  modal_title: "Property Details"
```

### Aggregating Values

Calculate totals across repeater rows:

```bfl
calculation "Subtotal"
  formula: "SUM({items:line_total})"

calculation "Item Count"
  formula: "COUNT({items})"

calculation "Average Price"
  formula: "AVG({items:price})"
```

### Keyboard Navigation in Repeaters

| Key | Action |
| --- | ------ |
| Tab | Move between fields within row |
| Enter | Add new row (from last field) |
| Delete | Remove current row (with confirmation) |
| Ctrl+↑/↓ | Reorder rows |

---

## Entry Views

Display form entries on the frontend of your site using Views.

### Creating a View

```bfl
view "Member Directory"
  form: "member_registration"
  type: "cards"
  
  fields:
    - "name"
    - "photo"
    - "company"
  
  search: true
  filters:
    - "location"
    - "industry"
  
  pagination: 12
```

### View Types

#### Table View

Data-dense display for large datasets:

```bfl
view "Orders"
  type: "table"
  columns:
    - field: "order_number"
      sortable: true
    - field: "customer"
      searchable: true
    - field: "total"
      format: "currency"
    - field: "status"
      filterable: true
```

#### Cards View

Visual grid layout:

```bfl
view "Team"
  type: "cards"
  columns: 3
  
  card:
    image: "photo"
    title: "name"
    subtitle: "position"
```

#### List View

Vertical list with custom templates:

```bfl
view "Jobs"
  type: "list"
  
  template: |
    <article class="job">
      <h2>{title}</h2>
      <p>{company} • {location}</p>
    </article>
```

#### Map View

Show entries on an interactive map:

```bfl
view "Locations"
  type: "map"
  
  marker:
    lat: "latitude"
    lng: "longitude"
    title: "name"
```

#### Calendar View

Display date-based entries:

```bfl
view "Events"
  type: "calendar"
  
  date_field: "event_date"
  title_field: "event_name"
```

### Embedding Views

**Shortcode:**

```text
[jexi_view id="123"]
[jexi_view id="123" filter_category="news" limit="5"]
```

**Gutenberg Block:**

Add the "QAF View" block and select your view.

**PHP:**

```php
<?php jexi_view( 123, ['limit' => 5] ); ?>
```

---

## Entry Management

Tools for managing submissions including approval workflows, locking, and magic links.

### Entry Approval

Control which entries are visible using approval states.

```bfl
form "Submissions"
  require_approval: true
  initial_status: "pending"
  
  notifications:
    on_approval:
      to: "{field:email}"
      subject: "Your submission has been approved!"
```

**Statuses:**

- `pending` - Awaiting review
- `approved` - Visible on frontend
- `rejected` - Not visible

### Entry Locking

Prevent conflicts when multiple users edit the same entry:

```bfl
form "Shared Document"
  entry_locking: true
  lock_timeout: 30
```

When someone is editing, others see: "This entry is being edited by John Doe"

### Magic Links

Generate secure URLs for viewing or editing entries without login:

```bfl
form "Orders"
  magic_links:
    enabled: true
    expires: "7 days"
```

In notifications:

```text
View your order: {entry:magic_link:view}
Edit your order: {entry:magic_link:edit}
```

### Submission Limits

Control how many entries can be submitted:

```bfl
form "Contest"
  limits:
    total_entries: 1000
    per_user: 1
    per_email: 1
    limit_message: "Maximum entries reached."
```

### Scheduling

Open and close forms on a schedule:

```bfl
form "Registration"
  schedule:
    start: "2025-01-15 09:00"
    end: "2025-02-28 23:59"
    timezone: "America/New_York"
    closed_message: "Registration is closed."
```

---

## Notifications & Confirmations

Send emails when forms are submitted.

### Admin Notifications

```bfl
form "Contact"
  email_notifications: ["admin@example.com", "sales@example.com"]
  email_subject: "New Contact: {field:subject}"
  email_template: |
    <h2>New Contact Form Submission</h2>
    
    <p><strong>Name:</strong> {field:name}</p>
    <p><strong>Email:</strong> {field:email}</p>
    <p><strong>Message:</strong></p>
    {field:message}
```

### User Confirmations

```bfl
form "Registration"
  confirmation_email: true
  confirmation_to: "{field:email}"
  confirmation_subject: "Welcome, {field:name}!"
  confirmation_template: |
    <p>Thank you for registering!</p>
    <p>Your account details:</p>
    {all_fields}
```

### Conditional Routing

Route notifications based on form values:

```bfl
form "Support"
  routing:
    - if: "{field:department}" = "Sales"
      to: "sales@example.com"
    - if: "{field:department}" = "Support"
      to: "support@example.com"
    - else:
      to: "info@example.com"
```

---

## Payment Processing

Accept payments with Stripe and PayPal.

### Stripe Integration

```bfl
form "Order"
  payment:
    gateway: "stripe"
    amount: "{field:total}"
    currency: "USD"
    methods: ["card", "apple_pay"]
```

### PayPal Integration

```bfl
form "Donation"
  payment:
    gateway: "paypal"
    amount: "{field:amount}"
    currency: "USD"
```

### Product Fields

```bfl
product "Plan"
  required
  options:
    - "Basic": 9.99
    - "Pro": 19.99
    - "Enterprise": 49.99
```

---

## Security & Spam Prevention

### Honeypot

Invisible spam trap (recommended):

```bfl
form "Contact"
  honeypot: true
```

### Rate Limiting

Prevent submission flooding:

```bfl
form "Contact"
  rate_limit:
    max_submissions: 5
    window: "1 hour"
    per: "ip"
```

### CAPTCHA

Add human verification:

```bfl
form "Contact"
  recaptcha: true
  recaptcha_type: "v3"
```

---

## REST API

QAF provides a complete REST API for programmatic access.

### Authentication

All API requests require authentication:

```bash
curl -X GET \
  https://example.com/wp-json/jexi-forms/v1/forms \
  -H "X-WP-Nonce: your-nonce"
```

### Endpoints

| Method | Endpoint | Description |
| ------ | -------- | ----------- |
| GET | /forms | List all forms |
| GET | /forms/{id} | Get form details |
| POST | /forms | Create form |
| PUT | /forms/{id} | Update form |
| DELETE | /forms/{id} | Delete form |
| GET | /entries | List entries |
| GET | /entries/{id} | Get entry details |
| POST | /entries | Create entry |
| PUT | /entries/{id} | Update entry |
| DELETE | /entries/{id} | Delete entry |

### Example: Submit an Entry

```bash
curl -X POST \
  https://example.com/wp-json/jexi-forms/v1/entries \
  -H "Content-Type: application/json" \
  -d '{
    "form_id": 123,
    "fields": {
      "name": "John Doe",
      "email": "john@example.com"
    }
  }'
```

---

## Hooks & Filters for Developers

### Form Lifecycle

```php
// Before form renders
add_action( 'qaf_before_form', function( $form_id ) {
    // Customize form before display
} );

// After form renders
add_action( 'qaf_after_form', function( $form_id ) {
    // Add content after form
} );
```

### Entry Lifecycle

```php
// Before entry is saved
add_filter( 'qaf_pre_save_entry', function( $data, $form_id ) {
    // Modify entry data
    return $data;
}, 10, 2 );

// After entry is saved
add_action( 'qaf_entry_created', function( $entry_id, $form_id, $data ) {
    // Trigger external actions
}, 10, 3 );
```

### Validation

```php
// Custom validation
add_filter( 'qaf_validate_field', function( $is_valid, $value, $field, $form ) {
    if ( $field['id'] === 'promo_code' ) {
        return validate_promo_code( $value );
    }
    return $is_valid;
}, 10, 4 );
```

### Notifications

```php
// Modify notification before sending
add_filter( 'qaf_notification_message', function( $message, $entry, $form ) {
    return $message . "\n\nSent from " . get_bloginfo('name');
}, 10, 3 );
```

---

## Additional Feature Guides

For detailed documentation on specific features, see these dedicated guides:

| Guide | Description |
|-------|-------------|
| [Autocomplete Providers](autocomplete-providers-guide.md) | Address autocomplete, static datasets (countries, states, timezones), Google Places integration, and graceful fallbacks |
| [Field Type Conversion](field-type-conversion-guide.md) | Converting fields between types while preserving properties and conditional logic |
| [Accessibility Coach](accessibility-coach-guide.md) | Real-time accessibility scoring, issue detection, and fix suggestions |
| [CSV Import](csv-import-guide.md) | Generate forms from CSV files with intelligent field type detection |
| [QAF Form Language Guide](qaf-form-language-guide.md) | Comprehensive QAF form language tutorial with recipes for common patterns |
| [Field Types Guide](field-types-guide.md) | Field-by-field walkthrough with examples |

---

## Troubleshooting

### Form Not Displaying

1. **Check shortcode**: Verify the ID matches your form
2. **Check form status**: Form must be published, not draft
3. **Check JavaScript console**: Look for errors
4. **Disable other plugins**: Test for conflicts

### Emails Not Sending

1. **Check spam folder**: Emails may be filtered
2. **Verify email addresses**: Check settings
3. **Use SMTP plugin**: WordPress mail can be unreliable
4. **Check server logs**: Look for mail errors

### AJAX Submission Failing

1. **Check REST API**: Visit `/wp-json/` to verify it works
2. **Check security plugins**: They may block REST API
3. **Check error log**: Look for PHP errors
4. **Disable caching**: Some caching interferes

### Keyboard Navigation Not Working

1. **Check JavaScript errors**: Open browser console
2. **Try without other plugins**: Test for conflicts
3. **Verify focus visible**: Check browser settings

### Screen Reader Issues

1. **Update screen reader**: Use latest version
2. **Check browser compatibility**: Some combinations work better
3. **Verify ARIA regions**: Use browser dev tools to inspect

### Need More Help?

- **Documentation**: You're reading it!
- **Support**: Contact support@example.com
- **Community**: Join our Discord server
- **GitHub**: Report issues in our repository

---

## Credits

QAF is developed with accessibility as a first-class citizen.

Licensed under the MIT License.
