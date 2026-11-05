# Field Types — Beginner Guide
This guide is intentionally **hand-holding**: it tells you *what each field is for*, *how it behaves*, and *what settings you will see* in the builder — based on the actual field definitions in the plugin.
Quick accessibility rule of thumb:
- **Single inputs** (text/email/number/etc.) use a label tied to one control.
- **Grouped choices** (radio/checkbox/likert/ranking/etc.) should be labeled as a group. QAF uses **`<fieldset>` + `<legend>`** for these where appropriate.

---

## HTML5 Attributes (All Text-Based Fields)

These attributes are available on text, email, number, textarea, and other text-based fields via QAF form language or the Visual Builder's Advanced settings:

| Attribute | the QAF form language Example | Description |
| --------- | ----------- | ----------- |
| `pattern` | `@pattern "[A-Z]{3}-[0-9]{4}"` | Regex pattern for validation |
| `autofocus` | `@autofocus` | Auto-focus field on page load |
| `spellcheck` | `@spellcheck "false"` | Enable/disable browser spellcheck |
| `inputmode` | `@inputmode "tel"` | Keyboard type on mobile (numeric, tel, email, url, search, decimal, none) |
| `enterkeyhint` | `@enterkeyhint "search"` | Enter key label on mobile (enter, done, go, next, previous, search, send) |

### Example: Mobile-Optimized Phone Input
```bfl
text "Phone Number"
  @inputmode "tel"
  @pattern "[0-9]{3}-[0-9]{3}-[0-9]{4}"
  @placeholder "123-456-7890"
  @enterkeyhint "next"
```

---
## Standard Fields
### Single Line Text (`text`)
**When to use it**
Use this when you need a **single line text** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
text "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Placeholder** (`placeholder`)
   - **Default Value** (`default_value`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Field Size** (`size`)
   - **Label Placement** (`options`)
   - **Label Placement** (`label_placement`)
   - **CSS Class** (`css_class`)
5) Open the **Validation** section and set what you need:
   - **Minimum Length** (`min_length`)
   - **Maximum Length** (`max_length`)
   - **Custom Error Message** (`custom_error_message`)
