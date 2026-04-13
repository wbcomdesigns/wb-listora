<?php
/**
 * Listing Submission — Progress stepper indicator.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-submission/stepper.php
 *
 * @package WBListora
 *
 * @var array $steps       Step definitions array, each with 'id', 'label', 'num'.
 * @var int   $total_steps Total number of steps.
 * @var array $view_data   Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="listora-submission__progress" role="progressbar" aria-valuemin="1" aria-valuemax="<?php echo esc_attr( $total_steps ); ?>" aria-valuenow="1" aria-label="<?php esc_attr_e( 'Submission progress', 'wb-listora' ); ?>">
	<?php foreach ( $steps as $i => $step ) : ?>
		<?php if ( $i > 0 ) : ?>
		<div class="listora-submission__step-line"></div>
		<?php endif; ?>
		<div class="listora-submission__step-indicator <?php echo 0 === $i ? 'is-current' : ''; ?>" data-step="<?php echo esc_attr( $step['id'] ); ?>">
			<span class="listora-submission__step-dot"><?php echo esc_html( $step['num'] ); ?></span>
			<span class="listora-submission__step-label"><?php echo esc_html( $step['label'] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
