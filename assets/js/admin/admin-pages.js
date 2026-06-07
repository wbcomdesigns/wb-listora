/**
 * Listora — admin page behaviours (consolidated).
 *
 * Replaces four inline <script> blocks previously emitted from
 * includes/admin/class-admin.php (Rule 11: no inline JS in PHP).
 * Each module is idempotent — only runs when its DOM target exists,
 * so the file can be enqueued safely across all admin pages.
 *
 * Localised data lives on `window.listoraAdminPages`:
 *   - i18n     : translated UI strings.
 *   - endpoints: REST + ajax URLs and nonces (per-page).
 *
 * @package WBListora
 */
( function () {
	'use strict';

	var data = window.listoraAdminPages || {};
	var i18n = data.i18n || {};
	var endpoints = data.endpoints || {};

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

	function t( key, fallback ) {
		return ( i18n && i18n[ key ] ) || fallback;
	}

	/* ──────────────────────────────────────────────────────────────────
	   1. Onboarding checklist — dismiss button
	   ────────────────────────────────────────────────────────────────── */
	function initOnboardingDismiss() {
		var btn = document.getElementById( 'listora-dismiss-onboarding' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var card = document.getElementById( 'listora-onboarding-checklist' );
			if ( card ) {
				card.classList.add( 'is-dismissing' );
			}
			var formData = new FormData();
			formData.append( 'action', 'listora_dismiss_onboarding' );
			formData.append( '_nonce', btn.dataset.nonce );
			abortableFetch( window.ajaxurl, { method: 'POST', body: formData } ).then( function () {
				if ( card ) {
					card.classList.add( 'is-dismissed' );
					setTimeout( function () { card.remove(); }, 500 );
				}
			} ).catch( function () { /* dismiss is fire-and-forget */ } );
		} );
	}

	/* ──────────────────────────────────────────────────────────────────
	   2. Reviews list — inline reply toggle + REST submission
	   ────────────────────────────────────────────────────────────────── */
	function initReviewReply() {
		var toggles = document.querySelectorAll( '.listora-review-reply-toggle' );
		if ( ! toggles.length ) {
			return;
		}

		toggles.forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var reviewId = this.getAttribute( 'data-review-id' );
				var row      = document.getElementById( 'listora-reply-row-' + reviewId );
				if ( ! row ) {
					return;
				}
				row.hidden = ! row.hidden;
				if ( ! row.hidden ) {
					var ta = row.querySelector( 'textarea' );
					if ( ta ) {
						ta.focus();
					}
				}
			} );
		} );

		document.querySelectorAll( '.listora-reply-submit' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var form     = this.closest( '.listora-reply-form' );
				var reviewId = form.getAttribute( 'data-review-id' );
				var textarea = form.querySelector( '.listora-reply-textarea' );
				var status   = form.querySelector( '.listora-reply-status' );
				var content  = textarea.value.trim();

				if ( ! content ) {
					setStatus( status, t( 'replyEmpty', 'Please enter a reply.' ), 'is-error' );
					return;
				}

				btn.disabled    = true;
				btn.textContent = t( 'replySending', 'Sending...' );
				setStatus( status, '', '' );

				if ( ! window.wp || ! window.wp.apiFetch ) {
					return;
				}

				abortableApiFetch( {
					path:   '/listora/v1/reviews/' + reviewId + '/reply',
					method: 'POST',
					data:   { content: content },
				} ).then( function () {
					setStatus( status, t( 'replySaved', 'Reply saved.' ), 'is-success' );
					btn.textContent = t( 'replySend', 'Send Reply' );
					btn.disabled    = false;
				} ).catch( function ( err ) {
					var msg = isAbortError( err )
						? ( i18n.networkSlow || 'Network is slow — please try again.' )
						: ( ( err && err.message ) || t( 'replyFailed', 'Failed to save reply.' ) );
					setStatus( status, msg, 'is-error' );
					btn.textContent = t( 'replySend', 'Send Reply' );
					btn.disabled    = false;
				} );
			} );
		} );
	}

	function setStatus( el, text, cls ) {
		if ( ! el ) {
			return;
		}
		el.textContent = text;
		el.classList.remove( 'is-error', 'is-success', 'is-progress' );
		if ( cls ) {
			el.classList.add( cls );
		}
	}

	/* ──────────────────────────────────────────────────────────────────
	   3. Import / Export tools

	   Wires the Import/Export settings card:
	     • CSV export  — streams a download from the REST export route.
	     • CSV import  — parses headers client-side, builds a column→field
	                     mapping UI, queues a resumable Background_Import run,
	                     then polls progress and drives the progress widget.

	   The selectors below MATCH the IDs render_import_export_tab() emits
	   (listora-csv-*). An earlier version bound listora-export-btn /
	   listora-import-btn, which the template never rendered — both buttons
	   were silently dead. This is the wiring fix (action-audit Check 1).
	   ────────────────────────────────────────────────────────────────── */
	function initImportExport() {
		initCsvExport();
		initCsvImport();
	}

	function initCsvExport() {
		var exportBtn = document.getElementById( 'listora-csv-export-btn' );
		if ( ! exportBtn ) {
			return;
		}
		exportBtn.addEventListener( 'click', function () {
			var typeSel = document.getElementById( 'listora-csv-export-type' );
			var status  = document.getElementById( 'listora-csv-export-status' );
			var params  = new URLSearchParams( { include_meta: '1' } );
			if ( typeSel && typeSel.value ) {
				params.set( 'type', typeSel.value );
			}

			setStatus( status, t( 'exportGenerating', 'Generating export...' ), 'is-progress' );
			exportBtn.disabled = true;

			var url = ( endpoints.exportCsv || '' ) + '?' + params.toString();
			if ( endpoints.restNonce ) {
				url += '&_wpnonce=' + encodeURIComponent( endpoints.restNonce );
			}

			var a      = document.createElement( 'a' );
			a.href     = url;
			a.download = '';
			document.body.appendChild( a );
			a.click();
			document.body.removeChild( a );

			setStatus( status, t( 'exportStarted', 'Download started.' ), 'is-success' );
			exportBtn.disabled = false;
		} );
	}

	function initCsvImport() {
		var importBtn = document.getElementById( 'listora-csv-import-btn' );
		var card      = document.getElementById( 'listora-csv-import-card' );
		if ( ! importBtn || ! card ) {
			return;
		}

		var typeSel    = document.getElementById( 'listora-csv-import-type' );
		var fileEl     = document.getElementById( 'listora-csv-import-file' );
		var status     = document.getElementById( 'listora-csv-import-status' );
		var mappingBox = document.getElementById( 'listora-csv-import-mapping' );

		// Mappable field key→label map, rendered server-side from
		// CSV_Importer::get_mappable_fields() so the dropdown options stay in
		// lockstep with what the importer actually accepts.
		var fields = {};
		try {
			fields = JSON.parse( card.getAttribute( 'data-mappable-fields' ) || '{}' );
		} catch ( e ) {
			fields = {};
		}

		var headers = []; // CSV header cells, in column order.

		// When a file is chosen, read its first line and build the mapping UI.
		if ( fileEl ) {
			fileEl.addEventListener( 'change', function () {
				headers = [];
				if ( mappingBox ) {
					mappingBox.innerHTML = '';
					mappingBox.classList.add( 'is-hidden' );
				}
				setStatus( status, '', '' );

				if ( ! fileEl.files || ! fileEl.files.length ) {
					return;
				}

				var reader = new FileReader();
				reader.onload = function ( ev ) {
					headers = parseCsvHeader( String( ev.target.result || '' ) );
					buildMappingUi( mappingBox, headers, fields );
				};
				reader.onerror = function () {
					setStatus( status, t( 'importReadFailed', 'Could not read the selected file.' ), 'is-error' );
				};
				// Only the header line is needed; reading a slice keeps large
				// files from being pulled entirely into the browser.
				reader.readAsText( fileEl.files[ 0 ].slice( 0, 65536 ) );
			} );
		}

		importBtn.addEventListener( 'click', function () {
			var typeSlug = typeSel ? typeSel.value : '';

			if ( ! typeSlug ) {
				setStatus( status, t( 'importNoType', 'Please select a listing type.' ), 'is-error' );
				return;
			}
			if ( ! fileEl || ! fileEl.files.length ) {
				setStatus( status, t( 'importNoFile', 'Please select a CSV file.' ), 'is-error' );
				return;
			}

			var mapping = collectMapping( mappingBox, headers );
			if ( ! hasMappedField( mapping ) ) {
				setStatus( status, t( 'importNoMapping', 'Map at least one column to a listing field.' ), 'is-error' );
				return;
			}

			if ( ! window.wp || ! window.wp.apiFetch ) {
				return;
			}

			importBtn.disabled    = true;
			importBtn.textContent = t( 'importImporting', 'Importing...' );
			setStatus( status, '', '' );

			var formData = new FormData();
			formData.append( 'file', fileEl.files[ 0 ] );
			formData.append( 'type_slug', typeSlug );
			formData.append( 'mapping', JSON.stringify( mapping ) );

			abortableApiFetch( {
				path:   '/listora/v1/import/queue/csv',
				method: 'POST',
				body:   formData,
				parse:  true,
			}, 60000 ).then( function ( res ) {
				if ( ! res || ! res.run_id ) {
					setStatus( status, t( 'importFailed', 'Import failed.' ), 'is-error' );
					resetImportBtn( importBtn );
					return;
				}
				setStatus( status, t( 'importQueued', 'Import queued.' ), 'is-progress' );
				pollImportProgress( res.run_id, importBtn, status );
			} ).catch( function ( err ) {
				var msg = isAbortError( err )
					? ( i18n.networkSlow || 'Network is slow — please try again.' )
					: ( ( err && err.message ) || t( 'importFailed', 'Import failed.' ) );
				setStatus( status, msg, 'is-error' );
				resetImportBtn( importBtn );
			} );
		} );
	}

	/* ── CSV header parsing + mapping UI helpers ── */

	// Minimal RFC-4180-ish parse of a single header row (handles quoted cells
	// with embedded commas). Only the first record is consumed.
	function parseCsvHeader( text ) {
		var line = text.split( /\r\n|\n|\r/ )[ 0 ] || '';
		var out  = [];
		var cur  = '';
		var inQ  = false;
		for ( var i = 0; i < line.length; i++ ) {
			var ch = line.charAt( i );
			if ( inQ ) {
				if ( ch === '"' && line.charAt( i + 1 ) === '"' ) {
					cur += '"';
					i++;
				} else if ( ch === '"' ) {
					inQ = false;
				} else {
					cur += ch;
				}
			} else if ( ch === '"' ) {
				inQ = true;
			} else if ( ch === ',' ) {
				out.push( cur.trim() );
				cur = '';
			} else {
				cur += ch;
			}
		}
		out.push( cur.trim() );
		return out;
	}

	function buildMappingUi( box, headers, fields ) {
		if ( ! box ) {
			return;
		}
		box.innerHTML = '';
		if ( ! headers.length ) {
			box.classList.add( 'is-hidden' );
			return;
		}

		var title = document.createElement( 'p' );
		title.className   = 'listora-impex__mapping-title';
		title.textContent = t( 'importMapTitle', 'Map columns to fields' );
		box.appendChild( title );

		var hint = document.createElement( 'p' );
		hint.className   = 'listora-impex__mapping-hint';
		hint.textContent = t( 'importMapHint', 'Choose the listing field each CSV column fills. Leave on “Skip” to ignore a column.' );
		box.appendChild( hint );

		headers.forEach( function ( header, idx ) {
			var row = document.createElement( 'div' );
			row.className = 'listora-impex__mapping-row';

			var col = document.createElement( 'span' );
			col.className   = 'listora-impex__mapping-col';
			col.textContent = header || ( t( 'importColumn', 'Column' ) + ' ' + ( idx + 1 ) );
			col.title       = col.textContent;
			row.appendChild( col );

			var select = document.createElement( 'select' );
			select.className = 'listora-impex__mapping-select';
			select.setAttribute( 'data-col', String( idx ) );
			var labelledBy = 'listora-csv-map-' + idx;
			select.setAttribute( 'aria-label', col.textContent );
			select.id = labelledBy;

			Object.keys( fields ).forEach( function ( key ) {
				var opt = document.createElement( 'option' );
				opt.value       = key;
				opt.textContent = fields[ key ];
				// Auto-match a column to a field when the header text equals
				// the field key or its label (case-insensitive).
				if (
					key !== '_skip' &&
					(
						key.toLowerCase() === String( header ).toLowerCase() ||
						String( fields[ key ] ).toLowerCase() === String( header ).toLowerCase()
					)
				) {
					opt.selected = true;
				}
				select.appendChild( opt );
			} );
			row.appendChild( select );

			box.appendChild( row );
		} );

		box.classList.remove( 'is-hidden' );
	}

	function collectMapping( box, headers ) {
		var mapping = {};
		if ( ! box ) {
			return mapping;
		}
		box.querySelectorAll( '.listora-impex__mapping-select' ).forEach( function ( sel ) {
			var col = sel.getAttribute( 'data-col' );
			if ( col === null || sel.value === '_skip' || sel.value === '' ) {
				return;
			}
			mapping[ col ] = sel.value;
		} );
		return mapping;
	}

	function hasMappedField( mapping ) {
		return Object.keys( mapping ).length > 0;
	}

	/* ── Background-import progress polling ── */

	function pollImportProgress( runId, importBtn, status ) {
		var box = document.getElementById( 'listora-csv-import-progress' );
		if ( box ) {
			box.hidden = false;
			box.classList.remove( 'is-done', 'is-failed' );
		}

		var path  = '/listora/v1/import/progress/' + encodeURIComponent( runId );
		var tries = 0;

		function tick() {
			tries++;
			abortableApiFetch( { path: path, method: 'GET' }, 15000 ).then( function ( p ) {
				updateProgressWidget( box, p );

				if ( p && p.done ) {
					finishImport( box, p, importBtn, status );
					return;
				}
				// Cap polling so a stuck run can't loop forever (~10 min).
				if ( tries < 400 ) {
					setTimeout( tick, 1500 );
				} else {
					setStatus( status, t( 'importStillRunning', 'Import is still running in the background.' ), 'is-progress' );
					resetImportBtn( importBtn );
				}
			} ).catch( function () {
				// A transient poll failure shouldn't abort the run — retry a few
				// times, then surface a soft message.
				if ( tries < 400 ) {
					setTimeout( tick, 2500 );
				} else {
					setStatus( status, t( 'importProgressLost', 'Lost track of the import. Refresh to check its status.' ), 'is-error' );
					resetImportBtn( importBtn );
				}
			} );
		}

		tick();
	}

	function updateProgressWidget( box, p ) {
		if ( ! box || ! p ) {
			return;
		}
		var pct   = typeof p.percent === 'number' ? p.percent : 0;
		var bar   = box.querySelector( '.listora-import-progress__bar' );
		var track = box.querySelector( '.listora-import-progress__track' );
		var count = box.querySelector( '.listora-import-progress__count' );
		if ( bar ) {
			bar.style.inlineSize = pct + '%';
		}
		if ( track ) {
			track.setAttribute( 'aria-valuenow', String( pct ) );
		}
		if ( count ) {
			count.textContent = String( typeof p.imported === 'number' ? p.imported : 0 );
		}
	}

	function finishImport( box, p, importBtn, status ) {
		var failed = p.status === 'failed';
		if ( box ) {
			box.classList.add( failed ? 'is-failed' : 'is-done' );
			if ( ! failed ) {
				updateProgressWidget( box, { percent: 100, imported: p.imported } );
			}
		}

		var msg = t( 'importImported', 'Imported:' ) + ' ' + ( p.imported || 0 );
		if ( p.skipped ) {
			msg += ', ' + t( 'importSkipped', 'Skipped:' ) + ' ' + p.skipped;
		}
		if ( p.errors ) {
			msg += ', ' + t( 'importErrors', 'Errors:' ) + ' ' + p.errors;
		}
		setStatus( status, msg, ( failed || p.errors ) ? 'is-error' : 'is-success' );
		resetImportBtn( importBtn );
	}

	function resetImportBtn( importBtn ) {
		if ( ! importBtn ) {
			return;
		}
		importBtn.textContent = t( 'importBtn', 'Import CSV' );
		importBtn.disabled    = false;
	}

	/* ──────────────────────────────────────────────────────────────────
	   4. Migration — start migration buttons
	   ────────────────────────────────────────────────────────────────── */
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

				if ( progress ) progress.classList.add( 'is-active' );
				if ( resultEl ) resultEl.classList.remove( 'is-visible' );
				if ( fill ) fill.style.setProperty( '--listora-progress', '0%' );
				if ( stats ) stats.textContent = t( 'migrationStarting', 'Starting...' );

				btn.textContent = t( 'migrationMigrating', 'Migrating...' );
				btn.classList.add( 'listora-btn--migrating' );

				var formData = new FormData();
				formData.append( 'action', 'listora_run_migration' );
				formData.append( '_nonce', endpoints.migrationNonce || '' );
				formData.append( 'source', source );
				formData.append( 'dry_run', isDry ? '1' : '0' );

				abortableFetch( window.ajaxurl, { method: 'POST', body: formData }, 60000 )
					.then( function ( r ) { return r.json(); } )
					.then( function ( data ) {
						if ( data.success ) {
							var res = data.data;
							if ( fill ) {
								fill.style.setProperty( '--listora-progress', '100%' );
								fill.classList.add( 'listora-migration-progress__fill--complete' );
							}
							if ( pctEl ) pctEl.textContent = '100%';

							var msg = t( 'migrationImported', 'Imported:' ) + ' ' + res.imported;
							msg    += ', ' + t( 'migrationSkipped', 'Skipped:' ) + ' ' + res.skipped;
							msg    += ', ' + t( 'migrationErrors', 'Errors:' ) + ' ' + res.errors;
							if ( stats ) stats.textContent = msg;

							var resultClass = res.errors > 0
								? 'listora-migration-result--error'
								: ( isDry ? 'listora-migration-result--dryrun' : 'listora-migration-result--success' );
							var resultMsg = res.errors > 0
								? t( 'migrationErroredMsg', 'Migration completed with errors. Check the logs for details.' )
								: ( isDry
									? t( 'migrationDryrunMsg', 'Dry run complete. No data was imported. Run again without dry run to import.' )
									: t( 'migrationDoneMsg', 'Migration completed successfully.' ) );

							if ( resultEl ) {
								resultEl.className   = 'listora-migration-result is-visible ' + resultClass;
								resultEl.textContent = resultMsg;
							}

							btn.textContent = t( 'migrationComplete', 'Complete' );
							btn.classList.remove( 'listora-btn--migrating' );
						} else {
							var failMsg = ( data.data && data.data.message ) || t( 'migrationFailed', 'Migration failed.' );
							if ( stats ) stats.textContent = failMsg;
							if ( resultEl ) {
								resultEl.className   = 'listora-migration-result is-visible listora-migration-result--error';
								resultEl.textContent = failMsg;
							}
							btn.textContent = t( 'migrationStart', 'Start Migration' );
							btn.classList.remove( 'listora-btn--migrating' );
						}
						buttons.forEach( function ( b ) { b.disabled = false; } );
					} )
					.catch( function ( err ) {
						var failMsg = isAbortError( err )
							? ( i18n.networkSlow || 'Network is slow — please try again.' )
							: ( ( err && err.message ) || t( 'migrationNetworkErr', 'Network error. Please try again.' ) );
						if ( stats ) stats.textContent = t( 'migrationRequestFailed', 'Request failed.' );
						if ( resultEl ) {
							resultEl.className   = 'listora-migration-result is-visible listora-migration-result--error';
							resultEl.textContent = failMsg;
						}
						btn.textContent = t( 'migrationStart', 'Start Migration' );
						btn.classList.remove( 'listora-btn--migrating' );
						buttons.forEach( function ( b ) { b.disabled = false; } );
					} );
			} );
		} );
	}

	function init() {
		initOnboardingDismiss();
		initReviewReply();
		initImportExport();
		initMigration();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
