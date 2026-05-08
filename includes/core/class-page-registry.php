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

		// Polylang — translate to the current locale's matching page.
		if ( $id > 0 && function_exists( 'pll_get_post' ) ) {
			$translated = \pll_get_post( $id );
			if ( $translated ) {
				$id = (int) $translated;
			}
		}

		// WPML — same idea via the canonical filter.
		if ( $id > 0 && defined( 'ICL_LANGUAGE_CODE' ) ) {
			$translated = apply_filters( 'wpml_object_id', $id, 'page', true );
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
			$id     = self::get_id( $key );
			$url    = $id > 0 ? (string) get_permalink( $id ) : '';
			$status = self::status_for( $key );
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
		$id     = (int) get_option( $config['option_key'], 0 );

		if ( $id > 0 ) {
			$post = get_post( $id );
			if ( ! $post || 'page' !== $post->post_type ) {
				return 'missing';
			}
			if ( 'trash' === $post->post_status ) {
				return 'trashed';
			}
			return 'linked';
		}

		// No mapping yet — see if a page somewhere contains the registered block.
		if ( '' !== $config['default_block'] && self::find_orphan( $key ) > 0 ) {
			return 'orphan';
		}

		return 'missing';
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
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				's'              => 'wp:' . $block,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'suppress_filters' => false,
			)
		);

		return empty( $pages ) ? 0 : (int) $pages[0];
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
