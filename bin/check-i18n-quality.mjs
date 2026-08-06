#!/usr/bin/env node
/**
 * Catch corrupted translation catalogues before they ship.
 *
 * Why this exists: the AI translate step sends a batch as an indexed object
 * and reads the reply back positionally. If the model drops, adds or renumbers
 * a key, every entry after it shifts — each msgid silently receives the
 * translation belonging to its neighbour. That is how "Lifetime" ended up
 * carrying the German for its own description in the first 1.4.2 run.
 *
 * The translate step's own placeholder check only catches the subset where the
 * shift happens to change the printf placeholders. A label that inherits the
 * following description translates cleanly past it, so nothing flags it and the
 * catalogue looks finished.
 *
 * Signals, in descending confidence:
 *
 *   placeholder  msgstr's printf placeholders differ from msgid's. A hard
 *                failure: it is also a crash risk, since a stray %s in a
 *                string that is later sprintf'd against no argument is a
 *                fatal, not a typo.
 *   tag          HTML tag count differs. Hard failure — broken markup ships
 *                to the page.
 *   length       msgstr wildly longer or shorter than msgid. A warning only:
 *                CJK is legitimately terser than English and German is
 *                legitimately longer, so this reports rather than blocks.
 *                A cluster of these in one locale is the fingerprint of a
 *                shifted batch and is worth a human look.
 *
 * Usage:  node bin/check-i18n-quality.mjs [--dir=languages] [--strict]
 *         --strict also fails on the length warnings.
 * Exit:   0 clean, 1 hard failures found.
 */

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join( fileURLToPath( new URL( '.', import.meta.url ) ), '..' );
const args = Object.fromEntries(
	process.argv.slice( 2 ).map( ( t ) => {
		const [ k, v ] = t.replace( /^--/, '' ).split( '=' );
		return [ k, v === undefined ? true : v ];
	} )
);

// Directories to scan: the plugin's own catalogue plus any bundled library
// that ships its own (the Credits SDK owns the wbcom-credits-sdk domain).
const DIRS = args.dir
	? [ join( ROOT, String( args.dir ) ) ]
	: [ join( ROOT, 'languages' ), join( ROOT, 'libs/wbcom-credits-sdk/languages' ) ].filter( existsSync );

const PLACEHOLDER = /%%|%(?:\d+\$)?[bcdeEfFgGosuxX]/g;
const HTML_TAG = /<[a-zA-Z/][^>]*>/g;

/** Split a .po into {msgid, msgstr} pairs, skipping the header and empties. */
function parsePo( text ) {
	const out = [];
	for ( const block of text.split( /\n\n+/ ) ) {
		const id = block.match( /^msgid "(.*)"/m );
		const str = block.match( /^msgstr "(.*)"/m );
		if ( id && str && id[ 1 ] && str[ 1 ] ) {
			out.push( { msgid: id[ 1 ], msgstr: str[ 1 ] } );
		}
	}
	return out;
}

const count = ( s, re ) => ( s.match( re ) || [] ).length;
const sortedPlaceholders = ( s ) => ( s.match( PLACEHOLDER ) || [] ).slice().sort().join( ',' );

let hardFailures = 0;
let warnings = 0;

for ( const dir of DIRS ) {
	for ( const file of readdirSync( dir ).filter( ( f ) => f.endsWith( '.po' ) ) ) {
		const entries = parsePo( readFileSync( join( dir, file ), 'utf8' ) );
		const bad = { placeholder: [], tag: [], length: [] };

		for ( const { msgid, msgstr } of entries ) {
			if ( sortedPlaceholders( msgid ) !== sortedPlaceholders( msgstr ) ) {
				bad.placeholder.push( msgid );
			} else if ( count( msgid, HTML_TAG ) !== count( msgstr, HTML_TAG ) ) {
				bad.tag.push( msgid );
			} else if ( msgid.length >= 8 && msgstr.length >= 8 ) {
				const ratio = msgstr.length / msgid.length;
				if ( ratio > 2.8 || ratio < 0.36 ) bad.length.push( msgid );
			}
		}

		const hard = bad.placeholder.length + bad.tag.length;
		hardFailures += hard;
		warnings += bad.length.length;

		if ( hard || bad.length.length ) {
			console.log( `\n${ file } — ${ entries.length } translated` );
			if ( bad.placeholder.length ) {
				console.log( `  FAIL  ${ bad.placeholder.length } placeholder mismatch` );
				bad.placeholder.slice( 0, 3 ).forEach( ( m ) => console.log( `          "${ m.slice( 0, 70 ) }"` ) );
			}
			if ( bad.tag.length ) {
				console.log( `  FAIL  ${ bad.tag.length } HTML tag mismatch` );
				bad.tag.slice( 0, 3 ).forEach( ( m ) => console.log( `          "${ m.slice( 0, 70 ) }"` ) );
			}
			if ( bad.length.length ) {
				const pct = ( ( 100 * bad.length.length ) / entries.length ).toFixed( 1 );
				console.log( `  warn  ${ bad.length.length } length outliers (${ pct }%) — a cluster here means a shifted batch` );
				bad.length.slice( 0, 3 ).forEach( ( m ) => console.log( `          "${ m.slice( 0, 70 ) }"` ) );
			}
		}
	}
}

if ( hardFailures ) {
	console.log( `\n${ hardFailures } hard failure(s). Re-translate the affected locale from a clean .po — patching only the flagged entries leaves the silent shifts behind.` );
	process.exit( 1 );
}

if ( warnings && args.strict ) {
	console.log( `\n${ warnings } warning(s) and --strict is set.` );
	process.exit( 1 );
}

console.log( `\ni18n catalogues clean — 0 placeholder or tag failures${ warnings ? `, ${ warnings } length warning(s) to eyeball` : '' }.` );
