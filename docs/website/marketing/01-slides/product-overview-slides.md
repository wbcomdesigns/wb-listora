# WB Listora — Product Overview Deck (15 Slides)

Sales calls, conference talks, partner briefings. Each slide is one idea. Speaker notes are for the presenter, not the screen.

---

## Slide 01 — Title

**Heading:** Run a fully-monetizable business directory on WordPress.

**Sub:** WB Listora — Free + Pro

**Speaker notes:** Open with the outcome, not the feature list. "Fully-monetizable" is the differentiator from every other directory plugin that stops at the public directory and leaves revenue as homework.

**Suggested visual:** `home-frontend.png` — full-bleed screenshot of a populated directory homepage.

---

## Slide 02 — The Problem

**Heading:** Every directory plugin makes you choose.

**Sub:** Either you get a functional public directory OR you get a business model. Rarely both. Never without duct tape.

**Bullets:**
- Competitors ship separate "monetization add-ons" that break on major WP updates
- Payment integrations are bolted on, not designed in
- Vendor dashboards push business owners into wp-admin — they leave

**Speaker notes:** This is the empathy slide. Let the room nod. Don't name competitors here — the comparison slide does that later.

**Suggested visual:** Simple two-column diagram: "Public directory" on one side, "Revenue" on the other, with a wall between them.

---

## Slide 03 — The Solution

**Heading:** Free covers the public site. Pro adds the business model.

**Sub:** Both ship today. Both are the same codebase. No add-on fragmentation.

**Bullets:**
- Free: complete directory, 11 Gutenberg blocks, search + reviews + claims + spam protection
- Pro: credits, plans, lead capture, verification, comparison, moderators, needs marketplace
- Built on native WordPress APIs — no page builders, no jQuery soup

**Speaker notes:** Emphasize that Free is genuinely complete — not a crippled demo. You could run a real city guide on Free alone and never need Pro.

**Suggested visual:** `why-wb-listora.png`

---

## Slide 04 — Who It's For

**Heading:** Built for three audiences at once.

**Sub:** The operator, the vendor, the visitor. All three have to win or the directory dies.

**Bullets:**
- **Site operators** — setup wizard, moderation tools, analytics, payment integrations, white-label
- **Vendors / listing owners** — frontend dashboard, lead inbox, renewal, zero wp-admin
- **Visitors / buyers** — fast search, real reviews, comparison, saved searches, needs marketplace

**Speaker notes:** Most plugins optimize for the operator and forget the other two. A directory where vendors hate submitting and visitors can't find anything fails regardless of the backend.

**Suggested visual:** Three-column layout, one icon per audience.

---

## Slide 05 — Free: What You Get

**Heading:** A complete public directory. No credit card required.

**Sub:** 11 native Gutenberg blocks. Works out of the box.

**Bullets:**
- Full-text + geo + faceted search, "Search this area" map-bounds drag
- 5-star reviews, helpful votes, owner replies, business claims
- Frontend submission wizard with conditional fields, draft saving, email verification
- 6-layer anti-spam (honeypot, rate limits, CAPTCHA, Akismet, blacklist, URL density)

**Speaker notes:** Hit the numbers: 9 demo packs, 10 Schema.org types, 55 REST endpoints, 8 WP-CLI commands. These are all in Free. Don't let the audience assume "Free = demo."

**Suggested visual:** `home-frontend.png` or `search-and-filters.png`

---

## Slide 06 — The Setup Experience

**Heading:** From install to operational: 30 minutes.

**Sub:** The setup wizard does the scaffolding so you can focus on content.

**Bullets:**
- 6-step wizard: pick listing types, set location, configure maps, create pages, load demo data, done
- Auto-creates Add Listing / My Listings / Directory pages with the correct blocks already in place
- 9 demo packs seed realistic content for restaurants, hotels, real estate, jobs, and more

**Speaker notes:** The 30-minute claim is conservative — experienced WP users do it in 15. Let the audience know the demo data looks real, not lorem-ipsum. That matters when showing clients.

**Suggested visual:** `setup-wizard-step1.png`

---

## Slide 07 — Pro: The Business Model Layer

**Heading:** Charge vendors. Capture leads. Verify quality.

**Sub:** Pro is the revenue infrastructure Free was designed to connect to.

**Bullets:**
- Credit-based pricing plans with Hold-and-Commit activation — no partial charges on failed listings
- Lead forms replace the contact form with analytics, custom fields, and CRM-ready data
- Verification badges + audit log + moderator team for trust at scale

**Speaker notes:** "Hold-and-Commit" is worth explaining: credits are held when a plan is selected, committed when the listing activates. If activation fails, the hold cancels. No vendor gets charged for a listing that never went live.

**Suggested visual:** `credits-and-plans.png`

---

## Slide 08 — Payment Integrations

**Heading:** Vendors pay through whatever gateway they already use.

**Sub:** Five SDK adapters + Stripe and PayPal direct — all managed through one credit ledger.

