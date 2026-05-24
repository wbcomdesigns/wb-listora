# WB Listora — Feature Page Copy

Organized by customer outcome, not by feature name. Each outcome section leads with what the user achieves, then the features that deliver it.

---

## Outcome 1: Find

**Heading:** Help visitors find what they're looking for — fast.

Every directory lives or dies on search quality. WB Listora's search engine is purpose-built, not a wrapper around `WP_Query`. The result: faceted filters that update without a page reload, geo queries that return accurate results at any catalog size, and URL state that lets visitors share exactly what they found.

**Image:** `search-and-filters.png`

### Full-text search — Built into Free

The search index is a dedicated denormalized table — not WordPress's built-in post search. Queries run against pre-processed text with full-text indexing so results stay fast whether you have 100 listings or 100,000.

### Geo search — Built into Free

Near Me uses browser geolocation. Radius filter lets visitors expand or tighten the search area. "Search this area" updates results as visitors drag the map — no reload, no lost context.

### Faceted filters — Built into Free

Stack category, location, feature/amenity, and listing type filters freely. Each filter updates results in real time via the Interactivity API store. Filter counts show how many listings match — so visitors don't waste clicks on empty results.

### Advanced search builder — Pro adds this

Custom field filters beyond the standard taxonomy facets. A restaurant directory can let visitors filter by cuisine type, price range, or outdoor seating. A job board can filter by salary band, employment type, or remote-friendly.

### Saved searches with alerts — Pro adds this

Visitors save any search and get an email when a new listing matches. A visitor looking for a photographer in Austin can set a saved search and hear about new photographers the day they list — without returning to check manually.

### SEO landing pages — Pro adds this

Auto-generated `/type-in-location/` pages with full search engine markup. "Restaurants in Brooklyn" becomes a real page with a canonical URL, title tag, and populated listing grid — not a redirect or a filter state.

### Side-by-side comparison — Pro adds this

Up to 4 listings selected from any card or detail page. A floating bar tracks the selection across the whole site. The comparison page shows configurable columns: core info, pricing, features, ratings, services, hours. State persists via localStorage and shares via URL.

---

## Outcome 2: Be Found

**Heading:** Give businesses a listing worth having.

A directory only works when vendors want to be in it. That means a submission flow that doesn't require a developer, a dashboard that doesn't require a wp-admin login, and a listing detail page that earns the click.

**Image:** `frontend-submission.png`

### Frontend submission wizard — Built into Free

Multi-step form — Basics, Details, Media, Contact, Hours — with draft auto-saving at every step. Conditional fields show only what's relevant to the listing type. The map pin is draggable to the exact storefront. Image gallery supports bulk upload. Guest submissions are supported with email verification.

### User dashboard — Built into Free

Vendors manage all their listings, reviews, favorites, and claims from a single frontend page. No wp-admin access needed. Per-row actions: Edit, Renew, Feature (Pro), Deactivate, Reactivate. Status visible at a glance: Live, Pending, Expired, Awaiting Credits, Deactivated.

### Business claims — Built into Free

If a business is already in the directory — added by a visitor or admin — the owner can claim it without creating a duplicate. Upload proof, admin approves, ownership transfers cleanly.

### Social links — Built into Free

Seven platforms supported on every listing: Facebook, Instagram, Twitter/X, LinkedIn, YouTube, Pinterest, TikTok. Social links appear in a dedicated card on the listing detail sidebar.

### Schema.org JSON-LD — Built into Free

10 supported schema types: LocalBusiness, Restaurant, Hotel, LodgingBusiness, FoodEstablishment, Store, HealthAndBeautyBusiness, JobPosting, Event, Place. Schema markup fires automatically based on the listing type — no configuration needed.

### Services per listing — Built into Free (Pro enhances)

Vendors add sub-products to their listing — menu items, service tiers, treatment prices. Services have their own title, description, price, and duration. Pro adds cross-listing service search and a booking CTA.

### Duplicate detection — Pro adds this

