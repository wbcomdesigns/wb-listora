/**
 * The business-hours builder: time pickers, the Open 24 hours / Closed toggles,
 * and the add / remove range controls.
 *
 * One implementation for the Add Listing wizard and the wp-admin listing
 * editor. Both print the same `.listora-submission__hours-*` markup from
 * wb_listora_render_submission_field(); the wizard drives it from its view
 * module and wp-admin from src/admin/listing-hours-builder.js, so a change here
 * reaches both.
 *
 * @package WBListora
 */

/**
 * Attach flatpickr time picker to every business_hours input.
 *
 * The submission form renders `<input type="time">` for each weekday
 * open/close slot — Chrome and Edge show a clock-chrome dropdown for
 * those, Firefox renders a numeric spinner only. flatpickr provides a
 * consistent click-to-pick UI across all browsers. The native input is replaced visually by flatpickr's text
 * input but keeps its `name` attribute, so form submission posts the
 * same `HH:MM` value the server has always parsed.
 *
 * Idempotent: flatpickr stores its instance on the element and the
 * `data-listora-flatpickr-attached` flag prevents double-initialisation
 * if a re-render fires this twice.
 */
export function initBusinessHoursPickers( root ) {
	if ( typeof window.flatpickr !== 'function' ) {
		return;
	}
	const scope = root || document;
	const inputs = scope.querySelectorAll(
		'.listora-submission__hours-input:not([data-listora-flatpickr-attached])'
	);
	inputs.forEach( ( input ) => {
		input.dataset.listoraFlatpickrAttached = '1';
		window.flatpickr( input, {
			enableTime: true,
			noCalendar: true,
			dateFormat: 'H:i',
			time_24hr: true,
			minuteIncrement: 15,
			allowInput: true,
			// flatpickr appends its panel to <body>, outside the form, so the
			// class is how the panel picks up Listora's tokens without touching
			// any other plugin's picker.
			onReady: ( _dates, _str, instance ) => {
				instance.calendarContainer.classList.add( 'listora-flatpickr' );
			},
		} );
	} );
}

/**
 * Live state for the hours-builder "Open 24 hours" / "Closed" toggles.
 *
 * The SSR markup (submission-field-renderer.php) seeds the `is-24h` /
 * `is-closed` card class, the disabled state on the open/close inputs,
 * and the inline `.listora-submission__hours-state` chip from saved
 * data — this delegated handler keeps all three in sync as the user
 * toggles, so the row gives immediate feedback before save
 * (flow-closure f10-lifecycle-polish).
 *
 * Mutual exclusivity: checking either toggle unchecks the other —
 * mirrors the render-side guard ("Closed wins" when both are set in
 * hand-edited data). Delegated on document so re-rendered hours blocks
 * (listing-type switches) need no re-binding.
 */
function initBusinessHoursToggles() {
	document.addEventListener( 'change', ( event ) => {
		const checkbox = event.target;
		if (
			! checkbox.matches ||
			! checkbox.matches(
				'.listora-submission__hours-24h, .listora-submission__hours-closed'
			)
		) {
			return;
		}
		const card = checkbox.closest( '.listora-submission__hours-card' );
		if ( ! card ) {
			return;
		}
		const cb24h = card.querySelector( '.listora-submission__hours-24h' );
		const cbClosed = card.querySelector(
			'.listora-submission__hours-closed'
		);
		if ( checkbox.checked ) {
			if ( checkbox === cb24h && cbClosed ) {
				cbClosed.checked = false;
			}
			if ( checkbox === cbClosed && cb24h ) {
				cb24h.checked = false;
			}
		}
		const isClosed = !! ( cbClosed && cbClosed.checked );
		const is24h = ! isClosed && !! ( cb24h && cb24h.checked );
		card.classList.toggle( 'is-closed', isClosed );
		card.classList.toggle( 'is-24h', is24h );
		// No times apply when a day is Closed or Open-24h — disabling the
		// native input also stops its flatpickr instance from opening.
		card.querySelectorAll( '.listora-submission__hours-input' ).forEach(
			( input ) => {
				input.disabled = isClosed || is24h;
			}
		);
		const chip = card.querySelector( '.listora-submission__hours-state' );
		if ( chip ) {
			const i18n =
				( typeof window !== 'undefined' && window.listoraI18n ) || {};
			let label = '';
			if ( isClosed ) {
				label = i18n.closed || 'Closed';
			} else if ( is24h ) {
				label = i18n.open24h || 'Open 24 Hours';
			}
			chip.textContent = label;
		}
	} );
}


/**
 * Renumber every range in a day after an add or a remove.
 *
 * Slot indexes have to stay contiguous from 0. The server reads
 * `business_hours[<day>][ranges][<slot>][open|close]` and treats the ranges as
 * a list — removing the middle of 0/1/2 and leaving 0/2 makes PHP hand the
 * indexer a sparse array whose keys no longer match the `slot` column the
 * hours table stores. Renumbering here is what keeps the posted shape the same
 * shape the SSR would have produced for that data.
 *
 * Also re-derives the aria-labels, because "Monday opening time 3" is wrong
 * the moment range 2 is deleted.
 */
