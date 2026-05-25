# WB Listora - Free vs Pro Comparison Deck (10 Slides)

Use this deck for upgrade conversations, pricing page walkthroughs, and partner briefings. Ground truth: `docs/website/feature-matrix.md`.

---

## Slide 01 - The Simple Version

**Heading:** Free gives you the directory. Pro gives you the business model.

**Sub:** That's the whole story. Everything else is detail.

**Bullets:**
- Free: complete public directory - search, reviews, claims, maps, submission, spam protection
- Pro: credit plans, lead capture, verification, moderators, comparison, needs marketplace, analytics
- Both run on the same codebase - Pro extends Free, never replaces it

**Speaker notes:** This is the frame for the whole conversation. Anchor on it at the start, return to it at the end.

**Suggested visual:** Two-column layout. Left: Free. Right: Pro. Clean, no noise.

---

## Slide 02 - Setup and Infrastructure

**Heading:** Free and Pro both get the full setup experience.

**Sub:** Pro adds a longer wizard overlay covering license, credit packs, plans, and Google Maps key.

| Capability | Free | Pro |
|---|:---:|:---:|
| 6-step setup wizard | Yes | Yes |
| 9 demo packs (128+ listings) | Yes | Yes |
| Multiple listing types | Yes | Yes |
| Custom field framework | Yes | Yes + Pro fields |
| Setup wizard Pro overlay (license, credits, plans) | - | Yes |

**Speaker notes:** The goal here is to show that Free isn't a stripped-down demo. The Pro overlay supplements what Free already gives you.

**Suggested visual:** `setup-wizard-step1.png`

---

## Slide 03 - Search and Discovery

**Heading:** Visitors find listings in Free. Pro adds the tools power users need.

| Capability | Free | Pro |
|---|:---:|:---:|
| Full-text search (denormalized index) | Yes | Yes |
| Geo radius search (Near Me) | Yes | Yes |
| Faceted filters (category, location, feature, type) | Yes | Yes |
| "Search this area" drag-to-update | Yes | Yes |
| Advanced search builder (custom field filters) | - | Yes |
| Saved searches with email alerts | - | Yes |
| Infinite scroll on listing grid | - | Yes |
| Quick-view modal on listing cards | - | Yes |
| SEO landing pages (auto-generated /type-in-location/) | - | Yes |
| Side-by-side listing comparison | - | Yes |

**Speaker notes:** The Free search is production-grade - denormalized index, geo, facets. Pro's additions are about depth of engagement: saving searches, comparing picks, SEO pages.

**Suggested visual:** `search-and-filters.png` (Free side), `advanced-search.png` (Pro side)

---

## Slide 04 - Submission and Listing Lifecycle

**Heading:** Both tiers support full frontend submission. Pro adds the monetization gate.

| Capability | Free | Pro |
|---|:---:|:---:|
| Frontend submission wizard | Yes | Yes |
| Draft auto-saving | Yes | Yes |
| Guest submissions with email verification | Yes | Yes |
| Conditional fields | Yes | Yes |
| Draggable map pin | Yes | Yes |
| Image gallery upload | Yes | Yes |
| Business hours with timezone | Yes | Yes |
| Social links (7 platforms) | Yes | Yes |
| Listing renewal | Yes | Yes + credit-gated pricing |
| Self-service deactivate / reactivate | Yes | Yes |
| Duplicate detection at submit | - | Yes |
| Pricing plans (free / paid / featured) | - | Yes |
| Credit system (Hold-and-Commit) | - | Yes |
| Coupons (discount codes for plans) | - | Yes |

**Suggested visual:** `frontend-submission.png`

---

## Slide 05 - Reviews

**Heading:** Free reviews are solid. Pro reviews are thorough.

| Capability | Free | Pro |
|---|:---:|:---:|
| 5-star rating + written reviews | Yes | Yes |
| Owner public replies | Yes | Yes |
| Helpful-vote + milestone notifications | Yes | Yes |
| Report-a-review workflow | Yes | Yes |
| Auto-approve / require moderation toggle | Yes | Yes |
| Multi-criteria ratings (per-aspect stars) | - | Yes |
| Photo reviews (reviewers attach photos) | - | Yes |

**Speaker notes:** Multi-criteria is the most requested review feature in the category. Restaurants rate Food, Service, Value, Ambiance separately. Hotels rate Comfort, Cleanliness, Location, Value. Each aspect gets its own score.

**Suggested visual:** `reviews-system.png` (Free), `multi-criteria-reviews.png` (Pro)

