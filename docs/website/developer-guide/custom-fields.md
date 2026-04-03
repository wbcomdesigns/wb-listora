## Custom Fields & Field Types

WB Listora includes 22 built-in field types organized into 6 categories.

### Field Types

#### Basic
- **Text** — Single-line text input
- **Textarea** — Multi-line text input
- **Number** — Numeric input with optional min/max
- **Email** — Email address with validation
- **Phone** — Phone number input
- **URL** — Website URL with validation

#### Choice
- **Select** — Dropdown select (single value)
- **Multi-Select** — Dropdown with multiple selections
- **Checkbox** — Single checkbox (yes/no)
- **Radio** — Radio button group (single selection)

#### Date & Time
- **Date** — Date picker
- **Time** — Time picker
- **Date & Time** — Combined date and time

#### Media
- **Gallery** — Image gallery with drag-to-reorder
- **File Upload** — File attachment
- **Video** — Video URL (YouTube, Vimeo)

#### Location
- **Map Location** — Address with lat/lng coordinates

#### Structured
- **Business Hours** — Weekly hours with open/closed states
- **Social Links** — Social media profile URLs
- **Price Range** — Price level indicator ($, $$, $$$, $$$$)
- **Color** — Color picker
- **Rating** — Star rating input

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
        'key'    => 'contact',
        'label'  => 'Contact Information',
        'icon'   => 'phone',
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
        'key'    => 'custom-group',
        'label'  => 'Custom Fields',
        'fields' => array(
            array(
                'key'      => 'custom_field',
                'type'     => 'text',
                'label'    => 'My Custom Field',
                'required' => false,
            ),
        ),
    );
    return $types;
});
```
