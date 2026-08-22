<?php
/**
 * Page Registry — public helper functions + Free's page registrations.
 *
 * Required from wb-listora.php after the autoloader so consumers anywhere
 * (Free, Pro, themes, third-party plugins) can call:
 *
 *   wb_listora_get_page_url( 'dashboard', [ 'tab' => 'credits' ] )
 *
 * without knowing about option keys, slugs, or translation plugins.
 *
 * Free registers its 3 canonical pages on `init`@5 (early enough for any
 * frontend `init`@10 code). Pro registers `compare` on `wb_listora_loaded`.
 *
 * @package WBListora
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_get_page_id' ) ) {

	/**
	 * Resolve a Listora-managed page ID by stable key.
	 *
	 * Translation-aware (Polylang / WPML auto-detected) and overridable via
	 * the `wb_listora_page_id` filter. Returns 0 when the page is missing,
	 * trashed, or the key isn't registered — caller decides the fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Registered page key (e.g. 'dashboard', 'directory', 'compare').
	 * @param string $context Optional. Caller context passed through to filter.
	 * @return int Page ID or 0.
	 */
	function wb_listora_get_page_id( string $key, string $context = '' ): int {
		return \WBListora\Core\Page_Registry::get_id( $key, $context );
	}
}

if ( ! function_exists( 'wb_listora_get_page_config' ) ) {
	/**
	 * Read a registered page's config (title, slug, block, owner).
	 *
	 * @since 1.7.0
	 *
	 * @param string $key Registered page key.
	 * @return array<string, mixed> Empty array when the key is not registered.
	 */
	function wb_listora_get_page_config( string $key ): array {
		return \WBListora\Core\Page_Registry::get_config( $key );
	}
}

if ( ! function_exists( 'wb_listora_get_public_page_url' ) ) {
	/**
	 * Resolve a page URL only when a visitor could actually open it.
	 *
	 * Use this for anything a member clicks. {@see wb_listora_get_page_url()}
	 * answers where the page is, including when it is a draft nobody but an
	 * editor can see.
	 *
	 * @since 1.7.0
	 *
	 * @param string               $key  Registered page key.
	 * @param array<string, mixed> $args Optional query args.
	 * @return string URL or ''.
	 */
	function wb_listora_get_public_page_url( string $key, array $args = array() ): string {
		return \WBListora\Core\Page_Registry::get_public_url( $key, $args );
	}
}

if ( ! function_exists( 'wb_listora_ensure_page' ) ) {
	/**
	 * Resolve a Listora-managed page, creating it once if the site never had one.
	 *
	 * The extension-safe entry point Pro uses (INV-3), so Pro never has to
	 * write its own create-a-page routine — which is how the site ended up with
	 * three of them, two of which created duplicates when an owner edited the
	 * page. See {@see \WBListora\Core\Page_Registry::ensure()}.
	 *
	 * Creates at most once per key per site. A page the owner deleted stays
	 * deleted.
	 *
	 * @since 1.7.0
	 *
	 * @param string $key Registered page key.
	 * @return int Page ID, or 0.
	 */
	function wb_listora_ensure_page( string $key ): int {
		return \WBListora\Core\Page_Registry::ensure( $key );
	}
}

if ( ! function_exists( 'wb_listora_create_page' ) ) {
	/**
	 * Create the page for a registered key, whether or not one was made before.
	 *
	 * For an explicit request — the Create page control in Settings. Prefer
	 * {@see wb_listora_ensure_page()} for automatic setup.
	 *
	 * @since 1.7.0
	 *
	 * @param string $key Registered page key.
	 * @return int New page ID, or 0 on failure.
	 */
	function wb_listora_create_page( string $key ): int {
		return \WBListora\Core\Page_Registry::create( $key );
	}
}

if ( ! function_exists( 'wb_listora_get_page_url' ) ) {

	/**
	 * Resolve a Listora-managed page URL by stable key, with optional query args.
	 *
	 * Returns an empty string when the page is missing — never falls back to
	 * `home_url('/')` or admin URLs internally. Callers that need a fallback
	 * should compose it explicitly:
	 *
	 *   $url = wb_listora_get_page_url( 'dashboard', [ 'tab' => 'credits' ] );
	 *   if ( '' === $url ) { $url = home_url( '/' ); }
	 *
	 * @since 1.0.0
	 *
	 * @param string               $key  Registered page key.
	 * @param array<string, mixed> $args Optional query args appended via add_query_arg.
	 * @return string URL or empty string.
	 */
	function wb_listora_get_page_url( string $key, array $args = array() ): string {
		return \WBListora\Core\Page_Registry::get_url( $key, $args );
	}
}

