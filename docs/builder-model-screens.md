# Plan: builder screens for rules, outcomes, translations and choice libraries

Status: built (2026-11), steps 1 to 5 below. Not yet checked by hand with
a screen reader (step 6). The form model holds
all four, QAF form language writes and reads them, QUILL edits them, and the visual
builder keeps them when it saves (`QAF_Form::apply_builder_config`). This
was the plan for editing them in QAF's own admin without writing the QAF form language.

Where it is:

- `admin/partials/model-screens.php`: the tabs, panels and dialogs.
- `admin/js/qaf-model-editor-core.js`: the logic, with no DOM (the
  expression check, rule sentences, pointer paths as words, translation
  rows); `tests/js/model-editor.test.js` tests it.
- `admin/js/qaf-model-editor.js`: the shared pieces (tabs, saving, the
  list screen, dialogs, errors, expression fields).
- `admin/js/qaf-model-screens.js`: the four screens.
- `admin/model-editor-i18n.php`: every string the scripts use, checked
  against them by `AccessibleScreensTest`.
- New AJAX actions: `qaf_read_choice_lines` (Paste choices, through
  `QAF_BFL_Service::read_choice_lines`) and `qaf_get_choice_library`.
- The per-question Scoring section: `includes/model/class-qaf-field-scoring-panel.php`
  draws it on the server from the question in the stored model (new AJAX
  `qaf_get_field_scoring`), and `admin/js/qaf-field-scoring.js` saves it with
  `QAFModelEditorCore.applyScoring`. A scored question's card (quiz, radio,
  checkbox, select, multiselect, text) has a Scoring button that opens it in
  the field settings panel.
- The Visual mode's field cards now work, each on the model:
  `includes/model/class-qaf-builder-field-ops.php` adds, copies, deletes and
  reorders questions and saves a question's settings (label, help,
  placeholder, required), each changing only its own question or the order,
  saved through `QAF_Form::set_model()` so `validate()` checks it. The AJAX
  actions the builder script always called now exist: `qaf_add_field`,
  `qaf_duplicate_field`, `qaf_delete_field`, `qaf_update_field_order`,
  `qaf_save_field_settings`, and `qaf_get_field_settings` with a `field_id`.
  A new question's card comes from the server, so a scored one has its
  Scoring button at once. Moving a question onto another page or section is
  refused and said; that is done in Structure mode.
- The canvas is drawn from the model (`QAF_Form_Builder::render_canvas`), so
  questions inside sections and columns have cards with the same actions,
  each list labelled by its page, section or column; every action answers
  with the canvas drawn again, so the page always shows the saved model.
  Drag and drop is saved through the same order check as the move buttons.
- A form not made yet is made, as a draft, by its first question or by Save
  Draft; the page takes its new ID and address without a reload.
- Add Field (F, while focus is in the canvas) opens a modal "Add a question"
  dialog with the registry's types by category and a filter. Convert opens
  a modal dialog that says what would change (`qaf_convert_question` with
  `dry_run`) before it changes the type on the model
  (`QAF_Builder_Field_Ops::convert`), including how many conditions read the
  question when its answers change kind. Structure mode's own Convert
  (`qaf_convert_field`) now converts on the model too. Save Draft and
  Publish (`qaf_save_form_status`) save the title and the status and say so.

Differences from the plan as written: a save reads the stored model again
and replaces only the screen's own part, so a visual-builder save made in
between is not undone; the quiz "Try it" evaluates the outcomes against
the typed score directly, since `QAFFormModel.outcome()` works the score
out from answers; translations and libraries have no Move buttons, since
their order means nothing; renaming a library or an outcome carries the
new name to the questions and translations that use it.

## The shape every screen shares

All four are lists of objects in the model. Each gets the same accessible
pattern, so learning one teaches the others:

- **A tab in the builder's mode switcher**, after Visual, Structure and the QAF form language:
  "Rules", "Quiz outcomes", "Translations", "Choice libraries". The tabs
  follow the existing `role="tablist"` pattern in
  `admin/partials/form-builder.php`, with arrow keys between tabs.
- **A heading, a short paragraph, and a table** (`<table>` with `<caption>`,
  `<th scope="col">`), one row per object, the first cell a row header.
  Each row says the object in a sentence: a rule reads "Require Phone when
  Contact me by is Phone", from `QAF_Expression::to_sentence()`.
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
  The check runs in the browser with `public/js/qaf-expression.js`, the same
  evaluator the published form uses, so nothing needs a round trip. A
  "Fields" button inserts `#id` from a searchable list of the form's fields
  by label, for people who do not remember IDs.
- **Saving** goes through the existing `qaf_get_form_model` and
  `qaf_save_form_model` AJAX actions: the screen edits the model JSON and
  saves it whole, so `QAF_Form_Model::validate()` (schema, then the rules
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
would be chosen, using `QAFFormModel.outcome()`.

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
choices", which takes one choice per line in the QAF form language's choice-line spellings
(`+ Small`, `+ s|Small`, `+ Small:10`) through the QAF form language reader. QAF's
built-in libraries (countries, months...) are listed read-only, with a
"Copy into this form" button for when the form needs its own version.

## Steps, each its own change with tests

1. The shared pieces: the tab, the table and row buttons, the dialog, the
   expression field (with its live check and sentence) and the Fields list,
   as one script (`admin/js/qaf-model-editor.js`) that takes a description
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
- A model edited on these screens and saved is the same model QAF form language would
  give for the same form (checked by writing it as QAF form language and reading it back).
- No screen announces what the screen reader already says (titles, focus
  moves, control names); each announces only results it alone knows.
