<?php
/**
 * Listora page mapping in Settings.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Page mapping: the General tab Pages section and the Create page action.
 */
class Pages_Settings {

	/**
	 * Render the Pages section inside the General tab.
	 *
	 * Iterates Page_Registry::all(): each registered page (Free's directory,
	 * submission, dashboard, Pro's compare, other plugins') gets a status
	 * badge, a page picker and an edit link, so admins can remap pages
	 * without knowing option names (e.g. after a slug collision created
	 * `my-dashboard-2`). Saved by the General tab's "Save Changes".
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_section(): void {
		if ( ! class_exists( '\\WBListora\\Core\\Page_Registry' ) ) {
			return;
		}

		$pages = \WBListora\Core\Page_Registry::all();
		if ( empty( $pages ) ) {
			return;
		}

		// Build a single dropdown of all pages once — reused per registered key.
		$all_pages = get_pages(
			array(
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);
		?>
		<section class="listora-settings-block">
			<div class="listora-settings-block__head">
				<h3 class="listora-settings-block__title"><?php esc_html_e( 'Pages', 'wb-listora' ); ?></h3>
				<p class="listora-settings-block__desc"><?php esc_html_e( 'Map each Listora-managed page to a WordPress page. Listora auto-creates these on activation, but you can remap them anytime — useful when a theme or another plugin owns the slug, or when the page was renamed.', 'wb-listora' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ( $pages as $key => $page ) : ?>
						<?php
						$option_key   = (string) $page['option_key'];
						$current_id   = (int) $page['id'];
						$status       = (string) $page['status'];
						$role         = (string) $page['role'];
						$status_class = 'listora-page-status listora-page-status--' . sanitize_html_class( $status );

						$status_label = __( 'Unknown', 'wb-listora' );
						switch ( $status ) {
							case 'linked':
								$status_label = __( 'Linked', 'wb-listora' );
								break;
							case 'missing':
								$status_label = __( 'Missing', 'wb-listora' );
								break;
							case 'trashed':
								$status_label = __( 'Trashed', 'wb-listora' );
								break;
							case 'orphan':
								$status_label = __( 'Orphan detected', 'wb-listora' );
								break;
							case 'inactive':
								$status_label = __( 'Feature off', 'wb-listora' );
								break;
						}
						?>
						<tr>
							<th scope="row">
								<label for="page-<?php echo esc_attr( $key ); ?>">
									<?php echo esc_html( $page['default_title'] ); ?>
								</label>
								<span class="<?php echo esc_attr( $status_class ); ?>" title="<?php echo esc_attr( $status_label ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
							</th>
							<td>
								<select id="page-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $option_key ); ?>" class="regular-text">
									<option value="0"><?php esc_html_e( '— Not mapped —', 'wb-listora' ); ?></option>
									<?php foreach ( $all_pages as $wp_page ) : ?>
										<option value="<?php echo esc_attr( (string) $wp_page->ID ); ?>" <?php selected( $current_id, (int) $wp_page->ID ); ?>>
											<?php echo esc_html( $wp_page->post_title ? $wp_page->post_title : sprintf( '#%d', $wp_page->ID ) ); ?>
											<?php if ( 'publish' !== $wp_page->post_status ) : ?>
												— <?php echo esc_html( $wp_page->post_status ); ?>
											<?php endif; ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php echo esc_html( $page['description'] ); ?>
									<?php if ( $current_id > 0 && 'linked' === $status ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $current_id ) ); ?>" target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'Edit page', 'wb-listora' ); ?>
										</a>
										|
										<a href="<?php echo esc_url( (string) get_permalink( $current_id ) ); ?>" target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'View page', 'wb-listora' ); ?>
										</a>
									<?php elseif ( 'orphan' === $status ) : ?>
										<?php
										$orphan_id = \WBListora\Core\Page_Registry::find_orphan( $key );
										if ( $orphan_id > 0 ) :
											$orphan_post = get_post( $orphan_id );
											?>
											<strong><?php esc_html_e( 'Detected:', 'wb-listora' ); ?></strong>
											<?php echo esc_html( $orphan_post ? $orphan_post->post_title : '#' . $orphan_id ); ?>
											— <?php esc_html_e( 'select it from the dropdown above and Save Changes to relink.', 'wb-listora' ); ?>
										<?php endif; ?>
									<?php elseif ( 'inactive' === $status ) : ?>
										<em><?php esc_html_e( 'This page exists but its feature is switched off, so it has nothing to show and the site returns a 404 for it. Turn the feature back on, or delete the page.', 'wb-listora' ); ?></em>
									<?php elseif ( 'missing' === $status ) : ?>
										<em><?php esc_html_e( 'No page mapped — this Listora feature will fall back to slug-based URL or hide.', 'wb-listora' ); ?></em>
										<?php
										// Pages are created once per site and never
										// re-created behind the owner's back, so a page
										// they deleted stays deleted. This is how they
										// ask for it back, and the only reason refusing
										// to resurrect is not a dead end.
										$create_url = wp_nonce_url(
											add_query_arg(
												array(
													'listora_create_page' => $key,
												),
												admin_url( 'admin-post.php?action=wb_listora_create_page' )
											),
											'wb_listora_create_page_' . $key
										);
										?>
										<a href="<?php echo esc_url( $create_url ); ?>" class="button button-secondary">
											<?php esc_html_e( 'Create page', 'wb-listora' ); ?>
										</a>
									<?php endif; ?>
								</p>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	/**
	 * Confirm the outcome of a Create page request.
	 *
	 * The row redrawing as "Linked" is real feedback, but it is feedback you
	 * have to already be looking at the right row to notice, on a screen with
	 * ten of them. Say what happened.
	 */
	public static function created_page_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of an outcome; the state change was nonce-checked in create_page().
		if ( ! isset( $_GET['listora_page_created'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
		$page_id = absint( $_GET['listora_page_created'] );

		if ( $page_id <= 0 ) {
			printf(
				'<div class="notice notice-error listora-notice"><p>%s</p></div>',
				esc_html__( 'The page could not be created. Check that you have permission to publish pages.', 'wb-listora' )
			);
			return;
		}

		printf(
			'<div class="notice notice-success listora-notice"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Page created and mapped.', 'wb-listora' ),
			esc_url( (string) get_edit_post_link( $page_id ) ),
			esc_html__( 'Edit it', 'wb-listora' )
		);
	}

	/**
	 * Create a registered page an owner has asked for.
	 *
	 * Hooked to admin-post action `wb_listora_create_page`. Deliberately
	 * separate from the automatic path: {@see \WBListora\Core\Page_Registry::ensure()}
	 * creates once per site and then never again, because silently re-creating
	 * a page someone deleted is the plugin overruling them. An explicit request
	 * is not that, so this calls create() directly.
	 */
	public static function create_page(): void {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wb-listora' ) );
		}

		$key = isset( $_GET['listora_create_page'] ) ? sanitize_key( wp_unslash( $_GET['listora_create_page'] ) ) : '';
		if ( '' === $key ) {
			wp_die( esc_html__( 'No page was specified.', 'wb-listora' ) );
		}

		check_admin_referer( 'wb_listora_create_page_' . $key );

		$page_id = \WBListora\Core\Page_Registry::create( $key );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                 => 'listora-settings',
					'tab'                  => 'general',
					'listora_page_created' => $page_id > 0 ? $page_id : 0,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
