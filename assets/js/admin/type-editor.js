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

	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	// Translated text goes into attribute HTML here, so quotes and brackets are escaped.
	function escAttr( text ) {
		return String( text ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}

	// AbortController + 10s timeout helper (P1-3 — REST hang risk).
	function abortableFetch( url, opts, ms ) {
		var ctrl = new AbortController();
		var id = setTimeout( function () { ctrl.abort(); }, ms || 10000 );
		opts = opts || {};
		opts.signal = ctrl.signal;
		return fetch( url, opts ).finally( function () { clearTimeout( id ); } );
	}
	function isAbortError( e ) {
		return Boolean( e && ( e.name === 'AbortError' || e.code === 20 ) );
	}

	// Category / feature pickers: search, "Selected only", live count.
	document.querySelectorAll( '[data-listora-picker]' ).forEach( function ( picker ) {
		var search = picker.querySelector( '[data-listora-picker-search]' );
		var only   = picker.querySelector( '[data-listora-picker-only]' );
		var count  = picker.querySelector( '[data-listora-picker-count]' );
		var none   = picker.querySelector( '[data-listora-picker-none]' );
		var items  = Array.prototype.slice.call( picker.querySelectorAll( '[data-listora-picker-item]' ) );

		function update() {
			var term  = ( search.value || '' ).trim().toLowerCase();
			var shown = 0;
			var ticked = 0;
			items.forEach( function ( item ) {
				var box = item.querySelector( 'input' );
				if ( box.checked ) {
					ticked++;
				}
				var show = ( ! term || item.textContent.toLowerCase().indexOf( term ) !== -1 ) && ( ! only.checked || box.checked );
				item.hidden = ! show;
				if ( show ) {
					shown++;
				}
			} );
			none.hidden = shown > 0;
			count.textContent = count.dataset.template.replace( '%1$s', ticked ).replace( '%2$s', items.length );
		}
		search.addEventListener( 'input', update );
		only.addEventListener( 'change', update );
		picker.addEventListener( 'change', function ( e ) {
			if ( e.target.closest( '[data-listora-picker-item]' ) ) {
				update();
			}
		} );
	} );

	var builder = document.getElementById( 'listora-field-builder' );
	if ( ! builder ) {
		return;
	}

	// ── State ──
	var fieldGroups = JSON.parse( builder.dataset.fieldGroups || '[]' );
	var fieldTypes  = JSON.parse( builder.dataset.fieldTypes || '{}' );
	var typeSlug    = builder.dataset.typeSlug || '';
	var isNew       = ! typeSlug;
	var isDirty     = false;

	// ── Unsaved changes warning ──
	function markDirty() {
		isDirty = true;
	}

	window.addEventListener( 'beforeunload', function ( e ) {
		if ( isDirty ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	// Track changes on sidebar inputs.
	document.querySelectorAll( '.listora-type-sidebar input, .listora-type-sidebar select, .listora-type-sidebar textarea' ).forEach( function ( input ) {
		input.addEventListener( 'change', markDirty );
		input.addEventListener( 'input', markDirty );
	} );

	// ── Review criteria ──
	// Add/remove rows client-side; collectFormData() below reads every
	// [data-listora-criteria-row] back out into the `review_criteria` payload.
	var criteriaList = document.getElementById( 'listora-type-criteria' );
	var addCriteriaBtn = document.querySelector( '[data-listora-criteria-add]' );
	if ( criteriaList && addCriteriaBtn ) {
		addCriteriaBtn.addEventListener( 'click', function () {
			var row = document.createElement( 'div' );
			row.className = 'listora-criteria-row';
			row.setAttribute( 'data-listora-criteria-row', '' );
			row.innerHTML =
				'<input type="text" class="listora-input" placeholder="' + escAttr( __( 'key (e.g. food)', 'wb-listora' ) ) + '" data-listora-criteria-key>' +
				'<input type="text" class="listora-input" placeholder="' + escAttr( __( 'Label (e.g. Food Quality)', 'wb-listora' ) ) + '" data-listora-criteria-label>' +
				'<button type="button" class="listora-icon-btn listora-icon-btn--danger" data-listora-criteria-remove aria-label="' + escAttr( __( 'Remove criterion', 'wb-listora' ) ) + '"><i data-lucide="x"></i></button>';
			criteriaList.appendChild( row );
			if ( window.lucide && window.lucide.createIcons ) window.lucide.createIcons();
			markDirty();
		} );

		criteriaList.addEventListener( 'click', function ( e ) {
			var removeBtn = e.target.closest( '[data-listora-criteria-remove]' );
			if ( removeBtn ) {
				removeBtn.closest( '[data-listora-criteria-row]' ).remove();
				markDirty();
			}
		} );

		criteriaList.addEventListener( 'input', function ( e ) {
			if ( e.target.closest( '[data-listora-criteria-row]' ) ) markDirty();
		} );
	}

	// Category labels for the field type picker.
	var categoryLabels = {
		basic:      __( 'Basic', 'wb-listora' ),
		choice:     __( 'Choice', 'wb-listora' ),
		datetime:   __( 'Date & Time', 'wb-listora' ),
		money:      __( 'Money', 'wb-listora' ),
		media:      __( 'Media', 'wb-listora' ),
		location:   __( 'Location', 'wb-listora' ),
		structured: __( 'Structured', 'wb-listora' ),
		display:    __( 'Display', 'wb-listora' ),
		custom:     __( 'Custom', 'wb-listora' )
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
		addGroupBtn.appendChild( document.createTextNode( ' ' + __( 'Add Field Group', 'wb-listora' ) ) );
		addGroupBtn.addEventListener( 'click', function () {
			showAddGroupPanel();
		} );
		builder.appendChild( addGroupBtn );
		var groupHint = el( 'p', { className: 'description' } );
		groupHint.textContent = __( 'A group shows as a tab when it has something to show. Contact details and the map location appear in the sidebar, so a group made only of those gets no tab.', 'wb-listora' );
		builder.appendChild( groupHint );

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

		// Reorder buttons (Up / Down).
		var reorderWrap = el( 'div', { className: 'listora-reorder-btns' } );

		var moveUpBtn = el( 'button', { type: 'button', className: 'listora-icon-btn listora-icon-btn--xs', title: __( 'Move up', 'wb-listora' ) } );
		moveUpBtn.appendChild( lucideIcon( 'arrow-up' ) );
		if ( gIdx === 0 ) {
			moveUpBtn.disabled = true;
		}
		moveUpBtn.addEventListener( 'click', function () {
			if ( gIdx > 0 ) {
				var temp = fieldGroups[ gIdx - 1 ];
				fieldGroups[ gIdx - 1 ] = fieldGroups[ gIdx ];
				fieldGroups[ gIdx ] = temp;
				markDirty();
				render();
			}
		} );
		reorderWrap.appendChild( moveUpBtn );

		var moveDownBtn = el( 'button', { type: 'button', className: 'listora-icon-btn listora-icon-btn--xs', title: __( 'Move down', 'wb-listora' ) } );
		moveDownBtn.appendChild( lucideIcon( 'arrow-down' ) );
		if ( gIdx === fieldGroups.length - 1 ) {
			moveDownBtn.disabled = true;
		}
		moveDownBtn.addEventListener( 'click', function () {
			if ( gIdx < fieldGroups.length - 1 ) {
				var temp = fieldGroups[ gIdx + 1 ];
				fieldGroups[ gIdx + 1 ] = fieldGroups[ gIdx ];
				fieldGroups[ gIdx ] = temp;
				markDirty();
				render();
			}
		} );
		reorderWrap.appendChild( moveDownBtn );

		header.appendChild( reorderWrap );

		var titleWrap = el( 'div', { className: 'listora-field-group__title-wrap' } );
		var title = el( 'span', { className: 'listora-field-group__title' } );
		title.textContent = group.label || __( 'Untitled Group', 'wb-listora' );
		titleWrap.appendChild( title );

		// FIX 5: Rename affordance — pencil icon to edit group name inline.
		var renameBtn = el( 'button', { type: 'button', className: 'listora-icon-btn listora-icon-btn--xs', title: __( 'Rename group', 'wb-listora' ) } );
		renameBtn.appendChild( lucideIcon( 'pencil' ) );
		renameBtn.addEventListener( 'click', function () {
			startGroupRename( titleWrap, group );
		} );
		titleWrap.appendChild( renameBtn );

		var countBadge = el( 'span', { className: 'listora-badge listora-badge--muted' } );
		countBadge.textContent = ( group.fields ? group.fields.length : 0 ) + ' fields';
		titleWrap.appendChild( countBadge );

		header.appendChild( titleWrap );

		var headerActions = el( 'div', { className: 'listora-field-group__actions' } );

		var collapseBtn = el( 'button', { type: 'button', className: 'listora-icon-btn', title: __( 'Toggle', 'wb-listora' ) } );
		collapseBtn.appendChild( lucideIcon( collapsed ? 'chevron-down' : 'chevron-up' ) );
		collapseBtn.addEventListener( 'click', function () {
			group._collapsed = ! group._collapsed;
			render();
		} );
		headerActions.appendChild( collapseBtn );

		var deleteGroupBtn = el( 'button', { type: 'button', className: 'listora-icon-btn listora-icon-btn--danger', title: __( 'Delete group', 'wb-listora' ) } );
		deleteGroupBtn.appendChild( lucideIcon( 'trash-2' ) );
		deleteGroupBtn.addEventListener( 'click', function () {
			window.listoraConfirm( {
				title: __( 'Delete field group?', 'wb-listora' ),
				message: __( 'This will remove the group and all its fields. This cannot be undone.', 'wb-listora' ),
				confirmLabel: __( 'Delete group', 'wb-listora' ),
				tone: 'danger',
			} ).then( function ( ok ) {
				if ( ! ok ) {
					return;
				}
				fieldGroups.splice( gIdx, 1 );
				markDirty();
				render();
			} );
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
				empty.textContent = __( 'No fields yet. Click "Add Field" below.', 'wb-listora' );
				body.appendChild( empty );
			}

			// Add field button.
			var addFieldBtn = el( 'button', {
				type: 'button',
				className: 'listora-btn listora-btn--sm listora-add-field-btn'
			} );
			addFieldBtn.appendChild( lucideIcon( 'plus' ) );
			addFieldBtn.appendChild( document.createTextNode( ' ' + __( 'Add Field', 'wb-listora' ) ) );
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

		var fieldReorder = el( 'div', { className: 'listora-reorder-btns' } );

		var fieldUpBtn = el( 'button', { type: 'button', className: 'listora-icon-btn listora-icon-btn--xs', title: __( 'Move up', 'wb-listora' ) } );
		fieldUpBtn.appendChild( lucideIcon( 'arrow-up' ) );
		if ( fIdx === 0 ) {
			fieldUpBtn.disabled = true;
		}
		fieldUpBtn.addEventListener( 'click', function () {
			var fields = fieldGroups[ gIdx ].fields;
			if ( fIdx > 0 ) {
				var tmp = fields[ fIdx - 1 ];
				fields[ fIdx - 1 ] = fields[ fIdx ];
				fields[ fIdx ] = tmp;
				markDirty();
				render();
			}
		} );
		fieldReorder.appendChild( fieldUpBtn );

		var fieldDownBtn = el( 'button', { type: 'button', className: 'listora-icon-btn listora-icon-btn--xs', title: __( 'Move down', 'wb-listora' ) } );
		fieldDownBtn.appendChild( lucideIcon( 'arrow-down' ) );
		var totalFields = fieldGroups[ gIdx ].fields ? fieldGroups[ gIdx ].fields.length : 0;
		if ( fIdx === totalFields - 1 ) {
			fieldDownBtn.disabled = true;
		}
		fieldDownBtn.addEventListener( 'click', function () {
			var fields = fieldGroups[ gIdx ].fields;
			if ( fIdx < fields.length - 1 ) {
				var tmp = fields[ fIdx + 1 ];
				fields[ fIdx + 1 ] = fields[ fIdx ];
				fields[ fIdx ] = tmp;
				markDirty();
				render();
			}
		} );
		fieldReorder.appendChild( fieldDownBtn );

		summary.appendChild( fieldReorder );

		// The label opens the field, like the pencil: it is what people
		// click first.
		var label = el( 'button', { type: 'button', className: 'listora-field-row__label', 'aria-expanded': expanded ? 'true' : 'false' } );
		label.textContent = field.label || __( 'Untitled', 'wb-listora' );
		label.addEventListener( 'click', function () {
			field._expanded = ! field._expanded;
			render();
		} );
		summary.appendChild( label );

		var typeBadge = el( 'span', { className: 'listora-badge listora-badge--default' } );
		var typeInfo = fieldTypes[ field.type ];
		typeBadge.textContent = typeInfo ? typeInfo.label : field.type;
		summary.appendChild( typeBadge );

		var keySpan = el( 'span', { className: 'listora-field-row__key' } );
		keySpan.textContent = field.key || '';
		summary.appendChild( keySpan );

		// What the field does, at a glance, without opening it.
		var flags = el( 'span', { className: 'listora-field-row__flags' } );
		[
			[ field.required, __( 'Required', 'wb-listora' ) ],
			[ field.show_in_card, __( 'On card', 'wb-listora' ) ],
			[ field.filterable, __( 'Filter', 'wb-listora' ) ],
			[ field.searchable, __( 'Search', 'wb-listora' ) ]
		].forEach( function ( pair ) {
			if ( pair[ 0 ] ) {
				var flag = el( 'span', { className: 'listora-field-row__flag' } );
				flag.textContent = pair[ 1 ];
				flags.appendChild( flag );
			}
		} );
		summary.appendChild( flags );

		var actions = el( 'div', { className: 'listora-field-row__actions' } );

		var editBtn = el( 'button', { type: 'button', className: 'listora-icon-btn', title: __( 'Edit field', 'wb-listora' ), 'aria-label': sprintf( __( 'Edit %s', 'wb-listora' ), field.label || __( 'field', 'wb-listora' ) ) } );
		editBtn.appendChild( lucideIcon( expanded ? 'chevron-up' : 'pencil' ) );
		editBtn.addEventListener( 'click', function () {
			field._expanded = ! field._expanded;
			render();
		} );
		actions.appendChild( editBtn );

		var delBtn = el( 'button', { type: 'button', className: 'listora-icon-btn listora-icon-btn--danger', title: __( 'Delete field', 'wb-listora' ), 'aria-label': sprintf( __( 'Delete %s', 'wb-listora' ), field.label || __( 'field', 'wb-listora' ) ) } );
		delBtn.appendChild( lucideIcon( 'trash-2' ) );
		delBtn.addEventListener( 'click', function () {
			window.listoraConfirm( {
				title: __( 'Delete field?', 'wb-listora' ),
				message: __( 'Existing data in this field will remain in the database but will no longer be shown.', 'wb-listora' ),
				confirmLabel: __( 'Delete field', 'wb-listora' ),
				tone: 'danger',
			} ).then( function ( ok ) {
				if ( ! ok ) {
					return;
				}
				fieldGroups[ gIdx ].fields.splice( fIdx, 1 );
				markDirty();
				render();
			} );
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

		// Key field is created first so the Label handler can write into
		// its <input> directly instead of calling render(). render() on
		// every keystroke rebuilds the editor DOM and steals focus from
		// the Label input the user is actively typing into.
		var keyField = fieldInput( __( 'Key', 'wb-listora' ), field.key || '', function ( val ) {
			field.key = val.replace( /[^a-z0-9_]/g, '' );
			field._keyEdited = true;
		} );
		if ( ! field._isNew ) {
			keyField.querySelector( 'input' ).setAttribute( 'readonly', 'readonly' );
		}
		var keyInput = keyField.querySelector( 'input' );

		// Label.
		editor.appendChild( fieldInput( __( 'Label', 'wb-listora' ), field.label || '', function ( val ) {
			field.label = val;
			if ( field._isNew && ! field._keyEdited ) {
				field.key = toSlug( val ).replace( /-/g, '_' );
				if ( keyInput ) {
					keyInput.value = field.key;
				}
			}
		} ) );

		editor.appendChild( keyField );

		// Type (readonly).
		var typeLabel = fieldTypes[ field.type ] ? fieldTypes[ field.type ].label : field.type;
		var typeField = fieldInput( __( 'Type', 'wb-listora' ), typeLabel, function () {} );
		typeField.querySelector( 'input' ).setAttribute( 'readonly', 'readonly' );
		editor.appendChild( typeField );

		// Options (for choice types).
		var typeInfo = fieldTypes[ field.type ];
		if ( typeInfo && typeInfo.has_options ) {
			editor.appendChild( renderOptionsEditor( field ) );
		}

		// Checkboxes row.
		var checks = el( 'div', { className: 'listora-field-editor__checks' } );
		checks.appendChild( checkboxField( __( 'Required', 'wb-listora' ), field.required, function ( val ) { field.required = val; } ) );
		checks.appendChild( checkboxField( __( 'Searchable', 'wb-listora' ), field.searchable, function ( val ) { field.searchable = val; } ) );
		checks.appendChild( checkboxField( __( 'Filterable', 'wb-listora' ), field.filterable, function ( val ) { field.filterable = val; } ) );
		checks.appendChild( checkboxField( __( 'Show on Card', 'wb-listora' ), field.show_in_card, function ( val ) { field.show_in_card = val; } ) );
		// Both default to on; the API never shows more than the listing page does.
		// A field hidden on the listing page is hidden from the API too, so
		// Show in API follows it: unticked and locked while the page hides it.
		var apiCheck = checkboxField( __( 'Show in API', 'wb-listora' ), field.show_in_rest !== false, function ( val ) { field.show_in_rest = val; } );
		var apiHint = el( 'span', { className: 'description listora-field-editor__lock-hint', id: 'listora-api-lock-' + ( field.key || field.name || 'field' ) } );
		apiHint.textContent = __( 'Hidden from the API because the field is hidden on the listing page.', 'wb-listora' );
		function syncApiCheck() {
			var apiInput = apiCheck.querySelector( 'input' );
			var hidden = false === field.show_in_detail;
			apiInput.disabled = hidden;
			apiInput.checked = ! hidden && false !== field.show_in_rest;
			// Say why the box is locked, and tie the reason to it for screen readers.
			apiHint.hidden = ! hidden;
			if ( hidden ) {
				apiInput.setAttribute( 'aria-describedby', apiHint.id );
			} else {
				apiInput.removeAttribute( 'aria-describedby' );
			}
		}
		checks.appendChild( checkboxField( __( 'Show on listing page', 'wb-listora' ), field.show_in_detail !== false, function ( val ) { field.show_in_detail = val; syncApiCheck(); } ) );
		checks.appendChild( apiCheck );
		checks.appendChild( apiHint );
		syncApiCheck();
		if ( 'number' === field.type ) {
			checks.appendChild( checkboxField( __( 'Show as money (site currency)', 'wb-listora' ), !! field.currency, function ( val ) { field.currency = val; } ) );
		}
		editor.appendChild( checks );

		// Schema property.
		editor.appendChild( fieldInput( __( 'Schema.org property', 'wb-listora' ), field.schema_prop || '', function ( val ) {
			field.schema_prop = val;
		} ) );

		// Placeholder.
		editor.appendChild( fieldInput( __( 'Placeholder', 'wb-listora' ), field.placeholder || '', function ( val ) {
			field.placeholder = val;
		} ) );

		// Help text.
		editor.appendChild( fieldInput( __( 'Help text', 'wb-listora' ), field.description || '', function ( val ) {
			field.description = val;
		} ) );

		return editor;
	}

	function renderOptionsEditor( field ) {
		var wrap = el( 'div', { className: 'listora-options-editor' } );

		var lbl = el( 'label', { className: 'listora-meta-field__label' } );
		lbl.textContent = __( 'Options', 'wb-listora' );
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
				// Always store the canonical { value, label } object shape —
				// plain strings fatal the PHP 8 renderers (submission form,
				// search filters). Field::normalize_options() is the server-
				// side backstop for data saved before 1.4.1.
				field.options[ idx ] = { value: toSlug( this.value ), label: this.value };
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
		addBtn.appendChild( document.createTextNode( ' ' + __( 'Add Option', 'wb-listora' ) ) );
		addBtn.addEventListener( 'click', function () {
			field.options.push( { value: '', label: '' } );
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
		pickerTitle.textContent = __( 'Add Field', 'wb-listora' );
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
		markDirty();
		render();
	}

	// ── Add group panel (inline form instead of window.prompt) ──
	function showAddGroupPanel() {
		// Remove existing inline form if open.
		var existing = builder.querySelector( '.listora-add-group-form' );
		if ( existing ) {
			existing.remove();
			return;
		}

		// Hide the Add Field Group button while the form is visible.
		var addBtn = builder.querySelector( '.listora-add-group-btn' );

		var form = el( 'div', { className: 'listora-add-group-form' } );

		var input = el( 'input', {
			type: 'text',
			className: 'listora-input',
			placeholder: __( 'Group name...', 'wb-listora' )
		} );

		var confirmBtn = el( 'button', {
			type: 'button',
			className: 'listora-btn listora-btn--primary listora-btn--sm'
		} );
		confirmBtn.textContent = __( 'Add Group', 'wb-listora' );

		var cancelBtn = el( 'button', {
			type: 'button',
			className: 'listora-btn listora-btn--sm'
		} );
		cancelBtn.textContent = __( 'Cancel', 'wb-listora' );

		function submitGroup() {
			var name = input.value.trim();
			if ( ! name ) {
				input.focus();
				return;
			}

			fieldGroups.push( {
				key: toSlug( name ).replace( /-/g, '_' ),
				label: name,
				description: '',
				icon: '',
				order: fieldGroups.length,
				fields: []
			} );

			markDirty();
			render();
		}

		confirmBtn.addEventListener( 'click', submitGroup );

		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				submitGroup();
			} else if ( e.key === 'Escape' ) {
				form.remove();
			}
		} );

		cancelBtn.addEventListener( 'click', function () {
			form.remove();
		} );

		form.appendChild( input );
		form.appendChild( confirmBtn );
		form.appendChild( cancelBtn );

		if ( addBtn ) {
			builder.insertBefore( form, addBtn );
		} else {
			builder.appendChild( form );
		}

		input.focus();
		refreshIcons();
	}

	// ── Group rename (inline edit) ──
	function startGroupRename( titleWrap, group ) {
		// Clear the title wrap and replace with an input.
		titleWrap.textContent = '';

		var input = el( 'input', {
			type: 'text',
			className: 'listora-input listora-input--sm',
			value: group.label || ''
		} );

		function finishRename() {
			var newName = input.value.trim();
			if ( newName && newName !== group.label ) {
				group.label = newName;
				group.key = toSlug( newName ).replace( /-/g, '_' );
				markDirty();
			}
			render();
		}

		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				finishRename();
			} else if ( e.key === 'Escape' ) {
				render();
			}
		} );

		input.addEventListener( 'blur', finishRename );

		titleWrap.appendChild( input );
		input.focus();
		input.select();
	}

	// ── Save handler ──
	var saveBtn = document.getElementById( 'listora-save-type' );
	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', function () {
			var data = collectFormData();

			if ( ! data.name ) {
				listoraToast( __( 'Please enter a type name.', 'wb-listora' ), 'error' );
				return;
			}

			saveBtn.disabled = true;
			saveBtn.textContent = __( 'Saving...', 'wb-listora' );

			var slug   = isNew ? ( data.slug || toSlug( data.name ) ) : typeSlug;
			var method = isNew ? 'POST' : 'PUT';
			var url    = listoraTypeEditor.apiBase + ( isNew ? '' : '/' + slug );

			abortableFetch( url, {
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
				saveBtn.appendChild( document.createTextNode( ' ' + __( 'Save Type', 'wb-listora' ) ) );
				refreshIcons();

				if ( result.slug ) {
					isDirty = false;
					listoraToast( __( 'Type saved successfully.', 'wb-listora' ), 'success' );
					if ( isNew ) {
						// Carry the success through the redirect. The toast above
						// is drawn and then immediately destroyed by the
						// navigation, so a new type saved silently as far as the
						// owner could tell.
						window.location.href = listoraTypeEditor.adminUrl +
							'&edit=' + encodeURIComponent( result.slug ) +
							'&listora_saved=type';
					}
				} else {
					listoraToast( result.message || __( 'Error saving type.', 'wb-listora' ), 'error' );
				}
			} )
			.catch( function ( err ) {
				saveBtn.disabled = false;
				saveBtn.textContent = '';
				saveBtn.appendChild( lucideIcon( 'save' ) );
				saveBtn.appendChild( document.createTextNode( ' ' + __( 'Save Type', 'wb-listora' ) ) );
				refreshIcons();
				listoraToast( isAbortError( err )
					? __( 'Network is slow — please try again.', 'wb-listora' )
					: __( 'Network error. Please try again.', 'wb-listora' ), 'error' );
			} );
		} );
	}

	function collectFormData() {
		// Clean up internal state props before sending.
		var cleanGroups = fieldGroups.map( function ( group, gIdx ) {
			var cleanFields = ( group.fields || [] ).map( function ( f, fIdx ) {
				// Send the whole field: a whitelist here silently reset every
				// setting the editor has no control for (min, max, step,
				// filter_type...) and dropped "Show in API". Only the editor's
				// own `_` state stays behind.
				var clean = {};
				Object.keys( f ).forEach( function ( prop ) {
					if ( '_' !== prop.charAt( 0 ) ) {
						clean[ prop ] = f[ prop ];
					}
				} );
				clean.required = !! f.required;
				clean.searchable = !! f.searchable;
				clean.filterable = !! f.filterable;
				clean.show_in_card = !! f.show_in_card;
				clean.show_in_detail = f.show_in_detail !== false;
				clean.show_in_rest = f.show_in_rest !== false;
				clean.schema_prop = f.schema_prop || '';
				clean.placeholder = f.placeholder || '';
				clean.description = f.description || '';
				clean.options = f.options || [];
				clean.order = fIdx;
				return clean;
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

		// Collect selected category IDs. The picker's own "Selected only" box
		// sits in the same container and is a view filter, not a term: counting
		// it sent NaN and the save failed with "Invalid parameter(s)".
		var catCheckboxes = document.querySelectorAll( '#listora-type-categories input[type="checkbox"]:checked:not([data-listora-picker-only])' );
		var categories    = [];
		catCheckboxes.forEach( function ( cb ) {
			categories.push( parseInt( cb.value, 10 ) );
		} );

		var featCheckboxes = document.querySelectorAll( '#listora-type-features input[type="checkbox"]:checked:not([data-listora-picker-only])' );
		var features       = [];
		featCheckboxes.forEach( function ( cb ) {
			features.push( parseInt( cb.value, 10 ) );
		} );

		// Skip a row until it has both a key and a label;
		// a half-filled row shouldn't silently become "" -> "" on save.
		var reviewCriteria = [];
		document.querySelectorAll( '#listora-type-criteria [data-listora-criteria-row]' ).forEach( function ( row ) {
			var keyEl   = row.querySelector( '[data-listora-criteria-key]' );
			var labelEl = row.querySelector( '[data-listora-criteria-label]' );
			var label   = labelEl ? labelEl.value.trim() : '';
			var key     = keyEl ? keyEl.value.trim() : '';
			if ( ! key && label ) key = toSlug( label );
			if ( key && label ) {
				reviewCriteria.push( { key: key, label: label } );
			}
		} );

		var payload = {
			name: ( document.getElementById( 'listora-type-name' ) || {} ).value || '',
			status: ( document.getElementById( 'listora-type-status' ) || {} ).value || 'active',
			slug: ( document.getElementById( 'listora-type-slug' ) || {} ).value || '',
			schema_type: ( document.getElementById( 'listora-type-schema' ) || {} ).value || 'LocalBusiness',
			icon: ( document.getElementById( 'listora-type-icon' ) || {} ).value || 'building-2',
			color: ( document.getElementById( 'listora-type-color' ) || {} ).value || '#0073aa',
			map_enabled: !! ( document.getElementById( 'listora-type-map' ) || {} ).checked,
			reviews: ( document.getElementById( 'listora-type-reviews' ) || {} ).value || 'global',
			submission_enabled: !! ( document.getElementById( 'listora-type-submission' ) || {} ).checked,
			services_enabled: !! ( document.getElementById( 'listora-type-services' ) || {} ).checked,
			// Site-level singleton, not a type prop — the controller writes it
			// to the `default_listing_type` setting. Sent from the per-type
			// form because that is where an owner expects to find it.
			is_default_type: !! ( document.getElementById( 'listora-type-default' ) || {} ).checked,
			moderation: ( document.getElementById( 'listora-type-moderation' ) || {} ).value || 'global',
			expiration_days: parseInt( ( document.getElementById( 'listora-type-expiry' ) || {} ).value || '0', 10 ),
			field_groups: cleanGroups,
			categories: categories,
			features: features
		};
		// The criteria card is Pro's. Without it the key is left out, so saving a
		// type never wipes criteria stored while Pro was active.
		if ( criteriaList ) {
			payload.review_criteria = reviewCriteria;
		}
		return payload;
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
			markDirty();
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
			markDirty();
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
