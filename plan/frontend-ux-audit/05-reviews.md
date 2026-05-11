# G5 Reviews — Audit

**Audited:** listing-reviews block (render.php 105 + style.css 391) + 3 templates (reviews.php 140 + review-card.php 100 + review-form.php 104) + Reviews tab inside listing-detail/tabs.php (~80 lines).

**Total surface: 920 lines across the standalone reviews block + Reviews tab in listing-detail.**

**Headline:** Reviews UI exists in **TWO places** — the standalone `listora/listing-reviews` block AND the Reviews tab in listing-detail/tabs.php. Both render review cards. The risk: drift between the two surfaces. Audit confirms: review-card.php (100 lines, 18 BEM) is shared via `wb_listora_get_template_html('listing-reviews/review-card', $data)`, so the visual consistency holds. But review-form.php (the write-review UI) only exists for the standalone block — the Reviews tab in detail doesn't use it.

---

## Inventory

| File | Lines | BEM `__` | Used by |
|---|---|---|---|
| blocks/listing-reviews/render.php | 105 | 0 (wrapper-only) | standalone block on any page |
| blocks/listing-reviews/style.css | 391 | n/a | both standalone block + Reviews tab in detail |
| templates/blocks/listing-reviews/reviews.php | 140 | 17 | standalone block — reviews list |
| templates/blocks/listing-reviews/review-card.php | 100 | 18 | shared — both standalone block AND Reviews tab in detail |
| templates/blocks/listing-reviews/review-form.php | 104 | 12 | standalone block only |
| templates/blocks/listing-detail/tabs.php (Reviews tab section) | ~80 | (counted as part of tabs.php's 52) | listing-detail Reviews tab |

---

## Issues

### G5-01 (BLOCK) — Reviews tab in detail does NOT render the write-review form

Customers viewing a listing detail expect to scroll to Reviews and write one inline. Instead, they see only the list. To write a review, they have to scroll to the listing detail's sidebar (which shows a "Write a review" button) OR navigate elsewhere.

Comparing to the standalone `listing-reviews` block: it renders reviews.php (list) + review-form.php (write UI) together. The Reviews tab in listing-detail/tabs.php renders ONLY the list — the form is missing.

**Recommendation:** include review-form.php in the Reviews tab. Move write-review CTA from sidebar (cluttered) to inline above the list.

### G5-02 (BLOCK) — Duplicate reviews list logic between block and detail tab

reviews.php (140 lines) loops `wb_listora_reviews` for a listing and renders cards. listing-detail/tabs.php's Reviews tab section (~80 lines) does the SAME loop with the SAME card template, just inside a different wrapper.

**Recommendation:** make the standalone block re-usable. listing-detail/tabs.php Reviews tab section becomes: `do_action('wb_listora_render_reviews_list', $post_id)` — Free's listing-reviews handler renders the list, the standalone block's rendering becomes the single source of truth.

### G5-03 (BLOCK) — review-form.php (104 lines) has no a11y form structure

review-form.php inputs use no `<fieldset>` or `aria-labelledby` to associate the star rating with the rest of the form. A screen reader reads each star button independently without context.

**Recommendation:** wrap the form in `<fieldset>` with `<legend>` for the listing name. Group the star inputs with `role="radiogroup"` + `aria-labelledby="rating-label"`. Each star is `role="radio"` with `aria-checked`. Same a11y pattern Section 8 of block-quality-standard.md requires.

### G5-04 (ADVISORY) — listing-reviews style.css uses `--listora-rating` token correctly

✓ Star colors reference `--listora-rating` (gold) from shared.css. No hex literals for stars. Good token usage.

### G5-05 (ADVISORY) — Helpful vote button has 0 hover state in standalone but has one in detail tab

Standalone reviews.php: `<button class="listora-reviews__helpful-btn">Helpful (N)</button>` — no `:hover` rule in style.css.
Detail tabs.php Reviews tab: same button with `.listora-detail__helpful-btn` — has `:hover` rule.

Visual inconsistency. The same button looks one way in one place, another way in another.

**Recommendation:** rename the standalone class to match the detail's class OR introduce a shared `.listora-helpful-btn` primitive that both surfaces use.

### G5-06 (ADVISORY) — Owner reply form is inline-rendered but only in detail tab

After the 2026-04-30 fix (commit e01486b), owner-reply uses an inline form (not a modal). That fix lives in dashboard tab-reviews.php (G4). The standalone listing-reviews block doesn't render owner-reply inline — owners viewing the standalone block can't reply from there.

**Recommendation:** add the owner-reply form to standalone reviews.php (it's already in tab-reviews.php and detail tab — extract to shared partial).

### G5-07 (FUTURE) — Pro `multi_criteria_reviews` and `photo_reviews` extend the form but not the read view consistently

When a listing has criteria (Food/Service/Ambiance for Restaurant), the WRITE form shows multi-star inputs. The READ view (review-card.php) is supposed to show the criteria breakdown. Verify visually.

---

## Summary

| # | Severity | Title | Effort |
|---|---|---|---|
| G5-01 | BLOCK | Add review-form to Reviews tab in listing-detail | 1-2 h |
| G5-02 | BLOCK | De-duplicate reviews list logic (action-based composition) | 2-3 h |
| G5-03 | BLOCK | Add fieldset/legend/radiogroup a11y to review-form | 1 h |
| G5-05 | ADVISORY | Shared helpful-btn primitive | 30 min |
| G5-06 | ADVISORY | Owner-reply inline in standalone reviews | 1 h |
| G5-07 | FUTURE | Verify Pro criteria breakdown renders in read view | 30 min |

**Total G5 effort: ~half a day for BLOCK items.**
