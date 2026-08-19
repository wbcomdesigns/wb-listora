<?php
/**
 * General-purpose public helper functions — the documented Free→Pro surface
 * for cross-cutting utilities that don't belong to one feature.
 *
 * Pro (and site builders) consume these functions instead of referencing
 * Free's internal helper classes directly. Per the architecture contract
 * (INV-3), the function is the documented Free→Pro surface; the implementation
 * class is internal.
 *
 * @package WBListora
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_is_bot_request' ) ) {
	/**
	 * Whether the current request's User-Agent looks like a bot / crawler.
	 *
	 * The single canonical "is this a bot?" check for the whole plugin. Used by
	 * Free's analytics-lite view recording and anti-spam scoring, and consumed
	 * by Pro instead of referencing Free's internal
	 * `\WBListora\Bot_Detection` class directly.
	 *
	 * Conservative by design — catches the common self-identifying crawlers,
	 * link-preview fetchers, headless agents, and SEO spiders. A missing /
	 * empty User-Agent is treated as a bot. The verdict is filterable via
	 * `wb_listora_is_bot_request` and the signature list via
	 * `wb_listora_bot_signatures`.
	 *
	 * @since 1.2.0
	 *
	 * @param string|null $ua Optional explicit User-Agent to classify. When
	 *                        null (default), the current request's
	 *                        `HTTP_USER_AGENT` is read.
	 * @return bool True when the request / User-Agent is treated as a bot.
	 */
	function wb_listora_is_bot_request( $ua = null ): bool {
		if ( null === $ua ) {
			return \WBListora\Bot_Detection::is_bot_request();
		}

		return \WBListora\Bot_Detection::is_bot_user_agent( (string) $ua );
	}
}

if ( ! function_exists( 'wb_listora_sanitize_tile_url' ) ) {
	/**
	 * Sanitize a map tile URL template, keeping Leaflet's {z}/{x}/{y} placeholders.
	 *
	 * A tile template is not an ordinary URL. Leaflet substitutes {z}, {x} and
	 * {y} — and optionally {s} for a subdomain and {r} for retina — at request
	 * time, so those curly braces have to survive sanitization or the template
	 * stops being a template.
	 *
	 * esc_url_raw() alone cannot do this. Braces are not legal URL characters,
	 * so it strips them and returns a value that looks saved but requests
	 * https://tiles.example.com/z/x/y.png and 404s every tile (BC 10217195006).
	 * sanitize_text_field() keeps the braces but will happily store a
	 * javascript: scheme.
	 *
	 * Percent-encoding the braces first lets esc_url_raw() do the part it is
	 * good at — rejecting a non-http(s) scheme and stripping control
	 * characters — after which the placeholders are put back.
	 *
	 * @since 1.6.0
	 *
	 * @param string $url Raw tile URL template.
	 * @return string Sanitized template, or '' when the value is not a usable http(s) URL.
	 */
	function wb_listora_sanitize_tile_url( $url ): string {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$encoded = strtr(
			$url,
			array(
				'{' => '%7B',
				'}' => '%7D',
			)
		);

		$clean = esc_url_raw( $encoded, array( 'http', 'https' ) );

		if ( '' === $clean ) {
			return '';
		}

		return strtr(
			$clean,
			array(
				'%7B' => '{',
				'%7b' => '{',
				'%7D' => '}',
				'%7d' => '}',
			)
		);
	}
}
