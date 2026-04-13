<?php
/**
 * Style generator: options in → correct CSS out.
 */

namespace Krslys\NextLevelFaqAccordion\Tests\Unit;

use Krslys\NextLevelFaqAccordion\Presets;
use Krslys\NextLevelFaqAccordion\Style_Generator;
use Krslys\NextLevelFaqAccordion\Tests\WpTestCase;

class StyleGeneratorTest extends WpTestCase {

	private function minimal_options(): array {
		return Presets::get_preset_values( 'minimal' );
	}

	// -- CSS contains root variables --

	public function test_css_contains_root(): void {
		$css = Style_Generator::build_css( $this->minimal_options() );

		$this->assertStringContainsString( ':root', $css );
	}

	// -- CSS contains main selector --

	public function test_css_contains_faq_selector(): void {
		$css = Style_Generator::build_css( $this->minimal_options() );

		$this->assertStringContainsString( '.nlf-faq', $css );
	}

	// -- Custom color appears in output --

	public function test_accent_color_in_css(): void {
		$options = $this->minimal_options();
		$options['accent_color'] = '#ff0000';
		$css = Style_Generator::build_css( $options );

		$this->assertStringContainsString( '#ff0000', $css );
	}

	// -- All presets produce valid CSS --

	public function test_all_presets_produce_css(): void {
		foreach ( Presets::get_registry() as $slug => $preset ) {
			$css = Style_Generator::build_css( $preset['values'] );
			$this->assertStringContainsString( '.nlf-faq', $css, "Preset '{$slug}' should produce valid CSS" );
		}
	}
}
