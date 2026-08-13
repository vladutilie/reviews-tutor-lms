<?php
/**
 * Tests for the Reviews list table.
 *
 * @package Reviews_Tutor_Lms
 */

use ReviewsTutorLms\Includes\Reviews;

/**
 * Tests for ReviewsTutorLms\Includes\Reviews.
 */
class Test_Reviews extends WP_UnitTestCase {

	/**
	 * Instance under test.
	 *
	 * @var Reviews
	 */
	protected $reviews_table;

	/**
	 * Set up an administrator who is allowed to moderate reviews.
	 */
	public function set_up() {
		parent::set_up();

		set_current_screen( 'toplevel_page_tutor' );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$user = new WP_User( $user_id );
		$user->add_cap( Reviews::CAPABILITY );

		wp_set_current_user( $user_id );

		$this->reviews_table = new Reviews();
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
	 * Create a review attached to a course.
	 *
	 * @param string $status Value for comment_approved.
	 * @param array  $overrides Extra comment fields.
	 * @param int    $rating Star rating stored in comment meta.
	 * @return int Comment ID.
	 */
	protected function create_review( string $status = 'hold', array $overrides = array(), int $rating = 5 ): int {
		$course_id = self::factory()->post->create(
			array(
				'post_type'  => 'courses',
				'post_title' => 'Test Course',
			)
		);

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

		add_comment_meta( $review_id, 'tutor_rating', $rating );

		return $review_id;
	}

	/**
	 * Stage a signed bulk action request.
	 *
	 * WP_List_Table::current_action() reads $_REQUEST, which PHP only populates
	 * for real requests, so it has to be filled in explicitly here.
	 *
	 * @param string $action     Bulk action name.
	 * @param array  $review_ids Selected review IDs.
	 */
	protected function stage_bulk_action( string $action, array $review_ids ): void {
		$_POST['element']  = $review_ids;
		$_POST['action']   = $action;
		$_POST['_wpnonce'] = wp_create_nonce( 'bulk-reviews' );

		$_REQUEST = $_POST;
	}

	/**
	 * Invoke a protected method on the instance under test.
	 *
	 * @param string $name Method name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed Return value of the method.
	 */
	protected function invoke( string $name, ...$args ) {
		$method = new ReflectionMethod( Reviews::class, $name );
		$method->setAccessible( true );

		return $method->invokeArgs( $this->reviews_table, $args );
	}

	/**
	 * The table extends WP_List_Table.
	 */
	public function test_reviews_class_instantiation() {
		$this->assertInstanceOf( Reviews::class, $this->reviews_table );
		$this->assertInstanceOf( WP_List_Table::class, $this->reviews_table );
	}

	/**
	 * The expected columns are declared.
	 */
	public function test_get_columns() {
		$columns = $this->reviews_table->get_columns();

		$this->assertIsArray( $columns );

		foreach ( array( 'cb', 'author', 'review', 'rating', 'course', 'date' ) as $column ) {
			$this->assertArrayHasKey( $column, $columns );
		}
	}

	/**
	 * The "all" view offers both approve and unapprove.
	 */
	public function test_get_bulk_actions() {
		$_GET['review_status'] = 'all';

		$actions = $this->reviews_table->get_bulk_actions();

		$this->assertIsArray( $actions );
		$this->assertArrayHasKey( 'approve', $actions );
		$this->assertArrayHasKey( 'unapprove', $actions );
	}

	/**
	 * The spam view offers unspam and permanent delete, but not approve.
	 */
	public function test_get_bulk_actions_spam_status() {
		$_GET['review_status'] = 'spam';

		$actions = $this->reviews_table->get_bulk_actions();

		$this->assertArrayHasKey( 'unspam', $actions );
		$this->assertArrayHasKey( 'delete', $actions );
		$this->assertArrayNotHasKey( 'approve', $actions );
	}

	/**
	 * An unrecognised review_status falls back to the "all" view.
	 */
	public function test_unknown_review_status_falls_back_to_all() {
		$_GET['review_status'] = "spam' OR 1=1 -- ";

		$this->reviews_table->prepare_items();

		$views = $this->reviews_table->get_views();

		$this->assertTrue( str_contains( $views['all'], 'current' ) );
	}

	/**
	 * Filtering by status only returns reviews with that status.
	 */
	public function test_prepare_items_filters_by_status() {
		$approved = $this->create_review( 'approved' );
		$this->create_review( 'spam' );

		$_GET['review_status'] = 'approved';

		$this->reviews_table->prepare_items();

		$ids = wp_list_pluck( $this->reviews_table->items, 'id' );

		$this->assertEquals( array( (string) $approved ), $ids );
	}

	/**
	 * prepare_items populates the table.
	 */
	public function test_prepare_items() {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->create_review( 'approved' );
		}

		$this->reviews_table->prepare_items();

		$this->assertCount( 3, $this->reviews_table->items );
	}

