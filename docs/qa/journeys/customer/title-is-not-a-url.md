---
journey: title-is-not-a-url
plugin: wb-listora
priority: normal
roles: [owner, other, guest, admin]
covers: [10213032167, title-url-guard, submission-validation]
prerequisites:
  - "Site reachable at $SITE_URL; a member who can submit listings ({owner})"
  - "For 201 outcomes: cost 0, no published plan for the type, rate-limit bypass in the runner (`wb_listora_rate_limit_bypass`; the 10/hour/IP limit 429 `listora_rate_limit` fires before the title check)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: "title https://example.com/thing accepted by POST /submit"
---

# A web address is not a business name

## Why
Submission once required only a non-empty title, so `https://example.com/thing` became the business name and permalink (spam). The guard is `wb_listora_title_is_url()` called in `submit_listing()` and `update_listing()`, returning 400 `listora_title_is_url` with `data.field = "title"`. The rule is deliberately narrow: any whitespace means "a name", and a bare domain is allowed (refusing `Booking.com` would be a worse product).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Use `rest_do_request` as {owner}; prefix created titles `QA-J1-` and delete them afterwards.

## Steps

### 1. A title that IS a web address is refused
- **Action**: `POST /listora/v1/submit` with each title (`listing_type`, `description`, `agree_terms: true`).
- **Expect**: HTTP 400, code `listora_title_is_url`, `data.field` `title` for: `https://example.com/thing`, `http://example.com`, `www.example.com`, `HTTPS://EXAMPLE.COM/X` (case must not matter).

### 2. Real names are NOT refused (positive control)
- **Expect**: 201 (status `pending`) for `Booking.com`, `example.com`, `Best Pizza, see foo.com`, `W Hotels`, `Cafe 24.7`. If the rule is ever widened, widen it away from these.

### 3. Boundary input
- **Action**: titles that are empty, whitespace only, 300 characters, markup `<b>x</b>`, quotes, emoji, and a URL with leading/trailing spaces.
- **Expect**: empty/whitespace 400 on the required-title rule (not `listora_title_is_url`); markup is sanitised to text; a padded URL is still refused. Not verified live.

### 4. Editing is not a second way in
- **Action**: create a listing with a normal title, then `POST /submit` with `listing_id` and `title: "https://example.com/x"`.
- **Expect**: 400 `listora_title_is_url` and the original title unchanged (read back `post_title`).

### 5. UI surfaces the message
- **Action**: in the wizard type `https://example.com/x` as the Title and submit; at 1440 and 390.
- **Expect**: the error block `.listora-submission__error` shows the REST message; the member stays on the form with other input intact; no horizontal scroll at 390.

### 6. Retry
- **Action**: double-click submit; replay the request from two tabs.
- **Expect**: identical 400 each time, no draft created.

## Role ladder
| Actor | Expect |
|---|---|
| {owner} | steps 1-6 |
| {guest} | 401/403 before any title check |
| {admin} / {editor} | same rule applies (guard is not role-gated; confirm with one POST) |
| {other} | cannot edit the owner's title (403) |

## Pass criteria
1. Every URL-shaped title is refused on create and on edit with `listora_title_is_url`.
2. The five real-name controls return 201.
3. The wizard shows the message without losing input.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| URL title accepted on create | guard call lost | `includes/rest/class-submission-controller.php` `submit_listing()` (~:621) |
| Accepted on edit only | guard call lost | same `update_listing()` (~:1148) |
| `Booking.com` refused | rule widened | `includes/media-helpers.php` `wb_listora_title_is_url()` (~:158) |

## Not verified
Steps 1-2 ran via `rest_do_request` as {owner} (9 titles; the 5 allowed ones created and deleted). Steps 3-6 and the ladder were not run. The Need-title cases of BC 10213032167 are out of scope here.

## Cleanup
Delete every QA-J1- listing created; remove the bypass filter.
