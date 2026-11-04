# The BAF form model

A BAF form is one JSON document. That document is the definition of the form;
everything else is a view of it:

- BAF's visual builder edits it;
- BFL is a readable notation of it (`docs/bfl-and-the-model.md`);
- QUILL's form windows read and write it.

Every feature exists in the model first, so no view can fall behind another.

- **Schema:** `schema/form.schema.json` (JSON Schema 2020-12).
- **Expressions:** `docs/expression-language.md`.
- **PHP:** `includes/model/` (`BAF_Form_Model::validate()` checks the schema
  and the rules below it).
- **Shared tests:** `tests/conformance/` (QUILL's Python reader runs the same files).

## A small example

```json
{
  "version": 1,
  "title": "Join the club",
  "language": "en",
  "settings": { "submit_label": "Join" },
  "pages": [
    {
      "id": "page_1",
      "items": [
        { "id": "type", "type": "radio", "label": "Joining as", "required": true,
          "choices": [
            { "value": "person", "label": "An individual" },
            { "value": "business", "label": "A business" },
            { "value": "charity", "label": "A charity" } ] },
        { "id": "company", "type": "text", "label": "Company name",
          "when": "#type is one of [\"business\", \"charity\"]" },
        { "id": "email", "type": "email", "label": "Email", "required": true,
          "validation": { "unique": true, "unique_scope": "form" } }
      ]
    }
  ],
  "confirmations": [ { "id": "thanks", "type": "message", "message": "Welcome aboard." } ]
}
```

## The document

| Property | Required | Meaning |
| --- | --- | --- |
| `version` | yes | The model version the form was written for. This document describes version 1. |
| `title` | yes | The form's name. |
| `pages` | yes | One or more pages, in order. A one-page form has one page. |
| `id` | | A short ID for the form. |
| `description` | | Text shown above the form. |
| `language` | | The language the labels are written in (BCP 47, such as `en` or `pt-BR`). |
| `settings` | | Form behaviour (below). |
| `rules` | | Show, hide, require and set rules that act on several items. |
| `choice_libraries` | | Choice lists defined by this form, by name. |
| `scoring` | | Quiz outcomes and how the score is shown. |
| `confirmations` | | What a person sees after sending the form. |
| `notifications` | | Emails sent when the form is sent. |
| `translations` | | The form's text in other languages. |
| `extensions` | | Data owned by another program. Kept and sent back unchanged. |

Nothing else is allowed at the top level, so a typo is an error rather than a
silent loss.

**IDs** (forms, pages, items, confirmations, notifications, outcomes) are
letters, digits and underscores, not starting with a digit. Item and page IDs
are unique across the whole form, because expressions name them (`#email`).

## Pages

```json
{ "id": "page_2", "title": "About you", "description": "...", "when": "#type is \"person\"",
  "items": [ ... ], "props": { "next_button_text": "Continue" } }
```

A page with `when` is skipped when its condition does not hold. `props` holds
the page settings of BAF's page field (`next_button_text`, `prev_button_text`,
`show_progress`, `progress_style`, `validate_on_next`).

## Items: fields, sections and columns

Each page holds a list of **items**. An item is a field, a section or a columns layout.

### Sections

```json
{ "type": "section", "id": "contact", "label": "How to reach you", "help": "...",
  "when": "#wants_contact is true", "items": [ ... ], "props": { "collapsible": true } }
```

A section groups items under a heading. A section with `when` hides
everything in it. Sections may contain sections.

### Columns

```json
{ "type": "columns", "id": "names", "columns": [
    { "width": "1/2", "items": [ ... ] },
    { "width": "1/2", "items": [ ... ] } ] }
```

### Fields

| Property | Meaning |
| --- | --- |
| `id`, `type` | Required. `type` is one of the 64 field types below. |
| `label` | What the person is asked. |
| `help` | Help text, announced with the field. |
| `placeholder` | Example text inside an empty box. |
| `required` | `true` when an answer is needed. |
| `default` | A fixed starting value: text, a number, true or false, or a list for several choices. |
| `smart_default` | An expression for a starting value worked out when the form opens: `today() + 30`, `user("email")`, `query("ref")`, `geo()`. |
| `mask` | An input mask: `9` a digit, `a` a letter, `*` either; anything else is typed for the person. `(999) 999-9999`. |
| `when` | An expression; the field is shown only when it holds. |
| `calculation` | An expression for the field's value (calculation, total, price and quantity fields). |
| `choices` | The answers to choose from (below). |
| `choice_library` | The name of a choice list: BAF's built-in libraries (`countries`, `us_states`, ...) or one in `choice_libraries`. |
| `cascade` | This list depends on another field: `{ "from": "country", "library": "states", "empty_message": "...", "loading_message": "..." }`. |
| `validation` | `min`, `max` (numbers, or dates for date fields), `min_length`, `max_length`, `pattern` (a regular expression), `message` (the error to show), `unique`, `unique_scope` (`form` or `global`), `unique_message`. |
| `scoring` | Quiz scoring (below). |
| `attributes` | Extra HTML attributes. Only `data-*`, `aria-*` and a short safe list (`title`, `lang`, `dir`, `role`, `tabindex`, ...) are allowed; event handlers never are. |
| `items` | A repeater's own fields. Only a repeater has them. |
| `props` | Settings particular to the field type, using the field class's own setting names (a matrix's `rows` and `columns`, an NPS field's `low_label`). |

