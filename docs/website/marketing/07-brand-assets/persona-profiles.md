# WB Listora - Persona Profiles

Use these profiles whenever you're writing copy, planning a campaign, or prioritising a feature. Each persona is specific enough that you can ask "would Sarah care about this sentence?" and get a real answer.

---

## Persona 1 - Site Owner / Operator

**Name (working):** Sarah Chen
**Role:** Independent directory site operator
**Age range:** 32-52
**Team size:** Solo or 1-3 person team (often just her)
**Industry:** Niche media, local publishing, community platforms, B2B lead-gen
**Technical level:** Comfortable in WordPress admin, runs plugins confidently, does not write code

### Demographics

Sarah has been running WordPress sites for 5+ years. She may be a blogger who spotted a monetization opportunity, a local journalist who wants to build a structured business guide, or a consultant who identified a gap in a niche vertical (medical spas, independent bookshops, vegan restaurants in her city). She runs the site alongside other work or as her primary income. She pays hosting, theme, and plugin bills out of her own pocket.

### Goals

- Launch a directory that earns real money from vendor subscriptions or featured placements within 90 days
- Keep operational overhead low enough to run it in under 5 hours per week after launch
- Build a site she can hand to a client or sell as a product in 12-24 months
- Not get locked into a page builder or theme that prevents her from switching stacks

### Pain Points

- **Setup time waste:** Most directory plugins require 2-3 hours of configuration before anything looks usable. She has burned weekends on plugins that never felt "done."
- **Monetization is an afterthought:** Free plugins give her a directory. Getting paid requires bolting on WooCommerce, a membership plugin, and custom code she doesn't know how to write.
- **Spam drowns the queue:** Without layered protection, her moderation inbox fills with garbage submissions within days of opening registration.
- **Scale anxiety:** She does not know if her chosen plugin will perform at 5,000 listings or 50,000. She has been burned by plugins that slowed to a crawl.
- **Vendor churn:** Listing owners don't renew if the dashboard feels like a WordPress admin clone. She needs her vendors to feel like they're using a proper product.

### Why WB Listora appeals

| Feature | What she gains |
|---|---|
| Setup wizard + 9 demo packs | Directory is operational in 30 minutes, not 30 hours |
| Credit-based plans with Hold-and-Commit (Pro) | She can charge vendors without custom payment glue |
| 6-layer anti-spam (honeypot + rate limits + CAPTCHA + Akismet + blacklist + URL-density cap) | Moderation queue stays manageable on day one |
| Denormalized search index + Action Scheduler | She can confidently tell clients "it handles 100K listings" |
| White-label (Pro) | She ships the product under her brand, not someone else's plugin name |

### Key message

WB Listora is the only WordPress directory plugin that ships the full business model out of the box - search, spam protection, vendor payments, and a dashboard your vendors actually want to use.

### Content they respond to

- **Formats:** Step-by-step setup tutorials, income case studies ("how I charge vendors"), comparison tables (Free vs Pro, WB Listora vs Directorist)
- **Channels:** YouTube tutorials, WordPress-focused newsletters, Facebook groups (WP Entrepreneurs, WP Builds community), targeted blog content on "how to build a directory website"
- **Tone:** Practical and direct. She skims. Lead with what she can DO, not what the plugin IS.

---

## Persona 2 - Agency / Reseller

**Name (working):** Marcus Webb
**Role:** WordPress agency owner or senior developer building client projects
**Age range:** 28-45
**Team size:** 2-15 people
**Industry:** Web development, digital marketing agencies, boutique WordPress shops
**Technical level:** Developer-fluent - reads code, understands hooks, evaluates REST APIs, reviews plugin source

### Demographics

Marcus runs client projects. He evaluates plugins the way an engineer evaluates a dependency: architecture first, then support track record, then price. He has been burned by plugins that shipped breaking changes in minor versions, had no hooks, or required forking templates to add basic customization. He is usually building for a client who wants something "like Yelp" or "like a job board" in a specific niche. He may be building the same category of product for multiple clients.

### Goals

- Deliver a polished, custom-branded directory product to his client without writing a directory engine from scratch
- Reduce recurring support tickets by choosing a plugin that handles edge cases correctly the first time
- Resell the Pro license and include a support retainer, earning margin on the plugin
- Build a productized directory package he can pitch to new verticals (healthcare, real estate, hospitality) without rebuilding from zero

### Pain Points

