<?php
/**
 * Taxonomy Fields — adds icon, image, and color fields to taxonomy term forms.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Custom fields for listing category and feature taxonomies.
 */
class Taxonomy_Fields {

	/**
	 * Nonce action name.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'listora_term_meta';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	const NONCE_NAME = '_listora_term_meta_nonce';

	/**
	 * Register hooks for taxonomy form fields, saving, columns, and assets.
	 */
	public function __construct() {
		// Category form fields.
		add_action( 'listora_listing_cat_add_form_fields', array( $this, 'category_add_fields' ) );
		add_action( 'listora_listing_cat_edit_form_fields', array( $this, 'category_edit_fields' ), 10, 1 );
		add_action( 'created_listora_listing_cat', array( $this, 'save_category_fields' ), 10, 1 );
		add_action( 'edited_listora_listing_cat', array( $this, 'save_category_fields' ), 10, 1 );

		// Feature form fields.
		add_action( 'listora_listing_feature_add_form_fields', array( $this, 'feature_add_fields' ) );
		add_action( 'listora_listing_feature_edit_form_fields', array( $this, 'feature_edit_fields' ), 10, 1 );
		add_action( 'created_listora_listing_feature', array( $this, 'save_feature_fields' ), 10, 1 );
		add_action( 'edited_listora_listing_feature', array( $this, 'save_feature_fields' ), 10, 1 );

		// Category custom columns.
		add_filter( 'manage_edit-listora_listing_cat_columns', array( $this, 'category_columns' ) );
		add_filter( 'manage_listora_listing_cat_custom_column', array( $this, 'category_column_content' ), 10, 3 );

		// Feature custom columns.
		add_filter( 'manage_edit-listora_listing_feature_columns', array( $this, 'feature_columns' ) );
		add_filter( 'manage_listora_listing_feature_custom_column', array( $this, 'feature_column_content' ), 10, 3 );

		// Enqueue assets on taxonomy screens.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Check if the current screen is a Listora taxonomy screen.
	 *
	 * @return bool
	 */
	private function is_taxonomy_screen() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return in_array( $screen->taxonomy, array( 'listora_listing_cat', 'listora_listing_feature' ), true );
	}

	/**
	 * Enqueue media uploader and taxonomy field scripts on taxonomy screens.
	 */
	public function enqueue_assets() {
		if ( ! $this->is_taxonomy_screen() ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'listora-taxonomy-fields',
			WB_LISTORA_PLUGIN_URL . 'assets/js/admin/taxonomy-fields.js',
			array( 'jquery', 'wp-media-utils' ),
			WB_LISTORA_VERSION,
			true
		);
	}

