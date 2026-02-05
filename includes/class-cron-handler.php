<?php
/**
 * Cron handler for scheduled publishing of staged revisions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewrites_Cron_Handler {

	/**
	 * Singleton instance.
	 *
	 * @var Rewrites_Cron_Handler|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Rewrites_Cron_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'rewrites_publish_staged', array( $this, 'handle_scheduled_publish' ) );
	}

	/**
	 * Handle scheduled publishing of a staged revision.
	 *
	 * @param int $revision_id The staged revision ID.
	 */
	public function handle_scheduled_publish( $revision_id ) {
		$revision = Rewrites_Staged_Revision::get_by_id( $revision_id );

		if ( ! $revision ) {
			$this->log( sprintf( 'Revision %d not found for scheduled publish.', $revision_id ) );
			return;
		}

		// Check if still staged (not already published/discarded).
		// Use get_metadata for revision posts since get_post_meta doesn't work on revisions.
		if ( ! get_metadata( 'post', $revision_id, '_staged_revision', true ) ) {
			$this->log( sprintf( 'Revision %d is no longer a staged revision.', $revision_id ) );
			return;
		}

		// Check workflow status - don't publish if rejected.
		$status = get_metadata( 'post', $revision_id, '_staged_status', true );
		if ( 'rejected' === $status ) {
			$this->log( sprintf( 'Revision %d was rejected, skipping scheduled publish.', $revision_id ) );
			return;
		}

		// Publish the staged revision.
		$result = Rewrites_Staged_Revision::publish( $revision_id );

		if ( is_wp_error( $result ) ) {
			$this->log(
				sprintf(
					'Failed to publish revision %d: %s',
					$revision_id,
					$result->get_error_message()
				)
			);
			return;
		}

		$this->log( sprintf( 'Successfully published staged revision %d to post %d.', $revision_id, $result ) );

		/**
		 * Fires after a scheduled staged revision is published.
		 *
		 * @param int $post_id     The parent post ID.
		 * @param int $revision_id The revision ID that was published.
		 */
		do_action( 'rewrites_scheduled_publish_completed', $result, $revision_id );
	}

	/**
	 * Log a message for debugging.
	 *
	 * @param string $message The message to log.
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Rewrites: ' . $message );
		}
	}
}
