---
journey: compare-one-renderer-one-table
plugin: wb-listora
priority: high
covers:
  - BC 10208510133
likely_files:
  - ../wb-listora-pro/includes/features/class-comparison.php
  - ../wb-listora-pro/blocks/comparison/render.php
  - ../wb-listora-pro/blocks/comparison/style.css
---

# Compare: one renderer, one table, one aligned edge

Three changes, all verifiable in the browser.

**One renderer.** `[listora_compare]` was a second, independent implementation
of the same table. It had drifted in ways nobody filed, because almost nobody
runs the shortcode day to day. It now renders the block.

**One table.** The block split the selection into a separate table per listing
type. Pick a Restaurant and a Cafe — the common case — and you got two stacked
tables of one listing each, which is not a comparison.

**One aligned edge.** The header cell was centred while the values below were
not, and the thumbnail's `margin: 0 auto` centred the image even where text
alignment was overridden.

## Steps

1. Compare two listings of DIFFERENT types on the block page.
   - **Expect:** ONE table, both listings side by side, fields unioned with `—`
     where one does not apply.
   - **Expect:** rows present on BOTH listings come first; partially-present
     rows follow. Rows empty for every listing are omitted entirely.
   - **Fail if:** two tables appear. That was the old behaviour.
2. Same URL via `[listora_compare]` on any page.
   - **Expect:** byte-comparable structure. Assert equal counts of
     `<table class="listora-comparison-table`, `data-listora-compare-table`,
     and `listora-compare-row`.
   - **Fail if:** the shortcode lacks `data-listora-compare-table`. `view.js`
     uses it to hide the server table before drawing the client one, so
     without it removing a column leaves the STALE table on screen under the
     new one.
3. Select only ONE listing, both entry points.
   - **Expect:** the same rich empty card — icon, "Compare Listings
     Side-by-Side", and a Browse Listings CTA.
   - **Fail if:** either shows a bare `<p>`. That was the shortcode's old
     empty state, with different wording.
4. Alignment: measure the thumbnail, title and rating in each header cell.
   - **Expect:** identical left offsets, and matching the value cells beneath.
     Scanning ACROSS columns is the whole point of the table, so a ragged edge
     per column defeats it.
   - Check RTL too — the rule uses `text-align: start` and `margin-inline`.
5. Owners who genuinely need per-type splitting:
   `add_filter( 'wb_listora_pro_compare_group_by_type', '__return_true' )`.
   - **Expect:** the old grouped rendering returns.
