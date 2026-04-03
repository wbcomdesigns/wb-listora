/**
 * Taxonomy Fields — media uploader and form reset for term meta fields.
 *
 * @package WBListora
 */
( function( $ ) {
	'use strict';

	var frame;

	/**
	 * Open the media frame and set the selected image.
	 */
	$( document ).on( 'click', '.listora-upload-image', function( e ) {
		e.preventDefault();

		var $button = $( this );

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: $button.data( 'title' ) || 'Select Image',
			button: { text: 'Use Image' },
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', function() {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var thumbUrl   = attachment.sizes && attachment.sizes.thumbnail
				? attachment.sizes.thumbnail.url
				: attachment.url;

			$( '#listora-image' ).val( attachment.id );
			$( '#listora-image-preview' ).html(
				'<img src="' + thumbUrl + '" style="max-width:150px;height:auto;display:block;margin-bottom:8px;" />'
			);
			$( '.listora-remove-image' ).show();
		} );

		frame.open();
	} );

	/**
	 * Remove the selected image.
	 */
	$( document ).on( 'click', '.listora-remove-image', function( e ) {
		e.preventDefault();
		$( '#listora-image' ).val( '' );
		$( '#listora-image-preview' ).html( '' );
		$( this ).hide();

		// Reset media frame so a fresh one is created next time.
		frame = null;
	} );

	/**
	 * Reset fields after a new term is added via AJAX (Add New Term form).
	 */
	$( document ).ajaxComplete( function( event, xhr, settings ) {
		if (
			settings.data &&
			typeof settings.data === 'string' &&
			settings.data.indexOf( 'action=add-tag' ) !== -1 &&
			(
				settings.data.indexOf( 'taxonomy=listora_listing_cat' ) !== -1 ||
				settings.data.indexOf( 'taxonomy=listora_listing_feature' ) !== -1
			)
		) {
			$( '#listora-icon' ).val( '' );
			$( '#listora-image' ).val( '' );
			$( '#listora-image-preview' ).html( '' );
			$( '.listora-remove-image' ).hide();
			$( '#listora-color' ).val( '#3B82F6' );

			// Reset frame for fresh selection.
			frame = null;
		}
	} );
}( jQuery ) );
