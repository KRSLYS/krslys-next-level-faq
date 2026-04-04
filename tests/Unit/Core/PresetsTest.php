<?php
/**
 * Tests for the Presets class.
 *
 * @package Krslys\NextLevelFaq\Tests\Unit\Core
 */

namespace Krslys\NextLevelFaq\Tests\Unit\Core;

use Krslys\NextLevelFaq\Presets;
use Krslys\NextLevelFaq\Tests\WpTestCase;

/**
 * @covers \Krslys\NextLevelFaq\Presets
 */
class PresetsTest extends WpTestCase {

	// -----------------------------------------------------------------------
	// Registry shape
	// -----------------------------------------------------------------------

	public function test_get_registry_returns_five_presets(): void {
		$this->assertCount( 5, Presets::get_registry() );
	}

	public function test_registry_contains_all_expected_slugs(): void {
		$registry = Presets::get_registry();

		foreach ( [ 'minimal', 'modern', 'card', 'outline', 'contrast' ] as $slug ) {
			$this->assertArrayHasKey( $slug, $registry, "Missing preset: {$slug}" );
		}
	}

	public function test_each_preset_has_slug_name_description_and_values(): void {
		foreach ( Presets::get_registry() as $slug => $preset ) {
			$this->assertArrayHasKey( 'slug', $preset, "Preset '{$slug}' missing 'slug'" );
			$this->assertArrayHasKey( 'name', $preset, "Preset '{$slug}' missing 'name'" );
			$this->assertArrayHasKey( 'description', $preset, "Preset '{$slug}' missing 'description'" );
			$this->assertArrayHasKey( 'values', $preset, "Preset '{$slug}' missing 'values'" );
			$this->assertSame( $slug, $preset['slug'], "Preset slug key mismatch for '{$slug}'" );
		}
	}

	public function test_each_preset_values_has_all_required_style_keys(): void {
		$required = [
			'container_background',
			'container_border_color',
			'container_border_radius',
			'container_padding',
			'question_color',
			'question_font_size',
			'question_font_weight',
			'answer_color',
			'answer_font_size',
			'accent_color',
			'icon_style',
			'gap_between_items',
			'shadow',
			'animation',
			'layout',
		];

		foreach ( Presets::get_registry() as $slug => $preset ) {
			foreach ( $required as $key ) {
				$this->assertArrayHasKey( $key, $preset['values'], "Preset '{$slug}' values missing '{$key}'" );
			}
		}
	}

	public function test_numeric_value_fields_are_integers(): void {
		$int_keys = [
			'container_border_radius',
			'container_padding',
			'question_font_size',
			'question_font_weight',
			'answer_font_size',
			'gap_between_items',
		];

		foreach ( Presets::get_registry() as $slug => $preset ) {
			foreach ( $int_keys as $key ) {
				$this->assertIsInt(
					$preset['values'][ $key ],
					"Preset '{$slug}' field '{$key}' should be int"
				);
			}
		}
	}

	// -----------------------------------------------------------------------
	// Default preset constant
	// -----------------------------------------------------------------------

	public function test_default_preset_constant_is_minimal(): void {
		$this->assertSame( 'minimal', Presets::DEFAULT_PRESET );
	}

	// -----------------------------------------------------------------------
	// normalize_slug
	// -----------------------------------------------------------------------

	public function test_normalize_slug_returns_valid_slug_unchanged(): void {
		foreach ( [ 'minimal', 'modern', 'card', 'outline', 'contrast' ] as $slug ) {
			$this->assertSame( $slug, Presets::normalize_slug( $slug ) );
		}
	}

	public function test_normalize_slug_returns_default_for_unknown_slug(): void {
		$this->assertSame( Presets::DEFAULT_PRESET, Presets::normalize_slug( 'nonexistent' ) );
	}

	public function test_normalize_slug_returns_default_for_empty_string(): void {
		$this->assertSame( Presets::DEFAULT_PRESET, Presets::normalize_slug( '' ) );
	}

	public function test_normalize_slug_returns_default_for_null(): void {
		$this->assertSame( Presets::DEFAULT_PRESET, Presets::normalize_slug( null ) );
	}

	// -----------------------------------------------------------------------
	// get_preset_values
	// -----------------------------------------------------------------------

