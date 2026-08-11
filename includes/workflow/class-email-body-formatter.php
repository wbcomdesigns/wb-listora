<?php
/**
 * Email Body Formatter — HTML → plain-text for multipart mail.
 *
 * Replaces 2 byte-identical copies of `html_to_text()` that lived in
 * `class-notifications.php` (Free, private instance method) and
 * `class-email-helpers.php` (Pro, public static). Consolidated in 1.1.0:
 * both Free's notifications system AND Pro's email helpers consume this
 * single canonical static method, so body formatting cannot drift between
 * the two plugins.
 *
 * Preserves link URLs inline so screen-reader / Gmail text-only clients
 * still get the CTA — `<a href="https://example.com">Click here</a>`
 * becomes `Click here (https://example.com)` in the text/plain alternative.
 *
 * @package WBListora\Workflow
 * @since   1.1.0
 */

namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Static helper for the text/plain alternative of a multipart email.
 *
 * `final` so consumers can't subclass — there's a single canonical
 * implementation per design.
 */
final class Email_Body_Formatter {

	/**
	 * Strip HTML to plain text for the text/plain mail alternative.
	 *
	 * Keeps link URLs visible so screen-reader / Gmail text-only clients
	 * still get the CTA. Collapses repeated whitespace and blank lines
	 * so the resulting plain-text body reads cleanly.
	 *
	 * @since 1.1.0
	 *
	 * @param string $html Rendered HTML body.
	 * @return string Plain-text body suitable for `multipart/alternative` text part.
	 */
	public static function html_to_text( string $html ): string {
		// Replace <a href="X">Y</a> with "Y (X)" BEFORE strip_tags runs —
		// angle brackets around the URL would otherwise be eaten by
		// wp_strip_all_tags because <https://…> looks like an HTML tag.
		$with_links = preg_replace( '#<a[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>(.*?)</a>#is', '$2 ($1)', $html );
		$text       = wp_strip_all_tags( (string) $with_links );
		$text       = preg_replace( "/[ \t]+/", ' ', $text );
		$text       = preg_replace( "/\n{3,}/", "\n\n", (string) $text );
		return trim( (string) $text );
	}
}
