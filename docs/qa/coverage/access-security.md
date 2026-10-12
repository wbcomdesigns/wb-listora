# Coverage: access-security

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Block numeric attributes never 500; also: `columns=0` never fatals | ora:access-security/block-attribute-trust-boundary.md | R, B | admin, guest, contributor | 1440 | light | Pro blocks enumerated but not driven; saved-content steps not live; block-renderer 400, negatives n.v. |
| Block string/CSS attributes cannot inject | ora:access-security/block-attribute-trust-boundary.md | R, B | admin, guest, contributor | 1440 | light | steps 4, 6, 7 not live |
| Public listing reads stop at the per-IP limit, members exempt | ora:access-security/listing-api-read-rate-limit.md | R | guest, owner, other, admin | n/a | n/a | step 3 read-back and contact-form control not run live |
| Suspended or role-stripped account write gate | ora:access-security/member-write-gate.md | A (Users), R | admin, moderator, owner, other, role-stripped, guest | n/a | n/a | bulk/parallel unrun |
| Moderation/Settings screen capability map | ora:access-security/moderation-and-settings-screen-capabilities.md | admin | admin, editor, subscriber, guest | n/a | n/a | Pro moderator role |
| Rate limit: members by account, guests by IP | ora:access-security/rate-limit-counts-members-by-account.md | R, CLI | owner, other, guest, admin | n/a | n/a | HTTP 429 unprovoked |
| Contact-form REST nonce gate for authenticated apps | ora:access-security/rest-nonce-gate-authenticated-app-writes.md | R | guest, subscriber, admin | - | - | boundary, parallel |
| Role capability matrix | ora:access-security/role-cap-matrix.md | A, R, F, C | all six rungs | 1440 | light | deleted-author, stripped-role case |
| Spam protection matrix + captcha + Retry-After | ora:access-security/spam-protection-layers.md | REST | guest, member, admin | n/a | n/a | expected-fail: reviews/claims skip Anti_Spam; Akismet |
| App/native writes: contact form + click tracking nonce rules, 429 | pro:access-security/app-nonce-exempt-pro-writes.md | REST | guest, owner, other, admin | - | - | boundary inputs; retry |
| Coming Soon splash follows dark preference | pro:access-security/coming-soon-dark-mode.md | F | guest, admin | 1440/390 | light/dark | computed colours not re-run |
| Coming Soon / Private visibility gate; also: Private / Coming Soon visibility modes persist and gate; also: Private/Coming Soon gate lets logged-in members in | pro:access-security/coming-soon-mode.md | frontend, REST, sitemap, oEmbed, BuddyPress | guest, member, author, editor, admin | both | both | upgrade pin (disposable site), author/contributor rows not live; Whole-site at 390/dark; sitemap/feed; no-role user row; overlaps other visibility journeys |
| Token links work while the directory is hidden (Pro) | pro:access-security/hidden-directory-token-links-work.md | REST, front-end links | guest, member, editor, admin | n/a | n/a | replays, signed webhook |
| Hidden directory unreadable by any route spelling | pro:access-security/visibility-gate-matched-route.md | R, F | guest, other, editor, admin | 1440/390 | light/dark | batch sub-request and scale rows not live |
| Visibility tab always present; Features save keeps hidden keys | pro:access-security/visibility-tab-always-present.md | A | admin, editor, other, guest | both | not run | form-driven save unrun |
