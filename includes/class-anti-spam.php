<?php
/**
 * Anti-Spam helper — Akismet + keyword/URL blacklist for guest submissions.
 *
 * Layered defence on top of captcha + rate-limiter + honeypot already in
 * `class-submission-controller.php`. Captcha catches bots, this catches the
 * humans-paid-to-spam crowd that solves captchas.
 *
 * Akismet integration is a graceful no-op when the plugin isn't active. The
 * keyword/URL blacklist runs unconditionally so sites without an Akismet
 * subscription still get a working second tier.
 *
 * Hook surface for site builders:
 *
 *   - `wb_listora_antispam_should_check( bool $check, array $context )`
 *     Return false to skip every check for a request. Default: skip for
 *     users with `edit_others_listora_listings` (admins / editors).
 *
 *   - `wb_listora_antispam_keyword_blacklist( string[] $patterns )`
 *     Filter the default keyword blacklist.
 *
 *   - `wb_listora_antispam_max_urls( int $max )`
 *     Filter the per-submission URL cap (default 3 — most legit listings
 *     don't have more than the homepage + a booking link + social).
 *
 * @package WBListora
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

/**
 * Anti-spam checks for guest submissions.
 */
class Anti_Spam {

	/**
	 * Run every enabled check on a submission payload.
	 *
	 * @param array<string, mixed> $payload Submission fields. Recognized keys:
	 *                                       title, description, author_name, author_email,
	 *                                       author_url, source (default 'submission').
	 * @return true|\WP_Error True when the submission passes, WP_Error on first failure.
	 */
	public static function check( array $payload ) {
		$context = wp_parse_args(
			$payload,
			array(
				'title'        => '',
				'description'  => '',
				'author_name'  => '',
				'author_email' => '',
				'author_url'   => '',
				'source'       => 'submission',
			)
		);

		/**
		 * Short-circuit the anti-spam pipeline.
		 *
		 * Default: skip for trusted users (admins / editors with
		 * edit_others_listora_listings).
		 *
		 * @param bool                 $should_check Whether to run checks.
		 * @param array<string, mixed> $context      Submission payload + source.
		 */
		$should_check = (bool) apply_filters(
			'wb_listora_antispam_should_check',
			! current_user_can( 'edit_others_listora_listings' ),
			$context
		);
		if ( ! $should_check ) {
			return true;
		}

		$blacklist_result = self::check_blacklist( $context );
		if ( is_wp_error( $blacklist_result ) ) {
			return $blacklist_result;
		}

		$url_result = self::check_url_density( $context );
		if ( is_wp_error( $url_result ) ) {
			return $url_result;
		}

		$akismet_result = self::check_akismet( $context );
		if ( is_wp_error( $akismet_result ) ) {
			return $akismet_result;
		}

		return true;
	}

	/**
	 * Reject submissions whose body matches the keyword blacklist.
	 *
	 * Default patterns target the high-volume guest-listing spam buckets
	 * (gambling, knock-off pharma, escort, viagra-style payloads). Sites
	 * extend via the filter below — case-insensitive substring match per
	 * pattern.
	 *
	 * @param array<string, mixed> $context Submission context.
	 * @return true|\WP_Error
	 */
	private static function check_blacklist( array $context ) {
		$default_patterns = array(
			'casino',
			'viagra',
			'cialis',
			'escort',
			'porn',
			'sex chat',
			'crypto pump',
			'pump and dump',
			'free bitcoin',
			'forex signals',
		);

		/**
		 * Filter the keyword blacklist.
		 *
		 * @param string[] $patterns Case-insensitive substrings.
		 */
		$patterns = (array) apply_filters( 'wb_listora_antispam_keyword_blacklist', $default_patterns );
		if ( empty( $patterns ) ) {
			return true;
		}

		$haystack = strtolower(
			implode(
				' ',
				array(
					(string) $context['title'],
					(string) $context['description'],
					(string) $context['author_name'],
				)
			)
		);

		foreach ( $patterns as $pattern ) {
			$pattern = trim( strtolower( (string) $pattern ) );
			if ( '' === $pattern ) {
				continue;
			}
			if ( false !== strpos( $haystack, $pattern ) ) {
				return new \WP_Error(
					'listora_spam_keyword',
					__( 'Your submission contains content that looks like spam. Please review and try again.', 'wb-listora' ),
					array( 'status' => 422 )
				);
			}
		}

		return true;
	}

	/**
	 * Reject submissions whose description hides more URLs than a real
	 * listing ever needs. Legit listings ship 0-3 links (homepage,
	 * booking, social). Spam tends to ship 5-15.
	 *
	 * @param array<string, mixed> $context Submission context.
	 * @return true|\WP_Error
	 */
	private static function check_url_density( array $context ) {
		/**
		 * Filter the maximum number of URLs allowed in a single submission's
		 * description. Set to 0 to disable the check.
		 *
		 * @param int $max Default 3.
		 */
		$max = (int) apply_filters( 'wb_listora_antispam_max_urls', 3 );
		if ( $max <= 0 ) {
			return true;
		}

		$haystack = (string) $context['description'];
		preg_match_all( '#https?://#i', $haystack, $matches );
		$url_count = count( $matches[0] );

		if ( $url_count > $max ) {
			return new \WP_Error(
				'listora_spam_urls',
				sprintf(
					/* translators: %d: maximum number of URLs allowed. */
					__( 'Your description contains too many links (max %d). Trim a few and resubmit.', 'wb-listora' ),
					$max
				),
				array( 'status' => 422 )
			);
		}

		return true;
	}

	/**
	 * Defer to Akismet if active. Graceful no-op otherwise.
	 *
	 * @param array<string, mixed> $context Submission context.
	 * @return true|\WP_Error
	 */
	private static function check_akismet( array $context ) {
		if ( ! class_exists( '\\Akismet' ) ) {
			return true;
		}

		$payload = array(
			'comment_author'       => (string) $context['author_name'],
			'comment_author_email' => (string) $context['author_email'],
			'comment_author_url'   => (string) $context['author_url'],
			'comment_content'      => trim( (string) $context['title'] . "\n\n" . (string) $context['description'] ),
			'comment_type'         => 'wb-listora-' . (string) $context['source'],
			'user_ip'              => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'             => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'blog'                 => home_url(),
			'blog_lang'            => get_locale(),
			'blog_charset'         => get_option( 'blog_charset' ),
		);

		try {
			$response = \Akismet::http_post( build_query( $payload ), 'comment-check' );
		} catch ( \Throwable $e ) {
			// Akismet outage MUST NOT block a legitimate submission. Fail open.
			return true;
		}

		if ( ! is_array( $response ) || ! isset( $response[1] ) ) {
			return true;
		}

		$verdict = trim( (string) $response[1] );

		// Akismet returns 'true' for spam, 'false' for clean, 'discard' for
		// the most-confident spam bucket. Block both spam states.
		if ( 'true' === $verdict || 'discard' === $verdict ) {
			return new \WP_Error(
				'listora_spam_akismet',
				__( 'Your submission was flagged as spam. If this is a mistake, contact site support.', 'wb-listora' ),
				array( 'status' => 422 )
			);
		}

		return true;
	}
}
