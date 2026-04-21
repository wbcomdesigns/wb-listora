# Lead Forms

> **Pro feature** — requires [WB Listora Pro](../getting-started/activating-pro.md). Free sites do not include a contact-owner form on listing pages.

## What it does

Lead forms add a **Contact Owner** form to every listing detail page. When a visitor submits the form, an email is sent directly to the listing owner. No message data is stored in the database — only an aggregate lead count is tracked for analytics.

## Why you'd use it

- Listing owners receive inquiries directly to their inbox, increasing the value of having a listing.
- Guests and logged-in users can both send messages — no account required.
- GDPR-friendly: contact messages are emailed and not stored permanently as database records.
- Lead count appears in the listing owner's analytics dashboard, showing how many contact attempts their listing generated.

## How to use it

### For site owners (admin steps)

Lead forms are enabled automatically with WB Listora Pro. They appear on all listing detail pages by default.

**What the owner receives:**

An email notification containing:
- Sender's name and email address.
- Sender's phone number (optional field).
- Message text.
- A link back to the listing.

The email is sent to the listing author's WordPress user email address.

**Email template:** The lead notification email uses the template at `templates/emails/lead-notification.php` inside the Pro plugin. Themes can override it by placing a file at `{theme}/wb-listora/emails/lead-notification.php`.

**Spam protection:** The form includes a honeypot field (`hp`) to filter automated bot submissions. Rate limiting applies at the REST level — repeated submissions from the same IP within a short window are rejected.

### For end users (visitor/user-facing)

1. Navigate to a listing detail page.
2. Find the **Contact Owner** form in the listing's contact tab or sidebar.
3. Fill in:
   - **Name** (required)
   - **Email address** (required)
   - **Phone number** (optional)
   - **Message** (required)
4. Click **Send Message**.
5. A success confirmation appears. The listing owner receives the email immediately.

## Tips

- Remind listing owners to check their email spam folder for lead notifications — depending on their email provider, automated WordPress emails may be filtered.
- Configure WordPress to send email via an SMTP service (Mailgun, SendGrid, Postmark) to improve deliverability. Use a plugin like WP Mail SMTP.
- Lead counts appear in the listing owner's **Analytics** tab. Point owners there to show them how many inquiries their listing is generating.
- To disable lead forms on specific listing types, use the `wb_listora_after_listing_fields` action priority — the form renders at priority 10, so hooking at a lower priority with `return false` is not sufficient. Instead, use a custom check inside a child class or a conditional filter on the form output.

> **TODO:** Confirm whether lead forms can be disabled per listing type from the admin settings, or only via code.

## Common issues

| Symptom | Fix |
|---------|-----|
| Contact form not visible | Confirm WB Listora Pro is active and the license is valid |
| Owner not receiving emails | Check WordPress mail configuration; test with WP Mail Check |
| "Too many requests" error | Rate limiting triggered — the visitor has submitted too many messages in a short time |
| Form submits but no email arrives | The listing's author may have no email address set in **Users → Edit User** |

## Related features

- [Analytics](analytics.md)
- [Reviews System](reviews-system.md)
- [Digest Notifications](digest-notifications.md)