Before a vendor submits a listing, Pro checks for potential duplicates by name and location. If a match is found, the vendor sees the existing listing and can choose to claim it instead — preventing catalog pollution from the start.

### Featured listing rotation — Pro adds this

Vendors spend credits to rotate their listing into the homepage featured carousel for a defined number of days. The rotation schedule is managed per-listing, not per-admin — vendors can see their rotation status in the dashboard.

---

## Outcome 3: Monetize

**Heading:** Turn your directory into a revenue stream.

Free gives you a working directory. Pro gives you the financial plumbing to charge for it — without writing payment code.

**Image:** `credits-and-plans.png`

### Credit-based pricing plans — Pro adds this

Define credit packs (e.g. 10 credits for $40, 50 credits for $150) and pricing plans (Basic: 5 credits/month, Featured: 15 credits/month). Vendors buy credits and activate plans. Hold-and-Commit activation means no vendor gets charged for a listing that never went live — credits are held at selection and only committed on activation success.

### Seven payment paths — Pro adds this

Vendors top up credits through WooCommerce, WooCommerce Subscriptions, MemberPress, Paid Memberships Pro, WooMemberships, Stripe direct (webhook receiver), or PayPal direct (webhook receiver). Every top-up — regardless of gateway — flows through the same credit ledger and automatically resumes any paused listings.

### Coupons — Pro adds this

Percentage or flat discount codes, single-use or multi-use, with optional expiration dates. Vendors enter the coupon code during plan checkout. Coupon manager in wp-admin shows redemption history.

### Lead forms with analytics — Pro adds this

Lead forms replace the basic contact form with custom questions, per-listing conversion tracking, and CRM-ready delivery. Every fill is recorded with timestamp, visitor metadata, and listing ID. Directory-wide lead volume analytics show which listings convert best.

---

## Outcome 4: Trust and Verify

**Heading:** Give visitors reasons to trust what they find.

A directory where any business can add themselves with no verification is a directory visitors stop using. WB Listora builds trust infrastructure into the core — and gives Pro operators the tools to certify quality at scale.

**Image:** `verification-badges.png`

### Reviews with helpful votes — Built into Free

5-star ratings with written reviews. Helpful-vote system with milestone notifications. Owner public replies. Report-a-review workflow. Auto-approve or hand-moderate toggle. Review length requirements configurable per site.

### Anti-spam — 6 layers — Built into Free

Honeypot on every form. Per-IP sliding-window rate limits. reCAPTCHA v3 or Cloudflare Turnstile. Akismet review and claim content analysis. Keyword blacklist. URL density cap. All active by default — no configuration required to get protected.

**Image:** `spam-protection-settings.png`

### Multi-criteria reviews and photo reviews — Pro adds this

Reviewers rate individual aspects — Food, Service, Value, Ambiance for a restaurant; Comfort, Cleanliness, Location, Value for a hotel. Each aspect gets a separate score. Reviewers can attach photos. Composite scores display per-aspect on the listing detail page.

### Verification badges — Pro adds this

Admin defines badge types with configurable icon, color, criteria description, and optional expiration. Verified, Top Rated, Editor's Choice — any taxonomy the operator chooses. Badges render on listing cards, detail pages, and search result facets. Badge grants and removals are logged in the audit trail.

### Moderators team — Pro adds this

Grant team members exactly the moderation capabilities they need — approve listings, approve claims, moderate reviews, resolve reports — and nothing beyond that. Moderator actions are logged with actor and timestamp. For directories with volume, this prevents one person becoming a bottleneck.

**Image:** `moderators.png`

### Audit log — Pro adds this

Every status change, badge assignment, credit transaction, and moderation action recorded with actor, timestamp, and context. The audit log is the compliance layer for operators who need to demonstrate accountability.

**Image:** `audit-log-admin.png`

---

## Outcome 5: Scale

**Heading:** Build for tomorrow's traffic, not just today's.

Most directory plugins are built for the demo. WB Listora is built for the long run — both in architecture and in operator tooling.

**Image:** `directory.png`

### Denormalized search index — Built into Free

