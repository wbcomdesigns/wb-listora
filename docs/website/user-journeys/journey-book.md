# WB Listora Journey Book

The bound atlas. One document you can hand to a new product manager, marketing director, or customer-success lead on day one and have them understand what the product actually does, who it serves, and how the pieces connect.

WB Listora is a private WordPress directory plugin distributed at wblistora.com (never on wordpress.org). It ships in two halves - a mandatory Free plugin that delivers a complete, public, searchable directory with reviews, claims, frontend submission, and the full taxonomy / map / spam-protection stack, plus an optional Pro plugin that layers on the business-model parts (credit-based plans, lead capture, advanced analytics, moderators team, comparison, verification, white-label, BuddyPress sync, and the reverse Needs marketplace). Pro extends Free, never stands alone. Together they support WordPress 6.9 or newer on PHP 7.4 or newer, with 226 fired hooks (120 actions + 106 filters), 55 Free REST routes (Pro adds 65 more), 11 Free blocks, 5 Pro blocks, 32 Pro feature modules, 9 demo packs, a 6-layer anti-spam stack, and 7 payment integrations (Stripe direct, PayPal direct, WooCommerce, WooSubscriptions, MemberPress, Paid Memberships Pro, WooMemberships).

## The lifecycle model

Every persona we serve moves through the same seven-stage arc. Different surfaces, same shape.

| Stage | What the customer wants | What we deliver |
|---|---|---|
| **Awareness** | Discover that a tool exists for the problem they have | wblistora.com marketing, the directory's own SEO, demo packs that make a fresh install look traffic-worthy from day one |
| **Consideration** | Compare options. Decide if Free is enough or Pro from day one | Feature matrix, comparison page, the directory's own quality (photo coverage, review density) |
| **Purchase** | Commit money or commit time | wblistora.com checkout (Pro), credit purchase via one of 7 integrations (Vendor), free browsing (Visitor) |
| **Onboarding** | First success in under 30 minutes | 6-step setup wizard, frontend submission wizard, capability-gated moderator sidebar |
| **Activation** | First real proof of life | First paying vendor (Site Owner), first lead (Vendor), first contact (Visitor), first 10 approvals (Moderator) |
| **Retention** | Stay valuable week over week | 7-day renewal reminder cron, audit log, saved searches, daily moderation rhythm |
| **Advocacy** | Recommend, refer, resell | White-label, public REST API, share buttons, case-study collaboration |

The full lifecycle doc with channels and KPIs per stage lives at [lifecycle.md](lifecycle.md). The grid below shows which persona owns which slice.

## The 5 personas

The cast we write for. If a sentence does not serve at least one of them, cut it.

### Site Owner / Operator - Sarah Chen

Independent directory operator (or someone who acts like one). Runs WordPress confidently, does not write code, and is on the hook for hosting + revenue. Wants to launch a directory that earns money from vendor subscriptions or featured placements within 90 days, keep operational overhead under 5 hours a week, and not get locked into a stack that prevents her from switching themes or pivoting later. Burned by plugins that took weekends to configure, bolted-on payment systems that required custom glue, and search performance that fell over past 5,000 listings.

**Her journey in one paragraph:** Install Free in 30 minutes via the 6-step setup wizard, load a demo pack so the directory looks alive from minute one, build out taxonomy (categories, locations, amenities), configure submissions + anti-spam + notifications, optionally activate Pro to charge vendors via credit packs + pricing plans + one of 7 payment integrations, open the floodgates to real vendors via frontend submission or CSV / GeoJSON / JSON import (or competitor migration from Directorist / GeoDirectory / WPBDP / ListingPro), then settle into a weekly rhythm of approving listings + reviews + claims, watching the audit log for anomalies, monitoring analytics, and confirming notifications deliver via the email log. The work is the directory, not the plugin.

Full journey: [site-owner.md](site-owner.md).

### Agency / Reseller - Marcus Webb

