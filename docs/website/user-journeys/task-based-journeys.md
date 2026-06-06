# Task-Based Journeys

Ten specific "I want to X" flows, each scoped to a real persona doing a real task. Use these when you are writing a help article, drafting a support reply, or planning an onboarding email - they are the shortest path between "the customer's words" and "the screens we ship."

## Index

| # | Task | Persona | Tier |
|---|---|---|---|
| 1 | Add my business to the directory | Listing Owner | Free |
| 2 | Claim an existing listing I see is mine | Listing Owner | Free |
| 3 | Upgrade my listing to Featured | Listing Owner | Pro |
| 4 | Promote my plumbing services to people who need them | Listing Owner | Pro (Needs Marketplace) |
| 5 | Find a restaurant near me right now | Visitor | Free |
| 6 | Compare 3 dentists side by side | Visitor | Pro |
| 7 | Be notified when a new venue opens in my city | Visitor | Pro (Saved Searches) |
| 8 | Add a teammate who can only moderate reviews | Site Owner | Pro (Moderators) |
| 9 | Start charging vendors for premium placement | Site Owner | Pro (Pricing Plans) |
| 10 | Migrate 5,000 listings from Directorist without downtime | Site Owner | Free (Migration) |

---

## 1. "I want to add my business to the directory" (Listing Owner)

**Setup needed:** None. If the operator allows guest submissions you do not even need an account up front - you will verify your email at the end.

**Steps:**

1. Land on the directory home. Click the **Add Listing** CTA in the header or hero.
2. The submission wizard opens. Pick your listing type (Restaurant, Hotel, Service, …) - the form re-shapes per type.
3. Fill 4-6 steps: Basics → Details → Media → Contact → Hours (and Services / Plan if the operator enabled them). Drafts auto-save so you can step away and resume.
4. Drag the map pin to the exact storefront. Upload your gallery (1200px or larger - thumbnails are generated for you).
5. Click Submit. If the operator auto-publishes, your listing is live. If they moderate, you get a confirmation email + a second email when it is approved or rejected. If a plan requires credits and your balance is short, the listing saves with status "Awaiting Credits" + a Buy Credits CTA.

**Expected result:** Listing live on the directory with a public URL, or in the pending queue with a clear next step.

**What could go wrong + recovery:**

