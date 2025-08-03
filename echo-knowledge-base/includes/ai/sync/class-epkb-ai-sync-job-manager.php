<?php  if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AI Sync Job Manager
 * 
 * Manages sync jobs with unified approach for both direct and cron modes.
 * Stores sync state in WordPress option for persistence and single-job enforcement.
 */
class EPKB_AI_Sync_Job_Manager {

	const SYNC_OPTION_NAME = 'epkb_ai_sync_job_status';
	const CRON_HOOK = 'epkb_do_sync_cron_event';
	const DEFAULT_BATCH_SIZE = 10;
	
	/**
	 * Get current sync job status
	 * 
	 * @return array Sync job data or default values
	 */
	public static function get_sync_job() {
		$default = array(
			'status' => 'idle',
			'type' => '',
			'total' => 0,
			'processed' => 0,
			'percent' => 0,
			'posts' => array(),
			'collection_id' => 0,
			'cancel_requested' => false,
			'errors' => 0,
			'start_time' => '',
			'last_update' => ''
		);
		
		$job = get_option( self::SYNC_OPTION_NAME, $default );

		return wp_parse_args( $job, $default );
	}
	
	/**
	 * Update sync job status
	 * 
	 * @param array $data Data to update
	 * @return bool Success
	 */
	public static function update_sync_job( $data ) {
		$current = self::get_sync_job();
		$updated = array_merge( $current, $data );
		$updated['last_update'] = gmdate( 'Y-m-d H:i:s' );
		return update_option( self::SYNC_OPTION_NAME, $updated, false );
	}
	
	
	/**
	 * Check if a sync job is currently active
	 * 
	 * @return bool
	 */
	public static function is_job_active() {
		$job = self::get_sync_job();
		return in_array( $job['status'], array( 'scheduled', 'running' ) );
	}
	
	/**
	 * Start a new sync job
	 * 
	 * @param array|string $selected_post_ids Post IDs or 'ALL'
	 * @param string $mode 'direct' or 'cron'
	 * @param int $collection_id Collection ID
	 * @return array|WP_Error
	 */
	public static function start_sync_job( $selected_post_ids, $mode, $collection_id ) {
		
		// Validate no active job exists
		if ( self::is_job_active() ) {
			return new WP_Error( 'job_active', __( 'A sync job is already running', 'echo-knowledge-base' ) );
		}
		
		// Validate collection
		$collection_id = EPKB_AI_Validation::validate_collection_id( $collection_id );
		if ( is_wp_error( $collection_id ) ) {
			return $collection_id;
		}
		
		// Resolve post IDs
		if ( $selected_post_ids === 'ALL' ) {
			$post_ids = self::get_all_posts_for_collection( $collection_id );
		} elseif ( is_array( $selected_post_ids ) ) {
			$post_ids = array_map( 'intval', $selected_post_ids );
		} else {
			return new WP_Error( 'invalid_post_ids', __( 'Invalid post IDs provided', 'echo-knowledge-base' ) );
		}
		
		// Check if we have posts to sync
		if ( empty( $post_ids ) ) {
			return new WP_Error( 'no_posts', __( 'No posts found to sync', 'echo-knowledge-base' ) );
		}
		
		// Create job data
		$job_data = array(
			'status' => $mode === 'cron' ? 'scheduled' : 'running',
			'type' => $mode,
			'total' => count( $post_ids ),
			'processed' => 0,
			'percent' => 0,
			'posts' => $post_ids,
			'collection_id' => $collection_id,
			'cancel_requested' => false,
			'errors' => 0,
			'start_time' => gmdate( 'Y-m-d H:i:s' )
		);
		
		// Save job
		if ( ! self::update_sync_job( $job_data ) ) {
			return new WP_Error( 'save_failed', __( 'Failed to save sync job', 'echo-knowledge-base' ) );
		}
		
		return $job_data;
	}
	
