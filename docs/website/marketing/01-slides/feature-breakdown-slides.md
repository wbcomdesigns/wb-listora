# WB Listora - Feature Deep-Dive Deck (20 Slides)

Per-feature slides for product demos, technical sales calls, and partner webinars. Each slide covers one major feature area. Lead with the customer outcome, then the mechanics.

---

## Slide 01 - Setup Wizard

**Heading:** Zero to operational in 30 minutes.

**Sub:** The 6-step wizard handles all the scaffolding automatically.

**Bullets:**
- Step 1: Choose listing types (Restaurant, Hotel, Real Estate, Job Board, Place, Classified, …)
- Step 2: Set default location and timezone
- Step 3: Configure maps (Leaflet/OSM free; Google Maps API with Pro)
- Step 4: Auto-create Add Listing, My Listings, Directory pages with correct blocks
- Step 5: Load demo data - 9 verticals, 128+ seeded listings
- Step 6: Done - your directory is live

**Screenshot:** `setup-wizard-step1.png`

---

## Slide 02 - Listing Types + Custom Fields

**Heading:** Every listing type gets exactly the fields it needs.

**Sub:** Restaurants get menus and cuisine. Hotels get room types and amenities. Jobs get salary and schedule. All configurable - no code required.

**Bullets:**
- Built-in type templates: restaurant, hotel, real estate, job board, place, classified, education, healthcare, general
- Per-type custom field framework: text, textarea, select, checkbox, URL, image, social links, hours, price range
- Custom listing types with icon, label, schema type, and field groups
- Conditional fields - show/hide based on other field values

**Screenshot:** `type-editor.png` and `listing-types.png`

---

## Slide 03 - Search + Filters

**Heading:** Search that actually finds things - at any catalog size.

**Sub:** Denormalized index. Faceted filters. Geo radius. All updating without a page reload.

**Bullets:**
- Full-text search on a dedicated `listora_search_index` table - not `LIKE %query%` on post_content
- Faceted filters: category, location, feature/amenity, listing type - stack them freely
- Geo search: "Near Me" (browser geolocation), radius slider, "Search this area" drag-to-update
- URL state preserved so visitors can share or bookmark any filtered view
- Pro adds: advanced search builder (custom field filters), saved searches with email alerts, infinite scroll

**Screenshot:** `search-and-filters.png` and `advanced-search.png`

---

## Slide 04 - Listing Detail + Map

**Heading:** A listing page that earns the click.

**Sub:** All the information visitors need. None of the clutter they don't.

**Bullets:**
- Photo gallery, business hours with timezone, contact info, social links (7 platforms)
- Embedded map with draggable pin - Leaflet/OSM in Free, Google Maps with custom styles in Pro
- Schema.org JSON-LD - 10 types including LocalBusiness, Restaurant, Hotel, JobPosting
- Related listings carousel, services tab, reviews tab - all in the same block
- Pro adds: verification badge, lead form, multi-criteria reviews, quick-view modal

**Screenshot:** `contact-form-listing.png` and `google-maps.png`

---

## Slide 05 - Frontend Submission Wizard

**Heading:** Vendors submit listings without ever seeing wp-admin.

**Sub:** A multi-step form that saves drafts, validates in real time, and handles media uploads gracefully.

**Bullets:**
- Steps: Basics → Details → Media → Contact → Hours → Plan (Pro)
- Draft auto-saves at each step - returning vendors resume where they left off
- Image gallery with drag-and-drop upload, map pin draggable to exact storefront
- Spam protection built in: honeypot, rate limiting, CAPTCHA, and Akismet on every submission
- Pro adds: duplicate detection at submit, plan picker with credit cost preview

**Screenshot:** `frontend-submission.png` and `duplicate-check-step.png`

---

## Slide 06 - Reviews System

**Heading:** Reviews that actually inform purchase decisions.

**Sub:** Star ratings with written reviews, owner replies, helpful votes, and moderation controls.

**Bullets:**
- 5-star rating with required text review, auto-approve or moderation-queue setting
- Owner public replies - builds trust, improves SEO
- Helpful votes with milestone notifications ("10 people found this review helpful")
- Report-a-review workflow - flagged reviews queue for moderator action
- Pro adds: multi-criteria ratings (rate Food, Service, Value, Ambiance separately), photo reviews

**Screenshot:** `reviews-system.png` and `multi-criteria-reviews.png`

---

## Slide 07 - Business Claims

**Heading:** Let businesses own their listing without creating a duplicate.

**Sub:** The claim flow is clean, admin-gated, and traceable.

