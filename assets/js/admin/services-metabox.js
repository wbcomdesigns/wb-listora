/**
 * Services meta-box — photo upload handlers.
 *
 * Card 9872014083 — adds a click-to-pick-from-WP-media-library affordance
 * to each service row in the Services meta box on the listora_listing
 * post-edit screen.
 *
 * Delegated listeners on document so dynamically-rendered rows (none today,
 * but any future "Add another service" UI) work without re-binding.
 *
 * Depends on `wp.media` (enqueued by `wp_enqueue_media()` in the metabox
 * class's `enqueue_assets` method) and the localized `listoraServicesMetabox`
 * object for translatable strings.
 */
( function () {
	'use strict';

	if ( typeof window === 'undefined' || ! document ) {
		return;
	}

	var i18n = ( window.listoraServicesMetabox || {} );

	function findRow( target ) {
		return target && target.closest ? target.closest( '.wb-listora-services-metabox__photo' ) : null;
	}

	function setRowImage( row, attachment ) {
		var preview = row.querySelector( '[data-listora-svc-preview]' );
		var input   = row.querySelector( '[data-listora-svc-input]' );
		var remove  = row.querySelector( '[data-listora-svc-remove]' );
		var choose  = row.querySelector( '[data-listora-svc-choose]' );
		if ( ! preview || ! input ) return;

		// Build / update the <img> element.
		var img = preview.querySelector( '[data-listora-svc-img]' );
		var emptyMark = preview.querySelector( '[data-listora-svc-empty]' );
		var url = '';
		if ( attachment && attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url ) {
			url = attachment.sizes.thumbnail.url;
		} else if ( attachment && attachment.url ) {
			url = attachment.url;
		}

		if ( ! img ) {
			img = document.createElement( 'img' );
			img.setAttribute( 'data-listora-svc-img', '1' );
			img.setAttribute( 'alt', '' );
			img.style.width = '100%';
			img.style.height = '100%';
			img.style.objectFit = 'cover';
			preview.appendChild( img );
		}
		img.src = url;
		if ( emptyMark ) emptyMark.style.display = 'none';

		input.value = attachment && attachment.id ? String( attachment.id ) : '';
		if ( remove ) remove.style.display = '';
		if ( choose ) choose.textContent = ( i18n.changeLabel || 'Change' );
	}

	function clearRowImage( row ) {
		var preview = row.querySelector( '[data-listora-svc-preview]' );
		var input   = row.querySelector( '[data-listora-svc-input]' );
		var remove  = row.querySelector( '[data-listora-svc-remove]' );
		var choose  = row.querySelector( '[data-listora-svc-choose]' );

		if ( preview ) {
			var img = preview.querySelector( '[data-listora-svc-img]' );
			if ( img ) img.remove();
			var emptyMark = preview.querySelector( '[data-listora-svc-empty]' );
			if ( ! emptyMark ) {
				emptyMark = document.createElement( 'span' );
				emptyMark.setAttribute( 'data-listora-svc-empty', '1' );
				emptyMark.style.color = '#a7aaad';
				emptyMark.style.fontSize = '11px';
				emptyMark.style.textAlign = 'center';
				emptyMark.textContent = '—';
				preview.appendChild( emptyMark );
			} else {
				emptyMark.style.display = '';
			}
		}
		if ( input ) input.value = '';
		if ( remove ) remove.style.display = 'none';
		if ( choose ) choose.textContent = 'Choose';
	}

	function openMediaFor( row ) {
		if ( ! window.wp || ! window.wp.media ) {
			// `wp_enqueue_media()` should have run on this screen — log if not.
			if ( window.console && window.console.warn ) {
				window.console.warn( 'listora services metabox: wp.media is unavailable.' );
			}
			return;
		}

		var frame = window.wp.media( {
			title:    i18n.frameTitle  || 'Choose service photo',
			button:   { text: i18n.frameButton || 'Use this photo' },
			multiple: false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first();
			if ( att ) {
				setRowImage( row, att.toJSON() );
			}
		} );

		frame.open();
	}

	document.addEventListener( 'click', function ( ev ) {
		var t = ev.target;
		if ( ! t ) return;

		// Choose / Change
		var choose = t.closest && t.closest( '[data-listora-svc-choose]' );
		if ( choose ) {
			ev.preventDefault();
			var row = findRow( choose );
			if ( row ) openMediaFor( row );
			return;
		}

		// Remove
		var remove = t.closest && t.closest( '[data-listora-svc-remove]' );
		if ( remove ) {
			ev.preventDefault();
			var row2 = findRow( remove );
			if ( row2 ) clearRowImage( row2 );
		}
	} );
} )();
