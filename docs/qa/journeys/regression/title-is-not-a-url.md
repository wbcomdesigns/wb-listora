---
journey: title-is-not-a-url
plugin: wb-listora
priority: normal
roles: [member, logged-out, admin]
covers: [10213032167]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who can submit listings (QA-J1-member)"
  - "For 201 outcomes: cost 0, no published plan for the type, rate-limit bypass in the runner (see terms-acceptance-enforced.md)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A web address is not a business name

Submission used to require only a non-empty title, so `https://example.com/thing` became the business name and permalink (spam). The guard is `wb_listora_title_is_url()` called in `submit_listing()` (`includes/rest/class-submission-controller.php:621`) and `update_listing()` (`:1148`), returning 400 `listora_title_is_url` with `data.field = "title"`. The rule is deliberately narrow: any whitespace means "a name", and a bare domain is allowed.

## Steps

### 1. A title that IS a web address is refused
- **Action**: `POST /listora/v1/submit` with each title (`listing_type`, `description`, `agree_terms: true`).
- **Expect**: HTTP 400, code `listora_title_is_url`, `data.field` `title` (all verified 2026-10-12):

| Title |
|---|
| `https://example.com/thing` |
| `http://example.com` |
| `www.example.com` |
| `HTTPS://EXAMPLE.COM/X` (case must not matter) |

### 2. Real names are NOT refused (positive control: the rule is not a blanket block)
- **Expect**: 201 every time (all verified 2026-10-12, status `pending`):

| Title | Why it must pass |
|---|---|
| `Booking.com` | a real company's real name |
| `example.com` | a bare domain is indistinguishable from a brand |
| `Best Pizza, see foo.com` | a name that mentions an address is still a name |
| `W Hotels` | ordinary |
| `Cafe 24.7` | dots in names are common |

- If the rule is ever widened, widen it away from these.

### 3. Editing is not a second way in
- **Action**: create a listing with a normal title, then `POST /submit` with `listing_id` and `title: "https://example.com/x"`.
- **Expect**: 400 `listora_title_is_url` and the original title unchanged (code path `:1148`; not run, see below).

### 4. UI surfaces the message
- **Action**: in the wizard type `https://example.com/x` as the Title through to submit.
- **Expect**: the error block (`.listora-submission__error`) shows the REST message; the member stays on the form with their other input intact.

### 5. Role ladder
Member: steps 1-4. Logged out: 401/403 before any title check. Admin / editor: same rule applies (the guard is not role-gated; confirm with one admin POST). Another member: cannot edit the owner's title (403).

## Notes
- Whitespace is the discriminator; refusing `Booking.com` would be a worse product than accepting the occasional bare domain.
- Rate limit: 10 submissions/hour/IP (429 `listora_rate_limit`) fires before the title check; bypass it in the runner or you will read a 429 as a pass/fail.

## Fail diagnostics
| Symptom | File |
|---|---|
| URL title accepted on create | `includes/rest/class-submission-controller.php:621` |
| Accepted on edit only | `class-submission-controller.php:1148` |
| `Booking.com` refused | `includes/media-helpers.php:158` `wb_listora_title_is_url()` |

## Not verified
Steps 1 and 2 were run via `rest_do_request` as QA-J1-member (9 titles; the 5 allowed ones created and deleted). Steps 3 and 4 and the role ladder were not run. The Need-title cases of BC 10213032167 are out of scope here.

Verified via: wp-cli `rest_do_request`.
