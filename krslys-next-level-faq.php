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
		$this->hooks();
	}

	/**
	 * Register hooks and initialize subsystems.
	 */
	private function hooks() {
		add_action( 'admin_init', array( $this, 'maybe_update_schema' ) );
		
		// Each subsystem registers its own hooks internally.
		\Krslys\NextLevelFaq\Frontend_Renderer::init();
		\Krslys\NextLevelFaq\Admin_Settings::init();
		\Krslys\NextLevelFaq\Group_Admin::init();
		\Krslys\NextLevelFaq\Block_Registrar::init();
		\Krslys\NextLevelFaq\Style_Generator::init();
	}

	/**
	 * Consolidated activation handler.
	 *
	 * Runs all activation tasks in the correct order.
	 */
	public static function activate() {
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
		if ( NLF_FAQ_VERSION !== $css_version ) {
			\Krslys\NextLevelFaq\Style_Generator::generate_and_save();
			update_option( 'nlf_faq_css_version', NLF_FAQ_VERSION );
		}
	}
}

// Activation hook (must be registered at file load, before plugins_loaded).
register_activation_hook( NLF_FAQ_PLUGIN_FILE, array( 'Krslys_NextLevelFaq_Plugin', 'activate' ) );

/**
 * Return the main plugin instance.
 *
 * @return Krslys_NextLevelFaq_Plugin
 */
function nlf_faq() {
	return Krslys_NextLevelFaq_Plugin::instance();
}

add_action( 'plugins_loaded', 'nlf_faq' );
