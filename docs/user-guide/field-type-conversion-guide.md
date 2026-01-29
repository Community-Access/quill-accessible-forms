# Field Type Conversion Guide

This guide explains how to convert fields between types in BAF, preserving properties and handling conditional logic safely.

## Overview

BAF allows you to change a field's type after creation. This is useful when:

- Realizing a text field should be a dropdown
- Converting radio buttons to checkboxes for multiple selection
- Upgrading a date field to include time
- Simplifying a complex field type

## Accessing Field Conversion

### In Structure Mode

1. Navigate to Structure Mode (tree view)
2. Find the field to convert
3. Click the 🔄 (Convert) button in the field actions
4. Select the target type
5. Review the conversion preview
6. Click "Convert Field"

### In Visual Builder

1. Open the Visual Builder
2. Find the field card
3. Click the 🔄 (Convert) button
4. Follow the same conversion flow

### Keyboard Shortcut

In Structure Mode, select a field and press `C` to open the conversion dialog.

## Conversion Compatibility

Fields are classified by conversion compatibility:

| Level | Meaning | Example |
|-------|---------|---------|
| ✅ Lossless | All data preserved | text → textarea |
| ⚠️ Lossy | Some data may change | multiselect → select |
| ❌ Incompatible | Requires manual setup | text → date |

### Compatibility Matrix

#### Text-Based Conversions

| From | To | Compatibility |
|------|-----|---------------|
| text | textarea | ✅ Lossless |
| text | email | ✅ Lossless |
| text | phone | ✅ Lossless |
| text | website | ✅ Lossless |
| text | password | ✅ Lossless |
| text | number | ⚠️ Lossy (non-numeric lost) |
| text | select | ⚠️ Lossy (needs choices) |

#### Choice-Based Conversions

| From | To | Compatibility |
|------|-----|---------------|
| select | radio | ✅ Lossless |
| select | multiselect | ✅ Lossless |
| radio | select | ✅ Lossless |
| radio | checkbox | ✅ Lossless |
| checkbox | radio | ⚠️ Lossy (multi→single) |
| multiselect | select | ⚠️ Lossy (multi→single) |

#### Date/Time Conversions

| From | To | Compatibility |
|------|-----|---------------|
| date | datetime | ✅ Lossless |
| time | datetime | ✅ Lossless |
| datetime | date | ⚠️ Lossy (time lost) |
| datetime | time | ⚠️ Lossy (date lost) |

#### Rating/Scale Conversions

| From | To | Compatibility |
|------|-----|---------------|
| rating | radio | ✅ Lossless (generates choices) |
| nps | radio | ✅ Lossless (generates 0-10 scale) |
| likert | select | ✅ Lossless |

## Property Preservation

BAF intelligently preserves properties during conversion.

### Always Preserved

- Field ID
- Label
- Description / Help Text
- Required status
- Readonly / Disabled
- CSS Class / ID
- Conditional Logic (if compatible)
- Page / Section placement

### Preserved When Applicable

| Property | Preserved For |
|----------|---------------|
| min / max | Number-based fields |
| minlength / maxlength | Text-based fields |
| pattern | Text-based fields |
| choices | Choice-based fields |
| autocomplete | Text-based fields |
| rows / cols | Textarea |
| accept / capture | File upload |

### Dropped When Incompatible

Properties that don't apply to the target type are dropped. You'll see these in the conversion preview.

## Special Conversions

### Text → Choice Field

When converting a text field to a choice field (select, radio, checkbox):

1. BAF generates default placeholder choices
2. Edit the field after conversion to add your options
3. Any existing data will be preserved as-is

**Generated choices:**
```
Option 1
Option 2
Option 3
```

### Rating → Radio

When converting a star rating to radio buttons:

1. BAF generates choices from the rating scale
2. A 5-star rating becomes choices: "1 star", "2 stars", etc.
3. Max stars determines number of choices

### NPS → Radio/Number

When converting an NPS field:

1. BAF generates 0-10 scale choices
2. Labels are numeric (0, 1, 2, ... 10)
3. Existing responses map directly

### Likert → Select/Radio

When converting a Likert scale:

1. Scale options become choice options
2. Original scale labels are preserved
3. Format adapts to target type

## Conditional Logic Impact

When you convert a field, BAF checks for conditional logic that references it:

### Automatic Detection

BAF scans all fields for conditions that:
- Reference the converted field's ID
- Use operators that may become invalid

### Compatibility Issues

| Source Type | Target Type | Issue |
|-------------|-------------|-------|
| Number | Text | Numeric operators (>, <) invalid |
| Choice | Text | "in" / "not_in" operators invalid |
| Text | Choice | "contains" operator may not work |

### Auto-Repair Option

Check "Automatically repair compatible conditions" to:

1. Update operator to nearest compatible equivalent
2. Change `>` to `=` when converting number to text
3. Change `contains` to `=` when converting to choice

### Manual Review

For complex conditions, BAF shows:
- Which fields are affected
- What the current condition is
- Suggested fix
- Whether it can be auto-repaired

## Conversion Preview

Before confirming, you'll see:

### Properties Preserved
All settings that will carry over to the new field type.

### Properties Removed
Settings that don't apply to the target type.

### New Defaults Added
Default values BAF adds for the target type.

### Conditional Logic Warnings
Any rules that may be affected by the conversion.

## Best Practices

### 1. Review Before Converting

Always check the conversion preview. Understand what changes before confirming.

### 2. Test Conditional Logic

After converting a field used in conditions, test the form to ensure logic still works.

### 3. Update Choices

When converting to a choice field, immediately update the placeholder choices.

### 4. Check Existing Data

If you have form submissions, understand how existing data maps to the new type.

### 5. Use Lossless Conversions When Possible

Prefer conversions marked as "lossless" to avoid data complications.

## Examples

### Convert Text to Dropdown

**Before:**
```bfl
department "Department" text
    @required
```

**After conversion to select:**
```bfl
department "Department" select
    @required
    "Option 1" | "Option 2" | "Option 3"
```

Then edit to add your choices:
```bfl
department "Department" select
    @required
    "Sales" | "Marketing" | "Engineering" | "Support"
```

### Convert Checkbox to Radio

**Before:**
```bfl
contact_method "How may we contact you?" checkbox
    "Email" | "Phone" | "Text"
```

**After conversion to radio:**
```bfl
contact_method "How may we contact you?" radio
    "Email" | "Phone" | "Text"
```

Note: Existing multi-select responses will keep only first value.

### Convert Date to DateTime

**Before:**
```bfl
appointment "Appointment Date" date
    @min_date "today"
```

**After conversion to datetime:**
```bfl
appointment "Appointment Date & Time" datetime
    @min_date "today"
```

Time defaults to 00:00 for existing entries.

## Troubleshooting

### Conversion Not Available

Some conversions are not supported:
- Layout fields (columns, sections) cannot be converted
- Submit buttons cannot be converted to inputs
- Repeater fields have limited conversion options

### Lost Data After Conversion

Data is never deleted, but may become inaccessible:
- Choice values not matching new options appear as-is
- Numeric validation may reject previously valid text
- Date formats may not parse correctly

### Conditional Logic Broken

If conditions stop working after conversion:
1. Edit the dependent field
2. Review and update the condition
3. Test with the new field type
