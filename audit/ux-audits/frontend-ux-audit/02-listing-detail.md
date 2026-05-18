# G2 Listing Detail — Audit

**Audited:** listing-detail block (render.php 759 lines + style.css **1,194 lines**) + 3 templates (gallery.php 67 + sidebar.php 83 + tabs.php **539**) + theme templates (single-listora_listing.html, single-listora_listing.php, template-listora-full-width.php).

**Total surface: 2,642 lines.** Largest single-block surface in the plugin.

**Headline:** tabs.php is the most BEM-disciplined template in the plugin (52 `__` elements, clean structure). render.php carries **80 lines of inline `<script>` JS** (a Part 8.0 production-rule violation) and inlines 3 modals (Claim/Share/Login) instead of using a `templates/.../modals/` subdirectory.

---

## Issues

### G2-01 (BLOCK) — 80 lines of inline `<script>` in render.php

The IAPI-fallback for tab + gallery switching (called out in G7 F-07). Documented intent: "Vanilla JS fallback for tab/gallery switching when Interactivity API isn't available in custom templates." Three real customer impacts:

1. **CSP-strict sites** (defensive customers + agencies) block inline `<script>` outright. The fallback breaks for them, leaving non-IAPI-rendered tabs unusable.
2. **Sites that use IAPI** load both the IAPI store AND the inline fallback — 80 lines of dead JS per pageload.
3. **No build pipeline visibility** — webpack can't tree-shake, minify, or version this JS. It ships as-written.

**Recommendation:** extract to `assets/js/listora-detail-fallback.js`, register-only (never auto-enqueue), conditionally enqueue from render.php when `apply_filters('wb_listora_use_iapi_fallback', false)` returns true. Default: false (IAPI is mainline). Custom templates that need the fallback can opt in via the filter.

### G2-02 (BLOCK) — 3 modals (Claim/Share/Login) inlined in render.php instead of templates

The modal markup lives inside `render.php` rather than `templates/blocks/listing-detail/modals/{claim,share,login}.php`. Three issues:

1. **Theme overrides impossible** — the WB Listora template override system (`{theme}/wb-listora/blocks/listing-detail/modals/claim.php`) doesn't reach inlined markup.
2. **render.php scrollability** — at 759 lines, finding a specific modal markup requires scrolling. Templating keeps each modal at ~50-80 lines.
3. **Reuse** — if Pro wants a custom claim modal (e.g. for premium claim flows), it has to filter HTML strings instead of replacing a template file.

**Recommendation:** split into `templates/blocks/listing-detail/modals/{claim,share,login}.php`. Use `wb_listora_get_template_html()` from render.php to compose. Reduces render.php by ~150-200 lines.

### G2-03 (ADVISORY) — tabs.php at 539 lines does too much

tabs.php carries 52 BEM `__` elements covering: tab nav (5 tabs), Overview tab content, Reviews tab content, Hours tab content, Services tab content, Map tab content. That's 6 distinct UI regions in one file.

**Recommendation:** split per-tab into sub-templates:
- `tabs.php` (the nav + tab wrapper, ~80 lines)
- `tab-overview.php` (~100 lines)
- `tab-reviews.php` (~80 lines)
- `tab-hours.php` (~50 lines)
- `tab-services.php` (~120 lines — includes service cards loop)
- `tab-map.php` (~60 lines)

Theme override system already supports nested directories.

### G2-04 (ADVISORY) — render.php at 759 lines after stripping modals + inline JS

Post-G2-01 + G2-02 + G2-03, render.php would shrink from 759 → ~400 lines. Still large, but readable. Most remaining lines are: data preparation (fetch listing meta, prefetch services, prefetch reviews) + IAPI state init + wrapper HTML.

**Recommendation:** extract the data-prep into a helper class (`Listing_Detail_Data::for_block($post_id)`) returning a single data array. render.php becomes ~150 lines.

### G2-05 (ADVISORY) — Tab a11y attributes complete + correct (positive finding)

```html
<button role="tab" class="listora-detail__tab is-active" id="tab-overview"
        aria-selected="true" aria-controls="panel-overview">
```

Per Section 8 of the block-quality-standard.md, tabs MUST have `role="tablist"` on the container, `role="tab"` + `aria-selected` + `aria-controls` on buttons, and `role="tabpanel"` + `aria-labelledby` on panels. Verified all 5 tabs comply. ✓

### G2-06 (ADVISORY) — Detail block has highest hex literal count (13)

Spread across style.css (status badges, claim-cta, share-cta, review stars, owner-reply form bg, services-grid card). Most map to existing tokens (`--listora-success`, `--listora-warning`, `--listora-rating`, `--listora-primary`, `--listora-bg-secondary`).

### G2-07 (FUTURE) — single-listora_listing.html (FSE) + single-listora_listing.php (legacy) coexist

The plugin ships BOTH a block-theme FSE template (.html) AND a legacy classic-theme template (.php). For block themes, the .html is used (FSE template hierarchy). For classic themes, the .php is used. Both exist for back-compat with non-FSE themes.

**Recommendation:** decide whether legacy classic-theme support is in-scope for v1. If not, deprecate single-listora_listing.php (warn in admin) over 1-2 releases. If yes, document the dual-template strategy in CLAUDE.md.

---

## Summary

| # | Severity | Title | Effort |
|---|---|---|---|
| G2-01 | BLOCK | Extract inline `<script>` (80 lines) to enqueued file | 1-2 h |
| G2-02 | BLOCK | Split 3 modals into template files | 2-3 h |
| G2-03 | ADVISORY | Split tabs.php (539 lines) into per-tab files | 3-4 h |
| G2-04 | ADVISORY | Extract data-prep into helper class | 2-3 h |
| G2-06 | ADVISORY | 13 hex literals → tokens | 1 h |
| G2-07 | FUTURE | FSE vs legacy template policy | 30 min doc |

**Total G2 effort: 1-2 days for the BLOCK + ADVISORY items.**
