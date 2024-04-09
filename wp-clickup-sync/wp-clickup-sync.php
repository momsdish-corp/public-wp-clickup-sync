<?php
/**
 * Plugin Name:     X - ClickUp Sync
 * Description:     Notifies ClickUp on post changes.
 * Text Domain:     wp-clickup-sync
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         X
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

define( 'WP_CLICKUP_SYNC_DIR', __DIR__ );
define( 'WP_CLICKUP_SYNC_FILE', __FILE__ );
define( 'WP_CLICKUP_SYNC_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_CLICKUP_SYNC_TEXT_DOMAIN', 'wp-clickup-sync' );
// Since ClickUp usually limits the API calls to 100 per minute, it's better stick to a lower limit to be safe.
if ( ! defined( 'WP_CLICKUP_SYNC_CLICKUP_API_RATE_LIMIT_PER_MINUTE' ) ) {
	define( 'WP_CLICKUP_SYNC_CLICKUP_API_RATE_LIMIT_PER_MINUTE', 60 );
}

// Add a Stylesheet for the Admin
add_action( 'admin_enqueue_scripts', function () {
	wp_enqueue_style( 'wp-clickup-sync-admin', WP_CLICKUP_SYNC_URL . 'assets/css/admin.css', [], '0.1.1' );
} );


// Add Admin Menu Pages
require_once WP_CLICKUP_SYNC_DIR . '/includes/Plugin.php';