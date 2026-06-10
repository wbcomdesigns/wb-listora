## Submission & Moderation

Access submission settings at **Listora > Settings > Submissions**.

![Settings Submission - admin UI screenshot (1.0.5)](../images/settings-submission.png)

### Frontend Submissions

Toggle whether users can submit listings from the frontend. When disabled, only admins can create listings.

### Require Login

When enabled, users must be logged in to submit listings. When disabled, a registration form is shown inline.

### Submission Form Style (since 1.2.0)

Choose how the submission form presents to users:

- **Step-by-step wizard** (default) - one step per screen with a progress indicator. Best for most directories: keeps the form from looking overwhelming, and the draft-reminder email links users back to the exact step where they left off.
- **Single page form** - all fields on one scrollable page. Useful when your listing type has very few fields and the multi-step wrapper would feel excessive.

This setting applies site-wide. If you have placed the Listing Submission block on a page and want that specific instance to use a different style, set the **Layout mode** attribute in the block editor to override the global setting.

Developers can also override the resolved value programmatically:

```php
add_filter( 'wb_listora_submission_layout_mode', function ( $mode ) {
    return 'single_form'; // or 'wizard'
} );
```

### Moderation Mode

- **Auto-publish:** Submitted listings are published immediately
- **Manual review:** Listings are saved as "Pending" and require admin approval
- **Trusted users:** Auto-publish for users with 3+ approved listings, manual for others

### Allowed Listing Types

Select which listing types accept frontend submissions. Unchecked types can only be created by admins.

### Image Settings

- **Maximum gallery images:** Limit the number of images per listing (default: 10)
- **Maximum file size:** Per-image upload limit in MB
- **Allowed formats:** JPG, PNG, WebP

### Listing Expiration

- **Days until expiration:** Number of days before a listing expires (0 = never)
- **Expiration warning:** Days before expiration to send a warning email
- **Auto-renewal:** Allow listing owners to renew from their dashboard

### Edit Approval

When enabled, edits to published listings require re-approval before going live.

## Related

- [Installation & Activation](../getting-started/installation.md)
- [Setup Wizard](../getting-started/setup-wizard.md)
- [General Settings](../settings/general-settings.md)
- [Frontend Submission](../features/frontend-submission.md)
