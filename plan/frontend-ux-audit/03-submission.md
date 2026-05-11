# G3 Submission Wizard — Audit

**Audited:** listing-submission block (render.php 303 + style.css **1,198**) + 9 step templates (768 lines total) + flatpickr vendored asset.

**Total surface: 2,269 lines + flatpickr vendor.**

**Headline:** Steps are well-named + BEM-clean (3-18 `__` elements each, scaling with step complexity). The 1,198-line style.css carries the weight of multi-step UX (steppers, validation states, field renderers, media uploaders, plan picker, review pane). Two structural issues: **(a) step-preview.php at 140 lines duplicates field rendering logic the steps already own**, and **(b) the wizard has no shared "form field" primitives — each step inlines its own form-control classes**.

---

## Step-by-step inventory

| Step | Lines | BEM `__` | Purpose |
|---|---|---|---|
| stepper.php (top nav) | 27 | 5 | step indicator dots + labels |
| navigation.php (bottom nav) | 40 | 6 | Back / Continue buttons |
| step-type.php | 41 | 5 | 10-type grid picker |
| step-basic.php | 75 | 4 | title + content + categories + location |
| step-details.php | 108 | 7 | per-type custom fields + business hours |
| step-media.php | 101 | 10 | featured image + gallery uploader |
| step-preview.php | 140 | 18 | summary of all entered data |
| step-duplicate-review.php | 72 | 14 | "your listing is similar to X" guard |
| submission.php (wrapper) | 164 | 11 | the wizard frame + error display |

---

## Issues

### G3-01 (BLOCK) — step-preview.php (140 lines) re-implements field display from each step

Each of step-basic / step-details / step-media renders its own fields. step-preview then RE-RENDERS each field's value for the customer to confirm. The preview re-implementation has its own markup, its own class names (18 BEM `__` elements vs the 4-10 in the source steps), and drifts visually from the source.

When a step changes (e.g. new field added to step-details), step-preview must be updated too. The duplication is fragile.

**Recommendation:** extract field display into shared partial `templates/blocks/listing-submission/_field-display.php` that takes `$field` + `$value` + `$mode` (`'edit' | 'preview'`). Step templates and step-preview both `include` it.

### G3-02 (BLOCK) — No shared form-control primitives

Each step inlines its own form-control markup:
- `.listora-submission__field` (some steps) vs `.listora-submission-field` (other steps)
- `.listora-submission__input` (basic) vs `.listora-form__input` (details)
- `.listora-submission__error` (basic) vs `.listora-submission-error` (details)

Inconsistent naming for the same widget. The portfolio standard (per block-quality-standard.md Section 11) wants standard form-control primitives shared across blocks.

**Recommendation:** define canonical form primitives in shared.css:
- `.listora-form-field`
- `.listora-form-field__label`
- `.listora-form-field__input` (with `--invalid` / `--readonly` / `--required` modifiers)
- `.listora-form-field__error`
- `.listora-form-field__hint`

Then step templates use `.listora-form-field` consistently. Likely saves 100-200 lines across step CSS.

### G3-03 (ADVISORY) — step-duplicate-review at 14 BEM elements feels over-engineered for a guard

step-duplicate-review's purpose is "we found similar listings, are you sure?". 72 lines with 14 BEM elements suggests it grew its own UI vocabulary. Could likely be 1 alert card + a list of similar listings (~30 lines, 4 BEM elements).

**Recommendation:** simplify after G3-02 — once `.listora-card--warning` exists as a primitive, the dup-review step is just a list of `.listora-card` items inside the warning frame.

### G3-04 (ADVISORY) — flatpickr vendored at 4.6.13 — track update cadence

The Business Hours time picker. Vendored to ship a specific version. Custom JS layer (`initBusinessHoursPickers`) attaches with `data-listora-flatpickr-attached` flag. Should we track upstream flatpickr releases?

**Recommendation:** add a TODO in CLAUDE.md to re-evaluate every 12 months. Current 4.6.13 has no known critical bugs.

### G3-05 (ADVISORY) — 24 hex literals + 23 distinct px values in submission style.css

Worst of G3. Most are validation-state colors (success green, error red, warning amber for required-but-empty fields) — could all map to `--listora-{success,danger,warning}*` tokens.

### G3-06 (FUTURE) — Wizard doesn't auto-save drafts

If a customer fills 5 steps then accidentally closes the tab, they lose progress. The dashboard "Drafts" tab exists but the wizard doesn't write to it until "Save Draft" is explicitly clicked.

**Recommendation:** debounced auto-save every 30s once required fields on a step are filled. Out of scope for this audit but worth noting.

---

## Summary

| # | Severity | Title | Effort |
|---|---|---|---|
| G3-01 | BLOCK | Extract field-display partial (step-preview vs source steps duplication) | 2-3 h |
| G3-02 | BLOCK | Define canonical form-field primitives in shared.css | 3-4 h |
| G3-03 | ADVISORY | Simplify step-duplicate-review after G3-02 | 1 h |
| G3-05 | ADVISORY | 24 hex literals + 23 px values → tokens | 2-3 h |
| G3-06 | FUTURE | Wizard auto-save drafts | 4-8 h feature |

**Total G3 effort: 1 day for the BLOCK items, plus ~half day cleanup.**
