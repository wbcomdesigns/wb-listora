<?php
/**
 * Webhook receiver fixture: an independent witness on the RECEIVING end, so
 * journeys need not trust Pro's own delivery log. Records method, headers
 * (incl. the HMAC signature) and raw body per request, and can answer 500 for
 * the first N deliveries to exercise the retry ladder and give-up.
 * Run from the Free plugin root (it routes every path): `php -S 127.0.0.1:8955 docs/qa/fixtures/webhook-receiver.php`
 *   php -S 127.0.0.1:8955 docs/qa/fixtures/webhook-receiver.php
 *
 * Control: GET /?_control=reset[&fail_first=N] | log | count (never logged).
 * Anything else is a delivery: logged, then 500 while the count since reset
 * is <= fail_first, else 200. State lives in WBL_RECEIVER_LOG /
 * WBL_RECEIVER_STATE (default: sys_get_temp_dir() JSON files).
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
