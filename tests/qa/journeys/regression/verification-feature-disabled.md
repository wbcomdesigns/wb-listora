---
journey: verification-feature-disabled
plugin: wb-listora
priority: high
roles: [anonymous, administrator]
covers: [verification-feature-toggle, verified-flag-gating, verified-badge-display, is-verified-rest, listing-detail-block-resolver]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free + Pro both active (verification is a Pro feature)"
  - "A published listing with _listora_is_verified=1 exists (capture as LISTING_ID + slug)"
  - "Pro verification feature ON at start"
estimated_runtime_minutes: 6
covers_card: 9911539296
covers_commits: [pr-71, efcab2e]
---

# Verification feature toggle hides the verified flag on ALL surfaces

Regression sentinel for BUG-01. Verification is a Pro feature, but Free reads
`_listora_is_verified` meta in 5 places (card badge, detail flag, search index,
two REST controllers). The original bug: those reads went straight to the meta
with no feature gate, so a verified badge / `is_verified:true` kept showing
after an admin disabled the Pro verification feature. The meta is intentionally
left intact (re-enabling restores the badge) — only the *resolved* flag must
flip.

## Setup

- Site: `$SITE_URL`
- Free routes every verified read through `wb_listora_is_verified( $post_id )`
  (in `includes/class-features.php`), which applies the `wb_listora_is_verified`
  filter.
- Pro hooks that filter on the ALWAYS-loaded path
  (`class-pro-plugin.php::gate_verified_on_feature`) and returns false when the
  `verification` feature toggle is off. The gate cannot live in the Verification
  feature class itself — Feature_Manager never instantiates that class when the
  toggle is off.
- Toggle lives in the `wb_listora_pro_features` option (key `verification`).

## Steps

### 1. Baseline (verification ON)
- **Action**: `wp eval "echo wb_listora_is_verified(LISTING_ID);"` and
  `GET /listora/v1/listings/<ID>/detail`.
- **Expect**: helper returns truthy; REST `is_verified` is `true`; verified
  badge renders on the listing card + detail page.

### 2. Disable the verification feature
- **Action**: Pro Settings → Features → uncheck "Verification" → save (or set
  `wb_listora_pro_features['verification']=false`). Flush cache.
- **Expect (ALL must report not-verified, meta UNCHANGED)**:
  - `wp post meta get <ID> _listora_is_verified` still returns `1`
  - `wb_listora_is_verified(<ID>)` returns false
  - `GET /listings/<ID>/detail` → `is_verified:false`
  - `GET /listings/<ID>` (list prepare) → `is_verified:false`
  - `GET /listora/v1/search` result for the listing → `is_verified:false`
  - verified badge absent from the card + detail page
  - next search re-index writes `is_verified=0` for the row
- **On fail**: the 6 read sites — `includes/class-template-helpers.php` (card
  badges), `includes/search/class-search-indexer.php`,
  `includes/rest/class-listings-controller.php` (×2: list + detail),
  `includes/rest/class-search-controller.php`,
  `blocks/listing-detail/render.php` (line 219 — added in `efcab2e`). All must call
  `wb_listora_is_verified()`, never read the meta directly.

### 2b. Detail block render — verified badge gates with feature toggle (BC 9911539296 / efcab2e)
- **Why**: the listing-detail block previously read `_listora_is_verified` meta
  directly at `blocks/listing-detail/render.php:216`, bypassing the resolver.
  When verification was disabled, every OTHER surface hid the badge but the
  single-listing detail page kept showing it.
- **Action** (with feature still OFF from step 2):
  - Visit `$SITE_URL/listing/<slug>/` in an incognito window (anonymous).
  - Inspect for `.listora-detail__verified-badge` or `verified` text in the
    listing header / sidebar.
- **Expect**: badge NOT present in DOM. The detail block's `$is_verified`
  variable resolves to false via `wb_listora_is_verified( $post_id )`.
- **Regression check** (re-enable verification, re-visit): badge returns. Toggle
  changes propagate to the detail block on the very next page-load (no listing
  re-save required).

### 3. Re-enable + verify return
- **Action**: re-enable Verification; flush cache.
- **Expect**: helper + all 4 REST surfaces report `is_verified:true` again; badge
  returns. Meta was never mutated, so no re-save of the listing is required.
