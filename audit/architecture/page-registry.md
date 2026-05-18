# Page Registry

**Status:** in progress (2026-05-08)
**Owner plugin:** Free (`wb-listora`)
**Pro touch points:** registers `compare` page key on `wb_listora_loaded`

## Why

Five different option keys (`wb_listora_directory_page_id`, `wb_listora_submission_page_id`, `wb_listora_dashboard_page_id`, `wb_listora_pro_compare_page_id`, `wb_listora_pro_credits_page`) are read from 12+ scattered call sites. No central API. Each consumer reinvents lookup + fallback. Several fall back to admin URLs (wrong for end users) or `home_url('/')` (lossy).

When the upcoming gateway purchase flow needs "the dashboard URL" (`?tab=credits`) for the Stripe `return_url`, hardcoding option-name + fallback would repeat the bug. We need a single resolver.

Plus: "Dashboard" is a slug claimed by many plugins. Without a robust mapping admins can override, slug collisions silently rename our page (`my-dashboard-2`) and orphan the original.

## Public API

```php
// Resolve a page by stable key, returning a frontend URL with optional query args.
wb_listora_get_page_url( 'dashboard', [ 'tab' => 'credits' ] );
// → http://site.local/my-dashboard/?tab=credits

// Resolve just the page ID (translation-aware via filter).
wb_listora_get_page_id( 'directory' );
// → 6

// Inspect every registered page for admin / debug surfaces.
wb_listora_get_registered_pages();
// → [ 'directory' => […], 'submission' => […], 'dashboard' => […], 'compare' => […] ]
```

Filters for theme / plugin / translation overrides:

```php
apply_filters( 'wb_listora_page_id',  $id,  $key, $context );  // per-language remap
apply_filters( 'wb_listora_page_url', $url, $key, $args, $id ); // post-resolution URL override
```

## Architecture

### `WBListora\Core\Page_Registry` (new class, Free)

```php
namespace WBListora\Core;

final class Page_Registry {
    public static function register( string $key, array $config ): void;
    public static function get_id( string $key ): int;
    public static function get_url( string $key, array $args = [] ): string;
    public static function all(): array;
    public static function ensure_pages(): void;          // activator-time creation
    public static function status_for( string $key ): string;  // 'linked' | 'missing' | 'orphan'
    public static function find_orphan( string $key ): int;    // re-detect by block content
}
```

### Registration shape

```php
Page_Registry::register( 'dashboard', [
    'default_slug'  => 'my-dashboard',
    'default_title' => 'My Dashboard',
    'default_block' => 'listora/user-dashboard',          // for orphan detection
    'default_content' => '<!-- wp:listora/user-dashboard /-->',
    'option_key'    => 'wb_listora_dashboard_page_id',    // legacy storage path (read+write)
    'owner'         => 'free',
    'role'          => 'frontend',
    'description'   => __( 'User dashboard with listings, reviews, favorites, credits, and profile tabs.', 'wb-listora' ),
] );
```

### Free registers 3 keys on `init`@5

| Key | Default slug | Block | Option |
|---|---|---|---|
| `directory` | `listings` | `listora/listing-grid` | `wb_listora_directory_page_id` |
| `submission` | `add-listing` | `listora/listing-submission` | `wb_listora_submission_page_id` |
| `dashboard` | `my-dashboard` | `listora/user-dashboard` | `wb_listora_dashboard_page_id` |

### Pro registers 1 key on `wb_listora_loaded`

| Key | Default slug | Block | Option |
|---|---|---|---|
| `compare` | `compare-listings` | `listora-pro/comparison` | `wb_listora_pro_compare_page_id` |

