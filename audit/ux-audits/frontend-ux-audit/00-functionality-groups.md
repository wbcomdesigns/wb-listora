# Frontend UX Audit — Functionality Groups

**Why this exists:** WB Listora has 16 blocks + 54 PHP templates + 2 HTML templates + 1,153-line shared.css + 11 per-block stylesheets. Reviewing them block-by-block obscures the real organizing principle: every surface belongs to one **customer functionality** (Discover / Read / Submit / Manage / Review / Pro-extend). Grouping by functionality gives a coherent UX review that mirrors how a user actually moves through the directory.

**Method:** Each group below names the customer task it serves, lists every block/template/CSS file involved, names the URL routes a customer hits, and the Pro extensions that layer in. The per-group audit docs (`01-discovery.md`, `02-listing-detail.md`, etc.) drill into HTML organization, CSS structure, BEM consistency, token usage, responsive behavior, and the live browser view at desktop + mobile.

**Scope:** Free + Pro frontend only. Email templates are a separate visual system (Group 7 — Foundation).

---

## G1 — Discovery (find a listing)

What a customer does: arrive on the directory, search, filter, browse cards, peek at locations on a map.

| Surface | File(s) |
|---|---|
| Block: listing-search | `blocks/listing-search/{block.json,render.php,style.css}` + `src/blocks/listing-search/view.js` |
| Block: listing-grid | `blocks/listing-grid/{block.json,render.php,style.css}` + `src/blocks/listing-grid/view.js` |
| Block: listing-card | `blocks/listing-card/{block.json,render.php,style.css}` |
| Block: listing-map | `blocks/listing-map/{block.json,render.php,style.css}` + `src/blocks/listing-map/view.js` |
| Block: listing-categories | `blocks/listing-categories/{block.json,render.php,style.css}` |
| Block: listing-featured | `blocks/listing-featured/{block.json,render.php,style.css}` |
| Block: listing-calendar | `blocks/listing-calendar/{block.json,render.php,style.css}` |
| Templates: search | `templates/blocks/listing-search/{search,search-bar,filters}.php` |
| Templates: grid | `templates/blocks/listing-grid/{grid,pagination,toolbar}.php` |
| Templates: card | `templates/blocks/listing-card/{card,card-image,card-content,card-actions}.php` |
| Templates: map | `templates/blocks/listing-map/map.php` |
| Templates: categories | `templates/blocks/listing-categories/{categories,category-card}.php` |
| Templates: featured | `templates/blocks/listing-featured/featured.php` |
| Templates: calendar | `templates/blocks/listing-calendar/calendar.php` |
| Customer URLs | `/`, `/listings/`, `/business/` (category), `/listings/?listora_listing_cat=…`, `/featured-listings/` |
| Pro extensions | `infinite_scroll` toggle, `quick_view` toggle (cards), `comparison` "Add to Compare" button on cards, `google_maps` provider swap |

**Audit doc:** `01-discovery.md` (next)

---

## G2 — Listing Detail (read a listing)

What a customer does: open a single listing, scroll the gallery, read tabs, claim/share/save it.

| Surface | File(s) |
|---|---|
| Block: listing-detail | `blocks/listing-detail/{block.json,render.php,style.css}` |
| Templates: detail | `templates/blocks/listing-detail/{gallery,sidebar,tabs}.php` |
| Theme templates | `templates/single-listora_listing.{html,php}`, `templates/template-listora-full-width.php` |
| Modals | Claim, Share, Login (rendered inside listing-detail render.php via IAPI) |
| Detail tabs | Overview, Reviews, Hours, Services, Location |
| Customer URLs | `/listing/<slug>/` (single listing detail) |
| Pro extensions | `lead_form` sidebar, `multi_criteria_reviews` on Reviews tab, `photo_reviews` on Reviews tab, `badges` on header, `analytics` track-events on view |

**Audit doc:** `02-listing-detail.md`

---

