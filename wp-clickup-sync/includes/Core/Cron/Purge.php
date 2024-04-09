<?php

namespace WP_ClickUp_Sync\Core\Cron;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use JsonException;

class Purge {
	/**
	 * Register actions and filters.
	 */
	public static function init(): void {
		// Schedule cron jobs
		register_activation_hook( WP_CLICKUP_SYNC_FILE, array( __CLASS__, 'on_activation' ) );
		register_deactivation_hook( WP_CLICKUP_SYNC_FILE, array( __CLASS__, 'on_deactivation' ) );

		// Add cron jobs
		add_action( 'wp_clickup_sync_cron_purge', [ __CLASS__, 'do_purge' ] );
	}

	public static function on_activation(): void {
		// Add Cron Purge
		if ( ! wp_next_scheduled( 'wp_clickup_sync_cron_purge' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_clickup_sync_cron_purge' );
		}
	}

	public static function on_deactivation(): void {
		// Remove Cron Purge
		wp_clear_scheduled_hook( 'wp_clickup_sync_cron_purge' );
	}

	/**
	 * Purge the queue.
	 *
	 * @return void
	 */
	public static function do_purge(): void {
		// Get settings for days retained
		$Options        = new \WP_ClickUp_Sync\Helpers\Options\Base();
		$queue_max_days = $Options->get( 'wp_clickup_sync_config', 'queue_retain_days', 30 );

		// Purge Queue db items
		\WP_ClickUp_Sync\Core\Admin\DB_Queue::purge( $queue_max_days );

		// Get settings for days retained
		$Options       = new \WP_ClickUp_Sync\Helpers\Options\Base();
		$logs_max_days = $Options->get( 'wp_clickup_sync_config', 'logs_retain_days', 30 );

		// Purge Logs db items
		\WP_ClickUp_Sync\Core\Admin\DB_Logs::purge( $logs_max_days );
	}
}

Purge::init();