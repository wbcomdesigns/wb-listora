<?php
/**
 * Settings Page — WP Settings API with tabbed interface.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the plugin settings page with 7 tabs.
 */
class Settings_Page {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wb_listora_settings';

	/**
	 * Register settings.
	 */
	public static function register() {
		register_setting(
			'wb_listora_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize settings on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults  = wb_listora_get_default_settings();
		$sanitized = array();

		foreach ( $defaults as $key => $default ) {
			if ( ! isset( $input[ $key ] ) ) {
				$sanitized[ $key ] = is_bool( $default ) ? false : $default;
				continue;
			}

			$value = $input[ $key ];

			if ( is_bool( $default ) ) {
				$sanitized[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$sanitized[ $key ] = (int) $value;
			} elseif ( is_float( $default ) ) {
				$sanitized[ $key ] = (float) $value;
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		// Flush rewrites if slugs changed.
		$old = get_option( self::OPTION_KEY, array() );
		if ( ( $old['listing_slug'] ?? '' ) !== ( $sanitized['listing_slug'] ?? '' ) ) {
			add_action( 'shutdown', 'flush_rewrite_rules' );
		}

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}

		$active_tab = sanitize_text_field( $_GET['tab'] ?? 'general' );

		$tabs = array(
			'general'       => __( 'General', 'wb-listora' ),
			'maps'          => __( 'Maps', 'wb-listora' ),
			'submissions'   => __( 'Submissions', 'wb-listora' ),
			'notifications' => __( 'Notifications', 'wb-listora' ),
			'seo'           => __( 'SEO', 'wb-listora' ),
			'advanced'      => __( 'Advanced', 'wb-listora' ),
		);

		/**
		 * Filter settings tabs. Pro can add tabs here.
		 */
		$tabs = apply_filters( 'wb_listora_settings_tabs', $tabs );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Listora Settings', 'wb-listora' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-settings&tab=' . $tab_key ) ); ?>"
					class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $tab_label ); ?>
				</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" style="margin-top: 1rem;">
				<?php
				settings_fields( 'wb_listora_settings_group' );

				switch ( $active_tab ) {
					case 'maps':
						self::render_maps_tab();
						break;
					case 'submissions':
						self::render_submissions_tab();
						break;
					case 'notifications':
						self::render_notifications_tab();
						break;
					case 'seo':
						self::render_seo_tab();
						break;
					case 'advanced':
						self::render_advanced_tab();
						break;
					default:
						self::render_general_tab();
						break;
				}

				/**
				 * Fires to render Pro tab content.
				 *
				 * @param string $active_tab Current active tab.
				 */
				do_action( 'wb_listora_settings_tab_content', $active_tab );

				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// ─── Tab Renderers ───

	private static function render_general_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="per_page"><?php esc_html_e( 'Listings per page', 'wb-listora' ); ?></label></th>
				<td><input type="number" id="per_page" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[per_page]" value="<?php echo esc_attr( $s['per_page'] ?? $d['per_page'] ); ?>" min="1" max="100" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="listing_slug"><?php esc_html_e( 'Listing URL slug', 'wb-listora' ); ?></label></th>
				<td><input type="text" id="listing_slug" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[listing_slug]" value="<?php echo esc_attr( $s['listing_slug'] ?? $d['listing_slug'] ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Single listing URL: /listing/{slug}/', 'wb-listora' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="currency"><?php esc_html_e( 'Currency', 'wb-listora' ); ?></label></th>
				<td>
					<select id="currency" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[currency]">
						<?php
						$currencies = array( 'USD', 'EUR', 'GBP', 'JPY', 'INR', 'AUD', 'CAD', 'CHF', 'CNY', 'BRL' );
						$current    = $s['currency'] ?? $d['currency'];
						foreach ( $currencies as $c ) {
							printf( '<option value="%s" %s>%s</option>', esc_attr( $c ), selected( $current, $c, false ), esc_html( $c ) );
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Distance unit', 'wb-listora' ); ?></th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[distance_unit]" value="km" <?php checked( $s['distance_unit'] ?? $d['distance_unit'], 'km' ); ?> /> <?php esc_html_e( 'Kilometers', 'wb-listora' ); ?></label>&nbsp;&nbsp;
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[distance_unit]" value="mi" <?php checked( $s['distance_unit'] ?? $d['distance_unit'], 'mi' ); ?> /> <?php esc_html_e( 'Miles', 'wb-listora' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Listing expiration', 'wb-listora' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_expiration]" value="1" <?php checked( $s['enable_expiration'] ?? $d['enable_expiration'] ); ?> /> <?php esc_html_e( 'Enable listing expiration', 'wb-listora' ); ?></label>
					<br/><br/>
					<label><?php esc_html_e( 'Default expiration:', 'wb-listora' ); ?> <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_expiration]" value="<?php echo esc_attr( $s['default_expiration'] ?? $d['default_expiration'] ); ?>" min="0" class="small-text" /> <?php esc_html_e( 'days (0 = never)', 'wb-listora' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Claiming', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_claiming]" value="1" <?php checked( $s['enable_claiming'] ?? $d['enable_claiming'] ); ?> /> <?php esc_html_e( 'Enable listing claiming', 'wb-listora' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	private static function render_maps_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Map Provider', 'wb-listora' ); ?></th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_provider]" value="osm" <?php checked( $s['map_provider'] ?? $d['map_provider'], 'osm' ); ?> /> <?php esc_html_e( 'OpenStreetMap (free, no API key)', 'wb-listora' ); ?></label>
					<br/>
					<label style="opacity:0.5;"><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_provider]" value="google" <?php checked( $s['map_provider'] ?? $d['map_provider'], 'google' ); ?> disabled /> <?php esc_html_e( 'Google Maps (Pro)', 'wb-listora' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="map_default_lat"><?php esc_html_e( 'Default center', 'wb-listora' ); ?></label></th>
				<td>
					<label><?php esc_html_e( 'Lat:', 'wb-listora' ); ?> <input type="text" id="map_default_lat" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_default_lat]" value="<?php echo esc_attr( $s['map_default_lat'] ?? $d['map_default_lat'] ); ?>" class="small-text" /></label>
					<label><?php esc_html_e( 'Lng:', 'wb-listora' ); ?> <input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_default_lng]" value="<?php echo esc_attr( $s['map_default_lng'] ?? $d['map_default_lng'] ); ?>" class="small-text" /></label>
					<label><?php esc_html_e( 'Zoom:', 'wb-listora' ); ?> <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_default_zoom]" value="<?php echo esc_attr( $s['map_default_zoom'] ?? $d['map_default_zoom'] ); ?>" min="1" max="20" class="small-text" /></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Options', 'wb-listora' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_clustering]" value="1" <?php checked( $s['map_clustering'] ?? $d['map_clustering'] ); ?> /> <?php esc_html_e( 'Enable marker clustering', 'wb-listora' ); ?></label><br/>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_search_on_drag]" value="1" <?php checked( $s['map_search_on_drag'] ?? $d['map_search_on_drag'] ); ?> /> <?php esc_html_e( 'Search on map drag', 'wb-listora' ); ?></label><br/>
					<label><?php esc_html_e( 'Max markers:', 'wb-listora' ); ?> <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map_max_markers]" value="<?php echo esc_attr( $s['map_max_markers'] ?? $d['map_max_markers'] ); ?>" min="50" max="5000" class="small-text" /></label>
				</td>
			</tr>
		</table>
		<?php
	}

	private static function render_submissions_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Frontend submission', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_submission]" value="1" <?php checked( $s['enable_submission'] ?? $d['enable_submission'] ); ?> /> <?php esc_html_e( 'Enable frontend listing submission', 'wb-listora' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Moderation', 'wb-listora' ); ?></th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[moderation]" value="manual" <?php checked( $s['moderation'] ?? $d['moderation'], 'manual' ); ?> /> <?php esc_html_e( 'Require admin approval', 'wb-listora' ); ?></label><br/>
					<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[moderation]" value="auto_approve" <?php checked( $s['moderation'] ?? $d['moderation'], 'auto_approve' ); ?> /> <?php esc_html_e( 'Auto-approve all submissions', 'wb-listora' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="max_upload_size"><?php esc_html_e( 'Max file size (MB)', 'wb-listora' ); ?></label></th>
				<td><input type="number" id="max_upload_size" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_upload_size]" value="<?php echo esc_attr( $s['max_upload_size'] ?? $d['max_upload_size'] ); ?>" min="1" max="50" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="max_gallery_images"><?php esc_html_e( 'Max gallery images', 'wb-listora' ); ?></label></th>
				<td><input type="number" id="max_gallery_images" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_gallery_images]" value="<?php echo esc_attr( $s['max_gallery_images'] ?? $d['max_gallery_images'] ); ?>" min="1" max="100" class="small-text" /></td>
			</tr>
		</table>
		<?php
	}

	private static function render_notifications_tab() {
		$s     = get_option( self::OPTION_KEY, array() );
		$notif = $s['notifications'] ?? array();

		$events = array(
			'listing_submitted' => array( __( 'New listing submitted', 'wb-listora' ), __( 'Sent to admin when a new listing is submitted for review.', 'wb-listora' ) ),
			'listing_approved'  => array( __( 'Listing approved', 'wb-listora' ), __( 'Sent to listing owner when their listing is published.', 'wb-listora' ) ),
			'listing_rejected'  => array( __( 'Listing rejected', 'wb-listora' ), __( 'Sent to listing owner with feedback.', 'wb-listora' ) ),
			'listing_expired'   => array( __( 'Listing expired', 'wb-listora' ), __( 'Sent to listing owner when their listing expires.', 'wb-listora' ) ),
			'listing_expiring'  => array( __( 'Expiration reminder', 'wb-listora' ), __( 'Sent before a listing expires (7 and 1 day).', 'wb-listora' ) ),
			'review_received'   => array( __( 'New review received', 'wb-listora' ), __( 'Sent to listing owner when they receive a review.', 'wb-listora' ) ),
			'review_reply'      => array( __( 'Owner replied to review', 'wb-listora' ), __( 'Sent to reviewer when the owner responds.', 'wb-listora' ) ),
			'claim_submitted'   => array( __( 'Claim submitted', 'wb-listora' ), __( 'Sent to admin when a claim is filed.', 'wb-listora' ) ),
			'claim_approved'    => array( __( 'Claim approved', 'wb-listora' ), __( 'Sent to claimant when their claim is approved.', 'wb-listora' ) ),
			'claim_rejected'    => array( __( 'Claim rejected', 'wb-listora' ), __( 'Sent to claimant when their claim is denied.', 'wb-listora' ) ),
		);
		?>
		<table class="form-table">
			<?php foreach ( $events as $key => $event_info ) :
				$enabled = ! isset( $notif[ $key ] ) || $notif[ $key ];
				?>
			<tr>
				<th scope="row"><?php echo esc_html( $event_info[0] ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notifications][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $enabled ); ?> />
						<?php echo esc_html( $event_info[1] ); ?>
					</label>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	private static function render_seo_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Schema.org', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_schema]" value="1" <?php checked( $s['enable_schema'] ?? $d['enable_schema'] ); ?> /> <?php esc_html_e( 'Enable Schema.org structured data', 'wb-listora' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Breadcrumbs', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_breadcrumbs]" value="1" <?php checked( $s['enable_breadcrumbs'] ?? $d['enable_breadcrumbs'] ); ?> /> <?php esc_html_e( 'Enable breadcrumbs (JSON-LD)', 'wb-listora' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sitemap', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_sitemap]" value="1" <?php checked( $s['enable_sitemap'] ?? $d['enable_sitemap'] ); ?> /> <?php esc_html_e( 'Add listings to WordPress sitemap', 'wb-listora' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Open Graph', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_opengraph]" value="1" <?php checked( $s['enable_opengraph'] ?? $d['enable_opengraph'] ); ?> /> <?php esc_html_e( 'Add Open Graph + Twitter Card meta tags', 'wb-listora' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	private static function render_advanced_tab() {
		$s = get_option( self::OPTION_KEY, array() );
		$d = wb_listora_get_default_settings();
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Data management', 'wb-listora' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_on_uninstall]" value="1" <?php checked( $s['delete_on_uninstall'] ?? $d['delete_on_uninstall'] ); ?> /> <?php esc_html_e( 'Delete all data on plugin uninstall', 'wb-listora' ); ?></label>
					<p class="description" style="color:#d63638;"><?php esc_html_e( 'Warning: This permanently removes all listings, reviews, settings, and custom tables.', 'wb-listora' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cache TTL', 'wb-listora' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Search:', 'wb-listora' ); ?> <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[search_cache_ttl]" value="<?php echo esc_attr( $s['search_cache_ttl'] ?? $d['search_cache_ttl'] ); ?>" min="0" max="120" class="small-text" /> <?php esc_html_e( 'minutes', 'wb-listora' ); ?></label><br/>
					<label><?php esc_html_e( 'Facets:', 'wb-listora' ); ?> <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[facet_cache_ttl]" value="<?php echo esc_attr( $s['facet_cache_ttl'] ?? $d['facet_cache_ttl'] ); ?>" min="0" max="120" class="small-text" /> <?php esc_html_e( 'minutes', 'wb-listora' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Maintenance', 'wb-listora' ); ?></th>
				<td>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=listora-settings&action=reindex' ), 'listora_reindex' ) ); ?>" class="button">
						<?php esc_html_e( 'Rebuild Search Index', 'wb-listora' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=listora-setup' ) ); ?>" class="button" style="margin-inline-start: 0.5rem;">
						<?php esc_html_e( 'Run Setup Wizard', 'wb-listora' ); ?>
					</a>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Debug', 'wb-listora' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[debug_logging]" value="1" <?php checked( $s['debug_logging'] ?? $d['debug_logging'] ); ?> /> <?php esc_html_e( 'Enable debug logging', 'wb-listora' ); ?></label></td>
			</tr>
		</table>
		<?php
	}
}
