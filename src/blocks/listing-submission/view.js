/**
 * Listing Submission — Interactivity API view module.
 *
 * Handles multi-step navigation, form validation, draft saving, and submission.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import '../../interactivity/store.js';
import {
	abortableApiFetch,
	abortableFetch,
	isAbortError,
	NETWORK_SLOW_MESSAGE,
} from '../../utils/abortable-fetch.js';

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
					indicators[ currentIdx ].removeAttribute( 'aria-current' );
				}
				if ( indicators[ currentIdx + 1 ] ) {
					indicators[ currentIdx + 1 ].classList.add( 'is-current' );
					indicators[ currentIdx + 1 ].setAttribute( 'aria-current', 'step' );
				}
				// Mark connecting line as completed.
				if ( lines[ currentIdx ] ) {
					lines[ currentIdx ].classList.add( 'is-completed' );
				}

				// Update progressbar's aria-valuenow so screen readers
				// announce step progression (the stepper.php server-side
				// markup ships aria-valuenow="1" — never updated until now).
				const progressbarNext = form.querySelector( '.listora-submission__progress' );
				if ( progressbarNext ) {
					progressbarNext.setAttribute( 'aria-valuenow', String( currentIdx + 2 ) );
				}

				updateNavButtons( form, currentIdx + 1, steps.length );

				// BC 9895249904: rebuild preview whenever the user lands on
				// the preview step, regardless of how many steps were
				// traversed in this transition. Previously gated on
				// `currentIdx + 1 === steps.length - 1` which is correct for
				// the single-step-forward case but missed paths where the
				// wizard collapses steps in edit-mode or jumps via the
				// Save Draft → resume flow.
				if ( steps[ currentIdx + 1 ].dataset.step === 'preview' ) {
					buildPreview( form );
					updateCreditBanner( form );
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
					indicators[ currentIdx ].removeAttribute( 'aria-current' );
				}
				if ( indicators[ currentIdx - 1 ] ) {
					indicators[ currentIdx - 1 ].classList.remove( 'is-completed' );
					indicators[ currentIdx - 1 ].classList.add( 'is-current' );
					indicators[ currentIdx - 1 ].setAttribute( 'aria-current', 'step' );
				}
				// Revert connecting line.
				if ( lines[ currentIdx - 1 ] ) {
					lines[ currentIdx - 1 ].classList.remove( 'is-completed' );
				}

				const progressbarPrev = form.querySelector( '.listora-submission__progress' );
				if ( progressbarPrev ) {
					progressbarPrev.setAttribute( 'aria-valuenow', String( currentIdx ) );
				}

				updateNavButtons( form, currentIdx - 1, steps.length );

				// BC 9895249904: if the user navigates BACK into the preview
				// step (e.g. went Submit → Cancel → Back), rebuild so any
				// edits they made on the way are reflected.
				if ( steps[ currentIdx - 1 ].dataset.step === 'preview' ) {
					buildPreview( form );
					updateCreditBanner( form );
				}

				form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		},

		/**
		 * Select listing type — populate category dropdown + reveal type-specific fields.
		 *
		 * Category options are empty on first render when there's no pre-set
		 * listingType (the "Pick your type" flow). Fetch the type's allowed
		 * categories via REST and hydrate the select. The user advances to
		 * Step 2 explicitly via the Continue button — selecting a Type does
		 * NOT auto-advance, matching standard wizard UX (deterministic step
		 * navigation, no surprise page transitions on a single click).
		 */
		selectSubmissionType() {
			const el = getElement();
			const container = el.ref.closest( '.listora-submission' );
			const radio = el.ref.querySelector( 'input[type="radio"]' ) || el.ref.closest( 'label' )?.querySelector( 'input[type="radio"]' );
			const slug = radio?.value || '';

			if ( ! slug || ! container ) return;

			// 1) Hydrate the category dropdown from REST for the picked
			//    type. BC 9895227195: previously this short-circuited when
			//    the dropdown already had options, so switching from
			//    Place → Hotel kept showing Place categories. Now we reset
			//    to the placeholder + a transient "Loading…" entry every
			//    time the user picks a type, then repopulate from REST.
			const categorySelect = container.querySelector( '[name="category"]' );
			if ( categorySelect ) {
				if ( categorySelect.dataset.listoraTypeLoaded === slug ) {
					// Same type re-selected (e.g. label re-click) — nothing to do.
				} else {
					// Reset to placeholder. Keep first <option value="">…</option>
					// which the template renders so users always see a hint.
					while ( categorySelect.options.length > 1 ) {
						categorySelect.remove( 1 );
					}
					categorySelect.value = '';
					categorySelect.dataset.listoraTypeLoaded = slug;

					abortableApiFetch( {
						path: `/listora/v1/listing-types/${ slug }/categories`,
					} )
						.then( ( categories ) => {
							// Race-guard: if user already picked a different
							// type while this fetch was in flight, drop the
							// stale response.
							if ( categorySelect.dataset.listoraTypeLoaded !== slug ) return;
							if ( ! Array.isArray( categories ) ) return;
							categories.forEach( ( cat ) => {
								const opt = document.createElement( 'option' );
								opt.value = cat.id;
								opt.textContent = cat.name;
								categorySelect.appendChild( opt );
							} );
						} )
						.catch( () => {
							// Silently fail — user can still pick a type
							// again to retry, and the placeholder remains.
							if ( categorySelect.dataset.listoraTypeLoaded === slug ) {
								delete categorySelect.dataset.listoraTypeLoaded;
							}
						} );
				}
			}

			// 2) Reveal this type's pre-rendered field group, hide the others,
			//    and disable the inputs inside hidden blocks so their empty
			//    values don't get POSTed for the wrong type.
			const wrap = container.querySelector( '.listora-submission__type-fields-wrap' );
			if ( wrap ) {
				const placeholder = wrap.querySelector( '[data-listora-type-placeholder]' );
				if ( placeholder ) placeholder.hidden = true;

				wrap.querySelectorAll( '.listora-submission__type-fields' ).forEach( ( block ) => {
					const isActive = block.dataset.typeSlug === slug;
					block.hidden = ! isActive;
					block.classList.toggle( 'is-active', isActive );
					// Inputs in hidden blocks shouldn't submit. Disabled inputs
					// are skipped by FormData.
					block.querySelectorAll( 'input, select, textarea' ).forEach( ( input ) => {
						input.disabled = ! isActive;
					} );
				} );
			}
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

			// Single-form layout submits directly without stepping through the
			// wizard, so the per-step validation that normally runs in
			// `nextSubmissionStep` never fired. Validate every step here and
			// bail (leaving the marked-invalid fields in place) on the first
			// step that fails, so single-form gets the same guarantees as the
			// wizard before the request is sent.
			if ( form.classList.contains( 'listora-submission--single-form' ) ) {
				const allSteps = form.querySelectorAll( '.listora-submission__step' );
				for ( const step of allSteps ) {
					if ( ! validateStep( step ) ) {
						return;
					}
				}
			}

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
				// Get reCAPTCHA v3 token if applicable.
				await getRecaptchaToken( formEl );

				// Clear values of hidden conditional fields before submission.
				clearHiddenConditionalFields( formEl );

				const formData = new FormData( formEl );

				// Always use POST — the server detects listing_id in the body to route to update.
				// Use a longer timeout (60s) for submissions because file uploads can be slow.
				const response = await abortableApiFetch( {
					path: '/listora/v1/submit',
					method: 'POST',
					body: formData,
				}, 60000 );

				formEl.hidden = true;
				const progress = form.querySelector( '.listora-submission__progress' );
				if ( progress ) progress.remove();
				const nav = form.querySelector( '.listora-submission__nav' );
				if ( nav ) nav.remove();
				// Also hide the guest registration section.
				const guestReg = form.querySelector( '.listora-submission__guest-register' );
				if ( guestReg ) guestReg.hidden = true;
				// Also hide the duplicate review step if it was shown previously.
				const dupStep = form.querySelector( '.listora-submission__duplicate-review' );
				if ( dupStep ) dupStep.hidden = true;

				// Verification-required path — show the "Check your email" card
				// instead of the regular success message.
				if ( response && response.verification_required ) {
					showVerifyEmailCard( form, response );
				} else if ( successDiv ) {
					successDiv.hidden = false;
				}
			} catch ( error ) {
				// Detect duplicate-detected response (HTTP 409 with code "listora_duplicate_detected").
				const isDuplicate =
					error?.code === 'listora_duplicate_detected' ||
					error?.data?.status === 409 ||
					( Array.isArray( error?.duplicates ) && error.duplicates.length > 0 );

				if ( isDuplicate ) {
					const duplicates = Array.isArray( error.duplicates )
						? error.duplicates
						: ( error?.data?.duplicates || [] );

					showDuplicateReviewStep( form, duplicates );

					if ( submitBtn ) {
						submitBtn.disabled = false;
						submitBtn.textContent = originalBtnText || ( isEditMode ? 'Update Listing' : 'Submit Listing' );
					}
					return;
				}

				if ( errorDiv ) {
					errorDiv.hidden = false;
					const p = errorDiv.querySelector( 'p' );
					const msg = isAbortError( error )
						? NETWORK_SLOW_MESSAGE
						: ( error.message || 'Submission failed. Please try again.' );
					if ( p ) p.textContent = msg;
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

				await abortableApiFetch( {
					path: '/listora/v1/submit',
					method: 'POST',
					body: formData,
				}, 60000 );

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

					await abortableApiFetch( {
						path: '/listora/v1/submit',
						method: 'POST',
						body: formData,
					}, 60000 );

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
		 * Evaluate conditional field visibility when a field value changes.
		 *
		 * Listens on inputs/selects with data-wp-on--change or data-wp-on--input.
		 * Finds all conditional fields in the form and shows/hides them based
		 * on their condition config.
		 */
		evaluateConditionalFields() {
			const el = getElement();
			const form = el.ref.closest( '.listora-submission__form' );
			if ( ! form ) return;

			evaluateConditionals( form );
		},

		/**
		 * Validate a field on blur.
		 *
		 * Radios and checkboxes have their state in `.checked` rather than
		 * `.value` — use group lookup so a user-selected Type passes
		 * validation and an unselected one is flagged.
		 */
		validateField() {
			const el = getElement();
			const field = el.ref.closest( '.listora-submission__field' );
			const input = el.ref;

			if ( ! field || ! input ) return;

			let hasValue;
			if ( input.type === 'radio' ) {
				const form = input.closest( 'form' );
				const checked = form
					? form.querySelector( 'input[type="radio"][name="' + CSS.escape( input.name ) + '"]:checked' )
					: ( input.checked ? input : null );
				hasValue = !! checked;
			} else if ( input.type === 'checkbox' ) {
				hasValue = !! input.checked;
			} else {
				hasValue = input.value.trim() !== '';
			}

			if ( input.required && ! hasValue ) {
				field.classList.remove( 'listora-submission__field--valid' );
				field.classList.add( 'listora-submission__field--error' );
			} else if ( hasValue ) {
				field.classList.remove( 'listora-submission__field--error' );
				field.classList.add( 'listora-submission__field--valid' );
			} else {
				field.classList.remove( 'listora-submission__field--valid', 'listora-submission__field--error' );
			}
		},

		/**
		 * Open WP media library for image uploads.
		 *
		 * Marks the click event so the delegated DOM fallback (defined at the
		 * bottom of this file) skips re-handling on visible elements where
		 * Interactivity API hydration succeeded.
		 */
		openMediaUpload( event ) {
			if ( event ) {
				event.__listoraMediaHandled = true;
			}
			const ctx = getContext();
			openMediaForTarget( ctx && ctx.uploadTarget ? ctx.uploadTarget : '' );
		},
	},
} );

