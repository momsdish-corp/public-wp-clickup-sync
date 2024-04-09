<?php

namespace WP_ClickUp_Sync\Core\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class Post_Edit {

	// Post id of the post, or if it's a Republish, the original post id
	private static array $real_post_id = [];

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post', [ __CLASS__, 'save_meta_box' ], 10, 2 );
	}

	public static function add_meta_boxes(): void {
		add_meta_box( 'wp-clickup-sync', 'ClickUp Sync', [ __CLASS__, 'clickup_meta_box' ], 'post', "side", "default" );
	}

	private static function get_real_post_id( $post ): int {
		if ( ! isset( self::$real_post_id[ $post->ID ] ) ) {
			$Post_Data                       = new Post_Data( $post );
			self::$real_post_id[ $post->ID ] = $Post_Data->is_republish_get_original_post_id() ?: $post->ID;
		}

		return self::$real_post_id[ $post->ID ];
	}


	public static function clickup_meta_box( $post ): void {
		// Generate text input form with label and link to ClickUp task (in new tab)
		echo '<div class="clickup-sync-meta-box">';

		// Require the post to first have been saved and reloaded. This assures that the generated input values are loaded.
		// - New posts do not have the $_GET['post'] value set.
		// - Posts that were saved, but not reloaded, will not match $post->ID to $_GET['post'], since $post->ID is an
		//   id of the temporary (auto-draft) post.
		$post_was_saved_and_reloaded = isset( $_GET['post'] ) && $post->ID === (int) $_GET['post'] ?? false;

		if ( $post_was_saved_and_reloaded ) {
			// If post is a Republish, show the box from the parent
			$real_post_id = self::get_real_post_id( $post );

			$DB_Connections  = new DB_Connections();
			$row             = $DB_Connections::get( $post->post_type, $real_post_id );
			$clickup_task_id = $row->clickup_task_id ?? '';

			// <input type="text" name="clickup_task_id" value="$clickup_task_id">
			wp_nonce_field( 'wp-clickup-sync-meta-box', 'wp-clickup-sync-meta-box-nonce' );
			$label = $clickup_task_id ? '<a href="https://app.clickup.com/t/' . esc_attr( $clickup_task_id ) . '" target="_blank">ClickUp Task</a>' : 'ClickUp Task';
			echo '<label for="wp-clickup-sync-clickup-task-id">' . $label . '</label>';
			echo '<input type="hidden" name="wp-clickup-sync-clickup-task-id-original" value="' . esc_attr( $clickup_task_id ) . '">';
			echo '<input type="text" name="wp-clickup-sync-clickup-task-id" value="' . esc_attr( $clickup_task_id ) . '" class="regular-text">';
		} else {
			echo '<div class="content-message">Save & reload the post to show the generated task ID.</div>';
		}

		echo '</div>';
	}

	public static function save_meta_box( $post_id, $post ) {
		// If post is a Republish, get the data from the parent
		$real_post_id = self::get_real_post_id( $post );

		if ( ! isset( $_POST['wp-clickup-sync-meta-box-nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_REQUEST['wp-clickup-sync-meta-box-nonce'], 'wp-clickup-sync-meta-box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['post_type'] ) || 'post' !== $_POST['post_type'] ) {
			return;
		}

		if ( isset( $_POST['wp-clickup-sync-clickup-task-id-original'], $_POST['wp-clickup-sync-clickup-task-id'] ) ) {
			$posted_clickup_task_id_original = sanitize_text_field( $_POST['wp-clickup-sync-clickup-task-id-original'] );
			$posted_clickup_task_id          = sanitize_text_field( $_POST['wp-clickup-sync-clickup-task-id'] );

			// If the field was not changed, do nothing
			if ( $posted_clickup_task_id_original === $posted_clickup_task_id ) {
				return;
			}

			$DB_Connections  = new DB_Connections();
			$row             = $DB_Connections::get( 'post', $real_post_id );
			$clickup_task_id = $row->clickup_task_id ?? '';

			// If the task ID in DB is the same as the one posted, do nothing
			if ( $posted_clickup_task_id === $clickup_task_id ) {
				return;
			}

			if ( ! $row ) {
				$DB_Connections::add( 'post', $real_post_id, $posted_clickup_task_id );
			} else {
				$DB_Connections::update( 'post', $real_post_id, $posted_clickup_task_id );
			}
		}
	}
}

Post_Edit::init();
