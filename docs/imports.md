# Importing forms from other builders

BAF reads five other form builders' forms into its form model
(`docs/form-model.md`). Each import is a mapping to the model, and each
returns an import report: what came across changed, what did not come across
at all, and what to check. Nothing is dropped without being listed.

- **Code:** `includes/model/class-baf-importers.php`
  (`BAF_Importers::import( $source, $text )`).
- **On the site:** the AJAX action `baf_import_form` saves the result as a
  draft form and returns the report, both as data and as plain sentences.
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

## Not done yet

- **Exports** to these builders' formats.
- **SurveyJS, XLSForm and plain HTML forms**, which 17a.15 places in QUILL
  (they run on the computer).
- **A screen in BAF's admin** for importing; the AJAX action is ready for one.
