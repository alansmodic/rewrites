<?php
/**
 * REST API controller for staged revisions.
 *
 * @package Rewrites
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API controller providing endpoints for staged revision management.
 */
class Rewrites_REST_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'rewrites/v1';
		$this->rest_base = 'staged';
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /rewrites/v1/staged - List all staged revisions.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// GET/POST /rewrites/v1/staged/{post_id} - Get or create staged revision for a post.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<post_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'title'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content' => array(
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						),
						'excerpt' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'notes'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// DELETE /rewrites/v1/staged/{revision_id} - Discard a staged revision.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/revision/(?P<revision_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'revision_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// POST /rewrites/v1/staged/{revision_id}/publish - Publish a staged revision.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<revision_id>[\d]+)/publish',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'publish_item' ),
					'permission_callback' => array( $this, 'publish_item_permissions_check' ),
					'args'                => array(
						'revision_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// POST /rewrites/v1/staged/{revision_id}/schedule - Schedule a staged revision.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<revision_id>[\d]+)/schedule',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'schedule_item' ),
					'permission_callback' => array( $this, 'schedule_item_permissions_check' ),
					'args'                => array(
						'revision_id'  => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'publish_date' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// POST /rewrites/v1/staged/{revision_id}/approve - Approve a staged revision.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<revision_id>[\d]+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve_item' ),
					'permission_callback' => array( $this, 'approve_item_permissions_check' ),
					'args'                => array(
						'revision_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// POST /rewrites/v1/staged/{revision_id}/reject - Reject a staged revision.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<revision_id>[\d]+)/reject',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reject_item' ),
					'permission_callback' => array( $this, 'reject_item_permissions_check' ),
					'args'                => array(
						'revision_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Get all staged revisions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'status'   => $request->get_param( 'status' ),
			'per_page' => $request->get_param( 'per_page' ),
			'page'     => $request->get_param( 'page' ),
		);

		$items = Rewrites_Staged_Revision::get_all( $args );

		$data = array();
		foreach ( $items as $item ) {
			$data[] = array(
				'revision_id'    => (int) $item->revision_id,
				'post_id'        => (int) $item->post_parent,
				'post_title'     => $item->post_title,
				'post_type'      => $item->post_type,
				'revision_title' => $item->revision_title,
				'author'         => (int) $item->staged_author_id,
				'author_name'    => $this->get_author_name( $item->staged_author_id ),
				'status'         => $item->staged_status ? $item->staged_status : 'pending',
				'scheduled_date' => $item->scheduled_date,
				'notes'          => $item->notes,
				'modified'       => $item->post_modified,
			);
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Get author display name.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_author_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown', 'rewrites' );
	}

	/**
	 * Get staged revision for a post.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$post_id  = $request->get_param( 'post_id' );
		$revision = Rewrites_Staged_Revision::get( $post_id );

		if ( ! $revision ) {
			return new WP_Error(
				'rest_not_found',
				__( 'No staged revision found for this post.', 'rewrites' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( Rewrites_Staged_Revision::format_for_response( $revision ) );
	}

	/**
	 * Create a staged revision.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$post_id = $request->get_param( 'post_id' );

		$post_data = array(
			'title'   => $request->get_param( 'title' ),
			'content' => $request->get_param( 'content' ),
			'excerpt' => $request->get_param( 'excerpt' ),
		);

		$meta_data = array(
			'notes' => $request->get_param( 'notes' ),
		);

		$revision_id = Rewrites_Staged_Revision::create( $post_id, $post_data, $meta_data );

		if ( is_wp_error( $revision_id ) ) {
			return $revision_id;
		}

		$revision = Rewrites_Staged_Revision::get_by_id( $revision_id );

		return rest_ensure_response( Rewrites_Staged_Revision::format_for_response( $revision ) );
	}

	/**
	 * Delete (discard) a staged revision.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$revision_id = $request->get_param( 'revision_id' );
		$result      = Rewrites_Staged_Revision::discard( $revision_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'message' => __( 'Staged revision discarded.', 'rewrites' ),
			)
		);
	}

	/**
	 * Publish a staged revision.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function publish_item( $request ) {
		$revision_id = $request->get_param( 'revision_id' );
		$result      = Rewrites_Staged_Revision::publish( $revision_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'published' => true,
				'post_id'   => $result,
				'message'   => __( 'Staged revision published successfully.', 'rewrites' ),
			)
		);
	}

	/**
	 * Schedule a staged revision.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function schedule_item( $request ) {
		$revision_id  = $request->get_param( 'revision_id' );
		$publish_date = $request->get_param( 'publish_date' );

		$result = Rewrites_Staged_Revision::schedule( $revision_id, $publish_date );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$revision = Rewrites_Staged_Revision::get_by_id( $revision_id );

		return rest_ensure_response( Rewrites_Staged_Revision::format_for_response( $revision ) );
	}

	/**
	 * Approve a staged revision.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve_item( $request ) {
		$revision_id = $request->get_param( 'revision_id' );
		$result      = Rewrites_Staged_Revision::approve( $revision_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$revision = Rewrites_Staged_Revision::get_by_id( $revision_id );

		return rest_ensure_response( Rewrites_Staged_Revision::format_for_response( $revision ) );
	}

	/**
	 * Reject a staged revision.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject_item( $request ) {
		$revision_id = $request->get_param( 'revision_id' );
		$result      = Rewrites_Staged_Revision::reject( $revision_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$revision = Rewrites_Staged_Revision::get_by_id( $revision_id );

		return rest_ensure_response( Rewrites_Staged_Revision::format_for_response( $revision ) );
	}

	/**
	 * Check permissions for getting items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Check permissions for getting an item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$post_id = $request->get_param( 'post_id' );
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Check permissions for creating an item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$post_id = $request->get_param( 'post_id' );
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Check permissions for deleting an item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$revision_id = $request->get_param( 'revision_id' );
		$revision    = Rewrites_Staged_Revision::get_by_id( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'rest_not_found', __( 'Staged revision not found.', 'rewrites' ), array( 'status' => 404 ) );
		}

		return current_user_can( 'edit_post', $revision->post_parent );
	}

	/**
	 * Check permissions for publishing an item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function publish_item_permissions_check( $request ) {
		$revision_id = $request->get_param( 'revision_id' );
		$revision    = Rewrites_Staged_Revision::get_by_id( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'rest_not_found', __( 'Staged revision not found.', 'rewrites' ), array( 'status' => 404 ) );
		}

		return current_user_can( 'publish_posts' ) && current_user_can( 'edit_post', $revision->post_parent );
	}

	/**
	 * Check permissions for scheduling an item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function schedule_item_permissions_check( $request ) {
		return $this->publish_item_permissions_check( $request );
	}

	/**
	 * Check permissions for approving an item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function approve_item_permissions_check( $request ) {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Check permissions for rejecting an item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function reject_item_permissions_check( $request ) {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'status'   => array(
				'description'       => __( 'Filter by staged revision status.', 'rewrites' ),
				'type'              => 'string',
				'enum'              => array( 'pending', 'approved', 'rejected' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items to return.', 'rewrites' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'page'     => array(
				'description'       => __( 'Current page of results.', 'rewrites' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
		);
	}
}
