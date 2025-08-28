<?php

/**
 * OpenAI Handler
 * 
 * Provides high-level operations for OpenAI API including vector stores and files.
 * Wraps the OpenAI client and vector store service for use by the sync manager.
 */
class EPKB_AI_OpenAI_Handler {

	const VECTOR_STORES_ENDPOINT = '/vector_stores';
	const FILES_ENDPOINT = '/files';
	const OPENAI_BETA_HEADERS = [ 'OpenAI-Beta' => 'assistants=v2' ];

	/**
	 * OpenAI client
	 * @var EPKB_OpenAI_Client
	 */
	private $client;

	public function __construct() {
		$this->client = new EPKB_OpenAI_Client();
	}
	
	/**
	 * Create a vector store
	 *
	 * @param array $data Vector store data with 'name' and optional 'metadata'
	 * @return array|WP_Error Vector store object with 'id' or error
	 */
	public function create_vector_store( $data ) {

		$vector_store_data = array(
			'name' => $data['name'],
			//'metadata' => EPKB_AI_Validation::validate_metadata( $data )
		);

		return $this->client->request( self::VECTOR_STORES_ENDPOINT, $vector_store_data, 'POST', self::OPENAI_BETA_HEADERS );
	}

	/**
	 * Get or create vector store
	 *
	 * @param int $collection_id Collection ID
	 * @return string|WP_Error Vector store ID or error
	 */
	public function get_or_create_vector_store( $collection_id ) {

		// Get collection configuration
		$collection_config = EPKB_AI_Training_Data_Config_Specs::get_training_data_collection( $collection_id );
		if ( is_wp_error( $collection_config ) ) {
			return $collection_config;
		}

		// Check if vector store already exists for this collection
		$existing_store_id = $collection_config['ai_training_data_store_id'];
		if ( ! empty( $existing_store_id ) ) {
			// Verify the store still exists in OpenAI
			$store_info = $this->get_vector_store_info( $collection_id );
			if ( ! is_wp_error( $store_info ) ) {
				return $existing_store_id;
			}

			// If store doesn't exist anymore, clear it from the collection
			$collection_config['ai_training_data_store_id'] = '';
			$collection_config['override_vector_store_id'] = true; // Allow overriding the vector store ID
			$save_result = EPKB_AI_Training_Data_Config_Specs::update_training_data_collection( $collection_id, $collection_config );
			if ( is_wp_error( $save_result ) ) {
				return $save_result;
			}
		}

		// Create new vector store
		$store_name = empty( $collection_config['ai_training_data_store_name'] ) ? EPKB_AI_Training_Data_Config_Specs::get_default_collection_name( $collection_id ) : $collection_config['ai_training_data_store_name'];

		$response = $this->create_vector_store( array(
			'name' => $store_name,
			'metadata' => array(
				'collection_id' => strval( $collection_id ),
				'kb_id' => strval( $collection_id ),
				'created_by' => 'echo_kb'
			)
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Save vector store ID in the collection configuration
		$collection_config['ai_training_data_store_id'] = $response['id'];
		$collection_config['override_vector_store_id'] = true; // Allow overriding the vector store ID
		$save_result = EPKB_AI_Training_Data_Config_Specs::update_training_data_collection( $collection_id, $collection_config );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		return $response['id'];
	}

	/**
	 * Create file content for OpenAI
	 *
	 * @param array $processed Processed content data
	 * @return string|WP_Error File content or error if content would exceed size limit
	 */
	public function create_file_content( $processed ) {

		// Check if content size would exceed the limit
		$content_size = strlen( $processed['content'] );
		if ( $content_size > EPKB_OpenAI_Client::MAX_FILE_SIZE ) {
			return new WP_Error( 'content_too_large', sprintf( __( 'Content size (%s) exceeds maximum allowed size (%s)', 'echo-knowledge-base' ),
					size_format( $content_size ), size_format( EPKB_OpenAI_Client::MAX_FILE_SIZE ) ) );
		}

		return $processed['content'];
	}

	/**
	 * Upload a file to OpenAI
	 *
	 * @param string $content File content
	 * @param string $purpose File purpose (e.g., 'assistants')
	 * @param string $file_name Optional filename
	 * @return array|WP_Error File object with 'id' or error
	 */
	public function upload_file( $id, $content, $file_type, $purpose = 'assistants', $file_name = null ) {
		
		if ( empty( $content ) ) {
			return new WP_Error( 'empty_content', __( 'File content cannot be empty', 'echo-knowledge-base' ) );
		}
		
		// Validate file size
		$content_size = strlen( $content );
		if ( $content_size > EPKB_OpenAI_Client::MAX_FILE_SIZE ) {
			return new WP_Error( 
				'file_too_large', 
				sprintf( 
					__( 'File size (%s) exceeds maximum allowed size (%s)', 'echo-knowledge-base' ),
					size_format( $content_size ),
					size_format( EPKB_OpenAI_Client::MAX_FILE_SIZE )
				)
			);
		}
		
		// Map KB post types to 'article' for clarity, use the actual type for others
		// KB post types follow pattern: epkb_post_type_1, epkb_post_type_2, etc.
		if ( strpos( $file_type, 'epkb_post_type_' ) === 0 ) {
			$safe_type = 'article';
		} else {
			// WordPress post_type is already slug-like (lowercase, underscores, safe)
			// Just do minimal sanitization to ensure it's safe for OpenAI API
			$safe_type = preg_replace( '/[^a-z0-9_-]/', '_', strtolower( $file_type ) );
		}
		
		// Default to 'article' if empty
		if ( empty( $safe_type ) ) {
			$safe_type = 'article';
		}

		// Generate filename if not provided
		if ( empty( $file_name ) ) {
			$file_name = 'kb_' . $safe_type . '_' . $id . '_' . time() . '.txt';
		}
		
		// Use the client's upload_file method which properly handles multipart form data
		$fields = array(
			'purpose' => $purpose
		);
		
		return $this->client->upload_file( self::FILES_ENDPOINT, $content, $file_name, $fields );
	}
	
	/**
	 * Add a file to a vector store
	 *
	 * @param string $vector_store_id Vector store ID
	 * @param string $file_id File ID
	 * @return array|WP_Error Vector store file object with 'id' or error
	 */
    public function add_file_to_vector_store( $vector_store_id, $file_id, $max_wait = 90 ) {
		
		if ( empty( $vector_store_id ) || empty( $file_id ) ) {
			return new WP_Error( 'missing_params', __( 'Vector store ID and file ID are required', 'echo-knowledge-base' ) );
		}
		
		$data = array(
			'file_id' => $file_id
		);
		
		$response = $this->client->request( self::VECTOR_STORES_ENDPOINT . "/{$vector_store_id}" . self::FILES_ENDPOINT, $data, 'POST', self::OPENAI_BETA_HEADERS );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
        // Wait for file to be processed
        $file_status = $this->wait_for_file_processing( $vector_store_id, $response['id'], $max_wait );
		if ( is_wp_error( $file_status ) ) {
			return $file_status;
		}
		
		return $response;
	}

	/**
	 * Remove a file from a vector store
	 *
	 * @param string $vector_store_id Vector store ID
	 * @param string $file_id File ID
	 * @return bool|WP_Error True on success or error
	 */
	public function remove_file_from_vector_store( $vector_store_id, $file_id ) {
		
		if ( empty( $vector_store_id ) || empty( $file_id ) ) {
			return new WP_Error( 'missing_params', __( 'Vector store ID and file ID are required', 'echo-knowledge-base' ) );
		}
		
		$response = $this->client->request(
			self::VECTOR_STORES_ENDPOINT . "/{$vector_store_id}/" . self::FILES_ENDPOINT . "/{$file_id}",
			array(),
			'DELETE',
			self::OPENAI_BETA_HEADERS
		);
		
		if ( is_wp_error( $response ) ) {
			// Ignore 404 errors - file already removed
			if ( $response->get_error_code() === 'not_found' ) {
				// TODO log this as a warning
				return true;
			}
			return $response;
		}
		
		return true;
	}
	
	/**
	 * Delete a file from OpenAI
	 *
	 * @param string $file_id File ID
	 * @return bool|WP_Error True on success or error
	 */
	public function delete_file( $file_id ) {
		
		if ( empty( $file_id ) ) {
			return new WP_Error( 'missing_id', __( 'File ID is required', 'echo-knowledge-base' ) );
		}
		
		$response = $this->client->request( self::FILES_ENDPOINT . "/{$file_id}", array(), 'DELETE' );
		if ( is_wp_error( $response ) ) {
			// Ignore 404 errors - file already deleted
			if ( $response->get_error_code() === 'not_found' ) {
				return true;
			}
			return $response;
		}
		
		return true;
	}
	
	/**
	 * Delete a vector store
	 *
	 * @param string $vector_store_id Vector store ID
	 * @return bool|WP_Error True on success or error
	 */
	public function delete_vector_store( $vector_store_id ) {
		
		if ( empty( $vector_store_id ) ) {
			return new WP_Error( 'missing_id', __( 'Vector store ID is required', 'echo-knowledge-base' ) );
		}
		
		$response = $this->client->request( self::VECTOR_STORES_ENDPOINT . "/{$vector_store_id}", array(), 'DELETE', self::OPENAI_BETA_HEADERS );
		if ( is_wp_error( $response ) ) {
			// Ignore 404 errors - vector store already deleted
			if ( $response->get_error_code() === 'not_found' ) {
				return true;
			}
			return $response;
		}
		
		// Check if deletion was successful
		if ( isset( $response['deleted'] ) && $response['deleted'] === true ) {
			return true;
		}
		
		return new WP_Error( 'delete_failed', __( 'Failed to delete vector store', 'echo-knowledge-base' ) . ' ' . $vector_store_id );
	}
	
	/**
	 * Wait for file processing in vector store
	 *
	 * @param string $vector_store_id Vector store ID
	 * @param string $file_id File ID
	 * @param int $max_wait Maximum wait time in seconds (default 90 = 1.5 minutes)
	 * @return bool|WP_Error True when ready or error
	 */
	private function wait_for_file_processing( $vector_store_id, $file_id, $max_wait = 90 ) {
		
		$start_time = time();
		
		while ( ( time() - $start_time ) < $max_wait ) {
			
			$response = $this->client->request(
				self::VECTOR_STORES_ENDPOINT . "/{$vector_store_id}/" . self::FILES_ENDPOINT . "/{$file_id}",
				array(),
				'GET',
				self::OPENAI_BETA_HEADERS
			);
			
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			
			// Check status
			if ( isset( $response['status'] ) ) {
				if ( $response['status'] === 'completed' ) {
					return true;
				} elseif ( $response['status'] === 'failed' ) {
					$error_msg = isset( $response['last_error']['message'] ) ? $response['last_error']['message'] : __( 'File processing failed', 'echo-knowledge-base' );
					return new WP_Error( 'processing_failed', $error_msg );
				}
			}
			
			// Wait before next check
			EPKB_AI_Utilities::safe_sleep( 1 );
		}
		
		return new WP_Error( 'timeout', __( 'File processing timed out', 'echo-knowledge-base' ) );
	}
	
	/**
	 * Calculate exponential backoff delay
	 *
	 * @param int $retry_count Current retry attempt (0-based)
	 * @param int $base_delay Base delay in seconds
	 * @param int $max_delay Maximum delay in seconds
	 * @param WP_Error|null $error Optional error object that may contain retry-after header
	 * @return int Delay in seconds
	 */
	public static function calculate_backoff_delay( $retry_count, $base_delay = 1, $max_delay = 60, $error = null ) {
		$calculated_delay = 0;
		$server_hint_delay = 0;
		
		// Calculate exponential backoff with jitter
		$exponential_delay = $base_delay * pow( 2, $retry_count );
		// Add jitter (0-25% of delay)
		$jitter = $exponential_delay * ( mt_rand( 0, 25 ) / 100 );
		$calculated_delay = $exponential_delay + $jitter;
		
		// Check for Retry-After header in error data (highest priority)
		if ( is_wp_error( $error ) ) {
			$error_data = $error->get_error_data();
			if ( ! empty( $error_data['retry_after'] ) ) {
				// Retry-After can be seconds or HTTP date
				$retry_after = $error_data['retry_after'];
				if ( is_numeric( $retry_after ) ) {
					// It's seconds
					$server_hint_delay = intval( $retry_after );
				} else {
					// It's an HTTP date, parse it
					$retry_time = strtotime( $retry_after );
					if ( $retry_time !== false ) {
						$server_hint_delay = max( 0, $retry_time - time() );
					}
				}
			}
		}
		
		// Check for X-RateLimit-Reset from transient (second priority)
		if ( $server_hint_delay === 0 ) {
			$rate_limit_info = get_transient( 'epkb_openai_rate_limit' );
			if ( ! empty( $rate_limit_info['reset_in'] ) && $rate_limit_info['remaining'] === 0 ) {
				// Use actual reset time from OpenAI headers
				$server_hint_delay = $rate_limit_info['reset_in'] + 1;
			}
		}
		
		// Use the maximum of calculated backoff and server hint
		$delay = max( $calculated_delay, $server_hint_delay );
		
		// Cap at max delay
		return min( $delay, $max_delay );
	}
	
	/**
	 * Check if OpenAI API error is retryable
	 * 
	 * This determines whether the server should retry a failed OpenAI API request.
	 * Note: This is different from EPKB_AI_Log::is_retryable_error() which determines
	 * if the client should retry a request to our REST API.
	 *
	 * @param WP_Error $error
	 * @return bool
	 */
	public static function is_retryable_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}
		
		$error_code = $error->get_error_code();
		$error_data = $error->get_error_data();
		$http_code = null;
		
		// Extract HTTP status code if available
		if ( isset( $error_data['response']['code'] ) ) {
			$http_code = $error_data['response']['code'];
		}
		
		// Use centralized logic from EPKB_AI_Log for consistency
		// But apply OpenAI-specific rules
		$is_retryable = EPKB_AI_Log::is_retryable_error( $error_code, $http_code );
		
		// OpenAI-specific overrides:
		// - Don't retry 401/403 auth errors (API key issues)
		if ( $http_code === 401 || $http_code === 403 ) {
			return false;
		}
		
		// - Don't retry insufficient_quota errors (billing issues) or invalid API key
		if ( $error_code === 'insufficient_quota' || $error_code === 'invalid_api_key' ) {
			return false;
		}
		
		// - Don't retry incomplete responses (won't be fixed by retrying)
		if ( $error_code === 'response_incomplete' ) {
			return false;
		}
		
		return $is_retryable;
	}
	
