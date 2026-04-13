<?php
/**
 * Handles FAQ style options.
 *
 * @package Krslys\NextLevelFaqAccordion
 */

namespace Krslys\NextLevelFaqAccordion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles FAQ style options.
 *
 * SECURITY FEATURES:
 * - All color values validated via sanitize_hex_color().
 * - Numeric values type-cast to integers with min/max boundaries.
 * - String values validated against allowlists.
 * - No direct user input accepted without sanitization.
 */
class Options {

	const OPTION_KEY = 'nlf_faq_style_options';

	/**
	 * Get preset registry.
	 *
	 * @return array[]
	 */
	public static function get_preset_registry() {
		return Presets::get_registry();
	}

	/**
	 * Get default preset slug.
	 *
	 * @return string
	 */
	public static function get_default_preset_slug() {
		return Presets::DEFAULT_PRESET;
	}

	/**
	 * Default options for FAQ styles.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		$base = Presets::get_default_values();

		return array_merge(
			$base,
			array(
				'preset' => self::get_default_preset_slug(),
			)
		);
	}

	/**
	 * Get options merged with defaults.
	 *
	 * @return array
	 */
	public static function get_options() {
		$defaults = self::get_defaults();
		$saved    = Settings_Repository::get_setting( Settings_Repository::KEY_GLOBAL_STYLES, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Get options merged with preset defaults for rendering.
	 *
	 * @return array
	 */
	public static function get_resolved_options() {
		$options = self::get_options();

		return self::resolve_for_preset( $options['preset'] ?? null, $options );
	}

	/**
	 * Resolve an options array against a preset.
	 *
	 * @param string|null $preset_slug Preset slug (optional).
	 * @param array       $overrides   Overrides (usually saved options).
	 *
	 * @return array
	 */
	public static function resolve_for_preset( $preset_slug = null, array $overrides = array() ) {
		$slug = self::get_active_preset_slug( $overrides, $preset_slug );

		$preset_values = Presets::get_preset_values( $slug );

		if ( ! is_array( $preset_values ) ) {
			$preset_values = self::get_defaults();
		}

		$resolved = wp_parse_args(
			$overrides,
			array_merge(
				$preset_values,
				array(
					'preset' => $slug,
				)
			)
		);

		return $resolved;
	}

	/**
	 * Determine the active preset slug.
	 *
	 * @param array       $options     Options array (optional).
	 * @param string|null $fallback_slug Fallback slug.
	 *
	 * @return string
	 */
	public static function get_active_preset_slug( array $options = array(), $fallback_slug = null ) {
		if ( ! empty( $fallback_slug ) ) {
			return Presets::normalize_slug( $fallback_slug );
		}

		$slug = isset( $options['preset'] ) ? $options['preset'] : Presets::DEFAULT_PRESET;

		return Presets::normalize_slug( $slug );
	}

	/**
	 * Check if a preset slug is valid.
	 *
	 * @param string $slug Preset slug.
	 *
	 * @return bool
	 */
	public static function is_valid_preset_slug( $slug ) {
		$registry = Presets::get_registry();

		return isset( $registry[ sanitize_key( $slug ) ] );
	}

	/**
	 * Sanitize and save options.
	 *
	 * SECURITY:
	 * - All color values validated via sanitize_hex_color().
	 * - Numeric values type-cast with min/max boundaries.
	 * - Icon style and animation validated against allowlists.
	 * - Shadow value converted to strict boolean.
	 *
	 * @param array $raw Raw input.
	 *
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$defaults = self::get_defaults();
		$sanitized = array();

	$raw = is_array( $raw ) ? $raw : array();

	$preset_slug = isset( $raw['preset'] ) ? sanitize_key( $raw['preset'] ) : self::get_default_preset_slug();
	$preset_slug = self::is_valid_preset_slug( $preset_slug ) ? $preset_slug : self::get_default_preset_slug();
	$sanitized['preset'] = $preset_slug;

	$preset_defaults = Presets::get_preset_values( $preset_slug );
	if ( ! is_array( $preset_defaults ) ) {
		$preset_defaults = $defaults;
	}

	$sanitized['container_background']    = isset( $raw['container_background'] ) ? sanitize_hex_color( $raw['container_background'] ) : $defaults['container_background'];
	$sanitized['container_border_color']  = isset( $raw['container_border_color'] ) ? sanitize_hex_color( $raw['container_border_color'] ) : $defaults['container_border_color'];
	
	if ( empty( $sanitized['container_background'] ) ) {
		$sanitized['container_background'] = $preset_defaults['container_background'] ?? $defaults['container_background'];
	}
	if ( empty( $sanitized['container_border_color'] ) ) {
		$sanitized['container_border_color'] = $preset_defaults['container_border_color'] ?? $defaults['container_border_color'];
	}

	$sanitized['container_border_radius'] = isset( $raw['container_border_radius'] ) ? max( 0, intval( $raw['container_border_radius'] ) ) : ( $preset_defaults['container_border_radius'] ?? $defaults['container_border_radius'] );
	$sanitized['container_padding']       = isset( $raw['container_padding'] ) ? max( 0, intval( $raw['container_padding'] ) ) : ( $preset_defaults['container_padding'] ?? $defaults['container_padding'] );

	$sanitized['question_color']      = isset( $raw['question_color'] ) ? sanitize_hex_color( $raw['question_color'] ) : $defaults['question_color'];
	if ( empty( $sanitized['question_color'] ) ) {
		$sanitized['question_color'] = $preset_defaults['question_color'] ?? $defaults['question_color'];
	}

	$sanitized['question_font_size']  = isset( $raw['question_font_size'] ) ? max( 10, intval( $raw['question_font_size'] ) ) : ( $preset_defaults['question_font_size'] ?? $defaults['question_font_size'] );
	
	$sanitized['question_font_weight'] = isset( $raw['question_font_weight'] ) ? max( 100, min( 900, intval( $raw['question_font_weight'] ) ) ) : ( $preset_defaults['question_font_weight'] ?? $defaults['question_font_weight'] );

	$sanitized['answer_color']     = isset( $raw['answer_color'] ) ? sanitize_hex_color( $raw['answer_color'] ) : $defaults['answer_color'];
	if ( empty( $sanitized['answer_color'] ) ) {
		$sanitized['answer_color'] = $preset_defaults['answer_color'] ?? $defaults['answer_color'];
	}
	
	$sanitized['answer_font_size'] = isset( $raw['answer_font_size'] ) ? max( 10, intval( $raw['answer_font_size'] ) ) : ( $preset_defaults['answer_font_size'] ?? $defaults['answer_font_size'] );

	$sanitized['accent_color'] = isset( $raw['accent_color'] ) ? sanitize_hex_color( $raw['accent_color'] ) : $defaults['accent_color'];
	if ( empty( $sanitized['accent_color'] ) ) {
		$sanitized['accent_color'] = $preset_defaults['accent_color'] ?? $defaults['accent_color'];
	}

	$allowed_icon_styles = array( 'plus_minus', 'chevron', 'arrow' );
	$sanitized['icon_style'] = in_array( $raw['icon_style'] ?? '', $allowed_icon_styles, true )
		? $raw['icon_style']
		: ( $preset_defaults['icon_style'] ?? $defaults['icon_style'] );

	$sanitized['gap_between_items'] = isset( $raw['gap_between_items'] ) ? max( 0, intval( $raw['gap_between_items'] ) ) : ( $preset_defaults['gap_between_items'] ?? $defaults['gap_between_items'] );
	
	// Shadow - supports boolean (checkbox) or string ('sm', 'md', 'lg', 'xl', 'colored').
	$allowed_shadows = array( 'none', 'sm', 'md', 'lg', 'xl', 'colored' );
	if ( isset( $raw['shadow'] ) && is_string( $raw['shadow'] ) && in_array( $raw['shadow'], $allowed_shadows, true ) ) {
		$sanitized['shadow'] = $raw['shadow'];
	} else {
		$sanitized['shadow'] = ! empty( $raw['shadow'] );
	}

	$allowed_animation = array( 'slide', 'fade', 'none' );
	$sanitized['animation'] = in_array( $raw['animation'] ?? '', $allowed_animation, true )
		? $raw['animation']
		: ( $preset_defaults['animation'] ?? $defaults['animation'] );

	$allowed_layouts = array( 'flat', 'cards', 'bordered', 'clean', 'striped' );
	$sanitized['layout'] = in_array( $raw['layout'] ?? '', $allowed_layouts, true )
		? $raw['layout']
		: ( $preset_defaults['layout'] ?? 'flat' );

	return $sanitized;
	}

	/**
	 * Activation callback.
	 */
	public static function activate() {
		// Initialize settings in new settings table
		if ( ! Settings_Repository::setting_exists( Settings_Repository::KEY_GLOBAL_STYLES ) ) {
			Settings_Repository::update_setting( Settings_Repository::KEY_GLOBAL_STYLES, self::get_defaults() );
		}

		// Generate CSS for all presets
		if ( class_exists( 'Krslys\NextLevelFaqAccordion\Style_Generator' ) ) {
			Style_Generator::generate_all_presets();
			Settings_Repository::update_setting( 'presets_css_version', NLF_FAQ_CSS_VERSION );
		}
	}
}

