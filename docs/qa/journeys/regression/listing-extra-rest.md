---
journey: listing-extra-rest
plugin: wb-listora
priority: normal
roles: [anonymous, subscriber, administrator]
covers: [related-listings, listings-bulk, renewal-quote, services-reorder, settings-app-config]
prerequisites:
  - "Site reachable at $SITE_URL (REST base $SITE_URL/wp-json/listora/v1)"
  - "A published listing with same-type siblings (demo: listing 17 'the-golden-fork', author admin)"
  - "A listing with >=2 services (demo: listing 17 has 3, ids 1,2,3)"
estimated_runtime_minutes: 5
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
changed: true
---

# Sentinel for the lesser-exercised listing REST routes

REST-only journey (no rendered surface; no theme matrix). Verified via `rest_do_request()` under `wp eval` as admin, author, subscriber and logged out.

## Steps

### 1. Related listings
- **Action**: `GET /listings/17/related` (logged out), then `?limit=3`, then `?limit=50`.
- **Expect**: 200, array of card objects with keys `id,title,link,featured_image,rating,listing_type,listing_type_name,is_featured,location`; never contains id 17; `limit=3` returns 3; `limit=50` returns 400 (max 12, default 4).
- **Control**: no `owner` key on these rows (card payloads omit it; only `/detail` carries it).

### 2. Batch fetch by IDs
- **Action**: `POST /listings/bulk` body `{"ids":[17,<pending id>]}` as anonymous, then as admin. (`GET /listings/bulk` is 404: POST only.)
- **Expect**: anonymous 200 with `{listings,total,requested}` containing only 17; admin sees both. Params: `ids` (max 50), `fields` = `card` (default) or `detail`.
- **Note**: distinct from `POST /listings/bulk-moderate` (admin moderation).

### 3. Renewal quote preview (no mutation)
- **Action**: `GET /listings/17/renewal-quote` as anonymous, as a member who does not own 17, as the owner (admin here), and as admin.
- **Expect**: anonymous 401 `listora_unauthorized`; non-owner 403 `listora_forbidden`; owner and admin 200 with `plan_id, plan_name, cost, currency, duration_days, days_until_expiry, is_expired, can_renew_now, renewal_window_days, reason, balance, expiry`. Nothing changes in the DB (the mutation is `POST /listings/{id}/renew`).

### 4. Services reorder
- **Action**: `POST /listings/17/services/reorder` body `{"order":[1,2,3]}` as anonymous, as author1, as the owner/admin.
- **Expect**: 401, 403 (`You do not have permission to manage services for this listing.`), 200 `{"reordered":true}`. Re-submitting the existing order leaves the page unchanged.

### 5. App-config bootstrap
- **Action**: `GET /settings/app-config` logged out.
- **Expect**: 200 with keys `contract_version, plugin_version, rest_namespace, directory_url, submit_url, dashboard_url, per_page, distance_unit, near_me_radius, currency..., listing_statuses, default_country, moderation, enable_*, captcha_provider, is_pro_active, contact_path, app_enabled, min_app_version, branding, legal, features, auth, password_login, languages, timezone`. No `captcha_secret_key`, no `google_maps_key`, no license data anywhere in the body.

## Fail diagnostics
| Symptom | File |
|---|---|
| related wrong/empty | `includes/rest/class-listings-controller.php` `get_related()` (route at ~`:393`) |
| bulk leaks drafts to anonymous | same controller, `/listings/bulk` handler |
| renewal-quote 200 for non-owner | `class-listings-controller.php:417` (route) / `:2042` (`get_renewal_quote`) |
| reorder not owner-gated | `includes/rest/class-services-controller.php:187` |
| app-config leaks a secret | `includes/rest/class-settings-controller.php:93` |
