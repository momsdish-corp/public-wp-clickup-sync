<?php

namespace WP_ClickUp_Sync\Core;

// If this file is called directly, abort.
use JsonException;

defined( 'ABSPATH' ) || exit;

/**
 * On trigger event, add work to the queue table
 */
class Trigger_Events {

	public static bool $is_production;

	/**
	 * Register actions and filters.
	 *
	 * @return void
	 * @throws JsonException
	 */
	public static function init(): void {
		self::$is_production = wp_get_environment_type() === 'production';

		// Require the production environment, to avoid calls from dev environments.
		if ( false === self::$is_production ) {
			return;
		}

		// Add triggers
		self::post_triggers();
		self::term_triggers();
	}

	/**
	 * Post Triggers
	 *
	 * @return void
	 * @throws JsonException
	 */
	private static function post_triggers(): void {
		// On post save
		// - Requires a lower priority, to be able to get $parent_entity_id
//		add_action( 'save_post', function ( $post_id, $post, $update ) {
		add_action( 'wp_after_insert_post', function ( $post_id, $post, $update, $post_before ) {
			// Do not trigger if this is an auto-draft
			if ( 'auto-draft' === $post->post_status ) {
				return;
			}
			// Do not trigger if this is a rewrite/republish.
			// The rewrite/republish feature creates a temporary post on publish, which it then deletes.
			if ( 'dp-rewrite-republish' === $post->post_status ) {
				return;
			}

			$Add_To_Queue = new Admin\Add_To_Queue();
			$Add_To_Queue->entity_changed( 'post', $post, 'wp_after_insert_post', 0, false );
		}, 103, 4 );

		// Removing this trigger, as the post is already triggered above, via wp_after_insert_post
//		// On scheduled publish of post
//		add_action( 'future_to_publish', function ( $post ) {
//			$Add_To_Queue = new Admin\Add_To_Queue();
//			$Add_To_Queue->entity_changed( 'post', $post, 'future_to_publish', 0, [ 'status' ] );
//		}, 103, 1 );

		// On post delete
		add_action( 'after_delete_post', function ( $post_id, $post ) {
			$Add_To_Queue = new Admin\Add_To_Queue();
			$Add_To_Queue->entity_changed( 'post', $post, 'after_delete_post', 0, [ 'status' ] );
		}, 103, 2 );
	}

	/**
	 * Add a trigger on taxonomy term changes
	 *
	 * @return void
	 * @throws JsonException
	 */
	private static function term_triggers(): void {
		// Trigger on term change
		add_action( 'edited_term', function ( $term_id, $tt_id, $taxonomy ) {
			$Add_To_Queue = new Admin\Add_To_Queue();
			$Add_To_Queue->entity_changed( 'term', $term_id, 'edited_term', 0, false, 'Active' );
		}, 10, 3 );

		// Trigger on term delete
		add_action( 'delete_term', function ( $term_id, $tt_id, $taxonomy, $deleted_term ) {
			$Add_To_Queue = new Admin\Add_To_Queue();
			$Add_To_Queue->entity_changed( 'term', $term_id, 'delete_term', 0, false, 'Archived' );
		}, 10, 4 );
	}
}

Trigger_Events::init();