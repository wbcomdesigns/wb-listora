# Compare Listings

> **Pro feature** — requires [WB Listora Pro](../getting-started/activating-pro.md).
Let visitors pick 2–4 listings and view them side by side in a clean comparison table — core info, pricing, features, ratings, services, hours, grouped by listing type so apples are compared with apples. Selections persist across the site via localStorage; a floating bar shows current selections and offers a one-click "Compare now" jump.

![Compare Listings — side-by-side comparison table on the modernized 1.0.5 UI](../images/compare-listings.png)

## What it is

For high-consideration directory categories — picking a hotel, comparing real-estate listings, evaluating two doctors — visitors don't decide from a list view. They want a table. Compare Listings gives them that table without leaving the site.

The feature is a self-contained system:

- **A native `listora-pro/comparison` Gutenberg block** renders the side-by-side table — placed on a dedicated "Compare Listings" page that the activator auto-creates.
- **A "Compare" toggle on every listing card + detail page** (rendered via the `wb_listora_card_actions` and `wb_listora_after_listing_fields` hooks) lets visitors add or remove a listing from their comparison set.
- **A floating comparison bar** at the bottom of every page shows the current selection (2–4 listings), with a "Compare now" button that jumps to the comparison page.
- **Selection state is stored in `localStorage`**, so visitors can keep browsing — open new tabs, follow links — and their comparison set survives.
- **Listings are grouped by type** in the comparison table — restaurants under one heading, hotels under another — because comparing a restaurant against a hotel field-by-field isn't useful.
- **The comparison page also reachable via URL** (`/compare-listings/?compare=ID1,ID2,ID3`) so visitors can share a comparison link.
- **REST routes** `GET /listora/v1/comparison` and `GET /listora/v1/comparison/preview` power the table data + the floating-bar preview (public READ endpoints).
- **Field groups in the table are configurable** per block via Inspector controls: Core / Pricing / Features / Ratings / Services / Hours.

## How you use it

### As a site owner — enable + place

1. **Enable the feature:** Listora → Settings → Features → **Comparison** (default: off — turn it on if you want this UX).
2. **Verify the auto-created page:** WP Admin → Pages → look for **Compare Listings**. The activator ensures it exists with the `listora-pro/comparison` block. If it's missing or has the wrong block, click **Pages → Add New**, title it "Compare Listings", and insert the block manually.
3. **Customize what gets compared** (optional): in the block editor, select the Comparison block and use Inspector → Field Groups to toggle which sections appear (Core, Pricing, Features, Ratings, Services, Hours).
4. **Confirm the Compare button is showing on listings:** visit a listing card in your directory; the "Compare" toggle should appear in the card-actions row.

### As a visitor — compare two listings

1. Browse the directory → click **Compare** on a listing card. The toggle becomes "Selected" + the floating bar appears at the bottom of the screen showing "1 listing selected".
2. Add 1 to 3 more listings (max 4) the same way.
3. Click **Compare now** on the floating bar (or visit the Compare Listings page directly).
4. The table renders the listings side by side; remove any from the table via the in-row "Remove" button.
5. To clear all selections, click **Clear** on the floating bar.

### Sharing a comparison

The Compare page reads `?compare=` from the URL, so a fully-formed link like `/compare-listings/?compare=42,103,257` opens those three listings directly — useful for support, sales chats, or external embeds.

## Settings & options

| Setting | Location | Default | Notes |
|---|---|---|---|
| Feature toggle | Settings → Features → Comparison | **Off** | Off by default per product design — turn on intentionally |
| Compare page | (auto-created on activation) | `/compare-listings/` | Idempotent — the activator verifies the page exists with the right block |
| Block field groups | Inspector → Field Groups | All 6 enabled | Per-block override; same Compare page can host multiple blocks with different field selections |
| Max selections | (system) | 4 | Hardcoded UX limit; comparison tables wider than 4 columns hurt readability |
| Storage | `localStorage` | — | Cleared by the visitor's browser settings; not synced across devices |

REST routes (public):

- `GET /wp-json/listora/v1/comparison?ids=42,103` — returns the full comparison data
- `GET /wp-json/listora/v1/comparison/preview?ids=42,103` — lightweight data for the floating-bar preview

## Related

- [Listing Detail](blocks-overview.md#listing-detail) — the source for the per-field data shown in the table.
- [Listing Types](../getting-started/listing-types.md) — type grouping in the table comes from your registered listing types.
- [Quick View Modal (Pro)](quick-view.md) — a lighter alternative for previewing a single listing without leaving the grid.
- [Developer Reference: REST API](../developer-guide/rest-api.md) — for the `/comparison` route shape.
