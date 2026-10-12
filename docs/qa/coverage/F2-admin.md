# Coverage: F2-admin (47 journeys consolidated to 33)

Owner = the one journey that asserts the behaviour. Paths: `ora:` = wb-listora `docs/qa/journeys/`, `pro:` = wb-listora-pro `docs/qa/journeys/`. Viewport/theme cells say what the journey asserts; "-" = not applicable, "gap" = missing and listed in the gaps column. All added steps are marked "Not verified live" inside the journeys.

| behaviour | owner journey | surfaces | roles | 1440/390 | light/dark | gaps |
|---|---|---|---|---|---|---|
| Public app-config serves the full contract; Free resolves only free flags; also: `app_enabled` fails closed in Free | ora:system/app-config-contract-shape.md | REST | guest, owner, editor, admin | - | - | role-ladder step added, not run |
| Pro merges license gate, real flags, branding; payload leaks nothing private | pro:system/app-config-pro-merge.md | REST, CLI | guest, owner, admin | - | - | CDN-cached copy; `expires_at` timezone boundary |
| Member signs in to the app with their password; owner can switch it off; rate limit; no enumeration | ora:system/app-password-sign-in.md | REST, admin (Settings > Advanced), CLI | guest, owner, admin, editor | settings screen gap | gap | 2FA/membership plugin step (8) unrun; settings-screen layout |
| Recurring Free cron hooks never hold duplicate pending actions; sweep only on scheduling requests | ora:system/cron-recurring-dedupe.md | CLI, REST, admin | admin, guest, system | - | - | activation race itself not reproducible |
| Pro recurring jobs on Action Scheduler, lazy arming, need expiry, webhook log trim, licence check | pro:system/pro-cron-coverage.md | CLI, admin, REST | system, admin, owner, editor, guest | - | - | digest ON case; timezone matrix; step 6 probe unrun |
| Feature toggles: screen rows, isolation matrix, OFF cancels jobs, ON flushes URLs, tab-controlled keys survive | pro:admin/feature-toggles.md | admin, REST, frontend, CLI | admin, editor, owner, other, guest | 1440+390 asserted (steps 13) | light/dark asserted, unrun | front-end OFF states on shared site; seo_pages ON |
| License screen truthful on both surfaces, never gates features, no extra PII, throttled; also: licence key never blank when active (get_key falls back to the SDK key; wizard shows the key) | pro:admin/license-lifecycle.md | admin, wizard, Plugins modal, CLI | admin, editor, owner, other, guest | 1440+390 asserted | asserted, unrun | non-local host steps; SDK option holds customer_email (expected-fail) |
| Dependency notices reach only the owner and never fatal | pro:admin/dependency-notices-owner-only.md | admin | admin, editor, owner, guest | 1440 | gap | throwaway-site only, unrun; 390/dark |
| White label: brand scope, two rows differ, app branding, never touches identifiers | pro:admin/white-label-branding.md | admin, Plugins screen, privacy tools, REST app-config, email | admin, editor, owner, other, guest | 1440+390 | light/dark | whole-screen grep (step 6) unrun; multisite |
| Moderators screen: promote, pause, bulk, paging, counters, self-protection | pro:admin/moderators-management.md | admin, REST | admin, other, editor, guest | 1440+390 (step 17 unmeasured) | gap | REST parity step; retry |
| Moderator sees the catalog minus others' queue | pro:admin/moderator-sees-the-catalog.md | admin | admin, other, editor, guest | 1440+390 | gap | WooCommerce lockout (expected-fail) |
| Badges: create, assign, cap, order, list count truthful | pro:admin/create-badge.md | admin, frontend card/detail, REST | admin, editor, owner, other, guest | 1440+390 | light/dark | plan-perk assignment; parallel save |
| Audit log screen, CSV, moderator clamp, retention, plain titles, IP spoof guard | pro:admin/audit-log.md | admin, REST, CSV, CLI | admin, other, editor, owner, guest | 1440+390 | light/dark | timezone retention matrix |
| Needs moderation: approve, reject with note, close, double action | pro:admin/reverse-listings-moderation.md | admin, email, REST | admin, owner, other, editor, guest | 1440+390 | light/dark | 2,000+ needs and parallel unrun |
| Competitor migration (Pro UX over Free pipeline), HivePress source, idempotent re-run | pro:admin/competitor-migration.md | admin, REST | admin, owner, editor, guest | 1440+390 | gap | no competitor plugin on reference site: real run unrun |
| Google Places import | pro:admin/google-places-import.md | admin, REST | admin, owner, editor, guest | 1440+390 | light/dark | needs API key; quota; N=0 |
| Visual CSV import wizard incl. header-skip default | pro:admin/visual-import-csv.md | admin, REST, Action Scheduler | admin, owner, editor, guest | 1440+390 | light/dark | retry/boundary step unrun |
| Hidden submenu stubs redirect cleanly | pro:admin/hidden-submenu-redirects.md | admin | admin, other, guest | - | - | Playwright check unrun |
| Outgoing webhook admin CRUD, test, listener, REST CRUD, secret masking | pro:admin/outgoing-webhook-management.md | admin, REST, queue | admin, editor, owner, guest | 1440+390 | light/dark | label inset unrun; boundary inputs |
| Webhook delivery: signature, retry ladder, id stability, auto-pause, deliveries REST, retry API | pro:system/webhook-delivery-and-retry.md | script, admin, REST | admin, owner, other, editor, guest | 390 unrun | gap | parallel retry; timezone of scheduled_at |
| Inbound payment webhook strict HMAC and replay defence | pro:system/webhook-strict-hmac.md | REST, audit | guest, owner, admin, system | - | - | doubled audit rows (expected-fail); parallel replay |
| Inbound webhook with no transaction id is replay-safe or refused | pro:system/webhook-missing-txn-id.md | REST, DB | system, guest, admin | - | - | parallel UNIQUE race; zero-decimal currency |
| App/native writes: contact form + click tracking nonce rules, 429 | pro:system/app-nonce-exempt-pro-writes.md | REST | guest, owner, other, admin | - | - | boundary inputs; retry |
| Pro email templates render warning-free | pro:system/email-template-palette-keys.md | CLI render | admin, system, owner | - | gap | the other 15 templates |
| Pro uninstall in both orders keeps data unless opted in | pro:system/pro-uninstall-both-orders.md | CLI | admin, system, editor | - | - | throwaway site only; F, G, multisite unrun |
| Admin Location column renders region-level addresses | ora:admin/admin-location-column-no-city.md | admin | admin, editor, other, guest | 1440+390 | gap | boundary + large-table steps unrun |
| Submission form style setting (wizard vs single page) | ora:admin/submission-form-style-setting.md | admin, frontend | admin, owner, guest, editor | 1440+390 | light/dark | steps 3-7 unrun |
| Services can be switched off per listing type | ora:admin/services-per-listing-type.md | admin, frontend, dashboard, REST | admin, guest, owner, other | 1440+390 | light/dark | edit/save flow unrun; lifecycle |
| Type editor "Selected only" filter does not alter saved ids | ora:admin/type-editor-selected-only-saves.md | admin, REST | admin, editor, owner, guest | 1440+820 | gap | empty selection, retry unrun |
| Service description toggle on the listing page | ora:customer/service-details-toggle.md | frontend | guest, owner, other, admin | 1440+390 | light/dark | click unrun in a browser |
| Unticking a toggle on the frontend edit form saves | ora:customer/toggle-untick-persists-frontend-edit.md | frontend, REST | owner, other, guest, admin | 1440+390 | light/dark | form-level steps unrun |

Pro template: `pro:.template.md` rewritten to the uniform shape (no behaviour).
