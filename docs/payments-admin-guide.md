# Taking payments: a guide for site owners

This guide is for the person who runs the WordPress site. It covers
connecting Stripe and PayPal, test mode, webhooks, going live, the payments
report, and what the plugin does to keep money and keys safe. If you write
forms in QUILL or QUILL Lite, their own guides have a chapter called
"Taking payments with your forms" that picks up where this one stops.

Everything here happens under **QAF Forms > Payments** in wp-admin. You need
to be an administrator, or have the `qaf_manage_payments` capability.

## What happens when someone pays

1. A visitor fills in a paid form and sends it.
2. The site checks the form's payment settings again, works out what is
   owed from the form itself, and saves the answers. Nothing the browser
   sends about money is believed: not a price, a total, a currency or a
   "paid" flag. Only the visitor's own choices count: which product, how
   many, which coupon code, how much to give.
3. A payment panel appears under the form, with a heading that says how
   much ("Pay 25.00 USD"). The visitor picks card (through Stripe) or
   PayPal, and pays in the provider's own fields.
4. The site asks the provider what happened, and the provider also tells
   the site through a signed webhook. Only then does the entry change from
   "Waiting for payment" to "Paid".

The entry exists from step 2. If the visitor never pays, it stays
"Waiting for payment", which is what you see in the report and what QUILL
shows in Responses.

## Before you start

- A Stripe account, a PayPal business account, or both. You can use one or
  the other; a form only offers the providers it names.
- HTTPS on the site. Both providers refuse to take payment on a plain
  http page, and so should you.
- Permalinks set to anything other than Plain, so that the webhook
  addresses under `/wp-json/` work.

## Test mode first

A new site starts in test mode. In test mode the plugin uses only your test
keys (Stripe) and sandbox app (PayPal), and no real money moves. Keep it
there until you have tried every step.

A form also says whether it is a test form or a live one. The site will not
take payment for a form whose mode is different from its own: a test form
on a live site is refused, and so is a live form on a test site. The
visitor is told payment is not available right now, and nothing is saved.
QUILL checks the same thing before it publishes, so you normally find out
there rather than from a visitor.

## Connecting Stripe

1. In the Stripe Dashboard, turn on test mode and open Developers, then API
   keys. You need the publishable key (`pk_test_...`) and the secret key
   (`sk_test_...`). A restricted key (`rk_test_...`) also works, if it can
   create and read Payment Intents and webhook endpoints.
2. On **Payments**, find the fieldset "Stripe, test keys". Paste the
   publishable key and the secret key, then press **Save Payment Settings**.
3. Press **Check Connection: Stripe test**. The site asks Stripe whether it
   accepts the key, and whether Stripe agrees that it is a test key. The
   result appears at the top of the page and takes focus, so a screen
   reader reads it straight away.
4. Press **Create Webhook: Stripe test**. The site registers its own
   webhook address with Stripe, asks for the payment and refund events it
   needs, and keeps the signing secret Stripe shows only once.

If you would rather make the endpoint yourself in the Stripe Dashboard, use
the webhook address shown on the page, choose the events
`payment_intent.succeeded`, `payment_intent.processing`,
`payment_intent.payment_failed`, `payment_intent.canceled`,
`payment_intent.amount_capturable_updated`, `charge.refunded` and
`charge.refund.updated`, and paste the signing secret (`whsec_...`) into
"Webhook signing secret". The site counts the webhook as ready once the first
correctly signed event arrives: send a test event from the Dashboard, or
with the Stripe CLI.

## Connecting PayPal

1. In the PayPal Developer Dashboard, make sure you are looking at Sandbox,
   open Apps and Credentials, and create an app (or use one you have).
   Copy its client ID and secret.
2. On **Payments**, fill in "PayPal, sandbox keys" and save.
3. Press **Check Connection: PayPal sandbox**. The site asks PayPal for an
   access token with those credentials.
4. Press **Create Webhook: PayPal sandbox**. The site registers its
   webhook address and keeps the webhook ID, which it needs to have PayPal
   verify each event.

