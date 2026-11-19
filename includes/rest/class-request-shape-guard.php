<?php
/**
 * Refuses a list where a Listora REST route expects a single value.
 *
 * WordPress checks a declared `type` only when the arg has no sanitize_callback
 * of its own, and sanitizes before the permission check, so `?slug[]=1` reached
 * string-only code as a logged-out critical error. This adds a shape-only check
 * (a list or object gets a 400; `5` for a string still passes) to every
 * single-value arg and every path param on the covered routes, Free's and
 * Pro's, before a route's own validate_callback. Undeclared params are not
 * covered: declare them (single_value_args() for scalars,
 * refuse_list_for_single_value_fields() for per-type `meta_*`). See CLAUDE.md.
 *
 * @package WBListora\Rest
 * @since 2.1.1
 */

namespace WBListora\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Shape guard for Listora REST args.
 */
class Request_Shape_Guard {

	/**
	 * Routes this guard covers: Listora's own (Free and Pro), and the Credits
	 * SDK routes registered under Listora's slug. The SDK's own copy is fixed
	 * upstream, never edited here.
	 */
	public const ROUTE_PREFIXES = array( '/listora/', '/wbcom-credits/v1/wb-listora/' );

	/**
	 * Core sanitizers that take one value and expect it to be a string or number.
	 */
	private const SINGLE_VALUE_SANITIZERS = array(
		'sanitize_text_field',
		'sanitize_textarea_field',
		'sanitize_key',
		'sanitize_title',
		'sanitize_hex_color',
		'sanitize_email',
		'esc_url_raw',
		'absint',
	);

	/**
	 * Register the guard.
	 */
	public static function init(): void {
		add_filter( 'rest_endpoints', array( __CLASS__, 'harden_endpoints' ), 999 );
	}

	/**
	 * Add the shape check to every arg of every Listora route.
	 *
	 * @param array<string, mixed> $endpoints Registered endpoints.
	 * @return array<string, mixed>
	 */
	public static function harden_endpoints( $endpoints ) {
		foreach ( $endpoints as $route => $handlers ) {
			if ( ! self::covers( (string) $route ) || ! is_array( $handlers ) ) {
				continue;
			}
			foreach ( $handlers as $key => $handler ) {
				if ( ! is_int( $key ) || ! is_array( $handler ) || empty( $handler['args'] ) || ! is_array( $handler['args'] ) ) {
					continue;
				}
				foreach ( $handler['args'] as $name => $arg ) {
					if ( is_array( $arg ) ) {
						$endpoints[ $route ][ $key ]['args'][ $name ] = self::harden_arg( $arg );
					}
				}
			}
			// A query or body param of the same name outranks a path param
			// (`/reviews/5?id[]=1`), so every path param is checked too,
			// declared or not.
			preg_match_all( '#\(\?P<([a-zA-Z0-9_]+)>#', (string) $route, $path_params );
			foreach ( $handlers as $key => $handler ) {
				if ( ! is_int( $key ) || ! is_array( $handler ) ) {
					continue;
				}
				foreach ( $path_params[1] as $name ) {
					if ( ! isset( $endpoints[ $route ][ $key ]['args'][ $name ] ) ) {
						$endpoints[ $route ][ $key ]['args'][ $name ] = array( 'validate_callback' => self::single_value_validator( null ) );
					}
				}
			}
		}

		return $endpoints;
	}

