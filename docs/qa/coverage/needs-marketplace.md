# Coverage: needs-marketplace

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| My Needs 20/page, badge counts all, controls parity | pro:needs-marketplace/my-needs-pages-20-and-badge-counts-all.md | F, A (Needs list) | owner, other, editor, admin, guest | both | not run | parity and 500-need steps unrun |
| Need matcher uses geo_query service | pro:needs-marketplace/need-matcher-geo-query-service.md | PHP service/REST | admin, owner, other, guest | n/a | n/a | none known |
| Need close/delete guards on live response count | pro:needs-marketplace/need-owner-close-and-delete-guards.md | frontend, REST, admin, hooks | author, other, guest, editor, admin | 1440/390 | yes | modal steps and large-data not run |
| /needs/ is the marketplace (Pro) | pro:needs-marketplace/needs-archive-is-the-marketplace.md | F, B | guest, owner, admin | both (390 n.v.) | n.v. | theme override, SEO canonical n.v. |
| Needs budget unit, card author, quote positivity | pro:needs-marketplace/needs-budget-unit-card-author.md | frontend, REST, admin | owner, other, guest, moderator, admin | both | both | lapsed-deadline edit, big site not live |
| Needs marketplace: browse, CTAs by registry, moderate, deadline, expire, My Responses, feature switch | pro:needs-marketplace/needs-marketplace-browse-moderate-expire.md | frontend, admin, REST, cron | guest, member, vendor, moderator, admin | 1440/820/390 | yes | feature switch not exercised on shared site; past-the-end pager step not run |
| Needs single URL and upgrade migration | pro:needs-marketplace/needs-one-url-upgrade-migration.md | frontend, upgrade | admin, member, guest | 1440/390 | yes | steps 1-7 need staging |
| Post a need, quote in site currency, accept; budget display | pro:needs-marketplace/needs-post-quote-accept.md | frontend, REST, admin table, email | buyer, vendor, other, guest, editor, admin | 1440/390 | yes | 25-need paging not run; currency switch not run |
| Needs moderation: approve, reject with note, close, double action | pro:needs-marketplace/reverse-listings-moderation.md | admin, email, REST | admin, owner, other, editor, guest | 1440+390 | light/dark | 2,000+ needs and parallel unrun |
| Pay-per-lead charges once (Pro) | pro:needs-marketplace/pay-per-lead-charge-once.md | frontend, REST, credits ledger | vendor, author, member, guest, editor, admin | partial | partial | zero-decimal currency |
