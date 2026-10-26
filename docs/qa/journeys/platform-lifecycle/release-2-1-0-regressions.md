---
journey: release-2-1-0-regressions
group: platform-lifecycle
actor: admin
mode: mixed
plugin: wb-listora
priority: high
roles: [admin, owner, other, guest]
covers: [search, dashboard, listing-detail, quick-view, settings, leads, coupons, analytics, submission]
prerequisites:
  - "Free and Pro 2.1.0 active, the model-site seed loaded, at least 21 listings owned by {admin}"
  - "One listing {listing} with at least one approved review by another member"
estimated_runtime_minutes: 30
last_verified: 2026-10-23
verified_against: free 2.1.0 branch / pro 2.1.0 branch
viewports: [1440, 390]
themes: [buddyx-pro, buddyx, twentytwentyfive, twentytwentyfour]
supersedes: []
fails_on: unrecorded
site_profile: required
---

# The 2.1.0 fix classes stay fixed

## Why
Each step is a bounce that shipped once and was fixed in 2.1.0: the check that would have caught it. Steps are tagged `card:<id>` (Basecamp card) so a regression names its origin.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).

## Steps

### 1. Save shows its state (card:10417835581)
- **Action**: as {owner} open `{listing}`, click Save, reload, click again.
- **Expect**: after the first click the button reads "Saved" with a filled heart and `aria-pressed=true`, and still does after reload; the second click returns to "Save" with an outline heart.

### 2. Guest Save opens the login dialog on any page
- **Action**: logged out, click the heart on a listing page and on a directory card.
- **Expect**: a centred, overlaid "Log in to save listings" dialog; Escape closes it. Holds on all four themes.

### 3. Coupon reaches the summary (card:10412785095)
- **Action**: as {owner} with credits, start Add Listing, reach the Plan step, apply a 50% coupon, Continue to Preview.
- **Expect**: "This listing will cost" and "Remaining after submit" use the discounted cost shown on the plan card.

### 4. Quick View price label (card:10417899315)
- **Action**: open Quick View on a listing with a price range.
- **Expect**: the line reads "$$ — Moderate" with no extra dollar icon.

### 5. Pager only under a list (card:10412444371)
- **Action**: as {admin} (more than 20 listings) open `/my-listings/?tab=listings&action=add`.
- **Expect**: no "Listings pagination" nav anywhere in the add form; `?tab=listings` still shows it.

### 6. Analytics tab pages (card:10398461494)
- **Action**: as {admin} open `?tab=analytics`, `&analytics_page=2`, `&analytics_page=99`, and 90d on page 2.
- **Expect**: 20 rows per page, the pager keeps `analytics_period`, page 99 shows page 1, the chart covers all listings.

### 7. Coupon usage log pages (card:10398479718)
- **Action**: as {admin} open a coupon with more than 20 redemptions, then `&usage_page=3` and `&usage_page=99`.
- **Expect**: a redemption count in the card head, 20 rows per page, a pager, page 99 shows the last page.

### 8. Lead drawer shows everything (card:10408498363)
- **Action**: Listora > Leads > Details on a lead with email and phone.
- **Expect**: name, email, phone, listing, received time and the message.

### 9. Dashboard cards link to what they count (card:10407726629)
- **Action**: Listora Dashboard > Leads card.
- **Expect**: opens Leads filtered to 30 days, not Analytics.

### 10. Wp-admin stays light under a dark OS (card:10412384633)
- **Action**: emulate `prefers-color-scheme: dark`; open Upgrade to Pro (Pro off), Transactions > Credits and Pricing Plans.
- **Expect**: cards are white with dark text; no near-black panels on the light admin.

### 11. Add New opens with fields (card:10417571068)
- **Action**: Listings > Add New; then `post-new.php?post_type=listora_listing&listing_type=restaurant`; then `listing_type=nope`.
- **Expect**: no type: a "Start with a type" link list; restaurant: its field groups are on screen with the type selected; unknown slug assigns nothing.

### 12. Services row on a phone (card:10417590503)
- **Action**: at 390px open Add New with a type that has services.
- **Expect**: each service row is a stack of labelled fields with no sideways scroll; at 1440 it is the eight-column table.

### 13. Settings Documentation links (card:10412655756)
- **Action**: on each settings tab with a header, read `.listora-docs-link`.
- **Expect**: `https://docs.wbcomdesigns.com/products/listora/<path>/`, a different page per tab, each returning 200.