`props` never uses the names in the table above, nor BFL's older spellings of
them (`readonly`, `class`, `css`, `admin`, `hidden`, `confirm`, `allowother`),
nor the names BAF's stored field settings use for the same things
(`default_value`, `input_mask`, `custom_attributes`, `conditional_logic`,
`formula`, `correct_answers`, `options`, ...; the full list is in the schema).
That is what lets every prop be written in BFL as `@name value`, and stored in
BAF's builder format, without ambiguity.

### The field types

| Group | Types |
| --- | --- |
| Text and numbers | `text`, `textarea`, `number`, `email`, `phone`, `website`, `hidden`, `password`, `color`, `range`, `slider`, `currency`, `otp`, `toggle` |
| Choices | `select`, `multiselect`, `radio`, `checkbox`, `consent`, `image_choice`, `likert`, `matrix`, `ranking`, `rating`, `nps`, `quiz` |
| Dates and times | `date`, `time`, `datetime`, `daterange`, `appointment` |
| Advanced | `name`, `address`, `fileupload`, `signature`, `typed_signature`, `rich_text`, `map`, `social`, `table`, `captcha`, `repeater`, `calculation` |
| Payments and pricing | `payment`, `product`, `quantity`, `price`, `total`, `coupon` |
| Form helpers | `html`, `progress`, `summary`, `submit`, `save` |
| WordPress posts | `post_title`, `post_content`, `post_excerpt`, `post_category`, `post_tags`, `post_image`, `post_custom` |
| WordPress users | `user_login`, `user_email`, `user_password` |

What each type needs beyond `id` and `type`:

- `select`, `multiselect`, `radio`, `checkbox`, `ranking`, `image_choice` and
  `quiz` need `choices`, a `choice_library` or a `cascade`.
- `calculation` needs `calculation`.
- `quiz` needs `scoring`.
- `matrix` needs `props.rows` and `props.columns`; `likert` needs `props.rows`.
- Only `repeater` has `items`.

The `save` field is save and resume: `props` holds its settings (`button_text`,
`email_message`, ...), and the form setting `save_continue_enabled` turns it on.

## Choices and choice libraries

```json
{ "value": "large", "label": "Large", "price": 12.5, "image": "https://...", "points": 2,
  "description": "...", "selected": true }
```

`value` is what is stored, `label` what is shown. `price` is for product and
pricing fields, `image` for image choice, `points` for scored questions
(personality quizzes, weighted surveys), `selected` marks a default choice.

A form can define its own lists in `choice_libraries` and use them from any
field with `choice_library`. A name not defined there is one of BAF's
built-in libraries (`docs/CHOICE-LIBRARIES.md`).

## Rules, calculations and conditions

Most conditions live on the item itself (`when`). `rules` are for anything
else:

```json
{ "action": "require", "targets": ["phone"], "when": "#contact_by is \"phone\"" }
{ "action": "set", "targets": ["discount"], "when": "#members > 10", "value": "#total * 0.1" }
{ "action": "skip_to", "targets": ["page_4"], "when": "#eligible is false" }
```

Actions: `show`, `hide`, `require`, `optional`, `enable`, `disable`, `set`
(needs `value`), `skip_to` (its target is a page). An item is shown when its
own `when`, its sections' and its page's all hold; a `hide` rule that holds
hides it, and a `show` rule that does not hold hides it.

Calculations are expressions over field references: `#qty * #price`,
`sum(#a, #b)`, `round(#total * 1.08, 2)`.

## Scoring

A scored question has:

```json
"scoring": { "correct": ["paris"], "points": 2, "partial": false, "case_sensitive": false,
             "feedback_correct": "Right.", "feedback_incorrect": "Not quite.", "explanation": "..." }
```

It earns `points` (1 when not given) when the answer matches the correct
answers exactly; with several correct answers and `partial`, it earns a share
for each correct one chosen, and nothing if any wrong one is chosen. Text
answers ignore case and surrounding spaces unless `case_sensitive`. Choices
with `points` add their points when chosen. The form's score is the total of
every field.

The form's `scoring` names the outcomes; the first whose `when` holds is shown:

