---
journey: expiry-off-on-free-directory
plugin: wb-listora
priority: high
roles: [administrator, member-owner, member-other, guest]
covers: [expiration-default-off, dated-types-expire-on-date, no-renew-for-lifetime, new-types-no-expiry, own-switch-with-reminders, republish-future-date, 2.0.0-expiry, bc-10393806630]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} autologin"
  - "wb-listora-pro DEACTIVATED for the whole journey (a free directory): `wp plugin deactivate wb-listora-pro`; reactivate afterwards. A run with Pro active cannot see the free-directory default (the Pro side is `wb-listora-pro: admin/monetization-expiry-follows-toggle.md`)"
  - "Fixtures: {owner} who can publish; an Event listing with an end date and a Job with a deadline (demo packs `event`, `job-board`)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default, buddyx-pro light for the dashboard]
site_profile: required
supersedes:
  - "ora:admin/2.0-expiry-off-on-free-directory.md (Part B moved to pro:admin/2.0-monetization-expiry-follows-toggle.md)"
fails_on: [8bc550fb]
---

# Listing expiry is off on a free directory, dated types still end on their date, and a new listing never expires

## Why
2.0.0 changes the default: most directories keep listings until someone removes them, so expiry is OFF and "Default expiration" is 0 (lifetime). A countdown or Renew button on a lifetime listing, or an Event that never ends, misleads owners and visitors. Events and job deadlines always end on their own date (end + 24 h grace); new types default to no expiry in the editor and REST.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Run the cron for one pass with `wp eval 'do_action("wb_listora_check_expirations");'`.
- Record the original `enable_expiration` and `default_expiration` and restore them at the end.

## Steps

### 1. The setting and its wording
- **Action**: as {admin} open `admin.php?page=listora-settings&tab=general`, section "Listing Lifecycle".
- **Expect**: checkbox "Enable automatic listing expiration" UNTICKED; help "Off by default: most directories keep listings until they are removed. When on, listings unpublish after their plan, type or default period, with reminder emails 7 days and 1 day before. Events and job deadlines end on their own date either way."; "Default expiration" = 0 days with "Days before a new listing expires. Leave at 0 for lifetime listings (the directory default). Plans (Pro) override this per plan."; NO note about Monetization.
- **Read-back**: `wp eval 'var_dump(wb_listora_expiration_enabled());'` -> `bool(false)`.
- **On fail**: `class-settings-page.php` (~928-970), `wb_listora_expiration_enabled()` (`includes/helpers.php` ~802), defaults `enable_expiration` false, `default_expiration` 0 in `wb-listora.php`.

### 2. A new listing gets no expiry date
- **Action**: publish a {type} listing as {owner} (wizard, or admin approve of a pending one).
- **Expect**: `wp post meta get <id> _listora_expiration_date` is empty/unset; the listing stays published after the cron pass.
- **On fail**: `Status_Manager::set_expiration_on_publish` (`class-status-manager.php` ~487).

### 3. The owner sees no countdown and no Renew
- **Action**: as {owner} open the dashboard `?tab=listings` at 1440 then 390.
- **Expect**: the row has NO "Expires in N days"/"Expires today" and NO "Renew"/"Renew Now"; the Overview "Expiring soon" widget is absent; dashboard listing items carry `expiry: ""`.
- **Read-back**: positive control: with the setting on (step 7) both appear, so their absence here is the setting. Another member never sees {owner}'s countdown.
- **On fail**: `Status_Manager::listing_expires` / `expiry_timestamp` (~403, ~420), `tab-listings.php` (~158), `class-dashboard-controller.php` ~502 (reading the raw meta).

