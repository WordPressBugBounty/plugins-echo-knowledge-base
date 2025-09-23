<?php  if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AI Sync Job Manager
 * 
 * Manages sync jobs with unified approach for both direct and cron modes.
 * Both direct and cron sync process one post at a time for consistent behavior.
 * Stores sync state in WordPress option for persistence and single-job enforcement.
 */
class EPKB_AI_Sync_Job_Manager {

	const SYNC_OPTION_NAME = 'epkb_ai_sync_job_status';
	const CRON_HOOK = 'epkb_do_sync_cron_event';
	
	/**
	 * Get current sync job status
	 * 
	 * @return array Sync job data or default values
	 */
	public static function get_sync_job() {

		$default = self::get_default_job_data();
		$job = get_option( self::SYNC_OPTION_NAME, $default );

		return wp_parse_args( $job, $default );
	}

	/**
	 * Update sync job status
	 * 
	 * @param array $data Data to update
	 * @return bool Success
	 */
	public static function update_sync_job( $data=array() ) {

		$job = self::get_sync_job();
		if ( self::is_job_canceled( $job ) ) {
			return false;
		}

		$updated_job = array_merge( $job, $data );
		$updated_job['last_update'] = gmdate( 'Y-m-d H:i:s' );

		return update_option( self::SYNC_OPTION_NAME, $updated_job, false );
	}

	/**
	 * Initialize a new sync job
	 * 
	 * @param array|string $selected_post_ids Post IDs or 'ALL'
	 * @param string $mode 'direct' or 'cron'
	 * @param int $collection_id Collection ID
	 * @return array|WP_Error
	 */
	public static function initialize_sync_job( $selected_post_ids, $mode, $collection_id ) {

		// Validate no active job exists
		if ( self::is_job_active() ) {
			return new WP_Error( 'job_active', __( 'A sync job is already running', 'echo-knowledge-base' ) );
		}

		// Clear any existing sync job data to ensure we start fresh
		// This prevents old sync records from being processed
		delete_option( self::SYNC_OPTION_NAME );
		
		// Validate collection
		$collection_id = EPKB_AI_Validation::validate_collection_id( $collection_id );
		if ( is_wp_error( $collection_id ) ) {
			return $collection_id;
		}
		
		// Always get all items from collection to have correct types
		$all_items = self::get_all_posts_for_collection( $collection_id );
		
		// Filter items based on selection
		$items = array();
		if ( $selected_post_ids === 'ALL' ) {
			$items = $all_items;
		} elseif ( is_string( $selected_post_ids ) && strpos( $selected_post_ids, 'ALL_' ) === 0 ) {
			// Handle status-filtered "ALL" requests (e.g., "ALL_PENDING", "ALL_ERROR")
			$status_filter = strtolower( substr( $selected_post_ids, 4 ) ); // Extract status after "ALL_"
			
			// Filter items by status
			$training_data_db = new EPKB_AI_Training_Data_DB( false );
			foreach ( $all_items as $item ) {
				$record = $training_data_db->get_training_data_by_item( $collection_id, $item['id'] );
				if ( $record && isset( $record->status ) && $record->status === $status_filter ) {
					$items[] = $item;
				}
			}
		} elseif ( is_array( $selected_post_ids ) ) {
			foreach ( $all_items as $item ) {
				if ( in_array( $item['id'], $selected_post_ids ) ) {
					$items[] = $item;
				}
			}
		} else {
			return new WP_Error( 'invalid_post_ids', __( 'Invalid post IDs provided', 'echo-knowledge-base' ) );
		}

		// Check if we have posts to sync
		if ( empty( $items ) ) {
			return new WP_Error( 'no_posts', __( 'No posts found to sync', 'echo-knowledge-base' ) );
		}

		// Create job data
		$job_data = array_merge( self::get_default_job_data(), array(
			'status' => $mode === 'cron' ? 'scheduled' : 'running',
			'type' => $mode,
			'collection_id' => $collection_id,	
			'items' => $items,
			'total' => count( $items )
		) );

		// Save job
		if ( ! self::update_sync_job( $job_data ) ) {
			return new WP_Error( 'save_failed', __( 'Failed to save sync job', 'echo-knowledge-base' ) );
		}

		return $job_data;
	}

