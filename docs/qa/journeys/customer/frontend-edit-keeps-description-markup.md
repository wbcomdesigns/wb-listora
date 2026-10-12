---
journey: frontend-edit-keeps-description-markup
plugin: wb-listora
priority: critical
roles: [owner, other, guest, editor, admin]
covers: [submission, frontend-edit, post-content, importers, card-10355097393]
prerequisites:
  - "A published listing {listing} owned by a member {owner} (subscriber, no unfiltered_html); create a QA- rich listing"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/frontend-edit-keeps-description-markup.md
fails_on: unrecorded
site_profile: required
---

# Editing a listing from the frontend keeps its images, video and formatting

## Why
`Submission_Controller::submit_listing()` (`includes/rest/class-submission-controller.php:599`) and `update_listing()` (`:1164`) sanitise `post_content` with `wp_kses_post()`, never `sanitize_textarea_field()` (only `duplicate_explanation` at `:859` uses the textarea sanitiser). The edit route is `PUT /listora/v1/submit/{id}` (`:173`); the textarea pre-fill must not be texturized (`wb-listora.php:1346` adds `textarea` to `no_texturize_tags`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).

## Steps

### 1. Author rich content (admin)
- **Action**: create the listing with block content: a paragraph with `<strong>`, `<!-- wp:image -->` + `<img>`, `<!-- wp:video -->` + `<video>`.
- **Expect**: the page shows image, player and bold text.

### 2. Owner edits something else from the frontend
- **Action**: {owner} opens Dashboard > My Listings > Edit (`$DASH/?tab=listings&action=edit&id=<ID>`, `templates/blocks/user-dashboard/tab-listings.php:38-44`) or `PUT /submit/{id}` with only `title`. Save.
- **Expect**: 200 "Your listing has been updated."; `post_content` still contains `<!-- wp:image -->`, `<img`, `<!-- wp:video -->`, `<video` and `<strong>`.

### 3. Unsafe markup is stripped (positive control: safe markup survives)
- **Action**: {owner} saves description `<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>`.
- **Expect**: stored `<p>ok</p>alert(1)<img src="x">`: no `<script`, no `onerror`; `<p>` kept.

### 4. The edit form pre-fill is not texturized (browser)
- **Action**: open the edit form for the rich listing.
- **Expect**: the textarea shows `<!-- wp:image -->` literally, not `<!&#8211;`. Not verified live.

### 5. Importers keep markup (added)
- **Action**: import a CSV/JSON row whose description contains `<img>` and `<strong>`.
- **Expect**: stored content keeps both and still strips `<script`. Not verified live (`includes/import-export/`).

## Role ladder
| Actor | Expect |
|---|---|
| {owner} | steps 2-4 (200) |
| {other} | `PUT` -> 403 `listora_forbidden` |
| {guest} | 401 `listora_unauthorized` |
| {editor}, {admin} | 200 and the owner stays the author (no duplicate warning); admin is the control for step 1 |

## Pass criteria
1. A partial frontend edit leaves images, video and formatting intact.
2. Scripts and event handlers are stripped on every write path.
3. The pre-fill textarea shows the real markup.

## Fail diagnostics
| Symptom | File |
|---|---|
| Block comments gone but media renders (textarea shows `<!-`) | `wb-listora.php:1346` `no_texturize_tags` lost `textarea` |
| Media gone after step 2 | a `post_content` write calls `sanitize_textarea_field()`: `includes/rest/class-submission-controller.php:599,1164`, `includes/import-export/` |
| Script kept | path writes raw content without `wp_kses_post()` |

## Not verified
The browser form pre-fill (texturize) and importer paths (CSV/JSON/GeoJSON) were not exercised; REST rungs ran via `rest_do_request()`.

## Cleanup
Delete the QA- listing.
