---
journey: services-box-category-checkboxes
plugin: wb-listora
priority: normal
roles: [admin, editor]
covers: [services-category-checkboxes, services-metabox, card-10354973676]
prerequisites:
  - "A Service listing {listing} in wp-admin; at least two service categories"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: []
supersedes:
  - ora:regression/directory-search-and-small-fixes.md
fails_on: unrecorded
site_profile: required
---

# The Services box picks categories with checkboxes in a short scrolling list

## Why
The service category field was a hard-to-use control. `Services_Metabox::render_category_select()` (`includes/admin/class-services-metabox.php:345`) now renders a checkbox list in a short scrolling box that keeps the choice.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).

## Steps

1. **Render**: edit {listing} in wp-admin. **Expect**: the Services box shows a checkbox list (not a `<select>`) inside a short scrolling container.
2. **Save and reload**: tick a category, Save, reload. **Expect**: the choice is kept; unticking and saving clears it.
3. **Read-back**: the saved term/ID is in the service row (`${PREFIX}listora_services`) or its term relationship.
4. **Many categories** (added): with 30+ service categories the box scrolls and does not push the Save button off screen. Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| {admin}, {editor} | steps 1-4 |
| member / {guest} | no wp-admin access |

## Pass criteria
Checkbox list renders and the choice persists across save and reload.

## Fail diagnostics
| Symptom | File |
|---|---|
| Services box not checkboxes | `includes/admin/class-services-metabox.php:345` |

## Not verified
Not exercised in a browser.

## Cleanup
Untick any category added for the run.
