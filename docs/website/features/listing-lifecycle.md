# Listing Lifecycle Actions

Built into WB Listora **Free**.

The self-service actions a listing owner can take on their own listings from the **My Listings** dashboard — **Renew** (extend expiration), **Feature** (promote, Pro), **Deactivate** (hide from directory), **Reactivate** (restore deactivated), **Edit** (re-open in the submission wizard), **Delete** (trash), and **Report** (flag for admin review). These actions consolidate every state transition a vendor can perform without admin intervention.

![Listing Lifecycle — My Listings dashboard showing per-row action buttons](../images/listing-lifecycle-dashboard.png)

## What it is

Every listing transitions through a small state machine. The lifecycle actions on the dashboard are the customer-facing controls for these transitions:

| Action | Status before | Status after | Who can trigger |
|---|---|---|---|
| **Renew** | `publish`, `listora_expired` | `publish` with new expiration | Listing owner |
| **Feature** (Pro) | `publish` | `publish` (Featured flag set) | Listing owner with credits / plan |
| **Deactivate** | `publish` | `listora_deactivated` | Listing owner |
| **Reactivate** | `listora_deactivated` | `publish` | Listing owner |
| **Edit** | any | any (no transition) | Listing owner |
| **Delete** | any | (trashed) | Listing owner |
| **Report** | any | (no transition, admin notified) | Any logged-in visitor |

The same buttons surface in the **Listings admin page** for admins with `edit_others_listora_listings`.

## How you use it

### Renew an expired or expiring listing

1. **Open My Listings** (`/my-listings/`).
2. Find the listing showing **Expired** or **Expiring soon**.
3. Click **Renew**.
4. The modal shows the renewal cost (credits if Pro Pricing Plans is on, free otherwise) and the new expiration date.
5. Confirm — the listing transitions back to `publish` with a fresh expiration.

Renewal pricing comes from `/listings/{id}/renewal-quote` and the actual renew posts to `/listings/{id}/renew`. Listings can be renewed at any time within the renewal window (configured on the Pricing Plans).

### Deactivate a listing (vendor pause)

1. Open My Listings.
2. Click **Deactivate** on the row.
3. Confirm in the design-system modal.
4. The listing transitions to `listora_deactivated`. It disappears from the directory, search results, map markers, and category archives. It still exists in the database — the owner can reactivate any time.

Deactivation does NOT delete reviews, favourites, or claims. It's a soft pause. The View icon disappears from the row when deactivated (since the public URL would 404).

### Reactivate a deactivated listing

1. Open My Listings.
2. The deactivated row shows **Reactivate** instead of Deactivate.
3. Click it. Listing transitions back to `publish` and reappears everywhere it was before.

### Feature a listing (Pro)

Requires the [Pricing Plans](pricing-plans.md) feature to be on, the listing to have a plan with featured-rotation entitlement (or credits to spend), and `featured_listings` toggle enabled.

1. Open My Listings.
2. Click **Feature** on a published listing.
3. The modal shows the cost (credits) and the duration the feature flag stays active.
4. Confirm. The listing immediately joins the `listing-featured` block's rotation and gets the Featured badge on its card / detail page.

See [Featured Listings](featured-listings.md) for the full Featured rotation logic.

### Edit an existing listing

1. Open My Listings.
2. Click **Edit** on the row.
3. The submission wizard re-opens with all fields pre-populated.
4. Make changes, save. Status transitions depend on your **Settings → Submissions → Edited submissions** flow:
   - **Auto-publish edits** → goes straight back to `publish`.
   - **Re-moderate edits** → goes to `pending` until admin re-approves.

### Delete a listing

1. Open My Listings.
2. Click **Delete** on the row.
3. Confirm. The listing transitions to `trash` (standard WordPress behavior).

Trashed listings disappear from the directory but stay recoverable for 30 days via WP Admin → Listings → Trash. After 30 days WordPress permanently deletes them.

### Report a listing (any logged-in visitor)

1. Open any listing detail page.
2. Click **Report** in the action bar.
3. Pick a reason (spam, inappropriate, wrong category, duplicate, etc.) and optionally add a note.
4. Submit. Listing owner is NOT notified. The report enters the admin Reports queue (Listora → Reports).

## REST endpoints

Every action above maps to a single REST route. See [REST API](../developer-guide/rest-api.md) for full parameter detail.

| Action | Endpoint | Method |
|---|---|---|
| Renewal quote (pricing) | `/listings/{id}/renewal-quote` | GET |
| Renew | `/listings/{id}/renew` | POST |
| Deactivate | `/listings/{id}/deactivate` | POST |
| Reactivate | `/listings/{id}/reactivate` | POST |
| Feature (Pro) | `/listings/{id}/feature` | POST |
| Report | `/listings/{id}/report` | POST |
| Edit | `/listings/{id}` | PUT |
| Delete | `/listings/{id}` | DELETE |

## Permissions

Every action checks the user's relationship to the listing:

- **Owner-only** (Renew / Feature / Deactivate / Reactivate / Edit / Delete) → `post_author === current_user_id` OR `edit_others_listora_listings`.
- **Public** (Report) → `is_user_logged_in()` only. Guests can't report.

Admins with `edit_others_listora_listings` can do any of these from the Listings admin page bypassing the dashboard.

## Hooks

Every transition fires a `before_` filter (return `WP_Error` to abort) and `after_` action:

- `wb_listora_before_renew_listing` / `wb_listora_after_reactivate_listing` / `wb_listora_after_deactivate_listing`
- `wb_listora_listing_status_changed` ($post_id, $new_status, $old_status) — single canonical listener point for any state transition. Free's [Notifications](../settings/notifications-settings.md) dispatcher hooks here so approve / reject / expire / renew emails fire from one place.

Full list at [Hooks reference](../developer-guide/hooks-reference.md).

## Related

- [User Dashboard](user-dashboard.md) — the My Listings page that hosts these actions.
- [Featured Listings](featured-listings.md) — the Feature action's downstream rotation.
- [Pricing Plans (Pro)](pricing-plans.md) — credits + plans that gate Renew / Feature pricing.
- [Notifications Settings](../settings/notifications-settings.md) — emails fired on each transition.
- [Moderation Queue](moderation-queue.md) — where rejected / reported listings go for admin review.
- [REST API](../developer-guide/rest-api.md) — every endpoint above.
