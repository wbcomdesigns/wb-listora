# Coverage: submit, edit, dashboard listings, renewal, setup, expiry (area A)

Every behaviour has exactly one owner row. Surfaces: F frontend, A admin, R REST, E email, B block, S shortcode, C CLI. Matrix columns: 1440/390 and light/dark are `Y` when the owner journey asserts them, `n` when only declared (not verified live), `-` not applicable.

| behaviour | owner journey | surfaces | roles | 1440/390 | light/dark | gaps |
|---|---|---|---|---|---|---|
| Wizard walk, required messages, terms gate, success text per moderation, category tree, reload keeps data, social gate, term id refused | free:customer/02-submit-a-listing-wizard-end-to-end | F R | subscriber, contributor, editor, admin, guest, no-cap | Y/Y | n | dark/unknown theme not run; Free-only wizard not walked |
| Optional category, no empty fieldset, fieldset width, upload limit follows server | free:customer/submission-step-rendering-invariants | F A | subscriber, guest, admin, no-cap | Y/Y | n | upload run needs large files; Event schedule 820/390 not measured |
| Save Draft never limited, id carried, terms matrix, Buy Credits keeps draft, off-site return refused | free:customer/submission-save-draft-and-resume | F R | owner, other, guest, admin | Y/Y | n | browser steps and WooCommerce return not run |
| Submission cost / plan charged once, hold-settle-release, concurrency | free:customer/submission-cost-charged-once | F R | owner, other, guest, admin | n/n | n | steps 2-7 carried unrun |
| Field options, price, contact fields render, save, prefill | free:customer/submission-field-values-round-trip | F A R | owner, guest, admin | Y/n | n | boundary price step 13 added unrun; Type Editor UI not run |
| Business hours: multiple ranges, picker, Firefox, theming, schema, preview, Places import | free:customer/business-hours-multiple-ranges | F A R | owner, guest, admin | Y/Y | Y | builder add/remove and Firefox need a browser |
| Owner edit form, edit permission, staff edit, media kept on edit, empty-title | free:customer/17-edit-my-listing | F R | owner, other, editor, admin, guest | n/n | n | 2 expected-fail steps (non-owner blank wizard, empty title saved) |
| Owner opens own unpublished listing, 404 for others, expired notice | free:customer/owner-opens-own-unpublished-listing | F R | owner, other, guest, admin | Y/Y | n | trashed/deleted-author step added unrun |
| Number fields as money, min/max range, JSON-LD | free:customer/number-fields-money-and-range | F R | guest, admin | Y/Y | n | zero-decimal currency step added unrun |
| Owner renews, quote, modal live region, duration rules, failed renewal, expiry-off 409 | free:customer/06-listing-renewal | F R E | owner, other, guest, admin | Y/Y | n | timezone half unrun; renew_buy link expected-fail |
| My Listings badge, rows, statuses, filters, paging, REST agreement | free:customer/dashboard-my-listings-statuses-counts-filters | F R | owner, other, guest, admin | Y/Y | n | needs 2000+ seed; cache staleness unobservable without object cache |
| Services CRUD, categories kept, photos, admin box, flag | free:customer/listing-services-crud-and-photos | F A R | owner, other, guest, editor, admin | Y/Y | n | media frame steps need browser; foreign image on update may clear photo |
| Listed by owner name and toggle; also: "Listed by" never shows a login | free:customer/listing-owner-name | F R B | guest, owner, admin | Y/Y | n | contact-name path source-only; filter/boundary steps unrun |
| Setup wizard run, session, done heading, replay protection | free:admin/04-setup-wizard-first-run | A | admin, editor, guest | Y/Y | n | replay steps unrun (rewrite settings) |
| Essential and showcase pages created, adopted, not resurrected | free:admin/setup-pages-created-adopted-once | A C | admin, editor, guest | Y/- | - | needs pristine DB for steps 1-4; multisite step unrun |
| Expiry off on free directory, dated types, new types, own switch | free:admin/expiry-off-on-free-directory | A F R | admin, owner, other, guest | Y/Y | n | timezone and republish steps unrun |
| Type field visibility: page, API, schema, cards; also: API mirrors the listing page (field visibility, internal keys) | free:admin/type-field-visibility-page-api-schema | A F R | admin, owner, other, guest | Y/Y | n | lifecycle step 8 unrun; api-mirrors-the-page sentinel is a cross-area duplicate; steps 4-6 not live; embedded schema telephone expected-fail |
| wp-admin listing media fields, moderator keeps media, deleted attachments | free:admin/listing-media-fields-and-retention | A F R | admin, moderator, guest | Y/Y | n | interactive steps unrun |
| Reviews and claims bulk Apply / Search submit | free:admin/admin-bulk-actions-submit | A | admin, editor | Y/Y | n | no pending review on QA site |
| Legal rows: draft vs not set | free:admin/privacy-policy-url-draft-state | A R | admin | Y/Y | n | steps 2-5 not toggled |
| Pro setup wizard | pro:admin/09-pro-setup-wizard | A | admin, editor, guest | Y/Y | n | no real license activation by design |
| Monetization on turns expiry on, off keeps dates | pro:admin/monetization-expiry-follows-toggle | A F R | admin, owner, other, guest, editor | Y/Y | n | mobile/dark unrun |
| Plan required to publish; draft cannot skip; also: Plan charged once, to author, at coupon price; no plan-less route to review | pro:customer/plan-required-on-submit | F R | owner, other, guest, editor, admin | Y/Y | n | short-balance pause expected-fail (Free transition guard); wp-admin/Quick Edit/block-editor half partly not run |
| Load More disabled while loading | pro:customer/load-more-disabled-while-loading | F | guest, member, admin | Y/Y | n | count boundary step unrun |
| Monetization status one answer; Direct pack is a pack | pro:admin/monetization-status-is-one-answer | A F R | admin, owner, guest, moderator | Y/Y | n | needs gateway keys and WooCommerce dummy |
| Plan auto-renew from credits or pause | pro:system/plan-auto-renewal | R C | system, member, admin | -/- | - | pause step expected-fail (publish -> listora_payment refused) |
