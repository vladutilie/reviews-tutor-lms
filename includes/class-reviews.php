<?php
/**
 * Reviews class for rendering the table.
 *
 * @package Reviews_Tutor_LMS\Includes
 * @since 1.0.0
 */

namespace ReviewsTutorLms\Includes;

/**
 * Reviews class to render the table of the reviews.
 *
 * @since 1.0.0
 */
class Reviews extends \WP_List_Table {

	/**
	 * Capability required to view and moderate reviews.
	 *
	 * @since 1.0.3
	 */
	const CAPABILITY = 'manage_tutor_instructor';

	/**
	 * Review statuses the table knows about.
	 *
	 * Used as an allow list for anything coming from the request.
	 *
	 * @since 1.0.3
	 */
	const STATUSES = array( 'hold', 'approved', 'spam', 'trash' );

	/**
	 * Current selected review status.
	 *
	 * @since 1.0.0
	 * @var string $current_review_status_view Current review status.
	 */
	protected $current_review_status_view = 'all';

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
	 * Read the requested review status from the request, restricted to known statuses.
	 *
	 * Anything unrecognised falls back to `all` so the value can never reach the
	 * database as free-form input.
	 *
	 * @since 1.0.3
	 *
	 * @return string One of the values in self::STATUSES, or `all`.
	 */
	protected static function requested_status(): string {
		if ( ! isset( $_GET['review_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'all';
		}

		$status = sanitize_key( wp_unslash( $_GET['review_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $status, self::STATUSES, true ) ? $status : 'all';
	}

	/**
	 * Prepares the list of reviews for displaying.
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		$this->current_review_status_view = self::requested_status();
		$this->process_bulk_action();
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
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);

		$this->items = $reviews;
	}

	/**
	 * Define the list of column names.
	 *
	 * @since 1.0.0
	 */
	public function get_columns() {
		return array(
			'cb'     => '<input type="checkbox" />',
			'author' => __( 'Author', 'reviews-tutor-lms' ),
			'review' => __( 'Review', 'reviews-tutor-lms' ),
			'rating' => __( 'Rating', 'reviews-tutor-lms' ),
			'course' => __( 'Course', 'reviews-tutor-lms' ),
			'date'   => __( 'Date', 'reviews-tutor-lms' ),
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

		if ( in_array( $this->current_review_status_view, self::STATUSES, true ) ) {
			$sql .= $wpdb->prepare( ' AND c.comment_approved = %s', $this->current_review_status_view );
		}

		$order_qry = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_by  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Both parts of the ORDER BY clause come from these hard-coded maps, never from the request.
		$columns = array(
			'author' => 'c.user_id',
			'rating' => 'cm.meta_value + 0',
			'status' => 'c.comment_approved',
			'date'   => 'c.comment_date_gmt',
		);

		$column = isset( $columns[ $order_by ] ) ? $columns[ $order_by ] : 'c.comment_date_gmt';
		$order  = ( 'asc' === $order_qry ) ? 'ASC' : 'DESC';

		$sql .= " ORDER BY $column $order";

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Render column values.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $item Array data of the review.
	 * @param string $column_name Name of the current column.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'author':
				if ( ! empty( $item['user_id'] ) ) {
					$user_profile_url = add_query_arg(
						array(
							'user_id'         => absint( $item['user_id'] ),
							'wp_http_referer' => rawurlencode( 'admin.php?page=' . Main::SUBMENU_SLUG ),
						),
						admin_url( 'user-edit.php' )
					);

					return sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( $user_profile_url ),
						esc_html( $item['author'] )
					);
				}

				return esc_html( $item['author'] );
			case 'review':
				return wp_kses_post( $item['review'] );
			case 'rating':
				$rating = min( 5, max( 0, absint( $item['rating'] ) ) );

				$stars  = str_repeat( '&#9733;', $rating );
				$stars .= str_repeat( '&#9734;', 5 - $rating );

				return $stars;
			case 'course':
				$course_url = get_edit_post_link( $item['course_id'] );

				if ( ! $course_url ) {
					return esc_html( $item['course_name'] );
				}

				return sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $course_url ),
					esc_html( $item['course_name'] )
				);
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
		return sprintf( '<input type="checkbox" name="element[]" value="%d" />', absint( $item['id'] ) );
	}

	/**
	 * Render the filters for reviews.
	 *
	 * @since 1.0.0
	 *
	 * @global object $wpdb WordPress database abstraction object.
	 */
	public function get_views() {
		global $wpdb;

		$base_link = add_query_arg( 'page', Main::SUBMENU_SLUG, admin_url( 'admin.php' ) );

		$status_links = array();
		$review_count = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
				'reviews-tutor-lms'
			),
			// translators: %s: Number of reviews.
			'hold'     => _nx_noop(
				'Pending <span class="count">(%s)</span>',
				'Pending <span class="count">(%s)</span>',
				'reviews',
				'reviews-tutor-lms'
			),
			// translators: %s: Number of reviews.
			'approved' => _nx_noop(
				'Approved <span class="count">(%s)</span>',
				'Approved <span class="count">(%s)</span>',
				'reviews',
				'reviews-tutor-lms'
			),

			// translators: %s: Number of reviews.
			'spam'     => _nx_noop(
				'Spam <span class="count">(%s)</span>',
				'Spam <span class="count">(%s)</span>',
				'reviews',
				'reviews-tutor-lms'
			),

			// translators: %s: Number of reviews.
			'trash'    => _nx_noop(
				'Trash <span class="count">(%s)</span>',
				'Trash <span class="count">(%s)</span>',
				'reviews',
				'reviews-tutor-lms'
			),
		);

		foreach ( $links as $status => $label ) {
			if ( ! isset( $num_reviews->$status ) ) {
				$num_reviews->$status = 0;
			}

			$link = add_query_arg( 'review_status', $status, $base_link );

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
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$review_id = absint( $item['id'] );

		$url = add_query_arg(
			array(
				'page' => Main::SUBMENU_SLUG,
				'r'    => $review_id,
			),
			admin_url( 'admin.php' )
		);

		/**
		 * Build an action URL carrying the nonce that Main::process_review_actions() expects
		 * for that action: approving uses its own nonce action, everything else shares the
		 * destructive one.
		 *
		 * @param string $action Action name.
		 * @return string Escaped URL.
		 */
		$action_url = static function ( string $action ) use ( $url, $review_id ): string {
			$nonce_action = in_array( $action, array( 'approve', 'unapprove' ), true )
				? 'approve-review_' . $review_id
				: 'delete-review_' . $review_id;

			return esc_url( wp_nonce_url( add_query_arg( 'action', $action, $url ), $nonce_action ) );
		};

		$approve_url   = $action_url( 'approve' );
		$unapprove_url = $action_url( 'unapprove' );
		$spam_url      = $action_url( 'spam' );
		$unspam_url    = $action_url( 'unspam' );
		$trash_url     = $action_url( 'trash' );
		$untrash_url   = $action_url( 'untrash' );
		$delete_url    = $action_url( 'delete' );

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
				esc_attr__( 'Unapprove this review', 'reviews-tutor-lms' ),
				__( 'Unapprove', 'reviews-tutor-lms' )
			);
		} elseif ( 'hold' === $item['status'] ) {
			$actions['approve'] = sprintf(
				'<a href="%s" class="vim-a vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$approve_url,
				esc_attr__( 'Approve this review', 'reviews-tutor-lms' ),
				__( 'Approve', 'reviews-tutor-lms' )
			);
		}

		if ( 'spam' !== $item['status'] ) {
			$actions['spam'] = sprintf(
				'<a href="%s" class="vim-s vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$spam_url,
				esc_attr__( 'Mark this review as spam', 'reviews-tutor-lms' ),
				/* translators: "Mark as spam" link. */
				_x( 'Spam', 'verb', 'reviews-tutor-lms' )
			);
		} elseif ( 'spam' === $item['status'] ) {
			$actions['unspam'] = sprintf(
				'<a href="%s" class="vim-z vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$unspam_url,
				esc_attr__( 'Restore this review from the spam', 'reviews-tutor-lms' ),
				_x( 'Not spam', 'review', 'reviews-tutor-lms' )
			);
		}

		if ( 'trash' === $item['status'] ) {
			$actions['untrash'] = sprintf(
				'<a href="%s" class="vim-z vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$untrash_url,
				esc_attr__( 'Restore this review from the Trash', 'reviews-tutor-lms' ),
				__( 'Restore', 'reviews-tutor-lms' )
			);
		}

		if ( 'spam' === $item['status'] || 'trash' === $item['status'] || ! EMPTY_TRASH_DAYS ) {
			$actions['delete'] = sprintf(
				'<a href="%s" class="delete vim-d vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$delete_url,
				esc_attr__( 'Delete this review permanently', 'reviews-tutor-lms' ),
				__( 'Delete permanently', 'reviews-tutor-lms' )
			);
		} else {
			$actions['trash'] = sprintf(
				'<a href="%s" class="delete vim-d vim-destructive aria-button-if-js" aria-label="%s">%s</a>',
				$trash_url,
				esc_attr__( 'Move this review to the Trash', 'reviews-tutor-lms' ),
				_x( 'Trash', 'verb', 'reviews-tutor-lms' )
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
				++$i;
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
	 */
	public function get_bulk_actions(): array {
		$review_status = self::requested_status();

		$actions = array();

		if ( in_array( $review_status, array( 'all', 'approved' ), true ) ) {
			$actions['unapprove'] = __( 'Unapprove', 'reviews-tutor-lms' );
		}

		if ( in_array( $review_status, array( 'all', 'hold' ), true ) ) {
			$actions['approve'] = __( 'Approve', 'reviews-tutor-lms' );
		}

		if ( in_array( $review_status, array( 'all', 'hold', 'approved', 'trash' ), true ) ) {
			$actions['spam'] = _x( 'Mark as spam', 'review', 'reviews-tutor-lms' );
		}

		if ( 'trash' === $review_status ) {
			$actions['untrash'] = __( 'Restore', 'reviews-tutor-lms' );
		} elseif ( 'spam' === $review_status ) {
			$actions['unspam'] = _x( 'Not spam', 'review', 'reviews-tutor-lms' );
		}

		if ( in_array( $review_status, array( 'trash', 'spam' ), true ) || ! EMPTY_TRASH_DAYS ) {
			$actions['delete'] = __( 'Delete permanently', 'reviews-tutor-lms' );
		} else {
			$actions['trash'] = __( 'Move to Trash', 'reviews-tutor-lms' );
		}

		return $actions;
	}

	/**
	 * Bulk actions processing.
	 *
	 * @since 1.0.0
	 *
	 * @global object $wpdb WordPress database abstraction object.
	 */
	public function process_bulk_action(): void {
		$current_action = $this->current_action();

		if ( ! $current_action ) {
			return;
		}

		check_admin_referer( 'bulk-reviews' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You are not allowed to moderate reviews.', 'reviews-tutor-lms' ),
				403
			);
		}

		$statuses = array(
			'unapprove' => 'hold',
			'unspam'    => 'hold',
			'untrash'   => 'hold',
			'approve'   => 'approved',
			'spam'      => 'spam',
			'trash'     => 'trash',
		);

		// Ignore anything that is not one of our own bulk actions.
		if ( 'delete' !== $current_action && ! isset( $statuses[ $current_action ] ) ) {
			return;
		}

		$review_ids = isset( $_POST['element'] ) && is_array( $_POST['element'] )
			? array_filter( array_map( 'absint', wp_unslash( $_POST['element'] ) ) )
			: array();

		// An empty selection would produce an invalid `IN ()` clause.
		if ( ! $review_ids ) {
			return;
		}

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $review_ids ), '%d' ) );

		if ( 'delete' === $current_action ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"DELETE FROM $wpdb->comments
					WHERE comment_type='tutor_course_rating' AND comment_approved IN ('trash', 'spam') AND comment_ID IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					...$review_ids
				)
			);
		} else {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
					"UPDATE $wpdb->comments
					SET comment_approved = %s
					WHERE comment_type='tutor_course_rating' AND comment_ID IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$statuses[ $current_action ],
					...$review_ids
				)
			);
		}

		foreach ( $review_ids as $review_id ) {
			clean_comment_cache( $review_id );
		}
	}
}
