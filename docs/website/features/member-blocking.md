# Member Blocking

> **Availability:** Free + Pro. Blocking is **Free**. Pro's [Multi-Criteria Reviews](multi-criteria-reviews.md) respect it too, so per-aspect averages match the reviews the reader can actually see.

A member who does not want to hear from another member can block them. The block hides that person's reviews from view, in both directions, and closes the contact route between them.

## What it is

Directories put strangers in contact with each other, which occasionally goes wrong. Member blocking is the member's own remedy, applied without an administrator having to intervene and without anything being deleted.

A block is **mutual in effect and one-sided in control**. If A blocks B:

- A no longer sees B's reviews, and B no longer sees A's. Neither has to know the other acted.
- Neither can use the [Contact Form](contact-form.md) to reach the other.
- Only A can lift it. B cannot block their way out of it or undo it.

Nothing is removed. B's review still exists, still counts for the owner, and is still visible to everyone else. Blocking changes who sees it, not whether it happened. That is deliberate: a member should not be able to delete criticism by blocking its author.

## How you use it

### As a member - block someone

From any review, use the block control on the reviewer's card. The review disappears from your view immediately.

### As a member - see and lift your blocks

Your blocks are listed under **Dashboard > Profile**. Unblock from there.

This is the half that 1.5.0 added. Before it, blocking was reachable but unblocking was not, so the action was effectively permanent from the member's side and needed an administrator to undo. Blocking and unblocking are now both member-controlled.

### As a developer

Three REST routes on the account resource:

| Method | Route | Does |
|---|---|---|
| `GET` | `/listora/v1/account/blocks` | The current member's block list |
| `POST` | `/listora/v1/account/blocks` | Block a member |
| `DELETE` | `/listora/v1/account/blocks/{user_id}` | Unblock a member |

Template helpers for theme authors:

```php
wb_listora_hidden_from( $viewer );        // user IDs to hide from this viewer, both directions
wb_listora_can_contact( $a, $b );         // false when either has blocked the other
wb_listora_is_blocked_pair( $a, $b );     // true when a block exists in either direction
```

Filter the resolved list with `wb_listora_blocked_members` (receives the ID array and the user ID). Two actions fire on change: `wb_listora_member_blocked` and `wb_listora_member_unblocked`, both receiving the acting user and the target. Both are also published as [automation triggers](automation-triggers.md).

## Good to know

- **Anonymous visitors see everything.** A block is scoped to a signed-in viewer; there is nobody to hide from otherwise.
- **Blocking is not reporting.** If a review breaks your rules, the owner should report it so a moderator can act - see [Reviews System](reviews-system.md). Blocking is a private preference and does not notify anyone.
- **Deleting an account clears its blocks**, in both directions, so a recycled user ID cannot inherit someone else's block list.

## Related

- [Reviews System](reviews-system.md) - reporting a review, which is the moderated route
- [Multi-Criteria Reviews](multi-criteria-reviews.md) - Pro averages that honour blocking
- [Contact Form](contact-form.md) - the route a block closes
- [User Dashboard](user-dashboard.md) - where the block list lives
