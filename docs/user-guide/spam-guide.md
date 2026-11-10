# Spam: how QAF keeps it out without shutting people out

QAF checks every form for spam before it saves it. This guide is about what
those checks do, what your visitors see, and how you look after what was
caught. The settings are in **QAF Forms > Settings > Spam**.

## The one rule

**Nothing is thrown away.** When a check doubts a form, QAF keeps it, marks
it as spam and writes down why: "6 links (the limit is 3)", "Filled in 1
second (the shortest the site expects is 3)", "OOPSpam score 5 of 6 (the
limit is 3)". The visitor sees the same thank-you message either way. If a
real person was caught by mistake, they have lost nothing: you mark the form
Not Spam and it comes back.

The second half of the rule: QAF never judges people by how they use a
computer. Many bot detectors watch mouse movement, typing rhythm and how long
a page was open. Keyboard users, screen reader users, switch users and people
who dictate all look unusual to those, and they are exactly the people QAF is
for. So any check of that kind (reCAPTCHA v3 scores, Cloudflare Turnstile,
time to fill) can only hold a form for review. It can never turn it away.

There is one thing QAF does refuse: more than 100 forms from one address in
a day. The person is told plainly to try again tomorrow, and their answers
stay on the page.

## What your visitors see

Almost always, nothing at all. The checks are quiet.

When the quiet checks are unsure, and only then, QAF asks one short question
in words, such as "Which is colder, snow or soup?" It appears above the
submit button as an ordinary field: a label, the question read with it, and
room to type. A question with a short list of answers also offers them as
radio buttons. There is no time limit. If the answer does not match, the
visitor gets a new question and the same patience. After three tries they
can choose **Send Anyway**, and the form is kept for a person to read.

If a form has a visible CAPTCHA (reCAPTCHA v2, hCaptcha or the maths
question) and the visitor did not complete it, they are asked to complete it,
as before.

## The checks

These run on your site and send nothing anywhere. They are all on when QAF
is installed, apart from proof of work.

- **Honeypot.** Hidden fields people never see or reach. A script that fills
  every box fills those too.
- **Time to fill.** A form sent within a few seconds of the page opening.
  You choose the shortest time, never less than three seconds. Keep it
  generous: people who paste, dictate or use autofill are quick.
- **Link count.** More links than you allow (3 to start with).
- **Code in answers.** Script tags, event handlers, forum link codes.
- **Your word lists.** The Disallowed Comment Keys and Comment Moderation
  lists in **Settings > Discussion**, so one list protects your comments and
  your forms. A disallowed word holds the form; a moderation word is a doubt.
- **Form token.** Notices a page left open a long time or served from a
  cache. Before this, a stale page lost the person's answers; now it is only
  a doubt.
- **Rate limit.** Many forms from one address in an hour (5 to start with,
  set per form). Each wrong answer to a question tightens it a little.
- **CAPTCHA.** Whatever the form already uses.
- **Proof of work (ALTCHA).** Off until you turn it on. The visitor's browser
  does a small calculation in the background: no puzzle, no tracking,
  nothing sent away. It needs scripts in the browser, so a browser without
  them is only a doubt, never refused. Each wrong answer to a question
  doubles the work for that address, up to sixteen times.

## Spam services and security plugins

These are all off until you turn them on, because some send what your
visitors wrote to another company. Those are listed in the privacy policy
text QAF suggests in **Settings > Privacy > Policy Guide**; add it to your
privacy policy when you turn one on.

- **Akismet.** If the Akismet plugin is active with a key, QAF asks it about
  each form as a contact form, and tells it when you mark one Spam or Not
  Spam so it learns. Even what Akismet calls blatant spam is held, not
  discarded.
- **OOPSpam.** Scores each form from 0 to 6. Put your key and the score that
  should hold a form (3 is a sensible start) in the Spam settings. Spam and
  Not Spam are reported back. QAF does not yet read the key from the OOPSpam
  plugin itself; a developer can pass it through the
  `qaf_oopspam_plugin_settings` filter.
