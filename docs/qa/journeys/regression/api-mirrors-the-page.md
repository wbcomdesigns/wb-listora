---
journey: api-mirrors-the-page
plugin: wb-listora
priority: critical
roles: [anonymous, author, administrator]
covers: [field-visibility-page, field-visibility-api, listing-api-meta]
prerequisites:
  - "A Restaurant listing with phone and email, owned by a member"
estimated_runtime_minutes: 10
---

# The listing API shows what the listing page shows

Card 10369254010 (owner decision: mirror the page).

## Steps

### 1. Hide a field from the page
- **Action**: Listing Types > Restaurant > Email > untick "Show on listing page", Save Type.
- **Expect**, as a visitor: the listing page shows no email (contact card and JSON-LD); `GET /listora/v1/listings/{id}` `listora_meta`, `/listings?fields=` full mode `meta`, `/search` `meta` and `wp/v2/listings/{id}` carry no email.
- **Expect**, as the listing owner (or admin): `GET /listora/v1/listings/{id}` still includes the email.
- **Expect**: the contact form still emails the owner.
- **Expect**: with "Show on Card" also on for that field, directory cards for the listing do not show it either; `/listings/{id}/detail` gallery is unchanged.

### 2. Hide a field from the API only
- **Action**: tick "Show on listing page" again, untick "Show in API" on Phone.
- **Expect**: page shows the phone; visitor API responses do not.

### 3. Restore both toggles.
- **On fail**: `Meta_Handler::get_visible_values()`, `Meta_Handler::register_field_meta()`, `assets/js/admin/type-editor.js`.

## Pass criteria

1. A visitor never gets a field through any API that the page hides.
