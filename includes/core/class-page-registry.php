<?php
/**
 * Page Registry: central resolver for Listora-managed WordPress pages.
 *
 * `wb_listora_get_page_url( $key, $args )` returns a translation-aware,
 * overridable URL, or '' when the page is missing. Resolution: the key's
 * `option_key` → Polylang/WPML remap → `wb_listora_page_id` filter →
 * get_permalink() → add_query_arg( $args ) → `wb_listora_page_url` filter.
 * Creation is `Activator::ensure_essential_pages()`.
 *
 * @package WBListora\Core
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Central resolver + registrar for Listora-managed pages.
 *
 * @since 1.0.0
 */
final class Page_Registry {

	/**
	 * Registered pages keyed by stable identifier.
	 *
	 * Shape per entry:
	 *   - default_slug / default_title / default_content  Used when creating the page.
	 *   - default_block / default_shortcode  Detect orphans (page has the block but is unmapped).
	 *   - menu_candidate  bool    Whether this page needs a nav-menu entry to be reachable.
	 *   - option_key      string  wp_options row that stores the resolved ID.
	 *   - owner           string  'free' | 'pro' | <plugin-slug>.
	 *   - role            string  'frontend' | 'admin'.
	 *   - description     string  Localized human description shown on Settings → Pages.
	 * @var array<string, array<string, string>>
	 */
	private static array $registry = array();

	/**
	 * Register a page key.
	 *
	 * Idempotent — re-registering the same key is a no-op (first registration wins).
	 * That keeps owner-plugin-then-theme override patterns sane: themes register
	 * earlier on plugins_loaded@5; the owner plugin's later @10 registration
	 * doesn't clobber it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    Stable identifier (e.g. 'dashboard', 'directory', 'compare').
	 * @param array  $config Registration config — see class docblock for the shape.
	 * @return void
	 */
	public static function register( string $key, array $config ): void {
		if ( '' === $key || isset( self::$registry[ $key ] ) ) {
			return;
		}

		$defaults = array(
			'default_slug'    => $key,
			'default_title'   => ucfirst( str_replace( '-', ' ', $key ) ),
			'default_block'   => '',
			// Legacy shortcode equivalent, where one exists. Only used to
			// recognise an unmapped page as this key's page: a site that built
			// its Compare page before the block existed has the shortcode and
			// no block, and without this the page is invisible to adoption.
			'default_shortcode' => '',
			// Optional callable: does the thing this page exists for currently
			// work? A page whose feature is switched off renders blank, and a
			// blank published page is worse than an honest 404.
			'is_available'    => null,
			// Is this page a DESTINATION a visitor navigates to, or is it
			// reached from a control inside the product? Compare is opened by
			// pressing Compare on a listing; Buy Credits by a plan CTA. Neither
			// belongs in a nav menu, and offering to put them there is noise.
			// Browse Needs has no other way in — for a visitor it exists only
			// if the menu says so.
			'menu_candidate'  => false,
			'default_content' => '',
			'option_key'      => 'wb_listora_' . $key . '_page_id',
			'owner'           => 'free',
			'role'            => 'frontend',
			'description'     => '',
		);

		self::$registry[ $key ] = array_merge( $defaults, $config );
	}

