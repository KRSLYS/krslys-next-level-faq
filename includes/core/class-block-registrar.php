<?php
/**
 * Gutenberg block registration.
 *
 * @package Krslys\NextLevelFaq
 */

namespace Krslys\NextLevelFaq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the FAQ Gutenberg block and its assets.
 */
class Block_Registrar {

	/**
	 * Bootstrap block registration hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Register the FAQ block.
	 *
	 * Hooked to `init` at priority 20.
	 */
	public static function register() {
		$block_dir  = NLF_FAQ_PLUGIN_DIR . 'blocks/faq';
		$block_json = $block_dir . '/block.json';

		if ( ! file_exists( $block_json ) ) {
			return;
		}

		self::register_editor_script();
		self::register_generated_style();

		register_block_type(
			$block_dir,
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Register and localize the block editor script.
	 */
	private static function register_editor_script() {
		wp_register_script(
			'nlf-faq-block-editor',
			nlf_asset_url( 'blocks/faq/editor.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-data' ),
			NLF_FAQ_VERSION,
			true
		);

		wp_localize_script(
			'nlf-faq-block-editor',
			'nlfFaqBlockData',
			array(
				'presets'       => Options::get_preset_registry(),
				'activePreset'  => Options::get_active_preset_slug( Options::get_options() ),
				'defaultPreset' => Options::get_default_preset_slug(),
			)
		);

		wp_set_script_translations( 'nlf-faq-block-editor', 'next-level-faq', NLF_FAQ_PLUGIN_DIR . 'languages' );
	}

	/**
	 * Register the generated CSS style handle.
	 */
	private static function register_generated_style() {
		$css_path = Style_Generator::get_css_file_path();
		$css_url  = Style_Generator::get_css_file_url();

		if ( ! $css_url ) {
			return;
		}

		$version = file_exists( $css_path ) ? filemtime( $css_path ) : NLF_FAQ_CSS_VERSION;

		wp_register_style(
			'nlf-faq-generated',
			$css_url,
			array(),
			$version
		);
	}

	/**
	 * Render callback for the FAQ block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string Rendered HTML.
	 */
	public static function render_block( $attributes, $content ) {
		$atts = array(
			'title'  => isset( $attributes['title'] ) ? (string) $attributes['title'] : '',
			'group'  => isset( $attributes['groupId'] ) ? (int) $attributes['groupId'] : 0,
			'preset' => isset( $attributes['preset'] ) ? sanitize_key( $attributes['preset'] ) : '',
		);

		return Frontend_Renderer::render_shortcode( $atts );
	}
}
