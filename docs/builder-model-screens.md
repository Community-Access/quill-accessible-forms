# Plan: builder screens for rules, outcomes, translations and choice libraries

Status: planned, not built (2026-11). The form model holds all four, BFL
writes and reads them, QUILL edits them, and the visual builder keeps them
when it saves (`BAF_Form::apply_builder_config`). What is missing is a way
to edit them in BAF's own admin without writing BFL. This is the plan for
that, so it can be built in small, separately reviewable steps.

## The shape every screen shares

All four are lists of objects in the model. Each gets the same accessible
pattern, so learning one teaches the others:

- **A tab in the builder's mode switcher**, after Visual, Structure and BFL:
  "Rules", "Quiz outcomes", "Translations", "Choice libraries". The tabs
  follow the existing `role="tablist"` pattern in
  `admin/partials/form-builder.php`, with arrow keys between tabs.
- **A heading, a short paragraph, and a table** (`<table>` with `<caption>`,
  `<th scope="col">`), one row per object, the first cell a row header.
  Each row says the object in a sentence: a rule reads "Require Phone when
  Contact me by is Phone", from `BAF_Expression::to_sentence()`.
- **Add, Edit, Move up, Move down, Delete** buttons on each row, each with an
  accessible name that includes the row ("Edit rule 3: Require Phone when
  ..."). Move announces the new position ("Rule 3 is now rule 2.") in the
  screen's status region; Delete asks first, in an `alertdialog`, and focus
  returns to the row that took its place, or to Add.
- **Edit in a modal dialog** (`role="dialog"`, `aria-modal`, focus trapped,
  Escape closes, focus returns to the button that opened it), with real
  `<label>`s, help joined by `aria-describedby`, and errors joined to their
  field and listed at the top of the dialog as links to the fields.
- **Expressions are typed, checked as you go, and read back.** An
  expression field is a text box; under it, a status line gives either the
  sentence ("Shown when Type is Business or Type is Charity") or the parse
  error with its position ("Expected a value after is, at character 9").
  The check runs in the browser with `public/js/baf-expression.js`, the same
  evaluator the published form uses, so nothing needs a round trip. A
  "Fields" button inserts `#id` from a searchable list of the form's fields
  by label, for people who do not remember IDs.
- **Saving** goes through the existing `baf_get_form_model` and
  `baf_save_form_model` AJAX actions: the screen edits the model JSON and
  saves it whole, so `BAF_Form_Model::validate()` (schema, then the rules
  beyond it) checks it. Problems come back with JSON pointer paths, which
  the screen turns into "Rule 3, condition: ..." and focuses.
- **Nothing is lost.** A field type, action or language the screen does not
  know is shown read-only and saved back unchanged.

## The four screens

### Rules (`model.rules`)

Columns: number, the rule as a sentence, targets. The dialog has:

- **Action**, a select: show, hide, require, optional, enable, disable, set,
  skip to.
- **Targets**: a list of check boxes of the form's items by label (pages
  only, for skip to), with a filter box.
- **When**, an expression field.
- **Value**, an expression field, shown only for set.

### Quiz outcomes (`model.scoring`)

Above the table: "Show the score" (check box) and "Pass mark" (number).
Columns: number, when, message. The dialog has **ID**, **When** (an
expression field; `score` is offered in the Fields list) and **Message**.
A "Try it" region lets the author enter a score and hear which outcome
would be chosen, using `BAFFormModel.outcome()`.

Per-question scoring (`scoring.correct`, points, partial, feedback) belongs
in the field's own settings panel in the Visual mode, as a "Scoring"
section shown for scored types (quiz, radio, checkbox, select, text).

### Translations (`model.translations`)

A table of languages: code, name, how much is translated ("41 of 52
texts"). The language dialog is a grid built as a table, one row per text
(form title, each page title and description, each field's label, help,
placeholder and choices, each confirmation and outcome message, the
built-in messages), with the form's own text in one column and a labelled
text box per row in the other. A filter shows only untranslated rows. Empty
means "falls back to the form's own text", which the row says.

### Choice libraries (`model.choice_libraries`)

A table of the form's own libraries: name, number of choices, which fields
use it. The dialog edits a library as a list of choices (value, label,
price, points) with the same add, move and delete buttons, plus "Paste
choices", which takes one choice per line in BFL's choice-line spellings
(`+ Small`, `+ s|Small`, `+ Small:10`) through the BFL reader. BAF's
built-in libraries (countries, months...) are listed read-only, with a
"Copy into this form" button for when the form needs its own version.

## Steps, each its own change with tests

1. The shared pieces: the tab, the table and row buttons, the dialog, the
   expression field (with its live check and sentence) and the Fields list,
   as one script (`admin/js/baf-model-editor.js`) that takes a description
   of the object's fields. Tests: a node test for the expression field's
   messages; `AccessibleScreensTest` extended to the new partial (labels,
   `aria-describedby`, headings, status regions).
2. Rules, on that.
3. Choice libraries.
4. Quiz outcomes, and the Scoring section in the field settings panel.
5. Translations.
6. Each screen checked by hand with NVDA and JAWS in Chrome and Firefox,
   keyboard only, at 200 percent zoom; findings go to the user guide.

## Done when

- Every object in the model that the builder could not edit can be added,
  edited, moved and deleted from the keyboard alone, and saved.
- A model edited on these screens and saved is the same model BFL would
  give for the same form (checked by writing it as BFL and reading it back).
- No screen announces what the screen reader already says (titles, focus
  moves, control names); each announces only results it alone knows.
