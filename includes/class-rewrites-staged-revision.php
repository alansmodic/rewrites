<?php
/**
 * Staged revision manager class.
 *
 * @package Rewrites
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CRUD operations, publishing, scheduling, and approval workflow for staged revisions.
 */
class Rewrites_Staged_Revision {

	/**
	 * Update meta for a revision post.
	 * Uses update_metadata directly since update_post_meta doesn't work for revisions.
	 *
	 * @param int    $revision_id The revision ID.
	 * @param string $meta_key    The meta key.
	 * @param mixed  $meta_value  The meta value.
	 * @return int|bool Meta ID if new, true if updated, false on failure.
	 */
	private static function update_revision_meta( $revision_id, $meta_key, $meta_value ) {
		return update_metadata( 'post', $revision_id, $meta_key, $meta_value );
	}

	/**
	 * Get meta for a revision post.
	 *
	 * @param int    $revision_id The revision ID.
	 * @param string $meta_key    The meta key.
	 * @return mixed The meta value.
	 */
	private static function get_revision_meta( $revision_id, $meta_key ) {
		return get_metadata( 'post', $revision_id, $meta_key, true );
	}

	/**
	 * Delete meta for a revision post.
	 *
	 * @param int    $revision_id The revision ID.
	 * @param string $meta_key    The meta key.
	 * @return bool True on success, false on failure.
	 */
	private static function delete_revision_meta( $revision_id, $meta_key ) {
		return delete_metadata( 'post', $revision_id, $meta_key );
	}