If you make the webhook yourself, use the address on the page, subscribe to
`PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.PENDING`,
`PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.DECLINED`,
`PAYMENT.CAPTURE.REFUNDED`, `PAYMENT.CAPTURE.REVERSED`,
`PAYMENT.AUTHORIZATION.CREATED` and `PAYMENT.AUTHORIZATION.VOIDED`, paste
the webhook ID, save, and press Check Connection again. The check confirms
the ID exists, points at this site, and asks for those events.

## The Readiness table

The top of the page has a small table: one row per provider, with its keys
and its webhook in words. "Ready" means the provider accepted exactly the
keys saved now. Change a key and the row goes back to "Not checked since the
keys were saved" until you check again. A typed key on its own is never
reported as ready.

QUILL reads the same information from the site (the route is
`GET /wp-json/qaf/v1/payments/settings`) when you use Verify Payment Setup or
publish a paid form. It gets only yes or no for each provider and the
site's mode, never a key.

## Trying it out

With a form published in test mode:

- Stripe's test card `4242 4242 4242 4242`, any future expiry date, any
  three-digit CVC and any postcode, succeeds.
- `4000 0000 0000 0002` is declined, so you can hear what a failure sounds
  like and try again.
- `4000 0025 0000 3155` asks for extra authentication, the step banks call
  3-D Secure.
- For PayPal, sign in with a sandbox personal account from the Developer
  Dashboard, never your own.

Stripe publishes the full list of test cards. Never type a real card number
into a test form, and never use test card numbers anywhere else.

Check the entry after each try, in the report below and in QUILL's
Responses, and compare it with the payment in the provider's dashboard: the
amount, the currency and the transaction ID should match.

## Going live

When every test has worked:

1. In Stripe, turn test mode off and copy the live publishable and secret
   keys into "Stripe, live keys". In PayPal, switch to Live, create a live
   app, and copy its client ID and secret into "PayPal, live keys".
2. Save, then use **Check Connection** and **Create Webhook** for the live
   environment of each provider you use.
3. In Mode, choose **Live**, check **I have finished testing and want this
   site to take real payments**, and save. Without the check box nothing
   changes, so a slip of the arrow keys cannot switch you to live.
4. In QUILL, turn test mode off in Form Payments Setup for each form that
   should take real money, and publish it again. QUILL says "LIVE payments:
   real money" in its publish confirmation.

Test forms stop taking payment on a live site. That is on purpose.

## Manual capture

A form can authorize a card and take the money later ("Capture: Manual" in
QUILL). The entry says "Authorized, not yet captured" until you capture
the payment in the Stripe or PayPal dashboard; then the webhook moves it to
"Paid". If you void it, or the authorization expires, it becomes
"Cancelled".

## Refunds

Refunds are made in the Stripe or PayPal dashboard, not here and not in
QUILL. The provider tells the site, and the entry becomes "Partly refunded"
or "Refunded". A partial refund is never shown as a full one, and a late
event can never turn a refunded entry back into a paid one.

## The report

"Recent payments" lists the latest payments, newest first, as a table with
a header row: entry, form, amount (with any refund), status in words,
provider, mode, transaction ID and when it last changed. You can filter by
status and by form ID. Above the table, "Totals for the payments shown"
gives, per currency, how many were paid, what was taken, what was refunded,
what you kept, and what is authorized but not yet captured.

## For developers

- `qaf_payment_paid` fires once when an entry's payment becomes paid,
  however many times the news arrives. It is the place for fulfilment:
  send the ticket, grant the membership. It receives the payment record
  (no keys in it).
- `qaf_payment_authorized` fires once when a manual-capture payment is
  authorized.
- `qaf_payment_status_changed` fires on every change of state, with the
  record and the state it left.
- `qaf_form_submitted` still fires when the answers are saved, before
  payment. Do not fulfil an order from it.
- The routes under `qaf/v1/payments/` are: `settings` (GET, administrators
  only), `start`, `finish` and `status` (POST, used by the checkout, each
  needing the one-time token the site gave that submission), and
  `webhook/stripe` and `webhook/paypal` (POST, provider-signed).
