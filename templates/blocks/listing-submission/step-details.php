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
?>
<div class="listora-submission__step" data-step="details" hidden>
	<h2><?php esc_html_e( 'Details', 'wb-listora' ); ?></h2>
	<p class="listora-submission__step-desc"><?php esc_html_e( 'Provide additional details about your listing.', 'wb-listora' ); ?></p>

	<?php
	// Render type-specific fields.
	if ( $listing_type ) {
		$type_obj = $registry->get( $listing_type );
		if ( $type_obj ) {
			foreach ( $type_obj->get_field_groups() as $group ) {
				echo '<fieldset class="listora-submission__fieldset">';
				echo '<legend class="listora-submission__fieldset-legend">' . esc_html( $group->get_label() ) . '</legend>';

				foreach ( $group->get_fields() as $field ) {
					$existing_value = array_key_exists( $field->get_key(), $prefill_meta ) ? $prefill_meta[ $field->get_key() ] : null;
					wb_listora_render_submission_field( $field, $existing_value );
				}

				echo '</fieldset>';
			}
		}
	} else {
		// Dynamic: fields loaded via JS after type selection.
		echo '<div class="listora-submission__dynamic-fields" data-wp-html="state.submissionFieldsHtml">';
		echo '<p class="listora-submission__field-placeholder">' . esc_html__( 'Select a listing type to see fields.', 'wb-listora' ) . '</p>';
		echo '</div>';
	}
	?>
</div>
