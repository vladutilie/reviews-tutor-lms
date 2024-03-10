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
	const SUBMENU_SLUG = 'tutor-lms-reviews';

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
	 *
	 * @see is_plugin_inactive
	 * @link https://developer.wordpress.org/reference/functions/is_plugin_inactive
	 *
	 * @see add_action
	 * @link https://developer.wordpress.org/reference/functions/add_action
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
	 *
	 * @see esc_html__
	 * @link https://developer.wordpress.org/reference/functions/esc_html__
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
	 *
	 * @see load_plugin_textdomain
	 * @link https://developer.wordpress.org/reference/functions/load_plugin_textdomain
	 *
	 * @see plugin_basename
	 * @link https://developer.wordpress.org/reference/functions/plugin_basename
	 */
	public function load_text_domain(): void {
		load_plugin_textdomain( 'reviews-tutor-lms', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages' );
	}

	/**
	 * Add "Reviews" submenu in the Tutor LMS dashboard navigation
	 *
	 * @since 1.0.0
	 *
	 * @see esc_attr
	 * @link https://developer.wordpress.org/reference/functions/esc_attr
	 *
	 * @see esc_html
	 * @link https://developer.wordpress.org/reference/functions/esc_html
	 *
	 * @see add_submenu_page
	 * @link https://developer.wordpress.org/reference/functions/add_submenu_page
	 *
	 * @see __
	 * @link https://developer.wordpress.org/reference/functions/__
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
	 *
	 * @see esc_html__
	 * @link https://developer.wordpress.org/reference/functions/esc_html__
	 *
	 * @see __
	 * @link https://developer.wordpress.org/reference/functions/__
	 */
	public function review_list() {
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
			<?php
			// translators: %1$s: h2 opening tag, %2$s: h2 closing tag.
			printf( esc_html__( '%1$sReviews%2$s', 'reviews-tutor-lms' ), '<h2>', '</h2>' );
			$table->views();
			?>
			<form method="post">
				<?php
				if ( $table->has_items() ) {
					$table->search_box( __( 'Search review', 'reviews-tutor-lms' ), 'tutor-lms-reviews' );
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
	 * @see wp_verify_nonce
	 * @link https://developer.wordpress.org/reference/functions/wp_verify_nonce
	 *
	 * @see sanitize_text_field
	 * @link https://developer.wordpress.org/reference/functions/sanitize_text_field
	 *
	 * @see wp_unslash
	 * @link https://developer.wordpress.org/reference/functions/wp_unslash
	 *
	 * @see wp_safe_redirect
	 * @link https://developer.wordpress.org/reference/functions/wp_safe_redirect
	 *
	 * @see admin_url
	 * @link https://developer.wordpress.org/reference/functions/admin_url
	 *
	 * @global object $wpdb WordPress database abstraction object.
	 */
	protected function process_review_actions() {
		global $wpdb;

		if ( isset( $_GET['_wpnonce'] ) && isset( $_GET['r'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'approve-review_' . sanitize_text_field( wp_unslash( $_GET['r'] ) ) ) ) {
			$action    = ( isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '' );
			$review_id = ( isset( $_GET['r'] ) ? sanitize_text_field( wp_unslash( $_GET['r'] ) ) : '' );

			$data = array();
			if ( 'approve' === $action ) {
				$data = array( 'comment_approved' => 'approved' );
			} elseif ( 'unapprove' === $action ) {
				$data = array( 'comment_approved' => 'hold' );
			} else {
				return;
			}

			$update = $wpdb->update( $wpdb->comments, $data, array( 'comment_ID' => $review_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SUBMENU_SLUG ) );
		} elseif ( isset( $_GET['_wpnonce'] ) && isset( $_GET['r'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete-review_' . sanitize_text_field( wp_unslash( $_GET['r'] ) ) ) ) {
			$action    = ( isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '' );
			$review_id = ( isset( $_GET['r'] ) ? sanitize_text_field( wp_unslash( $_GET['r'] ) ) : '' );

			$data = array();
			if ( 'spam' === $action ) {
				$data = array( 'comment_approved' => 'spam' );
			} elseif ( 'unspam' === $action || 'untrash' === $action ) {
				$data = array( 'comment_approved' => 'hold' );
			} elseif ( 'trash' === $action ) {
				$data = array( 'comment_approved' => 'trash' );
			} elseif ( 'delete' === $action ) {
				$delete = $wpdb->delete( $wpdb->comments, array( 'comment_ID' => $review_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

				return wp_safe_redirect( admin_url( 'admin.php?page=' . self::SUBMENU_SLUG ) );
			} else {
				return;
			}

			$update = $wpdb->update( $wpdb->comments, $data, array( 'comment_ID' => $review_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SUBMENU_SLUG ) );
		}
	}
}
