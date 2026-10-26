<?php
// phpcs:ignoreFile -- vendored dev tool (synced from the portfolio skill), not shipped in the release zip.
/**
 * core-first-check.php - fail when a plugin re-implements what WordPress core already offers.
 *
 * Standard: ~/.claude/workflows/wbcom-core-first-standard.md (each plugin keeps a synced copy
 * at docs/standards/core-first.md). Only checks WPCS does not already cover (WPCS flags
 * curl_*, file_get_contents, date(), json_encode, rand ... via WordPress.WP.AlternativeFunctions).
 *
 *   CF1  plugin cache wrapper class (remember()/get_or_set() around wp_cache_* or transients)
 *   CF2  rows from the plugin's own tables cached in a transient (use wp_cache_* + last_changed)
 *   CF3  transient used as a cache version counter (use wp_cache_set_last_changed())
 *   CF4  wp_cache_flush_group() without a wp_cache_supports( 'flush_group' ) guard
 *   CF5  wp_cache_flush() - wipes the whole site's object cache
 *   CF6  PHP sessions (session_start / $_SESSION) - use user meta, transients or cookies
 *   CF7  bare mail() - use wp_mail()
 *
 * Usage:  php core-first-check.php [plugin-dir]
 * Allow:  <plugin-dir>/.core-first-allow, one "CFn path/fragment  # reason" per line; a finding
 *         is allowed when its check id matches and its file path contains the fragment.
 * Exit:   0 clean, 1 findings.
 */

$dir = rtrim( $argv[1] ?? '.', '/' );
if ( ! is_dir( $dir ) ) {
	fwrite( STDERR, "no such dir: $dir\n" );
	exit( 1 );
}

$allow = array();
if ( is_file( "$dir/.core-first-allow" ) ) {
	foreach ( file( "$dir/.core-first-allow", FILE_IGNORE_NEW_LINES ) as $line ) {
		$line = trim( preg_replace( '/#.*/', '', $line ) );
		if ( preg_match( '/^(CF\d+)\s+(\S+)/', $line, $m ) ) {
			$allow[] = array( $m[1], $m[2] );
		}
	}
}

$skip  = '#/(vendor|node_modules|libs|lib|tests?|bin|tools|dist|build|\.git)/#';
$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
$found = array();

$report = static function ( $id, $file, $line, $msg ) use ( &$found, $allow, $dir ) {
	$rel = substr( $file, strlen( $dir ) + 1 );
	foreach ( $allow as $a ) {
		if ( $a[0] === $id && false !== strpos( $rel, $a[1] ) ) {
			return;
		}
	}
	$found[] = sprintf( '%-4s %s:%d  %s', $id, $rel, $line, $msg );
};

/**
 * Split a token stream into function bodies: [ name, start line, body source ].
 */
$functions = static function ( array $tokens ) {
	$out = array();
	$n   = count( $tokens );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || T_FUNCTION !== $tokens[ $i ][0] ) {
			continue;
		}
		$name = '';
		$line = $tokens[ $i ][2];
		for ( $j = $i + 1; $j < $n && '{' !== $tokens[ $j ] && ';' !== $tokens[ $j ]; $j++ ) {
			if ( '' === $name && is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
				$name = $tokens[ $j ][1];
			}
		}
		if ( $j >= $n || ';' === $tokens[ $j ] ) {
			continue; // Abstract / interface method.
		}
		$depth = 0;
		$body  = '';
		for ( $k = $j; $k < $n; $k++ ) {
			$t     = $tokens[ $k ];
			$text  = is_array( $t ) ? $t[1] : $t;
			$body .= $text;
			if ( '{' === $t || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				++$depth;
			} elseif ( '}' === $t && 0 === --$depth ) {
				break;
			}
		}
		$out[] = array( $name, $line, $body );
	}
	return $out;
};

