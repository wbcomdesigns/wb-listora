---
group: theme-presentation
plugin: wb-listora
scope: Cross-cutting look and behaviour of Listora styles on any theme - CSS layers, dark mode, contrast, tap targets, overlays, RTL twins, native controls
journeys: 9
site_profile: required
---
# Theme presentation: what an agent checks here

## Scope
Covers how Listora CSS is delivered and how it looks across themes, colour modes, widths and RTL: linked layered sheets, `@layer listora` / `listora-overrides`, theme isolation and opt-out, brand colour and AA text contrast, `color-scheme` on native controls, 40px tap targets, the overlay inventory (open, fit, close, focus trap), RTL twin files, versioned block styles.
Does NOT cover: the content or flow inside an overlay (listing-detail owns Claim/Report/Login behaviour, reviews owns report-a-review, dashboard-account owns Renew/Services, browse-blocks owns quick view and the Pro buttons); Pro-owned button and empty-state rules (Pro `_group.md`); map tiles (map); wp-admin screen layout beyond the overlays and the admin 34px floor (admin-listings, settings-setup).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Plain page head (stylesheet delivery) | `$SITE_URL/{plain-page}/` | guest | `includes/core/class-theme-defenses.php`, `includes/class-assets.php` |
| 2 | Directory | `$SITE_URL/{directory}/` (also `?listora_page={n}`) | guest, owner | `blocks/listing-search`, `blocks/listing-grid`, `templates/blocks/listing-grid/pagination.php` |
| 3 | Home screen with Listora blocks | `$SITE_URL/` | guest | block render files under `blocks/*` |
| 4 | Listing detail | `{listing}` URL | guest, other, owner | `blocks/listing-detail/render.php`, `templates/blocks/listing-detail/gallery.php`, `templates/blocks/reviews/report-modal.php` |
| 5 | Add Listing, Details step (date/time inputs) | `{submit-page}` as {owner}, type {type} with date fields | owner | `blocks/listing-submission/*` |
| 6 | Member dashboard, Listings tab | `{dashboard}?tab=listings` | owner | `templates/blocks/user-dashboard/tab-listings.php` |
| 7 | Any Listora page under an RTL locale | surface 2 with site or user locale `ar` | guest | `includes/class-plugin.php` (`mark_styles_rtl`) |
| 8 | Block editor | `wp-admin/post.php?post={page}&action=edit` | admin | `blocks/*/style.css`, `editor.css` |
| 9 | Customizer Additional CSS | `wp-admin/customize.php` | admin | WordPress core, `Theme_Defenses::layer_custom_css()` |
| 10 | wp-admin overlays (detail drawers, email preview, Remove credits, promo modal) | Claims, Reviews, Email Log, Listing Types, Settings > Notifications, setup wizard `step=maps&rerun=1` | admin | `includes/admin/class-admin-table.php`, `class-settings-page.php`, `class-pro-promotion.php` |
| 11 | Theme colour-mode toggle | theme header toggle (`localStorage['bx-color-mode']`) | all | theme (BuddyX / BuddyX Pro) |