WordPress agency owner or senior developer building client projects. Developer-fluent. Evaluates plugins the way an engineer evaluates a dependency: architecture first, support track record second, price third. Wants to deliver polished, white-labelled directory products to multiple clients without rebuilding the engine each time. Has been burned by plugins that ship breaking changes in minor versions, have no hooks, or require forking templates to add basic customization.

**His journey in one paragraph:** Reads the REST docs (55 Free + 65 Pro endpoints), greps the source for `do_action` (finds 120 actions + 106 filters - 226 in total - all documented with `args_signature` and `consumed_by` arrays), confirms WooCommerce-style template overrides and capability-gated moderator role exist, verifies the 2-minor-version deprecation policy, then ships the same Listora install to client A. Six months later, ships it to client B with a different brand color and logo via White Label, different demo pack, different Stripe account. Same engine, two productized packages. The agency journey is structurally the same as the Site Owner journey + the white-label and REST notes in `site-owner.md` Stage 5 and 6.

Full journey: shared with [site-owner.md](site-owner.md) - read it through the agency lens.

### Listing Owner / Vendor - Diego Ramirez

Small business owner with a listing on the directory. Not technical. Uses a phone for most tasks. Logs into WordPress only if forced - and only if the dashboard does not look like wp-admin. Did not choose Listora; the operator chose Listora. Wants to be visible, manage his listing in under 5 minutes per week, respond to reviews professionally, and never get blindsided by an expiring listing.

**His journey in one paragraph:** Discovers the directory through Google or word of mouth, decides in 30 seconds whether it is worth being on, fills the multi-step submission wizard (4-6 steps, drafts auto-save, draggable map pin, 7 social platforms supported) in 10-15 minutes from his phone, gets a confirmation email + a second one when the operator approves him, manages everything from the frontend dashboard at `/my-dashboard/` (canonical Pro slug) or `/my-listings/` (legacy Free slug - both work), replies to reviews publicly, optionally claims an existing listing if his business was already in the directory, optionally buys a Pro plan that gives him Featured rotation + Lead Form analytics + Verification badge eligibility, and gets a 7-day reminder before his listing expires so renewal is one click via `POST /listings/{id}/renew`. There is no auto-renew today - every renewal is intentional.

Full journey: [listing-owner.md](listing-owner.md).

### Visitor / End Customer - Priya Nair

The end customer. Arrived from Google, a social share, or a recommendation. Knows nothing about the plugin powering the site. Has an immediate need (a restaurant for tonight, a plumber for tomorrow, a venue for next month). Will leave within 10 seconds if the site does not show her relevant results. Will not sign up to browse. Mobile-first (70% of traffic).

**Her journey in one paragraph:** Lands on the directory home or `/listings/`, sees search + filters + featured grid above the fold, types a query (autocomplete suggests), clicks Near Me (Haversine query re-sorts by distance), narrows by facet (category, location, feature, custom field, minimum rating, open now), URL state preserves her filter set across reloads + makes it shareable. Picks 2-3 candidates, optionally compares them side-by-side (Pro), opens a detail page that shows the hero gallery + tabs (Overview, Location, Reviews, Services, Map, Place Details) + a sidebar with one-click contact / phone / directions / claim / share. Contacts the business via Free contact form or Pro Lead Form (operator-dependent), writes a review post-experience (5-star + optional multi-criteria + photos in Pro), optionally saves the search for recurring email alerts (Pro), optionally flips the script entirely and posts a Need (Pro reverse marketplace) so businesses respond to her with quotes instead of the other way around.

Full journey: [visitor.md](visitor.md).

### Moderator / Team Member - Aisha Okonkwo

Trusted team member with capped permissions. Could be a freelancer hired specifically for moderation, a junior team member, or a vendor elevated to moderate their own category. Cannot touch settings, pricing, or the moderator list itself - that requires `manage_listora_moderators`, which is admin-only. Needs to triage 20-50 items per day in clear, separated queues, with audit trail.

