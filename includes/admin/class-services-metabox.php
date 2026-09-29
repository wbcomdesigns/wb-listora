<?php
/**
 * Services meta box on the Edit Listing screen.
 *
 * Owners manage their services from the frontend dashboard. This meta box
 * gives site admins / editors the same capability without leaving wp-admin
 * — fixes Basecamp 9843428450 (the morphed report: "no Services field is
 * available in the backend to add services for listings").
 *
 * Architecture: piggybacks on the post's main save form. Each existing
 * service renders inline-editable inputs under the `wb_listora_services`
 * POST key; the `save_post_listora_listing` handler parses those, runs
 * delete-then-update-then-create against `WBListora\Core\Services`, and
 * redirects via WP's normal post-update flow. No nested forms, no AJAX,
 * no schema changes.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

use WBListora\Core\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Renders + saves the Services meta box on listora_listing edit screens.
 */
class Services_Metabox {

	/**
	 * Nonce name used by the meta-box form.
	 *
	 * @var string
	 */
	const NONCE_NAME = '_wb_listora_services_metabox_nonce';

	/**
	 * Nonce action used by the meta-box form.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'wb_listora_services_metabox';

	/**
	 * Register WordPress hooks.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_listora_listing', array( __CLASS__, 'register_metabox' ) );
		add_action( 'save_post_listora_listing', array( __CLASS__, 'save_post' ), 20, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue media + the metabox JS only on the listora_listing edit screen.
	 *
	 * Card 9872014083 — Services meta box gained an image-upload affordance
	 * that needs the WP media frame (wp.media) and a small handler to wire
	 * the "Choose Image" button to the media library.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'listora_listing' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'wb-listora-services-metabox',
			WB_LISTORA_PLUGIN_URL . 'assets/js/admin/services-metabox.js',
			array(),
			WB_LISTORA_VERSION,
			true
		);
		wp_localize_script(
			'wb-listora-services-metabox',
			'listoraServicesMetabox',
			array(
				'frameTitle'    => __( 'Choose service photo', 'wb-listora' ),
				'frameButton'   => __( 'Use this photo', 'wb-listora' ),
				'removeConfirm' => __( 'Remove this photo?', 'wb-listora' ),
				'fallbackThumb' => '',
			)
		);
	}

	/**
	 * Register the meta box.
	 */
	public static function register_metabox( ?\WP_Post $post = null ): void {
		// A type with services switched off shows no Services box in wp-admin
		// either, matching the dashboard and the listing page.
		if ( $post instanceof \WP_Post && ! \WBListora\Core\Services::enabled_for_listing( $post->ID ) ) {
			return;
		}

		add_meta_box(
			'wb_listora_services',
			__( 'Services', 'wb-listora' ),
			array( __CLASS__, 'render' ),
			'listora_listing',
			'normal',
			'default'
		);
	}