	/**
	 * An injection attempt in orderby cannot reach the query.
	 */
	public function test_orderby_is_restricted_to_known_columns() {
		$this->create_review( 'approved' );

		$_GET['orderby'] = 'rating; DROP TABLE wp_comments';
		$_GET['order']   = 'ASC; DROP TABLE wp_comments';

		$this->reviews_table->prepare_items();

		$this->assertCount( 1, $this->reviews_table->items );
	}

	/**
	 * Sorting by rating ascending orders the rows by their star value.
	 */
	public function test_orderby_rating_ascending() {
		$this->create_review( 'approved', array(), 5 );
		$this->create_review( 'approved', array(), 1 );
		$this->create_review( 'approved', array(), 3 );

		$_GET['orderby'] = 'rating';
		$_GET['order']   = 'asc';

		$this->reviews_table->prepare_items();

		$ratings = array_map( 'intval', wp_list_pluck( $this->reviews_table->items, 'rating' ) );

		$this->assertSame( array( 1, 3, 5 ), $ratings );
	}

	/**
	 * The author column links to the user profile and escapes the name.
	 */
	public function test_column_default_author() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'Test Author' ) );

		$item = array(
			'author'  => '<script>alert(1)</script>',
			'user_id' => $user_id,
		);

		$output = $this->reviews_table->column_default( $item, 'author' );

		$this->assertStringContainsString( 'user-edit.php', $output );
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
	}

	/**
	 * The review column strips markup that is not allowed in post content.
	 */
	public function test_column_default_review_is_escaped() {
		$item = array( 'review' => 'Nice course <script>alert(1)</script><img src=x onerror=alert(1)>' );

		$output = $this->reviews_table->column_default( $item, 'review' );

		$this->assertStringContainsString( 'Nice course', $output );
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringNotContainsString( 'onerror', $output );
	}

	/**
	 * The course column escapes the course title.
	 */
	public function test_column_default_course_is_escaped() {
		$course_id = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => '<script>alert(1)</script>',
			)
		);

		$item = array(
			'course_id'   => $course_id,
			'course_name' => '<script>alert(1)</script>',
		);

		$output = $this->reviews_table->column_default( $item, 'course' );

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
	}

	/**
	 * The rating column renders filled and empty stars.
	 */
	public function test_column_default_rating() {
		$output = $this->reviews_table->column_default( array( 'rating' => '4' ), 'rating' );

		$this->assertEquals( 4, substr_count( $output, '&#9733;' ) );
		$this->assertEquals( 1, substr_count( $output, '&#9734;' ) );
	}

	/**
	 * An out-of-range rating is clamped instead of raising an error.
	 */
	public function test_column_default_rating_out_of_range() {
		$output = $this->reviews_table->column_default( array( 'rating' => '9' ), 'rating' );

		$this->assertEquals( 5, substr_count( $output, '&#9733;' ) );
		$this->assertEquals( 0, substr_count( $output, '&#9734;' ) );
	}

	/**
	 * The checkbox column renders the review ID as an integer.
	 */
	public function test_column_cb() {
		$output = $this->reviews_table->column_cb( array( 'id' => '123" onfocus="alert(1)' ) );

		$this->assertStringContainsString( '<input type="checkbox"', $output );
		$this->assertStringContainsString( 'value="123"', $output );
		$this->assertStringNotContainsString( 'onfocus', $output );
	}

	/**
	 * A pending review gets the unapproved row class.
	 */
	public function test_single_row_unapproved_class() {
		$item = array(
			'id'          => 1,
			'course_id'   => 1,
			'status'      => 'hold',
			'author'      => 'Test',
			'review'      => 'Test review',
			'rating'      => 5,
			'course_name' => 'Test Course',
			'date'        => gmdate( 'Y-m-d H:i:s' ),
		);

		ob_start();
		$this->reviews_table->single_row( $item );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'class="review unapproved"', $output );
	}

	/**
	 * The status views are rendered with per-status counts.
	 */
	public function test_get_views() {
		$this->create_review( 'approved' );
		$this->create_review( 'hold' );

		$views = $this->reviews_table->get_views();

		$this->assertIsArray( $views );
		$this->assertArrayHasKey( 'all', $views );
		$this->assertArrayHasKey( 'hold', $views );

		// Each view links to its own status, rather than accumulating them.
		$this->assertStringContainsString( 'review_status=hold', $views['hold'] );
		$this->assertStringNotContainsString( 'review_status=all', $views['hold'] );
	}

	/**
	 * A bulk approve updates every selected review.
	 */
	public function test_process_bulk_action_approve_multiple() {
		$review_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$review_ids[] = $this->create_review( 'hold' );
		}

		$this->stage_bulk_action( 'approve', $review_ids );

		$this->reviews_table->process_bulk_action();

		foreach ( $review_ids as $review_id ) {
			$this->assertEquals( 'approved', get_comment( $review_id )->comment_approved );
		}
	}

	/**
	 * A bulk delete removes every selected trashed review.
	 */
	public function test_process_bulk_action_delete_multiple() {
		$review_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$review_ids[] = $this->create_review( 'trash' );
		}

		$this->stage_bulk_action( 'delete', $review_ids );

		$this->reviews_table->process_bulk_action();

		foreach ( $review_ids as $review_id ) {
			$this->assertNull( get_comment( $review_id ) );
		}
	}

	/**
	 * A bulk delete never removes reviews that are neither spam nor trashed.
	 */
	public function test_process_bulk_action_delete_spares_live_reviews() {
		$approved = $this->create_review( 'approved' );

		$this->stage_bulk_action( 'delete', array( $approved ) );

		$this->reviews_table->process_bulk_action();

		$this->assertNotNull( get_comment( $approved ) );
	}

	/**
	 * An empty selection is a no-op rather than an invalid `IN ()` query.
	 */
	public function test_process_bulk_action_with_empty_selection() {
		$review_id = $this->create_review( 'hold' );

		$this->stage_bulk_action( 'approve', array() );

		$this->reviews_table->process_bulk_action();

		$this->assertEquals( 'hold', get_comment( $review_id )->comment_approved );
	}

	/**
	 * An unknown bulk action is ignored instead of writing an empty status.
	 */
	public function test_process_bulk_action_ignores_unknown_action() {
		$review_id = $this->create_review( 'hold' );

		$this->stage_bulk_action( 'explode', array( $review_id ) );

		$this->reviews_table->process_bulk_action();

		$this->assertEquals( 'hold', get_comment( $review_id )->comment_approved );
	}

	/**
	 * A bulk action from a user without the capability is refused.
	 */
	public function test_process_bulk_action_requires_capability() {
		$review_id = $this->create_review( 'hold' );

		// Sign the request as the subscriber, so the nonce is valid and the
		// capability check is what actually rejects the request.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->stage_bulk_action( 'approve', array( $review_id ) );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'You are not allowed to moderate reviews.' );

		$this->reviews_table->process_bulk_action();
	}

	/**
	 * Row actions for an approved review offer unapprove, not approve.
	 */
	public function test_handle_row_actions_approved() {
		$output = $this->invoke(
			'handle_row_actions',
			array(
				'id'     => 123,
				'status' => 'approved',
			),
			'review',
			'review'
		);

		$this->assertStringContainsString( 'Unapprove', $output );
		$this->assertStringNotContainsString( '>Approve<', $output );
	}

	/**
	 * Row actions for a pending review offer approve, not unapprove.
	 */
	public function test_handle_row_actions_pending() {
		$output = $this->invoke(
			'handle_row_actions',
			array(
				'id'     => 123,
				'status' => 'hold',
			),
			'review',
			'review'
		);

		$this->assertStringContainsString( 'Approve', $output );
		$this->assertStringNotContainsString( 'Unapprove', $output );
	}

	/**
	 * Row actions are only rendered on the primary column.
	 */
	public function test_handle_row_actions_only_on_primary_column() {
		$output = $this->invoke(
			'handle_row_actions',
			array(
				'id'     => 123,
				'status' => 'hold',
			),
			'author',
			'review'
		);

		$this->assertSame( '', $output );
	}

	/**
	 * Each row action carries a nonce bound to that review.
	 */
	public function test_handle_row_actions_are_nonced() {
		$output = $this->invoke(
			'handle_row_actions',
			array(
				'id'     => 123,
				'status' => 'hold',
			),
			'review',
			'review'
		);

		$this->assertStringContainsString( wp_create_nonce( 'approve-review_123' ), $output );
		$this->assertStringContainsString( wp_create_nonce( 'delete-review_123' ), $output );
	}
}
