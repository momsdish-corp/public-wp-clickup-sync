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
class Page_Connections {

	private array $table_column_names = [
		'id'              => 'ID',
		'entity_type'     => 'Entity Type',
		'entity_id'       => 'Entity ID',
		'clickup_task_id' => 'ClickUp Task ID',
		'created_at'      => 'Created At',
		'updated_at'      => 'Updated At',
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
			'Connections',
			'Connections',
			'manage_options',
			WP_CLICKUP_SYNC_TEXT_DOMAIN . '-connections',
			[ $this, 'page_content' ],
			2
		);
	}


	/**
	 * Page content
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function page_content(): void {
		?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Connections', WP_CLICKUP_SYNC_TEXT_DOMAIN ); ?></h1>
			<?php $this->inner_page_content(); ?>
        </div>
		<?php
	}

	/**
	 * Inner page content
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function inner_page_content(): void {
		$protocol                 = is_ssl() ? 'https://' : 'http://';
		$DB_Connections           = new DB_Connections();
		$filtered_entity_id       = isset( $_GET['entity_id'] ) ? abs( (int) $_GET['entity_id'] ) : false;
		$filtered_clickup_task_id = isset( $_GET['clickup_task_id'] ) ? (string) $_GET['clickup_task_id'] : false;
		$total                    = $DB_Connections::count( $filtered_entity_id, $filtered_clickup_task_id );
		$posts_per_page           = 100;
		$page                     = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1;
		$offset                   = ( $page * $posts_per_page ) - $posts_per_page;
		$order                    = [];
		$order[]                  = [ 'column' => 'id', 'order' => 'DESC' ];
		$results                  = $DB_Connections::select( $posts_per_page, $offset, $order, $filtered_entity_id, $filtered_clickup_task_id );

		echo '<p>';
		$showing_count = count( $results );
		if ( $showing_count === $total ) {
			echo 'Showing all <strong>' . $showing_count . '</strong> records.';
		} else {
			echo 'Showing <strong>' . $showing_count . '</strong> of <strong>' . $total . '</strong> records.';
		}
		echo ' Connections are ordered by ID, descending.';
		echo '</p>';
		echo '<div class="wp-clickup-sync filter-menu">';
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
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'clickup_task_id' ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		echo '<input type="text" name="clickup_task_id" value="' . ( $filtered_clickup_task_id ?: '' ) . '" placeholder="ClickUp Task ID">';
		echo '<input type="submit" value="Filter" class="button button-primary">';
		echo '</form>';
		echo '<form method="GET" action="' . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '">';
		foreach ( $_GET as $key => $value ) {
			if ( $key !== 'clickup_task_id' ) {
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
				echo '<td>';
				if ( $key === 'clickup_task_id' ) {
					echo '<a href="https://app.clickup.com/t/' . $value . '" target="_blank">' . $value . '</a>';
				} else {
					echo $value;
				}
				echo '</td>';
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
}

$Page_Connections = new Page_Connections();
