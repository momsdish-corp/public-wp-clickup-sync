<?php

namespace WP_ClickUp_Sync\Helpers\ClickUp_API;

use JsonException;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class Base extends \WP_ClickUp_Sync\Helpers\Base {

	public string $api_endpoint = 'https://api.clickup.com/api/v2/';
	private string|false $api_key = false;
	private \WP_ClickUp_Sync\Helpers\Options\Base $Options;
	private array|object $response = [];

	/**
	 * Base constructor
	 *
	 * @var object{
	 *   user: {$this->fetch_user()},
	 *   team: [
	 *     $team_id: {
	 *       $this->fetch_teams(),
	 *     },
	 *   ],
	 *   space: [
	 *     $space_id: {
	 *       $this->fetch_space( $team_id ),
	 *       folder: [
	 *         $folder_id: {
	 *           $this->fetch_folder( $space_id ),
	 *           list: [
	 *             $list_id: {
	 *               $this->fetch_list( $folder_id ),
	 *             },
	 *           ],
	 *         },
	 *         ],
	 *     },
	 *   ],
	 *   list: [
	 *     $list_id: {
	 *       $this->fetch_list( $list_id ),
	 *       field: [
	 *         $custom_field_id: {
	 *           $this->fetch_custom_fields( $list_id ),
	 *         },
	 *       ],
	 *     },
	 *   ],
	 * }
	 */

	private object $data;

	public function __construct() {
		$this->Options = new \WP_ClickUp_Sync\Helpers\Options\Base();
		$this->data    = (object) [];

		$this->set_api_key();
	}

	/**
	 * Set ClickUp API key
	 *
	 * @return void
	 */
	private function set_api_key(): void {
		// If defined in constant, return it
		if ( defined( 'WP_CLICKUP_SYNC_API_KEY' ) ) {
			$this->api_key = WP_CLICKUP_SYNC_API_KEY;
		}

		if ( ! $this->api_key ) {
			$db_api_key = $this->get_option( 'wp_clickup_sync_clickup_api_key' );
			if ( $db_api_key ) {
				$this->api_key = $db_api_key;
			}
		}
	}

	/**
	 * Get option value
	 *
	 * @param string $option_name
	 * @param string|false $option_key
	 * @param string|array|false $default
	 *
	 * @return string|array|false
	 */
	public function get_option( string $option_name, string|false $option_key = false, mixed $default = false ): string|array|false {
		return $this->Options->get( $option_name, $option_key, $default );
	}

	/**
	 * Fetch authorized account user, i.e. the owner of the API key.
	 * @see https://clickup.com/api/clickupreference/operation/GetAuthorizedUser/
	 *
	 * @return object { id, username, color, profilePicture, email }
	 * @throws JsonException
	 */
	public function fetch_user(): object {
		// Skip if already loaded
		if ( isset( $this->data->user->id ) ) {
			return $this->data->user;
		}

		// Prepare
		if ( ! isset ( $this->data->user ) ) {
			$this->data->user = (object) [];
		}

		// Fetch
		$url      = $this->api_endpoint . 'user';
		$response = $this->request( $url )->get_parsed_response_body();

		// Update data
		if ( isset( $response->user ) ) {
			$this->data->user = $response->user;
		}

		return $this->data->user;
	}

	/**
	 * Fetch authorized teams (workspaces)
	 * @see https://clickup.com/api/clickupreference/operation/GetAuthorizedTeams/
	 *
	 * @return array [ $team_id: { id: $team_id, name, color, avatar, members: array{} } ]
	 * @throws JsonException
	 */
	public function fetch_teams(): array {
		// Build teams array
		isset( $this->data->team ) ?: $this->data->team = [];

		// Skip if already loaded
		if ( isset( $this->data->team[ array_key_first( $this->data->team ) ]->id ) ) {
			return $this->data->team;
		}

		// Prepare
		if ( ! isset( $this->data->teams ) ) {
			$this->data->teams = [];
		}

		// Fetch
		$url      = $this->api_endpoint . 'team';
		$response = $this->request( $url )->get_parsed_response_body();

		// Update data
		if ( isset( $response->teams ) ) {
			foreach ( $response->teams as $team ) {
				// If the object exists, merge it
				if ( isset( $this->data->team[ $team->id ] ) ) {
					$this->data->team[ $team->id ] = (object) array_merge( (array) $this->data->team[ $team->id ], (array) $team );
				} else {
					$this->data->team[ $team->id ] = $team;
				}
			}
		}

		return $this->data->team;
	}

	/**
	 * Fetch spaces
	 * @see https://clickup.com/api/clickupreference/operation/GetSpaces/
	 *
	 * @param string $team_id
	 *
	 * @return array [ $space_id: { id: $space_id, name, color, avatar, members: array{} } ]
	 * @throws JsonException
	 */
	public function fetch_spaces( string $team_id ): array {
		// Build spaces array
		isset( $this->data->team ) ?: $this->data->team = [];
		isset( $this->data->team[ $team_id ]->space ) ?: $this->data->team[ $team_id ]->space = [];

		// Skip if already loaded
		if ( isset( $this->data->team[ $team_id ]->space[ array_key_first( $this->data->team[ $team_id ]->space ) ]->id ) ) {
			return $this->data->team[ $team_id ]->space;
		}

		// Prepare
		if ( ! isset( $this->data->team[ $team_id ]->space ) ) {
			$this->data->team[ $team_id ]->space = [];
		}

		// Fetch
		$url      = $this->api_endpoint . 'team/' . $team_id . '/space';
		$response = $this->request( $url )->get_parsed_response_body();

		// Update data
		if ( isset( $response->spaces ) ) {
			foreach ( $response->spaces as $space ) {
				// If the object exists, merge it
				if ( isset( $this->data->team[ $team_id ]->space[ $space->id ] ) ) {
					$this->data->team[ $team_id ]->space[ $space->id ] = (object) array_merge( (array) $this->data->team[ $team_id ]->space[ $space->id ], (array) $space );
				} else {
					$this->data->team[ $team_id ]->space[ $space->id ] = $space;
				}
			}
		}

		return $this->data->team[ $team_id ]->space;
	}

	/**
	 * Fetch folders
	 * @see https://clickup.com/api/clickupreference/operation/GetFolders/
	 *
	 * @param string $space_id
	 *
	 * @return array [ $folder_id: { id: $folder_id, name, color, avatar, members: array{} } ]
	 * @throws JsonException
	 */
	public function fetch_folders( string $space_id ): array {
		// Build folders array
		isset( $this->data->space ) ?: $this->data->space = [];
		isset( $this->data->space[ $space_id ]->folder ) ?: $this->data->space[ $space_id ]->folder = [];

		// Skip if already loaded
		if ( isset( $this->data->space[ $space_id ]->folder[ array_key_first( $this->data->space[ $space_id ]->folder ) ]->id ) ) {
			return $this->data->space[ $space_id ]->folder;
		}

		// Prepare
		if ( ! isset( $this->data->space[ $space_id ]->folder ) ) {
			$this->data->space[ $space_id ]->folder = [];
		}

		// Fetch
		$url      = $this->api_endpoint . 'space/' . $space_id . '/folder';
		$response = $this->request( $url )->get_parsed_response_body();

		// Update data
		if ( isset( $response->folders ) ) {
			foreach ( $response->folders as $folder ) {
				// If the object exists, merge it
				if ( isset( $this->data->space[ $space_id ]->folder[ $folder->id ] ) ) {
					$this->data->space[ $space_id ]->folder[ $folder->id ] = (object) array_merge( (array) $this->data->space[ $space_id ]->folder[ $folder->id ], (array) $folder );
				} else {
					$this->data->space[ $space_id ]->folder[ $folder->id ] = $folder;
				}
			}
		}

		return $this->data->space[ $space_id ]->folder;
	}

	/**
	 * Fetch the list from ClickUp.
	 * @see https://clickup.com/api/clickupreference/operation/GetList/
	 *
	 * @param string $list_id ID of the list.
	 *
	 * @return object { id: $list_id, name, orderindex, content, status: object{}, priority: object{}, assignee: due_date, due_date_time, folder: object{}, space: object{}, inbound_address:, archived, override_statuses, statuses: array{}, permission_level }
	 * @throws JsonException
	 */
	public function fetch_list( string $list_id ): object {
		// Skip if already loaded
		if ( isset( $this->data->list[ $list_id ]->id ) ) {
			return $this->data->list[ $list_id ];
		}

		// Prepare
		if ( ! isset( $this->data->list ) ) {
			$this->data->list = [];
		}

		// Fetch
		$url      = $this->api_endpoint . 'list/' . $list_id;
		$response = $this->request( $url )->get_parsed_response_body();

		// Update data
		if ( isset( $response->id ) ) {
			// If object exists, merge it
			if ( isset( $this->data->list[ $list_id ] ) ) {
				$this->data->list[ $list_id ] = (object) array_merge( (array) $this->data->list[ $list_id ], (array) $response );
			} else {
				$this->data->list[ $list_id ] = $response;
			}
		}

		return $this->data->list[ $list_id ];
	}

	/**
	 * Fetch custom fields from ClickUp.
	 * @see https://clickup.com/api/clickupreference/operation/GetAccessibleCustomFields/
	 *
	 * @param string $list_id The ID of the list.
	 *
	 * @return array { id: $custom_field_id, name, type, type_config, date_created, hide_from_guests }
	 *   The list data. Returns an array of objects.
	 * @throws JsonException
	 */
	public function fetch_custom_fields( string $list_id ): array {
		// Skip if already loaded
		if ( isset( $this->data->list[ $list_id ]->field ) ) {
			return $this->data->list[ $list_id ]->field;
		}

		// Prepare
		if ( ! isset( $this->data->list[ $list_id ] ) ) {
			$this->data->list[ $list_id ] = (object) [];
		}
		if ( ! isset( $this->data->list[ $list_id ]->field ) ) {
			$this->data->list[ $list_id ]->field = [];
		}

		// Fetch
		$url      = $this->api_endpoint . 'list/' . $list_id . '/field';
		$response = $this->request( $url )->get_parsed_response_body();

		// Update data
		if ( isset( $response->fields ) ) {
			foreach ( $response->fields as $field ) {
				$this->data->list[ $list_id ]->field[ $field->id ] = $field;
			}
		}

		return $this->data->list[ $list_id ]->field;
	}

	/**
	 * Submit request
	 *
	 * @param string $url The URL to submit the request to.
	 * @param string $method The method to use for the request.
	 * @param array $args The arguments for the request.
	 *
	 * @return $this Returns object if the error is on WordPress side.
	 * @throws JsonException
	 */
	public function request( string $url, string $method = 'GET', array $args = [] ): static {
		// Add proper headers
		$args['headers']['Authorization'] = $this->api_key;
		$args['headers']['Content-Type'] = 'application/json';
		if ( isset( $args['body'] ) ) {
			$args['body'] = json_encode( $args['body'], JSON_THROW_ON_ERROR );
		}

		// Submit request
		if ( $method === 'GET' ) {
			$response = wp_remote_get( $url, $args );
		} else if ( $method === 'POST' ) {
			$response = wp_remote_post( $url, $args );
		} else if ( $method === 'PUT' ) {
			$args['method'] = 'PUT';
			$response       = wp_remote_post( $url, $args );
		} else if ( $method === 'DELETE' ) {
			$args['method'] = 'DELETE';
			$response       = wp_remote_post( $url, $args );
		} else {
			$this->response = [];
			return $this;
		}

		// Log errors
		if ( is_wp_error( $response ) ) {
			$this->show_message( 'Error when pushing task changes. Response: ' . $response->get_error_message(), 'error' );
		}

		// Save response
		$this->response = $response;

		return $this;
	}

	/**
	 * Parse and return response body.
	 *
	 * @return array|object|false The response or false on failure.
	 * @throws JsonException
	 */
	public function get_parsed_response_body(): array|object|false {
		// Check if response is valid
		if ( empty( $this->response ) ) {
			return false;
		}

		// Return the parsed response
		return json_decode( wp_remote_retrieve_body( $this->response ), false, 512, JSON_THROW_ON_ERROR );
	}

	/**
	 * Get response
	 *
	 * @return array|object|false The response or false on failure.
	 */
	public function get_response(): array|object|false {
		return $this->response;
	}
}