	/**
	 * Process next post in the sync queue
	 * 
	 * @return array Result with processed count and status
	 */
	public static function process_next_sync_item() {

		$job = self::get_sync_job();

		// Skip if canceled
		if ( self::is_job_canceled( $job ) ) {
			return array( 'status' => 'idle' );
		}

		// Skip if not running
		if ( $job['status'] !== 'running' ) {
			return array( 'status' => $job['status'] );
		}

		// Get next unprocessed item (always process one at a time)
		$remaining_item = array_slice( $job['items'], $job['processed'], 1 );
		$remaining_item = empty( $remaining_item[0] ) ? null : $remaining_item[0];
		$remaining_post_ids = $remaining_item ? array( $remaining_item['id'] ) : array();
		
		// Check if we need to retry failed posts
		if ( empty( $remaining_post_ids ) && ! empty( $job['retry_post_ids'] ) && empty( $job['retrying'] ) ) {

			// Start retrying failed posts one by one
			$failed_items = array();
			foreach ( $job['retry_post_ids'] as $failed_id ) {
				// Find the original item type from the items array
				$item_type = 'post';
				foreach ( $job['items'] as $item ) {
					if ( $item['id'] == $failed_id ) {
						$item_type = $item['type'];
						break;
					}
				}
				$failed_items[] = array( 'id' => $failed_id, 'type' => $item_type );
			}
			
			$result = self::update_sync_job( array( 'retrying' => true, 'items' => $failed_items, 'processed' => 0, 'total' => count( $failed_items ) ) );
			if ( ! $result ) {
				return array( 'status' => 'failed', 'message' => __( 'Stopping retrying failed posts', 'echo-knowledge-base' ) );
			}
			
			$job = self::get_sync_job();
			$remaining_item = array_slice( $job['items'], 0, 1 );
			$remaining_item = empty( $remaining_item[0] ) ? null : $remaining_item[0];
			$remaining_post_ids = $remaining_item ? array( $remaining_item['id'] ) : array();
		}
		
		// check if all done including retries
		if ( empty( $remaining_post_ids ) ) {
			self::update_sync_job( array( 'status' => 'completed', 'percent' => 100 ) );
			return array( 'status' => 'completed' );
		}
		
		$consecutive_errors = $job['consecutive_errors'];
		$updated_posts = array();
		
		// Process the single post
		$post_id = $remaining_post_ids[0];
			
		// Get or create vector store
		$openai_handler = new EPKB_AI_OpenAI_Handler();
		$vector_store_id = $openai_handler->get_or_create_vector_store( $job['collection_id'] );
		if ( is_wp_error( $vector_store_id ) ) {
			self::update_sync_job( array( 'status' => 'failed', 'error_message' => $vector_store_id->get_error_message() ) );
			return array( 'status' => 'failed', 'error' => $vector_store_id );
		}

		// Sync the post
		$sync_manager = new EPKB_AI_Sync_Manager();
		$job['processed']++;
		$result = $sync_manager->sync_post( $post_id, $remaining_item['type'], $job['collection_id'], $vector_store_id );
		if ( is_wp_error( $result ) ) {

			$is_retry = $result->get_error_data( 'retry' ) === true;

			$consecutive_errors = $is_retry ? $consecutive_errors + 1 : 0;
			$job['errors']++;
			
			$updated_posts[] = array( 
				'id' => $post_id, 
				'status' => 'error', 
				'message' => $result->get_error_message()
			);

			self::update_sync_job();

			// Check if we've hit 5 consecutive errors
			if ( $consecutive_errors >= 5 ) {
				// Update job status and exit sync
				self::update_sync_job( array(
					'status' => 'failed',
					'processed' => $job['processed'],
					'errors' => $job['errors'],
					'percent' => round( ( $job['processed'] / $job['total'] ) * 100 ),
					'consecutive_errors' => $consecutive_errors,
					'error_message' => __( 'Sync stopped after 5 consecutive errors', 'echo-knowledge-base' )
				) );

				return array(
					'status' => 'failed',
					'processed' => 0,
					'errors' => 1,
					'message' => __( 'Sync stopped after 5 consecutive errors', 'echo-knowledge-base' ),
					'updated_posts' => $updated_posts
				);
			}

			// Track failed post for retry (only if not already retrying)
			if ( empty( $job['retrying'] ) && ! in_array( $post_id, $job['retry_post_ids'] ) && $is_retry ) {
				$job['retry_post_ids'][] = $post_id;
				self::update_sync_job( array( 'retry_post_ids' => $job['retry_post_ids'] ) );
			}

		} else {
			// Reset consecutive errors on success
			$consecutive_errors = 0;

			// Only send minimal data - JavaScript already has title and type from the table
			$post_update_data = array( 
				'id' => $post_id, 
				'status' => empty( $result['skipped'] ) ? 'synced' : 'skipped'
			);

			$updated_posts[] = $post_update_data;
		}

		// Update job progress
		$new_processed = $job['processed'];
		$percent = $job['retrying'] ? 100 : round( ( $new_processed / $job['total'] ) * 100 );
		
		self::update_sync_job( array( 'processed' => $new_processed, 'errors' => $job['errors'], 'percent' => $percent, 'consecutive_errors' => $consecutive_errors ) );
		
		// Check if complete
		if ( $new_processed >= $job['total'] ) {

			// Verify vector store has all expected files before marking as complete
			$expected_files = $job['total'] - $job['errors'];
			
			// Allow 1.5 minutes per file for processing
			if ( $expected_files > 0 ) {
				$timeout = $expected_files * 90;
				$verify_result = $openai_handler->verify_vector_store_ready( $vector_store_id, $expected_files, $timeout );
				if ( is_wp_error( $verify_result ) ) {
					self::update_sync_job( array( 'status' => 'failed', 'error_message' => sprintf( __( 'Vector store verification failed: %s', 'echo-knowledge-base' ), $verify_result->get_error_message() ) ) );
					return array( 'status' => 'failed',	'error' => $verify_result, 'updated_posts' => $updated_posts );
				}
			}
			
			$result = self::update_sync_job( array( 'status' => 'completed', 'percent' => 100, 'processed' => $new_processed ) );

			return array( 'status' => $result ? 'completed' : 'failed', 'updated_posts' => $updated_posts );
		}
		
		return array(
			'status' => self::is_job_canceled() ? 'idle' : 'running',
			'processed' => 1,
			'errors' => $job['errors'],
			'updated_posts' => $updated_posts
		);
	}

