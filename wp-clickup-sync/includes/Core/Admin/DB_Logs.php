<?php

namespace WP_ClickUp_Sync\Core\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class DB_Logs {
	/**
	 * Current version of the analytics database structure.
	 *
	 * @access   private
	 * @var      mixed $database_version Current version of the analytics database structure.
	 */
	private static $database_version = '1.5';

	/**
	 * Register actions and filters.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'check_database_version' ), 1 );
	}

	/**
	 * Check if the correct database version is present.
	 */
	public static function check_database_version() {
		if ( ! is_admin() ) {
			return;
		}

		$current_version = get_option( 'wp_clickup_sync_db_logs_version', '0.0' );

		if ( version_compare( $current_version, self::$database_version ) < 0 ) {
			self::update_database();
		}
	}

	/**
	 * Create or update the rating database.
	 * Dev notes: If this changes, make sure to update the self::$database_version variable.
	 */
	public static function update_database() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		queue_id bigint(20) NOT NULL,
		response_code int(3) unsigned NULL,
		response_message text NULL,
		queue_status varchar(32) NULL,
		created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY (id),
		KEY created_at (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$result = dbDelta( $sql );

		update_option( 'wp_clickup_sync_db_logs_version', self::$database_version );
	}

	/**
	 * Get the name of an analytics database table.
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'wp_clickup_sync_logs';
	}

	/**
	 * Sanitize an action.
	 *
	 * @param mixed $unsanitized_data Action to sanitize.
	 */
	public static function sanitize( $unsanitized_data ) {
		$data = [];

		// queue_id
		if ( isset( $unsanitized_data['queue_id'] ) ) {
			$data['queue_id'] = absint( $unsanitized_data['queue_id'] );
		}

		// response_code
		if ( isset( $unsanitized_data['response_code'] ) ) {
			$data['response_code'] = absint( $unsanitized_data['response_code'] );
		}

		// response_message
		if ( isset( $unsanitized_data['response_message'] ) ) {
			$data['response_message'] = sanitize_text_field( $unsanitized_data['response_message'] );
		}

		// queue_status
		if ( isset( $unsanitized_data['queue_status'] ) ) {
			$data['queue_status'] = sanitize_text_field( $unsanitized_data['queue_status'] );
		}

		// created_at - Set it using php, not MYSQL, to match WP time
		if ( isset( $unsanitized_data['created_at'] ) ) {
			$data['created_at'] = current_time( 'mysql' );
		}

		return $data;
	}

	/**
	 * Add queue item.
	 *
	 * @param int $queue_id Queue ID.
	 * @param int $response_code Response status.
	 * @param string $response_message Response response.
	 * @param string $queue_status Queue new status.
	 *
	 * @return int|false The number of rows inserted, or false on error.
	 */
	public static function add( int $queue_id, int $response_code, string $response_message, string $queue_status ): int|false {
		global $wpdb;
		$table_name = self::get_table_name();

		// Build data array
		$unsanitized_data = [
			'queue_id'    => $queue_id,
			'response_message' => $response_message,
			'response_code'   => $response_code,
			'queue_status'  => $queue_status,
			'created_at' => true,
		];

		// Sanitize data
		$data = self::sanitize( $unsanitized_data );

		// Insert data
		return $wpdb->insert( $table_name, $data );
	}

