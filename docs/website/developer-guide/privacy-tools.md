# WordPress Privacy Tools (GDPR / Data Requests)

Since 1.2.0, WB Listora registers handlers for WordPress's built-in personal data tools. When a user submits a **Data Export** or **Data Erasure** request through **WP Admin → Tools → Export Personal Data** or **Erase Personal Data**, Listora includes its data automatically - no extra configuration needed.

## What data is included

### Free plugin (since 1.2.0)

| Data type | Export | Erase |
|---|---|---|
| Business claims filed by the user | Yes - claim ID, listing title, status, date | Yes - claim record deleted |
| Reviews written by the user | Yes - listing title, rating, review text, date | Yes - review deleted, listing's aggregate rating recomputed |
| Favorites saved by the user | Yes - listing title and URL | Yes - favorites removed |

### Pro plugin (since 1.2.0)

| Data type | Export | Erase |
|---|---|---|
| Audit log entries attributed to the user | Yes - action, target, timestamp, IP | Yes - audit entries erased |
| Lead form submissions (contact-form messages sent by the user) | Yes - listing name, message, date | Yes - lead records removed |

## How it works

The exporter and eraser follow the standard WordPress personal-data API (`wp_register_personal_data_exporter`, `wp_register_personal_data_eraser`). When WordPress processes a request it calls Listora's handlers page by page - each page returns a batch of records plus a `done` flag, so large data sets process across multiple requests without exhausting memory.

Erasure is destructive and irreversible. Ratings recompute automatically after reviews are erased so the listing's star average stays accurate.

## Running a data request

1. Go to **WP Admin → Tools → Export Personal Data** (or **Erase Personal Data**).
2. Enter the user's email address and click **Send Request**.
3. The user receives a confirmation email with a link to confirm the request.
4. Once confirmed, return to the same admin page and click **Download Export File** (or **Erase Personal Data** for erasure).

WordPress handles the full workflow including the confirmation step and the downloadable zip file. Listora data is included alongside any other plugin's data in the same export.

## Programmatic access

```php
// Adjust the batch size Listora uses per pagination page.
add_filter( 'wb_listora_privacy_erase_per_page', function ( $per_page ) {
    return 25; // default is 50
} );
```

## Related

- [Capabilities & Roles](capabilities.md) - who can initiate and process data requests
- [Audit Log (Pro)](../features/audit-log.md) - what gets erased from the audit log on erasure
