# Moderation Queue

Built into WB Listora **Free**.

Three separate admin work queues — pending listings on **All Listings** (filtered by Pending), pending reviews on **Listora → Reviews**, pending claims on **Listora → Claims**, plus the Reports queue and photo-review uploads (Pro) — that share one consistent approve/reject/edit/bulk-action pattern. Per-row history is tracked everywhere; bulk-moderate (up to 100 IDs) is available via `POST /listora/v1/listings/bulk-moderate`. The architectural separation is intentional so capability gating stays clean per content type.

![Moderation Queue — bulk-moderate UI with filter chips for type, status, and date](../images/moderation-queue.png)

## What it is

A directory's quality is the moderator's quality. Moderation has three different work queues that share one pattern:

1. **Pending listings** — submissions with status `pending` waiting for approval. Row actions on the All Listings admin page: **Approve** (transitions to `publish`), **Reject** (transitions to `listora_rejected`, sends notification), **Feature**, **Unfeature**, **Trash**.
2. **Pending reviews** — reviews in `pending` status (set when moderation is enabled in Settings → Reviews → Require Approval). Same row actions: approve, reject, edit, delete.
3. **Pending claims** — business-claim requests in `pending` status. Approve transfers `post_author` to the claimant; reject sends a `claim-rejected` notification.
4. **Flagged content** — any reviewer can flag a review as inappropriate; flags surface in their own queue for moderator action.

Bulk operations:

- The All Listings admin page supports `POST /listora/v1/listings/bulk-moderate` — approve, reject, feature, unfeature, or trash up to 100 listings per call.
- The Reviews admin page supports the same bulk operations for reviews.
- Claims have a single-row workflow (approval triggers post-author transfer, not idempotent in bulk).

Moderator role (Pro):

- **Pro adds a dedicated `listora_moderator` role** (see [Moderator Role](moderators.md)) — scoped to listing approval, review moderation, and claims management; cannot edit site settings.
- **Auto-assignment** — Pro can round-robin assign new submissions to a pool of moderators (`listora_last_moderator_index` option drives the rotation).
- **Moderator Queue block** (Pro `listora-pro/moderator-queue`) — surfaces all pending work in one frontend block, useful for a dedicated moderator dashboard page.

History + audit:

- Every moderation action fires an `wb_listora_after_*` hook (e.g. `wb_listora_after_update_claim` with `$old_status`, `$new_status`, `$actor_id`).
- Pro's [Audit Log](audit-log.md) records every action with actor + timestamp + before/after, so disputes are reconstructable.

## How you use it

### As a site owner — work the queue daily

1. **Listings:** WP Admin → Listora → All Listings → filter by **Pending**. The list view shows row actions: Approve / Reject / Edit / Feature / Unfeature.
   - **Approve** = one-click transition to `publish`. Sends the listing-approved notification automatically.
   - **Reject** = one-click transition to `listora_rejected`. Prompts for an optional rejection note that's included in the email.
2. **Reviews:** Listora → Reviews → filter by Pending. Approve / Reject / Edit / Delete row actions.
3. **Claims:** Listora → Claims → filter by Pending. Approve transfers `post_author` to the claimant and notifies; Reject sends a polite rejection.
4. **Bulk:** select multiple rows → use the **Bulk Actions** dropdown at the top of the table → pick the action → Apply.

### As a moderator (Pro role)

- Visit `/wp-admin/admin.php?page=listora-moderators` for the Moderators admin page (or the [Moderator Queue block](moderators.md) on the frontend if your site offers one).
- Your view is scoped: you see only items assigned to you (when auto-assignment is on) or all pending items (when it's off).
- All your moderation actions are recorded against your user in the Audit Log — both for accountability and to surface stalled moderators to admins.

### Configuration

- **Require approval for new listings:** Settings → Submission → **Moderation** → set to `manual` (default) or `auto` (auto-publish — not recommended for public-submit directories).
- **Require approval for new reviews:** Settings → Reviews → **Require Approval**.
- **Auto-assignment to moderators** (Pro): Settings → Moderators → **Auto-Assign New Submissions**.

## Settings & options

| Setting | Location | Default | Notes |
|---|---|---|---|
| Listing moderation mode | Settings → Submission → Moderation | `manual` | `manual` / `auto` |
| Review moderation | Settings → Reviews → Require Approval | On | When off, reviews go straight to publish |
| Bulk-moderate endpoint | `POST /listora/v1/listings/bulk-moderate` | — | Up to 100 IDs per call |
| Claims approval flow | Listora → Claims → Pending | Manual | Approves transfer `post_author` |
| Pro: moderator role | (auto-created on activation) | — | `listora_moderator` |
| Pro: auto-assignment | Settings → Moderators → Auto-Assign | Off | Round-robin via `listora_last_moderator_index` |

Developer hooks:

- `wb_listora_after_update_claim` (action, 3 args) — fires on approve/reject; listen to push to CRM, Slack, etc.
- `wb_listora_listing_status_changed` (action) — fires on every listing transition; canonical signal for "a moderator did something".
- `wb_listora_pro_moderator_assigned` (action) — fires when Pro auto-assigns a submission to a moderator.

## Related

- [Reviews & Ratings](reviews-system.md) — review moderation specifics.
- [Business Claims](business-claims.md) — claim approval transfers `post_author`.
- [Moderator Role (Pro)](moderators.md) — the dedicated WP role for moderation.
- [Audit Log (Pro)](audit-log.md) — every moderation action recorded.
- [Spam Protection](spam-protection.md) — first-line defence that keeps the queue manageable.
