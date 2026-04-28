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
		importBtn.addEventListener( 'click', function () {
			var typeSlug  = document.getElementById( 'listora-csv-import-type' );
			var fileInput = document.getElementById( 'listora-csv-import-file' );
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
			formData.append( 'mapping', JSON.stringify( { 0: 'title', 1: 'description', 2: 'category', 3: 'tags' } ) );

			if ( ! window.wp || ! window.wp.apiFetch ) {
				setStatus( status, t( 'apiFetchUnavailable', 'WordPress API helper is not loaded.' ), 'is-error' );
				importBtn.disabled    = false;
				importBtn.textContent = t( 'importCsv', 'Import CSV' );
				return;
			}
			window.wp.apiFetch( {
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
			window.wp.apiFetch( { path: '/listora/v1/settings', method: 'DELETE' } )
				.then( function () { window.location.reload(); } )
				.catch( function ( err ) {
					toast( t( 'resetFailed', 'Reset failed:' ) + ' ' + ( ( err && err.message ) || err ), 'error' );
				} );
		} );
	};

	window.listoraExportSettings = function () {
		if ( ! window.wp || ! window.wp.apiFetch ) {
			return;
		}
		window.wp.apiFetch( { path: '/listora/v1/settings/export', parse: false } )
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
				toast( t( 'exportFailed', 'Export failed:' ) + ' ' + ( ( err && err.message ) || err ), 'error' );
			} );
	};

	function doSettingsImport( data, statusEl ) {
		if ( ! statusEl || ! window.wp || ! window.wp.apiFetch ) {
			return;
		}
		setStatus( statusEl, t( 'importingSettings', 'Importing...' ), 'is-progress' );
		window.wp.apiFetch( { path: '/listora/v1/settings/import', method: 'POST', data: data } )
			.then( function () {
				setStatus( statusEl, t( 'importedSettings', 'Imported successfully!' ), 'is-success' );
				setTimeout( function () { window.location.reload(); }, 1000 );
			} )
			.catch( function ( err ) {
				setStatus( statusEl, t( 'importSettingsFailed', 'Import failed:' ) + ' ' + ( ( err && err.message ) || err ), 'is-error' );
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

			window.wp.apiFetch( {
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
	   6. Email Log standalone admin page — fetch / clear / render log table
	   Was: inline <script> at class-settings-page.php:1785 (originally a
	   settings tab; promoted to its own submenu per Rule 1).
	   ──────────────────────────────────────────────────────────────────── */
	function initNotificationLog() {
		var logEl      = document.getElementById( 'listora-notification-log' );
		var refreshBtn = document.getElementById( 'listora-notification-log-refresh' );
		var clearBtn   = document.getElementById( 'listora-notification-log-clear' );
		if ( ! logEl ) {
			return;
		}

		function clearChildren( node ) {
			while ( node.firstChild ) {
				node.removeChild( node.firstChild );
			}
		}

		function descParagraph( msg, isError ) {
			var p = document.createElement( 'p' );
			p.className = 'description' + ( isError ? ' is-error' : '' );
			p.textContent = msg;
			return p;
		}

		function buildLogTable( entries ) {
			var table   = document.createElement( 'table' );
			var thead   = document.createElement( 'thead' );
			var headRow = document.createElement( 'tr' );
			[
				t( 'logSentAt', 'Sent At (UTC)' ),
				t( 'logEvent', 'Event' ),
				t( 'logRecipient', 'Recipient' ),
				t( 'logSubject', 'Subject' ),
				t( 'logResult', 'Result' ),
			].forEach( function ( label ) {
				var th = document.createElement( 'th' );
				th.textContent = label;
				headRow.appendChild( th );
			} );
			thead.appendChild( headRow );
			table.appendChild( thead );

			var tbody = document.createElement( 'tbody' );
			entries.forEach( function ( e ) {
				var tr = document.createElement( 'tr' );

				var sentTd = document.createElement( 'td' );
				sentTd.textContent = e.sent_at || '';
				tr.appendChild( sentTd );

				var eventTd   = document.createElement( 'td' );
				var eventCode = document.createElement( 'code' );
				eventCode.textContent = e.event_key || '';
				eventTd.appendChild( eventCode );
				tr.appendChild( eventTd );

				var recipientTd = document.createElement( 'td' );
				recipientTd.textContent = e.recipient || '';
				tr.appendChild( recipientTd );

				var subjectTd = document.createElement( 'td' );
				subjectTd.textContent = e.subject || '';
				tr.appendChild( subjectTd );

				var resultTd   = document.createElement( 'td' );
				var resultSpan = document.createElement( 'span' );
				if ( e.success ) {
					resultSpan.className = 'is-success';
					resultSpan.textContent = t( 'sent', 'Sent' );
				} else {
					resultSpan.className = 'is-error';
					resultSpan.textContent = t( 'failed', 'Failed' ).replace( /:$/, '' ) + ( e.error ? ': ' + e.error : '' );
				}
				resultTd.appendChild( resultSpan );
				tr.appendChild( resultTd );

				tbody.appendChild( tr );
			} );
			table.appendChild( tbody );
			return table;
		}

		function renderLog( payload ) {
			clearChildren( logEl );
			var entries = ( payload && payload.entries ) || [];
			if ( ! entries.length ) {
				logEl.appendChild( descParagraph(
					t( 'logEmpty', 'No activity yet. Click "Send Test" on any event in the Notifications tab to record an entry.' ),
					false
				) );
				return;
			}
			logEl.appendChild( buildLogTable( entries ) );
		}

		function loadLog() {
			if ( ! window.wp || ! window.wp.apiFetch ) {
				return;
			}
			window.wp.apiFetch( { path: '/listora/v1/settings/notifications/log' } )
				.then( renderLog )
				.catch( function ( err ) {
					clearChildren( logEl );
					logEl.appendChild( descParagraph(
						t( 'logFailed', 'Failed to load log:' ) + ' ' + ( ( err && err.message ) || err ),
						true
					) );
				} );
		}

		if ( refreshBtn ) {
			refreshBtn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				loadLog();
			} );
		}
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				if ( ! window.wp || ! window.wp.apiFetch ) {
					return;
				}
				window.wp.apiFetch( {
					path:   '/listora/v1/settings/notifications/log',
					method: 'DELETE',
				} ).then( loadLog );
			} );
		}
		loadLog();
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

				fetch( settings.ajaxUrl || window.ajaxurl, { method: 'POST', body: formData } )
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

	ready( function () {
		initCsvExportImport();
		initCopyButtons();
		initSubmissionLimits();
		initNotificationTests();
		initNotificationLog();
		initMigration();
	} );
}() );