/**
 * Surface a validation/error message inline next to the upload trigger.
 *
 * Native alert() is a wppqa Rule 10 release blocker. This helper inserts a
 * `role="alert"` div directly after the upload trigger so screen readers
 * announce the message without dismissing the form. Falls back to a global
 * region pinned to the top of the submission form when the trigger cannot
 * be located.
 *
 * @param {string} target Upload target (e.g. `featured_image`, `gallery`).
 * @param {string} msg    Human-readable message.
 */
function showUploaderInlineError( target, msg ) {
	let trigger = null;
	if ( target ) {
		const candidates = document.querySelectorAll(
			'[data-wp-on--click="actions.openMediaUpload"]'
		);
		for ( const el of candidates ) {
			const raw = el.getAttribute( 'data-wp-context' );
			if ( raw ) {
				try {
					const parsed = JSON.parse( raw );
					if ( parsed && parsed.uploadTarget === target ) {
						trigger = el;
						break;
					}
				} catch ( _err ) {
					// Malformed JSON — keep searching.
				}
			}
		}
	}

	const anchor = trigger
		? trigger.closest( '.listora-submission__upload, .listora-form__row, fieldset' ) || trigger.parentElement
		: document.querySelector( '.listora-submission' ) || document.body;
	if ( ! anchor ) return;

	let alertEl = anchor.querySelector( ':scope > .listora-form__error[data-listora-upload-error]' );
	if ( ! alertEl ) {
		alertEl = document.createElement( 'div' );
		alertEl.className = 'listora-form__error';
		alertEl.setAttribute( 'role', 'alert' );
		alertEl.setAttribute( 'data-listora-upload-error', target || '1' );
		if ( trigger && trigger.nextSibling ) {
			anchor.insertBefore( alertEl, trigger.nextSibling );
		} else {
			anchor.appendChild( alertEl );
		}
	}
	alertEl.textContent = msg;
	alertEl.hidden = false;
}

/**
 * Shared media-upload handler.
 *
 * Used by both the Interactivity API `openMediaUpload` action and the
 * delegated DOM-level fallback listener. This is the single source of
 * truth for opening the WP media frame and wiring its `select` callback.
 */
function openMediaForTarget( target ) {
	if ( ! target ) return;

	if ( typeof wp === 'undefined' || ! wp.media ) {
		// Surface the failure instead of returning silently — a dead
		// "Click to upload" with no feedback is the exact symptom QA
		// re-flagged. The render now always enqueues wp.media on the
		// submission page, so this should only fire when the script
		// loaded before wp-mediaelement / wp.media (a load-order bug
		// worth knowing about).
		const msg =
			( window.listoraI18n && window.listoraI18n.mediaUnavailable ) ||
			'The media uploader could not load. Please refresh the page and try again.';
		if ( window.listoraToast ) {
			window.listoraToast( msg, 'error' );
		} else {
			showUploaderInlineError( target, msg );
		}
		return;
	}

	const isGallery = target === 'gallery';

	// Scope the picker to the current member's OWN uploads unless they're
	// privileged (editors/admins). Without this a non-admin member could
	// browse other members' and the admin's Media Library from the frontend
	// submission form (card 9996105562). The server-side
	// ajax_query_attachments_args filter enforces the same rule and is
	// authoritative; this just opens the modal already scoped.
	const mediaLibrary = { type: 'image' };
	if (
		window.listoraI18n &&
		window.listoraI18n.mediaRestrictToOwn &&
		Number( window.listoraI18n.mediaAuthorId ) > 0
	) {
		mediaLibrary.author = Number( window.listoraI18n.mediaAuthorId );
	}

	const frame = wp.media( {
		title: isGallery ? 'Select Gallery Images' : 'Select Image',
		multiple: isGallery,
		library: mediaLibrary,
	} );

	frame.on( 'select', function () {
		const selection = frame.state().get( 'selection' );

		// Per-site cap from Settings → Submissions → "Max file size".
		// PHP's upload_max_filesize is the hard ceiling (the upload would
		// have already been rejected by WP if it exceeded that), but the
		// site owner can set a tighter Listora cap to keep listing images
		// reasonable. We reject any selection whose attachment has a
		// filesize above the cap, so the user gets feedback before the
		// form gets submitted.
		const maxUploadMb = ( window.listoraI18n && Number( window.listoraI18n.maxUploadSizeMb ) ) || 5;
		const maxBytes = maxUploadMb * 1024 * 1024;
		const tooLargeTpl = ( window.listoraI18n && window.listoraI18n.fileTooLarge ) || 'File exceeds %d MB.';
		const oversized = [];
		selection.each( ( attachment ) => {
			const a = attachment.toJSON();
			const size = Number( a.filesizeInBytes || a.fileLength || 0 );
			if ( size > 0 && size > maxBytes ) {
				oversized.push( a.filename || `#${ a.id }` );
			}
		} );
		if ( oversized.length ) {
			const msg = tooLargeTpl.replace( '%d', String( maxUploadMb ) );
			const fullMsg = `${ msg } (${ oversized.join( ', ' ) })`;
			if ( window.listoraToast ) {
				window.listoraToast( fullMsg, 'error' );
			} else {
				showUploaderInlineError( target, fullMsg );
			}
			return;
		}

		if ( isGallery ) {
			// Gallery-cap enforcement (BC 9901104724). The template rendered
			// the "(up to N photos)" label but the media frame still let
			// users pick beyond N. Enforce here in the select handler so the
			// limit is honored before any thumb DOM exists.
			const input = document.querySelector( 'input[name="gallery"]' );
			const existing = ( input?.value || '' )
				.split( ',' )
				.map( ( s ) => s.trim() )
				.filter( Boolean );
			const maxGallery = ( window.listoraI18n && Number( window.listoraI18n.maxGalleryImages ) ) || 20;
			const remaining = Math.max( 0, maxGallery - existing.length );
			const picked = selection.length;

			if ( remaining === 0 ) {
				const msg = (
					( window.listoraI18n && window.listoraI18n.galleryLimitReached ) ||
					'You can upload a maximum of %d gallery images.'
				).replace( '%d', String( maxGallery ) );
				if ( window.listoraToast ) {
					window.listoraToast( msg, 'error' );
				} else {
					showUploaderInlineError( target, msg );
				}
				return;
			}

			if ( picked > remaining ) {
				const tpl =
					( window.listoraI18n && window.listoraI18n.galleryLimitWouldExceed ) ||
					'You can add %1$d more image(s). You selected %2$d.';
				const msg = tpl
					.replace( '%1$d', String( remaining ) )
					.replace( '%2$d', String( picked ) );
				if ( window.listoraToast ) {
					window.listoraToast( msg, 'error' );
				} else {
					showUploaderInlineError( target, msg );
				}
				return;
			}

			const ids = [];
			selection.each( ( attachment ) => {
				ids.push( attachment.id );
				addGalleryThumb( attachment.toJSON() );
			} );
			if ( input ) {
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
				img.classList.add( 'listora-submission__media-preview' );
				zone.appendChild( img );
			}
		}
	} );

	frame.open();
}

/**
 * Attach flatpickr time picker to every business_hours input.
 *
 * The submission form renders `<input type="time">` for each weekday
 * open/close slot — Chrome and Edge show a clock-chrome dropdown for
 * those, Firefox renders a numeric spinner only. flatpickr provides a
 * consistent click-to-pick UI across all browsers (card 9856828615
 * round 2). The native input is replaced visually by flatpickr's text
 * input but keeps its `name` attribute, so form submission posts the
 * same `HH:MM` value the server has always parsed.
 *
 * Idempotent: flatpickr stores its instance on the element and the
 * `data-listora-flatpickr-attached` flag prevents double-initialisation
 * if a re-render fires this twice.
 */
