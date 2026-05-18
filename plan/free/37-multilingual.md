# 37 — Multilingual & RTL Support

## Scope

| | Free | Pro |
|---|---|---|
| Plugin string translation (.pot) | Yes | Yes |
| RTL CSS support (logical properties) | Yes | Yes |
| WPML/Polylang integration | Yes | Yes |
| Field label translation | Yes | Yes |
| Non-Latin FULLTEXT search | Yes | Yes |

---

## Translation Readiness

### Plugin Strings
- All user-facing strings wrapped in `__()`, `_e()`, `esc_html__()`, etc.
- Text domain: `wb-listora`
- POT file generated at `languages/wb-listora.pot`
- Loaded via `load_plugin_textdomain()` on `init`

### Field Label Translation

Field labels stored in term meta (`_listora_field_groups` JSON) are plain strings. For translation:

**Method 1: WPML String Translation (Recommended)**
On listing type save, register all field labels with WPML:
```php
if (function_exists('icl_register_string')) {
    foreach ($field_groups as $group) {
        icl_register_string('wb-listora', "field_group_{$group['key']}", $group['label']);
        foreach ($group['fields'] as $field) {
            icl_register_string('wb-listora', "field_{$field['key']}_label", $field['label']);
            if (!empty($field['options'])) {
                foreach ($field['options'] as $opt) {
                    icl_register_string('wb-listora', "field_{$field['key']}_opt_{$opt['value']}", $opt['label']);
                }
            }
        }
    }
}
```

**Method 2: Polylang String Translations**
Same pattern using `pll_register_string()`.

**Method 3: Gettext filter fallback**
```php
apply_filters('wb_listora_field_label', $label, $field_key, $locale);
```

### When Rendering
```php
$label = function_exists('icl_t')
    ? icl_t('wb-listora', "field_{$field_key}_label", $field['label'])
    : $field['label'];
```

---

## WPML/Polylang Integration

### CPT Registration
Already has `'show_in_rest' => true` and `'public' => true` — WPML/Polylang auto-detect translatable CPTs.

### Taxonomy Translation
All taxonomies registered with `'public' => true` — auto-detected by WPML/Polylang.

### WPML Config File (`wpml-config.xml`)
Bundled with the plugin:
```xml
<wpml-config>
  <custom-types>
    <custom-type translate="1">listora_listing</custom-type>
    <custom-type translate="0">listora_plan</custom-type>
  </custom-types>
  <taxonomies>
    <taxonomy translate="1">listora_listing_type</taxonomy>
    <taxonomy translate="1">listora_listing_cat</taxonomy>
    <taxonomy translate="1">listora_listing_tag</taxonomy>
    <taxonomy translate="1">listora_listing_location</taxonomy>
    <taxonomy translate="1">listora_listing_feature</taxonomy>
  </taxonomies>
  <custom-fields>
    <custom-field action="translate">_listora_phone</custom-field>
    <custom-field action="translate">_listora_address</custom-field>
    <custom-field action="copy">_listora_lat</custom-field>
    <custom-field action="copy">_listora_lng</custom-field>
    <custom-field action="copy">_listora_price_range</custom-field>
    <custom-field action="copy">_listora_business_hours</custom-field>
    <custom-field action="copy">_listora_gallery</custom-field>
  </custom-fields>
</wpml-config>
```

### Custom Tables and Translation

| Table | Multilingual Behavior |
|-------|----------------------|
| `listora_search_index` | One row per listing per language. Each translation is a separate WP post with its own listing_id. Index populated per translation. |
| `listora_field_index` | Same — one set of rows per translation's listing_id. |
| `listora_geo` | Shared — lat/lng don't change per language. Address translated. |
| `listora_reviews` | Reviews belong to the original language listing. Shown on all translations. |
| `listora_favorites` | Stored on the original listing ID. `wpml_object_id()` used to resolve across languages. |
| `listora_hours` | Shared — hours don't change per language. |

### Search per Language
WPML/Polylang automatically filter `WP_Query` by current language. For custom table queries:
```php
// Get current language listings only
$lang = apply_filters('wpml_current_language', null);
if ($lang) {
    // Join with WPML's translation table to filter search_index by language
    // OR: search_index stores language column
}
```

