<?php

namespace WP_ClickUp_Sync\Core\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Maps data for Posts and Terms
 */
class Term_Data {

	public object $term;
	private array $options = [
		'entity'   => 'Entity',
		'id'       => 'Term ID',
		'name'     => 'Term name',
		'status'   => 'Term status',
		'slug'     => 'Term slug',
		'view_url' => 'View link',
		'edit_url' => 'Edit link',
	];

	private array $status_map = [
		'active' => 'Active',
		'trash'  => 'Archived',
	];

	public function __construct( $term_id = false ) {
		if ( $term_id ) {
			$this->term = get_term( $term_id );
		}
	}

	public function get_option( $option_name ) {
		return $this->options[ $option_name ] ?? false;
	}

	public function get_options() {
		return $this->options ?? [];
	}

	public function get_value( $option_name ) {
		// Require a term to be set
		if ( ! $this->term ) {
			return false;
		}

		// Require to be set in the options array
		if ( ! isset( $this->options[ $option_name ] ) ) {
			return '';
		}

		$status_map = $this->get_status_map();

		return match ( $option_name ) {
			'entity'   => $this->term,
			'id' => $this->term->term_id,
			'name' => $this->term->name,
			'status' => $status_map['active'],
			'slug' => $this->term->slug,
			'view_url' => get_permalink( $this->term->term_id ),
			'edit_url' => get_edit_term_link( $this->term->term_id ),
			default => '',
		};
	}

	public function get_status_map() {
		return $this->status_map;
	}
}