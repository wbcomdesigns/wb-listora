# Needs Marketplace

> **Pro feature** — requires [WB Listora Pro](../getting-started/activating-pro.md). Free sites support standard listing submission from businesses outward.

## What it does

The Needs Marketplace is a reverse directory: instead of businesses posting listings, visitors post what they're looking for. A need might be "Looking for a caterer for 200 guests in Austin, budget $5,000." Business owners browse posted needs and respond to the ones they can fulfill.

## Why you'd use it

- Creates a two-sided marketplace dynamic — value flows from visitors to businesses and back.
- Businesses in your directory see active demand they can respond to directly.
- Users who post needs are highly motivated buyers, making each need a qualified lead opportunity.
- Needs have urgency levels, so businesses can prioritize time-sensitive opportunities.

## How to use it

### For site owners (admin steps)

1. WB Listora Pro registers a **Needs** section in the admin automatically when Pro is active.
2. Go to **Listora → Needs** to see all posted needs, moderate them, and manage responses.
3. Use **Approve** and **Reject** controls on each need to control what appears publicly.
4. Needs can expire automatically — the expiration system runs in the background and marks needs as **Expired** after the configured period.

> **TODO:** Confirm whether a needs page is created automatically by the Setup Wizard with Pro active, or whether site owners must create it manually.

### For end users (visitor/user-facing)

**Posting a need:**

1. Navigate to the Needs page on your directory (created or linked by the site owner).
2. Click **Post Your Need**.
3. Fill in the need details:
   - **Title** — a short description of what you need.
   - **Description** — details about your requirement, timeline, and budget.
   - **Type** — the type of service you're looking for (matches your directory's listing types).
   - **Location** — where you need the service.
   - **Budget** — optional budget range.
   - **Urgency** — Flexible, Normal, or Urgent.
4. Submit. After admin approval, your need appears publicly.

**Urgency levels:**

| Urgency | Display |
|---------|---------|
| Flexible | Default appearance |
| Normal | Standard badge |
| Urgent | Highlighted badge — appears prominently |

**Responding to a need (business owners):**

1. Browse the Needs page to find relevant needs.
2. Click a need to view its full details.
3. Click **Respond** and submit your offer or message.
4. The need poster receives a notification with your response.

**Need statuses:**

| Status | Meaning |
|--------|---------|
| Open | Accepting responses |
| Fulfilled | The poster found what they needed |
| Expired | Past the expiry date, no longer active |

## Tips

- Set urgency to **Urgent** on your need to make it stand out in the list — urgent needs appear with a highlighted badge.
- Business owners: respond to urgent needs quickly. Time-sensitive buyers are more likely to convert from a fast response.
- As site owner, moderate needs promptly — long moderation queues discourage users from posting.
- Encourage business owners to check the Needs page regularly as part of their directory participation.
- Needs are stored as the `listora_need` custom post type. They support all standard WordPress moderation tools (bulk actions, filtering by status).

## Common issues

| Symptom | Fix |
|---------|-----|
| Needs page not visible | The site owner must create a page for needs browsing — this is not created automatically by the Setup Wizard |
| "Post Your Need" button not visible | Confirm WB Listora Pro is active and the user has the required capability |
| Need stuck in Pending | Go to **Listora → Needs** in the admin and approve it manually |
| Responses not sending notifications | Check WordPress email configuration; use WP Mail SMTP for reliable delivery |

## Related features

- [Lead Forms](lead-forms.md)
- [Search and Filters](search-and-filters.md)
- [Digest Notifications](digest-notifications.md)