	/**
	 * Whether a route is one this guard covers.
	 *
	 * @param string $route Route pattern.
	 */
	public static function covers( string $route ): bool {
		foreach ( self::ROUTE_PREFIXES as $prefix ) {
			if ( 0 === strpos( $route, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add the shape check to one arg.
	 *
	 * @param array<string, mixed> $arg Arg definition.
	 * @return array<string, mixed>
	 */
	public static function harden_arg( array $arg ): array {
		$sanitize           = $arg['sanitize_callback'] ?? null;
		$core_single_value  = is_string( $sanitize ) && in_array( $sanitize, self::SINGLE_VALUE_SANITIZERS, true );
		$declared_type      = isset( $arg['type'] ) ? (array) $arg['type'] : array();
		$expects_single     = $declared_type
			? ! array_intersect( $declared_type, array( 'array', 'object' ) )
			: $core_single_value;

		if ( $core_single_value ) {
			$arg['sanitize_callback'] = static function ( $value ) use ( $sanitize ) {
				return $sanitize( $value );
			};
		}

		if ( $expects_single ) {
			$arg['validate_callback'] = self::single_value_validator( $arg['validate_callback'] ?? null );
		}

		return $arg;
	}

	/**
	 * Arg declarations for params a route reads but never declared.
	 *
	 * Undeclared, a list is coerced instead of refused: `tags` as an array
	 * emptied a listing's tags, `featured_image` as an array bound attachment 1,
	 * `status` or `plan_id` as an array took the publish or no-plan branch.
	 * Merge the result into a route's `args` (`array(...) + single_value_args(...)`);
	 * harden_endpoints() then adds the list refusal like any declared arg.
	 *
	 * @param array<string, string> $types Param name => JSON schema type
	 *                                     (`string`, `integer`, `number`).
	 * @return array<string, array<string, string>>
	 */
	public static function single_value_args( array $types ): array {
		$args = array();
		foreach ( $types as $name => $type ) {
			$args[ $name ] = array(
				'type'              => $type,
				'sanitize_callback' => 'rest_sanitize_request_arg',
			);
		}

		return $args;
	}

	/**
	 * Refuse a list sent for a listing-type field that holds one value.
	 *
	 * The `meta_*` params are named after each listing type's fields, so a route
	 * cannot declare them. Each field's own REST schema (Field::get_rest_schema())
	 * says whether it takes a list (gallery, multiselect, hours, price) or one
	 * value; a list for a one-value field made sanitize_email() and esc_url_raw()
	 * throw after the title was saved, and blanked text fields with a 200.
	 * Call before anything is written.
	 *
	 * @param \WP_REST_Request $request   Request.
	 * @param string           $type_slug Listing type slug.
	 * @return \WP_Error|null Null when every meta value has an accepted shape.
	 */
	public static function refuse_list_for_single_value_fields( $request, string $type_slug ) {
		$type = $type_slug ? \WBListora\Core\Listing_Type_Registry::instance()->get( $type_slug ) : null;
		if ( ! $type ) {
			return null;
		}

		$params = $request->get_params();
		foreach ( $type->get_all_fields() as $field ) {
			$name = 'meta_' . $field->get_key();
			if ( ! isset( $params[ $name ] ) || ! is_array( $params[ $name ] ) ) {
				continue;
			}
			$schema = $field->get_rest_schema();
			if ( array_intersect( (array) ( $schema['type'] ?? 'string' ), array( 'array', 'object' ) ) ) {
				continue;
			}

			return new \WP_Error(
				'rest_invalid_type',
				/* translators: %s: request parameter name. */
				sprintf( __( '%s must be a single value, not a list.', 'wb-listora' ), $name ),
				array(
					'status' => 400,
					'field'  => $name,
				)
			);
		}

		return null;
	}

	/**
	 * A validate_callback that refuses a list, then runs the arg's own one.
	 *
	 * @param callable|null $own_validate The arg's own validate_callback.
	 * @return \Closure
	 */
	private static function single_value_validator( $own_validate ): \Closure {
		return static function ( $value, $request, $param ) use ( $own_validate ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				return new \WP_Error(
					'rest_invalid_type',
					/* translators: %s: request parameter name. */
					sprintf( __( '%s must be a single value, not a list.', 'wb-listora' ), $param ),
					array( 'status' => 400 )
				);
			}

			return is_callable( $own_validate ) ? call_user_func( $own_validate, $value, $request, $param ) : true;
		};
	}
}