	private static function get_default_job_data() {
		return array(
			'status' => 'idle',	// idle, scheduled (cron), running (direct), completed, failed
			'type' => '',
			'collection_id' => 0,
			'items' => array(),
			'retry_post_ids' => array(),
			'retrying' => false,
			'cancel_requested' => false,
			'processed' => 0,
			'total' => 0,
			'percent' => 0,
			'errors' => 0,
			'consecutive_errors' => 0,
			'start_time' => gmdate( 'Y-m-d H:i:s' ),
			'last_update' => ''
		);
	}

	/**
	 * Check if a job is active
	 *
	 * @return bool
	 */
	public static function is_job_active() {
		$job = self::get_sync_job();
		return in_array( $job['status'], array( 'scheduled', 'running' ) );
	}

	private static function is_job_canceled( $job = null ) {

		if ( empty( $job ) ) {
			$job = self::get_sync_job();
		}

		return ! empty( $job['cancel_requested'] );
	}

	/**
	 * Cancel all sync operations
	 *
	 * @return bool Success
	 */
	public static function cancel_all_sync() {

		// Mark cancel requested and set to idle (align with sync semantics)
		self::update_sync_job( array(
			'status' => 'idle',
			'cancel_requested' => true,
		) );
		
		// Clear scheduled cron event if exists
		wp_clear_scheduled_hook( self::CRON_HOOK );
		
		// Don't delete the cancel flag here - let it persist until a new sync starts
		// This prevents race conditions where a running process might not see the cancel
		
		return true;
	}
	
	/**
	 * Get all posts for a collection with their metadata
	 * 
	 * @param int $collection_id Collection ID
	 * @return array Array of items with id and type
	 */
	private static function get_all_posts_for_collection( $collection_id ) {
		
		// Get all items from the training data database for this collection
		// This includes posts, KB files, and any other item types
		$training_data_db = new EPKB_AI_Training_Data_DB( false );
		$training_items = $training_data_db->get_training_data_by_collection( $collection_id );

		// Extract item IDs and types from the training data
		$items = array();
		foreach ( $training_items as $item ) {
			if ( ! empty( $item->item_id ) ) {
				$items[] = array(
					'id' => $item->item_id,
					'type' => empty( $item->type ) ? 'post' : $item->type
				);
			}
		}
		
		return $items;
	}
}