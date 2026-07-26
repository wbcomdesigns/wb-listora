# WB Listora - LinkedIn Posts

15 long-form LinkedIn posts. Professional tone, no emoji. 200-300 words each. Problem then solution. Every post ends with a CTA.

Categories: 1 launch / 5 feature deep-dives / 3 customer-story placeholders / 3 thought leadership / 3 competitor comparison.

Source voice: `../hero-pitch.md`. Source claims: `../feature-matrix.md`, `../social-snippets.md`.

---

## Launch (1)

### LI-01 - Launch announcement

A directory plugin is supposed to do three things: let people find businesses, let businesses be found, and let you make money doing it. Most WordPress directory plugins do the first two badly and leave the third as homework.

We built WB Listora to do all three.

Free is the complete public directory. Eleven native Gutenberg blocks. A denormalized search index that scales to six-figure listing counts. Geo radius and "search this area" map bounds. Reviews with helpful votes. Business claims. A multi-step frontend submission wizard with auto-save. Hierarchical locations. Anti-spam in six layers. Fifty-five REST endpoints. Eight WP-CLI commands. You could launch a niche city guide tomorrow on Free alone and never need to upgrade.

Pro adds the business model layer. Credit-based pricing plans with Hold-and-Commit activation so you never owe a refund. Lead capture with analytics. Verification badges. Multi-criteria photo reviews. A moderators team with their own capability scope. Side-by-side comparison. Saved searches with alerts. BuddyPress activity sync. Outgoing webhooks. An inbound payment receiver that bridges Stripe, PayPal, WooCommerce, WooSubscriptions, MemberPress, PMPro and WooMemberships. Plus the reverse Needs Marketplace where buyers post requests and businesses respond with quotes.

Built on native Gutenberg blocks and the Interactivity API. No page builder lock-in. No jQuery soup. No template hacks. Designed for site owners who want a product, not a project.

Free and Pro both ship today at wblistora.com.

CTA: Visit wblistora.com to install Free and trial Pro.

---

## Feature deep-dives (5)

### LI-02 - Why we denormalized search

WP_Query is fine when you have a hundred posts. It is a liability when you have a hundred thousand listings with faceted filters and geo radius and full-text and "search this area" map bounds running in parallel.

So WB Listora does not use WP_Query for search.

The plugin maintains a denormalized search index in a dedicated table. Every listing has a precomputed row with the searchable text, the active facet values, the lat and lng, the status, the type and the verified flag. The search engine queries that table directly with prepared SQL and a Haversine distance calculator. Faceted filters become tight WHERE clauses. Geo Near-Me becomes a bounded subquery. Map bounds become a BETWEEN clause under a LIMIT cap.

The index rebuilds via Action Scheduler, vendored in Free. Every CRUD operation queues a single-row reindex. A full rebuild is one WP-CLI command: `wp listora reindex --all`. Action Scheduler retries on failure, batches across requests and tolerates worker death. WP-Cron drops jobs at scale; Action Scheduler does not.

The cost: one extra table, one extra index per indexed field, and a one-time reindex during upgrade. The benefit: a directory that does not get slower as it grows.

Most "scales to 100K" claims in this category mean "the SQL eventually returns". We benchmark against TTFB under 800ms for anonymous reads at production listing counts.

CTA: Read the architecture notes at wblistora.com/docs and run `wp listora reindex` on your own dataset.

---

### LI-03 - Hold-and-Commit credits explained

Most directory plugins charge vendors at submission. The listing then fails moderation. Now you owe a refund and a support email.

WB Listora Pro avoids that with a Hold-and-Commit credit pattern, verified in `class-pricing-plans.php`.

When a vendor selects a paid plan and submits a listing, the plugin checks the credit balance. If credits are sufficient, the cost is HELD on the listing, not deducted. If credits are short, the listing pauses with status "Awaiting Credits" and the vendor sees the exact shortfall plus a one-click link to the credit packs.

The vendor tops up. The hold remains.

When the admin (or a moderator with the right cap) approves the listing, credits COMMIT. That is the only point at which the balance changes. The listing publishes with the right featured-rotation entitlement.

If the admin rejects the listing, the hold releases automatically. The vendor's balance is untouched. No refund flow, no chargeback, no support ticket.

This is one of thirty-two Pro feature modules, but it is the one that determines whether a paid directory survives its first hundred vendors.

CTA: See the credits system live at wblistora.com.

---

