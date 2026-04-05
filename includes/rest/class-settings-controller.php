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
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'reset_settings' ),
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

		// GET /settings/export — download settings as JSON.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_settings' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_listora_settings' );
					},
				),
			)
		);

		// POST /settings/import — upload JSON to replace settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_settings' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_listora_settings' );
					},
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

	/**
	 * Reset all settings to defaults (DELETE /settings).
	 *
	 * Deletes the stored option so defaults regenerate automatically.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function reset_settings( $request ) {
		delete_option( 'wb_listora_settings' );
		$defaults = wb_listora_get_default_settings();

		return new WP_REST_Response(
			array(
				'reset'    => true,
				'settings' => $defaults,
			),
			200
		);
	}

	/**
	 * Export settings as JSON (GET /settings/export).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function export_settings( $request ) {
		$settings = get_option( 'wb_listora_settings', array() );
		$defaults = wb_listora_get_default_settings();

		$response = new WP_REST_Response(
			array(
				'plugin'     => 'wb-listora',
				'version'    => defined( 'WB_LISTORA_VERSION' ) ? WB_LISTORA_VERSION : '1.0.0',
				'exported'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'settings'   => wp_parse_args( $settings, $defaults ),
			),
			200
		);

		$response->header( 'Content-Disposition', 'attachment; filename=wb-listora-settings.json' );

		return $response;
	}

	/**
	 * Import settings from JSON (POST /settings/import).
	 *
	 * Accepts a JSON body with a "settings" key containing settings to merge.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function import_settings( $request ) {
		$body = $request->get_json_params();

		if ( empty( $body['settings'] ) || ! is_array( $body['settings'] ) ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Invalid import data. Expected a JSON object with a "settings" key.', 'wb-listora' ) ),
				400
			);
		}

		$imported = $body['settings'];
		$defaults = wb_listora_get_default_settings();
		$clean    = array();

		// Only import keys that exist in defaults (type-safe).
		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $imported ) ) {
				$clean[ $key ] = $default;
				continue;
			}

			$value = $imported[ $key ];

			if ( is_bool( $default ) ) {
				$clean[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = (int) $value;
			} elseif ( is_float( $default ) ) {
				$clean[ $key ] = (float) $value;
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		// Import nested arrays (notifications, reviews) if present.
		if ( isset( $imported['notifications'] ) && is_array( $imported['notifications'] ) ) {
			$clean['notifications'] = array_map( 'absint', $imported['notifications'] );
		}

		if ( isset( $imported['reviews'] ) && is_array( $imported['reviews'] ) ) {
			$clean['reviews'] = array(
				'auto_approve'    => ! empty( $imported['reviews']['auto_approve'] ),
				'require_login'   => ! empty( $imported['reviews']['require_login'] ),
				'min_length'      => isset( $imported['reviews']['min_length'] ) ? absint( $imported['reviews']['min_length'] ) : 20,
				'one_per_listing' => ! empty( $imported['reviews']['one_per_listing'] ),
				'allow_reply'     => ! empty( $imported['reviews']['allow_reply'] ),
			);
		}

		update_option( 'wb_listora_settings', $clean );

		return new WP_REST_Response(
			array(
				'imported' => true,
				'settings' => $clean,
			),
			200
		);
	}
}
