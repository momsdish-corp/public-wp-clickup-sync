<?php

namespace WP_ClickUp_Sync\Core\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use DateTime;

/**
 * Maps data for Posts and Terms
 *
 * Note: Functions, requiring editing/admin permissions, will not render correctly on cron (such as future publish).
 *       That's because cron runs as anonymous user.
 */
class Post_Data {

	public object $post;

	public int|false|null $is_republish_get_original_post_id = null;

	public int|false|null $has_republish_get_republish_post_id = null;

	private array $options = [
		'entity'         => 'Entity',
		'id'             => 'Post ID',
		'title'          => 'Post title',
		'slug'           => 'Post slug',
		'status'         => 'Post status',
		'view_url'       => 'View link',
		'edit_url'       => 'Edit link',
		'author'         => 'Author',
		'type'           => 'Post type',
		'date'           => 'Published date',
		'original_date'  => 'Original published date',
		'modified'       => 'Updated date',
		'public_dir' => 'Dev public directory',
		'work_dir'   => 'Dev work directory',
		'word_count'     => 'Word Count',
		'screenshot'     => 'Page Screenshot',
	];

	private array $status_map = [
		'auto-draft'        => 'Auto-Draft',
		'draft'             => 'Draft',
		'draft-republish'   => 'Draft (Republish)',
		'pending'           => 'Pending Review',
		'pending-republish' => 'Pending Review (Republish)',
		'future'            => 'Scheduled',
		'future-republish'  => 'Scheduled (Republish)',
		'publish'           => 'Published',
		'private'           => 'Private',
		'trash'             => 'Archived',
	];

	public function __construct( $post = false ) {
		if ( $post ) {
			$this->post = $post;

			// If this post is a Republish post, switch to the original post ID, but rewrite the status
			if ( $original_post_id = $this->is_republish_get_original_post_id() ) {
				$original_post = get_post( $original_post_id );
				if ( $post->post_status === 'draft' ) {
					$original_post->post_status = 'draft-republish';
				} elseif ( $post->post_status === 'pending' ) {
					$original_post->post_status = 'pending-republish';
				} elseif ( $post->post_status === 'future' ) {
					$original_post->post_status = 'future-republish';
				}
				$this->post = $original_post;
			} elseif ( $republish_post_id = $this->has_republish_get_republish_post_id() ) {
				$republish_post = get_post( $republish_post_id );
				if ( $republish_post->post_status === 'draft' ) {
					$post->post_status = 'draft-republish';
				} elseif ( $republish_post->post_status === 'pending' ) {
					$post->post_status = 'pending-republish';
				} elseif ( $republish_post->post_status === 'future' ) {
					$post->post_status = 'future-republish';
				}
				$this->post = $post;
			}
		}
	}

	public function get_option( $option_name ) {
		return $this->options[ $option_name ] ?? false;
	}

	public function get_options() {
		return $this->options ?? [];
	}

