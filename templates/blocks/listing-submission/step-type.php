<?php
/**
 * Listing Submission — Step: Type selection.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-submission/step-type.php
 *
 * @package WBListora
 *
 * @var bool   $show_type_step Whether to show the type selection step.
 * @var string $listing_type   Pre-selected listing type slug (empty if dynamic).
 * @var array  $types          All registered listing type objects.
 * @var array  $view_data      Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;

if ( ! $show_type_step || $listing_type || count( $types ) <= 1 ) {
	return;
}
?>
<div class="listora-submission__step" data-step="type">
	<h2><?php esc_html_e( 'What type of listing are you adding?', 'wb-listora' ); ?></h2>
	<div class="listora-submission__type-grid">
		<?php
		foreach ( $types as $type_item ) :
			if ( ! $type_item->get_prop( 'submission_enabled' ) ) {
				continue;
			}
			?>
		<label class="listora-submission__type-card">
			<input type="radio" name="listing_type" value="<?php echo esc_attr( $type_item->get_slug() ); ?>" required
				data-wp-on--change="actions.selectSubmissionType" />
			<span class="listora-submission__type-card-inner" style="--listora-type-color: <?php echo esc_attr( $type_item->get_color() ); ?>">
				<?php echo \WBListora\Core\Lucide_Icons::render( $type_item->get_icon(), 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="listora-submission__type-name"><?php echo esc_html( $type_item->get_name() ); ?></span>
			</span>
		</label>
		<?php endforeach; ?>
	</div>
</div>
