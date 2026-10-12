---
group: integrations-api
plugin: wb-listora
scope: REST contracts that apps and hosts consume (app-config, app sign-in, listing routes, space routes) and the one screen that governs them (Settings > Advanced > App sign-in)
journeys: 5
site_profile: required
---
# Integrations and API: what an agent checks here

## Scope
Covers the public and member REST contract a native app or host plugin reads: the app-config bootstrap payload, password-for-credential sign-in, the listing envelope/timestamps/decoded strings, related/bulk/renewal-quote/reorder routes, and the space (BuddyNext-style host) showcase and moderation routes. The only browser screen is the owner switch for app password sign-in.

Does NOT cover: who may call a route by role or rate limit (access-security owns role matrix, write gate, rate limits, nonce gate); the dashboard screens that consume the same data (dashboard-account); the Add Listing form beyond its category dropdown (submission); search ranking and filters (directory-search); the Settings screen as a whole (settings-setup). Pro merge into app-config, webhooks and BuddyPress are in the Pro folder.

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | App-config JSON (public bootstrap) | GET `$SITE_URL/wp-json/listora/v1/settings/app-config` | guest, every role | includes/rest/class-settings-controller.php |
| 2 | App password sign-in endpoint | POST `$SITE_URL/wp-json/listora/v1/auth/app-password` | guest (credentials), owner | includes/rest/class-auth-controller.php, includes/auth/class-app-credentials.php |
| 3 | Settings > Advanced > "App sign-in" block | `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=advanced` | admin only (manage settings); editor refused | includes/admin/class-advanced-settings-tab.php |
| 4 | Listing REST routes | GET `/wp-json/listora/v1/listings`, `/listings/{id}`, `/search`, related, batch fetch by ids; owner routes renewal-quote and services reorder | guest (reads), owner/other/editor/admin (owner routes) | includes/rest/ (listings, search controllers) |
| 5 | Add Listing category dropdown (REST consumer) | `$SITE_URL{add_listing}` with a type chosen; data from GET `/listing-types/{type}/categories` | owner, admin | blocks/ submission block + REST |
| 6 | Space showcase routes | GET/POST `/wp-json/listora/v1/spaces/{space_id}/listings`, `/listings/{id}/approve`, DELETE `/listings/{id}`, GET `/listings/{id}/spaces` | space-team, owner, guest | includes/rest/class-space-listings-controller.php |
| 7 | Space moderation queue | GET `/wp-json/listora/v1/spaces/{space_id}/listings/pending` | space-moderator only | includes/rest/class-space-listings-controller.php |

Free ships no space host: surfaces 6 and 7 answer 403 to everyone until a host (or a temporary mu-plugin) answers `wb_listora_user_can_moderate_space` and `wb_listora_user_can_view_space`.

## Components on each surface
1. App-config JSON: `contract_version`, `app_enabled` (real boolean, Free default false), `features{}` (free keys only on a Free-only site), `branding{}`, legal links, back-compat keys; 32 keys today. No cookies or nonce needed. A browser renders it as raw JSON: check key presence and types, not styling.
2. Sign-in endpoint: request body (username, password, app id), success body (credential, one per app id), failure body (identical for wrong user and wrong password), `Retry-After` on lockout, 403/disabled body when switched off.
3. Settings > Advanced: left tab nav (`.listora-settings-nav-item.is-active`) > `.listora-settings-pane` with blocks `.listora-settings-block`: "Setup wizard" (link "Re-run setup"); "App sign-in" (title, description, row "Password sign-in": hidden `input[name=wb_listora_app_password_login][value=0]`, checkbox `#wb_listora_app_password_login`, label text, `.description`); "Mobile app: legal links" (read-only `#legal_privacy_policy_url`, publish status hint, links). Save button of the settings form.
4. Listing routes: `listings[]`, `total`, `pages`, `has_more`, `cursor`, `next_cursor`, row fields (`listing_type`, `listora_meta`, `status`, timestamps), `X-WP-Total`/`X-WP-TotalPages` headers, `/search` shared envelope, related list, batch result, renewal-quote body, reorder result.
5. Add Listing dropdown: type select, category `<select>` with option labels (names decoded), tap height at 390.
6. Space showcase: approve result `{"status":"approved"}`, removal context (reject / takedown / withdraw), list rows plus `X-WP-Total`.
7. Space queue: rows, `X-WP-Total`, `X-WP-TotalPages`, `per_page` (default 20, max 100), `page`.

## States to force
| Surface | State | How |
|---|---|---|
| 1 | Pro active / inactive | `wp plugin deactivate wb-listora-pro` then restore |
| 1 | `app_enabled` Free default | Pro inactive, read the key |
| 2 | Switch off | untick on surface 3 or `wp option update wb_listora_app_password_login 0` |
| 2 | Lockout | five wrong attempts on a throwaway account (locks username and IP 15 minutes) |
| 2 | Reconnect same app id | POST twice with the same app id |
| 3 | Switch on / off, saved, reload | tick/untick, Save, reload |
| 3 | Privacy page unpublished / unset | set `wp_page_for_privacy_policy` to a draft page, then 0 |
| 3 | No-permission | log in as {editor}: page refused |
| 4 | N=0, 1, many; last page; big page | `TOTAL` vs `per_page`; seed 2000+ for scale |
| 4 | Pending/draft listing | guest must never see it in lists or batch fetch |
| 5 | Title with ampersand | listing/term named `Health & Wellness` |
| 6/7 | Host present / absent | mu-plugin returning true for the space filters; remove to see 403 |
| 7 | 25+ pending rows in one second | seed in one burst |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `wb_listora_app_password_login` (Settings > Advanced > Password sign-in) | on (default) / off | surface 2 refuses new password sign-ins; existing credentials keep working; app-config advertises the sign-in mode |
| Filter `wb_listora_app_password_login_enabled` | bool | overrides the option (journey step 7) |
| Pro active or not | on / off | surface 1 `features{}` and `app_enabled` change source; Free alone never emits Pro keys |
| Directory "Listings per page" | integer | default `per_page` on surface 4 |
| Filters `wb_listora_user_can_moderate_space`, `wb_listora_user_can_view_space` | bool | surfaces 6/7 go from 403 to allowed |

