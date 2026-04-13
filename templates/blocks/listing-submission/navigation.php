<?php
/**
 * Listing Submission — Navigation buttons (back / save draft / continue / submit).
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-submission/navigation.php
 *
 * @package WBListora
 *
 * @var bool $is_edit_mode Whether we are editing an existing listing.
 * @var array $view_data   Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="listora-submission__nav">
	<button type="button" class="listora-btn listora-btn--secondary listora-submission__back" data-wp-on--click="actions.prevSubmissionStep" hidden>
		<?php esc_html_e( '← Back', 'wb-listora' ); ?>
	</button>

	<div class="listora-submission__nav-right">
		<button type="button" class="listora-btn listora-btn--text listora-submission__save-draft" data-wp-on--click="actions.saveDraft">
			<?php esc_html_e( 'Save Draft', 'wb-listora' ); ?>
		</button>

		<button type="button" class="listora-btn listora-btn--primary listora-submission__next" data-wp-on--click="actions.nextSubmissionStep">
			<?php esc_html_e( 'Continue →', 'wb-listora' ); ?>
		</button>

		<button type="submit" class="listora-btn listora-btn--primary listora-submission__submit-btn" hidden>
			<?php
			if ( $is_edit_mode ) {
				esc_html_e( 'Update Listing', 'wb-listora' );
			} else {
				esc_html_e( 'Submit Listing', 'wb-listora' );
			}
			?>
		</button>
	</div>
</div>
