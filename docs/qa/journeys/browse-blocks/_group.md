---
group: browse-blocks
plugin: wb-listora
scope: Listing cards, the grid/list toolbar, Categories and Calendar blocks, the home screen, and the Save (favourite) controls
journeys: 7
site_profile: required
---
# Browse blocks: what an agent checks here

## Scope
Covers how listings are shown for browsing: the card (image, badges, heart, body, meta), the grid block toolbar (count, view toggle, sort, pagination), the Categories tile block, the Events Calendar block, the home-screen composition, and the Save heart on card and detail.

Not covered here: the search bar, filters, suggestions, empty-state-after-filter, term pages and the Featured carousel (directory-search); the single listing page body (listing-detail); the member dashboard shell and other tabs (dashboard-account); the map panel and its popup (map). Compare, Quick view, Load more and Infinite scroll are Pro-owned: run `wb-listora-pro/docs/qa/journeys/browse-blocks/_group.md` after this one.

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Directory grid (cards + toolbar + pagination) | `$SITE_URL/{directory}/` | guest, {owner}, {other}, {editor}, {admin} | `blocks/listing-grid/render.php`, `templates/blocks/listing-grid/{grid,toolbar,pagination}.php` |
| 2 | Card (inside any grid, featured track, dashboard favourites) | any surface above | all | `templates/blocks/listing-card/{card,card-image,card-content,card-actions}.php` |
| 3 | Home screen (search + type chips + categories + grid) | `$SITE_URL/` (page that holds the blocks) | guest, member, admin | `templates/blocks/listing-search/search.php`, `listing-categories/categories.php`, grid templates |
| 4 | Categories block | any page holding `listora/listing-categories` | all | `blocks/listing-categories/render.php`, `templates/blocks/listing-categories/{categories,category-card}.php` |
| 5 | Calendar block | any page holding `listora/listing-calendar` (default `listingType` = event) | all | `blocks/listing-calendar/render.php`, `templates/blocks/listing-calendar/calendar.php` |
| 6 | Dashboard Favorites tab | `{dashboard}?tab=favorites` | {owner}, {other} (own list only) | `templates/blocks/user-dashboard/tab-favorites.php` |
| 7 | Detail page Save button + count | `$SITE_URL/listing/{listing}/` | all (guest gets login modal) | `blocks/listing-detail/render.php` (~:524, ~:1039, modal ~:1084) |
| 8 | Login modal after guest taps Save | surface 7 or 2 as guest | guest | `blocks/listing-detail/render.php` |
| 9 | Grid with a placeholder (no featured image) card | listing with no photo in surface 1 | all | `templates/blocks/listing-card/card-image.php` |

## Components on each surface
1. Directory grid: toolbar `.listora-grid__toolbar` (result count `.listora-grid__count` role=status aria-live, "Showing a-b of N listings"; view toggle `.listora-grid__view-toggle` radiogroup with two `.listora-grid__view-btn` role=radio aria-checked; sort `select.listora-grid__sort-select` labelled "Sort by"); results `.listora-grid__results.listora-grid` role=list aria-busy (`--list` modifier for list view); skeletons `.listora-grid__skeletons` (aria-hidden, shown while loading); empty state `.listora-grid__empty` (Clear All Filters / Add a listing); pagination `nav.listora-grid__pagination` (prev `.listora-grid__page-btn`, numbers `.listora-grid__page-num` with `.is-active` + aria-current, ellipsis `.listora-grid__page-ellipsis`, next).
2. Card `.listora-card` role=listitem (`--grid`/`--list` layout class, `.listora-type--{slug}`): media `.listora-card__media` > image link `.listora-card__image-link` (tabindex -1, aria-hidden) > `img.listora-card__image`; featured badge `.listora-card__badge-featured`; heart `button.listora-favorite-btn.listora-card__favorite` with `.listora-favorite-btn__count`; rating `.listora-card__rating`; body `.listora-card__body`: type badge `.listora-card__type`, title `h3.listora-card__title a`, location `address.listora-card__location`, owner `.listora-card__owner`, next occurrence `.listora-card__next-occurrence`, meta `.listora-card__meta-item` (sr-only label + aria-hidden value), features `.listora-feature-badge` (+ `--more`), excerpt `.listora-card__excerpt` (20 words), distance `.listora-card__distance`; actions `.listora-card__actions` (extension point, Pro adds Compare + Quick view).
3. Home screen: type tabs `.listora-search__type-tabs` inside `.listora-search__type-tabs-wrap` with prev/next arrows `.listora-search__type-tabs-nav--prev|--next` (`data-listora-scroll`), then Categories block, then grid; block-to-block spacing between wrappers.
4. Categories: wrapper `.listora-block.listora-categories` (or `--empty .listora-card--empty`); top grid `.listora-categories__grid--top` role=list; tile `a.listora-categories__card` role=listitem with aria-label "{name}, {type}": icon wrap `.listora-categories__icon-wrap` (letter fallback `.listora-categories__letter`), `.listora-categories__name`, `.listora-categories__type`, `.listora-categories__count` ("N listing/listings"); overflow `details.listora-categories__all` > `summary` (`View all N categories` / `Show fewer`) > `section.listora-categories__group` > `h3.listora-categories__group-title` + grid.
5. Calendar: header `.listora-calendar__header` (`h2.listora-calendar__month`, prev/next `button.listora-calendar__nav-btn`); `div.listora-calendar__grid-wrap` role=grid labelled by month; `.listora-calendar__day-header` x7 role=columnheader; cells `.listora-calendar__cell` (`--empty`, `--today`, `--has-events`) role=gridcell with aria-label; day number `.listora-calendar__day-num`; events `a.listora-calendar__event` (title attr, `--event-color`); overflow `.listora-calendar__more` "+N"; phone agenda `ul.listora-calendar__agenda` > `.listora-calendar__agenda-day` (`.listora-calendar__agenda-date`, `.listora-calendar__agenda-events`); empty state role=status.
6. Dashboard Favorites: `#dash-panel-favorites` role=tabpanel (`aria-labelledby=dash-tab-favorites`); empty state (shared empty component); `.listora-dashboard__favorites-grid.listora-grid` with 2 columns of cards (surface 2); hooks `wb_listora_before_dashboard_favorites` / `_after_`.
7. Detail Save: `button.listora-btn.listora-btn--secondary` (data-wp-class--is-favorited) and `.listora-detail__favorite-count`; second Save button in the sticky/mobile bar (~:1039).
8. Login modal: title and `.listora-detail__modal-desc` text bound to `state.loginModalDesc`.

