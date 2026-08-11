/**
 * Translation helpers for view scripts.
 *
 * Strings written into the DOM from JS have to come from the localized
 * `listoraI18n` object, not from literals in the bundle. A literal is invisible
 * to `wp i18n make-pot`, so it never reaches a .po file and no translation pass
 * can ever fix it — the catalogue reports 100% while a German site still shows
 * "Save Draft" and "Submitting...".
 *
 * `fmt()` exists because several of these strings were built by concatenation
 * (`'You need ' + n + ' credits'`). Concatenation is untranslatable: languages
 * order their clauses differently, so a translator needs the whole sentence with
 * numbered placeholders in it.
 *
 */

/**
 * Look up a localized string, falling back to the English source.
 *
 * The fallback is the same text that was previously hardcoded, so a missing
 * key degrades to today's behaviour rather than printing "undefined".
 *
 * @param {string} key      Key in the listoraI18n object.
 * @param {string} fallback English source text.
 * @return {string}         Localized string.
 */
export function t( key, fallback ) {
	const bag = typeof window !== 'undefined' ? window.listoraI18n : null;
	const val = bag && typeof bag[ key ] === 'string' ? bag[ key ] : '';
	return val || fallback;
}

/**
 * Fill %1$s / %2$s (and bare %s / %d) placeholders, in any order.
 *
 * Numbered placeholders are what make reordering possible for translators, so
 * they are resolved first; bare specifiers are supported for single-argument
 * strings where ordering cannot matter.
 *
 * @param {string}             template String containing placeholders.
 * @param {...(string|number)} args     Values in source order.
 * @return {string}                     Interpolated string.
 */
export function fmt( template, ...args ) {
	let out = String( template );

	args.forEach( ( value, index ) => {
		out = out.replace(
			new RegExp( '%' + ( index + 1 ) + '\\$[sd]', 'g' ),
			String( value )
		);
	} );

	args.forEach( ( value ) => {
		out = out.replace( /%[sd]/, String( value ) );
	} );

	return out;
}

/**
 * Look up and interpolate in one call.
 *
 * @param {string}             key      Key in the listoraI18n object.
 * @param {string}             fallback English source text, same placeholders.
 * @param {...(string|number)} args     Values in source order.
 * @return {string}                     Localized, interpolated string.
 */
export function tf( key, fallback, ...args ) {
	return fmt( t( key, fallback ), ...args );
}