	public function get_value( $option_name ): mixed {
		// Require a post to be set
		if ( ! $this->post ) {
			return false;
		}

		// Require to be set in the options array
		if ( ! isset( $this->options[ $option_name ] ) ) {
			return '';
		}

		switch ( $option_name ) {
			case 'entity':
				return $this->post;
			case 'id':
				return $this->post->ID;
			case 'title':
				return $this->post->post_title === '' ? 'Untitled' : $this->post->post_title;
			case 'slug':
				return $this->post->post_name;
			case 'status':
				$status = $this->post->post_status;

				// The "trash" status is returned on both the posts awaiting deletion in the trash and the posts that
				// have been deleted from the trash.

				$status_map = $this->get_status_map();
				foreach ( $status_map as $key => $value ) {
					if ( $key === $status ) {
						return $value;
					}
				}

				return '';
			case 'view_url':
				return get_permalink( $this->post );
			case 'edit_url':
				return admin_url( 'post.php?post=' . $this->post->ID . '&action=edit' );
			case 'author':
				return $this->post->post_author;
			case 'type':
				return $this->post->post_type;
			case 'date':
				// Format YYYY-MM-DD HH:MM:SS ??
				$wp_date = $this->post->post_date_gmt === '0000-00-00 00:00:00' ? $this->post->post_date : $this->post->post_date_gmt;
				$date    = DateTime::createFromFormat( 'Y-m-d H:i:s', $wp_date );
				$unix_ms = $date->format( 'Uv' );

				return $unix_ms;
			case 'original_date':
				// Get the original date if it exists
				// Format YYYY-MM-DD
				$old_post_date = get_post_meta( $this->post->ID, '_wp_old_date', true );

				// Convert to unix timestamp in milliseconds
				if ( $old_post_date ) {
					$date    = DateTime::createFromFormat( 'Y-m-d', $old_post_date );
					$unix_ms = $date->format( 'Uv' );

					return $unix_ms;
				}

				// Fallback to post_date if no old date exists
				return $this->get_value( 'date' );
			case 'modified':
				// Format YYY-MM-DD HH:MM:SS ??
				$wp_date = $this->post->post_date_gmt === '0000-00-00 00:00:00' ? $this->post->post_modified : $this->post->post_modified_gmt;
				$date    = DateTime::createFromFormat( 'Y-m-d H:i:s', $wp_date );
				$unix_ms = $date->format( 'Uv' );

				return $unix_ms;
			case 'public_dir':
				$real_post_id = $this->is_republish_get_original_post_id() ?: $this->post->ID;

				return get_post_meta( $real_post_id, 'x_alpha_public_dir', true );
			case 'work_dir':
				$real_post_id = $this->is_republish_get_original_post_id() ?: $this->post->ID;

				return get_post_meta( $real_post_id, 'x_alpha_work_dir', true );
			case 'word_count':
				return str_word_count( trim( strip_tags( $this->post->post_content ) ) );
			case 'screenshot':
				// If post status is not publish, return false
				if ( 'publish' !== $this->post->post_status ) {
					return false;
				}
				// TODO - Configure screenshots
				$result = '';

				return $result;
			default:
				return '';
		}
	}

	public function get_status_map() {
		return $this->status_map;
	}

	/**
	 * Returns the original Post ID if the given post is a Rewrite & Republish. Otherwise returns false.
	 *
	 * PLUGIN SUPPORT
	 * Currently only supports the Yoast Rewrite & Republish plugin.
	 * When updating the function, also update a similar function in x-alpha/includes/Core/Google_Drive/Folders.php.
	 *
	 * @return false|int The parent Post ID or '' if not found.
	 */
	public function is_republish_get_original_post_id(): false|int {
		if ( $this->is_republish_get_original_post_id === null ) {
			// Support: Duplicate Post plugin
			$original_entity_id = get_post_meta( $this->post->ID, '_dp_original', true );

			if ( ! $original_entity_id && isset( $_GET['action'], $_GET['post'] ) && 'duplicate_post_rewrite' === $_GET['action'] && is_numeric( $_GET['post'] ) ) {
				$original_entity_id = (int) $_GET['post'];
			}

			$this->is_republish_get_original_post_id = is_numeric($original_entity_id) ? (int) $original_entity_id : false;
		}

		return $this->is_republish_get_original_post_id;
	}

	/**
	 * Returns the Rewrite & Republish Post ID if the given Post is being rewritten.
	 *
	 * @return false|int The parent Post ID or '' if not found.
	 */
	public function has_republish_get_republish_post_id(): false|int {
		if ( $this->has_republish_get_republish_post_id === null ) {
			// Support: Duplicate Post plugin
			$republish_post_id = get_post_meta( $this->post->ID, '_dp_has_rewrite_republish_copy', true );

			$this->has_republish_get_republish_post_id = is_numeric($republish_post_id) ? (int) $republish_post_id : false;
		}

		return $this->has_republish_get_republish_post_id;
	}
}