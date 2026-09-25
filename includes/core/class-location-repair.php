<?php
/**
 * One-off repair of duplicate location terms.
 *
 * Before 1.9.0 each writer created the country from whatever text the
 * address carried, so a site could hold "US" (the demo), "United States" (the
 * geocoder) and "USA" (a CSV) as three country roots, each with its own copy
 * of the states and cities below it (card 10337180588). This merges them:
 * one root per country (the most-used one, with its readable name and ISO
 * code), and below it one term per state and city, with every listing moved
 * across. Runs once from the 1.9.0 migration; safe to run again.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Location_Repair
 *
 * @since 1.9.0
 */
class Location_Repair {

	const TAXONOMY = 'listora_listing_location';

	/**
	 * Listings whose terms moved, for the cache clean-up at the end.
	 *
	 * @var int[]
	 */
	private static $touched = array();

	/**
	 * Merge duplicate country roots and their duplicate children.
	 *
	 * @return array{countries:int, merged:int} Countries tagged, terms merged away.
	 */
	public static function run() {
		self::$touched = array();
		$merged        = 0;
		$tagged        = 0;

		$roots = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'parent'     => 0,
			)
		);
		if ( is_wp_error( $roots ) ) {
			return array(
				'countries' => 0,
				'merged'    => 0,
			);
		}

		$by_code = array();
		foreach ( $roots as $root ) {
			$code = (string) get_term_meta( $root->term_id, Countries::TERM_META, true );
			$code = '' !== $code ? $code : Countries::code_for( $root->name );
			if ( '' !== $code ) {
				$by_code[ $code ][] = $root;
			}
		}

		foreach ( $by_code as $code => $terms ) {
			// Keep the term most listings use; on a tie, the one already
			// carrying the readable name.
			usort(
				$terms,
				static function ( $a, $b ) use ( $code ) {
					if ( (int) $a->count !== (int) $b->count ) {
						return (int) $b->count - (int) $a->count;
					}
					return ( Countries::name( $code ) === $b->name ) <=> ( Countries::name( $code ) === $a->name );
				}
			);
			$keep = array_shift( $terms );
			update_term_meta( $keep->term_id, Countries::TERM_META, $code );
			++$tagged;
			if ( Countries::code_for( $keep->name ) === $code && Countries::name( $code ) !== $keep->name ) {
				// "US" becomes "United States"; the slug stays, so links do.
				wp_update_term( $keep->term_id, self::TAXONOMY, array( 'name' => Countries::name( $code ) ) );
			}
			foreach ( $terms as $duplicate ) {
				$merged += self::merge( (int) $duplicate->term_id, (int) $keep->term_id, $code, 1 );
			}
			$merged += self::dedupe_children( (int) $keep->term_id, $code, 1 );
		}

		self::finish();

		return array(
			'countries' => $tagged,
			'merged'    => $merged,
		);
	}

	/**
	 * The name two sibling terms are compared by: states are expanded
	 * ("NY" is "New York" in the US), everything is case-insensitive.
	 *
	 * @param string $name  Term name.
	 * @param string $code  Country code.
	 * @param int    $depth 1 for states, 2+ for cities.
	 * @return string
	 */
	private static function key( $name, $code, $depth ) {
		$name = 1 === $depth ? Countries::state_name( $name, $code ) : $name;
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $name ) ) : strtolower( trim( $name ) );
	}

	/**
	 * Merge same-named children of a term into one each, and expand state
	 * abbreviations where nothing collides.
	 *
	 * @param int    $parent Parent term ID.
	 * @param string $code   Country code.
	 * @param int    $depth  Depth of the children (1 = states).
	 * @return int Terms merged away.
	 */
	private static function dedupe_children( $parent, $code, $depth ) {
		$merged   = 0;
		$children = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'parent'     => $parent,
			)
		);
		if ( is_wp_error( $children ) ) {
			return 0;
		}
		$seen = array();
		foreach ( $children as $child ) {
			$key = self::key( $child->name, $code, $depth );
			if ( isset( $seen[ $key ] ) ) {
				$merged += self::merge( (int) $child->term_id, (int) $seen[ $key ]->term_id, $code, $depth + 1 );
				continue;
			}
			$seen[ $key ] = $child;
			if ( 1 === $depth && Countries::state_name( $child->name, $code ) !== $child->name ) {
				wp_update_term( $child->term_id, self::TAXONOMY, array( 'name' => Countries::state_name( $child->name, $code ) ) );
			}
		}
		foreach ( $seen as $child ) {
			$merged += self::dedupe_children( (int) $child->term_id, $code, $depth + 1 );
		}
		return $merged;
	}

	/**
	 * Merge one term into another: children join the target (merging with
	 * a same-named child there), listings move, the source is deleted.
	 *
	 * @param int    $from  Term merged away.
	 * @param int    $into  Term that stays.
	 * @param string $code  Country code.
	 * @param int    $depth Depth of $from's children.
	 * @return int Terms merged away, this one included.
	 */
	private static function merge( $from, $into, $code, $depth ) {
		global $wpdb;
		$merged = 0;

		$targets = array();
		$kids    = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'parent'     => $into,
			)
		);
		foreach ( is_wp_error( $kids ) ? array() : $kids as $kid ) {
			$targets[ self::key( $kid->name, $code, $depth ) ] = (int) $kid->term_id;
		}

		$children = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'parent'     => $from,
			)
		);
		foreach ( is_wp_error( $children ) ? array() : $children as $child ) {
			$key = self::key( $child->name, $code, $depth );
			if ( isset( $targets[ $key ] ) ) {
				$merged += self::merge( (int) $child->term_id, $targets[ $key ], $code, $depth + 1 );
			} else {
				wp_update_term( $child->term_id, self::TAXONOMY, array( 'parent' => $into ) );
				$targets[ $key ] = (int) $child->term_id;
			}
		}

		$from_term = get_term( $from, self::TAXONOMY );
		$into_term = get_term( $into, self::TAXONOMY );
		if ( $from_term instanceof \WP_Term && $into_term instanceof \WP_Term ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk move; caches cleared in finish().
			$ids           = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $from_term->term_taxonomy_id ) ) );
			self::$touched = array_merge( self::$touched, $ids );
			// IGNORE: a listing already on the target keeps one row.
			$wpdb->query( $wpdb->prepare( "UPDATE IGNORE {$wpdb->term_relationships} SET term_taxonomy_id = %d WHERE term_taxonomy_id = %d", $into_term->term_taxonomy_id, $from_term->term_taxonomy_id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $from_term->term_taxonomy_id ) );
			// phpcs:enable
		}
		wp_delete_term( $from, self::TAXONOMY );

		return $merged + 1;
	}

	/**
	 * Recount every location term and clear the caches the bulk moves
	 * bypassed.
	 */
	private static function finish() {
		$tt_ids = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'tt_ids',
			)
		);
		if ( ! is_wp_error( $tt_ids ) && $tt_ids ) {
			wp_update_term_count_now( array_map( 'intval', $tt_ids ), self::TAXONOMY );
		}
		foreach ( array_chunk( array_values( array_unique( self::$touched ) ), 500 ) as $chunk ) {
			clean_object_term_cache( $chunk, 'listora_listing' );
		}
		clean_taxonomy_cache( self::TAXONOMY );
	}
}
