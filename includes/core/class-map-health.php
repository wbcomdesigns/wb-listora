<?php
/**
 * Tells a site owner why their maps are blank.
 *
 * Listora ships no default tile server on purpose: OpenStreetMap's public tiles
 * are not licensed for product-scale use, and pointing every install at them
 * without asking is not ours to do. Settings > Maps explains all of that
 * clearly — to an owner who goes there.
 *
 * An owner who does not sees empty grey boxes where their maps should be, with
 * nothing anywhere saying a tile source is needed. The setting is right and the
 * silence around it is not: from where they sit, the map feature is broken.
 *
 * So the check goes where WordPress already trains people to look for "what is
 * wrong with my site", and it only speaks up when the site actually has
 * something to draw on a map. A directory that never uses maps should not be
 * told to configure them.
 *
 * @package WBListora\Core
 * @since 1.7.0
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Site Health check for an unconfigured tile source.
 */
class Map_Health {

	/**
	 * Register the check.
	 */
	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'register' ) );
	}

	/**
	 * Add the test.
	 *
	 * @param array<string, mixed> $tests Registered tests.
	 * @return array<string, mixed>
	 */
	public static function register( $tests ) {
		$tests['direct']['wb_listora_map_tiles'] = array(
			'label' => __( 'Maps have a tile source', 'wb-listora' ),
			'test'  => array( __CLASS__, 'run' ),
		);

		return $tests;
	}

	/**
	 * Whether any listing actually has coordinates to plot.
	 *
	 * @return bool
	 */
	private static function site_uses_maps(): bool {
		global $wpdb;

		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'geo';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off admin health check.
		$found = $wpdb->get_var( "SELECT 1 FROM {$table} LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant.

		return ! empty( $found );
	}

	/**
	 * Run the check.
	 *
	 * @return array<string, mixed>
	 */
	public static function run() {
		$result = array(
			'label'       => __( 'Maps have a tile source', 'wb-listora' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Listora', 'wb-listora' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Your maps have a tile source configured, so the map background draws for visitors.', 'wb-listora' ) . '</p>',
			'actions'     => '',
			'test'        => 'wb_listora_map_tiles',
		);

		$provider = (string) wb_listora_get_setting( 'map_provider', 'osm' );

		// Google draws its own tiles through its SDK; there is no raster layer
		// to configure, so this check does not apply.
		if ( 'google' === $provider ) {
			$result['description'] = '<p>' . esc_html__( 'Maps use Google, which supplies its own map background.', 'wb-listora' ) . '</p>';

			return $result;
		}

		$tiles = function_exists( 'wb_listora_get_map_tiles' ) ? wb_listora_get_map_tiles( $provider ) : array( 'url' => '' );

		if ( '' !== trim( (string) $tiles['url'] ) ) {
			return $result;
		}

		// Nothing to plot yet — no reason to raise it.
		if ( ! self::site_uses_maps() ) {
			$result['label']       = __( 'Maps are not in use yet', 'wb-listora' );
			$result['description'] = '<p>' . esc_html__( 'No listing has a location yet, so there is nothing to draw on a map. Once listings have addresses, choose a map tile source in Listora settings.', 'wb-listora' ) . '</p>';

			return $result;
		}

		$result['status']         = 'recommended';
		$result['badge']['color'] = 'orange';
		$result['label']          = __( 'Your maps have no background and will look empty', 'wb-listora' );
		$result['description']    = '<p>' . esc_html__( 'Listings on this site have locations, but no map tile source is set — so every map draws its markers on a blank background. Visitors see an empty grey box where the map should be.', 'wb-listora' ) . '</p>'
			. '<p>' . esc_html__( 'Listora deliberately ships no default. OpenStreetMap\'s public tiles are not licensed for use by a plugin at this scale, so pointing your site at them without asking would not be right. Use your own tile server or a provider such as MapTiler, Stadia Maps or Thunderforest — most have a free tier that covers a small directory.', 'wb-listora' ) . '</p>';

		$result['actions'] = sprintf(
			'<p><a href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'admin.php?page=listora-settings&tab=maps' ) ),
			esc_html__( 'Set a map tile source', 'wb-listora' )
		);

		return $result;
	}
}
