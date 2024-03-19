<?php
/**
 * Plugin Name:       Reviews for Tutor LMS
 * Description:       Add "Reviews" submenu in your Tutor LMS installation.
 * Author:            Vlad Ilie
 * Author URI:        https://vladilie.ro
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       reviews-tutor-lms
 * Domain Path:       /languages
 * Version:           1.0.0
 * Requires at least: 4.6
 * Requires PHP:      7.4
 *
 * @package           Reviews_Tutor_LMS
 */

use ReviewsTutorLms\Includes\Main;

defined( 'ABSPATH' ) || exit;

$plugin_root = plugin_dir_path( __FILE__ );

/**
 * Require the main class of the plugin.
 */
require_once $plugin_root . '/includes/class-main.php';

new Main( $plugin_root );
