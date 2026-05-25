# WB Listora - Re-engagement Sequence (3 Emails)

Win-back sequence for users who installed WB Listora but haven't logged in for 30+ days. Assumes the site is still active (WordPress is running) but the user isn't actively managing the directory.

Trigger: 30 days of inactivity on the directory admin.

---

## Email 1 - Day 30: Soft check-in

**Subject:** Your directory is still there. Want to pick it up?

**Preview text:** A few things have shipped since you were last in.

---

Hi {{first_name}},

It's been a while since you were last in WB Listora.

Your directory is still there - listings, reviews, and settings all intact. Nothing was lost.

We've shipped a few things worth knowing about since you were last active:

- Search now preserves URL state so visitors can bookmark and share exact filter sets
- Business hours editor now supports timezone selection per listing
- Geo "Search this area" updates results as visitors drag the map - no reload
- Email log shows delivery status for every notification sent

If there's something that stopped you from moving forward - a feature that wasn't there, a setup step that got confusing, or something that just didn't work - reply to this email and we'll help sort it out.

No obligation. Your license is still active.

The Wbcom Designs Team

---

## Email 2 - Day 37: Problem-solving

**Subject:** What usually stops people at this stage

**Preview text:** Three common sticking points - and how to get past them.

---

Hi {{first_name}},

Based on where people usually drop off, here are the three most common sticking points - and how to get past each one.

**Sticking point 1: "I don't have enough content to launch."**

You don't need real listings to test and learn. Load demo data: `wp listora demo seed --pack=restaurant` seeds 20+ realistic listings so you can see what the search, filters, and detail pages look like with actual content. Remove it anytime with `wp listora demo remove`.

**Sticking point 2: "I wasn't sure how to get vendors to submit."**

The Add Listing page auto-created by the setup wizard is your submission entry point. Share the URL. For the first cohort of vendors, it sometimes helps to submit the first few listings yourself as examples of what a complete listing looks like - vendors copy the quality level they see.

**Sticking point 3: "I needed a feature that wasn't there."**

Reply to this email with what you were looking for. We can point you to an existing feature you may have missed, tell you what's coming on the roadmap, or give you an honest answer if it's not something we're building.

The Wbcom Designs Team

---

## Email 3 - Day 44: Last check-in

**Subject:** Last note from us for a while

**Preview text:** If the directory isn't the right project right now, that's fine.

---

Hi {{first_name}},

This is the last email we'll send as part of this check-in sequence.

If the directory project isn't the right thing to be working on right now - timing changed, priorities shifted, the use case evolved - that's completely fine. Your license is still active and the plugin will be here when the time is right.

If you do want to pick it up again, the two fastest paths back:

**Option A: Jump back into the setup wizard.** Go to WB Listora in your admin. The wizard doesn't overwrite anything you've already done - you can re-run individual steps.

**Option B: Email us.** support@wbcomdesigns.com. Tell us where you got stuck and what you were trying to build. We'll give you a direct answer, not a link to documentation.

The Wbcom Designs Team

P.S. If there's a specific feature you needed that we don't have, we'd genuinely like to know. Reply with what it is - it goes into the product roadmap review.
