---
journey: repair-locations-command
plugin: wb-listora
priority: high
roles: [admin]
covers: [BC-10190573574, BC-10185645412, wp-cli, data-repair, map_location]
prerequisites:
  - "WP-CLI available against the site"
  - "Disposable fixtures only (prefix QA-J1-); never damage real listings"
estimated_runtime_minutes: 6
covers_card: 10190573574
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# `wp listora repair-locations`: dry-run first, never automatic

An old wp-admin save wrote flat keys the renderer never emitted, so `_listora_address` landed empty and the listing left the map
and distance search. `wp listora repair-locations` (`includes/class-cli-commands.php:987`) repairs it from surviving data; it is NOT
hooked to the activator or a DB-version bump. Role ladder: CLI/admin only (system journey; no member surface). Control: healthy
listings are never listed.

Witnesses (only listings with one are ever considered):
| Witness | Restores |
|---|---|
| `listora_geo` row (lat/lng + text) | address, city, state, country, postal code, coordinates |
| `listora_search_index` row only | coordinates + city/country; street address must be re-entered |
| neither | never had a location, never reported ("Empty address" is not damage) |

## Setup (verified 2026-10-12, fixtures created and deleted)
```php
$a = wp_insert_post( array( 'post_type'=>'listora_listing','post_status'=>'publish','post_title'=>'QA-J1 repair A' ) );
$b = wp_insert_post( array( 'post_type'=>'listora_listing','post_status'=>'publish','post_title'=>'QA-J1 repair B' ) );
$addr = array( 'address'=>'1 Test Street','city'=>'Testville','state'=>'NY','country'=>'US','postal_code'=>'10001','lat'=>40.7128,'lng'=>-74.0060 );
\WBListora\Core\Meta_Handler::set_value( $a, 'address', $addr );   // writes meta + geo + index
\WBListora\Core\Meta_Handler::set_value( $b, 'address', $addr );
\WBListora\Core\Meta_Handler::set_value( $a, 'address', array() ); // damage A: meta empty, geo row survives
\WBListora\Core\Meta_Handler::set_value( $b, 'address', array() );
$wpdb->delete( $wpdb->prefix . 'listora_geo', array( 'listing_id' => $b ) ); // B: index-only
```
On a clean site the command prints: `Success: No damaged locations found - every listing that ever had a location still has it.`

## Steps

### 1. Dry run is the default and writes nothing
- **Action**: `wp listora repair-locations`
- **Expect**: a table (`id title status lat lng address`) with A showing "1 Test Street" and B "(coordinates only)"; then
  "2 listing(s) can be repaired.", "  1 from a surviving geo row - full address text is restored.",
  "  1 from the search index only - coordinates are restored, street address must be re-entered.", and
  `Success: Dry run - nothing was written. Re-run with --execute to apply.`
- **Then**: `Meta_Handler::get_value( $a, 'address' )` is still the eight-key array of empty strings (verified).

### 2. Execute restores, and restores text where it survived
- **Action**: `wp listora repair-locations --execute`
- **Expect**: `Repaired: 2`, `Skipped (already had an address): 0`, `Coordinates-only (no geo row survived): 1 - the street address must be
  re-entered by hand.`, `Success: Location repair complete. ...`. A gets street, city, state, country, postal code and coordinates;
  B gets coordinates + city/country with `address` empty (verified). **Fails if** B gains a street address (that would be reverse
  geocoding).

### 3. Idempotent
- **Action**: `--execute` again. **Expect**: `No damaged locations found`, nothing re-reported.

### 4. Healthy listings are never touched
- A listing with a populated address appears in neither mode; the `Skipped (already had an address)` counter exists for the race where
  a listing is saved between the query and the write.

### 5. The meta-key trap
- The key is `_listora_address`; `map_location` is the field TYPE, `address` the field KEY. Querying the type returns zero rows.

### 6. The serialization trap
- The erased value is NOT `a:0:{}` but a full array of empty strings (`address, city, state, country, postal_code, country_code, lat,
  lng`). Detection reads through `Meta_Handler`, so a `meta_value = 'a:0:{}'` test would report a clean site.

### 7. Cleanup
`wp_delete_post( $a, true ); wp_delete_post( $b, true );` and confirm no `listora_geo` / `listora_search_index` rows remain (verified).

## Large-site note
The command loads every geo/index row with a non-zero coordinate into memory and reads each through `Meta_Handler` (no LIMIT /
batching, `class-cli-commands.php:1000-1060`). Fine at 2,800 listings; at 100k a batched variant is needed (see report).

## Not verified
Behaviour at 100k rows; `--format=csv`.
