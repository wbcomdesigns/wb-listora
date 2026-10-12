---
journey: demo-import-quality
plugin: wb-listora
priority: high
roles: [administrator, member-other, anonymous]
covers: [demo-import-all-packs, demo-dates-spread, demo-per-type-features, demo-quiet-no-approval-email, demo-accounts-random-password, demo-events-dated-from-import-day, demo-education-contact-fields, demo-pack-single-list, event-demo-pack, events-calendar, bc-10393851876, bc-10393851820, bc-10393531373, "card 10393016231"]
prerequisites:
  - "SCRATCH site only (Docker bench or throwaway Local site): imports 100+ listings, ten member accounts and photos"
  - "Free active; Pro optional (its demo hooks run too)"
  - "A fresh install for the Education fields check; Action Scheduler available or WP-Cron running"
  - "The Events Calendar page exists (the Event listing type has 0 listings before the run)"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default; buddyx-pro light and dark for the front-end checks]
supersedes:
  - ora:admin/2.0-demo-import-quality.md
  - ora:regression/event-demo-pack.md
fails_on: step 12 fails on 11204e3c (spread step added in 16bbd3b7); steps 10-11 pre-fix sha unknown
site_profile: required
---

# Demo import: every pack, believable dates and features, upcoming events, no email storm, no usable demo passwords

## Why
A fresh site must look real: publish dates spread over the last 60 days, each type offered only its own features, no approval mail per sample listing, demo accounts with random passwords, an Events pack dated from the import day so the calendar is never empty, and Re-run Demo Import running EVERY pack like the wizard and WP-CLI. Engine mechanics: `admin/background-import-lifecycle.md`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Baselines: `_listora_demo_content` and `_listora_demo_user` meta counts (expect 0); `MAIL_BASE` = `SELECT COALESCE(MAX(id),0) FROM {prefix}listora_email_log`; the pack list `wb_listora_get_demo_packs()` (single source: restaurant, hotel, real-estate, job-board, general, classified, education, healthcare, place, event).
- Before state of the Events Calendar page: `.listora-calendar__empty[role=status]` reads "No events in <month year>."

## Steps

### 1. The wizard offers every pack
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-setup&rerun=1&step=demo` as {admin}.
- **Expect**: no admin notices; one `input[name=demo_pack]` radio per pack in `wb_listora_get_demo_packs()` plus "All packs" (count = packs + 1); Events copy "8 upcoming concerts, conferences and workshops, dated from the day you import"; All-packs copy "100+ listings across all 10 listing types, with photos, reviews and services."; "Skip demo content - I will add my own listings"; a "Skip setup" link (absent on the Done step).

### 2. Settings > Advanced > Re-run Demo Import runs all packs
- **Action**: `admin.php?page=listora-settings&tab=advanced` > `#listora-demo-import-btn`; if demo content exists a `listoraConfirm` dialog says "Demo listings already exist. Re-running will add duplicate demo content. Continue?" > Continue.
- **Expect**: "Demo import queued. Importing in background..."; `#listora-demo-import-progress [role=progressbar]` advances with "N listings imported"; ends in the done state; the run's packs equal `wb_listora_get_demo_packs()` (all ten), not the old subset.
- **Doc check**: the text under the button says "Queues a background import of the default demo listings." (predates "all packs"); record for the docs owner.

### 3. CLI reports the same single pack list
- **Action**: `wp listora demo seed --pack=nope`.
- **Expect**: the error lists exactly the packs of `wb_listora_get_demo_packs()`; the wizard, Settings button and CLI all read that one list.

### 4. No approval emails for sample listings
- **Action**: `SELECT COUNT(*) FROM {prefix}listora_email_log WHERE id > MAIL_BASE`.
- **Expect**: 0 new `listing_approved`, `listing_submitted`, `listing_pending_admin` rows (the seeder suppresses them with `wb_listora_should_send_notification`).
- **Positive control**: approve one real pending listing by hand: its `listing_approved` email IS logged.

### 5. Publish dates spread over 60 days
- **Action**: `SELECT MIN(p.post_date) mn, MAX(p.post_date) mx, COUNT(DISTINCT DATE(p.post_date)) days FROM {posts} p JOIN {postmeta} m ON m.post_id=p.ID AND m.meta_key='_listora_demo_content' WHERE p.post_type='listora_listing'`.
- **Expect**: `mn` within 60 days before today, `mx` within the last day or two, `days` >= 30; `/listings/?listora_sort=newest` differs from Featured order; no review `created_at` earlier than its listing's `post_date_gmt`; (Pro) the "New" badge shows on a minority of cards.

### 6. Each type is offered its own features
- **Action**: Directory > type {type A} > Filters > Features; repeat for two other types.
- **Expect**: the three lists differ and are each shorter than the site-wide feature list; a feature no listing of that type uses never appears; counts are listings of that type using the feature, most used first, with "Show all features (N)" for the rest.
- **Read-back**: `wp term meta get <type term id> _listora_allowed_features` is a subset and differs per type.

