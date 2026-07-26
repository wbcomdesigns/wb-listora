# Email Templates

> **Availability:** Free + Pro. Free ships 15 customer-facing templates; Pro adds **13 more** (digest, lead-notification, listing-paused, listing-resumed, moderator-reassigned, need-approved, need-match, need-pending-mod, need-rejected, need-response, response-accepted, response-rejected, saved-search-alert).

Every customer-facing email - listing approved, review received, helpful-vote milestone, draft reminder, claim accepted - is rendered from a themeable PHP template with a shared header/footer, a unified token palette, and a single notifications class that pipes data into the template. Themes override templates the WooCommerce way: copy the file into `{theme}/wb-listora/emails/` and edit.

![Email - listing-approved template rendered in a desktop mail client](../images/email-listing-approved.png)

## What it is

Plugins that hardcode emails ship inconsistent designs across notifications. Listora's email system is a small design system in its own right:

- **Shared partials** - `templates/emails/parts/header.php` and `parts/footer.php` are included by every template, so the wordmark, color palette, GDPR footer, and unsubscribe link all live in one place.
- **Tokenized palette** - `Notifications::get_palette()` resolves the colors once per send (primary, success, warning, danger, neutral, text, text_muted, bg_alt, border, white). Templates read `$colors['primary']`, never hardcoded hex.
- **Variant resolver** - each event picks a tone (`success` / `warning` / `danger` / `neutral`) via `Notifications::resolve_variant()`, applied to the header band and CTA button. Templates carry no conditional color logic.
- **Plain-text fallback** - `phpmailer_init` AltBody filter generates a text version so clients that prefer plain text still get a readable message.
- **GDPR-friendly footer** - marketing emails (`$is_marketing = true`) carry an `$unsubscribe_url`; transactional emails skip it.
- **Theme overrides** - every template runs through `wb_listora_locate_template()`; drop a file at `{theme}/wb-listora/emails/{name}.php` and it wins over the plugin's copy.
- **WPML / Polylang ready** - every string is wrapped in `__()` against the plugin text domain; `make-pot` extracts them.

The 15 Free templates (in `wb-listora/templates/emails/`):

`claim-approved`, `claim-rejected`, `claim-submitted`, `draft-reminder`, `listing-approved`, `listing-expired`, `listing-expiring-soon`, `listing-pending-admin`, `listing-rejected`, `listing-renewed`, `listing-submitted`, `listing-verify-email`, `review-helpful`, `review-received`, `review-reply`.

Pro adds (in `wb-listora-pro/templates/emails/`):

`digest`, `lead-notification`, `listing-paused`, `listing-resumed`, `moderator-reassigned`, `need-approved`, `need-match`, `need-pending-mod`, `need-rejected`, `need-response`, `response-accepted`, `response-rejected`, `saved-search-alert`.

## How you use it

### As a site owner - customize without code

For most customers the defaults are fine; if you need brand-specific tweaks:

1. **Override the primary color:** Listora → Settings → General → **Brand Color**. The email palette inherits this token, so every notification picks up your brand.
2. **Logo in emails:** drop a 240×60 logo at Listora → Settings → Notifications → **Email Logo URL**. Surfaces in `parts/header.php`.
3. **Footer text:** Settings → Notifications → **Email Footer Text** (HTML allowed) - supports GDPR/Imprint requirements.
4. **Send a test:** Settings → Notifications → click **Send test email** for any event.

### As a developer - theme override

Themes shipping a directory layout often want their own email skin:

1. Copy the template you want to change from the plugin (e.g. `wp-content/plugins/wb-listora/templates/emails/listing-approved.php`) to your theme: `wp-content/themes/{your-theme}/wb-listora/emails/listing-approved.php`.
2. Edit the copy. Variables passed in (documented at the top of every template):
- `$site_name`, `$site_url`, `$logo_url`, `$footer_text`
- Per-event: `$listing_title`, `$listing_url`, `$author_name`, `$dashboard_url`, …
- `$colors` (palette array), `$variant` (`success` / `warning` / `danger` / `neutral`)
- `$is_marketing`, `$unsubscribe_url` (GDPR fields)
3. Save. The next outbound email of that type uses your override.

### Filter hooks for non-template tweaks

- `wb_listora_email_subject` (filter) - modify any subject. Event-scoped variant: `wb_listora_email_subject_{event}`.
- `wb_listora_email_content` (filter) - modify the rendered HTML body. Event-scoped: `wb_listora_email_content_{event}`.
- `wb_listora_email_recipients` (filter) - add CC/BCC; e.g. send admin a copy of every approval.
- `wb_listora_email_headers` (filter) - modify `wp_mail()` headers (Reply-To, custom X-headers).
- `wb_listora_email_logo_url` / `wb_listora_email_footer_text` (filter) - programmatic overrides for the header logo + footer text.

## Settings & options

| Setting | Location | Default | Notes |
|---|---|---|---|
| Brand color | Settings → General → Brand Color | Plugin red | Drives the email palette via tokens |
| Email logo | Settings → Notifications → Email Logo URL | (empty) | Rendered in shared header partial |
| Footer text | Settings → Notifications → Email Footer Text | "&copy; {year} {site}" | HTML allowed |
| From name | Settings → Notifications → From Name | Site title | `wp_mail()` From header |
| Notification mode (Pro) | Settings → Notifications → Mode | Instant | Switch to "Daily digest" to batch (see [Digest Notifications](digest-notifications.md)) |
| Test send | Settings → Notifications | - | Per-event test button |

Template lookup order (Free + Pro share the same locator):
1. `{stylesheet}/wb-listora/emails/{name}.php`
2. `{template}/wb-listora/emails/{name}.php` (parent theme)
3. Plugin default

## Related

- [Digest Notifications (Pro)](digest-notifications.md) - batch transactional emails into a daily digest to reduce inbox noise.
- [Reviews & Ratings](reviews-system.md) - drives `review-received` / `review-reply` / `review-helpful` notifications.
- [Frontend Submission](frontend-submission.md) - drives `listing-submitted` / `listing-approved` / `listing-rejected`.
- [Draft Reminder (Free)](draft-reminder.md) - twicedaily cron-driven recovery email for incomplete drafts.
- [Developer Reference: Hooks](../developer-guide/hooks-reference.md) - full list of email-related filters.
