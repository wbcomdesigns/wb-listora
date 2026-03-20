# 01 — Plugin Foundation

## Scope

| | Free | Pro |
|---|---|---|
| Plugin bootstrap | Yes | Yes (separate plugin, hooks into free) |
| Composer PSR-4 autoloader | Yes | Yes |
| Activation/deactivation/uninstall | Yes | Yes |
| Asset management | Yes | Yes |
| Pro detection helper | Yes | — |

---

## Overview

The foundation layer handles plugin lifecycle, autoloading, dependency management, and the hook system that Pro extends.

---

## File Structure

```
wb-listora/
├── wb-listora.php                    # Main plugin file (bootstrap)
├── uninstall.php                     # Clean removal
├── composer.json                     # PSR-4 autoload config
├── package.json                      # Block build dependencies
├── webpack.config.js                 # Block build config (if custom)
├── includes/
│   ├── class-plugin.php              # Main orchestrator (singleton)
│   ├── class-activator.php           # Runs on activation
│   ├── class-deactivator.php         # Runs on deactivation
│   └── class-assets.php              # Script/style registration
```

---

## Plugin Bootstrap (`wb-listora.php`)

### Header
```
Plugin Name: WB Listora
Plugin URI: https://wblistora.com
Description: The complete WordPress directory plugin. Create any type of listing directory — business, restaurant, hotel, real estate, jobs, events, and more.
Version: 1.0.0
Requires at least: 6.4
Requires PHP: 7.4
Author: WBCom
Author URI: https://wblistora.com
License: GPL v2 or later
Text Domain: wb-listora
Domain Path: /languages
```

### Bootstrap Flow
1. Check PHP version (7.4+) — show admin notice if incompatible
2. Check WP version (6.4+) — show admin notice if incompatible
3. Load Composer autoloader (`vendor/autoload.php`)
4. Define constants (see "Constants Naming" below)
5. Register activation/deactivation hooks
6. Initialize `Plugin::instance()` on `plugins_loaded` (priority 10)

### Constants Naming
All constants use `WB_LISTORA_` prefix:
```php
WB_LISTORA_VERSION
WB_LISTORA_PLUGIN_FILE
WB_LISTORA_PLUGIN_DIR
WB_LISTORA_PLUGIN_URL
WB_LISTORA_PLUGIN_BASENAME
WB_LISTORA_DB_VERSION        // Database schema version
```

---

## Main Plugin Class (`class-plugin.php`)

### Responsibilities
- Singleton instance
- Load text domain
- Initialize all subsystems in correct order
- Provide `wb_listora_is_pro_active()` check

### Initialization Order
```
1. Load text domain
2. Register CPT + Taxonomies (init, priority 5)
3. Register Capabilities (init, priority 5)
4. Initialize Listing Type Registry (init, priority 10)
5. Initialize Field Registry (init, priority 10)
6. Initialize Meta Handler (init, priority 10)
7. Register REST API controllers (rest_api_init)
8. Register Blocks (init, priority 20)
9. Initialize Search Indexer (hooks into save_post)
10. Initialize Admin (admin_init)
11. Initialize Assets (wp_enqueue_scripts, admin_enqueue_scripts)
12. Initialize Workflow (Status Manager, Cron, Notifications)
13. Initialize Schema/SEO (wp_head)
14. Fire wb_listora_loaded action (for Pro and extensions)
```

### Pro Detection
```php
function wb_listora_is_pro_active(): bool {
    return defined('WB_LISTORA_PRO_VERSION')
        && did_action('wb_listora_pro_loaded');
}
```

### Capabilities

| Capability | Administrator | Editor | Author (own) | Subscriber |
|-----------|:---:|:---:|:---:|:---:|
| `edit_listora_listing` | Yes | Yes | Yes (own only) | — |
| `edit_others_listora_listings` | Yes | Yes | — | — |
| `publish_listora_listings` | Yes | Yes | Configurable* | — |
| `delete_listora_listing` | Yes | Yes | Yes (own only) | — |
| `delete_others_listora_listings` | Yes | — | — | — |
| `read_private_listora_listings` | Yes | Yes | — | — |
| `manage_listora_settings` | Yes | — | — | — |
| `moderate_listora_reviews` | Yes | Yes | — | — |
| `manage_listora_claims` | Yes | — | — | — |
| `manage_listora_types` | Yes | — | — | — |
| `submit_listora_listing` | Yes | Yes | Yes | Yes** |

*Author can publish if Settings → Submissions → Moderation = "Auto-approve"
**Subscriber gets `submit_listora_listing` to allow frontend submission. The listing goes to "pending" status.

Note: When a user registers via the frontend submission form, they get the `subscriber` role with `submit_listora_listing` capability added.

---

## Activator (`class-activator.php`)

### On First Activation
1. Check environment (PHP, WP, required extensions: `json`, `mbstring`)
2. Create custom database tables via `dbDelta()`
3. Register default listing types (Business, Restaurant, Real Estate)
4. Create default categories for each type
5. Create default features/amenities
6. Set default plugin options (`wb_listora_settings`)
7. Set `wb_listora_db_version` option
8. Add custom capabilities to Administrator and Editor roles
9. Flush rewrite rules
10. Set `wb_listora_activation_redirect` transient (for setup wizard)

### On Subsequent Activation (Re-activation)
1. Run database migration if `wb_listora_db_version` differs
2. Re-add capabilities (in case roles were reset)
3. Flush rewrite rules

---

## Deactivator (`class-deactivator.php`)

### On Deactivation
1. Clear all scheduled cron events (`wp_clear_scheduled_hook`)
2. Flush rewrite rules
3. **Do NOT delete data** — data persists for re-activation

---

## Uninstall (`uninstall.php`)

