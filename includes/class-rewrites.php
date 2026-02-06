<?php
/**
 * Core plugin class.
 *
 * @package Rewrites
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rewrites
 *
 * Core plugin class for registering meta, REST routes, and editor assets.
 */
class Rewrites {

	/**
	 * Singleton instance.
	 *
	 * @var Rewrites|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Rewrites
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
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'wp_save_post_revision_post_has_changed', array( $this, 'protect_staged_revisions' ), 10, 3 );
	}

	/**
	 * Register post meta for staged revisions.
	 */
	public function register_meta() {
		// Meta keys for revision posts.
		$revision_meta = array(
			'_staged_revision'     => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'_staged_status'       => array(
				'type'    => 'string',
				'default' => 'pending',
			),
			'_staged_publish_date' => array(
				'type'    => 'string',
				'default' => '',
			),
			'_staged_author'       => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'_staged_notes'        => array(
				'type'    => 'string',
				'default' => '',
			),
		);

		foreach ( $revision_meta as $key => $args ) {
			register_post_meta(
				'',
				$key,
				array(
					'type'              => $args['type'],
					'single'            => true,
					'default'           => $args['default'],
					'show_in_rest'      => true,
					'auth_callback'     => array( $this, 'meta_auth_callback' ),
					'sanitize_callback' => $this->get_sanitize_callback( $args['type'] ),
				)
			);
		}

		// Register meta for parent posts to track staged revision existence.
		$public_post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $public_post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'_has_staged_revision',
				array(
					'type'              => 'integer',
					'single'            => true,
					'default'           => 0,
					'show_in_rest'      => true,
					'auth_callback'     => array( $this, 'meta_auth_callback' ),
					'sanitize_callback' => 'absint',
				)
			);
		}
	}

	/**
	 * Get sanitize callback for meta type.
	 *
	 * @param string $type Meta type.
	 * @return callable
	 */
	private function get_sanitize_callback( $type ) {
		switch ( $type ) {
			case 'boolean':
				return 'rest_sanitize_boolean';
			case 'integer':
				return 'absint';
			case 'string':
			default:
				return 'sanitize_text_field';
		}
	}

	/**
	 * Auth callback for meta.
	 *
	 * @param bool   $allowed   Whether the user can add the post meta.
	 * @param string $meta_key  The meta key.
	 * @param int    $object_id The object ID.
	 * @return bool
	 */
	public function meta_auth_callback( $allowed, $meta_key, $object_id ) {
		return current_user_can( 'edit_post', $object_id );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$controller = new Rewrites_REST_Controller();
		$controller->register_routes();
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_editor_assets() {
		global $post;

		if ( ! $post ) {
			return;
		}

		// Load for published posts and drafts (so UI appears after first publish).
		if ( ! in_array( $post->post_status, array( 'publish', 'draft', 'auto-draft', 'pending' ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'rewrites-editor',
			REWRITES_PLUGIN_URL . 'assets/js/editor.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-api-fetch',
				'wp-i18n',
			),
			REWRITES_VERSION,
			true
		);

		// Pass data to JavaScript.
		wp_localize_script(
			'rewrites-editor',
			'rewritesData',
			array(
				'postId'           => $post->ID,
				'stagedRevisionId' => (int) get_post_meta( $post->ID, '_has_staged_revision', true ),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'checklistEnabled' => Rewrites_Settings::is_enabled(),
				'checklistItems'   => Rewrites_Settings::get_checklist_items(),
				'strings'          => array(
					'checklistTitle'    => __( 'Before You Publish', 'rewrites' ),
					'checklistSubtitle' => __( 'Please review the checklist below before publishing your changes.', 'rewrites' ),
					'saveAsRewrite'     => __( 'Save as Rewrite', 'rewrites' ),
					'confirmAndPublish' => __( 'Confirm & Publish Now', 'rewrites' ),
					'cancel'            => __( 'Cancel', 'rewrites' ),
					'requiredItems'     => __( 'Please check all required items before publishing.', 'rewrites' ),
					'savingRewrite'     => __( 'Saving as rewrite...', 'rewrites' ),
					'publishing'        => __( 'Publishing...', 'rewrites' ),
				),
			)
		);
	}

	/**
	 * Protect staged revisions from being overwritten by normal revision process.
	 *
	 * @param bool    $post_has_changed Whether the post has changed.
	 * @param WP_Post $last_revision    The last revision post object.
	 * @param WP_Post $post             The post object.
	 * @return bool
	 */
	public function protect_staged_revisions( $post_has_changed, $last_revision, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// If the last revision is a staged revision, don't mark it as unchanged.
		// This ensures WordPress creates a new revision instead of skipping.
		// Use get_metadata for revision posts since get_post_meta doesn't work on revisions.
		if ( get_metadata( 'post', $last_revision->ID, '_staged_revision', true ) ) {
			return true;
		}
		return $post_has_changed;
	}

	/**
	 * Get supported post types for staged revisions.
	 *
	 * @return array
	 */
	public static function get_supported_post_types() {
		return get_post_types( array( 'public' => true ), 'names' );
	}
}
