#!/usr/bin/env node
/**
 * Build JS/block translation JSON keyed to the scripts WordPress actually loads.
 *
 * Why this exists (do not replace with `wp i18n make-json` alone):
 *
 * `wp i18n make-json` splits a .po by the `#:` source references it finds, so it
 * emits one JSON per SOURCE file — `wb-listora-de_DE-<md5('src/blocks/x/index.js')>.json`.
 * At runtime WordPress does the opposite lookup. `_load_script_textdomain_from_src()`
 * (wp-includes/l10n.php) tries `{domain}-{locale}-{handle}.json`, then
 * `{domain}-{locale}-{md5(<relative path of the ENQUEUED script>)}.json`. There is no
 * fallback that reads the `source` field inside the file. Our blocks enqueue
 * `build/blocks/x/index.js`, so the md5 never matches and the editor stays English.
 *
 * A plain src/ -> build/ rename still would not be correct: webpack inlines
 * `src/shared/components/*` and `src/editor/*` into each block bundle, so those
 * strings belong in several build artifacts at once and in no single source file.
 *
 * So we key off the built artifact itself: for every runtime script, keep the
 * msgids that appear literally in that file. That survives minification (string
 * literals are preserved even when the surrounding call is mangled) and puts
 * shared-component strings into every bundle that inlined them. An over-inclusive
 * match only means a slightly larger JSON, never a missing translation.
 *
 * Usage: node bin/build-i18n-json.mjs [--domain=<text-domain>]
 */

import { readFileSync, writeFileSync, readdirSync, statSync, existsSync, unlinkSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import gettextParser from 'gettext-parser';

const ROOT = join(fileURLToPath(new URL('.', import.meta.url)), '..');
const args = Object.fromEntries(
	process.argv.slice(2).map( ( t ) => {
		const [ k, v ] = t.replace( /^--/, '' ).split( '=' );
		return [ k, v === undefined ? true : v ];
	} )
);
const DOMAIN = args.domain || JSON.parse( readFileSync( join( ROOT, '.wbcom-i18n.json' ), 'utf8' ) ).domain;
const LANGS = join( ROOT, 'languages' );

/** Directories holding scripts WordPress can enqueue, relative to the plugin root. */
const RUNTIME_DIRS = [ 'build', 'assets/js' ];

function walk( dir, out = [] ) {
	if ( ! existsSync( dir ) ) return out;
	for ( const name of readdirSync( dir ) ) {
		const full = join( dir, name );
		if ( statSync( full ).isDirectory() ) walk( full, out );
		else if ( name.endsWith( '.js' ) && ! name.endsWith( '.min.js' ) ) out.push( full );
	}
	return out;
}

const scripts = RUNTIME_DIRS.flatMap( ( d ) => walk( join( ROOT, d ) ) );
if ( ! scripts.length ) {
	console.error( 'No runtime scripts found — run `npm run build` first.' );
	process.exit( 1 );
}

// Drop the per-source JSONs make-json emitted; they are keyed to paths WP never asks for.
for ( const f of readdirSync( LANGS ) ) {
	if ( f.startsWith( `${ DOMAIN }-` ) && f.endsWith( '.json' ) ) unlinkSync( join( LANGS, f ) );
}

const poFiles = readdirSync( LANGS ).filter(
	( f ) => f.startsWith( `${ DOMAIN }-` ) && f.endsWith( '.po' )
);

let totalWritten = 0;

for ( const poFile of poFiles ) {
	const locale = poFile.slice( DOMAIN.length + 1, -3 );
	const parsed = gettextParser.po.parse( readFileSync( join( LANGS, poFile ) ) );

	// msgid -> msgstr[], skipping the header, untranslated entries and fuzzy ones
	// (fuzzy means the placeholder check failed; English is the safer render).
	const entries = [];
	for ( const ctx of Object.keys( parsed.translations ) ) {
		for ( const key of Object.keys( parsed.translations[ ctx ] ) ) {
			const e = parsed.translations[ ctx ][ key ];
			if ( ! e.msgid ) continue;
			const flags = ( e.comments?.flag || '' ).split( ',' ).map( ( s ) => s.trim() );
			if ( flags.includes( 'fuzzy' ) ) continue;
			if ( ! e.msgstr.some( ( s ) => s ) ) continue;
			entries.push( e );
		}
	}

	for ( const script of scripts ) {
		const source = relative( ROOT, script ).split( '\\' ).join( '/' );
		const code = readFileSync( script, 'utf8' );

		// Only scripts that actually call into wp.i18n can consume a JSON. Without
		// this guard the literal-substring match below fires on any file that
		// happens to contain a short msgid ("Save", "Type"), and we would ship a
		// JSON for e.g. assets/js/admin/settings-page.js, which takes its strings
		// from PHP via wp_localize_script and never reads a translation file.
		if ( ! /wp\.i18n|@wordpress\/i18n/.test( code ) ) continue;

		const messages = {};
		for ( const e of entries ) {
			if ( ! code.includes( e.msgid ) ) continue;
			// Jed keys use "contextmsgid" when a context is present.
			const key = e.msgctxt ? `${ e.msgctxt }${ e.msgid }` : e.msgid;
			messages[ key ] = e.msgstr;
		}
		if ( ! Object.keys( messages ).length ) continue;

		const json = {
			'translation-revision-date': parsed.headers[ 'PO-Revision-Date' ] || '',
			generator: 'wb-listora/bin/build-i18n-json.mjs',
			source,
			domain: 'messages',
			locale_data: {
				messages: {
					'': {
						domain: 'messages',
						lang: locale,
						'plural-forms': parsed.headers[ 'plural-forms' ] || parsed.headers[ 'Plural-Forms' ] || 'nplurals=2; plural=(n != 1);',
					},
					...messages,
				},
			},
		};

		const md5 = createHash( 'md5' ).update( source ).digest( 'hex' );
		writeFileSync( join( LANGS, `${ DOMAIN }-${ locale }-${ md5 }.json` ), JSON.stringify( json ) );
		totalWritten++;
	}
}

console.log( `${ totalWritten } JSON files written for ${ poFiles.length } locales across ${ scripts.length } runtime scripts.` );
