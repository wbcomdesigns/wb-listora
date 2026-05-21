# Extending with WB Listora Pro

WB Listora is **Free + Pro by design** — Pro never replaces Free, it consumes Free's documented surfaces (197 hooks + 55 REST endpoints + template overrides). The same surfaces are open to you for third-party extensions, themes, and custom integrations.

## How Pro extends Free

```
┌──────────────┐  do_action / apply_filters   ┌──────────────┐
│  WB Listora  │ ───────────────────────────► │  WB Listora  │
│   (Free)     │    + REST request shaping    │     Pro      │
│              │ ◄─────────────────────────── │              │
└──────────────┘     filter return values     └──────────────┘
```

Pro **only consumes documented surfaces** — no direct refs to Free's `\WBListora\Core\*` internals (enforced by architecture invariant INV-3 in `bin/architecture-checks.sh`). The current Free→Pro coupling is **29 documented pairs**, all listed in `audit/derived/cross-plugin-coupling.json`.

## The four extension surfaces

### 1. Action hooks (canonical extension point)

Every write operation fires a `wb_listora_after_{action}_{resource}` action. Pro's Audit Log, Outgoing Webhooks, BuddyPress integration, and Analytics features all hook these:

```php
// Pro's class-outgoing-webhooks.php:144 (Free fires, Pro listens)
add_action( 'wb_listora_after_create_listing', array( $this, 'on_listing_created' ), 50, 2 );

public function on_listing_created( int $post_id, WP_REST_Request $request ): void {
    $payload = $this->build_listing_payload( $post_id );
    $this->dispatch_event( 'listing_created', $payload );
}
```

See [Hooks Reference](hooks-reference.md) for the full list of 109 actions.

### 2. REST response filters

Every REST controller fires a `wb_listora_rest_prepare_{resource}` filter so Pro can inject Pro-only fields without forking the controller:

```php
// Pro's Quick_View::filter_quick_view_response
add_filter( 'wb_listora_rest_prepare_listing', array( $this, 'filter_quick_view_response' ), 10, 3 );

public function filter_quick_view_response( array $data, WP_Post $post, WP_REST_Request $request ): array {
    if ( $request->get_param( 'view' ) === 'quick-view' ) {
        $data['quick_view_fields'] = $this->get_quick_view_fields( $post );
    }
    return $data;
}
```

### 3. Before-write filters (write gates)

Every write fires a `wb_listora_before_{action}_{resource}` filter — return `WP_Error` to abort:

```php
add_filter( 'wb_listora_before_create_review', function ( $value, $listing_id, $args ) {
    if ( $this->is_suspected_spam( $args ) ) {
        return new WP_Error( 'spam_blocked', 'Submission blocked by anti-spam.' );
    }
    return $value;
}, 5, 3 );  // Priority 5 — runs before Free's own checks
```

### 4. Template overrides (theme-style)

Drop a file at `{theme}/wb-listora/{template-name}.php` to override any Listora template. See [Template Overrides](template-overrides.md) for the full file index.

## What every Pro feature plugs into

Drawn from `audit/derived/cross-plugin-coupling.json` (29 pairs as of 2026-05-20):

