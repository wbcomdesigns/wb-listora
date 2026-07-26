# Customer Lifecycle Journey

The macro view of how a real person becomes a Site Owner, a Vendor, or a Visitor in the WB Listora world - and what we do at every stage to keep them moving forward. This is the journey marketing, sales, and customer success share. It is the canvas the 4 persona journeys are painted on.

## The model

Awareness → Consideration → Purchase → Onboarding → Activation → Retention → Advocacy.

The same arc applies to a Site Owner buying Pro, a Vendor signing up to a directory, and a Visitor returning to the same directory month after month. Different surfaces, same shape.

## Where each persona journey lives in the lifecycle

```
Stage | Site Owner / Agency | Listing Owner | Visitor | Moderator
-----------|------------------------------------|------------------------|-------------------------|--------------------
Awareness | Google / YouTube / WP Tavern | Search the directory | Google a category | Invitation only
Consider | Compare with Directorist + GeoDir | Read existing reviews | Skim grid + filters | (n/a)
Purchase | Buy Pro at wblistora.com | Submit / Claim / Plan | (free to browse) | (n/a)
Onboarding | Setup wizard + demo packs | Submission wizard | First search | Caps assigned
Activation | First vendor + first review | First lead / first 5* | First contact-form fill | First 10 approvals
Retention | Audit log + analytics weekly | 7-day renewal cron | Saved searches alerts | Daily triage rhythm
Advocacy | Case study / white-label resell | Recommends to peers | Shares listings | Tier-up to senior mod
```

## Stage 1 - Awareness

**What the customer is doing / thinking / feeling**

- Site Owner: "I want to monetize a niche I know - vegan restaurants, medical spas, indie bookshops - by building a directory people in that niche actually use."
- Listing Owner: "My business needs more local visibility. I am searching for 'add my restaurant to [city] guide' or I clicked a 'List your business' CTA in the directory's nav."
- Visitor: "I need an Italian restaurant near me tonight." Types it into Google. Lands on a directory page that already ranks.

**Listora touchpoint**

- Public marketing site at wblistora.com (Site Owner)
- The directory's homepage, top-rated grid, featured carousel (Visitor + Listing Owner)
- Schema.org JSON-LD on every listing detail page (LocalBusiness, Restaurant, Hotel - 10 schema types). Google rich-results eligibility is on by default
- 9 demo packs (restaurant, hotel, real-estate, job-board, general, classified, education, healthcare, place) so a brand new install shows traffic-worthy content from minute one

**Channels marketing should activate**

- WordPress-focused channels: WP Tavern, Post Status, WP Builds, agency Slacks, the WP Entrepreneurs Facebook group
- YouTube tutorial videos on building niche directories
- Long-form blog posts that target "directory plugin" comparison search intent
- For Visitor awareness: the directory's own SEO is the work - we don't market the plugin to visitors

**KPI**

- Site Owner: monthly organic sessions on wblistora.com, demo-pack installs (`wp listora demo seed --pack=*`)
- Visitor: organic traffic per listing, Google CTR on schema-enhanced results

**Common failure modes + fixes**

| Failure | Fix |
|---|---|
| Site Owner reaches the marketing page but cannot tell what makes Listora different from Directorist / GeoDirectory / WPBDP / ListingPro | The comparison page (`docs/website/comparison.md`) leads with one differentiator per competitor. Update every release |
| Visitor lands on an empty directory and bounces | Operator did not load a demo pack. Onboarding checklist nudges them to run `wp listora demo seed` before launch |
| Schema markup invisible to Google | Pages need to be in the sitemap. Setup wizard does this; if disabled the operator must add manually |

## Stage 2 - Consideration

**What the customer is doing / thinking / feeling**

- Site Owner: comparison-shopping. Reading feature matrices. Watching demo videos. Wondering whether Free is enough or they need Pro from day one
- Agency: reading the REST docs and the hook reference. Cloning the Free zip and grepping for `do_action`. Asking "can I white-label this?"
- Listing Owner: looking at the existing listings - do they look polished? Will my business fit here? Is there traffic?
- Visitor: scanning the grid, applying filters, deciding which 2-3 listings deserve a closer look

