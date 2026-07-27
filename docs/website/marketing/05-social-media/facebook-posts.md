# WB Listora - Facebook Posts

10 community-focused Facebook posts. 100-150 words each. Story-led. Every post ends with an engagement question.

Best windows for the WP groups (WP Help & Share, Advanced WordPress, WPDevs Worldwide): weekday afternoons US Eastern.

Categories: 1 launch / 3 behind-the-scenes / 2 customer success placeholders / 2 product update / 2 tip-of-the-week.

Source voice: `../hero-pitch.md`. Source claims: `../feature-matrix.md`.

---

## Launch (1)

### FB-01 - Launch announcement

We just shipped WB Listora.

A WordPress directory plugin where Free gives you the full public site (search, filters, reviews, claims, frontend submission, 6 layers of anti-spam, 11 Gutenberg blocks) and Pro gives you the business model layer on top (credit plans with Hold-and-Commit activation, lead forms, verification badges, moderators team, side-by-side comparison, and the reverse Needs Marketplace).

Built block-first on the Interactivity API. Setup wizard gets you from install to a working directory with seeded demo content in about 30 minutes.

Free and Pro both ship today at wblistora.com.

What kind of directory are you thinking of building - city guide, niche vertical, B2B marketplace, something else?

---

## Behind the scenes (3)

### FB-02 - Why the credits system holds before it commits

When we were building Pro's pricing plans, we kept hitting the same support pattern from other directories: vendor pays, listing fails moderation, refund flow. Now you owe a customer money and you owe yourself the time to issue it.

So we built credits to HOLD before they COMMIT.

A vendor submits a paid listing. The plugin checks the balance and holds the cost. The listing only debits credits when the admin (or a scoped moderator) approves it. Reject the listing and the hold releases automatically. No refund. No support ticket. No silent debit.

That decision shaped how the whole plan editor works.

Anyone here run a paid directory? How do you handle a payment for a listing that fails moderation?

---

### FB-03 - Why we chose Action Scheduler over WP-Cron

Every WordPress plugin that ships background work hits the same wall: WP-Cron drops jobs at scale because it depends on someone visiting your site to fire.

For a directory at 100K listings, that is not good enough. Search reindexing, expiration sweeps, featured rotation, draft reminders - they all need to run on schedule or your data falls behind reality.

So we vendored Action Scheduler inside Free. Not as a "Pro feature" or an "add it yourself" dependency. It ships in the box. Every Listora background job goes through it. Retries on failure. Batches across requests. Survives worker death.

What is your worst WP-Cron horror story? Mine is a daily cleanup that did not run for 11 days because the site had no anonymous traffic.

---

### FB-04 - The hardest design decision: no shortcodes

Halfway through building Listora we had to make a call. Ship a few shortcode wrappers for "compatibility" or commit to block-first all the way?

Shortcodes are easier to drop into a page builder. They feel familiar. But they hide configuration behind opaque strings, they break when content moves between sites, and they leave block-theme users with a worse experience than classic theme users.

So we shipped 11 Gutenberg blocks in Free, 5 more in Pro, all with Inspector Controls and per-instance CSS scoping. There is exactly one shortcode in the whole plugin (`[listora_compare]`), and that is only because the Compare page needs a stable URL across theme switches.

Are you on a block theme or a classic theme right now? Curious which way the room leans.

---

## Customer success placeholders (2)

### FB-05 - Customer success (city guide) - placeholder

[Customer name] runs a city guide for [city]. They came to us off [previous plugin] because their search was timing out at [listing count] listings. We helped them migrate over a weekend using the built-in migrator.

Three weeks later they turned on Pro because paid vendors started asking how they could appear higher in search results. The pricing plan editor took them an afternoon. Their first paid listing went live the same day they configured the credit packs.

[Headline metric] in [timeframe]. [Headline metric two].

What we love about this story: they did not need a developer for any of it. Just the setup wizard, the plan editor and the credit packs.

Tag a friend who runs a city guide or local-business directory.

[REPLACE BRACKETED FIELDS WITH REAL CUSTOMER DATA BEFORE PUBLISHING]

---

### FB-06 - Customer success (white-label agency) - placeholder

[Agency name] builds directories for clients across [industry vertical]. Before Listora they stitched together three plugins and a custom theme for every project.

The white-label feature is what they keep telling us closed the deal. Their directory ships under each client's brand with custom color, custom logo and no "Wbcom" wordmark anywhere in admin. Same plugin, same updates, completely different look.

They now run [number] client sites on the same base setup. New project setup time dropped from [previous] to [current].

Their lead developer's quote: "[real quote when shipping]."

Building directories for clients? What would white-label have to do for it to be worth standardizing on one plugin across all your projects?

[REPLACE BRACKETED FIELDS WITH REAL CUSTOMER DATA BEFORE PUBLISHING]

---

## Product update (2)

### FB-07 - "Search this area" map bounds

Small update worth highlighting. The map block now supports "search this area".

If a visitor drags the map after running a search, they see a button to research the visible area. Hit it and the grid filters down to listings inside the current viewport. Refresh the page and the bounds persist.

It is one of those features that you only notice if it is missing. Almost every map app does this (Google Maps, Airbnb, Zillow). Now Listora maps do too.

Free feature. Already shipping.

What other map interactions would you like to see? Heat maps? Clustering thresholds? Better mobile gesture handling?

---

### FB-08 - Approve and Reject row actions on the listings list

If you have ever moderated a long pending queue from wp-admin, you know the pain: open each listing, scroll to the status, change it, save, go back, repeat.

The latest Listora update adds one-click Approve and Reject row actions directly on the listings list, next to View and Edit. Pending listings get the actions. Already-published listings do not.

A single click transitions the status and refreshes the list. Admin notice confirms the action. No detail-page round trip.

Tiny change, big quality-of-life win for high-volume directories.

What is your moderation queue looking like this week? Mostly real submissions or mostly spam attempts?

---

## Tip of the week (2)

### FB-09 - Tip: seed 9 demo packs in one CLI command

If you are evaluating Listora or building a demo for a client, do not start from an empty directory. Run:

`wp listora demo seed --pack=all`

That loads 9 verticals (restaurant, hotel, real-estate, job-board, general, classified, education, healthcare, place) with 128 plus seeded listings, images, taxonomies and reviews. You can then trim down to the verticals that match your project.

If you prefer a single vertical: `wp listora demo seed --pack=restaurant`.

To clean up later: `wp listora demo remove --pack=all`.

The setup wizard offers a subset of demo packs at first run, but the CLI is faster if you know what you want.

Which vertical are you most curious about?

---

### FB-10 - Tip: layer your anti-spam before you launch

Submissions require a logged-in account, so bots are already fighting uphill. But for a public directory, stack the anti-spam layers Free ships with and your moderation queue stays mostly real.

How it works: honeypot fields catch scripted bots, per-IP sliding-window rate limits stop floods, CAPTCHA (reCAPTCHA v3 or Cloudflare Turnstile) blocks the rest, and Akismet screens reviews and claims. Add a keyword blacklist and URL-density cap for the stubborn cases.

All of it is in Free, and most of it is on by default.

Which anti-spam layer do you lean on most: CAPTCHA, rate limits, or Akismet?
