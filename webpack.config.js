/**
 * Webpack build configuration for Next Level FAQ.
 *
 * Extends @wordpress/scripts defaults to:
 * - Accept multiple JS + CSS entry points from their existing locations.
 * - Output minified files (.min.js / .min.css) next to the source files.
 * - Preserve directory structure so nlf_asset_url() works without changes.
 */

const wpScriptsConfig = require( '@wordpress/scripts/config/webpack.config' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const path = require( 'path' );

// @wordpress/scripts may export an array of configs in newer versions.
const defaultConfig = Array.isArray( wpScriptsConfig ) ? wpScriptsConfig[ 0 ] : wpScriptsConfig;

/**
 * Replace the default MiniCssExtractPlugin so we can control the output
 * filename pattern (we want [name].min.css, not the default [name].css).
 */
const filteredPlugins = defaultConfig.plugins.filter(
	( plugin ) => ! ( plugin instanceof MiniCssExtractPlugin )
);

module.exports = {
	...defaultConfig,

	/**
	 * Disable source maps entirely.
	 * Unminified source files are used during development (SCRIPT_DEBUG = true),
	 * so .map files serve no purpose and would expose source code in production.
	 */
	devtool: false,

	entry: {
		'assets/js/frontend-faq':           path.resolve( __dirname, 'assets/js/frontend-faq.js' ),
		'assets/js/admin-faq-style':        path.resolve( __dirname, 'assets/js/admin-faq-style.js' ),
		'assets/js/admin-faq-group-metabox': path.resolve( __dirname, 'assets/js/admin-faq-group-metabox.js' ),
		'assets/js/admin-state-collector':  path.resolve( __dirname, 'assets/js/admin-state-collector.js' ),
		'assets/js/admin-faq-questions':    path.resolve( __dirname, 'assets/js/admin-faq-questions.js' ),
		'blocks/faq/editor':                path.resolve( __dirname, 'blocks/faq/editor.js' ),
		'assets/css/admin-faq-style':       path.resolve( __dirname, 'assets/css/admin-faq-style.css' ),
	},

	output: {
		/**
		 * Output to plugin root so that entry key paths (e.g. 'assets/js/frontend-faq')
		 * resolve to the correct location: assets/js/frontend-faq.min.js
		 */
		path: path.resolve( __dirname ),
		filename: '[name].min.js',
	},

	plugins: [
		...filteredPlugins,
		new MiniCssExtractPlugin( { filename: '[name].min.css' } ),
	],
};
