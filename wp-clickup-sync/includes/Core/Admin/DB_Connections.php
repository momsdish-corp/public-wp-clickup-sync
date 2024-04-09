<?php

namespace WP_ClickUp_Sync\Core\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use stdClass;

class DB_Connections {
	/**
	 * Current version of the analytics database structure.
	 *
	 * @access   private
	 * @var      mixed $database_version Current version of the analytics database structure.
	 */
	private static $database_version = '1.3';

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

		$current_version = get_option( 'wp_clickup_sync_db_connections_version', '0.0' );

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
		clickup_task_id varchar(32) NULL,
		created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY (id),
		KEY created_at (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$result = dbDelta( $sql );

		update_option( 'wp_clickup_sync_db_connections_version', self::$database_version );
	}

	/**
	 * Get the name of an analytics database table.
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'wp_clickup_sync_connections';
	}

	/**
	 * Sanitize an action.
	 *
	 * @param mixed $unsanitized_data Action to sanitize.
	 */
	public static function sanitize( $unsanitized_data ) {
		$data = [];

		// entity_type
		if ( isset( $unsanitized_data['entity_type'] ) && ( $unsanitized_data['entity_type'] === 'post' || $unsanitized_data['entity_type'] === 'term' ) ) {
			$data['entity_type'] = $unsanitized_data['entity_type'];
		}

		// entity_id
		if ( isset( $unsanitized_data['entity_id'] ) ) {
			$data['entity_id'] = (int) $unsanitized_data['entity_id'];
		}

		// clickup_task_id
		if ( isset( $unsanitized_data['clickup_task_id'] ) ) {
			if ( $unsanitized_data['clickup_task_id'] === '' || $unsanitized_data['clickup_task_id'] === false ) {
				$data['clickup_task_id'] = '';
			} else {
				$data['clickup_task_id'] = sanitize_text_field( $unsanitized_data['clickup_task_id'] );
			}
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
	 * Get a row from the database.
	 *
	 * @param string $entity_type Type of entity, e.g. 'post' or 'term'.
	 * @param int $entity_id Id of the post or term.
	 *
	 * @return array|null The row as an object, or null if not found.
	 */
	public static function get( string $entity_type, int $entity_id ): object|null {
		global $wpdb;
		$table_name = self::get_table_name();

		$query = $wpdb->prepare( "SELECT * FROM $table_name WHERE entity_type = %s AND entity_id = %d", $entity_type, $entity_id );

		return $wpdb->get_row( $query );
	}

	/**
	 * Add a new action to the database.
	 * This is a conditional insert. Will only insert if the same $entity_type and $entity_id combination does not exist.
	 *
	 * @param string $entity_type Type of entity, e.g. 'post' or 'term'.
	 * @param int $entity_id Id of the post or term.
	 * @param string $task_id Task id.
	 *
	 * @return int|false The number of rows inserted, or false on error.
	 */
	public static function add( string $entity_type, int $entity_id, string $task_id ): int|false {
		// Check if the same entity_type and entity_id combination already exists.
		$existing = self::get( $entity_type, $entity_id );

		if ( $existing ) {
			return false;
		}

		global $wpdb;
		$table_name = self::get_table_name();

		$unsanitized_data = [
			'entity_type'     => $entity_type,
			'entity_id'       => $entity_id,
			'clickup_task_id' => $task_id,
			'created_at'      => true,
		];

		$data = self::sanitize( $unsanitized_data );

		return $wpdb->insert( $table_name, $data );
	}

	/**
	 * Update an existing action to the database.
	 *
	 * @param string $entity_type Type of entity, e.g. 'post' or 'term'.
	 * @param int $entity_id Id of the post or term.
	 * @param string $task_id Task id.
	 *
	 * @return int|false The number of rows updated, or false on error.
	 */
	public static function update( string $entity_type, int $entity_id, string $task_id ): int|false {
		global $wpdb;
		$table_name = self::get_table_name();

		$unsanitized_data = [
			'entity_type'     => $entity_type,
			'entity_id'       => $entity_id,
			'clickup_task_id' => $task_id,
			'updated_at'      => true,
		];

		$data = self::sanitize( $unsanitized_data );

		$where = [
			'entity_type' => $entity_type,
			'entity_id'   => $entity_id,
		];

		return $wpdb->update( $table_name, $data, $where );
	}

	/**
	 * Count results from the database.
	 *
	 * @param int|false $filtered_entity_id Entity id to filter by.
	 * @param string|false $filtered_clickup_task_id ClickUp task id to filter by.
	 *
	 * @return int Returns the row as an array. Returns null if nothing is matched.
	 */
	public static function count( int|false $filtered_entity_id = false, string|false $filtered_clickup_task_id = false ): int {
		global $wpdb;
		$table_name = self::get_table_name();

		$where_string = "WHERE 1=1";
		if ( $filtered_entity_id ) {
			$where_string .= " AND entity_id = $filtered_entity_id";
		}
		if ( $filtered_clickup_task_id ) {
			$where_string .= " AND clickup_task_id = '$filtered_clickup_task_id'";
		}

		$query = "SELECT COUNT(*) FROM {$table_name} {$where_string}";

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Select results from the database.
	 *
	 * @param int $limit Number of results to return.
	 * @param int $offset Offset to start from.
	 * @param array $order Column to order by. Defaults to 'id', 'DESC'.
	 *   [ 'column': string 'id', 'order': string 'ASC' ]
	 * @param int|false $filtered_entity_id Entity id to filter by.
	 * @param string|false $filtered_clickup_task_id ClickUp task id to filter by.
	 *
	 * @return array|null Returns the row as an array. Returns null if nothing is matched.
	 */
	public static function select( int $limit = 0, int $offset = 0, array $order = [], int|false $filtered_entity_id = false, string|false $filtered_clickup_task_id = false ): array|null {
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

		$where_string = "WHERE 1=1";
		if ( $filtered_entity_id ) {
			$where_string .= " AND entity_id = $filtered_entity_id";
		}
		if ( $filtered_clickup_task_id ) {
			$where_string .= " AND clickup_task_id = '$filtered_clickup_task_id'";
		}

		$query = $wpdb->prepare( "SELECT * FROM {$table_name} {$where_string} ORDER BY $order_string LIMIT %d OFFSET %d", $limit, $offset );

		return $wpdb->get_results( $query );
	}
}

DB_Connections::init();
