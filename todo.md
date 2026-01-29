# TODO — BAF roadmap (autocomplete, field features, HTML attributes)

This file is an implementation checklist to make BAF more resilient (works with/without Google), more consistent across field types, and more complete in HTML attribute + accessibility coverage.

---

## A) Autocomplete: provider model + graceful fallback (Google optional)

### A1. Define the product behavior (spec)
- [ ] Define “Autocomplete” as a **capability** with multiple providers:
  - Browser/HTML `autocomplete` tokens (no network)
  - Remote suggestion providers (Google Places, etc.)
  - Local/static datasets (countries/states/cities, etc.)
- [ ] Define global policy when a provider is unavailable (no API key, blocked script, CSP, network offline):
  - [ ] Must **not break the field**
  - [ ] Field remains fully usable as normal inputs/selects
  - [ ] Any “autocomplete UI” must hide/disable itself cleanly
  - [ ] Provide a non-invasive notice in admin (not on public form)

### A2. Provider abstraction
- [ ] Create an internal “autocomplete provider” interface/contract (PHP config + JS adapter) covering:
  - [ ] Enable/disable condition
  - [ ] Provider id string
  - [ ] Options schema (types, countries, minChars, debounce, maxResults)
  - [ ] Result schema (label/value + optional structured data)
- [ ] Ensure providers can be swapped per field:
  - [ ] `autocomplete_provider` accepts `google_places`, `none`, `static`, and future providers

### A2.1 Large-dataset selection UX (optional, accessible)
- [ ] Add a first-class **“type to search”** mode for fields with many options:
  - [ ] `select` / `multiselect` can be configured as:
    - [ ] plain select (default)
    - [ ] searchable select (type-to-filter)
    - [ ] combobox (ARIA combobox + listbox)
  - [ ] Must be fully keyboard accessible:
    - [ ] arrow keys navigate results
    - [ ] enter selects, escape closes
    - [ ] correct `aria-controls`, `aria-expanded`, `aria-activedescendant`
  - [ ] Must be screen reader friendly:
    - [ ] announce result counts, selection changes
    - [ ] preserve visible label + help text semantics
  - [ ] Must be optional per-field, and never required
- [ ] Ensure this integrates with cascading fields:
  - [ ] child fields can inherit “searchable” mode when option lists are large
  - [ ] loading/empty states are announced and non-blocking

### A3. Address field fallback (current Google Places)
- [ ] Verify current Address autocomplete behavior when Google key missing:
  - [ ] No JS errors
  - [ ] No broken markup
  - [ ] No “stuck loading” state
- [ ] Improve fallback UX:
  - [ ] If Google unavailable, keep Address as plain address inputs
  - [ ] Optionally show a subtle “manual entry” mode toggle only if we ever add combined single-line address search

### A4. Static dataset autocomplete (no Google)
- [ ] Add a **static suggestions provider** for:
  - [ ] Country
  - [ ] US state / CA province
  - [ ] Timezone
  - [ ] Language
- [ ] Decide which field types use it:
  - [ ] `select` / `multiselect` (local filtering)
  - [ ] “combobox” style input (if added) for large datasets

### A5. Geo cascading fields (no Google)
- [ ] Implement “geographic cascade” as first-class:
  - [ ] Country → State/Province → City (optional) → Postal code
- [ ] Design choices:
  - [ ] Provide built-in datasets for country + subdivisions
  - [ ] City datasets likely too large: decide between (a) user-provided dataset upload, (b) optional remote provider, (c) omit city
- [ ] UX requirements (optional but supported):
  - [ ] Each step can be configured as plain select vs searchable select vs combobox
  - [ ] If options exceed a threshold (configurable), recommend searchable mode in the builder
  - [ ] Cascades must remain usable with JS disabled (graceful degradation)
- [ ] Make cascades work in both:
  - [ ] Visual builder
  - [ ] BFL (`@cascade_from`, `@cascade_library` already exist — verify end-to-end)

### A6. Open-source provider options (optional)
- [ ] Evaluate adding a non-Google remote provider:
  - [ ] OpenStreetMap/Nominatim (rate limiting + ToS considerations)
  - [ ] Photon / Pelias (self-hosted)
