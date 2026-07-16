<?php
/**
 * bin/check-erasure-map.php — coding-rules Rule 9 detector.
 *
 * FAILS THE BUILD when a `listora_*` table with a column pointing at a
 * WordPress user has no entry in the plugin-level erasure map
 * (`includes/privacy/privacy-helpers.php`, plus Pro's rows in
 * `../wb-listora-pro/includes/privacy/pro-privacy-helpers.php`).
 *
 * WHY THIS EXISTS
 * ---------------
 * A GDPR policy map without a gate is a comment. It is correct on the day it is
 * written and silently wrong the first time someone adds a table with a
 * `user_id` column and doesn't think about erasure — which is exactly how the
 * pre-1.2.3 coverage gap happened: 8 tables carrying a user pointer, 4 of them
 * handled. Nobody was careless; there was just nothing that could notice.
 *
 * This gate makes forgetting impossible: add a table with a user column, and the
 * build fails until you have made an explicit, documented decision about what
 * happens to that member's rows. `retain` is a perfectly good answer — but it
 * has to be a decision someone wrote down, not an omission.
 *
 * HOW IT WORKS
 * ------------
 * Static only — no database, no WordPress bootstrap, so it runs in local-CI on a
 * fresh clone before WP is even installed:
 *
 *   1. Parse `CREATE TABLE {$prefix}NAME ( ... )` blocks out of the two DDL
 *      sources (Free's Activator, Pro's Pro_Migrator).
 *   2. Flag every column that names a WordPress user.
 *   3. Require each such table to be in the map, and each such column to be
 *      declared in that entry's `user_columns`.
 *
 * The map files are pure data by construction, which is what lets this script
 * `require` them with nothing but an `ABSPATH` stub.
 *
 * KNOWN LIMIT (deliberate)
 * ------------------------
 * SDK-owned tables (`credit_ledger`, `credit_gateway_log`) are NOT scanned. The
 * Wbcom Credits SDK builds its table names through a variable
 * (`$wpdb->prefix . $prefix . '_credit_ledger'`), which this parser cannot
 * resolve statically. They are pre-declared in the map with `owner => sdk`, and
 * map entries without matching DDL are allowed for exactly that reason. The
 * drift risk this gate targets is Listora's OWN tables — a vendored third-party
 * SDK does not grow a new user column behind our backs.
 *
 * Usage: php bin/check-erasure-map.php   (exit 0 = pass, 1 = gap found)
 *
 * @package WBListora
 * @since   1.2.3
 */

// The map files guard on ABSPATH (they are WordPress runtime files). Stub it so
// they can be required standalone. Nothing else about WordPress is loaded.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$free_dir = dirname( __DIR__ );
$pro_dir  = dirname( $free_dir ) . '/wb-listora-pro';

/**
 * Column names that point at a WordPress user.
 *
 * Any column matching these is personal-data-adjacent by definition: it links a
 * row to a natural person. Broad on purpose — a false positive costs one map
 * entry and thirty seconds; a false negative ships a compliance gap.
 */
const USER_COLUMN_PATTERNS = array(
	'/^user_id$/',
	'/^author_id$/',
	'/^owner_id$/',
	'/^member_id$/',
	'/^customer_id$/',
	'/^moderator_id$/',
	'/_by$/',
);

/**
 * Whether a column name references a WordPress user.
 *
 * @param string $column Column name.
 * @return bool
 */