	/**
	 * Get vector store info
	 *
	 * @param int $collection_id Collection ID
	 * @return array|WP_Error Vector store info or error
	 */
	public function get_vector_store_info( $collection_id ) {
		
		// Get collection configuration
		$collection_config = EPKB_AI_Training_Data_Config_Specs::get_training_data_collection( $collection_id );
		if ( is_wp_error( $collection_config ) ) {
			return $collection_config;
		}
		
		// Get vector store ID from collection config
		$vector_store_id = isset( $collection_config['ai_training_data_store_id'] ) ? $collection_config['ai_training_data_store_id'] : '';
		if ( empty( $vector_store_id ) ) {
			return new WP_Error( 'no_vector_store', __( 'No vector store found', 'echo-knowledge-base' ) );
		}
		
		return $this->get_vector_store_info_by_id( $vector_store_id );
	}

	/**
	 * Get vector store info by store id
	 *
	 * @param string $vector_store_id
	 * @return array|WP_Error
	 */
	public function get_vector_store_info_by_id( $vector_store_id ) {

		// Get vector store details from OpenAI
		$response = $this->client->request( self::VECTOR_STORES_ENDPOINT . '/' . $vector_store_id, array(), 'GET', self::OPENAI_BETA_HEADERS );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Get file count
		$files_response = $this->client->request( self::VECTOR_STORES_ENDPOINT . '/' . $vector_store_id . '/files', array( 'limit' => 1 ), 'GET', self::OPENAI_BETA_HEADERS );
		$file_count = 0;
		if ( ! is_wp_error( $files_response ) && isset( $files_response['data'] ) ) {
			$file_count = isset( $files_response['total'] ) ? $files_response['total'] : count( $files_response['data'] );
		}

		return array(
			'id' => $response['id'],
			'name' => isset( $response['name'] ) ? $response['name'] : '',
			'status' => isset( $response['status'] ) ? $response['status'] : 'unknown',
			'file_counts' => array(
				'total' => $file_count,
				'in_progress' => isset( $response['file_counts']['in_progress'] ) ? $response['file_counts']['in_progress'] : 0,
				'completed' => isset( $response['file_counts']['completed'] ) ? $response['file_counts']['completed'] : 0,
				'failed' => isset( $response['file_counts']['failed'] ) ? $response['file_counts']['failed'] : 0,
				'cancelled' => isset( $response['file_counts']['cancelled'] ) ? $response['file_counts']['cancelled'] : 0
			),
			'created_at' => isset( $response['created_at'] ) ? $response['created_at'] : '',
			'metadata' => isset( $response['metadata'] ) ? $response['metadata'] : array()
		);
	}
	
