# QAF form language and the form model

QAF form language is how people write a QAF form by hand. Since the QAF form language 1 it is a readable
notation of the form model (`docs/form-model.md`) and nothing more:

- every model feature has a QAF form language spelling (the tables below);
- QAF form language to model and model to QAF form language round-trip exactly;
- QAF's PHP and QUILL's Python both check the result against the same schema.

The reader is `includes/model/class-qaf-bfl-reader.php` and the writer
`includes/model/class-qaf-bfl-writer.php`.

## Where QAF reads and writes the QAF form language

Every place the site reads or writes QAF form language uses the reader and the writer,
through `includes/model/class-qaf-bfl-service.php`:

| Where | What it does now |
| --- | --- |
| `POST qaf/v1/bfl/parse` (`bfl`) | Reads the QAF form language. The reply is `valid`, the `model`, the builder's shape written from it (`config`, `title`, `settings`; `config` is null when there are errors), and `errors` and `warnings`, each with a `line` and a `message`. Before 2026-11 the reply was the older parser's config on its own. |
| `POST qaf/v1/bfl/validate` (`bfl`) | New. The reader's problems, then the model's schema and rules as `model_errors` (JSON pointer paths), and `valid`. |
| `POST qaf/v1/bfl/format` (`bfl`) | New. The same QAF form language in canonical form, or null with the errors. |
| `POST qaf/v1/bfl/compile` (`model` or `config`) | Writes canonical QAF form language from a form model, or from the builder's shape (with the conversion `report`). Before 2026-11 it used the older compiler, which wrote a dialect without `bfl 1`. |
| `GET qaf/v1/forms/{id}` | Also returns the form's `model`. |
| Builder: QAF form language panel | Format Code rewrites the text in canonical form (`qaf_format_bfl`); Sync to Visual saves it as the form's model (`qaf_save_form_model`) and reloads the builder. Problems are listed with their line numbers, each a button that moves to its line. |
| Builder: Structure mode | Drawn from the model: sections, columns and repeaters hold their questions, and each condition is read as a sentence ("Shown when Type is Business"). |
| `qaf_parse_bfl`, `qaf_compile_bfl` (admin-ajax) | As `bfl/parse` and `bfl/compile`. |

The older parser, compiler, validator and formatter in `includes/bfl/` stay
only for reading forms written before the model: the older file importer
(`includes/import/`) and the older formatter still call them. The new reader
reads everything they read (see "Older the QAF form language" below), so nothing new should.

## What "round-trip exactly" means

- **Model to QAF form language to model:** always the same model.
- **QAF form language to model to the QAF form language:** QAF form language written by the writer (canonical the QAF form language) comes back
  byte for byte. Hand-written QAF form language comes back in canonical spelling: the same
  form, tidied. Comments are not part of the model and are not kept.

The pairs that prove it are in `tests/conformance/bfl/`.

## Canonical layout

```
bfl 1
form #club "Join the club"
  @description "..."
  @language "en"
  @submit_label "Join"

library #sizes
  + s|Small
  + m|Medium

page #about "About you"
  @when #type is not "person"
  radio #type "Joining as"
    @required
    + person|An individual
    + business|A business
  text #company "Company name"
    @when #type is "business" or #type is "charity"

rule require #phone
  @when #contact_by is "phone"
```

- The first line is `bfl` and the version. A reader refuses a newer version
  rather than guessing.
- Two spaces per level. Blank lines between top-level blocks.
- `#id` is left out where the reader would make up the same ID: `field_1`,
  `field_2` ... for items in order, `page_1` for the first page,
  `confirmation_1`, `notification_1`, `outcome_1`.
- A form with one page that has nothing but items has no page line.
- Text is in double quotes, escaped as JSON (non-ASCII written as itself).
  Numbers are written as JSON writes them, keeping `.0`. A true setting or
  prop is written `@name` alone; false is `@name false`.
- Lists and objects are written as compact JSON: `@rows ["Speed","Quality"]`.
- Expressions (`@when`, `@calculate`, `@smart_default`, `@value`) are written in
  canonical expression text and run to the end of the line.

## Spellings

### The form

| Model | the QAF form language |
| --- | --- |
| `version` | `bfl 1` (first line) |
| `id`, `title` | `form #id "Title"` |
| `description` | `@description "..."` |
| `language` | `@language "en"` |
| `settings.<name>` | `@<name> value` on the form (any modifier the form does not otherwise know) |
| `extensions` | `@extensions {"...": ...}` |
| `choice_libraries.<name>` | `library #name`, then choice lines |

### Pages, sections, columns

| Model | the QAF form language |
| --- | --- |
| page | `page #id "Title"`, then its items. The first page line takes the first page |
| page `description`, `when`, `props.<name>` | `@description`, `@when`, `@<name>` |
| section | `section #id "Label"`, then its items indented under it |
| section `help`, `when`, `props` | `@description`, `@when`, `@<name>` |
| empty section | `section #id "Label" {}`, or with modifiers inside `{ }` |
| columns | `columns #id 2`, `@when`, `@<prop>`, then one `column` per column |
| column `width` | `@width "1/2"` under `column` |

### Fields

`<type> #id "Label"`, where `<type>` is the model type (`image_choice`,
`typed_signature`, `post_title`, ...), then:

