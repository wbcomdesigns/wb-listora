# WB Listora — QA Journeys by End User

A checkable matrix of every customer-facing flow in WB Listora (Free) + WB Listora Pro. Walk it role-by-role. Each item is one testable journey: short title, where it lives, and the success criterion.

> **Existing scripted journeys** are flagged `[scripted]` — they live under [`audit/journeys/`](journeys/) and run via `composer journeys`. Everything else is a manual walkthrough until it's promoted to a scripted journey.

> **Pro-only items** are flagged `(Pro)`. Free items work without `wb-listora-pro` active.

---

## 1. Anonymous Visitor

No account, just landing on the site.

### Discovery
- [ ] **Browse the directory home** — `/` (or whichever page hosts `listing-grid` block) — grid renders, cards have title/category/image/rating, no console errors. `[scripted: 01]`
- [ ] **Filter by category** — sidebar/header facets on the grid page — URL updates, results filter, paging works.
- [ ] **Filter by location** — city/region facet — distance accurate when geo data is present.
- [ ] **Search by keyword** — search bar on home — full-text matches in title, description, and services.
- [ ] **Search by distance** *(Pro)* — Google Maps "Near me" radius — results sorted by haversine distance.
- [ ] **Featured carousel** — scrolls on desktop + 390px, autoplay/pause respect reduced-motion.
- [ ] **Categories grid** — clickable tiles → category archive.
- [ ] **Calendar of events** — `event` listing-type entries render with start/end dates.
- [ ] **Compare listings** *(Pro)* — `?compare=` URL OR floating bar — side-by-side table renders, "Remove" works, "Share" copies URL with state.

### Listing Detail
- [ ] **Open a listing detail page** — gallery, hours, description, services tab, reviews tab all render.
- [ ] **Map embed renders** *(Pro)* — Google Maps shows correct pin.
- [ ] **Schema.org JSON-LD** — `<script type="application/ld+json">` includes LocalBusiness + AggregateRating + OfferCatalog.
- [ ] **Anonymous review attempt** — clicking "Write Review" prompts login modal, doesn't submit.
- [ ] **Anonymous claim attempt** — clicking "Claim this listing" prompts login modal.
- [ ] **Anonymous favorite attempt** — heart icon prompts login.
- [ ] **Anonymous lead form** *(Pro)* — Contact form submits to listing owner without requiring an account.

### Site-wide
- [ ] **Coming-soon mode redirect** *(Pro)* — when visibility=coming_soon, anonymous users see the splash and can't reach listings.
- [ ] **Private mode redirect** *(Pro)* — when visibility=private, anonymous users redirected to login. `[scripted: admin/visibility-private-mode]`
- [ ] **Sitemap & SEO pages** — XML sitemap includes published listings, type/category/location archives have unique titles.
- [ ] **404 on draft/pending listing** — anonymous user gets 404, not pending preview.

---

## 2. Customer / Subscriber

Registered user. Submits, reviews, favorites. Has a dashboard at `/dashboard/`.

### Account
- [ ] **Register from "Submit Listing" CTA** — guest hits `/add-listing/`, prompted to register, account created with `subscriber` role.
- [ ] **Email verification** *(if enabled)* — verification email sent, link verifies, listing activates.
- [ ] **Login via wp-login** — standard WP login redirects back to dashboard.
- [ ] **Profile edit** — handled by WP core; not Listora-owned but smoke-test it doesn't break.
- [ ] **Logout** — destroys session, hides admin-bar shortcut.

### Dashboard `/dashboard/`
- [ ] **Listings tab** — list of own listings with status (pending/publish/expired/draft), edit/delete/renew actions.
- [ ] **Reviews tab** — reviews authored, with edit and inline reply form (`[scripted: 03]`).
- [ ] **Favorites tab** — list, "Remove" works, navigates to listing detail. `[scripted: 01]`
- [ ] **Credits tab** *(Pro)* — current balance, transaction history, "Buy Credits" CTA, credit-packs grid.
- [ ] **Saved Searches tab** *(Pro)* — list, edit alert frequency, delete.
- [ ] **Needs tab** *(Pro)* — needs the user posted, responses received, accept/reject responses.