| Problem | Recovery |
|---|---|
| Submit fails on a required field hidden by conditional logic | Scroll up - the wizard highlights the missing field on the relevant step |
| Email verification link expired | Click **Resend** in the same screen - 5-minute rate-limit only |
| Submission stuck on "Awaiting Credits" | Visit `/buy-credits/` (or the operator's named credit-purchase page) and top up. The listing auto-activates the moment your balance covers the plan cost (Hold-and-Commit pattern) |

---

## 2. "I want to claim an existing listing I see is mine" (Listing Owner)

**Setup needed:** A WordPress account on the directory (free signup). The listing must already exist - someone added it before you found this directory.

**Steps:**

1. Find your business listing through search.
2. Click **"Claim this business"** at the top of the listing detail.
3. Upload proof of ownership - utility bill, business license, or anything that ties you to the address.
4. Submit. You get a confirmation email. The operator (or a moderator with claims scope) reviews your proof.
5. On approval, the listing's `post_author` transfers to you. You receive a `claim_approved` email + the listing now appears in your dashboard at `/my-dashboard/` (or `/my-listings/` if the operator stuck with the legacy slug). You can now edit, reply to reviews, manage services.

**Expected result:** Ownership of the listing transferred to your account, with full edit access from the frontend dashboard.

**What could go wrong + recovery:**

| Problem | Recovery |
|---|---|
| Claim rejected because the proof was unclear | Operator emails you with the specific reason. Upload better proof and re-submit |
| Multiple people claim the same listing | First valid claim wins. Subsequent claims are auto-rejected |
| You cannot find a "Claim this business" button | Operator disabled claims in Settings → Submissions. Contact them directly |

---

## 3. "I want to upgrade my listing to Featured" (Listing Owner, Pro)

**Setup needed:** Pro must be active on the site. The operator must have configured pricing for Featured placement.

**Steps:**

1. Open your dashboard → **Listings** tab.
2. Find the listing row. Click the **Feature** action.
3. The plan selector shows the credit cost for N days of Featured rotation. Confirm.
4. Credits are deducted. Your listing rotates into the homepage Featured carousel for the chosen duration. A scheduled job (`wb_listora_featured_rotation`) re-evaluates the carousel via Action Scheduler.

**Expected result:** Your listing appears in the Featured carousel + carries a "Featured" badge on its card across the directory until the entitlement expires.

**What could go wrong + recovery:**

| Problem | Recovery |
|---|---|
| Not enough credits | Visit `/buy-credits/` and top up |
| Featured rotation seems stuck on the same 3 listings | The carousel rotates with Action Scheduler. Site Owner can re-trigger via the Pro admin or `wp listora-pro featured-rotate` |
| You unfeature accidentally | Per-listing actions are reversible up until the credits are committed. After that, the cost is non-refundable by default |

---

## 4. "I want to promote my plumbing services to people who need them" (Listing Owner, Pro Needs Marketplace)

**Setup needed:** Pro + the Needs Marketplace feature toggle ON. Your listing must be in a category that buyers post needs in.

**Steps:**

1. Visit `/needs/` - the marketplace CPT archive Pro auto-creates.
2. Filter by type / urgency / location. Find open buyer needs in your category ("plumber for water heater, Brooklyn, urgent").
3. Click into a need. Read the full request.
4. Click **Respond**. Write your quote (price + lead time + a short message).
5. Submit. The buyer sees your response in their dashboard → **Needs** tab. If they accept, they reach out via the message thread.
6. Track your sent quotes in your dashboard → **My Responses** tab.

**Expected result:** Direct, buyer-initiated lead flow without paying per click - the buyer came to you with intent.

**What could go wrong + recovery:**

| Problem | Recovery |
|---|---|
| You cannot find `/needs/` | Operator did not enable the `reverse_listings` toggle. Ask them to flip it ON |
| Your response is invisible to other businesses | Responses are private to the buyer + the operator. By design |
| Need was deleted before you could respond | Needs expire via the `wb_listora_pro_expire_needs` job when the operator's expiry window passes. Faster bird gets the worm |

---

## 5. "I want to find a restaurant near me right now" (Visitor)

**Setup needed:** None. Browse anonymously. The browser will ask for location permission when you tap **Near Me**.

**Steps:**

1. Land on the directory home or `/listings/`.
2. Type "italian" (or whatever cuisine) in the search bar - autocomplete suggests as you type.
3. Click the **Near Me** button. Allow location. Results re-sort by distance via the Haversine query.
4. Toggle **Open Now** if the directory has business-hours filtering enabled. Tick **Outdoor Seating** in the facet sidebar if you want it.
5. Click a card. Read the detail. Click the phone number (mobile launches dialer) or **Get Directions** (opens Maps).

**Expected result:** A short list of relevant, open, nearby restaurants - decision-ready in under 30 seconds.

**What could go wrong + recovery:**

| Problem | Recovery |
|---|---|
| "No results" for a common query | Operator should reindex search (Settings → Advanced → Rebuild Search Index) or check that the facet is visible |
| Map shows no markers | Operator did not set the Google Maps API key (Pro) or fell back to Leaflet + OSM (Free, no key) |
| Location prompt was denied | Re-enable in browser site settings, refresh, click Near Me again |

---

## 6. "I want to compare 3 dentists side by side" (Visitor, Pro)

**Setup needed:** The operator must have Pro enabled with the Compare feature ON.

**Steps:**

1. Search for "dentist" + your area.
2. On each candidate card, tick the **Compare** checkbox.
3. A floating bar appears showing "3 selected." Click **Compare now**.
4. The comparison table opens with all three side-by-side: price, rating, hours, services, amenities, distance, reviews summary.
5. Pick your winner. Open the detail page. Contact directly.

**Expected result:** One-screen decision instead of 3 open browser tabs.

**What could go wrong + recovery:**

| Problem | Recovery |
|---|---|
| Compare checkbox missing on cards | Feature is Pro-only. Operator needs to flip the `comparison` toggle in Pro Features |
| Compare table is missing a field you care about | Operator can extend the comparison via the `wb_listora_compare_table_columns` filter or template override |
| You exceed 4 listings | The hard cap is 4 - any more would not fit on a typical screen. Remove one to add another |

---

## 7. "I want to be notified when a new venue opens in my city" (Visitor, Pro Saved Searches)

**Setup needed:** Pro + Saved Searches feature ON. You need a free account so the alerts have somewhere to go.

**Steps:**

1. Run your normal search ("event venues in Queens, capacity 100+").
2. From the results page, click **Save this search**.
3. Name it ("Queens venues 100+"). Toggle email alerts ON.
4. From now on, every time a new listing is published that matches all the filters in your saved search, you get an email.
5. Manage / pause / delete in your dashboard → **Saved Searches** tab.

**Expected result:** A passive lead-generation loop - the directory tells you when something new fits, instead of you re-searching every week.

**What could go wrong + recovery:**

| Problem | Recovery |
|---|---|
| Save this search button missing | Pro feature not enabled, or you are not logged in |
| Alerts arriving too often | Pause the saved search from the dashboard, or tighten your filters |
| New listings match but no email arrived | Check spam folder + your account email. Email Log (operator side) confirms whether the email was dispatched |

---

## 8. "I want to add a teammate who can only moderate reviews" (Site Owner, Pro Moderators)

**Setup needed:** Pro + the Moderators feature toggle ON. The teammate needs an existing WordPress user account on your site.

**Steps:**

1. Open **Listora → Moderators** in the admin (requires `manage_listora_moderators` - admin only).
2. Click **Add Moderator**. Search for the teammate's WP user.
3. Select the **Reviews** scope only. Leave Listings / Claims / Reports off.
4. Save. The teammate receives an email notification telling them they have been assigned.
5. When they log in, their sidebar surfaces only the moderation-relevant screens (in this case, **Reviews**). They do not see Settings, Pricing Plans, Coupons, Webhooks, Audit Log, Email Log, or the Moderators page itself.

**Expected result:** A capped-permission teammate who can approve / reject reviews and nothing else - enforced at the capability layer, not just hidden UI.

**What could go wrong + recovery:**

| Problem | Recovery |
|---|---|
| Teammate cannot find the Reviews screen | They need to log out and back in for the new capability map to take effect |
| You want to remove them later | Same Moderators page → remove. The capability is revoked immediately on next page load |
| Teammate accidentally given Settings access | Only admins (`manage_listora_settings`) can see Settings. Moderator scopes do not include it - this would mean their WP role itself is too high |

---

## 9. "I want to start charging vendors for premium placement" (Site Owner, Pro Pricing Plans)

**Setup needed:** Pro active + at least one payment integration configured (Stripe direct, PayPal direct, or one of: WooCommerce, WooSubscriptions, MemberPress, Paid Memberships Pro, WooMemberships).

**Steps:**

1. Open **Listora → Credit Packs**. Define your credit-pack pricing (e.g. "5 credits / $25", "20 credits / $80", "100 credits / $300").
2. Open **Listora → Pricing Plans**. Create your tier set (e.g. Basic / Featured / Premium) - each plan has credit cost + duration + entitlements (Featured rotation, photo count, etc.).
3. Connect your gateway via the SDK consumer config (Pro setup wizard step 3, or Settings → Credits).
4. Update your submission wizard's Plan step to show your plans + cost in credits.
5. Run a test submission yourself with a Stripe test card or PayPal sandbox account. Confirm credits land in your test user's balance.
6. Switch the gateway from test → live. Vendors now top up + pay per listing via the Hold-and-Commit pattern - credits hold on submission, commit when the listing is approved.

**Expected result:** Vendors can buy credits, pick a plan, and your directory earns revenue without you building any payment plumbing.

**What could go wrong + recovery:**

| Problem | Recovery |
|---|---|
| Plan picker shows no plans | Plans need at least one credit cost + duration. Saving a plan with empty fields fails silently in some older themes - check browser console |
| Vendor paid via WooCommerce but credits did not land | Bridge order completion is required. Check that the WC order moved to `completed`, not just `processing`. Webhook receiver logs are in **Pro → Webhooks** |
| Submission gets stuck on "Awaiting Credits" even though credits exist | The plan cost meta key is canonical at `_listora_plan_credits`. If a custom integration writes to the old `_listora_plan_credit_cost`, activation fails. INV-13 guards against this in the architecture check |

---

## 10. "I want to migrate 5,000 listings from Directorist without downtime" (Site Owner, Free Migration)

**Setup needed:** WP-CLI access (SSH to your host). The source Directorist plugin must still be installed and active so the data is readable. Take a full DB backup before starting.

**Steps:**

1. SSH to the site. Run a dry-run first:
```
wp listora migrate --from=directorist --dry-run
```
This reports what would be migrated without writing anything. Review the count + sample output.
2. Run the real migration:
```
wp listora migrate --from=directorist
```
The migrator (extending `Migration_Base` in Free's `includes/import-export/`) reads Directorist's schema (documented at `audit/architecture/competitor-schemas/directorist.md`), maps fields, and writes new `listora_listing` posts. Each migrated listing fires `wb_listora_listing_submitted` with `'source' => 'migration'` context, so the notifications listener does NOT email the admin for every legacy entry.
3. Watch the progress in the terminal. 5,000 listings on average hardware takes 15-30 minutes.
4. After migration, reindex search:
```
wp listora reindex
```
5. Spot-check 10 random migrated listings on the frontend. Verify categories, locations, gallery images, hours.
6. Optionally deactivate Directorist once you are happy. Original posts are NOT deleted - they remain as `directorist_listing` posts and can be removed manually after a verification window.

**Expected result:** 5,000 listings in WB Listora with full taxonomy mapping, intact media, no flood of admin emails, and search indexed. Site remains live throughout - the migrator does not lock tables or take down the frontend.

**What could go wrong + recovery:**

| Problem | Recovery |
|---|---|
| Migration runs out of memory | Re-run with `--batch=200` to process in smaller chunks |
| A field did not map | Check `audit/architecture/competitor-schemas/directorist.md`. If the field is genuinely missing, file a Free PR adding it to the migrator's `extract_*` template method |
| Duplicate listings | The migrator stamps each new listing with `_listora_migrated_from`. Re-running the migration skips already-migrated source posts via the `Migrated_From_Tracker` deduplication |
| Visitor sees stale facet counts | Run `wp listora reindex` again - facet counts come from the denormalized `search_index` table and refresh on reindex |

If you want the visual mapping UI (drag-drop source-to-target field assignment, import preview, saved templates), upgrade to **Pro Visual Importer**. The same migration pipeline runs underneath - Pro only adds the premium UX wrapper.

---

## How to extend this list

When a new task pattern surfaces in support tickets ("how do I X?"), promote it here. Each entry should be:

- 1-2 paragraphs total
- A real, single-sentence task statement in the customer's voice
- Setup → steps → expected result → what-could-go-wrong table
- Anchored to a specific persona (no "anyone can do this")

If a task does not have a clean answer yet, that is a feature brief. File it.