### 14. Attribute-safe contexts (card:10412877534)
- **Action**: set a reviewer's display name to `O'Brien" data-x="1`, view the listing as {other}; add an owner reply containing an apostrophe and open the dashboard Reviews tab.
- **Expect**: no element gains a `data-x` attribute; the reply row still opens. Restore the name.

### 15. Writes that fail do not half-complete (cards:10412882782, 10412889678, 10412889980, 10412892126)
- **Action**: `wp phpunit` (or the plugin's PHPUnit) groups `claims` and `ClaimApprovalOrderTest`, `ClaimInsertFailureTest`, `MigratorStampTest`.
- **Expect**: all pass; each fails on the pre-fix code.

### 16. Room above the footer (cards:10407980432, 10407646913)
- **Action**: on Reign 8.0.8 and BuddyX Pro, at 1440 and 390, light and dark, open the directory, every dashboard tab, Add Listing, a single listing, Needs and Compare; scroll to the bottom.
- **Expect**: at least 48px between the last card, pager or panel and the footer on every page.

### 17. Coupon reaches the banner on the single form (card:10412785095)
- **Action**: as {owner} open `/my-listings/?tab=listings&action=add` (single form), pick a paid plan, apply a valid percentage coupon, read the Preview banner without changing step; then apply an expired code.
- **Expect**: the banner cost and "Remaining after submit" use the discounted price at once, keep decimals (87.13, not 87), and go back to the full price when the coupon is refused.

### 18. Stacked Services controls keep a sensible width (card:10417590503)
- **Action**: Edit Listing in the block editor at 1440 with the settings sidebar open, then 1024 and 390; type 1999.99 in a service price.
- **Expect**: the row stacks; Price, Type, Duration and Status are at most 24rem wide, Title and Description full width, the Choose button sized to its label, nothing clipped.

### 19. An unset category colour reads as unset (card:10417745331)
- **Action**: Categories > Add New without touching the colour; then pick one and press "Use the type's colour".
- **Expect**: a dimmed neutral swatch with "Not set: uses the listing type's colour" and no button; picking shows the colour and the button; clearing returns to unset and stores nothing.

### 20. Profile photo hint and link agree (card:10412890500)
- **Action**: as {owner} open Dashboard > Profile on BuddyPress 12+, on BuddyBoss, and with no community plugin.
- **Expect**: "Managed in your community profile." always pairs with the member's change-avatar link; "Managed by Gravatar" always pairs with gravatar.com.

### 21. Filters reach listings past the 5,000-row cap (card:10400674702)
- **Action**: add 6,000 newer filler rows to `wp_listora_search_index` (listing_id 900000+), open `/listings/?listora_category=<a category with 1 listing>`, call `/wp-json/listora/v1/search?facets=1`, then delete the rows.
- **Expect**: the directory shows "Showing 1-1 of 1" with that listing; location, feature, tag, custom-field (value, any-of, min/max), event date and Open now filters each return the same total as before the filler rows; the facets call reports the full total, not 5,000; the last page is reachable; a radius search leaves out listings in the bounding box's corners.

### 22. "Listed by" never opens an empty archive (card:10408075420)
- **Action**: open a listing whose owner has no blog posts, then one whose owner has a post, on a plain site and on BuddyPress.
- **Expect**: no posts and no community plugin: the name is plain text; with posts: it links to the author archive; on BuddyPress: it links to the member profile; the `wb_listora_listing_owner_url` filter still wins.

### 23. Webhook secrets stay masked (card:10421884411)
- **Action**: as {admin} open Settings > Credits > Payments, then Tools > Webhooks > Add Webhook, at 1440 and 390; press Show, Hide, then Copy and paste the clipboard somewhere.
- **Expect**: both secrets render as dots; Show reveals and Hide re-masks; Copy pastes the full secret on http and https; the Payments secret appears once in the page source (the field's value), not again on the Copy button.

### 24. A Stripe purchase is confirmed by webhook and a refund takes the credits back (card:10421654022)
- **Action**: with Stripe test keys saved and `stripe listen --events checkout.session.completed,charge.refunded --forward-to <site>/wp-json/wbcom-credits/v1/wb-listora/webhook/stripe` running (its `whsec_` in the Webhook signing secret), add a Direct pack, then as {owner} buy it from Dashboard > Credits with card 4242 4242 4242 4242. Refund the payment in Stripe, then resend both events with `stripe events resend <evt>`. Repeat with an older SDK copy (1.10.1 or 1.9.4) loaded ahead of Listora's.
- **Expect**: the billing form starts with the member's profile first and last name and account email; Stripe returns the buyer to Dashboard > Credits with "N credits added."; `stripe listen` shows `[200]` for both events, never `[400]`; the balance goes up once and comes back down after the refund; the resends add no ledger row; the gateway log row carries the webhook's event id; with the older copy loaded the site still reports `WBCOM_CREDITS_SDK_VERSION` 1.10.3 and the webhook still returns 200.

### 25. Directory and Post a Need layout (card:10436016856, card:10435953952, card:10436060953)
- **Action**: at 1440 and 390, open the Directory page with Search, Map and Grid blocks stacked; open Filters, then Save This Search, then Filters again. Post a Need as {owner}.
- **Expect**: the map and the "Showing …" toolbar have the same gap as the search bar and map (about 24px), not 0; only one of Filters and Save This Search is open at a time, whichever was opened last, and opening Filters focuses its first field; after a Need is posted, "Go to My Needs" is as wide as the Submit button.

### 26. A category with no colour takes its type's everywhere (card:10417745331)
- **Action**: call `GET /wp-json/listora/v1/listing-types/restaurant/categories`; add an event listing dated this month in a category with no colour and open the Listing Calendar page; give that category its own colour and reload.
- **Expect**: categories with no stored colour return the restaurant type's colour, not `""`; the calendar event carries the event type's colour (`--event-color`); once the category has a colour, REST and the calendar show the category's own.

### 27. The address picker never picks for the member (card:10436455532)
- **Action**: on Add Listing, type `28186 Bear Creek Road` in a Map Location address and press Enter; press Enter again; then ArrowDown and Enter. Search `Beau Jo's Pizza Evergreen`.
- **Expect**: after the first Enter the list shows the address only (no shops, offices or restaurants sharing it) with no row highlighted; the second Enter leaves the typed text unchanged; ArrowDown highlights row 1 and Enter fills the address and lat/lng; a business-name search with no plain address match still lists the business. Same in the wp-admin listing editor.