```json
"scoring": { "show_score": true, "pass_mark": 8, "outcomes": [
  { "id": "pass", "when": "score >= 8", "message": "You passed." },
  { "id": "fail", "when": "score < 8", "message": "Have another go." } ] }
```

## Translations

One form, several languages. `language` is the language the form is written
in; `translations` gives the others, side by side:

```json
"translations": {
  "es": {
    "title": "Únase al club",
    "pages":  { "page_1": { "title": "Sobre usted" } },
    "fields": { "type": { "label": "Se une como", "help": "...", "placeholder": "...",
                          "choices": { "person": "Una persona", "business": "Una empresa" } } },
    "confirmations": { "thanks": { "message": "Bienvenido." } },
    "outcomes": { "pass": { "message": "Aprobó." } },
    "messages": { "required": "Este campo es obligatorio." }
  }
}
```

A missing translation falls back to the form's own text. `messages` holds the
form's built-in messages (`required`, `invalid_email`, `submit`, ...), named
by BAF's message keys.

## Settings

`settings` holds BAF's form settings under their own names
(`BAF_Form::get_default_settings()`): `ajax_submission`, `enable_honeypot`,
`enable_captcha`, `captcha_type`, `save_continue_enabled`,
`rate_limit_enabled`, `validation_mode`, `progress_indicator`, the schedule
and entry-limit settings, and so on, plus `submit_label`. A setting the
model does not list is kept as it is. Only settings that differ from BAF's
defaults need to be written.

## Confirmations and notifications

```json
{ "id": "thanks", "type": "message", "message": "Thank you.", "when": "#type is \"person\"" }
{ "id": "to_page", "type": "page", "page_id": 42 }
{ "id": "away", "type": "redirect", "url": "https://example.org/thanks" }
{ "id": "admin", "to": "office@example.org", "subject": "New member", "message": "{all_fields}",
  "when": "#type is \"business\"" }
```

The first confirmation whose `when` holds (or that has none) is used. Every
notification whose `when` holds is sent.

## Objects and lists

`settings`, `props`, `attributes`, `translations`, `choice_libraries` and
the other objects in the model are JSON objects: write an empty one as `{}`.
PHP decodes `{}` and `[]` to the same thing, so BAF also accepts an empty
`[]` where an object belongs; QUILL rejects it, as the schema says. BAF's own
writer always writes `{}`, so this only arises in a model written by hand.

## Rules beyond the schema

`BAF_Form_Model::validate()` also checks what a JSON Schema cannot say:

- every page and item ID is unique, and confirmation, notification and outcome IDs are unique among themselves;
- every expression parses, and every `#id` it names exists;
- a cascade's `from` and a rule's targets exist (a `skip_to` target is a page);
- choice values within one field are unique, and a quiz's correct answers are among its choices;
- a translation names only pages and items that exist;
- a page, confirmation or notification prop does not reuse one of that object's own property names.

## Versions

- Every form carries `version`. This is version 1.
- A reader that meets a newer version than it knows refuses the form rather
  than guessing, and says which version it needs.
- A change that only adds optional properties, field types or functions keeps
  the version. Anything that changes the meaning of an existing form makes a
  new version, with a written migration from the one before.
- A BFL file carries its version on its first line: `bfl 1`.

## Migrating the forms BAF stores today

Until now BAF stored a form as columns of the `baf_forms` table: `config`
(a flat list of fields, each naming its page, plus a list of pages),
`settings`, `confirmations`, `notifications` and `bfl_source`. Those forms are
not broken and do not need re-saving. `BAF_Form_Model_Converter::from_legacy()`
reads them into the model, and `BAF_Form::get_model()` uses it whenever a form
has no stored model yet. What it does:

1. **Pages.** `config.pages` become the pages, and each field goes to the page
   its `page` names (to the first page when that page does not exist; the
   report says so). A form with no pages gets one, `page_1`.
2. **Sections.** A `section` field takes the fields after it, up to the next
   section on the same page, as its items.
3. **Columns.** A `columns` field's `columns` become columns with their own items.
4. **Repeaters.** A repeater's `fields` become its `items`.
5. **Type names.** `dropdown` becomes `select`, `paragraph` `textarea`,
   `url` `website`, `checkboxes` `checkbox`, `file` `fileupload`, `savebutton`
   `save`, `divider` and `spacer` an `html` field with `props.element`. A type
   the model does not know is kept under `extensions.baf_legacy` and listed in
   the report.
6. **Names.** `description` becomes `help`, `default_value` `default`,
   `input_mask` `mask`, `custom_attributes` `attributes`, and `formula`
   (calculation fields) `calculation`, rewritten in the expression language
   (`{qty}` becomes `#qty`).
7. **Validation.** `min`, `max`, `min_length`, `max_length`, `pattern`,
   `unique`, `unique_scope`, `unique_message` and `custom_error_message` move
   into `validation`, with numbers as numbers.
