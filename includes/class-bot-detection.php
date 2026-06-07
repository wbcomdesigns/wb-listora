<?php
/**
 * Bot Detection — canonical crawler / non-human User-Agent classifier.
 *
 * One conservative implementation of "is this request a self-identifying bot?"
 * shared by every Free surface that needs to skip a bot (analytics-lite view
 * recording, anti-spam scoring) — and consumed by Pro via the public
 * {@see wb_listora_is_bot_request()} function (NEVER by referencing this class
 * directly; INV-3).
 *
 * Deliberately conservative: it catches the common, self-identifying bots
 * (Googlebot, bingbot, generic spiders/crawlers/scrapers, headless agents,
 * SEO crawlers, link-preview fetchers) from the User-Agent header without
 * trying to be a full bot-management product. A missing / empty User-Agent is
 * treated as a bot.
 *
 * Hook surface for site builders:
 *
 *   - `wb_listora_bot_signatures( string[] $signatures )`
 *     Filter the case-insensitive User-Agent substrings that mark a request as
 *     a bot. Add private crawlers or remove a false positive here.
 *
 *   - `wb_listora_is_bot_request( bool $is_bot, string $ua )`
 *     Final verdict filter — the canonical extension point. Return true/false
 *     to override the signature scan for the current request.
 *
 * @package WBListora
 * @since   1.1.0
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

/**
 * Shared, conservative bot/crawler detector.
 */
class Bot_Detection {

	/**
	 * Whether the current request's User-Agent looks like a bot/crawler.
	 *
	 * Reads `HTTP_USER_AGENT` from the current request. A missing or empty
	 * User-Agent is treated as a bot. The signature list and the final verdict
	 * are both filterable.
	 *
	 * @since 1.2.0
	 *
	 * @return bool True when the request is treated as a bot.
	 */
	public static function is_bot_request() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		return self::is_bot_user_agent( $ua );
	}

	/**
	 * Whether a given User-Agent string looks like a bot/crawler.
	 *
	 * Split from {@see self::is_bot_request()} so callers that already hold a
	 * User-Agent (e.g. an audit-log entry, an imported request record) can reuse
	 * the same canonical signature list without re-reading `$_SERVER`.
	 *
	 * @since 1.2.0
	 *
	 * @param string $ua Raw User-Agent string. Empty is treated as a bot.
	 * @return bool True when the User-Agent is treated as a bot.
	 */
	public static function is_bot_user_agent( $ua ) {
		$ua = (string) $ua;

		// A request with no User-Agent at all is almost never a real browser —
		// treat it as a bot.
		if ( '' === $ua ) {
			return true;
		}

		$ua_lower = strtolower( $ua );

		foreach ( self::signatures() as $needle ) {
			$needle = strtolower( (string) $needle );
			if ( '' === $needle ) {
				continue;
			}
			if ( false !== strpos( $ua_lower, $needle ) ) {
				return true;
			}
		}

		/**
		 * Filter the final bot verdict for the current request.
		 *
		 * This is the canonical extension point — Pro and site builders answer
		 * here to override the signature scan (e.g. to whitelist a known good
		 * crawler or flag a private scraper the default list misses).
		 *
		 * @since 1.2.0
		 *
		 * @param bool   $is_bot Whether the request is treated as a bot.
		 * @param string $ua     The raw User-Agent string.
		 */
		return (bool) apply_filters( 'wb_listora_is_bot_request', false, $ua );
	}

	/**
	 * The canonical, filterable list of bot User-Agent signatures.
	 *
	 * Case-insensitive substrings. Conservative on purpose — only the common,
	 * self-identifying crawlers, link-preview fetchers, headless agents, and SEO
	 * spiders. Sites extend or trim via the `wb_listora_bot_signatures` filter.
	 *
	 * @since 1.2.0
	 *
	 * @return string[] Lower-cased substrings to match against the User-Agent.
	 */
	public static function signatures() {
		$signatures = array(
			'bot',
			'crawl',
			'spider',
			'slurp',
			'mediapartners',
			'facebookexternalhit',
			'embedly',
			'quora link preview',
			'pinterest',
			'wget',
			'curl',
			'python-requests',
			'headlesschrome',
			'phantomjs',
			'archive.org_bot',
			'semrush',
			'ahrefs',
			'mj12bot',
			'dotbot',
		);

		/**
		 * Filter the bot User-Agent signature list.
		 *
		 * @since 1.2.0
		 *
		 * @param string[] $signatures Case-insensitive User-Agent substrings.
		 */
		return (array) apply_filters( 'wb_listora_bot_signatures', $signatures );
	}
}

// The public extension function Pro consumes lives in the sibling helper file
// and delegates to this class. Loading it here guarantees the function is
// available whenever this class autoloads (e.g. the first time a Free consumer
// calls wb_listora_is_bot_request()), independent of bootstrap ordering.
require_once __DIR__ . '/helpers.php';
