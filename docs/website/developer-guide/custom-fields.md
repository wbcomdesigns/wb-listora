## Custom Fields & Field Types

WB Listora includes 22 built-in field types organized into 6 categories.

### Field Types

#### Basic
- **Text** - Single-line text input
- **Textarea** - Multi-line text input
- **Number** - Numeric input with optional min/max
- **Email** - Email address with validation
- **Phone** - Phone number input
- **URL** - Website URL with validation

#### Choice
- **Select** - Dropdown select (single value)
- **Multi-Select** - Dropdown with multiple selections
- **Checkbox** - Single checkbox (yes/no)
- **Radio** - Radio button group (single selection)

#### Date & Time
- **Date** - Date picker
- **Time** - Time picker
- **Date & Time** - Combined date and time

#### Media
- **Gallery** - Image gallery with drag-to-reorder
- **File Upload** - File attachment
- **Video** - Video URL (YouTube, Vimeo)

#### Location
- **Map Location** - Address with lat/lng coordinates

#### Structured
- **Business Hours** - Weekly hours with open/closed states
- **Social Links** - Social media profile URLs
- **Price Range** - Price level indicator ($, $$, $$$, $$$$)
- **Color** - Color picker
- **Rating** - Star rating input

### Field Properties

Each field has configurable properties:

| Property | Description |
|----------|-------------|
| `key` | Unique field identifier (auto-generated from label) |
| `label` | Display name shown in forms |
| `type` | Field type from the list above |
| `required` | Whether the field is mandatory |
| `searchable` | Include in full-text search index |
| `filterable` | Show as a filter option in search |
| `show_in_card` | Display on listing cards |
| `schema_prop` | Schema.org property mapping |
| `placeholder` | Placeholder text for inputs |
| `help_text` | Helper text below the field |
| `options` | Available options (for select/radio/checkbox) |

### Field Groups

Fields are organized into groups for the submission form and detail page:

```php
$field_groups = array(
array(
'key' => 'contact',
'label' => 'Contact Information',
'icon' => 'phone',
'fields' => array(
array( 'key' => 'address', 'type' => 'map_location', 'label' => 'Address' ),
array( 'key' => 'phone', 'type' => 'phone', 'label' => 'Phone' ),
),
),
);
```

### Adding Custom Fields

Use the visual field builder at **Listora > Listing Types > Edit Type**, or programmatically:

```php
add_filter( 'wb_listora_register_listing_types', function( $types ) {
$types['my-type']['field_groups'][] = array(
'key' => 'custom-group',
'label' => 'Custom Fields',
'fields' => array(
array(
'key' => 'custom_field',
'type' => 'text',
'label' => 'My Custom Field',
'required' => false,
),
),
);
return $types;
});
```

### Social Links field (since 2026-05-12)

The `social_links` field stores 7 platform URLs as an associative array:

```php
// Stored shape (in _listora_meta JSON or via REST):
array(
'website' => 'https://example.com',
'facebook' => 'https://facebook.com/example',
'twitter' => 'https://twitter.com/example',
'instagram' => 'https://instagram.com/example',
'linkedin' => 'https://linkedin.com/in/example',
'youtube' => 'https://youtube.com/@example',
'tiktok' => 'https://tiktok.com/@example',
)
```

The canonical 7 platforms are returned by `\WBListora\Core\Field::social_link_platforms()` so renderers / Pro extensions don't drift. Sanitization is centralized at `Field::sanitize_social_links()` - every URL passes `esc_url_raw` + a platform-specific host whitelist (a Facebook URL must be on `facebook.com`, etc.). The field's schema-generator output emits a `sameAs` array on the listing's JSON-LD so Google reads the same set.

To add an 8th platform, filter `wb_listora_social_link_platforms`:

```php
add_filter( 'wb_listora_social_link_platforms', function ( array $platforms ): array {
$platforms['threads'] = array(
'label' => 'Threads',
'icon' => 'message-circle',
'host' => 'threads.net',
);
return $platforms;
} );
```

### Registering a custom field type

The 22 built-in types live in `\WBListora\Core\Field_Registry`. To add your own type:

```php
add_filter( 'wb_listora_field_types', function ( array $types ): array {
$types['my_special_field'] = array(
'label' => __( 'My Special Field', 'my-plugin' ),
'category' => 'choice', // 'basic' | 'choice' | 'date_time' | 'media' | 'location' | 'structured'
'sanitizer' => array( My_Field_Handler::class, 'sanitize' ),
'renderer' => array( My_Field_Handler::class, 'render_submission' ),
'display' => array( My_Field_Handler::class, 'render_display' ),
'searchable' => true,
'filterable' => false,
);
return $types;
} );
```

The handler is responsible for:
- **Sanitization** at REST + admin entry points (`sanitize($value, $field_config): mixed`).
- **Submission rendering** - the form field HTML inside the submission wizard.
- **Display rendering** - the read-side HTML on the detail page.

Once registered, the new type appears in the Listing Types admin field-builder dropdown and accepts REST + WP-CLI imports like any built-in type.

## Related

- [Hooks Reference](hooks-reference.md) - every field-related action + filter.
- [REST API](rest-api.md) - how custom fields serialize in REST responses.
- [Extending with Pro](extending-with-pro.md) - how Pro adds Pro-only field types.
- [Template Overrides](template-overrides.md) - override the submission-step + detail-tab templates that render fields.