6) Open the **Advanced** section and set what you need:
   - **Input Mask** (`input_mask`)
   - **Custom Mask Pattern** (`options`)
   - **Custom Mask Pattern** (`input_mask_custom`)
   - **Autocomplete** (`autocomplete`)
   - **Allow Dynamic Prefill** (`allow_prefill`)
   - **URL Parameter Name** (`prefill_parameter`)
   - **Admin Only** (`admin_only`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `placeholder`: Placeholder
  - `default_value`: Default Value
  - `required`: Required
- *appearance*
  - `size`: Field Size
  - `options`: Label Placement
  - `label_placement`: Label Placement
  - `css_class`: CSS Class
- *validation*
  - `min_length`: Minimum Length
  - `max_length`: Maximum Length
  - `custom_error_message`: Custom Error Message
- *advanced*
  - `input_mask`: Input Mask
  - `options`: Custom Mask Pattern
  - `input_mask_custom`: Custom Mask Pattern
  - `autocomplete`: Autocomplete
  - `allow_prefill`: Allow Dynamic Prefill
  - `prefill_parameter`: URL Parameter Name
  - `admin_only`: Admin Only
---
### Paragraph Text (`textarea`)
**When to use it**
Use this when you need a **paragraph text** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
textarea "Example"
  @required
```
---
### Number (`number`)
**When to use it**
Use this when you need a **number** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
number "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Placeholder** (`placeholder`)
   - **Prefix** (`prefix`)
   - **Suffix** (`suffix`)
5) Open the **Validation** section and set what you need:
   - **Minimum Value** (`min`)
   - **Maximum Value** (`max`)
   - **Step Increment** (`step`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description — e.g., 0.01 for cents, 1 for whole numbers
  - `required`: Required — e.g., 0.01 for cents, 1 for whole numbers
- *appearance*
  - `placeholder`: Placeholder — e.g., $
  - `prefix`: Prefix — e.g., $
  - `suffix`: Suffix — e.g., kg, units
- *validation*
  - `min`: Minimum Value — e.g., 0.01 for cents, 1 for whole numbers
  - `max`: Maximum Value — e.g., 0.01 for cents, 1 for whole numbers
  - `step`: Step Increment — e.g., 0.01 for cents, 1 for whole numbers
---
### Email (`email`)
**When to use it**
Use this when you need an **email** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
email "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Placeholder** (`placeholder`)
   - **Default Value** (`default_value`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Field Size** (`size`)
   - **Show Validation Status Icon** (`options`)
   - **Show Validation Status Icon** (`show_email_validation`)
5) Open the **Validation** section and set what you need:
   - **Enable Email Confirmation** (`confirm_email`)
   - **Confirmation Field Label** (`confirm_label`)
   - **Block Disposable Email Addresses** (`block_disposable`)
   - **Block Free Email Providers** (`block_free_email`)
   - **Allowed Domains** (`allowed_domains`)
   - **Blocked Domains** (`blocked_domains`)
   - **Custom Error Message** (`custom_error_message`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `placeholder`: Placeholder
  - `default_value`: Default Value
  - `required`: Required
- *appearance*
  - `size`: Field Size
  - `options`: Show Validation Status Icon
  - `show_email_validation`: Show Validation Status Icon
- *validation*
  - `confirm_email`: Enable Email Confirmation
  - `confirm_label`: Confirmation Field Label
  - `block_disposable`: Block Disposable Email Addresses
  - `block_free_email`: Block Free Email Providers
  - `allowed_domains`: Allowed Domains
  - `blocked_domains`: Blocked Domains
  - `custom_error_message`: Custom Error Message
---
### Phone (`phone`)
**When to use it**
Use this when you need a **phone** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
phone "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Placeholder** (`placeholder`)
5) Open the **Validation** section and set what you need:
   - **Phone Format** (`format`)
   - **Placeholder** (`options`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required
- *appearance*
  - `placeholder`: Placeholder
- *validation*
  - `format`: Phone Format
  - `options`: Placeholder
---
### Website / URL (`website`)
**When to use it**
Use this when you need a **website / url** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
website "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Placeholder** (`placeholder`)
5) Open the **Validation** section and set what you need:
   - **Require HTTPS** (`require_https`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label — Only accept secure URLs.
  - `description`: Description — Only accept secure URLs.
  - `required`: Required — Only accept secure URLs.
- *appearance*
  - `placeholder`: Placeholder
- *validation*
  - `require_https`: Require HTTPS — Only accept secure URLs.
---
### Hidden (`hidden`)
**When to use it**
Use this when you need a **hidden** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
hidden "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Admin Label** (`label`)
   - **Default Value** (`default_value`)
   - **Populate From** (`dynamic_source`)
   - **Parameter/Key Name** (`options`)
   - **Parameter/Key Name** (`parameter_name`)
   - **Allow URL Override** (`allow_override`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Admin Label — For admin reference only.
  - `default_value`: Default Value
  - `dynamic_source`: Populate From — Allow the value to be changed via URL parameters.
  - `options`: Parameter/Key Name — Allow the value to be changed via URL parameters.
  - `parameter_name`: Parameter/Key Name — Allow the value to be changed via URL parameters.
  - `allow_override`: Allow URL Override — Allow the value to be changed via URL parameters.
---
### Password (`password`)
**When to use it**
Use this when you need a **password** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
password "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Placeholder** (`placeholder`)
   - **Show Strength Meter** (`show_strength`)
   - **Enable Confirm Password** (`enable_confirm`)
   - **Show Password Toggle** (`show_toggle`)
5) Open the **Validation** section and set what you need:
   - **Minimum Length** (`min_length`)
   - **Require Uppercase Letter** (`require_uppercase`)
   - **Require Lowercase Letter** (`require_lowercase`)
   - **Require Number** (`require_number`)
   - **Require Special Character** (`require_special`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required
- *appearance*
  - `placeholder`: Placeholder
  - `show_strength`: Show Strength Meter
  - `enable_confirm`: Enable Confirm Password
  - `show_toggle`: Show Password Toggle
- *validation*
  - `min_length`: Minimum Length
  - `require_uppercase`: Require Uppercase Letter
  - `require_lowercase`: Require Lowercase Letter
  - `require_number`: Require Number
  - `require_special`: Require Special Character
---
### Color Picker (`color`)
**When to use it**
Use this when you need a **color picker** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
color "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
   - **Default Color** (`default_color`)
   - **Allow Alpha/Transparency** (`allow_alpha`)
   - **Show Hex Input** (`show_input`)
   - **Preset Colors** (`preset_colors`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required — One hex color per line.
  - `default_color`: Default Color — One hex color per line.
  - `allow_alpha`: Allow Alpha/Transparency — One hex color per line.
  - `show_input`: Show Hex Input — One hex color per line.
  - `preset_colors`: Preset Colors — One hex color per line.
---
### Range Slider (`range`)
**When to use it**
Use this when you need a **range slider** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
range "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
   - **Minimum Value** (`min`)
   - **Maximum Value** (`max`)
   - **Step** (`step`)
   - **Default Value** (`default_value`)
   - **Dual Handle (Range Selection)** (`dual_handle`)
   - **Show Current Value** (`show_value`)
   - **Show Min/Max Labels** (`show_labels`)
   - **Min Label** (`min_label`)
   - **Max Label** (`max_label`)
   - **Value Prefix** (`prefix`)
   - **Value Suffix** (`suffix`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required
  - `min`: Minimum Value
  - `max`: Maximum Value
  - `step`: Step
  - `default_value`: Default Value — Leave empty to show number.
  - `dual_handle`: Dual Handle (Range Selection) — Leave empty to show number.
  - `show_value`: Show Current Value — Leave empty to show number.
  - `show_labels`: Show Min/Max Labels — Leave empty to show number.
  - `min_label`: Min Label — Leave empty to show number.
  - `max_label`: Max Label — E.g., %, px, km
  - `prefix`: Value Prefix — E.g., %, px, km
  - `suffix`: Value Suffix — E.g., %, px, km
---
## Choice Fields
### Dropdown (`select`)
**When to use it**
Use this when you need a **dropdown** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
select "Example"
  + Option A
  + Option B

  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Choices** (`choices`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Placeholder Text** (`placeholder`)
   - **Allow Multiple Selections** (`multiple`)
   - **Make Searchable** (`searchable`)
5) Open the **Advanced** section and set what you need:
   - **Use Choice Library** (`choice_library`)
6) Open the **Cascading** section and set what you need:
   - **Cascade From Field** (`cascade_from`)
   - **Use Cascading Library** (`cascade_library`)
   - **Cascade Mapping** (`options`)
   - **Cascade Mapping** (`cascade_mapping`)
   - **Empty State Message** (`empty_message`)
   - **Loading Message** (`loading_message`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `choices`: Choices — Enables filtering options as user types.
  - `required`: Required — Enables filtering options as user types.
- *appearance*
  - `placeholder`: Placeholder Text — Enables filtering options as user types.
  - `multiple`: Allow Multiple Selections — Enables filtering options as user types.
  - `searchable`: Make Searchable — Enables filtering options as user types.
- *advanced*
  - `choice_library`: Use Choice Library
- *cascade*
  - `cascade_from`: Cascade From Field
  - `cascade_library`: Use Cascading Library
  - `options`: Cascade Mapping
  - `cascade_mapping`: Cascade Mapping
  - `empty_message`: Empty State Message
  - `loading_message`: Loading Message
---
### Multi-Select (`multiselect`)
**When to use it**
Use this when you need a **multi-select** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
multiselect "Example"
  + Option A
  + Option B

  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Choices** (`choices`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Visible Options** (`size`)
   - **Enhanced UI** (`enhanced`)
   - **Searchable** (`searchable`)
5) Open the **Validation** section and set what you need:
   - **Minimum Selections** (`min_selections`)
   - **Maximum Selections** (`max_selections`)
6) Open the **Advanced** section and set what you need:
   - **Use Choice Library** (`choice_library`)
   - **Allow New Values** (`allow_create`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description — 0 for unlimited.
  - `choices`: Choices — 0 for unlimited.
  - `required`: Required — 0 for unlimited.
- *appearance*
  - `size`: Visible Options — Use accessible combobox instead of native select.
  - `enhanced`: Enhanced UI — Use accessible combobox instead of native select.
  - `searchable`: Searchable — Users can create new options.
- *validation*
  - `min_selections`: Minimum Selections — 0 for unlimited.
  - `max_selections`: Maximum Selections — 0 for unlimited.
- *advanced*
  - `choice_library`: Use Choice Library — Users can create new options.
  - `allow_create`: Allow New Values — Users can create new options.
---
### Radio Buttons (`radio`)
**When to use it**
Use this when you need a **radio buttons** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
radio "Example"
  + Option A
  + Option B

  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Group Label** (`label`)
   - **Description** (`description`)
   - **Choices** (`choices`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Layout** (`layout`)
   - **Enable "Other" Option** (`options`)
   - **Enable "Other" Option** (`other_option`)
5) Open the **Advanced** section and set what you need:
   - **Randomize Choices** (`randomize`)
   - **Default Selection** (`default_value`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Group Label — Appears as the legend for this radio group.
  - `description`: Description — Enter each choice on a new line.
  - `choices`: Choices — Enter each choice on a new line.
  - `required`: Required
- *appearance*
  - `layout`: Layout
  - `options`: Enable "Other" Option
  - `other_option`: Enable "Other" Option
- *advanced*
  - `randomize`: Randomize Choices
  - `default_value`: Default Selection
---
### Checkboxes (`checkbox`)
**When to use it**
Use this when you need a **checkboxes** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
checkbox "Example"
  + Option A
  + Option B

  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Group Label** (`label`)
   - **Description** (`description`)
   - **Choices** (`choices`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Layout** (`layout`)
   - **Show "Select All" option** (`options`)
   - **Show "Select All" option** (`select_all`)
5) Open the **Validation** section and set what you need:
   - **Minimum Selections** (`min_selections`)
   - **Maximum Selections** (`max_selections`)
6) Open the **Advanced** section and set what you need:
   - **Enable "Other" Option** (`other_option`)
   - **Randomize Choices** (`randomize`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Group Label — At least one option must be selected.
  - `description`: Description — At least one option must be selected.
  - `choices`: Choices — At least one option must be selected.
  - `required`: Required — At least one option must be selected.
- *appearance*
  - `layout`: Layout — 0 for unlimited.
  - `options`: Show "Select All" option — 0 for unlimited.
  - `select_all`: Show "Select All" option — 0 for unlimited.
- *validation*
  - `min_selections`: Minimum Selections — 0 for unlimited.
  - `max_selections`: Maximum Selections — 0 for unlimited.
- *advanced*
  - `other_option`: Enable "Other" Option
  - `randomize`: Randomize Choices
---
### Consent / Terms (`consent`)
**When to use it**
Use this when you need a **consent / terms** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
consent "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Field Label** (`label`)
   - **Consent Text** (`consent_text`)
   - **Required** (`required`)
   - **Show Terms Box** (`show_terms_box`)
   - **Terms Content** (`terms_content`)
   - **Or Link to Page** (`terms_page`)
   - **Terms Box Height (px)** (`terms_box_height`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Field Label — Optional heading for the consent section.
  - `consent_text`: Consent Text — The checkbox label. Can include HTML links.
  - `required`: Required — Display scrollable terms content above checkbox.
  - `show_terms_box`: Show Terms Box — Display scrollable terms content above checkbox.
  - `terms_content`: Terms Content — Link to an existing page for terms.
  - `terms_page`: Or Link to Page — Link to an existing page for terms.
  - `terms_box_height`: Terms Box Height (px)
---
### Toggle Switch (`toggle`)
**When to use it**
Use this when you need a **toggle switch** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
toggle "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required (must be On)** (`required`)
   - **Default to On** (`default`)
   - **On State Label** (`on_label`)
   - **Off State Label** (`off_label`)
   - **On Value (stored)** (`on_value`)
   - **Off Value (stored)** (`off_value`)
4) Open the **Appearance** section and set what you need:
   - **Style** (`style`)
   - **options** (`options`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required (must be On)
  - `default`: Default to On
  - `on_label`: On State Label
  - `off_label`: Off State Label
  - `on_value`: On Value (stored)
  - `off_value`: Off Value (stored)
- *appearance*
  - `style`: Style
  - `options`
---
### Likert Scale (`likert`)
**When to use it**
Use this when you need a **likert scale** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
likert "Example"
  + Option A
  + Option B

  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Group Label** (`label`)
   - **Description** (`description`)
   - **Rows (Statements)** (`rows`)
   - **Value** (`fields`)
   - **Scale Type** (`scale_type`)
   - **Columns (Scale Points)** (`options`)
   - **Columns (Scale Points)** (`columns`)
4) Open the **Validation** section and set what you need:
   - **Require All Rows** (`require_all`)
5) Open the **Advanced** section and set what you need:
   - **Randomize Row Order** (`randomize_rows`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Group Label
  - `description`: Description
  - `rows`: Rows (Statements)
  - `fields`: Value
  - `scale_type`: Scale Type
  - `options`: Columns (Scale Points) — User must answer every row.
  - `columns`: Columns (Scale Points) — User must answer every row.
- *validation*
  - `require_all`: Require All Rows — User must answer every row.
- *advanced*
  - `randomize_rows`: Randomize Row Order
---
### Ranking (`ranking`)
**When to use it**
Use this when you need a **ranking** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
ranking "Example"
  + Option A
  + Option B

  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Items to Rank** (`choices`)
   - **Required** (`required`)
   - **Randomize Initial Order** (`randomize`)
   - **Require All Items Ranked** (`require_all`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `choices`: Items to Rank
  - `required`: Required
  - `randomize`: Randomize Initial Order
  - `require_all`: Require All Items Ranked
---
### Star Rating (`rating`)
**When to use it**
Use this when you need a **star rating** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
rating "Example"
  + Option A
  + Option B

  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
   - **Maximum Rating** (`max_rating`)
   - **Allow Half Values** (`options`)
   - **Allow Half Values** (`allow_half`)
   - **Allow Clear Selection** (`allow_clear`)
4) Open the **Appearance** section and set what you need:
   - **Icon Type** (`icon_type`)
   - **Show Low/High Labels** (`options`)
   - **Show Low/High Labels** (`show_labels`)
   - **Low Label** (`low_label`)
   - **High Label** (`high_label`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required
  - `max_rating`: Maximum Rating
  - `options`: Allow Half Values
  - `allow_half`: Allow Half Values
  - `allow_clear`: Allow Clear Selection
- *appearance*
  - `icon_type`: Icon Type
  - `options`: Show Low/High Labels
  - `show_labels`: Show Low/High Labels
  - `low_label`: Low Label
  - `high_label`: High Label
---
### Net Promoter Score (`nps`)
**When to use it**
Use this when you need a **net promoter score** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
nps "Example"
  + Option A
  + Option B

  @required
```
---
## Date & Time Fields
### Date Picker (`date`)
**When to use it**
Use this when you need a **date picker** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
date "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Display Format** (`display_format`)
   - **options** (`options`)
5) Open the **Validation** section and set what you need:
   - **Minimum Date** (`min_date`)
   - **Maximum Date** (`max_date`)
   - **Disable Past Dates** (`disable_past`)
   - **Disable Future Dates** (`disable_future`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required
- *appearance*
  - `display_format`: Display Format
  - `options`
- *validation*
  - `min_date`: Minimum Date
  - `max_date`: Maximum Date
  - `disable_past`: Disable Past Dates
  - `disable_future`: Disable Future Dates
---
### Time Picker (`time`)
**When to use it**
Use this when you need a **time picker** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
time "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Display Format** (`format`)
   - **Time Intervals** (`options`)
   - **Time Intervals** (`step`)
   - **Default Value** (`default_value`)
5) Open the **Validation** section and set what you need:
   - **Earliest Time** (`min_time`)
   - **Latest Time** (`max_time`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required
- *appearance*
  - `format`: Display Format
  - `options`: Time Intervals
  - `step`: Time Intervals
  - `default_value`: Default Value
- *validation*
  - `min_time`: Earliest Time
  - `max_time`: Latest Time
---
### Date & Time (`datetime`)
**When to use it**
Use this when you need a **date & time** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
datetime "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Date Format** (`date_format`)
   - **Time Format** (`options`)
   - **Time Format** (`time_format`)
   - **Time Intervals** (`step`)
5) Open the **Validation** section and set what you need:
   - **Earliest Date/Time** (`min_datetime`)
   - **Latest Date/Time** (`max_datetime`)
   - **Disable Past Dates/Times** (`disable_past`)
   - **Disable Future Dates/Times** (`disable_future`)
