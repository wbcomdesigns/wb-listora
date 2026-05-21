<?php
/**
 * User Dashboard — My Claims tab content.
 *
 * Lists claim requests the current user has submitted, with status pill
 * and a link back to the source listing. This template can be overridden
 * by copying it to: {theme}/wb-listora/blocks/user-dashboard/tab-claims.php
 *
 * @package WBListora
 *
 * @var int    $user_id     Current user ID.
 * @var string $default_tab Default active tab slug.
 * @var array  $user_claims Array of claim rows (associative arrays).
 * @var array  $view_data   Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

$claim_status_labels = array(
	'pending'  => array(
		'label' => __( 'Under review', 'wb-listora' ),
		'class' => 'listora-dashboard__status--pending',
	),
	'approved' => array(
		'label' => __( 'Approved', 'wb-listora' ),
		'class' => 'listora-dashboard__status--publish',
	),
	'rejected' => array(
		'label' => __( 'Not approved', 'wb-listora' ),
		'class' => 'listora-dashboard__status--rejected',
	),
);

do_action( 'wb_listora_before_dashboard_claims', $view_data );
?>
<div role="tabpanel" id="dash-panel-claims" aria-labelledby="dash-tab-claims" class="listora-dashboard__panel"
	<?php echo 'claims' !== $default_tab ? 'hidden' : ''; ?>>

	<?php if ( empty( $user_claims ) ) : ?>
	<div class="listora-dashboard__empty">
		<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
			<path d="M9 12l2 2 4-4"/>
			<path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9c1.86 0 3.59.56 5.03 1.53"/>
		</svg>
		<h3><?php esc_html_e( 'No claim requests yet', 'wb-listora' ); ?></h3>
		<p><?php esc_html_e( 'Claim an existing business listing to manage it. Click "Claim this listing" on any unowned entry.', 'wb-listora' ); ?></p>
		<a href="<?php echo esc_url( wb_listora_get_directory_url() ); ?>" class="listora-btn listora-btn--primary">
			<?php esc_html_e( 'Browse the directory', 'wb-listora' ); ?>
		</a>
	</div>
	<?php else : ?>
	<ul class="listora-dashboard__claims-list">
		<?php
		foreach ( $user_claims as $claim ) :
			$status       = (string) ( $claim['status'] ?? 'pending' );
			$status_info  = $claim_status_labels[ $status ] ?? $claim_status_labels['pending'];
			$listing_id   = (int) ( $claim['listing_id'] ?? 0 );
			$listing_url  = $listing_id ? (string) get_permalink( $listing_id ) : '';
			$listing_name = (string) ( $claim['listing_title'] ?? __( '(listing removed)', 'wb-listora' ) );
			$submitted    = ! empty( $claim['created_at'] )
				? wp_date( get_option( 'date_format' ), strtotime( $claim['created_at'] ) )
				: '';
			$admin_notes  = (string) ( $claim['admin_notes'] ?? '' );
			?>
		<li class="listora-dashboard__claim-row">
			<div class="listora-dashboard__claim-main">
				<h3 class="listora-dashboard__claim-title">
					<?php if ( $listing_url ) : ?>
						<a href="<?php echo esc_url( $listing_url ); ?>"><?php echo esc_html( $listing_name ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $listing_name ); ?>
					<?php endif; ?>
				</h3>
				<div class="listora-dashboard__claim-meta">
					<span class="listora-dashboard__status <?php echo esc_attr( $status_info['class'] ); ?>">
						<?php echo esc_html( $status_info['label'] ); ?>
					</span>
					<?php if ( $submitted ) : ?>
					<span class="listora-dashboard__claim-submitted">
						<?php
						/* translators: %s: submission date */
						printf( esc_html__( 'Submitted %s', 'wb-listora' ), esc_html( $submitted ) );
						?>
					</span>
					<?php endif; ?>
				</div>
				<?php if ( 'rejected' === $status && $admin_notes ) : ?>
				<p class="listora-dashboard__claim-notes">
					<strong><?php esc_html_e( 'Reviewer notes:', 'wb-listora' ); ?></strong>
					<?php echo esc_html( $admin_notes ); ?>
				</p>
				<?php elseif ( 'pending' === $status ) : ?>
				<p class="listora-dashboard__claim-notes listora-dashboard__claim-notes--muted">
					<?php esc_html_e( 'We will email you as soon as the review is complete.', 'wb-listora' ); ?>
				</p>
				<?php elseif ( 'approved' === $status ) : ?>
				<p class="listora-dashboard__claim-notes listora-dashboard__claim-notes--success">
					<?php esc_html_e( 'You now own this listing. Open My Listings to edit it.', 'wb-listora' ); ?>
				</p>
				<?php endif; ?>
			</div>

			<div class="listora-dashboard__claim-actions">
				<?php if ( 'approved' === $status && $listing_id ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'edit', $listing_id, wb_listora_get_submit_url() ) ); ?>"
					class="listora-btn listora-btn--primary listora-btn--sm">
					<?php esc_html_e( 'Edit Listing', 'wb-listora' ); ?>
				</a>
				<?php elseif ( $listing_url ) : ?>
				<a href="<?php echo esc_url( $listing_url ); ?>" class="listora-btn listora-btn--secondary listora-btn--sm">
					<?php esc_html_e( 'View Listing', 'wb-listora' ); ?>
				</a>
				<?php endif; ?>
			</div>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>
</div>
<?php
do_action( 'wb_listora_after_dashboard_claims', $view_data );
