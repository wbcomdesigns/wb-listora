# Analytics

## Free-tier view tracking (since 1.2.0)

WB Listora Free now tracks page views for every listing automatically - no Pro required, no configuration needed.

**What you get on Free:**

- A **Views** count on each listing's row in the admin listings table (Listora → All Listings).
- View counts on the listing owner's User Dashboard under My Listings.
- A `views` field on the listing REST response.

View tracking is bot-filtered (known crawler user-agents are excluded) and rate-limited per visitor per listing to avoid inflation from page refreshes. Your own views as a site admin are not counted.

**Deferral to Pro:** If WB Listora Pro is active with its Analytics feature enabled, Pro owns view recording and the Free tracker steps aside. Both write to the same `listora_analytics` table, so the numbers are consistent whether you upgrade later.

## Pro analytics

> **Pro feature** - requires [WB Listora Pro](../getting-started/activating-pro.md). Pro adds click-event tracking and the full Analytics dashboard tab.

WB Listora Pro tracks views and engagement clicks on every listing - automatically, without cookies or third-party scripts. Listing owners see their own analytics from their User Dashboard. You see aggregate data from the WordPress admin.

![Analytics - screenshot from the modernized 1.0.5 site](../images/analytics.png)

## Why you'd use Pro analytics

- Listing owners get data showing how their listing performs, giving them a reason to keep it updated and renew their plan.
- You can identify underperforming listings that need attention.
- Click events (phone, website, email, directions) show actual engagement, not just passive views.
- Privacy-safe: no personally identifiable information is stored, only aggregate daily counts.

## How to use it

### For site owners (admin steps)

Pro analytics are enabled automatically with WB Listora Pro - no configuration is needed to start collecting data.

Bot traffic is excluded server-side. Views from users with the `manage_listora_settings` capability (site owners) are also excluded so your own browsing doesn't inflate counts.

### For end users (visitor/user-facing)

Listing owners see the **Analytics** tab in their User Dashboard. The tab shows:

**For each listing:**

- **Views** - total page views for the listing detail page.
- **Phone clicks** - how many visitors clicked the phone number.
- **Website clicks** - how many visitors clicked the website link.
- **Email clicks** - how many visitors clicked the email address.
- **Direction clicks** - how many visitors clicked "Get Directions."

**Time period selector:**

- **Last 7 days**
- **Last 30 days**
- **Last 90 days**

Click any listing in the Analytics tab to see its breakdown by event type over the selected period.

## Tips

- Encourage listing owners to check their analytics monthly - it's a strong reason for them to renew paid plans.
- If you run a featured listing upsell, point owners to their analytics to show the difference in view counts between standard and featured periods.
- Analytics data is stored in the `listora_analytics` table (shared with Free, populated by Pro). Do not truncate this table manually.
- Views are tracked server-side on single listing page loads. Click events (phone, website, etc.) are tracked via a `POST /listora/v1/analytics/track` REST call triggered by the frontend when a user clicks those elements.
- To access analytics data programmatically, use the REST endpoint `GET /listora/v1/analytics/{listing_id}?period=30`. See `docs/REST-API.md` in the Pro plugin root.

> **TODO:** Confirm whether the Analytics tab appears only when the user has `analytics_access` plan perk, or for all users regardless of plan.

## Common issues

| Symptom | Fix |
|---------|-----|
| Analytics tab not appearing in dashboard | Confirm WB Listora Pro is active and the license is valid |
| View counts not increasing | Check that bot detection isn't blocking legitimate traffic; also confirm the page loads as a single listing (`is_singular('listora_listing')`) |
| Click events not tracking | The click tracker fires via JavaScript REST call - check for JavaScript errors in the browser console |
| Counts reset or look wrong after migration | The `listora_analytics` table is separate from post data; include it in any site migration |

## Search analytics (Pro, since 1.2.0)

WB Listora Pro records the search terms your visitors use daily. Go to **Listora → Analytics → Search Terms** to see which queries drive traffic and which come up empty - useful for identifying gaps in your directory content.

Search term data is stored in the `listora_search_terms` table and pruned automatically by the analytics retention cron. Retrieve it via `GET /listora/v1/analytics/search` (requires `manage_listora_settings`).

## Related features

- [Credits and Plans](credits-and-plans.md)
- [User Dashboard](user-dashboard.md)