6) Open the **Advanced** section and set what you need:
   - **Timezone** (`timezone`)
   - **Default Value** (`options`)
   - **Default Value** (`default_value`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required
- *appearance*
  - `date_format`: Date Format
  - `options`: Time Format
  - `time_format`: Time Format
  - `step`: Time Intervals
- *validation*
  - `min_datetime`: Earliest Date/Time
  - `max_datetime`: Latest Date/Time
  - `disable_past`: Disable Past Dates/Times
  - `disable_future`: Disable Future Dates/Times
- *advanced*
  - `timezone`: Timezone
  - `options`: Default Value
  - `default_value`: Default Value
---
### Date Range (`daterange`)
**When to use it**
Use this when you need a **date range** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
daterange "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Group Label** (`label`)
   - **Start Date Label** (`start_label`)
   - **End Date Label** (`end_label`)
   - **Description** (`description`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Date Format** (`date_format`)
   - **options** (`options`)
5) Open the **Validation** section and set what you need:
   - **Earliest Date** (`min_date`)
   - **Latest Date** (`max_date`)
   - **Disable Past Dates** (`disable_past`)
   - **Minimum Days** (`min_duration`)
   - **Maximum Days** (`max_duration`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Group Label
  - `start_label`: Start Date Label
  - `end_label`: End Date Label
  - `description`: Description
  - `required`: Required — Minimum difference between start and end.
- *appearance*
  - `date_format`: Date Format
  - `options`
- *validation*
  - `min_date`: Earliest Date — Minimum difference between start and end.
  - `max_date`: Latest Date — Minimum difference between start and end.
  - `disable_past`: Disable Past Dates — Minimum difference between start and end.
  - `min_duration`: Minimum Days — Minimum difference between start and end.
  - `max_duration`: Maximum Days — Maximum difference between start and end. 0 for unlimited.
---
## Advanced Fields
### Name (`name`)
**When to use it**
Use this when you need a **name** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
name "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Field Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
   - **Name Format** (`format`)
   - **Show Prefix (Title)** (`options`)
   - **Show Prefix (Title)** (`prefix_enabled`)
   - **Show Suffix** (`suffix_enabled`)
   - **First Name Label** (`label_first`)
   - **Middle Name Label** (`label_middle`)
   - **Last Name Label** (`label_last`)
4) Open the **Appearance** section and set what you need:
   - **Layout** (`layout`)
   - **options** (`options`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Field Label — Label for the entire name group (legend).
  - `description`: Description
  - `required`: Required
  - `format`: Name Format
  - `options`: Show Prefix (Title)
  - `prefix_enabled`: Show Prefix (Title)
  - `suffix_enabled`: Show Suffix
  - `label_first`: First Name Label
  - `label_middle`: Middle Name Label
  - `label_last`: Last Name Label
- *appearance*
  - `layout`: Layout
  - `options`
---
### Address (`address`)
**When to use it**
Use this when you need a **address** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
address "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Field Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
   - **Show Address Line 2** (`show_line2`)
   - **Show Country** (`show_country`)
4) Open the **Appearance** section and set what you need:
   - **Layout** (`layout`)
   - **Enable Address Autocomplete** (`options`)
5) Open the **Advanced** section and set what you need:
   - **Enable Address Autocomplete** (`autocomplete`)
   - **Autocomplete Countries** (`autocomplete_countries`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Field Label
  - `description`: Description
  - `required`: Required
  - `show_line2`: Show Address Line 2
  - `show_country`: Show Country
- *appearance*
  - `layout`: Layout
  - `options`: Enable Address Autocomplete
- *advanced*
  - `autocomplete`: Enable Address Autocomplete
  - `autocomplete_countries`: Autocomplete Countries
---
### File Upload (`fileupload`)
**When to use it**
Use this when you need a **file upload** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
fileupload "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Show File Preview** (`enable_preview`)
   - **Enable Drag & Drop** (`enable_drag_drop`)
5) Open the **Validation** section and set what you need:
   - **Allowed File Types** (`allowed_types`)
   - **Max File Size (MB)** (`options`)
   - **Max File Size (MB)** (`max_size`)
   - **Max Number of Files** (`max_files`)