**Listora touchpoint**

- [Feature Matrix](../feature-matrix.md) - the Free vs Pro grid at a glance
- [Why WB Listora?](../why-wb-listora.md) - the architectural reasoning
- The directory's own quality - photo coverage, review density, listing freshness
- Compare Listings block (Pro) for Visitor side-by-side decision
- Featured carousel for Listing Owner social-proof

**Channels**

- Email nurture sequence from the Site Owner-facing newsletter
- Developer-targeted content for Agency (architecture write-ups, hooks reference)
- The directory itself for Visitor + Listing Owner - the UI is the pitch

**KPI**

- Site Owner: Free → Pro conversion rate (Pro license activations vs Free installs)
- Visitor: bounce rate on the directory homepage, filter engagement rate
- Listing Owner: click-through on "Add Listing" CTA from listing detail pages

**Common failure modes + fixes**

| Failure | Fix |
|---|---|
| Site Owner cannot find the answer "does this scale to 50K listings?" | Performance budgets page + the case for the denormalized `search_index` table. 100K-readiness sprint already shipped (Phase 1-3, 2026-05-07) |
| Agency cannot evaluate without source access | Free is openly distributable (private to Wbcom but installable on any site). REST + hooks are documented. White-label flag exists in Pro |
| Visitor compare flow needs Pro | The free Compare-via-favorites fallback still works. Pro upgrade is a Site Owner decision, not Visitor's |

## Stage 3 - Purchase

**What the customer is doing / thinking / feeling**

- Site Owner: "I have decided. I am buying the Pro license. Where do I pay and how fast can I activate?"
- Listing Owner: "The plan costs N credits. Do I have a balance? If not, where do I top up?" If Free directory: no purchase decision, only the submit decision
- Visitor: free to browse forever. No purchase

**Listora touchpoint**

- Pro license purchase at wblistora.com (NOT wordpress.org - WB Listora is privately distributed)
- The license-and-updates server at wblistora.com powers auto-updates inside the customer's WP admin
- For Listing Owner: the credit-purchase flow runs through one of 7 payment integrations - Stripe (direct), PayPal (direct), WooCommerce, WooSubscriptions, MemberPress, Paid Memberships Pro, WooMemberships
- Pricing Plans page renders the operator's plans with credit cost + duration + entitlements

**Channels**

- Site Owner: wblistora.com pricing page, email receipt with license key and download link
- Listing Owner: the operator's own checkout - the gateway is the operator's chosen one

**KPI**

- Site Owner: average revenue per Pro install, churn at 12 / 24 / 36 months
- Listing Owner: average plan revenue per active vendor, credit-pack repurchase rate

**Common failure modes + fixes**

| Failure | Fix |
|---|---|
| Site Owner cannot find the Pro download after purchase | License email links to the download URL + the activation guide. Auto-update kicks in once the license key is entered |
| Listing Owner abandons checkout because the credit math is unclear | Plan picker on the submission wizard shows "X credits = $Y from your current balance" in real time |
| Wrong gateway assumption (Razorpay / EDD bridge) | Listora does not ship Razorpay or an EDD bridge. The 7 integrations above are the supported set |

## Stage 4 - Onboarding

**What the customer is doing / thinking / feeling**

- Site Owner: "I want to see something working in 30 minutes. I do not want to read a 40-page manual."
- Listing Owner: "I just want to submit my business and get on with my day."
- Visitor: "First search. Did it work? Are the results good?"
- Moderator: "I just got invited. What does my queue look like?"

**Listora touchpoint**

- [Setup Wizard](../getting-started/setup-wizard.md) - 6 steps: type → location → maps → pages → demo → done. Auto-creates Add Listing, My Listings, Directory pages
- [Frontend submission wizard](../features/frontend-submission.md) - 4-6 steps with auto-save drafts so the vendor can leave mid-form and come back
- Visitor search-and-facet experience powered by the denormalized `search_index` table + 6 taxonomies
- Moderator: capability-gated sidebar shows only `All Listings`, `Reviews`, `Claims`, `Reports` - everything else is hidden

