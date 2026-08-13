<?php
/**
 * Plugin Name:       Reviews for Tutor LMS
 * Description:       This plugin enables the course reviews for Tutor LMS installation and allows you to manage them.
 * Author:            Vlad Ilie
 * Author URI:        https://vladilie.ro
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       reviews-tutor-lms
 * Domain Path:       /languages
 * Version:           1.0.3
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  tutor
 *
 * @package           Reviews_Tutor_LMS
 */

use ReviewsTutorLms\Includes\Main;

defined( 'ABSPATH' ) || exit;

/**
 * Require the main class of the plugin.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-main.php';

new Main( plugin_dir_path( __FILE__ ) );
