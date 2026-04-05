/**
 * Taxonomy Fields — media uploader, form reset, and Lucide icon picker.
 *
 * @package WBListora
 */
( function( $ ) {
	'use strict';

	var frame;

	/* ─── Icon Picker ─── */

	var ICONS_PER_PAGE = 100;

	/**
	 * Create an SVG element for a Lucide icon by name.
	 *
	 * @param {string} name Lucide icon name (kebab-case).
	 * @return {Element|null} SVG element or null if not found.
	 */
	function createIconSvg( name ) {
		if ( ! window.lucide || ! window.lucide.icons || ! window.lucide.icons[ name ] ) {
			return null;
		}

		var iconData = window.lucide.icons[ name ];
		var svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );

		svg.setAttribute( 'xmlns', 'http://www.w3.org/2000/svg' );
		svg.setAttribute( 'width', '24' );
		svg.setAttribute( 'height', '24' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'fill', 'none' );
		svg.setAttribute( 'stroke', 'currentColor' );
		svg.setAttribute( 'stroke-width', '2' );
		svg.setAttribute( 'stroke-linecap', 'round' );
		svg.setAttribute( 'stroke-linejoin', 'round' );

		// iconData is [attrs, children] — children is an array of [tag, attrs].
		var children = iconData[ 1 ] || iconData;

		if ( Array.isArray( children ) ) {
			for ( var i = 0; i < children.length; i++ ) {
				var child = children[ i ];

				if ( ! Array.isArray( child ) || child.length < 2 ) {
					continue;
				}

				var tag   = child[ 0 ];
				var attrs = child[ 1 ];
				var el    = document.createElementNS( 'http://www.w3.org/2000/svg', tag );

				if ( attrs && typeof attrs === 'object' ) {
					for ( var key in attrs ) {
						if ( attrs.hasOwnProperty( key ) ) {
							el.setAttribute( key, attrs[ key ] );
						}
					}
				}

				svg.appendChild( el );
			}
		}

		return svg;
	}

	/**
	 * Get all Lucide icon names sorted alphabetically.
	 *
	 * @return {string[]}
	 */
	function getAllIconNames() {
		if ( ! window.lucide || ! window.lucide.icons ) {
			return [];
		}

		return Object.keys( window.lucide.icons ).sort();
	}

	/**
	 * Initialise an icon picker instance.
	 *
	 * @param {HTMLElement} container The .listora-icon-picker element.
	 */
	function initIconPicker( container ) {
		var $container = $( container );
		var $input     = $container.find( 'input[type="hidden"]' );
		var $toggle    = $container.find( '.listora-icon-picker__toggle' );
		var $clear     = $container.find( '.listora-icon-picker__clear' );
		var $preview   = $container.find( '.listora-icon-picker__preview' );
		var $dropdown  = $container.find( '.listora-icon-picker__dropdown' );
		var $search    = $container.find( '.listora-icon-picker__search' );
		var $grid      = $container.find( '.listora-icon-picker__grid' );

		var allNames     = getAllIconNames();
		var currentValue = $input.val() || '';
		var visibleCount = ICONS_PER_PAGE;
		var searchTerm   = '';

		/**
		 * Render the preview area with the currently selected icon.
		 */
		function renderPreview() {
			$preview.empty();

			if ( ! currentValue ) {
				$clear.hide();
				return;
			}

			var svg = createIconSvg( currentValue );

			if ( svg ) {
				$preview.append( svg );
			}

			$clear.show();
		}

		/**
		 * Filter icons by search term.
		 *
		 * @return {string[]}
		 */
		function getFilteredNames() {
			if ( ! searchTerm ) {
				return allNames;
			}

			var term = searchTerm.toLowerCase();

			return allNames.filter( function( name ) {
				return name.indexOf( term ) !== -1;
			} );
		}

		/**
		 * Render the icon grid.
		 */
		function renderGrid() {
			$grid.empty();

			var filtered = getFilteredNames();
			var limit    = Math.min( visibleCount, filtered.length );

			for ( var i = 0; i < limit; i++ ) {
				var name = filtered[ i ];
				var btn  = document.createElement( 'button' );

				btn.type      = 'button';
				btn.className = 'listora-icon-picker__item';
				btn.setAttribute( 'data-icon', name );

				if ( name === currentValue ) {
					btn.className += ' is-selected';
				}

				var svg = createIconSvg( name );

				if ( svg ) {
					btn.appendChild( svg );
				}

				var span       = document.createElement( 'span' );
				span.className = 'listora-icon-picker__item-name';
				span.textContent = name;
				btn.appendChild( span );

				$grid.append( btn );
			}

			// Show "Load more" button if there are more icons.
			if ( limit < filtered.length ) {
				var more       = document.createElement( 'button' );
				more.type      = 'button';
				more.className = 'listora-icon-picker__more';
				more.textContent = 'Show more (' + ( filtered.length - limit ) + ' remaining)';
				$grid.append( more );
			}
		}

		/**
		 * Select an icon by name.
		 *
		 * @param {string} name
		 */
		function selectIcon( name ) {
			currentValue = name;
			$input.val( name );
			renderPreview();

			// Update selection state in grid.
			$grid.find( '.listora-icon-picker__item' ).removeClass( 'is-selected' );
			$grid.find( '[data-icon="' + name + '"]' ).addClass( 'is-selected' );

			closeDropdown();
		}

		/**
		 * Clear the selection.
		 */
		function clearIcon() {
			currentValue = '';
			$input.val( '' );
			renderPreview();
			$grid.find( '.listora-icon-picker__item' ).removeClass( 'is-selected' );
		}

		/**
		 * Open the dropdown.
		 */
		function openDropdown() {
			visibleCount = ICONS_PER_PAGE;
			searchTerm   = '';
			$search.val( '' );
			renderGrid();
			$dropdown.show();
			$search.trigger( 'focus' );
		}

		/**
		 * Close the dropdown.
		 */
		function closeDropdown() {
			$dropdown.hide();
		}

		// --- Event bindings ---

		$toggle.on( 'click', function( e ) {
			e.preventDefault();

			if ( $dropdown.is( ':visible' ) ) {
				closeDropdown();
			} else {
				openDropdown();
			}
		} );

		$clear.on( 'click', function( e ) {
			e.preventDefault();
			clearIcon();
		} );

		// Search filtering with debounce.
		var searchTimer;

		$search.on( 'input', function() {
			clearTimeout( searchTimer );
			searchTimer = setTimeout( function() {
				searchTerm   = $search.val().trim();
				visibleCount = ICONS_PER_PAGE;
				renderGrid();
			}, 150 );
		} );

		// Icon selection.
		$grid.on( 'click', '.listora-icon-picker__item', function( e ) {
			e.preventDefault();
			var name = $( this ).attr( 'data-icon' );

			if ( name ) {
				selectIcon( name );
			}
		} );

		// "Show more" button.
		$grid.on( 'click', '.listora-icon-picker__more', function( e ) {
			e.preventDefault();
			visibleCount += ICONS_PER_PAGE;
			renderGrid();
		} );

		// Close on click outside.
		$( document ).on( 'mousedown', function( e ) {
			if ( $dropdown.is( ':visible' ) && ! $( e.target ).closest( container ).length ) {
				closeDropdown();
			}
		} );

		// Render initial preview if a value is set.
		renderPreview();
	}

	/* ─── Initialise all icon pickers on the page ─── */

	$( document ).ready( function() {
		$( '[data-listora-icon-picker]' ).each( function() {
			initIconPicker( this );
		} );
	} );

	/* ─── Media Uploader ─── */

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

	/* ─── AJAX Reset ─── */

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
			// Reset icon picker.
			$( '[data-listora-icon-picker]' ).each( function() {
				var $picker = $( this );
				$picker.find( 'input[type="hidden"]' ).val( '' );
				$picker.find( '.listora-icon-picker__preview' ).empty();
				$picker.find( '.listora-icon-picker__clear' ).hide();
				$picker.find( '.listora-icon-picker__dropdown' ).hide();
				$picker.find( '.listora-icon-picker__item' ).removeClass( 'is-selected' );
			} );

			// Reset image & color fields.
			$( '#listora-image' ).val( '' );
			$( '#listora-image-preview' ).html( '' );
			$( '.listora-remove-image' ).hide();
			$( '#listora-color' ).val( '#3B82F6' );

			// Reset frame for fresh selection.
			frame = null;
		}
	} );
}( jQuery ) );