(`credits` page is intentionally NOT registered — the dashboard's Credits tab IS the credits surface.)

## Settings → Pages tab

Adds `pages` to the `general` group in `Settings_Page::get_nav_groups()`. Tab content shows a table:

| Page | Status | Mapped to | Actions |
|---|---|---|---|
| Directory | ✅ Linked | `Directory` (ID 6) | [Change page ▾] [Reset to default] |
| Add Listing | ✅ Linked | `Add Listing` (ID 7) | [Change page ▾] [Reset to default] |
| My Dashboard | ✅ Linked | `My Dashboard` (ID 8) | [Change page ▾] [Reset to default] |
| Compare Listings | 🔍 Orphan detected | (unmapped, but ID 180 contains `listora-pro/comparison`) | [Use detected page] [Create new] |

`[Change page ▾]` is a dropdown of all `post_type=page` posts. Selecting one stores its ID in the registered `option_key`. Validation: warn (don't block) if the chosen page doesn't contain the expected block.

`[Reset to default]` — recreates the page with the canonical slug + title + content if missing; or if already exists at default slug, just relinks.

## Hybrid auto-creation

On Pro/Free activation:
1. Activator iterates all registered pages (Free's 3 + Pro's 1 once Pro is active).
2. For each: if mapped page is valid → no-op; else if orphan with matching block → relink; else create with default slug + title + content.
3. Same as today — silent, idempotent.

After activation:
- Set transient `wb_listora_pages_review_pending = 1` (24h TTL).
- On next admin page load, render dismissible notice:
  > **Listora is ready.** We've created 4 pages — Directory, Add Listing, My Dashboard, Compare Listings. [Review on Settings → Pages] [Dismiss]
- Dismiss writes `wb_listora_pages_reviewed = 1` user meta so the same admin doesn't see it again.

## Polylang / WPML auto-map

Built-in detection (no separate plugin compatibility layer):

```php
// In Page_Registry::get_id():
$id = (int) get_option( $config['option_key'], 0 );

// Polylang
if ( function_exists( 'pll_get_post' ) ) {
    $translated = pll_get_post( $id );
    if ( $translated ) {
        $id = (int) $translated;
    }
}

// WPML
if ( function_exists( 'apply_filters' ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
    $translated = apply_filters( 'wpml_object_id', $id, 'page', true );
    if ( $translated ) {
        $id = (int) $translated;
    }
}

// Public override hook
$id = (int) apply_filters( 'wb_listora_page_id', $id, $key, $context );
```

Effect: when site is Polylang/WPML active and current request is a non-default language, the registry returns the translated page's ID automatically. Themes/menus get the right URL with no extra wiring.

## Migration path (zero breakage)

The registry **reads from existing option keys** — no DB migration. Existing code paths keep working until refactored. Refactor plan (separate task list):

| Site | Current call | After registry |
|---|---|---|
| `class-template-helpers.php:228` | `wb_listora_get_setting('dashboard_page_id', 0)` | `wb_listora_get_page_id('dashboard')` |
| `class-pricing-plans.php:321-322` | `get_option('wb_listora_pro_credits_page', 0)` + fallback to admin URL | `wb_listora_get_page_url('dashboard', ['tab' => 'credits'])` |
| `class-comparison.php:612` | `get_option('wb_listora_pro_compare_page_id', 0)` | `wb_listora_get_page_id('compare')` |
| `class-listing-limits.php:565` | `wb_listora_get_credits_purchase_url()` | Same function, internally now uses `wb_listora_get_page_url('dashboard', ['tab' => 'credits'])` |
| `class-notification-digest.php:321` | `admin_url('admin.php?page=listora')` (wrong — sends users to admin) | `wb_listora_get_page_url('dashboard')` |

12 sites total. Refactor in two passes — first 5 high-value (the ones that emit URLs to end users), then the rest.

## Test plan

- [ ] Fresh activation creates 3 pages (Free) + 1 page (Pro) idempotently
- [ ] Re-activation does not duplicate
- [ ] Trash a registered page → registry status returns `missing`; admin notice surfaces; "Reset to default" recreates
- [ ] Manually create a page with `listora/user-dashboard` block before activation → registry detects orphan + offers to use it
- [ ] Polylang active with EN + DE: registry returns DE page ID for DE request
- [ ] WPML active with same setup: same behavior
- [ ] `wb_listora_page_id` filter override returns correct ID
- [ ] Settings → Pages tab loads, dropdown lists all WP pages, save persists option
- [ ] Dismissible notice appears once after activation, doesn't return after dismiss

## Out of scope (deferred)

- Multisite network-wide page mapping (each subsite's options are independent — fine for now)
- Per-language page CREATION (translation plugins typically duplicate manually; we just RESOLVE)
- A "Disable this page" state — every registered page is mandatory; admins manage via WP trash if needed

## Compatibility

- Existing third-party code reading `wb_listora_*_page_id` options directly: keeps working (registry doesn't change option storage).
- Existing `wb_listora_get_credits_purchase_url()` function: keeps signature, internally uses registry.
- New code: must use registry.
