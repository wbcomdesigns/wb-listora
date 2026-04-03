/**
 * WB Listora -- Type Editor & Field Builder
 *
 * Vanilla JS (no jQuery, no React). Renders field groups and fields,
 * handles inline editing, add/remove, and save via REST API.
 *
 * All innerHTML usage contains only static markup (Lucide icon tags),
 * never user-supplied content.
 *
 * @package WBListora
 */
( function () {
	'use strict';

	var builder = document.getElementById( 'listora-field-builder' );
	if ( ! builder ) {
		initListView();
		return;
	}

	// ── State ──
	var fieldGroups = JSON.parse( builder.dataset.fieldGroups || '[]' );
	var fieldTypes  = JSON.parse( builder.dataset.fieldTypes || '{}' );
	var typeSlug    = builder.dataset.typeSlug || '';
	var isNew       = ! typeSlug;

	// Category labels for the field type picker.
	var categoryLabels = {
		basic:      'Basic',
		choice:     'Choice',
		datetime:   'Date & Time',
		money:      'Money',
		media:      'Media',
		location:   'Location',
		structured: 'Structured',
		display:    'Display',
		custom:     'Custom'
	};

	// ── Slug generation ──
	var nameInput = document.getElementById( 'listora-type-name' );
	var slugInput = document.getElementById( 'listora-type-slug' );

	if ( nameInput && slugInput && isNew ) {
		nameInput.addEventListener( 'input', function () {
			slugInput.value = toSlug( nameInput.value );
		} );
	}

	function toSlug( str ) {
		return str
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}

	// ── Render ──
	render();

	function render() {
		builder.textContent = '';

		// Render each group.
		fieldGroups.forEach( function ( group, gIdx ) {
			builder.appendChild( renderGroup( group, gIdx ) );
		} );

		// Add group button.
		var addGroupBtn = el( 'button', {
			type: 'button',
			className: 'listora-btn listora-btn--full listora-add-group-btn'
		} );
		addGroupBtn.appendChild( lucideIcon( 'plus' ) );
		addGroupBtn.appendChild( document.createTextNode( ' Add Field Group' ) );
		addGroupBtn.addEventListener( 'click', function () {
			showAddGroupPanel();
		} );
		builder.appendChild( addGroupBtn );

		refreshIcons();
	}

	function renderGroup( group, gIdx ) {
		var collapsed = group._collapsed || false;

		var card = el( 'div', {
			className: 'listora-field-group' + ( collapsed ? ' is-collapsed' : '' ),
			'data-group-index': gIdx
		} );

		// Group header.
		var header = el( 'div', { className: 'listora-field-group__header' } );

		var dragHandle = el( 'span', { className: 'listora-drag-handle', title: 'Drag to reorder' } );
		dragHandle.textContent = '\u2261';
		header.appendChild( dragHandle );

		var titleWrap = el( 'div', { className: 'listora-field-group__title-wrap' } );
		var title = el( 'span', { className: 'listora-field-group__title' } );
		title.textContent = group.label || 'Untitled Group';
		titleWrap.appendChild( title );

		var countBadge = el( 'span', { className: 'listora-badge listora-badge--muted' } );
		countBadge.textContent = ( group.fields ? group.fields.length : 0 ) + ' fields';
		titleWrap.appendChild( countBadge );

		header.appendChild( titleWrap );

		var headerActions = el( 'div', { className: 'listora-field-group__actions' } );

		var collapseBtn = el( 'button', { type: 'button', className: 'listora-icon-btn', title: 'Toggle' } );
		collapseBtn.appendChild( lucideIcon( collapsed ? 'chevron-down' : 'chevron-up' ) );
		collapseBtn.addEventListener( 'click', function () {
			group._collapsed = ! group._collapsed;
			render();
		} );
		headerActions.appendChild( collapseBtn );

		var deleteGroupBtn = el( 'button', { type: 'button', className: 'listora-icon-btn listora-icon-btn--danger', title: 'Delete group' } );
		deleteGroupBtn.appendChild( lucideIcon( 'trash-2' ) );
		deleteGroupBtn.addEventListener( 'click', function () {
			if ( confirm( 'Delete this field group and all its fields?' ) ) {
				fieldGroups.splice( gIdx, 1 );
				render();
			}
		} );
		headerActions.appendChild( deleteGroupBtn );

		header.appendChild( headerActions );
		card.appendChild( header );

		// Group body.
		if ( ! collapsed ) {
			var body = el( 'div', { className: 'listora-field-group__body' } );

			if ( group.fields && group.fields.length > 0 ) {
				group.fields.forEach( function ( field, fIdx ) {
					body.appendChild( renderField( field, gIdx, fIdx ) );
				} );
			} else {
				var empty = el( 'p', { className: 'listora-text-muted listora-field-group__empty' } );
				empty.textContent = 'No fields yet. Click "Add Field" below.';
				body.appendChild( empty );
			}

			// Add field button.
			var addFieldBtn = el( 'button', {
				type: 'button',
				className: 'listora-btn listora-btn--sm listora-add-field-btn'
			} );
			addFieldBtn.appendChild( lucideIcon( 'plus' ) );
			addFieldBtn.appendChild( document.createTextNode( ' Add Field' ) );
			addFieldBtn.addEventListener( 'click', function () {
				showFieldTypePicker( gIdx );
			} );
			body.appendChild( addFieldBtn );

			card.appendChild( body );
		}

		return card;
	}

	function renderField( field, gIdx, fIdx ) {
		var expanded = field._expanded || false;

		var row = el( 'div', {
			className: 'listora-field-row' + ( expanded ? ' is-expanded' : '' )
		} );

		// Field summary row.
		var summary = el( 'div', { className: 'listora-field-row__summary' } );

		var handle = el( 'span', { className: 'listora-drag-handle' } );
		handle.textContent = '\u2261';
		summary.appendChild( handle );

		var label = el( 'span', { className: 'listora-field-row__label' } );
		label.textContent = field.label || 'Untitled';
		summary.appendChild( label );

		var typeBadge = el( 'span', { className: 'listora-badge listora-badge--default' } );
		var typeInfo = fieldTypes[ field.type ];
		typeBadge.textContent = typeInfo ? typeInfo.label : field.type;
		summary.appendChild( typeBadge );

		var keySpan = el( 'span', { className: 'listora-field-row__key' } );
		keySpan.textContent = field.key || '';
		summary.appendChild( keySpan );

		var actions = el( 'div', { className: 'listora-field-row__actions' } );

		var editBtn = el( 'button', { type: 'button', className: 'listora-icon-btn', title: 'Edit field' } );
		editBtn.appendChild( lucideIcon( expanded ? 'chevron-up' : 'pencil' ) );
		editBtn.addEventListener( 'click', function () {
			field._expanded = ! field._expanded;
			render();
		} );
		actions.appendChild( editBtn );

		var delBtn = el( 'button', { type: 'button', className: 'listora-icon-btn listora-icon-btn--danger', title: 'Delete field' } );
		delBtn.appendChild( lucideIcon( 'trash-2' ) );
		delBtn.addEventListener( 'click', function () {
			if ( confirm( 'Delete this field?' ) ) {
				fieldGroups[ gIdx ].fields.splice( fIdx, 1 );
				render();
			}
		} );
		actions.appendChild( delBtn );

		summary.appendChild( actions );
		row.appendChild( summary );

		// Inline editor.
		if ( expanded ) {
			row.appendChild( renderFieldEditor( field, gIdx, fIdx ) );
		}

		return row;
	}

	function renderFieldEditor( field ) {
		var editor = el( 'div', { className: 'listora-field-editor' } );

		// Label.
		editor.appendChild( fieldInput( 'Label', field.label || '', function ( val ) {
			field.label = val;
			if ( field._isNew && ! field._keyEdited ) {
				field.key = toSlug( val ).replace( /-/g, '_' );
				render();
			}
		} ) );

		// Key.
		var keyField = fieldInput( 'Key', field.key || '', function ( val ) {
			field.key = val.replace( /[^a-z0-9_]/g, '' );
			field._keyEdited = true;
		} );
		if ( ! field._isNew ) {
			keyField.querySelector( 'input' ).setAttribute( 'readonly', 'readonly' );
		}
		editor.appendChild( keyField );

		// Type (readonly).
		var typeLabel = fieldTypes[ field.type ] ? fieldTypes[ field.type ].label : field.type;
		var typeField = fieldInput( 'Type', typeLabel, function () {} );
		typeField.querySelector( 'input' ).setAttribute( 'readonly', 'readonly' );
		editor.appendChild( typeField );

		// Options (for choice types).
		var typeInfo = fieldTypes[ field.type ];
		if ( typeInfo && typeInfo.has_options ) {
			editor.appendChild( renderOptionsEditor( field ) );
		}

		// Checkboxes row.
		var checks = el( 'div', { className: 'listora-field-editor__checks' } );
		checks.appendChild( checkboxField( 'Required', field.required, function ( val ) { field.required = val; } ) );
		checks.appendChild( checkboxField( 'Searchable', field.searchable, function ( val ) { field.searchable = val; } ) );
		checks.appendChild( checkboxField( 'Filterable', field.filterable, function ( val ) { field.filterable = val; } ) );
		checks.appendChild( checkboxField( 'Show on Card', field.show_in_card, function ( val ) { field.show_in_card = val; } ) );
		editor.appendChild( checks );

		// Schema property.
		editor.appendChild( fieldInput( 'Schema.org property', field.schema_prop || '', function ( val ) {
			field.schema_prop = val;
		} ) );

		// Placeholder.
		editor.appendChild( fieldInput( 'Placeholder', field.placeholder || '', function ( val ) {
			field.placeholder = val;
		} ) );

		// Help text.
		editor.appendChild( fieldInput( 'Help text', field.description || '', function ( val ) {
			field.description = val;
		} ) );

		return editor;
	}

	function renderOptionsEditor( field ) {
		var wrap = el( 'div', { className: 'listora-options-editor' } );

		var lbl = el( 'label', { className: 'listora-meta-field__label' } );
		lbl.textContent = 'Options';
		wrap.appendChild( lbl );

		var list = el( 'div', { className: 'listora-options-list' } );

		if ( ! field.options ) {
			field.options = [];
		}

		field.options.forEach( function ( opt, idx ) {
			var optValue = ( typeof opt === 'object' ) ? ( opt.label || opt.value || '' ) : opt;
			var row = el( 'div', { className: 'listora-options-row' } );

			var input = el( 'input', {
				type: 'text',
				className: 'listora-input listora-input--sm',
				value: optValue
			} );
			input.addEventListener( 'input', function () {
				if ( typeof opt === 'object' ) {
					field.options[ idx ] = { value: toSlug( this.value ), label: this.value };
				} else {
					field.options[ idx ] = this.value;
				}
			} );
			row.appendChild( input );

			var removeBtn = el( 'button', { type: 'button', className: 'listora-icon-btn listora-icon-btn--danger listora-icon-btn--xs' } );
			removeBtn.appendChild( lucideIcon( 'x' ) );
			removeBtn.addEventListener( 'click', function () {
				field.options.splice( idx, 1 );
				render();
			} );
			row.appendChild( removeBtn );

			list.appendChild( row );
		} );

		wrap.appendChild( list );

		var addBtn = el( 'button', { type: 'button', className: 'listora-btn listora-btn--sm' } );
		addBtn.appendChild( lucideIcon( 'plus' ) );
		addBtn.appendChild( document.createTextNode( ' Add Option' ) );
		addBtn.addEventListener( 'click', function () {
			field.options.push( '' );
			render();
		} );
		wrap.appendChild( addBtn );

		return wrap;
	}

	// ── Field type picker ──
	function showFieldTypePicker( gIdx ) {
		// Remove existing picker.
		var existingPicker = document.querySelector( '.listora-field-picker' );
		if ( existingPicker ) {
			existingPicker.remove();
		}

		var overlay = el( 'div', { className: 'listora-field-picker' } );

		var panel = el( 'div', { className: 'listora-field-picker__panel' } );

		var pickerHeader = el( 'div', { className: 'listora-field-picker__header' } );
		var pickerTitle = el( 'h3' );
		pickerTitle.textContent = 'Add Field';
		pickerHeader.appendChild( pickerTitle );

		var closeBtn = el( 'button', { type: 'button', className: 'listora-icon-btn' } );
		closeBtn.appendChild( lucideIcon( 'x' ) );
		closeBtn.addEventListener( 'click', function () {
			overlay.remove();
		} );
		pickerHeader.appendChild( closeBtn );
		panel.appendChild( pickerHeader );

		// Group types by category.
		var grouped = {};
		Object.keys( fieldTypes ).forEach( function ( key ) {
			var ft = fieldTypes[ key ];
			var cat = ft.category || 'other';
			if ( ! grouped[ cat ] ) {
				grouped[ cat ] = [];
			}
			grouped[ cat ].push( { key: key, label: ft.label, icon: ft.icon } );
		} );

		var pickerBody = el( 'div', { className: 'listora-field-picker__body' } );

		Object.keys( grouped ).forEach( function ( cat ) {
			var catLabel = el( 'p', { className: 'listora-field-picker__cat' } );
			catLabel.textContent = categoryLabels[ cat ] || cat;
			pickerBody.appendChild( catLabel );

			var grid = el( 'div', { className: 'listora-field-picker__grid' } );

			grouped[ cat ].forEach( function ( ft ) {
				var card = el( 'button', { type: 'button', className: 'listora-field-picker__card' } );
				card.textContent = ft.label;
				card.addEventListener( 'click', function () {
					addField( gIdx, ft.key );
					overlay.remove();
				} );
				grid.appendChild( card );
			} );

			pickerBody.appendChild( grid );
		} );

		panel.appendChild( pickerBody );
		overlay.appendChild( panel );
		document.body.appendChild( overlay );

		// Close on overlay click.
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				overlay.remove();
			}
		} );

		refreshIcons();
	}

	function addField( gIdx, fieldType ) {
		if ( ! fieldGroups[ gIdx ].fields ) {
			fieldGroups[ gIdx ].fields = [];
		}

		var newField = {
			key: '',
			label: '',
			type: fieldType,
			required: false,
			searchable: false,
			filterable: false,
			show_in_card: false,
			show_in_detail: true,
			schema_prop: '',
			placeholder: '',
			description: '',
			options: [],
			order: fieldGroups[ gIdx ].fields.length,
			_expanded: true,
			_isNew: true
		};

		fieldGroups[ gIdx ].fields.push( newField );
		render();
	}

	// ── Add group panel ──
	function showAddGroupPanel() {
		var name = prompt( 'Group name:' );
		if ( ! name || ! name.trim() ) {
			return;
		}

		fieldGroups.push( {
			key: toSlug( name ).replace( /-/g, '_' ),
			label: name.trim(),
			description: '',
			icon: '',
			order: fieldGroups.length,
			fields: []
		} );

		render();
	}

	// ── Save handler ──
	var saveBtn = document.getElementById( 'listora-save-type' );
	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', function () {
			var data = collectFormData();

			if ( ! data.name ) {
				listoraToast( 'Please enter a type name.', 'error' );
				return;
			}

			saveBtn.disabled = true;
			saveBtn.textContent = 'Saving...';

			var slug   = isNew ? ( data.slug || toSlug( data.name ) ) : typeSlug;
			var method = isNew ? 'POST' : 'PUT';
			var url    = listoraTypeEditor.apiBase + ( isNew ? '' : '/' + slug );

			fetch( url, {
				method: method,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': listoraTypeEditor.nonce
				},
				body: JSON.stringify( data )
			} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( result ) {
				saveBtn.disabled = false;
				saveBtn.textContent = '';
				saveBtn.appendChild( lucideIcon( 'save' ) );
				saveBtn.appendChild( document.createTextNode( ' Save Type' ) );
				refreshIcons();

				if ( result.slug ) {
					listoraToast( 'Type saved successfully.', 'success' );
					if ( isNew ) {
						window.location.href = listoraTypeEditor.adminUrl + '&edit=' + result.slug;
					}
				} else {
					listoraToast( result.message || 'Error saving type.', 'error' );
				}
			} )
			.catch( function () {
				saveBtn.disabled = false;
				saveBtn.textContent = '';
				saveBtn.appendChild( lucideIcon( 'save' ) );
				saveBtn.appendChild( document.createTextNode( ' Save Type' ) );
				refreshIcons();
				listoraToast( 'Network error. Please try again.', 'error' );
			} );
		} );
	}

	function collectFormData() {
		// Clean up internal state props before sending.
		var cleanGroups = fieldGroups.map( function ( group, gIdx ) {
			var cleanFields = ( group.fields || [] ).map( function ( f, fIdx ) {
				return {
					key: f.key,
					label: f.label,
					type: f.type,
					required: !! f.required,
					searchable: !! f.searchable,
					filterable: !! f.filterable,
					show_in_card: !! f.show_in_card,
					show_in_detail: f.show_in_detail !== false,
					schema_prop: f.schema_prop || '',
					placeholder: f.placeholder || '',
					description: f.description || '',
					options: f.options || [],
					order: fIdx
				};
			} );

			return {
				key: group.key,
				label: group.label,
				description: group.description || '',
				icon: group.icon || '',
				order: gIdx,
				fields: cleanFields
			};
		} );

		// Collect selected category IDs.
		var catCheckboxes = document.querySelectorAll( '#listora-type-categories input[type="checkbox"]:checked' );
		var categories    = [];
		catCheckboxes.forEach( function ( cb ) {
			categories.push( parseInt( cb.value, 10 ) );
		} );

		return {
			name: ( document.getElementById( 'listora-type-name' ) || {} ).value || '',
			slug: ( document.getElementById( 'listora-type-slug' ) || {} ).value || '',
			schema_type: ( document.getElementById( 'listora-type-schema' ) || {} ).value || 'LocalBusiness',
			icon: ( document.getElementById( 'listora-type-icon' ) || {} ).value || 'building-2',
			color: ( document.getElementById( 'listora-type-color' ) || {} ).value || '#0073aa',
			map_enabled: !! ( document.getElementById( 'listora-type-map' ) || {} ).checked,
			review_enabled: !! ( document.getElementById( 'listora-type-review' ) || {} ).checked,
			submission_enabled: !! ( document.getElementById( 'listora-type-submission' ) || {} ).checked,
			moderation: ( document.getElementById( 'listora-type-moderation' ) || {} ).value || 'manual',
			expiration_days: parseInt( ( document.getElementById( 'listora-type-expiry' ) || {} ).value || '365', 10 ),
			field_groups: cleanGroups,
			categories: categories
		};
	}

	// ── List view: delete type ──
	function initListView() {
		document.querySelectorAll( '.listora-delete-type' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var slug = this.dataset.slug;
				var name = this.dataset.name;

				if ( ! confirm( 'Delete "' + name + '"? This cannot be undone.' ) ) {
					return;
				}

				fetch( listoraTypeEditor.apiBase + '/' + slug, {
					method: 'DELETE',
					headers: {
						'X-WP-Nonce': listoraTypeEditor.nonce
					}
				} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( result ) {
					if ( result.deleted ) {
						var row = document.querySelector( 'tr[data-type-slug="' + slug + '"]' );
						if ( row ) {
							row.remove();
						}
						listoraToast( 'Type deleted.', 'success' );
						if ( result.listings_count > 0 ) {
							listoraToast( result.message, 'warning' );
						}
					} else {
						listoraToast( result.message || 'Error deleting type.', 'error' );
					}
				} )
				.catch( function () {
					listoraToast( 'Network error.', 'error' );
				} );
			} );
		} );
	}

	// ── Helpers ──
	function el( tag, attrs ) {
		var node = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( key ) {
				if ( key === 'className' ) {
					node.className = attrs[ key ];
				} else {
					node.setAttribute( key, attrs[ key ] );
				}
			} );
		}
		return node;
	}

	/**
	 * Create a Lucide icon placeholder element.
	 * Uses data-lucide attribute which Lucide's createIcons() replaces with SVG.
	 */
	function lucideIcon( name ) {
		var i = document.createElement( 'i' );
		i.setAttribute( 'data-lucide', name );
		return i;
	}

	function fieldInput( labelText, value, onChange ) {
		var wrap = el( 'div', { className: 'listora-meta-field' } );
		var lbl = el( 'label' );
		lbl.textContent = labelText;
		wrap.appendChild( lbl );
		var input = el( 'input', { type: 'text', className: 'listora-input', value: value } );
		input.addEventListener( 'input', function () {
			onChange( this.value );
		} );
		wrap.appendChild( input );
		return wrap;
	}

	function checkboxField( labelText, isChecked, onChange ) {
		var lbl = el( 'label', { className: 'listora-checkbox-label listora-checkbox-label--inline' } );
		var cb = el( 'input', { type: 'checkbox' } );
		cb.checked = !! isChecked;
		cb.addEventListener( 'change', function () {
			onChange( this.checked );
		} );
		lbl.appendChild( cb );
		lbl.appendChild( document.createTextNode( ' ' + labelText ) );
		return lbl;
	}

	function refreshIcons() {
		if ( window.lucide && typeof window.lucide.createIcons === 'function' ) {
			window.lucide.createIcons();
		}
	}
} )();