export function renumberHoursRanges( ranges, dayName, patterns ) {
	const rows = ranges.querySelectorAll( '.listora-submission__hours-times' );
	rows.forEach( ( row, slot ) => {
		row.dataset.slot = String( slot );
		row.querySelectorAll( 'input[name]' ).forEach( ( input ) => {
			// Only the slot index moves — the field name, day and open/close
			// key are all left exactly as the server rendered them.
			input.name = input.name.replace(
				/\[ranges]\[\d+]/,
				'[ranges][' + slot + ']'
			);
			const isClose = /\[close]$/.test( input.name );
			const pattern = isClose ? patterns.close : patterns.open;
			input.setAttribute(
				'aria-label',
				pattern
					.replace( '%1$s', dayName )
					.replace( '%2$d', String( slot + 1 ) )
			);
		} );
		const removeBtn = row.querySelector(
			'.listora-submission__hours-remove'
		);
		if ( removeBtn ) {
			removeBtn.setAttribute(
				'aria-label',
				patterns.remove
					.replace( '%1$s', dayName )
					.replace( '%2$d', String( slot + 1 ) )
			);
		}
	} );
	return rows.length;
}

/**
 * Add / remove time ranges within a day (split shifts).
 *
 * A café that opens 08:00-12:00 and again 17:00-22:00 must be able to
 * record both; a single range would make the "Open
 * now" badge wrong for half the day. The SSR already renders every saved
 * range; this is the half that lets a member create the second one.
 *
 * The new row is CLONED from the day's first range rather than built here, so
 * the clock-icon SVG, the input classes and the wrapper markup have exactly
 * one definition — the renderer. The cap and the aria-label patterns come off
 * the builder's data attributes for the same reason (see the renderer's
 * comment): the JS owns no vocabulary of its own.
 *
 * Delegated on document so a listing-type switch that re-renders the hours
 * block needs no rebinding.
 */
function initBusinessHoursRanges() {
	document.addEventListener( 'click', ( event ) => {
		const target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}
		const addBtn = target.closest( '.listora-submission__hours-add' );
		const removeBtn = target.closest( '.listora-submission__hours-remove' );
		if ( ! addBtn && ! removeBtn ) {
			return;
		}

		const card = ( addBtn || removeBtn ).closest(
			'.listora-submission__hours-card'
		);
		const builder = ( addBtn || removeBtn ).closest(
			'.listora-submission__hours-builder'
		);
		if ( ! card || ! builder ) {
			return;
		}
		event.preventDefault();

		const ranges = card.querySelector( '.listora-submission__hours-ranges' );
		if ( ! ranges ) {
			return;
		}
		const dayEl = card.querySelector( '.listora-submission__hours-day' );
		const dayName = dayEl ? dayEl.textContent.trim() : '';
		const patterns = {
			open: builder.dataset.tplOpen || '%1$s opening time %2$d',
			close: builder.dataset.tplClose || '%1$s closing time %2$d',
			remove: builder.dataset.tplRemove || 'Remove %1$s time %2$d',
		};
		// Fall back to the shipped default rather than to "unlimited" — a
		// missing attribute must not let the form post more ranges than the
		// save path will keep.
		const maxSlots = parseInt( builder.dataset.maxSlots, 10 ) || 3;

		if ( removeBtn ) {
			const row = removeBtn.closest( '.listora-submission__hours-times' );
			if ( row ) {
				row.remove();
			}
		} else {
			const rows = ranges.querySelectorAll(
				'.listora-submission__hours-times'
			);
			if ( rows.length >= maxSlots ) {
				return;
			}
			const clone = rows[ 0 ].cloneNode( true );
			clone.querySelectorAll( 'input' ).forEach( ( input ) => {
				input.value = '';
				input.disabled = false;
				// flatpickr marks the inputs it has taken over; the clone must
				// look untouched or initBusinessHoursPickers will skip it and
				// the new row gets no picker.
				delete input.dataset.listoraFlatpickrAttached;
				input.classList.remove( 'flatpickr-input' );
				input.removeAttribute( 'readonly' );
			} );
			// The first range never carries a remove button (a day cannot have
			// zero ranges), so the clone has to grow one.
			if (
				! clone.querySelector( '.listora-submission__hours-remove' )
			) {
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'listora-submission__hours-remove';
				btn.innerHTML = '&times;';
				clone.appendChild( btn );
			}
			ranges.insertBefore( clone, addBtn );
			initBusinessHoursPickers( clone );
		}

		const count = renumberHoursRanges( ranges, dayName, patterns );

		// Absent, not disabled, at the cap — matching the SSR. A greyed
		// control invites a click and then has to explain itself.
		const add = ranges.querySelector( '.listora-submission__hours-add' );
		if ( add ) {
			add.hidden = count >= maxSlots;
		}
	} );
}

/**
 * Start the builder: pickers now (and on DOMContentLoaded), toggles and range
 * controls delegated on the document, so a re-rendered hours block needs no
 * rebinding.
 *
 * @return {void}
 */
export function initBusinessHoursBuilder() {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => initBusinessHoursPickers() );
	} else {
		initBusinessHoursPickers();
	}
	initBusinessHoursToggles();
	initBusinessHoursRanges();
}
