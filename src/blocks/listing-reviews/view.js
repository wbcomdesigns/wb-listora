/**
 * Listing Reviews — Interactivity API view module.
 *
 * Handles review form toggle, submission, helpful votes, report, reply.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import '../../interactivity/store.js';
import {
	abortableApiFetch,
	isAbortError,
	NETWORK_SLOW_MESSAGE,
} from '../../utils/abortable-fetch.js';

store( 'listora/directory', {
	actions: {
		/**
		 * Sort reviews by changing URL parameter — server re-renders in new order.
		 */
		sortReviews() {
			const el = getElement();
			const sort = el.ref.value;
			const url = new URL( window.location.href );
			if ( sort && sort !== 'newest' ) {
				url.searchParams.set( 'review_sort', sort );
			} else {
				url.searchParams.delete( 'review_sort' );
			}
			window.location.href = url.toString();
		},

		/**
		 * Toggle review form visibility.
		 */
		toggleReviewForm() {
			const el = getElement();
			const block = el.ref.closest( '.listora-reviews' );
			const form = block?.querySelector( '#listora-review-form' );
			if ( form ) {
				form.hidden = ! form.hidden;
				if ( ! form.hidden ) {
					const firstInput = form.querySelector( 'input[type="radio"], input[type="text"]' );
					if ( firstInput ) firstInput.focus();
				}
			}
		},

		/**
		 * Submit review via REST API.
		 */
		async submitReviewForm( event ) {
			event.preventDefault();

			const ctx = getContext();
			const el = getElement();
			const form = el.ref.closest( '.listora-reviews__form' ) || el.ref;
			const block = el.ref.closest( '.listora-reviews' );

			const rating = form.querySelector( 'input[name="overall_rating"]:checked' )?.value;
			const title = form.querySelector( 'input[name="title"]' )?.value;
			const contentField = form.querySelector( 'textarea[name="content"]' );
			const content = contentField?.value;

			// Content is only mandatory when the textarea is server-rendered
			// `required` (reviews.min_length > 0). When min_length is 0 the
			// admin allows rating-only reviews, so an empty body must pass —
			// the REST endpoint validates the length authoritatively.
			const contentRequired = contentField?.required ?? true;
			if ( ! rating || ! title || ( contentRequired && ! content ) ) return;

			// Collect criteria ratings — radio inputs named criteria_ratings[key].
			const criteriaRatings = {};
			form.querySelectorAll( 'input[name^="criteria_ratings["]:checked' ).forEach( ( input ) => {
				const match = input.name.match( /^criteria_ratings\[([^\]]+)\]$/ );
				if ( match ) {
					criteriaRatings[ match[ 1 ] ] = parseInt( input.value, 10 );
				}
			} );

			const submitBtn = form.querySelector( 'button[type="submit"]' );
			const msgDiv = form.querySelector( '.listora-reviews__form-message' );

			if ( submitBtn ) {
				submitBtn.disabled = true;
				submitBtn.textContent = 'Submitting...';
			}

			const requestData = {
				listing_id: ctx.listingId,
				overall_rating: parseInt( rating, 10 ),
				title,
				content,
			};

			if ( Object.keys( criteriaRatings ).length > 0 ) {
				requestData.criteria_ratings = criteriaRatings;
			}

			try {
				const response = await abortableApiFetch( {
					path: `/listora/v1/listings/${ ctx.listingId }/reviews`,
					method: 'POST',
					data: requestData,
				} );

				// Show success.
				if ( msgDiv ) {
					msgDiv.hidden = false;
					msgDiv.textContent = response.message || 'Review submitted!';
					msgDiv.style.color = 'var(--listora-success)';
				}

				// Hide form after delay.
				setTimeout( () => {
					const wrapper = block?.querySelector( '#listora-review-form' );
					if ( wrapper ) wrapper.hidden = true;
					// Reload page to show new review.
					window.location.reload();
				}, 2000 );

			} catch ( error ) {
				const errMsg = isAbortError( error )
					? NETWORK_SLOW_MESSAGE
					: ( error.message || 'Failed to submit review.' );
				if ( msgDiv ) {
					msgDiv.hidden = false;
					msgDiv.textContent = errMsg;
					msgDiv.style.color = 'var(--listora-danger)';
				}
				if ( submitBtn ) {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Submit Review';
				}
			}
		},

		/**
		 * Vote a review as helpful.
		 */
		async voteReviewHelpful() {
			const ctx = getContext();
			const el = getElement();
			const btn = el.ref;

			try {
				const response = await abortableApiFetch( {
					path: `/listora/v1/reviews/${ ctx.reviewId }/helpful`,
					method: 'POST',
				} );

				// Update count in UI safely.
				let countSpan = btn.querySelector( '.listora-reviews__helpful-count' );
				if ( countSpan ) {
					countSpan.textContent = `(${ response.helpful_count })`;
				} else {
					countSpan = document.createElement( 'span' );
					countSpan.className = 'listora-reviews__helpful-count';
					countSpan.textContent = `(${ response.helpful_count })`;
					btn.appendChild( countSpan );
				}

				btn.disabled = true;
				btn.style.color = 'var(--listora-primary)';
			} catch ( error ) {
				// Already voted, network error, or timeout — silently handle.
				// Surface a toast for timeout so the user knows to retry.
				if ( isAbortError( error ) && window.listoraToast ) {
					window.listoraToast( NETWORK_SLOW_MESSAGE, 'error' );
				}
				btn.style.opacity = '0.5';
			}
		},

		// `showReportModal` now lives in src/interactivity/store.js — it opens
		// the accessible, focus-managed page-level review-report dialog
		// (templates/blocks/listing-reviews/reviews.php) instead of the former
		// native prompt(), which was inaccessible to screen readers and blocked
		// under strict Content-Security-Policy. Per the plugin's IAPI rule, all
		// actions live in the shared store, not in individual view.js files.

		/**
		 * Show reply form for listing owner.
		 */
		showReplyForm() {
			const ctx = getContext();
			const el = getElement();
			const review = el.ref.closest( '.listora-reviews__review' );
			if ( ! review ) return;

			// Check if form already exists.
			if ( review.querySelector( '.listora-reviews__reply-form' ) ) return;

			const form = document.createElement( 'div' );
			form.className = 'listora-reviews__reply-form';
			form.style.cssText = 'margin-top:0.75rem;margin-left:var(--listora-space-4);';

			const textarea = document.createElement( 'textarea' );
			textarea.className = 'listora-input listora-submission__textarea';
			textarea.rows = 3;
			textarea.placeholder = 'Write your reply...';
			textarea.required = true;
			form.appendChild( textarea );

			const btnRow = document.createElement( 'div' );
			btnRow.style.cssText = 'display:flex;gap:0.5rem;margin-top:0.5rem;';

			const submitBtn = document.createElement( 'button' );
			submitBtn.className = 'listora-btn listora-btn--primary';
			submitBtn.textContent = 'Reply';
			submitBtn.style.fontSize = '0.85rem';
			submitBtn.addEventListener( 'click', async () => {
				const content = textarea.value.trim();
				if ( ! content ) return;

				submitBtn.disabled = true;
				submitBtn.textContent = 'Sending...';

				try {
					await abortableApiFetch( {
						path: `/listora/v1/reviews/${ ctx.reviewId }/reply`,
						method: 'POST',
						data: { content },
					} );
					window.location.reload();
				} catch ( error ) {
					if ( window.listoraToast ) {
						const msg = isAbortError( error )
							? NETWORK_SLOW_MESSAGE
							: ( error.message || 'Failed to reply.' );
						listoraToast( msg, { type: 'error' } );
					}
					submitBtn.disabled = false;
					submitBtn.textContent = 'Reply';
				}
			} );
			btnRow.appendChild( submitBtn );

			const cancelBtn = document.createElement( 'button' );
			cancelBtn.className = 'listora-btn listora-btn--text';
			cancelBtn.textContent = 'Cancel';
			cancelBtn.style.fontSize = '0.85rem';
			cancelBtn.addEventListener( 'click', () => form.remove() );
			btnRow.appendChild( cancelBtn );

			form.appendChild( btnRow );
			el.ref.replaceWith( form );
		},

		/**
		 * Load more reviews.
		 */
		async loadMoreReviews() {
			// For v1, reload page — in v2, use AJAX pagination.
			window.location.hash = 'reviews';
			window.location.reload();
		},
	},
} );