function initBusinessHoursPickers( root ) {
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
		} );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => initBusinessHoursPickers() );
} else {
	initBusinessHoursPickers();
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

initBusinessHoursToggles();

/**
 * Delegated click fallback for media upload triggers.
 *
 * The Interactivity API does not always bind `data-wp-on--click` handlers
 * inside subtrees that start with the `hidden` attribute (type-specific
 * field blocks, the media step, etc.). Once the user navigates to those
 * sections, clicks reach the document but find no IAPI listener and the
 * upload never opens. This delegated listener guarantees the upload
 * works regardless of hydration state. When the IAPI handler does fire,
 * it marks the event so this listener no-ops to avoid opening twice.
 */
document.addEventListener( 'click', ( event ) => {
	const trigger = event.target && event.target.closest
		? event.target.closest( '[data-wp-on--click="actions.openMediaUpload"]' )
		: null;
	if ( ! trigger ) return;
	if ( event.__listoraMediaHandled ) return;
	event.__listoraMediaHandled = true;

	let target = '';
	const raw = trigger.getAttribute( 'data-wp-context' );
	if ( raw ) {
		try {
			const parsed = JSON.parse( raw );
			if ( parsed && parsed.uploadTarget ) {
				target = parsed.uploadTarget;
			}
		} catch ( _err ) {
			// Malformed JSON — silently fall through.
		}
	}
	if ( target ) {
		openMediaForTarget( target );
	}
} );

/**
 * Validate required fields in the current step.
 *
 * Skips fields hidden by conditional rules (the renderer keeps `required`
 * on every field, so the DOM-level attribute alone is not authoritative)
 * and inactive type-specific blocks. Radios are checked via :checked
 * group lookup since `field.value` is the radio's value attribute, not
 * its checked state.
 */
function validateStep( step ) {
	if ( ! step ) return true;

	const radioGroupsSeen = new Set();
	let valid = true;

	const isFieldActive = ( field ) => {
		if ( field.disabled ) return false;
		if ( field.closest( '.listora-submission__field--conditional-hidden' ) ) {
			return false;
		}
		// Inactive dynamic-type blocks carry the `hidden` attribute.
		const typeBlock = field.closest( '.listora-submission__type-fields' );
		if ( typeBlock && typeBlock.hasAttribute( 'hidden' ) ) {
			return false;
		}
		// Generic `display: none` from custom JS or CSS rules. `offsetParent`
		// is null for genuinely hidden fields BUT ALSO for
		// `position: fixed` / `position: sticky` fields and inside elements
		// the browser still paints — so don't treat a null offsetParent as
		// hidden on its own. Confirm with both the computed display AND
		// getClientRects(): an element the browser lays out always reports at
		// least one client rect, so a field visible via either signal stays
		// active and isn't wrongly skipped (card 9927816084 hardening).
		if ( field.offsetParent === null ) {
			const hiddenByDisplay = getComputedStyle( field ).display === 'none';
			const hasNoBox = field.getClientRects().length === 0;
			if ( hiddenByDisplay && hasNoBox ) {
				return false;
			}
		}
		return true;
	};

	const markInvalid = ( field ) => {
		valid = false;

		// Radios are visually 16px dots in the theme — applying .is-invalid
		// to the input itself produces no visible change. Mark the visible
		// type-card wrapper instead, and reveal a sibling error message
		// beneath the grid so the user has a clear cue.
		if ( field.type === 'radio' ) {
			const card = field.closest( '.listora-submission__type-card' );
			if ( card ) {
				card.classList.add( 'is-invalid' );
			}
			const grid = field.closest( '.listora-submission__type-grid' );
			if ( grid ) {
				let msg = grid.parentElement
					? grid.parentElement.querySelector( '.listora-submission__field-error' )
					: null;
				if ( ! msg ) {
					msg = document.createElement( 'p' );
					msg.className = 'listora-submission__field-error';
					msg.setAttribute( 'role', 'alert' );
					grid.parentElement?.insertBefore( msg, grid.nextSibling );
				}
				msg.textContent =
					( window.listoraI18n && window.listoraI18n.selectTypeError ) ||
					'Please select a listing type to continue.';
				msg.hidden = false;
			}

			const groupName = field.name;
			const onChange = () => {
				document
					.querySelectorAll(
						`input[type="radio"][name="${ CSS.escape( groupName ) }"]`
					)
					.forEach( ( r ) => {
						const c = r.closest( '.listora-submission__type-card' );
						if ( c ) c.classList.remove( 'is-invalid' );
					} );
				const grid2 = field.closest( '.listora-submission__type-grid' );
				const msg2 = grid2 && grid2.parentElement
					? grid2.parentElement.querySelector( '.listora-submission__field-error' )
					: null;
				if ( msg2 ) msg2.hidden = true;
			};
			field.addEventListener( 'change', onChange, { once: true } );
			return;
		}

		field.classList.add( 'is-invalid' );
		const cleanup = () => {
			field.classList.remove( 'is-invalid' );
		};
		field.addEventListener( 'input', cleanup, { once: true } );
		field.addEventListener( 'change', cleanup, { once: true } );
	};

	step.querySelectorAll( '[required]' ).forEach( ( field ) => {
		if ( ! isFieldActive( field ) ) return;

		if ( field.type === 'radio' ) {
			if ( radioGroupsSeen.has( field.name ) ) return;
			radioGroupsSeen.add( field.name );
			const checked = step.querySelector(
				'input[type="radio"][name="' + CSS.escape( field.name ) + '"]:checked'
			);
			if ( ! checked ) {
				markInvalid( field );
			}
			return;
		}

		if ( field.type === 'checkbox' ) {
			if ( ! field.checked ) markInvalid( field );
			return;
		}

		if ( ! field.value.trim() ) markInvalid( field );
	} );

	// Custom-required: hidden inputs that represent a media-upload field
	// (`featured_image`, etc.) cannot use the `required` attribute because
	// they are intentionally `type="hidden"` and isFieldActive() rightly
	// skips DOM-hidden fields. They opt in to validation via
	// `data-listora-required="<context>"`, and the error message is shown
	// in a sibling `.listora-submission__field-error--<context>` element.
	step.querySelectorAll( '[data-listora-required]' ).forEach( ( field ) => {
		// `data-listora-required` mirrors the field's form name (e.g. `featured_image`)
		// so it stays meaningful in the HTML. The BEM error class uses kebab-case
		// (e.g. `…field-error--featured-image`), so map underscores to hyphens
		// when building the selector.
		const ctx      = field.dataset.listoraRequired || 'field';
		const ctxClass = ctx.replace( /_/g, '-' );
		const filled   = field.type === 'checkbox' ? field.checked : !! ( field.value || '' ).trim();
		if ( filled ) return;

		valid = false;
		const errorEl = step.querySelector(
			'.listora-submission__field-error--' + ctxClass
		);
		if ( errorEl ) {
			errorEl.hidden = false;
			// Field-aware prompt first ("Add a featured photo to continue."),
			// generic required copy only as the last resort.
			const ctxMessages =
				( window.listoraI18n && window.listoraI18n.requiredFieldMessages ) || {};
			errorEl.textContent =
				ctxMessages[ ctx ] ||
				( window.listoraI18n && window.listoraI18n.requiredFieldError ) ||
				'This field is required.';
		}
		const wrapper = field.closest( '.listora-submission__field' );
		if ( wrapper ) {
			wrapper.classList.add( 'is-invalid' );
			const cleanup = () => {
				wrapper.classList.remove( 'is-invalid' );
				if ( errorEl ) errorEl.hidden = true;
			};
			field.addEventListener( 'change', cleanup, { once: true } );
		}
	} );

	if ( ! valid ) {
		const firstInvalid = step.querySelector( '.is-invalid' );
		if ( firstInvalid ) {
			// Hidden inputs can't focus — focus their visible upload trigger instead.
			const focusTarget = firstInvalid.matches( 'input, select, textarea' )
				? firstInvalid
				: firstInvalid.querySelector( '[data-wp-on--click], button, [tabindex]' ) || firstInvalid;
			if ( focusTarget && typeof focusTarget.focus === 'function' ) {
				focusTarget.focus();
			}
		}
	}

	return valid;
}

/**
 * Show/hide navigation buttons based on current step.
 *
 * Uses a CSS class (`.is-hidden`) instead of the HTML `hidden` attribute
 * because WooCommerce/theme front-end stylesheets ship a high-specificity
 * `button[type="submit"] { display: inline-block }` rule that overrides
 * the default `[hidden]` behaviour on the Submit button.
 */
function updateNavButtons( form, idx, total ) {
	const backBtn = form.querySelector( '.listora-submission__back' );
	const nextBtn = form.querySelector( '.listora-submission__next' );
	const submitBtn = form.querySelector( '.listora-submission__submit-btn' );
	const draftBtn = form.querySelector( '.listora-submission__save-draft' );

	const setHidden = ( el, hidden ) => {
		if ( ! el ) return;
		el.classList.toggle( 'is-hidden', hidden );
		// Keep the attribute in sync for accessibility tools that ignore
		// the class but honour the attribute.
		if ( hidden ) {
			el.setAttribute( 'hidden', 'hidden' );
		} else {
			el.removeAttribute( 'hidden' );
		}
	};

	// Single-form layout renders every step stacked and visible at once, so
	// the wizard's Back/Continue step navigation is meaningless. Hide both and
	// reveal Submit directly — otherwise Submit (shipped `hidden` in markup and
	// normally revealed only by stepping onto the last step) never appears and
	// the user has no way to submit. `handleSubmission` validates every step
	// for this layout. Centralised here so the load-time init and the
	// duplicate-review cancel path both produce the correct state.
	if ( form.classList.contains( 'listora-submission--single-form' ) ) {
		setHidden( backBtn, true );
		setHidden( nextBtn, true );
		setHidden( submitBtn, false );
		setHidden( draftBtn, false );
		return;
	}

	setHidden( backBtn, idx === 0 );
	setHidden( nextBtn, idx === total - 1 );
	setHidden( submitBtn, idx !== total - 1 );
	setHidden( draftBtn, idx === total - 1 );
}

/**
 * Pluralize a translatable word client-side.
 *
 * Keeps the banner readable when the amount flips between 1 and N; mirrors
 * the server-side _n() call. English-only fallback — translations are applied
 * to the surrounding PHP-rendered labels, not these inline nouns.
 */
function pluralizeCredits( count ) {
	return count === 1 ? 'credit' : 'credits';
}

/**
 * Format an integer using the user's locale when available.
 */
function formatCreditNumber( n ) {
	try {
		return new Intl.NumberFormat().format( n );
	} catch ( e ) {
		return String( n );
	}
}

/**
 * Refresh the credit-cost banner on the preview step.
 *
 * Reads cost from the selected plan radio (if present), otherwise falls back
 * to the default cost stored on the banner element. Balance is always the
 * server-rendered value on the banner — it does not change client-side.
 */
function updateCreditBanner( form ) {
	const banner = form.querySelector( '[data-listora-credit-banner]' );
	if ( ! banner ) return;

	const defaultCost = parseInt( banner.dataset.defaultCost || '0', 10 );
	const balance = parseInt( banner.dataset.balance || '0', 10 );
	const purchaseUrl = banner.dataset.purchaseUrl || '';

	// If a plan is selected, its cost wins over the default.
	const selectedPlan = form.querySelector( 'input[name="plan_id"]:checked' );
	let cost = defaultCost;

	if ( selectedPlan ) {
		// Support both `data-credits` (preferred) and legacy `data-credit-cost`.
		const planCost = parseInt(
			selectedPlan.dataset.credits || selectedPlan.dataset.creditCost || '',
			10
		);
		if ( ! Number.isNaN( planCost ) ) {
			cost = planCost;
		}
	}

	// No cost and no default → hide the banner entirely. Submission is free.
	if ( cost <= 0 ) {
		banner.hidden = true;
		return;
	}
	banner.hidden = false;

	const insufficient = balance < cost;
	banner.classList.toggle( 'listora-submission__credit-banner--insufficient', insufficient );

	const costEl = banner.querySelector( '[data-listora-credit-cost]' );
	if ( costEl ) {
		costEl.textContent = formatCreditNumber( cost );
		const parent = costEl.parentElement;
		if ( parent ) {
			// Replace the trailing noun (everything after the cost span).
			const trailing = ' ' + pluralizeCredits( cost );
			// Walk text nodes after the span and replace them in one shot.
			let node = costEl.nextSibling;
			while ( node ) {
				const next = node.nextSibling;
				if ( node.nodeType === 3 /* TEXT_NODE */ ) {
					parent.removeChild( node );
				}
				node = next;
			}
			parent.appendChild( document.createTextNode( trailing ) );
		}
	}

	const remaining = Math.max( 0, balance - cost );
	const remainingLine = banner.querySelector( '[data-listora-credit-remaining-line]' );
	const remainingEl = banner.querySelector( '[data-listora-credit-remaining]' );
	if ( remainingEl ) {
		remainingEl.textContent = formatCreditNumber( remaining );
		const parent = remainingEl.parentElement;
		if ( parent ) {
			let node = remainingEl.nextSibling;
			while ( node ) {
				const next = node.nextSibling;
				if ( node.nodeType === 3 ) parent.removeChild( node );
				node = next;
			}
			parent.appendChild( document.createTextNode( ' ' + pluralizeCredits( remaining ) ) );
		}
	}
	if ( remainingLine ) {
		remainingLine.hidden = insufficient;
	}

	const buyBtn = banner.querySelector( '[data-listora-credit-buy]' );
	if ( buyBtn ) {
		if ( purchaseUrl ) {
			buyBtn.hidden = ! insufficient;
		} else {
			buyBtn.hidden = true;
		}
	}
}

/**
 * Build a preview from form data using safe DOM methods.
 *
 * Walks every visible, filled field in the form (including type-specific
 * meta_ fields) and renders a label → value list. Title, category, and
 * description get top placement; everything else appears as a labeled
 * row in document order so the preview reflects the user's actual input.
 */
function buildPreview( form ) {
	const preview = form.querySelector( '#listora-preview-content' );
	if ( ! preview ) return;

	const formEl = form.querySelector( '.listora-submission__form' ) || form;

	// Preserve the server-rendered placeholder so we can restore it if the
	// build genuinely produces nothing — clearing the container up-front (as
	// before) left the Preview step blank whenever every section bailed
	// (card 9927816084). Snapshot it, then clear; we re-insert it at the end
	// only when no real content was appended.
	const placeholder = preview.querySelector( '.listora-submission__field-placeholder' );
	const placeholderClone = placeholder ? placeholder.cloneNode( true ) : null;

	preview.textContent = '';

	// Resilient sub-call wrapper. Each preview section is built by a
	// helper; one helper throwing should NOT bail the entire preview.
	// BC-OPEN-4 (card 9895249904) reported "Business Hours preview bails
	// silently" — without DevTools repro evidence we don't know which
	// sub-call throws on the reporter's site. This wrapper guarantees
	// the rest of the preview still renders + surfaces the exception
	// in the console so future sessions can pin the bail point.
	const safely = ( label, fn ) => {
		try { fn(); }
		catch ( e ) {
			// eslint-disable-next-line no-console -- diagnostic surface for BC-OPEN-4
			console.error( '[wb-listora] preview section "' + label + '" failed:', e );
		}
	};

	// Header: title + category badge.
	safely( 'header', () => {
		const title = formEl.querySelector( '[name="title"]' )?.value?.trim() || '';
		const h2 = document.createElement( 'h2' );
		h2.classList.add( 'listora-submission__preview-title' );
		h2.textContent = title || 'Untitled';
		preview.appendChild( h2 );

		const categoryEl = formEl.querySelector( '[name="category"] option:checked' );
		const category = categoryEl ? categoryEl.textContent.trim() : '';
		if ( category ) {
			const badge = document.createElement( 'span' );
			badge.className = 'listora-badge listora-badge--type';
			badge.textContent = category;
			preview.appendChild( badge );
		}
	} );

	// Description (full, but truncated for the preview blurb).
	safely( 'description', () => {
		const desc = formEl.querySelector( '[name="description"]' )?.value?.trim() || '';
		if ( desc ) {
			const p = document.createElement( 'p' );
			p.classList.add( 'listora-submission__preview-desc' );
			p.textContent = desc.length > 200 ? desc.substring( 0, 200 ) + '…' : desc;
			preview.appendChild( p );
		}
	} );

	// Media — featured image and gallery. The upload zone on the Media step
	// already renders a preview <img> for the featured image AND appends each
	// gallery thumbnail to #listora-gallery-thumbs, but those live in a
	// different DOM section that the user can't see from the Preview step.
	// Card 9842552596 round 5: read the hidden inputs (featured_image, gallery)
	// and mirror the existing thumbnails into the Preview card.
	safely( 'media', () => appendMediaPreview( formEl, preview ) );

	// All other visible fields, rendered as a key/value list.
	const list = document.createElement( 'dl' );
	list.classList.add( 'listora-submission__preview-list' );

	const skipNames = new Set( [
		'title', 'description', 'category', 'listing_id',
		'listora_hp_field', 'listora_nonce', 'gallery',
		// featured_image is rendered via appendMediaPreview() above; skip
		// it in the generic field loop so it doesn't ALSO show as a "Featured
		// Image: 1234" attachment-ID row (card 9842552596 round 5).
		'featured_image',
		// BC smoke 2026-05-25: listing_type + plan_id are picked on dedicated
		// wizard steps (Type chooser + Plan step) which render their own
		// human-readable summary cards. Their hidden inputs have no <label>
		// element, so resolvePreviewLabel() fell back to the raw field name
		// and surfaced "listing_type: business" / "plan_id: 781" as a row.
		'listing_type', 'plan_id',
		// _wp_http_referer + listora_dup_* round out the housekeeping fields
		// the wizard ships but that have no customer-meaningful value.
		'_wp_http_referer', 'listora_dup_confirm', 'listora_dup_explanation',
	] );

	const seenLabels = new Set();

	formEl.querySelectorAll( 'input[name], select[name], textarea[name]' ).forEach( ( field ) => {
		try {
		const name = field.name;
		if ( ! name || skipNames.has( name ) ) return;
		if ( field.type === 'hidden' ) return;
		if ( field.type === 'file' ) return;
		if ( name.startsWith( 'notification_prefs' ) ) return;
		// Composite fields rendered separately below — skip in the generic
		// loop so each day's open/close/closed inputs don't show as
		// individually-rendered rows (Basecamp 9842552596 round 3).
		// The actual field name is `meta_business_hours[...]` because
		// submission-field-renderer.php prefixes the field key with
		// `meta_`. The prior fix only matched the bare `business_hours[`
		// prefix and so never skipped them — the generic loop rendered
		// each `[closed]` checkbox as its own "Closed: ✓" row, which is
		// the only thing QA could see in the preview.
		// Also skip composite-field name patterns from social_links / map_location
		// so they don't leak as individual rows either.
		if ( name.startsWith( 'business_hours[' ) || name.startsWith( 'meta_business_hours[' ) ) return;
		// Skip fields hidden by conditional rules or inside inactive type-blocks.
		if ( field.closest( '.listora-submission__field--conditional-hidden' ) ) return;
		const typeBlock = field.closest( '.listora-submission__type-fields' );
		if ( typeBlock && typeBlock.hasAttribute( 'hidden' ) ) return;

		const label = resolvePreviewLabel( field );
		const value = resolvePreviewValue( field );
		if ( ! label || value === '' ) return;
		// Coalesce repeated labels (radio groups, multi-checkbox arrays share a label).
		const dedupeKey = label + '|' + value;
		if ( seenLabels.has( dedupeKey ) ) return;
		seenLabels.add( dedupeKey );

		const dt = document.createElement( 'dt' );
		dt.textContent = label;
		const dd = document.createElement( 'dd' );
		dd.textContent = value;
		list.appendChild( dt );
		list.appendChild( dd );
		} catch ( e ) {
			// eslint-disable-next-line no-console -- diagnostic surface for BC-OPEN-4
			console.error( '[wb-listora] preview field row failed for', field?.name, e );
		}
	} );

	// Business Hours composite — render as a single Mon→Sun schedule row.
	// Wrapped in the safely() resilience harness so a malformed hours
	// input (BC-OPEN-4) leaves the rest of the preview intact instead
	// of bailing the whole panel.
	safely( 'business_hours', () => appendBusinessHoursPreview( formEl, list ) );

	if ( list.children.length > 0 ) {
		preview.appendChild( list );
	}

	// If every section bailed (threw, or found nothing to render) the
	// container would otherwise be left empty and the Preview step appears
	// blank. Restore the placeholder so the user always sees something
	// rather than a void (card 9927816084 hardening).
	if ( ! preview.hasChildNodes() ) {
		if ( placeholderClone ) {
			preview.appendChild( placeholderClone );
		} else {
			const p = document.createElement( 'p' );
			p.className = 'listora-submission__field-placeholder';
			p.textContent =
				( window.listoraI18n && window.listoraI18n.previewPlaceholder ) ||
				'Preview will appear here after filling in the form.';
			preview.appendChild( p );
		}
	}
}

/**
 * Aggregate business_hours[day][open|close|closed|is_24h] inputs into a
 * single readable schedule and append to the preview list. Mirrors the
 * frontend listing detail's wb_listora_render_hours() PHP helper so the
 * preview matches what the user will see published.
 *
 * @param {HTMLElement} formEl Form root.
 * @param {HTMLDListElement} list Preview <dl> being built.
 */
function appendBusinessHoursPreview( formEl, list ) {
	// Match both legacy `business_hours[...]` and the actual rendered
	// name `meta_business_hours[...]` — submission-field-renderer
	// prefixes meta-field keys with `meta_`, so the bare prefix never
	// hit anything and the helper silently no-op'd (round-3 of QA card
	// 9842552596).
	const inputs = formEl.querySelectorAll(
		'[name^="business_hours["], [name^="meta_business_hours["]'
	);
	if ( ! inputs.length ) return;

	// Flatpickr wraps each time input (initBusinessHoursPickers) and keeps
	// the chosen time on its own instance/altInput until the picker closes
	// or the field blurs. When the user advances to Preview while a picker
	// still has an unflushed pick, `input.value` is empty and the section
	// gets dropped (card 9928220940). Force-flush each picker's selected
	// value back to its underlying input before we read, and fall back to
	// the visible flatpickr text input value if the instance hasn't synced.
	const readHoursValue = ( input ) => {
		const fp = input._flatpickr;
		if ( fp ) {
			// setDate( currentValue, false ) re-serializes selectedDates into
			// the underlying input via the configured dateFormat without
			// firing onChange — this is flatpickr's own flush path.
			if ( Array.isArray( fp.selectedDates ) && fp.selectedDates.length ) {
				try {
					fp.setDate( fp.selectedDates, false );
				} catch ( _e ) {
					// Defensive — never let a flush error bail the preview.
				}
			}
			if ( input.value && input.value.trim() ) {
				return input.value.trim();
			}
			// Last resort: read the visible alternate/text input flatpickr renders.
			const visible = fp.altInput || fp.input;
			if ( visible && visible.value && visible.value.trim() ) {
				return visible.value.trim();
			}
		}
		return ( input.value || '' ).trim();
	};

	const byDay = {};
	inputs.forEach( ( input ) => {
		// Every listing type renders its OWN business_hours block reusing the
		// same `meta_business_hours[<day>][...]` names; only the picked type's
		// block is active, the rest are hidden + disabled. Iterating all of
		// them let a later inactive block's empty value overwrite the active
		// block's real pick in `byDay`, so `hasAny` came out false and the
		// whole section was dropped (card 9928220940). Skip disabled inputs
		// and inputs inside hidden type-blocks so only the active type's
		// hours feed the preview.
		if ( input.disabled ) return;
		const typeBlock = input.closest( '.listora-submission__type-fields' );
		if ( typeBlock && typeBlock.hasAttribute( 'hidden' ) ) return;

		const m = input.name.match(
			/^(?:meta_)?business_hours\[(\d+)\]\[([a-z_0-9]+)\]$/
		);
		if ( ! m ) return;
		const day = parseInt( m[ 1 ], 10 );
		const key = m[ 2 ];
		const isCheckbox = input.type === 'checkbox';
		const value = isCheckbox ? input.checked : readHoursValue( input );
		if ( ! byDay[ day ] ) byDay[ day ] = {};
		byDay[ day ][ key ] = value;
	} );

	// Skip if every day is blank — user hasn't filled this section yet.
	const hasAny = Object.values( byDay ).some( ( d ) =>
		( d.open && String( d.open ).trim() ) || ( d.close && String( d.close ).trim() ) || d.closed || d.is_24h
	);
	if ( ! hasAny ) return;

	const dayLabels = [
		'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
	];

	const dt = document.createElement( 'dt' );
	dt.textContent = ( window.listoraI18n && window.listoraI18n.businessHours ) || 'Business Hours';

	const dd = document.createElement( 'dd' );
	const table = document.createElement( 'table' );
	table.className = 'listora-submission__preview-hours';

	for ( let d = 0; d <= 6; d++ ) {
		const data = byDay[ d ] || {};
		const tr = document.createElement( 'tr' );

		const labelCell = document.createElement( 'th' );
		labelCell.scope = 'row';
		labelCell.textContent = dayLabels[ d ];
		tr.appendChild( labelCell );

		const valueCell = document.createElement( 'td' );
		if ( data.closed ) {
			valueCell.textContent = ( window.listoraI18n && window.listoraI18n.closed ) || 'Closed';
		} else if ( data.is_24h ) {
			valueCell.textContent = ( window.listoraI18n && window.listoraI18n.open24h ) || 'Open 24 Hours';
		} else if ( data.open ) {
			valueCell.textContent = data.open + ' – ' + ( data.close || '–' );
		} else {
			valueCell.textContent = '–';
		}
		tr.appendChild( valueCell );

		table.appendChild( tr );
	}

	dd.appendChild( table );
	list.appendChild( dt );
	list.appendChild( dd );
}

/**
 * Find the human-readable label for a form field (its visible <label>).
 */
function resolvePreviewLabel( field ) {
	if ( field.id ) {
		const lbl = document.querySelector( `label[for="${ CSS.escape( field.id ) }"]` );
		if ( lbl ) {
			return lbl.textContent.replace( /\*$/, '' ).trim();
		}
	}
	const wrapper = field.closest( '.listora-submission__field, .listora-submission__group' );
	if ( wrapper ) {
		const lbl = wrapper.querySelector( '.listora-submission__label, label' );
		if ( lbl ) {
			return lbl.textContent.replace( /\*$/, '' ).trim();
		}
	}
	return field.name;
}

/**
 * Read a user-readable value for a form field, accounting for selects,
 * checkboxes, and multi-value inputs.
 */
function resolvePreviewValue( field ) {
	if ( field.tagName === 'SELECT' ) {
		const opt = field.options[ field.selectedIndex ];
		return opt && opt.value ? opt.textContent.trim() : '';
	}
	if ( field.type === 'checkbox' ) {
		return field.checked ? ( field.value || '✓' ) : '';
	}
	if ( field.type === 'radio' ) {
		return field.checked ? ( field.value || '' ) : '';
	}
	const v = ( field.value || '' ).trim();
	return v.length > 200 ? v.substring( 0, 200 ) + '…' : v;
}

/**
 * Render featured image + gallery thumbnails into the Preview step.
 *
 * The Media step persists the user's selection in two hidden inputs:
 *   - <input type="hidden" name="featured_image" value="{ID}">
 *   - <input type="hidden" name="gallery" value="{ID1,ID2,...}">
 *
 * The selection UX shows a preview <img> inside the upload zone (featured
 * image) and clones thumbs into #listora-gallery-thumbs (gallery), so we
 * already have rendered <img> elements with usable URLs in the DOM. This
 * helper finds them and clones their src into a Preview-card-friendly
 * layout — no extra REST round-trip, no new attachment metadata bag.
 *
 * Called from buildPreview() right after the Description blurb so the
 * Preview step actually mirrors what the published listing detail page
 * will look like (card 9842552596 round 5).
 *
 * @param {HTMLElement} formEl  The .listora-submission__form root.
 * @param {HTMLElement} preview The #listora-preview-content container.
 */
function appendMediaPreview( formEl, preview ) {
	const wrap = document.createElement( 'div' );
	wrap.classList.add( 'listora-submission__preview-media' );

	// Featured image — the upload zone for the `featured_image` target
	// renders an <img class="listora-submission__media-preview"> after a pick.
	const fiInput = formEl.querySelector( 'input[name="featured_image"]' );
	const fiId    = fiInput?.value?.trim() || '';
	if ( fiId ) {
		const fiZoneImg = formEl.querySelector(
			'[data-wp-context*="featured_image"] img'
		);
		const fiUrl = fiZoneImg?.src || '';
		if ( fiUrl ) {
			const fig = document.createElement( 'figure' );
			fig.classList.add( 'listora-submission__preview-featured' );
			const img = document.createElement( 'img' );
			img.src = fiUrl;
			img.alt = '';
			fig.appendChild( img );
			wrap.appendChild( fig );
		}
	}

	// Gallery — the user's picked images live as <img> children of
	// #listora-gallery-thumbs. Clone each into a flex row so the Preview
	// shows the same set the user just curated.
	const gInput = formEl.querySelector( 'input[name="gallery"]' );
	const gIds   = ( gInput?.value || '' ).split( ',' ).map( ( s ) => s.trim() ).filter( Boolean );
	if ( gIds.length ) {
		const galleryThumbs = document.querySelectorAll(
			'#listora-gallery-thumbs .listora-submission__gallery-thumb img'
		);
		if ( galleryThumbs.length ) {
			const row = document.createElement( 'div' );
			row.classList.add( 'listora-submission__preview-gallery' );
			galleryThumbs.forEach( ( srcImg ) => {
				const img = document.createElement( 'img' );
				img.src = srcImg.src;
				img.alt = '';
				row.appendChild( img );
			} );
			wrap.appendChild( row );
		}
	}

	if ( wrap.children.length > 0 ) {
		preview.appendChild( wrap );
	}
}

/**
 * Build a small "remove" (X) button used on featured-image previews and
 * gallery thumbs. Built with createElement / createElementNS so we never
 * touch innerHTML with localized strings (BC 9919930884).
 */
function buildRemoveButton( datasetKey, datasetValue, ariaLabel ) {
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = 'listora-submission__media-remove';
	btn.dataset[ datasetKey ] = datasetValue;
	btn.setAttribute( 'aria-label', ariaLabel );

	const svgNs = 'http://www.w3.org/2000/svg';
	const svg = document.createElementNS( svgNs, 'svg' );
	svg.setAttribute( 'width', '14' );
	svg.setAttribute( 'height', '14' );
	svg.setAttribute( 'viewBox', '0 0 24 24' );
	svg.setAttribute( 'fill', 'none' );
	svg.setAttribute( 'stroke', 'currentColor' );
	svg.setAttribute( 'stroke-width', '2.5' );
	svg.setAttribute( 'aria-hidden', 'true' );

	[ [ '18', '6', '6', '18' ], [ '6', '6', '18', '18' ] ].forEach(
		( [ x1, y1, x2, y2 ] ) => {
			const line = document.createElementNS( svgNs, 'line' );
			line.setAttribute( 'x1', x1 );
			line.setAttribute( 'y1', y1 );
			line.setAttribute( 'x2', x2 );
			line.setAttribute( 'y2', y2 );
			svg.appendChild( line );
		}
	);

	btn.appendChild( svg );
	return btn;
}

/**
 * Add a gallery thumbnail using safe DOM methods. Each thumb carries its
 * attachment ID + a remove control (BC 9919930884) so users can drop a
 * picked image without re-opening the media library.
 */
function addGalleryThumb( attachment ) {
	const thumbs = document.querySelector( '#listora-gallery-thumbs' );
	if ( ! thumbs ) return;

	const url = attachment.sizes?.thumbnail?.url || attachment.url;
	const div = document.createElement( 'div' );
	div.classList.add( 'listora-submission__gallery-thumb' );
	div.dataset.attachmentId = String( attachment.id );

	const img = document.createElement( 'img' );
	img.src = url;
	img.alt = '';
	div.appendChild( img );

	const i18n = ( typeof window !== 'undefined' && window.listoraI18n ) || {};
	div.appendChild(
		buildRemoveButton(
			'listoraRemoveGallery',
			String( attachment.id ),
			i18n.removeGalleryImage || 'Remove gallery image'
		)
	);

	thumbs.appendChild( div );
}

/**
 * Restore the empty upload-zone state after a featured-image remove.
 * Built via safe DOM methods — no innerHTML.
 */
function restoreUploadZone( zone ) {
	zone.textContent = '';

	const svgNs = 'http://www.w3.org/2000/svg';
	const svg = document.createElementNS( svgNs, 'svg' );
	svg.setAttribute( 'width', '32' );
	svg.setAttribute( 'height', '32' );
	svg.setAttribute( 'viewBox', '0 0 24 24' );
	svg.setAttribute( 'fill', 'none' );
	svg.setAttribute( 'stroke', 'currentColor' );
	svg.setAttribute( 'stroke-width', '1.5' );
	svg.setAttribute( 'aria-hidden', 'true' );

	const rect = document.createElementNS( svgNs, 'rect' );
	rect.setAttribute( 'width', '18' );
	rect.setAttribute( 'height', '18' );
	rect.setAttribute( 'x', '3' );
	rect.setAttribute( 'y', '3' );
	rect.setAttribute( 'rx', '2' );
	rect.setAttribute( 'ry', '2' );
	svg.appendChild( rect );

	const circle = document.createElementNS( svgNs, 'circle' );
	circle.setAttribute( 'cx', '9' );
	circle.setAttribute( 'cy', '9' );
	circle.setAttribute( 'r', '2' );
	svg.appendChild( circle );

	const path = document.createElementNS( svgNs, 'path' );
	path.setAttribute( 'd', 'm21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21' );
	svg.appendChild( path );

	zone.appendChild( svg );

	const i18n = ( typeof window !== 'undefined' && window.listoraI18n ) || {};
	const promptSpan = document.createElement( 'span' );
	promptSpan.textContent = i18n.uploadPrompt || 'Click to upload or drag & drop';
	zone.appendChild( promptSpan );

	const hintSpan = document.createElement( 'span' );
	hintSpan.className = 'listora-submission__upload-hint';
	hintSpan.textContent = i18n.uploadHint || 'Max 5MB, JPG/PNG/WebP';
	zone.appendChild( hintSpan );
}

/**
 * Delegated click handler for media remove controls (BC 9919930884).
 *
 * Handles two surfaces:
 *   [data-listora-remove-gallery="{id}"] — strips the thumb DOM AND the
 *     ID from the comma-separated gallery hidden input.
 *   [data-listora-remove-media="featured_image"] — clears the hidden input
 *     AND restores the upload-zone empty state.
 *
 * stopPropagation() prevents the upload-zone click handler from re-opening
 * the media library when the user clicks the embedded remove button.
 */
document.addEventListener( 'click', function ( event ) {
	const btn = event.target.closest(
		'[data-listora-remove-gallery], [data-listora-remove-media]'
	);
	if ( ! btn ) return;

	event.preventDefault();
	event.stopPropagation();

	const removeId = btn.dataset.listoraRemoveGallery;
	if ( removeId ) {
		const thumb = btn.closest( '.listora-submission__gallery-thumb' );
		if ( thumb ) thumb.remove();

		const input = document.querySelector( 'input[name="gallery"]' );
		if ( input ) {
			input.value = input.value
				.split( ',' )
				.map( ( s ) => s.trim() )
				.filter( ( id ) => id && id !== removeId )
				.join( ',' );
		}
		return;
	}

	const target = btn.dataset.listoraRemoveMedia;
	if ( target ) {
		const input = document.querySelector( `input[name="${ target }"]` );
		if ( input ) input.value = '';

		const zone = document.querySelector(
			`[data-wp-context*="${ target }"]`
		);
		if ( zone ) restoreUploadZone( zone );
	}
} );

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

	abortableFetch( url, { headers: { Accept: 'application/json' } } )
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

	abortableFetch( url, { headers: { Accept: 'application/json' } } )
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
 * Registry of provider-specific map-picker initializers.
 *
 * Extension point for add-ons that render the Add Listing location picker with
 * a different map engine than the bundled Leaflet/OpenStreetMap one. The
 * provider key matches the admin's Settings → Maps → Provider value (and the
 * `wb_listora_map_provider` filter), surfaced on each picker element as
 * `data-provider`. 'osm' is handled natively below; any other provider is
 * delegated here.
 *
 * Contract — a registered initializer is called as:
 *
 *     window.wbListoraMapPickers[ provider ]( el, {
 *         parent,        // .listora-submission__map-field wrapper (or null)
 *         initialLat,    // number — resolved start latitude
 *         initialLng,    // number — resolved start longitude
 *         initialZoom,   // number — resolved start zoom
 *         hasExisting,   // boolean — true in edit mode (coords pre-filled)
 *         updateLatLngFields, // fn( {lat,lng}, parent ) — writes hidden lat/lng inputs
 *         reverseGeocode,     // fn( lat, lng, parent ) — fills address fields from coords
 *         forwardGeocode,     // fn( addressString, mapRef, markerRef, parent ) — Leaflet-only; pass own geocoder
 *     } )
 *
 * The initializer OWNS the element from that point: it must build the map,
 * wire marker drag / map click → updateLatLngFields + (its own) reverse
 * geocode, wire the address field → forward geocode, and set a truthy guard
 * (e.g. `el.dataset.providerMapInit = '1'`) so it doesn't double-init. Returning
 * a truthy value tells Free the picker was handled; returning falsy lets Free
 * fall back to the Leaflet/OSM engine below.
 *
 * Example (Pro, after its Google Maps JS API loader is ready):
 *
 *     window.wbListoraMapPickers = window.wbListoraMapPickers || {};
 *     window.wbListoraMapPickers.google = function ( el, ctx ) { … return true; };
 *
 * @type {Object.<string, Function>}
 */