## Components on each surface
1. Plain page head: `<link>` per Listora sheet (`listora-variables`, `listora-theme-bridge`, `listora-components`, `listora-isolation` unlayered, `listora-base`, block sheets) ending `.layer.css`; no inline `<style>` with `@import`; Additional CSS printed twice (plain + `@layer listora-overrides`).
2. Directory: `.listora-block` root, search bar (`.listora-search__input`, Search button), type pills, filter chips and `.listora-btn--sm` buttons, `.listora-card` grid (`.listora-card__title`, type badge, secondary buttons), `.listora-grid__page-numbers` with `.listora-grid__page-num`, Load more `.listora-loadmore__btn` (Pro).
3. Home: hero search, type chips and arrows, category tiles, grids - read tokens `--listora-primary`, `--listora-surface`, `--listora-border`, `--listora-text`.
4. Listing detail: header badges, `.listora-detail__actions` row (Edit, Share, Save, Claim, Report as `a.listora-btn` and `button.listora-btn`), fixed Call/Visit/Save bar, gallery `dialog.listora-detail__lightbox` (prev, next, "n / N" counter), `#listora-claim-modal`, `#listora-report-modal`, `#listora-login-modal`, `#listora-report-review-modal` containing `#listora-report-review-dialog` (opened by `button.listora-detail__review-report-btn` on the Reviews tab), `.listora-detail__modal.is-open [role=dialog]`, Contact form Send button.
5. Add Listing Details step: `input[type=date]`, `input[type=datetime-local]`, `input[type=time]`, `.listora-form-field`, its label and `.listora-form-field__control`.
6. Dashboard Listings: row actions (Edit, View, More), `.listora-dashboard__services-panel` (+ Add Service form), `[data-listora-renew-modal]`, `.listora-confirm-overlay` (`.listora-confirm`, `.listora-confirm__title`, Cancel/Confirm), `.listora-dashboard__nav-item` with count badge, toasts `.listora-toast-container > .listora-toast`.
7. RTL page: `html[dir=rtl]`, `*-rtl.layer.css` twins, `listora-isolation-rtl.css`, bridge `themes/buddyx-pro-rtl.layer.css`.
8. Editor: block `style.css` (versioned), editor preview of the same blocks.
9. Additional CSS: textarea, publish button; effect on `.listora-card`, `.listora-card__title`, `.listora-btn--primary`.
10. wp-admin overlays: `[data-listora-drawer]` `<dialog>`, `[data-listora-email-preview]` dialog, `[data-listora-refund-open]` modal (Pro), `#listora-promo-modal`, `.listora-confirm-overlay`; admin `.listora-btn` 34px floor.
11. Toggle: light / dark / auto; `<html data-bx-mode>`; `body.dark-scheme` / `body.dark-mode` on other themes.

## States to force
| Surface | State | How |
|---|---|---|
| 1-6 | light, dark, auto+OS dark | theme toggle; `page.emulateMedia({colorScheme:'dark'})` |
| 1-6 | primary theme, unbridged theme, Astra | preview theme per browser or scratch copy, never switch a shared site |
| 1 | isolation off | mu-plugin `add_filter('wb_listora_theme_isolation','__return_false')` |
| 1 | on-demand block assets off | mu-plugin returning false from `should_load_block_assets_on_demand`, `should_load_separate_core_block_assets` |
| 4 | guest, other, owner of the listing | log in as each; owner sees no Claim/Report |
| 4 | 3+ photos / 1 photo / none | pick {listing} accordingly |
| 6 | expired listing with renewal on | fixtures; a listing past expiry |
| 7 | RTL | install `ar` language pack, set locale |
| 8-9 | dark accent | set theme accent to `#1d3557` |
| all | 390, 820, 1280, 1440 | browser resize |
| 10 | Pro inactive (promo modal) | Free-only install |

## Settings that change this group
| Setting | Values | What must visibly change, where |
|---|---|---|
| filter `wb_listora_theme_isolation` | true (default) / false | false: unlayered `.css` served, no `listora-isolation*.css`, no `.layer.css`, no `@layer listora-overrides` copy (surface 1) |
| Appearance > Additional CSS | any Listora class rule | rule wins on Listora classes, printed twice (surface 2, 9) |
| Theme accent colour | default / dark `#1d3557` | `--listora-primary` follows, text derived via `--listora-primary-text` stays >= 4.5:1 (surfaces 2-4) |
| Theme colour mode | light / dark / auto | `color-scheme`, palette and bridge flip together (all) |
| Site / user locale | LTR / `ar` | RTL twins load, no 404 (surface 7) |
| Settings > Features (Pro on) | Needs, Services, Monetization | which overlays exist (surfaces 6, 10) |
| `--listora-button-bg` override | unset / `var(--listora-primary-text)` | primary button fill and AA white-on-fill (opt-in escape hatch) |

## Fixtures and setup
- `docs/qa/fixtures/2.0-fixtures.php seed` (personas {owner} {other} {editor} {admin}); `clean` afterwards.
- Back up Additional CSS first: `wp eval 'echo wp_get_custom_css();' > <scratch>/css-backup.css`; restore with `wp_update_custom_css_post`.
- Listing with 3+ photos, an unclaimed listing with an approved review, an expired listing, an Event-type listing type with date fields, a QA-prefixed seed larger than one Directory page.
- Language pack `ar`: `wp language core install ar`; set back to the default locale.
- Restore: Additional CSS, mu-plugins, renamed twin files, locale, theme colour mode, autosaved draft listings.

