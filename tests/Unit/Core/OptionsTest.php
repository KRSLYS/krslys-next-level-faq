<?php
/**
 * Tests for Options::sanitize() — the core input-sanitization method.
 *
 * @package Krslys\NextLevelFaq\Tests\Unit\Core
 */

namespace Krslys\NextLevelFaq\Tests\Unit\Core;

use Krslys\NextLevelFaq\Options;
use Krslys\NextLevelFaq\Presets;
use Krslys\NextLevelFaq\Tests\WpTestCase;

/**
 * @covers \Krslys\NextLevelFaq\Options::sanitize
 */
class OptionsTest extends WpTestCase {

	/** Fully valid input that maps 1-to-1 to the minimal preset defaults. */
	private array $valid_input;

	protected function setUp(): void {
		parent::setUp();

		$this->valid_input = [
			'preset'                  => 'minimal',
			'container_background'    => '#ffffff',
			'container_border_color'  => '#e5e7eb',
			'container_border_radius' => '12',
			'container_padding'       => '28',
			'question_color'          => '#111827',
			'question_font_size'      => '17',
			'question_font_weight'    => '600',
			'answer_color'            => '#6b7280',
			'answer_font_size'        => '15',
			'accent_color'            => '#3b82f6',
			'icon_style'              => 'plus_minus',
			'gap_between_items'       => '12',
			'shadow'                  => 'sm',
			'animation'               => 'slide',
			'layout'                  => 'flat',
		];
	}

	// -----------------------------------------------------------------------
	// Output shape
	// -----------------------------------------------------------------------

	public function test_sanitize_returns_array(): void {
		$this->assertIsArray( Options::sanitize( $this->valid_input ) );
	}

	public function test_sanitize_output_contains_all_required_keys(): void {
		$result = Options::sanitize( $this->valid_input );

		$expected = [
			'preset', 'container_background', 'container_border_color',
			'container_border_radius', 'container_padding',
			'question_color', 'question_font_size', 'question_font_weight',
			'answer_color', 'answer_font_size', 'accent_color',
			'icon_style', 'gap_between_items', 'shadow', 'animation', 'layout',
		];

		foreach ( $expected as $key ) {
			$this->assertArrayHasKey( $key, $result, "Missing key in sanitized output: {$key}" );
		}
	}

	public function test_sanitize_with_non_array_input_returns_defaults(): void {
		$result = Options::sanitize( 'not_an_array' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'preset', $result );
		$this->assertSame( Presets::DEFAULT_PRESET, $result['preset'] );
	}

	public function test_sanitize_with_empty_array_returns_preset_defaults(): void {
		$result        = Options::sanitize( [] );
		$preset_values = Presets::get_preset_values( Presets::DEFAULT_PRESET );

		$this->assertSame( $preset_values['icon_style'], $result['icon_style'] );
		$this->assertSame( $preset_values['layout'], $result['layout'] );
		$this->assertSame( $preset_values['animation'], $result['animation'] );
	}

	// -----------------------------------------------------------------------
	// Preset slug
	// -----------------------------------------------------------------------

	public function test_valid_preset_slug_is_kept(): void {
		$result = Options::sanitize( $this->valid_input );
		$this->assertSame( 'minimal', $result['preset'] );
	}

	public function test_unknown_preset_slug_falls_back_to_default(): void {
		$input           = $this->valid_input;
		$input['preset'] = 'not_a_real_preset';
		$result          = Options::sanitize( $input );
		$this->assertSame( Presets::DEFAULT_PRESET, $result['preset'] );
	}

	public function test_all_valid_preset_slugs_are_accepted(): void {
		foreach ( array_keys( Presets::get_registry() ) as $slug ) {
			$input           = $this->valid_input;
			$input['preset'] = $slug;
			$result          = Options::sanitize( $input );
			$this->assertSame( $slug, $result['preset'] );
		}
	}

	// -----------------------------------------------------------------------
	// Colors
	// -----------------------------------------------------------------------

	public function test_valid_6digit_hex_color_is_kept(): void {
		$result = Options::sanitize( $this->valid_input );
		$this->assertSame( '#ffffff', $result['container_background'] );
		$this->assertSame( '#111827', $result['question_color'] );
		$this->assertSame( '#6b7280', $result['answer_color'] );
		$this->assertSame( '#3b82f6', $result['accent_color'] );
	}

	public function test_invalid_container_background_falls_back_to_preset_default(): void {
		$input                         = $this->valid_input;
		$input['container_background'] = 'not-a-color';
		$result                        = Options::sanitize( $input );
		$preset                        = Presets::get_preset_values( 'minimal' );

		$this->assertSame( $preset['container_background'], $result['container_background'] );
	}

	public function test_invalid_question_color_falls_back_to_preset_default(): void {
		$input                   = $this->valid_input;
		$input['question_color'] = 'rgba(0,0,0,0)';
		$result                  = Options::sanitize( $input );
		$preset                  = Presets::get_preset_values( 'minimal' );

		$this->assertSame( $preset['question_color'], $result['question_color'] );
	}

	public function test_empty_color_falls_back_to_preset_default(): void {
		$input                  = $this->valid_input;
		$input['accent_color']  = '';
		$result                 = Options::sanitize( $input );
		$preset                 = Presets::get_preset_values( 'minimal' );

		$this->assertSame( $preset['accent_color'], $result['accent_color'] );
	}

	// -----------------------------------------------------------------------
	// Numeric fields — minimum clamping
	// -----------------------------------------------------------------------