| Model | the QAF form language |
| --- | --- |
| `required` | `@required` (or `@required false`) |
| `placeholder` | `@placeholder "..."` |
| `help` | `@description "..."` |
| `default` | `@default value` (text, number, true/false, or a JSON list) |
| `smart_default` | `@smart_default today() + 30` |
| `mask` | `@mask "(999) 999-9999"` |
| `when` | `@when <expression>` |
| `calculation` | `@calculate <expression>` |
| `choices` | choice lines (below) |
| `choice_library` | `@library "countries"` |
| `cascade.from`, `.library`, `.empty_message`, `.loading_message` | `@cascade_from #country`, `@cascade_library "states"`, `@empty_message "..."`, `@loading_message "..."` |
| `validation.min`, `max`, `min_length`, `max_length`, `pattern`, `message` | `@min`, `@max`, `@minlength`, `@maxlength`, `@pattern`, `@error_message` |
| `validation.unique`, `unique_scope`, `unique_message` | `@unique`, `@unique_scope "form"`, `@unique_message "..."` |
| `scoring.correct` | `@correct ["paris"]` |
| `scoring.points`, `partial`, `case_sensitive`, `feedback_correct`, `feedback_incorrect`, `feedback_partial`, `explanation` | `@points 2`, `@partial`, `@case_sensitive`, `@feedback_correct "..."` and so on |
| `attributes.<name>` | `@attr "data-track" "signup"` |
| `props.<name>` | `@<name> value`: `@low_label "Not likely"`, `@rows ["Speed","Quality"]` |
| repeater `items` | the repeater's fields, indented under it after its modifiers |

### Choices

| Line | Choice |
| --- | --- |
| `+ Small` | value `small` (the label's slug), label `Small` |
| `+ s\|Small` | value `s`, label `Small` |
| `+ Small:10` | value `small`, label `Small`, price 10 (a colon not followed by a number is part of the label) |
| `+ "s" \| "Small" @price 10 @points 2 @image "..." @description "..." @selected` | any choice, with every extra |

The writer uses the shortest line that reads back to the same choice:

- `+ Small` when the value is the label's slug and there is nothing else;
- `+ Small:10` when, besides that, the choice has a price and nothing more
  (a price written with a decimal point keeps it: `+ Large:20.0`, because
  the model holds 20.0, not 20);
- `+ s|Small` when the value is not the slug and there is nothing else;
- the quoted form otherwise: a label with a colon or a bar, a value that is
  not the slug with a price, or any other extra.

`tests/conformance/bfl/canonical/priced-choices.qaf` holds every case.

### Rules, scoring, confirmations, notifications

```
rule set #fee
  @value 10
  @when #type is "charity"

scoring
  @show_score
  @pass_mark 8
  outcome #pass
    @when score >= 8
    @message "You passed."

confirmation #thanks
  @type "redirect"
  @url "https://example.org/thanks"
  @when #type is "person"

notification #office
  @to "office@example.org"
  @subject "New member"
  @message "{all_fields}"
```

A rule's action is `show`, `hide`, `require`, `optional`, `enable`, `disable`,
`set` or `skip_to`, followed by its targets. A confirmation's type is
`message` unless it says otherwise. Anything else under a confirmation or
notification is one of its props.

### Translations

```
translation "es"
  @title "Únase al club"
  page #about
    @title "Sobre usted"
  field #type
    @label "Se une como"
    @description "..."
    @placeholder "..."
    + person|Una persona
  confirmation #thanks
    @message "Gracias."
  outcome #pass
    @message "Aprobó."
  message #required "Este campo es obligatorio."
```

## Conditions as blocks

`@when` is the canonical spelling, but the older block forms still read:

```
if #type is "business" or #type is "charity"
  text #company "Company name"
else
  text #why "Why are you joining?"
```

Every item in an `if` block gets the condition (joined with `and` to its own
`@when`, and to any enclosing `if`); items in `else` get `not (...)`. All four
block styles work: indentation, `{ }`, `[ ]` and `begin ... end`.

## Older the QAF form language

Everything the older parser and the language guide accept still reads, with
the same meaning:

- the four block styles; modifiers inline, indented, or on the lines straight
  after a field without indentation;
- `dropdown`, `paragraph`, `url`, `checkboxes`, `file` and `savebutton`
  (written back as `select`, `textarea`, `website`, `checkbox`, `fileupload`,
  `save`); `divider` and `spacer` become an `html` field with `@element`;
- `@desc`, `@readonly`, `@class`, `@css`, `@admin`, `@admin-only`, `@hidden`,
  `@confirm`, `@allow-other`, `@cascadefrom` and the other older spellings;
- form modifiers `@ajax`, `@honeypot`, `@captcha "type"`, and the older
  compiler's `settings` block;
- `@smart_default "today+7"`, `"user:display_name"`, `"query:ref"`, `"geo"`;
- a library named after a choice field's label, as the language guide
  teaches: `select "Country" countries` is `@library "countries"`. Any word
  there is taken as the library's name, unless it starts another item;
- the eight single-test conditions (`is`, `contains`, `starts`, `ends`,
  `greater`, `less`, `empty`, `filled`), now inside the full expression language;
- flat sections: a section with nothing indented under it takes the items
  after it, up to the next section;
- a `checkbox` with no choices is one box: it gets the choice `yes|Yes`, which
  is what `if #newsletter is "yes"` always assumed.

Where the older parser was wrong, the new reader does what the older parser's
own tests expect: it keeps the first choice of an indented list, reads
indented columns and conditions, and gives the first `page` line the first page.
