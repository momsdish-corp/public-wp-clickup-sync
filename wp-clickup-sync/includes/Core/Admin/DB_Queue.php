<?php

namespace WP_ClickUp_Sync\Core\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use http\Encoding\Stream\Inflate;
use JsonException;

class DB_Queue {
	/**
	 * Current version of the analytics database structure.
	 *
	 * @access   private
	 * @var      mixed $database_version Current version of the analytics database structure.
	 */
	private static $database_version = '1.9';

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

		$current_version = get_option( 'wp_clickup_sync_db_queue_version', '0.0' );

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
		entity_type varchar(64) NOT NULL,
		entity_id bigint(20) unsigned NOT NULL,
		request_url varchar(128) NOT NULL,
		request_method varchar(32) NOT NULL,
		request_body text NULL,
		event_trigger varchar(64) NOT NULL,
		priority int(32) NOT NULL,
		retry_count int(3) unsigned NULL,
		queue_status varchar(32) NULL,
		created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY (id),
		KEY created_at (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$result = dbDelta( $sql );

		update_option( 'wp_clickup_sync_db_queue_version', self::$database_version );
	}

	/**
	 * Get the name of an analytics database table.
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'wp_clickup_sync_queue';
	}

	/**
	 * Sanitize an action.
	 *
	 * @param mixed $unsanitized_data Action to sanitize.
	 *
	 * @throws JsonException
	 */
	public static function sanitize( $unsanitized_data ) {
		$data = [];

		// id
		if ( isset( $unsanitized_data['id'] ) ) {
			$data['id'] = absint( $unsanitized_data['id'] );
		}

		// entity_type
		if ( isset( $unsanitized_data['entity_type'] ) && ( $unsanitized_data['entity_type'] === 'post' || $unsanitized_data['entity_type'] === 'term' ) ) {
			$data['entity_type'] = $unsanitized_data['entity_type'];
		}

		// entity_id
		if ( isset( $unsanitized_data['entity_id'] ) ) {
			$data['entity_id'] = (int) $unsanitized_data['entity_id'];
		}

		// request_url
		if ( isset( $unsanitized_data['request_url'] ) ) {
			$data['request_url'] = sanitize_text_field( $unsanitized_data['request_url'] );
		}

		// request_method
		if ( isset( $unsanitized_data['request_method'] ) ) {
			$data['request_method'] = sanitize_text_field( $unsanitized_data['request_method'] );
		}

		// request_body - Sanitize and serialize the request body.
		if ( isset( $unsanitized_data['request_body'] ) ) {
			$data['request_body'] = json_encode( $unsanitized_data['request_body'], JSON_THROW_ON_ERROR );
			$data['request_body'] = sanitize_text_field( $data['request_body'] );
		}

		// event_trigger
		if ( isset( $unsanitized_data['event_trigger'] ) ) {
			$data['event_trigger'] = sanitize_text_field( $unsanitized_data['event_trigger'] );
		}

		// priority
		if ( isset( $unsanitized_data['priority'] ) ) {
			$data['priority'] = (int) $unsanitized_data['priority'];
		}

		// retry_count
		if ( isset( $unsanitized_data['retry_count'] ) ) {
			$data['retry_count'] = (int) $unsanitized_data['retry_count'];
		}

		// queue_status (Options: queued, cancelled, retrying, successful, failed, duplicate)
		if ( isset( $unsanitized_data['queue_status'] ) && (
				$unsanitized_data['queue_status'] === 'queued' ||
				$unsanitized_data['queue_status'] === 'retrying' ||
				$unsanitized_data['queue_status'] === 'successful' ||
				$unsanitized_data['queue_status'] === 'failed' ||
				$unsanitized_data['queue_status'] === 'duplicate' ||
				$unsanitized_data['queue_status'] === 'cancelled'
			) ) {
			$data['queue_status'] = $unsanitized_data['queue_status'];
		}

		// created_at - Set it using php, not MYSQL, to match WP time
		if ( isset( $unsanitized_data['created_at'] ) ) {
			$data['created_at'] = current_time( 'mysql' );
		}

		// updated_at - Set it using php, not MYSQL, to match WP time
		if ( isset( $unsanitized_data['updated_at'] ) ) {
			$data['updated_at'] = current_time( 'mysql' );
		}

		return $data;
	}

	/**
	 * Add queue item.
	 *
	 * @param string $entity_type Entity type.
	 * @param int $entity_id Entity ID.
	 * @param string $request_url The request url.
	 * @param string $request_method The request method.
	 * @param array $request_body The request body.
	 * @param string $event_trigger name of the WP hook that triggered the request.
	 * @param int $priority The priority.
	 *
	 * @return int|false The number of rows inserted, or false on error.
	 * @throws JsonException
	 */
	public static function add( string $entity_type, int $entity_id, string $request_url, string $request_method, array $request_body, string $event_trigger, int $priority = 0 ): int|false {
		global $wpdb;
		$table_name = self::get_table_name();

		// Build data array
		$unsanitized_data = [
			'entity_type'    => $entity_type,
			'entity_id'      => $entity_id,
			'request_url'    => $request_url,
			'request_method' => $request_method,
			'request_body'   => $request_body,
			'event_trigger'  => $event_trigger,
			'priority'       => $priority,
			'retry_count'    => 0,
			'queue_status'   => 'queued',
			'created_at'     => true,
			'updated_at'     => true,
		];

		// Sanitize data
		$data = self::sanitize( $unsanitized_data );

		// Insert data
		return $wpdb->insert( $table_name, $data );
	}