**Her journey in one paragraph:** Invited by the Site Owner via Listora → Moderators with a selected scope (Listings / Reviews / Claims / Reports - any combination), receives an assignment email, logs in to find her sidebar surfaces only the relevant Listora screens (NOT Settings, Pricing Plans, Coupons, Webhooks, Audit Log, Email Log, or the Moderators page itself), works through pending items on their separate admin pages (there is no single unified Moderation Queue admin page - the separation is intentional so capability gating stays clean), uses bulk-moderate via `POST /listora/v1/listings/bulk-moderate` for up to 100 items at once when she trusts the batch, leaves admin notes on rejections so the submitter gets the reason in their notification email, and never has to manually email anyone - status transitions auto-fire the appropriate notifications.

Full journey: [moderator.md](moderator.md).

## The 10 task-based journeys

Persona journeys answer "what does Diego do over time?" Task journeys answer "Diego has just asked me 'how do I X?' - what is the shortest correct answer?"

| When the customer asks… | Use task journey |
|---|---|
| "How do I add my business to this directory?" | Task 1 - Add my business to the directory |
| "Someone else listed my business - how do I take it over?" | Task 2 - Claim an existing listing I see is mine |
| "Can I pay to be more visible?" | Task 3 - Upgrade my listing to Featured |
| "I am a plumber - how do I get leads from this directory?" | Task 4 - Promote my plumbing services to people who need them |
| "I need a restaurant near me right now" | Task 5 - Find a restaurant near me right now |
| "I want to compare a few candidates without opening 10 tabs" | Task 6 - Compare 3 dentists side by side |
| "I keep searching the same thing - can the site tell me when something new fits?" | Task 7 - Be notified when a new venue opens in my city |
| "I want my reviewer to handle reviews and nothing else" | Task 8 - Add a teammate who can only moderate reviews |
| "How do I start making money from vendors?" | Task 9 - Start charging vendors for premium placement |
| "I have 5,000 listings in Directorist - how do I move them over?" | Task 10 - Migrate 5,000 listings from Directorist without downtime |

Each task carries setup → steps → expected result → what-could-go-wrong table. Full file: [task-based-journeys.md](task-based-journeys.md).

## Where journeys overlap - the cross-persona orchestration map

The product's real value is in the loops between personas. A single listing publication touches every persona we serve.

```
Vendor          Site Owner / Moderator       Visitor          Vendor
(submit)   →    (approve)               →    (find)      →    (get lead)
   ↑                  ↑                          ↓                ↓
   └──────────────────┴────── feedback loop ─────┴────────────────┘
              (renewal cron + review + reply + recommend)
```

### Loop A - the submission loop

1. **Vendor** opens `/add-listing/` and walks the 4-6 step submission wizard. On submit, the listing transitions to `pending` (if moderation is on) and the `wb_listora_listing_submitted` action fires with the submitter context.
2. **Notifications** listener (gated to skip migration context) sends the operator the new-submission email.
3. **Site Owner or Moderator** opens the appropriate admin page, reviews the listing in full visitor-perspective context (admin notes panel on top shows submitter IP + anti-spam flags + prior approval count), clicks Approve.
4. The status transitions to `publish`. `wb_listora_listing_status_changed` fires. The owner gets a `listing_approved` email. The denormalized `search_index` row is rebuilt.
5. **Visitor** searches the directory, hits the now-public listing, contacts via Free contact form or Pro Lead Form.
6. **Vendor** gets the lead email with Reply-To set to the visitor - one click and the reply goes straight to the visitor's inbox.
7. **Visitor** writes a review post-experience. **Moderator** approves it. **Vendor** gets a `review_reply` email + the rating updates the listing's aggregated stars.
8. Seven days before expiry, the `wb_listora_listing_expiring` cron fires. **Vendor** gets the reminder + opens the dashboard + clicks Renew + `POST /listings/{id}/renew` extends the expiration. The loop continues.

