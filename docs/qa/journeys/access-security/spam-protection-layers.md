---
journey: spam-protection-layers
group: access-security
actor: system
mode: api
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, administrator]
covers: [honeypot, rate-limit-per-ip, captcha, captcha-bypass-cli, akismet-integration, banned-words, url-density-cap, retry-after-header, bc-10393803241, bc-10393853941]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Free active (spam protection is a Free feature); Pro optional (step 11)"
  - "A throwaway member (subscriber) {other} and {admin}; fixtures prefixed QA-J1-"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes: []
fails_on: "d165204f (Free) and f93ce28 (Pro Needs), step 12; unknown for step 13"
---

# Spam protection: what actually guards each public write

## Why
Each public write surface has its own set of layers (not one six-layer pipeline); a layer missing from a surface, or a 429 without `Retry-After`, lets junk in or leaves clients retrying blind. The matrix below is the contract.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). System/API journey: no frontend screen is asserted (responses are REST), so no theme or viewport matrix. Verified via `rest_do_request` and curl.
- Order inside `/submit`: honeypot, nonce, captcha, `Anti_Spam` (keyword, then URL count, then Akismet), then the rate limit.

| Surface | Honeypot | Captcha | Keyword + URL density + Akismet (`Anti_Spam`) | Rate limit |
|---|---|---|---|---|
| `POST /listora/v1/submit` (new + edit) | `listora_hp_field` | yes | yes (admins and editors exempt) | `submission`: 30/h per user, 10/h per IP |
| `POST /listora/v1/listings/{id}/contact-form` | `hp` (silent success) | no | yes | 3/h per sender and listing, 20/day per listing |
| `POST /listora/v1/listings/{id}/reviews` | none | yes | NO | `review_create` |
| `POST /listora/v1/claims` | none | NO | NO | `claim_submit` (daily window) |

The contact-form layers (honeypot, keyword, URL density, 3/h and 20/day caps) are asserted once in `contact-leads/listing-contact-form.md` steps 8, 10, 11; this file owns the cross-surface matrix, submit, captcha and the Retry-After contract.

## Steps

### 1. Submission honeypot
- **Action**: as {other} `POST /listora/v1/submit` with a valid draft body plus `listora_hp_field: "x"`.
- **Expect**: `403 listora_spam` "Submission blocked."; no listing created. Positive control: the same body without the field returns `201 {status:"draft"}` (delete the draft).
- **Read-back**: `wp post list --post_type=listora_listing --post_status=draft --format=count` unchanged by the 403.

### 2. Keyword blacklist
- **Action**: same body with `description: "QA-J1 free bitcoin casino"`.
- **Expect**: `422 listora_spam_keyword` "Your submission contains content that looks like spam. Please review and try again." Defaults (case-insensitive substring over title, description, author name): casino, viagra, cialis, escort, porn, sex chat, crypto pump, pump and dump, free bitcoin, forex signals. No option for this list; extend with `wb_listora_antispam_keyword_blacklist`.
- **Read-back**: no draft created.

### 3. URL density
- **Action**: description with 4 `http://` URLs, then exactly 3.
- **Expect**: 4 -> `422 listora_spam_urls` "Your description contains too many links (max 3). Trim a few and resubmit."; 3 passes (positive control). Tune with `wb_listora_antispam_max_urls` (0 disables).

### 4. Exemption control
- **Action**: as {admin} repeat step 2.
- **Expect**: NOT `422`; the request proceeds (observed `409 listora_duplicate_detected` when the QA draft existed). `wb_listora_antispam_should_check` can force checks on.

### 5. Captcha enabled
- **Action**: set `captcha_provider` to `recaptcha_v3` with a site and secret key in `wb_listora_settings` (Listora > Settings > Security); from a real HTTP session (cookie + `X-WP-Nonce`; WP-CLI and application-password requests bypass) `POST /submit` with no token, then a bogus token.
- **Expect**: no token `400 listora_captcha_missing` "CAPTCHA verification is required. Please try again."; bogus `403 listora_captcha_failed` "CAPTCHA verification failed. Please try again." (needs outbound access). Captcha counts as enabled only when BOTH keys are set. Restore the provider to `none`.
- **Read-back**: with one key only, no captcha error.

### 6. Captcha bypass contexts
- **Expect**: `Captcha::verify()` is true under WP-CLI, cron, application-password requests and when `wb_listora_captcha_bypass` returns true; `wp eval '\WBListora\Captcha::verify("")'` returns `true` even with the provider on. There is no `should_skip_captcha()` helper.

### 7. Rate limiting: scope and header
- **Action**: from one IP exceed one limiter (e.g. contact form 4th send) and then call another listing.
- **Expect**: `429` on the limited route only; counters are per listing; every Listora 429 that says when to retry carries `Retry-After` (`includes/class-rate-limiter.php:446`). `POST /submit/check-duplicate` needs a login and has no counter of its own.
- **Read-back**: delete `listora_rl_*` and `wb_listora_contact_*` transients you created.

