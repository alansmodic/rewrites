<?php
/**
 * Admin page for reviewing staged revisions.
 *
 * @package Rewrites
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu page, script enqueuing, and dashboard widget for staged revisions.
 */
class Rewrites_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Rewrites_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Rewrites_Admin
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
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_menu_page() {
		$count = $this->get_pending_count();
		$title = __( 'Rewrites', 'rewrites' );

		if ( $count > 0 ) {
			$title .= sprintf( ' <span class="awaiting-mod">%d</span>', $count );
		}

		add_menu_page(
			__( 'Rewrites - Staged Content', 'rewrites' ),
			$title,
			'edit_others_posts',
			'rewrites',
			array( $this, 'render_page' ),
			'dashicons-backup',
			25
		);
	}

	/**
	 * Get count of pending staged revisions.
	 *
	 * @return int
	 */
	private function get_pending_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->postmeta} pm_status ON pm.post_id = pm_status.post_id
			 WHERE pm.meta_key = '_staged_revision' AND pm.meta_value = '1'
			 AND pm_status.meta_key = '_staged_status' AND pm_status.meta_value = 'pending'"
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_rewrites' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'rewrites-admin',
			REWRITES_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			REWRITES_VERSION
		);

		wp_enqueue_script(
			'rewrites-admin',
			REWRITES_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-api-fetch' ),
			REWRITES_VERSION,
			true
		);

		wp_localize_script(
			'rewrites-admin',
			'rewritesAdmin',
			array(
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'apiBase' => rest_url( 'rewrites/v1/' ),
				'strings' => array(
					'confirmPublish' => __( 'Are you sure you want to publish these changes now?', 'rewrites' ),
					'confirmReject'  => __( 'Are you sure you want to reject these changes?', 'rewrites' ),
					'confirmDiscard' => __( 'Are you sure you want to discard these changes? This cannot be undone.', 'rewrites' ),
					'publishing'     => __( 'Publishing...', 'rewrites' ),
					'approving'      => __( 'Approving...', 'rewrites' ),
					'rejecting'      => __( 'Rejecting...', 'rewrites' ),
					'error'          => __( 'An error occurred. Please try again.', 'rewrites' ),
				),
			)
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		$staged_items = Rewrites_Staged_Revision::get_all(
			array(
				'per_page' => 50,
				'page'     => 1,
			)
		);

		include REWRITES_PLUGIN_DIR . 'admin/views/admin-page.php';
	}

	/**
	 * Add dashboard widget.
	 */
	public function add_dashboard_widget() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'rewrites_dashboard_widget',
			__( 'Rewrites Queue', 'rewrites' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the dashboard widget.
	 */
	public function render_dashboard_widget() {
		$staged_items = Rewrites_Staged_Revision::get_all(
			array(
				'per_page' => 5,
				'page'     => 1,
			)
		);

		if ( empty( $staged_items ) ) {
			echo '<p>' . esc_html__( 'No staged revisions in the queue.', 'rewrites' ) . '</p>';
			return;
		}

		echo '<style>
			.rewrites-widget-item { padding: 8px 0; border-bottom: 1px solid #f0f0f1; }
			.rewrites-widget-item:last-child { border-bottom: none; }
			.rewrites-widget-title { font-weight: 600; margin-bottom: 4px; }
			.rewrites-widget-meta { color: #646970; font-size: 12px; }
			.rewrites-widget-status { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
			.rewrites-widget-status--pending { background: #f0b849; color: #1d2327; }
			.rewrites-widget-status--approved { background: #4ab866; color: #fff; }
			.rewrites-widget-status--rejected { background: #d63638; color: #fff; }
		</style>';

		echo '<div class="rewrites-widget-list">';

		foreach ( $staged_items as $item ) {
			$status      = $item->staged_status ? $item->staged_status : 'pending';
			$author      = get_userdata( $item->staged_author_id );
			$author_name = $author ? $author->display_name : __( 'Unknown', 'rewrites' );

			$status_labels = array(
				'pending'  => __( 'Pending', 'rewrites' ),
				'approved' => __( 'Approved', 'rewrites' ),
				'rejected' => __( 'Rejected', 'rewrites' ),
			);

			echo '<div class="rewrites-widget-item">';
			echo '<div class="rewrites-widget-title">';
			echo '<a href="' . esc_url( get_edit_post_link( $item->post_parent ) ) . '">' . esc_html( $item->post_title ) . '</a>';
			echo '</div>';
			echo '<div class="rewrites-widget-meta">';
			echo '<span class="rewrites-widget-status rewrites-widget-status--' . esc_attr( $status ) . '">' . esc_html( $status_labels[ $status ] ?? ucfirst( $status ) ) . '</span> ';
			/* translators: %s: author name */
			echo esc_html( sprintf( __( 'by %s', 'rewrites' ), $author_name ) ) . ' &bull; ';
			/* translators: %s: human-readable time difference */
			echo esc_html( sprintf( __( '%s ago', 'rewrites' ), human_time_diff( strtotime( $item->post_modified ) ) ) );
			echo '</div>';
			echo '</div>';
		}

		echo '</div>';

		$total_count = $this->get_pending_count();
		if ( $total_count > 5 ) {
			echo '<p class="rewrites-widget-footer" style="margin: 12px 0 0; padding-top: 12px; border-top: 1px solid #f0f0f1;">';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=rewrites' ) ) . '">';
			/* translators: %d: number of additional items */
			echo esc_html( sprintf( __( 'View all %d items in queue &rarr;', 'rewrites' ), $total_count ) );
			echo '</a>';
			echo '</p>';
		} else {
			echo '<p class="rewrites-widget-footer" style="margin: 12px 0 0; padding-top: 12px; border-top: 1px solid #f0f0f1;">';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=rewrites' ) ) . '">' . esc_html__( 'Manage all staged content &rarr;', 'rewrites' ) . '</a>';
			echo '</p>';
		}
	}
}
