---
journey: spam-protection-layers
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, administrator]
covers: [honeypot, rate-limit-per-ip, captcha, captcha-bypass-cli, akismet-integration, banned-words, url-density-cap, contact-form-spam, bc-10393803241, bc-10393853941]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Free active (spam protection is a Free feature); Pro optional"
  - "A throwaway member (subscriber) and the admin; fixtures prefixed QA-J1-"
estimated_runtime_minutes: 8
covers_doc: features/spam-protection
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---
<!-- System/API journey: no frontend screen is asserted (responses are REST), so no theme or viewport matrix. The role ladder is the member, the visitor and the admin as the exempt control. Verified live on 2026-10-12 via rest_do_request and curl. -->

# Spam protection: what actually guards each public write

The older version of this journey described one six-layer pipeline over submit, reviews and claims. That is not what the code does. Each surface has its own set, and some documented layers do not exist on some surfaces (see the matrix and the PRODUCT-BUGS in the refresh report).

| Surface | Honeypot | Captcha | Keyword + URL density + Akismet (`Anti_Spam`) | Rate limit |
|---|---|---|---|---|
| `POST /listora/v1/submit` (new + edit) | `listora_hp_field` | yes | yes (admins and editors exempt) | `submission`: 30/h per user, 10/h per IP |
| `POST /listora/v1/listings/{id}/contact-form` | `hp` (silent success) | no | yes | 3/h per sender and listing, 20/day per listing |
| `POST /listora/v1/listings/{id}/reviews` | none | yes | NO | `review_create` |
| `POST /listora/v1/claims` | none | NO | NO | `claim_submit` (daily window) |

Order inside `/submit`: honeypot, nonce, captcha, `Anti_Spam` (keyword, then URL count, then Akismet), then the rate limit.

## Steps

### 1. Submission honeypot
- **Action**: as a member, `POST /listora/v1/submit` with a valid draft body plus `listora_hp_field: "x"`.
- **Expect**: `403 listora_spam` "Submission blocked."; no listing created. POSITIVE CONTROL: the same body without the field returns `201 {status:"draft"}` (delete the draft).

### 2. Keyword blacklist
- **Action**: same body with `description: "QA-J1 free bitcoin casino"`.
- **Expect**: `422 listora_spam_keyword`, "Your submission contains content that looks like spam. Please review and try again." Default patterns: casino, viagra, cialis, escort, porn, sex chat, crypto pump, pump and dump, free bitcoin, forex signals (case-insensitive substring over title, description and author name). There is no option for this list; sites extend it with the `wb_listora_antispam_keyword_blacklist` filter.

### 3. URL density
- **Action**: description with 4 `http://` URLs, then with exactly 3.
- **Expect**: 4 gives `422 listora_spam_urls`, "Your description contains too many links (max 3). Trim a few and resubmit."; 3 passes (POSITIVE CONTROL). Tune with `wb_listora_antispam_max_urls` (0 disables).

### 4. Exemption control
- **Action**: as the admin, repeat step 2.
- **Expect**: NOT `422`; the request proceeds (observed `409 listora_duplicate_detected` because the QA draft already existed). `wb_listora_antispam_should_check` can force checks on.

### 5. Captcha, enabled
- **Action**: set `captcha_provider` to `recaptcha_v3` with a site and secret key in `wb_listora_settings` (Listora > Settings > Security), then from a real HTTP session (cookie + `X-WP-Nonce`; WP-CLI and application-password requests bypass) `POST /submit` with no token, then with a bogus token.
- **Expect**: no token `400 listora_captcha_missing` "CAPTCHA verification is required. Please try again."; bogus token `403 listora_captcha_failed` "CAPTCHA verification failed. Please try again." (needs outbound access to the provider). Captcha only counts as enabled when BOTH keys are set. Restore the provider to `none`.

### 6. Captcha bypass contexts
- **Expect**: `Captcha::verify()` returns true under WP-CLI, cron and application-password requests, and when `wb_listora_captcha_bypass` returns true. `wp eval '\WBListora\Captcha::verify("")'` returns `true` even with the provider on (observed).
- **There is no `should_skip_captcha()` helper**; older text naming it was wrong.

### 7. Contact form layers
- **Action**: see `customer/16-listing-contact-form.md` steps 5, 7, 8.
- **Expect**: honeypot `hp` returns `200 {sent:true}` with no lead; "casino" gives `422 listora_spam_keyword`; 4 URLs `422 listora_spam_urls`; the 4th message in an hour `429 listora_rate_limit` with `Retry-After: 3600`.

