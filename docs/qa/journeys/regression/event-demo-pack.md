---
journey: event-demo-pack
plugin: wb-listora
priority: high
roles: [administrator, anonymous]
covers: [demo-import-event-pack, events-calendar]
prerequisites:
  - "A site with the Event listing type and the Events Calendar page (here /calendar-demo/, page id 806)"
  - "Event type currently has 0 listings on this site (so the Before state is the empty calendar)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The Event demo pack fills the calendar with upcoming events

Card 10393016231 (owner decision 2026-10-11: rolling dates, included in All packs). Pack source: `demo/event-pack.php` (8 events dated by `$listora_event_at( N, 'HH:MM' )`, N from 3 to 48 days, "Tuesday Open Mic Comedy" `recurrence_type=weekly` until +60 days); pack list `wb_listora_get_demo_packs()` = restaurant, hotel, real-estate, job-board, general, classified, education, healthcare, place, event.

Verified via: source read and the live "before" state on 2026-10-12. The import was NOT run: it creates 8 public listings and uploads photos on the shared QA site (see Not verified).

## Steps

### 0. Before state (verified)
- GET `/calendar-demo/`: `.listora-calendar` with `.listora-calendar__empty[role="status"]` reading "No events in October 2026." and the type Event has 0 listings.

### 1. Import
- **Action**: `wp listora demo seed --pack=event --reindex`, or Setup Wizard > Events card ("8 upcoming concerts, conferences and workshops, dated from the day you import"), or the wizard's All packs, or Settings > Re-run Demo Import.
- **Expect**: 8 published Event listings, each with a featured image and gallery; start dates 3-48 days after the import day; the comedy night repeats weekly until 60 days out.

### 2. Calendar and pages (2000+ rows rule: the grid is small, so check the month cells)
- **Action**: open `/calendar-demo/` at 1440 and 390; step to next month.
- **Expect**: `.listora-calendar__cell` days carry the events; the comedy night appears each Tuesday; no `.listora-calendar__empty` for this month or next; no horizontal scroll at 390. Each event page shows date, venue, ticket price and photos.

### 3. Wizard copy (verified in source)
- **Expect**: the demo step lists "Events"; the All packs card says "100+ listings across all 10 listing types, with photos, reviews and services." (`includes/admin/class-setup-wizard.php:864-874`).

### 4. Re-import is idempotent
- **Action**: run step 1 again.
- **Expect**: no duplicate titles. Not specified in source; check and report.

### 5. Role ladder
- Import is admin/CLI only (administrator). Anonymous, member, owner and editor only read the result (public calendar and event pages). Another member cannot trigger it.

## Pass criteria

1. A fresh import shows upcoming events on the calendar whatever day it is run.

## Fail diagnostics

| Symptom | File |
|---|---|
| Past dates | `demo/event-pack.php:51-53` `$listora_event_at` |
| Pack unknown | `includes/class-cli-commands.php:837-848`, `wb_listora_get_demo_packs()` |
| Wizard copy | `includes/admin/class-setup-wizard.php:864-874` |
| No images | `demo/photos.php` |

## Not verified

Everything from step 1 on (importing mutates shared data: owner should run it on a disposable site or ask first).
