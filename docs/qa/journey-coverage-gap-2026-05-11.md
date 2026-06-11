# Journey Coverage Gap — 2026-05-11

Goal: every feature has a small (~60-100 line) journey that verifies all three axes — **data flow** (option save → DB → render), **functionality flow** (anon → member → admin round-trip), **developer flow** (hook fires, filter override accepted, REST shape stable).

## Current state

| Plugin | Journeys | Features (manifest) | Coverage |
|---|---|---|---|
| Free | 30 (10 customer + 10 admin + 10 regression) | ~30 customer-facing flows | ~75% |
| Pro | 23 (11 customer + 11 admin + 1 system) | 29 toggleable + 10 core | ~50% |
| **Combined** | **53** | **~70 flows** | **~65%** |

Combo smoke walks only what's authored. Untested features get no signal.

## Pro gaps (10 missing journeys)

| # | Feature | Journey to author | Path | Priority |
|---|---|---|---|---|
| 1 | google_maps | `customer/12-google-maps-render.md` | Map renders with Google provider, marker click opens popup w/ image, custom style applied | high |
| 2 | advanced_search (Pro side) | `customer/13-saved-search-crud.md` | Member saves a search, alert email fires on new match | high |
| 3 | analytics | `admin/11-analytics-dashboard.md` | Dashboard tile renders with seeded events; cleanup cron runs | high |
| 4 | white_label | `admin/12-white-label-branding.md` | Admin overrides Free's brand; menu icon + footer text swap | normal |
| 5 | notification_digest | `admin/13-notification-digest.md` | Digest groups N notifications; cron fires daily | high |
| 6 | seo_pages | `customer/14-seo-pages-render.md` | Programmatic SEO page renders with auto-generated meta | normal |
| 7 | pricing_plans (standalone) | `admin/14-pricing-plan-assign.md` | Plan CPT created, assigned to listing, expiry calculated | high |
| 8 | services_pro | `customer/15-service-booking-cta.md` | Service card shows booking CTA, clicking fires the booking hook | high |
| 9 | field_mapper (visual-import) | `admin/15-visual-import-csv.md` | Wizard: upload → map → preview → import → success report | high |
| 10 | google_places | `admin/16-google-places-import.md` | Google Places search → preview → import → listing created | normal |

## Free gaps (5 missing journeys)

| # | Feature | Journey to author | Path | Priority |
|---|---|---|---|---|
| 1 | Dashboard overview tab | `customer/11-dashboard-overview.md` | Stats accurate vs DB (listings count, reviews, claims, favourites) | high |
| 2 | Dashboard profile tab | `customer/12-dashboard-profile.md` | Profile edit save round-trip; avatar upload | normal |
| 3 | Dashboard claims tab | `customer/13-dashboard-claims.md` | Owner sees own claim history with status | normal |
| 4 | Email send: listing approved | `regression/email-approval-send.md` | Approve listing → email log row + recipient owner | high |
| 5 | Role/capability matrix | `admin/11-role-cap-matrix.md` | Each role (admin/editor/contributor/subscriber) can/cannot do X | critical |

## Triplet content requirement (every new journey)

Each journey MUST include at least:
- **Data flow:** 1 SQL assertion (DB row exists / option value persisted) or REST GET that confirms write
- **Functionality flow:** at least 1 anon-or-member action and 1 admin action
- **Developer flow:** at least 1 hook/filter assertion OR 1 REST response-shape assertion + entry in the Fail diagnostics table pointing at the file:line for failure

## Execution order

1. Author 15 journeys inline (this session). Author 3 at a time, commit per batch.
2. Run `composer journeys:dry-run` to verify all parse.
3. Run `/wp-plugin-smoke combo` (Sonnet sub-agent, ~25 min).
4. Triage `failures[]` + `debug_log_issues[]`.
5. Fix real defects journey-by-journey.

## Coverage after this pass

| Plugin | Authored | Total flows | Coverage |
|---|---|---|---|
| Free | 35 | ~30 | 100% of identified flows |
| Pro | 33 | ~39 | 85% (Pro core features that have no UI like Webhook_Receiver don't need standalone customer journey — covered by system/01) |
| **Combined** | **68** | **~70** | **~97%** |

Remaining 3% = features with no customer-visible flow (License internals, Webhook_Receiver internals beyond HMAC test).
