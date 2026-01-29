# Field Types — Beginner Guide
This guide is intentionally **hand-holding**: it tells you *what each field is for*, *how it behaves*, and *what settings you will see* in the builder — based on the actual field definitions in the plugin.
Quick accessibility rule of thumb:
- **Single inputs** (text/email/number/etc.) use a label tied to one control.
- **Grouped choices** (radio/checkbox/likert/ranking/etc.) should be labeled as a group. BAF uses **`<fieldset>` + `<legend>`** for these where appropriate.
---
## Standard Fields
### Single Line Text (`text`)
**When to use it**
Use this when you need a **single line text** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders as a single control with an associated label.
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
## Pricing Fields
### Product (`product`)
**When to use it**
Use this when you need a **product** style input. If you’re unsure, add it and preview the form — you can always swap types later.
**What users experience**
They will see the label, optional help text, and an input/control appropriate for this field type.
**Accessibility notes**
- This field renders a grouped control using **`<fieldset>` + `<legend>`** (your field label becomes the legend).
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
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
**BFL example**
```bfl
user_password "Example"
  @required
```
---