**Bullets:**
- "Claim this business" CTA on every listing detail page
- Owner uploads proof - admin reviews and approves or rejects
- On approval: `post_author` transfers cleanly, `_listora_is_claimed` flag set, search index updated
- Claimed badge appears on listing card and detail page
- Admin sees all pending claims in the moderation queue

**Screenshot:** `business-claims.png` and `claims-admin.png`

---

## Slide 08 - Anti-Spam (6 Layers)

**Heading:** Six layers of spam protection - all on by default.

**Sub:** Spam has to defeat all six. Most bots don't make it past layer two.

**Bullets:**
- Layer 1: Honeypot field on every form
- Layer 2: Per-IP sliding-window rate limits (configurable per form type)
- Layer 3: reCAPTCHA v3 OR Cloudflare Turnstile (GDPR-friendly alternative)
- Layer 4: Akismet review + claim content analysis
- Layer 5: Keyword blacklist (configurable per site)
- Layer 6: URL density cap per submission event

**Screenshot:** `spam-protection-settings.png`

---

## Slide 09 - User Dashboard

**Heading:** Vendors manage everything from one frontend page.

**Sub:** No wp-admin logins. No support tickets for basic listing edits.

**Bullets:**
- Tabs: Listings, Reviews, Favorites, Claims, Saved Searches (Pro), My Needs (Pro), My Responses (Pro)
- Per-listing row actions: Edit, Renew, Feature, Deactivate - one click each
- Status visible at a glance: Live, Pending, Expired, Awaiting Credits, Deactivated
- Lead inbox (Pro) - every contact form fill and lead form fill with Reply-To
- 7-day expiration reminder email fires automatically

**Screenshot:** `user-dashboard.png` and `listing-lifecycle-dashboard.png`

---

## Slide 10 - Notifications + Email (28 Templates)

**Heading:** Every important event has an email. Every email is overrideable.

**Sub:** 15 Free templates, 13 Pro templates - all WooCommerce-style overrideable from your theme.

**Bullets:**
- Free events: new submission, approved, rejected, expired, renewed, claimed, claim approved, review received, helpful-vote milestone, draft reminder, contact form, password reset, admin new-listing notice
- Pro adds: plan activated, paused listing, listing resumed, lead form notification, saved-search alert, digest bundle, audit event, and more
- Override any template: copy to `{theme}/wb-listora/templates/emails/{name}.php`
- Email log admin page - see every sent email, delivery status, timestamps

**Screenshot:** `email-log-page.png` and `email-listing-approved.png`

---

## Slide 11 - Credits + Pricing Plans (Pro)

**Heading:** Charge vendors on your terms, not the plugin's.

**Sub:** Define credit packs. Define plans. Vendors buy credits and activate plans. You collect.

**Bullets:**
- Site owner defines credit packs (e.g. 10 credits / $40, 50 credits / $150, 200 credits / $500)
- Plans have credit cost + duration + entitlements (featured rotation, verified badge eligibility, lead form)
- Hold-and-Commit activation: credits held at plan selection, committed when listing activates - no partial charges
- Paused listings resume automatically when vendor tops up - no manual intervention
- Coupons: percentage or flat discount, single-use or multi-use, expiry date

**Screenshot:** `credits-and-plans.png` and `pricing-plans-admin.png`

---

## Slide 12 - Lead Forms (Pro)

**Heading:** Contact forms that track conversions and integrate with your stack.

**Sub:** Lead forms replace the basic contact form with analytics, custom questions, and CRM-ready delivery.

**Bullets:**
- Per-listing lead form with custom fields - add questions relevant to the listing type
- Every submit tracked: timestamp, visitor metadata, listing ID, plan tier
- Owner receives email notification with Reply-To set - one-click reply goes to the visitor
- Per-listing and directory-wide analytics: fill rate, top-converting listings, lead volume by day
- Contact-form stays in Free; Lead Form activates in Pro as a feature toggle

**Screenshot:** `lead-forms.png`

---

## Slide 13 - Verification Badges (Pro)

**Heading:** A verified badge is worth more than a paragraph of copy.

**Sub:** Define badge types. Assign to listings. Badge appears on card, detail, and search facets.

**Bullets:**
- Admin creates badge types: Verified Owner, Top Rated, Editor's Choice, etc.
- Each badge is configurable: icon, color, criteria description, optional expiration
- Assigned badges render on listing cards, detail pages, and within search result facets
- Verification badge eligibility can be tied to a pricing plan (e.g. "Premium plan" gets a badge)
- Audit log records every badge grant and removal with actor + timestamp

**Screenshot:** `verification-badges.png`

---

## Slide 14 - Comparison (Pro)

**Heading:** Let visitors compare their top picks side by side.

