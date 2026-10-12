---
journey: listing-renewal-flow
plugin: wb-listora
priority: high
roles: [subscriber, other-member, logged-out, admin]
covers: [listing-renewal-rest, renewal-quote, renew-modal, expiration-reminder-email, renewal-window, credits-gate, expiry-setting-2.0, bc-10393853466, bc-10393851854]
prerequisites:
  - "Site reachable at $SITE_URL (Local: http://wb-listora.local)"
  - "Listing expiry is ON: `wb_listora_expiration_enabled()` is true. In 2.0.0 it is OFF on a free directory (`enable_expiration` false) and ON while Pro's Pricing Plans (paid listing time) is active; on this site Pro is active so it is ON"
  - "Features > Renewal ON; `renewal_window_days` = 7 (default)"
  - "A published listing owned by OWNER with an expiry date inside the window. Fixture: QA-J1 Wizard Listing (plan Standard Listing, 10 credits), OWNER = QA-J1-member"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Owner renews an expiring listing

An owner whose listing expires within the renewal window sees "Expires in N days" and "Renew Now" on My Listings, opens the confirm modal (plan, cost, duration, balance), confirms, and the expiry moves forward while the listing stays published. A member who cannot afford it is told so and cannot confirm. Other members and logged-out visitors cannot renew.

Verified via: Playwright (isolated context per role), curl REST with a nonce, WP-CLI for DB and the notification hook. Pro was active (credits).

## Setup

- Put the fixture inside the window (UTC string, same format the plugin writes): `update_post_meta( LISTING_ID, '_listora_expiration_date', gmdate( 'Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS ) );`
- Dashboard: `$SITE_URL/my-listings/?tab=listings`.
- Credits (Pro): note the member balance; `\WBListoraPro\Features\Credit_System::add_credits( $uid, N, 'fixture', 'admin' )` to top up. Remove the credits and the listing at the end.
- Email evidence is in `{prefix}listora_email_log` (columns `id, sent_at, event_key, recipient, subject, body, success`; there is no `template` or `listing_id` column). Event keys: `listing_expiring_soon`, `listing_renewed`.

## Steps

### 1. Reminder email (system step)
- **Action**: the twice-daily job `wb_listora_check_expirations` (`includes/workflow/class-expiration-cron.php:40`) fires `do_action( 'wb_listora_listing_expiring', LISTING_ID, 7 )` for published listings whose `_listora_expiration_date` is within 7 days and that have no `_listora_expiry_reminded_7d`. To test one listing without mailing the whole site, fire that action for the fixture only (WP-CLI eval).
- **Expect**: one `listora_email_log` row `event_key='listing_expiring_soon'`, `recipient` = OWNER's email, subject "Your listing expires in 7 days: <title>", `success=1`.
- **On fail**: `includes/workflow/class-notifications.php:406` (`listing_expiring_soon`), `includes/workflow/class-expiration-cron.php:59`

### 2. Dashboard shows the expiring row and Renew
- **Action**: log in as OWNER, open `?tab=listings`.
- **Expect**: `.listora-dashboard__listing-row[data-listora-listing-id=LISTING_ID]` has `data-listora-state="expiring"`; row text includes "Published", "Expires in 3 days" and a button `.listora-dashboard__renew-btn` "Renew Now" (also "Renew" in the row menu); the Expiry filter `#listora-renewal-filter` offers "Any expiry", "Active", "Expiring soon", "Expired".
- **On fail**: `templates/blocks/user-dashboard/tab-listings.php:157-175`

### 3. NEGATIVE: cannot afford it
- **Action**: with balance below the cost (5 credits vs 10), click "Renew Now".
- **Expect**: `GET /wp-json/listora/v1/listings/LISTING_ID/renewal-quote` returns `200` with `plan_name`, `cost`, `duration_days`, `days_until_expiry`, `can_renew_now: true`, `balance`; the modal `[data-listora-renew-modal]` (role dialog) shows Plan "Standard Listing", Cost "10 credits", Duration "30 days", Your balance "5 credits" and the error "You need 10 credits to renew (you have 5)."; `[data-listora-renew-confirm]` ("Confirm renewal") is disabled.
- **Known gap**: the "Buy more credits" link (`[data-listora-renew-buy]`) stays hidden because the quote does not carry `purchase_url` (see PRODUCT-BUGS in the report).

### 4. Renew
- **Action**: top the member up to at least the cost (15 credits), reload, click "Renew Now", then "Confirm renewal".
- **Expect**: modal balance "15 credits", Confirm enabled; `POST /wp-json/listora/v1/listings/LISTING_ID/renew` returns `200` `{"renewed":true,"new_expiry":"<UTC date>","new_expiry_human":"<Month D, YYYY>","credits_deducted":10,"balance":5,"renewal_count":1,"status":"publish","message":"Listing renewed until <date>."}`; the toast stack shows the message; the row flips to `data-listora-state="active"` and loses its Renew buttons; after reload the row reads "Expires <Month D, YYYY>".
- **On fail**: `src/blocks/user-dashboard/view.js:432` (`doRenew`), `includes/rest/class-listings-controller.php:2069` (`renew_listing`), `:2107` (`charge_and_renew`)

