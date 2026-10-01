# Visual Import

> **Availability:** Pro only. Requires [WB Listora Pro](../getting-started/activating-pro.md). With Pro active, Visual Import is the only importer for CSV files. Free's fixed-column CSV importer is hidden.

## What it does

Visual Import loads listings from a CSV, JSON or GeoJSON file. You upload the file, match its columns to Listora fields on screen, check a preview, and run the import. It handles files with unfamiliar column names, which is the usual case for data exported from another system.

## Where to find it

Go to **Listora → Settings → Import / Export**. With Pro active the tab shows three cards:

- **Visual Import** - this page.
- **Google Import** - search Google Places and import places as listings. It needs a Google Maps API key set under **Settings → Maps**.
- **Competitor Migration** - move listings from another directory plugin. See [Migrating from another plugin](#migrating-from-another-directory-plugin).

The CSV **Export** card from Free is still there. Only the basic CSV importer is replaced.

## How to use it

1. In **Visual Import**, drag your file into the box or click **Choose File**. CSV, JSON and GeoJSON files are supported.
2. Choose the **Listing Type** the rows belong to. The list shows the listing types that are active on your site, so a type you have added or renamed appears here.
3. Set the options:
   - **First row contains column headers** - leave it ticked for most CSV files.
   - **Update existing listings (match by title)** - tick it to update a listing with the same title instead of creating a duplicate.
   - **Default status** - the status new listings get.
4. If you saved a mapping before, pick it under **Saved Templates** and click **Load**.
5. Click **Upload & Map**.
6. On the **Map Fields** step, each **Source Column** is matched to a **Listora Field**. The **Status** column says whether a match was made automatically (**Auto**), by you (**Manual**) or skipped (**Skipped**). Change any match you disagree with. A **Data Preview (first 3 rows)** shows what your file holds.
7. To reuse the mapping, tick **Save this mapping as template** and give it a **Template name**.
8. Click **Preview**. The preview counts the rows that are **valid**, have **warnings** or have **errors**, so you can fix the file before anything is written.
9. Click **Start Import**. The **Import** step shows progress. You can **Cancel Import** while it runs.
10. When it finishes, the screen reports how many rows were **imported**, how many had **errors** and how many were **skipped**. Use **View Imported Listings** to see them, **Download Error Report** for the rows that failed, **Retry Import** if the run failed, or **Import Another File**.

A row with no title is skipped and reported as "Missing title, skipped."

**HTML in descriptions.** Listing descriptions keep their HTML, including links, headings and lists. This also holds when you re-import over an existing listing with **Update existing listings** ticked.

**Saved templates.** Under **Saved Templates** you can **Load**, **Edit** or **Delete** a saved mapping. Use them when you import regular exports from the same source.

## Migrating from another directory plugin

The **Competitor Migration** card shows one entry for each supported plugin. Each entry shows its state:

| State | Meaning |
|-------|---------|
| **Detected** | The plugin is active and has listings. The entry shows how many listings were found and the migration can be started. |
| **Installed** | The plugin is active but has no listings to move yet. The entry says so and the migration button is off. |
| **Not installed** | The plugin is not on this site. The migration button is off and tells you to install the plugin first. |

Run a dry run before the real migration. For plugin-specific guidance see the migration guides: [GeoDirectory](../migrate-from-geodirectory.md), [Directorist](../migrate-from-directorist.md), [Business Directory Plugin](../migrate-from-business-directory-plugin.md), [ListingPro](../migrate-from-listingpro.md) and [HivePress](../migrate-from-hivepress.md).

## Tips

- Import a small sample first, such as 10 rows, and check the listings before you run the whole file.
- Save the mapping as a template as soon as it looks right.
- Large imports run in batches, so you can leave the screen open while they finish.

## Related features

- [Import and Export](import-export.md)
- [Google Maps](google-maps.md)
