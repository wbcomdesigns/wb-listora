# 42 — Pro Plugin Business Plan

## How We Make Money

---

## Revenue Model

### Pricing Structure

| Plan | Price | Sites | Best For |
|------|-------|-------|----------|
| **Single Site** | $79/year | 1 | Solo directory operators |
| **5 Sites** | $149/year | 5 | Small agencies |
| **Unlimited** | $249/year | ∞ | Agencies, developers |
| **Lifetime Single** | $199 once | 1 | Long-term operators |
| **Lifetime Unlimited** | $499 once | ∞ | Agency one-time purchase |

### Why This Pricing
- **$79/year single** — Lower than GeoDirectory membership ($99-229/year), competitive with Directorist ($109/year)
- **Lifetime option** — HivePress does this ($29-39/extension × 15 extensions = $435+). We beat them with one purchase
- **No per-addon pricing** — Our ENTIRE competitive advantage. One price, everything included
- **Annual AND lifetime** — Annual = recurring revenue. Lifetime = cash flow spike + word-of-mouth from happy buyers

### Revenue Projections (Conservative)

| Metric | Month 6 | Month 12 | Month 24 |
|--------|---------|----------|----------|
| Free installs | 2,000 | 8,000 | 25,000 |
| Free → Pro conversion | 2% | 2.5% | 3% |
| Pro customers | 40 | 200 | 750 |
| Avg revenue/customer | $120 | $130 | $140 |
| Monthly revenue | $800 | $2,166 | $8,750 |
| Annual revenue | $4,800 | $26,000 | $105,000 |

**Conservative assumptions:** 2-3% conversion is standard for freemium WordPress plugins. Pricing includes mix of annual + lifetime.

---

## What Makes Someone Buy Pro

### The Upgrade Triggers (In Order of Impact)

**1. "I want to charge for listings" — Credit System + Plans**
This is the #1 reason. The moment a site owner wants to make money FROM their directory, they need Pro. The credit system + pricing plans are the primary monetization driver.

**2. "I need Google Maps" — Maps Upgrade**
OSM works, but Google Maps is what clients expect. Agencies building for clients almost always need Google Maps. This alone justifies $79/year for many buyers.

**3. "I need analytics" — Owner Analytics Dashboard**
Listing owners ask "is my listing working?" The moment a site owner's listing clients ask for stats, the site owner needs Pro.

**4. "I need better search" — Range Sliders, Saved Searches**
For real estate (price range slider), jobs (salary range), hotels (price per night) — the min/max number inputs in Free work but look amateur. Range sliders look professional.

**5. "I need multi-criteria reviews" — Review Upgrade**
When a restaurant owner says "our food is great but they dinged us on ambiance" — they want criteria breakdown. Site owners buy Pro to offer this to their listing clients.

**6. "I need to contact listing owners" — Lead Form**
Contact form with tracking is a Pro feature that listing owners value. Site owners can sell this as a plan perk.

---

## Pro Feature Value Map

### Tier 1: Core Revenue Features (These SELL Pro)

| Feature | Who Wants It | Perceived Value |
|---------|-------------|----------------|
| Credit system + pricing plans | Every monetized directory | $$$$ |
| Google Maps | Agencies, client work | $$$ |
| Analytics dashboard | Listing owners who pay | $$$ |
| Range sliders (price, salary, area) | Real estate, jobs, hotels | $$ |
| Lead form (contact owner) | All business directories | $$ |
| Multi-criteria reviews | Restaurants, hotels, healthcare | $$ |

### Tier 2: Professional Features (These JUSTIFY Pro)

| Feature | Who Wants It | Perceived Value |
|---------|-------------|----------------|
| Saved searches with email alerts | Job boards, real estate | $$ |
| Company profiles (jobs) | Job board operators | $$ |
| Application tracking | Job board operators | $$ |
| Event series linking | Event directories | $ |
| Favorite collections | Active visitor communities | $ |
| Photo reviews | Restaurant, hotel directories | $ |
| Overlay card layout | Design-conscious sites | $ |
| Quick view popup | High-volume browsing | $ |
| Infinite scroll | High-volume directories | $ |

### Tier 3: Enterprise Features (These RETAIN Pro)

| Feature | Who Wants It | Perceived Value |
|---------|-------------|----------------|
| Moderator role + assignment | Multi-moderator teams | $$ |
| Notification digests | High-volume directories (100+ submissions/day) | $$ |
| Audit log | Enterprise compliance | $$ |
| White-label | Agencies reselling | $$$ |
| Competitor migration tools | Switching from GeoDirectory/Directorist | $$ |
| Programmatic SEO pages | SEO-focused directories | $$ |
| Verification badges | Trust-focused directories | $ |
| Webhooks (outgoing) | Automation-heavy workflows | $ |
| Coming soon mode | Pre-launch directories | $ |

---

## Upgrade Prompts in Free Plugin (Non-Intrusive)

### Where Pro Hints Appear

| Location | Hint Type | Example |
|----------|-----------|---------|
| Settings → Maps | Inline option | `( ) Google Maps ⭐ Pro` |
| Listing Type → Search Filters | Disabled slider | Range slider grayed out with "Pro" badge |
| Dashboard → Analytics tab | Tab with lock icon | "Analytics ⭐ Unlock with Pro" |
| Review form → criteria | Collapsed section | "Multi-criteria ratings available with Pro" |
| Submission → plan step | Skipped | Plan selection step simply doesn't appear |
| Search → saved searches | Small link | "Save this search ⭐ Pro" |
| Card → quick view | Not shown | Feature simply absent (no broken UI) |
| Admin → sidebar | Small banner | One-line "Upgrade to Pro" link (dismissible) |

