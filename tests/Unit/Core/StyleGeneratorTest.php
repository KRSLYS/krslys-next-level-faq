<?php
/**
 * Tests for Style_Generator::build_css().
 *
 * build_css() is the only public method that does not touch the filesystem or
 * the WordPress options/upload APIs, making it ideal for unit testing. It
 * accepts a resolved options array and returns a CSS string.
 *
 * @package Krslys\NextLevelFaq\Tests\Unit\Core
 */

namespace Krslys\NextLevelFaq\Tests\Unit\Core;

use Krslys\NextLevelFaq\Presets;
use Krslys\NextLevelFaq\Style_Generator;
use Krslys\NextLevelFaq\Tests\WpTestCase;

/**
 * @covers \Krslys\NextLevelFaq\Style_Generator::build_css
 */
class StyleGeneratorTest extends WpTestCase {

	/**
	 * Fully resolved options array based on the minimal preset.
	 * Passed directly to build_css() in every test.
	 */
	private array $minimal_options;

	protected function setUp(): void {
		parent::setUp();

		// Merge minimal preset values with the preset key so normalize_options()
		// can resolve them without calling Settings_Repository.
		$this->minimal_options = array_merge(
			Presets::get_preset_values( 'minimal' ),
			[ 'preset' => 'minimal' ]
		);
	}

	// -----------------------------------------------------------------------
	// Return type and basic structure
	// -----------------------------------------------------------------------

	public function test_build_css_returns_a_string(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertIsString( $css );
	}

	public function test_build_css_returns_non_empty_string(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertNotEmpty( $css );
	}

	public function test_build_css_output_contains_css_root_block(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertStringContainsString( ':root', $css );
	}

	public function test_build_css_output_contains_nlf_faq_selector(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertStringContainsString( '.nlf-faq', $css );
	}

	// -----------------------------------------------------------------------
	// CSS custom properties reflect option values
	// -----------------------------------------------------------------------

	public function test_container_background_color_appears_in_css(): void {
		$options                         = $this->minimal_options;
		$options['container_background'] = '#aabbcc';
		$css                             = Style_Generator::build_css( $options );

		$this->assertStringContainsString( '#aabbcc', $css );
	}

	public function test_question_color_appears_in_css(): void {
		$options                  = $this->minimal_options;
		$options['question_color'] = '#123456';
		$css                      = Style_Generator::build_css( $options );

		$this->assertStringContainsString( '#123456', $css );
	}

	public function test_answer_color_appears_in_css(): void {
		$options                = $this->minimal_options;
		$options['answer_color'] = '#654321';
		$css                    = Style_Generator::build_css( $options );

		$this->assertStringContainsString( '#654321', $css );
	}

	public function test_accent_color_appears_in_css(): void {
		$options                 = $this->minimal_options;
		$options['accent_color'] = '#ff6600';
		$css                     = Style_Generator::build_css( $options );

		$this->assertStringContainsString( '#ff6600', $css );
	}

	public function test_font_weight_appears_in_css(): void {
		$options                         = $this->minimal_options;
		$options['question_font_weight'] = 700;
		$css                             = Style_Generator::build_css( $options );

		$this->assertStringContainsString( '700', $css );
	}

	// -----------------------------------------------------------------------
	// CSS variable names are emitted
	// -----------------------------------------------------------------------

	public function test_css_variable_container_bg_is_declared(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertStringContainsString( '--nlf-faq-container-bg', $css );
	}

	public function test_css_variable_question_color_is_declared(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertStringContainsString( '--nlf-faq-question-color', $css );
	}

	public function test_css_variable_answer_color_is_declared(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertStringContainsString( '--nlf-faq-answer-color', $css );
	}

	public function test_css_variable_accent_color_is_declared(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertStringContainsString( '--nlf-faq-accent-color', $css );
	}

	public function test_css_variable_border_radius_is_declared(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertStringContainsString( '--nlf-faq-border-radius', $css );
	}

	public function test_css_variable_shadow_is_declared(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertStringContainsString( '--nlf-faq-shadow', $css );
	}

