# Listing Type Editor

> **Availability:** Free + Pro.

The admin page where you create, edit, and delete listing types - Restaurant, Hotel, Real Estate, Job, Event, anything else your directory needs. Each type has its own icon, schema mapping, and custom field set. The Type Editor is distinct from the [Listing Types getting-started guide](../getting-started/listing-types.md): the guide explains the CONCEPT; this page is the admin SURFACE where you manage them.

![Listing Type Editor - list view with icon, name, slug, fields, schema columns](../images/type-editor.png)

## What it is

Listora ships with the listing-type system as a first-class concept: every `listora_listing` post belongs to exactly one type, and that type determines:

- Which **custom fields** appear in the submission wizard.
- Which **Schema.org type** the JSON-LD on the detail page emits.
- Which **default icon** appears on cards / map markers.
- Which **filterable fields** show up as facets on the search block.
- Which **demo pack** seeds matching listings when you run `wp listora demo seed --pack={slug}`.

The Type Editor is where you configure all of that. It's the "schema-design" surface for the directory.

## Where it lives

**WP Admin → Listora → Listing Types** (`?page=listora-listing-types`)

Requires the `manage_listora_types` capability.

## The list view

Each row shows the type's icon (Lucide SVG), name, slug, field count, filterable-field count, Schema.org type, and a Default badge for whichever type new listings get when no type is specified. Default actions per row:

- **Edit** → opens the editor view for that type.
- **Add new field** → jump straight into the Fields tab of the editor.
- **Duplicate** → clone the type with `-copy` slug suffix (useful when launching a new vertical).
- **Trash** → soft-delete. Listings of this type get their type pointer cleared but stay in the database.

The header **Add New Type** button opens the editor with `action=new`.

## The editor view

Three tabs.

### Settings

| Field | What it does |
|---|---|
| **Name** | Customer-facing label everywhere ("Restaurant", "Boutique Hotel"). |
| **Slug** | URL-safe identifier (`restaurant`). Used in admin URLs, REST routes, and filters. Once set, don't change - existing listings of this type lose their type pointer. |
| **Icon** | Lucide icon identifier. Dropdown of 24 common directory icons (Building, Utensils, Home, Hotel, Briefcase, Calendar, Shopping Bag, etc.). |
| **Schema.org type** | Which `@type` to emit in the listing detail JSON-LD. Pick the closest match from the 20-entry Schema.org catalog - LocalBusiness, Restaurant, Hotel, Store, MedicalBusiness, Event, etc. |
| **Default** | Toggle on if this is the type new submissions default to. Only one type can be default at a time - selecting another type unsets the current one. |

### Fields

A drag-to-reorder list of every field that appears in the submission wizard for this type. Each field row:

- **Label** - what customers see in the wizard.
- **Type** - the field input type (text, textarea, number, select, multi-select, image, gallery, file, url, email, phone, date, time, business hours, social links, etc.).
- **Required / Optional** toggle.
- **Filterable** toggle - when on, the field appears as a search facet.
- **Validate** - per-type validation rules (min/max length, regex pattern, allowed file types).

Add new fields with the **+ Add Field** button. Reorder with the drag handle. Delete with the trash icon (confirmation required if the field has saved data on existing listings).

### Schema mapping

Per-field mapping into the Schema.org JSON-LD output. For each field, pick which Schema property it should populate (e.g. address field → `address`, phone field → `telephone`, image field → `image`). Unmapped fields are still rendered on the detail page but don't appear in structured data.

## How you use it

### Add a new type

1. Click **+ Add New Type**.
2. Fill in **Name** (e.g. "Coworking Space") and **Slug** (auto-generated from name; edit if needed).
3. Pick an **Icon** that visually represents the type.
4. Pick the **Schema.org type** closest to your data - `LocalBusiness` or `Place` are safe defaults.
5. Save.
6. Switch to the **Fields** tab and add the fields specific to this type (e.g. "Hot Desk Rate", "Meeting Rooms Available", "24/7 Access" for a coworking space).
7. Optionally configure **Schema mapping**.
8. Switch the type to **Default** if you want new submissions to land in it by default.

### Edit an existing type

Click **Edit** on any row. Changes save per-tab; switching tabs prompts to save unsaved work.

### Delete a type

Trash on the list view. Listings of the trashed type stay in the database but become "untyped" - they lose their type pointer and inherit fallback rendering (no custom fields). Use this when retiring a vertical; existing listings can be reassigned via bulk-edit.

### Use a type from CLI

```bash
wp listora listing-types
```

Outputs a table of every registered type with field counts. Useful for verifying after CSV imports.

## How types map to demo packs

Each type has an optional matching demo pack at `demo/{slug}-pack.php`. Listora ships packs for: restaurant, hotel, real-estate, job-board, general, classified, education, healthcare, place. Seed any pack via `wp listora demo seed --pack={slug}` - see [WP-CLI Commands](../developer-guide/wp-cli-commands.md).

## Permissions

| Capability | Who has it | What it gates |
|---|---|---|
| `manage_listora_types` | Administrator (custom roles via [Capabilities](../developer-guide/capabilities.md)) | View Type Editor, create / edit / delete types and their fields |

This cap is the gate for the **entire** taxonomy admin surface - Categories, Locations, Features all check it too. If you grant `manage_listora_types` to a custom role, that role gets full type-system access.

## Related

- [Listing Types (getting started)](../getting-started/listing-types.md) - concept-level overview.
- [Custom Fields](../developer-guide/custom-fields.md) - programmatic field definition + override hooks.
- [Listing Categories](listing-categories.md) - taxonomy WITHIN a type.
- [Capabilities & Roles](../developer-guide/capabilities.md) - who can manage types.
- [WP-CLI Commands](../developer-guide/wp-cli-commands.md) - `wp listora listing-types` for the CLI inventory.
