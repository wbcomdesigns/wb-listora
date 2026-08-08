---
journey: submission-category-optional-when-none
plugin: wb-listora
priority: critical
roles: [member]
covers: [BC-10180373117, submission-wizard, allowed_categories, dead-end-guard]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one listing type with an EMPTY allowed_categories list (`business` ships this way)"
  - "At least one listing type WITH categories (`restaurant` has 15)"
  - "Auto-login mu-plugin present (?autologin=1)"
estimated_runtime_minutes: 5
covers_card: 10180373117
---

# A type with no categories must not dead-end the wizard (BC 10180373117 sentinel)

`allowed_categories` is a per-type allowlist and an **empty one is a legitimate
configuration**, not a data error — `business` ships that way while the other
nine types carry 8–15.

`step-basic.php:43` already suppresses the Category field when the type is known
at render time. In the **wizard** it never is: the member picks the type on step 1
in the browser, so the server prints the select unconditionally, always
`required`, and `view.js selectSubmissionType()` is the only thing that ever
fills it. For a type with no categories it stayed at the bare placeholder while
still carrying `required` — a control with nothing to choose that refuses to let
you past Basic Info, with no message explaining why.

**The server was never the blocker.** `POST /submit` accepts a listing with no
category and returns 201 — verified. This was a client-side dead end only.

Fix: `syncCategoryApplicability()` in `src/blocks/listing-submission/view.js`
enforces one invariant — **required if and only if there is something to pick** —
applied on the reset, success and failure paths, so a failed REST call also
cannot strand the member.

## Setup

- `playwright_navigate $SITE_URL/add-listing/?autologin=1` at 1440×900

## Steps

### 1. A type with NO categories hides the field
- **Action**: click the **Business** type card, wait for the categories fetch.
- **Expect**: `[name="category"]` has `options.length === 1` (placeholder only),
  `required === false`, and its `.listora-submission__field` wrapper is `hidden`.

### 2. …and the wizard advances
- **Action**: Continue → fill Title and Description → Continue.
- **Expect**: the visible step is `details`. **Staying on `basic` is the
  regression.**

### 3. A type WITH categories still shows and requires it
- **Action**: reload, pick **Restaurant**, wait for the fetch.
- **Expect**: `options.length > 1` (15 + placeholder), `required === true`,
  wrapper not hidden.

### 4. …and still blocks when left empty
- **Action**: Continue → fill Title and Description, leave Category unset →
  Continue.
- **Expect**: still on step `basic`. The fix must not weaken validation for types
  that *do* have categories.

### 5. …and passes once a category is picked
- **Action**: select the first real option → Continue.
- **Expect**: step `details`.

### 6. Switching type re-evaluates — no stale `required`
- **Action**: reload, pick **Restaurant**, wait, then pick **Business**, wait.
- **Expect**: back to `options.length === 1`, `required === false`, wrapper
  `hidden`. A stale `required` left over from the previous type is the regression
  this step exists for.

### 7. 390px
- **Action**: resize to 390×844.
- **Expect**: no horizontal page scroll.

## Pass criteria

1. Empty-category type: field hidden, `required === false`, wizard reaches `details`
2. Populated type: field shown, `required === true`
3. Populated type with Category empty: Continue is still blocked
4. Populated type with Category chosen: Continue passes
5. Switching populated → empty re-hides and un-requires
6. No horizontal scroll at 390px

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Business stuck on Basic Info, browser bubble on an empty select | `syncCategoryApplicability()` not called on the fetch-success path | `src/blocks/listing-submission/view.js` — the `.then()` after the categories fetch |
| Restaurant no longer requires a category | the invariant was inverted or the wrapper lookup returns null | same helper — `required` must be `options.length > 1` |
| Field stays hidden after switching Business → Restaurant | helper not called on the reset path | the `syncCategoryApplicability()` call right after `dataset.listoraTypeLoaded = slug` |
| Works in the wizard, broken on the single-form layout | that path renders with a known type and is governed by the template, not the JS | `templates/blocks/listing-submission/step-basic.php:43` |
| Fix present in `src/` but not on the page | compiled bundle stale | run `npm run build`; never hand-edit `build/` |
