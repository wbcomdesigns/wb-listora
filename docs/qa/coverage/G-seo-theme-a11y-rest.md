# Coverage: SEO, theme, a11y, REST contracts, block safety (area G)

Surfaces: F = frontend, A = admin, R = REST, E = email, B = block, S = shortcode, C = CLI. Journeys are under `docs/qa/journeys/` (Free) or the Pro repo (`pro:`). All steps marked "Not verified" in a journey are open verification work.

| behaviour | owner journey | surfaces | roles | 1440/390 | light/dark | gaps |
|---|---|---|---|---|---|---|
| Public listing reads stop at the per-IP limit, members exempt | system/listing-api-read-rate-limit.md | R | guest, owner, other, admin | n/a | n/a | step 3 read-back and contact-form control not run live |
| Block numeric attributes never 500; also: `columns=0` never fatals | system/block-attribute-trust-boundary.md | R, B | admin, guest, contributor | 1440 | light | Pro blocks enumerated but not driven; saved-content steps not live; block-renderer 400, negatives n.v. |
| Block string/CSS attributes cannot inject | system/block-attribute-trust-boundary.md | R, B | admin, guest, contributor | 1440 | light | steps 4, 6, 7 not live |
| Block stylesheets versioned | system/block-styles-versioned.md | F | guest, owner, admin | n/a | n/a | needs two release builds for cache bump |
| Space showcase approves only published listings; total counts rendered | system/space-listings-rest.md | R | space-team, owner, other, guest, admin | n/a | n/a | 403 rows and scale paging not live; no BuddyNext |
| Space removal reports reject / takedown / withdraw | system/space-listings-rest.md | R | space-team, owner, other, guest, admin | n/a | n/a | steps 4-7 read from source only |
| Listing head: schema, OG, twitter, canonical, breadcrumbs, sitemap toggles; also: REST schema field follows the Schema toggle | system/listing-seo-head-output.md | F | guest, admin | 1440/390 | light/dark | breadcrumb at 390 and dark not screenshotted; sibling routes |
| SEO plugin ownership (Yoast, Rank Math, filter-only) | system/listing-seo-head-output.md | F | guest, admin | 1440 | light | AIOSEO/SEOPress only via filter; locale switch not run |
| Schema generator survives malformed meta | system/listing-schema-content.md | F, C | system, guest | 1440 | light | empty PostalAddress for garbage address: expected-fail |
| JobPosting JSON-LD completeness | system/listing-schema-content.md | F | guest | 1440 | light | no published job on reference site; step 7 not live |
| Listing REST envelope, pagination, timestamps | system/listing-rest-contract.md | R | guest, owner, admin | n/a | n/a | scale seed and timezone sub-step not live |
| REST strings decoded (title, alt, term names, dropdown) | system/listing-rest-contract.md | R, F | guest, owner | 390 dropdown | n/a | /search excerpt not decoded: expected-fail |
| Related, bulk, renewal-quote, services reorder routes | system/listing-rest-contract.md | R | guest, owner, other, admin | n/a | n/a | app-config secrecy owned by regression/app-config-contract-shape.md (cross-area) |
| Submit REST refused when Submission feature is off | system/submission-rest-feature-gate.md | R, F | admin, owner, editor, guest | 1440/390 | light/dark | steps 4, 5, 7 not live |
| Trigger labels translate (no early __()) | system/trigger-labels-translate.md | C, A | guest, admin | 1440 | n/a | steps 3-5 not run |
| Site CSS reaches Listora; themes stay out; theme colour; opt-out | system/site-css-and-layer-overrides.md | F | admin, guest, owner | 1440/390 | light/dark | three-theme run and token parity not live |
| URL-shaped title refused | customer/title-is-not-a-url.md | R, F | owner, other, guest, admin | 1440/390 | light/dark | steps 3-6 not run |
| Editing keeps categories the form cannot show | customer/submission-preserves-multi-category.md | R, F | owner, other, guest, admin | 1440/390 | light/dark | steps 5-8 not run |
| Featured-image upload operable by keyboard | customer/submission-upload-keyboard-access.md | F | owner, guest, admin | 1440/390 | light/dark | edit-mode markup and retry not run |
| Stepper fits phones | customer/submission-stepper-fits-phones.md | F | owner, guest, admin | 390/360 | light/dark | steps 2-6 not run |
| Listing modals trap focus | customer/modal-traps-focus.md | F | guest, other, owner, admin | 1440/390 | light/dark | Report-review modal expected-fail; 390/dark not run |
| --sm buttons >= 40px, row height parity | customer/sm-button-tap-target.md | F | guest, owner, admin | 390/820/1440 | light/dark | measurements not run in a browser |
| Native pickers follow colour mode | customer/native-controls-color-scheme.md | F | owner, guest, admin | 1440/390 | light/dark | open native popup manual only |
| Brand text meets AA contrast | customer/brand-text-meets-contrast.md | F | guest, owner | 1440/390 | light/dark | values computed, not measured composited |
| Back-to-top clears the action bar | customer/back-to-top-clears-action-bar.md | F | guest, other | 390/820/1440 | light/dark | geometry not measured |
| Share copies link, no scroll lock | customer/share-copies-link-no-scroll-lock.md | F | guest, other, admin | 1440/390 | light/dark | never run in a browser |
| No-permission member gets a reason | customer/add-listing-no-permission-message.md | F, R | no-cap-member, owner, guest, admin | 1440/390 | light/dark | 390 and dark not verified in a browser |
| Categories block: tiles, counts, hide-empty, View all | customer/09-categories-block.md | F, B | guest, other, owner, admin | 1440/390 | light/dark | block attribute boundary (step 8) not run |
| Activating/deactivating Pro leaves no blank page, offers pages once | admin/pro-upgrade-and-downgrade.md | A, F | admin, editor, guest | 1440/390 | light/dark | steps 1, 2, 5, 8 not run |
| Hidden directory unreadable by any route spelling | pro:system/visibility-gate-matched-route.md | R, F | guest, other, editor, admin | 1440/390 | light/dark | batch sub-request and scale rows not live |
| Coming Soon splash follows dark preference | pro:customer/coming-soon-dark-mode.md | F | guest, admin | 1440/390 | light/dark | computed colours not re-run |
| Programmatic SEO pages render, route, defer, keep unrated; also: Programmatic SEO pages + sitemap provider | pro:customer/14-seo-pages-render.md | F, A | guest, other, admin | 1440/390 | light/dark | double title and paged=99 expected-fail; Yoast run, singular/under-minimum on this site not live; past-end clamp (expected-fail); single-listing unverified combo not live |
| Comparison labels wrap, sticky, phone columns | pro:customer/comparison-label-wrap.md | F | guest, owner, editor, admin | 1440/820/390 | light/dark | step 6 and count boundaries not run |
| Front-end moderator queue block | pro:customer/moderator-queue-block.md | F, B | moderator, other, guest, editor, admin | 1440/390 | light/dark | >perPage paging is an open owner decision |
| Pro secondary REST routes auth/shape/gate | pro:system/pro-rest-completeness.md | R | admin, editor, owner, other, guest | n/a | n/a | in-flight import cancel, feature-OFF 404, boundary and rate-limit steps not run |
| One recorded view per window when Pro owns analytics | pro:system/analytics-no-double-count.md | R, F | guest, owner, other, admin | n/a | n/a | toggle OFF, parallel burst, timezone not live |
| No wp-element-button on customer surfaces | pro:customer/no-wp-element-button-customer-facing.md | F | guest, other, owner, admin | 1440/390 | light/dark | Free contact form Send button expected-fail; 390/dark not run |