- **No extension surface:** Plugins that give him a finished product but no hooks mean every customization is a template fork or a hacked `functions.php`. He hates maintaining those diffs across updates.
- **Page builder lock-in:** Clients eventually want to edit pages. If the plugin is built on Elementor or WPBakery, he is locked to that stack.
- **Brittle update contracts:** A plugin that removes a template file or renames a hook in a minor version kills client sites. He needs deprecation windows.
- **No white-label path:** He cannot sell a premium product if "WB Listora" is branded into every admin screen.
- **REST API gaps:** Headless or mobile clients need a full REST API. Half-baked REST implementations force him to write bridge endpoints.

### Why WB Listora appeals

| Feature | What he gains |
|---|---|
| 259 documented hooks (133 actions + 126 filters) | He can extend without forking - every major surface has an entry point |
| 58 Free + 73 Pro REST endpoints | Headless frontends (Next.js, Astro) and mobile apps work against documented contracts |
| Native Gutenberg blocks + Interactivity API | No page builder dependency - block themes are the safe long-term bet |
| White-label (Pro) | Client sees his brand throughout the admin |
| WooCommerce-style template overrides | He overrides only what he needs; updates don't clobber his changes |
| 2-minor-version deprecation policy | He can plan upgrades without emergency patch sessions |

### Key message

WB Listora is built for extension - 259 hooks, 58 REST endpoints, WooCommerce-style template overrides, and a white-label mode that keeps your brand front and centre when you hand it to a client.

### Content they respond to

- **Formats:** Developer documentation (hooks reference, REST API contracts), architecture write-ups, code examples, migration guides
- **Channels:** GitHub, YouTube technical deep-dives, WP Tavern / Post Status, Twitter/X developer community, agency Slack groups
- **Tone:** Technical and precise. Show the hook signature. Show the endpoint schema. Do not summarise - give him the detail.

---

## Persona 3 - Listing Owner / Vendor

**Name (working):** Diego Ramirez
**Role:** Small business owner with a listing on the directory
**Age range:** 28-55
**Team size:** Solo or 2-5 employees
**Industry:** Any local service or product business - restaurants, tradespeople, studios, agencies, retail
**Technical level:** Not technical. Uses a phone for most tasks. Logs into WordPress only if forced.

### Demographics

Diego did not choose WB Listora. The directory operator chose it. Diego's goal is to get found by customers, manage his listing quickly, and not waste time in admin screens. He may have been invited to submit his business, or he found the directory while searching for competitors and decided to add himself. He will not read a manual. If the submission form takes more than 15 minutes, he will abandon it.

### Goals

- Get his business visible to potential customers in the directory, quickly
- Keep his listing accurate (hours, photos, services) without a technical support call
- Respond to reviews publicly so potential customers see an engaged owner
- If the directory is paid, understand exactly what he gets and what it costs before committing

### Pain Points

- **Form overwhelm:** Multi-step forms that feel like tax returns. He wants to fill in the essentials and get listed.
- **No feedback on status:** Submit and silence. He does not know if his listing is live, pending, or rejected.
- **Dashboard is wp-admin:** He does not want to navigate a WordPress backend to edit a business name.
- **Renewal surprises:** His listing expires with no warning. He loses leads while the listing is offline.
- **Fake review anxiety:** A competitor left him a 1-star review. He wants a visible, professional way to respond.

### Why WB Listora appeals

| Feature | What he gains |
|---|---|
| Multi-step submission wizard with draft saving | He can complete the form in stages, on mobile, without losing work |
| Frontend dashboard at `/my-listings/` | He never has to enter wp-admin |
| Status emails (submitted / approved / rejected / expiring) | He always knows where his listing stands |
| 7-day renewal reminder cron | He gets advance notice before his listing goes offline |
| Owner reply to reviews | He can respond publicly - no special access required |

### Key message

Submit your business in 15 minutes, manage everything from a frontend dashboard, and get notified before anything important changes.

### Content they respond to

- **Formats:** Short how-to videos ("how to submit your listing"), FAQ pages, onboarding emails from the directory operator
- **Channels:** Email from the directory operator, in-product tooltips, the submission wizard itself
- **Tone:** Reassuring and simple. One instruction at a time. No jargon. Tell him what happens next.

---

## Persona 4 - Visitor / End Customer

**Name (working):** Priya Nair
**Role:** Directory end-user - someone searching for a business or service
**Age range:** 22-55
**Team size:** Individual
**Industry:** Consumer (any vertical the directory serves)
**Technical level:** Non-technical. Knows how to Google. Uses mobile 70% of the time.

### Demographics