### Rules for Upgrade Hints
1. **Never block a workflow** — everything in Free works completely
2. **Never show popups or modals** — only inline hints
3. **Maximum 1 hint per admin page**
4. **Zero hints on frontend** — visitors NEVER see "Pro" messaging
5. **All hints are dismissible** — dismissed hints don't return for 30 days
6. **Never show "feature X is Pro" if feature X doesn't exist in Free** — only hint at enhancements of existing features

---

## Pro License System

### License Key
- Generated on purchase at wblistora.com
- Format: `LISTORA-XXXX-XXXX-XXXX-XXXX`
- Entered in: Pro plugin settings → License tab
- Validated against wblistora.com API

### License Validation
```
POST https://wblistora.com/wp-json/listora-license/v1/validate
Body: { license_key, site_url, site_name }
Response: { valid: true, plan: "unlimited", expires: "2027-03-19", sites_limit: -1 }
```

### License Enforcement
- Checked on activation, then weekly via cron
- If expired: Pro features deactivated, admin notice shown, data preserved
- If site limit exceeded: warning notice, 7-day grace period
- If license removed: Pro deactivated, all data preserved, Free continues working

### Update Delivery
- Pro plugin updates delivered via custom update server (EDD Software Licensing or WP Plugin API)
- Update checks include license validation
- Invalid license = no updates (but existing version continues working)

---

## Sales Channels

### Primary: wblistora.com
- Plugin page with feature comparison
- Checkout via Stripe/PayPal/LemonSqueezy
- License management dashboard
- Documentation + support

### Secondary: WordPress.org
- Free plugin listing drives traffic
- "Pro" link in plugin description
- Support forum presence (answer questions, suggest Pro for advanced needs)

### Partnerships
- Theme developers: bundle Pro with directory themes
- Agencies: white-label resale program
- Hosting companies: recommend as directory solution

---

## Support Strategy

### Free Plugin
- WordPress.org support forum (community + team)
- Self-serve documentation on wblistora.com
- Response time: best effort (within 48 hours)

### Pro Plugin
- Priority email support via wblistora.com
- Response time: within 24 hours (business days)
- Access to Pro-only documentation
- Screen-share support for complex setups (Unlimited plan)

### Documentation
- Getting started guide
- Per-feature documentation (one page per feature)
- Video tutorials (setup wizard walkthrough, payment setup, search config)
- Developer docs (hooks, REST API, extending Pro)
- Migration guides (from each competitor)

---

## Launch Strategy

### Pre-Launch (Before WordPress.org Submission)
1. Build wblistora.com with pricing, docs, comparison pages
2. Create 3 YouTube walkthrough videos (setup, search demo, payment setup)
3. Write 5 comparison blog posts (vs GeoDirectory, vs Directorist, vs HivePress, vs ListingPro, vs MyListing)
4. Set up Basecamp project for support tracking

### Launch Week
1. Submit Free plugin to WordPress.org
2. Announce on Reddit r/wordpress, r/webdev
3. Post on WordPress Facebook groups
4. Send to WordPress plugin review sites (WPBeginner, BlogVault, WPMayor)
5. Offer launch discount: 40% off first year Pro (code: LAUNCH40)

### Post-Launch (Month 1-3)
1. Respond to EVERY WordPress.org support thread within 24 hours
2. Write "how to build X directory" tutorials for each listing type
3. Create demo sites for each niche (restaurant, real estate, job board)
4. Collect 20+ 5-star reviews on WordPress.org
5. Guest post on WordPress blogs about "modern directory architecture"

### Growth (Month 3-12)
1. Add remaining listing types (if shipped with 3 initially)
2. Build competitor migration tools (Pro upsell driver)
3. Partner with 2-3 theme developers
4. Attend WordCamp (sponsor booth or lightning talk)
5. Start affiliate program for Pro sales

---

## Key Metrics to Track

| Metric | Target (Month 12) | How to Measure |
|--------|-------------------|----------------|
| Free active installs | 8,000+ | WordPress.org stats |
| Pro customers | 200+ | License server |
| Free → Pro conversion | 2.5%+ | License server / WP.org installs |
| MRR (Monthly Recurring Revenue) | $2,000+ | Payment dashboard |
| Support response time | < 24 hours | Support system |
| WordPress.org rating | 4.8+ stars | WordPress.org |
| Churn rate (annual) | < 15% | License renewals |
| Feature usage (Pro) | Track top 5 | Analytics in Pro |

---

## Competitive Positioning

### Our Message
> "The last directory plugin you'll ever need. Everything in one free plugin. Pro when you're ready to grow."

### vs Each Competitor

| Competitor | Our Pitch |
|-----------|-----------|
| vs GeoDirectory | "Same performance (custom tables), 10x easier setup, no addon nickel-and-diming" |
| vs Directorist | "Faster at scale, modern stack (no jQuery), everything included (not 30 addons)" |
| vs HivePress | "More features free, credit system works with any payment provider, not just WooCommerce" |
| vs ListingPro | "Plugin not theme — keep your theme. REST API included. Works with block editor." |
| vs MyListing | "No Elementor dependency. 7x faster page loads. Works with any theme." |
| vs JetEngine | "Purpose-built for directories — works in 15 minutes, not 15 hours." |