## States to force
| Surface | State | How |
|---|---|---|
| 1 | empty / one / many / many pages | `wp post list --post_type=listora_listing --post_status=publish --format=count` vs `perPage`; filter to 0 with a keyword nobody matches; seed with 2.0-fixtures `seed` |
| 1 | loading | throttle `/listora/v1/search` then change sort |
| 1 | list view default / remembered | block attr `defaultView`; localStorage view key (see grid-view-mode journey) |
| 1 | past last page | `?listora_page=999` (prefix per directory-url-params journey) |
| 2 | no featured image, untitled title, 120-char title, long word | fixtures; edit a QA-prefixed listing |
| 2 | featured, rated, favourited | `listora_favorites` row; featured flag; reviews |
| 4 | hideEmpty on/off, limit overflow (>12), listingType pinned | block attributes `hideEmpty`, `limit`, `listingType` |
| 5 | empty month, recurring, event spanning months, 4+ events one day | create QA events; navigate months |
| 6 | zero favourites, 1, 25+ | clean `listora_favorites` rows for {owner} (command in browse-and-favourite journey) |
| 7/8 | guest vs member, favorites feature off | `wb_listora_features.favorites` false |
| all | RTL | switch site language to an RTL locale (stylesheet twins `*-rtl.css`) |

## Settings that change this group
| Setting | Values | What must visibly change, where |
|---|---|---|
| `wb_listora_features.favorites` (Settings > Features) | on / off | heart on every card, Save on detail, Favorites tab all appear/disappear together; REST favourites 403/404 when off |
| Grid block `defaultView` | grid / list | initial layout and `.is-active` + aria-checked of the toggle on surface 1 |
| Grid block `showViewToggle`, `showResultCount`, `showSort`, `showPagination` | on / off | the matching toolbar/pagination part absent |
| Grid block `perPage`, `columns`, `cardLayout` | numbers / layout | cards per page, columns, card layout class |
| Categories block `hideEmpty`, `showCount`, `showIcon`, `limit`, `columns`, `listingType` | per attr | empty tiles hidden/shown, count line, icon vs letter, top-N vs "View all", columns, type filter |
| Settings > General `per_page` | int | default grid page size |
| Pro `pagination_type` (page numbers / load more / infinite) | 3 values | replaces surface 1 pagination (Pro group) |

## Fixtures and setup
- `wp --path=$WP_PATH eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed` (idempotent, tags rows `_j20_fixture`); restore with `... clean`.
- Personas and content slugs resolve from `docs/qa/sites/<site-host>.json`.
- Clear favourites before a run: command in `browse-and-favourite-a-listing.md` step setup; restore `wb_listora_features` to its recorded value afterwards.
- Remove any QA-prefixed listing, event or category you created.