### LI-04 - The reverse Needs Marketplace

Every WordPress directory plugin works one way. Vendors list. Customers search.

WB Listora Pro adds the reverse direction. Buyers post a Need ("looking for a caterer for 200 guests in Brooklyn") with a type, a location, an urgency and an optional budget. Matching businesses respond with quotes through their dashboard.

Auto-match runs on the type and location taxonomies the buyer chose. The needs grid is filterable like the listings grid. Buyers and responders both have a Needs tab in the user dashboard. Quotes get an audit trail. Needs expire on the schedule the operator configures.

This turns one directory into two flywheels. Vendors get inbound leads without paying for them. Buyers get matched without having to read every listing. The operator gets a second engagement loop without a second product.

The Needs Marketplace ships as four Pro blocks (`listora-pro/needs-grid`, `listora-pro/post-need`, dashboard Needs tab, response form) plus a feature toggle that controls whether the marketplace is visible at all.

CTA: Tour the Needs Marketplace at wblistora.com.

---

### LI-05 - Moderators team with scoped capabilities

Running a directory at scale means delegating moderation. Most plugins force you to grant Editor or Administrator to delegate anything. That is not delegation, that is co-ownership.

WB Listora Pro adds a Moderators team. Each moderator is a normal WordPress user with three Pro-only capabilities: approve listings, approve claims, moderate reviews. Plus the existing free Resolve Reports capability. You assign the exact scope each person needs and nothing more.

The caps are enforced everywhere it counts. REST endpoints check them before any write. Admin row actions hide when the user lacks the cap. The moderation queue block (`listora-pro/moderator-queue`) renders only the queues the user is scoped for. The Audit Log records every transition with user, action, target and timestamp, so you know who did what.

A non-admin moderator never sees Settings, never sees Users, never sees the Pricing Plans editor. They see the queue, they action items, they go home.

This is what safe delegation looks like in a content-heavy directory. Hire the help. Limit the blast radius.

CTA: Add a Moderators team at wblistora.com.

---

### LI-06 - 259 documented hooks and why they matter

Most WordPress plugins ship with extension surface as an afterthought. A handful of filters, undocumented, with `add_filter` and pray as the recommended usage pattern.

WB Listora's audit manifest counts 259 fired hooks across Free (133 actions plus 126 filters). Every one has a documented signature, a consumed-by list of internal listeners, and a stable contract. Every REST response runs through a `wb_listora_rest_prepare_*` filter so third-party code can add fields without forking the controller. Every write operation fires a `before_` filter (return WP_Error to abort) and an `after_` action.

Pro consumes Free's hooks instead of forking Free's code. Twenty-nine Free-to-Pro coupling pairs are documented in the cross-plugin coupling cache. The boundary check script refuses to merge any Pro PR that references Free's internal classes directly.

Free also ships fifty-five REST endpoints with cursor pagination and the same RFC-3339 timestamp shape across endpoints, plus eight WP-CLI commands (`stats`, `reindex`, `listing-types`, `import`, `export`, `repair`, `migrate`, `demo`). Templates are overrideable WooCommerce-style. Capabilities gate every UI and REST surface.

Headless implementations, custom integrations and white-label resellers all run on the same surface. Nothing is locked behind Pro that should be in Free.

CTA: Browse the extensibility surface at wblistora.com/docs/developer-guide.

---

## Customer story placeholders (3)

### LI-07 - Customer story (city guide) - placeholder

[Customer name] runs a city guide for [city]. [Number] listings across [number] categories. The directory ran on [previous plugin] for [duration]. The pain: search slowed down as the dataset grew, every monetization attempt required custom code, and the vendor onboarding flow lost people at step 3.

They moved to WB Listora over a weekend using the built-in migrator. Free's denormalized search handled the same dataset with [TTFB measurement] response time. They turned on Pro three weeks later when paid listings became the business model question.

Within [timeframe], they had [number] paid vendors, [number] verified business claims, and a moderators team of [number] handling the daily queue. The reverse Needs Marketplace launched in month [number] and now generates [percentage] of weekly active engagement.

Their quote: "[real customer quote when shipping]."

Operator takeaway: a directory plugin that does not require a developer for the monetization layer is the difference between a hobby and a business.

CTA: Read the full case study at wblistora.com/customers/[slug] - or talk to us about adding your story.

[REPLACE BRACKETED FIELDS WITH REAL CUSTOMER DATA BEFORE PUBLISHING]

