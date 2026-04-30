# QA Bugs Triage — 2026-04-30

Source: WB Listora QA Basecamp project (47045113), Bugs column (9827892296). 15 active cards triaged with code-level root cause analysis.

> Verdict legend: **VALID** = bug reproducible in code, **INVALID** = report mis-attributes the cause, **PARTIAL** = previous fix landed but residual gap remains, **NEEDS-INFO** = report lacks data to triage.

---

## Summary table

| # | Card | Title | Verdict | Severity | Root cause group |
|---|------|-------|---------|----------|------------------|
| 1 | 9842576349 | Unable to Edit Listing from My Listings | **INVALID** (mis-attributed; real bug lives in submission block edit mode) | High | G1 |
| 2 | 9842552596 | Listing Preview Does Not Display Complete Information | **VALID** | Medium | G3 |
| 3 | 9838553758 | Profile Save Button Not Working | **VALID** | High | **G1 — store/template contract drift** |
| 4 | 9838530731 | Listing Deactivate Action Not Working | **VALID** | High | **G1 — store/template contract drift** |
| 5 | 9838460853 | "Get Directions" Button Position (UI) | **VALID** (CSS only) | Low | G6 |
| 6 | 9838103313 | No Confirmation Message After Adding Listing | **VALID** | High | G3 |
| 7 | 9838081827 | Sort Results Dropdown Not Working | **VALID** | High | **G2 — likely cascade from missing `toggleFeatureFilter`** |
| 8 | 9838055062 | Category Filter Not Applying | **VALID** | High | **G2 — same cascade** |
| 9 | 9833812803 | Type Validation No Error Message | **PARTIAL** (blocks correctly; visual feedback missing) | Medium | G3 |
| 10 | 9833907227 | Continue/Submit — console errors | **PARTIAL** (functionality works; unsafe querySelector throws) | Medium | G5 |
| 11 | 9838412472 | Company Logo Upload (Job type) | **VALID** | High | **G4 — Interactivity API hydration of hidden subtrees** |
| 12 | 9833594862 | UI Spacing — one location remaining | **PARTIAL** (Reviews fixed; Setup Wizard Location step still inline-styled) | Low | G6 |
| 13 | 9838515326 | Business Hours Not Displayed | **VALID** | High | G3 — meta key/schema match |
| 14 | 9838139094 | Gallery Images Not Displaying on Detail | **VALID** | Medium | G3 — fallback URL regex |
| 15 | 9838185036 | Gallery Add Photos Not Working | **VALID** | High | **G4 — same hydration bug as #11** |

---

## Root-cause groups (fix once, resolve many)

### G1 — Store/template contract drift (Bugs #3, #4)
Templates declare `data-wp-on--click="actions.X"` for actions that **do not exist** in `src/interactivity/store.js`. The Interactivity API silently ignores unknown action references.

| Drift | Template (declared) | Store (missing) |
|-------|--------------------|-----------------| 
| `actions.deactivateListing` | `templates/blocks/user-dashboard/tab-listings.php:198` | `src/interactivity/store.js` |
| `actions.saveProfile` (form has no JS submit binding at all) | `templates/blocks/user-dashboard/tab-profile.php:79` (plain `<form>` POST that no handler reads) | n/a |
| `actions.toggleFeatureFilter` | `templates/blocks/listing-search/filters.php:127` | `src/interactivity/store.js` |

**The third drift is the prime suspect for Bugs #7 + #8** — if the IAPI throws on unknown action references during hydration, all `setSort`, `setFilter`, etc. on the same store may also fail to fire, producing the observed sort/filter ghost behavior.

**Also missing on backend side:** there is **no REST endpoint** for deactivate. Closest is `DELETE /listings/{id}` (trashes). Bug #4 needs `POST /listings/{id}/deactivate` plus a `listora_deactivated` post status writer.

**Fix scope:**
- Add `actions.deactivateListing`, `actions.saveProfile`, `actions.toggleFeatureFilter` to `src/interactivity/store.js`.
- Add `POST /listora/v1/listings/{id}/deactivate` to `includes/rest/class-listings-controller.php` with author-ownership permission callback. Sets `post_status = 'listora_deactivated'`.
- Wire profile form: either bind a submit handler that calls existing `PUT /dashboard/profile` (preferred — REST-first), or add a `template_redirect` PHP handler. Existing endpoint at `class-dashboard-controller.php:726` already works.
- After JS changes: `npm run build` (rebuilds `build/blocks/user-dashboard/view.js` and `build/blocks/listing-search/view.js`).