	/**
	 * Get the resolved page ID for a key, applying translation + override filters.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Registered page key.
	 * @param string $context Optional. Context label passed through to filter for caller-side disambiguation.
	 * @return int Page ID, or 0 if unmapped / missing / trashed.
	 */
	public static function get_id( string $key, string $context = '' ): int {
		if ( ! isset( self::$registry[ $key ] ) ) {
			return 0;
		}

		$config = self::$registry[ $key ];
		$id     = (int) get_option( $config['option_key'], 0 );

		// Self-heal a missing or stale mapping. A stored ID must still point at a
		// live page (a deleted or trashed page leaves a dead ID); when it does not,
		// or nothing is stored, a live page containing the registered block is
		// adopted and the mapping persisted, so the registry, Health Check and URL
		// helpers agree.
		if ( $id > 0 && ! self::is_live_page( $id ) ) {
			$id = 0;
		}

		if ( $id <= 0 ) {
			$id = self::heal_mapping( $key, $config );
		}

		// Polylang — translate to the current locale's matching page.
		if ( $id > 0 && function_exists( 'pll_get_post' ) ) {
			$translated = \pll_get_post( $id );
			if ( $translated ) {
				$id = (int) $translated;
			}
		}

		// WPML — same idea via the canonical filter.
		if ( $id > 0 && defined( 'ICL_LANGUAGE_CODE' ) ) {
			// `wpml_object_id` is WPML's own filter — we're consuming it,
			// not registering one of our own. Calling third-party hooks
			// by name is the entire point of cross-plugin integration.
			$translated = apply_filters( 'wpml_object_id', $id, 'page', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- consumes the WPML filter, not a hook of our own.
			if ( $translated ) {
				$id = (int) $translated;
			}
		}

		/**
		 * Filter the resolved page ID for a registered key.
		 *
		 * Useful for themes / plugins that map pages by their own logic (e.g.
		 * a multisite network with central pages, or a template-bypass on a
		 * specific request).
		 *
		 * @since 1.0.0
		 *
		 * @param int    $id      Resolved page ID, post-translation.
		 * @param string $key     Registered page key.
		 * @param string $context Caller context.
		 */
		$id = (int) apply_filters( 'wb_listora_page_id', $id, $key, $context );

		// Validate — page must still exist + not be trashed.
		if ( $id > 0 ) {
			$post = get_post( $id );
			if ( ! $post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
				return 0;
			}
		}

		return $id;
	}

	/**
	 * Get the frontend URL for a key, with optional query args.
	 *
	 * Returns an empty string when the page is missing — callers should
	 * either gracefully degrade ( hide a CTA, etc. ) or surface a fallback.
	 * NEVER falls back to `home_url()` or admin URLs internally; that
	 * decision belongs to the caller.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $key  Registered page key.
	 * @param array<string, mixed> $args Optional. Query args appended via add_query_arg.
	 * @return string Frontend URL, or empty string if unresolvable.
	 */
	public static function get_url( string $key, array $args = array() ): string {
		$id = self::get_id( $key );
		if ( 0 === $id ) {
			return '';
		}

		$url = (string) get_permalink( $id );
		if ( '' === $url ) {
			return '';
		}

		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		/**
		 * Filter the resolved page URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string               $url  Resolved URL with query args applied.
		 * @param string               $key  Registered page key.
		 * @param array<string, mixed> $args Query args that were applied.
		 * @param int                  $id   Resolved page ID.
		 */
		return (string) apply_filters( 'wb_listora_page_url', $url, $key, $args, $id );
	}

	/**
	 * Which registered key, if any, a page belongs to.
	 *
	 * Three sources, because no single one covers every site. The meta stamp is
	 * only on pages created since 1.7.0; the ledger only knows pages the
	 * registry itself created; and the live mappings only exist while the
	 * plugin that registered the key is active — which is precisely the case
	 * that matters most, when it is not.
	 *
	 * @since 1.7.0
	 *
	 * @param int $page_id Page to identify.
	 * @return string Registered key, or '' when the page is not ours.
	 */
	public static function key_for_page( int $page_id ): string {
		if ( $page_id <= 0 ) {
			return '';
		}

		$key = (string) get_post_meta( $page_id, self::META_KEY, true );
		if ( '' !== $key ) {
			return $key;
		}

		$created = get_option( self::OPTION_CREATED, array() );
		if ( is_array( $created ) ) {
			$found = array_search( $page_id, array_map( 'intval', $created ), true );
			if ( is_string( $found ) ) {
				return $found;
			}
		}

		// Live mappings. Reads the option directly rather than get_id(), which
		// would recurse through healing on every page view.
		foreach ( self::$registry as $registered_key => $config ) {
			if ( (int) get_option( $config['option_key'], 0 ) === $page_id ) {
				return $registered_key;
			}
		}

		return '';
	}

	/**
	 * Forget a mapping when its page is deleted.
	 *
	 * get_id() already ignores dead IDs; this also clears the option so anyone
	 * reading it directly is not told the page exists. On `deleted_post`, not
	 * `trashed_post`, so a restored page keeps its mapping.
	 *
	 * @since 1.7.0
	 *
	 * @param int $post_id Post being permanently deleted.
	 * @return void
	 */
	public static function forget_deleted_page( $post_id ): void {
		$post_id = (int) $post_id;
		if ( $post_id < 1 ) {
			return;
		}

		foreach ( self::$registry as $key => $config ) {
			if ( (int) get_option( $config['option_key'], 0 ) !== $post_id ) {
				continue;
			}

			delete_option( $config['option_key'] );

			/**
			 * Fires when a registered page's mapping is dropped because the
			 * page was permanently deleted.
			 *
			 * @since 1.7.0
			 *
			 * @param string $key     Registered page key.
			 * @param int    $post_id Page that was deleted.
			 */
			do_action( 'wb_listora_page_mapping_forgotten', $key, $post_id );
		}
	}

	/**
	 * Record which key a page belongs to, if it is not recorded yet.
	 *
	 * Called from {@see self::ensure()} so a site that had its pages before the
	 * stamp existed picks it up the next time anything sets up pages, rather
	 * than needing a migration of its own.
	 *
	 * @since 1.7.0
	 *
	 * @param int    $page_id Page.
	 * @param string $key     Registered key.
	 */
	private static function stamp( int $page_id, string $key ): void {
		if ( $page_id <= 0 ) {
			return;
		}

		if ( '' === (string) get_post_meta( $page_id, self::META_KEY, true ) ) {
			update_post_meta( $page_id, self::META_KEY, $key );
		}
	}

	/**
	 * Whether the thing this page exists for currently works.
	 *
	 * False in two situations, and they look identical to a visitor: the key is
	 * not registered at all (the plugin that owned it is deactivated), or it is
	 * registered but its feature is switched off. Either way the page's blocks
	 * render nothing and the page is published, so the site serves a blank 200
	 * — which search engines index and visitors read as a broken site.
	 *
	 * @since 1.7.0
	 *
	 * @param string $key Registered page key.
	 * @return bool
	 */
	public static function is_available( string $key ): bool {
		if ( ! isset( self::$registry[ $key ] ) ) {
			return false;
		}

		$callback = self::$registry[ $key ]['is_available'];

		if ( null === $callback || ! is_callable( $callback ) ) {
			return true;
		}

		return (bool) call_user_func( $callback );
	}

	/**
	 * Resolve a page URL only when a visitor could actually open it.
	 *
	 * {@see self::get_url()} answers "where is this page"; this answers "may I
	 * send someone there". Publish status is the test (via core's
	 * is_post_publicly_viewable(), which also covers private and
	 * password-protected pages); page CONTENT never is.
	 *
	 * @since 1.7.0
	 *
	 * @param string               $key  Registered page key.
	 * @param array<string, mixed> $args Optional query args.
	 * @return string URL, or '' when there is nothing a visitor may open.
	 */
	public static function get_public_url( string $key, array $args = array() ): string {
		$id = self::get_id( $key );
		if ( $id <= 0 ) {
			return '';
		}

		if ( ! is_post_publicly_viewable( $id ) ) {
			return '';
		}

		return self::get_url( $key, $args );
	}

	/**
	 * Return every registered page with its resolved status.
	 *
	 * Used by Settings → Pages to render the management table and by
	 * `Activator::ensure_essential_pages()` to walk the canonical list.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Registry rows enriched with id + url + status.
	 */
	public static function all(): array {
		$out = array();
		foreach ( self::$registry as $key => $config ) {
			$id          = self::get_id( $key );
			$url         = $id > 0 ? (string) get_permalink( $id ) : '';
			$status      = self::status_for( $key );
			$out[ $key ] = array_merge(
				$config,
				array(
					'key'    => $key,
					'id'     => $id,
					'url'    => $url,
					'status' => $status,
				)
			);
		}
		return $out;
	}

	/**
	 * Resolve the status of a registered key.
	 *
	 * - 'unregistered' : key is not registered (caller error).
	 * - 'linked'       : option points to a published / draft page that exists.
	 * - 'trashed'      : option points to a trashed page (treat as missing).
	 * - 'missing'      : option is 0 or points to a non-existent post.
	 * - 'orphan'       : option is missing but a page somewhere contains the registered block.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Registered page key.
	 * @return string Status token.
	 */
	public static function status_for( string $key ): string {
		if ( ! isset( self::$registry[ $key ] ) ) {
			return 'unregistered';
		}

		$config = self::$registry[ $key ];

		// get_id() self-heals a missing mapping from Settings / an orphan page
		// and persists it, so a site with a working (but previously unmapped)
		// page reports 'linked' here instead of a false 'orphan' / 'missing'
		// that made the Health Check cry wolf on an otherwise fine site.
		$id = self::get_id( $key );

		if ( $id > 0 ) {
			$post = get_post( $id );
			if ( ! $post || 'page' !== $post->post_type ) {
				return 'missing';
			}
			if ( 'trash' === $post->post_status ) {
				return 'trashed';
			}

			// Mapped and published, but its feature is switched off, so it
			// renders nothing and the front end serves a 404 for it. Without
			// saying so here the owner has a page that looks fine in this table
			// and is gone from their site.
			if ( ! self::is_available( $key ) ) {
				return 'inactive';
			}

			return 'linked';
		}

		// No mapping and nothing to heal — see if a page somewhere contains the
		// registered block (a genuine orphan the owner can adopt).
		if ( '' !== $config['default_block'] && self::find_orphan( $key ) > 0 ) {
			return 'orphan';
		}

		return 'missing';
	}

	/**
	 * Resolve and persist a page ID for a key whose option row is unset (0).
	 *
	 * Prefers the ID the Settings screen stores for the same page
	 * (wb_listora_settings[{key}_page], e.g. dashboard_page / submission_page /
	 * directory_page) because that is what a site owner picks in the UI; falls
	 * back to an orphan page that contains the registered block. On a match the
	 * canonical option row is written so this heals exactly once.
	 *
	 * @param string               $key    Registered page key.
	 * @param array<string, mixed> $config Registered config for the key.
	 * @return int Resolved page ID, or 0 when nothing matches.
	 */
	private static function heal_mapping( string $key, array $config ): int {
		// 1. The ID the Settings screen stores for this page (cheap option read).
		$settings  = get_option( 'wb_listora_settings', array() );
		$settings  = is_array( $settings ) ? $settings : array();
		$candidate = isset( $settings[ $key . '_page' ] ) ? (int) $settings[ $key . '_page' ] : 0;

		// 2. Otherwise an orphan page that already contains the block (a search).
		if ( $candidate <= 0 || ! self::is_live_page( $candidate ) ) {
			$candidate = self::find_orphan( $key );
		}

		if ( $candidate > 0 && self::is_live_page( $candidate ) ) {
			update_option( $config['option_key'], $candidate );
			return $candidate;
		}

		return 0;
	}

	/**
	 * Whether an ID is a live (existing, non-trashed) page.
	 *
	 * @param int $id Page ID.
	 * @return bool
	 */
	private static function is_live_page( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}
		$post = get_post( $id );
		return $post && 'page' === $post->post_type && 'trash' !== $post->post_status;
	}

