#!/usr/bin/env node
/**
 * Dead-end crawler: walk the site as a guest from the home page and fail on
 * anything a visitor can click into that goes nowhere.
 *
 * Listora renders every block on the server, so plain HTTP sees what a visitor
 * sees. No browser and no dependencies (Node 18+ fetch). Viewport, console and
 * JS-state checks stay with the browser walk; this gate catches the class of
 * bug that shipped past every other check: a link to the bare listing archive
 * (theme blog loop), a visible empty state on demo data, a "0 listings" tile,
 * a 4xx/5xx, or PHP output in the page.
 *
 * Usage:
 *   node bin/dead-end-crawl.mjs --site http://listora.local [--max 400] [--out report.json]
 *
 * Exit 0 = no failures, 1 = failures, 2 = site unreachable / bad arguments.
 */

import { writeFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

// Never crawl into these: admin, auth, API, feeds, uploads, carts, comment replies.
const SKIP = [
	/\/wp-admin/, /\/wp-login\.php/, /\/wp-json/, /\/xmlrpc\.php/, /\/feed\/?$/, /\/wp-content\//, /\/wp-includes\//,
	/[?&](add-to-cart|replytocom|action=logout|_wpnonce|autologin)=/, /\/(cart|checkout)\/?$/,
	/\.(jpe?g|png|gif|webp|svg|pdf|zip|css|js|xml|ics)(\?|$)/i,
];

// Empty states a visitor may legitimately land on (a listing with no reviews yet).
const EMPTY_OK = [ 'listora-reviews__empty' ];

/**
 * Checks on one page's HTML. Returns { fails: string[], warns: string[] }.
 *
 * @param {string} url  Page URL.
 * @param {string} html Response body.
 */
export function inspect( url, html ) {
	const fails = [];
	const warns = [];

	const php = html.match( /<b>(Fatal error|Parse error|Warning|Notice|Deprecated)<\/b>:[^<]{0,160}/ );
	if ( php ) {
		fails.push( `PHP output in page: ${ php[ 0 ].replace( /<\/?b>/g, '' ) }` );
	}

	const body = ( html.match( /<body[^>]*class="([^"]*)"/ ) || [] )[ 1 ] || '';
	if ( /\bpost-type-archive-listora_listing\b/.test( body ) && ! /listora-block|wp-block-listora/.test( html ) ) {
		fails.push( 'Bare listing archive rendered by the theme blog loop (link should go to the Directory page)' );
	}

	// Visible empty states: the outermost empty wrapper, not hidden server-side.
	for ( const m of html.matchAll( /<[a-z]+\b[^>]*class="([^"]*(?:listora-card--empty|listora-featured__empty|listora-map__unconfigured)[^"]*)"[^>]*>/g ) ) {
		const cls = m[ 1 ];
		if ( /\bis-hidden\b/.test( cls ) || /\shidden(\s|>|=)/.test( m[ 0 ] ) || EMPTY_OK.some( ( ok ) => cls.includes( ok ) ) ) {
			continue;
		}
		const at = html.indexOf( m[ 0 ] );
		const title = ( html.slice( at, at + 1500 ).match( /class="listora-empty(?:-state)?__title"[^>]*>([^<]+)/ ) || [] )[ 1 ];
		fails.push( `Visible empty state (${ cls.split( /\s+/ )[ 0 ] })${ title ? `: "${ title.trim() }"` : '' }` );
	}

	const zero = html.match( /listora-categories__count"[^>]*>\s*0 listings?/ );
	if ( zero ) {
		fails.push( 'Category tile with 0 listings' );
	}

	for ( const m of html.matchAll( /<img\b[^>]*>/g ) ) {
		if ( /\ssrc=""/.test( m[ 0 ] ) && ! /data-wp-bind--src/.test( m[ 0 ] ) ) {
			warns.push( 'Image with empty src' );
			break;
		}
	}

	return { fails, warns };
}

async function main() {
const args = Object.fromEntries(
	process.argv.slice( 2 ).reduce( ( pairs, arg, i, all ) => {
		if ( arg.startsWith( '--' ) ) {
			pairs.push( [ arg.slice( 2 ), all[ i + 1 ] && ! all[ i + 1 ].startsWith( '--' ) ? all[ i + 1 ] : true ] );
		}
		return pairs;
	}, [] )
);

const SITE = String( args.site || process.env.LOCAL_CI_SITE_URL || '' ).replace( /\/+$/, '' );
const MAX_PAGES = Number( args.max || 400 );
const CONCURRENCY = 6;
const MAX_QUERY_VARIANTS = 3; // per path: enough to hit sorts/filters, not every combination.

if ( ! SITE ) {
	console.error( 'Usage: node bin/dead-end-crawl.mjs --site <url> [--max N] [--out file.json]' );
	process.exit( 2 );
}

const origin = new URL( SITE ).origin;

/**
 * Same-origin links worth following, normalised (no hash).
 *
 * @param {string} html Page HTML.
 * @param {string} base URL the page was served from.
 */
function links( html, base ) {
	const out = new Set();
	for ( const m of html.matchAll( /<a\b[^>]*\shref="([^"#][^"]*)"/g ) ) {
		let u;
		try {
			u = new URL( m[ 1 ].replace( /&amp;/g, '&' ), base );
		} catch {
			continue;
		}
		if ( u.origin !== origin || SKIP.some( ( re ) => re.test( u.pathname + u.search ) ) ) {
			continue;
		}
		u.hash = '';
		out.add( u.href );
	}
	return out;
}

const seen = new Map(); // url -> referrer
const variants = new Map(); // path -> query variants taken
const queue = [ [ `${ SITE }/`, '(start)' ] ];
seen.set( `${ SITE }/`, '(start)' );
const results = [];

function enqueue( url, from ) {
	if ( seen.has( url ) || seen.size >= MAX_PAGES ) {
		return;
	}
	const u = new URL( url );
	if ( u.search ) {
		const n = variants.get( u.pathname ) || 0;
		if ( n >= MAX_QUERY_VARIANTS ) {
			return;
		}
		variants.set( u.pathname, n + 1 );
	}
	seen.set( url, from );
	queue.push( [ url, from ] );
}

async function visit( [ url, from ] ) {
	let res;
	try {
		res = await fetch( url, { redirect: 'follow', headers: { 'User-Agent': 'listora-dead-end-crawl' } } );
	} catch ( e ) {
		results.push( { url, from, status: 0, fails: [ `Request failed: ${ e.message }` ], warns: [] } );
		return;
	}
	const type = res.headers.get( 'content-type' ) || '';
	const html = type.includes( 'text/html' ) ? await res.text() : '';
	const found = html ? inspect( res.url, html ) : { fails: [], warns: [] };
	if ( res.status >= 400 ) {
		found.fails.unshift( `HTTP ${ res.status }` );
	}
	results.push( { url, from, status: res.status, ...found } );
	if ( html && res.status < 400 ) {
		for ( const next of links( html, res.url ) ) {
			enqueue( next, url );
		}
	}
}

try {
	const probe = await fetch( `${ SITE }/`, { redirect: 'follow' } );
	if ( probe.status >= 500 ) {
		throw new Error( `HTTP ${ probe.status }` );
	}
} catch ( e ) {
	console.error( `Site ${ SITE } is not reachable: ${ e.message }` );
	process.exit( 2 );
}

while ( queue.length ) {
	await Promise.all( queue.splice( 0, CONCURRENCY ).map( visit ) );
}

const failed = results.filter( ( r ) => r.fails.length );
const warned = results.filter( ( r ) => r.warns.length );

for ( const r of failed ) {
	console.log( `FAIL ${ r.url }\n     from ${ r.from }\n     - ${ r.fails.join( '\n     - ' ) }` );
}
for ( const r of warned ) {
	console.log( `warn ${ r.url } - ${ r.warns.join( '; ' ) }` );
}
console.log( `\nCrawled ${ results.length } pages${ seen.size >= MAX_PAGES ? ` (capped at ${ MAX_PAGES })` : '' }: ${ failed.length } failing, ${ warned.length } with warnings.` );

if ( args.out ) {
	writeFileSync( String( args.out ), JSON.stringify( { site: SITE, crawled: results.length, failed, warned }, null, 2 ) );
}

process.exit( failed.length ? 1 : 0 );
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	await main();
}
