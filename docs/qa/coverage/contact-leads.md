# Coverage: contact-leads

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Blocked member cannot contact owner (both routes) | ora:contact-leads/blocked-member-cannot-contact-owner.md | REST, app-config | member, owner, guest, admin | n/a | n/a | UI hide for blocked pair |
| Lead counted in analytics exactly once | ora:contact-leads/lead-counted-without-analytics.md | REST, DB, email | guest, member, owner | partial | partial | Free-only, timezone |
| Leads admin: retention, delete, privacy export/erase, follow owner | ora:contact-leads/leads-admin-retention-privacy-ownership.md | admin, REST, WP privacy tools | admin, editor, owner, other, guest | partial | n/a | paged scale seed |
| Contact form stores a lead, emails owner, owner reads it; honeypot, nonce, spam, rate limits | ora:contact-leads/listing-contact-form.md | frontend, REST, email, dashboard | guest, member, owner, editor, admin | partial | partial | expected-fail: empty message accepted over REST; Mailpit; signed-in prefill |
| Pro deprecated /contact alias and lead hook bridges | pro:contact-leads/lead-form-deprecated-route.md | REST, hooks | guest, member, owner, admin | n/a | n/a | feature-off row |