6) Open the **Advanced** section and set what you need:
   - **Add to Media Library** (`media_library`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required
- *appearance*
  - `enable_preview`: Show File Preview — Store uploaded files in WordPress Media Library.
  - `enable_drag_drop`: Enable Drag & Drop — Store uploaded files in WordPress Media Library.
- *validation*
  - `allowed_types`: Allowed File Types
  - `options`: Max File Size (MB)
  - `max_size`: Max File Size (MB)
  - `max_files`: Max Number of Files — Store uploaded files in WordPress Media Library.
- *advanced*
  - `media_library`: Add to Media Library — Store uploaded files in WordPress Media Library.
---
### Signature (`signature`)
**When to use it**
Use this when you need a **signature** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
signature "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
   - **Canvas Width (px)** (`width`)
   - **Canvas Height (px)** (`height`)
4) Open the **Appearance** section and set what you need:
   - **Stroke Width (px)** (`stroke_width`)
   - **Stroke Color** (`stroke_color`)
   - **Background Color** (`background_color`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required
  - `width`: Canvas Width (px)
  - `height`: Canvas Height (px)
- *appearance*
  - `stroke_width`: Stroke Width (px) — Essential for accessibility - allows users to type their name instead of drawing.
  - `stroke_color`: Stroke Color — Essential for accessibility - allows users to type their name instead of drawing.
  - `background_color`: Background Color — Essential for accessibility - allows users to type their name instead of drawing.
---
### Captcha (`captcha`)
**When to use it**
Use this when you need a **captcha** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
captcha "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **CAPTCHA Type** (`captcha_type`)
   - **Minimum Number** (`options`)
   - **Minimum Number** (`math_min`)
   - **Maximum Number** (`math_max`)
   - **Operation** (`math_operation`)
   - **reCAPTCHA Site Key** (`recaptcha_site_key`)
   - **reCAPTCHA Version** (`recaptcha_version`)
   - **Theme** (`recaptcha_theme`)
   - **hCaptcha Site Key** (`hcaptcha_site_key`)
   - **Turnstile Site Key** (`turnstile_site_key`)
   - **Enable Honeypot** (`honeypot_enabled`)

**Common settings in the builder (from code)**
- *general*
  - `captcha_type`: CAPTCHA Type — Math challenges are most accessible. reCAPTCHA v3 is invisible.
  - `options`: Minimum Number
  - `math_min`: Minimum Number
  - `math_max`: Maximum Number
  - `math_operation`: Operation
  - `recaptcha_site_key`: reCAPTCHA Site Key
  - `recaptcha_version`: reCAPTCHA Version
  - `recaptcha_theme`: Theme
  - `hcaptcha_site_key`: hCaptcha Site Key — Adds invisible spam trap field.
  - `turnstile_site_key`: Turnstile Site Key — Adds invisible spam trap field.
  - `honeypot_enabled`: Enable Honeypot — Adds invisible spam trap field.
---
### Repeater (`repeater`)
**When to use it**
Use this when you need a **repeater** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
repeater "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Fields in Repeater** (`fields`)
   - **Required** (`required`)
4) Open the **Appearance** section and set what you need:
   - **Row Label** (`row_label`)
   - **Add Button Text** (`add_button_text`)
   - **Layout** (`layout`)
   - **Collapsible Rows** (`options`)
   - **Collapsible Rows** (`collapsible`)
   - **Sortable Rows** (`sortable`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `fields`: Fields in Repeater
  - `required`: Required
- *appearance*
  - `row_label`: Row Label — Allows keyboard and drag-drop reordering.
  - `add_button_text`: Add Button Text — Allows keyboard and drag-drop reordering.
  - `layout`: Layout — Allows keyboard and drag-drop reordering.
  - `options`: Collapsible Rows — Allows keyboard and drag-drop reordering.
  - `collapsible`: Collapsible Rows — Allows keyboard and drag-drop reordering.
  - `sortable`: Sortable Rows — Allows keyboard and drag-drop reordering.
---
### Calculation (`calculation`)
**When to use it**
Use this when you need a **calculation** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
calculation "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Formula** (`formula`)
   - **Format** (`format`)
   - **Decimal Places** (`options`)
   - **Decimal Places** (`decimal_places`)
   - **Thousand Separator** (`thousand_separator`)
   - **Rounding Method** (`round_method`)
   - **Currency Symbol** (`currency_symbol`)
   - **Symbol Position** (`currency_position`)
   - **Prefix** (`prefix`)
   - **Suffix** (`suffix`)
   - **Hide When Empty/Zero** (`hide_empty`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label — Use {field_id} to reference other fields. Supports +, -, *, /, (), and functions like sum(), avg(), min(), max().
  - `description`: Description — Use {field_id} to reference other fields. Supports +, -, *, /, (), and functions like sum(), avg(), min(), max().
  - `formula`: Formula — Use {field_id} to reference other fields. Supports +, -, *, /, (), and functions like sum(), avg(), min(), max().
  - `format`: Format
  - `options`: Decimal Places
  - `decimal_places`: Decimal Places
  - `thousand_separator`: Thousand Separator
  - `round_method`: Rounding Method
  - `currency_symbol`: Currency Symbol
  - `currency_position`: Symbol Position
  - `prefix`: Prefix
  - `suffix`: Suffix
  - `hide_empty`: Hide When Empty/Zero
---
### Currency (`currency`)
**When to use it**
Use this for collecting monetary values with proper currency formatting, symbols, and thousand separators.
**What users experience**
They see a text input that automatically formats their entry as currency (e.g., "$1,234.56") while storing the raw numeric value.
**Accessibility notes**
- Includes both a formatted display input and hidden raw value input for form submission.
- Screen readers announce the currency symbol and formatted value.
**QAF form language example**
```bfl
currency "Annual Salary"
  @currency_code(USD)
  @min(0)
  @max(10000000)
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Currency**.
2) Click the field to open its settings panel.
3) Open the **General** section and set:
   - **Label** - The field label
   - **Description** - Help text
   - **Required** - Whether the field must be filled
4) Open the **Currency** section:
   - **Currency Code** - USD, EUR, GBP, etc. (20 currencies supported)
   - **Symbol Position** - Before or after the amount
   - **Decimal Places** - Number of decimal places (default: 2)
   - **Thousand Separator** - Comma, period, or space
   - **Decimal Separator** - Period or comma
5) Open the **Validation** section:
   - **Minimum Value** - Minimum allowed amount
   - **Maximum Value** - Maximum allowed amount

---
### OTP / Verification Code (`otp`)
**When to use it**
Use this for collecting verification codes, PINs, or one-time passwords sent via SMS or email.
**What users experience**
They see a series of individual input boxes (typically 4-6) that auto-advance as they type. Supports paste for convenience.
**Accessibility notes**
- Each digit input has proper labeling (e.g., "Digit 1 of 6")
- Keyboard navigation with arrow keys between digits
- Backspace moves to previous digit when empty
- Paste distributes code across all inputs
**QAF form language example**
```bfl
otp "Verification Code"
  @length(6)
  @mode(numeric)
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **OTP / Verification Code**.
2) Open the **General** section:
   - **Label** - The field label
   - **Help Text** - Instructions for the user
   - **Required** - Must be filled
3) Open the **OTP Settings** section:
   - **Code Length** - 4 to 10 digits (default: 6)
   - **Input Mode** - Numeric, Alpha, or Alphanumeric
   - **Masked Input** - Hide digits like a password
   - **Auto-Submit** - Submit form when complete