## Run order
1. `browse-and-favourite-a-listing.md` - mixed, critical: find, save, dashboard tab, un-save, feature gate.
2. `favorite-controls-on-card-and-detail.md` - browser, high: heart overlay and detail count move together.
3. `grid-view-mode-default-and-remembered.md` - browser, high: Default View vs remembered choice.
4. `card-image-alt-fallback.md` - mixed, high: placeholder and alt rules on card/detail/map.
5. `home-screen-ux.md` - browser, high: spacing, type-chip arrows, two-column tiles.
6. `categories-block.md` - browser, normal: tiles, counts, hideEmpty, View all.
7. `calendar-block.md` - mixed, normal: days, recurrence, month nav, timezone.
Then run: `wb-listora-pro/docs/qa/journeys/browse-blocks/_group.md`

## Group-specific micro checks
- G-01 The toolbar count "Showing a-b of N listings" has b = a + cards on screen - 1 and N equals the REST `X-WP-Total` for the same query.
- G-02 With N = 1 the count text reads singular ("1 result"/"Showing 1-1 of 1") and the single card does not stretch to full width in grid view.
- G-03 Exactly one `.listora-grid__view-btn` has `.is-active` and `aria-checked="true"`; the other has `aria-checked="false"`; they flip together on click and after reload.
- G-04 The heart `.listora-favorite-btn__count` equals the count beside Save on the detail page for the same listing, before and after toggling, and after reload.
- G-05 `aria-pressed` on the heart equals `is-favorited` class; the `aria-label` changes between "Save" and "Remove" wording with the state.
- G-06 The card image link has `tabindex=-1` and `aria-hidden`, so the title link is the only tab stop to the listing; the heart and actions come in DOM order after it with a visible focus ring.
- G-07 A card without a featured image has `img` with `alt=""` and `aria-hidden="true"`; with an image the alt is the title; an untitled listing falls back to "Listing #ID".
- G-08 The type badge, title, location and meta items align to the same left edge across all cards in a row; card heights in a row are equal (flex stretch) at 1440 and 390.
- G-09 `.listora-card__excerpt` is at most 20 words, ends with an ellipsis only when cut, and never splits an emoji or HTML entity.
- G-10 `.listora-categories__count` uses the singular for 1 ("1 listing") and a thousands separator for 4-digit counts; the number equals `wp term get ... --field=count` for published listings only (no drafts).
- G-11 "View all N categories": N equals the total number of non-hidden categories and the sum of top tiles + grouped tiles; the `summary` toggles to "Show fewer" and keyboard Space/Enter opens it.
- G-12 Category tile aria-label is "{name}, {type}" when the type label is non-empty and just "{name}" otherwise; no trailing comma.
- G-13 The calendar agenda (phone) lists the same events and dates as the grid cells for the month; `+N` equals events on that day minus the visible ones.
- G-14 `.listora-calendar__cell--today` appears on exactly one cell, only when the visible month is the current site-timezone month.
- G-15 The month heading and `grid-wrap` aria-label are the same string; prev/next change both and the URL/state without a full reload.
- G-16 Type-chip arrows (`.listora-search__type-tabs-nav`) are hidden when the chips fit and each disabled/hidden arrow is not in the tab order.
- G-17 The skeleton card count while loading equals the `perPage` value, and `aria-busy` returns to `false` after the request, also after an error.
- G-18 Pagination `aria-current="page"` sits on the same element as `.is-active`; ellipsis never appears between consecutive page numbers.

## Known defects (expected-fail)
| Journey | Step | Defect |
|---|---|---|
| calendar-block.md | 9 | Month prev/next arrows measure 31 x 31 px at 390, below the 40 px floor (`templates/blocks/listing-calendar/calendar.php`, `.listora-calendar__nav-btn`) |

## Not covered yet
- Grid sort select (`#listora-sort`) values and their effect on card order: no journey asserts each option.
- Card-level hooks (`wb_listora_before_card*`, `wb_listora_card_actions`, `wb_listora_grid_after_card`) and card template override: only the dashboard favourites override is tested.
- Card distance line ("N mi/km away") when a near-me search is active, and the next-occurrence line for recurring events on cards.
- Card owner line ("Listed by") including the deleted-author and unnamed-member cases.
- Grid block toggles `showViewToggle`, `showResultCount`, `showSort`, `showPagination` off, and `columns`/`cardLayout` variants.
- Categories block `uniqueId`, per-device `hideOnDesktop|Tablet|Mobile` and spacing/shadow attributes rendering on the front end.
- Misfiled: part of `home-screen-ux.md` (type-chip arrows) belongs to directory-search; the dashboard steps of `browse-and-favourite-a-listing.md` (7-9) and the detail step of `favorite-controls-on-card-and-detail.md` (B) overlap dashboard-account and listing-detail.
