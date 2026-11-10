# Map Providers

Listora draws maps through a registry of providers. OpenStreetMap ships in Free. Pro registers Google, Kakao, Naver and AMap. Your own plugin can add another.

## Register a provider

Extend `WBListora\Core\Map_Provider` and add it with the `wb_listora_map_providers` filter:

```php
add_filter( 'wb_listora_map_providers', function ( array $providers ) {
	$providers['acme'] = new Acme_Map_Provider();
	return $providers;
} );
```

A provider reports its `slug()`, `label()`, `kind()` (`tiles` for a Leaflet tile source, `sdk` for a vendor script), `is_ready()` and `key_setting()`.

## What the registry does for you

- **Settings > Maps** and the Setup Wizard list every registered provider.
- `wb_listora_get_setting( 'map_provider' )` returns the saved slug, or `osm` when the saved provider is registered but not ready (for example, no key). A slug that is not registered passes through untouched.
- For `sdk` providers Listora skips Leaflet and the tile checks on the page. Site Health names the provider.

## Hooks

- `wb_listora_map_providers` (filter) - the provider list.
- `wb_listora_map_provider` (filter) - the final provider slug.
- `wb_listora_map_config` (filter) - the map settings passed to the blocks.

## Scripts for an SDK provider

Register your picker and detail map on the existing objects, so the blocks find them by slug:

- `window.wbListoraMapPickers[ slug ]` - the Add Listing location picker.
- `window.wbListoraDetailMaps[ slug ]` - the listing detail map.
- `window.wbListoraGeocoder` - address search.

The directory map block reads the active provider from its map config.

## Related

- [Hooks Reference](hooks-reference.md)
- [Map Settings](../settings/map-settings.md)
- [Regional Maps (Pro)](../features/regional-maps.md)