	// -----------------------------------------------------------------------
	// Shadow CSS values
	// -----------------------------------------------------------------------

	public function test_shadow_none_produces_none_value(): void {
		$options           = $this->minimal_options;
		$options['shadow'] = 'none';
		$css               = Style_Generator::build_css( $options );

		// The shadow CSS variable should be set to 'none'.
		$this->assertMatchesRegularExpression( '/--nlf-faq-shadow\s*:\s*none/', $css );
	}

	public function test_shadow_false_produces_none_value(): void {
		$options           = $this->minimal_options;
		$options['shadow'] = false;
		$css               = Style_Generator::build_css( $options );

		$this->assertMatchesRegularExpression( '/--nlf-faq-shadow\s*:\s*none/', $css );
	}

	public function test_shadow_sm_produces_box_shadow_value(): void {
		$options           = $this->minimal_options;
		$options['shadow'] = 'sm';
		$css               = Style_Generator::build_css( $options );

		// Shadow 'sm' maps to a specific rgba value.
		$this->assertStringContainsString( 'rgba(0,0,0,0.06)', $css );
	}

	public function test_shadow_md_produces_box_shadow_value(): void {
		$options           = $this->minimal_options;
		$options['shadow'] = 'md';
		$css               = Style_Generator::build_css( $options );

		$this->assertStringContainsString( 'rgba(0,0,0,0.1)', $css );
	}

	// -----------------------------------------------------------------------
	// Animation transition output
	// -----------------------------------------------------------------------

	public function test_animation_none_produces_none_transition(): void {
		$options              = $this->minimal_options;
		$options['animation'] = 'none';
		$css                  = Style_Generator::build_css( $options );

		// --nlf-faq-answer-transition should be 'none' when animation=none.
		$this->assertMatchesRegularExpression( '/--nlf-faq-answer-transition\s*:\s*none/', $css );
	}

	public function test_animation_slide_produces_max_height_transition(): void {
		$options              = $this->minimal_options;
		$options['animation'] = 'slide';
		$css                  = Style_Generator::build_css( $options );

		$this->assertStringContainsString( 'max-height', $css );
	}

	public function test_animation_fade_produces_opacity_transition(): void {
		$options              = $this->minimal_options;
		$options['animation'] = 'fade';
		$css                  = Style_Generator::build_css( $options );

		$this->assertStringContainsString( 'opacity', $css );
	}

	// -----------------------------------------------------------------------
	// Numeric conversions (px → rem) reach the output
	// -----------------------------------------------------------------------

	public function test_border_radius_converts_to_rem(): void {
		$options                            = $this->minimal_options;
		$options['container_border_radius'] = 16; // 16px → 1.000rem
		$css                                = Style_Generator::build_css( $options );

		$this->assertStringContainsString( '1rem', $css );
	}

	public function test_zero_border_radius_produces_zero_rem(): void {
		$options                            = $this->minimal_options;
		$options['container_border_radius'] = 0;
		$css                                = Style_Generator::build_css( $options );

		$this->assertMatchesRegularExpression( '/--nlf-faq-border-radius\s*:\s*0rem/', $css );
	}

	// -----------------------------------------------------------------------
	// Output is trimmed (no leading/trailing whitespace)
	// -----------------------------------------------------------------------

	public function test_build_css_output_is_trimmed(): void {
		$css = Style_Generator::build_css( $this->minimal_options );
		$this->assertSame( $css, trim( $css ) );
	}

	// -----------------------------------------------------------------------
	// All presets produce valid CSS
	// -----------------------------------------------------------------------

	public function test_all_presets_produce_non_empty_css(): void {
		foreach ( array_keys( Presets::get_registry() ) as $slug ) {
			$options = array_merge(
				Presets::get_preset_values( $slug ),
				[ 'preset' => $slug ]
			);
			$css = Style_Generator::build_css( $options );

			$this->assertNotEmpty( $css, "build_css() returned empty string for preset '{$slug}'" );
			$this->assertStringContainsString( ':root', $css, "Preset '{$slug}' CSS missing :root block" );
		}
	}
}