### 28. Phone layouts of the dashboard and listing page (cards:10436133910, 10436386322, 10436411060, 10436431362, 10436449249)
- **Action**: at 390, open My Dashboard; Edit a listing from it; open the Directory and press List; open a listing as its owner with Pro active.
- **Expect**: every dashboard tab is one line in the sideways strip; the Edit Listing card lines up with the tab bar (same left and right edges); List view shows each card as a row with a small thumbnail, different from Grid; Share, Add to Compare and "Feature for … credits · 30 days" each keep their label on one line; the sticky Call / Visit / Save bar has padding below its buttons. At 1440 nothing changes except the edit card lining up with the dashboard column.

## Role ladder
| Actor | Expect |
|---|---|
| {owner} (Subscriber) | steps 1-4 work; wp-admin screens in steps 6-13 return 403 |
| {other} | step 14 shows no injected attribute |
| guest | step 2 only |

## Pass criteria
1. Every step's Expect holds on the four themes listed, at 1440 and 390 where the step names a viewport.
2. Steps 14 and 15 fail on the commit before the fix named by their card.

## Fail diagnostics
| Symptom | File |
|---|---|
| Save label or heart unchanged | `blocks/listing-detail/render.php` (`state.isFavorited` spans), `blocks/listing-detail/style.css` |
| Summary ignores the coupon | `src/plan-coupon.js` (Pro, `discountedCredits`), `src/blocks/listing-submission/view.js` (`updateCreditBanner`) |
| Pager under the add form | `templates/blocks/user-dashboard/tab-listings.php` |
| Context attribute breaks out | any template with a bare `wp_json_encode()` in `data-wp-context` (coding-rules Rule 21) |
| Admin cards dark on a dark OS | `assets/css/admin/pro-promotion.css`, Pro `assets/css/pro-admin.css` |
| Add New has no fields | `includes/admin/class-listing-type-metabox.php` (`type_for_new_listing`) |

## Not verified
Real PayPal checkout, a real reCAPTCHA key, BuddyBoss and Reign, a Subscriber with more than 20 listings on the Analytics tab, and a live upgrade from a 2.0.0 database with a table dropped.

## Cleanup
Restore any reviewer display name, delete QA coupons and leads, and switch the theme back to the site's own.