Priya arrived from Google, a social share, or a direct recommendation. She knows nothing about the plugin powering the site. She has an immediate need (a restaurant for tonight, a plumber by tomorrow, a venue for next month). She will leave within 10 seconds if the site does not show her relevant results. She will not sign up for an account to browse. She may write a review after a visit if the experience was notable.

### Goals

- Find a relevant business fast, with enough information to make a decision without leaving the directory
- Compare two or three options without opening multiple tabs
- Contact the business directly from the listing
- Get notified when something new matches her regular search (Pro saved search feature)

### Pain Points

- **Search returns nothing useful:** She types "vegan restaurant" and gets generic results or a blank state with no guidance.
- **Forced signup to browse:** Any site that gates browsing behind a login wall loses her immediately.
- **Stale listings:** Businesses that are closed or have wrong hours erode trust fast.
- **No mobile-friendly contact:** Phone numbers that are not clickable on mobile. Maps that do not load. Gallery images that break on small screens.
- **Review noise:** A listing with 40 reviews, 35 of which are obviously fake, makes the whole site feel untrustworthy.

### Why WB Listora appeals

| Feature | What she gains |
|---|---|
| Anonymous browsing with no signup wall | She can explore freely and sign up only when she has a reason to |
| Faceted search with reactive URL state | Her filter set persists across reloads and is shareable |
| Multi-criteria reviews (Pro) | Ratings per aspect (Food / Service / Ambiance) give her more signal |
| Side-by-side comparison (Pro) | She compares 2-4 shortlisted businesses in one screen |
| Saved searches with email alerts (Pro) | She gets notified when a new listing matches her criteria |

### Key message

Find what you need fast - search by category, filter by distance and rating, compare your top picks side by side, and contact the business in one click.

### Content they respond to

- **Formats:** She does not read marketing copy about the plugin. Her content is the directory itself: fast search, good listing quality, trustworthy reviews.
- **Channels:** Google organic, word-of-mouth, social shares of specific listings
- **Tone:** The UI speaks. Speed and clarity are the message. Every second of friction is a lost user.

---

## Persona 5 - Moderator / Team Member

**Name (working):** Aisha Okonkwo
**Role:** Community manager or first-line content moderator
**Age range:** 24-40
**Team size:** Part of a team - reports to the Site Owner
**Industry:** Any directory vertical
**Technical level:** Non-technical to lightly technical. Comfortable with admin interfaces. Cannot touch code.

### Demographics

Aisha was invited into the directory by the Site Owner. She may be a freelancer hired specifically for moderation, a junior team member given a defined scope, or a trusted vendor elevated to moderate their own category. She has a capped permission set - she can approve, reject, and hide content, but she cannot touch settings, pricing, or the moderator list itself. She needs to triage 20-50 items per day efficiently.

### Goals

- Clear her moderation queue in under 30 minutes per day without ambiguity about what each item needs
- Make correct approve/reject decisions without having to escalate to the Site Owner for every edge case
- Have a clear audit trail so she can defend a rejection if a submitter complains
- Not accidentally do something that affects payments, settings, or other parts of the site

### Pain Points

- **No clear queue:** Plugins that mix pending listings, reviews, and claims into a single unsorted admin list. She cannot prioritise or batch by type.
- **Over-permissioned access:** If the plugin gives her full admin access just to moderate listings, both she and the Site Owner feel uncomfortable.
- **No context on submissions:** She sees "pending listing" but cannot tell if the submitter has a history of spam or previous approvals.
- **Silent approval:** She approves or rejects something and does not know if the submitter got a notification. She ends up emailing them manually.
- **Escalation black hole:** No way to add a note or flag an item for the Site Owner without using a separate communication channel.

### Why WB Listora appeals

| Feature | What she gains |
|---|---|
| Capability-gated moderator role (Pro) | She sees only the screens relevant to her scope - nothing else |
| Separate queues per type (listings / reviews / claims / reports) | She triages by type, not by date-created-order of everything |
| Per-listing submitter context panel | She sees submitter IP, anti-spam flags, and prior approval history before deciding |
| Admin notes on reject | She writes a rejection note that the submitter receives - no manual email |
| Automatic status notifications | She approves something and the submitter gets the email automatically |

### Key message

A moderation role with the exact access you need and nothing you do not - approve, reject, note, and move on, without touching a single setting.

### Content they respond to

- **Formats:** Workflow documentation, "how to triage" quick-start guides, onboarding emails from the Site Owner
- **Channels:** Email from the Site Owner, in-product admin tooltips, the Moderators feature doc
- **Tone:** Operational and task-focused. What do I do, in what order, and what happens when I do it.