---
### Typed Signature (`typed_signature`)
**When to use it**
Use this as an accessible alternative to handwritten signatures. Users type their full legal name which is displayed in a signature-style font.
**What users experience**
They type their name in a text field and see it rendered in a signature font below, along with the current date and a confirmation checkbox.
**Accessibility notes**
- Fully keyboard accessible (no drawing required)
- Works with all assistive technologies
- Captures timestamp and IP for legal compliance
- Confirmation checkbox ensures intentional signing
**QAF form language example**
```bfl
typed_signature "Electronic Signature"
  @font_style(cursive)
  @show_date
  @require_confirmation
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Typed Signature**.
2) Open the **General** section:
   - **Label** - "Signature" or similar
   - **Description** - Legal notice or instructions
   - **Required** - Must be signed
3) Open the **Signature** section:
   - **Font Style** - Cursive, Handwritten, or Formal
   - **Show Signature Preview** - Display live preview
   - **Show Date** - Display signing date
4) Open the **Agreement** section:
   - **Agreement Text** - Terms being agreed to
   - **Confirmation Text** - Legal statement shown with checkbox
   - **Require Confirmation Checkbox** - Must check to confirm

---
### Slider (`slider`)
**When to use it**
Use this when you want users to select a value from a continuous range, with an accessible numeric input alternative.
**What users experience**
They see a draggable slider with optional min/max labels, a live value display, and a paired numeric input for precise entry.
**Accessibility notes**
- Includes paired numeric input for keyboard users
- Proper ARIA attributes (aria-valuenow, aria-valuemin, aria-valuemax, aria-valuetext)
- Live region announces value changes
- Keyboard: Arrow keys adjust value by step amount
**QAF form language example**
```bfl
slider "Satisfaction Rating"
  @min(0)
  @max(100)
  @step(5)
  @default(50)
  @suffix(%)
  @show_labels
  @show_input
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Slider**.
2) Open the **General** section:
   - **Label** - Field label
   - **Description** - Help text
   - **Required** - Must select a value
3) Open the **Range** section:
   - **Minimum Value** - Slider start value
   - **Maximum Value** - Slider end value
   - **Step** - Increment amount (e.g., 5 for 0,5,10,15...)
   - **Default Value** - Initial position
4) Open the **Display** section:
   - **Show Current Value** - Display selected value
   - **Show Min/Max Labels** - Display range endpoints
   - **Show Number Input** - Include text input for keyboard entry
   - **Prefix** - Text before value (e.g., "$")
   - **Suffix** - Text after value (e.g., "%", "lbs")

---
### Image Choice (`image_choice`)
**When to use it**
Use this when you want users to select from options represented visually with images or icons, such as selecting a product color, style preference, or category.
**What users experience**
They see a grid of clickable cards containing images/icons with labels. Selected options are highlighted visually.
**Accessibility notes**
- Uses `<fieldset>` + `<legend>` for proper grouping
- Full keyboard navigation with arrow keys
- Visual selection indicator (checkmark) alongside border highlight
- Screen readers announce option labels
**QAF form language example**
```bfl
image_choice "Select Your Plan"
  + Basic {icon: "star"} "Free tier"
  + Pro {icon: "star-filled"} "For professionals"
  + Enterprise {image: "/images/enterprise.png"} "Full features"

  @layout(grid)
  @columns(3)
  @multiple
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Image Choice**.
2) Open the **General** section:
   - **Label** - Field label
   - **Description** - Help text
   - **Required** - Must select at least one
3) Open the **Options** section:
   - **Options** - Add/remove choices with images, icons, or text
   - **Allow Multiple Selection** - Checkboxes instead of radio buttons
4) Open the **Display** section:
   - **Layout** - Grid, List, or Inline
   - **Columns** - Number of columns (for grid layout)
   - **Image Size** - Width and height of images
   - **Show Labels** - Display text below images

---
### Matrix / Grid (`matrix`)
**When to use it**
Use this for tabular data entry where users rate or respond to multiple items using the same scale (Likert-style surveys, feature comparisons, scheduling grids).
**What users experience**
A table with row headers (questions/items) and column headers (options/ratings). Each cell contains an input appropriate to the configuration.
**Accessibility notes**
- Uses proper `<table>` semantics with row and column headers
- Each cell input has aria-label combining row and column context
- Keyboard navigation works with Tab and arrow keys
- Mobile-responsive with stacked card view option
**QAF form language example**
```bfl
matrix "Rate the following aspects"
  rows:
    - Service Quality
    - Value for Money
    - Staff Friendliness
  columns:
    - Poor
    - Fair
    - Good
    - Excellent

  @input_type(radio)
  @require_all
  @alternate_rows
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Matrix / Grid**.
2) Open the **General** section:
   - **Label** - Field label (used as legend)
   - **Description** - Instructions for the user
3) Open the **Rows & Columns** section:
   - **Rows** - Items to be rated/answered
   - **Columns** - Response options or categories
4) Open the **Input Type** section:
   - **Input Type** - Radio, Checkbox, Text, Number, or Dropdown
   - **Dropdown Options** - For select type, the options to show
   - **Require All Rows** - Each row must have a selection
5) Open the **Display** section:
   - **Alternate Row Colors** - Zebra striping for readability
   - **Sticky Header** - Column headers stay visible when scrolling
   - **Mobile Layout** - Stacked cards or horizontal scroll

---
### Rich Text Editor (`rich_text`)
**When to use it**
Use this when you need users to enter formatted content (blog posts, detailed descriptions, formatted messages).
**What users experience**
A WYSIWYG editor with a formatting toolbar (bold, italic, links, lists, headings) and a content area where they can type and see formatting live.
**Accessibility notes**
- Toolbar buttons have proper labels and keyboard shortcuts
- Editor area uses contenteditable with proper ARIA roles
- Character counter announces updates via live region
- Hidden textarea syncs content for form submission
**QAF form language example**
```bfl
rich_text "Your Message"
  @toolbar(standard)
  @max_chars(5000)
  @show_char_count
  @placeholder "Write your message here..."
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Rich Text Editor**.
2) Open the **General** section:
   - **Label** - Field label
   - **Description** - Instructions
   - **Placeholder** - Text shown when empty
   - **Required** - Must have content
3) Open the **Editor** section:
   - **Toolbar** - Minimal, Standard, or Full
   - **Editor Height** - Height in pixels
   - **Show Media Upload** - Allow image insertion
4) Open the **Validation** section:
   - **Maximum Characters** - Character limit (0 for unlimited)
   - **Show Character Count** - Display count below editor
5) Open the **Security** section:
   - **Allow HTML Output** - Whether to preserve formatting
   - **Allowed Tags** - Which HTML tags are permitted

---
### Progress Indicator (`progress`)
**When to use it**
Use this to show users their progress through a multi-page form. Place it at the top or bottom of your form to provide navigation context.
**What users experience**
A visual indicator showing current step, completed steps, and remaining steps. May include step numbers, labels, and percentage completion.
**Accessibility notes**
- Uses `<nav>` with proper aria-label
- Completed steps can be clickable (if enabled) for back-navigation
- Live region announces progress changes
- Current step marked with aria-current="step"
**QAF form language example**
```bfl
progress
  @style(steps)
  @show_labels
  @show_numbers
  @clickable
  @position(top)
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Progress Indicator**.
2) Open the **Display** section:
   - **Style** - Steps, Bar, Percentage Circle, or Dots
   - **Position** - Top, Bottom, or Fixed at Top
   - **Show Labels** - Display step titles
   - **Show Numbers** - Display step numbers
   - **Show Percentage** - Display completion percentage
3) Open the **Interaction** section:
   - **Allow Click Navigation** - Let users click to go back
   - **Animate Progress** - Smooth transitions
4) Open the **Appearance** section:
   - **Connector Style** - Solid, Dashed, or None
   - **Completed Color** - Color for completed steps
   - **Current Color** - Color for current step
   - **Pending Color** - Color for future steps