	/**
	 * Find a page that contains the registered block but isn't mapped.
	 *
	 * Survives renames and translations: detects by stable block name
	 * regardless of what the page is called or where it lives.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Registered page key.
	 * @return int Page ID, or 0 if no orphan match.
	 */
	public static function find_orphan( string $key ): int {
		if ( ! isset( self::$registry[ $key ] ) ) {
			return 0;
		}

		$block = self::$registry[ $key ]['default_block'] ?? '';
		if ( '' === $block ) {
			return 0;
		}

		$pages = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => array( 'publish', 'draft', 'private' ),
				'posts_per_page'   => 1,
				's'                => 'wp:' . $block,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		if ( ! empty( $pages ) ) {
			return (int) $pages[0];
		}

		// Fall back to the legacy shortcode. A page built before the block
		// existed is still this key's page, and refusing to recognise it means
		// offering to create a second one next to it.
		$shortcode = self::$registry[ $key ]['default_shortcode'] ?? '';
		if ( '' === $shortcode ) {
			return 0;
		}

		$pages = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => array( 'publish', 'draft', 'private' ),
				'posts_per_page'   => 1,
				's'                => '[' . $shortcode,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		return empty( $pages ) ? 0 : (int) $pages[0];
	}

	/**
	 * Option holding the keys this site has already auto-created a page for.
	 *
	 * A ledger, not a cache: it records an event that happened, and it is never
	 * recomputed from the current state of the site.
	 */
	const OPTION_CREATED = 'wb_listora_created_pages';

