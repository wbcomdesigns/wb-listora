# Template Overrides

Override any WB Listora frontend or email template from your theme - WooCommerce-style. Drop a file at `{theme}/wb-listora/{template-name}.php` and your version wins. Works for block templates (cards, detail, dashboard), email templates (15 Free + 13 Pro), and the full-width page-template the plugin registers for its shortcode pages.

## What it is

Every template the plugin renders goes through `wb_listora_locate_template()` - defined in `wb-listora/includes/class-template-helpers.php:23`. The locator walks three paths in order:

1. **Child theme:** `{stylesheet}/wb-listora/{name}.php`
2. **Parent theme:** `{template}/wb-listora/{name}.php`
3. **Plugin default:** `wb-listora/templates/{name}.php`

The first hit wins. This is the same pattern WooCommerce, Easy Digital Downloads, and BuddyPress use - battle-tested, expected behaviour for WP devs.

Hookable: `apply_filters( 'wb_listora_locate_template', $template, $template_name, $template_path )` lets you override the lookup result entirely (e.g. serve a tenant-specific template in a multisite).

Templates that respect the locator:

### Block templates (frontend listing UI)

```
templates/blocks/listing-card/card.php
templates/blocks/listing-card/card-image.php
templates/blocks/listing-card/card-meta.php
templates/blocks/listing-card/card-actions.php
templates/blocks/listing-detail/hero.php
templates/blocks/listing-detail/sidebar.php
templates/blocks/listing-detail/tabs.php
templates/blocks/listing-detail/review-card.php
templates/blocks/listing-grid/grid.php
templates/blocks/listing-grid/toolbar.php
templates/blocks/listing-grid/pagination.php
templates/blocks/listing-search/search.php
templates/blocks/listing-search/search-bar.php
templates/blocks/listing-search/filters.php
templates/blocks/listing-map/map.php
templates/blocks/listing-submission/submission.php
templates/blocks/listing-submission/step-type.php
templates/blocks/listing-submission/step-basic.php
templates/blocks/listing-submission/step-details.php
templates/blocks/listing-submission/step-media.php
templates/blocks/listing-submission/step-preview.php
templates/blocks/listing-submission/step-duplicate-review.php
templates/blocks/listing-submission/stepper.php
templates/blocks/listing-submission/navigation.php
templates/blocks/listing-reviews/reviews.php
templates/blocks/listing-reviews/review-card.php
templates/blocks/listing-categories/categories.php
templates/blocks/listing-categories/category-card.php
templates/blocks/listing-featured/featured.php
templates/blocks/listing-calendar/calendar.php
templates/blocks/user-dashboard/dashboard.php
templates/blocks/user-dashboard/tab-listings.php
templates/blocks/user-dashboard/tab-reviews.php
templates/blocks/user-dashboard/tab-favorites.php
templates/blocks/user-dashboard/tab-claims.php
templates/blocks/user-dashboard/tab-credits.php
templates/blocks/user-dashboard/tab-profile.php
```

### Single-listing + page templates

```
templates/single-listora_listing.php
templates/template-listora-full-width.php
```

### Email templates (15 Free)

```
templates/emails/parts/header.php
templates/emails/parts/footer.php
templates/emails/claim-approved.php
templates/emails/claim-rejected.php
templates/emails/claim-submitted.php
templates/emails/draft-reminder.php
templates/emails/listing-approved.php
templates/emails/listing-expired.php
templates/emails/listing-expiring-soon.php
templates/emails/listing-pending-admin.php
templates/emails/listing-rejected.php
templates/emails/listing-renewed.php
templates/emails/listing-submitted.php
templates/emails/listing-verify-email.php
templates/emails/review-helpful.php
templates/emails/review-received.php
templates/emails/review-reply.php
```

### Pro templates (13)

Pro templates live in `wb-listora-pro/templates/` but use the **same locator** - overrides go in the same `{theme}/wb-listora/` folder:

```
templates/blocks/comparison/comparison.php
templates/blocks/credit-purchase/credit-purchase.php
templates/blocks/needs-grid/needs-grid.php
templates/blocks/needs-grid/need-card.php
templates/blocks/need-detail/need-detail.php
templates/blocks/post-need/post-need.php
templates/dashboard/tab-needs.php
templates/single-listora_need.php
templates/emails/digest.php
templates/emails/lead-notification.php
templates/emails/listing-paused.php
templates/emails/listing-resumed.php
templates/emails/moderator-reassigned.php
templates/emails/need-*.php
templates/emails/response-*.php
templates/emails/saved-search-alert.php
```

## How you use it

### Override a single template

1. Pick the template you want to change - e.g. `wb-listora/templates/blocks/listing-card/card.php`.
2. Create the matching path in your theme: `wp-content/themes/{your-theme}/wb-listora/blocks/listing-card/card.php`.
3. Copy the plugin file's contents as your starting point.
4. Edit. Save. The next request renders your version.

### Variables available

Every template starts with a docblock listing the variables passed in (`$id`, `$title`, `$listing_url`, `$colors`, `$variant`, etc.). The plugin extracts `$view_data` into template scope, so:

```php
<?php
defined( 'ABSPATH' ) || exit;
/**
* @var int $id Listing ID.
* @var string $title Sanitized title.
* @var string $excerpt Trimmed excerpt.
* @var array $type ['slug','name','color','icon','schema'].
* @var bool $show_rating Block attribute.
* …
*/
?>
<article class="listora-card listora-card--<?php echo esc_attr( $layout ); ?> listora-type--<?php echo esc_attr( $type['slug'] ); ?>">
<!-- your override -->
</article>
```

Every template ships with the variable docblock at the top - the contract is explicit, not implied.

### Best practices

- **Don't fork the whole template if you only need to add one line.** Use the action hooks fired around the template instead (`wb_listora_before_card`, `wb_listora_after_card_actions`, etc.). See [Hooks Reference](hooks-reference.md).
- **Keep override files small.** If your override is 90% identical to the plugin's template, the override is a maintenance burden - fork only the specific section that differs.
- **Track plugin updates.** When the plugin updates a template significantly, your override might diverge. Diff your version vs the new plugin version on each release.
- **Email templates: keep variable docblock.** Lots of plugins forget to update email-template variables across versions; the docblock at the top of each email template is the contract - don't strip it.

## Settings & options

| Filter / hook | Purpose |
|---|---|
| `wb_listora_locate_template` (filter) | Override the located template path. |
| `wb_listora_template_args` (filter) | Modify the `$args` array passed into the template (before `extract()`). |
| `wb_listora_get_template` (action) | Fires after a template is loaded; useful for instrumentation. |

For block-level extension without forking the template, see the per-block action hooks documented in [Hooks Reference](hooks-reference.md).

## Related

- [Hooks Reference (Actions & Filters)](hooks-reference.md) - extend without forking.
- [Email Templates](../features/email-templates.md) - the email-specific override pattern + variables.
- [Gutenberg Blocks Overview](../features/blocks-overview.md) - which templates each block uses.
