<?php
/**
 * Migration extension surface — public functions Pro consumes to
 * reach Free's competitor migrators. Pro never references the
 * \WBListora\ImportExport\Migration_Base subclasses directly (INV-3);
 * it calls these functions instead.
 *
 * @package WBListora
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_get_migrators' ) ) {
	/**
	 * Get every available competitor migrator instance.
	 *
	 * @since 1.1.0
	 * @return \WBListora\ImportExport\Migration_Base[]
	 */
	function wb_listora_get_migrators(): array {
		return \WBListora\ImportExport\Migration_Base::get_migrators();
	}
}

if ( ! function_exists( 'wb_listora_get_migrator' ) ) {
	/**
	 * Get a single migrator by source-plugin slug.
	 *
	 * @since 1.1.0
	 * @param string $source_slug e.g. 'directorist', 'geodirectory', 'bdp', 'listingpro', 'hivepress'.
	 * @return \WBListora\ImportExport\Migration_Base|null Null if no migrator matches.
	 */
	function wb_listora_get_migrator( string $source_slug ) {
		foreach ( wb_listora_get_migrators() as $migrator ) {
			if ( method_exists( $migrator, 'get_source_slug' ) && $source_slug === $migrator->get_source_slug() ) {
				return $migrator;
			}
		}
		return null;
	}
}
