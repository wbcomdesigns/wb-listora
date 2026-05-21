---
journey: spam-protection-layers
plugin: wb-listora
priority: high
roles: [anonymous, administrator]
covers: [honeypot, rate-limit-per-ip, captcha-bypass-cli, akismet-integration, banned-words, url-density-cap]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Free active (spam protection is a Free feature)"
  - "WB Listora Pro is OPTIONAL — step 9 (audit-log recording) is a Pro-only enhancement; skip that step in Free-only mode"
estimated_runtime_minutes: 8
covers_doc: features/spam-protection
---

# 6-layer spam-protection defence — honeypot + rate limit + CAPTCHA + Akismet + blacklist + URL density

Functional sentinel for the layered spam-protection model documented in `docs/website/features/spam-protection.md`. Each layer is INDEPENDENT — spam must defeat ALL active layers to land. The journey hits each in isolation.

## Setup

- Site: `$SITE_URL`
- Test endpoints: `POST /listora/v1/submit`, `POST /listora/v1/listings/{id}/reviews`, `POST /listora/v1/listings/{id}/contact-form`

## Steps

### 1. Honeypot — filled hidden field is REJECTED
- **Action**: POST to `/listora/v1/submit` with all valid fields PLUS `listora_hp_field: "spam"`.
- **Expect**: HTTP 400/403 with `code: listora_honeypot_filled` or similar. No DB row created.

### 2. Rate limit per IP — exceed the per-hour cap → 429
- **Action**: from a single IP, POST to `/listora/v1/submission/check-duplicate` 31 times in quick succession (default cap = 30/hr per the rate-limit doc).
- **Expect**: requests 1-30 succeed (200), request 31 returns HTTP 429 with `code: listora_rate_limited` and a `Retry-After` header.

### 3. Rate limit — different endpoint different counter
- **Action**: after hitting cap on `/check-duplicate`, immediately POST to `/listings/<ID>/reviews` (different counter).
- **Expect**: succeeds — counters are per-endpoint per-IP, not global.

### 4. CAPTCHA — when enabled, missing token is REJECTED
- **Action**:
  1. Set `wp option update wb_listora_captcha_provider recaptcha_v3`.
  2. Set fake recaptcha keys.
  3. POST to `/submit` with NO recaptcha token.
- **Expect**: HTTP 400/403 with `code: listora_captcha_failed`.
- **Then reset**: `wp option update wb_listora_captcha_provider none`.

### 5. CAPTCHA bypass — WP-CLI context skips
- **Action**: with CAPTCHA still enabled, run `wp eval "echo \WBListora\Captcha::should_skip_captcha() ? '1' : '0';"`
- **Expect**: `1`. The `listora_should_skip_captcha()` helper returns true under WP_CLI/DOING_CRON contexts.

### 6. Akismet — review with spam-bait text is rejected (Akismet ham/spam decisions)
- **Action** (requires Akismet active + valid API key):
  ```
  curl -X POST "$SITE_URL/wp-json/listora/v1/listings/<ID>/reviews" \
    -H "Content-Type: application/json" \
    -H "X-WP-Nonce: $NONCE" \
    -d '{"rating":5,"text":"viagra cialis cheap meds spammy spammy"}'
  ```
- **Expect**: HTTP 400/403 with `code: listora_spam_akismet` (or the review is queued as `pending` for moderation, depending on Akismet's confidence — both are correct outcomes; the bug would be a clean `publish`).

### 7. Keyword blacklist — banned word in submission triggers rejection
- **Action**:
  1. `wp option update wb_listora_blacklist_words "casino, viagra"`.
  2. POST a review with text "Visit my casino site!"
- **Expect**: HTTP 400 with `code: listora_blacklisted_word`.
- **Then reset blacklist option.**

### 8. URL density cap — review with too many URLs is rejected
- **Action**: POST a review with text containing 5 URLs (default cap = 2 per review).
- **Expect**: HTTP 400 with `code: listora_too_many_urls`.

### 9. Rejection logged to Pro Audit Log (Pro-only step — SKIP in Free-only mode)
- **Action** (Pro active + audit_log feature ON only):
  ```
  wp eval "global \$wpdb; echo \$wpdb->get_var(\"SELECT COUNT(*) FROM {\$wpdb->prefix}listora_audit_log WHERE event LIKE 'spam_%' AND created_at > NOW() - INTERVAL 10 MINUTE\");"
  ```
- **Expect (Pro active)**: `>= 1` for each rejection above. Every spam-rejection event lands in audit log with the reason.
- **Expect (Free-only)**: this step is skipped — Free has no audit log. The rejections in steps 1-8 still fire correctly; they're just not persisted to an audit log surface. Free-only smoke verifies the rejection itself (HTTP 4xx + error code body), not the audit trail.

### 10. Authenticated REST bypass — logged-in user with cap bypasses rate limit
- **Action**: as admin (full caps), POST 50 times to `/submit` (with valid bodies).
- **Expect**: NO 429. Authenticated requests are gated by AUTH not by IP — the rate limiter's `wb_listora_should_skip_rate_limit` filter or equivalent returns true.

## Cleanup

- Reset captcha provider to `none`.
- Clear `wb_listora_blacklist_words` option.
- Truncate test rate-limit transients: `wp transient delete-all --path=$WP`.

## Notes

- Order of layer firing matters for performance: honeypot (cheap) → rate-limit (cheap) → CAPTCHA (network call) → Akismet (network call) → blacklist (cheap) → URL density (cheap). The expensive layers run last so spam usually gets rejected by a cheap check first.
- Adding a 7th layer requires updating `Anti_Spam::check()` AND this journey's step list.
- Pro's `Audit_Log` is the canonical place to investigate "why was this submission rejected" — every layer must record its rejection there for forensics.
