<?php
/**
 * Plugin Name: Next Level FAQ & Accordion
 * Plugin URI:  https://krslys.com/plugins/next-level-faq/
 * Description: Flexible FAQ and Accordion plugin with customizable styling, live preview, and Gutenberg block support.
 * Version:     1.0.0
 * Author:      Krslys
 * Author URI:  https://krslys.com
 * Text Domain: krslys-next-level-faq
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
define( 'NLF_FAQ_CSS_VERSION', '1.0.0' );
define( 'NLF_FAQ_PLUGIN_FILE', __FILE__ );
define( 'NLF_FAQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NLF_FAQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load PSR-4 autoloader.
require_once NLF_FAQ_PLUGIN_DIR . 'includes/Autoloader.php';

// Load global helper functions.
require_once NLF_FAQ_PLUGIN_DIR . 'includes/core/functions.php';

// Initialize autoloader.
$krslys_nlf_autoloader = new \Krslys\NextLevelFaq\Autoloader( NLF_FAQ_PLUGIN_DIR . 'includes' );
$krslys_nlf_autoloader->register();

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
		// Each subsystem registers its own hooks internally.
		\Krslys\NextLevelFaq\Database::init();
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
		\Krslys\NextLevelFaq\Settings_Repository::initialize_defaults();
		\Krslys\NextLevelFaq\Options::activate();
	}
}

// Activation hook (must be registered at file load, before plugins_loaded).
register_activation_hook( NLF_FAQ_PLUGIN_FILE, array( 'Krslys_NextLevelFaq_Plugin', 'activate' ) );

/**
 * Return the main plugin instance.
 *
 * @return Krslys_NextLevelFaq_Plugin
 */
function krslys_nlf_faq() {
	return Krslys_NextLevelFaq_Plugin::instance();
}

add_action( 'plugins_loaded', 'krslys_nlf_faq' );
