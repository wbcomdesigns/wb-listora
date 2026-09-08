/**
 * Media controls for the listing-fields meta boxes.
 *
 * The meta boxes reuse the frontend submission field renderer, whose media
 * controls are Interactivity API bindings (`data-wp-on--click`). wp-admin
 * never loads that store, so those buttons did nothing at all here: every
 * `file` custom field rendered an upload zone that could not be clicked, and
 * the gallery was skipped outright rather than fixed (BC 10272654379).
 *
 * wp.media is already enqueued on this screen, so binding it to the same
 * markup is all that was missing.
 *
 * @package WBListora\Admin
 */
( function ( $ ) {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.media ) {
		return;
	}

	var l10n = window.wbListoraMediaFields || {};

	/**
	 * Open the media frame.
	 *
	 * @param {Object}   options          Frame options.
	 * @param {string}   options.title    Frame title.
	 * @param {boolean}  options.multiple Whether to allow multi-select.
	 * @param {Function} options.onSelect Receives the selection as an array of attachment models.
	 */
	function openFrame( options ) {
		var frame = wp.media( {
			title: options.title,
			button: { text: l10n.useSelection || 'Use selection' },
			library: { type: 'image' },
			multiple: options.multiple ? 'add' : false,
		} );

		frame.on( 'select', function () {
			options.onSelect( frame.state().get( 'selection' ).toArray() );
		} );

		frame.open();
	}

	// ---------------------------------------------------------------------
	// Single file fields (e.g. Job → Company Logo).
	// ---------------------------------------------------------------------

	$( document ).on( 'click', '.listora-admin-fields .listora-submission__upload-trigger', function ( event ) {
		event.preventDefault();

		var $trigger = $( this );
		// The renderer prints the hidden input as the trigger's next sibling.
		var $input = $trigger.siblings( 'input[type="hidden"]' ).first();
		if ( ! $input.length ) {
			return;
		}

		openFrame( {
			title: l10n.selectFile || 'Select image',
			multiple: false,
			onSelect: function ( selection ) {
				var attachment = selection[ 0 ];
				if ( ! attachment ) {
					return;
				}

				var sizes = attachment.get( 'sizes' ) || {};
				var previewUrl = ( sizes.medium && sizes.medium.url ) || attachment.get( 'url' );

				$input.val( attachment.get( 'id' ) );
				$trigger.html(
					$( '<img />', {
						class: 'listora-submission__upload-preview',
						src: previewUrl,
						alt: '',
					} )
				);
			},
		} );
	} );

	// ---------------------------------------------------------------------
	// Gallery fields.
	// ---------------------------------------------------------------------

	/**
	 * Rewrite the hidden input from whatever thumbs are currently rendered.
	 *
	 * The DOM is the source of truth so that add and remove cannot disagree.
	 *
	 * @param {jQuery} $gallery The gallery wrapper.
	 */
	function syncGalleryInput( $gallery ) {
		var ids = $gallery
			.find( '.listora-admin-gallery__thumb' )
			.map( function () {
				return $( this ).data( 'attachment-id' );
			} )
			.get();

		$gallery.find( '[data-listora-gallery-input]' ).val( ids.join( ',' ) );
	}

	$( document ).on( 'click', '[data-listora-gallery-add]', function ( event ) {
		event.preventDefault();

		var $gallery = $( this ).closest( '[data-listora-admin-gallery]' );
		var $thumbs = $gallery.find( '[data-listora-gallery-thumbs]' );

		openFrame( {
			title: l10n.selectGallery || 'Add photos',
			multiple: true,
			onSelect: function ( selection ) {
				selection.forEach( function ( attachment ) {
					var id = attachment.get( 'id' );

					// Selecting the same image twice must not add it twice.
					if ( $thumbs.find( '[data-attachment-id="' + id + '"]' ).length ) {
						return;
					}

					var sizes = attachment.get( 'sizes' ) || {};
					var thumbUrl = ( sizes.thumbnail && sizes.thumbnail.url ) || attachment.get( 'url' );

					var $thumb = $( '<div />', {
						class: 'listora-admin-gallery__thumb',
						'data-attachment-id': id,
					} );
					$thumb.append( $( '<img />', { src: thumbUrl, alt: '' } ) );
					$thumb.append(
						$( '<button />', {
							type: 'button',
							class: 'listora-admin-gallery__remove',
							'data-listora-gallery-remove': id,
							'aria-label': l10n.removeImage || 'Remove gallery image',
							html: '&times;',
						} )
					);

					$thumbs.append( $thumb );
				} );

				syncGalleryInput( $gallery );
			},
		} );
	} );

	$( document ).on( 'click', '[data-listora-gallery-remove]', function ( event ) {
		event.preventDefault();

		var $gallery = $( this ).closest( '[data-listora-admin-gallery]' );
		$( this ).closest( '.listora-admin-gallery__thumb' ).remove();
		syncGalleryInput( $gallery );
	} );
} )( jQuery );
