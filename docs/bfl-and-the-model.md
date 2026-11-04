# BFL and the form model

BFL is how people write a BAF form by hand. Since BFL 1 it is a readable
notation of the form model (`docs/form-model.md`) and nothing more:

- every model feature has a BFL spelling (the tables below);
- BFL to model and model to BFL round-trip exactly;
- BAF's PHP and QUILL's Python both check the result against the same schema.

The reader is `includes/model/class-baf-bfl-reader.php` and the writer
`includes/model/class-baf-bfl-writer.php`. The older parser in `includes/bfl/`
is unchanged, so nothing that calls it behaves differently.

## What "round-trip exactly" means

- **Model to BFL to model:** always the same model.
- **BFL to model to BFL:** BFL written by the writer (canonical BFL) comes back
  byte for byte. Hand-written BFL comes back in canonical spelling: the same
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

| Model | BFL |
| --- | --- |
| `version` | `bfl 1` (first line) |
| `id`, `title` | `form #id "Title"` |
| `description` | `@description "..."` |
| `language` | `@language "en"` |
| `settings.<name>` | `@<name> value` on the form (any modifier the form does not otherwise know) |
| `extensions` | `@extensions {"...": ...}` |
| `choice_libraries.<name>` | `library #name`, then choice lines |

### Pages, sections, columns

| Model | BFL |
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

| Model | BFL |
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
| `+ Small:10` | value `small`, label `Small`, price 10 (older spelling; a colon not followed by a number is part of the label) |
| `+ "s" \| "Small" @price 10 @points 2 @image "..." @description "..." @selected` | any choice, with every extra |

The writer uses the shortest line that reads back to the same choice.

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

## Older BFL

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
- the eight single-test conditions (`is`, `contains`, `starts`, `ends`,
  `greater`, `less`, `empty`, `filled`), now inside the full expression language;
- flat sections: a section with nothing indented under it takes the items
  after it, up to the next section;
- a `checkbox` with no choices is one box: it gets the choice `yes|Yes`, which
  is what `if #newsletter is "yes"` always assumed.

Where the older parser was wrong, the new reader does what the older parser's
own tests expect: it keeps the first choice of an indented list, reads
indented columns and conditions, and gives the first `page` line the first page.