### G2 — Sort & filter UX (Bugs #7, #8)
Beyond the cascade from G1, two additional standalone defects:

1. **Sort dropdown server-render**: `<option>` tags in `templates/blocks/listing-grid/toolbar.php:90-99` have no `selected` attribute. `data-wp-bind--value="state.sortBy"` only sets the DOM `.value` after hydration, so the dropdown visually snaps to "Featured" on every server-rendered load even when the URL says `?sort=rating`.
   **Fix:** add `<?php selected( $effective_sort, $value ); ?>` per option.

2. **Sort allowlist gap**: `blocks/listing-grid/render.php:77` allowlist is missing `'relevance'`. The REST API accepts it (`class-search-controller.php:182`) but SSR silently coerces to `'featured'`.
   **Fix:** append `'relevance'` to `$allowed_sorts`.

3. **Stale search index for rating**: commit `2c5065b` introduced auto-reindex but if cron is dormant, existing rows have `avg_rating = 0` — sort by rating produces same order as featured. Verify on the affected install with `wp listora reindex` before claiming the fix landed.

### G3 — Read/write schema mismatches & template guards (Bugs #2, #6, #13, #14, #9-residual)
Each shares the pattern: writer and reader (or display gate) disagree on shape.

- **Bug #2 — `buildPreview()` at `src/blocks/listing-submission/view.js:678-705`** reads only 3 hardcoded fields (`title`, `description`, `category`). Must iterate the active type's field set and render each label+value. Pure JS change.
- **Bug #6 — Submission success div is a child of the `<form>` that `handleSubmission()` hides at view.js:212.** `successDiv.hidden = false` is then set on a child of a hidden parent — never visible. **Fix:** in `templates/blocks/listing-submission/submission.php`, move `.listora-submission__success` and `.listora-submission__error` outside the `<form>` (sibling under `.listora-submission`, matching how `.listora-submission__duplicate-review` is already placed at line 160). No JS change — `form.querySelector` traverses from the block root, not formEl.
- **Bug #13 — Business hours field key mismatch.** `blocks/listing-detail/render.php:120` reads `$meta['business_hours']`. `Meta_Handler::get_all_values` strips `_listora_` prefix, so this works only if the listing-type field key is exactly `business_hours`. Must verify `includes/core/class-listing-type-defaults.php` registers it under that key for every type that supports hours. Both display sites (`sidebar.php:54`, `tabs.php:151`) silent-skip on empty array — they hide the mismatch. **Fix:** confirm key, add explicit empty-state UI so future drift is visible to QA, not silent.
- **Bug #14 — Gallery click swap fallback regex** at `blocks/listing-detail/render.php:658` does `src.replace(/thumbnail|150x150/, 'large').replace(/\d+x\d+/, '')`. Fails for any thumbnail size that isn't literally 150x150. **Fix:** read `data-wp-context.imageSrc` (already pre-emitted by `gallery.php:53-56` with the correct `large` URL) instead of regex-rewriting the thumbnail src. The IAPI store path already does this correctly; align the no-JS fallback.
- **Bug #9 residual — radio validation has no visible feedback.** `markInvalid()` at view.js:486-498 sets `field.style.borderColor` on a 16px `<input type="radio">` with no border. No `.is-invalid` rule for radios in `blocks/listing-submission/style.css`. **Fix:** apply error class to `.listora-submission__type-card-inner` wrapper + add CSS + insert/reveal a sibling `.listora-submission__field-error` element after `.listora-submission__type-grid` (matching the text-input error pattern). Add an initially-hidden placeholder in `templates/blocks/listing-submission/step-type.php`.

### G4 — Interactivity API hydration of initially-hidden subtrees (Bugs #11, #15)
Single underlying cause. Click handlers declared via `data-wp-on--click="actions.openMediaUpload"` on elements that start with the `hidden` attribute (or live inside a `[hidden]` parent) are not always bound by the Interactivity API runtime. When `selectSubmissionType()` or `nextSubmissionStep()` later removes `hidden`, the handler is dead.

Affected sites:
- Job type Company Logo upload zone: `includes/submission-field-renderer.php:251-256` rendered inside `templates/blocks/listing-submission/step-details.php:74-80` (type-block starts `hidden`).
- Gallery + Featured Image upload zones in `templates/blocks/listing-submission/step-media.php:33,75-79` (entire `data-step="media"` step starts `hidden`).

