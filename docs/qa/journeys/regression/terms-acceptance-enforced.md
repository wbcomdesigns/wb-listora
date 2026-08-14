---
journey: terms-acceptance-enforced
plugin: wb-listora
priority: critical
roles: [member]
covers: [agree_terms, submission-wizard-validation, wb_listora_require_terms_acceptance, terms-consent-meta, BC-10195308842]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active"
  - "A member account that can reach the Add Listing form"
  - "The submission block left at its default showTerms = true"
estimated_runtime_minutes: 5
---

# A listing cannot be created without accepting the Terms of Service

The checkbox existed from the start. Nothing enforced it.

**Frontend:** add mode renders the wizard layout, edit mode renders single-form. `handleSubmission`
ran `validateStep()` only behind `if ( single-form )`, so in the wizard nothing validated. The
wizard's own per-step validation runs inside `nextSubmissionStep` — on the step being *left* — and
Preview is only ever arrived at, never advanced past. So the one step carrying the checkbox was the
one step no code validated.

**Backend:** `submit_listing()` never read `agree_terms`. A direct REST POST created a listing with
no consent at all, and nothing was written anywhere afterwards, so an accepted submission and a
bypassed one were indistinguishable after the fact.

That combination is why this is a compliance defect and not a validation nit: the gate was cosmetic
on the only path members use, and absent on the path a script uses.

## The three assertions

1. The server refuses a submission with no consent — **omitted and explicit `false` both refused**.
2. The wizard refuses before sending, and says why **visibly** (computed style, not presence).
3. Accepting still works, and the acceptance is **recorded** on the listing.

## Setup

```bash
SITE=http://listora.local
# Log in as a member in the browser via ?autologin=<user>
```

## Steps

### 1 — Server refuses a submission with no consent

Post to `/wp-json/listora/v1/submit` from the logged-in browser context (the REST nonce is required)
with a valid title, `listing_type`, `confirmed_not_duplicate: true` and a duplicate explanation, but
**no `agree_terms`**.

- **Expect** `400` and code `listora_terms_required`.
- Repeat with `agree_terms: false` → same `400`.
- Repeat with `agree_terms: true` → `201`.

A `201` on either of the first two is the regression.

### 2 — The wizard refuses, and the refusal is visible

Walk the Add Listing wizard to Preview, filling only **active** required fields at each step.

> Fill active fields only. Every listing type renders its own field block; the inactive blocks are
> `disabled` and carry duplicate names (there are several `meta_address[address]` inputs). Filling
> the first match fills a disabled one and the step will refuse to advance — that is correct
> behaviour, not a bug.

Leave "I agree to the Terms of Service" unticked and click Submit.

- **Expect** no request to `/submit` is sent.
- **Expect** the form is still visible and the success panel is still hidden.
- **Expect** `.listora-submission__field-error--agree-terms` reads
  "Please accept the Terms of Service to continue." and is **computed-visible**:

```js
const err = document.querySelector( '.listora-submission__field-error--agree-terms' );
const cs  = getComputedStyle( err );
// display must be 'block', not 'none'. Presence in the DOM proves nothing —
// three cards were bounced in the 2026-08-11 sweep for exactly that mistake.
( cs.display !== 'none' && cs.visibility !== 'hidden' && err.getBoundingClientRect().height > 0 )
```

Repeat at **390px** — the message must stay inside the viewport and the body must not scroll
horizontally.

### 3 — Accepting works, and is recorded

Tick the checkbox, click Submit.

- **Expect** the success panel appears.
- **Expect** the listing carries consent meta:

```bash
wp eval 'echo get_post_meta( <NEW_ID>, "_listora_terms_accepted", true );'
```

An empty value is the regression — a listing that cannot prove consent is the thing this journey
exists to prevent.

### 4 — The escape hatch still works

`showTerms` is a block attribute, so the REST layer cannot see it. Sites that turn the checkbox off
opt out server-side:

```php
add_filter( 'wb_listora_require_terms_acceptance', '__return_false' );
```

With that filter active, step 1's first case must return `201`. Without it, `400`. A filter that no
longer changes the outcome means the escape hatch has rotted and integrators are stranded.

## Cleanup

Delete every probe listing created by this journey.