### 4. Dated types still end on their date
- **Action**: publish an Event whose `end_date` is yesterday and a Job whose `deadline` is yesterday (admin: set the fields, Publish), then run the cron pass.
- **Expect**: each becomes `listora_expired` and `_listora_expiration_date` = end/deadline + 24 hours (site time zone); a non-dated listing published the same minute is untouched. Positive control: an Event with a future end date stays published with expiry = end + 24 h.
- **Expect**: NO reminder emails queued for any of them while expiry is off (no `expiry_reminder`/`listing_expiring` rows in the Email Log).
- **On fail**: `Expiration_Cron::check_expirations` (`class-expiration-cron.php` ~40-42, `expire_listings( true )` for dated only).

### 5. New types default to no expiry
- **Action**: `admin.php?page=listora-listing-types&action=new` > sidebar field "Listing expires after (days)" (`#listora-type-expiry`).
- **Expect**: value 0 and hint "0 = never expires". Create the type: `GET /listora/v1/listing-types/<slug>` has `expiration_days` 0 (a POST with no value also stores 0).

### 6. The setting is stored once, the default is lifetime for REST-created listings
- **Action**: create a listing via `POST /listora/v1/submit` as {owner} and via wp-admin as {admin}.
- **Expect**: both have empty `_listora_expiration_date`.

### 7. Own switch on (Pro off): expiry and reminders follow it
- **Action**: tick "Enable automatic listing expiration", set Default expiration = 30, Save.
- **Expect**: new listings expire in 30 days; reminders go out 7 days and 1 day before (`wb_listora_check_expirations`); the dashboard shows Renew. Unticking returns to step 2 behaviour for NEW listings (existing dates stay stored but are not acted on: `expiry_timestamp()` returns 0 for non-expiring listings, so no countdown).
- **Read-back**: a new listing's `_listora_expiration_date` = publish time + 30 days (UTC).

### 8. Every non-publish status moved to Published gets a future date (bc-10393806630)
- **Action** ({admin}): for listings in `listora_expired`, `draft`, `listora_deactivated`, `listora_rejected`, each carrying `_listora_expiration_date` 30 days in the past, Quick Edit > Status = Published; then run the cron pass.
- **Expect**: each is `publish` after the cron run and its `_listora_expiration_date` is in the future (or empty when expiry is OFF and the type has no end date); none returns to `listora_expired`.
- **Read-back**: `wp post meta get <id> _listora_expiration_date` and `wp post get <id> --field=post_status` for all four; positive control: a published listing with a past date and untouched status still expires (with the setting on).
- **fails_on**: 8bc550fb. Not verified live.

### 9. Timezone matrix for dated types
- **Action**: repeat step 4 with the site timezone at UTC+14 (`Pacific/Kiritimati`), then UTC-12 (`Etc/GMT+12`), then restore UTC.
- **Expect**: an Event ending "yesterday" site time expires in the same cron pass in all three; the stored expiry equals end + 24 h computed in the site zone. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {admin} | steps 1, 4-9 |
| member-owner | steps 3, 7 (own countdown only) |
| member-other | never sees another member's countdown or Renew |
| {guest} | an expired listing's page shows the visitor note (see `customer/owner-opens-own-unpublished-listing.md`); a lifetime listing shows nothing |

## Pass criteria
1. Free directory: setting off, default 0, no expiry date, no reminders, no countdown or Renew.
2. Events and job deadlines expire on end/deadline + 24 h regardless.
3. New types default to 0 in the editor and in REST.
4. Turning the own switch on gives plan-less expiry with reminders; republishing never leaves a past date.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Countdown on a lifetime listing | dashboard reading raw meta, not `expiry_timestamp` | `tab-listings.php`, `class-dashboard-controller.php` ~502 |
| Event never expires with expiry off | `check_expirations` early return removed | `class-expiration-cron.php` ~42 |
| Past date after republish | status transition does not reset the date | `class-status-manager.php` ~487 |

## Not verified
- Steps 6 and 9 added in consolidation; step 8 not run live; the others were run on a free directory with Pro deactivated.

## Cleanup
Restore `enable_expiration`/`default_expiration`, delete the created type and test listings, reactivate Pro, restore site timezone to UTC.
