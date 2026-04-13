<?php
/**
 * Theme preset registry.
 *
 * @package Krslys\NextLevelFaqAccordion
 */

namespace Krslys\NextLevelFaqAccordion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized registry of FAQ theme presets.
 *
 * Each preset defines a complete visual identity:
 * - Colors (background, borders, text, accent)
 * - Typography (sizes, weights)
 * - Spacing (padding, gap, border-radius)
 * - Layout (flat, cards, bordered, clean, striped)
 * - Icon style (plus_minus, chevron, arrow)
 * - Shadow intensity (false, sm, md, lg, xl, colored)
 * - Animation (slide, fade, none)
 */
class Presets {

	/**
	 * Default preset slug.
	 */
	const DEFAULT_PRESET = 'minimal';

	/**
	 * Get preset registry.
	 *
	 * @return array[]
	 */
	public static function get_registry() {
		return array(
			// Minimal — Universal neutral baseline
			'minimal'  => array(
				'slug'        => 'minimal',
				'name'        => __( 'Minimal', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'Clean and subtle. Works with any design.', 'krslys-next-level-faq-accordion' ),
				'values'      => array(
					'container_background'    => '#ffffff',
					'container_border_color'  => '#e5e7eb',
					'container_border_radius' => 12,
					'container_padding'       => 28,
					'question_color'          => '#111827',
					'question_font_size'      => 17,
					'question_font_weight'    => 600,
					'answer_color'            => '#6b7280',
					'answer_font_size'        => 15,
					'accent_color'            => '#3b82f6',
					'icon_style'              => 'plus_minus',
					'gap_between_items'       => 0,
					'shadow'                  => 'sm',
					'animation'               => 'slide',
					'layout'                  => 'flat',
				),
			),
			// Modern — Individual floating cards
			'modern'   => array(
				'slug'        => 'modern',
				'name'        => __( 'Modern', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'Floating cards with indigo accent and soft elevation.', 'krslys-next-level-faq-accordion' ),
				'values'      => array(
					'container_background'    => '#ffffff',
					'container_border_color'  => '#e0e7ff',
					'container_border_radius' => 14,
					'container_padding'       => 0,
					'question_color'          => '#1e1b4b',
					'question_font_size'      => 17,
					'question_font_weight'    => 600,
					'answer_color'            => '#64748b',
					'answer_font_size'        => 15,
					'accent_color'            => '#6366f1',
					'icon_style'              => 'chevron',
					'gap_between_items'       => 10,
					'shadow'                  => 'md',
					'animation'               => 'slide',
					'layout'                  => 'cards',
				),
			),
			// Card — Connected bordered accordion
			'card'     => array(
				'slug'        => 'card',
				'name'        => __( 'Card', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'Stacked connected items with clean borders.', 'krslys-next-level-faq-accordion' ),
				'values'      => array(
					'container_background'    => '#ffffff',
					'container_border_color'  => '#d1d5db',
					'container_border_radius' => 10,
					'container_padding'       => 24,
					'question_color'          => '#111827',
					'question_font_size'      => 17,
					'question_font_weight'    => 600,
					'answer_color'            => '#6b7280',
					'answer_font_size'        => 15,
					'accent_color'            => '#059669',
					'icon_style'              => 'plus_minus',
					'gap_between_items'       => 0,
					'shadow'                  => false,
					'animation'               => 'slide',
					'layout'                  => 'bordered',
				),
			),
			// Outline — Content-first with accent indicator
			'outline'  => array(
				'slug'        => 'outline',
				'name'        => __( 'Outline', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'No borders. Answers marked with left accent bar.', 'krslys-next-level-faq-accordion' ),
				'values'      => array(
					'container_background'    => '#ffffff',
					'container_border_color'  => '#e5e5e5',
					'container_border_radius' => 0,
					'container_padding'       => 24,
					'question_color'          => '#18181b',
					'question_font_size'      => 17,
					'question_font_weight'    => 500,
					'answer_color'            => '#52525b',
					'answer_font_size'        => 15,
					'accent_color'            => '#0ea5e9',
					'icon_style'              => 'chevron',
					'gap_between_items'       => 0,
					'shadow'                  => false,
					'animation'               => 'slide',
					'layout'                  => 'clean',
				),
			),
			// Contrast — Dark surface, warm accent
			'contrast' => array(
				'slug'        => 'contrast',
				'name'        => __( 'Contrast', 'krslys-next-level-faq-accordion' ),
				'description' => __( 'Dark surface with orange accent. Bold statement.', 'krslys-next-level-faq-accordion' ),
				'values'      => array(
					'container_background'    => '#0f172a',
					'container_border_color'  => '#1e293b',
					'container_border_radius' => 14,
					'container_padding'       => 28,
					'question_color'          => '#f1f5f9',
					'question_font_size'      => 17,
					'question_font_weight'    => 600,
					'answer_color'            => '#94a3b8',
					'answer_font_size'        => 15,
					'accent_color'            => '#f97316',
					'icon_style'              => 'arrow',
					'gap_between_items'       => 0,
					'shadow'                  => 'lg',
					'animation'               => 'slide',
					'layout'                  => 'striped',
				),
			),
		);
	}

	/**
	 * Get preset values for a slug.
	 *
	 * @param string $slug Preset slug.
	 * @return array|null
	 */
	public static function get_preset_values( $slug ) {
		$registry = self::get_registry();

		return $registry[ $slug ]['values'] ?? null;
	}

	/**
	 * Validate preset slug.
	 *
	 * @param string|null $slug Candidate slug.
	 * @return string
	 */
	public static function normalize_slug( $slug ) {
		$slug = sanitize_key( $slug );

		if ( isset( self::get_registry()[ $slug ] ) ) {
			return $slug;
		}

		return self::DEFAULT_PRESET;
	}

	/**
	 * Get default preset values.
	 *
	 * @return array
	 */
	public static function get_default_values() {
		$values = self::get_preset_values( self::DEFAULT_PRESET );

		return is_array( $values ) ? $values : array();
	}
}
