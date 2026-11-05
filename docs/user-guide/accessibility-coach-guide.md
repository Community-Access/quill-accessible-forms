# Accessibility Coach Guide

The QAF Accessibility Coach provides real-time guidance to help you create forms that work for everyone, including users with disabilities.

## Overview

The Accessibility Coach analyzes your form and provides:

- **Accessibility Score** - 0-100 rating based on issue severity
- **Issue Detection** - Automatic scanning for common problems
- **Fix Suggestions** - Actionable guidance to resolve issues
- **Click-to-Navigate** - Jump directly to problem fields

## Opening the Coach

### Method 1: Toolbar Button

In the form builder, click the "Accessibility Coach" button (♿) in the toolbar.

### Method 2: Keyboard Shortcut

Press **Ctrl+Shift+A** (Windows) or **Cmd+Shift+A** (Mac) anywhere in the builder.

## Understanding the Score

| Score | Rating | Meaning |
|-------|--------|---------|
| 80-100 | Excellent | Few or no issues |
| 50-79 | Needs Work | Notable accessibility gaps |
| 0-49 | Critical | Major accessibility barriers |

The score is calculated based on:
- **Errors**: -15 points each (critical issues)
- **Warnings**: -5 points each (should fix)
- **Info**: -1 point each (suggestions)

## Issue Categories

### Labels & Names

**Why it matters:** Screen reader users rely on labels to identify form fields.

| Issue | Severity | Fix |
|-------|----------|-----|
| Missing field label | Error | Add a visible label |
| Generic/unclear label | Warning | Use specific, descriptive text |
| Placeholder as label | Error | Add proper label; placeholder can supplement |
| Label too short | Warning | Use more descriptive text |

**Good labels:**
- ✅ "Email Address"
- ✅ "Date of Birth"
- ✅ "Street Address Line 1"

**Bad labels:**
- ❌ "Input"
- ❌ "Field 1"
- ❌ "Enter here"

### Help Text & Instructions

**Why it matters:** Complex fields need clear instructions for all users.

| Issue | Severity | Fix |
|-------|----------|-----|
| Missing help text | Info | Add description for complex fields |

**Fields that benefit from help text:**
- File upload (explain accepted formats)
- Date fields (clarify expected format)
- Phone fields (include formatting hints)
- Password fields (show requirements)

### Form Structure

**Why it matters:** Well-organized forms are easier to navigate and complete.

| Issue | Severity | Fix |
|-------|----------|-----|
| Missing form title | Warning | Add descriptive title |
| Too many fields without sections | Info | Organize into logical groups |
| Duplicate field IDs | Error | Ensure each ID is unique |
| No submit button | Info | Add submit button |
| Missing page title | Warning | Title each page in multi-page forms |

### Validation & Errors

**Why it matters:** Error messages must be clear and connected to fields.

| Issue | Severity | Fix |
|-------|----------|-----|
| Missing validation message | Warning | Add custom error message for pattern fields |
| Insufficient choice options | Warning | Add at least 2 options to choice fields |

### Grouped Controls

**Why it matters:** Radio buttons and checkboxes need proper grouping for screen readers.

| Issue | Severity | Fix |
|-------|----------|-----|
| Hidden legend on grouped controls | Error | Remove "hide legend" or add ARIA labels |

## Using the Coach

### 1. Initial Analysis

When you open the coach, it automatically analyzes your current form configuration.

### 2. Review Issues

Issues are grouped by category and sorted by severity:
- 🔴 **Errors** - Must fix for accessibility compliance
- 🟡 **Warnings** - Should fix for better experience
- 🔵 **Info** - Suggestions for improvement

### 3. Navigate to Issues

Click any issue to scroll to and highlight the affected field.

### 4. Apply Fixes

Each issue includes a suggestion. Common fixes:

**For missing labels:**
1. Click the issue to navigate to the field
2. Click Edit (✏️) on the field
3. Enter a descriptive label
4. Save changes

**For missing help text:**
1. Navigate to the field
2. Click Edit
3. Add text in the "Description" or "Help Text" field
4. Save changes

### 5. Re-analyze

Click the refresh button (🔄) to re-scan after making changes.

### Auto-Analyze Mode

Enable "Auto-analyze on changes" in the coach footer to automatically re-scan whenever you modify the form.

## Checks Performed

### Field-Level Checks

1. **Label presence** - Every input needs a label
2. **Label quality** - Labels should be descriptive
3. **Placeholder misuse** - Placeholders shouldn't replace labels
4. **Help text** - Complex fields need instructions
5. **Validation messages** - Pattern fields need error text
6. **Choice options** - Selection fields need options
7. **Fieldset/legend** - Grouped controls need proper markup

### Form-Level Checks

1. **Form title** - Forms should be titled
2. **Unique IDs** - No duplicate field IDs
3. **Organization** - Large forms benefit from sections
4. **Submit button** - Forms need a way to submit
5. **Page titles** - Multi-page forms need page headings

## Interpreting Suggestions

Each issue includes actionable guidance:

### Example: Missing Label

> **Missing field label**
> 
> Every form field must have a visible label. Screen reader users cannot identify unlabeled fields.
> 
> **Suggestion:** Add a descriptive label that explains what information this field collects.

### Example: Generic Label

> **Generic field label**
> 
> The label "Input" is generic and may not clearly describe the expected input.
> 
> **Suggestion:** Use a specific, descriptive label like "First Name" instead of "Name" or "Enter information" instead of "Input".

## Best Practices

### 1. Start with Good Labels

The most common accessibility issue is poor labels. Write labels as if explaining to someone who can't see the form.

### 2. Use Help Text Strategically

Don't add help text to every field, but do add it when:
- The expected format isn't obvious
- There are specific requirements
- The field has technical constraints

### 3. Organize Large Forms

Forms with more than 10 fields should be organized into sections with clear headings.

### 4. Test with Real Users

The coach catches common issues, but real user testing with assistive technology is invaluable.

### 5. Check Throughout Development

Run the coach periodically as you build, not just at the end.

## Limitations

The Accessibility Coach checks for common issues but cannot detect:

- Color contrast problems (requires visual inspection)
- Keyboard trap issues (requires manual testing)
- Complex ARIA implementation errors
- Screen reader pronunciation issues
- Cognitive complexity

For full accessibility compliance, complement the coach with:
- Manual keyboard testing
- Screen reader testing (NVDA, JAWS, VoiceOver)
- Color contrast analyzers
- User testing with people who have disabilities

## WCAG 2.2 Coverage

The coach helps with these WCAG success criteria:

| Criteria | Level | Coverage |
|----------|-------|----------|
| 1.3.1 Info and Relationships | A | ✅ Fieldset/legend checks |
| 2.4.6 Headings and Labels | AA | ✅ Label quality checks |
| 3.3.2 Labels or Instructions | A | ✅ Label presence checks |
| 3.3.1 Error Identification | A | ⚠️ Validation message checks |
| 4.1.2 Name, Role, Value | A | ✅ Label/ARIA checks |

For full WCAG compliance, use the coach alongside manual testing.
