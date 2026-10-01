# Pro Emails

> **Availability:** Pro only. Requires [WB Listora Pro](../getting-started/activating-pro.md). These emails sit alongside the ones Listora Free sends.

WB Listora Pro sends emails for credits, plans, needs, leads, saved searches, digests, moderator changes and paused webhooks. They are managed in the same places as the Free emails: you can switch each one off, edit its subject and message, preview it, and see every one that was sent. Members can switch off the ones sent to them.

## The emails

| Group | Email | Sent to | When |
|-------|-------|---------|------|
| Credits | **Low credit balance** | Member | The balance drops to the low-balance alert. |
| Credits | **Credits refunded** | Member | Credits are returned to a member, or taken back after a refund. |
| Credits | **Listing paused for credits** | Listing owner | A plan renewal cannot be paid, so the listing is paused. |
| Credits | **Listing resumed** | Listing owner | A paused listing is live again after a top-up. |
| Needs | **New request for a business** | Matching businesses | A need that matches their listing is approved. |
| Needs | **New quote on a need** | Need author | A business sends a quote. |
| Needs | **Quote accepted** | Business | Its quote is accepted. It includes the buyer's contact details. |
| Needs | **Quote not chosen** | Business | Its quote is declined. |
| Needs | **Request closed** | Businesses that quoted | The request is fulfilled, closed or expires. |
| Needs | **Request updated** | Businesses that quoted | The member edits the request. Sent once. |
| Needs | **Need waiting for review** | Moderators | A new need needs approval. |
| Needs | **Need approved** | Need author | Their need is published. |
| Needs | **Need declined** | Need author | Their need is rejected. It includes the moderator's note. |
| Needs | **Need expired** | Need author | Their need reaches its deadline or expiry. |
| Members | **New enquiry** | Listing owner | A visitor uses the contact form. |
| Members | **Saved search alert** | Member | New listings match a search they saved. |
| Moderation | **Queue items reassigned** | Moderator | Another moderator's queue is moved to them. |
| Site | **Webhook paused** | Administrators | A webhook fails 10 deliveries in a row and is paused. |

The Pro digest email (see [Digest Notifications](digest-notifications.md)) is sent through the same path.

## How to use it

### Switch, edit and preview an email

1. Go to **Listora → Settings → Notifications**.
2. Find the email in its group (**Credits**, **Needs**, **Members**, **Moderation** or **Site**). Each one shows who receives it.
3. Use the toggle to switch it off or on. An email that is off is not sent.
4. To change the wording, open the email in the template editor, change the **Subject** and the **Message**, and save. The placeholders you can use are listed under the fields. To undo a change, tick **Go back to the built-in email on save**.
5. Click **Preview** to see the email with sample content before you save.

See [Notifications Settings](../settings/notifications-settings.md) for the rest of the tab, including **Send Test Email**.

Your saved wording applies wherever the email is sent: from the web, from background jobs and from WP-CLI.

### See what was sent

Every Pro email is recorded in **Listora → Tools → Email Log**, next to the Free emails, with whether it was sent or failed. See [Email Log](email-log.md).

### Members can switch off their own emails

Members choose which emails they get on the **Profile** tab of their dashboard. Pro adds these under **Credits and plans** and **Needs**:

| Toggle | Email it controls |
|--------|-------------------|
| My credit balance is running low | Low credit balance |
| Credits were refunded to me | Credits refunded |
| A listing paused for lack of credits | Listing paused for credits |
| A paused listing went live again | Listing resumed |
| A new need matches my listings | New request for a business |
| Someone responded to my need | New quote on a need |
| A need I responded to was updated | Request updated |
| My need expired | Need expired |
| My need was approved | Need approved |
| My need was rejected | Need declined |
| My response to a need was accepted | Quote accepted |
| My response to a need was declined | Quote not chosen |

Everything is on until a member switches it off. A member's switch stops that email going to that member. Emails with no toggle in this list, such as the moderator and administrator emails, are not affected. You can still turn any email off for everyone in **Settings → Notifications**.

## Saved search alerts

A saved search alert links to your site's own directory page, with the member's filters applied. It does not point at a fixed `/listings/` address, so it keeps working if you named or moved your directory page. The links the member sees on the **Saved Searches** tab of their dashboard work the same way. See [Saved Searches](saved-searches.md).

## Related features

- [Notifications Settings](../settings/notifications-settings.md)
- [Email Templates](email-templates.md)
- [Email Log](email-log.md)
- [Credits and Plans](credits-and-plans.md)
- [Needs Marketplace](needs-marketplace.md)
- [Outgoing Webhooks](outgoing-webhooks.md)
