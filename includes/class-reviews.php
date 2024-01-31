<?php
/**
 * Main class
 *
 * Main class of the plugin that handles hooks, internationalization, connects all the plugin features, migrations and
 * script and styles enqueuing.
 *
 * @package Tutor_Reviews
 * @subpackage Includes
 * @since 1.0.0
 */

namespace TutorReviews\Includes;

/**
 * Class Main
 *
 * Main class of the plugin that handles hooks, internationalization, connects all the plugin features, migrations and
 * script and styles enqueuing.
 *
 * @since 1.0.0
 */
class Reviews {

	/**
	 * Main constructor
	 *
	 * Add the "Reviews" submenu.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action(
			'tutor_after_courses_menu',
			function () {
				add_submenu_page( 'tutor', __( 'Reviews', 'tutor-reviews' ), __( 'Reviews', 'tutor-reviews' ), 'manage_tutor_instructor', 'tutor', array( $this, 'tutor_course_list' ) );
			}
		);
	}
}
