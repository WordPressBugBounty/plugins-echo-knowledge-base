<?php  if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AI Sync Manager
 * 
 * Simplified sync manager that handles individual post syncing.
 * Batch processing and job management is handled by EPKB_AI_Sync_Job_Manager.
 * 
 * Summary Mode:
 * When ai_training_data_use_summary is enabled, content is rewritten using OpenAI
 * before being uploaded to the vector store. The summarization status is tracked
 * by appending '_summarized' or '_full' to the content hash, ensuring content
 * is re-synced when the summary setting changes.
 */
class EPKB_AI_Sync_Manager {

	/**
	 * Training data database
	 * @var EPKB_AI_Training_Data_DB
	 */
	private $training_data_db;

	/**
	 * OpenAI handler
	 * @var EPKB_AI_OpenAI_Handler
	 */
	private $openai_handler;

	public function __construct() {
		$this->training_data_db = new EPKB_AI_Training_Data_DB( false );
		$this->openai_handler = new EPKB_AI_OpenAI_Handler();
	}

	/**
	 * Process a single post - called from batch or single post Admin UI
	 *
	 * @param int $post_id Post ID
	 * @param string $item_type typically 'post'
	 * @param int $collection_id Collection ID
	 * @param string $vector_store_id Vector store ID
	 * @return array|WP_Error Result
	 */
	public function sync_post( $post_id, $item_type, $collection_id, $vector_store_id = null ) {
		
		$collection_id = EPKB_AI_Validation::validate_collection_id( $collection_id );
		if ( is_wp_error( $collection_id ) ) {
			return $collection_id;
		}
		
		// Check if this is a KB files type - content comes from filter
		if ( $item_type === 'epkb_kb_files' ) {
			// For KB files, get content from filter
			$content = '';
			if ( has_filter( 'epkb_process_kb_file' ) ) {
				$content = apply_filters( 'epkb_process_kb_file', $post_id );
			}
			if ( is_wp_error( $content ) ) {
				$this->training_data_db->mark_as_error( $post_id, 500, $content->get_error_message() );
				return $content;
			}
			if ( empty( $content ) ) {
				$this->training_data_db->mark_as_error( $post_id, 404, __( 'Content not found', 'echo-knowledge-base' ) );
				return new WP_Error( 'invalid_content', __( 'Content not found', 'echo-knowledge-base' ), array( 'post_id' => $post_id ) );
			}
			
			$prepared = array(
				'content' => $content,
				'size' => strlen( $content )
			);

		} else {

			$post = get_post( $post_id );
			if ( ! $post ) {
				$this->training_data_db->mark_as_error( $post_id, 404, __( 'Post not found', 'echo-knowledge-base' ) );
				return new WP_Error( 'invalid_post', __( 'Post not found', 'echo-knowledge-base' ), array( 'post_id' => $post_id ) );
			}

			// Prepare content for regular posts
			$content_processor = new EPKB_AI_Content_Processor();
			$prepared = $content_processor->prepare_post( $post );
			if ( is_wp_error( $prepared ) ) {
				$error_code = $prepared->get_error_code() === 'post_not_published' ? 404 : 500;
				$this->training_data_db->mark_as_error( $post_id, $error_code, $prepared->get_error_message() );
				return $prepared; 
			}
			
			// Check for empty content (shouldn't happen if prepare_post is working correctly)
			if ( empty( $prepared['content'] ) ) {
				return new WP_Error( 'empty_content', __( 'Content is empty', 'echo-knowledge-base' ), array( 'post_id' => $post_id ) );
			}
		}
		
		// Calculate content hash - include summary status in hash to track changes
		$collection_config = EPKB_AI_Training_Data_Config_Specs::get_training_data_collection( $collection_id );
		if ( is_wp_error( $collection_config ) ) {
			return $collection_config;
		}

		// Summarization disabled for now - always use full content
		$is_summarized = false; // $collection_config['ai_training_data_use_summary'] === 'on';
		$content_hash = md5( $prepared['content'] . '_full' ); // ($is_summarized ? '_summarized' : '_full') );
		
		// Check if already exists in training data
		$existing = $this->training_data_db->get_training_data_by_item( $collection_id, $post_id );
		
		if ( $existing ) {

			// if user selected force update before: Skip if content hasn't changed - check both 'added' and 'updated' statuses
			/* if ( $existing->content_hash === $content_hash && in_array( $existing->status, array( 'added', 'updated' ), true ) ) {
				// Update last_synced timestamp even though content unchanged
				$this->training_data_db->update_training_data( $existing->id, array( 'last_synced' => gmdate( 'Y-m-d H:i:s' ) ) );
				return array( 'skipped' => true, 'reason' => 'unchanged' );
			} */
			
			// Mark as updating and clear any previous error message
			$this->training_data_db->update_training_data( $existing->id, array( 'status' => 'updating', 'error_message' => '' ) );
			
			$training_data_id = $existing->id;
			$is_update = true;

		} else {

			// Insert new record
			$training_data = array(
				'collection_id' => $collection_id,
				'item_id' => $post_id,
				'store_id' => $vector_store_id,
				'title' => $item_type === 'epkb_kb_files' ? '' : $post->post_title,	// TODO: get title from filter
				'type' => $item_type,
				'status' => 'adding',
				'content_hash' => $content_hash,
				'url' => $item_type === 'epkb_kb_files' ? '' : get_permalink( $post_id )
			);
			
			$training_data_id = $this->training_data_db->insert_training_data( $training_data );
			if ( is_wp_error( $training_data_id ) ) {
				$training_data_id->add_data( array( 'retry' => true ) );
				return $training_data_id;
			}
			
			$is_update = false;
		}

		// add the file content and attach to AI store
		try {
			
			// Check if summary mode is enabled for this collection
			/* if ( $collection_config && $is_summarized ) {
				// Apply content rewriting if summary mode is enabled
				$rewritten_content = $this->apply_content_rewrite( $post, $prepared, $collection_config );
				if ( is_wp_error( $rewritten_content ) ) {
					$this->handle_sync_error( $training_data_id, $rewritten_content );
					return $rewritten_content;
				}
				$prepared['content'] = $rewritten_content;
			} */

			$file_content = $this->openai_handler->create_file_content( $prepared );
			if ( is_wp_error( $file_content ) ) {
				$this->handle_sync_error( $training_data_id, $file_content );
				$file_content->add_data( array( 'retry' => true ) );
				return $file_content;
			}
			
			// Upload file to OpenAI
			$file_result = $this->openai_handler->upload_file( $post_id, $file_content, $item_type );
			if ( is_wp_error( $file_result ) ) {
				$this->handle_sync_error( $training_data_id, $file_result );
				$file_result->add_data( array( 'retry' => true ) );
				return $file_result;
			}
			
			$file_id = $file_result['id'];
			
			// Remove old file from vector store if updating
			if ( $is_update && ! empty( $existing->file_id ) ) {
				$result = $this->openai_handler->remove_file_from_vector_store( $vector_store_id, $existing->file_id );
				if ( is_wp_error( $result ) ) {
					// Clean up the newly uploaded file before returning error
					$this->openai_handler->delete_file( $file_id );
					$this->handle_sync_error( $training_data_id, $result );
					$result->add_data( array( 'retry' => true ) );
					return $result;
				}
			}
			
			// Add file to vector store
			$store_result = $this->openai_handler->add_file_to_vector_store( $vector_store_id, $file_id );
			if ( is_wp_error( $store_result ) ) {
				// Clean up uploaded file
				$this->openai_handler->delete_file( $file_id );
				$this->handle_sync_error( $training_data_id, $store_result );
				return $store_result;
			}
			
			// Delete old file if updating
			if ( $is_update && ! empty( $existing->file_id ) ) {
				$result = $this->openai_handler->delete_file( $existing->file_id );
				if ( is_wp_error( $result ) ) {
					$this->handle_sync_error( $training_data_id, $result );
					$result->add_data( array( 'retry' => true ) );
					return $result;
				}
			}
			
			// Mark as synced - content_hash includes summary status for change tracking
			$result = $this->training_data_db->mark_as_synced( $training_data_id, array(
				'file_id' => $file_id,
				'store_id' => $vector_store_id,
				'content_hash' => $content_hash // Hash includes '_summarized' or '_full' suffix
			) );
			if ( is_wp_error( $result ) ) {
				$this->handle_sync_error( $training_data_id, $result );
				$result->add_data( array( 'retry' => true ) );
				return $result;
			}
			
			$return_data = array(
				'success' => true,
				'file_id' => $file_id
			);

			// If summarization is enabled, include the summarized content for single row sync - Disabled for now
			// if ( $is_summarized && ! empty( $prepared['content'] ) ) {
			// 	$return_data['summarized_content'] = $prepared['content']; // This is the summarized content
			// }

			return $return_data;
			
		} catch ( Exception $e ) {
			$this->handle_sync_error( $training_data_id, new WP_Error( 'sync_exception', $e->getMessage() ) );
			$error = new WP_Error( 'sync_exception', $e->getMessage() );
			$error->add_data( array( 'retry' => true ) );
			return $error;
		}
	}


