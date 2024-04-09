<?php

namespace WP_ClickUp_Sync\Core\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use JsonException;
use WP_ClickUp_Sync\Helpers\Menu_Page\Base;

/**
 * Settings page
 * @ref https://codex.wordpress.org/Creating_Options_Pages#Example_.232
 */
class Page_Queue {

    private array $table_column_names = [
        'id' => 'ID',
        'entity_type' => 'Entity Type',
        'entity_id' => 'Entity ID',
        'request_url' => 'Request URL',
        'request_method' => 'Request Method',
        'request_body' => 'Request Body',
        'event_trigger' => 'Event Trigger',
        'priority' => 'Priority',
        'retry_count' => 'Retry Count',
        'queue_status' => 'Queue Status',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ];

	/**
	 * Start up
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	/**
	 * Add options page
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			WP_CLICKUP_SYNC_TEXT_DOMAIN . '-dashboard',
			'Queue',
			'Queue',
			'manage_options',
			WP_CLICKUP_SYNC_TEXT_DOMAIN . '-queue',
			[ $this, 'page_content' ],
			1
		);
	}


	/**
	 * Page content
	 *
	 * @return void
	 */
	public function page_content(): void {
		?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Queue', WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></h1><?php
			// Sections
			$sections = [
				[
					'id'            => 'upcoming',
					'title'         => 'Upcoming',
					'callback'      => [ $this, 'upcoming_tab' ],
					'callback_args' => []
				],
				[
					'id'            => 'completed',
					'title'         => 'Completed',
					'callback'      => [ $this, 'completed_tab' ],
					'callback_args' => []
				],
				[
					'id'            => 'failed',
					'title'         => 'Failed',
					'callback'      => [ $this, 'failed_tab' ],
					'callback_args' => []
				],
			];
			// Add URLs to sections
			foreach ( $sections as $key => $section ) {
				$sections[ $key ]['url'] = add_query_arg( 'tab', $section['id'], menu_page_url( WP_CLICKUP_SYNC_TEXT_DOMAIN . '-queue', false ) );
			}
			// Get current tab
			$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : $sections[0]['id'];
			// Render tabs
			?>
            <h2 class="wp-clickup-sync nav-tab-wrapper">
				<?php foreach ( $sections as $section ) : ?>
                    <a href="<?php echo esc_url( $section['url'] ); ?>"
                       class="nav-tab <?php echo $current_tab === $section['id'] ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $section['title'] ); ?></a>
				<?php endforeach; ?>
            </h2>
			<?php
			// Render current tab
			foreach ( $sections as $section ) {
				if ( ( $current_tab === $section['id'] ) ) {
					if ( is_callable( $section['callback'] ) ) {
						if ( isset( $section['callback_args'] ) ) {
							call_user_func( $section['callback'], $section['callback_args'] );
						} else {
							call_user_func( $section['callback'] );
						}
					}
				}
			}
			?>
        </div>
		<?php
	}

