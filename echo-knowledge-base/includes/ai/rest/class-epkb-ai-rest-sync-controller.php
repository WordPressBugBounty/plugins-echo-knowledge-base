<?php defined( 'ABSPATH' ) || exit();

/**
 * REST API Controller for AI Sync operations
 */
class EPKB_AI_REST_Sync_Controller extends EPKB_AI_REST_Base_Controller {

	/**
	 * Register routes
	 */
	public function register_routes() {
		
		// Start direct sync
		register_rest_route( $this->admin_namespace, '/start-direct-sync', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_direct_sync' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'selected_post_ids' => array(
						'required' => true,
						'description' => 'Post IDs to sync or "ALL"',
					),
					'collection_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => 'Collection ID',
					),
				),
			)
		) );
		
		// Start cron sync
		register_rest_route( $this->admin_namespace, '/start-cron-sync', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_cron_sync' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'selected_post_ids' => array(
						'required' => true,
						'description' => 'Post IDs to sync or "ALL"',
					),
					'collection_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => 'Collection ID',
					),
				),
			)
		) );
		
		// Get sync progress
		register_rest_route( $this->admin_namespace, '/sync-progress', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'sync_progress' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		) );
		
		// Cancel all sync
		register_rest_route( $this->admin_namespace, '/cancel-all-sync', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_all_sync' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		) );
	}
	
	/**
	 * Check admin permission
	 * 
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_admin_permission( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to perform this action.', 'echo-knowledge-base' ), array( 'status' => 403 ) );
		}
		return true;
	}
	
	/**
	 * Start direct sync
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function start_direct_sync( $request ) {
		
		$selected_post_ids = $request->get_param( 'selected_post_ids' );
		$collection_id = intval( $request->get_param( 'collection_id' ) );
		
		// Start sync job
		$result = EPKB_AI_Sync_Job_Manager::start_sync_job( $selected_post_ids, 'direct', $collection_id );
		
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array( 'success' => false, 'error' => $result->get_error_code(), 'message' => $result->get_error_message() ), 400 );
		}
		
		return $this->create_rest_response( array( 'success' => true, 'job' => $result, 'total' => $result['total'] ) );
	}
	
	/**
	 * Start cron sync
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function start_cron_sync( $request ) {
		
		$selected_post_ids = $request->get_param( 'selected_post_ids' );
		$collection_id = intval( $request->get_param( 'collection_id' ) );
		
		// Start sync job
		$result = EPKB_AI_Sync_Job_Manager::start_sync_job( $selected_post_ids, 'cron', $collection_id );
		
		if ( is_wp_error( $result ) ) {
			return $this->create_rest_response( array( 'success' => false, 'error' => $result->get_error_code(), 'message' => $result->get_error_message() ), 400 );
		}
		
		// Schedule cron event
		wp_schedule_single_event( time() + 1, EPKB_AI_Sync_Job_Manager::CRON_HOOK );
		
		return $this->create_rest_response( array( 'success' => true, 'job' => $result, 'total' => $result['total'] ) );
	}
	
	/**
	 * Get sync progress and optionally process next batch for direct sync
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function sync_progress( $request ) {
		
		$job = EPKB_AI_Sync_Job_Manager::get_sync_job();
		
		// For direct sync that's running, process next batch
		if ( $job['type'] === 'direct' && $job['status'] === 'running' ) {
			$batch_result = EPKB_AI_Sync_Job_Manager::process_next_batch();
			
			// Refresh job data after processing
			$job = EPKB_AI_Sync_Job_Manager::get_sync_job();
			
			// Include updated posts info if available
			if ( ! empty( $batch_result['updated_posts'] ) ) {
				$job['updated_posts'] = $batch_result['updated_posts'];
			}
		}
		
		return $this->create_rest_response( array(
								'success' => true,
								'progress' => array(
									'status' => $job['status'],
									'total' => $job['total'],
									'processed' => $job['processed'],
									'percent' => $job['percent'],
									'errors' => isset( $job['errors'] ) ? $job['errors'] : 0,
									'type' => $job['type'],
									'updated_posts' => isset( $job['updated_posts'] ) ? $job['updated_posts'] : array()
								)
		) );
	}
	
	/**
	 * Cancel all sync operations
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function cancel_all_sync( $request ) {
		
		$result = EPKB_AI_Sync_Job_Manager::cancel_all_sync();
		
		return $this->create_rest_response( array( 'success' => $result,  'message' => __( 'Sync canceled successfully', 'echo-knowledge-base' )) );
	}
}