**Bullets:**
- WooCommerce, WooCommerce Subscriptions, MemberPress, Paid Memberships Pro, WooMemberships
- Stripe direct (webhook receiver) + PayPal direct (webhook receiver)
- Every transaction — regardless of origin — flows through the same audit trail

**Speaker notes:** The SDK is bundled in Free, so Pro doesn't need its own copy. Every top-up from any gateway automatically resumes paused listings. No silent failures.

**Suggested visual:** `payment-webhooks-settings.png`

---

## Slide 09 — The Needs Marketplace

**Heading:** A directory where buyers post and businesses respond.

**Sub:** Most directories are one flywheel. WB Listora Pro adds a second.

**Bullets:**
- Buyers post a need: "Looking for a photographer in Austin under $500"
- Businesses filter needs by type, urgency, budget, and location — respond with a quote
- The directory operator benefits from both sides of the marketplace

**Speaker notes:** No other WordPress directory plugin has this. It's the strongest differentiator in the Pro pitch. Use it to close the gap with purpose-built marketplace software like Thumbtack or Bark.

**Suggested visual:** `needs-marketplace.png`

---

## Slide 10 — Search That Scales

**Heading:** Built for 100,000 listings, not 100.

**Sub:** A denormalized search index — not WP_Query. It changes everything.

**Bullets:**
- Dedicated `listora_search_index` table with full-text indexing — fast at any catalog size
- Geo radius search (Haversine) with "Search this area" drag-to-update bounds
- Action Scheduler vendored in Free — background jobs don't miss on shared hosting

**Speaker notes:** The "built for scale" message matters for agencies and marketplace builders who've been burned by directory plugins that fall over at 5,000 listings. Lead with this for developer/technical audiences.

**Suggested visual:** `search-and-filters.png` or `directory.png`

---

## Slide 11 — Modern WordPress, Not Legacy WordPress

**Heading:** Native blocks. Interactivity API. REST-first. No shortcodes.

**Sub:** Built for where WordPress is going, not where it was in 2016.

**Bullets:**
- 11 Free blocks + 5 Pro blocks — all Gutenberg-native, all customizable in the editor
- Single Interactivity API store — no jQuery, no separate page builder dependency
- 226 documented hooks (120 actions + 106 filters) + 55 REST endpoints for headless / mobile

**Speaker notes:** For developer audiences, this is the most important slide. "No shortcodes" means no hidden rendering dependencies, no fighting with the block editor, no theme lock-in.

**Suggested visual:** `blocks-overview.png` or `directory-page-blocks.png`

---

## Slide 12 — Trust + Moderation

**Heading:** Real trust signals for real directories.

**Sub:** Reviews, claims, badges, moderators, audit trail — all in one plugin.

**Bullets:**
- Business claims transfer ownership cleanly — no duplicate listings
- Multi-criteria reviews (Pro) + photo reviews (Pro) for depth that matters
- Moderators team (Pro) with scoped capabilities — not everyone needs admin access
- Audit log (Pro) records every approval, rejection, and change with actor + timestamp

**Speaker notes:** Trust infrastructure is what separates directories people use from directories people browse once. The audit log is the unsung hero for compliance-conscious operators.

**Suggested visual:** `moderation-queue.png` or `audit-log-admin.png`

---

## Slide 13 — Integrations + Compatibility

**Heading:** Fits into the WordPress ecosystem you already have.

**Sub:** Works with the tools your clients expect.

**Bullets:**
- BuddyPress activity sync — listing actions appear in the social activity stream
- Google Maps API (Pro) with custom styles + marker clustering
- Multisite, RTL, headless (Next.js / Astro / mobile app via REST)
- WordPress 6.9+ / PHP 7.4+ / works with any modern block theme

**Speaker notes:** The multisite + headless claims matter for agencies doing enterprise projects. The BuddyPress integration matters for community-driven directories.

**Suggested visual:** `buddypress-activity.png` or `google-maps.png`

---

## Slide 14 — Pricing

**Heading:** Free is free. Pro is at wblistora.com.

**Sub:** See current pricing at {{PRICING_PLACEHOLDER}}.

**Bullets:**
- Free: download from wblistora.com — no license key, no expiration
- Pro: license from wblistora.com — adds 32 feature modules on top of Free
- Both plugins are private (Wbcom Designs only) — distributed directly, not via wordpress.org

**Speaker notes:** Don't improvise pricing numbers in a live presentation. Direct the audience to wblistora.com for the current offer. Pricing changes; this slide does not.

**Suggested visual:** `pro-license.png`

---

## Slide 15 — Get Started

**Heading:** Your directory is 30 minutes away.

**Sub:** Start with Free. Add Pro when you're ready to monetize.

**Bullets:**
- Download WB Listora from wblistora.com (Free + Pro both live there)
- Run the setup wizard — pick types, load demo data
- Launch your directory — upgrade to Pro when you need the business model layer

**Speaker notes:** End with a clear next step, not a question. The call to action is "install Free now." Pro is the natural next conversation after they've seen the directory working.

**Suggested visual:** `home-frontend.png`
