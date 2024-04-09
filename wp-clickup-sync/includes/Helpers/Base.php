<?php

namespace WP_ClickUp_Sync\Helpers;

use JsonException;

defined( 'ABSPATH' ) || exit;

/**
 * Core functionality
 */

class Base {

	/**
	 * Display an error message
	 *
	 * @param string $message The error message.
	 * @param string $type Type of message.
	 *   Options: 'info'|'error'|'warning'|'success'
	 *
	 * @return void
	 */
	protected function show_message( string $message, string $type ): void {
		echo '<div class="notice notice-{$type}"><p>' . $message . '</p></div>';
	}

	/**
	 * Log error
	 *
	 * @param string $message The error message.
	 */
	protected function log_error( string $message ): void {
		$error_message = 'Warning! [ X ClickUp Sync plugin ] ';

		// Add the remaining message
		$error_message .= $message;

		error_log( $error_message );
	}

}