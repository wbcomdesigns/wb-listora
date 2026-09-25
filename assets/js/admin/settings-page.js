/**
 * WB Listora — Settings Page Behaviors
 *
 * Consolidates every JS handler that was previously emitted as inline <script>
 * blocks from class-settings-page.php. All translatable strings, REST URLs and
 * nonces flow in via wp_localize_script as `wbListoraSettings`.
 *
 * Replaces inline blocks at:
 *   - line 504  (CSV export + import)
 *   - line 1235 (copy-to-clipboard buttons in Credits tab)
 *   - line 1482 (Submission limits — beyond-limit + unlimited toggles)
 *   - line 1706 (Notifications — send-test buttons)
 *   - line 1785 (Activity Log — fetch / clear / render log)
 *   - line 2466 (Migration — run AJAX migration)
 *
 * No inline styles: status color/display states are driven by `is-progress`,
 * `is-success`, `is-error`, `is-hidden` utility classes (see settings.css).
 */
( function () {
	'use strict';

	var settings = window.wbListoraSettings || {};
	var i18n     = settings.i18n || {};

	function t( key, fallback ) {
		return ( i18n && i18n[ key ] ) ? i18n[ key ] : fallback;
	}

	// AbortController + 10s timeout helper. Mirrors
	// src/utils/abortable-fetch.js but kept inline because this file
	// is a plain ES5 IIFE outside the wp-scripts module pipeline.
	function abortableFetch( url, opts, ms ) {
		var ctrl = new AbortController();
		var id = setTimeout( function () { ctrl.abort(); }, ms || 10000 );
		opts = opts || {};
		opts.signal = ctrl.signal;
		return fetch( url, opts ).finally( function () { clearTimeout( id ); } );
	}
	function abortableApiFetch( opts, ms ) {
		var ctrl = new AbortController();
		var id = setTimeout( function () { ctrl.abort(); }, ms || 10000 );
		opts = opts || {};
		opts.signal = ctrl.signal;
		return window.wp.apiFetch( opts ).finally( function () { clearTimeout( id ); } );
	}
	function isAbortError( e ) {
		return Boolean( e && ( e.name === 'AbortError' || e.code === 20 ) );
	}
	function networkSlowMsg() {
		return i18n.networkSlow || 'Network is slow — please try again.';
	}

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function setStatus( el, message, state ) {
		if ( ! el ) {
			return;
		}
		el.textContent = message;
		el.classList.remove( 'is-progress', 'is-success', 'is-error' );
		if ( state ) {
			el.classList.add( state );
		}
	}

	/* ────────────────────────────────────────────────────────────────────
	   1. CSV Export + Import (Tools tab — Submissions/Import-Export panel)
	   Was: inline <script> at class-settings-page.php:504
	   ──────────────────────────────────────────────────────────────────── */
	function initCsvExportImport() {
		var exportBtn = document.getElementById( 'listora-csv-export-btn' );
		if ( exportBtn ) {
			exportBtn.addEventListener( 'click', function () {
				var typeSel = document.getElementById( 'listora-csv-export-type' );
				var status  = document.getElementById( 'listora-csv-export-status' );
				var type    = typeSel ? typeSel.value : '';
				var params  = new URLSearchParams( { include_meta: '1' } );
				if ( type ) {
					params.set( 'type', type );
				}

				setStatus( status, t( 'generatingExport', 'Generating export...' ), 'is-progress' );
				exportBtn.disabled = true;

				var url = ( settings.exportCsvUrl || '' ) + '?' + params.toString();
				url    += '&_wpnonce=' + encodeURIComponent( settings.restNonce || '' );

				var a    = document.createElement( 'a' );
				a.href     = url;
				a.download = '';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );

				setStatus( status, t( 'downloadStarted', 'Download started.' ), 'is-success' );
				exportBtn.disabled = false;
			} );
		}

		var importBtn = document.getElementById( 'listora-csv-import-btn' );
		if ( ! importBtn ) {
			return;
		}

		var fileInput  = document.getElementById( 'listora-csv-import-file' );
		var mappingBox = document.getElementById( 'listora-csv-import-mapping' );
		var csvFields  = settings.csvFields || {};

		// Parse just the header row (first line) of a CSV File client-side.
		// Handles simple double-quoted fields so headers like "Business, Inc."
		// survive. Good enough for a header row — full RFC-4180 parsing happens
		// server-side in CSV_Importer.
		function parseCsvHeaderLine( line ) {
			var out = [];
			var cur = '';
			var inQuotes = false;
			for ( var i = 0; i < line.length; i++ ) {
				var ch = line[ i ];
				if ( inQuotes ) {
					if ( ch === '"' ) {
						if ( line[ i + 1 ] === '"' ) { cur += '"'; i++; }
						else { inQuotes = false; }
					} else {
						cur += ch;
					}
				} else if ( ch === '"' ) {
					inQuotes = true;
				} else if ( ch === ',' ) {
					out.push( cur );
					cur = '';
				} else {
					cur += ch;
				}
			}
			out.push( cur );
			return out.map( function ( h ) { return h.trim(); } );
		}

		// Guess the best field key for a given CSV header by matching the
		// header text against each field key and label (case-insensitive).
		function guessFieldKey( header ) {
			var norm = String( header ).toLowerCase().replace( /[^a-z0-9]/g, '' );
			if ( ! norm ) { return '_skip'; }
			// Exporter header → importer field aliases.
			var aliases = {
				categories: 'category',
				featuredimageurl: 'image_url',
				imageurl: 'image_url'
			};
			if ( aliases[ norm ] && csvFields[ aliases[ norm ] ] ) {
				return aliases[ norm ];
			}
			var key;
			for ( key in csvFields ) {
				if ( ! Object.prototype.hasOwnProperty.call( csvFields, key ) ) { continue; }
				if ( '_skip' === key ) { continue; }
				if ( key.replace( /[^a-z0-9]/g, '' ) === norm ) { return key; }
			}
			for ( key in csvFields ) {
				if ( ! Object.prototype.hasOwnProperty.call( csvFields, key ) ) { continue; }
				if ( '_skip' === key ) { continue; }
				var labelNorm = String( csvFields[ key ] ).toLowerCase().replace( /[^a-z0-9]/g, '' );
				if ( labelNorm === norm ) { return key; }
			}
			return '_skip';
		}

		// Render one <select> per CSV column, pre-selecting the guessed field.
		function renderMapping( headers ) {
			if ( ! mappingBox ) { return; }
			mappingBox.innerHTML = '';

			var title = document.createElement( 'p' );
			title.className   = 'listora-impex__mapping-title';
			title.textContent = t( 'mapColumns', 'Map columns' );
			mappingBox.appendChild( title );

			var hint = document.createElement( 'p' );
			hint.className   = 'listora-impex__mapping-hint';
			hint.textContent = t( 'mapColumnsHint', 'Match each CSV column to a listing field. Unmatched columns are skipped.' );
			mappingBox.appendChild( hint );

			headers.forEach( function ( header, idx ) {
				var row = document.createElement( 'div' );
				row.className = 'listora-impex__mapping-row';

				var label = document.createElement( 'span' );
				label.className   = 'listora-impex__mapping-col';
				label.textContent = header || ( '#' + ( idx + 1 ) );
				row.appendChild( label );

				var select = document.createElement( 'select' );
				select.className = 'listora-impex__mapping-select';
				select.setAttribute( 'data-col', String( idx ) );
				var guessed = guessFieldKey( header );
				Object.keys( csvFields ).forEach( function ( key ) {
					var opt = document.createElement( 'option' );
					opt.value       = key;
					opt.textContent = csvFields[ key ];
					if ( key === guessed ) { opt.selected = true; }
					select.appendChild( opt );
				} );
				row.appendChild( select );

				mappingBox.appendChild( row );
			} );

			mappingBox.classList.remove( 'is-hidden' );
		}

		if ( fileInput ) {
			fileInput.addEventListener( 'change', function () {
				if ( ! fileInput.files.length ) {
					if ( mappingBox ) { mappingBox.classList.add( 'is-hidden' ); }
					return;
				}
				var reader = new FileReader();
				reader.onload = function ( e ) {
					var text  = String( e.target.result || '' );
					var line  = text.split( /\r\n|\n|\r/ )[ 0 ] || '';
					renderMapping( parseCsvHeaderLine( line ) );
				};
				// Reading the whole file is fine for an admin-side import; we only
				// use the first line.
				reader.readAsText( fileInput.files[ 0 ] );
			} );
		}

		// Build the column-index → field-key mapping from the rendered selects.
		// Skip columns mapped to '_skip'. Falls back to the legacy positional
		// mapping when the mapping UI has not rendered (e.g. no headers parsed).
		function buildMapping() {
			var mapping = {};
			var hasMapping = false;
			if ( mappingBox ) {
				var selects = mappingBox.querySelectorAll( '.listora-impex__mapping-select' );
				selects.forEach( function ( sel ) {
					var col = sel.getAttribute( 'data-col' );
					if ( '_skip' !== sel.value ) {
						mapping[ col ] = sel.value;
						hasMapping = true;
					}
				} );
			}
			if ( ! hasMapping ) {
				return { 0: 'title', 1: 'description', 2: 'category', 3: 'tags' };
			}
			return mapping;
		}

		importBtn.addEventListener( 'click', function () {
			var typeSlug  = document.getElementById( 'listora-csv-import-type' );
			var dryRun    = document.getElementById( 'listora-csv-import-dryrun' );
			var status    = document.getElementById( 'listora-csv-import-status' );

			if ( ! typeSlug || ! typeSlug.value ) {
				setStatus( status, t( 'selectListingType', 'Please select a listing type.' ), 'is-error' );
				return;
			}
			if ( ! fileInput || ! fileInput.files.length ) {
				setStatus( status, t( 'selectCsvFile', 'Please select a CSV file.' ), 'is-error' );
				return;
			}

			importBtn.disabled    = true;
			importBtn.textContent = t( 'importing', 'Importing...' );
			setStatus( status, '', null );

			var formData = new FormData();
			formData.append( 'file', fileInput.files[ 0 ] );
			formData.append( 'type_slug', typeSlug.value );
			formData.append( 'dry_run', dryRun && dryRun.checked ? '1' : '0' );
			formData.append( 'mapping', JSON.stringify( buildMapping() ) );

			if ( ! window.wp || ! window.wp.apiFetch ) {
				setStatus( status, t( 'apiFetchUnavailable', 'WordPress API helper is not loaded.' ), 'is-error' );
				importBtn.disabled    = false;
				importBtn.textContent = t( 'importCsv', 'Import CSV' );
				return;
			}
			abortableApiFetch( {
				path:   '/listora/v1/import/csv',
				method: 'POST',
				body:   formData,
				parse:  true,
			} ).then( function ( res ) {
				var msg = t( 'imported', 'Imported:' ) + ' ' + res.imported;
				if ( res.skipped ) { msg += ', ' + t( 'skipped', 'Skipped:' ) + ' ' + res.skipped; }
				if ( res.errors )  { msg += ', ' + t( 'errors', 'Errors:' ) + ' ' + res.errors; }
				if ( res.dry_run ) { msg += ' (' + t( 'dryRun', 'dry run' ) + ')'; }
				setStatus( status, msg, res.errors ? 'is-error' : 'is-success' );
				importBtn.textContent = t( 'importCsv', 'Import CSV' );
				importBtn.disabled    = false;
			} ).catch( function ( err ) {
				setStatus( status, ( err && err.message ) || t( 'importFailed', 'Import failed.' ), 'is-error' );
				importBtn.textContent = t( 'importCsv', 'Import CSV' );
				importBtn.disabled    = false;
			} );
		} );
	}

	/* ────────────────────────────────────────────────────────────────────
	   2. Reset / Export / Import settings (Tools tab footer + Import/Export tab)
	   Was: inline <script> at class-settings-page.php:590 (same block as #1)
	   These are exposed as window.* globals because they are wired via inline
	   onclick attributes in legacy markup; the bodies themselves live here so
	   no <script> is emitted by PHP. The underlying onclick="" attributes will
	   be replaced with addEventListener wiring in a follow-up pass.
	   ──────────────────────────────────────────────────────────────────── */
	function toast( message, type ) {
		if ( window.listoraToast ) {
			window.listoraToast( message, { type: type || 'info' } );
		}
	}

	window.listoraResetDefaults = function () {
		if ( typeof window.listoraConfirm !== 'function' || ! window.wp || ! window.wp.apiFetch ) {
			return;
		}
		window.listoraConfirm( {
			title:        t( 'resetTitle', 'Reset all settings?' ),
			message:      t( 'resetMessage', 'Every tab will be restored to its default value. This cannot be undone.' ),
			confirmLabel: t( 'resetConfirm', 'Reset settings' ),
			tone:         'danger',
		} ).then( function ( ok ) {
			if ( ! ok ) {
				return;
			}
			abortableApiFetch( { path: '/listora/v1/settings', method: 'DELETE' } )
				.then( function () {
					// Reload WITH a flag. A toast cannot survive the reload, and
					// staying silent after a destructive action is the worst
					// place to do it: the owner cannot tell whether the reset
					// ran, half-ran, or failed (BC 10167580523).
					var url = new URL( window.location.href );
					url.searchParams.set( 'listora_reset', '1' );
					window.location.href = url.toString();
				} )
				.catch( function ( err ) {
					toast( t( 'resetFailed', 'Reset failed:' ) + ' ' + ( ( err && err.message ) || err ), 'error' );
				} );
		} );
	};

	window.listoraExportSettings = function () {
		if ( ! window.wp || ! window.wp.apiFetch ) {
			return;
		}
		abortableApiFetch( { path: '/listora/v1/settings/export', parse: false } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( data ) {
				var blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
				var url  = URL.createObjectURL( blob );
				var a    = document.createElement( 'a' );
				a.href     = url;
				a.download = 'wb-listora-settings.json';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
			} )
			.catch( function ( err ) {
				var msg = isAbortError( err ) ? networkSlowMsg() : ( ( err && err.message ) || err );
				toast( t( 'exportFailed', 'Export failed:' ) + ' ' + msg, 'error' );
			} );
	};

	function doSettingsImport( data, statusEl ) {
		if ( ! statusEl || ! window.wp || ! window.wp.apiFetch ) {
			return;
		}
		setStatus( statusEl, t( 'importingSettings', 'Importing...' ), 'is-progress' );
		abortableApiFetch( { path: '/listora/v1/settings/import', method: 'POST', data: data } )
			.then( function () {
				setStatus( statusEl, t( 'importedSettings', 'Imported successfully!' ), 'is-success' );
				setTimeout( function () { window.location.reload(); }, 1000 );
			} )
			.catch( function ( err ) {
				var msg = isAbortError( err ) ? networkSlowMsg() : ( ( err && err.message ) || err );
				setStatus( statusEl, t( 'importSettingsFailed', 'Import failed:' ) + ' ' + msg, 'is-error' );
			} );
	}

	window.listoraImportSettings = function () {
		var fileInput = document.getElementById( 'listora-import-file' );
		var statusEl  = document.getElementById( 'listora-import-status' );

		if ( ! fileInput || ! fileInput.files.length ) {
			toast( t( 'selectJsonFile', 'Please select a JSON file first.' ), 'warning' );
			return;
		}

		var reader = new FileReader();
		reader.onload = function ( e ) {
			var data;
			try {
				data = JSON.parse( e.target.result );
			} catch ( err ) {
				toast( t( 'invalidJson', 'Invalid JSON file.' ), 'error' );
				return;
			}
			if ( typeof window.listoraConfirm !== 'function' ) {
				doSettingsImport( data, statusEl );
				return;
			}
			window.listoraConfirm( {
				title:        t( 'replaceTitle', 'Replace current settings?' ),
				message:      t( 'replaceMessage', 'Your current settings will be overwritten with values from the imported file.' ),
				confirmLabel: t( 'replaceConfirm', 'Replace settings' ),
				tone:         'danger',
			} ).then( function ( ok ) {
				if ( ok ) {
					doSettingsImport( data, statusEl );
				}
			} );
		};
		reader.readAsText( fileInput.files[ 0 ] );
	};

	/* ────────────────────────────────────────────────────────────────────
	   3. Copy-to-clipboard buttons (Credits / License / Webhook fields)
	   Was: inline <script> at class-settings-page.php:1235
	   ──────────────────────────────────────────────────────────────────── */
	function initCopyButtons() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.listora-copy-btn' );
			if ( ! btn ) {
				return;
			}
			e.preventDefault();
			var text = btn.getAttribute( 'data-copy-target' ) || '';
			if ( ! text ) {
				return;
			}
			var label    = btn.querySelector( '.listora-copy-btn__label' );
			var original = label ? label.textContent : '';

			var done = function () {
				if ( label ) {
					label.textContent = t( 'copied', 'Copied!' );
					setTimeout( function () { label.textContent = original; }, 1500 );
				}
			};

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done );
				return;
			}
			var input = btn.parentNode && btn.parentNode.querySelector( '.listora-copy-field__input' );
			if ( input ) {
				input.select();
				try { document.execCommand( 'copy' ); } catch ( err ) { /* ignored */ }
				done();
			}
		} );
	}

	/* ────────────────────────────────────────────────────────────────────
	   4. Submissions tab — beyond-limit radio + unlimited per-role toggle
	   Was: inline <script> at class-settings-page.php:1482
	   ──────────────────────────────────────────────────────────────────── */
	function initSubmissionLimits() {
		var radios = document.querySelectorAll( '.listora-beyond-limit-radio' );
		var row    = document.querySelector( '.listora-overflow-cost-row' );
		if ( radios.length && row ) {
			var sync = function () {
				var checked = document.querySelector( '.listora-beyond-limit-radio:checked' );
				row.classList.toggle( 'is-hidden', ! ( checked && checked.value === 'credits' ) );
			};
			radios.forEach( function ( r ) { r.addEventListener( 'change', sync ); } );
			sync();
		}

		document.querySelectorAll( '.listora-limit-unlimited' ).forEach( function ( cb ) {
			var role = cb.getAttribute( 'data-role' );
			if ( ! role ) {
				return;
			}
			var safeRole = ( window.CSS && CSS.escape ) ? CSS.escape( role ) : role;
			var numField = document.querySelector( '.listora-limit-count[data-role="' + safeRole + '"]' );
			if ( ! numField ) {
				return;
			}
			var toggle = function () {
				if ( cb.checked ) {
					numField.disabled = true;
					numField.value    = '';
				} else {
					numField.disabled = false;
					if ( ! numField.value ) {
						numField.value = '10';
					}
					numField.focus();
				}
			};
			cb.addEventListener( 'change', toggle );
		} );
	}

	/* ────────────────────────────────────────────────────────────────────
	   5. Notifications tab — Send Test (single consolidated tester)
	   Reads selected event from #listora-notification-test-event dropdown
	   and recipient from #listora-notification-test-recipient input.
	   ──────────────────────────────────────────────────────────────────── */
	function initNotificationTests() {
		var btn         = document.getElementById( 'listora-notification-test-send' );
		var eventEl     = document.getElementById( 'listora-notification-test-event' );
		var recipientEl = document.getElementById( 'listora-notification-test-recipient' );
		var status      = document.getElementById( 'listora-notification-test-status' );
		if ( ! btn || ! eventEl ) {
			return;
		}

		btn.addEventListener( 'click', function ( ev ) {
			ev.preventDefault();
			if ( ! window.wp || ! window.wp.apiFetch ) {
				return;
			}
			setStatus( status, t( 'sending', 'Sending…' ), 'is-progress' );
			btn.disabled = true;

			abortableApiFetch( {
				path:   '/listora/v1/settings/notifications/test',
				method: 'POST',
				data:   {
					event_key:       eventEl.value,
					recipient_email: recipientEl ? recipientEl.value : '',
				},
			} ).then( function ( res ) {
				if ( res && res.sent ) {
					setStatus( status, t( 'sent', 'Sent' ), 'is-success' );
				} else {
					setStatus( status, t( 'failed', 'Failed:' ) + ' ' + ( ( res && res.error ) || '' ), 'is-error' );
				}
			} ).catch( function ( err ) {
				setStatus( status, t( 'errored', 'Error:' ) + ' ' + ( ( err && err.message ) || err ), 'is-error' );
			} ).finally( function () {
				btn.disabled = false;
			} );
		} );
	}

	/* ────────────────────────────────────────────────────────────────────
	   7. Migration — Import / Export tab
	   Was: inline <script> at class-settings-page.php:2466
	   ──────────────────────────────────────────────────────────────────── */
	function initMigration() {
		var buttons = document.querySelectorAll( '.listora-migration-start' );
		if ( ! buttons.length ) {
			return;
		}

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var source = btn.dataset.source;
				var dryRun = document.querySelector( '.listora-migration-dryrun[data-source="' + source + '"]' );
				var isDry  = dryRun ? dryRun.checked : false;

				buttons.forEach( function ( b ) { b.disabled = true; } );

				var progress = document.getElementById( 'listora-progress-' + source );
				var fill     = document.getElementById( 'listora-fill-' + source );
				var stats    = document.getElementById( 'listora-stats-' + source );
				var pctEl    = document.getElementById( 'listora-pct-' + source );
				var resultEl = document.getElementById( 'listora-result-' + source );

				if ( progress ) { progress.classList.add( 'is-active' ); }
				if ( resultEl ) { resultEl.classList.remove( 'is-visible' ); }
				if ( fill ) {
					fill.classList.remove( 'listora-migration-progress__fill--complete' );
					fill.style.setProperty( '--listora-migration-progress', '0%' );
				}
				if ( stats ) { stats.textContent = t( 'migStarting', 'Starting...' ); }

				btn.textContent = t( 'migMigrating', 'Migrating...' );
				btn.classList.add( 'listora-btn--migrating' );

				var formData = new FormData();
				formData.append( 'action', 'listora_run_migration' );
				formData.append( '_nonce', settings.migrationNonce || '' );
				formData.append( 'source', source );
				formData.append( 'dry_run', isDry ? '1' : '0' );

				abortableFetch( settings.ajaxUrl || window.ajaxurl, { method: 'POST', body: formData }, 60000 )
					.then( function ( response ) { return response.json(); } )
					.then( function ( data ) {
						if ( data.success ) {
							var res = data.data;
							if ( fill ) {
								fill.style.setProperty( '--listora-migration-progress', '100%' );
								fill.classList.add( 'listora-migration-progress__fill--complete' );
							}
							if ( pctEl ) { pctEl.textContent = '100%'; }

							var msg = t( 'migImported', 'Imported:' ) + ' ' + res.imported
								+ ', ' + t( 'migSkipped', 'Skipped:' ) + ' ' + res.skipped
								+ ', ' + t( 'migErrors', 'Errors:' ) + ' ' + res.errors;
							if ( stats ) { stats.textContent = msg; }

							var resultClass = res.errors > 0
								? 'listora-migration-result--error'
								: ( isDry ? 'listora-migration-result--dryrun' : 'listora-migration-result--success' );
							var resultMsg = res.errors > 0
								? t( 'migErrored', 'Migration completed with errors. Check the logs for details.' )
								: ( isDry
									? t( 'migDryDone', 'Dry run complete. No data was imported. Run again without dry run to import.' )
									: t( 'migDone', 'Migration completed successfully.' ) );

							if ( resultEl ) {
								resultEl.className   = 'listora-migration-result is-visible ' + resultClass;
								resultEl.textContent = resultMsg;
							}
							btn.textContent = t( 'migComplete', 'Complete' );
						} else {
							var serverMsg = ( data.data && data.data.message ) || t( 'migFailed', 'Migration failed.' );
							if ( stats ) { stats.textContent = serverMsg; }
							if ( resultEl ) {
								resultEl.className   = 'listora-migration-result is-visible listora-migration-result--error';
								resultEl.textContent = serverMsg;
							}
							btn.textContent = t( 'migStart', 'Start Migration' );
						}
						btn.classList.remove( 'listora-btn--migrating' );
					} )
					.catch( function ( err ) {
						if ( stats ) { stats.textContent = t( 'migRequestFailed', 'Request failed.' ); }
						if ( resultEl ) {
							resultEl.className   = 'listora-migration-result is-visible listora-migration-result--error';
							resultEl.textContent = ( err && err.message ) || t( 'migNetwork', 'Network error. Please try again.' );
						}
						btn.textContent = t( 'migStart', 'Start Migration' );
						btn.classList.remove( 'listora-btn--migrating' );
					} )
					.finally( function () {
						buttons.forEach( function ( b ) { b.disabled = false; } );
					} );
			} );
		} );
	}

	/**
	 * Warn before leaving a settings tab with unsaved edits.
	 *
	 * Each tab now saves every section with one Save Changes (card
	 * 10337174947); an owner who edits Stripe keys and navigates away should
	 * hear about it, not find the fields empty on the next visit.
	 */
	function initUnsavedGuard() {
		var dirty = false;
		document.querySelectorAll( '.listora-settings-section form' ).forEach( function ( form ) {
			var mark = function ( e ) {
				if ( e.target && e.target.name ) {
					dirty = true;
				}
			};
			form.addEventListener( 'input', mark );
			form.addEventListener( 'change', mark );
			form.addEventListener( 'submit', function () {
				dirty = false;
			} );
		} );
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );
	}

	ready( function () {
		initUnsavedGuard();
		initCsvExportImport();
		initCopyButtons();
		initSubmissionLimits();
		initNotificationTests();
		initMigration();
	} );
}() );
