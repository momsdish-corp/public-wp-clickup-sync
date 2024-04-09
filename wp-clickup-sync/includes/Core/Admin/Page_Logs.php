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
class Page_Logs {

	private array $table_column_names = [
		'id' => 'ID',
		'queue_id' => 'Queue ID',
		'response_code' => 'Response Code',
        'response_message' => 'Response Message',
        'queue_status' => 'Queue Status',
        'created_at' => 'Created At',
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
			'Logs',
			'Logs',
			'manage_options',
			WP_CLICKUP_SYNC_TEXT_DOMAIN . '-logs',
			[ $this, 'page_content' ],
			3
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
            <h1><?php esc_html_e( 'Logs', WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></h1><?php
			// Sections
			$sections = [
				[
					'id'            => 'all-calls',
					'title'         => 'All',
					'callback'      => [ $this, 'tab_content' ],
					'callback_args' => [
						'type' => 'all'
					]
				],
				[
					'id'            => 'successful-calls',
					'title'         => 'Successful',
					'callback'      => [ $this, 'tab_content' ],
					'callback_args' => [
						'type' => 'successful'
					]
				],
				[
					'id'            => 'unsuccessful-calls',
					'title'         => 'Unsuccessful',
					'callback'      => [ $this, 'tab_content' ],
					'callback_args' => [
						'type' => 'unsuccessful'
					]
				],
			];
			// Add URLs to sections
			foreach ( $sections as $key => $section ) {
				$sections[ $key ]['url'] = add_query_arg( 'tab', $section['id'], menu_page_url( WP_CLICKUP_SYNC_TEXT_DOMAIN . '-logs', false ) );
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
	 * @param array $args Callback arguments
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function tab_content( array $args = [] ): void {
		$protocol = is_ssl() ? 'https://' : 'http://';
		$DB_Logs  = new DB_Logs();

		// Process the $_POST data
		if ( isset( $_POST['wp-clickup-sync-logs-action'] ) ) {
			// Purge All Jobs
			if ( $_POST['wp-clickup-sync-logs-action'] === 'purge-all' && $this->check_nonce( 'wp-clickup-sync-logs-action-purge-all' ) ) {
				$DB_Logs::purge( false );
				// Render message
				?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Purged all logs.', WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></p>
                </div>
				<?php
			}
		}

		$type              = $args['type'] ?? '';
		$filtered_queue_id = isset( $_GET['queue_id'] ) ? abs( (int) $_GET['queue_id'] ) : false;
		$total             = $DB_Logs::count( $type, $filtered_queue_id );
		$posts_per_page    = 20;
		$page              = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1;
		$offset            = ( $page * $posts_per_page ) - $posts_per_page;
		$order             = [];
		$order[]           = [ 'column' => 'id', 'order' => 'DESC' ];
		$results           = $DB_Logs::select( $posts_per_page, $offset, $order, $type, false, $filtered_queue_id );

		if ( $type === 'all' ) {
			// Create a button menu
			echo '<div class="wp-clickup-sync menu-wrapper subsection-menu settings-subsections">';
			echo '<ul class="subsubsub menu">';
			echo '<li class="menu-item">';
			echo '<form method="post" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
			wp_nonce_field( 'wp-clickup-sync-logs-action-purge-all', 'wp-clickup-sync-logs-action-purge-all-nonce' );
			echo '<input type="hidden" name="wp-clickup-sync-logs-action" value="purge-all">';
			echo '<input type="submit" value="Purge All Inactive Jobs" class="button button-secondary">';
			echo '</form>';
			echo '</li>';
			echo '</ul>';
			echo '</div>';
		}
		echo '<p>';
		$showing_count = count( $results );
		if ( $showing_count === $total ) {
			echo 'Showing all <strong>' . $showing_count . '</strong> records.';
		} else {
			echo 'Showing <strong>' . $showing_count . '</strong> of <strong>' . $total . '</strong> records.';
		}
		echo ' This table is sorted in the order it was created, with the latest logs at the top.';
		// Get settings for days retained
		$Options     = new \WP_ClickUp_Sync\Helpers\Options\Base();
		$max_days    = $Options->get( 'wp_clickup_sync_config', 'logs_retain_days', 30 );
		$days_string = $max_days > 1 ? ' days' : ' day';
		echo ' Records older than <strong>' . $max_days . $days_string . '</strong> are set to purge automatically.';
		echo '</p>';
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
		echo '</tr>';
		echo '</thead>';
		echo '<tbody id="the-list">';
		foreach ( $results as $result ) {
			echo '<tr>';
			foreach ( $result as $key => $value ) {
				echo '<td>' . $value . '</td>';
			}
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

$Page_Logs = new Page_Logs();
