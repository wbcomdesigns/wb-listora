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
		add_action( 'save_post_listora_listing', array( __CLASS__, 'save_post' ), 20, 2 );
	}

	/**
	 * Register the meta box.
	 */
	public static function register_metabox(): void {
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
				<?php esc_html_e( 'Add the services this listing offers. Each service can have its own title, description, price, duration, and photo. Services appear on the listing detail page under the Services tab.', 'wb-listora' ); ?>
			</p>

			<?php if ( empty( $services ) ) : ?>
				<p class="wb-listora-services-metabox__empty">
					<em><?php esc_html_e( 'No services yet. Add one below to get started.', 'wb-listora' ); ?></em>
				</p>
			<?php else : ?>
				<p class="wb-listora-services-metabox__count">
					<strong><?php echo esc_html( $count_text ); ?></strong>
				</p>

				<table class="widefat wb-listora-services-metabox__table">
					<thead>
						<tr>
							<th style="width:30%"><?php esc_html_e( 'Title', 'wb-listora' ); ?></th>
							<th style="width:15%"><?php esc_html_e( 'Price', 'wb-listora' ); ?></th>
							<th style="width:15%"><?php esc_html_e( 'Type', 'wb-listora' ); ?></th>
							<th style="width:12%"><?php esc_html_e( 'Duration (min)', 'wb-listora' ); ?></th>
							<th style="width:13%"><?php esc_html_e( 'Status', 'wb-listora' ); ?></th>
							<th style="width:15%"><?php esc_html_e( 'Delete', 'wb-listora' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $services as $service ) : ?>
							<?php self::render_existing_row( $service ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h4 style="margin-top:1.5em;">
				<?php esc_html_e( 'Add a new service', 'wb-listora' ); ?>
			</h4>
			<?php self::render_new_row(); ?>

			<p class="description" style="margin-top:1em;">
				<?php esc_html_e( 'Click "Update" / "Publish" above to save changes to services. Leave the new-service title blank if you only want to update existing services.', 'wb-listora' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render an existing-service editable row.
	 *
	 * @param array $service Service row from Services::get_services().
	 */
	private static function render_existing_row( $service ): void {
		$id = (int) ( $service['id'] ?? 0 );
		if ( $id <= 0 ) {
			return;
		}
		?>
		<tr>
			<td>
				<input
					type="text"
					name="wb_listora_services[existing][<?php echo esc_attr( (string) $id ); ?>][title]"
					value="<?php echo esc_attr( (string) ( $service['title'] ?? '' ) ); ?>"
					class="regular-text"
				/>
				<br>
				<textarea
					name="wb_listora_services[existing][<?php echo esc_attr( (string) $id ); ?>][description]"
					rows="2"
					class="large-text"
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
					style="width:100%;"
				/>
			</td>
			<td>
				<?php self::render_status_select( "wb_listora_services[existing][{$id}][status]", (string) ( $service['status'] ?? 'active' ) ); ?>
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
	 * Render the empty new-service form row.
	 */
	private static function render_new_row(): void {
		?>
		<table class="widefat">
			<tbody>
				<tr>
					<td style="width:30%">
						<label class="screen-reader-text" for="wb-listora-services-new-title">
							<?php esc_html_e( 'New service title', 'wb-listora' ); ?>
						</label>
						<input
							type="text"
							id="wb-listora-services-new-title"
							name="wb_listora_services[new][title]"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. Catering for 20+', 'wb-listora' ); ?>"
						/>
						<br>
						<textarea
							name="wb_listora_services[new][description]"
							rows="2"
							class="large-text"
							placeholder="<?php esc_attr_e( 'Description (optional)', 'wb-listora' ); ?>"
						></textarea>
					</td>
					<td style="width:15%">
						<input
							type="number"
							step="0.01"
							min="0"
							name="wb_listora_services[new][price]"
							placeholder="0.00"
							style="width:100%;"
						/>
					</td>
					<td style="width:15%">
						<?php self::render_price_type_select( 'wb_listora_services[new][price_type]', 'fixed' ); ?>
					</td>
					<td style="width:12%">
						<input
							type="number"
							min="0"
							name="wb_listora_services[new][duration_minutes]"
							placeholder="<?php esc_attr_e( 'min', 'wb-listora' ); ?>"
							style="width:100%;"
						/>
					</td>
					<td style="width:13%">
						<?php self::render_status_select( 'wb_listora_services[new][status]', 'active' ); ?>
					</td>
					<td style="width:15%">
						<em><?php esc_html_e( 'Set a title to create.', 'wb-listora' ); ?></em>
					</td>
				</tr>
			</tbody>
		</table>
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
		<select name="<?php echo esc_attr( $name ); ?>" style="width:100%;">
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
		<select name="<?php echo esc_attr( $name ); ?>" style="width:100%;">
			<option value="active" <?php selected( $current, 'active' ); ?>><?php esc_html_e( 'Active', 'wb-listora' ); ?></option>
			<option value="inactive" <?php selected( $current, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'wb-listora' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Handle save: create / update / delete services based on the POSTed data.
	 *
	 * Bound at priority 20 so it runs after WP's core post fields are saved
	 * (we read `$post` for the listing_id, which is reliable from the start
	 * of save_post but conventional ordering is safer).
	 *
	 * @param int      $post_id Post ID being saved.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save_post( $post_id, $post ): void {
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above.
		$payload = isset( $_POST['wb_listora_services'] ) ? wp_unslash( $_POST['wb_listora_services'] ) : array();
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

		// 3) Create the optional new service if a title was provided.
		if ( ! empty( $payload['new'] ) && is_array( $payload['new'] ) ) {
			$new = $payload['new'];
			if ( isset( $new['title'] ) && '' !== trim( (string) $new['title'] ) ) {
				Services::create_service(
					array_merge(
						array( 'listing_id' => $post_id ),
						self::row_to_service_data( $new )
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
	 * @param array $row Raw row from $_POST.
	 * @return array
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

		return $out;
	}
}
