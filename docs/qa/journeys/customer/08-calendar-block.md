---
journey: calendar-block
plugin: wb-listora
priority: normal
roles: [guest, member, owner, admin]
covers: [listing-calendar-block, recurring-events, virtual-occurrences, multi-day-events, month-navigation, phone-agenda, empty-month, directory-date-filter, event-timezone, bc-10393557243]
prerequisites:
  - "Site reachable at $SITE_URL; a page hosting the listora/listing-calendar block ($CAL_URL)"
  - "Three published Event listings dated near today: single-day, multi-day, weekly recurring with a recurrence end (create QA-prefixed ones in Setup if the Events demo pack is not imported)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes: []
fails_on: 61f5ba3b
site_profile: required
---

# An Events calendar shows each event on the right days, navigates by month, and agrees with the Directory date filter

## Why
Visitors plan around the calendar; an event on the wrong day or an arrow that does nothing sends them to the wrong date. Phones need the agenda list because the grid is cramped.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `$CAL_URL` = the calendar page; `{today}` = site-local today.
- Fixtures (QA-prefixed, delete in Cleanup): `QA Event Single` (start {today}+2d 18:00), `QA Event Multiday` ({today}+4d to +6d), `QA Event Weekly` (start {today}+1d, `_listora_recurrence_type=weekly`, `_listora_recurrence_end` {today}+40d). Re-index each after writing meta (Search_Indexer) or save from the editor. Fixtures output from docs/qa/fixtures/2.0-fixtures.php lists the ids.
- Block context: `{"calendarMonth":M,"calendarYear":Y,"listingType":"event"}` on `.wp-block-listora-listing-calendar`. Meta keys: `_listora_start_date` (`Y-m-d` or `Y-m-d H:i`), `_listora_end_date`, `_listora_recurrence_type` (`daily|weekly|monthly`), `_listora_recurrence_end`. URL contract: `?cal_year=YYYY&cal_month=M`.
- Compute expected cells from `{today}`; never copy dates from a previous run.

## Steps

### 1. Open the calendar page
- **Action**: open `$CAL_URL` as guest.
- **Expect**: `.listora-calendar__month` reads the current month; `.listora-calendar__grid-wrap[role="grid"]` has `aria-label` = the month; seven `.listora-calendar__day-header` (Sun..Sat); today's cell has `listora-calendar__cell--today` and `aria-label="<Month> <d>, today"`; two `.listora-calendar__nav-btn` with `aria-label` "Previous month" / "Next month".

### 2. Events sit on the right days
- **Action**: read `.listora-calendar__grid .listora-calendar__event` with each cell's `aria-label`.
- **Expect**: Single on {today}+2d; Multiday on each of {today}+4d, +5d, +6d (one tile per covered day); Weekly on {today}+1d, +8d, +15d, ... until the recurrence end. Cells with events have `listora-calendar__cell--has-events`; each tile is an `<a class="listora-calendar__event">` whose `href` is the listing permalink and `title` its name.

### 3. Recurring events are virtual occurrences
- **Action**: count `QA Event Weekly` tiles this month, then `wp post list --post_type=listora_listing --title="QA Event Weekly" --format=count`.
- **Expect**: tiles = number of weekly dates in the month up to the end; DB count is `1` (no row per occurrence); no tile after `_listora_recurrence_end`.

### 4. Month navigation
- **Action**: click `.listora-calendar__nav-btn[aria-label="Next month"]`.
- **Expect**: full page load (not REST) to `$CAL_URL?cal_year=<Y>&cal_month=<M+1>`; heading is next month; Weekly tiles on its weekly dates; Single and Multiday absent unless they fall in it. "Previous month" returns to the first URL.

### 5. Empty month and clamping
- **Action**: open `$CAL_URL?cal_year=<Y+1>&cal_month=6` (a month with no events).
- **Expect**: heading "June <Y+1>"; `.listora-calendar__empty` (`role="status"`) reads "No events in June <Y+1>."; the grid still renders. `cal_year=3000&cal_month=13` shows "December 2100".
- **Positive control**: step 2 shows tiles for the same block in a month that has events.

### 6. Click-through
- **Action**: click the `QA Event Single` tile.
- **Expect**: lands on its permalink, `h1` "QA Event Single".

