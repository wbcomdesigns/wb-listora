<?php
/**
 * Automation schema loader.
 *
 * @package WBListora\Automation
 */

namespace WBListora\Automation;

defined( 'ABSPATH' ) || exit;

/**
 * Reads trigger payload schemas from disk.
 */
class Schema_Loader {

	/**
	 * Absolute path to Free's schema directory, with a trailing slash.
	 *
	 * @return string
	 */
	public static function dir() {
		return WB_LISTORA_PLUGIN_DIR . 'includes/automation/schemas/';
	}

	/**
	 * Every directory a schema may live in, in search order.
	 *
	 * Pro declares triggers into Free's registry and ships their schemas in
	 * its own tree — it cannot write into Free's plugin directory, and a Pro
	 * schema landing in Free would be deleted by the next Free update. Pro
	 * appends its own directory here.
	 *
	 * Free's own directory is always first and cannot be displaced, so an
	 * add-on cannot shadow a Free event's schema with its own.
	 *
	 * @since 1.7.0
	 *
	 * @return string[] Absolute paths, each with a trailing slash.
	 */
	public static function dirs() {
		/**
		 * Filters the directories searched for trigger payload schemas.
		 *
		 * @since 1.7.0
		 *
		 * @param string[] $dirs Absolute paths, trailing slash. Free's is first.
		 */
		$dirs = apply_filters( 'wb_listora_automation_schema_dirs', array( self::dir() ) );

		$resolved = array( self::dir() );

		foreach ( (array) $dirs as $dir ) {
			$dir = trailingslashit( (string) $dir );
			if ( '' !== $dir && ! in_array( $dir, $resolved, true ) && is_dir( $dir ) ) {
				$resolved[] = $dir;
			}
		}

		return $resolved;
	}

	/**
	 * Resolve a schema filename to an absolute path inside the schema dir.
	 *
	 * Returns an empty string for anything that is not a bare filename. The
	 * name arrives from a registry entry and Pro can populate the registry,
	 * so a traversal is reachable from another plugin's code — cheap to
	 * refuse, expensive to discover the hard way.
	 *
	 * @param string $file Schema filename.
	 * @return string Absolute path, or '' when the name is not acceptable.
	 */
	public static function path( $file ) {
		$file = (string) $file;

		if ( '' === $file || basename( $file ) !== $file ) {
			return '';
		}

		if ( ! preg_match( '/^[a-z0-9_]+\.v[0-9]+\.json$/', $file ) ) {
			return '';
		}

		foreach ( self::dirs() as $dir ) {
			if ( is_readable( $dir . $file ) ) {
				return $dir . $file;
			}
		}

		return '';
	}

	/**
	 * Load and decode a schema.
	 *
	 * @param string $file Schema filename.
	 * @return array<string, mixed>|null Decoded schema, or null when missing or unreadable.
	 */
	public static function load( $file ) {
		$path = self::path( $file );

		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file, not a remote fetch.

		return is_array( $decoded ) ? $decoded : null;
	}
}
