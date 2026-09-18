---
journey: services-per-listing-type
plugin: wb-listora
priority: normal
roles: [administrator, anonymous, member-owner]
covers: [listing-types, services, rest, dashboard, card-10217625415]
prerequisites:
  - "A listing of a type that has services (the demo Job pack ships one)"
estimated_runtime_minutes: 5
---

# Services can be switched off for a listing type

Services rendered on every type, so a Job listing carried a Services tab — and the demo content had
invented "Fast-Track Interview" to fill it. The type editor now has a Services enabled checkbox
alongside Map, Reviews and Frontend submission.

The gate lives in `Services::enabled_for_listing()`, which every read and write goes through, so the
detail page, the dashboard panel, the REST payload and the write routes cannot disagree. Escape
hatch: `wb_listora_services_enabled`. PHPUnit: `tests/unit/ServicesPerTypeTest.php`.

## Steps

### 1. Default is on, including for types that predate the setting
- **Action**: on an upgraded site, open any existing type in the editor
- **Expect**: Services enabled is ticked, and existing listings still show their services. Absent term meta must read as ON — a plain boolean cast of an empty string is false, which would switch services off for every existing type on upgrade

### 2. Switch it off for a type
- **Action**: Listing Types → Job → untick Services enabled → save
- **Expect** on a Job listing:
  - the detail page has no Services tab and no services panel
  - `GET /listora/v1/listings/{id}/detail` returns `services: []`
  - the member dashboard shows no services panel for that listing
  - creating a service returns `listora_services_disabled` (403)

### 3. Nothing is deleted
- **Action**: switch Services enabled back on
- **Expect**: the previously saved services are listed again, unchanged

### 4. Other types are untouched
- **Expect**: a Restaurant listing still shows its services throughout

## Pass criteria

1. One switch turns services off on every surface at once, including REST.
2. Turning it back on restores what was there.
