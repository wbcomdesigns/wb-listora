const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const path = require( 'path' );
const glob = require( 'glob' );
const fs = require( 'fs' );

// ── Classic script entries (editor + admin) ──────────────────────────
const classicEntries = {};

// Block editor scripts.
glob.sync( './src/blocks/*/index.js' ).forEach( ( file ) => {
	const block = file.match( /src\/blocks\/(.+?)\/index\.js/ )[ 1 ];
	classicEntries[ `blocks/${ block }/index` ] = path.resolve( file );
} );

// Admin scripts.
glob.sync( './src/admin/*.js' ).forEach( ( file ) => {
	const name = path.basename( file, '.js' );
	classicEntries[ `admin/${ name }` ] = path.resolve( file );
} );

// Editor utilities.
glob.sync( './src/editor/*.js' ).forEach( ( file ) => {
	const name = path.basename( file, '.js' );
	classicEntries[ `editor/${ name }` ] = path.resolve( file );
} );

// ── ES module entries (view scripts + interactivity store) ───────────
const moduleEntries = {};

// Block frontend view scripts — loaded as script modules for Interactivity API.
glob.sync( './src/blocks/*/view.js' ).forEach( ( file ) => {
	const block = file.match( /src\/blocks\/(.+?)\/view\.js/ )[ 1 ];
	moduleEntries[ `blocks/${ block }/view` ] = path.resolve( file );
} );

// Shared interactivity store.
if ( fs.existsSync( './src/interactivity/store.js' ) ) {
	moduleEntries[ 'interactivity/store' ] = path.resolve(
		'./src/interactivity/store.js'
	);
}

// Strip the shared DependencyExtractionWebpackPlugin — each config gets its own.
const sharedPlugins = ( defaultConfig.plugins || [] ).filter(
	( p ) => p.constructor.name !== 'DependencyExtractionWebpackPlugin'
);

// ── Build configs ────────────────────────────────────────────────────
module.exports = [
	// 1) Classic scripts (editor, admin) — IIFE output.
	{
		...defaultConfig,
		entry: classicEntries,
		output: {
			...defaultConfig.output,
			clean: false,
		},
		plugins: [ ...sharedPlugins, new DependencyExtractionWebpackPlugin() ],
	},
	// 2) ES modules (view scripts, interactivity store) — module output.
	{
		...defaultConfig,
		entry: moduleEntries,
		experiments: {
			...( defaultConfig.experiments || {} ),
			outputModule: true,
		},
		output: {
			...defaultConfig.output,
			module: true,
			chunkFormat: 'module',
			library: { type: 'module' },
			clean: false,
		},
		externalsType: 'module',
		plugins: [ ...sharedPlugins, new DependencyExtractionWebpackPlugin() ],
	},
];
