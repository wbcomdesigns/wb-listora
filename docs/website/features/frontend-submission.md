# Frontend Listing Submission

## What it does

The **Listing Submission** block gives registered users a multi-step form to add listings directly from your site's frontend. No WordPress admin access is required. Users can also edit existing listings, manage services per listing, and save drafts — all from the same interface.

## Why you'd use it

- Directory operators get community-sourced listings without manual data entry.
- Businesses submit their own information, keeping it accurate.
- The draft-reminder email brings back users who started a listing but didn't finish.
- A pre-submit duplicate check prevents identical listings from cluttering your directory.

## How to use it

### For site owners (admin steps)

1. The Setup Wizard creates an **Add Listing** page automatically with the **Listing Submission** block already placed.
2. To create the page manually: add a new page, insert the **Listing Submission** block, and publish.
3. Configure submission behavior under **Listora → Settings → Submissions**:
   - **Require login** — only registered users can submit (recommended).
   - **Moderation mode** — choose **Auto-publish** or **Manual review** (listings held as Pending).
   - **Edit approval** — require re-approval when a listing is edited.
   - **Allowed types** — restrict which listing types accept submissions.
   - **Image limits** — maximum number of gallery images per listing.
   - **Expiration** — days until a listing expires (0 = never).

### For end users (visitor/user-facing)

**Submitting a new listing:**

1. Go to the Add Listing page and click **Start**.
2. A pre-submit duplicate check runs as you type the listing name. If a matching listing is found, you'll see a warning with a link to the existing listing — preventing accidental duplicates.
3. Complete the five steps:
   - **Choose Type** — select the listing type (Restaurant, Hotel, Real Estate, etc.).
   - **Basic Info** — title, description, and featured image.
   - **Type Fields** — fields specific to the chosen type (address, phone, hours, price range, social links, etc.).
   - **Categories** — select relevant categories and feature tags.
   - **Preview & Submit** — review your listing before submitting.
4. After submitting, the listing is either published immediately or set to Pending depending on your site's moderation mode.

**Editing an existing listing:**

1. Go to **User Dashboard → My Listings**.
2. Click **Edit** next to the listing you want to update.
3. Make your changes and save. If the site requires edit approval, the listing returns to Pending until approved.

**Managing services on a listing:**

From **My Listings**, click **Manage Services** to add, edit, or delete services offered by that business. See [Services per Listing](services-per-listing.md).

**Draft reminder:** If a user starts a listing and doesn't submit within 24 hours, WB Listora sends an email reminder with a link to resume. This is handled automatically — no configuration required.

## Tips

- Set **Moderation mode** to **Manual review** for public directories. This prevents spam and low-quality listings from going live automatically.
- Use **Allowed types** to restrict submissions to specific types. For example, a restaurant directory should only allow the **Restaurant** type.
- The **Guest registration** option (under Submissions settings) lets unregistered users create an account during submission — useful for lowering the barrier to entry.
- Conditional fields: some field types only appear based on earlier answers (e.g., a "Cuisines" field only appears after selecting the Restaurant type).
- The draggable map pin on the address field lets submitters fine-tune their precise location on the map.

## Common issues

| Symptom | Fix |
|---------|-----|
| Submission form is blank | Confirm the **Listing Submission** block is on the page, not a shortcode |
| Users can't see the form | Check **Require login** is enabled and the user is logged in |
| Submitted listing not visible | If moderation is on, approve the listing under **Listora → All Listings** |
| Draft reminder not sending | Verify WordPress cron is running — check with a plugin like WP Crontrol |
| Images not uploading | Check your server's `upload_max_filesize` and `post_max_size` PHP settings |

## Related features

- [Listing Types](../getting-started/listing-types.md)
- [User Dashboard](user-dashboard.md)
- [Services per Listing](services-per-listing.md)