	/**
	 * Process next batch of posts
	 * 
	 * @param int $batch_size Number of posts to process
	 * @return array Result with processed count and status
	 */
	public static function process_next_batch( $batch_size = self::DEFAULT_BATCH_SIZE ) {
		
		$job = self::get_sync_job();
		
		// Skip if canceled
		if ( ! empty( $job['cancel_requested'] ) ) {
			self::update_sync_job( array( 'status' => 'canceled' ) );
			return array( 'status' => 'canceled' );
		}
		
		// Skip if not running
		if ( $job['status'] !== 'running' ) {
			return array( 'status' => $job['status'] );
		}
		
		// Get unprocessed posts
		$remaining_posts = array_slice( $job['posts'], $job['processed'], $batch_size );
		if ( empty( $remaining_posts ) ) {
			// All done
			self::update_sync_job( array( 'status' => 'completed', 'percent' => 100 ) );
			return array( 'status' => 'completed' );
		}
		
		// Process batch
		$sync_manager = new EPKB_AI_Sync_Manager();
		$openai_handler = new EPKB_AI_OpenAI_Handler();
		
		// Get or create vector store
		$vector_store_id = $openai_handler->get_or_create_vector_store( $job['collection_id'] );
		if ( is_wp_error( $vector_store_id ) ) {
			self::update_sync_job( array( 'status' => 'failed', 'error_message' => $vector_store_id->get_error_message() ) );
			return array( 'status' => 'failed', 'error' => $vector_store_id );
		}
		
		$batch_processed = 0;
		$batch_errors = 0;
		$updated_posts = array();
		
		foreach ( $remaining_posts as $post_id ) {
			// Check cancellation between posts
			if ( get_option( self::SYNC_OPTION_NAME . '_cancel', false ) ) {
				delete_option( self::SYNC_OPTION_NAME . '_cancel' );
				self::update_sync_job( array( 'status' => 'canceled', 'cancel_requested' => true ) );
				return array( 'status' => 'canceled' );
			}
			
			// Sync the post
			$result = $sync_manager->sync_post( $post_id, $job['collection_id'], $vector_store_id );
			
			if ( is_wp_error( $result ) ) {
				$batch_errors++;
				$updated_posts[] = array(
					'id' => $post_id,
					'status' => 'error',
					'message' => $result->get_error_message()
				);
			} else {
				$batch_processed++;
				$status = ! empty( $result['skipped'] ) ? 'skipped' : 'synced';
				$updated_posts[] = array(
					'id' => $post_id,
					'status' => $status
				);
			}
		}
		
		// Update job progress
		$new_processed = $job['processed'] + count( $remaining_posts );
		$percent = round( ( $new_processed / $job['total'] ) * 100 );
		
		self::update_sync_job( array( 'processed' => $new_processed, 'errors' => $job['errors'] + $batch_errors, 'percent' => $percent ) );
		
		// Check if complete
		if ( $new_processed >= $job['total'] ) {
			self::update_sync_job( array( 'status' => 'completed', 'percent' => 100, 'processed' => $new_processed ) );
			return array(
				'status' => 'completed',
				'updated_posts' => $updated_posts
			);
		}
		
		return array(
			'status' => 'running',
			'processed' => $batch_processed,
			'errors' => $batch_errors,
			'updated_posts' => $updated_posts
		);
	}
	
	/**
	 * Cancel all sync operations
	 * 
	 * @return bool Success
	 */
	public static function cancel_all_sync() {

		if ( self::is_job_active() ) {
			// Set cancel flag
			update_option( self::SYNC_OPTION_NAME . '_cancel', true, false );
			
			// Update job status
			self::update_sync_job( array(
				'status' => 'canceled',
				'cancel_requested' => true
			) );
			
			// Clear scheduled cron event if exists
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
		
		// Clean up cancel flag
		delete_option( self::SYNC_OPTION_NAME . '_cancel' );
		
		return true;
	}
	
	/**
	 * Get all posts for a collection
	 * 
	 * @param int $collection_id Collection ID
	 * @return array Post IDs
	 */
	private static function get_all_posts_for_collection( $collection_id ) {
		
		// Get collection configuration
		$collection_config = EPKB_AI_Training_Data_Config_Specs::get_training_data_collection( $collection_id );
		if ( ! $collection_config ) {
			return array();
		}
		
		// Get configured post types
		$post_types = $collection_config['ai_training_data_store_post_types'];
		if ( empty( $post_types ) ) {
			return array();
		}
		
		// Query posts
		$args = array(
			'post_type' => $post_types,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false
		);
		
		$query = new WP_Query( $args );

		return $query->posts;
	}
}