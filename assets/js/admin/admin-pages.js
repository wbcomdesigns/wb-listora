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
			fetch( window.ajaxurl, { method: 'POST', body: formData } ).then( function () {
				if ( card ) {
					card.classList.add( 'is-dismissed' );
					setTimeout( function () { card.remove(); }, 500 );
				}
			} );
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

				window.wp.apiFetch( {
					path:   '/listora/v1/reviews/' + reviewId + '/reply',
					method: 'POST',
					data:   { content: content },
				} ).then( function () {
					setStatus( status, t( 'replySaved', 'Reply saved.' ), 'is-success' );
					btn.textContent = t( 'replySend', 'Send Reply' );
					btn.disabled    = false;
				} ).catch( function ( err ) {
					setStatus( status, ( err && err.message ) || t( 'replyFailed', 'Failed to save reply.' ), 'is-error' );
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
	   ────────────────────────────────────────────────────────────────── */
	function initImportExport() {
		var exportBtn = document.getElementById( 'listora-export-btn' );
		if ( exportBtn ) {
			exportBtn.addEventListener( 'click', function () {
				var typeSel = document.getElementById( 'listora-export-type' );
				var status  = document.getElementById( 'listora-export-status' );
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

				var a    = document.createElement( 'a' );
				a.href   = url;
				a.download = '';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );

				setStatus( status, t( 'exportStarted', 'Download started.' ), 'is-success' );
				exportBtn.disabled = false;
			} );
		}

		var importBtn = document.getElementById( 'listora-import-btn' );
		if ( importBtn ) {
			importBtn.addEventListener( 'click', function () {
				var typeSel  = document.getElementById( 'listora-import-type' );
				var fileEl   = document.getElementById( 'listora-import-file' );
				var dryEl    = document.getElementById( 'listora-import-dryrun' );
				var status   = document.getElementById( 'listora-import-status' );
				var typeSlug = typeSel ? typeSel.value : '';
				var dryRun   = dryEl && dryEl.checked;

				if ( ! typeSlug ) {
					setStatus( status, t( 'importNoType', 'Please select a listing type.' ), 'is-error' );
					return;
				}
				if ( ! fileEl || ! fileEl.files.length ) {
					setStatus( status, t( 'importNoFile', 'Please select a CSV file.' ), 'is-error' );
					return;
				}

				importBtn.disabled    = true;
				importBtn.textContent = t( 'importImporting', 'Importing...' );
				setStatus( status, '', '' );

				var formData = new FormData();
				formData.append( 'file', fileEl.files[ 0 ] );
				formData.append( 'type_slug', typeSlug );
				formData.append( 'dry_run', dryRun ? '1' : '0' );
				formData.append( 'mapping', JSON.stringify( { 0: 'title', 1: 'description', 2: 'category', 3: 'tags' } ) );

				if ( ! window.wp || ! window.wp.apiFetch ) {
					return;
				}

				window.wp.apiFetch( {
					path:   '/listora/v1/import/csv',
					method: 'POST',
					body:   formData,
					parse:  true,
				} ).then( function ( res ) {
					var msg = t( 'importImported', 'Imported:' ) + ' ' + res.imported;
					if ( res.skipped ) {
						msg += ', ' + t( 'importSkipped', 'Skipped:' ) + ' ' + res.skipped;
					}
					if ( res.errors ) {
						msg += ', ' + t( 'importErrors', 'Errors:' ) + ' ' + res.errors;
					}
					if ( res.dry_run ) {
						msg += ' (' + t( 'importDryRun', 'dry run' ) + ')';
					}
					setStatus( status, msg, res.errors ? 'is-error' : 'is-success' );
					importBtn.textContent = t( 'importBtn', 'Import CSV' );
					importBtn.disabled    = false;
				} ).catch( function ( err ) {
					setStatus( status, ( err && err.message ) || t( 'importFailed', 'Import failed.' ), 'is-error' );
					importBtn.textContent = t( 'importBtn', 'Import CSV' );
					importBtn.disabled    = false;
				} );
			} );
		}
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

				fetch( window.ajaxurl, { method: 'POST', body: formData } )
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
						var failMsg = ( err && err.message ) || t( 'migrationNetworkErr', 'Network error. Please try again.' );
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
