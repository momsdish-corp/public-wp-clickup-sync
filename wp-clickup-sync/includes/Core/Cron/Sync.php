<?php

namespace WP_ClickUp_Sync\Core\Cron;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use JsonException;

class Sync {
	/**
	 * Register actions and filters.
	 */
	public static function init(): void {
		// Add cron hook
		register_activation_hook( WP_CLICKUP_SYNC_FILE, array( __CLASS__, 'on_activation' ) );
		register_deactivation_hook( WP_CLICKUP_SYNC_FILE, array( __CLASS__, 'on_deactivation' ) );

		// Add cron interval
		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_intervals' ] );

		// Add cron job
		add_action( 'wp_clickup_sync_cron_sync', [ __CLASS__, 'do_sync' ] );
	}

	public static function on_activation(): void {
		// Add Cron Sync
		if ( ! wp_next_scheduled( 'wp_clickup_sync_cron_sync' ) ) {
			wp_schedule_event( time(), 'wp_clickup_sync_60sec', 'wp_clickup_sync_cron_sync' );
		}
	}

	public static function on_deactivation(): void {
		// Remove Cron Sync
		wp_clear_scheduled_hook( 'wp_clickup_sync_cron_sync' );
	}

	/**
	 * Add interval to cron schedules.
	 *
	 * @param mixed $schedules Current cron schedules.
	 *
	 * @return array
	 */
	public static function add_cron_intervals( mixed $schedules ): array {
		$schedules['wp_clickup_sync_60sec'] = array(
			'interval' => 60,
			'display'  => 'X ClickUp Sync 60 Seconds',
		);

		return $schedules;
	}

	/**
	 * Sync the queue.
	 *
	 * @return void
	 * @throws JsonException
	 */
	public static function do_sync(): void {
		// Get request limit
		$Options             = new \WP_ClickUp_Sync\Helpers\Options\Base();
		$requests_per_minute = $Options->get( 'wp_clickup_sync_config', 'requests_per_minute', 1 );
		// Do this for X number of queries
		$DB_Queue = new \WP_ClickUp_Sync\Core\Admin\DB_Queue();
		// Get next item in queue
		$next_items = $DB_Queue::get_next( $requests_per_minute );
		foreach ( $next_items as $next_item ) {
			// Get item properties
			$queue_id       = $next_item['id'];
			$entity_type    = $next_item['entity_type'];
			$entity_id      = $next_item['entity_id'];
			$request_url    = $next_item['request_url'];
			$request_method = $next_item['request_method'];
			$request_args   = [
				'body' => json_decode( $next_item['request_body'], true, 512, JSON_THROW_ON_ERROR ),
			];
			$retry_count    = (int) $next_item['retry_count'] ? $next_item['retry_count'] : 0;

			self::sync_item( $queue_id, $entity_type, $entity_id, $request_url, $request_method, $request_args, $retry_count );
		}
	}

	/**
	 * Sync an item.
	 *
	 * @param string $queue_id Queue ID.
	 * @param string $entity_type Entity Type.
	 * @param string $entity_id Entity ID.
	 * @param string $request_url Request URL.
	 * @param string $request_method Request Method.
	 * @param array $request_args Request Args.
	 * @param int $retry_count Retry Count.
	 *
	 * @return void
	 * @throws JsonException
	 */
	public static function sync_item( string $queue_id, string $entity_type, string $entity_id, string $request_url, string $request_method, array $request_args, int $retry_count ): void {
		$ClickUp  = new \WP_ClickUp_Sync\Helpers\ClickUp_API\Base();
		$DB_Queue = new \WP_ClickUp_Sync\Core\Admin\DB_Queue();

		// Send request
		$response = $ClickUp->request( $request_url, $request_method, $request_args )->get_response();


		// Get response info
		if ( is_wp_error( $response ) ) {
			$response_code    = 0;
			$response_message = $response->get_error_message();
		} else {
			$response_code    = $response['response']['code'] ?? 0;
			$response_message = $response['body'] ?? '';
		}


		// Decide the next queue status ('queued'|'retrying'|'successful'|'failed'|'cancelled')
		if ( $response_code === 200 ) {
			$queue_status = 'successful';
		} else if ( $retry_count < 2 ) {
			// If retry count is less than 3, then retry
			$retry_count ++;
			$queue_status = 'retrying';
		} else {
			$queue_status = 'failed';
		}


		// If this was a valid task create call, require it to return task ID
		// if $request_url starts with 'https://api.clickup.com/api/v2/list/',
		if ( $response_code === 200 && $request_method === 'POST' && str_starts_with( $request_url, $ClickUp->api_endpoint . 'list/' ) ) {
			// Parse response
			$response_body = json_decode( wp_remote_retrieve_body( $response ), false, 512, JSON_THROW_ON_ERROR );
			if ( isset( $response_body->id ) ) {
				// Add ClickUp ID to connections
				$DB_Connections  = new \WP_ClickUp_Sync\Core\Admin\DB_Connections();
				$clickup_task_id = $response_body->id;
				$is_added_to_db  = $DB_Connections::add( $entity_type, $entity_id, $clickup_task_id );
				// If not able to add to the database, it means that there is an existing connection
				if ( false === $is_added_to_db ) {
					// If the clickup_task_id in the existing connection is null, then update it
					// Otherwise, set set the queue status to 'duplicate', and delete the ClickUp task that was just created
					$row             = $DB_Connections::get( $entity_type, $entity_id );
					$connected_clickup_task_id = $row->clickup_task_id ?? '';
					if ( empty( $connected_clickup_task_id ) ) {
						$is_updated_on_db = $DB_Connections::update( $entity_type, $entity_id, $clickup_task_id );
					} else {
						// Update queue status
						$queue_status = 'duplicate';
						// Build query
						$Query          = new \WP_ClickUp_Sync\Helpers\ClickUp_API\Query();
						$query          = $Query->delete_task( $clickup_task_id );
						$request_url    = $query['url'];
						$request_method = $query['method'];
						$ClickUp->request( $request_url, $request_method )->get_response();
					}
				}
			} else {
				// If no ClickUp ID is returned, then fail the job
				$queue_status = 'failed';
			}
		}

		// Update queue status
		$DB_Queue::update( $queue_id, $queue_status, $retry_count ++ );

		// Log response
		$DB_Logs = new \WP_ClickUp_Sync\Core\Admin\DB_Logs();
		$DB_Logs::add( $queue_id, $response_code, $response_message, $queue_status );
	}
}

Sync::init();