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
	 * Initialise nav click handlers.
	 */
	function initNav() {
		document.querySelectorAll( NAV_ITEM_SEL ).forEach( function ( item ) {
			item.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var sectionId = this.getAttribute( 'data-section' );
				if ( sectionId ) {
					window.location.hash = sectionId;
					activateSection( sectionId );
				}
			} );
		} );
	}

	/**
	 * Preserve hash on form submit by injecting a hidden field
	 * that stores the current section, so after save we redirect back.
	 */
	function preserveHashOnSubmit() {
		document.querySelectorAll( '.listora-settings-section form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function () {
				var hash = getHashSection();
				if ( hash ) {
					var input  = document.createElement( 'input' );
					input.type  = 'hidden';
					input.name  = '_wp_http_referer_hash';
					input.value = hash;

					// Append hash to the _wp_http_referer value so WP redirects correctly.
					var referer = form.querySelector( 'input[name="_wp_http_referer"]' );
					if ( referer ) {
						// Strip any existing hash, then append ours.
						referer.value = referer.value.replace( /#.*$/, '' ) + '#' + hash;
					}

					form.appendChild( input );
				}
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
