---
journey: frontend-edit-keeps-description-markup
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [submission, frontend-edit, post-content, importers, card-10355097393]
prerequisites:
  - "A published listing owned by a member (subscriber, no unfiltered_html)"
estimated_runtime_minutes: 5
---

# Editing a listing from the frontend keeps its images, video and formatting

Regression sentinel for `Submission_Controller::submit_listing()` / `update_listing()` and the CSV, JSON, GeoJSON, background and Pro visual importers: listing `post_content` is rich HTML and is sanitized with `wp_kses_post()`, never `sanitize_textarea_field()`.

## Background

Card 10355097393. The frontend edit form pre-fills the description textarea with raw `post_content`, and both submission paths ran it through `sanitize_textarea_field()`, which strips every tag. A listing authored in the block editor lost all its images, video and formatting the first time the owner saved anything from the frontend, even without touching the description.

## Steps

### 1. Author rich content in wp-admin
As admin, open the listing in the block editor, add a paragraph with **bold** text, an Image block and a Video block. Update.
- **Expect**: the listing page shows the image, the video player and the bold text.

### 2. Owner edits something else from the frontend
As the owner, open the listing in the frontend Edit Listing form (dashboard > My Listings > Edit). Change only the title. Save.
- **Expect**: the listing page still shows the image, video and bold text. `post_content` still contains `<!-- wp:image -->`, `<img`, `<!-- wp:video -->`, `<video` and `<strong>`.

### 3. Unsafe markup is still stripped
As the owner, add `<script>alert(1)</script>` and `<img src=x onerror=alert(1)>` to the description and save.
- **Expect**: no `<script`, no `onerror` in `post_content`; no alert on the listing page.

### 4. Restore
Delete the test content.

## Fail diagnostics
- Media gone after step 2 -> a listing `post_content` write path uses `sanitize_textarea_field()` again (grep `includes/rest/class-submission-controller.php` and `includes/import-export/`).
- Script kept in step 3 -> the path writes raw content without `wp_kses_post()`.
