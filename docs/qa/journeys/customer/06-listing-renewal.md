---
journey: listing-renewal-flow
plugin: wb-listora
priority: high
roles: [subscriber, other-member, guest, admin]
covers: [listing-renewal-rest, renewal-quote, renew-modal, renewal-modal-aria-live, expiration-reminder-email, renewal-window, credits-gate, expiry-setting-2.0, renewal-duration, failed-renewal-atomic, bc-10393853466, bc-10393851854, card-10340447577]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Listing expiry is ON: `wb_listora_expiration_enabled()` is true (OFF on a free directory, ON while Pro's Pricing Plans is active); Features > Renewal ON; `renewal_window_days` = 7 (default)"
  - "A published listing {listing} owned by {owner} with an expiry date inside the window; a plan with duration 0 (standard) and one with 90 days"
  - "Pro for the credit steps; the Free-only path is in Not verified"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - "ora:regression/renewal-duration.md"
  - "ora:regression/renewal-modal-error-aria-live.md"
fails_on: []
---

# An owner renews an expiring listing for the period the settings say; a failed or refused renewal changes nothing

## Why
An owner who cannot see, afford or complete a renewal loses a live listing; one charged for a renewal that failed, or given a hardcoded 365 days, loses trust. Other members and guests must never renew.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Put the fixture inside the window: `update_post_meta( {listing id}, '_listora_expiration_date', gmdate( 'Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS ) );` (UTC string, same format the plugin writes).
- Dashboard `$DASH` = `wb_listora_get_dashboard_url()`, tab `listings`.
- Read `$COST` (renewal cost, plan credits), `$BALANCE`, `$DAYS` (duration_days) from `GET /listings/{id}/renewal-quote`; derive, never assume. Top up with `Credit_System::add_credits( $uid, N, 'fixture', 'admin' )`; remove credits and the listing at the end.
- Settings save/restore: `update_option( 'wb_listora_settings', ... )` then `wb_listora_get_setting( null, null, true )` to flush the static cache; record originals.
- Email evidence is `{prefix}listora_email_log` (columns `id, sent_at, event_key, recipient, subject, body, success`). Event keys: `listing_expiring_soon`, `listing_renewed`.
- The big-list filter, pager and badge counts live in `customer/dashboard-my-listings-statuses-counts-filters.md`.

## Steps

### 1. Reminder email (system step)
- **Action**: the twice-daily `wb_listora_check_expirations` (`includes/workflow/class-expiration-cron.php:40`) fires `do_action( 'wb_listora_listing_expiring', {listing id}, 7 )` for published listings whose expiry is within 7 days and without `_listora_expiry_reminded_7d`; fire it for the fixture only.
- **Expect**: one `listora_email_log` row `event_key='listing_expiring_soon'`, recipient = {owner}'s email, subject "Your listing expires in 7 days: <title>", `success=1`.
- **On fail**: `includes/workflow/class-notifications.php:406`, `class-expiration-cron.php:59`.

### 2. Dashboard shows the expiring row and Renew
- **Action**: as {owner} open `$DASH?tab=listings`.
- **Expect**: `.listora-dashboard__listing-row[data-listora-listing-id=<id>]` has `data-listora-state="expiring"`; row text includes "Published", "Expires in 3 days" and `.listora-dashboard__renew-btn` "Renew Now" (also "Renew" in the row menu); `#listora-renewal-filter` offers "Any expiry", "Active", "Expiring soon", "Expired".
- **On fail**: `templates/blocks/user-dashboard/tab-listings.php:157-175`.

### 3. The modal error is a pre-existing assertive live region
- **Action**: GET the dashboard HTML before any JS and inspect `[data-listora-renew-error]`.
- **Expect**: `<p class="listora-dashboard__renew-modal-error" data-listora-renew-error aria-live="assertive" aria-atomic="true" hidden></p>` server-rendered inside the shared `[data-listora-renew-modal]`, once per Listings tab even with no renewable listing; `[e !== null, e.getAttribute('aria-live'), e.getAttribute('aria-atomic'), e.hasAttribute('hidden')]` = `[true,"assertive","true",true]`.
- **On fail**: an AT only announces mutations in a region present when it built the accessibility tree: `tab-listings.php:744,769`.

### 4. NEGATIVE: cannot afford it
- **Action**: with balance below `$COST`, click "Renew Now".
- **Expect**: `GET /listora/v1/listings/<id>/renewal-quote` returns `200` with `plan_name`, `cost`, `duration_days`, `days_until_expiry`, `can_renew_now: true`, `balance`; the modal shows Plan, Cost "`$COST` credits", Duration "`$DAYS` days", Your balance, the error "You need `$COST` credits to renew (you have `$BALANCE`)." in the SAME `[data-listora-renew-error]` node (loses `hidden`, `aria-live` stays "assertive", node reused not replaced); `[data-listora-renew-confirm]` is disabled.
- **Read-back**: expected-fail: the "Buy more credits" link `[data-listora-renew-buy]` must show (quote carries `purchase_url`); today it stays hidden. `PRODUCT-BUGS "renewal quote missing purchase_url"`.

### 5. Renew
- **Action**: top {owner} up to at least `$COST`, reload, click "Renew Now", then "Confirm renewal".
- **Expect**: modal balance updated, Confirm enabled; `POST /listora/v1/listings/<id>/renew` returns `200 {"renewed":true,"new_expiry":"<UTC>","new_expiry_human":"<Month D, YYYY>","credits_deducted":$COST,"balance":...,"renewal_count":1,"status":"publish","message":"Listing renewed until <date>."}`; toast shows the message; the error element is back to `hidden` with empty text; row flips to `data-listora-state="active"` and loses its Renew buttons; after reload the row reads "Expires <Month D, YYYY>".
- **On fail**: `src/blocks/user-dashboard/view.js:432` (`doRenew`), `:321,369-499` (`errEl`), `includes/rest/class-listings-controller.php:2069`, `:2107`.

### 6. DB, status, email
- **Expect**: `_listora_expiration_date` is about now + `$DAYS` in UTC (written with `gmdate()`, `:2224`, not shifted by the site offset), `post_status='publish'`; balance decreased by `$COST`; a `listing_renewed` row, subject "Your listing has been renewed: <title>", `success=1`.
- **Free-only**: cost is `default_renewal_credit_cost` (0 by default), no credit lines, modal Cost "Free".

### 7. NEGATIVE: renewing again is refused (outside the window)
- **Action**: `POST /listings/<id>/renew` as {owner} right after.
- **Expect**: `400` `listora_renewal_too_early` ("You can renew within 7 days of expiry.", `data.days_until_expiry` = `$DAYS`, `data.renewal_window_days` 7); `GET renewal-quote` shows `can_renew_now: false` with the same reason.
- **Read-back**: positive control: step 5, same call inside the window returned 200.

### 8. Duration follows settings: standard, plan, renewal setting, lifetime
- **Order**: plan duration, then Settings > Renewal duration (`default_renewal_duration_days`), then the type's `expiration_days`, then Default expiration (`default_expiration`); 0 all the way down = no longer expires. No hardcoded 365 (`Status_Manager::standard_duration_days()` `:443`, `build_renewal_quote()` `:1954`).
- **Action/Expect** (set each, listing `listora_expired`, read the quote):
  - Default expiration 30, Renewal duration 0, no plan: `duration_days: 30`, `can_renew_now: true`, `is_expired: true`, `new_expiry` = now + 30 d UTC.
  - A 90-day plan: +90. Renewal duration 14 with a standard plan: `duration_days: 14`.
  - Default expiration 0 and Renewal duration 0: `duration_days: 0`; modal duration reads "No expiry" (`jsRenewNoExpiry`); `POST /renew` returns `200` `new_expiry: ""`, `renewed: true`, message "Listing renewed. It no longer expires."; `_listora_expiration_date` deleted, status `publish`.
- **Read-back**: plan cards (Add Listing plan step, Pricing Plans admin) show "30 days" for a 0-day plan while Default expiration is 30, and "Never expires" only when it is 0; the plan metabox help says 0 uses the standard expiration.
- **Note**: expiry may be off (free directory) or on (Pro plans); the quote does not depend on it, an owner can still renew an expired listing.

### 9. A failed renewal changes nothing
- **Action**: mu-plugin `add_filter( 'wp_insert_post_empty_content', '__return_true' )` so `wp_update_post()` fails; renew.
- **Expect**: `500 listora_renewal_failed` "We couldn't renew your listing. You weren't charged. Please try again."; balance unchanged; `_listora_renewal_count` unchanged; old expiry restored.
- **On fail**: count rises on failure when the write (`:2267`) moved before the status update.

### 10. Expiry OFF and an Event: nothing to renew (bc-10393853466)
- **Action** ({owner}): set Settings > General > Enable automatic listing expiration OFF, keep a legacy `_listora_expiration_date` in the future on a {type} listing; `GET /listings/<id>/renewal-quote`, then `POST /listings/<id>/renew`. Repeat on a published Event with `_listora_end_date` = today + 10 days.
- **Expect**: quote `can_renew_now: false` and NO `days_until_expiry`; `POST renew` answers `409` (not 200, not 400 `listora_renewal_too_early`); the Event's `_listora_end_date` and `_listora_expiration_date` are unchanged.
- **Read-back**: `wp post meta get <id> _listora_expiration_date` identical before/after; no `listing_renewed` row; positive control: expiry ON, same listing inside the window, renew answers 200.
- **fails_on**: unknown (rule introduced in 2d78846e). Not verified live.

### 11. Role ladder
| Actor | Action | Expect |
|---|---|---|
| {owner} | steps 2-9 | as above |
| {other} | `GET renewal-quote` / `POST renew` | `403 listora_forbidden` ("You do not have permission to renew this listing.") |
| {guest} | `POST renew` | `401 listora_unauthorized` ("You must be logged in to renew a listing.") |
| {admin} (control) | `GET renewal-quote` | `200`; admin and editors pass `edit_others_posts`; the quote's `balance` is the admin's own, and a 0-cost listing charges the admin nothing |

### 12. 390px, dark, timezone
- **Expect**: dashboard row, Renew Now and the modal: `scrollWidth <= 390`; Renew Now, Confirm, Cancel and close >= 40px; modal readable in dark. With site timezone UTC+14 and UTC-12 the "Expires in N days" text and `new_expiry` equal the UTC value in step 6 (restore UTC). Timezone half not verified live.

## Pass criteria
1. Reminder email logged with `event_key=listing_expiring_soon` for an in-window listing.
2. Row shows "Expires in N days" and "Renew Now"; the modal shows plan, cost, duration and balance from `renewal-quote`; unaffordable blocks Confirm through the live region.
3. Renew returns 200 `renewed:true`, expiry moved by the setting-derived duration (0 = no expiry), status `publish`, credits deducted, `listing_renewed` logged.
4. A second renew is refused `listora_renewal_too_early`; expiry-off and Event return 409; a failed renewal charges nothing and changes nothing.
5. Other members 403; guests 401.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| No reminder email | action not fired / notification off | `class-expiration-cron.php:59`, `class-notifications.php:406`, setting `notifications.listing_expiring_soon` |
| No "Renew Now" on an expiring row | renewal off / outside window / expiry off | `tab-listings.php:26,161,175`, `includes/helpers.php:796` |
| Modal stuck on "..." | quote request failing | `class-listings-controller.php:2047` |
| Renew POST 400 too early | window setting | `class-listings-controller.php:2107`, `renewal_window_days` |
| Expiry not extended | duration filter returns 0 | `wb_listora_renewal_duration_days`, `class-listings-controller.php:1954` |
| 365 days | hardcoded fallback | `build_renewal_quote()` `:1954`, `charge_and_renew()` `:2107` |
| Expiry shifted by GMT offset | not written with `gmdate()` | `:2224` |
| Credits not deducted (Pro) | credits lock / charge path | `wb_listora_with_credits_lock`, `includes/features/class-credit-system.php` (Pro) |
| Error not announced | region created after load | `tab-listings.php:769`, `view.js:369-499` |

## Not verified
- Free-only run (no Pro, expiry off): Renew affordances are absent in practice because no listing has an expiry date; not walked on a Free-only site.
- The real cron path end to end (Action Scheduler) was not run, to avoid mailing every expiring listing on a shared site.
- From renewal-duration: the 403/401 role ladder was read from code, step 8 labels and the modal at 390 were not exercised, the 90-day plan path was read in code; step 5-7 of the original verified live on 2026-10-12. From aria-live: only step 3 was checked in the DOM.

## Cleanup
Restore settings, test listings and balances to their recorded originals; remove the failing-update mu-plugin.
