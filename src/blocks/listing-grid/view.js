/**
 * Listing Grid — Interactivity API view module.
 *
 * Handles dynamic result updates after search.
 * Server-renders initial results, JS updates on search/filter/page changes.
 *
 * @package WBListora
 */

import { store, getContext } from '@wordpress/interactivity';

// The grid reads results from the shared store (state.results).
// When search updates results, the server-rendered cards remain but
// the Interactivity API reactivity handles hiding/showing the loading state.
//
// For full dynamic rendering (replacing cards via JS), we would need
// wp_interactivity_process_directives_of_interactive_blocks or
// a wp-router approach. For v1, we use a hybrid:
// - Initial page load = server-rendered cards (SEO)
// - Search = full page navigation with URL params (progressive enhancement)
// - Interactivity handles: loading state, pagination, sort, view mode toggle

store(
	'listora/directory',
	{
		callbacks: {
			/**
			 * Called when grid block initializes.
			 * Sets initial view mode from block attributes.
			 */
			onGridInit() {
				const ctx = getContext();
				if ( ctx.defaultView ) {
					const { state } = store( 'listora/directory' );
					if ( ! state.viewMode ) {
						state.viewMode = ctx.defaultView;
					}
				}
			},
		},
	}
);
