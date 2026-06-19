<?php
/**
 * Listora companion registry.
 *
 * A single declarative, filterable catalog of the Wbcom plugins WB Listora
 * integrates with (BuddyNext, Jetonomy, WB Gamification, …). Each entry is
 * DATA, not code — Pro and third parties extend the list via the
 * `wb_listora_companions` filter. Every UI + integration decision keys off
 * `status()` / `is_active()` (a runtime capability probe), never a hardcoded
 * plugin path, so "works standalone" and "no duplication" both hold:
 * capability present -> delegate; absent -> hide.
 *
 * Self-contained on purpose: this whole `includes/integrations/` wrapper is
 * designed to copy cleanly into the other Wbcom plugins (they already bundle
 * the identical EDD SL SDK the installer speaks to).
 *
 * @package WBListora\Integrations
 */

namespace WBListora\Integrations;

defined( 'ABSPATH' ) || exit;

final class Companion_Registry {

	/**
	 * Resolve the companion catalog. Each entry:
	 *   label      string   Display name.
	 *   why        string   One-line value proposition.
	 *   detect     callable Returns true when the companion's capability is live.
	 *   free       array    { item_id, key, basename } for one-click free install.
	 *   pro        array    { item_id, basename } for the license-gated upgrade (optional).
	 *   store_url  string   Product page for the "Upgrade to Pro" link.
	 *   unlocks    string   What this turns on inside WB Listora when connected.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		/**
		 * Filter the Listora companion catalog. Pro + third-party plugins
		 * add their own entries here; the installer + admin screen render whatever
		 * this returns.
		 *
		 * @param array<string, array<string, mixed>> $companions Slug => entry.
		 */
		return (array) apply_filters(
			'wb_listora_companions',
			array(
				'buddynext'       => array(
					'label'     => __( 'BuddyNext', 'wb-listora' ),
					'why'       => __( 'Community engine — profiles, activity feeds, and member spaces.', 'wb-listora' ),
					'detect'    => static fn() => defined( 'BUDDYNEXT_VERSION' ),
					'free'      => array(
						'item_id'  => 1664401,
						'key'      => 'buddynext9a3c7e1d5f2b8a4c6e0d9b7f1a2c8e55',
						'basename' => 'buddynext/buddynext.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/buddynext/',
					'unlocks'   => __( 'Enables member profiles linked to listings and activity feed events.', 'wb-listora' ),
				),
				'jetonomy'        => array(
					'label'     => __( 'Jetonomy', 'wb-listora' ),
					'why'       => __( 'Discussions and Q&A spaces around your listings.', 'wb-listora' ),
					'detect'    => static fn() => function_exists( 'jetonomy' ) || class_exists( '\\Jetonomy\\Plugin' ),
					'free'      => array(
						'item_id'  => 1660320,
						'key'      => 'wbcomfreec7e2a9b45d8f1c3e6a0b9d2f7c4e8a11',
						'basename' => 'jetonomy/jetonomy.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/jetonomy/',
					'unlocks'   => __( 'Adds discussion threads and Q&A directly on listing pages.', 'wb-listora' ),
				),
				'wb-gamification' => array(
					'label'     => __( 'WB Gamification', 'wb-listora' ),
					'why'       => __( 'Points and badges for submitting and reviewing listings.', 'wb-listora' ),
					'detect'    => static fn() => defined( 'WB_GAM_VERSION' ) || function_exists( 'wb_gam_submit_event' ),
					'free'      => array(
						'item_id'  => 1662147,
						'key'      => 'wbcomfree6e2a9c1d7b4f3c8a0e5d9b2f1a7c6e11',
						'basename' => 'wb-gamification/wb-gamification.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/wordpress-gamification-plugin/',
					'unlocks'   => __( 'Awards points when members submit, review, or interact with listings.', 'wb-listora' ),
				),
				'learnomy'        => array(
					'label'     => __( 'Learnomy', 'wb-listora' ),
					'why'       => __( 'Full LMS — courses, lessons, quizzes, and certificates.', 'wb-listora' ),
					'detect'    => static fn() => defined( 'LEARNOMY_VERSION' ) || class_exists( '\\Learnomy\\Learnomy' ),
					'free'      => array(
						'item_id'  => 1662698,
						'key'      => 'wbcomfree5d8a1f3c7b2e9a4c6f0d1e8b3c9a7f25',
						'basename' => 'learnomy/learnomy.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/learnomy/',
					'unlocks'   => __( 'Courses and certifications for your listing owners.', 'wb-listora' ),
				),
				'wp-career-board' => array(
					'label'     => __( 'WP Career Board', 'wb-listora' ),
					'why'       => __( 'Job listings and applicant management with employer profiles.', 'wb-listora' ),
					'detect'    => static fn() => defined( 'WCB_VERSION' ) || class_exists( '\\WCB\\Core\\Plugin' ),
					'free'      => array(
						'item_id'  => 1659888,
						'key'      => 'wbcomfree5b8c1e7a9d3f2a4c6e0d1b7f9c2a6e00',
						'basename' => 'wp-career-board/wp-career-board.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/wp-career-board/',
					'unlocks'   => __( 'A job board next to your business directory.', 'wb-listora' ),
				),
				'wpmediaverse'    => array(
					'label'     => __( 'MediaVerse', 'wb-listora' ),
					'why'       => __( 'Direct messaging and media galleries for members.', 'wb-listora' ),
					'detect'    => static fn() => defined( 'MVS_VERSION' ) || class_exists( '\\WPMediaVerse\\Core\\Plugin' ),
					'free'      => array(
						'item_id'  => 1660826,
						'key'      => 'wbcomfree7a9c2e5d1f8b4c6a3e0d9b2f7c1a8e44',
						'basename' => 'wpmediaverse/wpmediaverse.php',
					),
					'store_url' => 'https://wbcomdesigns.com/downloads/mediaverse/',
					'unlocks'   => __( 'Media galleries and DMs around your listings.', 'wb-listora' ),
				),
			)
		);
	}

	/**
	 * A single companion entry, or null when the slug is unknown.
	 *
	 * @param string $slug Companion slug.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $slug ): ?array {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}

	/**
	 * Lifecycle status of a companion:
	 *   'active'             - its capability probe returns true (installed + on).
	 *   'installed_inactive' - the plugin file is present but not active.
	 *   'not_installed'      - absent.
	 *
	 * @param string $slug Companion slug.
	 */
	public static function status( string $slug ): string {
		$entry = self::get( $slug );
		if ( null === $entry ) {
			return 'not_installed';
		}

		$detect = $entry['detect'] ?? null;
		if ( is_callable( $detect ) && (bool) $detect() ) {
			return 'active';
		}

		// Capability absent — is the free plugin at least on disk (so we offer
		// "Activate" instead of "Install")?
		$basename = (string) ( $entry['free']['basename'] ?? '' );
		if ( '' !== $basename && self::plugin_file_exists( $basename ) ) {
			return 'installed_inactive';
		}

		return 'not_installed';
	}

	/**
	 * Whether a companion's capability is live. The single gate WB Listora
	 * integration code should call before delegating to a companion.
	 *
	 * @param string $slug Companion slug.
	 */
	public static function is_active( string $slug ): bool {
		return 'active' === self::status( $slug );
	}

	/**
	 * Whether a plugin file exists under wp-content/plugins, without loading the
	 * (potentially expensive) full plugin list on every call when WP's helper
	 * isn't available yet.
	 *
	 * @param string $basename e.g. "buddynext/buddynext.php".
	 */
	private static function plugin_file_exists( string $basename ): bool {
		$path = trailingslashit( WP_PLUGIN_DIR ) . ltrim( $basename, '/' );
		return file_exists( $path );
	}
}
