<?php
/**
 * Settings > Advanced tab.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings > Advanced: setup wizard, app sign-in, legal links, cache, maintenance, debug and data.
 */
class Advanced_Settings_Tab {

	/**
	 * Render the Advanced tab.
	 */
	public static function render(): void {
		$s      = get_option( Settings_Page::OPTION_KEY, array() );
		$d      = wb_listora_get_default_settings();
		$opt    = esc_attr( Settings_Page::OPTION_KEY );
		$bounds = Settings_Page::bounded_int_ranges();

		// The Maintenance section renders the demo-import progress widget which
		// shares the same CSS as the CSV import widget on the Import/Export tab.
		wp_enqueue_style(
			'listora-import-progress',
			WB_LISTORA_PLUGIN_URL . 'assets/css/admin/import-progress.css',
			array( 'listora-admin' ),
			WB_LISTORA_VERSION
		);
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Setup wizard', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Walk through the first-run setup again: listing types, location, map, pages and demo content. Listing types you leave unticked are switched to draft; your listings and other settings stay.', 'wb-listora' ); ?></p>
				</div>
				<div class="listora-settings-block__body">
					<a class="listora-btn wp-element-button" href="<?php echo esc_url( \WBListora\Admin\Setup_Wizard::rerun_url() ); ?>"><i data-lucide="wand-2" aria-hidden="true"></i> <?php esc_html_e( 'Re-run setup', 'wb-listora' ); ?></a>
				</div>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'App sign-in', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Whether members may sign in to the mobile app by typing their WordPress password. Turning this off leaves the browser-based sign-in, which is the flow a site running two-factor authentication should prefer.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Password sign-in', 'wb-listora' ); ?></th>
							<td>
								<?php
								// Paired hidden 0 so clearing the box actually posts a
								// value — see Settings_Page::sanitize_app_password_login().
								?>
								<input type="hidden" name="wb_listora_app_password_login" value="0" />
								<label for="wb_listora_app_password_login">
									<input
										type="checkbox"
										id="wb_listora_app_password_login"
										name="wb_listora_app_password_login"
										value="1"
										<?php checked( (bool) get_option( 'wb_listora_app_password_login', true ) ); ?>
									/>
									<?php esc_html_e( 'Let members sign in to the app with their WordPress password', 'wb-listora' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'When this is off, the app sends members to the site to sign in instead. Their existing app sign-ins keep working either way.', 'wb-listora' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Mobile app: legal links', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Only needed if you publish the Listora mobile app. The app shows these links, and the app stores ask for them. Your privacy policy comes from Settings > Privacy.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="legal_privacy_policy_url"><?php esc_html_e( 'Privacy policy URL', 'wb-listora' ); ?></label></th>
							<td>
								<?php
								$privacy_url     = (string) get_privacy_policy_url();
								$privacy_page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );
								$privacy_status  = wb_listora_get_page_publish_status( $privacy_page_id );
								?>
								<input type="url" id="legal_privacy_policy_url" value="<?php echo esc_attr( $privacy_url ); ?>" class="regular-text code" readonly />
								<p class="description">
									<?php if ( 'unpublished' === $privacy_status ) : ?>
										<strong><?php esc_html_e( 'Page selected but not published.', 'wb-listora' ); ?></strong>
										<?php
										printf(
											/* translators: 1: page title, 2: link to edit the page. */
											esc_html__( '"%1$s" is not public yet, so the app has no privacy policy to link to. %2$s', 'wb-listora' ),
											esc_html( get_the_title( $privacy_page_id ) ),
											'<a href="' . esc_url( (string) get_edit_post_link( $privacy_page_id ) ) . '">' . esc_html__( 'Publish it', 'wb-listora' ) . '</a>'
										);
										?>
									<?php elseif ( '' === $privacy_url ) : ?>
										<strong><?php esc_html_e( 'Not set.', 'wb-listora' ); ?></strong>
										<?php
										printf(
											/* translators: %s: link to WP Privacy settings. */
											esc_html__( 'The app will have no privacy policy — set one under %s to avoid App Store rejection.', 'wb-listora' ),
											'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings → Privacy', 'wb-listora' ) . '</a>'
										);
										?>
									<?php else : ?>
										<?php
										printf(
											/* translators: %s: link to WP Privacy settings. */
											esc_html__( 'Pulled from %s. Change it there.', 'wb-listora' ),
											'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings → Privacy', 'wb-listora' ) . '</a>'
										);
										?>
									<?php endif; ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="legal_terms_page_id"><?php esc_html_e( 'Terms of service', 'wb-listora' ); ?></label></th>
							<td>
								<?php
								/*
								 * Pick the page you already have — no ID typing,
								 * and no page is ever created for you.
								 *
								 * Terms used to be mapped in two places (this
								 * setting as a raw URL, and a "Terms Page ID"
								 * number field on the submission block), so an
								 * owner mapped the same page twice in two
								 * formats and setting only one left the other
								 * surface without a link. One mapping now, here.
								 */
								$terms_page_id = (int) ( $s['legal_terms_page_id'] ?? 0 );
								// Drafts listed too: a selected draft would vanish from the list,
								// read as "nothing selected", and a save would reset it to 0.
								wp_dropdown_pages(
									array(
										'name'             => esc_attr( $opt ) . '[legal_terms_page_id]',
										'id'               => 'legal_terms_page_id',
										'selected'         => (int) $terms_page_id,
										'show_option_none' => esc_html__( '— Select your terms page —', 'wb-listora' ),
										'option_none_value' => '0',
										'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
									)
								);
								?>
								<?php if ( 'unpublished' === wb_listora_get_page_publish_status( $terms_page_id ) ) : ?>
									<p class="description">
										<strong><?php esc_html_e( 'Page selected but not published.', 'wb-listora' ); ?></strong>
										<?php
										printf(
											/* translators: %s: link to edit the page. */
											esc_html__( 'Members and the app get no terms link until it is public. %s', 'wb-listora' ),
											'<a href="' . esc_url( (string) get_edit_post_link( $terms_page_id ) ) . '">' . esc_html__( 'Publish it', 'wb-listora' ) . '</a>'
										);
										?>
									</p>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Choose the terms page this site already has. Members must accept these terms to submit a listing, and the mobile app links to the same page.', 'wb-listora' ); ?></p>

								<p style="margin-top:.75rem;">
									<label for="legal_terms_url"><?php esc_html_e( 'Or an external URL', 'wb-listora' ); ?></label><br />
									<input type="url" id="legal_terms_url" name="<?php echo esc_attr( $opt ); ?>[legal_terms_url]" value="<?php echo esc_attr( $s['legal_terms_url'] ?? $d['legal_terms_url'] ); ?>" class="regular-text code" placeholder="https://example.com/terms" />
								</p>
								<p class="description"><?php esc_html_e( 'Only needed if your terms live outside this site. The selected page wins when both are set.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="legal_community_guidelines_url"><?php esc_html_e( 'Community guidelines URL', 'wb-listora' ); ?></label></th>
							<td>
								<input type="url" id="legal_community_guidelines_url" name="<?php echo esc_attr( $opt ); ?>[legal_community_guidelines_url]" value="<?php echo esc_attr( $s['legal_community_guidelines_url'] ?? $d['legal_community_guidelines_url'] ); ?>" class="regular-text code" placeholder="https://example.com/guidelines" />
								<p class="description"><?php esc_html_e( 'Optional. Rules for reviews and user submissions — strengthens the user-generated-content story at app review.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="legal_abuse_contact_email"><?php esc_html_e( 'Abuse contact email', 'wb-listora' ); ?></label></th>
							<td>
								<input type="email" id="legal_abuse_contact_email" name="<?php echo esc_attr( $opt ); ?>[legal_abuse_contact_email]" value="<?php echo esc_attr( $s['legal_abuse_contact_email'] ?? $d['legal_abuse_contact_email'] ); ?>" class="regular-text" placeholder="abuse@example.com" />
								<p class="description"><?php esc_html_e( 'Where people report objectionable content. The app stores require one.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Cache', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Time-to-live for cached search results and facet counts. Higher values improve performance but delay new-listing visibility.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="search_cache_ttl"><?php esc_html_e( 'Search results TTL', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="search_cache_ttl" name="<?php echo esc_attr( $opt ); ?>[search_cache_ttl]" value="<?php echo esc_attr( $s['search_cache_ttl'] ?? $d['search_cache_ttl'] ); ?>" min="<?php echo esc_attr( (string) $bounds['search_cache_ttl'][0] ); ?>" max="<?php echo esc_attr( (string) $bounds['search_cache_ttl'][1] ); ?>" class="small-text" />
								<span><?php esc_html_e( 'minutes', 'wb-listora' ); ?></span>
								<p class="description"><?php esc_html_e( 'How long cached search result sets are kept. Set to 0 to disable caching for search queries.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Maintenance', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Rebuild internal indexes or re-run the setup wizard to reconfigure listing types and demo data.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Search index', 'wb-listora' ); ?></th>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=listora-settings&action=reindex' ), 'listora_reindex' ) ); ?>" class="button">
									<i data-lucide="refresh-cw"></i> <?php esc_html_e( 'Rebuild Search Index', 'wb-listora' ); ?>
								</a>
								<p class="description"><?php esc_html_e( 'Regenerates the denormalized search_index table. Run after bulk-editing listings or changing custom fields.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<?php Demo_Import_Admin::render_rows(); ?>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Debug', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Developer tools for tracing plugin behavior. Leave disabled on production unless actively troubleshooting.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Debug logging', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[debug_logging]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[debug_logging]" value="1" <?php checked( $s['debug_logging'] ?? $d['debug_logging'] ); ?> />
									<?php esc_html_e( 'Log plugin actions and queries to debug.log', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Requires WP_DEBUG and WP_DEBUG_LOG in wp-config.php. Output appears in wp-content/debug.log.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Data Management', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php /* translators: %s: product name */ echo esc_html( sprintf( __( 'Control what happens to plugin data when %s is uninstalled.', 'wb-listora' ), wb_listora_brand_name() ) ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Uninstall', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[delete_on_uninstall]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[delete_on_uninstall]" value="1" <?php checked( $s['delete_on_uninstall'] ?? $d['delete_on_uninstall'] ); ?> />
									<?php /* translators: %s: product name */ echo esc_html( sprintf( __( 'Permanently delete all %s data on plugin uninstall', 'wb-listora' ), wb_listora_brand_name() ) ); ?>
								</label>
								<p class="description listora-description--danger"><?php esc_html_e( 'Warning: this removes every listing, review, favorite, claim, custom table, and setting. Cannot be undone.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Privacy', 'wb-listora' ); ?></th>
							<td>
								<?php
								// Pointer row only — WB Listora's personal data (listings,
								// reviews, favorites, claims) is already wired into WP core's
								// privacy tools via Privacy_Exporter / Privacy_Eraser
								// (includes/privacy/). These buttons link to the core screens;
								// no duplicate export/erase implementation here.
								?>
								<a href="<?php echo esc_url( admin_url( 'export-personal-data.php' ) ); ?>" class="button">
									<i data-lucide="download"></i> <?php esc_html_e( 'Export Personal Data', 'wb-listora' ); ?>
								</a>
								<a href="<?php echo esc_url( admin_url( 'erase-personal-data.php' ) ); ?>" class="button">
									<i data-lucide="eraser"></i> <?php esc_html_e( 'Erase Personal Data', 'wb-listora' ); ?>
								</a>
								<p class="description"><?php /* translators: %s: product name */ echo esc_html( sprintf( __( '%s registers its listings, reviews, favorites, and claims with the WordPress privacy tools. Use the core Export / Erase Personal Data screens to fulfill GDPR data requests — plugin data is included automatically.', 'wb-listora' ), wb_listora_brand_name() ) ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<?php
			// Health Check — moved here from a standalone admin submenu so all
			// diagnostics + maintenance live next to one another. The legacy
			// `?page=listora-health` URL redirects to this tab anchor.
			if ( class_exists( '\\WBListora\\Admin\\Health_Check' ) ) {
				$health = new \WBListora\Admin\Health_Check();
				if ( method_exists( $health, 'render_section' ) ) {
					$health->render_section();
				}
			}
			?>

		</div>
		<?php
	}
}
