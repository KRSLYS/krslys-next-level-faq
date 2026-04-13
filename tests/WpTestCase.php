<?php
/**
 * Base test case with Brain Monkey lifecycle and common WP function stubs.
 *
 * @package Krslys\NextLevelFaqAccordion\Tests
 */

namespace Krslys\NextLevelFaqAccordion\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Extend this class instead of PHPUnit\Framework\TestCase whenever the code
 * under test calls WordPress functions (__, sanitize_key, wp_parse_args, …).
 *
 * setUp()  — boots Brain Monkey and registers the most-common WP stubs.
 * tearDown() — tears down Brain Monkey so expectations are verified.
 *
 * Individual test methods may add further stubs or expectations on top of
 * these defaults using Functions\when() / Functions\expect().
 */
abstract class WpTestCase extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// --- Passthrough stubs (return first argument unchanged) ----------
		Functions\stubs( [
			'__',
			'_x',
			'_n',
			'esc_html__',
			'esc_attr__',
			'esc_html',
			'esc_attr',
			'esc_url',
			'esc_js',
		] );

		// --- Functional stubs (real-ish implementations) ------------------

		// sanitize_key: lowercase, allow [a-z0-9_-] only.
		Functions\when( 'sanitize_key' )->alias(
			function ( $key ) {
				return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) );
			}
		);

		// sanitize_hex_color: accept #RGB and #RRGGBB only.
		Functions\when( 'sanitize_hex_color' )->alias(
			function ( $color ) {
				$color = trim( (string) $color );
				if ( '' === $color ) {
					return '';
				}
				if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color ) ) {
					return $color;
				}
				return null;
			}
		);

		// wp_parse_args: merge $args on top of $defaults ($args wins).
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults = [] ) {
				if ( is_string( $args ) ) {
					parse_str( $args, $args );
				}
				return array_merge( (array) $defaults, (array) $args );
			}
		);

		// sanitize_title: lowercase with hyphens (simplified version).
		Functions\when( 'sanitize_title' )->alias(
			function ( $title ) {
				$title = strtolower( trim( (string) $title ) );
				$title = preg_replace( '/[^a-z0-9\-_]/', '-', $title );
				return trim( $title, '-' );
			}
		);

		// wp_json_encode: thin wrapper around json_encode.
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
