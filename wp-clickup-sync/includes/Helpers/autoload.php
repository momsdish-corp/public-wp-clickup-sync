<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	function ( $class ) {
		if ( str_starts_with( $class, 'WP_ClickUp_Sync\\' ) ) {
			$path = str_replace( 'WP_ClickUp_Sync\\', '\\', $class );
			$path = explode( '\\', $path );
			$file = array_pop( $path );
			$path = implode( '/', $path ) . '/' . $file . '.php';
			$path = realpath( __DIR__ . '/../' . $path );

			if ( file_exists( $path ) ) {
				require_once $path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			}
		}
	}
);
