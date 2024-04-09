<?php

namespace WP_ClickUp_Sync\Helpers\ClickUp_API;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Get API queries for specific actions
 */
class Query extends Base {

	/**
	 * Create a ClickUp task.
	 *
	 * @param string $list_id
	 *     ID of the list to which the task will be added.
	 * @param string $name
	 *     Name of the task
	 * @param array $custom_fields Optional
	 *     array{ id: string, value: string }
	 *     Custom fields to add to the task
	 *
	 * @return array
	 */
	public function create_task( string $list_id, string $name = '', array $custom_fields = [] ): array {
		// TODO - currently ClickUp does not support additional settings in $custom_fields, therefore things like Date, will likely not get adjusted to the timezone of the user until a separate call is made to update the custom field
		$output = [
			'url'    => $this->api_endpoint . 'list/' . $list_id . '/task',
			'method' => 'POST',
			'body'   => [
				'name' => $name,
			],
		];

		// If custom fields are set, add them to the body.
		$output['body']['custom_fields'] = [];
		foreach ( $custom_fields as $custom_field ) {
			$output['body']['custom_fields'][] = [
				'id'    => $custom_field['id'],
				'value' => $custom_field['value'],
			];
		}

		return $output;
	}

	/**
	 * Update the ClickUp task.
	 * Note: Custom fields must be updated separately.
	 *
	 * @param string $task_id
	 *     ID of the task to update.
	 * @param string $name
	 *     Name of the task

	 * @return array
	 */
	public function update_task( string $task_id, string $name ): array {
		$output = [
			'url'    => $this->api_endpoint . 'task/' . $task_id,
			'method' => 'PUT',
			'body'   => [
				'name' => $name,
			],
		];

		return $output;
	}

	/**
	 * Update the ClickUp task custom fields.
	 *
	 * @param string $task_id
	 *     ID of the task to update.
	 * @param string $custom_field_id
	 *     ID of the custom field to update
	 * @param string $custom_field_value
	 *     Value of the custom field
	 * @param string $custom_field_type Optional
	 *     Type of the custom field
	 *
	 * @return array
	 */
	public function update_custom_field( string $task_id, string $custom_field_id, string $custom_field_value, string $custom_field_type ): array {
		// TODO: Add support for other types later.
		// Currently not supporting Task Relationship, People, Emoji, Manual Progress, Label, and Location
		$body = match ( $custom_field_type ) {
			'date' => [
				'value'         => (int) $custom_field_value,
				'value_options' => [
					'time' => true,
				],
			],
			'number', 'money' => [
				'value' => (int) $custom_field_value,
			],
			default => [
				'value' => (string) $custom_field_value,
			],
		};

		return [
			'url'    => $this->api_endpoint . 'task/' . $task_id . '/field/' . $custom_field_id,
			'method' => 'POST',
			'body'   => $body,
		];
	}

	/**
	 * Delete a ClickUp task.
	 *
	 * @param string $task_id
	 *
	 * @return array
	 */
	public function delete_task( string $task_id ): array {
		return [
			'url'    => $this->api_endpoint . 'task/' . $task_id,
			'method' => 'DELETE',
		];
	}
}