### 7. Demo accounts cannot be signed in to
- **Action**: `wp eval 'foreach(get_users(["meta_key"=>"_listora_demo_user","fields"=>["ID","user_login","user_pass"]]) as $u) echo $u->user_login,": ",json_encode(wp_check_password("password",$u->user_pass,$u->ID)),"\n";'`
- **Expect**: `false` for every demo account (random 24-character passwords); the real login form with a demo login and `password` says the password is incorrect.
- **Upgrade case**: `wp_set_password('password', <demo user id>)`, `update_option('wb_listora_db_version','1.9.0')`, load any page: that account no longer accepts `password`; a demo account whose password the owner changed keeps it.

### 8. Events are dated from the import day and fill the calendar
- **Action**: after the Events pack: `$SITE_URL/listings/?listora_type=event` and the Events Calendar page at 1440 and 390, step to next month.
- **Expect**: 8 published Event listings with featured image and gallery; start dates 3-48 days after the import day; the weekly comedy night repeats weekly until 60 days out; none in the past; `.listora-calendar__cell` days carry events, the comedy night on each of its weekdays; no `.listora-calendar__empty` for this month or next; no horizontal scroll at 390; each event page shows date, venue, ticket price and photos. Re-import another day and the dates move with the import day.

### 9. Re-importing a pack does not duplicate (added, Event pack)
- **Action**: run the Events pack twice.
- **Expect**: record whether duplicate titles are created (the source does not specify: report the observed behaviour as the contract for the owner). Not verified live.

### 10. Education has contact fields (fresh install)
- **Action**: Listing Types > Education > field groups.
- **Expect**: Provider group has Phone, Email, Website (`provider.telephone`, `provider.email`, `provider.url`). An UPGRADED site keeps its saved field list (record "new install only").

### 11. Upgrade with one stray non-demo listing using a demo-like feature (bc-10393851876)
- **Action**: import demo content at the previous version, create one NON-demo listing of {type A} tagged with a feature only another type's demo listing used, set `wb_listora_db_version` back one step, load any admin page.
- **Expect**: {type A}'s `_listora_allowed_features` does NOT contain that feature; each demo attachment carries its listing's author.
- **Read-back**: `wp term meta get <type id> _listora_allowed_features`; `SELECT COUNT(*) FROM {posts} WHERE post_type='attachment' AND post_parent=0 AND post_author=0 AND ID IN (<demo attachment ids>)` = 0. Not verified live.

### 12. Demo job pack after the date spread stays published (bc-10393851820)
- **Action**: upgrade a site holding the previous demo job pack, then `do_action("wb_listora_check_expirations")`.
- **Expect**: every demo Job has `validThrough` >= `datePosted` in its JSON-LD and stays `publish`; none moves to `listora_expired`.
- **Read-back**: `Schema_Generator::for_listing()->get_data()`; `wp post list --post_status=listora_expired --post_type=listora_listing` has no demo job. Not verified live.

### 13. Content imported before 1.9.1 is respread and gets authors (bc-10393531373)
- **Action**: site with pre-1.9.1 demo content (one shared `post_date`, attachments with `post_author` 0), upgrade, load an admin page.
- **Expect**: listings span >= 30 distinct dates within 60 days; each demo attachment's `post_author` equals its parent's; non-demo listings' dates unchanged (positive control).
- **Read-back**: the step 5 query returns `days >= 30`; mismatched-author attachment join count is 0. Not verified live.

### 14. Timezone (added)
- **Action**: repeat step 8 with site timezone UTC+14 then UTC-12, then restore UTC.
- **Expect**: no event lands in the past relative to site time. Not verified live.

## Role ladder

| Rung | Expect |
|---|---|
| {admin} | steps 1-4, 10 |
| {guest} | steps 5, 6, 8 on the front end; no demo member profile reachable by password |
| {other} | cannot reach the wizard or Re-run button; `POST admin-ajax.php?action=listora_run_demo_import` is 403 |

## Pass criteria
1. Wizard, Settings and CLI offer and run the same pack list.
2. No notification emails during import.
3. Dates spread over 60 days; each type offers only its own features; events are future-dated and fill the calendar.
4. No demo account accepts `password`.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Only some packs ran | handler default list or JS sends a stale subset | `includes/admin/class-demo-import-admin.php` (empty `packs` falls back to `wb_listora_get_demo_packs()`), `assets/js/admin/admin-pages.js` |
| Hundreds of emails | `wb_listora_should_send_notification` not applied around insert | `demo/class-demo-seeder.php` |
| All listings on one date | `days_ago` spread removed | `demo/class-demo-seeder.php` |
| Shared feature list | `allow_terms` not called per type | `class-demo-seeder.php` |
| Past event dates | `$listora_event_at` | `demo/event-pack.php` |
| Pack unknown | pack list | `includes/class-cli-commands.php`, `wb_listora_get_demo_packs()` |
| No images | photo source | `demo/photos.php` |

## Not verified
Steps 9, 11-14 and the Events import itself on the shared site (it mutates shared data).

## Cleanup
Settings > Advanced > "Delete Demo Data" (or `wp listora demo remove`); confirm the baseline counts are 0.
