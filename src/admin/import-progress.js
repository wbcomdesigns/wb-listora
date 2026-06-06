/**
 * Setup-wizard background-import progress poller.
 *
 * Polls the read-only REST progress endpoint
 * (`/listora/v1/import/progress/{run_id}`) on an interval and updates the
 * progress bar + count until the run reports done or failed. Uses
 * `wp.apiFetch` (sent with the X-WP-Nonce header WP adds automatically),
 * never a raw inline script — see ux-foundation Rule 11.
 *
 * The endpoint is idempotent and safe to poll: it only reads the run option.
 * Polling backs off and stops once the run is terminal.
 *
 * @package WBListora
 * @since   1.2.0
 */

( function () {
	'use strict';

	const cfg = window.listoraImportProgress;
	if ( ! cfg || ! cfg.runId || ! cfg.endpoint ) {
		return;
	}

	const apiFetch =
		window.wp && window.wp.apiFetch ? window.wp.apiFetch : null;
	if ( ! apiFetch ) {
		return;
	}

	const __ =
		window.wp && window.wp.i18n && window.wp.i18n.__
			? ( text ) => window.wp.i18n.__( text, 'wb-listora' )
			: ( text ) => text;

	const root = document.querySelector(
		'.listora-import-progress[data-run-id="' + cfg.runId + '"]'
	);
	if ( ! root ) {
		return;
	}

	const track = root.querySelector( '.listora-import-progress__track' );
	const bar = root.querySelector( '.listora-import-progress__bar' );
	const text = root.querySelector( '.listora-import-progress__text' );
	const count = root.querySelector( '.listora-import-progress__count' );

	const POLL_MS = 2500;
	let stopped = false;

	/**
	 * Apply a progress payload to the DOM.
	 *
	 * @param {Object} data Progress payload from the REST endpoint.
	 */
	function paint( data ) {
		const percent = Math.max( 0, Math.min( 100, parseInt( data.percent, 10 ) || 0 ) );

		if ( bar ) {
			bar.style.width = percent + '%';
		}
		if ( track ) {
			track.setAttribute( 'aria-valuenow', String( percent ) );
		}
		if ( count ) {
			count.textContent = String( parseInt( data.imported, 10 ) || 0 );
		}

		if ( data.status === 'done' ) {
			root.classList.add( 'is-done' );
			if ( text ) {
				text.textContent = __( 'Demo content imported.' );
			}
		} else if ( data.status === 'failed' ) {
			root.classList.add( 'is-failed' );
			if ( text ) {
				text.textContent = __( 'Import finished with errors — check Tools → Health Check.' );
			}
		}
	}

	/**
	 * Poll once; reschedule unless terminal.
	 */
	function poll() {
		if ( stopped ) {
			return;
		}

		apiFetch( { path: cfg.endpoint } )
			.then( ( data ) => {
				if ( ! data ) {
					return;
				}
				paint( data );
				if ( data.done ) {
					stopped = true;
					return;
				}
				window.setTimeout( poll, POLL_MS );
			} )
			.catch( () => {
				// A transient error (run not yet visible, network blip) — keep
				// polling a few more times rather than abandoning the UI.
				window.setTimeout( poll, POLL_MS );
			} );
	}

	poll();

	// Re-render Lucide icons if the loader is present.
	if ( window.lucide && typeof window.lucide.createIcons === 'function' ) {
		window.lucide.createIcons();
	}
} )();
