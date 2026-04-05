/**
 * Listing Submission — Interactivity API view module.
 *
 * Handles multi-step navigation, form validation, draft saving, and submission.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'listora/directory', {
	actions: {
		/**
		 * Move to next step.
		 */
		nextSubmissionStep() {
			const el = getElement();
			const form = el.ref.closest( '.listora-submission' );
			if ( ! form ) return;

			const steps = form.querySelectorAll( '.listora-submission__step' );
			const indicators = form.querySelectorAll( '.listora-submission__step-indicator' );
			const lines = form.querySelectorAll( '.listora-submission__step-line' );
			let currentIdx = -1;

			steps.forEach( ( step, i ) => {
				if ( ! step.hidden ) currentIdx = i;
			} );

			if ( ! validateStep( steps[ currentIdx ] ) ) return;

			if ( currentIdx < steps.length - 1 ) {
				steps[ currentIdx ].hidden = true;
				steps[ currentIdx + 1 ].hidden = false;

				if ( indicators[ currentIdx ] ) {
					indicators[ currentIdx ].classList.remove( 'is-current' );
					indicators[ currentIdx ].classList.add( 'is-completed' );
				}
				if ( indicators[ currentIdx + 1 ] ) {
					indicators[ currentIdx + 1 ].classList.add( 'is-current' );
				}
				// Mark connecting line as completed.
				if ( lines[ currentIdx ] ) {
					lines[ currentIdx ].classList.add( 'is-completed' );
				}

				updateNavButtons( form, currentIdx + 1, steps.length );

				if ( currentIdx + 1 === steps.length - 1 ) {
					buildPreview( form );
				}

				if ( steps[ currentIdx + 1 ].dataset.step === 'details' ) {
					initMapPickers( steps[ currentIdx + 1 ] );
				}

				form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		},

		/**
		 * Move to previous step.
		 */
		prevSubmissionStep() {
			const el = getElement();
			const form = el.ref.closest( '.listora-submission' );
			if ( ! form ) return;

			const steps = form.querySelectorAll( '.listora-submission__step' );
			const indicators = form.querySelectorAll( '.listora-submission__step-indicator' );
			const lines = form.querySelectorAll( '.listora-submission__step-line' );
			let currentIdx = -1;

			steps.forEach( ( step, i ) => {
				if ( ! step.hidden ) currentIdx = i;
			} );

			if ( currentIdx > 0 ) {
				steps[ currentIdx ].hidden = true;
				steps[ currentIdx - 1 ].hidden = false;

				if ( indicators[ currentIdx ] ) {
					indicators[ currentIdx ].classList.remove( 'is-current' );
				}
				if ( indicators[ currentIdx - 1 ] ) {
					indicators[ currentIdx - 1 ].classList.remove( 'is-completed' );
					indicators[ currentIdx - 1 ].classList.add( 'is-current' );
				}
				// Revert connecting line.
				if ( lines[ currentIdx - 1 ] ) {
					lines[ currentIdx - 1 ].classList.remove( 'is-completed' );
				}

				updateNavButtons( form, currentIdx - 1, steps.length );
				form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		},

		/**
		 * Select listing type — auto-advance.
		 */
		selectSubmissionType() {
			setTimeout( () => {
				const el = getElement();
				const nextBtn = el.ref.closest( '.listora-submission' )?.querySelector( '.listora-submission__next' );
				if ( nextBtn ) nextBtn.click();
			}, 300 );
		},

		/**
		 * Handle form submission via REST API.
		 * When a listing_id hidden field is present (edit mode), uses POST /submit with the
		 * listing_id in the body — the server routes to update instead of create.
		 */
		async handleSubmission( event ) {
			event.preventDefault();

			const el = getElement();
			const form = el.ref.closest( '.listora-submission' );
			const formEl = form?.querySelector( '.listora-submission__form' );
			if ( ! formEl ) return;

			const hp = formEl.querySelector( '[name="listora_hp_field"]' );
			if ( hp && hp.value ) return;

			const submitBtn = form.querySelector( '.listora-submission__submit-btn' );
			const errorDiv = form.querySelector( '.listora-submission__error' );
			const successDiv = form.querySelector( '.listora-submission__success' );

			// Detect edit mode via hidden listing_id field.
			const listingIdInput = formEl.querySelector( '[name="listing_id"]' );
			const listingId = listingIdInput ? parseInt( listingIdInput.value, 10 ) : 0;
			const isEditMode = listingId > 0;

			const originalBtnText = submitBtn ? submitBtn.textContent.trim() : '';

			if ( submitBtn ) {
				submitBtn.disabled = true;
				submitBtn.textContent = isEditMode ? 'Updating...' : 'Submitting...';
			}
			if ( errorDiv ) errorDiv.hidden = true;

			try {
				const formData = new FormData( formEl );

				// Always use POST — the server detects listing_id in the body to route to update.
				await window.wp.apiFetch( {
					path: '/listora/v1/submit',
					method: 'POST',
					body: formData,
				} );

				formEl.hidden = true;
				const progress = form.querySelector( '.listora-submission__progress' );
				if ( progress ) progress.remove();
				const nav = form.querySelector( '.listora-submission__nav' );
				if ( nav ) nav.remove();
				if ( successDiv ) successDiv.hidden = false;
			} catch ( error ) {
				if ( errorDiv ) {
					errorDiv.hidden = false;
					const p = errorDiv.querySelector( 'p' );
					if ( p ) p.textContent = error.message || 'Submission failed. Please try again.';
				}
				if ( submitBtn ) {
					submitBtn.disabled = false;
					submitBtn.textContent = originalBtnText || ( isEditMode ? 'Update Listing' : 'Submit Listing' );
				}
			}
		},

		/**
		 * Save draft via REST API.
		 * In edit mode, updates the existing listing. Otherwise creates a new draft.
		 */
		async saveDraft() {
			const el = getElement();
			const form = el.ref.closest( '.listora-submission' );
			const formEl = form?.querySelector( '.listora-submission__form' );
			if ( ! formEl ) return;

			const btn = form.querySelector( '.listora-submission__save-draft' );
			if ( btn ) btn.textContent = 'Saving...';

			try {
				const formData = new FormData( formEl );
				// listing_id already in FormData when editing; server detects it.
				// For new listings only, explicitly set draft status.
				const listingIdInput = formEl.querySelector( '[name="listing_id"]' );
				const isEditMode = listingIdInput && parseInt( listingIdInput.value, 10 ) > 0;
				if ( ! isEditMode ) {
					formData.set( 'status', 'draft' );
				}

				await window.wp.apiFetch( {
					path: '/listora/v1/submit',
					method: 'POST',
					body: formData,
				} );

				if ( btn ) btn.textContent = '✓ Saved';
				setTimeout( () => {
					if ( btn ) btn.textContent = 'Save Draft';
				}, 2000 );
			} catch {
				if ( btn ) btn.textContent = 'Save Draft';
			}
		},

		/**
		 * Auto-save draft (debounced, called on field changes).
		 */
		autoSaveDraft() {
			const el = getElement();
			const form = el.ref.closest( '.listora-submission' );
			const formEl = form?.querySelector( '.listora-submission__form' );
			const indicator = form?.querySelector( '.listora-submission__autosave' );
			if ( ! formEl ) return;

			// Debounce 30 seconds.
			if ( form._autoSaveTimeout ) clearTimeout( form._autoSaveTimeout );

			form._autoSaveTimeout = setTimeout( async () => {
				if ( indicator ) {
					indicator.textContent = 'Saving...';
					indicator.className = 'listora-submission__autosave listora-submission__autosave--saving';
				}

				try {
					const formData = new FormData( formEl );
					formData.set( 'status', 'draft' );

					await window.wp.apiFetch( {
						path: '/listora/v1/submit',
						method: 'POST',
						body: formData,
					} );

					if ( indicator ) {
						indicator.textContent = 'Draft saved';
						indicator.className = 'listora-submission__autosave listora-submission__autosave--saved';
					}
				} catch {
					if ( indicator ) {
						indicator.textContent = '';
						indicator.className = 'listora-submission__autosave';
					}
				}
			}, 30000 );
		},

		/**
		 * Validate a field on blur.
		 */
		validateField() {
			const el = getElement();
			const field = el.ref.closest( '.listora-submission__field' );
			const input = el.ref;

			if ( ! field || ! input ) return;

			if ( input.required && ! input.value.trim() ) {
				field.classList.remove( 'listora-submission__field--valid' );
				field.classList.add( 'listora-submission__field--error' );
			} else if ( input.value.trim() ) {
				field.classList.remove( 'listora-submission__field--error' );
				field.classList.add( 'listora-submission__field--valid' );
			} else {
				field.classList.remove( 'listora-submission__field--valid', 'listora-submission__field--error' );
			}
		},

		/**
		 * Open WP media library for image uploads.
		 */
		openMediaUpload() {
			const ctx = getContext();
			const target = ctx.uploadTarget;

			if ( typeof wp === 'undefined' || ! wp.media ) {
				return;
			}

			const isGallery = target === 'gallery';

			const frame = wp.media( {
				title: isGallery ? 'Select Gallery Images' : 'Select Image',
				multiple: isGallery,
				library: { type: 'image' },
			} );

			frame.on( 'select', function () {
				const selection = frame.state().get( 'selection' );

				if ( isGallery ) {
					const ids = [];
					selection.each( ( attachment ) => {
						ids.push( attachment.id );
						addGalleryThumb( attachment.toJSON() );
					} );
					const input = document.querySelector( 'input[name="gallery"]' );
					if ( input ) {
						const existing = input.value ? input.value.split( ',' ) : [];
						input.value = [ ...existing, ...ids ].join( ',' );
					}
				} else {
					const attachment = selection.first().toJSON();
					const input = document.querySelector( `input[name="${ target }"]` );
					if ( input ) input.value = attachment.id;

					// Show preview using safe DOM methods.
					const zone = document.querySelector( `[data-wp-context*="${ target }"]` );
					if ( zone ) {
						zone.textContent = '';
						const img = document.createElement( 'img' );
						img.src = attachment.sizes?.medium?.url || attachment.url;
						img.alt = '';
						img.style.cssText = 'max-width:100%;border-radius:var(--listora-card-radius);';
						zone.appendChild( img );
					}
				}
			} );

			frame.open();
		},
	},
} );

