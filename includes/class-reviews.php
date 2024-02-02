<?php
/**
 * Reviews class for rendering the table.
 *
 * @package Tutor_LMS_Reviews\Includes
 * @since 1.0.0
 */

namespace TutorLmsReviews\Includes;

/**
 * Reviews class to render the table of the reviews.
 *
 * @since 1.0.0
 *
 * @see WP_List_Table
 * @link https://developer.wordpress.org/reference/classes/WP_List_Table
 */
class Reviews extends \WP_List_Table {

	/**
	 * Current selected review status.
	 *
	 * @since 1.0.0
	 * @var string $current_review_status_view Current review status.
	 */
	protected $current_review_status_view;

	/**
	 * Inherit data from parent class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'review',
				'plural'   => 'reviews',
			)
		);
	}

	/**
	 * Prepares the list of reviews for displaying.
	 *
	 * @since 1.0.0
	 *
	 * @see sanitize_text_field
	 * @link https://developer.wordpress.org/reference/functions/sanitize_text_field
	 *
	 * @see wp_unslash
	 * @link https://developer.wordpress.org/reference/functions/wp_unslash
	 */
	public function prepare_items() {
		$this->current_review_status_view = isset( $_GET['review_status'] ) ? sanitize_text_field( wp_unslash( $_GET['review_status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$reviews = $this->get_reviews();

		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = 10;
		$current_page = $this->get_pagenum();
		$total_items  = count( $reviews );

		$reviews = array_slice( $reviews, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		$this->items = $reviews;
	}

	/**
	 * Define the list of column names.
	 *
	 * @since 1.0.0
	 *
	 * @see __
	 * @link https://developer.wordpress.org/reference/functions/__
	 */
	public function get_columns() {
		return array(
			'cb'     => '<input type="checkbox" />',
			'author' => __( 'Author', 'tutor-lms-reviews' ),
			'review' => __( 'Review', 'tutor-lms-reviews' ),
			'rating' => __( 'Rating', 'tutor-lms-reviews' ),
			'course' => __( 'Course', 'tutor-lms-reviews' ),
			'date'   => __( 'Date', 'tutor-lms-reviews' ),
		);
	}

	/**
	 * Define the sortable columns of the table.
	 *
	 * @since 1.0.0
	 */
	protected function get_sortable_columns() {
		return array(
			'author' => array( 'author', false ),
			'rating' => array( 'rating', false ),
			'status' => array( 'status', true ),
			'date'   => array( 'date', true ), // Default sorted by this field.
		);
	}

	/**
	 * Get Tutor LMS reviews from database.
	 *
	 * @since 1.0.0
	 *
	 * @see sanitize_text_field
	 * @link https://developer.wordpress.org/reference/functions/sanitize_text_field
	 *
	 * @see wp_unslash
	 * @link https://developer.wordpress.org/reference/functions/wp_unslash
	 *
	 * @see esc_sql
	 * @link https://developer.wordpress.org/reference/functions/esc_sql
	 *
	 * @global object $wpdb WordPress database abstraction object.
	 */
	protected function get_reviews(): array {
		global $wpdb;

		$sql = "SELECT c.comment_ID as id,
				c.comment_post_ID as course_id,
				c.comment_author as author,
				c.comment_date as `date`,
				c.comment_content as review,
				c.comment_approved as `status`,
				c.user_id,
				cm.meta_value as rating,
				p.post_title as course_name
			FROM $wpdb->comments c
			JOIN $wpdb->commentmeta cm ON c.comment_ID = cm.comment_id
			JOIN $wpdb->posts p ON c.comment_post_ID = p.ID
			WHERE c.comment_type = 'tutor_course_rating' AND cm.meta_key = 'tutor_rating'";

		if ( 'all' !== $this->current_review_status_view ) {
			$sql .= $wpdb->prepare( ' AND c.comment_approved = %s', $this->current_review_status_view );
		}

		$order    = ( isset( $_GET['order'] ) ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_by = ( isset( $_GET['orderby'] ) ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! empty( $order_by ) ) {
			switch ( $order_by ) {
				case 'author':
					$sql .= ' ORDER BY c.user_id ' . esc_sql( $order );
					break;
				case 'rating':
					$sql .= ' ORDER BY rating ' . esc_sql( $order );
					break;
				default:
					$sql .= ' ORDER BY c.comment_date_gmt DESC';
					break;
			}
		} else {
			$sql .= ' ORDER BY c.comment_date_gmt DESC';
		}

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Render column values.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $item Array data of the review.
	 * @param string $column_name Name of the current column.
	 *
	 * @see admin_url
	 * @link https://developer.wordpress.org/reference/functions/admin_url
	 *
	 * @see get_edit_post_link
	 * @link https://developer.wordpress.org/reference/functions/get_edit_post_link
	 *
	 * @see get_option
	 * @link https://developer.wordpress.org/reference/functions/get_option
	 *
	 * @see date_i18n
	 * @link https://developer.wordpress.org/reference/functions/date_i18n
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'author':
				$user_profile_url = admin_url( 'user-edit.php?user_id=' . $item['user_id'] . '&wp_http_referer=admin.php?page=' . Main::SUBMENU_SLUG );

				return sprintf( '%1$s%2$s%3$s', "<a href='$user_profile_url'>", $item['author'], '</a>' );
			case 'review':
				return $item['review'];
			case 'rating':
				$stars  = str_repeat( '&#9733;', $item['rating'] );
				$stars .= str_repeat( '&#9734;', 5 - $item['rating'] );

				return $stars;
			case 'course':
				$course_url = get_edit_post_link( $item['course_id'] );

				return sprintf( '%1$s%2$s%3$s', "<a href='$course_url'>", $item['course_name'], '</a>' );
			case 'date':
				$date_time_format = implode( ', ', array( get_option( 'date_format' ), get_option( 'time_format' ) ) );

				return date_i18n( $date_time_format, strtotime( $item['date'] ) );
			case 'id':
			default:
				break;

		}
	}

	/**
	 * Render checkbox for first column.
	 *
	 * @param array $item Array data of the review.
	 *
	 * @since 1.0.0
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="element[]" value="%s" />', $item['id'] );
	}

	/**
	 * Render the filters for reviews.
	 *
	 * @since 1.0.0
	 *
	 * @see admin_url
	 * @link https://developer.wordpress.org/reference/functions/admin_url
	 *
	 * @see _nx_noop
	 * @link https://developer.wordpress.org/reference/functions/_nx_noop
	 *
	 * @see add_query_arg
	 * @link https://developer.wordpress.org/reference/functions/add_query_arg
	 *
	 * @see esc_url
	 * @link https://developer.wordpress.org/reference/functions/esc_url
	 *
	 * @see translate_nooped_plural
	 * @link https://developer.wordpress.org/reference/functions/translate_nooped_plural
	 *
	 * @see number_format_i18n
	 * @link https://developer.wordpress.org/reference/functions/number_format_i18n
	 *
	 * @global object $wpdb WordPress database abstraction object.
	 */
	public function get_views() {
		global $wpdb;

		$link = admin_url( 'admin.php?page=' . Main::SUBMENU_SLUG );

		$status_links = array();
		$review_count = $wpdb->get_results(
			"SELECT comment_approved as `status`, COUNT(*) AS total
			FROM $wpdb->comments
			WHERE comment_type='tutor_course_rating'
			GROUP BY comment_approved",
			OBJECT
		);

		$num_reviews      = new \stdClass();
		$num_reviews->all = 0;
		foreach ( $review_count as $row ) {
			$num_reviews->{$row->status} = intval( $row->total );
			$num_reviews->all           += $row->total;
		}

		$links = array(
			// translators: %s: Number of reviews.
			'all'      => _nx_noop(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				'reviews',
				'tutor-lms-reviews'
			),
			// translators: %s: Number of reviews.
			'hold'     => _nx_noop(
				'Pending <span class="count">(%s)</span>',
				'Pending <span class="count">(%s)</span>',
				'reviews',
				'tutor-lms-reviews'
			),
			// translators: %s: Number of reviews.
			'approved' => _nx_noop(
				'Approved <span class="count">(%s)</span>',
				'Approved <span class="count">(%s)</span>',
				'reviews',
				'tutor-lms-reviews'
			),

			// translators: %s: Number of reviews.
			'spam'     => _nx_noop(
				'Spam <span class="count">(%s)</span>',
				'Spam <span class="count">(%s)</span>',
				'reviews',
				'tutor-lms-reviews'
			),

			// translators: %s: Number of reviews.
			'trash'    => _nx_noop(
				'Trash <span class="count">(%s)</span>',
				'Trash <span class="count">(%s)</span>',
				'reviews',
				'tutor-lms-reviews'
			),
		);

		foreach ( $links as $status => $label ) {
			if ( ! isset( $num_reviews->$status ) ) {
				$num_reviews->$status = 0;
			}

			$link = add_query_arg( 'review_status', $status, $link );

			$status_links[ $status ] = array(
				'url'     => esc_url( $link ),
				'label'   => sprintf(
					translate_nooped_plural( $label, $num_reviews->$status ),
					sprintf(
						'<span class="%s-count">%s</span>',
						( 'hold' === $status ) ? 'hold' : $status,
						number_format_i18n( $num_reviews->$status )
					)
				),
				'current' => $status === $this->current_review_status_view,
			);
		}

		return $this->get_views_links( $status_links );
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @since 1.0.0
	 */
	protected function get_primary_column_name() {
		return 'review';
	}

	/**
	 * Render actions for every review row.
	 *
	 * @param array  $item Array data of the review.
	 * @param string $column_name Name of the current column.
	 * @param string $primary Name of the primary column.
	 *
	 * @since 1.0.0
	 *
	 * @see esc_html
	 * @link https://developer.wordpress.org/reference/functions/esc_html
	 *
	 * @see wp_create_nonce
	 * @link https://developer.wordpress.org/reference/functions/wp_create_nonce
	 *
	 * @see admin_url
	 * @link https://developer.wordpress.org/reference/functions/admin_url
	 *
	 * @see esc_url
	 * @link https://developer.wordpress.org/reference/functions/esc_url
	 *
	 * @see esc_attr__
	 * @link https://developer.wordpress.org/reference/functions/esc_attr__
	 *
	 * @see __
	 * @link https://developer.wordpress.org/reference/functions/__
	 *
	 * @see _x
	 * @link https://developer.wordpress.org/reference/functions/_x
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$approve_nonce = esc_html( '_wpnonce=' . wp_create_nonce( 'approve-review_' . $item['id'] ) );
		$del_nonce     = esc_html( '_wpnonce=' . wp_create_nonce( 'delete-review_' . $item['id'] ) );

		$url = admin_url( 'admin.php?page=' . Main::SUBMENU_SLUG . '&r=' . $item['id'] );

		$approve_url   = esc_url( $url . "&action=approve&$approve_nonce" );
		$unapprove_url = esc_url( $url . "&action=unapprove&$approve_nonce" );
		$spam_url      = esc_url( $url . "&action=spam&$del_nonce" );
		$unspam_url    = esc_url( $url . "&action=unspam&$del_nonce" );
		$trash_url     = esc_url( $url . "&action=trash&$del_nonce" );
		$untrash_url   = esc_url( $url . "&action=untrash&$del_nonce" );
		$delete_url    = esc_url( $url . "&action=delete&$del_nonce" );

		// Preorder it: Approve | Spam | Trash.
		$actions = array(
			'approve'   => '',
			'unapprove' => '',
			'spam'      => '',
			'unspam'    => '',
			'trash'     => '',
			'untrash'   => '',
			'delete'    => '',
		);

		if ( 'approved' === $item['status'] ) {
			$actions['unapprove'] = sprintf(
				'<a href="%s" class="vim-u vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$unapprove_url,
				esc_attr__( 'Unapprove this review', 'tutor-lms-reviews' ),
				__( 'Unapprove', 'tutor-lms-reviews' )
			);
		} elseif ( 'hold' === $item['status'] ) {
			$actions['approve'] = sprintf(
				'<a href="%s" class="vim-a vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$approve_url,
				esc_attr__( 'Approve this review', 'tutor-lms-reviews' ),
				__( 'Approve', 'tutor-lms-reviews' )
			);
		}

		if ( 'spam' !== $item['status'] ) {
			$actions['spam'] = sprintf(
				'<a href="%s" class="vim-s vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$spam_url,
				esc_attr__( 'Mark this review as spam', 'tutor-lms-reviews' ),
				/* translators: "Mark as spam" link. */
				_x( 'Spam', 'verb', 'tutor-lms-reviews' )
			);
		} elseif ( 'spam' === $item['status'] ) {
			$actions['unspam'] = sprintf(
				'<a href="%s" class="vim-z vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$unspam_url,
				esc_attr__( 'Restore this review from the spam', 'tutor-lms-reviews' ),
				_x( 'Not Spam', 'review', 'tutor-lms-reviews' )
			);
		}

		if ( 'trash' === $item['status'] ) {
			$actions['untrash'] = sprintf(
				'<a href="%s" class="vim-z vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$untrash_url,
				esc_attr__( 'Restore this review from the Trash', 'tutor-lms-reviews' ),
				__( 'Restore', 'tutor-lms-reviews' )
			);
		}

		if ( 'spam' === $item['status'] || 'trash' === $item['status'] || ! EMPTY_TRASH_DAYS ) {
			$actions['delete'] = sprintf(
				'<a href="%s" class="delete vim-d vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$delete_url,
				esc_attr__( 'Delete this review permanently', 'tutor-lms-reviews' ),
				__( 'Delete Permanently', 'tutor-lms-reviews' )
			);
		} else {
			$actions['trash'] = sprintf(
				'<a href="%s" class="delete vim-d vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$trash_url,
				esc_attr__( 'Move this review to the Trash', 'tutor-lms-reviews' ),
				_x( 'Trash', 'verb', 'tutor-lms-reviews' )
			);
		}

		$i           = 0;
		$count_links = count(
			array_filter(
				$actions,
				function ( $value ) {
					return '' !== $value;
				}
			)
		);

		$output = '<div class="row-actions">';
		foreach ( $actions as $action => $link ) {
			if ( ! empty( $link ) ) {
				if ( $i < $count_links - 1 ) {
					$output .= "<span class='$action'>$link | </span>";
				} else {
					$output .= "<span class='$action'>$link</span>";
				}
				$i++;
			}
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @param array $item Array data of the review.
	 *
	 * @since 1.0.0
	 *
	 * @see esc_attr
	 * @link https://developer.wordpress.org/reference/functions/esc_attr
	 */
	public function single_row( $item ) {
		$unnapproved_class = 'hold' === $item['status'] ? ' unapproved' : '';
		?>
		<tr class="review<?php echo esc_attr( $unnapproved_class ); ?>">
		<?php $this->single_row_columns( $item ); ?>
		</tr>
		<?php
	}

	/**
	 * Return an associative array containing the bulk actions.
	 *
	 * @return array
	 * @since 1.0.0
	 *
	 * @see __
	 * @link https://developer.wordpress.org/reference/functions/__
	 *
	 * @see _x
	 * @link https://developer.wordpress.org/reference/functions/_x
	 */
	public function get_bulk_actions(): array {
		$review_status = $this->current_review_status_view;

		$actions = array();

		if ( in_array( $review_status, array( 'all', 'approved' ), true ) ) {
			$actions['unapprove'] = __( 'Unapprove', 'tutor-lms-reviews' );
		}

		if ( in_array( $review_status, array( 'all', 'hold' ), true ) ) {
			$actions['approve'] = __( 'Approve', 'tutor-lms-reviews' );
		}

		if ( in_array( $review_status, array( 'all', 'hold', 'approved', 'trash' ), true ) ) {
			$actions['spam'] = _x( 'Mark as spam', 'review', 'tutor-lms-reviews' );
		}

		if ( 'trash' === $review_status ) {
			$actions['untrash'] = __( 'Restore', 'tutor-lms-reviews' );
		} elseif ( 'spam' === $review_status ) {
			$actions['unspam'] = _x( 'Not spam', 'review', 'tutor-lms-reviews' );
		}

		if ( in_array( $review_status, array( 'trash', 'spam' ), true ) || ! EMPTY_TRASH_DAYS ) {
			$actions['delete'] = __( 'Delete permanently', 'tutor-lms-reviews' );
		} else {
			$actions['trash'] = __( 'Move to Trash', 'tutor-lms-reviews' );
		}

		return $actions;
	}
}
