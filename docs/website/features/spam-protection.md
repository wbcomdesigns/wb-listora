# Spam Protection

> **Availability:** Free + Pro.

Listora checks public forms in layers: a hidden honeypot field, an optional CAPTCHA, rate limits, a keyword list, a link cap and Akismet. Each layer can reject a submission on its own. Everything except CAPTCHA works without any setup.

## What it is

Public forms attract spam. A bot that gets past one layer still has to get past the others. The layers are described below in the order a submission meets them.

### Where each layer applies

| Form | Honeypot | CAPTCHA | Rate limit | Keyword list, link cap, Akismet |
|---|---|---|---|---|
| Add listing | Yes | Yes | Yes | Yes |
| Write a review | No | Yes | Yes | Yes |
| Reply to a review | No | No | Yes | Yes |
| Claim a listing | No | No | Yes | Yes |
| Contact the owner | Yes | No | Yes | Yes |

Claims need a signed-in member and are rate limited, so they do not use CAPTCHA.

### 1. Honeypot

A hidden field is added to the Add listing form. People never see it, so they leave it empty. A bot that fills in every field is refused with the error `listora_spam` ("Submission blocked."). The contact form has its own hidden field and quietly accepts a bot's message without sending it, so the bot believes it worked.

There is nothing to turn on or tune.

### 2. CAPTCHA (off by default)

Choose a provider in **Listora > Settings > Submissions > CAPTCHA provider**:

- **reCAPTCHA v3** is invisible and scores each visit.
- **reCAPTCHA v2 (checkbox)** shows the "I'm not a robot" checkbox. It suits sites where v3 scoring blocks real people. It needs its own keys, not the v3 ones.
- **Cloudflare Turnstile** is the option that does not track visitors.

Paste the site key and secret key from the provider. CAPTCHA turns on only when a provider is chosen and both keys are filled in. It then protects the Add listing form and the review forms. There is no per-form switch.

A request with no CAPTCHA token is refused with `listora_captcha_missing`.

WP-CLI, WP-Cron and requests that use an application password skip CAPTCHA. Developers can change that with the `wb_listora_captcha_bypass` filter.

### 3. Rate limits

Every public write has a limit per member and per network address. See [Rate Limiting](rate-limiting.md).

### 4. Keyword list, link cap and Akismet

After the layers above, the text of a listing, review, review reply, claim or contact message goes through three checks, in this order. The first one that fails stops the submission.

- **Keyword list.** Listora ships a short built-in list of common spam terms (casino, pharmacy and adult-content words, and crypto pump scams). The title, description and author name are checked, case-insensitively, as plain substrings. A match is refused with `listora_spam_keyword`. There is no setting for this list; developers change it with the `wb_listora_antispam_keyword_blacklist` filter.
- **Link cap.** A listing, claim or contact message may contain up to 3 links, and a review or review reply up to 2. Links are counted by `http://` and `https://` in the text. Over the cap is refused with `listora_spam_urls`. Developers change the cap with the `wb_listora_antispam_max_urls` filter, which also receives the source (`review`, `review_reply`, `claim`, `contact_form` or `submission`). Return `0` to switch the cap off.
- **Akismet.** When the Akismet plugin is active, the text is sent to Akismet. A result of spam, or Akismet's most confident spam result, is refused with `listora_spam_akismet`. If Akismet cannot be reached, the submission is allowed. Nothing is needed beyond installing and activating Akismet.

Members who can edit other people's listings, such as administrators, editors and Listora moderators, skip these three checks.

## How you use it

1. **Check the honeypot, rate limits, keyword list, link cap and Akismet.** They are already working. For Akismet, activate the Akismet plugin and add its key.
2. **Turn on CAPTCHA** if spam still gets through: pick a provider and paste its keys.
3. **Review what gets through.** Spam that passes every layer lands in your usual review queue. See [Moderation Queue](moderation-queue.md).

Listora does not keep a list of rejected attempts, so there is nothing to review for refused submissions. The visitor sees the error message, and your server log shows the request.

## Settings and options

| Layer | Default | Where to change it |
|---|---|---|
| Honeypot | On | No setting |
| CAPTCHA | Off | Listora > Settings > Submissions > CAPTCHA provider |
| Rate limits | On | Filters only. See [Rate Limiting](rate-limiting.md) |
| Keyword list | Built-in list | Filter only |
| Link cap | 3 links, or 2 in reviews and replies | Filter only |
| Akismet | On when the Akismet plugin is active | Akismet's own settings |

Developer hooks:

- `wb_listora_antispam_should_check` (filter) - return `false` to skip the keyword, link and Akismet checks for a request. The second argument carries the submission, its source and an `is_bot` flag.
- `wb_listora_antispam_keyword_blacklist` (filter) - the list of blocked words.
- `wb_listora_antispam_max_urls` (filter) - the link cap, with the source as the second argument.
- `wb_listora_captcha_bypass` (filter) - return `true` to skip CAPTCHA for a request.

## Related

- [Submission & Moderation Settings](../settings/submission-settings.md) - where CAPTCHA is set.
- [Moderation Queue](moderation-queue.md) - what happens to submissions that pass every check.
- [Rate Limiting](rate-limiting.md) - the request limits that pair with these checks.
