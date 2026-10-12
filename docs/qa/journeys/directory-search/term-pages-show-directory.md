---
journey: term-pages-show-directory
group: directory-search
actor: customer
mode: mixed
plugin: wb-listora
priority: critical
roles: [guest, other, admin, owner]
covers: [term-archives, listing-archive-redirect, term-canonical, term-title-breadcrumb, term-paging, term-escape-hatch]
prerequisites:
  - "Site reachable at $SITE_URL with demo content and a Directory page mapped (Settings > General > Pages)"
  - "A category, location, tag, feature and listing type that each have published listings ({cat}, {loc}, {tag}, {feature}, {type}); one {feature} with more than 20 published listings for paging"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes: []
site_profile: required
---

# Category, location, tag, feature and type pages are the Directory, filtered to that term

## Why
Before 2.0.0 a term URL showed the theme's blog archive (no search, no map, "Read More" cards). Visitors and search engines landing on a term URL must get the Directory UI titled and bread-crumbed by the term. This journey also owns the bare `/listing/` redirect and the escape hatch.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Term URLs: `$SITE_URL/listing-category/{cat}/`, `/listing-location/{loc}/`, `/listing-tag/{tag}/`, `/listing-feature/{feature}/`, `/listing-type/{type}/` (use the real term links from `get_term_link()` because location slugs can be nested).
- Per-term expected count: `wp --path=$WP_PATH term get <taxonomy> <slug> --by=slug --field=count`.
- Roles: guest, `{other}` member, `{admin}` control, `{owner}` of a pending listing in the term.

## Steps

### 1. Each term URL renders the Directory (guest, 1440)
For each of the five term URLs:
- **Expect**: HTTP 200; `<h1>` = the term name; `<title>` = "<term name> - <site name>"; `body` has `listora-term-archive` and `listora-term-archive--<taxonomy>` (e.g. `--listora_listing_cat`); `<link rel="canonical">` = the term URL itself.
- **Expect**: breadcrumb `nav.listora-term-archive__crumbs[aria-label="Breadcrumb"]` reads "Directory > <term name>", first link = Directory URL, last item `aria-current="page"`.
- **Expect**: Directory UI present (`.listora-search`, `.listora-grid__count`, `.listora-card`); the count `Showing 1-N of T listings` has T = the term's published `count` (command in Setup).
- **Expect**: no theme blog chrome: no "Read More", no post-date/author byline on cards.
- **On fail**: `includes/core/class-term-archives.php::route_term_archive`, `Page_Registry::get_id('directory')`.

### 2. Description and the intro filter
- **Action**: set the `{cat}` term Description = `QA intro text`, Update.
- **Expect**: shown in `.listora-term-archive__desc` under the breadcrumb. Clear it: the div disappears (no empty box).
- **Action**: mu-plugin `add_filter( 'wb_listora_term_archive_intro', fn( $html, $term ) => '<p class="qa-intro">QA hello</p>', 10, 2 );`
- **Expect**: `.qa-intro` replaces the default intro. Remove the mu-plugin.

### 3. Paging and filters keep the term
- **Action**: open the `{feature}` term page that has more than one page.
- **Expect**: the page 2 link is `<term URL>?listora_page=2` (the term URL, not `/listings/?...`); it reads "Showing 21-N of N listings"; `?listora_page=999` shows the last page, never "Showing 1,961-127 of 127".
- **Action**: type a keyword in the search and press Enter.
- **Expect**: results narrow to a subset of the term's listings (every card is in the term; spot-check one with `wp post term list <id> listora_listing_feature --field=slug`).
- **Action**: "Clear all" while the term URL is open.
- **Expect**: filters are removed but the term stays: the grid never shows listings outside the term while the term URL is open.

### 4. Bare archive and unknown terms
- **Action**: `curl -sI $SITE_URL/listing/`.
- **Expect**: `301` with `Location: $SITE_URL/listings/` (the Directory page).
- **Action**: `curl -sI $SITE_URL/listing-category/no-such-term/`.
- **Expect**: `404`. A real term with zero published listings returns 200 with the Directory empty state ("No listings found"), not 404.
- **Positive control**: the `{cat}` URL is 200 in the same run, so the 404 is the unknown term and not a broken route.

### 5. Single listings are untouched
- **Action**: open any `/listing/<slug>/`.
- **Expect**: the normal listing page (redirect only hits the archive); its category chip links to the term page from step 1.

### 6. Lifecycle: drafts and pending never appear
- **Action**: put one `{cat}` listing in `draft`, `pending`, `trash`, and set one author deleted-case; reload the term page each time.
- **Expect**: count and cards exclude it each time and equal the REST `GET /search?category={cat}` total; the `{owner}` of a pending listing sees it only in their Dashboard.

### 7. Role ladder
| Rung | Expect |
|---|---|
| guest | steps 1-5 |
| `{other}` | identical output; no owner bar, no admin links |
| `{admin}` (control) | identical output plus the admin bar; counts unchanged (no drafts) |
| `{owner}` with a pending listing | pending listing not counted or listed on the term page |

### 8. Escape hatch
- **Action**: mu-plugin `add_filter( 'wb_listora_term_archive_filters', '__return_empty_array' );`
- **Expect**: term URLs fall back to the theme archive (documented opt-out); remove the mu-plugin and step 1 passes again.

### 9. Scale
- **Action**: on a site with a term holding 2,000+ published listings (QA seed) open its term URL and page 100.
- **Expect**: one count query, `LIMIT 20` row query, first paint under 1 s; the last page clamps. Not verified live.

### 10. Layout (390)
- **Expect**: no horizontal page scroll; breadcrumb wraps; search bar and card grid stack; tap targets >= 40px; dark mode colours come from tokens (no white breadcrumb box on `body.buddyx-dark-theme`).

## Role ladder
See step 7.

## Pass criteria
1. All five taxonomies render the Directory with the term title, breadcrumb, canonical and count.
2. Paging stays on the term URL and a past-the-end page shows the last page.
3. `/listing/` 301s to the Directory; an unknown term 404s while a real one is 200 in the same run.
4. Same output for every role; drafts and pending never appear.
5. The filter escape hatch restores the theme archive.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Blog-style archive with "Read More" | `request` filter not routing (no Directory page mapped) | `includes/core/class-term-archives.php`, `Page_Registry` |
| `<h1>` is "Directory" not the term | theme reads the title outside the loop | `Term_Archives::page_title` / `document_title` |
| Canonical points at `/listings/` | SEO plugin canonical not filtered | `Term_Archives::canonical` |
| Count differs from `term get ... count` | search index stale | `wp listora reindex` |
| `/listing/` does not redirect | archive redirect | `includes/core/class-term-archives.php:49` (`redirect_post_type_archive`) |

## Not verified
Steps 6 (trash and deleted-author cases) and 9.

## Cleanup
Remove the intro description, mu-plugins and any QA seed; restore draft/pending listings.
