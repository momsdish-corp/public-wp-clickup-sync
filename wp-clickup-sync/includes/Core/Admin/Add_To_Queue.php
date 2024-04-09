<?php

namespace WP_ClickUp_Sync\Core\Admin;

// If this file is called directly, abort.

defined( 'ABSPATH' ) || exit;

use JsonException;

/**
 * On trigger event, add work to the queue table
 */
class Add_To_Queue {

	// Either a post or term object
	public object $entity;

	/**
	 * Updated by $this->get_entity_data()
	 */
	private array $entity_data_index = [
		'post' => [],
		'term' => [],
	];

	private \WP_ClickUp_Sync\Helpers\Options\Base $Options;

	public function __construct() {
		// Define classes
		$this->Options = new \WP_ClickUp_Sync\Helpers\Options\Base();
	}

	/**
	 * Fire on post or term change. This function looks decides if this needs to be sent to work queue and adds the necessary.
	 *
	 * @param string $entity_type Type of entity, e.g. 'post' or 'term'.
	 * @param object|int $entity Entity object, or id of the post or term.
	 * @param string $event_trigger name of the WP hook that triggered the request. For manual triggers, begin with 'manual_'.
	 * @param int $priority Priority of the job. Default is 0.
	 * @param false|array $limit_to_fields Whether to limit syncing to specific custom fields. Default is false.
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function entity_changed( string $entity_type, object|int $entity, string $event_trigger, int $priority = 0, false|array $limit_to_fields = false ): void {
		if ( ! isset( $this->Options ) ) {
			$this->Options = new \WP_ClickUp_Sync\Helpers\Options\Base();
		}

		if ( $entity_type !== 'post' && $entity_type !== 'term' ) {
			return;
		}

		// Load the entity object
		if ( ! is_object( $entity ) ) {
			$entity = $entity_type === 'post' ? get_post( $entity ) : get_term( $entity );
		}

		// Build option values
		$option_name  = $entity_type === 'post' ? 'wp_clickup_sync_post_connections' : 'wp_clickup_sync_term_connections';
		$option_value = $entity_type === 'post' ? $entity->post_type . '__list_id' : $entity->taxonomy . '__list_id';

		// Get post settings
		$list_id = $this->Options->get( $option_name, $option_value, false );

		// If current term type is not associated with ClickUp ID, exit
		if ( ! $list_id ) {
			return;
		}

		$this->add_to_queue( $entity_type, $entity, $event_trigger, $list_id, $priority, $limit_to_fields );
	}

	/**
	 * Create a job. Send work through $this->entity_changed() function.
	 *
	 * @param string $entity_type Type of entity, e.g. 'post' or 'term'.
	 * @param object $entity Entity object, post or term.
	 * @param string $event_trigger name of the WP hook that triggered the request.
	 * @param string $list_id ClickUp list ID to sync to.
	 * @param int $priority Priority of the job. Default is 0.
	 * @param false|array $limit_to_fields Whether to limit syncing to specific custom fields. Default is false.
	 *
	 * @return void
	 * @throws JsonException
	 */
	private function add_to_queue( string $entity_type, object $entity, string $event_trigger, string $list_id, int $priority = 0, false|array $limit_to_fields = false ): void {
		// Require $entity_type to be 'post' or 'term'
		if ( ! in_array( $entity_type, [ 'post', 'term' ], true ) ) {
			return;
		}

		// Prepare variables
		$DB_Queue  = new DB_Queue();
		$entity_id = $entity_type === 'post' ? $entity->ID : $entity->term_id;

		// Get entity data
		$entity_data        = $this->get_entity_data( $entity_type, $entity );
		// Require entity data
		if ( ! $entity_data ) {
			return;
		}
		$entity_id          = $entity_data['id'];
		$entity             = $entity_data['entity'];
		$task_name          = $entity_data['name'];
		$task_custom_fields = $entity_data['custom_fields'] ?? [];

		// Get task id from wp_clickup_sync_connections table
		$DB_Connections = new DB_Connections();
		$row            = $DB_Connections::get( $entity_type, $entity_id );
		$task_id        = $row->clickup_task_id ?? false;

		// Cancel any previous queues
		$DB_Queue::cancel_upcoming_jobs_by_entity( $entity_type, $entity_id );

		// Build query
		$Query = new \WP_ClickUp_Sync\Helpers\ClickUp_API\Query();

		// If task id is not set or empty, create a new task
		$query = [];
		if ( false === $task_id || '' === $task_id ) {
			// Create a new task.
			// Only if it's not on post delete. For example, if a Rewrite & Republish is being deleted,
			// there is no way to tell that it's a Rewrite & Republish, because the post-meta is
			// already deleted. In that case, we don't want to create a new task.
			if ( 'after_delete_post' !== $event_trigger ) {
				$query[] = $Query->create_task( $list_id, $task_name, $task_custom_fields );
			}
		} else {
			// Update existing task
			$query[] = $Query->update_task( $task_id, $task_name );
			// Update custom fields (When updating a task, ClickUp API requires custom fields to be individually updated)
			foreach ( $task_custom_fields as $custom_field ) {
				// If limit fields to sync is set, only update those fields
				$field_name = $custom_field['name'] ?? '';
				if ( is_array( $limit_to_fields ) && $field_name && ! in_array( $field_name, $limit_to_fields, true ) ) {
					continue;
				}
				$custom_field_id    = $custom_field['id'];
				$custom_field_value = $custom_field['value'];
				$custom_field_type  = $custom_field['type'];
				$query[]            = $Query->update_custom_field( $task_id, $custom_field_id, $custom_field_value, $custom_field_type );
			}
		}

		// Boost tasks that are updating only one custom field, such as status
		if ( is_array( $limit_to_fields ) && count( $limit_to_fields ) <= 1 ) {
			$priority ++;
		}
		// Assign yet another priority point to the new tasks, since it's important to update the Connections DB quicker, and it still takes just one query
		if ( ! $task_id ) {
			$priority ++;
		}

		// Update the queue
		foreach ( $query as $query_item ) {
			$request_url    = $query_item['url'];
			$request_method = $query_item['method'];
			$request_args   = $query_item['body'];

			$DB_Queue::add( $entity_type, $entity_id, $request_url, $request_method, $request_args, $event_trigger, $priority );
		}
	}

