# Saved Searches

> **Pro feature** — requires [WB Listora Pro](../getting-started/activating-pro.md). Free sites include the Favorites feature for bookmarking individual listings.

## What it does

Logged-in visitors can save any search — keyword, location, type, filters — and receive a daily email alert when new listings match those criteria. Saved searches appear in the **User Dashboard** for easy management.

## Why you'd use it

- Users who save a search come back to your directory when they receive alerts, driving repeat traffic.
- Buyers and researchers get automatic updates without checking your directory manually.
- Alert emails link directly to matching new listings, shortening the path to contact.
- Users feel the directory is working for them, increasing satisfaction.

## How to use it

### For site owners (admin steps)

Saved searches are enabled automatically with WB Listora Pro.

**How alerts are sent:** A daily WordPress cron event (`wb_listora_pro_saved_search_alerts`) runs once per day. It checks all saved searches against listings published in the last 24 hours and sends an email for any matches.

**Email template:** Alerts use the template at `templates/emails/saved-search-alert.php`. Override it at `{theme}/wb-listora/emails/saved-search-alert.php` for custom branding.

**Disabling alerts for a specific user:** There is no admin toggle per user. If a user no longer wants alerts, they disable them from their own dashboard.

### For end users (visitor/user-facing)

**Saving a search:**

1. Run a search on your directory page — enter keywords, set filters, select a location.
2. After results load, a **Save this search** button appears below the search bar.
3. Click the button.
4. Enter a name for the saved search (e.g., "Italian restaurants in Brooklyn").
5. Toggle **Email alerts** on or off.
6. Click **Save**. The search is stored in your account.

**Managing saved searches:**

1. Go to **User Dashboard**.
2. The dashboard navigation includes a **Saved Searches** section (added by Pro).
3. Each saved search is listed with its name and alert status.
4. Toggle email alerts on or off per search.
5. Click **Delete** to remove a saved search entirely.

**Receiving alerts:**

When new listings match your saved search criteria, you receive an email with a summary and direct links to the matching listings. Alerts are sent once daily — not in real time.

## Tips

- Encourage users to save searches during onboarding. A single saved search creates a recurring reason to return to your site.
- The alert email links to individual listing pages — make sure your listing detail pages load quickly and look good on mobile.
- Saved searches respect all active filters: type, category, location radius, price range, rating. The more specific the search, the fewer (but more relevant) alerts a user receives.
- REST endpoint: `GET /listora/v1/saved-searches` returns the current user's saved searches. `POST /listora/v1/saved-searches` creates a new one. `DELETE /listora/v1/saved-searches/{id}` removes one. See `docs/REST-API.md` in the Pro plugin root.
- Saved search data is stored in user meta (`_listora_saved_searches`). It is not tied to the `listora_saved_searches` database table (which is reserved for a future relational index).

## Common issues

| Symptom | Fix |
|---------|-----|
| "Save this search" button not appearing | Confirm the user is logged in and WB Listora Pro is active |
| Alerts not arriving | Check WordPress cron is running — install WP Crontrol and verify `wb_listora_pro_saved_search_alerts` is scheduled |
| Alerts sending for old listings | The alert checks listings published in the last 24 hours based on `post_date` |
| Saved Searches section missing from dashboard | Confirm WB Listora Pro is active and the license is valid |

## Related features

- [Search and Filters](search-and-filters.md)
- [User Dashboard](user-dashboard.md)
- [Digest Notifications](digest-notifications.md)
