/**
 * Listing Submission — Interactivity API view module.
 *
 * Handles multi-step navigation, form validation, draft saving, and submission.
 *
 * @package WBListora
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import '../../interactivity/store.js';

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

			// 1) Hydrate the category dropdown from REST if we haven't yet.
			const categorySelect = container.querySelector( '[name="category"]' );
			if ( categorySelect && categorySelect.options.length <= 1 ) {
				window.wp.apiFetch( {
					path: `/listora/v1/listing-types/${ slug }/categories`,
				} )
					.then( ( categories ) => {
						if ( ! Array.isArray( categories ) ) return;
						categories.forEach( ( cat ) => {
							const opt = document.createElement( 'option' );
							opt.value = cat.id;
							opt.textContent = cat.name;
							categorySelect.appendChild( opt );
						} );
					} )
					.catch( () => {
						// Silently fail — user can still type Category manually if the field supports it.
					} );
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
				const response = await window.wp.apiFetch( {
					path: '/listora/v1/submit',
					method: 'POST',
					body: formData,
				} );

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
 * Shared media-upload handler.
 *
 * Used by both the Interactivity API `openMediaUpload` action and the
 * delegated DOM-level fallback listener. This is the single source of
 * truth for opening the WP media frame and wiring its `select` callback.
 */
function openMediaForTarget( target ) {
	if ( ! target || typeof wp === 'undefined' || ! wp.media ) {
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
				img.classList.add( 'listora-submission__media-preview' );
				zone.appendChild( img );
			}
		}
	} );

	frame.open();
}

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
		// Generic `display: none` from custom JS or CSS rules.
		if ( field.offsetParent === null && getComputedStyle( field ).display === 'none' ) {
			return false;
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
			errorEl.textContent =
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

	preview.textContent = '';

	// Header: title + category badge.
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

	// Description (full, but truncated for the preview blurb).
	const desc = formEl.querySelector( '[name="description"]' )?.value?.trim() || '';
	if ( desc ) {
		const p = document.createElement( 'p' );
		p.classList.add( 'listora-submission__preview-desc' );
		p.textContent = desc.length > 200 ? desc.substring( 0, 200 ) + '…' : desc;
		preview.appendChild( p );
	}

	// All other visible fields, rendered as a key/value list.
	const list = document.createElement( 'dl' );
	list.classList.add( 'listora-submission__preview-list' );

	const skipNames = new Set( [
		'title', 'description', 'category', 'listing_id',
		'listora_hp_field', 'listora_nonce', 'gallery',
	] );

	const seenLabels = new Set();

	formEl.querySelectorAll( 'input[name], select[name], textarea[name]' ).forEach( ( field ) => {
		const name = field.name;
		if ( ! name || skipNames.has( name ) ) return;
		if ( field.type === 'hidden' ) return;
		if ( field.type === 'file' ) return;
		if ( name.startsWith( 'notification_prefs' ) ) return;
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
	} );

	if ( list.children.length > 0 ) {
		preview.appendChild( list );
	}
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
 * Add a gallery thumbnail using safe DOM methods.
 */
function addGalleryThumb( attachment ) {
	const thumbs = document.querySelector( '#listora-gallery-thumbs' );
	if ( ! thumbs ) return;

	const url = attachment.sizes?.thumbnail?.url || attachment.url;
	const div = document.createElement( 'div' );
	div.classList.add( 'listora-submission__gallery-thumb' );

	const img = document.createElement( 'img' );
	img.src = url;
	img.alt = '';
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
	} );
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

	window.wp.apiFetch( {
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
		if ( code === 'rest_invalid_param' || ( data && data.error === 'rate_limited' ) ) {
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

