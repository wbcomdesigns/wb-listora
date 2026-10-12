---
journey: admin-listing-types-crud
plugin: wb-listora
priority: high
roles: [administrator, qa_editor, member-other, anonymous]
covers: [listing-types-admin, type-registry, type-editor-switches, builtin-stays-builtin, per-type-moderation-reviews, schema-type-kept, field-settings-preserved, type-delete-moves-listings, 2.0.0-type-editor]
prerequisites:
  - "Site reachable at $SITE_URL; admin ?autologin=1"
  - "A scratch slug that does not exist: j20-type"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default; buddyx-pro light for the frontend]
---

# Admin creates, edits and deletes a custom listing type; built-in types stay intact

Listing Types (`admin.php?page=listora-listing-types`, hub "Listing Types") lists every type with Status (Active/Draft), Fields, Listings and "Search engines see" (Schema.org). The editor (`&action=new` or `&edit=<slug>`) has the field builder (per field: Required, Searchable, Filterable, Show on Card, **Show on listing page**, **Show in API**, and for Number fields **Show as money (site currency)**), categories and features pickers, review criteria, and TYPE SETTINGS: Status, Name, Slug, Icon, Color, Schema.org Type, Map enabled, Frontend submission, Services enabled, Default for new submissions, **Moderation**, **Reviews**, "Listing expires after (days)". Per-type switch behaviour has its own journeys: `admin/2.0-per-type-moderation-and-reviews-take-effect.md`, `admin/2.0-type-field-visibility-page-api-schema.md`.

## Steps

### 1. The list (administrator, 1440)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-listing-types&autologin=1`.
- **Expect**: shared header "Listing Types" with "Each type decides the fields, categories and features a listing has. A draft type stays hidden from members while you set it up." and a primary "Add New Type" button; hub tabs "Listing Types | Badges (Pro)"; count text "<n> listing types"; columns Type, Status, Fields, Listings, Search engines see; each row has "Edit" and "Delete…". The default type carries a "Default" badge.
- **On fail**: `Type_Editor::render_list` (`includes/admin/class-type-editor.php` ~262).

### 2. Create a type
- **Action**: "Add New Type": Name `J20 Type`, Slug auto `j20-type`, Icon, Color, Schema.org Type `LocalBusiness`, Status "Active: members can submit and browse it", Frontend submission ticked, then "Save Type" (`#listora-save-type`).
- **Expect**: redirect to the list with the new row (Status Active, Fields "None yet" badge until fields are added); a "No categories" warning badge when none are assigned ("Members can submit this type, but their listings will not be filed under any category.").
- **Default**: the new type's "Listing expires after (days)" is 0 ("0 = never expires"), Moderation and Reviews both "Use the site setting".
- **Registry**: `wp listora listing-types` lists `j20-type` with Built-in `No`.

### 3. Add a field with a choice option (BC 10162700303 sentinel)
- **Action**: in the field builder add a Select field "J20 Choice", "Add Option" `Smoke Choice`, Save Type.
- **Expect**: term meta stores the option as `{value:'smoke-choice', label:'Smoke Choice'}` (never a bare string); Add Listing > pick the type > the field renders with the option; HTTP 200 and no `Cannot access offset` in `debug.log`; the Directory filter for the field (when Filterable ticked) renders the option.

### 4. The new type shows up where it must
- **Expect**: the Directory's type tabs include "J20 Type"; Add Listing's type step offers it with its icon; flipping Status to "Draft: hidden from members" removes it from both (and the list badge reads Draft with the tooltip "Hidden from members until you set it to Active.").

### 5. Edit keeps field settings (2.0.0)
- **Action**: Edit `j20-type`; tick Required/Searchable/Filterable/Show on Card on "J20 Choice", set Schema.org property, Placeholder, Help text; Save Type; reopen.
- **Expect**: every setting is exactly as saved; saving the type again from the list or via `PUT /listora/v1/listing-types/j20-type {"label":"J20 Type 2"}` (partial update) leaves ALL other settings (moderation, reviews, fields, categories) untouched.

### 6. Built-in types stay built in
- **Action**: Edit Restaurant and Job, change nothing, Save Type.
- **Expect**: `wp listora listing-types` still shows Built-in = Yes for both and the Schema column unchanged (Restaurant `Restaurant`, Job `JobPosting`); the type is not duplicated, and the "Delete…" action still warns before removing.
- **Also**: Education has Phone, Email and Website under its Provider group on a fresh install; an upgraded site keeps its saved field list.

### 7. Delete moves listings first
- **Action**: create a listing of `j20-type`, then "Delete…" on its row.
- **Expect**: the row's inline form says "1 listing is a J20 Type. Choose the type it becomes; its details stay, and fields the new type does not have are hidden." with a required "Move listings to" select ("Choose a type…") and a "Move listings and delete type" button; after confirming: "Type deleted. 1 listing was moved to the type you chose."; the type is gone from the registry; the moved listing is still published under the new type; no orphan term relationship for the deleted slug.
- **Positive control**: a type with no listings shows "No listings use this type. Its fields and settings are deleted." and a plain "Delete type".

### 8. Role ladder
| Rung | Action | Expect |
|---|---|---|
| administrator | steps 1-7 | pass |
| qa_editor | the screen URL | not allowed (needs `manage_listora_types`) and `POST /listing-types` -> 403 |
| member-other / anonymous | `GET /listora/v1/listing-types` | 200 public read (names and public settings only; no admin-only internals) |
| member-other | `PUT /listora/v1/listing-types/restaurant` | 401/403 |

### 9. 390px
- **Expect**: the field builder rows stack, checkboxes wrap, the sidebar sections (Categories, Features, Review Criteria, Type Settings) drop below the builder, "Save Type" stays reachable, no horizontal scroll.

## Pass criteria

1. A custom type can be created, edited, hidden (Draft) and deleted with listings moved, and appears/disappears on Directory and Add Listing accordingly.
2. Choice options round-trip without a fatal.
3. Saving never resets field settings or per-type switches, and built-ins stay built in with their Schema type.
4. Only `manage_listora_types` can change types; the public read stays public.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Built-in turns custom after save | save writing `is_default`/builtin flag false | `Type_Editor` save, `Listing_Types_Controller` |
| Field flags reset | editor posting a fixed subset of props | `assets/js/admin/type-editor.js` (~851) |
| 500 on Add Listing after option add | option stored as bare string | field normaliser (`regression/string-shaped-field-options-fatal.md`) |
| Delete leaves listings typeless | reassign skipped | `Type_Editor::handle_delete` |
