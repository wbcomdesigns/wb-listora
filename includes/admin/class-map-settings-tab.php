<?php
/**
 * Settings > Maps tab and the map tile presets.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings > Maps: provider, tile source, start view and map options.
 */
class Map_Settings_Tab {

	/**
	 * Render the Maps tab.
	 */
	public static function render(): void {
		$s      = get_option( Settings_Page::OPTION_KEY, array() );
		$d      = wb_listora_get_default_settings();
		$opt    = esc_attr( Settings_Page::OPTION_KEY );
		$bounds = Settings_Page::bounded_int_ranges();
		?>
		<div class="listora-settings-pane">

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Map Provider', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'The tile service used to render listing maps across the directory.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Provider', 'wb-listora' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Map Provider', 'wb-listora' ); ?></legend>
									<div class="listora-field-group">
										<label>
											<input type="radio" name="<?php echo esc_attr( $opt ); ?>[map_provider]" value="osm" <?php checked( $s['map_provider'] ?? $d['map_provider'], 'osm' ); ?> />
											<strong><?php esc_html_e( 'OpenStreetMap', 'wb-listora' ); ?></strong>
											<span class="listora-field-group__hint"> — <?php esc_html_e( 'free, no API key required.', 'wb-listora' ); ?></span>
										</label>
										<?php
										// Google Maps requires the Pro add-on: the radio is enabled when Pro
										// is active, otherwise it stays disabled with a hint.
										$google_disabled    = ! defined( 'WB_LISTORA_PRO_VERSION' );
										$google_label_class = $google_disabled ? ' class="listora-field-group__disabled"' : '';
										?>
										<label<?php echo $google_label_class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built attribute string. ?>>
											<input type="radio" name="<?php echo esc_attr( $opt ); ?>[map_provider]" value="google" <?php checked( $s['map_provider'] ?? $d['map_provider'], 'google' ); ?> <?php disabled( $google_disabled ); ?> />
											<strong><?php esc_html_e( 'Google Maps', 'wb-listora' ); ?></strong>
											<span class="listora-field-group__hint">
												<?php
												echo ' — ';
												echo esc_html(
													$google_disabled
														? __( 'requires the Pro add-on.', 'wb-listora' )
														: __( 'enter your API key below to activate.', 'wb-listora' )
												);
												?>
											</span>
										</label>
									</div>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Google Maps requires the Pro add-on and a key with Maps JavaScript API + Places API + Geocoding API enabled.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wb_listora_map_tile_url"><?php esc_html_e( 'Map tile URL', 'wb-listora' ); ?></label>
							</th>
							<td>
								<?php
								self::render_tile_presets( 'wb_listora_map_tile_url', 'wb_listora_map_tile_attribution', (string) ( $s['map_tile_url'] ?? '' ) );
								// The URL is only asked for when the owner must type into it
								// (their own provider, or a key in a keyed preset); a preset
								// fills its own credit line.
								$listora_preset = self::tile_preset_choice( (string) ( $s['map_tile_url'] ?? '' ) );
								?>
								<div data-listora-show-if="wb_listora_map_tile_url_preset" data-listora-show-only="custom maptiler stadia"<?php echo in_array( $listora_preset, array( 'custom', 'maptiler', 'stadia' ), true ) ? '' : ' hidden'; ?>>
								<input
									type="url"
									id="wb_listora_map_tile_url"
									class="regular-text code"
									name="<?php echo esc_attr( $opt ); ?>[map_tile_url]"
									value="<?php echo esc_attr( (string) ( $s['map_tile_url'] ?? '' ) ); ?>"
									placeholder="https://tiles.example.com/{z}/{x}/{y}.png"
								/>
								<p class="description">
									<?php esc_html_e( 'The address your map images come from. Each provider has its own terms of use.', 'wb-listora' ); ?>
								</p>
								</div>
								<p data-listora-show-if="wb_listora_map_tile_url_preset" data-listora-show-only="custom"<?php echo 'custom' === $listora_preset ? '' : ' hidden'; ?>>
									<label for="wb_listora_map_tile_attribution"><?php esc_html_e( 'Tile attribution', 'wb-listora' ); ?></label><br />
									<input
										type="text"
										id="wb_listora_map_tile_attribution"
										class="regular-text"
										name="<?php echo esc_attr( $opt ); ?>[map_tile_attribution]"
										value="<?php echo esc_attr( (string) ( $s['map_tile_attribution'] ?? '' ) ); ?>"
										placeholder="&copy; OpenStreetMap contributors"
									/>
									<span class="description"><?php esc_html_e( 'The credit your provider asks you to show on the map.', 'wb-listora' ); ?></span>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<?php
			// The same picker members use when they add a listing: search a place
			// or drag the pin, and the coordinates and zoom below fill themselves.
			Listing_Fields_Metabox::enqueue_map_picker_assets();
			$listora_tiles = wb_listora_get_map_tiles();
			?>
			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Where maps start', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'The place and zoom a map opens on before any listing is in view.', 'wb-listora' ); ?></p>
				</div>
				<div class="listora-submission__map-field listora-settings-map" data-lat-input="map_default_lat" data-lng-input="map_default_lng" data-zoom-input="map_default_zoom">
					<p>
						<label for="listora-map-center-search"><?php esc_html_e( 'Find a place', 'wb-listora' ); ?></label><br />
						<input type="text" id="listora-map-center-search" name="listora_map_center[address]" class="regular-text" placeholder="<?php esc_attr_e( 'City, address or landmark', 'wb-listora' ); ?>" autocomplete="off" />
					</p>
					<div
						class="listora-submission__map-picker listora-settings-map__picker"
						data-provider="osm"
						data-default-lat="<?php echo esc_attr( (string) ( $s['map_default_lat'] ?? $d['map_default_lat'] ) ); ?>"
						data-default-lng="<?php echo esc_attr( (string) ( $s['map_default_lng'] ?? $d['map_default_lng'] ) ); ?>"
						data-default-zoom="<?php echo esc_attr( (string) ( $s['map_default_zoom'] ?? $d['map_default_zoom'] ) ); ?>"
						data-tile-url="<?php echo esc_attr( (string) $listora_tiles['url'] ); ?>"
						data-tile-attribution="<?php echo esc_attr( wp_strip_all_tags( (string) $listora_tiles['attribution'] ) ); ?>"
						role="application"
						aria-label="<?php esc_attr_e( 'Map: drag the pin or click to set where maps start', 'wb-listora' ); ?>"
					></div>
					<p class="description"><?php esc_html_e( 'Drag the pin or click the map, then zoom to the view visitors should see first.', 'wb-listora' ); ?></p>
				</div>
				<details class="listora-settings-map__advanced">
					<summary><?php esc_html_e( 'Enter coordinates', 'wb-listora' ); ?></summary>
					<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="map_default_lat"><?php esc_html_e( 'Default latitude', 'wb-listora' ); ?></label></th>
							<td>
								<input type="text" id="map_default_lat" name="<?php echo esc_attr( $opt ); ?>[map_default_lat]" value="<?php echo esc_attr( $s['map_default_lat'] ?? $d['map_default_lat'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Decimal degrees. Example: 40.7128 (New York).', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="map_default_lng"><?php esc_html_e( 'Default longitude', 'wb-listora' ); ?></label></th>
							<td>
								<input type="text" id="map_default_lng" name="<?php echo esc_attr( $opt ); ?>[map_default_lng]" value="<?php echo esc_attr( $s['map_default_lng'] ?? $d['map_default_lng'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Decimal degrees. Example: -74.0060 (New York).', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="map_default_zoom"><?php esc_html_e( 'Default zoom', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="map_default_zoom" name="<?php echo esc_attr( $opt ); ?>[map_default_zoom]" value="<?php echo esc_attr( $s['map_default_zoom'] ?? $d['map_default_zoom'] ); ?>" min="<?php echo esc_attr( (string) $bounds['map_default_zoom'][0] ); ?>" max="<?php echo esc_attr( (string) $bounds['map_default_zoom'][1] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Zoom level 1 (world) to 20 (street). City views typically use 12–14.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				</details>
			</section>

			<section class="listora-settings-block">
				<div class="listora-settings-block__head">
					<h3 class="listora-settings-block__title"><?php esc_html_e( 'Options', 'wb-listora' ); ?></h3>
					<p class="listora-settings-block__desc"><?php esc_html_e( 'Interaction and display behavior for map-enabled blocks and pages.', 'wb-listora' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Marker clustering', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[map_clustering]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[map_clustering]" value="1" <?php checked( $s['map_clustering'] ?? $d['map_clustering'] ); ?> />
									<?php esc_html_e( 'Group nearby markers into clusters', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Collapses nearby listings into a single badge on dense maps. Applies to every map block that has not set its own clustering, and to the mobile app.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Search on drag', 'wb-listora' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[map_search_on_drag]" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[map_search_on_drag]" value="1" <?php checked( $s['map_search_on_drag'] ?? $d['map_search_on_drag'] ); ?> />
									<?php esc_html_e( 'Re-run search when the user pans or zooms', 'wb-listora' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically refreshes results based on the current map viewport.', 'wb-listora' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="map_max_markers"><?php esc_html_e( 'Max markers', 'wb-listora' ); ?></label></th>
							<td>
								<input type="number" id="map_max_markers" name="<?php echo esc_attr( $opt ); ?>[map_max_markers]" value="<?php echo esc_attr( $s['map_max_markers'] ?? $d['map_max_markers'] ); ?>" min="<?php echo esc_attr( (string) $bounds['map_max_markers'][0] ); ?>" max="<?php echo esc_attr( (string) $bounds['map_max_markers'][1] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Upper cap on markers rendered at once. Higher values impact performance on low-powered devices.', 'wb-listora' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

		</div>
		<?php
	}

	/**
	 * The one-click tile sources: key => label, url, attr (credit), note.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function tile_presets() {
		$osm_credit = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
		return array(
			'osm'      => array(
				'label' => __( 'OpenStreetMap - free, for small sites', 'wb-listora' ),
				'url'   => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', // tile-preset: an option the owner picks, not a default.
				'attr'  => $osm_credit,
				'note'  => __( 'Fine for a small directory. OpenStreetMap asks heavy-traffic sites to use another provider; see their tile usage policy.', 'wb-listora' ),
			),
			'maptiler' => array(
				'label' => __( 'MapTiler Streets - free key, any traffic level', 'wb-listora' ),
				'url'   => 'https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=YOUR_KEY', // tile-preset: an option the owner picks, not a default.
				'attr'  => '&copy; <a href="https://www.maptiler.com/copyright/">MapTiler</a> ' . $osm_credit,
				'note'  => __( 'Create a free key at maptiler.com, then replace YOUR_KEY in the URL below. Paid plans cover busy sites.', 'wb-listora' ),
			),
			'stadia'   => array(
				'label' => __( 'Stadia Alidade Smooth - free key, clean style', 'wb-listora' ),
				'url'   => 'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}{r}.png?api_key=YOUR_KEY', // tile-preset: an option the owner picks, not a default.
				'attr'  => '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a> &copy; <a href="https://openmaptiles.org/">OpenMapTiles</a> ' . $osm_credit,
				'note'  => __( 'Create a free key at stadiamaps.com, then replace YOUR_KEY in the URL below. Paid plans cover busy sites.', 'wb-listora' ),
			),
		);
	}

	/**
	 * Which preset a saved tile URL is: a preset key, 'custom', or '' for none.
	 *
	 * @param string $current Saved tile URL.
	 * @return string
	 */
	private static function tile_preset_choice( $current ) {
		$choice = '' === $current ? '' : 'custom';
		foreach ( self::tile_presets() as $key => $preset ) {
			// A keyed preset is saved with the owner's key in place of YOUR_KEY.
			$pattern = '#^' . str_replace( 'YOUR_KEY', '[^&]+', preg_quote( $preset['url'], '#' ) ) . '$#';
			if ( $preset['url'] === $current || preg_match( $pattern, $current ) ) {
				$choice = $key;
			}
		}
		return $choice;
	}

	/**
	 * One-click map tile sources for the Maps tab and the Setup Wizard.
	 *
	 * No tile server is chosen silently, but an owner can pick a known source
	 * in one click. Choosing one fills the URL and credit fields
	 * (admin-delegation.js); each option carries its usage note.
	 *
	 * @since 1.9.0
	 *
	 * @param string $url_id  ID of the tile URL input.
	 * @param string $attr_id ID of the attribution input.
	 * @param string $current Saved tile URL.
	 * @return void
	 */
	public static function render_tile_presets( $url_id, $attr_id, $current ) {
		$presets = self::tile_presets();

		$choice    = self::tile_preset_choice( $current );
		$select_id = $url_id . '_preset';
		?>
		<p>
			<label for="<?php echo esc_attr( $select_id ); ?>"><?php esc_html_e( 'Tile source', 'wb-listora' ); ?></label><br />
			<select id="<?php echo esc_attr( $select_id ); ?>" data-listora-tile-preset data-url-input="<?php echo esc_attr( $url_id ); ?>" data-attribution-input="<?php echo esc_attr( $attr_id ); ?>">
				<option value="" <?php selected( $choice, '' ); ?>><?php esc_html_e( 'Choose a tile source…', 'wb-listora' ); ?></option>
				<?php foreach ( $presets as $key => $preset ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" data-url="<?php echo esc_attr( $preset['url'] ); ?>" data-attribution="<?php echo esc_attr( $preset['attr'] ); ?>" data-note="<?php echo esc_attr( $preset['note'] ); ?>" <?php selected( $choice, $key ); ?>><?php echo esc_html( $preset['label'] ); ?></option>
				<?php endforeach; ?>
				<option value="custom" <?php selected( $choice, 'custom' ); ?>><?php esc_html_e( 'My own or another provider', 'wb-listora' ); ?></option>
			</select>
		</p>
		<p class="description" data-listora-tile-note><?php echo isset( $presets[ $choice ] ) ? esc_html( $presets[ $choice ]['note'] ) : ''; ?></p>
		<?php
	}
}
