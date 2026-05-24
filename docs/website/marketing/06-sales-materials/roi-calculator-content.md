# WB Listora ROI Calculator — Content and Scenarios

This document gives sales teams a framework for walking prospects through the return on a WB Listora Pro purchase. Four scenarios are included with explicit math. Adapt the numbers to the prospect's actual context — the framework holds, the figures will vary.

---

## The Framework

A directory site has three cost buckets and three revenue levers.

**Cost buckets**
1. Plugin cost (WB Listora Pro license at {{PRICING_PLACEHOLDER_PRO}})
2. Setup cost (your time, or an agency's hourly rate, to install + configure + load content)
3. Ongoing cost (hosting, domain, optional team time for moderation)

**Revenue levers**
1. Paid listing plans (vendors pay credits to list and feature their business)
2. Lead / response fees (vendors pay credits to contact a buyer or respond to a Need)
3. Service fees / agency margin (if you're building for a client, your project rate applies)

**Break-even logic**
Break-even = (Plugin cost + Setup cost) / Revenue per listing per year

Below 10 paying vendors at $25+ per year, the plugin pays for itself. At 50+ vendors, it's a margin business.

---

## Scenario A: City Food Guide

**Setup:** A blogger runs a city-specific restaurant directory for Chicago. She charges $200 per year per restaurant to be listed. She starts with 50 vendors at launch.

| Item | Value |
|---|---|
| Plugin cost (WB Listora Pro, year 1) | {{PRICING_PLACEHOLDER_PRO}} |
| Setup cost (self-managed, ~8 hours at $75/hr estimate) | $600 |
| Total year-1 cost | {{PRICING_PLACEHOLDER_PRO}} + $600 |
| Listing fee per vendor per year | $200 |
| Vendors at launch | 50 |
| Gross year-1 revenue | $10,000 |
| Year-1 net (approx) | $10,000 - ({{PRICING_PLACEHOLDER_PRO}} + $600) |
| Break-even (vendors needed to cover costs) | ({{PRICING_PLACEHOLDER_PRO}} + $600) / $200 |

**Revenue model:** Vendors pay $200/year via a credit pack (e.g. "Basic 1-year plan"). WB Listora Pro's WooCommerce adapter handles the purchase; credits are held on plan selection and committed when the listing activates. No partial charges, no manual reconciliation.

**Ongoing cost (year 2+):** Hosting ($20-50/month) + plugin renewal ({{PRICING_PLACEHOLDER_PRO_RENEWAL}}) + moderation time (~2-4 hours/month). Year 2 net is higher because setup cost is paid back in year 1.

**What Pro features matter here:** Pricing plans, WooCommerce adapter (credit purchase checkout), featured listing rotation (upsell at $50/quarter), audit log (who approved what), analytics (show vendors their views to justify renewal).

**Key question for the prospect:** How many restaurants in your city would pay $200 to be in a well-trafficked local guide? If the answer is 20 or more, the math works before year-end.

---

## Scenario B: Niche Directory with a Pro Upgrade Path

**Setup:** An operator runs a directory for independent yoga studios across the US. 200 listings total. 20% of listing owners upgrade to a Pro plan with a featured badge and lead form.

| Item | Value |
|---|---|
| Plugin cost (WB Listora Pro, year 1) | {{PRICING_PLACEHOLDER_PRO}} |
| Setup cost (freelancer, ~20 hours at $75/hr) | $1,500 |
| Total year-1 cost | {{PRICING_PLACEHOLDER_PRO}} + $1,500 |
| Free listings | 160 (no revenue) |
| Pro listings | 40 (20% of 200) |
| Pro plan fee per year | $600 |
| Gross year-1 revenue | 40 x $600 = $24,000 |
| Year-1 net (approx) | $24,000 - ({{PRICING_PLACEHOLDER_PRO}} + $1,500) |
| Break-even (Pro upgrades needed) | ({{PRICING_PLACEHOLDER_PRO}} + $1,500) / $600 |

**Revenue model:** Free listings are allowed — operators use them as the funnel. Studios that want lead forms, a verification badge, and featured placement upgrade to a Pro plan. WB Listora's plan picker appears in the submission wizard after the "Basics" step; the upsell is built into the submission flow.

**Ongoing cost:** Hosting + plugin renewal + content moderation (~1-2 hours/week for a 200-listing directory). Some operators bring on a moderator at a discounted rate — WB Listora Pro's Moderator Team feature grants moderation caps without admin access.

**What Pro features matter here:** Lead forms (the #1 reason yoga studios upgrade), verification badges (social proof on listings), multi-criteria reviews (studios want to know ratings by class type, instructor, cleanliness), analytics (show studios their lead count monthly).

**Sensitivity check:** Even at 10% upgrade rate (20 studios), revenue is $12,000. At a 15% upgrade rate (30 studios), it's $18,000. The plugin pays for itself with 3-4 Pro subscribers.

---

## Scenario C: Agency Reseller Building Client Directories

**Setup:** A WordPress agency builds directory sites for clients as a productized service. Each project is a WB Listora install configured for the client's niche. The agency charges $5,000 per directory build plus a $200/month retainer for hosting, updates, and support.

| Item | Value |
|---|---|
| WB Listora Pro license | {{PRICING_PLACEHOLDER_PRO}} |
| Setup cost per client (20 hours at $100/hr) | $2,000 |
| Client project fee | $5,000 |
| Margin per project | $5,000 - $2,000 - (license share) |
| Projects in year 1 | 10 |
| Gross project revenue year 1 | $50,000 |
| Retainer revenue (10 clients x $200/month x 12 months) | $24,000 |
| Total year-1 revenue | $74,000 |

**Revenue model:** The agency uses the WB Listora Pro license as part of their toolchain — the license cost is absorbed into the project fee (or billed as a line item). The white-label feature removes all Wbcom branding, so clients see the agency's brand in the admin. Each client gets a configured directory — listing types, taxonomy, demo content, one payment integration wired up — as the deliverable.

**What Pro features matter here:** White-label (agency brand in admin, not Wbcom), setup wizard (reduces per-project setup time dramatically), competitor migrators (agencies migrating clients from Directorist or GeoDirectory save 4-8 hours of manual data work per project), 8 WP-CLI commands (automation of seeding, repair, export in a delivery workflow).

**Leverage point:** With WB Listora, the agency can configure a new directory type (restaurant, job board, real estate, services marketplace) in under a day using a demo pack as the starting point. Without it, each build starts from scratch. The per-project setup cost drops from ~40 hours to ~20 hours as the agency builds confidence with the tool.

**Note on licensing:** Check the current WB Listora Pro license terms at wblistora.com for multi-site or agency usage rights — {{AGENCY_LICENSE_PLACEHOLDER}}.

---

## Scenario D: Reverse Marketplace (B2B Services with Response Fees)

**Setup:** An operator runs a B2B services directory — IT consultants, accountants, lawyers, HR agencies. Buyers post "Needs" (service requests with budget and timeline). Vendors pay a credit fee to respond to each Need. Average 50 new Needs per month, each attracting 3-5 vendor responses at $5 per response.

| Item | Value |
|---|---|
| Plugin cost (WB Listora Pro, year 1) | {{PRICING_PLACEHOLDER_PRO}} |
| Setup cost (agency build, ~30 hours at $100/hr) | $3,000 |
| Total year-1 cost | {{PRICING_PLACEHOLDER_PRO}} + $3,000 |
| New Needs per month | 50 |
| Average vendor responses per Need | 4 |
| Response fee per credit pack equivalent | $5 |
| Monthly response revenue | 50 x 4 x $5 = $1,000 |
| Annual response revenue | $12,000 |
| Additional: featured listing fees (vendors who want top placement) | + {{FEATURED_LISTING_REVENUE_PLACEHOLDER}} |
| Year-1 net (response revenue only) | $12,000 - ({{PRICING_PLACEHOLDER_PRO}} + $3,000) |
| Month-to-break-even | ({{PRICING_PLACEHOLDER_PRO}} + $3,000) / $1,000 |

**Revenue model:** Buyers post Needs for free (or for a small credit fee to control quality). Vendors pay credits per response. Credits are purchased via any of the 7 payment integrations. The site operator sets the response credit cost in WB Listora Pro settings. The Needs marketplace handles buyer-vendor matching, response threading, and status tracking — no custom code required.

**What Pro features matter here:** The Needs CPT + Needs grid block + "Post a Need" block + needs dashboard tab (all Pro) form the reverse marketplace layer. Auto-match by listing type and location surfaces relevant Needs to vendors in their category. The audit log tracks who responded to what (important for dispute resolution in B2B contexts).

**Growth path:** As the network grows, response fees can increase (supply-and-demand). Verified badge upsells are highly effective in B2B — buyers prefer responding vendors who carry a verification badge. A directory with 200 active vendors and 100 Needs/month at $8/response generates $32,000+ in response fees annually, with marginal costs staying flat.

**Risk to communicate to the prospect:** The reverse marketplace model requires buyers. It works best on directories where the site already has traffic and established vendor relationships. Don't lead with the Needs marketplace if the site is starting from zero.

---

## Talking Points for the Sales Conversation

- Ask how many paying vendors the prospect needs to break even before quoting a number. The answer is usually 3-10, which is achievable in a first month of outreach.
- For agencies, frame WB Listora Pro as a force multiplier on billable hours — fewer hours per project at the same or higher rate.
- For niche directory operators, the upgrade path (Free listings → Pro plans) is a proven model. Don't let them plan to monetize everyone from day one.
- The unified credit ledger is a meaningful differentiator when the prospect has tried to stitch together WooCommerce + a separate gateway + a listing plugin before.
- Hold-and-Commit activation (credits held, committed on listing activation) removes the #1 support headache in other directory tools — "I paid but my listing isn't live." That eliminates a support burden worth real hours per month.

---

*All revenue projections are illustrative. Actual results depend on traffic, niche demand, pricing, and operator execution. Pricing placeholders ({{PRICING_PLACEHOLDER_PRO}}, {{PRICING_PLACEHOLDER_PRO_RENEWAL}}) should be filled with current published pricing from wblistora.com before presenting to a prospect.*
