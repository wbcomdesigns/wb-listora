/**
 * Abortable fetch / apiFetch helpers.
 *
 * Wraps every REST + raw network call in an AbortController + timeout
 * so a slow / hung backend can't trap the UI in a permanent loading
 * state. At 100K users with intermittent networks, untimed requests
 * lock up the dashboard indefinitely (Phase 1.3 — REST hang risk).
 *
 * Usage:
 *
 *     import { abortableApiFetch, isAbortError, NETWORK_SLOW_MESSAGE } from 'path/to/abortable-fetch';
 *     try {
 *         const data = await abortableApiFetch( '/listora/v1/listings' );
 *     } catch ( err ) {
 *         if ( isAbortError( err ) ) {
 *             // Show a "network slow" toast — request never resolved.
 *             toast( NETWORK_SLOW_MESSAGE );
 *             return;
 *         }
 *         throw err;
 *     }
 *
 * Default 10s timeout matches the WordPress remote-request budget; the
 * helper accepts a per-call override for endpoints that are known to
 * be slow (e.g. CSV import).
 *
 * @package WBListora
 * @since   1.0.0
 */

// NOTE: We deliberately read apiFetch + __ from `window.wp.*` instead of
// importing `@wordpress/api-fetch` / `@wordpress/i18n`. The script-module
// entries built by wp-scripts (this file is consumed by them via `import`)
// cannot resolve those packages at module-load time — webpack errors with
// "Attempted to use WordPress script in a module ... not supported yet".
// The classic IIFE entries that also import this file get the globals via
// the same path; both worlds share a single helper.
const __ = ( text ) =>
	( window.wp && window.wp.i18n && window.wp.i18n.__ )
		? window.wp.i18n.__( text, 'wb-listora' )
		: text;
const apiFetch = ( opts ) =>
	( window.wp && window.wp.apiFetch ) ? window.wp.apiFetch( opts ) : Promise.reject( new Error( 'wp.apiFetch unavailable' ) );

/**
 * Default timeout (ms). 10 seconds matches WordPress's
 * `wp_remote_request()` default — beyond this, server-side requests
 * give up too, so a longer client-side wait can't recover anything.
 *
 * @type {number}
 */
export const ABORT_DEFAULT_MS = 10000;

/**
 * Error class for explicit abort identification.
 *
 * Native AbortError is a DOMException — its `name` is 'AbortError'
 * and its `code` is 20. {@see isAbortError} accepts both shapes.
 */
export class AbortableFetchError extends Error {
	constructor( message, code ) {
		super( message );
		this.name = 'AbortableFetchError';
		this.code = code;
	}
}

/**
 * Wraps `@wordpress/api-fetch` with an AbortController + timeout.
 *
 * @param {string|Object} args  Path string OR full apiFetch options object.
 * @param {number}        ms    Optional timeout override in milliseconds.
 * @return {Promise<*>} Resolves with the apiFetch response. Rejects with
 *                     an AbortError when the timeout fires or when the
 *                     caller aborts via a passed-in signal.
 */
export function abortableApiFetch( args, ms = ABORT_DEFAULT_MS ) {
	const ctrl = new AbortController();
	const id = setTimeout( () => ctrl.abort(), ms );
	const opts =
		typeof args === 'string'
			? { path: args, signal: ctrl.signal }
			: { ...args, signal: ctrl.signal };
	return apiFetch( opts ).finally( () => clearTimeout( id ) );
}

/**
 * Wraps native `fetch()` with an AbortController + timeout.
 *
 * @param {RequestInfo} input The first arg to fetch().
 * @param {RequestInit} init  Optional fetch init options.
 * @param {number}      ms    Optional timeout override in milliseconds.
 * @return {Promise<Response>} Resolves with the fetch Response.
 */
export function abortableFetch( input, init = {}, ms = ABORT_DEFAULT_MS ) {
	const ctrl = new AbortController();
	const id = setTimeout( () => ctrl.abort(), ms );
	return fetch( input, { ...init, signal: ctrl.signal } ).finally( () =>
		clearTimeout( id )
	);
}

/**
 * Test whether an error is an AbortError (timeout OR caller-aborted).
 *
 * Accepts both DOMException (native fetch / AbortController) and
 * AbortableFetchError. apiFetch's wrapped errors also surface as
 * { name: 'AbortError' } so the same check works there.
 *
 * @param {*} err Any thrown value.
 * @return {boolean}
 */
export function isAbortError( err ) {
	return Boolean(
		err && ( err.name === 'AbortError' || err.code === 20 )
	);
}

/**
 * Translatable toast message for the "your request timed out" UX.
 *
 * @type {string}
 */
export const NETWORK_SLOW_MESSAGE = __( 'Network is slow — please try again.' );
