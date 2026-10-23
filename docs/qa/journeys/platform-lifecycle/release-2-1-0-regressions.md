---
journey: release-2-1-0-regressions
group: platform-lifecycle
actor: admin
mode: mixed
plugin: wb-listora
priority: high
roles: [admin, owner, other, guest]
covers: [dashboard, listing-detail, quick-view, settings, leads, coupons, analytics, submission]
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
Real Stripe and PayPal checkout, a real reCAPTCHA key, BuddyBoss and Reign, a Subscriber with more than 20 listings on the Analytics tab, and a live upgrade from a 2.0.0 database with a table dropped.

## Cleanup
Restore any reviewer display name, delete QA coupons and leads, and switch the theme back to the site's own.
