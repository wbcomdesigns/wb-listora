# WB Listora - Messaging Guide

This is the single source of truth for how WB Listora talks about itself. Every piece of copy - landing page, social post, email subject line, video script, release note - should be checked against this guide before it ships.

---

## Brand voice

**The one-line rule:** Write like a knowledgeable colleague explaining something to a capable peer. Not a salesperson. Not a chatbot.

### Principles

**Specific over vague.** "55 REST endpoints" beats "a powerful API." "6-layer anti-spam" beats "robust spam protection." If you can name the number or the mechanism, name it.

**Benefits lead, features follow.** Lead with what the user gets, then explain what the plugin does to deliver it. Wrong: "WB Listora includes an Action Scheduler integration." Right: "Cron jobs that actually run at scale - WB Listora bundles Action Scheduler so your renewal reminders and listing expirations fire even at 100K listings."

**Earn the claim.** Every claim in external copy must have a matching source in the feature matrix or manifest. If you cannot point to a file or a table row that backs the number, do not write the number.

**No filler.** Words like "simply," "just," "basically," and "seamlessly" add zero information and signal low confidence. Cut them.

**Active voice.** "WB Listora sends a renewal reminder 7 days before expiry" beats "Renewal reminders are sent by WB Listora 7 days before expiry."

### YES vs NO - examples

| Do not write | Write instead |
|---|---|
| "A revolutionary approach to directory management" | "A directory plugin built to handle 100K listings without re-architecture" |
| "Seamlessly integrates with your existing workflow" | "Works alongside WooCommerce, MemberPress, and PMPro via inbound payment webhooks" |
| "Game-changing spam protection" | "Spam protection in 6 layers - honeypot, rate limits, CAPTCHA, Akismet, keyword blacklist, URL-density cap" |
| "Leverage our powerful API to synergize your stack" | "55 REST endpoints in Free - every listing, review, search, and submission operation has a documented route" |
| "Simply install and you're done!" | "Install, run the setup wizard, load a demo pack - operational in 30 minutes" |
| "The best WordPress directory plugin" | "A WordPress directory plugin built for site owners who need to charge vendors" |
| "Unlimited everything" | "Scales to 100K+ listings on the denormalized search index" |

---

## Tone variations per channel

### Landing page / sales copy

Tone: Direct, confident, structured. The reader is evaluating. Give them enough to decide, structured for scanning.

- Use subheadings every 2-3 paragraphs
- Short paragraphs (2-3 sentences max)
- Bullet points for feature lists - not prose
- Lead every section with the user benefit, not the plugin feature
- Avoid exclamation marks

### Video script / voiceover

Tone: Conversational and paced. The viewer cannot scroll back easily. Use shorter sentences than you would in print.

- One idea per sentence
- Read it out loud - if it sounds unnatural spoken, rewrite it
- Avoid parenthetical asides (those work in text, not audio)
- Do not use jargon without immediately explaining it

### Social (Twitter/X, LinkedIn)

Tone: Factual and scannable. Twitter: short, one point per post. LinkedIn: slightly longer, first-person observation framing works well.