---

## Slide 06 - Trust and Moderation

**Heading:** Free handles moderation basics. Pro handles it at team scale.

| Capability | Free | Pro |
|---|:---:|:---:|
| Business claims flow | Yes | Yes |
| Moderation queue (listings / reviews / claims) | Yes | Yes |
| Bulk-moderate (REST + admin) | Yes | Yes |
| Approve / Reject row actions | Yes | Yes |
| Verification badges | - | Yes |
| Moderators team (non-admin users) | - | Yes |
| Audit log (every transition recorded) | - | Yes |

**Speaker notes:** The moderators team is for directories with volume - a food guide with 500 restaurants can't have one person approving everything. The audit log is for compliance and accountability.

**Suggested visual:** `moderation-queue.png` (Free), `audit-log-admin.png` (Pro)

---

## Slide 07 - Analytics and Marketing

**Heading:** Free is transparent. Pro measures everything.

| Capability | Free | Pro |
|---|:---:|:---:|
| Per-listing analytics (views, clicks, contact fills) | - | Yes |
| Directory-wide analytics (top listings, categories) | - | Yes |
| Outgoing webhooks (push events to external systems) | - | Yes |
| Inbound payment webhooks (Stripe, PayPal + WooCommerce, WooSubs, MemberPress, PMPro, WooMemberships bridges) | - | Yes |
| Notification digest (daily / weekly email bundle) | - | Yes |
| White-label (custom brand across admin) | - | Yes |

**Speaker notes:** The outgoing webhooks slide connects WB Listora to external CRMs, Slack, analytics platforms, Zapier, and custom systems. That's the integration story for agencies.

**Suggested visual:** `analytics.png`, `outgoing-webhooks-admin.png`

---

## Slide 08 - Blocks (Gutenberg)

**Heading:** 11 blocks in Free. 5 more in Pro.

| Block | Free | Pro |
|---|:---:|:---:|
| listing-grid, listing-card, listing-search | Yes | Yes |
| listing-map, listing-detail, listing-reviews | Yes | Yes |
| listing-submission, listing-categories | Yes | Yes |
| listing-featured (carousel), listing-calendar | Yes | Yes |
| user-dashboard | Yes | Yes + Saved Searches + Needs tabs |
| comparison | - | Yes |
| needs-grid, post-need | - | Yes |
| moderator-queue | - | Yes |
| credit-purchase | - | Yes |

**Speaker notes:** Every block supports 20 standard responsive attributes - padding, margin, border radius, box shadow, device visibility. No third-party block library required.

**Suggested visual:** `blocks-overview.png`

---

## Slide 09 - Developer Surface

**Heading:** Pro extends what Free already exposes - it never duplicates it.

| Capability | Free | Pro |
|---|:---:|:---:|
| REST API | 55 endpoints | +65 endpoints (62 unique routes) |
| Action / filter hooks | 226 (120 actions + 106 filters) | Adds extension points |
| WP-CLI | 8 commands | +1 (demo seed/remove) |
| Template overrides (WooCommerce-style) | Yes | Yes |
| Custom capabilities | 15 caps | +3 Pro caps |
| Action Scheduler (bundled) | Yes (Free bundles AS 3.9.3) | Consumes Free's copy |

**Speaker notes:** The architecture contract is explicit: Pro is an extension of Free, not a fork. Pro never re-implements what Free already provides. Everything Pro adds is layered on top of Free's documented surface.

**Suggested visual:** None needed - table speaks for itself.

---

## Slide 10 - The Decision Matrix

**Heading:** Which tier is right for your project?

| You're building… | What you need |
|---|---|
| Open directory of free listings | Free only |
| Niche directory with submission gate | Free only |
| Job board for a single industry | Free only |
| Paid business directory (vendors pay to list) | Free + Pro |
| Premium listings with featured rotation | Free + Pro |
| B2B services marketplace with lead capture | Free + Pro |
| Reverse marketplace where buyers post needs | Free + Pro (Needs) |
| Multi-city directory with SEO landing pages | Free + Pro (SEO Pages) |
| Membership-gated directory | Free + Pro (with MemberPress/PMPro/WooMemberships) |
| Community + directory hybrid | Free + Pro (+ BuddyPress) |
| White-label / agency reseller | Free + Pro (White Label) |

**Speaker notes:** "Free only" isn't a consolation prize - it's a complete product. The upgrade conversation starts when money needs to change hands or when the directory needs trust infrastructure at scale.

**Suggested visual:** `feature-catalog.png`
