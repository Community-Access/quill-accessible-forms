# BFL (Barrier-Free Language) — an instructor-in-your-pocket guide

BFL is the plain-text language used by **BITS Accessible Forms (BAF)**.  
You can think of it as a "form builder you can type": you describe your form in readable lines, and BAF turns it into a real, accessible form.

This guide is intentionally **hand-holding** and **narrative**. It assumes you're new, and it tells you *exactly what to type*, *why you're typing it*, and *how to fix common mistakes*.

> Important: Everything in this guide is written to match the **current** BFL lexer/parser behavior in this repository.

---

## Table of contents

- Your first form (copy/paste)
- How BFL is read (mental model)
- Blocks: indentation vs `{ }` vs `[ ]` vs `begin/end`
- Lesson: labels vs IDs (`#field_id`)
- Lesson: modifiers (`@required`, `@placeholder`, …)
- Lesson: choice fields (+ options)
- Lesson: pages and sections
- Lesson: conditional blocks (`if` / `conditional`)
- Lesson: columns layouts (`columns` / `column`)
- Troubleshooting (when it "should work" but doesn't)
- Quick reference (what's actually supported)

---

## Your first form (copy/paste)

Let's start with a tiny form you can understand at a glance.

```bfl
form "Contact Us" @description "A simple starter form"

text "Your Name"
  @required
  @placeholder "Jane Doe"

email "Email Address"
  @required
  @placeholder "jane@example.com"

textarea "Message"
  @required
  @rows 6

submit "Send"
```

### What you just wrote

- `form "Contact Us"` starts the form and sets the title.
- Each field starts with a **field type** keyword (`text`, `email`, `textarea`, `submit`, …).
- Lines that are "inside" a field are **modifiers** like `@required` or `@rows 6`.

If you can read the example above, you already understand the core of BFL.

---

## How BFL is read (mental model)

When BAF reads BFL, it's basically doing this:

1. **Read a line** that starts a thing (a form, a field, a page break, a columns layout…)
2. **Optionally read a block** that belongs to that thing (modifiers, column contents, conditional contents…)
3. Repeat.

A helpful way to think about BFL:

- The form is the whole project.
- Each field is one question.
- A block is "details about the thing above it."

---

## Blocks: indentation vs `{ }` vs `[ ]` vs `begin/end`

In BFL, lots of features use the idea of a **block** ("everything inside here belongs to this").

BAF supports **four equivalent ways** to write blocks:

### 1) Indentation blocks (classic BFL)

```bfl
text "Your Name"
  @required
  @placeholder "Jane Doe"
```

### 2) Curly brace blocks `{ ... }`

```bfl
text "Your Name" { @required @placeholder "Jane Doe" }
```

### 3) Square bracket blocks `[ ... ]`

```bfl
text "Your Name" [ @required @placeholder "Jane Doe" ]
```

### 4) `begin ... end` blocks

```bfl
text "Your Name" begin
  @required
  @placeholder "Jane Doe"
end
```

### When should you use which?

- Use **indentation** when you're typing by hand and want the file to look like a clean outline.
- Use **`{ }` / `[ ]` / `begin/end`** when you're pasting BFL into places that might break whitespace.

### A note about `[ ]`: block vs list

Square brackets have **two meanings** in current BFL:

1. As a **block delimiter** (like `{ ... }`):
   - `text "Name" [ @required ]`

2. As a **list** for specific modifiers (like `@autocomplete_countries [...]`):
   - `@autocomplete_countries ["us", "ca"]`

You'll know which one you're using by *where it appears*:

- If the `[` appears right after a field header (or `columns`, `if`, etc.), it starts a **block**.
- If the `[` appears right after a modifier like `@autocomplete_countries`, it's a **list**.

---

## Lesson: labels vs IDs (`#field_id`)

This is the part that trips up almost everyone at least once.

### Labels are what people see

Your label is the text users see next to a field:

```bfl
text "Full Name"
```

You *can* use a single word without quotes:

```bfl
text Name
```

But be careful: without quotes, you only get **one word**. If you need spaces, use quotes.

### IDs are what logic points at

A **field ID** is how you refer to a field from conditional logic.

In current BFL, **IDs must start with `#`**.

```bfl
text #full_name "Full Name"
email #email "Email Address"
```

Why this matters:

- If you later change the label from "Email Address" to "Work Email", your ID `#email` can stay the same.
- Conditional logic uses the ID (not the label).

### "I wrote an ID but it didn't work" (common mistake)

This does **not** create an ID:

```bfl
text full_name "Full Name"   // ❌ no leading #, so it's not an ID
```

To create an ID, you must write:

```bfl
text #full_name "Full Name"  // ✅
```

---

## Lesson: modifiers (`@required`, `@placeholder`, …)

Modifiers are settings. They always start with `@`.

### You can write modifiers in four common syntaxes

#### A) On their own lines (indentation)

```bfl
text "Company"
  @required
  @placeholder "Acme Inc"
  @description "Your business name"
  @min 2
  @max 100
```

This is the most readable when typing by hand or writing long configurations.

#### B) Inline on the same line

```bfl
text "Company" @required @placeholder "Acme Inc" @description "Your business name"
```

Useful for short fields with a few modifiers.

#### C) Inside curly braces `{ ... }`

```bfl
text "Company" { @required @placeholder "Acme Inc" @description "Your business name" }
```

Good for copy/paste contexts where indentation might get lost.

#### D) Inside `begin/end` blocks

```bfl
text "Company" begin
  @required
  @placeholder "Acme Inc"
  @description "Your business name"
  @min 2
  @max 100
end
```

Great for long modifier lists or complex configurations that need visual separation.

All four syntaxes are understood by the current parser.

### More modifier examples by type

#### Text validation modifiers

```bfl
text "Email" @required @minlength 5 @maxlength 255
text "Username" { @required @min 3 @max 20 @unique_scope form }
```

#### Placeholder and default values

```bfl
text #username "Username" begin
  @required
  @placeholder "jane_doe"
  @default "user_"
  @description "Choose a unique username (3-20 characters)"
end

email #email "Email" [
  @required
  @placeholder "jane@example.com"
  @default "noreply@company.com"
]
```

#### Appearance and presentation modifiers

```bfl
text "Company" {
  @placeholder "Acme Inc"
  @size "large"
  @class "premium-field"
  @prefix "$"
  @suffix "/year"
}

textarea "Biography" [
  @rows 8
  @class "bio-field"
  @placeholder "Tell us about yourself..."
  @description "Share your background and experience"
]
```

#### Conditional visibility and state

```bfl
text #admin_notes "Admin notes" begin
  @admin-only
  @hidden
  @default "To be filled by staff"
  @rows 4
end

text "Read only reference" { @readonly @disabled @description "This field cannot be edited" }

text #archived_data "Archived data" [
  @hidden
  @admin
  @default "System generated"
]
```

#### Advanced choice and autocomplete modifiers

```bfl
select #country "Country" [
  @required
  @placeholder "Select your country"
  @autocomplete_provider "google"
  @autocomplete_countries ["us", "ca", "mx"]
]

select #address "Address" begin
  @cascade_from #country
  @cascade_library "google_places"
  @loading_message "Finding addresses..."
  @empty_message "No addresses found for that location"
end

multiselect #interests "Interests" {
  @multiple
  @placeholder "Select all that apply"
  @allow-other
}
```

#### Numeric and formatting modifiers

```bfl
number #age "Age" {
  @min 0
  @max 120
  @step 1
  @description "Must be 18 or older"
}

number #price "Price per unit" [
  @min 0.01
  @step 0.01
  @prefix "$"
  @default 9.99
]

number #quantity "Quantity" begin
  @min 1
  @max 1000
  @step 5
  @description "Order in increments of 5"
end
```

#### Validation and uniqueness

```bfl
text #social_handle "Social media handle" begin
  @required
  @unique_scope form
  @unique_message "That handle is already taken in this form"
  @mask "@*****"
  @description "Your unique username on our platform"
end

email #work_email "Work email" {
  @required
  @confirm
  @placeholder "name@company.com"
  @description "Please confirm your work email address"
}

text #phone_masked "Phone" [
  @required
  @mask "(999) 999-9999"
  @description "Format: (123) 456-7890"
]
```

### Modifiers currently supported for fields

These are the field-level modifiers that the parser recognizes today:

**Core Properties:**
- `@required`
- `@placeholder "..."`
- `@default "..."` or `@default 123`
- `@description "..."` (alias: `@desc`)

**Validation Constraints:**
- `@min 0`, `@max 100` (numeric values)
- `@minlength 2`, `@maxlength 100` (text length)
- `@pattern "[A-Z]{3}-[0-9]{4}"` (HTML5 regex pattern)
- `@unique` (enforce uniqueness)
- `@unique_scope form` (or `global`)
- `@unique_message "..."`

**Appearance:**
- `@rows 6` (textarea rows)
- `@cols 80` (textarea columns)
- `@size "..."` (small, medium, large, full)
- `@class "..."` (alias: `@css`)
- `@prefix "..."`
- `@suffix "..."`

**State Modifiers:**
- `@readonly`
- `@disabled`
- `@admin` / `@admin-only`
- `@hidden` (sets visibility hidden)

**Email Field:**
- `@confirm` (email confirmation)

**Input Masking:**
- `@mask "..."`
- `@format "..."`

**HTML5 Attributes:**
- `@autocomplete` or `@autocomplete "..."` (browser autocomplete tokens)
- `@autofocus` (auto-focus on page load)
- `@spellcheck "true"` or `@spellcheck "false"`
- `@inputmode "..."` (none, text, decimal, numeric, tel, search, email, url)
- `@enterkeyhint "..."` (enter, done, go, next, previous, search, send)

**File Upload Attributes:**
- `@accept "image/*,.pdf"` (MIME types / extensions)
- `@capture "environment"` or `@capture "user"` (camera access on mobile)

**Autocomplete Providers:**
- `@autocomplete_provider google`
- `@autocomplete_countries ["us", "ca"]`
- `@autocomplete_types ["address"]`

**Smart Defaults:**
- `@smart_default "..."` (user:*, today+N, geo)

**Choice Fields:**
- `@library countries` (choice library)
- `@multiple`
- `@allow-other`

**Cascading Dropdowns:**
- `@cascade_from #other_field` (or a string)
- `@cascade_library "..."`
- `@empty_message "..."`
- `@loading_message "..."`

**Numeric Fields:**
- `@step 1`

> If you write a modifier that's not on this list, the current parser will simply ignore it (no error), because it only handles the cases above.

### Form-level modifiers (on the `form` line)

Form-level modifiers are specified **inline on the same line** as the `form` keyword. They cannot be placed inside blocks (`{ }`, `[ ]`, or `begin/end`).

```bfl
form "My Form" @description "A comprehensive form with validation" @ajax @honeypot @captcha "recaptcha3"

form "Another Form" @description "A simple form" @ajax

form "Third Form" @description "Multi-step form with anti-spam" @honeypot @captcha
```

Supported form modifiers:

- `@description` / `@desc`
- `@ajax`
- `@honeypot`
- `@captcha` (optionally `@captcha "type"`)

---

## Lesson: choice fields (+ options)

Choice fields are fields like `select`, `radio`, and `checkbox`.

### First: the golden rule for choices

Choices are **not read from the same line** as the field.

This won't work the way you expect:

```bfl
select "Department" + Sales    // ❌ choices must be in the following block/lines
```

Instead, put choices on their own lines.

### The simplest choice list

```bfl
select "Department"
  + Sales
  + Support
  + Billing
```

### Stored value vs label (`value|label`)

Use this format when you want a stable internal value (left side), but a nicer label (right side):

```bfl
radio "Payment Method"
  + card|Credit Card
  + paypal|PayPal
```

### Label with price (`label:price`)

Use this format for product-like selections:

```bfl
select "T-Shirt"
  + Small:10
  + Large:12
```

### Explicit choice blocks with different syntaxes

#### Using indentation (most readable)

```bfl
select "Department"
  + Sales
  + Support
  + Billing
```

#### Using curly braces `{ ... }`

```bfl
select "Department" {
  + Sales
  + Support
  + Billing
}
```

#### Using square brackets `[ ... ]`

```bfl
select "Department" [
  + Sales
  + Support
  + Billing
]
```

#### Using `begin/end` blocks

```bfl
select "Department" begin
  + Sales
  + Support
  + Billing
end
```

> Tip: Even inside `{ }`, `[ ]`, or `begin/end`, each choice must be on its own line because `+` reads until the end of the line.

### Choice blocks with modifiers

All syntaxes support adding modifiers to the field:

```bfl
select #dept "Department" {
  @required
  @placeholder "Select one..."
  + Sales
  + Support
  + Billing
}

radio #preferred "Preferred Contact" [
  @required
  + email|Email me
  + phone|Call me
  + sms|Text me
]

checkbox #interests "Interests" begin
  @multiple
  @allow-other
  + tech|Technology
  + sports|Sports
  + art|Art
end
```

### Choice libraries

Some keywords act as built-in choice libraries (e.g. `countries`, `us_states`, `months`).

```bfl
select "Country" countries
select "State" us_states
select "Month" months
```

That line sets choices to a library reference.

---

## Lesson: pages and sections

Pages and sections help you break up long forms.

### Page breaks

A `page` creates a new page (step) in the form:

```bfl
page "Step 1: About You"
text "Name" @required

page "Step 2: Details"
textarea "Details"
```

### Sections

A `section` is a heading inside the current page:

```bfl
section "Contact Information"
text "Full Name"
email "Email"
```

---

## Lesson: conditional blocks (`if` / `conditional`)

Conditional blocks are how you say: "Only show these fields when something is true."

In current BFL:

- You start a conditional block with **either** `if` **or** `conditional`.
- Inside the block, you put **fields**.
- Each field inside the block gets conditional logic attached (enabled, action `show`, logic `all`).

### Step 0: make sure the field you're checking has an ID

If you want to check a field in a condition, give it a `#id`.

```bfl
radio #contact_method "Preferred contact method"
  + Email
  + Phone
```

### Step 1: write the condition

A condition currently supports **one rule** and follows this pattern:

```bfl
if #field_id is "some value"
  ...fields...
```

The `#field_id` part is required (no `#` means it won't be recognized as an ID in the condition).

### Operators you can use

The parser recognizes these operator keywords:

- `is` (equals)
- `contains`
- `starts`
- `ends`
- `greater`
- `less`
- `empty`
- `filled`

### Example: show an "Email address" field only if they pick Email

```bfl
radio #contact_method "Preferred contact method"
  + Email
  + Phone

if #contact_method is "Email"
  email #email "Email address" @required
```

### Example: `empty` / `filled`

These are useful for "only ask the follow-up if they already typed something."

```bfl
text #company "Company"

if #company filled
  text "Company website" @placeholder "https://..."

if #company empty
  text "No company? That's okay—tell us what you do" @description "Student, freelancer, etc."
```

### Example: numeric comparisons (`greater` / `less`)

```bfl
number #age "Age"

if #age greater 17
  text "Which university are you attending?"
```

### Writing conditional blocks with explicit delimiters

#### Using indentation (most readable)

```bfl
if #contact_method is "Email"
  email #email "Email address" @required
  text #email_confirm "Confirm email" @required
```

#### Using `{ ... }`

```bfl
if #contact_method is "Email" {
  email #email "Email address" @required
  text #email_confirm "Confirm email" @required
}
```

#### Using `[ ... ]`

```bfl
if #contact_method is "Email" [
  email #email "Email address" @required
  text #email_confirm "Confirm email" @required
]
```

#### Using `begin/end`

```bfl
if #contact_method is "Phone" begin
  phone #phone "Phone number" @mask "(999) 999-9999"
  text #phone_ext "Extension" @placeholder "1234"
end
```

### Current limitations (important)

These are not "future plans"—they're simply what the current parser does:

- Conditions currently parse **a single rule** (no `and`/`or` groups).
- `else` blocks are not handled by the parser.
- Nested conditional blocks inside a conditional block are not parsed as conditionals.
- The conditional action is currently set to **show** for fields in the block.

If you need more complex behavior, you'll likely need to use the visual builder's logic UI after importing the structure, or split logic into separate simple `if` blocks.

---

## Lesson: columns layouts (`columns` / `column`)

Columns are a layout container that lets you place fields side-by-side.

### The shape of a columns layout

A columns layout starts with `columns`, optionally followed by a number (the column count), and then one or more `column` blocks.

#### Indentation style (most readable)

```bfl
columns 2
  column
    text #first "First name" @required

  column
    text #last "Last name" @required
```

What to notice:

- `columns 2` starts the container.
- Each `column` has its own block.
- Inside each `column`, you can place normal fields.

#### Curly braces style `{ ... }`

```bfl
columns 2 {
  column {
    text #first "First name" @required
  }

  column {
    text #last "Last name" @required
  }
}
```

#### Square brackets style `[ ... ]`

```bfl
columns 2 [
  column [
    text #first "First name" @required
  ]

  column [
    text #last "Last name" @required
  ]
]
```

#### `begin/end` style (most copy/paste friendly)

```bfl
columns 2 begin
  column begin
    text #first "First name" @required
  end

  column begin
    text #last "Last name" @required
  end
end
```

### Columns + mixed field types example

```bfl
columns 2
  column
    email #email "Email" @required
    phone #phone "Phone" @mask "(999) 999-9999"

  column
    select #dept "Department"
      + Sales
      + Support
      + Billing
```

### Three-column layout example

```bfl
columns 3 {
  column {
    text #first "First Name" @required
  }

  column {
    text #middle "Middle Name"
  }

  column {
    text #last "Last Name" @required
  }
}
```

### Common mistakes with columns

- **Forgetting `column`**: `columns` doesn't directly contain fields; it contains `column` blocks.
- **Wrong indentation**: fields must be indented under `column`.
- **Trying to add modifiers to `columns`**: the current parser doesn't read `@modifiers` on `columns`.

---

## Troubleshooting (when it "should work" but doesn't)

### 1) "My conditional block does nothing"

Check these in order:

- Does the field you're checking have a **`#id`**?
  - ✅ `radio #contact_method "Preferred contact"`
  - ❌ `radio contact_method "Preferred contact"`

- Does your condition reference the ID **with `#`**?
  - ✅ `if #contact_method is "Email"`
  - ❌ `if contact_method is "Email"`

- Are you using one of the supported operators (`is`, `contains`, `empty`, ...)?

### 2) "My choices aren't showing up"

- Choices must be on their own lines starting with `+`.
- Choices must be in the following block/lines (or inside a `{ }` / `[ ]` / `begin/end` block).

Good:

```bfl
select "Department"
  + Sales
  + Support
```

### 3) "My label looks wrong (missing words)"

Unquoted labels only capture **one word**.

- ✅ `text "Full Name"`
- ✅ `text Name`
- ❌ `text Full Name` (this will not produce the label you expect)

### 4) "My block is ending too early"

With indentation blocks, **spacing defines ownership**.

- If a line is indented under a field, it belongs to that field.
- If it is not indented (or the indent level decreases), the block is over.

If you're unsure, switch to explicit blocks:

```bfl
text "Name" begin
  @required
end
```

### 5) "Curly braces worked for modifiers but not for choices"

This is subtle:

- Modifiers can be space-separated inside `{ ... }`.
- Choices (`+`) read until the end of the line, so you typically want one `+` per line.

---

## Quick reference (what's actually supported)

### Structural keywords

- `form`
- `page`
- `section`
- `columns` / `column`
- `if` / `conditional`
- `begin` / `end`

### Field types

These are the field types recognized by the current parser:

- Text: `text`, `textarea` (alias: `paragraph`)
- Numbers: `number`
- Contact: `email`, `phone`, `website` (alias: `url`)
- Hidden/password: `hidden`, `password`
- Choice: `select` (alias: `dropdown`), `multiselect`, `radio`, `checkbox` (alias: `checkboxes`)
- Other choice-like: `consent`, `likert`, `ranking`, `rating`
- Date/time: `date`, `time`, `datetime`, `daterange`
- Advanced: `name`, `address`, `fileupload` (alias: `file`), `signature`, `captcha`, `repeater`, `calculation`
- Pricing: `product`, `quantity`, `price`, `total`, `coupon`
- Layout: `html`, `divider`, `spacer`
- Helpers: `submit`, `save` (alias: `savebutton`)
- Post fields: `post_title`, `post_content`, `post_excerpt`, `post_category`, `post_tags`, `post_image`, `post_custom`
- User fields: `user_login`, `user_email`, `user_password`

### Field type aliases (what BAF normalizes)

- `dropdown` → `select`
- `paragraph` → `textarea`
- `url` → `website`
- `checkboxes` → `checkbox`
- `file` → `fileupload`
- `savebutton` → `save`

### Condition operators

In a conditional header, the parser maps these keywords:

- `is` → `equals`
- `contains` → `contains`
- `starts` → `starts_with`
- `ends` → `ends_with`
- `greater` → `greater_than`
- `less` → `less_than`
- `empty` → `is_empty`
- `filled` → `is_not_empty`

### Block syntax summary

All these features support four block syntaxes:

| Feature | Indentation | `{ }` | `[ ]` | `begin/end` |
|---------|-------------|-------|-------|-----------|
| Field modifiers | ✅ | ✅ | ✅ | ✅ |
| Choice options | ✅ | ✅ | ✅ | ✅ |
| Conditional fields | ✅ | ✅ | ✅ | ✅ |
| Column contents | ✅ | ✅ | ✅ | ✅ |
| Form modifiers | (inline only) | ❌ | ❌ | ❌ |

---

---

## Recipes: common patterns

### Geographic cascade (Country → State)

Use choice libraries with cascade support to create dependent dropdowns:

```bfl
form "Shipping Address"

select #country "Country"
  @library countries
  @required
  @placeholder "Select your country"

select #state "State/Province"
  @cascade_from #country
  @cascade_library states
  @empty_message "Please select a country first"
  @loading_message "Loading states..."
  @required
```

The `states` cascade library automatically filters to show US states when United States is selected, Canadian provinces when Canada is selected, etc.

### Pattern validation examples

Use `@pattern` for regex validation with clear error feedback:

```bfl
form "Registration"

// US ZIP code (5 digits or 5+4)
text #zip "ZIP Code"
  @pattern "[0-9]{5}(-[0-9]{4})?"
  @placeholder "12345 or 12345-6789"
  @description "Enter a valid US ZIP code"

// Product code format
text #product_code "Product Code"
  @pattern "[A-Z]{3}-[0-9]{4}"
  @placeholder "ABC-1234"
  @description "Format: 3 letters, dash, 4 numbers"

// Username (alphanumeric, 3-20 chars)
text #username "Username"
  @pattern "[a-zA-Z0-9_]{3,20}"
  @minlength 3
  @maxlength 20
  @description "Letters, numbers, and underscores only"
```

### Mobile-optimized inputs

Use `@inputmode` and `@enterkeyhint` for better mobile UX:

```bfl
form "Contact"

text #phone "Phone Number"
  @inputmode "tel"
  @placeholder "(555) 123-4567"

number #age "Age"
  @inputmode "numeric"
  @min 0
  @max 150

email #email "Email"
  @inputmode "email"
  @enterkeyhint "next"

text #search "Search"
  @inputmode "search"
  @enterkeyhint "search"
```

### Photo capture on mobile

Use `@capture` for direct camera access:

```bfl
form "Verification"

// Front-facing camera (selfie)
fileupload #selfie "Take a Selfie"
  @accept "image/*"
  @capture "user"
  @description "Use your front camera"

// Back camera (documents, environment)
fileupload #document "Scan Document"
  @accept "image/*"
  @capture "environment"
  @description "Use your back camera to scan"
```

### Unique email with custom message

```bfl
form "Newsletter Signup"

email #email "Email Address"
  @required
  @unique
  @unique_scope "global"
  @unique_message "This email is already subscribed"
  @placeholder "you@example.com"
```

### Multi-column address form

```bfl
form "Address Form"

columns 2 {
  column {
    text #street "Street Address"
      @required
      @autocomplete "street-address"
  }
  column {
    text #apt "Apt/Suite"
      @autocomplete "address-line2"
  }
}

columns 3 {
  column {
    text #city "City"
      @required
      @autocomplete "address-level2"
  }
  column {
    select #state "State"
      @library us_states
      @required
      @autocomplete "address-level1"
  }
  column {
    text #zip "ZIP"
      @required
      @pattern "[0-9]{5}"
      @autocomplete "postal-code"
      @inputmode "numeric"
  }
}
```

---

## Next steps

- For field-specific behavior beyond what BFL parses today, use BFL for **structure**, then fine-tune in the visual builder.
- Keep IDs stable (`#like_this`) once you start using conditional logic.
- Mix and match syntax styles—use whichever is most readable for your use case.

See also:

- `docs/user-guide/complete-guide.md`
- `docs/CHOICE-LIBRARIES.md`
