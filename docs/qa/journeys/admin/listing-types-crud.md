---
journey: listing-types-crud
plugin: wb-listora
priority: high
roles: [administrator, editor, member-owner, member-other, anonymous]
covers: [listing-types-admin, type-registry, type-editor-switches, builtin-stays-builtin, schema-type-kept, field-settings-preserved, type-delete-moves-listings, type-partial-put, type-services-enabled, services-hidden-not-deleted, 2.0.0-type-editor, bc-10393550054, bc-10393575316, "card 10331936497", bc-10162700303]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} session"
  - "A scratch slug that does not exist: qa-type"
  - "A member {owner} with a published listing of the type under test (for the Services steps)"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default; buddyx-pro light and dark for the frontend]
supersedes:
  - ora:admin/06-listing-types-crud.md
  - ora:regression/type-services-toggle-saves.md
fails_on: step 5 read-back cf032112; step 6 read-back 81d650a5; others unknown
site_profile: required
---

# Admin creates, edits and deletes a listing type; built-ins stay intact; the Services switch saves and hides services

## Why
Listing Types decide the fields, categories and features a listing has. A save that resets field settings, reclassifies a built-in type, strands listings on delete, or a Services switch that reverts, silently breaks owners' directories. Per-type switch effects have their own journeys (`admin/reviews-and-moderation-switches-take-effect.md`, `admin/type-field-visibility-page-api-schema.md`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Screen: `admin.php?page=listora-listing-types` (hub "Listing Types"); editor `&action=new` or `&edit=<slug>`. Field builder per field: Required, Searchable, Filterable, Show on Card, Show on listing page, Show in API, and for Number fields Show as money. Type settings: Status, Name, Slug, Icon, Color, Schema.org Type, Map enabled, Frontend submission, Services enabled, Default for new submissions, Moderation, Reviews, "Listing expires after (days)".

## Steps

### 1. The list
- **Action**: open the screen as {admin} at 1440.
- **Expect**: header "Listing Types" with "Each type decides the fields, categories and features a listing has. A draft type stays hidden from members while you set it up." and primary "Add New Type"; hub tabs "Listing Types | Badges (Pro)"; "<n> listing types" (n equals `wp listora listing-types` row count); columns Type, Status, Fields, Listings, Search engines see; each row "Edit" and "Delete…"; default type carries a "Default" badge.

### 2. Create a type
- **Action**: Add New Type: Name `QA Type`, slug auto `qa-type`, icon, color, Schema.org `LocalBusiness`, Status "Active", Frontend submission ticked, Save Type (`#listora-save-type`). Confirmation: `admin/save-and-reset-confirm-success.md` step 1.
- **Expect**: new row Active, Fields "None yet", a "No categories" warning badge ("Members can submit this type, but their listings will not be filed under any category."); defaults: "Listing expires after (days)" 0 ("0 = never expires"), Moderation and Reviews "Use the site setting".
- **Registry**: `wp listora listing-types` lists `qa-type` with Built-in `No`.
- **Boundary input (added)**: empty name, 300-character name, markup and emoji in the name, a duplicate slug. Expect server-side rejection or sanitising, never a fatal. Not verified live.

### 3. Add a field with a choice option (BC 10162700303)
- **Action**: add a Select field "QA Choice", "Add Option" `Smoke Choice`, Save Type.
- **Expect**: term meta stores `{value:'smoke-choice', label:'Smoke Choice'}` (never a bare string); Add Listing > the type > the field renders the option; HTTP 200 and no `Cannot access offset` in `debug.log`; the Directory filter renders the option (when Filterable).

### 4. The new type shows up where it must
- **Expect**: Directory type tabs include "QA Type"; Add Listing's type step offers it with its icon; flipping Status to "Draft" removes it from both (list badge Draft, tooltip "Hidden from members until you set it to Active.").

### 5. Edit keeps field settings
- **Action**: tick Required/Searchable/Filterable/Show on Card on "QA Choice", set Schema.org property, Placeholder, Help text; Save; reopen.
- **Expect**: every setting as saved; a partial update `PUT /listora/v1/listing-types/qa-type {"label":"QA Type 2"}` leaves moderation, reviews, fields, categories untouched.
- **Read-back** (card 10393575316): `PUT` a field with `min` 5, `max` 50, `show_in_api` true; open the type in the editor and press Save Type unchanged; `GET /listing-types/qa-type` returns the same `min`, `max`, `show_in_api`. fails_on cf032112. Not verified live.
- **Partial PUT on a built-in**: `PUT /listing-types/{type} {"review_enabled":false}` then `{"name":"<same name>"}`: after the first `review_enabled:false` and `reviews:"off"`; after the name-only PUT both unchanged and `is_builtin:true`; restore with `{"review_enabled":true}` (reviews back to `"global"`).

