<?php
/**
 * Plugin Name:     Tutor Reviews
 * Description:     Add "Reviews" subbmenu in your Tutor LMS installation.
 * Author:          Vlăduț Ilie
 * Author URI:      https://vladilie.ro
 * Text Domain:     tutor-reviews
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Tutor_Reviews
 */

use TutorReviews\Includes;

defined( 'ABSPATH' ) || exit;

$plugin_root = plugin_dir_path( __FILE__ );

/**
 * Require the main class of the plugin.
 */
require_once $plugin_root . '/includes/class-reviews.php';

new Main();