- **CleanTalk and Shield Security.** QAF can see when they are installed and
  says so on the settings page, but it does not ask them anything yet. The
  ways they let other forms ask are still being confirmed with their
  developers, and QAF does not guess at them.

## Text questions

Choose when they are asked: **only when the other checks are unsure**
(recommended), **always** (for a form under attack) or **never** (doubtful
forms are simply held). You can choose differently for each form at the
bottom of the Spam settings.

The questions are made fresh each time, so a script cannot learn one answer.
There are four kinds, and you choose which to use:

- Everyday sense: "Which is heavier, an elephant or a feather?"
- Following an instruction: "Type the second word of this sentence."
- Choosing from a short list: "Which of these is an animal: chair, horse,
  window?"
- Putting things in order: "Put these in order, smallest first: town, city,
  house."

Simple sums are there too but off, because numbers are a barrier for people
with dyscalculia.

You can add your own questions, one per line, with the right answers after
bars:

```
What color is the sky on a clear day? | blue
What do you call a baby cat? | kitten | kitty
```

Ask what anyone using the form would know, not facts about your site.

Answers are read kindly: capitals, spaces and punctuation do not matter,
"nine" and "9" are the same, "an elephant" and "elephant" are the same, a
slip of a letter in a longer word is fine, and "Snow is colder" counts.

The honest limit: anything a screen reader can read, an AI can read and
answer. A text question stops the cheap scripts that send most form spam,
and costs the rest time. It is one layer, never the gate.

The Spam settings show how this week went: how often a question was asked,
answered, answered wrongly, and sent anyway.

## Looking after what was caught

Held forms have the status **spam**, with the check that held them, the
reason and the score. Notification emails and integrations wait for them:
nothing is sent for a held form until you release it.

- **Not Spam** puts the form back with your other entries, tells the spam
  services that learn (Akismet and OOPSpam), and sends the notifications that
  waited, once.
- **Spam** confirms it and tells the same services.

In QUILL, the WordPress Workbench lists held forms under **Needs My
Attention > Held as Spam**, with the reason, and offers Not Spam and Spam
there.

## For developers

REST routes, in `qaf/v1`:

| Route | What it does |
| ----- | ------------ |
| `GET entries?form_id=7&status=spam` | Held entries, each with a `spam` object (check, reason, score, score_max, every doubt, who marked it what) |
| `POST entries/{id}/spam` | Mark as spam (needs `qaf_edit_entries`) |
| `POST entries/{id}/not-spam` | Mark as not spam (needs `qaf_edit_entries`) |
| `GET spam` | Each check's state, the limits, question settings and this week's numbers; never a key (needs `qaf_view_entries`) |
| `GET spam/pow` | A proof-of-work challenge for a form page, when ALTCHA is on |

When the site asks a question, the submit route answers with `success`
false, a `message` and a `challenge` (its id, question, markup, and a Send
Anyway id after three tries). Send the form again with `qaf_challenge_id`
and `qaf_challenge_answer`, or with `qaf_send_anyway`.

Hooks:

- `qaf_spam_check` (filter): add a verdict to hold a form. A verdict is an
  array with `provider`, `verdict` (`pass`, `unsure` or `spam`), `reason`,
  and optionally `score`, `score_max` and `behavioural`. Nothing added here
  can refuse a form; the most it can do is hold it.
- `qaf_spam_providers` (filter): add a whole provider, an object that
  implements `QAF_Spam_Provider`. It is told about Spam and Not Spam too.
- `qaf_entry_marked` (action): someone marked an entry. Gets the entry id,
  `spam` or `ham`, the submission and the entry.
- `qaf_entry_held` (action): a form was held when it arrived, in place of
  `qaf_form_submitted`.
- `qaf_oopspam_plugin_settings` (filter): hand QAF an OOPSpam key and
  threshold from elsewhere.
- `qaf_trust_proxy_headers` (filter): read the visitor's address from
  X-Forwarded-For, only behind a proxy you trust.
