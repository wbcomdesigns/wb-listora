---
journey: dashboard-tab-pagination
plugin: wb-listora
priority: high
roles: [member]
covers: [LST-F-06, dashboard, pagination, big-site-readiness]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member with MORE THAN 20 listings, >20 reviews written, >20 reviews received and >20 favourites"
  - "NEEDS SEEDED DATA — on a small dataset every pager correctly hides and this journey proves nothing"
  - "Auto-login mu-plugin present (?autologin=1)"
estimated_runtime_minutes: 6
covers_card: null
---

# Every dashboard tab must reach every row (LST-F-06 sentinel)

Claims was the only dashboard tab that paginated. The other four — listings,
reviews written, reviews received, favourites — took a flat `LIMIT 20` with no
way forward, **while the stat tile above them rendered the real `COUNT(*)`**. So
the numbers visibly disagreed with the list underneath: a member with 61
favourites read "61" and could reach 20 of them, and a vendor with 50 listings
could manage 20 from the frontend.

The paginated REST endpoints already existed. The block simply never called them,
and the fix keeps it server-rendered rather than wiring JS: each pager link
reloads with `?tab={tab}&{arg}=N` and `render.php` SSRs the matching slice — the
same model the active tab itself uses via `?tab=`. Works with JS off, survives
the back button.

Markup comes from `wb_listora_render_pagination()` in
`includes/class-render-helpers.php`, shared by all five tabs including Claims,
which was refactored onto it.

## Setup

- `$SITE_URL/my-listings/?autologin=1`
- Confirm the seed first — this journey is meaningless without it:
  ```sql
  SELECT (SELECT COUNT(*) FROM wp_posts WHERE post_author=1 AND post_type='listora_listing') AS listings,
         (SELECT COUNT(*) FROM wp_listora_reviews WHERE user_id=1) AS written,
         (SELECT COUNT(*) FROM wp_listora_favorites WHERE user_id=1) AS favourites;
  ```

## Steps

### 1. All five pagers render
- **Action**: load the dashboard, collect every `nav.listora-pagination`.
- **Expect**: five, with `aria-label`s — Listings, Reviews written, Reviews
  received, Favorites, Claims. Each shows "Page 1 of N" with **N > 1**.

### 2. Tile and reachable rows agree
- **Action**: compare each stat tile against its pager total × page size.
- **Expect**: they describe the same set. **A tile of 61 above a list capped at 20
  with no pager is the regression.**

### 3. Next changes the slice
- **Action**: `?tab=listings&listings_page=2`; compare the first row against page 1.
- **Expect**: different row, status "Page 2 of N", Previous is now a real `<a>`.

### 4. First and last pages disable the right control
- **Action**: page 1, then the last page.
- **Expect**: page 1 → Previous is a `<span aria-disabled="true">`; last page →
  Next is a `<span aria-disabled="true">`. Never a link that goes nowhere.

### 5. Out-of-range clamps to the last real page
- **Action**: `?tab=listings&listings_page=99999`.
- **Expect**: renders the **last page with rows on it** — reference run: "Page 276
  of 276" with 8 rows and Next disabled. **An empty state here is the regression**,
  and it is the exact bug the first cut of this fix shipped: the clamp was built
  on `WP_Query::found_posts`, which returns 0 when `paged` is past the end, so it
  never fired. The total now comes from a dedicated `COUNT(*)` taken *before* the
  slice query.
- **Repeat** for `reviews_page`, `received_page`, `favorites_page`, `claims_page`.

### 6. The two Reviews pagers are independent
- **Action**: `?tab=reviews&reviews_page=3`.
- **Expect**: written shows "Page 3 of N", received still "Page 1 of M". Paging
  one list must not reset the other — they carry separate query args for exactly
  this reason.

### 7. Page size is filterable
- **Action**: `add_filter( 'wb_listora_dashboard_per_page', fn() => 5 )`.
- **Expect**: every tab paginates at 5. The filter receives
  `( $per_page, $context, $user_id )` where `$context` is one of `listings`,
  `reviews_written`, `reviews_received`, `favorites`.

### 8. Small datasets show no pager at all
- **Action**: a member with fewer rows than one page.
- **Expect**: **no** `nav.listora-pagination` for that tab. A pager with one page
  is noise.

### 9. 390px
- **Expect**: pager visible and usable, no horizontal page scroll.

## Pass criteria

1. Five pagers, each "Page 1 of N" with N > 1 on a seeded member
2. Tile totals and reachable rows agree
3. Next/Previous change the slice; disabled ends are `<span aria-disabled>`
4. Out-of-range clamps to the last **populated** page on all five
5. The two Reviews pagers move independently
6. `wb_listora_dashboard_per_page` changes the page size
7. No pager below two pages
8. No overflow at 390px

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Tile says 61, list shows 20, no pager | a tab regressed to a flat `LIMIT 20` | `blocks/user-dashboard/render.php` — each tab needs a COUNT, a clamp and LIMIT/OFFSET |
| Out-of-range shows the empty state | clamp built on `found_posts` again instead of a dedicated COUNT | same file, the listings block — this is the trap |
| Paging "written" resets "received" | both pagers sharing one query arg | `templates/blocks/user-dashboard/tab-reviews.php` — must be `reviews_page` and `received_page` |
| Pager renders on a 3-row tab | the `total_pages < 2` guard was dropped | `wb_listora_render_pagination()` |
| Pager markup differs between tabs | a tab inlined its own nav instead of calling the helper | `includes/class-render-helpers.php` is the only copy |
| Counts wrong on a themed site | `COUNT(*)` replaced by `count()` on a LIMIT-ed result | never count a paged result set |