	/**
	 * Get entity data
	 *
	 * @param string $entity_type Type of entity, e.g. 'post' or 'term'.
	 * @param object $entity Entity object, post or term.
	 *
	 * @return array Task data array
	 *     array{ name: string, custom_fields: array }
	 *         custom_fields: array{ id: string, value: string }
	 */
	private function get_entity_data( string $entity_type, object $entity ): array {
		if ( ! isset( $this->Options ) ) {
			$this->Options = new \WP_ClickUp_Sync\Helpers\Options\Base();
		}

		$entity_id = $entity_type === 'post' ? $entity->ID : $entity->term_id;

		if ( empty( $this->entity_data_index[ $entity_type ][ $entity_id ] ) ) {
			if ( 'post' === $entity_type ) {
				// Require the current entity to exist
				// This is to prevent errors when a post is deleted, but the queue is not cleared yet
				// Example: Duplicate Post plugin deletes the Rewrite & Republish post on publish (post_save hook), which gets
				// triggered before this workflow, causing the queue to be created, but the post is deleted before the queue.
				if ( ! get_post( $entity->ID ) ) {
					return [];
				}

				// Get post data
				$Post_Data                    = new Post_Data( $entity );
				$entity_data                  = [];
				$entity_data['entity']        = $Post_Data->get_value( 'entity' );
				$entity_data['id']            = $Post_Data->get_value( 'id' );
				$entity_id                    = $entity_data['id'];
				$entity_data['name']          = $Post_Data->get_value( 'title' );
				$entity_data['custom_fields'] = [];

				// Get custom fields
				foreach ( $Post_Data->get_options() as $option_id => $option_label ) {
					// If the Post Data option has been linked to a ClickUp custom field id
					$custom_field_object = $this->Options->get( 'wp_clickup_sync_post_connections', $entity_type . '__' . $option_id );

					/** @noinspection JsonEncodingApiUsageInspection */
					$field_values    = json_decode( $custom_field_object, true, 512 );
					$custom_field_id = $field_values['id'] ?? '';
					$field_type      = $field_values['type'] ?? '';
					$field_options   = $field_values['options'] ?? [];

					// Require field value to include $custom_field_id and $field_type
					if ( empty( $custom_field_id ) || empty( $field_type ) ) {
						continue;
					}

					$custom_field_value = $Post_Data->get_value( $option_id );

					// If custom field value is not false. This allows us to return Conditional Values.
					if ( false !== $custom_field_value ) {
						// If the field type is drop_down, replace the value by the clickup dropdown option id
						if ( 'drop_down' === $field_type ) {
							// If the custom field is a dropdown, get the option label
							$dropdown_option_id = false;
							foreach ( $field_options as $field_option ) {
								// Match case-insensitive
								if ( strtolower( $field_option['value'] ) === strtolower( $custom_field_value ) ) {
									$dropdown_option_id = $field_option['id'];
									break;
								}
							}

							// If the option id was not found, skip this custom field
							if ( false === $dropdown_option_id ) {
								continue;
							}

							$custom_field_value = $dropdown_option_id;
						}

						// Add the custom field to the task
						$entity_data['custom_fields'][] = [
							'id'    => (string) $custom_field_id,
							'value' => (string) $custom_field_value,
							'type'  => (string) $field_type,
							'name'  => (string) $option_id,
						];
					}
				}
				$this->entity_data_index[ $entity_type ][ $entity_id ] = $entity_data;
			} elseif ( 'term' === $entity_type ) {
				// Get term data
				$Term_Data                    = new Term_Data( $entity );
				$entity_data                  = [];
				$entity_data['entity']        = $Term_Data->get_value( 'entity' );
				$entity_data['id']            = $Term_Data->get_value( 'id' );
				$entity_id                    = $entity_data['id'];
				$entity_data['name']          = $Term_Data->get_value( 'name' );
				$entity_data['custom_fields'] = [];

				// Get custom fields
				foreach ( $Term_Data->get_options() as $option_id => $option_label ) {
					// If the Term Data option has been linked to a ClickUp custom field id
					$custom_field_object = $this->Options->get( 'wp_clickup_sync_term_connections', $entity_type . '__' . $option_id );

					/** @noinspection JsonEncodingApiUsageInspection */
					$field_values    = json_decode( $custom_field_object, true, 512 );
					$custom_field_id = $field_values['id'] ?? '';
					$field_type      = $field_values['type'] ?? '';
					$field_options   = $field_values['options'] ?? [];

					// Require field value to include $custom_field_id and $field_type
					if ( empty( $custom_field_id ) || empty( $field_type ) ) {
						continue;
					}

					$custom_field_value = $Term_Data->get_value( $option_id );

					// If custom field value is not false. This allows us to return Conditional Values.
					if ( false !== $custom_field_value ) {
						// If the field type is drop_down, replace the value by the clickup dropdown option id
						if ( 'drop_down' === $field_type ) {
							// If the custom field is a dropdown, get the option label
							$dropdown_option_id = false;
							foreach ( $field_options as $field_option ) {
								// Match case-insensitive
								if ( strtolower( $field_option['value'] ) === strtolower( $custom_field_value ) ) {
									$dropdown_option_id = $field_option['id'];
									break;
								}
							}

							// If the option id was not found, skip this custom field
							if ( false === $dropdown_option_id ) {
								continue;
							}

							$custom_field_value = $dropdown_option_id;
						}

						// Add the custom field to the task
						$entity_data['custom_fields'][] = [
							'id'    => (string) $custom_field_id,
							'value' => (string) $custom_field_value,
							'type'  => (string) $field_type,
							'name'  => (string) $option_id,
						];
					}
				}
				$this->entity_data_index[ $entity_type ][ $entity_id ] = $entity_data;
			}
		}

		return $this->entity_data_index[ $entity_type ][ $entity_id ];
	}
}