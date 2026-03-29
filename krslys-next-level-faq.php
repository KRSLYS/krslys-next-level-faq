<?php
/**
 * Plugin Name: Next Level FAQ
 * Plugin URI:  https://krslys.com/plugins/next-level-faq/
 * Description: Flexible FAQ plugin with customizable styling and live preview.
 * Version:     1.0.0
 * Author:      Krslys
 * Author URI:  https://krslys.com
 * Text Domain: next-level-faq
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Krslys\NextLevelFaq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'NLF_FAQ_VERSION', '1.0.0' );
define( 'NLF_FAQ_PLUGIN_FILE', __FILE__ );
define( 'NLF_FAQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NLF_FAQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load PSR-4 autoloader.
require_once NLF_FAQ_PLUGIN_DIR . 'includes/Autoloader.php';

// Load global helper functions.
require_once NLF_FAQ_PLUGIN_DIR . 'includes/core/functions.php';

// Initialize autoloader.
$autoloader = new \Krslys\NextLevelFaq\Autoloader( NLF_FAQ_PLUGIN_DIR . 'includes' );
$autoloader->register();

/**
 * Main plugin class.
 */
final class Krslys_NextLevelFaq_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Krslys_NextLevelFaq_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Krslys_NextLevelFaq_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->hooks();
	}

	/**
	 * Load required files.
	 * 
	 * Note: Files are now loaded automatically via PSR-4 autoloader.
	 */
	private function includes() {
		// Classes are autoloaded, no manual includes needed.
	}

	/**
	 * Register hooks.
	 */
	private function hooks() {
		// Consolidated activation hook.
		register_activation_hook( NLF_FAQ_PLUGIN_FILE, array( $this, 'activate' ) );

		// Core hooks.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_update_schema' ) );
		add_action( 'init', array( '\Krslys\NextLevelFaq\Group_Admin', 'register' ) );
		add_action( 'init', array( '\Krslys\NextLevelFaq\Frontend_Renderer', 'register_shortcodes' ) );
		add_action( 'init', array( '\Krslys\NextLevelFaq\Frontend_Renderer', 'register_tracking_routes' ) );
		add_action( 'wp_enqueue_scripts', array( '\Krslys\NextLevelFaq\Frontend_Renderer', 'enqueue_styles' ) );
		add_action( 'admin_menu', array( '\Krslys\NextLevelFaq\Admin_Settings', 'register_menu' ) );
		add_action( 'admin_menu', array( '\Krslys\NextLevelFaq\Group_Admin', 'register_admin_pages' ), 20 );
		add_action( 'admin_init', array( '\Krslys\NextLevelFaq\Admin_Settings', 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( '\Krslys\NextLevelFaq\Admin_Settings', 'enqueue_assets' ) );
		add_action( 'admin_post_nlf_faq_export', array( '\Krslys\NextLevelFaq\Admin_Settings', 'handle_export' ) );
		add_action( 'admin_post_nlf_faq_import', array( '\Krslys\NextLevelFaq\Admin_Settings', 'handle_import' ) );
		
		// AJAX handlers for admin settings
		add_action( 'wp_ajax_nlf_save_settings_ajax', array( '\Krslys\NextLevelFaq\Admin_Settings', 'handle_ajax_save_settings' ) );

		// Style generation (no longer tied to wp_options update)
		add_action( 'nlf_faq_settings_updated', array( '\Krslys\NextLevelFaq\Style_Generator', 'generate_and_save' ), 10, 2 );

		// Gutenberg block registration using block.json and dynamic render.
		if ( function_exists( 'register_block_type' ) ) {
			add_action(
				'init',
				function () {
					$block_dir  = NLF_FAQ_PLUGIN_DIR . 'blocks/faq';
					$block_json = $block_dir . '/block.json';

					if ( ! file_exists( $block_json ) ) {
						return;
					}

					// Register editor script BEFORE block registration.
					// The handle must match the editorScript in block.json.
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
							'presets'       => \Krslys\NextLevelFaq\Options::get_preset_registry(),
							'activePreset'  => \Krslys\NextLevelFaq\Options::get_active_preset_slug( \Krslys\NextLevelFaq\Options::get_options() ),
							'defaultPreset' => \Krslys\NextLevelFaq\Options::get_default_preset_slug(),
						)
					);

					// Set script translations for the block editor.
					if ( function_exists( 'wp_set_script_translations' ) ) {
						wp_set_script_translations( 'nlf-faq-block-editor', 'next-level-faq', NLF_FAQ_PLUGIN_DIR . 'languages' );
					}

					// Register style handle BEFORE block registration.
					// The handle must match the "style" in block.json.
					$css_path = \Krslys\NextLevelFaq\Style_Generator::get_css_file_path();
					$css_url  = \Krslys\NextLevelFaq\Style_Generator::get_css_file_url();

					if ( $css_url ) {
						$version = file_exists( $css_path ) ? filemtime( $css_path ) : NLF_FAQ_VERSION;

						wp_register_style(
							'nlf-faq-generated',
							$css_url,
							array(),
							$version
						);
					}

					// Register block type from block.json directory.
					// WordPress will automatically read block.json and use registered scripts/styles.
					$block = register_block_type(
						$block_dir,
						array(
							'render_callback' => function( $attributes, $content ) {
								$group_id = isset( $attributes['groupId'] ) ? (int) $attributes['groupId'] : 0;
								$title    = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
								$preset   = isset( $attributes['preset'] ) ? sanitize_key( $attributes['preset'] ) : '';

								$shortcode = '[krslys_nlf';

								if ( $group_id > 0 ) {
									$shortcode .= ' group="' . esc_attr( $group_id ) . '"';
								}

								if ( '' !== $title ) {
									$shortcode .= ' title="' . esc_attr( $title ) . '"';
								}

								if ( '' !== $preset && \Krslys\NextLevelFaq\Options::is_valid_preset_slug( $preset ) ) {
									$shortcode .= ' preset="' . esc_attr( $preset ) . '"';
								}

								$shortcode .= ']';

								return do_shortcode( $shortcode );
							},
						)
					);

					// Ensure block is properly registered.
					if ( ! $block ) {
						return;
					}
				},
				20
			);
		}
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'next-level-faq',
			false,
			dirname( plugin_basename( NLF_FAQ_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Consolidated activation handler.
	 *
	 * Runs all activation tasks in the correct order.
	 */
	public function activate() {
		\Krslys\NextLevelFaq\Database::create_tables();
		\Krslys\NextLevelFaq\Database::cleanup_legacy_data();
		\Krslys\NextLevelFaq\Settings_Repository::initialize_defaults();
		\Krslys\NextLevelFaq\Options::activate();
	}

	/**
	 * Update database schema when the version changes.
	 *
	 * Database::create_tables() has its own version check internally,
	 * so we simply delegate to it.
	 */
	public function maybe_update_schema() {
		\Krslys\NextLevelFaq\Database::create_tables();

		// Regenerate CSS if the plugin version changed (CSS structure may have changed).
		$css_version = get_option( 'nlf_faq_css_version', '' );
		if ( $css_version !== NLF_FAQ_VERSION ) {
			if ( class_exists( '\Krslys\NextLevelFaq\Style_Generator' ) ) {
				\Krslys\NextLevelFaq\Style_Generator::generate_and_save();
			}
			update_option( 'nlf_faq_css_version', NLF_FAQ_VERSION );
		}
	}
}

/**
 * Initialize plugin.
 */
function nlf_faq_init() {
	return Krslys_NextLevelFaq_Plugin::instance();
}

nlf_faq_init();