if ( typeof window.wbListoraMapPickers === 'undefined' ) {
	window.wbListoraMapPickers = {};
}

/**
 * Init the location map pickers in the details step.
 *
 * Default engine is Leaflet/OpenStreetMap (bundled with Free). When the admin
 * selects a different provider (exposed per element as `data-provider`) and an
 * add-on has registered a matching initializer in `window.wbListoraMapPickers`,
 * that initializer takes over the element instead of Leaflet.
 *
 * Leaflet path creates a draggable marker that syncs with address fields:
 * - Drag marker or click map: reverse-geocodes to fill address fields.
 * - Type address: forward-geocodes to move the marker.
 * - On init: uses existing lat/lng values, or attempts browser geolocation.
 */
function initMapPickers( step ) {
	step.querySelectorAll( '.listora-submission__map-picker' ).forEach( ( el ) => {
		if ( el._leafletMap || el.dataset.providerMapInit ) return;

		const parent = el.closest( '.listora-submission__map-field' );

		// Resolve provider — 'osm' (Leaflet) is the bundled default.
		const provider = ( el.dataset.provider || 'osm' ).toLowerCase();

		// Pre-compute the shared start position so every engine centers the same
		// way (edit-mode coords → admin default → NYC legacy fallback).
		const preLat = parent ? parseFloat( parent.querySelector( '[name$="[lat]"]' )?.value ) : NaN;
		const preLng = parent ? parseFloat( parent.querySelector( '[name$="[lng]"]' )?.value ) : NaN;
		const preHasExisting = ! isNaN( preLat ) && ! isNaN( preLng ) && preLat !== 0 && preLng !== 0;
		const dfLat = parseFloat( el.dataset.defaultLat );
		const dfLng = parseFloat( el.dataset.defaultLng );
		const dfZoom = parseInt( el.dataset.defaultZoom, 10 );
		const startLat = preHasExisting ? preLat : ( ! isNaN( dfLat ) ? dfLat : 40.7128 );
		const startLng = preHasExisting ? preLng : ( ! isNaN( dfLng ) ? dfLng : -74.006 );
		const startZoom = preHasExisting ? 15 : ( ! isNaN( dfZoom ) && dfZoom > 0 ? dfZoom : 12 );

		// Non-OSM provider with a registered initializer → delegate and skip Leaflet.
		if (
			'osm' !== provider &&
			typeof window.wbListoraMapPickers[ provider ] === 'function'
		) {
			const handled = window.wbListoraMapPickers[ provider ]( el, {
				parent,
				initialLat: startLat,
				initialLng: startLng,
				initialZoom: startZoom,
				hasExisting: preHasExisting,
				updateLatLngFields,
				reverseGeocode,
				forwardGeocode,
			} );
			if ( handled ) {
				el.dataset.providerMapInit = '1';
				return;
			}
		}

		// No registered engine for this provider: fall back to Leaflet/OSM. When
		// Leaflet isn't present (e.g. a Pro provider deregistered it) the add-on
		// initializes the element off its own DOM scan — mirror the display map's
		// "skip itself when L is undefined" idiom rather than rendering OSM.
		if ( typeof L === 'undefined' ) return;

		// Default coords come from admin's Settings → Maps → Default location
		// (exposed by the renderer as data-default-* attributes), resolved into
		// startLat / startLng / startZoom above. Fall back to NYC only when those
		// attrs are missing — keeps the legacy out-of-the-box behaviour for
		// installs that haven't customised the map defaults.
		const hasExisting = preHasExisting;
		const initialLat = startLat;
		const initialLng = startLng;
		const initialZoom = startZoom;

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

		// Recalc tile geometry once the container is actually laid out. The
		// map is created the moment the Details step is revealed, but the
		// CSS step transition can leave the container at 0 height for a few
		// frames — a single fixed 200ms timeout (the old approach) raced that
		// transition and computed tiles against a 0-height box, so the map
		// only appeared after a resize / extra Continue click (card
		// 9932290292). Recalc via rAF once the box has height, keep a short
		// retry loop as a backstop, and observe later size changes with a
		// ResizeObserver. All paths are idempotent — invalidateSize() is safe
		// to call repeatedly.
		recalcMapWhenVisible( map, el );
	} );
}

