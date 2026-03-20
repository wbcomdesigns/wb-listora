/**
 * Listing Search Block — Interactivity API view module.
 *
 * Extends the shared store with search-block-specific actions.
 *
 * @package WBListora
 */

import { store, getContext } from '@wordpress/interactivity';

const { state, actions } = store( 'listora/directory', {
	actions: {
		/**
		 * Toggle the filters panel visibility.
		 */
		toggleFiltersPanel() {
			const panel = document.getElementById( 'listora-filters-panel' );
			const btn = document.querySelector( '.listora-search__toggle-btn' );

			if ( ! panel ) return;

			const isVisible = ! panel.classList.contains( 'is-hidden' );

			if ( isVisible ) {
				panel.classList.add( 'is-hidden' );
				if ( btn ) btn.setAttribute( 'aria-expanded', 'false' );
			} else {
				panel.classList.remove( 'is-hidden' );
				if ( btn ) btn.setAttribute( 'aria-expanded', 'true' );

				// Focus first input in panel.
				const firstInput = panel.querySelector( 'input, select' );
				if ( firstInput ) {
					setTimeout( () => firstInput.focus(), 100 );
				}
			}
		},

		/**
		 * Handle type selection from the dropdown (not tabs).
		 * The tabs use selectType via context, this handles the <select> change.
		 */
		selectTypeFromDropdown( event ) {
			const slug = event.target.value;
			// Set context for the shared selectType action.
			const ctx = getContext();
			ctx.typeSlug = slug;
			actions.selectType();
		},
	},
} );

/**
 * Initialize: hide filters panel by default.
 */
document.addEventListener( 'DOMContentLoaded', () => {
	const panel = document.getElementById( 'listora-filters-panel' );
	if ( panel ) {
		panel.classList.add( 'is-hidden' );
	}
} );