/**
 * Validate required fields in the current step.
 */
function validateStep( step ) {
	if ( ! step ) return true;

	const required = step.querySelectorAll( '[required]' );
	let valid = true;

	required.forEach( ( field ) => {
		if ( ! field.value.trim() ) {
			field.classList.add( 'is-invalid' );
			field.style.borderColor = 'var(--listora-error)';
			valid = false;

			field.addEventListener( 'input', () => {
				field.classList.remove( 'is-invalid' );
				field.style.borderColor = '';
			}, { once: true } );
		}
	} );

	if ( ! valid ) {
		const firstInvalid = step.querySelector( '.is-invalid' );
		if ( firstInvalid ) firstInvalid.focus();
	}

	return valid;
}

/**
 * Show/hide navigation buttons based on current step.
 */
function updateNavButtons( form, idx, total ) {
	const backBtn = form.querySelector( '.listora-submission__back' );
	const nextBtn = form.querySelector( '.listora-submission__next' );
	const submitBtn = form.querySelector( '.listora-submission__submit-btn' );
	const draftBtn = form.querySelector( '.listora-submission__save-draft' );

	if ( backBtn ) backBtn.hidden = ( idx === 0 );
	if ( nextBtn ) nextBtn.hidden = ( idx === total - 1 );
	if ( submitBtn ) submitBtn.hidden = ( idx !== total - 1 );
	if ( draftBtn ) draftBtn.hidden = ( idx === total - 1 );
}