## G3 — Submission (create / edit a listing)

What a customer does: walk a 6-step wizard to add or edit a listing.

| Surface | File(s) |
|---|---|
| Block: listing-submission | `blocks/listing-submission/{block.json,render.php,style.css}` + `src/blocks/listing-submission/view.js` (+ flatpickr vendored) |
| Wizard chrome | `templates/blocks/listing-submission/{submission,navigation,stepper}.php` |
| Step templates | `step-type.php` · `step-basic.php` · `step-details.php` · `step-media.php` · `step-preview.php` · `step-duplicate-review.php` |
| Customer URLs | `/add-listing/`, `/dashboard/?edit=<id>` |
| Pro extensions | `pricing_plans` step (between Plan + Preview), coupons input on Plan step, `credit_system` cost display, `google_places` autocomplete on Basic step |

**Audit doc:** `03-submission.md`

---

## G4 — Member Account (manage own listings + activity)

What a customer does: log in, manage their listings + reviews + favourites + claims + credits + profile.

| Surface | File(s) |
|---|---|
| Block: user-dashboard | `blocks/user-dashboard/{block.json,render.php,style.css}` |
| Dashboard chrome | `templates/blocks/user-dashboard/nav.php` |
| Tab templates | `tab-listings.php` · `tab-reviews.php` · `tab-claims.php` · `tab-credits.php` · `tab-profile.php` |
| Customer URLs | `/dashboard/`, `/dashboard/#listings`, `/dashboard/#reviews`, etc. |
| Pro extensions | Adds tabs: `My Needs`, `Analytics`, `Saved Searches`. Adds Credits-tab purchase UI, BuddyPress profile widget |

**Tabs missing dedicated templates (rendered inline in render.php):** Favorites, Settings/Notifications

**Audit doc:** `04-member-account.md`

---

## G5 — Reviews (write + read reviews)

What a customer does: write a review on a listing, vote helpful, owner replies inline.

| Surface | File(s) |
|---|---|
| Block: listing-reviews | `blocks/listing-reviews/{block.json,render.php,style.css}` |
| Templates: reviews | `templates/blocks/listing-reviews/{reviews,review-card,review-form}.php` |
| Reviews tab on detail | `templates/blocks/listing-detail/tabs.php` (lines 332-345 review-author block) |
| Customer URLs | Embedded on `/listing/<slug>/#reviews` + standalone via the `listora/listing-reviews` block on any page |
| Pro extensions | `multi_criteria_reviews` (per-criterion stars), `photo_reviews` (image upload + lightbox) |

**Note:** Reviews UI exists in TWO places — the dedicated listing-reviews block AND the Reviews tab on listing-detail. Audit must check consistency across both.

**Audit doc:** `05-reviews.md`

---

## G6 — Pro Extensions (comparison, credits, needs, moderator)

What a customer does: compare listings side-by-side, buy/use credits, post a need, fulfil a need.

| Surface | File(s) |
|---|---|
| Pro block: comparison | `wb-listora-pro/blocks/comparison/{block.json,render.php,style.css}` |
| Pro block: credit-purchase | `wb-listora-pro/blocks/credit-purchase/{block.json,render.php,style.css}` + template `templates/blocks/credit-purchase/credit-purchase.php` |
| Pro block: needs-grid | `wb-listora-pro/blocks/needs-grid/{block.json,render.php,style.css}` + `templates/blocks/needs-grid/{needs-grid,need-card}.php` |
| Pro block: post-need | `wb-listora-pro/blocks/post-need/{block.json,render.php,style.css}` + template `templates/blocks/post-need/post-need.php` |
| Pro block: moderator-queue | `wb-listora-pro/blocks/moderator-queue/{block.json,render.php,style.css}` |
| Pro detail template | `wb-listora-pro/templates/blocks/need-detail/need-detail.php` |
| Floating bar | Comparison floating bar (renders on every page with localStorage selection) |
| Customer URLs | `/compare-listings/`, `/post-need/`, `/browse-needs/`, `/need/<slug>/` |