**Channels**

- In-product onboarding checklist on first admin visit
- Welcome email after activation
- Tooltips in the setup wizard
- Demo packs that ship with the plugin (`wp listora demo seed --pack=restaurant --with-users --reindex`)

**KPI**

- Site Owner: time-to-first-listing (target: under 30 minutes from install)
- Listing Owner: submission completion rate (target: > 70%)
- Visitor: zero-result rate (target: < 5%)
- Moderator: first-approval time (target: same day as invite)

**Common failure modes + fixes**

| Failure | Fix |
|---|---|
| Setup wizard fails because pages already exist | Wizard detects existing pages and links them instead of recreating |
| Vendor submission fails on a required field that was hidden by conditional logic | Submission step skip-list was wrong - regression covered by journey `submission-wizard-end-to-end.md` |
| Visitor sees no markers on the map | Operator did not enable map provider. Free uses Leaflet + OSM (no key); Pro adds Google Maps (key required) |
| Moderator sees admin screens they should not | Capability gating bug. Covered by `role-cap-matrix` journey |

## Stage 5 - Activation

**What the customer is doing / thinking / feeling**

- Site Owner: "I have my first paying vendor. My first 5-star review. I have proof of life."
- Listing Owner: "I got my first lead through the directory. The receipts are arriving in my inbox."
- Visitor: "I contacted a business through the directory and got a useful reply. This site is real to me now."
- Moderator: "I cleared a 30-item queue in 20 minutes. I know the rhythm."

**Listora touchpoint**

- For Site Owner: Audit Log (Pro) recording the first vendor approval + first revenue event. Email Log confirming notifications fire
- For Listing Owner: Lead Form (Pro) or the free Contact Form delivering the first inquiry email with Reply-To set to the visitor
- For Visitor: a clean detail page with the click-phone / get-directions / contact-owner flow + the post-experience review prompt
- For Moderator: bulk-moderate REST endpoint (`POST /listora/v1/listings/bulk-moderate`, up to 100 IDs) + per-row Approve / Reject actions on the Listings list

**Channels**

- Transactional emails from Listora (14 notification templates)
- The dashboard at `/my-dashboard/` (canonical Pro slug) or `/my-listings/` (legacy Free slug)
- In-product activity counters (pending counts in the moderator dashboard)

**KPI**

- Site Owner: weeks-to-first-revenue (Pro), first-month MRR
- Listing Owner: leads per listing per month
- Visitor: contact-form fill rate, review submission rate
- Moderator: items per hour throughput

**Common failure modes + fixes**

| Failure | Fix |
|---|---|
| Listing Owner submits but listing flips to "Awaiting Credits" with no explanation | Submission response carries `paused: true` + the exact credits-short + Buy Credits CTA inline (post 2026-05-13 refactor) |
| Visitor's contact form is rate-limited | Per-IP-per-listing 3/hr + per-listing 20/day caps. Adjustable via `wb_listora_contact_form_per_listing_daily_cap` filter |
| Moderator approves but submitter never gets the email | Notifications listener listens on the canonical `wb_listora_listing_status_changed` action (fixed in 0aa62ca, 2026-04-30) |

## Stage 6 - Retention

**What the customer is doing / thinking / feeling**

- Site Owner: "It is week 12. The directory is producing revenue. I need to keep vendors renewing and keep search quality high."
- Listing Owner: "My listing renews next week. The 7-day reminder just arrived. I have 1 click to renew."
- Visitor: "I have come back twice. I want a Saved Search so the directory tells me when something new matches."
- Moderator: "I have a daily 15-minute rhythm. It works."

**Listora touchpoint**