### 8. Rate limiting: scope and header
- **Action**: from one IP, four contact-form sends to one listing; then one to a different listing.
- **Expect**: the 4th is `429`; the other listing is unaffected (counters are per listing). Every Listora 429 that says when to retry carries a `Retry-After` header (`includes/class-rate-limiter.php:446`). `POST /submit/check-duplicate` needs a login and has no counter of its own (the old 31-requests step was wrong).
- **Reset** (QA): delete `listora_rl_*` and `wb_listora_contact_*` transients you created.

### 9. Reviews and claims: keyword, URL and Akismet are NOT applied (documented, not true)
- **Action**: as a member, create a review whose body contains "casino viagra" and 5 URLs.
- **Expect today**: `201 "Review submitted and pending approval."` (observed): moderation, not spam filtering, is the only guard. `docs/website/features/spam-protection.md` promises keyword, URL-density and Akismet checks on reviews and claims; this step must flip to `422 listora_spam_keyword` when that is implemented. Delete the QA review row afterwards.

### 10. Logged-in bypass is NOT a thing
- **Action**: as the admin, `POST /submit` repeatedly.
- **Expect**: still counted against `submission` (30/h per user). Only the `wb_listora_rate_limit_bypass` filter skips a limit. Do not assert "no 429 for admins" (older text did).

### 11. Pro audit log (Pro only, skip on Free)
- **Expect**: a spam rejection should leave an audit row; not verified in this refresh. Free has no audit log surface.

### 12. Every 429 carries Retry-After (bc-10393803241)
- **Action**: drive each limiter past its cap from one IP: contact form (`POST /listings/{id}/contact`, 4th in an hour), unsubscribe landing (repeat the link), app sign-in in `includes/rest/class-auth-controller.php` (repeat wrong credentials), space pending submission (`includes/rest/class-submission-controller.php`), and, with Pro, Needs create (`POST /needs`).
- **Expect**: each 429 has response header `Retry-After` equal to a positive integer and, where the body has `data.retry_after`, the same number. No 429 without the header.
- **Read-back**: `curl -si ... | grep -i '^retry-after'` per route; POSITIVE CONTROL: the request under the cap returns 200/201 with no `Retry-After`.
- fails_on: d165204f (Free), f93ce28 (Pro Needs). Not verified live.

### 13. Retry-After counts down; quota limits are not 429 (bc-10393853941)
- **Action**: on every limiter route from step 12 send a limited request, wait 60 s, send another limited request. Separately exhaust a quota limit (listings per member, monthly submissions) rather than a rate limit.
- **Expect**: the second `Retry-After` is smaller than the first by about 60 (never the full window again); a quota denial answers 409 or 403 with its own code and NO `Retry-After`, because waiting does not clear it.
- **Read-back**: record both header values in the run log; the `listora_rl_*` transient timeout matches the header +/- 2 s.
- fails_on: unknown. Not verified live.



## Pass criteria

1. Every row of the matrix behaves as written, including the two "NO" cells (so a future fix is noticed).
2. Each refusal creates nothing and sends nothing; each positive control succeeds.
3. Captcha is only enforced with both keys set and never blocks WP-CLI, cron or application passwords.

## Fail diagnostics

| Symptom | Likely cause | File:line |
|---|---|---|
| Honeypot accepted | param name or order | `includes/rest/class-submission-controller.php:534` |
| Spam text accepted on submit | pipeline skipped | `includes/class-anti-spam.php:35`, call site `includes/rest/class-submission-controller.php:561` |
| Keyword list wrong | defaults | `includes/class-anti-spam.php:105` |
| URL cap wrong | default 3 | `includes/class-anti-spam.php:165` |
| Captcha never enforced | keys missing or bypass | `includes/class-captcha.php:33`, `:116` |
| No 429 / no Retry-After | limiter | `includes/class-rate-limiter.php:209`, `:446` |
| Contact honeypot stores a lead | order | `includes/class-contact-form.php:189` |
| Reviews not filtered | no Anti_Spam call | `includes/rest/class-reviews-controller.php:455` |

## Not verified

- Akismet (not active on the QA site), the Pro audit log, and the 31st-request style cap on `public_read` (120 per minute per IP for logged-out reads, source only).
- Admin submit 30/h and IP submit 10/h caps were read from source, not exhausted.