	/**
	 * Render the meta box body.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render( $post ): void {
		$listing_id = (int) $post->ID;
		$services   = Services::get_services( $listing_id, 'all' );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		// Translators: %d is the count of services attached to this listing.
		$count_text = sprintf( _n( '%d service', '%d services', count( $services ), 'wb-listora' ), count( $services ) );

		?>
		<div class="wb-listora-services-metabox">
			<p class="description">
				<?php esc_html_e( 'Add the services this listing offers. Each service can have its own title, description, price, duration, status, and photo. Services appear on the listing detail page under the Services tab.', 'wb-listora' ); ?>
			</p>

			<?php if ( empty( $services ) ) : ?>
				<p class="wb-listora-services-metabox__empty">
					<em><?php esc_html_e( 'No services yet. Add one below to get started.', 'wb-listora' ); ?></em>
				</p>
			<?php else : ?>
				<p class="wb-listora-services-metabox__count">
					<strong><?php echo esc_html( $count_text ); ?></strong>
				</p>

				<div class="wb-listora-services-metabox__scroll">
				<table class="widefat wb-listora-services-metabox__table">
					<thead>
						<tr>
							<th style="width:8%"><?php esc_html_e( 'Photo', 'wb-listora' ); ?></th>
							<th style="width:20%"><?php esc_html_e( 'Title', 'wb-listora' ); ?></th>
							<th style="width:11%"><?php esc_html_e( 'Price', 'wb-listora' ); ?></th>
							<th style="width:11%"><?php esc_html_e( 'Type', 'wb-listora' ); ?></th>
							<th style="width:10%"><?php esc_html_e( 'Duration (min)', 'wb-listora' ); ?></th>
							<th style="width:11%"><?php esc_html_e( 'Status', 'wb-listora' ); ?></th>
							<th style="width:14%"><?php esc_html_e( 'Category', 'wb-listora' ); ?></th>
							<th style="width:15%"><?php esc_html_e( 'Delete', 'wb-listora' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $services as $service ) : ?>
							<?php self::render_existing_row( $service ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php endif; ?>

			<h4 style="margin-top:1.5em;">
				<?php esc_html_e( 'Add new services', 'wb-listora' ); ?>
			</h4>
			<div class="wb-listora-services-metabox__scroll">
			<table class="widefat">
				<tbody id="wb-listora-services-new-rows">
					<?php self::render_new_row( 0 ); ?>
				</tbody>
			</table>
			</div>
			<p style="margin-top:0.5em;">
				<button type="button" class="button" data-listora-svc-add-row>
					<?php esc_html_e( '+ Add Another Service', 'wb-listora' ); ?>
				</button>
			</p>

			<p class="description" style="margin-top:1em;">
				<?php esc_html_e( 'Click "Update" / "Publish" above to save changes to services. Leave a new-service title blank to skip that row.', 'wb-listora' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render an existing-service editable row.
	 *
	 * @param array<string, mixed> $service Service row from Services::get_services().
	 */
	private static function render_existing_row( array $service ): void {
		$id = (int) ( $service['id'] ?? 0 );
		if ( $id <= 0 ) {
			return;
		}
		$image_id  = isset( $service['image_id'] ) ? (int) $service['image_id'] : 0;
		$thumb_url = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
		$row_uid   = 'svc-' . $id;
		?>
		<tr class="wb-listora-services-metabox__row" data-row-uid="<?php echo esc_attr( $row_uid ); ?>">
			<td>
				<?php self::render_photo_cell( $row_uid, "wb_listora_services[existing][{$id}][image_id]", $image_id, $thumb_url ); ?>
			</td>
			<td>
				<input
					type="text"
					name="wb_listora_services[existing][<?php echo esc_attr( (string) $id ); ?>][title]"
					value="<?php echo esc_attr( (string) ( $service['title'] ?? '' ) ); ?>"
					class="regular-text"
					aria-label="<?php esc_attr_e( 'Service name', 'wb-listora' ); ?>"
				/>
				<br>
				<textarea
					name="wb_listora_services[existing][<?php echo esc_attr( (string) $id ); ?>][description]"
					rows="2"
					class="large-text"
					aria-label="<?php esc_attr_e( 'Service description', 'wb-listora' ); ?>"
					placeholder="<?php esc_attr_e( 'Description (optional)', 'wb-listora' ); ?>"
				><?php echo esc_textarea( (string) ( $service['description'] ?? '' ) ); ?></textarea>
			</td>
			<td>
				<input
					type="number"
					step="0.01"
					min="0"
					name="wb_listora_services[existing][<?php echo esc_attr( (string) $id ); ?>][price]"
					value="<?php echo esc_attr( null !== $service['price'] ? (string) $service['price'] : '' ); ?>"
					placeholder="0.00"
					aria-label="<?php esc_attr_e( 'Price', 'wb-listora' ); ?>"
					style="width:100%;"
				/>
			</td>
			<td>
				<?php self::render_price_type_select( "wb_listora_services[existing][{$id}][price_type]", (string) ( $service['price_type'] ?? 'fixed' ) ); ?>
			</td>
			<td>
				<input
					type="number"
					min="0"
					name="wb_listora_services[existing][<?php echo esc_attr( (string) $id ); ?>][duration_minutes]"
					value="<?php echo esc_attr( null !== $service['duration_minutes'] ? (string) $service['duration_minutes'] : '' ); ?>"
					aria-label="<?php esc_attr_e( 'Duration in minutes', 'wb-listora' ); ?>"
					style="width:100%;"
				/>
			</td>
			<td>
				<?php self::render_status_select( "wb_listora_services[existing][{$id}][status]", (string) ( $service['status'] ?? 'active' ) ); ?>
			</td>
			<td>
				<?php self::render_category_select( "wb_listora_services[existing][{$id}][categories][]", Services::get_service_categories( $id ) ); ?>
			</td>
			<td>
				<label>
					<input
						type="checkbox"
						name="wb_listora_services[delete][]"
						value="<?php echo esc_attr( (string) $id ); ?>"
					/>
					<?php esc_html_e( 'Remove on save', 'wb-listora' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render one blank new-service row, addressed by index.
	 *
	 * Indexed (`[new][N][...]`) rather than singular so more than one row
	 * can be POSTed per save — the "+ Add Another Service" button
	 * (`services-metabox.js`) clones this markup and bumps the index.
	 * Card 10350359093.
	 *
	 * @param int $index Row index for the `wb_listora_services[new][N]` field group.
	 */
	private static function render_new_row( int $index ): void {
		$uid = 'svc-new-' . $index;
		?>
		<tr class="wb-listora-services-metabox__row" data-row-uid="<?php echo esc_attr( $uid ); ?>" data-listora-svc-new-index="<?php echo esc_attr( (string) $index ); ?>">
			<td style="width:8%">
				<?php self::render_photo_cell( $uid, "wb_listora_services[new][{$index}][image_id]", 0, '' ); ?>
			</td>
			<td style="width:20%">
				<label class="screen-reader-text" for="wb-listora-services-new-title-<?php echo esc_attr( (string) $index ); ?>">
					<?php esc_html_e( 'New service title', 'wb-listora' ); ?>
				</label>
				<input
					type="text"
					id="wb-listora-services-new-title-<?php echo esc_attr( (string) $index ); ?>"
					name="wb_listora_services[new][<?php echo esc_attr( (string) $index ); ?>][title]"
					class="regular-text"
					placeholder="<?php esc_attr_e( 'e.g. Catering for 20+', 'wb-listora' ); ?>"
				/>
				<br>
				<textarea
					name="wb_listora_services[new][<?php echo esc_attr( (string) $index ); ?>][description]"
					rows="2"
					class="large-text"
					placeholder="<?php esc_attr_e( 'Description (optional)', 'wb-listora' ); ?>"
				></textarea>
			</td>
			<td style="width:11%">
				<input
					type="number"
					step="0.01"
					min="0"
					name="wb_listora_services[new][<?php echo esc_attr( (string) $index ); ?>][price]"
					placeholder="0.00"
					style="width:100%;"
				/>
			</td>
			<td style="width:11%">
				<?php self::render_price_type_select( "wb_listora_services[new][{$index}][price_type]", 'fixed' ); ?>
			</td>
			<td style="width:10%">
				<input
					type="number"
					min="0"
					name="wb_listora_services[new][<?php echo esc_attr( (string) $index ); ?>][duration_minutes]"
					placeholder="<?php esc_attr_e( 'min', 'wb-listora' ); ?>"
					style="width:100%;"
				/>
			</td>
			<td style="width:11%">
				<?php self::render_status_select( "wb_listora_services[new][{$index}][status]", 'active' ); ?>
			</td>
			<td style="width:14%">
				<?php self::render_category_select( "wb_listora_services[new][{$index}][categories][]", array() ); ?>
			</td>
			<td style="width:15%">
				<?php if ( 0 === $index ) : ?>
					<em><?php esc_html_e( 'Set a title to create.', 'wb-listora' ); ?></em>
				<?php else : ?>
					<button type="button" class="button-link-delete wp-element-button" data-listora-svc-remove-row>
						<?php esc_html_e( 'Remove row', 'wb-listora' ); ?>
					</button>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a multi-select for the service-category taxonomy.
	 *
	 * Card 10351076301 — mirrors the price_type/status select pattern; a
	 * service can carry more than one category, matching
	 * Services::get/set_service_categories()'s array shape.
	 *
	 * @param string $name     HTML name attribute (array-style, e.g. "...[categories][]").
	 * @param array  $selected Currently selected term IDs.
	 */
	private static function render_category_select( string $name, array $selected ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => 'listora_service_cat',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			?>
			<em style="color:#a7aaad;"><?php esc_html_e( 'No categories yet', 'wb-listora' ); ?></em>
			<?php
			return;
		}
		$selected = array_map( 'intval', $selected );
		?>
		<select name="<?php echo esc_attr( $name ); ?>" multiple size="3" style="width:100%;" aria-label="<?php esc_attr_e( 'Service categories', 'wb-listora' ); ?>">
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, $selected, true ) ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render the photo upload cell — preview + WP-media-library trigger +
	 * remove button + hidden image_id input. Card 9872014083.
	 *
	 * @param string $uid       Per-row unique key (used to scope JS click handlers).
	 * @param string $name      HTML name attribute for the hidden input.
	 * @param int    $image_id  Currently selected attachment ID (0 if none).
	 * @param string $thumb_url Pre-resolved thumbnail URL (empty if none).
	 */
	private static function render_photo_cell( string $uid, string $name, int $image_id, string $thumb_url ): void {
		?>
		<div class="wb-listora-services-metabox__photo" data-row-uid="<?php echo esc_attr( $uid ); ?>">
			<div class="wb-listora-services-metabox__photo-preview" data-listora-svc-preview style="width:48px;height:48px;border:1px solid #ccd0d4;border-radius:4px;overflow:hidden;background:#f6f7f7;display:flex;align-items:center;justify-content:center;">
				<?php if ( $thumb_url ) : ?>
					<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;" data-listora-svc-img />
				<?php else : ?>
					<span data-listora-svc-empty style="color:#a7aaad;font-size:11px;text-align:center;">—</span>
				<?php endif; ?>
			</div>
			<button type="button" class="button button-small wb-listora-services-metabox__choose wp-element-button" data-listora-svc-choose style="margin-top:4px;width:100%;">
				<?php echo $image_id ? esc_html__( 'Change', 'wb-listora' ) : esc_html__( 'Choose', 'wb-listora' ); ?>
			</button>
			<button type="button" class="button-link-delete wb-listora-services-metabox__remove wp-element-button" data-listora-svc-remove style="margin-top:2px;font-size:11px;<?php echo $image_id ? '' : 'display:none;'; ?>">
				<?php esc_html_e( 'Remove', 'wb-listora' ); ?>
			</button>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-listora-svc-input />
		</div>
		<?php
	}

	/**
	 * Render a <select> for the price_type field.
	 *
	 * @param string $name    HTML name attribute.
	 * @param string $current Current value.
	 */
	private static function render_price_type_select( string $name, string $current ): void {
		$options = array(
			'fixed'         => __( 'Fixed', 'wb-listora' ),
			'starting_from' => __( 'Starting from', 'wb-listora' ),
			'hourly'        => __( 'Hourly', 'wb-listora' ),
			'free'          => __( 'Free', 'wb-listora' ),
			'contact'       => __( 'Contact for price', 'wb-listora' ),
		);
		?>
		<select name="<?php echo esc_attr( $name ); ?>" style="width:100%;" aria-label="<?php esc_attr_e( 'Price type', 'wb-listora' ); ?>">
			<?php foreach ( $options as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current, $val ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render a <select> for the status field.
	 *
	 * @param string $name    HTML name attribute.
	 * @param string $current Current value.
	 */
	private static function render_status_select( string $name, string $current ): void {
		?>
		<select name="<?php echo esc_attr( $name ); ?>" style="width:100%;" aria-label="<?php esc_attr_e( 'Service status', 'wb-listora' ); ?>">
			<option value="active" <?php selected( $current, 'active' ); ?>><?php esc_html_e( 'Active', 'wb-listora' ); ?></option>
			<option value="inactive" <?php selected( $current, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'wb-listora' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Handle save: create / update / delete services based on the POSTed data.
	 *
	 * Bound at priority 20 so it runs after WP's core post fields are saved
	 * — conventional ordering for meta box save handlers, even when the
	 * handler doesn't use the post object directly.
	 *
	 * @param int $post_id Post ID being saved.
	 */
	public static function save_post( $post_id ): void {
		// Standard WordPress save guards.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Nonce + capability.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handled below.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ),
			self::NONCE_ACTION
		) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		wb_listora_keep_listing_media( (int) $post_id );

		// $payload is a 3-level nested array of strings; per-string sanitisation
		// happens inside Services::sanitize_data() once each row is dispatched
		// to the create/update path. Sanitising at this boundary would either
		// mangle the structure or require a recursive walker that duplicates
		// what sanitize_data() already does.
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$payload = isset( $_POST['wb_listora_services'] ) ? wp_unslash( $_POST['wb_listora_services'] ) : array();
		// phpcs:enable
		if ( ! is_array( $payload ) ) {
			return;
		}

		// 1) Deletes first so updates / creates can't accidentally touch
		//    a row the user asked to remove.
		if ( ! empty( $payload['delete'] ) && is_array( $payload['delete'] ) ) {
			foreach ( $payload['delete'] as $service_id ) {
				$service_id = (int) $service_id;
				if ( $service_id > 0 ) {
					Services::delete_service( $service_id );
				}
			}
		}

		// 2) Updates to existing services (skip rows scheduled for delete).
		$delete_ids = array();
		if ( ! empty( $payload['delete'] ) && is_array( $payload['delete'] ) ) {
			$delete_ids = array_map( 'intval', $payload['delete'] );
		}
		if ( ! empty( $payload['existing'] ) && is_array( $payload['existing'] ) ) {
			foreach ( $payload['existing'] as $sid => $row ) {
				$sid = (int) $sid;
				if ( $sid <= 0 || in_array( $sid, $delete_ids, true ) ) {
					continue;
				}
				if ( ! is_array( $row ) ) {
					continue;
				}

				// Title is the only hard requirement — empty title is treated
				// as "leave alone". Use empty-string check so the inline
				// editor doesn't accidentally blank a title to nothing.
				if ( ! isset( $row['title'] ) || '' === trim( (string) $row['title'] ) ) {
					continue;
				}

				Services::update_service( $sid, self::row_to_service_data( $row ) );
			}
		}

		// 3) Create every new-service row that got a title (card 10350359093 —
		// indexed rows from the "+ Add Another Service" clone-row UI; a row
		// left blank is silently skipped, same as the old single-row behavior).
		if ( ! empty( $payload['new'] ) && is_array( $payload['new'] ) ) {
			foreach ( $payload['new'] as $new_row ) {
				if ( ! is_array( $new_row ) || ! isset( $new_row['title'] ) || '' === trim( (string) $new_row['title'] ) ) {
					continue;
				}
				Services::create_service(
					array_merge(
						array( 'listing_id' => $post_id ),
						self::row_to_service_data( $new_row )
					)
				);
			}
		}
	}

	/**
	 * Convert a meta-box row to the array shape Services::create/update expects.
	 *
	 * Sanitization happens inside Services::sanitize_data — no need to double up.
	 *
	 * @param array<string, mixed> $row Raw row from $_POST.
	 * @return array<string, mixed>
	 */
	private static function row_to_service_data( array $row ): array {
		$out = array();

		if ( isset( $row['title'] ) ) {
			$out['title'] = (string) $row['title'];
		}
		if ( isset( $row['description'] ) ) {
			$out['description'] = (string) $row['description'];
		}
		if ( array_key_exists( 'price', $row ) ) {
			$out['price'] = ( '' === $row['price'] ) ? null : $row['price'];
		}
		if ( isset( $row['price_type'] ) ) {
			$out['price_type'] = (string) $row['price_type'];
		}
		if ( array_key_exists( 'duration_minutes', $row ) ) {
			$out['duration_minutes'] = ( '' === $row['duration_minutes'] ) ? null : $row['duration_minutes'];
		}
		if ( isset( $row['status'] ) ) {
			$out['status'] = (string) $row['status'];
		}
		// Card 10351076301 — Services::sanitize_data() + create/update_service()
		// already do the term-relationship write; this row just needs to pass
		// the posted term IDs through.
		if ( isset( $row['categories'] ) ) {
			$out['categories'] = array_map( 'absint', (array) $row['categories'] );
		}
		// Card 9872014083 — accept image_id from the photo upload cell.
		// `''` means "no image picked" → null. Services::sanitize_data
		// further coerces with absint().
		if ( array_key_exists( 'image_id', $row ) ) {
			$out['image_id'] = ( '' === $row['image_id'] || '0' === $row['image_id'] ) ? null : $row['image_id'];
		}

		return $out;
	}
}
