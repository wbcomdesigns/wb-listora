---
journey: verification-feature-disabled
plugin: wb-listora
priority: high
roles: [anonymous, member, administrator]
covers: [verification-feature-toggle, verified-flag-gating, verified-badge-display, is-verified-rest, listing-detail-block-resolver]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free + Pro both active (verification is a Pro feature)"
  - "A published listing with _listora_is_verified=1 (create a QA-J1- one; capture LISTING_ID + slug)"
  - "Pro verification feature ON at start (option `wb_listora_pro_features['verification']`)"
estimated_runtime_minutes: 7
covers_card: 9911539296
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Verification feature toggle hides the verified flag on ALL surfaces

Verification is a Pro feature, but Free reads `_listora_is_verified` in several places. Every read goes through `wb_listora_is_verified( $post_id )` (`includes/class-features.php:269`), which applies the `wb_listora_is_verified` filter. Pro hooks that filter on the always-loaded path (`wb-listora-pro/includes/class-pro-plugin.php:58`, `gate_verified_on_feature()` at `:325`) and returns false when the `verification` toggle is off. The gate cannot live in the Verification feature class itself because Feature_Manager never instantiates it when the toggle is off. The meta is intentionally left intact (re-enabling restores the flag).

The read sites (all must call the helper, none may read the meta): `includes/class-template-helpers.php:1355` (card badges), `includes/search/class-search-indexer.php:350`, `includes/rest/class-listings-controller.php:792` (item) and `:1103` (`/detail`), `includes/rest/class-search-controller.php:704`, `blocks/listing-detail/render.php:250`.

## Steps

> **Run each toggle state in a fresh process/request.** Flipping the option inside a process that already booted with the feature ON leaves `Verification::add_to_rest_response()` (`class-verification.php:39`) hooked, so `/detail` still reports `true`; that is a test artefact, not a product bug (reproduced and cleared 2026-10-12).

### 1. Baseline (verification ON)
- **Action**: `wp eval 'echo wb_listora_is_verified( <ID> ) ? "yes" : "no";'` and `GET /listora/v1/listings/<ID>/detail`, `GET /listora/v1/listings/<ID>`.
- **Expect**: helper `yes`; `is_verified: true` on both REST routes; on the listing page (`/listing/<slug>/`) the header badge `span.listora-badge.listora-badge--open` with text "Verified" and the sidebar `.listora-verified-detail` ("Verified Business") render.

### 2. Disable the verification feature
- **Action**: Pro Settings > Features > untick "Verification" (or set `wb_listora_pro_features['verification'] = false`), flush cache; then a NEW request/process.
- **Expect** (all, meta unchanged):
  - `wp post meta get <ID> _listora_is_verified` still `1`
  - `wb_listora_is_verified( <ID> )` false
  - `GET /listings/<ID>/detail` -> `is_verified:false`; `GET /listings/<ID>` -> `is_verified:false` (both verified 2026-10-12)
  - `GET /listora/v1/search` entry for the listing -> `is_verified:false`
  - the next search re-index writes `is_verified = 0` for the row
  - on the listing page the header "Verified" badge and `.listora-verified-detail` are absent (verified 2026-10-12 via curl as anonymous; the only remaining `.listora-badge--verified` markup is the hidden quick-view modal template bound to `state.quickView.isVerified`, so assert on the header badge and `.listora-verified-detail`, not on that class)
- **On fail**: one of the read sites above bypasses the resolver.

### 3. Re-enable + verify return
- **Action**: re-enable; flush cache; new request.
- **Expect**: helper, both REST routes and search report `is_verified:true`; the header badge and `.listora-verified-detail` return without re-saving the listing (verified 2026-10-12).

### 4. Role ladder
| Actor | Expect |
|---|---|
| Anonymous | steps 1-3 on the public page and REST |
| Member (owner) | dashboard row for the listing shows no verified chip while OFF |
| Another member | same public view |
| Admin | wp-admin listing edit may still show the verification meta box only when the feature is ON; the REST/page rules apply to admin too (control) |

### 5. Cards
- **Expect** the card badge (`badges.verified`, `class-template-helpers.php:1355`) follows the same toggle in the Directory grid and Featured block; spot-check one card in each state.

### 6. Mobile
At 390 the detail header badge row wraps, no horizontal scroll.

## Cleanup
Restore `wb_listora_pro_features['verification']` to its original value (true on 2026-10-12) and delete the QA-J1- listing.

## Fail diagnostics
| Symptom | File |
|---|---|
| Helper true while OFF | `wb-listora-pro/includes/class-pro-plugin.php:58, 325` |
| `/detail` true while OFF in a fresh process | `includes/rest/class-listings-controller.php:1103` or a filter on `wb_listora_rest_prepare_listing` (`wb-listora-pro/includes/features/class-verification.php:39`, `class-quick-view.php:41`) |
| Badge on the detail page while OFF | `blocks/listing-detail/render.php:250, 463` |
| Search shows verified while OFF | `includes/search/class-search-indexer.php:350`, `includes/rest/class-search-controller.php:704` |

## Not verified
Steps 1-3 REST + page markup were run with wp-cli and curl on 2026-10-12 (anonymous, one throwaway listing, feature restored to ON, listing deleted). The search endpoint, the re-index, card badges (5), dashboard chip, role rows and mobile were not run; browser rendering was not checked.

Verified via: wp-cli REST, curl/DOM.
