<?php
/**
 * Tests for the global helper functions in includes/core/functions.php.
 *
 * SCRIPT_DEBUG is defined as false in tests/bootstrap.php, so the minified
 * code path is always exercised in this test run.
 *
 * @package Krslys\NextLevelFaqAccordion\Tests\Unit\Core
 */

namespace Krslys\NextLevelFaqAccordion\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * @covers ::nlf_asset_url
 * @covers ::nlf_asset_path
 */
class FunctionsTest extends TestCase {

	// -----------------------------------------------------------------------
	// nlf_asset_url — .min suffix injection
	// -----------------------------------------------------------------------

	public function test_js_url_gets_min_suffix(): void {
		$url = nlf_asset_url( 'assets/js/frontend-faq.js' );
		$this->assertStringEndsWith( 'assets/js/frontend-faq.min.js', $url );
	}

	public function test_css_url_gets_min_suffix(): void {
		$url = nlf_asset_url( 'assets/css/admin-faq-style.css' );
		$this->assertStringEndsWith( 'assets/css/admin-faq-style.min.css', $url );
	}

	public function test_already_min_js_is_not_double_minified(): void {
		$url = nlf_asset_url( 'assets/js/frontend-faq.min.js' );
		$this->assertStringNotContainsString( '.min.min.', $url );
		$this->assertStringEndsWith( 'assets/js/frontend-faq.min.js', $url );
	}

	public function test_already_min_css_is_not_double_minified(): void {
		$url = nlf_asset_url( 'assets/css/style.min.css' );
		$this->assertStringNotContainsString( '.min.min.', $url );
		$this->assertStringEndsWith( 'assets/css/style.min.css', $url );
	}

	public function test_non_js_css_file_url_unchanged(): void {
		$url = nlf_asset_url( 'assets/images/logo.png' );
		$this->assertStringEndsWith( 'assets/images/logo.png', $url );
	}

	public function test_pot_file_url_unchanged(): void {
		$url = nlf_asset_url( 'languages/next-level-faq.pot' );
		$this->assertStringEndsWith( 'languages/next-level-faq.pot', $url );
	}

	// -----------------------------------------------------------------------
	// nlf_asset_url — prefix
	// -----------------------------------------------------------------------

	public function test_url_has_plugin_url_prefix(): void {
		$url = nlf_asset_url( 'assets/js/frontend-faq.js' );
		$this->assertStringStartsWith( NLF_FAQ_PLUGIN_URL, $url );
	}

	public function test_url_is_full_url(): void {
		$url = nlf_asset_url( 'blocks/faq/editor.js' );
		$this->assertStringStartsWith( 'http', $url );
	}

	// -----------------------------------------------------------------------
	// nlf_asset_path — .min suffix injection
	// -----------------------------------------------------------------------

	public function test_js_path_gets_min_suffix(): void {
		$path = nlf_asset_path( 'assets/js/frontend-faq.js' );
		$this->assertStringEndsWith( 'assets/js/frontend-faq.min.js', $path );
	}

	public function test_css_path_gets_min_suffix(): void {
		$path = nlf_asset_path( 'assets/css/admin-faq-style.css' );
		$this->assertStringEndsWith( 'assets/css/admin-faq-style.min.css', $path );
	}

	public function test_already_min_js_path_is_not_double_minified(): void {
		$path = nlf_asset_path( 'assets/js/admin-faq-questions.min.js' );
		$this->assertStringNotContainsString( '.min.min.', $path );
	}

	public function test_already_min_css_path_is_not_double_minified(): void {
		$path = nlf_asset_path( 'assets/css/admin-faq-style.min.css' );
		$this->assertStringNotContainsString( '.min.min.', $path );
	}

	public function test_non_js_css_file_path_unchanged(): void {
		$path = nlf_asset_path( 'languages/next-level-faq.pot' );
		$this->assertStringEndsWith( 'languages/next-level-faq.pot', $path );
	}

	// -----------------------------------------------------------------------
	// nlf_asset_path — prefix
	// -----------------------------------------------------------------------

	public function test_path_has_plugin_dir_prefix(): void {
		$path = nlf_asset_path( 'assets/js/frontend-faq.js' );
		$this->assertStringStartsWith( NLF_FAQ_PLUGIN_DIR, $path );
	}

	// -----------------------------------------------------------------------
	// URL vs path consistency
	// -----------------------------------------------------------------------

	public function test_url_and_path_agree_on_filename(): void {
		$relative = 'assets/js/admin-faq-group-metabox.js';

		$url  = nlf_asset_url( $relative );
		$path = nlf_asset_path( $relative );

		// Both should resolve to the same filename.
		$this->assertSame( basename( $url ), basename( $path ) );
	}
}
