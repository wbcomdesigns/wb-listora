---
journey: calendar-block
plugin: wb-listora
priority: normal
roles: [anonymous, subscriber, admin]
covers: [listing-calendar-block, recurring-events, virtual-occurrences, multi-day-events, month-navigation, phone-agenda, empty-month, directory-date-filter]
prerequisites:
  - "Site reachable at $SITE_URL (Local: http://wb-listora.local)"
  - "A page hosting the listora/listing-calendar block: $SITE_URL/calendar-demo/ (page id 806)"
  - "At least 3 published Event listings dated near today: one single-day, one multi-day, one weekly recurring with a recurrence end. This site has none by default (the Events demo pack is not imported); create `QA-J1 Event Single` (start +2 days 18:00), `QA-J1 Event Multiday` (+4 to +6 days), `QA-J1 Event Weekly` (start +1 day, `_listora_recurrence_type=weekly`, `_listora_recurrence_end` +40 days) or import the 2.0.0 Events demo pack (eight events dated relative to the import day)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Events calendar

A visitor opens a page with the Events Calendar block. Events appear on the right days (multi-day events on every day they cover, weekly events once per week), month arrows move through months, an event links to its listing, an empty month says so, and phones also get a month agenda. The Directory filtered by the same dates agrees with the calendar.

Verified via: Playwright (isolated contexts, 1440 and 390) plus curl. Dates below are for 2026-10-11 (today); recompute from your own "today".

## Setup

- Calendar page: `$CAL_URL = $SITE_URL/calendar-demo/`. Block context: `{"calendarMonth":10,"calendarYear":2026,"listingType":"event"}` on `.wp-block-listora-listing-calendar`.
- Event meta keys: `_listora_start_date` (`Y-m-d` or `Y-m-d H:i`), `_listora_end_date`, `_listora_recurrence_type` (`daily|weekly|monthly`), `_listora_recurrence_end`, listing type term `event`. After writing meta, re-index the listing (Search_Indexer) or save it from the editor.
- URL contract: `?cal_year=YYYY&cal_month=M`.

## Steps

### 1. Open the calendar page
- **Action**: open `$CAL_URL` logged out.
- **Expect**: `.listora-calendar__month` reads the current month ("October 2026"); `.listora-calendar__grid-wrap[role="grid"]` has `aria-label` = the month; seven `.listora-calendar__day-header` (Sun..Sat); today's cell has `listora-calendar__cell--today` and `aria-label="October 11, today"`; two `.listora-calendar__nav-btn` buttons with `aria-label` "Previous month" and "Next month".
- **On fail**: `blocks/listing-calendar/render.php`, `templates/blocks/listing-calendar/calendar.php`

### 2. Events sit on the right days
- **Action**: read `.listora-calendar__grid .listora-calendar__event` with their cell's `aria-label`.
- **Expect** (fixtures above, today = Oct 11): `QA-J1 Event Single` on "October 13"; `QA-J1 Event Multiday` on "October 15", "October 16" and "October 17" (one tile per covered day); `QA-J1 Event Weekly` on "October 12", "October 19" and "October 26". Cells with events have `listora-calendar__cell--has-events`; each tile is an `<a class="listora-calendar__event">` whose `href` is the listing permalink and `title` its name.
- **On fail**: `includes/core/class-recurrence.php:300` (`events_overlapping`), `:341` (recurring series)

### 3. Recurring events are virtual occurrences
- **Action**: count `QA-J1 Event Weekly` tiles this month, then check the DB.
- **Expect**: 3 tiles in October (12, 19, 26); `SELECT COUNT(*) FROM wp_posts WHERE post_title='QA-J1 Event Weekly'` is `1` (no row per occurrence); no occurrence after `_listora_recurrence_end`.

### 4. Month navigation
- **Action**: click `.listora-calendar__nav-btn[aria-label="Next month"]`.
- **Expect**: the page navigates to `/calendar-demo/?cal_year=2026&cal_month=11` (a full page load, not a REST call); heading "November 2026"; tiles `QA-J1 Event Weekly` on "November 2", "November 9", "November 16"; no single or multi-day events. "Previous month" returns to `?cal_year=2026&cal_month=10`.
- **On fail**: `src/interactivity/store.js:2569` (`navigateMonth`)

### 5. Empty month
- **Action**: open `$CAL_URL?cal_year=2027&cal_month=6`.
- **Expect**: heading "June 2027"; `.listora-calendar__empty` (`role="status"`) reads "No events in June 2027."; grid still renders. Out-of-range values are clamped (`cal_year=3000&cal_month=13` shows "December 2100").
- **POSITIVE CONTROL**: step 2 shows tiles for the same block in a month that has events.

### 6. Click-through
- **Action**: click the `QA-J1 Event Single` tile.
- **Expect**: lands on `/listing/qa-j1-event-single/`, `h1` "QA-J1 Event Single".

### 7. Directory date filter agrees with the calendar
- **Action**: open `$SITE_URL/listings/?listora_type=event&listora_date_from=2026-10-12&listora_date_to=2026-10-14`.
- **Expect**: count "Showing 1-2 of 2 listings": QA-J1 Event Single (Oct 13) and QA-J1 Event Weekly (Oct 12); the multi-day event (Oct 15-17) is excluded. Same result from `GET /listora/v1/search?type=event&date_from=2026-10-12&date_to=2026-10-14` (`total: 2`). Without the dates `?listora_type=event` lists all 3.

### 8. ROLE LADDER
| Actor | Action | Expect |
|---|---|---|
| Logged out | steps 1-7 | as above |
| Member / editor / admin | steps 1-2 | same tiles (the calendar shows published events only, for every role) |
| Event owner, event in `draft` or `pending` | open the calendar | NOT shown for anyone: both queries require `post_status = 'publish'` (`includes/core/class-recurrence.php:311,353`; read from code, not exercised in the browser). Control: publish it and it appears on the next load |

### 9. Phone layout (390)
- **Action**: open `$CAL_URL` at 390x844.
- **Expect**: `scrollWidth <= 390`; the grid and `ul.listora-calendar__agenda` (aria-label "Events in October 2026") are both present and the agenda is visible (it is hidden on desktop); agenda rows "Mon 12", "Tue 13", "Thu 15"... each hold the event link; agenda links are 44px high. The two month arrows measure 31x31, below the 40px tap-target floor (see PRODUCT-BUGS in the report).

## Pass criteria

1. Month grid with today highlighted renders for logged-out visitors.
2. Single, multi-day and weekly events appear on exactly the days listed.
3. Recurring occurrences are virtual (one post).
4. Next/Previous month change `cal_year`/`cal_month` and the heading.
5. Empty month shows "No events in <Month Year>."
6. Tile links to the listing; Directory date filter matches.
7. Agenda is visible on phones; no horizontal scroll.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Calendar empty with events present | meta missing, wrong format or listing not indexed | `includes/core/class-recurrence.php:300`, `blocks/listing-calendar/render.php:48` |
| Multi-day shown only on day 1 | overlap query | `includes/core/class-recurrence.php:300-330` |
| No weekly occurrences | recurrence generator / `_listora_recurrence_type` | `includes/core/class-recurrence.php:191`, `:341-360` |
| Arrows do nothing | handler unbound | `src/interactivity/store.js:2569`, `templates/blocks/listing-calendar/calendar.php` |
| No agenda on phones | template/CSS | `templates/blocks/listing-calendar/calendar.php`, `blocks/listing-calendar/style.css` |
| Directory ignores dates | URL param names | `includes/search/search-url-helpers.php`, `src/interactivity/store.js:837` |