## Fixtures and setup
- Placeholders from `docs/qa/sites/<site>.json`; fixtures: `docs/qa/fixtures/2.0-fixtures.php` (listings, owners, services).
- Throwaway member for sign-in: `wp user create` with prefix `qa-app-`, subscriber, random password. Never a real account.
- Space host: temporary mu-plugin returning true for {admin} or `$SPACE_MODERATOR`; space ids are throwaway integers; QA-J1- listings: published, pending, one published page.
- Restore: delete `qa-app-*` users and credentials, restore `wb_listora_app_password_login` to on, remove the mu-plugin and QA- rows in `${PREFIX}listora_space_listings`, clear lockout transients.

## Run order
1. `app-password-sign-in.md` (mixed, critical): password for credential, replace on reconnect, no enumeration, lockout, owner switch on surface 3.
2. `listing-rest-contract.md` (mixed, high): envelope, timestamps, related/bulk/quote/reorder, decoded strings, add-listing dropdown.
3. `app-config-contract-shape.md` (api, critical): full key set, free-only flags, `app_enabled` fails closed.
4. `space-listings-rest.md` (api, normal): published-only approval, honest total, removal context.
5. `space-moderation-queue-is-bounded.md` (api, normal): bounded, non-overlapping pages with true total.

Then run: wb-listora-pro/docs/qa/journeys/integrations-api/_group.md (it extends app-config-contract-shape).

## Group-specific micro checks
| id | Check |
|---|---|
| G-01 | `app-config.app_enabled` is JSON `true`/`false`, never `"1"`/`1`, and is `false` with Pro inactive |
| G-02 | `features{}` on a Free-only site contains no key tagged `store = wb_listora_pro_features`; the key count equals the free registry rows |
| G-03 | The same app-config keys appear for guest, owner, editor and admin (no role-dependent shape) |
| G-04 | `/listings?per_page=2`: `pages == ceil(total / 2)`, `has_more` is true on every page but the last, and `next_cursor` is null on the last |
| G-05 | `total` in the body equals `wp post list --post_type=listora_listing --post_status=publish --format=count` for a guest |
| G-06 | Every timestamp field ends in `Z` or carries an offset; none is a bare local `Y-m-d H:i:s` |
| G-07 | No `&amp;`, `&#038;`, `&#8217;` in any title, image alt, term name, dropdown option or (except the known `/search` excerpt defect) excerpt returned by the API |
| G-08 | Add Listing category select shows `Health & Wellness` literally, and its rendered height is at least 40 px at 390 |
| G-09 | Wrong password and wrong username return byte-identical body, status and `Retry-After` presence |
| G-10 | Settings > Advanced: the hidden `0` input precedes the checkbox in DOM order and unticking then Save stores `0` (read the option back) |
| G-11 | Clicking the label text "Let members sign in to the app with their WordPress password" toggles the checkbox (label `for` matches `#wb_listora_app_password_login`) |
| G-12 | With the switch off, a credential issued before still authenticates a protected route (description says existing sign-ins keep working) |
| G-13 | The "Mobile app: legal links" privacy field is read-only and its hint switches among set, not set, and "Page selected but not published" |
| G-14 | `/spaces/{id}/listings/pending`: `X-WP-TotalPages == ceil(X-WP-Total / per_page)`, `?page=99` is 200 with `[]` and the true total, `?per_page=1000` is 400 |
| G-15 | Approving a pending listing id or a page id on a space is refused and `X-WP-Total` equals the number of rows that render |
| G-16 | Page 1 and page 2 of the queue at `per_page=10` share no `listing_id` when all rows have one `created_at` second |
| G-17 | With no space host installed, an administrator gets 403 on surfaces 6 and 7 (not 200 and not 500) |

## Known defects (expected-fail)
| Journey | Step | Defect |
|---|---|---|
| listing-rest-contract | 11 | `/search` rows return `&#038;`/`&#8217;` in `excerpt`; `class-search-controller.php` does not decode it |

## Not covered yet
- No journey opens surface 3 in a browser at 390 or in dark mode (layout of the "App sign-in" block, label tap target).
- `/listings/{id}/spaces` (which spaces a listing is in) has no journey.
- `GET /listing-types/{type}/categories` is only checked through the dropdown, not as a contract (shape, depth, empty type).
- `app-config` legal links and branding keys are listed but values are not asserted against Settings > Privacy.
- Rate-limit headers on `/listings` reads belong to access-security but app-config has no CDN/cache header check.
