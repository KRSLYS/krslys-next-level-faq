/**
 * Webpack build configuration for Next Level FAQ.
 *
 * Extends @wordpress/scripts defaults to:
 * - Accept multiple JS + CSS entry points from their existing locations.
 * - Output minified files (.min.js / .min.css) next to the source files.
 * - Preserve directory structure so nlf_asset_url() works without changes.
 */

const wpScriptsConfig = require( '@wordpress/scripts/config/webpack.config' );
const path            = require( 'path' );

// @wordpress/scripts may export an array of configs in newer versions.
const defaultConfig = Array.isArray( wpScriptsConfig ) ? wpScriptsConfig[ 0 ] : wpScriptsConfig;

// ---------------------------------------------------------------------------
// MiniCssExtractPlugin — reuse the class that @wordpress/scripts already ships.
//
// Requiring mini-css-extract-plugin separately can resolve to a different copy
// of the module (nested node_modules in a hoisted tree), making instanceof
// comparisons fail silently and leaving two conflicting plugin instances in the
// plugins array.  Grabbing the constructor from the existing instance guarantees
// we use the exact same class reference.
// ---------------------------------------------------------------------------
const cssPluginInstance = defaultConfig.plugins.find(
	( p ) => p && p.constructor && p.constructor.name === 'MiniCssExtractPlugin'
);
const MiniCssExtractPlugin = cssPluginInstance
	? cssPluginInstance.constructor
	: require( 'mini-css-extract-plugin' );

// ---------------------------------------------------------------------------
// CSS-only entries (no JS logic) — webpack still emits a stub .min.js for them.
// This inline plugin deletes those stubs from the compilation output so they
// are never written to disk.
// ---------------------------------------------------------------------------
const CSS_ONLY_ENTRIES = [ 'assets/css/admin-faq-style' ];

class DeleteCssStubsPlugin {
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap( 'DeleteCssStubsPlugin', ( compilation ) => {
			compilation.hooks.afterProcessAssets.tap(
				{
					name:  'DeleteCssStubsPlugin',
					stage: compiler.webpack.Compilation.PROCESS_ASSETS_STAGE_OPTIMIZE,
				},
				() => {
					CSS_ONLY_ENTRIES.forEach( ( entry ) => {
						const stub = entry + '.min.js';
						if ( compilation.assets[ stub ] ) {
							compilation.deleteAsset( stub );
						}
					} );
				}
			);
		} );
	}
}

// ---------------------------------------------------------------------------
// Final config
// ---------------------------------------------------------------------------
module.exports = {
	...defaultConfig,

	// Always build in production mode so output is minified regardless of
	// whether NODE_ENV is set in the shell environment (common on Windows).
	mode: 'production',

	// Disable source maps — unminified sources serve that purpose when
	// SCRIPT_DEBUG is true, so maps would only expose source unnecessarily.
	devtool: false,

	entry: {
		'assets/js/frontend-faq':            path.resolve( __dirname, 'assets/js/frontend-faq.js' ),
		'assets/js/admin-faq-group-metabox': path.resolve( __dirname, 'assets/js/admin-faq-group-metabox.js' ),
		'assets/js/admin-state-collector':   path.resolve( __dirname, 'assets/js/admin-state-collector.js' ),
		'assets/js/admin-faq-questions':     path.resolve( __dirname, 'assets/js/admin-faq-questions.js' ),
		'blocks/faq/editor':                 path.resolve( __dirname, 'blocks/faq/editor.js' ),
		'blocks/accordion/editor':           path.resolve( __dirname, 'blocks/accordion/editor.js' ),
		'assets/css/admin-faq-style':        path.resolve( __dirname, 'assets/css/admin-faq-style.css' ),
	},

	output: {
		// Output to plugin root so entry keys like 'assets/js/frontend-faq'
		// resolve to assets/js/frontend-faq.min.js automatically.
		path:     path.resolve( __dirname ),
		filename: '[name].min.js',
	},

	plugins: [
		// Keep all default plugins EXCEPT the original MiniCssExtractPlugin,
		// then add our reconfigured version that writes [name].min.css.
		...defaultConfig.plugins.filter( ( p ) => ! ( p instanceof MiniCssExtractPlugin ) ),
		new MiniCssExtractPlugin( { filename: '[name].min.css' } ),
		new DeleteCssStubsPlugin(),
	],
};
