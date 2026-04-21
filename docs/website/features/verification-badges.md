# Verification Badges

> **Pro feature** — requires [WB Listora Pro](../getting-started/activating-pro.md). Free sites can approve claims but cannot award verification badges.

## What it does

Verification badges let you mark individual listings as verified businesses. A badge appears on the listing card in search results and on the listing detail page. Visitors see at a glance that this business has been vetted by the directory.

## Why you'd use it

- Verified badges build trust with visitors making decisions about which business to contact.
- Listings with badges stand out in the search grid, increasing click-through.
- Awarding badges gives business owners an incentive to keep their listing information accurate.
- You control which listings earn a badge — verification is always a manual, admin decision.

## How to use it

### For site owners (admin steps)

Verification badges are managed from the WordPress admin listing edit screen.

**Awarding a verification badge:**

1. Go to **Listora → All Listings** (or **Posts → All Posts** filtered by **Listora Listing**).
2. Click on the listing you want to verify.
3. In the right sidebar, find the **Verification** metabox.
4. Check **Verified Business** to award the badge.
5. Optionally check **Claimed** to mark the listing as owner-claimed (separate from the claims workflow).
6. Click **Update** to save.

The badge appears immediately on the listing card and detail page.

**Removing a badge:**

1. Open the listing in the admin.
2. Uncheck **Verified Business** in the **Verification** metabox.
3. Click **Update**.

**Search filter:** Visitors can filter search results to show only verified listings. The `verified_only=true` parameter is supported in the search REST API.

### For end users (visitor/user-facing)

Verification is managed entirely by the site owner — there is no self-service verification flow. Visitors see the badge on:

- **Listing cards** in the search grid.
- **Listing detail pages** near the listing title.

## Tips

- Establish a verification policy before awarding badges. For example: "We verify businesses that have been claimed and have a physical address confirmed by the owner."
- Batch-verify listings after a claims approval workflow: approve a claim, then go to that listing and award the badge in the same session.
- The verification badge and the claimed status are two separate checkboxes — a listing can be claimed without being verified, and verified without being claimed.
- To filter search results to verified listings only via URL, append `?verified_only=1` to your directory page URL (requires the search block to respect this parameter).
- For large directories, use the **Listora → All Listings** admin table with column sorting to review which listings are verified.

## Common issues

| Symptom | Fix |
|---------|-----|
| Verification metabox not visible | Confirm WB Listora Pro is active |
| Badge not appearing on listing card | Clear your site and page cache after saving |
| "Verified only" search filter not working | Confirm the `verified_only` parameter is supported in your version — check the REST API docs |

## Related features

- [Business Claims](business-claims.md)
- [Analytics](analytics.md)
- [User Dashboard](user-dashboard.md)
