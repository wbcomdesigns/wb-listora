/**
 * WB Listora Settings — Hash-based sidebar navigation.
 *
 * @package WBListora
 */
( function () {
	'use strict';

	const NAV_ITEM_SEL   = '.listora-settings-nav-item';
	const SECTION_SEL    = '.listora-settings-section';
	const ACTIVE_CLASS   = 'is-active';

	/**
	 * Activate a section by its ID (without the "section-" prefix).
	 *
	 * @param {string} sectionId Tab key, e.g. "general", "maps".
	 */
	function activateSection( sectionId ) {
		// De-activate all nav items and sections.
		document.querySelectorAll( NAV_ITEM_SEL ).forEach( function ( el ) {
			el.classList.remove( ACTIVE_CLASS );
		} );
		document.querySelectorAll( SECTION_SEL ).forEach( function ( el ) {
			el.classList.remove( ACTIVE_CLASS );
		} );

		// Activate the matching nav item and section.
		const navItem = document.querySelector( NAV_ITEM_SEL + '[data-section="' + sectionId + '"]' );
		const section = document.getElementById( 'section-' + sectionId );

		if ( navItem && section ) {
			navItem.classList.add( ACTIVE_CLASS );
			section.classList.add( ACTIVE_CLASS );
		} else {
			// Fallback: activate the first section.
			const firstNav     = document.querySelector( NAV_ITEM_SEL );
			const firstSection = document.querySelector( SECTION_SEL );
			if ( firstNav ) {
				firstNav.classList.add( ACTIVE_CLASS );
			}
			if ( firstSection ) {
				firstSection.classList.add( ACTIVE_CLASS );
			}
		}
	}

	/**
	 * Read hash from URL and return the section key.
	 *
	 * @return {string} Section key or empty string.
	 */
	function getHashSection() {
		var hash = window.location.hash.replace( '#', '' );
		return hash || '';
	}

	/**
	 * Update both `?tab=` and `#hash` so SSR (which keys off ?tab=)
	 * AND legacy hash-watchers stay in sync. Without updating the
	 * query, options.php's `wp_get_referer()` redirect-back lands
	 * on the *previous* tab's URL — exactly QA card 9856796225
	 * round 2: clicking Save on Visibility (after navigating in
	 * via JS-only hash change) lands on whatever ?tab= was on the
	 * URL at page-load time.
	 *
	 * @param {string} sectionId Tab key, e.g. "visibility".
	 */
	function pushTabUrl( sectionId ) {
		if ( ! sectionId || typeof window === 'undefined' ) {
			return;
		}
		try {
			var url = new URL( window.location.href );
			url.searchParams.set( 'tab', sectionId );
			url.hash = sectionId;
			window.history.replaceState( null, '', url.toString() );
		} catch ( e ) {
			// Older browsers without URL constructor — fall back to hash only.
			window.location.hash = sectionId;
		}
	}

	/**
	 * Initialise nav click handlers.
	 */
	function initNav() {
		document.querySelectorAll( NAV_ITEM_SEL ).forEach( function ( item ) {
			item.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var sectionId = this.getAttribute( 'data-section' );
				if ( sectionId ) {
					pushTabUrl( sectionId );
					activateSection( sectionId );
				}
			} );
		} );
	}

	/**
	 * Preserve hash on form submit by injecting a hidden field
	 * that stores the current section, so after save we redirect back.
	 *
	 * Also rewrites the `_wp_http_referer` query string to point at
	 * the active tab — when settings-nav.js's click handler updated
	 * the URL via replaceState in this same page-load, the form's
	 * pre-rendered referer still carries the tab the user originally
	 * landed on. options.php's wp_get_referer() reads from the form
	 * field, not window.location, so without this rewrite the post-
	 * save redirect lands on the wrong tab (QA card 9856796225 round 2).
	 */
	function preserveHashOnSubmit() {
		document.querySelectorAll( '.listora-settings-section form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function () {
				var hash = getHashSection();
				if ( ! hash ) {
					return;
				}

				var input = document.createElement( 'input' );
				input.type  = 'hidden';
				input.name  = '_wp_http_referer_hash';
				input.value = hash;
				form.appendChild( input );

				// Rewrite EVERY `_wp_http_referer` input in the form, not
				// just the first. The Visibility section's form ships
				// with two of them (Free's settings_fields() emits one,
				// Pro's render_visibility_settings adds a second via
				// wp_nonce_field inside the same form). PHP's
				// $_REQUEST last-wins on duplicate names, so rewriting
				// only the first input let the second one's stale
				// page-load URL win — exactly why QA card 9856796225
				// kept reverting Visibility to whatever tab the user
				// landed on at page load.
				var referers = form.querySelectorAll( 'input[name="_wp_http_referer"]' );
				referers.forEach( function ( referer ) {
					try {
						var rUrl = new URL( referer.value, window.location.origin );
						rUrl.searchParams.set( 'tab', hash );
						rUrl.hash = hash;
						referer.value = rUrl.pathname + rUrl.search + rUrl.hash;
					} catch ( e ) {
						// Fallback: bare hash append, like before.
						referer.value = referer.value.replace( /#.*$/, '' ) + '#' + hash;
					}
				} );
			} );
		} );
	}

	/**
	 * Listen for hash changes (browser back/forward).
	 */
	function initHashChange() {
		window.addEventListener( 'hashchange', function () {
			var section = getHashSection();
			if ( section ) {
				activateSection( section );
			}
		} );
	}

	/**
	 * Boot.
	 */
	function init() {
		initNav();
		preserveHashOnSubmit();
		initHashChange();

		// Honor a hash on first load if one is present (deep link / save
		// redirect). When the URL has no hash, leave whatever PHP set as
		// `.is-active` alone — the server already activates the right pane
		// from `?tab=` query, and we'd be overriding the no-JS fallback for
		// no reason if we forced the first section here.
		var hash = getHashSection();
		if ( hash ) {
			activateSection( hash );
		}
	}

	// Run on DOMContentLoaded.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Initialise Lucide icons. The previous implementation was a single
	// `if (window.lucide) createIcons else DOMContentLoaded listener`
	// pair — both branches missed the "lucide loaded after both this
	// script and DOMContentLoaded had already fired" race that QA hit
	// on a fresh WP install (no opcode cache, scripts deferred). Result:
	// the placeholder `<i data-lucide="...">` tags stayed as bare empty
	// nodes, so the sidebar nav, save buttons, and section icons all
	// looked missing while the rest of the page rendered fine.
	function initLucide() {
		if ( window.lucide && typeof window.lucide.createIcons === 'function' ) {
			window.lucide.createIcons();
			return true;
		}
		return false;
	}

	if ( ! initLucide() ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', initLucide );
		}
		// Poll for up to 5s so a late-loading lucide script (CDN
		// fallback, cache miss) still gets applied without a refresh.
		var attempts = 0;
		var poller   = setInterval( function () {
			if ( initLucide() || ++attempts > 50 ) {
				clearInterval( poller );
			}
		}, 100 );
	}
} )();