	/**
	 * Remove post from sync
	 *
	 * @param int $post_id Post ID
	 * @param int $collection_id Collection ID
	 * @return bool|WP_Error
	 */
	public function remove_post( $post_id, $collection_id ) {

		$collection_id = EPKB_AI_Validation::validate_collection_id( $collection_id );
		if ( is_wp_error( $collection_id ) ) {
			return $collection_id;
		}

		// Get existing training data
		$existing = $this->training_data_db->get_training_data_by_item( $collection_id, $post_id );
		if ( ! $existing ) {
			return true; // Already removed
		}

		// Remove from vector store
		if ( ! empty( $existing->store_id ) && ! empty( $existing->file_id ) ) {
			$result = $this->openai_handler->remove_file_from_vector_store( $existing->store_id, $existing->file_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Delete file from OpenAI
		if ( ! empty( $existing->file_id ) ) {
			$result = $this->openai_handler->delete_file( $existing->file_id );
			if ( is_wp_error( $result ) ) {
				EPKB_AI_Log::add_log( $result, array( 'training_data_id' => $existing->id, 'file_id' => $existing->file_id, 'message' => 'Failed to delete file from OpenAI' ) );
			}
		}

		// Delete from database
		return $this->training_data_db->delete_training_data_record( $existing->id );
	}

	/**
	 * Handle sync error
	 *
	 * @param int $training_data_id Training data ID
	 * @param WP_Error $wp_error Error object
	 * @return void
	 */
	private function handle_sync_error( $training_data_id, $wp_error ) {

		// Log the full error for debugging
		EPKB_AI_Log::add_log( $wp_error, array( 'training_data_id' => $training_data_id ) );

		// Use centralized error mapping
		$mapped = EPKB_AI_Log::map_error_to_internal_code( $wp_error );
		$error_code = isset( $mapped['code'] ) ? $mapped['code'] : 500;
		$error_message = isset( $mapped['message'] ) ? $mapped['message'] : $wp_error->get_error_message();
		$this->training_data_db->mark_as_error( $training_data_id, $error_code, $error_message );
	}

	/**
	 * Get posts to sync
	 *
	 * @param int $collection_id Collection ID
	 * @param array $options Sync options
	 * @return array Post IDs
	 */
	public function get_posts_to_sync( $collection_id, $options = array() ) {

		// Get collection configuration
		$collection_config = EPKB_AI_Training_Data_Config_Specs::get_training_data_collection( $collection_id );
		if ( is_wp_error( $collection_config ) ) {
			$collection_config = EPKB_AI_Training_Data_Config_Specs::get_default_collection_config();
		}

		// Get configured post types for this collection
		$configured_post_types = $collection_config['ai_training_data_store_post_types'];
		if ( empty( $configured_post_types ) ) {
			return array();
		}

		$args = array(
			'post_type' => $configured_post_types,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false
		);

		// Add specific post IDs if provided
		if ( ! empty( $options['post_ids'] ) ) {
			$args['post__in'] = $options['post_ids'];
		}

		// Handle sync type
		if ( ! empty( $options['sync_type'] ) ) {
			switch ( $options['sync_type'] ) {
				case 'incremental':
					// Sync only recently modified articles
					if ( isset( $options['since'] ) ) {
						// Use specific timestamp if provided
						$args['date_query'] = array(
							array(
								'column' => 'post_modified',
								'after' => date( 'Y-m-d H:i:s', $options['since'] )
							)
						);
					} else {
						// Use date range in days
						$date_range = isset( $options['date_range'] ) ? intval( $options['date_range'] ) : 7;
						$args['date_query'] = array(
							array(
								'column' => 'post_modified',
								'after' => $date_range . ' days ago',
							)
						);
					}
					break;

				case 'retry':
					// Get failed items from training data DB
					$failed_items = $this->training_data_db->get_items_needing_sync( 1000 );
					$post_ids = wp_list_pluck( $failed_items, 'item_id' );
					if ( empty( $post_ids ) ) {
						return array(); // No failed items to retry
					}
					$args['post__in'] = $post_ids;
					break;
			}
		}

		// Limit posts for development mode
		if ( ! empty( $options['limit_posts'] ) && $options['limit_posts'] > 0 ) {
			$args['posts_per_page'] = intval( $options['limit_posts'] );
		}

		$query = new WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Apply content rewriting based on collection configuration
	 *
	 * Uses OpenAI to rewrite content based on the configured prompt template.
	 *
	 * @param $post
	 * @param array $prepared Prepared content data
	 * @param array $collection_config Collection configuration
	 * @return string|WP_Error Rewritten content or error
	 */
	private function apply_content_rewrite( $post, $prepared, $collection_config ) {

		$instructions = 'TODO';

		// Call OpenAI to rewrite the content
		$rewritten_content = EPKB_AI_OpenAI_Handler::call_openai_for_rewrite( $instructions, $prepared['content'] );
		if ( is_wp_error( $rewritten_content ) ) {
			// Log error but return original content to continue sync
			EPKB_AI_Log::add_log( $rewritten_content, array( 'post_id' => $post->ID, 'action' => 'content_rewrite' ) );
			return $prepared['content'];
		}
		
		// Validate the rewritten content
		if ( ! $this->validate_rewritten_content( $rewritten_content ) ) {
			// Log validation failure and return original content
			EPKB_AI_Log::add_log( new WP_Error( 'validation_failed', 'Rewritten content failed validation' ), array( 
				'post_id' => $post->ID, 
				'action' => 'content_rewrite',
				'rewritten_content' => substr( $rewritten_content, 0, 200 ) . '...'
			) );
			return $prepared['content'];
		}
		
		return $rewritten_content;
	}

	/**
	 * Validate rewritten content
	 * 
	 * @param string $content Rewritten content to validate
	 * @return bool True if valid, false otherwise
	 */
	private function validate_rewritten_content( $content ) {
		
		// Ensure content is meaningful (at least 50 characters)
		if ( strlen( $content ) < 50 ) {
			return false;
		}
		
		// Check for common AI failure patterns
		$failure_patterns = array(
			'I cannot',
			'I can\'t',
			'As an AI',
			'I\'m sorry',
			'I don\'t have access',
			'I\'m unable to',
			// TODO 'The document'
		);
		
		foreach ( $failure_patterns as $pattern ) {
			if ( stripos( $content, $pattern ) !== false ) {
				return false;
			}
		}
		
		// Ensure it's not just the prompt repeated (check for common prompt markers)
		$prompt_markers = array(
			'Title:',
			'Content:',
			'Summary:',
			'Rewrite the following',
			'Summarize the following'
		);
		
		foreach ( $prompt_markers as $marker ) {
			if ( stripos( $content, $marker ) !== false ) {
				return false;
			}
		}
		
		return true;
	}
}