/**
 * Robustly recalc a Leaflet map's size once its container is laid out.
 *
 * Replaces the fragile single `setTimeout(() => invalidateSize(), 200)` that
 * raced the CSS step-reveal transition. Strategy:
 *   1. requestAnimationFrame loop — fire invalidateSize() as soon as the
 *      container reports a non-zero height (bounded retry count so we never
 *      spin forever if the step stays hidden).
 *   2. ResizeObserver — keep the map honest if the container changes size
 *      later (responsive reflow, late font load, sidebar toggle).
 *
 * @param {Object}      map Leaflet map instance.
 * @param {HTMLElement} el  Map container element.
 */
function recalcMapWhenVisible( map, el ) {
	let frames = 0;
	const maxFrames = 60; // ~1s at 60fps — generous backstop, then stop.

	const tick = () => {
		if ( ! el.isConnected ) return;
		if ( el.offsetHeight > 0 ) {
			map.invalidateSize();
			return;
		}
		if ( frames++ < maxFrames ) {
			( window.requestAnimationFrame || window.setTimeout )( tick );
		}
	};
	( window.requestAnimationFrame || window.setTimeout )( tick );

	if ( typeof window.ResizeObserver === 'function' && ! el._leafletResizeObserver ) {
		const ro = new window.ResizeObserver( () => {
			if ( el.offsetHeight > 0 ) {
				map.invalidateSize();
			}
		} );
		ro.observe( el );
		el._leafletResizeObserver = ro;
	}
}