---
### Summary / Review (`summary`)
**When to use it**
Use this on the final page of a form to let users review all their answers before submitting. Essential for long forms and legal documents.
**What users experience**
A formatted display of all previously entered data, organized by section or page, with optional edit buttons to make corrections.
**Accessibility notes**
- Uses semantic markup (tables, definition lists)
- Edit buttons are properly labeled for each field
- Collapsible sections use proper ARIA expanded states
- Modal edit dialog is fully keyboard accessible
**QAF form language example**
```bfl
summary "Review Your Application"
  @layout(table)
  @group_by(section)
  @allow_edit
  @edit_mode(modal)
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Summary / Review**.
2) Open the **General** section:
   - **Title** - Heading for the review section
   - **Description** - Instructions ("Review before submitting")
3) Open the **Display** section:
   - **Layout** - Table, Cards, or List
   - **Group By** - Section, Page, or None
   - **Show Empty Fields** - Include fields with no value
   - **Show Field Icons** - Display icons for field types
   - **Collapsible Sections** - Allow expanding/collapsing
4) Open the **Editing** section:
   - **Allow Editing** - Show edit buttons
   - **Edit Mode** - Modal, Inline, or Jump to Field

---
### Quiz Question (`quiz`)
**When to use it**
Use this for assessments, tests, or surveys with correct/incorrect answers. Supports scoring, feedback, and various question types.
**What users experience**
A question with answer options, optional hints, and instant feedback upon submission. Supports time limits and partial credit.
**Accessibility notes**
- Uses `<fieldset>` + `<legend>` for proper grouping
- Hint button uses aria-expanded and aria-controls
- Feedback announced via live region
- Timer announced to screen readers
**QAF form language example**
```bfl
quiz "What is the capital of France?"
  + London
  + Paris {correct}
  + Berlin
  + Rome

  @question_type(multiple_choice)
  @points(5)
  @show_feedback
  @feedback_correct "Correct! Paris is the capital."
  @explanation "France's capital has been Paris since 987 CE."
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Quiz Question**.
2) Open the **Question** section:
   - **Question** - The question text
   - **Question Type** - Multiple Choice, Multiple Answer, True/False, or Short Answer
   - **Options** - Answer choices with correct answers marked
3) Open the **Scoring** section:
   - **Points** - Points for correct answer
   - **Allow Partial Credit** - For multiple-answer questions
   - **Case Sensitive** - For short answer questions
4) Open the **Feedback** section:
   - **Show Feedback** - Display result after answering
   - **Correct Feedback** - Message for correct answers
   - **Incorrect Feedback** - Message for wrong answers
   - **Explanation** - Detailed explanation shown after
5) Open the **Hints** section:
   - **Hint Text** - Hint content
   - **Show Hint Button** - Let users request hints

---
### Appointment Picker (`appointment`)
**When to use it**
Use this when users need to schedule appointments, meetings, or reserve time slots with availability management.
**What users experience**
A calendar for date selection alongside a time slot picker showing available times. Selected datetime is displayed clearly.
**Accessibility notes**
- Calendar uses proper grid semantics with navigation
- Time slots are in a listbox with keyboard navigation
- Disabled dates/times marked with aria-disabled
- Selected appointment announced via live region
**QAF form language example**
```bfl
appointment "Schedule Your Consultation"
  @duration(60)
  @min_notice(24)
  @max_advance(30)
  @timezone(user)
  @show_timezone
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Appointment Picker**.
2) Open the **General** section:
   - **Label** - Field label
   - **Description** - Instructions
   - **Required** - Must select an appointment
3) Open the **Scheduling** section:
   - **Duration** - Appointment length (15-120 minutes)
   - **Buffer Time** - Time between appointments
   - **Minimum Notice** - Hours in advance required
   - **Max Advance** - Days in future allowed
   - **Max Per Slot** - For group appointments
4) Open the **Availability** section:
   - **Weekly Availability** - Set hours for each day
   - **Blocked Dates** - Specific dates unavailable
5) Open the **Display** section:
   - **Timezone** - Site, User's, or specific
   - **Date/Time Format** - How dates are displayed
   - **Show Remaining Spots** - For group appointments

---
### Map / Location Picker (`map`)
**When to use it**
Use this when you need users to select a geographic location - delivery addresses, event venues, property locations, etc.
**What users experience**
An interactive map where they can search for addresses, click to place a pin, or use their current location. The selected address is displayed clearly.
**Accessibility notes**
- Search input with autocomplete suggestions
- "Use My Location" button with proper labeling
- Keyboard-accessible clear button
- Address components available separately for processing
**QAF form language example**
```bfl
map "Delivery Address"
  @provider(leaflet)
  @show_search
  @show_current_location
  @reverse_geocode
  @restrict_country(US)
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Map / Location Picker**.
2) Open the **General** section:
   - **Label** - Field label
   - **Description** - Instructions
   - **Required** - Must select location
3) Open the **Map** section:
   - **Provider** - Leaflet (free) or Google Maps
   - **Map Style** - Streets, Satellite, or Terrain
   - **Map Height** - Height in pixels
4) Open the **Location** section:
   - **Default Center** - Initial map center coordinates
   - **Default Zoom** - Initial zoom level
   - **Restrict to Country** - Limit searches to specific country
5) Open the **Features** section:
   - **Show Search** - Address search box
   - **Use My Location** - Geolocation button
   - **Show Coordinates** - Display lat/lng fields
   - **Draggable Marker** - Allow repositioning pin

---
### Social Profile (`social`)
**When to use it**
Use this to collect social media profile links with proper validation for each platform (LinkedIn, Twitter, GitHub, etc.).
**What users experience**
Input fields with network icons, automatic URL validation, and optional username-to-URL conversion.
**Accessibility notes**
- Network icons have proper labels
- Validation errors are specific to each platform
- Multiple-entry mode has proper add/remove controls
- All inputs properly labeled
**QAF form language example**
```bfl
social "Your Social Profiles"
  @mode(specific)
  @networks(linkedin, twitter, github)
  @validate_url
  @allow_username
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Social Profile**.
2) Open the **General** section:
   - **Label** - Field label
   - **Description** - Instructions
   - **Required** - Must provide at least one
3) Open the **Mode** section:
   - **Mode** - Single (one network), Specific (choose which), Multiple (any)
   - **Network** - For single mode, which network
   - **Networks** - For specific mode, which networks to show
   - **Max Profiles** - For multiple mode, limit count
4) Open the **Display** section:
   - **Show Network Icons** - Display platform icons
5) Open the **Validation** section:
   - **Validate URL** - Check URL format
   - **Allow Username Only** - Accept @handle without full URL
   - **Normalize to URL** - Convert usernames to full URLs

---
### Table Input (`table`)
**When to use it**
Use this for structured data entry that fits a tabular format - expense reports, item lists, schedules, data grids.
**What users experience**
A spreadsheet-like grid where they can enter data in cells, add/remove rows, and optionally reorder entries.
**Accessibility notes**
- Proper table semantics with column and row headers
- Each cell input is individually labeled
- Add/delete actions have clear labels
- Mobile view shows stacked cards instead of table
**QAF form language example**
```bfl
table "Expense Items"
  columns:
    - Date {type: date, required}
    - Description {type: text}
    - Amount {type: number, required}
    - Category {type: select, options: "Travel|Food|Supplies|Other"}

  @min_rows(1)
  @max_rows(20)
  @allow_add_rows
  @allow_delete_rows
  @allow_reorder
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Table Input**.
2) Open the **General** section:
   - **Label** - Field label
   - **Description** - Instructions
   - **Required** - Must have at least minimum rows with data
3) Open the **Columns** section:
   - **Columns** - Define each column with ID, Label, Type, Width, Required, Options
4) Open the **Rows** section:
   - **Minimum Rows** - Minimum entries required
   - **Maximum Rows** - Maximum entries allowed
   - **Default Rows** - Initial number of empty rows
   - **Allow Adding Rows** - Show add button
   - **Allow Deleting Rows** - Show delete buttons
   - **Allow Reordering** - Drag handle for row order
5) Open the **Display** section:
   - **Show Row Numbers** - Number column on left
   - **Header Style** - Sticky, Static, or Hidden
   - **Striped Rows** - Alternating row colors
   - **Cell Padding** - Compact, Normal, or Spacious

