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
 * Render the dismissible "Review your pages" admin notice.
 *
 * Surfaces once after activation so admins know the page mapping was created
 * automatically and can be remapped on Settings → General → Pages.
 *
 * Suppressed when:
 *   - The transient isn't set (default state, no notice).
 *   - The current user dismissed it (user-meta flag).
 *   - The current screen IS the Listora settings page (don't shout while
 *     the admin is already there).
 *
 * @since 1.0.0
 * @return void
 */
function wb_listora_render_pages_review_notice(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! get_transient( 'wb_listora_pages_review_pending' ) ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( $user_id && get_user_meta( $user_id, 'wb_listora_pages_review_dismissed', true ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && false !== strpos( (string) $screen->id, 'listora-settings' ) ) {
		return;
	}

	$settings_url = admin_url( 'admin.php?page=listora-settings&tab=general#general' );
	$dismiss_url  = wp_nonce_url(
		add_query_arg( 'wb_listora_dismiss_pages_review', '1' ),
		'wb_listora_dismiss_pages_review'
	);

	?>
	<div class="notice notice-info is-dismissible listora-pages-review-notice">
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

/**
 * Register Free's 3 canonical pages on `init`@5.
 *
 * Pro and themes register additional keys on `wb_listora_loaded` (Pro uses it)
 * or on `init` with a higher priority (themes can use it).
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
				'role'            => 'frontend',
				'description'     => __( 'Logged-in user dashboard — listings, reviews, favorites, credits, and profile tabs.', 'wb-listora' ),
			)
		);
	},
	5
);
