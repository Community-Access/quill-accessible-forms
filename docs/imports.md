# Importing forms from other builders

BAF reads five other form builders' forms into its form model
(`docs/form-model.md`). Each import is a mapping to the model, and each
returns an import report: what came across changed, what did not come across
at all, and what to check. Nothing is dropped without being listed.

- **Code:** `includes/model/class-baf-importers.php`
  (`BAF_Importers::import( $source, $text )`).
- **On the site:** BAF Forms, Import and Export (below). The AJAX action
  `baf_import_form` behind it saves the result as a draft form and returns
  the report, both as data and as plain sentences, and the builder link.
- **Tests:** `tests/conformance/imports/`, one realistic export per source,
  with the model and report it must give.

| Source | What to import |
| --- | --- |
| `gravity-forms` | The JSON from Forms, Import/Export, Export Forms |
| `wpforms` | The JSON from WPForms, Tools, Export |
| `contact-form-7` | The form template (the Form tab's text), or JSON with `form`, `mail` and `messages` |
| `ninja-forms` | The `.nff` file from Ninja Forms, Import/Export |
| `google-forms` | The form resource from the Google Forms API (`forms.get`) |

## What every import does

- Field IDs come from the source: `field_3` for Gravity Forms field 3,
  `your_name` for Contact Form 7's `your-name`, `q_` and the question ID for
  Google Forms. A renamed ID is reported.
- Conditions become one expression, read as a sentence; a condition that
  names a field the form does not have is reported, and the rest of it kept.
- Merge tags in confirmations and notifications become BAF's `{name}` tags
  (`[your-name]`, `{Name:1}`, `{field_id="1"}` and `{field:email}` become
  `{your_name}`, `{field_1}`, `{field_1}`, `{email}`); one with no BAF
  equivalent is left as it is and reported.
- A choice field with no choices gets one, `yes|Yes`, and that is reported.
- A field without a label is carried over and listed under "Check": a
  screen reader can only announce its type, so it needs a label before the
  form is published.

## Gravity Forms

- Fields map one to one where BAF has the type: text, paragraph, drop down,
  multi select, number, checkboxes, radio buttons, hidden, HTML, section,
  name, date, time, phone, address, website, email, file upload, CAPTCHA, list
  (a table), consent, the post fields, product, quantity, option, shipping,
  total, credit card (payment), password, signature, username, quiz and the
  survey add-on's likert, rank and rating.
- Page breaks start pages, named from the form's page titles; a break's
  conditions become the page's condition, and its Next button text goes on
  the page before it.
- Sections hold the fields after them, up to the next section or page.
- A number with a calculation is a calculation field; `{Nights:11}` becomes
  `#field_11`.
- Prices such as `$45.00` become numbers; quiz answers marked correct become
  the question's correct answers, and answer weights its points.
- `{date_mdy}` and `{user:display_name}` defaults become smart defaults.
- Confirmations (message, page, redirect) and notifications carry over with
  their conditions. **Not carried:** coupon fields, notification routing,
  other merge-tag defaults, and add-on fields BAF has no type for.

## WPForms

- Fields map one to one where BAF has the type, including number slider,
  likert, net promoter score, rich text, entry preview (summary), GDPR
  checkbox (consent) and the payment fields (with prices).
- Page breaks start pages; a divider is a section that holds the fields
  after it.
- Conditions are groups joined with "or", each group's rules joined with
  "and"; a rule about a choice names the choice by its key, which becomes
  the choice's value.
- Choices with pictures become an image choice field. **Not carried:** the
  layout field (columns), and fields BAF has no type for.

## Contact Form 7

- Each form tag becomes a field: text, email, url, tel, number, range, date,
  textarea, select (with `multiple`, a multi select), checkbox, radio,
  acceptance (consent, with its text), quiz, file, hidden and submit.
- The label is the text inside the `<label>` around the tag, or the text just
  before it; "(required)" is taken off, and `*` makes the field required.
- `"Label|value"` choices keep both; `default:2` marks the second choice;
  `placeholder` makes the value a placeholder; `40x6`, `min:`, `max:`,
  `step:`, `maxlength:`, `class:`, `autocomplete:`, `filetypes:` and `limit:`
  are carried; `default:user_display_name` and `default:get` become smart
  defaults.
- Text between the tags becomes HTML blocks.
- The mail becomes a notification; the "sent" message becomes the
  confirmation. **Not carried:** quiz questions after the first (Contact
  Form 7 asks one at random), the other messages, additional settings,
  reCAPTCHA and tags from other plugins.

## Ninja Forms

- Fields map one to one where BAF has the type; first name, last name,
  address, city and post code are text fields with the right autocomplete;
  the country and US state lists use BAF's choice libraries.
- Choice `calc` values become choice points.
- Calculations become calculation fields (`{field:rooms}` becomes `#rooms`).
- Success message and redirect actions become confirmations; email actions
  become notifications. **Not carried:** the Conditional Logic add-on's
  conditions (its own format), the fields inside a repeater (not in the
  export), calculations using functions BAF does not have, and other actions.

## Google Forms

- Short answer and paragraph, multiple choice, checkboxes, drop-down, linear
  scale (a rating, or an NPS field for 0 to 10), rating, date, time, file
  upload and grids (a matrix) all map.
- "Other" becomes `allow_other`; shuffled options and rows are kept.
- Sections start pages. "Go to section based on answer" becomes a
  `skip_to` rule.
- Quiz grading becomes each question's scoring (correct answers, points,
  feedback), and a quiz form shows its score.