	/**
	 * Purge queue items.
	 *
	 * @param int|false $retain_days The number of days to retain items, since last modified. If 0 or false, all items will be deleted.
	 * @param string $select Select the items to delete. Options 'all'|'completed'|'failed'.
	 *
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public static function purge( int|false $retain_days = 30, string $select = 'all' ): int|false {
		global $wpdb;
		$table_name = self::get_table_name();

		// Where clause
		if ( $select === 'completed' ) {
			$where_string = " WHERE queue_status IN ('successful', 'cancelled', 'duplicate')";
		} elseif ( $select === 'failed' ) {
			$where_string = " WHERE queue_status = 'failed'";
		} else {
			$where_string = " WHERE queue_status IN ('successful', 'failed', 'cancelled', 'duplicate')";
		}

		$retain_string = '';
		if ( $retain_days && $retain_days > 0 ) {
			$retain_string = " AND updated_at < DATE_SUB(NOW(), INTERVAL {$retain_days} DAY)";
		}

        $wpdb_query_str="DELETE FROM {$table_name}{$where_string}" . $retain_string;

        return $wpdb->query( $wpdb_query_str );
	}

	/**
	 * Count results from the database.
	 *
	 * @param string|false $type 'upcoming'|'completed'|'failed'|false. Queue status to filter by. Defaults to false.
	 * @param int|false $filtered_entity_id The entity ID to filter by. Defaults to false.
	 * @param int|false $filtered_queue_id The queue ID to filter by. Defaults to false.
	 *
	 * @return int Returns the row as an array. Returns null if nothing is matched.
	 */
	public static function count( string|false $type = false, int|false $filtered_entity_id = false, int|false $filtered_queue_id = false ): int {
		global $wpdb;
		$table_name = self::get_table_name();

		// Where queue_status
		if ( $type === 'upcoming' ) {
			$where_string = " WHERE queue_status IN ('queued', 'retrying')";
		} elseif ( $type === 'completed' ) {
			$where_string = " WHERE queue_status IN ('successful', 'cancelled', 'duplicate')";
		} elseif ( $type === 'failed' ) {
			$where_string = " WHERE queue_status = 'failed'";
		} else {
			$where_string = ' WHERE 1=1';
		}

		// Entity ID
		$entity_id_where_string = '';
		if ( $filtered_entity_id ) {
			$entity_id_where_string = " AND entity_id = {$filtered_entity_id}";
		}

		// Queue ID
		$queue_id_where_string = '';
		if ( $filtered_queue_id ) {
			$queue_id_where_string = " AND id = {$filtered_queue_id}";
		}

		$query = "SELECT COUNT(*) FROM {$table_name}{$where_string}{$entity_id_where_string}{$queue_id_where_string}";

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Select results from the database.
	 *
	 * @param int $limit Number of results to return.
	 * @param int $offset Offset to start from.
	 * @param array $order Column to order by. Defaults to 'id', 'DESC'.
	 *   [ 'column': string 'id', 'order': string 'ASC' ]
	 * @param string|false $type 'upcoming'|'completed'|'failed'|false. Queue status to filter by. Defaults to false.
	 * @param int|false $filtered_entity_id The entity ID to filter by. Defaults to false.
	 * @param int|false $filtered_queue_id The queue ID to filter by. Defaults to false.
	 *
	 * @return array|null Returns the row as an array. Returns null if nothing is matched.
	 */
	public static function select( int $limit = 0, int $offset = 0, array $order = [], string|false $type = false, int|false $filtered_entity_id = false, int|false $filtered_queue_id = false ): array|null {
		global $wpdb;
		$table_name = self::get_table_name();

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
		if ( $type === 'upcoming' ) {
			$where_string = " WHERE queue_status IN ('queued', 'retrying')";
		} elseif ( $type === 'completed' ) {
			$where_string = " WHERE queue_status IN ('successful', 'cancelled', 'duplicate')";
		} elseif ( $type === 'failed' ) {
			$where_string = " WHERE queue_status = 'failed'";
		} else {
			$where_string = ' WHERE 1=1';
		}

		// Entity ID
		$entity_id_where_string = '';
		if ( $filtered_entity_id ) {
			$entity_id_where_string = " AND entity_id = {$filtered_entity_id}";
		}

		// Queue ID
		$queue_id_where_string = '';
		if ( $filtered_queue_id ) {
			$queue_id_where_string = " AND id = {$filtered_queue_id}";
		}

		$query = "SELECT * FROM {$table_name}{$where_string}{$entity_id_where_string}{$queue_id_where_string} ORDER BY {$order_string} LIMIT {$limit} OFFSET {$offset}";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get next queue item.
	 *
	 * @param int $limit Number of results to return.
	 *
	 * @return array|null Returns the row as an array. Returns null if nothing is matched.
	 * {
	 *   id: int
	 *   entity_type: string
	 *   entity_id: int
	 *   request_url: string
	 *   request_method: string 'GET'|'POST'|'PUT'|'PATCH'|'DELETE'
	 *   request_body: string (serialized array)
	 *   event_trigger: string
	 *   priority: int
	 *   retry_count: int
	 *   queue_status: string 'queued'|'retrying'
	 *   created_at: YYYY-MM-DD HH:MM:SS
	 *   updated_at: YYYY-MM-DD HH:MM:SS
	 * )
	 */
	public static function get_next( int $limit = 1 ): array|null {
		global $wpdb;
		$table_name = self::get_table_name();

		$query = "SELECT * FROM {$table_name} WHERE queue_status in ('queued','retrying') ORDER BY queue_status DESC, priority DESC, id DESC LIMIT {$limit}";

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get one queue item.
	 *
	 * @param int $id The queue item ID.
	 *
	 * @return array|null Returns the row as an array. Returns null if nothing is matched.
	 */
	public static function get_one( int $id ): array|null {
		global $wpdb;
		$table_name = self::get_table_name();

		$query = "SELECT * FROM {$table_name} WHERE id = {$id}";

		return $wpdb->get_row( $query, ARRAY_A );
	}

	/**
	 * Update an existing action to the database.
	 *
	 * @param int $queue_id Queue ID to match on.
	 * @param string $queue_status Queue status to update to.
	 * @param int|false $retry_count (Optional) Number of retries. Defaults to false.
	 *
	 * @return int|false The number of rows updated, or false on error.
	 */
	public static function update( int $queue_id, string $queue_status, int|false $retry_count = false ): int|false {
		global $wpdb;
		$table_name = self::get_table_name();

		$unsanitized_data = [
			'id'           => $queue_id,
			'queue_status' => $queue_status,
			'updated_at'   => true,
		];

		if ( $retry_count !== false ) {
			$unsanitized_data['retry_count'] = $retry_count;
		}

		$data = self::sanitize( $unsanitized_data );

		$where['id'] = $queue_id;

		return $wpdb->update( $table_name, $data, $where );
	}

	/**
	 * Cancel active queue jobs
	 *
	 * @param int|false $queue_id Queue ID to match on. Defaults to all.
	 *
	 * @return int|false The number of rows updated, or false on error.
	 */
	public static function cancel_upcoming_jobs( int|false $queue_id = false ): int|false {
		global $wpdb;
		$table_name = self::get_table_name();

		// Data
		$unsanitized_data = [
			'queue_status' => 'cancelled',
			'updated_at'   => true,
		];
		$data             = self::sanitize( $unsanitized_data );
		$data_string      = implode( ', ', array_map(
			static function ( $v, $k ) {
				return $k . " = '" . $v . "'";
			},
			$data,
			array_keys( $data )
		) );


		// Where
		$where_string = "queue_status IN ('queued', 'retrying')";

		if ( $queue_id ) {
			$where_string .= " AND id = '{$queue_id}'";
		}

		$query = "UPDATE {$table_name} SET {$data_string} WHERE {$where_string}";

		return $wpdb->query( $query );
	}

	/**
	 * Cancel queue jobs by entity type and ID
	 *
	 * @param string $entity_type Entity type to match on.
	 * @param int $entity_id Entity ID to match on.
	 *
	 * @return int|false The number of rows updated, or false on error.
	 */
	public static function cancel_upcoming_jobs_by_entity( string $entity_type, int $entity_id ): int|false {
		global $wpdb;
		$table_name = self::get_table_name();

		// Data
		$unsanitized_data = [
			'queue_status' => 'cancelled',
			'updated_at'   => true,
		];
		$data             = self::sanitize( $unsanitized_data );
		$data_string      = implode( ', ', array_map(
			static function ( $v, $k ) {
				return $k . " = '" . $v . "'";
			},
			$data,
			array_keys( $data )
		) );

		// Where
		$unsanitized_where = [
			'entity_type' => $entity_type,
			'entity_id'   => $entity_id,
		];
		$where             = self::sanitize( $unsanitized_where );
		$where_string      = implode( ' AND ', array_map(
			static function ( $v, $k ) {
				return $k . " = '" . $v . "'";
			},
			$where,
			array_keys( $where )
		) );
		$where_string      .= " AND queue_status IN ('queued', 'retrying')";

		$query = "UPDATE {$table_name} SET {$data_string} WHERE {$where_string}";

		return $wpdb->query( $query );
	}
}

DB_Queue::init();
