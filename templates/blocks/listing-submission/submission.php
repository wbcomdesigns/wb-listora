<?php
/**
 * Listing Submission — Main wrapper template.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-submission/submission.php
 *
 * @package WBListora
 *
 * @var string $wrapper_attrs      Block wrapper attributes string.
 * @var string $block_css          Rendered Block_CSS output.
 * @var array  $steps              Step definitions array.
 * @var int    $total_steps        Total number of steps.
 * @var string $listing_type       Pre-selected listing type slug (empty if dynamic).
 * @var bool   $show_type_step     Whether to show the type selection step.
 * @var bool   $show_terms         Whether to show terms checkbox.
 * @var int    $terms_page_id      Terms page ID for link.
 * @var bool   $is_edit_mode       Whether we are editing an existing listing.
 * @var int    $edit_listing_id    Listing ID being edited (0 if creating).
 * @var bool   $is_guest           Whether the current user is a guest.
 * @var bool   $guest_submission_enabled Whether guest submission is enabled.
 * @var array  $types              All registered listing type objects.
 * @var object $registry           Listing_Type_Registry instance.
 * @var array  $type_categories    Categories for the pre-selected type.
 * @var array  $prefill_meta       Existing meta values for edit mode pre-fill.
 * @var object|null $edit_listing_data  The listing post object in edit mode.
 * @var int    $edit_category_id   Category ID in edit mode.
 * @var string $edit_tags_string   Comma-separated tags in edit mode.
 * @var int    $edit_thumbnail_id  Featured image ID in edit mode.
 * @var string $edit_gallery_ids   Comma-separated gallery IDs in edit mode.
 * @var string $edit_video         Video URL in edit mode.
 * @var array  $view_data          Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>

<?php echo $block_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php wb_listora_get_template( 'blocks/listing-submission/stepper.php', $view_data ); ?>

	<form class="listora-submission__form" data-wp-on--submit="actions.handleSubmission">

		<?php wp_nonce_field( 'listora_submit_listing', 'listora_nonce' ); ?>
		<input type="hidden" name="listing_type" value="<?php echo esc_attr( $listing_type ); ?>" />
		<?php if ( $is_edit_mode ) : ?>
		<input type="hidden" name="listing_id" value="<?php echo esc_attr( $edit_listing_id ); ?>" />
		<?php endif; ?>

		<?php // Honeypot anti-spam field. ?>
		<div style="position:absolute;left:-9999px;" aria-hidden="true">
			<input type="text" name="listora_hp_field" value="" tabindex="-1" autocomplete="off" />
		</div>

		<?php // ─── Guest Registration Fields ─── ?>
		<?php if ( $is_guest && $guest_submission_enabled ) : ?>
		<div class="listora-submission__guest-register">
			<h3><?php esc_html_e( 'Create your account', 'wb-listora' ); ?></h3>
			<p class="listora-submission__guest-desc"><?php esc_html_e( 'An account will be created so you can manage your listing.', 'wb-listora' ); ?></p>
			<?php
			/**
			 * Fires inside the guest registration area, before the name/email fields.
			 *
			 * Pro can hook here to inject social login buttons (Google, Facebook, etc.).
			 */
			do_action( 'wb_listora_submission_login_buttons' );
			?>
			<div class="listora-submission__guest-fields">
				<div class="listora-submission__field">
					<label for="listora-guest-name" class="listora-submission__label">
						<?php esc_html_e( 'Your Name', 'wb-listora' ); ?> <span class="required">*</span>
					</label>
					<input type="text" id="listora-guest-name" name="listora_guest_name" class="listora-input"
						placeholder="<?php esc_attr_e( 'Your Name', 'wb-listora' ); ?>" required autocomplete="name" />
				</div>
				<div class="listora-submission__field">
					<label for="listora-guest-email" class="listora-submission__label">
						<?php esc_html_e( 'Email Address', 'wb-listora' ); ?> <span class="required">*</span>
					</label>
					<input type="email" id="listora-guest-email" name="listora_guest_email" class="listora-input"
						placeholder="<?php esc_attr_e( 'Email Address', 'wb-listora' ); ?>" required autocomplete="email" />
				</div>
			</div>
			<p class="listora-submission__guest-notice">
				<?php esc_html_e( 'A password will be emailed to you after submission.', 'wb-listora' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<?php wb_listora_get_template( 'blocks/listing-submission/step-type.php', $view_data ); ?>

		<?php wb_listora_get_template( 'blocks/listing-submission/step-basic.php', $view_data ); ?>

		<?php wb_listora_get_template( 'blocks/listing-submission/step-details.php', $view_data ); ?>

		<?php wb_listora_get_template( 'blocks/listing-submission/step-media.php', $view_data ); ?>

		<?php
		/**
		 * Fires inside the submission form after the Media step, before the Preview step.
		 *
		 * Pro (and other extensions) can hook here to inject additional steps such as
		 * plan / pricing selection. Each hooked callback receives the pre-selected
		 * listing type string (empty when type is chosen dynamically in the form).
		 *
		 * @since 1.0.0
		 *
		 * @param string $listing_type The pre-configured listing type slug, or empty string.
		 */
		do_action( 'wb_listora_submission_plan_step', $listing_type );
		?>

		<?php wb_listora_get_template( 'blocks/listing-submission/step-preview.php', $view_data ); ?>

		<?php // ─── Success Message ─── ?>
		<div class="listora-submission__success" hidden>
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" style="color: var(--listora-success)">
				<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
			</svg>
			<?php if ( $is_edit_mode ) : ?>
			<h2><?php esc_html_e( 'Listing Updated!', 'wb-listora' ); ?></h2>
			<p><?php esc_html_e( 'Your listing has been updated successfully.', 'wb-listora' ); ?></p>
			<div class="listora-submission__success-actions">
				<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="listora-btn listora-btn--primary">
					<?php esc_html_e( 'Go to Dashboard', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( get_permalink( $edit_listing_id ) ); ?>" class="listora-btn listora-btn--secondary">
					<?php esc_html_e( 'View Listing', 'wb-listora' ); ?>
				</a>
			</div>
			<?php else : ?>
			<h2><?php esc_html_e( 'Listing Submitted!', 'wb-listora' ); ?></h2>
			<p><?php esc_html_e( 'Your listing has been submitted and is pending review. We\'ll notify you once it\'s approved.', 'wb-listora' ); ?></p>
			<div class="listora-submission__success-actions">
				<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="listora-btn listora-btn--primary">
					<?php esc_html_e( 'Go to Dashboard', 'wb-listora' ); ?>
				</a>
				<a href="<?php echo esc_url( get_permalink() ); ?>" class="listora-btn listora-btn--secondary">
					<?php esc_html_e( 'Add Another Listing', 'wb-listora' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>

		<?php // ─── Error Message ─── ?>
		<div class="listora-submission__error" role="alert" hidden>
			<p></p>
		</div>

		<?php wb_listora_get_template( 'blocks/listing-submission/navigation.php', $view_data ); ?>

	</form>
</div>
