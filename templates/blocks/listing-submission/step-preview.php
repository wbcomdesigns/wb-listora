<?php
/**
 * Listing Submission — Step: Preview before submit.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-submission/step-preview.php
 *
 * @package WBListora
 *
 * @var bool $show_terms    Whether to show terms checkbox.
 * @var int  $terms_page_id Terms page ID for link.
 * @var array $view_data    Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="listora-submission__step" data-step="preview" hidden>
	<h2><?php esc_html_e( 'Preview Your Listing', 'wb-listora' ); ?></h2>
	<p class="listora-submission__step-desc"><?php esc_html_e( 'Review your listing before submitting.', 'wb-listora' ); ?></p>

	<div class="listora-submission__preview-card">
		<div id="listora-preview-content">
			<p class="listora-submission__field-placeholder"><?php esc_html_e( 'Preview will appear here after filling in the form.', 'wb-listora' ); ?></p>
		</div>
	</div>

	<?php // ─── CAPTCHA Widget ─── ?>
	<?php \WBListora\Captcha::render_widget( 'submission' ); ?>

	<?php if ( $show_terms ) : ?>
	<div class="listora-submission__field listora-submission__terms">
		<label class="listora-submission__checkbox-label">
			<input type="checkbox" name="agree_terms" required />
			<?php
			if ( $terms_page_id > 0 ) {
				printf(
					/* translators: %s: link to terms page */
					wp_kses_post( __( 'I agree to the <a href="%s" target="_blank">Terms of Service</a>', 'wb-listora' ) ),
					esc_url( get_permalink( $terms_page_id ) )
				);
			} else {
				esc_html_e( 'I agree to the Terms of Service', 'wb-listora' );
			}
			?>
		</label>
	</div>
	<?php endif; ?>
</div>
