## Hooks Reference

WB Listora provides hooks for extending functionality. The Pro add-on uses these same hooks.

### Actions

#### `wb_listora_loaded`
Fires when the plugin is fully loaded. Use this to hook into Listora functionality.

```php
add_action( 'wb_listora_loaded', function() {
    // Plugin is ready
});
```

#### `wb_listora_rest_api_init`
Fires after REST routes are registered.

```php
add_action( 'wb_listora_rest_api_init', function() {
    // Register custom REST routes
});
```

#### `wb_listora_listing_submitted`
Fires after a listing is submitted from the frontend.

```php
add_action( 'wb_listora_listing_submitted', function( $post_id, $status, $request ) {
    // Send notification, log event, etc.
}, 10, 3 );
```

#### `wb_listora_review_submitted`
Fires after a review is posted.

```php
add_action( 'wb_listora_review_submitted', function( $review_id, $listing_id, $user_id, $criteria_ratings ) {
    // Pro uses this to save multi-criteria ratings
}, 10, 4 );
```

#### `wb_listora_after_listing_fields`
Fires after listing detail fields render. Used by Pro for lead forms.

```php
add_action( 'wb_listora_after_listing_fields', function( $post_id, $listing_type ) {
    // Add custom content after listing fields
}, 10, 2 );
```

### Filters

#### `wb_listora_review_criteria`
Filter review criteria fields for multi-criteria ratings.

```php
add_filter( 'wb_listora_review_criteria', function( $criteria, $type_slug ) {
    if ( 'restaurant' === $type_slug ) {
        return array(
            array( 'key' => 'food', 'label' => 'Food' ),
            array( 'key' => 'service', 'label' => 'Service' ),
        );
    }
    return $criteria;
}, 10, 2 );
```

#### `wb_listora_map_config`
Filter map configuration.

```php
add_filter( 'wb_listora_map_config', function( $config ) {
    $config['tileUrl'] = 'https://your-tile-server/{z}/{x}/{y}.png';
    return $config;
});
```

#### `wb_listora_search_args`
Filter search query parameters.

```php
add_filter( 'wb_listora_search_args', function( $args ) {
    $args['per_page'] = 12;
    return $args;
});
```

#### `wb_listora_settings_tabs` / `wb_listora_settings_tab_content`
Add custom settings tabs.

```php
add_filter( 'wb_listora_settings_tabs', function( $tabs ) {
    $tabs['custom'] = array( 'label' => 'My Tab', 'icon' => 'settings' );
    return $tabs;
});
```

### REST API Namespace

All endpoints are under `listora/v1`. Use `wp_json_encode()` for responses and `$request->get_param()` for input.

See the [REST API documentation](rest-api.md) for the full endpoint reference.
