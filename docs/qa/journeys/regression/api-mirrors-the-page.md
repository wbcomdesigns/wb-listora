---
journey: api-mirrors-the-page
plugin: wb-listora
priority: critical
roles: [anonymous, author, editor, administrator]
covers: [field-visibility-page, field-visibility-api, listing-api-meta]
prerequisites:
  - "A Restaurant listing with phone and email, owned by a member (create QA-J1-api owned by subscriber2 with Meta_Handler::set_value for email and phone; delete it afterwards)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The listing API shows what the listing page shows

Card 10369254010 (owner decision: mirror the page). A Listing Types field has two switches in the type editor: "Show on listing page" (`show_in_detail`) and "Show in API" (`show_in_rest`), both default on. The API never shows more than the page does, so a field hidden from the page is hidden from the API too; "Show in API" off hides it from the API only. The single gate is `Meta_Handler::get_visible_values( $post_id, $context )` (`includes/core/class-meta-handler.php:353`): a viewer who cannot edit the listing gets only visible fields; an editor of the listing (owner, moderator, admin: `wb_listora_user_can_edit_listing()`) gets every value through the API for `$context = 'rest'`.

Consumers of the gate (all verified): the listing page and JSON-LD (`includes/class-template-helpers.php:1233`, `includes/schema/class-schema-generator.php:43,592`), `GET /listora/v1/listings/{id}` -> `listora_meta` (`includes/rest/class-listings-controller.php:775`), `GET /listora/v1/listings/{id}/detail` -> `meta` (`:922`), `GET /listora/v1/search?q=` -> each `listings[].meta` (`includes/rest/class-search-controller.php:681`).

## Steps (steps 1-2 results verified 2026-10-12 with wp eval against a QA-J1- listing; the type definition was restored after)

### 1. Hide a field from the page
- **Action**: Listing Types > Restaurant > Email > untick "Show on listing page", Save Type.
- **Expect, anonymous**: the listing page shows no email (contact card and JSON-LD `email`); `GET /listora/v1/listings/{id}` `listora_meta` has no `email`; `GET /listora/v1/search?q=<title>` the item's `meta` has no `email`; `GET /listora/v1/listings/{id}/detail` has no `meta.email` and no `schema.email`. Other fields (phone) are still present.
- **Expect, as the listing owner (subscriber2) and as admin**: `listora_meta`, search `meta` and `detail.meta` still include the email.
- **POSITIVE CONTROL**: with the switch back on (step 3), the same anonymous requests include the email, proving the earlier absence is the switch and not a broken endpoint.
- **Expect**: the contact form still emails the owner (the address is used server-side, not exposed).
- **Expect**: with "Show on Card" also on for that field, directory cards do not show it either; the gallery is unchanged.
- **Note**: `GET /wp/v2/listings/{id}` carries no listing meta at all (no `meta` key), so it is not a leak path; do not use it as the control.

### 2. Hide a field from the API only
- **Action**: tick "Show on listing page" again, untick "Show in API" on Phone.
- **Expect, anonymous**: the page shows the phone; `listora_meta`, search `meta` and `detail.meta` have no `phone`. Owner and admin still get it.
- **Known by design / open question**: `detail.schema.telephone` (the page's JSON-LD, embedded in `/detail`) still carries the phone, because the page shows it. See report question.

### 3. Restore both toggles.

### 4. Role ladder
| Viewer | email hidden from page | phone hidden from API only |
|---|---|---|
| anonymous | absent everywhere | page yes, API no |
| another member | same as anonymous | same as anonymous |
| owner of the listing | API still has it | API still has it |
| editor (qa_editor) / admin (control) | API still has it | API still has it |

### 5. Large site
Search results page 1 and page 40 of 2,809 listings return `meta` through the same gate, one `get_visible_values()` per row (query count not measured in this pass).

## Fail diagnostics
| Symptom | File |
|---|---|
| Hidden field in the API | `includes/core/class-meta-handler.php:353-385` (`get_visible_values()`), call sites listed above |
| Switch not saved | `assets/js/admin/type-editor.js:483-484`, REST save path (2.0.0 fix: "Show in API" now saves) |
| Owner loses the value | `wb_listora_user_can_edit_listing()` |

Verified via: wp eval REST dispatch as anonymous / owner / admin against a QA-J1- Restaurant listing; type definition restored and compared byte-for-byte. The page HTML and the contact-form email were not re-checked in this pass.
