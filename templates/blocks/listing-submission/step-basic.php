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
 * @var string      $listing_type      Pre-selected listing type slug (empty if dynamic).
 * @var bool        $is_edit_mode      Whether we are editing an existing listing.
 * @var object|null $edit_listing_data The listing post object in edit mode.
 * @var int         $edit_category_id  Category ID in edit mode.
 * @var string      $edit_tags_string  Comma-separated tags in edit mode.
 * @var array       $type_categories   Categories for the pre-selected type.
 * @var array       $view_data         Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="listora-submission__step" data-step="basic" <?php echo ( $show_type_step && ! $listing_type ) ? 'hidden' : ''; ?>>
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
			value="<?php echo $is_edit_mode ? esc_attr( $edit_listing_data->post_title ) : ''; ?>" />
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
			value="<?php echo $is_edit_mode ? esc_attr( $edit_tags_string ) : ''; ?>" />
	</div>

	<div class="listora-submission__field">
		<label for="listora-description" class="listora-submission__label">
			<?php esc_html_e( 'Description', 'wb-listora' ); ?> <span class="required">*</span>
		</label>
		<textarea id="listora-description" name="description" class="listora-input listora-submission__textarea" rows="6" required
			placeholder="<?php esc_attr_e( 'Describe your listing...', 'wb-listora' ); ?>"><?php echo $is_edit_mode ? esc_textarea( $edit_listing_data->post_content ) : ''; ?></textarea>
	</div>
</div>
