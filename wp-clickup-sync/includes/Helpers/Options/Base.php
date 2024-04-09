<?php

namespace WP_ClickUp_Sync\Helpers\Options;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Helper for storing and fetching options
 */
class Base extends \WP_ClickUp_Sync\Helpers\Base {

	private array $options = [];

	/**
	 * Get an option value.
	 *
	 * @param string $option_name Option name.
	 * @param string|false $option_key Option key, if array.
	 * @param string|array|false $default Default return value.
	 *
	 * @return string|array|false
	 */
	public function get( string $option_name, string|false $option_key = false, mixed $default = false ): string|array|false {
		// If the option hasn't been loaded, load the option
		if ( ! isset( $this->options[ $option_name ] ) ) {
			$this->options[ $option_name ] = get_option( $option_name, false );
		}

		// If no key is set, return the entire option value.
		if ( $option_key === false ) {
			return $this->options[ $option_name ];
		}

		// If the key is set and is in array, return the key value. Use default otherwise.
		return $this->options[ $option_name ][ $option_key ] ?? $default;
	}

//	protected function set_options( string $option_name, $option_value ): void {
//		$this->options[ $option_name ] = $option_value;
//	}
//
//	protected function set_option_name( string $option_name ): void {
//		$this->option_name = $option_name;
//	}
//
//	protected function get_option_name(): string {
//		return $this->option_name;
//	}

//	/**
//	 * Save settings
//	 *
//	 * @return void
//	 */
//	protected function save_options(): void {
//		update_option( $this->settings['option_name'], $this->settings['option_values'] );
//	}
}
