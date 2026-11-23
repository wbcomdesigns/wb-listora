---
journey: listing-on-every-surface-matches
group: directory-search
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [subscriber, admin]
covers: [card-10531791500, card-10533222795, card-10533264164, card-10533264268, card-10533264518, card-10533222907, card-10533264405, listing-card-producers, load-more, infinite-scroll]
prerequisites:
  - "A listing titled with an ampersand ({title}) that has a featured image, a city and a state, in a type with several listings (more than one page of results)"
  - "A member who saved it; one saved listing then trashed, one set to draft"
  - "Pro active for steps 4 and 5 (Free alone: Load More does not exist)"
estimated_runtime_minutes: 12
last_verified: unrecorded
verified_against: free 2.1.2 / pro 2.1.2
viewports: [1440, 390]
themes: [reign, buddyx]
fails_on: unrecorded
site_profile: required
---

# One listing reads the same on every list, and page 2 matches page 1

## Why
A listing is built by four separate producers (web card, REST card helper, `/search`, detail). Each rule that drifted (title text, place text, image, saved count, layout of appended cards) lives in one function now; this walk proves the surfaces still agree, which no single-surface check does.

## Steps

### 1. Same title, place and image on every list
- **Action**: Open the grid, the Featured block, the member's Favorites tab and a Related listings block; call `GET /listora/v1/search`, `/favorites`, `/listings/{id}/detail`.
- **Expect**: title reads "Tom & Jerry Diner" (no `&amp;`) everywhere; the place is "Austin, TX" on the grid, Favorites tab and in `/favorites`; one image URL everywhere.

### 2. Saved count equals what the member sees
- **Action**: As the member: dashboard stats, Favorites tab, `GET /favorites` with `X-WP-Total`.
- **Expect**: the stat, the number of cards and the total are equal and exclude the trashed and the draft listing. The last pagination page is never empty.

### 3. Map zoom on a site that never saved Maps settings
- **Action**: Remove `map_default_zoom` from the options (fresh site), open a listing page and the Map block.
- **Expect**: the same zoom (12) on both, and Settings > Maps shows 12.

### 4. Load More / Infinite Scroll: card 21 matches card 1 (Pro)
- **Action**: Grid block with Card layout Compact, pagination mode Load More, then repeat with Infinite Scroll and with list view. Use a near-me search.
- **Expect**: appended cards carry the same layout class as page 1 (`listora-card--compact`, or `--horizontal` in list view); real image, type tag, price/fields, Featured badge; the distance badge on near-me; the heart shows the real saved count, not 0.

### 5. Hook parity (Pro)
- **Action**: Add an `add_action( 'wb_listora_grid_after_card', ... )` logger; load page 1 and press Load More.
- **Expect**: the hook fires once per card on page 1 and once per appended card.

## Failure
Name the surface and the field that differs, and the producer (web card, card helper, search, favourites, detail) before filing.