/**
 * Build a preview from form data using safe DOM methods.
 */
function buildPreview( form ) {
	const preview = form.querySelector( '#listora-preview-content' );
	if ( ! preview ) return;

	const title = form.querySelector( '[name="title"]' )?.value || '';
	const desc = form.querySelector( '[name="description"]' )?.value || '';
	const category = form.querySelector( '[name="category"] option:checked' )?.textContent || '';

	// Clear previous preview safely.
	preview.textContent = '';

	const h3 = document.createElement( 'h3' );
	h3.style.cssText = 'margin:0 0 0.5rem';
	h3.textContent = title || 'Untitled';
	preview.appendChild( h3 );

	if ( category ) {
		const badge = document.createElement( 'span' );
		badge.className = 'listora-badge listora-badge--type';
		badge.textContent = category;
		preview.appendChild( badge );
	}

	const p = document.createElement( 'p' );
	p.style.cssText = 'margin:0.5rem 0;color:var(--listora-text-secondary)';
	p.textContent = desc.length > 200 ? desc.substring( 0, 200 ) + '...' : desc;
	preview.appendChild( p );
}

/**
 * Add a gallery thumbnail using safe DOM methods.
 */
function addGalleryThumb( attachment ) {
	const thumbs = document.querySelector( '#listora-gallery-thumbs' );
	if ( ! thumbs ) return;

	const url = attachment.sizes?.thumbnail?.url || attachment.url;
	const div = document.createElement( 'div' );
	div.style.cssText = 'width:80px;height:80px;border-radius:var(--listora-radius-md);overflow:hidden;position:relative;';

	const img = document.createElement( 'img' );
	img.src = url;
	img.alt = '';
	img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
	div.appendChild( img );

	thumbs.appendChild( div );
}