	/**
	 * Active Jobs tab
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function upcoming_tab(): void {
		$protocol = is_ssl() ? 'https://' : 'http://';
		$DB_Queue    = new DB_Queue();

		// Process the $_POST data
		if ( isset( $_POST['wp-clickup-sync-queue-action'] ) ) {
			// Cancel item
			if ( $_POST['wp-clickup-sync-queue-action'] === 'cancel' && $this->check_nonce( 'wp-clickup-sync-queue-action-cancel' ) ) {
				$id = (int) $_POST['wp-clickup-sync-queue-id'];
				// Cancel the job
				$results = $DB_Queue::cancel_upcoming_jobs( $id );
				// Render message
				if ( $results === false ) {
					?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php esc_html_e( "Failed to cancel the job #{$id}.", WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                    </div>
					<?php
				} else if ( $results === 0 ) {
					?>
                    <div class="notice notice-warning is-dismissible">
                        <p><?php esc_html_e( "Unable to find an upcoming job #{$id}.", WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                    </div>
					<?php
				} else {
					?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( "Job #{$id} has been cancelled.", WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                    </div>
					<?php
				}
			}
			// Manual Sync item
			if ( $_POST['wp-clickup-sync-queue-action'] === 'manual-sync' && $this->check_nonce( 'wp-clickup-sync-queue-action-manual-sync' ) ) {
				$id = (int) $_POST['wp-clickup-sync-queue-id'];
				// Cancel the job
				$queue_item = $DB_Queue::get_one( $id );
				if ( $queue_item ) {
					// Get item properties
					$queue_id       = $queue_item['id'];
					$entity_type    = $queue_item['entity_type'];
					$entity_id      = $queue_item['entity_id'];
					$request_url    = $queue_item['request_url'];
					$request_method = $queue_item['request_method'];
					$request_args   = [
						'body' => json_decode( $queue_item['request_body'], true, 512, JSON_THROW_ON_ERROR ),
					];
					$retry_count    = (int) $queue_item['retry_count'] ? $queue_item['retry_count'] : 0;

					// Request to sync
					$Sync = new \WP_ClickUp_Sync\Core\Cron\Sync();
					$Sync::sync_item( $queue_id, $entity_type, $entity_id, $request_url, $request_method, $request_args, $retry_count );

					// Render message
					?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( "Queue #{$id} was requested to sync.", WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                    </div>
					<?php
				} else {
					// Render message
					?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php esc_html_e( "Queue #{$id} not found. Unable to sync item.", WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                    </div>
					<?php
				}
			}
			// Cancel All Jobs
			if ( $_POST['wp-clickup-sync-queue-action'] === 'cancel-all' && $this->check_nonce( 'wp-clickup-sync-queue-action-cancel-all' ) ) {
				// Cancel all jobs
				$results = $DB_Queue::cancel_upcoming_jobs();
				// Render message
				if ( $results === false ) {
					?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php esc_html_e( 'Failed to cancel all jobs.', WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                    </div>
					<?php
				} else if ( $results === 0 ) {
					?>
                    <div class="notice notice-warning is-dismissible">
                        <p><?php esc_html_e( 'No upcoming jobs found.', WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                    </div>
					<?php
				} else {
					$jobs_have = $results === 1 ? ' job has' : ' jobs have';
					?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( $results . $jobs_have . ' been cancelled.', WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                    </div>
					<?php
				}
			}
			// Bulk Add Everything on the Website
			if ( $_POST['wp-clickup-sync-queue-action'] === 'sync-all' && $this->check_nonce( 'wp-clickup-sync-queue-action-sync-all' ) ) {
				$Options = new \WP_ClickUp_Sync\Helpers\Options\Base();

				/**
				 * Posts
				 */
				// Get all post types
				global $wp_post_types;

				// For each post
				foreach ( $wp_post_types as $post_type ) {
					$option_name = 'wp_clickup_sync_post_connections';
					$option_key  = $post_type->name . '__list_id';

					// Find out which is linked to a ClickUp List
					$clickup_list_id = $Options->get( $option_name, $option_key );

					// If linked
					if ( $clickup_list_id ) {
						// Get all posts
						$posts = get_posts( [
							'numberposts' => - 1,
							'post_status' => 'any',
							'post_type'   => $post_type->name,
						] );
						$count = 0;
						// For each post
						foreach ( $posts as $post ) {
							$count ++;
							// Add to queue
							$Add_To_Queue = new \WP_ClickUp_Sync\Core\Admin\Add_To_Queue();
							$Add_To_Queue->entity_changed( 'post', $post, 'manual_bulk_sync', - 2, false );
						}
						// Render Message
						?>
                        <div class="notice notice-success is-dismissible">
                            <p><?php esc_html_e( $count . " " . ( $count === 1 ? $post_type->singular_label : $post_type->label ) . " have been added to the queue.", WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                        </div>
						<?php
					}
				}

				/**
				 * Terms
				 */
				// Get all taxonomies
				global $wp_taxonomies;

				// For each taxonomy
				foreach ( $wp_taxonomies as $taxonomy ) {
					$option_name = 'wp_clickup_sync_term_connections';
					$option_key  = $taxonomy->name . '__list_id';

					// Find out which is linked to a ClickUp List
					$clickup_list_id = $Options->get( $option_name, $option_key );

					// If linked
					if ( $clickup_list_id ) {
						// Get all terms
						$terms = get_terms( [
							'taxonomy'   => $taxonomy->name,
							'hide_empty' => false,
						] );
						// For each term
						foreach ( $terms as $term ) {
							// Add to queue
                            $Add_To_Queue = new \WP_ClickUp_Sync\Core\Admin\Add_To_Queue();
							$Add_To_Queue->entity_changed( 'term', $term, 'manual_bulk_sync', - 2, false );
						}
						// Render Message
						?>
                        <div class="notice notice-success is-dismissible">
                            <p><?php esc_html_e( "All {$taxonomy->label} have been added to the queue.", WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                        </div>
						<?php
					}
				}
			}
		}

