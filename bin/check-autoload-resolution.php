<?php
/**
 * Autoloader-resolution contract check.
 *
 * Verifies that every `class_exists( '\WBListora\...' )` literal in the source
 * resolves to a file the runtime autoloader can actually load. This catches the
 * "class lives outside the autoload scope" foot-gun: Demo_Seeder lived under
 * `demo/` while wb_listora_autoload() only mapped `includes/`, so class_exists()
 * returned false on the settings page render AND the AJAX remover — the
 * Delete-Demo-Data button stayed permanently disabled and the remover returned
 * 500, even though the class file shipped fine (card 10020109923). A green
 * `php -l` and a passing unit test never caught it because the class is only
 * resolved at runtime.
 *
 * This MUST mirror wb_listora_autoload() in wb-listora.php — keep the two in
 * sync (base-dir map + Demo namespace root + irregular-filename aliases).
 *
 * Exit 0 = every reference resolves. Exit 1 = at least one points at a missing
 * file (prints the class, the expected path, and where it is referenced).
 *
 * @package WBListora
 */

$plugin_dir = dirname( __DIR__ ) . '/';

// Irregular-filename aliases — must match the $aliases map in wb_listora_autoload().
$aliases = array(
	'WBListora\\ImportExport\\GeoJSON_Importer' => 'includes/import-export/class-geojson-importer.php',
);

/**
 * Resolve a fully-qualified WBListora class name to its file path using the
 * exact rule wb_listora_autoload() applies.
 *
 * @param string $class      Fully-qualified class name (no leading backslash).
 * @param string $plugin_dir Plugin root with trailing slash.
 * @param array  $aliases    Irregular-filename alias map.
 * @return string|null Absolute path, or null if the class is not in our namespace.
 */
function wb_listora_resolve_autoload_path( $class, $plugin_dir, $aliases ) {
	$ns = 'WBListora\\';
	if ( 0 !== strpos( $class, $ns ) ) {
		return null;
	}
	if ( isset( $aliases[ $class ] ) ) {
		return $plugin_dir . $aliases[ $class ];
	}

	$relative = substr( $class, strlen( $ns ) );
	$parts    = explode( '\\', $relative );
	$class_f  = array_pop( $parts );

	$class_f = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_f ) );
	$class_f = str_replace( '_', '-', $class_f );
	$class_f = 'class-' . $class_f . '.php';

	$subdir = '';
	if ( ! empty( $parts ) ) {
		$sp     = array_map(
			static function ( $p ) {
				$p = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $p );
				return strtolower( str_replace( '_', '-', $p ) );
			},
			$parts
		);
		$subdir = implode( '/', $sp ) . '/';
	}

	// Base-dir map — MUST match wb_listora_autoload(): the `demo/` directory is
	// a top-level namespace root, everything else lives under includes/.
	$base = $plugin_dir . 'includes/';
	if ( isset( $parts[0] ) && 'Demo' === $parts[0] ) {
		$base = $plugin_dir;
	}

	return $base . $subdir . $class_f;
}

// Collect candidate source files.
$files = array( $plugin_dir . 'wb-listora.php' );
foreach ( array( 'includes', 'demo', 'blocks', 'templates' ) as $dir ) {
	$root = $plugin_dir . $dir;
	if ( ! is_dir( $root ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && '.php' === substr( $f->getFilename(), -4 ) ) {
			$files[] = $f->getPathname();
		}
	}
}

// Extract class_exists( '\WBListora\...' ) / class_exists( "\\WBListora\\..." ) literals.
$refs = array();
foreach ( $files as $file ) {
	$src = file_get_contents( $file );
	if ( false === $src ) {
		continue;
	}
	if ( preg_match_all( '/class_exists\(\s*[\'"]\\\\?(WBListora(?:\\\\+[A-Za-z0-9_]+)+)[\'"]/', $src, $m ) ) {
		foreach ( $m[1] as $raw ) {
			// Collapse single/double backslash literals to a single separator.
			$class = preg_replace( '/\\\\+/', '\\', $raw );
			$refs[ $class ][ $file ] = true;
		}
	}
}

$bad = array();
foreach ( $refs as $class => $where ) {
	$path = wb_listora_resolve_autoload_path( $class, $plugin_dir, $aliases );
	if ( null === $path ) {
		continue;
	}
	if ( ! file_exists( $path ) ) {
		$bad[ $class ] = array(
			'path'  => $path,
			'where' => array_keys( $where ),
		);
	}
}

if ( ! empty( $bad ) ) {
	foreach ( $bad as $class => $info ) {
		fwrite( STDERR, "  {$class}\n" );
		fwrite( STDERR, '    expected file (MISSING): ' . str_replace( $plugin_dir, '', $info['path'] ) . "\n" );
		foreach ( $info['where'] as $w ) {
			fwrite( STDERR, '    referenced in: ' . str_replace( $plugin_dir, '', $w ) . "\n" );
		}
	}
	fwrite( STDERR, "The class is referenced via class_exists() but the autoloader cannot find it.\n" );
	fwrite( STDERR, "Either move the file under an autoload root or extend wb_listora_autoload()'s base-dir map.\n" );
	exit( 1 );
}

echo 'OK: ' . count( $refs ) . " class_exists('\\WBListora\\...') references all resolve under the autoloader roots\n";
exit( 0 );
