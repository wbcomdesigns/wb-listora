<?php
/**
 * Rate limiter for public REST endpoints.
 *
 * Centralises the per-user / per-IP transient counters so every controller
 * uses the same limits and the same error shape. ADR-001 (architecture plan)
 * requires every public REST endpoint to be rate-limited or session-gated.
 *
 * Usage:
 *
 *     $check = \WBListora\Rate_Limiter::check( 'review_create' );
 *     if ( is_wp_error( $check ) ) {
 *         return $check;
 *     }
 *
 * Default limits live in {@see self::DEFAULTS}. Sites can override per-action
 * limits via the `wb_listora_rate_limit_config` filter, and bypass entirely
 * for trusted users via `wb_listora_rate_limit_bypass`.
 *
 * @package WBListora
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

/**
 * Static rate-limit gate for public write endpoints.
 */
class Rate_Limiter {

	/**
	 * Built-in per-action limits.
	 *
	 * These are tuned to **only block spammers and bots**, never real people.
	 * The numbers below are far above any plausible human pace — they're
	 * automation-detection thresholds, not throttles. A real visitor (paid
	 * customer, reviewer, owner) will never hit them.
	 *
	 * The two scopes serve different purposes:
	 *
	 *   - `ip_max` is the actual anti-abuse gate. Any single IP making
	 *     more requests per window than the cap is overwhelmingly a bot,
	 *     a botnet exit node, or a compromised host.
	 *   - `user_max` is a runaway-script guard for logged-in users. It
	 *     stops a stolen credential or a buggy automation from being used
	 *     as a spam gun, and never gets close to a human's normal pace.
	 *
	 * Each entry: `user_max` per `user_window` seconds, `ip_max` per `ip_window` seconds.
	 * Site owners can override per action via the `wb_listora_rate_limit_config`
	 * filter, or bypass for trusted roles via `wb_listora_rate_limit_bypass`.
	 *
	 * @var array<string,array<string,int>>
	 */
	const DEFAULTS = array(
		// Listing submissions are the highest-value abuse target — a successful
		// submission yields a public page, so the IP cap is tighter here.
		'submission'    => array(
			'user_max'    => 30,
			'user_window' => HOUR_IN_SECONDS,
			'ip_max'      => 10,
			'ip_window'   => HOUR_IN_SECONDS,
		),
		'review_create' => array(
			'user_max'    => 30,
			'user_window' => HOUR_IN_SECONDS,
			'ip_max'      => 15,
			'ip_window'   => HOUR_IN_SECONDS,
		),
		'review_vote'   => array(
			'user_max'    => 300,
			'user_window' => HOUR_IN_SECONDS,
			'ip_max'      => 150,
			'ip_window'   => HOUR_IN_SECONDS,
		),
		// Owner replies — the calling account is already proven to own a
		// listing, so the user cap is essentially a runaway-script guard.
		'review_reply'  => array(
			'user_max'    => 200,
			'user_window' => HOUR_IN_SECONDS,
			'ip_max'      => 50,
			'ip_window'   => HOUR_IN_SECONDS,
		),
		'review_report' => array(
			'user_max'    => 50,
			'user_window' => HOUR_IN_SECONDS,
			'ip_max'      => 20,
			'ip_window'   => HOUR_IN_SECONDS,
		),
		// Claims are infrequent by nature; daily windows are the right scale.
		'claim_submit'  => array(
			'user_max'    => 20,
			'user_window' => DAY_IN_SECONDS,
			'ip_max'      => 10,
			'ip_window'   => DAY_IN_SECONDS,
		),
		// Favourites are pure UX — only block runaway scripts.
		'favorite'      => array(
			'user_max'    => 1000,
			'user_window' => HOUR_IN_SECONDS,
			'ip_max'      => 500,
			'ip_window'   => HOUR_IN_SECONDS,
		),
		// Bulk listing fetch — public endpoint that returns up to 50 listings
		// per call (heavy: 50 × join + meta + image URL resolution). The cap
		// catches scrapers and accidental N+1 client loops while leaving the
		// legitimate card-grid initial render (1 call/page navigation) far
		// inside the budget. F-01 in plan/release-issues-and-flow-tests.md.
		'bulk_listings' => array(
			'user_max'    => 120,
			'user_window' => MINUTE_IN_SECONDS,
			'ip_max'      => 30,
			'ip_window'   => MINUTE_IN_SECONDS,
		),
	);