/**
 * Evaluate conditional fields within a form.
 *
 * Reads `data-listora-condition` attributes from field wrappers and
 * shows/hides them based on the current values of trigger fields.
 *
 * Condition format: { "field": "meta_key", "operator": "equals", "value": "rent" }
 *
 * @param {HTMLElement} form The form element.
 */
function evaluateConditionals( form ) {
	const conditionalFields = form.querySelectorAll( '[data-listora-condition]' );

	conditionalFields.forEach( ( wrapper ) => {
		try {
			const condition = JSON.parse( wrapper.dataset.listoraCondition );
			if ( ! condition || ! condition.field ) return;

			// Find the trigger field by name (meta_ prefix).
			// Field names may contain brackets (e.g. business_hours[1][open]),
			// so escape before composing the attribute selector — without
			// CSS.escape, querySelector throws SyntaxError on those names.
			const triggerName = 'meta_' + condition.field;
			let triggerInput = null;
			try {
				triggerInput = form.querySelector( `[name="${ CSS.escape( triggerName ) }"]` );
			} catch ( _err ) {
				return;
			}
			if ( ! triggerInput ) return;

			// Get the current value of the trigger field.
			let currentValue = '';
			if ( triggerInput.type === 'checkbox' ) {
				currentValue = triggerInput.checked ? '1' : '';
			} else if ( triggerInput.type === 'radio' ) {
				let checked = null;
				try {
					checked = form.querySelector(
						`[name="${ CSS.escape( triggerName ) }"]:checked`
					);
				} catch ( _err ) {
					checked = null;
				}
				currentValue = checked ? checked.value : '';
			} else {
				currentValue = triggerInput.value;
			}

			const shouldShow = evaluateCondition( currentValue, condition.operator || 'equals', condition.value || '' );

			if ( shouldShow ) {
				wrapper.classList.remove( 'listora-submission__field--conditional-hidden' );
				// Restore required attribute on inputs that originally had it.
				wrapper.querySelectorAll( '[data-listora-required-original]' ).forEach( ( inp ) => {
					inp.setAttribute( 'required', 'required' );
				} );
			} else {
				wrapper.classList.add( 'listora-submission__field--conditional-hidden' );
				// Strip native required from hidden fields so the browser's
				// own form validation does not block submission on inputs the
				// user cannot see. Mark them so we can restore on un-hide.
				wrapper.querySelectorAll( '[required]' ).forEach( ( inp ) => {
					inp.dataset.listoraRequiredOriginal = '1';
					inp.removeAttribute( 'required' );
				} );
			}
		} catch {
			// Invalid JSON — skip this field.
		}
	} );
}

/**
 * Evaluate a single condition.
 *
 * @param {string} actual   Current field value.
 * @param {string} operator Condition operator.
 * @param {string} target   Expected value.
 * @return {boolean}
 */
function evaluateCondition( actual, operator, target ) {
	switch ( operator ) {
		case 'equals':
			return actual === target;
		case 'not_equals':
			return actual !== target;
		case 'contains':
			return actual.includes( target );
		case 'not_empty':
			return actual !== '' && actual !== null && actual !== undefined;
		case 'empty':
			return actual === '' || actual === null || actual === undefined;
		case 'greater_than':
			return parseFloat( actual ) > parseFloat( target );
		case 'less_than':
			return parseFloat( actual ) < parseFloat( target );
		default:
			return true;
	}
}

/**
 * Clear values of hidden conditional fields before form submission.
 *
 * This ensures that fields hidden by conditions do not send stale data
 * to the server.
 *
 * @param {HTMLElement} formEl The form element.
 */
function clearHiddenConditionalFields( formEl ) {
	const hiddenFields = formEl.querySelectorAll( '.listora-submission__field--conditional-hidden' );

	hiddenFields.forEach( ( wrapper ) => {
		const inputs = wrapper.querySelectorAll( 'input, select, textarea' );
		inputs.forEach( ( input ) => {
			if ( input.type === 'checkbox' || input.type === 'radio' ) {
				input.checked = false;
			} else {
				input.value = '';
			}
		} );
	} );
}

/**
 * Get reCAPTCHA v3 token before submission.
 *
 * If reCAPTCHA v3 is loaded (window.grecaptcha), executes a token request
 * and places the result in the hidden captcha token field.
 *
 * @param {HTMLElement} formEl The form element.
 * @return {Promise<void>}
 */
async function getRecaptchaToken( formEl ) {
	const providerInput = formEl.querySelector( '[name="listora_captcha_provider"]' );
	if ( ! providerInput || providerInput.value !== 'recaptcha_v3' ) {
		return;
	}

	if ( typeof window.grecaptcha === 'undefined' ) {
		return;
	}

	const siteKey = document.querySelector( '.g-recaptcha' )?.dataset?.sitekey ||
		formEl.closest( '[data-wp-interactive]' )?.dataset?.recaptchaSitekey || '';

	// Use a fallback: scan for the script tag to get the site key.
	if ( ! siteKey ) {
		const scriptTag = document.querySelector( 'script[src*="recaptcha/api.js?render="]' );
		if ( scriptTag ) {
			const match = scriptTag.src.match( /render=([^&]+)/ );
			if ( match ) {
				try {
					await window.grecaptcha.ready( () => {} );
					const token = await window.grecaptcha.execute( match[ 1 ], { action: 'listora_submit' } );
					const tokenInput = formEl.querySelector( '[name="listora_captcha_token"]' );
					if ( tokenInput ) {
						tokenInput.value = token;
					}
				} catch {
					// reCAPTCHA failed — let server handle the missing token.
				}
			}
		}
		return;
	}

	try {
		await window.grecaptcha.ready( () => {} );
		const token = await window.grecaptcha.execute( siteKey, { action: 'listora_submit' } );
		const tokenInput = formEl.querySelector( '[name="listora_captcha_token"]' );
		if ( tokenInput ) {
			tokenInput.value = token;
		}
	} catch {
		// reCAPTCHA failed — let server handle the missing token.
	}
}

/**
 * Initialize conditional field watchers.
 *
 * Sets up change/input event listeners on all fields that are referenced by
 * conditional fields, so that visibility is re-evaluated in real time.
 */
function initConditionalFieldWatchers() {
	document.querySelectorAll( '.listora-submission__form' ).forEach( ( form ) => {
		const conditionalFields = form.querySelectorAll( '[data-listora-condition]' );
		const triggerFieldNames = new Set();

		// Collect all trigger field names.
		conditionalFields.forEach( ( wrapper ) => {
			try {
				const condition = JSON.parse( wrapper.dataset.listoraCondition );
				if ( condition && condition.field ) {
					triggerFieldNames.add( 'meta_' + condition.field );
				}
			} catch {
				// Invalid JSON — skip.
			}
		} );

		// Attach event listeners to trigger fields. Field names with brackets
		// (e.g. nested array notation) need CSS.escape — without it
		// querySelectorAll throws SyntaxError and watcher setup aborts.
		triggerFieldNames.forEach( ( name ) => {
			let inputs;
			try {
				inputs = form.querySelectorAll( `[name="${ CSS.escape( name ) }"]` );
			} catch ( _err ) {
				return;
			}
			inputs.forEach( ( input ) => {
				input.addEventListener( 'change', () => evaluateConditionals( form ) );
				input.addEventListener( 'input', () => evaluateConditionals( form ) );
			} );
		} );

		// Run initial evaluation.
		evaluateConditionals( form );
	} );
}

/**
 * Initialize Turnstile callback.
 *
 * Cloudflare Turnstile calls a global callback with the token.
 * We place it into the hidden input.
 */
if ( typeof window.listoraOnTurnstileSuccess === 'undefined' ) {
	window.listoraOnTurnstileSuccess = function( token ) {
		// Update all turnstile token inputs on the page.
		document.querySelectorAll( '[name="listora_captcha_token"]' ).forEach( ( input ) => {
			const provider = input.closest( 'form' )?.querySelector( '[name="listora_captcha_provider"]' );
			if ( provider && provider.value === 'cloudflare_turnstile' ) {
				input.value = token;
			}
		} );
	};
}

// Initialize conditional field watchers when the DOM is ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initConditionalFieldWatchers );
} else {
	initConditionalFieldWatchers();
}

