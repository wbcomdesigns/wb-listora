/**
 * Persist dismissal of the "Review your pages" admin notice.
 *
 * The notice is rendered with `is-dismissible`, so core paints an X on it —
 * but core's X is client-side only: it hides the notice for the current
 * pageload and stores nothing. Admins reasonably click it instead of the
 * "Dismiss" link, so the notice reappeared on every admin screen until the
 * 7-day transient expired, and re-armed on every plugin reactivation.
 *
 * Listening on the notice rather than the button matters: core injects
 * `.notice-dismiss` from common.js after this markup is parsed.
 *
 * @package WBListora
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var notice = document.querySelector( '.listora-pages-review-notice' );

		if ( ! notice || ! notice.dataset.listoraDismissUrl ) {
			return;
		}

		notice.addEventListener( 'click', function ( event ) {
			var target = event.target;

			if ( ! target || ! target.classList || ! target.classList.contains( 'notice-dismiss' ) ) {
				return;
			}

			// Fire-and-forget: the notice is already hidden by core, and the
			// endpoint only writes the user-meta flag and clears the transient.
			window.fetch( notice.dataset.listoraDismissUrl, {
				credentials: 'same-origin',
			} ).catch( function () {} );
		} );
	} );
}() );