- 7-day renewal reminder cron fires via the `wb_listora_listing_expiring` event (Action Scheduler-driven since the Phase 1 migration). Renewal itself is manual via `POST /listings/{id}/renew` - there is no auto-renew today
- Audit Log (Pro) for Site Owner anomaly review
- Notification Digest (Pro) for daily / weekly bundle emails
- Saved Searches (Pro) for Visitor recurring alerts
- Moderation queue rhythm: each pending item type lives on its own admin page (Listings / Reviews / Claims / Reports) - there is no single unified "Moderation Queue" admin page

**Channels**

- The 14 transactional email templates
- The dashboard
- Pro Digest (opt-in)

**KPI**

- Site Owner: monthly vendor renewal rate, MRR retention
- Listing Owner: own renewal cadence (does the reminder convert?)
- Visitor: return-visit rate, Saved Search subscribers
- Moderator: queue clearance rate over time

**Common failure modes + fixes**

| Failure | Fix |
|---|---|
| Listing Owner missed the 7-day reminder and lost leads | The reminder is the only proactive nudge. No 1-day reminder. Set a calendar entry on renewal day |
| Site Owner sees stale Featured carousel | Featured rotation runs via Action Scheduler - the `wb_listora_featured_rotation` cron is bullet-proof since the Phase 1 migration |
| Visitor's Saved Search produces too many alerts | Saved Search dashboard tab has per-search toggle for alert frequency |

## Stage 7 - Advocacy

**What the customer is doing / thinking / feeling**

- Site Owner: "I love this product. I want to recommend it. I am writing a case study, building a course, or selling a directory-template to other operators."
- Agency: "I am white-labelling Listora for client B because client A loves the result. I am pitching this as a productized package."
- Listing Owner: "I recommend the directory to other businesses in my city."
- Visitor: "I shared this listing on social. I write reviews here."

**Listora touchpoint**

- White-label (Pro) so the agency / reseller can ship under their brand
- Outgoing webhooks (Pro) so customers can build their own integrations
- Public REST API (58 Free + 73 Pro) so headless / mobile clients can be built on top
- Share buttons on listing detail pages so Visitors push listings into their own networks

**Channels**

- Case-study collaboration (Site Owner + marketing team co-author)
- Customer-success retention emails that include "refer a friend" CTAs
- Public Slack / Discord / community spaces (when active)

**KPI**

- Site Owner: referral attribution, NPS, public testimonials
- Listing Owner: cross-vendor referrals
- Visitor: review submissions per visitor, social shares per listing

**Common failure modes + fixes**

| Failure | Fix |
|---|---|
| Site Owner pitches white-label but cannot strip the "WB Listora" branding from admin | White Label (Pro) feature toggles brand color + logo. Setup wizard skip-flag is on the roadmap |
| Agency wants a custom field that does not exist | 259 hooks (133 actions + 126 filters) give the extension point. WooCommerce-style template overrides keep customizations safe across updates |
| Visitor wants to delete their account | Self-service deletion is a manual support request today (`hello@wblistora.com`). Roadmap item |

## How to use this doc

- Marketing: pick the stage your campaign targets. Activate the channels listed. Measure against the KPI
- Product: when a feature is proposed, ask "which lifecycle stage does it improve?" If the answer is "I don't know" - the brief is incomplete
- Customer Success: when a customer churns, walk back through the stages. Where did they stall? That is the next product investment
- Engineering: when a bug is filed, anchor it to a stage. Bug at Onboarding (setup wizard) is higher priority than bug at Advocacy (white-label edge case)

## Related

- [Persona Profiles](../marketing/07-brand-assets/persona-profiles.md) - the 5 personas with goals, pains, and tone
- [Site Owner Journey](site-owner.md) - operator end-to-end
- [Listing Owner Journey](listing-owner.md) - vendor end-to-end
- [Visitor Journey](visitor.md) - end customer end-to-end
- [Moderator Journey](moderator.md) - capability-scoped team member
- [task-based-journeys.md](task-based-journeys.md) - 10 specific "I want to X" flows
- [journey-book.md](journey-book.md) - the bound atlas
