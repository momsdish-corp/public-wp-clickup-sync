<?php

namespace WP_ClickUp_Sync;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class Plugin {

	/**
	 * Initialize
	 *
	 * @return void
	 */
	public static function init( ): void {
		// run load_dependencies function
		self::load_dependencies();
	}

	/**
	 * Load dependency classes
	 *
	 * @return void
	 */
	private static function load_dependencies(): void {
		// Helpers
		require_once WP_CLICKUP_SYNC_DIR . '/includes/Helpers/autoload.php';

		// Events that trigger Syncing
		require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Trigger_Events.php';


		// Core/Admin
		if ( is_admin() ) {
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/Add_To_Queue.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/DB_Logs.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/DB_Connections.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/DB_Queue.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/Page_Dashboard.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/Page_Logs.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/Page_Queue.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/Page_Connections.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/Page_Settings.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/Post_Edit.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/Post_Data.php';
			require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Admin/Term_Data.php';
		}

		// Core/Cron
		// require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Cron/Heal.php';
		require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Cron/Purge.php';
		require_once WP_CLICKUP_SYNC_DIR . '/includes/Core/Cron/Sync.php';
	}
}

Plugin::init();