- [ ] If adding any remote provider:
  - [ ] Make it opt-in
  - [ ] Add caching + debouncing
  - [ ] Ensure privacy + compliance messaging in docs

---

## B) HTML attributes + semantics coverage (audit + standardization)

### B1. Centralize attribute building
- [ ] Create/confirm a single canonical method in the field base class to build attributes:
  - [ ] `id`, `name`, `value`
  - [ ] `required`, `disabled`, `readonly`
  - [ ] `min`, `max`, `minlength`, `maxlength`, `step`, `pattern`
  - [ ] `autocomplete`, `inputmode`, `enterkeyhint`
  - [ ] `aria-*` wiring (describedby, invalid, required)
  - [ ] `data-*` hooks (validation triggers, conditional logic)
- [ ] Ensure every field type uses the shared method (reduce “one-off” attributes)

### B2. Attribute parity audit across field types
- [ ] Audit every `includes\fields\class-baf-field-*.php` for:
  - [ ] Missing support for common modifiers (min/max/step/pattern/autocomplete)
  - [ ] Inconsistent attribute names (`min_length` vs `minlength`, etc.)
  - [ ] Any attributes hardcoded instead of derived from config
- [ ] Confirm `BAF_BFL_Validator::$valid_modifiers` matches parser/compiler support:
  - [ ] If validator lists modifiers the parser doesn’t compile, fix mismatch

### B3. “Arbitrary attributes” support (advanced)
- [ ] Decide if BAF should allow a controlled escape hatch:
  - [ ] `@attr "data-foo" "bar"`
  - [ ] OR a JSON-ish `@attrs { ... }`
- [ ] Security rules:
  - [ ] Allowlist attributes; never allow event handlers (`on*`)
  - [ ] Sanitize URL-bearing attrs

### B4. Accessibility semantics checks
- [ ] Verify fieldset/legend coverage for all grouped controls:
  - [ ] radio/checkbox groups
  - [ ] likert, ranking, NPS, rating (if grouped)
  - [ ] any composite widgets
- [ ] Ensure each input has an accessible name:
  - [ ] `label for` + `id`
  - [ ] fallback to `aria-label` only when necessary
- [ ] Ensure error messages are connected via `aria-describedby` and `aria-invalid`.

---

## C) Field types: new additions + upgrades

### C1. Candidate new field types
- [ ] Searchable select (type-to-filter) mode for `select`/`multiselect` (optional, accessible)
- [ ] Combobox (accessible ARIA combobox + listbox) for very large datasets (optional)
- [ ] Address “single line search + expand to parts” (with provider + manual fallback)
- [ ] Currency input (number + currency formatting)
- [ ] Slider w/ numeric input pairing (range + number)
- [ ] OTP / verification code input (pattern + inputmode + maxlength)
- [ ] URL preview/link field (safe preview)
- [ ] Signature typed name alternative (accessible substitute)

### C2. Feature upgrades for existing fields
- [ ] Better multiselect UX (keyboard-first)
- [ ] Post tags autocomplete robustness (ensure ARIA listbox correctness)
- [ ] File upload enhancements:
  - [ ] accept types, max size, multiple
  - [ ] progress UI, server-side validation parity
- [ ] Repeater enhancements:
  - [ ] min/max rows
  - [ ] “copy previous row”

---

## D) BFL language + builder parity (follow-ups)

### D1. BFL modifiers support parity
- [ ] If `BAF_BFL_Validator` allows modifiers not implemented in parser (`pattern`, `autofocus`, `spellcheck`, `accept`, `capture`, `cols`):
  - [ ] Implement them in parser
  - [ ] Plumb through compiler
  - [ ] Apply in relevant field renderers

### D2. Choice blocks vs list ambiguity documentation
- [ ] Add explicit docs section: `[ ... ]` used both for blocks and for lists in certain modifiers.

### D3. Conditional logic expressiveness (optional)
- [ ] Add support for multi-rule conditions (`and/or`) if desired
- [ ] Add `else` blocks if desired

---

## E) Testing + tooling
- [ ] Add/extend parser tests for:
  - [ ] indentation blocks
  - [ ] `{}` blocks
  - [ ] `[]` blocks
  - [ ] `begin/end` blocks
  - [ ] nested columns + conditional combos