/**
 * Live-update the credit banner whenever the user picks a different plan.
 *
 * Plan radios are rendered by Pro via `wb_listora_submission_plan_step` and
 * may appear anywhere inside the submission form. A single delegated listener
 * keeps this resilient to Pro's exact markup.
 */
function initCreditBannerWatchers() {
	document.querySelectorAll( '.listora-submission' ).forEach( ( form ) => {
		if ( form.dataset.listoraCreditWatcher === '1' ) return;
		form.dataset.listoraCreditWatcher = '1';

		form.addEventListener( 'change', ( event ) => {
			const target = event.target;
			if ( target && target.name === 'plan_id' ) {
				updateCreditBanner( form );
			}
		} );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initCreditBannerWatchers );
} else {
	initCreditBannerWatchers();
}

/**
 * Initialize map pickers on any details step that is already visible on load.
 *
 * In wizard mode `initMapPickers()` runs when the user navigates forward onto
 * the `details` step (see `nextSubmissionStep`). In single-form layout mode
 * (the dashboard inline add form, `layoutMode="single-form"`) every step is
 * rendered visible at once and no step navigation ever fires, so the map would
 * otherwise never initialize. Init each `details` step that is currently
 * visible (`offsetParent !== null`); steps hidden in wizard mode have
 * `offsetParent === null` and are skipped. The double-init guard inside
 * `initMapPickers()` keeps this safe alongside the wizard call path.
 */
function initVisibleMapPickers() {
	document
		.querySelectorAll( '.listora-submission__step[data-step="details"]' )
		.forEach( ( step ) => {
			if ( step.offsetParent !== null ) {
				initMapPickers( step );
			}
		} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initVisibleMapPickers );
} else {
	initVisibleMapPickers();
}

/**
 * Set navigation button state for single-form layout forms on load.
 *
 * There is no per-form nav init in wizard mode — the markup ships the correct
 * defaults (Back/Submit `hidden`, Continue visible). Single-form needs the
 * inverse (Continue hidden, Submit shown), so it must be applied explicitly on
 * load. Delegates to `updateNavButtons`, which carries the single-form branch.
 */
function initSingleFormNav() {
	document
		.querySelectorAll( '.listora-submission--single-form' )
		.forEach( ( form ) => {
			const steps = form.querySelectorAll( '.listora-submission__step' );
			updateNavButtons( form, 0, steps.length );
		} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initSingleFormNav );
} else {
	initSingleFormNav();
}

/**
 * Populate + live-update the "Preview Your Listing" panel in single-form layout.
 *
 * In wizard mode `buildPreview()` is driven by the Back/Continue step-navigation
 * (the only callers, at lines ~75 / ~133). Single-form layout (the dashboard
 * inline add/edit, `layoutMode="single-form"`) hides that navigation, so the
 * preview was never built and the owner just saw the placeholder
 * ("Preview will appear here after filling in the form.") — card 9986602764.
 *
 * This mirrors initSingleFormNav / initVisibleMapPickers: build once on load so
 * edit mode shows the listing's current values immediately, then keep the panel
 * in sync as the owner types (debounced so we rebuild after they pause, not on
 * every keystroke). Non-technical owners get the live, reassuring preview the
 * wizard already provides.
 */
function initSingleFormPreview() {
	document
		.querySelectorAll( '.listora-submission--single-form' )
		.forEach( ( form ) => {
			if ( ! form.querySelector( '#listora-preview-content' ) ) {
				return;
			}
			// Initial paint (covers edit mode with pre-filled values).
			buildPreview( form );

			// Live updates, debounced so a fast typist doesn't trigger a
			// rebuild per character.
			let previewTimer = null;
			const schedulePreview = () => {
				if ( previewTimer ) {
					clearTimeout( previewTimer );
				}
				previewTimer = setTimeout( () => buildPreview( form ), 250 );
			};
			form.addEventListener( 'input', schedulePreview );
			form.addEventListener( 'change', schedulePreview );
		} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initSingleFormPreview );
} else {
	initSingleFormPreview();
}

/**
 * Render the duplicate-review step inside the submission block.
 *
 * Builds (or reuses) a `.listora-submission__duplicate-review` panel with one
 * card per duplicate (capped at 5, with "+ N more" overflow), a confirm
 * checkbox, an explanation textarea, and Cancel / Submit Anyway buttons.
 *
 * @param {HTMLElement} form       The `.listora-submission` block root.
 * @param {Array}       duplicates Array of `{ id, title, url, similarity, distance? }`.
 */
function showDuplicateReviewStep( form, duplicates ) {
	const formEl = form.querySelector( '.listora-submission__form' );
	if ( ! formEl ) return;

	let panel = form.querySelector( '.listora-submission__duplicate-review' );
	if ( ! panel ) {
		panel = document.createElement( 'div' );
		panel.className = 'listora-submission__duplicate-review';
		form.appendChild( panel );
	}

	// Edge case: 0 duplicates returned. Fall back to the generic error path
	// instead of showing an empty review step.
	if ( ! Array.isArray( duplicates ) || duplicates.length === 0 ) {
		panel.hidden = true;
		const errorDiv = form.querySelector( '.listora-submission__error' );
		if ( errorDiv ) {
			errorDiv.hidden = false;
			const p = errorDiv.querySelector( 'p' );
			if ( p ) p.textContent = 'Potential duplicate listing(s) found, but no details available. Please change your title and try again.';
		}
		return;
	}

	const visible = duplicates.slice( 0, 5 );
	const overflow = Math.max( 0, duplicates.length - visible.length );

	// Reset panel children safely without innerHTML.
	while ( panel.firstChild ) {
		panel.removeChild( panel.firstChild );
	}

	const heading = document.createElement( 'h2' );
	heading.className = 'listora-submission__duplicate-review-heading';
	heading.textContent = 'We found similar listings — is yours different?';
	panel.appendChild( heading );

	const intro = document.createElement( 'p' );
	intro.className = 'listora-submission__duplicate-review-intro';
	intro.textContent = 'These existing listings look similar to what you entered. Please review them before submitting.';
	panel.appendChild( intro );

	const list = document.createElement( 'ul' );
	list.className = 'listora-submission__duplicate-list';
	visible.forEach( ( dup ) => {
		list.appendChild( buildDuplicateCard( dup ) );
	} );
	panel.appendChild( list );

	if ( overflow > 0 ) {
		const more = document.createElement( 'p' );
		more.className = 'listora-submission__duplicate-more';
		more.textContent = '+ ' + overflow + ' more similar listing' + ( overflow === 1 ? '' : 's' ) + ' not shown.';
		panel.appendChild( more );
	}

	const notice = document.createElement( 'p' );
	notice.className = 'listora-submission__duplicate-review-notice';
	notice.textContent = 'If your business is different from all listings above, you can submit it. We\'ll keep both.';
	panel.appendChild( notice );

	// Confirm checkbox.
	const confirmField = document.createElement( 'div' );
	confirmField.className = 'listora-submission__field listora-submission__field--checkbox';
	const confirmLabel = document.createElement( 'label' );
	confirmLabel.className = 'listora-submission__checkbox-label';
	const confirmInput = document.createElement( 'input' );
	confirmInput.type = 'checkbox';
	confirmInput.name = 'listora_dup_confirm';
	confirmInput.required = true;
	const confirmText = document.createElement( 'span' );
	confirmText.textContent = ' I confirm this is a different business, not a duplicate of the above';
	confirmLabel.appendChild( confirmInput );
	confirmLabel.appendChild( confirmText );
	confirmField.appendChild( confirmLabel );
	panel.appendChild( confirmField );

	// Explanation textarea.
	const explainField = document.createElement( 'div' );
	explainField.className = 'listora-submission__field';
	const explainLabel = document.createElement( 'label' );
	explainLabel.className = 'listora-submission__label';
	explainLabel.textContent = 'Briefly explain how it\'s different';
	const explainHint = document.createElement( 'span' );
	explainHint.className = 'listora-submission__field-hint';
	explainHint.textContent = ' (helps our review team — at least 20 characters)';
	explainLabel.appendChild( explainHint );
	const explainInput = document.createElement( 'textarea' );
	explainInput.name = 'listora_dup_explanation';
	explainInput.rows = 4;
	explainInput.required = true;
	explainInput.minLength = 20;
	explainInput.className = 'listora-input';
	explainInput.placeholder = 'e.g. "We are a different restaurant in Brooklyn, not affiliated with the Manhattan location."';
	explainField.appendChild( explainLabel );
	explainField.appendChild( explainInput );
	panel.appendChild( explainField );

	// Inline error placeholder.
	const inlineError = document.createElement( 'div' );
	inlineError.className = 'listora-submission__duplicate-review-error';
	inlineError.setAttribute( 'role', 'alert' );
	inlineError.hidden = true;
	panel.appendChild( inlineError );

	// Action buttons.
	const actions = document.createElement( 'div' );
	actions.className = 'listora-submission__duplicate-review-actions';

	const cancelBtn = document.createElement( 'button' );
	cancelBtn.type = 'button';
	cancelBtn.className = 'listora-btn listora-btn--secondary';
	cancelBtn.textContent = 'Cancel — change my listing';
	cancelBtn.addEventListener( 'click', () => {
		cancelDuplicateReviewImpl( form );
	} );

	const submitBtn = document.createElement( 'button' );
	submitBtn.type = 'button';
	submitBtn.className = 'listora-btn listora-btn--primary';
	submitBtn.textContent = 'Submit anyway';
	submitBtn.addEventListener( 'click', () => {
		submitAnywayImpl( form );
	} );

	actions.appendChild( cancelBtn );
	actions.appendChild( submitBtn );
	panel.appendChild( actions );

	// Hide the form + nav while showing the review.
	formEl.hidden = true;
	const nav = form.querySelector( '.listora-submission__nav' );
	if ( nav ) nav.hidden = true;
	const errorDiv = form.querySelector( '.listora-submission__error' );
	if ( errorDiv ) errorDiv.hidden = true;

	panel.hidden = false;
	// Make the panel an accessible region with a focusable target so focus
	// can move into it without trapping mouse users.
	panel.setAttribute( 'role', 'region' );
	panel.setAttribute( 'aria-label', 'Duplicate listing review' );
	if ( ! panel.hasAttribute( 'tabindex' ) ) panel.setAttribute( 'tabindex', '-1' );
	panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	// Defer focus until after scroll so the heading is in view.
	setTimeout( () => panel.focus( { preventScroll: true } ), 200 );

	// ESC key cancels the review. Tracked on the panel so we can detach.
	if ( ! panel._dupEscHandler ) {
		panel._dupEscHandler = ( ev ) => {
			if ( ev.key === 'Escape' && ! panel.hidden ) {
				ev.preventDefault();
				cancelDuplicateReviewImpl( form );
			}
		};
		document.addEventListener( 'keydown', panel._dupEscHandler );
	}
}

/**
 * Build a single duplicate card list item.
 *
 * @param {Object} dup Duplicate descriptor with id/title/url/similarity[/distance].
 * @return {HTMLElement} The `<li>` card.
 */
function buildDuplicateCard( dup ) {
	const li = document.createElement( 'li' );
	li.className = 'listora-submission__duplicate-card';

	const similarity = Math.round( Number( dup.similarity ) || 0 );
	let badgeClass = 'listora-submission__duplicate-badge';
	if ( similarity >= 90 ) {
		badgeClass += ' listora-submission__duplicate-badge--high';
	} else if ( similarity >= 75 ) {
		badgeClass += ' listora-submission__duplicate-badge--medium';
	} else {
		badgeClass += ' listora-submission__duplicate-badge--low';
	}

	const body = document.createElement( 'div' );
	body.className = 'listora-submission__duplicate-card-body';

	const title = document.createElement( 'a' );
	title.className = 'listora-submission__duplicate-title';
	title.href = dup.url || '#';
	title.target = '_blank';
	title.rel = 'noopener noreferrer';
	title.textContent = dup.title || 'Untitled listing';
	body.appendChild( title );

	const meta = document.createElement( 'div' );
	meta.className = 'listora-submission__duplicate-meta';

	const badge = document.createElement( 'span' );
	badge.className = badgeClass;
	badge.textContent = similarity + '% match';
	meta.appendChild( badge );

	if ( typeof dup.distance === 'number' && ! Number.isNaN( dup.distance ) ) {
		const distEl = document.createElement( 'span' );
		distEl.className = 'listora-submission__duplicate-distance';
		distEl.textContent = '· ' + dup.distance + 'm away';
		meta.appendChild( distEl );
	}

	body.appendChild( meta );
	li.appendChild( body );

	const viewBtn = document.createElement( 'a' );
	viewBtn.className = 'listora-btn listora-btn--text listora-submission__duplicate-view';
	viewBtn.href = dup.url || '#';
	viewBtn.target = '_blank';
	viewBtn.rel = 'noopener noreferrer';
	viewBtn.textContent = 'View';
	li.appendChild( viewBtn );

	return li;
}

/**
 * Hide the duplicate review panel.
 *
 * @param {HTMLElement} form The `.listora-submission` block root.
 */
function hideDuplicateReviewStep( form ) {
	const panel = form.querySelector( '.listora-submission__duplicate-review' );
	if ( panel ) {
		panel.hidden = true;
		if ( panel._dupEscHandler ) {
			document.removeEventListener( 'keydown', panel._dupEscHandler );
			panel._dupEscHandler = null;
		}
	}
	const nav = form.querySelector( '.listora-submission__nav' );
	if ( nav ) nav.hidden = false;
}

/**
 * Cancel duplicate review — return to step 1.
 *
 * Standalone implementation invoked from the dynamically-built Cancel button.
 *
 * @param {HTMLElement} form The `.listora-submission` block root.
 */
function cancelDuplicateReviewImpl( form ) {
	hideDuplicateReviewStep( form );

	const confirmedInput = form.querySelector( '[name="confirmed_not_duplicate"]' );
	if ( confirmedInput ) confirmedInput.value = '';
	const explanationInput = form.querySelector( '[name="duplicate_explanation"]' );
	if ( explanationInput ) explanationInput.value = '';

	const formEl = form.querySelector( '.listora-submission__form' );
	if ( ! formEl ) return;
	formEl.hidden = false;

	const steps = form.querySelectorAll( '.listora-submission__step' );
	const indicators = form.querySelectorAll( '.listora-submission__step-indicator' );
	const lines = form.querySelectorAll( '.listora-submission__step-line' );

	steps.forEach( ( step, idx ) => {
		step.hidden = idx !== 0;
	} );
	indicators.forEach( ( ind, idx ) => {
		ind.classList.remove( 'is-completed' );
		ind.classList.toggle( 'is-current', idx === 0 );
		if ( idx === 0 ) {
			ind.setAttribute( 'aria-current', 'step' );
		} else {
			ind.removeAttribute( 'aria-current' );
		}
	} );
	const progressbarReset = form.querySelector( '.listora-submission__progress' );
	if ( progressbarReset ) {
		progressbarReset.setAttribute( 'aria-valuenow', '1' );
	}
	lines.forEach( ( line ) => line.classList.remove( 'is-completed' ) );

	updateNavButtons( form, 0, steps.length );
	form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
}

/**
 * Submit anyway — validate confirm + explanation, inject hidden fields, resubmit.
 *
 * @param {HTMLElement} form The `.listora-submission` block root.
 */
function submitAnywayImpl( form ) {
	const reviewStep = form.querySelector( '.listora-submission__duplicate-review' );
	if ( ! reviewStep ) return;

	const confirmCheckbox = reviewStep.querySelector( '[name="listora_dup_confirm"]' );
	const explanationField = reviewStep.querySelector( '[name="listora_dup_explanation"]' );
	const errorEl = reviewStep.querySelector( '.listora-submission__duplicate-review-error' );

	let valid = true;
	const messages = [];

	reviewStep.querySelectorAll( '.listora-submission__field--error' ).forEach( ( f ) => {
		f.classList.remove( 'listora-submission__field--error' );
	} );

	if ( ! confirmCheckbox || ! confirmCheckbox.checked ) {
		valid = false;
		messages.push( 'Please confirm this is not a duplicate.' );
		if ( confirmCheckbox ) {
			confirmCheckbox.closest( '.listora-submission__field' )?.classList.add( 'listora-submission__field--error' );
		}
	}

	const explanation = explanationField ? explanationField.value.trim() : '';
	if ( ! explanation || explanation.length < 20 ) {
		valid = false;
		messages.push( 'Please explain how your business is different (at least 20 characters).' );
		if ( explanationField ) {
			explanationField.closest( '.listora-submission__field' )?.classList.add( 'listora-submission__field--error' );
		}
	}

	if ( ! valid ) {
		if ( errorEl ) {
			errorEl.hidden = false;
			errorEl.textContent = messages.join( ' ' );
		}
		return;
	}
	if ( errorEl ) errorEl.hidden = true;

	const formEl = form.querySelector( '.listora-submission__form' );
	if ( ! formEl ) return;

	let confirmedInput = formEl.querySelector( '[name="confirmed_not_duplicate"]' );
	if ( ! confirmedInput ) {
		confirmedInput = document.createElement( 'input' );
		confirmedInput.type = 'hidden';
		confirmedInput.name = 'confirmed_not_duplicate';
		formEl.appendChild( confirmedInput );
	}
	confirmedInput.value = '1';

	let explanationInput = formEl.querySelector( '[name="duplicate_explanation"]' );
	if ( ! explanationInput ) {
		explanationInput = document.createElement( 'input' );
		explanationInput.type = 'hidden';
		explanationInput.name = 'duplicate_explanation';
		formEl.appendChild( explanationInput );
	}
	explanationInput.value = explanation;

	hideDuplicateReviewStep( form );
	formEl.hidden = false;

	if ( typeof formEl.requestSubmit === 'function' ) {
		formEl.requestSubmit();
	} else {
		formEl.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
	}
}

/**
 * Render the "Check your email" verification card after a 202 response from
 * the guest-submission flow.
 *
 * Replaces (or hides) the existing success div with a card that explains the
 * next step, exposes a rate-limited "Resend email" button, and a "Wrong email"
 * link that returns the user to step 1 of the wizard.
 *
 * @param {HTMLElement} form     The .listora-submission block root.
 * @param {Object}      response Server payload — { listing_id, email, message }.
 */
function showVerifyEmailCard( form, response ) {
	let card = form.querySelector( '.listora-submission__verify-email' );
	if ( ! card ) {
		card = document.createElement( 'div' );
		card.className = 'listora-submission__verify-email';
		form.appendChild( card );
	}

	while ( card.firstChild ) card.removeChild( card.firstChild );

	const icon = document.createElement( 'div' );
	icon.className = 'listora-submission__verify-icon';
	icon.setAttribute( 'aria-hidden', 'true' );
	icon.textContent = '✓';
	card.appendChild( icon );

	const heading = document.createElement( 'h2' );
	heading.className = 'listora-submission__verify-heading';
	heading.textContent = 'Almost there — verify your email';
	card.appendChild( heading );

	const body = document.createElement( 'p' );
	body.className = 'listora-submission__verify-body';
	const email = ( response && response.email ) ? response.email : 'your inbox';
	body.textContent = 'We sent a verification link to ' + email + '. Click the link in the email to publish your listing.';
	card.appendChild( body );

	const note = document.createElement( 'p' );
	note.className = 'listora-submission__verify-note';
	note.textContent = "Didn't get the email? Check your spam folder or click below to resend.";
	card.appendChild( note );

	const actions = document.createElement( 'div' );
	actions.className = 'listora-submission__verify-actions';

	const resendBtn = document.createElement( 'button' );
	resendBtn.type = 'button';
	resendBtn.className = 'listora-btn listora-btn--primary';
	resendBtn.textContent = 'Resend email';
	resendBtn.addEventListener( 'click', () => handleResend( resendBtn, response, statusEl ) );
	actions.appendChild( resendBtn );

	const editLink = document.createElement( 'a' );
	editLink.href = '#';
	editLink.className = 'listora-submission__verify-edit';
	editLink.textContent = 'Wrong email? Edit submission';
	editLink.addEventListener( 'click', ( ev ) => {
		ev.preventDefault();
		window.location.reload();
	} );
	actions.appendChild( editLink );

	card.appendChild( actions );

	const statusEl = document.createElement( 'p' );
	statusEl.className = 'listora-submission__verify-status';
	statusEl.setAttribute( 'role', 'status' );
	statusEl.hidden = true;
	card.appendChild( statusEl );

	card.hidden = false;
	card.scrollIntoView( { behavior: 'smooth', block: 'start' } );
}

/**
 * Handle the "Resend email" click — disabled for 60 seconds after each click.
 *
 * @param {HTMLButtonElement} btn      Resend button.
 * @param {Object}            response Server payload.
 * @param {HTMLElement}       statusEl Status message paragraph.
 */
function handleResend( btn, response, statusEl ) {
	if ( ! response || ! response.listing_id ) return;

	btn.disabled = true;
	const originalLabel = btn.textContent;
	btn.textContent = 'Sending…';
	if ( statusEl ) {
		statusEl.hidden = true;
	}

	abortableApiFetch( {
		path: '/listora/v1/submission/resend-verification',
		method: 'POST',
		data: {
			listing_id: response.listing_id,
			email: response.email || '',
		},
	} ).then( ( result ) => {
		if ( result && result.sent ) {
			if ( statusEl ) {
				statusEl.hidden = false;
				statusEl.textContent = 'A fresh verification email is on its way.';
			}
			btn.textContent = '✓ Sent';
			startResendCooldown( btn, originalLabel, 60 );
		} else if ( result && result.error === 'rate_limited' ) {
			const retry = result.retry_after || 60;
			if ( statusEl ) {
				statusEl.hidden = false;
				statusEl.textContent = 'Please wait ' + retry + ' seconds before requesting another email.';
			}
			btn.textContent = originalLabel;
			startResendCooldown( btn, originalLabel, retry );
		} else {
			if ( statusEl ) {
				statusEl.hidden = false;
				statusEl.textContent = 'Could not send the email. Please try again later.';
			}
			btn.disabled = false;
			btn.textContent = originalLabel;
		}
	} ).catch( ( err ) => {
		const code = err && err.code;
		const data = err && err.data;
		if ( isAbortError( err ) ) {
			if ( statusEl ) {
				statusEl.hidden = false;
				statusEl.textContent = NETWORK_SLOW_MESSAGE;
			}
			btn.disabled = false;
			btn.textContent = originalLabel;
		} else if ( code === 'rest_invalid_param' || ( data && data.error === 'rate_limited' ) ) {
			const retry = ( data && data.retry_after ) || 60;
			if ( statusEl ) {
				statusEl.hidden = false;
				statusEl.textContent = 'Please wait ' + retry + ' seconds before requesting another email.';
			}
			startResendCooldown( btn, originalLabel, retry );
		} else {
			if ( statusEl ) {
				statusEl.hidden = false;
				statusEl.textContent = ( err && err.message ) || 'Could not send the email. Please try again later.';
			}
			btn.disabled = false;
			btn.textContent = originalLabel;
		}
	} );
}

/**
 * Cooldown timer for the resend button.
 *
 * @param {HTMLButtonElement} btn      Button.
 * @param {string}            label    Original label.
 * @param {number}            seconds  Cooldown length in seconds.
 */
function startResendCooldown( btn, label, seconds ) {
	let remaining = Math.max( 1, parseInt( seconds, 10 ) || 60 );
	btn.disabled = true;
	const tick = () => {
		btn.textContent = label + ' (' + remaining + 's)';
		remaining -= 1;
		if ( remaining < 0 ) {
			btn.disabled = false;
			btn.textContent = label;
			return;
		}
		setTimeout( tick, 1000 );
	};
	tick();
}

