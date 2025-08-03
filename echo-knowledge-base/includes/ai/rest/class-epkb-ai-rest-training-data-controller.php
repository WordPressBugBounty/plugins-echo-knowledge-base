<?php  if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST API Controller for AI Sync operations
 * 
 * Provides endpoints for managing training data synchronization.
 */
class EPKB_AI_REST_Training_Data_Controller extends EPKB_AI_REST_Base_Controller {

	const MAX_BATCH_SIZE = 50;           // Maximum batch size

	public function __construct() {
		parent::__construct();
	}
	
	/**
	 * Register routes
	 */
	public function register_routes() {

		// Get sync status
		register_rest_route( $this->admin_namespace, '/sync/status', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_sync_status' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
			'args'                => array(
				'collection_id' => array(
					'type' => 'integer',
					'required' => true
				)
			)
		) );

		/** MANAGE ROWS OF TRAINING DATA */

		// Get training data rows
		register_rest_route( $this->admin_namespace, '/training-data', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_training_data_rows'),
			'permission_callback' => array( $this, 'check_admin_permission' ),
			'args'                => array(
				'collection_id' => array(
					'type' => 'integer',
					'required' => true
				),
				'status' => array(
					'type' => 'string',
					'enum' => array( '', 'adding', 'added', 'updating', 'updated', 'outdated', 'error' )
				),
				'type' => array(
					'type' => 'string'
				),
				'page' => array(
					'type' => 'integer',
					'default' => 1,
					'minimum' => 1
				),
				'per_page' => array(
					'type' => 'integer',
					'default' => 50,
					'minimum' => 1,
					'maximum' => 100
				),
				'search' => array(
					'type' => 'string'
				)
			)
		) );

		// Delete selected training data rows
		register_rest_route( $this->admin_namespace, '/training-data/delete-selected', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_training_data_rows'),
			'permission_callback' => array( $this, 'check_admin_permission' ),
			'args'                => array(
				'ids' => array(
					'required' => true,
					'type' => 'array',
					'items' => array( 'type' => 'integer' )
				)
			)
		) );

		/** MANAGE COLLECTIONS OF TRAINING DATA */

		// Get training data collections
		register_rest_route( $this->admin_namespace, '/training-collections', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_training_collections' ),
			'permission_callback' => array( $this, 'check_admin_permission' )
		) );
		
		// Create training data collection
		register_rest_route( $this->admin_namespace, '/training-collections', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_training_collection' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
			'args'                => array(
				'name' => array(
					'required' => true,
					'type' => 'string',
					'minLength' => 3,
					'maxLength' => 80,
					'sanitize_callback' => 'sanitize_text_field'
				),
				'post_types' => array(
					'type' => 'array',
					'items' => array( 'type' => 'string' ),
					'default' => array()
				)
			)
		) );
		
		// Update training data collection
		register_rest_route( $this->admin_namespace, '/training-collections/(?P<collection_id>\d+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_training_collection' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
			'args'                => array(
				'collection_id' => array(
					'required' => true,
					'type' => 'integer'
				),
				'name' => array(
					'type' => 'string',
					'minLength' => 3,
					'maxLength' => 80,
					'sanitize_callback' => 'sanitize_text_field'
				),
				'post_types' => array(
					'type' => 'array',
					'items' => array( 'type' => 'string' )
				)
			)
		) );
		
		// Delete training data collection
		register_rest_route( $this->admin_namespace, '/training-collections/(?P<collection_id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_training_collection' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
			'args'                => array(
				'collection_id' => array(
					'required' => true,
					'type' => 'integer'
				)
			)
		) );
		
		// Add data to training data collection
		register_rest_route( $this->admin_namespace, '/training-collections/(?P<collection_id>\d+)/add-data', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'add_data_to_collection' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
			'args'                => array(
				'collection_id' => array(
					'required' => true,
					'type' => 'integer'
				),
				'data_types' => array(
					'required' => true,
					'type' => 'array',
					'items' => array( 'type' => 'string' )
				)
			)
		) );
	}

	/**
	 * Get sync status
	 *
	 * Returns comprehensive sync status information including progress tracking,
	 * health checks, and recent sync history. This uses the get_status() method
	 * which provides full status details, not just basic statistics.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_sync_status( $request ) {

		$collection_id = $request->get_param( 'collection_id' );

		// Validate collection exists
		$collection_config = EPKB_AI_Training_Data_Config_Specs::get_training_data_collection( $collection_id );
		if ( ! $collection_config ) {
			return $this->create_rest_response( [], 400, new WP_Error( 'invalid_collection', __( 'Invalid training data collection', 'echo-knowledge-base' ) ) );
		}

		// Get database statistics
		$training_data_db = new EPKB_AI_Training_Data_DB();
		$db_stats = $training_data_db->get_status_statistics( $collection_id );
		
		// Get sync job status from new job manager
		$job = EPKB_AI_Sync_Job_Manager::get_sync_job();
		
		// Build simple status response
		$status = array(
			'is_running' => EPKB_AI_Sync_Job_Manager::is_job_active(),
			'database' => $db_stats,
			'progress' => array(
				'percentage' => $job['percent'],
				'phase' => $job['status'],
				'processed' => $job['processed'],
				'total' => $job['total'],
				'errors' => $job['errors']
			)
		);

		return $this->create_rest_response( array( 'success' => true, 'status' => $status ) );
	}

	/**********************************************************************
	 * Training Data Rows
	 **********************************************************************/

	/**
	 * Get training data list
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_training_data_rows( $request ) {

		$collection_id = $request->get_param( 'collection_id' );

		// Validate collection exists
		$collection_config = EPKB_AI_Training_Data_Config_Specs::get_training_data_collection( $collection_id );
		if ( ! $collection_config ) {
			return $this->create_rest_response( [], 400, new WP_Error( 'invalid_collection', __( 'Invalid training data collection', 'echo-knowledge-base' ) ) );
		}
		$status = $request->get_param( 'status' );
		$type = $request->get_param( 'type' );
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$search = $request->get_param( 'search' );

		// Build query args
		$args = array(
			'training_collection_id' => $collection_id,
			'page' => $page,
			'per_page' => $per_page,
			'orderby' => 'updated',
			'order' => 'DESC'
		);

		if ( ! empty( $status ) ) {
			$args['status'] = $status;
		}

		if ( ! empty( $type ) ) {
			$args['type'] = $type;
		}

		if ( ! empty( $search ) ) {
			$args['search'] = $search;
		}

		// Get training data from database
		$training_data_db = new EPKB_AI_Training_Data_DB();
		$data = $training_data_db->get_training_data_list( $args );
		if ( is_wp_error( $data ) ) {
			return $this->create_rest_response( [], 500, $data );
		}

		$total = $training_data_db->get_training_data_count( $args );

		// Calculate pagination
		$total_pages = ceil( $total / $per_page );

		// Get total status counts for the collection
		$status_stats = $training_data_db->get_status_statistics( $collection_id );

		return $this->create_rest_response( array( 'success' => true, 'data' => $data, 'pagination' => array(
			'page' => $page,
			'per_page' => $per_page,
			'total' => $total,
			'total_pages' => $total_pages
		),
		'status_counts' => $status_stats
		) );
	}


	/**********************************************************************
	 * Training Data Collections Management
	 **********************************************************************/

	/**
	 * Get training data collections
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_training_collections( $request ) {
		
		$collections = EPKB_AI_Training_Data_Config_Specs::get_training_data_collections();
		if ( is_wp_error( $collections ) ) {
			return $this->create_rest_response( [], 500, $collections );
		}
		$formatted_collections = array();
		
		$training_data_db = new EPKB_AI_Training_Data_DB();
		
		foreach ( $collections as $collection_id => $collection_config ) {
			// Get database stats for this collection
			$db_stats = $training_data_db->get_status_statistics( $collection_id );
			$last_sync_date = $training_data_db->get_last_sync_date( $collection_id );
			
			$formatted_collections[] = array(
				'id' => $collection_id,
				'name' => empty( $collection_config['ai_training_data_store_name'] ) ? EPKB_AI_Training_Data_Config_Specs::get_default_collection_name( $collection_id ) : $collection_config['ai_training_data_store_name'],
				'post_types' => $collection_config['ai_training_data_store_post_types'],
				'item_count' => isset( $db_stats['synced'] ) ? $db_stats['synced'] : 0,
				'last_synced' => $last_sync_date
			);
		}
		
		return $this->create_rest_response( array( 'success' => true, 'collections' => $formatted_collections ) );
	}
	
	/**
	 * Create training data collection
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function create_training_collection( $request ) {
		
		$name = $request->get_param( 'name' );
		$post_types = $request->get_param( 'post_types' );
		
		// Get next collection ID
		$collection_id = EPKB_AI_Training_Data_Config_Specs::get_next_collection_id();
		if ( is_wp_error( $collection_id ) ) {
			return $this->create_rest_response( [], 500, $collection_id );
		}
		
		// Clear any potential existing cache for this collection ID (in case of ID reuse)
		delete_transient( 'epkb_sync_progress_' . $collection_id );
		
		// Create collection config - start with empty post types
		$collection_config = array(
			'ai_training_data_store_name' => empty( $name ) ? EPKB_AI_Training_Data_Config_Specs::get_default_collection_name( $collection_id ) : $name,
			'ai_training_data_store_id' => '',
			'ai_training_data_store_post_types' => array() // Start empty - user will add data types later
		);
		
		// Save the collection
		$saved = EPKB_AI_Training_Data_Config_Specs::update_training_data_collection( $collection_id, $collection_config );
		
		if ( is_wp_error( $saved ) ) {
			return $this->create_rest_response( [], 400, $saved );
		}
		
		if ( ! $saved ) {
			return $this->create_rest_response( [], 400, new WP_Error( 'save_failed', __( 'Failed to save training data collection', 'echo-knowledge-base' ) ) );
		}
		
		return $this->create_rest_response( array( 'success' => true, 'collection_id' => $collection_id, 'message' => __( 'Training data collection created successfully', 'echo-knowledge-base' ) ) );
	}
	
	/**
	 * Update training data collection
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update_training_collection( $request ) {
		
		$collection_id = $request->get_param( 'collection_id' );
		$name = $request->get_param( 'name' );
		$post_types = $request->get_param( 'post_types' );
		
		// Get existing collection
		$collection_config = EPKB_AI_Training_Data_Config_Specs::get_training_data_collection( $collection_id );
		if ( ! $collection_config ) {
			return $this->create_rest_response( [], 400, new WP_Error( 'invalid_collection', __( 'Invalid training data collection', 'echo-knowledge-base' ) ) );
		}
		
		// Store the old name to check if it changed
		$old_name = $collection_config['ai_training_data_store_name'];
		
		// Update fields
		if ( $name !== null ) {
			$collection_config['ai_training_data_store_name'] = $name;
		}
		if ( $post_types !== null ) {
			$collection_config['ai_training_data_store_post_types'] = $post_types;
		}
		
		// Save the collection
		$saved = EPKB_AI_Training_Data_Config_Specs::update_training_data_collection( $collection_id, $collection_config );
		
		if ( is_wp_error( $saved ) ) {
			return $this->create_rest_response( [], 400, $saved );
		}
		
		if ( ! $saved ) {
			return $this->create_rest_response( [], 400, new WP_Error( 'save_failed', __( 'Failed to update training data collection', 'echo-knowledge-base' ) ) );
		}
		
		// If the name changed and there's a vector store, update its name too
		if ( $name !== null && $name !== $old_name && ! empty( $collection_config['ai_training_data_store_id'] ) ) {

			$openai_handler = new EPKB_AI_OpenAI_Handler();
			$update_result = $openai_handler->update_vector_store( $collection_config['ai_training_data_store_id'], array( 'name' => $name ) );
			if ( is_wp_error( $update_result ) ) {
				// Log the error but don't fail the entire operation
				EPKB_AI_Log::add_log( 'Failed to update vector store name', array('collection_id' => $collection_id, 'vector_store_id' => $collection_config['ai_training_data_store_id'],
					'new_name' => $name, 'error' => $update_result->get_error_message()) );
			}
		}
		
		return $this->create_rest_response( array( 'success' => true, 'message' => __( 'Training data collection updated successfully', 'echo-knowledge-base' )	) );
	}

	/**
	 * Delete training data rows user selected (REST endpoint).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete_training_data_rows( $request ) {

		$ids = $request->get_param( 'ids' );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return $this->create_rest_response( [], 400, new WP_Error( 'invalid_ids', __( 'No IDs provided', 'echo-knowledge-base' ) ) );
		}

		// Use internal method to delete
		$results = $this->delete_training_data_by_ids( $ids );

		$message = sprintf( __( 'Deleted %d items, %d failed', 'echo-knowledge-base' ), $results['deleted'], $results['failed'] );
		if ( $results['vector_store_errors'] > 0 ) {
			$message .= sprintf( __( ' (%d vector store errors)', 'echo-knowledge-base' ), $results['vector_store_errors'] );
		}

		return $this->create_rest_response( array(
			'success' => true,
			'deleted' => $results['deleted'],
			'failed' => $results['failed'],
			'vector_store_errors' => $results['vector_store_errors'],
			'message' => $message
		) );
	}

	/**
	 * Delete training data collection
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete_training_collection( $request ) {
		
		$collection_id = $request->get_param( 'collection_id' );
		
		// Initialize components
		$training_data_db = new EPKB_AI_Training_Data_DB();
		
		// Get the collection configuration to retrieve the vector store ID
		$collection_config = EPKB_AI_Training_Data_Config_Specs::get_training_data_collection( $collection_id );
		if ( ! $collection_config ) {
			return $this->create_rest_response( [], 400, new WP_Error( 'invalid_collection', __( 'Invalid training data collection', 'echo-knowledge-base' ) ) );
		}
		
		// Store the vector store ID before deleting the collection
		$vector_store_id = isset( $collection_config['ai_training_data_store_id'] ) ? $collection_config['ai_training_data_store_id'] : '';
		
		// First, get all training data for this collection
		$training_data = $training_data_db->get_training_data_by_collection( $collection_id );
		
		// If there's data, delete it first
		if ( ! empty( $training_data ) ) {
			// Extract IDs from the training data records
			$ids = array_map( function( $record ) {
				return $record->id;
			}, $training_data );
			
			// Delete all training data records and their Vector Store files
			$delete_result = $this->delete_training_data_by_ids( $ids );
			if ( is_wp_error( $delete_result ) ) {
				return $delete_result;
			}
		}
		
		// Delete the vector store if it exists
		if ( ! empty( $vector_store_id ) ) {
			$openai_handler = new EPKB_AI_OpenAI_Handler();
			$vector_store_delete_result = $openai_handler->delete_vector_store( $vector_store_id );
			
			if ( is_wp_error( $vector_store_delete_result ) ) {
				// Log the error but don't fail the entire operation
				EPKB_AI_Log::add_log( 'Failed to delete vector store during collection deletion', array(
					'collection_id' => $collection_id,
					'vector_store_id' => $vector_store_id,
					'error' => $vector_store_delete_result->get_error_message()
				) );
			} else {
				// Log successful deletion
				EPKB_AI_Log::add_log( 'Vector store deleted successfully', array(
					'collection_id' => $collection_id,
					'vector_store_id' => $vector_store_id
				) );
			}
		}
		
		// Clear any sync progress for this collection
		delete_transient( 'epkb_sync_progress_' . $collection_id );
		
		// Now delete the collection
		$result = EPKB_AI_Training_Data_Config_Specs::delete_training_data_collection( $collection_id );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		
		return new WP_REST_Response( array(
			'success' => true,
			'message' => __( 'Collection and associated vector store deleted successfully.', 'echo-knowledge-base' )
		), 200 );
	}

	/**
	 * Delete training data rows by IDs (internal method)
	 *
	 * @param array $ids Array of training data record IDs to delete
	 * @return array Results array with deleted count, failed count, and vector store errors
	 */
	private function delete_training_data_by_ids( $ids ) {

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return array( 'deleted' => 0, 'failed' => 0, 'vector_store_errors' => 0 );
		}

		$training_data_db = new EPKB_AI_Training_Data_DB();
		$openai_handler = new EPKB_AI_OpenAI_Handler();
		$deleted = 0;
		$failed = 0;
		$vector_store_errors = 0;

		foreach ( $ids as $id ) {
			// Get the training data record before deleting to get file IDs
			$record = $training_data_db->get_training_data( $id );

			if ( $record ) {
				// Delete from OpenAI if file ID exists
				if ( ! empty( $record->file_id ) ) {
					$delete_file_result = $openai_handler->delete_file( $record->file_id );
					if ( is_wp_error( $delete_file_result ) ) {
						$vector_store_errors++;
						EPKB_AI_Log::add_log( 'Failed to delete file from OpenAI', array(
							'file_id' => $record->file_id,
							'error' => $delete_file_result->get_error_message()
						) );
					}
				}

				// Remove from vector store if file_id exists
				if ( ! empty( $record->store_id ) && ! empty( $record->file_id ) ) {
					$remove_result = $openai_handler->remove_file_from_vector_store( $record->store_id, $record->file_id );
					if ( is_wp_error( $remove_result ) ) {
						$vector_store_errors++;
						EPKB_AI_Log::add_log( 'Failed to remove file from vector store', array(
							'store_id' => $record->store_id,
							'file_id' => $record->file_id,
							'error' => $remove_result->get_error_message()
						) );
					}
				}
			}

			// Delete from database
			$result = $training_data_db->delete_training_data_record( $id );
			if ( is_wp_error( $result ) ) {
				$failed++;
			} else {
				$deleted++;
			}
		}

		return array(
			'deleted' => $deleted,
			'failed' => $failed,
			'vector_store_errors' => $vector_store_errors
		);
	}

	/**
	 * Add data to training data collection
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function add_data_to_collection( $request ) {
		
		$collection_id = $request->get_param( 'collection_id' );
		$data_types = $request->get_param( 'data_types' );
		
		// Validate collection exists
		$collection_config = EPKB_AI_Training_Data_Config_Specs::get_training_data_collection( $collection_id );
		if ( ! $collection_config ) {
			return $this->create_rest_response( [], 400, new WP_Error( 'invalid_collection', __( 'Invalid training data collection', 'echo-knowledge-base' ) ) );
		}
		
		// Initialize counters
		$total_added = 0;
		$total_errors = 0;
		
		// Update collection configuration to include the selected post types FIRST
		$collection_config['ai_training_data_store_post_types'] = $data_types;
		$update_result = EPKB_AI_Training_Data_Config_Specs::update_training_data_collection( $collection_id, $collection_config );
		
		if ( is_wp_error( $update_result ) ) {
			return $this->create_rest_response( [], 400, $update_result );
		}
		
		// Process each data type
		foreach ( $data_types as $data_type ) {
			// For now, only handle post types (KB #1)
			if ( $data_type === 'epkb_post_type_1' ) {
				$result = $this->add_posts_to_collection( $collection_id, $data_type );
				if ( is_wp_error( $result ) ) {
					$total_errors++;
				} else {
					$total_added += $result;
				}
			}
			// Future: Handle other data types like notes, documents, etc.
		}
		
		// Prepare response message
		if ( $total_added > 0 ) {
			$message = sprintf( __( 'Successfully added %d new items to the collection', 'echo-knowledge-base' ), $total_added );
			if ( $total_errors > 0 ) {
				$message .= sprintf( __( ' (%d errors)', 'echo-knowledge-base' ), $total_errors );
			}
			return $this->create_rest_response( array( 'success' => true, 'message' => $message, 'items_added' => $total_added ) );
		} else {
			// Check if it's because all posts already exist
			$existing_count = 0;
			foreach ( $data_types as $data_type ) {
				if ( $data_type === 'epkb_post_type_1' ) {
					$training_data_db = new EPKB_AI_Training_Data_DB();
					$existing_count = $training_data_db->get_training_data_count( array( 'training_collection_id' => $collection_id ) );
					break;
				}
			}
			
			if ( $existing_count > 0 ) {
				return $this->create_rest_response( array( 
					'success' => true, 
					'message' => __( 'All posts are already in the collection. No new posts were found to add.', 'echo-knowledge-base' ),
					'items_added' => 0 
				) );
			} else {
				return $this->create_rest_response( [], 400, new WP_Error( 'no_data_added', __( 'No data was added to the collection', 'echo-knowledge-base' ) ) );
			}
		}
	}
	
	/**
	 * Add posts to collection (extracted from load_posts_for_default_collection)
	 *
	 * @param int $collection_id
	 * @param string $post_type
	 * @return int Number of posts added or error
	 */
	private function add_posts_to_collection( $collection_id, $post_type ) {
		
		$training_data_db = new EPKB_AI_Training_Data_DB();
		
		// Get existing post IDs for this collection
		$existing_post_ids = $training_data_db->get_existing_post_ids( $collection_id );
		if ( is_wp_error( $existing_post_ids ) ) {
			$existing_post_ids = array();
		}
		
		// Use the sync manager to get posts - this ensures consistency with the sync process
		$sync_manager = new EPKB_AI_Sync_Manager();
		$post_ids = $sync_manager->get_posts_to_sync( $collection_id );
		if ( empty( $post_ids ) ) {
			return 0;
		}
		
		// Filter out posts that already exist in the collection
		$new_post_ids = array_diff( $post_ids, $existing_post_ids );
		
		if ( empty( $new_post_ids ) ) {
			// All posts already exist
			return 0;
		}
		
		// Batch insert for better performance
		$batch_size = 100;
		$batches = array_chunk( $new_post_ids, $batch_size );
		$total_loaded = 0;
		
		foreach ( $batches as $batch ) {
			// Get posts data in one query
			$posts = get_posts( array(
				'post__in'       => $batch,
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'post__in'
			) );
			
			foreach ( $posts as $post ) {
				// Prepare training data record
				$training_data = array(
					'training_collection_id' => $collection_id,
					'item_id'                => (string) $post->ID,
					'title'                  => $post->post_title,
					'type'                   => $post->post_type,
					'status'                 => 'pending', // Default status for new records
					'url'                    => get_permalink( $post->ID ),
					'content_hash'           => md5( $post->post_content ),
					'user_id'                => get_current_user_id()
				);
				
				// Insert the record
				$result = $training_data_db->insert_training_data( $training_data );
				if ( ! is_wp_error( $result ) ) {
					$total_loaded++;
				}
			}
		}
		
		// Log the operation
		EPKB_AI_Log::add_log( 'Posts added to collection', array(
			'collection_id' => $collection_id,
			'post_type' => $post_type,
			'posts_loaded' => $total_loaded,
			'total_posts' => count( $post_ids ),
			'existing_posts' => count( $existing_post_ids ),
			'new_posts' => count( $new_post_ids )
		) );
		
		return $total_loaded;
	}
	
	/**
	 * Check admin permission
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_admin_permission( $request ) {
		
		// Check nonce
		$nonce_check = EPKB_AI_Security::check_rest_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}
		
		// Check user capability
		if ( ! EPKB_Admin_UI_Access::is_user_access_to_context_allowed( 'admin_eckb_access_ai_feature' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to manage AI sync', 'echo-knowledge-base' ),	array( 'status' => 403 ) );
		}
		
		return true;
	}
	
	/**
	 * Validate date format
	 *
	 * @param string $date Date string to validate
	 * @return bool True if valid YYYY-MM-DD format
	 */
	private function is_valid_date( $date ) {
		// Check format
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		
		// Check if it's a valid date
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}
}