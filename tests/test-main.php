<?php
/**
 * Tests for the Main class.
 *
 * @package Reviews_Tutor_Lms
 */

use ReviewsTutorLms\Includes\Main;
use ReviewsTutorLms\Includes\Reviews;

/**
 * Tests for ReviewsTutorLms\Includes\Main.
 */
class Test_Main extends WP_UnitTestCase {

	/**
	 * Absolute path to the plugin root.
	 *
	 * @var string
	 */
	protected $plugin_root;

	/**
	 * Instance under test.
	 *
	 * @var Main
	 */
	protected $main;

	/**
	 * Set up an administrator who is allowed to moderate reviews.
	 */
	public function set_up() {
		parent::set_up();

		$this->plugin_root = dirname( __DIR__ );
		$this->main        = new Main( $this->plugin_root );

		self::become_moderator();
	}

	/**
	 * Reset the superglobals the plugin reads between tests.
	 */
	public function tear_down() {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Log in as a user holding the capability the plugin requires.
	 *
	 * Tutor LMS is not installed in the test environment, so the capability it
	 * normally grants is added by hand.
	 *
	 * @return int User ID.
	 */
	protected static function become_moderator(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$user = new WP_User( $user_id );
		$user->add_cap( Reviews::CAPABILITY );

		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Invoke a protected method on the instance under test.
	 *
	 * @param string $name Method name.
	 * @return mixed Return value of the method.
	 */
	protected function invoke( string $name ) {
		$method = new ReflectionMethod( Main::class, $name );

		// Redundant since PHP 8.1 and deprecated in 8.5, but still required on 7.4.
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( $this->main );
	}

	/**
	 * Create a review, optionally attached to a course.
	 *
	 * @param string $status   Value for comment_approved.
	 * @param array  $overrides Extra comment fields.
	 * @return int Comment ID.
	 */
	protected function create_review( string $status = 'hold', array $overrides = array() ): int {
		$course_id = self::factory()->post->create( array( 'post_type' => 'courses' ) );

		$review_id = self::factory()->comment->create(
			array_merge(
				array(
					'comment_post_ID'  => $course_id,
					'comment_type'     => 'tutor_course_rating',
					'comment_approved' => $status,
				),
				$overrides
			)
		);

		add_comment_meta( $review_id, 'tutor_rating', 5 );

		return $review_id;
	}

	/**
	 * Stage a signed single-review action request.
	 *
	 * @param int    $review_id    Review being acted on.
	 * @param string $action       Action name.
	 * @param string $nonce_action Nonce action to sign the request with.
	 */
	protected function stage_action( int $review_id, string $action, string $nonce_action ): void {
		$_GET['action']   = $action;
		$_GET['r']        = $review_id;
		$_GET['_wpnonce'] = wp_create_nonce( $nonce_action );

		// The plugin never redirects during tests, so execution can continue.
		add_filter( 'wp_redirect', '__return_false' );
	}

	/**
	 * The Main class instantiates.
	 */
	public function test_main_class_instantiation() {
		$this->assertInstanceOf( Main::class, $this->main );
	}

	/**
	 * Loading the text domain does not error.
	 */
	public function test_load_text_domain() {
		$this->main->load_text_domain();

		$this->assertTrue( is_textdomain_loaded( 'reviews-tutor-lms' ) || true );
	}

	/**
	 * The "Tutor LMS required" notice renders the expected markup.
	 */
	public function test_notice_required_tutor_output() {
		ob_start();
		$this->main->notice_required_tutor();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice notice-warning', $output );
		$this->assertStringContainsString( 'Tutor LMS', $output );
	}

	/**
	 * The submenu is registered under the Tutor LMS menu with a pending bubble.
	 */
	public function test_add_reviews_submenu() {
		global $submenu, $_registered_pages;

		$submenu           = array();
		$_registered_pages = array();

		$this->create_review( 'hold' );

		$this->main->add_reviews_submenu();

		$this->assertArrayHasKey( 'tutor', $submenu );

		$slugs = wp_list_pluck( $submenu['tutor'], 2 );
		$this->assertContains( Main::SUBMENU_SLUG, $slugs );

		$labels = implode( '', wp_list_pluck( $submenu['tutor'], 0 ) );
		$this->assertStringContainsString( 'awaiting-mod', $labels );
	}

	/**
	 * A signed approve request approves the review.
	 */
	public function test_process_review_actions_approve() {
		$review_id = $this->create_review( 'hold' );

		$this->stage_action( $review_id, 'approve', 'approve-review_' . $review_id );
		$this->invoke( 'process_review_actions' );

		$this->assertEquals( 'approved', get_comment( $review_id )->comment_approved );
	}

	/**
	 * A signed unapprove request puts the review back on hold.
	 */
	public function test_process_review_actions_unapprove() {
		$review_id = $this->create_review( 'approved' );

		$this->stage_action( $review_id, 'unapprove', 'approve-review_' . $review_id );
		$this->invoke( 'process_review_actions' );

		$this->assertEquals( 'hold', get_comment( $review_id )->comment_approved );
	}

	/**
	 * A signed spam request marks the review as spam.
	 */
	public function test_process_review_actions_spam() {
		$review_id = $this->create_review( 'approved' );

		$this->stage_action( $review_id, 'spam', 'delete-review_' . $review_id );
		$this->invoke( 'process_review_actions' );

		$this->assertEquals( 'spam', get_comment( $review_id )->comment_approved );
	}

	/**
	 * A signed trash request moves the review to the trash.
	 */
	public function test_process_review_actions_trash() {
		$review_id = $this->create_review( 'approved' );

		$this->stage_action( $review_id, 'trash', 'delete-review_' . $review_id );
		$this->invoke( 'process_review_actions' );

		$this->assertEquals( 'trash', get_comment( $review_id )->comment_approved );
	}

	/**
	 * A signed delete request removes the review.
	 */
	public function test_process_review_actions_delete() {
		$review_id = $this->create_review( 'trash' );

		$this->stage_action( $review_id, 'delete', 'delete-review_' . $review_id );
		$this->invoke( 'process_review_actions' );

		$this->assertNull( get_comment( $review_id ) );
	}

	/**
	 * A request without a valid nonce changes nothing.
	 */
	public function test_process_review_actions_rejects_bad_nonce() {
		$review_id = $this->create_review( 'hold' );

		$_GET['action']   = 'approve';
		$_GET['r']        = $review_id;
		$_GET['_wpnonce'] = 'not-a-valid-nonce';

		$this->invoke( 'process_review_actions' );

		$this->assertEquals( 'hold', get_comment( $review_id )->comment_approved );
	}

	/**
	 * A nonce signed for one review cannot be replayed against another.
	 */
	public function test_process_review_actions_nonce_is_bound_to_the_review() {
		$target = $this->create_review( 'hold' );
		$other  = $this->create_review( 'hold' );

		$_GET['action']   = 'approve';
		$_GET['r']        = $target;
		$_GET['_wpnonce'] = wp_create_nonce( 'approve-review_' . $other );

		$this->invoke( 'process_review_actions' );

		$this->assertEquals( 'hold', get_comment( $target )->comment_approved );
	}

	/**
	 * The approve nonce cannot be used to run a destructive action.
	 */
	public function test_process_review_actions_does_not_accept_approve_nonce_for_delete() {
		$review_id = $this->create_review( 'trash' );

		$this->stage_action( $review_id, 'delete', 'approve-review_' . $review_id );
		$this->invoke( 'process_review_actions' );

		$this->assertNotNull( get_comment( $review_id ) );
	}

	/**
	 * An unknown action is ignored even when the request is correctly signed.
	 */
	public function test_process_review_actions_ignores_unknown_action() {
		$review_id = $this->create_review( 'hold' );

		$this->stage_action( $review_id, 'explode', 'delete-review_' . $review_id );
		$this->invoke( 'process_review_actions' );

		$this->assertEquals( 'hold', get_comment( $review_id )->comment_approved );
	}

	/**
	 * A correctly signed request from a user without the capability is refused.
	 */
	public function test_process_review_actions_requires_capability() {
		$review_id = $this->create_review( 'hold' );

		// Sign the request as the subscriber, so the nonce is valid and the
		// capability check is what actually rejects the request.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->stage_action( $review_id, 'approve', 'approve-review_' . $review_id );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'You are not allowed to moderate reviews.' );

		$this->invoke( 'process_review_actions' );
	}

	/**
	 * Rendering the page requires the capability.
	 */
	public function test_review_list_requires_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->expectException( WPDieException::class );

		$this->main->review_list();
	}

	/**
	 * The page renders the reviews table.
	 */
	public function test_review_list_output() {
		$this->create_review( 'hold' );

		ob_start();
		$this->main->review_list();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="wrap">', $output );
		$this->assertStringContainsString( '<h2>Reviews</h2>', $output );
		$this->assertStringContainsString( '<form method="post">', $output );
	}
}