### 7. Directory date filter agrees with the calendar
- **Action**: open `$SITE_URL/listings/?listora_type=event&listora_date_from=<{today}+1d>&listora_date_to=<{today}+3d>`.
- **Expect**: count "Showing 1-2 of 2 listings": Single and Weekly; Multiday (+4d to +6d) excluded. `GET /listora/v1/search?type=event&date_from=...&date_to=...` returns `total: 2`. Without dates `?listora_type=event` lists all 3 fixtures (plus any other events; compare to REST).

### 8. Lifecycle: only published events show
- **Action**: set `QA Event Single` to `draft`, then `pending`, then `trash`, reloading the calendar each time; publish it again.
- **Expect**: absent for every role in each non-published state; present again after publish (both queries require `post_status = 'publish'`, `includes/core/class-recurrence.php:311,353`).
- **Positive control**: the publish-again reload.

### 9. Phone layout (390)
- **Action**: open `$CAL_URL` at 390x844.
- **Expect**: `scrollWidth <= 390`; the grid and `ul.listora-calendar__agenda` (aria-label "Events in <Month Year>") are both present and the agenda is visible (hidden on desktop); agenda rows hold the event links; agenda links >= 40px high.
- **expected-fail**: the two month arrows measure 31x31, below the 40px tap-target floor (product finding; `templates/blocks/listing-calendar/calendar.php`).

### 10. Event time reads the same in every site timezone (bc-10393557243)
- **Action** (`{admin}`, then guest): for each of `America/New_York`, `Asia/Kolkata` (UTC+5:30), `Pacific/Kiritimati` (UTC+14), `Etc/GMT+12` (UTC-12) and `UTC`, set Settings > General timezone, create an Event starting `<{today}+10d> 09:00` (end `10:30`), publish, then open the listing page, its directory card and the calendar tile popover.
- **Expect**: all three surfaces read `9:00 AM` and the same date in every timezone; the calendar puts it in that day's cell, never the day before or after.
- **Read-back**: `wp post meta get <id> _listora_start_date` is `<date> 09:00` (stored as entered, not shifted) after each change; `GET /listings/<id>` `meta.start_date` equals it. Restore UTC.
- **fails_on**: 61f5ba3b (inferred from 48961d11, not card-matched). Not verified live.

### 11. Scale and N=0/1/>1
- **Action**: with 200 QA events spread over one month, open that month; with 0 and exactly 1 event repeat step 2.
- **Expect**: page renders under 2 s; a day with many events lists them all without clipping the grid (overflow indicator or scroll, named in the run notes); 0 events shows step 5's empty state; 1 event shows one tile. Not verified live.

## Role ladder
| Actor | Action | Expect |
|---|---|---|
| guest | steps 1-9 | as above |
| `{member}` / `{editor}` / `{admin}` | steps 1-2 | same tiles (published events only, every role) |
| event `{owner}` with a draft/pending event | open the calendar | NOT shown to anyone (step 8); owner sees it in their Dashboard only |

## Pass criteria
1. Month grid with today highlighted renders for guests.
2. Single, multi-day and weekly events appear on exactly the computed days; recurring occurrences are virtual (one post).
3. Next/Previous change `cal_year`/`cal_month` and the heading; an empty month says "No events in <Month Year>."
4. Tile links to the listing; the Directory date filter matches.
5. Event time and day are identical across timezones; non-published events never appear.
6. Agenda is visible on phones; no horizontal scroll.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Calendar empty with events present | meta missing/wrong format or not indexed | `includes/core/class-recurrence.php:300`, `blocks/listing-calendar/render.php:48` |
| Multi-day shown only on day 1 | overlap query | `includes/core/class-recurrence.php:300-330` |
| No weekly occurrences | recurrence generator | `includes/core/class-recurrence.php:191`, `:341-360` |
| Arrows do nothing | handler unbound | `src/interactivity/store.js:2569`, `templates/blocks/listing-calendar/calendar.php` |
| No agenda on phones | template/CSS | `blocks/listing-calendar/style.css` |
| Directory ignores dates | URL param names | `includes/search/search-url-helpers.php` |
| Time shifts with timezone | date parsed through site tz | `includes/core/class-recurrence.php` |

## Not verified
Steps 8, 10 and 11 were not run live; the 390px arrow size is a recorded product finding.

## Cleanup
Delete the QA events; restore the site timezone to UTC.