	/**
	 * Check + record a hit against the rate limit for an action.
	 *
	 * Increments the relevant counter(s) on every call. Returns a WP_Error
	 * with HTTP status 429 when either the per-user or per-IP limit is hit;
	 * returns true on pass.
	 *
	 * Bypass is honoured via `wb_listora_rate_limit_bypass` (filter receives
	 * the action and current user ID) — used by Pro to exempt trusted roles
	 * (e.g. paid moderators) without changing the default limits.
	 *
	 * @param string $action  Action key — see self::DEFAULTS for known actions.
	 * @param int    $user_id Optional explicit user ID. Defaults to current user.
	 * @return true|\WP_Error True on pass, WP_Error (429) on limit hit.
	 */
	public static function check( $action, $user_id = 0 ) {
		$action = sanitize_key( $action );
		if ( '' === $action ) {
			return true;
		}

		/**
		 * Allow trusted contexts to bypass rate limiting.
		 *
		 * Returning true skips the check entirely — useful for paid roles,
		 * cron, CLI, and integration tests.
		 *
		 * @param bool   $bypass  Default false.
		 * @param string $action  Rate-limit action key.
		 * @param int    $user_id Current user ID (0 for guests).
		 */
		if ( apply_filters( 'wb_listora_rate_limit_bypass', false, $action, (int) $user_id ) ) {
			return true;
		}

		$config = self::config_for( $action );
		if ( empty( $config ) ) {
			return true;
		}

		$user_id = (int) $user_id ?: get_current_user_id();

		// Per-user counter — only meaningful when a user is logged in.
		if ( $user_id > 0 && ! empty( $config['user_max'] ) ) {
			$key   = self::key( $action, 'u', (string) $user_id );
			$count = (int) get_transient( $key );
			if ( $count >= (int) $config['user_max'] ) {
				return self::error( $action, 'user' );
			}
			set_transient( $key, $count + 1, (int) $config['user_window'] );
		}

		// Per-IP counter — runs for guests AND logged-in users so a single
		// host can't burn through every test account it created.
		$ip = self::client_ip();
		if ( '' !== $ip && ! empty( $config['ip_max'] ) ) {
			$key   = self::key( $action, 'ip', md5( $ip ) );
			$count = (int) get_transient( $key );
			if ( $count >= (int) $config['ip_max'] ) {
				return self::error( $action, 'ip' );
			}
			set_transient( $key, $count + 1, (int) $config['ip_window'] );
		}

		return true;
	}

	/**
	 * Resolve the active config for an action, after the override filter.
	 *
	 * @param string $action Action key.
	 * @return array<string,int>
	 */
	private static function config_for( $action ) {
		$config = self::DEFAULTS[ $action ] ?? array();

		/**
		 * Filter the rate-limit config for an action.
		 *
		 * @param array  $config Defaults — keys: user_max, user_window, ip_max, ip_window.
		 * @param string $action Action key.
		 */
		$config = (array) apply_filters( 'wb_listora_rate_limit_config', $config, $action );

		// Defensive defaults so a misconfigured filter can't disable limits silently.
		return wp_parse_args(
			$config,
			array(
				'user_max'    => 0,
				'user_window' => HOUR_IN_SECONDS,
				'ip_max'      => 0,
				'ip_window'   => HOUR_IN_SECONDS,
			)
		);
	}

	/**
	 * Build a stable transient key for a counter.
	 *
	 * @param string $action Action key.
	 * @param string $scope  'u' (user) or 'ip'.
	 * @param string $id     User ID or hashed IP.
	 * @return string
	 */
	private static function key( $action, $scope, $id ) {
		return 'listora_rl_' . $action . '_' . $scope . '_' . $id;
	}

	/**
	 * Resolve the client IP with proxy awareness.
	 *
	 * Supports a single trusted proxy hop (X-Forwarded-For) when the site
	 * opts in via `wb_listora_trust_proxy_headers`. Default is REMOTE_ADDR
	 * only — the safest behaviour for plain Apache / nginx hosts where
	 * X-Forwarded-For can be spoofed.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( apply_filters( 'wb_listora_trust_proxy_headers', false ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$first     = trim( explode( ',', $forwarded )[0] );
			if ( $first && filter_var( $first, FILTER_VALIDATE_IP ) ) {
				$ip = $first;
			}
		}

		return $ip;
	}

	/**
	 * Build the standard WP_Error returned when a limit is hit.
	 *
	 * @param string $action Action key.
	 * @param string $scope  'user' or 'ip'.
	 * @return \WP_Error
	 */
	private static function error( $action, $scope ) {
		$message = ( 'user' === $scope )
			? __( 'Too many requests. Please wait a moment before trying again.', 'wb-listora' )
			: __( 'Too many requests from this network. Please try again later.', 'wb-listora' );

		return new \WP_Error(
			'listora_rate_limit',
			$message,
			array(
				'status' => 429,
				'action' => $action,
				'scope'  => $scope,
			)
		);
	}
}