- [ ] Add field renderer snapshot tests (if test harness exists)
- [ ] Add JS unit tests for autocomplete providers + fallback
- [ ] Add conversion tests (schema-level) for field type changes:
  - [ ] any→any conversion matrix smoke tests (at least: text↔textarea, checkbox↔radio, select↔multiselect, date↔datetime, number↔range)
  - [ ] ensure conditional logic rewrite suggestions are correct

---

## F) Documentation updates (when implementing items above)
- [ ] Update `docs\user-guide\bfl-language-guide.md` with:
  - [ ] provider explanation + fallback narrative
  - [ ] geo cascade recipes
  - [ ] searchable select / combobox guidance + accessibility expectations
  - [ ] “field type conversion” section for BFL users (how to refactor safely)
- [ ] Update `docs\user-guide\field-types-guide.md` per field changes
- [ ] Update PRD to reflect provider model + offline behavior

---

## G) Visual Builder UX: “magical” flexibility (guided, safe)

### G0. Import/export: BFL + CSV → forms (guided)
- [ ] Import BFL to builder with a guided “review + fixups” step:
  - [ ] show parsed structure (pages/sections/fields)
  - [ ] flag unknown modifiers/field types and offer replacements
  - [ ] allow batch conversions (e.g., convert all `select` → searchable select)
- [ ] Export builder → BFL with stable formatting and minimal diffs
- [ ] CSV import to auto-build forms (guided wizard):
  - [ ] Accept common survey/CRM exports (column headers + sample rows)
  - [ ] Infer field types from sample data (opt-in suggestions)
  - [ ] Map columns → fields with preview and accessibility checks
  - [ ] Support lookup tables for options (separate CSV or inline delimited lists)
  - [ ] Support cascades from CSV (country/state) via declared relationships
- [ ] CSV export of form schema (for round-tripping) + version tagging

### G1. Field type conversion (guided)
- [ ] Allow changing a field’s type in the builder (e.g., checkbox group → radio, select → multiselect, text → textarea)
- [ ] **Scope: any → any** (as far as technically possible):
  - [ ] Allow converting *any* field type to *any other* field type in all tooling modes:
    - [ ] Visual Builder
    - [ ] BFL authoring (source transforms / refactors)
    - [ ] CSV import → form build
    - [ ] JSON export/import (if present) / internal schema migrations
- [ ] Implement a conversion mapper that preserves as many properties as possible:
  - [ ] label, description/help, required, visibility/admin-only
  - [ ] choices/options (when applicable)
  - [ ] default value (with best-effort conversion)
  - [ ] validation rules (min/max/length/pattern) when compatible
  - [ ] CSS/class and layout settings
- [ ] Conversion UI should be guided:
  - [ ] show what will be kept / changed / dropped
  - [ ] require confirmation when data loss is likely
- [ ] Provide a “conversion report” after the change:
  - [ ] list of migrated properties
  - [ ] list of dropped/changed properties
  - [ ] links to affected conditional logic rules

### G2. Conditional logic safety + auto-repair
- [ ] When a field type/id changes, run a conditional-logic integrity pass:
  - [ ] detect broken references (missing field id)
  - [ ] detect incompatible operators (e.g., numeric ops on text, contains on number)
- [ ] Provide builder warnings and suggested fixes:
  - [ ] auto-map old field id → new field id (when converting in-place)
  - [ ] offer to update operators to nearest compatible operator
  - [ ] highlight affected rules and allow quick review
- [ ] Never silently change meaning; always show diffs / confirmation

### G3. Usability wins (creative, accessible)
- [ ] “Recommend mode” in builder: suggest field types/settings based on label text (opt-in)
- [ ] Inline accessibility coach (opt-in): flags missing labels, poor help text, contrast issues, keyboard traps
- [ ] Live preview with screen-reader hints (opt-in): shows computed accessible name/description per field
- [ ] Undo/redo + version history for form edits
- [ ] Field templates/patterns (opt-in): Address block, Contact block, Consent block, Payment block
- [ ] Smart option management for big lists:
  - [ ] paste/import options
  - [ ] dedupe
  - [ ] sort
  - [ ] group into optgroups
