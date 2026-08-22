<?php
/**
 * Listing Submission — Step: Basic Information.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-submission/step-basic.php
 *
 * @package WBListora
 *
 * @var bool        $show_type_step    Whether to show the type selection step.
 * @var bool        $is_single_form    True in single-form layout — steps render stacked and must NOT emit `hidden`.
 * @var string      $listing_type      Pre-selected listing type slug (empty if dynamic).
 * @var bool        $is_edit_mode      Whether we are editing an existing listing.
 * @var object|null $edit_listing_data The listing post object in edit mode.
 * @var int         $edit_category_id  Category ID in edit mode.
 * @var string      $edit_tags_string  Comma-separated tags in edit mode.
 * @var array       $available_features Feature terms offered as checkboxes.
 * @var int[]       $edit_feature_ids   Feature term IDs already on the listing.
 * @var array       $type_categories   Categories for the pre-selected type.
 * @var array       $view_data         Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="listora-submission__step" data-step="basic" <?php echo ( empty( $is_single_form ) && $show_type_step && ! $listing_type ) ? 'hidden' : ''; ?>>
	<h2>
		<?php
		if ( $is_edit_mode ) {
			esc_html_e( 'Edit Basic Information', 'wb-listora' );
		} else {
			esc_html_e( 'Basic Information', 'wb-listora' );
		}
		?>
	</h2>

	<div class="listora-submission__field">
		<label for="listora-title" class="listora-submission__label">
			<?php esc_html_e( 'Listing Title', 'wb-listora' ); ?> <span class="required">*</span>
		</label>
		<input type="text" id="listora-title" name="title" class="listora-input" required
			placeholder="<?php esc_attr_e( 'e.g., Pizza Palace', 'wb-listora' ); ?>"
			value="<?php echo esc_attr( $is_edit_mode ? $edit_listing_data->post_title : '' ); ?>" />
	</div>

	<?php if ( ! empty( $type_categories ) || ! $listing_type ) : ?>
	<div class="listora-submission__field">
		<label for="listora-category" class="listora-submission__label">
			<?php esc_html_e( 'Category', 'wb-listora' ); ?> <span class="required">*</span>
		</label>
		<select id="listora-category" name="category" class="listora-input listora-select" required>
			<option value=""><?php esc_html_e( 'Select a category', 'wb-listora' ); ?></option>
			<?php foreach ( $type_categories as $cat ) : ?>
			<option value="<?php echo esc_attr( $cat->term_id ); ?>"
				<?php selected( $is_edit_mode && $edit_category_id === (int) $cat->term_id ); ?>>
				<?php echo esc_html( $cat->name ); ?>
			</option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php endif; ?>

	<div class="listora-submission__field">
		<label for="listora-tags" class="listora-submission__label">
			<?php esc_html_e( 'Tags', 'wb-listora' ); ?>
		</label>
		<input type="text" id="listora-tags" name="tags" class="listora-input"
			placeholder="<?php esc_attr_e( 'pizza, italian, downtown (comma separated)', 'wb-listora' ); ?>"
			value="<?php echo esc_attr( $is_edit_mode ? $edit_tags_string : '' ); ?>" />
	</div>

	<?php
	/*
	 * Features (amenities).
	 *
	 * The taxonomy could only be assigned from the block editor's sidebar in
	 * wp-admin, so members could never set an amenity — at submission or
	 * afterwards. The search Features filter was therefore dead for every
	 * member-created listing, and their detail pages carried an empty
	 * "Features & Amenities" section (BC 10198974105).
	 *
	 * Checkboxes rather than a free-text field: features are a curated
	 * vocabulary the site owner defines, and letting members type new ones is
	 * what tags are for.
	 */
	?>
	<?php if ( ! empty( $available_features ) ) : ?>
	<div class="listora-submission__field">
		<span class="listora-submission__label" id="listora-features-label">
			<?php esc_html_e( 'Features & Amenities', 'wb-listora' ); ?>
		</span>
		<?php
		/*
		 * The grid carries every feature because it renders before a type is
		 * chosen. The map says which features each type allows, and the wizard
		 * narrows the grid when the member picks one. A type not in the map is
		 * unrestricted. The server applies the same rule on submit, so this
		 * governs display only.
		 */
		$feature_allowlist_map = isset( $feature_allowlist_map ) && is_array( $feature_allowlist_map )
			? $feature_allowlist_map
			: array();
		?>
		<div class="listora-submission__checkbox-grid" role="group" aria-labelledby="listora-features-label"
			data-listora-feature-allowlist="<?php echo esc_attr( (string) wp_json_encode( $feature_allowlist_map ) ); ?>">
			<?php foreach ( $available_features as $available_feature ) : ?>
				<label class="listora-submission__checkbox" data-feature-id="<?php echo esc_attr( (string) $available_feature->term_id ); ?>">
					<input type="checkbox" name="features[]"
						value="<?php echo esc_attr( (string) $available_feature->term_id ); ?>"
						<?php checked( in_array( (int) $available_feature->term_id, (array) $edit_feature_ids, true ) ); ?> />
					<span><?php echo esc_html( $available_feature->name ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="listora-submission__field">
		<label for="listora-description" class="listora-submission__label">
			<?php esc_html_e( 'Description', 'wb-listora' ); ?> <span class="required">*</span>
		</label>
		<textarea id="listora-description" name="description" class="listora-input listora-submission__textarea" rows="6" required
			placeholder="<?php esc_attr_e( 'Describe your listing...', 'wb-listora' ); ?>"><?php echo esc_textarea( $is_edit_mode ? $edit_listing_data->post_content : '' ); ?></textarea>
	</div>
</div>