	/**
	 * Create a staged revision for a published post.
	 *
	 * @param int   $post_id   The parent post ID.
	 * @param array $post_data Post data (title, content, excerpt).
	 * @param array $meta_data Additional meta data.
	 * @return int|WP_Error Revision ID on success, WP_Error on failure.
	 */
	public static function create( $post_id, $post_data, $meta_data = array() ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'rewrites' ) );
		}

		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'not_published', __( 'Can only stage revisions for published posts.', 'rewrites' ) );
		}

		// Check for existing staged revision.
		$existing = self::get( $post_id );

		// Prepare revision data.
		$revision_data = array(
			'post_title'    => isset( $post_data['title'] ) ? $post_data['title'] : $post->post_title,
			'post_content'  => isset( $post_data['content'] ) ? $post_data['content'] : $post->post_content,
			'post_excerpt'  => isset( $post_data['excerpt'] ) ? $post_data['excerpt'] : $post->post_excerpt,
			'post_type'     => 'revision',
			'post_status'   => 'inherit',
			'post_parent'   => $post_id,
			'post_name'     => $post_id . '-staged-v1',
			'post_author'   => get_current_user_id(),
			'post_date'     => current_time( 'mysql' ),
			'post_date_gmt' => current_time( 'mysql', 1 ),
		);

		if ( $existing ) {
			// Update existing staged revision.
			$revision_data['ID'] = $existing->ID;
			$revision_id         = wp_update_post( wp_slash( $revision_data ), true );
		} else {
			// Create new staged revision.
			$revision_id = wp_insert_post( wp_slash( $revision_data ), true );
		}

		if ( is_wp_error( $revision_id ) ) {
			return $revision_id;
		}

		// Mark as staged revision using direct metadata functions (update_post_meta doesn't work for revisions).
		self::update_revision_meta( $revision_id, '_staged_revision', '1' );
		$current_user_id = get_current_user_id();
		self::update_revision_meta( $revision_id, '_staged_author', $current_user_id ? $current_user_id : 1 );
		self::update_revision_meta( $revision_id, '_staged_status', 'pending' );

		// Save additional meta.
		if ( isset( $meta_data['notes'] ) ) {
			self::update_revision_meta( $revision_id, '_staged_notes', sanitize_textarea_field( $meta_data['notes'] ) );
		}

		// Update parent post meta.
		update_post_meta( $post_id, '_has_staged_revision', $revision_id );

		return $revision_id;
	}

	/**
	 * Get the staged revision for a post.
	 *
	 * @param int $post_id The parent post ID.
	 * @return WP_Post|null The staged revision or null if none exists.
	 */
	public static function get( $post_id ) {
		$revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled'  => false,
				'posts_per_page' => -1,
			)
		);

		foreach ( $revisions as $revision ) {
			if ( self::get_revision_meta( $revision->ID, '_staged_revision' ) ) {
				return $revision;
			}
		}

		return null;
	}

	/**
	 * Get staged revision by revision ID.
	 *
	 * @param int $revision_id The revision ID.
	 * @return WP_Post|null The staged revision or null.
	 */
	public static function get_by_id( $revision_id ) {
		$revision = get_post( $revision_id );

		if ( ! $revision || 'revision' !== $revision->post_type ) {
			return null;
		}

		if ( ! self::get_revision_meta( $revision_id, '_staged_revision' ) ) {
			return null;
		}

		return $revision;
	}

	/**
	 * Publish a staged revision (merge changes to live post).
	 *
	 * @param int $revision_id The staged revision ID.
	 * @return int|WP_Error Parent post ID on success, WP_Error on failure.
	 */
	public static function publish( $revision_id ) {
		$revision = self::get_by_id( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'invalid_revision', __( 'Invalid staged revision.', 'rewrites' ) );
		}

		$parent_id = $revision->post_parent;

		// Use WordPress core function to restore revision.
		$result = wp_restore_post_revision( $revision_id );

		if ( ! $result || is_wp_error( $result ) ) {
			return new WP_Error( 'restore_failed', __( 'Failed to publish staged revision.', 'rewrites' ) );
		}

		// Clean up staged revision meta.
		self::delete_revision_meta( $revision_id, '_staged_revision' );
		self::delete_revision_meta( $revision_id, '_staged_status' );
		self::delete_revision_meta( $revision_id, '_staged_publish_date' );
		self::delete_revision_meta( $revision_id, '_staged_author' );
		self::delete_revision_meta( $revision_id, '_staged_notes' );

		// Clean up parent meta.
		delete_post_meta( $parent_id, '_has_staged_revision' );

		// Clear any scheduled cron.
		wp_clear_scheduled_hook( 'rewrites_publish_staged', array( $revision_id ) );

		/**
		 * Fires after a staged revision is published.
		 *
		 * @param int $parent_id   The parent post ID.
		 * @param int $revision_id The revision ID that was published.
		 */
		do_action( 'rewrites_staged_published', $parent_id, $revision_id );

		return $parent_id;
	}

	/**
	 * Schedule a staged revision for future publishing.
	 *
	 * @param int    $revision_id  The staged revision ID.
	 * @param string $publish_date The publish date (ISO 8601 or MySQL format).
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function schedule( $revision_id, $publish_date ) {
		$revision = self::get_by_id( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'invalid_revision', __( 'Invalid staged revision.', 'rewrites' ) );
		}

		$timestamp = strtotime( $publish_date );

		if ( ! $timestamp || $timestamp <= time() ) {
			return new WP_Error( 'invalid_date', __( 'Publish date must be in the future.', 'rewrites' ) );
		}

		// Clear any existing scheduled event.
		wp_clear_scheduled_hook( 'rewrites_publish_staged', array( $revision_id ) );

		// Store the scheduled date in UTC.
		self::update_revision_meta( $revision_id, '_staged_publish_date', gmdate( 'Y-m-d H:i:s', $timestamp ) );

		// Schedule the cron event.
		$scheduled = wp_schedule_single_event(
			$timestamp,
			'rewrites_publish_staged',
			array( $revision_id )
		);

		if ( false === $scheduled ) {
			return new WP_Error( 'schedule_failed', __( 'Failed to schedule publishing.', 'rewrites' ) );
		}

		return true;
	}

	/**
	 * Discard (delete) a staged revision.
	 *
	 * @param int $revision_id The staged revision ID.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function discard( $revision_id ) {
		$revision = self::get_by_id( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'invalid_revision', __( 'Invalid staged revision.', 'rewrites' ) );
		}

		$parent_id = $revision->post_parent;

		// Clear any scheduled cron.
		wp_clear_scheduled_hook( 'rewrites_publish_staged', array( $revision_id ) );

		// Delete the revision.
		wp_delete_post_revision( $revision_id );

		// Clean up parent meta.
		delete_post_meta( $parent_id, '_has_staged_revision' );

		/**
		 * Fires after a staged revision is discarded.
		 *
		 * @param int $parent_id   The parent post ID.
		 * @param int $revision_id The revision ID that was discarded.
		 */
		do_action( 'rewrites_staged_discarded', $parent_id, $revision_id );

		return true;
	}

	/**
	 * Approve a staged revision.
	 *
	 * @param int $revision_id The staged revision ID.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function approve( $revision_id ) {
		$revision = self::get_by_id( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'invalid_revision', __( 'Invalid staged revision.', 'rewrites' ) );
		}

		self::update_revision_meta( $revision_id, '_staged_status', 'approved' );

		/**
		 * Fires after a staged revision is approved.
		 *
		 * @param int     $revision_id The revision ID.
		 * @param WP_Post $revision    The revision post object.
		 */
		do_action( 'rewrites_staged_approved', $revision_id, $revision );

		return true;
	}

	/**
	 * Reject a staged revision.
	 *
	 * @param int $revision_id The staged revision ID.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function reject( $revision_id ) {
		$revision = self::get_by_id( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'invalid_revision', __( 'Invalid staged revision.', 'rewrites' ) );
		}

		self::update_revision_meta( $revision_id, '_staged_status', 'rejected' );

		// Clear any scheduled publishing.
		wp_clear_scheduled_hook( 'rewrites_publish_staged', array( $revision_id ) );
		self::delete_revision_meta( $revision_id, '_staged_publish_date' );

		/**
		 * Fires after a staged revision is rejected.
		 *
		 * @param int     $revision_id The revision ID.
		 * @param WP_Post $revision    The revision post object.
		 */
		do_action( 'rewrites_staged_rejected', $revision_id, $revision );

		return true;
	}

	/**
	 * Get all posts with staged revisions.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of staged revision data.
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'per_page' => 20,
			'page'     => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( "pm_staged.meta_value = '1'" );

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = $wpdb->prepare( 'pm_status.meta_value = %s', $args['status'] );
		}

		$where  = implode( ' AND ', $where_clauses );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT r.ID as revision_id, r.post_parent, r.post_modified, r.post_author,
					r.post_title as revision_title, r.post_content as revision_content,
					p.post_title, p.post_type,
					pm_status.meta_value as staged_status,
					pm_date.meta_value as scheduled_date,
					pm_notes.meta_value as notes,
					pm_author.meta_value as staged_author_id
			 FROM {$wpdb->posts} r
			 INNER JOIN {$wpdb->posts} p ON r.post_parent = p.ID
			 INNER JOIN {$wpdb->postmeta} pm_staged ON r.ID = pm_staged.post_id AND pm_staged.meta_key = '_staged_revision'
			 LEFT JOIN {$wpdb->postmeta} pm_status ON r.ID = pm_status.post_id AND pm_status.meta_key = '_staged_status'
			 LEFT JOIN {$wpdb->postmeta} pm_date ON r.ID = pm_date.post_id AND pm_date.meta_key = '_staged_publish_date'
			 LEFT JOIN {$wpdb->postmeta} pm_notes ON r.ID = pm_notes.post_id AND pm_notes.meta_key = '_staged_notes'
			 LEFT JOIN {$wpdb->postmeta} pm_author ON r.ID = pm_author.post_id AND pm_author.meta_key = '_staged_author'
			 WHERE r.post_type = 'revision' AND {$where}
			 ORDER BY r.post_modified DESC
			 LIMIT %d OFFSET %d",
			$args['per_page'],
			$offset
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( $query );
	}

	/**
	 * Format staged revision data for REST API response.
	 *
	 * @param WP_Post $revision The revision post object.
	 * @return array Formatted data.
	 */
	public static function format_for_response( $revision ) {
		return array(
			'id'             => $revision->ID,
			'post_id'        => $revision->post_parent,
			'title'          => $revision->post_title,
			'content'        => $revision->post_content,
			'excerpt'        => $revision->post_excerpt,
			'author'         => (int) self::get_revision_meta( $revision->ID, '_staged_author' ),
			'status'         => self::get_revision_meta( $revision->ID, '_staged_status' ) ? self::get_revision_meta( $revision->ID, '_staged_status' ) : 'pending',
			'scheduled_date' => self::get_revision_meta( $revision->ID, '_staged_publish_date' ),
			'notes'          => self::get_revision_meta( $revision->ID, '_staged_notes' ),
			'modified'       => $revision->post_modified,
			'modified_gmt'   => $revision->post_modified_gmt,
		);
	}
}
