<?php
/**
 * REST Settings Controller.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Provides settings endpoints for admin and public map config.
 */
class Settings_Controller extends WP_REST_Controller {

	protected $namespace = WB_LISTORA_REST_NAMESPACE;
	protected $rest_base = 'settings';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /settings — all settings (admin only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_all_settings' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_listora_settings' );
					},
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_listora_settings' );
					},
				),
			)
		);

		// GET /settings/maps — public map config (provider, API key for frontend).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/maps',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_map_settings' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Get all settings (admin).
	 */
	public function get_all_settings( $request ) {
		$settings = get_option( 'wb_listora_settings', array() );
		$defaults = wb_listora_get_default_settings();

		return new WP_REST_Response( wp_parse_args( $settings, $defaults ), 200 );
	}

	/**
	 * Update settings (admin).
	 */
	public function update_settings( $request ) {
		$current  = get_option( 'wb_listora_settings', array() );
		$defaults = wb_listora_get_default_settings();
		$params   = $request->get_params();

		// Only update known keys, with type-safe sanitization.
		foreach ( $defaults as $key => $default ) {
			if ( ! isset( $params[ $key ] ) ) {
				continue;
			}

			$value = $params[ $key ];

			if ( is_bool( $default ) ) {
				$current[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$current[ $key ] = (int) $value;
			} elseif ( is_float( $default ) ) {
				$current[ $key ] = (float) $value;
			} else {
				$current[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		update_option( 'wb_listora_settings', $current );

		// Flush rewrite rules if slugs changed.
		if ( isset( $params['listing_slug'] ) || isset( $params['category_slug'] ) ) {
			flush_rewrite_rules();
		}

		return new WP_REST_Response( array( 'updated' => true ), 200 );
	}

	/**
	 * Get public map settings (no auth required).
	 */
	public function get_map_settings( $request ) {
		return new WP_REST_Response(
			array(
				'provider'     => wb_listora_get_setting( 'map_provider', 'osm' ),
				'default_lat'  => (float) wb_listora_get_setting( 'map_default_lat', 40.7128 ),
				'default_lng'  => (float) wb_listora_get_setting( 'map_default_lng', -74.0060 ),
				'default_zoom' => (int) wb_listora_get_setting( 'map_default_zoom', 12 ),
				'clustering'   => (bool) wb_listora_get_setting( 'map_clustering', true ),
			),
			200
		);
	}
}
