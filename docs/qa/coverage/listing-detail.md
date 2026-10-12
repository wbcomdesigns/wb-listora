# Coverage: listing-detail

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Listed by owner name and toggle; also: "Listed by" never shows a login | ora:listing-detail/listing-owner-name.md | F R B | guest, owner, admin | Y/Y | n | contact-name path source-only; filter/boundary steps unrun |
| Number fields as money, min/max range, JSON-LD | ora:listing-detail/number-fields-money-and-range.md | F R | guest, admin | Y/Y | n | zero-decimal currency step added unrun |
| Type field visibility: page, API, schema, cards; also: API mirrors the listing page (field visibility, internal keys) | ora:listing-detail/type-field-visibility-page-api-schema.md | A F R | admin, owner, other, guest | Y/Y | n | lifecycle step 8 unrun; api-mirrors-the-page sentinel is a cross-area duplicate; steps 4-6 not live; embedded schema telephone expected-fail |
| Back-to-top clears the action bar | ora:listing-detail/back-to-top-clears-action-bar.md | F | guest, other | 390/820/1440 | light/dark | geometry not measured |
| Share copies link, no scroll lock | ora:listing-detail/share-copies-link-no-scroll-lock.md | F | guest, other, admin | 1440/390 | light/dark | never run in a browser |
| Flag a listing: confirm once, staff mailed (not owner), admin flag, toggle | ora:listing-detail/flag-a-listing.md | frontend, REST, admin column/metabox, email | guest, member, owner, editor, admin | partial | partial | modal click-through, metabox clear |
| Listing detail file field renders as image, never raw id | ora:listing-detail/listing-detail-file-field-renders-as-image.md | F | guest, other, admin | Y | light/dark | logo-on-tab is a live bug (expected-fail); non-image files |
| Listing header: one Verified chip, one address | ora:listing-detail/listing-header-chips-and-address.md | frontend | guest + ladder | n/390 n | both | steps 2, 8, 390 not live |
| Open-now badge equals search filter | ora:listing-detail/open-now-badge-matches-search.md | F, R | guest, owner, admin | Y | light | nothing run live |
| Anonymous login modal always has Create account | ora:listing-detail/anon-login-modal-register-cta.md | F (listing page; misfiled, belongs to listing-detail) | guest, owner, admin | both | not run | click/filter steps unrun |