		// Render the table
		$type           = 'upcoming';
		$filtered_entity_id = isset( $_GET['entity_id'] ) ? abs( (int) $_GET['entity_id'] ) : false;
		$filtered_queue_id = isset( $_GET['queue_id'] ) ? abs( (int) $_GET['queue_id'] ) : false;
		$total          = $DB_Queue::count( $type, $filtered_entity_id, $filtered_queue_id );
		$posts_per_page = 20;
		$page           = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1;
		$offset         = ( $page * $posts_per_page ) - $posts_per_page;
		$order          = [];
		$order[]        = [ 'column' => 'queue_status', 'order' => 'DESC' ];
		$order[]        = [ 'column' => 'priority', 'order' => 'DESC' ];
		$order[]        = [ 'column' => 'id', 'order' => 'ASC' ];
		$results        = $DB_Queue::select( $posts_per_page, $offset, $order, $type, $filtered_entity_id, $filtered_queue_id );

		// Create a button menu
		echo '<div class="wp-clickup-sync menu-wrapper subsection-menu settings-subsections">';
		echo '<ul class="subsubsub menu">';
		echo '<li class="menu-item">';
		echo '<form method="post" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		wp_nonce_field( 'wp-clickup-sync-queue-action-sync-all', 'wp-clickup-sync-queue-action-sync-all-nonce' );
		echo '<input type="hidden" name="wp-clickup-sync-queue-action" value="sync-all">';
		echo '<input type="submit" value="Bulk-Sync Entire Website" class="button button-primary">';
		echo '</form>';
		echo '</li>';
		echo '<li class="menu-item">';
		echo '<form method="post" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		wp_nonce_field( 'wp-clickup-sync-queue-action-cancel-all', 'wp-clickup-sync-queue-action-cancel-all-nonce' );
		echo '<input type="hidden" name="wp-clickup-sync-queue-action" value="cancel-all">';
		echo '<input type="submit" value="Cancel All Jobs" class="button button-secondary">';
		echo '</form>';
		echo '</li>';
		echo '</ul>';
		echo '</div>';
		echo '<p>';
		$showing_count = count( $results );
		if ( $showing_count === $total ) {
			echo 'Showing all <strong>' . $showing_count . '</strong> records.';
		} else {
			echo 'Showing <strong>' . $showing_count . '</strong> of <strong>' . $total . '</strong> records.';
		}
		echo ' This table is sorted in the order of <strong>Queue Status</strong>, <strong>Priority</strong> and <strong>ID</strong>.';
		echo ' Items at the top of the table will sync first. New posts are given higher priority.';
		echo ' This list includes statuses <strong>queued</strong> and <strong>retrying</strong>.';
		echo '</p>';
		echo '<div class="wp-clickup-sync filter-menu">';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'queue_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="text" name="queue_id" value="' . ( $filtered_queue_id ?: '' ) . '" placeholder="Queue ID">';
		echo '<input type="submit" value="Filter" class="button button-primary">';
		echo '</form>';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'queue_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="submit" value="Clear" class="button button-secondary">';
		echo '</form>';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'entity_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="text" name="entity_id" value="' . ( $filtered_entity_id ?: '' ) . '" placeholder="Entity ID">';
		echo '<input type="submit" value="Filter" class="button button-primary">';
		echo '</form>';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'entity_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="submit" value="Clear" class="button button-secondary">';
		echo '</form>';
		echo '</div>';
		// Require data
		if ( ! isset( $results[0] ) ) {
			echo '<p>No data found.</p>';

			return;
		}
		echo '<table class="wp-list-table widefat fixed striped posts">';
		echo '<thead>';
		echo '<tr>';
		foreach ( $results[0] as $key => $value ) {
            echo '<th>';
            echo $this->table_column_names[ $key ] ?? $key;
            echo '</th>';
		}
		echo '<th>Cancel</th>';
		echo '<th>Manual Sync</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody id="the-list">';
		foreach ( $results as $result ) {
			echo '<tr>';
			foreach ( $result as $key => $value ) {
				echo '<td>' . $value . '</td>';
			}
			echo '<td>';
			echo '<form method="post" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
			wp_nonce_field( 'wp-clickup-sync-queue-action-manual-sync', 'wp-clickup-sync-queue-action-manual-sync-nonce' );
			echo '<input type="hidden" name="wp-clickup-sync-queue-action" value="manual-sync">';
			echo '<input type="hidden" name="wp-clickup-sync-queue-id" value="' . $result['id'] . '">';
			echo '<input type="submit" value="Manual Sync" class="button button-primary">';
			echo '</form>';
			echo '</td>';
			echo '<td>';
			echo '<form method="post" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
			wp_nonce_field( 'wp-clickup-sync-queue-action-cancel', 'wp-clickup-sync-queue-action-cancel-nonce' );
			echo '<input type="hidden" name="wp-clickup-sync-queue-action" value="cancel">';
			echo '<input type="hidden" name="wp-clickup-sync-queue-id" value="' . $result['id'] . '">';
			echo '<input type="submit" value="Cancel" class="button button-secondary">';
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';

		$pagination = paginate_links( array(
			'base'      => add_query_arg( 'cpage', '%#%' ),
			'format'    => '',
			'prev_text' => __( '&laquo;' ),
			'next_text' => __( '&raquo;' ),
			'total'     => ceil( $total / $posts_per_page ),
			'current'   => $page
		) );

		if ( $pagination ) {
			echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $pagination . '</div></div>';
		}
	}