- Text, image and video items become HTML; an image with no description is
  reported, so it gets one.
- `BAF_Importers::fetch_google_form( $form_id, $access_token )` fetches the
  form from the API. It needs an OAuth access token with the
  `forms.body.readonly` scope; BAF does not store it. **Not carried:** "submit
  form" and "restart form" branching, and Google's own email collection.

## The Import and Export screen

BAF Forms, Import and Export (`admin/partials/import-export.php`,
`admin/js/baf-import-export.js`) does both directions, from the keyboard
alone:

- **Import:** a group of radio buttons for the builder, each with a line on
  how to get its export; a labelled text box for the export, and a file
  chooser that puts the file's text in the box to check first; for Google
  Forms, the form's ID and an access token instead (used once, not stored).
  An empty box is an error joined to the box (`aria-describedby`,
  `aria-invalid`), and focus returns to it.
- **Export:** a form, a builder, Export; then the export in a read-only
  text box, with Download and Copy buttons.
- **The result** is said in the section's status region ("Imported 18 items
  from Gravity Forms as a draft form. 2 changed, 3 not carried over. The
  report follows the Import button.") and the report follows as a heading,
  a summary and a list for each kind of entry, with a link to the new form.

`tests/unit/Model/AccessibleScreensTest.php` checks that every control has a
label, every `aria-describedby` names something that exists, and the
results have status regions and headings.

## Exports

`includes/model/class-baf-exporters.php`
(`BAF_Exporters::export( $target, $model )`) writes a model in each
builder's own format, with a report in the same shape as an import report
(its `direction` is `export`). The AJAX action is `baf_export_form`.

| Target | What it writes | What it keeps |
| --- | --- | --- |
| `gravity-forms` | the Forms, Import/Export JSON | fields, choices and prices, help, placeholders, required, defaults, masks, page breaks and page titles, conditions with one list of tests (all or any), calculations of `+ - * /` |
| `wpforms` | the Tools, Export JSON | fields, choices, help, placeholders, required, defaults, page breaks, conditions as groups of tests (an "or" of "and"s) |
| `contact-form-7` | the Form tab's template | fields with their labels, choices (`"Label\|value"`), required, placeholders, sections and page titles as headings |
| `ninja-forms` | the `.nff` JSON | fields, choices and prices (`calc`), help, placeholders, required, defaults, masks |
| `google-forms` | a `forms.get` resource | questions, choices (by label: Google keeps no separate value), required, page breaks, scales, quiz answers and points |

Every export reads back through the importer to the same questions and
choices, and Gravity Forms and WPForms conditions to the same sentence
(`tests/unit/Model/ExportersTest.php`). `tests/conformance/exports/` holds
one export per builder byte for byte, with its report (BAF only).

What none of them can hold is reported, never silently left out: rules,
quiz outcomes, translations, notifications, smart defaults, cascades,
repeaters, conditions the builder cannot say (dates, field-to-field, mixed
groups), calculations beyond arithmetic, and field types the builder lacks.
A field whose choices come from BAF's built-in libraries gets them written
out on the site (the plugin supplies them); without the plugin it is
reported.

## Not done yet

- **SurveyJS, XLSForm and plain HTML forms**, which 17a.15 places in QUILL
  (they run on the computer).
- **Rules as conditions:** a show or hide rule could be folded into its
  targets' conditions before exporting; today each rule is reported.