- Entries in `GET /qaf/v1/entries` carry a `payment` object with
  `provider`, `status`, `amount` (decimal text in major units, such as
  "25.00"), `currency`, `receipt_url` and `transaction_id`. An entry with no
  `payment` object has an unknown payment, never a paid one.

## Security notes

- Secret keys, webhook signing secrets and webhook IDs live in their own
  WordPress option, which is never loaded on ordinary page views. The
  settings screen never shows them again after you save, not even the last
  four characters; it says only "Saved". To replace one, paste the new
  value; to remove it, check its Remove box and save.
- You can keep secrets out of the database entirely by defining them in
  `wp-config.php`, for example `define( 'QAF_STRIPE_LIVE_SECRET_KEY', '...' );`.
  The names are `QAF_` followed by the provider, the environment and the
  setting, in capitals: `QAF_STRIPE_TEST_WEBHOOK_SECRET`,
  `QAF_PAYPAL_LIVE_SECRET`, `QAF_PAYPAL_LIVE_WEBHOOK_ID` and so on. The
  settings screen then says the value is set in wp-config.php.
- No REST route returns a secret, and QUILL has no way to send one. The page
  gets only the publishable key or client ID and a one-time token for its
  own payment; the site keeps only a hash of that token.
- Card numbers never reach the site. They go from the visitor's browser to
  Stripe or PayPal directly.
- Every webhook is checked before it is read: Stripe's with its signing
  secret and a five-minute time limit, PayPal's with PayPal's own
  verification call. Each event is applied once, its environment must match
  the payment's, and its amount and currency must match what the site
  worked out. An event that fails any of these changes nothing.
- Payment Intents and PayPal orders are created with idempotency keys made
  from the payment, so a double click, a reload or two tabs cannot charge
  twice. Sending the same page of the form twice reuses the first entry.
- Changes of state are written only if the payment is still in the state
  it was read in, so two events arriving together cannot both win.

## Accessibility notes

- The payment field says, in words, what will be charged and in which
  currency. A donation is one labelled text box with its limits in the
  hint, tied to the box so a screen reader reads them with it.
- Errors are tied to the field they are about and say what to do: "The
  smallest gift this form takes is 5.00 USD."
- When the form is sent, the payment panel's heading takes focus, so a
  screen reader announces "Pay 25.00 USD". Progress ("Sending your payment.
  Please wait.") comes from one polite status line, problems from one
  alert, and the result is said in words: "Payment complete. Thank you."
- Choosing card or PayPal is a pair of ordinary radio buttons. Paying is
  an ordinary button. Nothing depends on colour, and every step works from
  the keyboard.
- The card fields themselves are Stripe's Payment Element and the PayPal
  button is PayPal's. Both are made to work with screen readers, but they are
  the providers' code, so test them with yours before going live. The
  PayPal button opens PayPal in a new window; the panel says so.
- This screen uses native WordPress form controls, fieldsets with legends
  for each set of keys, visible labels, and a captioned table for readiness
  and for the report.

## Older payment fields

Everything in this guide applies to forms that carry a payments block in
their form model, which is what QUILL and QUILL Lite publish. A Payment
field set up in the visual builder's older field settings, with no payments
block, still goes through the older Stripe and PayPal integration code,
which works differently and is not covered here. To use what this guide
describes, publish the form from QUILL, or add the payments block in the
form's model.

## When something goes wrong

- **A visitor hears "This form cannot take payment right now".** The form
  and the site are in different modes, or a provider the form uses is not
  ready. Look at the Readiness table, or run Verify Payment Setup in QUILL.
- **Check Connection says the key belongs to the other environment.** A
  live key is saved under test, or the other way round.
- **Payments stay "Waiting for payment" after a successful test.** The
  webhook is not reaching the site. Check the address in the provider's
  dashboard, that the site is reachable from the internet, and that no
  security plugin blocks `/wp-json/qaf/v1/payments/webhook/`. The visitor's
  own page also asks the site to check, so a paid entry usually changes
  even before the webhook arrives; one that never changes means both routes
  are failing.
- **An entry says "Payment failed".** The card was declined or the
  visitor gave up. They can press Try again on the same page; the entry
  stays the same one.
