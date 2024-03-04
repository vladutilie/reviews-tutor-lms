<?php
/**
 * Plugin Name:       Tutor LMS Reviews
 * Description:       Add "Reviews" submenu in your Tutor LMS installation.
 * Author:            Vlăduț Ilie
 * Author URI:        https://vladilie.ro
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tutor-lms-reviews
 * Domain Path:       /languages
 * Version:           1.0.0
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