| Pro feature | Free hook it consumes | Why |
|---|---|---|
| **Audit Log** | `wb_listora_after_create_listing`, `_update_listing`, `_delete_listing`, `_create_review`, `_update_review`, `_delete_review`, `_submit_claim`, `_update_claim`, `_add_favorite`, `_remove_favorite` | Records every write with actor + before/after diff |
| **Outgoing Webhooks** | Same write-lifecycle surface + `wb_listora_listing_submitted`, `wb_listora_listing_status_changed`, `wb_listora_review_submitted`, `wb_listora_claim_approved` | Dispatches HMAC-signed payloads to external endpoints |
| **BuddyPress Integration** | `wb_listora_listing_submitted`, `wb_listora_review_submitted`, `wb_listora_claim_approved`, `wb_listora_member_profile_url` (filter) | Activity stream + notifications + profile-linked reviewer names |
| **Analytics** | `wb_listora_after_create_listing`, `wb_listora_listing_status_changed` | View/click tracking per listing |
| **Comparison** | `wb_listora_card_actions` (action), `wb_listora_after_listing_fields` (action) | Renders Compare-toggle on cards + detail page |
| **Quick View** | `wb_listora_card_actions` + `wb_listora_rest_prepare_listing` | Eye-icon button + REST response shape |
| **Verification Badges** | `wb_listora_is_verified` (filter) — Pro answers with feature-gate state | Toggle-aware badge visibility |
| **SEO Pages** | `wb_listora_rest_prepare_listing` + `init` rewrite rules | URL `/type-in-location/` pattern rendering |
| **Saved Searches** | `wb_listora_rest_api_init` (action) | Registers Pro's `/saved-searches` REST routes |
| **Credits / Pricing Plans** | `wb_listora_after_create_listing` + `wb_listora_listing_paused/resumed` | Credit-gated submission flow |
| **Pages auto-creation** | `wb_listora_register_pages` (action) — Pro registers Compare / Buy Credits / Needs pages | Single canonical activator-time page-registration surface |
| **Reset Settings** | `wb_listora_after_reset_settings` (action) + `wb_listora_reset_option_keys` (filter) | Purge Pro options when admin clicks "Reset all settings" |

## Pro features at a glance

WB Listora Pro adds these features on top of Free's foundation. Each is independently toggleable from Listora → Settings → Features.

| Feature | What it does | Doc |
|---|---|---|
| Google Maps | Replaces OSM with Google Maps + Places autocomplete + marker clustering | [Google Maps](../features/google-maps.md) |
| Multi-Criteria Reviews | Per-aspect star ratings (Food / Service / Ambiance for restaurants etc.) | [Multi-Criteria Reviews](../features/multi-criteria-reviews.md) |
| Photo Reviews | Image uploads attached to reviews | [Photo Reviews](../features/photo-reviews.md) |
| Lead Forms | Contact-owner form on every listing | [Lead Forms](../features/lead-forms.md) |
| Comparison | Side-by-side listing comparison page | [Compare Listings](../features/compare-listings.md) |
| Quick View | In-page modal preview from any card | [Quick View Modal](../features/quick-view.md) |
| Analytics | Per-listing view + click tracking | [Analytics](../features/analytics.md) |
| Saved Searches | Save searches + daily email digest | [Saved Searches](../features/saved-searches.md) |
| Verification Badges | Verified-business badges | [Verification Badges](../features/verification-badges.md) |
| Advanced Search | Multi-facet filter UI | [Advanced Search](../features/advanced-search.md) |
| Credit System | Credit-based payment flow | [Credits & Pricing Plans](../features/credits-and-plans.md) |
| Pricing Plans | Subscription tiers for listing submission | [Pricing Plans](../features/pricing-plans.md) |
| Coupons | Discount codes for listing plans | [Coupons](../features/coupons.md) |
| White Label | Remove Listora branding from admin | [White Label](../features/white-label.md) |
| Coming Soon | Hide directory while setting up | [Coming Soon & Private Mode](../features/coming-soon.md) |
| Notification Digest | Batch transactional emails into a daily summary | [Digest Notifications](../features/digest-notifications.md) |
| Programmatic SEO Pages | Auto-generate `/type-in-location/` landing pages | [SEO Pages](../features/seo-pages.md) |
| Outgoing Webhooks | HMAC-signed event webhooks for integrations | [Outgoing Webhooks](../features/outgoing-webhooks.md) |
| BuddyPress Integration | Activity + notifications + profile sub-nav | [BuddyPress Integration](../features/buddypress-integration.md) |
| Audit Log | Tamper-evident record of every write | [Audit Log](../features/audit-log.md) |
| Needs Marketplace | Reverse-listing flow (buyers post, businesses respond) | [Needs Marketplace](../features/needs-marketplace.md) |
| Moderator Role | Dedicated `listora_moderator` WP role | [Moderator Role](../features/moderators.md) |
| Infinite Scroll | Replace pagination with load-more or infinite scroll | [Infinite Scroll](../features/infinite-scroll.md) |