---

### LI-08 - Customer story (niche vertical) - placeholder

[Customer name] runs a directory of [niche vertical] for [region]. [Vendor count] active vendors on the platform. The previous setup was a custom WordPress build with [list of plugins stitched together] and a developer on retainer for every change.

They consolidated onto WB Listora because one plugin maintained by one team beats three plugins maintained by three teams. The migration moved [count] listings, [count] reviews and [count] images. Custom fields mapped through the field framework. The taxonomy hierarchy moved through the GeoJSON importer.

The white-label feature is what closed the deal. Their directory ships under [client brand] with no "Wbcom" wordmark anywhere. The agency uses the same setup as a template across [number] client sites.

Three months in: [outcome metric], [outcome metric], [outcome metric].

Their lead developer's quote: "[real quote when shipping]."

Operator takeaway: a directory plugin that respects white-label is not a feature; it is the contract that lets an agency standardize.

CTA: Read the full case study at wblistora.com/customers/[slug].

[REPLACE BRACKETED FIELDS WITH REAL CUSTOMER DATA BEFORE PUBLISHING]

---

### LI-09 - Customer story (B2B marketplace) - placeholder

[Customer name] runs a B2B services marketplace for [industry]. The directory side was easy. The lead-capture side broke them.

Generic contact forms gave them no analytics, no per-listing attribution, no integration with their CRM. They lost track of which listings generated which leads, which forms had the highest fill rate and which vendors were getting their money's worth.

WB Listora Pro's Lead Form replaced the free contact form on every listing. The Lead Form ships with per-listing analytics, custom fields per listing type, an outgoing webhook to their CRM and a digest email so vendors see their pipeline weekly.

Within [timeframe], lead volume was up [percentage], the per-listing analytics dashboard surfaced which categories were converting and which were not, and the moderators team handled review moderation without ever touching wp-admin Settings.

CTA: See the Lead Form live at wblistora.com.

[REPLACE BRACKETED FIELDS WITH REAL CUSTOMER DATA BEFORE PUBLISHING]

---

## Thought leadership (3)

### LI-10 - Why monetization should be in the plugin

Most WordPress directory plugins treat monetization as a third-party concern. Want to charge vendors? Stitch in WooCommerce, write a custom integration, run a sync job, hope the webhook does not drop.

This is backwards.

A directory plugin already knows the listing lifecycle: who submitted, what plan, when it expires, what features were granted, when to renew. The monetization layer needs all of the same context. Splitting it across plugin boundaries means duplicating that context in WooCommerce metadata, syncing it back, and maintaining two source-of-truths.

WB Listora Pro ships pricing plans, credit packs, coupons and the Hold-and-Commit activation pattern inside the directory plugin itself. The bridge to external payment systems (Stripe, PayPal, WooCommerce, WooSubscriptions, MemberPress, PMPro, WooMemberships) is a thin adapter layer through the Wbcom Credits SDK. Vendors pay through whatever payment system you already run. Credits land in WB Listora. Listings know about plan state because plan state lives in the same plugin as the listing.

That is the contract that turns a public directory into a recurring-revenue business. The plugin owns it. You do not write a sync layer.

CTA: Read more about Pro's business model layer at wblistora.com/pro.

---

### LI-11 - Free should be a complete product, not a demo

A pattern I keep seeing in WordPress: a Free plugin that is missing one critical feature on purpose so the upgrade button does the work. Search without filters. Submission without spam protection. Reviews without moderation.

This pattern trains customers to assume Free is a teaser and Pro is the real product. So they install, hit the missing feature, and either pay or churn. Either way the relationship started with a gate.

WB Listora Free is a complete public directory. Search with facets, geo and full-text. Reviews with moderation and helpful votes. A multi-step frontend submission wizard with auto-save. Business claims. Six layers of anti-spam. Eleven Gutenberg blocks. Fifty-five REST endpoints. Eight WP-CLI commands. You can launch a niche city guide tomorrow on Free and never upgrade.

Pro adds the business model layer on top. Credit plans, lead forms, verification badges, moderators, comparison, the reverse Needs Marketplace, advanced analytics, white-label. These are not gates on Free features; they are entirely new capabilities for operators who want to run a paid directory.

The test: would you launch this product if you only had Free? For WB Listora, yes. That is the bar.

CTA: Install Free at wblistora.com.

---

### LI-12 - Why no shortcodes (almost)

