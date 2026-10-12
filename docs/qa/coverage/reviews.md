# Coverage: reviews

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Blocking a reviewer hides the review, not the total | ora:reviews/blocking-a-reviewer-hides-review-not-total.md | frontend, REST, cards, JSON-LD | member, blocked, guest, admin | partial | partial | header/JSON-LD in browser |
| Flag a review: accessible dialog, review reasons enum, duplicate | ora:reviews/flag-a-review-dialog-and-reasons.md | frontend, block, REST | guest, member, admin | partial | partial | UI steps need browser |
| Member dashboard Reviews tab counts match the list | ora:reviews/member-reviews-tab-agrees.md | dashboard, REST | member, other, guest | partial | partial | 2000+ scale seed |
| Admin moderates reviews (single, bulk, delete), aggregate + hook + drawer + reply gate | ora:reviews/moderate-review.md | admin, REST, frontend | admin, editor, owner, author, guest | partial | n/a | editor/subscriber rows, 2000+ list |
| One gate for edit/helpful/report/reply/photo on every route (pending, type off, site off) | ora:reviews/review-actions-gated-on-every-route.md | REST, listing page | member, owner, other, guest, editor, admin | n/a | n/a | fails_on runs not verified live |
| Review form follows Settings > Reviews (min length, auto-approve, one per listing, replies) | ora:reviews/review-form-settings-take-effect.md | frontend, REST, settings | member, owner, guest, admin | partial | partial | expected-fail: listing-page form ignores min_length 0 |
| Pending review confirms it saved (no reload, Awaiting approval) | ora:reviews/review-form-settings-take-effect.md | frontend | member | partial | partial | browser run |
| Reviewer without display name shows as "Member"; no login leak (page, block, REST, oEmbed, feed) | ora:reviews/reviewer-name-member-no-login-leak.md | frontend, block, REST, feed | guest, owner, other, admin | partial | partial | deleted/former steps live |
| Type Moderation/Reviews switches and site Reviews switch hit every surface | ora:reviews/reviews-and-moderation-switches-take-effect.md | admin, frontend, cards, REST, JSON-LD | admin, owner, other, guest, editor | partial | partial | upgrade from previous-version data |
| Deep link opens the Reviews tab and switching hides it | ora:reviews/reviews-deep-link-tab-switching.md | frontend | guest, owner | partial | partial | no-JS, invalid hashes, 390 |
| Member writes a review, moderator approves, owner replies, edits return to moderation | ora:reviews/write-and-reply-to-a-review.md | frontend, dashboard, REST, admin | member, owner, other, guest, editor, admin | yes | partial | rate limits, 100+ review scale seed (Not verified live); block sort and Load more; header aria-label plural for 1 review |
| Per-criterion ratings validated, stored, displayed | ora:reviews/write-and-reply-to-a-review.md (steps 11-12) | frontend, REST, DB | member, guest | partial | partial | Free-alone path, 390 |
| Multi-criteria and photo review display + styles on both surfaces (Pro) | pro:reviews/multi-criteria-photo-review.md | frontend, REST, CSS | member, author, guest, owner, editor, admin | partial | partial | criteria OFF, real picks |
| Review photo upload gates, caps, limits, cleanup (Pro) | pro:reviews/review-photo-upload-gates-and-caps.md | REST, frontend, media | author, other, guest, owner, editor, admin | partial | partial | fails_on runs not verified live |
| Duplicate review criteria get unique keys | ora:reviews/listing-type-review-criteria-unique-keys.md | A, F | admin, editor, owner | both (n.v.) | n.v. | not run live |
