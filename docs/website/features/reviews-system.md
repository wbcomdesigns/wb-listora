# Reviews System

## What it does

WB Listora includes a full review system. Visitors rate listings with 1–5 stars, write a review, vote on helpful reviews, report inappropriate ones, and read owner replies — all on the listing detail page without leaving the page.

## Why you'd use it

- Star ratings and reviews build social proof for listed businesses.
- Owner replies show businesses are engaged, which keeps the directory active.
- Helpful votes surface the most useful reviews at the top.
- Moderation tools let you maintain quality without deleting all reviews manually.

## How to use it

### For site owners (admin steps)

1. Go to **Listora → Settings → Reviews** to configure:
   - **Auto-approve reviews** — publish immediately or hold for moderation.
   - **Minimum content length** — require a minimum character count (e.g., 30 characters).
   - **One review per listing** — prevent duplicate reviews from the same user.
   - **Require login** — only registered users can submit reviews.
2. To moderate held reviews, go to **Listora → Reviews**:
   - Filter by **Pending**, **Approved**, or **Rejected**.
   - Search by listing name or reviewer.
   - Bulk approve or reject using the checkboxes.
   - View the flag status of reported reviews.

### For end users (visitor/user-facing)

**Submitting a review:**
1. Navigate to a listing detail page and click **Write a Review**.
2. Select a star rating (1–5).
3. Write your review title and text.
4. If the listing type has multi-criteria ratings enabled (<Badge>Pro</Badge>), rate each criterion separately.
5. Click **Submit**. Depending on moderation settings, your review appears immediately or after admin approval.

**Editing a review:** If the site allows it, you can edit your review by clicking **Edit** next to your existing review.

**Deleting a review:** Click **Delete** on your own review to remove it.

**Helpful votes:** Click **Helpful** on any review to upvote it. The most helpful reviews rise to the top.

**Reporting a review:** Click **Report** to flag a review as inappropriate. Admins see flagged reviews in **Listora → Reviews**.

**Owner reply:** If you own a listing, navigate to the listing's detail page or your **User Dashboard → Reviews** tab and click **Reply** next to any review. Your reply appears below the review, labelled "Owner Response."

## Tips

- Enable **One review per listing** to prevent review manipulation — users can update their existing review instead of submitting duplicates.
- Use **Auto-approve** only if your directory has a small, trusted user base. For public directories, manual moderation is safer.
- Encourage listing owners to reply to reviews — directories with active owner replies see more review submissions.
- Multi-criteria reviews (Pro) let you define custom rating aspects per listing type. For example, restaurants get Food, Service, Ambiance, and Value; hotels get Rooms, Cleanliness, Service, Location, and Value. See [Multi-Criteria Reviews](multi-criteria-reviews.md).
- Photo reviews (Pro) let reviewers upload images. See [Photo Reviews](photo-reviews.md).

## Common issues

| Symptom | Fix |
|---------|-----|
| Review form not visible | Verify the **Listing Reviews** block is on the detail page template, or that the **Listing Detail** block is present |
| Reviews stuck in Pending | Go to **Listora → Reviews** and approve them manually, or enable auto-approve |
| Owner can't see the Reply button | Confirm the user is the listing author or has the `moderate_listora_reviews` capability |
| "One review per listing" not working | Clear your site cache — the duplicate check uses a database query that caching may bypass |

## Related features

- [Blocks Overview](blocks-overview.md)
- [User Dashboard](user-dashboard.md)
- [Business Claims](business-claims.md)
