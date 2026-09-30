/**
 * Listing Search Block — Interactivity API view module.
 *
 * Extends the shared store with search-block-specific actions.
 *
 * @package WBListora
 */

import { store } from '@wordpress/interactivity';
import '../../interactivity/store.js';

const { state } = store( 'listora/directory', {
	actions: {
		/**
		 * Toggle the filters panel. The panel's `hidden` / `is-hidden` and the
		 * button's `aria-expanded` are all bound to this one flag.
		 */
		toggleFiltersPanel() {
			state.showFiltersPanel = ! state.showFiltersPanel;

			if ( state.showFiltersPanel ) {
				const panel = document.getElementById( 'listora-filters-panel' );
				const firstInput = panel && panel.querySelector( 'input, select' );
				if ( firstInput ) {
					setTimeout( () => firstInput.focus(), 100 );
				}
			}
		},
	},
} );

/**
 * Type chip row: arrows and edge fades appear only while it overflows
 * (card 10337186901). Plain DOM: the row is server-rendered and the
 * affordance carries no state worth hydrating.
 */
function initTypeTabsScroll() {
	document.querySelectorAll( '.listora-search__type-tabs-wrap' ).forEach( ( wrap ) => {
		const row = wrap.querySelector( '.listora-search__type-tabs' );
		if ( ! row ) return;

		const update = () => {
			const max = row.scrollWidth - row.clientWidth;
			const pos = Math.abs( row.scrollLeft );
			const overflows = max > 1;
			wrap.classList.toggle( 'is-scroll-start', overflows && pos > 1 );
			wrap.classList.toggle( 'is-scroll-end', overflows && pos < max - 1 );
			wrap.querySelectorAll( '.listora-search__type-tabs-nav' ).forEach( ( btn ) => {
				btn.hidden = ! overflows;
			} );
		};

		wrap.querySelectorAll( '[data-listora-scroll]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const dir = Number( btn.dataset.listoraScroll ) || 1;
				const rtl = 'rtl' === getComputedStyle( row ).direction;
				row.scrollBy( { left: dir * ( rtl ? -1 : 1 ) * row.clientWidth * 0.6, behavior: 'smooth' } );
			} );
		} );

		row.addEventListener( 'scroll', update, { passive: true } );
		window.addEventListener( 'resize', update );
		update();
	} );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', initTypeTabsScroll );
} else {
	initTypeTabsScroll();
}