	public function test_question_font_size_valid_value_cast_to_int(): void {
		$result = Options::sanitize( $this->valid_input );
		$this->assertSame( 17, $result['question_font_size'] );
	}

	public function test_question_font_size_below_minimum_clamped_to_10(): void {
		$input                       = $this->valid_input;
		$input['question_font_size'] = '5';
		$result                      = Options::sanitize( $input );
		$this->assertSame( 10, $result['question_font_size'] );
	}

	public function test_answer_font_size_below_minimum_clamped_to_10(): void {
		$input                     = $this->valid_input;
		$input['answer_font_size'] = '2';
		$result                    = Options::sanitize( $input );
		$this->assertSame( 10, $result['answer_font_size'] );
	}

	public function test_container_border_radius_cannot_be_negative(): void {
		$input                            = $this->valid_input;
		$input['container_border_radius'] = '-5';
		$result                           = Options::sanitize( $input );
		$this->assertSame( 0, $result['container_border_radius'] );
	}

	public function test_container_padding_cannot_be_negative(): void {
		$input                    = $this->valid_input;
		$input['container_padding'] = '-20';
		$result                   = Options::sanitize( $input );
		$this->assertSame( 0, $result['container_padding'] );
	}

	public function test_gap_between_items_cannot_be_negative(): void {
		$input                      = $this->valid_input;
		$input['gap_between_items'] = '-10';
		$result                     = Options::sanitize( $input );
		$this->assertSame( 0, $result['gap_between_items'] );
	}

	// -----------------------------------------------------------------------
	// Font weight — range 100–900
	// -----------------------------------------------------------------------

	public function test_font_weight_valid_value_kept(): void {
		$result = Options::sanitize( $this->valid_input );
		$this->assertSame( 600, $result['question_font_weight'] );
	}

	public function test_font_weight_below_100_clamped_to_100(): void {
		$input                          = $this->valid_input;
		$input['question_font_weight']  = '50';
		$result                         = Options::sanitize( $input );
		$this->assertSame( 100, $result['question_font_weight'] );
	}

	public function test_font_weight_above_900_clamped_to_900(): void {
		$input                          = $this->valid_input;
		$input['question_font_weight']  = '1000';
		$result                         = Options::sanitize( $input );
		$this->assertSame( 900, $result['question_font_weight'] );
	}

	// -----------------------------------------------------------------------
	// Allowlist: icon_style
	// -----------------------------------------------------------------------

	public function test_valid_icon_styles_are_accepted(): void {
		foreach ( [ 'plus_minus', 'chevron', 'arrow' ] as $style ) {
			$input               = $this->valid_input;
			$input['icon_style'] = $style;
			$result              = Options::sanitize( $input );
			$this->assertSame( $style, $result['icon_style'] );
		}
	}

	public function test_invalid_icon_style_falls_back_to_preset_default(): void {
		$input               = $this->valid_input;
		$input['icon_style'] = 'custom_icon';
		$result              = Options::sanitize( $input );
		$preset              = Presets::get_preset_values( 'minimal' );

		$this->assertSame( $preset['icon_style'], $result['icon_style'] );
	}

	// -----------------------------------------------------------------------
	// Allowlist: layout
	// -----------------------------------------------------------------------

	public function test_valid_layouts_are_accepted(): void {
		foreach ( [ 'flat', 'cards', 'bordered', 'clean', 'striped' ] as $layout ) {
			$input            = $this->valid_input;
			$input['layout']  = $layout;
			$result           = Options::sanitize( $input );
			$this->assertSame( $layout, $result['layout'] );
		}
	}

	public function test_invalid_layout_falls_back_to_preset_default(): void {
		$input            = $this->valid_input;
		$input['layout']  = 'masonry';
		$result           = Options::sanitize( $input );
		$preset           = Presets::get_preset_values( 'minimal' );

		$this->assertSame( $preset['layout'], $result['layout'] );
	}

	// -----------------------------------------------------------------------
	// Allowlist: animation
	// -----------------------------------------------------------------------

	public function test_valid_animation_values_are_accepted(): void {
		foreach ( [ 'slide', 'fade', 'none' ] as $animation ) {
			$input               = $this->valid_input;
			$input['animation']  = $animation;
			$result              = Options::sanitize( $input );
			$this->assertSame( $animation, $result['animation'] );
		}
	}

	public function test_invalid_animation_falls_back_to_preset_default(): void {
		$input               = $this->valid_input;
		$input['animation']  = 'bounce';
		$result              = Options::sanitize( $input );
		$preset              = Presets::get_preset_values( 'minimal' );

		$this->assertSame( $preset['animation'], $result['animation'] );
	}

	// -----------------------------------------------------------------------
	// Shadow: supports string names and checkbox boolean
	// -----------------------------------------------------------------------

	public function test_named_shadow_strings_are_accepted(): void {
		foreach ( [ 'none', 'sm', 'md', 'lg', 'xl', 'colored' ] as $shadow ) {
			$input            = $this->valid_input;
			$input['shadow']  = $shadow;
			$result           = Options::sanitize( $input );
			$this->assertSame( $shadow, $result['shadow'] );
		}
	}

	public function test_truthy_shadow_value_becomes_true(): void {
		$input            = $this->valid_input;
		$input['shadow']  = '1';
		$result           = Options::sanitize( $input );
		$this->assertTrue( (bool) $result['shadow'] );
	}

	public function test_falsy_shadow_value_becomes_false(): void {
		$input            = $this->valid_input;
		$input['shadow']  = '';
		$result           = Options::sanitize( $input );
		$this->assertFalse( (bool) $result['shadow'] );
	}
}
