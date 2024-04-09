<?php

namespace WP_ClickUp_Sync\Core\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use JsonException;

/**
 * Settings page
 * @ref https://codex.wordpress.org/Creating_Options_Pages#Example_.232
 */
class Page_Settings {

	private \WP_ClickUp_Sync\Helpers\ClickUp_API\Base $ClickUp_API;
	private \WP_ClickUp_Sync\Helpers\Settings_Page\Base $Menu_Page;

	/**
	 * Start up
	 * @throws JsonException
	 */
	public function __construct() {
		$this->ClickUp_API = new \WP_ClickUp_Sync\Helpers\ClickUp_API\Base();
		$this->Menu_Page   = new \WP_ClickUp_Sync\Helpers\Settings_Page\Base();

		// Create menu page
		$page = [
			'parent_slug' => WP_CLICKUP_SYNC_TEXT_DOMAIN . '-dashboard',
			'page_title'  => esc_html__( 'Settings', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			'menu_title'  => esc_html__( 'Settings', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			'capability'  => 'manage_options',
			'menu_slug'   => WP_CLICKUP_SYNC_TEXT_DOMAIN . '-settings',
			'position'    => 4,
		];
		$this->Menu_Page->add_submenu_page( $page );

		// Section: Authenticate
		$settings_section = [
			'id'    => 'account-setup',
			'title' => esc_html__( 'Account Setup', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
		];
		$this->Menu_Page->add_settings_section( $settings_section );

		// Field: ClickUp API Key
		$settings_field = [
			'title' => esc_html__( 'ClickUp API Key', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			'args'  => [
				'render'            => 'input-password',
				'option_name'       => 'wp_clickup_sync_clickup_api_key',
				'validate_callback' => [ $this, 'validate_clickup_api_key' ],
				'description'       => esc_html__( 'You can find your API key in your ClickUp profile settings.', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			],
		];
		if ( defined( 'WP_CLICKUP_SYNC_API_KEY' ) ) {
			$settings_field['args'] = [
				'render'      => 'input-text-fixed',
				'value'       => '***',
				'description' => '<div>This has been set by your WP_CLICKUP_SYNC_API_KEY constant.</div>',
			];
		}
		$this->Menu_Page->add_settings_field( $settings_field );

		// Section: Config
		$settings_section = [
			'id'    => 'config',
			'title' => esc_html__( 'Config', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
		];
		$this->Menu_Page->add_settings_section( $settings_section );

		// Field: Requests per minute
		$settings_field = [
			'title' => esc_html__( 'Requests per minute', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			'args'  => [
				'render'            => 'input-number',
				'option_name'       => 'wp_clickup_sync_config',
				'option_key'        => 'requests_per_minute',
				'validate_callback' => [ $this, 'validate_integer' ],
				'description'       => esc_html__( 'Maximum number of requests to make per minute to ClickUp. Default: 1.', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			],
		];
		$this->Menu_Page->add_settings_field( $settings_field );

		// Field: Queue days to retain
		$settings_field = [
			'title' => esc_html__( 'Queue Auto-Purge', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			'args'  => [
				'render'            => 'input-number',
				'option_name'       => 'wp_clickup_sync_config',
				'option_key'        => 'queue_retain_days',
				'validate_callback' => [ $this, 'validate_integer' ],
				'description'       => esc_html__( 'Number of days to keep records in the queue before purging. Excludes active jobs. Default: 30.', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			],
		];
		$this->Menu_Page->add_settings_field( $settings_field );

		// Field: DB_Logs days to retain
		$settings_field = [
			'title' => esc_html__( 'Logs Auto-Purge', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			'args'  => [
				'render'            => 'input-number',
				'option_name'       => 'wp_clickup_sync_config',
				'option_key'        => 'logs_retain_days',
				'validate_callback' => [ $this, 'validate_integer' ],
				'description'       => esc_html__( 'Number of days to retain logs before purging. Default: 30.', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			],
		];
		$this->Menu_Page->add_settings_field( $settings_field );

		// Section: Post Types
		$settings_section = [
			'id'    => 'post-connections',
			'title' => esc_html__( 'Connect Posts', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
			'args'  => [
				'before_section' => '<p>' . esc_html__( 'Select post types.', WP_CLICKUP_SYNC_TEXT_DOMAIN ) . '</p>',
			],
		];
		$this->Menu_Page->add_settings_section( $settings_section );

		// Subsections & Fields: Post Types
		$this->add_post_connections_section();

		// Section: Taxonomies
		$settings_section = [
			'id'    => 'term-connections',
			'title' => esc_html__( 'Connect Terms', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
		];
		$this->Menu_Page->add_settings_section( $settings_section );

		// Subsections & Fields: Taxonomies
		$this->add_term_connections_section();

		$this->Menu_Page->render();
	}

	/**
	 * Get Settings Fields for Posts
	 *
	 * @return void
	 * @throws JsonException
	 */
	private function add_post_connections_section(): void {
		global $wp_post_types;

		// For each post type
		foreach ( $wp_post_types as $post_type ) {
			// Set ClickUp List ID option values
			$option_name = 'wp_clickup_sync_post_connections';
			$option_key  = $post_type->name . '__list_id';

			// Get current setting
			$list_id = $this->Menu_Page->get_option( $option_name, $option_key, '' );

			// Set subsection values
			$subsection_id    = $post_type->name;
			$subsection_title = $list_id ? $post_type->label . ' (active)' : $post_type->label;

			// Create a subsection
			$settings_subsection = [
				'id'    => $subsection_id,
				'title' => $subsection_title,
			];
			$this->Menu_Page->add_settings_subsection( $settings_subsection );

			// Render input field
			$field = [
				'id'         => $option_key,
				'title'      => esc_html__( 'List ID', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
				'subsection' => $subsection_id,
				'args'       => [
					'render'            => 'input-text',
					'option_name'       => $option_name,
					'option_key'        => $option_key,
					'validate_callback' => [ $this, 'validate_list_id' ],
				],
			];
			$this->Menu_Page->add_settings_field( $field );

			// Set ClickUp List ID option values
			if ( ! empty( $list_id ) ) {

				$entity_data = new Post_Data();

//				// Render status field
//				$field = [
//					'id'         => $option_key,
//					'title'      => esc_html__( 'Status', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
//					'subsection' => $subsection_id,
//					'args'       => [
//						'render'                 => 'text',
//						'value'                  => '',
//						'validate_callback'      => [ $this, 'validate_status_field' ],
//						'validate_callback_args' => [
//							'list_id'          => $list_id,
//							'allowed_statuses' => $entity_data->get_status_map(),
//						],
//					],
//				];
//				$this->Menu_Page->add_settings_field( $field );

				// Get all available custom fields from ClickUp
				// Require current section before fetching, to prevent unnecessary API calls
				$custom_fields = 'post-connections' === $this->Menu_Page->active_section ? $this->ClickUp_API->fetch_custom_fields( $list_id ) : [];

				// Build dropdown options
				$available_values = [];
				foreach ( $custom_fields as $custom_field ) {
					$allowed_types = [ 'url', 'date', 'short_text', 'number', 'drop_down' ];
					if ( ! in_array( $custom_field->type, $allowed_types, true ) ) {
						continue;
					}

					$values = [
						'id'    => $custom_field->id,
						'type' => $custom_field->type,
					];

					// Dropdowns
					if ( 'drop_down' === $custom_field->type &&  isset($custom_field->type_config, $custom_field->type_config->options ) ) {
						$values['options'] = [];
						foreach ( $custom_field->type_config->options as $option ) {
							$values['options'][] = [
								'id'    => $option->id,
								'value' => $option->name,
							];
						}
					}

					$available_values[ htmlspecialchars( json_encode( $values, JSON_THROW_ON_ERROR ), ENT_QUOTES, 'UTF-8' ) ] = (string) $custom_field->name;
				}

				foreach ( $entity_data->get_options() as $field_name => $field_label ) {
					if ( 'title' === $field_name ) {
						continue;
					}

					$option_key = $post_type->name . '__' . $field_name;

					$field = [
						'id'         => $option_key,
						'title'      => $field_label . ' <code>' . $field_name . '</code>',
						'subsection' => $subsection_id,
						'args'       => [
							'render'                 => 'select-optional',
							'option_name'            => $option_name,
							'option_key'             => $option_key,
							'default_value'          => '',
							'available_values'       => $available_values,
							'validate_callback'      => [ $this, 'validate_custom_fields' ],
							'validate_callback_args' => [
								'list_id' => $list_id,
							],
						],
					];
					$this->Menu_Page->add_settings_field( $field );
				}
			}
		}
	}

	/**
	 * Get Settings Fields for Terms
	 *
	 * @return void
	 * @throws JsonException
	 */
	private function add_term_connections_section(): void {
		global $wp_taxonomies;

		// Do for each taxonomy
		foreach ( $wp_taxonomies as $taxonomy ) {
			// Set ClickUp List ID option values
			$option_name = 'wp_clickup_sync_term_connections';
			$option_key  = $taxonomy->name . '__list_id';

			// Get current setting
			$list_id = $this->Menu_Page->get_option( $option_name, $option_key, '' );

			// Set Subsection values
			$subsection_id    = $taxonomy->name;
			$subsection_title = $list_id ? $taxonomy->label . ' (active)' : $taxonomy->label;

			// Create a Subsection
			$settings_subsection = [
				'id'    => $subsection_id,
				'title' => $subsection_title,
			];
			$this->Menu_Page->add_settings_subsection( $settings_subsection );

			// Render input field
			$field = [
				'id'         => $option_key,
				'title'      => ' ' . esc_html__( 'List ID', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
				'subsection' => $subsection_id,
				'args'       => [
					'render'            => 'input-text',
					'option_name'       => $option_name,
					'option_key'        => $option_key,
					'validate_callback' => [ $this, 'validate_list_id' ],
				],
			];
			$this->Menu_Page->add_settings_field( $field );

			// Set ClickUp List ID option values
			if ( ! empty( $list_id ) ) {

				$entity_data = new Term_Data();

//				// Render status field
//				$field = [
//					'id'         => $option_key,
//					'title'      => esc_html__( 'Status', WP_CLICKUP_SYNC_TEXT_DOMAIN ),
//					'subsection' => $subsection_id,
//					'args'       => [
//						'render'                 => 'text',
//						'value'                  => '',
//						'validate_callback'      => [ $this, 'validate_status_field' ],
//						'validate_callback_args' => [
//							'list_id'          => $list_id,
//							'allowed_statuses' => $entity_data->get_status_map(),
//						],
//					],
//				];
//				$this->Menu_Page->add_settings_field( $field );

				// Get all available custom fields from ClickUp
				// Require current section before fetching, to prevent unnecessary API calls
				$custom_fields = 'term-connections' === $this->Menu_Page->active_section ? $this->ClickUp_API->fetch_custom_fields( $list_id ) : [];

				// Build dropdown options
				$available_values = [];
				foreach ( $custom_fields as $custom_field ) {
					$allowed_types = [ 'url', 'date', 'short_text', 'number', 'drop_down' ];
					if ( ! in_array( $custom_field->type, $allowed_types, true ) ) {
						continue;
					}

					$values = [
						'id'    => $custom_field->id,
						'type' => $custom_field->type,
					];

					// Dropdowns
					if ( 'drop_down' === $custom_field->type &&  isset($custom_field->type_config, $custom_field->type_config->options ) ) {
						$values['options'] = [];
						foreach ( $custom_field->type_config->options as $option ) {
							$values['options'][] = [
								'id'    => $option->id,
								'value' => $option->name,
							];
						}
					}

					$available_values[ htmlspecialchars( json_encode( $values, JSON_THROW_ON_ERROR ), ENT_QUOTES, 'UTF-8' ) ] = (string) $custom_field->name;
				}

				foreach ( $entity_data->get_options() as $field_name => $field_label ) {
					if ( 'name' === $field_name ) {
						continue;
					}

					$option_key = $taxonomy->name . '__' . $field_name;

					$field = [
						'id'         => $option_key,
						'title'      => $field_label,
						'subsection' => $subsection_id,
						'args'       => [
							'render'                 => 'select-optional',
							'option_name'            => $option_name,
							'option_key'             => $option_key,
							'default_value'          => '',
							'available_values'       => $available_values,
							'validate_callback'      => [ $this, 'validate_custom_fields' ],
							'validate_callback_args' => [
								'list_id' => $list_id,
							],
						],
					];
					$this->Menu_Page->add_settings_field( $field );
				}
			}
		}
	}

	/**
	 * Validate clickup api key. Make sure it's working.
	 *
	 * @param string $field_id Field ID
	 * @param string $field_value Field value
	 * @param array $args Field arguments
	 *
	 * @return string|array
	 * @throws JsonException
	 */
	public function validate_clickup_api_key( string $field_id, string $field_value, array $args ): string|array {
		$clickup_user = $this->ClickUp_API->fetch_user();

		if ( isset( $clickup_user->email ) ) {
			return [
				'status'  => 'success',
				'message' => 'Connected to <strong>' . $clickup_user->email . '</strong>'
			];
		}

		return [ 'status' => 'error', 'message' => 'Connection failed' ];
	}

	/**
	 * Validate the ClickUp List ID. Make sure it's working.
	 *
	 * @param string $field_id Field ID
	 * @param string $field_value Field value
	 * @param array $args Field arguments
	 *
	 * @return string|array
	 * @throws JsonException
	 */
	public function validate_list_id( string $field_id, string $field_value, array $args ): string|array {
		// Require field value
		if ( empty( $field_value ) ) {
			return false;
		}

		$clickup_list = $this->ClickUp_API->fetch_list( $field_value );

		if ( isset( $clickup_list->name ) ) {
			return [
				'status'  => 'success',
				'message' => 'Connected to <strong>' . $clickup_list->name . '</strong>'
			];
		}

		return [ 'status' => 'error', 'message' => 'Connection failed' ];
	}

	/**
	 * Validate the ClickUp Custom Fields. Make sure they're working.
	 *
	 * @param string $field_id Field ID
	 * @param string $field_value Field value
	 * @param array $args Field arguments
	 *
	 * @return string|array
	 * @throws JsonException
	 */
	public function validate_custom_fields( string $field_id, string $field_value, array $args ): string|array {
		/** @noinspection JsonEncodingApiUsageInspection */
		$field_values    = json_decode( $field_value, true, 512 );
		$custom_field_id = $field_values['id'] ?? '';
		$field_type      = $field_values['type'] ?? '';
		$field_options   = $field_values['options'] ?? [];

		// Require field value to include $custom_field_id and $field_type
		if ( empty( $custom_field_id ) || empty( $field_type ) ) {
			return false;
		}

		// Require List ID
		if ( empty( $args['list_id'] ) ) {
			return false;
		}
		$list_id = $args['list_id'];

		$clickup_custom_fields = $this->ClickUp_API->fetch_custom_fields( $list_id );

		if ( isset( $clickup_custom_fields[ $custom_field_id ]->id ) ) {
			if ( $clickup_custom_fields[ $custom_field_id ]->type !== $field_type ) {
				$message = 'Field type mismatch. Expecting field type <strong>' . $clickup_custom_fields[ $custom_field_id ]->type . '</strong> </code>' . $custom_field_id . '</code>, but got <strong>' . $field_type . '</strong>';
				return [
					'status'  => 'error',
					'message' => $message,
				];
			}
			$message = 'Connected to <strong>' . $clickup_custom_fields[ $custom_field_id ]->name . '</strong> <code>' . $custom_field_id . '</code> with type <strong>' . $clickup_custom_fields[ $custom_field_id ]->type . '</strong>';

			if ( $field_options ) {
				$options = [];
				foreach ($field_options as $field_option) {
					$options[] = '<strong>' . $field_option['value'] . '</strong> <code>' . $field_option['id'] . '</code>';
				}

				$message .= ' with options ' . implode( ', ', $options );
			}

			return [
				'status'  => 'success',
				'message' => $message,
			];
		}

		return [ 'status' => 'error', 'message' => 'Connection failed' ];
	}

	/**
	 * Validate requests per minute.
	 *
	 * @param string $field_id Field ID
	 * @param string $field_value Field value
	 * @param array $args Field arguments
	 *
	 * @return string|array
	 */
	public function validate_integer( string $field_id, string $field_value, array $args ): string|array {
		// Require field value
		if ( empty( $field_value ) ) {
			return false;
		}

		// Require $field_value to be an integer
		if ( ! is_numeric( $field_value ) ) {
			return [
				'status'  => 'error',
				'message' => 'Value must be a valid integer'
			];
		}

		return false;
	}
}

$page_settings = new Page_Settings();