### 5. DB, status, email
- **Expect**: `_listora_expiration_date` is about now + `duration_days` (30), `post_status='publish'`; balance decreased by `cost` (15 -> 5); a `listora_email_log` row `event_key='listing_renewed'`, subject "Your listing has been renewed: <title>", `success=1`.
- **Free-only**: cost is `default_renewal_credit_cost` (0 by default) and no credit lines appear; the modal shows Cost "Free".

### 6. NEGATIVE: renewing again is refused (outside the window)
- **Action**: `POST /listings/LISTING_ID/renew` as OWNER right after.
- **Expect**: `400` code `listora_renewal_too_early` ("You can renew within 7 days of expiry.", `data.days_until_expiry` 30, `data.renewal_window_days` 7). `GET renewal-quote` shows `can_renew_now: false` and the same `reason`. (Positive control: step 4, same call inside the window returned 200.)

### 7. ROLE LADDER
| Actor | Action | Expect |
|---|---|---|
| OWNER | steps 2-6 | as above |
| Other member | `GET renewal-quote` / `POST renew` | `403 listora_forbidden` ("You do not have permission to renew this listing.") |
| Logged out | `POST renew` | `401 listora_unauthorized` ("You must be logged in to renew a listing.") |
| Admin (control) | `GET renewal-quote` | `200` with the listing's quote (admin and editors pass `edit_others_posts`); the quote's `balance` is the admin's own |

### 8. Scale and layout
- **Scale**: My Listings is paginated with the Expiry filter applied server-side (`?listings_filter=expiring`, `listings_page`, nav label "Listings pagination"); the filter runs in the query, not in JS. A member with 100+ listings must see the right page count under "Expiring soon". This site has no member with 100+ listings: seed one, not verified here.
- **390px**: dashboard row, Renew Now and the modal: `scrollWidth <= 390`; Renew Now, Confirm, Cancel and close are at least 40px high.

### 9. Expiry OFF and an Event: nothing to renew (bc-10393853466)
- **Action** (OWNER): set Settings > General > Enable automatic listing expiration OFF, keep a legacy `_listora_expiration_date` in the future on a Restaurant listing; `GET /listings/ID/renewal-quote`, then `POST /listings/ID/renew`. Repeat on a published Event with `_listora_end_date` = today + 10 days.
- **Expect**: quote has `can_renew_now: false` and NO `days_until_expiry`; `POST renew` answers `409` (not 200 and not 400 `listora_renewal_too_early`); the Event's `_listora_end_date` and `_listora_expiration_date` are unchanged.
- **Read-back**: `wp post meta get ID _listora_expiration_date` before and after are identical; no `listing_renewed` row in `listora_email_log`; POSITIVE CONTROL: expiry ON, same listing inside the window, renew answers 200.
- fails_on: unknown (rule introduced in 2d78846e). Not verified live.


## Pass criteria

1. Reminder email logged with `event_key=listing_expiring_soon` for an in-window listing.
2. Row shows "Expires in N days" and "Renew Now".
3. Modal shows plan, cost, duration and balance from `renewal-quote`; unaffordable blocks Confirm.
4. Renew returns 200 with `renewed:true`, expiry moved forward, status `publish`, credits deducted, `listing_renewed` email logged.
5. A second renew is refused with `listora_renewal_too_early`; other members 403; logged out 401.

## Not verified

- Free-only run (no Pro, expiry off): Renew affordances are absent when `wb_listora_expiration_enabled()` is false in practice because no listing has an expiry date; not walked on a Free-only site.
- The real cron path end to end (`wb_listora_check_expirations` via Action Scheduler) was not run, to avoid mailing every expiring listing on the shared site.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| No reminder email | `wb_listora_listing_expiring` not fired / notification off | `includes/workflow/class-expiration-cron.php:59`, `includes/workflow/class-notifications.php:406`, setting `notifications.listing_expiring_soon` |
| No "Renew Now" on an expiring row | renewal off / outside window / expiry off | `templates/blocks/user-dashboard/tab-listings.php:26,161,175`, `includes/helpers.php:796` |
| Modal stuck on "..." | quote request failing | `includes/rest/class-listings-controller.php:2047` |
| Renew POST 400 too early | window setting | `includes/rest/class-listings-controller.php:2107`, setting `renewal_window_days` |
| Expiry not extended | duration filter returns 0 | `wb_listora_renewal_duration_days` filter, `includes/rest/class-listings-controller.php:1954` |
| Credits not deducted (Pro) | credits lock / charge path | `wb_listora_with_credits_lock`, `includes/features/class-credit-system.php` (Pro) |