---
### Payment (`payment`)
**When to use it**
Use this to collect payments within your form using Stripe, PayPal, or Square. Supports various payment methods, tips, and multiple currencies.
**What users experience**
A secure payment form with amount display, optional tip selection, payment method choices, and inline card/payment fields powered by the payment provider.
**Accessibility notes**
- Payment provider elements are PCI-compliant iframes
- Tip options use proper radio button semantics
- Total updates announced via live region
- Security badge provides reassurance
**QAF form language example**
```bfl
payment "Complete Your Order"
  @provider(stripe)
  @payment_methods(card, apple_pay, google_pay)
  @amount_type(fixed)
  @amount(49.99)
  @currency(USD)
  @allow_tip
  @tip_options(10, 15, 20, 25)
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose **Payment**.
2) Open the **General** section:
   - **Label** - Field label
   - **Description** - Order summary text
   - **Required** - Payment must be completed
3) Open the **Provider** section:
   - **Provider** - Stripe, PayPal, or Square
   - **Payment Methods** - Card, Apple Pay, Google Pay, etc.
4) Open the **Amount** section:
   - **Amount Type** - Fixed, From Another Field, or User Input
   - **Amount** - For fixed type, the price
   - **Amount Field** - For field type, which field
   - **Currency** - USD, EUR, GBP, etc.
5) Open the **Tips** section:
   - **Allow Tips** - Show tip options
   - **Tip Percentages** - Preset tip amounts
   - **Allow Custom Tip** - Let users enter amount
6) Open the **Display** section:
   - **Show Order Total** - Display running total
   - **Theme** - Default, Night, Flat, or Minimal

---
## Pricing Fields
### Product (`product`)
**When to use it**
Use this when you need a **product** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**QAF form language example**
```bfl
product "Example"
  + Option A
  + Option B

  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Products** (`products`)
   - **Product Name** (`fields`)
   - **Required** (`required`)
   - **Display Style** (`display_style`)
   - **Columns** (`options`)
   - **Columns** (`columns`)
   - **Show Product Images** (`show_images`)
   - **Show Descriptions** (`show_descriptions`)
   - **Show Prices** (`show_prices`)
   - **Featured Badge Text** (`featured_badge`)
4) Open the **Pricing** section and set what you need:
   - **Currency** (`currency`)
   - **Currency Position** (`currency_position`)
   - **Price Format** (`options`)
   - **Price Format** (`price_format`)
   - **Allow Quantity Selection** (`allow_quantity`)
   - **Minimum Quantity** (`min_quantity`)
   - **Maximum Quantity** (`max_quantity`)
   - **Allow Multiple Products** (`multiple_selection`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `products`: Products
  - `fields`: Product Name
  - `required`: Required
  - `display_style`: Display Style
  - `options`: Columns
  - `columns`: Columns
  - `show_images`: Show Product Images
  - `show_descriptions`: Show Descriptions
  - `show_prices`: Show Prices
  - `featured_badge`: Featured Badge Text
- *pricing*
  - `currency`: Currency
  - `currency_position`: Currency Position
  - `options`: Price Format
  - `price_format`: Price Format
  - `allow_quantity`: Allow Quantity Selection
  - `min_quantity`: Minimum Quantity
  - `max_quantity`: Maximum Quantity
  - `multiple_selection`: Allow Multiple Products
---
### Quantity (`quantity`)
**When to use it**
Use this when you need a **quantity** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
quantity "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Required** (`required`)
   - **Default Quantity** (`default_value`)
   - **Minimum** (`min`)
   - **Maximum** (`max`)
   - **Step** (`step`)
   - **Display Style** (`display_style`)
   - **Show Increment/Decrement Controls** (`options`)
   - **Show Increment/Decrement Controls** (`show_controls`)
   - **Control Style** (`control_style`)
   - **Allow Decimal Values** (`allow_decimal`)
   - **Decimal Places** (`decimal_places`)
   - **Unit Label** (`unit_label`)
   - **Unit Position** (`unit_position`)
   - **Show Stock Count** (`show_stock`)
   - **Available Stock** (`stock_count`)
   - **Low Stock Warning** (`low_stock_threshold`)
4) Open the **Advanced** section and set what you need:
   - **Linked Product Field** (`linked_product`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `required`: Required
  - `default_value`: Default Quantity
  - `min`: Minimum
  - `max`: Maximum
  - `step`: Step
  - `display_style`: Display Style
  - `options`: Show Increment/Decrement Controls
  - `show_controls`: Show Increment/Decrement Controls
  - `control_style`: Control Style
  - `allow_decimal`: Allow Decimal Values
  - `decimal_places`: Decimal Places
  - `unit_label`: Unit Label
  - `unit_position`: Unit Position
  - `show_stock`: Show Stock Count
  - `stock_count`: Available Stock
  - `low_stock_threshold`: Low Stock Warning
- *advanced*
  - `linked_product`: Linked Product Field
---
### Price (`price`)
**When to use it**
Use this when you need a **price** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
price "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Base Price** (`base_price`)
   - **Currency** (`currency`)
   - **Currency Position** (`options`)
   - **Currency Position** (`currency_position`)
   - **Decimal Places** (`decimal_places`)
   - **Display Style** (`display_style`)
   - **Per Unit Label** (`per_unit_label`)
   - **Show Original Price (Strikethrough)** (`show_original`)
   - **Original Price** (`original_price`)
   - **Sale Badge Text** (`sale_badge`)
   - **Linked Fields** (`linked_fields`)
   - **Price Calculation** (`calculation`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `base_price`: Base Price
  - `currency`: Currency
  - `options`: Currency Position
  - `currency_position`: Currency Position
  - `decimal_places`: Decimal Places
  - `display_style`: Display Style
  - `per_unit_label`: Per Unit Label
  - `show_original`: Show Original Price (Strikethrough)
  - `original_price`: Original Price
  - `sale_badge`: Sale Badge Text
  - `linked_fields`: Linked Fields
  - `calculation`: Price Calculation
---
### Total (`total`)
**When to use it**
Use this when you need a **total** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
total "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Total Label** (`label`)
   - **Description** (`description`)
   - **Currency** (`currency`)
   - **Currency Position** (`options`)
   - **Currency Position** (`currency_position`)
   - **Decimal Places** (`decimal_places`)
   - **Thousand Separator** (`thousand_separator`)
   - **Show Breakdown** (`show_breakdown`)
   - **Show Subtotal** (`show_subtotal`)
   - **Show Discount Line** (`show_discount`)
   - **Include Tax** (`show_tax`)
   - **Tax Rate (%)** (`tax_rate`)
   - **Tax Label** (`tax_label`)
   - **Tax Included in Prices** (`tax_included`)
   - **Include Shipping** (`show_shipping`)
   - **Shipping Amount** (`shipping_amount`)
   - **Shipping Label** (`shipping_label`)
   - **Free Shipping Minimum** (`free_shipping_min`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Total Label
  - `description`: Description
  - `currency`: Currency
  - `options`: Currency Position
  - `currency_position`: Currency Position
  - `decimal_places`: Decimal Places
  - `thousand_separator`: Thousand Separator
  - `show_breakdown`: Show Breakdown
  - `show_subtotal`: Show Subtotal
  - `show_discount`: Show Discount Line
  - `show_tax`: Include Tax
  - `tax_rate`: Tax Rate (%)
  - `tax_label`: Tax Label
  - `tax_included`: Tax Included in Prices
  - `show_shipping`: Include Shipping
  - `shipping_amount`: Shipping Amount
  - `shipping_label`: Shipping Label
  - `free_shipping_min`: Free Shipping Minimum
---
### Coupon (`coupon`)
**When to use it**
Use this when you need a **coupon** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
coupon "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label** (`label`)
   - **Description** (`description`)
   - **Placeholder** (`placeholder`)
   - **Required** (`required`)
   - **Coupon Codes** (`coupons`)
   - **Code** (`fields`)
   - **Code** (`code`)
   - **Discount Type** (`discount_type`)
   - **Discount Value** (`options`)
   - **Discount Value** (`discount_value`)
   - **Minimum Purchase** (`min_purchase`)
   - **Maximum Discount** (`max_discount`)
   - **Usage Limit** (`usage_limit`)
   - **Expiry Date** (`expiry_date`)
   - **Enabled** (`enabled`)
   - **Display Style** (`display_style`)
   - **Apply Button Text** (`button_text`)
   - **Show Savings Amount** (`show_savings`)
   - **Savings Display Format** (`savings_format`)
   - **Success Message** (`success_message`)
   - **Invalid Code Message** (`error_message`)
   - **Expired Message** (`expired_message`)
   - **Usage Limit Message** (`used_message`)
4) Open the **Advanced** section and set what you need:
   - **Case Sensitive Codes** (`case_sensitive`)
   - **Validate via AJAX** (`validate_ajax`)
   - **Auto-Apply Code** (`auto_apply`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label
  - `description`: Description
  - `placeholder`: Placeholder
  - `required`: Required
  - `coupons`: Coupon Codes
  - `fields`: Code
  - `code`: Code
  - `discount_type`: Discount Type
  - `options`: Discount Value
  - `discount_value`: Discount Value
  - `min_purchase`: Minimum Purchase
  - `max_discount`: Maximum Discount
  - `usage_limit`: Usage Limit
  - `expiry_date`: Expiry Date
  - `enabled`: Enabled
  - `display_style`: Display Style
  - `button_text`: Apply Button Text
  - `show_savings`: Show Savings Amount
  - `savings_format`: Savings Display Format
  - `success_message`: Success Message
  - `error_message`: Invalid Code Message
  - `expired_message`: Expired Message
  - `used_message`: Usage Limit Message
- *advanced*
  - `case_sensitive`: Case Sensitive Codes
  - `validate_ajax`: Validate via AJAX
  - `auto_apply`: Auto-Apply Code
---
## Layout Elements
### Section Break (`section`)
**When to use it**
Use this when you need a **section break** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
section "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Section Title** (`title`)
   - **Section Description** (`description`)
4) Open the **Appearance** section and set what you need:
   - **Heading Level** (`heading_level`)
   - **Show Divider Line** (`options`)
   - **Show Divider Line** (`show_divider`)
   - **Make Section Collapsible** (`collapsible`)
   - **Start Collapsed** (`collapsed_default`)
   - **Collapse Icon Style** (`collapse_icon`)
   - **Animation** (`animation`)
5) Open the **Advanced** section and set what you need:
   - **CSS Class** (`css_class`)

**Common settings in the builder (from code)**
- *general*
  - `title`: Section Title — Choose appropriate level for document hierarchy.
  - `description`: Section Description — Choose appropriate level for document hierarchy.
- *appearance*
  - `heading_level`: Heading Level — Choose appropriate level for document hierarchy.
  - `options`: Show Divider Line
  - `show_divider`: Show Divider Line
  - `collapsible`: Make Section Collapsible
  - `collapsed_default`: Start Collapsed
  - `collapse_icon`: Collapse Icon Style
  - `animation`: Animation
- *advanced*
  - `css_class`: CSS Class
---
### Page Break (`page`)
**When to use it**
Use this when you need a **page break** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
page "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Page Title** (`page_title`)
   - **Previous Button Text** (`prev_button_text`)
   - **Next Button Text** (`next_button_text`)
   - **Show Progress Indicator** (`show_progress`)
   - **Progress Style** (`progress_style`)
   - **Validate Before Moving Next** (`options`)
4) Open the **Validation** section and set what you need:
   - **Validate Before Moving Next** (`validate_on_next`)

