<?php
/**
 * Page Registry — central resolver for Listora-managed WordPress pages.
 *
 * Solves the duplication that came from 12+ scattered call sites each calling
 * `get_option( 'wb_listora_*_page_id' )` + `get_permalink()` + their own
 * fallback. Code now calls `wb_listora_get_page_url( $key, $args )` and gets
 * a translation-aware, theme-overridable URL — or a clear empty string if
 * the page is missing.
 *
 * Read-side resolution order:
 *   1. Look up the registered key's `option_key` in wp_options.
 *   2. Translation plugins remap (Polylang / WPML auto-detected).
 *   3. `wb_listora_page_id` filter for theme/plugin overrides.
 *   4. `get_permalink()` → URL.
 *   5. `add_query_arg( $args, $url )` for tab/anchor passthrough.
 *   6. `wb_listora_page_url` filter for final URL override.
 *
 * Write-side (creation) is delegated to `Activator::ensure_essential_pages()`
 * which iterates the registry and creates missing pages with default content.
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
	 *   - default_slug    string  WP post_name fallback if the page must be created.
	 *   - default_title   string  WP post_title fallback.
	 *   - default_block   string  Block name used to detect orphans (page contains the block but isn't mapped).
	 *   - default_shortcode string Legacy shortcode equivalent, also used for orphan detection.
	 *   - default_content string  WP post_content used when creating from scratch.
	 *   - option_key      string  wp_options row that stores the resolved ID.
	 *   - owner           string  'free' | 'pro' | <plugin-slug>.
	 *   - role            string  'frontend' | 'admin'.
	 *   - description     string  Localized human description shown on Settings → Pages.
	 *
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

		// Self-heal a missing/stale mapping. The option row is the registry's
		// source of truth, but Settings stores page IDs separately
		// (wb_listora_settings[{key}_page]) and a site may already have a page
		// that contains the registered block. When the option is unset (0) but a
		// real matching page exists, adopt it and persist the mapping — so the
		// registry, the Health Check, and the URL helpers stop disagreeing. They
		// previously reported orphan/missing and the dashboard helper 404'd to
		// the default slug (/my-dashboard/) while the working page (/my-listings/)
		// sat unmapped.
		// The stored ID is only useful if it still points at a live page. An
		// owner who deletes or trashes the mapped page leaves the option holding
		// a dead ID, and this used to accept it unchecked — get_permalink()
		// then returned '' and every caller silently degraded: the canonical tag
		// stopped rendering, CTAs fell back to whatever secondary URL they knew,
		// and nothing anywhere said why. The docblock above always described
		// healing a "missing/stale" mapping; only the missing half was
		// implemented.
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
			$translated = apply_filters( 'wpml_object_id', $id, 'page', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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
	 * {@see self::get_url()} answers "where is this page", which is not the
	 * same question as "may I send someone there", and every caller that links
	 * a member somewhere needs the second one. Four of them worked it out
	 * separately and got four different answers:
	 *
	 *   - Compare required `publish` AND that the page still contained the
	 *     block or the shortcode — so an owner who rebuilt the page with their
	 *     own layout lost the URL entirely.
	 *   - Buy Credits required `publish`. Correct, but its own copy.
	 *   - The Needs canonical ran `url_to_postid()` on a URL it had just
	 *     derived from a post ID, to get back the ID it started with.
	 *   - Everything else asked nothing and would happily hand a member
	 *     `?page_id=12&preview=true` for a draft.
	 *
	 * Publish status is the right test; page CONTENT never is. A mapped page
	 * belongs to the owner whatever they put on it — the same rule that governs
	 * {@see self::ensure()}.
	 *
	 * Delegates to core's `is_post_publicly_viewable()`, which also covers
	 * private and password-protected pages and any future status core adds.
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
	 * The single creation path. Three copies of "ensure a page exists" grew up
	 * separately — Free's activator, Pro's Compare page, Pro's Buy Credits page
	 * — and the two Pro copies decided whether the mapped page "counts" by
	 * re-inspecting its CONTENT: publish status, and in one case whether the
	 * block was still in it. That is the wrong question. It made an owner's
	 * ordinary edit look like a missing page:
	 *
	 *   Swap the Buy Credits block for a shortcode and your own copy, and the
	 *   next call decided that was not its page and created `buy-credits-2`.
	 *   The plugin then linked to the new empty one while the customised page
	 *   sat there orphaned. Reproduced before this was written.
	 *
	 * A mapped page belongs to the owner whatever they put on it. So resolution
	 * here is {@see self::get_id()} and nothing else — which matches on the
	 * registered BLOCK rather than a title search, adopts an orphan, and heals a
	 * stale pointer.
	 *
	 * Creation happens at most once per key per site, recorded in a ledger. A
	 * page the owner deleted stays deleted: re-creating it would be the plugin
	 * overruling a deliberate act, and doing so silently, on a schedule they
	 * cannot see. They can map any page from Settings, or press Create page,
	 * which calls {@see self::create()} directly and is not a resurrection
	 * because they asked for it.
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