if ( ! function_exists( 'wb_listora_get_registered_pages' ) ) {

	/**
	 * Inspect every registered page with current id/url/status.
	 *
	 * Used by the Settings → Pages tab and by the post-activation review notice.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Registry keyed by page key.
	 */
	function wb_listora_get_registered_pages(): array {
		return \WBListora\Core\Page_Registry::all();
	}
}

/**
 * Mark the post-activation review-pages notice as pending.
 *
 * Called from the activator after pages are ensured. Sets a 7-day transient
 * so the next admin pageload renders a dismissible "Review your pages" banner.
 *
 * @since 1.0.0
 * @return void
 */
function wb_listora_mark_pages_review_pending(): void {
	set_transient( 'wb_listora_pages_review_pending', '1', 7 * DAY_IN_SECONDS );
}

/**
 * Whether the "Review your pages" admin notice should render.
 *
 * Surfaces once after activation so admins know the page mapping was created
 * automatically and can be remapped on Settings → General → Pages.
 *
 * Shared by the renderer and the script enqueue so the two can never disagree
 * about which pageloads show the notice.
 *
 * Suppressed when:
 *   - The transient isn't set (default state, no notice).
 *   - The current user dismissed it (user-meta flag).
 *   - The current screen IS the Listora settings page (don't shout while
 *     the admin is already there).
 *
 * @since 1.0.0
 * @return bool
 */
function wb_listora_should_show_pages_review_notice(): bool {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}

	if ( ! get_transient( 'wb_listora_pages_review_pending' ) ) {
		return false;
	}

	$user_id = get_current_user_id();
	if ( $user_id && get_user_meta( $user_id, 'wb_listora_pages_review_dismissed', true ) ) {
		return false;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && false !== strpos( (string) $screen->id, 'listora-settings' ) ) {
		return false;
	}

	return true;
}

/**
 * Enqueue the script that persists the notice's X dismissal.
 *
 * Gated on the same conditions as the notice itself, so it loads on exactly
 * the pageloads that render it — `admin_notices` fires on every admin screen,
 * including ones where the plugin enqueues nothing else.
 *
 * @since 1.4.2
 * @return void
 */