**Common settings in the builder (from code)**
- *general*
  - `page_title`: Page Title — Optional title shown in progress indicator.
  - `prev_button_text`: Previous Button Text
  - `next_button_text`: Next Button Text
  - `show_progress`: Show Progress Indicator
  - `progress_style`: Progress Style
  - `options`: Validate Before Moving Next
- *validation*
  - `validate_on_next`: Validate Before Moving Next
---
### HTML (`html`)
**When to use it**
Use this when you need a **html** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
html "Example"
  @required
```
---
### Columns (`columns`)
**When to use it**
Use this when you need a **columns** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
columns "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Column Layout** (`layout`)
   - **Column Contents** (`options`)
   - **Column Contents** (`columns`)
   - **Column Gap** (`gap`)
   - **Stack on Mobile** (`stack_on_mobile`)
   - **Reverse Order on Mobile** (`reverse_mobile`)

**Common settings in the builder (from code)**
- *general*
  - `layout`: Column Layout
  - `options`: Column Contents
  - `columns`: Column Contents — Display columns vertically on small screens.
  - `gap`: Column Gap — Display columns vertically on small screens.
  - `stack_on_mobile`: Stack on Mobile — Display columns vertically on small screens.
  - `reverse_mobile`: Reverse Order on Mobile
---
## Form Helpers
### Submit Button (`submit`)
**When to use it**
Use this when you need a **submit button** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
submit "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Button Text** (`button_text`)
   - **Processing Text** (`processing_text`)
4) Open the **Appearance** section and set what you need:
   - **Button Style** (`button_style`)
   - **Button Size** (`options`)
   - **Button Size** (`button_size`)
   - **Full Width** (`full_width`)
   - **Alignment** (`alignment`)
   - **Icon** (`icon`)
   - **Icon Position** (`icon_position`)

**Common settings in the builder (from code)**
- *general*
  - `button_text`: Button Text — Text shown while form is being submitted.
  - `processing_text`: Processing Text — Text shown while form is being submitted.
- *appearance*
  - `button_style`: Button Style
  - `options`: Button Size
  - `button_size`: Button Size
  - `full_width`: Full Width
  - `alignment`: Alignment
  - `icon`: Icon
  - `icon_position`: Icon Position
---
### Save Progress Button (`save`)
**When to use it**
Use this when you need a **save progress button** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
save "Example"
  @required
```
**Visual Builder steps (very literal)**
1) In the builder, click **Add Field** and choose this field type.
2) Click the field to open its settings panel.
3) Open the **General** section and set what you need:
   - **Label (Screen Reader)** (`label`)
   - **Button Text** (`button_text`)
4) Open the **Appearance** section and set what you need:
   - **Button Style** (`button_style`)
   - **Button Size** (`options`)
   - **Button Size** (`button_size`)
   - **Icon** (`icon`)
   - **Icon Position** (`icon_position`)
   - **Alignment** (`alignment`)
   - **Show Confirmation Message** (`show_confirmation`)
   - **Confirmation Message** (`confirmation_message`)
   - **Email Continuation Link** (`send_link`)
   - **Email Subject** (`email_subject`)
   - **Email Message** (`email_message`)
   - **Link Expiry (days)** (`link_expiry_days`)

**Common settings in the builder (from code)**
- *general*
  - `label`: Label (Screen Reader)
  - `button_text`: Button Text
- *appearance*
  - `button_style`: Button Style
  - `options`: Button Size
  - `button_size`: Button Size
  - `icon`: Icon
  - `icon_position`: Icon Position
  - `alignment`: Alignment
  - `show_confirmation`: Show Confirmation Message
  - `confirmation_message`: Confirmation Message
  - `send_link`: Email Continuation Link
  - `email_subject`: Email Subject
  - `email_message`: Email Message
  - `link_expiry_days`: Link Expiry (days)
---
## Post Fields
### Post Title (`post_title`)
**When to use it**
Use this when you need a **post title** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
post_title "Example"
  @required
```
---
### Post Content (`post_content`)
**When to use it**
Use this when you need a **post content** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
post_content "Example"
  @required
```
---
### Post Excerpt (`post_excerpt`)
**When to use it**
Use this when you need a **post excerpt** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
post_excerpt "Example"
  @required
```
---
### Post Category (`post_category`)
**When to use it**
Use this when you need a **post category** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
post_category "Example"
  @required
```
---
### Post Tags (`post_tags`)
**When to use it**
Use this when you need a **post tags** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
post_tags "Example"
  @required
```
---
### Featured Image (`post_image`)
**When to use it**
Use this when you need a **featured image** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
post_image "Example"
  @required
```
---
### Custom Field (`post_custom`)
**When to use it**
Use this when you need a **custom field** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
post_custom "Example"
  @required
```
---
## User Fields
### Username (`user_login`)
**When to use it**
Use this when you need a **username** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
user_login "Example"
  @required
```
---
### User Email (`user_email`)
**When to use it**
Use this when you need a **user email** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
user_email "Example"
  @required
```
---
### User Password (`user_password`)
**When to use it**
Use this when you need a **user password** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**QAF form language example**
```bfl
user_password "Example"
  @required
```
---
