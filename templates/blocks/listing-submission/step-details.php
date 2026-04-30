<?php
/**
 * Listing Submission — Step: Type-specific details.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-submission/step-details.php
 *
 * @package WBListora
 *
 * @var string $listing_type Pre-selected listing type slug (empty if dynamic).
 * @var object $registry     Listing_Type_Registry instance.
 * @var array  $prefill_meta Existing meta values for edit mode pre-fill.
 * @var array  $view_data    Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render every field group for a given type inside the wrapping container.
 *
 * Kept as a closure so this template can be overridden by themes without
 * dragging the helper into global scope.
 *
 * @param \WBListora\Core\Listing_Type $type_obj     Type to render.
 * @param array                        $prefill_meta Existing meta values.
 */
$render_type_fields = static function ( $type_obj, $prefill_meta ) {
	if ( ! $type_obj ) {
		return;
	}
	foreach ( $type_obj->get_field_groups() as $group ) {
		echo '<fieldset class="listora-submission__fieldset">';
		echo '<legend class="listora-submission__fieldset-legend">' . esc_html( $group->get_label() ) . '</legend>';

		foreach ( $group->get_fields() as $field ) {
			$existing_value = array_key_exists( $field->get_key(), $prefill_meta ) ? $prefill_meta[ $field->get_key() ] : null;
			// Pass full prefill_meta so composite fields (map_location) can
			// read sibling keys like `latitude` / `longitude` / `city`
			// that Meta_Handler returns flat instead of nested.
			wb_listora_render_submission_field( $field, $existing_value, $prefill_meta );
		}

		echo '</fieldset>';
	}
};
?>
<div class="listora-submission__step" data-step="details" hidden>
	<h2><?php esc_html_e( 'Details', 'wb-listora' ); ?></h2>
	<p class="listora-submission__step-desc"><?php esc_html_e( 'Provide additional details about your listing.', 'wb-listora' ); ?></p>

	<?php if ( $listing_type ) : ?>

		<?php $render_type_fields( $registry->get( $listing_type ), $prefill_meta ); ?>

	<?php else : ?>

		<?php
		/*
		 * Dynamic type flow — pre-render every type's field groups so the
		 * user can pick any type in Step 1 and see the right fields here
		 * without a second round-trip.
		 *
		 * Only the picked type's container is visible; view.js toggles the
		 * `is-active` class on selectSubmissionType. Names stay identical
		 * across types so the chosen type's fields POST correctly and
		 * inactive ones contribute nothing (they're never focused/filled).
		 *
		 * The `listora-submission__field-placeholder` is kept as a fallback
		 * for when no type is yet selected.
		 */
		?>
		<div class="listora-submission__type-fields-wrap">
			<p class="listora-submission__field-placeholder" data-listora-type-placeholder>
				<?php esc_html_e( 'Select a listing type above to see the fields for that type.', 'wb-listora' ); ?>
			</p>
			<?php foreach ( $registry->get_all() as $type_obj_iter ) : ?>
				<div
					class="listora-submission__type-fields"
					data-type-slug="<?php echo esc_attr( $type_obj_iter->get_slug() ); ?>"
					hidden
				>
					<?php $render_type_fields( $type_obj_iter, $prefill_meta ); ?>
				</div>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>
</div>