	/**
	 * Purge log items.
	 *
	 * @param int|false $retain_days The number of days to retain items, since last modified. If 0 or false, all items will be deleted.
	 *
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public static function purge( int|false $retain_days = 30 ): int|false {
		global $wpdb;
		$table_name = self::get_table_name();

		$retain_string = '';
		if ( $retain_days && $retain_days > 0 ) {
			$retain_string = " WHERE updated_at < DATE_SUB(NOW(), INTERVAL {$retain_days} DAY)";
		}

		// Get data
		return $wpdb->query( "DELETE FROM {$table_name}" . $retain_string );
	}

	/**
	 * Count results from the database.
	 *
	 * @param string|false $type 'successful'|'unsuccessful'|'all'. Queue status to filter by. Defaults to false.
	 * @param int|false $filtered_queue_id The entity ID to filter by. Defaults to false.
	 *
	 * @return int Returns the row as an array. Returns null if nothing is matched.
	 */
	public static function count( string|false $type = false, int|false $filtered_queue_id = false ): int {
		global $wpdb;
		$table_name = self::get_table_name();

		// Where queue_status
		$where_string = '';
		if ( $type === 'successful' ) {
			$where_string = " WHERE response_code = '200'";
		} elseif ( $type === 'unsuccessful' ) {
			$where_string = " WHERE response_code != '200'";
		} else {
			$where_string = " WHERE 1=1";
		}

		// Where entity_id
		$where_queue_id = '';
		if ( $filtered_queue_id ) {
			$where_queue_id = " AND queue_id = {$filtered_queue_id}";
		}

		$query = "SELECT COUNT(*) FROM {$table_name}{$where_string}{$where_queue_id}";

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Select results from the database.
	 *
	 * @param int $limit Number of results to return.
	 * @param int $offset Offset to start from.
	 * @param array $order Column to order by. Defaults to 'id', 'DESC'.
	 *   [ 'column': string 'id', 'order': string 'ASC' ]
	 * @param string|false $type 'successful'|'unsuccessful'|'all'. Queue status to filter by. Defaults to false.
	 * @param bool $wide Detailed view. Joins results with Queue db table. Defaults to false.
	 * @param int|false $filtered_queue_id The entity ID to filter by. Defaults to false.
	 *
	 * @return array|null Returns the row as an array. Returns null if nothing is matched.
	 */
	public static function select( int $limit = 0, int $offset = 0, array $order = [], string|false $type = false, bool $wide = false, int|false $filtered_queue_id = false ): array|null {
		global $wpdb;
		$logs_table_name = self::get_table_name();

		// Order
		if ( empty( $order ) ) {
			$order = [
				'column' => 'id',
				'order'  => 'DESC',
			];
		}

		$order_string = '';
		foreach ( $order as $order_item ) {
			if ( ! isset( $order_item['column'], $order_item['order'] ) ) {
				return null;
			}
			$order_string .= sanitize_text_field( $order_item['column'] ) . ' ' . ( $order_item['order'] );
			if ( $order_item !== end( $order ) ) {
				$order_string .= ', ';
			}
		}

		// Where queue_status
		$where_string = '';
		if ( $type === 'successful' ) {
			$where_string = " WHERE response_code = '200'";
		} elseif ( $type === 'unsuccessful' ) {
			$where_string = " WHERE response_code != '200'";
		} else {
			$where_string = " WHERE 1=1";
		}

		// Where entity_id
		$where_queue_id = '';
		if ( $filtered_queue_id ) {
			$where_queue_id = " AND queue_id = {$filtered_queue_id}";
		}

		// Wide
		$DB_Queue = new DB_Queue();
		$queue_table_name = $DB_Queue->get_table_name();
		if (! $wide) {
			$query = "SELECT * FROM {$logs_table_name}{$where_string}{$where_queue_id} ORDER BY {$order_string} LIMIT {$limit} OFFSET {$offset}";
//			$join_string = " LEFT JOIN {$wpdb->prefix}wp2static_queue ON {$wpdb->prefix}wp2static_queue.id = {$logs_table_name}.queue_id";
		} else {
			$query = "SELECT {$logs_table_name}.*, {$queue_table_name}.entity_type, {$queue_table_name}.entity_id, {$queue_table_name}.request_url, {$queue_table_name}.request_method, {$queue_table_name}.request_body, {$queue_table_name}.event_trigger FROM {$logs_table_name} LEFT JOIN {$queue_table_name} ON {$queue_table_name}.id = {$logs_table_name}.queue_id{$where_string}{$where_queue_id} ORDER BY {$order_string} LIMIT {$limit} OFFSET {$offset}";
		}

		return $wpdb->get_results( $query );
	}
}

DB_Logs::init();
