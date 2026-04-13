<?php
/**
 * Global helper functions for Next Level FAQ.
 *
 * @package Krslys\NextLevelFaqAccordion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a plugin asset URL, using the minified version in production.
 *
 * Resolves to the `.min.js` / `.min.css` variant unless SCRIPT_DEBUG is true,
 * matching WordPress core convention for asset debugging.
 *
 * @param string $path Relative path from plugin root, e.g. 'assets/js/frontend-faq.js'.
 * @return string Full URL to the asset.
 */
function krslys_nlfa_asset_url( string $path ): string {
	if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
		$path = preg_replace( '/(?<!\.min)\.(js|css)$/', '.min.$1', $path );
	}

	return NLF_FAQ_PLUGIN_URL . $path;
}

/**
 * Returns a plugin asset filesystem path, using the minified version in production.
 *
 * Mirrors krslys_nlfa_asset_url() for filesystem operations such as file_exists() and filemtime(),
 * ensuring the URL version and the version hash always point to the same physical file.
 *
 * @param string $path Relative path from plugin root, e.g. 'assets/js/frontend-faq.js'.
 * @return string Absolute filesystem path to the asset.
 */
function krslys_nlfa_asset_path( string $path ): string {
	if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
		$path = preg_replace( '/(?<!\.min)\.(js|css)$/', '.min.$1', $path );
	}

	return NLF_FAQ_PLUGIN_DIR . $path;
}
