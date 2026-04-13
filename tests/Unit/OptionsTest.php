<?php
/**
 * Options sanitization: valid input passes, invalid input falls back to default.
 */

namespace Krslys\NextLevelFaqAccordion\Tests\Unit;

use Krslys\NextLevelFaqAccordion\Options;
use Krslys\NextLevelFaqAccordion\Presets;
use Krslys\NextLevelFaqAccordion\Tests\WpTestCase;

class OptionsTest extends WpTestCase {

	/** Build a valid input array from preset defaults. */
	private function valid_input(): array {
		$defaults = Presets::get_default_values();
		$defaults['preset'] = 'minimal';
		return $defaults;
	}

	// -- Valid preset passes through --

	public function test_valid_preset_passes(): void {
		$input  = $this->valid_input();
		$result = Options::sanitize( $input );

		$this->assertSame( 'minimal', $result['preset'] );
	}

	// -- Invalid preset falls back to default --

	public function test_invalid_preset_falls_back(): void {
		$input = $this->valid_input();
		$input['preset'] = 'hacked';
		$result = Options::sanitize( $input );

		$this->assertSame( Presets::DEFAULT_PRESET, $result['preset'] );
	}

	// -- Valid hex color passes --

	public function test_valid_hex_color_passes(): void {
		$input = $this->valid_input();
		$input['accent_color'] = '#ff0000';
		$result = Options::sanitize( $input );

		$this->assertSame( '#ff0000', $result['accent_color'] );
	}

	// -- Invalid color falls back to preset default --

	public function test_invalid_color_falls_back(): void {
		$input = $this->valid_input();
		$input['accent_color'] = 'not-a-color';
		$result = Options::sanitize( $input );

		$defaults = Presets::get_preset_values( 'minimal' );
		$this->assertSame( $defaults['accent_color'], $result['accent_color'] );
	}

	// -- Negative font size clamped to minimum --

	public function test_negative_font_size_clamped(): void {
		$input = $this->valid_input();
		$input['question_font_size'] = -5;
		$result = Options::sanitize( $input );

		$this->assertGreaterThanOrEqual( 10, $result['question_font_size'] );
	}

	// -- Valid icon style passes --

	public function test_valid_icon_style_passes(): void {
		$input = $this->valid_input();
		$input['icon_style'] = 'chevron';
		$result = Options::sanitize( $input );

		$this->assertSame( 'chevron', $result['icon_style'] );
	}

	// -- Invalid icon style falls back --

	public function test_invalid_icon_style_falls_back(): void {
		$input = $this->valid_input();
		$input['icon_style'] = 'rocket';
		$result = Options::sanitize( $input );

		$this->assertContains( $result['icon_style'], [ 'plus_minus', 'chevron', 'arrow' ] );
	}
}
