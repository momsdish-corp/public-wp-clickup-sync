<?php

namespace WP_ClickUp_Sync\Core\Cron;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use JsonException;

class Heal {

	/**
	 * Register actions and filters.
	 */
	public static function init(): void {
		// Schedule cron jobs
		register_activation_hook( WP_CLICKUP_SYNC_FILE, array( __CLASS__, 'on_activation' ) );
		register_deactivation_hook( WP_CLICKUP_SYNC_FILE, array( __CLASS__, 'on_deactivation' ) );

		// Add cron jobs
		add_action( 'wp_clickup_sync_cron_heal', [ __CLASS__, 'do_heal' ] );
	}

	public static function on_activation(): void {
		// Add Cron Heal
		if ( ! wp_next_scheduled( 'wp_clickup_sync_cron_heal' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_clickup_sync_cron_heal' );
		}
	}

	public static function on_deactivation(): void {
		// Remove Cron Heal
		wp_clear_scheduled_hook( 'wp_clickup_sync_cron_heal' );
	}

	/**
	 * Daily 2 way sync with ClickUp to find any dandling items.
	 *
	 * - Loop through all ClickUp Tasks to find any dandling tasks. Move them to status Archive.
	 * TODO: Should we do this:
	 * - Adds all posts and terms to the queue, to resync everything.
	 *
	 * @return void
	 */
	public static function do_heal(): void {
	}
}

Heal::init();