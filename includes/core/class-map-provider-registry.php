<?php
/**
 * The list of map providers and the one rule for which is live.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Filter-filled registry of Map_Provider objects.
 */
class Map_Provider_Registry {

	const DEFAULT_SLUG = 'osm';

	/**
	 * All registered providers keyed by slug, OpenStreetMap first.
	 *
	 * @return Map_Provider[]
	 */
	public static function all(): array {
		$osm = new Osm_Map_Provider();

		/**
		 * Register map providers.
		 *
		 * @param Map_Provider[] $providers Providers keyed by slug.
		 */
		$filtered = apply_filters( 'wb_listora_map_providers', array( $osm->slug() => $osm ) );
		$out      = array();

		foreach ( (array) $filtered as $provider ) {
			if ( $provider instanceof Map_Provider ) {
				$out[ $provider->slug() ] = $provider;
			}
		}

		return isset( $out[ self::DEFAULT_SLUG ] ) ? $out : array( self::DEFAULT_SLUG => $osm ) + $out;
	}

	/**
	 * @param string $slug Provider slug.
	 * @return Map_Provider|null
	 */
	public static function get( string $slug ): ?Map_Provider {
		return self::all()[ $slug ] ?? null;
	}

	/**
	 * The slug that actually serves maps for a requested one: a registered
	 * provider with no key falls back to OpenStreetMap, so a half-configured
	 * Maps tab never blanks a map. An unregistered slug passes through, so a
	 * site answering `wb_listora_map_provider` itself keeps working.
	 *
	 * @param string $slug Requested slug.
	 * @return string
	 */
	public static function resolve( string $slug ): string {
		$provider = self::get( $slug );

		if ( $provider && ! $provider->is_ready() ) {
			return self::DEFAULT_SLUG;
		}

		return '' === $slug ? self::DEFAULT_SLUG : $slug;
	}
}