	public function test_get_preset_values_returns_array_for_valid_slug(): void {
		$values = Presets::get_preset_values( 'minimal' );
		$this->assertIsArray( $values );
		$this->assertNotEmpty( $values );
	}

	public function test_get_preset_values_returns_null_for_invalid_slug(): void {
		$this->assertNull( Presets::get_preset_values( 'does_not_exist' ) );
	}

	public function test_get_preset_values_returns_null_for_empty_slug(): void {
		$this->assertNull( Presets::get_preset_values( '' ) );
	}

	// -----------------------------------------------------------------------
	// get_default_values
	// -----------------------------------------------------------------------

	public function test_get_default_values_matches_minimal_preset(): void {
		$this->assertSame(
			Presets::get_preset_values( 'minimal' ),
			Presets::get_default_values()
		);
	}

	public function test_get_default_values_returns_array(): void {
		$this->assertIsArray( Presets::get_default_values() );
		$this->assertNotEmpty( Presets::get_default_values() );
	}

	// -----------------------------------------------------------------------
	// Spot-checks on specific preset values
	// -----------------------------------------------------------------------

	public function test_minimal_uses_plus_minus_icon(): void {
		$values = Presets::get_preset_values( 'minimal' );
		$this->assertSame( 'plus_minus', $values['icon_style'] );
	}

	public function test_modern_uses_cards_layout(): void {
		$values = Presets::get_preset_values( 'modern' );
		$this->assertSame( 'cards', $values['layout'] );
	}

	public function test_contrast_uses_striped_layout(): void {
		$values = Presets::get_preset_values( 'contrast' );
		$this->assertSame( 'striped', $values['layout'] );
	}

	public function test_outline_uses_clean_layout(): void {
		$values = Presets::get_preset_values( 'outline' );
		$this->assertSame( 'clean', $values['layout'] );
	}

	public function test_icon_style_is_one_of_allowed_values(): void {
		$allowed = [ 'plus_minus', 'chevron', 'arrow' ];
		foreach ( Presets::get_registry() as $slug => $preset ) {
			$this->assertContains(
				$preset['values']['icon_style'],
				$allowed,
				"Preset '{$slug}' has invalid icon_style"
			);
		}
	}

	public function test_layout_is_one_of_allowed_values(): void {
		$allowed = [ 'flat', 'cards', 'bordered', 'clean', 'striped' ];
		foreach ( Presets::get_registry() as $slug => $preset ) {
			$this->assertContains(
				$preset['values']['layout'],
				$allowed,
				"Preset '{$slug}' has invalid layout"
			);
		}
	}

	public function test_animation_is_one_of_allowed_values(): void {
		$allowed = [ 'slide', 'fade', 'none' ];
		foreach ( Presets::get_registry() as $slug => $preset ) {
			$this->assertContains(
				$preset['values']['animation'],
				$allowed,
				"Preset '{$slug}' has invalid animation"
			);
		}
	}

	public function test_question_font_size_is_at_least_10(): void {
		foreach ( Presets::get_registry() as $slug => $preset ) {
			$this->assertGreaterThanOrEqual(
				10,
				$preset['values']['question_font_size'],
				"Preset '{$slug}' question_font_size below minimum"
			);
		}
	}

	public function test_question_font_weight_is_within_valid_range(): void {
		foreach ( Presets::get_registry() as $slug => $preset ) {
			$weight = $preset['values']['question_font_weight'];
			$this->assertGreaterThanOrEqual( 100, $weight, "Preset '{$slug}' weight too low" );
			$this->assertLessThanOrEqual( 900, $weight, "Preset '{$slug}' weight too high" );
		}
	}

	public function test_color_fields_are_valid_hex(): void {
		$color_keys = [ 'container_background', 'container_border_color', 'question_color', 'answer_color', 'accent_color' ];
		foreach ( Presets::get_registry() as $slug => $preset ) {
			foreach ( $color_keys as $key ) {
				$this->assertMatchesRegularExpression(
					'/^#([0-9a-f]{3}|[0-9a-f]{6})$/i',
					$preset['values'][ $key ],
					"Preset '{$slug}' field '{$key}' is not a valid hex color"
				);
			}
		}
	}
}