**Sub:** Up to 4 listings, floating bar tracking their selection across the whole site.

**Bullets:**
- Heart-save listings from any card or detail page - floating comparison bar appears automatically
- Compare page renders up to 4 listings in a configurable table: core info, pricing, features, ratings, services, hours
- Comparison state persists via localStorage - visitors can navigate and come back
- Share the comparison URL - each state is a unique URL
- The `listora-pro/comparison` block is the page's only dependency - no shortcode

**Screenshot:** `compare-listings.png` and `comparison.png`

---

## Slide 15 - Needs Marketplace (Pro)

**Heading:** Buyers post needs. Businesses respond with quotes.

**Sub:** A second flywheel in the same plugin.

**Bullets:**
- Buyer posts a need: title, type, budget, urgency, location, description
- Needs grid with filters - businesses find relevant open requests fast
- Businesses respond with a message + quote - tracked per need, per responder
- Auto-match by listing type + location - relevant businesses notified automatically
- Buyer accepts a quote → responds directly; the engagement thread lives in the dashboard

**Screenshot:** `needs-marketplace.png`

---

## Slide 16 - Moderators Team (Pro)

**Heading:** You don't have to approve every listing yourself.

**Sub:** Grant team members exactly the capabilities they need - nothing more.

**Bullets:**
- Moderator capabilities: approve listings, approve claims, moderate reviews, resolve reports
- Each capability is individually grantable - a claims moderator can't approve listings unless you say so
- Moderator queue block: filterable list of pending items with one-click approve/reject
- All moderator actions logged in the audit trail with actor + timestamp
- Restrict to individual listing types if running a multi-vertical directory

**Screenshot:** `moderators.png` and `moderation-queue.png`

---

## Slide 17 - Analytics + Audit Log (Pro)

**Heading:** If you can't measure it, you can't grow it.

**Sub:** Per-listing and directory-wide analytics. Full audit trail for compliance.

**Bullets:**
- Per-listing: views, map clicks, contact-form fills, lead form fills - daily/weekly/monthly
- Directory-wide: top listings, top categories, submission volume, conversion rates
- Outgoing webhooks: push listing.created, review.created, claim.approved, and more to any external system
- Audit log: every status change, badge grant, credit transaction - actor + timestamp + context
- Analytics data stored in the `listora_analytics` table - no third-party trackers

**Screenshot:** `analytics.png` and `audit-log-admin.png`

---

## Slide 18 - Import / Migration

**Heading:** Move your data in without starting from scratch.

**Sub:** CSV, JSON, GeoJSON - and built-in migrators for the four main competitors.

**Bullets:**
- Universal importers: CSV (per listing type), JSON, GeoJSON FeatureCollection - available in Free
- Competitor migrators: Directorist, GeoDirectory, WPBDP (Business Directory Plugin), ListingPro - Free
- Pro adds: Visual Importer with field auto-detection + preview + saved mapping templates
- Google Places bulk import (Pro) - seed your directory from Places API data
- WP-CLI: `wp listora migrate --from=directorist --dry-run` to preview before importing

**Screenshot:** `migrate-from-directorist.png` and `import-export-tab.png`

---

## Slide 19 - Developer Surface

**Heading:** Extend anything. Hook into everything.

**Sub:** 226 documented hooks. 55 REST endpoints in Free. 8 WP-CLI commands. Template overrides everywhere.

**Bullets:**
- 226 fired hooks (120 actions + 106 filters) - all with args signatures in `audit/manifest.json`
- 55 REST endpoints in Free (listora/v1) + 65 in Pro (62 unique routes)
- WP-CLI: `stats`, `reindex`, `listing-types`, `import`, `export`, `repair`, `migrate`, `demo`
- WooCommerce-style template overrides - copy any template to your theme and edit freely
- Headless / mobile: REST API + single Interactivity API store, WordPress 6.9+, PHP 7.4+

**Screenshot:** `blocks-overview.png`

---

## Slide 20 - White-Label + BuddyPress (Pro)

**Heading:** Make it yours. Connect it to your community.

**Sub:** Ship to clients with their brand. Sync the directory to BuddyPress activity.

**Bullets:**
- White-label: custom brand color + logo across all admin screens - your client sees their brand
- BuddyPress activity sync: listing submitted, approved, reviewed, claimed → activity stream posts
- Coming Soon mode: gate the directory to non-logged-in visitors while you build content
- Digest notifications (Pro): bundle daily/weekly email summaries instead of one-per-event noise
- Saved-search alerts (Pro): notify subscribers when new listings match their saved criteria

**Screenshot:** `white-label.png` and `buddypress-activity.png`
