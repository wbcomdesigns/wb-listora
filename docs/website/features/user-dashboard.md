# User Dashboard

## What it does

The User Dashboard gives listing owners a self-service frontend panel to manage everything related to their presence in your directory — listings, reviews, favorites, claims, credits, and profile — without needing access to the WordPress admin.

## Why you'd use it

- Business owners update their own listing details 24/7, reducing support requests.
- Claimants track their claim status without emailing you.
- Users view all their activity in one place, improving retention.
- The dashboard respects permissions — users only see their own data.

## How to use it

### For site owners (admin steps)

1. The Setup Wizard creates a **Dashboard** page automatically. If you skipped the wizard, create a new page and add the **User Dashboard** block.
2. Make sure the page is not restricted to logged-in users by your theme or a membership plugin — WB Listora handles its own login redirect.
3. Go to **Listora → Settings → General** to confirm the Dashboard Page is set correctly.

### For end users (visitor/user-facing)

Log in and navigate to the Dashboard page. You'll see four clickable stat cards at the top:

| Card | What it shows |
|------|---------------|
| **Active** | Published listings |
| **Pending** | Listings awaiting review |
| **Reviews** | Total reviews received |
| **Saved** | Favorited listings |

Clicking any card jumps to the matching tab.

#### My Listings tab

- See all your submissions with status badges: **Published**, **Pending**, **Draft**, **Expired**.
- Click **Edit** to update any listing field, images, or description.
- Click **Renew** on expired listings.
- Click **Delete** to remove a listing.
- Each listing row shows a **Manage Services** link (see [Services per Listing](services-per-listing.md)).

#### Reviews tab

- View every review left on your listings.
- Click **Reply** to post an owner response directly from the dashboard.
- Track your average rating across all listings.

#### Favorites tab

- See listings you've saved.
- Click the listing title to visit it, or click **Remove** to unsave.

#### My Claims tab

- See every claim you've submitted, with a status pill: **Pending**, **Approved**, or **Rejected**.
- **Pending** claims show an information message while your claim is under review.
- **Approved** claims show an **Edit Listing** button — click it to start managing that listing immediately.
- **Rejected** claims show the rejection reason if one was provided.

#### Credits tab

- View your current credit balance.
- See your transaction history (top-ups and deductions).
- A **Buy Credits** CTA links to your credits page (Pro feature — upgrade prompt shown in Free).

#### Profile tab

- Update your display name, bio, and contact details.
- Changes apply to your WordPress user account.

## Welcome banner

After completing the Setup Wizard for the first time, a welcome banner appears on the dashboard. It links to key next steps: add your first listing, customize settings, and read the docs. The banner appears once and disappears after you dismiss it.

## Tips

- Pin the dashboard URL in your navigation menu so users can find it easily.
- Set the Dashboard page to **Wide** template or **Full Width** in your theme for the best layout.
- If a user's listing is expired, the **My Listings** tab shows a **Renew** button — make sure your expiration settings are configured under **Listora → Settings → Submissions**.
- The Credits tab always appears in Free, but displays a Pro upgrade prompt instead of a balance. This is intentional — it signals to power users that a credits system is available.
- Stat card click-through only works when the matching tab has content. Empty states show a CTA to add a listing or save a favorite.

## Common issues

| Symptom | Fix |
|---------|-----|
| Dashboard shows a login form instead of content | Verify the Dashboard page uses the **User Dashboard** block, not a shortcode from another plugin |
| "My Claims" tab is missing | Claims must be enabled under **Listora → Settings → Claims** |
| Stats show 0 even though listings exist | Clear your site cache — stat cards are cached for 60 seconds |
| User can see other users' listings | Check no third-party plugin is removing the `edit_listora_listings` capability |

## Related features

- [Business Claims](business-claims.md)
- [Favorites](favorites.md)
- [Frontend Submission](frontend-submission.md)
- [Services per Listing](services-per-listing.md)
