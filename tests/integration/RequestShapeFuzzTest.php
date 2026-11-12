<?php
/**
 * No Listora REST route returns a critical error for a param in the wrong shape.
 *
 * Every route Request_Shape_Guard covers is called once per param, with that param sent as
 * a list and then as a plain string. Params are the route's declared args plus
 * every `get_param( '...' )` or `$request['...']` its callback reads. A PHP error or a 5xx fails the
 * test with the route, method, param and error. A 4xx is the right answer.
 *
 * Pro runs the same test for its own routes through a subclass that overrides
 * owns_callback().
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group request-shape
 */
class RequestShapeFuzzTest extends WP_UnitTestCase {

	/**
	 * Routes that send a file or redirect and then exit, so they cannot run
	 * inside PHPUnit. Their args still pass through Request_Shape_Guard.
	 *
	 * @var string[]
	 */
	protected const STREAMING_ROUTES = array(
		'/listora/v1/export/csv',
		'/listora/v1/unsubscribe',
	);

	private int $listing_id = 0;

	/**
	 * The request being sent, named in any _doing_it_wrong() it triggers.
	 */
	private string $current = '';

	/**
	 * _doing_it_wrong() notices raised by a fuzzed request.
	 *
	 * @var string[]
	 */
	private array $misuse = array();

