<?php
/**
 * Main class of the plugin
 *
 * Main class of the plugin that handles hooks, internationalization, connects all the plugin features, script and styles enqueuing.
 *
 * @package Tutor_Reviews
 * @subpackage Includes
 * @since 1.0.0
 */

namespace TutorReviews\Includes;

/**
 * Class Main
 *
 * Main class of the plugin that handles hooks, internationalization, connects all the plugin features, script and styles enqueuing.
 *
 * @since 1.0.0
 */
class Main {

	/**
	 * Main constructor
	 *
	 * Initiate the main settings of the plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->init();
	}

	/**
	 * Load dependency files
	 *
	 * Include files and instantiates plugin dependencies.
	 *
	 * @since 1.0.0
	 */
	protected function load_dependencies(): void {
		$plugin_root = $this->plugin_root;

		require_once $plugin_root . '/includes/class-reviews.php';
	}

	/**
	 * Hooks defining
	 *
	 * Define the hooks.
	 *
	 * @since 1.0.0
	 *
	 * @see add_shortcode
	 * @link https://developer.wordpress.org/reference/functions/add_shortcode/
	 */
	protected function init(): void {
		add_action( 'plugins_loaded', array( $this, 'load_text_domain' ) );

		new Reviews();
	}

	/**
	 * Localization
	 *
	 * Setup localization for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @see load_plugin_textdomain
	 * @link https://developer.wordpress.org/reference/functions/load_plugin_textdomain/
	 *
	 * @see plugin_basename
	 * @link https://developer.wordpress.org/reference/functions/plugin_basename/
	 */
	public function load_text_domain(): void {
		load_plugin_textdomain( 'tutor-reviews', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages/' );
	}
}