### Loop B - the claim loop

1. **Visitor** searches, finds a listing for a business she runs (added by someone else - the operator at bulk-migration time, or a community member).
2. She does not have an account yet, so she registers via the claim modal (or logs in if she does).
3. **Visitor → Vendor** transition begins: she clicks "Claim this business" + uploads proof of ownership.
4. The claim hits `listora_claims` table + fires `wb_listora_listing_claimed` action.
5. **Site Owner or Moderator with claims scope** reviews proof, approves. `post_author` transfers. Free's `_listora_is_claimed` flag is set + Pro's search-index syncs via the same action.
6. The newly-minted **Vendor** receives `claim_approved` email + sees the listing in her dashboard.
7. She is now in Loop A from step 4 onward.

### Loop C - the reverse-marketplace loop (Pro only)

1. **Visitor** has a specific need ("catering for 100 guests next weekend, Brooklyn"). The operator has added the Post a Need block to a page.
2. Visitor posts the need on the page. The need surfaces at `/needs/` (the auto-created CPT archive).
3. **Vendors** in matching categories filter `/needs/` and respond with quotes. The Saved Searches feature can auto-notify a Vendor of new needs in their category.
4. **Visitor** sees responses in her dashboard → **Needs** tab. Picks a winner. The two communicate via the message thread.
5. Post-job, the Visitor returns and writes a review on the Vendor's listing. Back to Loop A from step 7.

### Loop D - the moderation loop

1. **Site Owner** flips on Pro Moderators + invites a teammate with scope (e.g. Reviews only).
2. **Moderator** receives the assignment email, logs in, sees the gated sidebar.
3. Each pending review fires its own `wb_listora_review_status_changed` event when triaged - notifications go to the reviewer and the listing owner.
4. **Audit Log** (Pro) records every transition + the moderator who performed it. **Site Owner** spot-checks weekly.
5. When the queue size grows beyond what one moderator can carry, the Site Owner invites a second moderator with a different scope (e.g. Listings) - no role conflict because the capabilities are additive and isolated by scope.

## Reading order

A new joiner walking into the team should read in this order:

1. **This file (`journey-book.md`)** - 10 minutes - get the model
2. **[persona-profiles.md](../marketing/07-brand-assets/persona-profiles.md)** - 15 minutes - meet the cast
3. **[lifecycle.md](lifecycle.md)** - 20 minutes - see the channels + KPIs per stage
4. **[task-based-journeys.md](task-based-journeys.md)** - read just the 2-3 tasks relevant to today's work
5. The 4 persona deep-dive journeys ([site-owner](site-owner.md) / [listing-owner](listing-owner.md) / [visitor](visitor.md) / [moderator](moderator.md)) - read the one for your immediate audience

After that, the [feature matrix](../feature-matrix.md) is the indexed reference.

## The promise this book is built around

A directory plugin that earns its place by removing real work. The Site Owner does not glue payments together (7 integrations ship). The Vendor does not learn WordPress (frontend dashboard + wizard). The Visitor does not sign up to browse (anonymous browsing is unlimited). The Moderator does not see what she should not (capability-gated sidebar). The Agency does not fork templates (WooCommerce-style overrides + 226 hooks + 120 REST routes). Every journey in this book is a measurement of how well we keep that promise.

When a feature change crosses one of these journeys, update the journey in the same PR. The journey is the contract.

## Related

- [README.md](README.md) - this directory's index
- [lifecycle.md](lifecycle.md) - macro view + channels + KPIs
- [task-based-journeys.md](task-based-journeys.md) - 10 specific task flows
- [site-owner.md](site-owner.md) / [listing-owner.md](listing-owner.md) / [visitor.md](visitor.md) / [moderator.md](moderator.md) - persona deep-dives
- [persona-profiles.md](../marketing/07-brand-assets/persona-profiles.md) - full marketing-grade persona briefs
- [feature-matrix.md](../feature-matrix.md) - capability index across Free + Pro