## Building your own extension

The same surfaces work for your own plugin / theme / mu-plugin. A minimal "log every new listing to Slack" extension is ~10 lines:

```php
<?php
/**
 * Plugin Name: My Listora → Slack
 */
add_action( 'wb_listora_after_create_listing', function ( int $post_id, $request ) {
    if ( get_post_status( $post_id ) !== 'publish' ) return;
    wp_remote_post( 'https://hooks.slack.com/services/...', array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( array(
            'text' => sprintf( '*New listing:* %s — %s', get_the_title( $post_id ), get_permalink( $post_id ) ),
        ) ),
        'timeout' => 5,
    ) );
}, 10, 2 );
```

For multi-event listeners (mirror Pro's pattern), subscribe to the canonical event hooks:

| Event | Hook | Args |
|---|---|---|
| New listing submitted | `wb_listora_listing_submitted` | `($post_id, $status, $request, $context)` |
| Status changed | `wb_listora_listing_status_changed` | `($post_id, $old_status, $new_status)` |
| Review posted | `wb_listora_review_submitted` | `($review_id, $listing_id, $user_id)` |
| Claim approved | `wb_listora_claim_approved` | `($claim_id, $listing_id, $user_id)` |
| Coupon redeemed (Pro) | `wb_listora_pro_coupon_redeemed` | `($coupon_id, $context)` |
| Need posted (Pro) | `wb_listora_pro_need_posted` | `($need_id, $context)` |

## Service locator

For services Free wants to expose to Pro (and that Pro might need to swap), Free uses a service locator pattern:

```php
// Free defines:
wb_listora_service( 'cache' );           // returns the Cache singleton
wb_listora_service( 'search_engine' );   // returns Search_Engine
wb_listora_service( 'capabilities' );    // returns Capabilities helper

// Pro consumes:
$cache = wb_listora_service( 'cache' );
$cache->bust( 'listings' );
```

This is **the only** way to reach Free's internal classes from Pro. Direct namespace refs (`new \WBListora\Core\Cache()`) are forbidden by INV-3.

## License model

All Pro features are gated behind an active license:

- **License key** entered at Listora → Settings → License (Pro).
- **Weekly remote check** via Action Scheduler — verifies key + expiry against the wbcom.com licensing endpoint.
- **License expired** → Pro features are deactivated. Existing Pro data (audit log entries, analytics, criteria ratings) is preserved. The Free plugin continues to work normally.
- **License renewed** → Pro features reactivate automatically on the next remote check (or manually via `wp listora-pro license check`).

See [License Management (Pro)](../getting-started/pro-license.md) for the customer-side flow + WP-CLI alternatives.

## Architecture invariants (enforced)

These rules are enforced by `bin/architecture-checks.sh` and `bin/cleanup-duplicate-detect.php`. Any PR that violates them is blocked.

- **INV-3**: Pro never directly imports `\WBListora\Core\*` internal classes — only hooks, REST, service-locator keys.
- **INV-12**: Cross-plugin coupling pairs are listed in `audit/derived/cross-plugin-coupling.json` — every new Pro listener gets a row.
- **INV-13**: Canonical credit-cost meta key is `_listora_plan_credits` (never `_listora_plan_credit_cost`).
- **INV-14**: Pro never re-fires a Free hook (would cause double-firing of Free's listeners — notifications, indexer, etc.). Exception: competitor migration sets `'context' => 'migration'` so Free listeners can opt out.

## Related

- [Hooks Reference](hooks-reference.md) — every action + filter, with consumer chains.
- [REST API](rest-api.md) — every endpoint, with auth model + handler.
- [Template Overrides](template-overrides.md) — theme-style overrides.
- [Custom Fields & Field Types](custom-fields.md) — register your own field types.
- [WP-CLI Commands](wp-cli-commands.md) — `wp listora-pro features list` to introspect runtime state.
