/**
 * Confirmation modal for WB Listora.
 *
 * Promise-based replacement for window.confirm().
 *
 * Usage:
 *   listoraConfirm( 'Delete this field?' ).then( function ( ok ) {
 *     if ( ok ) { ... }
 *   } );
 *
 *   listoraConfirm( {
 *     title: 'Delete listing?',
 *     message: 'This cannot be undone.',
 *     confirmLabel: 'Delete',
 *     cancelLabel: 'Keep',
 *     tone: 'danger',
 *   } ).then( function ( ok ) { ... } );
 *
 * @package WBListora
 */
( function () {
	'use strict';

	if ( window.listoraConfirm ) {
		return;
	}

	var FOCUSABLE = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

	function normalize( opts ) {
		if ( typeof opts === 'string' ) {
			opts = { message: opts };
		}
		opts = opts || {};
		return {
			title:        opts.title || '',
			message:      opts.message || '',
			confirmLabel: opts.confirmLabel || 'Confirm',
			cancelLabel:  opts.cancelLabel || 'Cancel',
			tone:         opts.tone === 'danger' ? 'danger' : 'primary',
		};
	}

	function buildModal( o ) {
		var overlay = document.createElement( 'div' );
		overlay.className = 'listora-confirm-overlay';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );

		var dialog = document.createElement( 'div' );
		dialog.className = 'listora-confirm';
		dialog.setAttribute( 'tabindex', '-1' );

		if ( o.title ) {
			var titleId = 'listora-confirm-title-' + Date.now();
			var h = document.createElement( 'h2' );
			h.className = 'listora-confirm__title';
			h.id = titleId;
			h.textContent = o.title;
			dialog.appendChild( h );
			overlay.setAttribute( 'aria-labelledby', titleId );
		}

		if ( o.message ) {
			var msgId = 'listora-confirm-msg-' + Date.now();
			var p = document.createElement( 'p' );
			p.className = 'listora-confirm__message';
			p.id = msgId;
			p.textContent = o.message;
			dialog.appendChild( p );
			overlay.setAttribute( 'aria-describedby', msgId );
		}

		var actions = document.createElement( 'div' );
		actions.className = 'listora-confirm__actions';

		var cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'listora-confirm__btn listora-confirm__btn--cancel';
		cancel.textContent = o.cancelLabel;

		var confirm = document.createElement( 'button' );
		confirm.type = 'button';
		confirm.className = 'listora-confirm__btn listora-confirm__btn--' + o.tone;
		confirm.textContent = o.confirmLabel;

		actions.appendChild( cancel );
		actions.appendChild( confirm );
		dialog.appendChild( actions );
		overlay.appendChild( dialog );

		return { overlay: overlay, dialog: dialog, confirm: confirm, cancel: cancel };
	}

	window.listoraConfirm = function ( opts ) {
		var o = normalize( opts );

		return new Promise( function ( resolve ) {
			var parts = buildModal( o );
			var prevFocus = document.activeElement;

			function close( result ) {
				document.removeEventListener( 'keydown', onKey, true );
				parts.overlay.classList.remove( 'is-visible' );
				setTimeout( function () {
					if ( parts.overlay.parentNode ) {
						parts.overlay.parentNode.removeChild( parts.overlay );
					}
					if ( prevFocus && typeof prevFocus.focus === 'function' ) {
						prevFocus.focus();
					}
					resolve( result );
				}, 150 );
			}

			function onKey( e ) {
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					close( false );
					return;
				}
				if ( e.key === 'Tab' ) {
					var nodes = parts.dialog.querySelectorAll( FOCUSABLE );
					if ( ! nodes.length ) {
						return;
					}
					var first = nodes[ 0 ];
					var last  = nodes[ nodes.length - 1 ];
					if ( e.shiftKey && document.activeElement === first ) {
						e.preventDefault();
						last.focus();
					} else if ( ! e.shiftKey && document.activeElement === last ) {
						e.preventDefault();
						first.focus();
					}
				}
			}

			parts.overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === parts.overlay ) {
					close( false );
				}
			} );
			parts.cancel.addEventListener( 'click', function () {
				close( false );
			} );
			parts.confirm.addEventListener( 'click', function () {
				close( true );
			} );
			document.addEventListener( 'keydown', onKey, true );

			document.body.appendChild( parts.overlay );

			// Trigger transition.
			setTimeout( function () {
				parts.overlay.classList.add( 'is-visible' );
				parts.confirm.focus();
			}, 10 );
		} );
	};
} )();