	/**
	 * Verify vector store has all files processed and ready
	 *
	 * @param string $vector_store_id Vector store ID
	 * @param int $expected_files Expected number of files
	 * @param int $max_wait Maximum wait time in seconds
	 * @return bool|WP_Error True when ready or error
	 */
	public function verify_vector_store_ready( $vector_store_id, $expected_files, $max_wait = 120 ) {
		
		$start_time = time();
		
		while ( ( time() - $start_time ) < $max_wait ) {
			
			// Get vector store info
			$response = $this->client->request( self::VECTOR_STORES_ENDPOINT . "/{$vector_store_id}", array(), 'GET', self::OPENAI_BETA_HEADERS );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			
			// Check file counts
			if ( isset( $response['file_counts'] ) ) {
				$completed = isset( $response['file_counts']['completed'] ) ? $response['file_counts']['completed'] : 0;
				$in_progress = isset( $response['file_counts']['in_progress'] ) ? $response['file_counts']['in_progress'] : 0;
				$failed = isset( $response['file_counts']['failed'] ) ? $response['file_counts']['failed'] : 0;
				
				// Check if any files failed
				if ( $failed > 0 ) {
					return new WP_Error( 'files_failed', sprintf( __( '%d files failed to process in vector store', 'echo-knowledge-base' ), $failed ) );
				}
				
				// Check if all files are completed
				if ( $completed >= $expected_files && $in_progress === 0 ) {
					return true;
				}
				
				// If we're close to timeout, provide detailed status
				if ( ( time() - $start_time ) >= ( $max_wait - 5 ) ) {
					return new WP_Error( 'incomplete_processing', sprintf( __( 'Vector store incomplete: Expected %d files, but only %d completed, %d in progress, %d failed', 'echo-knowledge-base' ), $expected_files, $completed, $in_progress, $failed 
					) );
				}
			}
			
			// Wait before next check
			EPKB_AI_Utilities::safe_sleep( 2 );
		}
		
		return new WP_Error( 'timeout', sprintf( __( 'Vector store processing timed out. Expected %d files, but processing did not complete.', 'echo-knowledge-base' ), $expected_files ) );
	}
	
