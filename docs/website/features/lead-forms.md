# Lead Forms

> **Availability:** Free since 2.0.0. Pro adds lead counts to Analytics and keeps a few old hooks working for now.

Lead capture is part of free Listora. Since 2.0.0 there is one contact form on every listing, and every message sent through it is saved as a lead. Everything about the form, where owners and site owners read leads, retention and the REST routes is on the [Contact Form](contact-form.md) page.

Pro used to replace the Free form with its own Lead Form. It no longer does. Pro shows no second form, and the **Lead Capture Form** switch under **Listora > Settings > Features** is gone. The one switch left is **Contact Owner Form**.

## What Pro adds

- **Analytics** counts leads per listing and for the whole directory, next to views and clicks. Leads show in the listing owner's analytics and in the site report. See [Analytics](analytics.md).
- **Your own code** can react to a lead through Free's `wb_listora_after_contact_form_submit` action. Pro listens to the same action.

## Deprecated Pro route and hooks

If you built something on the old Pro lead form, it keeps working through 2.1.x and is removed in 2.2.0. Each use logs a deprecation notice when `WP_DEBUG` is on.

| Old | Use instead |
|---|---|
| `POST /listora/v1/listings/{id}/contact` | `POST /listora/v1/listings/{id}/contact-form` |
| `wb_listora_pro_before_submit_lead` filter | `wb_listora_before_contact_form_submit` |
| `wb_listora_pro_after_submit_lead` action | `wb_listora_after_contact_form_submit` |
| `wb_listora_pro_lead_per_listing_daily_cap` filter | `wb_listora_contact_form_per_listing_daily_cap` |
| `wb_listora_pro_email_lead_notification_subject` filter | `wb_listora_email_subject_lead_received` |
| `wb_listora_rest_prepare_lead` filter | No replacement |

The old route sends the message through Free's form code, so it is saved, emailed and counted once. It needs the old Pro nonce or a signed-in app, and it is not registered while **Contact Owner Form** is off.

## Related

- [Contact Form](contact-form.md)
- [Analytics](analytics.md)
- [Email Templates](email-templates.md)