	public function set_up() {
		parent::set_up();
		add_filter( 'wb_listora_rate_limit_bypass', '__return_true' );
		// Route code that opens its own transaction must not commit the test's.
		add_filter( 'query', array( $this, 'nest_transactions' ) );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->listing_id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => $admin,
			)
		);
		add_action( 'doing_it_wrong_run', array( $this, 'record_misuse' ), 10, 2 );
		do_action( 'rest_api_init' );
	}

	/**
	 * Name the request that misused an API, e.g. a list reaching $wpdb->prepare().
	 *
	 * @param string $function Function misused.
	 * @param string $message  Notice text.
	 */
	public function record_misuse( $function, $message ) {
		$this->misuse[] = $this->current . ' ' . $function . ': ' . wp_strip_all_tags( (string) $message );
		// Reported in this test's own assertion, so the base class need not fail on it again.
		unset( $this->caught_doing_it_wrong[ $function ] );
	}

	public function tear_down() {
		remove_filter( 'query', array( $this, 'nest_transactions' ) );
		parent::tear_down();
	}

	/**
	 * Turn a route's own transaction into a savepoint inside the test's.
	 *
	 * @param string $query SQL.
	 * @return string
	 */
	public function nest_transactions( $query ) {
		$map = array(
			'START TRANSACTION' => 'SAVEPOINT listora_fuzz',
			'COMMIT'            => 'RELEASE SAVEPOINT listora_fuzz',
			'ROLLBACK'          => 'ROLLBACK TO SAVEPOINT listora_fuzz',
		);
		$key = strtoupper( trim( $query ) );

		return $map[ $key ] ?? $query;
	}

	/**
	 * Whether this suite owns the route's callback. Pro narrows it to its own.
	 *
	 * @param callable|mixed $callback Route callback.
	 */
	protected function owns_callback( $callback ): bool {
		if ( is_array( $callback ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			// Free's routes and the Credits SDK it bundles; not core's namespace index.
			return 0 === strpos( $class, 'WBListora\\' ) || 0 === strpos( $class, 'Wbcom\\Credits\\' );
		}

		return $callback instanceof Closure;
	}

	public function test_no_route_errors_on_a_list_or_a_string() {
		$failures = array();
		$checked  = 0;

		foreach ( rest_get_server()->get_routes() as $path => $handlers ) {
			if ( ! \WBListora\Rest\Request_Shape_Guard::covers( $path ) || in_array( $path, static::STREAMING_ROUTES, true ) ) {
				continue;
			}
			foreach ( $handlers as $handler ) {
				if ( empty( $handler['callback'] ) || ! $this->owns_callback( $handler['callback'] ) ) {
					continue;
				}
				$route = $this->fill_path( $path );
				$args  = is_array( $handler['args'] ?? null ) ? $handler['args'] : array();
				$base  = array();
				foreach ( $args as $name => $arg ) {
					if ( ! empty( $arg['required'] ) ) {
						$base[ $name ] = $this->plausible( is_array( $arg ) ? $arg : array() );
					}
				}
				$params = array_unique( array_merge( array_keys( $args ), $this->params_read_by( $handler['callback'] ) ) );

				foreach ( array_keys( array_filter( (array) $handler['methods'] ) ) as $method ) {
					foreach ( $params as $param ) {
						foreach ( array( array( 1, 2 ), 'x' ) as $value ) {
							++$checked;
							$this->current = sprintf( '%s %s [%s as %s]', $method, $path, $param, is_array( $value ) ? 'list' : 'string' );
							$error = $this->dispatch( $method, $route, array_merge( $base, array( $param => $value ) ) );
							if ( $error ) {
								$failures[] = sprintf( '%s %s [%s as %s] %s', $method, $path, $param, is_array( $value ) ? 'list' : 'string', $error );
							}
						}
					}
				}
			}
		}

		$this->assertGreaterThan( 50, $checked, 'The fuzz reached too few route params to mean anything.' );
		$failures = array_merge( $failures, array_unique( $this->misuse ) );
		$this->assertSame( array(), $failures, "Routes that error on a wrong-shaped param:\n" . implode( "\n", $failures ) );
	}

	/**
	 * Send one request; return the error text, or '' when it was handled.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Route with path params filled.
	 * @param array<string, mixed> $body   Params.
	 */
	private function dispatch( string $method, string $route, array $body ): string {
		$request = new WP_REST_Request( $method, $route );
		if ( 'GET' === $method || 'DELETE' === $method ) {
			$request->set_query_params( $body );
		} else {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		try {
			$response = rest_do_request( $request );
		} catch ( WPDieException $e ) {
			return '';
		} catch ( Throwable $e ) {
			return get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
		}

		if ( $response->get_status() >= 500 ) {
			$data = $response->get_data();
			return 'HTTP ' . $response->get_status() . ' ' . ( is_array( $data ) ? (string) ( $data['code'] ?? '' ) : '' );
		}

		return '';
	}

	/**
	 * Replace each named path group with a value its pattern accepts.
	 *
	 * @param string $path Route pattern.
	 */
	private function fill_path( string $path ): string {
		return (string) preg_replace_callback(
			'#\(\?P<[a-z_]+>([^)]+)\)#i',
			function ( $m ) {
				foreach ( array( (string) $this->listing_id, 'business', 'x' ) as $candidate ) {
					if ( preg_match( '#^' . $m[1] . '$#', $candidate ) ) {
						return $candidate;
					}
				}
				return 'x';
			},
			$path
		);
	}

	/**
	 * A value a required arg accepts, so the request reaches the route code.
	 *
	 * @param array<string, mixed> $arg Arg definition.
	 * @return mixed
	 */
	private function plausible( array $arg ) {
		if ( array_key_exists( 'default', $arg ) ) {
			return $arg['default'];
		}
		if ( ! empty( $arg['enum'] ) ) {
			return reset( $arg['enum'] );
		}
		$type = (array) ( $arg['type'] ?? 'string' );
		if ( array_intersect( $type, array( 'integer', 'number' ) ) ) {
			return $this->listing_id;
		}
		if ( in_array( 'boolean', $type, true ) ) {
			return true;
		}
		if ( array_intersect( $type, array( 'array', 'object' ) ) ) {
			return array();
		}

		return 'test';
	}

	/**
	 * Params the callback reads with get_param() but may not declare.
	 *
	 * @param callable|mixed $callback Route callback.
	 * @return string[]
	 */
	private function params_read_by( $callback ): array {
		try {
			$reflection = is_array( $callback )
				? new ReflectionMethod( $callback[0], $callback[1] )
				: new ReflectionFunction( $callback );
		} catch ( ReflectionException $e ) {
			return array();
		}
		$lines = file( (string) $reflection->getFileName() );
		if ( ! $lines ) {
			return array();
		}
		$body = implode( '', array_slice( $lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
		preg_match_all( "/(?:get_param\(\s*|\\\$request\[\s*)'([a-z0-9_]+)'/", $body, $matches );

		return $matches[1];
	}
}