## Run order
1. `styles-linked-not-inline-imported` - mixed, high: no inline `@import`, build guard, lint.
2. `site-css-and-layer-overrides` - mixed, critical: Additional CSS reaches Listora, isolation, opt-out, brand colour, token parity.
3. `brand-text-meets-contrast` - browser, high: brand text >= 4.5:1.
4. `native-controls-color-scheme` - browser, high: `color-scheme` follows page mode.
5. `sm-button-tap-target` - browser, high: `--sm` >= 40px, row height parity.
6. `modal-traps-focus` - browser, high: Tab cannot leave a modal.
7. `overlays-usable-at-every-width` - browser, critical: 15-row overlay inventory at 1280 and 390.
8. `rtl-twin-only-claimed-when-it-exists` - mixed, normal: RTL sheets 200.
9. `block-styles-versioned` - mixed, high: block style `?ver=` equals plugin version.
Then run: wb-listora-pro/docs/qa/journeys/theme-presentation/_group.md

## Group-specific micro checks
- G-01 On the Directory, `getComputedStyle(document.documentElement).colorScheme` is `light` or `dark`, never `normal`, in every colour mode.
- G-02 With theme in light and OS dark emulated, no `.listora-card` background differs from the light value (no bare `prefers-color-scheme: dark`).
- G-03 Count of `<link>` hrefs under `/wb-listora/` not ending `.layer.css` is exactly 1 (`listora-isolation.css`) with isolation on; 0 `.layer.css` with it off.
- G-04 Every `.listora-btn` in one action row (`.listora-detail__actions`, dashboard row actions) has the same `getBoundingClientRect().height`, `fontWeight` and `lineHeight` for `<a>` and `<button>`.
- G-05 Every `.listora-btn--sm` outside wp-admin is >= 40px tall at 390; inside `.wb-listora-admin` it is 34px.
- G-06 Default-accent secondary button text, type badge, active tab and dashboard count badge measure >= 4.5:1 against their composited tint, not against white.
- G-07 Primary button white text on the true brand measures 3.87:1 with the default accent and is NOT a defect (owner decision); do not report it, report only if the escape hatch rule fails to raise it.
- G-08 `--listora-fg-muted` computed value on `.listora-block` is Listora's own, not the theme muted token.
- G-09 The three date/time inputs on the Details step each report the same `colorScheme` as the root.
- G-10 Page numbers in `.listora-grid__page-numbers`: neighbouring rects do not overlap at 390 and each is >= 40 x 40; the active page number is >= 4.5:1.
- G-11 With a modal open, `document.body.style.overflow` is `hidden`; after Escape it is `''` and focus is on the trigger (a programmatic `.click()` hides this; use real key presses).
- G-12 For each overlay, `document.elementFromPoint` at the centre of the close button and of the primary action returns that element, with the WP admin bar present, at 1280 and 390.
- G-13 The first `.listora-modal` / `.listora-confirm-overlay` z-index is above the admin bar (99999) when opened by a logged-in user; `src/components/modal.css` sets 9999, so verify the overlay is not under the bar.
- G-14 Under `ar`, `getComputedStyle(first .listora-card__title).direction === 'rtl'` and no `margin-left`/`right` physical leak: the card icon sits on the inline-start side.
- G-15 Every `.layer.css` request under RTL has an `-rtl.layer.css` URL and status 200; no HEAD returns 404 for any `wb-listora*` stylesheet.
- G-16 Block style URLs on a page end `?ver=<WB_LISTORA_VERSION>` (Pro blocks the Pro version) and equal the constants in the main plugin file.
- G-17 Fixed Call / Visit / Save bar on a listing at 390 does not cover the last page element or the theme back-to-top button after scrolling to the end.

## Known defects (expected-fail)
- modal-traps-focus step 5: Report-review modal has no keydown trap (`showReportModal`, `src/interactivity/store.js`), focus leaves after 5 Tab presses.

## Not covered yet
- Toast container (`assets/js/shared/toast.js`, `.listora-toast`) at 390, dark, and RTL: no journey measures it.
- `assets/css/shared/pro-cta.css` and `confirm.css` themselves (dark, RTL) beyond the confirm Cancel button height.
- `prefers-reduced-motion` and `forced-colors` handling in `src/components/*.css`: no check.
- Kadence, Blocksy, BuddyBoss, Elementor-kit colour chains are named in site-css step 5 but have no fixture themes.
- Reign bridge (`assets/css/themes/reign.css`) has no dark or contrast journey; only BuddyX Pro / BuddyX are exercised.
- Print and 200% zoom (P-17) of the overlays.
