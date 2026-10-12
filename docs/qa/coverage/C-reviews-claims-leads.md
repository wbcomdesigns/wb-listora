# Coverage: reviews, claims, leads, reporting (area C)

One owner row per behaviour. Paths relative to `docs/qa/journeys/` of the owning repo (`ora:` = wb-listora, `pro:` = wb-listora-pro). Viewports/themes: `1440/390` and `light/dark` are marked `yes`, `partial` (asserted but Not verified live) or `n/a` (REST/CLI).

| behaviour | owner journey | surfaces | roles | 1440/390 | light/dark | gaps |
|---|---|---|---|---|---|---|
| Member writes a review, moderator approves, owner replies, edits return to moderation | ora:customer/03-write-and-reply-to-a-review.md | frontend, dashboard, REST, admin | member, owner, other, guest, editor, admin | yes | partial | rate limits, 100+ review scale seed (Not verified live) |
| Per-criterion ratings validated, stored, displayed | ora:customer/03-write-and-reply-to-a-review.md (steps 11-12) | frontend, REST, DB | member, guest | partial | partial | Free-alone path, 390 |
| Review form follows Settings > Reviews (min length, auto-approve, one per listing, replies) | ora:customer/review-form-settings-take-effect.md | frontend, REST, settings | member, owner, guest, admin | partial | partial | expected-fail: listing-page form ignores min_length 0 |
| Pending review confirms it saved (no reload, Awaiting approval) | ora:customer/review-form-settings-take-effect.md | frontend | member | partial | partial | browser run |
| One gate for edit/helpful/report/reply/photo on every route (pending, type off, site off) | ora:customer/review-actions-gated-on-every-route.md | REST, listing page | member, owner, other, guest, editor, admin | n/a | n/a | fails_on runs not verified live |
| Reviewer without display name shows as "Member"; no login leak (page, block, REST, oEmbed, feed) | ora:customer/reviewer-name-member-no-login-leak.md | frontend, block, REST, feed | guest, owner, other, admin | partial | partial | deleted/former steps live |
| Blocking a reviewer hides the review, not the total | ora:customer/blocking-a-reviewer-hides-review-not-total.md | frontend, REST, cards, JSON-LD | member, blocked, guest, admin | partial | partial | header/JSON-LD in browser |
| Member dashboard Reviews tab counts match the list | ora:customer/member-reviews-tab-agrees.md | dashboard, REST | member, other, guest | partial | partial | 2000+ scale seed |
| Deep link opens the Reviews tab and switching hides it | ora:customer/reviews-deep-link-tab-switching.md | frontend | guest, owner | partial | partial | no-JS, invalid hashes, 390 |
| Flag a review: accessible dialog, review reasons enum, duplicate | ora:customer/flag-a-review-dialog-and-reasons.md | frontend, block, REST | guest, member, admin | partial | partial | UI steps need browser |
| Flag a listing: confirm once, staff mailed (not owner), admin flag, toggle | ora:customer/flag-a-listing.md | frontend, REST, admin column/metabox, email | guest, member, owner, editor, admin | partial | partial | modal click-through, metabox clear |
| Admin moderates reviews (single, bulk, delete), aggregate + hook + drawer + reply gate | ora:admin/02-moderate-review.md | admin, REST, frontend | admin, editor, owner, author, guest | partial | n/a | editor/subscriber rows, 2000+ list |
| Type Moderation/Reviews switches and site Reviews switch hit every surface | ora:admin/reviews-and-moderation-switches-take-effect.md | admin, frontend, cards, REST, JSON-LD | admin, owner, other, guest, editor | partial | partial | upgrade from previous-version data |
| Moderation/Settings screen capability map | ora:admin/moderation-and-settings-screen-capabilities.md | admin | admin, editor, subscriber, guest | n/a | n/a | Pro moderator role |
| Claim a business (CTA, login gate, proof file, one confirmation, approval) | ora:customer/05-claim-a-business.md | frontend, REST, DB | guest, member, owner, editor, admin | partial | partial | browser upload + file URL refusal |
| Member Claims tab (status chips, pagination, REST scope, no member writes) | ora:customer/13-dashboard-claims-tab.md | dashboard, REST | member, other, guest, admin | partial | partial | feature OFF, dark, filter |
| Admin approves/reverses/rejects/deletes a claim | ora:admin/03-approve-claim.md | admin, REST, email, leads | admin, editor, claimant, member, guest | partial | n/a | screen delete in a browser |
| Admin claims list and REST list share one model and count | ora:admin/claims-list-and-count-agree.md | admin, REST | admin, member, guest, editor | partial | n/a | search/empty/1-row, 2000+ scale |
| Claim audit rows and webhooks on every path (Pro) | pro:admin/claim-audit-trail-both-paths.md | admin, REST, audit log, webhooks | admin, member, editor, guest | partial | n/a | bulk and webhook not verified live |
| Member claims through the modal while Pro visibility gate is on | pro:customer/claim-business-with-visibility-gate-on.md | frontend, REST | guest, member, owner, moderator, admin | partial | partial | Private/Coming Soon modes (other journeys) |
| Contact form stores a lead, emails owner, owner reads it; honeypot, nonce, spam, rate limits | ora:customer/16-listing-contact-form.md | frontend, REST, email, dashboard | guest, member, owner, editor, admin | partial | partial | expected-fail: empty message accepted over REST; Mailpit |
| Blocked member cannot contact owner (both routes) | ora:customer/blocked-member-cannot-contact-owner.md | REST, app-config | member, owner, guest, admin | n/a | n/a | UI hide for blocked pair |
| Lead counted in analytics exactly once | ora:system/lead-counted-without-analytics.md | REST, DB, email | guest, member, owner | partial | partial | Free-only, timezone |
| Leads admin: retention, delete, privacy export/erase, follow owner | ora:admin/leads-admin-retention-privacy-ownership.md | admin, REST, WP privacy tools | admin, editor, owner, other, guest | partial | n/a | paged scale seed |
| Pro deprecated /contact alias and lead hook bridges | pro:customer/lead-form-deprecated-route.md | REST, hooks | guest, member, owner, admin | n/a | n/a | feature-off row |
| Guest submission with email verification | ora:customer/07-guest-submission-with-email-verify.md | frontend, email, REST | guest | partial | partial | HTML content-type, boundary rows |
| Spam protection matrix + captcha + Retry-After | ora:system/spam-protection-layers.md | REST | guest, member, admin | n/a | n/a | expected-fail: reviews/claims skip Anti_Spam; Akismet |
| Hard-delete cascades every listing table | ora:system/listing-delete-cascade.md | CLI, DB | admin | n/a | n/a | expected-fail: cleanup prints 0; leads owner decision; 2000+ rows |
| RTL stylesheet twins | ora:system/rtl-twin-only-claimed-when-it-exists.md | frontend assets | guest | partial | partial | needs RTL language pack |
| Multi-criteria and photo review display + styles on both surfaces (Pro) | pro:customer/05-multi-criteria-photo-review.md | frontend, REST, CSS | member, author, guest, owner, editor, admin | partial | partial | criteria OFF, real picks |
| Review photo upload gates, caps, limits, cleanup (Pro) | pro:customer/review-photo-upload-gates-and-caps.md | REST, frontend, media | author, other, guest, owner, editor, admin | partial | partial | fails_on runs not verified live |
| Verification badge flag, surfaces and feature-off hiding (Pro) | pro:admin/17-verification-badge.md | admin, REST, search, cards, Quick View | admin, moderator, owner, member, guest | partial | partial | search with feature OFF, dashboard chip |
| Moderator bulk actions report the result (Pro) | pro:admin/moderator-bulk-reports-result.md | admin | admin, moderator, editor, guest | partial | n/a | expected-fail: bulk reassign green on bad target |
| Pay-per-lead charges once (Pro) | pro:customer/pay-per-lead-charge-once.md | frontend, REST, credits ledger | vendor, author, member, guest, editor, admin | partial | partial | zero-decimal currency |
| Badge delete one path (Pro) | pro:admin/badge-delete-one-path.md | REST, admin | admin, member, moderator, guest | n/a | n/a | screen delete browser |
| Admin money uses site currency format (Pro) | pro:admin/payments-money-uses-site-currency-format.md | admin | admin | partial | n/a | browser |
| Review webhook payload carries real rating (Pro) | pro:system/webhook-review-payload-carries-real-rating.md | webhook, DB | member, admin | n/a | n/a | wire delivery |
| Token links work while the directory is hidden (Pro) | pro:system/hidden-directory-token-links-work.md | REST, front-end links | guest, member, editor, admin | n/a | n/a | replays, signed webhook |
| Migration preview REST envelope (Pro) | pro:system/migration-preview-rest-contract.md | REST | admin, editor, author, subscriber, guest | n/a | n/a | wrong-typed input |
| Legacy hook names no longer fire (Pro) | pro:system/legacy-hook-names-no-longer-fire.md | hooks | admin | n/a | n/a | exhaustive twin grep |

## Three entry points per data store

| data store | frontend | backend/admin | REST |
|---|---|---|---|
| reviews | 03, review-form-settings, flag-a-review | 02-moderate-review | review-actions-gated, 03 |
| claims | 05, 13 | 03-approve-claim, claims-list-and-count-agree | 05, 13 |
| leads | 16 | leads-admin-retention-privacy-ownership | 16 |
| listing reports | flag-a-listing | flag-a-listing (column + metabox) | flag-a-listing |
