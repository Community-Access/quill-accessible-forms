# Changelog

All notable changes to QUILL Accessible Forms. Newest first.

## Unreleased

### Added

- Preview: the builder's Preview link (shown once the form is made) opens
  the form as a visitor sees it, on its own admin screen, in a new tab. It
  uses the public renderer and page script; Submit checks the answers and
  says "nothing was sent", and the form posts nowhere and carries no token,
  so nothing can be saved even without the script.
- Canvas actions for sections and columns: Move up, Move down, Copy and
  Remove on every section and set of columns; Add column on a set; Move
  left, Move right and Remove column on each column. Add Section (with a
  labelled heading box) and Add Two Columns beside Add Field. Removing a
  section or a column never deletes a question: they stay in its place,
  and keep a removed section's condition. Every action is announced and
  focus stays with what was acted on. New AJAX action `qaf_layout_action`;
  model operations in `QAF_Builder_Layout_Ops`.
- Accessibility Coach in the builder: Check Accessibility checks the saved
  form model (`QAF_Accessibility_Coach::analyze_model()`), lists each
  finding with the question it concerns, and gives each a Go to button that
  moves focus to the question's card (a repeater's own question goes to the
  repeater's card). The model's own validation problems are included,
  joined to their question. New AJAX action `qaf_coach_check`.
- Convert rewrites conditions: when a question's answers change kind,
  conditions, calculations, rules and quiz outcomes that compared it with
  its old values are rewritten where certain and flagged, with a reason,
  where not (`QAF_Condition_Rewriter`). Convert's preview says both.
- Repeater cards: each entry of a repeating group is a list item holding a
  group named by the repeater's legend and the entry's title. Add, Remove,
  Copy and move buttons work (they had no script before), announce what
  they did, and move focus sensibly; limits are announced and the buttons
  stay reachable with aria-disabled.
- Imports never bring entries: an export that carries entries has them
  stripped before mapping, and the import report says how many were left
  behind.

### Fixed

- Field input names: text, email and textarea posted `qaf_fields[<id>]`
  while every other field posted `qaf_field_<id>`. Every field now posts
  `qaf_field_<id>`. The submission handler reads that, the page script's
  `fields[<id>]`, and the old `qaf_fields[<id>]` (for pages rendered before
  the change), so a form posted without the page script now submits too.
  Email confirmation reads both names.
- The page script dropped every value of a `qaf_field_<id>[]` input (check
  boxes, multiselect), because its name pattern did not allow `[]`.
- Rich text never sent what was typed: the editing area is now copied into
  the field's textarea as it changes and before the answers are sent, the
  toolbar acts on it, its label carries the ID its aria-labelledby names,
  and the hidden textarea is out of the tab order.
- A repeater whose value was empty rendered one row instead of its default
  number.
- Inside a repeater, text, email and textarea sub-questions kept their old
  name instead of the row's, because the repeater rewrote only
  `qaf_field_<id>`.
- Appointment validation raised "Undefined array key 1" for a value
  without a time.
- `phpunit.xml` moved from the deprecated `<filter><whitelist>` to
  `<coverage><include>`, so PHPUnit no longer warns about the schema.