Shortcodes were the right answer in 2014. They were what WordPress had. We built a generation of plugins on top of them.

Then came Gutenberg, the Interactivity API and full-site editing. Shortcodes became the legacy debt that prevented theme builders from understanding your output, that hid configuration behind opaque strings, that broke when content was copied between sites, and that left every block-theme user stranded.

WB Listora has eleven native Gutenberg blocks in Free and five more in Pro. Every block has Inspector Controls with five panels: Content, Display, Layout, Style and Advanced. Per-instance CSS scoping. Twenty standard attributes. Responsive padding and margin per breakpoint. Device visibility. apiVersion 3.

There is exactly one shortcode in the entire plugin: `[listora_compare]`, used internally by the auto-generated Compare page so the URL stays stable across theme switches. That is it.

For block themes, this means every Listora UI is editable in the Site Editor. For classic themes, blocks still render as HTML. For headless frontends, blocks render server-side and the REST API returns the same data.

CTA: Tour the block library at wblistora.com/docs/blocks-overview.

---

## Competitor comparison (3)

### LI-13 - WB Listora vs Directorist

Directorist is the most-installed WordPress directory plugin. It is also where most operators discover the limits of shortcode-driven, classic-theme-era directory architecture.

The differences when you compare side by side:

Architecture. Directorist is shortcode-driven. WB Listora is block-first with eleven Gutenberg blocks in Free and five more in Pro, all on the Interactivity API. No shortcodes (except one stable URL for the Compare page).

Search. Directorist runs faceted search through WP_Query and meta queries. WB Listora maintains a denormalized search index in a dedicated table that scales to six-figure listing counts without re-architecture.

Monetization. Directorist sells extensions for each monetization piece. WB Listora Pro ships pricing plans with Hold-and-Commit credits, lead forms, verification badges and the reverse Needs Marketplace as a single suite.

Migration. WB Listora ships a Free Directorist migrator. CLI: `wp listora migrate --from=directorist --dry-run`. Move when you are ready.

Side-by-side comparison: wblistora.com/comparison.

CTA: Try the Directorist migrator before you commit. Dry-run mode shows you the mapping first.

---

### LI-14 - WB Listora vs GeoDirectory

GeoDirectory has a strong geo and multi-vertical story. The trade-off is a complex configuration surface and a heavy reliance on add-ons for things that should be base capability.

The differences when you compare side by side:

Configuration. GeoDirectory's settings span dozens of pages and many add-ons. WB Listora ships a 6-step setup wizard that auto-creates the Add Listing, My Listings and Directory pages, picks a map provider, picks a country and seeds a demo pack. Operational in 30 minutes.

Block vs widget. GeoDirectory leans on widgets and shortcodes. WB Listora is block-first with eleven Gutenberg blocks in Free.

Reviews. GeoDirectory's review system is single-star with separate add-on for photos. WB Listora Pro ships multi-criteria reviews (per-aspect stars), photo reviews, owner replies and helpful-vote milestones in the base Pro product.

Reverse marketplace. GeoDirectory does not have one. WB Listora Pro ships the Needs Marketplace as a Pro block set.

Migration. WB Listora ships a Free GeoDirectory migrator with admin UI and CLI dry-run.

Side-by-side comparison: wblistora.com/comparison.

CTA: Run the GeoDirectory migrator in dry-run mode on a staging copy first.

---

### LI-15 - WB Listora vs ListingPro

ListingPro is a theme-coupled directory product. That is its greatest strength for fast launches and its biggest constraint when you outgrow the theme.

The differences when you compare side by side:

Architecture. ListingPro is a theme plus a plugin tightly coupled. WB Listora is plugin-only and theme-agnostic. We test against BuddyX Pro, Astra, Kadence and GeneratePress. Classic themes work through template overrides.

Extensibility. ListingPro's hook surface is informal. WB Listora ships 259 documented hooks, 58 REST endpoints in Free, 73 more in Pro, and 8 WP-CLI commands.

Multi-criteria reviews. ListingPro has them. WB Listora Pro has them with photos, owner replies and helpful-vote milestones.

Moderators team. ListingPro relies on default WordPress roles. WB Listora Pro adds a Moderators team with three scoped capabilities, REST-enforced and audit-logged.

Migration. WB Listora ships a Free ListingPro migrator. CLI dry-run first, admin import second.

Side-by-side comparison: wblistora.com/comparison.

CTA: See the side-by-side feature comparison and run a dry-run migration.