- Twitter: one claim + one number + one link. Max 240 characters of substance.
- LinkedIn: observation → specific problem → what WB Listora does about it → result. 150-250 words.
- No ALL CAPS. No multiple exclamation marks.
- Hashtags: 2-3 max, relevant only (#WordPress #DirectoryPlugin)

### Email (to existing users / prospects)

Tone: Collegial. You are writing to someone who already chose to hear from you or who already uses the product. Respect their time.

- Subject line: specific and honest. "WB Listora 1.1 - 32 Pro modules, 65 new REST endpoints" beats "Big news from WB Listora!"
- First sentence: state the point. Do not warm up.
- Body: one primary CTA. Do not bury it.
- Length: under 200 words for announcements. Longer is okay for onboarding sequences where the reader expects depth.

### Sales call / demo

Tone: Consultative. You are listening as much as talking. Adapt the pitch to the use case the prospect describes.

- Open with: "What kind of directory are you building?" before pitching features
- Match the persona (site owner: lead with monetization; developer: lead with API surface)
- Use the 90-second deep pitch as your structure, not the 8-second hook
- Have the feature matrix open for specifics

---

## Canonical product naming

| Correct | Incorrect |
|---|---|
| WB Listora | Listora (alone - collides with unrelated products) |
| WB Listora | Listora Plugin |
| WB Listora Free | Free Listora / Listora free version |
| WB Listora Pro | Listora Pro / WB Listora PRO / the Pro version |
| Free (capital F, standalone) | free version / free tier / the free plan |
| Pro (capital P, standalone) | pro / PRO / premium |

**Rule:** The first mention in any piece of content always uses "WB Listora." Subsequent mentions within the same piece may use "Free" or "Pro" as shorthand once the full name has been established.

**Do not:** Abbreviate to "WBL." Do not use "the plugin" as a primary reference - use "WB Listora."

---

## Word bank

### Words to use

- **verified** - as in verification badges, verified listings
- **specific** - use it to describe targeting, criteria, scopes
- **scalable** - tied always to a number ("scales to 100K listings")
- **granular** - for permission scopes, pricing tiers, search criteria
- **extensible** - for the hook and REST surface
- **layered** - for the anti-spam stack
- **operational** - for time-to-launch framing ("operational in 30 minutes")
- **documented** - always precede hook and endpoint counts ("226 documented hooks")
- **bundled** - for Action Scheduler and the Credits SDK
- **denormalized** - technical audiences only; for the search index
- **gated** - for capability-gated features and auth-gated REST routes
- **modular** - for Pro feature toggles
- **transparent** - for pricing, audit log, email log

### Words to ban

| Banned word/phrase | Why |
|---|---|
| revolutionary | Empty superlative |
| game-changing | Empty superlative |
| seamlessly | Tells nothing; implies the writer does not know where friction is |
| leverage (as a verb) | Business jargon |
| synergy / synergize | Business jargon |
| basically | Undermines confidence in what follows |
| simply / just | Dismisses legitimate complexity |
| robust (without specifics) | Means nothing without a number or mechanism |
| powerful (without specifics) | Same as robust |
| unlimited | Rarely true; use "scales to X" with a real number |
| cutting-edge | Ages immediately; means nothing |
| world-class | Unverifiable and grandiose |
| innovative | Self-applied, credibility-negative |
| ALL CAPS (for emphasis) | Use bold or restructure the sentence |
| !!! (multiple exclamation marks) | Reads as spam |
| best-in-class | Unverifiable |

---

## Emoji policy

- Maximum 1 emoji per piece of content
- Never in product names, feature names, or technical labels
- Never in headings
- Acceptable use: a single emoji in a social post to add visual anchor, or in a newsletter subject line (sparingly)
- Never as a bullet point replacement

---

## Canonical claims

These are the only claims we make about specific numbers. Each is backed by a source. Use them as written - do not round, do not paraphrase the numbers.

| Claim | Exact wording | Source |
|---|---|---|
| Hooks | "226 documented hooks (120 actions + 106 filters)" | `audit/manifest.json` - hooks_fired count, verified 2026-05-24 manifest refresh |
| Free REST endpoints | "55 REST endpoints in Free" | `audit/manifest.json` - REST routes, verified 2026-05-18 baseline |
| Pro REST endpoints | "65 additional REST endpoints in Pro (62 unique routes)" | `audit/manifest.json` - Pro REST audit |
| Gutenberg blocks | "11 blocks in Free + 5 blocks in Pro" | `docs/website/feature-matrix.md` - Frontend blocks section |
| Pro modules | "32 Pro feature modules" | `docs/website/feature-matrix.md` - Pro features enumeration |
| Demo packs | "9 demo packs" (restaurant, hotel, real-estate, job-board, general, classified, education, healthcare, place) | `docs/website/feature-matrix.md` - Setup & content infrastructure |
| Anti-spam layers | "6-layer anti-spam" or "spam protection in 6 layers" - layers are: honeypot, per-IP rate limits, reCAPTCHA v3 / Cloudflare Turnstile, Akismet, keyword blacklist, URL-density cap | `docs/website/feature-matrix.md` - Anti-spam & abuse controls |
| Payment integrations | "7 payment integrations" - WooCommerce, WooCommerce Subscriptions, MemberPress, Paid Memberships Pro, WooMemberships, Stripe (direct), PayPal (direct) | `docs/website/feature-matrix.md` - Integrations + Marketing/Analytics sections |
| PHP requirement | "PHP 7.4+" | `docs/website/feature-matrix.md` - Compatibility |
| WordPress requirement | "WordPress 6.9+" | `docs/website/feature-matrix.md` - Compatibility |
| Direct payment gateways | "Stripe and PayPal" | `docs/website/feature-matrix.md` - Integrations |
| SDK adapters | "5 payment SDK adapters" (WooCommerce, WooSubscriptions, MemberPress, PMPro, WooMemberships) | `docs/website/feature-matrix.md` - Integrations |
| Scale | "Scales to 100K listings" | `docs/website/hero-pitch.md` + `audit/manifest.json` - denormalized search_index + Action Scheduler |
| Hold-and-Commit | "Hold-and-Commit credit activation" | `docs/website/feature-matrix.md` - Credit system row |
| WP-CLI commands | "8 WP-CLI commands" (stats, reindex, listing-types, import, export, repair, migrate, demo) | `docs/website/feature-matrix.md` - Developer surface |
| Schema types | "10 Schema.org types" | `docs/website/feature-matrix.md` - Setup & content infrastructure |

---

## Forbidden claims

These claims are factually incorrect or unverifiable. They must never appear in any WB Listora marketing output.

| Forbidden claim | Why it is wrong | What to use instead |
|---|---|---|
| "Razorpay integration" | Razorpay is NOT a supported payment integration | List the 7 integrations that are supported |
| "EDD bridge" or "Easy Digital Downloads integration" | EDD is NOT supported - Pro is license-managed via wblistora.com, not EDD | Reference the license server directly |
| "199 hooks" or "199 documented hooks" | The correct count is 226 (120 actions + 106 filters). 199 was an outdated manifest summary figure corrected 2026-05-24. | "226 documented hooks" |
| "6 payment integrations" | The correct count is 7 | "7 payment integrations" |
| "WordPress 6.7+" | The correct minimum is WordPress 6.9+ | "WordPress 6.9+" |
| "Auto-renew" or "automatic renewal" | Renewal is always manual per the listing-owner journey - no auto-renew | "Manual renewal with a 7-day advance email reminder" |
| "3x conversion rate" or any conversion multiplier | No controlled study exists to back this claim | Remove entirely; use feature-specific benefits instead |
| "~50ms search" or any specific search latency claim | No benchmarked latency number has been published | "Search that scales to 100K listings" or reference the denormalized index architecture |
| "wordpress.org install count" or "X active installs" | WB Listora is a private plugin - it is not listed on wordpress.org and has no published install count | Do not reference install counts at all |
| "Free at wblistora.com" | WB Listora Free is at wblistora.com, but the product is private - do not imply public install metrics | Reference wordpress.org availability without install counts |

---

## Em-dash rule

Never use the em-dash character (`-`). Use a hyphen `-` followed by a space on each side when you need a dash break. This applies to all output: landing copy, email, social, release notes, documentation, and this guide.

Wrong: "WB Listora-the directory plugin for WordPress-ships with 226 hooks."
Right: "WB Listora - the directory plugin for WordPress - ships with 226 hooks."

Or better: restructure to avoid the dash entirely.

---

## Pricing placeholders

Wherever specific pricing would appear, use: `{{PRICING_PLACEHOLDER}}`

Do not invent pricing numbers. Pricing is set by the business and may change. The placeholder signals to any editor that this field needs to be filled from the current pricing page before publishing.
