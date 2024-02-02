<?php
/**
 * Plugin Name:       Tutor LMS Reviews
 * Description:       Add "Reviews" submenu in your Tutor LMS installation.
 * Author:            Vlăduț Ilie
 * Author URI:        https://vladilie.ro
 * Text Domain:       tutor-lms-reviews
 * Domain Path:       /languages
 * Version:           0.1.0
 * Requires at least: 4.6
 * Requires PHP:      7.4
 *
 * @package           Tutor_LMS_Reviews
 */

use TutorLmsReviews\Includes\Main;

defined( 'ABSPATH' ) || exit;

$plugin_root = plugin_dir_path( __FILE__ );

/**
 * Require the main class of the plugin.
 */
require_once $plugin_root . '/includes/class-main.php';

new Main( $plugin_root );
