<?php
/**
 * Ledger notes in the reader's language.
 *
 * @package Wbcom\Credits
 * @since   1.11.0
 */

declare( strict_types=1 );

namespace Wbcom\Credits;

defined( 'ABSPATH' ) || exit;

/**
 * Ledger notes are stored once, in English, and translated when read.
 *
 * The ledger is append-only, so a note translated when it was written stays
 * in that one language for good: a German member read "Job Posting — credits
 * held", and a site that changed language kept every older note in the old
 * one. The SDK now writes its notes in English (a consumer label given as a
 * callable is written untranslated too) and Credits::get_ledger(),
 * query_ledger() and get_ledger_row() hand them back through display(), in
 * the language of the request that reads them. Notes this SDK did not write
 * pass through unchanged, after the `wbcom_credits_display_note` filter, which
 * is where a consumer translates its own.
 *
 * @since 1.11.0
 */
final class Notes {

	/**
	 * The English note formats the SDK writes, each with its translation.
	 *
	 * Built per call so each one is translated in the current locale.
	 *
	 * @return array<string, string> English format => translated format.
	 */
	private static function formats(): array {
		return array(
			/* translators: %s: what the credits pay for, e.g. "Job Posting" */
			'%s — credits held'          => __( '%s — credits held', 'wbcom-credits-sdk' ),
			/* translators: %s: what the credits pay for, e.g. "Job Posting" */
			'%s — credits deducted'      => __( '%s — credits deducted', 'wbcom-credits-sdk' ),
			/* translators: %s: what the credits pay for, e.g. "Job Posting" */
			'%s — credits refunded'      => __( '%s — credits refunded', 'wbcom-credits-sdk' ),
			/* translators: %s: what the credits pay for, e.g. "Job Posting" */
			'%s — price change held'     => __( '%s — price change held', 'wbcom-credits-sdk' ),
			/* translators: %s: what the credits pay for, e.g. "Job Posting" */
			'%s — price change deducted' => __( '%s — price change deducted', 'wbcom-credits-sdk' ),
			/* translators: %s: what the credits pay for, e.g. "Job Posting" */
			'%s — price change released' => __( '%s — price change released', 'wbcom-credits-sdk' ),
			/* translators: %s: what the credits pay for, e.g. "Job Posting" */
			'%s — price change refunded' => __( '%s — price change refunded', 'wbcom-credits-sdk' ),
			/* translators: %d: credit lot number */
			'Credits expired (lot #%d)'  => __( 'Credits expired (lot #%d)', 'wbcom-credits-sdk' ),
			'Credits deducted'           => __( 'Credits deducted', 'wbcom-credits-sdk' ),
			'Credits refunded'           => __( 'Credits refunded', 'wbcom-credits-sdk' ),
			'Credits returned'           => __( 'Credits returned', 'wbcom-credits-sdk' ),
			'Credits spent'              => __( 'Credits spent', 'wbcom-credits-sdk' ),
			'Hold released on approval'  => __( 'Hold released on approval', 'wbcom-credits-sdk' ),
			'Admin REST adjustment'      => __( 'Admin REST adjustment', 'wbcom-credits-sdk' ),
		);
	}

	/**
	 * A consumer label as it is written into a note: untranslated.
	 *
	 * A label given as a callable (`static fn () => __( 'Job Posting', ... )`)
	 * is called with translation switched off, so the note holds the English
	 * text whoever's request wrote it. A plain string is written as given.
	 *
	 * @param string|callable $label Consumer label.
	 * @return string
	 */
	public static function label( $label ): string {
		return is_callable( $label ) ? self::untranslated( $label ) : (string) $label;
	}

	/**
	 * A stored note in the current request's language.
	 *
	 * @param string $slug Plugin slug the row belongs to.
	 * @param string $note Stored note.
	 * @return string
	 */
	public static function display( string $slug, string $note ): string {
		$text   = $note;
		$format = '';
		$args   = array();
		foreach ( self::formats() as $english => $translated ) {
			$found = self::match( $english, $note );
			if ( null === $found ) {
				continue;
			}
			if ( str_starts_with( $english, '%s' ) ) {
				$found[0] = self::display_label( $slug, $found[0] );
			}
			$text   = vsprintf( $translated, $found );
			$format = $english;
			$args   = $found;
			break;
		}

		/**
		 * Filter a ledger note as it is shown.
		 *
		 * The SDK is headless, so a consumer gives the wording in its own text
		 * domain: for one of the SDK's notes, `$format` is the English format it
		 * matched and `$args` its arguments (a consumer label already in the
		 * current language), ready for `vsprintf( __( $format, ... ), $args )`.
		 * For its own notes, a consumer stores the English format and matches
		 * `$note` with Notes::match().
		 *
		 * @since 1.11.0
		 *
		 * @param string             $text   The note to show (the SDK's own notes in the SDK's text domain).
		 * @param string             $note   The note as stored.
		 * @param string             $slug   Plugin slug the row belongs to.
		 * @param string             $format English format of an SDK note, or '' for any other note.
		 * @param array<int, string> $args   That format's arguments.
		 */
		return (string) apply_filters( 'wbcom_credits_display_note', $text, $note, $slug, $format, $args );
	}

	/**
	 * The arguments of a note written from a format, or null when it was not.
	 *
	 * Supports `%s` (any text) and `%d` (digits).
	 *
	 * @param string $format English format the note may have been written from.
	 * @param string $note   Stored note.
	 * @return array<int, string>|null
	 */
	public static function match( string $format, string $note ): ?array {
		$parts = preg_split( '/(%[sd])/', $format, -1, PREG_SPLIT_DELIM_CAPTURE );
		$regex = '';
		foreach ( (array) $parts as $part ) {
			$regex .= match ( $part ) {
				'%s'    => '(.*)',
				'%d'    => '(\d+)',
				default => preg_quote( (string) $part, '/' ),
			};
		}
		if ( ! preg_match( '/^' . $regex . '$/su', $note, $found ) ) {
			return null;
		}
		return array_slice( $found, 1 );
	}

	/**
	 * A consumer label from a stored note, in the current language.
	 *
	 * The note holds the label as it was written: English for a callable
	 * label, or the plain string a consumer passed. Older rows hold whatever
	 * language the label was in when they were written; those are matched
	 * against the current translation too, and anything else is left as stored.
	 *
	 * @param string $slug   Plugin slug.
	 * @param string $stored Label as stored in the note.
	 * @return string
	 */
	private static function display_label( string $slug, string $stored ): string {
		$config = Registry::instance()->get( $slug );
		foreach ( (array) ( $config['consumers'] ?? array() ) as $consumer ) {
			$label = $consumer['label'] ?? '';
			$now   = is_callable( $label ) ? (string) $label() : (string) $label;
			if ( $stored === $now || self::label( $label ) === $stored ) {
				return $now;
			}
		}
		return $stored;
	}

	/**
	 * Call a function with every gettext lookup returning its original text.
	 *
	 * @param callable $callback Function that returns translated text.
	 * @return string
	 */
	private static function untranslated( callable $callback ): string {
		$original = static fn ( $translation, $text ) => $text;
		add_filter( 'gettext', $original, PHP_INT_MAX, 2 );
		add_filter( 'gettext_with_context', $original, PHP_INT_MAX, 2 );
		try {
			return (string) $callback();
		} finally {
			remove_filter( 'gettext', $original, PHP_INT_MAX );
			remove_filter( 'gettext_with_context', $original, PHP_INT_MAX );
		}
	}
}
