---
journey: repair-locations-command
plugin: wb-listora
priority: high
roles: [admin]
covers: [BC-10190573574, BC-10185645412, wp-cli, data-repair, map_location]
prerequisites:
  - "WP-CLI available against the site"
  - "At least one listing with a location (a geo row with non-zero coordinates)"
estimated_runtime_minutes: 6
covers_card: 10190573574
---

# `wp listora repair-locations` — dry-run first, never automatic (BC 10190573574)

Every wp-admin listing save before `4dad883` wrote seven flat keys the renderer
never emitted, so `_listora_address` landed empty and the listing dropped off the
map and out of distance search. This command repairs the damage from the site's
own surviving data. It is deliberately NOT hooked to the activator or a
DB-version bump.

## Two findings that change how this is read

**1. More is recoverable than the card assumed.** The card states the address
text, city, state, country and postal code are unrecoverable. They are not: the
`listora_geo` row carries all of them, and a surviving geo row is the whole
precondition for repair. Where that row survived, the full street address comes
back. Nothing is invented and nothing is reverse-geocoded.

**2. "Empty address" is not a damage signature.** On the verification site 2709
of 2808 listings have no address meta — they never had a location. Reporting
those as data loss would tell an owner they lost thousands of addresses they
never entered. The command only considers listings with a surviving **witness**:

| Witness | Restores |
|---|---|
| `geo` row (lat/lng + full text) | address, city, state, country, postal code, coordinates |
| `search_index` row only | coordinates + city/country; street address must be re-entered |
| neither | not damaged — never had a location, never reported |

## Setup — create the damage, since a healthy site has none

```php
// Recoverable in full: empty the meta, leave the geo row.
\WBListora\Core\Meta_Handler::set_value( 17, 'address', array() );
// Coordinates-only: empty the meta AND delete the geo row.
\WBListora\Core\Meta_Handler::set_value( 31, 'address', array() );
$wpdb->delete( $wpdb->prefix . 'listora_geo', array( 'listing_id' => 31 ) );
```

## Steps

### 1. Dry run is the default and writes nothing
```bash
wp listora repair-locations
```
- **Expect**: a table of candidates; a summary splitting "from a surviving geo
  row" from "from the search index only"; `Dry run — nothing was written.`
- **Then assert the meta is untouched** — read `Meta_Handler::get_value()` for
  each listed ID and confirm address and lat are still empty. A dry run that
  quietly writes is the failure this step exists to catch.

### 2. Execute restores, and restores text where it survived
```bash
wp listora repair-locations --execute
```
- **Expect**: listings with a geo row get their street address, city, state,
  country, postal code and coordinates back. Index-only listings get
  coordinates + city/country with an **empty** street address.
- **Fails if** an index-only listing gains a street address — that would mean
  something reverse-geocoded, which places businesses on the wrong street.

### 3. Idempotent
Run `--execute` again immediately. Expect `No damaged locations found`. Running
twice must never double-write or re-report.

### 4. Healthy listings are never touched
A listing with a populated address must not appear in either mode, and the
`Skipped (already had an address)` counter exists for the race where a listing
is saved between the query and the write.

### 5. The meta-key trap
The key is `_listora_address`. `map_location` is the field **type**; `address`
is the field **key**. Querying the type returns zero rows and reads as total
data loss.

### 6. The serialization trap
The erased value is NOT `a:0:{}` — it is a full seven-key array of empty
strings. A `meta_value = 'a:0:{}'` test finds nothing and reports a clean site.
Detection reads through `Meta_Handler` for exactly this reason.
