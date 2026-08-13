<?php
/**
 * Main class of the plugin that handles hooks, internationalization, connects all the plugin features, script and styles enqueuing.
 *
 * @package Reviews_Tutor_LMS\Includes
 * @since 1.0.0
 */

namespace ReviewsTutorLms\Includes;

/**
 * Main class of the plugin that handles hooks, internationalization, connects all the plugin features, script and styles enqueuing.
 *
 * @since 1.0.0
 */
class Main {

	/**
	 * Reviews submenu admin page.
	 */
	const SUBMENU_SLUG = 'reviews-tutor-lms';

	/**
	 * The plugin main root.
	 *
	 * @since 1.0.0
	 * @var string $plugin_root Plugin absolute path.
	 */
	protected string $plugin_root;

	/**
	 * Initiate the main settings of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_root The absolute path of the plugin.
	 */
	public function __construct( string $plugin_root ) {
		$this->plugin_root = $plugin_root;

		$this->load_dependencies();
		$this->init();
	}

	/**
	 * Include plugin dependencies.
	 *
	 * @since 1.0.0
	 */
	protected function load_dependencies(): void {
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		require_once $this->plugin_root . '/includes/class-reviews.php';
	}

	/**
	 * Define hooks.
	 *
	 * @since 1.0.0
	 */
	protected function init(): void {
		/**
		 * Detect plugin. For frontend only.
		 */
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_plugin_inactive( 'tutor/tutor.php' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_required_tutor' ) );
		} else {
			add_action( 'tutor_after_courses_menu', array( $this, 'add_reviews_submenu' ) );
		}
		add_action( 'plugins_loaded', array( $this, 'load_text_domain' ) );
	}

	/**
	 * Admin notice if Tutor LMS not installed.
	 *
	 * @since 1.0.0
	 */
	public function notice_required_tutor() {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					// translators: %1$s: code opening tag, %2$s: code closing tag.
					esc_html__( 'Please enable the %1$sTutor LMS%2$s plugin for the %1$sReviews for Tutor LMS%2$s plugin to work.', 'reviews-tutor-lms' ),
					'<code>',
					'</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Set up internationalization for the plugin.
	 *
	 * @since 1.0.0
	 */
	public function load_text_domain(): void {
		load_plugin_textdomain( 'reviews-tutor-lms', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages' );
	}

	/**
	 * Add "Reviews" submenu in the Tutor LMS dashboard navigation
	 *
	 * @since 1.0.0
	 *
	 * @global object $wpdb WordPress database abstraction object.
	 */
	public function add_reviews_submenu() {
		global $wpdb;

		$reviews_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT COUNT(*)
			FROM $wpdb->comments
			WHERE comment_type='tutor_course_rating' AND comment_approved='hold'"
		);

		$bubble = ' <span class="awaiting-mod count-' . esc_attr( $reviews_count ) . '"><span class="pending-count">' . esc_html( $reviews_count ) . '</span></span>';

		add_submenu_page( 'tutor', __( 'Reviews', 'reviews-tutor-lms' ), __( 'Reviews', 'reviews-tutor-lms' ) . $bubble, 'manage_tutor_instructor', self::SUBMENU_SLUG, array( $this, 'review_list' ) );
	}

	/**
	 * Table of the reviews.
	 *
	 * @since 1.0.0
	 */
	public function review_list() {
		if ( ! current_user_can( Reviews::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You are not allowed to moderate reviews.', 'reviews-tutor-lms' ),
				403
			);
		}

		$this->process_review_actions();

		$table = new Reviews();
		$table->prepare_items();

		?>
		<style>
			tr.review.unapproved {
				background-color: #fcf9e8;
			}
			tr.review.unapproved th.check-column {
				border-left: 4px solid #d63638;
			}
		</style>
		<div class="wrap">
			<h2><?php esc_html_e( 'Reviews', 'reviews-tutor-lms' ); ?></h2>
			<?php $table->views(); ?>
			<form method="post">
				<?php
				if ( $table->has_items() ) {
					$table->search_box( __( 'Search review', 'reviews-tutor-lms' ), 'reviews-tutor-lms' );
				}
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Process actions of the reviews.
	 *
	 * @since 1.0.0
	 *
	 * @global object $wpdb WordPress database abstraction object.
	 */
	protected function process_review_actions() {
		global $wpdb;

		if ( ! isset( $_GET['_wpnonce'], $_GET['r'], $_GET['action'] ) ) {
			return;
		}

		$nonce     = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		$review_id = absint( wp_unslash( $_GET['r'] ) );
		$action    = sanitize_key( wp_unslash( $_GET['action'] ) );

		if ( ! $review_id ) {
			return;
		}

		/*
		 * Approving is gated behind its own nonce action, every destructive action behind
		 * another. Both are also gated behind the same capability as the page itself, so a
		 * leaked nonce alone is not enough to moderate reviews.
		 */
		$statuses = array(
			'approve'   => 'approved',
			'unapprove' => 'hold',
		);

		if ( isset( $statuses[ $action ] ) ) {
			$nonce_action = 'approve-review_' . $review_id;
		} else {
			$nonce_action = 'delete-review_' . $review_id;

			$statuses = array(
				'spam'    => 'spam',
				'unspam'  => 'hold',
				'untrash' => 'hold',
				'trash'   => 'trash',
			);

			if ( 'delete' !== $action && ! isset( $statuses[ $action ] ) ) {
				return;
			}
		}

		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return;
		}

		if ( ! current_user_can( Reviews::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You are not allowed to moderate reviews.', 'reviews-tutor-lms' ),
				403
			);
		}

		if ( 'delete' === $action ) {
			$wpdb->delete( $wpdb->comments, array( 'comment_ID' => $review_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->comments,
				array( 'comment_approved' => $statuses[ $action ] ),
				array( 'comment_ID' => $review_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		clean_comment_cache( $review_id );

		// wp_safe_redirect() returns false when a filter cancels it, which is how the tests
		// keep the request alive; a real redirect must always stop execution.
		if ( wp_safe_redirect( add_query_arg( 'page', self::SUBMENU_SLUG, admin_url( 'admin.php' ) ) ) ) {
			exit;
		}
	}
}
