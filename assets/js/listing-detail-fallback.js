/**
 * Listing-Detail vanilla-JS fallback.
 *
 * Activates tab/gallery/share/favorite/modal-close behaviour when the
 * Interactivity API isn't available (custom templates, plugins disabling
 * IAPI, or pre-WP 6.5 environments). The IAPI store at
 * src/interactivity/store.js is the source of truth when present.
 *
 * Extracted from blocks/listing-detail/render.php inline <script> per
 * Pass 3 F1/F2 remediation (Foundation Cleanup 2026-05-11). Logic is
 * verbatim — the only addition is the DOMContentLoaded guard so the
 * script behaves identically whether enqueued in <head> or footer.
 */
( function () {
	function init() {
		var d = document.querySelector( '.listora-detail' );
		if ( ! d ) {
			return;
		}

		d.addEventListener( 'click', function ( e ) {
			var tab = e.target.closest( '.listora-detail__tab' );
			if ( tab ) {
				var tabId = ( tab.id || '' ).replace( 'tab-', '' );
				if ( ! tabId ) {
					return;
				}
				d.querySelectorAll( '.listora-detail__tab' ).forEach( function ( t ) {
					t.classList.remove( 'is-active' );
					t.setAttribute( 'aria-selected', 'false' );
				} );
				d.querySelectorAll( '.listora-detail__panel' ).forEach( function ( p ) {
					p.hidden = true;
				} );
				tab.classList.add( 'is-active' );
				tab.setAttribute( 'aria-selected', 'true' );
				var panel = d.querySelector( '#panel-' + tabId );
				if ( panel ) {
					panel.hidden = false;
				}
				if ( typeof history !== 'undefined' ) {
					history.replaceState( null, '', '#' + tabId );
				}
				return;
			}

			var thumb = e.target.closest( '.listora-detail__gallery-thumb' );
			if ( thumb ) {
				var img = d.querySelector( '.listora-detail__gallery-image' );
				// Prefer the large URL pre-emitted by the IAPI context; fall back
				// to the thumb's own src so non-standard thumbnail dimensions
				// don't trip up a regex that only knows "150x150" / "thumbnail".
				var fullSrc = '';
				var raw = thumb.getAttribute( 'data-wp-context' );
				if ( raw ) {
					try {
						var ctx = JSON.parse( raw );
						if ( ctx && ctx.imageSrc ) {
							fullSrc = ctx.imageSrc;
						}
					} catch ( _err ) {
						/* fall through */
					}
				}
				if ( ! fullSrc ) {
					var src = thumb.querySelector( 'img' );
					if ( src ) {
						fullSrc = src.getAttribute( 'data-full-src' ) || src.src;
					}
				}
				if ( img && fullSrc ) {
					img.src = fullSrc;
				}
				d.querySelectorAll( '.listora-detail__gallery-thumb' ).forEach( function ( t ) {
					t.classList.remove( 'is-active' );
				} );
				thumb.classList.add( 'is-active' );
				return;
			}

			// Share button.
			var shareBtn = e.target.closest( '[data-wp-on--click="actions.shareDialog"]' );
			if ( shareBtn ) {
				var title = d.querySelector( '.listora-detail__title' );
				var shareData = {
					title: title ? title.textContent : document.title,
					url: location.href,
				};
				if ( navigator.share ) {
					navigator.share( shareData );
				} else if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( location.href ).then( function () {
						if ( window.listoraToast ) {
							listoraToast(
								( window.listoraI18n && listoraI18n.linkCopied ) || 'Link copied!',
								{ type: 'success' }
							);
						}
					} );
				} else {
					var ta = document.createElement( 'textarea' );
					ta.value = location.href;
					ta.style.position = 'fixed';
					ta.style.opacity = '0';
					document.body.appendChild( ta );
					ta.select();
					document.execCommand( 'copy' );
					document.body.removeChild( ta );
					if ( window.listoraToast ) {
						listoraToast(
							( window.listoraI18n && listoraI18n.linkCopied ) || 'Link copied!',
							{ type: 'success' }
						);
					}
				}
				return;
			}

			// Favorite button.
			var favBtn = e.target.closest( '[data-wp-on--click="actions.toggleFavorite"]' );
			if ( favBtn ) {
				favBtn.classList.toggle( 'is-favorited' );
			}

			// Modal close (X button, backdrop, Cancel button) and Escape key.
			//
			// We deliberately do NOT remove the `is-open` class or set `modal.hidden`
			// in this fallback — those would only de-sync the IAPI store from the DOM.
			// The X / Cancel / backdrop elements all carry `data-wp-on--click="actions.closeModal"`,
			// so the Interactivity API itself flips `state.activeModal` to null and
			// drops the `is-open` class via the `data-wp-class--is-open` directive.
			// All this fallback needs to do is keep the body-level scroll-lock class
			// in sync, which IAPI doesn't manage. Basecamp 9842877199: previous
			// versions toggled `modal.hidden` here, which fought IAPI's class
			// directive and left the modal stuck closed on the second open.
		} );

		// ESC key — synthesise a click on the modal's close button so the IAPI
		// `closeModal` action runs. This routes through the same single source of
		// truth the X button uses, instead of mutating the DOM directly.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Escape' && e.keyCode !== 27 ) {
				return;
			}
			var openModal = d.querySelector( '.listora-detail__modal.is-open' );
			if ( ! openModal ) {
				return;
			}
			var closeBtn = openModal.querySelector( '.listora-detail__modal-close' );
			if ( closeBtn ) {
				closeBtn.click();
			}
			document.body.classList.remove( 'listora-modal-open' );
		} );

		var hash = location.hash.replace( '#', '' );
		if ( hash ) {
			var t = d.querySelector( '#tab-' + hash );
			if ( t ) {
				t.click();
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
