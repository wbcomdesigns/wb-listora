# WB Listora — Welcome Email Sequence (5 Emails)

Onboarding drip for new Free users. Trigger: plugin activation or first-time download. Assumes the user has installed but may not have run the setup wizard yet.

---

## Email 1 — Day 0: Welcome + First Step

**Subject:** Your WB Listora directory is ready to set up

**Preview text:** One wizard. Thirty minutes. A working directory.

---

Hi {{first_name}},

Thanks for installing WB Listora.

You now have a WordPress plugin that can run a complete business directory — search, reviews, claims, maps, frontend submission, and spam protection — all without writing a line of code.

The fastest path to something working:

**Run the setup wizard.**

It takes about 30 minutes and handles everything automatically: picks your listing types, creates the Add Listing, My Listings, and Directory pages, and gives you the option to load demo data so you're browsing a real-looking directory by the end.

Go to your WordPress admin and look for "WB Listora" in the left sidebar. The wizard starts automatically on first install.

Questions? Just reply to this email.

The Wbcom Designs Team

---

## Email 2 — Day 2: First Setup Steps

**Subject:** 3 things worth doing before you launch

**Preview text:** Set your listing types, test your spam filters, send a test email.

---

Hi {{first_name}},

If you ran the setup wizard, your directory is already live. If you haven't yet — go to WB Listora in your WordPress admin. It takes about 30 minutes.

Here are three things worth doing before you share the link publicly:

**1. Set your listing types and categories.**

Go to Listora → Listing Types and customize the types you chose in the wizard. Rename them, change icons, add or remove custom fields. Then go to Listora → Categories and build out your taxonomy tree. Visitors filter by categories — a well-organized taxonomy makes a big difference.

**2. Test your spam protection.**

Settings → Security. Turn on reCAPTCHA v3 or Cloudflare Turnstile (Turnstile is GDPR-friendly and doesn't show a puzzle). Both are free. Don't skip this — without it, a busy directory will collect spam reviews within days.

**3. Send a test email.**

Settings → Notifications → Send Test Email. If the test doesn't arrive, your SMTP configuration needs attention before you go live. Better to catch it now.

Once those three are done, you're ready to start onboarding vendors.

The Wbcom Designs Team

---

## Email 3 — Day 5: First Listing

**Subject:** Ready to add your first real listing?

**Preview text:** Four options — pick the one that fits your situation.

---

Hi {{first_name}},

Your directory works best with real content in it. Here are four ways to get your first listings in:

**Option A: Load demo data.**
`wp listora demo seed --pack=restaurant` adds 20+ realistic restaurant listings so you can test the search and filters without manually creating content. Remove it later with `wp listora demo remove`.

**Option B: Submit through the wizard yourself.**
Visit your Add Listing page as a visitor would. Walk through the wizard end-to-end — it's a good test of the form, and your first real listing shows up immediately.

**Option C: Import from a CSV.**
If you have a spreadsheet of businesses, format it as CSV and import via Settings → Import/Export. Column headers map to listing fields — title, description, category, location, website, phone.

**Option D: Migrate from another plugin.**
If you're coming from Directorist, GeoDirectory, WPBDP, or ListingPro, the migration tool is built in. Run `wp listora migrate --from=directorist --dry-run` to preview before importing.

Pick the option that fits and get some listings in. The directory experience is completely different with 20 listings versus 0.

The Wbcom Designs Team

---

## Email 4 — Day 9: Reviews + Claims

**Subject:** How reviews and business claims work

**Preview text:** Trust infrastructure is what keeps visitors coming back.

---

Hi {{first_name}},

Two features worth turning on if you haven't already: reviews and business claims.

**Reviews**

Settings → Reviews lets you choose between auto-approve (reviews go live immediately) and moderation-required (you approve each one). For a new directory, auto-approve is fine — you can switch later. Set a minimum review length of 50-100 characters to avoid one-word reviews.

Visitors submit reviews from the listing detail page. Owners reply from their frontend dashboard — they never need wp-admin access. Helpful votes and a report-a-review workflow are included automatically.

**Business Claims**

Every listing that hasn't been claimed shows a "Claim this business" button. Business owners click it, upload proof of ownership, and you approve or reject from Listora → Claims. On approval, the listing author transfers to them — they become the owner and can edit, reply to reviews, and manage services.

Claims keep your directory healthy. An unclaimed listing is eventually outdated. A claimed listing gets maintained by the person who actually knows the business.

If you want deeper trust features — multi-criteria reviews with per-aspect scores, photo reviews, and verification badges — those are in Pro.

The Wbcom Designs Team

---

## Email 5 — Day 14: Upgrade Nudge

**Subject:** When does it make sense to add Pro?

**Preview text:** Here's the honest answer.

---

Hi {{first_name}},

You've been running WB Listora for two weeks now. Free covers a lot — and plenty of directories run on Free indefinitely.

Here's the honest breakdown of when Pro makes sense:

**You need Pro if you want to charge vendors.**
Credit-based pricing plans, 7 payment gateways (WooCommerce, WooCommerce Subscriptions, MemberPress, Paid Memberships Pro, WooMemberships, Stripe, PayPal), coupons, and featured rotation. Pro handles all of it — you define the plans and pricing, it handles the rest.

**You need Pro if you want real lead capture.**
The basic contact form in Free forwards messages. Pro's Lead Forms replace it with analytics, custom questions, and conversion tracking — so you know which listings generate leads and which don't.

**You need Pro if you're building a marketplace.**
The Needs Marketplace lets buyers post what they're looking for. Businesses respond with quotes. It's a second flywheel in the same plugin — most directory plugins don't have it at all.

**You don't need Pro if you're running a free open directory.**
Free is complete for public directories where vendors don't pay and you're not tracking lead conversions. Plenty of community guides and niche directories run on Free long-term.

If Pro sounds like the right next step, current pricing is at wblistora.com.

Any questions — just reply.

The Wbcom Designs Team
