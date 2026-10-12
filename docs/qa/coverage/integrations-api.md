# Coverage: integrations-api

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Listing REST envelope, pagination, timestamps | ora:integrations-api/listing-rest-contract.md | R | guest, owner, admin | n/a | n/a | scale seed and timezone sub-step not live |
| REST strings decoded (title, alt, term names, dropdown) | ora:integrations-api/listing-rest-contract.md | R, F | guest, owner | 390 dropdown | n/a | /search excerpt not decoded: expected-fail |
| Related, bulk, renewal-quote, services reorder routes | ora:integrations-api/listing-rest-contract.md | R | guest, owner, other, admin | n/a | n/a | app-config secrecy owned by integrations-api/app-config-contract-shape.md (cross-area) |
| Space showcase approves only published listings; total counts rendered | ora:integrations-api/space-listings-rest.md | R | space-team, owner, other, guest, admin | n/a | n/a | 403 rows and scale paging not live; no BuddyNext |
| Space removal reports reject / takedown / withdraw | ora:integrations-api/space-listings-rest.md | R | space-team, owner, other, guest, admin | n/a | n/a | steps 4-7 read from source only |
| Space moderation queue is bounded | ora:integrations-api/space-moderation-queue-is-bounded.md | R | admin, space-moderator, other, guest | n/a | n/a | needs host filter; scale step n.v. |
| Public app-config serves the full contract; Free resolves only free flags; also: `app_enabled` fails closed in Free | ora:integrations-api/app-config-contract-shape.md | REST | guest, owner, editor, admin | - | - | role-ladder step added, not run |
| Member signs in to the app with their password; owner can switch it off; rate limit; no enumeration | ora:integrations-api/app-password-sign-in.md | REST, admin (Settings > Advanced), CLI | guest, owner, admin, editor | settings screen gap | gap | 2FA/membership plugin step (8) unrun; settings-screen layout |
| Pro merges license gate, real flags, branding; payload leaks nothing private | pro:integrations-api/app-config-pro-merge.md | REST, CLI | guest, owner, admin | - | - | CDN-cached copy; `expires_at` timezone boundary |
| BuddyPress profile Listings/Reviews cards, pagination, per-page; also: BuddyPress profile tabs pagination | pro:integrations-api/bp-profile-listings-styled.md | F | owner, other, editor, admin, guest | both (+820) | not run | dark/unbridged unrun; overlaps buddypress-tabs-pagination; 2,000-row seed not run |
| BuddyPress review-author links and labelled stars | pro:integrations-api/buddypress-integration.md | frontend, REST | guest, member, editor, admin | both | both | aria-label read-back not live |
| Outgoing webhook admin CRUD, test, listener, REST CRUD, secret masking | pro:integrations-api/outgoing-webhook-management.md | admin, REST, queue | admin, editor, owner, guest | 1440+390 | light/dark | label inset unrun; boundary inputs |
| Pro secondary REST routes auth/shape/gate | pro:integrations-api/pro-rest-completeness.md | R | admin, editor, owner, other, guest | n/a | n/a | in-flight import cancel, feature-OFF 404, boundary and rate-limit steps not run |
| Webhook delivery: signature, retry ladder, id stability, auto-pause, deliveries REST, retry API | pro:integrations-api/webhook-delivery-and-retry.md | script, admin, REST | admin, owner, other, editor, guest | 390 unrun | gap | parallel retry; timezone of scheduled_at |
| Inbound webhook with no transaction id is replay-safe or refused | pro:integrations-api/webhook-missing-txn-id.md | REST, DB | system, guest, admin | - | - | parallel UNIQUE race; zero-decimal currency |
| Review webhook payload carries real rating (Pro) | pro:integrations-api/webhook-review-payload-carries-real-rating.md | webhook, DB | member, admin | n/a | n/a | wire delivery |
| Inbound payment webhook strict HMAC and replay defence | pro:integrations-api/webhook-strict-hmac.md | REST, audit | guest, owner, admin, system | - | - | doubled audit rows (expected-fail); parallel replay |
| Webhook subscriber UI matches the trigger registry | pro:integrations-api/webhook-subscriber-ui-matches-registry.md | A, R | admin, other, guest | Y | light | end-to-end delivery not run, boundary |
| Webhooks admin list paging + last delivery | pro:integrations-api/webhooks-list-pagination.md | admin, REST | admin, editor, member, guest | both | n | past-last-page (expected-fail); concurrency not live |
