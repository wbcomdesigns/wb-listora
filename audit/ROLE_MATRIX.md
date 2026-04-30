# WB Listora — Role Permission Matrix

**Generated:** 2026-04-30 (PM refresh — 17:30Z)
**Source:** `includes/core/class-capabilities.php` + [`manifest.json`](manifest.json) `capabilities[]` (schema v2.1)

**PM-refresh note (no role/cap deltas):** the 2026-04-30 PM commits (T1+T4 `f69f47f`, F1 `0aa62ca`, O3 `847dcc8`) introduced no new capabilities, no new role assignments, and no changes to the existing `meta`/primitive cap classifications. T4 explicitly classifies `class-pro-promotion.php:1188 ajax_dismiss_promo` as a verified false positive for the wppqa nonce-no-cap rule — the action is registered as `wp_ajax_*`-only (no `_nopriv_`), so WordPress core gates it to logged-in users upstream, and the handler only writes a per-user 3-day cookie (no shared mutation). Adding `current_user_can()` would over-restrict (CTA targets all logged-in users). See `manifest.json#/notes`.

Legend: **C** Create · **R** Read · **U** Update · **D** Delete · **—** No access · **own** = own records only

> **v2 schema note:** The manifest now classifies each cap as `meta` (requires object context) or primitive. The 2 meta caps below are `edit_listora_listing` and `delete_listora_listing` — never check these without a post ID. Taxonomy panels deliberately use the **plural primitives** (`edit_listora_listings`, `manage_listora_types`) so Gutenberg's no-context check passes.

---

## Custom Capabilities (Free)

| Capability | Admin | Editor | Author | Contributor | Subscriber |
|---|---|---|---|---|---|
| `edit_listora_listings` | ✓ | ✓ | ✓ | ✓ | — |
| `edit_others_listora_listings` | ✓ | ✓ | — | — | — |
| `edit_published_listora_listings` | ✓ | ✓ | ✓ | — | — |
| `publish_listora_listings` | ✓ | ✓ | — | — | — |
| `delete_listora_listings` | ✓ | ✓ | ✓ | ✓ (own) | — |
| `delete_others_listora_listings` | ✓ | ✓ | — | — | — |
| `delete_published_listora_listings` | ✓ | ✓ | — | — | — |
| `read_private_listora_listings` | ✓ | ✓ | — | — | — |
| `manage_listora_settings` | ✓ | — | — | — | — |
| `moderate_listora_reviews` | ✓ | ✓ | — | — | — |
| `manage_listora_claims` | ✓ | ✓ | — | — | — |
| `manage_listora_types` | ✓ | — | — | — | — |
| `submit_listora_listing` | ✓ | ✓ | ✓ | ✓ | ✓ |

---

## Feature Access Matrix

| Feature | Admin | Editor | Author | Contributor | Subscriber | Logged-Out |
|---|---|---|---|---|---|---|
| Browse listings | R | R | R | R | R | R |
| Search & facets | R | R | R | R | R | R |
| View listing detail | R | R | R | R | R | R |
| Submit listing (frontend) | C | C | C | C | C | — |
| Edit own listing | CRUD (own) | CRUD (own) | CRUD (own) | CRU (own) | CRU (own) | — |
| Edit any listing | CRUD | CRUD | — | — | — | — |
| Publish listing directly | C+pub | C+pub | — (pending) | — (pending) | — (pending) | — |
| Soft-delete own listing | D (own) | D (own) | D (own) | D (own) | D (own) | — |
| Renew expired listing | own | own | own | own | own | — |
| Feature own listing (paid) | own | own | own | own | own | — |
| Write a review | C | C | C | C | C | — |
| Update own review | own | own | own | own | own | — |
| Delete own review | own | own | own | own | own | — |
| Helpful vote | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Owner reply to reviews | own listings | own listings | own listings | own listings | own listings | — |
| Report review | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Moderate reviews | CRUD | CRUD | — | — | — | — |
| Submit business claim | C | C | C | C | C | — |
| Approve/reject claims | CRUD | CRUD | — | — | — | — |
| Add to favorites | C | C | C | C | C | — |
| Manage favorites | CRUD (own) | CRUD (own) | CRUD (own) | CRUD (own) | CRUD (own) | — |
| Saved searches (Pro) | CRUD (own) | CRUD (own) | CRUD (own) | CRUD (own) | CRUD (own) | — |
| Manage taxonomies (cat/loc/feature) | CRUD | — | — | — | — | — |
| Manage listing types | CRUD | — | — | — | — | — |
| Plugin settings | CRUD | — | — | — | — | — |
| Setup wizard | ✓ | — | — | — | — | — |
| Health check | ✓ | — | — | — | — | — |
| Import/Export (CSV/JSON/GeoJSON) | ✓ (manage_options) | — | — | — | — | — |
| WP-CLI `wp listora *` | shell user | shell user | — | — | — | — |
| Frontend dashboard (`user-dashboard` block) | own data | own data | own data | own data | own data | — |

---

## REST Permission Callback Reference

| Endpoint family | Permission | Failure code |
|---|---|---|
| Public read (listings list/detail/related/search/types/maps/app-config) | `__return_true` | n/a |
| Logged-in (favorites, dashboard, claim submit, helpful, report) | `is_user_logged_in()` else WP_Error 401 | 401 |
| Owner-only writes (edit/delete listing, owner reply, renew) | author match else WP_Error 403 | 403 |
| Submission (POST /submit) | `submit_listora_listing` | 403 |
| Settings + Notifications | `manage_listora_settings` | 403 |
| Claims approve/reject | `manage_listora_claims` (≈ admin/editor) | 403 |
| Types CRUD | `manage_listora_types` | 403 |
| Import/Export | `manage_options` | 403 |

All write endpoints fire `wb_listora_before_<op>` (filter) — extensions can return `WP_Error` to add additional veto rules without modifying core permission callbacks.

---

## Pro Capability Additions

Pro does **not** add custom WP roles; it gates features by license + per-feature toggle (`wb_listora_pro_features_enabled`). Admin-only Pro pages (Transactions, Analytics, Tools, Badges, Coupons, Audit Log, Webhooks, Reverse Listings) all check `manage_options`.