/**
 * Reverse-geocode coordinates via Nominatim and populate address fields.
 *
 * @param {number}      lat    Latitude.
 * @param {number}      lng    Longitude.
 * @param {HTMLElement} parent The .listora-submission__map-field container.
 */
function reverseGeocode( lat, lng, parent ) {
	if ( ! parent ) return;

	const url = `https://nominatim.openstreetmap.org/reverse?lat=${ lat }&lon=${ lng }&format=json&addressdetails=1`;

	fetch( url, { headers: { Accept: 'application/json' } } )
		.then( ( res ) => res.json() )
		.then( ( data ) => {
			if ( ! data || data.error ) return;

			const addr = data.address || {};
			const addressInput = parent.querySelector( '[name$="[address]"]' );
			if ( addressInput && data.display_name ) {
				addressInput.value = data.display_name;
			}

			const cityInput = parent.querySelector( '[name$="[city]"]' );
			if ( cityInput ) {
				cityInput.value = addr.city || addr.town || addr.village || addr.municipality || '';
			}

			const stateInput = parent.querySelector( '[name$="[state]"]' );
			if ( stateInput ) {
				stateInput.value = addr.state || '';
			}

			const countryInput = parent.querySelector( '[name$="[country]"]' );
			if ( countryInput ) {
				countryInput.value = addr.country || '';
			}

			const postalInput = parent.querySelector( '[name$="[postal_code]"]' );
			if ( postalInput ) {
				postalInput.value = addr.postcode || '';
			}
		} )
		.catch( () => {
			// Silently fail — user can still type the address manually.
		} );
}

/**
 * Forward-geocode an address string via Nominatim and move the marker.
 *
 * @param {string}       query  Address string.
 * @param {L.Map}        map    Leaflet map instance.
 * @param {L.Marker}     marker Leaflet marker instance.
 * @param {HTMLElement}  parent The .listora-submission__map-field container.
 */
function forwardGeocode( query, map, marker, parent ) {
	if ( ! query || query.length < 3 ) return;

	const url = `https://nominatim.openstreetmap.org/search?q=${ encodeURIComponent( query ) }&format=json&addressdetails=1&limit=1`;

	fetch( url, { headers: { Accept: 'application/json' } } )
		.then( ( res ) => res.json() )
		.then( ( results ) => {
			if ( ! results || ! results.length ) return;

			const result = results[ 0 ];
			const lat = parseFloat( result.lat );
			const lng = parseFloat( result.lon );
			const latlng = L.latLng( lat, lng );

			marker.setLatLng( latlng );
			map.setView( latlng, 15 );

			if ( parent ) {
				const latInput = parent.querySelector( '[name$="[lat]"]' );
				const lngInput = parent.querySelector( '[name$="[lng]"]' );
				if ( latInput ) latInput.value = lat.toFixed( 7 );
				if ( lngInput ) lngInput.value = lng.toFixed( 7 );

				const addr = result.address || {};
				const cityInput = parent.querySelector( '[name$="[city]"]' );
				if ( cityInput ) cityInput.value = addr.city || addr.town || addr.village || addr.municipality || '';

				const stateInput = parent.querySelector( '[name$="[state]"]' );
				if ( stateInput ) stateInput.value = addr.state || '';

				const countryInput = parent.querySelector( '[name$="[country]"]' );
				if ( countryInput ) countryInput.value = addr.country || '';

				const postalInput = parent.querySelector( '[name$="[postal_code]"]' );
				if ( postalInput ) postalInput.value = addr.postcode || '';
			}
		} )
		.catch( () => {
			// Silently fail — geocoding is best-effort.
		} );
}