### Submission Wizard `/add-listing/` (full flow)
- [ ] **Step 1 — Type** — picks `business` (or any type), "Continue" enables.
- [ ] **Step 2 — Basic Info** — title required, category required, tags optional, description min length.
- [ ] **Step 3 — Details** — address with map pin, phone, website, hours, custom fields per type.
- [ ] **Step 4 — Media** — upload featured image, gallery up to N items, file-size validation, ARIA error on too-large files.
- [ ] **Step 5 — Plan** *(Pro, only when plans exist)*:
  - [ ] Plan cards render, balance pill shows correct credits.
  - [ ] Free plan selectable, paid plan greyed when balance insufficient.
  - [ ] "Buy Credits" link from disabled paid card → credits page.
  - [ ] Coupon code: valid → discount applied; invalid → error message; expired → error.
  - [ ] Plan step is OMITTED when no plans configured (admin hasn't set any up).
- [ ] **Step 6 — Preview** — review before submit, edit-back works.
- [ ] **Submit — Free plan, manual moderation** — listing → `pending`, no credits deducted, confirmation email sent.
- [ ] **Submit — Paid plan, sufficient credits** — listing → `publish`, credits deducted, featured perk applied, expiration = plan duration.
- [ ] **Submit — Paid plan, insufficient credits** — listing → `listora_payment` status, no credits deducted, user redirected to credits.
- [ ] **Submit — Auto-approve setting on** — listing → `publish` immediately regardless of plan.
- [ ] **Duplicate detection** — submitting near-identical title/address shows duplicate-confirmation step before submit.

### Listing Management
- [ ] **Edit own pending listing** — can edit until approved.
- [ ] **Edit own published listing** — can edit, certain fields may re-trigger moderation.
- [ ] **Renew expiring listing** — credits deducted, expiry pushed, audit log row written.
- [ ] **Hit max-renewal cap** *(Pro plan-defined)* — UI shows "max renewals reached", suggests different plan.
- [ ] **Delete own listing** — confirmation modal, soft-deletes (trash) or hard-deletes per setting.
- [ ] **Receive expiry reminders** — email at 7d and 1d before expiration.
- [ ] **Receive payment-pending notice** — when plan deduct fails, email + dashboard banner.

### Listing Services
- [ ] **Add service** — name, price, duration, category — saved to `listora_services`.
- [ ] **Edit service** — same form, update.
- [ ] **Delete service** — confirms, removed from listing detail page.
- [ ] **Service appears on detail** — under Services tab with schema.org `OfferCatalog`.

### Reviews
- [ ] **Submit review on someone else's listing** — 1-5 stars, text required, submitted.
- [ ] **Multi-criteria rating** *(Pro)* — separate sliders for cleanliness/value/location etc.
- [ ] **Photo upload with review** *(Pro)* — gallery attached to review.
- [ ] **Edit own review** — within edit window.
- [ ] **Delete own review** — soft-deletes.
- [ ] **Mark review helpful** — vote count +1, debounced.
- [ ] **Report review** — abuse form submits, moderator queue gets entry.
- [ ] **Reply to review (as listing owner)** — inline form on dashboard, posts reply.

### Favorites + Claims
- [ ] **Add to favorites** — heart icon flips on listing card AND detail page.
- [ ] **Remove from favorites** — un-flip works from card OR dashboard tab.
- [ ] **Submit claim on unclaimed business** — modal, confirm ownership method, claim row created. `[scripted: claim-business]`
- [ ] **Claim approval/rejection email** — when admin acts, email arrives.
- [ ] **Verified badge** *(Pro)* — appears on approved-claim listings.

### Credits *(Pro)*
- [ ] **Buy credits via Stripe** — pack picker → Stripe Checkout → return URL → balance updated. `[scripted: buy-credits]`
- [ ] **Buy credits via PayPal** — same flow, PayPal Orders v2.
- [ ] **Webhook idempotency** — replay same webhook → no double-credit.
- [ ] **View credit history** — ledger shows topups, deductions, holds.
- [ ] **Receive credit-low warning** — when balance < threshold, dashboard banner.

### Notifications & Alerts *(Pro)*
- [ ] **Saved-search alert email** — daily/weekly cron emails new matching listings.
- [ ] **Lead-form contact** — listing owner gets email; user gets confirmation.
- [ ] **Notification digest** — instant vs digest mode honored per user.
- [ ] **Need-fulfilled email** — needs feature: posted, response accepted, fulfilled email.

---

## 3. Listing Owner (subset of Customer)

A customer who owns at least one published listing. Adds the operational "I run a business listed here" flows.

- [ ] **Receive lead-form submissions** *(Pro)* — emails from contact form on detail page.
- [ ] **Analytics dashboard** *(Pro)* — views, clicks, contact-clicks, charted by day/week/month.
- [ ] **Reply to incoming reviews** — inline form on dashboard. `[scripted: 03]`
- [ ] **Manage services CRUD** — see Services section above.
- [ ] **Set business hours** — weekly schedule, special hours overrides.
- [ ] **Verification request** *(Pro)* — admin metabox; once verified, badge appears.
- [ ] **Renewal flow** — credit-deduct + expiry-push + audit-log.
- [ ] **Featured perk active** — Pro plan with `is_featured=true` puts the listing in the featured carousel for the plan duration.
- [ ] **Outgoing webhook fired on listing approval/expiry** *(Pro)* — third-party endpoint receives JSON payload.

---

## 4. Moderator *(Pro)*

A user with the `listora_moderator` capability. Reviews community content.

- [ ] **Access Moderator Queue admin page** — `wp-admin/admin.php?page=listora-moderator` renders.
- [ ] **Approve pending reviews** — bulk action, email notifies the reviewer.
- [ ] **Reject pending reviews** — bulk action, optional reason.
- [ ] **Promote a subscriber to moderator** — admin promotes via dropdown; cap added.
- [ ] **Demote a moderator** — admin demotes via dropdown; cap removed.
- [ ] **View moderation audit log** — every approve/reject logged.
- [ ] **Photo review moderation** *(Pro)* — image preview + approve/reject.

---

## 5. Site Admin

Manages the directory. Has `manage_listora_settings` cap.

### Setup
- [ ] **Run setup wizard** — first-time install — creates demo content per type, configures settings.
- [ ] **Re-run setup wizard** — `Listora → Setup Wizard` — re-prompts safely without duplicating data.
- [ ] **Pro setup** *(Pro)* — license key entered, validates, features enabled.

### Daily Ops
- [ ] **Listings list table** — filters by status/type, bulk approve/reject works.
- [ ] **Approve a pending listing** — moves to publish, fires `wb_listora_listing_status_changed`, owner email sent.
- [ ] **Reject a pending listing** — moves to draft/trash, owner email sent with reason.
- [ ] **Reviews moderation** — Reviews list table, approve/reject/report-handle.
- [ ] **Claims queue** — approve/reject claims; on approve, listing ownership transfers to claimant.
- [ ] **Email Log** — `Listora → Email Log` — table paginates 25/page, retention selector saves, CSV export downloads. *(just shipped)*

### Configuration
- [ ] **General settings tab** — site name, currency, default location, page mappings.
- [ ] **Submission settings tab** — moderation mode (manual/auto), required fields per type, duplicate detection toggle.
- [ ] **Notifications tab** — admin email, per-event toggles, "Send Test" buttons all fire.
- [ ] **Credits settings tab** *(Pro)* — credit-rate, credit-packs, credits page, webhook URL/secret.
- [ ] **Tools tab** — re-index search, repair geo, regenerate badges, run migrations.
- [ ] **Health tab** — system info, DB status, cron health.
- [ ] **Import/Export tab** — CSV/JSON import, third-party migration (Yelp, Google, etc.), full export.

### Pricing & Promo *(Pro)*
- [ ] **Create a pricing plan** — `wp-admin/edit.php?post_type=listora_plan` — set credits, duration, perks.
- [ ] **Edit a plan** — changes apply to NEW activations; existing listings keep their snapshot.
- [ ] **Delete a plan** — protect against deleting plan that's in use (foreign-key check on `_listora_plan_id`).
- [ ] **Create a coupon** — `Listora → Coupons` — code, discount %, expiry, usage cap.
- [ ] **Apply coupon** — validates on Plan step (see Customer journey).
- [ ] **Manage badges** — `Listora → Badges` — create condition or plan badges, set color/icon, display-limit slider.

### Credit Operations *(Pro)*
- [ ] **Add credits to user manually** — admin panel, audit row written.
- [ ] **Refund credits** — undo a deduction, ledger row written.
- [ ] **Transactions list** — every gateway txn logged with idempotency key.
- [ ] **Audit log** — every cap-sensitive action recorded.

### Customization *(Pro)*
- [ ] **White-label settings** — branding swap on admin pages.
- [ ] **Visibility / Coming Soon** — toggle modes; verify front-end gate (see Anonymous tests).
- [ ] **License management** — re-validate, deactivate, re-activate.

### Reset / Destructive
- [ ] **Reset settings (Free + Pro)** — wipes options including Pro options via `wb_listora_after_reset_settings` listener.
- [ ] **Plugin deactivation** — Pro deactivation clears Pro cron events; Free deactivation safe.
- [ ] **Plugin uninstall** — drops Pro tables, deletes Pro options, removes plan posts.

---

## 6. System / Cron / Webhooks

Headless flows that run on schedule or in response to external events. Verify by inspecting Action Scheduler queue, options, log tables.

### Scheduled (Action Scheduler)
- [ ] **Daily expiry-reminder cron** — emails 7-day and 1-day reminders.
- [ ] **Daily expired-listings sweep** — listings past `_listora_expiration_date` move to expired.
- [ ] **Daily draft cleanup** — abandoned drafts > N days old purged.
- [ ] **Daily featured rotation** — featured slots cycled per plan-duration.
- [ ] **Daily email-verification cleanup** — stale verification tokens purged.
- [ ] **Daily email-log prune** — entries older than retention pruned. *(just shipped — check `wb_listora_prune_email_log` next run)*
- [ ] **Weekly license heartbeat** *(Pro)* — Pro pings license server, status option updated.
- [ ] **Daily saved-search alerts** *(Pro)* — alert emails sent.
- [ ] **Daily analytics retention** *(Pro)* — old rows in `listora_analytics` archived/dropped.
- [ ] **Outgoing webhook retry** *(Pro)* — failed webhooks retried with backoff.

### Webhook receivers *(Pro)*
- [ ] **Stripe payment webhook** — `/listora/v1/webhook` accepts signed payload, credits user, idempotent on replay.
- [ ] **PayPal payment webhook** — same shape, signature verifier accepts.
- [ ] **Invalid signature → 401** — never credits user.
- [ ] **Unknown event type → 200 ack** — doesn't crash.

### Hook chain integrity
- [ ] **`wb_listora_listing_submitted` chain** — Free fires it; Pro listeners (Pricing_Plans, Outgoing_Webhooks) all execute.
- [ ] **`wb_listora_listing_status_changed` chain** — Notifications send email, Pro outgoing webhooks dispatch.
- [ ] **`wb_listora_listing_expiration_date` filter chain** — Pro overrides Free's default (verified for paid plan, free plan, renewal).
- [ ] **`wb_listora_listing_claimed` action** — Pro's `Verification::on_listing_claimed` syncs `search_index.is_claimed`.

---

## How to use this list

- Walk the role you care about, top to bottom.
- A row is **passed** when its bullet does what the label says, in a real browser, against today's main branch.
- A row is **failed** when it doesn't — open a Basecamp card, paste the row, link the failing step, and reproduce.
- Promote frequently-broken rows into scripted journeys under [`audit/journeys/`](journeys/) so they run via `composer journeys` and gate the pre-push hook.

For any new feature: add the row to the relevant role section in this file as part of the same PR that ships the feature. The row IS the acceptance criterion.
