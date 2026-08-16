<?php
/**
 * Webhook receiver fixture.
 *
 * A journey that only reads Pro's OWN delivery log to assert "the webhook
 * fired" is trusting the thing under test to grade itself. This fixture is
 * an independent witness on the RECEIVING end: a standalone PHP endpoint
 * that records every request it gets — method, every header (including the
 * HMAC signature header), and the raw body — to a JSON file a journey can
 * read back, and that can be told to answer HTTP 500 for the first N
 * requests since its last reset so the 60s / 300s / 1800s retry ladder and
 * MAX_ATTEMPTS=4 give-up are exercisable rather than assumed.
 *
 * Run it with PHP's built-in server, from the Free plugin root:
 *
 *   php -S 127.0.0.1:8955 docs/qa/fixtures/webhook-receiver.php
 *
 * Passing this file directly as the built-in server's script makes it the
 * router for every request regardless of path — every request PHP's
 * dev-server receives is dispatched here, and the fixture uses `?_control=`
 * on the query string to distinguish its own control calls from an actual
 * webhook delivery.
 *
 * Control requests (any HTTP method; never logged as a delivery):
 *
 *   GET /?_control=reset[&fail_first=N]   Clear the log, arm N forced 500s.
 *   GET /?_control=log                    Return the recorded deliveries as JSON.
 *   GET /?_control=count                  Return { "received": N } only.
 *
 * Anything else is treated as a real delivery. It is appended to the log
 * (with the HTTP response this fixture is about to send it recorded on the
 * same entry) and answered:
 *
 *   - HTTP 500 while the running count of deliveries received since the
 *     last reset is <= the armed `fail_first`.
 *   - HTTP 200 once that count is exceeded.
 *
 * State survives across requests (the built-in server forks one process per
 * request) via two JSON files on disk. Override their location with env
 * vars set before starting `php -S`, so parallel journey runs on the same
 * machine don't collide:
 *
 *   WBL_RECEIVER_LOG    default: sys_get_temp_dir() . '/wbl-webhook-receiver-log.json'
 *   WBL_RECEIVER_STATE  default: sys_get_temp_dir() . '/wbl-webhook-receiver-state.json'
 *
 * @package WBListora\QA\Fixtures
 */

header( 'Content-Type: application/json' );

$log_file   = getenv( 'WBL_RECEIVER_LOG' ) ?: sys_get_temp_dir() . '/wbl-webhook-receiver-log.json';
$state_file = getenv( 'WBL_RECEIVER_STATE' ) ?: sys_get_temp_dir() . '/wbl-webhook-receiver-state.json';

/**
 * Read a JSON file into an array, tolerating a missing or unreadable file.
 *
 * @param string $path    File path.
 * @param array  $default Value to return when the file is absent or not
 *                         valid JSON.
 * @return array
 */
function wbl_receiver_read_json( $path, array $default ) {
	if ( ! is_file( $path ) ) {
		return $default;
	}
	$raw  = file_get_contents( $path );
	$data = json_decode( (string) $raw, true );
	return is_array( $data ) ? $data : $default;
}

/**
 * Write an array to disk as JSON.
 *
 * LOCK_EX matters here: retries in the ladder can arrive close together,
 * and a torn write would hand a journey unparsable JSON instead of a log.
 *
 * @param string $path Destination path.
 * @param array  $data Data to encode.
 * @return void
 */
function wbl_receiver_write_json( $path, array $data ) {
	file_put_contents( $path, (string) json_encode( $data, JSON_PRETTY_PRINT ), LOCK_EX );
}

/**
 * Every request header, normalized to a Title-Case name => value map.
 *
 * `getallheaders()` is available under the PHP built-in server (it is not
 * Apache-only from PHP 5.4 onward), but a manual `$_SERVER` reconstruction
 * is kept as a fallback so the fixture still captures headers under a SAPI
 * where it is absent, rather than silently recording an empty set.
 *
 * @return array<string, string>
 */
function wbl_receiver_request_headers() {
	if ( function_exists( 'getallheaders' ) ) {
		$headers = getallheaders();
		if ( is_array( $headers ) ) {
			return $headers;
		}
	}

	$headers = array();
	foreach ( $_SERVER as $key => $value ) {
		if ( 0 === strpos( $key, 'HTTP_' ) ) {
			$name             = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $key, 5 ) ) ) ) );
			$headers[ $name ] = $value;
		} elseif ( in_array( $key, array( 'CONTENT_TYPE', 'CONTENT_LENGTH' ), true ) ) {
			$name             = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', $key ) ) ) );
			$headers[ $name ] = $value;
		}
	}
	return $headers;
}

$control = isset( $_GET['_control'] ) ? (string) $_GET['_control'] : '';

if ( 'reset' === $control ) {
	$fail_first = isset( $_GET['fail_first'] ) ? max( 0, (int) $_GET['fail_first'] ) : 0;
	wbl_receiver_write_json( $log_file, array() );
	wbl_receiver_write_json(
		$state_file,
		array(
			'fail_first' => $fail_first,
			'received'   => 0,
		)
	);
	http_response_code( 200 );
	echo json_encode(
		array(
			'ok'         => true,
			'fail_first' => $fail_first,
		)
	);
	return;
}

if ( 'log' === $control ) {
	http_response_code( 200 );
	echo json_encode( wbl_receiver_read_json( $log_file, array() ) );
	return;
}

if ( 'count' === $control ) {
	$state = wbl_receiver_read_json(
		$state_file,
		array(
			'fail_first' => 0,
			'received'   => 0,
		)
	);
	http_response_code( 200 );
	echo json_encode( array( 'received' => (int) ( $state['received'] ?? 0 ) ) );
	return;
}

// Anything else is a real delivery.
$state             = wbl_receiver_read_json(
	$state_file,
	array(
		'fail_first' => 0,
		'received'   => 0,
	)
);
$state['received'] = (int) ( $state['received'] ?? 0 ) + 1;
$attempt_n         = $state['received'];

$will_fail = $attempt_n <= (int) ( $state['fail_first'] ?? 0 );

$record = array(
	'n'           => $attempt_n,
	'received_at' => gmdate( 'c' ),
	'method'      => isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '',
	'headers'     => wbl_receiver_request_headers(),
	'body'        => (string) file_get_contents( 'php://input' ),
	'responded'   => $will_fail ? 500 : 200,
);

$log   = wbl_receiver_read_json( $log_file, array() );
$log[] = $record;
wbl_receiver_write_json( $log_file, $log );
wbl_receiver_write_json( $state_file, $state );

if ( $will_fail ) {
	http_response_code( 500 );
	echo json_encode(
		array(
			'ok'      => false,
			'error'   => 'fixture: forced failure',
			'attempt' => $attempt_n,
		)
	);
} else {
	http_response_code( 200 );
	echo json_encode(
		array(
			'ok'      => true,
			'attempt' => $attempt_n,
		)
	);
}