**Audit doc:** `06-pro-extensions.md`

---

## G7 — Foundation (the shared layer everything inherits from)

The base layer. Tokens + page shells + card/empty primitives + editor controls. Inconsistencies here cascade to every other group.

| Surface | File(s) | Lines |
|---|---|---|
| Token system + page shells + primitives | `assets/css/shared.css` | **1,153** |
| RTL twin | `assets/css/shared-rtl.css` | 1,033 |
| Editor base CSS | `src/shared/base.css` | (small) |
| Theme isolation | `src/shared/theme-isolation.css` | (small) |
| Editor components (7) | `src/shared/components/{ResponsiveControl,SpacingControl,TypographyControl,BoxShadowControl,BorderRadiusControl,ColorHoverControl,DeviceVisibility}.js` |
| Editor hooks (2) | `src/shared/hooks/{useUniqueId,useResponsiveValue}.js` |
| Editor utils (2) | `src/shared/utils/{attributes,css}.js` |
| PHP CSS class | `includes/class-block-css.php` |
| Icons | `includes/core/class-lucide-icons.php` (21 icons) |

**Page shell vocabulary (introduced 2026-05-08, partly applied across blocks):**
- `.listora-page--single` (listing detail) — 1200px
- `.listora-page--list` (directory) — 1400px
- `.listora-page--dashboard` (member account) — 1280px (REVERTED on user-dashboard due to flex/grid conflict)
- `.listora-page--booking` (submission wizard) — 720px

**Card primitives:** `.listora-ui-card__head/body/foot`, `.listora-card--empty`, `.listora-empty__icon/__title/__desc/__actions`, badge variants `--success/--warning/--danger/--info/--neutral`

**Numeric tokens:** `--listora-space-1..12`, `--listora-font-size-xs..4xl`

**Audit doc:** `07-foundation.md`

---

## G8 — Emails (transactional + lifecycle)

Separate visual system — HTML email rules differ from web HTML.

| Surface | File(s) |
|---|---|
| Shared parts | `templates/emails/parts/{header,footer}.php` |
| Listing lifecycle (8) | `listing-submitted` · `listing-pending-admin` · `listing-approved` · `listing-rejected` · `listing-expiring-soon` · `listing-expired` · `listing-renewed` · `draft-reminder` · `listing-verify-email` |
| Claims (3) | `claim-submitted` · `claim-approved` · `claim-rejected` |
| Reviews (3) | `review-received` · `review-reply` · `review-helpful` |

**Audit doc:** `08-emails.md` (lower priority — visual rules are inline-style-driven and divergent from web layer)

---

## Audit method (applied per group)

For each group's audit doc:

1. **Inventory** — confirm every file listed.
2. **HTML structure** — class naming consistency (BEM `.listora-{block}__{element}--{modifier}`), semantic tags, ARIA, alignment with canonical page shell + card primitives.
3. **CSS structure** — token usage (no hex literals), responsive system (3 breakpoints), per-instance scoping (uniqueId), theme-isolation rules, RTL parity.
4. **Live view** — desktop 1280px + mobile 390px on the actual rendered surface. Capture computed-style assertions + screenshot.
5. **Gap list** — every divergence from the standard OR cross-block inconsistency, with severity (block / advisory / future).
6. **Recommended action** — single fix recommendation per gap.

---

## Audit order

1. **G7 Foundation** first (everything else depends on it).
2. **G1 Discovery** (highest customer-traffic surface).
3. **G2 Listing Detail** (most complex template — modals + tabs + sidebar).
4. **G3 Submission** (multi-step UX, most-fragile).
5. **G4 Member Account** (post-login, owner workflows).
6. **G5 Reviews** (smaller surface, mostly inside G2/G4).
7. **G6 Pro Extensions** (depends on Free being clean first).
8. **G8 Emails** — lowest priority, separate visual rules.