### 6. Built-in types stay built in
- **Action**: Edit {type} (a built-in, e.g. Restaurant) and Job, change nothing, Save Type.
- **Expect**: Built-in still Yes for both, Schema column unchanged (Restaurant `Restaurant`, Job `JobPosting`); not duplicated; "Delete…" still warns.
- **Read-back**: `GET /listora/v1/listing-types/{type}` `is_builtin: true` after a no-change save and after a changed-label save. fails_on 81d650a5. Not verified live.
- **Also**: Education has Phone, Email and Website under its Provider group on a fresh install; an upgraded site keeps its saved field list.

### 7. Services switch saves and hides services
- **Action**: edit a type > untick **Services enabled** (`#listora-type-services`, hint "Off for types like Jobs. Saved services are hidden, not deleted.") > Save Type; reload. API twin: `PUT /listing-types/{type} {"services_enabled":false}`.
- **Expect**: still unticked; term meta `_listora_services_enabled` is `0`; other settings (moderation `global`, map `1`) unchanged.
- **Dashboard**: as {owner} open `/my-listings/`: that type's row has no `.listora-dashboard__services-count`, no `button.listora-dashboard__services-toggle`, no `.listora-dashboard__services-panel`; other types keep theirs; the wp-admin Services box `#wb_listora_services` is not registered.
- **Switch back on (positive control)**: tick, save, reload: button and panel return with previously saved services intact (hidden, not deleted).
- **Default**: a type with no `_listora_services_enabled` meta reads as enabled.
- **Public page**: {guest} sees no "Services" section for a services-off type (control: shown when on). Browser half of the 2026-10-12 run not exercised.

### 8. Delete moves listings first
- **Action**: create a listing of `qa-type`, then "Delete…" on its row.
- **Expect**: inline form "1 listing is a QA Type. Choose the type it becomes; its details stay, and fields the new type does not have are hidden." with a required "Move listings to" select and "Move listings and delete type"; after confirming "Type deleted. 1 listing was moved to the type you chose."; type gone from the registry; the listing still published under the new type; no orphan term relationship.
- **Positive control**: a type with no listings shows "No listings use this type. Its fields and settings are deleted." and a plain "Delete type".

### 9. 390px
- **Expect**: field builder rows stack, checkboxes wrap, sidebar sections (Categories, Features, Review Criteria, Type Settings) drop below the builder, "Save Type" reachable, no horizontal scroll; Services row actions (Services, Edit, View, More) wrap with each >= 40 px tall.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-9 | pass |
| {editor} | the screen URL; `POST /listing-types` | not allowed (needs `manage_listora_types`); 403 |
| {owner} | steps 7 dashboard half | button present/absent as above; `PUT /services/{id}` on another member's listing 403 |
| {other} | `GET /listora/v1/listing-types`; `PUT /listing-types/{type}` | 200 public read (names and public settings only); 401/403 on write |
| {guest} | `GET /listora/v1/listing-types` | 200 public read |

## Pass criteria
1. A custom type can be created, edited, hidden (Draft) and deleted with listings moved.
2. Choice options round-trip without a fatal.
3. Saving never resets field settings or per-type switches; built-ins stay built in.
4. Services off hides the dashboard Services UI and keeps saved services; on restores them.
5. Only `manage_listora_types` can change types; the public read stays public.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Built-in turns custom | save writes builtin flag false | `Type_Editor` save, `Listing_Types_Controller` |
| Field flags reset | editor posts a fixed subset | `assets/js/admin/type-editor.js` |
| 500 on Add Listing after option add | option stored as bare string | field normaliser (`customer/submission-field-values-round-trip.md`) |
| Delete leaves listings typeless | reassign skipped | `Type_Editor::handle_delete` |
| Services untick reverts | not sent / not stored as `1`/`0` | `assets/js/admin/type-editor.js` (`services_enabled`), `includes/rest/class-listing-types-controller.php`, `includes/core/class-listing-type-registry.php` |
| Dashboard still shows button | gate | `templates/blocks/user-dashboard/tab-listings.php` (`$dash_svc_on`) |

## Not verified
Step 2 boundary input, step 5/6 read-backs, step 7 editor-UI save and public-page row.

## Cleanup
Delete `qa-type`; restore any built-in switch you changed (`services_enabled` back to ON, `review_enabled` back to true).