### On Delete (User chooses "Delete" in Plugins)
Check `wb_listora_settings['delete_data_on_uninstall']` option:
- If true: drop custom tables, delete all `_listora_*` postmeta, delete options, remove CPT posts, remove capabilities
- If false: leave everything (default)

---

## Composer Autoloader

```json
{
  "name": "wbcom/wb-listora",
  "autoload": {
    "psr-4": {
      "WBListora\\": "includes/"
    }
  },
  "require": {
    "php": ">=7.4"
  }
}
```

### Namespace Map
```
WBListora\Plugin              → includes/class-plugin.php
WBListora\Activator           → includes/class-activator.php
WBListora\Core\Post_Types     → includes/core/class-post-types.php
WBListora\DB\Table_Geo        → includes/db/class-table-geo.php
WBListora\REST\Listings_Controller → includes/rest/class-rest-listings-controller.php
```

PSR-4 maps `WBListora\` to `includes/`. Class `WBListora\Core\Post_Types` lives at `includes/core/class-post-types.php` (WordPress file naming convention with PSR-4 namespace).

Note: WordPress uses `class-{name}.php` file naming. Configure Composer classmap or use a custom autoloader that converts `Post_Types` → `class-post-types.php`.

---

## Assets (`class-assets.php`)

### Frontend Assets (loaded only when blocks are rendered)
- `listora-shared` — Shared CSS variables and base styles
- Block-specific CSS/JS loaded via `block.json` `style`, `viewScriptModule`

### Admin Assets
- `listora-admin` — Admin CSS for settings pages, listing type editor
- `listora-admin-js` — jQuery UI Sortable for field ordering (admin only)

### Asset Loading Rules
1. **Never load on pages without directory content** — check for blocks or directory pages
2. **Frontend:** Each block registers its own assets via `block.json`
3. **Maps:** Leaflet loaded only when map block is present
4. **Admin:** Only on Listora admin pages

---

## Hook System (Pro Extension Points)

### Actions (Pro hooks into these)
```php
do_action('wb_listora_loaded');                           // Plugin fully initialized
do_action('wb_listora_register_field_types', $registry);  // Register custom field types
do_action('wb_listora_register_listing_types', $registry);// Register custom listing types
do_action('wb_listora_after_listing_save', $post_id, $data);
do_action('wb_listora_after_listing_fields', $post_id, $type);
do_action('wb_listora_listing_status_changed', $post_id, $new, $old);
do_action('wb_listora_review_submitted', $review_id, $listing_id);
do_action('wb_listora_claim_submitted', $claim_id, $listing_id);
do_action('wb_listora_claim_approved', $claim_id, $listing_id, $user_id);
do_action('wb_listora_favorite_added', $listing_id, $user_id);
do_action('wb_listora_before_search', $args);
do_action('wb_listora_after_search', $results, $args);
```

### Filters (Pro hooks into these)
```php
apply_filters('wb_listora_field_types', $types);          // Add field types
apply_filters('wb_listora_schema_data', $schema, $post_id);
apply_filters('wb_listora_search_args', $args);
apply_filters('wb_listora_search_results', $results, $args);
apply_filters('wb_listora_card_template', $template, $type);
apply_filters('wb_listora_detail_template', $template, $type);
apply_filters('wb_listora_submission_fields', $fields, $type);
apply_filters('wb_listora_map_provider', $provider);
apply_filters('wb_listora_map_markers', $markers, $listings);
apply_filters('wb_listora_review_criteria', $criteria, $type);
apply_filters('wb_listora_notification_recipients', $recipients, $event);
apply_filters('wb_listora_listing_statuses', $statuses);
apply_filters('wb_listora_rest_listing_response', $response, $post, $request);
apply_filters('wb_listora_geocoding_provider', $provider);
apply_filters('wb_listora_card_layout_options', $layouts);
apply_filters('wb_listora_detail_layout_options', $layouts);
apply_filters('wb_listora_payment_gateways', $gateways);
```

---

## Pro Plugin Bootstrap (`wb-listora-pro.php`)

### Header
```
Plugin Name: WB Listora Pro
Plugin URI: https://wblistora.com/pro
Description: Advanced features for WB Listora — Google Maps, payments, analytics, and more.
Version: 1.0.0
Requires at least: 6.4
Requires PHP: 7.4
Author: WBCom
Text Domain: wb-listora-pro
```

### Bootstrap Flow
1. Check if free plugin is active — show admin notice if not
2. Check free plugin version compatibility
3. Validate license
4. Load Pro autoloader
5. Initialize `Pro_Plugin::instance()` on `wb_listora_loaded` action
6. Hook into all relevant filters/actions

---

## Error Handling

### Admin Notices
- Missing PHP version → persistent dismissible notice
- Missing WP version → persistent dismissible notice
- Missing free plugin (Pro) → persistent non-dismissible notice
- Database table creation failed → persistent notice with details
- Activation redirect to setup wizard → transient-based redirect

### Logging
- Use `WP_DEBUG_LOG` when `WP_DEBUG` is on
- Custom `WBListora\Logger` class for structured logging
- Log levels: `error`, `warning`, `info`, `debug`
- Logs to: `wp-content/debug.log` (standard WP)

---

## Coding Standards

- WordPress Coding Standards (WPCS) for PHP
- WordPress JavaScript Coding Standards for JS
- ESLint + Prettier for editor JS
- PHPCS with `WordPress-Extra` ruleset
- Type hints where possible (PHP 7.4 compatible)
- All strings translatable via `__()`, `esc_html__()`, etc.
- All output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- All input sanitized (`sanitize_text_field`, `absint`, etc.)
- Nonces on all forms and AJAX handlers
- Capability checks on all privileged operations
