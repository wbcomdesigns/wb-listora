# Business Hours

> **Availability:** Free + Pro. Business hours are **Free**. Pro's Google Places import fills them in automatically when a listing is matched to a Google business.

Each listing can publish opening hours per day, including split shifts - a kitchen open 08:00-12:00 and again 17:00-22:00 is expressible as one day with two ranges.

## What it is

A day holds up to **three ranges** by default. That covers the shapes real businesses actually keep: continuous opening, a lunch break, and the occasional third window. Before 1.5.0 a day held exactly one range, so a business with a midday close had to choose between publishing the morning or the evening.

Hours drive three things:

- **The hours table** on the listing page.
- **The open/closed state** shown on cards and in search, computed against the site's timezone.
- **Filtering by open-now**, where the theme or search surface offers it.

## How you use it

### As a member - set your hours

On the submission form or **Dashboard > Listings > Edit**, open the Business Hours section. For each day, either mark it closed or add one to three ranges. A day left empty is treated as unspecified rather than closed, so a listing that has not filled hours in does not advertise itself as permanently shut.

### As a site owner - import them

With Pro and Google Places configured, matching a listing to a Google business imports its opening hours, including split shifts. Google sends one period per opening block, and each block becomes its own range rather than overwriting the previous one.

### As a developer

Read hours through the normalizer, never straight from post meta:

```php
$hours = wb_listora_normalize_hours( $raw );
```

Three storage shapes exist in the wild - the canonical list, the older single-range-per-day form, and the current multi-range form - because the format has changed twice and old rows were not rewritten. `wb_listora_normalize_hours()` is the single place that knows all three and returns one shape. Any reader that skips it will be right on some installs and quietly wrong on others.

Raise the per-day limit with `wb_listora_max_hours_slots`:

```php
add_filter( 'wb_listora_max_hours_slots', function () {
	return 4;
} );
```

Storage and every reader honour the new value immediately. The submission form's own inputs are the one part that needs matching work to offer the extra row.

## Settings & options

| Setting | Where | Default |
|---|---|---|
| Ranges per day | `wb_listora_max_hours_slots` filter | 3 |
| Site timezone | WordPress **Settings > General** | Site default |

Open/closed is computed against the WordPress timezone, so set that correctly before troubleshooting a listing that reads closed when it should be open.

## Good to know

- **Importing from a competitor may not carry hours across.** Source plugins store hours in their own shapes, and Listora's migrators do not guess at a mapping they cannot verify - an unmapped shape is reported rather than silently dropped or misread. Check hours after a migration.
- **A range that ends before it starts is treated as crossing midnight.** 22:00-02:00 is a valid overnight range.

## Related

- [Frontend Submission](frontend-submission.md) - where members enter hours
- [Google Maps](google-maps.md) - Pro, and the Places import that fills hours in
- [Search & Filters](search-and-filters.md) - filtering by open now
- [Import & Export](import-export.md) - what happens to hours on migration