function wbl_is_user_column( string $column ): bool {
	foreach ( USER_COLUMN_PATTERNS as $pattern ) {
		if ( preg_match( $pattern, $column ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Extract table => [columns] from a PHP file containing dbDelta CREATE TABLE DDL.
 *
 * @param string $file Absolute path to a DDL-bearing PHP file.
 * @return array<string, array<int, string>> Columns keyed by table short-name.
 */
function wbl_parse_ddl( string $file ): array {
	$tables = array();

	if ( ! is_readable( $file ) ) {
		return $tables;
	}

	$source = (string) file_get_contents( $file );

	// Match: CREATE TABLE {$prefix}table_name ( ...body... ) ENGINE|charset|;
	if ( ! preg_match_all(
		'/CREATE\s+TABLE\s+\{\$prefix\}(\w+)\s*\((.*?)\)\s*(?:ENGINE|\{\$charset_collate\}|;)/is',
		$source,
		$matches,
		PREG_SET_ORDER
	) ) {
		return $tables;
	}

	foreach ( $matches as $match ) {
		$table = $match[1];
		$body  = $match[2];
		$cols  = array();

		foreach ( preg_split( '/,\s*\n/', $body ) as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			// Skip key/index/constraint definitions — not columns.
			if ( preg_match( '/^(PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|INDEX|CONSTRAINT|FULLTEXT)/i', $line ) ) {
				continue;
			}

			if ( preg_match( '/^([a-zA-Z_][a-zA-Z0-9_]*)\s+/', $line, $col ) ) {
				$cols[] = $col[1];
			}
		}

		// A table can be declared in both plugins (payments is created by
		// whichever activates first); union the columns rather than clobbering.
		$tables[ $table ] = isset( $tables[ $table ] )
			? array_values( array_unique( array_merge( $tables[ $table ], $cols ) ) )
			: $cols;
	}

	return $tables;
}

// ---------------------------------------------------------------------------
// 1. Load the map (Free's rows, plus Pro's if the companion is checked out).
// ---------------------------------------------------------------------------

$helpers = $free_dir . '/includes/privacy/privacy-helpers.php';

if ( ! is_readable( $helpers ) ) {
	fwrite( STDERR, "Erasure map helpers not found at {$helpers}\n" );
	exit( 1 );
}

require_once $helpers;

if ( ! function_exists( 'wb_listora_get_erasure_map_defaults' ) ) {
	fwrite( STDERR, "wb_listora_get_erasure_map_defaults() not defined — the map must stay pure data.\n" );
	exit( 1 );
}

$map          = wb_listora_get_erasure_map_defaults();
$pro_present  = false;
$pro_helpers  = $pro_dir . '/includes/privacy/pro-privacy-helpers.php';

if ( is_readable( $pro_helpers ) ) {
	require_once $pro_helpers;

	if ( function_exists( 'wb_listora_pro_get_erasure_map_defaults' ) ) {
		$map         = array_merge( $map, wb_listora_pro_get_erasure_map_defaults() );
		$pro_present = true;
	}
}

// ---------------------------------------------------------------------------
// 2. Parse every DDL source we can resolve statically.
// ---------------------------------------------------------------------------

$ddl = wbl_parse_ddl( $free_dir . '/includes/class-activator.php' );

if ( $pro_present ) {
	foreach ( wbl_parse_ddl( $pro_dir . '/includes/db/class-pro-migrator.php' ) as $table => $cols ) {
		$ddl[ $table ] = isset( $ddl[ $table ] )
			? array_values( array_unique( array_merge( $ddl[ $table ], $cols ) ) )
			: $cols;
	}
}

if ( empty( $ddl ) ) {
	fwrite( STDERR, "Parsed zero CREATE TABLE blocks — the DDL parser is broken, not the map.\n" );
	exit( 1 );
}

// ---------------------------------------------------------------------------
// 3. Every table with a user column must be mapped, column by column.
// ---------------------------------------------------------------------------

$errors  = array();
$checked = 0;

foreach ( $ddl as $table => $columns ) {
	$user_columns = array_values( array_filter( $columns, 'wbl_is_user_column' ) );

	if ( empty( $user_columns ) ) {
		continue;
	}

	++$checked;

	if ( ! isset( $map[ $table ] ) ) {
		$errors[] = sprintf(
			"Table `%s` has user column(s) [%s] but NO entry in the erasure map.\n"
			. "      Add an entry to %s\n"
			. "      declaring what happens to a member's rows on BOTH paths:\n"
			. "        - on_account_deletion (wp_delete_user() runs; the user row is destroyed)\n"
			. "        - on_privacy_erasure  (WP's Erase Personal Data tool; the user row SURVIVES)\n"
			. "      `retain` is a valid answer — but it must be a written decision with a reason.",
			$table,
			implode( ', ', $user_columns ),
			( 'pro' === ( $map[ $table ]['owner'] ?? '' ) ) ? 'wb-listora-pro/includes/privacy/pro-privacy-helpers.php' : 'wb-listora/includes/privacy/privacy-helpers.php'
		);
		continue;
	}

	$entry    = $map[ $table ];
	$declared = array_merge(
		(array) ( $entry['user_columns'] ?? array() ),
		array_keys( (array) ( $entry['secondary_user_columns'] ?? array() ) )
	);

	foreach ( $user_columns as $column ) {
		if ( ! in_array( $column, $declared, true ) ) {
			$errors[] = sprintf(
				"Table `%s` column `%s` points at a WP user but is NOT declared in the map entry.\n"
				. "      Add it to `user_columns` (if it identifies the data subject) or to\n"
				. "      `secondary_user_columns` (if it points at somebody else — e.g. a moderator —\n"
				. "      in which case erasure must never match on it).",
				$table,
				$column
			);
		}
	}

	// Both paths must be answered, with a reason. An entry that exists but says
	// nothing is the same gap wearing a disguise.
	foreach ( array( 'on_account_deletion', 'on_privacy_erasure' ) as $path ) {
		if ( ! isset( $entry[ $path ]['strategy'] ) ) {
			$errors[] = sprintf( "Table `%s` map entry is missing a `%s` strategy.", $table, $path );
			continue;
		}

		$strategy = $entry[ $path ]['strategy'];

		if ( ! in_array( $strategy, array( 'anonymize', 'delete', 'retain' ), true ) ) {
			$errors[] = sprintf( "Table `%s` has an unknown `%s` strategy: `%s`.", $table, $path, $strategy );
		}

		if ( empty( $entry[ $path ]['reason'] ) ) {
			$errors[] = sprintf(
				"Table `%s` `%s` has no `reason`. The reason IS the deliverable —\n"
				. "      it is what a DPO or reviewer reads to justify the decision.",
				$table,
				$path
			);
		}

		// anonymize must actually name the columns it strips, unless another
		// component owns the write.
		if ( 'anonymize' === $strategy
			&& 'account_eraser' === ( $entry[ $path ]['handled_by'] ?? '' )
			&& empty( $entry[ $path ]['columns'] )
		) {
			$errors[] = sprintf(
				"Table `%s` `%s` is `anonymize` via account_eraser but declares no `columns` to strip —\n"
				. "      it would run and change nothing.",
				$table,
				$path
			);
		}
	}
}

if ( ! empty( $errors ) ) {
	foreach ( $errors as $error ) {
		fwrite( STDERR, '  - ' . $error . "\n" );
	}
	exit( 1 );
}

printf(
	"Erasure map covers all %d table(s) with user columns (%s).%s",
	$checked,
	$pro_present ? 'Free + Pro' : 'Free only — Pro not checked out',
	PHP_EOL
);
exit( 0 );