/**
 * Update lat/lng hidden fields from marker position.
 *
 * @param {L.LatLng}     pos    Marker position.
 * @param {HTMLElement}  parent The .listora-submission__map-field container.
 */
function updateLatLngFields( pos, parent ) {
	if ( ! parent ) return;
	const latInput = parent.querySelector( '[name$="[lat]"]' );
	const lngInput = parent.querySelector( '[name$="[lng]"]' );
	if ( latInput ) latInput.value = pos.lat.toFixed( 7 );
	if ( lngInput ) lngInput.value = pos.lng.toFixed( 7 );
}

/**
 * Init Leaflet map pickers in the details step.
 *
 * Creates a draggable marker that syncs with address fields:
 * - Drag marker or click map: reverse-geocodes to fill address fields.
 * - Type address: forward-geocodes to move the marker.
 * - On init: uses existing lat/lng values, or attempts browser geolocation.
 */
function initMapPickers( step ) {
	if ( typeof L === 'undefined' ) return;

	step.querySelectorAll( '.listora-submission__map-picker' ).forEach( ( el ) => {
		if ( el._leafletMap ) return;

		const parent = el.closest( '.listora-submission__map-field' );

		// Check for pre-filled lat/lng (edit mode).
		const existingLat = parent ? parseFloat( parent.querySelector( '[name$="[lat]"]' )?.value ) : NaN;
		const existingLng = parent ? parseFloat( parent.querySelector( '[name$="[lng]"]' )?.value ) : NaN;

		const hasExisting = ! isNaN( existingLat ) && ! isNaN( existingLng ) && existingLat !== 0 && existingLng !== 0;
		const initialLat = hasExisting ? existingLat : 40.7128;
		const initialLng = hasExisting ? existingLng : -74.006;
		const initialZoom = hasExisting ? 15 : 12;

		const map = L.map( el ).setView( [ initialLat, initialLng ], initialZoom );
		L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap',
			maxZoom: 19,
		} ).addTo( map );

		const marker = L.marker( [ initialLat, initialLng ], { draggable: true } ).addTo( map );

		// On marker drag: update coords and reverse-geocode.
		marker.on( 'dragend', () => {
			const pos = marker.getLatLng();
			updateLatLngFields( pos, parent );
			reverseGeocode( pos.lat, pos.lng, parent );
		} );

		// On map click: move marker, update coords, reverse-geocode.
		map.on( 'click', ( e ) => {
			marker.setLatLng( e.latlng );
			updateLatLngFields( e.latlng, parent );
			reverseGeocode( e.latlng.lat, e.latlng.lng, parent );
		} );

		// On address field change: forward-geocode and move marker (debounced).
		if ( parent ) {
			const addressInput = parent.querySelector( '[name$="[address]"]' );
			if ( addressInput ) {
				let geocodeTimeout = null;
				addressInput.addEventListener( 'input', () => {
					if ( geocodeTimeout ) clearTimeout( geocodeTimeout );
					geocodeTimeout = setTimeout( () => {
						forwardGeocode( addressInput.value.trim(), map, marker, parent );
					}, 800 );
				} );
			}
		}

		// If no existing coords, try browser geolocation.
		if ( ! hasExisting && 'geolocation' in navigator ) {
			navigator.geolocation.getCurrentPosition(
				( position ) => {
					const lat = position.coords.latitude;
					const lng = position.coords.longitude;
					const latlng = L.latLng( lat, lng );

					marker.setLatLng( latlng );
					map.setView( latlng, 14 );
					updateLatLngFields( latlng, parent );
				},
				() => {
					// Geolocation denied or unavailable — keep defaults.
				},
				{ timeout: 5000 }
			);
		}

		el._leafletMap = map;
		setTimeout( () => map.invalidateSize(), 200 );
	} );
}
