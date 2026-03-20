const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const glob = require( 'glob' );

// Auto-discover entry points from src/blocks/*/index.js, src/blocks/*/view.js, etc.
const entries = {};

// Block editor scripts.
glob.sync( './src/blocks/*/index.js' ).forEach( ( file ) => {
	const block = file.match( /src\/blocks\/(.+?)\/index\.js/ )[ 1 ];
	entries[ `blocks/${ block }/index` ] = path.resolve( file );
} );

// Block view scripts (frontend).
glob.sync( './src/blocks/*/view.js' ).forEach( ( file ) => {
	const block = file.match( /src\/blocks\/(.+?)\/view\.js/ )[ 1 ];
	entries[ `blocks/${ block }/view` ] = path.resolve( file );
} );

// Shared interactivity store.
if ( require( 'fs' ).existsSync( './src/interactivity/store.js' ) ) {
	entries[ 'interactivity/store' ] = path.resolve( './src/interactivity/store.js' );
}

// Admin scripts.
glob.sync( './src/admin/*.js' ).forEach( ( file ) => {
	const name = path.basename( file, '.js' );
	entries[ `admin/${ name }` ] = path.resolve( file );
} );

// Editor utilities.
glob.sync( './src/editor/*.js' ).forEach( ( file ) => {
	const name = path.basename( file, '.js' );
	entries[ `editor/${ name }` ] = path.resolve( file );
} );

module.exports = {
	...defaultConfig,
	entry: entries,
};
