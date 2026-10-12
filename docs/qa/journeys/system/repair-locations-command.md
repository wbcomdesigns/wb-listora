---
journey: repair-locations-command
plugin: wb-listora
priority: high
roles: [admin]
covers: [BC-10190573574, BC-10185645412, wp-cli, data-repair, map_location, card-10190573574]
prerequisites:
  - "WP-CLI available against the site"
  - "Disposable fixtures only (prefix QA-); never damage real listings"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes:
  - ora:regression/repair-locations-command.md
fails_on: unrecorded
site_profile: required
---

# `wp listora repair-locations`: dry-run first, never automatic

## Why
An old wp-admin save wrote flat keys the renderer never emitted, so `_listora_address` landed empty and the listing left the map and distance search. `wp listora repair-locations` (`includes/class-cli-commands.php:987`) repairs it from surviving data; it is NOT hooked to the activator or a DB-version bump.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Witnesses (only listings with one are ever considered): `listora_geo` row (restores address, city, state, country, postal code, coordinates); `listora_search_index` row only (coordinates + city/country; street address must be re-entered); neither (never had a location, never reported).
```php
$a = wp_insert_post( array( 'post_type'=>'listora_listing','post_status'=>'publish','post_title'=>'QA repair A' ) );
$b = wp_insert_post( array( 'post_type'=>'listora_listing','post_status'=>'publish','post_title'=>'QA repair B' ) );
$addr = array( 'address'=>'1 Test Street','city'=>'Testville','state'=>'NY','country'=>'US','postal_code'=>'10001','lat'=>40.7128,'lng'=>-74.0060 );
\WBListora\Core\Meta_Handler::set_value( $a, 'address', $addr );   // writes meta + geo + index
\WBListora\Core\Meta_Handler::set_value( $b, 'address', $addr );
\WBListora\Core\Meta_Handler::set_value( $a, 'address', array() ); // damage A: meta empty, geo row survives
\WBListora\Core\Meta_Handler::set_value( $b, 'address', array() );
$wpdb->delete( $wpdb->prefix . 'listora_geo', array( 'listing_id' => $b ) ); // B: index-only
```
- On a clean site the command prints: `Success: No damaged locations found - every listing that ever had a location still has it.`

## Steps

1. **Dry run is the default and writes nothing**: `wp listora repair-locations`. **Expect**: a table (`id title status lat lng address`) with A showing "1 Test Street" and B "(coordinates only)"; "2 listing(s) can be repaired.", "  1 from a surviving geo row - full address text is restored.", "  1 from the search index only - coordinates are restored, street address must be re-entered.", and `Success: Dry run - nothing was written. Re-run with --execute to apply.` **Read-back**: `Meta_Handler::get_value( $a, 'address' )` is still the eight-key array of empty strings.
2. **Execute restores, and restores text where it survived**: `wp listora repair-locations --execute`. **Expect**: `Repaired: 2`, `Skipped (already had an address): 0`, `Coordinates-only (no geo row survived): 1 - the street address must be re-entered by hand.`, `Success: Location repair complete. ...`. A gets street, city, state, country, postal code and coordinates; B gets coordinates + city/country with `address` empty. Fails if B gains a street address (that would be reverse geocoding).
3. **Idempotent**: `--execute` again. **Expect**: `No damaged locations found`, nothing re-reported.
4. **Healthy listings are never touched**: a listing with a populated address appears in neither mode; the `Skipped (already had an address)` counter covers a listing saved between the query and the write.
5. **Meta-key trap**: the key is `_listora_address`; `map_location` is the field TYPE, `address` the field KEY. Querying the type returns zero rows.
6. **Serialization trap**: the erased value is NOT `a:0:{}` but a full array of empty strings (`address, city, state, country, postal_code, country_code, lat, lng`). Detection reads through `Meta_Handler`, so a `meta_value = 'a:0:{}'` test would report a clean site.
7. **Output formats** (added): `--format=csv` prints the same rows as the table. Not verified live.
8. **Large site**: the command loads every geo/index row with a non-zero coordinate into memory and reads each through `Meta_Handler` (no LIMIT / batching, `class-cli-commands.php:1000-1060`). Fine at a few thousand listings; at 100k a batched variant is needed (open gap).

## Role ladder
CLI / {admin} only (system journey; no member surface). Control: healthy listings are never listed.

## Pass criteria
1. Dry run lists damage and writes nothing.
2. `--execute` repairs from the strongest surviving witness and never invents a street address.
3. A second run reports nothing.

## Fail diagnostics
| Symptom | File |
|---|---|
| Dry run writes data | `includes/class-cli-commands.php:987` |
| Damage not detected | detection must read through `Meta_Handler`, not a raw `a:0:{}` query |
| Memory growth at scale | `class-cli-commands.php:1000-1060` |

## Not verified
Behaviour at 100k rows; `--format=csv`.

## Cleanup
`wp_delete_post( $a, true ); wp_delete_post( $b, true );` and confirm no `listora_geo` / `listora_search_index` rows remain.