**Recommended approach:** Add `language` column to `listora_search_index`:
```sql
ALTER TABLE listora_search_index ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT 'en';
```
Populated from WPML/Polylang language of the post. Search queries add `WHERE language = ?`.

---

## Non-Latin Text Search (Arabic, CJK, Cyrillic)

### Problem
MySQL FULLTEXT with default parser uses whitespace/punctuation for word boundaries. This fails for:
- **Arabic:** Words connect without spaces in some forms
- **CJK (Chinese, Japanese, Korean):** No spaces between words
- **Thai:** No spaces between words

### Solution
```sql
-- Use ngram parser for FULLTEXT index (MySQL 5.7.6+)
CREATE TABLE listora_search_index (
    ...
    FULLTEXT idx_search (title, content_text, meta_text) WITH PARSER ngram
) ENGINE=InnoDB;
```

**ngram configuration:**
```sql
-- Set minimum token size (default 2, good for CJK)
[mysqld]
ngram_token_size=2
```

**Detection:**
On activation, check MySQL version and engine:
- MySQL 5.7.6+ with InnoDB → use ngram parser
- Older MySQL or MyISAM → use default parser (works for Latin scripts)
- MariaDB → use default parser (ngram not available; acceptable for v1)

**Fallback for non-ngram environments:**
If ngram unavailable, use `LIKE '%keyword%'` on the `title` column only (slower but functional). Log a warning suggesting MySQL upgrade.

---

## RTL Support

### CSS Strategy
Already using CSS logical properties throughout (see `35-theme-compatibility.md`):
```css
margin-inline-start: 1rem;    /* instead of margin-left */
padding-inline-end: 1rem;     /* instead of padding-right */
text-align: start;            /* instead of text-align: left */
border-inline-start: 3px solid; /* instead of border-left */
```

### RTL-Specific Test Cases

| Component | RTL Check |
|-----------|-----------|
| Search bar | Input fields flow right-to-left, search icon on right |
| Filter pills | Pills flow right-to-left, × button on left |
| Listing cards | Image left → image right, text flows RTL |
| Map controls | Zoom buttons mirror position |
| Breadcrumbs | Separator direction flips (< instead of >) |
| Tabs | Tab order right-to-left |
| Submission form | Labels above inputs, fields flow RTL |
| Gallery lightbox | Nav arrows swap sides |
| Pagination | Page numbers right-to-left |
| Dashboard sidebar | Sidebar on right |
| Business hours | Day names in locale's language, times in locale format |
| Star rating | Stars fill right-to-left |
| Sort dropdown | Dropdown opens on correct side |

### HTML dir Attribute
Plugin respects the page's `dir="rtl"` attribute set by WordPress core based on locale.

### Testing
- Test with Arabic locale (`ar`) in WordPress
- Test with Hebrew locale (`he`)
- Use browser DevTools RTL emulation for development
- Screenshot comparison: LTR vs RTL for every component

---

## Hreflang Tags

For multilingual directories, output hreflang in `wp_head`:
```html
<link rel="alternate" hreflang="en" href="https://site.com/listing/pizza-palace/" />
<link rel="alternate" hreflang="ar" href="https://site.com/ar/listing/قصر-البيتزا/" />
<link rel="alternate" hreflang="x-default" href="https://site.com/listing/pizza-palace/" />
```

**Implementation:**
- WPML/Polylang handle hreflang automatically for posts
- Plugin ensures custom endpoints (search, listing-types) also get proper hreflang via filter
- `apply_filters('wb_listora_hreflang_urls', $urls, $post_id)`

---

## Localized Date/Time/Number Formatting

| Data | Formatting |
|------|-----------|
| Dates | `wp_date()` with locale-aware formatting |
| Times | 12h or 24h based on `get_option('time_format')` |
| Numbers | `number_format_i18n()` for review counts, listing counts |
| Currency | `$`, `€`, `£` etc. positioned per locale (before or after amount) |
| Day names | `wp_date('l')` for localized day names in business hours |
| Distance | km or miles based on plugin setting, with localized number formatting |