	/**
	 * Render category fields on the "Add New" term form.
	 */
	public function category_add_fields() {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="form-field">
			<label for="listora-icon"><?php esc_html_e( 'Icon', 'wb-listora' ); ?></label>
			<input type="text" name="listora_icon" id="listora-icon" value="" />
			<p class="description"><?php esc_html_e( 'Lucide icon name (e.g., "utensils", "building").', 'wb-listora' ); ?></p>
		</div>

		<div class="form-field">
			<label for="listora-image"><?php esc_html_e( 'Image', 'wb-listora' ); ?></label>
			<input type="hidden" name="listora_image" id="listora-image" value="" />
			<div id="listora-image-preview"></div>
			<button type="button" class="button listora-upload-image"><?php esc_html_e( 'Select Image', 'wb-listora' ); ?></button>
			<button type="button" class="button listora-remove-image" style="display:none;"><?php esc_html_e( 'Remove Image', 'wb-listora' ); ?></button>
			<p class="description"><?php esc_html_e( 'Category image displayed on archive pages.', 'wb-listora' ); ?></p>
		</div>

		<div class="form-field">
			<label for="listora-color"><?php esc_html_e( 'Color', 'wb-listora' ); ?></label>
			<input type="color" name="listora_color" id="listora-color" value="#3B82F6" />
			<p class="description"><?php esc_html_e( 'Category accent color (hex).', 'wb-listora' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render category fields on the "Edit" term form.
	 *
	 * @param \WP_Term $term The term being edited.
	 */
	public function category_edit_fields( $term ) {
		$icon     = get_term_meta( $term->term_id, '_listora_icon', true );
		$image_id = (int) get_term_meta( $term->term_id, '_listora_image', true );
		$color    = get_term_meta( $term->term_id, '_listora_color', true );

		if ( empty( $color ) ) {
			$color = '#3B82F6';
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<tr class="form-field">
			<th scope="row"><label for="listora-icon"><?php esc_html_e( 'Icon', 'wb-listora' ); ?></label></th>
			<td>
				<input type="text" name="listora_icon" id="listora-icon" value="<?php echo esc_attr( $icon ); ?>" />
				<p class="description"><?php esc_html_e( 'Lucide icon name (e.g., "utensils", "building").', 'wb-listora' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row"><label for="listora-image"><?php esc_html_e( 'Image', 'wb-listora' ); ?></label></th>
			<td>
				<input type="hidden" name="listora_image" id="listora-image" value="<?php echo esc_attr( $image_id ); ?>" />
				<div id="listora-image-preview">
					<?php if ( $image_id ) : ?>
						<?php echo wp_get_attachment_image( $image_id, 'thumbnail' ); ?>
					<?php endif; ?>
				</div>
				<button type="button" class="button listora-upload-image"><?php esc_html_e( 'Select Image', 'wb-listora' ); ?></button>
				<button type="button" class="button listora-remove-image" <?php echo $image_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove Image', 'wb-listora' ); ?></button>
				<p class="description"><?php esc_html_e( 'Category image displayed on archive pages.', 'wb-listora' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row"><label for="listora-color"><?php esc_html_e( 'Color', 'wb-listora' ); ?></label></th>
			<td>
				<input type="color" name="listora_color" id="listora-color" value="<?php echo esc_attr( $color ); ?>" />
				<p class="description"><?php esc_html_e( 'Category accent color (hex).', 'wb-listora' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save category term meta fields.
	 *
	 * @param int $term_id The term ID.
	 */
	public function save_category_fields( $term_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// Icon.
		if ( isset( $_POST['listora_icon'] ) ) {
			update_term_meta( $term_id, '_listora_icon', sanitize_text_field( wp_unslash( $_POST['listora_icon'] ) ) );
		}

		// Image.
		if ( isset( $_POST['listora_image'] ) ) {
			$image_id = absint( $_POST['listora_image'] );
			if ( $image_id ) {
				update_term_meta( $term_id, '_listora_image', $image_id );
			} else {
				delete_term_meta( $term_id, '_listora_image' );
			}
		}

		// Color.
		if ( isset( $_POST['listora_color'] ) ) {
			$color = sanitize_hex_color( wp_unslash( $_POST['listora_color'] ) );
			if ( $color ) {
				update_term_meta( $term_id, '_listora_color', $color );
			} else {
				delete_term_meta( $term_id, '_listora_color' );
			}
		}
	}

	/**
	 * Render feature fields on the "Add New" term form.
	 */
	public function feature_add_fields() {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="form-field">
			<label for="listora-icon"><?php esc_html_e( 'Icon', 'wb-listora' ); ?></label>
			<input type="text" name="listora_icon" id="listora-icon" value="" />
			<p class="description"><?php esc_html_e( 'Lucide icon name (e.g., "wifi", "parking").', 'wb-listora' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render feature fields on the "Edit" term form.
	 *
	 * @param \WP_Term $term The term being edited.
	 */
	public function feature_edit_fields( $term ) {
		$icon = get_term_meta( $term->term_id, '_listora_icon', true );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<tr class="form-field">
			<th scope="row"><label for="listora-icon"><?php esc_html_e( 'Icon', 'wb-listora' ); ?></label></th>
			<td>
				<input type="text" name="listora_icon" id="listora-icon" value="<?php echo esc_attr( $icon ); ?>" />
				<p class="description"><?php esc_html_e( 'Lucide icon name (e.g., "wifi", "parking").', 'wb-listora' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save feature term meta fields.
	 *
	 * @param int $term_id The term ID.
	 */
	public function save_feature_fields( $term_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		if ( isset( $_POST['listora_icon'] ) ) {
			update_term_meta( $term_id, '_listora_icon', sanitize_text_field( wp_unslash( $_POST['listora_icon'] ) ) );
		}
	}

	/**
	 * Add custom columns to the category term list table.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public function category_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$new_columns['listora_icon'] = esc_html__( 'Icon', 'wb-listora' );
			}

			$new_columns[ $key ] = $label;

			if ( 'name' === $key ) {
				$new_columns['listora_image'] = esc_html__( 'Image', 'wb-listora' );
				$new_columns['listora_color'] = esc_html__( 'Color', 'wb-listora' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom column content for category terms.
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 *
	 * @return string
	 */
	public function category_column_content( $content, $column_name, $term_id ) {
		switch ( $column_name ) {
			case 'listora_icon':
				$icon = get_term_meta( $term_id, '_listora_icon', true );
				if ( $icon ) {
					$content = '<i data-lucide="' . esc_attr( $icon ) . '"></i>';
				} else {
					$content = '&mdash;';
				}
				break;

			case 'listora_image':
				$image_id = (int) get_term_meta( $term_id, '_listora_image', true );
				if ( $image_id ) {
					$content = wp_get_attachment_image( $image_id, array( 32, 32 ), false, array( 'style' => 'border-radius:4px;' ) );
				} else {
					$content = '&mdash;';
				}
				break;

			case 'listora_color':
				$color = get_term_meta( $term_id, '_listora_color', true );
				if ( $color ) {
					$content = '<span style="display:inline-block;width:20px;height:20px;border-radius:4px;background-color:' . esc_attr( $color ) . ';vertical-align:middle;" title="' . esc_attr( $color ) . '"></span>';
				} else {
					$content = '&mdash;';
				}
				break;
		}

		return $content;
	}

	/**
	 * Add custom columns to the feature term list table.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public function feature_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$new_columns['listora_icon'] = esc_html__( 'Icon', 'wb-listora' );
			}

			$new_columns[ $key ] = $label;
		}

		return $new_columns;
	}

	/**
	 * Render custom column content for feature terms.
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 *
	 * @return string
	 */
	public function feature_column_content( $content, $column_name, $term_id ) {
		if ( 'listora_icon' === $column_name ) {
			$icon = get_term_meta( $term_id, '_listora_icon', true );
			if ( $icon ) {
				$content = '<i data-lucide="' . esc_attr( $icon ) . '"></i>';
			} else {
				$content = '&mdash;';
			}
		}

		return $content;
	}
}