function wb_listora_enqueue_pages_review_notice_script(): void {
	if ( ! wb_listora_should_show_pages_review_notice() ) {
		return;
	}

	wp_enqueue_script(
		'listora-pages-review-notice',
		WB_LISTORA_PLUGIN_URL . 'assets/js/admin/pages-review-notice.js',
		array(),
		WB_LISTORA_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'wb_listora_enqueue_pages_review_notice_script' );

function wb_listora_render_pages_review_notice(): void {
	if ( ! wb_listora_should_show_pages_review_notice() ) {
		return;
	}

	$settings_url = admin_url( 'admin.php?page=listora-settings&tab=general#general' );
	$dismiss_url  = wp_nonce_url(
		add_query_arg( 'wb_listora_dismiss_pages_review', '1' ),
		'wb_listora_dismiss_pages_review'
	);

	?>
	<div class="notice listora-notice notice-info is-dismissible listora-pages-review-notice" data-listora-dismiss-url="<?php echo esc_url( $dismiss_url ); ?>">
		<p>
			<strong><?php esc_html_e( 'WB Listora is set up.', 'wb-listora' ); ?></strong>
			<?php
			$registered = wb_listora_get_registered_pages();
			$count      = count( array_filter( $registered, static fn( $r ) => 'linked' === ( $r['status'] ?? '' ) ) );
			printf(
				/* translators: %d: number of Listora pages */
				esc_html( _n( '%d page is mapped — review or remap on Settings → General → Pages.', '%d pages are mapped — review or remap on Settings → General → Pages.', max( 1, $count ), 'wb-listora' ) ),
				(int) $count
			);
			?>
			<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary"><?php esc_html_e( 'Review pages', 'wb-listora' ); ?></a>
			<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link"><?php esc_html_e( 'Dismiss', 'wb-listora' ); ?></a>
		</p>
	</div>
	<?php
	// The X that core paints on `is-dismissible` notices is client-side only.
	// assets/js/admin/pages-review-notice.js (enqueued above) wires it to the
	// same nonce'd endpoint as the "Dismiss" link so it actually persists.
}
add_action( 'admin_notices', 'wb_listora_render_pages_review_notice' );

/**
 * Handle "Dismiss" link on the review-pages notice.
 *
 * @since 1.0.0
 * @return void
 */
function wb_listora_handle_pages_review_dismiss(): void {
	if ( ! is_admin() || ! isset( $_GET['wb_listora_dismiss_pages_review'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'wb_listora_dismiss_pages_review' );

	$user_id = get_current_user_id();
	if ( $user_id ) {
		update_user_meta( $user_id, 'wb_listora_pages_review_dismissed', '1' );
	}

	delete_transient( 'wb_listora_pages_review_pending' );

	wp_safe_redirect( remove_query_arg( array( 'wb_listora_dismiss_pages_review', '_wpnonce' ) ) );
	exit;
}
add_action( 'admin_init', 'wb_listora_handle_pages_review_dismiss' );

if ( ! function_exists( 'wb_listora_register_page' ) ) {

	/**
	 * Public registration helper for the Page Registry.
	 *
	 * Consumers (Pro, themes, third-party plugins) MUST use this helper instead
	 * of touching `\WBListora\Core\Page_Registry::register()` directly. Going
	 * through this function preserves the architecture invariant INV-3
	 * (Pro→Free namespace coupling) and keeps the internal class free to
	 * refactor without breaking extensions.
	 *
	 * Hook into `wb_listora_register_pages` and call this helper from your
	 * listener — that's the contract.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $key    Stable page key (e.g. 'compare', 'analytics').
	 * @param array<string, mixed>  $config See Page_Registry::register() for the shape.
	 * @return void
	 */
	function wb_listora_register_page( string $key, array $config ): void {
		\WBListora\Core\Page_Registry::register( $key, $config );
	}
}

/**
 * Register Free's 3 canonical pages on `init`@5.
 *
 * After Free's pages register, fire `wb_listora_register_pages` so Pro
 * and themes can register their own keys without touching the internal
 * Page_Registry class. The action runs on `init`@5 (priority 6 inner) so
 * any `init`@10 consumer sees a fully-populated registry.
 */
add_action(
	'init',
	static function (): void {
		\WBListora\Core\Page_Registry::register(
			'directory',
			array(
				'default_slug'    => 'listings',
				'default_title'   => __( 'Directory', 'wb-listora' ),
				'default_block'   => 'listora/listing-grid',
				'default_content' => "<!-- wp:listora/listing-search /-->\n\n<!-- wp:listora/listing-map {\"height\":\"350px\"} /-->\n\n<!-- wp:listora/listing-grid {\"columns\":3} /-->",
				'option_key'      => 'wb_listora_directory_page_id',
				'owner'           => 'free',
				'menu_candidate'  => true,
				'role'            => 'frontend',
				'description'     => __( 'Public directory landing page — search, map, and listing grid.', 'wb-listora' ),
			)
		);

		\WBListora\Core\Page_Registry::register(
			'submission',
			array(
				'default_slug'    => 'add-listing',
				'default_title'   => __( 'Add Listing', 'wb-listora' ),
				'default_block'   => 'listora/listing-submission',
				'default_content' => '<!-- wp:listora/listing-submission /-->',
				'option_key'      => 'wb_listora_submission_page_id',
				'owner'           => 'free',
				'menu_candidate'  => true,
				'role'            => 'frontend',
				'description'     => __( 'Frontend listing submission wizard for end users.', 'wb-listora' ),
			)
		);

		\WBListora\Core\Page_Registry::register(
			'dashboard',
			array(
				'default_slug'    => 'my-dashboard',
				'default_title'   => __( 'My Dashboard', 'wb-listora' ),
				'default_block'   => 'listora/user-dashboard',
				'default_content' => '<!-- wp:listora/user-dashboard /-->',
				'option_key'      => 'wb_listora_dashboard_page_id',
				'owner'           => 'free',
				'menu_candidate'  => true,
				'role'            => 'frontend',
				'description'     => __( 'Logged-in user dashboard — listings, reviews, favorites, credits, and profile tabs.', 'wb-listora' ),
			)
		);

		/**
		 * Pro and themes register their own page keys here.
		 *
		 * Listeners MUST call `wb_listora_register_page( $key, $config )` —
		 * touching `\WBListora\Core\Page_Registry::register` directly violates
		 * the architecture contract (INV-3). The helper accepts the same args
		 * and is the documented public surface.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wb_listora_register_pages' );
	},
	5
);
