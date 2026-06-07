/**
 * WB Listora — listings list-table bulk-edit helper.
 *
 * WordPress core's bulk-action UI has no slot for an extra field, so when the
 * admin chooses "Assign category" this script reveals a category <select> next
 * to the bulk-action selector. The chosen term ID rides the normal bulk submit
 * as a hidden/select field named `listora_bulk_category`, which the PHP handler
 * (Listing_Bulk_Actions::handle_bulk_actions) reads.
 *
 * No jQuery, no inline handlers — delegated listeners only.
 *
 * @package WBListora\Admin
 */
( function () {
	'use strict';

	var config = window.listoraBulkEdit || {};
	var ASSIGN_ACTION = config.action || 'listora_bulk_assign_category';
	var FIELD_NAME = config.fieldName || 'listora_bulk_category';

	/**
	 * Build the category <select> for one bulk toolbar.
	 *
	 * @param {string} suffix Unique suffix ("top" | "2") so IDs stay unique.
	 * @return {HTMLSelectElement}
	 */
	function buildCategorySelect( suffix ) {
		var select = document.createElement( 'select' );
		select.name = FIELD_NAME;
		select.className = 'listora-bulk-category-select';
		select.id = 'listora-bulk-category-' + suffix;
		select.setAttribute( 'aria-label', config.fieldAriaTip || 'Category' );
		// Disabled by default so it never submits an empty value for other actions.
		select.disabled = true;
		select.style.display = 'none';
		select.style.marginInlineStart = '6px';

		var placeholder = document.createElement( 'option' );
		placeholder.value = '';
		placeholder.textContent = config.selectLabel || 'Select category';
		select.appendChild( placeholder );

		( config.categories || [] ).forEach( function ( cat ) {
			var option = document.createElement( 'option' );
			option.value = String( cat.id );
			option.textContent = cat.name;
			select.appendChild( option );
		} );

		return select;
	}

	/**
	 * Toggle the category select's visibility based on the chosen bulk action.
	 *
	 * @param {HTMLSelectElement} actionSelect The core bulk-action <select>.
	 * @param {HTMLSelectElement} categorySelect The injected category <select>.
	 */
	function syncVisibility( actionSelect, categorySelect ) {
		var isAssign = actionSelect.value === ASSIGN_ACTION;
		categorySelect.style.display = isAssign ? '' : 'none';
		categorySelect.disabled = ! isAssign;
		if ( ! isAssign ) {
			categorySelect.value = '';
		}
	}

	/**
	 * Wire one bulk-action <select> (there are two: #bulk-action-selector-top
	 * and -bottom).
	 *
	 * @param {HTMLSelectElement} actionSelect
	 */
	function wireToolbar( actionSelect ) {
		if ( ! actionSelect || actionSelect.dataset.listoraBulkWired === '1' ) {
			return;
		}
		actionSelect.dataset.listoraBulkWired = '1';

		var suffix = actionSelect.id.indexOf( 'bottom' ) !== -1 ? '2' : 'top';
		var categorySelect = buildCategorySelect( suffix );
		actionSelect.parentNode.insertBefore( categorySelect, actionSelect.nextSibling );

		actionSelect.addEventListener( 'change', function () {
			syncVisibility( actionSelect, categorySelect );
		} );

		syncVisibility( actionSelect, categorySelect );
	}

	function init() {
		var selectors = document.querySelectorAll(
			'#bulk-action-selector-top, #bulk-action-selector-bottom'
		);
		Array.prototype.forEach.call( selectors, wireToolbar );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
