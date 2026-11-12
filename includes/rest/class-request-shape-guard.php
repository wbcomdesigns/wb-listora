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
 * covered: declare them. See "Request-shape rule" in CLAUDE.md.
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
