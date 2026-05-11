# G6 Pro Extensions — Audit

**Audited:** 5 Pro blocks (comparison · credit-purchase · moderator-queue · needs-grid · post-need) + 5 Pro templates (`needs-grid`/`need-card` + `post-need` + `credit-purchase` + `need-detail`).

**Total surface:** Pro block code: 1,029 render + 1,062 CSS = **2,091 lines**. Plus templates: 934 lines.

**Headline:** Pro inherits all of Free's token system and shared infrastructure (Pro has NO `src/shared/` of its own — uses Free's at runtime). Two structural concerns: **(a) Pro renders use 0 BEM `__` patterns in 4 of 5 blocks** (the markup lives in templates, but the consistency story differs from Free), and **(b) Pro has 121 hex literals across 5 blocks** (avg 24/block — higher than Free's average of 10/block).

---

## Pro block inventory

| Pro block | render.php | style.css | BEM__ in render | template files | hex literals |
|---|---|---|---|---|---|
| comparison | 399 | 182 | 0 | (none — render inlines) | 18 |
| credit-purchase | 133 | 201 | 0 | credit-purchase.php (136) | 22 |
| moderator-queue | 121 | 119 | 8 | (none) | 14 |
| needs-grid | 233 | 331 | 0 | needs-grid.php (109) + need-card.php (94) | 40 |
| post-need | 143 | 229 | 0 | post-need.php (265) | 27 |

Plus:
- Pro template `need-detail.php` (330 lines) — used by Need_Detail handler for `/need/<slug>/`
- Inline `<script>` in comparison (11 lines) — the floating compare bar localStorage handler

---

## Issues

### G6-01 (BLOCK) — Pro has no `src/shared/` directory (architectural divergence from Section 15)

Per block-quality-standard.md Section 15: "Every Wbcom plugin with Gutenberg blocks MUST have a `src/shared/` directory." Pro has 5 blocks but `src/shared/` does NOT exist (verified earlier).

In practice Pro consumes Free's `src/shared/` via JS imports at build time — this is the right architectural call for free+pro pairs. But it's an undocumented divergence from the portfolio standard.

**Recommendation:** add a one-line `references/shared-pattern-for-pairs.md` to wp-plugin-development skill that documents "free+pro pairs may have only one src/shared/ in Free; Pro imports across plugin boundary." This formalizes the pattern + closes the documentation gap.

### G6-02 (BLOCK) — Pro renders use very little BEM `__` pattern

Of 5 Pro blocks, only moderator-queue uses 8 BEM `__` patterns directly in render.php. The other 4 blocks delegate all markup to templates OR inline plain semantic markup without strict BEM. Free side: every block has explicit BEM in render.php for the wrapper + key elements.

Sample of needs-grid render.php (233 lines): block-wrapper-attrs + IAPI state init + then `include` of the template. The template has BEM, but the render.php wrapper itself uses `class="wp-block-listora-pro-needs-grid"` (apiVersion 3 auto-class) without Pro's own root class.

**Recommendation:** every Pro render.php should explicitly set wrapper class:

```php
$wrapper_attrs = get_block_wrapper_attributes( array(
    'class' => 'listora-pro-needs-grid listora-pro-needs-grid--' . $variant,
) );
```

Standardizes the BEM root and lets CSS scope to `.listora-pro-needs-grid` reliably. Cheap fix per block (~3 lines each).

### G6-03 (BLOCK) — needs-grid worst Pro offender: 40 hex literals + 331 CSS lines

Largest Pro block both in CSS and hex pollution. Likely status badges (open/in-progress/fulfilled), urgency markers (urgent/normal), budget pill colors, response-count badge.

**Recommendation:** migrate all 40 hex to `--listora-{success,warning,danger,info,premium}*` tokens. Same pattern as Free G1.

### G6-04 (BLOCK) — Pro template files mostly live under `templates/blocks/` but moderator-queue + comparison don't

Templates exist for: needs-grid (2 files), post-need (1), credit-purchase (1), need-detail (1 — non-block template). But comparison + moderator-queue have NO template files — their markup is inlined in render.php.

That asymmetry means:
- Themes can override needs-grid markup via `{theme}/wb-listora-pro/blocks/needs-grid/needs-grid.php` ✓
- Themes CANNOT override comparison or moderator-queue ✗

**Recommendation:** extract comparison render markup into `templates/blocks/comparison/comparison.php` + `comparison-column.php` (the per-listing column). Same for moderator-queue → `moderator-queue.php` + `queue-row.php`.

### G6-05 (BLOCK) — comparison render.php has inline `<script>` (the floating-bar localStorage handler)

Same Part 8.0 violation as Free's listing-detail (G7 F-07 / G2 G2-01). 11 lines of inline JS that handles localStorage read/write + URL sync.

**Recommendation:** move to `wb-listora-pro/assets/js/comparison-bar.js`, register-only, enqueue when comparison block renders.

### G6-06 (ADVISORY) — credit-purchase block has 22 hex + 201 CSS lines

For a single-purpose "Buy Credits" UI, 22 hex literals is high. Likely pricing card backgrounds, "Most Popular" badge, savings %, payment-method icons.

### G6-07 (ADVISORY) — post-need template at 265 lines is comparable to Free's heavyweight templates

post-need.php at 265 lines is similar to Free's listing-grid's all-templates-combined OR listing-detail's gallery+sidebar combined. Likely renders: form (title/description/category/budget/deadline) + preview + submit. The form alone is probably 150-180 lines.

**Recommendation:** extract `post-need-form.php` + `post-need-preview.php` sub-templates. Same pattern as G3 step splits.

### G6-08 (ADVISORY) — need-detail template at 330 lines (largest Pro template)

`templates/blocks/need-detail/need-detail.php` — renders `/need/<slug>/` page with the need's full content + response list + respond form (for vendors) + accept/reject UI (for need-creator). All in one file.

**Recommendation:** split into header / body / responses / respond-form sub-templates (similar to listing-detail's gallery/sidebar/tabs split in G2).

### G6-09 (ADVISORY) — Pro blocks don't use canonical card primitives (`.listora-ui-card`)

Same as Free's G7 F-03. needs-grid's `need-card` template + credit-purchase's pricing cards + moderator-queue rows could all use the canonical card primitive. They don't.

### G6-10 (FUTURE) — Pro feature toggles allow the customer to disable Pro blocks but block.json registers them unconditionally

A customer toggles off `comparison` in Pro Features → the comparison block class doesn't load, but `block.json` registration still publishes the block to Gutenberg. If a page already had the block embedded, it now renders as "Block has been deleted" in the editor.

**Recommendation:** Pro should `unregister_block_type('listora-pro/comparison')` when the toggle is off. Or document this as expected behavior (don't toggle off blocks that customers have embedded on pages).

---

## Summary

| # | Severity | Title | Effort |
|---|---|---|---|
| G6-01 | BLOCK | Document free+pro shared-pattern in wp-plugin-development | 30 min doc |
| G6-02 | BLOCK | Standardize BEM root class on all Pro render.php wrappers | 1 h |
| G6-03 | BLOCK | needs-grid 40 hex → tokens | 3-4 h |
| G6-04 | BLOCK | Extract comparison + moderator-queue templates | 4-6 h |
| G6-05 | BLOCK | Move comparison inline JS to enqueued file | 1 h |
| G6-06 | ADVISORY | credit-purchase 22 hex → tokens | 1-2 h |
| G6-07 | ADVISORY | Split post-need template | 2-3 h |
| G6-08 | ADVISORY | Split need-detail template | 2-3 h |
| G6-09 | ADVISORY | Adopt canonical card primitive in Pro blocks (paired with G7 F-03) | depends on G7 F-03 |
| G6-10 | FUTURE | Toggle-off should unregister block | 30 min |

**Total G6 effort: 1-2 days for BLOCK items + ~half day cleanup. Pairs naturally with the G7 foundation work and Free G1-G5 fixes.**
