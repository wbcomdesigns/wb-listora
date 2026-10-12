# Journey coverage index

Journeys are grouped by functionality. Each group has a folder `docs/qa/journeys/<group>/` in the repo that owns the code (Free, Pro or both), a manifest `_group.md` an agent starts from, and one coverage table here.

Rules:

1. One row per behaviour, exactly one owner journey. Extend the owner row; never add a second row for the same behaviour.
2. The owner column holds an existing journey path: `ora:` is wb-listora, `pro:` is wb-listora-pro. Deleting, merging or renaming a journey updates its rows in the same change.
3. Every journey owns at least one row in the table of its own group.

| Group | Scope | Journeys | Behaviours |
|---|---|---|---|
| [access-security](access-security.md) | Visibility modes, roles and capabilities, rate limits, spam, nonces, block safety | 14 | 15 |
| [admin-listings](admin-listings.md) | wp-admin listings table, editor, moderation, bulk actions, admin shell and notices | 17 | 17 |
| [analytics-audit](analytics-audit.md) | Analytics counts and dashboard, audit log | 6 | 6 |
| [browse-blocks](browse-blocks.md) | Cards, grids, blocks, quick view, load more, compare, favourites | 14 | 14 |
| [claims-verification](claims-verification.md) | Claim a business, approve, badges and verification | 8 | 8 |
| [contact-leads](contact-leads.md) | Contact form, leads inbox, retention, pay per lead | 5 | 5 |
| [credits-checkout](credits-checkout.md) | Credits, packs, checkout, receipts, coupons, gateways, refunds | 21 | 21 |
| [dashboard-account](dashboard-account.md) | Member dashboard tabs, profile, account, login modal | 14 | 14 |
| [directory-search](directory-search.md) | Search bar, filters, paging, empty states, term pages, featured | 14 | 20 |
| [emails-alerts](emails-alerts.md) | Email templates, log, status emails, digests, saved-search alerts | 9 | 9 |
| [import-migration](import-migration.md) | CSV import and export, demo import, competitor migration, Google Places | 11 | 11 |
| [integrations-api](integrations-api.md) | REST contract, app config, outgoing webhooks, BuddyPress, spaces | 16 | 19 |
| [listing-detail](listing-detail.md) | Single listing page: header, fields, hours, share, flag, owner | 10 | 10 |
| [map](map.md) | Map render, providers, near me, picker data, location terms | 8 | 8 |
| [needs-marketplace](needs-marketplace.md) | Pro needs (reverse listings): post, quote, browse, moderate | 10 | 10 |
| [plans-expiry-renewal](plans-expiry-renewal.md) | Pricing plans, plan step, expiry, renewal, monetization status | 11 | 11 |
| [platform-lifecycle](platform-lifecycle.md) | Install, upgrade, uninstall, cron, CLI, health check, translations | 13 | 15 |
| [reviews](reviews.md) | Write, reply, moderate, photos, criteria, flag a review | 13 | 15 |
| [seo-schema](seo-schema.md) | SEO head, schema, breadcrumbs, SEO pages | 4 | 6 |
| [services](services.md) | Services on a listing: admin box, dashboard CRUD, public display | 5 | 5 |
| [settings-setup](settings-setup.md) | Setup wizard, settings tabs, pages, listing types, taxonomies, feature toggles, licence, white label | 20 | 20 |
| [submission](submission.md) | Add-listing wizard, drafts, fields, media, terms, guest submission, frontend edit | 23 | 23 |
| [theme-presentation](theme-presentation.md) | Cross-cutting look: CSS layers, dark mode, contrast, tap targets, overlays, RTL | 11 | 10 |
| **Total** | | **277** | **292** |