### 8. Reviews and claims: keyword, URL and Akismet are NOT applied
- **Action**: as {other} create a review whose body contains "casino viagra" and 5 URLs.
- **Expect** (correct behaviour): `422 listora_spam_keyword` (`docs/website/features/spam-protection.md` promises keyword, URL-density and Akismet checks on reviews and claims).
- **expected-fail: reviews and claims skip Anti_Spam** - today `201 "Review submitted and pending approval."`; moderation is the only guard (`includes/rest/class-reviews-controller.php:455` has no Anti_Spam call). Delete the QA review row afterwards. Same for `POST /claims` with a spam `proof_text`.
- **Read-back**: review row exists today.

### 9. Logged-in bypass is NOT a thing
- **Action**: as {admin} `POST /submit` repeatedly.
- **Expect**: still counted against `submission` (30/h per user); only the `wb_listora_rate_limit_bypass` filter skips a limit. Do not assert "no 429 for admins".

### 10. Boundary input on spam-checked fields
- **Action**: title/description with empty string, whitespace only, 10k characters, markup `<script>`, emoji, unicode keyword variants (`C4SINO`, full-width characters).
- **Expect**: empty/whitespace -> required-field error not a spam code; markup is sanitised not rejected as spam; the documented keyword list is substring-based so obfuscated variants pass (record, not a defect). Not verified live.

### 11. Pro audit log (Pro only, skip on Free)
- **Expect**: a spam rejection leaves an audit row. Not verified. Free has no audit log surface.

### 12. Every 429 carries Retry-After (bc-10393803241)
- **Action**: drive each limiter past its cap from one IP: contact form (4th in an hour; alias `POST /listings/{id}/contact` with Pro), unsubscribe landing (repeat the link), app sign-in (`includes/rest/class-auth-controller.php`, repeat wrong credentials), space pending submission (`includes/rest/class-submission-controller.php`), and with Pro Needs create (`POST /needs`).
- **Expect**: each 429 has header `Retry-After` equal to a positive integer and, where the body has `data.retry_after`, the same number; no 429 without the header.
- **Read-back**: `curl -si ... | grep -i '^retry-after'` per route; positive control: the request under the cap returns 200/201 with no `Retry-After`. **fails_on**: d165204f (Free), f93ce28 (Pro Needs). Not verified live.

### 13. Retry-After counts down; quota limits are not 429 (bc-10393853941)
- **Action**: on every limiter route from step 12 (the logged-out `public_read` countdown is asserted once in `access-security/listing-api-read-rate-limit.md` step 3) send a limited request, wait 60 s, send another; separately exhaust a quota limit (listings per member, monthly submissions).
- **Expect**: the second `Retry-After` is about 60 smaller than the first (never the full window again); a quota denial answers 409 or 403 with its own code and NO `Retry-After`.
- **Read-back**: record both header values; the `listora_rl_*` transient timeout matches the header +/- 2 s. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | contact-form honeypot/nonce/spam/rate rows (see 16); `/submit` requires login |
| {other} (member) | steps 1-3, 7-9, 12 |
| {admin} (control) | exempt from `Anti_Spam` (step 4), still rate-limited (step 9) |

## Pass criteria
1. Every row of the matrix behaves as written; the two "NO" cells are steps flagged expected-fail so a future fix is noticed.
2. Each refusal creates nothing and sends nothing; each positive control succeeds.
3. Captcha is enforced only with both keys set and never blocks WP-CLI, cron or application passwords.
4. Every 429 carries a counting-down `Retry-After`; quota denials do not.

## Fail diagnostics
| Symptom | Likely cause | File:line |
|---|---|---|
| Honeypot accepted | param name or order | `includes/rest/class-submission-controller.php:534` |
| Spam text accepted on submit | pipeline skipped | `includes/class-anti-spam.php:35`, call site `class-submission-controller.php:561` |
| Keyword list / URL cap wrong | defaults | `class-anti-spam.php:105`, `:165` |
| Captcha never enforced | keys missing or bypass | `includes/class-captcha.php:33`, `:116` |
| No 429 / no Retry-After | limiter | `includes/class-rate-limiter.php:209`, `:446` |
| Reviews not filtered | no Anti_Spam call | `class-reviews-controller.php:455` |

## Not verified
Akismet (not active on the QA site), the Pro audit log, the `public_read` cap (120 per minute per IP for logged-out reads, source only), admin submit 30/h and IP 10/h exhaustion (source only).

## Cleanup
Delete QA drafts/reviews; restore captcha provider to `none`; delete `listora_rl_*` and `wb_listora_contact_*` transients.