	/**
	 * Completed Jobs tab
	 *
	 * @return void
	 */
	public function completed_tab(): void {
		$protocol = is_ssl() ? 'https://' : 'http://';
		$DB_Queue    = new DB_Queue();

		// Process the $_POST data
		if ( isset( $_POST['wp-clickup-sync-queue-action'] ) ) {
			// Purge All Completed Jobs
			if ( $_POST['wp-clickup-sync-queue-action'] === 'purge-all' && $this->check_nonce( 'wp-clickup-sync-queue-action-purge-all' ) ) {
				$DB_Queue::purge( false, 'completed' );
				// Render message
				?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Purged all completed jobs.', WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                </div>
				<?php
			}
		}

		// Render the table
		$type           = 'completed';
		$filtered_entity_id = isset( $_GET['entity_id'] ) ? abs( (int) $_GET['entity_id'] ) : false;
		$filtered_queue_id = isset( $_GET['queue_id'] ) ? abs( (int) $_GET['queue_id'] ) : false;
		$total          = $DB_Queue::count( $type, $filtered_entity_id, $filtered_queue_id );
		$posts_per_page = 20;
		$page           = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1;
		$offset         = ( $page * $posts_per_page ) - $posts_per_page;
		$order          = [];
		$order[]        = [ 'column' => 'updated_at', 'order' => 'DESC' ];
		$results        = $DB_Queue::select( $posts_per_page, $offset, $order, $type, $filtered_entity_id, $filtered_queue_id );

		// Create a button menu
		echo '<div class="wp-clickup-sync menu-wrapper subsection-menu settings-subsections">';
		echo '<ul class="subsubsub menu">';
		echo '<li class="menu-item">';
		echo '<form method="post" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		wp_nonce_field( 'wp-clickup-sync-queue-action-purge-all', 'wp-clickup-sync-queue-action-purge-all-nonce' );
		echo '<input type="hidden" name="wp-clickup-sync-queue-action" value="purge-all">';
		echo '<input type="submit" value="Purge Completed Jobs" class="button button-secondary">';
		echo '</form>';
		echo '</li>';
		echo '</ul>';
		echo '</div>';
		echo '<p>';
		$showing_count = count( $results );
		if ( $showing_count === $total ) {
			echo 'Showing all <strong>' . $showing_count . '</strong> records.';
		} else {
			echo 'Showing <strong>' . $showing_count . '</strong> of <strong>' . $total . '</strong> records.';
		}
		echo ' This table is ordered by <strong>last updated</strong>.';
        echo ' This list includes statuses <strong>successful</strong>, <strong>duplicate</strong>, and <strong>cancelled</strong>.';
		// Get settings for days retained
		$Options     = new \WP_ClickUp_Sync\Helpers\Options\Base();
		$max_days    = $Options->get( 'wp_clickup_sync_config', 'queue_retain_days', 30 );
		$days_string = $max_days > 1 ? ' days' : ' day';
		echo ' Records inactive for <strong>' . $max_days . $days_string . '</strong> are set to purge automatically.';
		echo '</p>';
		echo '<div class="wp-clickup-sync filter-menu">';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'queue_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="text" name="queue_id" value="' . ( $filtered_queue_id ?: '' ) . '" placeholder="Queue ID">';
		echo '<input type="submit" value="Filter" class="button button-primary">';
		echo '</form>';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'queue_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="submit" value="Clear" class="button button-secondary">';
		echo '</form>';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'entity_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="text" name="entity_id" value="' . ( $filtered_entity_id ?: '' ) . '" placeholder="Entity ID">';
		echo '<input type="submit" value="Filter" class="button button-primary">';
		echo '</form>';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'entity_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="submit" value="Clear" class="button button-secondary">';
		echo '</form>';
		echo '</div>';
		// Require data
		if ( ! isset( $results[0] ) ) {
			echo '<p>No data found.</p>';

			return;
		}
		echo '<table class="wp-list-table widefat fixed striped posts">';
		echo '<thead>';
		echo '<tr>';
		foreach ( $results[0] as $key => $value ) {
			echo '<th>';
			echo $this->table_column_names[ $key ] ?? $key;
			echo '</th>';
		}
		echo '<th>Retry</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody id="the-list">';
		foreach ( $results as $result ) {
			echo '<tr>';
			foreach ( $result as $key => $value ) {
				echo '<td>' . $value . '</td>';
			}
			echo '<td>';
			echo '<form method="post" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
			wp_nonce_field( 'wp-clickup-sync-queue-action-retry', 'wp-clickup-sync-queue-action-retry-nonce' );
			echo '<input type="hidden" name="wp-clickup-sync-queue-action" value="retry">';
			echo '<input type="hidden" name="wp-clickup-sync-queue-id" value="' . $result['id'] . '">';
			echo '<input type="submit" value="Retry" class="button button-primary">';
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';

		$pagination = paginate_links( array(
			'base'      => add_query_arg( 'cpage', '%#%' ),
			'format'    => '',
			'prev_text' => __( '&laquo;' ),
			'next_text' => __( '&raquo;' ),
			'total'     => ceil( $total / $posts_per_page ),
			'current'   => $page
		) );

		if ( $pagination ) {
			echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $pagination . '</div></div>';
		}
	}

	/**
	 * Failed Jobs tab
	 *
	 * @return void
	 */
	public function failed_tab(): void {
		$protocol = is_ssl() ? 'https://' : 'http://';
		$DB_Queue    = new DB_Queue();

		// Process the $_POST data
		if ( isset( $_POST['wp-clickup-sync-queue-action'] ) ) {
			// Retry item
			if ( $_POST['wp-clickup-sync-queue-action'] === 'retry' && $this->check_nonce( 'wp-clickup-sync-queue-action-retry' ) ) {
				$id = (int) $_POST['wp-clickup-sync-queue-id'];
				// Delete the job
				$DB_Queue::update( $id, 'retrying', 0 );
				// Render message
				?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( "Retrying queue #{$id}.", WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                </div>
				<?php
			}
			// Purge All Failed Jobs
			if ( $_POST['wp-clickup-sync-queue-action'] === 'purge-all' && $this->check_nonce( 'wp-clickup-sync-queue-action-purge-all' ) ) {
				$DB_Queue::purge( false, 'failed' );
				// Render message
				?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Purged all failed jobs.', WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                </div>
				<?php
			}
		}

		// Render the table
		$type           = 'failed';
		$filtered_entity_id = isset( $_GET['entity_id'] ) ? abs( (int) $_GET['entity_id'] ) : false;
		$filtered_queue_id = isset( $_GET['queue_id'] ) ? abs( (int) $_GET['queue_id'] ) : false;
		$total          = $DB_Queue::count( $type, $filtered_entity_id, $filtered_queue_id );
		$posts_per_page = 20;
		$page           = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1;
		$offset         = ( $page * $posts_per_page ) - $posts_per_page;
		$order          = [];
		$order[]        = [ 'column' => 'updated_at', 'order' => 'DESC' ];
		$results        = $DB_Queue::select( $posts_per_page, $offset, $order, $type, $filtered_entity_id, $filtered_queue_id );

		// Create a button menu
		echo '<div class="wp-clickup-sync menu-wrapper subsection-menu settings-subsections">';
		echo '<ul class="subsubsub menu">';
		echo '<li class="menu-item">';
		echo '<form method="post" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		wp_nonce_field( 'wp-clickup-sync-queue-action-purge-all', 'wp-clickup-sync-queue-action-purge-all-nonce' );
		echo '<input type="hidden" name="wp-clickup-sync-queue-action" value="purge-all">';
		echo '<input type="submit" value="Purge Failed Jobs" class="button button-secondary">';
		echo '</form>';
		echo '</li>';
		echo '</ul>';
		echo '</div>';
		echo '<p>';
		$showing_count = count( $results );
		if ( $showing_count === $total ) {
			echo 'Showing all <strong>' . $showing_count . '</strong> records.';
		} else {
			echo 'Showing <strong>' . $showing_count . '</strong> of <strong>' . $total . '</strong> records.';
		}
		echo ' This table is ordered by <strong>last updated</strong>.';
		echo ' This list only includes the <strong>failed</strong> status.';
		// Get settings for days retained
		$Options     = new \WP_ClickUp_Sync\Helpers\Options\Base();
		$max_days    = $Options->get( 'wp_clickup_sync_config', 'queue_retain_days', 30 );
		$days_string = $max_days > 1 ? ' days' : ' day';
		echo ' Records inactive for <strong>' . $max_days . $days_string . '</strong> are set to purge automatically.';
		echo '</p>';
		echo '<div class="wp-clickup-sync filter-menu">';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'queue_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="text" name="queue_id" value="' . ( $filtered_queue_id ?: '' ) . '" placeholder="Queue ID">';
		echo '<input type="submit" value="Filter" class="button button-primary">';
		echo '</form>';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'queue_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="submit" value="Clear" class="button button-secondary">';
		echo '</form>';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'entity_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="text" name="entity_id" value="' . ( $filtered_entity_id ?: '' ) . '" placeholder="Entity ID">';
		echo '<input type="submit" value="Filter" class="button button-primary">';
		echo '</form>';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'entity_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="submit" value="Clear" class="button button-secondary">';
		echo '</form>';
		echo '</div>';
		// Require data
		if ( ! isset( $results[0] ) ) {
			echo '<p>No data found.</p>';

			return;
		}
		echo '<table class="wp-list-table widefat fixed striped posts">';
		echo '<thead>';
		echo '<tr>';
		foreach ( $results[0] as $key => $value ) {
			echo '<th>';
			echo $this->table_column_names[ $key ] ?? $key;
			echo '</th>';
		}
		echo '<th>Retry</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody id="the-list">';
		foreach ( $results as $result ) {
			echo '<tr>';
			foreach ( $result as $key => $value ) {
				echo '<td>' . $value . '</td>';
			}
			echo '<td>';
			echo '<form method="post" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
			wp_nonce_field( 'wp-clickup-sync-queue-action-retry', 'wp-clickup-sync-queue-action-retry-nonce' );
			echo '<input type="hidden" name="wp-clickup-sync-queue-action" value="retry">';
			echo '<input type="hidden" name="wp-clickup-sync-queue-id" value="' . $result['id'] . '">';
			echo '<input type="submit" value="Retry" class="button button-primary">';
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';

		$pagination = paginate_links( array(
			'base'      => add_query_arg( 'cpage', '%#%' ),
			'format'    => '',
			'prev_text' => __( '&laquo;' ),
			'next_text' => __( '&raquo;' ),
			'total'     => ceil( $total / $posts_per_page ),
			'current'   => $page
		) );

		if ( $pagination ) {
			echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $pagination . '</div></div>';
		}
	}

	/**
	 * Check nonce
	 *
	 * @param string $action
	 *
	 * @return bool
	 */
	public function check_nonce( string $action ): bool {
		if ( wp_verify_nonce( $_REQUEST[ $action . '-nonce' ], $action ) ) {
			return true;
		}

		return false;
	}
}

$Page_Queue = new Page_Queue();