**Fix (single delegated listener):** in `src/blocks/listing-submission/view.js` `DOMContentLoaded` init, attach a delegated `click` listener on `.listora-submission` root matching `[data-wp-on--click="actions.openMediaUpload"]`, calling the same `wp.media` flow extracted into a standalone function. Keeps the IAPI declaration intact for the happy path while guaranteeing fallback bind. One change resolves both bugs and prevents future recurrences when new conditional upload fields are added.

### G5 — Unsafe querySelector with user-supplied field names (Bug #10 residual)
After commit `afb0035`, `evaluateConditionals` at `src/blocks/listing-submission/view.js:945` does `form.querySelector('[name="' + triggerName + '"]')` without escaping. Field names with brackets (e.g. `meta_business_hours[monday][open]`) crash `querySelector` with `SyntaxError`. Same issue at line 1115 inside `initConditionalFieldWatchers`.

**Fix:** wrap both call sites with `CSS.escape(triggerName)` (the radio path at line 507 already does this — apply the same pattern). Add a `try/catch` to skip malformed selectors gracefully.

### G6 — Inline CSS in PHP / Rule 11 violations (Bugs #5, #12 residual)
The codebase rule "no inline `style=` in PHP/templates, all CSS via enqueued stylesheets" has remaining offenders:

- **Bug #5** — `templates/blocks/listing-detail/tabs.php:455` has `<a ... style="margin-block-start: 0.75rem;">Get Directions</a>` directly after the map embed, not wrapped in any container. **Fix:** wrap in `<div class="listora-detail__map-actions">`, remove inline style, add the rule to `blocks/listing-detail/style.css`.
- **Bug #12 residual** — `includes/admin/class-setup-wizard.php` has 6 inline `style=` offenders at lines 287, 288, 293, 298, 315, 320, 329 (Location step coordinate row, Maps step provider column). **Fix:** add semantic classes (`.listora-wizard__field--coords`, `.listora-wizard__global-label`, `.listora-wizard__map-options`, `.listora-wizard__option-card__desc`) to `assets/css/admin/setup-wizard.css` with `@media (max-width: 640px)` mobile rule for the coordinate row, then replace the `style=""` attributes in PHP.

---

## Bug #1 — INVALID as filed
The "Edit" button in `templates/blocks/user-dashboard/tab-listings.php:181` is a plain `<a href="?edit=ID">` hard-link to the submission form in edit mode. It is not an AJAX or IAPI action; clicking it cannot fail in the dashboard. The error QA captured is being raised by the **listing-submission block** when it runs in edit mode and sends `PUT /listora/v1/submit/{id}`. The dashboard is not at fault.

**Recommendation:** close as INVALID against the dashboard, **reopen against `src/blocks/listing-submission/`** with the explicit error text from the screenshot (the Basecamp screenshot embeds the message but the report doesn't transcribe it). Without the error string, root cause cannot be pinned. Likely candidates:
- payload shape mismatch with `Submission_Controller::update_listing()` (`includes/rest/class-submission-controller.php:563`)
- a required field hidden by the conditional logic that the Bug #10 residual `querySelector` fix would also prevent crashing on
- ownership re-check failure if a meta key for `_listora_owner_id` is being read with wrong scope

QA needs to provide: the exact error text + browser console output during the Update click.

---

## Suggested fix order (dependency-aware)

1. **G1 store contract** — adds the missing actions + the missing REST endpoint. Unblocks #3, #4, and is a prerequisite for any reliable QA on #7/#8.
2. **G4 hydration delegated listener** — unblocks #11, #15 (both upload paths) with one change.
3. **G2 sort/filter SSR** — option `selected` + allowlist + reindex → unblocks #7, #8 visually after G1.
4. **G3 schema/preview/success/business-hours/gallery** — five independent fixes, can be parallelized.
5. **G5 querySelector escape** — small, quick, kills the console noise from #10.
6. **G6 inline CSS** — pure CSS/markup cleanup; ship in same PR as #5 and remaining wizard.
7. **#1 reopen** under listing-submission block once QA provides the error text.

---

## Browser verification gate
Per CLAUDE.md, every fixed item must be verified at 1440 / 1280 / 390 viewports via Playwright MCP, with hover/focus/visited states confirmed for any anchors restyled. No item is "done" until its verification screenshot is attached to the Basecamp card.
