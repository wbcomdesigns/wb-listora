<?php
/**
 * Report-a-review modal — shared by every surface that lists reviews.
 *
 * This markup lived only in the listing-reviews block, so the canonical
 * listing page — which renders its own review list in
 * blocks/listing-detail/tabs.php — had no report dialog at all, and the
 * report flow was unreachable there (BC 10154926676).
 *
 * One copy, included by both, so the reason options, the REST call and the
 * dialog wiring cannot drift between the two lists the way the report ENUM
 * itself did.
 *
 * Reasons come from wb_listora_get_review_report_reasons(), the same helper the
 * REST endpoint validates against, so the form can never offer an option the
 * endpoint rejects.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/reviews/report-modal.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;
?>
	<div class="listora-detail__modal" id="listora-report-review-modal" data-wp-class--is-open="state.isReportReviewModalOpen">
		<div class="listora-detail__modal-backdrop" data-wp-on--click="actions.closeReportReviewModal"></div>
		<div class="listora-detail__modal-content" id="listora-report-review-dialog" role="dialog" aria-modal="true" aria-labelledby="listora-report-review-title" tabindex="-1" data-wp-on--keydown="actions.handleReportReviewKeydown">
			<button type="button" class="listora-detail__modal-close" data-wp-on--click="actions.closeReportReviewModal" aria-label="<?php esc_attr_e( 'Close', 'wb-listora' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
			<h3 id="listora-report-review-title"><?php esc_html_e( 'Report This Review', 'wb-listora' ); ?></h3>
			<p class="listora-detail__modal-desc"><?php esc_html_e( 'Tell us what is wrong so our team can review it.', 'wb-listora' ); ?></p>
			<form class="listora-detail__report-form" data-wp-on--submit="actions.submitReviewReport">
				<div class="listora-detail__report-body">
					<div class="listora-submission__field">
						<label for="listora-report-review-reason" class="listora-submission__label"><?php esc_html_e( 'Reason', 'wb-listora' ); ?> *</label>
						<select id="listora-report-review-reason" name="reason" class="listora-input" required data-wp-on--change="actions.setReportReviewReason">
							<option value=""><?php esc_html_e( 'Select a reason…', 'wb-listora' ); ?></option>
							<?php // Review reasons, NOT the listing enum — the listing keys are rejected by /reviews/{id}/report (BC 10154926676). ?>
							<?php foreach ( wb_listora_get_review_report_reasons() as $listora_reason_key => $listora_reason_label ) : ?>
							<option value="<?php echo esc_attr( $listora_reason_key ); ?>"><?php echo esc_html( $listora_reason_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="listora-detail__report-actions">
						<button type="submit" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Submit Report', 'wb-listora' ); ?></button>
						<button type="button" class="listora-btn listora-btn--text" data-wp-on--click="actions.closeReportReviewModal"><?php esc_html_e( 'Cancel', 'wb-listora' ); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