8. **Choices.** `choices` (a list, or `{"library": ...}`) and `choice_library`
   become `choices` or `choice_library`; `cascade_from`, `cascade_library`,
   `empty_message` and `loading_message` become `cascade`. Values become text.
9. **Conditions.** `conditional_logic` (`action`, `logic` all or any, and its
   rules) becomes one `when` expression: `equals` is `is`, `not_equals`
   `is not`, `contains`, `starts_with`, `ends_with`, `greater_than` `>`,
   `less_than` `<`, `is_empty`, `is_not_empty`; `any` joins with `or`, `all`
   with `and`; a `hide` action wraps the whole in `not (...)`.
10. **Quiz fields.** `correct_answers`, `points`, `partial_credit`,
    `case_sensitive`, `feedback_*` and `explanation` move into `scoring`.
11. **Smart defaults.** `today` becomes `today()`, `today+7` `today() + 7`,
    `user:display_name` `user("display_name")`, `query:ref` `query("ref")`,
    `geo` `geo()`; any other text becomes a quoted text value.
12. **Everything else** a field holds goes into `props` under its own name
    (`readonly` is `read_only`), except a name `props` cannot use, which goes
    to `extensions.baf_legacy` and is listed in the report.
13. **Settings, confirmations and notifications** are carried as they are;
    a confirmation or notification without an ID gets one (`confirmation_1`).

14. **Checkboxes without choices** are one box: they get the choice `yes|Yes`,
    which is what conditions such as `#agree is "yes"` always assumed.
15. **Builder defaults.** The settings every field class starts with (`size`
    `medium`, `visibility` `visible`, empty ARIA overrides and so on) say
    nothing and are not carried into `props`; nor are empty settings.

Every step that could not carry something over says so in the import report
the converter returns. Nothing is dropped without being listed.

## On a published form

A form saved with a model runs the model on the page, with the same rules the
server applies: `public/js/baf-expression.js` is a copy of the expression
language and `public/js/baf-form-model.js` of the model's runtime, and both
run the conformance suite (`node tests/js/run-conformance.js`).

- **What is shown:** every item's own condition, its sections' and its
  page's, and the show and hide rules, exactly as `is_shown()`. A hidden
  question is disabled, so it is neither sent nor checked. When questions
  appear or disappear after an answer, the form's live region says how many
  ("1 more question shown."), because nothing else tells a screen-reader user.
- **Rules:** `require` and `optional` set the field's required state;
  `enable` and `disable` its enabled state; `set` writes its value;
  `skip_to` sends Next to the page it names. A page whose condition does not
  hold is passed by, and Previous goes back the way the visitor came.
- **Calculations** are worked out as the visitor types, and **smart
  defaults** fill empty fields when the form opens (`today()` is the site's
  date; `user()` gives only the facts the form's expressions ask for, about
  the signed-in visitor; `query()` reads the page's link).
- **Translations:** the form is drawn in the language asked for with
  `?baf_lang=es`, else the visitor's locale when the form has that
  translation, else its own. Labels, help, placeholders, choice labels, page
  titles, the title, the Submit button and the outcome message are translated.
- **Quiz outcomes are chosen on the server** when the form is sent, and the
  reply carries the outcome's message (and the score, with `show_score`).
  The page never receives correct answers, choice points, outcomes,
  confirmations, notifications or settings
  (`BAF_Form_Runtime::public_model()`), so a quiz's answers are not in its
  source and no address is. A condition on the page that uses `score`
  therefore sees 0; put score-based messages in outcomes.

A form saved before the model keeps the builder's per-field rules, run as
before.

## How BAF stores the model

The table does not change. A form's `config` column now holds:

- `form_model`: the model itself. This is the form's definition.
- `fields` and `pages`: the same form in the builder's flat shape, written
  from the model (`BAF_Form_Model_Converter::to_legacy`), so the visual
  builder and the renderer keep working unchanged. A section says how many
  items follow it (`model_items`); a condition the builder's rule list cannot
  say keeps its expression beside the rules.
- `model_extra`: what the builder has no place for (rules, scoring,
  translations, choice libraries, the form's language).
- `model_version`: marks the shape as written from a model.

`BAF_Form::get_model()` returns the stored model, or converts the builder's
fields for a form saved before the model. `set_model()` stores a model and
rewrites the builder's fields, settings, confirmations, notifications and BFL
source from it. When the visual builder saves, `apply_builder_config()` reads
its fields into the model and keeps what the builder cannot show from the
model as it was. `save()` gives a form saved before the model its model, when
it converts cleanly, and leaves the builder's fields as they are. A form with
a stored model keeps its IDs when it is duplicated or imported, because its
conditions and calculations name them.

Converting a model to the builder's shape and back gives the same model, and
the builder's shape written from a model converts back to the same shape;
`tests/conformance/builder/` holds the cases.
