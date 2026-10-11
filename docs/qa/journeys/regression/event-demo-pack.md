---
journey: event-demo-pack
plugin: wb-listora
priority: high
roles: [administrator, anonymous]
covers: [demo-import-event-pack, events-calendar]
prerequisites:
  - "A site with the Event listing type and the Events Calendar page"
estimated_runtime_minutes: 10
---

# The Event demo pack fills the calendar with upcoming events

Card 10393016231 (owner decision 2026-10-11: rolling dates, included in All packs).

## Steps

### 1. Import
- **Action**: `wp listora demo seed --pack=event --reindex`, or Setup Wizard > Events, or the wizard's All packs.
- **Expect**: 8 published Event listings, each with a featured image and gallery; start dates 3-48 days after the import day; "Tuesday Open Mic Comedy" repeats weekly until 60 days out.

### 2. Calendar and pages
- **Action**: open the Events Calendar page at 1440 and 390.
- **Expect**: this month and next show the events; the comedy night appears on each Tuesday; no horizontal scroll at 390. Each event page shows date, venue, ticket price and photos.

### 3. Wizard copy
- **Expect**: the demo step lists Events; the All packs card says "100+ listings across all 10 listing types" and no longer says Events has no pack.
- **On fail**: `demo/event-pack.php`, `demo/photos.php`, `wb_listora_get_demo_packs()`.

## Pass criteria

1. A fresh import shows upcoming events on the calendar whatever day it is run.
