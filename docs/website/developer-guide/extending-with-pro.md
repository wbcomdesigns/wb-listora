## Extending with WB Listora Pro

WB Listora Pro adds premium features on top of the free plugin using the same hooks and filters.

### How Pro Extends Free

The Pro plugin hooks into the free plugin via `do_action` and `apply_filters`. It never replaces free functionality — only adds to it.

```
Free Plugin → fires hooks → Pro Plugin responds
```

### Pro Features

| Feature | Description |
|---------|-------------|
| **Google Maps** | Google Maps with custom styles, Places autocomplete |
| **Multi-Criteria Reviews** | Per-aspect ratings (food, service, etc.) |
| **Lead Forms** | Contact forms on listing pages |
| **Comparison** | Side-by-side listing comparison |
| **Analytics** | Listing view/click tracking |
| **Saved Searches** | Save searches with email alerts |
| **Verification Badges** | Verified business badges |
| **Advanced Search** | Saved search queries, daily digest |
| **Credit System** | Credit-based payment for listings |
| **Pricing Plans** | Subscription plans for listing submission |
| **Photo Reviews** | Attach photos to reviews |
| **White Label** | Remove Listora branding |
| **Notification Digest** | Batch email notifications |

### Installation

1. Purchase WB Listora Pro from [wblistora.com](https://wblistora.com)
2. Upload and activate the plugin
3. Go to **Listora > Settings > Pro** to enter your license key
4. Pro features activate automatically

### License Requirement

All Pro features are gated behind an active license. If the license expires:

- Pro features are deactivated
- Existing Pro data (criteria ratings, analytics) is preserved
- The free plugin continues to work normally

### Building Your Own Extensions

Use the same hooks Pro uses to build custom extensions:

```php
// Add custom fields to the review form
add_filter( 'wb_listora_review_criteria', 'my_custom_criteria', 10, 2 );

// Add content after listing fields
add_action( 'wb_listora_after_listing_fields', 'my_custom_section', 10, 2 );

// Modify search parameters
add_filter( 'wb_listora_search_args', 'my_search_modification' );

// React to listing events
add_action( 'wb_listora_listing_submitted', 'my_submission_handler', 10, 3 );
```