	/**
	 * Post meta recording which registered key a page was created for.
	 */
	const META_KEY = '_wb_listora_page_key';

	/**
	 * Resolve a registered page, creating it once if the site has never had one.
	 *
	 * The single creation path. Resolution is {@see self::get_id()} only: a
	 * mapped page belongs to the owner whatever content they put on it, so an
	 * edit never triggers a duplicate. Creation happens at most once per key
	 * (recorded in a ledger), so a page the owner deleted stays deleted; they
	 * can map a page in Settings or press Create page ({@see self::create()}).
	 *
	 * @since 1.7.0
	 *
	 * @param string $key Registered page key.
	 * @return int Page ID, or 0 when nothing exists and none was created.
	 */
	public static function ensure( string $key ): int {
		if ( ! isset( self::$registry[ $key ] ) ) {
			return 0;
		}

		$id = self::get_id( $key );
		if ( $id > 0 ) {
			self::stamp( $id, $key );

			return $id;
		}

		$created = get_option( self::OPTION_CREATED, array() );
		$created = is_array( $created ) ? $created : array();

		if ( isset( $created[ $key ] ) ) {
			return 0;
		}

		return self::create( $key );
	}

	/**
	 * Create the page for a registered key and map it.
	 *
	 * Unconditional: the caller has decided a page should exist. Used by
	 * {@see self::ensure()} for the once-ever case, and by the Create page
	 * control in Settings when an owner asks for one back.
	 *
	 * @since 1.7.0
	 *
	 * @param string $key Registered page key.
	 * @return int New page ID, or 0 on failure.
	 */
	public static function create( string $key ): int {
		if ( ! isset( self::$registry[ $key ] ) ) {
			return 0;
		}

		$config = self::$registry[ $key ];

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $config['default_slug'],
				'post_title'   => $config['default_title'],
				'post_content' => $config['default_content'],
				// Stamped so this page can still be recognised as belonging to
				// this key when the plugin that registered the key is no longer
				// active — which is exactly when it matters, because that is
				// when the page renders blank.
				'meta_input'   => array(
					self::META_KEY => $key,
				),
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		$page_id = (int) $page_id;

		update_option( $config['option_key'], $page_id );

		$created         = get_option( self::OPTION_CREATED, array() );
		$created         = is_array( $created ) ? $created : array();
		$created[ $key ] = $page_id;
		update_option( self::OPTION_CREATED, $created, false );

		/**
		 * Fires after a registered page is created.
		 *
		 * @since 1.7.0
		 *
		 * @param int    $page_id Newly created page.
		 * @param string $key     Registered page key.
		 */
		do_action( 'wb_listora_page_created', $page_id, $key );

		return $page_id;
	}

	/**
	 * Get a registered page's full config (or empty array).
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Registered page key.
	 * @return array<string, mixed>
	 */
	public static function get_config( string $key ): array {
		return self::$registry[ $key ] ?? array();
	}

	/**
	 * Get every registered key.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public static function keys(): array {
		return array_keys( self::$registry );
	}
}
