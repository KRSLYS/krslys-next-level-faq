<?php
/**
 * Plugin Name: Next Level FAQ & Accordion
 * Plugin URI:  https://krslys.com/plugins/next-level-faq/
 * Description: Flexible FAQ and Accordion plugin with customizable styling, live preview, and Gutenberg block support.
 * Version:     1.0.0
 * Author:      Krslys
 * Author URI:  https://krslys.com
 * Text Domain: krslys-next-level-faq-accordion
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Tested up to:      6.9
 * Requires PHP: 7.4
 *
 * @package Krslys\NextLevelFaqAccordion
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
$krslys_nlfa_autoloader = new \Krslys\NextLevelFaqAccordion\Autoloader( NLF_FAQ_PLUGIN_DIR . 'includes' );
$krslys_nlfa_autoloader->register();

/**
 * Main plugin class.
 */
final class Krslys_NextLevelFaqAccordion_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Krslys_NextLevelFaqAccordion_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Krslys_NextLevelFaqAccordion_Plugin
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
		\Krslys\NextLevelFaqAccordion\Database::init();
		\Krslys\NextLevelFaqAccordion\Frontend_Renderer::init();
		\Krslys\NextLevelFaqAccordion\Admin_Settings::init();
		\Krslys\NextLevelFaqAccordion\Group_Admin::init();
		\Krslys\NextLevelFaqAccordion\Block_Registrar::init();
		\Krslys\NextLevelFaqAccordion\Style_Generator::init();
	}

	/**
	 * Consolidated activation handler.
	 *
	 * Runs all activation tasks in the correct order.
	 */
	public static function activate() {
		\Krslys\NextLevelFaqAccordion\Database::create_tables();
		\Krslys\NextLevelFaqAccordion\Settings_Repository::initialize_defaults();
		\Krslys\NextLevelFaqAccordion\Options::activate();

		// Grant custom capability to administrators.
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'manage_krslys_nlfa' );
		}
	}
}

// Activation hook (must be registered at file load, before plugins_loaded).
register_activation_hook( NLF_FAQ_PLUGIN_FILE, array( 'Krslys_NextLevelFaqAccordion_Plugin', 'activate' ) );

/**
 * Return the main plugin instance.
 *
 * @return Krslys_NextLevelFaqAccordion_Plugin
 */
function krslys_nlfa_faq() {
	return Krslys_NextLevelFaqAccordion_Plugin::instance();
}

add_action( 'plugins_loaded', 'krslys_nlfa_faq' );