Search runs against a purpose-built `listora_search_index` table with full-text indexing. At 100,000 listings, search stays fast. At 1,000 listings, it's barely distinguishable from `WP_Query` — but the architecture means you never have to re-engineer search when you grow.

### Action Scheduler — Built into Free (vendored 3.9.3)

All background jobs — expiration checks, email sends, index rebuilds, digest batches — run on Action Scheduler, not bare WP-Cron. AS retries failed jobs, survives high-traffic moments, and runs cleanly on shared hosting. It's bundled in Free so there's no version conflict with other plugins that also ship it.

### Import and migration — Built into Free

CSV, JSON, and GeoJSON universal importers. Competitor migrators for Directorist, GeoDirectory, WPBDP, and ListingPro — all available in Free via WP-CLI and admin. `wp listora migrate --from=directorist --dry-run` previews the import before you commit.

### Visual Importer — Pro adds this

Drag-and-drop field mapping UI, source-field auto-detection, import preview, and saved mapping templates. For operators importing hundreds of listings at once.

### Google Places import — Pro adds this

Import listings from the Google Places API — single or bulk — with automatic field mapping. Seed a new city directory from real Places data in hours, not weeks.

### WP-CLI commands — Built into Free

`stats`, `reindex`, `listing-types`, `import`, `export`, `repair`, `migrate`, `demo` — 8 commands covering every operational task. `wp listora repair` cleans orphan search index rows. `wp listora reindex` rebuilds the full search index. Both are safe to run on live sites.

### Analytics — Pro adds this

Per-listing analytics: views, map clicks, contact fills, lead form fills. Directory-wide: top listings, top categories, submission volume, conversion rates. Data stored in the `listora_analytics` table — no third-party trackers.

**Image:** `analytics.png`

---

## Outcome 6: Integrate

**Heading:** Connect your directory to the rest of your stack.

WB Listora is built as an open platform — 226 documented hooks, 55 REST endpoints in Free, outgoing webhooks in Pro, and native integrations with the plugins your users already have.

**Image:** `blocks-overview.png`

### REST API — Built into Free

55 endpoints in Free, 65 in Pro (62 unique routes). Auth-gated where appropriate. Covers listings, reviews, search, submission, claims, favorites, dashboard, listing types, services, and settings. Every response is filterable via `wb_listora_rest_prepare_*` filters.

### 226 documented hooks — Built into Free

120 actions + 106 filters, all with argument signatures. Every write operation fires a `before_` filter (return `WP_Error` to abort) and an `after_` action. Every REST response is filterable. Every block has render hooks. The hook library is the foundation for all Pro extensions.

### BuddyPress activity sync — Pro adds this

Listing submitted, approved, reviewed, and claimed actions appear in the BuddyPress activity stream. Member profile links in reviews connect to BuddyPress member profiles. For community-plus-directory hybrid sites.

**Image:** `buddypress-activity.png`

### Outgoing webhooks — Pro adds this

Push directory events to any external system: listing.created, review.created, claim.approved, payment.received, and more. HMAC signature on every delivery. Webhook log with delivery status and retry. Connect to Zapier, Make, CRMs, Slack, or custom systems.

**Image:** `outgoing-webhooks-admin.png`

### Google Maps — Pro adds this

Replace Leaflet/OSM with Google Maps API. Custom map styles, marker clustering, and the full Google Places autocomplete experience. API key configured once in Pro settings.

**Image:** `google-maps.png`

### White-label — Pro adds this

Custom brand color and logo across all WB Listora admin screens. Ship to agency clients with their brand on every admin page — not yours, not Wbcom's.

**Image:** `white-label.png`

### Template overrides — Built into Free

WooCommerce-style override system: copy any template to `{theme}/wb-listora/` and edit it freely. Covers email templates, block templates (listing-card, listing-detail, user-dashboard), and page shells. Your overrides survive plugin updates.

### Headless / mobile — Built into Free

Every directory surface is accessible via REST. Build your frontend in Next.js, Astro, React Native, or Flutter — the REST API is the same whether you're rendering in WordPress or not.
