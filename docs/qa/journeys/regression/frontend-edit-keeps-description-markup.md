---
journey: frontend-edit-keeps-description-markup
plugin: wb-listora
priority: critical
roles: [member-owner, another-member, anonymous, admin]
covers: [submission, frontend-edit, post-content, importers, card-10355097393]
prerequisites:
  - "A published listing owned by a member (subscriber, no unfiltered_html). Create QA-J1 rich with author tester (user 15)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Editing a listing from the frontend keeps its images, video and formatting

`Submission_Controller::submit_listing()` (`includes/rest/class-submission-controller.php:599`) and `update_listing()` (`:1164`) sanitise `post_content` with `wp_kses_post()`, never `sanitize_textarea_field()` (only `duplicate_explanation` at :859 uses the textarea sanitiser). The edit route is `PUT /listora/v1/submit/{id}` (`:173`). The textarea pre-fill must not be texturized: `wb-listora.php:1346` adds `textarea` to `no_texturize_tags`.

Verified via: `rest_do_request()` PUT as owner / other member / anonymous / admin against a throwaway listing, 2026-10-12 (the browser edit form not opened).

## Steps

### 1. Author rich content (admin)
- **Action**: listing with block content: paragraph with `<strong>`, `<!-- wp:image -->` + `<img>`, `<!-- wp:video -->` + `<video>`.
- **Expect**: the page shows image, player and bold text.

### 2. Owner edits something else from the frontend
- **Action**: owner opens Dashboard > My Listings > Edit (inline form `/my-listings/?tab=listings&action=edit&id=<ID>`, `templates/blocks/user-dashboard/tab-listings.php:38-44`) or `PUT /submit/{id}` with only `title`. Save.
- **Expect**: 200 "Your listing has been updated."; `post_content` still contains `<!-- wp:image -->`, `<img`, `<!-- wp:video -->`, `<video` and `<strong>` (all verified true).

### 3. Unsafe markup is stripped (POSITIVE CONTROL: safe markup survives)
- **Action**: owner saves description `<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>`.
- **Expect**: stored `<p>ok</p>alert(1)<img src="x">`: no `<script`, no `onerror`; `<p>` kept. (Verified.)

### 4. Role ladder
- Owner: steps 2-3 (200). Another member (subscriber2): `PUT` -> 403 `listora_forbidden`. Anonymous -> 401 `listora_unauthorized`. Editor/admin: 200 and the owner stays the author (admin saving a member's listing edits it, no duplicate warning). Admin is the control for step 1 only.

### 5. Restore
- Delete the QA-J1 listing.

## Fail diagnostics

| Symptom | File |
|---|---|
| Block comments gone but media renders (textarea shows `<!–`) | `wb-listora.php:1346` `no_texturize_tags` lost `textarea` |
| Media gone after step 2 | a `post_content` write path calls `sanitize_textarea_field()` again: `includes/rest/class-submission-controller.php:599,1164`, `includes/import-export/` |
| Script kept | path writes raw content without `wp_kses_post()` |

## Not verified

The browser form pre-fill (texturize) and importer paths (CSV/JSON/GeoJSON) were not exercised.