	/**
	 * Update vector store
	 *
	 * @param string $vector_store_id Vector store ID
	 * @param array $data Data to update (e.g., 'name')
	 * @return array|WP_Error Updated vector store object or error
	 */
	public function update_vector_store( $vector_store_id, $data ) {
		
		if ( empty( $vector_store_id ) ) {
			return new WP_Error( 'missing_id', __( 'Vector store ID is required', 'echo-knowledge-base' ) );
		}
		
		$update_data = array();
		if ( isset( $data['name'] ) ) {
			$update_data['name'] = $data['name'];
		}
		
		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_data', __( 'Vector store name is required', 'echo-knowledge-base' ) );
		}
		
		$response = $this->client->request(
			self::VECTOR_STORES_ENDPOINT . "/{$vector_store_id}",
			$update_data,
			'POST',
			self::OPENAI_BETA_HEADERS
		);
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		return $response;
	}
	
	/**
	 * Reset vector store
	 * 
	 * Removes all files from a vector store and optionally deletes the vector store itself.
	 * This effectively clears all training data associated with the collection,
	 * allowing for a fresh start with new or updated content.
	 *
	 * @param int $collection_id Collection ID
	 * @return bool|WP_Error True on success or error
	 */
	public function reset_vector_store( $collection_id ) {
		
		// Get vector store ID from AI config
		$vector_store_id = EPKB_AI_Config_Specs::get_ai_config_value( 'ai_training_data_store_id', '' );
		if ( empty( $vector_store_id ) ) {
			// No vector store to reset
			return true;
		}
		
		// Get all files in the vector store
		$limit = 100;
		$after = null;
		$all_removed = true;
		
		do {
			$params = array( 'limit' => $limit );
			if ( $after ) {
				$params['after'] = $after;
			}
			
			$files_response = $this->client->request(
				self::VECTOR_STORES_ENDPOINT . '/' . $vector_store_id . '/files',
				$params,
				'GET',
				self::OPENAI_BETA_HEADERS
			);
			
			if ( is_wp_error( $files_response ) ) {
				// If vector store doesn't exist, consider it reset
				if ( $files_response->get_error_code() === 'not_found' ) {
					EPKB_AI_Config_Specs::update_ai_config_value( 'ai_training_data_store_id', '' );
					return true;
				}
				return $files_response;
			}
			
			if ( ! isset( $files_response['data'] ) || ! is_array( $files_response['data'] ) ) {
				break;
			}
			
			// Remove each file from vector store
			foreach ( $files_response['data'] as $file ) {
				if ( ! isset( $file['id'] ) ) {
					continue;
				}
				
				$remove_result = $this->remove_file_from_vector_store( $vector_store_id, $file['id'] );
				if ( is_wp_error( $remove_result ) ) {
					$all_removed = false;
				}
				
				// Also delete the file from OpenAI if it exists
				if ( isset( $file['file_id'] ) ) {
					$this->delete_file( $file['file_id'] );
				}
			}
			
			// Check if there are more files
			$has_more = isset( $files_response['has_more'] ) && $files_response['has_more'];
			if ( $has_more && isset( $files_response['last_id'] ) ) {
				$after = $files_response['last_id'];
			} else {
				break;
			}
			
		} while ( $has_more );
		
		// Optionally delete the vector store itself
		// For now, we keep the vector store and just remove all files
		// This allows us to reuse the same vector store ID
		
		if ( ! $all_removed ) {
			return new WP_Error( 'partial_reset', __( 'Some files could not be removed from the vector store', 'echo-knowledge-base' ) );
		}
		
		return true;
	}

	/**
	 * Call OpenAI API to rewrite content
	 *
	 * @param string $instructions Instructions for the AI to follow
	 * @param string $original_content Original content
	 * @return string|WP_Error Rewritten content or error
	 */
	public static function call_openai_for_rewrite( $instructions, $original_content ) {
		
		// Calculate max tokens based on input length
		$input_length = strlen( $original_content );
		if ( $input_length > 5000 ) {
			$max_output_tokens = 800;
		} elseif ( $input_length > 2000 ) {
			$max_output_tokens = 500;
		} else {
			$max_output_tokens = 300;
		}
		
		// Prepare the request
		$request = array(
			'model' => EPKB_OpenAI_Client::DEFAULT_MODEL,
			'messages' => array(
				array(
					'role' => 'system',
					'content' => $instructions
				),
				array(
					'role' => 'user',
					'content' => $original_content
				)
			),
		);
		
		// Apply model-specific parameters using the generic method
		$params = array(
			'temperature' => 0.3, // Low temperature for consistency
			//'max_output_tokens' => $max_output_tokens
		);
		$request = EPKB_OpenAI_Client::apply_model_parameters( $request, EPKB_OpenAI_Client::DEFAULT_MODEL, $params );
		
		// Make the API call
		$client = new EPKB_OpenAI_Client();
		$response = $client->request( '/chat/completions', $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		// Extract the content from response
		if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from AI service', 'echo-knowledge-base' ) );
		}
		
		return trim( $response['choices'][0]['message']['content'] );
	}
}