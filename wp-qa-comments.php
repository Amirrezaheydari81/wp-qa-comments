<?php
/**
 * Plugin Name:       پرسش و پاسخ (WP Q&A Comments)
 * Plugin URI:        https://clarotm.ir
 * Description:       سیستم سبک پرسش و پاسخ برای نوشته‌ها، برگه‌ها و انواع محتوای سفارشی. شامل درون‌ریزی JSON برای محتوای دمو و تست.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Amirreza Heydari
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-qa-comments
 * Domain Path:       /languages
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

define( 'WPQA_VERSION', '1.0.1' );
define( 'WPQA_PLUGIN_FILE', __FILE__ );
define( 'WPQA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPQA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPQA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WPQA_PLUGIN_DIR . 'includes/class-database.php';
require_once WPQA_PLUGIN_DIR . 'includes/class-settings.php';
require_once WPQA_PLUGIN_DIR . 'includes/class-jalali.php';
require_once WPQA_PLUGIN_DIR . 'includes/class-comments.php';
require_once WPQA_PLUGIN_DIR . 'includes/class-importer.php';
require_once WPQA_PLUGIN_DIR . 'includes/class-ajax.php';
require_once WPQA_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Main plugin bootstrap.
 */
final class WP_QA_Comments {

	/**
	 * Singleton instance.
	 *
	 * @var WP_QA_Comments|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WP_QA_Comments
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
		$this->init_hooks();
	}

	/**
	 * Register core hooks.
	 */
	private function init_hooks() {
		register_activation_hook( WPQA_PLUGIN_FILE, array( 'WPQA_Database', 'activate' ) );
		register_deactivation_hook( WPQA_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wp-qa-comments',
			false,
			dirname( WPQA_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components.
	 */
	public function init() {
		WPQA_Settings::instance();
		WPQA_Comments::instance();
		WPQA_Ajax::instance();

		if ( is_admin() ) {
			WPQA_Admin::instance();
		}
	}

	/**
	 * Deactivation callback.
	 */
	public function deactivate() {
		// Intentionally empty — data cleanup is handled in uninstall.php.
	}
}

/**
 * Returns the main plugin instance.
 *
 * @return WP_QA_Comments
 */
function wpqa() {
	return WP_QA_Comments::instance();
}

wpqa();
