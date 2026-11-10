<?php
/**
 * Built-in listing type text, shown in the visitor's language.
 *
 * Stored in English at install, translated at read time only while it still equals a
 * built-in default. The pairs come from building the defaults in English and in the
 * current language, so a new default needs no extra code.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Default_Text class.
 */
class Default_Text {

	/**
	 * Keys that hold text a person reads (the only ones ever swapped).
	 *
	 * @var string[]
	 */
	const TEXT_KEYS = array( 'name', 'label', 'description', 'placeholder', 'range_label' );

	/**
	 * English => current-language pairs, per locale.
	 *
	 * @var array<string, array<string,string>>
	 */
	private static $maps = array();

	/**
	 * Translate a stored value if it is a built-in English default.
	 *
	 * @param mixed $text Stored value.
	 * @return mixed Translated text, or the value unchanged.
	 */
	public static function translate( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return $text;
		}
		$map = self::map();
		return $map[ $text ] ?? $text;
	}

	/**
	 * Undo translate(): a translated default goes back to its English source.
	 *
	 * Run before saving, so an editor that was shown Dutch labels does not
	 * freeze them into the database as if the owner had typed them.
	 *
	 * @param mixed $text Value about to be stored.
	 * @return mixed
	 */
	public static function to_source( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return $text;
		}
		$flipped = array_flip( self::map() );
		return $flipped[ $text ] ?? $text;
	}

	/**
	 * Translate every text key (and option label) in a nested array.
	 *
	 * @param array<int|string, mixed> $data    Field group, field or type data.
	 * @param bool                     $to_text True to translate, false to restore the English source.
	 * @return array<int|string, mixed>
	 */
	public static function walk( array $data, bool $to_text = true ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::walk( $value, $to_text );
			} elseif ( is_string( $key ) && in_array( $key, self::TEXT_KEYS, true ) ) {
				$data[ $key ] = $to_text ? self::translate( $value ) : self::to_source( $value );
			}
		}
		return $data;
	}

	/**
	 * Pairs for the current locale. Empty for English and before init, so
	 * nothing is translated while the plugin boots.
	 *
	 * @return array<string,string>
	 */
	private static function map(): array {
		if ( ! did_action( 'init' ) ) {
			return array();
		}
		$locale = determine_locale();
		if ( 0 === strpos( $locale, 'en_' ) ) {
			return array();
		}
		if ( ! isset( self::$maps[ $locale ] ) ) {
			$current = Listing_Type_Defaults::get_all();
			$switched = switch_to_locale( 'en_US' );
			$english  = Listing_Type_Defaults::get_all();
			if ( $switched ) {
				restore_previous_locale();
			}
			$pairs = array();
			self::pair( $english, $current, $pairs );
			self::$maps[ $locale ] = $pairs;
		}
		return self::$maps[ $locale ];
	}

	/**
	 * Collect every string that differs between the two builds of the defaults.
	 *
	 * @param mixed $english Defaults built in English.
	 * @param mixed $current Defaults built in the current language.
	 * @param array<string, string> $pairs   Output, English => translated.
	 */
	private static function pair( $english, $current, array &$pairs ): void {
		if ( is_array( $english ) && is_array( $current ) ) {
			foreach ( $english as $key => $value ) {
				if ( array_key_exists( $key, $current ) ) {
					self::pair( $value, $current[ $key ], $pairs );
				}
			}
		} elseif ( is_string( $english ) && is_string( $current ) && $english !== $current && ! isset( $pairs[ $english ] ) ) {
			$pairs[ $english ] = $current;
		}
	}
}
