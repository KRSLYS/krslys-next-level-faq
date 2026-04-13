<?php
/**
 * Tests for the Admin_Settings class.
 *
 * @package Krslys\NextLevelFaqAccordion\Tests\Unit\Admin
 */

namespace Krslys\NextLevelFaqAccordion\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Krslys\NextLevelFaqAccordion\Admin_Settings;
use Krslys\NextLevelFaqAccordion\Tests\MockWpdb;
use Krslys\NextLevelFaqAccordion\Tests\WpTestCase;

/**
 * @covers \Krslys\NextLevelFaqAccordion\Admin_Settings
 */
class AdminSettingsTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb = new MockWpdb( false );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Constants
	// -----------------------------------------------------------------------

	public function test_top_menu_slug_constant(): void {
		$this->assertSame( 'nlf-faq', Admin_Settings::TOP_MENU_SLUG );
	}

	public function test_questions_slug_constant(): void {
		$this->assertSame( 'nlf-faq-questions', Admin_Settings::QUESTIONS_SLUG );
	}

	public function test_tools_slug_constant(): void {
		$this->assertSame( 'nlf-faq-tools', Admin_Settings::TOOLS_SLUG );
	}

	// -----------------------------------------------------------------------
	// init() — guard: non-admin context
	// -----------------------------------------------------------------------

	public function test_init_does_not_register_hooks_outside_admin(): void {
		Functions\when( 'is_admin' )->justReturn( false );

		$add_action_count = 0;
		Functions\when( 'add_action' )->alias( function () use ( &$add_action_count ) {
			$add_action_count++;
		} );

		Admin_Settings::init();

		$this->assertSame( 0, $add_action_count, 'No hooks should be registered outside admin context' );
	}

	// -----------------------------------------------------------------------
	// init() — admin context hook registration
	// -----------------------------------------------------------------------

	public function test_init_registers_exactly_four_hooks(): void {
		Functions\when( 'is_admin' )->justReturn( true );

		$add_action_count = 0;
		Functions\when( 'add_action' )->alias( function () use ( &$add_action_count ) {
			$add_action_count++;
		} );

		Admin_Settings::init();

		$this->assertSame( 4, $add_action_count, 'init() should register exactly 4 action hooks' );
	}

	public function test_init_registers_all_expected_hooks(): void {
		Functions\when( 'is_admin' )->justReturn( true );

		$registered = [];
		Functions\when( 'add_action' )->alias( function ( $hook ) use ( &$registered ) {
			$registered[] = $hook;
		} );

		Admin_Settings::init();

		$this->assertContains( 'admin_menu', $registered );
		$this->assertContains( 'admin_enqueue_scripts', $registered );
		$this->assertContains( 'admin_post_nlf_faq_export', $registered );
		$this->assertContains( 'admin_post_nlf_faq_import', $registered );
		$this->assertNotContains( 'admin_init', $registered );
		$this->assertNotContains( 'wp_ajax_nlf_save_settings_ajax', $registered );
	}
}