foreach ( $files as $file ) {
	$path = $file->getPathname();
	if ( 'php' !== $file->getExtension() || preg_match( $skip, substr( $path, strlen( $dir ) ) ) ) {
		continue;
	}
	$src    = file_get_contents( $path );
	$tokens = token_get_all( $src );

	// Code only (comments and strings dropped) for the call checks, with line numbers.
	foreach ( $tokens as $idx => $t ) {
		if ( ! is_array( $t ) || T_STRING !== $t[0] ) {
			if ( is_array( $t ) && T_VARIABLE === $t[0] && '$_SESSION' === $t[1] ) {
				$report( 'CF6', $path, $t[2], '$_SESSION - PHP sessions bypass WordPress (page cache, multi-server); use user meta, transients or a cookie' );
			}
			continue;
		}
		$next = $tokens[ $idx + 1 ] ?? null;
		$prev = $tokens[ $idx - 1 ] ?? null;
		$is_call     = '(' === $next;
		$is_function = ! ( is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW ), true ) );
		if ( ! $is_call || ! $is_function ) {
			continue;
		}
		switch ( strtolower( $t[1] ) ) {
			case 'wp_cache_flush':
				$report( 'CF5', $path, $t[2], 'wp_cache_flush() empties the whole site object cache; move your group token with wp_cache_set_last_changed()' );
				break;
			case 'session_start':
				$report( 'CF6', $path, $t[2], 'session_start() - PHP sessions bypass WordPress; use user meta, transients or a cookie' );
				break;
			case 'mail':
				$report( 'CF7', $path, $t[2], 'mail() - use wp_mail() (filters, SMTP plugins, logging)' );
				break;
		}
	}

	foreach ( $functions( $tokens ) as list( $name, $line, $body ) ) {
		$code = preg_replace( array( '#//[^\n]*#', '#/\*.*?\*/#s' ), '', $body );
		if ( in_array( strtolower( $name ), array( 'remember', 'get_or_set' ), true ) && preg_match( '/\b(wp_cache_get|get_transient|get_site_transient)\s*\(/', $code ) ) {
			$report( 'CF1', $path, $line, "$name() wraps the object cache; call wp_cache_get()/wp_cache_set() with a last_changed key directly" );
		}
		// The transient's value is a query result variable (guards / flags next to a query are fine).
		if ( preg_match_all( '/(\$\w+)\s*=\s*\$wpdb\s*->\s*get_(?:results|row|col|var)\s*\(/', $code, $vars ) ) {
			foreach ( array_unique( $vars[1] ) as $var ) {
				if ( preg_match( '/\bset_(site_)?transient\s*\([^,]+,\s*' . preg_quote( $var, '/' ) . '\b/', $code ) ) {
					$report( 'CF2', $path, $line, "$name() caches own-table rows ($var) in a transient; use wp_cache_get/set keyed on wp_cache_get_last_changed( group ) and move the token on write" );
					break;
				}
			}
		}
		// Rate-limit counters (+1) are a legitimate transient; a version-named key is not.
		if ( preg_match( '/\bset_(site_)?transient\s*\(\s*[^,]*(_ver_|_ver\b|version)[^,]*,/i', $code ) ) {
			$report( 'CF3', $path, $line, "$name() keeps a cache version in a transient; use wp_cache_set_last_changed() / wp_cache_get_last_changed()" );
		}
		if ( preg_match( '/\bwp_cache_flush_group\s*\(/', $code ) && ! preg_match( "/wp_cache_supports\s*\(\s*['\"]flush_group['\"]\s*\)/", $code ) ) {
			$report( 'CF4', $path, $line, "$name() calls wp_cache_flush_group() unguarded; many backends cannot flush a group - move a last_changed token, or guard with wp_cache_supports( 'flush_group' )" );
		}
	}
}

$found = array_unique( $found );
if ( ! $found ) {
	echo "core-first: clean\n";
	exit( 0 );
}
echo "core-first: " . count( $found ) . " finding(s) - see ~/.claude/workflows/wbcom-core-first-standard.md\n";
foreach ( $found as $f ) {
	echo "  $f\n";
}
exit( 1 );
