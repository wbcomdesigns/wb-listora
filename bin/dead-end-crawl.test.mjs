// node --test bin/dead-end-crawl.test.mjs - the page checks catch each dead end they claim to.
import test from 'node:test';
import assert from 'node:assert/strict';
import { inspect } from './dead-end-crawl.mjs';

const page = ( body, bodyClass = '' ) => `<html><body class="${ bodyClass }">${ body }</body></html>`;

test( 'clean page passes', () => {
	assert.deepEqual( inspect( 'u', page( '<div class="listora-block">ok</div>' ) ), { fails: [], warns: [] } );
} );

test( 'listing archives in the blog loop fail', () => {
	assert.equal( inspect( 'u', page( '<article>post</article>', 'archive post-type-archive-listora_listing' ) ).fails.length, 1 );
	assert.equal( inspect( 'u', page( '<article>post</article><div class="listora-block listora-qv">popup</div>', 'archive tax-listora_listing_cat term-casual' ) ).fails.length, 1, 'Shared popup markup does not count as the directory.' );
	assert.equal( inspect( 'u', page( '<div class="wp-block-listora-listing-grid">grid</div>', 'tax-listora_listing_cat listora-term-archive' ) ).fails.length, 0 );
} );

test( 'visible empty state fails, hidden or allowed ones pass', () => {
	const shown = '<div class="listora-grid__empty listora-card listora-card--empty"><h3 class="listora-empty__title">No Event listings yet</h3></div>';
	assert.match( inspect( 'u', page( shown ) ).fails[ 0 ], /No Event listings yet/ );
	assert.equal( inspect( 'u', page( shown.replace( '--empty"', '--empty is-hidden"' ) ) ).fails.length, 0 );
	assert.equal( inspect( 'u', page( '<div class="listora-reviews__empty listora-card listora-card--empty">x</div>' ) ).fails.length, 0 );
} );

test( 'zero-listing category tile and PHP output fail', () => {
	assert.equal( inspect( 'u', page( '<span class="listora-categories__count">0 listings</span>' ) ).fails.length, 1 );
	assert.equal( inspect( 'u', page( '<b>Warning</b>: Undefined array key' ) ).fails.length, 1 );
} );

test( 'empty img src warns unless the Interactivity API binds it', () => {
	assert.equal( inspect( 'u', page( '<img src="" alt="">' ) ).warns.length, 1 );
	assert.equal( inspect( 'u', page( '<img src="" data-wp-bind--src="state.x">' ) ).warns.length, 0 );
} );
