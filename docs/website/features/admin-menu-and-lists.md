# Admin Menu and Lists

> **Availability:** Free + Pro. Screens that belong to Pro (Badges, Needs, Moderators, Plans, Coupons, Transactions, Audit Log, Webhooks) appear in the same menu when Pro is active.

The **Listora** menu in wp-admin groups related screens under nine items. Each item opens a row of tabs at the top of the page. The list screens under them (Claims, Reviews, Email Log, and the Pro lists) all work the same way, so once you know one you know them all.

## The nine menu items

| Menu item | Tabs inside it |
|---|---|
| **Dashboard** | One screen. See [Admin Dashboard](admin-dashboard.md). |
| **Listings** | All Listings. |
| **Listing Types** | Listing Types, and Badges (Pro). |
| **Categories** | Categories, Locations, Features, Service Categories. |
| **Moderation** | Reviews, Claims, and with Pro: Needs, Moderators. |
| **Monetization** | Pro screens: Plans, Coupons, Transactions. |
| **Analytics** | Analytics (Pro). |
| **Tools** | Email Log, and with Pro: Audit Log, Webhooks. |
| **Settings** | One screen with its own tabs. See [General Settings](../settings/general-settings.md). |

Click a menu item to open its first tab, then use the tab row to move between the others. Every screen keeps its own address, so existing bookmarks and links keep working.

The menu respects permissions. A user only sees the tabs their role can open, so a moderator who can handle reviews and claims sees **Moderation** with those tabs and nothing else.

## How the list screens work

Claims, Reviews and Email Log share one table. Pro lists such as Transactions use the same one.

### Status views

The row of links above the table (for example **All**, **Pending**, **Approved**, **Rejected**) filters the list by status. Each link shows how many rows it holds. Claims and Reviews open on **Pending** when something is waiting, and on **All** when nothing is.

### Search and filters

Type in the search box and click **Search** (or **Apply** when filters are shown next to it). Click **Clear** to drop the search and every filter.

| Screen | Search | Filters |
|---|---|---|
| **Reviews** | Listing or review text | Any rating, Any listing type, Any time, Owner reply, Reported or not |
| **Claims** | Listing, name or email | None. Use the status views. |
| **Email Log** | Subjects | Any email, Recipient email |

When a filter finds nothing, the table says **Nothing matches these filters** with a **Clear filters** link.

### Sorting

A column header that is a link can be sorted. Click it once for one direction and again to reverse it. Reviews sort by **Rating** and **Date**, Claims by **Submitted**, and Email Log by **Sent**.

### Bulk actions

Tick the checkboxes at the start of the rows, pick an action from **Bulk actions**, and click **Apply**. The tick box in the header selects every row on the current page.

- **Reviews** and **Claims**: **Approve**, **Reject**, **Delete**.
- **Email Log** has no bulk actions.

### Row actions and the Details drawer

The main action for a row (such as **Approve**) sits at the end of the row. Less common and destructive actions, such as **Delete** or **Reverse approval**, live behind the **...** button in the row. Choosing one opens a confirmation first, so a click never deletes anything by itself.

**Details** opens a side panel with the full record:

- **Claims**: the claimant, their proof of ownership, uploaded documents, and their other claims.
- **Email Log**: the email exactly as the member received it.

Press **Close** or Esc to go back to the list.

### Pages

The table is numbered, with previous and next links and a total at the top. When there are more than 20 rows you can switch between **20**, **50** and **100** per page. Listora remembers your choice for each list and for your account.

### Phones and tablets

On narrow screens each row stacks into a card with the column name beside each value, so nothing needs sideways scrolling.

## When two people act on the same item

If another moderator already decided a claim or review, or you open an old tab and click again, Listora does not apply the action twice. It tells you that the item was already in that state and leaves it as it was. Refreshing the page after an action never repeats the action.

## Export CSV

The Email Log has an **Export CSV** button in the page header. See [Email Log](email-log.md).

## Stat cards

Screens that show summary numbers use one card style. Where a card counts something, clicking it opens the list it counts.

## Related

- [Admin Dashboard](admin-dashboard.md) - the first screen under **Listora**.
- [Email Log](email-log.md) - every email Listora sent.
- [Business Claims](business-claims.md) - the Claims list in use.
- [Reviews System](reviews-system.md) - the Reviews list in use.
- [Capabilities & Roles](../developer-guide/capabilities.md) - who can open which tab.
