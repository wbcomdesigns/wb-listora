---
journey: calendar-block
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [listing-calendar-block, recurring-events, virtual-occurrences]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 2 published Event listings (one with single date, one with recurring rule)"
  - "A page hosting the listora/listing-calendar block"
estimated_runtime_minutes: 3
---

# Anonymous visitor browses the events calendar

Visit a page with the listing-calendar block. Verifies events render on correct days, recurring events generate virtual occurrences, navigation between months works, click-through reaches the event detail page.

## Setup

- Site: `$SITE_URL`
- Calendar page URL: `$CAL_URL`
- Fixture: 1 single-date event next month, 1 recurring weekly event for the next 4 weeks

## Steps

### 1. Open calendar page
- **Action**: `playwright_navigate $CAL_URL`
- **Expect**: calendar grid renders (month view default), today highlighted

### 2. Verify events appear on correct dates
- **Action**: `browser_evaluate "Array.from(document.querySelectorAll('.listora-calendar__event')).length"`
- **Expect**: ≥2 events visible across the displayed month

### 3. Verify recurring events generate virtual occurrences
- **Action**: count events that match the recurring fixture title
- **Expect**: 4 occurrences (one per week) within the month if it spans the recurrence

### 4. Navigate to next month
- **Action**: click → arrow / next-month button
- **Expect**:
  - Network: `GET /wp-json/listora/v1/calendar/events?from=...&to=...` (or whatever endpoint shape)
  - Calendar updates with next month's events

### 5. Click an event
- **Action**: click on an event tile
- **Expect**:
  - Either navigates to event detail page OR opens a popover with link to detail
  - Detail page renders the event with date/time/location

### 6. Verify date filter integration
- **Action**: navigate `/listings/?listora_listing_type=event&date_from=...&date_to=...`
- **Expect**: directory filters by date range, results match the calendar's events

## Pass criteria

1. Calendar grid renders with events on correct dates
2. Recurring events generate virtual occurrences (no DB row spam)
3. Month navigation works
4. Click reaches event detail
5. Date filter on directory grid is consistent with calendar

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Calendar empty despite events | wrong query or missing date meta | `blocks/listing-calendar/render.php`, `Calendar::get_events_for_month` |
| No recurring occurrences | recurrence rule generator broken | `class-recurring-events.php` virtual-occurrence loop |
| Navigation does nothing | view.js missing handler | `src/blocks/listing-calendar/view.js` |
| Date filter URL doesn't narrow | `Search_Engine::filter_by_date` regression | search engine date branch |
