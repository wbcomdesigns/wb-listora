# Favorites

## What it does

Logged-in visitors can save any listing to their favorites with a single click. Saved listings appear in the **Favorites** tab of their User Dashboard, making it easy to return to listings they care about.

## Why you'd use it

- Users come back to your directory to check their saved listings, increasing repeat visits.
- Favorite counts can signal popularity to other visitors.
- With WB Listora Pro, users can turn a saved search into an alert — but favorites are a simpler "bookmark" that works in Free.
- No configuration needed — favorites work out of the box after activation.

## How to use it

### For site owners (admin steps)

Favorites are enabled by default. No settings to configure.

To review favorite data programmatically, use the REST endpoint `GET /listora/v1/favorites` (requires authentication). See `docs/REST-API.md` in the plugin root.

### For end users (visitor/user-facing)

**Saving a listing:**

1. Navigate to a listing detail page or find a listing in the search grid.
2. Click the heart icon (on the listing card or on the detail page action bar).
3. The icon fills in to confirm the listing is saved.
4. You must be logged in to save favorites. If you're not logged in, clicking the heart redirects you to the login page.

**Viewing saved listings:**

1. Go to your **User Dashboard**.
2. Click the **Favorites** tab, or click the **Saved** stat card at the top of the dashboard.
3. Each saved listing appears as a card with a quick link to the detail page.

**Removing a favorite:**

Click the heart icon again on any listing card or detail page to unsave it. Or click **Remove** from the **Favorites** tab on the dashboard.

## Tips

- The **Saved** stat card on the dashboard is clickable — it jumps directly to the Favorites tab. Remind users about this in any onboarding email you send.
- If you display listing cards on custom pages (using the **Listing Card** block), the heart icon is included automatically — users can save from any page that shows listing cards.
- For directories with many listings, encourage users to save favorites as a way to shortlist options before contacting businesses.
- Saved favorite counts are stored per-user in the database and are not displayed publicly by default. If you want to show a "X users saved this" count, this requires a custom filter on the `wb_listora_rest_prepare_listing` hook.

## Common issues

| Symptom | Fix |
|---------|-----|
| Heart icon not visible on listing cards | Confirm the **Listing Grid** and **Listing Card** blocks are using the latest version of the plugin |
| Clicking heart redirects to login | This is expected behavior for non-logged-in users; check your login page is set correctly under **Settings → General** |
| Favorites tab empty after saving | Clear your site cache — favorites are fetched via REST API and cached pages may show stale data |
| User can't remove a favorite | Refresh the page; the remove action requires a valid nonce that may expire after long sessions |

## Related features

- [User Dashboard](user-dashboard.md)
- [Search and Filters](search-and-filters.md)
- [Blocks Overview](blocks-overview.md)
